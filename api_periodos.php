<?php
/**
 * api_periodos.php - BACKEND PARA ADMINISTRACIÓN DE PERÍODOS
 * Maneja la lectura y actualización de la tabla 'ejperidodos'.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener lista de Ejercicios
    if ($action === 'get_exercises') {
        $stmt = $pdo->query("SELECT id, descripcion FROM ejercicios ORDER BY id DESC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // 2. Obtener Períodos de un Ejercicio específico
    if ($action === 'get_periods') {
        $id = intval($_GET['id'] ?? 0);
        if ($id === 0) throw new Exception("ID de ejercicio inválido.");

        // Consulta a tabla legacy 'ejperidodos' (con el typo original)
        $stmt = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        $periodos = [];
        if ($row) {
            $mesesNombres = [
                1 => "ENERO", 2 => "FEBRERO", 3 => "MARZO", 4 => "ABRIL",
                5 => "MAYO", 6 => "JUNIO", 7 => "JULIO", 8 => "AGOSTO",
                9 => "SEPTIEMBRE", 10 => "OCTUBRE", 11 => "NOVIEMBRE", 12 => "DICIEMBRE"
            ];

            $cant = intval($row['cantperiodos']);
            $mesIni = intval($row['mesinicio']);
            $anioIni = intval($row['anioinicio']);

            // Lógica de cálculo de nombres de meses (Replicando GenerarControlesPeriodos)
            for ($i = 1; $i <= $cant; $i++) {
                $mesActual = $mesIni + ($i - 1);
                $anioActual = $anioIni;

                // Ajuste si pasa de diciembre
                while ($mesActual > 12) {
                    $mesActual -= 12;
                    $anioActual++;
                }

                $columna = "p" . $i; // Columna p1, p2, etc.
                $estado = isset($row[$columna]) ? intval($row[$columna]) : 0;
                $nombreMes = $mesesNombres[$mesActual] ?? "MES $mesActual";

                $periodos[] = [
                    'col' => $columna,
                    'label' => "$nombreMes $anioActual",
                    'estado' => $estado
                ];
            }
        }

        echo json_encode(['success' => true, 'data' => $periodos]);
        exit;
    }

    // 3. Guardar cambios (UPDATE)
    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        $idEjercicio = intval($_POST['idejercicio'] ?? 0);
        if ($idEjercicio === 0) throw new Exception("Ejercicio no identificado.");

        // Construir query dinámica UPDATE ejperidodos SET p1=?, p2=? ... WHERE idejercicio=?
        // Solo actualizamos las columnas p1..p12 que vengan en el POST
        $updates = [];
        $params = [];

        for ($i = 1; $i <= 12; $i++) {
            $col = "p" . $i;
            if (isset($_POST[$col])) {
                $updates[] = "$col = ?";
                $params[] = intval($_POST[$col]); // 1 o 0
            }
        }

        if (empty($updates)) throw new Exception("No se recibieron datos para actualizar.");

        $sql = "UPDATE ejperidodos SET " . implode(", ", $updates) . " WHERE idejercicio = ?";
        $params[] = $idEjercicio;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Períodos actualizados correctamente.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}