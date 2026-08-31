<?php
/**
 * api_pedidos_admin.php - BACKEND ADMINISTRACIÓN PEDIDOS
 * Migración exacta de lógica de "Clientes - Ver Pedidos.vb" + Cálculo dinámico de totales
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    // Helper para sanitizar (Lógica Legacy)
    $pdo->query("UPDATE lineapedidosclientes SET descuento = 0 WHERE descuento IS NULL");

    switch ($action) {
        case 'list':
        case 'export':
            $input = ($action === 'list') ? json_decode(file_get_contents('php://input'), true) : $_GET;
            
            $desde = $input['desde'];
            $hasta = $input['hasta'];
            $tipoFecha = $input['tipo_fecha']; // 'entrega' o 'carga'
            $estado = $input['estado']; // 0, 1, 2, todos
            $busqueda = $input['busqueda'] ?? '';
            $vendedor_id = $input['vendedor_id'] ?? 0;

            // Seguridad: Vendedor restringido (se fuerza, ignorando lo que pida el front)
            if (esVendedorRestringido()) {
                $vendedor_id = idVendedorRestringido();
            }

            // Selección de campo de fecha según filtro (VB Logic)
            // VB usa fechapedido para carga y fechaentrega para entrega.
            // Si la columna fechapedido no existe en tu DB real (revisar estructura), usa fechaentrega o la que corresponda.
            // Asumiendo que existen por el código VB.
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

            // CONSULTA CORREGIDA: Calculamos el total al vuelo sumando las líneas
            // Formula: Cantidad * PrecioUnitario - (Cantidad * PrecioUnitario * Descuento / 100)
            // Se añaden COALESCE para robustez.
            // Nota: VB inserta el descuento como porcentaje en la línea.
            
            $sql = "SELECT 
                        p.cliente as num_cliente,
                        c.razon as cliente,
                        p.fechapedido as fecha_carga,
                        p.numpedido,
                        p.observaciones,
                        p.fechaentrega as fecha_entrega,
                        (SELECT SUM(cantidad) FROM lineapedidosclientes WHERE numpedido = p.numpedido) as cantidad_productos,
                        t.razon as transporte,
                        c.horario,
                        CASE p.estadofac 
                            WHEN 1 THEN 'Facturado' 
                            WHEN 0 THEN 'Sin Facturar' 
                            WHEN 2 THEN 'Anulado' 
                        END as estado_str,
                        p.estadofac,
                        v.nombre as vendedor,
                        p.impreso,
                        -- CÁLCULO DINÁMICO DEL TOTAL (La columna total no existe en pedidosclientes)
                        COALESCE((
                            SELECT SUM(
                                (l.cantidad * l.preciounit) - ((l.cantidad * l.preciounit) * (COALESCE(l.descuento, 0) / 100))
                            )
                            FROM lineapedidosclientes l 
                            WHERE l.numpedido = p.numpedido
                        ), 0) as total
                    FROM pedidosclientes p
                    LEFT JOIN alb_clientes c ON p.cliente = c.id
                    LEFT JOIN transportes t ON c.transporte = t.id
                    LEFT JOIN vendedores v ON p.vendedorid = v.id
                    $where
                    ORDER BY p.numpedido DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($action === 'export') {
                generarExcelPedidos($data, $desde, $hasta);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;

        case 'annul':
            $input = json_decode(file_get_contents('php://input'), true);
            $numpedido = $input['numpedido'];

            $stmtCheck = $pdo->prepare("SELECT estadofac FROM pedidosclientes WHERE numpedido = ?");
            $stmtCheck->execute([$numpedido]);
            $estadoActual = $stmtCheck->fetchColumn();

            if ($estadoActual == 2) throw new Exception("El pedido seleccionado ya estaba anulado");
            if ($estadoActual == 1) throw new Exception("El pedido seleccionado ya ha sido facturado, no se puede anular.");

            $stmtUpd = $pdo->prepare("UPDATE pedidosclientes SET estadofac = 2 WHERE numpedido = ?");
            if ($stmtUpd->execute([$numpedido])) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("No se pudo procesar la solicitud");
            }
            break;
            
        case 'restore':
            $input = json_decode(file_get_contents('php://input'), true);
            $numpedido = $input['numpedido'];
            
            $stmtCheck = $pdo->prepare("SELECT estadofac FROM pedidosclientes WHERE numpedido = ?");
            $stmtCheck->execute([$numpedido]);
            if ($stmtCheck->fetchColumn() != 2) throw new Exception("El pedido seleccionado no está anulado");

            $stmtUpd = $pdo->prepare("UPDATE pedidosclientes SET estadofac = 0 WHERE numpedido = ?");
            if ($stmtUpd->execute([$numpedido])) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("No se pudo procesar la solicitud");
            }
            break;
    }
} catch (Exception $e) {
    if ($action === 'export') echo "Error: " . $e->getMessage();
    else echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Generación de Excel con Estilos (Formato Tabla HTML)
 */
function generarExcelPedidos($data, $desde, $hasta) {
    $filename = "Pedidos_Venta_" . date('Ymd_His') . ".xls";
    
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");

    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta http-equiv='Content-type' content='text/html;charset=utf-8' />
        <style>
            body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 11px; }
            .header-main { background-color: #000020; color: #00ffff; font-size: 16px; font-weight: bold; text-align: center; border: 1px solid #000; height: 30px; vertical-align: middle; }
            .header-sub { background-color: #0d2b45; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
            .th-col { background-color: #0d2b45; color: #ffffff; border: 1px solid #999; text-align: center; font-weight: bold; padding: 5px; }
            .td-row { border: 1px solid #ccc; vertical-align: middle; padding: 4px; }
            .num { text-align: right; mso-number-format:\"\#\,\#\#0\.00\"; }
            .date { text-align: center; }
            .text-danger { color: #ff0000; font-weight: bold; }
            .text-success { color: #008000; font-weight: bold; }
            .bold { font-weight: bold; }
        </style>
    </head>
    <body>
        <table>
            <tr><td colspan='10' class='header-main'>LISTADO DE PEDIDOS DE VENTA - WEST IT ERP</td></tr>
            <tr><td colspan='10' class='header-sub'>Período: $desde al $hasta</td></tr>
            <tr><td colspan='10'></td></tr>
            <thead>
                <tr>
                    <th class='th-col'>FECHA</th>
                    <th class='th-col'>Nº PEDIDO</th>
                    <th class='th-col'>CLIENTE</th>
                    <th class='th-col'>CANT. PROD</th>
                    <th class='th-col'>TOTAL ($)</th>
                    <th class='th-col'>OBSERVACIONES</th>
                    <th class='th-col'>TRANSPORTE</th>
                    <th class='th-col'>VENDEDOR</th>
                    <th class='th-col'>ESTADO</th>
                    <th class='th-col'>IMPRESO</th>
                </tr>
            </thead>
            <tbody>";

    $totalGral = 0;

    foreach ($data as $row) {
        $fecha = date('d/m/Y', strtotime($row['fecha_carga']));
        $impreso = $row['impreso'] == 1 ? 'SI' : 'NO';
        $style = $row['impreso'] == 0 ? "font-weight: bold;" : ""; // Negrita si no impreso
        
        $styleEstado = "";
        if ($row['estadofac'] == 2) $styleEstado = "color: #ff0000; font-weight: bold;"; // Anulado
        if ($row['estadofac'] == 1) $styleEstado = "color: #008000; font-weight: bold;"; // Facturado

        $totalGral += floatval($row['total']);
        
        echo "<tr style='$style'>
                <td class='td-row date'>$fecha</td>
                <td class='td-row date'>{$row['numpedido']}</td>
                <td class='td-row'>{$row['cliente']}</td>
                <td class='td-row date'>{$row['cantidad_productos']}</td>
                <td class='td-row num'>{$row['total']}</td>
                <td class='td-row'>{$row['observaciones']}</td>
                <td class='td-row'>{$row['transporte']}</td>
                <td class='td-row'>{$row['vendedor']}</td>
                <td class='td-row date' style='$styleEstado'>{$row['estado_str']}</td>
                <td class='td-row date'>$impreso</td>
              </tr>";
    }

    echo "
            <tr>
                <td colspan='4' class='td-row' style='text-align: right; font-weight: bold; background-color: #f0f0f0;'>TOTAL GENERAL:</td>
                <td class='td-row num' style='font-weight: bold; background-color: #f0f0f0;'>" . number_format($totalGral, 2, ',', '.') . "</td>
                <td colspan='5' class='td-row' style='background-color: #f0f0f0;'></td>
            </tr>
            </tbody>
        </table>
    </body>
    </html>";
    exit;
}
