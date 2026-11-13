<?php
/**
 * index.php - Punto de entrada de la API de la web de clientes. Se llega por
 * /api/... (ver public/.htaccess y public/api/index.php).
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/catalogo.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/cuenta.php';

/** Configuración pública del sitio (lo que el front necesita para armarse). */
function hSitio(): array
{
    global $CFG, $pdo;
    $img = fn($f) => $f ? '/api/img/sitio?f=' . rawurlencode($f) : null;
    $banners = array_values(array_filter(array_map(fn($b) => [
        'imagen' => $img($b['imagen'] ?? ''),
        'titulo' => (string) ($b['titulo'] ?? ''),
        'subtitulo' => (string) ($b['subtitulo'] ?? ''),
        'botonTexto' => (string) ($b['boton_texto'] ?? ''),
        'botonLink' => (string) ($b['boton_link'] ?? ''),
    ], json_decode($CFG['banners'] ?: '[]', true) ?: []), fn($b) => $b['imagen']));

    $redes = array_filter([
        'instagram' => $CFG['red_instagram'], 'facebook' => $CFG['red_facebook'],
        'tiktok' => $CFG['red_tiktok'], 'youtube' => $CFG['red_youtube'],
    ], fn($u) => preg_match('#^https?://#i', (string) $u));

    $cliente = clienteActual();
    return [
        'nombre' => webNombreEmpresa($pdo, $CFG),
        'slogan' => $CFG['sitio_slogan'],
        'descripcion' => $CFG['sitio_descripcion'],
        'logo' => $img($CFG['logo']),
        'favicon' => $img($CFG['favicon']),
        'banners' => $banners,
        'quienesSomos' => [
            'titulo' => $CFG['quienes_somos_titulo'],
            'texto' => $CFG['quienes_somos_texto'],
            'imagen' => $img($CFG['quienes_somos_imagen']),
        ],
        'contacto' => [
            'telefono' => $CFG['contacto_telefono'],
            'whatsapp' => preg_replace('/\D/', '', $CFG['contacto_whatsapp']),
            'email' => $CFG['contacto_email'],
            'direccion' => $CFG['contacto_direccion'],
            'localidad' => $CFG['contacto_localidad'],
            'horarios' => $CFG['contacto_horarios'],
            'mapa' => preg_match('#^https?://#i', $CFG['contacto_mapa']) ? $CFG['contacto_mapa'] : '',
            'whatsappFlotante' => $CFG['whatsapp_flotante'] === '1',
        ],
        'redes' => $redes,
        'colores' => [
            'primario' => $CFG['color_primario'],
            'secundario' => $CFG['color_secundario'],
            'encabezado' => $CFG['color_encabezado'],
        ],
        'catalogo' => [
            'publico' => $CFG['catalogo_publico'] === '1',
            'preciosPublicos' => $CFG['precios_publicos'] === '1',
            'precioConIva' => $CFG['precio_mostrar_iva'] === '1',
            'mostrarStock' => $CFG['mostrar_stock'],
        ],
        'pedidos' => [
            'habilitados' => $CFG['pedidos_habilitados'] === '1',
            'montoMinimo' => (float) $CFG['pedido_monto_minimo'],
        ],
        'registroHabilitado' => $CFG['registro_habilitado'] === '1',
        'avisosPagoHabilitados' => $CFG['avisos_pago_habilitados'] === '1',
        'diseno' => [
            'animaciones' => $CFG['animaciones'],
            'encabezado' => $CFG['encabezado_estilo'],
            'anuncio' => $CFG['anuncio_texto'],
            'heroBuscador' => $CFG['hero_buscador'] === '1',
            'heroOlas' => $CFG['hero_olas'] === '1',
            'heroPalabras' => array_values(array_filter(array_map('trim', explode(',', $CFG['hero_palabras'])), 'strlen')),
            'contadores' => $CFG['mostrar_contadores'] === '1',
            'beneficios' => json_decode($CFG['beneficios'] ?: '[]', true) ?: [],
            'secciones' => json_decode($CFG['portada_secciones'] ?: '[]', true) ?: [],
            'rubrosIconos' => (object) (json_decode($CFG['rubros_iconos'] ?: '{}', true) ?: []),
            'carritoSinLogin' => $CFG['carrito_sin_login'] === '1',
            'vistaRapida' => $CFG['vista_rapida'] === '1',
        ],
        'cliente' => $cliente ? ['razon' => $cliente['razon'], 'usuario' => $cliente['usuario']] : null,
    ];
}

/** Manifest de la app (para "Agregar a pantalla de inicio" en el celular), con el nombre y el ícono de la empresa. */
function hManifest(): never
{
    global $CFG, $pdo;
    $nombre = webNombreEmpresa($pdo, $CFG);
    $icono = $CFG['favicon'] ?: $CFG['logo'];
    $m = [
        'name' => $nombre,
        'short_name' => mb_substr($nombre, 0, 20),
        'description' => $CFG['sitio_descripcion'] ?: $CFG['sitio_slogan'],
        'lang' => 'es-AR',
        'start_url' => '/',
        'scope' => '/',
        'display' => 'standalone',
        'background_color' => '#f8fafc',
        'theme_color' => $CFG['color_encabezado'],
        'icons' => $icono ? [
            ['src' => '/api/img/sitio?f=' . rawurlencode($icono), 'sizes' => '192x192', 'type' => str_ends_with($icono, '.png') ? 'image/png' : 'image/jpeg', 'purpose' => 'any'],
            ['src' => '/api/img/sitio?f=' . rawurlencode($icono), 'sizes' => '512x512', 'type' => str_ends_with($icono, '.png') ? 'image/png' : 'image/jpeg', 'purpose' => 'any'],
        ] : [],
    ];
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo json_encode($m, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ruta = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
$ruta = preg_replace('#^.*?/api(?=/|$)#', '', $ruta) ?: '/';

$rutas = [
    'GET /sitio' => 'hSitio',
    'GET /manifest' => 'hManifest',
    'GET /inicio' => 'hInicio',
    'GET /productos' => 'hProductos',
    'GET /productos/imprimir' => 'hProductosImprimir',
    'GET /productos/detalle' => 'hProducto',
    'GET /filtros' => 'hFiltros',
    'POST /carrito/precios' => 'hPreciosCarrito',
    'GET /img/producto' => 'hImagenProducto',
    'GET /img/sitio' => 'hImagenSitio',

    'GET /auth/sesion' => 'hSesion',
    'POST /auth/login' => 'hLogin',
    'POST /auth/logout' => 'hLogout',
    'GET /auth/token' => 'hValidarToken',
    'POST /auth/activar' => 'hActivar',
    'POST /auth/recuperar' => 'hRecuperar',
    'POST /auth/registro' => 'hRegistro',
    'POST /auth/clave' => 'hCambiarClave',

    'GET /cuenta/resumen' => 'hResumen',
    'GET /cuenta/movimientos' => 'hMovimientos',
    'GET /cuenta/comprobantes' => 'hComprobantes',
    'GET /cuenta/comprobante' => 'hVerComprobante',
    'GET /cuenta/pedidos' => 'hPedidos',
    'GET /cuenta/pedido' => 'hPedido',
    'POST /cuenta/pedidos' => 'hEnviarPedido',
    'GET /cuenta/pagos' => 'hAvisosPago',
    'POST /cuenta/pagos' => 'hInformarPago',
];

try {
    // CSRF: todo POST tiene que venir del propio front (header que un form de otro sitio no puede mandar).
    if ($metodo === 'POST' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'XMLHttpRequest') {
        throw new ApiError('Solicitud inválida.', 403);
    }
    $handler = $rutas["$metodo $ruta"] ?? null;
    if (!$handler) throw new ApiError('No encontrado.', 404);
    $resultado = $handler();
    session_write_close();
    responder($resultado);
} catch (ApiError $e) {
    responder(['error' => $e->getMessage(), 'codigo' => $e->codigo], $e->status);
} catch (Throwable $e) {
    error_log('web_catalogo ' . $metodo . ' ' . $ruta . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    responder(['error' => 'Ocurrió un error. Probá de nuevo en unos minutos.'], 500);
}
