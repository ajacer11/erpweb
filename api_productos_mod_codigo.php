<?php
/**
 * api_productos_mod_codigo.php - BACKEND
 * Lógica para buscar producto, validar movimientos y actualizar código.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. BUSCAR PRODUCTO Y VALIDAR MOVIMIENTOS
    if ($action === 'search') {
        $codigo = $_GET['codigo'] ?? '';
        if (empty($codigo)) throw new Exception("Código vacío.");

        // A. Obtener descripción
        $stmt = $pdo->prepare("SELECT descripcion FROM alb_productos WHERE codigo = ?");
        $stmt->execute([$codigo]);
        $prod = $stmt->fetch();

        if (!$prod) {
            throw new Exception("Producto no encontrado.");
        }

        // B. Validar Movimientos (Lógica VB BuscarUnDato)
        // Tablas: Lineadeventa (prodcod), ingresosdestock (codigo), movimientosdestock (codigo)
        
        $hasMovements = false;

        // Check Lineadeventa (sum(cant))
        $stmt1 = $pdo->prepare("SELECT COUNT(*) FROM Lineadeventa WHERE prodcod = ?");
        $stmt1->execute([$codigo]);
        if ($stmt1->fetchColumn() > 0) $hasMovements = true;

        if (!$hasMovements) {
            // Check ingresosdestock (ingreso)
            $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM ingresosdestock WHERE codigo = ?");
            $stmt2->execute([$codigo]);
            if ($stmt2->fetchColumn() > 0) $hasMovements = true;
        }

        if (!$hasMovements) {
            // Check movimientosdestock (entradas + salidas)
            $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM movimientosdestock WHERE codigo = ?");
            $stmt3->execute([$codigo]);
            if ($stmt3->fetchColumn() > 0) $hasMovements = true;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'descripcion' => $prod['descripcion'],
                'allow_edit' => !$hasMovements // Si tiene movimientos, NO permite editar
            ]
        ]);
        exit;
    }

    // 2. ACTUALIZAR CÓDIGO (Transacción 5 tablas)
    if ($action === 'update') {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Método no permitido.");

        $codActual = $_POST['codigo_actual'] ?? '';
        $codNuevo = $_POST['nuevo_codigo'] ?? '';

        if (empty($codActual) || empty($codNuevo)) throw new Exception("Datos incompletos.");

        // Validar que el nuevo código no exista
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM alb_productos WHERE codigo = ?");
        $stmtCheck->execute([$codNuevo]);
        if ($stmtCheck->fetchColumn() > 0) {
            throw new Exception("El nuevo código ya existe en la base de datos.");
        }

        $pdo->beginTransaction();

        try {
            // 1. Update alb_productos
            $sql1 = "UPDATE alb_productos SET codigo = ? WHERE codigo = ?";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$codNuevo, $codActual]);
            if ($stmt1->rowCount() === 0) throw new Exception("No se encontró el producto origen al intentar actualizar.");

            // 2. Update Lineadeventa (campo prodcod)
            $sql2 = "UPDATE Lineadeventa SET prodcod = ? WHERE prodcod = ?";
            $stmt2 = $pdo->prepare($sql2);
            $stmt2->execute([$codNuevo, $codActual]);

            // 3. Update ingresosdestock
            $sql3 = "UPDATE ingresosdestock SET codigo = ? WHERE codigo = ?";
            $stmt3 = $pdo->prepare($sql3);
            $stmt3->execute([$codNuevo, $codActual]);

            // 4. Update movimientosdestock
            $sql4 = "UPDATE movimientosdestock SET codigo = ? WHERE codigo = ?";
            $stmt4 = $pdo->prepare($sql4);
            $stmt4->execute([$codNuevo, $codActual]);

            // 5. DELETE referenciasproductos (campo prodcod)
            $sql5 = "DELETE FROM referenciasproductos WHERE codigo = ?";
            $stmt5 = $pdo->prepare($sql5);
            $stmt5->execute([$codNuevo]);

            // 6. Update referenciasproductos (campo prodcod)
            $sql5 = "INSERT INTO referenciasproductos (codigo, refierea) VALUES (?, ?)";
            $stmt5 = $pdo->prepare($sql5);
            $stmt5->execute([$codNuevo, $codNuevo]);

            $pdo->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        exit;
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}