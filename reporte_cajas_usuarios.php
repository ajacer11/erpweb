<?php
/**
 * reporte_cajas_usuarios.php - REPORTE DE MOVIMIENTOS DE CAJA DE USUARIOS
 * Basado en la lógica de tesorería y saldos acumulados.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloCajasUsuarios">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-cash-register text-accent me-2"></i>MOVIMIENTOS DE CAJAS (USUARIOS)</h4>
            <small class="text-accent opacity-75">DETALLE DE EFECTIVO Y RENDICIONES POR PUESTO</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">PUESTO / CUENTA DE EFECTIVO</label>
                    <select class="form-select select2-simple trigger-filter" name="id_cuenta" id="cmbCajas" required>
                        <option value="">CARGANDO...</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA DESDE</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_desde" name="fecha_desde" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA HASTA</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_hasta" name="fecha_hasta" value="<?= date('Y-m-d') ?>">
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-pills erp-tabs mb-3 gap-1" id="tabCajas" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active btn-sm text-uppercase" id="tab-btn-movimientos" data-bs-toggle="tab" data-bs-target="#panelMovimientos" type="button">
                <i class="fa-solid fa-right-left me-1"></i> Movimientos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link btn-sm text-uppercase" id="tab-btn-aperturas" data-bs-toggle="tab" data-bs-target="#panelAperturas" type="button">
                <i class="fa-solid fa-door-open me-1"></i> Aperturas y Cierres
            </button>
        </li>
    </ul>

    <div class="tab-content">
    <div class="tab-pane fade show active" id="panelMovimientos">

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
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;" id="tablaCajasUsuarios">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3" style="width: 100px;">FECHA</th>
                    <th class="text-white">DETALLE / OBSERVACIONES</th>
                    <th class="text-white">N° COMP.</th>
                    <th class="text-end text-white" style="width: 140px;">INGRESOS</th>
                    <th class="text-end text-white" style="width: 140px;">EGRESOS</th>
                    <th class="text-end text-accent fw-bold pe-3" style="width: 140px;">SALDO</th>
                </tr>
            </thead>
            <tbody id="tbodyMovimientos">
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted fst-italic">
                        POR FAVOR, SELECCIONE UNA CAJA Y EL RANGO DE FECHAS
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">POR FAVOR, SELECCIONE UNA CAJA Y EL RANGO DE FECHAS</div>
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

    </div>
    <div class="tab-pane fade" id="panelAperturas">

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;" id="tablaAperturas">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3">APERTURA</th>
                    <th class="text-white">ABIERTA POR</th>
                    <th class="text-end text-white">SALDO INICIAL</th>
                    <th class="text-white">CIERRE</th>
                    <th class="text-white">CERRADA POR</th>
                    <th class="text-end text-white">SALDO FINAL</th>
                    <th class="text-center text-white pe-3">ESTADO</th>
                </tr>
            </thead>
            <tbody id="tbodyAperturas">
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted fst-italic">
                        POR FAVOR, SELECCIONE UNA CAJA Y EL RANGO DE FECHAS
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCardsAperturas">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">POR FAVOR, SELECCIONE UNA CAJA Y EL RANGO DE FECHAS</div>
    </div>

    </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formFiltros');
    const tbody = document.getElementById('tbodyMovimientos');
    const btnExportar = document.getElementById('btnExportar');
    const cmbCajas = document.getElementById('cmbCajas');

    let debounceTimer = null;
    let currentData = [];
    let globalSaldoAnterior = 0;
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;
    let cachedRawData = null; // Para la exportación
    let vistaActual = 'movimientos';
    let cachedAperturas = null;
    const ordenMovimientos = erpOrdenarTabla('#tablaCajasUsuarios', () => currentData, renderPagina, [
        {campo:'fecha', tipo:'fecha'}, 'observaciones', 'compasoc',
        {campo:'debe_calc', tipo:'numero'}, {campo:'haber_calc', tipo:'numero'},
        {campo:'saldo_acumulado', tipo:'numero'}
    ]);
    const ordenAperturas = erpOrdenarTabla('#tablaAperturas', () => cachedAperturas, () => renderAperturas(cachedAperturas), [
        {campo:'abiertael', tipo:'fecha'}, 'usuario_apertura', {campo:'saldoinicial', tipo:'numero'},
        {campo:'cerradael', tipo:'fecha'}, 'usuario_cierre', {campo:'saldofinal', tipo:'numero'},
        {campo:'estado', tipo:'numero'}
    ]);

    async function init() {
        // Inicializar Select2
        $('.select2-simple').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            theme: 'default'
        });

        // Cargar Cajas desde la API
        try {
            const resp = await fetch('api_reporte_cajas_usuarios.php?action=list_cajas', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            
            if (res.success) {
                $(cmbCajas).empty().append(new Option('SELECCIONE UN PUESTO...', ''));
                if (res.data.length === 0) {
                    $(cmbCajas).append(new Option('NO SE ENCONTRARON CAJAS HABILITADAS', ''));
                } else {
                    res.data.forEach(c => {
                        $(cmbCajas).append(new Option(c.medio.toUpperCase(), c.id));
                    });
                }
            } else {
                throw new Error(res.error || 'Error al cargar lista');
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudieron cargar las cuentas de caja: ' + e.message, 'error');
        }

        // Eventos con Debounce para auto-búsqueda
        $('.trigger-filter').on('input change', function () {
            clearTimeout(debounceTimer);
            const desde = document.getElementById('fecha_desde').value;
            const hasta = document.getElementById('fecha_hasta').value;

            if ((desde && parseInt(desde.split('-')[0]) < 2000) ||
                (hasta && parseInt(hasta.split('-')[0]) < 2000)) {
                return;
            }

            debounceTimer = setTimeout(() => {
                if (cmbCajas.value) {
                    cargarReporte();
                }
            }, 800);
        });

        document.getElementById('btnConsultar').addEventListener('click', () => {
            clearTimeout(debounceTimer);
            cargarReporte();
        });

        btnExportar.addEventListener('click', () => {
            if (vistaActual === 'aperturas') {
                if (cachedAperturas) generarExcelAperturas(cachedAperturas);
                else Swal.fire('Atención', 'Primero consulte los datos antes de exportar.', 'warning');
            } else {
                if (cachedRawData) generarExcel(cachedRawData);
                else Swal.fire('Atención', 'Primero consulte los datos antes de exportar.', 'warning');
            }
        });

        document.getElementById('tabCajas').addEventListener('shown.bs.tab', (e) => {
            vistaActual = e.target.id === 'tab-btn-aperturas' ? 'aperturas' : 'movimientos';
            if (cmbCajas.value) cargarReporte();
        });
    }

    function cargarReporte() {
        if (vistaActual === 'aperturas') return cargarAperturas();
        return cargarMovimientos();
    }

    async function cargarMovimientos() {
        if (!cmbCajas.value) {
            Swal.fire('Atención', 'Seleccione un puesto de trabajo para consultar.', 'warning');
            return;
        }

        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const btn = document.getElementById('btnConsultar');
        const cards = document.getElementById('tblCards');

        try {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_reporte_cajas_usuarios.php?action=get_report&${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error);

            cachedRawData = result.data; // Guardar original para exportar
            procesarSaldos(result.data);
            ordenMovimientos.aplicar();

            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR';
        }
    }

    async function cargarAperturas() {
        if (!cmbCajas.value) {
            Swal.fire('Atención', 'Seleccione un puesto de trabajo para consultar.', 'warning');
            return;
        }

        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const btn = document.getElementById('btnConsultar');
        const tbodyAp = document.getElementById('tbodyAperturas');
        const cardsAp = document.getElementById('tblCardsAperturas');

        try {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';
            tbodyAp.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cardsAp.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_reporte_cajas_usuarios.php?action=get_aperturas_cierres&${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error);

            cachedAperturas = result.data || [];
            ordenAperturas.aplicar();
            renderAperturas(cachedAperturas);

        } catch (error) {
            tbodyAp.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cardsAp.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR';
        }
    }

    function renderAperturas(data) {
        const tbodyAp = document.getElementById('tbodyAperturas');
        const cardsAp = document.getElementById('tblCardsAperturas');

        if (!data || data.length === 0) {
            tbodyAp.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">SIN APERTURAS/CIERRES EN ESTE PERÍODO</td></tr>';
            cardsAp.innerHTML = '<div class="text-center text-muted p-5 opacity-50 fst-italic">SIN APERTURAS/CIERRES EN ESTE PERÍODO</div>';
            return;
        }

        let html = '';
        let htmlCards = '';

        data.forEach(a => {
            const abierta = parseInt(a.estado) === 1;
            const badgeCls = abierta ? 'bg-success text-dark' : 'bg-secondary text-white';
            const badgeTxt = abierta ? 'ABIERTA' : 'CERRADA';
            const cierreTxt = a.cerradael ? formatearFecha(a.cerradael) : '-';
            const cerradaPorTxt = a.usuario_cierre || (abierta ? '-' : 'S/D');
            const saldoFinalTxt = a.cerradael ? ('$ ' + parseFloat(a.saldofinal || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})) : '-';

            html += `
                <tr>
                    <td class="ps-3 text-white">${formatearFecha(a.abiertael)}</td>
                    <td class="text-white-50 text-uppercase">${a.usuario_apertura || 'N/A'}</td>
                    <td class="text-end text-white">$ ${parseFloat(a.saldoinicial || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td class="text-white">${cierreTxt}</td>
                    <td class="text-white-50 text-uppercase">${cerradaPorTxt}</td>
                    <td class="text-end text-white">${saldoFinalTxt}</td>
                    <td class="text-center pe-3"><span class="badge ${badgeCls}">${badgeTxt}</span></td>
                </tr>
            `;

            htmlCards += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title">${a.usuario_apertura || 'N/A'}</div>
                        <span class="erp-card-badge ${badgeCls}">${badgeTxt}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Apertura</span><span class="erp-card-value">${formatearFecha(a.abiertael)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Saldo inicial</span><span class="erp-card-value">$ ${parseFloat(a.saldoinicial || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Cierre</span><span class="erp-card-value">${cierreTxt}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Cerrada por</span><span class="erp-card-value">${cerradaPorTxt}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Saldo final</span><span class="erp-card-value">${saldoFinalTxt}</span></div>
                </div>
            `;
        });

        tbodyAp.innerHTML = html;
        cardsAp.innerHTML = htmlCards;
    }

    // Calcula el saldo dinámico previo a paginar para que arrastre correctamente
    function procesarSaldos(data) {
        globalSaldoAnterior = parseFloat(data.saldo_anterior || 0);
        let saldoActual = globalSaldoAnterior;

        currentData = (data.movimientos || []).map(m => {
            const debe = parseFloat(m.debe || 0);
            const haber = parseFloat(m.haber || 0);
            saldoActual += (debe - haber);
            return {
                ...m,
                debe_calc: debe,
                haber_calc: haber,
                saldo_acumulado: saldoActual
            };
        });
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

    function renderTabla(data) {
        let html = '';

        // Solo mostrar saldo inicial en la primera página
        if (pagActual === 1) {
            html += `
                <tr class="table-active" style="background-color: rgba(255, 255, 255, 0.05);">
                    <td class="ps-3 text-white-50 small">${formatearFechaInput(document.getElementById('fecha_desde').value)}</td>
                    <td colspan="2" class="fw-bold text-accent">SALDO INICIAL / ANTERIOR</td>
                    <td></td>
                    <td></td>
                    <td class="text-end fw-bold text-white pe-3">$ ${globalSaldoAnterior.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                </tr>
            `;
        }

        if (!data || data.length === 0) {
            if (pagActual === 1 && currentData.length === 0) {
                html += '<tr><td colspan="6" class="text-center py-4 text-muted">SIN MOVIMIENTOS EN ESTE PERÍODO</td></tr>';
            }
        } else {
            data.forEach(m => {
                html += `
                    <tr>
                        <td class="ps-3 text-white">${formatearFecha(m.fecha)}</td>
                        <td>
                            <div class="fw-bold text-white text-uppercase text-truncate" style="max-width: 350px;" title="${m.observaciones || 'S/D'}">${m.observaciones || 'S/D'}</div>
                            <small class="text-white-50 text-uppercase">USUARIO: ${m.usuario || 'N/A'}</small>
                        </td>
                        <td class="text-white-50 small">${m.compasoc || '-'}</td>
                        <td class="text-end text-success fw-bold">${m.debe_calc > 0 ? '$ ' + m.debe_calc.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end text-danger fw-bold">${m.haber_calc > 0 ? '$ ' + m.haber_calc.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end fw-bold text-accent pe-3">$ ${m.saldo_acumulado.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        }
        tbody.innerHTML = html;
    }

    function renderTarjetas(data) {
        let html = '';

        // Saldo Inicial en Tarjetas (Solo Pag 1)
        if (pagActual === 1) {
            html += `
            <div class="erp-card mb-2" style="border-left: 4px solid var(--accent);">
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">SALDO INICIAL / ANTERIOR</div>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatearFechaInput(document.getElementById('fecha_desde').value)}</span></div>
                <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                    <span class="erp-card-label text-white fw-bold">SALDO ACUMULADO</span>
                    <span class="erp-card-value text-white fw-bold fs-6">$ ${globalSaldoAnterior.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>
            </div>`;
        }

        if (!data || data.length === 0) {
            if (pagActual === 1 && currentData.length === 0) {
                html += '<div class="text-center text-muted p-5 opacity-50 fst-italic">SIN MOVIMIENTOS EN ESTE PERÍODO</div>';
            }
        } else {
            data.forEach(m => {
                const badgeStyle = m.debe_calc > 0 
                    ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' 
                    : (m.haber_calc > 0 ? 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;' : '');
                const tipoMov = m.debe_calc > 0 ? 'INGRESO' : (m.haber_calc > 0 ? 'EGRESO' : 'MOVIMIENTO');

                html += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title text-truncate" style="max-width: 240px;">${m.observaciones || 'S/D'}</div>
                            <small class="text-white-50"><i class="fa-solid fa-user me-1"></i> ${m.usuario || 'N/A'}</small>
                        </div>
                        <span class="erp-card-badge" style="${badgeStyle}">${tipoMov}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatearFecha(m.fecha)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">N° Comp.</span><span class="erp-card-value">${m.compasoc || '-'}</span></div>
                    
                    ${m.debe_calc > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Ingresos</span><span class="erp-card-value text-success fw-bold">$ ${m.debe_calc.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    ${m.haber_calc > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Egresos</span><span class="erp-card-value text-danger fw-bold">$ ${m.haber_calc.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    
                    <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                        <span class="erp-card-label text-accent fw-bold">SALDO</span>
                        <span class="erp-card-value text-accent fw-bold fs-6">$ ${m.saldo_acumulado.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>`;
            });
        }
        document.getElementById('tblCards').innerHTML = html;
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> movimientos`;

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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloCajasUsuarios')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatearFecha(f) {
        if(!f) return '-';
        const [y, m, d] = f.split(' ')[0].split('-');
        return `${d}/${m}/${y}`;
    }

    function formatearFechaInput(f) {
        if(!f) return '';
        const parts = f.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function generarExcel(data) {
        const nombreCaja = $("#cmbCajas option:selected").text();
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .date { text-align: center; border: 0.5pt solid #ccc; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="6" class="title">MOVIMIENTOS DE CAJA: ${nombreCaja}</td></tr>
                    <tr><td colspan="6">RANGO: ${formatearFechaInput(fechaDesde)} AL ${formatearFechaInput(fechaHasta)}</td></tr>
                    <tr><td colspan="6"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">FECHA</th>
                            <th class="header">OBSERVACIONES</th>
                            <th class="header">NRO. COMPROBANTE</th>
                            <th class="header">INGRESOS</th>
                            <th class="header">EGRESOS</th>
                            <th class="header">SALDO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        let saldo = parseFloat(data.saldo_anterior);
        excelHtml += `
            <tr>
                <td class="date">${formatearFechaInput(fechaDesde)}</td>
                <td colspan="2" style="font-weight:bold; background-color: #f1f1f1;">SALDO INICIAL / ANTERIOR</td>
                <td class="number"></td>
                <td class="number"></td>
                <td class="number" style="font-weight:bold;">${saldo}</td>
            </tr>
        `;

        (data.movimientos || []).forEach(m => {
            const d = parseFloat(m.debe || 0);
            const h = parseFloat(m.haber || 0);
            saldo += (d - h);
            excelHtml += `
                <tr>
                    <td class="date">${formatearFecha(m.fecha)}</td>
                    <td class="text">${(m.observaciones || '').toUpperCase()}</td>
                    <td class="text">${m.compasoc || ''}</td>
                    <td class="number">${d > 0 ? d : ''}</td>
                    <td class="number">${h > 0 ? h : ''}</td>
                    <td class="number" style="font-weight:bold;">${saldo}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Reporte_Caja_${nombreCaja.replace(/\s+/g, '_')}.xls`;
        link.click();
    }

    function generarExcelAperturas(data) {
        const nombreCaja = $("#cmbCajas option:selected").text();
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .date { text-align: center; border: 0.5pt solid #ccc; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="7" class="title">APERTURAS Y CIERRES DE CAJA: ${nombreCaja}</td></tr>
                    <tr><td colspan="7">RANGO: ${formatearFechaInput(fechaDesde)} AL ${formatearFechaInput(fechaHasta)}</td></tr>
                    <tr><td colspan="7"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">APERTURA</th>
                            <th class="header">ABIERTA POR</th>
                            <th class="header">SALDO INICIAL</th>
                            <th class="header">CIERRE</th>
                            <th class="header">CERRADA POR</th>
                            <th class="header">SALDO FINAL</th>
                            <th class="header">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        (data || []).forEach(a => {
            const abierta = parseInt(a.estado) === 1;
            excelHtml += `
                <tr>
                    <td class="date">${formatearFecha(a.abiertael)}</td>
                    <td class="text">${(a.usuario_apertura || '').toUpperCase()}</td>
                    <td class="number">${parseFloat(a.saldoinicial || 0)}</td>
                    <td class="date">${a.cerradael ? formatearFecha(a.cerradael) : ''}</td>
                    <td class="text">${(a.usuario_cierre || '').toUpperCase()}</td>
                    <td class="number">${a.cerradael ? parseFloat(a.saldofinal || 0) : ''}</td>
                    <td class="text">${abierta ? 'ABIERTA' : 'CERRADA'}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Aperturas_Cierres_${nombreCaja.replace(/\s+/g, '_')}.xls`;
        link.click();
    }

    init();
})();
</script>

<style>
    /* Tabs personalizadas estilo ERP (mismo criterio que asientos_auto_cfg.php) */
    .erp-tabs .nav-link {
        background: #1a1a1a;
        color: #888;
        border: 1px solid #333;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 8px 15px;
        border-radius: 5px;
        transition: all 0.3s;
    }
    .erp-tabs .nav-link.active {
        background: var(--accent) !important;
        color: #000 !important;
        font-weight: bold;
        border-color: var(--accent);
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.2);
    }
    .erp-tabs .nav-link:hover:not(.active) {
        background: #252525;
        color: #fff;
        border-color: #555;
    }

    @media (max-width: 768px) {
        .erp-tabs {
            flex-direction: column;
            width: 100%;
        }
        .erp-tabs .nav-item {
            width: 100%;
        }
        .erp-tabs .nav-link {
            text-align: center;
            width: 100%;
            margin-bottom: 2px;
        }
    }
</style>
