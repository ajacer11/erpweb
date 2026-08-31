<?php
/**
 * reporte_cajas_central.php - REPORTE DE MOVIMIENTOS DE CAJA CENTRAL
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloCajaCentral">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-building-columns text-accent me-2"></i>REPORTE DE CAJA CENTRAL</h4>
            <small class="text-accent opacity-75">CONSULTA DE MOVIMIENTOS Y SALDOS ACUMULADOS</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA DESDE</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_desde" name="fecha_desde" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA HASTA</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_hasta" name="fecha_hasta" value="<?= date('Y-m-d') ?>">
                </div>
            </form>
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

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;" id="tablaCaja">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3" style="width: 100px;">FECHA</th>
                    <th class="text-white">DETALLE / OBSERVACIONES</th>
                    <th class="text-white">COMP.</th>
                    <th class="text-end text-white" style="width: 140px;">INGRESOS</th>
                    <th class="text-end text-white" style="width: 140px;">EGRESOS</th>
                    <th class="text-end text-accent fw-bold pe-3" style="width: 140px;">SALDO</th>
                </tr>
            </thead>
            <tbody id="tbodyMovimientos">
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted fst-italic">
                        INGRESE EL RANGO DE FECHAS PARA CARGAR EL REPORTE
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">INGRESE EL RANGO DE FECHAS PARA CARGAR EL REPORTE</div>
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
    const form = document.getElementById('formFiltros');
    const tbody = document.getElementById('tbodyMovimientos');
    const cards = document.getElementById('tblCards');
    const btnExportar = document.getElementById('btnExportar');

    let debounceTimer = null;
    let currentData = [];
    let globalSaldoAnterior = 0;
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;
    let cachedRawData = null;

    async function cargarReporte() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const btn = document.getElementById('btnConsultar');
        
        try {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_caja_central.php?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error);

            cachedRawData = result.data;
            procesarSaldos(result.data);

            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> BUSCAR';
        }
    }

    function procesarSaldos(data) {
        globalSaldoAnterior = parseFloat(data.saldo_anterior || 0);
        let saldoActual = globalSaldoAnterior;

        currentData = (data.movimientos || []).map(m => {
            const debe = parseFloat(m.debe || 0);
            const haber = parseFloat(m.haber || 0);
            saldoActual += (debe - haber);
            return {
                ...m,
                debe_calc: debe,
                haber_calc: haber,
                saldo_acumulado: saldoActual
            };
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
    }

    function renderTabla(data) {
        let html = '';

        if (pagActual === 1) {
            html += `
                <tr class="table-active" style="background-color: rgba(255, 255, 255, 0.05);">
                    <td class="ps-3 text-white-50 small">${formatearFechaInput(document.getElementById('fecha_desde').value)}</td>
                    <td colspan="2" class="fw-bold text-accent">SALDO ANTERIOR ACUMULADO</td>
                    <td></td>
                    <td></td>
                    <td class="text-end fw-bold text-white pe-3">$ ${globalSaldoAnterior.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                </tr>
            `;
        }

        if (!data || data.length === 0) {
            if (pagActual === 1 && currentData.length === 0) {
                html += '<tr><td colspan="6" class="text-center py-4 text-muted">SIN MOVIMIENTOS EN ESTE PERÍODO</td></tr>';
            }
        } else {
            data.forEach(m => {
                html += `
                    <tr>
                        <td class="ps-3 text-white">${formatearFecha(m.fecha)}</td>
                        <td>
                            <div class="fw-bold text-white text-uppercase text-truncate" style="max-width: 350px;">${m.observaciones || 'S/D'}</div>
                            <small class="text-white-50 text-uppercase">USUARIO: ${m.usuario || 'SISTEMA'}</small>
                        </td>
                        <td class="text-white-50 small">${m.compasoc || '-'}</td>
                        <td class="text-end text-success fw-bold">${m.debe_calc > 0 ? '$ ' + m.debe_calc.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end text-danger fw-bold">${m.haber_calc > 0 ? '$ ' + m.haber_calc.toLocaleString('es-AR', {minimumFractionDigits: 2}) : '-'}</td>
                        <td class="text-end fw-bold text-accent pe-3">$ ${m.saldo_acumulado.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        }
        tbody.innerHTML = html;
    }

    function renderTarjetas(data) {
        let html = '';

        if (pagActual === 1) {
            html += `
            <div class="erp-card mb-2" style="border-left: 4px solid var(--accent);">
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">SALDO ANTERIOR ACUMULADO</div>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatearFechaInput(document.getElementById('fecha_desde').value)}</span></div>
                <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                    <span class="erp-card-label text-white fw-bold">SALDO ACUMULADO</span>
                    <span class="erp-card-value text-white fw-bold fs-6">$ ${globalSaldoAnterior.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                </div>
            </div>`;
        }

        if (!data || data.length === 0) {
            if (pagActual === 1 && currentData.length === 0) {
                html += '<div class="text-center text-muted p-5 opacity-50 fst-italic">SIN MOVIMIENTOS EN ESTE PERÍODO</div>';
            }
        } else {
            data.forEach(m => {
                const badgeStyle = m.debe_calc > 0 
                    ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' 
                    : (m.haber_calc > 0 ? 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;' : '');

                html += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title text-truncate" style="max-width: 240px;">${m.observaciones || 'S/D'}</div>
                            <small class="text-white-50"><i class="fa-solid fa-user me-1"></i> ${m.usuario || 'SISTEMA'}</small>
                        </div>
                        <span class="erp-card-badge" style="${badgeStyle}">${m.debe_calc > 0 ? 'INGRESO' : 'EGRESO'}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatearFecha(m.fecha)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">N° Comp.</span><span class="erp-card-value">${m.compasoc || '-'}</span></div>
                    ${m.debe_calc > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Ingreso</span><span class="erp-card-value text-success fw-bold">$ ${m.debe_calc.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    ${m.haber_calc > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Egreso</span><span class="erp-card-value text-danger fw-bold">$ ${m.haber_calc.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>` : ''}
                    <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                        <span class="erp-card-label text-accent fw-bold">SALDO</span>
                        <span class="erp-card-value text-accent fw-bold fs-6">$ ${m.saldo_acumulado.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>`;
            });
        }
        cards.innerHTML = html;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloCajaCentral')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatearFecha(f) {
        if(!f) return '-';
        const [y, m, d] = f.split(' ')[0].split('-');
        return `${d}/${m}/${y}`;
    }

    function formatearFechaInput(f) {
        if(!f) return '';
        const parts = f.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function generarExcel(data) {
        if(!data || !data.movimientos) return;
        const nombreCuenta = data.nombre_cuenta || "Caja Central";
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 16pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .date { text-align: center; border: 0.5pt solid #ccc; mso-number-format:"Short Date"; }
                    .saldo-inicial { background-color: #f8f9fa; font-weight: bold; border: 0.5pt solid #ccc; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="6" class="title">REPORTE DE CAJA CENTRAL - ${nombreCuenta.toUpperCase()}</td></tr>
                    <tr><td colspan="6">PERIODO: ${formatearFechaInput(fechaDesde)} AL ${formatearFechaInput(fechaHasta)}</td></tr>
                    <tr><td colspan="6"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">FECHA</th>
                            <th class="header">DETALLE / OBSERVACIONES</th>
                            <th class="header">COMPROBANTE</th>
                            <th class="header">INGRESOS</th>
                            <th class="header">EGRESOS</th>
                            <th class="header">SALDO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        let saldo = parseFloat(data.saldo_anterior);
        excelHtml += `
            <tr>
                <td class="date">${formatearFechaInput(fechaDesde)}</td>
                <td colspan="2" class="saldo-inicial">SALDO ANTERIOR ACUMULADO</td>
                <td class="number"></td>
                <td class="number"></td>
                <td class="number" style="font-weight:bold;">${saldo}</td>
            </tr>
        `;

        data.movimientos.forEach(m => {
            const d = parseFloat(m.debe || 0);
            const h = parseFloat(m.haber || 0);
            saldo += (d - h);
            excelHtml += `
                <tr>
                    <td class="date">${formatearFecha(m.fecha)}</td>
                    <td class="text">${(m.observaciones || 'S/D').toUpperCase()}</td>
                    <td class="text">${m.compasoc || '-'}</td>
                    <td class="number">${d > 0 ? d : ''}</td>
                    <td class="number">${h > 0 ? h : ''}</td>
                    <td class="number" style="color: #006666; font-weight:bold;">${saldo}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Caja_Central_${nombreCuenta.replace(/\s+/g, '_')}_${new Date().getTime()}.xls`;
        link.click();
    }

    // Debounce de 800ms para filtros [cite: 8, 15]
    $('.trigger-filter').on('input change', function () {
        clearTimeout(debounceTimer);
        const d = document.getElementById('fecha_desde').value;
        const h = document.getElementById('fecha_hasta').value;
        if ((d && parseInt(d.split('-')[0]) < 2000) || (h && parseInt(h.split('-')[0]) < 2000)) return;
        
        debounceTimer = setTimeout(() => cargarReporte(), 800);
    });

    document.getElementById('btnConsultar').addEventListener('click', () => {
        clearTimeout(debounceTimer);
        cargarReporte();
    });

    btnExportar.addEventListener('click', () => {
        if(cachedRawData) generarExcel(cachedRawData);
        else Swal.fire('Atención', 'Primero realice una consulta para exportar.', 'warning');
    });

})();
</script>