<?php
/**
 * reporte_movimientos_usuarios.php - MÓDULO DE AUDITORÍA DE USUARIOS
 * Visualiza y agrupa todas las transacciones generadas por un usuario.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloMovUsuarios">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-users-viewfinder text-accent me-2"></i>MOVIMIENTOS POR USUARIO</h4>
            <small class="text-accent opacity-75">AUDITORÍA Y VOLUMEN DE OPERACIONES DE TESORERÍA</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">FECHA DESDE</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_desde" name="fecha_desde" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">FECHA HASTA</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_hasta" name="fecha_hasta" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">USUARIO OPERADOR</label>
                    <select class="form-select select2-simple trigger-filter" name="usuario" id="usuario">
                        <option value="">TODOS LOS USUARIOS</option>
                    </select>
                </div>
                <div class="col-12 col-md-5 d-flex align-items-center pb-2">
                    <div class="form-check form-switch ms-2">
                        <input class="form-check-input trigger-filter" type="checkbox" id="agrupar" name="agrupar" value="1" style="transform: scale(1.3); cursor: pointer;">
                        <label class="form-check-label text-white fw-bold ms-2" for="agrupar" style="cursor: pointer;">
                            AGRUPAR POR TIPO DE MOVIMIENTO
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>

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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;" id="tablaMovimientos">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;" id="theadDetalle">
                <tr>
                    <th class="text-white ps-3" style="width: 100px;">FECHA</th>
                    <th class="text-white">USUARIO</th>
                    <th class="text-white">TIPO / ORIGEN</th>
                    <th class="text-white">DETALLE / OBSERVACIONES</th>
                    <th class="text-white">COMP.</th>
                    <th class="text-end text-white" style="width: 130px;">INGRESOS</th>
                    <th class="text-end text-white" style="width: 130px;">EGRESOS</th>
                </tr>
            </thead>
            <thead class="sticky-top d-none" style="background-color: #0d2b45; z-index: 5;" id="theadAgrupado">
                <tr>
                    <th class="text-white ps-3">USUARIO</th>
                    <th class="text-end text-white">VENTAS</th>
                    <th class="text-end text-white">COBROS</th>
                    <th class="text-end text-white">PAGOS</th>
                    <th class="text-end text-white">AJUSTES</th>
                    <th class="text-end text-white">TRANSF. ENV.</th>
                    <th class="text-end text-white">TRANSF. REC.</th>
                    <th class="text-end text-white">RETIROS</th>
                    <th class="text-end text-white">MOVIMIENTOS</th>
                    <th class="text-end text-white">OTROS</th>
                    <th class="text-end text-accent fw-bold pe-3">VOLUMEN TOTAL</th>
                </tr>
            </thead>
            <tbody id="tbodyMovimientos">
                <tr>
                    <td colspan="11" class="text-center py-5 text-muted fst-italic">
                        INGRESE LOS FILTROS Y PRESIONE BUSCAR
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">INGRESE LOS FILTROS Y PRESIONE BUSCAR</div>
    </div>

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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div id="panelTotalesAgrupado" class="card card-totales-dark shadow-sm border border-secondary mt-3 mb-4 d-none" style="background-color: #000a1a;">
        <div class="card-body p-3">
            <h6 class="text-accent fw-bold border-bottom border-secondary pb-2 mb-3"><i class="fa-solid fa-chart-pie me-2"></i>TOTALES GENERALES</h6>
            <div class="row g-2 text-end" id="totalesAgrupadosContent" style="font-size: 0.9rem;">
                </div>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formFiltros');
    const tbody = document.getElementById('tbodyMovimientos');
    const cards = document.getElementById('tblCards');
    const theadDetalle = document.getElementById('theadDetalle');
    const theadAgrupado = document.getElementById('theadAgrupado');
    const panelTotales = document.getElementById('panelTotalesAgrupado');
    const divTotales = document.getElementById('totalesAgrupadosContent');

    let debounceTimer = null;
    let ultimaRespuesta = null;
    let currentData = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    const traducirOrigen = (origen) => {
        const dicc = {
            'tickets': 'VENTAS', 'pagos': 'COBROS', 'pagoaproveedores': 'PAGOS A PROV.',
            'ajuste_caja': 'AJUSTES', 'transf_cajas': 'TRANSFERENCIAS',
            'retiro_central': 'RETIROS A CENTRAL', 'cccuentastesoreria': 'MOV. MANUALES'
        };
        return dicc[origen] || 'OTROS (' + origen.toUpperCase() + ')';
    };

    async function init() {
        $('.select2-simple').select2({ theme: 'default', width: '100%', dropdownParent: $('#main-content') });

        try {
            const resp = await fetch('api_reporte_movimientos_usuarios.php?action=get_filters', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.success) {
                const cmbUsu = document.getElementById('usuario');
                res.usuarios.forEach(u => cmbUsu.add(new Option(u.usuario.toUpperCase(), u.usuario)));
            }
        } catch (e) { console.error("Error cargando filtros", e); }

        $('.trigger-filter').on('input change', function () {
            clearTimeout(debounceTimer);
            const desde = document.getElementById('fecha_desde').value;
            const hasta = document.getElementById('fecha_hasta').value;
            
            if ((desde && parseInt(desde.split('-')[0]) < 2000) || (hasta && parseInt(hasta.split('-')[0]) < 2000)) return;
            
            debounceTimer = setTimeout(() => cargarReporte(), 800);
        });

        document.getElementById('btnConsultar').addEventListener('click', () => {
            clearTimeout(debounceTimer);
            cargarReporte();
        });

        document.getElementById('btnExportar').addEventListener('click', () => {
            if(ultimaRespuesta) generarExcel(ultimaRespuesta);
            else Swal.fire('Atención', 'Primero consulte los datos antes de exportar.', 'warning');
        });
    }

    async function cargarReporte() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const btn = document.getElementById('btnConsultar');

        try {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';
            panelTotales.classList.add('d-none');

            const resp = await fetch(`api_reporte_movimientos_usuarios.php?action=get_report&${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error);

            ultimaRespuesta = result.data;
            currentData = result.data.movimientos || [];
            
            calcularTotalesGlobales(ultimaRespuesta);

            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR';
        }
    }

    function renderPagina() {
        const total = currentData.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = currentData.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function calcularTotalesGlobales(data) {
        if (!data.agrupado || !data.movimientos || data.movimientos.length === 0) {
            panelTotales.classList.add('d-none');
            return;
        }

        let tVentas=0, tCobros=0, tPagos=0, tAjustes=0, tTransfEnv=0, tTransfRec=0, tRetiros=0, tMovs=0, tOtros=0, tGlobal=0;

        data.movimientos.forEach(v => {
            tVentas += parseFloat(v.ventas); tCobros += parseFloat(v.cobros);
            tPagos += parseFloat(v.pagos); tAjustes += parseFloat(v.ajustes);
            tTransfEnv += parseFloat(v.transf_enviadas || 0); tTransfRec += parseFloat(v.transf_recibidas || 0);
            tRetiros += parseFloat(v.retiros); tMovs += parseFloat(v.movimientos); 
            tOtros += parseFloat(v.otros); tGlobal += parseFloat(v.total_volumen);
        });

        divTotales.innerHTML = `
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Ventas</span><span class="text-success fw-bold">$ ${tVentas.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Cobros</span><span class="text-success fw-bold">$ ${tCobros.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Pagos</span><span class="text-danger fw-bold">$ ${tPagos.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Ajustes</span><span class="text-warning fw-bold">$ ${tAjustes.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Transf. Env.</span><span class="text-danger fw-bold">$ ${tTransfEnv.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-2"><span class="text-white-50 d-block small">Transf. Rec.</span><span class="text-success fw-bold">$ ${tTransfRec.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-3 mt-3"><span class="text-white-50 d-block small">Retiros</span><span class="text-primary fw-bold">$ ${tRetiros.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-3 mt-3"><span class="text-white-50 d-block small">Mov. Manuales</span><span class="text-secondary fw-bold">$ ${tMovs.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-3 mt-3"><span class="text-white-50 d-block small">Otros</span><span class="text-muted fw-bold">$ ${tOtros.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
            <div class="col-6 col-md-3 mt-3 border-start border-secondary"><span class="text-accent d-block small fw-bold">VOLUMEN TOTAL</span><span class="text-accent fw-bold fs-5">$ ${tGlobal.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
        `;
        panelTotales.classList.remove('d-none');
    }

    function renderTabla(datos) {
        let html = '';
        const agrp = ultimaRespuesta.agrupado;

        if (agrp) {
            theadDetalle.classList.add('d-none');
            theadAgrupado.classList.remove('d-none');

            if (datos.length === 0) {
                html = '<tr><td colspan="11" class="text-center py-4 text-muted">NO SE ENCONTRARON OPERACIONES</td></tr>';
            } else {
                datos.forEach(v => {
                    html += `
                        <tr>
                            <td class="fw-bold text-white text-uppercase ps-3"><i class="fa-solid fa-user text-muted me-2"></i>${v.usuario}</td>
                            <td class="text-end text-success">${parseFloat(v.ventas) > 0 ? '$ ' + parseFloat(v.ventas).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-success">${parseFloat(v.cobros) > 0 ? '$ ' + parseFloat(v.cobros).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-danger">${parseFloat(v.pagos) > 0 ? '$ ' + parseFloat(v.pagos).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-warning">${parseFloat(v.ajustes) > 0 ? '$ ' + parseFloat(v.ajustes).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-danger">${parseFloat(v.transf_enviadas || 0) > 0 ? '$ ' + parseFloat(v.transf_enviadas).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-success">${parseFloat(v.transf_recibidas || 0) > 0 ? '$ ' + parseFloat(v.transf_recibidas).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-primary">${parseFloat(v.retiros) > 0 ? '$ ' + parseFloat(v.retiros).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-secondary">${parseFloat(v.movimientos) > 0 ? '$ ' + parseFloat(v.movimientos).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end text-muted">${parseFloat(v.otros) > 0 ? '$ ' + parseFloat(v.otros).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                            <td class="text-end fw-bold text-accent pe-3">$ ${parseFloat(v.total_volumen).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                        </tr>
                    `;
                });
            }
        } else {
            theadDetalle.classList.remove('d-none');
            theadAgrupado.classList.add('d-none');

            if (datos.length === 0) {
                html = '<tr><td colspan="7" class="text-center py-4 text-muted">NO SE ENCONTRARON OPERACIONES CON ESTOS FILTROS</td></tr>';
            } else {
                datos.forEach(v => {
                    const d = parseFloat(v.debe || 0);
                    const h = parseFloat(v.haber || 0);
                    const tipoAclarado = v.tipo_origen === 'transf_cajas' ? (d > 0 ? '<span class="text-success ms-1 small">(RECIBIDA)</span>' : '<span class="text-danger ms-1 small">(ENVIADA)</span>') : '';

                    html += `
                        <tr>
                            <td class="text-white-50 ps-3">${formatearFechaHora(v.fecha)}</td>
                            <td class="fw-bold text-white">${v.usuario.toUpperCase()}</td>
                            <td class="text-info small fw-bold">${traducirOrigen(v.tipo_origen)}${tipoAclarado}</td>
                            <td class="text-white text-uppercase text-truncate" style="max-width: 250px;" title="${(v.descripcion || 'S/D')}">${(v.descripcion || 'S/D')}</td>
                            <td class="text-muted small">${v.compasoc || '-'}</td>
                            <td class="text-end text-success fw-bold">${d > 0 ? '$ ' + d.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                            <td class="text-end text-danger fw-bold pe-3">${h > 0 ? '$ ' + h.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        </tr>
                    `;
                });
            }
        }
        tbody.innerHTML = html;
    }

    function renderTarjetas(datos) {
        let html = '';
        const agrp = ultimaRespuesta.agrupado;

        if (datos.length === 0) {
            cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50 fst-italic">NO SE ENCONTRARON OPERACIONES</div>';
            return;
        }

        if (agrp) {
            datos.forEach(v => {
                html += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-white"><i class="fa-solid fa-user text-muted me-2"></i>${v.usuario.toUpperCase()}</div>
                    </div>
                    ${parseFloat(v.ventas) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Ventas</span><span class="erp-card-value text-success">$ ${parseFloat(v.ventas).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.cobros) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Cobros</span><span class="erp-card-value text-success">$ ${parseFloat(v.cobros).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.pagos) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Pagos</span><span class="erp-card-value text-danger">$ ${parseFloat(v.pagos).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.ajustes) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Ajustes</span><span class="erp-card-value text-warning">$ ${parseFloat(v.ajustes).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.transf_enviadas||0) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Transf. Enviadas</span><span class="erp-card-value text-danger">$ ${parseFloat(v.transf_enviadas).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.transf_recibidas||0) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Transf. Recibidas</span><span class="erp-card-value text-success">$ ${parseFloat(v.transf_recibidas).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.retiros) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Retiros</span><span class="erp-card-value text-primary">$ ${parseFloat(v.retiros).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.movimientos) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Mov. Manuales</span><span class="erp-card-value text-secondary">$ ${parseFloat(v.movimientos).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${parseFloat(v.otros) > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Otros</span><span class="erp-card-value text-muted">$ ${parseFloat(v.otros).toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    
                    <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                        <span class="erp-card-label text-accent fw-bold">VOLUMEN TOTAL</span>
                        <span class="erp-card-value text-accent fw-bold fs-6">$ ${parseFloat(v.total_volumen).toLocaleString('es-AR',{minimumFractionDigits:2})}</span>
                    </div>
                </div>`;
            });
        } else {
            datos.forEach(v => {
                const d = parseFloat(v.debe || 0);
                const h = parseFloat(v.haber || 0);
                const tipoMov = traducirOrigen(v.tipo_origen);
                const acla = v.tipo_origen === 'transf_cajas' ? (d > 0 ? ' (REC)' : ' (ENV)') : '';
                const badgeStyle = d > 0 
                    ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' 
                    : (h > 0 ? 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;' : 'background:rgba(23,162,184,.2);color:#17a2b8;border:1px solid #17a2b8;');

                html += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title text-truncate" style="max-width: 200px;">${(v.descripcion || 'S/D').toUpperCase()}</div>
                            <small class="text-white-50"><i class="fa-solid fa-user me-1"></i> ${v.usuario.toUpperCase()}</small>
                        </div>
                        <span class="erp-card-badge text-center" style="${badgeStyle}; font-size:0.7rem;">${tipoMov}${acla}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatearFechaHora(v.fecha)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">N° Comp.</span><span class="erp-card-value">${v.compasoc || '-'}</span></div>
                    ${d > 0 ? `<div class="erp-card-row mt-1"><span class="erp-card-label">Ingresos</span><span class="erp-card-value text-success fw-bold">$ ${d.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    ${h > 0 ? `<div class="erp-card-row mt-1"><span class="erp-card-label">Egresos</span><span class="erp-card-value text-danger fw-bold">$ ${h.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                </div>`;
            });
        }
        cards.innerHTML = html;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloMovUsuarios')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatearFechaHora(datetime) {
        if(!datetime) return '-';
        const [fecha, hora] = datetime.split(' ');
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y}`;
    }

    function generarExcel(data) {
        if (!data.movimientos || data.movimientos.length === 0) return;
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        let excelHtml = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><style>.header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; } .title { font-size: 14pt; font-weight: bold; text-align: left; } .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; } .text { text-align: left; border: 0.5pt solid #ccc; } .date { text-align: center; border: 0.5pt solid #ccc; }</style></head><body><table><tr><td colspan="${data.agrupado ? '11' : '7'}" class="title">REPORTE DE MOVIMIENTOS POR USUARIO</td></tr><tr><td colspan="${data.agrupado ? '11' : '7'}">PERIODO: ${fechaDesde} AL ${fechaHasta}</td></tr><tr><td colspan="${data.agrupado ? '11' : '7'}"></td></tr>`;

        if (data.agrupado) {
            excelHtml += `<thead><tr><th class="header">USUARIO</th><th class="header">VENTAS</th><th class="header">COBROS</th><th class="header">PAGOS</th><th class="header">AJUSTES</th><th class="header">TRANSF. ENVIADAS</th><th class="header">TRANSF. RECIBIDAS</th><th class="header">RETIROS</th><th class="header">MOVIMIENTOS</th><th class="header">OTROS</th><th class="header">VOLUMEN TOTAL</th></tr></thead><tbody>`;
            
            let tVentas=0, tCobros=0, tPagos=0, tAjustes=0, tTransfEnv=0, tTransfRec=0, tRetiros=0, tMovs=0, tOtros=0, tGlobal=0;

            data.movimientos.forEach(v => {
                tVentas += parseFloat(v.ventas); tCobros += parseFloat(v.cobros);
                tPagos += parseFloat(v.pagos); tAjustes += parseFloat(v.ajustes);
                tTransfEnv += parseFloat(v.transf_enviadas || 0); tTransfRec += parseFloat(v.transf_recibidas || 0);
                tRetiros += parseFloat(v.retiros); tMovs += parseFloat(v.movimientos); 
                tOtros += parseFloat(v.otros); tGlobal += parseFloat(v.total_volumen);

                excelHtml += `<tr><td class="text">${v.usuario.toUpperCase()}</td><td class="number">${parseFloat(v.ventas)}</td><td class="number">${parseFloat(v.cobros)}</td><td class="number">${parseFloat(v.pagos)}</td><td class="number">${parseFloat(v.ajustes)}</td><td class="number">${parseFloat(v.transf_enviadas || 0)}</td><td class="number">${parseFloat(v.transf_recibidas || 0)}</td><td class="number">${parseFloat(v.retiros)}</td><td class="number">${parseFloat(v.movimientos)}</td><td class="number">${parseFloat(v.otros)}</td><td class="number" style="font-weight:bold;">${parseFloat(v.total_volumen)}</td></tr>`;
            });

            excelHtml += `<tr><td style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTALES GENERALES:</td><td class="number" style="font-weight:bold;">${tVentas}</td><td class="number" style="font-weight:bold;">${tCobros}</td><td class="number" style="font-weight:bold;">${tPagos}</td><td class="number" style="font-weight:bold;">${tAjustes}</td><td class="number" style="font-weight:bold;">${tTransfEnv}</td><td class="number" style="font-weight:bold;">${tTransfRec}</td><td class="number" style="font-weight:bold;">${tRetiros}</td><td class="number" style="font-weight:bold;">${tMovs}</td><td class="number" style="font-weight:bold;">${tOtros}</td><td class="number" style="font-weight:bold; color: #006666;">${tGlobal}</td></tr></tbody></table></body></html>`;
        
        } else {
            excelHtml += `<thead><tr><th class="header">FECHA</th><th class="header">USUARIO</th><th class="header">TIPO ORIGEN</th><th class="header">DETALLE</th><th class="header">COMPROBANTE</th><th class="header">INGRESOS (DEBE)</th><th class="header">EGRESOS (HABER)</th></tr></thead><tbody>`;
            data.movimientos.forEach(v => {
                const tipoAclarado = v.tipo_origen === 'transf_cajas' ? (parseFloat(v.debe || 0) > 0 ? ' (RECIBIDA)' : ' (ENVIADA)') : '';
                excelHtml += `<tr><td class="date">${formatearFechaHora(v.fecha)}</td><td class="text">${v.usuario.toUpperCase()}</td><td class="text">${traducirOrigen(v.tipo_origen)}${tipoAclarado}</td><td class="text">${(v.descripcion || '').toUpperCase()}</td><td class="text">${v.compasoc || ''}</td><td class="number">${parseFloat(v.debe || 0) > 0 ? parseFloat(v.debe) : ''}</td><td class="number">${parseFloat(v.haber || 0) > 0 ? parseFloat(v.haber) : ''}</td></tr>`;
            });
            excelHtml += `</tbody></table></body></html>`;
        }

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Movimientos_Usuarios_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>