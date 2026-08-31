<?php
/**
 * vendedores_admin.php - ADMINISTRADOR DE VENDEDORES
 * Actualizado: Paginación en memoria (SPA) dual y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR VENDEDORES</h4>
            <small class="text-accent opacity-75">Control de planta de ventas y configuración de incentivos</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="btn-export-v">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span><span class="d-inline d-sm-none">EXCEL</span>
            </button>
            <button onclick="loadModule('vendedores_add.php')" class="btn btn-info btn-sm fw-bold shadow rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">NUEVO VENDEDOR</span><span class="d-inline d-sm-none">NUEVO</span>
            </button>
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
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 60vh; min-height: 400px;">
            <table class="table table-hover align-middle mb-0 small" id="tabla-vendedores">
                <thead class="sticky-top">
                    <tr>
                        <th class="ps-3" style="width: 50px;">ID</th>
                        <th>NOMBRE Y APELLIDO</th>
                        <th>LOCALIDAD / PROVINCIA</th>
                        <th>TELÉFONO / CELULAR</th>
                        <th class="text-end">COMISIÓN</th>
                        <th class="text-center">ESTADO</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbody-vendedores">
                    <tr><td colspan="7" class="text-center p-5"><div class="spinner-border text-info"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>
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
</div>

<script>
(function() {
    let listado = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    
    window.pagActual = 1; window.pagTotal = 1;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        $('#btn-export-v').click(exportarExcel);
        buscar();
    }

    async function buscar() {
        $('#tbody-vendedores').html('<tr><td colspan="7" class="text-center p-5"><div class="spinner-border text-info"></div></td></tr>');
        $('#tblCards').html('<div class="text-center p-5"><div class="spinner-border text-info"></div></div>');
        try {
            const res = await fetch('api_vendedores.php?action=list', { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                listado = res.data || [];
            } else {
                listado = [];
            }
        } catch (e) { 
            console.error(e); 
            listado = [];
        }
        
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
        const tbody = $('#tbody-vendedores').empty();

        if (datos.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center p-5 text-muted opacity-50 italic">No hay vendedores registrados.</td></tr>');
            return;
        }

        datos.forEach((v, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const h = v.habilitado == 1;
            const rowClass = !h ? 'opacity-50 text-decoration-line-through text-muted' : '';
            
            tbody.append(`
                <tr class="${rowClass}">
                    <td class="ps-3 text-muted">${v.id}</td>
                    <td class="fw-bold text-white">${v.nombre}</td>
                    <td>${v.localidad} <small class="text-muted">(${v.provincia})</small></td>
                    <td><i class="fas fa-phone-alt me-2 text-muted"></i>${v.telefono || '-'} / ${v.celular || '-'}</td>
                    <td class="text-end fw-bold text-accent">${parseFloat(v.comision || 0)}%</td>
                    <td class="text-center">
                        <span class="badge ${h ? 'bg-success' : 'bg-danger'} small">${h ? 'HABILITADO' : 'INACTIVO'}</span>
                    </td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <button class="btn btn-info" onclick="window.editV(${v.id})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="window.delV(${absoluteIdx})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(datos) {
        const wrap = $('#tblCards').empty();

        if (datos.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No hay vendedores registrados.</div>');
            return;
        }

        datos.forEach((v, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            const h = v.habilitado == 1;
            const rowClass = !h ? 'opacity-50 grayscale' : '';
            const badgeClass = h ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' : 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;';
            const estadoTxt = h ? 'HABILITADO' : 'INACTIVO';

            let html = `
            <div class="erp-card ${rowClass}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 200px;">${v.nombre}</div>
                        <span class="erp-card-badge ms-2" style="${badgeClass}">${estadoTxt}</span>
                    </div>
                    <span class="erp-card-code">#${v.id}</span>
                </div>
                
                <div class="erp-card-row">
                    <span class="erp-card-label">Localidad / Prov</span>
                    <span class="erp-card-value text-truncate" style="max-width: 60%;">${v.localidad} (${v.provincia})</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Contacto</span>
                    <span class="erp-card-value">${v.telefono || '-'} / ${v.celular || '-'}</span>
                </div>
                ${v.mail ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">Email</span>
                    <span class="erp-card-value text-muted" style="font-size: 11px;">${v.mail.toLowerCase()}</span>
                </div>` : ''}
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Comisión</span>
                    <span class="erp-card-value price fs-6">${parseFloat(v.comision || 0)} %</span>
                </div>

                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="window.editV(${v.id})">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                    <button class="btn btn-outline-danger btn-sm flex-fill fw-bold" onclick="window.delV(${absoluteIdx})">
                        <i class="fas fa-trash-alt me-1"></i> ELIMINAR
                    </button>
                </div>
            </div>`;
            
            wrap.append(html);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> vendedores`;
        
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

    /* ============================================================
       ACCIONES Y LÓGICA DE NEGOCIO
    ============================================================ */

    window.editV = (id) => loadModule(`vendedores_add.php?id=${id}`);

    window.delV = async (absoluteIdx) => {
        const v = listado[absoluteIdx];
        const conf = await Swal.fire({
            title: `¿Eliminar a ${v.nombre}?`,
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ELIMINAR'
        });

        if (conf.isConfirmed) {
            const res = await fetch('api_vendedores.php?action=delete', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: v.id })
            }).then(r => r.json());

            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Borrado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                buscar(); // Refrescar lista completa
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    };

    function exportarExcel() {
        if (!listado || listado.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay vendedores cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const dateStr = new Date().toLocaleDateString('es-AR');

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .center { text-align: center; border: 0.5pt solid #ccc; }
                    .inactivo { color: #cc0000; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .activo { color: #28a745; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="10" class="title">REPORTE DE VENDEDORES</td></tr>
                    <tr><td colspan="10">FECHA DE EXPORTACIÓN: ${dateStr}</td></tr>
                    <tr><td colspan="10"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">ID</th>
                            <th class="header">NOMBRE</th>
                            <th class="header">DIRECCIÓN</th>
                            <th class="header">LOCALIDAD</th>
                            <th class="header">PROVINCIA</th>
                            <th class="header">TELÉFONO</th>
                            <th class="header">CELULAR</th>
                            <th class="header">EMAIL</th>
                            <th class="header">COMISIÓN %</th>
                            <th class="header">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        listado.forEach(v => {
            const estadoTxt = v.habilitado == 1 ? 'ACTIVO' : 'INACTIVO';
            const estadoCls = v.habilitado == 1 ? 'activo' : 'inactivo';
            
            excelHtml += `
                <tr>
                    <td class="center">${v.id}</td>
                    <td class="text" style="font-weight:bold;">${(v.nombre || '').toUpperCase()}</td>
                    <td class="text">${(v.direccion || '').toUpperCase()}</td>
                    <td class="text">${(v.localidad || '').toUpperCase()}</td>
                    <td class="text">${(v.provincia || '').toUpperCase()}</td>
                    <td class="text">${v.telefono || ''}</td>
                    <td class="text">${v.celular || ''}</td>
                    <td class="text">${(v.mail || '').toLowerCase()}</td>
                    <td class="number">${parseFloat(v.comision || 0)}</td>
                    <td class="${estadoCls}">${estadoTxt}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Vendedores_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>