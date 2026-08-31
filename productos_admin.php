<?php 
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">ADMINISTRACIÓN DE PRODUCTOS</h4>
            <small class="text-accent opacity-75">GESTIÓN DE STOCK, COSTOS Y PRECIOS</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button onclick="prepararListaPrecios()" class="btn btn-warning btn-sm px-3 rounded-pill fw-bold text-dark">
                <i class="fas fa-list-ol me-1"></i> <span class="d-none d-sm-inline">LISTA DE PRECIOS</span><span class="d-sm-none">LISTA</span>
            </button>
            <button onclick="exportarExcel()" class="btn btn-outline-success btn-sm px-3 rounded-pill fw-bold">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button onclick="loadModule('productos_add.php')" class="btn btn-info btn-sm px-4 fw-bold rounded-pill">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">NUEVO PRODUCTO</span><span class="d-sm-none">NUEVO</span>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-2 mb-2">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-transparent border-secondary text-secondary"><i class="fas fa-search"></i></span>
                        <input type="text" id="busqProd" class="form-control"
                               placeholder="BUSCAR POR CÓDIGO, DESCRIPCIÓN..." onkeyup="filtrarProductos()">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="filtroHab" id="hab1" value="1" checked onchange="listarProductos()">
                        <label class="btn btn-outline-secondary btn-sm" for="hab1">HABIL.</label>
                        <input type="radio" class="btn-check" name="filtroHab" id="hab2" value="0" onchange="listarProductos()">
                        <label class="btn btn-outline-secondary btn-sm" for="hab2">DESH.</label>
                        <input type="radio" class="btn-check" name="filtroHab" id="hab3" value="todos" onchange="listarProductos()">
                        <label class="btn btn-outline-secondary btn-sm" for="hab3">TODOS</label>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="chkStockBajo" onchange="listarProductos()">
                        <label class="form-check-label text-muted small" for="chkStockBajo">Stock Bajo Mín.</label>
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <div class="form-check form-switch pt-1">
                        <input class="form-check-input" type="checkbox" id="chkVistaReducida" onchange="listarProductos()">
                        <label class="form-check-label text-muted small" for="chkVistaReducida">Vista Reducida</label>
                    </div>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-6 col-md-3"><select id="fRubro"     class="form-select form-select-sm select2-filter" onchange="listarProductos()"><option value="">TODOS LOS RUBROS</option></select></div>
                <div class="col-6 col-md-3"><select id="fMarca"     class="form-select form-select-sm select2-filter" onchange="listarProductos()"><option value="">TODAS LAS MARCAS</option></select></div>
                <div class="col-6 col-md-3"><select id="fFamilia"   class="form-select form-select-sm select2-filter" onchange="listarProductos()"><option value="">TODAS LAS FAMILIAS</option></select></div>
                <div class="col-6 col-md-3"><select id="fProveedor" class="form-select form-select-sm select2-filter" onchange="listarProductos()"><option value="">TODOS LOS PROVEEDORES</option></select></div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionProdsTop">
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
        <table class="table table-hover align-middle shadow-sm">
            <thead>
                <tr id="headerProds"></tr>
            </thead>
            <tbody id="listaProductosBody"></tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="listaProductosCards"></div>

    <!-- ============================
         PAGINACIÓN (compartida)
    ============================= -->
    <div class="erp-pagination" id="paginacionProds">
        <!-- info izquierda -->
        <div class="erp-pag-info" id="pagInfo">Cargando...</div>

        <!-- controles centro/derecha -->
        <div class="erp-pag-controls">
            <!-- Ir a página -->
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInput" min="1" value="1" title="Ir a página">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <!-- Botones de página -->
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)" title="Primera">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual - 1)" title="Anterior">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual + 1)" title="Siguiente">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)" title="Última">»</button>
        </div>

        <!-- Resultados por página -->
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
       ESTADO DE PAGINACIÓN
    ============================================================ */
    var pagActual = 1;
    var pagTotal  = 1;
    var pagTam    = 20;
    var todosLosDatos = []; // dataset completo recibido del servidor
    var timerBusqueda;

    // Exponer pagActual/pagTotal al scope global para los onclick inline
    window.pagActual = 1;
    window.pagTotal  = 1;

    /* ============================================================
       INIT
    ============================================================ */
    async function initAdmin() {
        $('.select2-filter').select2({ theme: "default", width: '100%' });

        const [rubros, marcas, familias, proveedores] = await Promise.all([
            fetch('api_helpers.php?action=get_rubros',      { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
            fetch('api_helpers.php?action=get_marcas',      { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
            fetch('api_helpers.php?action=get_familias',    { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()),
            fetch('api_helpers.php?action=get_proveedores', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json())
        ]);

        const fill = (id, data, val, txt) => {
            const el = document.getElementById(id);
            if (!el) return;
            data.forEach(d => el.add(new Option(d[txt], d[val], false, false)));
        };

        if (rubros.success)     fill('fRubro',     rubros.data,     'rubro', 'rubro');
        if (marcas.success)     fill('fMarca',     marcas.data,     'id',    'marca');
        if (familias.success)   fill('fFamilia',   familias.data,   'id',    'nombre');
        if (proveedores.success) fill('fProveedor', proveedores.data, 'razon', 'razon');

        listarProductos();
    }

    /* ============================================================
       BUSCAR (con debounce)
    ============================================================ */
    window.filtrarProductos = function() {
        clearTimeout(timerBusqueda);
        timerBusqueda = setTimeout(() => listarProductos(), 300);
    };

    /* ============================================================
       FETCH DATOS + RENDER
    ============================================================ */
    window.listarProductos = async function() {
        const q   = document.getElementById('busqProd')?.value || '';
        const rub = document.getElementById('fRubro').value;
        const mar = document.getElementById('fMarca').value;
        const fam = document.getElementById('fFamilia').value;
        const prv = document.getElementById('fProveedor').value;
        const hab = document.querySelector('input[name="filtroHab"]:checked').value;
        const stk = document.getElementById('chkStockBajo').checked;

        const url = `api_productos.php?q=${encodeURIComponent(q)}&rubro=${rub}&marca=${mar}&familia=${fam}&proveedor=${prv}&habilitado=${hab}&stock_bajo=${stk}`;

        // Mostrar spinner mientras carga
        document.getElementById('listaProductosBody').innerHTML =
            '<tr><td colspan="8" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</td></tr>';
        document.getElementById('listaProductosCards').innerHTML =
            '<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</div>';

        try {
            const resp = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res  = await resp.json();

            if (res.success) {
                todosLosDatos = res.data;
            } else {
                todosLosDatos = [];
            }
        } catch(e) {
            todosLosDatos = [];
        }

        pagActual = 1;
        window.pagActual = 1;
        renderPagina();
    };

    /* ============================================================
       RENDER DE LA PÁGINA ACTUAL
    ============================================================ */
    function renderPagina() {
        const redu = document.getElementById('chkVistaReducida').checked;
        const total = todosLosDatos.length;

        // Calcular páginas
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual;
        window.pagTotal  = pagTotal;

        // Slice de datos
        const datos = todosLosDatos.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        // --- TABLA (desktop) ---
        const thead = document.getElementById('headerProds');
        const tbody = document.getElementById('listaProductosBody');

        if (redu) {
            thead.innerHTML = '<th>CÓDIGO</th><th>PRODUCTO</th><th>RUBRO</th><th class="text-end">L1 FINAL</th><th class="text-center">STOCK</th><th class="text-center">ACC.</th>';
        } else {
            thead.innerHTML = '<th>CÓDIGO</th><th>PRODUCTO</th><th>RUBRO</th><th>MARCA</th><th class="text-end">COSTO</th><th class="text-end">L1 FINAL</th><th class="text-center">STOCK</th><th class="text-center">ACC.</th>';
        }

        tbody.innerHTML = '';
        if (datos.length === 0) {
            const cols = redu ? 6 : 8;
            tbody.innerHTML = `<tr><td colspan="${cols}" class="text-center p-4 text-muted">SIN RESULTADOS</td></tr>`;
        } else {
            datos.forEach(p => {
                const tr = document.createElement('tr');
                if (p.habilitado == 0) tr.style.opacity = '0.5';
                const stockReal = parseFloat(p.stock || p.Stock || 0);
                const stkMin    = parseFloat(p.StockMin || 0);
                const stockCls  = stockReal <= stkMin ? 'text-danger' : 'text-success';
                const preciofmt = parseFloat(p.precio || 0).toLocaleString('es-AR', {minimumFractionDigits: 2});
                const costofmt  = parseFloat(p.preciocosto || 0).toFixed(2);

                let html = `
                    <td class="fw-bold text-white">${p.codigo}</td>
                    <td>${p.descripcion}</td>
                    <td><span class="badge badge-status bg-dark border border-secondary">${p.rubro}</span></td>`;

                if (!redu) {
                    html += `
                    <td>${p.nombre_marca || '-'}</td>
                    <td class="text-end text-white">$${costofmt}</td>`;
                }

                html += `
                    <td class="text-end fw-bold text-accent">$&nbsp;${preciofmt}</td>
                    <td class="text-center fw-bold ${stockCls}">${stockReal}</td>
                    <td class="text-center">
                        <button class="btn btn-link text-info p-0" onclick="loadModule('productos_add.php?codigo=${p.codigo}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>`;

                tr.innerHTML = html;
                tbody.appendChild(tr);
            });
        }

        // --- TARJETAS (mobile) ---
        renderTarjetas(datos);

        // --- PAGINACIÓN ---
        actualizarPaginacion(total);
    }

    /* ============================================================
       RENDER TARJETAS (mobile)
    ============================================================ */
    function renderTarjetas(datos) {
        const wrap = document.getElementById('listaProductosCards');
        wrap.innerHTML = '';

        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>';
            return;
        }

        datos.forEach(p => {
            const stockReal = parseFloat(p.stock || p.Stock || 0);
            const stkMin    = parseFloat(p.StockMin || 0);
            const stockCls  = stockReal <= stkMin ? 'stock-low' : 'stock-ok';
            const preciofmt = parseFloat(p.precio || 0).toLocaleString('es-AR', {minimumFractionDigits: 2});
            const costofmt  = parseFloat(p.preciocosto || 0).toFixed(2);
            const opacidad  = p.habilitado == 0 ? 'opacity: 0.5;' : '';
            const habBadge  = p.habilitado == 0
                ? '<span class="erp-card-badge" style="background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;">DESHABILITADO</span>'
                : '';

            wrap.innerHTML += `
            <div class="erp-card" style="${opacidad}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title">${p.descripcion}</div>
                        ${habBadge}
                    </div>
                    <span class="erp-card-code">${p.codigo}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Rubro</span>
                    <span class="erp-card-value">${p.rubro}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Marca</span>
                    <span class="erp-card-value">${p.nombre_marca || '-'}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Costo</span>
                    <span class="erp-card-value">$&nbsp;${costofmt}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Precio L1</span>
                    <span class="erp-card-value price">$&nbsp;${preciofmt}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Stock</span>
                    <span class="erp-card-value ${stockCls}">${stockReal}</span>
                </div>

                <div class="erp-card-actions">
                    <button class="btn btn-info btn-sm fw-bold" onclick="loadModule('productos_add.php?codigo=${p.codigo}')">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                </div>
            </div>`;
        });
    }

    /* ============================================================
       PAGINACIÓN
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> productos`;
        const visible = pagTotal > 1 ? 'flex' : 'none';

        // Sincronizar AMBOS paginadores (top e inferior)
        ['', 'Top'].forEach(function(sfx) {
            var elInfo = document.getElementById('pagInfo' + sfx);
            if (elInfo) elInfo.innerHTML = infoHtml;

            var set = function(id, val) { var el = document.getElementById(id + sfx); if (el) el.disabled = val; };
            set('btnPagPrim', pagActual <= 1);
            set('btnPagAnt',  pagActual <= 1);
            set('btnPagSig',  pagActual >= pagTotal);
            set('btnPagUlt',  pagActual >= pagTotal);

            // Botones numerados (ventana de 5)
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
                        btn.onclick = (function(p){ return function(){ pagGo(p); }; })(i);
                        cont.appendChild(btn);
                    }
                }
            }

            // Input "ir a página"
            var jump = document.getElementById('pagJumpInput' + sfx);
            if (jump) { jump.value = pagActual; jump.max = pagTotal; }

            // Select "por página" sincronizado
            var sel = document.getElementById('pagTamano' + sfx);
            if (sel) sel.value = pagTam;

            // Visibilidad del paginador
            var wrap = document.getElementById('paginacionProds' + (sfx === 'Top' ? 'Top' : ''));
            if (wrap) wrap.style.display = visible;
        });
    }

    window.pagGo = function(n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual = n;
        window.pagActual = n;
        renderPagina();
        // Scroll al inicio del contenido
        document.getElementById('main-content')?.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.pagJump = function() {
        const v = parseInt(document.getElementById('pagJumpInput').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagJumpTop = function() {
        const v = parseInt(document.getElementById('pagJumpInputTop').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagCambiarTamano = function() {
        pagTam = parseInt(document.getElementById('pagTamano').value);
        pagActual = 1;
        window.pagActual = 1;
        renderPagina();
    };

    window.pagCambiarTamanoTop = function() {
        pagTam = parseInt(document.getElementById('pagTamanoTop').value);
        pagActual = 1;
        window.pagActual = 1;
        renderPagina();
    };

    /* ============================================================
       EXPORTAR / LISTA DE PRECIOS (sin cambios de lógica)
    ============================================================ */
    window.exportarExcel = function() {
        const q   = document.getElementById('busqProd').value;
        const hab = document.querySelector('input[name="filtroHab"]:checked').value;
        window.open(`exportar_productos_excel.php?q=${encodeURIComponent(q)}&habilitado=${hab}`, '_blank');
    };

    window.prepararListaPrecios = async function() {
        const resp = await fetch('api_helpers.php?action=get_listas_utilidad', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const res  = await resp.json();

        if (!res.success) return Swal.fire('Error', 'No se pudieron cargar las listas de precios', 'error');

        let optionsHtml = '';
        res.data.forEach(l => { optionsHtml += `<option value="${l.id}">${l.id} - ${l.descripcion}</option>`; });

        const { value: idLista } = await Swal.fire({
            title: 'Generar Lista de Precios',
            html: `
                <div class="text-start mb-2 small text-muted">Seleccione la lista de precios base:</div>
                <select id="swal-id-lista" class="form-select border-accent bg-dark text-white">${optionsHtml}</select>`,
            showCancelButton: true,
            confirmButtonText: 'Generar Excel',
            cancelButtonText: 'Cancelar',
            preConfirm: () => document.getElementById('swal-id-lista').value
        });

        if (idLista) {
            const params = new URLSearchParams({
                id_lista: idLista,
                q:        document.getElementById('busqProd').value,
                rubro:    document.getElementById('fRubro').value,
                marca:    document.getElementById('fMarca').value,
                familia:  document.getElementById('fFamilia').value,
                proveedor: document.getElementById('fProveedor').value,
                habilitado: document.querySelector('input[name="filtroHab"]:checked').value
            });
            window.open(`exportar_lista_precios.php?${params.toString()}`, '_blank');
        }
    };

    // ---- ARRANQUE ----
    initAdmin();

})();
</script>