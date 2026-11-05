<?php
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$old = 'staluc_old';
$new = 'staluc_new';
$final = 'staluc_final';

function qi(string $v): string { return '`' . str_replace('`', '``', $v) . '`'; }
function columns(PDO $pdo, string $db, string $table): array {
    $st = $pdo->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
    $st->execute([$db, $table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}
function commonColumns(PDO $pdo, string $a, string $b, string $table): array {
    return array_values(array_intersect(columns($pdo, $a, $table), columns($pdo, $b, $table)));
}
function copyAll(PDO $pdo, string $src, string $dst, string $table, bool $includeId = true): void {
    $cols = commonColumns($pdo, $src, $dst, $table);
    if (!$includeId) $cols = array_values(array_filter($cols, fn($c) => strtolower($c) !== 'id'));
    if (!$cols) return;
    $list = implode(',', array_map('qi', $cols));
    $pdo->exec('INSERT INTO ' . qi($dst) . '.' . qi($table) . " ($list) SELECT $list FROM " . qi($src) . '.' . qi($table));
}
function replaceFromNew(PDO $pdo, string $new, string $final, string $table): void {
    $pdo->exec('DELETE FROM ' . qi($final) . '.' . qi($table));
    copyAll($pdo, $new, $final, $table, true);
}
function insertRowWithoutId(PDO $pdo, string $srcDb, string $dstDb, string $table, int $id, array $overrides = []): int {
    $cols = commonColumns($pdo, $srcDb, $dstDb, $table);
    $cols = array_values(array_filter($cols, fn($c) => strtolower($c) !== 'id'));
    $select = [];
    $params = [];
    foreach ($cols as $c) {
        if (array_key_exists($c, $overrides)) {
            $select[] = '?';
            $params[] = $overrides[$c];
        } else {
            $select[] = qi($c);
        }
    }
    $list = implode(',', array_map('qi', $cols));
    $sql = 'INSERT INTO ' . qi($dstDb) . '.' . qi($table) . " ($list) SELECT " . implode(',', $select) .
           ' FROM ' . qi($srcDb) . '.' . qi($table) . ' WHERE id=?';
    $params[] = $id;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return (int)$pdo->lastInsertId();
}

function remapColumn(PDO $pdo, string $db, string $table, string $column, string $mapTable): void {
    $pdo->exec(
        'UPDATE ' . qi($db) . '.' . qi($table) . ' d ' .
        'JOIN ' . qi($db) . '.' . qi($mapTable) . ' m ON m.id_old=d.' . qi($column) . ' ' .
        'SET d.' . qi($column) . '=m.id_new'
    );
}

if ((int)$pdo->query("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $pdo->quote($final))->fetchColumn() > 0) {
    throw new RuntimeException("La base $final ya existe; no se modificó nada.");
}

$pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('CREATE DATABASE ' . qi($final) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE ' . qi($final));

try {
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=" . $pdo->quote($new) . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec('CREATE TABLE ' . qi($final) . '.' . qi($table) . ' LIKE ' . qi($new) . '.' . qi($table));
    }

    // Base histórica completa usando el esquema vigente.
    $oldTables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=" . $pdo->quote($old) . " AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($oldTables as $table) {
        if (in_array($table, $tables, true)) copyAll($pdo, $old, $final, $table, true);
    }

    // Configuración/maestros operativos vigentes del sistema actualmente en uso.
    $authoritativeNew = [
        'configuracion', 'datosempresa', 'cuentaspredeterminadas', 'alb_usuarios',
        'cajas', 'ejercicios', 'ejperidodos', 'confrubros', 'listas',
        'tiposcomprobantes', 'tipomovcuenta', 'plandecuentas'
    ];

    // Antes de sustituir cajas, alinear las referencias históricas por número de puesto.
    $pdo->exec("UPDATE " . qi($final) . ".cajaxusuario x JOIN " . qi($old) . ".cajas oc ON oc.id=x.idcaja JOIN " . qi($new) . ".cajas nc ON nc.numcaja=oc.numcaja SET x.idcaja=nc.id");
    $pdo->exec("UPDATE " . qi($final) . ".controlcajas x JOIN " . qi($old) . ".cajas oc ON oc.id=x.idcaja JOIN " . qi($new) . ".cajas nc ON nc.numcaja=oc.numcaja SET x.idcaja=nc.id");

    // Las cajas físicas y sus saldos vigentes son exclusivamente los de staluc_new.
    // Los controles que seguían abiertos en legacy se cierran administrativamente
    // en la fecha de corte, conservando su saldo final solamente como dato histórico.
    $fechaCorte = $pdo->query('SELECT MAX(fecha) FROM ' . qi($old) . '.tickets')->fetchColumn() ?: '2026-08-29';
    $st = $pdo->prepare(
        'UPDATE ' . qi($final) . '.controlcajas c ' .
        'JOIN ' . qi($old) . '.controlcajas o ON o.id=c.id ' .
        "SET c.estado=0,c.cerradael=?,c.usuario_cierre='MIGRACION' " .
        'WHERE o.estado=1'
    );
    $st->execute([$fechaCorte . ' 23:59:59']);
    foreach ($authoritativeNew as $table) replaceFromNew($pdo, $new, $final, $table);

    // Los medios nuevos usan IDs 101+. Se carga el maestro vigente, se remapean
    // todas las referencias históricas de los maestros que ya habían sido migrados
    // y luego se eliminan solamente esas copias legacy duplicadas.
    $pdo->exec("UPDATE " . qi($final) . ".mediosdepago SET habilitado=0 WHERE tipodecuenta='Caja Central'");
    copyAll($pdo, $new, $final, 'mediosdepago', true);

    $pdo->exec("CREATE TABLE " . qi($final) . "._mig_map_mediosdepago (id_old INT PRIMARY KEY, id_new INT NOT NULL UNIQUE KEY)");
    $pdo->exec("INSERT INTO " . qi($final) . "._mig_map_mediosdepago (id_old,id_new) VALUES
        (1,101),(2,102),(3,103),(4,104),(5,105),(6,106),(7,107),
        (11,111),(12,112),(13,113),(14,114),(15,115),(16,116),(17,117),(19,108)");

    $medioRefs = [
        'auxticket' => ['mpago1', 'mpago2'],
        'cccuentastesoreria' => ['idcuenta'],
        'ccclientes' => ['mediopago', 'mediopago2'],
        'ccproveedores' => ['mediopago'],
        'ccvendedores' => ['mediopago'],
        'factdecompra' => ['mpago1', 'mpago2'],
        'lineadecompra' => ['mpago1', 'mpago2'],
        'lineadeventa' => ['mpago1', 'mpago2', 'mpago22'],
        'moviemientosccclientes' => ['mpago1', 'mpago2'],
        'moviemientosccproveedores' => ['mpago1', 'mpago2'],
        'pagoaproveedores' => ['mp1', 'mp2'],
        'pagos' => ['mp1', 'mp2'],
        'pagosprov' => ['medio'],
        'pagosvende' => ['mp1', 'mp2'],
        'pagosvendedores' => ['mp1', 'mp2'],
        'recibos' => ['medio'],
        'rrhhcc' => ['mediodepago'],
        'rrhhpagos' => ['mp1', 'mp2'],
        'tickets' => ['mpago1', 'mpago2'],
    ];
    foreach ($medioRefs as $table => $cols) {
        foreach ($cols as $col) remapColumn($pdo, $final, $table, $col, '_mig_map_mediosdepago');
    }
    $pdo->exec(
        'DELETE p FROM ' . qi($final) . '.mediosdepago p ' .
        'JOIN ' . qi($final) . '._mig_map_mediosdepago m ON m.id_old=p.id'
    );

    // Mapas explícitos de migración.
    $pdo->exec("CREATE TABLE " . qi($final) . "._mig_map_clientes (id_old INT PRIMARY KEY, id_new INT NOT NULL)");
    $pdo->exec("CREATE TABLE " . qi($final) . "._mig_map_lineadeventa (id_old BIGINT PRIMARY KEY, id_new BIGINT NOT NULL)");
    $pdo->exec("CREATE TABLE " . qi($final) . "._mig_map_tickets (id_old INT PRIMARY KEY, id_new INT NOT NULL)");

    // Consumidor Final se unifica con el ID histórico 1; los demás clientes nuevos
    // se conservan como altas independientes para no resolver CUIT duplicados de forma arbitraria.
    $pdo->exec("INSERT INTO " . qi($final) . "._mig_map_clientes VALUES (1,1)");
    $newClientIds = $pdo->query('SELECT id FROM ' . qi($new) . '.alb_clientes WHERE id<>1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $stMapCli = $pdo->prepare('INSERT INTO ' . qi($final) . '._mig_map_clientes (id_old,id_new) VALUES (?,?)');
    foreach ($newClientIds as $id) {
        $newId = insertRowWithoutId($pdo, $new, $final, 'alb_clientes', (int)$id);
        $stMapCli->execute([(int)$id, $newId]);
    }

    // Proveedor creado en el sistema nuevo (sin relaciones transaccionales en este lote).
    $newProviderIds = $pdo->query('SELECT id FROM ' . qi($new) . '.proveedores ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($newProviderIds as $id) insertRowWithoutId($pdo, $new, $final, 'proveedores', (int)$id);

    // Líneas nuevas primero, guardando mapa para lvdesde/lvhasta.
    $newLineIds = $pdo->query('SELECT id FROM ' . qi($new) . '.lineadeventa ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $stMapLine = $pdo->prepare('INSERT INTO ' . qi($final) . '._mig_map_lineadeventa (id_old,id_new) VALUES (?,?)');
    foreach ($newLineIds as $id) {
        $row = $pdo->query('SELECT cliente FROM ' . qi($new) . '.lineadeventa WHERE id=' . (int)$id)->fetch();
        $st = $pdo->prepare('SELECT id_new FROM ' . qi($final) . '._mig_map_clientes WHERE id_old=?');
        $st->execute([(int)$row['cliente']]);
        $clienteFinal = (int)($st->fetchColumn() ?: 1);
        $newId = insertRowWithoutId($pdo, $new, $final, 'lineadeventa', (int)$id, ['cliente' => $clienteFinal]);
        $stMapLine->execute([(int)$id, $newId]);
    }

    // Existe una colisión fiscal histórica real (FCB PV4 Nro1, CAE diferentes).
    // Los números fiscales no se alteran; se cambia el índice a no único.
    $pdo->exec('ALTER TABLE ' . qi($final) . '.tickets DROP INDEX pdv_tipo_y_numero_de_comprobante');
    $pdo->exec('ALTER TABLE ' . qi($final) . '.tickets ADD INDEX idx_pdv_tipo_numcomp (tipocomp,numcomp,PtoVta)');

    $newTicketIds = $pdo->query('SELECT id FROM ' . qi($new) . '.tickets ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    $stMapTicket = $pdo->prepare('INSERT INTO ' . qi($final) . '._mig_map_tickets (id_old,id_new) VALUES (?,?)');
    foreach ($newTicketIds as $id) {
        $row = $pdo->query('SELECT cliente,lvdesde,lvhasta FROM ' . qi($new) . '.tickets WHERE id=' . (int)$id)->fetch();
        $st = $pdo->prepare('SELECT id_new FROM ' . qi($final) . '._mig_map_clientes WHERE id_old=?');
        $st->execute([(int)$row['cliente']]);
        $clienteFinal = (int)($st->fetchColumn() ?: 1);
        $lineMap = $pdo->prepare('SELECT id_new FROM ' . qi($final) . '._mig_map_lineadeventa WHERE id_old=?');
        $lineMap->execute([(int)$row['lvdesde']]); $lvDesde = (int)($lineMap->fetchColumn() ?: 0);
        $lineMap->execute([(int)$row['lvhasta']]); $lvHasta = (int)($lineMap->fetchColumn() ?: 0);
        $newId = insertRowWithoutId($pdo, $new, $final, 'tickets', (int)$id, [
            'cliente' => $clienteFinal, 'lvdesde' => $lvDesde, 'lvhasta' => $lvHasta
        ]);
        $stMapTicket->execute([(int)$id, $newId]);
    }

    $asientoOffset = (int)$pdo->query('SELECT COALESCE(MAX(numasiento),0) FROM ' . qi($old) . '.librodiario')->fetchColumn();
    $tesoOffset = (int)$pdo->query('SELECT COALESCE(MAX(numerodeasiento),0) FROM ' . qi($old) . '.cccuentastesoreria')->fetchColumn();

    // Libro diario nuevo: desplazar numeración y remapear compasoc de tickets.
    $cols = commonColumns($pdo, $new, $final, 'librodiario');
    $cols = array_values(array_filter($cols, fn($c) => strtolower($c) !== 'id'));
    $select = [];
    foreach ($cols as $c) {
        if ($c === 'numasiento') $select[] = 'l.numasiento+' . $asientoOffset;
        elseif ($c === 'compasoc') $select[] = "CASE WHEN LOWER(l.tabla)='tickets' THEN COALESCE(mt.id_new,l.compasoc) ELSE l.compasoc END";
        else $select[] = 'l.' . qi($c);
    }
    $list = implode(',', array_map('qi', $cols));
    $pdo->exec('INSERT INTO ' . qi($final) . ".librodiario ($list) SELECT " . implode(',', $select) . ' FROM ' . qi($new) . '.librodiario l LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=l.compasoc');

    // Tesorería nueva: mismo desplazamiento y mapa de tickets.
    $cols = commonColumns($pdo, $new, $final, 'cccuentastesoreria');
    $cols = array_values(array_filter($cols, fn($c) => strtolower($c) !== 'id'));
    $select = [];
    foreach ($cols as $c) {
        if ($c === 'numerodeasiento') $select[] = 'c.numerodeasiento+' . $tesoOffset;
        elseif ($c === 'compasoc') $select[] = "CASE WHEN LOWER(c.tabla)='tickets' THEN COALESCE(mt.id_new,c.compasoc) ELSE c.compasoc END";
        else $select[] = 'c.' . qi($c);
    }
    $list = implode(',', array_map('qi', $cols));
    $pdo->exec('INSERT INTO ' . qi($final) . ".cccuentastesoreria ($list) SELECT " . implode(',', $select) . ' FROM ' . qi($new) . '.cccuentastesoreria c LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=c.compasoc');

    // Apertura sintética para vincular los movimientos de caja del sistema nuevo.
    $minFechaCaja = $pdo->query('SELECT MIN(FechaMov) FROM ' . qi($new) . '.cajaxusuario')->fetchColumn() ?: '2026-09-01';
    $saldoCaja = (float)$pdo->query('SELECT saldo FROM ' . qi($new) . '.cajas WHERE id=1')->fetchColumn();
    $cantCaja = (int)$pdo->query('SELECT COUNT(*) FROM ' . qi($new) . '.cajaxusuario WHERE idcaja=1')->fetchColumn();
    $st = $pdo->prepare('INSERT INTO ' . qi($final) . ".controlcajas (idcaja,estado,abiertael,cerradael,saldoinicial,saldofinal,usuario,cantidadmovimientos) VALUES (1,1,?,'0000-00-00 00:00:00',0,?,'admin',?)");
    $st->execute([$minFechaCaja . ' 00:00:00', $saldoCaja, $cantCaja]);
    $controlNuevo = (int)$pdo->lastInsertId();

    $newCashIds = $pdo->query('SELECT id FROM ' . qi($new) . '.cajaxusuario ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($newCashIds as $id) {
        insertRowWithoutId($pdo, $new, $final, 'cajaxusuario', (int)$id, ['idcontrolcaja' => $controlNuevo]);
    }

    // Tablas nuevas de contingencia y catálogo operativo.
    copyAll($pdo, $new, $final, 'auxticket', true);
    copyAll($pdo, $new, $final, 'auxlineadeventa', true);
    copyAll($pdo, $new, $final, 'movdecaja', true);
    copyAll($pdo, $new, $final, 'login', false);

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo json_encode([
        'success' => true,
        'database' => $final,
        'asiento_offset' => $asientoOffset,
        'tesoreria_offset' => $tesoOffset,
        'control_caja_nuevo' => $controlNuevo,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
