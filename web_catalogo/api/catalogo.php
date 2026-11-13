<?php
/**
 * catalogo.php - Productos, filtros, portada e imágenes.
 *
 * Un producto es visible en la web si: está habilitado, tiene "Mostrar en catálogo",
 * su rubro no está oculto, y tiene stock disponible o "Mostrar sin stock" o no controla stock.
 * Precio: el de la lista del cliente logueado; sin login, la lista pública (si está
 * habilitado mostrar precios). El neto se recalcula desde el final (precios_listas.php).
 */
declare(strict_types=1);

/** Rubro sin espacios ni saltos de línea al final (hay datos legacy con "Rubro
"). */
const SQL_RUBRO = "TRIM(REPLACE(REPLACE(IFNULL(p.rubro, ''), '\r', ''), '\n', ''))";

const EXT_FOTOS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
const MAX_FOTOS = 5;

/** WHERE de visibilidad (alias p). Agrega parámetros a $params. */
function sqlVisible(array &$params): string
{
    global $CFG;
    $w = "p.habilitado = 1 AND p.mostrarencatalogo = 1
          AND (p.mostrarsinstock = 1 OR p.controlastock = 0 OR (IFNULL(p.Stock, 0) - IFNULL(p.stockcomprometido, 0)) > 0)";
    $ocultos = json_decode($CFG['rubros_ocultos'] ?: '[]', true) ?: [];
    if ($ocultos) {
        $w .= " AND " . SQL_RUBRO . " NOT IN (" . implode(',', array_fill(0, count($ocultos), '?')) . ")";
        array_push($params, ...$ocultos);
    }
    return $w;
}

/** Columnas de precio para la lista (o NULL si no se muestran precios). */
function sqlColumnasPrecio(?int $lista): string
{
    if ($lista === null) return "NULL AS precio_final, NULL AS precio_neto";
    $col = $lista === 1 ? 'precio' : "precio$lista";
    return "p.$col AS precio_final, ROUND((p.$col - IFNULL(p.ImpInt, 0)) / (1 + IFNULL(p.poriva, 0) / 100), 2) AS precio_neto";
}

const SQL_COLUMNAS_PRODUCTO = "p.id, p.codigo, p.descripcion, p.descripcion_tienda, " . SQL_RUBRO . " AS rubro, p.familia, f.nombre AS familia_nombre,
    p.Marca AS marca, m.Marca AS marca_nombre, p.Stock, p.stockcomprometido, p.controlastock, p.mostrarsinstock,
    p.uniporbulto, p.poriva";
const SQL_JOINS_PRODUCTO = "LEFT JOIN familias f ON f.id = p.familia LEFT JOIN Marcas m ON m.id = p.Marca";

/** Ruta del archivo de una foto (slot 1 = portada) o null. */
function rutaFoto(string $codigo, int $slot): ?string
{
    global $SITIO;
    $safe = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $codigo);
    $base = webDirFotosProductos((string) $SITIO['cuit']) . '/' . ($slot === 1 ? $safe : "{$safe}_{$slot}");
    foreach (array_keys(EXT_FOTOS) as $ext) {
        if (is_file("$base.$ext")) return "$base.$ext";
    }
    return null;
}

function urlFoto(string $codigo, int $slot, ?string $ruta, bool $blob = false): ?string
{
    if (!$ruta && !$blob) return null;
    $v = $ruta ? filemtime($ruta) : 'b';
    return '/api/img/producto?c=' . rawurlencode($codigo) . "&s=$slot&v=$v";
}

/** Códigos (de la lista) que tienen foto legacy en BLOB (fotosproductos, sistema de escritorio). */
function codigosConBlob(array $codigos): array
{
    global $pdo;
    if (!$codigos) return [];
    try {
        $st = $pdo->prepare("SELECT DISTINCT codigo FROM fotosproductos WHERE codigo IN (" . implode(',', array_fill(0, count($codigos), '?')) . ")");
        $st->execute(array_values($codigos));
        return array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        return []; // la tabla puede no existir
    }
}

function estadoStock(array $p): array
{
    global $CFG;
    $modo = $CFG['mostrar_stock'];
    $disp = (float) $p['Stock'] - (float) $p['stockcomprometido'];
    $hay = !(int) $p['controlastock'] || $disp > 0;
    $r = ['disponible' => $hay];
    if ($modo === 'cantidad' && (int) $p['controlastock']) $r['cantidad'] = max(0, (float) $disp);
    if ($modo === 'ninguno') $r = ['disponible' => null];
    return $r;
}

/** Normaliza una fila de producto para el front. */
function formatearProducto(array $p, array $novedades, array $conBlob): array
{
    $ruta = rutaFoto((string) $p['codigo'], 1);
    $final = $p['precio_final'] !== null ? round((float) $p['precio_final'], 2) : null;
    return [
        'codigo' => (string) $p['codigo'],
        'nombre' => trim((string) $p['descripcion']),
        'rubro' => $p['rubro'] ?: null,
        'familia' => $p['familia'] ? ['id' => (int) $p['familia'], 'nombre' => $p['familia_nombre']] : null,
        'marca' => $p['marca'] && $p['marca_nombre'] && strcasecmp(trim((string) $p['marca_nombre']), 'Sin Informar') !== 0 ? ['id' => (int) $p['marca'], 'nombre' => $p['marca_nombre']] : null,
        'precio' => $final !== null && $final > 0 ? $final : null,
        'precioNeto' => $final !== null && $final > 0 ? round((float) $p['precio_neto'], 2) : null,
        'alicuotaIva' => (float) $p['poriva'],
        'stock' => estadoStock($p),
        'bulto' => max(1, (int) $p['uniporbulto']),
        'imagen' => urlFoto((string) $p['codigo'], 1, $ruta, isset($conBlob[$p['codigo']])),
        'nuevo' => isset($novedades[(int) $p['id']]),
    ];
}

/** IDs de los últimos N productos visibles (las "novedades"). */
function idsNovedades(): array
{
    global $pdo, $CFG;
    static $cache = null;
    if ($cache !== null) return $cache;
    $n = (int) $CFG['novedades_cantidad'];
    if ($n <= 0) return $cache = [];
    $params = [];
    $st = $pdo->prepare("SELECT p.id FROM alb_productos p WHERE " . sqlVisible($params) . " ORDER BY p.id DESC LIMIT $n");
    $st->execute($params);
    return $cache = array_flip(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN)));
}

/** Ejecuta un SELECT de productos y los devuelve formateados. */
function traerProductos(string $whereExtra, array $paramsExtra, string $orden, int $limite, int $offset = 0): array
{
    global $pdo;
    $params = [];
    $where = sqlVisible($params);
    $sql = "SELECT " . SQL_COLUMNAS_PRODUCTO . ", " . sqlColumnasPrecio(listaParaPrecios()) . "
            FROM alb_productos p " . SQL_JOINS_PRODUCTO . "
            WHERE $where $whereExtra ORDER BY $orden LIMIT $limite OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute([...$params, ...$paramsExtra]);
    $filas = $st->fetchAll();
    $nov = idsNovedades();
    $blob = codigosConBlob(array_column($filas, 'codigo'));
    return array_map(fn($p) => formatearProducto($p, $nov, $blob), $filas);
}

/** Filtros del listado a partir de la querystring. Devuelve [sqlExtra, params]. */
function filtrosListado(array $q): array
{
    $sql = '';
    $params = [];
    $texto = trim((string) ($q['q'] ?? ''));
    if ($texto !== '') {
        foreach (array_slice(preg_split('/\s+/', $texto), 0, 6) as $palabra) {
            $sql .= " AND (p.descripcion LIKE ? OR p.codigo LIKE ? OR p.descripcion_tienda LIKE ?)";
            array_push($params, "%$palabra%", "$palabra%", "%$palabra%");
        }
    }
    if (($q['rubro'] ?? '') !== '') { $sql .= " AND " . SQL_RUBRO . " = ?"; $params[] = trim((string) $q['rubro']); }
    if (($q['familia'] ?? '') !== '') { $sql .= " AND p.familia = ?"; $params[] = (int) $q['familia']; }
    if (($q['marca'] ?? '') !== '') { $sql .= " AND p.Marca = ?"; $params[] = (int) $q['marca']; }
    if (!empty($q['nuevos'])) {
        $ids = array_keys(idsNovedades());
        $sql .= $ids ? " AND p.id IN (" . implode(',', $ids) . ")" : " AND 1 = 0";
    }
    if (!empty($q['atr']) && is_array($q['atr'])) {
        foreach (array_slice($q['atr'], 0, 10, true) as $atrId => $valor) {
            if ((string) $valor === '') continue;
            $sql .= " AND EXISTS (SELECT 1 FROM prod_atributo_valores v WHERE v.codigo = p.codigo AND v.atributo_id = ? AND v.valor = ?)";
            array_push($params, (int) $atrId, (string) $valor);
        }
    }
    return [$sql, $params];
}

function ordenListado(string $orden): string
{
    global $CFG;
    $precio = listaParaPrecios() !== null;
    return match ($orden) {
        'precio_asc' => $precio ? "precio_final = 0, precio_final ASC" : "p.descripcion",
        'precio_desc' => $precio ? "precio_final DESC" : "p.descripcion",
        'nombre' => "p.descripcion ASC",
        'nuevos' => "p.id DESC",
        'codigo' => "CAST(p.codigo AS UNSIGNED), p.codigo",
        default => match ($CFG['catalogo_orden']) {
            'codigo' => "CAST(p.codigo AS UNSIGNED), p.codigo",
            'descripcion' => "p.descripcion ASC",
            default => "f.nombre IS NULL, f.nombre, p.descripcion",
        },
    };
}

// =================================================================== Handlers

function hProductos(): array
{
    global $pdo;
    if (!catalogoVisible()) throw new ApiError('Ingresá para ver el catálogo.', 401, 'catalogo_privado');
    $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
    $porPagina = max(1, min(60, (int) ($_GET['por_pagina'] ?? 24)));
    [$extra, $paramsExtra] = filtrosListado($_GET);

    $params = [];
    $where = sqlVisible($params);
    $st = $pdo->prepare("SELECT COUNT(*) FROM alb_productos p WHERE $where $extra");
    $st->execute([...$params, ...$paramsExtra]);
    $total = (int) $st->fetchColumn();

    $items = traerProductos($extra, $paramsExtra, ordenListado((string) ($_GET['orden'] ?? '')), $porPagina, ($pagina - 1) * $porPagina);
    return ['items' => $items, 'total' => $total, 'pagina' => $pagina, 'porPagina' => $porPagina, 'paginas' => (int) ceil($total / $porPagina)];
}

/** Listado completo para imprimir (sin paginar, hasta 5000). */
function hProductosImprimir(): array
{
    if (!catalogoVisible()) throw new ApiError('Ingresá para ver el catálogo.', 401, 'catalogo_privado');
    [$extra, $paramsExtra] = filtrosListado($_GET);
    $items = traerProductos($extra, $paramsExtra, SQL_RUBRO . " = '', " . SQL_RUBRO . ", " . ordenListado((string) ($_GET['orden'] ?? '')), 5000);
    return ['items' => $items];
}

function hFiltros(): array
{
    global $pdo;
    if (!catalogoVisible()) throw new ApiError('Ingresá para ver el catálogo.', 401, 'catalogo_privado');
    $params = [];
    $where = sqlVisible($params);
    $rubroSel = (string) ($_GET['rubro'] ?? '');

    $st = $pdo->prepare("SELECT " . SQL_RUBRO . " AS nombre, COUNT(*) AS cantidad FROM alb_productos p WHERE $where AND " . SQL_RUBRO . " <> '' GROUP BY nombre ORDER BY nombre");
    $st->execute($params);
    $rubros = $st->fetchAll();

    $extraRubro = $rubroSel !== '' ? " AND " . SQL_RUBRO . " = ?" : '';
    $pr = $rubroSel !== '' ? [...$params, $rubroSel] : $params;

    $st = $pdo->prepare("SELECT f.id, f.nombre, COUNT(*) AS cantidad FROM alb_productos p JOIN familias f ON f.id = p.familia WHERE $where $extraRubro GROUP BY f.id, f.nombre ORDER BY f.nombre");
    $st->execute($pr);
    $familias = $st->fetchAll();

    $st = $pdo->prepare("SELECT m.id, m.Marca AS nombre, COUNT(*) AS cantidad FROM alb_productos p JOIN Marcas m ON m.id = p.Marca WHERE $where $extraRubro AND m.Marca <> 'Sin Informar' GROUP BY m.id, m.Marca ORDER BY m.Marca");
    $st->execute($pr);
    $marcas = $st->fetchAll();

    $atributos = [];
    try {
        $st = $pdo->prepare("SELECT a.id, a.nombre, a.unidad, v.valor, COUNT(*) AS cantidad
                             FROM prod_atributos a
                             JOIN prod_atributo_valores v ON v.atributo_id = a.id
                             JOIN alb_productos p ON p.codigo = v.codigo
                             WHERE a.activo = 1 AND a.filtrable = 1 AND $where $extraRubro
                             GROUP BY a.id, a.nombre, a.unidad, v.valor ORDER BY a.orden, a.nombre, v.valor");
        $st->execute($pr);
        foreach ($st->fetchAll() as $r) {
            $atributos[$r['id']] ??= ['id' => (int) $r['id'], 'nombre' => $r['nombre'], 'unidad' => $r['unidad'], 'valores' => []];
            $atributos[$r['id']]['valores'][] = ['valor' => $r['valor'], 'cantidad' => (int) $r['cantidad']];
        }
    } catch (Throwable $e) {
        error_log('web_catalogo filtros atributos: ' . $e->getMessage());
    }

    $cast = fn($rows) => array_map(fn($r) => array_merge($r, ['cantidad' => (int) $r['cantidad']] + (isset($r['id']) ? ['id' => (int) $r['id']] : [])), $rows);
    return ['rubros' => $cast($rubros), 'familias' => $cast($familias), 'marcas' => $cast($marcas), 'atributos' => array_values($atributos)];
}

function hProducto(): array
{
    global $pdo;
    if (!catalogoVisible()) throw new ApiError('Ingresá para ver el catálogo.', 401, 'catalogo_privado');
    $codigo = (string) ($_GET['c'] ?? '');
    $items = traerProductosCompletos([$codigo]);
    if (!$items) throw new ApiError('Producto no encontrado.', 404);
    $p = $items[0];

    // Fotos (hasta 5). La portada puede venir del BLOB legacy.
    $fotos = [];
    for ($s = 1; $s <= MAX_FOTOS; $s++) {
        $ruta = rutaFoto($codigo, $s);
        if ($ruta) $fotos[] = urlFoto($codigo, $s, $ruta);
        elseif ($s === 1 && $p['imagen']) $fotos[] = $p['imagen'];
    }
    $p['fotos'] = $fotos;

    // Características
    $p['caracteristicas'] = [];
    try {
        $st = $pdo->prepare("SELECT a.nombre, a.tipo, a.unidad, v.valor,
                                    (SELECT MIN(pl.orden) FROM prod_atributo_plantillas pl WHERE pl.atributo_id = a.id
                                      AND ((pl.ambito = 'rubro' AND pl.ambito_valor = ?) OR (pl.ambito = 'familia' AND pl.ambito_valor = ?))) AS op
                             FROM prod_atributo_valores v JOIN prod_atributos a ON a.id = v.atributo_id
                             WHERE v.codigo = ? AND a.activo = 1
                             ORDER BY op IS NULL, op, a.orden, a.nombre");
        $st->execute([(string) $p['rubro'], (string) ($p['familia']['id'] ?? ''), $codigo]);
        foreach ($st->fetchAll() as $r) {
            $valor = $r['valor'];
            if ($r['tipo'] === 'numero') $valor = str_replace('.', ',', $valor) . ($r['unidad'] ? ' ' . $r['unidad'] : '');
            $p['caracteristicas'][] = ['nombre' => $r['nombre'], 'valor' => $valor];
        }
    } catch (Throwable $e) {
        error_log('web_catalogo caracteristicas: ' . $e->getMessage());
    }

    // Relacionados: misma familia (o mismo rubro si no tiene familia).
    if ($p['familia']) {
        $p['relacionados'] = traerProductos(" AND p.familia = ? AND p.codigo <> ?", [$p['familia']['id'], $codigo], "RAND()", 8);
    } elseif ($p['rubro']) {
        $p['relacionados'] = traerProductos(" AND " . SQL_RUBRO . " = ? AND p.codigo <> ?", [$p['rubro'], $codigo], "RAND()", 8);
    } else {
        $p['relacionados'] = [];
    }
    return $p;
}

/** Productos visibles por código, con descripción larga. */
function traerProductosCompletos(array $codigos): array
{
    global $pdo;
    $codigos = array_values(array_unique(array_filter(array_map('strval', $codigos), 'strlen')));
    if (!$codigos) return [];
    $params = [];
    $where = sqlVisible($params);
    $in = implode(',', array_fill(0, count($codigos), '?'));
    $st = $pdo->prepare("SELECT " . SQL_COLUMNAS_PRODUCTO . ", " . sqlColumnasPrecio(listaParaPrecios()) . "
                         FROM alb_productos p " . SQL_JOINS_PRODUCTO . " WHERE $where AND p.codigo IN ($in)");
    $st->execute([...$params, ...$codigos]);
    $filas = $st->fetchAll();
    $nov = idsNovedades();
    $blob = codigosConBlob(array_column($filas, 'codigo'));
    return array_map(function ($f) use ($nov, $blob) {
        $p = formatearProducto($f, $nov, $blob);
        $desc = trim((string) $f['descripcion_tienda']);
        $p['descripcion'] = $desc;
        return $p;
    }, $filas);
}

function hInicio(): array
{
    global $pdo;
    if (!catalogoVisible()) return ['destacados' => [], 'novedades' => [], 'rubros' => []];

    $destacados = [];
    $cods = $pdo->query("SELECT codigo FROM web_destacados ORDER BY orden, codigo LIMIT 24")->fetchAll(PDO::FETCH_COLUMN);
    if ($cods) {
        $porCodigo = array_column(traerProductosCompletos($cods), null, 'codigo');
        foreach ($cods as $c) if (isset($porCodigo[$c])) $destacados[] = $porCodigo[$c];
    }
    $novedades = traerProductos('', [], 'p.id DESC', 12);

    // Rubros con una foto de muestra (el producto más nuevo con foto).
    $params = [];
    $where = sqlVisible($params);
    $st = $pdo->prepare("SELECT " . SQL_RUBRO . " AS rubro, p.codigo FROM alb_productos p WHERE $where AND " . SQL_RUBRO . " <> '' ORDER BY rubro, p.id DESC");
    $st->execute($params);
    $rubros = [];
    foreach ($st->fetchAll() as $r) {
        $nombre = $r['rubro'];
        $rubros[$nombre] ??= ['nombre' => $nombre, 'cantidad' => 0, 'imagen' => null, '_probados' => 0];
        $rubros[$nombre]['cantidad']++;
        if (!$rubros[$nombre]['imagen'] && $rubros[$nombre]['_probados'] < 15) {
            $rubros[$nombre]['_probados']++;
            $ruta = rutaFoto((string) $r['codigo'], 1);
            if ($ruta) $rubros[$nombre]['imagen'] = urlFoto((string) $r['codigo'], 1, $ruta);
        }
    }
    $rubros = array_map(function ($r) { unset($r['_probados']); return $r; }, array_values($rubros));

    $params = [];
    $st = $pdo->prepare("SELECT COUNT(*) FROM alb_productos p WHERE " . sqlVisible($params));
    $st->execute($params);
    $contadores = ['productos' => (int) $st->fetchColumn(), 'rubros' => count($rubros), 'novedades' => count(idsNovedades())];

    return ['destacados' => $destacados, 'novedades' => $novedades, 'rubros' => $rubros, 'contadores' => $contadores];
}

/** Precios actuales de los productos del carrito (el carrito vive en el navegador). */
function hPreciosCarrito(): array
{
    $codigos = array_slice((array) (cuerpoJson()['codigos'] ?? []), 0, 300);
    return ['items' => traerProductosCompletos($codigos)];
}

// =================================================================== Imágenes

function servirArchivo(string $ruta, string $mime, bool $publica): never
{
    $etag = '"' . md5($ruta . filemtime($ruta) . filesize($ruta)) . '"';
    header('Cache-Control: ' . ($publica ? 'public' : 'private') . ', max-age=604800');
    header('ETag: ' . $etag);
    if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
        http_response_code(304);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

function hImagenProducto(): never
{
    global $pdo;
    $codigo = (string) ($_GET['c'] ?? '');
    $slot = max(1, min(MAX_FOTOS, (int) ($_GET['s'] ?? 1)));
    // Solo productos visibles en la web (no se pueden enumerar fotos de productos ocultos).
    $params = [];
    $st = $pdo->prepare("SELECT 1 FROM alb_productos p WHERE " . sqlVisible($params) . " AND p.codigo = ? LIMIT 1");
    $st->execute([...$params, $codigo]);
    if (!catalogoVisible() || !$st->fetchColumn()) {
        http_response_code(404);
        exit;
    }
    $ruta = rutaFoto($codigo, $slot);
    if ($ruta) servirArchivo($ruta, EXT_FOTOS[strtolower(pathinfo($ruta, PATHINFO_EXTENSION))], true);

    if ($slot === 1) {
        try {
            $sb = $pdo->prepare("SELECT imagen FROM fotosproductos WHERE codigo = ? ORDER BY id DESC LIMIT 1");
            $sb->execute([$codigo]);
            $blob = $sb->fetchColumn();
            if ($blob) {
                header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->buffer($blob) ?: 'image/jpeg'));
                header('Cache-Control: public, max-age=86400');
                echo $blob;
                exit;
            }
        } catch (Throwable $e) {
        }
    }
    http_response_code(404);
    exit;
}

/** Imágenes de la web (logo, banners, quiénes somos). Solo las referenciadas en la configuración. */
function hImagenSitio(): never
{
    global $CFG, $SITIO;
    $f = basename((string) ($_GET['f'] ?? ''));
    $usadas = [$CFG['logo'], $CFG['favicon'], $CFG['quienes_somos_imagen']];
    foreach (json_decode($CFG['banners'] ?: '[]', true) ?: [] as $b) $usadas[] = $b['imagen'] ?? '';
    $ruta = webDirImagenes((string) $SITIO['cuit']) . '/' . $f;
    if ($f === '' || !in_array($f, $usadas, true) || !is_file($ruta)) {
        http_response_code(404);
        exit;
    }
    servirArchivo($ruta, str_ends_with($f, '.png') ? 'image/png' : 'image/jpeg', true);
}
