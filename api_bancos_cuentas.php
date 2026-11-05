<?php
/**
 * api_bancos_cuentas.php - MOTOR DE NEGOCIO PARA CUENTAS BANCARIAS
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_bancos':
            $stmt = $pdo->query("SELECT id, denominacion FROM bancos ORDER BY denominacion ASC");
            echo json_encode($stmt->fetchAll());
            break;

        case 'list':
        case 'export':
            $query = $_GET['query'] ?? '';
            $sql = "SELECT cp.*, b.denominacion as banco_nombre 
                    FROM cuentaspropias cp
                    INNER JOIN bancos b ON b.id = cp.banco
                    WHERE cp.referencia LIKE ? OR b.denominacion LIKE ? OR cp.numcbu LIKE ?
                    ORDER BY cp.referencia ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$query%", "%$query%", "%$query%"]);
            $resultados = $stmt->fetchAll();

            if ($action === 'export') {
                generarExcelCuentas($resultados);
            } else {
                echo json_encode($resultados);
            }
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM cuentaspropias WHERE id = ?");
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = $input['id'] ?? 0;

            // Mapeo de campos según VB.NET
            $params = [
                $input['referencia'],
                $input['banco'],
                $input['sucursal'],
                $input['numsucursal'],
                $input['numcuenta'],
                $input['cbu'],
                $input['aliasbanco'],
                $input['tipo'],
                $input['moneda']
            ];

            if ($id > 0) {
                $sql = "UPDATE cuentaspropias SET 
                        referencia = ?, banco = ?, sucursal = ?, numsucursal = ?, 
                        numcuenta = ?, numcbu = ?, alias = ?, tipocuenta = ?, moneda = ?
                        WHERE id = ?";
                $params[] = $id;
            } else {
                $sql = "INSERT INTO cuentaspropias (referencia, banco, sucursal, numsucursal, numcuenta, numcbu, alias, tipocuenta, moneda) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt = $pdo->prepare($sql);
            $res = $stmt->execute($params);
            echo json_encode(['success' => $res]);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Genera un reporte Excel profesional de las cuentas
 */
function generarExcelCuentas($datos) {
    $filename = "Listado_Cuentas_Bancarias_" . date('Ymd_His') . ".xls";
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");

    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head><meta http-equiv='Content-type' content='text/html;charset=utf-8' />
    <style>
        .header { background-color: #000020; color: #00ffff; font-family: sans-serif; font-size: 16px; font-weight: bold; text-align: center; }
        .table { border-collapse: collapse; font-family: sans-serif; font-size: 11px; }
        .table th { background-color: #0d2b45; color: #00ffff; border: 1px solid #333; padding: 8px; }
        .table td { border: 1px solid #ccc; padding: 6px; }
    </style>
    </head>
    <body>
        <table class='table'>
            <tr><td colspan='9' class='header'>CUENTAS BANCARIAS - WEST IT ERP</td></tr>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>REFERENCIA</th>
                    <th>BANCO</th>
                    <th>TIPO</th>
                    <th>MONEDA</th>
                    <th>SUCURSAL</th>
                    <th>NUM CUENTA</th>
                    <th>C.B.U.</th>
                    <th>ALIAS</th>
                </tr>
            </thead>
            <tbody>";
    
    foreach($datos as $d) {
        echo "<tr>
                <td>{$d['id']}</td>
                <td><b>{$d['referencia']}</b></td>
                <td>{$d['banco_nombre']}</td>
                <td>{$d['tipocuenta']}</td>
                <td>{$d['moneda']}</td>
                <td>{$d['sucursal']} ({$d['numsucursal']})</td>
                <td>'{$d['numcuenta']}</td>
                <td>'{$d['cbu']}</td>
                <td>{$d['alias']}</td>
              </tr>";
    }

    echo "</tbody></table></body></html>";
    exit;
}