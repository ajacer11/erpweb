<?php
/**
 * img_producto.php - Sirve la foto de un producto (multi-tenant).
 *
 * Se usa como <img src="img_producto.php?codigo=...">. Un <img> nunca manda
 * el header X-Requested-With, así que este archivo NO puede pasar por
 * check_access.php / check_api_access.php (ambos exigen AJAX) — tiene su
 * propio guard mínimo: sesión activa + que el código pedido sea realmente
 * un producto del tenant conectado (evita servir/enumerar archivos ajenos).
 *
 * Orden de búsqueda:
 *   1. Archivo nuevo: img/productos/{cuit}/{codigo}.{jpg|png|webp}
 *      (subido desde productos_add.php, vía api_productos_foto.php)
 *   2. BLOB legacy en la tabla fotosproductos (así guardaba las fotos
 *      el sistema de escritorio VB.NET). Se sirve igual para no perder
 *      fotos ya cargadas, aunque el destino final es el archivo en disco.
 *   3. 404 si no hay nada — el frontend cae a un ícono, no rompe la UI.
 */
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tenant_cuit'])) {
    http_response_code(401);
    exit;
}

$codigo = $_GET['codigo'] ?? '';
if ($codigo === '') {
    http_response_code(400);
    exit;
}

// Slot 1 = foto de portada (nombre histórico sin sufijo, {codigo}.jpg).
// Slots 2 a 5 = imágenes adicionales ({codigo}_{slot}.jpg). Ver api_productos_foto.php.
$slot = (int)($_GET['slot'] ?? 1);
if ($slot < 1 || $slot > 5) $slot = 1;

require_once 'db.php';

// El código debe corresponder a un producto real del tenant conectado.
$stmt = $pdo->prepare("SELECT codigo FROM alb_productos WHERE codigo = ? LIMIT 1");
$stmt->execute([$codigo]);
if (!$stmt->fetch()) {
    http_response_code(404);
    exit;
}

$cuit = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['tenant_cuit']);
$safeCode = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $codigo);
$safeCode = $slot === 1 ? $safeCode : "{$safeCode}_{$slot}";
$dir = __DIR__ . "/img/productos/$cuit";

$mimeByExt = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

foreach ($mimeByExt as $ext => $mime) {
    $path = "$dir/$safeCode.$ext";
    if (is_file($path)) {
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=86400');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

// Fallback: foto legacy guardada como BLOB (formato viejo del sistema VB.NET).
// Solo existe una por producto, así que solo aplica al slot de portada.
$row = null;
if ($slot === 1) {
    $stmtBlob = $pdo->prepare("SELECT imagen FROM fotosproductos WHERE codigo = ? ORDER BY id DESC LIMIT 1");
    $stmtBlob->execute([$codigo]);
    $row = $stmtBlob->fetch(PDO::FETCH_ASSOC);
}

if ($row && !empty($row['imagen'])) {
    $mime = 'image/jpeg';
    if (function_exists('finfo_open')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detected = $finfo->buffer($row['imagen']);
        if ($detected) $mime = $detected;
    }
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=86400');
    echo $row['imagen'];
    exit;
}

http_response_code(404);
