<?php
/**
 * proveedores_admin.php - GESTIÓN CENTRAL DE PADRÓN DE PROVEEDORES
 * Actualizado: Paginación en memoria (SPA) y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">ADMINISTRACIÓN DE PROVEEDORES</h4>
            <small class="text-accent opacity-75">GESTIÓN DE PADRÓN Y CUENTAS CORRIENTES</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 btn-sm" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-outline-info border-0 btn-sm me-2" onclick="imprimirListado()">
                <i class="fas fa-print me-1"></i> <span class="d-none d-sm-inline">IMPRIMIR</span>
            </button>
            <button onclick="loadModule('proveedores_add.php')" class="btn btn-info btn-sm px-4 fw-bold rounded-pill">
                <i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline"> NUEVO PROVEEDOR</span><span class="d-inline d-sm-none"> NUEVO</span>
            </button>
        </div>
    </div>

    <!-- Filtros y Búsqueda -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-7">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-secondary">
                            <i class="fas fa-search"></i>
                        </span>
                        <input type="text" id="buscarProv" class="form-control border-start-0" placeholder="BUSCAR POR NOMBRE, CUIT, ID O DOMICILIO..." oninput="filtrarProveedores()">
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="verInhabilitados" onchange="listarProveedores()">
                        <label class="form-check-label text-muted small" for="verInhabilitados">VER INHABILITADOS</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionProvTop" style="display: none;">
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
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 60vh; min-height: 300px;">
            <table class="table table-hover align-middle mb-0" id="tblProveedores">
                <thead class="sticky-top">
                    <tr>
                        <th class="ps-3" style="width: 60px;">ID</th>
                        <th>RAZÓN SOCIAL / CONTACTO</th>
                        <th>CUIT / DOC</th>
                        <th>CONDICIÓN</th>
                        <th>LOCALIDAD</th>
                        <th>TELÉFONO</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbody-proveedores">
                    <tr>
                        <td colspan="7" class="text-center py-5">
                            <div class="spinner-border text-info" role="status"></div>
                            <p class="mt-2 text-muted">CARGANDO PADRÓN...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="listaProveedoresCards">
        <div class="text-center text-muted p-4">
            <div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionProv" style="display: none;">
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
(function() {
    /* ============================================================
       ESTADO & PAGINACIÓN
    ============================================================ */
    var pagActual = 1;
    var pagTotal  = 1;
    var pagTam    = 20;
    var listado   = [];
    let timerBusqueda;
    const commonHeaders = {'X-Requested-With': 'XMLHttpRequest'};

    window.pagActual = 1;
    window.pagTotal  = 1;

    /* ============================================================
       FETCH & RENDERIZADO SPA
    ============================================================ */
    const ordenTabla = erpOrdenarTabla('#tblProveedores', () => listado, renderPagina,
        [{campo:'Id', tipo:'numero'}, 'Razon', 'cuit', 'condicion', 'localidad', 'telefono', null]);

    async function listarProveedores() {
        const query = document.getElementById('buscarProv').value;
        const inhabilitados = document.getElementById('verInhabilitados').checked;
        
        document.getElementById('tbody-proveedores').innerHTML = 
            '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO PADRÓN...</td></tr>';
        document.getElementById('listaProveedoresCards').innerHTML = 
            '<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</div>';

        try {
            const resp = await fetch(`api_proveedores_admin.php?action=list&q=${encodeURIComponent(query)}&inhabilitados=${inhabilitados}`, {
                headers: commonHeaders
            });
            const data = await resp.json();
            listado = Array.isArray(data) ? data : [];
        } catch (e) {
            console.error("Error al listar:", e);
            listado = [];
        }

        ordenTabla.aplicar();
        pagActual = 1;
        window.pagActual = 1;
        renderPagina();
    }

    function renderPagina() {
        const total = listado.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual;
        window.pagTotal = pagTotal;

        const datos = listado.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        actualizarPaginacion(total);
    }

    function renderTabla(datos) {
        const tbody = document.getElementById('tbody-proveedores');
        tbody.innerHTML = '';

        if (datos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 opacity-50 text-muted">No se encontraron proveedores con los filtros aplicados.</td></tr>';
            return;
        }

        datos.forEach(p => {
            const tr = document.createElement('tr');
            const rowClass = p.habilitado == 0 ? 'opacity-50 italic' : '';
            const iconStatus = p.habilitado == 0 ? '<i class="fas fa-ban text-danger ms-1" title="Inhabilitado"></i>' : '';
            
            tr.className = rowClass;
            tr.innerHTML = `
                <td class="ps-3 fw-bold text-white">${p.Id}</td>
                <td>
                    <div class="fw-bold text-white text-truncate" style="max-width: 200px;">${p.Razon} ${iconStatus}</div>
                    <small class="text-muted" style="font-size: 11px;">${p.mail || ''}</small>
                </td>
                <td>${p.cuit || '-'}</td>
                <td><span class="badge badge-status bg-dark border border-secondary text-light">${p.condicion || 'S/D'}</span></td>
                <td>${p.localidad || '-'}</td>
                <td>${p.telefono || '-'}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-link btn-sm text-info p-0 mx-2" onclick="loadModule('proveedores_add.php?id=${p.Id}')" title="Editar">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-link btn-sm text-warning p-0 mx-2" onclick="loadModule('proveedores_cuentas_corrientes.php?id=${p.Id}')" title="Cuenta Corriente">
                            <i class="fas fa-wallet"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('listaProveedoresCards');
        wrap.innerHTML = '';

        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>';
            return;
        }

        datos.forEach(p => {
            const isInhabilitado = p.habilitado == 0;
            const opacidad = isInhabilitado ? 'opacity:0.6;' : '';
            const inhBadge = isInhabilitado 
                ? '<span class="erp-card-badge ms-2" style="background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;">INHABILITADO</span>' 
                : '';

            wrap.innerHTML += `
            <div class="erp-card" style="${opacidad}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title d-inline-block text-truncate" style="max-width: 200px;">${p.Razon}</div>
                        ${inhBadge}
                    </div>
                    <span class="erp-card-code">#${p.Id}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">CUIT / Doc</span>
                    <span class="erp-card-value">${p.cuit || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Condición</span>
                    <span class="erp-card-value">${p.condicion || 'S/D'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Localidad</span>
                    <span class="erp-card-value">${p.localidad || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Teléfono</span>
                    <span class="erp-card-value">${p.telefono || '-'}</span>
                </div>
                ${p.mail ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">Email</span>
                    <span class="erp-card-value" style="font-size:11px;">${p.mail}</span>
                </div>` : ''}

                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-info btn-sm fw-bold flex-fill" onclick="loadModule('proveedores_add.php?id=${p.Id}')">
                        <i class="fas fa-pencil-alt me-1"></i> EDITAR
                    </button>
                    <button class="btn btn-warning btn-sm fw-bold flex-fill" onclick="loadModule('proveedores_cuentas_corrientes.php?id=${p.Id}')">
                        <i class="fas fa-wallet me-1"></i> CUENTA CTE
                    </button>
                </div>
            </div>`;
        });
    }

    /* ============================================================
       CONTROLES DE PAGINACIÓN (SINCRONIZADOS)
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> proveedores`;
        const visible = pagTotal > 1 ? 'flex' : 'none';

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
                        var btn = document.createElement('button');
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

            var wrap = document.getElementById(sfx === 'Top' ? 'paginacionProvTop' : 'paginacionProv');
            if (wrap) wrap.style.display = visible;
        });
    }

    window.pagGo = function (n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual = n;
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
        pagTam = parseInt(document.getElementById('pagTamano').value);
        pagActual = 1; window.pagActual = 1;
        renderPagina();
    };

    window.pagCambiarTamanoTop = function () {
        pagTam = parseInt(document.getElementById('pagTamanoTop').value);
        pagActual = 1; window.pagActual = 1;
        renderPagina();
    };

    /* ============================================================
       EVENTOS GLOBALES (BÚSQUEDA Y EXPORTACIÓN)
    ============================================================ */
    window.filtrarProveedores = function() {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => { listarProveedores(); }, 300);
    };

    window.listarProveedores = listarProveedores; // Exponer para el checkbox onchange

    window.exportarExcel = function() {
        const query = document.getElementById('buscarProv').value;
        const inhabilitados = document.getElementById('verInhabilitados').checked;
        window.location.href = `api_proveedores_admin.php?action=export&q=${encodeURIComponent(query)}&inhabilitados=${inhabilitados}`;
    };

    window.imprimirListado = function() {
        window.print();
    };

    // Carga inicial
    listarProveedores();
})();
</script>
