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

function puedeEditarDescripcionFacturar(): bool {
    return (int) ($_SESSION['pe_editardescripcionfacturar'] ?? 0) === 1;
}

function puedeEditarDescripcionPedidos(): bool {
    return (int) ($_SESSION['pe_editardescripcionpedidos'] ?? 0) === 1;
}

function puedeEditarDescripcionPresupuestos(): bool {
    return (int) ($_SESSION['pe_editardescripcionpresupuestos'] ?? 0) === 1;
}

/**
 * Modificar los medios de cobro de un comprobante ya emitido. Se lee de la base
 * (no de la sesión) para que un permiso quitado tenga efecto en el acto: la
 * operación mueve caja, tesorería, bancos, cuenta corriente y contabilidad.
 * Los administradores pueden siempre.
 */
function puedeModificarMediosCobro(PDO $pdo): bool {
    if ((int) ($_SESSION['es_admin'] ?? 0) === 1) return true;
    try {
        $stmt = $pdo->prepare('SELECT modificarmedioscobro FROM permisosespeciales WHERE usuario = ? LIMIT 1');
        $stmt->execute([$_SESSION['usuario_nombre'] ?? '']);
        return (int) $stmt->fetchColumn() === 1;
    } catch (PDOException $e) {
        // Base sin sql/2026-10-14_modificar_medios_cobro.sql: nadie salvo admin.
        return false;
    }
}
