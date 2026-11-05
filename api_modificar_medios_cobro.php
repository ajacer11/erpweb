<?php
/**
 * api_modificar_medios_cobro.php - Cambio de los medios de cobro de un comprobante ya emitido.
 *
 * El total del comprobante no cambia (tiene CAE): solo cambia cómo se reparte entre
 * medios de cobro. Lo que grabó la venta NO se toca; se registran contramovimientos
 * con fecha del día de la modificación, por la DIFERENCIA de cada medio:
 *
 *   - cccuentastesoreria  (un movimiento por medio que cambia)
 *   - cajaxusuario + cajas.saldo + controlcajas.saldofinal (efectivo, en la caja
 *     ABIERTA del usuario que modifica, aunque la venta se haya cobrado en otra caja)
 *   - ccbancos            (medios de tipo Bancos)
 *   - ccclientes          (medios de tipo Cuenta Corriente)
 *   - cupones_tarjeta     (medios de tipo Tarjetas: cupón por la diferencia, para la liquidación)
 *   - librodiario         (un asiento: cada medio contra su cuenta contable; balancea
 *                          solo porque el total cobrado no cambia)
 *
 * Y se actualizan en el comprobante mpago1/mpago2 (lo que muestra e imprime),
 * importectacte y pagada. Cada cambio queda en modificacionesmedioscobro.
 *
 * Signos: igual que api_facturar.php, una nota de crédito invierte todo (el dinero sale).
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'tarjetas_helpers.php';

const TIPOS_NC_MMC = [3, 8, 13, 101];
const TOLERANCIA_MMC = 0.009;

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

function etiquetaTipoMmc(int $tipo): string
{
    $map = [1 => 'FCA', 2 => 'NDA', 3 => 'NCA', 6 => 'FCB', 7 => 'NDB', 8 => 'NCB',
            11 => 'FCC', 12 => 'NDC', 13 => 'NCC', 100 => 'TKT', 101 => 'TDC'];
    return $map[$tipo] ?? 'Otro';
}

/** Caja abierta del usuario (mismo criterio que api_facturar.php). */
function cajaAbiertaMmc(PDO $pdo, string $usuario): array
{
    $st = $pdo->prepare("SELECT id, numcaja FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
    $st->execute([$usuario]);
    $caja = $st->fetch(PDO::FETCH_ASSOC);
    if (!$caja) return ['idcaja' => 0, 'numcaja' => 0, 'idcontrol' => 0];
    $st = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
    $st->execute([$caja['id'], $usuario]);
    return ['idcaja' => (int) $caja['id'], 'numcaja' => (int) $caja['numcaja'], 'idcontrol' => (int) ($st->fetchColumn() ?: 0)];
}

function medioEsDeCajaMmc(array $medio, array $caja): bool
{
    return $caja['idcaja'] > 0
        && ((int) $medio['puestoasociado'] === $caja['numcaja'] || (int) $medio['puestoasociado'] === $caja['idcaja']);
}

/** Medios que se pueden elegir: los mismos que ofrece la pantalla de facturación. */
function mediosDisponiblesMmc(PDO $pdo, array $caja): array
{
    $st = $pdo->prepare("SELECT * FROM mediosdepago
        WHERE habilitado = 1 AND visibleenventas = 1
          AND ((tipodecuenta = 'Caja de Usuarios' AND (puestoasociado = ? OR puestoasociado = ?))
               OR tipodecuenta <> 'Caja de Usuarios')
        ORDER BY medio ASC");
    $st->execute([$caja['numcaja'], $caja['idcaja']]);
    $medios = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $m) {
        // Sin caja abierta no se ofrece efectivo (la condición de arriba ya lo
        // excluye salvo un puestoasociado = 0 mal cargado).
        if ($m['tipodecuenta'] === 'Caja de Usuarios' && !medioEsDeCajaMmc($m, $caja)) continue;
        $medios[(int) $m['id']] = $m;
    }
    return $medios;
}

/** El comprobante solo guarda el NOMBRE del medio; se busca por nombre. */
function medioPorNombreMmc(PDO $pdo, string $nombre): array
{
    $st = $pdo->prepare("SELECT * FROM mediosdepago WHERE medio = ?");
    $st->execute([$nombre]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);
    if (count($filas) === 0) throw new Exception("No se encuentra el medio de pago \"$nombre\" que figura en el comprobante.");
    if (count($filas) > 1) throw new Exception("Hay más de un medio de pago llamado \"$nombre\"; no se puede determinar cuál se usó.");
    return $filas[0];
}

/**
 * Medios con los que se cobró el comprobante, tal como figuran en él.
 * El efectivo de otra caja se toma como efectivo de la caja abierta del usuario:
 * el ajuste de efectivo siempre impacta en la caja de quien modifica.
 */
function mediosActualesMmc(PDO $pdo, array $ticket, array $caja): array
{
    $actuales = [];
    foreach ([1, 2] as $n) {
        $nombre = trim((string) ($ticket["mpago$n"] ?? ''));
        $imp = round((float) ($ticket["mpago{$n}imp"] ?? 0), 2);
        if ($nombre === '' || abs($imp) < TOLERANCIA_MMC) continue;
        $medio = medioPorNombreMmc($pdo, $nombre);
        $efectivo = $medio['tipodecuenta'] === 'Caja de Usuarios';
        if ($efectivo && !medioEsDeCajaMmc($medio, $caja)) {
            if (!$caja['idcaja'] || !$caja['idcontrol']) {
                throw new Exception("El comprobante tiene un cobro en efectivo: debe tener una caja abierta para modificarlo.");
            }
            $st = $pdo->prepare("SELECT * FROM mediosdepago WHERE tipodecuenta = 'Caja de Usuarios' AND habilitado = 1 AND (puestoasociado = ? OR puestoasociado = ?)");
            $st->execute([$caja['numcaja'], $caja['idcaja']]);
            $deCaja = $st->fetchAll(PDO::FETCH_ASSOC);
            if (count($deCaja) !== 1) {
                throw new Exception("No se puede determinar el medio de efectivo de su caja abierta (hay " . count($deCaja) . ").");
            }
            $medio = $deCaja[0];
        }
        $actuales[] = ['medio' => $medio, 'nombre_original' => $nombre, 'imp' => $imp];
    }
    if (!$actuales) throw new Exception('El comprobante no tiene medios de cobro registrados.');
    return $actuales;
}

function sumarPorMedioMmc(array $lista): array
{
    $mapa = [];
    foreach ($lista as $l) {
        $id = (int) $l['medio']['id'];
        $mapa[$id] = round(($mapa[$id] ?? 0) + $l['imp'], 2);
    }
    return $mapa;
}

/** Lo ya imputado contra la parte en cuenta corriente del comprobante. */
function imputadoMmc(PDO $pdo, array $ticket): float
{
    if (in_array((int) $ticket['tipocomp'], TIPOS_NC_MMC, true)) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(importepagado), 0) FROM cobrosfacturasclientes WHERE idpago = ? AND tablapago = 'tickets'");
    } else {
        $st = $pdo->prepare("SELECT COALESCE(SUM(importepagado), 0) FROM cobrosfacturasclientes WHERE idfactura = ?");
    }
    $st->execute([$ticket['id']]);
    return round((float) $st->fetchColumn(), 2);
}

function cargarTicketMmc(PDO $pdo, int $id, bool $bloquear): array
{
    $st = $pdo->prepare("SELECT t.*, c.razon, c.cuentacorriente FROM tickets t LEFT JOIN alb_clientes c ON c.id = t.cliente WHERE t.id = ?" . ($bloquear ? ' FOR UPDATE' : ''));
    $st->execute([$id]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) throw new Exception('Comprobante no encontrado.');
    if (esVendedorRestringido()) {
        $stV = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = ?");
        $stV->execute([$t['cliente']]);
        if ((int) $stV->fetchColumn() !== idVendedorRestringido()) throw new Exception('No tiene acceso a este comprobante.');
    }
    return $t;
}

function numeroCompMmc(array $t): string
{
    return etiquetaTipoMmc((int) $t['tipocomp']) . ' ' . str_pad($t['PtoVta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($t['numcomp'], 8, '0', STR_PAD_LEFT);
}

try {
    if (!puedeModificarMediosCobro($pdo)) {
        throw new Exception('No tiene permiso para modificar medios de cobro.');
    }

    switch ($action) {
        case 'get':
            $ticket = cargarTicketMmc($pdo, (int) ($_GET['id'] ?? 0), false);
            $caja = cajaAbiertaMmc($pdo, $usuario);
            $actuales = mediosActualesMmc($pdo, $ticket, $caja);
            $esNota = in_array((int) $ticket['tipocomp'], TIPOS_NC_MMC, true);
            $ccActual = 0;
            foreach ($actuales as $a) if ($a['medio']['tipodecuenta'] === 'Cuenta Corriente') $ccActual += $a['imp'];

            echo json_encode([
                'success' => true,
                'ticket' => [
                    'id' => (int) $ticket['id'],
                    'numero' => numeroCompMmc($ticket),
                    'fecha' => $ticket['fecha'],
                    'cliente' => $ticket['razon'] ?? '',
                    'cliente_cc' => (int) ($ticket['cuentacorriente'] ?? 0),
                    'total' => round((float) $ticket['total'], 2),
                    'es_nota' => $esNota,
                ],
                'actuales' => array_map(static fn($a) => [
                    'medio_id' => (int) $a['medio']['id'],
                    'nombre' => $a['nombre_original'],
                    'imp' => $a['imp'],
                ], $actuales),
                'a_repartir' => round(array_sum(array_column($actuales, 'imp')), 2),
                'cc_actual' => round($ccActual, 2),
                'imputado' => imputadoMmc($pdo, $ticket),
                'caja_abierta' => $caja['idcaja'] > 0 && $caja['idcontrol'] > 0,
                'medios' => array_values(array_map(static fn($m) => [
                    'id' => (int) $m['id'], 'medio' => $m['medio'], 'tipo' => $m['tipodecuenta'],
                ], mediosDisponiblesMmc($pdo, $caja))),
                'tarjetas' => tarjetasHabilitadas($pdo),
            ]);
            break;

        case 'save':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
            $data = json_decode(file_get_contents('php://input'), true) ?: [];
            $motivo = trim((string) ($data['motivo'] ?? ''));
            if (mb_strlen($motivo) < 5) throw new Exception('Indique el motivo de la modificación.');
            $motivo = mb_substr($motivo, 0, 255);

            $fechaHoy = date('Y-m-d');
            $ejPer = resolverEjercicioPeriodo($pdo, $fechaHoy);

            $pdo->beginTransaction();

            $ticket = cargarTicketMmc($pdo, (int) ($data['ticket_id'] ?? 0), true);
            $tipo = (int) $ticket['tipocomp'];
            $esNota = in_array($tipo, TIPOS_NC_MMC, true);
            $signo = $esNota ? -1 : 1; // +1: el dinero entra
            $fiscal = in_array($tipo, [100, 101], true) ? 0 : 1;
            $caja = cajaAbiertaMmc($pdo, $usuario);
            $disponibles = mediosDisponiblesMmc($pdo, $caja);

            // --- Medios actuales y nuevos ---
            $actuales = mediosActualesMmc($pdo, $ticket, $caja);
            $aRepartir = round(array_sum(array_column($actuales, 'imp')), 2);

            $nuevos = [];
            foreach ([1, 2] as $n) {
                $id = (int) ($data["mp$n"] ?? 0);
                $imp = round((float) ($data["imp$n"] ?? 0), 2);
                if (!$id && abs($imp) < TOLERANCIA_MMC) continue;
                if (!$id) throw new Exception("Elija el medio de cobro $n.");
                if (!isset($disponibles[$id])) throw new Exception("El medio de cobro $n no está disponible (deshabilitado, no visible en ventas o efectivo de otra caja).");
                if (!is_finite($imp) || $imp <= 0) throw new Exception("El importe del medio de cobro $n debe ser mayor a cero.");
                $nuevos[] = ['medio' => $disponibles[$id], 'imp' => $imp];
            }
            if (!$nuevos) throw new Exception('Debe indicar al menos un medio de cobro.');
            if (count($nuevos) === 2 && (int) $nuevos[0]['medio']['id'] === (int) $nuevos[1]['medio']['id']) {
                throw new Exception('Los dos medios de cobro deben ser distintos.');
            }
            $sumaNueva = round(array_sum(array_column($nuevos, 'imp')), 2);
            if (abs($sumaNueva - $aRepartir) > TOLERANCIA_MMC) {
                throw new Exception('Los nuevos importes suman $' . number_format($sumaNueva, 2, ',', '.') . ' y deben sumar $' . number_format($aRepartir, 2, ',', '.') . ', igual que el cobro original.');
            }

            // --- Diferencia por medio ---
            $mapaAnt = sumarPorMedioMmc($actuales);
            $mapaNue = sumarPorMedioMmc($nuevos);
            $medios = [];
            foreach ($actuales as $a) $medios[(int) $a['medio']['id']] = $a['medio'];
            foreach ($nuevos as $nv) $medios[(int) $nv['medio']['id']] = $nv['medio'];
            $deltas = [];
            foreach ($medios as $id => $m) {
                $d = round(($mapaNue[$id] ?? 0) - ($mapaAnt[$id] ?? 0), 2);
                if (abs($d) > TOLERANCIA_MMC) $deltas[$id] = $d;
            }
            if (!$deltas) throw new Exception('No hay cambios: los medios e importes son los mismos.');

            // --- Cuenta corriente: no puede quedar por debajo de lo ya imputado ---
            $ccNueva = 0;
            foreach ($nuevos as $nv) if ($nv['medio']['tipodecuenta'] === 'Cuenta Corriente') $ccNueva += $nv['imp'];
            $ccNueva = round($ccNueva, 2);
            $imputado = imputadoMmc($pdo, $ticket);
            if ($ccNueva + TOLERANCIA_MMC < $imputado) {
                throw new Exception('El comprobante tiene $' . number_format($imputado, 2, ',', '.') . ' imputados ' . ($esNota ? 'a facturas' : 'por cobros')
                    . ' y la cuenta corriente quedaría en $' . number_format($ccNueva, 2, ',', '.') . '. Primero ajuste las imputaciones desde Gestión de imputaciones.');
            }
            $ccAnterior = 0;
            foreach ($actuales as $a) if ($a['medio']['tipodecuenta'] === 'Cuenta Corriente') $ccAnterior += $a['imp'];
            if ($ccNueva > round($ccAnterior, 2) + TOLERANCIA_MMC && (int) ($ticket['cuentacorriente'] ?? 0) !== 1) {
                throw new Exception('El cliente no tiene habilitada la Cuenta Corriente.');
            }

            // --- Efectivo: exige caja abierta de verdad (no cerrada a mitad de camino) ---
            $hayEfectivo = false;
            foreach ($deltas as $id => $d) if ($medios[$id]['tipodecuenta'] === 'Caja de Usuarios') $hayEfectivo = true;
            if ($hayEfectivo) {
                if (!$caja['idcaja'] || !$caja['idcontrol']) throw new Exception('Hay efectivo en juego: debe tener una caja abierta.');
                $stOpen = $pdo->prepare("SELECT COUNT(*) FROM controlcajas WHERE id = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') FOR UPDATE");
                $stOpen->execute([$caja['idcontrol']]);
                if (!(int) $stOpen->fetchColumn()) throw new Exception('Su caja se cerró. Ábrala nuevamente para modificar un cobro en efectivo.');
            }

            // --- Cuentas contables (se validan todas antes de grabar nada) ---
            $numComp = numeroCompMmc($ticket);
            $cuentas = [];
            foreach ($deltas as $id => $d) {
                $cuentas[$id] = resolverCuentaMedioPago($pdo, $id, "el medio de cobro \"{$medios[$id]['medio']}\" de $numComp");
            }

            $detalle = "Modif. medios cobro $numComp (" . ($ticket['razon'] ?? '') . ")";
            $detalleCorto = mb_substr($detalle, 0, 250);
            $stTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria");
            $numAsientoTeso = ((int) $stTesoNum->fetchColumn()) + 1;
            $numAsiento = siguienteNumeroAsiento($pdo, $ejPer['ejercicio']);

            foreach ($deltas as $id => $delta) {
                $m = $medios[$id];
                $efecto = round($delta * $signo, 2); // >0 entra dinero / aumenta la deuda del cliente
                $debe = $efecto > 0 ? $efecto : 0;
                $haber = $efecto < 0 ? -$efecto : 0;

                // 1. Tesorería
                $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, ?, 'tickets', ?, ?, ?, ?, ?, ?)")
                    ->execute([$id, $debe, $haber, $usuario, $fechaHoy, $detalleCorto, $fiscal, $ticket['id'], $numAsientoTeso]);

                // 2. Caja del usuario
                if ($m['tipodecuenta'] === 'Caja de Usuarios') {
                    $pdo->prepare("INSERT INTO cajaxusuario (FechaMov, HoraMov, TipoMov, Debe, Haber, usuario, idcaja, idcontrolcaja) VALUES (?, DATE_FORMAT(NOW(), '%H:%i'), ?, ?, ?, ?, ?, ?)")
                        ->execute([$fechaHoy, $detalleCorto, $debe, $haber, $usuario, $caja['idcaja'], $caja['idcontrol']]);
                    $pdo->prepare("UPDATE cajas SET saldo = saldo + ? WHERE id = ?")->execute([$efecto, $caja['idcaja']]);
                    $pdo->prepare("UPDATE controlcajas SET cantidadmovimientos = cantidadmovimientos + 1, saldofinal = saldofinal + ? WHERE id = ?")
                        ->execute([$efecto, $caja['idcontrol']]);
                }

                // 3. Bancos
                if ($m['tipodecuenta'] === 'Bancos' && $m['cuentabancaria']) {
                    $stSaldo = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                    $stSaldo->execute([$m['cuentabancaria']]);
                    $saldoB = (float) ($stSaldo->fetchColumn() ?: 0);
                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, ?, ?, ?, ?, 'tickets', ?)")
                        ->execute([$m['cuentabancaria'], $debe, $haber, round($saldoB + $efecto, 2), $fechaHoy, $ticket['id'], $detalleCorto]);
                }

                // 4. Cuenta corriente del cliente
                if ($m['tipodecuenta'] === 'Cuenta Corriente') {
                    $stS = $pdo->prepare("SELECT saldo FROM ccclientes WHERE cliente = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                    $stS->execute([$ticket['cliente']]);
                    $saldoC = (float) ($stS->fetchColumn() ?: 0);
                    $pdo->prepare("INSERT INTO ccclientes (cliente, debe, haber, saldo, compasoc, cliorigen, tabla, fecha, fiscal, mediopago) VALUES (?, ?, ?, ?, ?, ?, 'tickets', ?, ?, ?)")
                        ->execute([$ticket['cliente'], $debe, $haber, round($saldoC + $efecto, 2), $ticket['id'], $ticket['cliente'], $fechaHoy, $fiscal, $id]);
                }

                // 5. Cupón de tarjeta por la diferencia. Si se agrega (o aumenta) una tarjeta
                //    se usa la elegida en pantalla; si se quita, la del cupón original.
                if ($m['tipodecuenta'] === 'Tarjetas') {
                    $nNuevo = 0;
                    foreach ($nuevos as $i => $nv) if ((int) $nv['medio']['id'] === $id) $nNuevo = $i + 1;
                    $dato = $nNuevo ? ($data["tarjeta$nNuevo"] ?? null) : null;
                    $tj = (is_array($dato) && (int) ($dato['id'] ?? 0) > 0)
                        ? datosTarjetaCobro($pdo, $m, $dato, "el medio de cobro $nNuevo")
                        : tarjetaDeComprobante($pdo, 'tickets', (int) $ticket['id'], $id);
                    if ($delta > 0 && !$tj['idtarjeta'] && tarjetasHabilitadas($pdo)) {
                        throw new Exception("Elija la tarjeta del medio de cobro $nNuevo.");
                    }
                    registrarCupon($pdo, $tj, $efecto, $id, 'tickets', (int) $ticket['id'], (int) $ticket['cliente'], $fiscal, $fechaHoy, $usuario);
                }

                // 6. Contabilidad: cada medio contra su cuenta. Balancea porque la suma
                //    de las diferencias es cero (el total cobrado no cambia).
                grabarLineaAsiento($pdo, $numAsiento, $fechaHoy, $cuentas[$id], $debe, $haber, $ticket['id'], 'tickets', $fiscal, $usuario, $detalle, $ejPer['ejercicio'], $ejPer['periodo']);
            }

            // --- Comprobante ---
            $nombres = array_map(static fn($nv) => $nv['medio']['medio'], $nuevos);
            $pdo->prepare("UPDATE tickets SET mpago1 = ?, mpago1imp = ?, mpago2 = ?, mpago2imp = ?, importectacte = ?, pagada = ? WHERE id = ?")
                ->execute([
                    $nombres[0], $nuevos[0]['imp'],
                    $nombres[1] ?? 'Sin Informar', $nuevos[1]['imp'] ?? 0,
                    $ccNueva,
                    ($ccNueva - $imputado > 0.01) ? 0 : 1,
                    $ticket['id'],
                ]);

            // --- Auditoría ---
            $pdo->prepare("INSERT INTO modificacionesmedioscobro
                (idticket, fecha, usuario, motivo, mpago1_ant, mpago1imp_ant, mpago2_ant, mpago2imp_ant,
                 mpago1_nuevo, mpago1imp_nuevo, mpago2_nuevo, mpago2imp_nuevo, numasiento, idcaja, idcontrolcaja)
                VALUES (?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    $ticket['id'], $usuario, $motivo,
                    $ticket['mpago1'], (float) $ticket['mpago1imp'], $ticket['mpago2'], (float) $ticket['mpago2imp'],
                    $nombres[0], $nuevos[0]['imp'], $nombres[1] ?? null, $nuevos[1]['imp'] ?? 0,
                    $numAsiento, $hayEfectivo ? $caja['idcaja'] : 0, $hayEfectivo ? $caja['idcontrol'] : 0,
                ]);

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Medios de cobro de $numComp actualizados (asiento #$numAsiento)."]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
