<?php
/**
 * api_facturas_pendientes_prv.php - REPORTE DE DEUDA CON PROVEEDORES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'list') {
        $data = json_decode(file_get_contents('php://input'), true);
        $hasta = $data['hasta'];
        
        // estado = 1 es PENDIENTE en el sistema original
        $where = " WHERE facturasdecompra.estado = 1 AND facturasdecompra.fechavencimiento <= ? ";
        $params = [$hasta];

        if (!empty($data['proveedor_id'])) {
            $where .= " AND facturasdecompra.proveedorid = ? ";
            $params[] = $data['proveedor_id'];
        }
       
        $sql = "SELECT 
                    id, 
                    DATE_FORMAT(fechavencimiento, '%d/%m/%Y') as Vencimiento,
                    (SELECT razon FROM Proveedores WHERE id = facturasdecompra.proveedorid) as Proveedor,
                    CASE tipocomp 
                        WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 1000 THEN 'REM' WHEN 6 THEN 'FCB' 
                        WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' WHEN 11 THEN 'FCC' 
                        WHEN 13 THEN 'NCC' ELSE 'Otro' 
                    END AS Tipo,
                    CONCAT(LPAD(PV, 4, '0'),'-',LPAD(numfact, 8, '0')) AS 'Num Factura',
                    total AS Total,
                    COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0) AS Pagado,
                    total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0) AS Saldo,
                    DATE_FORMAT(fecha, '%d/%m/%Y') as 'Fecha Fac',
                    fiscal as Fiscal
                FROM facturasdecompra 
                $where 
                ORDER BY Proveedor ASC, fechavencimiento ASC";

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