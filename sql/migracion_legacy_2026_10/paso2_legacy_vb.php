<?php
/**
 * Migración de un tenant del sistema VB (esquema alb_*) al sistema nuevo.
 * Uso: php paso2_legacy_vb.php coego|nadia|mena
 *
 * src_<x>  : copia local de la base legacy (no se modifica)
 * destino  : wst_db_<cuit> (se recrea desde cero en cada corrida: el proceso es repetible
 *            para el corte definitivo)
 */
require __DIR__ . '/lib.php';
require __DIR__ . '/permisos_vb.php';

$TENANTS = [
    'coego' => ['src' => 'src_coego', 'cuit' => '20211411466', 'pv' => null, 'logo' => null],
    'nadia' => ['src' => 'src_nadia', 'cuit' => '27326319363', 'pv' => null,
                'logo' => 'E:/DESARROLLOS/nadia/AlbandR01/bin/Release/logo.jpg'],
    'mena'  => ['src' => 'src_mena', 'cuit' => '30714902888', 'pv' => null, 'logo' => null],   // MENA GROUP SA
];
$clave = $argv[1] ?? '';
if (!isset($TENANTS[$clave])) exit("Uso: php paso2_legacy_vb.php coego|nadia|mena\n");
$T = $TENANTS[$clave];
$dst = 'wst_db_' . $T['cuit'];
$pdo = conectar();

// ------------------------------------------------------------------ 1. copia
logm("== $clave -> $dst");
$pdo->exec("DROP DATABASE IF EXISTS `$dst`");
$pdo->exec("CREATE DATABASE `$dst` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$r = copiarBase($T['src'], $dst);
logm('copia: ' . ($r === '' ? 'ok' : $r));

// Cuentas que usa el libro diario y faltan en el plan (antes de los parches del backoffice: ver lib.php)
foreach (crearCuentasHuerfanas($pdo, $dst) as $cta => $desc) logm("  plan de cuentas: creada $cta $desc (la usaba el libro diario legacy)");

// ------------------------------------------------------------------ 2. esquema
$pdo->exec("USE `$dst`");
[$ok, $err] = aplicarActualizacionesBackoffice($pdo);
logm("actualizaciones.sql del backoffice: $ok aplicadas, $err ya estaban / no aplican");
foreach (aplicarMigracionesSistema($dst) as $s => $res) if ($res !== 'ok') logm("  $s: $res");
[$c, $a, $e] = completarEsquema($pdo, $dst, 'ref_completa');
logm('completado contra ref_completa: ' . count($c) . ' tablas creadas, ' . count($a) . ' columnas agregadas');
foreach ($e as $x) logm("  ERROR $x");
$pdo->exec("USE `$dst`");

// ------------------------------------------------------------------ 3. catálogos que el legacy no tiene
foreach (['plandecuentas', 'cuentaspredeterminadas', 'ejercicios', 'ejperidodos', 'tiposcomprobantes', 'tipomovcuenta', 'unidadesdenegocio', 'monedas', 'ramasclientes'] as $t) {
    $n = sembrarSiVacia($pdo, $dst, 'ref_completa', $t);
    if ($n) logm("  catálogo $t: $n filas de la plantilla");
}
// Cuentas predeterminadas que apuntan a cuentas inexistentes en el plan (defecto de la plantilla)
$cpCorr = ['mercaderia' => '11301001', 'chequesrechazados' => '11201001', 'comisionesvend' => '21103012', 'comisionestarjetas' => '42009001'];
$cp = $pdo->query("SELECT * FROM cuentaspredeterminadas WHERE id = 1")->fetch();
foreach ($cp ?: [] as $campo => $valor) {
    if ($campo === 'id') continue;
    $existe = $valor !== null && $valor !== '' && (int) $pdo->query("SELECT COUNT(*) FROM plandecuentas WHERE numcuenta = " . $pdo->quote((string) $valor) . " AND imputable = 1")->fetchColumn();
    if (!$existe && isset($cpCorr[$campo])) {
        $pdo->prepare("UPDATE cuentaspredeterminadas SET `$campo` = ? WHERE id = 1")->execute([$cpCorr[$campo]]);
        logm("  cuentaspredeterminadas.$campo: '" . ($valor ?? 'NULL') . "' no existe -> {$cpCorr[$campo]}");
    } elseif (!$existe) {
        logm("  ATENCION cuentaspredeterminadas.$campo = '" . ($valor ?? 'NULL') . "' no es una cuenta imputable del plan");
    }
}

// ------------------------------------------------------------------ 4. medios de pago
if ($clave === 'nadia') {
    // Medios legacy (los ids 1..12 quedan: los usa la historia). Se les asigna tipo y cuenta.
    $map = [
        1 => ['Caja de Usuarios', '11101002', 1, 1, 0], // [tipo, cuenta, visibleenventas, puesto, cuentabancaria]
        2 => ['Tarjetas', '11201002', 1, 0, 0],
        3 => ['Cuenta Corriente', '11201001', 1, 0, 0],
        4 => ['Bancos', '11104001', 1, 0, 'MERCADOPAGO'],
        5 => ['Bancos', '11102001', 1, 0, 'BANCO'],
        6 => ['Bancos', '11102001', 0, 0, 'BANCO'],
        7 => ['Cartera de Cheques', '11103001', 0, 0, 0],
        8 => ['Cartera de Cheques', '11103001', 0, 0, 0],
        9 => ['Cartera de Cheques', '11103001', 0, 0, 0],
        10 => ['Movimiento', '11202008', 0, 0, 0],
        11 => ['Movimiento', '11202010', 0, 0, 0],
        12 => ['Movimiento', '11202009', 0, 0, 0],
    ];
    // Cuentas bancarias propias para los medios de tipo Bancos
    $cuentasProp = [];
    foreach (['MERCADOPAGO' => 'MERCADOPAGO', 'BANCO' => 'BANCO (TRANSFERENCIAS Y DEPOSITOS)'] as $k => $ref) {
        $id = $pdo->query("SELECT id FROM cuentaspropias WHERE referencia = " . $pdo->quote($ref))->fetchColumn();
        if (!$id) {
            $pdo->prepare("INSERT INTO cuentaspropias (banco, sucursal, numsucursal, numcuenta, numcbu, alias, tipocuenta, moneda, referencia) VALUES (0,'','','','','','Cuenta corriente','Pesos',?)")->execute([$ref]);
            $id = $pdo->lastInsertId();
        }
        $cuentasProp[$k] = (int) $id;
    }
    $up = $pdo->prepare("UPDATE mediosdepago SET tipodecuenta = ?, cuentacontable = ?, visibleenventas = ?, puestoasociado = ?, cuentabancaria = ?, habilitado = ?, habilitadomovimientosmanuales = 1 WHERE id = ?");
    foreach ($map as $id => [$tipo, $cta, $vent, $puesto, $banco]) {
        $habil = in_array($id, [7, 8], true) ? 0 : 1; // una sola Cartera de Cheques habilitada (la usan depósito/cobro de cheques)
        $up->execute([$tipo, $cta, $vent, $puesto, is_string($banco) ? $cuentasProp[$banco] : 0, $habil, $id]);
    }
    // Efectivo de los demás puestos + medios del sistema que el legacy no tenía (ids de la plantilla)
    $cajas = $pdo->query("SELECT numcaja FROM cajas WHERE numcaja > 1 ORDER BY numcaja")->fetchAll(PDO::FETCH_COLUMN);
    $ins = $pdo->prepare("INSERT INTO mediosdepago (id, medio, recargo, descuento, vencimiento, habilitadocobro, habilitadopagos, tipodecuenta, visibletransferenciascuentas, visibleajustescajas, visiblemovcajacentral, visibletransferenciaotracaja, visibleretiroscajacentral, cuentacontable, visibleenventas, habilitado, puestoasociado, cuentabancaria, habilitadomovimientosmanuales)
                          VALUES (?, ?, 0, 0, 0, ?, ?, ?, 1, 1, 1, 1, 1, ?, ?, 1, ?, 0, 1)");
    foreach ($cajas as $n) $ins->execute([100 + (int) $n, "Efectivo Caja $n", 1, 1, 'Caja de Usuarios', '11101002', 1, (int) $n]);
    $ins->execute([112, 'Caja Central', 0, 1, 'Caja Central', '11101001', 0, 0]);
    $ins->execute([114, 'Cheques Propios', 0, 1, 'Cheques Emitidos', '21101003', 0, 0]);
    $ins->execute([115, 'Ajuste de Cajas', 0, 0, 'Ajuste', '42004012', 0, 0]);
    $ins->execute([117, 'Redondeo', 1, 1, 'Movimiento', '42004011', 0, 0]);
    $pdo->exec("UPDATE mediosdepago SET medio = 'Efectivo Caja 1' WHERE id = 1 AND medio = 'Efectivo'");
    // La historia guarda el NOMBRE del medio en tickets/Lineadeventa: se actualiza al nuevo nombre
    $pdo->exec("UPDATE tickets SET mpago1 = 'Efectivo Caja 1' WHERE mpago1 = 'Efectivo'");
    $pdo->exec("UPDATE tickets SET mpago2 = 'Efectivo Caja 1' WHERE mpago2 = 'Efectivo'");
    logm('medios de pago: tipos y cuentas asignados; efectivo por puesto: ' . implode(',', $cajas));
} elseif ($clave === 'mena') {
    // MENA ya tiene los medios del sistema nuevo y su tesorería (los saldos de caja pasan con la copia).
    // El efectivo está asociado por NÚMERO de puesto (1..7) y los puestos tienen ids 15..27: la apertura
    // de caja (api_cajas.php) busca el medio por id del puesto, así que se reasocia al id.
    $n = $pdo->exec("UPDATE mediosdepago m JOIN cajas c ON c.numcaja = m.puestoasociado SET m.puestoasociado = c.id
                     WHERE m.tipodecuenta = 'Caja de Usuarios' AND m.puestoasociado > 0 AND m.puestoasociado <> c.id");
    logm("medios de pago: $n medios de efectivo reasociados al id del puesto");
    // Puestos sin medio de efectivo (8, 9 y 10 no tenían ni en el legacy): se crean como los demás
    $sinMedio = $pdo->query("SELECT c.id, c.numcaja FROM cajas c WHERE NOT EXISTS (SELECT 1 FROM mediosdepago m
                             WHERE m.tipodecuenta = 'Caja de Usuarios' AND (m.puestoasociado = c.id OR m.puestoasociado = c.numcaja))")->fetchAll();
    $ins = $pdo->prepare("INSERT INTO mediosdepago (medio, recargo, descuento, vencimiento, habilitadocobro, habilitadopagos, tipodecuenta, visibletransferenciascuentas, visibleajustescajas, visiblemovcajacentral, visibletransferenciaotracaja, visibleretiroscajacentral, cuentacontable, visibleenventas, habilitado, puestoasociado, cuentabancaria, habilitadomovimientosmanuales)
                          VALUES (?, 0, 0, 0, 1, 1, 'Caja de Usuarios', 1, 1, 1, 1, 1, '11101002', 1, 1, ?, 0, 1)");
    foreach ($sinMedio as $c) $ins->execute(["Efectivo Puesto {$c['numcaja']}", $c['id']]);
    if ($sinMedio) logm('medios de pago: efectivo creado para los puestos ' . implode(', ', array_column($sinMedio, 'numcaja')));
} else {
    // coego ya tiene los medios del sistema nuevo; solo faltaba marcarlos visibles en ventas
    $pdo->exec("UPDATE mediosdepago m JOIN ref_completa.mediosdepago r ON r.id = m.id AND r.medio = m.medio SET m.visibleenventas = r.visibleenventas WHERE m.visibleenventas = 0 AND r.visibleenventas = 1");
    logm('medios de pago: visibles en ventas como en la plantilla');
}
// Validación: todo medio habilitado con cuenta contable imputable
foreach ($pdo->query("SELECT id, medio, cuentacontable FROM mediosdepago m WHERE habilitado = 1 AND NOT EXISTS (SELECT 1 FROM plandecuentas p WHERE p.numcuenta = m.cuentacontable AND p.imputable = 1)")->fetchAll() as $m) {
    logm("  ATENCION medio {$m['id']} {$m['medio']} sin cuenta contable válida ({$m['cuentacontable']})");
}

// ------------------------------------------------------------------ 5. datos de la empresa (padrón ARCA)
$padron = json_decode((string) @file_get_contents("https://erp.westit.com.ar/ARCA/padron/consultar_padron.php?cuit={$T['cuit']}"), true) ?: [];
$provMap = ['CIUDAD AUTONOMA BUENOS AIRES' => 'CABA', 'BUENOS AIRES' => 'Buenos Aires', 'MENDOZA' => 'Mendoza', 'CORDOBA' => 'Córdoba', 'SANTA FE' => 'Santa Fe'];
$prov = $provMap[strtoupper((string) ($padron['provincia'] ?? ''))] ?? 'Buenos Aires';
$pvLegacy = (int) $pdo->query("SELECT PtoVta FROM tickets WHERE tipocomp < 100 AND cae <> '' ORDER BY id DESC LIMIT 1")->fetchColumn();
$pv = $T['pv'] ?? ($pvLegacy ?: 1);
$pdo->exec("DELETE FROM datosempresa");
$pdo->prepare("INSERT INTO datosempresa (id, razon, domicilio, localidad, provincia, cuit, condicioniva, telefono, email, web, numiibb, puntodeventa) VALUES (1, ?, ?, ?, ?, ?, ?, '', '', '', ?, ?)")
    ->execute([$padron['razonSocial'] ?? $clave, $padron['domicilio'] ?? '', $padron['localidad'] ?: ($prov === 'CABA' ? 'CABA' : ''), $prov, $T['cuit'],
               $padron['condicionFiscal'] ?? 'Responsable Inscripto', $T['cuit'], $pv]);
logm("datosempresa: " . ($padron['razonSocial'] ?? '?') . " / {$padron['condicionFiscal']} / $prov / PV $pv");

// ------------------------------------------------------------------ 6. configuración vigente
$idCfg = (int) $pdo->query("SELECT MAX(id) FROM configuracion")->fetchColumn();
$pdo->exec("UPDATE configuracion SET admitenotasdebito = 0 WHERE id = $idCfg");
if ($T['logo'] && is_file($T['logo'])) {
    $pdo->prepare("UPDATE configuracion SET logo = ? WHERE id = ?")->execute([file_get_contents($T['logo']), $idCfg]);
    logm('logo cargado desde ' . $T['logo']);
}

// ------------------------------------------------------------------ 7. usuarios
$n = hashearContrasenas($pdo, $dst);
$pdo->exec("UPDATE alb_usuarios SET habilitado = 1 WHERE habilitado IS NULL");
if ($clave === 'nadia') $pdo->exec("UPDATE alb_usuarios SET esadmin = 1 WHERE usuario = 'nadia'");
$pdo->exec("UPDATE alb_usuarios SET logout = 0, failed_attempts = 0, locked_until = NULL");
// Permisos del legacy (nivelesdeacceso) traducidos al menú nuevo, para quienes los tengan
$trad = migrarNivelesDeAcceso($pdo);
if ($trad) logm('niveles de acceso legacy traducidos: ' . implode(', ', array_map(fn($u, $n) => "$u ($n pantallas)", array_keys($trad), $trad)));
foreach ($pdo->query("SELECT * FROM permisosespeciales")->fetchAll() as $r) {
    logm("  permisos especiales {$r['usuario']}: " . implode(' ', array_map(fn($k, $v) => "$k=$v", array_keys($r), $r)));
}
// Permisos de mostrador para los no administradores sin registros en el legacy (revisar en Niveles de Acceso)
$menu = json_decode(file_get_contents('C:/xampp/htdocs/sistema/menu2.json'), true);
$ids = []; $urls = [];
$recorrer = function ($items) use (&$recorrer, &$ids, &$urls) {
    foreach ($items as $it) { $ids[] = $it['id']; if (!empty($it['url'])) $urls[] = $it['url']; if (!empty($it['children'])) $recorrer($it['children']); }
};
foreach ($menu as $sec) if (in_array($sec['id'], ['facturacion', 'productos', 'clientes', 'cajas'], true)) { $ids[] = $sec['id']; $recorrer($sec['children'] ?? []); }
$permisos = json_encode(['allowed_ids' => array_values(array_unique($ids)), 'allowed_urls' => array_values(array_unique($urls))], JSON_UNESCAPED_SLASHES);
$insP = $pdo->prepare("INSERT INTO accesosusuarios (usuarioid, usuarionombre, permisos, timestamp, usuariomodificacion, vigente, vigente_unico) VALUES (?, ?, ?, NOW(), 'migracion', 1, 1)");
foreach ($pdo->query("SELECT id, usuario FROM alb_usuarios WHERE esadmin = 0 AND id NOT IN (SELECT usuarioid FROM accesosusuarios WHERE vigente = 1)")->fetchAll() as $u) {
    try { $insP->execute([$u['id'], $u['usuario'], $permisos]); } catch (Throwable $e) { logm("  permisos {$u['usuario']}: " . $e->getMessage()); }
}
logm("usuarios: $n contraseñas convertidas a passweb; permisos de mostrador para no administradores");

// ------------------------------------------------------------------ 8. cierre
ajustarAutoIncrement($pdo, $dst);
// logo de configuración como LONGBLOB (el legacy lo trae como BLOB/MEDIUMBLOB y los logos actuales no entran)
$pdo->exec("ALTER TABLE `{$dst}`.configuracion MODIFY logo LONGBLOB NULL");
$al = alinearConReferencia($pdo, $dst, 'ref_sofi', 'logm');
logm('netos e IVA recalculados desde el precio final: ' . recalcularNetos($pdo, $dst) . ' productos');
logm("estructura idéntica a westit_sofi: {$al['columnas']} columnas, {$al['indices']} índices, {$al['borradas']} columnas basura borradas, " . count($al['errores']) . ' errores');
$falta = diferencias($pdo, $dst, 'ref_sofi');
logm('diferencias de esquema restantes contra la referencia: ' . (count($falta) ? implode(', ', $falta) : 'ninguna'));
foreach (['tickets', 'lineadeventa', 'alb_clientes', 'alb_productos', 'alb_usuarios', 'ccclientes', 'librodiario', 'cccuentastesoreria', 'cajaxusuario', 'controlcajas', 'pagos', 'facturasdecompra', 'mediosdepago', 'plandecuentas'] as $t) {
    logm(sprintf('  %-22s origen %8d   destino %8d', $t, contar($pdo, $T['src'], $t), contar($pdo, $dst, $t)));
}
logm("== $clave terminado");
