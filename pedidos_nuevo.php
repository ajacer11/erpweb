<?php
/**
 * pedidos_nuevo.php - FORMULARIO DE ALTA / EDICIÓN DE PEDIDOS
 * Versión 3.5 (Con Responsive UI Mobile nativo - Sin alterar lógica JS)
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
require_once 'permisos_helpers.php';
$hoy = date('Y-m-d');
$pedidoId = isset($_GET['id']) ? intval($_GET['id']) : 0;

$configRow = $pdo->query("SELECT permitireditardescripcion FROM configuracion ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$puedeEditarDescPedidos = ((int) ($configRow['permitireditardescripcion'] ?? 0) === 1) && puedeEditarDescripcionPedidos();
?>

<style>
    /* --- CORE STYLES (Preservados 100%) --- */
    .form-label-custom { font-size: 0.75rem; margin-bottom: 2px; color: #adb5bd; font-weight: 600; }
    
    /* Layout */
    .layout-wrapper { display: flex; flex-direction: column; height: calc(100vh - 85px); overflow: hidden; position: relative; }
    .grid-container { flex-grow: 1; overflow-y: auto; background: #000a1a; border: 1px solid #445566; border-radius: 6px; }

    /* Totales Panel */
    .card-totales {
        background-color: #0d1b2a !important;
        border: 1px solid #445566;
        color: white;
        margin-top: auto;
    }
    /* En modo claro este panel quedaba ilegible: stylesglobal.css adapta
       .text-white y .text-white-50 al tema (pasan a texto oscuro), pero el fondo
       de acá estaba clavado con !important, así que daba texto oscuro sobre
       fondo oscuro. Sólo se corrige el tema claro; el oscuro queda igual. */
    [data-theme="light"] .card-totales {
        background-color: var(--bg-card) !important;
        border-color: var(--border-color);
        color: var(--text-main);
    }
    .lbl-total-main { font-size: 1.4rem; color: var(--accent); }

    /* Badge promoción en grilla */
    .badge-promo {
        font-size: 8px; font-weight: 900; letter-spacing: .4px;
        padding: 1px 5px; border-radius: 10px; text-transform: uppercase;
        background: #1a3a2a; color: #4ade80; border: 1px solid #16a34a;
        display: inline-block; white-space: nowrap; vertical-align: middle;
        margin-left: 3px;
    }

    /* Foto del producto al pasar el mouse (como en ventas). Flota junto al puntero. */
    .ped-foto-pop {
        position: fixed; z-index: 2000; width: 230px; pointer-events: none;
        background: var(--bg-card, #0d1b2a); border: 1px solid var(--border-color, #445566); border-radius: 10px;
        box-shadow: 0 10px 30px rgba(0,0,0,.55); padding: 8px; display: none;
    }
    .ped-foto-pop.visible { display: block; }
    .ped-foto-pop .pf-img {
        height: 200px; border-radius: 8px; overflow: hidden; background: #fff;
        display: flex; align-items: center; justify-content: center;
    }
    .ped-foto-pop .pf-img img { width: 100%; height: 100%; object-fit: contain; }
    .ped-foto-pop .pf-img i { font-size: 40px; color: #94a3b8; }
    .ped-foto-pop .pf-tit { font-size: 12px; font-weight: 700; color: var(--text-main, #fff); margin-top: 6px; line-height: 1.25;
        display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ped-foto-pop .pf-sub { font-size: 10.5px; color: var(--text-muted, #94a3b8); }
    #gridPedidos td.celda-cod { cursor: zoom-in; }
    .ped-thumb { width: 44px; height: 44px; flex-shrink: 0; border-radius: 8px; overflow: hidden; background: #fff;
        display: flex; align-items: center; justify-content: center; cursor: zoom-in; border: 1px solid var(--border-color, #445566); }
    .ped-thumb img { width: 100%; height: 100%; object-fit: contain; }
    .ped-thumb i { color: #94a3b8; }
    @media (hover: none) { .ped-foto-pop { display: none !important; } }

    /* Control de preparacion (se guarda en lineapedidosclientes.preparado) */
    .chk-preparado { width: 18px; height: 18px; cursor: pointer; accent-color: #22c55e; vertical-align: middle; }
    #gridPedidos tr.item-preparado td { background-color: rgba(34,197,94,0.15) !important; }
    #gridPedidos tr.item-preparado td:nth-child(3) { text-decoration: line-through; }
    .erp-card.item-preparado { border-color: #16a34a !important; background-color: rgba(34,197,94,0.12) !important; }

    /* Inputs Grilla */
    .select-grid { height: 100%; width: 100%; background: transparent; color: white; border: none; appearance: none; text-align: center; cursor: pointer; }
    .select-grid:focus { background: #222; outline: none; }
    .select-grid option { background: #000; color: white; }
    .input-grid { background: transparent; border: none; color: white; font-weight: bold; width: 100%; text-align: center; font-size: 0.85rem; }
    .input-grid:focus { background: #222; border: 1px solid var(--accent); outline: none; }

    /* --- RESPONSIVE MOBILE --- */
    @media (max-width: 768px) {
        .layout-wrapper { height: auto; min-height: 100vh; overflow-y: auto; overflow-x: hidden; padding-bottom: 220px; display: block; }

        .card-totales { 
            position: fixed; bottom: 0; left: 0; width: 100%; z-index: 9999; 
            border-radius: 15px 15px 0 0; 
            box-shadow: 0 -5px 20px rgba(0,0,0,0.8);
            background-color: #0b1021 !important; 
            border-top: 2px solid var(--accent);
            padding: 15px; padding-bottom: 20px;
            margin-top: 0;
        }
        /* Mismo caso que arriba: en mobile el panel fija otro fondo oscuro. */
        [data-theme="light"] .card-totales {
            background-color: var(--bg-card) !important;
        }
        .lbl-total-main { font-size: 1.5rem; text-align: right; display: block; }
    }
</style>

<div class="container-fluid px-3 py-2 fade-in layout-wrapper" id="moduloPedidos">
    <input type="hidden" id="hdnEditPedidoId" value="<?php echo $pedidoId; ?>">
    
    <!-- HEADER (Corregido con flex-wrap para Mobile) -->
    <div class="d-flex justify-content-between align-items-center mb-2 flex-shrink-0 flex-wrap gap-2">
        <div>
            <h5 class="text-white mb-0">
                <i class="fas fa-shopping-cart me-2 text-accent"></i>
                <span id="titleLabel"><?php echo ($pedidoId > 0) ? "EDITANDO PEDIDO #$pedidoId" : "NUEVO PEDIDO"; ?></span>
            </h5>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-secondary btn-sm rounded-pill px-3 shadow-sm" onclick="limpiarPedido()">
                <i class="fas fa-eraser me-1"></i> <span class="d-none d-sm-inline">LIMPIAR</span>
            </button>
            <button class="btn btn-success btn-sm fw-bold rounded-pill px-4 shadow-sm" onclick="guardarPedido()">
                <i class="fas fa-save me-1"></i> 
                <span class="d-none d-sm-inline"><?php echo ($pedidoId > 0) ? "ACTUALIZAR" : "GUARDAR"; ?> (F10)</span>
                <span class="d-inline d-sm-none"><?php echo ($pedidoId > 0) ? "ACTUALIZAR" : "GUARDAR"; ?></span>
            </button>
        </div>
    </div>

    <!-- DATOS GENERALES -->
    <div class="card card-filtros p-2 mb-2 border-secondary bg-dark bg-opacity-25 flex-shrink-0">
        <div class="row g-2">
            <div class="col-6 col-lg-2">
                <label class="form-label-custom">FECHA</label>
                <input type="date" id="txtFecha" class="form-control form-control-sm" value="<?php echo $hoy; ?>">
            </div>
            <div class="col-6 col-lg-2">
                <label class="form-label-custom text-info">SALDO</label>
                <input type="text" id="txtSaldoCliente" class="form-control form-control-sm fw-bold bg-dark text-info text-end border-0" readonly value="$ 0.00">
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label-custom">CLIENTE</label>
                <select id="cmbCliente" class="form-control select2-simple"></select>
            </div>
            <div class="col-12 col-lg-4">
                <label class="form-label-custom">VENDEDOR</label>
                <select id="cmbVendedor" class="form-control select2-simple"></select>
            </div>
            
            <div class="col-6 col-lg-2">
                <label class="form-label-custom">LISTA GLOBAL</label>
                <select id="cmbListaGlobal" class="form-select form-select-sm bg-dark text-white border-secondary">
                    <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>Lista $i</option>"; ?>
                </select>
            </div>
            <div class="col-6 col-lg-2">
                 <label class="form-label-custom text-warning">DESC. MASIVO %</label>
                 <input type="number" id="txtDescGral" class="form-control form-control-sm text-end fw-bold text-warning border-secondary" value="0">
            </div>
            <div class="col-12 col-lg-8">
                <label class="form-label-custom">OBSERVACIONES</label> 
                <input type="text" id="txtObsPedido" class="form-control form-control-sm bg-dark text-white-50 border-secondary" placeholder="Instrucciones...">
            </div>
        </div>
    </div>

    <!-- PANEL CARGA (Preservado v3.4) -->
    <div class="card card-filtros p-2 mb-2 bg-black border-secondary flex-shrink-0">
        <div class="row g-1 align-items-end">
            <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label-custom text-accent">CÓDIGO (F2)</label>
                <div class="input-group input-group-sm">
                    <input type="text" id="txtProdCodigo" class="form-control fw-bold text-white border-secondary" placeholder="Cod.">
                    <button class="btn btn-outline-secondary border-secondary" onclick="abrirModalProductos('')"><i class="fas fa-search"></i></button>
                </div>
            </div>
            <div class="col-12 col-md-5 col-lg-4">
                <label class="form-label-custom">DESCRIPCIÓN</label>
                <input type="text" id="txtProdDesc" class="form-control form-control-sm bg-dark text-white-50 border-0" readonly>
            </div>
            <div class="col-6 col-md-2 col-lg-1">
                <label class="form-label-custom">LISTA</label>
                <select id="cmbProdLista" class="form-select form-select-sm bg-dark text-white border-secondary ps-1 pe-0">
                    <?php for($i=1; $i<=8; $i++) echo "<option value='$i'>L$i</option>"; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-1">
                <label class="form-label-custom text-center w-100">CANT.</label>
                <input type="number" id="txtProdCant" class="form-control form-control-sm fw-bold text-center text-white bg-dark border-secondary" value="1">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label-custom text-end w-100">PRECIO</label>
                <input type="text" id="txtProdPrecio" class="form-control form-control-sm text-end bg-dark text-info border-0" readonly>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="form-label-custom text-end w-100">DESC%</label>
                <input type="number" id="txtProdDescUnit" class="form-control form-control-sm text-end bg-dark text-white border-secondary" value="0">
            </div>
            <div class="col-12 col-md-6 col-lg-1">
                <button class="btn btn-sm btn-info w-100 fw-bold" onclick="agregarItem()" id="btnAgregarItem">AGREGAR</button>
            </div>
            <div class="col-12 col-md-6 col-lg-1">
                <button type="button" class="btn btn-sm btn-warning w-100 fw-bold" onclick="abrirLineaManual()"><i class="fas fa-keyboard"></i> MANUAL</button>
            </div>
        </div>
        <input type="hidden" id="hdnProdId"><input type="hidden" id="hdnProdImpInt">
        <input type="hidden" id="hdnProdIVA"><input type="hidden" id="hdnProdRubro">
        <input type="hidden" id="hdnPrecioRaw"><input type="hidden" id="hdnCachePrecios"> 
        <input type="hidden" id="txtProdTela"><input type="hidden" id="txtProdColor">
    </div>

    <!-- BUSCADOR DENTRO DEL PEDIDO + PROGRESO DE PREPARACION -->
    <div class="d-flex align-items-center gap-2 mb-2 flex-shrink-0 flex-wrap">
        <div class="input-group input-group-sm" style="max-width: 380px;">
            <span class="input-group-text bg-dark text-white-50 border-secondary"><i class="fas fa-search"></i></span>
            <input type="text" id="txtFiltroItems" class="form-control bg-dark text-white border-secondary" placeholder="Buscar en el pedido (código o descripción)..." autocomplete="off">
            <button class="btn btn-outline-secondary" type="button" onclick="limpiarFiltroItems()" title="Limpiar"><i class="fas fa-times"></i></button>
        </div>
        <div class="form-check form-switch mb-0 small">
            <input class="form-check-input" type="checkbox" id="chkOcultarPreparados" onchange="aplicarFiltroItems()">
            <label class="form-check-label text-white-50" for="chkOcultarPreparados">Ocultar preparados</label>
        </div>
        <span class="small text-white-50">Preparados: <span id="lblPreparados" class="text-success fw-bold">0/0</span></span>
        <span class="small text-warning d-none" id="lblFiltroInfo"></span>
    </div>

    <!-- GRILLA DESKTOP -->
    <div class="erp-table-wrap grid-container mb-2">
        <table class="table table-dark table-sm table-hover mb-0" id="gridPedidos">
            <thead class="bg-black sticky-top border-bottom border-secondary">
                <tr>
                    <th class="text-center text-success" rowspan="2" style="width: 34px;" title="Preparado en caja (se guarda automáticamente)"><i class="fas fa-box"></i></th>
                    <th class="ps-2 text-white-50" rowspan="2" style="width: 80px;">CÓDIGO</th>
                    <th class="text-white-50" rowspan="2">DESCRIPCIÓN</th>
                    <th class="text-center text-white-50" rowspan="2" style="width: 50px;">LISTA</th>
                    <th class="text-center border-start border-end border-secondary text-info" colspan="4">UNITARIO</th>
                    <th class="text-center border-end border-secondary text-white" rowspan="2" style="width: 50px;">CANT.</th>
                    <th class="text-center border-end border-secondary text-warning" rowspan="2" style="width: 50px;">DESC%</th>
                    <th class="text-center border-end border-secondary text-success" colspan="4">TOTAL LÍNEA</th>
                    <th class="text-center" rowspan="2" style="width: 30px;"></th>
                </tr>
                <tr>
                    <th class="text-end text-muted fw-normal border-start border-secondary">P.List</th>
                    <th class="text-end text-muted fw-normal">Neto</th>
                    <th class="text-end text-muted fw-normal">IVA</th>
                    <th class="text-end text-info border-end border-secondary">Final</th>
                    <th class="text-end text-muted fw-normal">Neto</th>
                    <th class="text-end text-muted fw-normal">IVA</th>
                    <th class="text-end text-muted fw-normal">Imp.I</th>
                    <th class="text-end text-success fw-bold pe-2">FINAL</th>
                </tr>
            </thead>
            <tbody id="tbodyPedidos"></tbody>
        </table>
    </div>

    <!-- TARJETAS MOBILE -->
    <div class="erp-cards-wrap grid-container mb-2 p-2" id="tblCards" style="background-color: transparent; border: none;">
        <!-- Se renderizan dinámicamente -->
    </div>

    <!-- PIE TOTALES (Preservado v3.4) -->
    <div class="card card-totales p-2 rounded flex-shrink-0">
        <div class="row align-items-center g-2">
            <div class="col-12 col-md-8 d-none d-md-block text-white-50 small">
                <div class="d-flex gap-3">
                    <span>Items: <span id="lblItems" class="text-white">0</span></span>
                    <span>Cant: <span id="lblCant" class="text-white">0</span></span>
                    <span>Neto: <span id="lblNeto" class="text-white">$0.00</span></span>
                    <span>IVA: <span id="lblIVA" class="text-white">$0.00</span></span>
                    <span>Imp.Int: <span id="lblImpInt" class="text-white">$0.00</span></span>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="text-end w-100">
                    <span class="d-md-none text-muted small me-2">TOTAL PEDIDO:</span>
                    <span id="lblTotal" class="lbl-total-main fw-bold text-accent">$ 0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Búsqueda (Preservado v3.4) -->
<div class="modal fade" id="modalBuscarProd" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-info border-opacity-50 shadow-lg" style="background-color: #001020;">
            <div class="modal-header bg-info bg-opacity-10 py-2">
                <h6 class="modal-title text-white fw-bold small">BUSCAR PRODUCTO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-dark">
                <div class="p-2 border-bottom border-secondary">
                    <input type="text" id="txtBusquedaProd" class="form-control form-control-sm bg-black text-white border-secondary" placeholder="Filtrar..." autocomplete="off">
                </div>
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm small mb-0">
                        <thead class="sticky-top bg-black">
                            <tr><th class="text-start ps-3">CÓDIGO</th><th>DESCRIPCIÓN</th><th>RUBRO</th><th class="text-end">PRECIO</th><th></th></tr>
                        </thead>
                        <tbody id="tbodyResultadosProd"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Foto flotante al pasar el mouse por un producto -->
<div class="ped-foto-pop" id="pedFotoPop">
    <div class="pf-img" id="pedFotoPopImg"></div>
    <div class="pf-tit" id="pedFotoPopTit"></div>
    <div class="pf-sub" id="pedFotoPopSub"></div>
</div>

<!-- Modal Foto grande (clic en el código o en la miniatura) -->
<div class="modal fade" id="mod_foto_pedido" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="background:var(--bg-modal, #001020); border:1px solid var(--border-color, #445566);">
            <div class="modal-header py-2" style="border-color:var(--border-color, #445566);">
                <h6 class="modal-title fw-bold text-white" id="fotoPedidoTitulo"></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center p-4">
                <img id="fotoPedidoImg" src="" alt="" style="max-width:100%; max-height:70vh; border-radius:10px; object-fit:contain;">
                <div id="fotoPedidoSin" class="text-white-50 py-5" style="display:none;"><i class="fas fa-box-open fa-3x mb-2 d-block"></i>Este producto no tiene foto cargada.</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Línea Manual -->
<div class="modal fade" id="mod_manual_line" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning border-opacity-50" style="background-color: #001020;">
            <div class="modal-header bg-warning bg-opacity-10">
                <h6 class="modal-title fw-bold text-warning"><i class="fas fa-keyboard me-2"></i>AGREGAR FILA MANUAL</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label-custom">CANTIDAD</label>
                        <input type="number" id="ml_cant" class="form-control bg-dark text-white border-secondary fw-bold text-center" value="1" min="1">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label-custom">ALÍCUOTA DE IVA</label>
                        <select id="ml_iva" class="form-select bg-dark text-white border-secondary">
                            <option value="0">0%</option>
                            <option value="10.5">10.5%</option>
                            <option value="21" selected>21%</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">DETALLE / DESCRIPCIÓN</label>
                        <input type="text" id="ml_det" class="form-control bg-dark text-white border-secondary" placeholder="Ingrese descripción del ítem...">
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom text-accent">IMPORTE (IVA INCLUIDO)</label>
                        <input type="number" step="0.01" id="ml_total" class="form-control bg-dark text-white border-secondary text-end fw-bold fs-5" placeholder="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button class="btn btn-danger fw-bold px-3" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-success fw-bold flex-grow-1" onclick="confirmarLineaManual()">
                    <i class="fas fa-plus me-2"></i>AGREGAR
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let items = [];
    let searchTimeout = null;
    const pedidoId = parseInt($('#hdnEditPedidoId').val());
    const PUEDE_EDITAR_DESC = <?php echo $puedeEditarDescPedidos ? 'true' : 'false'; ?>;

    async function init() {
        const h = {'X-Requested-With': 'XMLHttpRequest'};

        try {
            // 1. Cargar Combos
            const [resCli, resVend] = await Promise.all([
                fetch('api_helpers.php?action=get_clientes', {headers: h}).then(r => r.json()),
                fetch('api_helpers.php?action=get_vendedores', {headers: h}).then(r => r.json())
            ]);

            fillSelect('#cmbCliente', resCli, 'razon');
            fillSelect('#cmbVendedor', resVend, 'text'); 

            $('.select2-simple').select2({ dropdownParent: $('#moduloPedidos'), width: '100%', placeholder: 'SELECCIONE...' });

            // 2. Modo Edición: Cargar datos si existe ID
            if(pedidoId > 0) {
                await cargarPedidoParaEdicion(pedidoId);
            }

            // --- LISTENERS ---
            $('#cmbCliente').on('select2:select', async function() {
                const id = $(this).val();
                if(!id) return;
                const d = await fetch(`api_pedidos.php?action=get_info_cliente&id=${id}`, {headers: h}).then(r=>r.json());
                if(d.success) {
                    $('#txtSaldoCliente').val('$ ' + formatMoney(d.data.saldo));
                    if(pedidoId === 0) $('#cmbListaGlobal').val(d.data.lista_precios || 1).trigger('change');
                    if(d.data.vendedor_id) $('#cmbVendedor').val(d.data.vendedor_id).trigger('change');
                }
            });

            $('#cmbListaGlobal').on('change', function() {
                $('#cmbProdLista').val($(this).val());
                actualizarPrecioInput();
            });

            $('#cmbProdLista').on('change', actualizarPrecioInput);

            $('#txtProdCodigo').on('keydown', async function(e) {
                if(e.key === 'Enter') { e.preventDefault(); const c = $(this).val().trim(); if(c) await buscarProductoPorCodigo(c); }
            });

            $('#txtProdCant').on('keydown', function(e) { if(e.key === 'Enter') { e.preventDefault(); agregarItem(); } });
            
            $('#txtBusquedaProd').on('keyup', function(e) {
                clearTimeout(searchTimeout);
                const q = $(this).val();
                searchTimeout = setTimeout(() => buscarEnModal(q), 300);
            });

            $(document).on('keydown', function(e) {
                if(e.key === 'F2') { e.preventDefault(); abrirModalProductos(); }
                if(e.key === 'F10') { e.preventDefault(); guardarPedido(); }
            });

            $('#txtDescGral').on('change', function() {
                const nuevoDesc = parseFloat($(this).val()) || 0;
                if(items.length > 0) {
                    items.forEach(it => { it.descuento = nuevoDesc; });
                    renderGrid();
                }
            });

        } catch (e) { console.error(e); }
    }

    async function cargarPedidoParaEdicion(id) {
        Swal.fire({ title: 'Cargando pedido...', didOpen: () => Swal.showLoading(), background: '#001', color: '#fff' });
        try {
            const res = await fetch(`api_pedidos.php?action=get_pedido&id=${id}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r=>r.json());
            if(res.success) {
                $('#txtFecha').val(res.cab.fechaentrega.split(' ')[0]);
                $('#cmbCliente').val(res.cab.cliente).trigger('change');
                $('#cmbVendedor').val(res.cab.vendedorid).trigger('change');
                $('#txtObsPedido').val(res.cab.observaciones);
                
                items = res.lineas.map(l => ({
                    codigo: l.producto, descripcion: l.descripcion, rubro: l.rubro,
                    cantidad: parseFloat(l.cantidad), precio: parseFloat(l.preciounit), 
                    descuento: parseFloat(l.descuento), lista: parseInt(l.lista),
                    iva_pct: parseFloat(l.iva_pct),
                    linea_id: parseInt(l.id) || null,
                    preparado: parseInt(l.preparado) === 1,
                    impint_unit: parseFloat(l.impint_unit),
                    precios_cache: {
                        siniva: l.siniva, siniva2: l.siniva2, siniva3: l.siniva3, siniva4: l.siniva4,
                        siniva5: l.siniva5, siniva6: l.siniva6, siniva7: l.siniva7, siniva8: l.siniva8
                    }
                }));
                renderGrid();
                Swal.close();
            } else {
                Swal.fire('Error', res.error, 'error').then(() => loadModule('pedidos_admin.php'));
            }
        } catch (e) { console.error(e); }
    }

    async function buscarProductoPorCodigo(codigo) {
        const resAlias = await fetch(`api_referencias.php?action=resolve&code=${encodeURIComponent(codigo)}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r=>r.json());
        const codigoReal = resAlias.success ? resAlias.data.codigo_real : codigo;
        const resProd = await fetch(`api_pedidos.php?action=get_producto_detalle&codigo=${encodeURIComponent(codigoReal)}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r=>r.json());

        if(resProd.success) cargarProductoEnInputs(resProd.data);
        else abrirModalProductos(codigo);
    }

    function cargarProductoEnInputs(p) {
        $('#hdnCachePrecios').val(JSON.stringify(p)); 
        $('#hdnProdId').val(p.id); $('#txtProdCodigo').val(p.codigo);
        $('#txtProdDesc').val(p.descripcion); $('#hdnProdIVA').val(p.poriva);
        $('#hdnProdImpInt').val(p.ImpInt || 0); $('#hdnProdRubro').val(p.rubro);
        actualizarPrecioInput();
        $('#txtProdCant').focus().select();
    }

    function actualizarPrecioInput() {
        const json = $('#hdnCachePrecios').val();
        if(!json) return;
        const p = JSON.parse(json);
        const L = parseInt($('#cmbProdLista').val());
        const precio = parseFloat(p[L==1?'siniva':'siniva'+L] || 0);
        $('#hdnPrecioRaw').val(precio);
        $('#txtProdPrecio').val(formatMoney(precio));
    }

    window.agregarItem = function() {
        const id = $('#hdnProdId').val();
        if(!id) return;
        const cant = parseFloat($('#txtProdCant').val()) || 0;
        if(cant <= 0) return;
        const lista = parseInt($('#cmbProdLista').val());
        const descInput = parseFloat($('#txtProdDescUnit').val()) || parseFloat($('#txtDescGral').val()) || 0;

        const idx = items.findIndex(i => i.codigo == $('#txtProdCodigo').val() && i.lista == lista);
        if(idx >= 0) {
            items[idx].cantidad += cant;
        } else {
            items.push({
                codigo: $('#txtProdCodigo').val(), descripcion: $('#txtProdDesc').val(), rubro: $('#hdnProdRubro').val(),
                cantidad: cant, precio: parseFloat($('#hdnPrecioRaw').val()), descuento: descInput, lista: lista,
                iva_pct: parseFloat($('#hdnProdIVA').val()) || 0,
                impint_unit: parseFloat($('#hdnProdImpInt').val()) || 0,
                precios_cache: JSON.parse($('#hdnCachePrecios').val()),
                promo_label: null, promo_id: null
            });
        }
        evaluarPromociones().then(() => renderGrid());
        limpiarInputsProducto();
        $('#txtProdCodigo').focus();
    }

    window.abrirLineaManual = function() {
        if (!$('#cmbCliente').val()) return Swal.fire('Atención', 'Seleccione un cliente primero.', 'warning');
        $('#ml_cant').val(1);
        $('#ml_det').val('');
        $('#ml_total').val('');
        $('#ml_iva').val(21);
        $('#mod_manual_line').modal('show');
        setTimeout(() => $('#ml_cant').focus().select(), 500);
    }

    window.confirmarLineaManual = function() {
        const prod = $('#ml_det').val().trim();
        const totalFinalU = parseFloat($('#ml_total').val());
        const alicuota = parseFloat($('#ml_iva').val());
        let cant = parseFloat($('#ml_cant').val());

        if (!prod || isNaN(totalFinalU) || totalFinalU <= 0) {
            return Swal.fire('Error', 'Complete descripción e importe mayor a cero.', 'warning');
        }
        if (isNaN(cant) || cant <= 0) cant = 1;

        const netoU = totalFinalU / (1 + alicuota / 100);
        const lista = parseInt($('#cmbListaGlobal').val()) || 1;

        items.push({
            codigo: 'LM', descripcion: prod, rubro: 'Otros',
            cantidad: cant, precio: netoU, descuento: 0, lista: lista,
            iva_pct: alicuota, impint_unit: 0,
            precios_cache: { siniva: netoU, siniva2: netoU, siniva3: netoU, siniva4: netoU, siniva5: netoU, siniva6: netoU, siniva7: netoU, siniva8: netoU },
            promo_label: null, promo_id: null
        });

        renderGrid();
        $('#mod_manual_line').modal('hide');
    }

    /**
     * Llama al motor de evaluación de promociones y actualiza el descuento
     * de cada ítem si una promo lo supera. Solo aplica si no hay desc. manual mayor.
     */
    async function evaluarPromociones() {
        if (items.length === 0) return;
        try {
            const cartParam = encodeURIComponent(JSON.stringify(items.map(it => ({
                codigo:   it.codigo,
                cantidad: it.cantidad,
                lista:    it.lista,
                rubro:    it.rubro || '',
                marca:    it.marca || ''
            }))));
            const res = await fetch(`api_promociones.php?action=evaluar&cart=${cartParam}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json());

            if (!res.success) return;

            res.resultados.forEach((r, idx) => {
                if (r.pordesc > 0) {
                    // Solo pisamos el descuento si la promo es mayor que el manual
                    if (r.pordesc > items[idx].descuento) {
                        items[idx].descuento   = r.pordesc;
                    }
                    items[idx].promo_label = r.promo_label;
                    items[idx].promo_id    = r.promo_id;
                } else {
                    items[idx].promo_label = null;
                    items[idx].promo_id    = null;
                }
            });
        } catch(e) {
            // Si la API de promos falla, continuamos sin descuento automático
            console.warn('Evaluación de promociones no disponible:', e.message);
        }
    }

    function renderGrid() {
        const tb = $('#tbodyPedidos'); tb.empty();
        const wrapCards = $('#tblCards'); wrapCards.empty();
        let tNeto = 0, tIva = 0, tImp = 0, tCant = 0;

        items.forEach((it, i) => {
            const pLista = parseFloat(it.precio);
            const pNetoU = pLista - (pLista * (it.descuento / 100));
            const pIvaU = pNetoU * (it.iva_pct / 100);
            const pFinalU = pNetoU + pIvaU + it.impint_unit;
            const subtotalL = pFinalU * it.cantidad;

            tNeto += (pNetoU * it.cantidad); tIva += (pIvaU * it.cantidad); tImp += (it.impint_unit * it.cantidad);
            tCant += it.cantidad;

            let selectLista = `<select class="select-grid" onchange="cambiarListaItem(${i}, this.value)">`;
            for(let L=1; L<=8; L++) selectLista += `<option value="${L}" ${it.lista == L ? 'selected' : ''}>L${L}</option>`;
            selectLista += `</select>`;

            let selectListaMobile = `<select class="form-select form-select-sm bg-dark text-white border-secondary d-inline-block" onchange="cambiarListaItem(${i}, this.value)" style="width: 70px; padding: 2px 5px; height: 26px; font-size: 11px;">`;
            for(let L=1; L<=8; L++) selectListaMobile += `<option value="${L}" ${it.lista == L ? 'selected' : ''}>L${L}</option>`;
            selectListaMobile += `</select>`;

            // Badge de promo para PC y mobile
            const promoBadge = it.promo_label
                ? `<span class="badge-promo" title="${it.promo_label}">🏷️ PROMO</span>`
                : '';
            const promoBadgeMobile = it.promo_label
                ? `<div class="mt-1"><span class="badge-promo">🏷️ ${it.promo_label}</span></div>`
                : '';

            // RENDERIZADO PC
            const prepClass = it.preparado ? 'item-preparado' : '';
            const chkPrep = `<input type="checkbox" class="chk-preparado" data-idx="${i}" ${it.preparado ? 'checked' : ''} onchange="togglePreparado(${i}, this.checked)" title="Preparado en caja">`;

            tb.append(`
                <tr data-idx="${i}" class="${prepClass}">
                    <td class="text-center align-middle">${chkPrep}</td>
                    <td class="ps-2 text-white fw-bold align-middle${it.codigo !== 'LM' ? ' celda-cod' : ''}" title="${it.codigo !== 'LM' ? 'Clic para ver la foto' : ''}">${it.codigo}</td>
                    <td class="text-white-50 text-truncate align-middle" style="max-width: 200px;">${(PUEDE_EDITAR_DESC && it.codigo !== 'LM')
                    ? `<input type="text" class="input-grid text-white-50" style="width:100%;" value="${it.descripcion}" onchange="cambiarDescripcionProductoItem(${i}, this.value)">`
                    : it.descripcion}${promoBadge}</td>
                    <td class="p-0 align-middle">${selectLista}</td>
                    <td class="text-end text-muted border-start border-secondary bg-dark bg-opacity-25">$ ${formatMoney(pLista)}</td>
                    <td class="text-end text-warning bg-dark bg-opacity-25">$ ${formatMoney(pNetoU)}</td>
                    <td class="text-end text-info bg-dark bg-opacity-25">$ ${formatMoney(pIvaU)}</td>
                    <td class="text-end fw-bold text-white border-end border-secondary bg-dark bg-opacity-25">$ ${formatMoney(pFinalU)}</td>
                    <td class="p-0 align-middle"><input type="number" class="input-grid text-center text-accent" value="${it.cantidad}" onchange="cambiarCantItem(${i}, this.value)" step="0.01"></td>
                    <td class="p-0 align-middle"><input type="number" class="input-grid text-end text-warning" value="${it.descuento}" onchange="cambiarDescItem(${i}, this.value)" step="0.01"></td>
                    <td class="text-end text-warning">$ ${formatMoney(pIvaU * it.cantidad)}</td>
                    <td class="text-end text-info">$ ${formatMoney(it.impint_unit * it.cantidad)}</td>
                    <td class="text-end fw-bold text-success pe-2">$ ${formatMoney(subtotalL)}</td>
                    <td class="text-center align-middle"><i class="fas fa-trash text-danger cursor-pointer" onclick="remItem(${i})"></i></td>
                </tr>`);

            // RENDERIZADO MOBILE
            wrapCards.append(`
                <div class="erp-card mb-2 ${prepClass}" data-idx="${i}">
                    <div class="erp-card-header mb-1 d-flex justify-content-between align-items-start gap-2">
                        ${it.codigo !== 'LM' ? `<div class="ped-thumb" data-codigo="${it.codigo}"><img src="${urlFotoProducto(it.codigo)}" loading="lazy" alt="" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className:'fas fa-box-open'}))"></div>` : ''}
                        <div class="flex-grow-1 min-w-0">
                            <div class="erp-card-title text-white" style="font-size: 13px; max-width: 250px;">${(PUEDE_EDITAR_DESC && it.codigo !== 'LM')
                    ? `<input type="text" class="form-control form-control-sm bg-dark text-white border-secondary" value="${it.descripcion}" onchange="cambiarDescripcionProductoItem(${i}, this.value)">`
                    : it.descripcion}</div>
                            <small class="text-accent" style="font-size: 11px;">CÓDIGO: ${it.codigo}</small>
                            ${promoBadgeMobile}
                        </div>
                        <label class="d-flex align-items-center gap-1 small text-success fw-bold mb-0" style="cursor:pointer;">
                            ${chkPrep} PREP.
                        </label>
                    </div>
                    
                    <div class="row g-1 mb-2 px-2">
                        <div class="col-6"><small class="text-muted d-block" style="font-size: 10px;">LISTA</small>${selectListaMobile}</div>
                        <div class="col-6 text-end"><small class="text-muted d-block" style="font-size: 10px;">P. LISTA</small><span class="text-white">$ ${formatMoney(pLista)}</span></div>
                    </div>
                    
                    <div class="row g-1 mb-2 px-2 bg-dark bg-opacity-50 rounded py-1 border border-secondary border-opacity-50">
                        <div class="col-4"><small class="text-muted d-block" style="font-size: 10px;">NETO U.</small><span class="text-warning" style="font-size: 11px;">$ ${formatMoney(pNetoU)}</span></div>
                        <div class="col-4 text-center"><small class="text-muted d-block" style="font-size: 10px;">IVA U.</small><span class="text-info" style="font-size: 11px;">$ ${formatMoney(pIvaU)}</span></div>
                        <div class="col-4 text-end"><small class="text-muted d-block" style="font-size: 10px;">FINAL U.</small><span class="text-white fw-bold" style="font-size: 11px;">$ ${formatMoney(pFinalU)}</span></div>
                    </div>

                    <div class="row g-2 align-items-end px-2 mb-2">
                        <div class="col-6">
                            <small class="text-white d-block mb-1" style="font-size: 10px;">CANTIDAD</small>
                            <input type="number" class="form-control form-control-sm text-center fw-bold border-info bg-dark text-white" value="${it.cantidad}" onchange="cambiarCantItem(${i}, this.value)" min="0.01" step="0.01">
                        </div>
                        <div class="col-6">
                            <small class="text-warning d-block mb-1" style="font-size: 10px;">BONIFICACIÓN %</small>
                            <input type="number" class="form-control form-control-sm text-center fw-bold border-warning bg-dark text-warning" value="${it.descuento}" onchange="cambiarDescItem(${i}, this.value)" min="0" max="100" step="0.01">
                        </div>
                    </div>

                    <div class="erp-card-row pt-2 mt-2 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <div class="w-100">
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted" style="font-size: 10px;">NETO TOT:</small><span class="text-muted" style="font-size: 11px;">$ ${formatMoney(pNetoU * it.cantidad)}</span></div>
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted" style="font-size: 10px;">IVA TOT:</small><span class="text-warning" style="font-size: 11px;">$ ${formatMoney(pIvaU * it.cantidad)}</span></div>
                            ${it.impint_unit > 0 ? `<div class="d-flex justify-content-between mb-1"><small class="text-muted" style="font-size: 10px;">IMP.I TOT:</small><span class="text-info" style="font-size: 11px;">$ ${formatMoney(it.impint_unit * it.cantidad)}</span></div>` : ''}
                            <div class="d-flex justify-content-between mt-1 pt-1 border-top border-secondary"><span class="text-success fw-bold">FINAL LÍNEA:</span><span class="text-success fw-bold fs-6">$ ${formatMoney(subtotalL)}</span></div>
                        </div>
                    </div>
                    
                    <div class="erp-card-actions mt-2">
                        <button class="btn btn-outline-danger btn-sm w-100 fw-bold" onclick="remItem(${i})">
                            <i class="fas fa-trash-alt me-1"></i> QUITAR ITEM
                        </button>
                    </div>
                </div>
            `);
        });

        // La cantidad admite decimales (step 0.01), pero en la practica casi
        // siempre son enteras: se muestran sin decimales salvo que los tenga.
        $('#lblItems').text(items.length);
        $('#lblCant').text(Number.isInteger(tCant) ? tCant : tCant.toFixed(2));
        $('#lblNeto').text('$ ' + formatMoney(tNeto)); $('#lblIVA').text('$ ' + formatMoney(tIva));
        $('#lblImpInt').text('$ ' + formatMoney(tImp)); $('#lblTotal').text('$ ' + formatMoney(tNeto + tIva + tImp));
        aplicarFiltroItems();
    }

    // --- Buscador dentro del pedido y control de preparacion ---
    // El flag "preparado" se guarda en lineapedidosclientes.preparado: en un pedido ya
    // grabado se guarda al instante al tildar; en uno nuevo (o en líneas recién agregadas)
    // viaja con el pedido al presionar Guardar.
    const normalizarTxt = (t) => String(t || '').toLowerCase().normalize('NFD').replace(/\p{M}/gu, '');

    window.aplicarFiltroItems = function() {
        const terminos = normalizarTxt($('#txtFiltroItems').val()).split(/\s+/).filter(Boolean);
        const ocultarPrep = $('#chkOcultarPreparados').is(':checked');
        let visibles = 0, preparados = 0;

        items.forEach((it, i) => {
            if (it.preparado) preparados++;
            const texto = normalizarTxt(it.codigo + ' ' + it.descripcion);
            const coincide = terminos.every(t => texto.includes(t));
            const mostrar = coincide && !(ocultarPrep && it.preparado);
            if (mostrar) visibles++;
            $(`#tbodyPedidos tr[data-idx="${i}"], #tblCards .erp-card[data-idx="${i}"]`).toggleClass('d-none', !mostrar);
        });

        $('#lblPreparados').text(`${preparados}/${items.length}`);
        const filtrando = terminos.length > 0 || ocultarPrep;
        $('#lblFiltroInfo').toggleClass('d-none', !filtrando).text(`Mostrando ${visibles} de ${items.length}`);
    };

    window.limpiarFiltroItems = function() {
        $('#txtFiltroItems').val('').focus();
        aplicarFiltroItems();
    };

    function pintarPreparado(i, marcado) {
        // Sincroniza el checkbox de la otra vista (PC / mobile) sin re-renderizar
        $(`.chk-preparado[data-idx="${i}"]`).prop('checked', !!marcado);
        $(`#tbodyPedidos tr[data-idx="${i}"], #tblCards .erp-card[data-idx="${i}"]`).toggleClass('item-preparado', !!marcado);
        aplicarFiltroItems();
    }

    window.togglePreparado = async function(i, marcado) {
        const it = items[i];
        if (!it) return;
        it.preparado = !!marcado;
        pintarPreparado(i, marcado);

        // Pedido ya grabado y línea existente en la base: se guarda en el momento
        if (!(pedidoId > 0 && it.linea_id)) return;
        try {
            const res = await fetch('api_pedidos.php?action=set_preparado', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({ numpedido: pedidoId, linea_id: it.linea_id, preparado: !!marcado })
            }).then(r => r.json());
            if (!res.success) throw new Error(res.error || 'No se pudo guardar');
        } catch (e) {
            // Si no se pudo guardar, se vuelve atrás para no mostrar algo que no quedó grabado
            if (items[i] === it) { it.preparado = !marcado; pintarPreparado(i, !marcado); }
            Swal.fire({ toast: true, position: 'top-end', icon: 'error', title: 'No se guardó el tilde', text: e.message, timer: 4000, showConfirmButton: false });
        }
    };

    // --- Foto del producto al pasar el mouse (como en ventas) ---
    // img_producto.php valida la sesión y el tenant; devuelve 404 si no hay foto.
    function urlFotoProducto(codigo) { return 'img_producto.php?codigo=' + encodeURIComponent(codigo); }
    window.urlFotoProducto = urlFotoProducto;
    // Se cuelga del body: si quedara dentro de un contenedor con transform (animación fade-in del
    // módulo), position:fixed se mediría contra ese contenedor y no contra la ventana.
    $('body > #pedFotoPop').remove();
    $('#pedFotoPop').appendTo('body');
    const sinFoto = new Set(); // códigos que ya dieron 404, para no reintentar en cada hover
    let fotoActual = null;

    function mostrarFotoPop(codigo, descripcion, sub, ev) {
        if (!codigo || codigo === 'LM') return ocultarFotoPop();
        if (fotoActual !== codigo) {
            fotoActual = codigo;
            const $img = $('#pedFotoPopImg');
            if (sinFoto.has(codigo)) {
                $img.html('<i class="fas fa-box-open"></i>');
            } else {
                const img = new Image();
                img.alt = '';
                img.onerror = () => { sinFoto.add(codigo); if (fotoActual === codigo) $img.html('<i class="fas fa-box-open"></i>'); };
                img.src = urlFotoProducto(codigo);
                $img.empty().append(img);
            }
            $('#pedFotoPopTit').text(descripcion || codigo);
            $('#pedFotoPopSub').text(sub || codigo);
        }
        $('#pedFotoPop').addClass('visible');
        moverFotoPop(ev);
    }
    function moverFotoPop(ev) {
        const pop = document.getElementById('pedFotoPop');
        if (!pop || !ev) return;
        const w = pop.offsetWidth || 230, h = pop.offsetHeight || 260, m = 18;
        let x = ev.clientX + m, y = ev.clientY + m;
        if (x + w > window.innerWidth - 8) x = ev.clientX - w - m;   // sin lugar a la derecha: a la izquierda
        if (y + h > window.innerHeight - 8) y = Math.max(8, window.innerHeight - h - 8);
        pop.style.left = Math.max(8, x) + 'px';
        pop.style.top = y + 'px';
    }
    function ocultarFotoPop() { fotoActual = null; $('#pedFotoPop').removeClass('visible'); }

    window.abrirFotoGrande = function(codigo, titulo) {
        if (!codigo || codigo === 'LM') return;
        ocultarFotoPop();
        $('#fotoPedidoTitulo').text(titulo ? `${codigo} · ${titulo}` : codigo);
        $('#fotoPedidoSin').hide();
        $('#fotoPedidoImg').show().off('error').on('error', function() { $(this).hide(); $('#fotoPedidoSin').show(); })
            .attr('src', urlFotoProducto(codigo));
        $('#mod_foto_pedido').modal('show');
    };

    // Grilla del pedido
    $(document).off('.fotoPed')
        .on('mouseenter.fotoPed mousemove.fotoPed', '#tbodyPedidos tr[data-idx]', function(ev) {
            const it = items[parseInt($(this).data('idx'))];
            if (!it) return;
            if (ev.type === 'mousemove' && fotoActual === it.codigo) return moverFotoPop(ev);
            mostrarFotoPop(it.codigo, it.descripcion, it.codigo + (it.rubro ? ' · ' + it.rubro : ''), ev);
        })
        .on('mouseleave.fotoPed', '#tbodyPedidos tr[data-idx]', ocultarFotoPop)
        // Buscador de productos (modal)
        .on('mouseenter.fotoPed mousemove.fotoPed', '#tbodyResultadosProd tr', function(ev) {
            const d = this.dataset;
            if (ev.type === 'mousemove' && fotoActual === d.codigo) return moverFotoPop(ev);
            mostrarFotoPop(d.codigo, d.descripcion, d.codigo + (d.rubro ? ' · ' + d.rubro : ''), ev);
        })
        .on('mouseleave.fotoPed', '#tbodyResultadosProd tr', ocultarFotoPop)
        // Clic en el código (grilla) o en la miniatura (celular): foto grande
        .on('click.fotoPed', '#tbodyPedidos td.celda-cod', function() {
            const it = items[parseInt($(this).closest('tr').data('idx'))];
            if (it) abrirFotoGrande(it.codigo, it.descripcion);
        })
        .on('click.fotoPed', '#tblCards .ped-thumb', function() {
            const it = items[parseInt($(this).closest('.erp-card').data('idx'))];
            if (it) abrirFotoGrande(it.codigo, it.descripcion);
        })
        .on('hide.bs.modal.fotoPed', '#modalBuscarProd', ocultarFotoPop);
    $('#gridPedidos').closest('.grid-container').off('scroll.fotoPed').on('scroll.fotoPed', ocultarFotoPop);

    $(document).off('input.filtroItems').on('input.filtroItems', '#txtFiltroItems', aplicarFiltroItems);
    $(document).off('keydown.filtroItems').on('keydown.filtroItems', '#txtFiltroItems', function(e) {
        if (e.key === 'Escape') limpiarFiltroItems();
    });

    window.cambiarListaItem = (i, v) => { items[i].lista = parseInt(v); items[i].precio = parseFloat(items[i].precios_cache[v==1?'siniva':'siniva'+v]||0); renderGrid(); };
    window.cambiarCantItem  = (i, v) => { items[i].cantidad = parseFloat(v) || 1; evaluarPromociones().then(() => renderGrid()); };
    window.cambiarDescItem  = (i, v) => { items[i].descuento = parseFloat(v) || 0; items[i].promo_label = null; items[i].promo_id = null; renderGrid(); };
    window.cambiarDescripcionProductoItem = (i, v) => { if (!PUEDE_EDITAR_DESC) return; items[i].descripcion = String(v).trim() || items[i].descripcion; renderGrid(); };
    window.remItem = (i) => { items.splice(i,1); renderGrid(); };

    window.abrirModalProductos = (q = '') => { $('#txtBusquedaProd').val(q); buscarEnModal(q); $('#modalBuscarProd').modal('show'); setTimeout(() => $('#txtBusquedaProd').focus(), 500); }
    window.buscarEnModal = async function(q = '') {
        const res = await fetch(`api_pedidos.php?action=buscar_productos&q=${encodeURIComponent(q)}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}}).then(r=>r.json());
        const tb = $('#tbodyResultadosProd'); tb.empty();
        if(res.success && res.data) {
            const lista = parseInt($('#cmbListaGlobal').val() || 1);
            res.data.forEach(p => {
                const key = (lista === 1) ? 'siniva' : 'siniva' + lista;
                const precio = parseFloat(p[key] || 0);
                
                const tr = document.createElement('tr');
                tr.className = 'cursor-pointer';
                tr.dataset.codigo = p.codigo;
                tr.dataset.descripcion = p.descripcion || '';
                tr.dataset.rubro = p.rubro || '';
                tr.onclick = () => seleccionarDeModal(p);
                tr.innerHTML = `<td class="text-white fw-bold ps-3">${p.codigo}</td>
                                <td class="text-white-50">${p.descripcion}</td>
                                <td>${p.rubro}</td>
                                <td class="text-end text-info pe-3">$ ${formatMoney(precio)}</td>
                                <td class="text-center"><i class="fas fa-plus text-success"></i></td>`;
                tb.append(tr);
            });
        }
    }
    window.seleccionarDeModal = function(p) { cargarProductoEnInputs(p); $('#modalBuscarProd').modal('hide'); }

    window.guardarPedido = async function() {
        if(items.length === 0) return Swal.fire('Error', 'No hay productos.', 'error');
        const payload = {
            numpedido: pedidoId, 
            fecha: $('#txtFecha').val(), cliente_id: $('#cmbCliente').val(), vendedor_id: $('#cmbVendedor').val(),
            observaciones: $('#txtObsPedido').val(), descuento_gral: $('#txtDescGral').val(), 
            items: items.map(i => ({
                codigo: i.codigo, descripcion: i.descripcion, cantidad: i.cantidad, 
                precio_unitario: i.precio, descuento: i.descuento, lista: i.lista,
                imp_int_unit: i.impint_unit, iva_pct: i.iva_pct,
                poriva: i.codigo === 'LM' ? i.iva_pct : null,
                promo_id: i.promo_id || null,
                preparado: !!i.preparado
            }))
        };
        Swal.fire({title:'Guardando...', didOpen:()=>Swal.showLoading(), background:'#001020', color:'#fff'});
        try {
            const res = await fetch('api_pedidos.php?action=save', {method:'POST', headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'}, body: JSON.stringify(payload)}).then(r=>r.json());
            if(res.success) { 
                Swal.fire('Éxito', `Pedido Nº ${res.numpedido} guardado.`, 'success').then(() => {
                    if(typeof loadModule === 'function') loadModule('pedidos_admin.php');
                    else location.reload();
                }); 
            } else { Swal.fire('Error', res.error, 'error'); }
        } catch(e) { Swal.fire('Error', 'Fallo de red', 'error'); }
    }

    window.limpiarPedido = function() { items = []; $('#cmbCliente').val('').trigger('change'); $('#txtObsPedido').val(''); $('#txtDescGral').val('0'); renderGrid(); limpiarInputsProducto(); }
    function fillSelect(sel, res, field) { const el = $(sel); el.empty().append(new Option('SELECCIONE...', '', true, true)); if(res.success && res.data) res.data.forEach(d => el.append(new Option(d[field].toUpperCase(), d.id))); }
    function formatMoney(n) { return parseFloat(n).toLocaleString('es-AR', {minimumFractionDigits: 2}); }
    function limpiarInputsProducto() { $('#txtProdCodigo, #txtProdDesc, #txtProdPrecio, #hdnPrecioRaw, #hdnProdId, #txtProdDescUnit').val(''); $('#txtProdCant').val('1'); }

    window.addEventListener('resize', renderGrid);
    init();
})();
</script>