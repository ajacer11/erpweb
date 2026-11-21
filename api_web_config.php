<?php
/**
 * api_web_config.php - Backend de "Administrador > Web de Clientes > Configuración de la Web".
 *
 * La configuración vive en la base de la empresa (web_config, clave/valor; ver web_helpers.php).
 * Las imágenes (logo, banners, quiénes somos) van a img/web/{cuit}/, que no es accesible por
 * HTTP directo: la web las sirve a través de su API (web_catalogo/api) y el ERP con
 * ?action=imagen.
 */
require_once 'check_api_access.php';
require_once 'web_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$cuit = (string) $_SESSION['tenant_cuit'];
$dbId = (int) ($_SESSION['tenant_db_id'] ?? 0);

const WEB_IMG_TIPOS = [
    // tipo => [lado máximo, formato]
    'logo'     => [600, 'png'],
    'favicon'  => [256, 'png'],
    'banner'   => [1920, 'jpg'],
    'quienes'  => [1400, 'jpg'],
];

/** Reprocesa la imagen subida con GD (achica, quita metadatos) y la guarda. Devuelve el nombre de archivo. */
function guardarImagenWeb(array $file, string $tipo, string $dir): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new Exception('No se recibió la imagen.');
    if ($file['size'] > 8 * 1024 * 1024) throw new Exception('La imagen supera los 8 MB.');
    [$maxLado, $formato] = WEB_IMG_TIPOS[$tipo];

    $info = @getimagesize($file['tmp_name']);
    if (!$info) throw new Exception('El archivo no es una imagen válida.');
    $src = match ($info[2]) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($file['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($file['tmp_name']),
        IMAGETYPE_WEBP => @imagecreatefromwebp($file['tmp_name']),
        IMAGETYPE_GIF  => @imagecreatefromgif($file['tmp_name']),
        default => false,
    };
    if (!$src) throw new Exception('Formato no soportado. Usá JPG, PNG o WEBP.');

    [$w, $h] = [imagesx($src), imagesy($src)];
    $escala = min(1, $maxLado / max($w, $h));
    [$nw, $nh] = [max(1, (int) round($w * $escala)), max(1, (int) round($h * $escala))];
    $dst = imagecreatetruecolor($nw, $nh);
    if ($formato === 'png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

    webAsegurarDir($dir);
    $nombre = $tipo . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $formato;
    $ok = $formato === 'png' ? imagepng($dst, "$dir/$nombre", 7) : imagejpeg($dst, "$dir/$nombre", 84);
    imagedestroy($src);
    imagedestroy($dst);
    if (!$ok) throw new Exception('No se pudo guardar la imagen.');
    return $nombre;
}

/** Borra las imágenes de la carpeta que ya no usa ninguna configuración. */
function limpiarImagenesHuerfanas(PDO $pdo, string $dir): void
{
    if (!is_dir($dir)) return;
    $cfg = webConfigLeer($pdo);
    $usadas = [$cfg['logo'], $cfg['favicon'], $cfg['quienes_somos_imagen']];
    foreach (json_decode($cfg['banners'], true) ?: [] as $b) $usadas[] = $b['imagen'] ?? '';
    foreach (glob("$dir/*.{jpg,png}", GLOB_BRACE) as $f) {
        // Solo las que tienen más de un día: una recién subida puede no estar guardada todavía.
        if (!in_array(basename($f), $usadas, true) && filemtime($f) < time() - 86400) @unlink($f);
    }
}

try {
    if (!webAddonHabilitado($pdo_master, $cuit)) {
        throw new Exception('La web de clientes no está contratada para esta empresa.');
    }

    switch ($action) {
        case 'get_config':
            $cfg = webConfigLeer($pdo);
            foreach (WEB_CONFIG_SECRETAS as $s) {
                $cfg[$s . '_cargada'] = $cfg[$s] !== '' ? '1' : '0';
                $cfg[$s] = '';
            }
            $listas = $pdo->query("SELECT ID AS id, descripcion FROM Listas ORDER BY ID")->fetchAll(PDO::FETCH_ASSOC);
            $rubros = $pdo->query("SELECT DISTINCT TRIM(REPLACE(REPLACE(rubro, CHAR(13), ''), CHAR(10), '')) AS r FROM alb_productos WHERE rubro IS NOT NULL AND TRIM(REPLACE(REPLACE(rubro, CHAR(13), ''), CHAR(10), '')) <> '' AND habilitado = 1 ORDER BY r")->fetchAll(PDO::FETCH_COLUMN);
            $familias = $pdo->query("SELECT DISTINCT f.nombre FROM familias f JOIN alb_productos p ON p.familia = f.id AND p.habilitado = 1 ORDER BY f.nombre")->fetchAll(PDO::FETCH_COLUMN);
            $destacados = $pdo->query("SELECT d.codigo, p.descripcion FROM web_destacados d LEFT JOIN alb_productos p ON p.codigo = d.codigo ORDER BY d.orden, d.codigo")->fetchAll(PDO::FETCH_ASSOC);
            $dominio = webDominioDeEmpresa($pdo_master, $cuit, $dbId);
            $mailSistema = webConfigMailWestit();
            echo json_encode([
                'success' => true,
                'data' => $cfg,
                'listas' => $listas,
                'rubros' => $rubros,
                'familias' => $familias,
                'destacados' => $destacados,
                'url_web' => webUrlBase($dominio),
                'mail_sistema_ok' => !empty($mailSistema['smtp_host']),
            ]);
            break;

        case 'save_config':
            $in = json_decode(file_get_contents('php://input'), true);
            if (!is_array($in)) throw new Exception('Datos inválidos.');

            // Validaciones puntuales
            foreach (['color_primario', 'color_secundario', 'color_encabezado'] as $c) {
                if (isset($in[$c]) && !preg_match('/^#[0-9a-fA-F]{6}$/', $in[$c])) throw new Exception("Color inválido: $c");
            }
            if (isset($in['contacto_whatsapp'])) $in['contacto_whatsapp'] = preg_replace('/\D/', '', $in['contacto_whatsapp']);
            if (isset($in['contacto_email']) && $in['contacto_email'] !== '' && !filter_var($in['contacto_email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('El mail de contacto no es válido.');
            }
            if (isset($in['pedido_mail_aviso'])) {
                foreach (array_filter(array_map('trim', preg_split('/[,;]+/', $in['pedido_mail_aviso']))) as $m) {
                    if (!filter_var($m, FILTER_VALIDATE_EMAIL)) throw new Exception("Mail de aviso de pedidos inválido: $m");
                }
            }
            if (isset($in['lista_publica'])) $in['lista_publica'] = (string) max(1, min(8, (int) $in['lista_publica']));
            if (isset($in['pedido_monto_minimo'])) $in['pedido_monto_minimo'] = (string) max(0, (float) str_replace(',', '.', $in['pedido_monto_minimo']));
            if (isset($in['novedades_cantidad'])) $in['novedades_cantidad'] = (string) max(0, min(200, (int) $in['novedades_cantidad']));
            if (isset($in['animaciones']) && !in_array($in['animaciones'], ['completas', 'suaves', 'ninguna'], true)) $in['animaciones'] = 'completas';
            if (isset($in['encabezado_estilo']) && !in_array($in['encabezado_estilo'], ['claro', 'oscuro'], true)) $in['encabezado_estilo'] = 'claro';
            if (isset($in['beneficios'])) {
                $in['beneficios'] = array_slice(array_values(array_filter(array_map(fn($b) => [
                    'icono' => preg_replace('/[^a-z]/', '', (string) ($b['icono'] ?? 'check')),
                    'titulo' => mb_substr(trim((string) ($b['titulo'] ?? '')), 0, 60),
                    'texto' => mb_substr(trim((string) ($b['texto'] ?? '')), 0, 140),
                ], (array) $in['beneficios']), fn($b) => $b['titulo'] !== '')), 0, 4);
            }
            if (isset($in['portada_secciones'])) {
                $validas = ['beneficios', 'rubros', 'destacados', 'novedades', 'franja', 'nosotros', 'contacto'];
                $in['portada_secciones'] = array_values(array_intersect(array_unique((array) $in['portada_secciones']), $validas));
            }
            if (isset($in['rubros_iconos'])) {
                $mapa = [];
                foreach ((array) $in['rubros_iconos'] as $rubro => $icono) {
                    $icono = preg_replace('/[^a-z]/', '', (string) $icono);
                    if ($icono !== '' && $icono !== 'auto') $mapa[trim((string) $rubro)] = $icono;
                }
                $in['rubros_iconos'] = json_encode((object) $mapa, JSON_UNESCAPED_UNICODE);
            }
            foreach (['banners', 'rubros_ocultos', 'beneficios', 'portada_secciones'] as $j) {
                if (isset($in[$j])) {
                    if (!is_array($in[$j])) throw new Exception("Formato inválido: $j");
                    $in[$j] = json_encode(array_values($in[$j]), JSON_UNESCAPED_UNICODE);
                }
            }
            // La clave SMTP solo se reemplaza si escribieron una nueva.
            if (isset($in['smtp_clave'])) {
                if ($in['smtp_clave'] === '') unset($in['smtp_clave']);
                else $in['smtp_clave'] = webCifrar($in['smtp_clave']);
            }
            foreach ($in as $k => $v) {
                if (is_bool($v)) $in[$k] = $v ? '1' : '0';
                elseif (!is_scalar($v)) unset($in[$k]);
            }

            $pdo->beginTransaction();
            webConfigGuardar($pdo, $in, $usuario);
            $pdo->commit();
            limpiarImagenesHuerfanas($pdo, webDirImagenes($cuit));
            echo json_encode(['success' => true, 'message' => 'Configuración guardada.']);
            break;

        case 'upload_imagen':
            $tipo = $_POST['tipo'] ?? '';
            if (!isset(WEB_IMG_TIPOS[$tipo])) throw new Exception('Tipo de imagen inválido.');
            $nombre = guardarImagenWeb($_FILES['imagen'] ?? [], $tipo, webDirImagenes($cuit));
            echo json_encode(['success' => true, 'archivo' => $nombre]);
            break;

        case 'imagen':
            // Vista previa en el ERP (la carpeta no se sirve por HTTP directo).
            $nombre = basename($_GET['f'] ?? '');
            $ruta = webDirImagenes($cuit) . '/' . $nombre;
            if ($nombre === '' || !preg_match('/^[a-z]+_[0-9_a-f]+\.(jpg|png)$/', $nombre) || !is_file($ruta)) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: ' . (str_ends_with($nombre, '.png') ? 'image/png' : 'image/jpeg'));
            header('Cache-Control: private, max-age=86400');
            readfile($ruta);
            exit;

        case 'buscar_productos':
            $q = trim($_GET['q'] ?? '');
            $st = $pdo->prepare("SELECT codigo AS id, CONCAT('[', codigo, '] ', descripcion) AS text FROM alb_productos
                                 WHERE habilitado = 1 AND mostrarencatalogo = 1 AND (codigo LIKE ? OR descripcion LIKE ?) ORDER BY descripcion LIMIT 30");
            $st->execute(["%$q%", "%$q%"]);
            echo json_encode(['results' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save_destacados':
            $codigos = json_decode(file_get_contents('php://input'), true)['codigos'] ?? null;
            if (!is_array($codigos)) throw new Exception('Datos inválidos.');
            $pdo->beginTransaction();
            $pdo->exec("DELETE FROM web_destacados");
            $st = $pdo->prepare("INSERT IGNORE INTO web_destacados (codigo, orden) VALUES (?, ?)");
            foreach (array_values($codigos) as $i => $c) $st->execute([(string) $c, $i]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'probar_mail':
            $para = trim($_POST['para'] ?? '');
            if (!filter_var($para, FILTER_VALIDATE_EMAIL)) throw new Exception('Ingresá un mail válido para la prueba.');
            [$ok, $err] = webEnviarMail($pdo, $para, 'Prueba de correo de la web',
                '<p>Este es un mail de prueba enviado desde la configuración de la web.</p><p>Si lo recibiste, el correo está funcionando.</p>');
            if (!$ok) throw new Exception('No se pudo enviar: ' . $err);
            echo json_encode(['success' => true, 'message' => "Mail de prueba enviado a $para."]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
