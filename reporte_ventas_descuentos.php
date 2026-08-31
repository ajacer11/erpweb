<?php
// SEGURIDAD CENTRALIZADA
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloDescuentos">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-percent text-accent me-2"></i>DESCUENTOS REALIZADOS</h4>
            <small class="text-accent opacity-75">CONTROL DE DESCUENTOS APLICADOS EN VENTAS</small>
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
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label-custom">DESDE</label>
                <input type="date" id="txtDesde" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-01') ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label-custom">HASTA</label>
                <input type="date" id="txtHasta" class="form-control form-control-sm filter-auto" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-12 col-md-3">
                <div class="form-check form-switch pt-md-3">
                    <input class="form-check-input filter-auto-force" type="checkbox" id="chkDetalles">
                    <label class="form-check-label text-white fw-bold" for="chkDetalles">VER DETALLES COMPLETOS</label>
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
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap" id="tablaDescuentos">
            <thead class="sticky-top bg-black">
                <tr id="headerRow">
                    <!-- Se llena dinámicamente según el modo -->
                </tr>
            </thead>
            <tbody id="tbodyDescuentos">
                <tr><td colspan="15" class="text-center py-5 text-muted">PRESIONE "CONSULTAR" PARA VER DATOS</td></tr>
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
</div>

<script>
(function() {
    let debounceTimer = null;
    let currentData = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;

    window.pagActual = 1; window.pagTotal = 1;

    // Inicialización de Listeners
    document.getElementById('btnConsultar').addEventListener('click', cargarInformeForce);
    
    // Checkbox ejecuta búsqueda inmediata
    document.getElementById('chkDetalles').addEventListener('change', cargarInformeForce);

    // Inputs de fecha con Debounce
    $('.filter-auto').on('input change', function () {
        clearTimeout(debounceTimer);
        const desde = $('#txtDesde').val();
        const hasta = $('#txtHasta').val();
        
        if (desde && parseInt(desde.split('-')[0]) < 2000) return;
        if (hasta && parseInt(hasta.split('-')[0]) < 2000) return;

        debounceTimer = setTimeout(() => { cargarInforme(); }, 800);
    });

    function cargarInformeForce() {
        clearTimeout(debounceTimer);
        cargarInforme();
    }

    async function cargarInforme() {
        const btn = document.getElementById('btnConsultar');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';

        const tbody = document.getElementById('tbodyDescuentos');
        const thead = document.getElementById('headerRow');
        const cards = document.getElementById('tblCards');
        
        tbody.innerHTML = '<tr><td colspan="15" class="text-center py-5"><i class="fas fa-spinner fa-spin me-2"></i>Generando informe...</td></tr>';
        cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin mb-2 text-info fs-3"></i><br>Generando...</div>';
        thead.innerHTML = ''; // Limpiamos headers antiguos

        const payload = {
            desde: document.getElementById('txtDesde').value,
            hasta: document.getElementById('txtHasta').value,
            ver_detalles: document.getElementById('chkDetalles').checked
        };

        try {
            const resp = await fetch('api_reporte_ventas_descuentos.php?action=generate_report', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();

            if (res.status !== 'ok') throw new Error(res.error || 'Error desconocido');

            currentData = res.data || [];
            
            pagActual = 1; window.pagActual = 1;
            renderPagina();

        } catch (error) {
            console.error(error);
            tbody.innerHTML = '<tr><td colspan="15" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
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
        const thead = document.getElementById('headerRow');
        const tbody = document.getElementById('tbodyDescuentos');
        
        tbody.innerHTML = '';
        thead.innerHTML = '';

        if (!currentData || currentData.length === 0) {
            tbody.innerHTML = '<tr><td colspan="15" class="text-center py-5 text-muted">NO SE ENCONTRARON REGISTROS</td></tr>';
            return;
        }

        // 1. Generar Headers Dinámicos basados en la primera fila de TODO el dataset
        const keys = Object.keys(currentData[0]);
        keys.forEach(k => {
            const th = document.createElement('th');
            th.className = "text-white text-nowrap px-3 py-2";
            th.innerText = k.replace(/_/g, ' ').toUpperCase();
            
            if (['Neto', 'IVA', 'Total_Comp', 'Total_Descuento', 'Cantidad', 'Porc_Descuento'].includes(k)) {
                th.className += " text-end";
            }
            thead.appendChild(th);
        });

        // 2. Generar Filas de la página actual
        if (!data || data.length === 0) return;

        data.forEach(r => {
            const tr = document.createElement('tr');
            
            keys.forEach(k => {
                const td = document.createElement('td');
                td.className = "px-3 small";
                
                let val = r[k];
                if (['Neto', 'IVA', 'Total_Comp', 'Total_Descuento'].includes(k)) {
                    td.innerText = '$ ' + formatMoney(val);
                    td.className += " text-end fw-bold text-accent";
                } else if (['Cantidad', 'Porc_Descuento'].includes(k)) {
                    td.innerText = k === 'Porc_Descuento' ? formatMoney(val) + ' %' : val;
                    td.className += " text-end text-warning";
                } else if (k === 'Fecha') {
                    td.innerText = formatDate(val);
                } else {
                    td.innerText = val || '-';
                }
                tr.appendChild(td);
            });

            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!currentData || currentData.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50 italic">No se encontraron registros.</div>';
            return;
        }

        // Obtenemos las claves dinámicas
        const keys = Object.keys(currentData[0]);

        data.forEach(r => {
            // Lógica para detectar el título y subtítulo dinámicamente según la data devuelta
            let titleField = keys.find(k => k.toLowerCase().includes('cliente')) || keys.find(k => k.toLowerCase().includes('descripcion')) || keys[0];
            let codeField = keys.find(k => k.toLowerCase().includes('numero')) || keys.find(k => k.toLowerCase().includes('comprobante')) || keys.find(k => k.toLowerCase() === 'tipo') || '';

            let titleVal = r[titleField] || 'DESCUENTO APLICADO';
            let codeVal = codeField ? (r[codeField] || '') : '';

            let html = `
            <div class="erp-card mb-2">
                <div class="erp-card-header mb-1">
                    <div class="erp-card-title text-truncate" style="max-width: 250px;">${titleVal}</div>
                    ${codeVal ? `<span class="erp-card-code text-info">${codeVal}</span>` : ''}
                </div>
            `;

            // Renderizar el resto de las columnas como filas
            keys.forEach(k => {
                // Evitamos repetir el título principal para que quede limpio
                if (k === titleField) return;

                let val = r[k];
                let valFormatted = val;
                let valClass = "erp-card-value text-truncate";

                if (['Neto', 'IVA', 'Total_Comp', 'Total_Descuento'].includes(k)) {
                    valFormatted = '$ ' + formatMoney(val);
                    valClass += " text-accent fw-bold fs-6";
                } else if (['Cantidad', 'Porc_Descuento'].includes(k)) {
                    valFormatted = k === 'Porc_Descuento' ? formatMoney(val) + ' %' : val;
                    valClass += k === 'Porc_Descuento' ? " text-warning" : "";
                } else if (k === 'Fecha') {
                    valFormatted = formatDate(val);
                } else {
                    valFormatted = val || '-';
                }

                html += `
                <div class="erp-card-row">
                    <span class="erp-card-label">${k.replace(/_/g, ' ').toUpperCase()}</span>
                    <span class="${valClass}" style="max-width: 65%;" title="${val || ''}">${valFormatted}</span>
                </div>`;
            });

            html += `</div>`;
            wrap.innerHTML += html;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloDescuentos')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };


    // Exportación
    document.getElementById('btnExportar').addEventListener('click', async () => {
        const btn = document.getElementById('btnExportar');
        const originalContent = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> GENERANDO...';

        const payload = {
            desde: document.getElementById('txtDesde').value,
            hasta: document.getElementById('txtHasta').value,
            ver_detalles: document.getElementById('chkDetalles').checked
        };

        try {
            const resp = await fetch('api_reporte_ventas_descuentos.php?action=export', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();
            
            if (res.status === 'ok') {
                const link = document.createElement('a');
                link.href = 'data:application/vnd.ms-excel;base64,' + res.file_content;
                link.download = res.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } else {
                throw new Error(res.error || 'Error al exportar');
            }
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    });

    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDate(dateString) {
        if (!dateString) return '';
        const parts = dateString.split(' ')[0].split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateString;
    }

})();
</script>