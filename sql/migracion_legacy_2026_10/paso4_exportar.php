<?php
/**
 * Exporta las bases migradas para importarlas en el VPS (Linux, nombres de tabla sensibles a
 * mayúsculas). MariaDB en Windows guarda todo en minúscula; acá se restituye la capitalización
 * de las 7 tablas que el código PHP usa con mayúsculas, SOLO en contexto de tabla (hay una
 * columna cuentaspredeterminadas.proveedores que no se debe tocar).
 * Uso: php paso4_exportar.php wst_db_xxx [wst_db_yyy ...]
 */
require __DIR__ . '/lib.php';
$CASE = ['afipconfig' => 'AFIPconfig', 'caja' => 'Caja', 'lineadeventa' => 'Lineadeventa', 'listas' => 'Listas',
         'marcas' => 'Marcas', 'movdecaja' => 'MovdeCaja', 'proveedores' => 'Proveedores'];
$re = '/((?:CREATE TABLE(?: IF NOT EXISTS)?|INSERT INTO|LOCK TABLES|DROP TABLE IF EXISTS|ALTER TABLE|REFERENCES)\s+)`(' . implode('|', array_keys($CASE)) . ')`/i';
@mkdir('C:/mig_legacy/export', 0775, true);
foreach (array_slice($argv, 1) as $db) {
    $tmp = "C:/mig_legacy/export/_$db.raw.sql";
    $out = "C:/mig_legacy/export/$db.sql";
    shell_exec('"C:/xampp/mysql/bin/mysqldump.exe" -uroot --routines --triggers --single-transaction --default-character-set=utf8mb4 --hex-blob ' . escapeshellarg($db) . ' > ' . escapeshellarg($tmp) . ' 2>&1');
    $in = fopen($tmp, 'r'); $o = fopen($out, 'w'); $n = 0;
    while (($line = fgets($in)) !== false) {
        $nueva = preg_replace_callback($re, static fn($m) => $m[1] . '`' . $CASE[strtolower($m[2])] . '`', $line);
        if ($nueva !== $line) $n++;
        // Triggers/rutinas del legacy traen DEFINER de usuarios que no existen en el VPS (kinetica, Liyo):
        // sin esto el INSERT en tickets falla con error 1449. Se deja root@localhost, el usuario de la conexión.
        $nueva = preg_replace('/DEFINER=`[^`]+`@`[^`]+`/', 'DEFINER=`root`@`localhost`', $nueva);
        fwrite($o, $nueva);
    }
    fclose($in); fclose($o); unlink($tmp);
    logm(sprintf('%s -> %s (%.1f MB, %d líneas con nombre corregido)', $db, $out, filesize($out) / 1048576, $n));
}
