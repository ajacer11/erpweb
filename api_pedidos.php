<?php
/**
 * api_pedidos.php - MOTOR TRANSACCIONAL PARA PEDIDOS DE VENTA
 * Soporte para Alta y Edición con validaciones de estado legacy.
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_info_cliente':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT vendedorid, ListaDePRecios FROM alb_clientes WHERE id = ?");
            $stmt->execute([$id]);
            $cli = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$cli) throw new Exception("Cliente no encontrado");
            $stmtSaldo = $pdo->prepare("SELECT (SUM(debe) - SUM(haber)) as saldo FROM ccclientes WHERE cliente = ?");
            $stmtSaldo->execute([$id]);
            $saldo = $stmtSaldo->fetchColumn() ?: 0;
            echo json_encode(['success' => true, 'data' => ['saldo' => $saldo, 'lista_precios' => $cli['ListaDePRecios'], 'vendedor_id' => $cli['vendedorid']]]);
            break;

        case 'buscar_productos':
            $q = $_GET['q'] ?? '';
            $sql = "SELECT id, codigo, descripcion, marca, rubro, poriva, ImpInt, siniva, siniva2, siniva3, siniva4, siniva5, siniva6, siniva7, siniva8 
                    FROM alb_productos 
                    WHERE habilitado = 1 " . (!empty($q) ? "AND (codigo LIKE ? OR descripcion LIKE ?)" : "") . " 
                    ORDER BY descripcion ASC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            if(!empty($q)) $stmt->execute(["%$q%", "%$q%"]); else $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_producto_detalle':
            $codigo = $_GET['codigo'] ?? '';
            $sql = "SELECT id, codigo, descripcion, marca, rubro, poriva, ImpInt, siniva, siniva2, siniva3, siniva4, siniva5, siniva6, siniva7, siniva8
                    FROM alb_productos WHERE codigo = ? AND habilitado = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$codigo]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => (bool)$prod, 'data' => $prod]);
            break;

        case 'get_pedido':
            $id = $_GET['id'] ?? 0;
            // 1. Obtener Cabecera
            $stmtCab = $pdo->prepare("SELECT numpedido, cliente, fechaentrega, observaciones, vendedorid, estadofac FROM pedidosclientes WHERE numpedido = ?");
            $stmtCab->execute([$id]);
            $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
            if(!$cab) throw new Exception("Pedido no encontrado");

            if (esVendedorRestringido() && (int) $cab['vendedorid'] !== idVendedorRestringido()) {
                throw new Exception("No tiene acceso a este pedido.");
            }

            // Validar estado para edición (Solo Pendientes = 0)
            if($cab['estadofac'] == 1) throw new Exception("El pedido ya está facturado y no puede editarse.");
            if($cab['estadofac'] == 2) throw new Exception("El pedido está anulado y no puede editarse.");

            // 2. Obtener Líneas + Precios de las 8 listas para que el JS pueda recalcular si se cambia de lista
            $sqlDet = "SELECT l.*, p.id as prod_id, p.poriva as iva_pct, p.ImpInt as impint_unit, p.rubro,
                              p.siniva, p.siniva2, p.siniva3, p.siniva4, p.siniva5, p.siniva6, p.siniva7, p.siniva8
                       FROM lineapedidosclientes l
                       INNER JOIN alb_productos p ON l.producto = p.codigo
                       WHERE l.numpedido = ?
                       ORDER BY l.id ASC";
            $stmtDet = $pdo->prepare($sqlDet);
            $stmtDet->execute([$id]);
            $lineas = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['success' => true, 'cab' => $cab, 'lineas' => $lineas]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            $numpedido = isset($input['numpedido']) ? intval($input['numpedido']) : 0;

            if (esVendedorRestringido()) {
                $stmtChkCli = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = ?");
                $stmtChkCli->execute([$input['cliente_id']]);
                if ((int) $stmtChkCli->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a cargar pedidos para este cliente.");
                }
                $input['vendedor_id'] = idVendedorRestringido();
            }

            $pdo->beginTransaction();

            if($numpedido > 0) {
                // VALIDACIÓN: El pedido no debe haber sido facturado en el interín
                $stValid = $pdo->prepare("SELECT estadofac FROM pedidosclientes WHERE numpedido = ?");
                $stValid->execute([$numpedido]);
                if($stValid->fetchColumn() != 0) throw new Exception("El pedido cambió de estado (Facturado o Anulado) y ya no puede ser modificado.");

                // UPDATE Cabecera
                $sqlUpd = "UPDATE pedidosclientes SET cliente = ?, fechaentrega = ?, observaciones = ?, vendedorid = ? WHERE numpedido = ?";
                $pdo->prepare($sqlUpd)->execute([$input['cliente_id'], $input['fecha'], $input['observaciones'], $input['vendedor_id'], $numpedido]);

                // Limpiar Líneas anteriores para re-insertar el nuevo estado
                $pdo->prepare("DELETE FROM lineapedidosclientes WHERE numpedido = ?")->execute([$numpedido]);
            } else {
                // INSERT Nuevo
                $stmtNum = $pdo->query("SELECT COALESCE(MAX(numpedido), 0) + 1 FROM pedidosclientes FOR UPDATE");
                $numpedido = $stmtNum->fetchColumn();

                $sqlCabe = "INSERT INTO pedidosclientes (cliente, estadofac, numpedido, fechaentrega, estadoprod, observaciones, vendedorid) VALUES (?, 0, ?, ?, 0, ?, ?)";
                $pdo->prepare($sqlCabe)->execute([$input['cliente_id'], $numpedido, $input['fecha'], $input['observaciones'], $input['vendedor_id']]);
            }

            // Inserción de Líneas (Común a ambos procesos)
            $sqlDet = "INSERT INTO lineapedidosclientes (numpedido, producto, cantidad, tela, color, columna, descripcion, observacion, lista, descuento, preciounit, impint) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtDet = $pdo->prepare($sqlDet);

            foreach($input['items'] as $it) {
                $stmtDet->execute([
                    $numpedido, $it['codigo'], $it['cantidad'], $it['tela'] ?? '', $it['color'] ?? '', 
                    $it['columna'] ?? '', $it['descripcion'], '', $it['lista'], 
                    $it['descuento'], $it['precio_unitario'], $it['imp_int_unit']
                ]);

                // Registrar uso de stock comprometido de promoción (si aplica)
                if (!empty($it['promo_id'])) {
                    $pdo->prepare(
                        "UPDATE promociones SET stock_usado = stock_usado + ? WHERE id = ? AND stock_comprometido IS NOT NULL"
                    )->execute([(int)$it['cantidad'], (int)$it['promo_id']]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'numpedido' => $numpedido]);
            break;

        default: throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
