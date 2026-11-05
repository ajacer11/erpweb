<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // solo consola
/**
 * Herramientas comunes de la migración legacy -> sistema nuevo (octubre 2026).
 * Todo corre contra el MySQL/MariaDB local; las bases de origen (src_*) no se modifican.
 */
date_default_timezone_set('America/Argentina/Buenos_Aires');

const ADMIN_SQLS = 'C:/xampp/htdocs/admin/sqls';
const SISTEMA_SQL = 'C:/xampp/htdocs/sistema/sql';
const MYSQL_BIN = 'C:/xampp/mysql/bin/mysql.exe';

function conectar(?string $db = null): PDO
{
    $pdo = new PDO('mysql:host=127.0.0.1;charset=utf8mb4' . ($db ? ";dbname=$db" : ''), 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
    $pdo->exec("SET SESSION sql_mode = 'NO_ENGINE_SUBSTITUTION'");
    return $pdo;
}

function logm(string $m): void { echo '[' . date('H:i:s') . "] $m\n"; flush(); }

/** Corre un .sql con el cliente mysql (respeta DELIMITER). Devuelve la salida. */
function correrArchivoSql(string $db, string $archivo, bool $forzar = true): string
{
    $cmd = '"' . MYSQL_BIN . '" -uroot --default-character-set=utf8mb4 ' . ($forzar ? '--force ' : '') . escapeshellarg($db) . ' < ' . escapeshellarg($archivo) . ' 2>&1';
    return (string) shell_exec($cmd);
}

/**
 * Copia una base local a otra pasando por un archivo (no por una tubería: con --routines,
 * mysqldump bloquea mysql.proc y el DROP PROCEDURE del lado que importa queda esperando).
 */
function copiarBase(string $origen, string $destino): string
{
    $tmp = 'C:/mig_legacy/_copia_' . $origen . '.sql';
    $err = trim((string) shell_exec('"C:/xampp/mysql/bin/mysqldump.exe" -uroot --routines --triggers --default-character-set=utf8mb4 ' . escapeshellarg($origen) . ' > ' . escapeshellarg($tmp) . ' 2>&1'));
    if ($err !== '' && !is_file($tmp)) return "dump: $err";
    $r = correrArchivoSql($destino, $tmp, false);
    @unlink($tmp);
    return trim($r) === '' ? 'ok' : trim($r);
}

/** Igual que applySchemaUpdates() del backoffice: cada consulta entre comillas, ignorando errores. */
function aplicarActualizacionesBackoffice(PDO $pdo): array
{
    preg_match_all('/"(.*?)"/s', file_get_contents(ADMIN_SQLS . '/actualizaciones.sql'), $m);
    $ok = 0; $err = 0;
    foreach ($m[1] as $sql) {
        $sql = trim($sql);
        if ($sql === '') continue;
        try { $pdo->exec($sql); $ok++; } catch (Throwable $e) { $err++; }
    }
    return [$ok, $err];
}

function tablas(PDO $pdo, string $db): array
{
    $st = $pdo->prepare("SELECT table_name FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'");
    $st->execute([$db]);
    return array_map('strtolower', $st->fetchAll(PDO::FETCH_COLUMN));
}

function columnas(PDO $pdo, string $db, string $tabla): array
{
    $st = $pdo->prepare("SELECT column_name, column_type, is_nullable, column_default, extra FROM information_schema.columns WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position");
    $st->execute([$db, $tabla]);
    $out = [];
    foreach ($st->fetchAll() as $c) $out[strtolower($c['column_name'])] = $c;
    return $out;
}

/** Definición de columna para ALTER ADD (default y nulabilidad como en la referencia). */
function defColumna(array $c): string
{
    $def = $c['column_type'];
    $extra = strtolower((string) $c['extra']);
    if (str_contains($extra, 'auto_increment')) return $def . ' NOT NULL'; // no se agrega AI a una tabla existente
    $nullable = $c['is_nullable'] === 'YES';
    $default = $c['column_default'];
    if ($default === null || strtoupper((string) $default) === 'NULL') {
        if ($nullable) return "$def NULL DEFAULT NULL";
        // NOT NULL sin default: se agrega con un default neutro para no romper las filas existentes
        $t = strtolower($c['column_type']);
        if (preg_match('/int|decimal|double|float|bit|year/', $t)) return "$def NOT NULL DEFAULT 0";
        if (str_starts_with($t, 'date') || str_starts_with($t, 'timestamp')) return "$def NULL DEFAULT NULL";
        if (preg_match('/text|blob|json/', $t)) return "$def NULL";
        return "$def NOT NULL DEFAULT ''";
    }
    // column_default de MariaDB ya viene como expresión (comillas incluidas para strings)
    $d = (string) $default;
    if (preg_match('/^current_timestamp/i', $d)) return "$def " . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT $d" . (str_contains($extra, 'on update') ? ' ON UPDATE current_timestamp()' : '');
    return "$def " . ($nullable ? 'NULL' : 'NOT NULL') . " DEFAULT $d";
}

/**
 * Completa la base destino con lo que tenga la referencia: tablas faltantes (CREATE de la
 * referencia) y columnas faltantes (ALTER ADD). No borra ni modifica nada existente.
 */
function completarEsquema(PDO $pdo, string $destino, string $ref): array
{
    $creadas = []; $agregadas = []; $errores = [];
    $tDest = tablas($pdo, $destino);
    foreach (tablas($pdo, $ref) as $t) {
        if (str_starts_with($t, '_mig')) continue;
        if (!in_array($t, $tDest, true)) {
            $create = $pdo->query("SHOW CREATE TABLE `$ref`.`$t`")->fetch(PDO::FETCH_NUM)[1];
            $create = preg_replace('/AUTO_INCREMENT=\d+\s*/', '', $create);
            try { $pdo->exec("USE `$destino`"); $pdo->exec($create); $creadas[] = $t; }
            catch (Throwable $e) { $errores[] = "CREATE $t: " . $e->getMessage(); }
            continue;
        }
        $cDest = columnas($pdo, $destino, $t);
        foreach (columnas($pdo, $ref, $t) as $nombre => $c) {
            if (isset($cDest[$nombre])) continue;
            $sql = "ALTER TABLE `$destino`.`$t` ADD COLUMN `{$c['column_name']}` " . defColumna($c);
            try { $pdo->exec($sql); $agregadas[] = "$t.{$c['column_name']}"; }
            catch (Throwable $e) { $errores[] = "$sql -> " . $e->getMessage(); }
        }
    }
    return [$creadas, $agregadas, $errores];
}

/** Diferencias que quedan entre destino y referencia (para el informe). */
function diferencias(PDO $pdo, string $destino, string $ref): array
{
    $faltan = [];
    $tDest = tablas($pdo, $destino);
    foreach (tablas($pdo, $ref) as $t) {
        if (str_starts_with($t, '_mig')) continue;
        if (!in_array($t, $tDest, true)) { $faltan[] = "tabla $t"; continue; }
        $cDest = columnas($pdo, $destino, $t);
        foreach (columnas($pdo, $ref, $t) as $n => $c) if (!isset($cDest[$n])) $faltan[] = "$t.$n";
    }
    return $faltan;
}

function contar(PDO $pdo, string $db, string $t): int
{
    try { return (int) $pdo->query("SELECT COUNT(*) FROM `$db`.`$t`")->fetchColumn(); } catch (Throwable $e) { return -1; }
}

/** Copia todas las filas de una tabla de la referencia si la tabla destino está vacía. */
function sembrarSiVacia(PDO $pdo, string $destino, string $origen, string $t): int
{
    if (contar($pdo, $destino, $t) !== 0) return 0;
    $cO = array_keys(columnas($pdo, $origen, $t));
    $cD = array_keys(columnas($pdo, $destino, $t));
    $comunes = array_values(array_intersect($cO, $cD));
    if (!$comunes) return 0;
    $lista = '`' . implode('`,`', $comunes) . '`';
    return $pdo->exec("INSERT INTO `$destino`.`$t` ($lista) SELECT $lista FROM `$origen`.`$t`");
}

/** Pone los AUTO_INCREMENT por encima del máximo id (después de inserts con id explícito). */
function ajustarAutoIncrement(PDO $pdo, string $db): void
{
    $st = $pdo->prepare("SELECT table_name, column_name FROM information_schema.columns WHERE table_schema = ? AND extra LIKE '%auto_increment%'");
    $st->execute([$db]);
    foreach ($st->fetchAll() as $r) {
        $max = (int) $pdo->query("SELECT COALESCE(MAX(`{$r['column_name']}`), 0) FROM `$db`.`{$r['table_name']}`")->fetchColumn();
        $pdo->exec("ALTER TABLE `$db`.`{$r['table_name']}` AUTO_INCREMENT = " . ($max + 1));
    }
}

/** passweb = bcrypt de la contraseña legacy (texto plano en `password`) para quien no tenga. */
function hashearContrasenas(PDO $pdo, string $db): int
{
    $n = 0;
    $up = $pdo->prepare("UPDATE `$db`.alb_usuarios SET passweb = ? WHERE id = ?");
    foreach ($pdo->query("SELECT id, password, passweb FROM `$db`.alb_usuarios")->fetchAll() as $u) {
        $pw = (string) $u['password'];
        if (($u['passweb'] ?? '') !== '' && str_starts_with((string) $u['passweb'], '$2y$')) continue;
        if ($pw === '' || $pw === '-') continue;
        $up->execute([password_hash($pw, PASSWORD_DEFAULT), $u['id']]);
        $n++;
    }
    return $n;
}

/** Aplica, en orden, los scripts de esquema de sistema/sql que son idempotentes y generales. */
function aplicarMigracionesSistema(string $db): array
{
    $scripts = [
        '2026-08-29_ampliar_descripcion_tesoreria.sql', '2026-08-29_cheques_rechazados_cuenta.sql',
        '2026-08-29_comisiones_vendedores_cuenta.sql', '2026-08-29_configuracion_mostrar_vendedor.sql',
        '2026-08-31_controlcajas_usuario_cierre.sql', '2026-09-02_auxticket_auxlineadeventa.sql',
        '2026-09-03_datos_empresa_punto_venta.sql', '2026-09-07_api_config_productos_externa.sql',
        '2026-09-07_remitos_mercaderia.sql', 'permisos_cambio_descripcion.sql', 'migrarsqldepositos.sql',
        'migrarsqldeposito_transito.sql', 'migrarsqldeposito_transito2.sql', 'migrarsqldeposito_transito3.sql',
        'migra31082026.sql', 'migrar_datos_fiscales_depositos.sql',
        '2026-10-07_tickets_importectacte.sql', '2026-10-08_configuracion_notas_debito.sql',
        '2026-10-14_modificar_medios_cobro.sql', '2026-10-15_chequesterceros_columnas_faltantes.sql',
        '2026-10-15_tarjetas_cupones.sql', '2026-10-15_conciliacion_bancaria.sql', '2026-10-15_retenciones_percepciones.sql',
    ];
    $out = [];
    foreach ($scripts as $s) {
        $f = SISTEMA_SQL . '/' . $s;
        if (!is_file($f)) { $out[$s] = 'no existe'; continue; }
        $r = trim(correrArchivoSql($db, $f));
        $out[$s] = $r === '' ? 'ok' : mb_substr(preg_replace('/\s+/', ' ', $r), 0, 300);
    }
    return $out;
}

/**
 * Alinea columnas obligatorias contra la referencia (estructura de westit_sofi en el VPS, cargada como ref_sofi).
 * El legacy y baseenblanco traen columnas NOT NULL sin default que el sistema nuevo no completa; con el
 * sql_mode estricto del VPS esos INSERT fallan (ej. controlcajas.iplocal al abrir caja, alb_productos.cuentaventas).
 * Columnas que existen en la referencia: toman su nulabilidad/default. Solo legacy: NULL, o 0 si son numéricas.
 */
function alinearObligatorias(PDO $pdo, string $destino, string $ref = 'ref_sofi'): int
{
    $pdo->exec('SET SESSION group_concat_max_len = 1000000');
    $sql = "SELECT CONCAT('ALTER TABLE `', x.t, '` ', GROUP_CONCAT(x.def ORDER BY x.c SEPARATOR ', ')) FROM (
        SELECT m.TABLE_NAME t, m.COLUMN_NAME c,
               CONCAT('MODIFY `', m.COLUMN_NAME, '` ', m.COLUMN_TYPE,
                      IF(m.CHARACTER_SET_NAME IS NULL, '', CONCAT(' CHARACTER SET ', m.CHARACTER_SET_NAME, ' COLLATE ', m.COLLATION_NAME)),
                      IF(r.COLUMN_NAME IS NULL,
                         IF(m.DATA_TYPE IN ('int','bigint','tinyint','smallint','decimal','double','float'), ' NOT NULL DEFAULT 0', ' NULL DEFAULT NULL'),
                         CONCAT(IF(r.IS_NULLABLE = 'YES', ' NULL', ' NOT NULL'), ' DEFAULT ', IFNULL(r.COLUMN_DEFAULT, 'NULL'))),
                      IF(m.COLUMN_COMMENT = '', '', CONCAT(' COMMENT ', QUOTE(m.COLUMN_COMMENT)))) def
        FROM information_schema.columns m
        JOIN information_schema.tables rt ON rt.TABLE_SCHEMA = " . $pdo->quote($ref) . " AND rt.TABLE_NAME = m.TABLE_NAME
        LEFT JOIN information_schema.columns r ON r.TABLE_SCHEMA = " . $pdo->quote($ref) . " AND r.TABLE_NAME = m.TABLE_NAME AND r.COLUMN_NAME = m.COLUMN_NAME
        WHERE m.TABLE_SCHEMA = " . $pdo->quote($destino) . "
          AND m.IS_NULLABLE = 'NO' AND m.COLUMN_DEFAULT IS NULL AND m.EXTRA = ''
          AND (r.COLUMN_NAME IS NULL OR r.IS_NULLABLE = 'YES' OR r.COLUMN_DEFAULT IS NOT NULL)
    ) x GROUP BY x.t";
    $n = 0;
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $alter) {
        try { $pdo->exec("USE `$destino`"); $pdo->exec($alter); $n++; } catch (Throwable $e) { logm('  alinear: ' . $e->getMessage()); }
    }
    return $n;
}

require_once __DIR__ . '/alinear.php';

/** JSON de permisos de mostrador (facturación, productos, clientes y cajas del menú 2). */
function permisosMostrador(): string
{
    $menu = json_decode(file_get_contents('C:/xampp/htdocs/sistema/menu2.json'), true);
    $ids = []; $urls = [];
    $recorrer = function ($items) use (&$recorrer, &$ids, &$urls) {
        foreach ($items as $it) { $ids[] = $it['id']; if (!empty($it['url'])) $urls[] = $it['url']; if (!empty($it['children'])) $recorrer($it['children']); }
    };
    foreach ($menu as $sec) if (in_array($sec['id'], ['facturacion', 'productos', 'clientes', 'cajas'], true)) { $ids[] = $sec['id']; $recorrer($sec['children'] ?? []); }
    return json_encode(['allowed_ids' => array_values(array_unique($ids)), 'allowed_urls' => array_values(array_unique($urls))], JSON_UNESCAPED_SLASHES);
}

/**
 * Crea (o actualiza) un usuario web. La contraseña llega por variable de entorno: en los archivos solo
 * queda el hash. $depositos: ids, el primero es el predeterminado. Sin la variable, no lo crea y avisa.
 */
function crearUsuarioWeb(PDO $pdo, string $usuario, string $envPassword, bool $admin, array $depositos): bool
{
    $pw = getenv($envPassword);
    if ($pw === false || $pw === '') { logm("  ATENCION usuario $usuario no creado: falta la variable $envPassword"); return false; }
    $hash = password_hash($pw, PASSWORD_BCRYPT);
    $pdo->prepare("INSERT INTO alb_usuarios (usuario, password, passweb, nivel, esadmin, habilitado, iddepositoactivo, logout) VALUES (?, '-', ?, 1, ?, 1, ?, 0)
                   ON DUPLICATE KEY UPDATE passweb = VALUES(passweb), esadmin = VALUES(esadmin), habilitado = 1, iddepositoactivo = VALUES(iddepositoactivo)")
        ->execute([$usuario, $hash, $admin ? 1 : 0, $depositos[0] ?? null]);
    $pdo->prepare("DELETE FROM usuarios_depositos WHERE usuario = ?")->execute([$usuario]);
    foreach ($depositos as $i => $d) {
        $pdo->prepare("INSERT INTO usuarios_depositos (usuario, id_deposito, es_predeterminado) VALUES (?, ?, ?)")->execute([$usuario, $d, $i === 0 ? 1 : 0]);
    }
    if (!$admin) {
        $id = (int) $pdo->query("SELECT id FROM alb_usuarios WHERE usuario = " . $pdo->quote($usuario))->fetchColumn();
        $pdo->prepare("UPDATE accesosusuarios SET vigente = 0 WHERE usuarioid = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO accesosusuarios (usuarioid, usuarionombre, permisos, timestamp, usuariomodificacion, vigente) VALUES (?, ?, ?, NOW(), 'migracion', 1)")
            ->execute([$id, $usuario, permisosMostrador()]);
    }
    return true;
}

/**
 * Saldo inicial de efectivo de un puesto de caja (el sistema nuevo calcula el saldo desde tesorería).
 * Tesorería + asiento: cuenta del efectivo del puesto (debe) contra Resultados No Asignados (haber).
 * Idempotente por descripción.
 */
function saldoInicialCaja(PDO $pdo, int $idCaja, float $importe, string $descripcion, string $ctaPatrimonio = '33200000'): void
{
    require_once 'C:/xampp/htdocs/sistema/contabilidad_cuentas_helpers.php';
    if (round($importe, 2) == 0.0) return;
    $ya = $pdo->prepare("SELECT COUNT(*) FROM cccuentastesoreria WHERE descripcion = ?");
    $ya->execute([$descripcion]);
    if ($ya->fetchColumn()) return;
    $medio = $pdo->query("SELECT m.id, m.cuentacontable FROM mediosdepago m JOIN cajas c ON c.id = $idCaja
        WHERE m.tipodecuenta = 'Caja de Usuarios' AND m.habilitado = 1 AND (m.puestoasociado = c.id OR m.puestoasociado = c.numcaja) ORDER BY m.id LIMIT 1")->fetch();
    if (!$medio) throw new Exception("el puesto $idCaja no tiene medio de efectivo");
    $fecha = date('Y-m-d');
    ['ejercicio' => $eje, 'periodo' => $per] = resolverEjercicioPeriodo($pdo, $fecha);
    $pdo->beginTransaction();
    $numTeso = (int) $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria FOR UPDATE")->fetchColumn() + 1;
    $num = siguienteNumeroAsiento($pdo, $eje);
    $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, numerodeasiento, descripcion, usuario, tabla, compasoc, fiscal) VALUES (?, ?, ?, 0, ?, ?, 'migracion', 'ajuste_caja', 0, 0)")
        ->execute([$medio['id'], $fecha, $importe, $numTeso, $descripcion]);
    grabarLineaAsiento($pdo, $num, $fecha, $medio['cuentacontable'], $importe, 0, 0, 'controlcajas', 0, 'migracion', $descripcion, $eje, $per);
    grabarLineaAsiento($pdo, $num, $fecha, $ctaPatrimonio, 0, $importe, 0, 'controlcajas', 0, 'migracion', $descripcion, $eje, $per);
    $pdo->commit();
}

/** Neto e IVA de las 8 listas recalculados desde el precio final (ver recalcular_netos.sql). Devuelve filas modificadas. */
function recalcularNetos(PDO $pdo, string $db): int
{
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/recalcular_netos.sql'));
    $pdo->exec("USE `$db`");
    return (int) $pdo->exec(trim($sql));
}

/**
 * Crea en el plan de cuentas las cuentas que el libro diario usa y el plan no tiene (el legacy VB no lo
 * validaba: MENA y COEGO imputaban mercadería a 11301002 y costo a 42002002 sin tenerlas en el plan).
 * Se cuelgan de su cuenta madre (xxxxx000 o xxx00000) y copian la clasificación de una hermana imputable.
 * Hay que correrla ANTES de las actualizaciones del backoffice: si no, el parche que corrige
 * cuentaspredeterminadas.mercaderia 11301002 -> 11301001 separaría la contabilidad nueva de la historia.
 * Devuelve [numcuenta => descripcion] creadas.
 */
function crearCuentasHuerfanas(PDO $pdo, string $db, string $ref = 'ref_completa'): array
{
    $pdo->exec("USE `$db`");
    $creadas = [];
    $huerf = $pdo->query("SELECT DISTINCT l.cuenta FROM librodiario l LEFT JOIN plandecuentas p ON p.numcuenta = l.cuenta
                          WHERE p.id IS NULL AND l.cuenta REGEXP '^[0-9]{8}$'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($huerf as $cta) {
        $madre = null;
        foreach ([substr($cta, 0, 5) . '000', substr($cta, 0, 3) . '00000', substr($cta, 0, 2) . '000000'] as $cand) {
            $m = $pdo->query("SELECT * FROM plandecuentas WHERE numcuenta = " . $pdo->quote($cand))->fetch(PDO::FETCH_ASSOC);
            if ($m) { $madre = $m; break; }
        }
        if (!$madre) { logm("  ATENCION cuenta $cta usada en el libro diario sin cuenta madre en el plan"); continue; }
        $hermana = $pdo->query("SELECT * FROM plandecuentas WHERE cuentamadre = " . $pdo->quote($madre['numcuenta']) . " AND imputable = 1 ORDER BY numcuenta LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: $madre;
        $desc = $pdo->query("SELECT descripcion FROM `$ref`.plandecuentas WHERE numcuenta = " . $pdo->quote($cta))->fetchColumn()
            ?: mb_substr($madre['descripcion'] . ' (legacy)', 0, 50);
        $pdo->prepare("INSERT INTO plandecuentas (numcuenta, descripcion, habilitada, imputable, cuentamadre, patrimonial, rubro, subrubro1, subrubro2, subrubro3, subrubro4, jerarquia, observaciones)
                       VALUES (?, ?, 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, 'Creada en la migración: la usaba el libro diario del sistema anterior')")
            ->execute([$cta, $desc, $madre['numcuenta'], $hermana['patrimonial'], $hermana['rubro'], $hermana['subrubro1'], $hermana['subrubro2'],
                       $hermana['subrubro3'], (int) substr($cta, -3), $hermana['jerarquia']]);
        $creadas[$cta] = $desc;
    }
    return $creadas;
}
