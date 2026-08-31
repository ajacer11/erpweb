<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_filters') {
        $data = [];
        // Carga de combos (Datos Maestros)
        $data['clientes'] = $pdo->query("SELECT id, razon FROM alb_clientes ORDER BY razon ASC")->fetchAll(PDO::FETCH_ASSOC);
        // PERMISO ESPECIAL: un usuario restringido a "un solo vendedor" solo ve su
        // propio nombre en el combo (no tiene sentido ofrecerle elegir a otro).
        if (esVendedorRestringido()) {
            $stmtVendRest = $pdo->prepare("SELECT id, nombre FROM vendedores WHERE habilitado = 1 AND id = ? ORDER BY nombre ASC");
            $stmtVendRest->execute([idVendedorRestringido()]);
            $data['vendedores'] = $stmtVendRest->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $data['vendedores'] = $pdo->query("SELECT id, nombre FROM vendedores WHERE habilitado = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
        }
        $data['productos'] = $pdo->query("SELECT codigo, Descripcion FROM alb_productos ORDER BY Descripcion ASC")->fetchAll(PDO::FETCH_ASSOC);
        $data['rubros'] = $pdo->query("SELECT rubro FROM rubros ORDER BY rubro ASC")->fetchAll(PDO::FETCH_ASSOC);
        // Linux distingue mayúsculas/minúsculas en nombres de tablas.
        $data['marcas'] = $pdo->query("SELECT id, marca FROM Marcas ORDER BY marca ASC")->fetchAll(PDO::FETCH_ASSOC);
        $data['familias'] = $pdo->query("SELECT id, nombre as familia FROM familias WHERE habilitada = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
        $data['unidades'] = $pdo->query("SELECT id, nombre FROM unidadesdenegocio ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
        $data['provincias'] = $pdo->query("SELECT DISTINCT provincia FROM alb_clientes WHERE provincia IS NOT NULL AND provincia <> '' ORDER BY provincia ASC")->fetchAll(PDO::FETCH_COLUMN);
        $data['ramas'] = $pdo->query("SELECT id, rama FROM ramasclientes ORDER BY rama ASC")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data);

    } elseif ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);

        $desde = $input['desde'] ?? date('Y-m-01');
        $hasta = $input['hasta'] ?? date('Y-m-d');

        // Construcción del WHERE
        $where = " WHERE a.cliente <> 0 AND j.razon <> 'INGRESO DE STOCK' AND a.fecha BETWEEN ? AND ? ";
        $params = [$desde, $hasta];

        if (!empty($input['cliente_id'])) { $where .= " AND a.cliente = ? "; $params[] = $input['cliente_id']; }
        // PERMISO ESPECIAL: fuerza el propio vendedor sin importar qué haya elegido
        // el usuario en el filtro (o si no eligió ninguno).
        if (esVendedorRestringido()) {
            $where .= " AND a.vendedor = ? "; $params[] = idVendedorRestringido();
        } elseif (!empty($input['vendedor_id'])) {
            $where .= " AND a.vendedor = ? "; $params[] = $input['vendedor_id'];
        }
        if (!empty($input['producto_cod'])) { $where .= " AND a.prodcod = ? "; $params[] = $input['producto_cod']; }
        if (!empty($input['unidad_id'])) { $where .= " AND j.unidad = ? "; $params[] = $input['unidad_id']; }
        if (!empty($input['rubro'])) { $where .= " AND a.rubro = ? "; $params[] = $input['rubro']; }
        if (!empty($input['marca'])) { $where .= " AND a.marca = ? "; $params[] = $input['marca']; }
        if (!empty($input['familia'])) { $where .= " AND d.familia = ? "; $params[] = $input['familia']; }
        if (!empty($input['provincia'])) { $where .= " AND j.provincia = ? "; $params[] = $input['provincia']; }
        if (!empty($input['rama_id'])) { $where .= " AND j.rama = ? "; $params[] = $input['rama_id']; }

        // Filtro Tipos Comprobante
        $where .= " AND a.tipocomp IN (1, 2, 3, 6, 7, 8, 11, 12, 13, 100, 101) ";

        // QUERY
        $sql = "SELECT 
                    a.fecha AS 'Fecha',
                    CASE 
                        WHEN a.tipocomp = 1 THEN 'FCA' 
                        WHEN a.tipocomp = 2 THEN 'FCC'
                        WHEN a.tipocomp = 3 THEN 'NCA' 
                        WHEN a.tipocomp = 6 THEN 'FCB' 
                        WHEN a.tipocomp = 7 THEN 'NCC'
                        WHEN a.tipocomp = 8 THEN 'NCB' 
                        WHEN a.tipocomp = 100 THEN 'TKT' 
                        WHEN a.tipocomp = 101 THEN 'TDC' 
                        ELSE 'Otro' 
                    END AS 'Tipo',
                    CONCAT(LPAD(COALESCE(a.pdv,0), 5, '0'), '-', LPAD(a.numcomp, 8, '0')) AS 'Numero',
                    a.prodcod AS 'Codigo',
                    a.proddes AS 'Descripcion',
                    a.cant AS 'Cantidad',
                    
                    /* Total: Negativo si es NC */
                    CASE WHEN a.tipocomp IN (3, 7, 8, 101) THEN (a.total * -1) ELSE a.total END AS 'Total',
                    
                    a.ii AS 'Imp_Int',
                    
                    /* IVA: Negativo si es NC */
                    CASE WHEN a.tipocomp IN (3, 7, 8, 101) THEN (a.iva * -1) ELSE a.iva END AS 'IVA',
                    
                    /* Neto (SubTotal): Negativo si es NC */
                    CASE WHEN a.tipocomp IN (3, 7, 8, 101) THEN (a.siniva * -1) ELSE a.siniva END AS 'Neto',
                    
                    a.rubro AS 'Rubro',
                    
                    /* IIBB: Negativo si es NC */
                    CASE WHEN a.tipocomp IN (3, 7, 8, 101) THEN (a.iibb * -1) ELSE a.iibb END AS 'IIBB',
                    
                    a.costo AS 'Costo',
                    a.usuario AS 'Usuario',
                    v.nombre AS 'Vendedor',
                    d.Proveedor AS 'Proveedor',
                    j.razon AS 'Cliente',
                    rc.rama AS 'Rama',
                    a.ivacosto AS 'Iva_compras',
                    h.marca AS 'Marca',
                    a.totaldescuento AS 'Porc_Descuento'

                FROM Lineadeventa a
                LEFT OUTER JOIN alb_productos d ON a.prodcod = d.codigo
                LEFT OUTER JOIN alb_clientes j ON a.cliente = j.id
                LEFT OUTER JOIN ramasclientes rc ON j.rama = rc.id
                LEFT OUTER JOIN Marcas h ON a.marca = h.id
                LEFT OUTER JOIN vendedores v ON a.vendedor = v.id
                $where
                ORDER BY a.fecha DESC, a.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cálculos Post-Query (Utilidad para totales, aunque no se pidió en la tabla, se usa para el footer)
        // La tabla solo mostrará los campos del SELECT
        
        if ($action === 'export') {
            // Generar Excel HTML
            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8" /></head>
            <body>
                <h3>INFORME DE VENTAS DETALLADO</h3>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <th>Fecha</th><th>Tipo</th><th>Numero</th><th>Codigo</th><th>Descripción</th>
                            <th>Cantidad</th><th>Total</th><th>Imp Int</th><th>IVA</th><th>Neto</th>
                            <th>Rubro</th><th>IIBB</th><th>Costo</th><th>Usuario</th><th>Vendedor</th>
                            <th>Proveedor</th><th>Cliente</th><th>Iva compras</th><th>Marca</th><th>% Desc</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <td><?= $r['Fecha'] ?></td>
                            <td><?= $r['Tipo'] ?></td>
                            <td style='mso-number-format:"\@";'><?= $r['Numero'] ?></td>
                            <td><?= $r['Codigo'] ?></td>
                            <td><?= htmlspecialchars($r['Descripcion']) ?></td>
                            <td align="right"><?= $r['Cantidad'] ?></td>
                            <td align="right"><?= number_format($r['Total'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Imp_Int'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['IVA'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Neto'], 2, ',', '') ?></td>
                            <td><?= htmlspecialchars($r['Rubro']) ?></td>
                            <td align="right"><?= number_format($r['IIBB'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Costo'], 2, ',', '') ?></td>
                            <td><?= htmlspecialchars($r['Usuario']) ?></td>
                            <td><?= htmlspecialchars($r['Vendedor']) ?></td>
                            <td><?= htmlspecialchars($r['Proveedor']) ?></td>
                            <td><?= htmlspecialchars($r['Cliente']) ?></td>
                            <td align="right"><?= number_format($r['Iva_compras'], 2, ',', '') ?></td>
                            <td><?= htmlspecialchars($r['Marca']) ?></td>
                            <td align="right"><?= number_format($r['Porc_Descuento'], 2, ',', '') ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Reporte_Ventas_'.date('YmdHis').'.xls']);
        } else {
            // Respuesta JSON Normal
            // Calculamos utilidad para los totales del front
            foreach($data as &$r) {
                // Utilidad = Total - IVA - (Costo - IvaCosto)
                $r['Utilidad_Calc'] = floatval($r['Total']) - floatval($r['IVA']) - (floatval($r['Costo']) - floatval($r['Iva_compras']));
            }
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>
