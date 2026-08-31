<?php
/**
 * api_imputar_pagos_prov.php - ENDPOINT PARA IMPUTACIÓN MANUAL PROVEEDORES
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_pendientes':
            $idprov = $_GET['proveedor'];

            // 1. Pagos a cuenta (saldoacuenta > 0)
            $sqlP = "SELECT id, numcomp, 
                            DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f, 
                            total, saldoacuenta 
                     FROM pagoaproveedores 
                     WHERE proveedor = ? AND saldoacuenta > 0 
                     ORDER BY fecha ASC";
            $stmtP = $pdo->prepare($sqlP);
            $stmtP->execute([$idprov]);
            $pagos = $stmtP->fetchAll();

            // 2. Facturas pendientes (estado 1)
            $sqlF = "SELECT id, 
                            CONCAT(LPAD(PV, 4, '0'), '-', LPAD(numfact, 8, '0')) as num_factura,
                            DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f,
                            total,
                            total - (SELECT IFNULL(SUM(importepagado), 0) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id) as pendiente,
                            fiscal
                     FROM facturasdecompra 
                     WHERE proveedorid = ? AND estado = 1 AND tipocomp NOT IN (3, 8, 13, 101)
                     ORDER BY fecha ASC";
            $stmtF = $pdo->prepare($sqlF);
            $stmtF->execute([$idprov]);
            $facturas = $stmtF->fetchAll();

            echo json_encode(['success' => true, 'pagos' => $pagos, 'facturas' => $facturas]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();

            $pagosSeleccionados = $data['pagos'];
            $facturasSeleccionadas = $data['facturas'];
            $proveedorId = (int)($data['proveedor_id'] ?? 0);
            $pagoIds = idsSeleccionados($pagosSeleccionados);
            $facturaIds = idsSeleccionados($facturasSeleccionadas);
            if (!$pagoIds || !$facturaIds) {
                throw new Exception('Debe seleccionar al menos un pago y un comprobante para imputar.');
            }

            $sqlPagos = "SELECT id, fiscal FROM pagoaproveedores WHERE proveedor = ? AND id IN (" . placeholdersSql($pagoIds) . ")";
            $stmtPagos = $pdo->prepare($sqlPagos);
            $stmtPagos->execute(array_merge([$proveedorId], $pagoIds));
            $fiscalPagos = fiscalUnicoDeFilas($stmtPagos->fetchAll(PDO::FETCH_ASSOC), $pagoIds, 'el pago');

            $sqlFacturas = "SELECT id, fiscal FROM facturasdecompra WHERE proveedorid = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
            $stmtFacturas = $pdo->prepare($sqlFacturas);
            $stmtFacturas->execute(array_merge([$proveedorId], $facturaIds));
            $fiscalFacturas = fiscalUnicoDeFilas($stmtFacturas->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante de compra');
            validarMismaCondicionFiscal($fiscalPagos, $fiscalFacturas);

            // Mapeamos los saldos actuales para trabajar en memoria antes de impactar
            // Similar a la lógica de dgv en VB.NET
            foreach ($pagosSeleccionados as &$pago) {
                $pago['saldo_trabajo'] = floatval($pago['saldoacuenta']);
                
                foreach ($facturasSeleccionadas as &$factura) {
                    if ($pago['saldo_trabajo'] <= 0) break;
                    
                    // Saldo de la factura en este paso de la iteración
                    if (!isset($factura['saldo_trabajo'])) $factura['saldo_trabajo'] = floatval($factura['pendiente']);
                    if ($factura['saldo_trabajo'] <= 0) continue;

                    $montoAImputar = min($pago['saldo_trabajo'], $factura['saldo_trabajo']);

                    // 1. Insertar relación
                    $pdo->prepare("INSERT INTO pagosfacturasproveedores (idpago, idfactura, importepagado, tipo) VALUES (?, ?, ?, 'P')")
                        ->execute([$pago['id'], $factura['id'], $montoAImputar]);

                    // 2. Descontar saldos de trabajo
                    $pago['saldo_trabajo'] -= $montoAImputar;
                    $factura['saldo_trabajo'] -= $montoAImputar;

                    // 3. Actualizar Factura si quedó en 0
                    if ($factura['saldo_trabajo'] <= 0) {
                        $pdo->prepare("UPDATE facturasdecompra SET estado = 2 WHERE id = ?")->execute([$factura['id']]);
                    }
                }

                // 4. Actualizar el pago con el nuevo saldo a cuenta (o 0 si se agotó)
                $pdo->prepare("UPDATE pagoaproveedores SET saldoacuenta = ? WHERE id = ?")
                    ->execute([$pago['saldo_trabajo'], $pago['id']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
