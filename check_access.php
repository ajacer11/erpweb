<?php
/**
 * check_access.php - CONTROL CENTRALIZADO DE SEGURIDAD Y PERMISOS
 * Incluye validación estricta de licencia/contrato (SaaS) y roles de usuario.
 * FIX: Los archivos core bypasean también la validación de roles (sección 6+).
 */

// 1. SEGURIDAD TÉCNICA (AJAX ONLY)
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header("Location: index.php");
    exit();
}

// 2. INICIO SEGURO DE SESIÓN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. VERIFICACIÓN DE AUTENTICACIÓN
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['tenant_cuit'])) {
    header("Location: login.php?error=" . urlencode("Sesión expirada."));
    exit();
}

// 4. CONEXIÓN A BASE DE DATOS
require_once 'db.php';

// DEFINICIÓN DE FUNCIONES DE AUDITORÍA Y SEGURIDAD VISUAL
function getIpAuditData() {
    return [
        'remote_addr'        => $_SERVER['REMOTE_ADDR'] ?? null,
        'x_forwarded_for'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        'cf_connecting_ip'   => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        'via'                => $_SERVER['HTTP_VIA'] ?? null,
        'user_agent'         => $_SERVER['HTTP_USER_AGENT'] ?? null,
    ];
}

function logSecurityEventInternal($pdo, $reason, $currentScript) {
    $userId   = $_SESSION['usuario_id']     ?? 0;
    $userName = $_SESSION['usuario_nombre'] ?? 'Desconocido';
    $ipData   = getIpAuditData();
    $requestUri = $_SERVER['REQUEST_URI'] ?? null;
    $referer    = $_SERVER['HTTP_REFERER'] ?? null;

    try {
        $stmtLog = $pdo->prepare("
            INSERT INTO log_accesos_denegados 
            (usuario_id, usuario_nombre, modulo, request_uri, referer, remote_addr, x_forwarded_for, cf_connecting_ip, via, user_agent, datos_adicionales) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtLog->execute([
            $userId, $userName, $currentScript, $requestUri, $referer,
            $ipData['remote_addr'], $ipData['x_forwarded_for'], $ipData['cf_connecting_ip'],
            $ipData['via'], $ipData['user_agent'], $reason
        ]);
    } catch (Exception $e) {
        error_log("check_access.php: fallo al registrar log_accesos_denegados: " . $e->getMessage());
    }
}

function denyAccessAndLog($pdo, $reason, $currentScript) {
    logSecurityEventInternal($pdo, $reason, $currentScript);
    session_unset();
    session_destroy();
    echo '<div class="container d-flex flex-column justify-content-center align-items-center" style="height: 70vh;">';
    echo '  <div class="text-center p-5 rounded border border-danger" style="background: rgba(220, 53, 69, 0.1); max-width: 600px;">';
    echo '      <i class="fa-solid fa-triangle-exclamation text-danger fa-4x mb-4"></i>';
    echo '      <h2 class="text-white fw-bold mb-3">ACCESO DENEGADO</h2>';
    echo '      <p class="text-white fs-5 mb-4">' . htmlspecialchars($reason) . '</p>';
    echo '      <p class="text-accent mb-4"><i class="fa-solid fa-shield-halved me-2"></i>Deberás volver a logearte, se dió aviso a un administrador.</p>';
    echo '      <div class="mt-4"><a href="login.php" class="btn btn-danger rounded-pill fw-bold px-4">SALIR AHORA</a></div>';
    echo '  </div>';
    echo '</div>';
    exit();
}

function forceUpdateAndLog($pdo, $reason) {
    logSecurityEventInternal($pdo, $reason, "Global");
    session_unset();
    session_destroy();
    echo '<div class="container d-flex flex-column justify-content-center align-items-center" style="height: 70vh;">';
    echo '  <div class="text-center p-5 rounded border border-info" style="background: rgba(13, 202, 240, 0.1); max-width: 600px;">';
    echo '      <i class="fa-solid fa-arrows-rotate text-info fa-4x mb-4 fa-spin" style="--fa-animation-duration: 2s;"></i>';
    echo '      <h2 class="text-white fw-bold mb-3">ACTUALIZACIÓN DE ROLES</h2>';
    echo '      <p class="text-white fs-5 mb-4">Un administrador ha modificado tus permisos de usuario recientemente.</p>';
    echo '      <p class="text-accent mb-4"><i class="fa-solid fa-circle-info me-2"></i>Es necesario reiniciar la sesión para aplicar los cambios.</p>';
    echo '      <div class="mt-4"><a href="login.php" class="btn btn-info fw-bold rounded-pill px-5">REINICIAR SESIÓN</a></div>';
    echo '  </div>';
    echo '</div>';
    exit();
}

function forceLogoutAndLog($pdo, $reason) {
    logSecurityEventInternal($pdo, $reason, "Global");
    session_unset();
    session_destroy();
    echo '<div class="container d-flex flex-column justify-content-center align-items-center" style="height: 70vh;">';
    echo '  <div class="text-center p-5 rounded border border-warning" style="background: rgba(255, 193, 7, 0.1); max-width: 600px;">';
    echo '      <i class="fa-solid fa-right-from-bracket text-warning fa-4x mb-4"></i>';
    echo '      <h2 class="text-white fw-bold mb-3">SESIÓN CERRADA</h2>';
    echo '      <p class="text-white fs-5 mb-4">Tu sesión ha sido finalizada remotamente por un administrador del sistema.</p>';
    echo '      <div class="mt-4"><a href="login.php" class="btn btn-warning fw-bold rounded-pill px-5 text-dark">VOLVER AL LOGIN</a></div>';
    echo '  </div>';
    echo '</div>';
    exit();
}

// ======================================================================
// 4.5. VALIDACIÓN DE ESTADO DEL USUARIO (Bloqueos en tiempo real)
// ======================================================================
try {
    $stmtEstado = $pdo->prepare("SELECT habilitado, logout FROM alb_usuarios WHERE id = ? LIMIT 1");
    $stmtEstado->execute([$_SESSION['usuario_id']]);
    $estadoRow = $stmtEstado->fetch(PDO::FETCH_ASSOC);

    if (!$estadoRow) {
        denyAccessAndLog($pdo, "El usuario ya no existe en la base de datos.", "Global");
    }
    if ((int)$estadoRow['habilitado'] === 0) {
        denyAccessAndLog($pdo, "La cuenta ha sido deshabilitada por el administrador.", "Global");
    }
    if ((int)$estadoRow['logout'] === 1) {
        forceLogoutAndLog($pdo, "Cierre de sesión forzado remotamente.");
    }
} catch (Exception $e) {
    // Falla silenciada por compatibilidad legacy si la columna no existe aún
    error_log("check_access.php: fallo en validación de estado de usuario: " . $e->getMessage());
}

// ======================================================================
// 5. DEFINICIÓN DE ARCHIVOS CORE (se usará también en sección 6+)
// ======================================================================
$currentScript = basename($_SERVER['PHP_SELF']);

$version = (int)($_SESSION['version'] ?? 1);
$version = $version === 2 ? 2 : 1;
$_SESSION['version'] = $version;

// Licencia 1 sólo dispone del panel rápido. Licencia 2 dispone de ambos.
$isDashboard = preg_match('/^dashboard\d+\.php$/', $currentScript) === 1;
$allowedDashboards = $version === 2
    ? ['dashboard1.php', 'dashboard2.php']
    : ['dashboard1.php'];

if ($isDashboard && !in_array($currentScript, $allowedDashboards, true)) {
    denyAccessAndLog($pdo, "La licencia contratada no incluye este panel.", $currentScript);
}

// ayuda.php no es un módulo de licencia: es el manual de ayuda, accesible
// para cualquier usuario sin importar el plan contratado ni sus permisos
// (se llega a él desde el menú de usuario, que no forma parte de
// menu1.json/menu2.json, por eso nunca aparecería en $contractUrls).
$core_files = ['index.php', 'login.php', 'logout.php', 'ayuda.php'];
$isCoreFile = in_array($currentScript, $core_files)
           || in_array($currentScript, $allowedDashboards, true);

// ======================================================================
// VALIDACIÓN DE CONTRATO (VERSIÓN SAAS)
// ======================================================================
$json_path    = 'menu' . $version . '.json';
$contractUrls = [];

if (file_exists($json_path)) {
    $menu_data = json_decode(file_get_contents($json_path), true);
    if (!function_exists('extractContractUrlsGlobal')) {
        function extractContractUrlsGlobal($items) {
            $urls = [];
            foreach ($items as $item) {
                if (!empty($item['url']) && $item['url'] !== '#') {
                    $urls[] = basename($item['url']);
                }
                if (!empty($item['children'])) {
                    $urls = array_merge($urls, extractContractUrlsGlobal($item['children']));
                }
            }
            return $urls;
        }
    }
    $contractUrls = extractContractUrlsGlobal($menu_data);
}

// FIX: los core files siempre están permitidos por contrato
$allowedByContract = false;

if ($isCoreFile || empty($contractUrls)) {
    $allowedByContract = true;
} else {
    if (in_array($currentScript, $contractUrls)) {
        $allowedByContract = true;
    } else {
        // Validación inteligente por prefijo de submódulos
        foreach ($contractUrls as $cUrl) {
            $parts = explode('_', $cUrl);
            if (count($parts) > 1) {
                array_pop($parts);
                $prefix = implode('_', $parts) . '_';
                if (strpos($currentScript, $prefix) === 0) {
                    $allowedByContract = true;
                    break;
                }
            }
        }
    }
}

if (!$allowedByContract) {
    denyAccessAndLog($pdo, "Módulo bloqueado. La licencia contratada por la empresa no incluye este servicio.", $currentScript);
}

// ======================================================================
// 6. VALIDACIÓN DE ROLES
// FIX: los core files Y los admins bypasean la validación de roles
// ======================================================================

// Bypass total para administradores
if (isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1) {
    return;
}

// FIX: Bypass para archivos core — no necesitan estar en el JSON de permisos del usuario
if ($isCoreFile) {
    return;
}

try {
    $stmtAcceso = $pdo->prepare("SELECT permisos, timestamp FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
    $stmtAcceso->execute([$_SESSION['usuario_id']]);
    $accesoRow = $stmtAcceso->fetch(PDO::FETCH_ASSOC);

    if (!$accesoRow) {
        denyAccessAndLog($pdo, "Usuario sin configuración de roles vigente.", "Global");
    }

    // 7. CONTROL DE INTEGRIDAD (TIMESTAMP)
    $dbTimestamp = $accesoRow['timestamp'];

    if (!isset($_SESSION['permisos_timestamp'])) {
        $_SESSION['permisos_timestamp'] = $dbTimestamp;
    } elseif ($_SESSION['permisos_timestamp'] !== $dbTimestamp) {
        forceUpdateAndLog($pdo, "Integridad: Cambio de permisos durante la sesión.");
    }

    // 8. CONTROL DE URL EN ROLES (WHITELIST)
    $permisosData = json_decode($accesoRow['permisos'], true);
    $allowedUrls  = $permisosData['allowed_urls'] ?? [];

    $isAllowed = false;
    foreach ($allowedUrls as $url) {
        if (basename($url) === $currentScript) {
            $isAllowed = true;
            break;
        }
    }

    if (!$isAllowed) {
        denyAccessAndLog($pdo, "URL no autorizada en tu perfil de usuario.", $currentScript);
    }

} catch (Exception $e) {
    header("Location: login.php?error=" . urlencode("Error de verificación de seguridad."));
    exit();
}
?>
