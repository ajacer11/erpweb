<?php
/**
 * api_productos_foto.php - Subida/borrado de fotos de un producto (hasta 5).
 *
 * Arquitectura multi-tenant en un solo dominio/webroot: las fotos NO se
 * guardan en la base de datos (el sistema de escritorio VB.NET las guardaba
 * como BLOB en `fotosproductos`, algo que se descartó a propósito acá) ni
 * sueltas en una carpeta compartida entre clientes. Se guardan en disco,
 * separadas por CUIT: img/productos/{cuit}/{codigo}[_{slot}].jpg — esa
 * carpeta tiene un .htaccess que bloquea el acceso HTTP directo; solo se
 * sirven a través de img_producto.php (con sesión) o img_producto_publico.php
 * (con token), que validan tenant antes de leer el archivo.
 *
 * Slots: 1 es la foto de portada y usa el nombre histórico sin sufijo
 * ({codigo}.jpg), por compatibilidad con lo que ya existía. Los slots 2 a
 * MAX_SLOTS son imágenes adicionales y usan sufijo ({codigo}_2.jpg, etc.).
 *
 * Toda imagen subida se reprocesa con GD y se guarda como JPEG (evita
 * acumular archivos gigantes de cámaras/celulares y unifica el formato).
 */
require_once 'check_api_access.php';

const MAX_BYTES = 6 * 1024 * 1024; // 6MB de entrada; el JPEG re-codificado queda mucho más chico
const MAX_DIM = 1000;              // lado máximo del lado más largo, en píxeles
const JPEG_QUALITY = 82;
const MAX_SLOTS = 5;

function tenantDir(): string {
    $cuit = preg_replace('/[^a-zA-Z0-9]/', '', $_SESSION['tenant_cuit'] ?? '');
    if ($cuit === '') throw new Exception('Sesión de empresa inválida.');
    return __DIR__ . "/img/productos/$cuit";
}

function safeFileName(string $codigo): string {
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $codigo);
    if ($safe === '') throw new Exception('Código de producto inválido.');
    return $safe;
}

function slotValido($slot): int {
    $slot = (int)$slot;
    if ($slot < 1 || $slot > MAX_SLOTS) {
        throw new Exception('Posición de imagen inválida (debe ser entre 1 y ' . MAX_SLOTS . ').');
    }
    return $slot;
}

function nombreBase(string $codigo, int $slot): string {
    $safe = safeFileName($codigo);
    return $slot === 1 ? $safe : "{$safe}_{$slot}";
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($method === 'POST' && isset($_FILES['foto']) ? 'upload' : ($_POST['action'] ?? ''));

    if ($method === 'POST' && $action === 'upload') {
        $codigo = trim($_POST['codigo'] ?? '');
        if ($codigo === '') throw new Exception('Falta el código de producto.');
        $slot = slotValido($_POST['slot'] ?? 1);

        // El producto debe existir realmente en la base del tenant conectado.
        $stmt = $pdo->prepare("SELECT codigo FROM alb_productos WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);
        if (!$stmt->fetch()) throw new Exception("El producto [$codigo] no existe.");

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No se recibió ningún archivo válido.');
        }
        if ($_FILES['foto']['size'] > MAX_BYTES) {
            throw new Exception('La imagen supera el tamaño máximo permitido (6MB).');
        }

        $tmpPath = $_FILES['foto']['tmp_name'];
        $info = @getimagesize($tmpPath);
        if (!$info) throw new Exception('El archivo no es una imagen válida.');

        [$width, $height, $type] = $info;

        switch ($type) {
            case IMAGETYPE_JPEG: $srcImg = @imagecreatefromjpeg($tmpPath); break;
            case IMAGETYPE_PNG:  $srcImg = @imagecreatefrompng($tmpPath); break;
            case IMAGETYPE_WEBP: $srcImg = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmpPath) : false; break;
            case IMAGETYPE_GIF:  $srcImg = @imagecreatefromgif($tmpPath); break;
            default: $srcImg = false;
        }
        if (!$srcImg) throw new Exception('Formato de imagen no soportado (use JPG, PNG, WEBP o GIF).');

        // Redimensionar si excede el máximo, manteniendo proporción.
        $scale = min(1, MAX_DIM / max($width, $height));
        $newW = (int) round($width * $scale);
        $newH = (int) round($height * $scale);

        $outImg = imagecreatetruecolor($newW, $newH);
        // Fondo blanco para que los PNG con transparencia no queden negros al pasar a JPEG.
        $white = imagecolorallocate($outImg, 255, 255, 255);
        imagefill($outImg, 0, 0, $white);
        imagecopyresampled($outImg, $srcImg, 0, 0, 0, 0, $newW, $newH, $width, $height);
        imagedestroy($srcImg);

        $dir = tenantDir();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new Exception('No se pudo crear la carpeta de imágenes del tenant.');
        }

        $base = nombreBase($codigo, $slot);
        $fileName = "$base.jpg";
        $destPath = "$dir/$fileName";

        // Limpiar variantes previas en otro formato de ese mismo slot (si alguna vez se guardó png/webp).
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $old = "$dir/$base.$ext";
            if (is_file($old)) @unlink($old);
        }

        if (!imagejpeg($outImg, $destPath, JPEG_QUALITY)) {
            imagedestroy($outImg);
            throw new Exception('No se pudo guardar la imagen procesada.');
        }
        imagedestroy($outImg);

        echo json_encode([
            'success' => true,
            'message' => 'Foto guardada correctamente.',
            'slot' => $slot,
            'url' => 'img_producto.php?codigo=' . urlencode($codigo) . '&slot=' . $slot . '&v=' . time()
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $codigo = trim($input['codigo'] ?? '');
        if ($codigo === '') throw new Exception('Falta el código de producto.');
        $slot = slotValido($input['slot'] ?? 1);

        $dir = tenantDir();
        $base = nombreBase($codigo, $slot);
        $borrado = false;
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = "$dir/$base.$ext";
            if (is_file($path)) { @unlink($path); $borrado = true; }
        }

        echo json_encode(['success' => true, 'borrado' => $borrado]);
        exit;
    }

    throw new Exception('Acción no válida.');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
