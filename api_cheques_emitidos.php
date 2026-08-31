<?php
/**
 * api_cheques_emitidos.php - MOTOR DE DATOS PARA GESTIÓN DE CHEQUES PROPIOS
 * Versión mejorada con exportación profesional a Excel (HTML/CSS)
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

try {
    switch ($action) {
        case 'list':
        case 'export':
            $estadoStr = $_GET['estado'] ?? '';
            $desde = $_GET['desde'] ?? date('Y-m-01');
            $hasta = $_GET['hasta'] ?? date('Y-m-t');
            $hoy = date('Y-m-d');

            $where = ["fechavencimiento >= ?", "fechavencimiento <= ?"];
            $params = [$desde, $hasta];

            // Replicar lógica del Select Case de VB.NET
            switch ($estadoStr) {
                case 'A vencer':
                    $where[] = "fechavencimiento >= ?";
                    $where[] = "estado = 1";
                    $params[] = $hoy;
                    break;
                case 'Vencidos':
                    $where[] = "fechavencimiento < ?";
                    $where[] = "estado = 1";
                    $params[] = $hoy;
                    break;
                case 'Pagados':
                    $where[] = "estado = 2";
                    break;
            }

            $sql = "SELECT 
                        c.id, 
                        p.razon AS proveedor, 
                        c.numero, 
                        c.fechaemision, 
                        c.fechavencimiento, 
                        c.importe, 
                        b.denominacion AS banco, 
                        c.tipo, 
                        c.observaciones, 
                        cp.referencia AS cuenta,
                        c.estado
                    FROM chequespropios c
                    LEFT JOIN Proveedores p ON p.id = c.proveedor
                    LEFT JOIN bancos b ON b.id = c.banco
                    LEFT JOIN cuentaspropias cp ON cp.id = c.cuenta
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY c.fechavencimiento ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($action === 'export') {
                generarExcelProfesional($cheques, $desde, $hasta, $estadoStr);
            } else {
                echo json_encode($cheques);
            }
            break;

        case 'mark_paid':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;

            if ($id <= 0) throw new Exception("ID de cheque inválido");

            $pdo->beginTransaction();

            $stmtCheque = $pdo->prepare("SELECT importe, cuenta, numero FROM chequespropios WHERE id = ?");
            $stmtCheque->execute([$id]);
            $cheque = $stmtCheque->fetch(PDO::FETCH_ASSOC);
            if (!$cheque) throw new Exception("Cheque no encontrado.");

            $pdo->prepare("UPDATE chequespropios SET estado = 2 WHERE id = ?")->execute([$id]);

            // Asiento contable: Cheques Emitidos (debe, se cancela el pasivo) / Banco
            // (haber, sale el dinero). Antes esta acción solo cambiaba el estado y nunca
            // generaba la contrapartida contable ni el movimiento del subdiario bancario.
            $importe = round((float)$cheque['importe'], 2);
            $detalle = "Débito Chq Propio Nº " . $cheque['numero'];
            $fecha = date('Y-m-d');

            $ejerPer = resolverEjercicioPeriodo($pdo, $fecha);
            $numAsiento = siguienteNumeroAsiento($pdo, $ejerPer['ejercicio']);

            $stmtMedioBanco = $pdo->prepare("SELECT id FROM mediosdepago WHERE tipodecuenta = 'Bancos' AND cuentabancaria = ? AND habilitado = 1 LIMIT 1");
            $stmtMedioBanco->execute([$cheque['cuenta']]);
            $idMedioBanco = $stmtMedioBanco->fetchColumn();
            if (!$idMedioBanco) {
                throw new Exception("La cuenta bancaria del cheque no tiene un medio de pago de Tesorería vinculado (Tesorería > Cuentas > vincular a Cuenta Bancaria Propia).");
            }
            $ctaBanco = resolverCuentaMedioPago($pdo, (int)$idMedioBanco, 'el cobro de un cheque propio');
            $ctaEmitidos = resolverCuentaMedioPagoPorTipo($pdo, 'Cheques Emitidos', 'el cobro de un cheque propio (emitidos)');

            grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaEmitidos, $importe, 0, $id, 'chequespropios', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);
            grabarLineaAsiento($pdo, $numAsiento, $fecha, $ctaBanco, 0, $importe, $id, 'chequespropios', 1, $usuario, $detalle, $ejerPer['ejercicio'], $ejerPer['periodo']);

            // Movimiento bancario (subdiario), mismo patrón que el depósito de cartera
            $stmtS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
            $stmtS->execute([$cheque['cuenta']]);
            $saldoAnt = $stmtS->fetchColumn() ?: 0;
            $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, 0, ?, ?, CURDATE(), ?, 'chequespropios', ?)")
                ->execute([$cheque['cuenta'], $importe, $saldoAnt - $importe, $id, $detalle]);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Acción no definida");
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Genera un archivo Excel con formato profesional (HTML + CSS)
 */
function generarExcelProfesional($datos, $desde, $hasta, $estado) {
    $filename = "Reporte_Cheques_Emitidos_" . date('Ymd_His') . ".xls";
    
    // Headers para forzar descarga como Excel
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Pragma: public");

    // Estilos CSS integrados para que Excel los interprete
    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta http-equiv='Content-type' content='text/html;charset=utf-8' />
        <style>
            .header-report { background-color: #000020; color: #00ffff; font-family: 'Segoe UI', sans-serif; font-size: 18px; font-weight: bold; text-align: center; }
            .info-table { font-family: 'Segoe UI', sans-serif; font-size: 12px; color: #333; margin-bottom: 20px; }
            .table-main { border-collapse: collapse; font-family: 'Segoe UI', sans-serif; font-size: 11px; width: 100%; }
            .table-main th { background-color: #0d2b45; color: #00ffff; border: 1px solid #222; padding: 10px; text-transform: uppercase; }
            .table-main td { border: 1px solid #ccc; padding: 8px; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .fw-bold { font-weight: bold; }
            .total-row { background-color: #f1f1f1; font-weight: bold; font-size: 13px; }
            .status-pagado { color: #28a745; font-weight: bold; }
            .status-vencido { color: #dc3545; font-weight: bold; }
        </style>
    </head>
    <body>
        <table class='info-table'>
            <tr><td colspan='9' class='header-report'>REPORTE DE CHEQUES EMITIDOS - WEST IT ERP</td></tr>
            <tr><td colspan='9'></td></tr>
            <tr>
                <td colspan='2'><b>Rango de Fechas:</b></td>
                <td colspan='2'>" . date('d/m/Y', strtotime($desde)) . " hasta " . date('d/m/Y', strtotime($hasta)) . "</td>
                <td colspan='2'><b>Estado Filtrado:</b></td>
                <td colspan='3'>" . ($estado ? mb_strtoupper($estado) : 'TODOS') . "</td>
            </tr>
            <tr><td colspan='9'><b>Fecha de Generación:</b> " . date('d/m/Y H:i') . "</td></tr>
        </table>

        <table class='table-main'>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>CHEQUE №</th>
                    <th>PROVEEDOR</th>
                    <th>BANCO</th>
                    <th>FECHA EMISIÓN</th>
                    <th>FECHA VENC.</th>
                    <th>CUENTA</th>
                    <th>ESTADO</th>
                    <th>IMPORTE</th>
                </tr>
            </thead>
            <tbody>";

    $totalImporte = 0;
    foreach ($datos as $row) {
        $totalImporte += $row['importe'];
        
        $labelEstado = ($row['estado'] == 2) ? 'PAGADO' : 'EMITIDO';
        $classEstado = ($row['estado'] == 2) ? 'status-pagado' : '';
        
        // Si no está pagado y la fecha pasó, es vencido
        if ($row['estado'] != 2 && strtotime($row['fechavencimiento']) < strtotime(date('Y-m-d'))) {
            $labelEstado = 'VENCIDO';
            $classEstado = 'status-vencido';
        }

        echo "
                <tr>
                    <td class='text-center'>{$row['id']}</td>
                    <td class='fw-bold'>{$row['numero']}</td>
                    <td>" . htmlspecialchars($row['proveedor'] ?? 'S/P') . "</td>
                    <td>{$row['banco']}</td>
                    <td class='text-center'>" . date('d/m/Y', strtotime($row['fechaemision'])) . "</td>
                    <td class='text-center fw-bold'>" . date('d/m/Y', strtotime($row['fechavencimiento'])) . "</td>
                    <td>{$row['cuenta']}</td>
                    <td class='text-center $classEstado'>$labelEstado</td>
                    <td class='text-end fw-bold'>" . number_format($row['importe'], 2, ',', '.') . "</td>
                </tr>";
    }

    echo "
            </tbody>
            <tfoot>
                <tr class='total-row'>
                    <td colspan='8' class='text-end'>TOTAL CARTERA EXPORTADA:</td>
                    <td class='text-end'>$ " . number_format($totalImporte, 2, ',', '.') . "</td>
                </tr>
            </tfoot>
        </table>
    </body>
    </html>";
    exit;
}