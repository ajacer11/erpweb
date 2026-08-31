<?php
/**
 * check_api_access.php - CONTROL DE SEGURIDAD BÁSICO PARA ENDPOINTS JSON
 * Incluir este archivo al inicio de todas las APIs.
 * Verifica: AJAX + Sesión Activa + Identificación de Empresa + Estado del Usuario.
 */

header('Content-Type: application/json');
date_default_timezone_set('America/Argentina/Buenos_Aires');

// 1. SEGURIDAD TÉCNICA (AJAX ONLY)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
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