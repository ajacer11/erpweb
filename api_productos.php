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

        if (!empty($q)) {
            $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ? OR p.rubro LIKE ? OR p.codigo IN (SELECT refierea FROM referenciasproductos WHERE codigo LIKE ?))";
            $term = "%$q%";
            array_push($params, $term, $term, $term, $term);
        }
        if (!empty($rubro)) { $sql .= " AND p.rubro = ?"; $params[] = $rubro; }
        if (!empty($marca)) { $sql .= " AND p.marca = ?"; $params[] = $marca; }
        if (!empty($familia)) { $sql .= " AND p.familia = ?"; $params[] = $familia; }
        if (!empty($proveedor)) { $sql .= " AND p.proveedor = ?"; $params[] = $proveedor; }
        if ($habilitado !== 'todos') { $sql .= " AND p.habilitado = ?"; $params[] = $habilitado; }
        if ($stock_bajo) { $sql .= " AND p.stock <= p.StockMin"; }

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
            'codigo', 'descripcion', 'rubro', 'marca', 'familia', 'proveedor', 'proveedorID',
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
            'cuentaventas', 'cuentacosto', 'cuentamercaderia'
        ];

        // Construir array de datos limpio (Solo campos de la DB)
        $data = [];
        foreach ($campos as $c) {
            $val = $input[$c] ?? null;
            if (strpos($c, 'siniva') !== false || strpos($c, 'precio') !== false || strpos($c, 'iva') !== false || strpos($c, 'IIBB') !== false || $c == 'impint') {
                $data[$c] = ($val === null || $val === '') ? 0 : floatval($val);
            } else if (in_array($c, ['habilitado', 'controlastock', 'mostrarencatalogo', 'mostrarsinstock'])) {
                $data[$c] = ($val == 1 || $val === true) ? 1 : 0;
            } else {
                $data[$c] = $val ?? '';
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