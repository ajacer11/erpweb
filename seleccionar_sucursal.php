<?php
/**
 * seleccionar_sucursal.php - Selector intermedio
 * Solo se accede si un CUIT tiene múltiples registros (sucursales) habilitados.
 */
session_start();

// Seguridad: Si no hay sucursales en cola, volver al login
if (!isset($_SESSION['sucursales_temp']) || empty($_SESSION['sucursales_temp'])) {
    header("Location: login.php");
    exit;
}

// Procesar selección
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sucursal_id'])) {
    $id_sel = $_POST['sucursal_id'];
    $seleccionada = null;

    // Buscar la sucursal elegida en el array temporal (seguridad: no volver a consultar DB)
    foreach ($_SESSION['sucursales_temp'] as $suc) {
        if ($suc['id'] == $id_sel) {
            $seleccionada = $suc;
            break;
        }
    }

    if ($seleccionada) {
        // --- FUNCIÓN DE PARSEO (Duplicada o incluir login_helpers.php si prefieres) ---
        function parseStr($str)
        {
            $c = [];
            foreach (explode(';', $str) as $p) {
                if (strpos($p, '=') !== false) {
                    [$k, $v] = explode('=', $p, 2);
                    $c[strtolower(trim($k))] = trim($v);
                }
            }
            return ['host' => $c['server'] ?? '', 'db' => $c['database'] ?? '', 'user' => $c['user id'] ?? '', 'pass' => $c['password'] ?? '', 'port' => $c['port'] ?? 3306];
        }

        // Guardar configuración FINAL en sesión
        $_SESSION['db_config'] = parseStr($seleccionada['cadenaconec']);
        $_SESSION['cliente_nombre'] = $seleccionada['nombre'] . " (" . $seleccionada['pdv'] . ")";
        $_SESSION['cliente_cuit'] = $_SESSION['cliente_cuit_temp'];
        $_SESSION['pdv'] = $seleccionada['pdv'];
        $_SESSION['modo'] = $seleccionada['nmodo'];

        if (!empty($seleccionada['logoA4'])) {
            $_SESSION['cliente_logo'] = 'data:image/png;base64,' . base64_encode($seleccionada['logoA4']);
        }

        // Limpiar temporales
        unset($_SESSION['sucursales_temp']);
        unset($_SESSION['cliente_cuit_temp']);

        // Ir al login final
        header("Location: login.php");
        exit;
    }
}

$sucursales = $_SESSION['sucursales_temp'];
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seleccionar Sucursal - WEST IT</title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="stylesglobal.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: radial-gradient(circle at center, #001f3f 0%, #000000 100%);
        }

        .select-card {
            width: 100%;
            max-width: 450px;
            background: #001020;
            border: 1px solid var(--accent);
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 0 20px rgba(0, 255, 255, 0.1);
        }

        .sucursal-option {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #334455;
            color: #fff;
            cursor: pointer;
            transition: 0.2s;
        }

        .sucursal-option:hover {
            background: var(--accent);
            color: #000;
            border-color: var(--accent);
        }

        .sucursal-option:checked+label {
            background: var(--accent);
            color: #000;
        }
    </style>
</head>

<body>

    <div class="select-card fade-in">
        <h5 class="text-white text-center mb-2">SELECCIONE SUCURSAL</h5>
        <p class="text-center text-muted small mb-4">La empresa tiene múltiples puntos de venta habilitados.</p>

        <form method="POST">
            <div class="d-grid gap-2 mb-4">
                <?php foreach ($sucursales as $suc): ?>
                    <button type="submit" name="sucursal_id" value="<?= $suc['id'] ?>"
                        class="btn btn-outline-secondary text-start p-3 text-white sucursal-option position-relative">
                        <div class="fw-bold"><?= htmlspecialchars($suc['nombre']) ?></div>
                        <div class="small opacity-75">
                            <i class="fas fa-map-marker-alt me-1 text-accent"></i>
                            <?= htmlspecialchars($suc['direccion'] . ", " . $suc['localidad']) ?>
                        </div>
                        <span class="position-absolute top-50 end-0 translate-middle-y me-3">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="text-center">
                <a href="login.php?reset=1" class="btn btn-sm btn-link text-muted text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Volver al inicio
                </a>
            </div>
        </form>
    </div>

</body>

</html>