<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $desde = $input['desde'] ?? date('Y-m-01');
        $hasta = $input['hasta'] ?? date('Y-m-d');
        $verDetalles = $input['ver_detalles'] ?? false;
        
        $data = [];
        $sql = "";
        $params = [];

        if ($verDetalles) {
            // 2. MODO DETALLADO: Listado completo de lineadescuentos (Sin agrupar)
            // VB: dat1 = GenerarDT("lineadescuentos", ...)
            $sql = "SELECT 
                        l.fecha as 'Fecha',
                        CASE 
                            WHEN l.tipocomp = 1 THEN 'FCA' 
                            WHEN l.tipocomp = 3 THEN 'NCA' 
                            WHEN l.tipocomp = 6 THEN 'FCB' 
                            WHEN l.tipocomp = 8 THEN 'NCB' 
                            WHEN l.tipocomp = 100 THEN 'TKT' 
                            WHEN l.tipocomp = 101 THEN 'TDC' 
                            ELSE CAST(l.tipocomp AS CHAR) 
                        END as 'Tipo',
                        CONCAT(LPAD(l.pdv, 5, '0'), '-', LPAD(l.numcomp, 8, '0')) as 'Comprobante',
                        l.codpro as 'Codigo',
                        p.Descripcion as 'Descripcion',
                        l.cantproductos as 'Cantidad',
                        l.importedesc as 'Total_Descuento',
                        l.pordesc as 'Porc_Descuento'
                    FROM lineadescuentos l
                    LEFT JOIN alb_productos p ON l.codpro = p.codigo
                    WHERE l.fecha BETWEEN ? AND ?
                    ORDER BY l.fecha DESC, l.numcomp DESC, l.id ASC";
            
            $params = [$desde, $hasta];

        } else {
            // 1. MODO RESUMIDO: Por Comprobante (Item 'DESC' en Lineadeventa)
            $sql = "SELECT 
                        fecha as 'Fecha',
                        CASE 
                            WHEN tipocomp = 1 THEN 'FCA' 
                            WHEN tipocomp = 3 THEN 'NCA' 
                            WHEN tipocomp = 6 THEN 'FCB' 
                            WHEN tipocomp = 8 THEN 'NCB' 
                            WHEN tipocomp = 100 THEN 'TKT' 
                            WHEN tipocomp = 101 THEN 'TDC' 
                            ELSE CAST(tipocomp AS CHAR) 
                        END as 'Tipo',
                        CONCAT(LPAD(pdv, 5, '0'), '-', LPAD(numcomp, 8, '0')) as 'Comprobante',
                        ROUND(siniva, 2) as 'Neto',
                        ROUND(iva, 2) as 'IVA',
                        ROUND(total, 2) as 'Total_Comp',
                        usuario as 'Usuario'
                    FROM Lineadeventa
                    WHERE prodcod = 'DESC' AND fecha BETWEEN ? AND ?
                    ORDER BY fecha DESC, id DESC";
            
            $params = [$desde, $hasta];
        }

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
                <h3>INFORME DE DESCUENTOS REALIZADOS</h3>
                <p><strong>Período:</strong> <?= $desde ?> al <?= $hasta ?></p>
                <p><strong>Modo:</strong> <?= $verDetalles ? 'Detallado por Ítem' : 'Resumido por Comprobante' ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <?php 
                            if (!empty($data)) {
                                foreach (array_keys($data[0]) as $key) {
                                    echo "<th>" . htmlspecialchars(str_replace('_', ' ', $key)) . "</th>";
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <?php foreach($r as $k => $v): 
                                $align = (is_numeric($v) && $k !== 'Codigo' && $k !== 'Comprobante') ? 'right' : 'left';
                            ?>
                                <td align="<?= $align ?>"><?= htmlspecialchars($v) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Reporte_Descuentos_'.date('YmdHis').'.xls']);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>