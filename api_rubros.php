<?php
/**
 * api_rubros.php - Backend para gestión de Rubros
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- GET: LISTAR O OBTENER UNO ---
    if ($method === 'GET') {
        // Obtener un rubro específico por ID
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT id, rubro, iibbespecial, alicuotaiibb FROM rubros WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => !!$data, 'data' => $data]);
            exit;
        }

        // Listar todos con filtro de búsqueda
        $q = $_GET['q'] ?? '';
        $sql = "SELECT id, rubro, iibbespecial, alicuotaiibb FROM rubros WHERE 1=1";
        $params = [];

        if ($q !== '') {
            $sql .= " AND rubro LIKE ?";
            $params[] = "%$q%";
        }

        $sql .= " ORDER BY rubro ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    // --- POST: AGREGAR O EDITAR ---
    if ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = $data['id'] ?? 0;
        $rubro = trim($data['rubro'] ?? '');
        $iibbespecial = isset($data['iibbespecial']) && $data['iibbespecial'] == 1 ? 1 : 0;
        $alicuotaiibb = $iibbespecial ? (float)($data['alicuotaiibb'] ?? 0) : 0.00; // Lógica VB: Si check es false, guarda 0

        // Valores hardcodeados según lógica legacy VB
        $iva = 21;
        $idiva = 5;

        if (empty($rubro)) {
            throw new Exception("Ingrese un valor válido en el rubro");
        }

        if ($id > 0) {
            // UPDATE
            $sql = "UPDATE rubros SET rubro = ?, iva = ?, idiva = ?, iibbespecial = ?, alicuotaiibb = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rubro, $iva, $idiva, $iibbespecial, $alicuotaiibb, $id]);
            echo json_encode(['success' => true, 'message' => 'Registro modificado correctamente']);
        } else {
            // INSERT
            $sql = "INSERT INTO rubros (rubro, iva, idiva, iibbespecial, alicuotaiibb) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$rubro, $iva, $idiva, $iibbespecial, $alicuotaiibb]);
            echo json_encode(['success' => true, 'message' => 'Registro agregado correctamente']);
        }
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>