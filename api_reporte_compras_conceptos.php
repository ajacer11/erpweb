<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener Filtros
    if ($action === 'get_filters') {
        $stmt = $pdo->query("SELECT id, concepto FROM conceptosgastos WHERE id > 0 ORDER BY concepto ASC");
        $conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'ok', 'conceptos' => $conceptos]);

    } 
    // 2. Generar Reporte / Exportar
    elseif ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);

        $desde = $input['desde'] ?? date('Y-m-01');
        $hasta = $input['hasta'] ?? date('Y-m-d');
        $idConcepto = $input['concepto_id'] ?? null;
        $agrupado = $input['agrupado'] ?? false; // Nuevo parámetro

        // Construcción de WHERE común
        $where = " WHERE l.id > 0 AND (l.fecha >= ? AND l.fecha <= ?) ";
        $params = [$desde, $hasta];

        if (!empty($idConcepto)) {
            $where .= " AND l.idconcepto = ? ";
            $params[] = $idConcepto;
        }

        if ($agrupado) {
            // MODO AGRUPADO: Sumar por Concepto
            $sql = "SELECT 
                        MIN(l.fecha) AS 'Fecha', /* Solo referencial para orden */
                        (SELECT concepto FROM conceptosgastos WHERE conceptosgastos.id = l.idconcepto) AS 'Concepto',
                        SUM(l.importe) AS 'Importe',
                        0 AS 'Alicuota', /* No aplica en agrupado o promedio ponderado complejo */
                        SUM(l.importe * l.alicuota / 100) AS 'IVA'
                    FROM lineaconceptoscompra l
                    $where
                    GROUP BY l.idconcepto
                    ORDER BY Concepto ASC";
        } else {
            // MODO DETALLADO (Original VB)
            $sql = "SELECT 
                        l.fecha AS 'Fecha',
                        (SELECT concepto FROM conceptosgastos WHERE conceptosgastos.id = l.idconcepto) AS 'Concepto',
                        l.importe AS 'Importe',
                        l.alicuota AS 'Alicuota',
                        (l.importe * l.alicuota / 100) AS 'IVA'
                    FROM lineaconceptoscompra l
                    $where
                    ORDER BY l.fecha DESC";
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
                <h3>MOVIMIENTOS POR CONCEPTOS <?= $agrupado ? '(RESUMEN AGRUPADO)' : '(DETALLADO)' ?></h3>
                <p><strong>Período:</strong> <?= $desde ?> al <?= $hasta ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <?php if(!$agrupado): ?><th>Fecha</th><?php endif; ?>
                            <th>Concepto</th>
                            <th>Importe</th>
                            <?php if(!$agrupado): ?><th>Alicuota</th><?php endif; ?>
                            <th>IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <?php if(!$agrupado): ?><td><?= $r['Fecha'] ?></td><?php endif; ?>
                            <td><?= htmlspecialchars($r['Concepto']) ?></td>
                            <td align="right"><?= number_format($r['Importe'], 2, ',', '') ?></td>
                            <?php if(!$agrupado): ?><td align="right"><?= number_format($r['Alicuota'], 2, ',', '') ?></td><?php endif; ?>
                            <td align="right"><?= number_format($r['IVA'], 2, ',', '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Movimientos_Conceptos_'.date('YmdHis').'.xls']);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>