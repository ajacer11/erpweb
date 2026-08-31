<?php
/**
 * api_imputar_cobros.php - LÓGICA DE IMPUTACIÓN MUCHOS A MUCHOS
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pendientes':
            $idcli = $_GET['cliente'];
            // 1. Pagos con saldo a imputar
            $stmtP = $pdo->prepare("SELECT id, DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f, (imp1 + imp2) as total, saldoaimputar 
                                   FROM pagos WHERE cliente = ? AND saldoaimputar > 0 AND estado <> 1000 ORDER BY fecha ASC");
            $stmtP->execute([$idcli]);

            // 2. Facturas pendientes
            $stmtF = $pdo->prepare("
                    SELECT 
                        id, 
                        DATE_FORMAT(fecha, '%d-%m-%Y') AS fecha_f,
                        CONCAT(LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0')) AS num_factura,
                        total - COALESCE(
                            (SELECT SUM(importepagado)
                            FROM cobrosfacturasclientes
                            WHERE idfactura = tickets.id), 0
                        ) AS pendiente
                    FROM tickets
                    WHERE cliente = ?
                    AND pagada = 0
                    AND tipocomp NOT IN (3, 8, 13, 101)
                    AND (total - COALESCE(
                            (SELECT SUM(importepagado)
                            FROM cobrosfacturasclientes
                            WHERE idfactura = tickets.id), 0
                        ) > 0)
                    ORDER BY fecha ASC
                ");
            $stmtF->execute([$idcli]);


            echo json_encode(['success' => true, 'pagos' => $stmtP->fetchAll(), 'facturas' => $stmtF->fetchAll()]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();

            $clienteId = (int)($data['cliente_id'] ?? 0);
            $pagoIds = idsSeleccionados($data['pagos'] ?? []);
            $facturaIds = idsSeleccionados($data['facturas'] ?? []);
            if (!$pagoIds || !$facturaIds) {
                throw new Exception('Debe seleccionar al menos un cobro y un comprobante para imputar.');
            }

            $sqlPagos = "SELECT p.id, cc.fiscal FROM pagos p INNER JOIN ccclientes cc ON cc.compasoc = p.id AND cc.tabla = 'pagos' WHERE p.cliente = ? AND p.id IN (" . placeholdersSql($pagoIds) . ")";
            $stmtPagos = $pdo->prepare($sqlPagos);
            $stmtPagos->execute(array_merge([$clienteId], $pagoIds));
            $fiscalPagos = fiscalUnicoDeFilas($stmtPagos->fetchAll(PDO::FETCH_ASSOC), $pagoIds, 'el cobro');

            $sqlFacturas = "SELECT id, (tipocomp < 100) AS fiscal FROM tickets WHERE cliente = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
            $stmtFacturas = $pdo->prepare($sqlFacturas);
            $stmtFacturas->execute(array_merge([$clienteId], $facturaIds));
            $fiscalFacturas = fiscalUnicoDeFilas($stmtFacturas->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante');
            validarMismaCondicionFiscal($fiscalPagos, $fiscalFacturas);

            // Algoritmo de Imputación Cascada (Similar a VB.NET)
            // Recorremos cada pago seleccionado
            foreach ($data['pagos'] as $p) {
                $pago_id = $p['id'];
                $pago_saldo = (float) $p['saldoaimputar'];

                // Para cada pago, recorremos las facturas seleccionadas (que aún tengan saldo)
                foreach ($data['facturas'] as &$f) {
                    if ($pago_saldo <= 0)
                        break; // Si el pago se agotó, vamos al siguiente pago
                    if ($f['pendiente'] <= 0)
                        continue; // Si la factura ya se cubrió, vamos a la siguiente factura

                    $imputacion = min($pago_saldo, $f['pendiente']);

                    // 1. Registrar cobro
                    $pdo->prepare("INSERT INTO cobrosfacturasclientes (idpago, idfactura, importepagado) VALUES (?, ?, ?)")
                        ->execute([$pago_id, $f['id'], $imputacion]);

                    // 2. Descontar de los saldos de trabajo
                    $pago_saldo -= $imputacion;
                    $f['pendiente'] -= $imputacion;

                    // 3. Si la factura se completó, marcar como pagada
                    if ($f['pendiente'] <= 0.01) { // Margen por redondeo
                        $pdo->prepare("UPDATE tickets SET pagada = 1 WHERE id = ?")->execute([$f['id']]);
                    }
                }

                // 4. Actualizar el pago con su nuevo saldo remanente
                $pdo->prepare("UPDATE pagos SET saldoaimputar = ? WHERE id = ?")->execute([$pago_saldo, $pago_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
