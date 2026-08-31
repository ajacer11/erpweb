<?php
/**
 * reporte_stock_ajustes.php - INFORME DE AJUSTES DE INVENTARIO
 * Muestra el historial de ajustes realizados (individuales y grupales)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloStockAjustes">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-boxes-stacked text-accent me-2"></i>REPORTE DE AJUSTES</h4>
            <small class="text-accent opacity-75">HISTORIAL DE AUDITORÍAS Y CORRECCIONES DE STOCK</small>
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
                <div class="col-12 col-md-8">
                    <label class="form-label-custom">BUSCAR POR MOTIVO / OBSERVACIÓN / ID</label>
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
                    <th class="ps-3" style="width: 80px;">Nº AJUSTE</th>
                    <th>FECHA CARGA</th>
                    <th>USUARIO</th>
                    <th>TIPO / GRUPO</th>
                    <th class="text-center">ÍTEMS</th>
                    <th>ESTADO</th>
                    <th>OBSERVACIÓN</th>
                    <th class="text-center pe-3" style="width: 100px;">DETALLE</th>
                </tr>
            </thead>
            <tbody id="tbody-reporte">
                <tr><td colspan="8" class="text-center text-muted py-5">HAGA CLIC EN BUSCAR PARA CARGAR LOS DATOS.</td></tr>
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

    async function init() {
        $('#btn-buscar').click(buscarDatos);
        
        // Búsqueda rápida por texto (filtro en memoria)
        $('#filtro-texto').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                aplicarFiltrosMemoria();
            }, 300);
        });

        // Cambio de fecha dispara búsqueda automática
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
        
        try {
            const resp = await fetch(`api_reporte_stock_ajustes.php?action=list&desde=${desde}&hasta=${hasta}`, { 
                headers: {'X-Requested-With': 'XMLHttpRequest'} 
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
            filtradosEnMemoria = datosCompletos.filter(a => 
                (a.observacion && a.observacion.toUpperCase().includes(texto)) ||
                (a.nombregrupo && a.nombregrupo.toUpperCase().includes(texto)) ||
                (a.usuariocarga && a.usuariocarga.toUpperCase().includes(texto)) ||
                (a.id.toString().includes(texto))
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
            tbody.append('<tr><td colspan="8" class="text-center text-muted py-5">NO SE ENCONTRARON AJUSTES</td></tr>');
            return;
        }

        data.forEach(a => {
            const badgeEstado = a.estado === 'FINALIZADO' ? 'bg-success' : (a.estado === 'INICIADO' ? 'bg-info' : 'bg-danger');
            const tipoText = a.nombregrupo ? `GRUPAL: ${a.nombregrupo}` : 'INDIVIDUAL';

            tbody.append(`
                <tr>
                    <td class="fw-bold text-white ps-3">${a.id}</td>
                    <td>${a.fechacarga}</td>
                    <td class="text-white-50">${a.usuariocarga}</td>
                    <td class="small">${tipoText}</td>
                    <td class="text-center text-accent fw-bold">${a.cant_items}</td>
                    <td><span class="badge ${badgeEstado} px-2 py-1 rounded-pill">${a.estado}</span></td>
                    <td><div class="text-truncate" style="max-width: 250px;" title="${a.observacion || ''}">${a.observacion || '-'}</div></td>
                    <td class="text-center pe-3">
                        <button class="btn btn-sm btn-outline-info rounded-pill" onclick="window.verDetalle(${a.id})">
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
            wrap.append('<div class="text-center text-muted p-5 opacity-50">NO SE ENCONTRARON AJUSTES</div>');
            return;
        }

        data.forEach(a => {
            const badgeStyle = a.estado === 'FINALIZADO' ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' : 
                              (a.estado === 'INICIADO' ? 'background:rgba(23,162,184,.2);color:#17a2b8;border:1px solid #17a2b8;' : 
                                                         'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;');
            const tipoText = a.nombregrupo ? `GRUPAL: ${a.nombregrupo}` : 'INDIVIDUAL';

            wrap.append(`
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-accent">#${a.id}</div>
                        <small class="text-white-50">${a.fechacarga}</small>
                    </div>
                    <span class="erp-card-badge" style="${badgeStyle}">${a.estado}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Usuario</span><span class="erp-card-value">${a.usuariocarga}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Tipo</span><span class="erp-card-value">${tipoText}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Items</span><span class="erp-card-value fw-bold text-info">${a.cant_items}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Observ.</span><span class="erp-card-value text-truncate">${a.observacion || '-'}</span></div>
                <div class="mt-2 pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-end">
                    <button class="btn btn-sm btn-info rounded-pill px-3 fw-bold" onclick="window.verDetalle(${a.id})">
                        <i class="fas fa-list me-1"></i> VER DETALLE
                    </button>
                </div>
            </div>`);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ajustes`;

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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloStockAjustes')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    window.verDetalle = async function(idAjuste) {
        Swal.fire({ title: 'Cargando detalle...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

        try {
            const resp = await fetch(`api_reporte_stock_ajustes.php?action=detalle&id=${idAjuste}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();

            if (res.success) {
                let html = `
                <div class="table-responsive" style="max-height: 45vh; text-align: left;">
                    <table class="table table-hover align-middle table-sm">
                        <thead class="sticky-top bg-dark">
                            <tr>
                                <th class="text-white">CÓDIGO</th>
                                <th class="text-white">DESCRIPCIÓN</th>
                                <th class="text-end text-white">STOCK ANT.</th>
                                <th class="text-end text-white">CONTEO</th>
                                <th class="text-end text-white pe-2">DIF.</th>
                            </tr>
                        </thead>
                        <tbody>`;

                res.data.forEach(d => {
                    const stockAnt = parseFloat(d.stockportabla || 0);
                    const stockFinal = parseFloat(d.stockfinal || 0);
                    const dif = stockFinal - stockAnt;

                    html += `
                        <tr>
                            <td class="fw-bold text-info">${d.codigo}</td>
                            <td class="small text-white-50">${d.descripcion}</td>
                            <td class="text-end">${stockAnt.toFixed(2)}</td>
                            <td class="text-end text-white">${stockFinal.toFixed(2)}</td>
                            <td class="text-end fw-bold pe-2 ${dif < 0 ? 'text-danger' : (dif > 0 ? 'text-success' : 'text-muted')}">${dif.toFixed(2)}</td>
                        </tr>`;
                });

                html += `</tbody></table></div>`;

                Swal.fire({
                    title: `DETALLE AJUSTE #${idAjuste}`,
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
                <h3>REPORTE DE AJUSTES DE INVENTARIO</h3>
                <p>Periodo: ${$('#filtro-desde').val()} al ${$('#filtro-hasta').val()}</p>
                <table>
                    <thead>
                        <tr><th>Nº AJUSTE</th><th>FECHA</th><th>USUARIO</th><th>TIPO</th><th>ITEMS</th><th>ESTADO</th><th>OBSERVACIÓN</th></tr>
                    </thead>
                    <tbody>
        `;

        filtradosEnMemoria.forEach(a => {
            const tipo = a.nombregrupo ? `GRUPAL: ${a.nombregrupo}` : 'INDIVIDUAL';
            tablaHTML += `
                <tr>
                    <td style="text-align:center">${a.id}</td>
                    <td style="text-align:center">${a.fechacarga}</td>
                    <td>${a.usuariocarga}</td>
                    <td>${tipo}</td>
                    <td style="text-align:right">${a.cant_items}</td>
                    <td style="text-align:center">${a.estado}</td>
                    <td>${a.observacion || ''}</td>
                </tr>`;
        });

        tablaHTML += `</tbody></table></body></html>`;

        const blob = new Blob(['\ufeff', tablaHTML], { type: 'application/vnd.ms-excel' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `Reporte_Ajustes_Stock_${new Date().getTime()}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    init();
})();
</script>