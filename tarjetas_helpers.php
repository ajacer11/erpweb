<?php
/**
 * tarjetas_helpers.php - Cupones de tarjeta (sql/2026-10-15_tarjetas_cupones.sql).
 *
 * Todo cobro con un medio de pago de tipo 'Tarjetas' deja un cupón en cupones_tarjeta:
 * importe positivo si entra dinero (venta, recibo) y negativo si sale (nota de crédito,
 * reverso). La liquidación toma cupones pendientes (estado 0) y los marca liquidados (1).
 *
 * Si el tenant todavía no cargó ninguna tarjeta en el catálogo, no se exige elegir una
 * (el cupón queda con idtarjeta 0): así no se traba el mostrador de quien no usa el módulo.
 */

const CUPON_PENDIENTE = 0;
const CUPON_LIQUIDADO = 1;
const CUPON_ANULADO = 1000;

function tarjetasHabilitadas(PDO $pdo): array
{
    try {
        return $pdo->query("SELECT id, descripcion, tipo, diasalcobro FROM tarjetas WHERE habilitada = 1 ORDER BY descripcion")->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return []; // base sin la migración: sin catálogo
    }
}

/**
 * Normaliza los datos de tarjeta que vienen del navegador para un medio de pago.
 * Devuelve null si el medio no es de tipo Tarjetas.
 */
function datosTarjetaCobro(PDO $pdo, array $medio, $dato, string $contexto): ?array
{
    if (($medio['tipodecuenta'] ?? '') !== 'Tarjetas') return null;
    $dato = is_array($dato) ? $dato : [];
    $id = (int) ($dato['id'] ?? 0);
    $cuotas = max(1, (int) ($dato['cuotas'] ?? 1));
    $habilitadas = tarjetasHabilitadas($pdo);
    if ($habilitadas) {
        $ids = array_map(static fn($t) => (int) $t['id'], $habilitadas);
        if (!$id) throw new Exception("Falta elegir la tarjeta en $contexto.");
        if (!in_array($id, $ids, true)) throw new Exception("La tarjeta elegida para $contexto no existe o está deshabilitada.");
    } else {
        $id = 0;
    }
    if ($cuotas > 99) throw new Exception("Cantidad de cuotas inválida en $contexto.");
    return [
        'idtarjeta' => $id,
        'cuotas' => $cuotas,
        'lote' => mb_substr(trim((string) ($dato['lote'] ?? '')), 0, 20),
        'numcupon' => mb_substr(trim((string) ($dato['cupon'] ?? '')), 0, 30),
    ];
}

/** Graba un cupón. $importe con signo: + entra dinero, - sale. */
function registrarCupon(PDO $pdo, array $t, float $importe, int $idMedio, string $tabla, int $compasoc,
                        int $cliente, int $fiscal, string $fecha, string $usuario): int
{
    if (abs($importe) < 0.005) return 0;
    $pdo->prepare("INSERT INTO cupones_tarjeta (fecha, idtarjeta, cuotas, lote, numcupon, importe, idmediopago, tabla, compasoc, cliente, fiscal, estado, usuario)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)")
        ->execute([$fecha, $t['idtarjeta'], $t['cuotas'], $t['lote'], $t['numcupon'], round($importe, 2), $idMedio,
                   $tabla, $compasoc, $cliente, $fiscal, $usuario]);
    return (int) $pdo->lastInsertId();
}

/**
 * Revierte los cupones de un comprobante que se anula (recibo, etc.): los pendientes se
 * anulan; los ya liquidados no se pueden tocar, así que se agrega un cupón de ajuste por
 * el importe contrario, que se descontará en la próxima liquidación de esa tarjeta.
 */
function revertirCuponesDe(PDO $pdo, string $tabla, int $compasoc, string $fecha, string $usuario): void
{
    try {
        $st = $pdo->prepare("SELECT * FROM cupones_tarjeta WHERE tabla = ? AND compasoc = ? AND estado <> 1000 FOR UPDATE");
        $st->execute([$tabla, $compasoc]);
    } catch (PDOException $e) {
        return; // base sin la migración
    }
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if ((int) $c['estado'] === CUPON_PENDIENTE) {
            $pdo->prepare("UPDATE cupones_tarjeta SET estado = 1000 WHERE id = ?")->execute([$c['id']]);
        } else {
            registrarCupon($pdo, $c, -(float) $c['importe'], (int) $c['idmediopago'], 'ajuste', (int) $c['id'],
                           (int) $c['cliente'], (int) $c['fiscal'], $fecha, $usuario);
        }
    }
}

/** Tarjeta usada en el último cupón de un comprobante (para ajustes que restan). */
function tarjetaDeComprobante(PDO $pdo, string $tabla, int $compasoc, int $idMedio): array
{
    try {
        $st = $pdo->prepare("SELECT idtarjeta, cuotas, lote, numcupon FROM cupones_tarjeta WHERE tabla = ? AND compasoc = ? AND idmediopago = ? AND estado <> 1000 ORDER BY id DESC LIMIT 1");
        $st->execute([$tabla, $compasoc, $idMedio]);
        $r = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $r = false;
    }
    return $r ?: ['idtarjeta' => 0, 'cuotas' => 1, 'lote' => '', 'numcupon' => ''];
}
