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

        // --- UPDATE MASIVO EN DOS PASOS ---
        // OJO: en un UPDATE de una sola tabla, MySQL/MariaDB asigna las columnas de izquierda a
        // derecha y cada expresión ve los valores YA modificados. Si en la misma sentencia se hace
        // "siniva = siniva * f, precio = siniva * f * (1+iva)", el precio queda aumentado dos veces
        // (10% -> 21%). Por eso: 1) se aumentan solo los netos; 2) IVA y precio final se recalculan
        // a partir de los netos nuevos.
        $netos = ['sinivacosto', 'siniva'];
        for ($i = 2; $i <= 8; $i++) $netos[] = "siniva{$i}";

        // Impuesto interno: monto fijo por unidad. Solo se aumenta si se pidió (casilla en la pantalla).
        if (!empty($input['impint'])) $netos[] = 'ImpInt';
        $sqlNetos = "UPDATE alb_productos SET "
            . implode(', ', array_map(fn($c) => $c === 'ImpInt' ? "ImpInt = ROUND(IFNULL(ImpInt, 0) * :factor, 2)" : "$c = ROUND($c * :factor, 2)", $netos))
            . " $whereClause";

        // [neto, iva, final] por cada lista y el costo
        $grupos = [['sinivacosto', 'ivacosto', 'preciocosto'], ['siniva', 'iva1', 'precio']];
        for ($i = 2; $i <= 8; $i++) $grupos[] = ["siniva{$i}", "iva{$i}", "precio{$i}"];
        $sets = [];
        foreach ($grupos as [$n, $iv, $fin]) {
            $sets[] = "$iv = ROUND($n * (IFNULL(poriva, 0) / 100), 2)";
            $sets[] = "$fin = ROUND($n * (1 + IFNULL(poriva, 0) / 100) + IFNULL(ImpInt, 0), 2)";
        }
        $sqlFinales = "UPDATE alb_productos SET " . implode(', ', $sets) . " $whereClause";

        $pdo->beginTransaction();
        $stmt = $pdo->prepare($sqlNetos);
        $stmt->execute($params + [':factor' => $factor]);
        $afectados = $stmt->rowCount();
        $pdo->prepare($sqlFinales)->execute($params);
        $pdo->commit();

        echo json_encode([
            'success' => true, 
            'message' => "Precios actualizados correctamente.",
            'rows_affected' => $afectados
        ]);
        exit;
    }

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>