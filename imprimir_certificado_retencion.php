<?php
/**
 * imprimir_certificado_retencion.php - Certificado de retención (agente de retención).
 * ?pago=ID  -> todos los certificados de un pago a proveedor (uno por hoja)
 * ?id=ID    -> un certificado puntual
 */
session_start();
require_once 'db.php';

$idPago = (int) ($_GET['pago'] ?? 0);
$id = (int) ($_GET['id'] ?? 0);
if (!$idPago && !$id) die('Indique el certificado.');

$st = $pdo->prepare("SELECT r.*, g.nombre AS regimen, g.impuesto, g.jurisdiccion, g.codigo_impuesto, g.codigo_regimen, g.resolucion,
        p.Razon AS proveedor, p.cuit, p.domicilio AS prov_domicilio, p.condicion,
        pg.numcomp AS pago_numero, pg.fecharecibo, pg.total AS pago_total
    FROM retenciones_practicadas r
    JOIN regimenes_retper g ON g.id = r.idregimen
    LEFT JOIN Proveedores p ON p.Id = r.idproveedor
    LEFT JOIN pagoaproveedores pg ON pg.id = r.idpago
    WHERE r.importe > 0 AND " . ($idPago ? 'r.idpago = ?' : 'r.id = ?') . " ORDER BY r.id");
$st->execute([$idPago ?: $id]);
$certs = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$certs) die('No hay retenciones para imprimir.');

$emp = $pdo->query("SELECT * FROM datosempresa WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$cfg = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$logo = (!empty($cfg['logo'])) ? 'data:' . ((new finfo(FILEINFO_MIME_TYPE))->buffer($cfg['logo']) ?: 'image/png') . ';base64,' . base64_encode($cfg['logo']) : null;

$h = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$m = static fn($v) => number_format((float) $v, 2, ',', '.');
$f = static fn($v) => $v ? date('d/m/Y', strtotime($v)) : '';
$cuitFmt = static function ($c) { $c = preg_replace('/\D/', '', (string) $c); return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c; };
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Certificado de retención</title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; color: #111; margin: 0; background: #eee; }
    .hoja { width: 190mm; min-height: 130mm; margin: 10mm auto; background: #fff; padding: 12mm; box-sizing: border-box; page-break-after: always; }
    .hoja:last-child { page-break-after: auto; }
    h1 { font-size: 18px; margin: 0 0 4px; text-transform: uppercase; }
    .cab { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
    .cab img { max-height: 55px; }
    .bloque { border: 1px solid #999; padding: 8px; margin-bottom: 8px; font-size: 13px; }
    .bloque b { display: inline-block; min-width: 170px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; margin-top: 6px; }
    th, td { border: 1px solid #999; padding: 6px; text-align: left; }
    td.n, th.n { text-align: right; }
    .firma { margin-top: 30mm; display: flex; justify-content: flex-end; font-size: 12px; }
    .firma div { border-top: 1px solid #111; width: 70mm; text-align: center; padding-top: 4px; }
    @media print { body { background: #fff; } .hoja { margin: 0; box-shadow: none; } .noprint { display: none; } }
</style>
</head>
<body>
<div class="noprint" style="text-align:center;padding:8px"><button onclick="window.print()">Imprimir</button></div>
<?php foreach ($certs as $c): ?>
<div class="hoja">
    <div class="cab">
        <div>
            <h1>Certificado de retención<?php if ((int) $c['estado'] === 1000): ?> <span style="color:#c00">— ANULADO</span><?php endif; ?></h1>
            <div style="font-size:13px">N° <?= str_pad((string) $c['numero_certificado'], 8, '0', STR_PAD_LEFT) ?> &nbsp;·&nbsp; Fecha <?= $f($c['fecha']) ?></div>
            <div style="font-size:12px"><?= $h($c['impuesto']) ?> · <?= $h($c['jurisdiccion']) ?> · <?= $h($c['regimen']) ?><?= $c['codigo_regimen'] !== '' ? ' (régimen ' . $h($c['codigo_regimen']) . ')' : '' ?></div>
        </div>
        <?php if ($logo): ?><img src="<?= $logo ?>" alt=""><?php endif; ?>
    </div>
    <div class="bloque">
        <div><b>Agente de retención:</b> <?= $h($emp['razon'] ?? '') ?></div>
        <div><b>CUIT:</b> <?= $h($cuitFmt($emp['cuit'] ?? '')) ?> &nbsp; <b style="min-width:0">IIBB:</b> <?= $h($emp['numiibb'] ?? '') ?></div>
        <div><b>Domicilio:</b> <?= $h(trim(($emp['domicilio'] ?? '') . ' ' . ($emp['localidad'] ?? '') . ' ' . ($emp['provincia'] ?? ''))) ?></div>
    </div>
    <div class="bloque">
        <div><b>Sujeto retenido:</b> <?= $h($c['proveedor']) ?></div>
        <div><b>CUIT:</b> <?= $h($cuitFmt($c['cuit'])) ?> &nbsp; <b style="min-width:0">Condición:</b> <?= $h($c['condicion']) ?></div>
        <div><b>Domicilio:</b> <?= $h($c['prov_domicilio'] ?? '') ?></div>
    </div>
    <table>
        <tr><th>Comprobante que origina la retención</th><th class="n">Base imponible</th><th class="n">Alícuota</th><th class="n">Importe retenido</th></tr>
        <tr>
            <td>Orden de pago N° <?= $h($c['pago_numero']) ?> del <?= $f($c['fecharecibo']) ?> (total cancelado $ <?= $m($c['pago_total']) ?>)</td>
            <td class="n">$ <?= $m($c['base']) ?></td>
            <td class="n"><?= rtrim(rtrim(number_format((float) $c['alicuota'], 4, ',', '.'), '0'), ',') ?> %</td>
            <td class="n"><b>$ <?= $m($c['importe']) ?></b></td>
        </tr>
    </table>
    <?php if ($c['resolucion'] !== ''): ?><div style="font-size:11px;margin-top:6px">Normativa: <?= $h($c['resolucion']) ?></div><?php endif; ?>
    <div class="firma"><div>Firma y aclaración del agente de retención</div></div>
</div>
<?php endforeach; ?>
</body>
</html>
