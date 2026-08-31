<?php
/**
 * api_produccion.php - Motor de Producción (Recetas y Órdenes)
 * CORREGIDO: Lógica de Finalización (Fix IngresosStock, Costos y Descripciones)
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'stock_deposito_helpers.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// MAPEO DE ESTADOS
const ESTADO_PENDIENTE = 1;
const ESTADO_CONFIRMADA = 2;
const ESTADO_EN_PROCESO = 3;
const ESTADO_FINALIZADA = 4;
const ESTADO_CANCELADA = 5;

// grabarLineaAsiento() y resolverEjercicioPeriodo() ahora viven en
// contabilidad_cuentas_helpers.php (ya incluido arriba) — antes estaban duplicadas
// idénticas en varios api_*.php.

try {
    // --- GESTIÓN DE RECETAS (Sin cambios) ---
    if ($action === 'get_recetas') {
        $q = $_GET['q'] ?? '';
        $sql = "SELECT r.id, r.version, r.activa, r.descripcion, p.codigo, p.descripcion as producto_final
                FROM produccion_listamateriales_enc r
                LEFT JOIN alb_productos p ON r.producto_terminado_id = p.id
                WHERE 1=1";
        if ($q) $sql .= " AND (p.descripcion LIKE :q OR p.codigo LIKE :q OR r.descripcion LIKE :q)";
        $sql .= " ORDER BY r.id DESC";
        $stmt = $pdo->prepare($sql);
        if ($q) $stmt->execute([':q' => "%$q%"]); else $stmt->execute();
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'get_receta_detalle') {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("
            SELECT r.*, p.codigo as prod_codigo, p.descripcion as prod_descripcion
            FROM produccion_listamateriales_enc r 
            LEFT JOIN alb_productos p ON r.producto_terminado_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$id]);
        $enc = $stmt->fetch();
        
        $stmtDet = $pdo->prepare("SELECT d.listamateriales_id, d.producto_componente_id, d.cantidad, 
                                         p.codigo, p.descripcion 
                                  FROM produccion_listamateriales_det d
                                  JOIN alb_productos p ON d.producto_componente_id = p.id
                                  WHERE d.listamateriales_id = ?");
        $stmtDet->execute([$id]);
        $det = $stmtDet->fetchAll();

        echo json_encode(['success' => true, 'data' => ['enc' => $enc, 'det' => $det]]);
        exit;
    }

    if ($action === 'save_receta' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $pdo->beginTransaction();
        
        if (!empty($data['id'])) {
            $sqlEnc = "UPDATE produccion_listamateriales_enc SET producto_terminado_id=?, descripcion=?, version=?, activa=? WHERE id=?";
            $stmt = $pdo->prepare($sqlEnc);
            $stmt->execute([$data['id_producto'], $data['descripcion'], $data['version'], $data['activa'], $data['id']]);
            $idEnc = $data['id'];
            $pdo->prepare("DELETE FROM produccion_listamateriales_det WHERE listamateriales_id = ?")->execute([$idEnc]);
        } else {
            $sqlEnc = "INSERT INTO produccion_listamateriales_enc (producto_terminado_id, descripcion, version, activa) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sqlEnc);
            $stmt->execute([$data['id_producto'], $data['descripcion'], $data['version'], $data['activa']]);
            $idEnc = $pdo->lastInsertId();
        }

        $sqlDet = "INSERT INTO produccion_listamateriales_det (listamateriales_id, producto_componente_id, cantidad) VALUES (?, ?, ?)";
        $stmtDet = $pdo->prepare($sqlDet);
        foreach ($data['detalles'] as $d) {
            $stmtDet->execute([$idEnc, $d['id_producto'], $d['cantidad']]);
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Receta guardada correctamente']);
        exit;
    }

    // --- GESTIÓN DE ÓRDENES ---
    // Máquina de estados: PENDIENTE (borrador, no toca stock) -> CONFIRMADA (valida y
    // compromete stock) -> FINALIZADA (consume insumos y da de alta productos terminados).
    // CANCELADA es alcanzable desde PENDIENTE o CONFIRMADA. EN_PROCESO queda como estado
    // legado: ninguna orden nueva lo usa, pero las creadas antes de este cambio (con stock
    // ya comprometido) se tratan como equivalentes a CONFIRMADA en confirmar/cancelar/finalizar.

    if ($action === 'get_ordenes') {
        $estadoFilter = $_GET['estado'] ?? 'TODOS';

        $sql = "SELECT o.id, o.numero_orden, o.fecha_inicio_prevista, o.fecha_fin_prevista, o.estado, o.notas, o.modo_costeo,
                       (SELECT GROUP_CONCAT(p.descripcion SEPARATOR ', ')
                        FROM produccion_ordenes_salidas s
                        JOIN alb_productos p ON s.producto_terminado_id = p.id
                        WHERE s.orden_id = o.id) as productos_a_producir
                FROM produccion_ordenes o WHERE 1=1";

        $params = [];

        if ($estadoFilter === 'PENDIENTE') {
            $sql .= " AND o.estado = :est"; $params[':est'] = ESTADO_PENDIENTE;
        } elseif ($estadoFilter === 'CONFIRMADA') {
            // Incluye EN_PROCESO (legado): órdenes con stock ya comprometido creadas antes de este cambio.
            $sql .= " AND o.estado IN (:est1, :est2)"; $params[':est1'] = ESTADO_CONFIRMADA; $params[':est2'] = ESTADO_EN_PROCESO;
        } elseif ($estadoFilter === 'FINALIZADA') {
            $sql .= " AND o.estado = :est"; $params[':est'] = ESTADO_FINALIZADA;
        } elseif ($estadoFilter === 'CANCELADA') {
            $sql .= " AND o.estado = :est"; $params[':est'] = ESTADO_CANCELADA;
        }

        $sql .= " ORDER BY o.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($action === 'get_orden_detalle') {
        $idOrden = $_GET['id'] ?? 0;

        $stmt = $pdo->prepare("SELECT * FROM produccion_ordenes WHERE id = ?");
        $stmt->execute([$idOrden]);
        $cabecera = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cabecera) throw new Exception("Orden no encontrada.");

        $stmtEnt = $pdo->prepare("SELECT oe.producto_componente_id as id_producto, oe.cantidad_requerida, oe.cantidad_consumida,
                                         p.codigo, p.descripcion, p.Stock as stock_actual, p.sinivacosto
                                  FROM produccion_ordenes_entradas oe
                                  JOIN alb_productos p ON oe.producto_componente_id = p.id
                                  WHERE oe.orden_id = ?");
        $stmtEnt->execute([$idOrden]);

        $stmtSal = $pdo->prepare("SELECT os.producto_terminado_id as id_producto, os.cantidad_a_producir, os.costo_unitario_asignado,
                                         p.codigo, p.descripcion, p.precio
                                  FROM produccion_ordenes_salidas os
                                  JOIN alb_productos p ON os.producto_terminado_id = p.id
                                  WHERE os.orden_id = ?");
        $stmtSal->execute([$idOrden]);

        echo json_encode(['success' => true, 'data' => [
            'cabecera' => $cabecera,
            'entradas' => $stmtEnt->fetchAll(PDO::FETCH_ASSOC),
            'salidas'  => $stmtSal->fetchAll(PDO::FETCH_ASSOC),
        ]]);
        exit;
    }

    // Guardar Orden como BORRADOR (PENDIENTE). No toca stock. Con 'id' reemplaza el detalle existente.
    if ($action === 'guardar_orden' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
        $idOrden = $data['id'] ?? null;

        if (empty($data['salidas'])) throw new Exception("Debe haber al menos un producto de salida.");

        // Depósito de insumos (MP) y de producto terminado (PT). Se fijan al guardar
        // el borrador y quedan almacenados por línea, para que confirmar/cancelar/
        // finalizar no dependan de que el usuario vuelva a elegir el mismo depósito.
        $idDepositoMP = null;
        $idDepositoPT = null;
        if (multidepositoHabilitado($pdo)) {
            $idDepositoMP = !empty($data['id_deposito_mp']) ? (int) $data['id_deposito_mp'] : depositoDeTrabajo($pdo, $usuario);
            $idDepositoPT = !empty($data['id_deposito_pt']) ? (int) $data['id_deposito_pt'] : $idDepositoMP;
        }

        $pdo->beginTransaction();

        if ($idOrden) {
            $chk = $pdo->prepare("SELECT estado FROM produccion_ordenes WHERE id = ?");
            $chk->execute([$idOrden]);
            $estadoActual = $chk->fetchColumn();
            if ($estadoActual === false) throw new Exception("Orden no encontrada.");
            if ((int) $estadoActual !== ESTADO_PENDIENTE) throw new Exception("Solo se puede editar una orden en estado PENDIENTE.");

            $pdo->prepare("UPDATE produccion_ordenes SET fecha_inicio_prevista = ?, fecha_fin_prevista = ?, notas = ? WHERE id = ?")
                ->execute([$data['fecha_inicio'], $data['fecha_fin'], $data['notas'], $idOrden]);
            $pdo->prepare("DELETE FROM produccion_ordenes_salidas WHERE orden_id = ?")->execute([$idOrden]);
            $pdo->prepare("DELETE FROM produccion_ordenes_entradas WHERE orden_id = ?")->execute([$idOrden]);
        } else {
            $numOrdenStr = 'ORD-' . date('Ymd') . '-' . substr(str_shuffle("0123456789"), 0, 4);
            $stmt = $pdo->prepare("INSERT INTO produccion_ordenes (numero_orden, fecha_inicio_prevista, fecha_fin_prevista, estado, notas, usuario_creo) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$numOrdenStr, $data['fecha_inicio'], $data['fecha_fin'], ESTADO_PENDIENTE, $data['notas'], $usuario]);
            $idOrden = $pdo->lastInsertId();
        }

        $stmtSal = $pdo->prepare("INSERT INTO produccion_ordenes_salidas (orden_id, producto_terminado_id, cantidad_a_producir, id_deposito) VALUES (?, ?, ?, ?)");
        foreach ($data['salidas'] as $s) {
            $stmtSal->execute([$idOrden, $s['id_producto'], $s['cantidad'], $idDepositoPT]);
        }

        $stmtEnt = $pdo->prepare("INSERT INTO produccion_ordenes_entradas (orden_id, producto_componente_id, cantidad_requerida, id_deposito) VALUES (?, ?, ?, ?)");
        foreach ($data['entradas'] as $e) {
            $stmtEnt->execute([$idOrden, $e['id_producto'], $e['cantidad'], $idDepositoMP]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $idOrden, 'message' => "Borrador #$idOrden guardado. No se modificó stock."]);
        exit;
    }

    // Confirmar Orden: valida stock suficiente y recién ahí lo compromete.
    if ($action === 'confirmar_orden' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $idOrden = $data['id'] ?? 0;
        $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

        $chk = $pdo->prepare("SELECT estado, numero_orden FROM produccion_ordenes WHERE id = ?");
        $chk->execute([$idOrden]);
        $orden = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$orden) throw new Exception("Orden no encontrada.");
        if ((int) $orden['estado'] !== ESTADO_PENDIENTE) throw new Exception("Solo se puede confirmar una orden en estado PENDIENTE.");

        $stmtEnt = $pdo->prepare("SELECT oe.producto_componente_id as id_producto, oe.cantidad_requerida, oe.id_deposito, p.codigo, p.descripcion, p.Stock, p.rubro
                                  FROM produccion_ordenes_entradas oe
                                  JOIN alb_productos p ON oe.producto_componente_id = p.id
                                  WHERE oe.orden_id = ?");
        $stmtEnt->execute([$idOrden]);
        $entradas = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);

        // Validar stock suficiente ANTES de tocar nada
        $faltantes = [];
        foreach ($entradas as $e) {
            $falta = round((float) $e['cantidad_requerida'] - (float) $e['Stock'], 4);
            if ($falta > 0.0001) {
                $faltantes[] = "{$e['codigo']} - {$e['descripcion']}: faltan " . number_format($falta, 2) .
                    " (stock: " . number_format($e['Stock'], 2) . ", requerido: " . number_format($e['cantidad_requerida'], 2) . ")";
            }
        }
        if (!empty($faltantes)) {
            echo json_encode(['success' => false, 'error' => "Stock insuficiente para confirmar la orden.", 'faltantes' => $faltantes]);
            exit;
        }

        $pdo->beginTransaction();

        $updComprometido = $pdo->prepare("UPDATE alb_productos SET stock_comprometido_produccion = stock_comprometido_produccion + ? WHERE id = ?");
        $logMov = $pdo->prepare("INSERT INTO movimientosdestock (codigo, rubro, entradas, salidas, tipomov, origenmov, esfiscal, tipocomp, numremito, fecha, usuario)
                                 VALUES (?, ?, 0, ?, 'ENVIO A PRODUCCION', 'PRODUCCION', 0, 0, ?, NOW(), ?)");
        foreach ($entradas as $e) {
            $cant = (float) $e['cantidad_requerida'];
            $logMov->execute([$e['codigo'], $e['rubro'], $cant, $idOrden, $usuario]);
            $idMovLegacy = (int) $pdo->lastInsertId();

            moverStock($pdo, (int) $e['id_producto'], $e['codigo'], $e['id_deposito'] ?? null, $cant, 'SALIDA',
                       'ENVIO A PRODUCCION', 'PRODUCCION', 0, $idOrden, $usuario, $idMovLegacy);
            $updComprometido->execute([$cant, $e['id_producto']]);
        }

        $updSal = $pdo->prepare("UPDATE alb_productos SET stkproduccion = stkproduccion + ? WHERE id = ?");
        $stmtSal = $pdo->prepare("SELECT producto_terminado_id as id_producto, cantidad_a_producir FROM produccion_ordenes_salidas WHERE orden_id = ?");
        $stmtSal->execute([$idOrden]);
        foreach ($stmtSal->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $updSal->execute([$s['cantidad_a_producir'], $s['id_producto']]);
        }

        $pdo->prepare("UPDATE produccion_ordenes SET estado = ? WHERE id = ?")->execute([ESTADO_CONFIRMADA, $idOrden]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Orden #{$orden['numero_orden']} confirmada. Stock reservado."]);
        exit;
    }

    // Cancelar Orden: si estaba PENDIENTE no hay nada que revertir; si ya estaba
    // CONFIRMADA/EN_PROCESO, devuelve el stock comprometido.
    if ($action === 'cancelar_orden' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $idOrden = $data['id'] ?? 0;
        $motivo = trim($data['motivo'] ?? '');
        $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

        $chk = $pdo->prepare("SELECT estado, numero_orden, notas FROM produccion_ordenes WHERE id = ?");
        $chk->execute([$idOrden]);
        $orden = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$orden) throw new Exception("Orden no encontrada.");

        $estadoActual = (int) $orden['estado'];
        if (!in_array($estadoActual, [ESTADO_PENDIENTE, ESTADO_CONFIRMADA, ESTADO_EN_PROCESO])) {
            throw new Exception("Esta orden no se puede cancelar (ya está finalizada o cancelada).");
        }

        $pdo->beginTransaction();

        if ($estadoActual !== ESTADO_PENDIENTE) {
            $stmtEnt = $pdo->prepare("SELECT oe.producto_componente_id as id_producto, oe.cantidad_requerida, oe.id_deposito, p.codigo, p.rubro
                                      FROM produccion_ordenes_entradas oe
                                      JOIN alb_productos p ON oe.producto_componente_id = p.id
                                      WHERE oe.orden_id = ?");
            $stmtEnt->execute([$idOrden]);

            $updComprometido = $pdo->prepare("UPDATE alb_productos SET stock_comprometido_produccion = stock_comprometido_produccion - ? WHERE id = ?");
            $logMov = $pdo->prepare("INSERT INTO movimientosdestock (codigo, rubro, entradas, salidas, tipomov, origenmov, esfiscal, tipocomp, numremito, fecha, usuario)
                                     VALUES (?, ?, ?, 0, 'ANULACION PRODUCCION', 'PRODUCCION', 0, 0, ?, NOW(), ?)");

            foreach ($stmtEnt->fetchAll(PDO::FETCH_ASSOC) as $e) {
                $cant = (float) $e['cantidad_requerida'];
                $logMov->execute([$e['codigo'], $e['rubro'], $cant, $idOrden, $usuario]);
                $idMovLegacy = (int) $pdo->lastInsertId();

                // Devuelve al MISMO depósito del que se había reservado (guardado al confirmar),
                // no al depósito de trabajo actual del usuario que cancela.
                moverStock($pdo, (int) $e['id_producto'], $e['codigo'], $e['id_deposito'] ?? null, $cant, 'ENTRADA',
                           'ANULACION PRODUCCION', 'PRODUCCION', 0, $idOrden, $usuario, $idMovLegacy);
                $updComprometido->execute([$cant, $e['id_producto']]);
            }

            $updSal = $pdo->prepare("UPDATE alb_productos SET stkproduccion = stkproduccion - ? WHERE id = ?");
            $stmtSal = $pdo->prepare("SELECT producto_terminado_id as id_producto, cantidad_a_producir FROM produccion_ordenes_salidas WHERE orden_id = ?");
            $stmtSal->execute([$idOrden]);
            foreach ($stmtSal->fetchAll(PDO::FETCH_ASSOC) as $s) {
                $updSal->execute([$s['cantidad_a_producir'], $s['id_producto']]);
            }
        }

        $notaFinal = trim($orden['notas'] . ($motivo !== '' ? "\n[CANCELADA] $motivo" : "\n[CANCELADA]"));
        $pdo->prepare("UPDATE produccion_ordenes SET estado = ?, fecha_fin_real = NOW(), notas = ? WHERE id = ?")
            ->execute([ESTADO_CANCELADA, $notaFinal, $idOrden]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Orden #{$orden['numero_orden']} cancelada."]);
        exit;
    }

    if ($action === 'get_receta_por_producto') {
        $idProd = $_GET['id_producto'];
        $stmt = $pdo->prepare("SELECT id FROM produccion_listamateriales_enc WHERE producto_terminado_id = ? AND activa = 1 ORDER BY version DESC LIMIT 1");
        $stmt->execute([$idProd]);
        $receta = $stmt->fetch();

        if ($receta) {
            $stmtDet = $pdo->prepare("SELECT d.producto_componente_id as id_producto, p.codigo, p.descripcion, d.cantidad 
                                      FROM produccion_listamateriales_det d
                                      JOIN alb_productos p ON d.producto_componente_id = p.id
                                      WHERE d.listamateriales_id = ?");
            $stmtDet->execute([$receta['id']]);
            echo json_encode(['success' => true, 'found' => true, 'insumos' => $stmtDet->fetchAll()]);
        } else {
            echo json_encode(['success' => true, 'found' => false]);
        }
        exit;
    }

    // --- FINALIZAR ORDEN (Consumo Definitivo y Alta de Producto) ---
    // Recibe: { id, consumos: [{id_producto, cantidad_consumida}], modo_costeo: 'auto'|'manual',
    //           costos_manuales: [{id_producto, costo_unitario}] }
    if ($action === 'finalizar_orden' && $method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        $idOrden = $data['id'];
        $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

        // 1. Validar Estado
        $chk = $pdo->prepare("SELECT estado, numero_orden FROM produccion_ordenes WHERE id = ?");
        $chk->execute([$idOrden]);
        $orden = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$orden) throw new Exception("Orden no encontrada.");

        $estadoActual = (int) $orden['estado'];
        if ($estadoActual === ESTADO_FINALIZADA) {
            echo json_encode(['success' => false, 'error' => 'Esta orden ya está finalizada.']); exit;
        }
        if (!in_array($estadoActual, [ESTADO_CONFIRMADA, ESTADO_EN_PROCESO])) {
            echo json_encode(['success' => false, 'error' => 'La orden debe estar CONFIRMADA para poder finalizarse.']); exit;
        }

        $consumosPorProducto = [];
        foreach (($data['consumos'] ?? []) as $c) {
            $consumosPorProducto[(int) $c['id_producto']] = (float) $c['cantidad_consumida'];
        }

        $ejerPer = resolverEjercicioPeriodo($pdo, date('Y-m-d'));

        $pdo->beginTransaction();

        // 2. Obtener Insumos Requeridos (lo reservado al confirmar)
        $stmtEnt = $pdo->prepare("SELECT oe.producto_componente_id as id_producto, oe.cantidad_requerida, oe.id_deposito, p.sinivacosto, p.codigo, p.cuentamerca
                                  FROM produccion_ordenes_entradas oe
                                  JOIN alb_productos p ON oe.producto_componente_id = p.id
                                  WHERE oe.orden_id = ?");
        $stmtEnt->execute([$idOrden]);
        $insumos = $stmtEnt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Consumir insumos por lo REALMENTE consumido (merma = requerida - consumida).
        //    Se libera siempre toda la reserva; si se consumió de más se descuenta la diferencia
        //    del stock físico (validando que alcance), si se consumió de menos se devuelve el sobrante.
        $updComprometido  = $pdo->prepare("UPDATE alb_productos SET stock_comprometido_produccion = stock_comprometido_produccion - ? WHERE id = ?");
        $updConsumido     = $pdo->prepare("UPDATE produccion_ordenes_entradas SET cantidad_consumida = ? WHERE orden_id = ? AND producto_componente_id = ?");
        $stmtStockActual  = $pdo->prepare("SELECT Stock FROM alb_productos WHERE id = ?");

        $totalCostoEntradas = 0;
        $cpProd = $pdo->query("SELECT ajuste, mercaderia FROM cuentaspredeterminadas WHERE id = 1")->fetch();
        $totMercaInsumo = [];

        foreach ($insumos as $i) {
            $idProd = (int) $i['id_producto'];
            $requerida = (float) $i['cantidad_requerida'];
            $consumida = $consumosPorProducto[$idProd] ?? $requerida;
            $costoUnitario = (float) $i['sinivacosto'];
            $diff = round($consumida - $requerida, 4);

            $updComprometido->execute([$requerida, $idProd]);

            if ($diff > 0.0001) {
                $stmtStockActual->execute([$idProd]);
                $stockActual = (float) $stmtStockActual->fetchColumn();
                if ($stockActual < $diff) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'error' => "No hay stock suficiente de {$i['codigo']} para el consumo adicional (faltan " . number_format($diff - $stockActual, 2) . ")."]);
                    exit;
                }
                moverStock($pdo, $idProd, $i['codigo'], $i['id_deposito'] ?? null, $diff, 'SALIDA',
                           'AJUSTE PRODUCCION', 'PRODUCCION', 0, $idOrden, $usuario);
            } elseif ($diff < -0.0001) {
                moverStock($pdo, $idProd, $i['codigo'], $i['id_deposito'] ?? null, abs($diff), 'ENTRADA',
                           'AJUSTE PRODUCCION', 'PRODUCCION', 0, $idOrden, $usuario);
            }

            $updConsumido->execute([$consumida, $idOrden, $idProd]);
            $costoLinea = $costoUnitario * $consumida;
            $totalCostoEntradas += $costoLinea;

            $ctaInsumo = $i['cuentamerca'] ?: $cpProd['mercaderia'];
            $totMercaInsumo[$ctaInsumo] = ($totMercaInsumo[$ctaInsumo] ?? 0) + $costoLinea;
        }

        // 4. Procesar Salidas (Productos Terminados) + costeo
        $stmtSal = $pdo->prepare("SELECT os.producto_terminado_id as id_producto, os.cantidad_a_producir as cantidad, os.id_deposito,
                                         p.codigo, p.descripcion, p.rubro, p.poriva, p.precio, p.cuentamerca
                                  FROM produccion_ordenes_salidas os
                                  JOIN alb_productos p ON os.producto_terminado_id = p.id
                                  WHERE os.orden_id = ?");
        $stmtSal->execute([$idOrden]);
        $salidas = $stmtSal->fetchAll(PDO::FETCH_ASSOC);
        if (empty($salidas)) throw new Exception("La orden no tiene productos de salida.");

        $modoCosteo = (($data['modo_costeo'] ?? 'auto') === 'manual') ? 'manual' : 'auto';
        $costosManuales = [];
        foreach (($data['costos_manuales'] ?? []) as $c) {
            $costosManuales[(int) $c['id_producto']] = (float) $c['costo_unitario'];
        }

        // Costo unitario por producto de salida:
        // - Un solo producto: recibe el 100% del costo de insumos (igual que siempre).
        // - Varios productos, modo manual: el costo unitario lo asigna el usuario por producto,
        //   sin forzar que sumen el costo total (una carnicería puede valorizar cortes premium
        //   por encima del promedio y descartes cerca de cero; es una decisión de negocio válida).
        // - Varios productos, modo automático: reparto proporcional al valor de venta relativo
        //   (precio * cantidad) de cada producto — método estándar de costeo conjunto.
        $costoUnitarioPorProducto = [];

        if (count($salidas) === 1) {
            $cant = (float) $salidas[0]['cantidad'];
            $costoUnitarioPorProducto[(int) $salidas[0]['id_producto']] = ($cant > 0) ? round($totalCostoEntradas / $cant, 2) : 0;
        } elseif ($modoCosteo === 'manual') {
            foreach ($salidas as $s) {
                $costoUnitarioPorProducto[(int) $s['id_producto']] = $costosManuales[(int) $s['id_producto']] ?? 0;
            }
        } else {
            $pesoTotal = 0;
            foreach ($salidas as $s) $pesoTotal += ((float) $s['precio'] * (float) $s['cantidad']);

            foreach ($salidas as $s) {
                $cant = (float) $s['cantidad'];
                $peso = (float) $s['precio'] * $cant;
                $proporcion = ($pesoTotal > 0) ? ($peso / $pesoTotal) : (1 / count($salidas));
                $costoAsignado = $totalCostoEntradas * $proporcion;
                $costoUnitarioPorProducto[(int) $s['id_producto']] = ($cant > 0) ? round($costoAsignado / $cant, 2) : 0;
            }
        }

        $updProd = $pdo->prepare("UPDATE alb_productos SET
                                  stkproduccion = stkproduccion - :cant,
                                  sinivacosto = :nc, ivacosto = :ic, preciocosto = :pc
                                  WHERE id = :id");

        $logMov = $pdo->prepare("INSERT INTO movimientosdestock (codigo, rubro, entradas, salidas, tipomov, origenmov, esfiscal, tipocomp, numremito, fecha, usuario)
                                 VALUES (:cod, :rub, :ent, 0.00, 'INGRESO PRODUCCION', 'PRODUCCION', 0, 0, :numOP, NOW(), :usr)");

        $logIng = $pdo->prepare("INSERT INTO ingresosdestock (stock, tipoingreso, tipocomp, numcomp, codigo, descripcion, ingreso, fecha, user, netocosto, ivacosto, preciocosto, nota)
                                 VALUES (:cant, 1, 999, :numOP, :cod, :desc, :cant, CURRENT_DATE, :usr, :nc, :ic, :pc, 'INGRESO POR PRODUCCION')");

        $updCostoSalida = $pdo->prepare("UPDATE produccion_ordenes_salidas SET costo_unitario_asignado = ? WHERE orden_id = ? AND producto_terminado_id = ?");
        $totMercaSalida = [];

        foreach ($salidas as $s) {
            $idProd = (int) $s['id_producto'];
            $cant = (float) $s['cantidad'];
            $nuevoCostoUnitarioSinIva = $costoUnitarioPorProducto[$idProd] ?? 0;
            $ivaPorc = floatval($s['poriva']) / 100;
            $nuevoIvaCosto = round($nuevoCostoUnitarioSinIva * $ivaPorc, 2);
            $nuevoPrecioCosto = $nuevoCostoUnitarioSinIva + $nuevoIvaCosto;

            // A. Actualizar Producto (Entra a Stock Físico, Sale de Stock Producción, Actualiza Costos)
            $updProd->execute([
                ':cant' => $cant,
                ':nc' => $nuevoCostoUnitarioSinIva,
                ':ic' => $nuevoIvaCosto,
                ':pc' => $nuevoPrecioCosto,
                ':id' => $idProd
            ]);

            // B. Log Movimientos (movimientosdestock)
            $logMov->execute([
                ':cod' => $s['codigo'],
                ':rub' => $s['rubro'],
                ':ent' => $cant,
                ':numOP' => $idOrden,
                ':usr' => $usuario
            ]);
            $idMovLegacy = (int) $pdo->lastInsertId();

            // A2. Stock físico del producto terminado (vía helper: respeta depósito)
            moverStock($pdo, $idProd, $s['codigo'], $s['id_deposito'] ?? null, $cant, 'ENTRADA',
                       'INGRESO PRODUCCION', 'PRODUCCION', 0, $idOrden, $usuario, $idMovLegacy);

            // C. Log Ingreso (ingresosdestock)
            $logIng->execute([
                ':numOP' => $idOrden,
                ':cod' => $s['codigo'],
                ':desc' => $s['descripcion'],
                ':cant' => $cant,
                ':usr' => $usuario,
                ':nc' => $nuevoCostoUnitarioSinIva,
                ':ic' => $nuevoIvaCosto,
                ':pc' => $nuevoPrecioCosto
            ]);

            // D. Trazabilidad: costo que se le asignó a este producto en esta orden puntual
            $updCostoSalida->execute([$nuevoCostoUnitarioSinIva, $idOrden, $idProd]);

            $ctaSalida = $s['cuentamerca'] ?: $cpProd['mercaderia'];
            $totMercaSalida[$ctaSalida] = ($totMercaSalida[$ctaSalida] ?? 0) + round($nuevoCostoUnitarioSinIva * $cant, 2);
        }

        // E. Asiento contable: mercadería de insumos (HABER) pasa a mercadería de producto
        // terminado (DEBE), por cuenta de cada producto. Si el valor asignado a las salidas no
        // coincide con lo que costaron los insumos (solo posible en modo manual), la diferencia
        // se postea contra 'ajuste' para que el asiento cierre.
        $numAsientoProd = null;
        $detalleAsientoProd = "Producción {$orden['numero_orden']}";
        $fechaProd = date('Y-m-d H:i:s');

        $stmtNumProd = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
        $stmtNumProd->execute([$ejerPer['ejercicio']]);
        $numAsientoProd = ((int) $stmtNumProd->fetchColumn()) + 1;

        // La diferencia se calcula sobre los mismos montos YA redondeados por cuenta que se
        // postean abajo, para garantizar que el asiento cierre exacto al centavo.
        $totalValorSalidas = 0;
        foreach ($totMercaSalida as $monto) $totalValorSalidas += round($monto, 2);
        $totalCostoInsumosRedondeado = 0;
        foreach ($totMercaInsumo as $monto) $totalCostoInsumosRedondeado += round($monto, 2);
        $diferenciaProd = round($totalValorSalidas - $totalCostoInsumosRedondeado, 2);

        foreach ($totMercaSalida as $cta => $monto) {
            grabarLineaAsiento($pdo, $numAsientoProd, $fechaProd, $cta, round($monto, 2), 0, $idOrden, "produccion_ordenes", 1, $usuario, $detalleAsientoProd, $ejerPer['ejercicio'], $ejerPer['periodo']);
        }
        foreach ($totMercaInsumo as $cta => $monto) {
            grabarLineaAsiento($pdo, $numAsientoProd, $fechaProd, $cta, 0, round($monto, 2), $idOrden, "produccion_ordenes", 1, $usuario, $detalleAsientoProd, $ejerPer['ejercicio'], $ejerPer['periodo']);
        }
        if ($diferenciaProd < 0) {
            // Las salidas se valorizaron por menos de lo que costaron los insumos: pérdida.
            grabarLineaAsiento($pdo, $numAsientoProd, $fechaProd, $cpProd['ajuste'], abs($diferenciaProd), 0, $idOrden, "produccion_ordenes", 1, $usuario, $detalleAsientoProd, $ejerPer['ejercicio'], $ejerPer['periodo']);
        } elseif ($diferenciaProd > 0) {
            // Las salidas se valorizaron por más de lo que costaron los insumos: ganancia.
            grabarLineaAsiento($pdo, $numAsientoProd, $fechaProd, $cpProd['ajuste'], 0, $diferenciaProd, $idOrden, "produccion_ordenes", 1, $usuario, $detalleAsientoProd, $ejerPer['ejercicio'], $ejerPer['periodo']);
        }

        // 5. Cerrar Orden
        $pdo->prepare("UPDATE produccion_ordenes SET estado = ?, fecha_fin_real = NOW(), modo_costeo = ? WHERE id = ?")
            ->execute([ESTADO_FINALIZADA, strtoupper($modoCosteo), $idOrden]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => "Orden #{$orden['numero_orden']} finalizada. Stock y Costos actualizados."]);
        exit;
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>