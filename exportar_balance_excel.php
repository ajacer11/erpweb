<?php
/**
 * exportar_balance_excel.php - Generador Excel Sumas y Saldos (Versión Optimizada)
 * Evita el bloqueo del servidor mediante procesamiento masivo de saldos.
 */
session_start();
require_once 'db.php';

// SEGURIDAD: Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    exit("Acceso denegado");
}

// Obtener filtros por GET
$ejercicioId = (int)($_GET['ejercicio'] ?? 0);
$fiscal = $_GET['fiscal'] ?? 'TODOS';
$soloMovimientos = (isset($_GET['soloMovimientos']) && $_GET['soloMovimientos'] === 'true');

// Configuración de Headers para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=SumasYSaldos_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // 1. Obtener datos del ejercicio (Regla VB: apertura y cierre)
    $stmtEj = $pdo->prepare("SELECT descripcion, apertura, cierre FROM ejercicios WHERE id = ?");
    $stmtEj->execute([$ejercicioId]);
    $ej = $stmtEj->fetch();
    if (!$ej) exit("Ejercicio inválido");

    $desde = $ej['apertura'];
    $hasta = $ej['cierre'];

    // Filtro fiscal común
    $fiscWhere = "";
    if ($fiscal === 'FISCAL') $fiscWhere = " AND fiscal = 1";
    elseif ($fiscal === 'NO_FISCAL') $fiscWhere = " AND fiscal = 0";

    // --- OPTIMIZACIÓN DE ALTO RENDIMIENTO (Elimina el cuelgue) ---

    // 2. OBTENCIÓN MASIVA DE SALDOS INICIALES (Una sola consulta)
    // Se calcula la suma de (debe - haber) para todo lo anterior a la apertura
    $sqlSaldosIni = "SELECT cuenta, SUM(debe - haber) as saldo 
                     FROM librodiario 
                     WHERE fecha < :desde $fiscWhere 
                     GROUP BY cuenta";
    $stmtSaldosIni = $pdo->prepare($sqlSaldosIni);
    $stmtSaldosIni->execute([':desde' => $desde]);
    $mapaSaldosIni = $stmtSaldosIni->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. OBTENCIÓN MASIVA DE MOVIMIENTOS DEL PERÍODO (Una sola consulta)
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

    // 4. OBTENER PLAN DE CUENTAS (Universo de cuentas)
    $sqlCuentas = "SELECT numcuenta, descripcion FROM plandecuentas 
                   WHERE imputable = 1 AND habilitada = 1 
                   ORDER BY numcuenta ASC";
    $listaCuentas = $pdo->query($sqlCuentas)->fetchAll();

    // Estilos del Excel
    echo '
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        .title { background-color: #0d2b45; color: #ffffff; font-weight: bold; text-align: center; }
        .header { background-color: #f2f2f2; font-weight: bold; }
        .money { mso-number-format:"\#\,\#\#0\.00"; text-align: right; }
        .total { background-color: #e9ecef; font-weight: bold; }
    </style>
    <table border="1">
        <tr><th colspan="6" class="title">WEST IT ERP - BALANCE DE SUMAS Y SALDOS</th></tr>
        <tr><th colspan="6">Ejercicio: ' . htmlspecialchars($ej['descripcion']) . ' (' . date('d/m/Y', strtotime($desde)) . ' a ' . date('d/m/Y', strtotime($hasta)) . ')</th></tr>
        <tr><th colspan="6">Fiscalidad: ' . $fiscal . '</th></tr>
        <tr></tr>
        <tr class="header">
            <th width="120">CÓDIGO</th>
            <th width="350">DETALLE DE LA CUENTA</th>
            <th width="120">SALDO INICIAL</th>
            <th width="120">DEBE</th>
            <th width="120">HABER</th>
            <th width="120">SALDO FINAL</th>
        </tr>';

    $t = ['si' => 0, 'd' => 0, 'h' => 0, 'sf' => 0];

    foreach ($listaCuentas as $c) {
        $numCta = $c['numcuenta'];
        
        $saldoIni = (float)($mapaSaldosIni[$numCta] ?? 0);
        $debe = (float)($mapaMovs[$numCta]['suma_debe'] ?? 0);
        $haber = (float)($mapaMovs[$numCta]['suma_haber'] ?? 0);
        $saldoFin = $saldoIni + $debe - $haber;

        // Regla VB: Si está tildado solo movimientos, ignorar cuentas en cero en el período
        if ($soloMovimientos && $debe == 0 && $haber == 0) continue;

        // Evitar exportar filas totalmente en cero si el plan es muy grande
        if ($saldoIni == 0 && $debe == 0 && $haber == 0 && $saldoFin == 0) continue;

        echo '<tr>
                <td align="center">' . $numCta . '</td>
                <td>' . htmlspecialchars($c['descripcion']) . '</td>
                <td class="money">' . ($saldoIni != 0 ? $saldoIni : '0.00') . '</td>
                <td class="money">' . ($debe != 0 ? $debe : '0.00') . '</td>
                <td class="money">' . ($haber != 0 ? $haber : '0.00') . '</td>
                <td class="money">' . ($saldoFin != 0 ? $saldoFin : '0.00') . '</td>
              </tr>';

        $t['si'] += $saldoIni; 
        $t['d'] += $debe; 
        $t['h'] += $haber; 
        $t['sf'] += $saldoFin;
    }

    echo '<tr class="total">
            <td colspan="2" align="right">TOTALES GENERALES:</td>
            <td class="money">' . $t['si'] . '</td>
            <td class="money">' . $t['d'] . '</td>
            <td class="money">' . $t['h'] . '</td>
            <td class="money">' . $t['sf'] . '</td>
          </tr>
    </table>';

} catch (Exception $e) { 
    echo "Error crítico en exportación: " . $e->getMessage(); 
}