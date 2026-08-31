<?php
/**
 * presupuestos_admin.php - ADMINISTRACIÓN DE PRESUPUESTOS
 * Actualizado: Estados 1 a 5 y acción de Rechazar.
 */
require_once 'check_access.php';
$hoy = date('Y-m-d');
$mes_anterior = date('Y-m-d', strtotime('-1 month'));
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloPresupuestosAdmin">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRACIÓN DE PRESUPUESTOS</h4>
            <small class="text-accent opacity-75">Seguimiento, aprobación y edición de cotizaciones</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 rounded-pill px-3 btn-sm fw-bold" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" onclick="loadModule('presupuestos_nuevo.php')">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">NUEVO PRESUPUESTO</span><span class="d-inline d-sm-none">NUEVO</span>
            </button>
        </div>
    </div>

    <div class="card card-filtros p-3 mb-3 shadow-sm border-0 text-start">
        <div class="row g-3 align-items-center">
            <div class="col-6 col-md-3">
                <label class="form-label-custom">DESDE</label>
                <input type="date" id="dtmDesde" class="form-control form-control-sm filter-auto" value="<?php echo $mes_anterior; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label-custom">HASTA</label>
                <input type="date" id="dtmHasta" class="form-control form-control-sm filter-auto" value="<?php echo $hoy; ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label-custom">FILTRAR POR FECHA DE</label>
                <div class="d-flex gap-3 mt-1 bg-dark p-1 rounded border border-secondary justify-content-center">
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="tipoFecha" id="optCarga" value="carga" checked>
                        <label class="form-check-label text-white small" for="optCarga">Carga</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="tipoFecha" id="optEntrega" value="entrega">
                        <label class="form-check-label text-white small" for="optEntrega">Validez / Entrega</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-2 text-md-end">
                <button class="btn btn-secondary w-100 fw-bold btn-sm h-100" onclick="buscarPresupuestos()">
                    <i class="fas fa-search me-1"></i> BUSCAR
                </button>
            </div>
        </div>

        <div class="row g-3 mt-1 align-items-end">
            <div class="col-12 col-lg-5">
                <label class="form-label-custom">ESTADO DEL PRESUPUESTO</label>
                <div class="d-flex gap-2 mt-1 p-2 border border-secondary rounded bg-dark bg-opacity-25 flex-wrap justify-content-center">
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="1" checked> <label class="form-check-label text-warning small fw-bold">Pendientes</label></div>
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="3"> <label class="form-check-label text-success small fw-bold">Aprobados</label></div>
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="4"> <label class="form-check-label text-primary small fw-bold">Facturados</label></div>
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="5"> <label class="form-check-label text-secondary small fw-bold">Rechazados</label></div>
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="2"> <label class="form-check-label text-danger small fw-bold">Anulados</label></div>
                    <div class="form-check"><input class="form-check-input filter-auto" type="radio" name="filtroEstado" value="todos"> <label class="form-check-label text-white small">Todos</label></div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <label class="form-label-custom">BUSCAR (CLIENTE / N° PRESUPUESTO)</label>
                <input type="text" id="txtBusqueda" class="form-control form-control-sm" placeholder="Escriba para filtrar...">
            </div>
            
            <div class="col-12 col-md-6 col-lg-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="chkVendedor">
                    <label class="form-check-label text-white small" for="chkVendedor">Por Vendedor</label>
                </div>
                <select id="cmbVendedorAdmin" class="form-select select2-filter" disabled></select>
            </div>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1"><button class="erp-pag-btn" onclick="pagJumpTop()">IR</button></div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2"><label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label><select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()"><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option></select></div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded mb-2" style="max-height: 60vh; background-color: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm mb-0 small align-middle" id="gridPresupuestos">
            <thead class="sticky-top bg-black border-bottom border-secondary">
                <tr>
                    <th class="ps-3 py-2 text-center" style="width: 90px;">F. CARGA</th>
                    <th class="py-2 text-center" style="width: 80px;">N° PRES</th>
                    <th class="py-2">CLIENTE</th>
                    <th class="py-2 text-center" style="width: 90px;">F. VALIDEZ</th>
                    <th class="py-2 text-center" style="width: 60px;">CANT.</th>
                    <th class="py-2 text-end">MONTO ESTIMADO</th>
                    <th class="py-2">OBSERVACIONES</th>
                    <th class="py-2">VENDEDOR</th>
                    <th class="py-2 text-center">ESTADO</th>
                    <th class="py-2 text-center" style="width: 160px;">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyPresupuestos"></tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

    <div class="erp-pagination mb-2" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1"><button class="erp-pag-btn" onclick="pagJump()">IR</button></div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2"><label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label><select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()"><option value="20" selected>20</option><option value="50">50</option><option value="100">100</option></select></div>
    </div>

    <div class="card card-filtros bg-dark border-secondary p-3 mb-4 mt-2 shadow-sm d-none" id="panel-totales">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 text-center text-md-start">
            <div><small class="text-muted text-uppercase d-block" style="font-size: 10px;">Total Presupuestos Listados</small><span id="lblCantListados" class="fs-5 fw-bold text-white">0</span></div>
            <div class="border-start border-secondary ps-md-4"><small class="text-accent text-uppercase fw-bold d-block" style="font-size: 10px;">Total Prod. Activos</small><span id="lblCantTotal" class="fs-4 fw-bold text-accent">0</span></div>
            <div class="border-start border-secondary ps-md-4 ms-auto"><small class="text-success text-uppercase fw-bold d-block" style="font-size: 10px;">Monto Total Estimado</small><span id="lblMontoTotal" class="fs-4 fw-bold text-success">$ 0.00</span></div>
        </div>
    </div>
</div>

<script>
(function() {
    let timeoutBusqueda = null;
    let listadoPresupuestos = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;

    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        const h = {'X-Requested-With': 'XMLHttpRequest'};
        const cmbV = $('#cmbVendedorAdmin');

        try {
            const resVend = await fetch('api_helpers.php?action=get_vendedores', { headers: h }).then(r => r.json());
            cmbV.empty().append(new Option('SELECCIONE VENDEDOR...', '', true, true));
            if (resVend.success && resVend.data) {
                resVend.data.forEach(v => { cmbV.append(new Option((v.text || v.nombre || 'SIN NOMBRE').toUpperCase(), v.id, false, false)); });
            }
            cmbV.select2({ dropdownParent: $('#main-content'), width: '100%', theme: 'default', language: 'es' });

            $('.filter-auto').on('change', function() { buscarPresupuestos(); });
            $('#txtBusqueda').on('input', function() { clearTimeout(timeoutBusqueda); timeoutBusqueda = setTimeout(() => { buscarPresupuestos(); }, 400); });
            $('#chkVendedor').on('change', function() { const c = $(this).is(':checked'); cmbV.prop('disabled', !c); if(!c) cmbV.val('').trigger('change.select2'); buscarPresupuestos(); });
            cmbV.on('change', function() { if($('#chkVendedor').is(':checked')) buscarPresupuestos(); });

            buscarPresupuestos();
        } catch (e) { console.error("Error inicializando:", e); }
    }

    window.buscarPresupuestos = async function() {
        const payload = {
            desde: $('#dtmDesde').val(), hasta: $('#dtmHasta').val(),
            tipo_fecha: $('input[name="tipoFecha"]:checked').val(),
            estado: $('input[name="filtroEstado"]:checked').val(),
            busqueda: $('#txtBusqueda').val(),
            vendedor_id: $('#chkVendedor').is(':checked') ? $('#cmbVendedorAdmin').val() : 0
        };

        $('#tbodyPresupuestos').html('<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</div>');
        $('#panel-totales').addClass('d-none');

        try {
            const res = await fetch('api_presupuestos_admin.php?action=list', {
                method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(payload)
            }).then(r => r.json());

            if (res.success) {
                listadoPresupuestos = res.data || [];
                let sumCantProd = 0, sumMontoTotal = 0;
                listadoPresupuestos.forEach(r => {
                    // Los estados anulados (2) o rechazados (5) no suman a los totales
                    if (r.estadofac != 2 && r.estadofac != 5) {
                        sumCantProd += parseInt(r.cantidad_productos || 0);
                        sumMontoTotal += parseFloat(r.total_estimado || 0);
                    }
                });
                $('#lblCantListados').text(listadoPresupuestos.length);
                $('#lblCantTotal').text(sumCantProd);
                $('#lblMontoTotal').text('$ ' + sumMontoTotal.toLocaleString('es-AR', {minimumFractionDigits: 2}));
                if(listadoPresupuestos.length > 0) $('#panel-totales').removeClass('d-none');

                pagActual = 1; window.pagActual = 1;
                renderPagina();
            } else { Swal.fire({ icon: 'error', title: 'Error', text: res.error, background: '#200', color: '#fff' }); }
        } catch (e) { console.error(e); }
    }

    /* RENDERIZADO */
    function renderPagina() {
        const total = listadoPresupuestos.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listadoPresupuestos.slice((pagActual - 1) * pagTam, pagActual * pagTam);
        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tb = $('#tbodyPresupuestos').empty();
        if (data.length === 0) { tb.html('<tr><td colspan="10" class="text-center py-5 text-muted italic">No se encontraron comprobantes.</td></tr>'); return; }

        data.forEach(r => {
            let badge = '', opacityClass = 'text-white fw-bold';
            
            if (r.estadofac == 1) { badge = '<span class="badge bg-warning text-dark" style="font-size:9px">PENDIENTE</span>'; }
            else if (r.estadofac == 2) { badge = '<span class="badge bg-danger" style="font-size:9px">ANULADO</span>'; opacityClass = 'opacity-50 text-decoration-line-through text-white-50'; }
            else if (r.estadofac == 3) { badge = '<span class="badge bg-success" style="font-size:9px">APROBADO</span>'; }
            else if (r.estadofac == 4) { badge = '<span class="badge bg-primary" style="font-size:9px">FACTURADO</span>'; }
            else if (r.estadofac == 5) { badge = '<span class="badge bg-secondary" style="font-size:9px">RECHAZADO</span>'; opacityClass = 'opacity-50 text-decoration-line-through text-white-50'; }

            tb.append(`
                <tr class="${opacityClass}">
                    <td class="text-center ps-3">${formatDate(r.fecha_carga)}</td>
                    <td class="text-center text-info">#${r.numpedido}</td>
                    <td><div class="text-truncate" style="max-width: 200px;">${r.cliente_razon || r.cliente}</div></td>
                    <td class="text-center text-warning">${formatDate(r.fecha_entrega)}</td>
                    <td class="text-center">${r.cantidad_productos || 0}</td>
                    <td class="text-end text-success">$ ${parseFloat(r.total_estimado || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td><div class="text-truncate text-muted" style="max-width: 150px;">${r.observaciones || ''}</div></td>
                    <td class="small text-accent">${r.vendedor || 'S/V'}</td>
                    <td class="text-center">${badge}</td>
                    <td class="text-center">
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-sm btn-info p-1 px-2" onclick="editarPresupuesto(${r.numpedido}, ${r.estadofac})" title="Ver/Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-sm btn-secondary p-1 px-2" onclick="imprimirPresupuesto(${r.numpedido})" title="Imprimir"><i class="fas fa-print"></i></button>
                            ${(r.estadofac == 1) ? `
                            <button class="btn btn-sm btn-success p-1 px-2" onclick="aprobarPresupuesto(${r.numpedido})" title="Aprobar a Pedido"><i class="fas fa-check-circle"></i></button>
                            <button class="btn btn-sm btn-dark p-1 px-2" onclick="rechazarPresupuesto(${r.numpedido})" title="Rechazar"><i class="fas fa-thumbs-down"></i></button>
                            <button class="btn btn-sm btn-danger p-1 px-2" onclick="anularPresupuesto(${r.numpedido})" title="Anular"><i class="fas fa-ban"></i></button>` : ''}
                            ${(r.estadofac == 2 || r.estadofac == 5) ? `
                            <button class="btn btn-sm btn-success p-1 px-2" onclick="restaurarPresupuesto(${r.numpedido})" title="Restaurar a Pendiente"><i class="fas fa-trash-restore"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards').empty();
        if (data.length === 0) { wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No se encontraron comprobantes.</div>'); return; }

        data.forEach(r => {
            let estadoTxt = '', badgeStyle = '', opacityClass = '';
            
            if (r.estadofac == 1) { estadoTxt = 'PENDIENTE'; badgeStyle = 'background:rgba(255,193,7,.2);color:#ffc107;border:1px solid #ffc107;'; }
            else if (r.estadofac == 2) { estadoTxt = 'ANULADO'; badgeStyle = 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;'; opacityClass = 'opacity-50 grayscale'; }
            else if (r.estadofac == 3) { estadoTxt = 'APROBADO'; badgeStyle = 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;'; }
            else if (r.estadofac == 4) { estadoTxt = 'FACTURADO'; badgeStyle = 'background:rgba(0,123,255,.2);color:#0d6efd;border:1px solid #0d6efd;'; }
            else if (r.estadofac == 5) { estadoTxt = 'RECHAZADO'; badgeStyle = 'background:rgba(108,117,125,.2);color:#adb5bd;border:1px solid #6c757d;'; opacityClass = 'opacity-50 grayscale'; }

            let html = `
            <div class="erp-card ${opacityClass}">
                <div class="erp-card-header">
                    <div><div class="erp-card-title text-truncate" style="max-width: 220px;">${r.cliente_razon || r.cliente}</div><span class="erp-card-badge ms-1" style="${badgeStyle}">${estadoTxt}</span></div>
                    <span class="erp-card-code">#${r.numpedido}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fechas (Carga / Vto)</span><span class="erp-card-value">${formatDate(r.fecha_carga)} <i class="fas fa-arrow-right mx-1 text-muted"></i> <span class="text-warning">${formatDate(r.fecha_entrega)}</span></span></div>
                <div class="erp-card-row"><span class="erp-card-label">Vendedor / Transp</span><span class="erp-card-value text-truncate" style="max-width: 60%;">${r.vendedor || 'S/V'} / ${r.transporte || '-'}</span></div>
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <div class="w-100 d-flex justify-content-between">
                        <div><span class="erp-card-label d-block">Items</span><span class="erp-card-value">${r.cantidad_productos || 0}</span></div>
                        <div class="text-end"><span class="erp-card-label d-block text-success">Monto Estimado</span><span class="erp-card-value price fs-6 text-success">$ ${parseFloat(r.total_estimado || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>
                    </div>
                </div>

                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="editarPresupuesto(${r.numpedido}, ${r.estadofac})"><i class="fas fa-edit me-1"></i> VER</button>
                    <button class="btn btn-secondary btn-sm flex-fill fw-bold" onclick="imprimirPresupuesto(${r.numpedido})"><i class="fas fa-print me-1"></i> IMPRIMIR</button>`;

            if (r.estadofac == 1) {
                html += `
                    <button class="btn btn-success btn-sm flex-fill fw-bold" onclick="aprobarPresupuesto(${r.numpedido})"><i class="fas fa-check-circle"></i> APROBAR</button>
                    <button class="btn btn-dark btn-sm flex-fill fw-bold" onclick="rechazarPresupuesto(${r.numpedido})"><i class="fas fa-thumbs-down"></i> RECHAZAR</button>
                    <button class="btn btn-outline-danger btn-sm flex-fill fw-bold" onclick="anularPresupuesto(${r.numpedido})"><i class="fas fa-ban"></i> ANULAR</button>`;
            }
            if (r.estadofac == 2 || r.estadofac == 5) {
                html += `
                    <button class="btn btn-outline-success btn-sm flex-fill fw-bold w-100" onclick="restaurarPresupuesto(${r.numpedido})"><i class="fas fa-trash-restore me-1"></i> RESTAURAR A PENDIENTE</button>`;
            }

            html += `</div></div>`;
            wrap.append(html);
        });
    }

    /* ACCIONES DE REGISTRO */
    window.editarPresupuesto = function(id, estado) {
        if (estado != 1) { 
            Swal.fire({ icon: 'info', title: 'Modo Lectura', text: 'El presupuesto ya fue procesado y no puede editarse, sólo visualizarse.', background: '#001', color: '#fff' }); 
        }
        loadModule('presupuestos_nuevo.php?id=' + id);
    }

    window.aprobarPresupuesto = async function(id) { runAction(id, 'approve', '¿Aprobar Presupuesto?', 'Se convertirá el presupuesto en un Pedido de Venta activo.', '#28a745', 'SÍ, APROBAR'); }
    window.rechazarPresupuesto = async function(id) { runAction(id, 'reject', '¿Rechazar Presupuesto?', 'El cliente no aceptó esta cotización.', '#6c757d', 'SÍ, RECHAZAR'); }
    window.anularPresupuesto = async function(id) { runAction(id, 'annul', '¿Anular Presupuesto?', 'Se cancelará este documento por error de carga.', '#d33', 'SÍ, ANULAR'); }
    window.restaurarPresupuesto = async function(id) { runAction(id, 'restore', '¿Restaurar a Pendiente?', 'El presupuesto volverá a estado pendiente para su evaluación.', '#28a745', 'SÍ, RESTAURAR'); }

    async function runAction(id, actionRoute, title, text, color, confirmText) {
        const { isConfirmed } = await Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: color, confirmButtonText: confirmText });
        if (isConfirmed) {
            try {
                const res = await fetch(`api_presupuestos_admin.php?action=${actionRoute}`, {
                    method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ numpedido: id })
                }).then(r => r.json());
                if (res.success) buscarPresupuestos();
                else Swal.fire('Error', res.error, 'error');
            } catch (e) { console.error(e); }
        }
    }

    window.imprimirPresupuesto = function(id) { window.open('imprimir_presupuesto.php?id=' + id, '_blank'); }

    function formatDate(d) { if(!d) return '-'; const parts = d.split(' ')[0].split('-'); return `${parts[2]}/${parts[1]}/${parts[0]}`; }

    // Helpers Paginación...
    function sincronizarPaginacion(total) { /* Misma logica SPA de siempre */ 
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> presupuestos`;
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ 
                cont.innerHTML=''; if(pagTotal>1){ 
                    var ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4); if(fin-ini<4) ini=Math.max(1,fin-4); 
                    for(var i=ini; i<=fin; i++){ var b = document.createElement('button'); b.className = 'erp-pag-btn' + (i===pagActual?' active':''); b.textContent = i; b.onclick = (function(p){return function(){pagGo(p);};})(i); cont.appendChild(b); } 
                } 
            }
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }
    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ pagGo(parseInt(document.getElementById('pagJumpInput').value)); };
    window.pagJumpTop = function(){ pagGo(parseInt(document.getElementById('pagJumpInputTop').value)); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; renderPagina(); };

    // Exportación Nativa adaptada a nuevos estados
    window.exportarExcel = function() {
        if (listadoPresupuestos.length === 0) return Swal.fire({ icon: 'info', title: 'Sin datos', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        let excelHtml = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"></head><body><table><thead><tr><th>F. CARGA</th><th>N° PRES</th><th>CLIENTE</th><th>F. VALIDEZ</th><th>CANT. PROD.</th><th>OBSERVACIONES</th><th>VENDEDOR</th><th>ESTADO</th></tr></thead><tbody>`;
        let sumCantProd = 0;
        listadoPresupuestos.forEach(r => {
            let estadoTxt = '';
            if (r.estadofac == 1) estadoTxt = 'PENDIENTE'; else if (r.estadofac == 2) estadoTxt = 'ANULADO'; else if (r.estadofac == 3) estadoTxt = 'APROBADO'; else if (r.estadofac == 4) estadoTxt = 'FACTURADO'; else if (r.estadofac == 5) estadoTxt = 'RECHAZADO';
            const cant = parseInt(r.cantidad_productos || 0);
            if (r.estadofac != 2 && r.estadofac != 5) sumCantProd += cant;
            excelHtml += `<tr><td>${formatDate(r.fecha_carga)}</td><td>${r.numpedido}</td><td>${r.cliente_razon || r.cliente}</td><td>${formatDate(r.fecha_entrega)}</td><td>${cant}</td><td>${r.observaciones || ''}</td><td>${r.vendedor || ''}</td><td>${estadoTxt}</td></tr>`;
        });
        excelHtml += `<tr><td colspan="4" style="text-align:right">TOTALES ACTIVOS:</td><td>${sumCantProd}</td><td colspan="3"></td></tr></tbody></table></body></html>`;
        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a"); link.href = URL.createObjectURL(blob); link.download = `Presupuestos_${new Date().getTime()}.xls`; link.click();
    }
    init();
})();
</script>