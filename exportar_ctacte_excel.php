<?php
/**
 * exportar_ctacte_excel.php - Exportación de Calidad de Cuenta Corriente
 * Refleja pura y exclusivamente lo visualizado en la grilla.
 */
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");

require_once 'db.php';
require_once 'permisos_helpers.php';

// FILTROS
$solo_saldos = $_GET['solo_saldos'] == '1';
$cliente_id = (int)($_GET['cliente_id'] ?? 0);
$por_cliente = ($_GET['por_cliente'] ?? '0') === '1';
$fisco_val = $_GET['fisco'];
$fisco_sql = ($fisco_val !== '') ? " AND fiscal = $fisco_val " : "";
$filtro_cliente = ($por_cliente && $cliente_id > 0) ? " AND ccclientes.cliente = $cliente_id " : "";

// Filtro vendedor restringido
$filtro_vendedor = "";
if (esVendedorRestringido()) {
    $idv = idVendedorRestringido();
    $filtro_vendedor = " AND ccclientes.cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = $idv) ";
}

$data = [];
$filename = "CtaCte_" . date('Ymd_His') . ".xls";

if ($solo_saldos) {
    // CONSULTA PARA VISTA DE SALDOS
    $sql = "SELECT 
                ccclientes.cliente as idcliente, 
                DATE_FORMAT(MAX(fecha), '%d/%m/%Y') AS Fecha, 
                a.razon AS Cliente, 
                ROUND(Sum(debe),2) AS Facturas, 
                ROUND(Sum(haber),2) AS Cobros, 
                ROUND(sum(debe)-sum(haber),2) AS Saldo 
            FROM ccclientes 
            INNER JOIN alb_clientes a ON ccclientes.cliente = a.id
            WHERE ccclientes.id > 0 $filtro_cliente $fisco_sql $filtro_vendedor
            GROUP by ccclientes.cliente 
            HAVING Saldo <> 0 
            ORDER BY a.razon ASC";
    $stmt = $pdo->query($sql);
    $data = $stmt->fetchAll();
} else {
    // CONSULTA PARA VISTA DETALLADA
    $entre_fechas = $_GET['entre_fechas'] == '1';
    $desde = $_GET['desde'];
    $hasta = $_GET['hasta'];
    $fecha_sql = $entre_fechas ? " AND (fecha BETWEEN '$desde' AND '$hasta') " : "";
    $where_cli = ($cliente_id > 0) ? " AND ccclientes.cliente = $cliente_id " : "";
    $orden_sql = "ccclientes.fecha ASC, ccclientes.id ASC";

    $comprobante_case = "
        CASE ccclientes.tabla 
            WHEN 'tickets' THEN (SELECT CASE tipocomp WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 6 THEN 'FCB' WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 11 THEN 'FCC' ELSE 'Otro' END FROM tickets WHERE id = ccclientes.compasoc) 
            WHEN 'pagos' THEN 'REC' 
        END AS Tipo_Comprobante,
        CASE ccclientes.tabla 
            WHEN 'tickets' THEN (SELECT CONCAT(letracomp, ' ', LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0')) FROM tickets WHERE id = ccclientes.compasoc) 
            WHEN 'pagos' THEN CONCAT('RECIBO ', LPAD(compasoc, 9, '0')) 
        END AS Num_Comprobante";

    // Lógica de Saldo Anterior (Solo si hay cliente seleccionado y filtro de fechas)
    if ($entre_fechas && $cliente_id > 0) {
        $stmtAnt = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as anterior FROM ccclientes WHERE cliente = ? AND fecha < ? $fisco_sql");
        $stmtAnt->execute([$cliente_id, $desde]);
        $resAnt = $stmtAnt->fetch();
        $saldo_ant = (float)($resAnt['anterior'] ?? 0);
        
        $data[] = [
            'idcliente' => $cliente_id,
            'Fecha' => date('d/m/Y', strtotime($desde)), 
            'Cliente' => '', 
            'Tipo_Comprobante' => 'SALDO', 
            'Num_Comprobante' => 'ANTERIOR', 
            'Facturas' => $saldo_ant >= 0 ? abs($saldo_ant) : 0, 
            'Cobros' => $saldo_ant < 0 ? abs($saldo_ant) : 0
        ];
    }

    $sql = "SELECT 
                ccclientes.cliente AS idcliente,
                DATE_FORMAT(fecha, '%d/%m/%Y') AS Fecha, 
                (SELECT razon FROM alb_clientes WHERE id = ccclientes.cliente) AS Cliente, 
                $comprobante_case, 
                ROUND(debe,2) AS Facturas, 
                ROUND(haber,2) AS Cobros 
            FROM ccclientes 
            WHERE id > 0 $where_cli $fecha_sql $fisco_sql $filtro_vendedor 
            ORDER BY $orden_sql";
    $stmt = $pdo->query($sql);
    $movs = $stmt->fetchAll();
    $data = array_merge($data, $movs);
}

// Si no hay datos, no generamos el archivo para cumplir con la condición de "si hay algo en grilla"
if (empty($data)) {
    die("<script>alert('No hay datos para exportar'); window.close();</script>");
}

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
    .header { background-color: #001020; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
    .number { text-align: right; mso-number-format:'0.00'; }
    .title { font-size: 18px; font-weight: bold; text-align: center; background-color: #00ffff; color: #000; }
    .row-saldo-anterior { background-color: #f0ffff; }
</style>
</head>
<body>
<table>
    <tr><td colspan="6" class="title">CUENTAS CORRIENTES DE CLIENTES - WEST IT ERP</td></tr>
    <tr><td colspan="6">Generado el: <?= date('d/m/Y H:i') ?></td></tr>
    <thead>
        <tr>
            <?php if($solo_saldos): ?>
                <th class="header">ID</th>
                <th class="header">CLIENTE</th>
                <th class="header">ÚLT. MOV</th>
                <th class="header">FACTURACIÓN</th>
                <th class="header">COBRANZAS</th>
                <th class="header">SALDO</th>
            <?php else: ?>
                <th class="header">FECHA</th>
                <th class="header">CLIENTE</th>
                <th class="header">COMPROBANTE</th>
                <th class="header">DEBE</th>
                <th class="header">HABER</th>
                <th class="header">SALDO</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php 
        $saldosPorCliente = [];
        foreach($data as $r): 
            if(!$solo_saldos) {
                $idClienteFila = (int)$r['idcliente'];
                if (!isset($saldosPorCliente[$idClienteFila])) {
                    $saldosPorCliente[$idClienteFila] = 0.0;
                }
                $saldosPorCliente[$idClienteFila] += ($r['Facturas'] - $r['Cobros']);
                $acum = $saldosPorCliente[$idClienteFila];
                $esAnterior = ($r['Num_Comprobante'] === 'ANTERIOR');
            }
        ?>
            <tr class="<?= (!$solo_saldos && $esAnterior) ? 'row-saldo-anterior' : '' ?>">
                <?php if($solo_saldos): ?>
                    <td><?= $r['idcliente'] ?></td>
                    <td><?= htmlspecialchars($r['Cliente']) ?></td>
                    <td style="text-align:center"><?= $r['Fecha'] ?></td>
                    <td class="number"><?= $r['Facturas'] ?></td>
                    <td class="number"><?= $r['Cobros'] ?></td>
                    <td class="number" style="font-weight:bold"><?= $r['Saldo'] ?></td>
                <?php else: ?>
                    <td style="text-align:center"><?= $r['Fecha'] ?></td>
                    <td><?= htmlspecialchars($r['Cliente']) ?></td>
                    <td><?= $r['Tipo_Comprobante'] ?> <?= $r['Num_Comprobante'] ?></td>
                    <td class="number"><?= $r['Facturas'] > 0 ? $r['Facturas'] : '' ?></td>
                    <td class="number"><?= $r['Cobros'] > 0 ? $r['Cobros'] : '' ?></td>
                    <td class="number" style="font-weight:bold"><?= number_format($acum, 2, '.', '') ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</body></html>
