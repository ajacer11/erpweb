<?php
// SEGURIDAD CENTRALIZADA
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloComprasConceptos">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-tags text-accent me-2"></i>MOVIMIENTOS POR CONCEPTO</h4>
            <small class="text-accent opacity-75">GASTOS E IMPUTACIONES DE COMPRA</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros mb-3 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="txtDesde" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="txtHasta" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">CONCEPTO DE GASTO</label>
                    <select id="cmbConcepto" class="form-control filter-auto"></select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-check form-switch pb-1">
                        <input class="form-check-input filter-auto-force" type="checkbox" id="chkAgrupar">
                        <label class="form-check-label text-white fw-bold ms-2" for="chkAgrupar">AGRUPAR POR CONCEPTO</label>
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
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- ============================
         GRILLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap" id="tablaConceptos" style="font-size: 0.9rem;">
            <thead class="sticky-top bg-black">
                <tr>
                    <th class="ps-3 py-2 col-fecha">FECHA</th>
                    <th>CONCEPTO</th>
                    <th class="text-end">IMPORTE</th>
                    <th class="text-end col-alicuota">ALICUOTA</th>
                    <th class="text-end text-accent pe-3">IVA</th>
                </tr>
            </thead>
            <tbody id="tbodyMovimientos">
                <tr><td colspan="5" class="text-center py-5 text-muted italic">Haga clic en ACTUALIZAR para cargar datos</td></tr>
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
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
                <option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- TOTALES FOOTER RESPONSIVO -->
    <div class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4" id="panelTotales" style="display: none; background-color: #000a1a;">
        <div class="card-body p-3">
            <div class="row text-center g-2 align-items-center justify-content-end">
                <div class="col-6 col-md-3">
                    <small class="text-muted d-block text-uppercase" style="font-size: 10px;">TOTAL IMPORTE</small>
                    <span id="txtTotalImporte" class="fw-bold text-white fs-5">$ 0,00</span>
                </div>
                <div class="col-6 col-md-3 border-start border-4 border-accent">
                    <small class="text-accent d-block fw-bold text-uppercase" style="font-size: 10px;">TOTAL IVA</small>
                    <span id="txtTotalIVA" class="fw-bold text-accent fs-4">$ 0,00</span>
                </div>
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

    // ============================================================
    //  INIT
    // ============================================================
    async function init() {
        $('#cmbConcepto').select2({
            dropdownParent: $('#moduloComprasConceptos'),
            width: '100%',
            placeholder: 'TODOS LOS CONCEPTOS...',
            allowClear: true
        });

        try {
            const resp = await fetch('api_reporte_compras_conceptos.php?action=get_filters', {
                headers: commonHeaders
            });
            const data = await resp.json();

            if (data.conceptos) {
                const el = $('#cmbConcepto');
                el.empty().append(new Option('TODOS LOS CONCEPTOS...', '', true, true));
                data.conceptos.forEach(c => el.append(new Option(c.concepto, c.id, false, false)));
            }
        } catch (error) {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los filtros.' });
        }

        configurarListeners();
        cargarInforme();
    }

    // ============================================================
    //  LISTENERS & DEBOUNCE
    // ============================================================
    function configurarListeners() {
        $('#btnConsultar').click(cargarForce);
        $('#btnExportar').click(exportar);

        // Checkbox de agrupado: fuerza búsqueda inmediata
        $('#chkAgrupar').on('change', cargarForce);

        // Debounce para fechas y concepto
        $('.filter-auto').not('.filter-auto-force').on('input change', function () {
            clearTimeout(debounceTimer);
            const desde = $('#txtDesde').val();
            const hasta = $('#txtHasta').val();
            if (desde && parseInt(desde.split('-')[0]) < 2000) return;
            if (hasta && parseInt(hasta.split('-')[0]) < 2000) return;
            debounceTimer = setTimeout(() => cargarInforme(), 800);
        });
    }

    function cargarForce() {
        clearTimeout(debounceTimer);
        cargarInforme();
    }

    // ============================================================
    //  FETCH DE DATOS
    // ============================================================
    async function cargarInforme() {
        const btn = document.getElementById('btnConsultar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';

        const isAgrupado = document.getElementById('chkAgrupar').checked;

        // Mostrar/ocultar columnas en modo agrupado
        if (isAgrupado) {
            $('.col-fecha, .col-alicuota').hide();
        } else {
            $('.col-fecha, .col-alicuota').show();
        }

        $('#tbodyMovimientos').html('<tr><td colspan="5" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Buscando movimientos...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin mb-2 text-info fs-3"></i><br>Buscando...</div>');
        $('#panelTotales').hide();

        const payload = {
            desde:       document.getElementById('txtDesde').value,
            hasta:       document.getElementById('txtHasta').value,
            concepto_id: $('#cmbConcepto').val() || null,
            agrupado:    isAgrupado
        };

        try {
            const resp = await fetch('api_reporte_compras_conceptos.php?action=generate_report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();

            if (res.status !== 'ok') throw new Error(res.error || 'Error desconocido');

            listado = res.data || [];

            // Calcular totales globales de toda la selección
            let totalImporte = 0, totalIVA = 0;
            listado.forEach(r => {
                totalImporte += parseFloat(r.Importe || 0);
                totalIVA     += parseFloat(r.IVA     || 0);
            });
            document.getElementById('txtTotalImporte').textContent = '$ ' + formatMoney(totalImporte);
            document.getElementById('txtTotalIVA').textContent     = '$ ' + formatMoney(totalIVA);

            pagActual = 1; window.pagActual = 1;
            renderPagina();

            if (listado.length > 0) $('#panelTotales').show();

        } catch (error) {
            console.error(error);
            const colspan = document.getElementById('chkAgrupar').checked ? 3 : 5;
            $('#tbodyMovimientos').html(`<tr><td colspan="${colspan}" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>`);
            $('#tblCards').html('<div class="text-center text-danger p-5">Error al consultar datos.</div>');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> ACTUALIZAR';
        }
    }

    // ============================================================
    //  RENDERIZADO SPA & PAGINACIÓN
    // ============================================================
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
        const isAgrupado = document.getElementById('chkAgrupar').checked;
        const tbody = $('#tbodyMovimientos').empty();

        if (!data || data.length === 0) {
            const colspan = isAgrupado ? 3 : 5;
            tbody.append(`<tr><td colspan="${colspan}" class="text-center py-5 text-muted small italic">NO SE ENCONTRARON MOVIMIENTOS EN ESTE PERÍODO</td></tr>`);
            return;
        }

        data.forEach(r => {
            let html = '';
            if (!isAgrupado) html += `<td class="ps-3 text-white-50">${formatDate(r.Fecha)}</td>`;
            html += `<td class="fw-bold text-white text-truncate" style="max-width: 400px;" title="${r.Concepto}">${r.Concepto}</td>
                     <td class="text-end text-white">$ ${formatMoney(r.Importe)}</td>`;
            if (!isAgrupado) html += `<td class="text-end text-white-50">${formatMoney(r.Alicuota)} %</td>`;
            html += `<td class="text-end text-accent fw-bold pe-3">$ ${formatMoney(r.IVA)}</td>`;

            tbody.append(`<tr>${html}</tr>`);
        });
    }

    function renderTarjetas(data) {
        const isAgrupado = document.getElementById('chkAgrupar').checked;
        const wrap = $('#tblCards').empty();

        if (!data || data.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No hay resultados.</div>');
            return;
        }

        data.forEach(r => {
            const importe = parseFloat(r.Importe || 0);
            const iva     = parseFloat(r.IVA     || 0);

            let extraRows = '';
            if (!isAgrupado) {
                extraRows = `
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha</span>
                    <span class="erp-card-value">${formatDate(r.Fecha)}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Alícuota</span>
                    <span class="erp-card-value">${formatMoney(r.Alicuota)} %</span>
                </div>`;
            }

            wrap.append(`
            <div class="erp-card mb-2">
                <div class="erp-card-header mb-1">
                    <div class="erp-card-title text-truncate" style="max-width: 240px;" title="${r.Concepto}">${r.Concepto}</div>
                    <span class="erp-card-code text-info"><i class="fa-solid fa-tags me-1"></i>GASTO</span>
                </div>

                ${extraRows}

                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <div class="w-100 d-flex justify-content-between align-items-end">
                        <div>
                            <span class="erp-card-label d-block text-white-50">Importe: $ ${formatMoney(importe)}</span>
                        </div>
                        <div class="text-end ms-2">
                            <span class="erp-card-label d-block text-accent">IVA</span>
                            <span class="erp-card-value price fs-5 text-accent">$ ${formatMoney(iva)}</span>
                        </div>
                    </div>
                </div>
            </div>
            `);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta  = Math.min(pagActual * pagTam, total);
        const noun   = total === 1 ? 'movimiento' : 'movimientos';
        const info   = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ${noun}`;

        ['', 'Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo' + sfx); if (el) el.innerHTML = info;
            ['btnPagPrim', 'btnPagAnt'].forEach(id => { var e = document.getElementById(id + sfx); if (e) e.disabled = pagActual <= 1; });
            ['btnPagSig',  'btnPagUlt'].forEach(id => { var e = document.getElementById(id + sfx); if (e) e.disabled = pagActual >= pagTotal; });
            var cont = document.getElementById('pagBotones' + sfx);
            if (cont) {
                cont.innerHTML = '';
                if (pagTotal > 1) {
                    var ini = Math.max(1, pagActual - 2), fin = Math.min(pagTotal, ini + 4);
                    if (fin - ini < 4) ini = Math.max(1, fin - 4);
                    for (var i = ini; i <= fin; i++) {
                        var b = document.createElement('button');
                        b.className  = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                        b.textContent = i;
                        b.onclick = (function(p) { return function() { pagGo(p); }; })(i);
                        cont.appendChild(b);
                    }
                }
            }
            var ji  = document.getElementById('pagJumpInput' + sfx); if (ji)  { ji.value = pagActual; ji.max = pagTotal; }
            var sel = document.getElementById('pagTamano'    + sfx); if (sel) sel.value = pagTam;
            var wrap = document.getElementById(sfx === 'Top' ? 'pagTopWrap' : 'pagBotWrap');
            if (wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual = n; window.pagActual = n;
        renderPagina();
        document.getElementById('moduloComprasConceptos')?.scrollIntoView({ behavior: 'smooth' });
    };
    window.pagJump    = function() { var v = parseInt(document.getElementById('pagJumpInput').value);    if (!isNaN(v)) pagGo(v); };
    window.pagJumpTop = function() { var v = parseInt(document.getElementById('pagJumpInputTop').value); if (!isNaN(v)) pagGo(v); };
    window.pagCambiarTamano    = function() { pagTam = parseInt(document.getElementById('pagTamano').value);    pagActual = 1; window.pagActual = 1; renderPagina(); };
    window.pagCambiarTamanoTop = function() { pagTam = parseInt(document.getElementById('pagTamanoTop').value); pagActual = 1; window.pagActual = 1; renderPagina(); };

    // ============================================================
    //  EXPORTAR
    // ============================================================
    async function exportar() {
        const btn = document.getElementById('btnExportar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> ...';

        const payload = {
            desde:       document.getElementById('txtDesde').value,
            hasta:       document.getElementById('txtHasta').value,
            concepto_id: $('#cmbConcepto').val() || null,
            agrupado:    document.getElementById('chkAgrupar').checked
        };

        try {
            const resp = await fetch('api_reporte_compras_conceptos.php?action=export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();

            if (res.status === 'ok') {
                const link = document.createElement('a');
                link.href     = 'data:application/vnd.ms-excel;base64,' + res.file_content;
                link.download = res.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-file-excel me-1"></i> EXPORTAR';
        }
    }

    // ============================================================
    //  HELPERS
    // ============================================================
    function formatMoney(n) { return parseFloat(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 }); }
    function formatDate(d)  { if (!d) return ''; const [y, m, day] = d.split(' ')[0].split('-'); return `${day}/${m}/${y}`; }

    init();
})();
</script>