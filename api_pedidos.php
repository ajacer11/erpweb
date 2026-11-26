<?php
/**
 * api_pedidos.php - MOTOR TRANSACCIONAL PARA PEDIDOS DE VENTA
 * Soporte para Alta y Edición con validaciones de estado legacy.
 */
require_once 'check_api_access.php';
require_once 'precios_listas.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

/**
 * ¿La base tiene la columna lineapedidosclientes.preparado? (sql/2026-11-13_pedidos_preparado.sql)
 * Si una base todavía no la tiene, los pedidos siguen funcionando igual, sin guardar el tilde.
 */
function pedidosTienePreparado(PDO $pdo): bool {
    static $tiene = null;
    if ($tiene === null) {
        $st = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lineapedidosclientes' AND COLUMN_NAME = 'preparado'");
        $tiene = (int) $st->fetchColumn() > 0;
    }
    return $tiene;
}

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
            // precio..precio8 = precio final de cada lista (para la columna PRECIO FINAL del buscador).
            $sql = "SELECT id, codigo, descripcion, marca, rubro, poriva, ImpInt, " . sqlNetosListas() . ",
                           precio, precio2, precio3, precio4, precio5, precio6, precio7, precio8
                    FROM alb_productos
                    WHERE habilitado = 1 " . (!empty($q) ? "AND (codigo LIKE ? OR descripcion LIKE ?)" : "") . " 
                    ORDER BY descripcion ASC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            if(!empty($q)) $stmt->execute(["%$q%", "%$q%"]); else $stmt->execute();
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_producto_detalle':
            $codigo = $_GET['codigo'] ?? '';
            $sql = "SELECT id, codigo, descripcion, marca, rubro, poriva, ImpInt, " . sqlNetosListas() . "
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
            // LEFT JOIN (no INNER): una línea manual (LM) no tiene producto real y no debe desaparecer.
            // El IVA sale del producto si existe; si no (línea LM), se usa el propio poriva de la línea,
            // con 21% de resguardo si llegara una LM sin poriva cargado (import legado).
            $sqlDet = "SELECT l.*, p.id as prod_id, COALESCE(p.poriva, l.poriva, 21) as iva_pct, p.ImpInt as impint_unit, p.rubro,
                              " . sqlNetosListas('p') . "
                       FROM lineapedidosclientes l
                       LEFT JOIN alb_productos p ON l.producto = p.codigo
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

            // Validar permiso para editar descripción de producto (config general + permiso de usuario)
            $cfgRow = $pdo->query("SELECT permitireditardescripcion FROM configuracion ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $permiteEditarDesc = ((int) ($cfgRow['permitireditardescripcion'] ?? 0) === 1) && puedeEditarDescripcionPedidos();
            foreach ($input['items'] as $itChk) {
                if (($itChk['codigo'] ?? '') === 'LM') continue;
                $stmtArtChk = $pdo->prepare("SELECT descripcion FROM alb_productos WHERE codigo = ?");
                $stmtArtChk->execute([$itChk['codigo']]);
                $descCatalogo = $stmtArtChk->fetchColumn();
                if ($descCatalogo !== false && trim($itChk['descripcion'] ?? '') !== trim($descCatalogo) && !$permiteEditarDesc) {
                    throw new Exception("No tiene permiso para modificar la descripción del artículo [{$descCatalogo}].");
                }
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
            // "preparado" (tilde de armado en caja) se conserva al re-insertar las líneas
            $conPrep = pedidosTienePreparado($pdo);
            $sqlDet = "INSERT INTO lineapedidosclientes (numpedido, producto, cantidad, tela, color, columna, descripcion, observacion, lista, descuento, preciounit, impint, poriva" . ($conPrep ? ", preparado" : "") . ") VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?" . ($conPrep ? ", ?" : "") . ")";
            $stmtDet = $pdo->prepare($sqlDet);

            foreach($input['items'] as $it) {
                $valores = [
                    $numpedido, $it['codigo'], $it['cantidad'], $it['tela'] ?? '', $it['color'] ?? '',
                    $it['columna'] ?? '', $it['descripcion'], '', $it['lista'],
                    $it['descuento'], $it['precio_unitario'], $it['imp_int_unit'],
                    $it['codigo'] === 'LM' ? ($it['poriva'] ?? null) : null
                ];
                if ($conPrep) $valores[] = !empty($it['preparado']) ? 1 : 0;
                $stmtDet->execute($valores);

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

        case 'set_preparado':
            // Guarda al instante el tilde "preparado en caja" de una línea de un pedido ya grabado
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido");
            if (!pedidosTienePreparado($pdo)) throw new Exception("Falta actualizar la base (columna preparado en las líneas de pedido).");
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $numpedido = (int) ($input['numpedido'] ?? 0);
            $lineaId = (int) ($input['linea_id'] ?? 0);
            if ($numpedido <= 0 || $lineaId <= 0) throw new Exception("Datos incompletos");

            $stCab = $pdo->prepare("SELECT vendedorid, estadofac FROM pedidosclientes WHERE numpedido = ?");
            $stCab->execute([$numpedido]);
            $cab = $stCab->fetch(PDO::FETCH_ASSOC);
            if (!$cab) throw new Exception("Pedido no encontrado");
            if (esVendedorRestringido() && (int) $cab['vendedorid'] !== idVendedorRestringido()) {
                throw new Exception("No tiene acceso a este pedido.");
            }
            if ((int) $cab['estadofac'] !== 0) throw new Exception("El pedido ya no está pendiente.");

            $stUpd = $pdo->prepare("UPDATE lineapedidosclientes SET preparado = ? WHERE id = ? AND numpedido = ?");
            $stUpd->execute([!empty($input['preparado']) ? 1 : 0, $lineaId, $numpedido]);
            $stExiste = $pdo->prepare("SELECT COUNT(*) FROM lineapedidosclientes WHERE id = ? AND numpedido = ?");
            $stExiste->execute([$lineaId, $numpedido]);
            if ((int) $stExiste->fetchColumn() === 0) throw new Exception("La línea ya no existe: el pedido fue modificado. Recargalo.");
            echo json_encode(['success' => true]);
            break;

        default: throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
