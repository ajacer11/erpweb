<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'generate_report' || $action === 'export') {
        $input = json_decode(file_get_contents('php://input'), true);

        $desde = $input['desde'] ?? date('Y-m-01');
        $hasta = $input['hasta'] ?? date('Y-m-d');

        // Construcción de WHERE
        $where = " WHERE fecha BETWEEN ? AND ? ";
        $params = [$desde, $hasta];

        // Consulta SQL
        $sql = "SELECT 
                    id,
                    usuario,
                    DATE_FORMAT(fecha, '%d/%m/%Y') as fecha_fmt,
                    TIME_FORMAT(hora, '%H:%i:%s') as hora_fmt,
                    ip,
                    ippublica,
                    host,
                    usuariopc
                FROM login
                $where
                ORDER BY fecha DESC, hora DESC";

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
                <h3>AUDITORÍA DE INICIO DE SESIÓN</h3>
                <p><strong>Período:</strong> <?= $desde ?> al <?= $hasta ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Fecha</th>
                            <th>Hora</th>
                            <th>IP Local</th>
                            <th>IP Pública</th>
                            <th>Host</th>
                            <th>Info Dispositivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['usuario']) ?></td>
                            <td><?= $r['fecha_fmt'] ?></td>
                            <td><?= $r['hora_fmt'] ?></td>
                            <td><?= htmlspecialchars($r['ip']) ?></td>
                            <td><?= htmlspecialchars($r['ippublica']) ?></td>
                            <td><?= htmlspecialchars($r['host']) ?></td>
                            <td><?= htmlspecialchars($r['usuariopc']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode(['status' => 'ok', 'file_content' => base64_encode($html), 'filename' => 'Auditoria_Login_'.date('YmdHis').'.xls']);
        } else {
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>