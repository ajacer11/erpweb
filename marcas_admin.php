<?php
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">ADMINISTRACIÓN DE MARCAS</h4>
            <small class="text-accent opacity-75">FABRICANTES Y MARCAS</small>
        </div>
        <button onclick="loadModule('marcas_add.php')" class="btn btn-info btn-sm px-4 fw-bold rounded-pill">
            <i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">NUEVA MARCA</span><span class="d-sm-none">NUEVA</span>
        </button>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="fas fa-search"></i></span>
                <input type="text" id="buscarMarca" class="form-control" placeholder="BUSCAR MARCA..." onkeyup="filtrarMarcas()">
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
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3" style="width:50px;">ID</th>
                <th>MARCA</th>
                <th class="text-center" style="width:150px;">ESTADO</th>
                <th class="text-center" style="width:100px;">ACCIONES</th>
            </tr></thead>
            <tbody id="listaMarcasBody"></tbody>
        </table>
    </div>

    <!-- Tarjetas mobile -->
    <div class="erp-cards-wrap" id="listaMarcasCards"></div>

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
    var todos = [], pagActual = 1, pagTotal = 1, pagTam = 20, timer;
    window.pagActual = 1; window.pagTotal = 1;

    window.filtrarMarcas = function () { clearTimeout(timer); timer = setTimeout(listarMarcas, 300); };

    async function listarMarcas() {
        const q = document.getElementById('buscarMarca').value;
        document.getElementById('listaMarcasBody').innerHTML = '<tr><td colspan="4" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</td></tr>';
        try {
            const res = await fetch(`api_marcas.php?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
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
        const tbody = document.getElementById('listaMarcasBody');
        tbody.innerHTML = '';
        if (!datos.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center p-4 text-muted">SIN RESULTADOS</td></tr>'; }
        else datos.forEach(m => {
            const habBadge = m.habilitada == 1 ? '<span class="badge bg-success">HABILITADA</span>' : '<span class="badge bg-danger">INHABILITADA</span>';
            tbody.innerHTML += `<tr style="${m.habilitada==0?'opacity:0.5':''}">
                <td class="ps-3 fw-bold text-muted">${m.id}</td>
                <td class="fw-bold text-white">${m.marca}</td>
                <td class="text-center">${habBadge}</td>
                <td class="text-center"><button class="btn btn-link btn-sm text-info p-0" onclick="loadModule('marcas_add.php?id=${m.id}')"><i class="fas fa-pencil-alt"></i></button></td>
            </tr>`;
        });

        // Tarjetas
        const wrap = document.getElementById('listaMarcasCards');
        wrap.innerHTML = '';
        if (!datos.length) { wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>'; }
        else datos.forEach(m => {
            const habilitada = m.habilitada == 1;
            const opacidad = habilitada ? '' : 'opacity:0.5;';
            const habBadge = habilitada
                ? '<span class="erp-card-badge" style="background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;">HABILITADA</span>'
                : '<span class="erp-card-badge" style="background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;">INHABILITADA</span>';
            wrap.innerHTML += `<div class="erp-card" style="${opacidad}">
                <div class="erp-card-header">
                    <div><div class="erp-card-title">${m.marca}</div>${habBadge}</div>
                    <span class="erp-card-code">#${m.id}</span>
                </div>
                <div class="erp-card-actions"><button class="btn btn-info btn-sm fw-bold" onclick="loadModule('marcas_add.php?id=${m.id}')"><i class="fas fa-pencil-alt me-1"></i>EDITAR</button></div>
            </div>`;
        });

        sincronizarPaginacion(total);
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> marcas`;
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

    listarMarcas();
})();
</script>
