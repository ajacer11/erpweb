<?php
/**
 * api_vendedores_cta_cte.php - LÓGICA DE CUENTA CORRIENTE DE VENDEDORES
 * OPTIMIZACIÓN: Soporte para filtro de fechas y cálculo de saldo anterior.
 * CORRECCIÓN: Filtrado estricto mediante DATE() y omisión de movimientos en cero.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_vendedores':
            $sql = "SELECT id, nombre FROM vendedores WHERE habilitado = 1 ORDER BY nombre ASC";
            echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
            break;

        case 'list':
        case 'export_excel':
            // Captura robusta de parámetros (JSON body para POST, REQUEST para GET/Excel)
            $json = json_decode(file_get_contents('php://input'), true) ?? [];
            $vendedor_id = $json['vendedor_id'] ?? $_REQUEST['vendedor_id'] ?? 0;
            $fecha_desde = $json['fecha_desde'] ?? $_REQUEST['fecha_desde'] ?? null;

            $idVende = (int)$vendedor_id;
            $fechaDesde = (!empty($fecha_desde)) ? trim($fecha_desde) : null;
            
            if ($idVende <= 0) throw new Exception("Debe seleccionar un vendedor");

            $saldoAnterior = 0;
            $params = [$idVende];
            
            // Filtro base: Solo traer filas que tengan algún valor (Ganancias o Pagos distintos de 0)
            $where = " WHERE m.idvende = ? AND (m.ganancias <> 0 OR m.pagos <> 0) ";

            // 1. Calcular Saldo Anterior y aplicar filtro de fecha
            if ($fechaDesde) {
                // El saldo anterior suma TODO lo previo (los ceros no afectan la suma, así que no hace falta filtrarlos aquí)
                $sqlSaldo = "SELECT SUM(ganancias) - SUM(pagos) FROM ccvendedores WHERE idvende = ? AND DATE(fecha) < ?";
                $stmtS = $pdo->prepare($sqlSaldo);
                $stmtS->execute([$idVende, $fechaDesde]);
                $saldoAnterior = floatval($stmtS->fetchColumn() ?: 0);
                
                // Filtramos la lista principal
                $where .= " AND DATE(m.fecha) >= ? ";
                $params[] = $fechaDesde;
            }

            // 2. Obtener movimientos
            $sql = "SELECT 
                        m.id, 
                        DATE_FORMAT(m.fecha, '%d-%m-%Y') as 'Fecha', 
                        m.tabla as 'Detalle',
                        m.compasoc as 'Comprobante',
                        m.ganancias, 
                        m.pagos
                    FROM ccvendedores m 
                    $where 
                    ORDER BY m.fecha ASC, m.id ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Cálculo de acumulados en PHP (O(n)) partiendo del saldo anterior
            $saldoAcumulado = $saldoAnterior;
            $resultados = [];
            foreach ($rows as $row) {
                $ganancia = floatval($row['ganancias']);
                $pago = floatval($row['pagos']);
                $saldoAcumulado += ($ganancia - $pago);
                
                $row['Ganancias'] = $ganancia;
                $row['Pagos'] = $pago;
                $row['Saldo'] = $saldoAcumulado;
                $resultados[] = $row;
            }

            if ($action === 'export_excel') {
                $vendedorNombre = $pdo->query("SELECT nombre FROM vendedores WHERE id = $idVende")->fetchColumn();
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename=ctacte_vendedor_' . $idVende . '.xls');
                echo "\xEF\xBB\xBF<table border='1'><thead>";
                echo "<tr><th colspan='6' style='background-color: #001020; color: #00ffff;'>RESUMEN DE CUENTA: $vendedorNombre</th></tr>";
                if ($fechaDesde) echo "<tr><th colspan='6' style='background-color: #f8f9fa;'>Desde: $fechaDesde | Saldo Anterior: ".number_format($saldoAnterior, 2, ',', '.')."</th></tr>";
                echo "<tr style='background-color: #445566; color: #ffffff;'>";
                echo "<th>FECHA</th><th>DETALLE</th><th>COMPROBANTE</th><th>GANANCIAS</th><th>PAGOS</th><th>SALDO</th></tr></thead><tbody>";
                
                if ($fechaDesde) {
                    echo "<tr><td>-</td><td style='font-weight:bold'>SALDO ANTERIOR ACUMULADO</td><td>-</td><td style='text-align:right'>0,00</td><td style='text-align:right'>0,00</td><td style='text-align: right; font-weight: bold;'>".number_format($saldoAnterior, 2, ',', '.')."</td></tr>";
                }

                foreach ($resultados as $row) {
                    echo "<tr>
                            <td>{$row['Fecha']}</td>
                            <td>" . htmlspecialchars($row['Detalle']) . "</td>
                            <td>" . htmlspecialchars($row['Comprobante']) . "</td>
                            <td style='text-align: right;'>".number_format($row['Ganancias'], 2, ',', '.')."</td>
                            <td style='text-align: right;'>".number_format($row['Pagos'], 2, ',', '.')."</td>
                            <td style='text-align: right; font-weight: bold;'>".number_format($row['Saldo'], 2, ',', '.')."</td>
                          </tr>";
                }
                echo "</tbody></table>";
                exit;
            }

            echo json_encode([
                'success' => true, 
                'data' => $resultados, 
                'saldoAnterior' => $saldoAnterior,
                'filtros' => ['vendedor' => $idVende, 'desde' => $fechaDesde] // Debug
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}