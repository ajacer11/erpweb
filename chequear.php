<?php

$directorio = __DIR__; // o poné la ruta que quieras
$resultados = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($directorio)
);

foreach ($iterator as $file) {

    if (!$file->isFile()) continue;

    $nombre = $file->getFilename();

    // Solo archivos que empiecen con api_ y terminen en .php
    if (!preg_match('/^api_.*\.php$/i', $nombre)) continue;

    $contenido = file_get_contents($file->getPathname());

    // Regex flexible:
    // INSERT (espacios)
    // INTO (espacios)
    // `tickets` o tickets
    $pattern = '/INSERT\s+INTO\s+[`"]?tickets[`"]?/i';

    if (preg_match($pattern, $contenido)) {
        $resultados[] = $file->getPathname();
    }
}

if (empty($resultados)) {
    echo "No se encontraron coincidencias.";
} else {
    echo "Archivos encontrados:\n\n";
    foreach ($resultados as $archivo) {
        echo $archivo . "\n";
    }
}