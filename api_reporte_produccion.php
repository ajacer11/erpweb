<?php
/**
 * api_reporte_produccion.php - REPORTE DE ÓRDENES DE PRODUCCIÓN
 * Historial de órdenes (borrador, confirmadas, finalizadas, canceladas) con su
 * detalle de insumos/salidas y el valor asignado en cada finalización.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $estadoFilter = $_GET['estado'] ?? 'TODOS';

        try {
            $sql = "SELECT o.id, o.numero_orden, o.fecha_inicio_prevista, o.fecha_fin_prevista, o.fecha_fin_real,
                           o.estado, o.notas, o.modo_costeo,
                           (SELECT COUNT(*) FROM produccion_ordenes_entradas WHERE orden_id = o.id) as cant_insumos,
                           (SELECT COUNT(*) FROM produccion_ordenes_salidas WHERE orden_id = o.id) as cant_salidas,
                           (SELECT GROUP_CONCAT(p.descripcion SEPARATOR ', ')
                              FROM produccion_ordenes_salidas s
                              JOIN alb_productos p ON s.producto_terminado_id = p.id
                              WHERE s.orden_id = o.id) as productos_a_producir,
                           (SELECT COALESCE(SUM(costo_unitario_asignado * cantidad_a_producir), 0)
                              FROM produccion_ordenes_salidas WHERE orden_id = o.id) as valor_total_producido
                    FROM produccion_ordenes o
                    WHERE DATE(o.fecha_inicio_prevista) BETWEEN ? AND ?";
            $params = [$desde, $hasta];

            if ($estadoFilter === 'PENDIENTE') {
                $sql .= " AND o.estado = 1";
            } elseif ($estadoFilter === 'CONFIRMADA') {
                $sql .= " AND o.estado IN (2, 3)"; // incluye EN_PROCESO legado
            } elseif ($estadoFilter === 'FINALIZADA') {
                $sql .= " AND o.estado = 4";
            } elseif ($estadoFilter === 'CANCELADA') {
                $sql .= " AND o.estado = 5";
            }

            $sql .= " ORDER BY o.id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener las órdenes: ' . $e->getMessage()]);
        }
        break;

    case 'detalle':
        $id = $_GET['id'] ?? 0;

        try {
            $stmtEnt = $pdo->prepare("SELECT oe.cantidad_requerida, oe.cantidad_consumida, p.codigo, p.descripcion
                                      FROM produccion_ordenes_entradas oe
                                      JOIN alb_productos p ON oe.producto_componente_id = p.id
                                      WHERE oe.orden_id = ?");
            $stmtEnt->execute([$id]);

            $stmtSal = $pdo->prepare("SELECT os.cantidad_a_producir, os.costo_unitario_asignado, p.codigo, p.descripcion
                                      FROM produccion_ordenes_salidas os
                                      JOIN alb_productos p ON os.producto_terminado_id = p.id
                                      WHERE os.orden_id = ?");
            $stmtSal->execute([$id]);

            echo json_encode(['success' => true, 'entradas' => $stmtEnt->fetchAll(), 'salidas' => $stmtSal->fetchAll()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener el detalle: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
        break;
}
