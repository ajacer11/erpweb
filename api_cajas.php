<?php
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

// El manual y las reglas indican vincular por NOMBRE de usuario (string)
$usuario_nombre = $_SESSION['usuario_nombre'] ?? null;
$action = $_GET['action'] ?? '';

if (!$usuario_nombre) {
    echo json_encode(['success' => false, 'error' => 'Sesión no válida o usuario no identificado.']);
    exit();
}

try {
    switch ($action) {
        case 'list_available':
            // Según SQL: descripcion (nombre), estado (0=cerrada)
            $stmt = $pdo->query("SELECT id, descripcion as nombre FROM cajas WHERE estado = 0 ORDER BY descripcion ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

            case 'get_current_status':
            // controlcajas: cerradael (fecha fin), usuario (string)
            $stmt = $pdo->prepare("SELECT c.id, c.descripcion as nombre 
                                  FROM controlcajas ctrl 
                                  JOIN cajas c ON ctrl.idcaja = c.id 
                                  WHERE ctrl.usuario = ? AND (ctrl.cerradael IS NULL OR ctrl.cerradael = '0000-00-00 00:00:00')
                                  ORDER BY ctrl.id DESC LIMIT 1");
            $stmt->execute([$usuario_nombre]);
            $caja = $stmt->fetch();
            
            if ($caja) {
                // Vincular cuenta en mediosdepago mediante puestoasociado
                $stmtAcc = $pdo->prepare("SELECT id FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
                $stmtAcc->execute([$caja['id']]);
                $cuenta = $stmtAcc->fetch();
                
                $saldo = 0;
                if ($cuenta) {
                    // cccuentastesoreria: idcuenta
                    $stmtSaldo = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as saldo 
                                               FROM cccuentastesoreria 
                                               WHERE idcuenta = ?");
                    $stmtSaldo->execute([$cuenta['id']]);
                    $saldo = (float)$stmtSaldo->fetchColumn();
                }
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'id' => $caja['id'],
                        'nombre' => $caja['nombre'],
                        'saldo' => $saldo
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'No tienes una caja abierta.']);
            }
            break;

        case 'open_box':
            $id_puesto = $_POST['id_puesto'] ?? null;
            if (!$id_puesto) throw new Exception("Debe seleccionar un puesto.");

            $pdo->beginTransaction();

            // Verificar apertura previa en controlcajas (cerradael IS NULL)
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM controlcajas WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00')");
            $stmtCheck->execute([$usuario_nombre]);
            if ($stmtCheck->fetchColumn() > 0) throw new Exception("Ya tienes una caja abierta registrada.");

            // Auto-saneo: 'cajas.usuarioactivo' tiene UNIQUE KEY, así que si una
            // apertura anterior quedó mal cerrada (corte de luz, edición manual de
            // controlcajas, etc.) puede quedar otro puesto marcado con el nombre de
            // este usuario aunque controlcajas ya no muestre nada abierto — el
            // UPDATE de más abajo explota con SQLSTATE[23000] Duplicate entry al
            // intentar abrir CUALQUIER caja. Como recién se confirmó arriba que no
            // tiene ninguna apertura vigente, cualquier fila marcada con su nombre
            // es basura de una sesión anterior y se puede liberar con seguridad.
            $pdo->prepare("UPDATE cajas SET estado = 0, usuarioactivo = NULL WHERE usuarioactivo = ? AND id != ?")
                ->execute([$usuario_nombre, $id_puesto]);

            // Identificar cuenta para saldo inicial
            $stmtAcc = $pdo->prepare("SELECT id FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtAcc->execute([$id_puesto]);
            $cuenta = $stmtAcc->fetch();
            if (!$cuenta) throw new Exception("El puesto no tiene una cuenta de tesorería vinculada.");

            // Calcular Saldo Inicial
            $stmtSaldo = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as saldo 
                                       FROM cccuentastesoreria 
                                       WHERE idcuenta = ?");
            $stmtSaldo->execute([$cuenta['id']]);
            $saldo_inicial = (float)$stmtSaldo->fetchColumn();

            // Obtener idanterior (MAX(id) actual) para delimitar movimientos
            $stmtMax = $pdo->prepare("SELECT MAX(id) FROM cccuentastesoreria WHERE idcuenta = ?");
            $stmtMax->execute([$cuenta['id']]);
            $id_anterior = $stmtMax->fetchColumn();
            if ($id_anterior === false || $id_anterior === null) $id_anterior = 0;

            // Grabar en controlcajas: abiertael, usuario, saldoinicial, idanterior, estado (1=Abierta)
            $stmtIns = $pdo->prepare("INSERT INTO controlcajas (idcaja, usuario, abiertael, saldoinicial, idanterior, estado) 
                                     VALUES (?, ?, NOW(), ?, ?, 1)");
            $stmtIns->execute([$id_puesto, $usuario_nombre, $saldo_inicial, $id_anterior]);

            // Actualizar tabla cajas: estado=1, usuarioactivo
            $stmtUpdCaja = $pdo->prepare("UPDATE cajas SET estado = 1, usuarioactivo = ? WHERE id = ?");
            $stmtUpdCaja->execute([$usuario_nombre, $id_puesto]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Caja abierta correctamente.']);
            break;

        case 'close_box':
            $pdo->beginTransaction();

            // Buscar registro abierto
            $stmtCtrl = $pdo->prepare("SELECT id, idcaja FROM controlcajas 
                                      WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00')
                                      ORDER BY id DESC LIMIT 1");
            $stmtCtrl->execute([$usuario_nombre]);
            $ctrlRecord = $stmtCtrl->fetch();

            if (!$ctrlRecord) throw new Exception("No hay una apertura activa para este usuario.");

            $id_puesto = $ctrlRecord['idcaja'];
            $id_control = $ctrlRecord['id'];

            // Calcular saldo final y ultimoid
            $stmtAcc = $pdo->prepare("SELECT id FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtAcc->execute([$id_puesto]);
            $cuenta = $stmtAcc->fetch();
            
            $saldo_final = 0;
            $ultimo_id = 0;

            if ($cuenta) {
                // Saldo Final
                $stmtSaldo = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as saldo 
                                           FROM cccuentastesoreria 
                                           WHERE idcuenta = ?");
                $stmtSaldo->execute([$cuenta['id']]);
                $saldo_final = (float)$stmtSaldo->fetchColumn();

                // Obtener ultimoid (MAX(id) al cierre)
                $stmtMax = $pdo->prepare("SELECT MAX(id) FROM cccuentastesoreria WHERE idcuenta = ?");
                $stmtMax->execute([$cuenta['id']]);
                $ultimo_id = $stmtMax->fetchColumn();
                if ($ultimo_id === false || $ultimo_id === null) $ultimo_id = 0;
            }

            // Actualizar controlcajas: cerradael, saldofinal, ultimoid, estado (0=Cerrada)
            $stmtUpdCtrl = $pdo->prepare("UPDATE controlcajas SET cerradael = NOW(), saldofinal = ?, ultimoid = ?, estado = 0, usuario_cierre = ?
                                        WHERE id = ?");
            $stmtUpdCtrl->execute([$saldo_final, $ultimo_id, $usuario_nombre, $id_control]);

            // Liberar en tabla cajas: estado=0, usuarioactivo=NULL
            $stmtUpdCaja = $pdo->prepare("UPDATE cajas SET estado = 0, usuarioactivo = NULL WHERE id = ?");
            $stmtUpdCaja->execute([$id_puesto]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Caja cerrada correctamente.']);
            break;

        // CIERRE DE CAJA POR ADMINISTRADOR (a nombre de otro usuario)
        case 'admin_close_box':
            if (empty($_SESSION['es_admin']) || (int)$_SESSION['es_admin'] !== 1) {
                throw new Exception("Solo un administrador puede cerrar la caja de otro usuario.");
            }
            $id_control = (int)($_POST['id_control'] ?? 0);
            if (!$id_control) throw new Exception("Debe indicar qué caja cerrar.");

            $pdo->beginTransaction();

            $stmtCtrl = $pdo->prepare("SELECT id, idcaja, usuario FROM controlcajas WHERE id = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') LIMIT 1");
            $stmtCtrl->execute([$id_control]);
            $ctrlRecord = $stmtCtrl->fetch();
            if (!$ctrlRecord) throw new Exception("Esa caja ya no figura abierta (puede haberse cerrado recién).");

            $id_puesto = $ctrlRecord['idcaja'];

            $stmtAcc = $pdo->prepare("SELECT id FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtAcc->execute([$id_puesto]);
            $cuenta = $stmtAcc->fetch();

            $saldo_final = 0;
            $ultimo_id = 0;

            if ($cuenta) {
                $stmtSaldo = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as saldo
                                           FROM cccuentastesoreria
                                           WHERE idcuenta = ?");
                $stmtSaldo->execute([$cuenta['id']]);
                $saldo_final = (float)$stmtSaldo->fetchColumn();

                $stmtMax = $pdo->prepare("SELECT MAX(id) FROM cccuentastesoreria WHERE idcuenta = ?");
                $stmtMax->execute([$cuenta['id']]);
                $ultimo_id = $stmtMax->fetchColumn();
                if ($ultimo_id === false || $ultimo_id === null) $ultimo_id = 0;
            }

            $stmtUpdCtrl = $pdo->prepare("UPDATE controlcajas SET cerradael = NOW(), saldofinal = ?, ultimoid = ?, estado = 0, usuario_cierre = ?
                                        WHERE id = ?");
            $stmtUpdCtrl->execute([$saldo_final, $ultimo_id, $usuario_nombre, $id_control]);

            $stmtUpdCaja = $pdo->prepare("UPDATE cajas SET estado = 0, usuarioactivo = NULL WHERE id = ?");
            $stmtUpdCaja->execute([$id_puesto]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Caja de ' . $ctrlRecord['usuario'] . ' cerrada correctamente por el administrador.']);
            break;

        case 'withdraw_to_central':
            $importe = (float)($_POST['importe'] ?? 0);
            if ($importe <= 0) throw new Exception("El importe debe ser mayor a cero.");

            $pdo->beginTransaction();

            // 1. Validar si el usuario tiene caja abierta
            $stmtCtrl = $pdo->prepare("SELECT id, idcaja FROM controlcajas WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
            $stmtCtrl->execute([$usuario_nombre]);
            $ctrl = $stmtCtrl->fetch();
            if (!$ctrl) throw new Exception("No tienes una caja abierta. No puedes realizar retiros.");
            $id_caja_usuario = $ctrl['idcaja'];

            // 2. Obtener la cuenta de la caja del usuario (Origen)
            $stmtOri = $pdo->prepare("SELECT id, cuentacontable FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtOri->execute([$id_caja_usuario]);
            $cuentaOri = $stmtOri->fetch();
            if (!$cuentaOri) throw new Exception("Tu puesto no tiene una cuenta de tesorería asociada.");
            if (!cuentaContableExiste($pdo, $cuentaOri['cuentacontable'])) {
                throw new Exception("Tu caja no tiene configurada una cuenta contable válida. Contactá a administración (Tesorería > Cuentas).");
            }

            // 3. Obtener la Caja Central habilitada (Destino)
            $stmtDes = $pdo->query("SELECT id, cuentacontable FROM mediosdepago WHERE tipodecuenta = 'Caja Central' AND habilitado = 1 LIMIT 1");
            $cuentaDes = $stmtDes->fetch();
            if (!$cuentaDes) throw new Exception("No se encontró una Caja Central habilitada en el sistema.");
            if (!cuentaContableExiste($pdo, $cuentaDes['cuentacontable'])) {
                throw new Exception("La Caja Central no tiene configurada una cuenta contable válida. Revísela en Tesorería > Cuentas.");
            }

            // 4. Validar saldo disponible
            $stmtBal = $pdo->prepare("SELECT SUM(debe) - SUM(haber) FROM cccuentastesoreria WHERE idcuenta = ?");
            $stmtBal->execute([$cuentaOri['id']]);
            $saldo_actual = (float)$stmtBal->fetchColumn();
            
            if ($importe > $saldo_actual) {
                throw new Exception("Saldo insuficiente. Tu saldo actual es: $ " . number_format($saldo_actual, 2));
            }

            // 5. VALIDACIÓN DE EJERCICIO Y PERÍODO (LÓGICA VB.NET MIGRADA)
            $fecha = date('Y-m-d'); // Fecha actual para el retiro

            // A. Buscar Ejercicio Abierto
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fecha, $fecha]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();
            if (!$ejercicio_id) {
                throw new Exception("No hay un ejercicio contable abierto para la fecha actual ($fecha).");
            }

            // B. Buscar Estructura de Períodos (ejperiodos)
            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
            if (!$rowPer) {
                throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");
            }

            // C. Calcular Período Actual dinámicamente
            $fechaDT = new DateTime($fecha);
            $mesesDesdeInicio = ((int) $fechaDT->format('Y') - (int) $rowPer['anioinicio']) * 12 + ((int) $fechaDT->format('m') - (int) $rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int) $rowPer['cantperiodos']) {
                throw new Exception("La fecha actual no pertenece a ningún período válido del ejercicio.");
            }

            // D. Verificar si el período específico está abierto (campo p1, p2, etc.)
            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0) {
                throw new Exception("El período contable {$periodoActual} está cerrado. No se pueden registrar movimientos.");
            }

            // 6. Obtener numeradores
            $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = ((int)$stmtTesoNum->fetchColumn()) + 1;

            $stmtAccNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtAccNum->execute([$ejercicio_id]);
            $num_asiento_contable = ((int)$stmtAccNum->fetchColumn()) + 1;

            // 7. Generar Asientos en LIBRODIARIO
            $concepto = "RETIRO A CAJA CENTRAL - " . $usuario_nombre;

            // a. CAJA CENTRAL (Entra dinero -> DEBE)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaDes['cuentacontable'], $importe, 0, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);

            // b. CAJA USUARIO (Sale dinero -> HABER)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaOri['cuentacontable'], 0, $importe, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);

            // 8. Generar Movimientos de Tesorería (cccuentastesoreria)
            $stmtInsTeso = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, numerodeasiento, descripcion, usuario, tabla) VALUES (?, ?, ?, ?, ?, ?, ?, 'retiro_central')");

            // a. CAJA USUARIO (Disminuye -> HABER)
            $stmtInsTeso->execute([$cuentaOri['id'], $fecha, 0, $importe, $num_asiento_teso, $concepto, $usuario_nombre]);

            // b. CAJA CENTRAL (Aumenta -> DEBE)
            $stmtInsTeso->execute([$cuentaDes['id'], $fecha, $importe, 0, $num_asiento_teso, $concepto, $usuario_nombre]);

            $pdo->commit();
            echo json_encode(['success' => true, 'comprobante_id' => $num_asiento_teso, 'message' => 'Retiro a Caja Central registrado correctamente. Asiento Contable Nº ' . $num_asiento_contable]);
            break;

        // Listar cajas destino disponibles
        case 'list_open_target_boxes':
            // Buscar cajas activas que NO sean la del usuario actual Y sean tipo "Caja de Usuarios"
            $stmt = $pdo->prepare("SELECT c.id, c.descripcion as nombre, c.usuarioactivo 
                                  FROM cajas c 
                                  JOIN mediosdepago m ON c.id = m.puestoasociado 
                                  JOIN controlcajas cc ON c.id = cc.idcaja 
                                  WHERE c.estado = 1 
                                  AND (cc.cerradael IS NULL OR cc.cerradael = '0000-00-00 00:00:00')
                                  AND m.tipodecuenta = 'Caja de Usuarios' 
                                  AND c.usuarioactivo != ? 
                                  ORDER BY c.descripcion ASC");
            $stmt->execute([$usuario_nombre]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        // Transferir a otra caja
        case 'transfer_to_box':
            $importe = (float)($_POST['importe'] ?? 0);
            $id_caja_destino = $_POST['id_caja_destino'] ?? null;

            if ($importe <= 0) throw new Exception("El importe debe ser mayor a cero.");
            if (!$id_caja_destino) throw new Exception("Debe seleccionar una caja de destino.");

            $pdo->beginTransaction();

            // 1. Validar Caja Origen (Usuario Actual)
            $stmtCtrl = $pdo->prepare("SELECT id, idcaja FROM controlcajas WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
            $stmtCtrl->execute([$usuario_nombre]);
            $ctrl = $stmtCtrl->fetch();
            if (!$ctrl) throw new Exception("No tienes una caja abierta. No puedes realizar transferencias.");
            $id_caja_origen = $ctrl['idcaja'];

            // 2. Obtener cuenta Origen
            $stmtOri = $pdo->prepare("SELECT id, cuentacontable, medio FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtOri->execute([$id_caja_origen]);
            $cuentaOri = $stmtOri->fetch();
            if (!$cuentaOri) throw new Exception("Tu puesto no tiene cuenta asociada.");
            if (!cuentaContableExiste($pdo, $cuentaOri['cuentacontable'])) {
                throw new Exception("Tu caja no tiene configurada una cuenta contable válida. Contactá a administración (Tesorería > Cuentas).");
            }

            // 3. Validar Caja Destino (Debe estar abierta y existir)
            $stmtDesCheck = $pdo->prepare("SELECT c.id, c.descripcion, c.usuarioactivo 
                                          FROM cajas c 
                                          JOIN controlcajas cc ON c.id = cc.idcaja 
                                          WHERE c.id = ? AND c.estado = 1 AND (cc.cerradael IS NULL OR cc.cerradael = '0000-00-00 00:00:00')");
            $stmtDesCheck->execute([$id_caja_destino]);
            $cajaDesData = $stmtDesCheck->fetch();
            if (!$cajaDesData) throw new Exception("La caja de destino no está disponible o fue cerrada recientemente.");

            // Obtener cuenta Destino
            $stmtDes = $pdo->prepare("SELECT id, cuentacontable, medio FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtDes->execute([$id_caja_destino]);
            $cuentaDes = $stmtDes->fetch();
            if (!$cuentaDes) throw new Exception("La caja de destino no tiene cuenta de tesorería vinculada.");
            if (!cuentaContableExiste($pdo, $cuentaDes['cuentacontable'])) {
                throw new Exception("La caja de destino no tiene configurada una cuenta contable válida. Contactá a administración (Tesorería > Cuentas).");
            }

            // 4. Validar saldo disponible en Origen
            $stmtBal = $pdo->prepare("SELECT SUM(debe) - SUM(haber) FROM cccuentastesoreria WHERE idcuenta = ?");
            $stmtBal->execute([$cuentaOri['id']]);
            $saldo_actual = (float)$stmtBal->fetchColumn();
            
            if ($importe > $saldo_actual) {
                throw new Exception("Saldo insuficiente. Tu saldo actual es: $ " . number_format($saldo_actual, 2));
            }

            // 5. VALIDACIÓN DE EJERCICIO Y PERÍODO
            $fecha = date('Y-m-d'); 
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fecha, $fecha]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();
            if (!$ejercicio_id) throw new Exception("No hay un ejercicio contable abierto para la fecha actual ($fecha).");

            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
            if (!$rowPer) throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");

            $fechaDT = new DateTime($fecha);
            $mesesDesdeInicio = ((int) $fechaDT->format('Y') - (int) $rowPer['anioinicio']) * 12 + ((int) $fechaDT->format('m') - (int) $rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int) $rowPer['cantperiodos']) {
                throw new Exception("La fecha actual no pertenece a ningún período válido del ejercicio.");
            }

            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0) {
                throw new Exception("El período contable {$periodoActual} está cerrado. No se pueden registrar movimientos.");
            }

            // 6. Obtener numeradores
            $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = ((int)$stmtTesoNum->fetchColumn()) + 1;
            $stmtAccNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtAccNum->execute([$ejercicio_id]);
            $num_asiento_contable = ((int)$stmtAccNum->fetchColumn()) + 1;

            // 7. Generar Asientos
            $concepto = "TRANSF. A CAJA " . $cajaDesData['descripcion'] . " (" . $cajaDesData['usuarioactivo'] . ")";

            // LIBRODIARIO
            // a. DESTINO (Entra -> DEBE)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaDes['cuentacontable'], $importe, 0, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);
            // b. ORIGEN (Sale -> HABER)
            grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaOri['cuentacontable'], 0, $importe, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);

            // TESORERÍA
            $stmtInsTeso = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, numerodeasiento, descripcion, usuario, tabla) VALUES (?, ?, ?, ?, ?, ?, ?, 'transf_cajas')");
            // a. ORIGEN (Sale -> HABER)
            $stmtInsTeso->execute([$cuentaOri['id'], $fecha, 0, $importe, $num_asiento_teso, $concepto, $usuario_nombre]);
            // b. DESTINO (Entra -> DEBE)
            $stmtInsTeso->execute([$cuentaDes['id'], $fecha, $importe, 0, $num_asiento_teso, $concepto, $usuario_nombre]);

            $pdo->commit();
            echo json_encode(['success' => true, 'comprobante_id' => $num_asiento_teso, 'message' => 'Transferencia registrada correctamente. Asiento Nº ' . $num_asiento_contable]);
            break;

        // NUEVO CASO: Arqueo y Ajuste de Caja
        case 'adjust_balance':
            $saldo_real = (float)($_POST['saldo_real'] ?? 0);
            if ($saldo_real < 0) throw new Exception("El saldo real no puede ser negativo.");

            $pdo->beginTransaction();

            // 1. Validar Caja Abierta del Usuario
            $stmtCtrl = $pdo->prepare("SELECT id, idcaja FROM controlcajas WHERE usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
            $stmtCtrl->execute([$usuario_nombre]);
            $ctrl = $stmtCtrl->fetch();
            if (!$ctrl) throw new Exception("No tienes una caja abierta para realizar ajustes.");
            $id_caja_usuario = $ctrl['idcaja'];

            // 2. Obtener cuenta de Tesorería de la Caja
            $stmtOri = $pdo->prepare("SELECT id, cuentacontable FROM mediosdepago WHERE puestoasociado = ? LIMIT 1");
            $stmtOri->execute([$id_caja_usuario]);
            $cuentaCaja = $stmtOri->fetch();
            if (!$cuentaCaja) throw new Exception("Tu puesto no tiene cuenta asociada.");
            if (!cuentaContableExiste($pdo, $cuentaCaja['cuentacontable'])) {
                throw new Exception("Tu caja no tiene configurada una cuenta contable válida. Contactá a administración (Tesorería > Cuentas).");
            }

            // 3. Calcular Saldo Teórico Actual
            $stmtBal = $pdo->prepare("SELECT SUM(debe) - SUM(haber) FROM cccuentastesoreria WHERE idcuenta = ?");
            $stmtBal->execute([$cuentaCaja['id']]);
            $saldo_teorico = (float)$stmtBal->fetchColumn();

            // 4. Calcular Diferencia (Real - Teórico)
            $diferencia = $saldo_real - $saldo_teorico;

            // Si la diferencia es 0 (o insignificante), no hacemos nada
            if (abs($diferencia) < 0.01) {
                $pdo->rollBack(); // No hay cambios
                echo json_encode(['success' => true, 'message' => 'El saldo es correcto. No se requiere ajuste.']);
                exit();
            }

            // 5. Contrapartida: el medio de tesorería de tipo 'Ajuste' ("Ajuste de Cajas", cuenta Diferencias de
            //    Caja), igual que el sistema anterior: el ajuste mueve la caja Y ese medio en tesorería, y el
            //    asiento va contra su cuenta. cuentaspredeterminadas.ajuste es la diferencia de INVENTARIOS
            //    (ajustes de stock): usarla acá imputaba los ajustes de caja a la cuenta equivocada.
            $stmtMedioAj = $pdo->query("SELECT id, cuentacontable FROM mediosdepago WHERE tipodecuenta = 'Ajuste' AND habilitado = 1 ORDER BY id");
            $mediosAjuste = array_values(array_filter($stmtMedioAj->fetchAll(PDO::FETCH_ASSOC), fn($m) => cuentaContableExiste($pdo, $m['cuentacontable'])));
            if (count($mediosAjuste) !== 1) {
                throw new Exception(count($mediosAjuste) === 0
                    ? "No hay un medio de tesorería de tipo 'Ajuste' habilitado con cuenta contable (ej. 'Ajuste de Cajas'). Configúrelo en Tesorería > Cuentas."
                    : "Hay más de un medio de tesorería de tipo 'Ajuste' habilitado; deje uno solo para los ajustes de caja.");
            }
            $medioAjuste = $mediosAjuste[0];
            $cuenta_ajuste_contable = trim((string) $medioAjuste['cuentacontable']);

            // 6. VALIDACIÓN DE EJERCICIO Y PERÍODO (CRÍTICO)
            $fecha = date('Y-m-d');
            
            // A. Ejercicio
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fecha, $fecha]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();
            if (!$ejercicio_id) throw new Exception("No hay un ejercicio contable abierto para la fecha actual.");

            // B. Estructura Períodos
            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
            if (!$rowPer) throw new Exception("No se encontró la estructura de períodos.");

            // C. Calcular Período
            $fechaDT = new DateTime($fecha);
            $mesesDesdeInicio = ((int) $fechaDT->format('Y') - (int) $rowPer['anioinicio']) * 12 + ((int) $fechaDT->format('m') - (int) $rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int) $rowPer['cantperiodos']) {
                throw new Exception("La fecha actual no pertenece a ningún período válido.");
            }

            // D. Validar Período Abierto
            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0) {
                throw new Exception("El período contable {$periodoActual} está cerrado.");
            }

            // 7. Obtener Numeradores
            $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE");
            $num_asiento_teso = ((int)$stmtTesoNum->fetchColumn()) + 1;
            
            $stmtAccNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtAccNum->execute([$ejercicio_id]);
            $num_asiento_contable = ((int)$stmtAccNum->fetchColumn()) + 1;

            // 8. Generar Asientos
            $concepto = "AJUSTE DE CAJA - " . $usuario_nombre . " (DIF: " . number_format($diferencia, 2) . ")";
            
            $stmtInsTeso = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, numerodeasiento, descripcion, usuario, tabla) VALUES (?, ?, ?, ?, ?, ?, ?, 'ajuste_caja')");

            if ($diferencia < 0) {
                // FALTANTE DE CAJA (Sale Dinero de Caja -> Haber)
                // Pérdida (Ajuste) -> Debe
                $importe_ajuste = abs($diferencia);

                // Libro Diario
                // 1. Pérdida (Ajuste) al DEBE
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuenta_ajuste_contable, $importe_ajuste, 0, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);
                // 2. Caja al HABER
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaCaja['cuentacontable'], 0, $importe_ajuste, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);

                // Tesorería: sale de la caja (HABER) y entra al medio de Ajuste (DEBE)
                $stmtInsTeso->execute([$cuentaCaja['id'], $fecha, 0, $importe_ajuste, $num_asiento_teso, $concepto, $usuario_nombre]);
                $stmtInsTeso->execute([$medioAjuste['id'], $fecha, $importe_ajuste, 0, $num_asiento_teso, $concepto, $usuario_nombre]);

            } else {
                // SOBRANTE DE CAJA (Entra Dinero a Caja -> Debe)
                // Ganancia (Ajuste) -> Haber
                $importe_ajuste = $diferencia;

                // Libro Diario
                // 1. Caja al DEBE
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuentaCaja['cuentacontable'], $importe_ajuste, 0, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);
                // 2. Ganancia (Ajuste) al HABER
                grabarLineaAsiento($pdo, $num_asiento_contable, $fecha, $cuenta_ajuste_contable, 0, $importe_ajuste, $ctrl['id'], 'controlcajas', 0, $usuario_nombre, $concepto, $ejercicio_id, $periodoActual);

                // Tesorería: entra a la caja (DEBE) y sale del medio de Ajuste (HABER)
                $stmtInsTeso->execute([$cuentaCaja['id'], $fecha, $importe_ajuste, 0, $num_asiento_teso, $concepto, $usuario_nombre]);
                $stmtInsTeso->execute([$medioAjuste['id'], $fecha, 0, $importe_ajuste, $num_asiento_teso, $concepto, $usuario_nombre]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'comprobante_id' => $num_asiento_teso, 'message' => 'Ajuste realizado correctamente. Diferencia ajustada: $ ' . number_format($diferencia, 2)]);
            break;

        default:
            throw new Exception("Acción no definida.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
