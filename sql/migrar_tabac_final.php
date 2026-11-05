<?php
declare(strict_types=1);

$pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$old = 'tabac_old';
$new = 'tabac_new';
$final = 'tabac_final';
$fechaLote = '2026-09-01';
$fechaInicioNuevo = '2026-09-02';

function qi(string $value): string {
    return '`' . str_replace('`', '``', $value) . '`';
}

function columns(PDO $pdo, string $db, string $table): array {
    $st = $pdo->prepare(
        "SELECT COLUMN_NAME
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND EXTRA NOT LIKE '%GENERATED%'
         ORDER BY ORDINAL_POSITION"
    );
    $st->execute([$db, $table]);
    return $st->fetchAll(PDO::FETCH_COLUMN);
}

function commonColumns(PDO $pdo, string $source, string $target, string $table): array {
    return array_values(array_intersect(columns($pdo, $source, $table), columns($pdo, $target, $table)));
}

function tableExists(PDO $pdo, string $db, string $table): bool {
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND TABLE_TYPE='BASE TABLE'"
    );
    $st->execute([$db, $table]);
    return (int)$st->fetchColumn() === 1;
}

function copyAll(PDO $pdo, string $source, string $target, string $table, bool $includeId = true): void {
    if (!tableExists($pdo, $source, $table) || !tableExists($pdo, $target, $table)) return;
    $cols = commonColumns($pdo, $source, $target, $table);
    if (!$includeId) {
        $cols = array_values(array_filter($cols, fn(string $c): bool => strtolower($c) !== 'id'));
    }
    if (!$cols) return;
    $list = implode(',', array_map('qi', $cols));
    $pdo->exec(
        'INSERT INTO ' . qi($target) . '.' . qi($table) . " ($list) " .
        'SELECT ' . $list . ' FROM ' . qi($source) . '.' . qi($table)
    );
}

function replaceFromNew(PDO $pdo, string $new, string $final, string $table): void {
    if (!tableExists($pdo, $new, $table) || !tableExists($pdo, $final, $table)) return;
    $pdo->exec('DELETE FROM ' . qi($final) . '.' . qi($table));
    copyAll($pdo, $new, $final, $table, true);
}

function createOffsetMap(PDO $pdo, string $source, string $final, string $table, string $map): int {
    $offset = (int)$pdo->query(
        'SELECT COALESCE(MAX(id),0) FROM ' . qi($final) . '.' . qi($table)
    )->fetchColumn();
    $pdo->exec(
        'CREATE TABLE ' . qi($final) . '.' . qi($map) . ' (' .
        'id_old BIGINT PRIMARY KEY,id_new BIGINT NOT NULL UNIQUE KEY)'
    );
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '.' . qi($map) . ' (id_old,id_new) ' .
        'SELECT id,id+' . $offset . ' FROM ' . qi($source) . '.' . qi($table)
    );
    return $offset;
}

function appendMapped(
    PDO $pdo,
    string $source,
    string $final,
    string $table,
    string $map,
    array $overrides = [],
    string $joins = ''
): void {
    $cols = commonColumns($pdo, $source, $final, $table);
    $select = [];
    foreach ($cols as $col) {
        if (strtolower($col) === 'id') $select[] = 'map.id_new';
        elseif (array_key_exists($col, $overrides)) $select[] = $overrides[$col];
        else $select[] = 's.' . qi($col);
    }
    $list = implode(',', array_map('qi', $cols));
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '.' . qi($table) . " ($list) SELECT " .
        implode(',', $select) . ' FROM ' . qi($source) . '.' . qi($table) . ' s ' .
        'JOIN ' . qi($final) . '.' . qi($map) . ' map ON map.id_old=s.id ' . $joins
    );
}

function moveLegacyDate(
    PDO $pdo,
    string $final,
    string $table,
    string $column,
    string $targetDate,
    bool $keepTime = false
): void {
    if (!tableExists($pdo, $final, $table)) return;
    $col = qi($column);
    $newExpr = $keepTime
        ? "TIMESTAMP(" . $pdo->quote($targetDate) . ",TIME(d.$col))"
        : $pdo->quote($targetDate);
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '._mig_fechas_legacy ' .
        '(tabla,id_registro,columna,fecha_original,fecha_nueva) ' .
        'SELECT ' . $pdo->quote($table) . ',d.id,' . $pdo->quote($column) .
        ",d.$col,$newExpr FROM " . qi($final) . '.' . qi($table) . " d " .
        "WHERE DATE(d.$col) IN ('2026-09-03','2026-09-04')"
    );
    $pdo->exec(
        'UPDATE ' . qi($final) . '.' . qi($table) . " d SET d.$col=$newExpr " .
        "WHERE DATE(d.$col) IN ('2026-09-03','2026-09-04')"
    );
}

function remapProductCode(
    PDO $pdo,
    string $final,
    string $table,
    string $column,
    array $preserveColumns = []
): void {
    if (!tableExists($pdo, $final, $table)) return;
    $sets = ['d.' . qi($column) . '=m.codigo_new'];
    foreach ($preserveColumns as $col) $sets[] = 'd.' . qi($col) . '=d.' . qi($col);
    $pdo->exec(
        'UPDATE ' . qi($final) . '.' . qi($table) . ' d ' .
        'JOIN ' . qi($final) . '._mig_map_productos m ON BINARY m.codigo_old=BINARY d.' . qi($column) . ' ' .
        'SET ' . implode(',', $sets) . ' WHERE BINARY m.codigo_old<>BINARY m.codigo_new'
    );
}

if ((int)$pdo->query(
    "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME=" . $pdo->quote($final)
)->fetchColumn() > 0) {
    throw new RuntimeException("La base $final ya existe; no se modificó nada.");
}

$pdo->exec("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'");
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$pdo->exec('CREATE DATABASE ' . qi($final) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
$pdo->exec('USE ' . qi($final));

try {
    // El esquema de destino siempre es el vigente.
    $tables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=" . $pdo->quote($new) . " AND TABLE_TYPE='BASE TABLE'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec('CREATE TABLE ' . qi($final) . '.' . qi($table) . ' LIKE ' . qi($new) . '.' . qi($table));
    }

    // Historia legacy completa, limitada a las tablas que siguen existiendo.
    $oldTables = $pdo->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=" . $pdo->quote($old) . " AND TABLE_TYPE='BASE TABLE'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($oldTables as $table) {
        if (in_array($table, $tables, true)) copyAll($pdo, $old, $final, $table, true);
    }

    // Auditoría de todos los cambios de fecha operativa del lote cargado tarde.
    $pdo->exec(
        'CREATE TABLE ' . qi($final) . '._mig_fechas_legacy (' .
        'tabla VARCHAR(100) NOT NULL,id_registro BIGINT NOT NULL,columna VARCHAR(100) NOT NULL,' .
        'fecha_original DATETIME NOT NULL,fecha_nueva DATETIME NOT NULL,' .
        'PRIMARY KEY(tabla,id_registro,columna))'
    );
    $dateMoves = [
        ['caja', 'FechaMov', false],
        ['cajaxusuario', 'FechaMov', false],
        ['ccbancos', 'fecha', false],
        ['cccuentastesoreria', 'fecha', false],
        ['ccvendedores', 'fecha', true],
        ['controlcajas', 'abiertael', true],
        ['controlcajas', 'cerradael', true],
        ['controlstock', 'fecha', true],
        ['ingresosdestock', 'fecha', false],
        ['librodiario', 'fecha', false],
        ['lineaconceptoscompra', 'fecha', false],
        ['lineadeventa', 'fecha', false],
        ['movimientosdestock', 'fecha', true],
        ['pagoaproveedores', 'fecha', false],
        ['tickets', 'fecha', false],
        ['tickets', 'fecharemito', false],
    ];
    foreach ($dateMoves as [$table, $column, $keepTime]) {
        moveLegacyDate($pdo, $final, $table, $column, $fechaLote, $keepTime);
    }

    // Maestros y configuración vigentes, tomados de tabac_new.
    $masters = [
        'accesosusuarios', 'afipconfig', 'alb_usuarios', 'asientosguardados',
        'bancos', 'cajas', 'conceptosgastos', 'configuracion', 'confrubros',
        'cuentaspredeterminadas', 'cuentaspropias', 'cuentastesoreria',
        'datosempresa', 'depositos', 'ejercicios', 'ejperidodos', 'familias',
        'fotosproductos', 'grupoajustes', 'libro', 'listas', 'marcas',
        'mediosdepago', 'monedas', 'nivelesdeacceso', 'permisosespeciales',
        'plandecuentas', 'proveedores', 'ramasclientes', 'rubros', 'sucursales',
        'tarjetas', 'tipomovcuenta', 'tiposcomprobantes', 'tiposdecambio',
        'transportes', 'unidadesdenegocio', 'usuarios_depositos', 'vendedores'
    ];
    foreach ($masters as $table) replaceFromNew($pdo, $new, $final, $table);

    // Clientes: manda el maestro nuevo, más las altas legacy posteriores a la
    // exportación inicial (IDs 1811-1813), que no colisionan con tabac_new.
    replaceFromNew($pdo, $new, $final, 'alb_clientes');
    $clientCols = commonColumns($pdo, $old, $final, 'alb_clientes');
    $clientList = implode(',', array_map('qi', $clientCols));
    $pdo->exec(
        'INSERT INTO ' . qi($final) . ".alb_clientes ($clientList) " .
        "SELECT $clientList FROM " . qi($old) . '.alb_clientes o ' .
        'WHERE NOT EXISTS (SELECT 1 FROM ' . qi($new) . '.alb_clientes n WHERE n.id=o.id)'
    );

    // Productos: manda tabac_new. Los productos creados solo en legacy se agregan
    // con IDs nuevos. La identidad se resuelve por código normalizado, nunca por ID.
    replaceFromNew($pdo, $new, $final, 'alb_productos');
    $productCols = commonColumns($pdo, $old, $final, 'alb_productos');
    $productColsNoId = array_values(array_filter($productCols, fn(string $c): bool => strtolower($c) !== 'id'));
    $productList = implode(',', array_map('qi', $productColsNoId));
    $productSelect = implode(',', array_map(fn(string $c): string => 'o.' . qi($c), $productColsNoId));
    $normOld = "CASE WHEN o.codigo REGEXP '^[0-9]+$' THEN CAST(o.codigo AS UNSIGNED) ELSE UPPER(TRIM(o.codigo)) END";
    $normNew = "CASE WHEN n.codigo REGEXP '^[0-9]+$' THEN CAST(n.codigo AS UNSIGNED) ELSE UPPER(TRIM(n.codigo)) END";
    $pdo->exec(
        'INSERT INTO ' . qi($final) . ".alb_productos ($productList) SELECT $productSelect " .
        'FROM ' . qi($old) . '.alb_productos o LEFT JOIN ' . qi($new) . ".alb_productos n ON $normOld=$normNew " .
        'WHERE n.id IS NULL'
    );

    $pdo->exec(
        'CREATE TABLE ' . qi($final) . '._mig_map_productos (' .
        'id_old INT PRIMARY KEY,id_new INT NOT NULL UNIQUE KEY,' .
        'codigo_old VARCHAR(200) NOT NULL,codigo_new VARCHAR(200) NOT NULL,' .
        'KEY idx_codigo_old(codigo_old),KEY idx_codigo_new(codigo_new))'
    );
    $normFinal = "CASE WHEN f.codigo REGEXP '^[0-9]+$' THEN CAST(f.codigo AS UNSIGNED) ELSE UPPER(TRIM(f.codigo)) END";
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '._mig_map_productos (id_old,id_new,codigo_old,codigo_new) ' .
        'SELECT o.id,f.id,o.codigo,f.codigo FROM ' . qi($old) . ".alb_productos o " .
        'JOIN ' . qi($final) . ".alb_productos f ON $normOld=$normFinal"
    );

    // Referencias: catálogo nuevo más las referencias de los nueve productos que
    // solo existían en legacy.
    replaceFromNew($pdo, $new, $final, 'referenciasproductos');
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '.referenciasproductos (codigo,refierea) ' .
        'SELECT DISTINCT r.codigo,m.codigo_new FROM ' . qi($old) . '.referenciasproductos r ' .
        'JOIN ' . qi($final) . '._mig_map_productos m ON BINARY m.codigo_old=BINARY r.refierea ' .
        'LEFT JOIN ' . qi($new) . '.alb_productos n ON n.id=m.id_new ' .
        'WHERE n.id IS NULL AND NOT EXISTS (' .
        'SELECT 1 FROM ' . qi($final) . '.referenciasproductos f ' .
        'WHERE BINARY f.codigo=BINARY r.codigo AND BINARY f.refierea=BINARY m.codigo_new)'
    );

    // Las tablas históricas deben apuntar a los códigos vigentes.
    $productRefs = [
        ['controlstock', 'codprod', ['fecha']],
        ['historicoproductos', 'codigo', ['timestamp']],
        ['ingresosdestock', 'codigo', ['fechacarga']],
        ['lineadepedido', 'prodcod', []],
        ['lineaderemitos', 'codprod', ['timestamp']],
        ['lineadeventa', 'prodcod', ['timestamp']],
        ['lineadevoluciones', 'prodcod', ['timestamp']],
        ['lineajustes', 'codigo', []],
        ['lineapedidosclientes', 'producto', ['fecha']],
        ['lineapresupuestosclientes', 'producto', ['fecha']],
        ['lineasremitossucursales', 'codprod', []],
        ['modificacionesproductos', 'codigo', ['timestamp']],
        ['movimientosdestock', 'codigo', []],
        ['promociones', 'codigo_prod', []],
        ['promociones_ventas', 'codigo_prod', []],
        ['respaldoproductos', 'codigo', ['timestamp']],
        ['saldosiniciales', 'codigo', []],
        ['telas', 'codigo', []],
    ];
    foreach ($productRefs as [$table, $column, $preserve]) {
        remapProductCode($pdo, $final, $table, $column, $preserve);
    }

    // Cerrar cualquier control legacy todavía activo. Los saldos físicos y el
    // estado vigente de las cajas provienen exclusivamente de tabac_new.
    $pdo->exec(
        'UPDATE ' . qi($final) . '.controlcajas c JOIN ' . qi($old) . '.controlcajas o ON o.id=c.id ' .
        "SET c.estado=0,c.cerradael=CASE WHEN c.cerradael IS NULL OR c.cerradael='0000-00-00 00:00:00' " .
        "THEN '2026-09-01 23:59:59' ELSE c.cerradael END,c.usuario_cierre=COALESCE(NULLIF(c.usuario_cierre,''),'MIGRACION') " .
        'WHERE o.estado=1'
    );

    // Mapas de IDs del sistema nuevo.
    createOffsetMap($pdo, $new, $final, 'lineadeventa', '_mig_map_lineadeventa');
    createOffsetMap($pdo, $new, $final, 'tickets', '_mig_map_tickets');
    createOffsetMap($pdo, $new, $final, 'facturasdecompra', '_mig_map_facturasdecompra');
    createOffsetMap($pdo, $new, $final, 'pagoaproveedores', '_mig_map_pagoaproveedores');
    createOffsetMap($pdo, $new, $final, 'pagos', '_mig_map_pagos');
    createOffsetMap($pdo, $new, $final, 'controlcajas', '_mig_map_controlcajas');
    createOffsetMap($pdo, $new, $final, 'cajaxusuario', '_mig_map_cajaxusuario');

    appendMapped($pdo, $new, $final, 'lineadeventa', '_mig_map_lineadeventa');
    appendMapped(
        $pdo, $new, $final, 'tickets', '_mig_map_tickets',
        [
            'lvdesde' => 'COALESCE(lvd.id_new,0)',
            'lvhasta' => 'COALESCE(lvh.id_new,0)',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_lineadeventa lvd ON lvd.id_old=s.lvdesde ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_lineadeventa lvh ON lvh.id_old=s.lvhasta'
    );

    appendMapped($pdo, $new, $final, 'facturasdecompra', '_mig_map_facturasdecompra');
    $pagoNumOffset = (int)$pdo->query(
        'SELECT COALESCE(MAX(numcomp),0) FROM ' . qi($old) . '.pagoaproveedores'
    )->fetchColumn();
    appendMapped(
        $pdo, $new, $final, 'pagoaproveedores', '_mig_map_pagoaproveedores',
        ['numcomp' => 's.numcomp+' . $pagoNumOffset]
    );
    appendMapped(
        $pdo, $new, $final, 'pagos', '_mig_map_pagos',
        ['idfactura' => 'CASE WHEN s.idfactura=0 THEN 0 ELSE COALESCE(mt.id_new,s.idfactura) END'],
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.idfactura'
    );
    appendMapped(
        $pdo, $new, $final, 'controlcajas', '_mig_map_controlcajas',
        [
            'idanterior' => 'COALESCE(ca.id_new,0)',
            'ultimoid' => 'cu.id_new',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_cajaxusuario ca ON ca.id_old=s.idanterior ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_cajaxusuario cu ON cu.id_old=s.ultimoid'
    );
    appendMapped(
        $pdo, $new, $final, 'cajaxusuario', '_mig_map_cajaxusuario',
        ['idcontrolcaja' => 'COALESCE(mc.id_new,s.idcontrolcaja)'],
        'LEFT JOIN ' . qi($final) . '._mig_map_controlcajas mc ON mc.id_old=s.idcontrolcaja'
    );

    // Hijos de compras, pagos y cobros.
    createOffsetMap($pdo, $new, $final, 'lineaconceptoscompra', '_mig_map_lineaconceptoscompra');
    appendMapped(
        $pdo, $new, $final, 'lineaconceptoscompra', '_mig_map_lineaconceptoscompra',
        ['idfactura' => 'COALESCE(mf.id_new,s.idfactura)'],
        'LEFT JOIN ' . qi($final) . '._mig_map_facturasdecompra mf ON mf.id_old=s.idfactura'
    );
    createOffsetMap($pdo, $new, $final, 'pagosfacturasproveedores', '_mig_map_pagosfacturasproveedores');
    appendMapped(
        $pdo, $new, $final, 'pagosfacturasproveedores', '_mig_map_pagosfacturasproveedores',
        [
            'idpago' => 'COALESCE(mp.id_new,s.idpago)',
            'idfactura' => 'COALESCE(mf.id_new,s.idfactura)',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_pagoaproveedores mp ON mp.id_old=s.idpago ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_facturasdecompra mf ON mf.id_old=s.idfactura'
    );
    createOffsetMap($pdo, $new, $final, 'cobrosfacturasclientes', '_mig_map_cobrosfacturasclientes');
    appendMapped(
        $pdo, $new, $final, 'cobrosfacturasclientes', '_mig_map_cobrosfacturasclientes',
        [
            'idpago' => 'COALESCE(mp.id_new,s.idpago)',
            'idfactura' => 'COALESCE(mt.id_new,s.idfactura)',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_pagos mp ON mp.id_old=s.idpago ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.idfactura'
    );

    // Cuentas corrientes y bancos.
    createOffsetMap($pdo, $new, $final, 'ccclientes', '_mig_map_ccclientes');
    appendMapped(
        $pdo, $new, $final, 'ccclientes', '_mig_map_ccclientes',
        ['compasoc' => "CASE WHEN LOWER(s.tabla)='tickets' THEN COALESCE(mt.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagos' THEN COALESCE(mp.id_new,s.compasoc) ELSE s.compasoc END"],
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagos mp ON mp.id_old=s.compasoc'
    );
    createOffsetMap($pdo, $new, $final, 'moviemientosccproveedores', '_mig_map_movccproveedores');
    appendMapped(
        $pdo, $new, $final, 'moviemientosccproveedores', '_mig_map_movccproveedores',
        [
            'compasoc' => "CASE WHEN LOWER(s.tabla)='facturasdecompra' THEN COALESCE(mf.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagoaproveedores' THEN COALESCE(mp.id_new,s.compasoc) ELSE s.compasoc END",
            'idpago' => 'CASE WHEN s.idpago=0 THEN 0 ELSE COALESCE(mp.id_new,s.idpago) END',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_facturasdecompra mf ON mf.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagoaproveedores mp ON mp.id_old=CASE WHEN s.idpago<>0 THEN s.idpago ELSE s.compasoc END'
    );
    createOffsetMap($pdo, $new, $final, 'ccbancos', '_mig_map_ccbancos');
    appendMapped(
        $pdo, $new, $final, 'ccbancos', '_mig_map_ccbancos',
        ['compasoc' => "CASE WHEN LOWER(s.tabla)='tickets' THEN COALESCE(mt.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagos' THEN COALESCE(mc.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagosprov' THEN COALESCE(mp.id_new,s.compasoc) ELSE s.compasoc END"],
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagos mc ON mc.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagoaproveedores mp ON mp.id_old=s.compasoc'
    );

    // Neutralización técnica de saldos históricos solamente para efectivo de
    // usuarios y Caja Central. Así el saldo vigente coincide con tabac_new.
    $tesoOldMaxNum = (int)$pdo->query(
        'SELECT COALESCE(MAX(numerodeasiento),0) FROM ' . qi($final) . '.cccuentastesoreria'
    )->fetchColumn();
    $pdo->exec(
        'INSERT INTO ' . qi($final) . '.cccuentastesoreria ' .
        '(idcuenta,debe,haber,tabla,usuario,usuariocaja,fecha,descripcion,fiscal,compasoc,numerodeasiento) ' .
        'SELECT q.id,CASE WHEN q.saldo<0 THEN -q.saldo ELSE 0 END,' .
        'CASE WHEN q.saldo>0 THEN q.saldo ELSE 0 END,' .
        "'migracion_saldo_corte','MIGRACION','MIGRACION'," . $pdo->quote($fechaInicioNuevo) . ',' .
        "CONCAT('Neutralización saldo legacy para conservar saldo físico de tabac_new: ',q.medio),0,0," .
        $tesoOldMaxNum . '+ROW_NUMBER() OVER (ORDER BY q.id) FROM (' .
        'SELECT m.id,m.medio,ROUND(SUM(t.debe-t.haber),2) saldo ' .
        'FROM ' . qi($final) . '.mediosdepago m JOIN ' . qi($final) . '.cccuentastesoreria t ON t.idcuenta=m.id ' .
        "WHERE m.tipodecuenta IN ('Caja de Usuarios','Caja Central') GROUP BY m.id,m.medio " .
        'HAVING ABS(SUM(t.debe-t.haber))>=0.005) q'
    );

    createOffsetMap($pdo, $new, $final, 'cccuentastesoreria', '_mig_map_cccuentastesoreria');
    $tesoNumOffset = (int)$pdo->query(
        'SELECT COALESCE(MAX(numerodeasiento),0) FROM ' . qi($final) . '.cccuentastesoreria'
    )->fetchColumn();
    appendMapped(
        $pdo, $new, $final, 'cccuentastesoreria', '_mig_map_cccuentastesoreria',
        [
            'compasoc' => "CASE WHEN LOWER(s.tabla)='tickets' THEN COALESCE(mt.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagos' THEN COALESCE(mc.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagoaproveedores' THEN COALESCE(mp.id_new,s.compasoc) ELSE s.compasoc END",
            'numerodeasiento' => 's.numerodeasiento+' . $tesoNumOffset,
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagos mc ON mc.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagoaproveedores mp ON mp.id_old=s.compasoc'
    );

    // Libro diario con numeración continua y referencias remapeadas.
    createOffsetMap($pdo, $new, $final, 'librodiario', '_mig_map_librodiario');
    $asientoOffset = (int)$pdo->query(
        'SELECT COALESCE(MAX(numasiento),0) FROM ' . qi($old) . '.librodiario'
    )->fetchColumn();
    appendMapped(
        $pdo, $new, $final, 'librodiario', '_mig_map_librodiario',
        [
            'numasiento' => 's.numasiento+' . $asientoOffset,
            'compasoc' => "CASE WHEN LOWER(s.tabla)='tickets' THEN COALESCE(mt.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagos' THEN COALESCE(mc.id_new,s.compasoc) WHEN LOWER(s.tabla)='pagoaproveedores' THEN COALESCE(mp.id_new,s.compasoc) WHEN LOWER(s.tabla)='facturasdecompra' THEN COALESCE(mf.id_new,s.compasoc) WHEN LOWER(s.tabla)='controlcajas' THEN COALESCE(mx.id_new,s.compasoc) ELSE s.compasoc END",
        ],
        'LEFT JOIN ' . qi($final) . '._mig_map_tickets mt ON mt.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagos mc ON mc.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_pagoaproveedores mp ON mp.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_facturasdecompra mf ON mf.id_old=s.compasoc ' .
        'LEFT JOIN ' . qi($final) . '._mig_map_controlcajas mx ON mx.id_old=s.compasoc'
    );

    // Stock: el histórico legacy es la base y tabac_new aporta la variación desde cero.
    $pdo->exec(
        'CREATE TABLE ' . qi($final) . '._mig_stock_base AS ' .
        'SELECT m.id_new id_producto,m.codigo_new codigo,CAST(o.Stock AS DECIMAL(18,2)) stock_base ' .
        'FROM ' . qi($final) . '._mig_map_productos m JOIN ' . qi($old) . '.alb_productos o ON o.id=m.id_old'
    );
    $pdo->exec('ALTER TABLE ' . qi($final) . '._mig_stock_base ADD PRIMARY KEY(id_producto),ADD UNIQUE KEY(codigo)');
    $pdo->exec(
        'UPDATE ' . qi($final) . '.alb_productos p JOIN ' . qi($final) . '._mig_stock_base b ON b.id_producto=p.id ' .
        'LEFT JOIN ' . qi($new) . '.alb_productos n ON n.id=p.id ' .
        'SET p.Stock=ROUND(b.stock_base+COALESCE(n.Stock,0),2),p.timestamp=p.timestamp'
    );

    createOffsetMap($pdo, $new, $final, 'controlstock', '_mig_map_controlstock');
    appendMapped(
        $pdo, $new, $final, 'controlstock', '_mig_map_controlstock',
        [
            'inicial' => 'ROUND(s.inicial+COALESCE(b.stock_base,0),2)',
            'final' => 'ROUND(s.final+COALESCE(b.stock_base,0),2)',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_stock_base b ON BINARY b.codigo=BINARY s.codprod'
    );
    createOffsetMap($pdo, $new, $final, 'ingresosdestock', '_mig_map_ingresosdestock');
    appendMapped(
        $pdo, $new, $final, 'ingresosdestock', '_mig_map_ingresosdestock',
        [
            'stkant' => 'ROUND(s.stkant+COALESCE(b.stock_base,0),2)',
            'stkfinal' => 'ROUND(s.stkfinal+COALESCE(b.stock_base,0),2)',
            'stock' => 'ROUND(s.stock+COALESCE(b.stock_base,0),2)',
        ],
        'LEFT JOIN ' . qi($final) . '._mig_stock_base b ON BINARY b.codigo=BINARY s.codigo'
    );
    createOffsetMap($pdo, $new, $final, 'historicoproductos', '_mig_map_historicoproductos');
    appendMapped(
        $pdo, $new, $final, 'historicoproductos', '_mig_map_historicoproductos',
        ['Stock' => 'ROUND(s.Stock+COALESCE(b.stock_base,0),2)'],
        'LEFT JOIN ' . qi($final) . '._mig_stock_base b ON BINARY b.codigo=BINARY s.codigo'
    );
    createOffsetMap($pdo, $new, $final, 'movimientosdestock', '_mig_map_movimientosdestock');
    appendMapped($pdo, $new, $final, 'movimientosdestock', '_mig_map_movimientosdestock');

    // Log de sesiones del sistema nuevo.
    createOffsetMap($pdo, $new, $final, 'login', '_mig_map_login');
    appendMapped($pdo, $new, $final, 'login', '_mig_map_login');

    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    echo json_encode([
        'success' => true,
        'database' => $final,
        'fecha_lote_legacy' => $fechaLote,
        'fecha_inicio_nuevo' => $fechaInicioNuevo,
        'asiento_offset' => $asientoOffset,
        'tesoreria_offset' => $tesoNumOffset,
        'pago_proveedor_numcomp_offset' => $pagoNumOffset,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
} catch (Throwable $e) {
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
