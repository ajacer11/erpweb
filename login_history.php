<?php
/**
 * login_history.php - AUDITORÍA DE LOGINS
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLoginHistory">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-shield-alt text-accent me-2"></i>AUDITORÍA DE LOGINS</h4>
            <small class="text-accent opacity-75">HISTORIAL DE ACCESOS AL SISTEMA</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportarCSV">
                <i class="fas fa-file-csv me-1"></i> <span class="d-none d-sm-inline">EXPORTAR CSV</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnBuscar">
                <i class="fas fa-search me-1"></i> BUSCAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">USUARIO</label>
                    <select class="form-select select2-filter trigger-filter" id="fil_usuario">
                        <option value="">— TODOS —</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">DESDE FECHA</label>
                    <input type="date" class="form-control trigger-filter" id="fil_desde" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA FECHA</label>
                    <input type="date" class="form-control trigger-filter" id="fil_hasta" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label-custom">BUSCAR POR IP, EQUIPO O HOST</label>
                    <input type="text" class="form-control shadow-none" id="fil_texto" placeholder="EJ: 192.168... O DESKTOP-...">
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
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblAuditoria" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 70px;">ID</th>
                    <th style="width: 150px;">USUARIO</th>
                    <th class="text-center" style="width: 100px;">FECHA</th>
                    <th class="text-center" style="width: 80px;">HORA</th>
                    <th>IP LOCAL</th>
                    <th>IP PÚBLICA / PROVEEDOR</th>
                    <th>DISPOSITIVO / SISTEMA</th>
                    <th class="pe-3">HOST</th>
                </tr>
            </thead>
            <tbody id="tbodyAuditoria">
                <tr><td colspan="8" class="text-center py-5 text-muted fst-italic">INDIQUE LOS FILTROS Y PRESIONE BUSCAR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">INDIQUE LOS FILTROS Y PRESIONE BUSCAR</div>
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
        $('.select2-filter').select2({ theme: 'default', width: '100%', dropdownParent: $('#main-content') });

        // Cargar lista de usuarios
        try {
            const rUsu = await fetch('api_auditoria_login.php?action=get_usuarios', { headers: {'X-Requested-With':'XMLHttpRequest'} });
            const dUsu = await rUsu.json();
            if(dUsu.success) {
                const sel = document.getElementById('fil_usuario');
                dUsu.data.forEach(u => sel.add(new Option(u.toUpperCase(), u)));
            }
        } catch(e) { console.error("Error cargando usuarios", e); }

        // Listeners
        document.getElementById('btnBuscar').addEventListener('click', () => buscarServidor());
        document.getElementById('btnExportarCSV').addEventListener('click', exportarCSV);

        $('.trigger-filter').on('change', () => buscarServidor());
        
        $('#fil_texto').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => aplicarFiltroLocal(), 400);
        });

        buscarServidor();
    }

    async function buscarServidor() {
        const btn = document.getElementById('btnBuscar');
        const tbody = document.getElementById('tbodyAuditoria');
        const cards = document.getElementById('tblCards');

        const params = new URLSearchParams({
            action: 'get_logins',
            usuario: document.getElementById('fil_usuario').value,
            desde: document.getElementById('fil_desde').value,
            hasta: document.getElementById('fil_hasta').value
        });

        try {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center p-5"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_auditoria_login.php?${params.toString()}`, { headers: {'X-Requested-With':'XMLHttpRequest'} });
            const res = await resp.json();

            if (!res.success) throw new Error(res.error || 'Error en servidor');

            rawData = res.data || [];
            aplicarFiltroLocal();

        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-4 text-danger">ERROR: ${e.message}</td></tr>`;
            cards.innerHTML = `<div class="text-center p-4 text-danger">ERROR: ${e.message}</div>`;
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i>BUSCAR';
        }
    }

    function aplicarFiltroLocal() {
        const texto = document.getElementById('fil_texto').value.toUpperCase();
        
        if (!texto) {
            currentData = [...rawData];
        } else {
            currentData = rawData.filter(r => 
                (r.ip || '').includes(texto) || 
                (r.ippublica || '').includes(texto) || 
                (r.usuariopc || '').toUpperCase().includes(texto) || 
                (r.host || '').toUpperCase().includes(texto)
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
        const tbody = document.getElementById('tbodyAuditoria');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">SIN RESULTADOS</td></tr>';
            return;
        }

        data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3 text-white-50 small">${r.id}</td>
                <td><span class="text-accent fw-bold text-uppercase"><i class="fas fa-user-circle me-2 opacity-50"></i>${r.usuario}</span></td>
                <td class="text-center text-white">${formatDate(r.fecha)}</td>
                <td class="text-center text-white-50">${r.hora || ''}</td>
                <td class="text-info small">${r.ip || '-'}</td>
                <td class="small text-white-50">${r.ippublica || '-'}</td>
                <td class="text-truncate small" style="max-width: 200px;" title="${r.usuariopc}">${r.usuariopc || '-'}</td>
                <td class="pe-3 text-truncate small opacity-75" style="max-width: 150px;">${r.host || '-'}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (data.length === 0) {
            wrap.innerHTML = '<div class="text-center p-5 opacity-50 text-muted">SIN RESULTADOS</div>';
            return;
        }

        data.forEach(r => {
            wrap.innerHTML += `
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent"><i class="fas fa-user me-2 opacity-50"></i>${r.usuario.toUpperCase()}</div>
                    <span class="erp-card-code text-info">#${r.id}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha / Hora</span>
                    <span class="erp-card-value text-white">${formatDate(r.fecha)} ${r.hora || ''}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">IP Pública</span>
                    <span class="erp-card-value text-info small">${r.ippublica || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Equipo</span>
                    <span class="erp-card-value small text-truncate" style="max-width:180px;">${r.usuariopc || '-'}</span>
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloLoginHistory')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatDate(d) {
        if(!d) return '-';
        const parts = d.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function exportarCSV() {
        if (currentData.length === 0) {
            Swal.fire('Atención', 'No hay datos para exportar.', 'warning');
            return;
        }
        const cabecera = ['ID', 'USUARIO', 'FECHA', 'HORA', 'IP LOCAL', 'IP PUBLICA', 'EQUIPO', 'HOST'];
        const filas = currentData.map(r => [r.id, r.usuario, r.fecha, r.hora, r.ip, r.ippublica, r.usuariopc, r.host]
            .map(v => `"${(v ?? '').toString().replace(/"/g, '""')}"`).join(','));

        const csv = '\uFEFF' + [cabecera.join(','), ...filas].join('\r\n');
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `auditoria_login_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    init();
})();
</script>