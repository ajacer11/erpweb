<?php
/**
 * api_reporte_stock_ajustes_val.php - ENDPOINT PARA EL REPORTE DE AJUSTES VALORIZADOS
 * Retorna el detalle de líneas ajustadas multiplicadas por su costo unitario.
 */

require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        $rubro = $_GET['rubro'] ?? '';
        $marca = $_GET['marca'] ?? '';
        $familia = $_GET['familia'] ?? '';
        $producto = $_GET['producto'] ?? '';

        $params = [$desde, $hasta];
        $where = "DATE(a.fechacarga) BETWEEN ? AND ?";

        // Filtros adicionales dinámicos
        if ($rubro !== '') { 
            $where .= " AND p.rubro = ?"; 
            $params[] = $rubro; 
        }
        if ($marca !== '') { 
            // Se asume compatibilidad con el formato de API de marcas
            $where .= " AND p.marca = ?"; 
            $params[] = $marca; 
        }
        if ($familia !== '') { 
            // Se asume compatibilidad con el formato de API de familias
            $where .= " AND p.familia = ?"; 
            $params[] = $familia; 
        }
        if ($producto !== '') {
            $where .= " AND (l.codigo LIKE ? OR p.descripcion LIKE ?)";
            $params[] = "%$producto%";
            $params[] = "%$producto%";
        }

        try {
            $sql = "SELECT 
                        a.id AS ajuste_id,
                        DATE_FORMAT(a.fechacarga, '%d/%m/%Y') AS fecha,
                        a.estado,
                        l.codigo,
                        COALESCE(p.descripcion, 'PRODUCTO NO ENCONTRADO/ELIMINADO') AS descripcion,
                        COALESCE(p.rubro, l.rubro, 'SIN RUBRO') AS rubro,
                        l.stockportabla,
                        l.stockfinal,
                        (l.stockfinal - l.stockportabla) AS dif_cantidad,
                        l.costounitario,
                        ((l.stockfinal - l.stockportabla) * l.costounitario) AS dif_valorizada
                    FROM lineajustes l
                    INNER JOIN ajustesdeinventario a ON l.idajuste = a.id
                    LEFT JOIN alb_productos p ON l.codigo = p.codigo
                    WHERE $where
                    ORDER BY a.fechacarga DESC, a.id DESC, l.codigo ASC";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $detalles = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $detalles]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener ajustes valorizados: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
        break;
}
?>