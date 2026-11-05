<?php
/**
 * libro_iva_helpers.php - Libro IVA Ventas / Compras y exportación al Libro IVA Digital
 * de ARCA (RG 4597: VENTAS_CBTE 266 posiciones, VENTAS_ALICUOTAS 62, COMPRAS_CBTE 325,
 * COMPRAS_ALICUOTAS 84).
 *
 * VENTAS: el libro tiene que coincidir con lo que se le declaró a ARCA al pedir el CAE,
 * no con las columnas crudas de `tickets`. api_facturar.php prorratea el descuento/recargo
 * global entre las alícuotas antes de armar ImpNeto/ImpIVA, pero graba en tickets los
 * importes SIN prorratear (subtotal/iva = suma de ítems; descuento/recargo aparte). Acá se
 * repite exactamente ese prorrateo (ivaVentaComoArca) para reconstruir lo declarado.
 * Lo que no es 21% ni 10,5% está todo junto en `subtotal0` (exento, no gravado y las pocas
 * líneas al 27%): se informa como alícuota 0%, que es como lo recibió ARCA dentro de ImpNeto.
 *
 * COMPRAS: `facturasdecompra` tiene un solo neto e IVA separado por alícuota (21/10,5/27).
 * El neto por alícuota sale de las líneas de concepto (lineaconceptoscompra) cuando suman el
 * neto; si no (datos migrados sin líneas), se deduce de cada IVA (neto = IVA / alícuota) y lo
 * que sobra va a 0%. Las notas de crédito están grabadas en negativo.
 * Percepciones sufridas: retiva = percepción de IVA, retiibb = percepción de IIBB.
 *
 * Signos: en pantalla y en Excel las notas de crédito restan (importes negativos). En los
 * TXT de ARCA todos los importes van en positivo: el tipo de comprobante ya indica que es NC.
 */

const LIVA_TIPOS_FISCALES = [1, 2, 3, 6, 7, 8, 11, 12, 13];
const LIVA_TIPOS_NC = [3, 8, 13];
const LIVA_TIPOS_C = [11, 12, 13];
const LIVA_TIPOS_B_C = [6, 7, 8, 11, 12, 13];
const LIVA_SIGLAS = [1 => 'FCA', 2 => 'NDA', 3 => 'NCA', 6 => 'FCB', 7 => 'NDB', 8 => 'NCB',
                     11 => 'FCC', 12 => 'NDC', 13 => 'NCC'];
// Código de alícuota de ARCA por porcentaje
const LIVA_COD_ALICUOTA = ['0' => 3, '10.5' => 4, '21' => 5, '27' => 6, '5' => 8, '2.5' => 9];

function livaR2($v): float { return round((float) $v, 2); }

/** Código de documento ARCA: 80 CUIT, 86 CUIL, 96 DNI, 99 sin identificar. */
function livaDocumento(?string $tipodoc, ?string $numdoc, bool $consumidorFinal): array
{
    $nro = preg_replace('/\D/', '', (string) $numdoc);
    if ($consumidorFinal && strlen($nro) !== 11) {
        // Venta a consumidor final: ARCA la recibió como 99 / 0 (ver api_facturar.php).
        return ['cod' => 99, 'nro' => '0'];
    }
    $t = strtoupper(trim((string) $tipodoc));
    if ($t === 'CUIT' || ($t === '' && strlen($nro) === 11)) return ['cod' => 80, 'nro' => $nro ?: '0'];
    if ($t === 'CUIL') return ['cod' => 86, 'nro' => $nro ?: '0'];
    if ($t === 'DNI' && $nro !== '') return ['cod' => 96, 'nro' => $nro];
    if (strlen($nro) === 11) return ['cod' => 80, 'nro' => $nro];
    return ['cod' => 99, 'nro' => '0'];
}

/** Misma regla que mapCondicionArca() de api_facturar.php: todo lo que no es RI/Exento/Monotributo es CF. */
function livaEsConsumidorFinal(?string $condicion): bool
{
    return !in_array(trim((string) $condicion), ['Responsable Inscripto', 'Exento', 'Monotributo'], true);
}

/**
 * Reconstruye lo declarado a ARCA para una venta: mismo prorrateo del ajuste global que
 * api_facturar.php (bloque "El descuento/recargo global ... ya está incluido").
 */
function ivaVentaComoArca(array $t): array
{
    $sub21 = (float) $t['subtotal']; $iva21 = (float) $t['iva'];
    $sub10 = (float) $t['subtotal10']; $iva10 = (float) $t['iva10'];
    $sub0 = (float) $t['subtotal0'];
    $ajusteG = (float) $t['recargo'] - (float) $t['descuento'];
    if ($ajusteG != 0) {
        $baseF = $sub21 + $iva21 + $sub10 + $iva10 + $sub0;
        if ($baseF > 0) {
            $montoF21 = $ajusteG * (($sub21 + $iva21) / $baseF);
            $montoF10 = $ajusteG * (($sub10 + $iva10) / $baseF);
            $montoF0 = $ajusteG * ($sub0 / $baseF);
            $netoF21 = round($montoF21 / 1.21, 2);
            $iva21 += round($montoF21 - $netoF21, 2);
            $sub21 += $netoF21;
            $netoF10 = round($montoF10 / 1.105, 2);
            $iva10 += round($montoF10 - $netoF10, 2);
            $sub10 += $netoF10;
            $sub0 += round($montoF0, 2);
        }
    }
    return ['neto21' => livaR2($sub21), 'iva21' => livaR2($iva21), 'neto10' => livaR2($sub10),
            'iva10' => livaR2($iva10), 'neto0' => livaR2($sub0)];
}

/**
 * Percepciones practicadas en ventas (módulo de agente de percepción). Si la tabla todavía
 * no existe en la base, no hay percepciones.
 */
function livaPercepcionesVentas(PDO $pdo, array $ids): array
{
    if (!$ids) return [];
    try {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT p.idticket, r.impuesto, SUM(p.importe) AS importe
            FROM percepciones_practicadas p JOIN regimenes_retper r ON r.id = p.idregimen
            WHERE p.idticket IN ($in) AND p.estado <> 1000 GROUP BY p.idticket, r.impuesto");
        $st->execute($ids);
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $clave = in_array($r['impuesto'], ['IVA', 'GANANCIAS'], true) ? 'nacionales' : ($r['impuesto'] === 'IIBB' ? 'iibb' : 'municipales');
        $out[(int) $r['idticket']][$clave] = ($out[(int) $r['idticket']][$clave] ?? 0) + abs((float) $r['importe']);
    }
    return $out;
}

/**
 * Filas del Libro IVA Ventas. Cada fila trae los importes con signo (NC en negativo),
 * las alícuotas para el TXT y una lista de observaciones.
 */
function libroIvaVentas(PDO $pdo, string $desde, string $hasta): array
{
    $in = implode(',', LIVA_TIPOS_FISCALES);
    $st = $pdo->prepare("SELECT t.id, t.fecha, t.tipocomp, t.PtoVta, t.numcomp, t.cae, t.NumdocCli,
                t.subtotal, t.subtotal10, t.subtotal0, t.iva, t.iva10, t.ii, t.recargo, t.descuento, t.total,
                c.razon, c.tipodoc, c.numdoc, c.condicionfiscal
            FROM tickets t LEFT JOIN alb_clientes c ON c.id = t.cliente
            WHERE t.fecha BETWEEN ? AND ? AND t.tipocomp IN ($in)
            ORDER BY t.fecha, t.tipocomp, t.PtoVta, t.numcomp");
    $st->execute([$desde, $hasta]);
    $tickets = $st->fetchAll(PDO::FETCH_ASSOC);
    $percep = livaPercepcionesVentas($pdo, array_map(static fn($t) => (int) $t['id'], $tickets));

    $filas = [];
    foreach ($tickets as $t) {
        $tipo = (int) $t['tipocomp'];
        $s = in_array($tipo, LIVA_TIPOS_NC, true) ? -1 : 1;
        $esC = in_array($tipo, LIVA_TIPOS_C, true);
        $a = ivaVentaComoArca($t);
        $ii = livaR2($t['ii']);
        $p = $percep[(int) $t['id']] ?? [];
        $perNac = livaR2($p['nacionales'] ?? 0);
        $perIibb = livaR2($p['iibb'] ?? 0);
        $perMun = livaR2($p['municipales'] ?? 0);
        $total = livaR2($t['total']);
        $obs = [];

        if ($esC) {
            // Factura C: ARCA recibió neto + IVA como neto, sin alícuotas.
            $neto21 = $iva21 = $neto10 = $iva10 = 0.0;
            $neto0 = livaR2($a['neto21'] + $a['iva21'] + $a['neto10'] + $a['iva10'] + $a['neto0']);
            $alicuotas = [];
        } else {
            $neto21 = $a['neto21']; $iva21 = $a['iva21']; $neto10 = $a['neto10']; $iva10 = $a['iva10']; $neto0 = $a['neto0'];
            $alicuotas = [];
            if ($neto21 != 0 || $iva21 != 0) $alicuotas[] = ['cod' => 5, 'neto' => $neto21, 'iva' => $iva21];
            if ($neto10 != 0 || $iva10 != 0) $alicuotas[] = ['cod' => 4, 'neto' => $neto10, 'iva' => $iva10];
            if ($neto0 != 0 || !$alicuotas) $alicuotas[] = ['cod' => 3, 'neto' => $neto0, 'iva' => 0.0];
        }
        // El total del libro es el que recibió ARCA (ArcaFunctions arma ImpTotal = neto + IVA +
        // tributos), no tickets.total: pueden diferir en centavos por el prorrateo. Una
        // diferencia mayor indica un comprobante viejo o mal grabado y se avisa.
        $noGravado = 0.0;
        $totalDeclarado = livaR2($neto21 + $iva21 + $neto10 + $iva10 + $neto0 + $ii + $perNac + $perIibb + $perMun);
        if (abs($total - $totalDeclarado) > 0.05) {
            $obs[] = 'El total grabado ($' . number_format($total, 2, ',', '.') . ') difiere de neto + IVA + tributos ($' . number_format($totalDeclarado, 2, ',', '.') . ').';
        }
        $total = $totalDeclarado;
        if (trim((string) $t['cae']) === '') $obs[] = 'Sin CAE.';

        $cf = livaEsConsumidorFinal($t['condicionfiscal']);
        $doc = livaDocumento($t['tipodoc'], $t['numdoc'], $cf);
        if (in_array($tipo, [1, 2, 3], true) && $doc['cod'] !== 80) $obs[] = 'Comprobante A sin CUIT del cliente.';

        $filas[] = [
            'id' => (int) $t['id'], 'fecha' => $t['fecha'], 'tipocomp' => $tipo,
            'sigla' => LIVA_SIGLAS[$tipo] ?? (string) $tipo, 'pv' => (int) $t['PtoVta'], 'numero' => (int) $t['numcomp'],
            'cliente' => (string) ($t['razon'] ?? ''), 'condicion' => (string) ($t['condicionfiscal'] ?? ''),
            'doc_cod' => $doc['cod'], 'doc_nro' => $doc['nro'],
            'neto21' => $s * $neto21, 'iva21' => $s * $iva21, 'neto10' => $s * $neto10, 'iva10' => $s * $iva10,
            'neto0' => $s * $neto0, 'no_gravado' => $s * $noGravado, 'imp_internos' => $s * $ii,
            'perc_nacionales' => $s * $perNac, 'perc_iibb' => $s * $perIibb, 'perc_municipales' => $s * $perMun,
            'total' => $s * $total,
            'alicuotas' => $alicuotas, // en positivo, para el TXT
            'obs' => $obs,
        ];
    }
    return $filas;
}

/** Filas del Libro IVA Compras (comprobantes fiscales, no anulados). */
function libroIvaCompras(PDO $pdo, string $desde, string $hasta, string $campoFecha = 'fechaimputacion'): array
{
    $campoFecha = $campoFecha === 'fecha' ? 'fecha' : 'fechaimputacion';
    $in = implode(',', LIVA_TIPOS_FISCALES);
    $st = $pdo->prepare("SELECT f.id, f.fecha, f.fechaimputacion, f.tipocomp, f.pv, f.numfact, f.neto, f.iva, f.iva10, f.iva27,
                f.impint, f.retiva, f.retiibb, f.total, p.Razon AS razon, p.cuit, p.tipodoc, p.condicion
            FROM facturasdecompra f LEFT JOIN Proveedores p ON p.Id = f.proveedorid
            WHERE f.$campoFecha BETWEEN ? AND ? AND f.fiscal = 1 AND f.estado <> 1000 AND f.tipocomp IN ($in)
            ORDER BY f.$campoFecha, f.fecha, f.id");
    $st->execute([$desde, $hasta]);
    $facturas = $st->fetchAll(PDO::FETCH_ASSOC);

    $lineas = [];
    if ($facturas) {
        $ids = array_map(static fn($f) => (int) $f['id'], $facturas);
        $inIds = implode(',', array_fill(0, count($ids), '?'));
        $stL = $pdo->prepare("SELECT idfactura, alicuota, SUM(ABS(importe)) AS importe FROM lineaconceptoscompra WHERE idfactura IN ($inIds) GROUP BY idfactura, alicuota");
        $stL->execute($ids);
        foreach ($stL->fetchAll(PDO::FETCH_ASSOC) as $l) {
            $lineas[(int) $l['idfactura']][(string) (float) $l['alicuota']] = (float) $l['importe'];
        }
    }

    $filas = [];
    foreach ($facturas as $f) {
        $tipo = (int) $f['tipocomp'];
        $s = in_array($tipo, LIVA_TIPOS_NC, true) ? -1 : 1;
        $neto = abs((float) $f['neto']);
        $iva21 = livaR2(abs((float) $f['iva'])); $iva10 = livaR2(abs((float) $f['iva10'])); $iva27 = livaR2(abs((float) $f['iva27']));
        $ii = livaR2(abs((float) $f['impint']));
        $percIva = livaR2(abs((float) $f['retiva'])); $percIibb = livaR2(abs((float) $f['retiibb']));
        $total = livaR2(abs((float) $f['total']));
        $obs = [];

        // Neto por alícuota: de las líneas si cierran contra el neto; si no, deducido del IVA.
        $porAli = $lineas[(int) $f['id']] ?? [];
        if ($porAli && abs(array_sum($porAli) - $neto) <= 0.05) {
            $n21 = livaR2($porAli['21'] ?? 0); $n10 = livaR2($porAli['10.5'] ?? 0); $n27 = livaR2($porAli['27'] ?? 0);
            $n0 = livaR2($neto - $n21 - $n10 - $n27);
        } else {
            $n21 = livaR2($iva21 / 0.21); $n10 = livaR2($iva10 / 0.105); $n27 = livaR2($iva27 / 0.27);
            $n0 = livaR2($neto - $n21 - $n10 - $n27);
            if ($n0 < 0) { // redondeo: se absorbe en la alícuota principal
                if ($n21 > 0) $n21 = livaR2($n21 + $n0); elseif ($n10 > 0) $n10 = livaR2($n10 + $n0); else $n27 = livaR2($n27 + $n0);
                $n0 = 0.0;
            }
            if ($porAli) $obs[] = 'Las líneas de concepto no suman el neto: neto por alícuota deducido del IVA.';
        }

        $noGravado = livaR2($total - ($n21 + $n10 + $n27 + $n0 + $iva21 + $iva10 + $iva27 + $ii + $percIva + $percIibb));
        if ($noGravado < 0) {
            // Sobran centavos (o más, si la factura se cargó mal): se descuentan del neto para
            // que el total siga siendo el del comprobante y ningún importe quede negativo.
            if (abs($noGravado) > 1) $obs[] = 'Neto + IVA + percepciones supera el total en $' . number_format(-$noGravado, 2, ',', '.') . ': se ajustó el neto.';
            foreach (['n0', 'n21', 'n10', 'n27'] as $var) {
                if ($$var >= -$noGravado) { $$var = livaR2($$var + $noGravado); $noGravado = 0.0; break; }
            }
            if ($noGravado < 0) { $obs[] = 'No se pudo ajustar el neto: revisar el comprobante.'; $noGravado = 0.0; }
        } elseif ($noGravado > 1) {
            $obs[] = 'Hay $' . number_format($noGravado, 2, ',', '.') . ' que no son neto, IVA ni percepciones: se informan como no gravado.';
        }

        $esBC = in_array($tipo, LIVA_TIPOS_B_C, true);
        $alicuotas = [];
        if (!$esBC) {
            if ($n21 != 0 || $iva21 != 0) $alicuotas[] = ['cod' => 5, 'neto' => $n21, 'iva' => $iva21];
            if ($n10 != 0 || $iva10 != 0) $alicuotas[] = ['cod' => 4, 'neto' => $n10, 'iva' => $iva10];
            if ($n27 != 0 || $iva27 != 0) $alicuotas[] = ['cod' => 6, 'neto' => $n27, 'iva' => $iva27];
            if ($n0 != 0 || !$alicuotas) $alicuotas[] = ['cod' => 3, 'neto' => $n0, 'iva' => 0.0];
        }

        $doc = livaDocumento($f['tipodoc'], $f['cuit'], false);
        if ($doc['cod'] !== 80) $obs[] = 'Proveedor sin CUIT válido.';

        $filas[] = [
            'id' => (int) $f['id'], 'fecha' => $f['fecha'], 'fechaimputacion' => $f['fechaimputacion'], 'tipocomp' => $tipo,
            'sigla' => LIVA_SIGLAS[$tipo] ?? (string) $tipo, 'pv' => (int) $f['pv'], 'numero' => (int) preg_replace('/\D/', '', (string) $f['numfact']),
            'proveedor' => (string) ($f['razon'] ?? ''), 'condicion' => (string) ($f['condicion'] ?? ''),
            'doc_cod' => $doc['cod'], 'doc_nro' => $doc['nro'],
            'neto21' => $s * $n21, 'iva21' => $s * $iva21, 'neto10' => $s * $n10, 'iva10' => $s * $iva10,
            'neto27' => $s * $n27, 'iva27' => $s * $iva27, 'neto0' => $s * $n0, 'no_gravado' => $s * $noGravado,
            'imp_internos' => $s * $ii, 'perc_iva' => $s * $percIva, 'perc_iibb' => $s * $percIibb,
            'credito_fiscal' => $esBC ? 0.0 : $s * livaR2($iva21 + $iva10 + $iva27),
            'total' => $s * $total,
            'alicuotas' => $alicuotas,
            'obs' => $obs,
        ];
    }
    return $filas;
}

// --------------------------------------------------------------------------------------
// TXT del Libro IVA Digital
// --------------------------------------------------------------------------------------

/** Importe en centavos, 13 enteros + 2 decimales, sin signo (los TXT van en positivo). */
function livaImp($v, int $len = 15): string
{
    return str_pad((string) (int) round(abs((float) $v) * 100), $len, '0', STR_PAD_LEFT);
}

function livaTexto(string $s, int $len): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    $ascii = strtoupper(preg_replace('/[^A-Za-z0-9 .,&\-]/', '', (string) ($ascii === false ? $s : $ascii)));
    return str_pad(substr($ascii, 0, $len), $len, ' ', STR_PAD_RIGHT);
}

function livaNum($v, int $len): string { return str_pad(preg_replace('/\D/', '', (string) $v) ?: '0', $len, '0', STR_PAD_LEFT); }

/** Código de operación: 0 si hay algo gravado; E (exento) si todo es 0%/no gravado. */
function livaCodigoOperacion(array $f): string
{
    foreach ($f['alicuotas'] as $a) if ($a['cod'] !== 3 && $a['neto'] != 0) return '0';
    return 'E';
}

function txtVentasComprobantes(array $filas): string
{
    $out = '';
    foreach ($filas as $f) {
        $exento = 0.0;
        foreach ($f['alicuotas'] as $a) if ($a['cod'] === 3) $exento += $a['neto'];
        $esC = in_array($f['tipocomp'], LIVA_TIPOS_C, true);
        $linea = str_replace('-', '', $f['fecha'])              // 1 Fecha
            . livaNum($f['tipocomp'], 3)                         // 2 Tipo
            . livaNum($f['pv'], 5)                               // 3 Punto de venta
            . livaNum($f['numero'], 20)                          // 4 Número desde
            . livaNum($f['numero'], 20)                          // 5 Número hasta
            . livaNum($f['doc_cod'], 2)                          // 6 Código documento
            . livaNum($f['doc_nro'], 20)                         // 7 Número documento
            . livaTexto($f['cliente'] ?: 'CONSUMIDOR FINAL', 30) // 8 Nombre
            . livaImp($f['total'])                               // 9 Total
            . livaImp($f['no_gravado'])                          // 10 No integran neto gravado
            . livaImp(0)                                         // 11 Percepción a no categorizados
            . livaImp(0)                                         // 12 Operaciones exentas (el 0% va como alícuota 3)
            . livaImp($f['perc_nacionales'])                     // 13 Percepciones nacionales
            . livaImp($f['perc_iibb'])                           // 14 Percepciones IIBB
            . livaImp($f['perc_municipales'])                    // 15 Percepciones municipales
            . livaImp($f['imp_internos'])                        // 16 Impuestos internos
            . 'PES'                                              // 17 Moneda
            . '0001000000'                                       // 18 Tipo de cambio
            . (string) count($f['alicuotas'])                    // 19 Cantidad de alícuotas
            . ($esC ? '0' : livaCodigoOperacion($f))             // 20 Código de operación
            . livaImp(0)                                         // 21 Otros tributos
            . '00000000';                                        // 22 Fecha vencimiento de pago
        $out .= $linea . "\r\n";
    }
    return $out;
}

function txtVentasAlicuotas(array $filas): string
{
    $out = '';
    foreach ($filas as $f) {
        foreach ($f['alicuotas'] as $a) {
            $out .= livaNum($f['tipocomp'], 3) . livaNum($f['pv'], 5) . livaNum($f['numero'], 20)
                . livaImp($a['neto']) . livaNum($a['cod'], 4) . livaImp($a['iva']) . "\r\n";
        }
    }
    return $out;
}

function txtComprasComprobantes(array $filas): string
{
    $out = '';
    foreach ($filas as $f) {
        $linea = str_replace('-', '', $f['fecha'])              // 1 Fecha del comprobante
            . livaNum($f['tipocomp'], 3)                         // 2 Tipo
            . livaNum($f['pv'], 5)                               // 3 Punto de venta
            . livaNum($f['numero'], 20)                          // 4 Número
            . str_repeat(' ', 16)                                // 5 Despacho de importación
            . livaNum($f['doc_cod'], 2)                          // 6 Código documento vendedor
            . livaNum($f['doc_nro'], 20)                         // 7 Número documento vendedor
            . livaTexto($f['proveedor'], 30)                     // 8 Nombre vendedor
            . livaImp($f['total'])                               // 9 Total
            . livaImp($f['no_gravado'])                          // 10 No integran neto gravado
            . livaImp(0)                                         // 11 Operaciones exentas (el 0% va como alícuota 3)
            . livaImp($f['perc_iva'])                            // 12 Percepciones de IVA
            . livaImp(0)                                         // 13 Percepciones otros impuestos nacionales
            . livaImp($f['perc_iibb'])                           // 14 Percepciones IIBB
            . livaImp(0)                                         // 15 Percepciones municipales
            . livaImp($f['imp_internos'])                        // 16 Impuestos internos
            . 'PES'                                              // 17 Moneda
            . '0001000000'                                       // 18 Tipo de cambio
            . (string) count($f['alicuotas'])                    // 19 Cantidad de alícuotas
            . (in_array($f['tipocomp'], LIVA_TIPOS_B_C, true) ? '0' : livaCodigoOperacion($f)) // 20 Código de operación
            . livaImp($f['credito_fiscal'])                      // 21 Crédito fiscal computable
            . livaImp(0)                                         // 22 Otros tributos
            . str_repeat('0', 11)                                // 23 CUIT emisor/corredor
            . str_repeat(' ', 30)                                // 24 Denominación emisor/corredor
            . livaImp(0);                                        // 25 IVA comisión
        $out .= $linea . "\r\n";
    }
    return $out;
}

function txtComprasAlicuotas(array $filas): string
{
    $out = '';
    foreach ($filas as $f) {
        foreach ($f['alicuotas'] as $a) {
            $out .= livaNum($f['tipocomp'], 3) . livaNum($f['pv'], 5) . livaNum($f['numero'], 20)
                . livaNum($f['doc_cod'], 2) . livaNum($f['doc_nro'], 20)
                . livaImp($a['neto']) . livaNum($a['cod'], 4) . livaImp($a['iva']) . "\r\n";
        }
    }
    return $out;
}

/** Totales por columna numérica. */
function libroIvaTotales(array $filas): array
{
    $tot = [];
    foreach ($filas as $f) {
        foreach ($f as $k => $v) {
            if (is_float($v) || is_int($v)) {
                if (in_array($k, ['id', 'tipocomp', 'pv', 'numero', 'doc_cod'], true)) continue;
                $tot[$k] = livaR2(($tot[$k] ?? 0) + $v);
            }
        }
    }
    return $tot;
}
