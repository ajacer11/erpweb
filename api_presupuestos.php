<?php
/**
 * api_presupuestos.php - MOTOR TRANSACCIONAL COMPLETO PARA PRESUPUESTOS
 * Corregido: Sincronización de parámetros y consultas seguras.
 */
require_once 'check_api_access.php';
require_once 'precios_listas.php';
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
            $sql = "SELECT id, codigo, descripcion, marca, rubro, poriva, ImpInt, " . sqlNetosListas() . " 
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

        case 'get_presupuesto': case 'get_pedido':
            $id = $_GET['id'] ?? 0;
            if (!$id) throw new Exception("ID de presupuesto no válido");

            $stmtCab = $pdo->prepare("SELECT numpedido, cliente, fechaentrega, observaciones, vendedorid, estadofac FROM presupuestosclientes WHERE numpedido = ?");
            $stmtCab->execute([$id]);
            $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
            if(!$cab) throw new Exception("Presupuesto #$id no encontrado");

            if (esVendedorRestringido() && (int) $cab['vendedorid'] !== idVendedorRestringido()) {
                throw new Exception("No tiene acceso a este presupuesto.");
            }

            // Regla estricta: Solo se puede editar si está Pendiente (1)
            if($cab['estadofac'] != 1) {
                $estadoTxt = "procesado";
                if($cab['estadofac'] == 2) $estadoTxt = "Anulado";
                if($cab['estadofac'] == 3) $estadoTxt = "Aprobado";
                if($cab['estadofac'] == 4) $estadoTxt = "Facturado";
                if($cab['estadofac'] == 5) $estadoTxt = "Rechazado";
                throw new Exception("El presupuesto se encuentra $estadoTxt y no puede modificarse.");
            }

            // LEFT JOIN (no INNER): una línea manual (LM) no tiene producto real y no debe desaparecer.
            // El IVA sale del producto si existe; si no (línea LM), se usa el propio poriva de la línea,
            // con 21% de resguardo si llegara una LM sin poriva cargado (import legado).
            $sqlDet = "SELECT l.*, p.id as prod_id, COALESCE(p.poriva, l.poriva, 21) as iva_pct, p.ImpInt as impint_unit, p.rubro,
                              " . sqlNetosListas('p') . "
                       FROM lineapresupuestosclientes l
                       LEFT JOIN alb_productos p ON l.producto = p.codigo
                       WHERE l.numpedido = ? ORDER BY l.id ASC";
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
                    throw new Exception("No tiene acceso a cargar presupuestos para este cliente.");
                }
                $input['vendedor_id'] = idVendedorRestringido();
            }

            // Validar permiso para editar descripción de producto (config general + permiso de usuario)
            $cfgRow = $pdo->query("SELECT permitireditardescripcion FROM configuracion ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $permiteEditarDesc = ((int) ($cfgRow['permitireditardescripcion'] ?? 0) === 1) && puedeEditarDescripcionPresupuestos();
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
                $pdo->prepare("UPDATE presupuestosclientes SET cliente = ?, fechaentrega = ?, observaciones = ?, vendedorid = ? WHERE numpedido = ?")
                    ->execute([$input['cliente_id'], $input['fecha'], $input['observaciones'], $input['vendedor_id'], $numpedido]);
                $pdo->prepare("DELETE FROM lineapresupuestosclientes WHERE numpedido = ?")->execute([$numpedido]);
            } else {
                $stmtNum = $pdo->query("SELECT COALESCE(MAX(numpedido), 0) + 1 FROM presupuestosclientes FOR UPDATE");
                $numpedido = $stmtNum->fetchColumn();

                // INSERCIÓN CON ESTADO 1 (Pendiente) por defecto
                $sqlCabe = "INSERT INTO presupuestosclientes (cliente, estadofac, numpedido, fechaentrega, observaciones, vendedorid, fechapedido) VALUES (?, 1, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sqlCabe)->execute([
                    $input['cliente_id'], 
                    $numpedido, 
                    $input['fecha'], 
                    $input['observaciones'], 
                    $input['vendedor_id']
                ]);
            }

            $sqlDet = "INSERT INTO lineapresupuestosclientes (numpedido, producto, cantidad, descripcion, lista, descuento, preciounit, impint, poriva) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmtDet = $pdo->prepare($sqlDet);
            foreach($input['items'] as $it) {
                $stmtDet->execute([
                    $numpedido, $it['codigo'], $it['cantidad'], $it['descripcion'], $it['lista'],
                    $it['descuento'], $it['precio_unitario'], $it['imp_int_unit'],
                    $it['codigo'] === 'LM' ? ($it['poriva'] ?? null) : null
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