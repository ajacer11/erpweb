<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">ADMINISTRACIÓN DE CLIENTES</h4>
            <small class="text-accent opacity-75">GESTIÓN DE PADRÓN Y CUENTAS</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="exportarExcel()" class="btn btn-outline-success btn-sm px-3 rounded-pill fw-bold">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button onclick="listarClientes()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill fw-bold">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button onclick="loadModule('clientes_alta.php')" class="btn btn-info btn-sm px-4 fw-bold rounded-pill">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">NUEVO CLIENTE</span><span class="d-sm-none">NUEVO</span>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-secondary">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="buscarCliente" class="form-control"
                               placeholder="BUSCAR POR RAZÓN SOCIAL O CUIT..." onkeyup="filtrarClientes()">
                    </div>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="verInhabilitados" onchange="listarClientes()">
                        <label class="form-check-label text-muted small" for="verInhabilitados">VER INHABILITADOS</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionCliTop">
        <div class="erp-pag-info" id="pagInfoTop">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInputTop" min="1" value="1" title="Ir a página">
                <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)" title="Primera">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual - 1)" title="Anterior">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual + 1)" title="Siguiente">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)" title="Última">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
        </div>
    </div>

    <!-- ============================
         TABLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-3">ID</th>
                    <th>RAZÓN SOCIAL</th>
                    <th>DOCUMENTO</th>
                    <th>CONDICIÓN FISCAL</th>
                    <th>LOCALIDAD</th>
                    <th>TELÉFONO</th>
                    <th class="text-center">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="listaClientesBody">
                <tr><td colspan="7" class="text-center p-4">
                    <div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...
                </td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="listaClientesCards">
        <div class="text-center text-muted p-4">
            <div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionCli">
        <div class="erp-pag-info" id="pagInfo">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInput" min="1" value="1" title="Ir a página">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)" title="Primera">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual - 1)" title="Anterior">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual + 1)" title="Siguiente">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)" title="Última">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
        </div>
    </div>
</div>

<script>
(function () {
    /* ============================================================
       ESTADO
    ============================================================ */
    var pagActual     = 1;
    var pagTotal      = 1;
    var pagTam        = 20;
    var todosLosDatos = [];
    const ordenTabla = erpOrdenarTabla('#listaClientesBody', () => todosLosDatos, renderPagina,
        [{campo:'id', tipo:'numero'}, 'razon', 'numdoc', 'condicionfiscal', 'localidad', 'telefono', null]);
    var timerBusqueda;

    window.pagActual = 1;
    window.pagTotal  = 1;

    /* ============================================================
       FETCH
    ============================================================ */
    window.listarClientes = async function () {
        const q            = document.getElementById('buscarCliente').value;
        const inhabilitados = document.getElementById('verInhabilitados').checked;

        document.getElementById('listaClientesBody').innerHTML =
            '<tr><td colspan="7" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</td></tr>';
        document.getElementById('listaClientesCards').innerHTML =
            '<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</div>';

        try {
            const resp   = await fetch(`api_clientes.php?q=${encodeURIComponent(q)}&inhabilitados=${inhabilitados}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();
            todosLosDatos = result.success ? result.data : [];
        } catch (e) {
            todosLosDatos = [];
        }

        pagActual        = 1;
        ordenTabla.aplicar();
        window.pagActual = 1;
        renderPagina();
    };

    window.filtrarClientes = function () {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => listarClientes(), 300);
    };

    /* ============================================================
       RENDER PÁGINA
    ============================================================ */
    function renderPagina() {
        const total  = todosLosDatos.length;
        pagTotal     = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual;
        window.pagTotal  = pagTotal;

        const datos = todosLosDatos.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos, total);
        renderTarjetas(datos, total);
        actualizarPaginacion(total);
    }

    /* ---- TABLA ---- */
    function renderTabla(datos, total) {
        const tbody = document.getElementById('listaClientesBody');
        tbody.innerHTML = '';

        if (datos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center p-4 text-muted">SIN RESULTADOS</td></tr>';
            return;
        }

        datos.forEach(c => {
            const opacidad  = c.habilitado == 0 ? 'opacity:0.5;' : '';
            const iconInh   = c.habilitado == 0 ? '<i class="fas fa-ban text-danger ms-2" title="Inhabilitado"></i>' : '';
            const tr        = document.createElement('tr');
            tr.style        = opacidad;
            tr.innerHTML    = `
                <td class="ps-3 fw-bold text-white">${c.id}</td>
                <td>
                    <div class="fw-bold text-white">${c.razon} ${iconInh}</div>
                    <small class="text-muted" style="font-size:11px;">${c.mail || ''}</small>
                </td>
                <td>${c.numdoc || '-'}</td>
                <td><span class="badge badge-status bg-dark border border-secondary text-light">${c.condicionfiscal || 'S/D'}</span></td>
                <td>${c.localidad || '-'}</td>
                <td>${c.telefono || '-'}</td>
                <td class="text-center">
                    <button class="btn btn-link btn-sm text-info p-0 mx-2" onclick="loadModule('clientes_alta.php?id=${c.id}')" title="Editar">
                        <i class="fas fa-pencil-alt"></i>
                    </button>
                    
                </td>`;
            tbody.appendChild(tr);
        });
    }

    /* ---- TARJETAS ---- */
    function renderTarjetas(datos, total) {
        const wrap  = document.getElementById('listaClientesCards');
        wrap.innerHTML = '';

        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>';
            return;
        }

        datos.forEach(c => {
            const opacidad = c.habilitado == 0 ? 'opacity:0.5;' : '';
            const inhBadge = c.habilitado == 0
                ? '<span class="erp-card-badge" style="background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;">INHABILITADO</span>'
                : '';

            wrap.innerHTML += `
            <div class="erp-card" style="${opacidad}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title">${c.razon}</div>
                        ${inhBadge}
                    </div>
                    <span class="erp-card-code">#${c.id}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">CUIT / Doc.</span>
                    <span class="erp-card-value">${c.numdoc || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Condición</span>
                    <span class="erp-card-value">${c.condicionfiscal || 'S/D'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Localidad</span>
                    <span class="erp-card-value">${c.localidad || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Teléfono</span>
                    <span class="erp-card-value">${c.telefono || '-'}</span>
                </div>
                ${c.mail ? `<div class="erp-card-row">
                    <span class="erp-card-label">Mail</span>
                    <span class="erp-card-value" style="font-size:11px;">${c.mail}</span>
                </div>` : ''}

                <div class="erp-card-actions">
                    <button class="btn btn-info btn-sm fw-bold" onclick="loadModule('clientes_alta.php?id=${c.id}')">
                        <i class="fas fa-pencil-alt me-1"></i> EDITAR
                    </button>
                    
                </div>
            </div>`;
        });
    }

    /* ============================================================
       PAGINACIÓN (sincroniza top e inferior)
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde   = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta   = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> clientes`;
        const visible  = pagTotal > 1 ? 'flex' : 'none';

        ['', 'Top'].forEach(function (sfx) {
            var el = document.getElementById('pagInfo' + sfx);
            if (el) el.innerHTML = infoHtml;

            var dis = function (id, val) { var e = document.getElementById(id + sfx); if (e) e.disabled = val; };
            dis('btnPagPrim', pagActual <= 1);
            dis('btnPagAnt',  pagActual <= 1);
            dis('btnPagSig',  pagActual >= pagTotal);
            dis('btnPagUlt',  pagActual >= pagTotal);

            var cont = document.getElementById('pagBotones' + sfx);
            if (cont) {
                cont.innerHTML = '';
                if (pagTotal > 1) {
                    var ini = Math.max(1, pagActual - 2);
                    var fin = Math.min(pagTotal, ini + 4);
                    if (fin - ini < 4) ini = Math.max(1, fin - 4);
                    for (var i = ini; i <= fin; i++) {
                        var btn     = document.createElement('button');
                        btn.className = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                        btn.textContent = i;
                        btn.onclick = (function (p) { return function () { pagGo(p); }; })(i);
                        cont.appendChild(btn);
                    }
                }
            }

            var jump = document.getElementById('pagJumpInput' + sfx);
            if (jump) { jump.value = pagActual; jump.max = pagTotal; }

            var sel = document.getElementById('pagTamano' + sfx);
            if (sel) sel.value = pagTam;

            var wrap = document.getElementById(sfx === 'Top' ? 'paginacionCliTop' : 'paginacionCli');
            if (wrap) wrap.style.display = visible;
        });
    }

    window.pagGo = function (n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual        = n;
        window.pagActual = n;
        renderPagina();
        document.getElementById('main-content')?.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.pagJump = function () {
        const v = parseInt(document.getElementById('pagJumpInput').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagJumpTop = function () {
        const v = parseInt(document.getElementById('pagJumpInputTop').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagCambiarTamano = function () {
        pagTam           = parseInt(document.getElementById('pagTamano').value);
        pagActual        = 1;
        window.pagActual = 1;
        renderPagina();
    };

    window.pagCambiarTamanoTop = function () {
        pagTam           = parseInt(document.getElementById('pagTamanoTop').value);
        pagActual        = 1;
        window.pagActual = 1;
        renderPagina();
    };

    /* ============================================================
       EXPORTAR
    ============================================================ */
    window.exportarExcel = function () {
        const q            = document.getElementById('buscarCliente').value;
        const inhabilitados = document.getElementById('verInhabilitados').checked;
        window.open(`exportar_clientes_excel.php?q=${encodeURIComponent(q)}&inhabilitados=${inhabilitados}`, '_blank');
    };

    /* ---- ARRANQUE ---- */
    listarClientes();

})();
</script>
