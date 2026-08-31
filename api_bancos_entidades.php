<?php
/**
 * api_bancos_entidades.php - BACKEND DE GESTIÓN DE BANCOS
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $q = isset($_GET['q']) ? trim($_GET['q']) : '';
            
            if ($q === '') {
                // Sin filtro: traer todo
                $sql = "SELECT * FROM bancos ORDER BY denominacion ASC";
                $stmt = $pdo->query($sql);
            } else {
                // Con filtro: buscar en denominación o código
                // Usamos CAST para asegurar que la comparación LIKE funcione en columnas INT
                $sql = "SELECT * FROM bancos 
                        WHERE denominacion LIKE ? OR CAST(codigo AS CHAR) LIKE ? 
                        ORDER BY denominacion ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$q%", "%$q%"]);
            }
            
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM bancos WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            $id = (int)$input['id'];
            $codigo = (int)$input['codigo'];
            $denominacion = mb_strtoupper(trim($input['denominacion']), 'UTF-8');

            if ($id > 0) {
                $sql = "UPDATE bancos SET codigo = ?, denominacion = ? WHERE id = ?";
                $params = [$codigo, $denominacion, $id];
            } else {
                $sql = "INSERT INTO bancos (codigo, denominacion, poseecuenta) VALUES (?, ?, 0)";
                $params = [$codigo, $denominacion];
            }

            $stmt = $pdo->prepare($sql);
            echo json_encode(['success' => $stmt->execute($params)]);
            break;

        case 'delete':
            $id = $_GET['id'] ?? 0;
            if ($id <= 0) throw new Exception("ID inválido");

            // VALIDACIONES DE INTEGRIDAD REFERENCIAL
            
            // 1. Cheques de Terceros
            $chk1 = $pdo->prepare("SELECT COUNT(*) FROM chequesterceros WHERE banco = ?");
            $chk1->execute([$id]);
            if ($chk1->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar: Esta entidad está asociada a CHEQUES DE TERCEROS.");
            }

            // 2. Cheques Propios
            $chk2 = $pdo->prepare("SELECT COUNT(*) FROM chequespropios WHERE banco = ?");
            $chk2->execute([$id]);
            if ($chk2->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar: Esta entidad está asociada a CHEQUES PROPIOS emitidos.");
            }

            // 3. Cuentas Propias (Nuestras cuentas bancarias)
            $chk3 = $pdo->prepare("SELECT COUNT(*) FROM cuentaspropias WHERE banco = ?");
            $chk3->execute([$id]);
            if ($chk3->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar: Poseemos CUENTAS BANCARIAS PROPIAS en esta entidad.");
            }

            // Si pasa todas las validaciones, eliminar
            $del = $pdo->prepare("DELETE FROM bancos WHERE id = ?");
            echo json_encode(['success' => $del->execute([$id])]);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}