<?php
/**
 * api_reporte_stock_por_deposito.php - STOCK CONSOLIDADO POR DEPÓSITO
 */
require_once 'check_api_access.php';
require_once 'stock_deposito_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'stock_producto':
            $idProducto = (int) ($_GET['id_producto'] ?? 0);
            $idDeposito = (int) ($_GET['id_deposito'] ?? 0);
            if (!$idProducto || !$idDeposito) {
                throw new Exception('Debe indicar producto y depósito.');
            }
            $stmt = $pdo->prepare('SELECT cantidad FROM stock_por_deposito WHERE id_producto = ? AND id_deposito = ?');
            $stmt->execute([$idProducto, $idDeposito]);
            $cantidad = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'cantidad' => $cantidad !== false ? (float) $cantidad : 0]);
            break;

        // Grilla producto x depósito, con la fila de totales para chequear
        // consistencia contra alb_productos.Stock (chequeo de integridad visual).
        case 'list':
            if (!multidepositoHabilitado($pdo)) {
                echo json_encode(['success' => true, 'habilitado' => false, 'data' => [], 'depositos' => []]);
                break;
            }

            $idDepositoFiltro = (int) ($_GET['id_deposito'] ?? 0);
            $query = trim($_GET['query'] ?? '');

            $depositos = $pdo->query('SELECT id, nombre FROM depositos WHERE habilitado = 1 ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC);

            $where = ['s.cantidad <> 0'];
            $params = [];
            if ($idDepositoFiltro) {
                $where[] = 's.id_deposito = ?';
                $params[] = $idDepositoFiltro;
            }
            if ($query !== '') {
                $where[] = '(p.codigo LIKE ? OR p.descripcion LIKE ?)';
                $params[] = "%$query%";
                $params[] = "%$query%";
            }

            $sql = "SELECT s.id_producto, p.codigo, p.descripcion, s.id_deposito, d.nombre AS deposito, s.cantidad, p.Stock AS stock_total
                    FROM stock_por_deposito s
                    INNER JOIN alb_productos p ON p.id = s.id_producto
                    INNER JOIN depositos d ON d.id = s.id_deposito
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.descripcion ASC, d.nombre ASC
                    LIMIT 2000";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode([
                'success' => true,
                'habilitado' => true,
                'depositos' => $depositos,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ]);
            break;

        default:
            throw new Exception('Acción no definida.');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
