<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener Filtros (Proveedores)
    if ($action === 'get_filters') {
        $stmt = $pdo->query("SELECT id, razon FROM proveedores WHERE habilitado = 1 ORDER BY razon ASC");
        $proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'ok', 'proveedores' => $proveedores]);

    } 
    // 2. Generar Reporte / Exportar
    elseif ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);

        // Filtro Fecha Vencimiento HASTA (Inclusive)
        $fechaHasta = $input['fecha_hasta'] ?? date('Y-m-d');
        
        // Filtro Proveedor
        $proveedorId = $input['proveedor_id'] ?? null;

        // Construcción de WHERE (Basado en VB: estado = 1)
        // Agregamos el filtro de fecha y proveedor
        $where = " WHERE facturasdecompra.estado = 1 AND facturasdecompra.fechavencimiento <= ? ";
        $params = [$fechaHasta];

        if (!empty($proveedorId)) {
            $where .= " AND facturasdecompra.proveedorid = ? ";
            $params[] = $proveedorId;
        }

        // Consulta SQL "Copiada Tal Cual" (Adaptada a sintaxis SQL directa)
        $sql = "SELECT 
                    facturasdecompra.id as 'ID',                    
                    facturasdecompra.fechavencimiento as 'Fecha de Vencimiento',
                    (
                        SELECT razon
                        FROM proveedores
                        WHERE proveedores.id = facturasdecompra.proveedorid
                    ) AS 'Proveedor',
                    CASE facturasdecompra.tipocomp 
                        WHEN 1 THEN 'FCA' 
                        WHEN 3 THEN 'NCA' 
                        WHEN 1000 THEN 'REM' 
                        WHEN 6 THEN 'FCB' 
                        WHEN 8 THEN 'NCB' 
                        WHEN 100 THEN 'TKT' 
                        WHEN 101 THEN 'TDC' 
                        WHEN 11 THEN 'FCC' 
                        WHEN 13 THEN 'NCC' 
                        ELSE 'Otro'
                    END AS 'Tipo',
                    (
                        SELECT COALESCE(SUM(importepagado),0)
                        FROM pagosfacturasproveedores
                        WHERE pagosfacturasproveedores.idfactura = facturasdecompra.id
                    ) AS 'Importe Pagado',
                    facturasdecompra.total - (
                        SELECT COALESCE(SUM(importepagado),0)
                        FROM pagosfacturasproveedores
                        WHERE pagosfacturasproveedores.idfactura = facturasdecompra.id
                    ) AS 'Saldo',
                    CONCAT(
                        LPAD(facturasdecompra.pv, 4, '0'),
                        '-',
                        LPAD(facturasdecompra.numfact, 8, '0')
                    ) AS 'Num Factura',
                    facturasdecompra.fecha AS 'Fecha de Factura',
                    facturasdecompra.total AS 'Total',
                    CASE WHEN facturasdecompra.fiscal = 1 THEN '✓' ELSE '' END AS 'Fiscal'
                FROM facturasdecompra
                $where
                ORDER BY facturasdecompra.fechavencimiento ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'export') {
            // Generar Excel HTML
            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8" /></head>
            <body>
                <h3>FACTURAS PENDIENTES DE PAGO A PROVEEDORES</h3>
                <p><strong>Vencimientos hasta:</strong> <?= $fechaHasta ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <th>ID</th>
                            <th>Fecha Vencimiento</th>
                            <th>Proveedor</th>
                            <th>Tipo</th>
                            <th>Num Factura</th>
                            <th>Fecha Factura</th>
                            <th>Fiscal</th>
                            <th>Total</th>
                            <th>Importe Pagado</th>
                            <th>Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <td><?= $r['ID'] ?></td>
                            <td><?= $r['Fecha de Vencimiento'] ?></td>
                            <td><?= htmlspecialchars($r['Proveedor']) ?></td>
                            <td><?= htmlspecialchars($r['Tipo']) ?></td>
                            <td style='mso-number-format:"\@";'><?= $r['Num Factura'] ?></td>
                            <td><?= $r['Fecha de Factura'] ?></td>
                            <td align="center" style="font-weight:bold;"><?= $r['Fiscal'] ?></td>
                            <td align="right"><?= number_format($r['Total'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Importe Pagado'], 2, ',', '') ?></td>
                            <td align="right" style="font-weight:bold;"><?= number_format($r['Saldo'], 2, ',', '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Pendientes_Pago_'.date('Ymd').'.xls']);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>