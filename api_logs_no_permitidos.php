<?php
// Incluimos el controlador de seguridad simplificado
// Esto valida AJAX, Sesión y carga $pdo (db.php)
require_once 'check_api_access.php';

$action = $_GET['action'] ?? 'list';

try {
    // Parámetros de Filtro
    $fechaDesde = $_GET['fecha_desde'] ?? date('Y-m-d');
    $fechaHasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

    // Query Base
    $sql = "SELECT * FROM log_accesos_denegados 
            WHERE fecha BETWEEN :desde AND :hasta 
            ORDER BY fecha DESC";
    
    // Ajustar fechas para cubrir todo el día
    $params = [
        ':desde' => "$fechaDesde 00:00:00",
        ':hasta' => "$fechaHasta 23:59:59"
    ];

    if ($action === 'list') {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $data]);

    } elseif ($action === 'export') {
        // EXPORTACIÓN EXCEL
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        ob_start();
        ?>
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head>
            <meta charset="utf-8" />
            <style>
                table { border-collapse: collapse; width: 100%; font-family: Arial, sans-serif; }
                th { background-color: #0d2b45; color: #00ffff; border: 1px solid #000; padding: 10px; font-weight: bold; }
                td { border: 1px solid #000; padding: 5px; color: #000; vertical-align: top; }
                .alert { background-color: #ffcccc; color: #cc0000; font-weight: bold; }
                .text-muted { color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <h3>LOG DE ACCESOS RESTRINGIDOS</h3>
            <p><strong>Periodo:</strong> <?= $fechaDesde ?> al <?= $fechaHasta ?></p>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>FECHA</th>
                        <th>USUARIO</th>
                        <th>MÓDULO SOLICITADO</th>
                        <th>URI SOLICITADA</th>
                        <th>REFERER</th>
                        <th>IP REMOTA</th>
                        <th>X-FWD-FOR</th>
                        <th>CF-IP</th>
                        <th>VIA</th>
                        <th>USER AGENT</th>
                        <th>RAZÓN DE BLOQUEO</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= $row['fecha'] ?></td>
                        <td>
                            <?= htmlspecialchars($row['usuario_nombre']) ?>
                            <br><span class="text-muted">(ID: <?= $row['usuario_id'] ?>)</span>
                        </td>
                        <td class="alert"><?= htmlspecialchars($row['modulo']) ?></td>
                        <td><?= htmlspecialchars($row['request_uri']) ?></td>
                        <td><?= htmlspecialchars($row['referer']) ?></td>
                        <td><?= htmlspecialchars($row['remote_addr']) ?></td>
                        <td><?= htmlspecialchars($row['x_forwarded_for']) ?></td>
                        <td><?= htmlspecialchars($row['cf_connecting_ip']) ?></td>
                        <td><?= htmlspecialchars($row['via']) ?></td>
                        <td><?= htmlspecialchars($row['user_agent']) ?></td>
                        <td style="font-weight: bold; color: #dc3545;"><?= htmlspecialchars($row['datos_adicionales']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </body>
        </html>
        <?php
        $htmlContent = ob_get_clean();
        
        echo json_encode([
            'status' => 'ok',
            'filename' => 'Logs_Restringidos_' . date('Ymd_His') . '.xls',
            'file_content' => base64_encode($htmlContent)
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>