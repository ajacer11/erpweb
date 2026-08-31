<?php
/**
 * logs_no_permitidos.php - LOG DE ACCESOS RESTRINGIDOS
 * Auditoría de seguridad y bloqueos del sistema adaptada a SPA.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLogsSeguridad">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-shield-halved text-accent me-2"></i>LOGS DE SEGURIDAD</h4>
            <small class="text-accent opacity-75">ACCESOS RESTRINGIDOS Y BLOQUEOS</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnBuscar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA DESDE</label>
                    <input type="date" id="txtFechaDesde" class="form-control trigger-filter" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA HASTA</label>
                    <input type="date" id="txtFechaHasta" class="form-control trigger-filter" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label-custom">BÚSQUEDA RÁPIDA (USUARIO / URI / MOTIVO)</label>
                    <input type="text" id="txtBusquedaLocal" class="form-control shadow-none" placeholder="FILTRAR RESULTADOS EN PANTALLA...">
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblLogs" style="font-size: 0.8rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>FECHA / HORA</th>
                    <th>USUARIO</th>
                    <th>URI SOLICITADA / ACCIÓN</th>
                    <th>RAZÓN BLOQUEO</th>
                    <th class="pe-3">IP ORIGEN</th>
                </tr>
            </thead>
            <tbody id="tbodyLogs">
                <tr><td colspan="6" class="text-center py-5 text-muted fst-italic">INGRESE EL RANGO DE FECHAS Y PRESIONE BUSCAR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">INGRESE EL RANGO DE FECHAS Y PRESIONE BUSCAR</div>
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
        </div>
    </div>
</div>

<script>
(function() {
    let rawData = [];
    let currentData = [];
    let debounceTimer = null;
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        document.getElementById('btnBuscar').addEventListener('click', cargarLogsServidor);
        document.getElementById('btnExportar').addEventListener('click', exportarExcel);

        $('.trigger-filter').on('change', cargarLogsServidor);

        $('#txtBusquedaLocal').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => aplicarFiltroLocal(), 400);
        });

        cargarLogsServidor();
    }

    async function cargarLogsServidor() {
        const desde = document.getElementById('txtFechaDesde').value;
        const hasta = document.getElementById('txtFechaHasta').value;
        const btn = document.getElementById('btnBuscar');
        const tbody = document.getElementById('tbodyLogs');
        const cards = document.getElementById('tblCards');

        try {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_logs_no_permitidos.php?action=list&fecha_desde=${desde}&fecha_hasta=${hasta}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();

            if (res.data != []) {
                rawData = res.data || [];
                aplicarFiltroLocal();
            } else {
                throw new Error(res.error || 'No hay datos disponibles para ese rango de fechas.');
            }

        } catch (error) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-danger">ERROR: ${error.message}</td></tr>`;
            cards.innerHTML = `<div class="text-center p-4 text-danger">ERROR: ${error.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR';
        }
    }

    function aplicarFiltroLocal() {
        const texto = document.getElementById('txtBusquedaLocal').value.toUpperCase();
        
        if (!texto) {
            currentData = [...rawData];
        } else {
            currentData = rawData.filter(r => 
                (r.usuario_nombre || '').toUpperCase().includes(texto) || 
                (r.request_uri || '').toUpperCase().includes(texto) ||
                (r.datos_adicionales || '').toUpperCase().includes(texto) ||
                (r.remote_addr || '').includes(texto)
            );
        }

        pagActual = 1;
        renderPagina();
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
        const tbody = document.getElementById('tbodyLogs');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">NO SE ENCONTRARON REGISTROS</td></tr>';
            return;
        }

        data.forEach(log => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3 text-white small">${log.id}</td>
                <td class="text-white small text-nowrap">${log.fecha}</td>
                <td class="fw-bold text-accent text-nowrap">
                    ${log.usuario_nombre || 'DESCONOCIDO'} 
                    <span class="text-muted small ms-1">(ID: ${log.usuario_id})</span>
                </td>
                <td class="text-truncate small" style="max-width: 300px;" title="${log.request_uri}">${log.request_uri}</td>
                <td class="text-danger fw-bold small text-nowrap">${log.datos_adicionales || '-'}</td>
                <td class="pe-3 text-info small fw-bold">${log.remote_addr || '-'}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (data.length === 0) {
            wrap.innerHTML = '<div class="text-center p-5 opacity-50 text-muted">NO SE ENCONTRARON REGISTROS</div>';
            return;
        }

        data.forEach(log => {
            wrap.innerHTML += `
            <div class="erp-card mb-2" style="border-left: 4px solid #dc3545;">
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${(log.usuario_nombre || 'DESCONOCIDO').toUpperCase()}</div>
                    <span class="erp-card-code text-info">#${log.id}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha / Hora</span>
                    <span class="erp-card-value text-white small">${log.fecha}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">URI Solicitada</span>
                    <span class="erp-card-value small text-white-50 text-break">${log.request_uri}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Razón</span>
                    <span class="erp-card-value text-danger fw-bold">${log.datos_adicionales || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">IP Remota</span>
                    <span class="erp-card-value text-info small fw-bold">${log.remote_addr || '-'}</span>
                </div>
                <div class="mt-2 pt-1 border-top border-secondary border-opacity-10">
                    <small class="text-muted d-block" style="font-size: 9px; line-height: 1;">UA: ${log.user_agent}</small>
                </div>
            </div>`;
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info  = `<strong>${desde}–${hasta}</strong> de <strong>${total}</strong>`;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloLogsSeguridad')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    async function exportarExcel() {
        const desde = document.getElementById('txtFechaDesde').value;
        const hasta = document.getElementById('txtFechaHasta').value;
        const btn = document.getElementById('btnExportar');
        const originalContent = btn.innerHTML;

        try {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> GENERANDO...';

            const resp = await fetch(`api_logs_no_permitidos.php?action=export&fecha_desde=${desde}&fecha_hasta=${hasta}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();

            if (res.file_content) {
                const link = document.createElement('a');
                link.href = 'data:application/vnd.ms-excel;base64,' + res.file_content;
                link.download = res.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                throw new Error('No se pudo generar el archivo.');
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    init();
})();
</script>