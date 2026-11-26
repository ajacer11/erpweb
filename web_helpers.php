<?php
/**
 * web_helpers.php - Funciones compartidas de la WEB DE CLIENTES (addon 'web_catalogo').
 *
 * Lo usan las pantallas del ERP (Configuración > Web, accesos, avisos de pago,
 * características) y la API pública de la web (web_catalogo/api). No depende de
 * sesión: recibe los PDO que necesita.
 *
 * Mails: por defecto salen de la casilla de WEST IT (config_mail.php, gitignoreado,
 * ver config_mail.example.php) con el nombre de la empresa y "responder a" el mail
 * de la empresa. Si la empresa cargó su propio SMTP en Configuración > Web > Correo,
 * se usa ese. La clave SMTP de la empresa se guarda cifrada con la clave de
 * config_mail.php ('clave_cifrado').
 */

const WEB_ADDON_CATALOGO = 'web_catalogo';

/** Valores por defecto de toda la configuración de la web. Cualquier clave fuera de esta lista se ignora al guardar. */
const WEB_CONFIG_DEFAULTS = [
    // --- Sitio / identidad
    'sitio_nombre'          => '',
    'sitio_slogan'          => '',
    'sitio_descripcion'     => '',      // meta description (buscadores / al compartir el link)
    'quienes_somos_titulo'  => 'Quiénes somos',
    'quienes_somos_texto'   => '',
    'logo'                  => '',      // archivo en img/web/{cuit}/
    'favicon'               => '',
    'banners'               => '[]',    // JSON: [{imagen, titulo, subtitulo, boton_texto, boton_link}]
    'quienes_somos_imagen'  => '',
    // --- Contacto
    'contacto_telefono'     => '',
    'contacto_whatsapp'     => '',      // solo números, con código de país (5491112345678)
    'contacto_email'        => '',
    'contacto_direccion'    => '',
    'contacto_localidad'    => '',
    'contacto_horarios'     => '',
    'contacto_mapa'         => '',      // link de Google Maps
    'red_instagram'         => '',
    'red_facebook'          => '',
    'red_tiktok'            => '',
    'red_youtube'           => '',
    'whatsapp_flotante'     => '1',
    // --- Apariencia
    'color_primario'        => '#0b4f8a',
    'color_secundario'      => '#f59e0b',
    'color_encabezado'      => '#0f172a',
    // --- Diseño y portada
    'animaciones'           => 'completas', // completas | suaves | ninguna
    'encabezado_estilo'     => 'claro',     // claro (blanco, logo a color) | oscuro (color de encabezado)
    'anuncio_texto'         => '',          // barra fina arriba de todo (ej. "Envíos a todo el país")
    'hero_buscador'         => '1',         // buscador grande sobre el banner
    'hero_palabras'         => '',          // palabras que rotan en el título, separadas por coma (vacío = rubros)
    'hero_olas'             => '1',         // olas animadas al pie del banner
    'mostrar_contadores'    => '1',         // productos / rubros / novedades con números animados
    'beneficios'            => '[]',        // JSON: [{icono, titulo, texto}] (hasta 4)
    'portada_secciones'     => '["beneficios","rubros","destacados","novedades","franja","nosotros","contacto"]',
    'rubros_iconos'         => '{}',        // JSON: {"Rubro": "cana"} (sin clave = automático)
    'carrito_sin_login'     => '1',         // armar el pedido sin ingresar (para enviarlo sí se pide)
    'vista_rapida'          => '1',         // ver el producto y agregarlo sin salir del catálogo
    // --- Catálogo
    'catalogo_publico'      => '1',     // 0 = el catálogo solo se ve logueado
    'precios_publicos'      => '1',     // muestra precios sin login
    'lista_publica'         => '1',     // lista de precios que ve el público
    'precio_mostrar_iva'    => '1',     // 1 = precio final con IVA, 0 = neto + IVA aparte
    'mostrar_stock'         => 'disponibilidad', // ninguno | disponibilidad | cantidad
    'rubros_ocultos'        => '[]',    // JSON: rubros (texto) que no se muestran
    'novedades_cantidad'    => '20',    // los últimos N productos dados de alta se marcan como "Novedad"
    'catalogo_orden'        => 'familia', // familia | descripcion | codigo
    // --- Pedidos
    'pedidos_habilitados'   => '1',
    'pedido_monto_minimo'   => '0',
    'pedido_mail_aviso'     => '',      // destinatarios del aviso de pedido (separados por coma)
    'pedido_avisar_vendedor'=> '1',
    'pedido_confirmar_cliente' => '1',  // mail de confirmación al cliente
    'pedido_texto_confirmacion' => 'Recibimos tu pedido. Te vamos a contactar para coordinar la entrega.',
    // --- Área de clientes
    'registro_habilitado'   => '1',     // formulario "Quiero ser cliente"
    'avisos_pago_habilitados' => '1',
    'datos_pago'            => '',      // texto con CBU/alias para transferencias
    // --- Correo propio de la empresa (opcional)
    'smtp_activo'           => '0',
    'smtp_host'             => '',
    'smtp_puerto'           => '587',
    'smtp_seguridad'        => 'tls',   // tls | ssl | none
    'smtp_usuario'          => '',
    'smtp_clave'            => '',      // cifrada
    'smtp_remitente'        => '',
    'smtp_remitente_nombre' => '',
];

/** Claves que nunca se devuelven tal cual al navegador. */
const WEB_CONFIG_SECRETAS = ['smtp_clave'];

/** ¿La empresa tiene contratado el addon? */
function webAddonHabilitado(PDO $pdo_master, string $cuit, string $addon = WEB_ADDON_CATALOGO): bool
{
    try {
        $st = $pdo_master->prepare("SELECT habilitado FROM tenant_addons WHERE cuit = ? AND addon_key = ? LIMIT 1");
        $st->execute([$cuit, $addon]);
        return (bool) $st->fetchColumn();
    } catch (Throwable $e) {
        error_log('webAddonHabilitado: ' . $e->getMessage());
        return false;
    }
}

/** Addons habilitados de la empresa (lista de claves). */
function webAddonsDeEmpresa(PDO $pdo_master, string $cuit): array
{
    try {
        $st = $pdo_master->prepare("SELECT addon_key FROM tenant_addons WHERE cuit = ? AND habilitado = 1");
        $st->execute([$cuit]);
        return $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/** Configuración completa (defaults + lo guardado). */
function webConfigLeer(PDO $pdo): array
{
    $cfg = WEB_CONFIG_DEFAULTS;
    try {
        foreach ($pdo->query("SELECT clave, valor FROM web_config") as $r) {
            if (array_key_exists($r['clave'], $cfg)) {
                $cfg[$r['clave']] = (string) $r['valor'];
            }
        }
    } catch (Throwable $e) {
        error_log('webConfigLeer: ' . $e->getMessage());
    }
    return $cfg;
}

/** Guarda solo claves conocidas. Devuelve la cantidad de claves guardadas. */
function webConfigGuardar(PDO $pdo, array $valores, string $usuario): int
{
    $st = $pdo->prepare("INSERT INTO web_config (clave, valor, usuario_modificacion) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor), usuario_modificacion = VALUES(usuario_modificacion)");
    $n = 0;
    foreach ($valores as $clave => $valor) {
        if (!array_key_exists($clave, WEB_CONFIG_DEFAULTS)) continue;
        $st->execute([$clave, (string) $valor, $usuario]);
        $n++;
    }
    return $n;
}

/** Carpeta de imágenes de la web de la empresa (logo, banners, quiénes somos). */
function webDirImagenes(string $cuit): string
{
    $cuit = preg_replace('/[^a-zA-Z0-9]/', '', $cuit);
    if ($cuit === '') throw new Exception('Empresa inválida.');
    return __DIR__ . "/img/web/$cuit";
}

/**
 * Crea (si hace falta) una carpeta de archivos de la web y la protege contra acceso HTTP directo:
 * img/web/ guarda comprobantes de pago de los clientes; todo se sirve por PHP validando permisos.
 */
function webAsegurarDir(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new Exception('No se pudo crear la carpeta de archivos.');
    $raiz = __DIR__ . '/img/web';
    if (!is_file("$raiz/.htaccess")) @file_put_contents("$raiz/.htaccess", "Require all denied
");
}

/** Carpeta de comprobantes adjuntos a los avisos de pago. */
function webDirAvisosPago(string $cuit): string
{
    return webDirImagenes($cuit) . '/avisos_pago';
}

/** Carpeta de fotos de productos (la misma que usa el ERP). */
function webDirFotosProductos(string $cuit): string
{
    $cuit = preg_replace('/[^a-zA-Z0-9]/', '', $cuit);
    return __DIR__ . "/img/productos/$cuit";
}

/** Configuración de correo de WEST IT (config_mail.php). null si no existe. */
function webConfigMailWestit(): ?array
{
    static $cfg = false;
    if ($cfg === false) {
        $f = __DIR__ . '/config_mail.php';
        $cfg = is_file($f) ? (require $f) : null;
    }
    return is_array($cfg) ? $cfg : null;
}

function webCifrar(string $texto): string
{
    $clave = webConfigMailWestit()['clave_cifrado'] ?? '';
    if ($clave === '') throw new Exception('Falta configurar la clave de cifrado del servidor (config_mail.php). Avisá a soporte.');
    $iv = random_bytes(12);
    $tag = '';
    $enc = openssl_encrypt($texto, 'aes-256-gcm', hash('sha256', $clave, true), OPENSSL_RAW_DATA, $iv, $tag);
    return base64_encode($iv . $tag . $enc);
}

function webDescifrar(string $cifrado): string
{
    if ($cifrado === '') return '';
    $clave = webConfigMailWestit()['clave_cifrado'] ?? '';
    $raw = base64_decode($cifrado, true);
    if ($clave === '' || $raw === false || strlen($raw) < 29) return '';
    $txt = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $clave, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $txt === false ? '' : $txt;
}

/** Nombre comercial de la empresa para mails / web (config web > datosempresa). */
function webNombreEmpresa(PDO $pdo, ?array $cfg = null): string
{
    $cfg = $cfg ?? webConfigLeer($pdo);
    if (trim($cfg['sitio_nombre']) !== '') return trim($cfg['sitio_nombre']);
    try {
        $r = $pdo->query("SELECT razon FROM datosempresa ORDER BY id DESC LIMIT 1")->fetchColumn();
        if ($r) return (string) $r;
    } catch (Throwable $e) {
    }
    return 'Nuestra empresa';
}

/**
 * Envía un mail. Usa el SMTP propio de la empresa si está activo; si no, el de WEST IT.
 * Devuelve [true, null] o [false, 'motivo'] (nunca lanza: un mail que falla no debe
 * frenar un pedido).
 */
function webEnviarMail(PDO $pdo, $para, string $asunto, string $html, ?array $cfg = null): array
{
    $cfg = $cfg ?? webConfigLeer($pdo);
    $destinos = array_values(array_filter(array_map('trim', is_array($para) ? $para : preg_split('/[,;]+/', (string) $para)),
        fn($m) => filter_var($m, FILTER_VALIDATE_EMAIL)));
    if (!$destinos) return [false, 'Sin destinatarios válidos'];

    require_once __DIR__ . '/vendor/autoload.php';
    $nombreEmpresa = webNombreEmpresa($pdo, $cfg);

    try {
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->CharSet = 'UTF-8';
        $m->isSMTP();
        $m->Timeout = 15;

        if ($cfg['smtp_activo'] === '1' && $cfg['smtp_host'] !== '') {
            $m->Host = $cfg['smtp_host'];
            $m->Port = (int) $cfg['smtp_puerto'];
            $seg = $cfg['smtp_seguridad'];
            $usr = $cfg['smtp_usuario'];
            $pass = webDescifrar($cfg['smtp_clave']);
            $remitente = $cfg['smtp_remitente'] ?: $usr;
            $remitenteNombre = $cfg['smtp_remitente_nombre'] ?: $nombreEmpresa;
        } else {
            $w = webConfigMailWestit();
            if (!$w || empty($w['smtp_host'])) return [false, 'Correo del sistema no configurado'];
            $m->Host = $w['smtp_host'];
            $m->Port = (int) ($w['smtp_puerto'] ?? 587);
            $seg = $w['smtp_seguridad'] ?? 'tls';
            $usr = $w['smtp_usuario'] ?? '';
            $pass = $w['smtp_clave'] ?? '';
            $remitente = $w['remitente'] ?? $usr;
            $remitenteNombre = $nombreEmpresa;
            if (filter_var($cfg['contacto_email'], FILTER_VALIDATE_EMAIL)) {
                $m->addReplyTo($cfg['contacto_email'], $nombreEmpresa);
            }
        }

        if ($seg === 'ssl') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        elseif ($seg === 'tls') $m->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        else { $m->SMTPSecure = ''; $m->SMTPAutoTLS = false; }
        if ($usr !== '') {
            $m->SMTPAuth = true;
            $m->Username = $usr;
            $m->Password = $pass;
        }
        $m->setFrom($remitente, $remitenteNombre);
        foreach ($destinos as $d) $m->addAddress($d);
        $m->isHTML(true);
        $m->Subject = $asunto;
        $m->Body = webPlantillaMail($nombreEmpresa, $cfg['color_primario'] ?: '#0b4f8a', $html);
        $m->AltBody = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $html))));
        $m->send();
        return [true, null];
    } catch (Throwable $e) {
        error_log('webEnviarMail: ' . $e->getMessage());
        return [false, $e->getMessage()];
    }
}

function webPlantillaMail(string $empresa, string $color, string $contenido): string
{
    $empresa = htmlspecialchars($empresa);
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', $color) ? $color : '#0b4f8a';
    return <<<HTML
<div style="background:#f1f5f9;padding:24px 12px;font-family:Arial,Helvetica,sans-serif">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;overflow:hidden">
    <div style="background:$color;color:#fff;padding:18px 24px;font-size:18px;font-weight:bold">$empresa</div>
    <div style="padding:24px;color:#1e293b;font-size:15px;line-height:1.55">$contenido</div>
  </div>
</div>
HTML;
}

/** Botón para mails. */
function webMailBoton(string $url, string $texto, string $color = '#0b4f8a'): string
{
    $url = htmlspecialchars($url);
    $texto = htmlspecialchars($texto);
    return "<p style=\"margin:24px 0\"><a href=\"$url\" style=\"background:$color;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block\">$texto</a></p>";
}

/** Dominio principal (habilitado) de la web de la empresa, o null. */
function webDominioDeEmpresa(PDO $pdo_master, string $cuit, int $dbId): ?string
{
    try {
        $st = $pdo_master->prepare("SELECT dominio FROM web_sitios WHERE cuit = ? AND db_id = ? AND habilitado = 1 ORDER BY id ASC LIMIT 1");
        $st->execute([$cuit, $dbId]);
        $d = $st->fetchColumn();
        return $d ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

/** URL base de la web (https://dominio). Local: localhost con el puerto de Vite. */
function webUrlBase(?string $dominio): ?string
{
    if (!$dominio) return null;
    if ($dominio === 'localhost') return 'http://localhost:5173';
    return 'https://' . $dominio;
}

/**
 * Genera un token de invitación / recuperación para un acceso web. Devuelve el token en claro
 * (va en el link del mail); en la base queda solo su hash.
 */
function webGenerarToken(PDO $pdo, int $webUsuarioId, string $tipo, int $horas): string
{
    $token = bin2hex(random_bytes(32));
    $st = $pdo->prepare("UPDATE web_usuarios SET token_hash = ?, token_tipo = ?, token_vence = DATE_ADD(NOW(), INTERVAL ? HOUR) WHERE id = ?");
    $st->execute([hash('sha256', $token), $tipo, $horas, $webUsuarioId]);
    return $token;
}

/** Mail de invitación al área de clientes. */
function webEnviarInvitacion(PDO $pdo, PDO $pdo_master, string $cuit, int $dbId, int $webUsuarioId): array
{
    $st = $pdo->prepare("SELECT w.id, w.nombre_usuario, w.email, c.razon FROM web_usuarios w LEFT JOIN alb_clientes c ON c.id = w.cliente_id WHERE w.id = ?");
    $st->execute([$webUsuarioId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return [false, 'Acceso no encontrado'];
    if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return [false, 'El acceso no tiene un mail válido'];

    $base = webUrlBase(webDominioDeEmpresa($pdo_master, $cuit, $dbId));
    if (!$base) return [false, 'La web todavía no tiene un dominio asignado. Pedíselo a WEST IT.'];

    $cfg = webConfigLeer($pdo);
    $token = webGenerarToken($pdo, (int) $u['id'], 'invitacion', 72);
    $link = $base . '/cuenta/activar?token=' . $token;
    $empresaTxt = webNombreEmpresa($pdo, $cfg);
    $empresa = htmlspecialchars($empresaTxt);
    $razon = htmlspecialchars((string) $u['razon']);
    $usuario = htmlspecialchars($u['nombre_usuario']);
    $html = "<p>Hola <b>$razon</b>,</p>
        <p>$empresa te habilitó el acceso a su <b>área de clientes</b>: vas a poder ver precios, hacer pedidos y consultar tu cuenta corriente y tus facturas.</p>
        <p>Tu usuario es <b>$usuario</b> (también podés entrar con este mail). Para crear tu contraseña, tocá el botón:</p>"
        . webMailBoton($link, 'Crear mi contraseña', $cfg['color_primario'])
        . "<p style=\"color:#64748b;font-size:13px\">El link vence en 72 horas. Si no lo pediste, ignorá este mail.</p>";
    return webEnviarMail($pdo, $u['email'], "Tu acceso a $empresaTxt", $html, $cfg);
}

/** Avisa al cliente que su solicitud se aprobó y ya puede ingresar con la contraseña que eligió al registrarse. */
function webEnviarAprobacion(PDO $pdo, PDO $pdo_master, string $cuit, int $dbId, int $webUsuarioId): array
{
    $st = $pdo->prepare("SELECT w.nombre_usuario, w.email, c.razon FROM web_usuarios w LEFT JOIN alb_clientes c ON c.id = w.cliente_id WHERE w.id = ?");
    $st->execute([$webUsuarioId]);
    $u = $st->fetch(PDO::FETCH_ASSOC);
    if (!$u) return [false, 'Acceso no encontrado'];
    if (!filter_var($u['email'], FILTER_VALIDATE_EMAIL)) return [false, 'El acceso no tiene un mail válido'];

    $base = webUrlBase(webDominioDeEmpresa($pdo_master, $cuit, $dbId));
    if (!$base) return [false, 'La web todavía no tiene un dominio asignado. Pedíselo a WEST IT.'];

    $cfg = webConfigLeer($pdo);
    $empresaTxt = webNombreEmpresa($pdo, $cfg);
    $empresa = htmlspecialchars($empresaTxt);
    $razon = htmlspecialchars((string) $u['razon']);
    $email = htmlspecialchars($u['email']);
    $usuario = htmlspecialchars($u['nombre_usuario']);
    $html = "<p>Hola <b>$razon</b>,</p>
        <p>¡Tu solicitud fue aprobada! Ya tenés acceso al <b>área de clientes</b> de $empresa: vas a poder ver tus precios, hacer pedidos y consultar tu cuenta corriente y tus facturas.</p>
        <p>Ingresá con tu mail <b>$email</b> (o con el usuario <b>$usuario</b>) y la contraseña que elegiste al registrarte.</p>"
        . webMailBoton($base . '/ingresar', 'Ingresar', $cfg['color_primario'])
        . "<p style=\"color:#64748b;font-size:13px\">¿No te acordás la contraseña? En la pantalla de ingreso tocá \"¿Olvidaste tu contraseña?\".</p>";
    return webEnviarMail($pdo, $u['email'], "Tu acceso a $empresaTxt está listo", $html, $cfg);
}
