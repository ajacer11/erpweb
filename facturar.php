<?php
/**
 * facturar.php - INTERFAZ DE FACTURACIÓN PROFESIONAL (V20.0)
 * INTEGRACIÓN: Botón Línea Manual (F8) y Modal de Carga Manual.
 * Fidelidad absoluta a frmagregarfila.vb y arquitectura SPA.
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$pedidoIdFacturar = isset($_GET['pedidoid']) ? intval($_GET['pedidoid']) : 0;
?>

<style>
    /* Estructura Anti-Scroll definitiva */
    #billingAppContainer {
        display: flex;
        width: 100%;
        height: 100%;
        background-color: var(--bg-escritorio);
        overflow: hidden;
        padding: 4px;
        gap: 4px;
        box-sizing: border-box;
        transition: background-color 0.3s ease;
    }
    
    /* Estilos Generales */
    #billingAppContainer .row { margin-left: 0; margin-right: 0; }
    #billingAppContainer .row>* { padding-left: 2px; padding-right: 2px; }

    .sidebar-bill {
        width: 90px;
        display: flex;
        flex-direction: column;
        gap: 4px;
        flex-shrink: 0;
    }

    .btn-side-bill {
        width: 100%;
        height: 55px;
        background-color: var(--bg-card);
        border: 1px solid var(--input-border);
        color: var(--text-main);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        font-size: 8.5px;
        font-weight: 700;
        text-transform: uppercase;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .btn-side-bill:hover {
        background-color: var(--accent-soft);
        border-color: var(--accent);
        color: var(--accent);
    }

    .btn-side-bill i { font-size: 16px; margin-bottom: 2px; }

    .bill-work-area {
        flex: 1;
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 0;
        gap: 3px;
    }

    .panel-bill {
        background: var(--bg-card);
        border: 1px solid var(--input-border);
        border-radius: 4px;
        padding: 6px 10px;
        flex-shrink: 0;
    }

    .input-group { display: flex !important; flex-wrap: nowrap !important; }
    .input-group .select2-container { flex: 1 1 auto !important; width: 1% !important; }
    .input-group .select2-selection--single {
        height: 31px !important;
        background-color: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important;
        border-radius: 4px 0 0 4px !important;
        display: flex !important;
        align-items: center !important;
    }
    .input-group .btn { height: 31px !important; z-index: 4 !important; }

    .btn-outline-accent { border-color: var(--accent); color: var(--accent); }
    .btn-outline-accent:hover { background-color: var(--accent); color: #000; }

    .grid-bill-wrapper {
        flex: 1;
        background: var(--bg-card);
        border: 1px solid var(--input-border);
        border-radius: 4px;
        overflow-y: auto;
        min-height: 0;
    }

    .table-pos { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .table-pos thead th {
        background: var(--bg-table-header);
        color: var(--text-muted);
        padding: 4px;
        text-align: left;
        border-bottom: 1px solid var(--border-color);
        position: sticky;
        top: 0;
        z-index: 10;
        font-size: 7.5px;
        text-transform: uppercase;
    }

    .table-pos tbody td {
        padding: 3px 4px;
        border-bottom: 1px solid var(--border-color);
        color: var(--text-main);
        font-size: 10px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .table-pos tbody tr:hover { background: var(--accent-soft); }

    .footer-bill {
        background: var(--bg-card);
        border: 1px solid var(--input-border);
        padding: 4px 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 4px;
        flex-shrink: 0;
        gap: 10px;
    }

    .tax-matrix { display: grid; grid-template-columns: repeat(3, auto); gap: 12px; flex-grow: 1; }
    .tax-col { display: flex; flex-direction: column; border-right: 1px solid var(--border-color); padding-right: 12px; }
    .tax-col:last-child { border-right: none; }
    .tax-row { display: flex; justify-content: space-between; align-items: center; gap: 20px; }
    .tax-lbl { font-size: 7.5px; color: var(--text-muted); font-weight: 800; text-transform: uppercase; }
    .tax-num { font-size: 13px; font-weight: 600; color: var(--text-titles); }

    .total-display-big {
        text-align: right;
        flex: 1;
        padding-left: 15px;
        border-left: 2px solid var(--accent);
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .lbl-total-pos {
        font-size: 42px;
        font-weight: 950;
        color: var(--accent);
        line-height: 0.8;
        letter-spacing: -2px;
    }

    .in-pos {
        background: var(--input-bg) !important;
        border: 1px solid var(--input-border) !important;
        color: var(--text-main) !important;
        font-size: 11px !important;
        padding: 2px 6px !important;
        border-radius: 3px !important;
        height: 31px;
    }

    .badge-fiscal-pos {
        height: 31px;
        display: flex;
        align-items: center; justify-content: center;
        text-align: center; line-height: 1; font-weight: 900; font-size: 8.5px;
        border: 1px solid var(--accent); border-radius: 4px; color: var(--accent);
        background: var(--accent-soft); text-transform: uppercase;
    }

    .btn-adj {
        padding: 0 4px; font-size: 8px; border: 1px solid var(--input-border);
        background: var(--input-bg); color: var(--accent); margin-left: 5px; border-radius: 2px;
    }
</style>

<div id="billingAppContainer" class="fade-in">
    <div class="sidebar-bill">

        <button class="btn-side-bill" onclick="bill.nueva()"><i class="fas fa-file"></i>NUEVO (F4)</button>
        
        <button class="btn-side-bill" onclick="bill.abrirLineaManual()"><i class="fas fa-keyboard"></i>LINEA MANUAL (F8)</button>

        <button class="btn-side-bill" id="btnAsociarTrigger" style="display:none;" onclick="bill.abrirAsociar()">
            <i class="fas fa-link"></i>ASOCIAR
        </button>
        
        <div class="mt-auto"></div>
        
        <button class="btn-side-bill" id="btnCobrarTrigger" onclick="bill.intentarCobro()"
            style="background:#064e3b; border-color:#10b981; color: #fff;"><i class="fas fa-cash-register"></i>COBRAR (F10)</button>
        <button class="btn-side-bill" onclick="bill.nueva()" style="background:#450a0a; border-color:#ef4444; color: #fff;">
            <i class="fas fa-times"></i>ANULAR (F4)
        </button>
    </div>
    
    <div class="bill-work-area">
        <div class="panel-bill">
            <div class="row g-1 align-items-end">
                <div class="col-md-5">
                    <label class="form-label-custom">CLIENTE (F3)</label>
                    <div class="input-group input-group-sm">
                        <select id="sel_cliente" class="form-control"></select>
                        <button class="btn btn-outline-accent" onclick="bill.abrirBuscadorCliente()"><i class="fas fa-search"></i></button>
                        <button class="btn btn-outline-info" onclick="bill.verFicha()"><i class="fas fa-id-card"></i></button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">TIPO COMPROBANTE</label>
                    <select id="sel_tipo" class="form-select in-pos"></select>
                </div>
                <div class="col-md-2 text-center">
                    <label class="form-label-custom">NÚMERO</label>
                    <span id="bill_nro_label" class="fw-bold text-accent fs-6 d-block">0000-00000000</span>
                </div>
                <div class="col-md-2">
                    <div id="badge_fiscal" class="badge-fiscal-pos">CONSUMIDOR<br>FINAL</div>
                </div>
            </div>
        </div>

        <div class="panel-bill" style="background: var(--input-bg); border-color:var(--accent-soft);">
            <div class="row g-1 align-items-end">
                <div style="width: 55px;"><label class="form-label-custom text-center d-block">CANT.</label>
                    <input type="number" id="in_cant" class="form-control in-pos text-center fw-bold" value="1"></div>
                <div style="width: 125px;"><label class="form-label-custom text-accent">CÓDIGO (F2)</label>
                    <input type="text" id="in_cod" class="form-control in-pos" placeholder="Cod..."></div>
                <div class="col">
                    <label class="form-label-custom">DESCRIPCIÓN / PRODUCTO</label>
                    <div class="input-group input-group-sm">
                        <select id="in_desc_select2" class="form-control"></select>
                        <button class="btn btn-outline-accent" onclick="bill.abrirBuscadorArticulo()"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div style="width: 75px;"><label class="form-label-custom text-center d-block">LISTA</label>
                    <select id="in_lista" class="form-select in-pos"></select></div>
                <div style="width: 90px;"><label class="form-label-custom text-end d-block">NETO U.</label>
                    <input type="text" id="in_unit" class="form-control in-pos text-end" readonly value="0.00"></div>
                <div style="width: 60px;"><label class="form-label-custom text-end d-block text-warning">DESC%</label>
                    <input type="number" id="in_desc_it" class="form-control in-pos text-end" value="0"></div>
                <div style="width: 80px;"><button class="btn btn-info btn-sm w-100 fw-bold" onclick="bill.addItem()" style="height:31px;">BAJAR</button></div>
            </div>
        </div>

        <div class="grid-bill-wrapper">
            <table class="table-pos table mb-0" id="main_grid">
                <thead id="grid_head"></thead>
                <tbody id="grid_body"></tbody>
            </table>
        </div>

        <div class="footer-bill">
            <div class="tax-matrix">
                <div class="tax-col">
                    <div class="tax-row"><span class="tax-lbl">Sub. 21%</span><span class="tax-num" id="tx_n21_1">0,00</span></div>
                    <div class="tax-row"><span class="tax-lbl">Sub. 10.5%</span><span class="tax-num" id="tx_n105_1">0,00</span></div>
                    <div class="tax-row"><span class="tax-lbl">Imp. Int Final</span><span class="tax-num" id="tx_ii_final_total">0,00</span></div>
                </div>
                <div class="tax-col">
                    <div id="btn_desc_global" class="tax-row"><span class="tax-lbl text-warning">Descuento</span>
                        <div><span class="tax-num text-warning" id="tx_desc_g">0,00</span><button class="btn-adj" onclick="bill.modalGlobal('DESC')"><i class="fas fa-edit"></i></button></div>
                    </div>
                    <div id="btn_reca_global" class="tax-row"><span class="tax-lbl text-info">Recargo</span>
                        <div><span class="tax-num text-info" id="tx_reca_g">0,00</span><button class="btn-adj" onclick="bill.modalGlobal('RECA')"><i class="fas fa-edit"></i></button></div>
                    </div>
                </div>
                <div class="tax-col">
                    <div class="tax-row"><span class="tax-lbl">IVA Final</span><span class="tax-num" id="tx_iva_f">0,00</span></div>
                    <div class="tax-row"><span class="tax-lbl">Neto Final</span><span class="tax-num" style="color:var(--accent)" id="tx_neto_f">0,00</span></div>
                </div>
            </div>
            <div class="total-display-big">
                <div class="tax-lbl" style="color:var(--accent); font-weight: 900;">TOTAL COMPROBANTE</div>
                <div class="lbl-total-pos" id="tx_total_f">0,00</div>
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
                        <input type="number" id="ml_cant" class="form-control in-pos text-center fw-bold" value="1" min="1">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label-custom">ALICUOTA DE IVA</label>
                        <select id="ml_iva" class="form-select in-pos">
                            <option value="0">0%</option>
                            <option value="10.5">10.5%</option>
                            <option value="21" selected>21%</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">DETALLE / DESCRIPCIÓN</label>
                        <input type="text" id="ml_det" class="form-control in-pos" placeholder="Ingrese descripción del ítem...">
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom text-accent">IMPORTE (IVA INCLUIDO)</label>
                        <input type="number" step="0.01" id="ml_total" class="form-control in-pos text-end fw-bold fs-5" placeholder="0.00">
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
                        <input type="text" id="asoc_pv" class="form-control in-pos text-center fw-bold" disabled>
                    </div>
                    <div class="col-8">
                        <label class="form-label-custom">N° DE COMPROBANTE</label>
                        <input type="text" id="asoc_num" class="form-control in-pos text-center fw-bold" placeholder="00000000">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom">TIPO FACTURA ORIGINAL</label>
                    <input type="text" id="asoc_tipo_txt" class="form-control in-pos text-center fw-bold opacity-75" readonly>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <label class="form-label-custom">FECHA COMPROBANTE</label>
                        <input type="date" id="asoc_fecha" class="form-control in-pos">
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

<div class="modal fade" id="mod_search_cli" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">BUSCAR CLIENTE</h6>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2">
                    <input type="text" id="in_mod_cli_q" class="form-control in-pos" placeholder="CUIT o Razón...">
                </div>
                <div style="max-height:400px;overflow-y:auto">
                    <table class="table-pos w-100 table mb-0">
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
                    <input type="text" id="in_mod_art_q" class="form-control in-pos" placeholder="Filtrar...">
                </div>
                <div style="max-height:450px;overflow-y:auto">
                    <table class="table-pos w-100 table mb-0">
                        <thead class="sticky-top"><tr><th width="120">COD</th><th>DESCRIPCIÓN</th><th>RUBRO</th><th class="text-end">NETO</th><th class="text-end">FINAL</th><th width="40"></th></tr></thead>
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
                    <div class="tax-lbl">TOTAL</div>
                    <div class="lbl-total-pos" id="mod_pay_total">$ 0,00</div>
                </div>
                <div class="row g-3">
                    <div class="col-12"><label class="form-label-custom">MEDIO 1</label>
                        <div class="input-group input-group-sm">
                            <select id="pay_m1" class="form-select in-pos" style="flex:2"></select>
                            <input type="number" id="pay_i1" class="form-control in-pos text-end fw-bold text-success" style="flex:1">
                        </div>
                    </div>
                    <div class="col-12"><label class="form-label-custom">MEDIO 2</label>
                        <div class="input-group input-group-sm">
                            <select id="pay_m2" class="form-select in-pos" style="flex:2"></select>
                            <input type="number" id="pay_i2" class="form-control in-pos text-end fw-bold text-warning" style="flex:1" value="0.00">
                        </div>
                    </div>
                    <div class="col-12 mt-3"><label class="form-label-custom">VENDEDOR</label>
                        <select id="pay_v" class="form-select in-pos"></select>
                    </div>
                </div>
            </div>
            <div class="modal-footer d-flex gap-2">
                <button type="button" class="btn btn-danger fw-bold px-3" data-bs-dismiss="modal" style="flex: 0 0 auto;">
                    CANCELAR (ESC)
                </button>
                <button type="button" class="btn btn-success fw-bold flex-grow-1 py-2" onclick="bill.grabar()">
                    EMITIR (F10)
                </button>
            </div>
        </div>
    </div>
</div>
<script>
    // Gatillo Automático si viene desde la pantalla de Pedidos
    const PEDIDO_ORIGEN_FACTURAR = <?php echo $pedidoIdFacturar; ?>;
    
    $(document).ready(function() {
        if (PEDIDO_ORIGEN_FACTURAR > 0) {
            setTimeout(() => {
                if (window.bill && typeof window.bill.cargarPedidoOrigen === 'function') {
                    window.bill.cargarPedidoOrigen(PEDIDO_ORIGEN_FACTURAR);
                }
            }, 800);
        }
        // La NC se detecta directamente en facturar.js via window.location (URLSearchParams)
    });
</script>
<script src="facturar_validaciones.js?v=<?php echo time(); ?>"></script>
<script src="facturar.js?v=<?php echo time(); ?>"></script>