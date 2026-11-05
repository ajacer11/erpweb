<?php
/** Gestión de imputaciones existentes y nuevas, sin alterar tesorería ni CC. */

function gestionImputacionesConfig(string $tipo): array {
    if ($tipo === 'proveedor') return [
        'factura' => 'facturasdecompra', 'entidad_factura' => 'proveedorid',
        'pago' => 'pagoaproveedores', 'entidad_pago' => 'proveedor',
        'relacion' => 'pagosfacturasproveedores', 'saldo_pago' => 'saldoacuenta',
        'base_factura' => 'f.total',
    ];
    if ($tipo === 'cliente') return [
        'factura' => 'tickets', 'entidad_factura' => 'cliente',
        'pago' => 'pagos', 'entidad_pago' => 'cliente',
        'relacion' => 'cobrosfacturasclientes', 'saldo_pago' => 'saldoaimputar',
        // En una venta con pago mixto solo queda pendiente lo enviado a cuenta
        // corriente. NULL = comprobante anterior a la columna: se toma el total.
        'base_factura' => 'COALESCE(f.importectacte, f.total)',
    ];
    throw new InvalidArgumentException('Tipo de imputación inválido.');
}

function totalPagoGestion(array $pago, string $tipo): float {
    return $tipo === 'proveedor' ? (float)$pago['total'] :
        (float)$pago['imp1'] + (float)$pago['imp2'] + (float)$pago['retiva'] + (float)$pago['retgan'] + (float)$pago['retiibb'];
}

function sumaImputadaGestion(PDO $pdo, string $tipo, string $campo, int $id): float {
    $c = gestionImputacionesConfig($tipo);
    $filtro = $tipo === 'proveedor' ? " AND tipo = 'P'" : " AND tablapago = 'pagos'";
    if ($campo === 'idfactura') $filtro = '';
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(importepagado), 0) FROM {$c['relacion']} WHERE $campo = ?$filtro");
    $stmt->execute([$id]);
    return (float)$stmt->fetchColumn();
}

function recalcularGestion(PDO $pdo, string $tipo, int $pagoId, int $facturaId): void {
    $c = gestionImputacionesConfig($tipo);
    $st = $pdo->prepare("SELECT * FROM {$c['pago']} WHERE id = ?");
    $st->execute([$pagoId]);
    $pago = $st->fetch(PDO::FETCH_ASSOC);
    $disponible = (int)$pago['estado'] === 1000 ? 0 :
        max(0, round(totalPagoGestion($pago, $tipo) - sumaImputadaGestion($pdo, $tipo, 'idpago', $pagoId), 2));
    $pdo->prepare("UPDATE {$c['pago']} SET {$c['saldo_pago']} = ? WHERE id = ?")->execute([$disponible, $pagoId]);

    $st = $pdo->prepare("SELECT {$c['base_factura']} FROM {$c['factura']} f WHERE f.id = ?");
    $st->execute([$facturaId]);
    $pendiente = max(0, round((float)$st->fetchColumn() - sumaImputadaGestion($pdo, $tipo, 'idfactura', $facturaId), 2));
    if ($tipo === 'proveedor') {
        $pdo->prepare('UPDATE facturasdecompra SET saldoaimputar = ?, estado = ? WHERE id = ?')
            ->execute([$pendiente, $pendiente > 0.009 ? 1 : 2, $facturaId]);
    } else {
        $pdo->prepare('UPDATE tickets SET pagada = ? WHERE id = ?')
            ->execute([$pendiente > 0.009 ? 0 : 1, $facturaId]);
    }
}

function listarGestionImputaciones(PDO $pdo, string $tipo, int $entidadId, int $pagina, string $buscar, bool $soloPendientes): array {
    if ($entidadId < 1) throw new Exception('Seleccione una entidad.');
    $c = gestionImputacionesConfig($tipo);
    $pagina = max(1, $pagina);
    $offset = ($pagina - 1) * 50;
    $numero = $tipo === 'proveedor'
        ? "CONCAT(LPAD(f.pv, 4, '0'), '-', LPAD(f.numfact, 8, '0'))"
        : "CONCAT(LPAD(f.PtoVta, 4, '0'), '-', LPAD(f.numcomp, 8, '0'))";
    $condicion = $tipo === 'proveedor'
        ? "f.estado <> 1000 AND f.tipocomp NOT IN (3,8,13,101)"
        : "f.tipocomp NOT IN (3,8,13,101) AND (f.pagada = 0 OR EXISTS (SELECT 1 FROM cobrosfacturasclientes x WHERE x.idfactura = f.id))";
    $sql = "SELECT f.id, f.fecha, $numero AS numero, f.total,
                   COALESCE(SUM(r.importepagado), 0) AS imputado,
                   {$c['base_factura']} - COALESCE(SUM(r.importepagado), 0) AS pendiente
            FROM {$c['factura']} f
            LEFT JOIN {$c['relacion']} r ON r.idfactura = f.id
            WHERE f.{$c['entidad_factura']} = ? AND $condicion";
    $params = [$entidadId];
    if ($buscar !== '') {
        $sql .= " AND (CAST(f.id AS CHAR) LIKE ? OR $numero LIKE ?)";
        $params[] = '%' . $buscar . '%';
        $params[] = '%' . $buscar . '%';
    }
    $sql .= ' GROUP BY f.id';
    if ($soloPendientes) $sql .= ' HAVING pendiente > 0.009';
    $sql .= " ORDER BY f.fecha DESC, f.id DESC LIMIT 51 OFFSET $offset";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $facturas = $st->fetchAll(PDO::FETCH_ASSOC);
    $hayMas = count($facturas) > 50;
    if ($hayMas) array_pop($facturas);
    foreach ($facturas as &$f) $f['imputaciones'] = [];
    unset($f);
    if ($facturas) {
        $indices = array_column($facturas, null, 'id');
        $marks = implode(',', array_fill(0, count($indices), '?'));
        $tipoCol = $tipo === 'proveedor' ? 'r.tipo' : 'r.tablapago AS tipo';
        $st = $pdo->prepare("SELECT r.id, r.idfactura, r.idpago, r.importepagado, $tipoCol
                             FROM {$c['relacion']} r WHERE r.idfactura IN ($marks) ORDER BY r.id");
        $st->execute(array_keys($indices));
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $imp) {
            $indices[$imp['idfactura']]['imputaciones'][] = $imp;
        }
        $facturas = array_values($indices);
    }
    return ['facturas' => $facturas, 'pagina' => $pagina, 'hay_mas' => $hayMas];
}

function buscarFondosGestion(PDO $pdo, string $tipo, int $entidadId, string $buscar): array {
    if ($entidadId < 1) throw new Exception('Seleccione una entidad.');
    $c = gestionImputacionesConfig($tipo);
    $total = $tipo === 'proveedor' ? 'p.total' : '(p.imp1+p.imp2+p.retiva+p.retgan+p.retiibb)';
    $filtro = $tipo === 'proveedor' ? " AND r.tipo = 'P'" : " AND r.tablapago = 'pagos'";
    $fecha = $tipo === 'proveedor' ? 'p.fecharecibo' : 'p.fecha';
    $sql = "SELECT p.id, $fecha AS fecha, $total AS total,
                   $total - COALESCE((SELECT SUM(r.importepagado) FROM {$c['relacion']} r
                       WHERE r.idpago = p.id $filtro), 0) AS disponible
            FROM {$c['pago']} p WHERE p.{$c['entidad_pago']} = ? AND p.estado <> 1000";
    $params = [$entidadId];
    if ($buscar !== '') {
        $sql .= ' AND CAST(p.id AS CHAR) LIKE ?';
        $params[] = '%' . $buscar . '%';
    }
    $sql .= ' HAVING disponible > 0.009 ORDER BY fecha DESC, p.id DESC LIMIT 40';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function modificarGestionImputacion(PDO $pdo, string $tipo, int $entidadId, string $accion,
                                     int $facturaId, int $pagoId, int $imputacionId, float $importe): void {
    $c = gestionImputacionesConfig($tipo);
    if ($entidadId < 1 || !in_array($accion, ['agregar', 'quitar', 'importe'], true)) throw new Exception('Datos de imputación inválidos.');
    $transaccionPropia = !$pdo->inTransaction();
    if ($transaccionPropia) $pdo->beginTransaction();
    else $pdo->exec('SAVEPOINT gestion_imputacion');
    try {
        $tipoRel = $tipo === 'proveedor' ? " AND tipo = 'P'" : " AND tablapago = 'pagos'";
        if ($accion !== 'agregar') {
            $st = $pdo->prepare("SELECT idfactura, idpago, importepagado FROM {$c['relacion']} WHERE id = ? $tipoRel FOR UPDATE");
            $st->execute([$imputacionId]);
            $rel = $st->fetch(PDO::FETCH_ASSOC);
            if (!$rel) throw new Exception('No se encontró una imputación editable.');
            $facturaId = (int)$rel['idfactura'];
            $pagoId = (int)$rel['idpago'];
        }
        $st = $pdo->prepare("SELECT * FROM {$c['pago']} WHERE id = ? AND {$c['entidad_pago']} = ? FOR UPDATE");
        $st->execute([$pagoId, $entidadId]);
        $pago = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pago) throw new Exception('Pago o recibo inexistente o de otra entidad.');
        if ($accion !== 'quitar' && (int)$pago['estado'] === 1000) throw new Exception('No se puede imputar un pago o recibo anulado.');
        $st = $pdo->prepare("SELECT * FROM {$c['factura']} WHERE id = ? AND {$c['entidad_factura']} = ? FOR UPDATE");
        $st->execute([$facturaId, $entidadId]);
        $factura = $st->fetch(PDO::FETCH_ASSOC);
        if (!$factura || in_array((int)$factura['tipocomp'], [3,8,13,101], true)
            || ($tipo === 'proveedor' && (int)$factura['estado'] === 1000)) {
            throw new Exception('Comprobante inexistente, anulado o no imputable.');
        }
        if ($accion === 'agregar' && $tipo === 'cliente' && (int)$factura['pagada'] === 1
            && sumaImputadaGestion($pdo, $tipo, 'idfactura', $facturaId) < 0.009) {
            throw new Exception('La venta ya fue cobrada al contado.');
        }
        if ($accion === 'agregar' && $tipo === 'proveedor' && (int)$factura['estado'] === 2
            && sumaImputadaGestion($pdo, $tipo, 'idfactura', $facturaId) < 0.009) {
            throw new Exception('El comprobante figura cancelado sin imputaciones registradas.');
        }
        if ($accion === 'agregar') {
            $fiscalPago = $tipo === 'proveedor' ? (int)$pago['fiscal'] : null;
            if ($tipo === 'cliente') {
                $st = $pdo->prepare("SELECT fiscal FROM ccclientes WHERE tabla = 'pagos' AND compasoc = ? AND cliente = ? LIMIT 1");
                $st->execute([$pagoId, $entidadId]);
                $fiscalPago = $st->fetchColumn();
                if ($fiscalPago === false) throw new Exception('No se puede determinar la condición fiscal del recibo.');
            }
            $fiscalFactura = $tipo === 'proveedor' ? (int)$factura['fiscal'] : ((int)$factura['tipocomp'] < 100 ? 1 : 0);
            if ((int)$fiscalPago !== $fiscalFactura) throw new Exception('La condición fiscal del pago y del comprobante no coincide.');
        }

        $anterior = $accion === 'agregar' ? 0.0 : (float)$rel['importepagado'];
        $nuevo = $accion === 'quitar' ? 0.0 : round($importe, 2);
        if ($accion !== 'quitar' && (!is_finite($importe) || $nuevo <= 0)) throw new Exception('El importe debe ser mayor a cero.');
        $disponible = totalPagoGestion($pago, $tipo) - sumaImputadaGestion($pdo, $tipo, 'idpago', $pagoId) + $anterior;
        $pendiente = (float)($factura['importectacte'] ?? $factura['total']) - sumaImputadaGestion($pdo, $tipo, 'idfactura', $facturaId) + $anterior;
        if ($nuevo > $disponible + 0.009 || $nuevo > $pendiente + 0.009) {
            throw new Exception('El importe supera el saldo disponible del pago o del comprobante.');
        }
        if ($accion === 'agregar') {
            $tipoInsert = $tipo === 'proveedor' ? "'P'" : "'pagos'";
            $colTipo = $tipo === 'proveedor' ? 'tipo' : 'tablapago';
            $pdo->prepare("INSERT INTO {$c['relacion']} (idpago, idfactura, importepagado, $colTipo) VALUES (?, ?, ?, $tipoInsert)")
                ->execute([$pagoId, $facturaId, $nuevo]);
        } elseif ($accion === 'quitar') {
            $pdo->prepare("DELETE FROM {$c['relacion']} WHERE id = ?")->execute([$imputacionId]);
        } else {
            $pdo->prepare("UPDATE {$c['relacion']} SET importepagado = ? WHERE id = ?")->execute([$nuevo, $imputacionId]);
        }
        recalcularGestion($pdo, $tipo, $pagoId, $facturaId);
        if ($transaccionPropia) $pdo->commit();
        else $pdo->exec('RELEASE SAVEPOINT gestion_imputacion');
    } catch (Throwable $e) {
        if ($transaccionPropia && $pdo->inTransaction()) $pdo->rollBack();
        elseif (!$transaccionPropia && $pdo->inTransaction()) $pdo->exec('ROLLBACK TO SAVEPOINT gestion_imputacion');
        throw $e;
    }
}

/** Imputación rápida FIFO: calcula todos los saldos en el servidor. */
function autoImputarSeleccionGestion(PDO $pdo, string $tipo, int $entidadId, array $pagoIds, array $facturaIds): void {
    $c = gestionImputacionesConfig($tipo);
    foreach ($pagoIds as $pagoId) {
        $st = $pdo->prepare("SELECT * FROM {$c['pago']} WHERE id = ? AND {$c['entidad_pago']} = ? AND estado <> 1000");
        $st->execute([(int)$pagoId, $entidadId]);
        $pago = $st->fetch(PDO::FETCH_ASSOC);
        if (!$pago) throw new Exception('Pago o recibo no disponible.');
        foreach ($facturaIds as $facturaId) {
            $disponible = totalPagoGestion($pago, $tipo) - sumaImputadaGestion($pdo, $tipo, 'idpago', (int)$pagoId);
            if ($disponible <= 0.009) break;
            $st = $pdo->prepare("SELECT {$c['base_factura']} FROM {$c['factura']} f WHERE f.id = ? AND f.{$c['entidad_factura']} = ?");
            $st->execute([(int)$facturaId, $entidadId]);
            $totalFactura = $st->fetchColumn();
            if ($totalFactura === false) throw new Exception('Comprobante no encontrado.');
            $pendiente = (float)$totalFactura - sumaImputadaGestion($pdo, $tipo, 'idfactura', (int)$facturaId);
            if ($pendiente <= 0.009) continue;
            modificarGestionImputacion($pdo, $tipo, $entidadId, 'agregar', (int)$facturaId,
                (int)$pagoId, 0, round(min($disponible, $pendiente), 2));
        }
    }
}
