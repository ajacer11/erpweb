<?php
/**
 * api_web_avisos_pago.php - Pagos informados por los clientes desde la web (web_avisos_pago).
 * Son avisos: no generan ningún movimiento. La empresa verifica el pago, lo carga en
 * Cobrar Facturas como cualquier cobro y marca el aviso como procesado (o lo rechaza).
 */
require_once 'check_api_access.php';
require_once 'web_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$cuit = (string) $_SESSION['tenant_cuit'];

try {
    if (!webAddonHabilitado($pdo_master, $cuit)) {
        throw new Exception('La web de clientes no está contratada para esta empresa.');
    }

    switch ($action) {
        case 'listar':
            $estado = $_GET['estado'] ?? 'pendiente';
            $sql = "SELECT a.*, c.razon, c.numdoc, w.nombre_usuario,
                           (SELECT SUM(debe) - SUM(haber) FROM ccclientes cc WHERE cc.cliente = a.cliente_id) AS saldo
                    FROM web_avisos_pago a
                    LEFT JOIN alb_clientes c ON c.id = a.cliente_id
                    LEFT JOIN web_usuarios w ON w.id = a.web_usuario_id";
            $params = [];
            if (in_array($estado, ['pendiente', 'procesado', 'rechazado'], true)) {
                $sql .= " WHERE a.estado = ?";
                $params[] = $estado;
            }
            $sql .= " ORDER BY a.fecha DESC LIMIT 500";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $pendientes = (int) $pdo->query("SELECT COUNT(*) FROM web_avisos_pago WHERE estado = 'pendiente'")->fetchColumn();
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC), 'pendientes' => $pendientes]);
            break;

        case 'comprobante':
            $id = (int) ($_GET['id'] ?? 0);
            $st = $pdo->prepare("SELECT archivo FROM web_avisos_pago WHERE id = ?");
            $st->execute([$id]);
            $archivo = basename((string) $st->fetchColumn());
            $ruta = webDirAvisosPago($cuit) . '/' . $archivo;
            if ($archivo === '' || !is_file($ruta)) {
                http_response_code(404);
                exit;
            }
            $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
            header('Content-Type: ' . (['pdf' => 'application/pdf', 'png' => 'image/png'][$ext] ?? 'image/jpeg'));
            header('Content-Disposition: inline; filename="comprobante_' . $id . '.' . $ext . '"');
            header('Cache-Control: private, max-age=3600');
            readfile($ruta);
            exit;

        case 'resolver':
            $in = json_decode(file_get_contents('php://input'), true) ?: [];
            $estado = $in['estado'] ?? '';
            if (!in_array($estado, ['procesado', 'rechazado', 'pendiente'], true)) throw new Exception('Estado inválido.');
            $pdo->prepare("UPDATE web_avisos_pago SET estado = ?, nota_interna = ?, fecha_resolucion = IF(? = 'pendiente', NULL, NOW()),
                           usuario_resolucion = IF(? = 'pendiente', NULL, ?) WHERE id = ?")
                ->execute([$estado, mb_substr(trim((string) ($in['nota'] ?? '')), 0, 500), $estado, $estado, $usuario, (int) ($in['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
