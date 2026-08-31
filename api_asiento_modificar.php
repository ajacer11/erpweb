<?php
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get') {
        $asiento = (int)$_GET['asiento'];
        $ejercicio = (int)$_GET['ejercicio'];

        // REGLA 2 Y 6: Obtenemos el campo 'tabla' para identificar el origen automático
        $sql = "SELECT id, tabla, cuenta AS codigo, 
                (SELECT descripcion FROM plandecuentas WHERE numcuenta = librodiario.cuenta) AS cuenta_nom, 
                debe, haber, detalle, fecha, fiscal 
                FROM librodiario 
                WHERE numasiento = :asiento AND idejercicio = :ejercicio 
                ORDER BY debe DESC, haber DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':asiento' => $asiento, ':ejercicio' => $ejercicio]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $numAnterior = (int)$input['numasiento'];
        $idejercicio = (int)$input['idejercicio'];
        $fechaNueva = $input['fechaNueva'];
        $fechaOriginal = $input['fechaOriginal'];
        $detalle = $input['detalle'];
        $fiscal = (int)$input['fiscal'];
        $renglones = $input['renglones'];

        // --- VALIDACIÓN BACKEND (EL "DOBLE JUEZ") ---
        
        // 1. Validación de Fecha Futura
        if (strtotime($fechaNueva) > strtotime(date('Y-m-d'))) {
            throw new Exception("NO PUEDE CARGAR MOVIMIENTOS CON FECHA FUTURA");
        }

        // 2. Validación de Balanceo (Debe = Haber)
        $totalDebe = 0;
        $totalHaber = 0;
        foreach ($renglones as $r) {
            $totalDebe += round((float)$r['debe'], 4);
            $totalHaber += round((float)$r['haber'], 4);
        }
        
        // Tolerancia mínima por precisión de punto flotante
        if (abs($totalDebe - $totalHaber) > 0.01) {
            throw new Exception("EL ASIENTO NO BALANCEA (Diferencia: " . abs($totalDebe - $totalHaber) . ")");
        }

        // 2.1 VALIDACIÓN DE EJERCICIO/PERÍODO ABIERTO (misma regla que aplica al cargar un
        // asiento nuevo en api_asiento_manual.php — antes no se repetía acá, y permitía
        // editar asientos de períodos ya cerrados).
        $stmtEjer = $pdo->prepare("SELECT id, apertura, cierre, abierto FROM ejercicios WHERE id = ? LIMIT 1");
        $stmtEjer->execute([$idejercicio]);
        $ejercicio = $stmtEjer->fetch(PDO::FETCH_ASSOC);

        if (!$ejercicio || (int)$ejercicio['abierto'] !== 1) {
            throw new Exception("El ejercicio contable de este asiento no está abierto.");
        }
        if ($fechaNueva < $ejercicio['apertura'] || $fechaNueva > $ejercicio['cierre']) {
            throw new Exception("La fecha no corresponde al ejercicio contable de este asiento.");
        }

        $fechaAperturaEj = new DateTime($ejercicio['apertura']);
        $fechaCierreEj = new DateTime($ejercicio['cierre']);
        $anioInicioEj = (int)$fechaAperturaEj->format('Y');
        $mesInicioEj = (int)$fechaAperturaEj->format('m');
        $intervaloEj = $fechaAperturaEj->diff($fechaCierreEj);
        $cantPeriodosEj = ($intervaloEj->y * 12) + $intervaloEj->m + 1;

        $fechaNuevaObj = new DateTime($fechaNueva);
        $mesesDesdeInicioEj = ((((int)$fechaNuevaObj->format('Y')) - $anioInicioEj) * 12) + (((int)$fechaNuevaObj->format('m')) - $mesInicioEj);
        $periodoActualEj = $mesesDesdeInicioEj + 1;

        if ($periodoActualEj < 1 || $periodoActualEj > $cantPeriodosEj) {
            throw new Exception("La fecha no corresponde a un período válido del ejercicio.");
        }

        $stmtPerEj = $pdo->prepare("SELECT p$periodoActualEj FROM ejperidodos WHERE idejercicio = ?");
        $stmtPerEj->execute([$idejercicio]);
        $estadoPeriodoEj = $stmtPerEj->fetchColumn();

        if (!$estadoPeriodoEj || (int)$estadoPeriodoEj == 0) {
            throw new Exception("El período contable $periodoActualEj está cerrado.");
        }

        $pdo->beginTransaction();

        $nuevoNumero = $numAnterior;

        // --- LÓGICA DE MIGRACIÓN VB.NET: RECALCULAR NÚMERO SI CAMBIA FECHA ---
        if ($fechaOriginal !== $fechaNueva) {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(numasiento), 0) + 1 as proximo FROM librodiario WHERE fecha = :fecha AND idejercicio = :ej FOR UPDATE");
            $stmt->execute([':fecha' => $fechaNueva, ':ej' => $idejercicio]);
            $nuevoNumero = $stmt->fetch()['proximo'];

            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM librodiario WHERE numasiento = :num AND idejercicio = :ej");
            $stmtCheck->execute([':num' => $nuevoNumero, ':ej' => $idejercicio]);
            if ($stmtCheck->fetchColumn() > 0) {
                $pdo->prepare("UPDATE librodiario SET numasiento = numasiento + 1 WHERE numasiento >= :num AND idejercicio = :ej")
                    ->execute([':num' => $nuevoNumero, ':ej' => $idejercicio]);
            }
        }

        // 3. UPDATE DE RENGLONES
        $sqlUpd = "UPDATE librodiario SET 
                    fecha = :fecha, 
                    cuenta = :cuenta, 
                    fiscal = :fiscal, 
                    detalle = :detalle, 
                    numasiento = :numasiento,
                    debe = :debe,
                    haber = :haber,
                    idejercicio = :ejercicio
                   WHERE id = :id";
        $stmtUpd = $pdo->prepare($sqlUpd);

        foreach ($renglones as $r) {
            $stmtUpd->execute([
                ':fecha'      => $fechaNueva,
                ':cuenta'     => $r['codigo'],
                ':fiscal'     => $fiscal,
                ':detalle'    => $detalle,
                ':numasiento' => $nuevoNumero,
                ':debe'       => round((float)$r['debe'], 4),
                ':haber'      => round((float)$r['haber'], 4),
                ':ejercicio'  => $idejercicio,
                ':id'         => (int)$r['id']
            ]);
        }

        // 4. LÓGICA VB.NET: RestarAsientosDesde si el número anterior dejó un hueco
        if ($fechaOriginal !== $fechaNueva && $nuevoNumero !== $numAnterior) {
            $pdo->prepare("UPDATE librodiario SET numasiento = numasiento - 1 WHERE numasiento > :num AND idejercicio = :ej")
                ->execute([':num' => $numAnterior, ':ej' => $idejercicio]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'nuevoNumero' => $nuevoNumero]);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}