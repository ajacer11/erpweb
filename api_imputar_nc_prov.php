<?php
/**
 * api_imputar_nc_prov.php - ENDPOINT PARA IMPUTACIÓN MANUAL DE NOTAS DE CRÉDITO
 * Corregido: Uso de ABS() para magnitudes y lógica de guardado robusta.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pendientes':
            $idprov = $_GET['proveedor'];

            // 1. Notas de Crédito con saldo disponible (Usamos ABS para magnitud)
            $sqlNC = "SELECT id, 
                             CONCAT(LPAD(PV, 4, '0'), '-', LPAD(numfact, 8, '0')) as numcomp,
                             DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f, 
                             ABS(total) as total, 
                             ABS(saldoaimputar) as saldo 
                      FROM facturasdecompra 
                      WHERE proveedorid = ? AND saldoaimputar <> 0 AND tipocomp IN (3, 8, 13, 101)
                      ORDER BY fecha ASC";
            $stmtNC = $pdo->prepare($sqlNC);
            $stmtNC->execute([$idprov]);
            $notas = $stmtNC->fetchAll();

            // 2. Facturas de compra pendientes
            $sqlF = "SELECT id, 
                            CONCAT(LPAD(PV, 4, '0'), '-', LPAD(numfact, 8, '0')) as num_factura,
                            DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f,
                            total,
                            total - (SELECT IFNULL(SUM(importepagado), 0) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id) as pendiente
                     FROM facturasdecompra 
                     WHERE proveedorid = ? AND estado = 1 AND tipocomp NOT IN (3, 8, 13, 101)
                     ORDER BY fecha ASC";
            $stmtF = $pdo->prepare($sqlF);
            $stmtF->execute([$idprov]);
            $facturas = $stmtF->fetchAll();

            echo json_encode(['success' => true, 'notas' => $notas, 'facturas' => $facturas]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();

            $notasSeleccionadas = $data['notas'];
            $facturasSeleccionadas = $data['facturas'] ?? [];

            foreach ($notasSeleccionadas as &$nc) {
                // Trabajamos con magnitudes positivas para el algoritmo de resta
                $nc['saldo_trabajo'] = abs(floatval($nc['saldo']));
                
                foreach ($facturasSeleccionadas as &$factura) {
                    if ($nc['saldo_trabajo'] <= 0) break;
                    
                    if (!isset($factura['saldo_trabajo'])) $factura['saldo_trabajo'] = floatval($factura['pendiente']);
                    if ($factura['saldo_trabajo'] <= 0) continue;

                    $montoAImputar = min($nc['saldo_trabajo'], $factura['saldo_trabajo']);

                    // 1. Grabar vínculo (Tipo 'N' para Nota de Crédito)
                    $pdo->prepare("INSERT INTO pagosfacturasproveedores (idpago, idfactura, importepagado, tipo) VALUES (?, ?, ?, 'N')")
                        ->execute([$nc['id'], $factura['id'], $montoAImputar]);

                    $nc['saldo_trabajo'] -= $montoAImputar;
                    $factura['saldo_trabajo'] -= $montoAImputar;

                    // 2. Si la factura se saldó, marcar como pagada (estado 2)
                    if ($factura['saldo_trabajo'] <= 0.01) { // Margen de centavos
                        $pdo->prepare("UPDATE facturasdecompra SET estado = 2 WHERE id = ?")->execute([$factura['id']]);
                    }
                }

                // 3. Actualizar el saldo de la Nota de Crédito. 
                // Si el total original era negativo, el saldo resultante debe ser negativo.
                $stmtTotal = $pdo->prepare("SELECT total FROM facturasdecompra WHERE id = ?");
                $stmtTotal->execute([$nc['id']]);
                $totalOriginal = $stmtTotal->fetchColumn();
                $nuevoSaldoDB = ($totalOriginal < 0) ? ($nc['saldo_trabajo'] * -1) : $nc['saldo_trabajo'];

                $nuevoEstadoNC = (abs($nuevoSaldoDB) <= 0.01) ? 2 : 1;
                $pdo->prepare("UPDATE facturasdecompra SET saldoaimputar = ?, estado = ? WHERE id = ?")
                    ->execute([$nuevoSaldoDB, $nuevoEstadoNC, $nc['id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}