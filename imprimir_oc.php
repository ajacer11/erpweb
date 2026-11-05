<?php
/**
 * imprimir_oc.php - ORDEN DE COMPRA, LÍNEA POR LÍNEA (A4, para PDF/impresión)
 *
 * Igual que imprimir_remito.php / imprimir_listado_oc.php: se abre por
 * navegación directa (window.open), no por AJAX, así que no puede pasar por
 * check_access.php/check_api_access.php (exigen el header X-Requested-With
 * que una navegación normal no manda).
 *
 * Parámetro "precios": 1 muestra costo unitario y subtotales (y el total del
 * pedido), 0 los oculta (para imprimir un pedido "ciego" al depósito o al
 * proveedor sin exponer los costos negociados).
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debe iniciar sesión.");
}
require_once 'db.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) die("ID de pedido no válido.");

$mostrarPrecios = ($_GET['precios'] ?? '1') === '1';

$stmtCab = $pdo->prepare("SELECT c.*, p.Razon AS proveedor_nombre, p.domicilio AS proveedor_domicilio, p.telefono AS proveedor_telefono
                           FROM pedidos_proveedores_cab c
                           JOIN Proveedores p ON c.id_proveedor = p.Id
                           WHERE c.id = ?");
$stmtCab->execute([$id]);
$cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
if (!$cab) die("La orden de compra no existe.");

$ordenes = ['grabado' => 'd.id ASC', 'alfabetico' => 'd.descripcion ASC, d.id ASC', 'cantidad' => 'd.cantidad DESC, d.id ASC', 'precio' => 'd.costo_unitario DESC, d.id ASC', 'codigo' => 'd.codigo_producto ASC, d.id ASC', 'proveedor' => 'p.codigoproveedor ASC, d.id ASC'];
$ordenSql = $ordenes[$_GET['orden'] ?? 'grabado'] ?? $ordenes['grabado'];
$stmtDet = $pdo->prepare("SELECT d.*, p.codigoproveedor
                           FROM pedidos_proveedores_det d
                           LEFT JOIN alb_productos p ON d.codigo_producto = p.codigo
                           WHERE d.id_pedido = ?
                           ORDER BY $ordenSql");
$stmtDet->execute([$id]);
$items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

$totalUnidades = array_sum(array_column($items, 'cantidad'));
$totalGeneral = array_sum(array_column($items, 'subtotal'));

// Logo y datos de la empresa (mismo criterio que el resto de los comprobantes imprimibles)
$stmtLogo = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1");
$logoRow = $stmtLogo->fetch(PDO::FETCH_ASSOC);
$logoBase64 = ($logoRow && !empty($logoRow['logo'])) ? base64_encode($logoRow['logo']) : null;
$logoMime = 'image/png';
if ($logoBase64) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $logoMime = $finfo->buffer($logoRow['logo']) ?: 'image/png';
}

$stmtEmp = $pdo->query("SELECT * FROM datosempresa ORDER BY id DESC LIMIT 1");
$emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
if (!$emp) {
    $emp = ['razon' => 'EMPRESA NO CONFIGURADA', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'cuit' => ''];
}

$colspan = $mostrarPrecios ? 6 : 4;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Orden de Compra #<?= $id ?></title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page { size: A4; margin: 10mm 12mm; }
        body { font-family: Arial, Helvetica, sans-serif; color: black; margin: 0; padding: 0; background: #f5f5f5; font-size: 10px; }

        .sheet {
            width: 210mm; min-height: 297mm; margin: 5mm auto; background: white;
            padding: 10mm 12mm; box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .header { display: flex; flex-direction: row; justify-content: space-between; border-bottom: 1.5pt solid #002d5a; padding-bottom: 3mm; margin-bottom: 4mm; text-align: left; }
        .logo-box img { max-width: 160px; max-height: 50px; object-fit: contain; }
        .company-info h2 { margin: 0; color: #002d5a; font-size: 13px; font-weight: 800; }
        .company-info p { margin: 1px 0; color: black; font-size: 9px; line-height: 1.1; }
        .doc-info { text-align: right; }
        .doc-type-box { display: inline-flex; flex-direction: column; align-items: center; justify-content: center; border: 1.5pt solid #002d5a; padding: 3px 10px; font-weight: 800; margin: 1mm 0; line-height: 1; }
        .doc-letter { font-size: 14px; }
        .doc-code { font-size: 8px; font-weight: 800; margin-top: 2px; }
        .doc-info p { margin: 1px 0; font-size: 9px; color: #555; }

        .info-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 2mm; margin-bottom: 4mm; }
        .box { border: 0.5pt solid #ccc; padding: 2mm; border-radius: 2px; background: #fcfcfc; }
        .box h3 { margin: 0 0 1mm 0; font-size: 8px; text-transform: uppercase; color: black; border-bottom: 0.5pt solid #ccc; padding-bottom: 0.5mm; }
        .box p { margin: 1px 0; font-size: 9.5px; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items thead { display: table-header-group; }
        table.items thead th { background-color: #002d5a; color: #fff; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 2mm 1.5mm; }
        table.items thead th.text-end { text-align: right; }
        table.items thead th.text-center { text-align: center; }
        table.items tbody td { font-size: 9.5px; padding: 1.5mm; border-bottom: 0.5pt solid #eee; page-break-inside: avoid; }
        table.items tbody tr:nth-child(even) { background-color: #eaf3fc; }
        table.items tbody td.text-end { text-align: right; }
        table.items tbody td.text-center { text-align: center; font-weight: 700; }

        tfoot td { padding: 2mm 1.5mm; font-size: 10px; }
        .total-label { text-align: right; font-weight: 800; background-color: #002d5a; color: #fff; }
        .total-val { text-align: right; font-weight: 800; background-color: #ffd54f; color: #002d5a; font-size: 12px; }

        .sin-precios-note { text-align: center; font-style: italic; color: #888; font-size: 9px; padding: 2mm; }

        .page-footer { border-top: 0.5pt solid #ddd; font-size: 8px; display: flex; justify-content: space-between; color: #666; padding-top: 2mm; margin-top: 4mm; }

        @media print {
            .btn-print { display: none; }
            body { background: white; }
            .sheet { box-shadow: none; margin: 0; width: 100%; }
        }
    </style>
</head>

<body>
    <button style="position:fixed;bottom:20px;right:20px;padding:10px 20px;z-index:999" class="btn-print" onclick="window.print()">IMPRIMIR / PDF</button>

    <div class="sheet">
        <div class="header">
            <div class="logo-side" style="display:flex; gap:4mm; align-items:center;">
                <div class="logo-box">
                    <?php if ($logoBase64): ?>
                        <img src="data:<?= htmlspecialchars($logoMime) ?>;base64,<?= $logoBase64 ?>" alt="Logo">
                    <?php endif; ?>
                </div>
                <div class="company-info">
                    <h2><?= htmlspecialchars($emp['razon']) ?></h2>
                    <p><?= htmlspecialchars(trim(($emp['domicilio'] ?? '') . ' - ' . ($emp['localidad'] ?? ''), ' -')) ?></p>
                    <?php if (!empty($emp['cuit'])): ?><p>CUIT: <?= htmlspecialchars($emp['cuit']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="doc-info">
                <div class="doc-type-box">
                    <div class="doc-letter">OC</div>
                    <div class="doc-code">ORDEN DE COMPRA</div>
                </div>
                <p class="fw-bold" style="font-weight:700">N° <?= str_pad((string) $id, 8, '0', STR_PAD_LEFT) ?></p>
                <p>Fecha: <?= date('d/m/Y', strtotime($cab['fecha'])) ?></p>
                <p>Estado: <strong><?= htmlspecialchars($cab['estado']) ?></strong></p>
            </div>
        </div>

        <div class="info-grid">
            <div class="box">
                <h3>PROVEEDOR</h3>
                <p><strong><?= htmlspecialchars($cab['proveedor_nombre']) ?></strong></p>
                <?php if (!empty($cab['proveedor_domicilio'])): ?><p><?= htmlspecialchars($cab['proveedor_domicilio']) ?></p><?php endif; ?>
                <?php if (!empty($cab['proveedor_telefono'])): ?><p>Tel: <?= htmlspecialchars($cab['proveedor_telefono']) ?></p><?php endif; ?>
            </div>
            <div class="box">
                <h3>DATOS DEL PEDIDO</h3>
                <p>Generado por: <strong><?= htmlspecialchars($cab['usuario_creacion'] ?? '-') ?></strong></p>
                <p>Cantidad de ítems: <strong><?= count($items) ?></strong></p>
                <p>Impreso el: <?= date('d/m/Y H:i') ?></p>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>CÓDIGO</th>
                    <th>COD. PROV.</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="text-center">CANTIDAD</th>
                    <?php if ($mostrarPrecios): ?>
                        <th class="text-end">PRECIO UNIT.</th>
                        <th class="text-end">IMPORTE</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                    <tr><td colspan="<?= $colspan ?>" style="text-align:center; padding: 6mm; color:#888;">Este pedido no tiene ítems cargados.</td></tr>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['codigo_producto']) ?></td>
                            <td><?= htmlspecialchars($it['codigoproveedor'] ?? '') ?></td>
                            <td><?= htmlspecialchars($it['descripcion']) ?></td>
                            <td class="text-center"><?= number_format((float) $it['cantidad'], 2, ',', '.') ?></td>
                            <?php if ($mostrarPrecios): ?>
                                <td class="text-end">$ <?= number_format((float) $it['costo_unitario'], 2, ',', '.') ?></td>
                                <td class="text-end">$ <?= number_format((float) $it['subtotal'], 2, ',', '.') ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($items)): ?>
            <tfoot>
                <?php if ($mostrarPrecios): ?>
                    <tr>
                        <td colspan="3" class="total-label">TOTAL UNIDADES</td><td class="total-label"><?= number_format($totalUnidades, 2, ',', '.') ?></td><td class="total-label">TOTAL ESTIMADO</td>
                        <td class="total-val">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                    </tr>
                <?php else: ?>
                    <tr><td colspan="4" class="sin-precios-note">Total unidades: <?= number_format($totalUnidades, 2, ',', '.') ?> — Pedido impreso sin precios/costos.</td></tr>
                <?php endif; ?>
            </tfoot>
            <?php endif; ?>
        </table>

        <div class="page-footer">
            <div>Sistema West IT ERP (https://westit.com.ar)</div>
            <div>Orden de Compra #<?= $id ?></div>
        </div>
    </div>
</body>

</html>
