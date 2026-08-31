<?php
/**
 * api_promociones.php — CRUD + EVALUADOR DE PROMOCIONES
 * West IT ERP v1.0
 */
require_once 'check_api_access.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$hoy = date('Y-m-d');

try {
    // ── GET ────────────────────────────────────────────────────────────────
    if ($method === 'GET') {
        switch ($action) {

            // ── Listar con filtros ──────────────────────────────────────
            case 'listar':
                $where  = ['1=1'];
                $params = [];

                if (!empty($_GET['tipo'])) {
                    $where[]  = 'tipo = ?';
                    $params[] = $_GET['tipo'];
                }
                if (isset($_GET['habilitada']) && $_GET['habilitada'] !== '') {
                    $where[]  = 'habilitada = ?';
                    $params[] = (int)$_GET['habilitada'];
                }
                if (!empty($_GET['vigentes'])) {
                    // Solo las que estén dentro del rango de fechas o sin fechas
                    $where[]  = '(fecha_desde IS NULL OR fecha_desde <= ?)';
                    $params[] = $hoy;
                    $where[]  = '(fecha_hasta IS NULL OR fecha_hasta >= ?)';
                    $params[] = $hoy;
                }
                if (!empty($_GET['q'])) {
                    $where[]  = '(nombre LIKE ? OR codigo_prod LIKE ? OR rubro LIKE ? OR marca LIKE ?)';
                    $q = '%' . $_GET['q'] . '%';
                    $params = array_merge($params, [$q, $q, $q, $q]);
                }

                $sql  = "SELECT * FROM promociones WHERE " . implode(' AND ', $where) . " ORDER BY id DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            // ── Una sola promo por ID ───────────────────────────────────
            case 'get':
                $id   = (int)($_GET['id'] ?? 0);
                $stmt = $pdo->prepare("SELECT * FROM promociones WHERE id = ?");
                $stmt->execute([$id]);
                $row  = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw new Exception("Promoción no encontrada.");
                echo json_encode(['success' => true, 'data' => $row]);
                break;

            // ── Autocompletar productos ─────────────────────────────────
            case 'buscar_productos':
                $q    = $_GET['q'] ?? '';
                $stmt = $pdo->prepare("SELECT codigo, descripcion, rubro, marca FROM alb_productos WHERE habilitado = 1 AND (codigo LIKE ? OR descripcion LIKE ?) ORDER BY descripcion LIMIT 30");
                $stmt->execute(["%$q%", "%$q%"]);
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;

            // ── Listado de rubros y marcas (para selects) ───────────────
            case 'get_rubros':
                $stmt = $pdo->query("SELECT DISTINCT rubro FROM alb_productos WHERE habilitado = 1 AND rubro != '' ORDER BY rubro ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
                break;

            case 'get_marcas':
                $stmt = $pdo->query("SELECT DISTINCT marca FROM alb_productos WHERE habilitado = 1 AND marca != '' ORDER BY marca ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
                break;

            // ── EVALUADOR: recibe carrito, retorna descuentos ───────────
            case 'evaluar':
                $cartJson = $_GET['cart'] ?? '[]';
                $cart     = json_decode($cartJson, true);
                $fecha    = $_GET['fecha'] ?? $hoy;

                // Traer todas las promociones habilitadas y vigentes a esa fecha
                $stmtP = $pdo->prepare(
                    "SELECT * FROM promociones
                     WHERE habilitada = 1
                       AND (fecha_desde IS NULL OR fecha_desde <= ?)
                       AND (fecha_hasta IS NULL OR fecha_hasta >= ?)
                     ORDER BY id ASC"
                );
                $stmtP->execute([$fecha, $fecha]);
                $promos = $stmtP->fetchAll(PDO::FETCH_ASSOC);

                $resultados = [];

                foreach ($cart as $item) {
                    $codigo  = $item['codigo']   ?? '';
                    $rubro   = strtoupper($item['rubro']    ?? '');
                    $marca   = strtoupper($item['marca']    ?? '');
                    $cant    = (float)($item['cantidad']    ?? 1);
                    $lista   = (int)($item['lista']         ?? 1);

                    $mejorDesc  = 0;
                    $promoLabel = '';
                    $promoId    = null;

                    foreach ($promos as $p) {
                        // Verificar que aplica a esta lista
                        if ((int)$p['lista_aplicable'] !== 0 && (int)$p['lista_aplicable'] !== $lista) continue;

                        // Verificar cupo disponible
                        if (!is_null($p['stock_comprometido'])) {
                            $restante = (int)$p['stock_comprometido'] - (int)$p['stock_usado'];
                            if ($restante <= 0) continue;
                        }

                        $desc = 0;
                        $label = '';

                        switch ($p['tipo']) {

                            case 'LLEVA_PAGA':
                                if (strtoupper($p['codigo_prod']) !== strtoupper($codigo)) break;
                                $llevar = (float)$p['cant_llevar'];
                                $pagar  = (float)$p['cant_pagar'];
                                if ($llevar > 0 && $cant >= $llevar) {
                                    // El descuento equivalente: (llevar - pagar) / llevar * 100
                                    $desc  = round((($llevar - $pagar) / $llevar) * 100, 2);
                                    $label = "Lleva {$llevar} paga {$pagar}";
                                }
                                break;

                            case '2DA_UNIDAD':
                                if (strtoupper($p['codigo_prod']) !== strtoupper($codigo)) break;
                                $cantMin = (float)($p['cant_minima'] ?? 2);
                                if ($cant >= $cantMin) {
                                    // Cuántas unidades pagan precio lleno vs cuántas al %
                                    // Mitad entera al precio lleno, mitad al descuento
                                    $dcto  = (float)$p['descuento_pct'];
                                    // Descuento efectivo sobre el total de la línea
                                    $pares = floor($cant / 2);
                                    $desc  = round(($pares * $dcto) / $cant, 2);
                                    $label = "2da unidad {$dcto}% OFF";
                                }
                                break;

                            case 'CANT_DESC':
                                if (strtoupper($p['codigo_prod']) !== strtoupper($codigo)) break;
                                if ($cant >= (float)$p['cant_minima']) {
                                    $desc  = (float)$p['descuento_pct'];
                                    $label = "Desc. {$desc}% x {$p['cant_minima']} u.";
                                }
                                break;

                            case 'FECHA_PROD':
                                if (strtoupper($p['codigo_prod']) !== strtoupper($codigo)) break;
                                $desc  = (float)$p['descuento_pct'];
                                $label = "Promo " . $p['nombre'];
                                break;

                            case 'FECHA_RUBRO':
                                if (strtoupper($p['rubro']) !== $rubro) break;
                                $desc  = (float)$p['descuento_pct'];
                                $label = "Promo rubro " . $p['rubro'];
                                break;

                            case 'FECHA_MARCA':
                                if (strtoupper($p['marca']) !== $marca) break;
                                $desc  = (float)$p['descuento_pct'];
                                $label = "Promo marca " . $p['marca'];
                                break;
                        }

                        // Ganó la promo de mayor descuento (no se acumulan)
                        if ($desc > $mejorDesc) {
                            $mejorDesc  = $desc;
                            $promoLabel = $label;
                            $promoId    = (int)$p['id'];
                        }
                    }

                    $resultados[] = [
                        'codigo'      => $codigo,
                        'pordesc'     => $mejorDesc,
                        'promo_label' => $promoLabel,
                        'promo_id'    => $promoId
                    ];
                }

                echo json_encode(['success' => true, 'resultados' => $resultados]);
                break;

            default:
                throw new Exception("Acción GET no válida.");
        }

    // ── POST ───────────────────────────────────────────────────────────────
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) throw new Exception("Datos inválidos.");

        $accionPost = $input['action'] ?? '';

        switch ($accionPost) {

            // ── Crear ──────────────────────────────────────────────────
            case 'crear':
                validarCampos($input);
                $stmt = $pdo->prepare(
                    "INSERT INTO promociones
                     (nombre, tipo, codigo_prod, rubro, marca,
                      cant_llevar, cant_pagar, cant_minima, descuento_pct,
                      lista_aplicable, fecha_desde, fecha_hasta,
                      stock_comprometido, habilitada, fecha_creacion, usuario)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?)"
                );
                $stmt->execute([
                    strtoupper(trim($input['nombre'])),
                    $input['tipo'],
                    nullOrStr($input['codigo_prod']  ?? null),
                    nullOrStr($input['rubro']        ?? null),
                    nullOrStr($input['marca']        ?? null),
                    nullOrNum($input['cant_llevar']  ?? null),
                    nullOrNum($input['cant_pagar']   ?? null),
                    nullOrNum($input['cant_minima']  ?? null),
                    nullOrNum($input['descuento_pct']?? null),
                    (int)($input['lista_aplicable']  ?? 0),
                    nullOrDate($input['fecha_desde'] ?? null),
                    nullOrDate($input['fecha_hasta'] ?? null),
                    nullOrInt($input['stock_comprometido'] ?? null),
                    date('Y-m-d'),
                    $usuario
                ]);
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'message' => 'Promoción creada correctamente.']);
                break;

            // ── Actualizar ─────────────────────────────────────────────
            case 'actualizar':
                $id = (int)($input['id'] ?? 0);
                if (!$id) throw new Exception("ID inválido.");
                validarCampos($input);
                $stmt = $pdo->prepare(
                    "UPDATE promociones SET
                       nombre = ?, tipo = ?, codigo_prod = ?, rubro = ?, marca = ?,
                       cant_llevar = ?, cant_pagar = ?, cant_minima = ?, descuento_pct = ?,
                       lista_aplicable = ?, fecha_desde = ?, fecha_hasta = ?,
                       stock_comprometido = ?
                     WHERE id = ?"
                );
                $stmt->execute([
                    strtoupper(trim($input['nombre'])),
                    $input['tipo'],
                    nullOrStr($input['codigo_prod']  ?? null),
                    nullOrStr($input['rubro']        ?? null),
                    nullOrStr($input['marca']        ?? null),
                    nullOrNum($input['cant_llevar']  ?? null),
                    nullOrNum($input['cant_pagar']   ?? null),
                    nullOrNum($input['cant_minima']  ?? null),
                    nullOrNum($input['descuento_pct']?? null),
                    (int)($input['lista_aplicable']  ?? 0),
                    nullOrDate($input['fecha_desde'] ?? null),
                    nullOrDate($input['fecha_hasta'] ?? null),
                    nullOrInt($input['stock_comprometido'] ?? null),
                    $id
                ]);
                echo json_encode(['success' => true, 'message' => 'Promoción actualizada correctamente.']);
                break;

            // ── Habilitar / Deshabilitar ───────────────────────────────
            case 'toggle':
                $id = (int)($input['id'] ?? 0);
                if (!$id) throw new Exception("ID inválido.");
                $pdo->prepare("UPDATE promociones SET habilitada = !habilitada WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true]);
                break;

            // ── Eliminar ───────────────────────────────────────────────
            case 'eliminar':
                $id = (int)($input['id'] ?? 0);
                if (!$id) throw new Exception("ID inválido.");
                $pdo->prepare("DELETE FROM promociones WHERE id = ?")->execute([$id]);
                echo json_encode(['success' => true, 'message' => 'Promoción eliminada.']);
                break;

            // ── Descontar stock_usado al grabar comprobante ────────────
            case 'registrar_uso':
                // Llamado desde api_facturar.php dentro de la transacción
                $promoId = (int)($input['promo_id']  ?? 0);
                $cantUso = (int)($input['cantidad']  ?? 0);
                if ($promoId && $cantUso > 0) {
                    $pdo->prepare("UPDATE promociones SET stock_usado = stock_usado + ? WHERE id = ? AND stock_comprometido IS NOT NULL")
                        ->execute([$cantUso, $promoId]);
                }
                echo json_encode(['success' => true]);
                break;

            default:
                throw new Exception("Acción POST no válida.");
        }
    } else {
        throw new Exception("Método no permitido.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ── Helpers ──────────────────────────────────────────────────────────────
function nullOrStr(?string $v): ?string { $v = trim($v ?? ''); return $v === '' ? null : strtoupper($v); }
function nullOrNum($v): ?float  { return ($v === null || $v === '') ? null : (float)$v; }
function nullOrInt($v): ?int    { return ($v === null || $v === '') ? null : (int)$v; }
function nullOrDate(?string $v): ?string { return (!$v || $v === '0000-00-00') ? null : $v; }

function validarCampos(array $d): void {
    if (empty(trim($d['nombre'] ?? ''))) throw new Exception("El nombre es obligatorio.");
    if (empty($d['tipo']))               throw new Exception("Debe seleccionar un tipo.");

    $tipo = $d['tipo'];
    if (in_array($tipo, ['LLEVA_PAGA'])) {
        if (empty($d['codigo_prod'])) throw new Exception("Debe indicar el producto.");
        if (empty($d['cant_llevar']) || empty($d['cant_pagar'])) throw new Exception("Complete Lleva / Paga.");
        if ((float)$d['cant_pagar'] >= (float)$d['cant_llevar']) throw new Exception("'Paga' debe ser menor que 'Lleva'.");
    }
    if (in_array($tipo, ['2DA_UNIDAD', 'CANT_DESC', 'FECHA_PROD'])) {
        if (empty($d['codigo_prod'])) throw new Exception("Debe indicar el producto.");
        if (empty($d['descuento_pct'])) throw new Exception("Ingrese el porcentaje de descuento.");
    }
    if ($tipo === '2DA_UNIDAD' && empty($d['cant_minima'])) $d['cant_minima'] = 2;
    if ($tipo === 'FECHA_RUBRO') {
        if (empty($d['rubro'])) throw new Exception("Debe indicar el rubro.");
        if (empty($d['descuento_pct'])) throw new Exception("Ingrese el porcentaje de descuento.");
    }
    if ($tipo === 'FECHA_MARCA') {
        if (empty($d['marca'])) throw new Exception("Debe indicar la marca.");
        if (empty($d['descuento_pct'])) throw new Exception("Ingrese el porcentaje de descuento.");
    }
}
?>
