<?php
/**
 * api_config_apis_externas.php - BACKEND DE CONFIGURACIÓN DE APIS EXTERNAS
 *
 * Administra la config de "Configurar APIs > Productos" (Administrador).
 * La config y el token viven en la base MAESTRA (api_config_productos_externa),
 * no en la base del tenant: el token es lo único que la tienda externa manda,
 * y hace falta poder resolver el tenant a partir de él sin sesión (ver
 * api_productos_externa.php). check_api_access.php ya incluye db.php, que a
 * su vez incluye db_master.php, así que $pdo (tenant) y $pdo_master (maestra)
 * están disponibles acá.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$cuit = $_SESSION['tenant_cuit'];
$dbId = (int)($_SESSION['tenant_db_id'] ?? 0);

try {
    switch ($action) {

        case 'get_config':
            $stmt = $pdo_master->prepare("SELECT * FROM api_config_productos_externa WHERE cuit = ? AND db_id = ? LIMIT 1");
            $stmt->execute([$cuit, $dbId]);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            $listas = $pdo->query("SELECT ID as id, descripcion FROM Listas ORDER BY ID ASC")->fetchAll(PDO::FETCH_ASSOC);

            $multidepositohabilitado = (bool)$pdo->query("SELECT multidepositohabilitado FROM configuracion ORDER BY id DESC LIMIT 1")->fetchColumn();
            $depositos = $multidepositohabilitado
                ? $pdo->query("SELECT id, nombre FROM depositos WHERE habilitado = 1 AND estransito = 0 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC)
                : [];

            // Interruptor de WEST IT (tabla tenant_addons en la maestra): si no está
            // contratado, se puede seguir configurando y generando token, pero la
            // API va a rechazar las llamadas hasta que WEST IT lo habilite.
            $stmtAddon = $pdo_master->prepare("SELECT habilitado FROM tenant_addons WHERE cuit = ? AND addon_key = 'api_productos' LIMIT 1");
            $stmtAddon->execute([$cuit]);
            $contratado = (bool)$stmtAddon->fetchColumn();

            echo json_encode([
                'success' => true,
                'data' => [
                    'habilitado' => (bool)($config['habilitado'] ?? false),
                    'token' => $config['token'] ?? null,
                    'listas_compartidas' => $config ? array_map('intval', explode(',', $config['listas_compartidas'])) : [1],
                    'id_deposito' => $config['id_deposito'] ?? null,
                ],
                'listas' => $listas,
                'depositos' => $depositos,
                'multidepositohabilitado' => $multidepositohabilitado,
                'contratado' => $contratado,
                'url_api' => (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/api_productos_externa.php',
            ]);
            break;

        case 'save_config':
            $habilitado = ($_POST['habilitado'] ?? '0') === '1' ? 1 : 0;

            $listasIds = array_filter(array_map('intval', explode(',', $_POST['listas_compartidas'] ?? '')));
            if (empty($listasIds)) {
                throw new Exception('Seleccioná al menos una lista de precios para compartir.');
            }
            $listasCompartidas = implode(',', $listasIds);

            $idDeposito = !empty($_POST['id_deposito']) ? (int)$_POST['id_deposito'] : null;

            $stmt = $pdo_master->prepare("
                INSERT INTO api_config_productos_externa (cuit, db_id, habilitado, token, listas_compartidas, id_deposito, usuario_modificacion)
                VALUES (?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    habilitado = VALUES(habilitado),
                    listas_compartidas = VALUES(listas_compartidas),
                    id_deposito = VALUES(id_deposito),
                    usuario_modificacion = VALUES(usuario_modificacion)
            ");
            // El token solo se genera la primera vez (después se regenera aparte).
            $stmtExiste = $pdo_master->prepare("SELECT token FROM api_config_productos_externa WHERE cuit = ? AND db_id = ? LIMIT 1");
            $stmtExiste->execute([$cuit, $dbId]);
            $tokenActual = $stmtExiste->fetchColumn();
            $token = $tokenActual ?: bin2hex(random_bytes(35)); // 70 caracteres hexadecimales

            $stmt->execute([$cuit, $dbId, $habilitado, $token, $listasCompartidas, $idDeposito, $usuario]);

            echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente.', 'token' => $token]);
            break;

        case 'regenerar_token':
            $stmt = $pdo_master->prepare("SELECT id FROM api_config_productos_externa WHERE cuit = ? AND db_id = ? LIMIT 1");
            $stmt->execute([$cuit, $dbId]);
            if (!$stmt->fetch()) {
                throw new Exception('Primero guardá la configuración antes de generar un token.');
            }

            $nuevoToken = bin2hex(random_bytes(35)); // 70 caracteres hexadecimales
            $stmtUpd = $pdo_master->prepare("UPDATE api_config_productos_externa SET token = ?, usuario_modificacion = ? WHERE cuit = ? AND db_id = ?");
            $stmtUpd->execute([$nuevoToken, $usuario, $cuit, $dbId]);

            echo json_encode(['success' => true, 'token' => $nuevoToken, 'message' => 'Se generó un nuevo token. El anterior deja de funcionar de inmediato.']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
