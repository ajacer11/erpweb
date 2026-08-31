<?php
/**
 * exportar_ctas_por_vendedor_excel.php - Exportación de Calidad
 */
session_start();
if (!isset($_SESSION['usuario_id'])) die("Acceso denegado.");

require_once 'db.php';
require_once 'permisos_helpers.php';

$vendedor_id = (int)($_GET['vendedor_id'] ?? 0);
$resumen = ($_GET['resumen'] ?? 0) == 1;
$params = [];

if (esVendedorRestringido()) $vendedor_id = idVendedorRestringido();

$where = " WHERE 1=1 ";
if ($vendedor_id > 0) { $where .= " AND a.vendedorid = ? "; $params[] = $vendedor_id; }

if ($resumen) {
    $sql = "SELECT v.id as ID, v.nombre as Vendedor, ROUND(SUM(c.debe - c.haber), 2) as Saldo
            FROM ccclientes c INNER JOIN alb_clientes a ON a.id = c.cliente INNER JOIN vendedores v ON v.id = a.vendedorid
            $where GROUP BY v.id HAVING Saldo <> 0 ORDER BY v.id ASC";
} else {
    $sql = "SELECT c.cliente as ID, a.razon as Cliente, v.nombre as Vendedor, ROUND(SUM(c.debe - c.haber), 2) as Saldo
            FROM ccclientes c INNER JOIN alb_clientes a ON a.id = c.cliente INNER JOIN vendedores v ON v.id = a.vendedorid
            $where GROUP BY c.cliente HAVING Saldo <> 0 ORDER BY a.vendedorid ASC, c.cliente ASC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

$filename = "Saldos_Vendedor_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><style>.header{background:#001020;color:#fff;font-weight:bold;text-align:center;border:1px solid #000;}.number{text-align:right;mso-number-format:'0.00';}.title{font-size:18px;font-weight:bold;text-align:center;background:#00ffff;color:#000;}</style></head>
<body>
<table>
    <tr><td colspan="<?= $resumen ? 3 : 4 ?>" class="title">REPORTE DE SALDOS POR CARTERA DE VENDEDOR</td></tr>
    <thead>
        <tr>
            <?php if($resumen): ?>
                <th class="header">ID</th><th class="header">VENDEDOR</th><th class="header">SALDO CARTERA</th>
            <?php else: ?>
                <th class="header">NRO CLI</th><th class="header">CLIENTE</th><th class="header">VENDEDOR</th><th class="header">SALDO</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php $total = 0; foreach($data as $r): $total += $r['Saldo']; ?>
        <tr>
            <td><?= $r['ID'] ?></td>
            <td><?= htmlspecialchars($r['Cliente'] ?? $r['Vendedor']) ?></td>
            <?php if(!$resumen): ?><td><?= htmlspecialchars($r['Vendedor']) ?></td><?php endif; ?>
            <td class="number"><?= $r['Saldo'] ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="font-weight:bold; background:#eee;">
            <td colspan="<?= $resumen ? 2 : 3 ?>" style="text-align:right;">TOTAL GENERAL:</td>
            <td class="number"><?= $total ?></td>
        </tr>
    </tbody>
</table>
</body></html>
