<?php
/**
 * exportar_facturas_pend_prv_excel.php - Exportación de Calidad
 */
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");

require_once 'db.php';

$hasta = $_GET['hasta'];
$prov_id = $_GET['proveedor_id'];

$where = " WHERE facturasdecompra.estado = 1 AND facturasdecompra.fechavencimiento <= ? ";
$params = [$hasta];
if ($prov_id) {
    $where .= " AND facturasdecompra.proveedorid = ? ";
    $params[] = $prov_id;
}

$sql = "SELECT 
            DATE_FORMAT(fechavencimiento, '%d/%m/%Y') as Vencimiento,
            (SELECT razon FROM Proveedores WHERE id = facturasdecompra.proveedorid) as Proveedor,
            CASE tipocomp WHEN 1 THEN 'FCA' WHEN 2 THEN 'NDA' WHEN 3 THEN 'NCA' WHEN 1000 THEN 'REM' WHEN 6 THEN 'FCB' WHEN 7 THEN 'NDB' WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 11 THEN 'FCC' WHEN 12 THEN 'NDC' ELSE 'Otro' END AS Tipo,
            CONCAT(LPAD(PV, 4, '0'),'-',LPAD(numfact, 8, '0')) AS NumFactura,
            total AS Total,
            COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0) AS Pagado,
            total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0) AS Saldo,
            DATE_FORMAT(fecha, '%d/%m/%Y') as FechaFac,
            fiscal as Fiscal
        FROM facturasdecompra $where ORDER BY Proveedor ASC, fechavencimiento ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$filename = "Facturas_Pendientes_Prv_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>.header{background:#001020;color:#fff;font-weight:bold;text-align:center;border:1px solid #000;}.number{text-align:right;mso-number-format:'0.00';}.title{font-size:18px;font-weight:bold;text-align:center;background:#00ffff;color:#000;}</style></head>
<body>
<table>
    <tr><td colspan="9" class="title">LISTADO DE FACTURAS PENDIENTES DE PAGO (PROVEEDORES)</td></tr>
    <tr><td colspan="9">Hasta Vencimiento: <?= $hasta ?> | Generado: <?= date('d/m/Y H:i') ?></td></tr>
    <thead><tr>
        <th class="header">PROVEEDOR</th><th class="header">VENCIMIENTO</th><th class="header">EMISION</th><th class="header">TIPO</th><th class="header">NRO FACTURA</th><th class="header">TOTAL</th><th class="header">PAGADO</th><th class="header">SALDO</th><th class="header">FISCAL</th>
    </tr></thead>
    <tbody>
        <?php $total = 0; foreach($data as $r): $total += $r['Saldo']; ?>
        <tr>
            <td><?= htmlspecialchars($r['Proveedor']) ?></td><td><?= $r['Vencimiento'] ?></td><td><?= $r['FechaFac'] ?></td><td><?= $r['Tipo'] ?></td><td><?= $r['NumFactura'] ?></td><td class="number"><?= $r['Total'] ?></td><td class="number"><?= $r['Pagado'] ?></td><td class="number" style="font-weight:bold"><?= $r['Saldo'] ?></td><td style="text-align:center"><?= $r['Fiscal'] ? 'SI' : 'NO' ?></td>
        </tr>
        <?php endforeach; ?>
        <tr><td colspan="7" style="text-align:right; font-weight:bold">TOTAL GENERAL:</td><td class="number" style="font-weight:bold"><?= $total ?></td><td></td></tr>
    </tbody>
</table>
</body></html>