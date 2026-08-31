<?php
/**
 * api_cashflow.php - ENDPOINT DEL ESTADO DE CASHFLOW
 * Cruza Tesorería, Facturas de Compras/Ventas y Cheques para proyectar liquidez.
 * Actualizado: Consulta exacta de tesorería y separación conceptual del Arrastre.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

function safeQueryAll($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function safeQueryValue($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val !== false && $val !== null ? (float)$val : 0;
    } catch (Exception $e) {
        return 0;
    }
}

$data = [
    'success' => true,
    'tesoreria' => [],
    'cobros' => [],
    'pagos' => [],
    'resumen' => [
        'liquidez_actual' => 0,
        'arrastre_cobros' => 0,
        'arrastre_pagos' => 0,
        'total_cobrar_periodo' => 0,
        'total_pagar_periodo' => 0
    ]
];

try {
    if ($action === 'get_data') {
        $desde = $_GET['desde'] ?? date('Y-m-d');
        $hasta = $_GET['hasta'] ?? date('Y-m-d', strtotime('+30 days'));
        $inc_vencidos = isset($_GET['inc_vencidos']) && $_GET['inc_vencidos'] == '1';

        /* =========================================================
           1. TESORERÍA (DISPONIBILIDAD LÍQUIDA ACTUAL)
           Consulta corregida según estructura legacy.
           ========================================================= */
        $sqlTeso = "
            SELECT 
                m.id,
                UPPER(COALESCE(m.tipodecuenta, 'INDEFINIDO')) as tipo,
                m.medio as cuenta,
                COALESCE((SELECT SUM(debe) - SUM(haber) FROM cccuentastesoreria WHERE idcuenta = m.id), 0) as saldo
            FROM mediosdepago m
            WHERE m.habilitado = 1
        ";
        $allCuentas = safeQueryAll($pdo, $sqlTeso);
        
        foreach ($allCuentas as $c) {
            $tipo = trim($c['tipo']);
            // Omitir cuentas pivote y de ajuste
            if (in_array($tipo, ['OTROS', 'AJUSTE', 'AJUSTES', 'RETENCION', 'RETENCIONES'])) continue;
            
            // Traemos las que tienen saldo
            if (abs((float)$c['saldo']) > 0.001) {
                $data['tesoreria'][] = [
                    'tipo' => $tipo,
                    'cuenta' => $c['cuenta'],
                    'saldo' => (float)$c['saldo']
                ];
                $data['resumen']['liquidez_actual'] += (float)$c['saldo'];
            }
        }

        // Ordenar alfabéticamente
        usort($data['tesoreria'], function($a, $b) {
            return strcmp($a['tipo'].$a['cuenta'], $b['tipo'].$b['cuenta']);
        });


        /* =========================================================
           2. ARRASTRE DE SALDOS (VENCIDOS ANTERIORES AL PERÍODO)
           ========================================================= */
        if ($inc_vencidos) {
            // A Cobrar (Tickets + Cheques)
            $data['resumen']['arrastre_cobros'] += safeQueryValue($pdo, "
                SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0)) 
                FROM tickets WHERE pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) AND vencimiento < ?
            ", [$desde]);
            
            $data['resumen']['arrastre_cobros'] += safeQueryValue($pdo, "
                SELECT SUM(importe) FROM chequesterceros WHERE estado = 1 AND fechavencimiento < ?
            ", [$desde]);

            // A Pagar (Facturas + Cheques)
            $data['resumen']['arrastre_pagos'] += safeQueryValue($pdo, "
                SELECT SUM(total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0)) 
                FROM facturasdecompra WHERE estado = 1 AND fechavencimiento < ?
            ", [$desde]);

            $data['resumen']['arrastre_pagos'] += safeQueryValue($pdo, "
                SELECT SUM(importe) FROM chequespropios WHERE estado = 1 AND fechavencimiento < ?
            ", [$desde]);
        }


        /* =========================================================
           3. A COBRAR (MOVIMIENTOS DENTRO DEL PERÍODO)
           ========================================================= */
        $sqlCobrosFac = "
            SELECT 
                'FACTURA' as origen,
                CONCAT('Fac. ', LPAD(PtoVta, 4, '0'), '-', LPAD(numcomp, 8, '0'), ' (', COALESCE((SELECT razon FROM alb_clientes WHERE id = tickets.cliente), 'Consumidor Final'), ')') as descripcion,
                DATE_FORMAT(vencimiento, '%Y-%m-%d') as fecha,
                (total - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = tickets.id), 0)) as monto
            FROM tickets 
            WHERE pagada = 0 AND tipocomp NOT IN (3, 8, 13, 101) 
              AND vencimiento BETWEEN ? AND ?
            HAVING monto > 0
        ";
        $cobrosFac = safeQueryAll($pdo, $sqlCobrosFac, [$desde, $hasta]);

        $sqlCobrosChq = "
            SELECT 
                'CHEQUE TERCERO' as origen,
                CONCAT('Cheque Nº ', numero, ' (', COALESCE((SELECT razon FROM alb_clientes WHERE id = chequesterceros.cliente), 'Tercero'), ')') as descripcion,
                DATE_FORMAT(fechavencimiento, '%Y-%m-%d') as fecha,
                importe as monto
            FROM chequesterceros 
            WHERE estado = 1 
              AND fechavencimiento BETWEEN ? AND ?
        ";
        $cobrosChq = safeQueryAll($pdo, $sqlCobrosChq, [$desde, $hasta]);

        $data['cobros'] = array_merge($cobrosFac, $cobrosChq);
        usort($data['cobros'], function($a, $b) { return strcmp($a['fecha'], $b['fecha']); });
        
        foreach ($data['cobros'] as $c) {
            $data['resumen']['total_cobrar_periodo'] += (float)$c['monto'];
        }


        /* =========================================================
           4. A PAGAR (MOVIMIENTOS DENTRO DEL PERÍODO)
           ========================================================= */
        $sqlPagosFac = "
            SELECT 
                'FACTURA COMPRA' as origen,
                CONCAT('Fac. ', COALESCE(numfact, id), ' (', COALESCE((SELECT razon FROM Proveedores WHERE id = facturasdecompra.proveedorid), 'Proveedor'), ')') as descripcion,
                DATE_FORMAT(fechavencimiento, '%Y-%m-%d') as fecha,
                (total - COALESCE((SELECT SUM(importepagado) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id), 0)) as monto
            FROM facturasdecompra 
            WHERE estado = 1 
              AND fechavencimiento BETWEEN ? AND ?
            HAVING monto > 0
        ";
        $pagosFac = safeQueryAll($pdo, $sqlPagosFac, [$desde, $hasta]);

        $sqlPagosChq = "
            SELECT 
                'CHEQUE PROPIO' as origen,
                CONCAT('Cheque Emitido Nº ', numero, ' (', COALESCE((SELECT razon FROM Proveedores WHERE id = chequespropios.proveedor), 'Tercero'), ')') as descripcion,
                DATE_FORMAT(fechavencimiento, '%Y-%m-%d') as fecha,
                importe as monto
            FROM chequespropios 
            WHERE estado = 1 
              AND fechavencimiento BETWEEN ? AND ?
        ";
        $pagosChq = safeQueryAll($pdo, $sqlPagosChq, [$desde, $hasta]);

        $data['pagos'] = array_merge($pagosFac, $pagosChq);
        usort($data['pagos'], function($a, $b) { return strcmp($a['fecha'], $b['fecha']); });

        foreach ($data['pagos'] as $p) {
            $data['resumen']['total_pagar_periodo'] += (float)$p['monto'];
        }
    }

} catch (Exception $e) {
    $data['success'] = false;
    $data['error'] = 'Error procesando Cashflow: ' . $e->getMessage();
}

echo json_encode($data);
?>