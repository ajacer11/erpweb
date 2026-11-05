<?php
session_start();
if (empty($_SESSION['usuario_id'])) { http_response_code(401); exit('Debe iniciar sesión.'); }
require_once 'db.php';
header('Cache-Control: no-store');
$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT t.*, m.medio FROM cccuentastesoreria t LEFT JOIN mediosdepago m ON m.id = t.idcuenta WHERE t.numerodeasiento = ? AND t.tabla IN ('retiro_central','transf_cajas','ajuste_caja') ORDER BY t.id");
$st->execute([$id]); $filas = $st->fetchAll(PDO::FETCH_ASSOC);
if (!$filas || ($filas[0]['usuario'] !== ($_SESSION['usuario_nombre'] ?? '') && empty($_SESSION['es_admin']))) { http_response_code(404); exit('Comprobante no disponible.'); }
$config = $pdo->query('SELECT formatoimpresion FROM configuracion ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
$ticket = in_array((string)($config['formatoimpresion'] ?? 'A4'), ['80', '58'], true);
$empresa = $pdo->query('SELECT razon, cuit, domicilio FROM datosempresa ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
$titulos = ['retiro_central' => 'Retiro a caja central', 'transf_cajas' => 'Transferencia entre cajas', 'ajuste_caja' => 'Ajuste de caja'];
$titulo = $titulos[$filas[0]['tabla']];
function hCaja($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title><?= hCaja($titulo) ?> #<?= $id ?></title>
<style>
@page { size: <?= $ticket ? '80mm auto' : 'A4' ?>; margin: <?= $ticket ? '3mm' : '12mm' ?>; }
* { box-sizing: border-box; } body { font: <?= $ticket ? '12px' : '15px' ?> Arial,sans-serif; margin: 0; color: #111; }
main { width: <?= $ticket ? '74mm' : '185mm' ?>; margin: 10px auto; padding: 2mm; } h1 { font-size: <?= $ticket ? '17px' : '24px' ?>; } h2 { font-size: 16px; }
.linea { border-top: 1px dashed #555; padding: 10px 0; overflow-wrap: anywhere; } .importe { font-weight: bold; } .firma { margin-top: 35px; border-top: 1px solid; padding-top: 5px; }
@media print { button { display:none; } main { margin:0; max-width:100%; } }
</style></head><body><main>
<h2><?= hCaja($empresa['razon'] ?? '') ?></h2><p>CUIT: <?= hCaja($empresa['cuit'] ?? '') ?><br><?= hCaja($empresa['domicilio'] ?? '') ?></p>
<h1><?= hCaja($titulo) ?></h1><p>Comprobante Nº <?= $id ?><br>Fecha: <?= hCaja($filas[0]['fecha']) ?><br>Registrado: <?= hCaja($filas[0]['timestamp']) ?><br>Usuario: <?= hCaja($filas[0]['usuario']) ?></p>
<p><?= hCaja($filas[0]['descripcion']) ?></p>
<?php foreach ($filas as $fila): ?>
<div class="linea"><strong><?= $fila['haber'] > 0 ? 'Salida / Origen' : 'Entrada / Destino' ?></strong><br><?= hCaja($fila['medio'] ?? ('Cuenta '.$fila['idcuenta'])) ?><br><span class="importe">$ <?= number_format(max((float)$fila['debe'], (float)$fila['haber']), 2, ',', '.') ?></span></div>
<?php endforeach; ?>
<div class="firma">Firma y aclaración</div><p>Comprobante interno de movimiento de fondos.</p><button onclick="window.print()">IMPRIMIR</button>
</main></body></html>
