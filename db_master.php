<?php
/**
 * db_master.php - NÚCLEO DE COORDINACIÓN Y SEGURIDAD (Soberanía del CUIT)
 * Maneja el acceso al directorio central ClientesSistema validando tablas por CUIT.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,   
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

$requestHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
if (str_starts_with($requestHost, '[')) {
    $closingBracket = strpos($requestHost, ']');
    $requestHost = $closingBracket !== false ? substr($requestHost, 1, $closingBracket - 1) : $requestHost;
} else {
    $requestHost = explode(':', $requestHost, 2)[0];
}
$normalizedDir = strtolower(str_replace('\\', '/', __DIR__));
$isXamppLocal = str_starts_with($normalizedDir, 'c:/xampp/');
$isLocalhost = PHP_SAPI === 'cli'
    || $isXamppLocal
    || in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);

// Producción/VPS: credenciales reales en config_db_master.php (gitignoreado, nunca
// se versiona). Ese archivo debe existir en el VPS antes de desplegar esta versión.
$masterConfig = $isLocalhost
    ? [
        // Desarrollo local: XAMPP por defecto, sin contraseña.
        'host' => 'localhost',
        'db' => 'clientesSistema',
        'user' => 'root',
        'pass' => '',
    ]
    : require __DIR__ . '/config_db_master.php';

$master_host = $masterConfig['host'];
$master_db = $masterConfig['db'];
$master_user = $masterConfig['user'];
$master_pass = $masterConfig['pass'];
$master_charset = 'utf8mb4';
try {
    $pdo_master = new PDO("mysql:host=$master_host;dbname=$master_db;charset=$master_charset", $master_user, $master_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $pdo_master->exec("SET time_zone = '-03:00'");
} catch (\PDOException $e) {
    die("Error crítico de infraestructura: No se pudo conectar al Master. " . $e->getMessage());
}

/**
 * Registra eventos en log_accesos
 */
function logSecurityEvent($pdo_master, $cuit, $user, $event, $status) {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $sql = "INSERT INTO log_accesos (cuit, usuario, evento, estado, ip, user_agent, fecha) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())";
        $stmt = $pdo_master->prepare($sql);
        $stmt->execute([$cuit, $user, $event, $status, $ip, $ua]);
    } catch (Exception $e) {
        error_log("db_master.php: fallo al registrar log_accesos ($event/$status): " . $e->getMessage());
    }
}

/**
 * Verifica bloqueo por IP o Usuario
 */
function isBlocked($pdo_master, $identifier, $type = 'IP') {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $where = ($type === 'IP') ? "ip = ?" : "(cuit = ? OR usuario = ?) AND ip = ?";
        $params = ($type === 'IP') ? [$ip] : [$identifier, $identifier, $ip];

        $sql = "SELECT COUNT(*) FROM log_accesos 
                WHERE $where 
                AND estado = 'FAIL' 
                AND fecha > DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
        
        $stmt = $pdo_master->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() >= 5;
    } catch (Exception $e) {
        error_log("db_master.php: fallo al verificar bloqueo ($type/$identifier): " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene todas las configuraciones disponibles para un CUIT.
 * Se eliminó el retorno silencioso de [] para que login.php pueda capturar el error.
 */
function getTenantDatabases($pdo_master, $cuit) {
    $tableName = preg_replace('/[^a-zA-Z0-9]/', '', $cuit);
    if (empty($tableName)) return [];

    $stmtExiste = $pdo_master->prepare(
        "SELECT COUNT(*) FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ?"
    );
    $stmtExiste->execute([$tableName]);
    if ((int)$stmtExiste->fetchColumn() === 0) return [];

    /**
     * REGLA CRÍTICA: Uso obligatorio de backticks (``) para tablas numéricas.
     * Sin ellos, MySQL falla al interpretar '20272869554' como un nombre de tabla.
     */
    $sql = "SELECT id, nombre, cadenaconec, nmodo, pdv, habilitado as activa, tipoversion
            FROM `$tableName` WHERE habilitado = 1 ORDER BY id ASC";
    $stmt = $pdo_master->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


/**
 * Obtiene la configuración específica (por ID o la activa por defecto)
 */
function getTenantDatabaseConfig($pdo_master, $cuit, $db_id = null) {
    $tableName = preg_replace('/[^a-zA-Z0-9]/', '', $cuit);
    if (empty($tableName)) return null;

    try {
        if ($db_id) {
            $stmt = $pdo_master->prepare("SELECT cadenaconec, tipoversion FROM `$tableName` WHERE id = ? AND habilitado = 1 LIMIT 1");
            $stmt->execute([(int)$db_id]);
        } else {
            $stmt = $pdo_master->query("SELECT cadenaconec, tipoversion FROM `$tableName` WHERE habilitado = 1 LIMIT 1");
        }
        return $stmt->fetch() ?: null;
    } catch (Exception $e) {
        error_log("db_master.php: fallo al obtener configuración de tenant ($cuit): " . $e->getMessage());
        return null;
    }
}

/**
 * Parsea la cadena de conexión legacy
 */
function parseConnectionString($str) {
    $config = ['host' => 'localhost', 'db' => '', 'user' => '', 'pass' => '', 'port' => '3306'];
    if (empty($str)) return $config;

    foreach (explode(';', $str) as $part) {
        if (strpos($part, '=') === false) continue;
        list($key, $val) = explode('=', $part, 2);
        $key = strtolower(trim($key));
        $val = trim($val);
        if ($key === 'server' || $key === 'data source') $config['host'] = $val;
        if ($key === 'database' || $key === 'initial catalog') $config['db'] = $val;
        if ($key === 'user id' || $key === 'uid' || $key === 'user') $config['user'] = $val;
        if ($key === 'password' || $key === 'pwd') $config['pass'] = $val;
        if ($key === 'port') $config['port'] = $val;
    }
    return $config;
}
