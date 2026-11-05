<?php
/**
 * api_retper.php - Agente de retención / percepción: regímenes, padrón, excepciones por
 * sujeto, retenciones y percepciones practicadas, y vista previa de percepciones para la
 * pantalla de facturación. Cálculo en retper_helpers.php.
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'retper_helpers.php';
require_once 'libro_iva_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

function rpJson(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function rpSoloAdmin(): void
{
    if ((int) ($_SESSION['es_admin'] ?? 0) !== 1) throw new Exception('Solo un administrador puede modificar la configuración de retenciones y percepciones.');
}

function rpFechaONull($v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    $dt = DateTime::createFromFormat('!Y-m-d', $v);
    if (!$dt || $dt->format('Y-m-d') !== $v) throw new Exception("Fecha inválida: $v");
    return $v;
}

function rpNum($v): float
{
    $s = str_replace(' ', '', (string) $v);
    if (preg_match('/^-?\d{1,3}(\.\d{3})*,\d+$/', $s) || preg_match('/^-?\d+,\d+$/', $s)) $s = str_replace(['.', ','], ['', '.'], $s);
    return is_numeric($s) ? (float) $s : 0.0;
}

try {
    switch ($action) {
        // ---------------------------------------------------------------- regímenes
        case 'regimenes_list':
            $filas = $pdo->query("SELECT r.*, (SELECT COUNT(*) FROM padron_retper p WHERE p.idregimen = r.id) AS padron_cant,
                    (SELECT COUNT(*) FROM sujetos_retper s WHERE s.idregimen = r.id) AS sujetos_cant
                FROM regimenes_retper r ORDER BY r.habilitado DESC, r.tipo, r.impuesto, r.nombre")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $filas]);
            break;

        case 'regimen_save':
            rpSoloAdmin();
            $d = rpJson();
            $nombre = mb_substr(trim((string) ($d['nombre'] ?? '')), 0, 100);
            if ($nombre === '') throw new Exception('Indique el nombre del régimen.');
            $impuesto = in_array($d['impuesto'] ?? '', ['IVA', 'GANANCIAS', 'IIBB', 'OTRO'], true) ? $d['impuesto'] : throw new Exception('Impuesto inválido.');
            $tipo = in_array($d['tipo'] ?? '', ['RETENCION', 'PERCEPCION'], true) ? $d['tipo'] : throw new Exception('Indique si es retención o percepción.');
            $base = in_array($d['base'] ?? '', ['NETO', 'TOTAL', 'IVA'], true) ? $d['base'] : 'NETO';
            $alic = rpNum($d['alicuota'] ?? 0);
            if ($alic < 0 || $alic > 100) throw new Exception('Alícuota inválida.');
            $alicNI = (isset($d['alicuota_no_inscripto']) && trim((string) $d['alicuota_no_inscripto']) !== '') ? rpNum($d['alicuota_no_inscripto']) : null;
            $habilitado = !empty($d['habilitado']) ? 1 : 0;
            $cuenta = trim((string) ($d['cuenta_contable'] ?? ''));
            if ($habilitado && !cuentaContableExiste($pdo, $cuenta)) throw new Exception('Para habilitar el régimen elija una cuenta contable válida (pasivo: retenciones / percepciones a depositar).');
            $condiciones = implode(',', array_filter(array_map('trim', (array) ($d['condiciones'] ?? []))));
            $tributoDefault = $tipo === 'PERCEPCION' ? ($impuesto === 'IVA' ? 6 : ($impuesto === 'IIBB' ? 7 : 9)) : 99;
            $vals = [
                $nombre, $impuesto, mb_substr(trim((string) ($d['jurisdiccion'] ?? 'Nacional')), 0, 60) ?: 'Nacional', $tipo, $base,
                $alic, $alicNI, round(rpNum($d['minimo_no_sujeto'] ?? 0), 2), !empty($d['resta_minimo']) ? 1 : 0,
                round(rpNum($d['minimo_importe'] ?? 0), 2), !empty($d['acumula_mensual']) ? 1 : 0, mb_substr($condiciones, 0, 255),
                !empty($d['usa_padron']) ? 1 : 0, ($d['sin_padron'] ?? '') === 'NO_APLICA' ? 'NO_APLICA' : 'GENERAL',
                (int) ($d['codigo_tributo_arca'] ?? 0) ?: $tributoDefault,
                mb_substr(trim((string) ($d['codigo_impuesto'] ?? '')), 0, 10), mb_substr(trim((string) ($d['codigo_regimen'] ?? '')), 0, 10),
                $cuenta, mb_substr(trim((string) ($d['resolucion'] ?? '')), 0, 120),
                rpFechaONull($d['vigente_desde'] ?? ''), rpFechaONull($d['vigente_hasta'] ?? ''), $habilitado,
                mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 255),
            ];
            $campos = 'nombre, impuesto, jurisdiccion, tipo, base, alicuota, alicuota_no_inscripto, minimo_no_sujeto, resta_minimo, minimo_importe, acumula_mensual, condiciones, usa_padron, sin_padron, codigo_tributo_arca, codigo_impuesto, codigo_regimen, cuenta_contable, resolucion, vigente_desde, vigente_hasta, habilitado, observaciones';
            $id = (int) ($d['id'] ?? 0);
            if ($id) {
                $set = implode(' = ?, ', explode(', ', $campos)) . ' = ?';
                $pdo->prepare("UPDATE regimenes_retper SET $set WHERE id = ?")->execute(array_merge($vals, [$id]));
            } else {
                $pdo->prepare("INSERT INTO regimenes_retper ($campos) VALUES (" . implode(',', array_fill(0, count($vals), '?')) . ")")->execute($vals);
                $id = (int) $pdo->lastInsertId();
            }
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'plan_cuentas':
            echo json_encode(['success' => true, 'data' => $pdo->query("SELECT numcuenta, descripcion FROM plandecuentas WHERE imputable = 1 AND habilitada = 1 ORDER BY numcuenta")->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ---------------------------------------------------------------- padrón
        case 'padron_list':
            $q = '%' . preg_replace('/\D/', '', (string) ($_GET['q'] ?? '')) . '%';
            $st = $pdo->prepare("SELECT * FROM padron_retper WHERE idregimen = ? AND cuit LIKE ? ORDER BY cuit LIMIT 200");
            $st->execute([(int) ($_GET['idregimen'] ?? 0), $q]);
            $cant = $pdo->prepare("SELECT COUNT(*) FROM padron_retper WHERE idregimen = ?");
            $cant->execute([(int) ($_GET['idregimen'] ?? 0)]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC), 'total' => (int) $cant->fetchColumn()]);
            break;

        case 'padron_importar':
            // Archivo CSV / Excel con columnas: CUIT ; alícuota ; [excluido S/N] ; [vigente desde] ; [vigente hasta]
            rpSoloAdmin();
            $idReg = (int) ($_POST['idregimen'] ?? 0);
            $reemplazar = ($_POST['reemplazar'] ?? '') === '1';
            if (!$idReg) throw new Exception('Régimen inválido.');
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) throw new Exception('No se recibió el archivo.');
            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            $filas = [];
            if (in_array($ext, ['xlsx', 'xls'], true)) {
                foreach (IOFactory::load($_FILES['archivo']['tmp_name'])->getActiveSheet()->toArray(null, true, false, false) as $f) $filas[] = $f;
            } else {
                foreach (preg_split('/\r\n|\n|\r/', (string) file_get_contents($_FILES['archivo']['tmp_name'])) as $linea) {
                    if (trim($linea) === '') continue;
                    $filas[] = str_getcsv($linea, substr_count($linea, ';') >= substr_count($linea, ',') ? ';' : ',');
                }
            }
            $validas = [];
            foreach ($filas as $f) {
                $cuit = preg_replace('/\D/', '', (string) ($f[0] ?? ''));
                if (strlen($cuit) !== 11) continue; // encabezados y renglones sin CUIT
                $exc = strtoupper(trim((string) ($f[2] ?? '')));
                $validas[] = [$cuit, rpNum($f[1] ?? 0), in_array($exc, ['S', 'SI', '1', 'X', 'EXCLUIDO'], true) ? 1 : 0,
                              concFechaPadron($f[3] ?? ''), concFechaPadron($f[4] ?? '')];
            }
            if (!$validas) throw new Exception('No se encontró ningún renglón con un CUIT de 11 dígitos.');
            $pdo->beginTransaction();
            if ($reemplazar) $pdo->prepare("DELETE FROM padron_retper WHERE idregimen = ?")->execute([$idReg]);
            $st = $pdo->prepare("INSERT INTO padron_retper (idregimen, cuit, alicuota, excluido, vigente_desde, vigente_hasta) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($validas as $v) $st->execute(array_merge([$idReg], $v));
            $pdo->commit();
            echo json_encode(['success' => true, 'importados' => count($validas), 'descartados' => count($filas) - count($validas)]);
            break;

        case 'padron_vaciar':
            rpSoloAdmin();
            $d = rpJson();
            $pdo->prepare("DELETE FROM padron_retper WHERE idregimen = ?")->execute([(int) ($d['idregimen'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        // ---------------------------------------------------------------- excepciones por sujeto
        case 'sujetos_list':
            $st = $pdo->prepare("SELECT s.*, CASE s.tipo_sujeto WHEN 'cliente' THEN (SELECT razon FROM alb_clientes WHERE id = s.idsujeto)
                                     ELSE (SELECT Razon FROM Proveedores WHERE Id = s.idsujeto) END AS nombre
                                 FROM sujetos_retper s WHERE s.idregimen = ? ORDER BY nombre");
            $st->execute([(int) ($_GET['idregimen'] ?? 0)]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'buscar_sujetos':
            $q = '%' . trim((string) ($_GET['q'] ?? '')) . '%';
            if (($_GET['tipo'] ?? '') === 'cliente') {
                $st = $pdo->prepare("SELECT id, razon AS nombre, numdoc AS cuit FROM alb_clientes WHERE razon LIKE ? OR numdoc LIKE ? ORDER BY razon LIMIT 20");
            } else {
                $st = $pdo->prepare("SELECT Id AS id, Razon AS nombre, cuit FROM Proveedores WHERE Razon LIKE ? OR cuit LIKE ? ORDER BY Razon LIMIT 20");
            }
            $st->execute([$q, $q]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'sujeto_save':
            rpSoloAdmin();
            $d = rpJson();
            $tipoS = ($d['tipo_sujeto'] ?? '') === 'cliente' ? 'cliente' : 'proveedor';
            $modo = ($d['modo'] ?? '') === 'ALICUOTA' ? 'ALICUOTA' : 'EXENTO';
            $pdo->prepare("INSERT INTO sujetos_retper (idregimen, tipo_sujeto, idsujeto, modo, alicuota, vigente_hasta, certificado, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE modo = VALUES(modo), alicuota = VALUES(alicuota), vigente_hasta = VALUES(vigente_hasta), certificado = VALUES(certificado), observaciones = VALUES(observaciones)")
                ->execute([(int) ($d['idregimen'] ?? 0), $tipoS, (int) ($d['idsujeto'] ?? 0), $modo, $modo === 'ALICUOTA' ? rpNum($d['alicuota'] ?? 0) : 0,
                           rpFechaONull($d['vigente_hasta'] ?? ''), mb_substr(trim((string) ($d['certificado'] ?? '')), 0, 100), mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 255)]);
            echo json_encode(['success' => true]);
            break;

        case 'sujeto_borrar':
            rpSoloAdmin();
            $d = rpJson();
            $pdo->prepare("DELETE FROM sujetos_retper WHERE id = ?")->execute([(int) ($d['id'] ?? 0)]);
            echo json_encode(['success' => true]);
            break;

        // ---------------------------------------------------------------- practicadas
        case 'practicadas':
            $d = rpJson();
            $desde = rpFechaONull($d['desde'] ?? '') ?? date('Y-m-01');
            $hasta = rpFechaONull($d['hasta'] ?? '') ?? date('Y-m-d');
            $idReg = (int) ($d['idregimen'] ?? 0);
            $incluirCero = !empty($d['incluir_cero']);
            if (($d['tipo'] ?? '') === 'PERCEPCION') {
                $st = $pdo->prepare("SELECT p.*, r.nombre AS regimen, r.impuesto, r.jurisdiccion, c.razon AS sujeto, c.numdoc AS cuit,
                        CONCAT(t.letracomp, ' ', LPAD(t.PtoVta, 4, '0'), '-', LPAD(t.numcomp, 8, '0')) AS comprobante, t.total AS total_comprobante
                    FROM percepciones_practicadas p JOIN regimenes_retper r ON r.id = p.idregimen
                    LEFT JOIN alb_clientes c ON c.id = p.idcliente LEFT JOIN tickets t ON t.id = p.idticket
                    WHERE p.fecha BETWEEN ? AND ? AND p.estado <> 1000" . ($idReg ? ' AND p.idregimen = ?' : '') . " ORDER BY p.fecha, p.id");
            } else {
                $st = $pdo->prepare("SELECT x.*, r.nombre AS regimen, r.impuesto, r.jurisdiccion, r.codigo_impuesto, r.codigo_regimen, pr.Razon AS sujeto, pr.cuit,
                        CONCAT('Orden de pago ', pg.numcomp) AS comprobante, pg.total AS total_comprobante
                    FROM retenciones_practicadas x JOIN regimenes_retper r ON r.id = x.idregimen
                    LEFT JOIN Proveedores pr ON pr.Id = x.idproveedor LEFT JOIN pagoaproveedores pg ON pg.id = x.idpago
                    WHERE x.fecha BETWEEN ? AND ? AND x.estado <> 1000" . ($incluirCero ? '' : ' AND x.importe > 0') . ($idReg ? ' AND x.idregimen = ?' : '') . " ORDER BY x.fecha, x.id");
            }
            $st->execute($idReg ? [$desde, $hasta, $idReg] : [$desde, $hasta]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ---------------------------------------------------------------- vista previa de percepciones
        case 'percepciones_venta':
            // La pantalla de facturar manda sus sumas por alícuota (calculadas igual que en el
            // servidor) y acá se aplica el mismo prorrateo que se declara a ARCA. El importe
            // definitivo lo vuelve a calcular api_facturar.php al grabar.
            $d = rpJson();
            $tipo = (int) ($d['tipo_comp'] ?? 0);
            $a = ivaVentaComoArca(['subtotal' => (float) ($d['sub21'] ?? 0), 'iva' => (float) ($d['iva21'] ?? 0), 'subtotal10' => (float) ($d['sub10'] ?? 0),
                                   'iva10' => (float) ($d['iva10'] ?? 0), 'subtotal0' => (float) ($d['sub0'] ?? 0),
                                   'recargo' => (float) ($d['reca'] ?? 0), 'descuento' => (float) ($d['desc'] ?? 0)]);
            [$neto, $iva] = percepcionBaseVenta($a, $tipo);
            $lineas = calcularPercepcionesVenta($pdo, (int) ($d['cliente_id'] ?? 0), $tipo, $neto, $iva, (float) ($d['total'] ?? 0), date('Y-m-d'));
            echo json_encode(['success' => true, 'lineas' => $lineas, 'total' => round(array_sum(array_column($lineas, 'importe')), 2)]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function concFechaPadron($v): ?string
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v)) return $v;
    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); // ddmmaaaa (padrones de rentas)
    return null;
}
