<?php
/**
 * permisos_helpers.php - Única fuente de verdad para leer la restricción
 * "solo ver/operar los clientes de un vendedor" (permisosespeciales.unvendedor/idvendedor).
 *
 * Se hidrata en $_SESSION['pe_unvendedor']/$_SESSION['pe_idvendedor'] en menu.php a partir
 * de la tabla permisosespeciales. No leer $_SESSION[...] directo en otros archivos: varias
 * copias con nombres de clave distintos (unvendedor/idvendedor sueltos, permisos.vendedor_restringido)
 * quedaron muertas porque nunca se escribían — usar siempre estas dos funciones.
 */

function esVendedorRestringido(): bool {
    return (int) ($_SESSION['pe_unvendedor'] ?? 0) === 1;
}

function idVendedorRestringido(): int {
    return (int) ($_SESSION['pe_idvendedor'] ?? 0);
}
