<?php
require_once 'check_api_access.php';

$input = json_decode(file_get_contents('php://input'), true);

$ejercicioId = (int)($input['ejercicio'] ?? 0);
$fiscal = $input['fiscal'] ?? 'TODOS';
$soloMovimientos = (bool)($input['soloMovimientos'] ?? true);

try {
    // 1. Obtener fechas del ejercicio (Regla VB: apertura/cierre)
    $stmtEj = $pdo->prepare("SELECT apertura, cierre FROM ejercicios WHERE id = ?");
    $stmtEj->execute([$ejercicioId]);
    $ej = $stmtEj->fetch();

    if (!$ej) {
        throw new Exception("El ejercicio seleccionado no es válido.");
    }

    $desde = $ej['apertura'];
    $hasta = $ej['cierre'];

    // Filtro fiscal común
    $fiscWhere = "";
    if ($fiscal === 'FISCAL') $fiscWhere = " AND fiscal = 1";
    elseif ($fiscal === 'NO_FISCAL') $fiscWhere = " AND fiscal = 0";

    // --- OPTIMIZACIÓN DE ALTO RENDIMIENTO (Elimina el cuelgue) ---

    // 2. OBTENCIÓN MASIVA DE SALDOS INICIALES
    // Calculamos de una sola vez el saldo acumulado (debe-haber) previo a la fecha de apertura
    $sqlSaldosIni = "SELECT cuenta, SUM(debe - haber) as saldo 
                     FROM librodiario 
                     WHERE fecha < :desde $fiscWhere 
                     GROUP BY cuenta";
    $stmtSaldosIni = $pdo->prepare($sqlSaldosIni);
    $stmtSaldosIni->execute([':desde' => $desde]);
    // Generamos un mapa indexado por cuenta para acceso instantáneo
    $mapaSaldosIni = $stmtSaldosIni->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. OBTENCIÓN MASIVA DE MOVIMIENTOS DEL PERÍODO
    // Calculamos las sumatorias de debe y haber dentro del rango del ejercicio
    $sqlMovs = "SELECT cuenta, SUM(debe) as suma_debe, SUM(haber) as suma_haber 
                FROM librodiario 
                WHERE (fecha BETWEEN :desde AND :hasta) $fiscWhere 
                GROUP BY cuenta";
    $stmtMovs = $pdo->prepare($sqlMovs);
    $stmtMovs->execute([':desde' => $desde, ':hasta' => $hasta]);
    $mapaMovs = [];
    while ($row = $stmtMovs->fetch(PDO::FETCH_ASSOC)) {
        $mapaMovs[$row['cuenta']] = $row;
    }

    // 4. OBTENER UNIVERSO DE CUENTAS (Plandecuentas legacy)
    $sqlCuentas = "SELECT numcuenta, descripcion FROM plandecuentas 
                   WHERE imputable = 1 AND habilitada = 1 
                   ORDER BY numcuenta ASC";
    $listaCuentas = $pdo->query($sqlCuentas)->fetchAll();

    $final_data = [];
    $totales = [
        'saldo_inicial' => 0,
        'suma_debe' => 0,
        'suma_haber' => 0,
        'saldo_final' => 0
    ];

    // 5. ENSAMBLADO EN MEMORIA (Complejidad Lineal O(n))
    foreach ($listaCuentas as $c) {
        $numCta = $c['numcuenta'];
        
        $saldoIni = (float)($mapaSaldosIni[$numCta] ?? 0);
        $debe = (float)($mapaMovs[$numCta]['suma_debe'] ?? 0);
        $haber = (float)($mapaMovs[$numCta]['suma_haber'] ?? 0);
        $saldoFin = $saldoIni + $debe - $haber;

        // Aplicar filtro de movimientos si corresponde
        if ($soloMovimientos && $debe == 0 && $haber == 0) {
            continue;
        }

        // Ignorar cuentas que estén totalmente en cero (Limpieza de reporte)
        if ($saldoIni == 0 && $debe == 0 && $haber == 0 && $saldoFin == 0) {
            continue;
        }

        $row_processed = [
            'numcuenta' => $numCta,
            'descripcion' => $c['descripcion'],
            'saldo_inicial' => $saldoIni,
            'suma_debe' => $debe,
            'suma_haber' => $haber,
            'saldo_final' => $saldoFin
        ];

        $final_data[] = $row_processed;

        // Acumular Totales Generales
        $totales['saldo_inicial'] += $saldoIni;
        $totales['suma_debe'] += $debe;
        $totales['suma_haber'] += $haber;
        $totales['saldo_final'] += $saldoFin;
    }

    echo json_encode([
        'success' => true,
        'data' => $final_data,
        'totales' => $totales
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}