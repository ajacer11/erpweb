<?php
/**
 * reporte_contable_mayor.php - LIBRO MAYOR CONTABLE
 * Análisis histórico de movimientos por cuenta con arrastre de saldos.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLibroMayor">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa- landmark text-accent me-2"></i>LIBRO MAYOR</h4>
            <small class="text-accent opacity-75">MOVIMIENTOS HISTÓRICOS POR CUENTA CONTABLE</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportarMayor">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnActualizarMayor">
                <i class="fas fa-sync-alt me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="txtDesdeMayor" class="form-control filter-trigger-mayor" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="txtHastaMayor" class="form-control filter-trigger-mayor" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">CUENTA / PREFIJO</label>
                    <div class="input-group input-group-sm">
                        <input type="text" id="txtCuentaMayor" class="form-control filter-trigger-mayor" placeholder="Ej: 1110...">
                        <button class="btn btn-info" id="btnBuscarCuentaMayor"><i class="fas fa-search"></i></button>
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">EJERCICIO</label>
                    <select id="cmbEjercicioMayor" class="form-select select2-simple filter-trigger-mayor"></select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end pb-1">
                    <div class="form-check form-switch">
                        <input class="form-check-input filter-trigger-mayor" type="checkbox" id="chkSaldoAnterior" checked>
                        <label class="form-check-label text-white small ms-1" for="chkSaldoAnterior">SALDO ANT.</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-4 mt-1 border-top border-secondary border-opacity-25 pt-2">
                    <div class="form-check">
                        <input class="form-check-input filter-trigger-mayor" type="radio" name="optFiscalMayor" id="optTodosMayor" value="TODOS" checked>
                        <label class="form-check-label text-white-50 small" for="optTodosMayor">TODOS</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-trigger-mayor" type="radio" name="optFiscalMayor" id="optFiscalesMayor" value="FISCAL">
                        <label class="form-check-label text-white-50 small" for="optFiscalesMayor">FISCALES</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-trigger-mayor" type="radio" name="optFiscalMayor" id="optNoFiscalesMayor" value="NO_FISCAL">
                        <label class="form-check-label text-white-50 small" for="optNoFiscalesMayor">NO FISCALES</label>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblLibroMayor" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 100px;">FECHA</th>
                    <th style="width: 100px;">ASIENTO</th>
                    <th>DETALLE / CONCEPTO</th>
                    <th class="text-end" style="width: 130px;">DEBE</th>
                    <th class="text-end" style="width: 130px;">HABER</th>
                    <th class="text-end pe-3" style="width: 130px;">SALDO</th>
                </tr>
            </thead>
            <tbody id="tbodyMayor">
                <tr><td colspan="6" class="text-center py-5 text-muted">SELECCIONE LOS FILTROS PARA VISUALIZAR EL MAYOR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">SELECCIONE LOS FILTROS PARA VISUALIZAR EL MAYOR</div>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCuentasMayor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title text-white">BUSCAR CUENTA CONTABLE</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-2 border-bottom border-secondary border-opacity-25">
                    <input type="text" id="txtFiltroModalMayor" class="form-control form-control-sm" placeholder="FILTRAR POR NOMBRE O CÓDIGO...">
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                        <tbody id="tbodyModalCuentasMayor"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentData = []; // Dataset lineal procesado con saldos
    let todasLasCuentas = [];
    let pagActual = 1, pagTotal = 1, pagTam = 50;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%', theme: 'default' });
        await cargarEjercicios();

        $('.filter-trigger-mayor').on('change', function() {
            pagActual = 1; window.pagActual = 1;
            cargarDatosMayor();
        });

        $('#btnActualizarMayor').on('click', cargarDatosMayor);
        $('#btnExportarMayor').on('click', exportarMayorExcel);
        $('#btnBuscarCuentaMayor').on('click', abrirModalBusqueda);
        $('#txtFiltroModalMayor').on('input', renderCuentasModal);
    }

    async function cargarEjercicios() {
        try {
            const resp = await fetch('api_helpers.php?action=get_ejercicios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success && result.data) {
                const $cmb = $('#cmbEjercicioMayor');
                $cmb.empty().append(new Option('SELECCIONE EJERCICIO...', ''));
                result.data.forEach(ej => $cmb.append(new Option(ej.descripcion, ej.id)));
                $cmb.trigger('change');
            }
        } catch (e) { console.error(e); }
    }

    async function cargarDatosMayor() {
        const ej = $('#cmbEjercicioMayor').val();
        if (!ej) return;

        const payload = {
            ejercicio: ej, desde: $('#txtDesdeMayor').val(), hasta: $('#txtHastaMayor').val(),
            cuenta: $('#txtCuentaMayor').val(), fiscal: $('input[name="optFiscalMayor"]:checked').val(),
            incluirSaldoAnt: $('#chkSaldoAnterior').is(':checked')
        };

        const $tbody = $('#tbodyMayor');
        const $cards = $('#tblCards');
        $tbody.html('<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>');
        $cards.html('<div class="text-center p-5"><div class="spinner-border text-info"></div></div>');

        try {
            const resp = await fetch('api_reporte_contable_mayor.php', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);

            procesarDatasetLineal(result.data);
            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (e) {
            $tbody.html(`<tr><td colspan="6" class="text-danger text-center py-4">Error: ${e.message}</td></tr>`);
            $cards.html(`<div class="text-danger text-center p-4">Error: ${e.message}</div>`);
        }
    }

    // Convierte el objeto agrupado por cuenta de la API en un array plano para paginar
    function procesarDatasetLineal(data) {
        currentData = [];
        const incluirSaldoAnt = $('#chkSaldoAnterior').is(':checked');

        for (const cuentaKey in data) {
            const grupo = data[cuentaKey];
            let saldoAcumulado = parseFloat(grupo.saldoAnterior || 0);

            // Fila de Encabezado de Cuenta
            currentData.push({ type: 'HEADER_CTA', label: `MAYOR CTA: ${cuentaKey}` });

            // Fila Saldo Anterior
            if (incluirSaldoAnt) {
                currentData.push({ 
                    type: 'SALDO_ANT', 
                    fecha: $('#txtDesdeMayor').val(), 
                    detalle: 'SALDO ANTERIOR ACUMULADO', 
                    saldo: saldoAcumulado 
                });
            }

            // Movimientos
            grupo.movimientos.forEach(m => {
                saldoAcumulado += (parseFloat(m.debe) - parseFloat(m.haber));
                currentData.push({
                    type: 'MOV', fecha: m.fecha, numasiento: m.numasiento, detalle: m.detalle,
                    debe: parseFloat(m.debe), haber: parseFloat(m.haber), saldo: saldoAcumulado
                });
            });

            // Fila Saldo Final
            currentData.push({ type: 'SALDO_FIN', label: 'SALDO FINAL DE CUENTA', saldo: saldoAcumulado });
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
        const $tbody = $('#tbodyMayor').empty();
        if (data.length === 0) return $tbody.html('<tr><td colspan="6" class="text-center py-5 text-muted">SIN MOVIMIENTOS</td></tr>');

        data.forEach(r => {
            if (r.type === 'HEADER_CTA') {
                $tbody.append(`<tr class="table-active" style="background-color: #1a3a5a !important;"><td colspan="6" class="fw-bold text-accent ps-3">${r.label}</td></tr>`);
            } else if (r.type === 'SALDO_ANT') {
                $tbody.append(`<tr class="fst-italic opacity-75"><td colspan="2"></td><td class="text-white-50">${r.detalle}</td><td colspan="2"></td><td class="text-end fw-bold text-accent pe-3">$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</td></tr>`);
            } else if (r.type === 'SALDO_FIN') {
                $tbody.append(`<tr class="table-dark"><td colspan="2"></td><td class="fw-bold text-info">${r.label}</td><td colspan="2"></td><td class="text-end fw-bold text-info border-bottom border-info pe-3">$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</td></tr>`);
            } else {
                $tbody.append(`
                    <tr>
                        <td class="text-white-50 ps-3">${new Date(r.fecha + 'T12:00:00').toLocaleDateString('es-ES')}</td>
                        <td class="fw-bold text-white text-center">${r.numasiento}</td>
                        <td class="text-truncate" style="max-width: 350px;" title="${r.detalle}">${r.detalle}</td>
                        <td class="text-end text-success">${r.debe > 0 ? '$ ' + r.debe.toLocaleString('es-AR',{minimumFractionDigits:2}) : '-'}</td>
                        <td class="text-end text-danger">${r.haber > 0 ? '$ ' + r.haber.toLocaleString('es-AR',{minimumFractionDigits:2}) : '-'}</td>
                        <td class="text-end fw-bold ${r.saldo < 0 ? 'text-danger' : 'text-accent'} pe-3">$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</td>
                    </tr>
                `);
            }
        });
    }

    function renderTarjetas(data) {
        const $wrap = $('#tblCards').empty();
        if (data.length === 0) return $wrap.html('<div class="text-center p-5 text-muted">SIN MOVIMIENTOS</div>');

        data.forEach(r => {
            if (r.type === 'HEADER_CTA') {
                $wrap.append(`<div class="erp-card-group-label text-accent fw-bold mb-2 mt-3 ps-2"><i class="fas fa-list-ul me-2"></i>${r.label}</div>`);
            } else if (r.type === 'SALDO_ANT') {
                $wrap.append(`<div class="erp-card mb-2" style="border-left: 4px solid var(--accent);"><div class="erp-card-row fw-bold text-white-50"><span>${r.detalle}</span><span class="text-accent">$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div></div>`);
            } else if (r.type === 'SALDO_FIN') {
                $wrap.append(`<div class="erp-card mb-2" style="background: rgba(0,255,255,0.05); border-left: 4px solid var(--info);"><div class="erp-card-row fw-bold text-info"><span>${r.label}</span><span>$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div></div>`);
            } else {
                $wrap.append(`
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-truncate">${r.detalle}</div>
                        <span class="erp-card-code text-info">#${r.numasiento}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span>${new Date(r.fecha + 'T12:00:00').toLocaleDateString('es-ES')}</span></div>
                    ${r.debe > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Debe</span><span class="text-success fw-bold">$ ${r.debe.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    ${r.haber > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Haber</span><span class="text-danger fw-bold">$ ${r.haber.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>` : ''}
                    <div class="erp-card-row mt-1 pt-1 border-top border-secondary border-opacity-25"><span class="erp-card-label text-accent">Saldo</span><span class="fw-bold ${r.saldo < 0 ? 'text-danger' : 'text-accent'}">$ ${r.saldo.toLocaleString('es-AR',{minimumFractionDigits:2})}</span></div>
                </div>`);
            }
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> líneas`;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloLibroMayor')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; cargarDatosMayor(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; cargarDatosMayor(); };

    function exportarMayorExcel() {
        const ej = $('#cmbEjercicioMayor').val();
        if (!ej) return Swal.fire('Atención', 'Seleccione el ejercicio', 'warning');
        const p = new URLSearchParams({ ejercicio: ej, desde: $('#txtDesdeMayor').val(), hasta: $('#txtHastaMayor').val(), cuenta: $('#txtCuentaMayor').val(), fiscal: $('input[name="optFiscalMayor"]:checked').val(), incluirSaldoAnt: $('#chkSaldoAnterior').is(':checked') });
        window.open(`exportar_libro_mayor.php?${p.toString()}`, '_blank');
    }

    async function abrirModalBusqueda() {
        if (todasLasCuentas.length === 0) {
            const resp = await fetch('api_helpers.php?action=get_plan_cuentas', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json(); todasLasCuentas = res.data || [];
        }
        renderCuentasModal(); $('#modalCuentasMayor').modal('show');
    }

    function renderCuentasModal() {
        const filtro = $('#txtFiltroModalMayor').val().toUpperCase();
        const filtered = todasLasCuentas.filter(c => c.numcuenta.toString().includes(filtro) || c.descripcion.toUpperCase().includes(filtro));
        const $tbody = $('#tbodyModalCuentasMayor').empty();
        filtered.slice(0, 30).forEach(c => {
            const tr = $(`<tr class="cursor-pointer"><td>${c.numcuenta}</td><td>${c.descripcion}</td></tr>`);
            tr.click(() => { $('#txtCuentaMayor').val(c.numcuenta); $('#modalCuentasMayor').modal('hide'); cargarDatosMayor(); });
            $tbody.append(tr);
        });
    }

    init();
})();
</script>