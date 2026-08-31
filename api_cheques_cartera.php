<?php
/**
 * api_cheques_cartera.php - ENDPOINT PARA GESTIÓN DE CHEQUES DE TERCEROS
 */

require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

try {
    switch ($action) {
        case 'list':
        case 'export_excel':
            $data = ($action === 'list') ? json_decode(file_get_contents('php://input'), true) : $_GET;
            
            $desde = $data['desde'];
            $hasta = $data['hasta'];
            $estado = $data['estado']; // 0, 1, 2, 3, 1000 o 'todos'
            $cliente = $data['cliente'] ?? 'todos';

            $where = " WHERE c.fechavencimiento BETWEEN ? AND ? ";
            $params = [$desde, $hasta];

            if ($estado !== 'todos') {
                $where .= " AND c.estado = ? ";
                $params[] = $estado;
            }
            if ($cliente !== 'todos') {
                $where .= " AND c.cliente = ? ";
                $params[] = $cliente;
            }

            $sql = "SELECT 
                        c.id, 
                        c.numero AS 'Num Cheque',
                        DATE_FORMAT(c.fechavencimiento, '%d-%m-%Y') AS 'Vencimiento',
                        (SELECT razon FROM alb_clientes WHERE id = c.cliente) AS 'Cliente',
                        c.importe AS 'Importe',
                        (SELECT denominacion FROM bancos WHERE id = c.banco) AS 'Banco',
                        c.tipo AS 'Tipo',
                        CASE c.estado 
                            WHEN 0 THEN 'En Cartera' 
                            WHEN 1 THEN 'Depositado/Cobrado' 
                            WHEN 2 THEN 'Endosado' 
                            WHEN 3 THEN 'Rechazado' 
                            WHEN 1000 THEN 'ANULADO' 
                        END AS 'Estado Actual',
                        (SELECT razon FROM Proveedores WHERE id = c.endosadoa) AS 'Endosado A',
                        c.observaciones AS 'Observaciones',
                        c.estado as 'status_code'
                    FROM chequesterceros c 
                    $where 
                    ORDER BY c.fechavencimiento ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();

            if ($action === 'export_excel') {
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename=cartera_cheques_' . date('Ymd') . '.xls');
                echo "\xEF\xBB\xBF<table border='1'><thead><tr>";
                foreach (array_keys($res[0] ?? []) as $col) echo "<th>$col</th>";
                echo "</tr></thead><tbody>";
                foreach ($res as $row) {
                    echo "<tr>";
                    foreach ($row as $v) echo "<td>$v</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                exit;
            }

            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'update_obs':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE chequesterceros SET observaciones = ? WHERE id = ?");
            $stmt->execute([$data['obs'], $data['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'reject':
            // Solo depositados (1) o endosados (2) pueden rechazarse según VB.NET.
            // Antes esta acción solo cambiaba el estado del cheque; no revertía el
            // depósito bancario ni la deuda del proveedor endosado, y no generaba
            // ningún asiento. Se contabiliza contra una cuenta puente dedicada
            // "Cheques Rechazados a Cobrar" (cuentaspredeterminadas.chequesrechazados),
            // separada de Deudores por Ventas, para poder hacerles seguimiento aparte.
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? 0;
            if (!$id) throw new Exception("ID de cheque inválido.");

            $pdo->beginTransaction();

            $stmtCheque = $pdo->prepare("SELECT numero, importe, estado, cuentadeposito, endosadoa FROM chequesterceros WHERE id = ?");
            $stmtCheque->execute([$id]);
            $cheque = $stmtCheque->fetch(PDO::FETCH_ASSOC);
            if (!$cheque) throw new Exception("Cheque no encontrado.");
            if (!in_array((int)$cheque['estado'], [1, 2], true)) {
                throw new Exception("Solo se pueden rechazar cheques depositados o endosados.");
            }

            $importe = round((float)$cheque['importe'], 2);
            $detalle = "Rechazo Chq Nº " . $cheque['numero'];
            $fecha = date('Y-m-d');

            $ctaRechazadosDefecto = $pdo->query("SELECT chequesrechazados FROM cuentaspredeterminadas WHERE id = 1")->fetchColumn();
            $ctaRechazados = resolverCuentaContable($pdo, null, $ctaRechazadosDefecto, 'el rechazo de un cheque');

            $ejerPer = resolverEjercicioPeriodo($pdo, $fecha);
            $numAsiento = siguienteNumeroAsiento($pdo, $ejerPer['ejercicio']);

            if ((int)$cheque['estado'] === 1) {
                // Estaba depositado en un banco propio: se revierte el depósito.
                if (!$cheque['cuentadeposito']) {
                    throw new Exception("El cheque no tiene registrada la cuenta bancaria del depósito.");
                }

                $stmtMedioBanco = $pdo->prepare("SELECT id FROM mediosdepago WHERE tipodecuenta = 'Bancos' AND cuentabancaria = ? AND habilitado = 1 LIMIT 1");
                $stmtMedioBanco->execute([$cheque['cuentadeposito']]);
                $idMedioBanco = $stmtMedioBanco->fetchColumn();
                if (!$idMedioBanco) {
                    throw new Exception("La cuenta bancaria del depósito no tiene un medio de pago de Tesorería vinculado.");
                }
                $ctaBanco = resolverCuentaMedioPago($pdo, (int)$idMedioBanco, 'el rechazo de un cheque depositado');

                grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaRechazados, $importe, 0, $id, 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);
                grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaBanco, 0, $importe, $id, 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);

                // Revertir el movimiento bancario (subdiario) que había generado el depósito
                $stmtS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
                $stmtS->execute([$cheque['cuentadeposito']]);
                $saldoAnt = $stmtS->fetchColumn() ?: 0;
                $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, 0, ?, ?, CURDATE(), ?, 'rechazo_cheque', ?)")
                    ->execute([$cheque['cuentadeposito'], $importe, $saldoAnt - $importe, $id, $detalle]);
            } else {
                // Estaba endosado a un proveedor: se reinstala la deuda con ese proveedor,
                // porque pagarle con un cheque que rebotó no canceló nada en la realidad.
                if (!$cheque['endosadoa']) {
                    throw new Exception("El cheque no tiene registrado el proveedor al que fue endosado.");
                }

                $stmtProvCta = $pdo->prepare("SELECT cuentacontable FROM Proveedores WHERE id = ?");
                $stmtProvCta->execute([$cheque['endosadoa']]);
                $ctaProvDefecto = $pdo->query("SELECT proveedores FROM cuentaspredeterminadas WHERE id = 1")->fetchColumn();
                $ctaProveedor = resolverCuentaContable($pdo, $stmtProvCta->fetchColumn(), $ctaProvDefecto, 'el proveedor endosado');

                grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaRechazados, $importe, 0, $id, 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);
                grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaProveedor, 0, $importe, $id, 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);

                // Reinstalar la deuda en la cuenta corriente del proveedor (mismo criterio
                // que api_pagos_proveedores.php: SUM(facturas) - SUM(pagos)).
                $pdo->prepare("INSERT INTO moviemientosccproveedores (proveedor, facturas, pagos, fecha, compasoc, tabla, fiscal, usuario, idpago) VALUES (?, 0, ?, ?, ?, 'chequesterceros', 1, ?, 0)")
                    ->execute([$cheque['endosadoa'], -$importe, $fecha, $id, $usuario]);
            }

            $pdo->prepare("UPDATE chequesterceros SET estado = 3 WHERE id = ?")->execute([$id]);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'remove_endorsement':
            $data = json_decode(file_get_contents('php://input'), true);
            $stmt = $pdo->prepare("UPDATE chequesterceros SET estado = 0, endosadoa = 0, fachaendoso = '0000-00-00' WHERE id = ?");
            $stmt->execute([$data['id']]);
            echo json_encode(['success' => true]);
            break;

        case 'deposit':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();

            $importe = round((float)$data['importe'], 2);
            $detalle = "Depósito Chq Nº " . $data['numero'];

            // 1. Update cheque
            $pdo->prepare("UPDATE chequesterceros SET estado = 1, cuentadeposito = ? WHERE id = ?")
                ->execute([$data['cuenta_id'], $data['id']]);

            // 2. Movimiento bancario (subdiario)
            $stmtS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
            $stmtS->execute([$data['cuenta_id']]);
            $saldoAnt = $stmtS->fetchColumn() ?: 0;

            $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, 0, ?, CURDATE(), 0, 'depositos', ?)")
                ->execute([$data['cuenta_id'], $importe, $saldoAnt + $importe, $detalle]);

            // 3. Asiento contable: Banco (debe) / Cartera de Cheques (haber). Antes el
            // depósito solo movía el subdiario bancario y nunca tocaba el libro diario,
            // por lo que la cuenta "Cartera de Cheques" nunca se descargaba contablemente.
            $fecha = date('Y-m-d');
            $ejerPer = resolverEjercicioPeriodo($pdo, $fecha);
            $numAsiento = siguienteNumeroAsiento($pdo, $ejerPer['ejercicio']);

            $stmtMedioBanco = $pdo->prepare("SELECT id FROM mediosdepago WHERE tipodecuenta = 'Bancos' AND cuentabancaria = ? AND habilitado = 1 LIMIT 1");
            $stmtMedioBanco->execute([$data['cuenta_id']]);
            $idMedioBanco = $stmtMedioBanco->fetchColumn();
            if (!$idMedioBanco) {
                throw new Exception("La cuenta bancaria seleccionada no tiene un medio de pago de Tesorería vinculado (Tesorería > Cuentas > vincular a Cuenta Bancaria Propia).");
            }
            $ctaBanco = resolverCuentaMedioPago($pdo, (int)$idMedioBanco, 'el depósito de un cheque');
            $ctaCartera = resolverCuentaMedioPagoPorTipo($pdo, 'Cartera de Cheques', 'el depósito de un cheque (cartera)');

            grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaBanco, $importe, 0, $data['id'], 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);
            grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaCartera, 0, $importe, $data['id'], 'chequesterceros', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}