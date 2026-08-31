<?php
/**
 * api_certificados.php - LÓGICA DE NEGOCIO PARA CERTIFICADOS ARCA
 * Genera el CSR en memoria y procesa la subida del CRT a la carpeta dinámica del tenant.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    // 1. Obtener CUIT del tenant de manera robusta (Sesión o Base de Datos)
    $cuit_tenant = $_SESSION['cliente_cuit'] ?? '';
    if (empty($cuit_tenant)) {
        $stmt = $pdo->query("SELECT cuit FROM configuracion ORDER BY id DESC LIMIT 1");
        $cuit_tenant = $stmt->fetchColumn();
    }
    
    // Limpiar guiones o espacios para asegurar una ruta y nombre limpio
    $cuit_tenant = preg_replace('/[^0-9]/', '', (string)$cuit_tenant);

    if (empty($cuit_tenant)) {
        throw new Exception("No se pudo determinar el CUIT de la empresa. Verifique la configuración general.");
    }

    // 2. Definir ruta dinámica estricta según requerimiento: ARCA/{cuit}/prod/
    $directorio_destino = __DIR__ . '/ARCA/' . $cuit_tenant . '/prod/';

    switch ($action) {
        
        case 'generate_csr':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $cuit = trim($data['cuit'] ?? '');
            $alias = trim($data['alias'] ?? '');
            $nombre_salida = trim($data['salida'] ?? 'solicitud');

            if (empty($cuit) || empty($alias)) {
                throw new Exception("El CUIT y el Alias son obligatorios.");
            }

            // La llave privada debería estar idealmente en la misma carpeta ARCA del tenant. 
            // Buscamos ahí primero, si no, hacemos fallback a la raíz por compatibilidad.
            $ruta_clave_privada = $directorio_destino . 'privada.key';
            if (!file_exists($ruta_clave_privada)) {
                $ruta_clave_privada = __DIR__ . '/privada.key';
                if (!file_exists($ruta_clave_privada)) {
                    throw new Exception("Falta el archivo de clave privada en el servidor. Contacte a soporte.");
                }
            }

            // Sanitizar nombre de archivo final
            $info = pathinfo($nombre_salida);
            $nombre_final = ($info['filename'] ?: 'solicitud') . '.csr';

            // Estructura requerida por ARCA/AFIP
            $dn = [
                "countryName" => "AR",
                "organizationName" => $alias,
                "commonName" => $alias,
                "serialNumber" => "CUIT " . $cuit
            ];

            // Leer la clave privada
            $privkey = file_get_contents($ruta_clave_privada);

            // Generar CSR en memoria (Requiere extensión OpenSSL activa en PHP)
            $csr = openssl_csr_new($dn, $privkey, ['digest_alg' => 'sha256']);
            if (!$csr) {
                throw new Exception("Error al procesar CSR: " . openssl_error_string());
            }

            // Exportar a string
            openssl_csr_export($csr, $csrout);

            echo json_encode([
                'success' => true,
                'filename' => $nombre_final,
                'csr_content' => $csrout
            ]);
            break;

        case 'upload_crt':
            if (!isset($_FILES['certificado']) || $_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("Error al recibir el archivo. Intente nuevamente.");
            }

            $file = $_FILES['certificado'];
            $ext = 'pem';

            if (!in_array($ext, ['crt', 'pem'])) {
                throw new Exception("Formato inválido. Solo se admiten archivos .crt o .pem");
            }

            // 3. Crear carpeta ARCA/{cuit}/prod/ de forma recursiva si no existe
            if (!is_dir($directorio_destino)) {
                if (!mkdir($directorio_destino, 0755, true)) {
                    throw new Exception("No se pudo crear el directorio de destino ARCA.");
                }
            }

            // 4. Requerimiento: El nombre debe ser el CUIT del tenant. 
            // Conservamos la extensión original del archivo subido (.crt o .pem)
            $nombre_archivo_final = $cuit_tenant . '.' . $ext;
            $destino = $directorio_destino . $nombre_archivo_final;

            // 5. move_uploaded_file sobreescribe automáticamente archivos existentes
            if (!move_uploaded_file($file['tmp_name'], $destino)) {
                throw new Exception("No se pudo guardar el archivo en el servidor. Verifique los permisos.");
            }

            echo json_encode([
                'success' => true,
                'message' => "Certificado guardado exitosamente en: ARCA/{$cuit_tenant}/prod/{$nombre_archivo_final}"
            ]);
            break;

        default:
            throw new Exception("Acción no reconocida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}