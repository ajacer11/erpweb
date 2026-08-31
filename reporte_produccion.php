<?php
/**
 * reporte_produccion.php - REPORTE DE ÓRDENES DE PRODUCCIÓN
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloReporteProduccion">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-industry text-accent me-2"></i>REPORTE DE PRODUCCIÓN</h4>
            <small class="text-accent opacity-75">HISTORIAL DE ÓRDENES, CONSUMOS Y COSTOS</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btn-buscar">
                <i class="fas fa-search me-1"></i> BUSCAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">DESDE FECHA</label>
                    <input type="date" id="filtro-desde" class="form-control trigger-filter" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA FECHA</label>
                    <input type="date" id="filtro-hasta" class="form-control trigger-filter" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">ESTADO</label>
                    <select id="filtro-estado" class="form-select trigger-filter">
                        <option value="TODOS">TODOS</option>
                        <option value="PENDIENTE">BORRADOR</option>
                        <option value="CONFIRMADA">CONFIRMADA</option>
                        <option value="FINALIZADA">FINALIZADA</option>
                        <option value="CANCELADA">CANCELADA</option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label-custom">BUSCAR POR PRODUCTO / NOTAS / Nº ORDEN</label>
                    <input type="text" id="filtro-texto" class="form-control" placeholder="INGRESE TEXTO A BUSCAR...">
                </div>
            </div>
        </div>
    </div>

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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto; position: relative;">
        <div id="loading-table" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100; justify-content: center; align-items: center;">
            <div class="text-center">
                <div class="spinner-border text-info" role="status"></div>
                <div class="text-info mt-2 small">CARGANDO REPORTE...</div>
            </div>
        </div>

        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 110px;">Nº ORDEN</th>
                    <th>FECHA INICIO</th>
                    <th>FECHA FIN</th>
                    <th>PRODUCTOS</th>
                    <th class="text-center">INSUMOS</th>
                    <th>ESTADO</th>
                    <th>COSTEO</th>
                    <th class="text-end">VALOR PRODUCIDO</th>
                    <th class="text-center pe-3" style="width: 100px;">DETALLE</th>
                </tr>
            </thead>
            <tbody id="tbody-reporte">
                <tr><td colspan="9" class="text-center text-muted py-5">HAGA CLIC EN BUSCAR PARA CARGAR LOS DATOS.</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">HAGA CLIC EN BUSCAR PARA CARGAR LOS DATOS.</div>
    </div>

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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>
</div>

<script>
(function() {
    let datosCompletos = [];
    let filtradosEnMemoria = [];
    let debounceTimer = null;
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    function estadoInfo(st) {
        st = parseInt(st);
        switch (st) {
            case 1: return { texto: 'BORRADOR',   badge: 'bg-warning text-dark' };
            case 2: return { texto: 'CONFIRMADA', badge: 'bg-primary' };
            case 3: return { texto: 'CONFIRMADA', badge: 'bg-primary' };
            case 4: return { texto: 'FINALIZADA', badge: 'bg-success' };
            case 5: return { texto: 'CANCELADA',  badge: 'bg-danger' };
            default: return { texto: 'DESCONOCIDO', badge: 'bg-dark' };
        }
    }

    async function init() {
        $('#btn-buscar').click(buscarDatos);

        $('#filtro-texto').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => aplicarFiltrosMemoria(), 300);
        });

        $('.trigger-filter').on('change', function() {
            const desde = $('#filtro-desde').val();
            const hasta = $('#filtro-hasta').val();
            if ((desde && parseInt(desde.split('-')[0]) < 2000) || (hasta && parseInt(hasta.split('-')[0]) < 2000)) return;
            buscarDatos();
        });

        buscarDatos();
    }

    async function buscarDatos() {
        $('#loading-table').css('display', 'flex');
        const desde = $('#filtro-desde').val();
        const hasta = $('#filtro-hasta').val();
        const estado = $('#filtro-estado').val();

        try {
            const resp = await fetch(`api_reporte_produccion.php?action=list&desde=${desde}&hasta=${hasta}&estado=${estado}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();

            if (res.success) {
                datosCompletos = res.data || [];
                aplicarFiltrosMemoria();
            } else {
                throw new Error(res.error);
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'Fallo de conexión al cargar el reporte.', 'error');
        } finally {
            $('#loading-table').hide();
        }
    }

    function aplicarFiltrosMemoria() {
        const texto = $('#filtro-texto').val().toUpperCase();

        if (!texto) {
            filtradosEnMemoria = [...datosCompletos];
        } else {
            filtradosEnMemoria = datosCompletos.filter(o =>
                (o.notas && o.notas.toUpperCase().includes(texto)) ||
                (o.productos_a_producir && o.productos_a_producir.toUpperCase().includes(texto)) ||
                (o.numero_orden && o.numero_orden.toUpperCase().includes(texto)) ||
                (o.id.toString().includes(texto))
            );
        }

        pagActual = 1; window.pagActual = 1;
        renderPagina();
    }

    function renderPagina() {
        const total = filtradosEnMemoria.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const datosSegmento = filtradosEnMemoria.slice(inicio, inicio + pagTam);

        renderTabla(datosSegmento);
        renderTarjetas(datosSegmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = $('#tbody-reporte').empty();

        if (data.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center text-muted py-5">NO SE ENCONTRARON ÓRDENES</td></tr>');
            return;
        }

        data.forEach(o => {
            const st = estadoInfo(o.estado);
            const valor = parseFloat(o.valor_total_producido || 0);
            tbody.append(`
                <tr>
                    <td class="fw-bold text-white ps-3">${o.numero_orden || '#' + o.id}</td>
                    <td>${o.fecha_inicio_prevista ? new Date(o.fecha_inicio_prevista).toLocaleDateString() : '-'}</td>
                    <td>${o.fecha_fin_real ? new Date(o.fecha_fin_real).toLocaleDateString() : '-'}</td>
                    <td class="small"><div class="text-truncate" style="max-width:220px;" title="${o.productos_a_producir || ''}">${o.productos_a_producir || '-'} (${o.cant_salidas})</div></td>
                    <td class="text-center text-accent fw-bold">${o.cant_insumos}</td>
                    <td><span class="badge ${st.badge} px-2 py-1 rounded-pill">${st.texto}</span></td>
                    <td class="small">${o.modo_costeo || '-'}</td>
                    <td class="text-end fw-bold">${valor > 0 ? '$' + valor.toFixed(2) : '-'}</td>
                    <td class="text-center pe-3">
                        <button class="btn btn-sm btn-outline-info rounded-pill" onclick="window.verDetalle(${o.id})">
                            <i class="fas fa-search-plus"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards').empty();

        if (data.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50">NO SE ENCONTRARON ÓRDENES</div>');
            return;
        }

        data.forEach(o => {
            const st = estadoInfo(o.estado);
            const valor = parseFloat(o.valor_total_producido || 0);
            wrap.append(`
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-accent">${o.numero_orden || '#' + o.id}</div>
                        <small class="text-white-50">${o.fecha_inicio_prevista ? new Date(o.fecha_inicio_prevista).toLocaleDateString() : '-'}</small>
                    </div>
                    <span class="badge ${st.badge}">${st.texto}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Productos</span><span class="erp-card-value text-truncate">${o.productos_a_producir || '-'}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Insumos</span><span class="erp-card-value fw-bold text-info">${o.cant_insumos}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Valor producido</span><span class="erp-card-value fw-bold">${valor > 0 ? '$' + valor.toFixed(2) : '-'}</span></div>
                <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-end">
                    <button class="btn btn-sm btn-info rounded-pill px-3 fw-bold" onclick="window.verDetalle(${o.id})">
                        <i class="fas fa-list me-1"></i> VER DETALLE
                    </button>
                </div>
            </div>`);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> órdenes`;

        ['','Top'].forEach(sfx => {
            const el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            const cont = document.getElementById('pagBotones'+sfx);
            if(cont){
                cont.innerHTML='';
                if(pagTotal>1){
                    let ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4);
                    if(fin-ini<4) ini=Math.max(1,fin-4);
                    for(let i=ini; i<=fin; i++){
                        const b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = (p => () => pagGo(p))(i);
                        cont.appendChild(b);
                    }
                }
            }
            const ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            const sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            const wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloReporteProduccion')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    window.verDetalle = async function(idOrden) {
        Swal.fire({ title: 'Cargando detalle...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        try {
            const resp = await fetch(`api_reporte_produccion.php?action=detalle&id=${idOrden}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();

            if (res.success) {
                let html = `
                <div class="table-responsive" style="max-height: 40vh; text-align: left;">
                    <h6 class="text-danger">Insumos</h6>
                    <table class="table table-hover align-middle table-sm mb-3">
                        <thead class="sticky-top bg-dark">
                            <tr><th class="text-white">CÓDIGO</th><th class="text-white">DESCRIPCIÓN</th><th class="text-end text-white">REQUERIDO</th><th class="text-end text-white">CONSUMIDO</th></tr>
                        </thead>
                        <tbody>`;

                res.entradas.forEach(e => {
                    html += `<tr>
                        <td class="fw-bold text-info">${e.codigo}</td>
                        <td class="small text-white-50">${e.descripcion}</td>
                        <td class="text-end">${parseFloat(e.cantidad_requerida).toFixed(2)}</td>
                        <td class="text-end text-white">${e.cantidad_consumida !== null ? parseFloat(e.cantidad_consumida).toFixed(2) : '-'}</td>
                    </tr>`;
                });

                html += `</tbody></table>
                    <h6 class="text-success">Salidas</h6>
                    <table class="table table-hover align-middle table-sm">
                        <thead class="sticky-top bg-dark">
                            <tr><th class="text-white">CÓDIGO</th><th class="text-white">DESCRIPCIÓN</th><th class="text-end text-white">CANTIDAD</th><th class="text-end text-white">COSTO UNIT.</th><th class="text-end text-white pe-2">VALOR</th></tr>
                        </thead>
                        <tbody>`;

                res.salidas.forEach(s => {
                    const cant = parseFloat(s.cantidad_a_producir || 0);
                    const costo = s.costo_unitario_asignado !== null ? parseFloat(s.costo_unitario_asignado) : null;
                    html += `<tr>
                        <td class="fw-bold text-info">${s.codigo}</td>
                        <td class="small text-white-50">${s.descripcion}</td>
                        <td class="text-end">${cant.toFixed(2)}</td>
                        <td class="text-end">${costo !== null ? costo.toFixed(2) : '-'}</td>
                        <td class="text-end fw-bold pe-2">${costo !== null ? (costo * cant).toFixed(2) : '-'}</td>
                    </tr>`;
                });

                html += `</tbody></table></div>`;

                Swal.fire({
                    title: `DETALLE ORDEN #${idOrden}`,
                    html: html,
                    width: '850px',
                    showCloseButton: true,
                    confirmButtonText: 'CERRAR',
                    confirmButtonColor: '#00ffffdc',
                    customClass: { confirmButton: 'text-dark fw-bold rounded-pill' }
                });
            } else {
                throw new Error(res.error);
            }
        } catch (error) {
            Swal.fire('Error', error.message || 'No se pudo cargar el detalle.', 'error');
        }
    };

    window.exportarExcel = function() {
        if (filtradosEnMemoria.length === 0) return Swal.fire('Atención', 'No hay datos para exportar.', 'warning');

        let tablaHTML = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8"><style>
                table { border-collapse: collapse; font-family: Arial; }
                th { background-color: #0d2b45; color: #00ffff; font-weight: bold; border: 1px solid #445; padding: 8px; }
                td { border: 1px solid #ccc; padding: 6px; }
            </style></head>
            <body>
                <h3>REPORTE DE PRODUCCIÓN</h3>
                <p>Periodo: ${$('#filtro-desde').val()} al ${$('#filtro-hasta').val()}</p>
                <table>
                    <thead>
                        <tr><th>Nº ORDEN</th><th>FECHA INICIO</th><th>FECHA FIN</th><th>PRODUCTOS</th><th>INSUMOS</th><th>ESTADO</th><th>COSTEO</th><th>VALOR PRODUCIDO</th></tr>
                    </thead>
                    <tbody>
        `;

        filtradosEnMemoria.forEach(o => {
            const st = estadoInfo(o.estado);
            tablaHTML += `
                <tr>
                    <td style="text-align:center">${o.numero_orden || '#' + o.id}</td>
                    <td style="text-align:center">${o.fecha_inicio_prevista || ''}</td>
                    <td style="text-align:center">${o.fecha_fin_real || ''}</td>
                    <td>${o.productos_a_producir || ''}</td>
                    <td style="text-align:right">${o.cant_insumos}</td>
                    <td style="text-align:center">${st.texto}</td>
                    <td style="text-align:center">${o.modo_costeo || ''}</td>
                    <td style="text-align:right">${parseFloat(o.valor_total_producido || 0).toFixed(2)}</td>
                </tr>`;
        });

        tablaHTML += `</tbody></table></body></html>`;

        const blob = new Blob(['﻿', tablaHTML], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Reporte_Produccion_${new Date().getTime()}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    init();
})();
</script>
