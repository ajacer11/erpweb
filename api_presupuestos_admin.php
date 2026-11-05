<?php
/**
 * api_presupuestos_admin.php - MOTOR BACKEND PARA ADMINISTRACIÓN DE PRESUPUESTOS
 * Actualizado con nuevos estados (1:Pendiente, 2:Anulado, 3:Aprobado, 4:Facturado, 5:Rechazado)
 */
require_once 'check_api_access.php';
require_once 'precios_listas.php';
require_once 'permisos_helpers.php';

/**
 * Devuelve, para un set de códigos de producto, sus 8 precios de lista
 * (siniva..siniva8) en un solo query. [codigo => ['siniva'=>.., 'siniva2'=>.., ...]]
 */
function obtenerPreciosActualesProductos(PDO $pdo, array $codigos): array
{
    $codigos = array_values(array_unique($codigos));
    if (empty($codigos))
        return [];
    $ph = implode(',', array_fill(0, count($codigos), '?'));
    $stmt = $pdo->prepare("SELECT codigo, " . sqlNetosListas() . "
                            FROM alb_productos WHERE codigo IN ($ph)");
    $stmt->execute($codigos);
    $out = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)
        $out[$row['codigo']] = $row;
    return $out;
}

/** Elige la columna siniva/siniva2..8 correspondiente a $lista (1-8) del mapa de un producto. */
function precioActualParaLista(array $preciosProducto, int $lista): float
{
    $lista = max(1, min(8, $lista));
    $col = $lista === 1 ? 'siniva' : 'siniva' . $lista;
    return isset($preciosProducto[$col]) ? (float) $preciosProducto[$col] : 0.0;
}

/**
 * Compara, línea por línea, el precio guardado (preciounit) contra el precio
 * actual de alb_productos para la lista propia de esa línea.
 * $lineas: filas de lineapresupuestosclientes (necesita producto, lista, preciounit).
 */
function calcularDiferenciaPrecios(PDO $pdo, array $lineas): array
{
    $mapa = obtenerPreciosActualesProductos($pdo, array_column($lineas, 'producto'));
    $hayDiferencia = false;
    $detalle = [];
    foreach ($lineas as $l) {
        if ($l['producto'] === 'LM')
            continue; // línea manual: no tiene precio de catálogo con el cual comparar
        $actual = precioActualParaLista($mapa[$l['producto']] ?? [], (int) $l['lista']);
        $guardado = (float) $l['preciounit'];
        $difiere = abs($actual - $guardado) > 0.005; // tolerancia de centavos
        if ($difiere)
            $hayDiferencia = true;
        $detalle[] = ['producto' => $l['producto'], 'lista' => (int) $l['lista'],
            'precio_guardado' => $guardado, 'precio_actual' => $actual, 'difiere' => $difiere];
    }
    return ['hay_diferencia' => $hayDiferencia, 'detalle' => $detalle];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'check_price_diff':
            $numpresupuesto = $_GET['numpedido'] ?? 0;
            $stmtCab = $pdo->prepare("SELECT fechaentrega, estadofac FROM presupuestosclientes WHERE numpedido = ?");
            $stmtCab->execute([$numpresupuesto]);
            $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);
            if (!$cab)
                throw new Exception("Presupuesto no encontrado.");

            $stmtLin = $pdo->prepare("SELECT producto, lista, preciounit FROM lineapresupuestosclientes WHERE numpedido = ?");
            $stmtLin->execute([$numpresupuesto]);
            $lineas = $stmtLin->fetchAll(PDO::FETCH_ASSOC);

            $vencido = strtotime($cab['fechaentrega']) < strtotime('today');
            $r = calcularDiferenciaPrecios($pdo, $lineas);

            echo json_encode([
                'success' => true,
                'vencido' => $vencido,
                'hay_diferencia' => $r['hay_diferencia'],
                'requiere_confirmacion' => $vencido && $r['hay_diferencia'],
                'detalle' => $r['detalle']
            ]);
            break;

        case 'list':
        case 'export':
            $input = ($action === 'list') ? json_decode(file_get_contents('php://input'), true) : $_GET;

            $desde = $input['desde'];
            $hasta = $input['hasta'];
            $tipoFecha = $input['tipo_fecha'];
            $estado = $input['estado'];
            $busqueda = $input['busqueda'] ?? '';
            $vendedor_id = $input['vendedor_id'] ?? 0;

            // Seguridad: Vendedor restringido (se fuerza, ignorando lo que pida el front)
            if (esVendedorRestringido()) {
                $vendedor_id = idVendedorRestringido();
            }

            $campoFecha = ($tipoFecha === 'carga') ? 'p.fechapedido' : 'p.fechaentrega';

            $where = " WHERE $campoFecha >= ? AND $campoFecha <= ? ";
            $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];

            if ($estado !== 'todos') {
                $where .= " AND p.estadofac = ? ";
                $params[] = $estado;
            }

            if ($vendedor_id > 0) {
                $where .= " AND p.vendedorid = ? ";
                $params[] = $vendedor_id;
            }

            if (!empty($busqueda)) {
                $where .= " AND (c.razon LIKE ? OR p.numpedido LIKE ?) ";
                $params[] = "%$busqueda%";
                $params[] = "%$busqueda%";
            }

            $sql = "SELECT 
                        p.numpedido, p.fechapedido as fecha_carga, p.fechaentrega as fecha_entrega, p.observaciones, p.estadofac,
                        c.razon as cliente_razon, v.nombre as vendedor, t.razon as transporte,
                        (SELECT SUM(cantidad) FROM lineapresupuestosclientes WHERE numpedido = p.numpedido) as cantidad_productos,
                        COALESCE((
                            SELECT SUM((l.cantidad * l.preciounit) * (1 - COALESCE(l.descuento, 0) / 100))
                            FROM lineapresupuestosclientes l WHERE l.numpedido = p.numpedido
                        ), 0) as total_estimado
                    FROM presupuestosclientes p
                    LEFT JOIN alb_clientes c ON p.cliente = c.id
                    LEFT JOIN vendedores v ON p.vendedorid = v.id
                    LEFT JOIN transportes t ON c.transporte = t.id
                    $where
                    ORDER BY p.numpedido DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($action === 'export') {
                echo "Exportación no implementada en este snippet";
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;

        case 'annul':
            $input = json_decode(file_get_contents('php://input'), true);
            // Solo se anula si está Pendiente (1)
            $pdo->prepare("UPDATE presupuestosclientes SET estadofac = 2 WHERE numpedido = ? AND estadofac = 1")->execute([$input['numpedido']]);
            echo json_encode(['success' => true]);
            break;

        case 'approve':
            $input = json_decode(file_get_contents('php://input'), true);
            $numpresupuesto = $input['numpedido'];
            $usarPreciosActuales = !empty($input['usar_precios_actuales']);

            $pdo->beginTransaction();

            try {
                // 1. Obtener y verificar la cabecera del presupuesto original (debe estar en estado 1 - Pendiente)
                $stmtCheck = $pdo->prepare("SELECT * FROM presupuestosclientes WHERE numpedido = ? AND estadofac = 1");
                $stmtCheck->execute([$numpresupuesto]);
                $presupuestoCab = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if (!$presupuestoCab) {
                    throw new Exception("El presupuesto no está pendiente o no existe.");
                }

                // 2. Cambiar el estado del presupuesto a 3 (Aprobado)
                $pdo->prepare("UPDATE presupuestosclientes SET estadofac = 3 WHERE numpedido = ?")->execute([$numpresupuesto]);

                // 3. Obtener las líneas originales del presupuesto
                $stmtLineas = $pdo->prepare("SELECT * FROM lineapresupuestosclientes WHERE numpedido = ?");
                $stmtLineas->execute([$numpresupuesto]);
                $lineasPresupuesto = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

                // 4. Generar el próximo número de pedido correlativo para la tabla de pedidos
                // (Verifica en tu api_pedidos.php si la tabla se llama 'pedidosclientes' o 'pedidos')
                $stmtNumPed = $pdo->query("SELECT COALESCE(MAX(numpedido), 0) + 1 FROM pedidosclientes FOR UPDATE");
                $nuevoNumPedido = $stmtNumPed->fetchColumn();

                // 5. Insertar la cabecera en la tabla de pedidos
                // (Usamos estado 1 o el que use por defecto tu módulo de pedidos para 'Pendiente')
                $sqlInsertPed = "INSERT INTO pedidosclientes (cliente, estadofac, numpedido, fechaentrega, observaciones, vendedorid, fechapedido) 
                                 VALUES (?, 0, ?, ?, ?, ?, NOW())";
                $pdo->prepare($sqlInsertPed)->execute([
                    $presupuestoCab['cliente'],
                    $nuevoNumPedido,
                    $presupuestoCab['fechaentrega'],
                    $presupuestoCab['observaciones'] . " (Gen. desde Presupuesto #$numpresupuesto)",
                    $presupuestoCab['vendedorid']
                ]);

                // 6. Traspasar e insertar cada una de las líneas en la tabla de líneas de pedido
                $mapaPreciosActuales = $usarPreciosActuales
                    ? obtenerPreciosActualesProductos($pdo, array_column($lineasPresupuesto, 'producto'))
                    : [];

                $sqlInsertLineas = "INSERT INTO lineapedidosclientes (numpedido, producto, cantidad, descripcion, lista, descuento, preciounit, impint, poriva)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtInsertL = $pdo->prepare($sqlInsertLineas);

                foreach ($lineasPresupuesto as $linea) {
                    // Línea manual (LM): nunca se le aplica el toggle de precio actual/guardado
                    // (no tiene producto real de catálogo para recalcular), y su IVA propio se traspasa tal cual.
                    $esLineaManual = $linea['producto'] === 'LM';
                    $preciounit = ($usarPreciosActuales && !$esLineaManual)
                        ? precioActualParaLista($mapaPreciosActuales[$linea['producto']] ?? [], (int) $linea['lista'])
                        : $linea['preciounit'];

                    $stmtInsertL->execute([
                        $nuevoNumPedido,
                        $linea['producto'],
                        $linea['cantidad'],
                        $linea['descripcion'],
                        $linea['lista'],
                        $linea['descuento'],
                        $preciounit,
                        $linea['impint'],
                        $esLineaManual ? ($linea['poriva'] ?? null) : null
                    ]);
                }

                // 7. Registrar en el historial/seguimiento de presupuestos
                $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
                $detalleSeg = "PRESUPUESTO APROBADO Y CONVERTIDO EN PEDIDO #$nuevoNumPedido";
                if ($usarPreciosActuales)
                    $detalleSeg .= " (usando PRECIOS ACTUALES de lista)";
                $pdo->prepare("INSERT INTO seguimientopresupuestos (numpresupuesto, fecha, usuario, detalle) VALUES (?, NOW(), ?, ?)")
                    ->execute([$numpresupuesto, $usuario, $detalleSeg]);

                $pdo->commit();
                echo json_encode(['success' => true, 'nuevo_pedido' => $nuevoNumPedido]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'reject':
            $input = json_decode(file_get_contents('php://input'), true);
            // Solo se rechaza si está Pendiente (1)
            $pdo->prepare("UPDATE presupuestosclientes SET estadofac = 5 WHERE numpedido = ? AND estadofac = 1")->execute([$input['numpedido']]);
            echo json_encode(['success' => true]);
            break;

        case 'restore':
            $input = json_decode(file_get_contents('php://input'), true);
            // Solo se restaura si está Anulado (2) o Rechazado (5) devolviéndolo a Pendiente (1)
            $pdo->prepare("UPDATE presupuestosclientes SET estadofac = 1 WHERE numpedido = ? AND estadofac IN (2, 5)")->execute([$input['numpedido']]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}