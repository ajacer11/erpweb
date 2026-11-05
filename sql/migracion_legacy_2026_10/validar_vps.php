<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // solo consola
// Valida en el VPS que los 3 tenants migrados resuelven y conectan con el mismo código del login.
$src = file_get_contents('/home/westit/erp.westit.com.ar/db_master.php');
$src = str_replace(["PHP_SAPI === 'cli'", '__DIR__'], ['false', "'/home/westit/erp.westit.com.ar'"], $src);
eval('?>' . $src);
$esperado = ['20211411466' => 'westit_coego', '27326319363' => 'westit_nadia', '20281563859' => 'westit_nicolas', '30714902888' => 'westit_mena'];
foreach ($esperado as $cuit => $base) {
    $filas = getTenantDatabases($pdo_master, $cuit);
    $fila = null;
    foreach ($filas as $f) if (str_contains($f['cadenaconec'], "database=$base;")) $fila = $f;
    if (!$fila) { echo "$cuit: FALLA no hay fila habilitada para $base\n"; continue; }
    $cfg = getTenantDatabaseConfig($pdo_master, $cuit, $fila['id']);
    $c = parseConnectionString($cfg['cadenaconec']);
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8mb4", $c['user'], $c['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $u = $pdo->query("SELECT COUNT(*) total, SUM(passweb LIKE '\$2y\$%') bcrypt, SUM(habilitado=1) habil FROM alb_usuarios")->fetch(PDO::FETCH_ASSOC);
    $de = $pdo->query("SELECT razon, puntodeventa FROM datosempresa WHERE id=1")->fetch(PDO::FETCH_NUM);
    $t = $pdo->query("SELECT COUNT(*), MAX(fecha) FROM tickets")->fetch(PDO::FETCH_NUM);
    $cfgv = $pdo->query("SELECT multidepositohabilitado FROM configuracion ORDER BY id DESC LIMIT 1")->fetchColumn();
    echo "$cuit OK fila {$fila['id']} '{$fila['nombre']}' tipoversion={$cfg['tipoversion']} -> {$c['db']} | {$de[0]} PV {$de[1]} | usuarios {$u['total']} (bcrypt {$u['bcrypt']}, habilitados {$u['habil']}) | tickets {$t[0]} hasta {$t[1]} | multidep $cfgv | filas visibles en login: " . count($filas) . "\n";
}
