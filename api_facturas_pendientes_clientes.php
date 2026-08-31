<?php
/**
 * api_facturas_pendientes.php - REPORTE DE DEUDA DE CLIENTES
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $data = json_decode(file_get_contents('php://input'), true);
        $hasta = $data['hasta'];
        
        $where = " WHERE tickets.pagada = 0 AND tickets.vencimiento <= ? AND tipocomp NOT IN (3, 8, 13, 101) ";
        $params = [$hasta];

        if ($data['cliente']) {
            $where .= " AND tickets.cliente = ? ";
            $params[] = $data['cliente'];
        }

        // Filtro Vendedor (Seguridad)
        if (esVendedorRestringido()) {
            $where .= " AND tickets.cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = ?) ";
            $params[] = idVendedorRestringido();
        }
        
        $sql = "SELECT 
                    id, 
                    DATE_FORMAT(vencimiento, '%d/%m/%Y') as Vencimiento,
                    (SELECT razon FROM alb_clientes WHERE id = tickets.cliente) as Cliente,
                    CASE tipocomp 
                        WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 1000 THEN 'REM' WHEN 6 THEN 'FCB' 
                        WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' WHEN 11 THEN 'FCC' 
                        WHEN 13 THEN 'NCC' ELSE 'Otro' 
                    END AS Tipo,
                    CONCAT(LPAD(PtoVta, 4, '0'),'-',LPAD(numcomp, 8, '0')) AS 'Num Factura',
                    total AS Total,
                    COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0) AS Cobrado,
                    total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0) AS Saldo,
                    DATE_FORMAT(fecha, '%d/%m/%Y') as 'Fecha Fac',
                    (tipocomp < 100) as Fiscal
                FROM tickets 
                $where 
                ORDER BY Cliente ASC, vencimiento ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        $totalGral = 0;
        foreach ($results as $r) { $totalGral += (float)$r['Saldo']; }

        echo json_encode(['success' => true, 'data' => $results, 'total_general' => $totalGral]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}