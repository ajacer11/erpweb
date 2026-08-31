<?php
/**
 * exportar_libro_diario.php - Generador de Excel para Libro Diario
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
$fiscal = $_GET['fiscal'] ?? 'TODOS';
$manual = $_GET['manual'] ?? 'TODOS';
$vista = $_GET['vista'] ?? 'DETALLADA';

// Configuración de Headers para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Libro_Diario_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

try {
    // Filtros SQL
    $where = "WHERE (fecha BETWEEN :desde AND :hasta) AND idejercicio = :ejercicio";
    $params = [':desde' => $desde, ':hasta' => $hasta, ':ejercicio' => $ejercicio];

    if ($fiscal === 'FISCAL') $where .= " AND fiscal = 1";
    elseif ($fiscal === 'NO_FISCAL') $where .= " AND fiscal = 0";
    if ($manual === 'MANUAL') $where .= " AND tabla = 'librodiario'";

    // Estilos básicos para el Excel
    echo '
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <style>
        .header { background-color: #0d2b45; color: #ffffff; font-weight: bold; text-align: center; }
        .asiento-header { background-color: #f2f2f2; font-weight: bold; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .money { mso-number-format:"\#\,\#\#0\.00"; }
    </style>
    <table border="1">
        <tr><th colspan="4" style="font-size: 18px;">WEST IT ERP - LIBRO DIARIO</th></tr>
        <tr><th colspan="4">Período: ' . date('d/m/Y', strtotime($desde)) . ' al ' . date('d/m/Y', strtotime($hasta)) . '</th></tr>
        <tr><th colspan="4">Vista: ' . $vista . '</th></tr>
        <tr></tr>
        <tr class="header">
            <th width="150">CÓDIGO</th>
            <th width="400">CUENTA</th>
            <th width="120">DEBE</th>
            <th width="120">HABER</th>
        </tr>';

    if ($vista === 'DETALLADA') {
        $sql = "SELECT detalle, numasiento, cuenta AS codigo, 
                (SELECT descripcion FROM plandecuentas WHERE numcuenta = librodiario.cuenta) AS cuenta_nom, 
                debe, haber, fiscal, usuario, fecha 
                FROM librodiario $where 
                ORDER BY fecha ASC, numasiento ASC, haber ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $numAsientoActual = -1;

        while ($row = $stmt->fetch()) {
            if ($row['numasiento'] != $numAsientoActual) {
                $numAsientoActual = $row['numasiento'];
                echo '<tr class="asiento-header">
                        <td>Asiento ' . $row['numasiento'] . '</td>
                        <td colspan="3">Fecha: ' . date('d/m/Y', strtotime($row['fecha'])) . ' - ' . $row['detalle'] . ' (Usu: ' . $row['usuario'] . ')</td>
                      </tr>';
            }
            echo '<tr>
                    <td class="text-center">' . $row['codigo'] . '</td>
                    <td>' . $row['cuenta_nom'] . '</td>
                    <td class="text-end money">' . ($row['debe'] > 0 ? $row['debe'] : '') . '</td>
                    <td class="text-end money">' . ($row['haber'] > 0 ? $row['haber'] : '') . '</td>
                  </tr>';
        }
    } else {
        // Vista Agrupada
        $tablasOrigen = [
            'tickets' => 'VENTAS',
            'facturasdecompra' => 'COMPRAS',
            'pagos' => 'COBROS',
            'pagoaproveedores' => 'PAGOS',
            'cccuentastesoreria' => 'FONDOS',
            'librodiario' => 'MANUALES'
        ];

        foreach ($tablasOrigen as $tablaDB => $nombre) {
            $sqlGroup = "SELECT cuenta AS codigo, 
                        (SELECT descripcion FROM plandecuentas WHERE numcuenta = librodiario.cuenta) AS cuenta_nom, 
                        SUM(debe) AS debe, SUM(haber) AS haber
                        FROM librodiario $where AND tabla = '$tablaDB'
                        GROUP BY cuenta ORDER BY cuenta";
            $stmt = $pdo->prepare($sqlGroup);
            $stmt->execute($params);
            $filas = $stmt->fetchAll();

            if (count($filas) > 0) {
                echo '<tr class="asiento-header"><td colspan="4">' . $nombre . '</td></tr>';
                foreach ($filas as $f) {
                    echo '<tr>
                            <td class="text-center">' . $f['codigo'] . '</td>
                            <td>' . $f['cuenta_nom'] . '</td>
                            <td class="text-end money">' . ($f['debe'] > 0 ? $f['debe'] : '') . '</td>
                            <td class="text-end money">' . ($f['haber'] > 0 ? $f['haber'] : '') . '</td>
                          </tr>';
                }
            }
        }
    }

    echo '</table>';

} catch (Exception $e) {
    echo "Error al exportar: " . $e->getMessage();
}