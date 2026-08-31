<?php
/**
 * api_uns.php - BACKEND UNIDADES DE NEGOCIO
 * Gestión de tabla 'unidadesdenegocio'.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. LISTAR
    if ($action === 'list') {
        $stmt = $pdo->query("SELECT id, nombre FROM unidadesdenegocio ORDER BY nombre ASC");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    // 2. CREAR
    if ($action === 'create') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido");
        
        $nombre = trim($_POST['nombre'] ?? '');
        if (empty($nombre)) throw new Exception("El nombre es obligatorio.");

        // Validar duplicados
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM unidadesdenegocio WHERE nombre = ?");
        $stmtCheck->execute([$nombre]);
        if ($stmtCheck->fetchColumn() > 0) throw new Exception("Ya existe una unidad con ese nombre.");

        $stmt = $pdo->prepare("INSERT INTO unidadesdenegocio (nombre) VALUES (?)");
        $stmt->execute([$nombre]);

        echo json_encode(['success' => true]);
        exit;
    }

    // 3. MODIFICAR
    if ($action === 'update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido");

        $id = intval($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');

        if ($id <= 0 || empty($nombre)) throw new Exception("Datos inválidos.");

        // Validar duplicados (excluyendo el propio registro)
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM unidadesdenegocio WHERE nombre = ? AND id <> ?");
        $stmtCheck->execute([$nombre, $id]);
        if ($stmtCheck->fetchColumn() > 0) throw new Exception("Ya existe otra unidad con ese nombre.");

        $stmt = $pdo->prepare("UPDATE unidadesdenegocio SET nombre = ? WHERE id = ?");
        $stmt->execute([$nombre, $id]);

        echo json_encode(['success' => true]);
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}