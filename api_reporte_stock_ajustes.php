<?php
/**
 * api_reporte_stock_ajustes.php - ENDPOINT PARA EL REPORTE DE AJUSTES
 * Retorna el listado de ajustes (cabeceras) y sus detalles (líneas).
 */

require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
        $hasta = $_GET['hasta'] ?? date('Y-m-d');
        
        try {
            // Buscamos las cabeceras de los ajustes.
            // Si tiene nombregrupo es un ajuste grupal, si no, se asume individual.
            $sql = "SELECT 
                        a.id, 
                        a.fechacarga, 
                        a.usuariocarga, 
                        a.estado, 
                        a.fechaajuste, 
                        a.observacion, 
                        a.nombregrupo,
                        (SELECT COUNT(*) FROM lineajustes l WHERE l.idajuste = a.id) as cant_items
                    FROM ajustesdeinventario a 
                    WHERE DATE(a.fechacarga) BETWEEN ? AND ? 
                    ORDER BY a.fechacarga DESC, a.id DESC";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$desde, $hasta]);
            $ajustes = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $ajustes]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener ajustes: ' . $e->getMessage()]);
        }
        break;

    case 'detalle':
        $idAjuste = $_GET['id'] ?? 0;
        
        try {
            // Buscamos el detalle cruzando con alb_productos para traer la descripción actualizada
            $sql = "SELECT 
                        l.codigo, 
                        COALESCE(p.descripcion, 'PRODUCTO ELIMINADO/NO ENCONTRADO') as descripcion,
                        l.stockportabla, 
                        l.ingresan, 
                        l.salen, 
                        l.stockfinal, 
                        l.costounitario,
                        l.rubro
                    FROM lineajustes l 
                    LEFT JOIN alb_productos p ON l.codigo = p.codigo 
                    WHERE l.idajuste = ?";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idAjuste]);
            $detalles = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $detalles]);

        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error al obtener detalle: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción no válida.']);
        break;
}
?>