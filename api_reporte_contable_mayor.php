<?php
require_once 'check_api_access.php';

$input = json_decode(file_get_contents('php://input'), true);

$ejercicio = (int)($input['ejercicio'] ?? 0);
$desde = $input['desde'];
$hasta = $input['hasta'];
$cuentaFiltro = $input['cuenta'] ?? '';
$fiscal = $input['fiscal'] ?? 'TODOS';
$incluirSaldoAnt = (bool)($input['incluirSaldoAnt'] ?? true);

try {
    // Filtro fiscal común para las consultas de datos
    $fiscWhere = "";
    if ($fiscal === 'FISCAL') $fiscWhere = " AND fiscal = 1";
    elseif ($fiscal === 'NO_FISCAL') $fiscWhere = " AND fiscal = 0";

    // 1. Obtener el universo de cuentas a procesar (Consulta rápida)
    $sqlCuentas = "SELECT numcuenta, descripcion FROM plandecuentas 
                   WHERE habilitada = 1 AND imputable = 1 
                   AND numcuenta LIKE :cta 
                   ORDER BY numcuenta ASC";
    $stmtCuentas = $pdo->prepare($sqlCuentas);
    $stmtCuentas->execute([':cta' => $cuentaFiltro . '%']);
    $listaCuentas = $stmtCuentas->fetchAll();

    if (empty($listaCuentas)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit;
    }

    // 2. OBTENCIÓN MASIVA DE SALDOS ANTERIORES (Evita el bucle N+1)
    $mapaSaldos = [];
    if ($incluirSaldoAnt) {
        $sqlSaldos = "SELECT cuenta, SUM(debe - haber) as saldo 
                      FROM librodiario 
                      WHERE fecha < :desde AND idejercicio = :ej $fiscWhere 
                      GROUP BY cuenta";
        $stmtSaldos = $pdo->prepare($sqlSaldos);
        $stmtSaldos->execute([':desde' => $desde, ':ej' => $ejercicio]);
        // PDO::FETCH_KEY_PAIR crea un array asociativo [cuenta => saldo] directamente
        $mapaSaldos = $stmtSaldos->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // 3. OBTENCIÓN MASIVA DE MOVIMIENTOS (Una sola consulta para todo el reporte)
    $sqlMovs = "SELECT cuenta, fecha, numasiento, detalle, debe, haber 
                FROM librodiario 
                WHERE (fecha BETWEEN :desde AND :hasta) AND idejercicio = :ej $fiscWhere 
                ORDER BY cuenta ASC, fecha ASC, numasiento ASC, id ASC";
    $stmtMovs = $pdo->prepare($sqlMovs);
    $stmtMovs->execute([':desde' => $desde, ':hasta' => $hasta, ':ej' => $ejercicio]);
    
    // Agrupamos los movimientos por cuenta en memoria PHP
    $movimientosPorCuenta = [];
    while ($row = $stmtMovs->fetch(PDO::FETCH_ASSOC)) {
        $cta = $row['cuenta'];
        if (!isset($movimientosPorCuenta[$cta])) {
            $movimientosPorCuenta[$cta] = [];
        }
        $movimientosPorCuenta[$cta][] = $row;
    }

    // 4. ENSAMBLADO FINAL DEL RESULTADO
    $resultado = [];
    foreach ($listaCuentas as $c) {
        $numCta = $c['numcuenta'];
        $descCta = $c['descripcion'];
        
        $saldoAnt = (float)($mapaSaldos[$numCta] ?? 0);
        $movs = $movimientosPorCuenta[$numCta] ?? [];

        // Solo incluimos la cuenta si tiene movimientos en el período o saldo inicial
        if (!empty($movs) || $saldoAnt != 0) {
            $resultado["$numCta - $descCta"] = [
                'saldoAnterior' => $saldoAnt,
                'movimientos' => $movs
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $resultado
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}