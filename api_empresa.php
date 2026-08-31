<?php
/**
 * api_empresa.php - BACKEND DE DATOS DE LA EMPRESA
 * Gestión del registro único (ID 1) en tabla datosempresa.
 */
require_once 'check_api_access.php';

/**
 * Valida formato (11 dígitos) y dígito verificador (módulo 11) de un CUIT
 * argentino. Antes solo se chequeaba que no viniera vacío, y un CUIT mal
 * tipeado recién se detectaba cuando ARCA rechazaba la autorización.
 */
function cuitEsValido(string $cuit): bool {
    if (!preg_match('/^\d{11}$/', $cuit)) return false;

    $digitos = str_split($cuit);
    $multiplicadores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
    $suma = 0;
    for ($i = 0; $i < 10; $i++) {
        $suma += (int)$digitos[$i] * $multiplicadores[$i];
    }
    $verificador = 11 - ($suma % 11);
    if ($verificador == 11) $verificador = 0;
    if ($verificador == 10) return false;

    return $verificador === (int)$digitos[10];
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_datos':
            // Obtener el registro ID 1 (único)
            $stmt = $pdo->query("SELECT * FROM datosempresa WHERE id = 1 LIMIT 1");
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                // Si no existe, inicializar vacío (aunque debería existir por instalación)
                echo json_encode(['success' => true, 'data' => null]);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;

        case 'save_datos':
            $input = $_POST; // Usamos POST directo para facilitar

            // Validaciones básicas
            if (empty($input['razon'])) throw new Exception("La Razón Social es obligatoria.");
            if (empty($input['cuit'])) throw new Exception("El CUIT es obligatorio.");
            if (!cuitEsValido($input['cuit'])) throw new Exception("El CUIT ingresado no es válido (deben ser 11 números y el dígito verificador debe coincidir).");

            // Verificar si existe ID 1
            $existe = $pdo->query("SELECT COUNT(*) FROM datosempresa WHERE id = 1")->fetchColumn();

            if ($existe) {
                // UPDATE
                $sql = "UPDATE datosempresa SET 
                        razon = ?, domicilio = ?, localidad = ?, provincia = ?, cuit = ?, 
                        condicioniva = ?, telefono = ?, email = ?, web = ?, numiibb = ?, fechainicioact = ?, puntodeventa = ?
                        WHERE id = 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $input['razon'],
                    $input['domicilio'],
                    $input['localidad'],
                    $input['provincia'],
                    $input['cuit'],
                    $input['condicioniva'],
                    $input['telefono'],
                    $input['email'],
                    $input['web'],
                    $input['numiibb'],
                    !empty($input['fechainicioact']) ? $input['fechainicioact'] : null,
                    $input['puntoventa']
                ]);
            } else {
                // INSERT (Caso raro, inicialización)
                $sql = "INSERT INTO datosempresa (id, razon, domicilio, localidad, provincia, cuit, condicioniva, telefono, email, web, numiibb, fechainicioact, puntodeventa) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $input['razon'],
                    $input['domicilio'],
                    $input['localidad'],
                    $input['provincia'],
                    $input['cuit'],
                    $input['condicioniva'],
                    $input['telefono'],
                    $input['email'],
                    $input['web'],
                    $input['numiibb'],
                    !empty($input['fechainicioact']) ? $input['fechainicioact'] : null,
                    $input['puntoventa']
                ]);
            }

            echo json_encode(['success' => true, 'message' => 'Datos de la empresa actualizados correctamente.']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>