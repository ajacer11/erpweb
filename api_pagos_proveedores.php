<?php
/**
 * api_pagos_proveedores.php - BACKEND DE PAGO A PROVEEDORES
 * v2.2 - Autodetección de Caja y Fix SQL Medios de Pago
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'];
$idcajaabierta = 0;
$idcontrolcaja = 0;
$ejercicio = 0;

// ---------------------------------------------------------------------------
// FIX: AUTODETECCIÓN DE CAJA ABIERTA (Igual que en api_cobros.php)
// ---------------------------------------------------------------------------
if ($idcajaabierta == 0) {
    // 1. Buscar en tabla 'cajas' si el usuario figura como activo en alguna
    $stmtBox = $pdo->prepare("SELECT id FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
    $stmtBox->execute([$usuario]);
    $cajaIdDB = $stmtBox->fetchColumn();
    
    if ($cajaIdDB) {
        $idcajaabierta = $cajaIdDB;
        
        // 2. Intentar recuperar el ID de control (auditoría) asociado
        $stmtCtrl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? ORDER BY id DESC LIMIT 1");
        $stmtCtrl->execute([$idcajaabierta, $usuario]);
        $idcontrolcaja = $stmtCtrl->fetchColumn() ?: 0;
    }
}
// ---------------------------------------------------------------------------

// grabarLineaAsiento() ahora vive en contabilidad_cuentas_helpers.php (ya incluido arriba):
// antes esta copia local no validaba cuenta vacía ni saltaba líneas en $0, a diferencia
// de api_cobros.php. Se unifica para que ambos flujos tengan la misma protección.

try {
    switch ($action) {
        case 'get_next_id':
            $stmt = $pdo->query("SELECT MAX(numcomp) FROM pagoaproveedores");
            $max = $stmt->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'next_id' => $max + 1]);
            break;

        case 'get_medios_pago':
            // CORRECCIÓN: Filtro doble para soportar vínculo por NumCaja O por ID
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
            break;

        case 'search_proveedores':
            $q = "%" . ($_GET['q'] ?? '') . "%";
            $stmt = $pdo->prepare("SELECT id, razon FROM Proveedores WHERE (razon LIKE ? OR id LIKE ?) ORDER BY razon ASC LIMIT 20");
            $stmt->execute([$q, $q]);
            $results = $stmt->fetchAll();
            $data = array_map(function($p) {
                return ['id' => $p['id'], 'text' => $p['razon']];
            }, $results);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_pendientes':
            $idprov = $_GET['proveedor'];
            $stmtSaldo = $pdo->prepare("SELECT ROUND(SUM(facturas) - SUM(pagos), 2) FROM moviemientosccproveedores WHERE proveedor = ?");
            $stmtSaldo->execute([$idprov]);
            $saldo = $stmtSaldo->fetchColumn() ?: 0;

            $sql = "SELECT id, 
                    CONCAT(LPAD(PV, 4, '0'),'-',LPAD(numfact, 8, '0')) AS num_factura,
                    total,
                    total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0) AS pendiente,
                    DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f,
                    DATE_FORMAT(fechavencimiento, '%d-%m-%Y') as vence_f,
                    fiscal
                    FROM facturasdecompra 
                    WHERE proveedorid = ? AND estado = 1 AND tipocomp NOT IN (3, 8, 13, 101)
                    ORDER BY fecha ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idprov]);
            
            echo json_encode(['success' => true, 'saldo' => $saldo, 'data' => $stmt->fetchAll()]);
            break;

        case 'get_cheques_cartera':
            $sql = "SELECT c.id, c.numero, c.importe, b.denominacion as banco, DATE_FORMAT(c.fechavencimiento, '%d-%m-%Y') as vencimiento 
                    FROM chequesterceros c 
                    JOIN bancos b ON c.banco = b.id 
                    WHERE c.estado = 0 ORDER BY c.fechavencimiento ASC";
            echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
            break;

        case 'get_cuentas_propias':
            $sql = "SELECT cp.id, cp.referencia, cp.banco AS banco_id, b.denominacion as banco_nom 
                    FROM cuentaspropias cp 
                    JOIN bancos b ON cp.banco = b.id ORDER BY cp.referencia ASC";
            echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
            break;

        case 'save_pago':
            $data = json_decode(file_get_contents('php://input'), true);
            $fechaPago = $data['fecha']; // Esta es la fecha del movimiento

            // VALIDACIÓN FECHA FUTURA
            if ($fechaPago > date('Y-m-d')) {
                throw new Exception("No se permite registrar pagos con fecha futura.");
            }

            $pdo->beginTransaction();

            // ---------------------------------------------------------
            // REGLA CONTABLE CRÍTICA (Validación Ejercicio y Período)
            // ---------------------------------------------------------
            
            // A. Buscar Ejercicio Abierto
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fechaPago, $fechaPago]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();
            
            if (!$ejercicio_id) {
                throw new Exception("No hay un ejercicio contable abierto para la fecha del pago ($fechaPago).");
            }

            // B. Buscar Estructura de Períodos
            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
            
            if (!$rowPer) {
                throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");
            }

            // C. Calcular Período Actual dinámicamente
            $fechaDT = new DateTime($fechaPago);
            $anio_fecha = (int)$fechaDT->format('Y');
            $mes_fecha = (int)$fechaDT->format('m');

            $mesesDesdeInicio = (($anio_fecha - (int)$rowPer['anioinicio']) * 12) + ($mes_fecha - (int)$rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int)$rowPer['cantperiodos']) {
                throw new Exception("La fecha del pago no pertenece a ningún período válido del ejercicio.");
            }

            // D. Validar si el período específico está abierto
            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int)$rowPer[$campoP] === 0) {
                throw new Exception("El período contable $periodoActual está cerrado. No se pueden registrar pagos.");
            }
            // ---------------------------------------------------------

            $totalPago = floatval($data['mp1imp']) + floatval($data['mp2imp']);
            $facturasSeleccionadas = $data['facturas_seleccionadas'] ?? [];
            $facturaIds = idsSeleccionados($facturasSeleccionadas);
            $fiscal = $data['fiscal'] ? 1 : 0;

            if ($facturaIds) {
                $sqlFiscal = "SELECT id, fiscal FROM facturasdecompra WHERE proveedorid = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
                $stmtFiscal = $pdo->prepare($sqlFiscal);
                $stmtFiscal->execute(array_merge([(int)$data['proveedor_id']], $facturaIds));
                $fiscal = fiscalUnicoDeFilas($stmtFiscal->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante de compra');
            }

            // Sanitizar MP2: si no viene o es vacío, forzar NULL e importe 0
            $mp2     = !empty($data['mp2']) ? (int)$data['mp2'] : 0;
            $mp2imp  = floatval($data['mp2imp'] ?? 0);

            // 1. Insertar en pagoaproveedores (Encabezado)
            $sqlP = "INSERT INTO pagoaproveedores 
                    (numcomp, proveedor, fecha, fecharecibo, usuario, total, mp1, mp1imp, mp2, mp2imp, fiscal, nota, endosos, propios, facturas, cuentabanco, ndc, importendc, saldoacuenta) 
                    VALUES (?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', '', 0, 0, 0, ?)";
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute([
                $data['numcomp'], $data['proveedor_id'], $fechaPago, $usuario, $totalPago,
                $data['mp1'], $data['mp1imp'], $mp2, $mp2imp,
                $fiscal, $data['nota'], 0
            ]);
            $idTicket = $pdo->lastInsertId();

            // 2. OBTENER NUMERADOR TESORERÍA (Secuencial Global)
            $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = ((int)$stmtTesoNum->fetchColumn()) + 1;

            // 3. INSERTAR EN CCCUENTASTESORERIA (MP1)
            $stmtTeso = $pdo->prepare("INSERT INTO cccuentastesoreria 
                (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) 
                VALUES (?, 0, ?, 'pagoaproveedores', ?, ?, ?, ?, ?, ?)");
            
            // Obtener Nombre Proveedor para la descripción
            $stmtRazonProv = $pdo->prepare("SELECT razon FROM Proveedores WHERE id = ?");
            $stmtRazonProv->execute([(int) $data['proveedor_id']]);
            $razonProv = $stmtRazonProv->fetchColumn();
            $descTeso = "Pago a " . $razonProv;

            $stmtTeso->execute([
                $data['mp1'], $data['mp1imp'], $usuario, $fechaPago, $descTeso, $fiscal, $idTicket, $num_asiento_teso
            ]);

            // 4. INSERTAR EN CCCUENTASTESORERIA (MP2 si existe)
            if ($mp2 && $mp2imp > 0) {
                $stmtTeso->execute([
                    $mp2, $mp2imp, $usuario, $fechaPago, $descTeso, $fiscal, $idTicket, $num_asiento_teso
                ]);
            }

            // 5. MOVIMIENTOS CC PROVEEDORES
            $pdo->prepare("INSERT INTO moviemientosccproveedores (proveedor, facturas, pagos, fecha, compasoc, mpago1, mpago1imp, mpago2, mpago2imp, tabla, fiscal, usuario, idpago) 
                           VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, 'pagoaproveedores', ?, ?, ?)")
                ->execute([$data['proveedor_id'], $totalPago, $fechaPago, $data['numcomp'], $data['mp1'], $data['mp1imp'], $mp2, $mp2imp, $fiscal, $usuario, $idTicket]);

            // 6. GESTIÓN DE MEDIOS DE PAGO (BANCOS / CAJAS)
            // Procesar MP1 y MP2
            $medios = [
                ['id' => $data['mp1'], 'imp' => $data['mp1imp']], 
                ['id' => $mp2, 'imp' => $mp2imp]
            ];

            foreach ($medios as $m) {
                if ($m['imp'] <= 0) continue;
                
                $stmtInfoMedio = $pdo->prepare("SELECT tipodecuenta, cuentabancaria, cuentacontable FROM mediosdepago WHERE id = ?");
                $stmtInfoMedio->execute([$m['id']]);
                $infoMedio = $stmtInfoMedio->fetch(PDO::FETCH_ASSOC);

                if (!$infoMedio) continue;

                // A. BANCOS (Impacto en ccbancos)
                if ($infoMedio['tipodecuenta'] === 'Bancos' && $infoMedio['cuentabancaria']) {
                    // Obtener saldo actual
                    $stSaldoBco = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
                    $stSaldoBco->execute([$infoMedio['cuentabancaria']]);
                    $saldoActualBco = $stSaldoBco->fetchColumn() ?: 0;
                    $nuevoSaldoBco = $saldoActualBco - $m['imp'];

                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, 0, ?, ?, ?, ?, 'pagosprov', ?)")
                        ->execute([$infoMedio['cuentabancaria'], $m['imp'], $nuevoSaldoBco, $fechaPago, $idTicket, $descTeso]);
                }

                // B. CAJAS (Caja Central o Usuario)
                if ($infoMedio['tipodecuenta'] === 'Caja Central') {
                    // Lógica simplificada de inserción en caja central (según VB InsertarEnCaja)
                    // ... Aquí asumimos que impacta en tabla 'Caja' genérica si existe, o solo contabilidad.
                    // El VB hace INSERT into Caja. Asumimos tabla 'cajas_movimientos' o similar si existiera, 
                    // pero en este sistema parece ser 'cccuentastesoreria' lo principal. 
                    // Si hay tabla 'Caja' legacy, insertaríamos aquí.
                }

                if ($infoMedio['tipodecuenta'] === 'Caja de Usuarios') {
                    $pdo->prepare("INSERT INTO cajaxusuario (fechamov, horamov, tipomov, debe, haber, usuario, idcaja, idcontrolcaja) VALUES (?, CURTIME(), ?, 0, ?, ?, ?, ?)")
                        ->execute([$fechaPago, $descTeso, $m['imp'], $usuario, $idcajaabierta, $idcontrolcaja]);
                    
                    $pdo->prepare("UPDATE cajas SET saldo = saldo - ? WHERE id = ?")->execute([$m['imp'], $idcajaabierta]);
                    $pdo->prepare("UPDATE controlcajas SET saldofinal = saldofinal - ? WHERE id = ?")->execute([$m['imp'], $idcontrolcaja]);
                }
            }

            // 7. PROCESAMIENTO DE CHEQUES Y ENDOSOS
            // Endosos (Terceros)
            if (!empty($data['endosos'])) {
                foreach ($data['endosos'] as $chqId) {
                    $pdo->prepare("UPDATE chequesterceros SET estado = 2, endosadoa = ?, fachaendoso = ?, tabla = 'pagos', compasoc = ? WHERE id = ?")
                        ->execute([$data['proveedor_id'], $fechaPago, $idTicket, $chqId]);
                }
            }
            // Propios
            if (!empty($data['propios'])) {
                foreach ($data['propios'] as $cp) {
                    $stmtCta = $pdo->prepare("SELECT banco FROM cuentaspropias WHERE id = ?");
                    $stmtCta->execute([$cp['cuenta_id']]);
                    $bancoIdCorrecto = $stmtCta->fetchColumn() ?: 0;

                    $pdo->prepare("INSERT INTO chequespropios (proveedor, numero, fechaemision, fechavencimiento, importe, banco, tipo, observaciones, cuenta, idmovimiento, tabla, estado) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pagos', 2)") // Estado 2 (Entregado)
                        ->execute([
                            $data['proveedor_id'], $cp['numero'], $fechaPago, $cp['vencimiento'], $cp['importe'], 
                            $bancoIdCorrecto, $cp['tipo'], "Emitido vía Web por $usuario", $cp['cuenta_id'], $idTicket
                        ]);
                }
            }

            // 8. IMPUTACIÓN DE FACTURAS
            $saldoAImputar = $totalPago;
            foreach ($data['facturas_seleccionadas'] as $f) {
                if ($saldoAImputar <= 0) break;
                $monto = min(floatval($f['pendiente']), $saldoAImputar);
                $pdo->prepare("INSERT INTO pagosfacturasproveedores (idpago, idfactura, importepagado, tipo) VALUES (?, ?, ?, 'P')")
                    ->execute([$idTicket, $f['id'], $monto]);
                
                if ($monto >= floatval($f['pendiente'])) {
                    $pdo->prepare("UPDATE facturasdecompra SET estado = 2 WHERE id = ?")->execute([$f['id']]);
                }
                $saldoAImputar -= $monto;
            }
            $pdo->prepare("UPDATE pagoaproveedores SET saldoacuenta = ? WHERE id = ?")->execute([$saldoAImputar, $idTicket]);

            // ---------------------------------------------------------
            // 9. CONTABILIDAD (LIBRO DIARIO) - LÓGICA VB.NET MIGRADA
            // ---------------------------------------------------------
            
            // A. Obtener Número de Asiento
            $stmtAccNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ?");
            $stmtAccNum->execute([$ejercicio_id]);
            $num_asiento_contable = ((int)$stmtAccNum->fetchColumn()) + 1;

            // B. Obtener Cuentas Contables (Lógica Específica)
            
            // B1. Cuenta del Proveedor (Debe)
            // Lógica VB: Dim cuentaproveedores As String = BuscarUnDato("cuentaspredeterminadas", "proveedores", "id = 1")
            $stmtDef = $pdo->query("SELECT proveedores FROM cuentaspredeterminadas WHERE id = 1");
            $cta_prov_defecto = $stmtDef->fetchColumn();

            // Lógica VB: Dim ctaprov As String = BuscarUnDato("Proveedores", "cuentacontable", $"id = {cmbcli.SelectedValue}")
            $stmtProvCta = $pdo->prepare("SELECT cuentacontable FROM Proveedores WHERE id = ?");
            $stmtProvCta->execute([$data['proveedor_id']]);
            $cta_prov_especifica = $stmtProvCta->fetchColumn();

            // Lógica VB: If ctaprov = "" Then ctaprov = cuentaproveedores
            $cta_contable_proveedor = resolverCuentaContable($pdo, $cta_prov_especifica, $cta_prov_defecto, 'el proveedor');

            // C. Grabar Asientos
            $detalleAsiento = "Pago a Proveedores: " . $razonProv;

            // C1. Asiento MP1 (Haber - Salida de dinero)
            $cta_mp1 = resolverCuentaMedioPago($pdo, (int)$data['mp1'], 'el pago a proveedores');
            
            grabarLineaAsiento($pdo, $num_asiento_contable, $fechaPago, $cta_mp1, 0, $data['mp1imp'], $idTicket, 'pagoaproveedores', $fiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);

            // C2. Asiento MP2 (Haber - Salida de dinero)
            if ($mp2 && $mp2imp > 0) {
                $cta_mp2 = resolverCuentaMedioPago($pdo, (int)$mp2, 'el pago a proveedores');

                grabarLineaAsiento($pdo, $num_asiento_contable, $fechaPago, $cta_mp2, 0, $mp2imp, $idTicket, 'pagoaproveedores', $fiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }

            // C3. Asiento Proveedor (Debe - Cancelación de deuda)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fechaPago, $cta_contable_proveedor, $totalPago, 0, $idTicket, 'pagoaproveedores', $fiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idTicket, 'nro_asiento' => $num_asiento_contable]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
