<?php
/**
 * exportar_ctacte_proveedores_excel.php - Exportación de Calidad
 * Refleja fielmente la lógica de acumulación fila a fila del ERP de escritorio.
 */
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");

require_once 'db.php';

$solo_saldos = $_GET['solo_saldos'] == '1';
$proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
$por_proveedor = ($_GET['por_proveedor'] ?? '0') === '1';
$fisco_val = $_GET['fisco'];
$fisco_sql = ($fisco_val !== '') ? " AND m.fiscal = $fisco_val " : "";
$filtro_proveedor = ($por_proveedor && $proveedor_id > 0) ? " AND m.proveedor = $proveedor_id " : "";

$data = [];
$filename = "CtaCte_Proveedores_" . date('Ymd_His') . ".xls";

if ($solo_saldos) {
    // VISTA DE RESUMEN DE SALDOS
    $sql = "SELECT m.proveedor as idproveedor, DATE_FORMAT(MAX(fecha), '%d/%m/%Y') AS Fecha, (SELECT razon FROM Proveedores WHERE id = m.proveedor) AS Proveedor, ROUND(Sum(facturas),2) AS Facturas, ROUND(Sum(pagos),2) AS Pagos, ROUND(sum(facturas)-sum(pagos),2) AS Saldo FROM moviemientosccproveedores m WHERE m.id > 0 $filtro_proveedor $fisco_sql GROUP by m.proveedor HAVING Saldo <> 0 ORDER BY Proveedor ASC";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();
} else {
    // VISTA DETALLADA (MOVIMIENTOS)
    $entre_fechas = $_GET['entre_fechas'] == '1';
    $desde = $_GET['desde'];
    $fecha_sql = $entre_fechas ? " AND (m.fecha BETWEEN '$desde' AND '{$_GET['hasta']}') " : "";
    $where_prov = ($proveedor_id > 0) ? " AND m.proveedor = $proveedor_id " : "";
    $orden_sql = "m.fecha ASC, m.id ASC";

    // 1. Obtener Saldo Anterior si corresponde
    if ($entre_fechas && $proveedor_id > 0) {
        // Necesitamos el nombre del proveedor para no romper el agrupamiento en el loop de Excel
        $stmtProv = $pdo->prepare("SELECT razon FROM Proveedores WHERE id = ?");
        $stmtProv->execute([$proveedor_id]);
        $nombreProv = $stmtProv->fetchColumn();

        $stmtAnt = $pdo->prepare("SELECT SUM(facturas) - SUM(pagos) as anterior FROM moviemientosccproveedores m WHERE proveedor = ? AND fecha < ? $fisco_sql");
        $stmtAnt->execute([$proveedor_id, $desde]);
        $resAnt = $stmtAnt->fetch();
        $saldo_ant = (float)($resAnt['anterior'] ?? 0);
        
        $data[] = [
            'idproveedor' => $proveedor_id,
            'Fecha' => date('d/m/Y', strtotime($desde)), 
            'Proveedor' => $nombreProv, 
            'Tipo_Comprobante' => 'SALDO', 
            'Num_Comprobante' => 'ANTERIOR', 
            'Facturas' => $saldo_ant >= 0 ? abs($saldo_ant) : 0, 
            'Pagos' => $saldo_ant < 0 ? abs($saldo_ant) : 0
        ];
    }

    // 2. Consulta de Movimientos
    $comprobante_case = "CASE m.tabla WHEN 'facturasdecompra' THEN (SELECT CASE tipocomp WHEN 1 THEN 'FCA' WHEN 2 THEN 'NDA' WHEN 3 THEN 'NCA' WHEN 6 THEN 'FCB' WHEN 7 THEN 'NDB' WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 11 THEN 'FCC' WHEN 12 THEN 'NDC' ELSE 'Otro' END FROM facturasdecompra WHERE id = m.compasoc) WHEN 'pagoaproveedores' THEN 'Pago' WHEN 'anulacion_pagoaproveedores' THEN 'Anulación pago' END AS Tipo_Comprobante, CASE m.tabla WHEN 'facturasdecompra' THEN (SELECT numfact FROM facturasdecompra WHERE id = m.compasoc) WHEN 'pagoaproveedores' THEN COALESCE(NULLIF(m.idpago, 0), (SELECT id FROM pagoaproveedores WHERE numcomp = m.compasoc AND proveedor = m.proveedor LIMIT 1)) WHEN 'anulacion_pagoaproveedores' THEN m.idpago END AS Num_Comprobante";
    
    $sql = "SELECT m.proveedor AS idproveedor, DATE_FORMAT(m.fecha, '%d/%m/%Y') AS Fecha, (SELECT razon FROM Proveedores WHERE id = m.proveedor) AS Proveedor, $comprobante_case, ROUND(m.facturas,2) AS Facturas, ROUND(m.pagos,2) AS Pagos FROM moviemientosccproveedores m WHERE m.id > 0 $where_prov $fecha_sql $fisco_sql ORDER BY $orden_sql";
    $stmtMain = $pdo->query($sql);
    $data = array_merge($data, $stmtMain->fetchAll());
}

// Validación de contenido
if (empty($data)) {
    die("<script>alert('No hay datos para exportar'); window.close();</script>");
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        .header { background-color: #001020; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
        .number { text-align: right; mso-number-format:'0.00'; }
        .title { font-size: 18px; font-weight: bold; text-align: center; background-color: #00ffff; color: #000; }
        .row-ant { background-color: #f0faff; font-style: italic; }
    </style>
</head>
<body>
<table>
    <tr><td colspan="6" class="title">CUENTA CORRIENTE PROVEEDORES - WEST IT ERP</td></tr>
    <tr><td colspan="6">Generado el: <?= date('d/m/Y H:i') ?></td></tr>
    <thead>
        <tr>
            <?php if($solo_saldos): ?>
                <th class="header">ID</th>
                <th class="header">PROVEEDOR</th>
                <th class="header">ULT. MOV</th>
                <th class="header">DEBE / CARGOS (+)</th>
                <th class="header">PAGADO (-)</th>
                <th class="header">SALDO</th>
            <?php else: ?>
                <th class="header">FECHA</th>
                <th class="header">PROVEEDOR</th>
                <th class="header">COMPROBANTE</th>
                <th class="header">DEBE / CARGOS</th>
                <th class="header">PAGOS</th>
                <th class="header">SALDO ACUM.</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        $saldosPorProveedor = [];
        
        foreach($data as $r): 
            if(!$solo_saldos) {
                $idProveedorFila = (int)$r['idproveedor'];
                if (!isset($saldosPorProveedor[$idProveedorFila])) {
                    $saldosPorProveedor[$idProveedorFila] = 0.0;
                }
                $saldosPorProveedor[$idProveedorFila] += ($r['Facturas'] - $r['Pagos']);
                $acum = $saldosPorProveedor[$idProveedorFila];
                $esAnterior = ($r['Num_Comprobante'] === 'ANTERIOR');
            } 
        ?>
            <tr class="<?= (isset($esAnterior) && $esAnterior) ? 'row-ant' : '' ?>">
                <?php if($solo_saldos): ?>
                    <td><?= $r['idproveedor'] ?></td>
                    <td><?= htmlspecialchars($r['Proveedor']) ?></td>
                    <td style="text-align:center"><?= $r['Fecha'] ?></td>
                    <td class="number"><?= $r['Facturas'] ?></td>
                    <td class="number"><?= $r['Pagos'] ?></td>
                    <td class="number" style="font-weight:bold"><?= $r['Saldo'] ?></td>
                <?php else: ?>
                    <td style="text-align:center"><?= $r['Fecha'] ?></td>
                    <td><?= htmlspecialchars($r['Proveedor']) ?></td>
                    <td><?= $r['Tipo_Comprobante'] ?> <?= $r['Num_Comprobante'] ?></td>
                    <td class="number"><?= $r['Facturas'] > 0 ? $r['Facturas'] : '' ?></td>
                    <td class="number"><?= $r['Pagos'] > 0 ? $r['Pagos'] : '' ?></td>
                    <td class="number" style="font-weight:bold"><?= number_format($acum, 2, '.', '') ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
