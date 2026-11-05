<?php
/**
 * api_cobros.php - BACKEND DE COBRO A CLIENTES CON ASIENTO CONTABLE
 * v2.3 - Autodetección de Caja Abierta y Fix Filtros
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'permisos_helpers.php';
require_once 'tarjetas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$idcajaabierta =  0;
$idcontrolcaja =  0;

// ---------------------------------------------------------------------------
// FIX: AUTODETECCIÓN DE CAJA ABIERTA (Si falla la sesión)
// ---------------------------------------------------------------------------
if ($idcajaabierta == 0) {
    // 1. Buscar en tabla 'cajas' si el usuario figura como activo en alguna
    $stmtBox = $pdo->prepare("SELECT id FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
    $stmtBox->execute([$usuario]);
    $cajaIdDB = $stmtBox->fetchColumn();
    
    if ($cajaIdDB) {
        $idcajaabierta = $cajaIdDB;
        
        // 2. Intentar recuperar el ID de control (auditoría) asociado
        // Buscamos el último registro de apertura de este usuario en esta caja
        $stmtCtrl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? ORDER BY id DESC LIMIT 1");
        $stmtCtrl->execute([$idcajaabierta, $usuario]);
        $idcontrolcaja = $stmtCtrl->fetchColumn() ?: 0;
    }
}
// ---------------------------------------------------------------------------

// grabarLineaAsiento() ahora vive en contabilidad_cuentas_helpers.php (ya incluido arriba)
// para que todos los módulos compartan la misma validación de cuenta e importe.

try {
    switch ($action) {
        case 'get_next_id':
            $stmt = $pdo->query("SELECT MAX(id) FROM pagos");
            $max = $stmt->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'next_id' => $max + 1]);
            exit;

        case 'get_medios':
            // CORRECCIÓN: Se agrega condición OR puestoasociado = ? para soportar vínculo por ID directo o NumCaja
            $sql = "SELECT id, medio, tipodecuenta FROM mediosdepago 
                    WHERE habilitado = 1 
                    AND tipodecuenta <> 'Cuenta Corriente' 
                    AND habilitadocobro = 1 
                    AND (
                        (tipodecuenta = 'Caja de Usuarios' AND (puestoasociado = (SELECT numcaja FROM cajas WHERE id = ?) OR puestoasociado = ?)) 
                        OR tipodecuenta <> 'Caja de Usuarios'
                    )
                    ORDER BY medio ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idcajaabierta, $idcajaabierta]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(), 'tarjetas' => tarjetasHabilitadas($pdo)]);
            exit;

        case 'get_medios_pago':
            // CORRECCIÓN: Se agrega condición OR puestoasociado = ? para soportar vínculo por ID directo o NumCaja
            $sql = "SELECT id, medio, tipodecuenta FROM mediosdepago 
                    WHERE habilitado = 1 
                    AND tipodecuenta <> 'Cuenta Corriente' 
                    AND habilitadopagos = 1 
                    AND (
                        (tipodecuenta = 'Caja de Usuarios' AND (puestoasociado = (SELECT numcaja FROM cajas WHERE id = ?) OR puestoasociado = ?)) 
                        OR tipodecuenta <> 'Caja de Usuarios'
                    )
                    ORDER BY medio ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idcajaabierta, $idcajaabierta]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;

        case 'get_bancos':
            echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, denominacion FROM bancos ORDER BY denominacion ASC")->fetchAll()]);
            exit;

        case 'search_clientes':
            $q = "%" . ($_GET['q'] ?? '') . "%";
            $whereVende = "";
            $params = [$q, $q];
            if (esVendedorRestringido()) {
                $whereVende = " AND vendedorid = ? ";
                $params[] = idVendedorRestringido();
            }
            $stmt = $pdo->prepare("SELECT id, razon FROM alb_clientes WHERE (razon LIKE ? OR id LIKE ?) $whereVende ORDER BY razon ASC LIMIT 20");
            $stmt->execute($params);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            exit;

        case 'get_pendientes':
            $idcli = $_GET['cliente'];

            if (esVendedorRestringido()) {
                $stmtChk = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = ?");
                $stmtChk->execute([$idcli]);
                if ((int) $stmtChk->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a los movimientos de este cliente.");
                }
            }

            $stmtSaldo = $pdo->prepare("SELECT ROUND(SUM(debe) - SUM(haber),2) FROM ccclientes WHERE cliente = ?");
            $stmtSaldo->execute([$idcli]);
            $saldo = $stmtSaldo->fetchColumn() ?: 0;

            $sql = "SELECT id, 
                    CASE tipocomp 
                        WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 1000 THEN 'REM' WHEN 6 THEN 'FCB' 
                        WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' WHEN 11 THEN 'FCC' 
                        WHEN 13 THEN 'NCC' ELSE 'Otro' 
                    END AS tipo_label,
                    CONCAT(LPAD(PtoVta, 4, '0'),'-',LPAD(numcomp, 8, '0')) AS num_factura,
                    total,
                    COALESCE(importectacte, total) - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0) AS pendiente,
                    DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f,
                    DATE_FORMAT(vencimiento, '%d-%m-%Y') as vence_f,
                    (tipocomp < 100) as es_fiscal
                    FROM tickets 
                    WHERE cliente = ? AND pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101)
                    ORDER BY fecha ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idcli]);

            $config = $pdo->query("SELECT imputarcobros FROM configuracion LIMIT 1")->fetch();

            echo json_encode([
                'success' => true,
                'saldo' => $saldo,
                'data' => $stmt->fetchAll(),
                'autoimputar' => $config['imputarcobros'] ?? false
            ]);
            exit;

        case 'save_cobro':
            $data = json_decode(file_get_contents('php://input'), true);
            $fecha = $data['fecha']; // Fecha del movimiento

            // VALIDACIÓN FECHA FUTURA
            if ($fecha > date('Y-m-d')) {
                throw new Exception("No se pueden grabar movimientos con fecha futura.");
            }

            if (esVendedorRestringido()) {
                $stmtChkCli = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = ?");
                $stmtChkCli->execute([$data['cliente_id']]);
                if ((int) $stmtChkCli->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a registrar cobros de este cliente.");
                }
            }

            $pdo->beginTransaction();

            // ---------------------------------------------------------
            // REGLA CONTABLE CRÍTICA (Validación Ejercicio y Período)
            // ---------------------------------------------------------
            
            // A. Buscar Ejercicio Abierto
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fecha, $fecha]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();
            
            if (!$ejercicio_id) {
                throw new Exception("No hay un ejercicio contable abierto para la fecha del cobro ($fecha).");
            }

            // B. Buscar Estructura de Períodos
            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
            
            if (!$rowPer) {
                throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");
            }

            // C. Calcular Período Actual dinámicamente
            $fechaDT = new DateTime($fecha);
            $anio_fecha = (int)$fechaDT->format('Y');
            $mes_fecha = (int)$fechaDT->format('m');

            $mesesDesdeInicio = (($anio_fecha - (int)$rowPer['anioinicio']) * 12) + ($mes_fecha - (int)$rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int)$rowPer['cantperiodos']) {
                throw new Exception("La fecha del cobro no pertenece a ningún período válido del ejercicio.");
            }

            // D. Validar si el período específico está abierto
            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int)$rowPer[$campoP] === 0) {
                throw new Exception("El período contable $periodoActual está cerrado. No se pueden registrar cobros.");
            }
            // ---------------------------------------------------------

            $totalRet = $data['retenciones']['ganancias'] + $data['retenciones']['iibb'] + $data['retenciones']['iva'];
            $totalCobro = $data['medio1']['importe'] + $data['medio2']['importe'] + $totalRet;
            $facturasSeleccionadas = $data['facturas_imputar'] ?? [];
            $facturaIds = idsSeleccionados($facturasSeleccionadas);
            $esFis = $data['es_fiscal'] ? 1 : 0;

            if ($facturaIds) {
                $sqlFiscal = "SELECT id, (tipocomp < 100) AS fiscal FROM tickets WHERE cliente = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
                $stmtFiscal = $pdo->prepare($sqlFiscal);
                $stmtFiscal->execute(array_merge([(int)$data['cliente_id']], $facturaIds));
                $esFis = fiscalUnicoDeFilas($stmtFiscal->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante');
            }

            // 1. Insertar en pagos (Encabezado)
            $stmtP = $pdo->prepare("INSERT INTO pagos (mp1, mp2, imp1, imp2, cliente, retiva, retgan, retiibb, usuario, fecha, saldoaimputar) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtP->execute([
                $data['medio1']['id'],
                $data['medio2']['id'] ?: 0,
                $data['medio1']['importe'],
                $data['medio2']['importe'],
                $data['cliente_id'],
                $data['retenciones']['iva'],
                $data['retenciones']['ganancias'],
                $data['retenciones']['iibb'],
                $usuario,
                $fecha,
                $totalCobro
            ]);
            $idPago = $pdo->lastInsertId();

            // 2. OBTENER NUMERADOR TESORERÍA (Secuencial Global)
            $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = ((int)$stmtTesoNum->fetchColumn()) + 1;

            // Nombre Cliente para descripción
            $descTeso = "Cobro a " . $data['nombre_cliente'];

            // 3. INSERTAR EN CCCUENTASTESORERIA (MP1) - DEBE
            $stmtTeso = $pdo->prepare("INSERT INTO cccuentastesoreria 
                (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) 
                VALUES (?, ?, 0, 'pagos', ?, ?, ?, ?, ?, ?)");
            
            $stmtTeso->execute([
                $data['medio1']['id'], $data['medio1']['importe'], $usuario, $fecha, $descTeso, $esFis, $idPago, $num_asiento_teso
            ]);

            // 4. INSERTAR EN CCCUENTASTESORERIA (MP2 si existe) - DEBE
            if ($data['medio2']['id'] && $data['medio2']['importe'] > 0) {
                $stmtTeso->execute([
                    $data['medio2']['id'], $data['medio2']['importe'], $usuario, $fecha, $descTeso, $esFis, $idPago, $num_asiento_teso
                ]);
            }

            // 5. MOVIMIENTOS CC CLIENTES
            $stmtSaldo = $pdo->prepare("SELECT saldo FROM ccclientes WHERE cliente = ? ORDER BY id DESC LIMIT 1");
            $stmtSaldo->execute([$data['cliente_id']]);
            $saldoActual = $stmtSaldo->fetchColumn() ?: 0;
            $nuevoSaldo = $saldoActual - $totalCobro;

            $pdo->prepare("INSERT INTO ccclientes (cliente, debe, haber, saldo, compasoc, mediopago, tabla, fiscal, fecha, retencionespagos) VALUES (?, 0, ?, ?, ?, ?, 'pagos', ?, ?, ?)")
                ->execute([$data['cliente_id'], $totalCobro, $nuevoSaldo, $idPago, $data['medio1']['id'], $esFis, $fecha, $totalRet]);

            // 6. GESTIÓN DE MEDIOS DE PAGO (BANCOS / CAJAS)
            $medios = [];
            if ($data['medio1']['importe'] > 0) $medios[] = $data['medio1'];
            if ($data['medio2']['importe'] > 0) $medios[] = $data['medio2'];

            foreach ($medios as $nMedio => $m) {
                $stmtCta = $pdo->prepare("SELECT tipodecuenta, CuentaBancaria, cuentacontable FROM mediosdepago WHERE id = ?");
                $stmtCta->execute([$m['id']]);
                $medioInfo = $stmtCta->fetch();

                // Cupón de tarjeta para la liquidación posterior
                $datosTarjeta = datosTarjetaCobro($pdo, $medioInfo, $m['tarjeta'] ?? null, 'el cobro');
                if ($datosTarjeta) {
                    registrarCupon($pdo, $datosTarjeta, (float) $m['importe'], (int) $m['id'], 'pagos', (int) $idPago,
                        (int) $data['cliente_id'], $esFis, $fecha, $usuario);
                }

                if ($medioInfo['tipodecuenta'] === 'Caja de Usuarios') {
                    $pdo->prepare("INSERT INTO cajaxusuario (fechamov, horamov, tipomov, debe, haber, usuario, idcaja, idcontrolcaja) VALUES (?, CURTIME(), ?, ?, 0, ?, ?, ?)")
                        ->execute([$fecha, $descTeso, $m['importe'], $usuario, $idcajaabierta, $idcontrolcaja]);
                    $pdo->prepare("UPDATE cajas SET saldo = saldo + ? WHERE id = ?")->execute([$m['importe'], $idcajaabierta]);
                    $pdo->prepare("UPDATE controlcajas SET cantidadmovimientos = cantidadmovimientos + 1, saldofinal = saldofinal + ? WHERE id = ?")->execute([$m['importe'], $idcontrolcaja]);
                }
                if ($medioInfo['tipodecuenta'] === 'Bancos' && $medioInfo['CuentaBancaria']) {
                    // Obtener saldo
                    $stSaldoBco = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
                    $stSaldoBco->execute([$medioInfo['CuentaBancaria']]);
                    $saldoActualBco = $stSaldoBco->fetchColumn() ?: 0;
                    $nuevoSaldoBco = $saldoActualBco + $m['importe'];

                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, 0, ?, ?, ?, 'pagos', ?)")
                        ->execute([$medioInfo['CuentaBancaria'], $m['importe'], $nuevoSaldoBco, $fecha, $idPago, $descTeso]);
                }
            }

            // 7. GRABACIÓN DE CHEQUES DE TERCEROS
            if (!empty($data['cheques'])) {
                $sqlChq = "INSERT INTO chequesterceros (cliente, numero, fechaemision, fechavencimiento, importe, banco, tipo, origen, emisor, observaciones, noalaorden, estado, compasoc, recibo_origen_id, tabla)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 'pagos')";
                $stmtChq = $pdo->prepare($sqlChq);
                foreach ($data['cheques'] as $c) {
                    $stmtChq->execute([
                        $data['cliente_id'],
                        $c['numero'],
                        $c['fecha_emision'],
                        $c['vencimiento'],
                        $c['importe'],
                        (int) $c['banco_id'],
                        $c['tipo'],
                        $c['origen'],
                        $c['emisor'],
                        '',
                        $c['nala'] ? 1 : 0,
                        $idPago, $idPago
                    ]);
                }
            }

            // 8. IMPUTACIÓN DE FACTURAS
            $saldoRestante = $totalCobro;
            foreach ($data['facturas_imputar'] as $f) {
                if ($saldoRestante <= 0) break;
                $pagoAFactura = min($f['pendiente'], $saldoRestante);
                if ($pagoAFactura > 0) {
                    $pdo->prepare("INSERT INTO cobrosfacturasclientes (idpago, idfactura, importepagado) VALUES (?, ?, ?)")
                        ->execute([$idPago, $f['id'], $pagoAFactura]);
                    if ($pagoAFactura >= $f['pendiente'])
                        $pdo->prepare("UPDATE tickets SET pagada = 1 WHERE id = ?")->execute([$f['id']]);
                    $saldoRestante -= $pagoAFactura;
                }
            }
            $pdo->prepare("UPDATE pagos SET saldoaimputar = ? WHERE id = ?")->execute([$saldoRestante, $idPago]);

            // ---------------------------------------------------------
            // 9. CONTABILIDAD (LIBRO DIARIO) - LÓGICA VB.NET MIGRADA
            // ---------------------------------------------------------
            
            // A. Obtener Número de Asiento
            $stmtAccNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtAccNum->execute([$ejercicio_id]);
            $num_asiento_contable = ((int)$stmtAccNum->fetchColumn()) + 1;

            // B. Cuentas Predeterminadas (VB Logic)
            $cuentas = $pdo->query("SELECT deudores, retiva, retiibb, retgan FROM cuentaspredeterminadas WHERE id = 1")->fetch();
            $cuentas['deudores'] = resolverCuentaContable($pdo, null, $cuentas['deudores'], 'deudores por ventas');
            $cuentas['retiva'] = resolverCuentaContable($pdo, null, $cuentas['retiva'], 'retenciones de IVA');
            $cuentas['retgan'] = resolverCuentaContable($pdo, null, $cuentas['retgan'], 'retenciones de Ganancias');
            $cuentas['retiibb'] = resolverCuentaContable($pdo, null, $cuentas['retiibb'], 'retenciones de IIBB');
            $detalleAsiento = "Cobro a Clientes: " . $data['nombre_cliente'];

            // C. Grabar Asientos
            
            // C1. Medios de Pago (DEBE)
            foreach ($medios as $m) {
                $ctaMedio = resolverCuentaMedioPago($pdo, (int)$m['id'], 'el cobro');

                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $ctaMedio, $m['importe'], 0, $idPago, "pagos", $esFis, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }

            // C2. Retenciones (DEBE)
            if ($data['retenciones']['iva'] > 0) {
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentas['retiva'], $data['retenciones']['iva'], 0, $idPago, "pagos", $esFis, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }
            if ($data['retenciones']['ganancias'] > 0) {
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentas['retgan'], $data['retenciones']['ganancias'], 0, $idPago, "pagos", $esFis, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }
            if ($data['retenciones']['iibb'] > 0) {
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentas['retiibb'], $data['retenciones']['iibb'], 0, $idPago, "pagos", $esFis, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }

            // C3. Deudores por Venta (HABER)
            // Lógica VB: Contrapartida por el total del cobro (Pagos + Retenciones)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentas['deudores'], 0, $totalCobro, $idPago, "pagos", $esFis, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);

            $pdo->commit();
            echo json_encode(['success' => true, 'id_pago' => $idPago]);
            exit;

        case 'annul':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($data['confirmar_imputaciones'])) throw new Exception('Debe confirmar la anulación y sus imputaciones.');
            require_once 'anulacion_pagos_helpers.php';
            $pdo->beginTransaction();
            anularPago($pdo, 'cliente', (int)($data['id'] ?? 0), $data['annul_date'] ?? date('Y-m-d'), $_SESSION['usuario_nombre']);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Recibo e imputaciones anulados.']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
