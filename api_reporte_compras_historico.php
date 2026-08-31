<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener Filtros (Productos)
    if ($action === 'get_filters') {
        // VB: GenerarDT("alb_productos", "codigo, descripcion", "", "descripcion ASC")
        $stmt = $pdo->query("SELECT codigo, descripcion FROM alb_productos ORDER BY descripcion ASC");
        $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'ok', 'productos' => $productos]);

    } 
    // 2. Generar Reporte / Exportar
    elseif ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);

        $fecha = $input['fecha'] ?? date('Y-m-d');
        $codigoProducto = $input['producto_cod'] ?? null;
        $verDetalles = $input['ver_detalles'] ?? false;

        if (empty($codigoProducto)) {
            throw new Exception("Debe seleccionar un producto.");
        }

        // Construcción de WHERE (Basado en VB)
        // fecha <= '{fecha}' and codigo = '{cmbprod.SelectedValue}' and (tipocomp <> 8 and tipocomp <> 3 and tipocomp <> 101)
        $where = " WHERE fecha <= ? AND codigo = ? AND tipocomp NOT IN (3, 8, 101) ";
        $params = [$fecha, $codigoProducto];

        // Lógica de Límite (Resumido vs Detallado)
        $limit = $verDetalles ? "" : " LIMIT 1 ";

        // Consulta SQL (Basada en VB)
        // VB: fecha AS 'Fecha', codigo as 'Codigo', descripcion as 'Descripcion', netocosto AS 'Neto Costo', ivacosto AS 'Iva Costo', impintcosto AS 'Imp Int', preciocosto as 'Costo Total'
        $sql = "SELECT 
                    fecha AS 'Fecha',
                    codigo AS 'Codigo',
                    descripcion AS 'Descripcion',
                    netocosto AS 'Neto_Costo',
                    ivacosto AS 'Iva_Costo',
                    impintcosto AS 'Imp_Int',
                    preciocosto AS 'Costo_Total'
                FROM ingresosdestock
                $where
                ORDER BY fecha DESC 
                $limit";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'export') {
            // Generar Excel HTML
            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8" /></head>
            <body>
                <h3>HISTÓRICO DE PRECIOS DE COMPRA</h3>
                <p><strong>Producto:</strong> <?= htmlspecialchars($codigoProducto) ?></p>
                <p><strong>Hasta Fecha:</strong> <?= $fecha ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <th>Fecha</th>
                            <th>Codigo</th>
                            <th>Descripcion</th>
                            <th>Neto Costo</th>
                            <th>Iva Costo</th>
                            <th>Imp Int</th>
                            <th>Costo Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <td><?= $r['Fecha'] ?></td>
                            <td><?= htmlspecialchars($r['Codigo']) ?></td>
                            <td><?= htmlspecialchars($r['Descripcion']) ?></td>
                            <td align="right"><?= number_format($r['Neto_Costo'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Iva_Costo'], 2, ',', '') ?></td>
                            <td align="right"><?= number_format($r['Imp_Int'], 2, ',', '') ?></td>
                            <td align="right" style="font-weight:bold;"><?= number_format($r['Costo_Total'], 2, ',', '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Historico_Precios_'.date('YmdHis').'.xls']);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>