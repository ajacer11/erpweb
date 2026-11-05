<?php
/**
 * api_tarjetas.php - Catálogo de tarjetas, cupones y liquidaciones de tarjeta.
 * (sql/2026-10-15_tarjetas_cupones.sql, tarjetas_helpers.php)
 *
 * Liquidación: toma cupones pendientes de UN medio de pago de tipo Tarjetas (y de una
 * tarjeta), más un "bruto anterior" opcional para cupones cobrados antes de que existiera
 * este módulo. Deja:
 *   - tesorería: medio tarjeta HABER bruto, medio banco DEBE neto (tabla 'liqtarjeta')
 *   - ccbancos:  DEBE neto en la cuenta bancaria del medio banco
 *   - asiento:   Banco (neto) + aranceles/otros (comisionestarjetas) + IVA aranceles
 *                (ivacompras) + retenciones sufridas (retiibb/retiva/retgan)
 *                contra la cuenta del medio tarjeta (bruto)
 * La anulación no borra nada: registra los movimientos inversos con fecha del día y
 * devuelve los cupones a pendientes.
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'tarjetas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

function jsonEntradaTj(): array
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
    return json_decode(file_get_contents('php://input'), true) ?: [];
}

function fechaValidaTj($f, string $campo): string
{
    $f = (string) $f;
    $dt = DateTime::createFromFormat('!Y-m-d', $f);
    if (!$dt || $dt->format('Y-m-d') !== $f) throw new Exception("Fecha inválida en $campo.");
    return $f;
}

function importeTj(array $d, string $k): float
{
    $v = round((float) ($d[$k] ?? 0), 2);
    if (!is_finite($v) || $v < 0) throw new Exception("El importe de $k no puede ser negativo.");
    return $v;
}

function cuentaPredeterminadaTj(PDO $pdo, string $campo, string $contexto): string
{
    try {
        $cta = $pdo->query("SELECT `$campo` FROM cuentaspredeterminadas WHERE id = 1")->fetchColumn();
    } catch (PDOException $e) {
        $cta = null;
    }
    if (!cuentaContableExiste($pdo, $cta)) {
        throw new Exception("Falta configurar la cuenta contable de $contexto en Contabilidad > Configurar Asientos Automáticos.");
    }
    return trim((string) $cta);
}

try {
    switch ($action) {
        // ------------------------------------------------------------- catálogo
        case 'tarjetas_list':
            $filas = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM cupones_tarjeta c WHERE c.idtarjeta = t.id AND c.estado = 0) AS pendientes
                                  FROM tarjetas t ORDER BY t.habilitada DESC, t.descripcion")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $filas]);
            break;

        case 'tarjeta_save':
            $d = jsonEntradaTj();
            $desc = mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 30);
            if ($desc === '') throw new Exception('Indique el nombre de la tarjeta.');
            $tipo = (int) ($d['tipo'] ?? 1) === 2 ? 2 : 1;
            $dias = max(0, min(120, (int) ($d['diasalcobro'] ?? 2)));
            $hab = !empty($d['habilitada']) ? 1 : 0;
            $id = (int) ($d['id'] ?? 0);
            $st = $pdo->prepare("SELECT id FROM tarjetas WHERE descripcion = ? AND id <> ?");
            $st->execute([$desc, $id]);
            if ($st->fetchColumn()) throw new Exception('Ya existe una tarjeta con ese nombre.');
            if ($id) {
                $pdo->prepare("UPDATE tarjetas SET descripcion = ?, tipo = ?, diasalcobro = ?, habilitada = ? WHERE id = ?")->execute([$desc, $tipo, $dias, $hab, $id]);
            } else {
                $pdo->prepare("INSERT INTO tarjetas (descripcion, tipo, diasalcobro, habilitada) VALUES (?, ?, ?, ?)")->execute([$desc, $tipo, $dias, $hab]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'medios':
            // Medios para la liquidación: de tarjeta (origen) y de banco (destino del neto).
            $filas = $pdo->query("SELECT id, medio, tipodecuenta FROM mediosdepago
                                  WHERE habilitado = 1 AND (tipodecuenta = 'Tarjetas' OR (tipodecuenta = 'Bancos' AND cuentabancaria > 0))
                                  ORDER BY tipodecuenta, medio")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $filas, 'tarjetas' => tarjetasHabilitadas($pdo)]);
            break;

        // ------------------------------------------------------------- cupones
        case 'cupones_list':
            $d = jsonEntradaTj();
            $where = ['c.fecha BETWEEN ? AND ?'];
            $params = [fechaValidaTj($d['desde'] ?? '', 'desde'), fechaValidaTj($d['hasta'] ?? '', 'hasta')];
            if (isset($d['estado']) && $d['estado'] !== '') { $where[] = 'c.estado = ?'; $params[] = (int) $d['estado']; }
            if (!empty($d['idtarjeta'])) { $where[] = 'c.idtarjeta = ?'; $params[] = (int) $d['idtarjeta']; }
            if (!empty($d['idmediopago'])) { $where[] = 'c.idmediopago = ?'; $params[] = (int) $d['idmediopago']; }
            $st = $pdo->prepare("SELECT c.*, COALESCE(t.descripcion, 'SIN TARJETA') AS tarjeta, cl.razon AS cliente_nombre, m.medio,
                    CASE c.tabla
                        WHEN 'tickets' THEN (SELECT CONCAT(tk.letracomp, ' ', LPAD(tk.PtoVta, 4, '0'), '-', LPAD(tk.numcomp, 8, '0')) FROM tickets tk WHERE tk.id = c.compasoc)
                        WHEN 'pagos' THEN CONCAT('Recibo #', c.compasoc)
                        ELSE CONCAT('Ajuste cupón #', c.compasoc) END AS origen
                FROM cupones_tarjeta c
                LEFT JOIN tarjetas t ON t.id = c.idtarjeta
                LEFT JOIN alb_clientes cl ON cl.id = c.cliente
                LEFT JOIN mediosdepago m ON m.id = c.idmediopago
                WHERE " . implode(' AND ', $where) . " ORDER BY c.fecha, c.id");
            $st->execute($params);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'cupones_pendientes':
            // Para armar una liquidación: pendientes de un medio (y opcionalmente una tarjeta) hasta una fecha.
            $d = jsonEntradaTj();
            $params = [(int) ($d['idmediotarjeta'] ?? 0), fechaValidaTj($d['hasta'] ?? '', 'hasta')];
            $extra = '';
            if (isset($d['idtarjeta']) && $d['idtarjeta'] !== '' && $d['idtarjeta'] !== null) { $extra = ' AND c.idtarjeta = ?'; $params[] = (int) $d['idtarjeta']; }
            $st = $pdo->prepare("SELECT c.id, c.fecha, c.importe, c.cuotas, c.lote, c.numcupon, c.idtarjeta, COALESCE(t.descripcion, 'SIN TARJETA') AS tarjeta, cl.razon AS cliente_nombre
                FROM cupones_tarjeta c LEFT JOIN tarjetas t ON t.id = c.idtarjeta LEFT JOIN alb_clientes cl ON cl.id = c.cliente
                WHERE c.estado = 0 AND c.idmediopago = ? AND c.fecha <= ? $extra ORDER BY c.fecha, c.id");
            $st->execute($params);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ------------------------------------------------------------- liquidaciones
        case 'liquidaciones_list':
            $d = jsonEntradaTj();
            $st = $pdo->prepare("SELECT l.*, COALESCE(t.descripcion, 'VARIAS / SIN TARJETA') AS tarjeta, mb.medio AS banco, mt.medio AS medio_tarjeta,
                    (SELECT COUNT(*) FROM cupones_tarjeta c WHERE c.idliquidacion = l.id) AS cantidad_cupones
                FROM liquidaciones_tarjeta l
                LEFT JOIN tarjetas t ON t.id = l.idtarjeta
                LEFT JOIN mediosdepago mb ON mb.id = l.idmediobanco
                LEFT JOIN mediosdepago mt ON mt.id = l.idmediotarjeta
                WHERE l.fecha BETWEEN ? AND ? ORDER BY l.fecha DESC, l.id DESC");
            $st->execute([fechaValidaTj($d['desde'] ?? '', 'desde'), fechaValidaTj($d['hasta'] ?? '', 'hasta')]);
            echo json_encode(['success' => true, 'data' => $st->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'liquidacion_save':
            $d = jsonEntradaTj();
            $fecha = fechaValidaTj($d['fecha'] ?? '', 'fecha de acreditación');
            if ($fecha > date('Y-m-d')) throw new Exception('La fecha de acreditación no puede ser futura.');
            $ejPer = resolverEjercicioPeriodo($pdo, $fecha);
            $idMedioTarjeta = (int) ($d['idmediotarjeta'] ?? 0);
            $idMedioBanco = (int) ($d['idmediobanco'] ?? 0);
            $idTarjeta = (int) ($d['idtarjeta'] ?? 0);
            $ded = [];
            foreach (['bruto_anterior', 'aranceles', 'iva_aranceles', 'ret_iibb', 'ret_iva', 'ret_ganancias', 'otros_descuentos'] as $k) $ded[$k] = importeTj($d, $k);
            $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($d['cupones'] ?? [])))));
            $numero = mb_substr(trim((string) ($d['numero'] ?? '')), 0, 30);
            $obs = mb_substr(trim((string) ($d['observaciones'] ?? '')), 0, 255);

            $pdo->beginTransaction();

            $st = $pdo->prepare("SELECT * FROM mediosdepago WHERE id = ?");
            $st->execute([$idMedioTarjeta]);
            $medioTarjeta = $st->fetch(PDO::FETCH_ASSOC);
            if (!$medioTarjeta || $medioTarjeta['tipodecuenta'] !== 'Tarjetas') throw new Exception('Elija el medio de pago de tarjeta que se liquida.');
            $st->execute([$idMedioBanco]);
            $medioBanco = $st->fetch(PDO::FETCH_ASSOC);
            if (!$medioBanco || $medioBanco['tipodecuenta'] !== 'Bancos' || !(int) $medioBanco['cuentabancaria']) throw new Exception('Elija la cuenta bancaria donde se acredita el neto.');

            $brutoCupones = 0.0;
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare("SELECT id, importe, estado, idmediopago, idtarjeta, fecha FROM cupones_tarjeta WHERE id IN ($in) FOR UPDATE");
                $st->execute($ids);
                $cupones = $st->fetchAll(PDO::FETCH_ASSOC);
                if (count($cupones) !== count($ids)) throw new Exception('Algún cupón seleccionado ya no existe.');
                foreach ($cupones as $c) {
                    if ((int) $c['estado'] !== CUPON_PENDIENTE) throw new Exception("El cupón #{$c['id']} ya no está pendiente (otra persona pudo haberlo liquidado). Actualice la lista.");
                    if ((int) $c['idmediopago'] !== $idMedioTarjeta) throw new Exception("El cupón #{$c['id']} es de otro medio de pago.");
                    if ($idTarjeta && (int) $c['idtarjeta'] !== $idTarjeta) throw new Exception("El cupón #{$c['id']} es de otra tarjeta.");
                    if ($c['fecha'] > $fecha) throw new Exception("El cupón #{$c['id']} es posterior a la fecha de acreditación.");
                    $brutoCupones += (float) $c['importe'];
                }
            }
            $brutoCupones = round($brutoCupones, 2);
            $bruto = round($brutoCupones + $ded['bruto_anterior'], 2);
            if ($bruto <= 0) throw new Exception('El bruto a liquidar debe ser mayor a cero.');
            $deducciones = round($ded['aranceles'] + $ded['iva_aranceles'] + $ded['ret_iibb'] + $ded['ret_iva'] + $ded['ret_ganancias'] + $ded['otros_descuentos'], 2);
            $neto = round($bruto - $deducciones, 2);
            if ($neto < 0) throw new Exception('Las deducciones superan el bruto liquidado.');

            // Cuentas contables: todas validadas antes de grabar.
            $ctaBanco = resolverCuentaMedioPago($pdo, $idMedioBanco, 'la cuenta bancaria de la liquidación');
            $ctaTarjeta = resolverCuentaMedioPago($pdo, $idMedioTarjeta, 'el medio de tarjeta liquidado');
            $lineas = [[$ctaBanco, $neto, 0]];
            if ($ded['aranceles'] + $ded['otros_descuentos'] > 0) $lineas[] = [cuentaPredeterminadaTj($pdo, 'comisionestarjetas', 'aranceles y comisiones de tarjetas'), round($ded['aranceles'] + $ded['otros_descuentos'], 2), 0];
            if ($ded['iva_aranceles'] > 0) $lineas[] = [cuentaPredeterminadaTj($pdo, 'ivacompras', 'IVA crédito fiscal'), $ded['iva_aranceles'], 0];
            if ($ded['ret_iibb'] > 0) $lineas[] = [cuentaPredeterminadaTj($pdo, 'retiibb', 'retenciones de IIBB sufridas'), $ded['ret_iibb'], 0];
            if ($ded['ret_iva'] > 0) $lineas[] = [cuentaPredeterminadaTj($pdo, 'retiva', 'retenciones de IVA sufridas'), $ded['ret_iva'], 0];
            if ($ded['ret_ganancias'] > 0) $lineas[] = [cuentaPredeterminadaTj($pdo, 'retgan', 'retenciones de Ganancias sufridas'), $ded['ret_ganancias'], 0];
            $lineas[] = [$ctaTarjeta, 0, $bruto];

            $pdo->prepare("INSERT INTO liquidaciones_tarjeta (idtarjeta, fecha, numero, bruto_cupones, bruto_anterior, aranceles, iva_aranceles, ret_iibb, ret_iva, ret_ganancias, otros_descuentos, neto, idmediobanco, idmediotarjeta, observaciones, usuario)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$idTarjeta, $fecha, $numero, $brutoCupones, $ded['bruto_anterior'], $ded['aranceles'], $ded['iva_aranceles'], $ded['ret_iibb'],
                           $ded['ret_iva'], $ded['ret_ganancias'], $ded['otros_descuentos'], $neto, $idMedioBanco, $idMedioTarjeta, $obs, $usuario]);
            $idLiq = (int) $pdo->lastInsertId();
            if ($ids) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $pdo->prepare("UPDATE cupones_tarjeta SET estado = 1, idliquidacion = ? WHERE id IN ($in)")->execute(array_merge([$idLiq], $ids));
            }

            $detalle = "Liquidación tarjeta #$idLiq" . ($numero !== '' ? " ($numero)" : '') . " - {$medioTarjeta['medio']}";
            $numTeso = ((int) $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria")->fetchColumn()) + 1;
            $stTeso = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, ?, 'liqtarjeta', ?, ?, ?, 1, ?, ?)");
            $stTeso->execute([$idMedioTarjeta, 0, $bruto, $usuario, $fecha, $detalle, $idLiq, $numTeso]);
            if ($neto > 0) $stTeso->execute([$idMedioBanco, $neto, 0, $usuario, $fecha, $detalle, $idLiq, $numTeso]);

            if ($neto > 0) {
                $stS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $stS->execute([$medioBanco['cuentabancaria']]);
                $saldo = (float) ($stS->fetchColumn() ?: 0);
                $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, 0, ?, ?, ?, 'liqtarjeta', ?)")
                    ->execute([$medioBanco['cuentabancaria'], $neto, round($saldo + $neto, 2), $fecha, $idLiq, $detalle]);
            }

            $numAsiento = siguienteNumeroAsiento($pdo, $ejPer['ejercicio']);
            foreach ($lineas as [$cta, $debe, $haber]) {
                grabarLineaAsiento($pdo, $numAsiento, $fecha, $cta, $debe, $haber, $idLiq, 'liqtarjeta', 1, $usuario, $detalle, $ejPer['ejercicio'], $ejPer['periodo']);
            }
            $pdo->prepare("UPDATE liquidaciones_tarjeta SET numasiento = ? WHERE id = ?")->execute([$numAsiento, $idLiq]);

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idLiq, 'neto' => $neto, 'message' => "Liquidación #$idLiq registrada (asiento #$numAsiento)."]);
            break;

        case 'liquidacion_anular':
            $d = jsonEntradaTj();
            $id = (int) ($d['id'] ?? 0);
            $fecha = date('Y-m-d');
            $ejPer = resolverEjercicioPeriodo($pdo, $fecha);
            $pdo->beginTransaction();
            $st = $pdo->prepare("SELECT * FROM liquidaciones_tarjeta WHERE id = ? FOR UPDATE");
            $st->execute([$id]);
            $liq = $st->fetch(PDO::FETCH_ASSOC);
            if (!$liq) throw new Exception('Liquidación no encontrada.');
            if ((int) $liq['estado'] === 1000) throw new Exception('La liquidación ya está anulada.');
            $st = $pdo->prepare("SELECT COUNT(*) FROM conciliacion_items i JOIN ccbancos b ON b.id = i.idccbancos WHERE b.tabla = 'liqtarjeta' AND b.compasoc = ?");
            try { $st->execute([$id]); $conciliada = (int) $st->fetchColumn(); } catch (PDOException $e) { $conciliada = 0; }
            if ($conciliada) throw new Exception('La acreditación de esta liquidación ya está conciliada con el extracto bancario. Desconcíliela primero.');

            $detalle = "Anulación liquidación tarjeta #$id";
            $pdo->prepare("UPDATE liquidaciones_tarjeta SET estado = 1000, anulada_por = ?, anulada_el = NOW() WHERE id = ?")->execute([$usuario, $id]);
            $pdo->prepare("UPDATE cupones_tarjeta SET estado = 0, idliquidacion = 0 WHERE idliquidacion = ? AND estado = 1")->execute([$id]);

            $st = $pdo->prepare("SELECT idcuenta, debe, haber FROM cccuentastesoreria WHERE tabla = 'liqtarjeta' AND compasoc = ?");
            $st->execute([$id]);
            $numTeso = ((int) $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria")->fetchColumn()) + 1;
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $t) {
                $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, ?, 'liqtarjeta', ?, ?, ?, 1, ?, ?)")
                    ->execute([$t['idcuenta'], $t['haber'], $t['debe'], $usuario, $fecha, $detalle, $id, $numTeso]);
            }
            $st = $pdo->prepare("SELECT cuenta, debe, haber FROM ccbancos WHERE tabla = 'liqtarjeta' AND compasoc = ?");
            $st->execute([$id]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $b) {
                $stS = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
                $stS->execute([$b['cuenta']]);
                $saldo = (float) ($stS->fetchColumn() ?: 0) + (float) $b['haber'] - (float) $b['debe'];
                $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, ?, ?, ?, ?, 'liqtarjeta', ?)")
                    ->execute([$b['cuenta'], $b['haber'], $b['debe'], round($saldo, 2), $fecha, $id, $detalle]);
            }
            $st = $pdo->prepare("SELECT cuenta, debe, haber FROM librodiario WHERE tabla = 'liqtarjeta' AND compasoc = ?");
            $st->execute([$id]);
            $lineas = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($lineas) {
                $numAsiento = siguienteNumeroAsiento($pdo, $ejPer['ejercicio']);
                foreach ($lineas as $l) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $l['cuenta'], (float) $l['haber'], (float) $l['debe'], $id, 'liqtarjeta', 1, $usuario, $detalle, $ejPer['ejercicio'], $ejPer['periodo']);
                }
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Liquidación #$id anulada; sus cupones volvieron a pendientes."]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
