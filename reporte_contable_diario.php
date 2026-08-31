<?php
/**
 * reporte_contable_diario.php - LIBRO DIARIO CONTABLE
 * Visualización de asientos con lógica de agrupamiento y vista detallada/agrupada.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLibroDiario">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-book text-accent me-2"></i>LIBRO DIARIO</h4>
            <small class="text-accent opacity-75">CONTROL DE ASIENTOS Y MOVIMIENTOS CONTABLES</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnActualizar">
                <i class="fas fa-sync-alt me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="txtDesde" class="form-control filter-trigger" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="txtHasta" class="form-control filter-trigger" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">EJERCICIO</label>
                    <select id="cmbEjercicio" class="form-select select2-simple filter-trigger"></select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">VISTA</label>
                    <select id="cmbVista" class="form-select filter-trigger">
                        <option value="DETALLADA">DETALLADA</option>
                        <option value="AGRUPADA">AGRUPADA</option>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center gap-4 mt-2">
                    <div class="d-flex gap-3 align-items-center border-end pe-4 border-secondary border-opacity-25">
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optTodos" value="TODOS" checked>
                            <label class="form-check-label text-white-50 small" for="optTodos">TODOS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optFiscales" value="FISCAL">
                            <label class="form-check-label text-white-50 small" for="optFiscales">FISCALES</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optNoFiscales" value="NO_FISCAL">
                            <label class="form-check-label text-white-50 small" for="optNoFiscales">NO FISCALES</label>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optManual" id="optTodosMan" value="TODOS" checked>
                            <label class="form-check-label text-white-50 small" for="optTodosMan">VER TODOS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optManual" id="optSoloMan" value="MANUAL">
                            <label class="form-check-label text-white-50 small" for="optSoloMan">SOLO MANUALES</label>
                        </div>
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
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblLibroDiario" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 150px;">CÓDIGO</th>
                    <th>CUENTA</th>
                    <th class="text-end" style="width: 150px;">DEBE</th>
                    <th class="text-end pe-3" style="width: 150px;">HABER</th>
                </tr>
            </thead>
            <tbody id="tbodyDiario">
                <tr><td colspan="4" class="text-center py-5 text-muted">SELECCIONE EL EJERCICIO PARA CARGAR DATOS</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">SELECCIONE EL EJERCICIO PARA CARGAR DATOS</div>
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

    <div class="mt-3 text-end" id="containerAcciones">
        <button class="btn btn-success fw-bold rounded-pill d-none px-4" id="btnModificarAsiento">
            <i class="fas fa-edit me-2"></i>MODIFICAR ASIENTO
        </button>
    </div>
</div>

<script>
(function() {
    let currentData = [];
    let asientoSeleccionado = null;
    let pagActual = 1, pagTotal = 1, pagTam = 50;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%', theme: 'default' });
        await cargarEjercicios();
        
        $('.filter-trigger').on('change input', function() {
            if ($(this).attr('id') === 'cmbVista') {
                const isAgr = $(this).val() === 'AGRUPADA';
                ['pagTopWrap','pagBotWrap','containerAcciones'].forEach(id => document.getElementById(id).classList.toggle('d-none', isAgr));
            }
            pagActual = 1; window.pagActual = 1;
            cargarDatos();
        });

        $('#btnActualizar').on('click', () => { pagActual = 1; window.pagActual = 1; cargarDatos(); });
        $('#btnExportar').on('click', exportarExcel);
        $('#btnModificarAsiento').click(() => {
            if (!asientoSeleccionado) return Swal.fire('Atención', 'Seleccione un asiento', 'info');
            const ej = $('#cmbEjercicio').val();
            if (!ej) return Swal.fire('Atención', 'Seleccione un ejercicio', 'info');
            loadModule('contabilidad_modificar_asiento.php?asiento=' + asientoSeleccionado + '&ejercicio=' + ej);
        });
    }

    async function cargarEjercicios() {
        try {
            const resp = await fetch('api_helpers.php?action=get_ejercicios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success && result.data) {
                const $cmb = $('#cmbEjercicio');
                $cmb.empty().append(new Option('SELECCIONE...', ''));
                result.data.forEach(ej => $cmb.append(new Option(ej.descripcion, ej.id)));
                $cmb.trigger('change');
            }
        } catch (e) { console.error(e); }
    }

    async function cargarDatos() {
        const ej = $('#cmbEjercicio').val();
        if (!ej) return;

        const vista = $('#cmbVista').val();
        const payload = {
            vista: vista, ejercicio: ej, desde: $('#txtDesde').val(), hasta: $('#txtHasta').val(),
            fiscal: $('input[name="optFiscal"]:checked').val(),
            manual: $('input[name="optManual"]:checked').val()
        };

        const $tbody = $('#tbodyDiario');
        const $cards = $('#tblCards');
        $tbody.html('<tr><td colspan="4" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>');
        $cards.html('<div class="text-center p-5"><div class="spinner-border text-info"></div></div>');

        try {
            const resp = await fetch('api_reporte_contable_diario.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);

            currentData = result.data || [];
            asientoSeleccionado = null;
            $('#btnModificarAsiento').addClass('d-none');

            if (vista === 'DETALLADA') {
                renderPagina();
            } else {
                renderAgrupada(currentData);
            }
        } catch (e) {
            $tbody.html(`<tr><td colspan="4" class="text-danger text-center py-4">Error: ${e.message}</td></tr>`);
            $cards.html(`<div class="text-danger text-center p-4">Error: ${e.message}</div>`);
        }
    }

    function renderPagina() {
        const total = currentData.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = currentData.slice((pagActual - 1) * pagTam, pagActual * pagTam);
        renderTablaDetallada(datos);
        renderTarjetasDetallada(datos);
        sincronizarPaginacion(total);
    }

    function renderTablaDetallada(data) {
        const $tbody = $('#tbodyDiario').empty();
        let numAsientoActual = -1;

        if (data.length === 0) return $tbody.html('<tr><td colspan="4" class="text-center py-5 text-muted">SIN MOVIMIENTOS</td></tr>');

        data.forEach(row => {
            if (row.numasiento != numAsientoActual) {
                numAsientoActual = row.numasiento;
                const trHeader = $(`
                    <tr class="table-active cursor-pointer header-asiento" style="background-color: #0d2b45 !important;">
                        <td class="fw-bold text-white ps-3">Asiento ${row.numasiento}</td>
                        <td class="fw-bold text-white" colspan="3">
                            <div class="d-flex justify-content-between pe-3">
                                <span>${new Date(row.fecha + 'T00:00:00').toLocaleDateString('es-ES')} - ${row.detalle}</span>
                                <span class="text-accent small">Fiscal: ${row.fiscal == 1 ? "SI" : "NO"} | Usu: ${row.usuario}</span>
                            </div>
                        </td>
                    </tr>
                `);
                trHeader.on('click', function() {
                    $('.header-asiento').removeClass('bg-primary'); $(this).addClass('bg-primary');
                    asientoSeleccionado = row.numasiento; $('#btnModificarAsiento').removeClass('d-none');
                });
                $tbody.append(trHeader);
            }
            $tbody.append(`
                <tr>
                    <td class="text-white-50 ps-4">${row.codigo}</td>
                    <td class="text-white">${row.cuenta}</td>
                    <td class="text-end text-success fw-bold">${parseFloat(row.debe) > 0 ? '$ ' + parseFloat(row.debe).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                    <td class="text-end text-danger fw-bold pe-3">${parseFloat(row.haber) > 0 ? '$ ' + parseFloat(row.haber).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                </tr>
            `);
        });
    }

    function renderTarjetasDetallada(data) {
        const $wrap = $('#tblCards').empty();
        let numAsientoActual = -1;
        let cardHtml = '';

        data.forEach(row => {
            if (row.numasiento != numAsientoActual) {
                if (cardHtml) $wrap.append(cardHtml + '</div></div>');
                numAsientoActual = row.numasiento;
                cardHtml = `
                <div class="erp-card mb-3" style="border-left: 4px solid var(--accent);">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">ASIENTO #${row.numasiento}</div>
                        <span class="erp-card-badge ${row.fiscal == 1 ? 'bg-success' : 'bg-secondary'}">${row.fiscal == 1 ? 'FISCAL' : 'INTERNO'}</span>
                    </div>
                    <div class="erp-card-row small text-white-50 mb-2">
                        <span>${new Date(row.fecha + 'T00:00:00').toLocaleDateString('es-ES')} | ${row.detalle}</span>
                    </div>
                    <div class="border-top border-secondary border-opacity-25 pt-2">`;
            }
            cardHtml += `
                <div class="d-flex justify-content-between mb-1 align-items-center">
                    <div style="max-width: 60%;">
                        <small class="d-block text-info">${row.codigo}</small>
                        <span class="small text-white">${row.cuenta}</span>
                    </div>
                    <div class="text-end">
                        ${parseFloat(row.debe) > 0 ? `<span class="text-success fw-bold small">$ ${parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2})} (D)</span>` : ''}
                        ${parseFloat(row.haber) > 0 ? `<span class="text-danger fw-bold small">$ ${parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2})} (H)</span>` : ''}
                    </div>
                </div>`;
        });
        if (cardHtml) $wrap.append(cardHtml + '</div></div>');
    }

    function renderAgrupada(data) {
        const $tbody = $('#tbodyDiario').empty();
        const $cards = $('#tblCards').empty();
        let hay = false;

        for (const grupo in data) {
            hay = true;
            $tbody.append(`<tr class="table-secondary"><td colspan="4" class="fw-bold text-white text-uppercase" style="background-color: #0d2b45 !important; border-left: 4px solid var(--accent);">${grupo}</td></tr>`);
            data[grupo].forEach(row => {
                const tr = `<tr><td class="text-white-50 ps-4">${row.codigo}</td><td class="text-white">${row.cuenta}</td><td class="text-end text-success fw-bold">${parseFloat(row.debe)>0?'$ '+parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2}):'-'}</td><td class="text-end text-danger fw-bold pe-3">${parseFloat(row.haber)>0?'$ '+parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2}):'-'}</td></tr>`;
                $tbody.append(tr);
                $cards.append(`<div class="erp-card mb-2"><div class="erp-card-header"><div class="erp-card-title">${row.cuenta}</div><small class="text-info">${row.codigo}</small></div><div class="erp-card-row"><span class="erp-card-label">Rubro</span><span>${grupo}</span></div><div class="erp-card-row">${parseFloat(row.debe)>0?`<span class="text-success fw-bold">$ ${parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2})} (D)</span>`:`<span class="text-danger fw-bold">$ ${parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2})} (H)</span>`}</div></div>`);
            });
        }
        if (!hay) { $tbody.html('<tr><td colspan="4" class="text-center py-5 text-muted">SIN DATOS</td></tr>'); $cards.html('<div class="text-center p-5">SIN DATOS</div>'); }
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloLibroDiario')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; cargarDatos(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; cargarDatos(); };

    function exportarExcel() {
        const ej = $('#cmbEjercicio').val();
        if (!ej) return Swal.fire('Atención', 'Seleccione el ejercicio', 'warning');
        const p = new URLSearchParams({ ejercicio: ej, desde: $('#txtDesde').val(), hasta: $('#txtHasta').val(), vista: $('#cmbVista').val(), fiscal: $('input[name="optFiscal"]:checked').val(), manual: $('input[name="optManual"]:checked').val() });
        window.open(`exportar_libro_diario.php?${p.toString()}`, '_blank');
    }

    init();
})();
</script>