<?php
/**
 * api_reporte_cajas_usuarios.php - LÓGICA DE DATOS PARA MOVIMIENTOS DE CAJAS
 * Sincronizado con la estructura real de la base de datos West IT.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list_cajas':
            /**
             * REGLA DE ORO: Búsqueda flexible. 
             * Usamos LIKE para evitar problemas con mayúsculas/minúsculas o plurales 
             * en la columna 'tipodecuenta'.
             */
            $stmt = $pdo->prepare("
                SELECT id, medio 
                FROM mediosdepago 
                WHERE (tipodecuenta LIKE '%Caja%Usuario%' OR tipodecuenta LIKE '%Efectivo%')
                AND habilitado = 1 
                ORDER BY medio ASC
            ");
            $stmt->execute();
            $data = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_report':
            $id_cuenta = $_GET['id_cuenta'] ?? 0;
            $desde = $_GET['fecha_desde'] ?? date('Y-m-d');
            $hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            if (!$id_cuenta) throw new Exception("Debe seleccionar una cuenta de caja válida.");

            // 1. OBTENER SALDO ANTERIOR (Arrastre contable)
            $stmtAnt = $pdo->prepare("
                SELECT (SUM(debe) - SUM(haber)) as saldo 
                FROM cccuentastesoreria 
                WHERE idcuenta = ? AND fecha < ?
            ");
            $stmtAnt->execute([$id_cuenta, $desde]);
            $saldoAnterior = (float)($stmtAnt->fetchColumn() ?: 0);

            // 2. OBTENER MOVIMIENTOS DEL PERÍODO
            // Columnas verificadas: descripcion, compasoc, debe, haber, usuario, fecha
            $stmtMov = $pdo->prepare("
                SELECT id, fecha, debe, haber, descripcion as observaciones, compasoc, usuario 
                FROM cccuentastesoreria 
                WHERE idcuenta = ? AND fecha BETWEEN ? AND ? 
                ORDER BY fecha ASC, id ASC
            ");
            $stmtMov->execute([$id_cuenta, $desde, $hasta]);
            $movimientos = $stmtMov->fetchAll();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'saldo_anterior' => $saldoAnterior,
                    'movimientos' => $movimientos
                ]
            ]);
            break;

        // APERTURAS Y CIERRES: historial de cuándo se abrió/cerró cada puesto y quién lo hizo
        case 'get_aperturas_cierres':
            $id_cuenta = $_GET['id_cuenta'] ?? 0;
            $desde = $_GET['fecha_desde'] ?? date('Y-m-01');
            $hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

            if (!$id_cuenta) throw new Exception("Debe seleccionar una cuenta de caja válida.");

            $stmtPuesto = $pdo->prepare("SELECT puestoasociado FROM mediosdepago WHERE id = ? LIMIT 1");
            $stmtPuesto->execute([$id_cuenta]);
            $idPuesto = $stmtPuesto->fetchColumn();
            if (!$idPuesto) throw new Exception("Ese medio de pago no tiene un puesto de caja asociado.");

            $desdeFull = $desde . ' 00:00:00';
            $hastaFull = $hasta . ' 23:59:59';

            $stmtAp = $pdo->prepare("
                SELECT usuario AS usuario_apertura, abiertael, saldoinicial,
                       usuario_cierre, cerradael, saldofinal, estado
                FROM controlcajas
                WHERE idcaja = ?
                  AND (abiertael BETWEEN ? AND ? OR cerradael BETWEEN ? AND ?)
                ORDER BY abiertael DESC
            ");
            $stmtAp->execute([$idPuesto, $desdeFull, $hastaFull, $desdeFull, $hastaFull]);
            $data = $stmtAp->fetchAll();

            echo json_encode(['status' => 'success', 'data' => $data]);
            break;

        default:
            throw new Exception("Acción no reconocida: " . $action);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'status' => 'error', 'error' => 'Error de sistema: ' . $e->getMessage()]);
}