<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener Datos Iniciales (Cuentas y Plantillas)
    if ($action === 'get_init_data') {
        $data = [];
        
        // Cuentas Imputables
        $stmtCuentas = $pdo->query("SELECT numcuenta as id, descripcion as text FROM plandecuentas WHERE habilitada = 1 AND imputable = 1 ORDER BY descripcion ASC");
        $data['cuentas'] = $stmtCuentas->fetchAll(PDO::FETCH_ASSOC);

        // Plantillas Guardadas
        $stmtPlantillas = $pdo->query("SELECT id, descripcion, cuentas FROM asientosguardados ORDER BY descripcion ASC");
        $data['plantillas'] = $stmtPlantillas->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['status' => 'ok', 'data' => $data]);

    } 
    // 2. Guardar Plantilla
    elseif ($action === 'save_template') {
        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = trim($input['nombre']);
        $cuentas = trim($input['cuentas']); // String separado por comas

        if (empty($nombre) || empty($cuentas)) throw new Exception("Nombre y cuentas requeridos.");

        // Verificar existencia
        $stmtCheck = $pdo->prepare("SELECT id FROM asientosguardados WHERE descripcion = ?");
        $stmtCheck->execute([$nombre]);
        $existe = $stmtCheck->fetchColumn();

        if ($existe) {
            $stmtUpd = $pdo->prepare("UPDATE asientosguardados SET cuentas = ? WHERE id = ?");
            $stmtUpd->execute([$cuentas, $existe]);
            $msg = "Plantilla actualizada.";
        } else {
            $stmtIns = $pdo->prepare("INSERT INTO asientosguardados (descripcion, cuentas) VALUES (?, ?)");
            $stmtIns->execute([$nombre, $cuentas]);
            $msg = "Plantilla guardada.";
        }

        echo json_encode(['status' => 'ok', 'message' => $msg]);

    }
    // 3. Guardar Asiento
    elseif ($action === 'save_entry') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $fecha = $input['fecha'];
        $observaciones = trim($input['observaciones']);
        $fiscal = $input['fiscal'] ? 1 : 0;
        $items = $input['items'] ?? [];
        $forceTreasury = $input['force_treasury'] ?? false; 

        if (empty($items)) throw new Exception("El asiento no tiene líneas.");
        if (empty($observaciones)) throw new Exception("Debe ingresar una observación.");

        // Validar Partida Doble
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($items as $it) {
            $totalDebe += (float)$it['debe'];
            $totalHaber += (float)$it['haber'];
        }
        if (abs($totalDebe - $totalHaber) > 0.009) {
            throw new Exception("El asiento no balancea. Diferencia: " . ($totalDebe - $totalHaber));
        }

        // --- VALIDACIÓN DE TESORERÍA (Warning) ---
        if (!$forceTreasury) {
            $cuentasTeso = [];
            foreach ($items as $it) {
                // Verificar si la cuenta está en mediosdepago
                $stmtTeso = $pdo->prepare("SELECT id FROM mediosdepago WHERE cuentacontable = ? LIMIT 1");
                $stmtTeso->execute([$it['cuenta_id']]);
                if ($stmtTeso->fetch()) {
                    $cuentasTeso[] = $it['cuenta_text'];
                }
            }
            if (!empty($cuentasTeso)) {
                echo json_encode([
                    'status' => 'warning_treasury', 
                    'message' => "Las siguientes cuentas están asociadas a tesorería:\n" . implode("\n", $cuentasTeso) . "\n¿Desea continuar?"
                ]);
                exit;
            }
        }

        $pdo->beginTransaction();
        try {
            // --- VALIDACIÓN CONTABLE OBLIGATORIA (Manual) ---
            // 1. Validar Ejercicio (CORREGIDO: Eliminadas columnas inexistentes anioinicio y cantperiodos)
            $stmtEjer = $pdo->prepare("SELECT id, apertura, cierre FROM ejercicios WHERE abierto = 1 AND apertura <= ? AND cierre >= ? LIMIT 1");
            $stmtEjer->execute([$fecha, $fecha]);
            $ejercicio = $stmtEjer->fetch(PDO::FETCH_ASSOC);

            if (!$ejercicio) throw new Exception("No hay ejercicio contable abierto para la fecha $fecha.");

            // 2. Calcular Período (Derivando todo de apertura/cierre para evitar errores de columnas faltantes)
            $fechaApertura = new DateTime($ejercicio['apertura']);
            $fechaCierre = new DateTime($ejercicio['cierre']);
            $anioInicio = (int)$fechaApertura->format('Y');
            $mesInicio = (int)$fechaApertura->format('m');

            // Calcular cantidad de periodos dinámicamente
            $intervalo = $fechaApertura->diff($fechaCierre);
            $cantPeriodosCalc = ($intervalo->y * 12) + $intervalo->m + 1; // +1 para incluir el mes final

            $fechaObj = new DateTime($fecha);
            $anioFecha = (int)$fechaObj->format('Y');
            $mesFecha = (int)$fechaObj->format('m');
            
            // Fórmula del Manual
            $mesesDesdeInicio = (($anioFecha - $anioInicio) * 12) + ($mesFecha - $mesInicio);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > $cantPeriodosCalc) {
                throw new Exception("La fecha no corresponde a un período válido del ejercicio ($periodoActual de $cantPeriodosCalc).");
            }

            // 3. Validar Período Abierto
            $columnaPeriodo = "p" . $periodoActual;
            $stmtPer = $pdo->prepare("SELECT $columnaPeriodo FROM ejperidodos WHERE idejercicio = ?");
            $stmtPer->execute([$ejercicio['id']]);
            $estadoPeriodo = $stmtPer->fetchColumn();

            if (!$estadoPeriodo || $estadoPeriodo == 0) {
                throw new Exception("El período contable $periodoActual está cerrado.");
            }

            // --- GENERACIÓN DE NÚMERO DE ASIENTO ---
            $stmtNum = $pdo->prepare("SELECT COALESCE(MAX(numasiento), 0) + 1 FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtNum->execute([$ejercicio['id']]);
            $numAsiento = (int)$stmtNum->fetchColumn();

            // Lógica de inserción histórica
            $hoy = date('Y-m-d');
            if ($fecha < $hoy) {
                $stmtMaxDia = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? AND fecha <= ?");
                $stmtMaxDia->execute([$ejercicio['id'], $fecha]);
                $ultimoAsientoFecha = $stmtMaxDia->fetchColumn();
                
                if ($ultimoAsientoFecha) {
                    $numAsiento = $ultimoAsientoFecha + 1;
                    $stmtShift = $pdo->prepare("UPDATE librodiario SET numasiento = numasiento + 1 WHERE idejercicio = ? AND numasiento >= ?");
                    $stmtShift->execute([$ejercicio['id'], $numAsiento]);
                }
            }

            // --- INSERTAR RENGLONES ---
            $sqlIns = "INSERT INTO librodiario 
                (numasiento, fecha, cuenta, debe, haber, compasoc, tabla, fiscal, usuario, detalle, idejercicio, periodo) 
                VALUES (?, ?, ?, ?, ?, 0, 'librodiario', ?, ?, ?, ?, ?)";
            
            $stmtIns = $pdo->prepare($sqlIns);
            $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

            foreach ($items as $it) {
                $stmtIns->execute([
                    $numAsiento,
                    $fecha,
                    $it['cuenta_id'],
                    $it['debe'],
                    $it['haber'],
                    $fiscal,
                    $usuario,
                    $observaciones,
                    $ejercicio['id'],
                    $periodoActual
                ]);
            }

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'message' => "Asiento N° $numAsiento generado correctamente."]);

        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>