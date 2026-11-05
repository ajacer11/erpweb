<?php
/**
 * Descarga las bases legacy y las carga en las copias locales de trabajo (src_*).
 * Las credenciales NO se guardan en el archivo: se pasan por variables de entorno.
 *
 *   set LEGACY_HOST=179.43.126.39
 *   set LEGACY_USER=...
 *   set LEGACY_PASS=...
 *   php paso0_descargar.php            (las tres)
 *   php paso0_descargar.php nadia      (una sola)
 *
 * El origen solo se lee (mysqldump --single-transaction). Hacerlo con los sistemas viejos
 * cerrados, al momento del corte, para no dejar ventas afuera.
 */
require __DIR__ . '/lib.php';
$BASES = ['coego' => ['coego', 'src_coego'], 'nadia' => ['kinetica_nadia1', 'src_nadia'], 'nicolas' => ['kinetica_nicolas', 'src_nicolas'], 'mena' => ['kinetica_mena', 'src_mena']];
$host = getenv('LEGACY_HOST'); $user = getenv('LEGACY_USER'); $pass = getenv('LEGACY_PASS');
if (!$host || !$user || $pass === false) exit("Faltan LEGACY_HOST / LEGACY_USER / LEGACY_PASS\n");
$elegidas = array_slice($argv, 1) ?: array_keys($BASES);
$pdo = conectar();
foreach ($elegidas as $k) {
    [$origen, $local] = $BASES[$k];
    $archivo = __DIR__ . "/$origen.sql";
    logm("descargando $origen...");
    $cmd = '"C:/xampp/mysql/bin/mysqldump.exe" -h ' . escapeshellarg($host) . ' -u ' . escapeshellarg($user) . ' -p' . escapeshellarg($pass)
         . ' --single-transaction --quick --routines --triggers --skip-lock-tables --default-character-set=utf8mb4 ' . escapeshellarg($origen)
         . ' > ' . escapeshellarg($archivo) . ' 2> ' . escapeshellarg("$archivo.err");
    shell_exec($cmd);
    $fh = fopen($archivo, 'r'); fseek($fh, max(0, filesize($archivo) - 200)); $cola = (string) fread($fh, 200); fclose($fh);
    if (!str_contains($cola, 'Dump completed')) {
        exit("La descarga de $origen no terminó bien: ver $archivo.err\n");
    }
    $pdo->exec("DROP DATABASE IF EXISTS `$local`");
    $pdo->exec("CREATE DATABASE `$local` CHARACTER SET utf8mb4");
    $r = trim(correrArchivoSql($local, $archivo, false));
    logm("$origen -> $local " . ($r === '' ? 'ok' : $r));
}
