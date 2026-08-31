<?php
/**
 * api_reporte_ventas_mediopago.php - LÓGICA DE DATOS PARA VENTAS POR MEDIO DE PAGO
 * Extrae información de tickets, clientes y medios de pago.
 * Soporta agrupación por total recaudado según medio.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_filters') {
        // Cargar listas para poblar los selectores del frontend
        
        // 1. Medios de Pago Activos
        $stmtMedios = $pdo->query("SELECT id, medio FROM mediosdepago WHERE habilitado = 1 AND visibleenventas = 1 ORDER BY medio ASC");
        $medios = $stmtMedios->fetchAll();
        
        // 2. Usuarios con operaciones registradas en tickets
        $stmtUsuarios = $pdo->query("SELECT DISTINCT usuario FROM tickets WHERE usuario IS NOT NULL AND usuario != '' ORDER BY usuario ASC");
        $usuarios = $stmtUsuarios->fetchAll();

        echo json_encode(['success' => true, 'medios' => $medios, 'usuarios' => $usuarios]);
        exit;
    }

    if ($action === 'get_report') {
        $desde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $id_medio = $_GET['id_medio'] ?? '';
        $tipo_comp = $_GET['tipo_comp'] ?? '';
        $usuario = $_GET['usuario'] ?? '';
        $cliente = $_GET['cliente'] ?? '';
        $agrupar = $_GET['agrupar'] ?? '0'; // '1' si está activo el toggle

        // Construcción dinámica de la consulta
        // Basado en regla de CERO ASUNCIONES: Asumimos columnas estándar legacy (mpago1, mpago1imp, mpago2, mpago2imp)
        $sql = "
            SELECT 
                t.id, 
                t.fecha, 
                t.tipocomp, 
                t.letracomp, 
                t.PtoVta, 
                t.numcomp, 
                t.total, 
                t.usuario,
                COALESCE(c.razon, 'CONSUMIDOR FINAL') as cliente_nombre,
                t.mpago1 as nombre_medio1, 
                t.mpago1imp as importe1, 
                t.mpago2 as nombre_medio2, 
                t.mpago2imp as importe2
            FROM tickets t
            LEFT JOIN alb_clientes c ON t.cliente = c.id
            WHERE DATE(t.fecha) BETWEEN ? AND ?
        ";

        $params = [$desde, $hasta];

        // Filtro por Medio de Pago (Busca si coincide en el 1 o en el 2)
        if ($id_medio !== '') {
            $sql .= " AND (t.mpago1 = ? OR t.mpago2 = ?)";
            $params[] = $id_medio;
            $params[] = $id_medio;
        }

        // Filtro por Usuario
        if ($usuario !== '') {
            $sql .= " AND t.usuario = ?";
            $params[] = $usuario;
        }

        // Filtro por Cliente (LIKE)
        if ($cliente !== '') {
            $sql .= " AND c.razon LIKE ?";
            $params[] = '%' . $cliente . '%';
        }

        // Filtro por Tipo de Comprobante
        if ($tipo_comp !== '') {
            switch ($tipo_comp) {
                case 'FCA': 
                    $sql .= " AND t.tipocomp IN (1, 2, 3)"; 
                    break;
                case 'FCB': 
                    $sql .= " AND t.tipocomp IN (6, 7, 8)"; 
                    break;
                case 'FCC': 
                    $sql .= " AND t.tipocomp IN (11, 12, 13)"; 
                    break;
                case '100': 
                    $sql .= " AND t.tipocomp = 100"; 
                    break;
                case '101': 
                    $sql .= " AND t.tipocomp = 101"; 
                    break;
                case 'FISCALES': 
                    $sql .= " AND t.tipocomp < 100"; 
                    break;
                case 'NOFISCALES': 
                    $sql .= " AND t.tipocomp >= 100"; 
                    break;
            }
        }

        $sql .= " ORDER BY t.fecha DESC, t.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ventas = $stmt->fetchAll();

        // Calcular totales generales del reporte
        $totalGeneral = 0;
        foreach($ventas as $v) {
            $totalGeneral += (float)$v['total'];
        }

        // LÓGICA DE AGRUPACIÓN SI ESTÁ SOLICITADO
        if ($agrupar === '1') {
            $totalesPorMedio = [];
            
            // Recorremos los tickets filtrados separando medios y sumando montos
            foreach($ventas as $v) {
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
            
            // Formatear para frontend
            $ventasAgrupadas = [];
            foreach($totalesPorMedio as $medio => $total) {
                $ventasAgrupadas[] = [
                    'medio' => $medio,
                    'total' => $total
                ];
            }
            
            // Ordenar de mayor a menor monto recaudado
            usort($ventasAgrupadas, function($a, $b) { return $b['total'] <=> $a['total']; });
            
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'agrupado' => true,
                    'ventas' => $ventasAgrupadas,
                    'total_general' => $totalGeneral
                ]
            ]);
            exit;
        }

        // Si no se agrupa, devolvemos el detalle normal
        echo json_encode([
            'status' => 'success',
            'data' => [
                'agrupado' => false,
                'ventas' => $ventas,
                'total_general' => $totalGeneral
            ]
        ]);
        exit;
    }

    throw new Exception("Acción no válida.");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error: ' . $e->getMessage()]);
}