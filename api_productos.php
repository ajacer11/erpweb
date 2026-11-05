<?php
/**
 * api_productos.php - CRUD de Productos
 * CORREGIDO: Sincronización estricta de parámetros para evitar SQLSTATE[HY093].
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (isset($_GET['codigo'])) {
            $stmt = $pdo->prepare("SELECT * FROM alb_productos WHERE codigo = ?");
            $stmt->execute([$_GET['codigo']]);
            $prod = $stmt->fetch();
            echo json_encode(['success' => !!$prod, 'data' => $prod]);
            exit;
        }

        $q = $_GET['q'] ?? '';
        $rubro = $_GET['rubro'] ?? '';
        $marca = $_GET['marca'] ?? '';
        $familia = $_GET['familia'] ?? '';
        $proveedor = $_GET['proveedor'] ?? '';
        $habilitado = $_GET['habilitado'] ?? 'todos';
        $stock_bajo = isset($_GET['stock_bajo']) && $_GET['stock_bajo'] == 'true';

        $sql = "SELECT p.*, m.marca as nombre_marca 
                FROM alb_productos p 
                LEFT JOIN Marcas m ON p.marca = m.id
                WHERE 1=1";
        $params = [];

        require_once 'productos_filtros.php';
        aplicarFiltrosProductos($sql, $params, $_GET);

        $sql .= " ORDER BY p.descripcion ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['codigo'])) throw new Exception("El código es obligatorio.");

        $codigo = $input['codigo'];
        $es_edicion = isset($input['is_edit']) && ($input['is_edit'] == "1" || $input['is_edit'] === true);

        // Lista de campos que existen en la tabla alb_productos
        $campos = [
            'codigo', 'descripcion', 'descripcion_tienda', 'rubro', 'marca', 'familia', 'proveedor', 'proveedorID',
            'codigoproveedor', 'poriva', 'idiva', 'impint', 'sinivacosto', 'ivacosto', 'preciocosto',
            'habilitado', 'controlastock', 'uniporbulto', 'stockmaximo', 'StockMin',
            'tipo_producto', 'mostrarencatalogo', 'mostrarsinstock', 'idmoneda',
            'siniva', 'iva1', 'precio', 
            'siniva2', 'iva2', 'precio2', 
            'siniva3', 'iva3', 'precio3',
            'siniva4', 'iva4', 'precio4', 
            'siniva5', 'iva5', 'precio5', 
            'siniva6', 'iva6', 'precio6',
            'siniva7', 'iva7', 'precio7',
            'siniva8', 'iva8', 'precio8',
            'IIBB1', 'IIBB2', 'IIBB3', 'IIBB4', 'IIBB5', 'IIBB6', 'IIBB7', 'IIBB8',
            'maxdescl1', 'maxdescl2', 'maxdescl3', 'maxdescl4', 'maxdescl5', 'maxdescl6', 'maxdescl7', 'maxdescl8',
            'cuentaventas', 'cuentacosto', 'cuentamercaderia'
        ];

        // Columnas numéricas de alb_productos con su default: un campo numérico que llega vacío (ej. moneda sin
        // elegir) no puede guardarse como '' — con el sql_mode estricto del VPS falla con
        // "Incorrect integer value: '' for column idmoneda". Se usa el default de la columna (idmoneda = 1).
        $numericas = [];
        foreach ($pdo->query("SELECT COLUMN_NAME, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.columns
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alb_productos'
                                AND DATA_TYPE IN ('int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'double', 'float')")->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $def = $col['COLUMN_DEFAULT'];
            $def = ($def === null || strtoupper($def) === 'NULL') ? null : trim($def, "'");
            $numericas[strtolower($col['COLUMN_NAME'])] = $def !== null ? $def + 0 : ($col['IS_NULLABLE'] === 'YES' ? null : 0);
        }

        // Construir array de datos limpio (Solo campos de la DB)
        $data = [];
        foreach ($campos as $c) {
            $val = $input[$c] ?? null;
            if (strpos($c, 'siniva') !== false || strpos($c, 'precio') !== false || strpos($c, 'iva') !== false || strpos($c, 'IIBB') !== false || $c == 'impint') {
                $data[$c] = ($val === null || $val === '') ? 0 : floatval($val);
            } else if (strpos($c, 'maxdescl') === 0) {
                // Porcentaje de descuento máximo permitido en facturación para la lista (validado en api_facturacion_validaciones_previas.php)
                $data[$c] = ($val === null || $val === '') ? 0 : max(0, min(100, floatval($val)));
            } else if (in_array($c, ['habilitado', 'controlastock', 'mostrarencatalogo', 'mostrarsinstock'])) {
                $data[$c] = ($val == 1 || $val === true) ? 1 : 0;
            } else if ($c === 'descripcion_tienda') {
                // Columna VARCHAR(2000): recortar acá para no depender solo del
                // maxlength del textarea (bypasseable llamando a la API directo).
                $data[$c] = $val !== null ? mb_substr((string)$val, 0, 2000) : '';
            } else if (array_key_exists(strtolower($c), $numericas) && ($val === null || $val === '')) {
                $data[$c] = $numericas[strtolower($c)];
            } else {
                $data[$c] = $val ?? '';
            }
        }

        // Mantener una única ecuación para todas las listas. El precio final es
        // la fuente de verdad cuando el usuario eligió "Por precio venta final";
        // en los demás modos lo es el neto. Así nunca se guardan juntos un
        // precio final nuevo y el neto/IVA anteriores.
        $modoCalculo = (string) ($input['calc_mode'] ?? '');
        $alicuota = (float) ($data['poriva'] ?? 0) / 100;
        $impInterno = (float) ($data['impint'] ?? 0);
        for ($i = 1; $i <= 8; $i++) {
            $campoNeto = $i === 1 ? 'siniva' : "siniva{$i}";
            $campoIva = "iva{$i}";
            $campoFinal = $i === 1 ? 'precio' : "precio{$i}";

            if ($modoCalculo === 'final') {
                $final = (float) $data[$campoFinal];
                $neto = ($final - $impInterno) / (1 + $alicuota);
                $data[$campoNeto] = round($neto, 2);
                $data[$campoIva] = round($neto * $alicuota, 2);
            } else {
                $neto = (float) $data[$campoNeto];
                $iva = round($neto * $alicuota, 2);
                $data[$campoIva] = $iva;
                $data[$campoFinal] = round($neto + $iva + $impInterno, 2);
            }
        }

        if ($es_edicion) {
            // Guardar en histórico (Aseguramos que el count de columnas coincida)
            $pdo->prepare("INSERT INTO historicoproductos (cuentaventas, cuentacosto, cuentamercaderia, idiva, codigo, descripcion, rubro, poriva, ImpInt, siniva, iva1, precio, siniva2, iva2, precio2, siniva3, iva3, precio3, siniva4, iva4, precio4, sinivacosto, ivacosto, preciocosto, habilitado, Stock, StockMin, IIBB1, IIBB2, IIBB3, IIBB4, Proveedor, siniva5, siniva6, siniva7, siniva8, iva5, iva6, iva7, iva8, IIBB5, IIBB6, IIBB7, IIBB8, precio5, precio6, precio7, precio8, idgrupoajuste, Marca, proveedorID, familia, maxdescl8, maxdescl7, maxdescl6, maxdescl5, maxdescl4, maxdescl3, maxdescl2, maxdescl1, registrastock, usuariomodif, fechamodif) 
                          SELECT cuentaventas, cuentacosto, cuentamercaderia, idiva, codigo, descripcion, rubro, poriva, ImpInt, siniva, iva1, precio, siniva2, iva2, precio2, siniva3, iva3, precio3, siniva4, iva4, precio4, sinivacosto, ivacosto, preciocosto, habilitado, Stock, StockMin, IIBB1, IIBB2, IIBB3, IIBB4, Proveedor, siniva5, siniva6, siniva7, siniva8, iva5, iva6, iva7, iva8, IIBB5, IIBB6, IIBB7, IIBB8, precio5, precio6, precio7, precio8, idgrupoajuste, Marca, proveedorID, familia, maxdescl8, maxdescl7, maxdescl6, maxdescl5, maxdescl4, maxdescl3, maxdescl2, maxdescl1, controlastock, 'WEB', CURRENT_DATE 
                          FROM alb_productos WHERE codigo = ?")
                ->execute([$codigo]);

            // Construir UPDATE dinámico eliminando el campo codigo del SET para evitar conflictos
            $setFields = [];
            foreach ($campos as $c) {
                if ($c !== 'codigo') $setFields[] = "$c = :$c";
            }
            $sql = "UPDATE alb_productos SET " . implode(', ', $setFields) . " WHERE codigo = :codigo";
            // En este caso $data ya contiene la clave 'codigo', que se usará para el WHERE.
        } else {
            $cols = implode(', ', $campos);
            $vals = ':' . implode(', :', $campos);
            $sql = "INSERT INTO alb_productos ($cols) VALUES ($vals)";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        echo json_encode(['success' => true, 'message' => 'Operación realizada con éxito.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
