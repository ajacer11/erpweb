<?php
/**
 * api_proveedores_add.php - GESTIÓN INTEGRAL DE PROVEEDORES (ALTA/EDICIÓN)
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_cuentas':
            $q = $_GET['q'] ?? '';
            $sql = "SELECT numcuenta as id, descripcion as text FROM plandecuentas 
                    WHERE (numcuenta LIKE ? OR descripcion LIKE ?) AND habilitada = 1 AND imputable = 1 
                    ORDER BY descripcion ASC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$q%", "%$q%"]);
            echo json_encode(['results' => $stmt->fetchAll()]);
            break;

        case 'get_data':
            $id = (int)$_GET['id'];
            // Consulta ajustada a los campos reales de Proveedores
            $sql = "SELECT p.*, 
                           c.descripcion as nombre_cuenta
                    FROM Proveedores p
                    LEFT JOIN plandecuentas c ON p.cuentacontable = c.numcuenta
                    WHERE p.Id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $data = $stmt->fetch();
            if ($data) {
                echo json_encode(['success' => true, 'data' => $data]);
            } else {
                throw new Exception("Proveedor no encontrado.");
            }
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);

            // Verificación de duplicado (Solo en Alta)
            if ($id === 0 && !empty($data['cuit']) && $data['cuit'] !== '0') {
                $stmtCheck = $pdo->prepare("SELECT Id FROM Proveedores WHERE cuit = ?");
                $stmtCheck->execute([$data['cuit']]);
                if ($stmtCheck->fetch()) throw new Exception("El CUIT ya existe registrado.");
            }

            // Campos según el INSERT proporcionado por el usuario
            $fields = [
                'Razon', 'domicilio', 'localidad', 'provincia', 'cp', 'cuit', 'condicion', 
                'telefono', 'celular', 'mail', 'contacto', 'tipodoc', 'habilitado', 
                'tipo', 'web', 'notas', 's1sector', 's1contacto', 's1tel', 's1celu', 's1mail', 
                's2sector', 's2contacto', 's2tel', 's2celu', 's2mail', 
                's3sector', 's3contacto', 's3tel', 's3celu', 's3mail', 
                'provmerca', 'cuentacontable'
            ];

            if ($id > 0) {
                // UPDATE
                $setPart = implode('=?, ', $fields) . '=?';
                $sql = "UPDATE Proveedores SET $setPart WHERE Id = ?";
                $stmt = $pdo->prepare($sql);
                $values = [];
                foreach($fields as $f) $values[] = $data[$f] ?? '';
                $values[] = $id;
            } else {
                // INSERT - Fiel al formato solicitado
                $placeholders = implode(', ', array_fill(0, count($fields), '?'));
                $sql = "INSERT INTO Proveedores (" . implode(', ', $fields) . ") VALUES ($placeholders)";
                $stmt = $pdo->prepare($sql);
                $values = [];
                foreach($fields as $f) $values[] = $data[$f] ?? '';
            }

            if ($stmt->execute($values)) {
                echo json_encode(['success' => true]);
            } else {
                throw new Exception("Error en la operación de base de datos.");
            }
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}