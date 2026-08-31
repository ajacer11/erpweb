<?php
/**
 * api_familias.php - Backend para gestión de Familias
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT id, nombre, habilitada FROM familias WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => !!$data, 'data' => $data]);
            exit;
        }

        $q = $_GET['q'] ?? '';
        $sql = "SELECT id, nombre, habilitada FROM familias WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND nombre LIKE ?";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY nombre ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = $data['id'] ?? 0;
        $nombre = trim($data['nombre'] ?? '');
        $habilitada = isset($data['habilitada']) && $data['habilitada'] == 1 ? 1 : 0;

        if (empty($nombre)) {
            throw new Exception("Ingrese un valor para el nombre de la familia");
        }

        if ($id > 0) {
            $sql = "UPDATE familias SET nombre = ?, habilitada = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $habilitada, $id]);
            echo json_encode(['success' => true, 'message' => 'Familia modificada correctamente']);
        } else {
            $sql = "INSERT INTO familias (nombre, habilitada) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $habilitada]);
            echo json_encode(['success' => true, 'message' => 'Familia agregada correctamente']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>