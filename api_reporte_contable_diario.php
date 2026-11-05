<?php
require_once 'check_api_access.php';

$input = json_decode(file_get_contents('php://input'), true);

$vista = $input['vista'] ?? 'DETALLADA';
$ejercicio = (int)($input['ejercicio'] ?? 0);
$desde = $input['desde'];
$hasta = $input['hasta'];
$fiscal = $input['fiscal'];
$manual = $input['manual'];
$pagina = max(1, (int)($input['pagina'] ?? 1));
$tamanioPagina = (int)($input['tamanioPagina'] ?? 50);
if (!in_array($tamanioPagina, [50, 100, 200, 500], true)) $tamanioPagina = 50;

try {
    // Construcción de filtros comunes
    $where = "WHERE (fecha BETWEEN :desde AND :hasta) AND idejercicio = :ejercicio";
    $params = [
        ':desde' => $desde,
        ':hasta' => $hasta,
        ':ejercicio' => $ejercicio
    ];

    if ($fiscal === 'FISCAL') {
        $where .= " AND fiscal = 1";
    } elseif ($fiscal === 'NO_FISCAL') {
        $where .= " AND fiscal = 0";
    }

    if ($manual === 'MANUAL') {
        $where .= " AND tabla = 'librodiario'";
    }

    if ($vista === 'DETALLADA') {
        // 1. Conteo para paginación
        $sqlCount = "SELECT COUNT(*) as total FROM librodiario $where";
        $stmtCount = $pdo->prepare($sqlCount);
        $stmtCount->execute($params);
        $totalRegistros = (int)$stmtCount->fetchColumn();
        $totalPaginas = max(1, (int)ceil($totalRegistros / $tamanioPagina));
        $pagina = min($pagina, $totalPaginas);

        // 2. Datos paginados
        // REGLA 5: Se incluye 'id' para asegurar que la SPA identifique correctamente cada renglón legacy
        $offset = ($pagina - 1) * $tamanioPagina;
        $sql = "SELECT 
                    id, detalle, numasiento, cuenta AS codigo, 
                    (SELECT descripcion FROM plandecuentas WHERE numcuenta = librodiario.cuenta) AS cuenta, 
                    debe, haber, fiscal, usuario, fecha 
                FROM librodiario 
                $where 
                ORDER BY fecha ASC, numasiento ASC, id ASC 
                LIMIT $tamanioPagina OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $data,
            'totalRegistros' => $totalRegistros,
            'totalPaginas' => $totalPaginas,
            'paginaActual' => $pagina
        ]);

    } else {
        // VISTA AGRUPADA (Regla VB: Itera por tablas/origen)
        $tablasOrigen = [
            'tickets' => 'VENTAS',
            'facturasdecompra' => 'COMPRAS',
            'pagos' => 'COBRO DE CUENTAS CORRIENTES',
            'pagoaproveedores' => 'PAGOS',
            'cccuentastesoreria' => 'MOVIMIENTOS DE FONDOS',
            'librodiario' => 'ASIENTOS MANUALES'
        ];

        $resultadoAgrupado = [];

        foreach ($tablasOrigen as $tablaDB => $nombreVista) {
            $sqlGroup = "SELECT 
                            cuenta AS codigo, 
                            (SELECT descripcion FROM plandecuentas WHERE numcuenta = librodiario.cuenta) AS cuenta, 
                            SUM(debe) AS debe, 
                            SUM(haber) AS haber
                         FROM librodiario 
                         $where AND tabla = '$tablaDB'
                         GROUP BY cuenta
                         ORDER BY 
                            CASE 
                                WHEN SUM(debe) > 0 AND SUM(haber) = 0 THEN 1 
                                WHEN SUM(debe) > 0 AND SUM(haber) > 0 THEN 2 
                                WHEN SUM(debe) = 0 AND SUM(haber) > 0 THEN 3 
                                ELSE 4 
                            END, cuenta";
            
            $stmt = $pdo->prepare($sqlGroup);
            $stmt->execute($params);
            $filas = $stmt->fetchAll();
            
            if (count($filas) > 0) {
                $resultadoAgrupado[$nombreVista] = $filas;
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $resultadoAgrupado
        ]);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}