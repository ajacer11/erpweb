<?php
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

// BUSCADOR SELECT2 (Corregido para responder a la búsqueda 'q')
if ($action === 'get_clientes') {
    $q = $_GET['q'] ?? '';
    $sql = "SELECT id, razon FROM alb_clientes WHERE razon LIKE ?";
    // PERMISO ESPECIAL: filtrar solo clientes del vendedor asignado
    if ((int)($_SESSION['pe_unvendedor'] ?? 0) === 1) {
        $sql .= " AND vendedorid = " . (int)$_SESSION['pe_idvendedor'];
    }
    $sql .= " ORDER BY razon ASC LIMIT 50";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$q%"]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// OBTENCIÓN DE DATOS
if ($action === 'get_data') {
    $solo_saldos = $_GET['solo_saldos'] == '1';
    $cliente_id = (int)($_GET['cliente_id'] ?? 0);
    $por_cliente = ($_GET['por_cliente'] ?? '0') === '1';
    $fisco_val = $_GET['fisco'];
    $fisco_sql = ($fisco_val !== '') ? " AND fiscal = $fisco_val " : "";
    $filtro_cliente = ($por_cliente && $cliente_id > 0) ? " AND ccclientes.cliente = $cliente_id " : "";

    // PERMISO ESPECIAL: solo clientes del vendedor asignado
    $filtro_vendedor = "";
    if ((int)($_SESSION['pe_unvendedor'] ?? 0) === 1) {
        $idv = (int)$_SESSION['pe_idvendedor'];
        $filtro_vendedor = " AND ccclientes.cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = $idv) ";
    }

    if ($solo_saldos) {
        $sql = "SELECT 
                    ccclientes.cliente as idcliente, 
                    DATE_FORMAT(MAX(fecha), '%d/%m/%Y') AS 'Fecha', 
                    a.razon AS 'Cliente', 
                    ROUND(Sum(debe),2) AS 'Facturas', 
                    ROUND(Sum(haber),2) AS 'Cobros', 
                    ROUND(sum(debe)-sum(haber),2) AS 'Saldo' 
                FROM ccclientes 
                INNER JOIN alb_clientes a ON ccclientes.cliente = a.id
                WHERE ccclientes.id > 0 $filtro_cliente $fisco_sql $filtro_vendedor
                GROUP by ccclientes.cliente 
                HAVING Saldo <> 0
                ORDER BY a.razon ASC";
    } else {
        $entre_fechas = $_GET['entre_fechas'] == '1';
        $desde = $_GET['desde'];
        $hasta = $_GET['hasta'];
        
        $fecha_sql = $entre_fechas ? " AND (fecha BETWEEN '$desde' AND '$hasta') " : "";
        $where_cli = ($cliente_id > 0) ? " AND ccclientes.cliente = $cliente_id " : "";

        $comprobante_case = "
            CASE ccclientes.tabla 
                WHEN 'tickets' THEN (SELECT CASE tipocomp WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 6 THEN 'FCB' WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 11 THEN 'FCC' ELSE 'Otro' END FROM tickets WHERE id = ccclientes.compasoc) 
                WHEN 'pagos' THEN 'REC' 
            END AS 'Tipo_Comprobante',
            CASE ccclientes.tabla 
                WHEN 'tickets' THEN (SELECT CONCAT(letracomp, ' ', LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0')) FROM tickets WHERE id = ccclientes.compasoc) 
                WHEN 'pagos' THEN CONCAT('RECIBO ', LPAD(compasoc, 9, '0')) 
            END AS 'Num_Comprobante'";

        $orden_sql = "ccclientes.fecha ASC, ccclientes.id ASC";

        $sql = "SELECT 
                    ccclientes.id,
                    ccclientes.cliente AS idcliente,
                    DATE_FORMAT(fecha, '%d/%m/%Y') AS 'Fecha', 
                    (SELECT razon FROM alb_clientes WHERE id = ccclientes.cliente) AS 'Cliente',
                    $comprobante_case, 
                    ROUND(debe,2) AS 'Facturas', 
                    ROUND(haber,2) AS 'Cobros' 
                FROM ccclientes 
                WHERE id > 0 $where_cli $fecha_sql $fisco_sql $filtro_vendedor
                ORDER BY $orden_sql";

        $data = [];
        
        // --- LÓGICA DE SALDO ANTERIOR ---
        if ($entre_fechas && $cliente_id > 0) {
            $stmtAnt = $pdo->prepare("SELECT SUM(debe) - SUM(haber) as anterior FROM ccclientes WHERE cliente = ? AND fecha < ? $fisco_sql");
            $stmtAnt->execute([$cliente_id, $desde]);
            $resAnt = $stmtAnt->fetch();
            $saldo_ant = (float)($resAnt['anterior'] ?? 0);

            // Insertamos la fila virtual de Saldo Anterior
            $data[] = [
                'id' => 0,
                'idcliente' => $cliente_id,
                'Fecha' => date('d/m/Y', strtotime($desde)),
                'Cliente' => '', // No repetimos el nombre para que visualmente destaque
                'Tipo_Comprobante' => 'SALDO',
                'Num_Comprobante' => 'ANTERIOR',
                'Facturas' => $saldo_ant >= 0 ? abs($saldo_ant) : 0,
                'Cobros' => $saldo_ant < 0 ? abs($saldo_ant) : 0
            ];
        }
    }

    try {
        if (!isset($data)) {
            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll();
        } else {
            // Si ya teníamos el saldo anterior, ejecutamos la query principal y mergeamos
            $stmt = $pdo->query($sql);
            $movimientos = $stmt->fetchAll();
            $data = array_merge($data, $movimientos);
        }

        if (!$solo_saldos) {
            $saldos_por_cliente = [];
            foreach ($data as &$movimiento) {
                $idcliente = (int)($movimiento['idcliente'] ?? $cliente_id);
                if (!isset($saldos_por_cliente[$idcliente])) {
                    $saldos_por_cliente[$idcliente] = 0.0;
                }
                $saldos_por_cliente[$idcliente] += (float)$movimiento['Facturas'] - (float)$movimiento['Cobros'];
                $movimiento['Saldo'] = round($saldos_por_cliente[$idcliente], 2);
            }
            unset($movimiento);
        }

        echo json_encode([
            'success' => true, 
            'data' => $data,
            'solo_saldos' => $solo_saldos
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
