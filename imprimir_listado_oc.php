<?php
/**
 * imprimir_listado_oc.php - LISTADO DE ÓRDENES DE COMPRA (A4, para PDF/impresión)
 *
 * Igual que imprimir_remito.php: se abre por navegación directa (window.open),
 * no por AJAX, así que no puede pasar por check_access.php/check_api_access.php
 * (exigen el header X-Requested-With que una navegación normal no manda).
 * Trae logo y datos de la empresa igual que el resto de los comprobantes
 * imprimibles del sistema.
 */
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debe iniciar sesión.");
}
require_once 'db.php';

$desde = $_GET['desde'] ?? date('Y-m-d', strtotime('-1 month'));
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$idProv = $_GET['proveedor'] ?? 'todos';
$estado = $_GET['estado'] ?? 'todos';

$where = " WHERE c.fecha BETWEEN ? AND ? ";
$params = [$desde, $hasta];

if ($idProv !== 'todos' && $idProv !== '') {
    $where .= " AND c.id_proveedor = ? ";
    $params[] = $idProv;
}
if ($estado !== 'todos' && $estado !== '') {
    $where .= " AND c.estado = ? ";
    $params[] = $estado;
}

$sql = "SELECT c.id, DATE_FORMAT(c.fecha, '%d/%m/%Y') AS fecha, p.Razon AS proveedor,
               c.total, c.estado, c.usuario_creacion AS usuario
        FROM pedidos_proveedores_cab c
        INNER JOIN Proveedores p ON c.id_proveedor = p.Id
        $where
        ORDER BY c.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalGeneral = array_sum(array_column($pedidos, 'total'));

$proveedorFiltro = 'TODOS LOS PROVEEDORES';
if ($idProv !== 'todos' && $idProv !== '') {
    $stmtP = $pdo->prepare("SELECT Razon FROM Proveedores WHERE Id = ?");
    $stmtP->execute([$idProv]);
    $proveedorFiltro = $stmtP->fetchColumn() ?: ('Proveedor #' . $idProv);
}

$estadoFiltroTexto = ['PENDIENTE' => 'PENDIENTES', 'FINALIZADO' => 'FINALIZADOS'][$estado] ?? 'TODOS';

// Logo y datos de la empresa (mismo criterio que imprimir_remito.php)
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
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Listado de Órdenes de Compra</title>
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
        .doc-info h1 { margin: 0; color: #002d5a; font-size: 15px; }
        .doc-info p { margin: 1px 0; font-size: 9px; color: #555; }

        .filtros-box { border: 0.5pt solid #ccc; background: #fcfcfc; border-radius: 2px; padding: 2mm 3mm; margin-bottom: 4mm; font-size: 9px; display: flex; gap: 8mm; flex-wrap: wrap; }
        .filtros-box b { color: #002d5a; }

        table.items { width: 100%; border-collapse: collapse; }
        table.items thead { display: table-header-group; }
        table.items thead th { background-color: #002d5a; color: #fff; text-align: left; font-size: 8.5px; text-transform: uppercase; padding: 2mm 1.5mm; }
        table.items thead th.text-end { text-align: right; }
        table.items thead th.text-center { text-align: center; }
        table.items tbody td { font-size: 9.5px; padding: 1.5mm; border-bottom: 0.5pt solid #eee; page-break-inside: avoid; }
        table.items tbody tr:nth-child(even) { background-color: #eaf3fc; }
        table.items tbody td.text-end { text-align: right; }
        table.items tbody td.text-center { text-align: center; }
        .badge-pend { color: #b8860b; font-weight: 700; }
        .badge-fin { color: #1e7e34; font-weight: 700; }

        tfoot td { padding: 2mm 1.5mm; font-size: 10px; }
        .total-label { text-align: right; font-weight: 800; background-color: #002d5a; color: #fff; }
        .total-val { text-align: right; font-weight: 800; background-color: #ffd54f; color: #002d5a; font-size: 12px; }

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
                    <?php if (!empty($emp['provincia'])): ?><p><?= htmlspecialchars($emp['provincia']) ?></p><?php endif; ?>
                    <?php if (!empty($emp['cuit'])): ?><p>CUIT: <?= htmlspecialchars($emp['cuit']) ?></p><?php endif; ?>
                </div>
            </div>
            <div class="doc-info">
                <h1>LISTADO DE<br>ÓRDENES DE COMPRA</h1>
                <p>Generado: <?= date('d/m/Y H:i') ?></p>
                <p>Por: <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'SISTEMA') ?></p>
            </div>
        </div>

        <div class="filtros-box">
            <div><b>Rango:</b> <?= date('d/m/Y', strtotime($desde)) ?> a <?= date('d/m/Y', strtotime($hasta)) ?></div>
            <div><b>Proveedor:</b> <?= htmlspecialchars(mb_strtoupper($proveedorFiltro, 'UTF-8')) ?></div>
            <div><b>Estado:</b> <?= $estadoFiltroTexto ?></div>
            <div><b>Total de pedidos:</b> <?= count($pedidos) ?></div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th>NRO</th>
                    <th>FECHA</th>
                    <th>PROVEEDOR</th>
                    <th class="text-end">MONTO ESTIMADO</th>
                    <th class="text-center">ESTADO</th>
                    <th>USUARIO</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pedidos)): ?>
                    <tr><td colspan="6" style="text-align:center; padding: 6mm; color:#888;">No se encontraron órdenes de compra para los filtros seleccionados.</td></tr>
                <?php else: ?>
                    <?php foreach ($pedidos as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['id']) ?></td>
                            <td><?= htmlspecialchars($p['fecha']) ?></td>
                            <td><?= htmlspecialchars($p['proveedor']) ?></td>
                            <td class="text-end">$ <?= number_format((float) $p['total'], 2, ',', '.') ?></td>
                            <td class="text-center"><span class="<?= $p['estado'] === 'PENDIENTE' ? 'badge-pend' : 'badge-fin' ?>"><?= htmlspecialchars($p['estado']) ?></span></td>
                            <td><?= htmlspecialchars($p['usuario']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (!empty($pedidos)): ?>
            <tfoot>
                <tr>
                    <td colspan="3" class="total-label">TOTAL ESTIMADO</td>
                    <td colspan="3" class="total-val">$ <?= number_format($totalGeneral, 2, ',', '.') ?></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <div class="page-footer">
            <div>Sistema West IT ERP (https://westit.com.ar)</div>
            <div>Listado de Órdenes de Compra</div>
        </div>
    </div>
</body>

</html>
