<?php
/**
 * check_api_access.php - CONTROL DE SEGURIDAD BÁSICO PARA ENDPOINTS JSON
 * Incluir este archivo al inicio de todas las APIs.
 * Verifica: AJAX (salvo descargas del navegador) + Sesión Activa + Identificación de Empresa + Estado del Usuario.
 */

header('Content-Type: application/json');
// Las APIs devuelven datos operativos que pueden cambiar entre dos consultas
// consecutivas (precios, stock, clientes, etc.). Evitar que el navegador
// reutilice una respuesta GET anterior después de editar un producto.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. SEGURIDAD TÉCNICA. Los enlaces de descarga abren un GET normal del
// navegador, sin X-Requested-With. La excepción se limita a estas acciones;
// todas las demás acciones de estas APIs continúan requiriendo AJAX.
$script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$descargasNavegador = [
    'api_importar_productos.php' => ['download_template'],
    'api_importar_clientes.php' => ['download_template'],
    'api_importar_proveedores.php' => ['download_template'],
    'api_importar_precios.php' => ['download_template', 'backup'],
    'api_transportes_import.php' => ['download_template'],
    'api_conceptos_compra.php' => ['export_excel'],
    'api_depositos.php' => ['logo'], // <img> del encabezado
    'api_web_config.php' => ['imagen'], // <img> de vista previa (logo/banners de la web)
    'api_web_avisos_pago.php' => ['comprobante'], // adjunto del pago informado
];
$descargaNavegador = ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET'
    && in_array($_GET['action'] ?? null, $descargasNavegador[$script] ?? [], true);
$esAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if (!$esAjax && !$descargaNavegador) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Acceso directo no permitido. Use la aplicación.']);
    exit();
}

// 2. SESIÓN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. VALIDACIÓN DE CREDENCIALES
// Verificamos que el usuario esté logueado y tenga una empresa (tenant) seleccionada.
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tenant_cuit'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['status' => 'error', 'error' => 'Sesión expirada. Recargue la página.']);
    exit();
}

// 4. CONEXIÓN A BASE DE DATOS
// Si llegamos acá, el acceso es técnicamente válido.
// Incluimos la conexión para que esté disponible en el script principal.
require_once 'db.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Error interno de conexión.'
    ]);
    exit();
}

// 5. VALIDACIÓN DE ESTADO EN TIEMPO REAL (Habilitado / Logout forzado)
try {
    $stmtEstadoUsr = $pdo->prepare("SELECT habilitado, logout FROM alb_usuarios WHERE id = ? LIMIT 1");
    $stmtEstadoUsr->execute([$_SESSION['usuario_id']]);
    $estadoUsr = $stmtEstadoUsr->fetch(PDO::FETCH_ASSOC);

    if (!$estadoUsr || (int)$estadoUsr['habilitado'] === 0 || (int)$estadoUsr['logout'] === 1) {
        // Matamos la sesión desde el backend
        session_unset();
        session_destroy();
        
        http_response_code(401); // Devolvemos 401 para que la SPA / fetch handlee la desconexión
        echo json_encode([
            'status' => 'error', 
            'error' => 'Sesión finalizada. Tu cuenta fue deshabilitada o cerrada remotamente.'
        ]);
        exit();
    }
} catch (Exception $e) {
    // Si la base no tiene aún la columna no rompemos la API de inmediato
    // Pero si logramos detectar un fallo grave, se puede capturar aquí.
}
?>
