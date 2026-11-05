<?php
/**
 * api_imputar_nc_cli.php - LÓGICA DE IMPUTACIÓN DE NOTAS DE CRÉDITO
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pendientes':
            $idcli = (int)$_GET['cliente'];
            
            // 1. Notas de Crédito (tipocomp IN 3, 8, 13, 101) con saldo pendiente
            // Calculamos el pendiente restando lo ya imputado en cobrosfacturasclientes con tablapago = 'tickets'
            $stmtNC = $pdo->prepare("
                SELECT 
                    id, 
                    DATE_FORMAT(fecha, '%d-%m-%Y') AS fecha_f,
                    CONCAT(letracomp, ' ', LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0')) AS numcomp,
                    COALESCE(importectacte, total) - COALESCE(
                        (SELECT SUM(importepagado) FROM cobrosfacturasclientes 
                         WHERE idpago = tickets.id AND tablapago = 'tickets'), 0
                    ) AS pendiente
                FROM tickets
                WHERE cliente = ? 
                AND pagada = 0 
                AND tipocomp IN (3, 8, 13, 101)
                HAVING pendiente > 0.01
                ORDER BY fecha ASC
            ");
            $stmtNC->execute([$idcli]);

            // 2. Facturas de venta (tipocomp NOT IN 3, 8, 13, 101) pendientes
            $stmtF = $pdo->prepare("
                SELECT 
                    id, 
                    DATE_FORMAT(fecha, '%d-%m-%Y') AS fecha_f,
                    CONCAT(letracomp, ' ', LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0')) AS num_factura,
                    COALESCE(importectacte, total) - COALESCE(
                        (SELECT SUM(importepagado) FROM cobrosfacturasclientes 
                         WHERE idfactura = tickets.id), 0
                    ) AS pendiente
                FROM tickets
                WHERE cliente = ?
                AND pagada = 0
                AND tipocomp NOT IN (3, 8, 13, 101)
                HAVING pendiente > 0.01
                ORDER BY fecha ASC
            ");
            $stmtF->execute([$idcli]);

            echo json_encode([
                'success' => true, 
                'notas_credito' => $stmtNC->fetchAll(), 
                'facturas' => $stmtF->fetchAll()
            ]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();

            // Algoritmo de Imputación Cascada
            foreach ($data['notas_credito'] as $nc) {
                $nc_id = $nc['id'];
                $nc_saldo = (float)$nc['pendiente'];

                foreach ($data['facturas'] as &$f) {
                    if ($nc_saldo <= 0.005) break; 
                    if ($f['pendiente'] <= 0.005) continue;

                    $imputacion = min($nc_saldo, $f['pendiente']);

                    // 1. Registrar cobro (tablapago = 'tickets' indica que la fuente es una NC/TDC)
                    $stmt = $pdo->prepare("INSERT INTO cobrosfacturasclientes (idpago, idfactura, importepagado, tablapago) VALUES (?, ?, ?, 'tickets')");
                    $stmt->execute([$nc_id, $f['id'], $imputacion]);

                    $nc_saldo -= $imputacion;
                    $f['pendiente'] -= $imputacion;

                    // 2. Si la factura se cubrió, marcar como pagada
                    if ($f['pendiente'] <= 0.01) {
                        $pdo->prepare("UPDATE tickets SET pagada = 1 WHERE id = ?")->execute([$f['id']]);
                    }
                }

                // 3. Si la Nota de Crédito se agotó, marcar como pagada
                if ($nc_saldo <= 0.01) {
                    $pdo->prepare("UPDATE tickets SET pagada = 1 WHERE id = ?")->execute([$nc_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}