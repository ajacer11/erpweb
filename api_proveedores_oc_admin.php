<?php
/**
 * api_proveedores_oc_admin.php - GESTIÓN ADMINISTRATIVA DE PEDIDOS
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $data = json_decode(file_get_contents('php://input'), true);

            $desde = $data['desde'] ?? date('Y-m-d', strtotime('-1 month'));
            $hasta = $data['hasta'] ?? date('Y-m-d');
            $idProv = $data['proveedor'] ?? 'todos';
            $estado = $data['estado'] ?? 'todos';

            $where = " WHERE c.fecha BETWEEN ? AND ? ";
            $params = [$desde, $hasta];

            if ($idProv !== 'todos' && !empty($idProv)) {
                $where .= " AND c.id_proveedor = ? ";
                $params[] = $idProv;
            }

            if ($estado !== 'todos' && !empty($estado)) {
                $where .= " AND c.estado = ? ";
                $params[] = $estado;
            }

            $sql = "SELECT 
                        c.id AS 'Nro Pedido', 
                        DATE_FORMAT(c.fecha, '%d-%m-%Y') AS 'Fecha', 
                        p.Razon AS 'Proveedor', 
                        c.total AS 'Monto Estimado', 
                        c.estado AS 'Estado', 
                        c.usuario_creacion AS 'Usuario'
                    FROM pedidos_proveedores_cab c
                    INNER JOIN Proveedores p ON c.id_proveedor = p.Id
                    $where 
                    ORDER BY c.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $resultados = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $resultados]);
            break;

        case 'finalize':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            // Validar que no esté ya finalizado
            $check = $pdo->prepare("SELECT estado FROM pedidos_proveedores_cab WHERE id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() === 'FINALIZADO') {
                throw new Exception("El pedido ya se encuentra finalizado.");
            }
            $stmt = $pdo->prepare("UPDATE pedidos_proveedores_cab SET estado = 'FINALIZADO' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $pdo->beginTransaction();
            // Borrar detalle primero
            $pdo->prepare("DELETE FROM pedidos_proveedores_det WHERE id_pedido = ?")->execute([$id]);
            // Borrar cabecera
            $pdo->prepare("DELETE FROM pedidos_proveedores_cab WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}