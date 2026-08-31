<?php
/**
 * reporte_contable_balance.php - BALANCE DE SUMAS Y SALDOS
 * Estado contable consolidado con lógica de paginación y tarjetas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloBalance">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-scale-balanced text-accent me-2"></i>SUMAS Y SALDOS</h4>
            <small class="text-accent opacity-75">ESTADO CONTABLE CONSOLIDADO POR EJERCICIO</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportarBalance">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnActualizarBalance">
                <i class="fas fa-sync-alt me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">EJERCICIO ECONÓMICO</label>
                    <select id="cmbEjercicioBalance" class="form-select select2-simple filter-trigger-balance"></select>
                </div>

                <div class="col-12 col-md-4">
                    <label class="form-label-custom d-block mb-2">FISCALIDAD</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input filter-trigger-balance" type="radio" name="optFiscalBalance" id="optTodosBal" value="TODOS" checked>
                            <label class="form-check-label text-white-50 small" for="optTodosBal">TODOS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger-balance" type="radio" name="optFiscalBalance" id="optFiscalesBal" value="FISCAL">
                            <label class="form-check-label text-white-50 small" for="optFiscalesBal">FISCALES</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger-balance" type="radio" name="optFiscalBalance" id="optNoFiscalesBal" value="NO_FISCAL">
                            <label class="form-check-label text-white-50 small" for="optNoFiscalesBal">INTERNOS</label>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-4 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input filter-trigger-balance" type="checkbox" id="chkSoloMovimientos" checked>
                        <label class="form-check-label text-white small ms-2" for="chkSoloMovimientos">SOLO CUENTAS CON MOVIMIENTOS</label>
                    </div>
                </div>
            </div>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 50vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblBalance" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 120px;">CÓDIGO</th>
                    <th>DETALLE DE LA CUENTA</th>
                    <th class="text-end" style="width: 140px;">SALDO INICIAL</th>
                    <th class="text-end" style="width: 140px;">DEBE</th>
                    <th class="text-end" style="width: 140px;">HABER</th>
                    <th class="text-end pe-3" style="width: 140px;">SALDO FINAL</th>
                </tr>
            </thead>
            <tbody id="tbodyBalance">
                <tr><td colspan="6" class="text-center py-5 text-muted">SELECCIONE UN EJERCICIO PARA PROCESAR EL BALANCE</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">SELECCIONE UN EJERCICIO PARA PROCESAR EL BALANCE</div>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div id="panelTotalesBalance" class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4 d-none" style="background-color: #000a1a;">
        <div class="card-body p-3">
            <h6 class="text-accent fw-bold border-bottom border-secondary pb-2 mb-3"><i class="fas fa-calculator me-2"></i>TOTALES GENERALES DEL BALANCE</h6>
            <div class="row g-2 text-end" style="font-size: 0.9rem;">
                <div class="col-6 col-md-3 border-end border-secondary border-opacity-25">
                    <span class="text-white-50 d-block small">Saldo Inicial</span>
                    <span id="tot-inicial" class="text-info fw-bold"></span>
                </div>
                <div class="col-6 col-md-3 border-end border-secondary border-opacity-25">
                    <span class="text-white-50 d-block small">Total Debe</span>
                    <span id="tot-debe" class="text-accent fw-bold"></span>
                </div>
                <div class="col-6 col-md-3 border-end border-secondary border-opacity-25">
                    <span class="text-white-50 d-block small">Total Haber</span>
                    <span id="tot-haber" class="text-accent fw-bold"></span>
                </div>
                <div class="col-6 col-md-3">
                    <span class="text-white-50 d-block small">Saldo Final</span>
                    <span id="tot-final" class="text-info fw-bold"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentData = []; 
    let totalesGlobales = null;
    let pagActual = 1, pagTotal = 1, pagTam = 50;
    window.pagActual = 1; window.pagTotal = 1;

    const fmt = (v) => parseFloat(v || 0).toLocaleString('es-AR', {minimumFractionDigits:2, maximumFractionDigits:2});

    async function init() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%', theme: 'default' });
        await cargarEjercicios();

        $('.filter-trigger-balance').on('change', function() {
            pagActual = 1; window.pagActual = 1;
            cargarDatosBalance();
        });

        $('#btnActualizarBalance').on('click', () => { pagActual = 1; window.pagActual = 1; cargarDatosBalance(); });
        $('#btnExportarBalance').on('click', exportarBalanceExcel);
    }

    async function cargarEjercicios() {
        try {
            const resp = await fetch('api_helpers.php?action=get_ejercicios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success && result.data) {
                const $cmb = $('#cmbEjercicioBalance');
                $cmb.empty().append(new Option('SELECCIONE EJERCICIO...', ''));
                result.data.forEach(ej => $cmb.append(new Option(ej.descripcion, ej.id)));
                $cmb.trigger('change');
            }
        } catch (e) { console.error(e); }
    }

    async function cargarDatosBalance() {
        const ej = $('#cmbEjercicioBalance').val();
        if (!ej) return;

        const payload = {
            ejercicio: ej,
            fiscal: $('input[name="optFiscalBalance"]:checked').val(),
            soloMovimientos: $('#chkSoloMovimientos').is(':checked')
        };

        const $tbody = $('#tbodyBalance');
        const $cards = $('#tblCards');
        $tbody.html('<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info"></div><br><small class="text-accent">GENERANDO BALANCE...</small></td></tr>');
        $cards.html('<div class="text-center p-5"><div class="spinner-border text-info"></div></div>');
        $('#panelTotalesBalance').addClass('d-none');

        try {
            const resp = await fetch('api_reporte_contable_balance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);

            currentData = result.data || [];
            totalesGlobales = result.totales;
            
            renderPagina();
            mostrarTotalesGlobales();

        } catch (e) {
            $tbody.html(`<tr><td colspan="6" class="text-danger text-center py-4">Error: ${e.message}</td></tr>`);
            $cards.html(`<div class="text-danger text-center p-4">Error: ${e.message}</div>`);
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

    function renderTabla(data) {
        const $tbody = $('#tbodyBalance').empty();
        if (data.length === 0) return $tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted">SIN DATOS PARA EL CRITERIO SELECCIONADO</td></tr>');

        data.forEach(row => {
            $tbody.append(`
                <tr>
                    <td class="fw-bold text-white ps-3">${row.numcuenta}</td>
                    <td class="text-truncate" style="max-width: 350px;" title="${row.descripcion}">${row.descripcion}</td>
                    <td class="text-end text-white-50">${row.saldo_inicial != 0 ? fmt(row.saldo_inicial) : '-'}</td>
                    <td class="text-end text-accent fw-bold">${row.suma_debe != 0 ? fmt(row.suma_debe) : '-'}</td>
                    <td class="text-end text-accent fw-bold">${row.suma_haber != 0 ? fmt(row.suma_haber) : '-'}</td>
                    <td class="text-end text-info fw-bold pe-3">${row.saldo_final != 0 ? fmt(row.saldo_final) : '-'}</td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const $wrap = $('#tblCards').empty();
        if (data.length === 0) return $wrap.html('<div class="text-center p-5 text-muted">SIN DATOS</div>');

        data.forEach(row => {
            $wrap.append(`
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${row.descripcion}</div>
                    <span class="erp-card-code text-info">#${row.numcuenta}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Saldo Inicial</span>
                    <span class="text-white-50">$ ${fmt(row.saldo_inicial)}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Debe / Haber</span>
                    <div class="text-end">
                        <span class="text-success fw-bold d-block small">${fmt(row.suma_debe)} (D)</span>
                        <span class="text-danger fw-bold d-block small">${fmt(row.suma_haber)} (H)</span>
                    </div>
                </div>
                <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                    <span class="erp-card-label text-info fw-bold">SALDO FINAL</span>
                    <span class="text-info fw-bold fs-6">$ ${fmt(row.saldo_final)}</span>
                </div>
            </div>`);
        });
    }

    function mostrarTotalesGlobales() {
        if (!totalesGlobales) return;
        $('#tot-inicial').text('$ ' + fmt(totalesGlobales.saldo_inicial));
        $('#tot-debe').text('$ ' + fmt(totalesGlobales.suma_debe));
        $('#tot-haber').text('$ ' + fmt(totalesGlobales.suma_haber));
        $('#tot-final').text('$ ' + fmt(totalesGlobales.saldo_final));
        $('#panelTotalesBalance').removeClass('d-none');
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> cuentas`;
        ['','Top'].forEach(sfx => {
            const el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            const cont = document.getElementById('pagBotones'+sfx);
            if(cont){
                cont.innerHTML='';
                if(pagTotal>1){
                    let ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4);
                    if(fin-ini<4) ini=Math.max(1,fin-4);
                    for(let i=ini; i<=fin; i++){
                        const b = document.createElement('button'); b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i; b.onclick = () => pagGo(i); cont.appendChild(b);
                    }
                }
            }
            const ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            const sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            const wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloBalance')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function exportarBalanceExcel() {
        const ej = $('#cmbEjercicioBalance').val();
        if (!ej) return Swal.fire('Atención', 'Seleccione el ejercicio', 'warning');
        const p = new URLSearchParams({ 
            ejercicio: ej, 
            fiscal: $('input[name="optFiscalBalance"]:checked').val(), 
            soloMovimientos: $('#chkSoloMovimientos').is(':checked') 
        });
        window.open(`exportar_balance_excel.php?${p.toString()}`, '_blank');
    }

    init();
})();
</script>