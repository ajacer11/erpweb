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

    /**
     * Deja la clave privada en la carpeta de la empresa, que es donde la busca la facturación
     * electrónica (ARCA/ArcaFunctions.php). Si la empresa ya tiene su clave no se toca:
     * reemplazarla invalidaría el certificado vigente. Si no la tiene, se copia la clave de la
     * raíz del sistema (la misma con la que se genera la solicitud).
     */
    $asegurarClaveEmpresa = function () use ($directorio_destino): string {
        $claveEmpresa = $directorio_destino . 'privada.key';
        if (is_file($claveEmpresa) && filesize($claveEmpresa) > 0) {
            return $claveEmpresa;
        }
        // Clave común: en ARCA/ (su .htaccess impide descargarla); la de la raíz queda por compatibilidad.
        $claveRaiz = '';
        foreach ([__DIR__ . '/ARCA/privada.key', __DIR__ . '/privada.key'] as $candidata) {
            if (is_file($candidata) && filesize($candidata) > 0) { $claveRaiz = $candidata; break; }
        }
        if ($claveRaiz === '') {
            throw new Exception("Falta el archivo de clave privada en el servidor. Contacte a soporte.");
        }
        if (!is_dir($directorio_destino) && !mkdir($directorio_destino, 0755, true)) {
            throw new Exception("No se pudo crear el directorio de destino ARCA.");
        }
        if (!copy($claveRaiz, $claveEmpresa)) {
            throw new Exception("No se pudo copiar la clave privada a la carpeta de la empresa. Verifique los permisos.");
        }
        @chmod($claveEmpresa, 0600);
        return $claveEmpresa;
    };

    switch ($action) {
        
        case 'generate_csr':
            $data = json_decode(file_get_contents('php://input'), true);
            
            $cuit = trim($data['cuit'] ?? '');
            $alias = trim($data['alias'] ?? '');
            $nombre_salida = trim($data['salida'] ?? 'solicitud');

            if (empty($cuit) || empty($alias)) {
                throw new Exception("El CUIT y el Alias son obligatorios.");
            }

            // La solicitud se firma con la clave de la carpeta de la empresa; si todavía no
            // tiene, primero se le copia la de la raíz (así el certificado que devuelva ARCA
            // corresponde a la clave que después usa la facturación).
            $ruta_clave_privada = $asegurarClaveEmpresa();

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
            $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, ['crt', 'pem'])) {
                throw new Exception("Formato inválido. Solo se admiten archivos .crt o .pem");
            }

            // 3. Crear carpeta ARCA/{cuit}/prod/ de forma recursiva si no existe
            if (!is_dir($directorio_destino)) {
                if (!mkdir($directorio_destino, 0755, true)) {
                    throw new Exception("No se pudo crear el directorio de destino ARCA.");
                }
            }

            // 3b. La clave privada también tiene que estar en la carpeta de la empresa (cubre
            //     solicitudes generadas antes de que se copiara al generarlas).
            $asegurarClaveEmpresa();

            // 4. Se graba dos veces, con el mismo contenido:
            //    - {cuit}.pem: nombre que usan las instalaciones legacy.
            //    - certificado.pem: el que buscan arcaCredencialesFiscalesDisponibles()
            //      (arca_certificados.php) y ARCA/ArcaFunctions.php.
            //    move_uploaded_file / copy sobreescriben los archivos existentes.
            $nombre_cuit = $cuit_tenant . '.pem';
            $nombre_generico = 'certificado.pem';
            if (!move_uploaded_file($file['tmp_name'], $directorio_destino . $nombre_cuit)) {
                throw new Exception("No se pudo guardar el archivo en el servidor. Verifique los permisos.");
            }
            if (!copy($directorio_destino . $nombre_cuit, $directorio_destino . $nombre_generico)) {
                throw new Exception("No se pudo terminar de guardar el certificado. Contacte a soporte.");
            }

            echo json_encode([
                'success' => true,
                'message' => 'Guardado con éxito'
            ]);
            break;

        default:
            throw new Exception("Acción no reconocida.");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}