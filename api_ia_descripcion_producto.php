<?php
/**
 * api_ia_descripcion_producto.php - Genera/mejora con IA la descripción de
 * catálogo de un producto (campo alb_productos.descripcion_tienda).
 *
 * No persiste nada: solo devuelve un texto sugerido a partir de los datos ya
 * cargados del producto (y, en modo "mejorar", del texto que escribió el
 * usuario). El usuario decide si lo guarda al grabar el producto.
 */
require_once 'check_api_access.php';
require_once 'config_ia.php';

// ------------------------------------------------------------------
// 1. LÍMITE DE USO POR SESIÓN (evita abuso / costos descontrolados)
// ------------------------------------------------------------------
const IA_DESC_LIMITE_MENSAJES = 20;
const IA_DESC_LIMITE_VENTANA_SEG = 600; // 10 minutos

if (!isset($_SESSION['ia_desc_producto_uso']) || !is_array($_SESSION['ia_desc_producto_uso'])) {
    $_SESSION['ia_desc_producto_uso'] = [];
}
$ahora = time();
$_SESSION['ia_desc_producto_uso'] = array_values(array_filter(
    $_SESSION['ia_desc_producto_uso'],
    fn($t) => ($ahora - $t) < IA_DESC_LIMITE_VENTANA_SEG
));

if (count($_SESSION['ia_desc_producto_uso']) >= IA_DESC_LIMITE_MENSAJES) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Estás generando muchas descripciones seguidas. Esperá unos minutos y volvé a intentar.']);
    exit;
}

// ------------------------------------------------------------------
// 2. ENTRADA
// ------------------------------------------------------------------
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$codigo = trim((string)($input['codigo'] ?? ''));
$modo = ($input['modo'] ?? '') === 'mejorar' ? 'mejorar' : 'generar';
$textoActual = trim((string)($input['texto_actual'] ?? ''));

if ($codigo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta el código de producto.']);
    exit;
}
if ($modo === 'mejorar' && $textoActual === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No hay texto para mejorar.']);
    exit;
}
if (mb_strlen($textoActual) > 1000) {
    $textoActual = mb_substr($textoActual, 0, 1000);
}

// ------------------------------------------------------------------
// 3. DATOS DEL PRODUCTO (fuente de verdad: la base, no lo que mande el cliente)
// ------------------------------------------------------------------
try {
    $stmt = $pdo->prepare("
        SELECT p.codigo, p.descripcion, p.rubro, m.marca AS nombre_marca, f.nombre AS nombre_familia
        FROM alb_productos p
        LEFT JOIN Marcas m ON m.id = p.Marca
        LEFT JOIN familias f ON f.id = p.familia
        WHERE p.codigo = ? LIMIT 1
    ");
    $stmt->execute([$codigo]);
    $prod = $stmt->fetch();
} catch (Exception $e) {
    error_log('api_ia_descripcion_producto.php: error consultando producto: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No se pudo leer el producto.']);
    exit;
}

if (!$prod) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Producto no encontrado.']);
    exit;
}

$datosProducto = json_encode([
    'descripcion_interna' => $prod['descripcion'],
    'marca' => $prod['nombre_marca'],
    'rubro' => $prod['rubro'],
    'familia' => $prod['nombre_familia'],
], JSON_UNESCAPED_UNICODE);

// ------------------------------------------------------------------
// 4. PROMPT
// ------------------------------------------------------------------
if ($modo === 'generar') {
    $systemPrompt = <<<PROMPT
Sos un redactor de catálogos de e-commerce en español rioplatense. A partir de
los datos internos de un producto (JSON a continuación), escribí una
descripción breve y atractiva pensada para mostrarse en una tienda online.

REGLAS:
1. Entre 1 y 3 oraciones, máximo 400 caracteres.
2. No inventes características, medidas, materiales ni beneficios que no
   estén sugeridos por los datos dados (nombre, marca, rubro, familia).
3. Texto plano, sin markdown, sin comillas envolventes, sin emojis.
4. No repitas literalmente el código de producto.
5. Devolvé ÚNICAMENTE el texto de la descripción, nada más.
PROMPT;
    $userPrompt = "Datos del producto:\n" . $datosProducto;
} else {
    $systemPrompt = <<<PROMPT
Sos un redactor de catálogos de e-commerce en español rioplatense. Te paso un
texto ya escrito por el usuario para la ficha de un producto en una tienda
online, y los datos internos del producto como contexto.

REGLAS:
1. Mejorá la redacción (claridad, prolijidad, atractivo comercial) SIN
   inventar características, medidas, materiales ni beneficios nuevos que no
   estén ya en el texto original o en los datos del producto.
2. Conservá la idea y el largo aproximado del texto original (no lo alargues
   desmedidamente).
3. Texto plano, sin markdown, sin comillas envolventes, sin emojis.
4. Devolvé ÚNICAMENTE el texto mejorado, nada más.
PROMPT;
    $userPrompt = "Datos del producto:\n" . $datosProducto . "\n\nTexto a mejorar:\n" . $textoActual;
}

// ------------------------------------------------------------------
// 5. LLAMADA A DEEPSEEK
// ------------------------------------------------------------------
$payload = json_encode([
    'model' => DEEPSEEK_MODEL,
    'messages' => [
        ['role' => 'system', 'content' => $systemPrompt],
        ['role' => 'user', 'content' => $userPrompt],
    ],
    'temperature' => 0.6,
    'max_tokens' => 250,
    'stream' => false,
], JSON_UNESCAPED_UNICODE);

$ch = curl_init(DEEPSEEK_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . DEEPSEEK_API_KEY,
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => 30,
]);
$respuestaCruda = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($respuestaCruda === false || $curlError) {
    error_log('api_ia_descripcion_producto.php: fallo de conexión con DeepSeek: ' . $curlError);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'No se pudo contactar a la IA en este momento. Probá de nuevo en un rato.']);
    exit;
}

$resultado = json_decode($respuestaCruda, true);

if ($httpCode !== 200 || !isset($resultado['choices'][0]['message']['content'])) {
    error_log('api_ia_descripcion_producto.php: respuesta inesperada de DeepSeek (HTTP ' . $httpCode . '): ' . substr($respuestaCruda, 0, 500));
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'La IA no pudo responder en este momento. Probá de nuevo en un rato.']);
    exit;
}

// Registrar el uso recién cuando la consulta se resolvió con éxito.
$_SESSION['ia_desc_producto_uso'][] = $ahora;

$texto = trim($resultado['choices'][0]['message']['content']);
$texto = trim($texto, "\"'“”");

echo json_encode(['success' => true, 'descripcion' => $texto]);
