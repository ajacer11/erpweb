<?php
/**
 * api_productos_hist.php - BACKEND CONSULTA HISTÓRICA
 * Consulta estrictamente la tabla 'historicoproductos' con lógica fiel a VB.NET.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. OBTENER COMBOS (Lógica VB.NET corregida)
    if ($action === 'get_combos') {
        $data = [
            'rubros' => $pdo->query("SELECT id, rubro FROM rubros ORDER BY rubro ASC")->fetchAll(),
            'familias' => $pdo->query("SELECT id, nombre FROM familias ORDER BY nombre ASC")->fetchAll(),
            'marcas' => $pdo->query("SELECT id, marca FROM Marcas ORDER BY marca ASC")->fetchAll(),
            'proveedores' => $pdo->query("SELECT Razon AS id, Razon AS descripcion FROM Proveedores ORDER BY Razon ASC")->fetchAll()
        ];
        echo json_encode(['success' => true, 'data' => $data]);
        exit;
    }

    // 2. BUSQUEDA y EXPORTACIÓN
    if ($action === 'search' || $action === 'export') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido");

        // Construcción de Query basada EXACTAMENTE en el snippet VB.NET
        $columns = "
            codigo AS 'Codigo', 
            descripcion as 'Producto', 
            stock as 'Stock', 
            stockcomprometido as 'Stock Comprometido', 
            stockmin as 'Stock Min.', 
            rubro as 'Rubro', 
            (SELECT Marcas.marca from Marcas WHERE Marcas.id = historicoproductos.marca) as 'Marca',
            preciocosto as 'Costo Final', 
            Proveedor as 'Proveedor', 
            sinivacosto as 'Neto Costo', 
            ivacosto as 'Iva Costo', 
            ImpInt as 'Imp. Int.', 
            Precio as 'Lista 1 Final', 
            siniva as 'Neto Lista 1', 
            iva1 as 'IVA Lista 1', 
            Precio2 as 'Lista 2 Final', 
            siniva2 as 'Neto Lista 2', 
            iva2 as 'IVA Lista 2', 
            Precio3 as 'Lista 3 Final', 
            siniva3 as 'Neto Lista 3', 
            iva3 as 'IVA Lista 3', 
            Precio4 as 'Lista 4 Final', 
            siniva4 as 'Neto Lista 4', 
            iva4 as 'IVA Lista 4', 
            Precio5 as 'Lista 5 Final', 
            siniva5 as 'Neto Lista 5', 
            iva5 as 'IVA Lista 5', 
            Precio6 as 'Lista 6 Final', 
            siniva6 as 'Neto Lista 6', 
            iva6 as 'IVA Lista 6', 
            Precio7 as 'Lista 7 Final', 
            siniva7 as 'Neto Lista 7', 
            iva7 as 'IVA Lista 7', 
            habilitado as 'Habilitado', 
            usuariomodif as 'Usuario Modifcicacion', 
            DATE_FORMAT(fechamodif, '%d/%m/%Y %H:%i') as 'Fecha Modificacion'
        ";

        $sql = "SELECT $columns FROM historicoproductos WHERE id > 0 ";
        
        $params = [];

        // Filtros (Se mantienen igual que en búsqueda para exportación)
        if (isset($_POST['usar_rubro']) && !empty($_POST['rubro'])) {
            $sql .= " AND rubro = ?"; 
            $params[] = $_POST['rubro'];
        }

        if (isset($_POST['usar_familia']) && !empty($_POST['familia'])) {
            $sql .= " AND familia = ?";
            $params[] = $_POST['familia'];
        }

        if (isset($_POST['usar_marca']) && !empty($_POST['marca'])) {
            $sql .= " AND marca = ?";
            $params[] = $_POST['marca'];
        }

        if (isset($_POST['usar_proveedor']) && !empty($_POST['proveedor'])) {
            $sql .= " AND Proveedor LIKE ?";
            $params[] = "%" . $_POST['proveedor'] . "%";
        }

        if (isset($_POST['usar_fechas'])) {
            $desde = $_POST['fecha_desde'];
            $hasta = $_POST['fecha_hasta'];
            if ($desde && $hasta) {
                $sql .= " AND fechamodif BETWEEN ? AND ?";
                $params[] = $desde . ' 00:00:00';
                $params[] = $hasta . ' 23:59:59';
            }
        }

        $estado = $_POST['estado'] ?? 'todos';
        if ($estado === '1') {
            $sql .= " AND habilitado = 1";
        } elseif ($estado === '0') {
            $sql .= " AND habilitado = 0";
        }

        if (!empty($_POST['busqueda'])) {
            $term = "%" . $_POST['busqueda'] . "%";
            $sql .= " AND (codigo LIKE ? OR descripcion LIKE ?)";
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= " ORDER BY fechamodif DESC"; // Orden por fecha
        
        // Limitar resultados solo si es búsqueda normal
        if ($action === 'search') {
            $sql .= " LIMIT 500";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // RESPUESTA EXCEL (XLS/HTML) EN JSON
        // Esto permite que el JS reciba el contenido aunque la petición sea AJAX
        if ($action === 'export') {
            if (empty($resultados)) throw new Exception("No hay datos filtrados para exportar.");

            // Generar Tabla HTML (compatible con Excel)
            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <!--[if gte mso 9]>
                <xml>
                <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                <x:Name>Historico Productos</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
                </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    body { font-family: Arial, sans-serif; font-size: 10pt; }
                    table { border-collapse: collapse; width: 100%; }
                    th { 
                        background-color: #002060; 
                        color: #ffffff; 
                        border: 1px solid #000000; 
                        font-weight: bold; 
                        padding: 8px; 
                        text-align: center;
                    }
                    td { 
                        border: 1px solid #000000; 
                        padding: 5px; 
                        vertical-align: middle; 
                    }
                    .num { mso-number-format: "Fixed"; text-align: right; }
                    .text { mso-number-format: "\@"; text-align: left; }
                    .center { text-align: center; }
                </style>
            </head>
            <body>
                <table>
                    <thead>
                        <tr>
                            <?php foreach (array_keys($resultados[0]) as $header): ?>
                                <th><?php echo htmlspecialchars($header); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $row): ?>
                            <tr>
                                <?php foreach ($row as $key => $cell): 
                                    // Formato básico para números
                                    $class = (is_numeric($cell) && $key !== 'Codigo') ? 'num' : 'text';
                                ?>
                                    <td class="<?php echo $class; ?>"><?php echo htmlspecialchars($cell ?? ''); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $xlsContent = ob_get_clean();

            // Devolver en JSON (base64) para evitar problemas de headers y permitir AJAX
            echo json_encode([
                'success' => true,
                'xls_base64' => base64_encode($xlsContent),
                'filename' => 'Historico_Productos_' . date('Ymd_His') . '.xls'
            ]);
            exit;
        }

        // RESPUESTA JSON GRID
        echo json_encode(['success' => true, 'data' => $resultados]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}