<?php
/**
 * api_productos_foto.php - Subida/borrado de la foto de un producto.
 *
 * Arquitectura multi-tenant en un solo dominio/webroot: las fotos NO se
 * guardan en la base de datos (el sistema de escritorio VB.NET las guardaba
 * como BLOB en `fotosproductos`, algo que se descartó a propósito acá) ni
 * sueltas en una carpeta compartida entre clientes. Se guardan en disco,
 * separadas por CUIT: img/productos/{cuit}/{codigo}.jpg — esa carpeta tiene
 * un .htaccess que bloquea el acceso HTTP directo; solo se sirven a través
 * de img_producto.php, que valida sesión + tenant antes de leer el archivo.
 *
 * Toda imagen subida se reprocesa con GD y se guarda como JPEG (evita
 * acumular archivos gigantes de cámaras/celulares y unifica el formato).
 */
require_once 'check_api_access.php';

const MAX_BYTES = 6 * 1024 * 1024; // 6MB de entrada; el JPEG re-codificado queda mucho más chico
const MAX_DIM = 1000;              // lado máximo del lado más largo, en píxeles
const JPEG_QUALITY = 82;

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

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? ($method === 'POST' && isset($_FILES['foto']) ? 'upload' : ($_POST['action'] ?? ''));

    if ($method === 'POST' && $action === 'upload') {
        $codigo = trim($_POST['codigo'] ?? '');
        if ($codigo === '') throw new Exception('Falta el código de producto.');

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

        $fileName = safeFileName($codigo) . '.jpg';
        $destPath = "$dir/$fileName";

        // Limpiar variantes previas en otro formato (si alguna vez se guardó png/webp).
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $old = "$dir/" . safeFileName($codigo) . ".$ext";
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
            'url' => 'img_producto.php?codigo=' . urlencode($codigo) . '&v=' . time()
        ]);
        exit;
    }

    if ($method === 'POST' && $action === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
        $codigo = trim($input['codigo'] ?? '');
        if ($codigo === '') throw new Exception('Falta el código de producto.');

        $dir = tenantDir();
        $borrado = false;
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            $path = "$dir/" . safeFileName($codigo) . ".$ext";
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
