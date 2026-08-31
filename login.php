<?php
/**
 * login.php - Entrada Multi-Tenant con Selector de Base de Datos y Variables de Facturación
 */
require_once 'db_master.php';

if (isset($_GET['reset'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

$error = $_GET['error'] ?? "";
$errorTitle = $error ? 'No pudimos ingresar' : '';
$step = 1;

function normalizarCuitLogin($valor) {
    return preg_replace('/\D/', '', (string)$valor);
}

function cargarEmpresaEnSesion($cuit, array $dbs) {
    $_SESSION['tenant_cuit'] = $cuit;
    $_SESSION['cliente_cuit'] = $cuit;
    $_SESSION['tenant_razon'] = $dbs[0]['nombre'];

    if (count($dbs) === 1) {
        $_SESSION['tenant_db_id'] = $dbs[0]['id'];
        $_SESSION['pdv'] = $dbs[0]['pdv'];
        $_SESSION['modo'] = $dbs[0]['nmodo'];
        $_SESSION['version'] = $dbs[0]['tipoversion'] ?? 1;
    } else {
        unset($_SESSION['tenant_db_id'], $_SESSION['pdv'], $_SESSION['modo'], $_SESSION['version']);
    }
}

function limpiarEmpresaDeSesion() {
    foreach (['tenant_cuit', 'cliente_cuit', 'tenant_db_id', 'tenant_razon', 'pdv', 'modo',
              'version', 'usuario_id', 'usuario_nombre', 'es_admin'] as $clave) {
        unset($_SESSION[$clave]);
    }
}

// Acceso directo por empresa: login.php?cuit=XXXXXXXXXXX
if (isset($_GET['cuit'])) {
    $cuitUrl = normalizarCuitLogin($_GET['cuit']);
    limpiarEmpresaDeSesion();

    if (strlen($cuitUrl) !== 11) {
        $errorTitle = 'Revisá el enlace';
        $error = 'El CUIT de la empresa debe tener 11 números.';
    } else {
        try {
            $dbsUrl = getTenantDatabases($pdo_master, $cuitUrl);
            if (!$dbsUrl) {
                $errorTitle = 'Empresa no encontrada';
                $error = 'No encontramos una empresa habilitada para ese CUIT. Revisá el enlace o ingresalo nuevamente.';
            } else {
                cargarEmpresaEnSesion($cuitUrl, $dbsUrl);
                header('Location: login.php');
                exit;
            }
        } catch (Throwable $e) {
            error_log("login.php: CUIT por URL no disponible ($cuitUrl): " . $e->getMessage());
            $errorTitle = 'Empresa no encontrada';
            $error = 'No pudimos identificar la empresa indicada. Revisá el CUIT o solicitá un nuevo enlace de acceso.';
        }
    }
}

try {
    if (isset($_SESSION['tenant_cuit'])) {
        if (!isset($_SESSION['tenant_db_id'])) {
            $dbs = getTenantDatabases($pdo_master, $_SESSION['tenant_cuit']);
            if (count($dbs) > 1) {
                $step = 2;
            } else if (count($dbs) === 1) {
                // Auto-selección si solo hay una base de datos
                $_SESSION['tenant_db_id'] = $dbs[0]['id'];
                $_SESSION['tenant_razon'] = $dbs[0]['nombre'];
                // Variables de Facturación
                $_SESSION['pdv'] = $dbs[0]['pdv'];
                $_SESSION['modo'] = $dbs[0]['nmodo'];
                $_SESSION['version'] = $dbs[0]['tipoversion'] ?? 1;
                $_SESSION['cliente_cuit'] = $_SESSION['tenant_cuit'];
                $step = 3;
            } else {
                session_destroy();
                header("Location: login.php?error=" . urlencode("Configuración no disponible."));
                exit;
            }
        } else {
            $step = 3;
        }
    }
} catch (Exception $e) {
    error_log('login.php: fallo al recuperar empresa de sesión: ' . $e->getMessage());
    limpiarEmpresaDeSesion();
    $errorTitle = 'No pudimos abrir la empresa';
    $error = 'La configuración de esta empresa no está disponible. Volvé a ingresar el CUIT o contactá a soporte.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        die("Error de seguridad.");
    }

    // PASO 1: Identificación por CUIT
    if (isset($_POST['cuit'])) {
        $cuit = normalizarCuitLogin($_POST['cuit']);
        if (strlen($cuit) !== 11) {
            $errorTitle = 'CUIT incompleto';
            $error = 'Ingresá los 11 números del CUIT, sin puntos ni guiones.';
        } else {
        try {
            $dbs = getTenantDatabases($pdo_master, $cuit);
            if (!empty($dbs)) {
                cargarEmpresaEnSesion($cuit, $dbs);
                header("Location: login.php");
                exit;
            } else {
                $errorTitle = 'Empresa no encontrada';
                $error = 'No encontramos una empresa habilitada para ese CUIT. Revisalo e intentá nuevamente.';
            }
        } catch (Throwable $e) {
            error_log("login.php: CUIT ingresado no disponible ($cuit): " . $e->getMessage());
            $errorTitle = 'Empresa no encontrada';
            $error = 'No pudimos identificar esa empresa. Revisá el CUIT o contactá a soporte.';
        }
        }
    }

    // PASO 2: Selección de Base de Datos
    if (isset($_POST['db_id'])) {
        $db_id = (int) $_POST['db_id'];
        if (!isset($_SESSION['tenant_cuit'])) {
            header("Location: login.php?reset=1");
            exit;
        }
        $cuit = $_SESSION['tenant_cuit'];

        try {
            // Buscamos los datos específicos del registro seleccionado
            $dbInfo = getTenantDatabaseConfig($pdo_master, $cuit, $db_id);
            $dbs = getTenantDatabases($pdo_master, $cuit); // Para obtener el nombre y otros campos

            foreach ($dbs as $d) {
                if ($d['id'] == $db_id) {
                    $_SESSION['tenant_db_id'] = $d['id'];
                    $_SESSION['tenant_razon'] = $d['nombre'];
                    // Variables de Facturación Críticas
                    $_SESSION['pdv'] = $d['pdv'];
                    $_SESSION['modo'] = $d['nmodo'];
                    $_SESSION['cliente_cuit'] = $cuit;
                    $_SESSION['version'] = $d['tipoversion'] ?? 1;

                    header("Location: login.php");
                    exit;
                }
            }
        } catch (Exception $e) {
            error_log('login.php: fallo al seleccionar base de empresa: ' . $e->getMessage());
            $errorTitle = 'No pudimos abrir esta opción';
            $error = 'La sucursal seleccionada no está disponible en este momento. Intentá nuevamente o contactá a soporte.';
        }
    }
}

if (empty($_SESSION['csrf_token']))
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>West IT ERP - Acceso</title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #000c18;
            color: white;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
        }

        .login-card {
            background: #0d1b2a;
            border: 1px solid #1b263b;
            border-radius: 15px;
            width: 400px;
            padding: 35px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
        }

        .text-accent {
            color: #00d4ff;
        }

        .btn-accent {
            background: #00d4ff;
            color: #000;
            font-weight: bold;
            border-radius: 30px;
            border: none;
            padding: 12px;
            transition: 0.3s;
        }

        .btn-accent:hover {
            background: #00b8e6;
            transform: translateY(-2px);
        }

        .form-control {
            background: #1b263b;
            border: 1px solid #415a77;
            color: white;
            border-radius: 10px;
            padding: 12px;
        }

        .form-control:focus {
            background: #1b263b;
            border-color: #00d4ff;
            color: white;
            box-shadow: none;
        }

        .db-option {
            background: #1b263b;
            border: 1px solid #415a77;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: 0.2s;
            width: 100%;
            text-align: left;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .db-option:hover {
            border-color: #00d4ff;
            background: #243b55;
        }

        .login-alert {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 22px;
            padding: 14px;
            border: 1px solid rgba(255, 133, 133, 0.35);
            border-radius: 8px;
            background: rgba(220, 53, 69, 0.12);
            color: #ffd0d0;
        }

        .login-alert i {
            color: #ff8585;
            font-size: 20px;
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <h2 class="fw-bold mb-0">WEST IT <span class="text-accent">ERP</span></h2>
        </div>

        <?php if ($error): ?>
            <div class="login-alert" role="alert">
                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <div class="fw-bold mb-1"><?php echo htmlspecialchars($errorTitle ?: 'No pudimos continuar'); ?></div>
                    <div class="small"><?php echo htmlspecialchars($error); ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="mb-4">
                    <label class="small mb-2 text-white-50 d-block text-center">CUIT DE EMPRESA</label>
                    <input type="text" name="cuit" class="form-control text-center fs-4 fw-bold" placeholder="30XXXXXXXXX"
                        required autofocus autocomplete="off">
                </div>
                <button type="submit" class="btn btn-accent w-100">CONTINUAR</button>
            </form>

        <?php elseif ($step === 2): ?>
            <div class="text-center mb-4 text-white-50 small">Empresa:
                <strong><?php echo $_SESSION['tenant_cuit']; ?></strong></div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <?php
                $dbs = getTenantDatabases($pdo_master, $_SESSION['tenant_cuit']);
                foreach ($dbs as $d):
                    ?>
                    <button type="submit" name="db_id" value="<?php echo $d['id']; ?>" class="db-option">
                        <span>
                            <i class="fas fa-database me-2 text-accent"></i>
                            <strong><?php echo htmlspecialchars($d['nombre']); ?></strong>
                            <small class="d-block text-white-50" style="font-size: 10px">PDV: <?php echo $d['pdv']; ?></small>
                        </span>
                    </button>
                <?php endforeach; ?>
                <div class="text-center mt-3"><a href="?reset=1" class="text-white-50 small">Volver</a></div>
            </form>

        <?php else: ?>
            <div class="text-center mb-4">
                <div class="p-2 rounded border border-info d-inline-block px-3">
                    <i class="fas fa-building me-2 text-accent"></i>
                    <span class="fw-bold text-white"><?php echo htmlspecialchars($_SESSION['tenant_razon']); ?></span>
                </div>
                <div class="mt-2"><a href="?reset=1" class="text-white-50 small">Cambiar</a></div>
            </div>
            <form action="auth.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="text" name="usuario" class="form-control mb-3" placeholder="Usuario" required autofocus>
                <input type="password" name="password" class="form-control mb-3" placeholder="Contraseña" required>
                <button type="submit" class="btn btn-accent w-100">INGRESAR</button>
            </form>
        <?php endif; ?>
    </div>
</body>

</html>
