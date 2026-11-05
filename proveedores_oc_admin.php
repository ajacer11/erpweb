<?php
/**
 * proveedores_oc_admin.php - ADMINISTRADOR DE ÓRDENES DE COMPRA
 * Actualizado: Paginación en memoria (SPA) y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR ÓRDENES DE COMPRA</h4>
            <small class="text-accent opacity-75">Seguimiento de pedidos, estados y auditoría de abastecimiento</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 btn-sm" id="btn-export-oc">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-outline-danger border-0 btn-sm" id="btn-export-pdf-oc">
                <i class="fas fa-file-pdf me-1"></i> <span class="d-none d-sm-inline">PDF</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold px-4 rounded-pill shadow" id="btn-actualizar-oc">
                <i class="fas fa-sync-alt me-1"></i> <span class="d-none d-sm-inline">ACTUALIZAR</span>
            </button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3 text-start">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label-custom small">RANGO DE FECHAS</label>
                    <div class="d-flex gap-1">
                        <input type="date" id="f-desde-oc" class="form-control form-control-sm filter-oc" value="<?= date('Y-m-d', strtotime('-1 month')) ?>">
                        <input type="date" id="f-hasta-oc" class="form-control form-control-sm filter-oc" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="col-12 col-md-5 col-lg-4">
                    <label class="form-label-custom small">PROVEEDOR</label>
                    <select id="cmb-proveedor-admin-oc" class="form-control filter-oc"></select>
                </div>
                <div class="col-8 col-md-3">
                    <label class="form-label-custom small">ESTADO DEL PEDIDO</label>
                    <select id="cmb-estado-oc" class="form-select form-select-sm filter-oc">
                        <option value="todos">-- TODOS LOS ESTADOS --</option>
                        <option value="PENDIENTE" selected>PENDIENTES</option>
                        <option value="FINALIZADO">FINALIZADOS</option>
                    </select>
                </div>
                <div class="col-4 col-md-2 col-lg-2">
                    <button class="btn btn-secondary btn-sm w-100" id="btn-limpiar-filtros-oc">
                        <i class="fas fa-eraser me-1"></i> <span class="d-none d-sm-inline">LIMPIAR</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="pagTopWrap" style="display:none;">
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

    <!-- ============================
         GRILLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 55vh; min-height: 300px;">
            <table class="table table-hover align-middle mb-0 small" id="tabla-pedidos">
                <thead class="sticky-top">
                    <tr>
                        <th class="ps-3" style="width: 100px;">NRO</th>
                        <th style="width: 120px;">FECHA</th>
                        <th>PROVEEDOR</th>
                        <th class="text-end">MONTO ESTIMADO</th>
                        <th class="text-center">ESTADO</th>
                        <th>USUARIO</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbody-pedidos">
                    <tr><td colspan="7" class="text-center p-5 opacity-50"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-dark border-top border-secondary p-2 px-3">
            <span class="text-muted small">Total de pedidos en lista: <b class="text-white" id="lbl-count-oc">0</b></span>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted py-5 opacity-50">BUSCANDO...</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="pagBotWrap" style="display:none;">
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
</div>

<!-- MODAL DETALLE OC (PEDIDOS FINALIZADOS) -->
<div class="modal fade" id="modalDetalleOC" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg text-start">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-file-invoice me-2 text-success"></i>Detalle de Orden de Compra #<span id="mdl-oc-nro"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row mb-4">
                    <div class="col-md-6"><small class="text-muted d-block">PROVEEDOR</small><span id="mdl-oc-prov" class="fw-bold text-white fs-5"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block">FECHA EMISIÓN</small><span id="mdl-oc-fecha" class="text-white"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block">ESTADO</small><span class="badge bg-success text-white">FINALIZADO</span></div>
                </div>
                
                <h6 class="text-accent border-bottom border-secondary pb-1 small fw-bold mt-4">DETALLE DE PRODUCTOS SOLICITADOS</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-dark small">
                        <thead>
                            <tr><th>CÓDIGO</th><th>DESCRIPCIÓN</th><th class="text-center">CANT.</th><th class="text-end">COSTO</th><th class="text-end">SUBTOTAL</th></tr>
                        </thead>
                        <tbody id="mdl-oc-tbody"></tbody>
                    </table>
                </div>
                <div class="text-end mt-3"><span class="text-muted me-2">TOTAL DEL PEDIDO:</span><span id="mdl-oc-total" class="fs-5 fw-bold text-white"></span></div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-outline-success btn-sm" id="mdl-oc-btn-excel"><i class="fas fa-file-excel me-2"></i>EXPORTAR ESTA OC</button>
                <button class="btn btn-outline-danger btn-sm" id="mdl-oc-btn-pdf"><i class="fas fa-file-pdf me-2"></i>IMPRIMIR PDF</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">CERRAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let listado = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        configurarSelect2();
        configurarListeners();
        buscar();
    }

    function configurarSelect2() {
        $('#cmb-proveedor-admin-oc').select2({
            placeholder: 'TODOS LOS PROVEEDORES...',
            dropdownParent: $('#main-content'),
            allowClear: true,
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        });
    }

    function configurarListeners() {
        $('#btn-actualizar-oc').click(buscar);
        $('.filter-oc').on('change', buscar);
        $('#btn-export-oc').click(exportarExcel);
        $('#btn-export-pdf-oc').click(exportarPDF);
        $('#btn-limpiar-filtros-oc').click(() => {
            $('#f-desde-oc').val("<?= date('Y-m-d', strtotime('-1 month')) ?>");
            $('#f-hasta-oc').val("<?= date('Y-m-d') ?>");
            $('#cmb-proveedor-admin-oc').val('todos').trigger('change');
            $('#cmb-estado-oc').val('todos').trigger('change');
        });
    }

    async function buscar() {
        const filters = {
            desde: $('#f-desde-oc').val(),
            hasta: $('#f-hasta-oc').val(),
            proveedor: $('#cmb-proveedor-admin-oc').val() || 'todos',
            estado: $('#cmb-estado-oc').val()
        };
        $('#tbody-pedidos').html('<tr><td colspan="7" class="text-center p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</div>');

        try {
            const res = await fetch('api_proveedores_oc_admin.php?action=list', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters)
            }).then(r => r.json());
            
            if (res.success) {
                listado = res.data;
            } else {
                listado = [];
            }
        } catch (e) {
            console.error(e);
            listado = [];
        }

        $('#lbl-count-oc').text(listado.length);
        pagActual = 1; window.pagActual = 1;
        renderPagina();
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = listado.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listado.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(datos) {
        const tbody = $('#tbody-pedidos').empty();

        if (datos.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center p-5 text-muted small opacity-50">NO SE ENCONTRARON PEDIDOS</td></tr>');
            return;
        }

        datos.forEach((p, idx) => {
            const isPendiente = p.Estado === 'PENDIENTE';
            const badgeClass = isPendiente ? 'bg-warning text-dark' : 'bg-success text-white';
            const absoluteIdx = (pagActual - 1) * pagTam + idx;

            tbody.append(`
                <tr class="${!isPendiente ? 'opacity-75' : ''}">
                    <td class="ps-3 fw-bold text-accent">${p['Nro Pedido']}</td>
                    <td>${p.Fecha}</td>
                    <td><div class="text-truncate" style="max-width:250px">${p.Proveedor}</div></td>
                    <td class="text-end fw-bold">$ ${parseFloat(p['Monto Estimado']).toFixed(2)}</td>
                    <td class="text-center">
                        <span class="badge ${badgeClass} small py-1 px-2 border border-secondary">${p.Estado}</span>
                    </td>
                    <td><small class="text-muted">${p.Usuario}</small></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm">
                            <button class="btn ${isPendiente ? 'btn-info' : 'btn-outline-success'}" 
                                    onclick="window.gestionarPedido(${absoluteIdx})" 
                                    title="${isPendiente ? 'Ver/Editar' : 'Visualizar Detalle'}">
                                <i class="fas ${isPendiente ? 'fa-edit' : 'fa-eye'}"></i>
                            </button>
                            ${isPendiente ? `<button class="btn btn-outline-success" onclick="window.finalizarOC(${absoluteIdx})" title="Finalizar"><i class="fas fa-check-circle"></i></button>` : ''}
                            <button class="btn btn-outline-secondary" onclick="window.exportarPdfOC(${absoluteIdx})" title="Imprimir PDF"><i class="fas fa-file-pdf"></i></button>
                            <button class="btn btn-outline-danger" onclick="window.eliminarOC(${absoluteIdx})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!datos.length) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50">NO SE ENCONTRARON PEDIDOS</div>';
            return;
        }

        datos.forEach((p, idx) => {
            const isPendiente = p.Estado === 'PENDIENTE';
            const badgeClass = isPendiente ? 'background:rgba(255,193,7,.2);color:#ffc107;border:1px solid #ffc107;' : 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;';
            const absoluteIdx = (pagActual - 1) * pagTam + idx;

            let html = `
            <div class="erp-card ${!isPendiente ? 'opacity-75' : ''}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 200px;">${p.Proveedor}</div>
                        <span class="erp-card-badge ms-2" style="${badgeClass}">${p.Estado}</span>
                    </div>
                    <span class="erp-card-code">#${p['Nro Pedido']}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha Emisión</span>
                    <span class="erp-card-value">${p.Fecha}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Usuario</span>
                    <span class="erp-card-value text-muted">${p.Usuario}</span>
                </div>
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Monto Estimado</span>
                    <span class="erp-card-value price fs-6">$ ${parseFloat(p['Monto Estimado']).toFixed(2)}</span>
                </div>
                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn ${isPendiente ? 'btn-info' : 'btn-outline-success'} btn-sm fw-bold flex-fill" onclick="window.gestionarPedido(${absoluteIdx})">
                        <i class="fas ${isPendiente ? 'fa-edit' : 'fa-eye'} me-1"></i> ${isPendiente ? 'EDITAR' : 'VER'}
                    </button>`;

            if (isPendiente) {
                html += `
                    <button class="btn btn-outline-success btn-sm flex-fill fw-bold" onclick="window.finalizarOC(${absoluteIdx})">
                        <i class="fas fa-check-circle me-1"></i> FINALIZAR
                    </button>
                    <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="window.exportarPdfOC(${absoluteIdx})" title="Imprimir PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm flex-fill" onclick="window.eliminarOC(${absoluteIdx})" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>`;
            } else {
                // Si está finalizado, permitimos que se borre si es necesario (el código original lo permitía)
                html += `
                    <button class="btn btn-outline-secondary btn-sm flex-fill" onclick="window.exportarPdfOC(${absoluteIdx})" title="Imprimir PDF">
                        <i class="fas fa-file-pdf"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm flex-fill" onclick="window.eliminarOC(${absoluteIdx})" title="Eliminar">
                        <i class="fas fa-trash-alt"></i>
                    </button>`;
            }

            html += `</div></div>`;
            wrap.innerHTML += html;
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> pedidos`;
        const vis   = pagTotal > 1 ? 'flex' : 'none';
        
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
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); if(wrap) wrap.style.display=total>0?vis:'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    /* ============================================================
       ACCIONES Y LÓGICA DE NEGOCIO
    ============================================================ */
    window.gestionarPedido = (absoluteIdx) => {
        const p = listado[absoluteIdx];
        if (p.Estado === 'FINALIZADO') {
            verDetalleOCModal(p['Nro Pedido']);
        } else {
            loadModule(`proveedores_oc_new.php?id=${p['Nro Pedido']}&edit=true`);
        }
    };

    async function verDetalleOCModal(id) {
        try {
            const res = await fetch(`api_proveedores_oc.php?action=get_order&id=${id}`, { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                $('#mdl-oc-nro').text(id);
                $('#mdl-oc-prov').text(res.cab.proveedor_nombre);
                $('#mdl-oc-fecha').text(res.cab.fecha.split('-').reverse().join('-'));
                $('#mdl-oc-total').text('$ ' + parseFloat(res.cab.total).toLocaleString('es-AR', {minimumFractionDigits:2}));
                
                const tbody = $('#mdl-oc-tbody').empty();
                res.items.forEach(i => {
                    tbody.append(`<tr>
                        <td>${i.codigo}</td><td>${i.descripcion}</td>
                        <td class="text-center">${i.cantidad}</td>
                        <td class="text-end">$ ${i.costo.toFixed(2)}</td>
                        <td class="text-end fw-bold">$ ${i.subtotal.toFixed(2)}</td>
                    </tr>`);
                });
                
                $('#mdl-oc-btn-excel').off('click').on('click', () => exportarSingleOC(res));
                $('#mdl-oc-btn-pdf').off('click').on('click', () => solicitarPdfOC(id));
                $('#modalDetalleOC').modal('show');
            }
        } catch (e) { console.error(e); }
    }

    function exportarSingleOC(data) {
        let html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
                <style>
                    .header { background-color: #001020; color: #00ffff; font-weight: bold; text-align: center; border: 1px solid #000; height: 30px; }
                    .cell { border: 1px solid #ccc; padding: 5px; }
                    .num { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; }
                    .total-label { font-weight: bold; background-color: #f2f2f2; text-align: right; }
                    .total-val { font-weight: bold; color: #d9534f; text-align: right; }
                    .title { font-size: 18px; font-weight: bold; text-align: center; color: #333; }
                </style>
            </head>
            <body>
                <table>
                    <tr><th colspan="5" class="title">ORDEN DE COMPRA #${data.cab.id}</th></tr>
                    <tr><th colspan="5" style="text-align: left;">PROVEEDOR: ${data.cab.proveedor_nombre}</th></tr>
                    <tr><th colspan="5" style="text-align: left; margin-bottom: 20px;">FECHA: ${data.cab.fecha}</th></tr>
                    <tr><td colspan="5"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">C&Oacute;DIGO</th>
                            <th class="header">DESCRIPCI&Oacute;N</th>
                            <th class="header">CANTIDAD</th>
                            <th class="header">COSTO UNIT.</th>
                            <th class="header">SUBTOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.items.forEach(i => {
            html += `
                <tr>
                    <td class="cell">${i.codigo}</td>
                    <td class="cell">${i.descripcion}</td>
                    <td class="cell" style="text-align: center;">${i.cantidad}</td>
                    <td class="cell num">${parseFloat(i.costo)}</td>
                    <td class="cell num">${parseFloat(i.subtotal)}</td>
                </tr>
            `;
        });

        html += `
                        <tr>
                            <td colspan="4" class="cell total-label">TOTAL ESTIMADO:</td>
                            <td class="cell num total-val">${parseFloat(data.cab.total)}</td>
                        </tr>
                    </tbody>
                </table>
            </body>
            </html>
        `;

        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", `OC_${data.cab.id}_${data.cab.proveedor_nombre.replace(/\s+/g, '_')}.xls`);
        link.click();
    }

    // Pregunta si el PDF debe incluir precios/costos antes de abrirlo (el usuario
    // puede querer un remito "ciego" sin precios para el dep\u00f3sito/proveedor).
    async function solicitarPrecios() {
        const result = await Swal.fire({
            title: '\u00bfIncluir precios en el PDF?',
            text: 'Pod\u00e9s generar el pedido con o sin precios/costos visibles.',
            icon: 'question',
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'CON PRECIOS',
            denyButtonText: 'SIN PRECIOS',
            cancelButtonText: 'CANCELAR'
        });
        if (result.isConfirmed) return '1';
        if (result.isDenied) return '0';
        return null;
    }

    async function solicitarPdfOC(idPedido) {
        const precios = await solicitarPrecios();
        if (precios === null) return;
        const orden = await Swal.fire({
            title: 'Orden de impresión', input: 'select',
            inputOptions: {grabado: 'Como está grabado', alfabetico: 'Alfabético', cantidad: 'Cantidad de mayor a menor', precio: 'Precio de mayor a menor', codigo: 'Código', proveedor: 'Código proveedor'},
            inputValue: 'grabado', showCancelButton: true, confirmButtonText: 'IMPRIMIR', cancelButtonText: 'CANCELAR'
        });
        if (!orden.isConfirmed) return;
        window.open(`imprimir_oc.php?id=${idPedido}&precios=${precios}&orden=${orden.value}`, '_blank');
    }

    window.exportarPdfOC = (absoluteIdx) => {
        const p = listado[absoluteIdx];
        solicitarPdfOC(p['Nro Pedido']);
    };

    window.finalizarOC = async (absoluteIdx) => {
        const p = listado[absoluteIdx];
        if (await Swal.fire({ title: `¿Finalizar Pedido Nº ${p['Nro Pedido']}?`, text: "No podrá editarse luego.", icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_proveedores_oc_admin.php?action=finalize', { method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: p['Nro Pedido'] }) }).then(r => r.json());
            if (res.success) { Swal.fire({ icon: 'success', title: 'Finalizado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 }); buscar(); }
            else Swal.fire('Error', res.error, 'error');
        }
    };

    window.eliminarOC = async (absoluteIdx) => {
        const p = listado[absoluteIdx];
        if (await Swal.fire({ title: `¿Eliminar Pedido Nº ${p['Nro Pedido']}?`, text: "Acción permanente.", icon: 'warning', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_proveedores_oc_admin.php?action=delete', { method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: p['Nro Pedido'] }) }).then(r => r.json());
            if (res.success) { Swal.fire({ icon: 'success', title: 'Borrado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 }); buscar(); }
            else Swal.fire('Error', res.error, 'error');
        }
    };

    // Genera el .xls enteramente en el navegador (Blob), a partir del `listado`
    // ya cargado en memoria. Evita depender de una descarga directa por
    // window.open contra la API (bloqueada por el filtro AJAX-only de
    // check_api_access.php, que no ve el header X-Requested-With en una
    // navegación normal de ventana).
    function exportarExcel() {
        if (listado.length === 0) return Swal.fire('Atención', 'No hay pedidos para exportar.', 'warning');

        const totalGeneral = listado.reduce((acc, p) => acc + (parseFloat(p['Monto Estimado']) || 0), 0);

        let html = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
                <style>
                    td, th { border: 1px solid #999999; padding: 5px 10px; font-family: Calibri, Arial, sans-serif; font-size: 12px; }
                    .title { font-size: 18px; font-weight: bold; text-align: center; background-color: #001020; color: #00e5ff; }
                    .subtitle { background-color: #f0f0f0; font-style: italic; text-align: left; color: #333; }
                    .header { background-color: #001020; color: #ffffff; font-weight: bold; text-align: center; }
                    .text { text-align: left; mso-number-format: '\\@'; }
                    .num { text-align: right; mso-number-format: '0.00'; }
                    .row-even { background-color: #ffffff; }
                    .row-odd { background-color: #eaf3fc; }
                    .estado-pend { color: #b8860b; font-weight: bold; text-align: center; }
                    .estado-fin { color: #1e7e34; font-weight: bold; text-align: center; }
                    .total-label { text-align: right; font-weight: bold; background-color: #001020; color: #ffffff; }
                    .total-val { text-align: right; font-weight: bold; background-color: #ffd54f; color: #001020; font-size: 14px; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="6" class="title">LISTADO DE ÓRDENES DE COMPRA</td></tr>
                    <tr><td colspan="6" class="subtitle">Generado el: ${new Date().toLocaleString('es-AR')} | Rango: ${$('#f-desde-oc').val()} a ${$('#f-hasta-oc').val()} | Estado: ${$('#cmb-estado-oc option:selected').text()}</td></tr>
                    <tr><td colspan="6"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">NRO PEDIDO</th>
                            <th class="header">FECHA</th>
                            <th class="header">PROVEEDOR</th>
                            <th class="header">MONTO ESTIMADO</th>
                            <th class="header">ESTADO</th>
                            <th class="header">USUARIO</th>
                        </tr>
                    </thead>
                    <tbody>`;

        listado.forEach((p, idx) => {
            const rowCls = idx % 2 === 0 ? 'row-even' : 'row-odd';
            const estadoCls = p.Estado === 'PENDIENTE' ? 'estado-pend' : 'estado-fin';
            html += `
                        <tr class="${rowCls}">
                            <td class="text">${p['Nro Pedido']}</td>
                            <td class="text">${p.Fecha}</td>
                            <td>${p.Proveedor}</td>
                            <td class="num">${parseFloat(p['Monto Estimado']).toFixed(2)}</td>
                            <td class="${estadoCls}">${p.Estado}</td>
                            <td>${p.Usuario}</td>
                        </tr>`;
        });

        html += `
                    </tbody>
                    <tr>
                        <td colspan="3" class="total-label">TOTAL ESTIMADO (${listado.length} pedidos)</td>
                        <td colspan="3" class="total-val">${totalGeneral.toFixed(2)}</td>
                    </tr>
                </table>
            </body>
            </html>`;

        const blob = new Blob(['﻿', html], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `Ordenes_Compra_${new Date().toISOString().slice(0, 10)}.xls`;
        link.click();
    }

    function exportarPDF() {
        if (listado.length === 0) return Swal.fire('Atención', 'No hay pedidos para exportar.', 'warning');
        const params = new URLSearchParams({
            desde: $('#f-desde-oc').val(),
            hasta: $('#f-hasta-oc').val(),
            proveedor: $('#cmb-proveedor-admin-oc').val() || 'todos',
            estado: $('#cmb-estado-oc').val()
        }).toString();
        window.open(`imprimir_listado_oc.php?${params}`, '_blank');
    }

    init();
})();
</script>