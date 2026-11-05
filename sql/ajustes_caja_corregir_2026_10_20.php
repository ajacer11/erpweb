<?php
/**
 * Corrige los ajustes de caja grabados por api_cajas.php (adjust_balance) antes del arreglo del 20/10/2026:
 *  - el asiento iba contra cuentaspredeterminadas.ajuste (diferencia de INVENTARIOS) en vez de la cuenta del medio
 *    de tesorería de tipo 'Ajuste' (Diferencias de Caja): se cambia la cuenta de esa línea;
 *  - en tesorería faltaba el movimiento del medio de Ajuste (contrapartida de la caja): se agrega.
 * Uso: php ajustes_caja_corregir.php [--aplicar] westit_xxx ...   (sin --aplicar solo informa)
 * Idempotente. Solo toca filas de tabla 'ajuste_caja' (tesorería) y las líneas 'controlcajas' con detalle
 * 'AJUSTE DE CAJA - ...' (diario) que generó esa función.
 */
$src = file_get_contents('/home/westit/erp.westit.com.ar/db_master.php');
$src = str_replace(["PHP_SAPI === 'cli'", '__DIR__'], ['false', "'/home/westit/erp.westit.com.ar'"], $src);
eval('?>' . $src);
$c = parseConnectionString($pdo_master->query("SELECT cadenaconec FROM `30718652959` WHERE id = 1")->fetchColumn());
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$args = array_slice($argv, 1);
$aplicar = in_array('--aplicar', $args, true);
foreach (array_diff($args, ['--aplicar']) as $db) {
    $pdo->exec("USE `$db`");
    $medios = $pdo->query("SELECT m.id, m.cuentacontable FROM mediosdepago m JOIN plandecuentas p ON p.numcuenta = m.cuentacontable AND p.imputable = 1
                           WHERE m.tipodecuenta = 'Ajuste' AND m.habilitado = 1")->fetchAll();
    if (count($medios) !== 1) { echo "$db: " . count($medios) . " medios de Ajuste habilitados con cuenta: se omite\n"; continue; }
    $medio = $medios[0];
    $ctaInv = (string) $pdo->query("SELECT ajuste FROM cuentaspredeterminadas WHERE id = 1")->fetchColumn();

    $lineasMal = $ctaInv !== $medio['cuentacontable'] ? $pdo->query("SELECT COUNT(*) n, IFNULL(SUM(debe), 0) d, IFNULL(SUM(haber), 0) h FROM librodiario
        WHERE tabla = 'controlcajas' AND detalle LIKE 'AJUSTE DE CAJA - %' AND cuenta = " . $pdo->quote($ctaInv))->fetch() : ['n' => 0, 'd' => 0, 'h' => 0];
    $sinContra = $pdo->query("SELECT t.* FROM cccuentastesoreria t WHERE t.tabla = 'ajuste_caja' AND t.idcuenta <> {$medio['id']}
        AND t.descripcion NOT LIKE 'SALDO INICIAL MIGRACION%' AND NOT EXISTS (SELECT 1 FROM cccuentastesoreria x WHERE x.tabla = 'ajuste_caja' AND x.numerodeasiento = t.numerodeasiento AND x.idcuenta = {$medio['id']})")->fetchAll();
    printf("%s: medio Ajuste %d (cta %s); diario contra %s: %d líneas (debe %.2f / haber %.2f); tesorería sin contrapartida: %d\n",
        $db, $medio['id'], $medio['cuentacontable'], $ctaInv, $lineasMal['n'], $lineasMal['d'], $lineasMal['h'], count($sinContra));
    if (!$aplicar || (!$lineasMal['n'] && !$sinContra)) continue;

    $pdo->beginTransaction();
    if ($lineasMal['n']) {
        $pdo->prepare("UPDATE librodiario SET cuenta = ? WHERE tabla = 'controlcajas' AND detalle LIKE 'AJUSTE DE CAJA - %' AND cuenta = ?")
            ->execute([$medio['cuentacontable'], $ctaInv]);
    }
    $ins = $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, fecha, debe, haber, numerodeasiento, descripcion, usuario, tabla, usuariocaja, fiscal, compasoc)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'ajuste_caja', ?, ?, ?)");
    foreach ($sinContra as $t) {
        $ins->execute([$medio['id'], $t['fecha'], $t['haber'], $t['debe'], $t['numerodeasiento'], $t['descripcion'], $t['usuario'], $t['usuariocaja'], $t['fiscal'], $t['compasoc']]);
    }
    $pdo->commit();
    echo "  corregido\n";
}
