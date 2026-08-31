<?php
/**
 * api_helpers.php - API para obtener y guardar listas de referencia con filtros inteligentes
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    // --- GET METHODS ---
    if ($method === 'GET') {
        switch ($action) {
            case 'get_rubros':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, rubro FROM rubros ORDER BY rubro")->fetchAll()]);
                break;
            case 'get_marcas':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, marca FROM Marcas ORDER BY marca")->fetchAll()]);
                break;
            case 'get_bancos':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, codigo, denominacion FROM bancos ORDER BY denominacion")->fetchAll()]);
                break;
            case 'get_familias':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, nombre FROM familias ORDER BY nombre")->fetchAll()]);
                break;
            case 'get_proveedores':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, razon FROM Proveedores WHERE habilitado = 1 ORDER BY razon")->fetchAll()]);
                break;
            case 'get_cuentas':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT numcuenta, descripcion FROM plandecuentas WHERE imputable = 1 ORDER BY descripcion")->fetchAll()]);
                break;
            case 'get_listas_utilidad':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, descripcion, utilidad FROM Listas WHERE id BETWEEN 1 AND 8 ORDER BY id")->fetchAll()]);
                break;
            case 'get_unidades':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, nombre FROM unidadesdenegocio ORDER BY nombre ASC")->fetchAll()]);
                break;
            case 'get_ramas_clientes':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, rama FROM ramasclientes ORDER BY rama ASC")->fetchAll()]);
                break;
            case 'get_usuarios':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, usuario FROM alb_usuarios ORDER BY usuario ASC")->fetchAll()]);
                break;
            case 'get_monedas':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, nombre FROM monedas ORDER BY nombre ASC")->fetchAll()]);
                break;
            case 'get_ramas':
                echo json_encode(['success' => true, 'data' => $pdo->query("SELECT id, rama as nombre FROM ramasclientes ORDER BY rama")->fetchAll()]);
                break;
            case 'get_vendedores':
                $q = $_GET['q'] ?? '';
                $stmt = $pdo->prepare("SELECT id, nombre as text FROM vendedores WHERE nombre LIKE ? AND habilitado = 1 ORDER BY nombre ASC");
                $stmt->execute(["%$q%"]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;
            case 'get_clientes':
                $sql = "SELECT id, razon FROM alb_clientes WHERE habilitado = 1";
                // PERMISO ESPECIAL: filtrar solo clientes del vendedor asignado
                if ((int)($_SESSION['pe_unvendedor'] ?? 0) === 1) {
                    $sql .= " AND vendedorid = " . (int)$_SESSION['pe_idvendedor'];
                }
                $sql .= " ORDER BY razon ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;
            case 'get_cotizacion':
                $id = $_GET['id'] ?? 1;
                $stmt = $pdo->prepare("SELECT cambio FROM tiposdecambio WHERE idmoneda = ? ORDER BY fecha DESC, id DESC LIMIT 1");
                $stmt->execute([$id]);
                $res = $stmt->fetch();
                echo json_encode(['success' => true, 'data' => ['cambio' => $res['cambio'] ?? 1]]);
                break;
            case 'get_plan_cuentas':
                // REGLA 6: Migración fiel de libromayor.vb. 
                // Se incluyen alias id/text para compatibilidad con componentes UI y orden numérico.
                $sql = "SELECT numcuenta, descripcion, 
                               numcuenta as id, 
                               descripcion as text 
                        FROM plandecuentas 
                        WHERE imputable = 1 AND habilitada = 1 
                        ORDER BY numcuenta ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;
            case 'get_puestos_cobro':
                // Se utiliza la tabla 'cajas' en lugar de 'puestos_cobro'
                $sql = "SELECT id, descripcion AS nombre FROM cajas ORDER BY descripcion ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;
            case 'get_medios_pago':
                $sql = "SELECT id, medio FROM mediosdepago WHERE habilitado = 1 AND visibleenventas = 1 ORDER BY medio ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;
            case 'get_unidades_negocio':
                $sql = "SELECT id, nombre FROM unidadesdenegocio ORDER BY nombre ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;

            case 'get_tipos_comprobante':
                /**
                 * Permite filtrar por tipo:
                 * - 'compra': 1, 6, 11, 100, 1000
                 * - 'venta_inscripto': 1, 2, 3, 6, 7, 8, 100, 101
                 * - 'venta_monotributo': 11, 12, 13, 100, 101
                 */
                $tipo_filtro = $_GET['tipo'] ?? '';
                $sql = "SELECT * FROM tiposcomprobantes";
                $where = "";

                if ($tipo_filtro === 'compra') {
                    $where = " WHERE numero IN (1, 6, 11, 100, 1000)";
                } elseif ($tipo_filtro === 'venta_inscripto') {
                    $where = " WHERE numero IN (1, 2, 3, 6, 7, 8, 100, 101)";
                } elseif ($tipo_filtro === 'venta_monotributo') {
                    $where = " WHERE numero IN (11, 12, 13, 100, 101)";
                }

                $sql .= $where . " ORDER BY numero ASC"; // Cambiado a número para mejor lectura en combos
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;

            case 'get_ejercicios':
                // Migración de verlibrodiario.vb: GenerarDT("ejercicios", "id, descripcion", "id > 0", "id ASC")
                $sql = "SELECT id, descripcion FROM ejercicios WHERE id > 0 ORDER BY id ASC";
                echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Acción GET no válida']);
        }
    }

    // --- POST METHODS ---
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            case 'save_listas':
                $listas = $input['listas'] ?? [];
                if (empty($listas))
                    throw new Exception("No hay datos para guardar");

                $sql = "REPLACE INTO Listas (id, descripcion, utilidad) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                $pdo->beginTransaction();
                foreach ($listas as $l) {
                    $id = $l['id'];
                    $desc = trim($l['descripcion']);
                    $util = (float) $l['utilidad'];

                    if ($id >= 1 && $id <= 8) {
                        $stmt->execute([$id, $desc, $util]);
                    }
                }
                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Listas actualizadas correctamente']);
                break;
            default:
                echo json_encode(['success' => false, 'error' => 'Acción POST no válida']);
        }
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>