<?php
/**
 * api_tesoreria_informe.php - MOTOR DE DATOS PARA INFORME DE TESORERÍA
 * v5.1 (Fila de Compensación para Saldo Real con Filtro de Usuario)
 */

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    if(($_GET['action'] ?? '') !== 'exportar_excel') {
        http_response_code(403);
        exit();
    }
}

require_once 'db.php';

$action = $_GET['action'] ?? '';

function getMovimientos($pdo, $filters) {
    // 1. Decodificar Cuentas
    $ids_cuentas = json_decode($filters['ids_cuentas'] ?? '[]');
    if (!is_array($ids_cuentas)) $ids_cuentas = [$filters['ids_cuentas'] ?? 0];

    // 2. Decodificar Usuarios (Para filtrado en PHP)
    $lista_usuarios = json_decode($filters['usuarios'] ?? '[]');
    if (!is_array($lista_usuarios)) {
        $lista_usuarios = !empty($filters['usuarios']) ? [$filters['usuarios']] : [];
    }
    $lista_usuarios = array_map('strval', array_filter($lista_usuarios)); 
    $filtrar_usuarios = !empty($lista_usuarios);

    $desde = $filters['desde'];
    $hasta = $filters['hasta'];
    $tipo_fiscal = $filters['tipo_fiscal'];
    $solo_saldos = $filters['solo_saldos'] === 'true' || $filters['solo_saldos'] === true;
    $desglosar = $filters['desglosar'] === 'true' || $filters['desglosar'] === true;

    // CONSTRUCCIÓN WHERE DINÁMICO (SOLO CUENTAS Y FECHAS, USUARIO SE FILTRA DESPUÉS)
    $whereCommon = "";
    $paramsCommon = [];
    
    if (!empty($ids_cuentas) && !in_array(0, $ids_cuentas) && !in_array("0", $ids_cuentas)) {
        $placeholders = implode(',', array_fill(0, count($ids_cuentas), '?'));
        $whereCommon .= " AND t.idcuenta IN ($placeholders) ";
        $paramsCommon = array_merge($paramsCommon, $ids_cuentas);
    }

    if ($tipo_fiscal === 'fiscales') $whereCommon .= " AND t.fiscal = 1 ";
    elseif ($tipo_fiscal === 'nofiscales') $whereCommon .= " AND t.fiscal = 0 ";

    // --- MODO 1: SOLO SALDOS (RESUMEN) ---
    // En "Solo Saldos" devolvemos el saldo REAL de la cuenta (ignoramos filtro de usuario para no dar info parcial falsa)
    if ($solo_saldos) {
        $sql = "SELECT m.id as ID, m.medio as Cuenta, (SUM(t.debe) - SUM(t.haber)) as Saldo
                FROM cccuentastesoreria t
                INNER JOIN mediosdepago m ON t.idcuenta = m.id
                WHERE t.fecha <= ? $whereCommon
                GROUP BY m.id, m.medio ORDER BY m.medio ASC";
        
        $finalParams = array_merge([$hasta], $paramsCommon);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($finalParams);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // --- MODO 2: DESGLOSADO POR CUENTA (AGRUPADO) ---
    if ($desglosar) {
        // A. Saldos Anteriores GLOBALES
        $sqlAnt = "SELECT t.idcuenta, (SUM(t.debe) - SUM(t.haber)) as saldo_ant 
                   FROM cccuentastesoreria t 
                   WHERE t.fecha < ? $whereCommon 
                   GROUP BY t.idcuenta";
        $finalParamsAnt = array_merge([$desde], $paramsCommon);
        $stmtAnt = $pdo->prepare($sqlAnt);
        $stmtAnt->execute($finalParamsAnt);
        $saldosIniciales = $stmtAnt->fetchAll(PDO::FETCH_KEY_PAIR);

        // B. Movimientos GLOBALES
        $sqlMov = "SELECT t.fecha as Fecha, t.numerodeasiento as NumAsiento, m.medio as Cuenta, m.id as IDCuenta,
                          t.descripcion as Detalle, t.debe as Debe, t.haber as Haber, t.fiscal as Fiscal, t.usuario as Usuario 
                   FROM cccuentastesoreria t
                   INNER JOIN mediosdepago m ON t.idcuenta = m.id
                   WHERE t.fecha >= ? AND t.fecha <= ? $whereCommon
                   ORDER BY m.medio ASC, t.fecha ASC, t.id ASC";
        $finalParamsMov = array_merge([$desde, $hasta], $paramsCommon);
        $stmtMov = $pdo->prepare($sqlMov);
        $stmtMov->execute($finalParamsMov);
        $movimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

        // C. Procesar y Filtrar
        $data = [];
        $cuentasProcesadas = [];
        $movsPorCuenta = [];
        
        foreach($movimientos as $mov) {
            $movsPorCuenta[$mov['IDCuenta']][] = $mov;
            $cuentasProcesadas[$mov['IDCuenta']] = $mov['Cuenta'];
        }
        
        foreach($saldosIniciales as $idCta => $saldo) {
            if(!isset($cuentasProcesadas[$idCta])) {
                $n = $pdo->query("SELECT medio FROM mediosdepago WHERE id = $idCta")->fetchColumn();
                $cuentasProcesadas[$idCta] = $n;
            }
        }
        asort($cuentasProcesadas);

        foreach($cuentasProcesadas as $idCta => $nombreCta) {
            $saldoActual = floatval($saldosIniciales[$idCta] ?? 0);
            
            $rowsCuenta = [];
            $rowsCuenta[] = ['Tipo' => 'SALDO_INICIAL', 'Fecha' => $desde, 'NumAsiento' => 0, 'Cuenta' => $nombreCta, 'Detalle' => 'SALDO ANTERIOR (REAL)', 'Debe'=>0, 'Haber'=>0, 'Fiscal'=>0, 'Fiscal_Badge'=>'', 'Saldo' => $saldoActual, 'Usuario' => '-'];

            $hiddenDebe = 0;
            $hiddenHaber = 0;
            $hayMovimientos = false;

            if(isset($movsPorCuenta[$idCta])) {
                foreach($movsPorCuenta[$idCta] as $r) {
                    $saldoActual += ($r['Debe'] - $r['Haber']); // Saldo siempre se actualiza con TODO
                    
                    // Filtrar visualización
                    if ($filtrar_usuarios && !in_array((string)$r['Usuario'], $lista_usuarios)) {
                        $hiddenDebe += $r['Debe'];
                        $hiddenHaber += $r['Haber'];
                        $hayMovimientos = true; // Hay actividad aunque esté oculta
                        continue;
                    }

                    $hayMovimientos = true;
                    $r['Tipo'] = 'MOVIMIENTO';
                    $r['Saldo'] = $saldoActual; 
                    $r['Fiscal_Badge'] = ($r['Fiscal'] == 1) ? '<span class="badge bg-success">F</span>' : '<span class="badge bg-secondary">N</span>';
                    $rowsCuenta[] = $r;
                }
            }

            // Si hay movimientos ocultos, agregar fila de compensación
            if ($hiddenDebe > 0 || $hiddenHaber > 0) {
                $rowsCuenta[] = [
                    'Tipo' => 'MOVIMIENTO',
                    'Fecha' => '-',
                    'NumAsiento' => 0,
                    'Cuenta' => $nombreCta,
                    'Detalle' => 'MOVIMIENTOS DE OTROS USUARIOS (AGRUPADO)',
                    'Debe' => $hiddenDebe,
                    'Haber' => $hiddenHaber,
                    'Fiscal' => 0,
                    'Fiscal_Badge' => '-',
                    'Saldo' => $saldoActual, // Saldo final real
                    'Usuario' => 'VARIOS'
                ];
            }

            if ($hayMovimientos || !$filtrar_usuarios) {
                $data[] = ['Tipo' => 'SEPARATOR', 'Cuenta' => $nombreCta];
                foreach($rowsCuenta as $row) {
                    $data[] = $row;
                }
            }
        }
        return $data;
    }

    // --- MODO 3: DETALLE GLOBAL ---
    
    $sqlAnt = "SELECT SUM(t.debe) - SUM(t.haber) FROM cccuentastesoreria t WHERE t.fecha < ? $whereCommon";
    $finalParamsAnt = array_merge([$desde], $paramsCommon);
    $stmtAnt = $pdo->prepare($sqlAnt);
    $stmtAnt->execute($finalParamsAnt);
    $saldo = floatval($stmtAnt->fetchColumn() ?: 0);

    $sql = "SELECT t.fecha as Fecha, t.numerodeasiento as NumAsiento, m.medio as Cuenta, 
                   t.descripcion as Detalle, t.debe as Debe, t.haber as Haber, t.fiscal as Fiscal, t.usuario as Usuario 
            FROM cccuentastesoreria t
            INNER JOIN mediosdepago m ON t.idcuenta = m.id
            WHERE t.fecha >= ? AND t.fecha <= ? $whereCommon
            ORDER BY t.fecha ASC, t.id ASC";
    
    $finalParamsMov = array_merge([$desde, $hasta], $paramsCommon);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($finalParamsMov);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = [];
    $data[] = ['Tipo' => 'GLOBAL', 'Fecha' => $desde, 'NumAsiento' => 0, 'Cuenta' => '-', 'Detalle' => 'SALDO ANTERIOR (REAL)', 'Debe'=>0, 'Haber'=>0, 'Fiscal'=>0, 'Saldo'=>$saldo, 'Usuario' => '-'];

    $hiddenDebe = 0;
    $hiddenHaber = 0;

    foreach ($rows as $r) {
        $saldo += ($r['Debe'] - $r['Haber']);

        if ($filtrar_usuarios && !in_array((string)$r['Usuario'], $lista_usuarios)) {
            $hiddenDebe += $r['Debe'];
            $hiddenHaber += $r['Haber'];
            continue;
        }

        $r['Saldo'] = $saldo;
        $r['Fiscal_Badge'] = ($r['Fiscal'] == 1) ? '<span class="badge bg-success">F</span>' : '<span class="badge bg-secondary">N</span>';
        $data[] = $r;
    }

    if ($hiddenDebe > 0 || $hiddenHaber > 0) {
        $data[] = [
            'Tipo' => 'GLOBAL',
            'Fecha' => '-',
            'NumAsiento' => 0,
            'Cuenta' => '-',
            'Detalle' => 'MOVIMIENTOS DE OTROS USUARIOS (AGRUPADO)',
            'Debe' => $hiddenDebe,
            'Haber' => $hiddenHaber,
            'Fiscal' => 0,
            'Saldo' => $saldo,
            'Usuario' => 'VARIOS',
            'Fiscal_Badge' => '-'
        ];
    }

    return $data;
}

try {
    if ($action === 'consultar') {
        $mode = $_POST['solo_saldos'] === 'true' ? 'resumen' : ($_POST['desglosar'] === 'true' ? 'desglosado' : 'detalle');
        echo json_encode(['success' => true, 'data' => getMovimientos($pdo, $_POST), 'mode' => $mode]);
    }
    elseif ($action === 'listar_cuentas') {
        $stmt = $pdo->query("SELECT id, medio FROM mediosdepago WHERE habilitado = 1 ORDER BY medio ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
    elseif ($action === 'listar_usuarios') {
        $stmt = $pdo->query("SELECT DISTINCT usuario FROM cccuentastesoreria WHERE usuario IS NOT NULL AND usuario != '' ORDER BY usuario ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
    }
    elseif ($action === 'exportar_excel') {
        $datos = getMovimientos($pdo, $_GET);
        generarExcelTesoreria($datos, $_GET);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generarExcelTesoreria($datos, $filtros) {
    $filename = "Informe_Tesoreria_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: public");

    $solo_saldos = $filtros['solo_saldos'] === 'true' || $filtros['solo_saldos'] === true;
    $desglosar = $filtros['desglosar'] === 'true' || $filtros['desglosar'] === true;
    $tipoInforme = $solo_saldos ? "RESUMEN DE SALDOS FINALES" : ($desglosar ? "DETALLE AGRUPADO POR CUENTA" : "DETALLE DE MOVIMIENTOS");
    
    $rango = "Hasta: " . date('d/m/Y', strtotime($filtros['hasta']));
    if(!$solo_saldos) $rango = "Desde: " . date('d/m/Y', strtotime($filtros['desde'])) . " " . $rango;

    $lista_usuarios = json_decode($filtros['usuarios'] ?? '[]');
    if (!empty($lista_usuarios) && is_array($lista_usuarios)) {
        $rango .= " | Usuarios: " . implode(', ', $lista_usuarios);
    } else {
        $rango .= " | Usuarios: TODOS";
    }

    $colspan = $solo_saldos ? 3 : 9;

    echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head><meta http-equiv='Content-type' content='text/html;charset=utf-8' />
    <style>
        body { font-family: Arial, sans-serif; font-size: 11px; }
        .header-main { background-color: #000020; color: #00ffff; font-size: 16px; font-weight: bold; text-align: center; border: 1px solid #000; }
        .header-sub { background-color: #0d2b45; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
        .th-col { background-color: #0d2b45; color: #00ffff; border: 1px solid #999; text-align: center; font-weight: bold; }
        .td-row { border: 1px solid #ccc; vertical-align: middle; }
        .num { text-align: right; mso-number-format:\"\#\,\#\#0\.00\"; }
        .date { text-align: center; }
        .separator { background-color: #ffffcc; font-weight: bold; font-size: 12px; border-top: 2px solid #000; }
        .text-danger { color: red; font-weight: bold; }
        .row-group { background-color: #f0f0f0; font-style: italic; color: #555; }
    </style></head><body>
    <table>
        <tr><td colspan='$colspan' class='header-main'>INFORME DE TESORERÍA - WEST IT ERP</td></tr>
        <tr><td colspan='$colspan' class='header-sub'>$tipoInforme | $rango</td></tr>
        <tr><td colspan='$colspan'></td></tr>";

    if ($solo_saldos) {
        echo "<thead><tr><th class='th-col'>ID</th><th class='th-col'>CUENTA</th><th class='th-col'>SALDO FINAL</th></tr></thead><tbody>";
        foreach ($datos as $row) {
            $styleSaldo = $row['Saldo'] < 0 ? "color:red;" : "color:black;";
            echo "<tr><td class='td-row date'>{$row['ID']}</td><td class='td-row'>{$row['Cuenta']}</td><td class='td-row num' style='$styleSaldo'>{$row['Saldo']}</td></tr>";
        }
    } else {
        echo "<thead><tr>
            <th class='th-col'>FECHA</th><th class='th-col'>ASIENTO</th><th class='th-col'>CUENTA</th>
            <th class='th-col' style='width: 300px;'>DETALLE</th><th class='th-col'>DEBE</th><th class='th-col'>HABER</th>
            <th class='th-col'>FISCAL</th><th class='th-col'>USUARIO</th><th class='th-col'>SALDO</th>
        </tr></thead><tbody>";

        foreach ($datos as $row) {
            if (isset($row['Tipo']) && $row['Tipo'] === 'SEPARATOR') {
                echo "<tr><td colspan='9' class='separator'>{$row['Cuenta']}</td></tr>";
            } else {
                $fecha = ($row['Fecha'] && $row['Fecha'] != '-') ? date('d/m/Y', strtotime($row['Fecha'])) : '-';
                $debe = ($row['Debe'] != 0) ? $row['Debe'] : '';
                $haber = ($row['Haber'] != 0) ? $row['Haber'] : '';
                $fiscal = isset($row['Fiscal']) && $row['Fiscal'] == 1 ? 'SI' : 'NO';
                $usuario = $row['Usuario'] ?? '-';
                $styleSaldo = $row['Saldo'] < 0 ? "color:red;" : "color:black;";
                
                $bgRow = "";
                if ($row['Detalle'] == 'SALDO ANTERIOR (REAL)') $bgRow = "background-color:#e6f7ff;";
                if ($row['Usuario'] == 'VARIOS') $bgRow = "background-color:#f8f9fa; font-style:italic;";

                echo "<tr style='$bgRow'>
                    <td class='td-row date'>$fecha</td><td class='td-row date'>" . ($row['NumAsiento'] ?: '-') . "</td>
                    <td class='td-row'>{$row['Cuenta']}</td><td class='td-row'>{$row['Detalle']}</td>
                    <td class='td-row num'>$debe</td><td class='td-row num'>$haber</td>
                    <td class='td-row date'>$fiscal</td><td class='td-row date'>$usuario</td><td class='td-row num' style='$styleSaldo'>{$row['Saldo']}</td>
                </tr>";
            }
        }
    }
    echo "</tbody></table></body></html>";
    exit;
}
?>