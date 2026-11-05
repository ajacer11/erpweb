<?php
/**
 * api_productos_externa.php - API PÚBLICA DE PRODUCTOS (PRECIOS + STOCK)
 *
 * Pensada para que tiendas externas consuman el catálogo de un tenant.
 * No usa sesión: el token identifica al tenant. La configuración (token,
 * listas compartidas, depósito a exponer) se administra desde
 * Administrador > Configurar APIs > Productos (config_apis_externas.php)
 * y vive en la base MAESTRA (api_config_productos_externa), porque hace
 * falta resolver el tenant ANTES de poder conectarse a su base.
 *
 * Acepta GET y POST indistintamente (misma lógica, mismos parámetros):
 *   - GET: parámetros por querystring.
 *   - POST: parámetros por body form-urlencoded/multipart, o JSON
 *     (Content-Type: application/json) con las mismas claves.
 * Si un parámetro llega tanto por querystring como por body, gana el body.
 *
 * Autenticación: header "Authorization: Bearer <token>", o parámetro "token"
 * (por querystring en GET, por body en POST).
 *
 * Parámetros opcionales:
 *   codigo             filtra por código exacto de producto
 *   q                  busca en código o descripción
 *   rubro              filtra por rubro (texto exacto)
 *   marca              filtra por id de marca
 *   familia            filtra por id de familia
 *   actualizado_desde  fecha/hora (YYYY-MM-DD HH:MM:SS), solo productos con
 *                      cambios desde ese momento (sincronización incremental)
 *   pagina             default 1
 *   por_pagina         default 100, máximo 500
 *
 * No se filtran mensajes de excepción hacia el cliente: es un endpoint público.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once 'db_master.php'; // da $pdo_master, getTenantDatabaseConfig(), parseConnectionString()

// -----------------------------------------------------------------------
// 0. Unificar parámetros de GET y POST (form o JSON) en un solo array
// -----------------------------------------------------------------------
$in = $_GET;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $body = json_decode(file_get_contents('php://input'), true);
        if (is_array($body)) $in = array_merge($in, $body);
    } else {
        $in = array_merge($in, $_POST);
    }
}

function respond(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function logIntento(PDO $pdo_master, ?string $token, ?string $cuit, string $resultado, ?string $detalle = null): void {
    try {
        $stmt = $pdo_master->prepare("INSERT INTO log_accesos_api_productos_externa (ip, token_usado, cuit, resultado, detalle) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', $token, $cuit, $resultado, $detalle]);
    } catch (Exception $e) {
        error_log('api_productos_externa.php: fallo al registrar log: ' . $e->getMessage());
    }
}

// -----------------------------------------------------------------------
// 1. Identificar el token
// -----------------------------------------------------------------------
$token = '';
if (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.+)/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
    $token = trim($m[1]);
} elseif (!empty($in['token'])) {
    $token = trim($in['token']);
}

if ($token === '') {
    respond(401, ['success' => false, 'error' => 'Token requerido (header Authorization: Bearer <token> o parámetro ?token=).']);
}

// -----------------------------------------------------------------------
// 2. Resolver configuración por token (base maestra)
// -----------------------------------------------------------------------
try {
    $stmt = $pdo_master->prepare("SELECT * FROM api_config_productos_externa WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $config = $stmt->fetch();
} catch (Exception $e) {
    error_log('api_productos_externa.php: error consultando config: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Error interno.']);
}

if (!$config) {
    logIntento($pdo_master, $token, null, 'token_invalido');
    respond(401, ['success' => false, 'error' => 'Token inválido.']);
}

if (!$config['habilitado']) {
    logIntento($pdo_master, $token, $config['cuit'], 'deshabilitado');
    respond(403, ['success' => false, 'error' => 'La API de productos está deshabilitada para esta empresa.']);
}

// -----------------------------------------------------------------------
// 2.5. Interruptor de WEST IT: el cliente tiene que tener este servicio
// contratado (tabla tenant_addons, controlada por WEST IT), independiente
// de cómo lo haya configurado el propio tenant en su panel.
// -----------------------------------------------------------------------
try {
    $stmtAddon = $pdo_master->prepare("SELECT habilitado FROM tenant_addons WHERE cuit = ? AND addon_key = 'api_productos' LIMIT 1");
    $stmtAddon->execute([$config['cuit']]);
    $addonHabilitado = (bool)$stmtAddon->fetchColumn();
} catch (Exception $e) {
    error_log('api_productos_externa.php: error consultando tenant_addons: ' . $e->getMessage());
    $addonHabilitado = false;
}

if (!$addonHabilitado) {
    logIntento($pdo_master, $token, $config['cuit'], 'no_contratado');
    respond(403, ['success' => false, 'error' => 'Este servicio no está contratado para esta empresa.']);
}

// -----------------------------------------------------------------------
// 3. Conectar a la base del tenant
// -----------------------------------------------------------------------
try {
    $dbConfig = getTenantDatabaseConfig($pdo_master, $config['cuit'], $config['db_id'] ?: null);
    if (!$dbConfig) {
        throw new Exception("Configuración de tenant no encontrada [{$config['cuit']}].");
    }
    $cfg = parseConnectionString($dbConfig['cadenaconec']);
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset=utf8mb4";
    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::ATTR_PERSISTENT         => false,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");
} catch (Exception $e) {
    error_log('api_productos_externa.php: fallo de conexión al tenant: ' . $e->getMessage());
    logIntento($pdo_master, $token, $config['cuit'], 'error', 'Fallo de conexión al tenant');
    respond(503, ['success' => false, 'error' => 'Servicio no disponible en este momento.']);
}

logIntento($pdo_master, $token, $config['cuit'], 'ok');

// -----------------------------------------------------------------------
// 4. Armar el SELECT dinámico (listas compartidas + stock por depósito o consolidado)
// -----------------------------------------------------------------------
$listaIds = array_values(array_filter(array_map('intval', explode(',', $config['listas_compartidas'])), fn($v) => $v >= 1 && $v <= 8));
if (empty($listaIds)) $listaIds = [1];

$listasNombres = [];
try {
    $stmtL = $pdo->prepare("SELECT ID as id, descripcion FROM Listas WHERE ID IN (" . implode(',', array_fill(0, count($listaIds), '?')) . ")");
    $stmtL->execute($listaIds);
    foreach ($stmtL->fetchAll() as $row) {
        $listasNombres[(int)$row['id']] = $row['descripcion'];
    }
} catch (Exception $e) {
    // si falla, se sigue exponiendo con nombre genérico "Lista N"
}

$colsListas = [];
foreach ($listaIds as $idLista) {
    $sufijoNeto = $idLista == 1 ? '' : $idLista;
    $sufijoIva = $idLista == 1 ? '1' : $idLista;
    $colsListas[] = "p.siniva{$sufijoNeto} AS l{$idLista}_neto, p.iva{$sufijoIva} AS l{$idLista}_iva, p.precio{$sufijoNeto} AS l{$idLista}_total";
}
$selectListas = implode(', ', $colsListas);

$idDeposito = !empty($config['id_deposito']) ? (int)$config['id_deposito'] : null;
if ($idDeposito) {
    $joinStock = "LEFT JOIN stock_por_deposito sd ON sd.id_producto = p.id AND sd.id_deposito = " . (int)$idDeposito;
    $selectStock = "COALESCE(sd.cantidad, 0) AS stock";
} else {
    $joinStock = '';
    $selectStock = "p.Stock AS stock";
}

// -----------------------------------------------------------------------
// 5. Filtros y paginación
// -----------------------------------------------------------------------
$where = ["p.habilitado = 1", "p.mostrarencatalogo = 1"];
$params = [];

if (!empty($in['codigo'])) {
    $where[] = "p.codigo = ?";
    $params[] = $in['codigo'];
}
if (!empty($in['q'])) {
    $where[] = "(p.codigo LIKE ? OR p.descripcion LIKE ?)";
    $term = '%' . $in['q'] . '%';
    $params[] = $term;
    $params[] = $term;
}
if (!empty($in['rubro'])) {
    $where[] = "p.rubro = ?";
    $params[] = $in['rubro'];
}
if (!empty($in['marca'])) {
    $where[] = "p.Marca = ?";
    $params[] = (int)$in['marca'];
}
if (!empty($in['familia'])) {
    $where[] = "p.familia = ?";
    $params[] = (int)$in['familia'];
}
if (!empty($in['actualizado_desde'])) {
    $where[] = "p.timestamp >= ?";
    $params[] = $in['actualizado_desde'];
}

$porPagina = min(500, max(1, (int)($in['por_pagina'] ?? 100)));
$pagina = max(1, (int)($in['pagina'] ?? 1));
$offset = ($pagina - 1) * $porPagina;

$whereSql = implode(' AND ', $where);

try {
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM alb_productos p $joinStock WHERE $whereSql");
    $stmtCount->execute($params);
    $total = (int)$stmtCount->fetchColumn();

    $sql = "SELECT p.codigo, p.descripcion, p.descripcion_tienda, p.rubro, p.Marca, p.familia,
                   p.idiva, p.poriva, p.ImpInt, p.stockcomprometido, p.mostrarsinstock, p.controlastock, p.timestamp,
                   m.marca AS nombre_marca, f.nombre AS nombre_familia,
                   $selectStock, $selectListas
            FROM alb_productos p
            LEFT JOIN Marcas m ON m.id = p.Marca
            LEFT JOIN familias f ON f.id = p.familia
            $joinStock
            WHERE $whereSql
            ORDER BY p.codigo ASC
            LIMIT $porPagina OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('api_productos_externa.php: error de consulta: ' . $e->getMessage());
    respond(500, ['success' => false, 'error' => 'Error consultando productos.']);
}

// -----------------------------------------------------------------------
// 6. Resolver imágenes en disco (mismas reglas que img_producto.php)
// -----------------------------------------------------------------------
$cuitSafe = preg_replace('/[^a-zA-Z0-9]/', '', $config['cuit']);
$dirImagenes = __DIR__ . "/img/productos/$cuitSafe";
$extensiones = ['jpg', 'jpeg', 'png', 'webp'];
$baseUrlImg = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/img_producto_publico.php';

const MAX_SLOTS_IMAGENES = 5;

function resolverImagenes(string $dir, string $codigo, array $extensiones, string $baseUrlImg, string $token): array {
    $safeCodeBase = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $codigo);
    $urls = [];
    for ($slot = 1; $slot <= MAX_SLOTS_IMAGENES; $slot++) {
        $safeCode = $slot === 1 ? $safeCodeBase : "{$safeCodeBase}_{$slot}";
        foreach ($extensiones as $ext) {
            if (is_file("$dir/$safeCode.$ext")) {
                $urls[] = $baseUrlImg . '?token=' . urlencode($token) . '&codigo=' . urlencode($codigo) . '&slot=' . $slot;
                break; // img_producto_publico.php ya resuelve la extensión real
            }
        }
    }
    return $urls;
}

// -----------------------------------------------------------------------
// 7. Armar respuesta
// -----------------------------------------------------------------------
$data = [];
foreach ($productos as $p) {
    $stockDisponible = $idDeposito
        ? (float)$p['stock'] // por depósito puntual no se descuenta comprometido (es global, no por depósito)
        : (float)$p['stock'] - (float)$p['stockcomprometido'];

    $listasPrecios = [];
    foreach ($listaIds as $idLista) {
        $listasPrecios[] = [
            'id_lista' => $idLista,
            'nombre' => $listasNombres[$idLista] ?? "Lista $idLista",
            'precio_neto' => (float)$p["l{$idLista}_neto"],
            'iva' => (float)$p["l{$idLista}_iva"],
            'precio_final' => (float)$p["l{$idLista}_total"],
        ];
    }

    $data[] = [
        'codigo' => $p['codigo'],
        'descripcion' => $p['descripcion'],
        'descripcion_tienda' => !empty($p['descripcion_tienda']) ? $p['descripcion_tienda'] : $p['descripcion'],
        'marca' => $p['nombre_marca'],
        'rubro' => $p['rubro'],
        'familia' => $p['nombre_familia'],
        'listas_precios' => $listasPrecios,
        'alicuota_iva' => (float)$p['poriva'],
        'impuesto_interno' => (float)$p['ImpInt'],
        'stock' => (float)$p['stock'],
        'stock_disponible' => max(0, $stockDisponible),
        'controla_stock' => (bool)$p['controlastock'],
        'mostrar_sin_stock' => (bool)$p['mostrarsinstock'],
        'imagenes' => resolverImagenes($dirImagenes, $p['codigo'], $extensiones, $baseUrlImg, $token),
        'actualizado' => $p['timestamp'],
    ];
}

respond(200, [
    'success' => true,
    'data' => $data,
    'meta' => [
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
        'total' => $total,
    ],
]);
