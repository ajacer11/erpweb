<?php
/**
 * api_composicion_saldos_cli.php - PROCESAMIENTO DE DESGLOSE DE SALDOS
 */
require_once 'check_api_access.php';

$cliente_id = (int)($_GET['cliente_id'] ?? 0);

if (!$cliente_id) {
    echo json_encode(['success' => false, 'error' => 'ID de cliente no válido']);
    exit;
}

try {
    // 1. PAGOS A CUENTA (Pagos con saldo a imputar)
    $stmtP = $pdo->prepare("SELECT id, fecha, (imp1 + imp2) as total, saldoaimputar as pendiente 
                            FROM pagos WHERE cliente = ? AND saldoaimputar > 0.01 AND estado <> 1000 ORDER BY fecha ASC");
    $stmtP->execute([$cliente_id]);
    $pagos_raw = $stmtP->fetchAll();
    $pagos = array_map(function($r) {
        return ['fecha' => $r['fecha'], 'fecha_f' => date('d/m/Y', strtotime($r['fecha'])), 'numero' => 'REC ' . $r['id'], 'pendiente' => $r['pendiente']];
    }, $pagos_raw);

    // 2. NOTAS DE CRÉDITO PENDIENTES (Tickets tipo 3, 8, 13, 101 sin liquidar)
    $stmtNC = $pdo->prepare("
        SELECT id, fecha, CONCAT(letracomp,' ',LPAD(PtoVta,4,'0'),'-',LPAD(numcomp,8,'0')) as numero,
               total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idpago = tickets.id AND tablapago = 'tickets'), 0) as pendiente
        FROM tickets WHERE cliente = ? AND pagada = 0 AND tipocomp IN (3, 8, 13, 101)
        HAVING pendiente > 0.01 ORDER BY fecha ASC");
    $stmtNC->execute([$cliente_id]);
    $nc_raw = $stmtNC->fetchAll();
    $nc = array_map(function($r) {
        return ['fecha' => $r['fecha'], 'fecha_f' => date('d/m/Y', strtotime($r['fecha'])), 'numero' => $r['numero'], 'pendiente' => $r['pendiente']];
    }, $nc_raw);

    // 3. FACTURAS PENDIENTES
    $stmtF = $pdo->prepare("
        SELECT id, fecha, CONCAT(letracomp,' ',LPAD(PtoVta,4,'0'),'-',LPAD(numcomp,8,'0')) as numero,
               total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0) as pendiente
        FROM tickets WHERE cliente = ? AND pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101)
        HAVING pendiente > 0.01 ORDER BY fecha ASC");
    $stmtF->execute([$cliente_id]);
    $facturas_raw = $stmtF->fetchAll();
    $facturas = array_map(function($r) {
        return ['fecha' => $r['fecha'], 'fecha_f' => date('d/m/Y', strtotime($r['fecha'])), 'numero' => $r['numero'], 'pendiente' => $r['pendiente']];
    }, $facturas_raw);

    // 4. CONSTRUIR CONSOLIDADO
    $consolidado = [];
    foreach($pagos as $p) $consolidado[] = ['fecha' => $p['fecha'], 'fecha_f' => $p['fecha_f'], 'tipo' => 'PAGO A CUENTA', 'numero' => $p['numero'], 'debe' => 0, 'haber' => $p['pendiente']];
    foreach($nc as $n) $consolidado[] = ['fecha' => $n['fecha'], 'fecha_f' => $n['fecha_f'], 'tipo' => 'NOTA DE CRÉDITO', 'numero' => $n['numero'], 'debe' => 0, 'haber' => $n['pendiente']];
    foreach($facturas as $f) $consolidado[] = ['fecha' => $f['fecha'], 'fecha_f' => $f['fecha_f'], 'tipo' => 'FACTURA/DEUDA', 'numero' => $f['numero'], 'debe' => $f['pendiente'], 'haber' => 0];

    // Ordenar consolidado por fecha
    usort($consolidado, function($a, $b) { return strtotime($a['fecha']) <=> strtotime($b['fecha']); });

    // Calcular Saldo Acumulado Fila a Fila
    $acum = 0;
    foreach($consolidado as &$item) {
        $acum += ($item['debe'] - $item['haber']);
        $item['saldo_acum'] = $acum;
    }

    echo json_encode([
        'success' => true,
        'pagos' => $pagos,
        'nc' => $nc,
        'facturas' => $facturas,
        'consolidado' => $consolidado,
        'saldo_final' => $acum
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}