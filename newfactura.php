<?php
/**
 * newfactura.php - INTERFAZ DE FACTURACIÓN (REDISEÑO V2)
 *
 * Misma lógica real que facturar.php (cálculo, promociones, validaciones,
 * grabado — usa los mismos endpoints, graba comprobantes de verdad).
 * El HTML/CSS es un diseño nuevo de punta a punta, pensado para no
 * depender de clases sin definir (la vieja "theme-aware-input" no existía
 * en ningún CSS y por eso el texto se veía negro/invisible en modo oscuro).
 * Todo color acá usa las variables de stylesglobal.css (--text-main,
 * --bg-card, etc.) para adaptarse solo a claro/oscuro.
 *
 * ACCESO: igual que el resto de los módulos, vía check_access.php
 * (licencia/contrato SaaS + rol de usuario).
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$pedidoIdFacturar = isset($_GET['pedidoid']) ? intval($_GET['pedidoid']) : 0;

// notacredito= (comprobantes_emitidos.php) y asociado= (reporte_ventas_comprobantes.php) son
// el mismo caso: id de un ticket existente para precargar su Nota de Crédito. Se resuelve acá,
// server-side, y no leyendo window.location.search en el JS — ese valor recién queda correcto
// después del pushState de loadModule() en index.php, que corre DESPUÉS de que el script ya
// arrancó a ejecutarse (carrera real, no garantizada por la red).
$ncOrigenId = isset($_GET['notacredito']) ? intval($_GET['notacredito'])
            : (isset($_GET['asociado']) ? intval($_GET['asociado']) : 0);

// ======================================================================
// PERMISO DE MENÚ: "Agregar Clientes" (clientes_alta.php)
// Mismo criterio que dashboard1.php/dashboard2.php: admin siempre puede,
// el resto depende de accesosusuarios.permisos.allowed_urls.
// ======================================================================
$isAdmin = isset($_SESSION['es_admin']) && (int) $_SESSION['es_admin'] === 1;
$allowedUrls = [];

if (!$isAdmin) {
    try {
        $stmtPerm = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
        $stmtPerm->execute([$_SESSION['usuario_id']]);
        $rowPerm = $stmtPerm->fetch(PDO::FETCH_ASSOC);
        if ($rowPerm) {
            $jsonPermisos = json_decode($rowPerm['permisos'], true);
            $allowedUrls = $jsonPermisos['allowed_urls'] ?? [];
        }
    } catch (Exception $e) {
        $allowedUrls = [];
    }
}

function canAccessUrl($url) {
    global $isAdmin, $allowedUrls;
    if ($isAdmin) return true;
    $baseTarget = basename($url);
    foreach ($allowedUrls as $allowed) {
        if (basename($allowed) === $baseTarget) return true;
    }
    return false;
}

$puedeCrearCliente = canAccessUrl('clientes_alta.php');
$puedeImprimirEnvios = canAccessUrl('imprimir_envios.php');

require_once 'permisos_helpers.php';
$puedeEditarDescFacturar = puedeEditarDescripcionFacturar();
?>

<style>
    /* ============================================================
       NEWFACTURA V2 — diseño propio, todo en base a var(--...)
       de stylesglobal.css. Nada de colores fijos (#fff, black, etc.)
       salvo los que ya son semánticos (success/danger) y también
       vienen de variables.
       ============================================================ */
    .nf2 {
        --nf2-radius: 14px;
        --nf2-radius-sm: 10px;
        --nf2-shadow: 0 10px 28px rgba(0,0,0,.22);
        display: grid;
        grid-template-columns: 1fr 320px;
        gap: 10px;
        width: 100%;
        height: 100%;
        padding: 10px;
        box-sizing: border-box;
        background: var(--bg-escritorio);
        color: var(--text-main);
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }
    .nf2 * { box-sizing: border-box; }
    .nf2-card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: var(--nf2-radius);
        box-shadow: var(--nf2-shadow);
    }

    /* ---------- Columna principal ---------- */
    .nf2-main { display: flex; flex-direction: column; min-width: 0; min-height: 0; gap: 10px; }

    .nf2-topbar { display: flex; align-items: center; gap: 14px; padding: 12px 16px; flex-wrap: wrap; }
    .nf2-topbar .nf2-field { display: flex; flex-direction: column; gap: 3px; }
    .nf2-topbar .lbl { font-size: 9.5px; font-weight: 800; letter-spacing: .5px; color: var(--text-muted); text-transform: uppercase; }
    .nf2-cliente-field { flex: 1 1 280px; min-width: 200px; }
    .nf2-tipo-field { width: 190px; }
    .nf2-num-field { text-align: center; }
    .nf2-num-field .num { font-size: 17px; font-weight: 800; color: var(--accent); }
    .nf2-badge {
        margin-left: auto;
        display: flex; flex-direction: column; align-items: flex-end; gap: 4px;
    }
    .nf2-pill {
        font-size: 9px; font-weight: 800; letter-spacing: .5px; text-transform: uppercase;
        padding: 4px 12px; border-radius: 20px;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent);
    }
    .nf2-fiscal { font-size: 10px; font-weight: 700; text-align: center; line-height: 1.3; }

    .nf2-icon-btn {
        width: 34px; height: 34px; border-radius: 10px;
        display: inline-flex; align-items: center; justify-content: center;
        background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);
        cursor: pointer; transition: .15s;
    }
    .nf2-icon-btn:hover { border-color: var(--accent); color: var(--accent); background: var(--accent-soft); }
    /* Nuevo cliente: verde permanente para diferenciarlo a simple vista */
    .nf2-icon-btn.nf2-btn-nuevo-cli { background: rgba(16,185,129,.15); border-color: #10b981; color: #10b981; }
    .nf2-icon-btn.nf2-btn-nuevo-cli:hover { background: rgba(16,185,129,.28); border-color: #10b981; color: #10b981; }
    /* Ficha del cliente: color propio (antes compartía el gris neutro con "Buscador ampliado") */
    .nf2-icon-btn.nf2-btn-ficha { background: rgba(13,202,240,.12); border-color: var(--info); color: var(--info); }
    .nf2-icon-btn.nf2-btn-ficha:hover { background: rgba(13,202,240,.24); border-color: var(--info); color: var(--info); }

    /* ---------- Barra de búsqueda (pill grande) ---------- */
    .nf2-searchbar {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 14px;
    }
    .nf2-qty {
        width: 64px; flex-shrink: 0; text-align: center; font-weight: 800; font-size: 15px;
        background: var(--input-bg); border: 1px solid var(--input-border); border-radius: var(--nf2-radius-sm);
        color: var(--text-main); height: 42px;
    }
    .nf2-cod-exacto {
        width: 150px; flex-shrink: 0; font-weight: 800; font-size: 13px; text-align: center;
        background: var(--input-bg); border: 1.5px solid var(--accent); border-radius: var(--nf2-radius-sm);
        color: var(--text-main); height: 42px;
    }
    .nf2-cod-exacto::placeholder { color: var(--text-muted); font-weight: 600; font-size: 10.5px; }
    .nf2-search-wrap { flex: 1 1 auto; min-width: 0; }
    .nf2-search-wrap .select2-container { width: 100% !important; }
    .nf2-search-wrap .select2-selection--single {
        height: 42px !important; border-radius: var(--nf2-radius-sm) !important;
        background: var(--input-bg) !important; border: 1px solid var(--input-border) !important;
        display: flex !important; align-items: center !important;
    }
    .nf2-search-wrap .select2-selection__rendered { color: var(--text-main) !important; font-size: 13px; }
    .nf2-search-wrap .select2-selection__arrow { height: 40px !important; }
    .nf2-lista {
        width: 108px; flex-shrink: 0; height: 42px; border-radius: var(--nf2-radius-sm);
        background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);
        font-size: 12px; font-weight: 700; text-align: center;
    }
    /* Resultados Select2: fila con miniatura */
    .nf2-opt { display: flex; align-items: center; gap: 8px; padding: 2px 0; }
    .nf2-opt .nf2-thumb-mini { width: 26px; height: 26px; }
    .nf2-opt-txt { display: flex; flex-direction: column; min-width: 0; }
    .nf2-opt-cod { font-size: 9.5px; color: var(--text-muted); font-weight: 700; }
    .nf2-opt-desc { font-size: 12px; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .nf2-opt-stk { margin-left: auto; padding-left: 8px; font-size: 11px; font-weight: 700; white-space: nowrap; color: var(--success, #2ecc71); }
    .nf2-opt-stk + .nf2-opt-price { margin-left: 0; }
    .select2-results__option--highlighted .nf2-opt-stk { color: inherit; }
    .nf2-opt-stk.sin-stock { color: var(--danger, #e74c3c); }
    .nf2-opt-price { margin-left: auto; font-size: 12px; font-weight: 800; color: var(--accent); white-space: nowrap; padding-left: 8px; }

    /* ---------- Lista de ítems ---------- */
    .nf2-list-toolbar { display: flex; align-items: center; justify-content: space-between; padding: 0 4px; }
    .nf2-list-count { font-size: 10.5px; color: var(--text-muted); font-weight: 700; }
    .nf2-toggle-vista {
        font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px;
        background: transparent; border: 1px dashed var(--input-border); color: var(--text-muted);
        border-radius: 20px; padding: 4px 12px; cursor: pointer;
    }
    .nf2-toggle-vista:hover { color: var(--accent); border-color: var(--accent); }

    .nf2-list-wrap { flex: 1; min-height: 0; overflow-y: auto; padding: 4px; }

    /* Tarjetas de producto (vista simple, por defecto) */
    .nf2-item-card {
        display: flex; align-items: center; gap: 12px;
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--nf2-radius-sm);
        padding: 8px 10px; margin-bottom: 8px; transition: border-color .15s;
    }
    .nf2-item-card:hover { border-color: var(--accent); }
    .nf2-thumb {
        width: 46px; height: 46px; border-radius: 8px; overflow: hidden; flex-shrink: 0;
        background: var(--input-bg); border: 1px solid var(--border-color);
        display: flex; align-items: center; justify-content: center;
    }
    .nf2-thumb img { width: 100%; height: 100%; object-fit: contain; display: block; cursor: zoom-in; }
    .nf2-thumb i { color: var(--text-muted); font-size: 16px; }
    .nf2-item-info { flex: 1 1 auto; min-width: 0; }
    .nf2-item-field { display:flex; flex-direction:column; gap:3px; flex-shrink:0; }
    .nf2-item-field-label { font-size:8px; line-height:1; font-weight:800; color:var(--text-muted); text-transform:uppercase; text-align:center; }
    .nf2-item-cod { font-size: 9.5px; font-weight: 800; color: var(--text-muted); }
    .nf2-item-desc { font-size: 12.5px; font-weight: 600; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .nf2-item-sub { font-size: 10px; color: var(--text-muted); margin-top: 1px; }
    .nf2-item-qty {
        width: 48px; text-align: center; font-weight: 800; font-size: 12px;
        background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 6px; color: var(--text-main); height: 30px;
    }
    .nf2-item-desc-pct {
        width: 46px; text-align: center; font-size: 11px; font-weight: 700;
        background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 6px; color: var(--warning); height: 30px;
    }
    .nf2-item-lista-sel {
        width: 44px; text-align: center; font-size: 10px; font-weight: 700; height: 30px;
        background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 6px; color: var(--text-main);
    }
    .nf2-item-total { width: 92px; text-align: right; font-weight: 800; font-size: 13.5px; color: var(--accent); }
    .nf2-item-final-input {
        width: 92px; text-align: right; font-weight: 800; font-size: 13px; color: var(--accent);
        background: var(--input-bg); border: 1px solid var(--input-border); border-radius: 6px; height: 30px;
    }
    .nf2-item-remove {
        width: 30px; height: 30px; border-radius: 8px; flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        color: var(--danger); background: transparent; border: 1px solid transparent; cursor: pointer;
    }
    .nf2-item-remove:hover { background: rgba(220,53,69,.12); border-color: var(--danger); }
    .nf2-promo-chip {
        font-size: 8px; font-weight: 900; padding: 1px 6px; border-radius: 10px;
        background: rgba(74, 222, 128, .15); color: #4ade80; border: 1px solid #16a34a; margin-left: 5px; white-space: nowrap;
    }
    .nf2-empty-list {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        height: 100%; color: var(--text-muted); gap: 8px; opacity: .7;
    }
    .nf2-empty-list i { font-size: 34px; }

    /* Vista tabla / contable (para quien la necesite, oculta por defecto) */
    .nf2-table-wrap { display: none; }
    .nf2-table-wrap.show { display: block; }
    .nf2-table { width: 100%; border-collapse: collapse; }
    .nf2-table thead th {
        position: sticky; top: 0; background: var(--bg-table-header); color: var(--text-muted);
        font-size: 8.5px; text-transform: uppercase; font-weight: 800; padding: 6px; text-align: left; border-bottom: 1px solid var(--border-color);
    }
    .nf2-table tbody td { padding: 5px 6px; border-bottom: 1px solid var(--border-color); font-size: 11px; color: var(--text-main); }
    .nf2-table tbody tr:hover { background: var(--accent-soft); }
    .nf2-table input {
        background: transparent; border: none; color: var(--text-main); width: 100%; font-size: 11px; text-align: right;
    }
    .nf2-table input:focus { outline: 1px solid var(--accent); border-radius: 4px; }

    /* ---------- Columna lateral ---------- */
    .nf2-side { display: flex; flex-direction: column; gap: 10px; min-height: 0; height: 100%; }

    /* Alto FIJO (% de la columna lateral) para que no "salte" el resto de los
       paneles al pasar el mouse por distintos ítems: antes esta tarjeta crecía
       o achicaba según hubiera imagen o no, y según el largo del título/nombre
       del producto (una o dos líneas de texto). Con flex-basis fijo + overflow
       hidden + texto en una sola línea (ellipsis), el tamaño es siempre el
       mismo pase lo que pase adentro. */
    .nf2-preview {
        flex: 0 0 34%;
        padding: 14px; text-align: center;
        display: flex; flex-direction: column; overflow: hidden;
    }
    .nf2-preview-img {
        width: 100%; flex: 1 1 auto; min-height: 0; border-radius: var(--nf2-radius-sm); overflow: hidden;
        background: var(--input-bg); border: 1px solid var(--border-color);
        display: flex; align-items: center; justify-content: center; margin-bottom: 8px;
    }
    .nf2-preview-img img { width: 100%; height: 100%; object-fit: contain; display: block; cursor: zoom-in; }
    .nf2-preview-img i { font-size: 40px; color: var(--text-muted); opacity: .5; }
    .nf2-preview-title {
        font-size: 12px; font-weight: 700; color: var(--text-main); flex-shrink: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .nf2-preview-sub {
        font-size: 10px; color: var(--text-muted); margin-top: 2px; flex-shrink: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .nf2-preview-price {
        font-size: 15px; font-weight: 800; color: var(--accent); margin-top: 4px; flex-shrink: 0;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .nf2-cliente-card { flex: 1; min-height: 90px; padding: 14px 16px; display: flex; flex-direction: column; overflow-y: auto; }
    .nf2-cliente-empty {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; color: var(--text-muted); text-align: center; font-size: 11px; opacity: .7;
    }
    .nf2-cliente-empty i { font-size: 30px; }
    .nf2-cliente-nombre { font-size: 13.5px; font-weight: 800; color: var(--text-main); }
    .nf2-cliente-doc { font-size: 10.5px; color: var(--text-muted); margin-bottom: 8px; }
    .nf2-cliente-rows { display: flex; flex-direction: column; gap: 6px; border-top: 1px dashed var(--border-color); padding-top: 8px; }
    .nf2-cliente-row { display: flex; justify-content: space-between; gap: 8px; font-size: 10.5px; color: var(--text-muted); }
    .nf2-cliente-row b { color: var(--text-main); font-weight: 700; text-align: right; max-width: 60%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .nf2-cliente-row b.ok { color: var(--success); }
    .nf2-cliente-row b.bloqueada { color: var(--danger); }

    .nf2-summary { flex-shrink: 0; padding: 14px 16px; display: flex; flex-direction: column; gap: 6px; }
    .nf2-sum-row { display: flex; justify-content: space-between; align-items: center; font-size: 11.5px; color: var(--text-muted); }
    .nf2-sum-row b { color: var(--text-main); font-weight: 700; font-size: 12.5px; }
    .nf2-sum-adj-edit { color: var(--accent); cursor: pointer; margin-left: 4px; font-size: 10px; }
    .nf2-sum-detail-toggle {
        font-size: 9.5px; color: var(--text-muted); text-decoration: underline dotted; cursor: pointer; text-align: center; margin: 2px 0;
    }
    .nf2-sum-detail { display: none; border-top: 1px dashed var(--border-color); padding-top: 6px; margin-top: 2px; }
    .nf2-sum-detail.show { display: block; }
    .nf2-total-row { border-top: 1px solid var(--border-color); margin-top: 6px; padding-top: 8px; text-align: right; }
    .nf2-total-lbl { font-size: 10px; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
    .nf2-total-val { font-size: 32px; font-weight: 900; color: var(--accent); line-height: 1; letter-spacing: -1px; }

    .nf2-btn-cobrar {
        width: 100%; padding: 14px; border: none; border-radius: var(--nf2-radius-sm);
        background: linear-gradient(135deg, #10b981, #059669); color: #fff;
        font-weight: 800; font-size: 13px; letter-spacing: .5px; text-transform: uppercase; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px;
        box-shadow: 0 6px 18px rgba(16,185,129,.3);
    }
    .nf2-btn-cobrar:hover { filter: brightness(1.05); }

    .nf2-toolbar-side { display: flex; gap: 8px; flex-wrap: wrap; }
    .nf2-btn-mini {
        flex: 1 1 auto; min-width: 70px; padding: 9px 4px; border-radius: var(--nf2-radius-sm);
        background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);
        font-size: 9px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; cursor: pointer;
        display: flex; flex-direction: column; align-items: center; gap: 4px;
    }
    .nf2-btn-mini i { font-size: 14px; }
    .nf2-btn-mini:hover { filter: brightness(1.15); }
    /* Colores permanentes (no solo al hover) para identificar la acción de un vistazo */
    .nf2-btn-mini.info { background: rgba(13,202,240,.12); border-color: var(--info); color: var(--info); }
    .nf2-btn-mini.warn { background: rgba(255,193,7,.12); border-color: var(--warning); color: var(--warning); }
    .nf2-btn-mini.danger { background: rgba(220,53,69,.12); border-color: var(--danger); color: var(--danger); }

    /* Botón "Asociar comprobante": llamativo mientras falta asociar, verde cuando ya se asoció */
    .nf2-icon-btn.nf2-asociar-pend {
        background: rgba(255,193,7,.15); border-color: var(--warning); color: var(--warning);
        animation: nf2-pulse 1.4s ease-in-out infinite;
    }
    .nf2-icon-btn.nf2-asociar-ok { background: rgba(16,185,129,.15); border-color: #10b981; color: #10b981; animation: none; }
    @keyframes nf2-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .5; } }

    .nf2-asoc-warning {
        display: none; align-items: center; gap: 8px; padding: 8px 14px; margin-top: -2px;
        background: rgba(255,193,7,.1); border: 1px solid var(--warning); border-radius: var(--nf2-radius-sm);
        color: var(--warning); font-size: 11px; font-weight: 700;
    }
    .nf2-asoc-warning.show { display: flex; }

    .nf2-in-modal {
        background: var(--input-bg) !important; border: 1px solid var(--input-border) !important;
        color: var(--text-main) !important; border-radius: 8px !important;
    }

    /* Tablet: una sola columna y sin paneles que compitan con la venta. */
    @media (max-width: 900px) {
        .nf2 {
            grid-template-columns: minmax(0, 1fr);
            height: auto;
            min-height: 100%;
            align-content: start;
            overflow: visible;
        }
        .nf2-main,
        .nf2-side { height: auto; min-height: 0; }
        .nf2-preview { display: none; }
        .nf2-cliente-card { flex: none; max-height: 210px; }
        .nf2-list-wrap { flex: none; min-height: 280px; max-height: 52vh; }
        .nf2-summary { flex: none; }
    }

    /* Teléfono: controles en filas completas y tarjetas sin desbordes. */
    @media (max-width: 600px) {
        #main-content {
            height: calc(100vh - 54px) !important;
            padding: 6px !important;
        }
        .nf2 {
            --nf2-radius: 8px;
            --nf2-radius-sm: 7px;
            gap: 8px;
            padding: 2px;
        }
        .nf2-main,
        .nf2-side { gap: 8px; }
        .nf2-card { box-shadow: 0 3px 10px rgba(0,0,0,.16); }

        .nf2-topbar {
            align-items: stretch;
            gap: 9px;
            padding: 10px;
        }
        .nf2-cliente-field {
            flex: 1 0 100%;
            width: 100%;
            min-width: 0;
        }
        .nf2-cliente-field > div { min-width: 0; }
        .nf2-cliente-field .select2-container { min-width: 0; width: auto !important; flex: 1 1 0; }
        .nf2-tipo-field { width: calc(55% - 5px); }
        .nf2-num-field {
            width: calc(45% - 5px);
            display: flex !important;
            justify-content: center;
        }
        .nf2-num-field .num { font-size: 13px; white-space: nowrap; }
        .nf2-topbar > .nf2-icon-btn { align-self: center; }
        .nf2-badge {
            flex: 1 1 auto;
            margin-left: 0;
            justify-content: center;
            align-items: center;
        }
        .nf2-fiscal { font-size: 9px; }

        .nf2-searchbar {
            flex-wrap: wrap;
            gap: 8px;
            padding: 9px;
        }
        .nf2-qty { width: 58px; }
        .nf2-cod-exacto { width: calc(100% - 66px); flex: 1 1 auto; }
        .nf2-search-wrap { flex: 1 0 100%; width: 100%; }
        .nf2-lista { width: calc(100% - 42px); flex: 1 1 auto; }
        .nf2-searchbar > .nf2-icon-btn { width: 34px; flex: 0 0 34px; }

        .nf2-list-toolbar { gap: 6px; }
        .nf2-toggle-vista { padding: 5px 8px; }
        .nf2-list-wrap { min-height: 250px; max-height: none; overflow: visible; }
        .nf2-empty-list { min-height: 220px; }
        .nf2-table-wrap.show { overflow-x: auto; }
        .nf2-table { min-width: 940px; }

        .nf2-item-card {
            flex-wrap: wrap;
            gap: 7px;
            padding: 9px;
        }
        .nf2-thumb { width: 42px; height: 42px; }
        .nf2-item-info { flex: 1 1 calc(100% - 52px); }
        .nf2-item-desc { white-space: normal; line-height: 1.2; }
        .nf2-item-qty { width: 48px; }
        .nf2-item-desc-pct { width: 48px; }
        .nf2-item-final-input { width: 82px; }
        .nf2-item-total { width: auto; min-width: 64px; flex: 1 1 64px; }
        .nf2-item-remove { margin-left: auto; }

        #nf2_cliente_card { display: none; }
        .nf2-summary { padding: 12px; }
        .nf2-total-val { font-size: 28px; }
        .nf2-btn-cobrar { min-height: 48px; }
        .nf2-btn-mini { min-height: 50px; }

        .modal-dialog { margin: 8px; }
        .modal-body { max-height: 68vh; overflow-y: auto; }
        .modal-footer { flex-wrap: wrap; }
    }
</style>

<div class="nf2 fade-in" id="nf2Shell">
    <div class="nf2-main">

        <!-- Barra superior: cliente / tipo / numero -->
        <div class="nf2-card nf2-topbar">
            <div class="nf2-field nf2-cliente-field">
                <span class="lbl">Cliente (F3)</span>
                <div style="display:flex; gap:6px;">
                    <select id="sel_cliente" style="flex:1"></select>
                    <button type="button" class="nf2-icon-btn" onclick="bill.abrirBuscadorCliente()" title="Buscador ampliado"><i class="fas fa-search"></i></button>
                    <?php if ($puedeCrearCliente): ?>
                    <button type="button" class="nf2-icon-btn nf2-btn-nuevo-cli" onclick="bill.abrirNuevoCliente()" title="Cargar cliente nuevo"><i class="fas fa-user-plus"></i></button>
                    <?php endif; ?>
                    <button type="button" class="nf2-icon-btn nf2-btn-ficha" onclick="bill.verFicha()" title="Ficha del cliente"><i class="fas fa-id-card"></i></button>
                </div>
            </div>
            <div class="nf2-field nf2-tipo-field">
                <span class="lbl">Tipo comprobante</span>
                <select id="sel_tipo" class="nf2-lista" style="width:100%;"></select>
            </div>
            <div class="nf2-field nf2-num-field">
                <span class="lbl">Número</span>
                <span class="num" id="bill_nro_label">0000-00000000</span>
            </div>
            <button type="button" class="nf2-icon-btn" id="btnAsociarTrigger" style="display:none;" onclick="bill.abrirAsociar()" title="Asociar comprobante"><i class="fas fa-link"></i></button>
            <div class="nf2-badge">
                <span class="nf2-fiscal" id="badge_fiscal" style="color:var(--accent);">CONSUMIDOR FINAL</span>
            </div>
        </div>

        <!-- Aviso: este tipo de comprobante necesita un comprobante asociado y todavía no se cargó -->
        <div class="nf2-asoc-warning" id="nf2_asoc_warning">
            <i class="fas fa-triangle-exclamation"></i>
            <span>Este comprobante necesita un documento asociado. Hacé clic en el ícono de enlace de arriba para asociarlo.</span>
        </div>

        <!-- Buscador principal -->
        <div class="nf2-card nf2-searchbar">
            <input type="number" id="in_cant" class="nf2-qty" value="1" title="Cantidad">
            <input type="text" id="in_cod_exacto" class="nf2-cod-exacto" placeholder="CÓDIGO (ESCANEO)" title="Código exacto: ENTER agrega, o avisa si no existe" autocomplete="off">
            <div class="nf2-search-wrap">
                <select id="in_buscar" style="width:100%;"></select>
            </div>
            <select id="in_lista" class="nf2-lista" title="Lista de precios"></select>
            <button type="button" class="nf2-icon-btn" onclick="bill.abrirBuscadorArticulo()" title="Buscador ampliado"><i class="fas fa-list"></i></button>
        </div>

        <!-- Lista de items -->
        <div class="nf2-list-toolbar">
            <span class="nf2-list-count" id="nf2_list_count">0 ítems</span>
            <button type="button" class="nf2-toggle-vista" onclick="bill.toggleVistaTabla()"><i class="fas fa-table-cells me-1"></i><span id="lbl_toggle_vista">Ver planilla contable</span></button>
        </div>

        <div class="nf2-card nf2-list-wrap">
            <div id="nf2_cards"></div>
            <div class="nf2-table-wrap" id="nf2_table_wrap">
                <table class="nf2-table">
                    <thead id="grid_head"></thead>
                    <tbody id="grid_body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Columna lateral -->
    <div class="nf2-side">
        <div class="nf2-card nf2-preview" id="nf2_preview">
            <div class="nf2-preview-img" id="nf2_preview_img"><i class="fas fa-box-open"></i></div>
            <div class="nf2-preview-title" id="nf2_preview_title">Pasá el mouse sobre un ítem</div>
            <div class="nf2-preview-sub" id="nf2_preview_sub">para ver su foto y precio</div>
            <div class="nf2-preview-price" id="nf2_preview_price"></div>
        </div>

        <div class="nf2-card nf2-cliente-card" id="nf2_cliente_card">
            <div class="nf2-cliente-empty" id="nf2_cliente_empty">
                <i class="fas fa-user-circle"></i>
                <span>Seleccioná un cliente (F3) para ver su ficha acá</span>
            </div>
            <div id="nf2_cliente_info" style="display:none;">
                <div class="nf2-cliente-nombre" id="nf2_cli_nombre"></div>
                <div class="nf2-cliente-doc" id="nf2_cli_doc"></div>
                <div class="nf2-cliente-rows">
                    <div class="nf2-cliente-row"><span>Condición</span><b id="nf2_cli_cond"></b></div>
                    <div class="nf2-cliente-row"><span>Cta. Cte.</span><b id="nf2_cli_ctacte"></b></div>
                    <div class="nf2-cliente-row"><span>Lista</span><b id="nf2_cli_lista"></b></div>
                    <div class="nf2-cliente-row"><span>Domicilio</span><b id="nf2_cli_domicilio"></b></div>
                </div>
            </div>
        </div>

        <div class="nf2-card nf2-summary">
            <div class="nf2-sum-row"><span>Neto</span><b id="tx_neto_f">0,00</b></div>
            <div class="nf2-sum-row"><span>IVA</span><b id="tx_iva_f">0,00</b></div>
            <div class="nf2-sum-row"><span>Impuestos internos</span><b id="tx_ii_final_total">0,00</b></div>
            <div class="nf2-sum-row" id="btn_desc_global"><span>Descuento</span><b style="color:var(--warning)">
                <span id="tx_desc_g">0,00</span><i class="fas fa-edit nf2-sum-adj-edit" onclick="bill.modalGlobal('DESC')"></i></b></div>
            <div class="nf2-sum-row" id="btn_reca_global"><span>Recargo</span><b style="color:var(--info)">
                <span id="tx_reca_g">0,00</span><i class="fas fa-edit nf2-sum-adj-edit" onclick="bill.modalGlobal('RECA')"></i></b></div>

            <div class="nf2-sum-detail-toggle" onclick="bill.toggleDetalleImpuestos()"><span id="lbl_ver_detalle">Ver detalle de impuestos</span></div>
            <div class="nf2-sum-detail" id="tax_matrix_detalle">
                <div class="nf2-sum-row"><span>Sub. 21%</span><b id="tx_n21_1">0,00</b></div>
                <div class="nf2-sum-row"><span>Sub. 10.5%</span><b id="tx_n105_1">0,00</b></div>
            </div>

            <div class="nf2-total-row">
                <div class="nf2-sum-row mb-1"><span>Unidades</span><b id="tx_total_unidades">0</b></div>
                <div class="nf2-total-lbl">Total comprobante</div>
                <div class="nf2-total-val" id="tx_total_f">0,00</div>
            </div>

            <button type="button" class="nf2-btn-cobrar" id="btnCobrarTrigger" onclick="bill.intentarCobro()">
                <i class="fas fa-cash-register"></i>Cobrar (F10)
            </button>

            <div class="nf2-toolbar-side">
                <button type="button" class="nf2-btn-mini info" onclick="bill.nueva()"><i class="fas fa-file"></i>Nuevo (F4)</button>
                <button type="button" class="nf2-btn-mini warn" onclick="bill.abrirLineaManual()"><i class="fas fa-keyboard"></i>Manual (F8)</button>
                <button type="button" class="nf2-btn-mini warn" id="btnTogglePreciosOrigen" style="display:none;" onclick="bill.toggleUsarPreciosActuales()">
                    <i class="fas fa-tag"></i>Usando: Precio GUARDADO
                </button>
                <button type="button" class="nf2-btn-mini danger" onclick="bill.nueva()"><i class="fas fa-times"></i>Anular</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_manual_line" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info border-opacity-50">
            <div class="modal-header bg-info bg-opacity-10">
                <h6 class="modal-title fw-bold text-info"><i class="fas fa-keyboard me-2"></i>AGREGAR FILA MANUAL</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-custom">CANTIDAD</label>
                        <input type="number" id="ml_cant" class="form-control nf2-in-modal fw-bold text-center" value="1" min="1">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label-custom">ALICUOTA DE IVA</label>
                        <select id="ml_iva" class="form-select nf2-in-modal">
                            <option value="0">0%</option>
                            <option value="10.5">10.5%</option>
                            <option value="21" selected>21%</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">DETALLE / DESCRIPCIÓN</label>
                        <input type="text" id="ml_det" class="form-control nf2-in-modal" placeholder="Ingrese descripción del ítem...">
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom text-accent">IMPORTE (IVA INCLUIDO)</label>
                        <input type="number" step="0.01" id="ml_total" class="form-control nf2-in-modal text-end fw-bold fs-5" placeholder="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button class="btn btn-danger fw-bold px-3" data-bs-dismiss="modal">CANCELAR (ESC)</button>
                <button class="btn btn-success fw-bold flex-grow-1" onclick="bill.confirmarLineaManual()">
                    <i class="fas fa-plus me-2"></i>AGREGAR (ENTER)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_asociar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-accent fw-bold"><i class="fas fa-link me-2"></i>ASOCIAR COMPROBANTE</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-4">
                        <label class="form-label-custom">P. DE VENTA</label>
                        <input type="text" id="asoc_pv" class="form-control nf2-in-modal text-center fw-bold" disabled>
                    </div>
                    <div class="col-8">
                        <label class="form-label-custom">N° DE COMPROBANTE</label>
                        <input type="text" id="asoc_num" class="form-control nf2-in-modal text-center fw-bold" placeholder="00000000">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">TIPO FACTURA ORIGINAL</label>
                    <input type="text" id="asoc_tipo_txt" class="form-control nf2-in-modal text-center fw-bold opacity-75" readonly>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label-custom">FECHA COMPROBANTE</label>
                        <input type="date" id="asoc_fecha" class="form-control nf2-in-modal">
                    </div>
                    <div class="col-6 d-flex align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="asoc_check_pv" onchange="document.getElementById('asoc_pv').disabled = !this.checked">
                            <label class="form-check-label text-white-50" style="font-size: 10px;" for="asoc_check_pv">EDITAR PV</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR (ESC)</button>
                <button class="btn btn-info rounded-pill px-4 fw-bold" onclick="bill.confirmarAsociacion()"><i class="fas fa-check me-2"></i>ASOCIAR</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_nuevo_cliente" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-success border-opacity-50">
            <div class="modal-header bg-success bg-opacity-10">
                <h6 class="modal-title fw-bold text-success"><i class="fas fa-user-plus me-2"></i>CLIENTE NUEVO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label-custom">RAZÓN SOCIAL</label>
                        <input type="text" id="nc_razon" class="form-control nf2-in-modal fw-bold" placeholder="NOMBRE DEL CLIENTE">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">TIPO DOC</label>
                        <select id="nc_tipodoc" class="form-select nf2-in-modal">
                            <option value="DNI">DNI</option>
                            <option value="CUIT">CUIT</option>
                            <option value="CUIL">CUIL</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label-custom">NRO DOCUMENTO</label>
                        <div class="input-group">
                            <input type="text" id="nc_numdoc" class="form-control nf2-in-modal">
                            <button type="button" class="btn btn-outline-info" id="btnAutocompletarCuitNC" title="Autocompletar por CUIT (consulta el padrón de AFIP)">
                                <i class="fas fa-magic"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label-custom">CONDICIÓN FISCAL</label>
                        <select id="nc_condicionfiscal" class="form-select nf2-in-modal">
                            <option value="CONSUMIDOR FINAL">CONSUMIDOR FINAL</option>
                            <option value="RESPONSABLE INSCRIPTO">RESPONSABLE INSCRIPTO</option>
                            <option value="MONOTRIBUTO">MONOTRIBUTO</option>
                            <option value="EXENTO">EXENTO</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label-custom">LISTA PRECIOS</label>
                        <select id="nc_lista" class="form-select nf2-in-modal">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>LISTA <?= $i ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">DOMICILIO</label>
                        <input type="text" id="nc_domicilio" class="form-control nf2-in-modal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">LOCALIDAD</label>
                        <input type="text" id="nc_localidad" class="form-control nf2-in-modal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">PROVINCIA</label>
                        <input type="text" id="nc_provincia" class="form-control nf2-in-modal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">TELÉFONO</label>
                        <input type="text" id="nc_telefono" class="form-control nf2-in-modal">
                    </div>
                    <div class="col-md-6 d-flex align-items-end pb-1">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="nc_ctacte">
                            <label class="form-check-label text-white-50" style="font-size: 10px;" for="nc_ctacte">HABILITAR CTA. CTE.</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button class="btn btn-danger fw-bold px-3" data-bs-dismiss="modal">CANCELAR (ESC)</button>
                <button class="btn btn-success fw-bold flex-grow-1" onclick="bill.confirmarNuevoCliente()">
                    <i class="fas fa-check me-2"></i>GUARDAR Y SELECCIONAR
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_search_cli" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">BUSCAR CLIENTE</h6>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2">
                    <input type="text" id="in_mod_cli_q" class="form-control nf2-in-modal" placeholder="CUIT o Razón...">
                </div>
                <div style="max-height:400px;overflow-y:auto">
                    <table class="nf2-table w-100">
                        <thead class="sticky-top"><tr><th>RAZON</th><th>CUIT</th><th>DOMICILIO</th><th></th></tr></thead>
                        <tbody id="tbody_mod_cli"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">CANCELAR (ESC)</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_search_art" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">BUSCAR ARTÍCULO</h6>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2">
                    <input type="text" id="in_mod_art_q" class="form-control nf2-in-modal" placeholder="Filtrar...">
                </div>
                <div style="max-height:450px;overflow-y:auto">
                    <table class="nf2-table w-100">
                        <thead class="sticky-top"><tr><th width="50"></th><th width="110">COD</th><th>DESCRIPCIÓN</th><th>RUBRO</th><th class="text-end">NETO</th><th class="text-end">FINAL</th><th width="40"></th></tr></thead>
                        <tbody id="tbody_mod_art"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">CANCELAR (ESC)</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_pay" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold text-accent">COBRO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-3 p-3 rounded border border-accent" style="background-color: var(--bg-card);">
                    <div style="font-size:9px;font-weight:800;color:var(--text-muted);text-transform:uppercase;">TOTAL</div>
                    <div class="nf2-total-val" id="mod_pay_total">$ 0,00</div>
                    <div class="small text-warning d-none mt-1" id="mod_pay_percep"></div>
                </div>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label-custom">MEDIO 1</label>
                        <div class="input-group input-group-sm">
                            <select id="pay_m1" class="form-select nf2-in-modal" style="flex:2"></select>
                            <input type="text" inputmode="decimal" id="pay_i1" class="form-control nf2-in-modal text-end fw-bold text-success" style="flex:1">
                        </div>
                        <div class="d-none mt-1 pay-tarjeta" id="pay_t1">
                            <div class="row g-1">
                                <div class="col-5"><select id="pay_t1_id" class="form-select form-select-sm nf2-in-modal" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="pay_t1_cuotas" class="form-control form-control-sm nf2-in-modal text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-2"><input type="text" id="pay_t1_lote" class="form-control form-control-sm nf2-in-modal" maxlength="20" placeholder="Lote" title="Lote (opcional)"></div>
                                <div class="col-3"><input type="text" id="pay_t1_cupon" class="form-control form-control-sm nf2-in-modal" maxlength="30" placeholder="Cupón" title="Número de cupón (opcional)"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label-custom">MEDIO 2</label>
                        <div class="input-group input-group-sm">
                            <select id="pay_m2" class="form-select nf2-in-modal" style="flex:2"></select>
                            <input type="text" inputmode="decimal" id="pay_i2" class="form-control nf2-in-modal text-end fw-bold text-warning" style="flex:1" value="0.00">
                        </div>
                        <div class="d-none mt-1 pay-tarjeta" id="pay_t2">
                            <div class="row g-1">
                                <div class="col-5"><select id="pay_t2_id" class="form-select form-select-sm nf2-in-modal" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="pay_t2_cuotas" class="form-control form-control-sm nf2-in-modal text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-2"><input type="text" id="pay_t2_lote" class="form-control form-control-sm nf2-in-modal" maxlength="20" placeholder="Lote" title="Lote (opcional)"></div>
                                <div class="col-3"><input type="text" id="pay_t2_cupon" class="form-control form-control-sm nf2-in-modal" maxlength="30" placeholder="Cupón" title="Número de cupón (opcional)"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 d-none" id="pay_balance_helper">
                        <div class="p-3 rounded border border-accent" style="background-color: var(--bg-card);">
                            <div class="d-flex justify-content-between"><span class="fw-bold">FALTA POR COBRAR</span><strong class="text-danger fs-5" id="pay_falta">$ 0,00</strong></div>
                            <div class="d-flex justify-content-between"><span class="fw-bold">VUELTO A ENTREGAR</span><strong class="text-success fs-5" id="pay_sobra">$ 0,00</strong></div>
                        </div>
                    </div>
                    <div class="col-12 mt-3"><label class="form-label-custom">VENDEDOR</label>
                        <select id="pay_v" class="form-select nf2-in-modal"></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button type="button" class="btn btn-danger fw-bold px-3" data-bs-dismiss="modal" style="flex: 0 0 auto;">
                    CANCELAR (ESC)
                </button>
                <button type="button" id="btnEmitir" class="btn btn-success fw-bold flex-grow-1 py-2" onclick="bill.grabar()">
                    EMITIR (F10)
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="mod_foto_grande" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:var(--bg-modal); border:1px solid var(--border-color);">
            <div class="modal-header" style="border-color:var(--border-color);">
                <h6 class="modal-title fw-bold" id="foto_grande_titulo" style="color:var(--text-titles);"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="foto_grande_img" src="" alt=""
                     style="max-width:100%; max-height:70vh; border-radius:10px; object-fit:contain;"
                     onerror="this.style.display='none'; document.getElementById('foto_grande_sin_imagen').style.display='block';"
                     onload="document.getElementById('foto_grande_sin_imagen').style.display='none';">
                <div id="foto_grande_sin_imagen" style="display:none; color:var(--text-muted); padding:40px 0;">
                    <i class="fas fa-image fa-3x mb-3 d-block"></i>Sin imagen disponible para este producto
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // La carga de pedido/presupuesto ocurre en bill.init() leyendo
    // ?pedidoid=/?presupuestoid= de la URL (igual que facturar.js).
    // En window (no const): newfactura.js los lee como window.NF2_*, y una const de nivel superior
    // no se ve ahí; además, al volver a abrir el módulo, redeclarar una const tira error.
    window.NF2_PUEDE_CREAR_CLIENTE = <?php echo $puedeCrearCliente ? 'true' : 'false'; ?>;
    window.NF2_NC_ORIGEN_ID = <?php echo (int) $ncOrigenId; ?>;
    window.NF2_PUEDE_EDITAR_DESC = <?php echo $puedeEditarDescFacturar ? 'true' : 'false'; ?>;
    window.NF2_PUEDE_IMPRIMIR_ENVIOS = <?php echo $puedeImprimirEnvios ? 'true' : 'false'; ?>;
</script>
<script src="facturar_validaciones.js?v=<?php echo time(); ?>"></script>
<script src="newfactura.js?v=<?php echo time(); ?>"></script>
