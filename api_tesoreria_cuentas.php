<?php
/**
 * api_tesoreria_cuentas.php - MOTOR DE NEGOCIO PARA MEDIOS DE PAGO
 */

header('Content-Type: application/json');

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    if (($_GET['action'] ?? '') !== 'export') {
        http_response_code(403);
        echo json_encode(['error' => 'Acceso prohibido']);
        exit();
    }
}

require_once 'db.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_tipos':
            // Obtenemos los tipos únicos de cuenta para los filtros
            $stmt = $pdo->query("SELECT DISTINCT tipodecuenta FROM mediosdepago WHERE tipodecuenta IS NOT NULL AND tipodecuenta != '0' ORDER BY tipodecuenta ASC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'list':
        case 'export':
            $estado = $_GET['estado'] ?? '1';
            $tipo = $_GET['tipo'] ?? '';
            $query = $_GET['query'] ?? '';

            $where = ["1=1"];
            $params = [];

            if ($estado !== 'todas') {
                $where[] = "habilitado = ?";
                $params[] = $estado;
            }

            if (!empty($tipo)) {
                $where[] = "tipodecuenta = ?";
                $params[] = $tipo;
            }

            if (!empty($query)) {
                $where[] = "(medio LIKE ? OR tipodecuenta LIKE ?)";
                $params[] = "%$query%";
                $params[] = "%$query%";
            }

            $sql = "SELECT m.*, (SELECT descripcion FROM plandecuentas WHERE numcuenta = m.cuentacontable) as nombre_cuenta_contable
                    FROM mediosdepago m
                    WHERE " . implode(" AND ", $where) . "
                    ORDER BY m.medio ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();

            if ($action === 'export') {
                generarExcelTesoreria($res);
            } else {
                echo json_encode($res);
            }
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM mediosdepago WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;

            // VALIDACIÓN OBLIGATORIA: sin esto, cajas/bancos/cheques quedaban sin cuenta
            // contable propia y sin fallback global posible, generando asientos con
            // cuenta NULL en api_cajas.php, api_tesoreria_movimientos.php, etc.
            if (empty(trim((string)($input['medio'] ?? '')))) {
                throw new Exception("La descripción del medio/cuenta es obligatoria.");
            }
            if (empty($input['tipodecuenta'] ?? '')) {
                throw new Exception("El tipo de cuenta es obligatorio.");
            }
            if (!cuentaContableExiste($pdo, $input['cuentacontable'] ?? null)) {
                throw new Exception("Debe seleccionar una cuenta contable válida del plan de cuentas para este medio de pago.");
            }

            // Varias pantallas (retiros a caja central, pagos, etc.) buscan la cuenta de tipo
            // "Caja Central" con LIMIT 1 sin más criterio: si hubiera más de una habilitada,
            // cuál se usa quedaría librado al azar. Se permite tener una sola a la vez.
            if (($input['tipodecuenta'] ?? '') === 'Caja Central' && (int)($input['habilitado'] ?? 1) === 1) {
                $stmtDupCentral = $pdo->prepare("SELECT id FROM mediosdepago WHERE tipodecuenta = 'Caja Central' AND habilitado = 1 AND id != ? LIMIT 1");
                $stmtDupCentral->execute([(int)$id]);
                if ($stmtDupCentral->fetchColumn()) {
                    throw new Exception("Ya existe una cuenta habilitada de tipo \"Caja Central\". Solo puede haber una a la vez: deshabilite la anterior primero.");
                }
            }

            // Mapeo completo de campos según VB.NET (abmcuentastesoreria.vb)
            $params = [
                mb_convert_case($input['medio'], MB_CASE_TITLE, "UTF-8"),
                $input['habilitadocobro'] ?? 1,
                $input['habilitadopagos'] ?? 1,
                $input['tipodecuenta'],
                $input['habilitado'] ?? 1,
                $input['mostrarendestino'] ?? 1,
                $input['mostrarenorigen'] ?? 1,
                $input['cuentacontable'] ?? null,
                $input['visibletransferenciascuentas'] ?? 1,
                $input['visibleajustescajas'] ?? 1,
                $input['visiblemovcajacentral'] ?? 1,
                $input['visibletransferenciaotracaja'] ?? 1,
                $input['visibleretiroscajacentral'] ?? 1,
                $input['puestoasociado'] ?? 0,
                $input['cuentabancaria'] ?? 0,
                $input['habilitadomovimientosmanuales'] ?? 1,
                $input['visibleenventas'] ?? 1
            ];

            if ($id > 0) {
                $sql = "UPDATE mediosdepago SET 
                        medio=?, habilitadocobro=?, habilitadopagos=?, tipodecuenta=?, habilitado=?, 
                        mostrarendestino=?, mostrarenorigen=?, cuentacontable=?, 
                        visibletransferenciascuentas=?, visibleajustescajas=?, visiblemovcajacentral=?,
                        visibletransferenciaotracaja=?, visibleretiroscajacentral=?,
                        puestoasociado=?, cuentabancaria=?, habilitadomovimientosmanuales=?, visibleenventas=?
                        WHERE id = ?";
                $params[] = $id;
            } else {
                $sql = "INSERT INTO mediosdepago (
                            medio, habilitadocobro, habilitadopagos, tipodecuenta, habilitado, 
                            mostrarendestino, mostrarenorigen, cuentacontable, 
                            visibletransferenciascuentas, visibleajustescajas, visiblemovcajacentral,
                            visibletransferenciaotracaja, visibleretiroscajacentral,
                            puestoasociado, cuentabancaria, habilitadomovimientosmanuales, visibleenventas
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
            }

            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute($params);
            echo json_encode(['success' => $res]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? 0;
            
            // Verificación de integridad: No permitir borrar si hay movimientos en cccuentastesoreria
            $check = $pdo->prepare("SELECT COUNT(*) FROM cccuentastesoreria WHERE idcuenta = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar: la cuenta posee movimientos registrados en el historial.");
            }

            $stmt = $pdo->prepare("DELETE FROM mediosdepago WHERE id = ?");
            echo json_encode(['success' => $stmt->execute([$id])]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function generarExcelTesoreria($datos) {
    $filename = "Cuentas_Tesoreria_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    echo "<html><head><meta http-equiv='Content-type' content='text/html;charset=utf-8' />
    <style>
        .header { background-color: #000020; color: #00ffff; font-weight: bold; text-align: center; }
        .table { border-collapse: collapse; font-family: sans-serif; font-size: 11px; }
        .table th { background-color: #0d2b45; color: #00ffff; border: 1px solid #333; padding: 5px; }
        .table td { border: 1px solid #ccc; padding: 5px; }
    </style></head><body>
    <table class='table'>
        <tr class='header'><td colspan='8'>CUENTAS DE TESORERÍA - WEST IT ERP</td></tr>
        <tr><th>ID</th><th>MEDIO / CUENTA</th><th>TIPO</th><th>COBROS</th><th>PAGOS</th><th>CONTABLE</th><th>INGRESO</th><th>EGRESO</th></tr>";
    foreach($datos as $d) {
        $cob = $d['habilitadocobro'] ? 'SI' : 'NO';
        $pag = $d['habilitadopagos'] ? 'SI' : 'NO';
        echo "<tr>
            <td>{$d['id']}</td>
            <td><b>{$d['medio']}</b></td>
            <td>{$d['tipodecuenta']}</td>
            <td>$cob</td><td>$pag</td>
            <td>{$d['cuentacontable']}</td>
            <td>" . ($d['mostrarendestino'] ? 'SI' : 'NO') . "</td>
            <td>" . ($d['mostrarenorigen'] ? 'SI' : 'NO') . "</td>
        </tr>";
    }
    echo "</table></body></html>";
    exit;
}