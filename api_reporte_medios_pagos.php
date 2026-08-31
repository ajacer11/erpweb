<?php
/**
 * api_reporte_medios_pagos.php - LÓGICA DE DATOS PARA PAGOS A PROVEEDORES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_filters') {
        $stmtMedios = $pdo->query("SELECT id, medio FROM mediosdepago WHERE habilitado = 1 ORDER BY medio ASC");
        $medios = $stmtMedios->fetchAll();
        
        $stmtUsuarios = $pdo->query("SELECT DISTINCT usuario FROM pagoaproveedores WHERE usuario IS NOT NULL AND usuario != '' ORDER BY usuario ASC");
        $usuarios = $stmtUsuarios->fetchAll();

        echo json_encode(['success' => true, 'medios' => $medios, 'usuarios' => $usuarios]);
        exit;
    }

    if ($action === 'get_report') {
        $desde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $id_medio = $_GET['id_medio'] ?? '';
        $usuario = $_GET['usuario'] ?? '';
        $proveedor = $_GET['proveedor'] ?? '';
        $agrupar = $_GET['agrupar'] ?? '0';

        $sql = "
            SELECT 
                pa.id, 
                pa.fecha, 
                pa.total, 
                pa.usuario,
                COALESCE(pr.razon, 'SIN PROVEEDOR ASIGNADO') as entidad_nombre,
                m1.medio as nombre_medio1, 
                pa.mp1imp as importe1, 
                m2.medio as nombre_medio2, 
                pa.mp2imp as importe2
            FROM pagoaproveedores pa
            LEFT JOIN proveedores pr ON pa.proveedor = pr.id
            LEFT JOIN mediosdepago m1 ON pa.mp1 = m1.id
            LEFT JOIN mediosdepago m2 ON pa.mp2 = m2.id
            WHERE DATE(pa.fecha) BETWEEN ? AND ?
        ";

        $params = [$desde, $hasta];

        if ($id_medio !== '') {
            $sql .= " AND (pa.mp1 = ? OR pa.mp2 = ?)";
            $params[] = $id_medio;
            $params[] = $id_medio;
        }

        if ($usuario !== '') {
            $sql .= " AND pa.usuario = ?";
            $params[] = $usuario;
        }

        if ($proveedor !== '') {
            // Se asume la columna "razon" en proveedores al igual que en clientes
            $sql .= " AND pr.razon LIKE ?";
            $params[] = '%' . $proveedor . '%';
        }

        $sql .= " ORDER BY pa.fecha DESC, pa.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movimientos = $stmt->fetchAll();

        $totalGeneral = 0;
        foreach($movimientos as $v) {
            $totalGeneral += (float)$v['total'];
        }

        if ($agrupar === '1') {
            $totalesPorMedio = [];
            
            foreach($movimientos as $v) {
                $m1 = strtoupper(trim($v['nombre_medio1']));
                $imp1 = (float)$v['importe1'];
                if ($m1 !== '' && $imp1 != 0) {
                    if (!isset($totalesPorMedio[$m1])) $totalesPorMedio[$m1] = 0;
                    $totalesPorMedio[$m1] += $imp1;
                }
                
                $m2 = strtoupper(trim($v['nombre_medio2']));
                $imp2 = (float)$v['importe2'];
                if ($m2 !== '' && $imp2 != 0) {
                    if (!isset($totalesPorMedio[$m2])) $totalesPorMedio[$m2] = 0;
                    $totalesPorMedio[$m2] += $imp2;
                }
            }
            
            $movsAgrupados = [];
            foreach($totalesPorMedio as $medio => $total) {
                $movsAgrupados[] = ['medio' => $medio, 'total' => $total];
            }
            
            usort($movsAgrupados, function($a, $b) { return $b['total'] <=> $a['total']; });
            
            echo json_encode(['status' => 'success', 'data' => ['agrupado' => true, 'movimientos' => $movsAgrupados, 'total_general' => $totalGeneral]]);
            exit;
        }

        echo json_encode(['status' => 'success', 'data' => ['agrupado' => false, 'movimientos' => $movimientos, 'total_general' => $totalGeneral]]);
        exit;
    }

    throw new Exception("Acción no válida.");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error: ' . $e->getMessage()]);
}