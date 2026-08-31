<?php
/**
 * api_vendedores_pagos.php - LÓGICA DE PAGO A VENDEDORES (MIGRACIÓN VB.NET)
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$idcajaabierta = 0;
$idcontrolcaja = 0;
$ejercicio = 0;

// ---------------------------------------------------------------------------
// FIX: AUTODETECCIÓN DE CAJA ABIERTA (Igual que en api_cobros.php / api_pagos_proveedores.php).
// Antes se leía directo de $_SESSION['idcajaabierta'], que normalmente no está seteado
// en este flujo: un pago en efectivo terminaba con idcaja=0 y no descontaba ninguna caja real.
// ---------------------------------------------------------------------------
if ($idcajaabierta == 0) {
    $stmtBox = $pdo->prepare("SELECT id FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
    $stmtBox->execute([$usuario]);
    $cajaIdDB = $stmtBox->fetchColumn();

    if ($cajaIdDB) {
        $idcajaabierta = $cajaIdDB;

        $stmtCtrl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? ORDER BY id DESC LIMIT 1");
        $stmtCtrl->execute([$idcajaabierta, $usuario]);
        $idcontrolcaja = $stmtCtrl->fetchColumn() ?: 0;
    }
}
// ---------------------------------------------------------------------------

// Variables de entorno/auditoría (Mapeo de VB.NET)
$iplocal = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$ippublica = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$userpc = gethostname() ?: 'WEB_CLIENT';

try {
    switch ($action) {
        case 'get_next_id':
            $stmt = $pdo->query("SELECT MAX(numcomp) FROM pagosvende");
            $max = $stmt->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'next_id' => $max + 1]);
            break;

        case 'get_saldo':
            $id = (int)$_GET['vendedor_id'];
            $stmt = $pdo->prepare("SELECT ROUND(SUM(ganancias) - SUM(pagos), 2) FROM ccvendedores WHERE idvende = ?");
            $stmt->execute([$id]);
            $saldo = $stmt->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'saldo' => $saldo]);
            break;

        case 'save_pago':
            $data = json_decode(file_get_contents('php://input'), true);
            $fechaPago = $data['fecha']; // YYYY-MM-DD

            // VALIDACIÓN FECHA FUTURA (mismo criterio que api_cobros.php / api_pagos_proveedores.php)
            if ($fechaPago > date('Y-m-d')) {
                throw new Exception("No se permite registrar pagos con fecha futura.");
            }

            // VALIDACIÓN CRÍTICA DE EJERCICIO Y PERÍODO (mismo criterio que cobros/compras/tesorería)
            $ejerPer = resolverEjercicioPeriodo($pdo, $fechaPago);
            $ejercicio = $ejerPer['ejercicio'];
            $periodo = $ejerPer['periodo'];

            $pdo->beginTransaction();

            $totalPago = floatval($data['mp1imp']) + floatval($data['mp2imp']);
            $fiscal = $data['fiscal'] ? 1 : 0;
            $vendedor_id = (int)$data['vendedor_id'];

            // 1. Insertar en pagosvende (Tablas según VB.NET)
            $sqlP = "INSERT INTO pagosvende 
                    (numcomp, vendedor, fecha, fecharecibo, usuario, total, mp1, mp1imp, mp2, mp2imp, fiscal, nota, endosos, propios, facturas, cuentabanco, ndc, importendc, saldoacuenta) 
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', ?, 0, 0, 0)";
            
            $cuentabco = 0;
            // Si el medio 1 es Bancos, obtenemos la cuenta bancaria asociada
            if (!empty($data['mp1'])) {
                $stmtM1 = $pdo->prepare("SELECT tipodecuenta, CuentaBancaria FROM mediosdepago WHERE id = ?");
                $stmtM1->execute([$data['mp1']]);
                $m1Info = $stmtM1->fetch();
                if ($m1Info && $m1Info['tipodecuenta'] === 'Bancos') $cuentabco = $m1Info['CuentaBancaria'];
            }

            // Nota: el frontend (vendedores_pagar_comisiones.php) manda los cheques propios
            // nuevos como "propios_data" (objetos completos, todavía sin crear), no como
            // "propios" (ids de cheques ya existentes). Antes esta acción leía "propios" y
            // se quedaba vacía siempre: el cheque propio nunca se creaba, aunque el importe
            // ya estaba contabilizado como si se hubiera entregado.
            $propiosData = $data['propios_data'] ?? [];

            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute([
                $data['numcomp'], $vendedor_id, $fechaPago, $usuario, $totalPago,
                $data['mp1'] ?: 0, $data['mp1imp'] ?: 0, $data['mp2'] ?: 0, $data['mp2imp'] ?: 0,
                $fiscal, $data['nota'] ?: '',
                !empty($data['endosos']) ? implode(',', $data['endosos']) : '0',
                !empty($propiosData) ? implode(',', array_column($propiosData, 'numero')) : '0',
                $cuentabco
            ]);
            $idTicket = $pdo->lastInsertId();

            // 2. Procesar Endosos (cheques de terceros entregados al vendedor)
            // Antes solo se actualizaba tabla/compasoc sin cambiar el estado ni "endosadoa":
            // el cheque quedaba con estado "en cartera" y se podía volver a usar en otro
            // cobro/pago (doble uso del mismo cheque).
            if (!empty($data['endosos'])) {
                $stmtEndoso = $pdo->prepare("UPDATE chequesterceros SET estado = 2, endosadoa = ?, fachaendoso = ?, tabla = 'pagosvende', compasoc = ? WHERE id = ?");
                foreach ($data['endosos'] as $chqId) {
                    $stmtEndoso->execute([$vendedor_id, $fechaPago, $idTicket, $chqId]);
                }
            }

            // 3. Cheques propios nuevos (entregados al vendedor)
            if (!empty($propiosData)) {
                foreach ($propiosData as $cp) {
                    $stmtCta = $pdo->prepare("SELECT banco FROM cuentaspropias WHERE id = ?");
                    $stmtCta->execute([$cp['cuenta']]);
                    $bancoIdCorrecto = $stmtCta->fetchColumn() ?: 0;

                    $pdo->prepare("INSERT INTO chequespropios (proveedor, numero, fechaemision, fechavencimiento, importe, banco, tipo, observaciones, cuenta, idmovimiento, tabla, estado)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pagosvende', 2)")
                        ->execute([
                            $vendedor_id, $cp['numero'], $cp['fechaemision'], $cp['fechavencimiento'], $cp['importe'],
                            $bancoIdCorrecto, $cp['tipo'], "Emitido vía Web por $usuario", $cp['cuenta'], $idTicket
                        ]);
                }
            }

            // 3. Tesorería (cccuentastesoreria)
            $stmtMaxAs = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $numAsTeso = ($stmtMaxAs->fetchColumn() ?: 0) + 1;

            $medios = [
                ['id' => $data['mp1'], 'imp' => $data['mp1imp']], 
                ['id' => $data['mp2'], 'imp' => $data['mp2imp']]
            ];

            foreach ($medios as $m) {
                if (floatval($m['imp']) <= 0 || !$m['id']) continue;
                
                $stmtM = $pdo->prepare("SELECT tipodecuenta, CuentaBancaria, cuentacontable FROM mediosdepago WHERE id = ?");
                $stmtM->execute([$m['id']]);
                $mInfo = $stmtM->fetch();

                // Movimiento Tesorería (Haber)
                $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) 
                               VALUES (?, 0, ?, 'pagosvende', ?, ?, ?, ?, ?, ?)")
                    ->execute([$m['id'], $m['imp'], $usuario, $fechaPago, "Pago a " . $data['vendedor_nombre'], $fiscal, $idTicket, $numAsTeso]);

                // Impacto en Caja de Usuarios
                if ($mInfo['tipodecuenta'] === 'Caja de Usuarios') {
                    $pdo->prepare("INSERT INTO cajaxusuario (fechamov, horamov, tipomov, debe, haber, usuario, idcaja, idcontrolcaja, iplocal, ippublica, usuariopc) 
                                   VALUES (?, CURTIME(), ?, 0, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$fechaPago, "Pago a " . $data['vendedor_nombre'], $m['imp'], $usuario, $idcajaabierta, $idcontrolcaja, $iplocal, $ippublica, $userpc]);
                    
                    $pdo->prepare("UPDATE cajas SET saldo = saldo - ? WHERE id = ?")->execute([$m['imp'], $idcajaabierta]);
                    $pdo->prepare("UPDATE controlcajas SET cantidadmovimientos = cantidadmovimientos + 1, saldofinal = saldofinal - ? WHERE id = ?")
                        ->execute([$m['imp'], $idcontrolcaja]);
                }

                // Impacto en Caja Central
                if ($mInfo['tipodecuenta'] === 'Caja Central') {
                    $stmtS = $pdo->query("SELECT saldo FROM Caja ORDER BY id DESC LIMIT 1");
                    $saldoCaja = $stmtS->fetchColumn() ?: 0;
                    $pdo->prepare("INSERT INTO Caja (fechamov, horamov, tipomov, debe, haber, saldo, usuario) VALUES (CURDATE(), CURTIME(), ?, 0, ?, ?, ?)")
                        ->execute(["Pago a " . $data['vendedor_nombre'], $m['imp'], $saldoCaja - $m['imp'], $usuario]);
                }

                // Impacto en Bancos
                if ($mInfo['tipodecuenta'] === 'Bancos') {
                    $stmtS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
                    $stmtS->execute([$mInfo['CuentaBancaria']]);
                    $saldoBco = $stmtS->fetchColumn() ?: 0;
                    
                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, 0, ?, ?, ?, ?, 'pagosvende', ?)")
                        ->execute([$mInfo['CuentaBancaria'], $m['imp'], $saldoBco - $m['imp'], $fechaPago, $idTicket, "Pago a " . $data['vendedor_nombre']]);
                }
            }

            // 4. Cuenta Corriente Vendedor (ccvendedores)
            $pdo->prepare("INSERT INTO ccvendedores (idvende, ganancias, pagos, fecha, compasoc, tabla, fiscal, usuario) 
                           VALUES (?, 0, ?, ?, ?, 'pagosvende', ?, ?)")
                ->execute([$vendedor_id, $totalPago, $fechaPago, $data['numcomp'], $fiscal, $usuario]);

            // 5. Libro Diario (Asiento Contable)
            $numAsiento = siguienteNumeroAsiento($pdo, $ejercicio);

            $cuentasPredet = $pdo->query("SELECT comisionesvend FROM cuentaspredeterminadas WHERE id = 1")->fetch();
            $detalleAsiento = "Pago a Vendedores: " . $data['vendedor_nombre'];

            // DEBE: Cuenta de Pasivo por comisiones a vendedores. Antes se usaba la cuenta
            // de "proveedores" por no existir una cuenta global propia (ver
            // cuentaspredeterminadas.comisionesvend / sql/2026-08-29_comisiones_vendedores_cuenta.sql),
            // lo que mezclaba dos submayores distintos en una misma cuenta contable.
            $sqlLD = "INSERT INTO librodiario (numasiento, fecha, cuenta, debe, haber, compasoc, tabla, fiscal, usuario, detalle, idejercicio, periodo) VALUES (?, ?, ?, ?, ?, ?, 'pagosvende', ?, ?, ?, ?, ?)";
            $stmtLD = $pdo->prepare($sqlLD);

            $cuentaVendedores = resolverCuentaContable($pdo, null, $cuentasPredet['comisionesvend'], 'comisiones a vendedores');
            $stmtLD->execute([$numAsiento, $fechaPago, $cuentaVendedores, $totalPago, 0, $idTicket, $fiscal, $usuario, $detalleAsiento, $ejercicio, $periodo]);

            // HABER: Cuentas de los medios de pago
            $stmtCtaMedio = $pdo->prepare("SELECT cuentacontable FROM mediosdepago WHERE id = ?");
            foreach ($medios as $m) {
                if (floatval($m['imp']) <= 0 || !$m['id']) continue;
                $ctaContable = resolverCuentaMedioPago($pdo, (int)$m['id'], 'el pago a vendedores');
                $stmtLD->execute([$numAsiento, $fechaPago, $ctaContable, 0, $m['imp'], $idTicket, $fiscal, $usuario, $detalleAsiento, $ejercicio, $periodo]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idTicket]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
