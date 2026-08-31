<?php
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

// BUSCADOR SELECT2
if ($action === 'get_proveedores') {
    $q = $_GET['q'] ?? '';
    $sql = "SELECT id, razon FROM Proveedores WHERE razon LIKE ? ORDER BY razon ASC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$q%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// OBTENCIÓN DE DATOS
if ($action === 'get_data') {
    $solo_saldos = $_GET['solo_saldos'] == '1';
    $proveedor_id = (int)($_GET['proveedor_id'] ?? 0);
    $por_proveedor = ($_GET['por_proveedor'] ?? '0') === '1';
    $fisco_val = $_GET['fisco'];
    $fisco_sql = ($fisco_val !== '') ? " AND m.fiscal = $fisco_val " : "";
    $filtro_proveedor = ($por_proveedor && $proveedor_id > 0) ? " AND m.proveedor = $proveedor_id " : "";
    
    if ($solo_saldos) {
        $sql = "SELECT 
                    m.proveedor as idproveedor, 
                    DATE_FORMAT(MAX(fecha), '%d/%m/%Y') AS Fecha, 
                    (SELECT razon FROM Proveedores WHERE id = m.proveedor) AS Proveedor, 
                    ROUND(Sum(facturas),2) AS Facturas, 
                    ROUND(Sum(pagos),2) AS Pagos, 
                    ROUND(sum(facturas)-sum(pagos),2) AS Saldo 
                FROM moviemientosccproveedores m 
                WHERE m.id > 0 $filtro_proveedor $fisco_sql
                GROUP by m.proveedor 
                HAVING Saldo <> 0 
                ORDER BY Proveedor ASC";
        $stmt = $pdo->query($sql);
        $data = $stmt->fetchAll();
    } else {
        $entre_fechas = $_GET['entre_fechas'] == '1';
        $desde = $_GET['desde'];
        $hasta = $_GET['hasta'];
        $fecha_sql = $entre_fechas ? " AND (m.fecha BETWEEN '$desde' AND '$hasta') " : "";
        $where_prov = ($proveedor_id > 0) ? " AND m.proveedor = $proveedor_id " : "";
        $orden_sql = "m.fecha ASC, m.id ASC";

        $comprobante_case = "
            CASE m.tabla 
                WHEN 'facturasdecompra' THEN (
                    SELECT CASE tipocomp 
                        WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 6 THEN 'FCB' WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' WHEN 11 THEN 'FCC' WHEN 13 THEN 'NCC' WHEN 1000 THEN 'REMITO' ELSE 'Otro' 
                    END 
                    FROM facturasdecompra WHERE id = m.compasoc
                ) 
                WHEN 'recibos' THEN 'REC' 
                WHEN 'pagoaproveedores' THEN 'Pago'
            END AS Tipo_Comprobante,
            CASE m.tabla 
                WHEN 'facturasdecompra' THEN (SELECT numfact FROM facturasdecompra WHERE id = m.compasoc) 
                WHEN 'pagoaproveedores' THEN (SELECT id FROM pagoaproveedores WHERE numcomp = m.compasoc)
            END AS Num_Comprobante";

        $data = [];
        
        // --- LÓGICA DE SALDO ANTERIOR (Solo si hay proveedor seleccionado y filtro de fechas) ---
        if ($entre_fechas && $proveedor_id > 0) {
            // Obtenemos el nombre del proveedor para que la fila de saldo anterior no rompa el agrupamiento
            $stmtProv = $pdo->prepare("SELECT razon FROM Proveedores WHERE id = ?");
            $stmtProv->execute([$proveedor_id]);
            $nombreProv = $stmtProv->fetchColumn();

            $stmtAnt = $pdo->prepare("SELECT SUM(facturas) - SUM(pagos) as anterior FROM moviemientosccproveedores m WHERE proveedor = ? AND fecha < ? $fisco_sql");
            $stmtAnt->execute([$proveedor_id, $desde]);
            $resAnt = $stmtAnt->fetch();
            $saldo_ant = (float)($resAnt['anterior'] ?? 0);

            $data[] = [
                'id' => 0,
                'idproveedor' => $proveedor_id,
                'Fecha' => date('d/m/Y', strtotime($desde)),
                'Proveedor' => $nombreProv ?: '',
                'Tipo_Comprobante' => 'SALDO',
                'Num_Comprobante' => 'ANTERIOR',
                'Facturas' => $saldo_ant >= 0 ? abs($saldo_ant) : 0,
                'Pagos' => $saldo_ant < 0 ? abs($saldo_ant) : 0
            ];
        }

        $sql = "SELECT 
                    m.id,
                    m.proveedor AS idproveedor,
                    DATE_FORMAT(m.fecha, '%d/%m/%Y') AS Fecha, 
                    (SELECT razon FROM Proveedores WHERE id = m.proveedor) AS Proveedor,
                    $comprobante_case, 
                    ROUND(m.facturas,2) AS Facturas, 
                    ROUND(m.pagos,2) AS Pagos 
                FROM moviemientosccproveedores m 
                WHERE m.id > 0 $where_prov $fecha_sql $fisco_sql
                ORDER BY $orden_sql";
        
        $stmtMain = $pdo->query($sql);
        $movimientos = $stmtMain->fetchAll();
        $data = array_merge($data, $movimientos);

        $saldos_por_proveedor = [];
        foreach ($data as &$movimiento) {
            $idproveedor = (int)($movimiento['idproveedor'] ?? $proveedor_id);
            if (!isset($saldos_por_proveedor[$idproveedor])) {
                $saldos_por_proveedor[$idproveedor] = 0.0;
            }
            $saldos_por_proveedor[$idproveedor] += (float)$movimiento['Facturas'] - (float)$movimiento['Pagos'];
            $movimiento['Saldo'] = round($saldos_por_proveedor[$idproveedor], 2);
        }
        unset($movimiento);
    }

    echo json_encode(['success' => true, 'data' => $data, 'solo_saldos' => $solo_saldos]);
}
?>
