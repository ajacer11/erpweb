<?php
/**
 * api_clientes.php - Backend para gestión de clientes
 * Maneja la lógica de negocio, capitalización y selección de comprobante.
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];

// Permiso de menú para dar de ALTA clientes ("Agregar Clientes" -> clientes_alta.php).
// Mismo criterio que dashboard1.php/dashboard2.php/newfactura.php: admin siempre puede,
// el resto depende de accesosusuarios.permisos.allowed_urls.
function puedeCrearClientes($pdo) {
    if ((int) ($_SESSION['es_admin'] ?? 0) === 1) return true;
    try {
        $stmt = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
        $stmt->execute([$_SESSION['usuario_id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return false;
        $allowedUrls = json_decode($row['permisos'], true)['allowed_urls'] ?? [];
        foreach ($allowedUrls as $allowed) {
            if (basename($allowed) === 'clientes_alta.php') return true;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}


try {
    if ($method === 'GET') {
        // Acción especial para cargar todos los desplegables necesarios en el formulario
        if (isset($_GET['action']) && $_GET['action'] === 'get_combos') {
            $combos = [
                'vendedores' => $pdo->query("SELECT id, nombre FROM vendedores WHERE habilitado = 1 ORDER BY nombre ASC")->fetchAll(),
                'ramas' => $pdo->query("SELECT id, rama FROM ramasclientes ORDER BY rama ASC")->fetchAll(),
                'unidades' => $pdo->query("SELECT id, nombre FROM unidadesdenegocio ORDER BY nombre ASC")->fetchAll(),
                'transportes' => $pdo->query("SELECT id, razon FROM transportes WHERE habilitado = 1 ORDER BY razon ASC")->fetchAll()
            ];
            echo json_encode(['success' => true, 'data' => $combos]);
            exit;
        }

        // Consulta al padrón de AFIP/ARCA por CUIT, para autocompletar el alta de un cliente.
        // Proxea al servicio ARCA (mismo criterio que api_facturar.php con new_fe_cae_api.php):
        // nunca se llama al padrón directo desde el navegador.
        if (isset($_GET['action']) && $_GET['action'] === 'consultar_padron') {
            $cuit = preg_replace('/[^0-9]/', '', $_GET['cuit'] ?? '');
            if (strlen($cuit) !== 11) {
                throw new Exception('El CUIT debe tener 11 dígitos.');
            }
            $ch = curl_init("https://erp.westit.com.ar/ARCA/padron/consultar_padron.php?cuit={$cuit}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT => 'SistemaERP/Padron'
            ]);
            $resp = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($resp === false) {
                throw new Exception('No se pudo comunicar con el padrón de AFIP: ' . $curlError);
            }
            $data = json_decode($resp, true);
            if (!is_array($data) || isset($data['error'])) {
                throw new Exception($data['error'] ?? 'No se encontraron datos para ese CUIT.');
            }
            echo json_encode(['success' => true, 'data' => $data]);
            exit;
        }

        // Obtener un cliente específico por ID
        if (isset($_GET['id'])) {
            $stmt = $pdo->prepare("SELECT * FROM alb_clientes WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $cliente = $stmt->fetch();
            echo json_encode(['success' => $cliente ? true : false, 'data' => $cliente]);
            exit;
        }

        // Listado general para la administración de clientes
        $busqueda = $_GET['q'] ?? '';
        $mostrar_inhabilitados = isset($_GET['inhabilitados']) && $_GET['inhabilitados'] == 'true';
        $sql = "SELECT id, razon, numdoc, condicionfiscal, telefono, mail, localidad, habilitado FROM alb_clientes WHERE 1=1";
        $params = [];

        if (!empty($busqueda)) {
            $sql .= " AND (razon LIKE ? OR numdoc LIKE ? OR id LIKE ?)";
            $term = "%$busqueda%";
            $params = [$term, $term, $term];
        }

        if (!$mostrar_inhabilitados) $sql .= " AND habilitado = 1";

        // PERMISO ESPECIAL: filtrar solo clientes del vendedor asignado
        if ((int)($_SESSION['pe_unvendedor'] ?? 0) === 1) {
            $sql .= " AND vendedorid = " . (int)$_SESSION['pe_idvendedor'];
        }

        $sql .= " ORDER BY razon ASC LIMIT 100";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        exit;
    }

    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input['razon'])) throw new Exception("La Razón Social es obligatoria.");

        // Lógica de Comprobante Predeterminado (Emulando el Select Case de VB)
        $cond = strtoupper($input['condicionfiscal'] ?? '');
        $tipocomp = ($cond === "RESPONSABLE INSCRIPTO") ? 1 : 6;

        // Listado de campos de la tabla alb_clientes (Coincidente con VB)
        $campos = [
            'rama', 'vendedorid', 'ListaDePRecios', 'razon', 'domicilio', 'localidad', 'provincia', 'cp', 
            'numdoc', 'condicionfiscal', 'telefono', 'celular', 'mail', 'contacto', 'comp_predet', 
            'tipodoc', 'habilitado', 'transporte', 'horario', 'web', 'notas', 'unidad', 'diascc', 'cuentacorriente',
            's1sector', 's1contacto', 's1tel', 's1celu', 's1mail',
            's2sector', 's2contacto', 's2tel', 's2celu', 's2mail',
            's3sector', 's3contacto', 's3tel', 's3celu', 's3mail'
        ];

        $datos = [];
        foreach ($campos as $c) {
            if ($c === 'comp_predet') {
                $datos[$c] = $tipocomp;
            } else {
                $valor = $input[$c] ?? "";
                
                // Procesamiento según tipo de campo
                if (in_array($c, ['ListaDePRecios', 'vendedorid', 'transporte', 'unidad', 'rama', 'diascc', 'habilitado', 'cuentacorriente'])) {
                    $datos[$c] = is_numeric($valor) ? intval($valor) : 0;
                } else {
                    // Capitalización (Proper Case) para campos de texto
                    $datos[$c] = mb_convert_case(trim($valor), MB_CASE_TITLE, "UTF-8");
                }
            }
        }

        $esAlta = empty($input['id']);

        if ($esAlta && !puedeCrearClientes($pdo)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No tenés permiso para cargar clientes nuevos.']);
            exit;
        }

        if (!$esAlta) {
            // Actualización (UPDATE)
            $setStr = implode(", ", array_map(fn($f) => "$f = :$f", $campos));
            $sql = "UPDATE alb_clientes SET $setStr WHERE id = :id";
            $datos['id'] = $input['id'];
        } else {
            // Creación (INSERT)
            $sql = "INSERT INTO alb_clientes (" . implode(", ", $campos) . ") VALUES (:" . implode(", :", $campos) . ")";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($datos);

        $respuesta = ['success' => true, 'message' => "Operación realizada correctamente."];
        if ($esAlta) {
            $nuevoId = $pdo->lastInsertId();
            $stmtNuevo = $pdo->prepare("SELECT * FROM alb_clientes WHERE id = ?");
            $stmtNuevo->execute([$nuevoId]);
            $respuesta['data'] = $stmtNuevo->fetch(PDO::FETCH_ASSOC);
        }
        echo json_encode($respuesta);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>