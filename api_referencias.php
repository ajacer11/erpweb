<?php
/**
 * api_referencias.php - Gestión de códigos de referencia (Equivalencias)
 * Lógica espejo de VB.NET para tabla 'referenciasproductos'
 * Actualizado: Soporte para búsqueda por 'codigoproveedor' (opcional)
 */

// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // --- RESOLVER CÓDIGO REAL ---
    // Parámetros GET: 
    // - code: El código a resolver
    // - search_prov: (opcional) si es 1, busca primero por codigoproveedor en alb_productos
    if ($method === 'GET' && $action === 'resolve') {
        $codigoInput = $_GET['code'] ?? '';
        $buscarPorProveedor = ($_GET['search_prov'] ?? '0') === '1';

        if (!$codigoInput) {
            echo json_encode(['success' => false, 'error' => 'Código vacío']);
            exit;
        }

        $codigoReal = $codigoInput; // Por defecto es el mismo

        // 1. LÓGICA NUEVA: Si el usuario activó el toggle de PROVEEDOR
        if ($buscarPorProveedor) {
            $stmtProv = $pdo->prepare("SELECT codigo FROM alb_productos WHERE codigoproveedor = ? LIMIT 1");
            $stmtProv->execute([$codigoInput]);
            $resProv = $stmtProv->fetch();
            
            if ($resProv) {
                $codigoReal = $resProv['codigo'];
            }
            // Nota: Si no lo encuentra por proveedor, el flujo sigue abajo para intentar por alias (redundancia de seguridad)
        }

        // 2. LÓGICA ACTUAL: Buscar en la tabla de referencias (alias/códigos viejos)
        // Solo si no se resolvió ya por proveedor o si se busca de modo normal
        if ($codigoReal === $codigoInput) {
            $stmt = $pdo->prepare("SELECT refierea FROM referenciasproductos WHERE codigo = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$codigoInput]);
            $ref = $stmt->fetch();
            
            if ($ref) {
                $codigoReal = $ref['refierea'];
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'codigo_ingresado' => $codigoInput,
                'codigo_real' => $codigoReal,
                'modo_proveedor' => $buscarPorProveedor
            ]
        ]);
        exit;
    }

    // --- MANTENER RESTO DE FUNCIONALIDAD (LISTAR, AGREGAR, ELIMINAR) ---
    if ($method === 'GET' && $action === 'list') {
        $codigo = $_GET['code'] ?? '';
        $stmt = $pdo->prepare("SELECT id, codigo, refierea FROM referenciasproductos WHERE refierea = ?");
        $stmt->execute([$codigo]);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // AGREGAR REFERENCIA
        if ($action === 'add') {
            $codigo = $input['codigo'] ?? '';
            $refierea = $input['refierea'] ?? '';
            
            if (!$codigo || !$refierea) throw new Exception("Faltan datos");

            $check = $pdo->prepare("SELECT id FROM referenciasproductos WHERE codigo = ? AND refierea = ?");
            $check->execute([$codigo, $refierea]);
            if ($check->fetch()) throw new Exception("Esta referencia ya existe.");

            $stmt = $pdo->prepare("INSERT INTO referenciasproductos (codigo, refierea) VALUES (?, ?)");
            $stmt->execute([$codigo, $refierea]);
            
            echo json_encode(['success' => true]);
            exit;
        }

        // ELIMINAR REFERENCIA
        if ($action === 'delete') {
            $id = $input['id'] ?? 0;
            if (!$id) throw new Exception("ID inválido");

            $stmt = $pdo->prepare("DELETE FROM referenciasproductos WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true]);
            exit;
        }
    }

    throw new Exception("Acción no válida");

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}