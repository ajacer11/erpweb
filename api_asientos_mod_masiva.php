<?php
/**
 * api_asientos_mod_masiva.php - BACKEND
 * Procesa la actualización masiva de cuentas en librodiario.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

/**
 * Arma el WHERE/params comunes a partir de los filtros del formulario y valida
 * que ninguno de los períodos que se van a tocar esté cerrado. La usan tanto
 * el conteo previo (preview_count) como la ejecución real (process), para que
 * el conteo que ve el usuario sea siempre el mismo universo que se va a
 * modificar y para no duplicar la validación de períodos cerrados.
 */
function construirFiltroModMasiva(PDO $pdo, array $post): array {
    $ctaOrigen = $post['cuenta_origen'] ?? '';
    $ctaDestino = $post['cuenta_destino'] ?? '';

    if (empty($ctaOrigen) || empty($ctaDestino)) {
        throw new Exception("Faltan datos de cuentas.");
    }

    // El ejercicio es obligatorio: antes, si no se tildaba ningún filtro,
    // el reemplazo se aplicaba a TODO el histórico de librodiario de una sola vez.
    $idEjer = $post['ejercicio'] ?? '';
    if (empty($idEjer)) {
        throw new Exception("Debe seleccionar un ejercicio: no se permite un reemplazo masivo sin acotarlo a uno.");
    }

    $whereComun = " WHERE cuenta = :cta_orig AND idejercicio = :idejercicio";
    $params = [
        ':cta_dest' => $ctaDestino,
        ':cta_orig' => $ctaOrigen,
        ':idejercicio' => $idEjer
    ];

    // Filtro Fiscal
    $filtroFiscal = $post['filtro_fiscal'] ?? 'todos';
    if ($filtroFiscal === '1') {
        $whereComun .= " AND fiscal = 1";
    } elseif ($filtroFiscal === '0') {
        $whereComun .= " AND fiscal = 0";
    }
    // Si es 'todos', no se agrega condición

    // Filtro Fechas
    if (isset($post['usar_fechas'])) {
        $desde = $post['fecha_desde'] ?? '';
        $hasta = $post['fecha_hasta'] ?? '';

        if (empty($desde) || empty($hasta)) throw new Exception("Rango de fechas incompleto.");

        $whereComun .= " AND fecha BETWEEN :desde AND :hasta";
        $params[':desde'] = $desde;
        $params[':hasta'] = $hasta;
    }

    // --- VALIDACIÓN DE PERÍODOS CERRADOS ---
    // Buscamos qué períodos se van a tocar y si alguno está cerrado, se aborta
    // todo el reemplazo (nada parcial) para no editar contabilidad de un
    // período cerrado.
    $stmtPeriodos = $pdo->prepare("SELECT DISTINCT periodo FROM librodiario" . $whereComun . " AND periodo IS NOT NULL");
    $stmtPeriodos->execute($params);
    $periodosTocados = $stmtPeriodos->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($periodosTocados)) {
        $stmtEstadoPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
        $stmtEstadoPer->execute([$idEjer]);
        $estadoPeriodos = $stmtEstadoPer->fetch(PDO::FETCH_ASSOC);

        $cerrados = [];
        foreach ($periodosTocados as $per) {
            $per = (int)$per;
            if ($per < 1) continue;
            $col = 'p' . $per;
            if (!$estadoPeriodos || empty($estadoPeriodos[$col])) {
                $cerrados[] = $per;
            }
        }
        if (!empty($cerrados)) {
            throw new Exception("No se puede reemplazar: el filtro incluye asientos de períodos cerrados (" . implode(', ', $cerrados) . "). Ajustá el rango de fechas o el ejercicio.");
        }
    }

    return [$whereComun, $params];
}

try {
    // 1. Obtener Combos (Cuentas y Ejercicios)
    if ($action === 'get_combos') {
        // Cuentas: Habilitadas e Imputables (según VB Load)
        $stmtCuentas = $pdo->query("SELECT numcuenta, descripcion FROM plandecuentas WHERE habilitada = 1 AND imputable = 1 ORDER BY numcuenta ASC");

        // Ejercicios
        $stmtEjer = $pdo->query("SELECT id, descripcion FROM ejercicios ORDER BY id DESC");

        echo json_encode([
            'success' => true,
            'data' => [
                'cuentas' => $stmtCuentas->fetchAll(),
                'ejercicios' => $stmtEjer->fetchAll()
            ]
        ]);
        exit;
    }

    // 1.5 Contar cuántos renglones se verían afectados, sin modificar nada.
    // Se usa para mostrarle al usuario un número concreto en la segunda
    // confirmación (no hay pantalla de vista previa fila por fila, pero al
    // menos sabe cuántos registros va a tocar antes de ejecutar).
    if ($action === 'preview_count') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        [$whereComun, $params] = construirFiltroModMasiva($pdo, $_POST);

        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM librodiario" . $whereComun);
        $stmtCount->execute($params);
        $count = (int)$stmtCount->fetchColumn();

        echo json_encode(['success' => true, 'count' => $count]);
        exit;
    }

    // 2. Procesar Actualización
    if ($action === 'process') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        [$whereComun, $params] = construirFiltroModMasiva($pdo, $_POST);

        $sql = "UPDATE librodiario SET cuenta = :cta_dest" . $whereComun;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();

        echo json_encode([
            'success' => true,
            'message' => "Se han reemplazado $count registros de la tabla."
        ]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}