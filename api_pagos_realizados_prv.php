<?php
/**
 * api_pagos_realizados_prv.php - ENDPOINT PARA GESTIÓN DE PAGOS A PROVEEDORES
 * Retorna datos en JSON para ser procesados visualmente o exportados a Excel desde el Frontend.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$usuario_sesion = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

try {
    switch ($action) {
        
        case 'get_filters':
            // Cargar listas para combos
            $stmtProv = $pdo->query("SELECT id, razon FROM proveedores ORDER BY razon ASC");
            $stmtUsu = $pdo->query("SELECT DISTINCT usuario FROM pagoaproveedores WHERE usuario IS NOT NULL AND usuario != '' ORDER BY usuario ASC");
            
            echo json_encode([
                'success' => true,
                'proveedores' => $stmtProv->fetchAll(),
                'usuarios' => $stmtUsu->fetchAll()
            ]);
            break;

        case 'list':
            // Recibe los filtros por GET
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-d');
            $estado = $_GET['estado'] ?? 'activos';
            $idprov = $_GET['proveedor'] ?? 'todos';
            $usuario = $_GET['usuario'] ?? 'todos';

            $sql = "
                SELECT 
                    p.id as 'Num Pago', 
                    p.fecharecibo as Fecha, 
                    pr.razon as Proveedor, 
                    m1.medio as Medio1, 
                    p.mp1imp, 
                    m2.medio as Medio2, 
                    p.mp2imp, 
                    p.usuario as Usuario, 
                    p.total as Total, 
                    p.estado as Estado, 
                    p.fiscal as Fiscal
                FROM pagoaproveedores p
                LEFT JOIN proveedores pr ON p.proveedor = pr.id
                LEFT JOIN mediosdepago m1 ON p.mp1 = m1.id
                LEFT JOIN mediosdepago m2 ON p.mp2 = m2.id
                WHERE DATE(p.fecharecibo) BETWEEN ? AND ?
            ";
            
            $params = [$desde, $hasta];

            if ($estado === 'activos') {
                $sql .= " AND p.estado <> 1000";
            } elseif ($estado === 'anulados') {
                $sql .= " AND p.estado = 1000";
            }

            if ($idprov !== 'todos' && !empty($idprov)) {
                $sql .= " AND p.proveedor = ?";
                $params[] = $idprov;
            }

            if ($usuario !== 'todos' && !empty($usuario)) {
                $sql .= " AND p.usuario = ?";
                $params[] = $usuario;
            }

            $sql .= " ORDER BY p.fecharecibo DESC, p.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'toggle_fiscal':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $actual = $data['actual'];
            $newVal = $actual == 1 ? 0 : 1;

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE pagoaproveedores SET fiscal = ? WHERE id = ?")->execute([$newVal, $id]);
            $pdo->prepare("UPDATE cccuentastesoreria SET fiscal = ? WHERE compasoc = ? AND tabla = 'pagoaproveedores'")->execute([$newVal, $id]);
            $pdo->prepare("UPDATE librodiario SET fiscal = ? WHERE compasoc = ? AND tabla = 'pagoaproveedores'")->execute([$newVal, $id]);
            $pdo->commit();
            
            echo json_encode(['success' => true]);
            break;

        case 'change_date':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $fecha = $data['fecha'];

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE pagoaproveedores SET fecharecibo = ? WHERE id = ?")->execute([$fecha, $id]);
            $pdo->prepare("UPDATE moviemientosccproveedores SET fecha = ? WHERE compasoc = (SELECT numcomp FROM pagoaproveedores WHERE id = ?) AND tabla = 'pagoaproveedores'")->execute([$fecha, $id]);
            $pdo->prepare("UPDATE cccuentastesoreria SET fecha = ? WHERE compasoc = ? AND tabla = 'pagoaproveedores'")->execute([$fecha, $id]);
            $pdo->prepare("UPDATE librodiario SET fecha = ? WHERE compasoc = ? AND tabla = 'pagoaproveedores'")->execute([$fecha, $id]);
            $pdo->commit();
            
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}