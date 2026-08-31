<?php
/**
 * api_marcas.php - Backend para gestión de Marcas
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- GET: LISTAR O OBTENER UNO ---
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT id, marca, habilitada FROM Marcas WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => !!$data, 'data' => $data]);
            exit;
        }

        $q = $_GET['q'] ?? '';
        $sql = "SELECT id, marca, habilitada FROM Marcas WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND marca LIKE ?";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY marca ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- POST: AGREGAR O EDITAR ---
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = $data['id'] ?? 0;
        $marca = trim($data['marca'] ?? '');
        $habilitada = isset($data['habilitada']) && $data['habilitada'] == 1 ? 1 : 0;

        if (empty($marca)) {
            throw new Exception("Ingrese un valor para el nombre de la marca");
        }

        if ($id > 0) {
            $sql = "UPDATE Marcas SET marca = ?, habilitada = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$marca, $habilitada, $id]);
            echo json_encode(['success' => true, 'message' => 'Marca modificada correctamente']);
        } else {
            $sql = "INSERT INTO Marcas (marca, habilitada) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$marca, $habilitada]);
            echo json_encode(['success' => true, 'message' => 'Marca agregada correctamente']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>