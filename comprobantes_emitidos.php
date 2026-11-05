<?php
/**
 * comprobantes_emitidos.php - REPORTE DE COMPROBANTES EMITIDOS
 * Migración de Informes - Comprobantes.vb (Ajustado a tablas reales)
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
require_once 'permisos_helpers.php';
$puedeModMediosCobro = puedeModificarMediosCobro($pdo);

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
                            <label class="form-label-custom mb-1">CLIENTE</label>
                            <select id="cCliente" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom mb-1">VENDEDOR</label>
                            <select id="cVendedor" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom mb-1">USUARIO</label>
                            <select id="cUsuario" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom mb-1">TIPO COMPROBANTE</label>
                            <select id="cTipo" class="form-select form-select-sm select2-filter filter-auto">
                                <option value="">TODOS</option>
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
                            <label class="form-label-custom mb-1">MEDIO DE PAGO</label>
                            <select id="cMPago" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom mb-1">UNIDAD NEGOCIO</label>
                            <select id="cUnidad" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom mb-1">PROVINCIA</label>
                            <select id="cProv" class="form-select form-select-sm select2-filter filter-auto">
                                <option value="">TODOS</option>
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
                            <label class="form-label-custom mb-1">RAMA CLIENTE</label>
                            <select id="cRama" class="form-select form-select-sm select2-filter filter-auto"></select>
                        </div>
                        <div class="col-md-4 d-none" id="grupoFiltroDeposito">
                            <label class="form-label-custom mb-1">DEPÓSITO</label>
                            <select id="cDeposito" class="form-select form-select-sm select2-filter filter-auto">
                                <option value="">TODOS</option>
                            </select>
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
                    <h5><i class="fa-solid fa-user-gear"></i> Facturación por usuario</h5>
                    <div class="comprobantes-chart-canvas"><canvas id="chartCompUsuarios"></canvas></div>
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

<!-- MODIFICAR MEDIOS DE COBRO (api_modificar_medios_cobro.php) -->
<div class="modal fade" id="modMediosCobro" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-accent fw-bold"><i class="fas fa-money-bill-transfer me-2"></i>MODIFICAR MEDIOS DE COBRO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <div class="fw-bold text-white" id="mmcNumero">-</div>
                        <div class="small text-white-50" id="mmcCliente">-</div>
                    </div>
                    <div class="text-end">
                        <div class="small text-white-50">A REPARTIR</div>
                        <div class="h5 fw-bold text-accent mb-0" id="mmcARepartir">$ 0,00</div>
                    </div>
                </div>

                <div class="small text-white-50 mb-1">COBRADO ACTUALMENTE</div>
                <div class="mb-3" id="mmcActuales"></div>

                <div class="row g-2 align-items-end mb-2">
                    <div class="col-8">
                        <label class="form-label-custom">MEDIO DE COBRO 1</label>
                        <select id="mmcMp1" class="form-select"></select>
                        <div class="d-none mt-1" id="mmcTj1">
                            <div class="row g-1">
                                <div class="col-7"><select id="mmcTj1Id" class="form-select form-select-sm" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="mmcTj1Cuotas" class="form-control form-control-sm text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-3"><input type="text" id="mmcTj1Cupon" class="form-control form-control-sm" maxlength="30" placeholder="Cupón"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label-custom">IMPORTE</label>
                        <input type="number" id="mmcImp1" class="form-control text-end fw-bold" step="0.01" min="0">
                    </div>
                </div>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-8">
                        <label class="form-label-custom">MEDIO DE COBRO 2 (OPCIONAL)</label>
                        <select id="mmcMp2" class="form-select"></select>
                        <div class="d-none mt-1" id="mmcTj2">
                            <div class="row g-1">
                                <div class="col-7"><select id="mmcTj2Id" class="form-select form-select-sm" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="mmcTj2Cuotas" class="form-control form-control-sm text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-3"><input type="text" id="mmcTj2Cupon" class="form-control form-control-sm" maxlength="30" placeholder="Cupón"></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <label class="form-label-custom">IMPORTE</label>
                        <input type="number" id="mmcImp2" class="form-control text-end fw-bold" step="0.01" min="0">
                    </div>
                </div>
                <div class="small mb-3" id="mmcDiferencia"></div>

                <label class="form-label-custom">MOTIVO (OBLIGATORIO)</label>
                <input type="text" id="mmcMotivo" class="form-control mb-3" maxlength="255" placeholder="Ej.: se cobró con MercadoPago y se cargó como efectivo">

                <div class="alert alert-warning small mb-0 py-2">
                    <i class="fas fa-triangle-exclamation me-1"></i>
                    No se modifica lo ya grabado: se registran movimientos de ajuste <strong>con fecha de hoy</strong>
                    en tesorería, bancos, cuenta corriente y contabilidad. El efectivo se ajusta en <strong>su caja abierta</strong>.
                    <div id="mmcAvisos"></div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-info rounded-pill px-4 fw-bold" id="mmcBtnGuardar" onclick="guardarMediosCobro()"><i class="fas fa-check me-2"></i>GUARDAR</button>
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
        const PUEDE_MOD_MEDIOS = <?= $puedeModMediosCobro ? 'true' : 'false' ?>;
        let mmcDatos = null;
        const ordenTabla = erpOrdenarTabla('#tablaEmitidos', () => listadoComprobantes, renderPagina, [
            {campo:r => `${r.fecha || ''} ${r.hora || ''}`, tipo:'fecha'}, 'tipo_str',
            {campo:r => `${String(r.ptovta || '').padStart(4, '0')}-${String(r.numcomp || '').padStart(8, '0')}`},
            'cliente_nombre', {campo:'subtotal_calc', tipo:'numero'}, {campo:'iva_calc', tipo:'numero'},
            {campo:'total', tipo:'numero'}, 'mpago1', 'mpago2', 'vendedor_nombre', 'usuario', null
        ]);

        window.pagActual = 1; window.pagTotal = 1;

        async function init() {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            document.getElementById('comprobantes-graficos-tab').addEventListener('shown.bs.tab', renderGraficosComprobantes);

            try {
                const [rCli, rVend, rUsu, rMPag, rUnid, rRamas, rDepositos] = await Promise.all([
                    fetch('api_helpers.php?action=get_clientes', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_vendedores', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_usuarios', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_medios_pago', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_unidades_negocio', { headers: h }).then(r => r.json()),
                    fetch('api_helpers.php?action=get_ramas_clientes', { headers: h }).then(r => r.json()),
                    fetch('api_comprobantes_emitidos.php?action=get_depositos', { headers: h }).then(r => r.json())
                ]);

                fillSelect('#cCliente', rCli, 'razon');
                fillSelect('#cVendedor', rVend, 'nombre');
                // El usuario se filtra por nombre (columna de texto en tickets), no por id:
                // el value de cada opción es el nombre de usuario, no su id numérico.
                fillSelectPorNombre('#cUsuario', rUsu, 'usuario');
                fillSelect('#cMPago', rMPag, 'medio');
                fillSelect('#cUnidad', rUnid, 'nombre');
                fillSelect('#cRama', rRamas, 'rama');
                if (rDepositos.success && rDepositos.habilitado) {
                    const dep = $('#cDeposito').empty().append(new Option('TODOS', ''));
                    rDepositos.data.forEach(d => dep.append(new Option(d.nombre, d.id)));
                    $('#grupoFiltroDeposito').removeClass('d-none');
                }

                // Listener para filtrado automático
                $('.filter-auto').on('change', function () {
                    consultarComprobantes();
                });

                // Inicialización Select2
                $('.select2-filter').select2({ dropdownParent: $('#moduloComprobantes'), width: '100%' });

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
                    cliente_id: $('#cCliente').val() || null,
                    vendedor_id: $('#cVendedor').val() || null,
                    usuario_txt: $('#cUsuario').val() || null,
                    tipo_comp: $('#cTipo').val() || null,
                    mpago_txt: $('#cMPago').val() ? $('#cMPago option:selected').text() : null,
                    unidad_id: $('#cUnidad').val() || null,
                    provincia: $('#cProv').val() || null,
                    rama_id: $('#cRama').val() || null,
                    deposito_id: $('#cDeposito').val() || null,
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
                        ordenTabla.aplicar();
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
                            <button class="btn btn-sm btn-link text-accent p-1" onclick="imprimirRemito(${row.ticket_id})" title="Imprimir Remito"><i class="fas fa-truck-ramp-box"></i></button>
                            ${caePendiente ? `<button class="btn btn-sm btn-link text-danger p-1" onclick="resolicitarCAE(${row.ticket_id}, ${row.tipocomp})" title="Re-solicitar CAE"><i class="fas fa-rotate"></i></button>` : ''}
                            <button class="btn btn-sm btn-link text-warning p-1" onclick="generarNC(${row.ticket_id}, '${row.tipo_str}')" title="Generar Crédito"><i class="fas fa-undo-alt"></i></button>
                            ${PUEDE_MOD_MEDIOS ? `<button class="btn btn-sm btn-link text-success p-1" onclick="abrirModificarMedios(${row.ticket_id})" title="Modificar medios de cobro"><i class="fas fa-money-bill-transfer"></i></button>` : ''}
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
                        <button class="btn btn-outline-info btn-sm flex-fill fw-bold" onclick="imprimirRemito(${row.ticket_id})">
                            <i class="fas fa-truck-ramp-box me-1"></i> REMITO
                        </button>
                        ${caePendiente ? `<button class="btn btn-danger btn-sm flex-fill fw-bold" onclick="resolicitarCAE(${row.ticket_id}, ${row.tipocomp})"><i class="fas fa-rotate me-1"></i> RE-SOLICITAR CAE</button>` : ''}
                        <button class="btn btn-warning btn-sm flex-fill fw-bold" onclick="generarNC(${row.ticket_id}, '${row.tipo_str}')">
                            <i class="fas fa-undo-alt me-1"></i> CRÉDITO
                        </button>
                        ${PUEDE_MOD_MEDIOS ? `<button class="btn btn-outline-success btn-sm flex-fill fw-bold" onclick="abrirModificarMedios(${row.ticket_id})">
                            <i class="fas fa-money-bill-transfer me-1"></i> MEDIOS DE COBRO
                        </button>` : ''}
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

            const usuarios = top(aggregate(r => r.usuario || 'SIN USUARIO', signedTotal));
            comprobantesCharts.push(new Chart(document.getElementById('chartCompUsuarios'), {
                type: 'bar',
                data: { labels: usuarios.map(([usuario]) => usuario), datasets: [{ label: 'Importe neto', data: usuarios.map(([, total]) => total), backgroundColor: palette[2] }] },
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

        window.imprimirRemito = function (id) {
            // imprimir_remito.php busca el remito ya generado para este comprobante,
            // o lo genera si es la primera vez (numeración propia, arranca en 6200).
            window.open('imprimir_remito.php?id=' + id, '_blank');
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
                if (res.renumeracion_fallida) {
                    await Swal.fire({
                        icon: 'warning',
                        title: 'CAE autorizado, pero requiere revisión',
                        html: `CAE N° ${res.cae}<br><br>${res.aviso_numeracion || 'ARCA aprobó un número de comprobante distinto al local y no se pudo corregir automáticamente.'}`
                    });
                } else {
                    await Swal.fire('CAE autorizado', `CAE N° ${res.cae}`, 'success');
                }
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
            const el = $(sel); el.empty().append(new Option('TODOS', '', true, true));
            if (res && res.success && res.data) {
                res.data.forEach(d => {
                    let label = d[field] || d.text || d.nombre || d.razon || 'S/D';
                    el.append(new Option(label.toString().toUpperCase(), d.id));
                });
            }
        }
        // Igual que fillSelect, pero el value de cada opción es el propio nombre
        // (no el id) — para filtros que comparan contra una columna de texto,
        // como "usuario" en tickets.
        function fillSelectPorNombre(sel, res, field) {
            const el = $(sel); el.empty().append(new Option('TODOS', '', true, true));
            if (res && res.success && res.data) {
                res.data.forEach(d => {
                    const nombre = (d[field] || '').toString();
                    if (nombre) el.append(new Option(nombre.toUpperCase(), nombre));
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

        // --- MODIFICAR MEDIOS DE COBRO (api_modificar_medios_cobro.php) ---
        const mmcHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
        const mmcNum = v => Math.round((parseFloat(v) || 0) * 100) / 100;
        const mmcEsc = t => $('<div>').text(t ?? '').html();

        window.abrirModificarMedios = async function (id) {
            const res = await fetch(`api_modificar_medios_cobro.php?action=get&id=${id}`, { headers: mmcHeaders }).then(r => r.json()).catch(() => null);
            if (!res || !res.success) {
                Swal.fire('No se puede modificar', res?.error || 'Error de comunicación.', 'warning');
                return;
            }
            mmcDatos = res;
            const t = res.ticket;
            $('#mmcNumero').text(`${t.numero} · ${formatDate(t.fecha)}${t.es_nota ? ' · NOTA DE CRÉDITO' : ''}`);
            $('#mmcCliente').text(t.cliente);
            $('#mmcARepartir').text('$ ' + formatMoney(res.a_repartir));
            $('#mmcActuales').html(res.actuales.map(a =>
                `<span class="badge bg-secondary me-1 mb-1 fw-normal">${mmcEsc(a.nombre)}: $ ${formatMoney(a.imp)}</span>`).join(''));

            const opciones = res.medios.map(m => `<option value="${m.id}">${mmcEsc(m.medio)}</option>`).join('');
            $('#mmcMp1').html('<option value="">SELECCIONE...</option>' + opciones);
            $('#mmcMp2').html('<option value="">(NINGUNO)</option>' + opciones);
            const a1 = res.actuales[0], a2 = res.actuales[1];
            $('#mmcMp1').val(a1 ? String(a1.medio_id) : ''); $('#mmcImp1').val(a1 ? a1.imp.toFixed(2) : '');
            $('#mmcMp2').val(a2 ? String(a2.medio_id) : ''); $('#mmcImp2').val(a2 ? a2.imp.toFixed(2) : '');
            $('#mmcMotivo').val('');
            const optTj = '<option value="">TARJETA...</option>' + (res.tarjetas || []).map(t => `<option value="${t.id}">${mmcEsc(t.descripcion)}</option>`).join('');
            $('#mmcTj1Id, #mmcTj2Id').html(optTj);
            $('#mmcTj1Cuotas, #mmcTj2Cuotas').val(1); $('#mmcTj1Cupon, #mmcTj2Cupon').val('');
            mmcActualizarTarjetas();

            let avisos = '';
            if (res.imputado > 0) avisos += `<div class="mt-1">Tiene <strong>$ ${formatMoney(res.imputado)}</strong> imputados ${t.es_nota ? 'a facturas' : 'por cobros'}: la cuenta corriente no puede quedar por debajo de ese importe.</div>`;
            if (!res.caja_abierta) avisos += '<div class="mt-1">No tiene caja abierta: no puede usar efectivo.</div>';
            if (!t.cliente_cc) avisos += '<div class="mt-1">El cliente no tiene cuenta corriente habilitada.</div>';
            $('#mmcAvisos').html(avisos);

            mmcActualizarDiferencia();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modMediosCobro')).show();
        };

        // Si se elige un medio de tipo Tarjetas (y hay tarjetas cargadas) se pide cuál, para el cupón.
        function mmcActualizarTarjetas() {
            const hay = mmcDatos && (mmcDatos.tarjetas || []).length > 0;
            [1, 2].forEach(n => {
                const m = hay ? mmcDatos.medios.find(x => String(x.id) === $(`#mmcMp${n}`).val()) : null;
                $(`#mmcTj${n}`).toggleClass('d-none', !(m && m.tipo === 'Tarjetas'));
            });
        }

        function mmcTarjeta(n) {
            if ($(`#mmcTj${n}`).hasClass('d-none')) return null;
            return { id: parseInt($(`#mmcTj${n}Id`).val()) || 0, cuotas: parseInt($(`#mmcTj${n}Cuotas`).val()) || 1, cupon: $(`#mmcTj${n}Cupon`).val().trim() };
        }

        function mmcActualizarDiferencia() {
            if (!mmcDatos) return;
            const suma = mmcNum($('#mmcImp1').val()) + ($('#mmcMp2').val() ? mmcNum($('#mmcImp2').val()) : 0);
            const dif = Math.round((mmcDatos.a_repartir - suma) * 100) / 100;
            $('#mmcDiferencia').html(Math.abs(dif) < 0.01
                ? '<span class="text-success"><i class="fas fa-check me-1"></i>Los importes cubren el total.</span>'
                : `<span class="text-danger">${dif > 0 ? 'Faltan' : 'Sobran'} $ ${formatMoney(Math.abs(dif))}</span>`);
        }

        // Al cambiar un importe, el otro medio toma el resto (si hay segundo medio).
        $(document).off('.mmc')
            .on('input.mmc', '#mmcImp1', function () {
                if ($('#mmcMp2').val()) $('#mmcImp2').val(Math.max(0, mmcDatos.a_repartir - mmcNum(this.value)).toFixed(2));
                mmcActualizarDiferencia();
            })
            .on('input.mmc', '#mmcImp2', function () {
                $('#mmcImp1').val(Math.max(0, mmcDatos.a_repartir - mmcNum(this.value)).toFixed(2));
                mmcActualizarDiferencia();
            })
            .on('change.mmc', '#mmcMp2', function () {
                if (!this.value) { $('#mmcImp2').val(''); $('#mmcImp1').val(mmcDatos.a_repartir.toFixed(2)); }
                mmcActualizarTarjetas();
                mmcActualizarDiferencia();
            })
            .on('change.mmc', '#mmcMp1', mmcActualizarTarjetas);

        window.guardarMediosCobro = async function () {
            const mp2 = $('#mmcMp2').val();
            const payload = {
                ticket_id: mmcDatos.ticket.id,
                mp1: parseInt($('#mmcMp1').val()) || 0, imp1: mmcNum($('#mmcImp1').val()),
                mp2: parseInt(mp2) || 0, imp2: mp2 ? mmcNum($('#mmcImp2').val()) : 0,
                motivo: $('#mmcMotivo').val().trim(),
                tarjeta1: mmcTarjeta(1), tarjeta2: mp2 ? mmcTarjeta(2) : null
            };
            if (!payload.mp1) return Swal.fire('Atención', 'Elija el medio de cobro 1.', 'warning');
            if (payload.motivo.length < 5) return Swal.fire('Atención', 'Indique el motivo de la modificación.', 'warning');

            const ok = await Swal.fire({
                title: '¿Confirmar el cambio?',
                html: 'Se registrarán movimientos de ajuste con fecha de hoy en caja, tesorería, bancos, cuenta corriente y contabilidad.',
                icon: 'question', showCancelButton: true, confirmButtonText: 'SÍ, MODIFICAR', cancelButtonText: 'CANCELAR'
            }).then(r => r.isConfirmed);
            if (!ok) return;

            $('#mmcBtnGuardar').prop('disabled', true);
            try {
                const res = await fetch('api_modificar_medios_cobro.php?action=save', {
                    method: 'POST', headers: { ...mmcHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                }).then(r => r.json());
                if (!res.success) return Swal.fire('Error', res.error, 'error');
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modMediosCobro')).hide();
                await Swal.fire('Listo', res.message, 'success');
                consultarComprobantes();
            } catch (e) {
                Swal.fire('Error', 'Error de comunicación.', 'error');
            } finally {
                $('#mmcBtnGuardar').prop('disabled', false);
            }
        };

        init();
    })();
</script>
