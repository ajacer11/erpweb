<?php
/**
 * exportar_composicion_saldos_cli_excel.php - Exportación del Consolidado de Saldos
 */
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");

require_once 'db.php';

$cliente_id = (int)$_GET['cliente_id'];
$stmtCli = $pdo->prepare("SELECT razon FROM alb_clientes WHERE id = ?");
$stmtCli->execute([$cliente_id]);
$nombreCli = $stmtCli->fetchColumn();

// Reutilizamos la lógica del API para obtener los datos consolidados
$stmtP = $pdo->prepare("SELECT fecha, id, saldoaimputar as p FROM pagos WHERE cliente = ? AND saldoaimputar > 0.01 AND estado <> 1000");
$stmtP->execute([$cliente_id]);
$pagos = $stmtP->fetchAll();

$stmtNC = $pdo->prepare("SELECT fecha, CONCAT(letracomp,' ',LPAD(PtoVta,4,'0'),'-',LPAD(numcomp,8,'0')) as n, total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idpago = tickets.id AND tablapago = 'tickets'), 0) as p FROM tickets WHERE cliente = ? AND pagada = 0 AND tipocomp IN (3, 8, 13, 101) HAVING p > 0.01");
$stmtNC->execute([$cliente_id]);
$nc = $stmtNC->fetchAll();

$stmtF = $pdo->prepare("SELECT fecha, CONCAT(letracomp,' ',LPAD(PtoVta,4,'0'),'-',LPAD(numcomp,8,'0')) as n, total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0) as p FROM tickets WHERE cliente = ? AND pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) HAVING p > 0.01");
$stmtF->execute([$cliente_id]);
$facturas = $stmtF->fetchAll();

$data = [];
foreach($pagos as $r) $data[] = ['f' => $r['fecha'], 't' => 'PAGO', 'n' => 'REC '.$r['id'], 'd' => 0, 'h' => $r['p']];
foreach($nc as $r) $data[] = ['f' => $r['fecha'], 't' => 'NC', 'n' => $r['n'], 'd' => 0, 'h' => $r['p']];
foreach($facturas as $r) $data[] = ['f' => $r['fecha'], 't' => 'FACTURA', 'n' => $r['n'], 'd' => $r['p'], 'h' => 0];

usort($data, function($a, $b) { return strtotime($a['f']) <=> strtotime($b['f']); });

$filename = "Comp_Saldo_" . str_replace(' ', '_', $nombreCli) . "_" . date('Ymd') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><style>.header{background:#001020;color:#fff;font-weight:bold;text-align:center;border:1px solid #000;}.number{text-align:right;mso-number-format:'0.00';}.title{font-size:18px;font-weight:bold;text-align:center;background:#00ffff;color:#000;}</style></head><body>
<table>
    <tr><td colspan="6" class="title">COMPOSICIÓN DE SALDO: <?= htmlspecialchars($nombreCli) ?></td></tr>
    <thead><tr><th class="header">FECHA</th><th class="header">TIPO</th><th class="header">COMPROBANTE</th><th class="header">DEBE</th><th class="header">HABER</th><th class="header">ACUMULADO</th></tr></thead>
    <tbody>
        <?php $acum=0; foreach($data as $r): $acum += ($r['d'] - $r['h']); ?>
        <tr><td><?= date('d/m/Y', strtotime($r['f'])) ?></td><td><?= $r['t'] ?></td><td><?= $r['n'] ?></td><td class="number"><?= $r['d'] > 0 ? $r['d'] : '' ?></td><td class="number"><?= $r['h'] > 0 ? $r['h'] : '' ?></td><td class="number" style="font-weight:bold"><?= $acum ?></td></tr>
        <?php endforeach; ?>
    </tbody>
</table></body></html>