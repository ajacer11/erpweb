<?php
/**
 * api_dashboard1.php - ENDPOINT PARA ALERTAS DEL DASHBOARD SIMPLIFICADO
 * Solo procesa las listas críticas: Stock, Cobros Vencidos y Pagos Vencidos.
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

function safeQueryAll($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$data = [
    'success' => true,
    'alertas' => [
        'stock' => [],
        'cobros' => [],
        'pagos' => []
    ]
];

try {
    // 1. ALERTA DE STOCK (Faltantes y Críticos)
    $sqlStock = "
        SELECT codigo, descripcion, Stock 
        FROM alb_productos 
        WHERE habilitado = 1 AND Stock <= COALESCE(StockMin, 0) 
        ORDER BY Stock ASC, descripcion ASC 
        LIMIT 30
    ";
    $data['alertas']['stock'] = safeQueryAll($pdo, $sqlStock);

    // 2. ALERTA DE COBROS VENCIDOS (Clientes)
    // Calcula el saldo pendiente y solo trae los que vencieron antes de HOY
    // PERMISO ESPECIAL: un usuario restringido a "un solo vendedor" solo ve las
    // facturas vencidas de sus propios clientes/ventas.
    $paramsCobros = [];
    $filtroVendCobros = '';
    if (esVendedorRestringido()) {
        $filtroVendCobros = " AND t.vendedor = ? ";
        $paramsCobros[] = idVendedorRestringido();
    }
    $sqlCobros = "
        SELECT
            t.id,
            COALESCE((SELECT razon FROM alb_clientes WHERE id = t.cliente), 'Consumidor Final') as cliente,
            DATE_FORMAT(t.vencimiento, '%d/%m/%Y') as fecha,
            (COALESCE(t.importectacte, t.total) - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = t.id), 0)) as monto
        FROM tickets t
        WHERE t.pagada = 0 AND t.tipocomp NOT IN (3, 8, 13, 101) AND t.vencimiento < CURDATE() $filtroVendCobros
        HAVING monto > 0
        ORDER BY t.vencimiento ASC
        LIMIT 30
    ";
    $data['alertas']['cobros'] = safeQueryAll($pdo, $sqlCobros, $paramsCobros);

    // 3. ALERTA DE PAGOS VENCIDOS (Proveedores)
    // Calcula el saldo pendiente y solo trae los que vencieron antes de HOY
    $sqlPagos = "
        SELECT 
            f.id, 
            COALESCE((SELECT razon FROM Proveedores WHERE id = f.proveedorid), 'Proveedor') as proveedor, 
            DATE_FORMAT(f.fechavencimiento, '%d/%m/%Y') as fecha, 
            (f.total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = f.id), 0)) as monto
        FROM facturasdecompra f 
        WHERE f.estado = 1 AND f.fechavencimiento < CURDATE()
        HAVING monto > 0
        ORDER BY f.fechavencimiento ASC
        LIMIT 30
    ";
    $data['alertas']['pagos'] = safeQueryAll($pdo, $sqlPagos);

} catch (Exception $e) {
    $data['success'] = false;
    $data['error'] = 'Error procesando alertas: ' . $e->getMessage();
}

echo json_encode($data);
?>