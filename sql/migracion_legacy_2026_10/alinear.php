<?php
/**
 * Deja las columnas de una base migrada idénticas a la referencia (estructura de westit_sofi):
 * tipo, CHARACTER SET / COLLATE, NULL, DEFAULT y EXTRA. Borra columnas basura del legacy que el
 * sistema no usa. Lo usan paso2/paso3 (local, contra ref_sofi) y alinear_vps.php (VPS, contra westit_sofi).
 *
 * Excepciones a "idéntica a sofi" (se conservan):
 *  - lineapresupuestosclientes.impint / lineapedidosclientes.impint: las usa api_presupuestos*.php y sofi no
 *    la tiene (sofi está desactualizada en ese punto).
 *  - Caja.ptovta: historial de punto de venta del legacy de NICOLAS.
 *  - Tablas de respaldo/auditoría de la migración (cobros_backup, _mig_renumerados).
 *
 * Los valores double del legacy pasan a decimal(18,2) igual que en sofi (se redondean a 2 decimales).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // solo consola

const COLUMNAS_BASURA_LEGACY = [
    'api_config_productos_externa' => ['cuit', 'db_id'],      // en los tenants no se usa: se lee de la base maestra
    'log_accesos_api_productos_externa' => ['cuit'],
    'cambiostoken' => ['computadora?'],
    'ejercicios' => ['anio?'],
    'pagosvende' => ['importendc?'],
    'pagos' => ['timestamp'],
];

function alinearConReferencia(PDO $pdo, string $db, string $ref, ?callable $log = null): array
{
    $log = $log ?? fn($m) => print("$m\n");
    $q = fn(?string $v) => $v === null ? 'NULL' : $pdo->quote($v);
    $pdo->exec("SET SESSION sql_mode = ''"); // redondeos double->decimal y NULL->default sin abortar
    $cols = $pdo->query("SELECT s.TABLE_NAME, s.COLUMN_NAME, s.COLUMN_TYPE, s.CHARACTER_SET_NAME, s.COLLATION_NAME, s.IS_NULLABLE,
            s.COLUMN_DEFAULT, s.EXTRA, s.COLUMN_COMMENT
        FROM information_schema.columns n
        JOIN information_schema.columns s ON s.TABLE_SCHEMA = " . $q($ref) . " AND s.TABLE_NAME = n.TABLE_NAME AND s.COLUMN_NAME = n.COLUMN_NAME
        WHERE n.TABLE_SCHEMA = " . $q($db) . " AND (n.COLUMN_TYPE <> s.COLUMN_TYPE OR n.IS_NULLABLE <> s.IS_NULLABLE
              OR NOT (n.COLUMN_DEFAULT <=> s.COLUMN_DEFAULT) OR NOT (n.COLLATION_NAME <=> s.COLLATION_NAME) OR n.EXTRA <> s.EXTRA)
        ORDER BY s.TABLE_NAME, s.ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    // Índices de la referencia que faltan (PRIMARY primero: un AUTO_INCREMENT necesita su clave)
    $idx = $pdo->query("SELECT s.TABLE_NAME t, s.INDEX_NAME i, MAX(s.NON_UNIQUE) nu, GROUP_CONCAT(CONCAT('`', s.COLUMN_NAME, '`') ORDER BY s.SEQ_IN_INDEX) cols,
            SUM(NOT EXISTS (SELECT 1 FROM information_schema.columns c WHERE c.TABLE_SCHEMA = " . $q($db) . " AND c.TABLE_NAME = s.TABLE_NAME AND c.COLUMN_NAME = s.COLUMN_NAME)) faltan_cols
        FROM information_schema.statistics s
        WHERE s.TABLE_SCHEMA = " . $q($ref) . " AND EXISTS (SELECT 1 FROM information_schema.tables t WHERE t.TABLE_SCHEMA = " . $q($db) . " AND BINARY t.TABLE_NAME = BINARY s.TABLE_NAME)
          AND NOT EXISTS (SELECT 1 FROM information_schema.statistics d WHERE d.TABLE_SCHEMA = " . $q($db) . " AND d.TABLE_NAME = s.TABLE_NAME AND d.INDEX_NAME = s.INDEX_NAME)
        GROUP BY s.TABLE_NAME, s.INDEX_NAME ORDER BY (s.INDEX_NAME = 'PRIMARY') DESC, s.TABLE_NAME")->fetchAll(PDO::FETCH_ASSOC);
    $indices = 0; $errores = [];
    foreach ($idx as $i) {
        if ($i['faltan_cols']) continue;
        $def = $i['i'] === 'PRIMARY' ? "ADD PRIMARY KEY ({$i['cols']})" : ($i['nu'] ? 'ADD INDEX' : 'ADD UNIQUE INDEX') . " `{$i['i']}` ({$i['cols']})";
        try { $pdo->exec("ALTER TABLE `$db`.`{$i['t']}` $def"); $indices++; }
        catch (Throwable $e) { $errores[] = "{$i['t']}.{$i['i']}: " . $e->getMessage(); $log("  índice {$i['t']}.{$i['i']}: " . $e->getMessage()); }
    }

    $porTabla = [];
    foreach ($cols as $c) {
        $d = 'MODIFY `' . $c['COLUMN_NAME'] . '` ' . $c['COLUMN_TYPE'];
        if ($c['CHARACTER_SET_NAME']) $d .= ' CHARACTER SET ' . $c['CHARACTER_SET_NAME'] . ' COLLATE ' . $c['COLLATION_NAME'];
        $d .= $c['IS_NULLABLE'] === 'YES' ? ' NULL' : ' NOT NULL';
        if ($c['COLUMN_DEFAULT'] !== null) $d .= ' DEFAULT ' . $c['COLUMN_DEFAULT'];
        elseif ($c['IS_NULLABLE'] === 'YES' && stripos($c['EXTRA'], 'auto_increment') === false) $d .= ' DEFAULT NULL';
        if ($c['EXTRA'] !== '' && stripos($c['EXTRA'], 'GENERATED') === false) $d .= ' ' . strtoupper($c['EXTRA']);
        if ($c['COLUMN_COMMENT'] !== '') $d .= ' COMMENT ' . $q($c['COLUMN_COMMENT']);
        $porTabla[$c['TABLE_NAME']][] = $d;
    }
    $ok = 0;
    foreach ($porTabla as $t => $mods) {
        try { $pdo->exec("ALTER TABLE `$db`.`$t` " . implode(', ', $mods)); $ok++; }
        catch (Throwable $e) { $errores[] = "$t: " . $e->getMessage(); $log("  alinear $t: " . $e->getMessage()); }
    }
    $borradas = 0;
    foreach (COLUMNAS_BASURA_LEGACY as $t => $lista) {
        foreach ($lista as $col) {
            $existe = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $existe->execute([$db, $t, $col]);
            $enRef = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
            $enRef->execute([$ref, $t, $col]);
            if ($existe->fetchColumn() && !$enRef->fetchColumn()) {
                // primero los índices que usan la columna (ej. uq_api_config_productos_cuit_db)
                $ix = $pdo->prepare("SELECT DISTINCT INDEX_NAME FROM information_schema.statistics WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $ix->execute([$db, $t, $col]);
                foreach ($ix->fetchAll(PDO::FETCH_COLUMN) as $nombreIdx) {
                    try { $pdo->exec("ALTER TABLE `$db`.`$t` DROP INDEX `$nombreIdx`"); } catch (Throwable $e) { }
                }
                try { $pdo->exec("ALTER TABLE `$db`.`$t` DROP COLUMN `$col`"); $borradas++; }
                catch (Throwable $e) { $errores[] = "$t.$col: " . $e->getMessage(); $log("  borrar $t.$col: " . $e->getMessage()); }
            }
        }
    }
    $pdo->exec("SET SESSION sql_mode = DEFAULT");
    return ['columnas' => count($cols), 'tablas' => $ok, 'indices' => $indices, 'borradas' => $borradas, 'errores' => $errores];
}
