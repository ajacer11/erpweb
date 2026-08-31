<?php
/**
 * auth.php - PROCESAMIENTO DE LOGIN ESTRICTO (SOLO HASH WEB)
 * Actualizado: Redirección dinámica al Dashboard según versión de contrato
 */
require_once 'db_master.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        logSecurityEvent($pdo_master, $_SESSION['tenant_cuit'] ?? 'N/A', 'SYSTEM', 'CSRF_FAILURE', 'FAIL');
        die("Error de seguridad.");
    }

    $usuario = trim($_POST['usuario'] ?? '');
    if (isBlocked($pdo_master, $usuario, 'ACCOUNT')) {
        header("Location: login.php?error=" . urlencode("Usuario bloqueado temporalmente por seguridad."));
        exit;
    }

    if (!isset($_SESSION['tenant_cuit'])) {
        header("Location: login.php?error=" . urlencode("Identificación de empresa requerida."));
        exit;
    }

    require_once 'db.php'; // Conecta a la base de datos del cliente ($pdo)
    $password = $_POST['password'] ?? '';

    try {
        // Añadimos validación del campo 'habilitado'
        $stmt = $pdo->prepare("SELECT id, usuario, passweb, esadmin, habilitado FROM alb_usuarios WHERE usuario = :user LIMIT 1");
        $stmt->execute([':user' => $usuario]);
        $userRow = $stmt->fetch();

        $loginValido = false;

        if ($userRow && !empty($userRow['passweb'])) {
            // Validar primero si el usuario está habilitado
            if ((int)$userRow['habilitado'] === 0) {
                header("Location: login.php?error=" . urlencode("Su cuenta ha sido deshabilitada. Contacte al administrador."));
                exit;
            }

            if (password_verify($password, $userRow['passweb'])) {
                $loginValido = true;
            }
        }

        if ($loginValido) {
            
            // LLEVAR EL VALOR DE LOGOUT A 0 PARA NO VOLVER A DESLOGUEARLO
            try {
                $stmtReset = $pdo->prepare("UPDATE alb_usuarios SET logout = 0 WHERE id = ?");
                $stmtReset->execute([$userRow['id']]);
            } catch (Exception $e) {
                // Falla silenciosa permitida, no interrumpe login si no existe la columna temporalmente
                error_log("auth.php: fallo al resetear logout para usuario {$userRow['id']}: " . $e->getMessage());
            }

            session_regenerate_id(true); 
            
            $_SESSION['usuario_id'] = $userRow['id'];
            $_SESSION['usuario_nombre'] = $userRow['usuario'];
            $_SESSION['es_admin'] = $userRow['esadmin'];
            
            // LOG DE SEGURIDAD MASTER (Global)
            logSecurityEvent($pdo_master, $_SESSION['tenant_cuit'], $usuario, 'LOGIN', 'SUCCESS');
            
            // LOG DE AUDITORÍA TENANT (Tabla 'login')
            try {
                // 1. Obtener IP y Host
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $ipPublica = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $ip; 
                // En Localhost devuelve el nombre de tu PC. En Web, el host del proveedor de internet.
                $host = gethostbyaddr($ip) ?: $ip;
                
                // 2. Detectar Sistema Operativo y Navegador para 'usuariopc'
                $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $os = 'Desconocido';
                if (stripos($ua, 'Windows') !== false) $os = 'Windows';
                elseif (stripos($ua, 'Android') !== false) $os = 'Android';
                elseif (stripos($ua, 'iPhone') !== false) $os = 'iOS';
                elseif (stripos($ua, 'Mac') !== false) $os = 'Mac';
                elseif (stripos($ua, 'Linux') !== false) $os = 'Linux';

                $browser = 'Web';
                if (stripos($ua, 'Chrome') !== false) $browser = 'Chrome';
                elseif (stripos($ua, 'Firefox') !== false) $browser = 'Firefox';
                elseif (stripos($ua, 'Safari') !== false) $browser = 'Safari';
                elseif (stripos($ua, 'Edge') !== false) $browser = 'Edge';

                $infoPC = "$os - $browser"; // Ej: "Windows - Chrome"

                // Insertar en tabla login del cliente
                $stmtLog = $pdo->prepare("INSERT INTO login (usuario, fecha, hora, ip, usuariopc, ippublica, host) VALUES (?, CURDATE(), CURTIME(), ?, ?, ?, ?)");
                $stmtLog->execute([
                    $userRow['usuario'],
                    $ip,
                    substr($infoPC, 0, 100), // Limitamos a 100 chars por seguridad
                    $ipPublica,
                    $host
                ]);
            } catch (Exception $ex) {
                // Si falla el log, no impedimos el ingreso, pero lo registramos en error log de PHP
                error_log("Error al registrar login en tabla tenant: " . $ex->getMessage());
            }
            
            // REDIRECCIÓN DINÁMICA AL DASHBOARD CORRECTO
            // Obtenemos el tipoversion real directo de la base master para evitar fallos de sesión
            $dbConfig = getTenantDatabaseConfig($pdo_master, $_SESSION['tenant_cuit'], $_SESSION['tenant_db_id'] ?? null);
            
            // Extraemos la versión (si viene nula o vacía, asume 1)
            $version = (!empty($dbConfig['tipoversion'])) ? $dbConfig['tipoversion'] : 1;
            
            // Forzamos la actualización de la sesión para que menú y seguridad lo tomen bien
            $_SESSION['version'] = $version; 
            
            // Construimos el nombre del dashboard (ej: dashboard1.php, dashboard2.php)
            $dashboard = 'dashboard' . $version . '.php';
            
            // Redirige al index enviando el módulo a cargar por GET (index.php?p=dashboardX.php)
            header("Location: index.php?p=" . urlencode($dashboard));
            exit;
        }

        logSecurityEvent($pdo_master, $_SESSION['tenant_cuit'], $usuario, 'LOGIN', 'FAIL');
        usleep(500000); 

        header("Location: login.php?error=" . urlencode("Credenciales inválidas."));
        exit;

    } catch (PDOException $e) {
        logSecurityEvent($pdo_master, $_SESSION['tenant_cuit'], $usuario, 'DB_ERROR', $e->getMessage());
        header("Location: login.php?error=" . urlencode("Servicio no disponible momentáneamente."));
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}