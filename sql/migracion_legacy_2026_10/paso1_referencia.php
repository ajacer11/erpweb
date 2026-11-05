<?php
/**
 * Referencia del esquema nuevo: base en blanco del backoffice (baseenblanco.sql +
 * actualizaciones.sql, igual que setup_db) + migraciones de sistema/sql + todo lo que el
 * esquema de desarrollo (coegotest -> ref_nuevo) tenga de más.
 * Resultado: ref_completa (estructura + datos semilla de la plantilla).
 */
require __DIR__ . '/lib.php';
$pdo = conectar();
$pdo->exec("DROP DATABASE IF EXISTS ref_completa");
$pdo->exec("CREATE DATABASE ref_completa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
logm('baseenblanco.sql...');
$r = correrArchivoSql('ref_completa', ADMIN_SQLS . '/baseenblanco.sql');
logm('  salida: ' . mb_substr(preg_replace('/\s+/', ' ', $r), 0, 400));
$pdo->exec("USE ref_completa");
[$ok, $err] = aplicarActualizacionesBackoffice($pdo);
logm("actualizaciones.sql: $ok ok, $err ignoradas");
foreach (aplicarMigracionesSistema('ref_completa') as $s => $res) logm("  $s: $res");
[$c, $a, $e] = completarEsquema($pdo, 'ref_completa', 'ref_nuevo');
logm('desde ref_nuevo: ' . count($c) . ' tablas creadas (' . implode(', ', $c) . '), ' . count($a) . ' columnas agregadas');
foreach ($a as $x) logm("  + $x");
foreach ($e as $x) logm("  ERROR $x");
logm('tablas en ref_completa: ' . count(tablas($pdo, 'ref_completa')));
