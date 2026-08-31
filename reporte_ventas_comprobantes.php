<?php
/**
 * comprobantes_emitidos.php - REPORTE DE COMPROBANTES EMITIDOS
 * Migración de Informes - Comprobantes.vb (Ajustado a tablas reales y Paginación SPA)
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$hoy = date('Y-m-d');
$mes_atras = date('Y-m-d');
?>

<style>
    /* Refuerzo de fondo oscuro para el totalizador */
    .card-totales-dark {
        background-color: #000a1a !important;
        border: 1px solid #445566 !important;
        border-radius: 8px;
    }

    .text-accent {
        color: #00ffffdc !important;
    }
</style>

<div class="container-fluid px-4 py-3 fade-in" id="moduloComprobantes">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-accent"></i>COMPROBANTES EMITIDOS</h4>
            <small class="text-white-50">Informe detallado de facturación (Tabla: Tickets)</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 rounded-pill px-3 btn-sm fw-bold" onclick="exportarExcelComprobantes()">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" onclick="consultarComprobantesForce()">
                <i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros mb-3 border-0 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-3 border-md-end border-secondary border-opacity-25">
                    <label class="form-label-custom">RANGO FECHAS</label>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-dark border-secondary text-white-50">Desde</span>
                        <input type="date" id="fDesde" class="form-control filter-auto">
                    </div>
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-dark border-secondary text-white-50">Hasta</span>
                        <input type="date" id="fHasta" class="form-control filter-auto">
                    </div>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input filter-auto" type="radio" name="rTipoFecha" id="fFac" value="fecha" checked>
                            <label class="form-check-label text-white small" for="fFac">F. Factura</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-auto" type="radio" name="rTipoFecha" id="fRem" value="fecharemito">
                            <label class="form-check-label text-white small" for="fRem">F. Remito</label>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-9">
                    <div class="row g-2">
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cCliente">
                                <label class="form-check-label text-white-50 small">Cliente</label>
                            </div>
                            <select id="cCliente" class="form-select form-select-sm select2-remote filter-auto" disabled></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cVendedor">
                                <label class="form-check-label text-white-50 small">Vendedor</label>
                            </div>
                            <select id="cVendedor" class="form-select form-select-sm select2-remote filter-auto" disabled></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cTipo">
                                <label class="form-check-label text-white-50 small">Tipo Comprobante</label>
                            </div>
                            <select id="cTipo" class="form-select form-select-sm filter-auto" disabled>
                                <option value="FACTURA A">FACTURA A</option>
                                <option value="NOTA DE CREDITO A">NOTA DE CREDITO A</option>
                                <option value="FACTURA B">FACTURA B</option>
                                <option value="NOTA DE CREDITO B">NOTA DE CREDITO B</option>
                                <option value="TICKET">TICKET</option>
                                <option value="TICKET DE CREDITO">TICKET DE CREDITO</option>
                                <option value="FISCALES">FISCALES</option>
                                <option value="NO FISCALES">NO FISCALES</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cMPago">
                                <label class="form-check-label text-white-50 small">Medio de Pago</label>
                            </div>
                            <select id="cMPago" class="form-select form-select-sm select2-remote filter-auto" disabled></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cUnidad">
                                <label class="form-check-label text-white-50 small">Unidad Negocio</label>
                            </div>
                            <select id="cUnidad" class="form-select form-select-sm select2-remote filter-auto" disabled></select>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cProv">
                                <label class="form-check-label text-white-50 small">Provincia</label>
                            </div>
                            <select id="cProv" class="form-select form-select-sm filter-auto" disabled>
                                <option value="BUENOS AIRES">Buenos Aires</option>
                                <option value="CABA">CABA</option>
                                <option value="CATAMARCA">Catamarca</option>
                                <option value="CHACO">Chaco</option>
                                <option value="CHUBUT">Chubut</option>
                                <option value="CORDOBA">Córdoba</option>
                                <option value="CORRIENTES">Corrientes</option>
                                <option value="ENTRE RIOS">Entre Ríos</option>
                                <option value="FORMOSA">Formosa</option>
                                <option value="JUJUY">Jujuy</option>
                                <option value="LA PAMPA">La Pampa</option>
                                <option value="LA RIOJA">La Rioja</option>
                                <option value="MENDOZA">Mendoza</option>
                                <option value="MISIONES">Misiones</option>
                                <option value="NEUQUEN">Neuquén</option>
                                <option value="RIO NEGRO">Río Negro</option>
                                <option value="SALTA">Salta</option>
                                <option value="SAN JUAN">San Juan</option>
                                <option value="SAN LUIS">San Luis</option>
                                <option value="SANTA CRUZ">Santa Cruz</option>
                                <option value="SANTA FE">Santa Fe</option>
                                <option value="SANTIAGO DEL ESTERO">Santiago del Estero</option>
                                <option value="TIERRA DEL FUEGO, ANTÁRTIDA E ISLAS DEL ATLÁNTICO SUR">Tierra del Fuego, Antártida e Isla del Atlántico Sur</option>
                                <option value="TUCUMAN">Tucumán</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2" hidden>
                        <div class="form-check form-switch">
                            <input class="form-check-input filter-auto" type="checkbox" id="chkSinRemitir">
                            <label class="form-check-label text-warning small fw-bold">Solo sin remitir (remitidototal = 0)</label>
                        </div>
                    </div>
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
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 50vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm mb-0 small text-nowrap" id="tablaEmitidos">
            <thead class="sticky-top bg-black">
                <tr>
                    <th class="ps-3 py-2">FECHA/HORA</th>
                    <th>TIPO</th>
                    <th>NÚMERO</th>
                    <th>CLIENTE</th>
                    <th class="text-end">SUBTOTAL</th>
                    <th class="text-end">IVA</th>
                    <th class="text-end">TOTAL</th>
                    <th>PAGO 1 / IMPORTE</th>
                    <th>PAGO 2 / IMPORTE</th>
                    <th>VENDEDOR</th>
                    <th>USUARIO</th>
                    <th class="text-center">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyEmitidos">
                <tr><td colspan="12" class="text-center py-5 text-muted">Ajuste los filtros y presione "Consultar"</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">Ajuste los filtros y presione "Consultar"</div>
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

    <!-- Totales Footer Responsivo -->
    <div class="card card-totales-dark mt-2 mb-4 shadow-lg">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center g-2">
                <div class="col-12 col-md-9">
                    <div class="row text-center g-2">
                        <div class="col-6 col-md-3 border-md-end border-secondary border-opacity-25">
                            <small class="text-white-50 d-block" style="font-size: 10px;">SUBTOTAL TOTAL</small>
                            <span class="fw-bold text-white fs-5" id="totSub">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3 border-md-end border-secondary border-opacity-25">
                            <small class="text-white-50 d-block" style="font-size: 10px;">IVA TOTAL</small>
                            <span class="fw-bold text-white fs-5" id="totIVA">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3 border-md-end border-secondary border-opacity-25">
                            <small class="text-white-50 d-block" style="font-size: 10px;">IMP. INTERNOS</small>
                            <span class="fw-bold text-white fs-5" id="totII">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-white-50 d-block" style="font-size: 10px;">DESC/RECA</small>
                            <span class="fw-bold text-white fs-5" id="totDR">$ 0,00</span>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-3 border-md-start border-accent border-4 py-1 text-center text-md-start mt-3 mt-md-0">
                    <div class="ps-md-3">
                        <small class="text-accent d-block fw-bold text-uppercase" style="font-size: 10px; letter-spacing: 1px;">Total General Reporte</small>
                        <span class="h3 text-accent fw-bold mb-0" id="totGral">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let debounceTimer;
        let listadoComprobantes = [];
        let pagActual = 1, pagTotal = 1, pagTam = 20;

        window.pagActual = 1; window.pagTotal = 1;

        async function init() {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };

            try {
                const [rCli, rVend, rMPag, rUnid] = await Promise.all([
                    fetch('api_helpers.php?action=get_clientes', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_vendedores', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_medios_pago', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_unidades_negocio', { headers: h }).then(r => r.json())
                ]);

                fillSelect('#cCliente', rCli, 'razon');
                fillSelect('#cVendedor', rVend, 'nombre');
                fillSelect('#cMPago', rMPag, 'medio');
                fillSelect('#cUnidad', rUnid, 'nombre');

                // Lógica de Switches
                $('.chk-toggle').on('change', function () {
                    const target = $(this).data('target');
                    const isChecked = $(this).is(':checked');
                    $(target).prop('disabled', !isChecked);
                    if (!isChecked) {
                        if ($(target).hasClass('select2-hidden-accessible')) { $(target).val(null).trigger('change'); }
                        else { $(target).val(''); }
                    }
                    consultarComprobantes();
                });

                // Listener para filtrado automático (con debounce de inputs)
                $('.filter-auto').on('input change', function () {
                    consultarComprobantes();
                });

                // Inicialización Select2
                $('.select2-remote').select2({ dropdownParent: $('#moduloComprobantes'), width: '100%', placeholder: 'SELECCIONE...' });

                // Consultar inicialmente
                setFechasIniciales();
                consultarComprobantesForce(); // Carga forzada inicial sin debounce

            } catch (e) { console.error("Error inicializando filtros:", e); }
        }

        // Wrapper para saltar el debounce en consultas de botones
        window.consultarComprobantesForce = function() {
            clearTimeout(debounceTimer);
            ejecutarConsulta();
        };

        window.consultarComprobantes = function () {
            clearTimeout(debounceTimer);
            
            // Validación de año correcto mientras el usuario tipea (Evitar llamadas fallidas al backend)
            const desde = $('#fDesde').val();
            const hasta = $('#fHasta').val();
            
            if (desde) {
                const yearDesde = parseInt(desde.split('-')[0]);
                if (yearDesde < 2000) return;
            }
            if (hasta) {
                const yearHasta = parseInt(hasta.split('-')[0]);
                if (yearHasta < 2000) return;
            }

            debounceTimer = setTimeout(() => { ejecutarConsulta(); }, 800);
        }

        async function ejecutarConsulta() {
            const payload = {
                desde: $('#fDesde').val(),
                hasta: $('#fHasta').val(),
                tipo_fecha: $('input[name="rTipoFecha"]:checked').val(),
                cliente_id: $('#cCliente').is(':disabled') ? null : $('#cCliente').val(),
                vendedor_id: $('#cVendedor').is(':disabled') ? null : $('#cVendedor').val(),
                tipo_comp: $('#cTipo').is(':disabled') ? null : $('#cTipo').val(),
                mpago_txt: $('#cMPago').is(':disabled') ? null : $('#cMPago option:selected').text(),
                unidad_id: $('#cUnidad').is(':disabled') ? null : $('#cUnidad').val(),
                provincia: $('#cProv').is(':disabled') ? null : $('#cProv').val(),
                sin_remitir: $('#chkSinRemitir').is(':checked')
            };

            const tbody = $('#tbodyEmitidos');
            const cards = $('#tblCards');
            
            tbody.html('<tr><td colspan="12" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Buscando comprobantes...</td></tr>');
            cards.html('<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin mb-2 text-info fs-3"></i><br>Buscando...</div>');

            try {
                const res = await fetch('api_comprobantes_emitidos.php?action=list', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(payload)
                }).then(r => r.json());

                if (res.success) { 
                    listadoComprobantes = res.data || [];
                    
                    // Cálculo de totales Globales
                    let tSub = 0, tIva = 0, tII = 0, tDR = 0, tGral = 0;
                    listadoComprobantes.forEach(row => {
                        const sub = parseFloat(row.subtotal_calc) || 0;
                        const iva = parseFloat(row.iva_calc) || 0;
                        const imp = parseFloat(row.imp_int) || 0;
                        const total = parseFloat(row.total) || 0;
                        const factor = ([3, 8, 13, 101].includes(parseInt(row.tipocomp))) ? -1 : 1;

                        tSub += (sub * factor); tIva += (iva * factor); tII += (imp * factor);
                        tDR += ((parseFloat(row.recargo) - parseFloat(row.descuento)) * factor);
                        tGral += (total * factor);
                    });
                    
                    actualizarTotales(tSub, tIva, tII, tDR, tGral);
                    
                    // Reiniciar paginación y renderizar
                    pagActual = 1; window.pagActual = 1;
                    renderPagina();
                }
                else { Swal.fire('Error', res.error, 'error'); }
            } catch (e) { 
                tbody.html('<tr><td colspan="12" class="text-center text-danger">Error de red.</td></tr>'); 
                cards.html('<div class="text-center text-danger p-5">Error de red.</div>');
            }
        }

        /* ============================================================
           RENDERIZADO SPA & PAGINACIÓN
        ============================================================ */
        function renderPagina() {
            const total = listadoComprobantes.length;
            pagTotal = Math.max(1, Math.ceil(total / pagTam));
            if (pagActual > pagTotal) pagActual = pagTotal;
            window.pagActual = pagActual; window.pagTotal = pagTotal;

            const datos = listadoComprobantes.slice((pagActual - 1) * pagTam, pagActual * pagTam);

            renderTabla(datos);
            renderTarjetas(datos);
            sincronizarPaginacion(total);
        }

        function renderTabla(data) {
            const tbody = $('#tbodyEmitidos'); tbody.empty();

            if (!data || data.length === 0) {
                tbody.html('<tr><td colspan="12" class="text-center py-5 text-muted">Sin movimientos encontrados.</td></tr>');
                return;
            }

            data.forEach(row => {
                const sub = parseFloat(row.subtotal_calc) || 0;
                const iva = parseFloat(row.iva_calc) || 0;
                const total = parseFloat(row.total) || 0;

                tbody.append(`
                <tr class="align-middle">
                    <td class="ps-3">${formatDate(row.fecha)} ${row.hora}</td>
                    <td class="fw-bold">${row.tipo_str}</td>
                    <td class="text-info">${row.ptovta.toString().padStart(4, '0')}-${row.numcomp.toString().padStart(8, '0')}</td>
                    <td class="text-truncate" style="max-width:180px">${row.cliente_nombre}</td>
                    <td class="text-end">$ ${formatMoney(sub)}</td>
                    <td class="text-end">$ ${formatMoney(iva)}</td>
                    <td class="text-end fw-bold text-accent">$ ${formatMoney(total)}</td>
                    <td class="small text-white-50">${row.mpago1 || '-'}<br>$ ${formatMoney(row.mpago1imp)}</td>
                    <td class="small text-white-50">${row.mpago2 || '-'}<br>$ ${formatMoney(row.mpago2imp)}</td>
                    <td class="small text-truncate" style="max-width:110px">${row.vendedor_nombre || 'S/V'}</td>
                    <td class="small text-white-50 text-truncate" style="max-width:90px">${row.usuario || '-'}</td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info p-1 px-2 shadow-sm" onclick="verComprobante(${row.ticket_id})" title="Ver / Imprimir">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `);
            });
        }

        function renderTarjetas(data) {
            const wrap = $('#tblCards'); wrap.empty();

            if (!data || data.length === 0) {
                wrap.html('<div class="text-center text-muted p-5 opacity-50 italic">Sin movimientos encontrados.</div>');
                return;
            }

            data.forEach(row => {
                const sub = parseFloat(row.subtotal_calc) || 0;
                const iva = parseFloat(row.iva_calc) || 0;
                const total = parseFloat(row.total) || 0;
                
                // Color distintivo para NC o Facturas
                const factor = ([3, 8, 13, 101].includes(parseInt(row.tipocomp))) ? -1 : 1;
                const totalColorClass = factor === -1 ? 'text-danger' : 'text-accent';

                wrap.append(`
                <div class="erp-card">
                    <div class="erp-card-header mb-1">
                        <div>
                            <div class="erp-card-title text-truncate" style="max-width: 230px;">${row.cliente_nombre || 'SIN CLIENTE'}</div>
                            <span class="erp-card-badge ms-1 bg-dark text-white border border-secondary">${row.tipo_str}</span>
                        </div>
                        <span class="erp-card-code text-info">${row.ptovta.toString().padStart(4, '0')}-${row.numcomp.toString().padStart(8, '0')}</span>
                    </div>
                    
                    <div class="erp-card-row">
                        <span class="erp-card-label">Fecha / Hora</span>
                        <span class="erp-card-value">${formatDate(row.fecha)} <span class="text-muted mx-1">|</span> <span class="text-warning">${row.hora}</span></span>
                    </div>
                    
                    <div class="erp-card-row">
                        <span class="erp-card-label">Vend / Usuario</span>
                        <span class="erp-card-value text-truncate" style="max-width: 65%;">${row.vendedor_nombre || 'S/V'} <span class="text-muted mx-1">/</span> ${row.usuario || '-'}</span>
                    </div>

                    <div class="erp-card-row">
                        <span class="erp-card-label">Medios Pago</span>
                        <span class="erp-card-value text-muted text-truncate" style="font-size: 10px; max-width: 70%;">
                            ${row.mpago1 || '-'}: $${formatMoney(row.mpago1imp)} <br>
                            ${row.mpago2 || '-'}: $${formatMoney(row.mpago2imp)}
                        </span>
                    </div>

                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <div class="w-100 d-flex justify-content-between align-items-end">
                            <div>
                                <span class="erp-card-label d-block text-white-50">Sub: $${formatMoney(sub)} | IVA: $${formatMoney(iva)}</span>
                            </div>
                            <div class="text-end ms-2">
                                <span class="erp-card-label d-block ${totalColorClass} mt-1">Total</span>
                                <span class="erp-card-value price fs-5 ${totalColorClass}">$ ${formatMoney(total)}</span>
                            </div>
                        </div>
                    </div>

                    <div class="erp-card-actions mt-2">
                        <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="verComprobante(${row.ticket_id})">
                            <i class="fas fa-eye me-1"></i> VER COMPROBANTE
                        </button>
                    </div>
                </div>
                `);
            });
        }

        function sincronizarPaginacion(total) {
            const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
            const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;
            
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

        window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloComprobantes')?.scrollIntoView({behavior:'smooth'}); };
        window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
        window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
        window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
        window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };


        function actualizarTotales(s, i, ii, dr, g) {
            $('#totSub').text('$ ' + formatMoney(s)); $('#totIVA').text('$ ' + formatMoney(i));
            $('#totII').text('$ ' + formatMoney(ii)); $('#totDR').text('$ ' + formatMoney(dr));
            $('#totGral').text('$ ' + formatMoney(g));
        }

        window.verComprobante = function (id) {
            window.open('imprimir_ticket.php?id=' + id, '_blank');
        }

        window.generarNC = function (id, tipo) {
            Swal.fire({
                title: 'Generar Crédito',
                text: `¿Desea crear un comprobante de crédito asociado a este ${tipo}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'SÍ, GENERAR',
                cancelButtonText: 'CANCELAR'
            }).then(r => { if (r.isConfirmed) { loadModule(`newfactura.php?asociado=${id}&tipo_nc=true`); } });
        }

        // --- NUEVA LÓGICA DE EXPORTACIÓN EXCEL NATIVA ---
        window.exportarExcelComprobantes = function () {
            if (!listadoComprobantes || listadoComprobantes.length === 0) {
                Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay comprobantes cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                return;
            }

            const desde = $('#fDesde').val().split('-').reverse().join('/');
            const hasta = $('#fHasta').val().split('-').reverse().join('/');

            let excelHtml = `
                <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta charset="utf-8">
                    <style>
                        .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                        .title { font-size: 14pt; font-weight: bold; text-align: left; }
                        .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                        .text { text-align: left; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                        .center { text-align: center; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    </style>
                </head>
                <body>
                    <table>
                        <tr><td colspan="11" class="title">REPORTE DE COMPROBANTES EMITIDOS</td></tr>
                        <tr><td colspan="11">PERIODO: ${desde} AL ${hasta}</td></tr>
                        <tr><td colspan="11"></td></tr>
                        <thead>
                            <tr>
                                <th class="header">FECHA/HORA</th>
                                <th class="header">TIPO</th>
                                <th class="header">NÚMERO</th>
                                <th class="header">CLIENTE</th>
                                <th class="header">SUBTOTAL</th>
                                <th class="header">IVA</th>
                                <th class="header">TOTAL</th>
                                <th class="header">PAGO 1 / IMPORTE</th>
                                <th class="header">PAGO 2 / IMPORTE</th>
                                <th class="header">VENDEDOR</th>
                                <th class="header">USUARIO</th>
                            </tr>
                        </thead>
                        <tbody>
            `;

            let tSub = 0, tIva = 0, tTotal = 0;

            listadoComprobantes.forEach(row => {
                const sub = parseFloat(row.subtotal_calc) || 0;
                const iva = parseFloat(row.iva_calc) || 0;
                const total = parseFloat(row.total) || 0;
                const factor = ([3, 8, 13, 101].includes(parseInt(row.tipocomp))) ? -1 : 1;

                tSub += (sub * factor);
                tIva += (iva * factor);
                tTotal += (total * factor);

                excelHtml += `
                    <tr>
                        <td class="center">${formatDate(row.fecha)} ${row.hora}</td>
                        <td class="center" style="font-weight:bold;">${row.tipo_str}</td>
                        <td class="center" style="color:#17a2b8; font-weight:bold; mso-number-format:'\\@';">${row.ptovta.toString().padStart(4, '0')}-${row.numcomp.toString().padStart(8, '0')}</td>
                        <td class="text">${(row.cliente_nombre || '').toUpperCase()}</td>
                        <td class="number">${(sub * factor)}</td>
                        <td class="number">${(iva * factor)}</td>
                        <td class="number" style="font-weight:bold; color:#006666;">${(total * factor)}</td>
                        <td class="text">${(row.mpago1 || '-').toUpperCase()} $${formatMoney(row.mpago1imp)}</td>
                        <td class="text">${(row.mpago2 || '-').toUpperCase()} $${formatMoney(row.mpago2imp)}</td>
                        <td class="text">${(row.vendedor_nombre || 'S/V').toUpperCase()}</td>
                        <td class="text">${(row.usuario || '').toUpperCase()}</td>
                    </tr>
                `;
            });

            excelHtml += `
                        <tr>
                            <td colspan="4" style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTALES GENERALES:</td>
                            <td class="number" style="font-weight:bold;">${tSub}</td>
                            <td class="number" style="font-weight:bold;">${tIva}</td>
                            <td class="number" style="font-weight:bold; color:#006666; background-color:#e6f9f9;">${tTotal}</td>
                            <td colspan="4"></td>
                        </tr>
                    </tbody></table></body></html>
            `;

            const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
            const link = document.createElement("a");
            link.href = URL.createObjectURL(blob);
            link.download = `Comprobantes_Emitidos_${new Date().getTime()}.xls`;
            link.click();
        }

        function fillSelect(sel, res, field) {
            const el = $(sel); el.empty().append(new Option('SELECCIONE...', '', true, true));
            if (res && res.success && res.data) {
                res.data.forEach(d => {
                    let label = d[field] || d.text || d.nombre || d.razon || 'S/D';
                    el.append(new Option(label.toString().toUpperCase(), d.id));
                });
            }
        }
        function setFechasIniciales() {
            const hoy = new Date();
            const primerDia = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

            const toInput = d => d.toISOString().split('T')[0];

            $('#fDesde').val(toInput(hoy));
            $('#fHasta').val(toInput(hoy));
        }

        function formatMoney(n) { return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); }
        function formatDate(d) { if (!d) return '-'; const p = d.split(' ')[0].split('-'); return `${p[2]}/${p[1]}/${p[0]}`; }

        init();
    })();
</script>