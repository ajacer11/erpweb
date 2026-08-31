<?php
/**
 * api_dashboard.php - ENDPOINT PARA MÉTRICAS DEL DASHBOARD
 * Retorna todos los KPIs financieros y operativos usando las tablas legacy exactas.
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

// Función de seguridad para evitar cuelgues si un dato falta o es nulo
function safeQueryValue($pdo, $sql, $default = 0) {
    try {
        $stmt = $pdo->query($sql);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (float)$val : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function safeQueryAll($pdo, $sql) {
    try {
        $stmt = $pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$data = [
    'success' => true,
    'kpis' => [],
    'charts' => [],
    'lists' => []
];

try {
    /* =========================================================
       1. KPIS OPERATIVOS (Clientes, Productos, Stock, Ventas)
       ========================================================= */
    $data['kpis']['total_clientes'] = safeQueryValue($pdo, "SELECT COUNT(*) FROM alb_clientes WHERE habilitado = 1");
    $data['kpis']['total_productos'] = safeQueryValue($pdo, "SELECT COUNT(*) FROM alb_productos WHERE habilitado = 1");
    $data['kpis']['stock_bajo'] = safeQueryValue($pdo, "SELECT COUNT(*) FROM alb_productos WHERE Stock <= COALESCE(StockMin, 0) AND habilitado = 1");
    $data['kpis']['valorizacion'] = safeQueryValue($pdo, "SELECT SUM(Stock * preciocosto) FROM alb_productos WHERE habilitado = 1 AND Stock > 0");
    
    // Operaciones (Ventas Hoy)
    $data['kpis']['ventas_hoy'] = safeQueryValue($pdo, "SELECT COUNT(*) FROM tickets WHERE DATE(fecha) = CURDATE()");
    
    // Ticket Promedio del Trimestre (Últimos 3 meses)
    $data['kpis']['ticket_promedio'] = safeQueryValue($pdo, "
        SELECT AVG(total) FROM tickets 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH) AND total > 0
    ");

    /* =========================================================
       2. KPIS FINANCIEROS (Basados en archivos de Cta Cte)
       ========================================================= */
       
    // SALDOS TOTALES (Extraídos del cálculo neto de Cta Cte)
    $data['kpis']['saldos_cobrar'] = safeQueryValue($pdo, "SELECT SUM(debe) - SUM(haber) FROM ccclientes");
    $data['kpis']['saldos_pagar']  = safeQueryValue($pdo, "SELECT SUM(facturas) - SUM(pagos) FROM moviemientosccproveedores");

    // VENCIDOS (Extraídos del cálculo de comprobantes pendientes y saldos impagos)
    $data['kpis']['vencidos_cobrar'] = safeQueryValue($pdo, "
        SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0)) 
        FROM tickets 
        WHERE pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) AND vencimiento < CURDATE()
    ");

    $data['kpis']['vencidos_pagar'] = safeQueryValue($pdo, "
        SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0)) 
        FROM facturasdecompra 
        WHERE estado = 1 AND fechavencimiento < CURDATE()
    ");

    // VENCIMIENTOS EXACTOS DE HOY
    $data['kpis']['hoy_cobros'] = safeQueryValue($pdo, "
        SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0)) 
        FROM tickets 
        WHERE pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) AND vencimiento = CURDATE()
    ");

    $data['kpis']['hoy_pagos'] = safeQueryValue($pdo, "
        SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0)) 
        FROM facturasdecompra 
        WHERE estado = 1 AND fechavencimiento = CURDATE()
    ");


    /* =========================================================
       3. GRÁFICOS Y LISTAS DE ACCIÓN GENERALES
       ========================================================= */
    // Gráfico de Rubros (Doughnut)
    $data['charts']['rubros'] = safeQueryAll($pdo, "
        SELECT COALESCE(rubro, 'SIN RUBRO') as label, SUM(Stock * preciocosto) as value 
        FROM alb_productos 
        WHERE habilitado = 1 AND Stock > 0 
        GROUP BY rubro 
        ORDER BY value DESC 
        LIMIT 5
    ");

    // Gráfico de Tendencia de Ventas (Últimos 7 días)
    $data['charts']['ventas_tendencia'] = safeQueryAll($pdo, "
        SELECT DATE_FORMAT(fecha, '%d/%m') as label, SUM(COALESCE(total, 0)) as value 
        FROM tickets 
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) 
        GROUP BY DATE(fecha) 
        ORDER BY DATE(fecha) ASC
    ");

    // Lista: Productos Críticos (Stock <= 0)
    $data['lists']['stock_critico'] = safeQueryAll($pdo, "
        SELECT codigo, descripcion, Stock 
        FROM alb_productos 
        WHERE habilitado = 1 AND Stock <= 0 
        ORDER BY Stock ASC, descripcion ASC 
        LIMIT 6
    ");

    // PROYECCIÓN DE CASHFLOW (Próximos 15 días)
    $data['charts']['cashflow_cobros'] = safeQueryAll($pdo, "
        SELECT 
            DATE_FORMAT(vencimiento, '%Y-%m-%d') as fecha, 
            SUM(total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0)) as monto
        FROM tickets 
        WHERE pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) 
          AND vencimiento >= CURDATE() AND vencimiento <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(vencimiento)
    ");

    $data['charts']['cashflow_pagos'] = safeQueryAll($pdo, "
        SELECT 
            DATE_FORMAT(fechavencimiento, '%Y-%m-%d') as fecha, 
            SUM(total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0)) as monto
        FROM facturasdecompra 
        WHERE estado = 1 
          AND fechavencimiento >= CURDATE() AND fechavencimiento <= DATE_ADD(CURDATE(), INTERVAL 14 DAY)
        GROUP BY DATE(fechavencimiento)
    ");

    /* =========================================================
       4. ESTADÍSTICAS POR VENDEDOR (Nueva Pestaña)
       ========================================================= */
    // Seguridad: Vendedor restringido — solo ve su propia fila del desglose.
    $filtroVendedorDash = esVendedorRestringido() ? (" AND c.vendedorid = " . idVendedorRestringido()) : "";

    // Intentamos hacer JOIN con vendedores.nombre. Si falla la estructura, el catch hace un fallback.
    $sqlVendedores = "
        SELECT
            COALESCE(v.nombre, CONCAT('Vend. ', c.vendedorid)) as vendedor_nombre,
            COUNT(DISTINCT c.id) as cant_clientes,
            COALESCE((SELECT SUM(debe - haber) FROM ccclientes WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid)), 0) as saldo_clientes,
            COALESCE((SELECT SUM(total) FROM tickets WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid) AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) as ventas_30d,
            COALESCE((SELECT SUM(haber) FROM ccclientes WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid) AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) as cobros_30d
        FROM alb_clientes c
        LEFT JOIN vendedores v ON c.vendedorid = v.id
        WHERE c.habilitado = 1 AND c.vendedorid > 0 $filtroVendedorDash
        GROUP BY c.vendedorid, v.nombre
        ORDER BY ventas_30d DESC
    ";

    $vendedoresData = safeQueryAll($pdo, $sqlVendedores);

    // Fallback de seguridad estricto por si la tabla vendedores no existe o se llama distinto
    if (empty($vendedoresData)) {
        $sqlVendedoresFallback = "
            SELECT
                CONCAT('Vendedor ID: ', c.vendedorid) as vendedor_nombre,
                COUNT(DISTINCT c.id) as cant_clientes,
                COALESCE((SELECT SUM(debe - haber) FROM ccclientes WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid)), 0) as saldo_clientes,
                COALESCE((SELECT SUM(total) FROM tickets WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid) AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) as ventas_30d,
                COALESCE((SELECT SUM(haber) FROM ccclientes WHERE cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = c.vendedorid) AND fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)), 0) as cobros_30d
            FROM alb_clientes c
            WHERE c.habilitado = 1 AND c.vendedorid > 0 $filtroVendedorDash
            GROUP BY c.vendedorid
            ORDER BY ventas_30d DESC
        ";
        $vendedoresData = safeQueryAll($pdo, $sqlVendedoresFallback);
    }
    
    $data['lists']['vendedores_stats'] = $vendedoresData;

} catch (Exception $e) {
    $data['success'] = false;
    $data['error'] = 'Error interno procesando métricas: ' . $e->getMessage();
}

echo json_encode($data);
?>