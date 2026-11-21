<?php
// Uso: php normalizar_foto.php <origen> <destino.jpg>
// Mismo criterio que api_productos_foto.php: lado mayor <= 1000 px, JPEG calidad 82, fondo blanco.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
[$_, $src, $dst] = $argv + [null, null, null];
$info = @getimagesize($src);
switch ($info[2] ?? 0) {
    case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($src); break;
    case IMAGETYPE_PNG:  $img = @imagecreatefrompng($src); break;
    case IMAGETYPE_WEBP: $img = @imagecreatefromwebp($src); break;
    case IMAGETYPE_GIF:  $img = @imagecreatefromgif($src); break;
    default: $img = false;
}
if (!$img) { fwrite(STDOUT, "no se pudo leer " . basename($src) . "\n"); exit(1); }
$w = imagesx($img); $h = imagesy($img);
$k = min(1, 1000 / max($w, $h));
$nw = max(1, (int) round($w * $k)); $nh = max(1, (int) round($h * $k));
$out = imagecreatetruecolor($nw, $nh);
imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
$tmp = $dst . '.tmp';
if (!imagejpeg($out, $tmp, 82) || !rename($tmp, $dst)) { @unlink($tmp); echo "no se pudo grabar $dst\n"; exit(1); }
chmod($dst, 0644);
