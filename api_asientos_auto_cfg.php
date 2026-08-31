<?php
/**
 * api_asientos_auto_cfg.php - PROCESAMIENTO AJAX
 * Encabezado estricto para API y lógica de guardado legacy.
 */

// SEGURIDAD CENTRALIZADA API (Check API Access)
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_data') {
        // 1. Obtener Plan de Cuentas (habilitada = 1 AND imputable = 1)
        $stmtCuentas = $pdo->query("SELECT numcuenta, descripcion FROM plandecuentas WHERE habilitada = 1 AND imputable = 1 ORDER BY numcuenta ASC");
        $cuentas = $stmtCuentas->fetchAll();

        // 2. Obtener Datos Actuales (ID = 1 por ley legacy)
        $stmtData = $pdo->query("SELECT * FROM cuentaspredeterminadas WHERE id = 1");
        $data = $stmtData->fetch();

        if (!$data) throw new Exception("Configuración no encontrada.");

        echo json_encode([
            'success' => true, 
            'data' => $data,
            'accounts' => $cuentas
        ]);
        exit;
    }

    if ($action === 'save') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        $fields = [
            'ventas', 'ivaventas', 'impintventas', 'descuentos', 'recargos',
            'mercaderia', 'proveedores', 'ivacompras', 'impintcompras',
            'perciibb', 'perciva', 'deudores', 'retiva', 'retiibb',
            'costomercaderia', 'retgan', 'ajusteimp', 'ajuste', 'ajustemercaderia',
            'comisionesvend', 'chequesrechazados'
        ];

        foreach ($fields as $f) {
            if (!isset($_POST[$f]) || empty($_POST[$f])) {
                throw new Exception("El campo [$f] es obligatorio.");
            }
        }

        $sql = "UPDATE cuentaspredeterminadas SET
                    ventas = ?, ivaventas = ?, impintventas = ?, descuentos = ?, recargos = ?,
                    mercaderia = ?, proveedores = ?, ivacompras = ?, impintcompras = ?,
                    perciibb = ?, perciva = ?, deudores = ?, retiva = ?, retiibb = ?,
                    costomercaderia = ?, retgan = ?, ajusteimp = ?, ajuste = ?, ajustemercaderia = ?,
                    comisionesvend = ?, chequesrechazados = ?
                WHERE id = 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['ventas'], $_POST['ivaventas'], $_POST['impintventas'], $_POST['descuentos'], $_POST['recargos'],
            $_POST['mercaderia'], $_POST['proveedores'], $_POST['ivacompras'], $_POST['impintcompras'],
            $_POST['perciibb'], $_POST['perciva'], $_POST['deudores'], $_POST['retiva'], $_POST['retiibb'],
            $_POST['costomercaderia'], $_POST['retgan'], $_POST['ajusteimp'], $_POST['ajuste'], $_POST['ajustemercaderia'],
            $_POST['comisionesvend'], $_POST['chequesrechazados']
        ]);

        echo json_encode(['success' => true, 'message' => 'Configuración actualizada correctamente.']);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}