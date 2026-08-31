<?php

function cuentaContableExiste(PDO $pdo, $cuenta): bool
{
    $cuenta = trim((string)$cuenta);
    if ($cuenta === '' || $cuenta === '0') {
        return false;
    }

    static $cache = [];
    if (!array_key_exists($cuenta, $cache)) {
        $stmt = $pdo->prepare('SELECT 1 FROM plandecuentas WHERE numcuenta = ? LIMIT 1');
        $stmt->execute([$cuenta]);
        $cache[$cuenta] = (bool)$stmt->fetchColumn();
    }
    return $cache[$cuenta];
}

function resolverCuentaContable(PDO $pdo, $cuentaPropia, $cuentaGlobal, string $contexto): string
{
    if (cuentaContableExiste($pdo, $cuentaPropia)) {
        return trim((string)$cuentaPropia);
    }
    if (cuentaContableExiste($pdo, $cuentaGlobal)) {
        return trim((string)$cuentaGlobal);
    }

    throw new Exception("No existe una cuenta contable válida para $contexto. Configure la cuenta propia o su cuenta global de respaldo.");
}

function resolverCuentaMedioPago(PDO $pdo, int $medioId, string $contexto): string
{
    $stmt = $pdo->prepare('SELECT tipodecuenta, cuentacontable FROM mediosdepago WHERE id = ? LIMIT 1');
    $stmt->execute([$medioId]);
    $medio = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$medio) {
        throw new Exception("No se encontró el medio de pago para $contexto.");
    }
    if (cuentaContableExiste($pdo, $medio['cuentacontable'])) {
        return trim((string)$medio['cuentacontable']);
    }

    // NOTA: este fallback busca la cuenta de "otro medio del mismo tipo" cuando el
    // elegido no tiene cuenta propia configurada. Es una red de contención para datos
    // legacy cargados antes de que el ABM de Tesorería exigiera la cuenta (ver
    // api_tesoreria_cuentas.php). Puede mezclar en una misma cuenta contable movimientos
    // de dos cajas/bancos distintos: se mantiene por compatibilidad, pero lo correcto es
    // que el usuario complete la cuenta propia de cada medio en Tesorería > Cuentas.
    $fallback = $pdo->prepare("SELECT m.cuentacontable
        FROM mediosdepago m
        INNER JOIN plandecuentas p ON p.numcuenta = m.cuentacontable
        WHERE m.tipodecuenta = ? AND m.habilitado = 1
          AND m.cuentacontable IS NOT NULL AND m.cuentacontable <> '' AND m.id <> ?
        ORDER BY m.id ASC LIMIT 1");
    $fallback->execute([$medio['tipodecuenta'], $medioId]);
    $cuentaGlobal = $fallback->fetchColumn();
    if (cuentaContableExiste($pdo, $cuentaGlobal)) {
        return trim((string)$cuentaGlobal);
    }

    throw new Exception("El medio de pago usado en $contexto no tiene una cuenta contable válida ni existe otra cuenta global del mismo tipo.");
}

/**
 * Resuelve la cuenta contable de "el" medio de pago habilitado de un tipo dado
 * (por ejemplo 'Cheques'), para operaciones que no reciben un id de medio explícito
 * (depósito/cobro de cheques). Si hay cero o más de un medio habilitado de ese tipo,
 * falla en lugar de adivinar, para no postear en la cuenta contable equivocada.
 */
function resolverCuentaMedioPagoPorTipo(PDO $pdo, string $tipo, string $contexto): string
{
    $stmt = $pdo->prepare("SELECT id, cuentacontable FROM mediosdepago WHERE tipodecuenta = ? AND habilitado = 1");
    $stmt->execute([$tipo]);
    $medios = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($medios) === 0) {
        throw new Exception("No hay ningún medio de pago habilitado de tipo '$tipo' para $contexto. Configúrelo en Tesorería > Cuentas.");
    }
    if (count($medios) > 1) {
        throw new Exception("Hay más de un medio de pago habilitado de tipo '$tipo'; no se puede determinar automáticamente cuál usar para $contexto.");
    }
    if (!cuentaContableExiste($pdo, $medios[0]['cuentacontable'])) {
        throw new Exception("El medio de pago de tipo '$tipo' no tiene una cuenta contable configurada o válida. Revíselo en Tesorería > Cuentas.");
    }
    return trim((string)$medios[0]['cuentacontable']);
}

/**
 * Graba una línea de asiento en el libro diario. Salta las líneas de importe 0 y
 * falla ruidosamente si falta configurar la cuenta contable para un importe real, en
 * vez de postear silenciosamente con una cuenta vacía o inexistente.
 * (Versión centralizada; antes estaba duplicada en varios api_*.php.)
 */
function grabarLineaAsiento($pdo, $num, $fec, $cta, $deb, $hab, $comp, $tab, $fis, $user, $det, $eje, $per)
{
    if (round($deb, 2) == 0 && round($hab, 2) == 0)
        return;
    if (empty($cta))
        throw new Exception("Falta configurar una cuenta contable para postear un movimiento de \$" . number_format(max($deb, $hab), 2) . " en el comprobante #$comp.");

    $sql = "INSERT INTO librodiario (numasiento, fecha, cuenta, debe, haber, compasoc, tabla, fiscal, usuario, detalle, idejercicio, periodo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$num, $fec, $cta, $deb, $hab, $comp, $tab, $fis, $user, $det, $eje, $per]);
}

/**
 * Resuelve ejercicio/período contable abierto para una fecha, o lanza excepción.
 * (Versión centralizada; antes estaba duplicada idéntica en varios api_*.php.)
 * Devuelve ['ejercicio' => int, 'periodo' => int].
 */
function resolverEjercicioPeriodo(PDO $pdo, string $fecha): array
{
    $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
    $stmtEjer->execute([$fecha, $fecha]);
    $ejercicio = (int) $stmtEjer->fetchColumn();
    if (!$ejercicio) throw new Exception("No hay un ejercicio contable abierto para la fecha ($fecha).");

    $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
    $stmtPer->execute([$ejercicio]);
    $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
    if (!$rowPer) throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");

    $fechaDT = new DateTime($fecha);
    $mesesDesdeInicio = (((int) $fechaDT->format('Y') - (int) $rowPer['anioinicio']) * 12) + ((int) $fechaDT->format('m') - (int) $rowPer['mesinicio']);
    $periodo = $mesesDesdeInicio + 1;
    if ($periodo < 1 || $periodo > (int) $rowPer['cantperiodos']) throw new Exception("La fecha no pertenece a ningún período válido del ejercicio.");
    $campoP = "p" . $periodo;
    if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0) throw new Exception("El período contable $periodo está cerrado.");

    return ['ejercicio' => $ejercicio, 'periodo' => $periodo];
}

/**
 * Próximo número de asiento del libro diario para un ejercicio (bloquea la fila con
 * FOR UPDATE; debe llamarse dentro de una transacción para evitar números duplicados).
 */
function siguienteNumeroAsiento(PDO $pdo, int $ejercicioId): int
{
    $stmt = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
    $stmt->execute([$ejercicioId]);
    return (int)$stmt->fetchColumn() + 1;
}
