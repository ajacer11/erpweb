<?php
/**
 * api_puestos_cobro.php - LÓGICA DE NEGOCIO PARA CAJAS / PUESTOS DE COBRO
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $sql = "SELECT id, descripcion, numcaja, estado, usuarioactivo FROM cajas ORDER BY numcaja ASC";
            $res = $pdo->query($sql)->fetchAll();
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'get':
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT id, descripcion, numcaja FROM cajas WHERE id = ?");
            $stmt->execute([$id]);
            $caja = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $caja]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $descripcion = trim($data['descripcion'] ?? '');
            $numcaja = (int)($data['numcaja'] ?? 0);

            if (empty($descripcion)) throw new Exception("La descripción no puede estar vacía.");
            if ($numcaja <= 0) throw new Exception("El número de caja debe ser mayor a cero.");

            // Validar que no exista el mismo número de caja en otro registro
            $checkNum = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE numcaja = ? AND id != ?");
            $checkNum->execute([$numcaja, $id]);
            if ($checkNum->fetchColumn() > 0) {
                throw new Exception("El número de caja $numcaja ya está asignado a otro puesto.");
            }

            if ($id > 0) {
                // UPDATE (Solo actualizamos descripción y número, el estado lo maneja la operativa)
                $sql = "UPDATE cajas SET descripcion = ?, numcaja = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$descripcion, $numcaja, $id]);
            } else {
                // INSERT (Nace cerrada por defecto, saldo 0)
                $sql = "INSERT INTO cajas (descripcion, numcaja, estado, saldo) VALUES (?, ?, 0, 0.00)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$descripcion, $numcaja]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)$data['id'];

            // 1. Validar que no tenga medios de pago asociados
            $checkPagos = $pdo->prepare("SELECT COUNT(*) FROM mediosdepago WHERE puestoasociado = ?");
            $checkPagos->execute([$id]);
            if ($checkPagos->fetchColumn() > 0) {
                throw new Exception("No se puede eliminar el puesto porque tiene Medios de Pago asociados. Desvincúlelos primero.");
            }

            // 2. Validar que no esté abierta
            $checkEstado = $pdo->prepare("SELECT estado FROM cajas WHERE id = ?");
            $checkEstado->execute([$id]);
            if ($checkEstado->fetchColumn() == 1) {
                throw new Exception("No se puede eliminar un puesto de cobro que actualmente se encuentra ABIERTO.");
            }

            // Si pasa las validaciones, procedemos a borrar
            $pdo->prepare("DELETE FROM cajas WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception("Acción no reconocida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}