<?php
/**
 * Migración de nicolas (sistema VB más viejo, 17 tablas) al sistema nuevo, con multidepósito.
 * Destino: wst_db_20281563859, armado desde la plantilla del backoffice (ref_completa) y
 * cargado con los datos de src_nicolas. Repetible: recrea el destino en cada corrida.
 *
 * Puntos de venta: hasta el ticket ~208145 se facturó por PV 1 y 9; desde ahí por
 * PV 3 (La Quesería, Sargento Cabral 396) y PV 4 (Ric San, Paso de los Andes 588).
 * Depósitos = PV 3 y PV 4; su stock sale de productos.stock3 / productos.stock4.
 */
require __DIR__ . '/lib.php';
$SRC = 'src_nicolas';
$CUIT = '20281563859';
$DST = "wst_db_$CUIT";
$LOGO = 'D:/Alband/AlbandR02 - Mysql - Nicolas/AlbandR01/bin/Release/logo.jpg';
$pdo = conectar();

logm("== nicolas -> $DST");
$pdo->exec("DROP DATABASE IF EXISTS `$DST`");
$pdo->exec("CREATE DATABASE `$DST` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$r = copiarBase('ref_completa', $DST);
logm('plantilla copiada: ' . ($r === '' ? 'ok' : $r));
$pdo->exec("USE `$DST`");

// Datos de ejemplo de la plantilla que se reemplazan por los reales
foreach (['alb_clientes', 'alb_productos', 'Proveedores', 'rubros', 'Listas', 'alb_usuarios', 'AFIPconfig', 'Caja', 'transportes', 'ajustesdeinventario', 'tickets', 'Lineadeventa', 'accesosusuarios'] as $t) {
    $pdo->exec("DELETE FROM `$t`");
}

// ------------------------------------------------------------------ maestros
$pdo->exec("INSERT INTO alb_clientes (id, razon, domicilio, localidad, provincia, cp, numdoc, condicionfiscal, telefono, celular, mail, contacto, comp_predet, tipodoc, habilitado, ListaDePRecios, cuentacorriente)
            SELECT id, razon, domicilio, localidad, provincia, cp, numdoc, condicionfiscal, telefono, celular, mail, contacto, comp_predet, tipodoc, habilitado, ListaDePRecios, 0 FROM $SRC.clientes");
$pdo->exec("INSERT INTO rubros (id, rubro, iva, IDiva) SELECT id, rubro, iva, IDiva FROM $SRC.rubros");
$pdo->exec("INSERT INTO Listas (ID, descripcion, utilidad) SELECT ID, MAX(descripcion), MAX(utilidad) FROM $SRC.listas GROUP BY ID");
$pdo->exec("INSERT INTO Proveedores (Id, Razon) SELECT Id, Razon FROM $SRC.proveedores");
$cols = array_values(array_intersect(array_keys(columnas($pdo, $SRC, 'productos')), array_keys(columnas($pdo, $DST, 'alb_productos'))));
$cols = array_values(array_diff($cols, ['stock']));
$lista = '`' . implode('`,`', $cols) . '`';
$pdo->exec("INSERT INTO alb_productos ($lista, Stock, controlastock) SELECT $lista, COALESCE(stock3, 0) + COALESCE(stock4, 0), 1 FROM $SRC.productos");
logm('maestros: clientes ' . contar($pdo, $DST, 'alb_clientes') . ', productos ' . contar($pdo, $DST, 'alb_productos') . ', rubros ' . contar($pdo, $DST, 'rubros') . ', listas ' . contar($pdo, $DST, 'Listas'));

// ------------------------------------------------------------------ medios de pago
// La plantilla trae los medios del sistema (efectivo por puesto, caja central, cheques...).
// Se agregan los que usaba nicolas, con el MISMO nombre que figura en su historia.
$pdo->exec("UPDATE mediosdepago SET medio = 'MERCADO PAGO', visibleenventas = 1 WHERE id = 108");
$pdo->exec("UPDATE mediosdepago SET habilitado = 0, visibleenventas = 0 WHERE id IN (109, 119, 120)"); // Santander / Cuenta DNI / comisiones billeteras de la plantilla
$ins = $pdo->prepare("INSERT INTO mediosdepago (id, medio, recargo, descuento, vencimiento, habilitadocobro, habilitadopagos, tipodecuenta, visibletransferenciascuentas, visibleajustescajas, visiblemovcajacentral, visibletransferenciaotracaja, visibleretiroscajacentral, cuentacontable, visibleenventas, habilitado, puestoasociado, cuentabancaria, habilitadomovimientosmanuales)
                      VALUES (?, ?, 0, 0, 0, 1, 0, ?, 1, 1, 1, 1, 1, ?, ?, ?, ?, 0, 1)");
$ins->execute([1, 'Efectivo', 'Caja de Usuarios', '11101002', 0, 0, 1]);           // nombre histórico; el vigente es "Efectivo Caja N"
$ins->execute([7, 'TARJETA CREDITO', 'Tarjetas', '11201002', 1, 1, 0]);
$ins->execute([8, 'TARJETA DE DEBITO', 'Tarjetas', '11201002', 1, 1, 0]);
$ins->execute([10, 'CONSUMO INTERNO', 'Movimiento', '11501001', 1, 1, 0]);
$ins->execute([11, 'MODO', 'Tarjetas', '11201002', 1, 1, 0]);
$pdo->exec("UPDATE mediosdepago SET habilitado = 0 WHERE id = 116"); // "Cupones de Tarjetas": reemplazado por crédito / débito / MODO
logm('medios de pago: plantilla + TARJETA CREDITO, TARJETA DE DEBITO, MODO, CONSUMO INTERNO; MercadoPago renombrado a MERCADO PAGO');

// ------------------------------------------------------------------ depósitos (multidepósito)
$logo = is_file($LOGO) ? file_get_contents($LOGO) : null;
$insDep = $pdo->prepare("INSERT INTO depositos (nombre, descripcion, esprincipal, habilitado, permitenegativo, usuariocreacion, estransito, domicilio, localidad, provincia, codigopostal, puntodeventa, logo)
                         VALUES (?, ?, ?, 1, 1, 'migracion', 0, ?, 'Mendoza', 'Mendoza', '5500', ?, ?)");
$insDep->execute(['La Quesería', 'Punto de venta 3', 1, 'Sargento Cabral 396', 3, $logo]);
$depLQ = (int) $pdo->lastInsertId();
$insDep->execute(['Ric San', 'Punto de venta 4', 0, 'Paso de los Andes 588', 4, $logo]);
$depRS = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO stock_por_deposito (id_producto, id_deposito, cantidad) SELECT p.id, $depLQ, COALESCE(s.stock3, 0) FROM alb_productos p JOIN $SRC.productos s ON s.id = p.id WHERE COALESCE(s.stock3, 0) <> 0");
$pdo->exec("INSERT INTO stock_por_deposito (id_producto, id_deposito, cantidad) SELECT p.id, $depRS, COALESCE(s.stock4, 0) FROM alb_productos p JOIN $SRC.productos s ON s.id = p.id WHERE COALESCE(s.stock4, 0) <> 0");
logm("depósitos: La Quesería #$depLQ (PV 3) y Ric San #$depRS (PV 4); stock_por_deposito " . contar($pdo, $DST, 'stock_por_deposito') . ' filas');

// ------------------------------------------------------------------ ventas
// La historia tiene 36 pares de tickets internos (tipo 100, sin CAE) con el mismo número en
// el mismo punto de venta. El sistema nuevo tiene un índice único (PtoVta, tipocomp, numcomp)
// que protege la numeración: se conserva y el SEGUNDO ticket de cada par pasa al siguiente
// número libre. Queda registrado en _mig_renumerados (y sus líneas de venta, renumeradas).
$pdo->exec("DROP TABLE IF EXISTS _mig_renumerados");
$pdo->exec("CREATE TABLE _mig_renumerados (id_ticket INT PRIMARY KEY, ptovta INT, tipocomp INT, num_original INT, num_nuevo INT, linea_desde INT, linea_hasta INT)");
$grupos = $pdo->query("SELECT PtoVta, tipocomp, numcomp FROM $SRC.tickets GROUP BY PtoVta, tipocomp, numcomp HAVING COUNT(*) > 1")->fetchAll();
$dups = [];
$stG = $pdo->prepare("SELECT id, PtoVta, tipocomp, numcomp, lvdesde, lvhasta FROM $SRC.tickets WHERE PtoVta = ? AND tipocomp = ? AND numcomp = ? ORDER BY id");
foreach ($grupos as $g) {
    $stG->execute([$g['PtoVta'], $g['tipocomp'], $g['numcomp']]);
    $filas = $stG->fetchAll();
    $hastaAnterior = (int) $filas[0]['lvhasta'];
    foreach (array_slice($filas, 1) as $f) {
        $f['hasta_anterior'] = $hastaAnterior;
        $hastaAnterior = max($hastaAnterior, (int) $f['lvhasta']);
        $dups[] = $f;
    }
}
$maxNum = [];
$insR = $pdo->prepare("INSERT INTO _mig_renumerados VALUES (?, ?, ?, ?, ?, ?, ?)");
foreach ($dups as $d) {
    $k = $d['PtoVta'] . '-' . $d['tipocomp'];
    if (!isset($maxNum[$k])) $maxNum[$k] = (int) $pdo->query("SELECT MAX(numcomp) FROM $SRC.tickets WHERE PtoVta = {$d['PtoVta']} AND tipocomp = {$d['tipocomp']}")->fetchColumn();
    $nuevo = ++$maxNum[$k];
    $insR->execute([$d['id'], $d['PtoVta'], $d['tipocomp'], $d['numcomp'], $nuevo, max((int) $d['lvdesde'], (int) $d['hasta_anterior'] + 1), (int) $d['lvhasta']]);
}
logm(count($dups) . ' tickets internos con número repetido: renumerados (ver _mig_renumerados)');
$tc = "CASE t.tipocomp WHEN 1 THEN 'FCA' WHEN 2 THEN 'NDA' WHEN 3 THEN 'NCA' WHEN 6 THEN 'FCB' WHEN 7 THEN 'NDB' WHEN 8 THEN 'NCB' WHEN 11 THEN 'FCC' WHEN 12 THEN 'NDC' WHEN 13 THEN 'NCC' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' ELSE 'TKT' END";
$pdo->exec("INSERT INTO tickets (id, tipocomp, PtoVta, numcomp, cliente, NumdocCli, lvdesde, lvhasta, subtotal, ii, iva, total, cae, fechacae, cantitems, fecha, hora, usuario,
                                 pagada, afectastock, remitidototal, letracomp, fecharemito, fecharemitostr, id_deposito, mpago1, mpago1imp)
            SELECT t.id, t.tipocomp, t.PtoVta, COALESCE(r.num_nuevo, t.numcomp), t.cliente, t.NumdocCli, t.lvdesde, t.lvhasta, t.subtotal, t.ii, t.iva, t.total, t.cae, t.fechacae, t.cantitems,
                   STR_TO_DATE(LEFT(t.fecha, 10), '%Y-%m-%d'), t.hora, t.usuario,
                   1, 1, 1, $tc, STR_TO_DATE(LEFT(t.fecha, 10), '%Y-%m-%d'), LEFT(t.fecha, 10),
                   CASE t.PtoVta WHEN 3 THEN $depLQ WHEN 4 THEN $depRS ELSE NULL END, 'Sin Informar', t.total
            FROM $SRC.tickets t LEFT JOIN _mig_renumerados r ON r.id_ticket = t.id");
$pdo->exec("INSERT INTO Lineadeventa (id, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant, siniva, iva, ii, total, rubro, alicuota, mpago1, mpago2, IIBB, costo, usuario, fiscal)
            SELECT id, ptovta, Tipocomp, numcomp, cliente, STR_TO_DATE(LEFT(fecha, 10), '%Y-%m-%d'), prodcod, proddes, cant, siniva, iva, ii, total, rubro, alicuota, mpago1, mpago2, IIBB, costo, usuario, IF(Tipocomp < 100, 1, 0)
            FROM $SRC.lineadeventa");
$pdo->exec("UPDATE Lineadeventa l JOIN _mig_renumerados r ON l.id BETWEEN r.linea_desde AND r.linea_hasta AND l.pdv = r.ptovta AND l.Tipocomp = r.tipocomp AND l.numcomp = r.num_original SET l.numcomp = r.num_nuevo");
// El ticket viejo no guardaba el medio de pago: se toma el de su primera línea.
$pdo->exec("UPDATE tickets t JOIN Lineadeventa l ON l.id = t.lvdesde SET t.mpago1 = TRIM(TRAILING 'ç' FROM l.mpago1) WHERE l.mpago1 IS NOT NULL AND l.mpago1 <> ''");
logm('ventas: tickets ' . contar($pdo, $DST, 'tickets') . ' / Lineadeventa ' . contar($pdo, $DST, 'Lineadeventa'));

// Libro de caja del sistema viejo (con su punto de venta)
try { $pdo->exec("ALTER TABLE Caja ADD COLUMN ptovta INT(11) NULL"); } catch (Throwable $e) { }
$pdo->exec("INSERT INTO Caja (id, FechaMov, HoraMov, TipoMov, Debe, Haber, Saldo, usuario, ptovta) SELECT id, FechaMov, HoraMov, TipoMov, Debe, Haber, Saldo, usuario, ptovta FROM $SRC.caja");
logm('caja histórica: ' . contar($pdo, $DST, 'Caja') . ' movimientos');

// ------------------------------------------------------------------ usuarios
$pdo->exec("INSERT INTO alb_usuarios (usuario, password, nivel, esadmin, habilitado, logout, iddepositoactivo) SELECT usuario, password, nivel, IF(nivel = 1, 1, 0), 1, 0, $depLQ FROM $SRC.usuarios");
$n = hashearContrasenas($pdo, $DST);
foreach ($pdo->query("SELECT usuario FROM alb_usuarios")->fetchAll(PDO::FETCH_COLUMN) as $u) {
    $pdo->prepare("INSERT INTO usuarios_depositos (usuario, id_deposito, es_predeterminado) VALUES (?, ?, 1), (?, ?, 0)")->execute([$u, $depLQ, $u, $depRS]);
}
logm("usuarios: " . contar($pdo, $DST, 'alb_usuarios') . " ($n contraseñas convertidas); acceso a los dos depósitos");
// Usuarios del negocio (creados en el VPS el 16/10/2026). Contraseñas por variable de entorno:
//   set PW_NICOLAS=...  set PW_QUESERIA=...  set PW_RICSAN=...
crearUsuarioWeb($pdo, 'Nicolas', 'PW_NICOLAS', true, [$depLQ, $depRS]);
crearUsuarioWeb($pdo, 'Queseria', 'PW_QUESERIA', false, [$depLQ]);
crearUsuarioWeb($pdo, 'Ricsan', 'PW_RICSAN', false, [$depRS]);
logm('usuarios del negocio: Nicolas (admin, ambos depósitos), Queseria (La Quesería), Ricsan (Ric San)');

// ------------------------------------------------------------------ empresa y configuración
$padron = json_decode((string) @file_get_contents("https://erp.westit.com.ar/ARCA/padron/consultar_padron.php?cuit=$CUIT"), true) ?: [];
$pdo->exec("DELETE FROM datosempresa");
$pdo->prepare("INSERT INTO datosempresa (id, razon, domicilio, localidad, provincia, cuit, condicioniva, telefono, email, web, numiibb, puntodeventa) VALUES (1, ?, ?, ?, 'Mendoza', ?, ?, '', '', '', ?, 3)")
    ->execute([$padron['razonSocial'] ?? 'PERAGGINI, PABLO NICOLAS', $padron['domicilio'] ?? 'SARGENTO CABRAL 396', $padron['localidad'] ?? 'MENDOZA', $CUIT, $padron['condicionFiscal'] ?? 'Responsable Inscripto', $CUIT]);
$idCfg = (int) $pdo->query("SELECT MAX(id) FROM configuracion")->fetchColumn();
$pdo->prepare("UPDATE configuracion SET multidepositohabilitado = 1, deposito_default_id = ?, admitenotasdebito = 0, logo = COALESCE(?, logo) WHERE id = ?")->execute([$depLQ, $logo, $idCfg]);
logm('datosempresa: ' . ($padron['razonSocial'] ?? '?') . ' / PV 3; configuración con multidepósito y logo');

// ------------------------------------------------------------------ contabilidad: cuentas predeterminadas válidas
$cpCorr = ['mercaderia' => '11301001', 'chequesrechazados' => '11201001', 'comisionesvend' => '21103012', 'comisionestarjetas' => '42009001'];
foreach ($cpCorr as $campo => $cta) {
    $v = $pdo->query("SELECT `$campo` FROM cuentaspredeterminadas WHERE id = 1")->fetchColumn();
    $ok = $v && (int) $pdo->query("SELECT COUNT(*) FROM plandecuentas WHERE imputable = 1 AND numcuenta = " . $pdo->quote((string) $v))->fetchColumn();
    if (!$ok || $campo === 'comisionesvend') $pdo->prepare("UPDATE cuentaspredeterminadas SET `$campo` = ? WHERE id = 1")->execute([$cta]);
}
foreach ($pdo->query("SELECT id, medio, cuentacontable FROM mediosdepago m WHERE habilitado = 1 AND NOT EXISTS (SELECT 1 FROM plandecuentas p WHERE p.numcuenta = m.cuentacontable AND p.imputable = 1)")->fetchAll() as $m) {
    logm("  ATENCION medio {$m['id']} {$m['medio']} sin cuenta contable válida");
}

ajustarAutoIncrement($pdo, $DST);
// logo de configuración como LONGBLOB (el legacy lo trae como BLOB/MEDIUMBLOB y los logos actuales no entran)
$pdo->exec("ALTER TABLE `{$DST}`.configuracion MODIFY logo LONGBLOB NULL");
$al = alinearConReferencia($pdo, $DST, 'ref_sofi', 'logm');
logm('netos e IVA recalculados desde el precio final: ' . recalcularNetos($pdo, $DST) . ' productos');
logm("estructura idéntica a westit_sofi: {$al['columnas']} columnas, {$al['indices']} índices, {$al['borradas']} columnas basura borradas, " . count($al['errores']) . ' errores');

// Saldo de efectivo al corte: último saldo corrido del libro de caja legacy por punto de venta -> puesto de caja
// del usuario de ese depósito (PV 3 La Quesería -> Puesto 2 de Queseria; PV 4 Ric San -> Puesto 3 de Ricsan).
$PV_A_PUESTO = [3 => 2, 4 => 3];
foreach ($PV_A_PUESTO as $pv => $puesto) {
    $u = $pdo->query("SELECT Saldo, FechaMov, HoraMov FROM $SRC.caja WHERE ptovta = $pv ORDER BY id DESC LIMIT 1")->fetch();
    if (!$u) continue;
    saldoInicialCaja($pdo, $puesto, (float) $u['Saldo'], "SALDO INICIAL MIGRACION - caja legacy PV $pv al {$u['FechaMov']} {$u['HoraMov']}");
    logm(sprintf('saldo inicial Puesto %d (PV %d): $%s', $puesto, $pv, number_format((float) $u['Saldo'], 2, ',', '.')));
}
$falta = diferencias($pdo, $DST, 'ref_sofi');
logm('diferencias de esquema contra la referencia: ' . (count($falta) ? implode(', ', $falta) : 'ninguna'));
foreach ([['tickets', 'tickets'], ['lineadeventa', 'Lineadeventa'], ['clientes', 'alb_clientes'], ['productos', 'alb_productos'], ['caja', 'Caja'], ['usuarios', 'alb_usuarios']] as [$o, $d]) {
    logm(sprintf('  %-14s origen %8d   destino %8d', $o, contar($pdo, $SRC, $o), contar($pdo, $DST, $d)));
}
$tot = $pdo->query("SELECT ROUND(SUM(total),2) FROM $SRC.tickets")->fetchColumn();
$totD = $pdo->query("SELECT ROUND(SUM(total),2) FROM tickets")->fetchColumn();
logm("  total facturado origen $tot / destino $totD");
logm('== nicolas terminado');
