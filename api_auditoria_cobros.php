<?php
/**
 * api_auditoria_cobros.php - BACKEND
 * Ejecuta la auditoría de consistencia financiera de clientes.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_data' || $action === 'export') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        // CONSULTA DE AUDITORÍA (Fiel a controldecobrosaclicentes.vb)
        // Lógica: 
        // 1. ccclientes: Saldo contable (Debe - Haber)
        // 2. tickets (Facturas): Total - Pagado (en cobrosfacturasclientes)
        // 3. Comparación y filtro por diferencia > 0.1
        // 4. Exclusión tipos comp: 3 (NC A), 8 (NC B), 13 (NC C), 101 (Presupuesto/Remito según implement.)

        $sql = "
            SELECT 
                c.id AS 'ID', 
                c.razon AS 'Cliente', 
                ROUND(IFNULL(cc.saldo_cc,0),2) AS SaldoCC, 
                ROUND(IFNULL(fac.saldo_facturas,0),2) AS SaldoFacturas, 
                ROUND(IFNULL(cc.saldo_cc,0)-IFNULL(fac.saldo_facturas,0),2) AS Diferencia 
            FROM alb_clientes c 
            LEFT JOIN (
                SELECT cliente, SUM(debe-haber) saldo_cc 
                FROM ccclientes 
                GROUP BY cliente
            ) cc ON cc.cliente=c.id 
            LEFT JOIN (
                SELECT t.cliente, SUM(t.total-IFNULL(p.pagado,0)) saldo_facturas 
                FROM tickets t 
                LEFT JOIN (
                    SELECT idfactura, SUM(importepagado) pagado 
                    FROM cobrosfacturasclientes 
                    GROUP BY idfactura
                ) p ON p.idfactura=t.id 
                WHERE t.pagada=0 AND t.tipocomp NOT IN (3,8,13,101) 
                GROUP BY t.cliente
            ) fac ON fac.cliente=c.id 
            WHERE ABS(IFNULL(cc.saldo_cc,0)-IFNULL(fac.saldo_facturas,0)) > 0.1
            ORDER BY c.razon ASC
        ";

        // NOTA DE SEGURIDAD: Esta consulta es pesada.
        // En un entorno de producción grande, podría requerir optimización o índices adicionales.
        // Se ejecuta tal cual la lógica legacy para garantizar fidelidad de datos.

        // Aumentar tiempo de ejecución para esta consulta específica si es necesario
        set_time_limit(120);

        $stmt = $pdo->query($sql);
        $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // EXPORTACIÓN A EXCEL (Formato XLS HTML Table)
        if ($action === 'export') {
            if (empty($resultados)) throw new Exception("No hay diferencias para exportar.");

            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                <!--[if gte mso 9]>
                <xml>
                <x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>
                <x:Name>Auditoria Cobros</x:Name>
                <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
                </x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook>
                </xml>
                <![endif]-->
                <style>
                    body { font-family: Arial, sans-serif; font-size: 10pt; }
                    table { border-collapse: collapse; width: 100%; }
                    th { 
                        background-color: #800000; /* Rojo oscuro para alertas */
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
                    .num { mso-number-format: "#,##0.00"; text-align: right; }
                    .text { mso-number-format: "\@"; text-align: left; }
                </style>
            </head>
            <body>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Saldo Cta. Cte.</th>
                            <th>Saldo Facturas</th>
                            <th>Diferencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resultados as $row): ?>
                            <tr>
                                <td class="text"><?php echo $row['ID']; ?></td>
                                <td class="text"><?php echo htmlspecialchars($row['Cliente']); ?></td>
                                <td class="num"><?php echo $row['SaldoCC']; ?></td>
                                <td class="num"><?php echo $row['SaldoFacturas']; ?></td>
                                <td class="num" style="color: red; font-weight: bold;"><?php echo $row['Diferencia']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $xlsContent = ob_get_clean();

            echo json_encode([
                'success' => true,
                'xls_base64' => base64_encode($xlsContent),
                'filename' => 'Auditoria_Cobros_' . date('Ymd_His') . '.xls'
            ]);
            exit;
        }

        // RESPUESTA JSON NORMAL
        echo json_encode(['success' => true, 'data' => $resultados]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}