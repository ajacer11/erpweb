<?php
/**
 * api_auditoria_login.php - ENDPOINT DE AUDITORÍA DE LOGINS
 * Lee la tabla `login` con filtros y paginación.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        case 'get_logins':
            $usuario   = trim($_GET['usuario']    ?? '');
            $desde     = $_GET['desde']           ?? '';
            $hasta     = $_GET['hasta']           ?? '';
            $usuariopc = trim($_GET['usuariopc']  ?? '');
            $page      = max(1, (int)($_GET['page'] ?? 1));
            $perPage   = 50;
            $offset    = ($page - 1) * $perPage;

            $where  = [];
            $params = [];

            if ($usuario !== '') {
                $where[]  = 'usuario LIKE ?';
                $params[] = "%{$usuario}%";
            }
            if ($usuariopc !== '') {
                $where[]  = 'usuariopc LIKE ?';
                $params[] = "%{$usuariopc}%";
            }
            if ($desde !== '') {
                $where[]  = 'fecha >= ?';
                $params[] = $desde;
            }
            if ($hasta !== '') {
                $where[]  = 'fecha <= ?';
                $params[] = $hasta;
            }

            $sqlWhere = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Total para paginación
            $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM login {$sqlWhere}");
            $stmtCount->execute($params);
            $total = (int) $stmtCount->fetchColumn();

            // Datos paginados
            $stmtData = $pdo->prepare(
                "SELECT id, usuario, fecha, hora, ip, usuariopc, ippublica, host
                 FROM login
                 {$sqlWhere}
                 ORDER BY id DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmtData->execute($params);
            $rows = $stmtData->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'   => true,
                'data'      => $rows,
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'pages'     => (int) ceil($total / $perPage)
            ]);
            break;

        case 'get_usuarios':
            // Lista de usuarios distintos para el filtro
            $stmt = $pdo->query("SELECT DISTINCT usuario FROM login ORDER BY usuario ASC");
            $lista = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'data' => $lista]);
            break;

        default:
            throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
