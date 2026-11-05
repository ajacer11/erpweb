<?php
/**
 * Corre alinearConReferencia() en el VPS sobre bases ya publicadas, contra westit_sofi.
 * Uso (en el VPS): php alinear_vps.php westit_nadia [westit_coego ...]
 * Hacer backup antes (mysqldump). Las tablas se reconstruyen: en bases grandes tarda unos minutos.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/alinear.php';
$src = file_get_contents('/home/westit/erp.westit.com.ar/db_master.php');
$src = str_replace(["PHP_SAPI === 'cli'", '__DIR__'], ['false', "'/home/westit/erp.westit.com.ar'"], $src);
eval('?>' . $src);
$c = parseConnectionString($pdo_master->query("SELECT cadenaconec FROM `30718652959` WHERE id = 1")->fetchColumn());
$pdo = new PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (array_slice($argv, 1) as $db) {
    $t0 = microtime(true);
    $r = alinearConReferencia($pdo, $db, 'westit_sofi');
    printf("%s: %d columnas alineadas en %d tablas, %d índices agregados, %d columnas basura borradas, %d errores (%.0f s)\n",
        $db, $r['columnas'], $r['tablas'], $r['indices'], $r['borradas'], count($r['errores']), microtime(true) - $t0);
}
