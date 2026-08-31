<?php
/**
 * comprobantes_emitidos.php - REPORTE DE COMPROBANTES EMITIDOS
 * Migración de Informes - Comprobantes.vb (Ajustado a tablas reales)
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$hoy = date('Y-m-d');
$mes_atras = date('Y-m-d');
?>

<style>
    .card-totales-dark {
        background-color: var(--bg-card) !important;
        color: var(--text-main);
        border: 1px solid var(--border-color) !important;
        border-radius: 6px;
    }
    .comprobantes-chart-panel {
        height: 100%;
        padding: 14px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 6px;
    }
    .comprobantes-chart-panel h5 { margin: 0 0 12px; color: var(--text-titles); font-size: 14px; font-weight: 700; text-transform: uppercase; }
    .comprobantes-chart-panel h5 i { color: var(--accent); margin-right: 6px; }
    .comprobantes-chart-canvas { position: relative; height: 320px; min-width: 0; }
    .comprobantes-chart-wide { height: 360px; }
    @media (max-width: 768px) { .comprobantes-chart-canvas, .comprobantes-chart-wide { height: 280px; } }
</style>

<div class="container-fluid px-4 py-3 fade-in" id="moduloComprobantes">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-file-invoice-dollar me-2 text-accent"></i>COMPROBANTES
                EMITIDOS</h4>
            <small class="text-white-50">Informe detallado de facturación (Tabla: Tickets)</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success border-0 rounded-pill px-3" onclick="exportarExcelComprobantes()">
                <i class="fa-solid fa-file-excel me-1"></i>EXCEL
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow" onclick="consultarComprobantes()">
                <i class="fa-solid fa-magnifying-glass me-1"></i>CONSULTAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros mb-3 border-0 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3 border-end border-secondary border-opacity-25">
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
                            <input class="form-check-input filter-auto" type="radio" name="rTipoFecha" id="fFac"
                                value="fecha" checked>
                            <label class="form-check-label text-white small" for="fFac">F. Factura</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-auto" type="radio" name="rTipoFecha" id="fRem"
                                value="fecharemito">
                            <label class="form-check-label text-white small" for="fRem">F. Remito</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-9">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cCliente">
                                <label class="form-check-label text-white-50 small">Cliente</label>
                            </div>
                            <select id="cCliente" class="form-select form-select-sm select2-remote filter-auto"
                                disabled></select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cVendedor">
                                <label class="form-check-label text-white-50 small">Vendedor</label>
                            </div>
                            <select id="cVendedor" class="form-select form-select-sm select2-remote filter-auto"
                                disabled></select>
                        </div>
                        <div class="col-md-4">
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
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cMPago">
                                <label class="form-check-label text-white-50 small">Medio de Pago</label>
                            </div>
                            <select id="cMPago" class="form-select form-select-sm select2-remote filter-auto"
                                disabled></select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cUnidad">
                                <label class="form-check-label text-white-50 small">Unidad Negocio</label>
                            </div>
                            <select id="cUnidad" class="form-select form-select-sm select2-remote filter-auto"
                                disabled></select>
                        </div>
                        <div class="col-md-4">
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
                                <option value="TIERRA DEL FUEGO, ANTÁRTIDA E ISLAS DEL ATLÁNTICO SUR">Tierra del Fuego,
                                    Antártida e Isla del Atlántico Sur</option>
                                <option value="TUCUMAN">Tucumán</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mb-1">
                                <input class="form-check-input chk-toggle" type="checkbox" data-target="#cRama">
                                <label class="form-check-label text-white-50 small">Rama Cliente</label>
                            </div>
                            <select id="cRama" class="form-select form-select-sm select2-remote filter-auto"
                                disabled></select>
                        </div>
                    </div>
                    <div class="mt-2" hidden>
                        <div class="form-check form-switch">
                            <input class="form-check-input filter-auto" type="checkbox" id="chkSinRemitir">
                            <label class="form-check-label text-warning small fw-bold">Solo sin remitir (remitidototal =
                                0)</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3" id="comprobantesViewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="comprobantes-detalle-tab" data-bs-toggle="tab" data-bs-target="#comprobantes-detalle-pane" type="button" role="tab" aria-controls="comprobantes-detalle-pane" aria-selected="true">
                <i class="fa-solid fa-table-list me-2"></i>DETALLE
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="comprobantes-graficos-tab" data-bs-toggle="tab" data-bs-target="#comprobantes-graficos-pane" type="button" role="tab" aria-controls="comprobantes-graficos-pane" aria-selected="false">
                <i class="fa-solid fa-chart-column me-2"></i>GRÁFICOS
            </button>
        </li>
    </ul>

    <div class="tab-content" id="comprobantesViewContent">
    <div class="tab-pane fade show active" id="comprobantes-detalle-pane" role="tabpanel" aria-labelledby="comprobantes-detalle-tab">

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination mb-2 flex-shrink-0" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop" onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop" onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop" onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- Grilla (Desktop) -->
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm"
        style="max-height: 50vh; background: #000a1a;">
        <table class="table table-dark table-hover table-sm mb-0 small" id="tablaEmitidos">
            <thead class="sticky-top bg-black">
                <tr>
                    <th class="ps-3 py-2" width="130">FECHA/HORA</th>
                    <th width="50">TIPO</th>
                    <th width="110">NÚMERO</th>
                    <th>CLIENTE</th>
                    <th class="text-end" width="90">SUBTOTAL</th>
                    <th class="text-end" width="90">IVA</th>
                    <th class="text-end" width="110">TOTAL</th>
                    <th width="120">PAGO 1 / IMPORTE</th>
                    <th width="120">PAGO 2 / IMPORTE</th>
                    <th width="110">VENDEDOR</th>
                    <th width="90">USUARIO</th>
                    <th class="text-center" width="80">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyEmitidos">
                <tr>
                    <td colspan="12" class="text-center py-5 text-muted">Ajuste los filtros y presione "Consultar"</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- TARJETAS (Mobile) -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">Ajuste los filtros y presione "Consultar"</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination mt-2 mb-2 flex-shrink-0" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt" onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig" onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt" onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- Totales con fondo oscuro corregido -->
    <div class="card card-totales-dark mt-3 shadow-lg">
        <div class="card-body py-2 px-3">
            <div class="row align-items-center">
                <div class="col-12 col-md-9">
                    <div class="row text-center">
                        <div class="col-6 col-md-3 border-end border-secondary border-opacity-25 mb-2 mb-md-0">
                            <small class="text-white-50 d-block">SUBTOTAL TOTAL</small>
                            <span class="fw-bold text-white fs-5" id="totSub">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3 border-end border-secondary border-opacity-25 mb-2 mb-md-0">
                            <small class="text-white-50 d-block">IVA TOTAL</small>
                            <span class="fw-bold text-white fs-5" id="totIVA">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3 border-end border-secondary border-opacity-25">
                            <small class="text-white-50 d-block">IMP. INTERNOS</small>
                            <span class="fw-bold text-white fs-5" id="totII">$ 0,00</span>
                        </div>
                        <div class="col-6 col-md-3">
                            <small class="text-white-50 d-block">DESC/RECA</small>
                            <span class="fw-bold text-white fs-5" id="totDR">$ 0,00</span>
                        </div>
                    </div>
                </div>
                <div
                    class="col-12 col-md-3 border-start border-accent border-4 py-1 mt-3 mt-md-0 text-center text-md-start">
                    <div class="ps-md-3">
                        <small class="text-accent d-block fw-bold text-uppercase"
                            style="font-size: 10px; letter-spacing: 1px;">Total General Reporte</small>
                        <span class="h3 text-accent fw-bold mb-0" id="totGral">$ 0,00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="tab-pane fade" id="comprobantes-graficos-pane" role="tabpanel" aria-labelledby="comprobantes-graficos-tab">
        <div id="comprobantesGraficosSinDatos" class="text-center text-muted py-5 border border-secondary rounded">REALICE UNA CONSULTA PARA GENERAR LOS GRÁFICOS</div>
        <div class="row g-3" id="comprobantesGraficos" style="display:none;">
            <div class="col-12">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-chart-line"></i> Evolución diaria neta</h5>
                    <div class="comprobantes-chart-canvas comprobantes-chart-wide"><canvas id="chartCompDia"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-file-invoice"></i> Importe por tipo</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompTipos"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-users"></i> Principales clientes</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompClientes"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-user-tie"></i> Facturación por vendedor</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompVendedores"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-stamp"></i> Importe neto por situación fiscal</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompFiscal"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="comprobantes-chart-panel">
                    <h5><i class="fa-solid fa-diagram-project"></i> Importe por rama de cliente</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompRamas"></canvas></div>
                </section>
            </div>
        </div>
    </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    (function () {
        let debounceTimer;
        let listadoComprobantes = [];
        let pagActual = 1, pagTotal = 1, pagTam = 20;
        let comprobantesCharts = [];

        window.pagActual = 1; window.pagTotal = 1;

        async function init() {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            document.getElementById('comprobantes-graficos-tab').addEventListener('shown.bs.tab', renderGraficosComprobantes);

            try {
                const [rCli, rVend, rMPag, rUnid, rRamas] = await Promise.all([
                    fetch('api_helpers.php?action=get_clientes', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_vendedores', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_medios_pago', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_unidades_negocio', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_ramas_clientes', { headers: h }).then(r => r.json())
                ]);

                fillSelect('#cCliente', rCli, 'razon');
                fillSelect('#cVendedor', rVend, 'nombre');
                fillSelect('#cMPago', rMPag, 'medio');
                fillSelect('#cUnidad', rUnid, 'nombre');
                fillSelect('#cRama', rRamas, 'rama');

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

                // Listener para filtrado automático
                $('.filter-auto').on('change', function () {
                    consultarComprobantes();
                });

                // Inicialización Select2
                $('.select2-remote').select2({ dropdownParent: $('#moduloComprobantes'), width: '100%', placeholder: 'SELECCIONE...' });

                // Consultar inicialmente
                setFechasIniciales();
                consultarComprobantes();

            } catch (e) { console.error("Error inicializando filtros:", e); }
        }

        window.consultarComprobantes = function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(async () => {
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
                    rama_id: $('#cRama').is(':disabled') ? null : $('#cRama').val(),
                    sin_remitir: $('#chkSinRemitir').is(':checked')
                };

                const tbody = $('#tbodyEmitidos');
                tbody.html('<tr><td colspan="12" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Generando informe...</td></tr>');
                $('#tblCards').html('<div class="text-center text-muted p-5 opacity-50 italic"><i class="fas fa-spinner fa-spin me-2"></i>Generando informe...</div>');

                try {
                    const res = await fetch('api_comprobantes_emitidos.php?action=list', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify(payload)
                    }).then(r => r.json());

                    if (res.success) {
                        listadoComprobantes = res.data;
                        calcularTotalesGlobales(listadoComprobantes);
                        pagActual = 1; window.pagActual = 1;
                        renderPagina();
                        renderGraficosComprobantes();
                    }
                    else { Swal.fire('Error', res.error, 'error'); }
                } catch (e) {
                    tbody.html('<tr><td colspan="12" class="text-center text-danger">Error de red.</td></tr>');
                    $('#tblCards').html('<div class="text-center text-danger p-5">Error de red.</div>');
                }
            }, 300);
        }

        function calcularTotalesGlobales(data) {
            let tSub = 0, tIva = 0, tII = 0, tDR = 0, tGral = 0;
            data.forEach(row => {
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

            renderizarTabla(datos);
            renderTarjetas(datos);
            sincronizarPaginacion(total);
        }

        function renderizarTabla(data) {
            const tbody = $('#tbodyEmitidos'); tbody.empty();

            if (data.length === 0) {
                tbody.html('<tr><td colspan="12" class="text-center py-5 text-muted">Sin movimientos encontrados.</td></tr>');
                return;
            }

            data.forEach(row => {
                const sub = parseFloat(row.subtotal_calc) || 0;
                const iva = parseFloat(row.iva_calc) || 0;
                const total = parseFloat(row.total) || 0;
                const caePendiente = ![100, 101].includes(Number(row.tipocomp)) && !(row.cae || '').trim();

                tbody.append(`
                <tr class="align-middle">
                    <td class="ps-3">${formatDate(row.fecha)} ${row.hora}</td>
                    <td class="fw-bold">${row.tipo_str}${caePendiente ? '<br><span class="badge bg-danger">SIN CAE</span>' : ''}</td>
                    <td class="text-info">${row.ptovta.toString().padStart(4, '0')}-${row.numcomp.toString().padStart(8, '0')}</td>
                    <td class="text-truncate" style="max-width:180px">${row.cliente_nombre}</td>
                    <td class="text-end">$ ${formatMoney(sub)}</td>
                    <td class="text-end">$ ${formatMoney(iva)}</td>
                    <td class="text-end fw-bold text-accent">$ ${formatMoney(total)}</td>
                    <td class="small text-white-50">${row.mpago1 || '-'}<br>$ ${formatMoney(row.mpago1imp)}</td>
                    <td class="small text-white-50">${row.mpago2 || '-'}<br>$ ${formatMoney(row.mpago2imp)}</td>
                    <td class="small">${row.vendedor_nombre || 'S/V'}</td>
                    <td class="small text-white-50">${row.usuario || '-'}</td>
                    <td class="text-center">
                        <div class="btn-group">
                            <button class="btn btn-sm btn-link text-info p-1" onclick="verComprobante(${row.ticket_id})" title="Ver / Imprimir"><i class="fas fa-eye"></i></button>
                            ${caePendiente ? `<button class="btn btn-sm btn-link text-danger p-1" onclick="resolicitarCAE(${row.ticket_id}, ${row.tipocomp})" title="Re-solicitar CAE"><i class="fas fa-rotate"></i></button>` : ''}
                            <button class="btn btn-sm btn-link text-warning p-1" onclick="generarNC(${row.ticket_id}, '${row.tipo_str}')" title="Generar Crédito"><i class="fas fa-undo-alt"></i></button>
                        </div>
                    </td>
                </tr>
                `);
            });
        }

        function renderTarjetas(data) {
            const wrapCards = $('#tblCards'); wrapCards.empty();

            if (data.length === 0) {
                wrapCards.html('<div class="text-center py-5 text-muted">Sin movimientos encontrados.</div>');
                return;
            }

            data.forEach(row => {
                const sub = parseFloat(row.subtotal_calc) || 0;
                const iva = parseFloat(row.iva_calc) || 0;
                const total = parseFloat(row.total) || 0;
                const caePendiente = ![100, 101].includes(Number(row.tipocomp)) && !(row.cae || '').trim();

                wrapCards.append(`
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title text-truncate" style="max-width: 250px;">${row.cliente_nombre}</div>
                            <small class="text-muted fw-bold">${row.tipo_str} ${row.ptovta.toString().padStart(4, '0')}-${row.numcomp.toString().padStart(8, '0')}</small>
                            ${caePendiente ? '<span class="badge bg-danger ms-1">SIN CAE</span>' : ''}
                        </div>
                        <span class="erp-card-code">${formatDate(row.fecha)} ${row.hora}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Subtotal / IVA</span>
                        <span class="erp-card-value">$ ${formatMoney(sub)} / $ ${formatMoney(iva)}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Pago 1</span>
                        <span class="erp-card-value">${row.mpago1 || '-'} <span class="text-info">($ ${formatMoney(row.mpago1imp)})</span></span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Pago 2</span>
                        <span class="erp-card-value">${row.mpago2 || '-'} <span class="text-info">($ ${formatMoney(row.mpago2imp)})</span></span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Vend / Usr</span>
                        <span class="erp-card-value text-muted">${row.vendedor_nombre || 'S/V'} / ${row.usuario || '-'}</span>
                    </div>
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="erp-card-label text-success">Total Final</span>
                        <span class="erp-card-value price fs-6 text-success">$ ${formatMoney(total)}</span>
                    </div>
                    <div class="erp-card-actions flex-wrap gap-1 mt-2">
                        <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="verComprobante(${row.ticket_id})">
                            <i class="fas fa-eye me-1"></i> VER
                        </button>
                        ${caePendiente ? `<button class="btn btn-danger btn-sm flex-fill fw-bold" onclick="resolicitarCAE(${row.ticket_id}, ${row.tipocomp})"><i class="fas fa-rotate me-1"></i> RE-SOLICITAR CAE</button>` : ''}
                        <button class="btn btn-warning btn-sm flex-fill fw-bold" onclick="generarNC(${row.ticket_id}, '${row.tipo_str}')">
                            <i class="fas fa-undo-alt me-1"></i> CRÉDITO
                        </button>
                    </div>
                </div>
                `);
            });
        }

        function renderGraficosComprobantes() {
            if (!document.getElementById('comprobantes-graficos-pane').classList.contains('active')) return;

            comprobantesCharts.forEach(chart => chart.destroy());
            comprobantesCharts = [];

            const sinDatos = document.getElementById('comprobantesGraficosSinDatos');
            const contenedor = document.getElementById('comprobantesGraficos');
            if (!listadoComprobantes.length || typeof Chart === 'undefined') {
                sinDatos.style.display = 'block';
                sinDatos.textContent = listadoComprobantes.length ? 'NO SE PUDO CARGAR EL MOTOR DE GRÁFICOS' : 'NO HAY COMPROBANTES PARA GRAFICAR';
                contenedor.style.display = 'none';
                return;
            }

            sinDatos.style.display = 'none';
            contenedor.style.display = '';

            const css = getComputedStyle(document.documentElement);
            const textColor = css.getPropertyValue('--text-main').trim() || '#59636e';
            const gridColor = css.getPropertyValue('--border-color').trim() || 'rgba(127,127,127,.2)';
            const palette = ['#008c95', '#2e7d32', '#d98200', '#c62828', '#3867b7', '#8e5aa9', '#607d8b', '#b56b45', '#557c3e', '#a64268'];
            const money = value => '$ ' + Number(value || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const signedTotal = row => {
                const factor = [3, 8, 13, 101].includes(Number(row.tipocomp)) ? -1 : 1;
                return Number(row.total || 0) * factor;
            };
            const aggregate = (keyFn, valueFn) => {
                const map = new Map();
                listadoComprobantes.forEach(row => {
                    const key = keyFn(row) || 'SIN DEFINIR';
                    map.set(key, (map.get(key) || 0) + Number(valueFn(row) || 0));
                });
                return [...map.entries()];
            };
            const top = entries => entries.sort((a, b) => b[1] - a[1]).slice(0, 10);
            const axis = { ticks: { color: textColor }, grid: { color: gridColor }, border: { color: gridColor } };
            const moneyTooltip = { callbacks: { label: context => `${context.dataset.label || context.label}: ${money(context.raw)}` } };

            const porDia = aggregate(r => String(r.fecha || '').split(' ')[0], signedTotal).sort((a, b) => a[0].localeCompare(b[0]));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompDia'), {
                type: 'line',
                data: { labels: porDia.map(([fecha]) => formatDate(fecha)), datasets: [{ label: 'Facturación neta', data: porDia.map(([, total]) => total), borderColor: palette[0], backgroundColor: 'rgba(0,140,149,.16)', fill: true, tension: .25, pointRadius: 3 }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { labels: { color: textColor } }, tooltip: moneyTooltip } }
            }));

            const tipos = aggregate(r => r.tipo_str || 'OTRO', signedTotal).sort((a, b) => b[1] - a[1]);
            comprobantesCharts.push(new Chart(document.getElementById('chartCompTipos'), {
                type: 'bar',
                data: { labels: tipos.map(([tipo]) => tipo), datasets: [{ label: 'Importe neto', data: tipos.map(([, total]) => total), backgroundColor: palette }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip: moneyTooltip } }
            }));

            const clientes = top(aggregate(r => r.cliente_nombre || 'SIN CLIENTE', signedTotal));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompClientes'), {
                type: 'bar',
                data: { labels: clientes.map(([cliente]) => cliente), datasets: [{ label: 'Importe neto', data: clientes.map(([, total]) => total), backgroundColor: palette[1] }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip: moneyTooltip } }
            }));

            const vendedores = top(aggregate(r => r.vendedor_nombre || 'SIN VENDEDOR', signedTotal));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompVendedores'), {
                type: 'bar',
                data: { labels: vendedores.map(([vendedor]) => vendedor), datasets: [{ label: 'Importe neto', data: vendedores.map(([, total]) => total), backgroundColor: palette[4] }] },
                options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip: moneyTooltip } }
            }));

            const fiscalKey = row => {
                if ([100, 101].includes(Number(row.tipocomp))) return 'NO FISCAL';
                return String(row.cae || '').trim() ? 'FISCAL CON CAE' : 'FISCAL SIN CAE';
            };
            const fiscal = aggregate(fiscalKey, signedTotal);
            const fiscalCantidad = new Map(aggregate(fiscalKey, () => 1));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompFiscal'), {
                type: 'bar',
                data: { labels: fiscal.map(([estado]) => estado), datasets: [{ label: 'Importe neto', data: fiscal.map(([, importe]) => importe), backgroundColor: [palette[1], palette[3], palette[6]] }] },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { x: axis, y: axis },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: context => `${money(context.raw)} · ${fiscalCantidad.get(context.label) || 0} comprobantes` } }
                    }
                }
            }));

            const ramas = top(aggregate(r => r.rama_nombre || 'SIN RAMA', signedTotal));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompRamas'), {
                type: 'bar',
                data: { labels: ramas.map(([rama]) => rama), datasets: [{ label: 'Importe neto', data: ramas.map(([, importe]) => importe), backgroundColor: palette[5] }] },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip: moneyTooltip } }
            }));
        }

        function sincronizarPaginacion(total) {
            const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1), hasta = Math.min(pagActual * pagTam, total);
            const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> comprobantes`;

            ['', 'Top'].forEach(sfx => {
                var el = document.getElementById('pagInfo' + sfx); if (el) el.innerHTML = info;
                ['btnPagPrim', 'btnPagAnt'].forEach(id => { var e = document.getElementById(id + sfx); if (e) e.disabled = pagActual <= 1; });
                ['btnPagSig', 'btnPagUlt'].forEach(id => { var e = document.getElementById(id + sfx); if (e) e.disabled = pagActual >= pagTotal; });
                var cont = document.getElementById('pagBotones' + sfx);
                if (cont) {
                    cont.innerHTML = '';
                    if (pagTotal > 1) {
                        var ini = Math.max(1, pagActual - 2), fin = Math.min(pagTotal, ini + 4);
                        if (fin - ini < 4) ini = Math.max(1, fin - 4);
                        for (var i = ini; i <= fin; i++) {
                            var b = document.createElement('button');
                            b.className = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                            b.textContent = i;
                            b.onclick = (function (p) { return function () { pagGo(p); }; })(i);
                            cont.appendChild(b);
                        }
                    }
                }
                var ji = document.getElementById('pagJumpInput' + sfx); if (ji) { ji.value = pagActual; ji.max = pagTotal; }
                var sel = document.getElementById('pagTamano' + sfx); if (sel) sel.value = pagTam;
                var wrap = document.getElementById(sfx === 'Top' ? 'pagTopWrap' : 'pagBotWrap');
                if (wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
            });
        }

        window.pagGo = function (n) { n = Math.max(1, Math.min(pagTotal, n)); if (n === pagActual) return; pagActual = n; window.pagActual = n; renderPagina(); document.getElementById('moduloComprobantes')?.scrollTo({ top: 0, behavior: 'smooth' }); };
        window.pagJump = function () { var v = parseInt(document.getElementById('pagJumpInput').value); if (!isNaN(v)) pagGo(v); };
        window.pagJumpTop = function () { var v = parseInt(document.getElementById('pagJumpInputTop').value); if (!isNaN(v)) pagGo(v); };
        window.pagCambiarTamano = function () { pagTam = parseInt(document.getElementById('pagTamano').value); pagActual = 1; window.pagActual = 1; renderPagina(); };
        window.pagCambiarTamanoTop = function () { pagTam = parseInt(document.getElementById('pagTamanoTop').value); pagActual = 1; window.pagActual = 1; renderPagina(); };

        function actualizarTotales(s, i, ii, dr, g) {
            $('#totSub').text('$ ' + formatMoney(s)); $('#totIVA').text('$ ' + formatMoney(i));
            $('#totII').text('$ ' + formatMoney(ii)); $('#totDR').text('$ ' + formatMoney(dr));
            $('#totGral').text('$ ' + formatMoney(g));
        }

        window.verComprobante = function (id) {
            window.open('imprimir_ticket.php?id=' + id, '_blank');
        }

        window.resolicitarCAE = async function (id, tipoComp) {
            const requiereAsociado = [2, 3, 7, 8, 12, 13].includes(Number(tipoComp));
            const confirmar = await Swal.fire(requiereAsociado ? {
                title: 'Asociar y re-solicitar CAE',
                html: `<p class="small text-muted">Indique el comprobante original asociado a esta nota legacy.</p>
                       <div class="row g-2 text-start">
                           <div class="col-4"><label class="form-label small">Punto de venta</label><input id="retry_asoc_pv" type="number" min="1" class="form-control" placeholder="0001"></div>
                           <div class="col-8"><label class="form-label small">Número</label><input id="retry_asoc_num" type="number" min="1" class="form-control" placeholder="00000001"></div>
                       </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'ASOCIAR Y RE-SOLICITAR',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#dc3545',
                focusConfirm: false,
                preConfirm: () => {
                    const pv = parseInt(document.getElementById('retry_asoc_pv').value);
                    const num = parseInt(document.getElementById('retry_asoc_num').value);
                    if (!pv || !num) {
                        Swal.showValidationMessage('Complete punto de venta y número del comprobante original.');
                        return false;
                    }
                    return {pv, num};
                }
            } : {
                title: 'Re-solicitar CAE',
                text: 'Se consultará primero si ARCA ya autorizó este comprobante y, si sigue pendiente, se volverá a solicitar.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'SÍ, RE-SOLICITAR',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#dc3545'
            });
            if (!confirmar.isConfirmed) return;

            Swal.fire({ title: 'Consultando ARCA...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const res = await fetch('api_comprobantes_emitidos.php?action=retry_cae', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ticket_id: id, associatedDoc: confirmar.value || null})
                }).then(r => r.json());
                if (!res.success) throw new Error(res.error || 'No se pudo obtener el CAE.');
                await Swal.fire('CAE autorizado', `CAE N° ${res.cae}`, 'success');
                consultarComprobantes();
            } catch (e) {
                Swal.fire('CAE no autorizado', e.message || 'Error al comunicarse con ARCA.', 'error');
            }
        }

        window.generarNC = function (id, tipo) {
            // Validar que el tipo sea facturable como NC
            const tiposPermitidos = ['FACTURA A', 'N. CRÉDITO A', 'FACTURA B', 'N. CRÉDITO B',
                'FACTURA C', 'N. CRÉDITO C', 'TICKET', 'TICKET DE CREDITO'];
            const tipoUp = (tipo || '').toUpperCase();
            // Evitar generar NC sobre una NC ya existente
            if (tipoUp.includes('CRÉDITO') || tipoUp.includes('CREDITO')) {
                Swal.fire('Atención', 'No se puede generar una Nota de Crédito sobre otro comprobante de crédito.', 'warning');
                return;
            }
            Swal.fire({
                title: 'Generar Nota de Crédito',
                html: `¿Desea crear una <strong>Nota de Crédito</strong> asociada al comprobante <strong>${tipo}</strong>?<br>
                       <small class="text-muted">Se pre-cargarán el cliente y todos los ítems del comprobante original.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-undo-alt me-1"></i> SÍ, GENERAR NC',
                cancelButtonText: 'CANCELAR',
                confirmButtonColor: '#0ea5e9'
            }).then(r => {
                if (r.isConfirmed) {
                    loadModule(`newfactura.php?notacredito=${id}`);
                }
            });
        }

        // --- LÓGICA DE EXPORTACIÓN EXCEL NATIVA ---
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
            const toInput = d => d.toISOString().split('T')[0];
            $('#fDesde').val(toInput(hoy));
            $('#fHasta').val(toInput(hoy));
        }

        function formatMoney(n) { return (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); }
        function formatDate(d) { if (!d) return '-'; const p = d.split(' ')[0].split('-'); return `${p[2]}/${p[1]}/${p[0]}`; }

        init();
    })();
</script>
