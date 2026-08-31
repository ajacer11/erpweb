<?php
/**
 * api_stock_movimientos.php - PROCESAMIENTO DE REPORTE DE MOVIMIENTOS
 * Réplica de lógica frmmovstock.informe() y exportación a Excel.
 */
header('Content-Type: application/json');

// Permitir que la acción de exportar no devuelva JSON
$action = $_GET['action'] ?? '';
if ($action !== 'export_excel') {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso prohibido']);
        exit();
    }
}

require_once 'db.php';

// Si es exportación, los datos vienen por GET para facilitar el link de descarga
if ($action === 'export_excel') {
    $data = $_GET;
} else {
    $data = json_decode(file_get_contents('php://input'), true);
}

if ($action === 'get_report' || $action === 'export_excel') {
    try {
        $desde = ($data['desde'] ?? date('Y-m-d')) . " 00:00:01";
        $hasta = ($data['hasta'] ?? date('Y-m-d')) . " 23:59:59";
        
        $where = " WHERE m.id > 0 ";
        $params = [];

        // Filtro Fechas
        $where .= " AND (m.fecha >= ? AND m.fecha <= ?)";
        $params[] = $desde;
        $params[] = $hasta;

        // Filtro Fiscal
        if (isset($data['fiscal']) && $data['fiscal'] !== 'todos') {
            $where .= " AND m.esfiscal = ?";
            $params[] = $data['fiscal'];
        }

        // Filtro Origen
        if (isset($data['origen']) && $data['origen'] !== 'todos') {
            $where .= " AND m.origenmov = ?";
            $params[] = $data['origen'];
        }

        // Filtro Remito
        if (!empty($data['remito'])) {
            $where .= " AND m.numremito = ?";
            $params[] = $data['remito'];
        }

        // Filtro Rubro
        if (!empty($data['rubro'])) {
            $where .= " AND m.rubro = ?";
            $params[] = $data['rubro'];
        }

        // Filtro Producto
        if (!empty($data['producto'])) {
            $where .= " AND m.codigo = ?";
            $params[] = $data['producto'];
        }

        // Filtro Depósito (solo aplica en tenants con multidepósito activado;
        // el LEFT JOIN deja "Deposito" en blanco para movimientos sin depósito).
        if (!empty($data['id_deposito'])) {
            $where .= " AND msd.id_deposito = ?";
            $params[] = (int) $data['id_deposito'];
        }

        // 1. Obtener Movimientos
        $sql = "SELECT
                    m.fecha AS 'Fecha',
                    p.descripcion AS 'Producto',
                    m.codigo AS 'Codigo',
                    m.rubro AS 'Rubro',
                    m.entradas AS 'Entradas',
                    m.salidas AS 'Salidas',
                    m.tipomov AS 'Detalle Movimiento',
                    m.esfiscal AS 'Fiscal',
                    CASE m.numremito WHEN 0 THEN '' ELSE m.numremito END AS 'Num Remito',
                    m.usuario AS 'Usuario',
                    d.nombre AS 'Deposito'
                FROM movimientosdestock m
                LEFT JOIN alb_productos p ON m.codigo = p.codigo
                LEFT JOIN movimientos_stock_deposito msd ON msd.id_movimiento_legacy = m.id
                LEFT JOIN depositos d ON d.id = msd.id_deposito
                $where
                ORDER BY m.fecha DESC, m.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $movimientos = $stmt->fetchAll();

        // Si es una petición de datos normal para la grilla
        if ($action === 'get_report') {
            $sqlNotas = "SELECT fecha, nota FROM notas WHERE fecha >= ? AND fecha <= ? ORDER BY fecha ASC";
            $stmtN = $pdo->prepare($sqlNotas);
            $stmtN->execute([$desde, $hasta]);
            $notas = $stmtN->fetchAll();

            $totalEntradas = 0;
            $totalSalidas = 0;
            foreach ($movimientos as $m) {
                $totalEntradas += (float)$m['Entradas'];
                $totalSalidas += (float)$m['Salidas'];
            }

            echo json_encode([
                'success' => true,
                'data' => $movimientos,
                'notas' => $notas,
                'resumen' => [
                    'entradas' => $totalEntradas,
                    'salidas' => $totalSalidas
                ]
            ]);
            exit;
        }

        // --- LÓGICA DE EXPORTACIÓN A EXCEL ---
        if ($action === 'export_excel') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=Movimientos_Stock_" . date('Ymd_His') . ".xls");
            header("Pragma: no-cache");
            header("Expires: 0");

            // Traducir etiquetas de filtros para la cabecera
            $f_fiscal = ($data['fiscal'] == '1') ? 'FISCALES' : (($data['fiscal'] == '0') ? 'NO FISCALES' : 'TODOS');
            $f_origen = strtoupper($data['origen'] ?? 'TODOS');
            $f_rubro = !empty($data['rubro']) ? $data['rubro'] : 'TODOS';
            $f_prod = !empty($data['producto']) ? $data['producto'] : 'TODOS';

            echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
            echo "<head><meta http-equiv='Content-type' content='text/html;charset=utf-8' /></head>";
            echo "<body>";
            
            // Cabecera estilizada
            echo "<table border='1'>";
            echo "<tr><th colspan='11' style='background-color: #001020; color: #00ffff; font-size: 16px;'>REPORTE DE MOVIMIENTOS DE STOCK</th></tr>";
            echo "<tr><th colspan='5' align='left'><b>PERÍODO:</b> {$data['desde']} al {$data['hasta']}</th><th colspan='6' align='left'><b>FISCALIDAD:</b> {$f_fiscal}</th></tr>";
            echo "<tr><th colspan='5' align='left'><b>ORIGEN:</b> {$f_origen}</th><th colspan='6' align='left'><b>RUBRO:</b> {$f_rubro}</th></tr>";
            echo "<tr><th colspan='11' align='left'><b>PRODUCTO:</b> {$f_prod}</th></tr>";
            echo "<tr><td colspan='11'></td></tr>"; // Espacio en blanco

            // Encabezados de tabla
            echo "<tr style='background-color: #f2f2f2;'>
                    <th>FECHA</th>
                    <th>PRODUCTO</th>
                    <th>CÓDIGO</th>
                    <th>RUBRO</th>
                    <th>ENTRADAS</th>
                    <th>SALIDAS</th>
                    <th>DETALLE MOVIMIENTO</th>
                    <th>FISC.</th>
                    <th>REMITO</th>
                    <th>USUARIO</th>
                    <th>DEPÓSITO</th>
                  </tr>";

            $sumEntradas = 0;
            $sumSalidas = 0;

            foreach ($movimientos as $m) {
                $sumEntradas += (float)$m['Entradas'];
                $sumSalidas += (float)$m['Salidas'];
                $fisc_text = ($m['Fiscal'] == 1) ? 'SI' : 'NO';

                echo "<tr>
                        <td>{$m['Fecha']}</td>
                        <td>" . htmlspecialchars($m['Producto']) . "</td>
                        <td>{$m['Codigo']}</td>
                        <td>{$m['Rubro']}</td>
                        <td align='right' style='color: green;'>" . number_format($m['Entradas'], 2, ',', '.') . "</td>
                        <td align='right' style='color: red;'>" . number_format($m['Salidas'], 2, ',', '.') . "</td>
                        <td>" . htmlspecialchars($m['Detalle Movimiento']) . "</td>
                        <td align='center'>{$fisc_text}</td>
                        <td align='center'>{$m['Num Remito']}</td>
                        <td>{$m['Usuario']}</td>
                        <td>" . htmlspecialchars($m['Deposito'] ?? '') . "</td>
                      </tr>";
            }

            // Pie con totales
            $balance = $sumEntradas - $sumSalidas;
            echo "<tr style='background-color: #e9ecef; font-weight: bold;'>
                    <td colspan='4' align='right'>TOTALES:</td>
                    <td align='right'>" . number_format($sumEntradas, 2, ',', '.') . "</td>
                    <td align='right'>" . number_format($sumSalidas, 2, ',', '.') . "</td>
                    <td colspan='5'></td>
                  </tr>";
            echo "<tr style='background-color: #d1ecf1; font-weight: bold;'>
                    <td colspan='4' align='right'>BALANCE DEL PERÍODO:</td>
                    <td colspan='2' align='center'>" . number_format($balance, 2, ',', '.') . "</td>
                    <td colspan='5'></td>
                  </tr>";

            echo "</table>";
            echo "</body></html>";
            exit;
        }

    } catch (Exception $e) {
        if ($action !== 'export_excel') {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        } else {
            echo "Error al exportar: " . $e->getMessage();
        }
    }
    exit;
}