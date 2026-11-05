<?php
/**
 * api_imputar_pagos_prov.php - ENDPOINT PARA IMPUTACIÓN MANUAL PROVEEDORES
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';
require_once 'imputaciones_gestion_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_gestion':
            echo json_encode(['success' => true] + listarGestionImputaciones($pdo, 'proveedor', (int)($_GET['proveedor'] ?? 0), (int)($_GET['pagina'] ?? 1), trim((string)($_GET['buscar'] ?? '')), ($_GET['solo_pendientes'] ?? '') === '1'));
            break;

        case 'buscar_fondos':
            echo json_encode(['success' => true, 'data' => buscarFondosGestion($pdo, 'proveedor', (int)($_GET['proveedor'] ?? 0), trim((string)($_GET['q'] ?? '')))]);
            break;

        case 'modificar_gestion':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
            $d = json_decode(file_get_contents('php://input'), true) ?: [];
            modificarGestionImputacion($pdo, 'proveedor', (int)($d['entidad_id'] ?? 0), (string)($d['accion'] ?? ''),
                (int)($d['factura_id'] ?? 0), (int)($d['pago_id'] ?? 0), (int)($d['imputacion_id'] ?? 0), (float)($d['importe'] ?? 0));
            echo json_encode(['success' => true]);
            break;

        case 'get_pendientes':
            $idprov = $_GET['proveedor'];

            // 1. Pagos a cuenta (saldoacuenta > 0)
            $sqlP = "SELECT id, numcomp, 
                            DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f, 
                            total, saldoacuenta 
                     FROM pagoaproveedores 
                     WHERE proveedor = ? AND estado <> 1000 AND saldoacuenta > 0
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

            $sqlPagos = "SELECT id, fiscal FROM pagoaproveedores WHERE proveedor = ? AND estado <> 1000 AND id IN (" . placeholdersSql($pagoIds) . ") FOR UPDATE";
            $stmtPagos = $pdo->prepare($sqlPagos);
            $stmtPagos->execute(array_merge([$proveedorId], $pagoIds));
            $fiscalPagos = fiscalUnicoDeFilas($stmtPagos->fetchAll(PDO::FETCH_ASSOC), $pagoIds, 'el pago');

            $sqlFacturas = "SELECT id, fiscal FROM facturasdecompra WHERE proveedorid = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
            $stmtFacturas = $pdo->prepare($sqlFacturas);
            $stmtFacturas->execute(array_merge([$proveedorId], $facturaIds));
            $fiscalFacturas = fiscalUnicoDeFilas($stmtFacturas->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante de compra');
            validarMismaCondicionFiscal($fiscalPagos, $fiscalFacturas);

            // Recalcular saldos en el servidor evita imputar importes viejos de
            // una pantalla abierta antes de que otro usuario cambiara vínculos.
            autoImputarSeleccionGestion($pdo, 'proveedor', $proveedorId, $pagoIds, $facturaIds);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
