<?php

/**
 * Indica si el tenant tiene las credenciales productivas necesarias para
 * emitir comprobantes fiscales. El CUIT se normaliza para impedir rutas libres.
 */
function arcaCredencialesFiscalesDisponibles($cuit)
{
    $cuitLimpio = preg_replace('/\D/', '', (string)$cuit);
    if ($cuitLimpio === '') {
        return false;
    }

    $directorio = __DIR__ . DIRECTORY_SEPARATOR . 'ARCA'
        . DIRECTORY_SEPARATOR . $cuitLimpio
        . DIRECTORY_SEPARATOR . 'prod';

    foreach (['certificado.pem', 'privada.key'] as $archivo) {
        $ruta = $directorio . DIRECTORY_SEPARATOR . $archivo;
        if (!is_file($ruta) || !is_readable($ruta) || filesize($ruta) < 1) {
            return false;
        }
    }

    return true;
}
