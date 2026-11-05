<?php
/**
 * api_imputar_cobros.php - LÓGICA DE IMPUTACIÓN MUCHOS A MUCHOS
 */
require_once 'check_api_access.php';
require_once 'fiscal_imputacion_helpers.php';
require_once 'imputaciones_gestion_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_gestion':
            echo json_encode(['success' => true] + listarGestionImputaciones($pdo, 'cliente', (int)($_GET['cliente'] ?? 0), (int)($_GET['pagina'] ?? 1), trim((string)($_GET['buscar'] ?? '')), ($_GET['solo_pendientes'] ?? '') === '1'));
            break;

        case 'buscar_fondos':
            echo json_encode(['success' => true, 'data' => buscarFondosGestion($pdo, 'cliente', (int)($_GET['cliente'] ?? 0), trim((string)($_GET['q'] ?? '')))]);
            break;

        case 'modificar_gestion':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
            $d = json_decode(file_get_contents('php://input'), true) ?: [];
            modificarGestionImputacion($pdo, 'cliente', (int)($d['entidad_id'] ?? 0), (string)($d['accion'] ?? ''),
                (int)($d['factura_id'] ?? 0), (int)($d['pago_id'] ?? 0), (int)($d['imputacion_id'] ?? 0), (float)($d['importe'] ?? 0));
            echo json_encode(['success' => true]);
            break;

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
                        COALESCE(importectacte, total) - COALESCE(
                            (SELECT SUM(importepagado)
                            FROM cobrosfacturasclientes
                            WHERE idfactura = tickets.id), 0
                        ) AS pendiente
                    FROM tickets
                    WHERE cliente = ?
                    AND pagada = 0
                    AND tipocomp NOT IN (3, 8, 13, 101)
                    AND (COALESCE(importectacte, total) - COALESCE(
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

            $sqlPagos = "SELECT p.id, cc.fiscal FROM pagos p INNER JOIN ccclientes cc ON cc.compasoc = p.id AND cc.tabla = 'pagos' WHERE p.cliente = ? AND p.estado <> 1000 AND p.id IN (" . placeholdersSql($pagoIds) . ") FOR UPDATE";
            $stmtPagos = $pdo->prepare($sqlPagos);
            $stmtPagos->execute(array_merge([$clienteId], $pagoIds));
            $fiscalPagos = fiscalUnicoDeFilas($stmtPagos->fetchAll(PDO::FETCH_ASSOC), $pagoIds, 'el cobro');

            $sqlFacturas = "SELECT id, (tipocomp < 100) AS fiscal FROM tickets WHERE cliente = ? AND id IN (" . placeholdersSql($facturaIds) . ")";
            $stmtFacturas = $pdo->prepare($sqlFacturas);
            $stmtFacturas->execute(array_merge([$clienteId], $facturaIds));
            $fiscalFacturas = fiscalUnicoDeFilas($stmtFacturas->fetchAll(PDO::FETCH_ASSOC), $facturaIds, 'el comprobante');
            validarMismaCondicionFiscal($fiscalPagos, $fiscalFacturas);

            // La imputación rápida usa los saldos actuales de la base; el navegador
            // sólo indica el orden de los comprobantes seleccionados.
            autoImputarSeleccionGestion($pdo, 'cliente', $clienteId, $pagoIds, $facturaIds);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
