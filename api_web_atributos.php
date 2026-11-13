<?php
/**
 * api_web_atributos.php - Características de productos (modelo MercadoLibre).
 *
 * - Atributos de la empresa (prod_atributos) con su tipo: texto, número (con unidad),
 *   opciones (lista cerrada, prod_atributo_opciones) o sí/no.
 * - Plantillas: qué atributos se piden para un rubro o una familia (prod_atributo_plantillas).
 * - Valores por producto (prod_atributo_valores), que se cargan desde la ficha del producto.
 */
require_once 'check_api_access.php';
require_once 'web_helpers.php';

$action = $_GET['action'] ?? '';
$cuit = (string) $_SESSION['tenant_cuit'];

const ATR_TIPOS = ['texto', 'numero', 'opciones', 'sino'];

function leerJson(): array
{
    $in = json_decode(file_get_contents('php://input'), true);
    if (!is_array($in)) throw new Exception('Datos inválidos.');
    return $in;
}

/** Atributos que aplican a un producto: los de la plantilla de su rubro y de su familia, más los que ya tenga cargados. */
function atributosDeProducto(PDO $pdo, string $codigo, ?string $rubro, ?int $familia): array
{
    $st = $pdo->prepare("
        SELECT * FROM (
            SELECT a.id, a.nombre, a.tipo, a.unidad, a.orden, v.valor,
                   (SELECT MIN(pl.orden) FROM prod_atributo_plantillas pl
                     WHERE pl.atributo_id = a.id
                       AND ((pl.ambito = 'rubro' AND pl.ambito_valor = ?) OR (pl.ambito = 'familia' AND pl.ambito_valor = ?))) AS orden_plantilla
            FROM prod_atributos a
            LEFT JOIN prod_atributo_valores v ON v.atributo_id = a.id AND v.codigo = ?
            WHERE a.activo = 1
        ) x
        WHERE x.orden_plantilla IS NOT NULL OR x.valor IS NOT NULL
        ORDER BY (x.orden_plantilla IS NULL), x.orden_plantilla, x.orden, x.nombre");
    $st->execute([trim((string) $rubro), (string) ($familia ?? ''), $codigo]);
    $atrs = $st->fetchAll(PDO::FETCH_ASSOC);
    foreach ($atrs as &$a) {
        $a['de_plantilla'] = $a['orden_plantilla'] !== null;
        $a['opciones'] = [];
        if ($a['tipo'] === 'opciones') {
            $so = $pdo->prepare("SELECT valor FROM prod_atributo_opciones WHERE atributo_id = ? ORDER BY orden, valor");
            $so->execute([$a['id']]);
            $a['opciones'] = $so->fetchAll(PDO::FETCH_COLUMN);
        }
    }
    return $atrs;
}

try {
    if (!webAddonHabilitado($pdo_master, $cuit)) {
        throw new Exception('La web de clientes no está contratada para esta empresa.');
    }

    switch ($action) {
        case 'listar':
            $atrs = $pdo->query("SELECT a.*, (SELECT COUNT(*) FROM prod_atributo_valores v WHERE v.atributo_id = a.id) AS usos
                                 FROM prod_atributos a ORDER BY a.orden, a.nombre")->fetchAll(PDO::FETCH_ASSOC);
            $opc = $pdo->query("SELECT atributo_id, valor FROM prod_atributo_opciones ORDER BY orden, valor")->fetchAll(PDO::FETCH_ASSOC);
            $pla = $pdo->query("SELECT atributo_id, ambito, ambito_valor FROM prod_atributo_plantillas ORDER BY orden")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($atrs as &$a) {
                $a['opciones'] = array_values(array_map(fn($o) => $o['valor'], array_filter($opc, fn($o) => $o['atributo_id'] == $a['id'])));
                $a['plantillas'] = array_values(array_filter($pla, fn($p) => $p['atributo_id'] == $a['id']));
            }
            unset($a);
            $rubros = $pdo->query("SELECT DISTINCT TRIM(REPLACE(REPLACE(rubro, CHAR(13), ''), CHAR(10), '')) AS r FROM alb_productos WHERE rubro IS NOT NULL AND TRIM(REPLACE(REPLACE(rubro, CHAR(13), ''), CHAR(10), '')) <> '' ORDER BY r")->fetchAll(PDO::FETCH_COLUMN);
            $familias = $pdo->query("SELECT id, nombre FROM familias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $atrs, 'rubros' => $rubros, 'familias' => $familias]);
            break;

        case 'guardar':
            $in = leerJson();
            $id = (int) ($in['id'] ?? 0);
            $nombre = trim((string) ($in['nombre'] ?? ''));
            $tipo = (string) ($in['tipo'] ?? 'texto');
            if ($nombre === '') throw new Exception('Falta el nombre.');
            if (!in_array($tipo, ATR_TIPOS, true)) throw new Exception('Tipo inválido.');
            $unidad = $tipo === 'numero' ? trim((string) ($in['unidad'] ?? '')) : '';
            $opciones = $tipo === 'opciones'
                ? array_values(array_unique(array_filter(array_map(fn($o) => trim((string) $o), (array) ($in['opciones'] ?? [])), 'strlen')))
                : [];
            if ($tipo === 'opciones' && !$opciones) throw new Exception('Cargá al menos una opción.');

            $pdo->beginTransaction();
            if ($id > 0) {
                $pdo->prepare("UPDATE prod_atributos SET nombre = ?, tipo = ?, unidad = ?, filtrable = ?, orden = ?, activo = ? WHERE id = ?")
                    ->execute([$nombre, $tipo, $unidad, !empty($in['filtrable']) ? 1 : 0, (int) ($in['orden'] ?? 0), !empty($in['activo']) ? 1 : 0, $id]);
            } else {
                $pdo->prepare("INSERT INTO prod_atributos (nombre, tipo, unidad, filtrable, orden, activo) VALUES (?, ?, ?, ?, ?, 1)")
                    ->execute([$nombre, $tipo, $unidad, !empty($in['filtrable']) ? 1 : 0, (int) ($in['orden'] ?? 0)]);
                $id = (int) $pdo->lastInsertId();
            }

            $pdo->prepare("DELETE FROM prod_atributo_opciones WHERE atributo_id = ?")->execute([$id]);
            $stO = $pdo->prepare("INSERT INTO prod_atributo_opciones (atributo_id, valor, orden) VALUES (?, ?, ?)");
            foreach ($opciones as $i => $o) $stO->execute([$id, $o, $i]);

            $pdo->prepare("DELETE FROM prod_atributo_plantillas WHERE atributo_id = ?")->execute([$id]);
            $stP = $pdo->prepare("INSERT IGNORE INTO prod_atributo_plantillas (atributo_id, ambito, ambito_valor, orden) VALUES (?, ?, ?, ?)");
            foreach ((array) ($in['plantillas'] ?? []) as $p) {
                $amb = $p['ambito'] ?? '';
                $val = trim((string) ($p['ambito_valor'] ?? ''));
                if (in_array($amb, ['rubro', 'familia'], true) && $val !== '') $stP->execute([$id, $amb, $val, (int) ($in['orden'] ?? 0)]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $id, 'message' => 'Característica guardada.']);
            break;

        case 'eliminar':
            $id = (int) (leerJson()['id'] ?? 0);
            $pdo->prepare("DELETE FROM prod_atributos WHERE id = ?")->execute([$id]); // CASCADE borra opciones, plantillas y valores
            echo json_encode(['success' => true, 'message' => 'Característica eliminada.']);
            break;

        // ---- Desde la ficha del producto
        case 'producto':
            $codigo = (string) ($_GET['codigo'] ?? '');
            $rubro = $_GET['rubro'] ?? null;
            $familia = isset($_GET['familia']) && $_GET['familia'] !== '' ? (int) $_GET['familia'] : null;
            if ($codigo !== '' && ($rubro === null || $familia === null)) {
                $sp = $pdo->prepare("SELECT rubro, familia FROM alb_productos WHERE codigo = ? LIMIT 1");
                $sp->execute([$codigo]);
                if ($p = $sp->fetch(PDO::FETCH_ASSOC)) {
                    $rubro = $rubro ?? $p['rubro'];
                    $familia = $familia ?? (int) $p['familia'];
                }
            }
            $todos = $pdo->query("SELECT id, nombre, tipo, unidad FROM prod_atributos WHERE activo = 1 ORDER BY orden, nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => atributosDeProducto($pdo, $codigo, $rubro, $familia), 'todos' => $todos]);
            break;

        case 'guardar_producto':
            $in = leerJson();
            $codigo = trim((string) ($in['codigo'] ?? ''));
            if ($codigo === '') throw new Exception('Falta el código del producto.');
            $tipos = $pdo->query("SELECT id, tipo FROM prod_atributos")->fetchAll(PDO::FETCH_KEY_PAIR);
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM prod_atributo_valores WHERE codigo = ?")->execute([$codigo]);
            $st = $pdo->prepare("INSERT INTO prod_atributo_valores (codigo, atributo_id, valor) VALUES (?, ?, ?)");
            foreach ((array) ($in['valores'] ?? []) as $atrId => $valor) {
                $valor = trim((string) $valor);
                if ($valor === '' || !isset($tipos[(int) $atrId])) continue;
                if ($tipos[(int) $atrId] === 'numero') {
                    $num = str_replace(',', '.', $valor);
                    if (!is_numeric($num)) throw new Exception("El valor \"$valor\" no es un número.");
                    $valor = rtrim(rtrim(number_format((float) $num, 4, '.', ''), '0'), '.');
                }
                $st->execute([$codigo, (int) $atrId, mb_substr($valor, 0, 255)]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
