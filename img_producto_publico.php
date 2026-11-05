<?php
/**
 * img_producto_publico.php - Sirve la foto de un producto para la API pública.
 *
 * Variante sin sesión de img_producto.php, para <img src="..."> embebidos en
 * catálogos de tiendas externas. Se autentica igual que api_productos_externa.php
 * (mismo token), y valida que el código pedido sea un producto real y visible
 * en catálogo del tenant dueño del token (evita servir/enumerar archivos ajenos).
 */
require_once 'db_master.php'; // da $pdo_master, getTenantDatabaseConfig(), parseConnectionString()

$token = $_GET['token'] ?? '';
$codigo = $_GET['codigo'] ?? '';
if ($token === '' || $codigo === '') {
    http_response_code(400);
    exit;
}

// Slot 1 = foto de portada (nombre histórico sin sufijo). Slots 2 a 5 = adicionales.
$slot = (int)($_GET['slot'] ?? 1);
if ($slot < 1 || $slot > 5) $slot = 1;

try {
    $stmt = $pdo_master->prepare("SELECT cuit, db_id, habilitado FROM api_config_productos_externa WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $config = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

if (!$config || !$config['habilitado']) {
    http_response_code(403);
    exit;
}

try {
    $dbConfig = getTenantDatabaseConfig($pdo_master, $config['cuit'], $config['db_id'] ?: null);
    if (!$dbConfig) throw new Exception('Config de tenant no encontrada');
    $cfg = parseConnectionString($dbConfig['cadenaconec']);
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT    => 5,
        PDO::ATTR_PERSISTENT => false,
    ]);
} catch (Exception $e) {
    http_response_code(503);
    exit;
}

$stmtP = $pdo->prepare("SELECT codigo FROM alb_productos WHERE codigo = ? AND habilitado = 1 AND mostrarencatalogo = 1 LIMIT 1");
$stmtP->execute([$codigo]);
if (!$stmtP->fetch()) {
    http_response_code(404);
    exit;
}

$cuitSafe = preg_replace('/[^a-zA-Z0-9]/', '', $config['cuit']);
$safeCode = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $codigo);
$safeCode = $slot === 1 ? $safeCode : "{$safeCode}_{$slot}";
$dir = __DIR__ . "/img/productos/$cuitSafe";

$mimeByExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

foreach ($mimeByExt as $ext => $mime) {
    $path = "$dir/$safeCode.$ext";
    if (is_file($path)) {
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

http_response_code(404);
