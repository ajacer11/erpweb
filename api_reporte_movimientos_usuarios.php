<?php
/**
 * api_reporte_movimientos_usuarios.php - LÓGICA DE DATOS PARA MOVIMIENTOS DE USUARIOS
 * Pivota la tabla cccuentastesoreria para agrupar por tipo de origen.
 * EXTRAE DINÁMICAMENTE EL USUARIO RECEPTOR EN LAS TRANSFERENCIAS RECIBIDAS.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_filters') {
        // Obtenemos los usuarios asegurando que también se incluyan los extraídos de las descripciones
        $stmtUsuarios = $pdo->query("
            SELECT DISTINCT usuario_real as usuario 
            FROM (
                SELECT 
                    CASE 
                        WHEN tabla = 'transf_cajas' AND debe > 0 AND descripcion LIKE '%(%)%' 
                        THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(descripcion, '(', -1), ')', 1))
                        ELSE usuario 
                    END AS usuario_real
                FROM cccuentastesoreria
                WHERE usuario IS NOT NULL AND usuario != '' 
            ) AS base
            WHERE usuario_real IS NOT NULL AND usuario_real != ''
            ORDER BY usuario_real ASC
        ");
        $usuarios = $stmtUsuarios->fetchAll();

        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
        exit;
    }

    if ($action === 'get_report') {
        $desde = $_GET['fecha_desde'] ?? date('Y-m-01');
        $hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
        $usuario = $_GET['usuario'] ?? '';
        $agrupar = $_GET['agrupar'] ?? '0';

        $params = [$desde, $hasta];
        $filtroUsuario = "";
        
        if ($usuario !== '') {
            $filtroUsuario = " AND usuario_real = ? ";
            $params[] = $usuario;
        }

        if ($agrupar === '1') {
            // VISTA AGRUPADA (Pivot Table)
            // Agrupamos sobre el resultado de la subconsulta (base) que ya tiene el usuario_real calculado
            $sql = "
                SELECT 
                    usuario_real as usuario,
                    SUM(CASE WHEN tipo_origen = 'tickets' THEN (debe + haber) ELSE 0 END) as ventas,
                    SUM(CASE WHEN tipo_origen = 'pagos' THEN (debe + haber) ELSE 0 END) as cobros,
                    SUM(CASE WHEN tipo_origen = 'pagoaproveedores' THEN (debe + haber) ELSE 0 END) as pagos,
                    SUM(CASE WHEN tipo_origen = 'ajuste_caja' THEN (debe + haber) ELSE 0 END) as ajustes,
                    SUM(CASE WHEN tipo_origen = 'transf_cajas' AND haber > 0 THEN haber ELSE 0 END) as transf_enviadas,
                    SUM(CASE WHEN tipo_origen = 'transf_cajas' AND debe > 0 THEN debe ELSE 0 END) as transf_recibidas,
                    SUM(CASE WHEN tipo_origen = 'retiro_central' THEN (debe + haber) ELSE 0 END) as retiros,
                    SUM(CASE WHEN tipo_origen = 'cccuentastesoreria' THEN (debe + haber) ELSE 0 END) as movimientos,
                    SUM(CASE WHEN tipo_origen NOT IN ('tickets', 'pagos', 'pagoaproveedores', 'ajuste_caja', 'transf_cajas', 'retiro_central', 'cccuentastesoreria') THEN (debe + haber) ELSE 0 END) as otros,
                    SUM(debe + haber) as total_volumen
                FROM (
                    SELECT 
                        CASE 
                            WHEN tabla = 'transf_cajas' AND debe > 0 AND descripcion LIKE '%(%)%' 
                            THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(descripcion, '(', -1), ')', 1))
                            ELSE usuario 
                        END AS usuario_real,
                        tabla as tipo_origen, debe, haber
                    FROM cccuentastesoreria
                    WHERE DATE(fecha) BETWEEN ? AND ?
                      -- el ajuste de caja también mueve el medio de tipo 'Ajuste' (contrapartida): no se cuenta dos veces
                      AND NOT (tabla = 'ajuste_caja' AND idcuenta IN (SELECT id FROM mediosdepago WHERE tipodecuenta = 'Ajuste'))
                ) AS base
                WHERE 1=1 $filtroUsuario
                GROUP BY usuario_real
                ORDER BY usuario_real ASC
            ";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $movimientos = $stmt->fetchAll();

            echo json_encode(['status' => 'success', 'data' => ['agrupado' => true, 'movimientos' => $movimientos]]);
            exit;
        }

        // VISTA DETALLADA
        // Reemplaza el usuario original por el usuario_real extraído para la vista detallada
        $sql = "
            SELECT 
                id, 
                fecha, 
                usuario_real as usuario, 
                tipo_origen, 
                descripcion, 
                compasoc, 
                debe, 
                haber
            FROM (
                SELECT 
                    id, fecha, descripcion, compasoc, debe, haber, tabla as tipo_origen,
                    CASE 
                        WHEN tabla = 'transf_cajas' AND debe > 0 AND descripcion LIKE '%(%)%' 
                        THEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(descripcion, '(', -1), ')', 1))
                        ELSE usuario 
                    END AS usuario_real
                FROM cccuentastesoreria
                WHERE DATE(fecha) BETWEEN ? AND ?
                      -- el ajuste de caja también mueve el medio de tipo 'Ajuste' (contrapartida): no se cuenta dos veces
                      AND NOT (tabla = 'ajuste_caja' AND idcuenta IN (SELECT id FROM mediosdepago WHERE tipodecuenta = 'Ajuste'))
            ) AS base
            WHERE 1=1 $filtroUsuario
            ORDER BY fecha DESC, id DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movimientos = $stmt->fetchAll();

        echo json_encode(['status' => 'success', 'data' => ['agrupado' => false, 'movimientos' => $movimientos]]);
        exit;
    }

    throw new Exception("Acción no válida.");

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error: ' . $e->getMessage()]);
}