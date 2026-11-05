<?php
/**
 * reporte_medios_pagos.php - MÓDULO DE PAGOS A PROVEEDORES
 * Actualizado: SPA, Paginación Dual y Responsive Cards.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloMediosPagos">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-money-bill-transfer text-accent me-2"></i>PAGOS POR MEDIO DE PAGO</h4>
            <small class="text-accent opacity-75">ANÁLISIS DE ORDENES DE PAGO A PROVEEDORES</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros p-3 mb-3 shadow-sm border-0">
        <div class="row g-2 align-items-end">
            <!-- Fila 1 -->
            <div class="col-6 col-md-2">
                <label class="form-label-custom">FECHA DESDE</label>
                <input type="date" class="form-control form-control-sm filter-auto" id="fecha_desde" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label-custom">FECHA HASTA</label>
                <input type="date" class="form-control form-control-sm filter-auto" id="fecha_hasta" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label-custom">MEDIO DE PAGO</label>
                <select class="form-select form-select-sm select2-simple filter-auto" id="id_medio">
                    <option value="">TODOS LOS MEDIOS</option>
                    <!-- Se llena vía AJAX -->
                </select>
            </div>
            <div class="col-12 col-md-2">
                <label class="form-label-custom">USUARIO</label>
                <select class="form-select form-select-sm select2-simple filter-auto" id="usuario">
                    <option value="">TODOS</option>
                    <!-- Se llena vía AJAX -->
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex align-items-center">
                <div class="form-check form-switch pt-md-3 ms-1">
                    <input class="form-check-input filter-auto-force" type="checkbox" id="agrupar" value="1">
                    <label class="form-check-label text-white fw-bold ms-1" for="agrupar" style="cursor: pointer;">
                        AGRUPAR TOTALES
                    </label>
                </div>
            </div>
            
            <!-- Fila 2 -->
            <div class="col-12 col-md-6 mt-2">
                <label class="form-label-custom">PROVEEDOR (BÚSQUEDA POR NOMBRE)</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary"><i class="fa-solid fa-truck text-muted"></i></span>
                    <input type="text" class="form-control filter-auto" id="proveedor" placeholder="DEJE EN BLANCO PARA VER TODOS">
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- ============================
         GRILLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 50vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap" id="tablaVentas">
            <!-- CABECERA PARA VISTA DETALLADA -->
            <thead class="sticky-top bg-black" id="theadDetalle">
                <tr>
                    <th class="ps-3 py-2" data-sort="fecha">FECHA</th>
                    <th data-sort="id">N° ORDEN PAGO</th>
                    <th data-sort="entidad_nombre">PROVEEDOR</th>
                    <th data-sort="nombre_medio1">MEDIO 1</th>
                    <th class="text-end" data-sort="importe1">IMPORTE 1</th>
                    <th data-sort="nombre_medio2">MEDIO 2</th>
                    <th class="text-end" data-sort="importe2">IMPORTE 2</th>
                    <th class="text-center" data-sort="usuario">USUARIO</th>
                    <th class="text-end text-accent" data-sort="total">TOTAL</th>
                </tr>
            </thead>
            <!-- CABECERA PARA VISTA AGRUPADA -->
            <thead class="sticky-top bg-black d-none" id="theadAgrupado">
                <tr>
                    <th class="ps-3 py-2" data-sort="medio">MEDIO DE PAGO</th>
                    <th class="text-end text-accent" data-sort="total">TOTAL PAGADO</th>
                </tr>
            </thead>
            <tbody id="tbodyVentas">
                <tr><td colspan="9" class="text-center py-5 text-muted italic">PRESIONE "CONSULTAR" PARA VER DATOS</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">PRESIONE "CONSULTAR" PARA VER DATOS</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- Totales Footer Responsivo -->
    <div class="card card-totales-dark shadow-sm border border-secondary mt-2 mb-4" style="background-color: #000a1a; display: none;" id="panelTotales">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <span class="text-white-50 fw-bold mb-2 mb-md-0" style="letter-spacing: 1px;">TOTAL GENERAL FILTRADO</span>
                <span class="h3 text-accent fw-bold mb-0" id="lblTotalGeneral">$ 0.00</span>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let debounceTimer = null;
    let currentData = [];
    let isAgrupado = false;
    let totalGeneral = 0;
    let sortKey = '', sortAsc = true;
    
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        // Inicializar Select2
        $('.select2-simple').select2({
            theme: 'default',
            width: '100%',
            dropdownParent: $('#main-content')
        });

        // Cargar combos
        try {
            const resp = await fetch('api_reporte_medios_pagos.php?action=get_filters', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            
            if (res.success) {
                const cmbMedio = document.getElementById('id_medio');
                res.medios.forEach(m => cmbMedio.add(new Option(m.medio.toUpperCase(), m.id)));
                
                const cmbUsu = document.getElementById('usuario');
                res.usuarios.forEach(u => cmbUsu.add(new Option(u.usuario.toUpperCase(), u.usuario)));
            }
        } catch (e) {
            console.error("Error cargando filtros:", e);
        }

        // Listeners
        document.getElementById('btnConsultar').addEventListener('click', cargarReporteForce);
        document.getElementById('btnExportar').addEventListener('click', () => generarExcel());
        $('#tablaVentas th[data-sort]').css('cursor', 'pointer').attr('title', 'Ordenar por esta columna');
        $('#tablaVentas').on('click', 'th[data-sort]', function () {
            const key = this.dataset.sort;
            sortAsc = sortKey === key ? !sortAsc : true;
            sortKey = key;
            ordenarDatos();
            pagActual = 1; window.pagActual = 1;
            renderPagina();
        });
        
        // Checkbox ejecuta búsqueda inmediata
        document.getElementById('agrupar').addEventListener('change', cargarReporteForce);

        // Inputs con Debounce
        $('.filter-auto').on('input change', function () {
            clearTimeout(debounceTimer);
            const desde = $('#fecha_desde').val();
            const hasta = $('#fecha_hasta').val();
            
            if (desde && parseInt(desde.split('-')[0]) < 2000) return;
            if (hasta && parseInt(hasta.split('-')[0]) < 2000) return;

            debounceTimer = setTimeout(() => { cargarReporte(); }, 800);
        });
    }

    function cargarReporteForce() {
        clearTimeout(debounceTimer);
        cargarReporte();
    }

    async function cargarReporte() {
        const btn = document.getElementById('btnConsultar');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';

        const tbody = document.getElementById('tbodyVentas');
        const cards = document.getElementById('tblCards');
        
        tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Generando informe...</td></tr>';
        cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin mb-2 text-info fs-3"></i><br>Generando...</div>';
        document.getElementById('panelTotales').style.display = 'none';

        const payload = new URLSearchParams({
            fecha_desde: document.getElementById('fecha_desde').value,
            fecha_hasta: document.getElementById('fecha_hasta').value,
            id_medio: document.getElementById('id_medio').value,
            usuario: document.getElementById('usuario').value,
            proveedor: document.getElementById('proveedor').value,
            agrupar: document.getElementById('agrupar').checked ? 1 : 0
        }).toString();

        try {
            const resp = await fetch(`api_reporte_medios_pagos.php?action=get_report&${payload}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error || 'Error desconocido');

            currentData = result.data.movimientos || [];
            isAgrupado = result.data.agrupado;
            totalGeneral = parseFloat(result.data.total_general || 0);
            if (sortKey && currentData.length) {
                if (Object.hasOwn(currentData[0], sortKey)) ordenarDatos();
                else {
                    sortKey = '';
                    $('#tablaVentas th[data-sort]').each(function () {
                        $(this).text($(this).data('label') || $(this).text().replace(/ [▲▼]$/, ''));
                    });
                }
            }
            
            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            console.error(error);
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function ordenarDatos() {
        const numericas = new Set(['id', 'importe1', 'importe2', 'total']);
        currentData.sort((a, b) => {
            const av = a[sortKey] ?? '', bv = b[sortKey] ?? '';
            const cmp = numericas.has(sortKey) ? Number(av) - Number(bv)
                : String(av).localeCompare(String(bv), 'es', {sensitivity:'base', numeric:true});
            return sortAsc ? cmp : -cmp;
        });
        $('#tablaVentas th[data-sort]').css('cursor', 'pointer').each(function () {
            const original = $(this).data('label') || $(this).text().replace(/ [▲▼]$/, '');
            $(this).data('label', original).text(original + (this.dataset.sort === sortKey ? (sortAsc ? ' ▲' : ' ▼') : ''));
        });
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
        
        // Actualizar Pie de Totales
        if (total > 0) {
            document.getElementById('lblTotalGeneral').innerText = '$ ' + totalGeneral.toLocaleString('es-AR', {minimumFractionDigits: 2});
            document.getElementById('panelTotales').style.display = 'block';
        } else {
            document.getElementById('panelTotales').style.display = 'none';
        }
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyVentas');
        const theadDetalle = document.getElementById('theadDetalle');
        const theadAgrupado = document.getElementById('theadAgrupado');
        
        tbody.innerHTML = '';

        if (isAgrupado) {
            theadDetalle.classList.add('d-none');
            theadAgrupado.classList.remove('d-none');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan=\"2\" class=\"text-center py-5 text-muted\">NO SE ENCONTRARON DATOS</td></tr>';
                return;
            }

            data.forEach(v => {
                const total = parseFloat(v.total || 0);
                tbody.innerHTML += `
                    <tr>
                        <td class="fw-bold text-white text-uppercase ps-3"><i class="fa-solid fa-wallet text-muted me-2"></i>${v.medio}</td>
                        <td class="text-end text-success fw-bold fs-5">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        } else {
            theadDetalle.classList.remove('d-none');
            theadAgrupado.classList.add('d-none');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan=\"9\" class=\"text-center py-5 text-muted\">NO SE ENCONTRARON PAGOS CON ESTOS FILTROS</td></tr>';
                return;
            }

            data.forEach(v => {
                const total = parseFloat(v.total || 0);
                const imp1 = parseFloat(v.importe1 || 0);
                const imp2 = parseFloat(v.importe2 || 0);
                const recFormatted = v.id.toString().padStart(8, '0');

                tbody.innerHTML += `
                    <tr>
                        <td class="text-white-50 ps-3">${formatearFechaHora(v.fecha)}</td>
                        <td class="fw-bold text-white">${recFormatted}</td>
                        <td class="text-white text-truncate" style="max-width:200px;">${(v.entidad_nombre || 'S/D').toUpperCase()}</td>
                        <td class="text-muted small text-truncate" style="max-width:120px;">${(v.nombre_medio1 || '-').toUpperCase()}</td>
                        <td class="text-end text-success">${imp1 > 0 ? '$ ' + imp1.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-muted small text-truncate" style="max-width:120px;">${(v.nombre_medio2 || '-').toUpperCase()}</td>
                        <td class="text-end text-success">${imp2 > 0 ? '$ ' + imp2.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-center"><span class="badge bg-secondary text-light">${v.usuario.toUpperCase()}</span></td>
                        <td class="text-end fw-bold text-accent">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        }
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!data || data.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50 italic">No se encontraron registros.</div>';
            return;
        }

        data.forEach(v => {
            if (isAgrupado) {
                const total = parseFloat(v.total || 0);
                wrap.innerHTML += `
                <div class="erp-card mb-2">
                    <div class="erp-card-row align-items-center">
                        <div class="d-flex align-items-center">
                            <i class="fa-solid fa-wallet fs-3 text-accent opacity-75 me-3"></i>
                            <div>
                                <span class="erp-card-label d-block text-white-50">MEDIO DE PAGO</span>
                                <span class="text-white fw-bold fs-6 text-uppercase">${v.medio}</span>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="erp-card-label d-block text-success">TOTAL PAGADO</span>
                            <span class="erp-card-value text-success fs-5 fw-bold">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>`;
            } else {
                const total = parseFloat(v.total || 0);
                const imp1 = parseFloat(v.importe1 || 0);
                const imp2 = parseFloat(v.importe2 || 0);
                const recFormatted = v.id.toString().padStart(8, '0');

                wrap.innerHTML += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header mb-1">
                        <div class="erp-card-title text-truncate" style="max-width: 250px;">${(v.entidad_nombre || 'S/D').toUpperCase()}</div>
                        <span class="erp-card-code text-info">OP # ${recFormatted}</span>
                    </div>
                    
                    <div class="erp-card-row">
                        <span class="erp-card-label">Fecha / Usuario</span>
                        <span class="erp-card-value">${formatearFechaHora(v.fecha)} <span class="text-muted mx-1">|</span> <span class="text-warning">${v.usuario.toUpperCase()}</span></span>
                    </div>
                    
                    ${imp1 > 0 ? `
                    <div class="erp-card-row">
                        <span class="erp-card-label text-truncate" style="max-width:40%;">${(v.nombre_medio1 || '').toUpperCase()}</span>
                        <span class="erp-card-value text-success">$ ${imp1.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>` : ''}

                    ${imp2 > 0 ? `
                    <div class="erp-card-row">
                        <span class="erp-card-label text-truncate" style="max-width:40%;">${(v.nombre_medio2 || '').toUpperCase()}</span>
                        <span class="erp-card-value text-success">$ ${imp2.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>` : ''}
                    
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <div class="w-100 text-end">
                            <span class="erp-card-label text-white-50 me-2">TOTAL PAGADO</span>
                            <span class="erp-card-value price text-accent">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                </div>`;
            }
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;
        
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloMediosPagos')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    // Funciones Auxiliares
    function formatearFechaHora(datetime) {
        if(!datetime) return '-';
        const [fecha, hora] = datetime.split(' ');
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y}`;
    }

    function formatearFechaInput(f) {
        if(!f) return '';
        const parts = f.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    // --- LÓGICA DE EXPORTACIÓN EXCEL NATIVA EN MEMORIA ---
    function generarExcel() {
        if (!currentData || currentData.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay datos cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .date { text-align: center; border: 0.5pt solid #ccc; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="${isAgrupado ? '2' : '9'}" class="title">REPORTE DE PAGOS POR MEDIO DE PAGO</td></tr>
                    <tr><td colspan="${isAgrupado ? '2' : '9'}">PERIODO: ${formatearFechaInput(fechaDesde)} AL ${formatearFechaInput(fechaHasta)}</td></tr>
                    <tr><td colspan="${isAgrupado ? '2' : '9'}"></td></tr>
        `;

        if (isAgrupado) {
            excelHtml += `
                    <thead>
                        <tr>
                            <th class="header">MEDIO DE PAGO</th>
                            <th class="header">TOTAL PAGADO</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            currentData.forEach(v => {
                excelHtml += `
                    <tr>
                        <td class="text">${v.medio}</td>
                        <td class="number">${parseFloat(v.total)}</td>
                    </tr>
                `;
            });
            excelHtml += `
                    <tr>
                        <td style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTAL GENERAL FILTRADO:</td>
                        <td class="number" style="font-weight:bold; color: #006666;">${totalGeneral}</td>
                    </tr>
                </tbody></table></body></html>
            `;
        } else {
            excelHtml += `
                    <thead>
                        <tr>
                            <th class="header">FECHA</th>
                            <th class="header">NRO ORDEN PAGO</th>
                            <th class="header">PROVEEDOR</th>
                            <th class="header">MEDIO PAGO 1</th>
                            <th class="header">IMPORTE 1</th>
                            <th class="header">MEDIO PAGO 2</th>
                            <th class="header">IMPORTE 2</th>
                            <th class="header">USUARIO</th>
                            <th class="header">TOTAL PAGADO</th>
                        </tr>
                    </thead>
                    <tbody>
            `;
            currentData.forEach(v => {
                const imp1 = parseFloat(v.importe1 || 0);
                const imp2 = parseFloat(v.importe2 || 0);
                const total = parseFloat(v.total || 0);
                const recFormatted = v.id.toString().padStart(8, '0');
                
                excelHtml += `
                    <tr>
                        <td class="date">${formatearFechaHora(v.fecha)}</td>
                        <td class="text" style="mso-number-format:'\\@';">${recFormatted}</td>
                        <td class="text">${(v.entidad_nombre || '').toUpperCase()}</td>
                        <td class="text">${(v.nombre_medio1 || '').toUpperCase()}</td>
                        <td class="number">${imp1 > 0 ? imp1 : ''}</td>
                        <td class="text">${(v.nombre_medio2 || '').toUpperCase()}</td>
                        <td class="number">${imp2 > 0 ? imp2 : ''}</td>
                        <td class="text">${v.usuario.toUpperCase()}</td>
                        <td class="number" style="font-weight:bold;">${total}</td>
                    </tr>
                `;
            });
            excelHtml += `
                    <tr>
                        <td colspan="8" style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTAL GENERAL FILTRADO:</td>
                        <td class="number" style="font-weight:bold; color: #006666;">${totalGeneral}</td>
                    </tr>
                </tbody></table></body></html>
            `;
        }

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Pagos_MedioPago_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>
