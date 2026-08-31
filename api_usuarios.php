<?php
/**
 * api_usuarios.php - Backend para gestión completa de usuarios
 * Todas las acciones son administrativas (alta, baja, reseteo de clave,
 * cierre forzado de sesión): solo un administrador puede ejecutarlas.
 */
require_once 'check_api_access.php';

if (empty($_SESSION['es_admin']) || (int)$_SESSION['es_admin'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Solo un administrador puede gestionar usuarios.']);
    exit();
}

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true);

try {
    // LISTADO DE USUARIOS (incluye si tiene una caja abierta actualmente)
    if ($action === 'list') {
        $stmt = $pdo->query("
            SELECT u.id, u.usuario, u.esadmin, u.habilitado,
                   cc.id AS caja_control_id, c.descripcion AS caja_nombre
            FROM alb_usuarios u
            LEFT JOIN controlcajas cc ON cc.id = (
                SELECT id FROM controlcajas
                WHERE usuario = u.usuario AND cerradael IS NULL
                ORDER BY id DESC LIMIT 1
            )
            LEFT JOIN cajas c ON c.id = cc.idcaja
            WHERE u.id > 2
            ORDER BY u.usuario ASC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // CREACIÓN
    else if ($action === 'create') {
        $user = trim($input['usuario'] ?? '');
        $pass = $input['passweb'] ?? '';
        $esadmin = (int)($input['esadmin'] ?? 0);

        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&._#\-\(\)\[\]\{\}])[A-Za-z\d@$!%*?&._#\-\(\)\[\]\{\}]{8,}$/';
        if (!preg_match($regex, $pass)) throw new Exception("La contraseña no cumple los requisitos.");

        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM alb_usuarios WHERE usuario = ?");
        $stmtCheck->execute([$user]);
        if ($stmtCheck->fetchColumn() > 0) throw new Exception("El usuario ya existe.");

        $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO alb_usuarios (usuario, passweb, esadmin, habilitado) VALUES (?, ?, ?, 1)");
        $stmt->execute([$user, $hashedPass, $esadmin]);

        echo json_encode(['success' => true]);
    }

    // CAMBIO DE CONTRASEÑA
    else if ($action === 'change_password') {
        $id = (int)$input['id'];
        $pass = $input['passweb'] ?? '';

        $regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&._#\-\(\)\[\]\{\}])[A-Za-z\d@$!%*?&._#\-\(\)\[\]\{\}]{8,}$/';
        if (!preg_match($regex, $pass)) throw new Exception("La contraseña no cumple los requisitos.");

        $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE alb_usuarios SET passweb = ? WHERE id = ?");
        $stmt->execute([$hashedPass, $id]);

        echo json_encode(['success' => true]);
    }

    // TOGGLE ESTADO (HABILITAR/DESHABILITAR)
    else if ($action === 'toggle_status') {
        $id = (int)$input['id'];
        $status = (int)$input['habilitado'];

        // Evitar que el admin se deshabilite a sí mismo
        if ($id === (int)$_SESSION['usuario_id']) {
            throw new Exception("No puede deshabilitar su propia cuenta.");
        }

        $stmt = $pdo->prepare("UPDATE alb_usuarios SET habilitado = ? WHERE id = ?");
        $stmt->execute([$status, $id]);

        echo json_encode(['success' => true]);
    }

    // FORZAR LOGOUT REMOTO
    else if ($action === 'force_logout') {
        $id = (int)$input['id'];

        // Evitar que el admin se cierre su propia sesión usando este botón
        if ($id === (int)$_SESSION['usuario_id']) {
            throw new Exception("No puede forzar el cierre de su propia sesión desde aquí.");
        }

        $stmt = $pdo->prepare("UPDATE alb_usuarios SET logout = 1 WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true]);
    }

    else {
        throw new Exception("Acción no definida.");
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}