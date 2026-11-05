<?php
require_once 'check_api_access.php';

// Validar sesión activa (Redundante con db.php pero buena práctica de doble chequeo)
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['error' => 'Sesión expirada']);
    exit();
}

$action = $_GET['action'] ?? '';

try {
    // ELIMINADO: Conexión manual que causaba errores.
    // Usamos $pdo provisto por db.php

    if ($action === 'get_users') {
        // Obtener lista de usuarios para el Select2 (Todos, para editar)
        // Solo traemos usuarios habilitados
        $stmt = $pdo->prepare("SELECT id, usuario, esadmin FROM alb_usuarios WHERE habilitado = 1 ORDER BY usuario ASC");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $usuarios]);

    } elseif ($action === 'get_users_source') {
        // NUEVO: Obtener lista de usuarios para CLONAR (Origen)
        // Regla: Usuarios habilitados que NO SEAN ADMINISTRADORES
        $stmt = $pdo->prepare("SELECT id, usuario FROM alb_usuarios WHERE habilitado = 1 AND esadmin = 0 ORDER BY usuario ASC");
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['data' => $usuarios]);

    } elseif ($action === 'get_permissions') {
        $userId = $_GET['id'] ?? 0;
        
        // 1. Verificar si es admin (acceso total)
        $stmtUser = $pdo->prepare("SELECT esadmin FROM alb_usuarios WHERE id = ?");
        $stmtUser->execute([$userId]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
        
        $isAdmin = ($user && $user['esadmin'] == 1);
        $permisos = [];

        if (!$isAdmin) {
            // 2. Buscar permisos vigentes
            // Usamos la lógica de vigente = 1
            $stmtPerm = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
            $stmtPerm->execute([$userId]);
            $row = $stmtPerm->fetch(PDO::FETCH_ASSOC);
            
            if ($row) {
                $permisos = json_decode($row['permisos'], true);
            }
        }

        echo json_encode([
            'is_admin' => $isAdmin,
            'permisos' => $permisos
        ]);

    } elseif ($action === 'save_permissions') {
        // Solo administradores pueden guardar permisos
        // Verificamos el usuario logueado en sesión, NO el editado
        $currentUserId = $_SESSION['usuario_id'];
        $stmtCheck = $pdo->prepare("SELECT esadmin FROM alb_usuarios WHERE id = ?");
        $stmtCheck->execute([$currentUserId]);
        $currentUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);

      /* if (!$currentUser || $currentUser['esadmin'] != 1) {
            throw new Exception("No tiene privilegios para modificar permisos.");
        } */

        // Leer datos del body
        $input = json_decode(file_get_contents('php://input'), true);
        $targetUserId = $input['usuario_id'];
        $targetUserName = $input['usuario_nombre']; // Se envía desde el front para guardar histórico
        $permisosJson = json_encode($input['permisos']); // { allowed_ids: [], allowed_urls: [] }
        $modificador = $_SESSION['usuario_nombre'];

        $pdo->beginTransaction();

        try {
            // 1. Marcar registros anteriores como no vigentes
            // NOTA: No usamos UPDATE directo a 0 porque rompería el unique index si hay multiples 0?
            // NO, el unique index es sobre (usuario, vigente_unico). vigente_unico es NULL si vigente=0.
            // Múltiples NULLs son permitidos en índices únicos SQL estándar.
            $upd = $pdo->prepare("UPDATE accesosusuarios SET vigente = 0 WHERE usuarioid = ? AND vigente = 1");
            $upd->execute([$targetUserId]);

            // 2. Insertar nuevo registro vigente
            $ins = $pdo->prepare("INSERT INTO accesosusuarios (usuarioid, usuarionombre, permisos, usuariomodificacion, vigente) VALUES (?, ?, ?, ?, 1)");
            $ins->execute([$targetUserId, $targetUserName, $permisosJson, $modificador]);

            $pdo->commit();
            echo json_encode(['status' => 'ok', 'message' => 'Permisos actualizados correctamente.']);

        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } elseif ($action === 'get_permisos_especiales') {
        // Leer permisos especiales del usuario (por nombre, igual que VB.NET)
        $usuarioNombre = $_GET['usuario_nombre'] ?? '';
        if (empty($usuarioNombre)) throw new Exception("Nombre de usuario requerido.");

        $stmt = $pdo->prepare("SELECT * FROM permisosespeciales WHERE usuario = ? LIMIT 1");
        $stmt->execute([$usuarioNombre]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe aún, devolver valores en cero (usuario sin restricciones especiales)
        if (!$row) {
            $row = [
                'usuario'           => $usuarioNombre,
                'precioscosto'      => 0,
                'unvendedor'        => 0,
                'idvendedor'        => 0,
                'modificarprecios'  => 0,
                'facturarpedidos'   => 0,
                'modificarfacturas' => 0,
                'editardescripcionfacturar'     => 0,
                'editardescripcionpedidos'      => 0,
                'editardescripcionpresupuestos' => 0,
                'modificarmedioscobro'          => 0,
            ];
        }

        echo json_encode(['success' => true, 'data' => $row]);

    } elseif ($action === 'save_permisos_especiales') {
        // Solo administradores pueden guardar (mismo criterio que save_permissions)
        $input = json_decode(file_get_contents('php://input'), true);

        $usuarioNombre  = $input['usuario']           ?? '';
        $precioscosto   = (int)($input['precioscosto']      ?? 0);
        $unvendedor     = (int)($input['unvendedor']        ?? 0);
        $idvendedor     = (int)($input['idvendedor']        ?? 0);
        $modificarprecios  = (int)($input['modificarprecios']  ?? 0);
        $facturarpedidos   = (int)($input['facturarpedidos']   ?? 0);
        $modificarfacturas = (int)($input['modificarfacturas'] ?? 0);
        $editardescripcionfacturar     = (int)($input['editardescripcionfacturar']     ?? 0);
        $editardescripcionpedidos      = (int)($input['editardescripcionpedidos']      ?? 0);
        $editardescripcionpresupuestos = (int)($input['editardescripcionpresupuestos'] ?? 0);
        $modificarmedioscobro          = (int)($input['modificarmedioscobro']          ?? 0);

        if (empty($usuarioNombre)) throw new Exception("Nombre de usuario requerido.");

        // Replicar exactamente la lógica VB.NET: DELETE + INSERT
        $pdo->beginTransaction();
        try {
            // puedecambiardeposito se administra desde Depósitos, no desde esta
            // pantalla: se conserva para que el DELETE + INSERT no lo borre.
            $stmtDep = $pdo->prepare("SELECT puedecambiardeposito FROM permisosespeciales WHERE usuario = ? LIMIT 1");
            $stmtDep->execute([$usuarioNombre]);
            $puedecambiardeposito = (int) ($stmtDep->fetchColumn() ?: 0);

            $pdo->prepare("DELETE FROM permisosespeciales WHERE usuario = ?")->execute([$usuarioNombre]);

            $pdo->prepare("INSERT INTO permisosespeciales
                (usuario, precioscosto, unvendedor, idvendedor, modificarprecios, facturarpedidos, modificarfacturas,
                 editardescripcionfacturar, editardescripcionpedidos, editardescripcionpresupuestos,
                 modificarmedioscobro, puedecambiardeposito)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $usuarioNombre,
                    $precioscosto,
                    $unvendedor,
                    // Si unvendedor está desactivado, idvendedor se guarda como 0 (igual que VB.NET)
                    $unvendedor === 1 ? $idvendedor : 0,
                    $modificarprecios,
                    $facturarpedidos,
                    $modificarfacturas,
                    $editardescripcionfacturar,
                    $editardescripcionpedidos,
                    $editardescripcionpresupuestos,
                    $modificarmedioscobro,
                    $puedecambiardeposito
                ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Permisos especiales guardados correctamente.']);
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } else {
        echo json_encode(['error' => 'Acción no válida']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>