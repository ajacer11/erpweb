<?php
/**
 * api_conceptos_compra.php - ENDPOINT PARA CONCEPTOS DE GASTOS/COMPRA
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
        case 'export_excel':
            $sql = "SELECT 
                        c.id AS 'ID', 
                        c.codigo AS 'Codigo', 
                        c.concepto AS 'Concepto', 
                        c.habilitada AS 'Habilitada',
                        c.cuentacontable AS 'Num Cuenta Cont.',
                        p.descripcion AS 'Cuenta Contable'
                    FROM conceptosgastos c
                    LEFT JOIN plandecuentas p ON c.cuentacontable = p.numcuenta
                    WHERE 1=1";
            if ($action === 'list' && ($_GET['solo_habilitados'] ?? '') === '1') {
                $sql .= " AND c.habilitada = 1";
            }
            $sql .= " ORDER BY c.concepto ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $data = $stmt->fetchAll();

            if ($action === 'export_excel') {
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename=conceptos_compra_' . date('Ymd') . '.xls');
                echo "\xEF\xBB\xBF<table border='1'><thead><tr style='background-color: #000020; color: #00ffff;'>";
                if (!empty($data)) {
                    foreach (array_keys($data[0]) as $col) echo "<th>" . strtoupper($col) . "</th>";
                    echo "</tr></thead><tbody>";
                    foreach ($data as $row) {
                        echo "<tr>";
                        foreach ($row as $val) echo "<td>" . htmlspecialchars($val) . "</td>";
                        echo "</tr>";
                    }
                }
                echo "</tbody></table>";
                exit;
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['codigo']) || empty($data['concepto']) || empty($data['cuentacontable'])) {
                throw new Exception("Debe completar todos los campos obligatorios.");
            }

            if (!empty($data['id'])) {
                // UPDATE
                $sql = "UPDATE conceptosgastos SET codigo = ?, concepto = ?, cuentacontable = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['codigo'], $data['concepto'], $data['cuentacontable'], $data['id']]);
                $msg = "Registro modificado correctamente.";
            } else {
                // INSERT
                $sql = "INSERT INTO conceptosgastos (codigo, concepto, cuentacontable) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['codigo'], $data['concepto'], $data['cuentacontable']]);
                $msg = "Registro agregado correctamente.";
            }
            echo json_encode(['success' => true, 'message' => $msg]);
            break;

        case 'set_habilitada':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
            $data = json_decode(file_get_contents('php://input'), true);
            $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
            $habilitada = $data['habilitada'] ?? null;
            if (!$id || !in_array($habilitada, [0, 1], true)) throw new Exception('Estado de concepto inválido.');
            $stmt = $pdo->prepare('UPDATE conceptosgastos SET habilitada = ? WHERE id = ?');
            $stmt->execute([$habilitada, $id]);
            if ($stmt->rowCount() === 0) {
                $existe = $pdo->prepare('SELECT 1 FROM conceptosgastos WHERE id = ?');
                $existe->execute([$id]);
                if (!$existe->fetchColumn()) throw new Exception('No se encontró el concepto.');
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];

            // Validación de integridad referencial (Réplica de VB.NET)
            $stmtCheck = $pdo->prepare("SELECT 1 FROM lineaconceptoscompra WHERE idconcepto = ? LIMIT 1");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetch()) {
                throw new Exception("NO SE PUEDEN ELIMINAR CONCEPTOS QUE HAYAN SIDO UTILIZADOS EN FACTURAS DE COMPRA.");
            }

            $stmtDel = $pdo->prepare("DELETE FROM conceptosgastos WHERE id = ?");
            $stmtDel->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'get_cuentas':
            // Trae cuentas imputables y habilitadas (Réplica de VB.NET altaconceptos_Load)
            $sql = "SELECT numcuenta as id, descripcion as text FROM plandecuentas WHERE imputable = 1 AND habilitada = 1 ORDER BY descripcion ASC";
            echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
