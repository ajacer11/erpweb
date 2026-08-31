<?php
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $q = trim($_GET['q'] ?? '');
        $sql = "SELECT r.id, r.rama, COUNT(c.id) AS clientes
                FROM ramasclientes r
                LEFT JOIN alb_clientes c ON c.rama = r.id";
        $params = [];
        if ($q !== '') {
            $sql .= " WHERE r.rama LIKE ? OR CAST(r.id AS CHAR) LIKE ?";
            $params = ["%$q%", "%$q%"];
        }
        $sql .= " GROUP BY r.id, r.rama ORDER BY r.rama ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?: [];

    if ($method === 'POST' || $method === 'PUT') {
        $id = (int)($input['id'] ?? 0);
        $rama = trim($input['rama'] ?? '');
        if ($rama === '') throw new Exception('Ingrese el nombre de la rama.');

        $dup = $pdo->prepare("SELECT id FROM ramasclientes WHERE UPPER(rama) = UPPER(?) AND id <> ? LIMIT 1");
        $dup->execute([$rama, $id]);
        if ($dup->fetchColumn()) throw new Exception('Ya existe una rama con ese nombre.');

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE ramasclientes SET rama = ? WHERE id = ?");
            $stmt->execute([$rama, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO ramasclientes (rama) VALUES (?)");
            $stmt->execute([$rama]);
            $id = (int)$pdo->lastInsertId();
        }

        echo json_encode(['success' => true, 'id' => $id]);
        exit;
    }

    if ($method === 'DELETE') {
        $id = (int)($input['id'] ?? 0);
        if ($id <= 0) throw new Exception('Rama inválida.');

        $used = $pdo->prepare("SELECT COUNT(*) FROM alb_clientes WHERE rama = ?");
        $used->execute([$id]);
        $cantidad = (int)$used->fetchColumn();
        if ($cantidad > 0) {
            throw new Exception("No se puede eliminar: la rama está asignada a $cantidad cliente(s).");
        }

        $pdo->prepare("DELETE FROM ramasclientes WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    throw new Exception('Método no permitido.');
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
