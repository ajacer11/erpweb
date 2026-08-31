<?php
/**
 * db.php - CONEXIÓN DINÁMICA MULTI-DB
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db_master.php';

if (!isset($_SESSION['tenant_cuit'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        http_response_code(403);
        exit(json_encode(['success' => false, 'error' => 'Identificación requerida.']));
    }
    header("Location: login.php"); exit;
}

try {
    $cuit = $_SESSION['tenant_cuit'];
    $db_id = $_SESSION['tenant_db_id'] ?? null; // ID seleccionado en el paso 2

    // Obtenemos la configuración específica
    $dbConfig = getTenantDatabaseConfig($pdo_master, $cuit, $db_id);

    if (!$dbConfig) {
        throw new Exception("Configuración de acceso no encontrada [$cuit].");
    }

    $cfg = parseConnectionString($dbConfig['cadenaconec']);
    $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['db']};charset=utf8mb4";

    $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT            => 5,
        PDO::ATTR_PERSISTENT         => false
    ]);
    $pdo->exec("SET time_zone = '-03:00'");

} catch (Exception $e) {
    error_log('db.php: fallo de conexión a la base tenant: ' . $e->getMessage());
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        exit(json_encode(['success' => false, 'error' => 'La base de datos de la empresa no está disponible en este momento.']));
    }
    session_unset();
    session_destroy();
    header('Location: login.php?error=' . urlencode('La conexión de esta empresa no está disponible. Intentá nuevamente o contactá a soporte.'));
    exit;
}
