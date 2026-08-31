<?php
/**
 * vendedores_cta_cte.php - RESUMEN DE CUENTA CORRIENTE DE VENDEDORES
 * Actualizado: Paginación en memoria (SPA) dual y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">CUENTA CORRIENTE DE VENDEDORES</h4>
            <small class="text-accent opacity-75">Consulta de comisiones devengadas y pagos realizados</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="btn-export-ccv">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span><span class="d-inline d-sm-none">EXCEL</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold px-4 shadow rounded-pill" id="btn-buscar-ccv">
                <i class="fas fa-search me-1"></i> <span class="d-none d-sm-inline">VER CUENTA</span><span class="d-inline d-sm-none">BUSCAR</span>
            </button>
        </div>
    </div>

    <!-- SECCIÓN FILTROS -->
    <div class="card card-filtros mb-3 shadow-sm text-start">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-12 col-md-5 col-lg-4">
                    <label class="form-label-custom small">VENDEDOR</label>
                    <select id="cmb-vendedor-cc" class="form-control"></select>
                </div>
                <div class="col-12 col-md-3 col-lg-3">
                    <label class="form-label-custom small">MOVIMIENTOS DESDE</label>
                    <input type="date" id="txt-fecha-desde" class="form-control bg-dark text-white border-secondary">
                </div>
                <div class="col-12 col-md-4 col-lg-5 text-md-end text-center mt-4 mt-md-0">
                    <div class="d-inline-block p-2 px-4 rounded bg-dark border border-secondary shadow-sm">
                        <small class="text-muted d-block text-uppercase" style="font-size: 10px;">SALDO ACTUAL</small>
                        <span id="lbl-saldo-actual" class="fw-bold fs-4 text-white">$ 0.00</span>
                    </div>
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
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 60vh; min-height: 400px;">
            <table class="table table-hover align-middle mb-0 small" id="tabla-ccv">
                <thead class="sticky-top">
                    <tr>
                        <th class="ps-3" style="width: 120px;">FECHA</th>
                        <th>DETALLE / TABLA</th>
                        <th>Nº COMPROBANTE</th>
                        <th class="text-end text-info">GANANCIAS (+)</th>
                        <th class="text-end text-warning">PAGOS (-)</th>
                        <th class="text-end text-accent">SALDO ACUM.</th>
                    </tr>
                </thead>
                <tbody id="tbody-ccv">
                    <tr><td colspan="6" class="text-center p-5 text-muted italic">Seleccione un vendedor para ver sus movimientos</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic">Seleccione un vendedor para ver sus movimientos</div>
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
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        configurarSelect2();
        $('#btn-buscar-ccv').click(buscar);
        $('#btn-export-ccv').click(exportarExcel);
        $('#txt-fecha-desde').on('change', function() {
            if ($('#cmb-vendedor-cc').val()) buscar();
        });
    }

    function configurarSelect2() {
        $('#cmb-vendedor-cc').select2({
            placeholder: 'BUSCAR VENDEDOR...',
            dropdownParent: $('#main-content'),
            allowClear: true,
            ajax: {
                url: 'api_vendedores_cta_cte.php?action=get_vendedores', 
                dataType: 'json', delay: 250, headers: commonHeaders,
                processResults: d => ({ 
                    results: (d.data || []).map(v => ({ id: v.id, text: v.nombre })) 
                })
            },
            width: '100%'
        }).on('select2:select', buscar);
    }

    async function buscar() {
        const idVende = $('#cmb-vendedor-cc').val();
        if (!idVende || idVende === "") return;

        $('#tbody-ccv').html('<tr><td colspan="6" class="text-center p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</div>');
        $('#lbl-saldo-actual').text('$ 0.00').removeClass('text-danger text-success');

        const payload = { 
            vendedor_id: idVende,
            fecha_desde: $('#txt-fecha-desde').val()
        };

        try {
            const response = await fetch('api_vendedores_cta_cte.php?action=list', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const res = await response.json();

            if (res.success) {
                let dataBruta = res.data || [];
                let saldoAnteriorValue = parseFloat(res.saldoAnterior) || 0;
                
                // 1. Calcular Saldo Final (último de la lista o saldo anterior si está vacía)
                let saldoFinal = saldoAnteriorValue;
                if (dataBruta.length > 0) {
                    const ultimo = dataBruta[dataBruta.length - 1];
                    saldoFinal = parseFloat(ultimo.Saldo || ultimo.saldo || 0);
                }
                
                const saldoFmt = saldoFinal.toLocaleString('es-AR', {minimumFractionDigits: 2});
                $('#lbl-saldo-actual').text('$ ' + saldoFmt)
                    .removeClass('text-danger text-success')
                    .addClass(saldoFinal > 0 ? 'text-success' : (saldoFinal < 0 ? 'text-danger' : ''));

                // 2. Inyectar "Saldo Anterior" como objeto inicial en memoria si hay filtro de fecha
                listado = [];
                if ($('#txt-fecha-desde').val()) {
                    listado.push({
                        isSaldoAnterior: true,
                        Fecha: '-',
                        Detalle: 'SALDO ANTERIOR ACUMULADO',
                        Comprobante: '-',
                        Ganancias: 0,
                        Pagos: 0,
                        Saldo: saldoAnteriorValue
                    });
                }
                
                listado = listado.concat(dataBruta);

                pagActual = 1; window.pagActual = 1;
                renderPagina();

            } else {
                $('#tbody-ccv').html(`<tr><td colspan="6" class="text-center p-5 text-danger">${res.error || 'Error desconocido'}</td></tr>`);
                $('#tblCards').html(`<div class="text-center p-5 text-danger">${res.error || 'Error desconocido'}</div>`);
            }
        } catch (e) { 
            console.error("Error en Fetch/Parse:", e);
            $('#tbody-ccv').html(`<tr><td colspan="6" class="text-center p-5 text-danger">Error al procesar respuesta: ${e.message}</td></tr>`);
            $('#tblCards').html(`<div class="text-center p-5 text-danger">Error de comunicación</div>`);
        }
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
        const tbody = $('#tbody-ccv').empty();

        if (datos.length === 0) {
            tbody.append('<tr><td colspan="6" class="text-center p-5 text-muted opacity-50 italic">No hay movimientos registrados</td></tr>');
            return;
        }

        datos.forEach((m) => {
            if (m.isSaldoAnterior) {
                const colorSA = m.Saldo >= 0 ? 'text-accent' : 'text-danger';
                tbody.append(`
                    <tr class="bg-dark bg-opacity-50 fw-bold border-bottom border-secondary">
                        <td class="ps-3 text-muted italic">-</td>
                        <td class="text-uppercase text-info">SALDO ANTERIOR ACUMULADO</td>
                        <td class="text-muted">-</td>
                        <td class="text-end text-info">-</td>
                        <td class="text-end text-warning">-</td>
                        <td class="text-end ${colorSA}">$ ${m.Saldo.toFixed(2)}</td>
                    </tr>
                `);
            } else {
                const ganancias = parseFloat(m.Ganancias || m.ganancias || 0);
                const pagos = parseFloat(m.Pagos || m.pagos || 0);
                const saldo = parseFloat(m.Saldo || m.saldo || 0);
                const saldoColor = saldo >= 0 ? 'text-accent' : 'text-danger';
                
                tbody.append(`
                    <tr>
                        <td class="ps-3">${m.Fecha || m.fecha || '-'}</td>
                        <td><div class="text-truncate text-uppercase text-white" style="max-width:250px;">${m.Detalle || m.detalle || '-'}</div></td>
                        <td class="fw-bold">${m.Comprobante || m.compasoc || '-'}</td>
                        <td class="text-end text-info fw-bold">${ganancias > 0 ? '$ ' + ganancias.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end text-warning fw-bold">${pagos > 0 ? '$ ' + pagos.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end fw-bold ${saldoColor}">$ ${saldo.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `);
            }
        });
    }

    function renderTarjetas(datos) {
        const wrap = $('#tblCards').empty();

        if (datos.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No hay movimientos registrados</div>');
            return;
        }

        datos.forEach((m) => {
            if (m.isSaldoAnterior) {
                const colorSA = m.Saldo >= 0 ? 'text-accent' : 'text-danger';
                wrap.append(`
                <div class="erp-card" style="border-color: rgba(0,255,255,0.3); background: rgba(0,255,255,0.04);">
                    <div class="erp-card-header mb-1">
                        <div class="erp-card-title text-info" style="font-size: 11px;">SALDO ANTERIOR</div>
                        <span class="erp-card-code bg-dark border-secondary">-</span>
                    </div>
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="erp-card-label">Acumulado</span>
                        <span class="erp-card-value price fs-6 ${colorSA}">$ ${m.Saldo.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>`);
            } else {
                const ganancias = parseFloat(m.Ganancias || m.ganancias || 0);
                const pagos = parseFloat(m.Pagos || m.pagos || 0);
                const saldo = parseFloat(m.Saldo || m.saldo || 0);
                const saldoColor = saldo >= 0 ? 'text-accent' : 'text-danger';

                let html = `
                <div class="erp-card">
                    <div class="erp-card-header mb-1">
                        <div>
                            <div class="erp-card-title text-truncate text-white" style="max-width: 200px; font-size:12px;">${m.Detalle || m.detalle || '-'}</div>
                            <small class="text-muted fw-bold" style="font-size:10px;">${m.Comprobante || m.compasoc || '-'}</small>
                        </div>
                        <span class="erp-card-code">${m.Fecha || m.fecha || '-'}</span>
                    </div>
                    ${ganancias > 0 ? `<div class="erp-card-row"><span class="erp-card-label text-info">Ganancias (+)</span><span class="erp-card-value text-info fw-bold">$ ${ganancias.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    ${pagos > 0 ? `<div class="erp-card-row"><span class="erp-card-label text-warning">Pagos (-)</span><span class="erp-card-value text-warning fw-bold">$ ${pagos.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="erp-card-label">Saldo Acum.</span>
                        <span class="erp-card-value price fs-6 ${saldoColor}">$ ${saldo.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>`;
                wrap.append(html);
            }
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> movimientos`;
        
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

    // --- EXPORTACIÓN EXCEL NATIVA (JS) ---
    function exportarExcel() {
        const idVende = $('#cmb-vendedor-cc').val();
        if (!idVende || listado.length === 0) {
            return Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay datos cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        }

        const nombreVendedor = $('#cmb-vendedor-cc option:selected').text();
        const fDesde = $('#txt-fecha-desde').val();
        const fechaFormat = fDesde ? fDesde.split('-').reverse().join('/') : 'INICIO DE OPERACIONES';

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
                    .saldo-inicial { background-color: #f8f9fa; font-weight: bold; border: 0.5pt solid #ccc; }
                    .ganancia { color: #17a2b8; font-weight: bold; text-align: right; border: 0.5pt solid #ccc; }
                    .pago { color: #ffc107; font-weight: bold; text-align: right; border: 0.5pt solid #ccc; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="6" class="title">CUENTA CORRIENTE VENDEDOR: ${nombreVendedor.toUpperCase()}</td></tr>
                    <tr><td colspan="6">MOVIMIENTOS DESDE: ${fechaFormat}</td></tr>
                    <tr><td colspan="6"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">FECHA</th>
                            <th class="header">DETALLE / TABLA</th>
                            <th class="header">Nº COMPROBANTE</th>
                            <th class="header">GANANCIAS (+)</th>
                            <th class="header">PAGOS (-)</th>
                            <th class="header">SALDO ACUM.</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        listado.forEach(m => {
            if (m.isSaldoAnterior) {
                excelHtml += `
                    <tr>
                        <td class="date">-</td>
                        <td class="saldo-inicial" colspan="2" style="text-align:right;">SALDO ANTERIOR ACUMULADO:</td>
                        <td class="number saldo-inicial"></td>
                        <td class="number saldo-inicial"></td>
                        <td class="number saldo-inicial" style="color: ${m.Saldo >= 0 ? '#006666' : '#cc0000'};">${m.Saldo}</td>
                    </tr>
                `;
            } else {
                const ganancias = parseFloat(m.Ganancias || m.ganancias || 0);
                const pagos = parseFloat(m.Pagos || m.pagos || 0);
                const saldo = parseFloat(m.Saldo || m.saldo || 0);
                const colorSaldo = saldo >= 0 ? '#006666' : '#cc0000';

                excelHtml += `
                    <tr>
                        <td class="date">${m.Fecha || m.fecha || '-'}</td>
                        <td class="text">${(m.Detalle || m.detalle || '-').toUpperCase()}</td>
                        <td class="text" style="font-weight:bold;">${m.Comprobante || m.compasoc || '-'}</td>
                        <td class="ganancia">${ganancias > 0 ? ganancias : ''}</td>
                        <td class="pago">${pagos > 0 ? pagos : ''}</td>
                        <td class="number" style="font-weight:bold; color: ${colorSaldo};">${saldo}</td>
                    </tr>
                `;
            }
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `CtaCte_${nombreVendedor.replace(/\s+/g, '_')}_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>