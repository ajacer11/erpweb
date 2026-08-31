<?php
/**
 * reporte_cajas_usuariosporpuesto.php - REPORTE DE SESIONES DE CAJA
 * Muestra qué usuario operó cada caja, fechas de apertura/cierre y saldos.
 */
require_once 'check_access.php';

// Obtenemos todos los puestos de la tabla 'cajas' para armar el filtro
try {
    $stmtCajas = $pdo->query("SELECT id, descripcion FROM cajas ORDER BY descripcion ASC");
    $listaCajas = $stmtCajas->fetchAll();
} catch (Exception $e) {
    $listaCajas = [];
}
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloUsuariosPuesto">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-users-gear text-accent me-2"></i>USUARIOS POR PUESTO</h4>
            <small class="text-accent opacity-75">HISTORIAL DE APERTURAS, CIERRES Y SALDOS POR CAJA</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnConsultar">
                <i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR HISTORIAL
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <form id="formFiltros" class="row g-3 align-items-end" onsubmit="return false;">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">PUESTO</label>
                    <select class="form-select select2-simple trigger-filter" name="id_puesto" id="id_puesto">
                        <option value="">TODOS LOS PUESTOS</option>
                        <?php foreach ($listaCajas as $caja): ?>
                            <option value="<?= $caja['id'] ?>"><?= htmlspecialchars(strtoupper($caja['descripcion'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA DESDE (APERTURA)</label>
                    <input type="date" class="form-control trigger-filter" id="fecha_desde" name="fecha_desde" value="<?= date('Y-m-01') ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">FECHA HASTA (APERTURA)</label>
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
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;" id="tablaHistorial">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3">PUESTO</th>
                    <th class="text-white">USUARIO</th>
                    <th class="text-white">APERTURA</th>
                    <th class="text-white">CIERRE</th>
                    <th class="text-white">ESTADO</th>
                    <th class="text-end text-white">SALDO INICIAL</th>
                    <th class="text-end text-accent fw-bold pe-3">SALDO FINAL / ACTUAL</th>
                </tr>
            </thead>
            <tbody id="tbodyHistorial">
                <tr>
                    <td colspan="7" class="text-center py-5 text-muted fst-italic">
                        INGRESE UN RANGO DE FECHAS Y PRESIONE BUSCAR
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 fst-italic">INGRESE UN RANGO DE FECHAS Y PRESIONE BUSCAR</div>
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
    const tbody = document.getElementById('tbodyHistorial');
    const cards = document.getElementById('tblCards');
    const btnExportar = document.getElementById('btnExportar');

    let debounceTimer = null;
    let currentData = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    // Inicializar Select2
    $('.select2-simple').select2({
        theme: 'default',
        width: '100%',
        dropdownParent: $('#main-content')
    });

    async function cargarReporte() {
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        const btn = document.getElementById('btnConsultar');
        
        try {
            btn.disabled = true;
            btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
            cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';

            const resp = await fetch(`api_reporte_cajas_usuariosporpuesto.php?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            
            const result = await resp.json();

            if (result.status === 'error') throw new Error(result.error);

            currentData = result.data || [];
            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            Swal.fire({ icon: 'error', title: 'ERROR', text: error.message, background: '#200', color: '#fff' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-1"></i> BUSCAR HISTORIAL';
        }
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

        if (!data || data.length === 0) {
            html = '<tr><td colspan="7" class="text-center py-4 text-muted">NO SE REGISTRARON APERTURAS EN ESTE PERÍODO</td></tr>';
        } else {
            data.forEach(s => {
                const sInicial = parseFloat(s.saldoinicial || 0);
                const sFinal = parseFloat(s.saldofinal || 0);
                const badgeClass = s.condicion === 'ABIERTA' ? 'bg-success text-white' : 'bg-secondary text-light';
                const cierreTexto = s.cerradael ? formatearFechaHora(s.cerradael) : '<span class="text-warning">EN CURSO</span>';

                html += `
                    <tr>
                        <td class="fw-bold text-white text-uppercase ps-3">${s.puesto}</td>
                        <td><span class="text-accent fw-bold text-uppercase"><i class="fa-solid fa-user me-2"></i>${s.usuario}</span></td>
                        <td class="text-white">${formatearFechaHora(s.abiertael)}</td>
                        <td class="text-white">${cierreTexto}</td>
                        <td><span class="badge ${badgeClass} px-2 py-1 rounded-pill">${s.condicion}</span></td>
                        <td class="text-end fw-bold text-white">$ ${sInicial.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                        <td class="text-end fw-bold text-info pe-3">$ ${sFinal.toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `;
            });
        }
        tbody.innerHTML = html;
    }

    function renderTarjetas(data) {
        let html = '';

        if (!data || data.length === 0) {
            html = '<div class="text-center text-muted p-5 opacity-50 fst-italic">NO SE REGISTRARON APERTURAS EN ESTE PERÍODO</div>';
        } else {
            data.forEach(s => {
                const sInicial = parseFloat(s.saldoinicial || 0);
                const sFinal = parseFloat(s.saldofinal || 0);
                const badgeStyle = s.condicion === 'ABIERTA' 
                    ? 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;' 
                    : 'background:rgba(108,117,125,.2);color:#adb5bd;border:1px solid #6c757d;';
                const cierreTexto = s.cerradael ? formatearFechaHora(s.cerradael) : '<span class="text-warning fw-bold">EN CURSO</span>';

                html += `
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-white text-uppercase">${s.puesto}</div>
                        <span class="erp-card-badge" style="${badgeStyle}">${s.condicion}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Usuario</span>
                        <span class="erp-card-value text-accent fw-bold text-uppercase"><i class="fa-solid fa-user me-1"></i>${s.usuario}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Apertura</span><span class="erp-card-value text-white">${formatearFechaHora(s.abiertael)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Cierre</span><span class="erp-card-value text-white">${cierreTexto}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Saldo Inicial</span><span class="erp-card-value text-white">$ ${sInicial.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span></div>
                    <div class="erp-card-row mt-2 pt-2 border-top border-secondary border-opacity-25">
                        <span class="erp-card-label text-info fw-bold">SALDO FINAL / ACTUAL</span>
                        <span class="erp-card-value text-info fw-bold fs-6">$ ${sFinal.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                    </div>
                </div>`;
            });
        }
        cards.innerHTML = html;
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> sesiones`;

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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloUsuariosPuesto')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatearFechaHora(datetime) {
        if(!datetime) return '-';
        const [fecha, hora] = datetime.split(' ');
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y} ${hora.substring(0, 5)}`;
    }

    function formatearFechaInput(f) {
        if(!f) return '';
        const parts = f.split('-');
        return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }

    function generarExcel(data) {
        const fechaDesde = document.getElementById('fecha_desde').value;
        const fechaHasta = document.getElementById('fecha_hasta').value;
        const selectPuesto = document.getElementById('id_puesto');
        const nombrePuesto = selectPuesto.options[selectPuesto.selectedIndex].text;

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
                    .open { color: #28a745; font-weight: bold; border: 0.5pt solid #ccc; text-align: center;}
                    .closed { color: #6c757d; font-weight: bold; border: 0.5pt solid #ccc; text-align: center;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="7" class="title">REPORTE DE USUARIOS POR PUESTO (SESIONES DE CAJA)</td></tr>
                    <tr><td colspan="7">PUESTO FILTRADO: ${nombrePuesto}</td></tr>
                    <tr><td colspan="7">RANGO DE APERTURAS: ${formatearFechaInput(fechaDesde)} AL ${formatearFechaInput(fechaHasta)}</td></tr>
                    <tr><td colspan="7"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">PUESTO</th>
                            <th class="header">USUARIO</th>
                            <th class="header">FECHA APERTURA</th>
                            <th class="header">FECHA CIERRE</th>
                            <th class="header">ESTADO</th>
                            <th class="header">SALDO INICIAL</th>
                            <th class="header">SALDO FINAL / ACTUAL</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        data.forEach(s => {
            const sInicial = parseFloat(s.saldoinicial || 0);
            const sFinal = parseFloat(s.saldofinal || 0);
            const estadoClase = s.condicion === 'ABIERTA' ? 'open' : 'closed';
            const cierreTexto = s.cerradael ? formatearFechaHora(s.cerradael) : 'EN CURSO';
            
            excelHtml += `
                <tr>
                    <td class="text">${(s.puesto || '').toUpperCase()}</td>
                    <td class="text">${(s.usuario || '').toUpperCase()}</td>
                    <td class="date">${formatearFechaHora(s.abiertael)}</td>
                    <td class="date">${cierreTexto}</td>
                    <td class="${estadoClase}">${s.condicion}</td>
                    <td class="number">${sInicial}</td>
                    <td class="number">${sFinal}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Usuarios_Por_Puesto_${new Date().getTime()}.xls`;
        link.click();
    }

    // Debounce de 800ms para filtros
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
        if(currentData.length > 0) generarExcel(currentData);
        else Swal.fire('Atención', 'Primero realice una consulta con resultados para exportar.', 'warning');
    });

    // Carga inicial
    cargarReporte();

})();
</script>