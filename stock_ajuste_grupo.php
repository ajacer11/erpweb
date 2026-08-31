<?php
/**
 * stock_ajuste_grupo.php - ADMINISTRACIÓN DE AJUSTES
 */
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">AJUSTES DE INVENTARIO POR GRUPO</h4>
            <small class="text-accent opacity-75">Administración y control de conteos cíclicos</small>
        </div>
        <button class="btn btn-info btn-sm fw-bold rounded-pill px-4" onclick="loadModule('stock_ajuste_add.php')">
            <i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">NUEVO AJUSTE GRUPAL</span><span class="d-sm-none">NUEVO</span>
        </button>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-6 col-md-3">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="filtro-desde" class="form-control" value="<?= date('Y-m-d', strtotime('-10 days')) ?>">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="filtro-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-outline-info w-100" id="btn-buscar">
                        <i class="fas fa-search me-2"></i>BUSCAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Paginación superior -->
    <div class="erp-pagination" id="pagTopWrap">
        <div class="erp-pag-info" id="pagInfoTop">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1"><button class="erp-pag-btn" onclick="pagJumpTop()">IR</button></div>
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

    <!-- Tabla desktop -->
    <div class="erp-table-wrap table-responsive">
        <table class="table table-hover align-middle shadow-sm">
            <thead><tr>
                <th>Nº</th>
                <th>FECHA INICIO</th>
                <th>ESTADO</th>
                <th>NOMBRE DEL GRUPO</th>
                <th>INICIADO POR</th>
                <th>FECHA AJUSTE</th>
                <th class="text-center">ACCIONES</th>
            </tr></thead>
            <tbody id="tbody-ajustes">
                <tr><td colspan="7" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Tarjetas mobile -->
    <div class="erp-cards-wrap" id="cards-ajustes">
        <div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</div>
    </div>

    <!-- Paginación inferior -->
    <div class="erp-pagination" id="pagBotWrap">
        <div class="erp-pag-info" id="pagInfo">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1"><button class="erp-pag-btn" onclick="pagJump()">IR</button></div>
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

<script>
(function () {
    var todos = [], pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    function badgeEstado(e) {
        if (e === 'INICIADO')   return 'bg-info text-dark';
        if (e === 'FINALIZADO') return 'bg-success';
        if (e === 'CANCELADO')  return 'bg-danger';
        return 'bg-secondary';
    }

    async function buscar() {
        const desde = $('#filtro-desde').val(), hasta = $('#filtro-hasta').val();
        document.getElementById('tbody-ajustes').innerHTML = '<tr><td colspan="7" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</td></tr>';
        document.getElementById('cards-ajustes').innerHTML = '<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</div>';
        try {
            const res = await fetch(`api_stock_grupo.php?action=list&desde=${desde}&hasta=${hasta}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
            todos = res.success ? res.data : [];
        } catch (e) { todos = []; }
        pagActual = 1; window.pagActual = 1; renderPagina();
    }

    function renderPagina() {
        const total = todos.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;
        const datos = todos.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        // Tabla
        const tbody = document.getElementById('tbody-ajustes');
        tbody.innerHTML = '';
        if (!datos.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-muted">SIN RESULTADOS</td></tr>'; }
        else datos.forEach(a => {
            tbody.innerHTML += `<tr>
                <td class="fw-bold text-white">${a.id}</td>
                <td>${a.fechacarga}</td>
                <td><span class="badge ${badgeEstado(a.Estado)}">${a.Estado}</span></td>
                <td>${a.nombregrupo || 'SIN NOMBRE'}</td>
                <td>${a.usuariocarga}</td>
                <td>${a.fechaajuste || '-'}</td>
                <td class="text-center"><button class="btn btn-sm btn-info" onclick="loadModule('stock_ajuste_add.php?id=${a.id}')"><i class="fas fa-edit"></i></button></td>
            </tr>`;
        });

        // Tarjetas
        const wrap = document.getElementById('cards-ajustes');
        wrap.innerHTML = '';
        if (!datos.length) { wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>'; }
        else datos.forEach(a => {
            wrap.innerHTML += `<div class="erp-card">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title">${a.nombregrupo || 'SIN NOMBRE'}</div>
                        <span class="badge ${badgeEstado(a.Estado)} mt-1">${a.Estado}</span>
                    </div>
                    <span class="erp-card-code">#${a.id}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha Inicio</span><span class="erp-card-value">${a.fechacarga}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Iniciado Por</span><span class="erp-card-value">${a.usuariocarga}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha Ajuste</span><span class="erp-card-value">${a.fechaajuste || '-'}</span></div>
                <div class="erp-card-actions">
                    <button class="btn btn-info btn-sm fw-bold" onclick="loadModule('stock_ajuste_add.php?id=${a.id}')"><i class="fas fa-edit me-1"></i>VER / EDITAR</button>
                </div>
            </div>`;
        });

        sincronizarPaginacion(total);
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ajustes`;
        const vis = pagTotal > 1 ? 'flex' : 'none';
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ cont.innerHTML=''; if(pagTotal>1){ var ini=Math.max(1,pagActual-2),fin=Math.min(pagTotal,ini+4); if(fin-ini<4)ini=Math.max(1,fin-4); for(var i=ini;i<=fin;i++){var b=document.createElement('button');b.className='erp-pag-btn'+(i===pagActual?' active':'');b.textContent=i;b.onclick=(function(p){return function(){pagGo(p);};})(i);cont.appendChild(b);} } }
            var ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            var sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); if(wrap) wrap.style.display=vis;
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    $('#btn-buscar').click(buscar);
    buscar();
})();
</script>
