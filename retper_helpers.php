<?php
/**
 * retper_helpers.php - Cálculo y registro de retenciones (al pagar a proveedores) y
 * percepciones (al facturar), como agente. Configuración en regimenes_retper
 * (sql/2026-10-15_retenciones_percepciones.sql).
 *
 * Sin regímenes habilitados no se calcula nada: todo devuelve listas vacías.
 *
 * Alícuota aplicable a un sujeto, en este orden:
 *   1. Excepción cargada para ese cliente/proveedor (sujetos_retper): EXENTO o alícuota propia.
 *   2. Padrón (padron_retper) por CUIT, si el régimen usa padrón. Si el CUIT no está en el
 *      padrón: se aplica la alícuota general o no corresponde, según el régimen.
 *   3. Condición fiscal: si el régimen alcanza solo a ciertas condiciones y el sujeto no
 *      está entre ellas, no corresponde. Si el sujeto no es RI / Monotributo / Exento y el
 *      régimen tiene alícuota para no inscriptos, se usa esa.
 *   4. Alícuota general del régimen.
 *
 * Importe: base = neto / total / IVA según el régimen. Si la base no supera el mínimo no
 * sujeto, no corresponde. Con "resta mínimo" se aplica sobre el excedente (Ganancias).
 * Con acumulado mensual, base y mínimo se evalúan sobre todo el mes del sujeto y se
 * descuenta lo ya retenido/percibido en el mes. Si el resultado no llega al importe mínimo
 * a retener/percibir, se registra en 0 (para que siga sumando al acumulado).
 */

const RETPER_INSCRIPTOS = ['RESPONSABLE INSCRIPTO', 'MONOTRIBUTO', 'EXENTO'];

function retperNormCondicion(?string $c): string
{
    $c = mb_strtoupper(trim((string) $c), 'UTF-8');
    return $c === 'EXCENTO' ? 'EXENTO' : $c;
}

function retperRegimenes(PDO $pdo, string $tipo, string $fecha): array
{
    try {
        $st = $pdo->prepare("SELECT * FROM regimenes_retper WHERE habilitado = 1 AND tipo = ?
            AND (vigente_desde IS NULL OR vigente_desde <= ?) AND (vigente_hasta IS NULL OR vigente_hasta >= ?) ORDER BY id");
        $st->execute([$tipo, $fecha, $fecha]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return []; // base sin la migración
    }
}

/** Devuelve ['alicuota' => %, 'origen' => ...] o null si al sujeto no le corresponde. */
function retperAlicuota(PDO $pdo, array $reg, string $tipoSujeto, int $idSujeto, string $cuit, ?string $condicion, string $fecha): ?array
{
    $st = $pdo->prepare("SELECT modo, alicuota FROM sujetos_retper WHERE idregimen = ? AND tipo_sujeto = ? AND idsujeto = ? AND (vigente_hasta IS NULL OR vigente_hasta >= ?)");
    $st->execute([$reg['id'], $tipoSujeto, $idSujeto, $fecha]);
    if ($ex = $st->fetch(PDO::FETCH_ASSOC)) {
        return $ex['modo'] === 'EXENTO' ? null : ['alicuota' => (float) $ex['alicuota'], 'origen' => 'SUJETO'];
    }
    $cuit = preg_replace('/\D/', '', $cuit);
    if ((int) $reg['usa_padron']) {
        $st = $pdo->prepare("SELECT alicuota, excluido FROM padron_retper WHERE idregimen = ? AND cuit = ?
            AND (vigente_desde IS NULL OR vigente_desde <= ?) AND (vigente_hasta IS NULL OR vigente_hasta >= ?) ORDER BY id DESC LIMIT 1");
        $st->execute([$reg['id'], $cuit, $fecha, $fecha]);
        if ($p = $st->fetch(PDO::FETCH_ASSOC)) {
            return (int) $p['excluido'] ? null : ['alicuota' => (float) $p['alicuota'], 'origen' => 'PADRON'];
        }
        if ($reg['sin_padron'] === 'NO_APLICA') return null;
    }
    $cond = retperNormCondicion($condicion);
    $alcanzadas = array_filter(array_map('retperNormCondicion', explode(',', (string) $reg['condiciones'])));
    if ($alcanzadas && !in_array($cond, $alcanzadas, true)) return null;
    if (!in_array($cond, RETPER_INSCRIPTOS, true) && $reg['alicuota_no_inscripto'] !== null && $reg['alicuota_no_inscripto'] !== '') {
        return ['alicuota' => (float) $reg['alicuota_no_inscripto'], 'origen' => 'NO_INSCRIPTO'];
    }
    return ['alicuota' => (float) $reg['alicuota'], 'origen' => 'GENERAL'];
}

/** Base e importe ya practicados en el mes para el acumulado. */
function retperAcumuladoMes(PDO $pdo, array $reg, string $tabla, string $campoSujeto, int $idSujeto, string $fecha): array
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(base), 0), COALESCE(SUM(importe), 0) FROM $tabla
        WHERE idregimen = ? AND $campoSujeto = ? AND estado <> 1000 AND fecha BETWEEN ? AND ?");
    $st->execute([$reg['id'], $idSujeto, date('Y-m-01', strtotime($fecha)), date('Y-m-t', strtotime($fecha))]);
    [$b, $i] = $st->fetch(PDO::FETCH_NUM);
    return [(float) $b, (float) $i];
}

/** Importe a retener/percibir para una base (positiva). */
function retperImporte(array $reg, float $alicuota, float $base, float $basePrevMes = 0, float $importePrevMes = 0): float
{
    $minimo = (float) $reg['minimo_no_sujeto'];
    $acumula = (int) $reg['acumula_mensual'] === 1;
    $baseEval = $acumula ? $basePrevMes + $base : $base;
    if ($baseEval <= $minimo) return 0.0;
    $imponible = (int) $reg['resta_minimo'] ? $baseEval - $minimo : $baseEval;
    $importe = round($imponible * $alicuota / 100, 2);
    if ($acumula) $importe = round($importe - $importePrevMes, 2);
    if ($importe <= 0 || $importe < (float) $reg['minimo_importe']) return 0.0;
    return $importe;
}

function retperBase(array $reg, float $neto, float $iva, float $total): float
{
    return round(match ($reg['base']) { 'TOTAL' => $total, 'IVA' => $iva, default => $neto }, 2);
}

/**
 * Retenciones de un pago a proveedor.
 * $efectivo: lo que se paga con los medios (null = cancelar todas las facturas elegidas).
 * Lo cancelado es efectivo + retenciones; la proporción neto/IVA sale de las facturas
 * elegidas (si no hay, se asume IVA 21%). Devuelve lineas, total retenido y cancelado.
 */
function calcularRetencionesPago(PDO $pdo, int $proveedorId, array $facturaIds, ?float $efectivo, string $fecha): array
{
    $vacio = ['lineas' => [], 'total' => 0.0, 'cancelado' => $efectivo ?? 0.0];
    $regs = retperRegimenes($pdo, 'RETENCION', $fecha);
    if (!$regs) return $vacio;
    $st = $pdo->prepare("SELECT Id, Razon, cuit, condicion FROM Proveedores WHERE Id = ?");
    $st->execute([$proveedorId]);
    $prov = $st->fetch(PDO::FETCH_ASSOC);
    if (!$prov) return $vacio;

    $sumTotal = $sumNeto = $sumIva = $sumPend = 0.0;
    if ($facturaIds) {
        $in = implode(',', array_fill(0, count($facturaIds), '?'));
        $st = $pdo->prepare("SELECT ABS(total) t, ABS(neto) n, ABS(iva) + ABS(iva10) + ABS(iva27) i,
                total - (SELECT IFNULL(SUM(importepagado), 0) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id) AS pend
            FROM facturasdecompra WHERE proveedorid = ? AND id IN ($in) AND tipocomp NOT IN (3, 8, 13, 101)");
        $st->execute(array_merge([$proveedorId], array_map('intval', $facturaIds)));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $sumTotal += (float) $f['t']; $sumNeto += (float) $f['n']; $sumIva += (float) $f['i']; $sumPend += max(0, (float) $f['pend']);
        }
    }
    $propNeto = $sumTotal > 0 ? $sumNeto / $sumTotal : 1 / 1.21;
    $propIva = $sumTotal > 0 ? $sumIva / $sumTotal : 0.21 / 1.21;

    $aplicables = [];
    foreach ($regs as $reg) {
        $a = retperAlicuota($pdo, $reg, 'proveedor', $proveedorId, (string) $prov['cuit'], $prov['condicion'], $fecha);
        if ($a === null) continue;
        $acum = (int) $reg['acumula_mensual'] ? retperAcumuladoMes($pdo, $reg, 'retenciones_practicadas', 'idproveedor', $proveedorId, $fecha) : [0, 0];
        $aplicables[] = [$reg, $a, $acum];
    }
    if (!$aplicables) return $vacio;

    $calc = function (float $cancelado) use ($aplicables, $propNeto, $propIva) {
        $lineas = [];
        foreach ($aplicables as [$reg, $a, $acum]) {
            $base = retperBase($reg, $cancelado * $propNeto, $cancelado * $propIva, $cancelado);
            $lineas[] = ['idregimen' => (int) $reg['id'], 'nombre' => $reg['nombre'], 'impuesto' => $reg['impuesto'],
                         'base' => $base, 'alicuota' => $a['alicuota'], 'origen' => $a['origen'],
                         'importe' => retperImporte($reg, $a['alicuota'], $base, $acum[0], $acum[1]),
                         'cuenta_contable' => $reg['cuenta_contable']];
        }
        return $lineas;
    };

    if ($efectivo === null) {
        $cancelado = round($sumPend, 2);
        $lineas = $calc($cancelado);
    } else {
        // cancelado = efectivo + retenciones(cancelado): punto fijo (converge en pocas vueltas).
        $cancelado = $efectivo;
        for ($i = 0; $i < 8; $i++) {
            $lineas = $calc($cancelado);
            $nuevo = round($efectivo + array_sum(array_column($lineas, 'importe')), 2);
            if (abs($nuevo - $cancelado) < 0.005) break;
            $cancelado = $nuevo;
        }
        $lineas = $calc($cancelado);
        $cancelado = round($efectivo + array_sum(array_column($lineas, 'importe')), 2);
    }
    return ['lineas' => $lineas, 'total' => round(array_sum(array_column($lineas, 'importe')), 2), 'cancelado' => $cancelado];
}

/**
 * Percepciones de una venta fiscal. Importes en positivo; la nota de crédito las devuelve
 * (el que llama registra con signo negativo). No aplica a tickets internos (100/101).
 */
function calcularPercepcionesVenta(PDO $pdo, int $clienteId, int $tipoComp, float $neto, float $iva, float $total, string $fecha): array
{
    if (in_array($tipoComp, [100, 101], true)) return [];
    $regs = retperRegimenes($pdo, 'PERCEPCION', $fecha);
    if (!$regs) return [];
    $st = $pdo->prepare("SELECT id, numdoc, condicionfiscal FROM alb_clientes WHERE id = ?");
    $st->execute([$clienteId]);
    $cli = $st->fetch(PDO::FETCH_ASSOC);
    if (!$cli) return [];
    $esNota = in_array($tipoComp, [3, 8, 13], true);
    $lineas = [];
    foreach ($regs as $reg) {
        $a = retperAlicuota($pdo, $reg, 'cliente', $clienteId, (string) $cli['numdoc'], $cli['condicionfiscal'], $fecha);
        if ($a === null) continue;
        $base = retperBase($reg, abs($neto), abs($iva), abs($total));
        if ($esNota) {
            // Devolución: misma alícuota sobre la base de la nota, sin mínimos (se devuelve
            // lo que corresponde a lo que se anula).
            $importe = round($base * $a['alicuota'] / 100, 2);
        } else {
            $acum = (int) $reg['acumula_mensual'] ? retperAcumuladoMes($pdo, $reg, 'percepciones_practicadas', 'idcliente', $clienteId, $fecha) : [0, 0];
            $importe = retperImporte($reg, $a['alicuota'], $base, $acum[0], $acum[1]);
        }
        if ($importe <= 0) continue;
        $lineas[] = ['idregimen' => (int) $reg['id'], 'nombre' => $reg['nombre'], 'impuesto' => $reg['impuesto'],
                     'base' => $base, 'alicuota' => $a['alicuota'], 'origen' => $a['origen'], 'importe' => $importe,
                     'codigo_tributo_arca' => (int) $reg['codigo_tributo_arca'], 'cuenta_contable' => $reg['cuenta_contable']];
    }
    return $lineas;
}

/**
 * Neto e IVA de una venta tal como se declaran a ARCA (salida de ivaVentaComoArca). En una
 * factura C no se discrimina IVA: todo es neto.
 */
function percepcionBaseVenta(array $declarado, int $tipoComp): array
{
    $neto = $declarado['neto21'] + $declarado['neto10'] + $declarado['neto0'];
    $iva = $declarado['iva21'] + $declarado['iva10'];
    if (in_array($tipoComp, [11, 12, 13], true)) return [round($neto + $iva, 2), 0.0];
    return [round($neto, 2), round($iva, 2)];
}

/** Cuenta contable del régimen; falla con un mensaje claro si no está configurada. */
function retperCuenta(PDO $pdo, array $linea): string
{
    if (!cuentaContableExiste($pdo, $linea['cuenta_contable'])) {
        throw new Exception("El régimen \"{$linea['nombre']}\" no tiene una cuenta contable válida (Contabilidad > Impuestos > Regímenes de Retención y Percepción).");
    }
    return trim((string) $linea['cuenta_contable']);
}

function registrarRetenciones(PDO $pdo, array $lineas, int $idPago, int $proveedorId, string $fecha, string $usuario): void
{
    foreach ($lineas as $l) {
        $nro = 0;
        if ($l['importe'] > 0) {
            $st = $pdo->prepare("SELECT COALESCE(MAX(numero_certificado), 0) + 1 FROM retenciones_practicadas WHERE idregimen = ? FOR UPDATE");
            $st->execute([$l['idregimen']]);
            $nro = (int) $st->fetchColumn();
        }
        $pdo->prepare("INSERT INTO retenciones_practicadas (idregimen, fecha, idproveedor, idpago, base, alicuota, importe, numero_certificado, origen_alicuota, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$l['idregimen'], $fecha, $proveedorId, $idPago, $l['base'], $l['alicuota'], $l['importe'], $nro, $l['origen'], $usuario]);
    }
}

function registrarPercepciones(PDO $pdo, array $lineas, int $idTicket, int $clienteId, string $fecha, bool $esNota, string $usuario): void
{
    $s = $esNota ? -1 : 1;
    foreach ($lineas as $l) {
        $pdo->prepare("INSERT INTO percepciones_practicadas (idregimen, fecha, idcliente, idticket, base, alicuota, importe, origen_alicuota, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$l['idregimen'], $fecha, $clienteId, $idTicket, $s * $l['base'], $l['alicuota'], $s * $l['importe'], $l['origen'], $usuario]);
    }
}

/** Percepciones grabadas de un comprobante (para imprimirlo). */
function percepcionesDeTicket(PDO $pdo, int $idTicket): array
{
    try {
        $st = $pdo->prepare("SELECT r.nombre, p.alicuota, ABS(p.base) AS base, ABS(p.importe) AS importe FROM percepciones_practicadas p JOIN regimenes_retper r ON r.id = p.idregimen WHERE p.idticket = ? AND p.estado <> 1000");
        $st->execute([$idTicket]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
