<?php
/**
 * api_bancos_conciliacion.php - Conciliación bancaria (sql/2026-10-15_conciliacion_bancaria.sql).
 *
 * Convención de signos: en ccbancos debe = ingreso al banco, haber = egreso; acá se trabaja
 * con importe = debe - haber. En el extracto, importe positivo = crédito (ingreso).
 *
 * El saldo de ccbancos.saldo NO se usa: hay movimientos grabados sin saldo (en 0) y la
 * cadena está rota; todos los saldos se calculan con SUM(debe - haber).
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
const CONC_TOL = 0.009;

function concJson(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function concFecha($f, string $campo): string
{
    $f = (string) $f;
    $dt = DateTime::createFromFormat('!Y-m-d', $f);
    if (!$dt || $dt->format('Y-m-d') !== $f) throw new Exception("Fecha inválida en $campo.");
    return $f;
}

/** Número en formato argentino (1.234,56), inglés (1,234.56) o simple; con signo, paréntesis o "-" al final. */
function concNumero($v): ?float
{
    if ($v === null) return null;
    if (is_int($v) || is_float($v)) return (float) $v;
    $s = trim(str_replace(["\xc2\xa0", ' ', '$'], '', (string) $v));
    if ($s === '') return null;
    $neg = false;
    if (preg_match('/^\((.*)\)$/', $s, $m)) { $neg = true; $s = $m[1]; }
    if (substr($s, -1) === '-') { $neg = true; $s = substr($s, 0, -1); }
    if ($s !== '' && $s[0] === '-') { $neg = !$neg; $s = substr($s, 1); }
    $coma = strrpos($s, ','); $punto = strrpos($s, '.');
    if ($coma !== false && $punto !== false) {
        $s = $coma > $punto ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
    } elseif ($coma !== false) {
        $s = (strlen($s) - $coma - 1 === 3 && substr_count($s, ',') >= 1 && !preg_match('/,\d{1,2}$/', $s)) ? str_replace(',', '', $s) : str_replace(',', '.', $s);
    } elseif ($punto !== false && substr_count($s, '.') > 1) {
        $s = str_replace('.', '', $s);
    }
    if (!is_numeric($s)) return null;
    return $neg ? -(float) $s : (float) $s;
}

/** Fecha dd/mm/aaaa, dd-mm-aa, aaaa-mm-dd o número de serie de Excel. */
function concParseFecha($v): ?string
{
    if ($v === null || $v === '') return null;
    if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
        return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
    }
    $s = trim((string) $v);
    $s = preg_replace('/\s.*$/', '', $s); // descarta la hora
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})$/', $s, $m)) [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{2,4})$/', $s, $m)) [$d, $mo, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
    else return null;
    if ($y < 100) $y += 2000;
    return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
}

function concNormHeader($h): string
{
    $h = mb_strtoupper(trim((string) $h), 'UTF-8');
    return strtr($h, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
}

/** Propone qué columna es cada dato, buscando la fila de encabezados en las primeras filas. */
function concAdivinarMapeo(array $filas): array
{
    $syn = [
        'fecha' => ['FECHA', 'FECHA MOV', 'FECHA OPERACION', 'FECHA DE OPERACION', 'FECHA VALOR', 'DATE', 'FECHA DE ORIGEN'],
        'descripcion' => ['DESCRIPCION', 'CONCEPTO', 'DETALLE', 'MOVIMIENTO', 'DESCRIPCION DEL MOVIMIENTO', 'TIPO DE OPERACION'],
        'referencia' => ['REFERENCIA', 'COMPROBANTE', 'NRO', 'NUMERO', 'NRO COMPROBANTE', 'ID DE OPERACION', 'ID OPERACION', 'CODIGO'],
        'importe' => ['IMPORTE', 'MONTO', 'VALOR', 'IMPORTE NETO', 'MONTO NETO', 'NETO', 'MONTO NETO DE OPERACION'],
        'debito' => ['DEBITO', 'DEBITOS', 'DEBE', 'EGRESO', 'EGRESOS', 'RETIRO'],
        'credito' => ['CREDITO', 'CREDITOS', 'HABER', 'INGRESO', 'INGRESOS', 'DEPOSITO'],
        'saldo' => ['SALDO', 'SALDO PARCIAL'],
    ];
    $mejor = ['fila_encabezado' => -1, 'puntaje' => 0];
    foreach (array_slice($filas, 0, 30, true) as $i => $fila) {
        $map = []; $puntaje = 0;
        foreach ($fila as $c => $valor) {
            $h = concNormHeader($valor);
            foreach ($syn as $campo => $lista) {
                if (!isset($map[$campo]) && in_array($h, $lista, true)) { $map[$campo] = $c; $puntaje++; }
            }
        }
        if ($puntaje > $mejor['puntaje']) $mejor = ['fila_encabezado' => $i, 'puntaje' => $puntaje] + $map;
    }
    unset($mejor['puntaje']);
    return $mejor;
}

/**
 * Subconjuntos candidatos para emparejar varios contra uno: todos, solo positivos, solo
 * negativos y, si son pocos (hasta 12), todas las combinaciones de 2 o más, de mayor a menor.
 */
function concVariantes(array $items): array
{
    $out = [$items,
            array_values(array_filter($items, static fn($x) => (float) $x['importe'] > 0)),
            array_values(array_filter($items, static fn($x) => (float) $x['importe'] < 0))];
    $n = count($items);
    if ($n >= 3 && $n <= 12) {
        $combos = [];
        for ($mask = 1; $mask < (1 << $n); $mask++) {
            $c = [];
            for ($i = 0; $i < $n; $i++) if ($mask & (1 << $i)) $c[] = $items[$i];
            if (count($c) >= 2 && count($c) < $n) $combos[] = $c;
        }
        usort($combos, static fn($a, $b) => count($b) <=> count($a));
        $out = array_merge($out, $combos);
    }
    return $out;
}

/** Saldo del sistema (ccbancos) de una cuenta hasta una fecha inclusive. */
function concSaldoSistema(PDO $pdo, int $cuenta, string $hasta): float
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(debe - haber), 0) FROM ccbancos WHERE cuenta = ? AND fecha <= ?");
    $st->execute([$cuenta, $hasta]);
    return round((float) $st->fetchColumn(), 2);
}

function concExtracto(PDO $pdo, int $id, bool $bloquear = false): array
{
    $st = $pdo->prepare("SELECT * FROM extractos_bancarios WHERE id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$id]);
    $e = $st->fetch(PDO::FETCH_ASSOC);
    if (!$e) throw new Exception('Extracto no encontrado.');
    return $e;
}

function concAbierto(array $e): void
{
    if ((int) $e['estado'] === 1) throw new Exception('El extracto está cerrado. Reábralo para modificar la conciliación.');
}

function concNuevoGrupo(PDO $pdo, int $idExtracto): int
{
    $st = $pdo->prepare("SELECT COALESCE(MAX(grupo), 0) + 1 FROM conciliacion_items WHERE idextracto = ?");
    $st->execute([$idExtracto]);
    return (int) $st->fetchColumn();
}

/** Graba un grupo conciliado (líneas del extracto + movimientos de ccbancos). */
function concGrabarGrupo(PDO $pdo, array $e, array $idsLineas, array $idsMov, string $usuario): int
{
    $grupo = concNuevoGrupo($pdo, (int) $e['id']);
    $st = $pdo->prepare("INSERT INTO conciliacion_items (idextracto, grupo, idlinea, idccbancos, usuario) VALUES (?, ?, ?, ?, ?)");
    foreach ($idsLineas as $l) $st->execute([$e['id'], $grupo, $l, null, $usuario]);
    foreach ($idsMov as $m) $st->execute([$e['id'], $grupo, null, $m, $usuario]);
    if ($idsLineas) {
        $in = implode(',', array_fill(0, count($idsLineas), '?'));
        $pdo->prepare("UPDATE extracto_lineas SET estado = 1 WHERE id IN ($in)")->execute($idsLineas);
    }
    return $grupo;
}

/** Movimientos del sistema candidatos: de la cuenta, cerca del período, no conciliados en otro extracto. */
function concMovimientos(PDO $pdo, array $e, int $margenDias = 15): array
{
    $st = $pdo->prepare("SELECT b.id, b.fecha, b.debe, b.haber, ROUND(b.debe - b.haber, 2) AS importe, b.tabla, b.compasoc, b.detalle, b.observacion,
                ci.idextracto AS conc_extracto, ci.grupo
            FROM ccbancos b
            LEFT JOIN conciliacion_items ci ON ci.idccbancos = b.id
            WHERE b.cuenta = ? AND b.fecha BETWEEN DATE_SUB(?, INTERVAL $margenDias DAY) AND DATE_ADD(?, INTERVAL $margenDias DAY)
              AND (ci.id IS NULL OR ci.idextracto = ?)
            ORDER BY b.fecha, b.id");
    $st->execute([$e['cuenta'], $e['desde'], $e['hasta'], $e['id']]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

try {
    switch ($action) {
        case 'cuentas':
            $filas = $pdo->query("SELECT m.id AS idmedio, m.medio, m.cuentabancaria AS cuenta, c.numcuenta, c.alias, b.denominacion AS banco
                FROM mediosdepago m LEFT JOIN cuentaspropias c ON c.id = m.cuentabancaria LEFT JOIN bancos b ON b.id = c.banco
                WHERE m.tipodecuenta = 'Bancos' AND m.cuentabancaria > 0 ORDER BY m.habilitado DESC, m.medio")->fetchAll(PDO::FETCH_ASSOC);
            $hoy = date('Y-m-d');
            foreach ($filas as &$f) $f['saldo'] = concSaldoSistema($pdo, (int) $f['cuenta'], $hoy);
            echo json_encode(['success' => true, 'data' => $filas]);
            break;

        case 'extractos':
            $st = $pdo->prepare("SELECT e.*,
                    (SELECT COUNT(*) FROM extracto_lineas l WHERE l.idextracto = e.id) AS lineas,
                    (SELECT COUNT(*) FROM extracto_lineas l WHERE l.idextracto = e.id AND l.estado = 0) AS pendientes
                FROM extractos_bancarios e WHERE e.cuenta = ? ORDER BY e.desde DESC, e.id DESC");
            $st->execute([(int) ($_GET['cuenta'] ?? 0)]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'leer_archivo':
            // Devuelve las celdas del archivo como texto para que el usuario confirme qué columna es cada dato.
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) throw new Exception('No se recibió el archivo.');
            if ($_FILES['archivo']['size'] > 10 * 1024 * 1024) throw new Exception('El archivo supera los 10 MB.');
            $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) throw new Exception('Formato no admitido: use Excel (.xlsx / .xls) o CSV.');
            $tmp = $_FILES['archivo']['tmp_name'];
            if (in_array($ext, ['csv', 'txt'], true)) {
                $contenido = file_get_contents($tmp);
                if (!mb_check_encoding($contenido, 'UTF-8')) $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
                $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
                $primera = strtok($contenido, "\n");
                $sep = ';';
                foreach ([';' => substr_count($primera, ';'), ',' => substr_count($primera, ','), "\t" => substr_count($primera, "\t")] as $s => $n) {
                    if ($n > substr_count($primera, $sep)) $sep = $s;
                }
                $filas = [];
                foreach (preg_split('/\r\n|\n|\r/', $contenido) as $linea) {
                    if (trim($linea) === '') continue;
                    $filas[] = str_getcsv($linea, $sep);
                }
            } else {
                $libro = IOFactory::load($tmp);
                $hoja = $libro->getActiveSheet();
                $filas = [];
                foreach ($hoja->getRowIterator() as $row) {
                    $celdas = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $v = $cell->getValue();
                        if (\PhpOffice\PhpSpreadsheet\Shared\Date::isDateTime($cell) && is_numeric($v)) $v = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($v)->format('Y-m-d');
                        $celdas[] = is_float($v) || is_int($v) ? $v : trim((string) $v);
                    }
                    if (implode('', array_map('strval', $celdas)) === '') continue;
                    $filas[] = $celdas;
                }
            }
            if (!$filas) throw new Exception('El archivo no tiene datos.');
            if (count($filas) > 20000) throw new Exception('El archivo tiene más de 20.000 filas: divídalo por período.');
            echo json_encode(['success' => true, 'filas' => $filas, 'mapeo' => concAdivinarMapeo($filas), 'archivo' => $_FILES['archivo']['name']], JSON_INVALID_UTF8_SUBSTITUTE);
            break;

        case 'extracto_crear':
            $d = concJson();
            $cuenta = (int) ($d['cuenta'] ?? 0);
            $idMedio = (int) ($d['idmediobanco'] ?? 0);
            $st = $pdo->prepare("SELECT id FROM mediosdepago WHERE id = ? AND tipodecuenta = 'Bancos' AND cuentabancaria = ?");
            $st->execute([$idMedio, $cuenta]);
            if (!$st->fetchColumn()) throw new Exception('Cuenta bancaria inválida.');
            $map = $d['mapeo'] ?? [];
            $col = static fn($k) => isset($map[$k]) && $map[$k] !== '' && $map[$k] !== null ? (int) $map[$k] : null;
            [$cF, $cD, $cR, $cI, $cDeb, $cCred] = [$col('fecha'), $col('descripcion'), $col('referencia'), $col('importe'), $col('debito'), $col('credito')];
            if ($cF === null) throw new Exception('Indique la columna de la fecha.');
            if ($cI === null && $cDeb === null && $cCred === null) throw new Exception('Indique la columna del importe, o las de débito y crédito.');
            $invertir = !empty($d['invertir_signo']);
            $inicio = max(0, (int) ($d['fila_inicio'] ?? 0));
            $lineas = []; $descartadas = 0;
            foreach (array_slice((array) ($d['filas'] ?? []), $inicio) as $f) {
                $fecha = concParseFecha($f[$cF] ?? null);
                if ($cI !== null) {
                    $imp = concNumero($f[$cI] ?? null);
                } else {
                    $deb = $cDeb !== null ? concNumero($f[$cDeb] ?? null) : null;
                    $cred = $cCred !== null ? concNumero($f[$cCred] ?? null) : null;
                    $imp = ($deb === null && $cred === null) ? null : (float) ($cred ?? 0) - abs((float) ($deb ?? 0));
                }
                if (!$fecha || $imp === null || abs($imp) < 0.005) { $descartadas++; continue; }
                if ($invertir) $imp = -$imp;
                $lineas[] = [$fecha, mb_substr(trim((string) ($cD !== null ? ($f[$cD] ?? '') : '')), 0, 255),
                             mb_substr(trim((string) ($cR !== null ? ($f[$cR] ?? '') : '')), 0, 100), round($imp, 2)];
            }
            if (!$lineas) throw new Exception('No se encontró ninguna fila con fecha e importe válidos. Revise la elección de columnas.');
            $fechas = array_column($lineas, 0);
            $desde = !empty($d['desde']) ? concFecha($d['desde'], 'desde') : min($fechas);
            $hasta = !empty($d['hasta']) ? concFecha($d['hasta'], 'hasta') : max($fechas);
            if ($desde > $hasta) throw new Exception('La fecha desde es posterior a la fecha hasta.');
            $saldoIni = concNumero($d['saldo_inicial'] ?? 0) ?? 0.0;
            $saldoFin = (isset($d['saldo_final']) && $d['saldo_final'] !== '') ? (concNumero($d['saldo_final']) ?? 0.0) : round($saldoIni + array_sum(array_column($lineas, 3)), 2);

            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO extractos_bancarios (cuenta, idmediobanco, desde, hasta, saldo_inicial, saldo_final, archivo, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$cuenta, $idMedio, $desde, $hasta, round($saldoIni, 2), round($saldoFin, 2), mb_substr((string) ($d['archivo'] ?? ''), 0, 255), $usuario]);
            $idExt = (int) $pdo->lastInsertId();
            $st = $pdo->prepare("INSERT INTO extracto_lineas (idextracto, orden, fecha, descripcion, referencia, importe) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($lineas as $i => [$f, $desc, $ref, $imp]) $st->execute([$idExt, $i + 1, $f, $desc, $ref, $imp]);
            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idExt, 'lineas' => count($lineas), 'descartadas' => $descartadas,
                              'diferencia_saldos' => round($saldoIni + array_sum(array_column($lineas, 3)) - $saldoFin, 2)]);
            break;

        case 'extracto_get':
            $e = concExtracto($pdo, (int) ($_GET['id'] ?? 0));
            $st = $pdo->prepare("SELECT l.*, ci.grupo FROM extracto_lineas l LEFT JOIN conciliacion_items ci ON ci.idlinea = l.id WHERE l.idextracto = ? ORDER BY l.fecha, l.orden");
            $st->execute([$e['id']]);
            $lineas = $st->fetchAll(PDO::FETCH_ASSOC);
            $movs = concMovimientos($pdo, $e);
            $saldoSistema = concSaldoSistema($pdo, (int) $e['cuenta'], $e['hasta']);
            // Partidas para la conciliación al cierre del período del extracto.
            $movNoConc = 0.0;
            foreach ($movs as $m) if (!$m['conc_extracto'] && $m['fecha'] <= $e['hasta'] && $m['fecha'] >= $e['desde']) $movNoConc += (float) $m['importe'];
            $linNoConc = 0.0; $linIgnoradas = 0.0;
            foreach ($lineas as $l) {
                if ((int) $l['estado'] === 0) $linNoConc += (float) $l['importe'];
                if ((int) $l['estado'] === 3) $linIgnoradas += (float) $l['importe'];
            }
            $st = $pdo->prepare("SELECT COUNT(*) AS n, COALESCE(SUM(b.debe - b.haber), 0) AS s FROM ccbancos b LEFT JOIN conciliacion_items ci ON ci.idccbancos = b.id
                                 WHERE b.cuenta = ? AND b.fecha < ? AND ci.id IS NULL");
            $st->execute([$e['cuenta'], $e['desde']]);
            $previos = $st->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'extracto' => $e, 'lineas' => $lineas, 'movimientos' => $movs, 'resumen' => [
                'saldo_extracto' => (float) $e['saldo_final'],
                'saldo_sistema' => $saldoSistema,
                'diferencia' => round($saldoSistema - (float) $e['saldo_final'], 2),
                'mov_sistema_sin_conciliar' => round($movNoConc, 2),
                'lineas_extracto_pendientes' => round($linNoConc, 2),
                'lineas_extracto_ignoradas' => round($linIgnoradas, 2),
                'previos_sin_conciliar_cant' => (int) $previos['n'],
                'previos_sin_conciliar_importe' => round((float) $previos['s'], 2),
            ]]);
            break;

        case 'auto':
            $d = concJson();
            $dias = max(0, min(15, (int) ($d['dias'] ?? 3)));
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $st = $pdo->prepare("SELECT id, fecha, importe FROM extracto_lineas WHERE idextracto = ? AND estado = 0 ORDER BY fecha, orden");
            $st->execute([$e['id']]);
            $lineas = $st->fetchAll(PDO::FETCH_ASSOC);
            $movs = array_values(array_filter(concMovimientos($pdo, $e, $dias), static fn($m) => !$m['conc_extracto']));
            $usados = [];
            $dif = static fn($a, $b) => abs((strtotime($a) - strtotime($b)) / 86400);
            $n1 = $nG = 0;
            // 1) Uno a uno: mismo importe y fecha cercana (la más cercana gana).
            foreach ($lineas as $k => $l) {
                $mejor = null;
                foreach ($movs as $m) {
                    if (isset($usados[$m['id']]) || abs((float) $m['importe'] - (float) $l['importe']) > CONC_TOL) continue;
                    $dd = $dif($m['fecha'], $l['fecha']);
                    if ($dd <= $dias && ($mejor === null || $dd < $mejor[0])) $mejor = [$dd, $m['id']];
                }
                if ($mejor) {
                    $usados[$mejor[1]] = true;
                    concGrabarGrupo($pdo, $e, [(int) $l['id']], [(int) $mejor[1]], $usuario);
                    unset($lineas[$k]); $n1++;
                }
            }
            // 2) Una línea contra varios movimientos libres de un mismo día (ej. MercadoPago
            //    acredita el total del día y el sistema tiene una fila por venta). Se prueban
            //    todos los del día, solo los ingresos y solo los egresos.
            $porDia = [];
            foreach ($movs as $m) if (!isset($usados[$m['id']])) $porDia[$m['fecha']][] = $m;
            foreach ($lineas as $k => $l) {
                foreach ($porDia as $fecha => $grupoMov) {
                    if ($dif($fecha, $l['fecha']) > $dias) continue;
                    $libres = array_values(array_filter($grupoMov, static fn($m) => !isset($usados[$m['id']])));
                    $hallado = null;
                    foreach (concVariantes($libres) as $cand) {
                        if (count($cand) >= 2 && abs(array_sum(array_column($cand, 'importe')) - (float) $l['importe']) <= CONC_TOL) { $hallado = $cand; break; }
                    }
                    if ($hallado) {
                        foreach ($hallado as $m) $usados[$m['id']] = true;
                        concGrabarGrupo($pdo, $e, [(int) $l['id']], array_map(static fn($m) => (int) $m['id'], $hallado), $usuario);
                        unset($lineas[$k]); $nG++;
                        break;
                    }
                }
            }
            // 3) Varias líneas de un mismo día contra un único movimiento (ej. una transferencia
            //    que el banco muestra partida).
            $lineasPorDia = [];
            foreach ($lineas as $l) $lineasPorDia[$l['fecha']][] = $l;
            foreach ($lineasPorDia as $fecha => $grupoLin) {
                $pendientesDia = $grupoLin;
                foreach ($movs as $m) {
                    if (isset($usados[$m['id']]) || $dif($m['fecha'], $fecha) > $dias || count($pendientesDia) < 2) continue;
                    foreach (concVariantes($pendientesDia) as $cand) {
                        if (count($cand) >= 2 && abs(array_sum(array_column($cand, 'importe')) - (float) $m['importe']) <= CONC_TOL) {
                            $usados[$m['id']] = true;
                            concGrabarGrupo($pdo, $e, array_map(static fn($x) => (int) $x['id'], $cand), [(int) $m['id']], $usuario);
                            $idsCand = array_column($cand, 'id');
                            $pendientesDia = array_values(array_filter($pendientesDia, static fn($x) => !in_array($x['id'], $idsCand, true)));
                            $nG++;
                            break;
                        }
                    }
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'uno_a_uno' => $n1, 'agrupados' => $nG]);
            break;

        case 'conciliar':
            $d = concJson();
            $idsL = array_values(array_unique(array_map('intval', (array) ($d['lineas'] ?? []))));
            $idsM = array_values(array_unique(array_map('intval', (array) ($d['movimientos'] ?? []))));
            if (!$idsL || !$idsM) throw new Exception('Elija al menos una línea del extracto y un movimiento del sistema.');
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $in = implode(',', array_fill(0, count($idsL), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(importe), 0), COUNT(*) FROM extracto_lineas WHERE idextracto = ? AND estado = 0 AND id IN ($in) FOR UPDATE");
            $st->execute(array_merge([$e['id']], $idsL));
            [$sumaL, $cantL] = $st->fetch(PDO::FETCH_NUM);
            if ((int) $cantL !== count($idsL)) throw new Exception('Alguna línea ya no está pendiente. Actualice la pantalla.');
            $in = implode(',', array_fill(0, count($idsM), '?'));
            $st = $pdo->prepare("SELECT COALESCE(SUM(b.debe - b.haber), 0), COUNT(*), SUM(ci.id IS NOT NULL) FROM ccbancos b LEFT JOIN conciliacion_items ci ON ci.idccbancos = b.id
                                 WHERE b.cuenta = ? AND b.id IN ($in)");
            $st->execute(array_merge([$e['cuenta']], $idsM));
            [$sumaM, $cantM, $yaConc] = $st->fetch(PDO::FETCH_NUM);
            if ((int) $cantM !== count($idsM) || (int) $yaConc) throw new Exception('Algún movimiento no es de esta cuenta o ya está conciliado.');
            if (abs((float) $sumaL - (float) $sumaM) > CONC_TOL) {
                throw new Exception('No coinciden: extracto $' . number_format((float) $sumaL, 2, ',', '.') . ' / sistema $' . number_format((float) $sumaM, 2, ',', '.') . '.');
            }
            $grupo = concGrabarGrupo($pdo, $e, $idsL, $idsM, $usuario);
            $pdo->commit();
            echo json_encode(['success' => true, 'grupo' => $grupo]);
            break;

        case 'desconciliar':
            $d = concJson();
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $grupo = (int) ($d['grupo'] ?? 0);
            $pdo->prepare("UPDATE extracto_lineas SET estado = 0 WHERE id IN (SELECT idlinea FROM conciliacion_items WHERE idextracto = ? AND grupo = ? AND idlinea IS NOT NULL)")
                ->execute([$e['id'], $grupo]);
            $pdo->prepare("DELETE FROM conciliacion_items WHERE idextracto = ? AND grupo = ?")->execute([$e['id'], $grupo]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'ignorar':
            $d = concJson();
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $restaurar = !empty($d['restaurar']);
            $st = $pdo->prepare("UPDATE extracto_lineas SET estado = ?, nota = ? WHERE id = ? AND idextracto = ? AND estado = ?");
            $st->execute([$restaurar ? 0 : 3, $restaurar ? '' : mb_substr(trim((string) ($d['nota'] ?? '')), 0, 255), (int) ($d['linea'] ?? 0), $e['id'], $restaurar ? 3 : 0]);
            if (!$st->rowCount()) throw new Exception('La línea no está en el estado esperado. Actualice la pantalla.');
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'registrar':
            // Movimiento que el banco hizo y el sistema no tiene: genera ccbancos + tesorería +
            // asiento contra la cuenta contable elegida, y lo concilia con la línea.
            $d = concJson();
            $cuentaContra = trim((string) ($d['cuenta_contable'] ?? ''));
            $desc = mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 200);
            if (!cuentaContableExiste($pdo, $cuentaContra)) throw new Exception('Elija una cuenta contable válida para la contrapartida.');
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $st = $pdo->prepare("SELECT * FROM extracto_lineas WHERE id = ? AND idextracto = ? AND estado = 0 FOR UPDATE");
            $st->execute([(int) ($d['linea'] ?? 0), $e['id']]);
            $l = $st->fetch(PDO::FETCH_ASSOC);
            if (!$l) throw new Exception('La línea ya no está pendiente. Actualice la pantalla.');
            $ejPer = resolverEjercicioPeriodo($pdo, $l['fecha']);
            $imp = (float) $l['importe'];
            $debe = $imp > 0 ? $imp : 0; $haber = $imp < 0 ? -$imp : 0;
            $detalle = mb_substr('Conciliación: ' . ($desc !== '' ? $desc : $l['descripcion']), 0, 250);
            $ctaBanco = resolverCuentaMedioPago($pdo, (int) $e['idmediobanco'], 'la cuenta bancaria');

            $stS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
            $stS->execute([$e['cuenta']]);
            $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle, observacion) VALUES (?, ?, ?, ?, ?, ?, 'conciliacion', ?, ?)")
                ->execute([$e['cuenta'], $debe, $haber, round((float) ($stS->fetchColumn() ?: 0) + $imp, 2), $l['fecha'], $l['id'], $detalle, mb_substr($l['referencia'], 0, 200)]);
            $idMov = (int) $pdo->lastInsertId();
            $numTeso = ((int) $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria")->fetchColumn()) + 1;
            $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, ?, 'conciliacion', ?, ?, ?, 1, ?, ?)")
                ->execute([$e['idmediobanco'], $debe, $haber, $usuario, $l['fecha'], $detalle, $l['id'], $numTeso]);
            $numAsiento = siguienteNumeroAsiento($pdo, $ejPer['ejercicio']);
            grabarLineaAsiento($pdo, $numAsiento, $l['fecha'], $ctaBanco, $debe, $haber, $l['id'], 'conciliacion', 1, $usuario, $detalle, $ejPer['ejercicio'], $ejPer['periodo']);
            grabarLineaAsiento($pdo, $numAsiento, $l['fecha'], $cuentaContra, $haber, $debe, $l['id'], 'conciliacion', 1, $usuario, $detalle, $ejPer['ejercicio'], $ejPer['periodo']);
            concGrabarGrupo($pdo, $e, [(int) $l['id']], [$idMov], $usuario);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Registrado (asiento #$numAsiento) y conciliado."]);
            break;

        case 'cerrar':
            $d = concJson();
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            $reabrir = !empty($d['reabrir']);
            if (!$reabrir) {
                $st = $pdo->prepare("SELECT COUNT(*) FROM extracto_lineas WHERE idextracto = ? AND estado = 0");
                $st->execute([$e['id']]);
                if ((int) $st->fetchColumn()) throw new Exception('Quedan líneas del extracto pendientes: concílielas, regístrelas o márquelas como ignoradas.');
            }
            $pdo->prepare("UPDATE extractos_bancarios SET estado = ?, cerrado_por = ?, cerrado_el = " . ($reabrir ? 'NULL' : 'NOW()') . " WHERE id = ?")
                ->execute([$reabrir ? 0 : 1, $reabrir ? '' : $usuario, $e['id']]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'extracto_eliminar':
            $d = concJson();
            $pdo->beginTransaction();
            $e = concExtracto($pdo, (int) ($d['id'] ?? 0), true);
            concAbierto($e);
            $st = $pdo->prepare("SELECT COUNT(*) FROM extracto_lineas l JOIN ccbancos b ON b.tabla = 'conciliacion' AND b.compasoc = l.id WHERE l.idextracto = ?");
            $st->execute([$e['id']]);
            if ((int) $st->fetchColumn()) throw new Exception('El extracto tiene movimientos registrados desde la conciliación (comisiones, impuestos, etc.): no se puede eliminar. Puede dejarlo abierto o cerrarlo.');
            $pdo->prepare("DELETE FROM conciliacion_items WHERE idextracto = ?")->execute([$e['id']]);
            $pdo->prepare("DELETE FROM extracto_lineas WHERE idextracto = ?")->execute([$e['id']]);
            $pdo->prepare("DELETE FROM extractos_bancarios WHERE id = ?")->execute([$e['id']]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'mayor':
            // Mayor de la cuenta bancaria con saldo calculado (no se usa ccbancos.saldo).
            $d = concJson();
            $cuenta = (int) ($d['cuenta'] ?? 0);
            $desde = concFecha($d['desde'] ?? '', 'desde');
            $hasta = concFecha($d['hasta'] ?? '', 'hasta');
            $st = $pdo->prepare("SELECT COALESCE(SUM(debe - haber), 0) FROM ccbancos WHERE cuenta = ? AND fecha < ?");
            $st->execute([$cuenta, $desde]);
            $saldo = round((float) $st->fetchColumn(), 2);
            $saldoAnterior = $saldo;
            $st = $pdo->prepare("SELECT b.id, b.fecha, b.debe, b.haber, b.tabla, b.compasoc, b.detalle, b.observacion, ci.idextracto
                                 FROM ccbancos b LEFT JOIN conciliacion_items ci ON ci.idccbancos = b.id
                                 WHERE b.cuenta = ? AND b.fecha BETWEEN ? AND ? ORDER BY b.fecha, b.id");
            $st->execute([$cuenta, $desde, $hasta]);
            $filas = $st->fetchAll(PDO::FETCH_ASSOC);
            foreach ($filas as &$f) { $saldo = round($saldo + (float) $f['debe'] - (float) $f['haber'], 2); $f['saldo'] = $saldo; }
            echo json_encode(['success' => true, 'saldo_anterior' => $saldoAnterior, 'saldo_final' => $saldo, 'data' => $filas]);
            break;

        case 'plan_cuentas':
            $filas = $pdo->query("SELECT numcuenta, descripcion FROM plandecuentas WHERE imputable = 1 AND habilitada = 1 ORDER BY numcuenta")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $filas]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
