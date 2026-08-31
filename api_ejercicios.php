<?php
/**
 * api_ejercicios.php - BACKEND PARA EJERCICIOS
 * Procesa la apertura de ejercicios y generación de períodos.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_combos') {
        // Replicación del Diccionario "meses" de VB.NET
        $meses = [
            "Enero" => 1, "Febrero" => 2, "Marzo" => 3, "Abril" => 4,
            "Mayo" => 5, "Junio" => 6, "Julio" => 7, "Agosto" => 8,
            "Septiembre" => 9, "Octubre" => 10, "Noviembre" => 11, "Diciembre" => 12
        ];
        
        echo json_encode(['success' => true, 'data' => ['meses' => $meses]]);
        exit;
    }

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido");

        $desc = $_POST['descripcion'] ?? '';
        $anioIni = intval($_POST['anioinicio'] ?? 0);
        $mesIni = intval($_POST['mesinicio'] ?? 0);
        $cantMeses = intval($_POST['meses'] ?? 0);

        // Validación final de seguridad
        if (empty($desc) || $anioIni === 0 || $mesIni === 0 || $cantMeses < 1 || $cantMeses > 12) {
            throw new Exception("Datos de entrada inválidos para el ejercicio.");
        }

        $pdo->beginTransaction();

        // 1. Calcular Fechas (Lógica VB: ObtenerFechas)
        // Fecha Inicio: 1/mes/anio
        $fechaInicioStr = "$anioIni-$mesIni-01";
        $dateInicio = new DateTime($fechaInicioStr);
        
        // Fecha Fin: Inicio + meses - 1 dia
        $dateFin = clone $dateInicio;
        $dateFin->modify('+' . $cantMeses . ' months');
        $dateFin->modify('-1 day');
        
        $apertura = $dateInicio->format('Y-m-d');
        $cierre = $dateFin->format('Y-m-d');

        // Validación de solapamiento: antes no se controlaba, y se podían crear
        // ejercicios con fechas superpuestas a uno ya existente.
        $stmtSolapa = $pdo->prepare("SELECT descripcion FROM ejercicios WHERE apertura <= ? AND cierre >= ? LIMIT 1");
        $stmtSolapa->execute([$cierre, $apertura]);
        $solapado = $stmtSolapa->fetchColumn();
        if ($solapado) {
            throw new Exception("Las fechas se superponen con el ejercicio existente \"$solapado\".");
        }

        // Valores para ejperidodos
        $mesFin = intval($dateFin->format('m'));
        $anioFin = intval($dateFin->format('Y'));

        // 2. Insertar en tabla ejercicios (CORREGIDO SEGÚN LEGACY)
        // VB: INSERT INTO ejercicios (apertura, cierre, meses, anio, mesinicio, descripcion, abierto)
        $sqlEje = "INSERT INTO ejercicios (apertura, cierre, meses, anio, mesinicio, descripcion, abierto) 
                   VALUES (?, ?, ?, ?, ?, ?, 1)";
        $stmtEje = $pdo->prepare($sqlEje);
        $stmtEje->execute([$apertura, $cierre, $cantMeses, $anioIni, $mesIni, $desc]);
        $idEjercicio = $pdo->lastInsertId();

        // 3. Generar bits p1...p12
        $p = array_fill(1, 12, 0);
        for ($i = 1; $i <= 12; $i++) {
            if ($i <= $cantMeses) {
                $p[$i] = 1;
            }
        }

        // 4. Insertar en tabla ejperidodos (Nombre tabla legacy confirmado)
        // VB: INSERT INTO ejperidodos (idejercicio, cantperiodos, mesinicio, anioinicio, ...)
        $sqlPer = "INSERT INTO ejperidodos (idejercicio, cantperiodos, mesinicio, anioinicio, mesfin, aniofin, 
                    p1, p2, p3, p4, p5, p6, p7, p8, p9, p10, p11, p12) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmtPer = $pdo->prepare($sqlPer);
        $stmtPer->execute([
            $idEjercicio, $cantMeses, $mesIni, $anioIni, $mesFin, $anioFin,
            $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7], $p[8], $p[9], $p[10], $p[11], $p[12]
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Ejercicio creado correctamente']);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}