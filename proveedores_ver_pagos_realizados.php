<?php
/**
 * proveedores_ver_pagos_realizados.php - ADMINISTRADOR DE PAGOS A PROVEEDORES
 * Actualizado: Paginación en memoria (SPA) dual y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">PAGOS REALIZADOS (PROVEEDORES)</h4>
            <small class="text-accent opacity-75">Control de egresos, auditoría de pagos y exportación</small>
        </div>
        <div>
            <button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i><span class="d-none d-sm-inline">EXPORTAR EXCEL</span><span class="d-inline d-sm-none">EXCEL</span>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end text-start">
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">FECHA DESDE</label>
                    <input type="date" class="form-control form-control-sm" id="desde" name="desde" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">FECHA HASTA</label>
                    <input type="date" class="form-control form-control-sm" id="hasta" name="hasta" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">ESTADO</label>
                    <select class="form-select form-select-sm" name="estado" id="estado">
                        <option value="activos">ACTIVOS</option>
                        <option value="anulados">ANULADOS</option>
                        <option value="todos">TODOS</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">USUARIO</label>
                    <select class="form-select select2-simple" name="usuario" id="usuario">
                        <option value="todos">TODOS</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">PROVEEDOR</label>
                    <select class="form-select select2-simple" name="proveedor" id="proveedor">
                        <option value="todos">TODOS LOS PROVEEDORES</option>
                    </select>
                </div>
                <div class="col-12 col-md-1 text-end">
                    <button type="submit" class="btn btn-info btn-sm fw-bold w-100">
                        <i class="fa-solid fa-search"></i>
                    </button>
                </div>
            </form>
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
    <div class="erp-table-wrap card card-filtros p-0 shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 55vh; min-height: 350px;">
            <table class="table table-hover align-middle mb-0 small" id="tablaPagos">
                <thead class="sticky-top bg-dark">
                    <tr>
                        <th class="ps-3" style="width: 90px;">FECHA</th>
                        <th>N° PAGO</th>
                        <th>PROVEEDOR</th>
                        <th>MEDIO 1</th>
                        <th class="text-end">IMP. 1</th>
                        <th>MEDIO 2</th>
                        <th class="text-end">IMP. 2</th>
                        <th class="text-center">USUARIO</th>
                        <th class="text-center">FISCAL</th>
                        <th class="text-end text-accent">TOTAL</th>
                        <th class="text-center">ESTADO</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbodyPagos">
                    <tr>
                        <td colspan="12" class="text-center py-5 text-muted italic">
                            INGRESE LOS FILTROS Y PRESIONE BUSCAR
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">INGRESE LOS FILTROS Y PRESIONE BUSCAR</div>
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

    <!-- TOTALES FOOTER (RESPONSIVO) -->
    <div class="card card-filtros bg-dark border-secondary p-3 mb-4 mt-2 shadow-sm d-none" id="panel-totales">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="text-muted fw-bold text-uppercase small">TOTAL GENERAL ACTIVO (FILTRADO):</span>
            <span id="lblTotalGeneral" class="fs-4 fw-bold text-accent m-0">$ 0.00</span>
        </div>
    </div>
</div>

<script>
(function() {
    const form = document.getElementById('formFiltros');
    const tbody = $('#tbodyPagos');
    const tblCards = $('#tblCards');
    const btnExportar = document.getElementById('btnExportar');

    let listadoActual = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('.select2-simple').select2({ theme: 'default', width: '100%', dropdownParent: $('#main-content') });

        try {
            const resp = await fetch('api_pagos_realizados_prv.php?action=get_filters', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            
            if (res.success) {
                const cmbProv = document.getElementById('proveedor');
                res.proveedores.forEach(p => cmbProv.add(new Option(p.razon.toUpperCase(), p.id)));
                
                const cmbUsu = document.getElementById('usuario');
                res.usuarios.forEach(u => cmbUsu.add(new Option(u.usuario.toUpperCase(), u.usuario)));
            }
        } catch (e) { console.error("Error cargando filtros", e); }
        
        // Disparar carga inicial si se desea
        // cargarReporte(); 
    }

    async function cargarReporte(esExport = false) {
        if (esExport && listadoActual.length > 0) {
            generarExcel(listadoActual);
            return;
        }

        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        
        tbody.html('<tr><td colspan="12" class="text-center py-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</td></tr>');
        tblCards.html('<div class="text-center text-muted p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</div>');
        $('#panel-totales').addClass('d-none');

        try {
            const resp = await fetch(`api_pagos_realizados_prv.php?action=list&${params}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            
            if (!result.success) throw new Error(result.error);

            listadoActual = result.data || [];

            // Calcular el total general sumando SOLO los activos ANTES de paginar
            let totalGeneral = 0;
            listadoActual.forEach(p => {
                if (p.Estado != 1000) totalGeneral += parseFloat(p.Total || 0);
            });
            $('#lblTotalGeneral').text('$ ' + totalGeneral.toLocaleString('es-AR', {minimumFractionDigits: 2}));
            
            if (listadoActual.length > 0) {
                $('#panel-totales').removeClass('d-none');
            }

            if (esExport) {
                generarExcel(listadoActual);
            } else {
                pagActual = 1; window.pagActual = 1;
                renderPagina();
            }
        } catch (error) {
            listadoActual = [];
            renderPagina();
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = listadoActual.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listadoActual.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        tbody.empty();

        if (data.length === 0) {
            tbody.append('<tr><td colspan="12" class="text-center py-5 text-muted opacity-50 italic">NO SE ENCONTRARON PAGOS CON ESTOS FILTROS</td></tr>');
            return;
        }

        data.forEach((p, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const isAnulado = p.Estado == 1000;
            const isImputado = parseInt(p.Imputado || 0) > 0;

            const total = parseFloat(p.Total || 0);
            const imp1 = parseFloat(p.mp1imp || 0);
            const imp2 = parseFloat(p.mp2imp || 0);

            const rowClass = isAnulado ? 'text-decoration-line-through text-muted opacity-50' : '';
            const btnFiscalClass = p.Fiscal == 1 ? 'btn-success' : 'btn-secondary';
            const textFiscal = p.Fiscal == 1 ? 'SI' : 'NO';
            const tituloFiscal = isImputado ? 'Pago ya imputado: no se puede cambiar la fiscalidad' : 'Cambiar fiscalidad';

            tbody.append(`
                <tr class="${rowClass}">
                    <td class="ps-3 text-info pointer" onclick="window.modificarFechaPago(${absoluteIdx})" title="Clic para cambiar fecha">${formatearFecha(p.Fecha)}</td>
                    <td class="fw-bold text-white">${p['Num Pago'].toString().padStart(8, '0')}</td>
                    <td><div class="text-truncate" style="max-width:200px;">${(p.Proveedor || 'S/D').toUpperCase()}</div></td>
                    <td class="text-muted small">${(p.Medio1 || '-').toUpperCase()}</td>
                    <td class="text-end text-danger">${imp1 > 0 ? '$ ' + imp1.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                    <td class="text-muted small">${(p.Medio2 || '-').toUpperCase()}</td>
                    <td class="text-end text-danger">${imp2 > 0 ? '$ ' + imp2.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                    <td class="text-center"><span class="badge bg-secondary text-light">${(p.Usuario || '').toUpperCase()}</span></td>
                    <td class="text-center">
                        <button class="btn btn-sm ${btnFiscalClass} py-0 px-2 border-0" onclick="window.toggleFiscal(${absoluteIdx})" title="${tituloFiscal}" ${(isAnulado || isImputado) ? 'disabled' : ''}>${textFiscal}</button>
                    </td>
                    <td class="text-end fw-bold text-accent">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    <td class="text-center">
                        ${isAnulado ? '<span class="badge bg-danger border border-danger">ANULADO</span>' : '<span class="badge bg-success border border-success">ACTIVO</span>'}
                    </td>
                    <td class="text-center">
                        ${!isAnulado ? `<button class="btn btn-danger btn-sm" onclick="window.anularPagoProveedor(${absoluteIdx})" title="Anular pago"><i class="fas fa-trash-alt"></i> ANULAR</button>` : ''}
                    ${!isAnulado && isImputado ? `<button class="btn btn-outline-secondary btn-sm" onclick="window.desimputarPago(${absoluteIdx})" title="Desimputar pago"><i class="fas fa-unlink"></i></button>` : '-'}
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        tblCards.empty();

        if (data.length === 0) {
            tblCards.append('<div class="text-center text-muted p-5 opacity-50 italic">NO SE ENCONTRARON PAGOS CON ESTOS FILTROS</div>');
            return;
        }

        data.forEach((p, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const isAnulado = p.Estado == 1000;
            const isImputado = parseInt(p.Imputado || 0) > 0;

            const total = parseFloat(p.Total || 0);
            const imp1 = parseFloat(p.mp1imp || 0);
            const imp2 = parseFloat(p.mp2imp || 0);

            const rowClass = isAnulado ? 'opacity-50 grayscale' : '';
            const txtEstado = isAnulado ? 'ANULADO' : 'ACTIVO';
            const badgeEstadoStyle = isAnulado ? 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;' : 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;';
            const txtFiscal = p.Fiscal == 1 ? 'FISCAL' : 'NO FISCAL';
            const badgeFiscalStyle = p.Fiscal == 1 ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' : 'background:rgba(108,117,125,.2);color:#adb5bd;border:1px solid #6c757d;';

            let html = `
            <div class="erp-card ${rowClass}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 200px;">${(p.Proveedor || 'S/D').toUpperCase()}</div>
                        <small class="text-muted fw-bold">PAGO #${p['Num Pago'].toString().padStart(8, '0')}</small>
                    </div>
                    <div class="text-end">
                        <span class="erp-card-badge mb-1 d-inline-block" style="${badgeEstadoStyle}">${txtEstado}</span><br>
                        <span class="erp-card-code pointer" onclick="window.modificarFechaPago(${absoluteIdx})">${formatearFecha(p.Fecha)}</span>
                    </div>
                </div>`;
            
            if (imp1 > 0) {
                html += `
                <div class="erp-card-row">
                    <span class="erp-card-label">${(p.Medio1 || '-').toUpperCase()}</span>
                    <span class="erp-card-value text-danger">$ ${imp1.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>`;
            }

            if (imp2 > 0) {
                html += `
                <div class="erp-card-row">
                    <span class="erp-card-label">${(p.Medio2 || '-').toUpperCase()}</span>
                    <span class="erp-card-value text-danger">$ ${imp2.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>`;
            }

            html += `
                <div class="erp-card-row">
                    <span class="erp-card-label">Usuario</span>
                    <span class="erp-card-value text-muted">${(p.Usuario || '').toUpperCase()}</span>
                </div>
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Total Pago</span>
                    <span class="erp-card-value price fs-6 ${isAnulado ? 'text-decoration-line-through' : ''}">$ ${total.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>
                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-outline-secondary btn-sm flex-fill fw-bold" onclick="window.toggleFiscal(${absoluteIdx})" title="${isImputado ? 'Pago ya imputado: no se puede cambiar la fiscalidad' : 'Cambiar fiscalidad'}" ${(isAnulado || isImputado) ? 'disabled' : ''}>
                        <i class="fas fa-balance-scale me-1"></i> ${txtFiscal}
                    </button>
                    <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="window.modificarFechaPago(${absoluteIdx})">
                        <i class="fas fa-calendar-alt me-1"></i> FECHA
                    </button>
                    ${!isAnulado ? `<button class="btn btn-danger btn-sm" onclick="window.anularPagoProveedor(${absoluteIdx})" title="Anular pago"><i class="fas fa-trash-alt"></i> ANULAR</button>` : ''}
                    ${!isAnulado && isImputado ? `<button class="btn btn-outline-warning btn-sm flex-fill fw-bold" onclick="window.desimputarPago(${absoluteIdx})">
                        <i class="fas fa-unlink me-1"></i> DESIMPUTAR
                    </button>` : ''}
                </div>
            </div>`;
            
            tblCards.append(html);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> pagos`;
        
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatearFecha(f) {
        if(!f) return '-';
        const parts = f.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    /* ============================================================
       EXPORTACIÓN NATIVA A EXCEL (JS HTML TABLE)
    ============================================================ */
    function generarExcel(data) {
        if (!data || data.length === 0) {
            Swal.fire('Atención', 'No hay datos para exportar.', 'warning');
            return;
        }

        const fecDesde = document.getElementById('desde').value;
        const fecHasta = document.getElementById('hasta').value;

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
                    .anulado { color: #cc0000; text-decoration: line-through; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="10" class="title">REPORTE DE PAGOS A PROVEEDORES</td></tr>
                    <tr><td colspan="10">PERIODO: ${formatearFecha(fecDesde)} AL ${formatearFecha(fecHasta)}</td></tr>
                    <tr><td colspan="10"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">NRO PAGO</th>
                            <th class="header">FECHA</th>
                            <th class="header">PROVEEDOR</th>
                            <th class="header">MEDIO 1</th>
                            <th class="header">IMPORTE 1</th>
                            <th class="header">MEDIO 2</th>
                            <th class="header">IMPORTE 2</th>
                            <th class="header">USUARIO</th>
                            <th class="header">FISCAL</th>
                            <th class="header">TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        let totalGeneral = 0;

        data.forEach(p => {
            const isAnulado = p.Estado == 1000;
            const rowClass = isAnulado ? 'anulado' : '';
            const t = parseFloat(p.Total || 0);
            const imp1 = parseFloat(p.mp1imp || 0);
            const imp2 = parseFloat(p.mp2imp || 0);
            const txtFiscal = p.Fiscal == 1 ? 'SI' : 'NO';
            
            if (!isAnulado) totalGeneral += t;

            excelHtml += `
                <tr class="${rowClass}">
                    <td class="text">${p['Num Pago']}</td>
                    <td class="date">${formatearFecha(p.Fecha)}</td>
                    <td class="text">${(p.Proveedor || '').toUpperCase()}</td>
                    <td class="text">${(p.Medio1 || '').toUpperCase()}</td>
                    <td class="number">${imp1 > 0 ? imp1 : ''}</td>
                    <td class="text">${(p.Medio2 || '').toUpperCase()}</td>
                    <td class="number">${imp2 > 0 ? imp2 : ''}</td>
                    <td class="text">${(p.Usuario || '').toUpperCase()}</td>
                    <td class="text" style="text-align:center;">${txtFiscal}</td>
                    <td class="number" style="font-weight:bold;">${t}</td>
                </tr>
            `;
        });

        excelHtml += `
                    <tr>
                        <td colspan="9" style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTAL GENERAL ACTIVO:</td>
                        <td class="number" style="font-weight:bold; color: #006666; background-color:#e6f9f9;">${totalGeneral}</td>
                    </tr>
                </tbody></table></body></html>
        `;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Pagos_Proveedores_${new Date().getTime()}.xls`;
        link.click();
    }

    /* ============================================================
       ACCIONES SOBRE REGISTROS (Requieren absoluteIdx para SPA)
    ============================================================ */
    window.toggleFiscal = async (absoluteIdx) => {
        const p = listadoActual[absoluteIdx];
        try {
            const res = await fetch('api_pagos_realizados_prv.php?action=toggle_fiscal', { 
                method: 'POST', 
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, 
                body: JSON.stringify({ id: p['Num Pago'], actual: p.Fiscal }) 
            });
            const result = await res.json();
            if (result.success) {
                // Actualizar estado localmente en lugar de recargar TODO el backend
                p.Fiscal = p.Fiscal == 1 ? 0 : 1;
                renderPagina();
            }
        } catch(e) {
            Swal.fire('Error', 'Falla al cambiar estado fiscal', 'error');
        }
    };

    window.anularPagoProveedor = async (idx) => {
        const p = listadoActual[idx];
        const result = await Swal.fire({title: '¿Anular pago?', text: `Se anulará el pago #${p['Num Pago']} y todas sus imputaciones. Las facturas recuperarán el saldo correspondiente.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'ANULAR PAGO', cancelButtonText: 'CANCELAR'});
        if (!result.isConfirmed) return;
        try {
            const response = await fetch('api_pagos_realizados_prv.php?action=annul', {method: 'POST', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({id: p['Num Pago'], confirmar_imputaciones: true})});
            const res = await response.json();
            if (!res.success) throw new Error(res.error || 'No se pudo anular.');
            await Swal.fire('Anulado', 'Pago e imputaciones anulados.', 'success');
            cargarReporte();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    window.desimputarPago = async (absoluteIdx) => {
        const p = listadoActual[absoluteIdx];
        const conf = await Swal.fire({
            title: '¿Desimputar pago?',
            text: `Se liberará el saldo del pago #${p['Num Pago'].toString().padStart(8, '0')} y la(s) factura(s) asociadas volverán a estado PENDIENTE.`,
            icon: 'warning',
            showCancelButton: true
        }).then(r => r.isConfirmed);
        if (!conf) return;

        try {
            const res = await fetch('api_pagos_realizados_prv.php?action=unimpute_pago', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id: p['Num Pago'] })
            });
            const result = await res.json();
            if (result.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: 'Pago desimputado correctamente.', timer: 1800, showConfirmButton: false });
                cargarReporte();
            } else {
                Swal.fire('Error', result.error, 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Falla de conexión', 'error');
        }
    };

    window.modificarFechaPago = async (absoluteIdx) => {
        const p = listadoActual[absoluteIdx];
        const { value: nDate } = await Swal.fire({ 
            title: 'Cambiar Fecha', 
            input: 'date', 
            inputValue: p.Fecha, 
            showCancelButton: true 
        });
        
        if (nDate) {
            try {
                const res = await fetch('api_pagos_realizados_prv.php?action=change_date', { 
                    method: 'POST', 
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, 
                    body: JSON.stringify({ id: p['Num Pago'], fecha: nDate }) 
                });
                const result = await res.json();
                if (result.success) { 
                    Swal.fire({icon: 'success', title:'Éxito', text:'Fecha actualizada.', timer:1500, showConfirmButton: false}); 
                    p.Fecha = nDate;
                    renderPagina();
                } else {
                    Swal.fire('Error', result.error, 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Falla de conexión', 'error');
            }
        }
    };

    form.addEventListener('submit', e => { e.preventDefault(); cargarReporte(); });
    btnExportar.addEventListener('click', () => { cargarReporte(true); });

    init();
})();
</script>