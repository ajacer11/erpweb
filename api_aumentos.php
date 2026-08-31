<?php
/**
 * api_aumentos.php - Backend para Aumentos Masivos de Precios
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $aumentoPorc = floatval($input['aumento'] ?? 0);
        
        // Validaciones básicas
        if ($aumentoPorc == 0) throw new Exception("El porcentaje de aumento no puede ser cero.");

        // Factor de multiplicación (ej: 10% -> 1.10)
        $factor = 1 + ($aumentoPorc / 100);

        // Construcción de filtros dinámicos
        $filtros = [];
        $params = [];

        // Lógica de "Todos" vs Filtros específicos
        if (empty($input['todos'])) {
            if (!empty($input['proveedor'])) {
                $filtros[] = "Proveedor = :proveedor";
                $params[':proveedor'] = $input['proveedor']; // String
            }
            if (!empty($input['rubro'])) {
                $filtros[] = "rubro = :rubro";
                $params[':rubro'] = $input['rubro']; // String
            }
            if (!empty($input['marca'])) {
                $filtros[] = "Marca = :marca";
                $params[':marca'] = $input['marca']; // ID
            }
            if (!empty($input['familia'])) {
                $filtros[] = "familia = :familia";
                $params[':familia'] = $input['familia']; // ID
            }
            
            // Si no marcó "Todos" pero tampoco eligió ningún filtro, detenemos por seguridad
            if (empty($filtros)) {
                throw new Exception("Debe seleccionar al menos un filtro o marcar 'Todos'.");
            }
        }

        $whereClause = !empty($filtros) ? "WHERE " . implode(" AND ", $filtros) : "WHERE id > 0";

        // --- CONSTRUCCIÓN DEL UPDATE MASIVO ---
        // A diferencia del VB que hacía múltiples updates o hardcodeaba el 21%,
        // aquí usamos el poder de SQL para recalcular todo en una sola pasada de forma precisa.
        
        // 1. Actualizar Costos
        // sinivacosto = sinivacosto * factor
        // ivacosto = nuevo_sinivacosto * (poriva / 100)
        // preciocosto = nuevo_sinivacosto + nuevo_ivacosto + ImpInt
        
        $sql = "UPDATE alb_productos SET 
            
            -- Actualización de Costos
            sinivacosto = ROUND(sinivacosto * :factor, 2),
            ivacosto = ROUND((sinivacosto * :factor) * (poriva / 100), 2),
            preciocosto = ROUND((sinivacosto * :factor) * (1 + (poriva / 100)) + ImpInt, 2),

            -- Actualización Lista 1 (Campos: siniva, iva1, precio)
            siniva = ROUND(siniva * :factor, 2),
            iva1 = ROUND((siniva * :factor) * (poriva / 100), 2),
            precio = ROUND((siniva * :factor) * (1 + (poriva / 100)) + ImpInt, 2)
        ";

        // 2. Actualización de Listas 2 a 8 (Loop para generar SQL)
        for ($i = 2; $i <= 8; $i++) {
            $sql .= ", 
            siniva{$i} = ROUND(siniva{$i} * :factor, 2),
            iva{$i} = ROUND((siniva{$i} * :factor) * (poriva / 100), 2),
            precio{$i} = ROUND((siniva{$i} * :factor) * (1 + (poriva / 100)) + ImpInt, 2)";
        }

        $sql .= " $whereClause";
        $params[':factor'] = $factor;

        // Ejecutar
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $afectados = $stmt->rowCount();

        echo json_encode([
            'success' => true, 
            'message' => "Precios actualizados correctamente.",
            'rows_affected' => $afectados
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>