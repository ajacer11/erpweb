<?php
/**
 * api_web_accesos.php - Accesos de clientes al área de clientes de la web (web_usuarios)
 * y solicitudes de registro hechas desde la web (web_solicitudes_registro).
 *
 * Un acceso se puede dar de dos formas:
 *  - Invitación: se manda un mail con un link para que el cliente cree su contraseña (72 hs).
 *  - Manual: se le asigna una contraseña desde acá y se le pasa por otro medio.
 * El cliente entra con su usuario o con su mail.
 */
require_once 'check_api_access.php';
require_once 'web_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$cuit = (string) $_SESSION['tenant_cuit'];
$dbId = (int) ($_SESSION['tenant_db_id'] ?? 0);

function jsonIn(): array
{
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) throw new Exception('Datos inválidos.');
    return $in;
}

function validarClave(string $clave): void
{
    if (mb_strlen($clave) < 8) throw new Exception('La contraseña debe tener al menos 8 caracteres.');
}

/** Crea el acceso y devuelve su id. */
function crearAcceso(PDO $pdo, int $clienteId, string $nombreUsuario, string $email, ?string $clave, string $usuarioAlta): int
{
    $nombreUsuario = trim($nombreUsuario);
    $email = trim(mb_strtolower($email));
    if (!preg_match('/^[A-Za-z0-9._@\-]{3,50}$/', $nombreUsuario)) {
        throw new Exception('El usuario debe tener entre 3 y 50 caracteres (letras, números, punto, guion o @).');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('El mail no es válido.');

    $st = $pdo->prepare("SELECT id FROM alb_clientes WHERE id = ?");
    $st->execute([$clienteId]);
    if (!$st->fetch()) throw new Exception('Cliente inexistente.');

    $st = $pdo->prepare("SELECT COUNT(*) FROM web_usuarios WHERE nombre_usuario = ?");
    $st->execute([$nombreUsuario]);
    if ($st->fetchColumn() > 0) throw new Exception("El usuario \"$nombreUsuario\" ya existe.");
    if ($email !== '') {
        $st = $pdo->prepare("SELECT COUNT(*) FROM web_usuarios WHERE LOWER(email) = ?");
        $st->execute([$email]);
        if ($st->fetchColumn() > 0) throw new Exception("El mail $email ya está usado por otro acceso.");
    }

    // Sin contraseña (invitación): un hash imposible de adivinar hasta que el cliente cree la suya.
    $hash = $clave !== null ? password_hash($clave, PASSWORD_DEFAULT) : '!' . bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO web_usuarios (cliente_id, nombre_usuario, password_hash, email, rol, activo, fecha_alta, usuario_alta)
                   VALUES (?, ?, ?, ?, 'cliente', 1, NOW(), ?)")
        ->execute([$clienteId, $nombreUsuario, $hash, $email ?: null, $usuarioAlta]);
    return (int) $pdo->lastInsertId();
}

try {
    if (!webAddonHabilitado($pdo_master, $cuit)) {
        throw new Exception('La web de clientes no está contratada para esta empresa.');
    }

    switch ($action) {
        case 'listar':
            $accesos = $pdo->query("
                SELECT w.id, w.cliente_id, w.nombre_usuario, w.email, w.activo, w.ultimo_login, w.fecha_alta,
                       (w.password_hash LIKE '!%') AS sin_clave,
                       (w.token_tipo = 'invitacion' AND w.token_vence > NOW()) AS invitacion_vigente,
                       (w.bloqueado_hasta IS NOT NULL AND w.bloqueado_hasta > NOW()) AS bloqueado,
                       c.razon, c.numdoc, c.ListaDePRecios AS lista,
                       (SELECT COUNT(*) FROM web_pedidos p WHERE p.web_usuario_id = w.id) AS pedidos
                FROM web_usuarios w
                LEFT JOIN alb_clientes c ON c.id = w.cliente_id
                ORDER BY c.razon, w.nombre_usuario")->fetchAll(PDO::FETCH_ASSOC);
            $solicitudes = $pdo->query("SELECT * FROM web_solicitudes_registro WHERE estado = 'pendiente' ORDER BY fecha DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data' => $accesos,
                'solicitudes' => $solicitudes,
                'url_web' => webUrlBase(webDominioDeEmpresa($pdo_master, $cuit, $dbId)),
            ]);
            break;

        case 'buscar_clientes':
            $q = trim($_GET['q'] ?? '');
            $st = $pdo->prepare("SELECT id, CONCAT(razon, IF(numdoc <> '', CONCAT(' (', numdoc, ')'), '')) AS text, mail, numdoc
                                 FROM alb_clientes WHERE habilitado = 1 AND (razon LIKE ? OR numdoc LIKE ? OR id = ?)
                                 ORDER BY razon LIMIT 30");
            $st->execute(["%$q%", "%$q%", ctype_digit($q) ? (int) $q : -1]);
            echo json_encode(['results' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'crear':
            $in = jsonIn();
            $modo = $in['modo'] ?? 'invitacion';
            $clave = null;
            if ($modo === 'manual') {
                $clave = (string) ($in['clave'] ?? '');
                validarClave($clave);
            } elseif (!filter_var($in['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Para mandar la invitación hace falta un mail válido.');
            }
            $pdo->beginTransaction();
            $id = crearAcceso($pdo, (int) ($in['cliente_id'] ?? 0), (string) ($in['usuario'] ?? ''), (string) ($in['email'] ?? ''), $clave, $usuario);
            if (!empty($in['solicitud_id'])) {
                $pdo->prepare("UPDATE web_solicitudes_registro SET estado = 'aprobada', cliente_id = ?, fecha_resolucion = NOW(), usuario_resolucion = ? WHERE id = ? AND estado = 'pendiente'")
                    ->execute([(int) $in['cliente_id'], $usuario, (int) $in['solicitud_id']]);
            }
            $pdo->commit();

            $msg = 'Acceso creado.';
            if ($modo !== 'manual') {
                [$ok, $err] = webEnviarInvitacion($pdo, $pdo_master, $cuit, $dbId, $id);
                $msg = $ok ? 'Acceso creado. Le mandamos la invitación por mail.' : "Acceso creado, pero no se pudo mandar la invitación: $err";
            }
            echo json_encode(['success' => true, 'id' => $id, 'message' => $msg]);
            break;

        case 'invitar':
            $id = (int) (jsonIn()['id'] ?? 0);
            [$ok, $err] = webEnviarInvitacion($pdo, $pdo_master, $cuit, $dbId, $id);
            if (!$ok) throw new Exception("No se pudo mandar la invitación: $err");
            echo json_encode(['success' => true, 'message' => 'Invitación enviada.']);
            break;

        case 'cambiar_clave':
            $in = jsonIn();
            $clave = (string) ($in['clave'] ?? '');
            validarClave($clave);
            $pdo->prepare("UPDATE web_usuarios SET password_hash = ?, token_hash = NULL, token_tipo = NULL, token_vence = NULL,
                           intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")
                ->execute([password_hash($clave, PASSWORD_DEFAULT), (int) ($in['id'] ?? 0)]);
            echo json_encode(['success' => true, 'message' => 'Contraseña actualizada.']);
            break;

        case 'editar':
            $in = jsonIn();
            $id = (int) ($in['id'] ?? 0);
            $email = trim(mb_strtolower((string) ($in['email'] ?? '')));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('El mail no es válido.');
            if ($email !== '') {
                $st = $pdo->prepare("SELECT COUNT(*) FROM web_usuarios WHERE LOWER(email) = ? AND id <> ?");
                $st->execute([$email, $id]);
                if ($st->fetchColumn() > 0) throw new Exception("El mail $email ya está usado por otro acceso.");
            }
            $pdo->prepare("UPDATE web_usuarios SET email = ?, activo = ?, intentos_fallidos = IF(?, 0, intentos_fallidos), bloqueado_hasta = IF(?, NULL, bloqueado_hasta) WHERE id = ?")
                ->execute([$email ?: null, !empty($in['activo']) ? 1 : 0, !empty($in['desbloquear']) ? 1 : 0, !empty($in['desbloquear']) ? 1 : 0, $id]);
            echo json_encode(['success' => true, 'message' => 'Acceso actualizado.']);
            break;

        case 'eliminar':
            $id = (int) (jsonIn()['id'] ?? 0);
            $pdo->prepare("DELETE FROM web_usuarios WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Acceso eliminado.']);
            break;

        case 'rechazar_solicitud':
            $id = (int) (jsonIn()['id'] ?? 0);
            $pdo->prepare("UPDATE web_solicitudes_registro SET estado = 'rechazada', fecha_resolucion = NOW(), usuario_resolucion = ? WHERE id = ? AND estado = 'pendiente'")
                ->execute([$usuario, $id]);
            echo json_encode(['success' => true, 'message' => 'Solicitud rechazada.']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
