<?php
/**
 * reporte_stock_ajustes_val.php - INFORME DE AJUSTES VALORIZADOS
 * Detalle línea por línea de ajustes de stock valorizados a costo.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloStockAjustesVal">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-dollar-sign text-accent me-2"></i>AJUSTES VALORIZADOS</h4>
            <small class="text-accent opacity-75">IMPACTO ECONÓMICO DE MOVIMIENTOS DE STOCK</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" onclick="exportarExcelValorizado()">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btn-buscar">
                <i class="fas fa-search me-1"></i> BUSCAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">DESDE FECHA</label>
                    <input type="date" id="filtro-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA FECHA</label>
                    <input type="date" id="filtro-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-8">
                    <label class="form-label-custom">BUSCAR ÍTEM (CÓDIGO O DESCRIPCIÓN)</label>
                    <input type="text" id="filtro-producto" class="form-control" placeholder="EJ: 10200 O TORNILLO...">
                </div>
            </div>
            
            <div class="row align-items-end g-3 border-top border-secondary border-opacity-25 pt-2 mt-2">
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">RUBRO</label>
                    <select class="form-select select2-filter" id="f-rubro"><option value="">TODOS LOS RUBROS</option></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">MARCA</label>
                    <select class="form-select select2-filter" id="f-marca"><option value="">TODAS LAS MARCAS</option></select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">FAMILIA</label>
                    <select class="form-select select2-filter" id="f-familia"><option value="">TODAS LAS FAMILIAS</option></select>
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 50vh; background: #000a1a; overflow-y: auto; position: relative;">
        <div id="loading-table" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100; justify-content: center; align-items: center;">
            <div class="text-center">
                <div class="spinner-border text-info" role="status"></div>
                <div class="text-info mt-2 small">CARGANDO REPORTE...</div>
            </div>
        </div>

        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3">FECHA</th>
                    <th>Nº AJUSTE</th>
                    <th>CÓDIGO</th>
                    <th>DESCRIPCIÓN</th>
                    <th>RUBRO</th>
                    <th>ESTADO</th>
                    <th class="text-end">DIF. FÍSICA</th>
                    <th class="text-end">COSTO U.</th>
                    <th class="text-end pe-3">VALORIZADO</th>
                </tr>
            </thead>
            <tbody id="tbody-reporte">
                <tr><td colspan="9" class="text-center text-muted py-5 fst-italic">HAGA CLIC EN BUSCAR PARA CARGAR LOS DATOS.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">HAGA CLIC EN BUSCAR PARA CARGAR LOS DATOS.</div>
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

    <div id="panelTotales" class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4" style="background-color: #000a1a;">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-12 col-md-4 text-center text-md-start border-end border-secondary border-opacity-25">
                    <small class="text-white-50 d-block">TOTAL POSITIVO (SOBRANTE)</small>
                    <span id="span-pos" class="text-success fw-bold fs-5">$ 0.00</span>
                </div>
                <div class="col-12 col-md-4 text-center border-end border-secondary border-opacity-25">
                    <small class="text-white-50 d-block">TOTAL NEGATIVO (FALTANTE)</small>
                    <span id="span-neg" class="text-danger fw-bold fs-5">$ 0.00</span>
                </div>
                <div class="col-12 col-md-4 text-center text-md-end">
                    <small class="text-accent fw-bold d-block">IMPACTO NETO VALORIZADO</small>
                    <span id="span-neto" class="text-accent fw-bold fs-4">$ 0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentData = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        await cargarCombos();
        
        $('.select2-filter').select2({
            theme: 'default',
            dropdownParent: $('#main-content'),
            width: '100%'
        });

        $('#btn-buscar').click(buscarDatos);
        
        // Disparar búsqueda al cambiar filtros (excepto texto)
        $('#filtro-desde, #filtro-hasta, #f-rubro, #f-marca, #f-familia').on('change', function() {
            const desde = $('#filtro-desde').val();
            const hasta = $('#filtro-hasta').val();
            if ((desde && parseInt(desde.split('-')[0]) < 2000) || (hasta && parseInt(hasta.split('-')[0]) < 2000)) return;
            buscarDatos();
        });

        buscarDatos();
    }

    async function cargarCombos() {
        const endpoints = [
            { id: '#f-rubro', action: 'get_rubros', key: 'rubro', label: 'rubro' },
            { id: '#f-marca', action: 'get_marcas', key: 'id', label: 'marca' },
            { id: '#f-familia', action: 'get_familias', key: 'id', label: 'nombre' }
        ];

        for (const item of endpoints) {
            try {
                const resp = await fetch(`api_helpers.php?action=${item.action}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
                const res = await resp.json();
                if (res.success) {
                    res.data.forEach(d => $(item.id).append(new Option(d[item.label], d[item.key])));
                }
            } catch (e) { console.error(`Error combo ${item.id}`, e); }
        }
    }

    async function buscarDatos() {
        $('#loading-table').css('display', 'flex');
        
        const params = new URLSearchParams({
            action: 'list',
            desde: $('#filtro-desde').val(),
            hasta: $('#filtro-hasta').val(),
            rubro: $('#f-rubro').val(),
            marca: $('#f-marca').val(),
            familia: $('#f-familia').val(),
            producto: $('#filtro-producto').val()
        });
        
        try {
            const resp = await fetch(`api_reporte_stock_ajustes_val.php?${params.toString()}`, { 
                headers: {'X-Requested-With': 'XMLHttpRequest'} 
            });
            const res = await resp.json();
            
            if (res.success) {
                currentData = res.data || [];
                pagActual = 1; window.pagActual = 1;
                renderPagina();
                actualizarTotalesGlobales();
            } else {
                throw new Error(res.error);
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Fallo de conexión.', 'error');
        } finally {
            $('#loading-table').hide();
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
        const tbody = $('#tbody-reporte').empty();

        if (data.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center text-muted py-5">SIN RESULTADOS CON ESTOS FILTROS</td></tr>');
            return;
        }

        data.forEach(d => {
            const difFisica = parseFloat(d.dif_cantidad || 0);
            const costo = parseFloat(d.costounitario || 0);
            const valorizado = parseFloat(d.dif_valorizada || 0);
            const colorCls = difFisica < 0 ? 'text-danger' : (difFisica > 0 ? 'text-success' : 'text-muted');
            const badgeEstado = d.estado === 'FINALIZADO' ? 'bg-success' : (d.estado === 'INICIADO' ? 'bg-info' : 'bg-danger');

            tbody.append(`
                <tr>
                    <td class="ps-3 text-white-50">${d.fecha}</td>
                    <td class="fw-bold text-white text-center">#${d.ajuste_id}</td>
                    <td class="fw-bold text-info">${d.codigo}</td>
                    <td class="text-truncate" style="max-width: 200px;" title="${d.descripcion}">${d.descripcion}</td>
                    <td class="small text-white-50">${d.rubro}</td>
                    <td><span class="badge ${badgeEstado} px-2 py-1 rounded-pill">${d.estado}</span></td>
                    <td class="text-end fw-bold ${colorCls}">${difFisica.toFixed(2)}</td>
                    <td class="text-end text-muted">$ ${costo.toFixed(2)}</td>
                    <td class="text-end fw-bold pe-3 ${colorCls}">$ ${valorizado.toFixed(2)}</td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards').empty();

        if (data.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 fst-italic">SIN RESULTADOS</div>');
            return;
        }

        data.forEach(d => {
            const difFisica = parseFloat(d.dif_cantidad || 0);
            const valorizado = parseFloat(d.dif_valorizada || 0);
            const colorCls = difFisica < 0 ? 'text-danger' : (difFisica > 0 ? 'text-success' : 'text-muted');
            const badgeStyle = d.estado === 'FINALIZADO' ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' : 
                              (d.estado === 'INICIADO' ? 'background:rgba(23,162,184,.2);color:#17a2b8;border:1px solid #17a2b8;' : 
                                                         'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;');

            wrap.append(`
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-accent">${d.descripcion}</div>
                        <small class="text-info">${d.codigo} | Ajuste #${d.ajuste_id}</small>
                    </div>
                    <span class="erp-card-badge" style="${badgeStyle}">${d.estado}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${d.fecha}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Rubro</span><span class="erp-card-value">${d.rubro}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Dif. Física</span><span class="erp-card-value fw-bold ${colorCls}">${difFisica.toFixed(2)}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Costo Unit.</span><span class="erp-card-value">$ ${parseFloat(d.costounitario).toFixed(2)}</span></div>
                <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                    <span class="erp-card-label text-white fw-bold">VALORIZADO</span>
                    <span class="erp-card-value fw-bold fs-6 ${colorCls}">$ ${valorizado.toFixed(2)}</span>
                </div>
            </div>`);
        });
    }

    function actualizarTotalesGlobales() {
        let totPos = 0, totNeg = 0, totNeto = 0;
        currentData.forEach(d => {
            const val = parseFloat(d.dif_valorizada || 0);
            if (val > 0) totPos += val;
            else if (val < 0) totNeg += Math.abs(val);
            totNeto += val;
        });

        $('#span-pos').text('$ ' + totPos.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#span-neg').text('$ ' + totNeg.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#span-neto').text('$ ' + totNeto.toLocaleString('es-AR', {minimumFractionDigits: 2}))
            .removeClass('text-danger text-success text-accent')
            .addClass(totNeto < 0 ? 'text-danger' : (totNeto > 0 ? 'text-success' : 'text-accent'));
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ítems`;

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
                        const b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = (p => () => pagGo(p))(i);
                        cont.appendChild(b);
                    }
                }
            }
            const ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            const sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            const wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloStockAjustesVal')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    window.exportarExcelValorizado = function() {
        if (currentData.length === 0) return Swal.fire('Atención', 'No hay datos para exportar.', 'warning');

        let totPos = 0, totNeg = 0, totNeto = 0;
        let tablaHTML = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8"><style>
                table { border-collapse: collapse; font-family: Arial; }
                th { background-color: #0d2b45; color: #00ffff; font-weight: bold; border: 1px solid #445; padding: 8px; }
                td { border: 1px solid #ccc; padding: 6px; }
                .num { text-align: right; }
                .pos { color: #006600; font-weight: bold; }
                .neg { color: #cc0000; font-weight: bold; }
            </style></head>
            <body>
                <h3>REPORTE DE AJUSTES DE INVENTARIO VALORIZADOS</h3>
                <p>Periodo: ${$('#filtro-desde').val()} al ${$('#filtro-hasta').val()}</p>
                <table>
                    <thead>
                        <tr><th>FECHA</th><th>Nº AJUSTE</th><th>CÓDIGO</th><th>DESCRIPCIÓN</th><th>RUBRO</th><th>ESTADO</th><th>DIF. FÍSICA</th><th>COSTO U.</th><th>VALORIZADO</th></tr>
                    </thead>
                    <tbody>
        `;

        currentData.forEach(d => {
            const val = parseFloat(d.dif_valorizada || 0);
            if (val > 0) totPos += val; else if (val < 0) totNeg += Math.abs(val);
            totNeto += val;

            tablaHTML += `
                <tr>
                    <td>${d.fecha}</td>
                    <td style="text-align:center">${d.ajuste_id}</td>
                    <td>${d.codigo}</td>
                    <td>${d.descripcion}</td>
                    <td>${d.rubro}</td>
                    <td style="text-align:center">${d.estado}</td>
                    <td class="num">${parseFloat(d.dif_cantidad).toFixed(2)}</td>
                    <td class="num">${parseFloat(d.costounitario).toFixed(2)}</td>
                    <td class="num ${val < 0 ? 'neg' : 'pos'}">${val.toFixed(2)}</td>
                </tr>`;
        });

        tablaHTML += `
                    </tbody>
                    <tfoot>
                        <tr style="background:#f2f2f2; font-weight:bold;">
                            <td colspan="8" style="text-align:right">TOTAL POSITIVO (SOBRANTE):</td>
                            <td class="num pos">${totPos.toFixed(2)}</td>
                        </tr>
                        <tr style="background:#f2f2f2; font-weight:bold;">
                            <td colspan="8" style="text-align:right">TOTAL NEGATIVO (FALTANTE):</td>
                            <td class="num neg">-${totNeg.toFixed(2)}</td>
                        </tr>
                        <tr style="background:#ddd; font-weight:bold;">
                            <td colspan="8" style="text-align:right">IMPACTO NETO VALORIZADO:</td>
                            <td class="num ${totNeto < 0 ? 'neg' : 'pos'}">${totNeto.toFixed(2)}</td>
                        </tr>
                    </tfoot>
                </table>
            </body></html>
        `;

        const blob = new Blob(['\ufeff', tablaHTML], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Ajustes_Valorizados_${new Date().getTime()}.xls`;
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
    };

    init();
})();
</script>