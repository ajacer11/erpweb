<?php
/**
 * api_tesoreria_movimientos.php - MOTOR TRANSACCIONAL DE TESORERÍA Y CONTABILIDAD
 * v2.0 (Validación Estricta de Ejercicio y Período)
 */

require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'WEB_USER';

try {
    switch ($action) {
        case 'check_caja':
            $caja_id = $_GET['caja_id'] ?? 0;
            $stmt = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND cerradael IS NULL AND usuario = ? LIMIT 1");
            $stmt->execute([$caja_id, $usuario]);
            $control = $stmt->fetch();
            if(!$control) throw new Exception("Usted no tiene abierta la caja Nº $caja_id en este equipo.");
            echo json_encode(['success' => true, 'id_control' => $control['id']]);
            break;

        case 'get_cartera_disponible':
            // Obtener cheques en cartera (estado 1) para poder endosarlos
            $sql = "SELECT c.*, b.denominacion as banco_nombre, cli.razon as cliente_nom 
                    FROM chequesterceros c
                    LEFT JOIN bancos b ON b.id = c.banco
                    LEFT JOIN alb_clientes cli ON cli.id = c.cliente
                    WHERE c.estado = 1 ORDER BY c.fechavencimiento ASC";
            echo json_encode($pdo->query($sql)->fetchAll());
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);

            $fecha = $input['fecha'];
            $obs = $input['obs'];
            $rows = $input['rows'] ?? [];

            // 1. VALIDACIÓN DE FECHA FUTURA (BACK-END)
            if ($fecha > date('Y-m-d')) {
                throw new Exception("No se permiten movimientos con fechas futuras.");
            }

            // 1B. VALIDACIÓN DE BALANCE Y RESOLUCIÓN DE CUENTAS (server-side). La cuenta
            // contable de cada línea se resuelve acá a partir del medio de pago (idcuenta);
            // antes se insertaba tal cual la "cuentacontable" que mandaba el navegador,
            // lo que permitía postear en cualquier cuenta (o en ninguna, si faltaba).
            if (empty($rows)) {
                throw new Exception("No hay líneas para registrar.");
            }
            $sumaDebe = 0;
            $sumaHaber = 0;
            $lineasAsiento = [];
            foreach ($rows as $row) {
                $rowDebe = round((float) ($row['debe'] ?? 0), 2);
                $rowHaber = round((float) ($row['haber'] ?? 0), 2);
                if ($rowDebe == 0 && $rowHaber == 0) {
                    continue; // línea vacía del grid, no se postea
                }
                if (empty($row['idcuenta'])) {
                    throw new Exception("Falta la cuenta de tesorería en una de las líneas del asiento.");
                }
                $cuentaResuelta = resolverCuentaMedioPago($pdo, (int) $row['idcuenta'], 'un movimiento de tesorería');

                $sumaDebe += $rowDebe;
                $sumaHaber += $rowHaber;
                $lineasAsiento[] = [
                    'idcuenta'       => (int) $row['idcuenta'],
                    'debe'           => $rowDebe,
                    'haber'          => $rowHaber,
                    'cuentacontable' => $cuentaResuelta,
                    'tipo'           => $row['tipo'] ?? null,
                    'puestoasociado' => $row['puestoasociado'] ?? null,
                    'cuentabancaria' => $row['cuentabancaria'] ?? null,
                ];
            }
            if (empty($lineasAsiento)) {
                throw new Exception("No hay líneas con importe para registrar.");
            }
            if (round($sumaDebe - $sumaHaber, 2) != 0) {
                throw new Exception("El asiento no está balanceado: debe \$" . number_format($sumaDebe, 2) . " vs. haber \$" . number_format($sumaHaber, 2) . ".");
            }

            $pdo->beginTransaction();

            // 2. VALIDACIÓN CRÍTICA DE EJERCICIO Y PERÍODO
            $ejerPer = resolverEjercicioPeriodo($pdo, $fecha);
            $ejercicio_id = $ejerPer['ejercicio'];
            $periodoActual = $ejerPer['periodo'];

            // 3. NÚMERO DE ASIENTO PARA LIBRO DIARIO (Secuencia Contable)
            $num_asiento_diario = siguienteNumeroAsiento($pdo, $ejercicio_id);

            // 4. NÚMERO DE ASIENTO PARA TESORERÍA (Secuencia Operativa)
            $stmtMaxTeso = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = (int)$stmtMaxTeso->fetchColumn() + 1;

            // 5. PREPARAR SENTENCIAS
            $stmtTeso = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, descripcion, numerodeasiento, fiscal, tabla, usuario) VALUES (?,?,?,?,?,?,?,'cccuentastesoreria',?)");
            $stmtDiario = $pdo->prepare("INSERT INTO librodiario (numasiento, fecha, cuenta, debe, haber, idejercicio, detalle, compasoc, tabla, fiscal, periodo, usuario) VALUES (?,?,?,?,?,?,?,?,'cccuentastesoreria',?,?,?)");

            foreach ($lineasAsiento as $row) {
                // Registro Tesorería
                $stmtTeso->execute([
                    $row['idcuenta'], $fecha, $row['debe'], $row['haber'], $obs, $num_asiento_teso, 1, $usuario
                ]);

                // Registro Contable (cuenta resuelta server-side; nunca la del navegador)
                $stmtDiario->execute([
                    $num_asiento_diario,
                    $fecha,
                    $row['cuentacontable'],
                    $row['debe'],
                    $row['haber'],
                    $ejercicio_id,
                    $obs,
                    $num_asiento_teso,
                    1,
                    $periodoActual,
                    $usuario
                ]);

                // 6. SI ES CAJA DE USUARIOS -> Auditoría y Saldo
                if($row['tipo'] === 'Caja de Usuarios') {
                    // La columna es "cerradael", no "fechacierre" (no existe en controlcajas) —
                    // como estaba, esta consulta tiraba "Unknown column 'fechacierre'" en CADA
                    // movimiento de tesorería sobre una Caja de Usuarios.
                    $stCtl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND cerradael IS NULL LIMIT 1");
                    $stCtl->execute([$row['puestoasociado']]);
                    $id_ctl = $stCtl->fetchColumn();

                    $sqlCaja = "INSERT INTO cajaxusuario (usuario, debe, haber, idcaja, idcontrolcaja, fechamov, tipomov, horamov) VALUES (?,?,?,?,?,?,'Movimiento Tesoreria',?)";
                    $pdo->prepare($sqlCaja)->execute([$usuario, $row['debe'], $row['haber'], $row['puestoasociado'], $id_ctl, $fecha, date('H:i:s')]);

                    if($id_ctl) {
                        $pdo->prepare("UPDATE controlcajas SET saldofinal = saldofinal + ? - ? WHERE id = ?")->execute([$row['debe'], $row['haber'], $id_ctl]);
                    }
                    $pdo->prepare("UPDATE cajas SET saldo = saldo + ? - ? WHERE id = ?")->execute([$row['debe'], $row['haber'], $row['puestoasociado']]);
                }

                // 7. SI ES BANCOS -> Impacto en ccbancos
                if($row['tipo'] === 'Bancos' && !empty($row['cuentabancaria']) && $row['cuentabancaria'] > 0) {
                    // El saldo corrido se calcula igual que en el resto de los movimientos
                    // bancarios (antes quedaba en 0 y cortaba la cadena de saldos).
                    $stSaldoBan = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                    $stSaldoBan->execute([$row['cuentabancaria']]);
                    $saldoBan = round((float) ($stSaldoBan->fetchColumn() ?: 0) + (float) $row['debe'] - (float) $row['haber'], 2);
                    $sqlBan = "INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle, fiscal, observacion) VALUES (?,?,?,?,?,?, 'cccuentastesoreria', 'Movimiento Tesorería', 1, ?)";
                    $pdo->prepare($sqlBan)->execute([$row['cuentabancaria'], $row['debe'], $row['haber'], $saldoBan, $fecha, $num_asiento_teso, $obs]);
                }
            }

            // 8. PROCESAR CARTERA DE TERCEROS

            // 8.1. NUEVOS CHEQUES RECIBIDOS
            $stmtNewCheq = $pdo->prepare("INSERT INTO chequesterceros (cliente, numero, banco, importe, fechaemision, fechavencimiento, tipo, estado, compasoc, tabla) VALUES (?,?,?,?,?,?,?,1,?, 'cccuentastesoreria')");
            foreach($input['cheques_rec'] ?? [] as $cr) {
                $stmtNewCheq->execute([$input['cli_id'], $cr['numero'], $cr['banco_id'], $cr['importe'], $cr['f_emi'], $cr['f_cob'], $cr['tipo'], $num_asiento_teso]);
            }

            // 8.2. CHEQUES ENDOSADOS
            $stmtEndoso = $pdo->prepare("UPDATE chequesterceros SET estado = 2, endosadoa = ?, fachaendoso = ? WHERE id = ?");
            foreach($input['cheques_end'] ?? [] as $ce) {
                $stmtEndoso->execute([$input['prov_id'], $fecha, $ce['id']]);
            }

            // 9. PROCESAR CHEQUES PROPIOS
            $stmtEmi = $pdo->prepare("INSERT INTO chequespropios (proveedor, numero, fechaemision, fechavencimiento, importe, banco, cuenta, estado, idmovimiento, tabla) VALUES (?,?,?,?,?,?,?,1,?, 'cccuentastesoreria')");
            foreach($input['cheques_pro'] ?? [] as $cp) {
                $stB = $pdo->prepare("SELECT banco FROM cuentaspropias WHERE id = ?");
                $stB->execute([$cp['cta_ban_id']]);
                $id_banco_propio = $stB->fetchColumn();

                $stmtEmi->execute([
                    $input['prov_id'], 
                    $cp['numero'], 
                    $cp['f_emi'], 
                    $cp['f_venc'], 
                    $cp['importe'], 
                    $id_banco_propio, 
                    $cp['cta_ban_id'], 
                    $num_asiento_teso
                ]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'nro_asiento' => $num_asiento_teso]);
            break;
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>