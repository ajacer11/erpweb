<?php
/**
 * proveedores_facturas_administrar.php - ADMINISTRACIÓN Y AUDITORÍA DE COMPRAS
 * Actualizado: Paginación SPA, Responsive Cards y Debounce.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloComprasFacturas">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-file-invoice text-accent me-2"></i>ADMINISTRAR FACTURAS DE COMPRA</h4>
            <small class="text-accent opacity-75">Auditoría, digitalización ARCA y gestión de comprobantes</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-warning btn-sm fw-bold rounded-pill px-3" id="btn-arca">
                <i class="fas fa-file-invoice me-1"></i> <span class="d-none d-sm-inline">LIBRO IVA DIGITAL (ARCA)</span><span class="d-inline d-sm-none">ARCA</span>
            </button>
            <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" id="btn-excel">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" id="btn-buscar">
                <i class="fas fa-search me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros mb-3 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 text-start align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">RANGO Y TIPO FECHA</label>
                    <div class="d-flex gap-1 mb-2">
                        <input type="date" id="f-desde" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-01') ?>">
                        <input type="date" id="f-hasta" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-d') ?>">
                    </div>
                    <select id="cmb-tipo-fecha" class="form-select form-select-sm filter-auto">
                        <option value="fecha">FECHA FACTURA</option>
                        <option value="fechaimputacion">FECHA IMPUTACIÓN</option>
                        <option value="fecharegistro">FECHA DE CARGA</option>
                        <option value="fechavencimiento">FECHA VENCIMIENTO</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 border-md-start border-secondary px-md-3 mt-2 mt-md-0">
                    <label class="form-label-custom">PROVEEDOR / COMPROBANTE</label>
                    <div class="mb-2">
                        <select id="cmb-proveedor" class="form-control filter-auto"></select>
                    </div>
                    <select id="cmb-tipo-comp" class="form-select form-select-sm filter-auto">
                        <option value="todos">-- TODOS LOS COMPROBANTES --</option>
                        <option value="FISCALES">SÓLO FISCALES</option>
                        <option value="NO_FISCALES">SÓLO NO FISCALES</option>
                        <option value="1">FACTURA A</option>
                        <option value="3">NOTA DE CRÉDITO A</option>
                        <option value="6">FACTURA B</option>
                        <option value="8">NOTA DE CRÉDITO B</option>
                        <option value="11">FACTURA C</option>
                        <option value="13">NOTA DE CRÉDITO C</option>
                        <option value="1000">REMITOS</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 border-md-start border-secondary px-md-3 mt-2 mt-md-0">
                    <label class="form-label-custom">FILTRO POR IMPORTE</label>
                    <div class="input-group input-group-sm mb-2">
                        <select id="cmb-logic-imp" class="form-select filter-auto" style="max-width: 90px;">
                            <option value="todos">OFF</option>
                            <option value="MAYOR">></option>
                            <option value="IGUAL">=</option>
                            <option value="MENOR"><</option>
                        </select>
                        <input type="number" id="txt-val-imp" class="form-control filter-auto" placeholder="0.00">
                    </div>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input filter-auto-force" type="checkbox" id="chk-solo-pend">
                        <label class="form-check-label text-white small fw-bold" for="chk-solo-pend" style="cursor:pointer">Sólo Pendientes</label>
                    </div>
                </div>

                <div class="col-md-2 border-start border-secondary text-center d-none d-md-flex flex-column justify-content-center">
                    <img src="logo.png" style="max-height: 40px; opacity: 0.2; filter: grayscale(1);" onerror="this.style.display='none'">
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- ============================
         GRILLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap" id="tabla-compras">
            <thead class="sticky-top bg-black">
                <tr>
                    <th class="ps-3 py-2">FECHA</th>
                    <th>VENCE</th>
                    <th>TIPO</th>
                    <th>NÚMERO</th>
                    <th>PROVEEDOR</th>
                    <th class="text-end">NETO</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end text-accent">TOTAL</th>
                    <th class="text-center">ESTADO</th>
                    <th class="text-center">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbody-compras">
                <tr><td colspan="10" class="text-center py-5 text-muted italic">Haga clic en ACTUALIZAR para cargar datos</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">Haga clic en ACTUALIZAR para cargar datos</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination mb-2" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- TOTALES FOOTER RESPONSIVO -->
    <div class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4" id="panelTotales" style="display: none; background-color: #000a1a;">
        <div class="card-body p-3">
            <div class="row text-center g-2 align-items-center">
                <div class="col-6 col-md-2">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">SUBTOTAL</small>
                    <span id="sum-sub" class="fw-bold text-white">$ 0,00</span>
                </div>
                <div class="col-6 col-md-2 border-start border-secondary border-opacity-25">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">IVA</small>
                    <span id="sum-iva" class="fw-bold text-white">$ 0,00</span>
                </div>
                <div class="col-6 col-md-2 border-md-start border-secondary border-opacity-25 mt-3 mt-md-0">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">PERC / IIBB</small>
                    <span id="sum-imp" class="fw-bold text-white">$ 0,00</span>
                </div>
                <div class="col-6 col-md-3 border-start border-secondary mt-3 mt-md-0">
                    <small class="text-warning d-block fw-bold text-uppercase" style="font-size: 10px;">RET. TOTALES</small>
                    <span id="sum-ret" class="fw-bold text-warning">$ 0,00</span>
                </div>
                <div class="col-12 col-md-3 border-md-start border-accent border-4 mt-3 mt-md-0 py-1">
                    <small class="text-accent d-block fw-bold text-uppercase" style="font-size: 10px;">TOTAL SELECCIÓN</small>
                    <span id="sum-total" class="fs-4 fw-bold text-accent">$ 0,00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL VISUALIZACIÓN DETALLE (Queda intacto) -->
<div class="modal fade" id="modalViewFact" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg text-start">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-file-invoice me-2 text-info"></i>Detalle de Comprobante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px;">PROVEEDOR</small>
                        <span id="det-prov" class="fw-bold text-white fs-5"></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px;">COMPROBANTE</small>
                        <span id="det-tipo-num" class="text-white"></span>
                    </div>
                    <div class="col-md-3 text-end">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px;">ESTADO</small>
                        <span id="det-estado" class="badge py-1 px-2 border"></span>
                    </div>
                </div>

                <div class="row mb-4 border-top border-secondary pt-3">
                    <div class="col-md-3"><small class="text-muted d-block" style="font-size: 10px;">FECHA FACTURA</small><span id="det-f-fac" class="text-white"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block" style="font-size: 10px;">FECHA VTO.</small><span id="det-f-vto" class="text-white"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block" style="font-size: 10px;">FECHA IMPUT.</small><span id="det-f-imp" class="text-white"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block" style="font-size: 10px;">TOTAL GENERAL</small><span id="det-total" class="text-accent fw-bold fs-5"></span></div>
                </div>

                <h6 class="text-info border-bottom border-secondary pb-1 small fw-bold mt-4">DESGLOSE DE CONCEPTOS</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-dark small">
                        <thead>
                            <tr>
                                <th>CONCEPTO</th>
                                <th class="text-end">NETO GRAVADO</th>
                                <th class="text-center">IVA %</th>
                            </tr>
                        </thead>
                        <tbody id="det-tbody-conceptos"></tbody>
                    </table>
                </div>

                <!-- SECCIÓN DE TOTALES DETALLADOS (PERCEPCIONES NO AGRUPADAS) -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label class="form-label-custom small">NOTAS / OBSERVACIONES</label>
                        <p id="det-notas" class="text-muted small italic p-2 bg-dark rounded border border-secondary" style="min-height: 120px;"></p>
                    </div>
                    <div class="col-md-6">
                        <div class="bg-dark p-3 rounded border border-secondary">
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted">Subtotal Neto:</small><span id="det-val-neto" class="text-white fw-bold"></span></div>
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted">IVA Total:</small><span id="det-val-iva" class="text-white"></span></div>
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted">Impuestos Internos:</small><span id="det-val-ii" class="text-white"></span></div>
                            
                            <!-- DESGLOSE DETALLADO DE PERCEPCIONES -->
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted">Percepción IVA:</small><span id="det-val-piva" class="text-white"></span></div>
                            <div class="d-flex justify-content-between mb-1"><small class="text-muted">Percepción IIBB:</small><span id="det-val-piibb" class="text-white"></span></div>
                            <div class="d-flex justify-content-between mb-2"><small class="text-muted">Ret. Ganancias:</small><span id="det-val-pgan" class="text-white"></span></div>
                            
                            <div class="d-flex justify-content-between pt-2 border-top border-secondary"><span class="text-accent fw-bold">TOTAL FINAL:</span><span id="det-val-total" class="text-accent fw-bold fs-5"></span></div>
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN DE AUDITORÍA -->
                <div class="row mt-4 pt-3 border-top border-secondary opacity-75">
                    <div class="col-md-6">
                        <small class="text-muted d-block" style="font-size: 9px;">USUARIO DE CARGA</small>
                        <span id="det-usr-carga" class="text-info small fw-bold"></span>
                    </div>
                    <div class="col-md-6 text-end">
                        <small class="text-muted d-block" style="font-size: 9px;">FECHA Y HORA DE REGISTRO</small>
                        <span id="det-fec-registro" class="text-info small fw-bold"></span>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">CERRAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let debounceTimer = null;
    let listado = [];
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
    
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        configurarSelect2();
        configurarListeners();
        buscarForce();
    }

    function configurarListeners() {
        $('#btn-buscar').click(buscarForce);
        $('#btn-excel').click(exportarExcel);
        $('#btn-arca').click(exportarArca);

        // Checkbox fuerza búsqueda inmediata
        $('#chk-solo-pend').on('change', buscarForce);

        // Debounce para el resto de inputs/selects
        $('.filter-auto').not('.filter-auto-force').on('input change', function () {
            clearTimeout(debounceTimer);
            const desde = $('#f-desde').val();
            const hasta = $('#f-hasta').val();
            
            if (desde && parseInt(desde.split('-')[0]) < 2000) return;
            if (hasta && parseInt(hasta.split('-')[0]) < 2000) return;

            debounceTimer = setTimeout(() => { buscar(); }, 800);
        });
    }

    function configurarSelect2() {
        $('#cmb-proveedor').select2({
            placeholder: 'FILTRAR POR PROVEEDOR...',
            dropdownParent: $('#moduloComprasFacturas'),
            allowClear: true,
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: [{id: 'todos', text: '-- TODOS LOS PROVEEDORES --'}, ...d.data] })
            }
        });
    }

    function buscarForce() {
        clearTimeout(debounceTimer);
        buscar();
    }

    async function buscar() {
        const filters = getFilters();
        const tbody = $('#tbody-compras');
        const cards = $('#tblCards');
        
        tbody.html('<tr><td colspan="10" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Buscando facturas...</td></tr>');
        cards.html('<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin mb-2 text-info fs-3"></i><br>Buscando...</div>');
        $('#panelTotales').hide();

        try {
            const res = await fetch('api_proveedores_facturas_admin.php?action=list', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters)
            }).then(r => r.json());
            
            if (res.success) { 
                listado = res.data; 
                updateTotals(res.resumen); 
                
                pagActual = 1; window.pagActual = 1;
                renderPagina();
            } else {
                tbody.html(`<tr><td colspan="10" class="text-center py-5 text-danger">${res.error || 'Error de conexión'}</td></tr>`);
                cards.html(`<div class="text-center text-danger p-5">${res.error || 'Error de conexión'}</div>`);
            }
        } catch (e) { 
            console.error(e); 
            tbody.html('<tr><td colspan="10" class="text-center py-5 text-danger">Error de red.</td></tr>');
            cards.html('<div class="text-center text-danger p-5">Error de red.</div>');
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = listado.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listado.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
        
        if (total > 0) $('#panelTotales').show();
    }

    function renderTabla(data) {
        const tbody = $('#tbody-compras').empty();
        
        if (data.length === 0) { 
            tbody.append('<tr><td colspan="10" class="text-center py-5 text-muted small italic">No hay resultados con los filtros actuales</td></tr>'); 
            return; 
        }

        data.forEach((f, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const isNC = [3, 8, 13, 101].includes(parseInt(f.tc_cod));
            const rowClass = isNC ? 'bg-warning bg-opacity-10' : '';
            
            const fv = f['Fecha Vencimiento'] ? f['Fecha Vencimiento'].split('-').reverse().join('-') : '-';
            const ff = f['Fecha'] ? f['Fecha'].split('-').reverse().join('-') : '-';
            
            tbody.append(`
                <tr class="${rowClass} pointer">
                    <td class="ps-3">${ff}</td>
                    <td class="text-muted">${fv}</td>
                    <td class="fw-bold">${f.Tipo}</td>
                    <td class="fw-bold text-white">${f['Num Factura']}</td>
                    <td><div class="text-truncate" style="max-width:180px" title="${f.Proveedor}">${f.Proveedor}</div></td>
                    <td class="text-end">$ ${parseFloat(f.Neto).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td class="text-end text-white-50">$ ${parseFloat(f['IVA Total']).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td class="text-end fw-bold text-accent">$ ${parseFloat(f.Total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td class="text-center"><span class="badge ${f.Estado === 'Pagada' ? 'bg-success text-white' : 'bg-warning text-dark'} opacity-75">${f.Estado}</span></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <button class="btn btn-info p-1 px-2" onclick="window.viewFact(${absoluteIdx})" title="Ver Detalle"><i class="fas fa-eye"></i></button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards').empty();

        if (!data || data.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No hay resultados.</div>');
            return;
        }

        data.forEach((f, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const isNC = [3, 8, 13, 101].includes(parseInt(f.tc_cod));
            const rowClass = isNC ? 'border-warning border-opacity-50' : '';
            
            const fv = f['Fecha Vencimiento'] ? f['Fecha Vencimiento'].split('-').reverse().join('-') : '-';
            const ff = f['Fecha'] ? f['Fecha'].split('-').reverse().join('-') : '-';
            
            const estadoCls = f.Estado === 'Pagada' ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' : 'background:rgba(255,193,7,.2);color:#ffc107;border:1px solid #ffc107;';

            wrap.append(`
            <div class="erp-card mb-2 ${rowClass}">
                <div class="erp-card-header mb-1">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 210px;">${f.Proveedor}</div>
                        <span class="erp-card-badge ms-1" style="${estadoCls}">${f.Estado.toUpperCase()}</span>
                    </div>
                    <span class="erp-card-code text-info">${f.Tipo}</span>
                </div>
                
                <div class="erp-card-row">
                    <span class="erp-card-label">Número</span>
                    <span class="erp-card-value fw-bold text-white">${f['Num Factura']}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Emisión / Vence</span>
                    <span class="erp-card-value">${ff} <span class="text-muted mx-1">|</span> <span class="text-warning">${fv}</span></span>
                </div>
                
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <div class="w-100 d-flex justify-content-between align-items-end">
                        <div>
                            <span class="erp-card-label d-block text-white-50">Neto: $${parseFloat(f.Neto).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                            <span class="erp-card-label d-block text-white-50 mt-1">IVA: $${parseFloat(f['IVA Total']).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="text-end ms-2">
                            <span class="erp-card-label d-block text-accent">Total</span>
                            <span class="erp-card-value price fs-5 text-accent">$ ${parseFloat(f.Total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>

                <div class="erp-card-actions mt-2">
                    <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="window.viewFact(${absoluteIdx})">
                        <i class="fas fa-eye me-1"></i> VER DETALLE
                    </button>
                </div>
            </div>
            `);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> facturas`;
        
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ 
                cont.innerHTML=''; 
                if(pagTotal>1){ 
                    var ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4); 
                    if(fin-ini<4) ini=Math.max(1,fin-4); 
                    for(var i=ini; i<=fin; i++){
                        var b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = (function(p){return function(){pagGo(p);};})(i);
                        cont.appendChild(b);
                    } 
                } 
            }
            var ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            var sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); 
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloComprasFacturas')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };


    function updateTotals(r) {
        const fmt = v => '$ ' + parseFloat(v||0).toLocaleString('es-AR', {minimumFractionDigits: 2});
        $('#sum-sub').text(fmt(r.subt)); 
        $('#sum-iva').text(fmt(r.iva)); 
        $('#sum-imp').text(fmt((parseFloat(r.ii)||0) + (parseFloat(r.riibb)||0) + (parseFloat(r.riva)||0)));
        $('#sum-total').text(fmt(r.total)); 
        $('#sum-ret').text(fmt((parseFloat(r.rgan)||0) + (parseFloat(r.riibb)||0) + (parseFloat(r.riva)||0)));
    }

    // Modal y Funciones Originales
    window.viewFact = async (idx) => {
        const f = listado[idx];
        const res = await fetch(`api_proveedores_facturas_admin.php?action=get_lines&id=${f.ID}`, { headers: commonHeaders }).then(r => r.json());
        
        if (res.success) {
            const fmt = v => '$ ' + parseFloat(v).toLocaleString('es-AR', {minimumFractionDigits: 2});

            $('#det-prov').text(f.Proveedor);
            $('#det-tipo-num').text(`${f.Tipo} ${f['Num Factura']}`);
            $('#det-total').text(fmt(f.Total));
            $('#det-f-fac').text(f.Fecha.split('-').reverse().join('-'));
            $('#det-f-vto').text(f['Fecha Vencimiento'] ? f['Fecha Vencimiento'].split('-').reverse().join('-') : '-');
            $('#det-f-imp').text(f['Fecha Imputacion'] ? f['Fecha Imputacion'].split('-').reverse().join('-') : '-');
            $('#det-notas').text(f.Notas || 'Sin observaciones adicionales');
            $('#det-estado').text(f.Estado.toUpperCase()).removeClass('bg-success bg-warning text-dark text-white border-success border-warning')
                           .addClass(f.Estado === 'Pagada' ? 'bg-success text-white border-success' : 'bg-warning text-dark border-warning');

            // Auditoría
            $('#det-usr-carga').text(f['Usuario Carga']);
            $('#det-fec-registro').text(f['Fecha Registro']);

            // Totales Resumen Modal
            $('#det-val-neto').text(fmt(f.Neto));
            $('#det-val-iva').text(fmt(f['IVA Total']));
            $('#det-val-ii').text(fmt(f.ImpInt));
            
            $('#det-val-piva').text(fmt(f['Ret IVA']));
            $('#det-val-piibb').text(fmt(f['Ret IIBB']));
            $('#det-val-pgan').text(fmt(f['Ret Ganancias']));
            
            $('#det-val-total').text(fmt(f.Total));

            const tbody = $('#det-tbody-conceptos').empty();
            if(res.data.length === 0) tbody.append('<tr><td colspan="3" class="text-center p-3 text-muted">Carga resumida sin desglose</td></tr>');
            res.data.forEach(line => {
                tbody.append(`<tr><td class="text-white">${line.conceptogasto || line.concepto}</td><td class="text-end">${fmt(line.importe)}</td><td class="text-center">${line.alicuota}%</td></tr>`);
            });
            $('#modalViewFact').modal('show');
        }
    };

    window.delFact = async (idx) => {
        const f = listado[idx];
        const conf = await Swal.fire({ title: `¿Eliminar ${f.Tipo} ${f['Num Factura']}?`, text: "Esta acción borrará el comprobante del CC y el Libro Diario.", icon: 'warning', showCancelButton: true });
        if (conf.isConfirmed) {
            const res = await fetch('api_proveedores_facturas_admin.php?action=delete', { method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' }, body: JSON.stringify({ id: f.ID }) }).then(r => r.json());
            if (res.success) { Swal.fire('Éxito', 'Comprobante eliminado.', 'success'); buscar(); }
            else Swal.fire('Error', res.error, 'error');
        }
    };

    window.unimputeNC = async (idx) => {
        const f = listado[idx];
        if (await Swal.fire({ title: '¿Desimputar?', text: 'Se liberará el saldo de la NC.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_proveedores_facturas_admin.php?action=unimpute_nc', { method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' }, body: JSON.stringify({ id: f.ID }) }).then(r => r.json());
            if (res.success) { Swal.fire('Éxito', 'Desimputación completada.', 'success'); buscar(); }
            else Swal.fire('Error', res.error, 'error');
        }
    };

    window.editDates = async (idx) => {
        const f = listado[idx];
        const { value: formValues } = await Swal.fire({
            title: 'Actualizar Datos',
            html: `<div class="text-start"><label class="small text-muted">Fecha Factura</label><input id="swal-f-fact" type="date" class="form-control mb-2" value="${f.Fecha}"><label class="small text-muted">Fecha Vencimiento</label><input id="swal-f-vto" type="date" class="form-control mb-2" value="${f['Fecha Vencimiento']}"><label class="small text-muted">Notas</label><textarea id="swal-notas" class="form-control">${f.Notas || ''}</textarea></div>`,
            showCancelButton: true,
            preConfirm: () => ({ f: $('#swal-f-fact').val(), v: $('#swal-f-vto').val(), n: $('#swal-notas').val() })
        });
        if (formValues) {
            await fetch('api_proveedores_facturas_admin.php?action=update_field', { method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: f.ID, field: 'fecha', value: formValues.f }) });
            await fetch('api_proveedores_facturas_admin.php?action=update_field', { method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: f.ID, field: 'fechavencimiento', value: formValues.v }) });
            await fetch('api_proveedores_facturas_admin.php?action=update_field', { method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: f.ID, field: 'notas', value: formValues.n }) });
            buscar();
        }
    };

    function exportarExcel() {
        const params = new URLSearchParams({ ...getFilters(), action: 'export_excel' }).toString();
        window.open(`api_proveedores_facturas_admin.php?${params}`, '_blank');
    }

    function exportarArca() {
        Swal.fire({ title: 'ARCA', text: 'Tipo de Archivo:', showDenyButton: true, confirmButtonText: 'COMPROBANTES', denyButtonText: 'ALÍCUOTAS' }).then((result) => {
            const act = result.isConfirmed ? 'export_arca_comp' : (result.isDenied ? 'export_arca_ali' : null);
            if(act) window.open(`api_proveedores_facturas_admin.php?${new URLSearchParams({...getFilters(), action: act}).toString()}`, '_blank');
        });
    }

    function getFilters() {
        return {
            desde: $('#f-desde').val(), hasta: $('#f-hasta').val(), tipo_fecha: $('#cmb-tipo-fecha').val(),
            proveedor: $('#cmb-proveedor').val() || 'todos', tipo_comp: $('#cmb-tipo-comp').val(),
            filtro_importe: $('#cmb-logic-imp').val(), valor_importe: $('#txt-val-imp').val(),
            solo_pendientes: $('#chk-solo-pend').is(':checked')
        };
    }

    init();
})();
</script>