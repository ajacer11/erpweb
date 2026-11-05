<?php
// SEGURIDAD CENTRALIZADA
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloReporteVentas">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-chart-line text-accent me-2"></i>INFORME DE VENTAS</h4>
            <small class="text-accent opacity-75">DETALLE DE VENTAS POR PRODUCTO Y RENTABILIDAD</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros p-3 mb-3 shadow-sm border-0">
        <form id="formFiltros">
            <div class="row g-2 align-items-end">
                <!-- Fila 1 -->
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="txtDesde" class="form-control form-control-sm trigger-filter" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="txtHasta" class="form-control form-control-sm trigger-filter" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">CLIENTE</label>
                    <select id="cmbCliente" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">VENDEDOR</label>
                    <select id="cmbVendedor" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">UNIDAD NEGOCIO</label>
                    <select id="cmbUnidad" class="form-select select2-filter trigger-filter"></select>
                </div>

                <!-- Fila 2 -->
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">PRODUCTO</label>
                    <select id="cmbProducto" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">RUBRO</label>
                    <select id="cmbRubro" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">MARCA</label>
                    <select id="cmbMarca" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FAMILIA</label>
                    <select id="cmbFamilia" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">PROVINCIA</label>
                    <select id="cmbProvincia" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">RAMA CLIENTE</label>
                    <select id="cmbRamaCliente" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-3 d-none" id="grupoFiltroDepositoVentas">
                    <label class="form-label-custom">DEPÓSITO</label>
                    <select id="cmbDeposito" class="form-select select2-filter trigger-filter"></select>
                </div>
            </div>
        </form>
    </div>

    <ul class="nav nav-tabs mb-3" id="ventasViewTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="ventas-detalle-tab" data-bs-toggle="tab" data-bs-target="#ventas-detalle-pane" type="button" role="tab" aria-controls="ventas-detalle-pane" aria-selected="true">
                <i class="fa-solid fa-table-list me-2"></i>DETALLE
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="ventas-graficos-tab" data-bs-toggle="tab" data-bs-target="#ventas-graficos-pane" type="button" role="tab" aria-controls="ventas-graficos-pane" aria-selected="false">
                <i class="fa-solid fa-chart-column me-2"></i>GRÁFICOS
            </button>
        </li>
    </ul>

    <div class="tab-content" id="ventasViewContent">
    <div class="tab-pane fade show active" id="ventas-detalle-pane" role="tabpanel" aria-labelledby="ventas-detalle-tab">

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
    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded mb-2" style="max-height: 60vh; background-color: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tablaVentas" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3">FECHA</th>
                    <th class="text-white">TIPO</th>
                    <th class="text-white">NUMERO</th>
                    <th class="text-white">CODIGO</th>
                    <th class="text-white">DESCRIPCIÓN</th>
                    <th class="text-end text-white">CANT.</th>
                    <th class="text-end text-accent">TOTAL</th>
                    <th class="text-end text-white-50">IMP.INT</th>
                    <th class="text-end text-white-50">IVA</th>
                    <th class="text-end text-success">NETO</th>
                    <th class="text-white">RUBRO</th>
                    <th class="text-end text-white-50">IIBB</th>
                    <th class="text-end text-white-50">COSTO</th>
                    <th class="text-white">USUARIO</th>
                    <th class="text-white">VENDEDOR</th>
                    <th class="text-white">PROVEEDOR</th>
                    <th class="text-white">CLIENTE</th>
                    <th class="text-end text-white-50">IVA COMP</th>
                    <th class="text-white">MARCA</th>
                    <th class="text-end text-info">% DESC</th>
                </tr>
            </thead>
            <tbody id="tbodyVentas">
                <tr><td colspan="20" class="text-center py-5 text-muted">DEFINA LOS FILTROS</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">DEFINA LOS FILTROS</div>
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

    <!-- Panel de Totales -->
    <div class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4" style="background-color: #000a1a;">
        <div class="card-body p-3">
            <div class="row g-2">
                <!-- Columna 1: Total General -->
                <div class="col-6 col-md-2">
                    <label class="form-label-custom text-accent fw-bold" style="font-size: 0.7rem;">TOTAL GENERAL</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-accent">$</span>
                        <input type="text" id="txtTotalGeneral" class="form-control bg-dark border-secondary text-accent fw-bold text-end" readonly value="0.00">
                    </div>
                </div>

                <!-- Columna 2: Costo y Utilidad -->
                <div class="col-6 col-md-2">
                    <label class="form-label-custom text-success" style="font-size: 0.7rem;">UTILIDAD</label>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-success">$</span>
                        <input type="text" id="txtUtilidad" class="form-control bg-dark border-secondary text-success fw-bold text-end" readonly value="0.00">
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom text-white-50" style="font-size: 0.7rem;">COSTO TOTAL</label>
                    <div class="input-group input-group-sm mb-1">
                        <span class="input-group-text bg-dark border-secondary text-white-50">$</span>
                        <input type="text" id="txtCostoTotal" class="form-control bg-dark border-secondary text-white text-end" readonly value="0.00">
                    </div>
                </div>

                <!-- Columna 3: Impuestos -->
                <div class="col-12 col-md-6 border-md-start border-secondary ps-md-3 mt-2 mt-md-0">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <label class="form-label-custom text-muted" style="font-size: 0.65rem;">IVA VENTA</label>
                            <input type="text" id="txtIvaVenta" class="form-control form-control-sm bg-dark border-secondary text-white-50 text-end" readonly value="0.00">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label-custom text-muted" style="font-size: 0.65rem;">IMP. INT.</label>
                            <input type="text" id="txtImpInt" class="form-control form-control-sm bg-dark border-secondary text-white-50 text-end" readonly value="0.00">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label-custom text-muted" style="font-size: 0.65rem;">II.BB.</label>
                            <input type="text" id="txtIIBB" class="form-control form-control-sm bg-dark border-secondary text-white-50 text-end" readonly value="0.00">
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label-custom text-muted" style="font-size: 0.65rem;">IVA COMPRA</label>
                            <input type="text" id="txtIvaCompra" class="form-control form-control-sm bg-dark border-secondary text-white-50 text-end" readonly value="0.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>

    <div class="tab-pane fade" id="ventas-graficos-pane" role="tabpanel" aria-labelledby="ventas-graficos-tab">
        <div id="graficosSinDatos" class="text-center text-muted py-5 border border-secondary rounded">
            REALICE UNA CONSULTA PARA GENERAR LOS GRÁFICOS
        </div>
        <div class="row g-3" id="graficosVentas" style="display:none;">
            <div class="col-12">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-chart-line"></i> Evolución diaria</h5>
                    <div class="ventas-chart-canvas ventas-chart-wide"><canvas id="chartVentasDia"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-ranking-star"></i> Productos con mayor venta</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartProductos"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-user-tie"></i> Ventas por vendedor</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartVendedores"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-user-gear"></i> Ventas por usuario</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartUsuarios"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-layer-group"></i> Distribución por rubro</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartRubros"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-scale-balanced"></i> Total, costo y utilidad</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartRentabilidad"></canvas></div>
                </section>
            </div>
            <div class="col-12 col-xl-6">
                <section class="ventas-chart-panel">
                    <h5><i class="fa-solid fa-diagram-project"></i> Ventas por rama de cliente</h5>
                    <div class="ventas-chart-canvas"><canvas id="chartRamasClientes"></canvas></div>
                </section>
            </div>
        </div>
    </div>
    </div>
</div>

<style>
    .ventas-chart-panel {
        height: 100%;
        padding: 14px;
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 6px;
    }
    .ventas-chart-panel h5 {
        margin: 0 0 12px;
        color: var(--text-titles);
        font-size: 14px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .ventas-chart-panel h5 i { color: var(--accent); margin-right: 6px; }
    .ventas-chart-canvas { position: relative; height: 320px; min-width: 0; }
    .ventas-chart-wide { height: 360px; }
    @media (max-width: 768px) {
        .ventas-chart-canvas, .ventas-chart-wide { height: 280px; }
    }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    let timeoutBusqueda = null;
    let listadoVentas = [];
    const ordenTabla = erpOrdenarTabla('#tablaVentas', () => listadoVentas, renderPagina, [
        {campo:'Fecha', tipo:'fecha'}, 'Tipo', 'Numero', 'Codigo', 'Descripcion',
        {campo:'Cantidad', tipo:'numero'}, {campo:'Total', tipo:'numero'}, {campo:'Imp_Int', tipo:'numero'},
        {campo:'IVA', tipo:'numero'}, {campo:'Neto', tipo:'numero'}, 'Rubro',
        {campo:'IIBB', tipo:'numero'}, {campo:'Costo', tipo:'numero'}, 'Usuario', 'Vendedor',
        'Proveedor', 'Cliente', {campo:'Iva_compras', tipo:'numero'}, 'Marca',
        {campo:'Porc_Descuento', tipo:'numero'}
    ]);
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    let ventasCharts = [];
    
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        // Configurar listeners automáticos con Debounce de 800ms
        $('.trigger-filter').on('input change', function() {
            clearTimeout(timeoutBusqueda);
            
            // Validar fechas antes de disparar la búsqueda
            const desde = $('#txtDesde').val();
            const hasta = $('#txtHasta').val();
            
            if (desde) {
                const yearDesde = parseInt(desde.split('-')[0]);
                if (yearDesde < 2000) return; // Evita buscar si el año está incompleto
            }
            if (hasta) {
                const yearHasta = parseInt(hasta.split('-')[0]);
                if (yearHasta < 2000) return;
            }

            timeoutBusqueda = setTimeout(() => { cargarInforme(); }, 800);
        });

        document.getElementById('ventas-graficos-tab').addEventListener('shown.bs.tab', renderGraficos);

        try {
            const resp = await fetch('api_reporte_ventas.php?action=get_filters', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (!resp.ok || data.status === 'error') {
                throw new Error(data.error || `Error HTTP ${resp.status}`);
            }

            const fill = (id, items, valKey, textKey) => {
                const el = $('#' + id);
                el.empty().append(new Option('TODOS', '', true, true));
                items.forEach(i => el.append(new Option(i[textKey], i[valKey], false, false)));
            };

            fill('cmbCliente', data.clientes, 'id', 'razon');
            fill('cmbProducto', data.productos, 'codigo', 'Descripcion');
            fill('cmbRubro', data.rubros, 'rubro', 'rubro');
            fill('cmbMarca', data.marcas, 'id', 'marca');
            fill('cmbFamilia', data.familias, 'id', 'familia');
            fill('cmbUnidad', data.unidades, 'id', 'nombre');
            fill('cmbRamaCliente', data.ramas, 'id', 'rama');
            
            const elProv = $('#cmbProvincia');
            elProv.empty().append(new Option('TODOS', '', true, true));
            data.provincias.forEach(p => elProv.append(new Option(p, p, false, false)));

            const elVend = $('#cmbVendedor');
            elVend.empty().append(new Option('TODOS', '', true, true));
            data.vendedores.forEach(v => elVend.append(new Option(v.nombre, v.id, false, false)));

            if (data.multideposito_habilitado) {
                const elDep = $('#cmbDeposito');
                elDep.empty().append(new Option('TODOS', '', true, true));
                data.depositos.forEach(d => elDep.append(new Option(d.nombre, d.id, false, false)));
                $('#grupoFiltroDepositoVentas').removeClass('d-none');
            }

            // Primero se cargan las opciones y luego se crea Select2, para que
            // la opción vacía TODOS sea visible y seleccionable en cada filtro.
            $('.select2-filter').select2({
                dropdownParent: $('#main-content'),
                width: '100%'
            }).val('').trigger('change.select2');

        } catch (error) {
            console.error(error);
            Swal.fire({
                icon: 'error',
                title: 'No se pudieron cargar los filtros',
                text: error.message || 'Error desconocido al consultar los datos maestros.'
            });
        }
    }

    function getPayload() {
        return {
            desde: document.getElementById('txtDesde').value,
            hasta: document.getElementById('txtHasta').value,
            cliente_id: $('#cmbCliente').val() || null,
            vendedor_id: $('#cmbVendedor').val() || null,
            producto_cod: $('#cmbProducto').val() || null,
            rubro: $('#cmbRubro').val() || null,
            marca: $('#cmbMarca').val() || null,
            familia: $('#cmbFamilia').val() || null,
            unidad_id: $('#cmbUnidad').val() || null,
            provincia: $('#cmbProvincia').val() || null,
            rama_id: $('#cmbRamaCliente').val() || null,
            deposito_id: $('#cmbDeposito').val() || null
        };
    }

    async function cargarInforme() {
        const btn = document.getElementById('btnConsultar');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';

        document.getElementById('tbodyVentas').innerHTML = '<tr><td colspan="20" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
        document.getElementById('tblCards').innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info mb-2"></div><br>Actualizando...</div>';

        try {
            const resp = await fetch('api_reporte_ventas.php?action=generate_report', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(getPayload())
            });
            const res = await resp.json();

            if (res.status !== 'ok') throw new Error(res.error || 'Error desconocido');
            
            listadoVentas = res.data || [];
            ordenTabla.aplicar();
            
            // Recálculo global para los totales del pie (Toda la data, no solo la paginada)
            let sumTotal = 0, sumCosto = 0, sumUtilidad = 0;
            let sumIvaVenta = 0, sumImpInt = 0, sumIIBB = 0, sumIvaCompra = 0;

            listadoVentas.forEach(r => {
                sumTotal += parseFloat(r.Total || 0);
                sumCosto += parseFloat(r.Costo || 0);
                sumUtilidad += parseFloat(r.Utilidad_Calc || 0);
                sumIvaVenta += parseFloat(r.IVA || 0);
                sumImpInt += parseFloat(r.Imp_Int || 0);
                sumIIBB += parseFloat(r.IIBB || 0);
                sumIvaCompra += parseFloat(r.Iva_compras || 0);
            });

            // Actualizar Panel de Totales
            document.getElementById('txtTotalGeneral').value = formatMoney(sumTotal);
            document.getElementById('txtCostoTotal').value = formatMoney(sumCosto);
            document.getElementById('txtUtilidad').value = formatMoney(sumUtilidad);
            document.getElementById('txtIvaVenta').value = formatMoney(sumIvaVenta);
            document.getElementById('txtImpInt').value = formatMoney(sumImpInt);
            document.getElementById('txtIIBB').value = formatMoney(sumIIBB);
            document.getElementById('txtIvaCompra').value = formatMoney(sumIvaCompra);

            pagActual = 1; window.pagActual = 1;
            renderPagina();
            renderGraficos();

        } catch (error) {
            console.error(error);
            document.getElementById('tbodyVentas').innerHTML = '<tr><td colspan="20" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            document.getElementById('tblCards').innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = listadoVentas.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listadoVentas.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyVentas');
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="20" class="text-center py-5 text-muted">NO SE ENCONTRARON RESULTADOS PARA LOS FILTROS APLICADOS</td></tr>';
            return;
        }

        let lastComprobante = '';
        let isDarker = false;

        data.forEach(r => {
            const currentComprobante = r.Tipo + r.Numero;
            if (currentComprobante !== lastComprobante) {
                isDarker = !isDarker;
                lastComprobante = currentComprobante;
            }

            const tr = document.createElement('tr');
            if (isDarker) tr.style.backgroundColor = 'rgba(255, 255, 255, 0.05)';

            tr.innerHTML = `
                <td class="ps-3">${formatDate(r.Fecha)}</td>
                <td>${r.Tipo}</td>
                <td class="fw-bold text-info">${r.Numero}</td>
                <td>${r.Codigo}</td>
                <td class="text-truncate" style="max-width: 200px;" title="${r.Descripcion}">${r.Descripcion}</td>
                <td class="text-end fw-bold">${r.Cantidad}</td>
                <td class="text-end fw-bold text-accent">$ ${formatMoney(r.Total)}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.Imp_Int)}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.IVA)}</td>
                <td class="text-end text-success fw-bold">$ ${formatMoney(r.Neto)}</td>
                <td>${r.Rubro || ''}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.IIBB)}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.Costo)}</td>
                <td>${r.Usuario || ''}</td>
                <td class="text-truncate" style="max-width: 100px;">${r.Vendedor || ''}</td>
                <td class="text-truncate" style="max-width: 150px;">${r.Proveedor || ''}</td>
                <td class="text-truncate" style="max-width: 150px;">${r.Cliente || ''}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.Iva_compras)}</td>
                <td>${r.Marca || ''}</td>
                <td class="text-end text-warning">${formatMoney(r.Porc_Descuento)} %</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        
        if (!data || data.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50 italic">No se encontraron resultados.</div>';
            return;
        }

        let lastComprobante = '';
        let isDarker = false;
        let htmlCards = '';

        data.forEach(r => {
            const currentComprobante = r.Tipo + r.Numero;
            if (currentComprobante !== lastComprobante) {
                isDarker = !isDarker;
                lastComprobante = currentComprobante;
            }

            // Para agrupar visualmente en móviles usamos un borde sutil
            const cardBorder = isDarker ? 'border-color: rgba(0, 255, 255, 0.3);' : '';

            htmlCards += `
            <div class="erp-card" style="${cardBorder}">
                <div class="erp-card-header mb-1">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 220px;">${r.Cliente || 'SIN CLIENTE'}</div>
                        <span class="erp-card-badge ms-1 bg-dark text-white border border-secondary">${r.Tipo}</span>
                    </div>
                    <span class="erp-card-code text-info">${r.Numero}</span>
                </div>
                
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha / Código</span>
                    <span class="erp-card-value">${formatDate(r.Fecha)} <span class="text-muted mx-1">|</span> <span class="text-warning">${r.Codigo}</span></span>
                </div>
                
                <div class="erp-card-row">
                    <span class="erp-card-label">Producto</span>
                    <span class="erp-card-value text-truncate" style="max-width: 65%;" title="${r.Descripcion}">${r.Descripcion}</span>
                </div>
                
                <div class="erp-card-row">
                    <span class="erp-card-label">Vend / Usuario</span>
                    <span class="erp-card-value text-truncate" style="max-width: 65%;">${r.Vendedor || '-'} <span class="text-muted mx-1">/</span> ${r.Usuario || '-'}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Clasif. / Prov</span>
                    <span class="erp-card-value text-muted text-truncate" style="font-size: 10px; max-width: 65%;">
                        ${r.Rubro || '-'} / ${r.Marca || '-'} <br> Prov: ${r.Proveedor || '-'}
                    </span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Impuestos</span>
                    <span class="erp-card-value text-muted" style="font-size: 10px;">
                        IVA: $${formatMoney(r.IVA)} | I.Int: $${formatMoney(r.Imp_Int)} <br>
                        IIBB: $${formatMoney(r.IIBB)} | Comp: $${formatMoney(r.Iva_compras)}
                    </span>
                </div>

                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <div class="w-100 d-flex justify-content-between align-items-end">
                        <div>
                            <span class="erp-card-label d-block text-white-50">Costo: $${formatMoney(r.Costo)} | Desc: ${formatMoney(r.Porc_Descuento)}%</span>
                            <span class="erp-card-label d-block mt-1">Cantidad</span>
                            <span class="erp-card-value fs-6">${r.Cantidad}</span>
                        </div>
                        <div class="text-end ms-2">
                            <span class="erp-card-label d-block text-success">Neto</span>
                            <span class="erp-card-value text-success">$ ${formatMoney(r.Neto)}</span>
                            <span class="erp-card-label d-block text-accent mt-1">Total</span>
                            <span class="erp-card-value price fs-5 text-accent">$ ${formatMoney(r.Total)}</span>
                        </div>
                    </div>
                </div>
            </div>`;
        });
        
        wrap.innerHTML = htmlCards;
    }

    function renderGraficos() {
        if (!document.getElementById('ventas-graficos-pane').classList.contains('active')) return;

        ventasCharts.forEach(chart => chart.destroy());
        ventasCharts = [];

        const sinDatos = document.getElementById('graficosSinDatos');
        const contenedor = document.getElementById('graficosVentas');
        if (!listadoVentas.length || typeof Chart === 'undefined') {
            sinDatos.style.display = 'block';
            sinDatos.textContent = listadoVentas.length
                ? 'NO SE PUDO CARGAR EL MOTOR DE GRÁFICOS'
                : 'NO HAY RESULTADOS PARA GRAFICAR';
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

        const aggregate = (keyFn, valueFn) => {
            const result = new Map();
            listadoVentas.forEach(row => {
                const key = keyFn(row) || 'SIN DEFINIR';
                result.set(key, (result.get(key) || 0) + Number(valueFn(row) || 0));
            });
            return [...result.entries()];
        };
        const top = entries => entries.sort((a, b) => b[1] - a[1]).slice(0, 10);
        const axis = {
            ticks: { color: textColor },
            grid: { color: gridColor },
            border: { color: gridColor }
        };
        const tooltip = {
            callbacks: { label: context => `${context.dataset.label || context.label}: ${money(context.raw)}` }
        };

        const porDia = aggregate(r => String(r.Fecha || '').split(' ')[0], r => r.Total)
            .sort((a, b) => a[0].localeCompare(b[0]));
        ventasCharts.push(new Chart(document.getElementById('chartVentasDia'), {
            type: 'line',
            data: {
                labels: porDia.map(([fecha]) => formatDate(fecha)),
                datasets: [{ label: 'Venta total', data: porDia.map(([, total]) => total), borderColor: palette[0], backgroundColor: 'rgba(0,140,149,.16)', fill: true, tension: .25, pointRadius: 3 }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { labels: { color: textColor } }, tooltip } }
        }));

        const productos = top(aggregate(r => `${r.Codigo || ''} · ${r.Descripcion || 'SIN DESCRIPCIÓN'}`, r => r.Total));
        ventasCharts.push(new Chart(document.getElementById('chartProductos'), {
            type: 'bar',
            data: { labels: productos.map(([nombre]) => nombre), datasets: [{ label: 'Venta total', data: productos.map(([, total]) => total), backgroundColor: palette[1] }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip } }
        }));

        const vendedores = top(aggregate(r => r.Vendedor || 'SIN VENDEDOR', r => r.Total));
        ventasCharts.push(new Chart(document.getElementById('chartVendedores'), {
            type: 'bar',
            data: { labels: vendedores.map(([nombre]) => nombre), datasets: [{ label: 'Venta total', data: vendedores.map(([, total]) => total), backgroundColor: palette[4] }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip } }
        }));

        const usuarios = top(aggregate(r => r.Usuario || 'SIN USUARIO', r => r.Total));
        ventasCharts.push(new Chart(document.getElementById('chartUsuarios'), {
            type: 'bar',
            data: { labels: usuarios.map(([nombre]) => nombre), datasets: [{ label: 'Venta total', data: usuarios.map(([, total]) => total), backgroundColor: palette[2] }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip } }
        }));

        const rubros = top(aggregate(r => r.Rubro || 'SIN RUBRO', r => r.Total));
        ventasCharts.push(new Chart(document.getElementById('chartRubros'), {
            type: 'doughnut',
            data: { labels: rubros.map(([nombre]) => nombre), datasets: [{ data: rubros.map(([, total]) => total), backgroundColor: palette }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { color: textColor, boxWidth: 12 } }, tooltip } }
        }));

        const total = listadoVentas.reduce((sum, r) => sum + Number(r.Total || 0), 0);
        const costo = listadoVentas.reduce((sum, r) => sum + Number(r.Costo || 0), 0);
        const utilidad = listadoVentas.reduce((sum, r) => sum + Number(r.Utilidad_Calc || 0), 0);
        ventasCharts.push(new Chart(document.getElementById('chartRentabilidad'), {
            type: 'bar',
            data: { labels: ['VENTA', 'COSTO', 'UTILIDAD'], datasets: [{ label: 'Importe', data: [total, costo, utilidad], backgroundColor: [palette[0], palette[2], palette[1]] }] },
            options: { responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip } }
        }));

        const ramas = top(aggregate(r => r.Rama || 'SIN RAMA', r => r.Total));
        ventasCharts.push(new Chart(document.getElementById('chartRamasClientes'), {
            type: 'bar',
            data: { labels: ramas.map(([rama]) => rama), datasets: [{ label: 'Venta total', data: ramas.map(([, totalRama]) => totalRama), backgroundColor: palette[5] }] },
            options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false, scales: { x: axis, y: axis }, plugins: { legend: { display: false }, tooltip } }
        }));
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloReporteVentas')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    // Botones (Consultar / Exportar)
    document.getElementById('btnConsultar').addEventListener('click', cargarInforme);
    
    document.getElementById('btnExportar').addEventListener('click', async () => {
        const btn = document.getElementById('btnExportar');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> GENERANDO...';

        try {
            const resp = await fetch('api_reporte_ventas.php?action=export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(getPayload())
            });
            const res = await resp.json();
            
            if (res.status === 'ok') {
                const link = document.createElement('a');
                link.href = 'data:application/vnd.ms-excel;base64,' + res.file_content;
                link.download = res.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                throw new Error(res.error || 'Error al exportar');
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    });

    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split(' ')[0].split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    }

    init();
})();
</script>
