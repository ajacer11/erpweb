<?php
/**
 * exportar_libro_mayor.php - Generador de Excel para Libro Mayor
 */
session_start();
require_once 'db.php';

// Validar sesión
if (!isset($_SESSION['usuario_id'])) {
    exit("Acceso denegado");
}

// Obtener filtros por GET
$ejercicio = (int)($_GET['ejercicio'] ?? 0);
$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
$cuentaFiltro = $_GET['cuenta'] ?? '';
$fiscal = $_GET['fiscal'] ?? 'TODOS';
$incluirSaldoAnt = ($_GET['incluirSaldoAnt'] === 'true');

// Configuración de Headers para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Libro_Mayor_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Filtro fiscal
    $fiscWhere = "";
    if ($fiscal === 'FISCAL') $fiscWhere = " AND fiscal = 1";
    elseif ($fiscal === 'NO_FISCAL') $fiscWhere = " AND fiscal = 0";

    // 1. Obtener cuentas
    $sqlCuentas = "SELECT numcuenta, descripcion FROM plandecuentas 
                   WHERE habilitada = 1 AND imputable = 1 
                   AND numcuenta LIKE :cta 
                   ORDER BY numcuenta ASC";
    $stmtCuentas = $pdo->prepare($sqlCuentas);
    $stmtCuentas->execute([':cta' => $cuentaFiltro . '%']);
    $listaCuentas = $stmtCuentas->fetchAll();

    // 2. Obtención masiva de saldos anteriores
    $mapaSaldos = [];
    if ($incluirSaldoAnt) {
        $sqlSaldos = "SELECT cuenta, SUM(debe - haber) as saldo 
                      FROM librodiario 
                      WHERE fecha < :desde AND idejercicio = :ej $fiscWhere 
                      GROUP BY cuenta";
        $stmtSaldos = $pdo->prepare($sqlSaldos);
        $stmtSaldos->execute([':desde' => $desde, ':ej' => $ejercicio]);
        $mapaSaldos = $stmtSaldos->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    // 3. Obtención masiva de movimientos
    $sqlMovs = "SELECT cuenta, fecha, numasiento, detalle, debe, haber 
                FROM librodiario 
                WHERE (fecha BETWEEN :desde AND :hasta) AND idejercicio = :ej $fiscWhere 
                ORDER BY cuenta ASC, fecha ASC, numasiento ASC, id ASC";
    $stmtMovs = $pdo->prepare($sqlMovs);
    $stmtMovs->execute([':desde' => $desde, ':hasta' => $hasta, ':ej' => $ejercicio]);
    
    $movimientosPorCuenta = [];
    while ($row = $stmtMovs->fetch(PDO::FETCH_ASSOC)) {
        $movimientosPorCuenta[$row['cuenta']][] = $row;
    }

    // Estilos y Cabecera del Excel
    echo '
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        .header-title { background-color: #0d2b45; color: #ffffff; font-weight: bold; text-align: center; font-size: 16px; }
        .acc-header { background-color: #f2f2f2; font-weight: bold; color: #000; border: 1px solid #ccc; }
        .col-header { background-color: #e9ecef; font-weight: bold; text-align: center; }
        .text-end { text-align: right; }
        .money { mso-number-format:"\#\,\#\#0\.00"; }
        .saldo-final { font-weight: bold; background-color: #f8f9fa; }
    </style>
    <table border="1">
        <tr><th colspan="6" class="header-title">WEST IT ERP - LIBRO MAYOR</th></tr>
        <tr><th colspan="6">Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . '</th></tr>
        <tr></tr>
        <tr class="col-header">
            <th width="100">FECHA</th>
            <th width="100">ASIENTO</th>
            <th width="400">DETALLE</th>
            <th width="120">DEBE</th>
            <th width="120">HABER</th>
            <th width="120">SALDO</th>
        </tr>';

    foreach ($listaCuentas as $c) {
        $numCta = $c['numcuenta'];
        $descCta = $c['descripcion'];
        $saldoAnt = (float)($mapaSaldos[$numCta] ?? 0);
        $movs = $movimientosPorCuenta[$numCta] ?? [];

        if (empty($movs) && $saldoAnt == 0) continue;

        // Fila de Cuenta
        echo '<tr class="acc-header"><td colspan="6">MAYOR DE LA CTA: ' . $numCta . ' - ' . $descCta . '</td></tr>';

        // Saldo Anterior
        if ($incluirSaldoAnt) {
            echo '<tr>
                    <td colspan="2"></td>
                    <td style="color: #666; font-style: italic;">SALDO ANTERIOR</td>
                    <td></td><td></td>
                    <td class="text-end money">' . $saldoAnt . '</td>
                  </tr>';
        }

        $saldoAcumulado = $saldoAnt;
        foreach ($movs as $m) {
            $saldoAcumulado += ($m['debe'] - $m['haber']);
            echo '<tr>
                    <td align="center">' . date('d/m/Y', strtotime($m['fecha'])) . '</td>
                    <td align="center">' . $m['numasiento'] . '</td>
                    <td>' . htmlspecialchars($m['detalle']) . '</td>
                    <td class="text-end money">' . ($m['debe'] > 0 ? $m['debe'] : '') . '</td>
                    <td class="text-end money">' . ($m['haber'] > 0 ? $m['haber'] : '') . '</td>
                    <td class="text-end money">' . $saldoAcumulado . '</td>
                  </tr>';
        }

        // Saldo Final
        echo '<tr class="saldo-final">
                <td colspan="2"></td>
                <td align="right">SALDO FINAL:</td>
                <td></td><td></td>
                <td class="text-end money">' . $saldoAcumulado . '</td>
              </tr>
              <tr></tr>'; // Espacio entre cuentas
    }

    echo '</table>';

} catch (Exception $e) {
    echo "Error al exportar Libro Mayor: " . $e->getMessage();
}