<?php
/**
 * exportar_oc_excel.php - Exportación de Orden de Compra a Excel
 * Recibe por POST el proveedor y los items armados en proveedores_oc_new.php
 * y genera un .xls (tabla HTML con estilos) que Excel abre con colores y
 * formato nativo, siguiendo el mismo esquema que exportar_productos_excel.php.
 */

session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debe iniciar sesión.");
}

$proveedor = trim((string) ($_POST['proveedor'] ?? 'SIN PROVEEDOR'));
$itemsJson = $_POST['items'] ?? '[]';
$items = json_decode($itemsJson, true);
if (!is_array($items)) {
    $items = [];
}

$totalGeneral = 0;
foreach ($items as $it) {
    $costo = (float) ($it['costo'] ?? 0);
    $cantidad = (float) ($it['cantidad'] ?? 0);
    $totalGeneral += (float) ($it['subtotal'] ?? ($costo * $cantidad));
}

$nombreArchivo = 'Orden_Compra_' . preg_replace('/[^A-Za-z0-9_\-]+/', '_', $proveedor) . '_' . date('Ymd_His') . '.xls';

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");
header("Pragma: no-cache");
header("Expires: 0");
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    table { border-collapse: collapse; }
    td, th { border: 1px solid #999999; padding: 5px 10px; font-family: Calibri, Arial, sans-serif; font-size: 12px; }
    .title { font-size: 18px; font-weight: bold; text-align: center; background-color: #001020; color: #00e5ff; }
    .subtitle { background-color: #f0f0f0; font-style: italic; text-align: left; color: #333; }
    .header { background-color: #001020; color: #ffffff; font-weight: bold; text-align: center; }
    .text { text-align: left; mso-number-format: '\@'; }
    .int { text-align: center; mso-number-format: '0'; }
    .number { text-align: right; }
    .row-even { background-color: #ffffff; }
    .row-odd { background-color: #eaf3fc; }
    .total-label { text-align: right; font-weight: bold; background-color: #001020; color: #ffffff; }
    .total-value { text-align: right; font-weight: bold; background-color: #ffd54f; color: #001020; font-size: 14px; }
</style>
</head>
<body>

<table>
    <tr>
        <td colspan="6" class="title">ORDEN DE COMPRA - <?= htmlspecialchars(mb_strtoupper($proveedor, 'UTF-8')) ?></td>
    </tr>
    <tr>
        <td colspan="6" class="subtitle">Generado el: <?= date('d/m/Y H:i:s') ?> | Items: <?= count($items) ?></td>
    </tr>
    <tr></tr>
    <thead>
        <tr>
            <th class="header">CÓDIGO</th>
            <th class="header">CÓD. PROVEEDOR</th>
            <th class="header">DESCRIPCIÓN</th>
            <th class="header">CANTIDAD</th>
            <th class="header">PRECIO UNIT.</th>
            <th class="header">IMPORTE TOTAL</th>
        </tr>
    </thead>
    <tbody>
        <?php $fila = 0; foreach ($items as $it): ?>
            <?php
                $rowCls = ($fila++ % 2 === 0) ? 'row-even' : 'row-odd';
                $costo = (float) ($it['costo'] ?? 0);
                $cantidad = (float) ($it['cantidad'] ?? 0);
                $subtotal = (float) ($it['subtotal'] ?? ($costo * $cantidad));
            ?>
            <tr class="<?= $rowCls ?>">
                <td class="text"><?= htmlspecialchars((string) ($it['codigo'] ?? '')) ?></td>
                <td class="text"><?= htmlspecialchars((string) ($it['cod_prov'] ?? '')) ?></td>
                <td><?= htmlspecialchars((string) ($it['descripcion'] ?? '')) ?></td>
                <td class="int"><?= (int) round($cantidad) ?></td>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($costo, 2, ',', '.') ?></td>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($subtotal, 2, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
    <tr>
        <td colspan="5" class="total-label">TOTAL ESTIMADO</td>
        <td class="total-value" style="mso-number-format:'0.00'"><?= number_format($totalGeneral, 2, ',', '.') ?></td>
    </tr>
</table>

</body>
</html>
