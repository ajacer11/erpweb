<?php
/**
 * api_recibos.php - MANTENIMIENTO DE RECIBOS EMITIDOS Y EXPORTACIÓN
 * Actualizado: get_detail ahora devuelve nombres de medios y tipos de cuenta.
 * Restaurado: Acciones de change_date y unimpute.
 */
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

// Solo validar AJAX si NO es exportación a Excel
if ($action !== 'export_excel') {
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
        http_response_code(403); exit;
    }
}

require_once 'db.php';
require_once 'permisos_helpers.php';

$ejercicio = $_SESSION['ejercicio'] ?? 1;
$usuario_sesion = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$idcajaabierta = $_SESSION['idcajaabierta'] ?? null;
$idcontrolcaja = $_SESSION['idcontrolcaja'] ?? null;

try {
    if ($action === 'list' || $action === 'export_excel') {
        if ($action === 'export_excel') {
            $data = $_GET;
        } else {
            $data = json_decode(file_get_contents('php://input'), true);
        }

        $desde = ($data['desde'] ?? date('Y-m-d')) . " 00:00:00";
        $hasta = ($data['hasta'] ?? date('Y-m-d')) . " 23:59:59";
        
        $where = " WHERE pagos.fecha BETWEEN ? AND ? ";
        $params = [$desde, $hasta];

        if (!empty($data['ocultarAnulados']) && $data['ocultarAnulados'] != '0') {
            $where .= " AND pagos.estado <> 1000 ";
        }
        if (!empty($data['soloSinImputar']) && $data['soloSinImputar'] != '0') {
            $where .= " AND NOT EXISTS (SELECT 1 FROM cobrosfacturasclientes WHERE idpago = pagos.id) ";
        }
        if (isset($data['cliente']) && $data['cliente'] !== '' && $data['cliente'] !== 'null') {
            $where .= " AND pagos.cliente = ? ";
            $params[] = $data['cliente'];
        }
        if (isset($data['usuario']) && $data['usuario'] !== '' && $data['usuario'] !== 'null') {
            $where .= " AND pagos.usuario = ? ";
            $params[] = $data['usuario'];
        }
        if (isset($data['rama']) && $data['rama'] !== '' && $data['rama'] !== 'null') {
            $where .= " AND pagos.cliente IN (SELECT id FROM alb_clientes WHERE rama = ?) ";
            $params[] = $data['rama'];
        }

        if (esVendedorRestringido()) {
            $where .= " AND pagos.cliente IN (SELECT id FROM alb_clientes WHERE vendedorid = ?) ";
            $params[] = idVendedorRestringido();
        }

        $sql = "SELECT 
                    pagos.id AS 'Num Recibo', 
                    DATE_FORMAT(pagos.fecha, '%d-%m-%Y') AS 'Fecha',
                    (SELECT razon FROM alb_clientes WHERE id = pagos.cliente) AS 'Cliente', 
                    (pagos.imp1 + pagos.imp2 + pagos.retiva + pagos.retgan + pagos.retiibb) AS 'Total', 
                    (SELECT CONCAT(letracomp, '-', ptovta, '-', numcomp) FROM tickets WHERE id = (SELECT idfactura FROM cobrosfacturasclientes WHERE idpago = pagos.id LIMIT 1)) AS 'Imputado a', 
                    (SELECT medio FROM mediosdepago WHERE id = pagos.mp1) AS 'M. de Pago 1', 
                    pagos.imp1 AS 'Importe MP1', 
                    CASE pagos.mp2 WHEN 0 THEN 'Ninguno' ELSE (SELECT medio FROM mediosdepago WHERE id = pagos.mp2) END AS 'M. de Pago 2', 
                    pagos.imp2 AS 'Importe MP2', 
                    CASE pagos.estado WHEN 1000 THEN 'ANULADO' ELSE 'NORMAL' END AS 'Estado', 
                    (SELECT fiscal FROM ccclientes WHERE compasoc = pagos.id AND tabla = 'pagos' LIMIT 1) AS 'Fiscal'
                FROM pagos $where ORDER BY pagos.id DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        if ($action === 'export_excel') {
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=Recibos_Emitidos_" . date('Ymd_His') . ".xls");
            echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
            echo "<head><meta http-equiv='Content-type' content='text/html;charset=utf-8' />";
            echo "<style>
                table { font-family: Arial; border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ccc; padding: 5px; }
                .header-rep { background-color: #001020; color: #00ffff; font-size: 16px; font-weight: bold; text-align: center; }
                .thead { background-color: #445566; color: #ffffff; font-weight: bold; }
                .num { mso-number-format:'\#\,\#\#0\.00'; text-align: right; }
            </style></head><body>";
            echo "<table><tr><th colspan='11' class='header-rep'>REPORTE DE RECIBOS EMITIDOS</th></tr>";
            echo "<tr class='thead'><th>RECIBO</th><th>FECHA</th><th>CLIENTE</th><th>TOTAL</th><th>IMPUTADO</th><th>MP 1</th><th>IMP. 1</th><th>MP 2</th><th>IMP. 2</th><th>FISCAL</th><th>ESTADO</th></tr>";
            foreach ($results as $r) {
                echo "<tr><td>{$r['Num Recibo']}</td><td>{$r['Fecha']}</td><td>".htmlspecialchars($r['Cliente'])."</td><td class='num'>{$r['Total']}</td><td>".htmlspecialchars($r['Imputado a'])."</td><td>{$r['M. de Pago 1']}</td><td class='num'>{$r['Importe MP1']}</td><td>{$r['M. de Pago 2']}</td><td class='num'>{$r['Importe MP2']}</td><td align='center'>".($r['Fiscal'] == 1 ? 'SI' : 'NO')."</td><td>{$r['Estado']}</td></tr>";
            }
            echo "</table></body></html>";
            exit;
        }
        echo json_encode(['success' => true, 'data' => $results]);
        exit;
    }

    switch ($action) {
        case 'get_detail':
            $id = $_GET['id'];

            if (esVendedorRestringido()) {
                $stmtChkR = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = (SELECT cliente FROM pagos WHERE id = ?)");
                $stmtChkR->execute([$id]);
                if ((int) $stmtChkR->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a este recibo.");
                }
            }

            $sqlRecibo = "SELECT p.*,
                         m1.medio as m1_nom, m1.tipodecuenta as m1_tipo,
                         m2.medio as m2_nom, m2.tipodecuenta as m2_tipo
                         FROM pagos p 
                         LEFT JOIN mediosdepago m1 ON p.mp1 = m1.id
                         LEFT JOIN mediosdepago m2 ON p.mp2 = m2.id
                         WHERE p.id = ?";
            $stmt = $pdo->prepare($sqlRecibo);
            $stmt->execute([$id]);
            $recibo = $stmt->fetch();

            $stmtF = $pdo->prepare("SELECT c.*, CONCAT(t.letracomp, '-', t.ptovta, '-', t.numcomp) as comprobante FROM cobrosfacturasclientes c JOIN tickets t ON c.idfactura = t.id WHERE c.idpago = ?");
            $stmtF->execute([$id]);
            $stmtC = $pdo->prepare("SELECT c.*, b.denominacion as banco_nom FROM chequesterceros c LEFT JOIN bancos b ON c.banco = b.id WHERE c.compasoc = ?");
            $stmtC->execute([$id]);

            echo json_encode(['success' => true, 'recibo' => $recibo, 'facturas' => $stmtF->fetchAll(), 'cheques' => $stmtC->fetchAll()]);
            break;

        case 'change_date':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $fecha = $data['fecha'];

            if (esVendedorRestringido()) {
                $stmtChkR = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = (SELECT cliente FROM pagos WHERE id = ?)");
                $stmtChkR->execute([$id]);
                if ((int) $stmtChkR->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a este recibo.");
                }
            }

            $pdo->beginTransaction();
            // Actualizar tabla pagos
            $pdo->prepare("UPDATE pagos SET fecha = ? WHERE id = ?")->execute([$fecha, $id]);
            // Actualizar cuenta corriente clientes
            $pdo->prepare("UPDATE ccclientes SET fecha = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$fecha, $id]);
            // Actualizar tesorería
            $pdo->prepare("UPDATE cccuentastesoreria SET fecha = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$fecha, $id]);
            // Actualizar libro diario
            $pdo->prepare("UPDATE librodiario SET fecha = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$fecha, $id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'unimpute':
            $data = json_decode(file_get_contents('php://input'), true);
            $idPago = $data['id'];
            $total = $data['total'];

            if (esVendedorRestringido()) {
                $stmtChkR = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = (SELECT cliente FROM pagos WHERE id = ?)");
                $stmtChkR->execute([$idPago]);
                if ((int) $stmtChkR->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a este recibo.");
                }
            }

            $pdo->beginTransaction();
            // 1. Liberar facturas
            $stmt = $pdo->prepare("SELECT idfactura FROM cobrosfacturasclientes WHERE idpago = ?");
            $stmt->execute([$idPago]);
            foreach ($stmt->fetchAll() as $f) { 
                $pdo->prepare("UPDATE tickets SET pagada = 0 WHERE id = ?")->execute([$f['idfactura']]); 
            }
            // 2. Borrar imputaciones
            $pdo->prepare("DELETE FROM cobrosfacturasclientes WHERE idpago = ?")->execute([$idPago]);
            // 3. Volver monto a "saldoaimputar"
            $pdo->prepare("UPDATE pagos SET saldoaimputar = ? WHERE id = ?")->execute([$total, $idPago]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'annul':
            $data = json_decode(file_get_contents('php://input'), true);
            $idRecibo = $data['id'];
            $fechaAnulacion = $data['annul_date'];

            if (esVendedorRestringido()) {
                $stmtChkR = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = (SELECT cliente FROM pagos WHERE id = ?)");
                $stmtChkR->execute([$idRecibo]);
                if ((int) $stmtChkR->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a este recibo.");
                }
            }

            $pdo->beginTransaction();
            $stmtRec = $pdo->prepare("SELECT * FROM pagos WHERE id = ?");
            $stmtRec->execute([$idRecibo]);
            $recibo = $stmtRec->fetch();

            $importeEfectivo = 0;
            $medios = [['id' => $recibo['mp1'], 'imp' => $recibo['imp1']], ['id' => $recibo['mp2'], 'imp' => $recibo['imp2']]];
            foreach ($medios as $m) {
                if ($m['id'] > 0) {
                    $stmtM = $pdo->prepare("SELECT tipodecuenta FROM mediosdepago WHERE id = ?");
                    $stmtM->execute([$m['id']]);
                    if ($stmtM->fetchColumn() === 'Caja de Usuarios') $importeEfectivo += $m['imp'];
                }
            }
            if ($importeEfectivo > 0 && !$idcajaabierta) throw new Exception("El recibo contiene efectivo. Debe tener caja abierta.");
            $pdo->prepare("UPDATE pagos SET estado = 1000 WHERE id = ?")->execute([$idRecibo]);
            $pdo->prepare("UPDATE ccclientes SET haber = 0 WHERE compasoc = ? AND tabla = 'pagos'")->execute([$idRecibo]);
            $stmtFac = $pdo->prepare("SELECT idfactura FROM cobrosfacturasclientes WHERE idpago = ?");
            $stmtFac->execute([$idRecibo]);
            foreach ($stmtFac->fetchAll() as $f) { $pdo->prepare("UPDATE tickets SET pagada = 0 WHERE id = ?")->execute([$f['idfactura']]); }
            if ($importeEfectivo > 0) {
                $pdo->prepare("INSERT INTO cajaxusuario (usuario, debe, haber, idcaja, idcontrolcaja, fechamov, tipomov, horamov) VALUES (?, 0, ?, ?, ?, ?, ?, ?)")
                    ->execute([$usuario_sesion, $importeEfectivo, $idcajaabierta, $idcontrolcaja, $fechaAnulacion, "Anulación Recibo $idRecibo", date('H:i')]);
                $pdo->prepare("UPDATE cajas SET saldo = saldo - ? WHERE id = ?")->execute([$importeEfectivo, $idcajaabierta]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Anulado correctamente']);
            break;

        case 'toggle_fiscal':
            $data = json_decode(file_get_contents('php://input'), true);

            if (esVendedorRestringido()) {
                $stmtChkR = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = (SELECT cliente FROM pagos WHERE id = ?)");
                $stmtChkR->execute([$data['id']]);
                if ((int) $stmtChkR->fetchColumn() !== idVendedorRestringido()) {
                    throw new Exception("No tiene acceso a este recibo.");
                }
            }

            $nuevo = $data['actual'] == 1 ? 0 : 1;
            $pdo->prepare("UPDATE ccclientes SET fiscal = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$nuevo, $data['id']]);
            $pdo->prepare("UPDATE cccuentastesoreria SET fiscal = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$nuevo, $data['id']]);
            $pdo->prepare("UPDATE librodiario SET fiscal = ? WHERE compasoc = ? AND tabla = 'pagos'")->execute([$nuevo, $data['id']]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
