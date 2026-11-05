<?php
require_once 'permisos_helpers.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'tarjetas_helpers.php';

/** El llamador controla la transacción: permite verificar con rollback en testing. */
function anularPago(PDO $pdo, string $tipo, int $id, string $fecha, string $usuario): void {
    if (!$pdo->inTransaction()) throw new Exception('La anulación requiere una transacción.');
    $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
    if (!$dt || $dt->format('Y-m-d') !== $fecha || $fecha > date('Y-m-d')) throw new Exception('Fecha de anulación inválida.');
    $proveedor = $tipo === 'proveedor';
    $tabla = $proveedor ? 'pagoaproveedores' : 'pagos';
    $st = $pdo->prepare("SELECT * FROM $tabla WHERE id = ? FOR UPDATE");
    $st->execute([$id]);
    $pago = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pago) throw new Exception('No se encontró el comprobante.');
    if ((int)$pago['estado'] === 1000) throw new Exception('El comprobante ya está anulado.');
    if (!$proveedor && esVendedorRestringido()) {
        $st = $pdo->prepare('SELECT vendedorid FROM alb_clientes WHERE id = ?');
        $st->execute([$pago['cliente']]);
        if ((int)$st->fetchColumn() !== idVendedorRestringido()) throw new Exception('No tiene acceso a este recibo.');
    }
    if ($fecha < substr($pago[$proveedor ? 'fecharecibo' : 'fecha'], 0, 10)) throw new Exception('La anulación no puede ser anterior al comprobante.');

    $ejPer = resolverEjercicioPeriodo($pdo, $fecha);
    $ejercicio = $ejPer['ejercicio'];
    $periodo = $ejPer['periodo'];
    // Cupones de tarjeta del recibo: los pendientes se anulan, los liquidados se compensan.
    if (!$proveedor) revertirCuponesDe($pdo, 'pagos', $id, $fecha, $usuario);
    // Retenciones practicadas en el pago (agente de retención): quedan anuladas. Su línea
    // del asiento se revierte junto con el resto del asiento del pago, más abajo.
    if ($proveedor) {
        try {
            $pdo->prepare("UPDATE retenciones_practicadas SET estado = 1000 WHERE idpago = ?")->execute([$id]);
        } catch (PDOException $e) {
            // base sin la migración de retenciones: no hay nada que anular
        }
    }

    $relacion = $proveedor ? 'pagosfacturasproveedores' : 'cobrosfacturasclientes';
    $condicion = $proveedor ? " AND tipo = 'P'" : '';
    $st = $pdo->prepare("SELECT DISTINCT idfactura FROM $relacion WHERE idpago = ? $condicion FOR UPDATE");
    $st->execute([$id]); $facturas = $st->fetchAll(PDO::FETCH_COLUMN);
    $pdo->prepare("DELETE FROM $relacion WHERE idpago = ? $condicion")->execute([$id]);
    foreach ($facturas as $factura) {
        if ($proveedor) {
            $pdo->prepare("UPDATE facturasdecompra SET saldoaimputar = GREATEST(0, total - (SELECT COALESCE(SUM(importepagado),0) FROM pagosfacturasproveedores WHERE idfactura = ?)), estado = CASE WHEN total - (SELECT COALESCE(SUM(importepagado),0) FROM pagosfacturasproveedores WHERE idfactura = ?) > 0.01 THEN 1 ELSE 2 END WHERE id = ? AND estado <> 1000")->execute([$factura, $factura, $factura]);
        } else {
            $pdo->prepare("UPDATE tickets SET pagada = CASE WHEN COALESCE(importectacte, total) - (SELECT COALESCE(SUM(importepagado),0) FROM cobrosfacturasclientes WHERE idfactura = ?) > 0.01 THEN 0 ELSE 1 END WHERE id = ?")->execute([$factura, $factura]);
        }
    }
    $saldoCampo = $proveedor ? 'saldoacuenta' : 'saldoaimputar';
    $pdo->prepare("UPDATE $tabla SET estado = 1000, $saldoCampo = 0 WHERE id = ?")->execute([$id]);
    $detalle = ($proveedor ? 'Anulación pago #' : 'Anulación recibo #') . $id;
    if ($proveedor) {
        // El ID interno y el número de comprobante pueden divergir. En datos
        // migrados idpago puede contener numcomp: nunca usar sólo idpago o
        // sólo compasoc, porque ambos pueden coincidir con otro pago.
        $stmtCC = $pdo->prepare("SELECT id, pagos FROM moviemientosccproveedores
            WHERE tabla = 'pagoaproveedores' AND proveedor = ?
              AND ((idpago = ? AND (compasoc = 0 OR (? <> 0 AND compasoc = ?)))
                   OR (? <> 0 AND compasoc = ? AND fecha = DATE(?) AND pagos = ?)) FOR UPDATE");
        $stmtCC->execute([$pago['proveedor'], $id, $pago['numcomp'], $pago['numcomp'], $pago['numcomp'], $pago['numcomp'], $pago['fecharecibo'], $pago['total']]);
        $movimientosCC = $stmtCC->fetchAll(PDO::FETCH_ASSOC);
        if (count($movimientosCC) === 1) {
            $pdo->prepare('UPDATE moviemientosccproveedores SET pagos = 0, mpago1imp = 0, mpago2imp = 0 WHERE id = ?')->execute([$movimientosCC[0]['id']]);
        } else {
            // Si no hay una fila única, se conserva el historial y se registra
            // un cargo compensatorio por el efecto real (o el total del pago).
            $importeCC = array_sum(array_map(static fn($m) => (float)$m['pagos'], $movimientosCC));
            if (!$movimientosCC) $importeCC = (float)$pago['total'];
            if (abs($importeCC) > 0.001) {
                $pdo->prepare("INSERT INTO moviemientosccproveedores
                    (proveedor, facturas, pagos, fecha, compasoc, tabla, fiscal, usuario, idpago)
                    VALUES (?, ?, 0, ?, ?, 'anulacion_pagoaproveedores', ?, ?, ?)")
                    ->execute([$pago['proveedor'], $importeCC, $fecha, $pago['numcomp'], $pago['fiscal'], $usuario, $id]);
            }
        }
    } else {
        $stmtCC = $pdo->prepare("SELECT id, haber, fiscal FROM ccclientes WHERE compasoc = ? AND tabla = 'pagos' AND cliente = ? FOR UPDATE");
        $stmtCC->execute([$id, $pago['cliente']]);
        $movimientosCC = $stmtCC->fetchAll(PDO::FETCH_ASSOC);
        if (count($movimientosCC) === 1) {
            $pdo->prepare('UPDATE ccclientes SET haber = 0, retencionespagos = 0 WHERE id = ?')->execute([$movimientosCC[0]['id']]);
        } else {
            $importeCC = array_sum(array_map(static fn($m) => (float)$m['haber'], $movimientosCC));
            if (!$movimientosCC) $importeCC = (float)$pago['imp1'] + (float)$pago['imp2'] + (float)$pago['retiva'] + (float)$pago['retgan'] + (float)$pago['retiibb'];
            if (abs($importeCC) > 0.001) {
                if ($movimientosCC) {
                    $fiscalCC = $movimientosCC[0]['fiscal'];
                } else {
                    $stmtFiscal = $pdo->prepare("SELECT fiscal FROM cccuentastesoreria WHERE tabla = 'pagos' AND compasoc = ? ORDER BY id LIMIT 1");
                    $stmtFiscal->execute([$id]);
                    $fiscalCC = $stmtFiscal->fetchColumn();
                    if ($fiscalCC === false) $fiscalCC = 1;
                }
                $pdo->prepare("INSERT INTO ccclientes (cliente, debe, haber, saldo, compasoc, tabla, fiscal, fecha)
                    VALUES (?, ?, 0, 0, ?, 'anulacion_pagos', ?, ?)")
                    ->execute([$pago['cliente'], $importeCC, $id, $fiscalCC, $fecha]);
            }
        }
        // El saldo almacenado también se usa al registrar el próximo recibo.
        $st = $pdo->prepare('SELECT id, debe, haber FROM ccclientes WHERE cliente = ? ORDER BY id FOR UPDATE');
        $st->execute([$pago['cliente']]); $saldo = 0;
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            $saldo += (float)$fila['debe'] - (float)$fila['haber'];
            $pdo->prepare('UPDATE ccclientes SET saldo = ? WHERE id = ?')->execute([$saldo, $fila['id']]);
        }
    }

    // Contramovimientos: conservar los originales y neutralizar todos los medios.
    $numTeso = (int)$pdo->query('SELECT COALESCE(MAX(numerodeasiento),0) + 1 FROM cccuentastesoreria FOR UPDATE')->fetchColumn();
    $st = $pdo->prepare('SELECT * FROM cccuentastesoreria WHERE tabla = ? AND compasoc = ? FOR UPDATE');
    $st->execute([$tabla, $id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $pdo->prepare('INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute([$fila['idcuenta'], $fila['haber'], $fila['debe'], $tabla, $usuario, $fecha, $detalle, $fila['fiscal'], $id, $numTeso]);
    }
    $st = $pdo->prepare('SELECT * FROM librodiario WHERE tabla = ? AND compasoc = ? FOR UPDATE');
    $st->execute([$tabla, $id]); $lineas = $st->fetchAll(PDO::FETCH_ASSOC);
    $st = $pdo->prepare('SELECT COALESCE(MAX(numasiento),0) + 1 FROM librodiario WHERE idejercicio = ? FOR UPDATE');
    $st->execute([$ejercicio]); $asiento = (int)$st->fetchColumn();
    foreach ($lineas as $fila) grabarLineaAsiento($pdo, $asiento, $fecha, $fila['cuenta'], $fila['haber'], $fila['debe'], $id, $tabla, $fila['fiscal'], $usuario, $detalle, $ejercicio, $periodo);

    $tablaBanco = $proveedor ? 'pagosprov' : 'pagos';
    $st = $pdo->prepare('SELECT * FROM ccbancos WHERE tabla = ? AND compasoc = ? FOR UPDATE');
    $st->execute([$tablaBanco, $id]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        $sb = $pdo->prepare('SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE');
        $sb->execute([$fila['cuenta']]);
        $saldo = (float)$sb->fetchColumn() + (float)$fila['haber'] - (float)$fila['debe'];
        $pdo->prepare('INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')->execute([$fila['cuenta'], $fila['haber'], $fila['debe'], $saldo, $fecha, $id, $tablaBanco, $detalle]);
    }
    foreach ([1, 2] as $n) {
        $importe = (float)$pago[$proveedor ? "mp{$n}imp" : "imp$n"];
        if ($importe <= 0) continue;
        $st = $pdo->prepare('SELECT tipodecuenta, puestoasociado FROM mediosdepago WHERE id = ?');
        $st->execute([$pago['mp'.$n]]);
        $medio = $st->fetch(PDO::FETCH_ASSOC);
        if (!$medio || $medio['tipodecuenta'] !== 'Caja de Usuarios') continue;
        $st = $pdo->prepare("SELECT id, idcaja FROM controlcajas WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1 FOR UPDATE");
        $st->execute([$usuario]); $caja = $st->fetch(PDO::FETCH_ASSOC);
        if (!$caja) throw new Exception('Debe tener caja abierta para anular un movimiento en efectivo.');
        if ((int)$medio['puestoasociado'] !== (int)$caja['idcaja']) throw new Exception('Para anular, abra la caja correspondiente al medio de pago original.');
        $debe = $proveedor ? $importe : 0; $haber = $proveedor ? 0 : $importe;
        $pdo->prepare('INSERT INTO cajaxusuario (usuario, debe, haber, idcaja, idcontrolcaja, fechamov, tipomov, horamov) VALUES (?, ?, ?, ?, ?, ?, ?, CURTIME())')->execute([$usuario, $debe, $haber, $caja['idcaja'], $caja['id'], $fecha, $detalle]);
        $pdo->prepare('UPDATE cajas SET saldo = saldo + ? WHERE id = ?')->execute([$debe - $haber, $caja['idcaja']]);
        $pdo->prepare('UPDATE controlcajas SET saldofinal = saldofinal + ? WHERE id = ?')->execute([$debe - $haber, $caja['id']]);
    }
    if ($proveedor) {
        $st = $pdo->prepare('SELECT 1 FROM chequesterceros WHERE pago_endoso_id = ? AND estado <> 2 LIMIT 1');
        $st->execute([$id]);
        if ($st->fetchColumn()) throw new Exception('Hay un cheque endosado con movimientos posteriores. Revise su rechazo o devolución antes de anular el pago.');
        $st = $pdo->prepare("SELECT 1 FROM ccbancos b JOIN chequespropios c ON c.id = b.compasoc WHERE b.tabla = 'chequespropios' AND c.idmovimiento = ? AND c.tabla = 'pagos' AND c.proveedor = ? LIMIT 1");
        $st->execute([$id, $pago['proveedor']]);
        if ($st->fetchColumn()) throw new Exception('Hay cheques propios debitados por el banco. Revierta el débito antes de anular el pago.');
        $pdo->prepare("UPDATE chequespropios SET estado = 1000 WHERE idmovimiento = ? AND tabla = 'pagos' AND proveedor = ?")->execute([$id, $pago['proveedor']]);
        $pdo->prepare("UPDATE chequesterceros SET estado = 0, endosadoa = 0, fachaendoso = '0', tabla = CASE WHEN recibo_origen_id IS NULL THEN 'origen_desconocido' ELSE 'pagos' END, compasoc = COALESCE(recibo_origen_id, 0), pago_endoso_id = NULL WHERE pago_endoso_id = ? AND endosadoa = ? AND estado = 2")->execute([$id, $pago['proveedor']]);
    } else {
        $st = $pdo->prepare("SELECT COALESCE(SUM(importe),0) FROM chequesterceros WHERE recibo_origen_id = ? AND cliente = ?");
        $st->execute([$id, $pago['cliente']]); $totalCheques = (float)$st->fetchColumn();
        $st = $pdo->prepare("SELECT id FROM mediosdepago WHERE tipodecuenta = 'Cartera de Cheques'");
        $mediosCheques = $st->fetchAll(PDO::FETCH_COLUMN);
        $esperadoCheques = 0;
        foreach ([1, 2] as $n) if (in_array($pago['mp'.$n], $mediosCheques)) $esperadoCheques += (float)$pago['imp'.$n];
        if ($esperadoCheques > $totalCheques + 0.01) throw new Exception('No se puede identificar el origen de todos los cheques de este recibo antiguo. Revise sus endosos antes de anular.');
        $st = $pdo->prepare("SELECT id, estado FROM chequesterceros WHERE recibo_origen_id = ? AND cliente = ? FOR UPDATE");
        $st->execute([$id, $pago['cliente']]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $cheque) {
            if (!in_array((int)$cheque['estado'], [0, 1000], true)) throw new Exception('El recibo tiene un cheque depositado o endosado. Revierta ese movimiento antes de anular.');
            $pdo->prepare('UPDATE chequesterceros SET estado = 1000 WHERE id = ?')->execute([$cheque['id']]);
        }
    }
}
