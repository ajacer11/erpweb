<?php
/**
 * api_proveedores_oc.php - LÓGICA DE NEGOCIO PARA ÓRDENES DE COMPRA
 * Actualizado: Seguridad robusta para pedidos finalizados y validación de IDs.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

try {
    switch ($action) {
        case 'get_next_id':
            $stmt = $pdo->query("SELECT MAX(id) FROM pedidos_proveedores_cab");
            $max = $stmt->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'next_id' => $max + 1]);
            break;

        case 'search_products':
            $q = "%" . ($_GET['q'] ?? '') . "%";
            $sql = "SELECT codigo as id, CONCAT('[', codigo, '] ', descripcion) as text, proveedorID 
                    FROM alb_productos 
                    WHERE (descripcion LIKE ? OR codigo LIKE ? OR codigoproveedor LIKE ?) 
                    AND habilitado = 1 LIMIT 25";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$q, $q, $q]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'generate_auto':
            $data = json_decode(file_get_contents('php://input'), true);
            $idProv = (int)($data['proveedor_id'] ?? 0);
            $tipoFiltro = $data['filtro'];

            $sql = "SELECT codigo, codigoproveedor, descripcion, Stock, stockmaximo, StockMin, preciocosto, proveedorID 
                    FROM alb_productos 
                    WHERE proveedorID = ? AND habilitado = 1 AND stockmaximo > 0";
            
            if ($tipoFiltro === 'minimo') {
                $sql .= " AND Stock < StockMin";
            } else {
                $sql .= " AND Stock < stockmaximo";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idProv]);
            $productos = $stmt->fetchAll();

            $detalle = [];
            foreach ($productos as $p) {
                $cant = floatval($p['stockmaximo']) - floatval($p['Stock']);
                if ($cant > 0) {
                    $detalle[] = [
                        'codigo' => $p['codigo'],
                        'cod_prov' => $p['codigoproveedor'] ?? '',
                        'descripcion' => $p['descripcion'],
                        'stock_actual' => floatval($p['Stock']),
                        'stock_ideal' => floatval($p['stockmaximo']),
                        'cantidad' => $cant,
                        'costo' => floatval($p['preciocosto']),
                        'subtotal' => $cant * floatval($p['preciocosto']),
                        'proveedorID' => $p['proveedorID']
                    ];
                }
            }
            echo json_encode(['success' => true, 'data' => $detalle]);
            break;

        case 'get_product':
            $cod = $_GET['codigo'] ?? '';
            $stmt = $pdo->prepare("SELECT codigo, codigoproveedor, descripcion, Stock, stockmaximo, preciocosto, proveedorID 
                                   FROM alb_productos WHERE (codigo = ? OR codigoproveedor = ?)");
            $stmt->execute([$cod, $cod]);
            $p = $stmt->fetch();
            if ($p) {
                echo json_encode(['success' => true, 'data' => [
                    'codigo' => $p['codigo'],
                    'cod_prov' => $p['codigoproveedor'] ?? '',
                    'descripcion' => $p['descripcion'],
                    'stock_actual' => floatval($p['Stock']),
                    'stock_ideal' => floatval($p['stockmaximo']),
                    'cantidad' => 1,
                    'costo' => floatval($p['preciocosto']),
                    'subtotal' => floatval($p['preciocosto']),
                    'proveedorID' => $p['proveedorID']
                ]]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Producto no encontrado.']);
            }
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $idPedido = (int)($data['id_pedido'] ?? 0);
            $isEdit = filter_var($data['edit'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $pdo->beginTransaction();

            if ($idPedido > 0) {
                // SEGURIDAD: Validar que el pedido exista y NO esté FINALIZADO
                $stmtCheck = $pdo->prepare("SELECT estado FROM pedidos_proveedores_cab WHERE id = ?");
                $stmtCheck->execute([$idPedido]);
                $estadoActual = $stmtCheck->fetchColumn();

                if (!$estadoActual) throw new Exception("El pedido solicitado no existe.");
                if ($estadoActual === 'FINALIZADO') throw new Exception("ERROR DE SEGURIDAD: No se puede modificar un pedido que ya ha sido FINALIZADO.");

                // UPDATE Cabecera
                $pdo->prepare("UPDATE pedidos_proveedores_cab SET total = ?, fecha = CURDATE() WHERE id = ?")
                    ->execute([$data['total'], $idPedido]);
                
                // DELETE Detalle anterior
                $pdo->prepare("DELETE FROM pedidos_proveedores_det WHERE id_pedido = ?")->execute([$idPedido]);
                $idCab = $idPedido;
            } else {
                // INSERT Cabecera
                $sqlIns = "INSERT INTO pedidos_proveedores_cab (id_proveedor, fecha, total, estado, usuario_creacion) 
                           VALUES (?, CURDATE(), ?, 'PENDIENTE', ?)";
                $pdo->prepare($sqlIns)->execute([(int)$data['proveedor_id'], $data['total'], $usuario]);
                $idCab = $pdo->lastInsertId();
            }

            // INSERT Detalle
            $sqlDet = "INSERT INTO pedidos_proveedores_det (id_pedido, codigo_producto, descripcion, cantidad, costo_unitario, subtotal) 
                       VALUES (?, ?, ?, ?, ?, ?)";
            $stmtDet = $pdo->prepare($sqlDet);
            foreach ($data['items'] as $item) {
                $stmtDet->execute([(int)$idCab, $item['codigo'], $item['descripcion'], $item['cantidad'], $item['costo'], $item['subtotal']]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idCab]);
            break;

        case 'get_order':
            $id = (int)($_GET['id'] ?? 0);
            $stmtCab = $pdo->prepare("SELECT c.*, p.Razon as proveedor_nombre FROM pedidos_proveedores_cab c JOIN Proveedores p ON c.id_proveedor = p.Id WHERE c.id = ?");
            $stmtCab->execute([$id]);
            $cab = $stmtCab->fetch();

            if (!$cab) throw new Exception("Orden de compra no encontrada.");

            $sqlDet = "SELECT d.*, p.codigoproveedor, p.Stock, p.stockmaximo, p.proveedorID
                       FROM pedidos_proveedores_det d
                       LEFT JOIN alb_productos p ON d.codigo_producto = p.codigo
                       WHERE d.id_pedido = ?";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([$id]);
            $det = $stmtDet->fetchAll();

            $items = array_map(function($i) {
                return [
                    'codigo' => $i['codigo_producto'],
                    'cod_prov' => $i['codigoproveedor'] ?? '',
                    'descripcion' => $i['descripcion'],
                    'stock_actual' => floatval($i['Stock'] ?? 0),
                    'stock_ideal' => floatval($i['stockmaximo'] ?? 0),
                    'cantidad' => floatval($i['cantidad']),
                    'costo' => floatval($i['costo_unitario']),
                    'subtotal' => floatval($i['subtotal']),
                    'proveedorID' => $i['proveedorID'] ?? 0
                ];
            }, $det);

            echo json_encode(['success' => true, 'cab' => $cab, 'items' => $items]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}