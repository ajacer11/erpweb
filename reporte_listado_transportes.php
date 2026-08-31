<?php 
/**
 * reporte_listado_transportes.php - LISTADO DE TRANSPORTES
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php'; 
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloListadoTransportes">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-truck text-accent me-2"></i>LISTADO DE TRANSPORTES</h4>
            <small class="text-accent opacity-75">LOGÍSTICA Y ENVÍOS</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
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

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tablaListado" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr id="headerRow"></tr>
            </thead>
            <tbody id="tbodyListado">
                <tr><td class="text-center py-5 text-muted">CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">CARGANDO...</div>
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
        const ENTITY = 'transportes';
        let currentData = [];
        let pagActual = 1, pagTotal = 1, pagTam = 20;
        window.pagActual = 1; window.pagTotal = 1;

        async function cargar() {
            try {
                const resp = await fetch(`api_reportes_listados.php?action=list&entity=${ENTITY}`, { 
                    headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const res = await resp.json();
                if (res.status === 'ok') {
                    currentData = res.data || [];
                    pagActual = 1; window.pagActual = 1;
                    renderPagina();
                } else {
                    throw new Error(res.error);
                }
            } catch (e) { 
                Swal.fire('Error', e.message, 'error'); 
                document.getElementById('tbodyListado').innerHTML = '<tr><td class="text-center py-5 text-danger">ERROR AL CARGAR DATOS</td></tr>';
            }
        }

        function renderPagina() {
            const total = currentData.length;
            pagTotal = Math.max(1, Math.ceil(total / pagTam));
            if (pagActual > pagTotal) pagActual = pagTotal;
            window.pagActual = pagActual; window.pagTotal = pagTotal;

            const datosSegmento = currentData.slice((pagActual - 1) * pagTam, pagActual * pagTam);

            renderTable(datosSegmento);
            renderCards(datosSegmento);
            sincronizarPaginacion(total);
        }

        function renderTable(data) {
            const thead = document.getElementById('headerRow');
            const tbody = document.getElementById('tbodyListado');
            thead.innerHTML = ''; tbody.innerHTML = '';

            if (!currentData || currentData.length === 0) {
                tbody.innerHTML = '<tr><td class="text-center py-5 text-muted">NO HAY DATOS</td></tr>';
                return;
            }

            // Encabezados dinámicos basados en la primera fila
            Object.keys(currentData[0]).forEach(k => {
                const th = document.createElement('th');
                th.className = "text-white px-3";
                th.innerText = k.toUpperCase();
                thead.appendChild(th);
            });

            data.forEach(r => {
                const tr = document.createElement('tr');
                Object.values(r).forEach(v => {
                    const td = document.createElement('td');
                    td.className = "px-3 text-white-50";
                    td.innerText = v || '-';
                    tr.appendChild(td);
                });
                tbody.appendChild(tr);
            });
        }

        function renderCards(data) {
            const wrap = document.getElementById('tblCards');
            wrap.innerHTML = '';

            if (!currentData || currentData.length === 0) {
                wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50">NO HAY DATOS</div>';
                return;
            }

            const keys = Object.keys(currentData[0]);

            data.forEach(r => {
                let cardHtml = `<div class="erp-card mb-2">`;
                cardHtml += `<div class="erp-card-header">
                                <div class="erp-card-title text-accent">${r[keys[1]] || 'SIN NOMBRE'}</div>
                                <span class="erp-card-code text-info">#${r[keys[0]] || '-'}</span>
                             </div>`;
                
                keys.forEach((k, index) => {
                    if (index > 1) {
                        cardHtml += `<div class="erp-card-row">
                                        <span class="erp-card-label">${k.toUpperCase()}</span>
                                        <span class="erp-card-value text-white-50">${r[k] || '-'}</span>
                                     </div>`;
                    }
                });
                
                cardHtml += `</div>`;
                wrap.innerHTML += cardHtml;
            });
        }

        function sincronizarPaginacion(total) {
            const desde = Math.min((pagActual - 1) * pagTam + 1, total || 0);
            const hasta = Math.min(pagActual * pagTam, total);
            const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;

            ['', 'Top'].forEach(sfx => {
                const elInfo = document.getElementById('pagInfo' + sfx);
                if (elInfo) elInfo.innerHTML = info;

                ['btnPagPrim', 'btnPagAnt'].forEach(id => { 
                    const e = document.getElementById(id + sfx); 
                    if (e) e.disabled = pagActual <= 1; 
                });
                ['btnPagSig', 'btnPagUlt'].forEach(id => { 
                    const e = document.getElementById(id + sfx); 
                    if (e) e.disabled = pagActual >= pagTotal; 
                });

                const cont = document.getElementById('pagBotones' + sfx);
                if (cont) {
                    cont.innerHTML = '';
                    if (pagTotal > 1) {
                        let ini = Math.max(1, pagActual - 2);
                        let fin = Math.min(pagTotal, ini + 4);
                        if (fin - ini < 4) ini = Math.max(1, fin - 4);
                        for (let i = ini; i <= fin; i++) {
                            const b = document.createElement('button');
                            b.className = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                            b.textContent = i;
                            b.onclick = () => pagGo(i);
                            cont.appendChild(b);
                        }
                    }
                }
                const ji = document.getElementById('pagJumpInput' + sfx);
                if (ji) { ji.value = pagActual; ji.max = pagTotal; }
                const sel = document.getElementById('pagTamano' + sfx);
                if (sel) sel.value = pagTam;
                
                const wrap = document.getElementById(sfx === 'Top' ? 'pagTopWrap' : 'pagBotWrap');
                if (wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
            });
        }

        window.pagGo = function(n) {
            n = Math.max(1, Math.min(pagTotal, n));
            if (n === pagActual) return;
            pagActual = n; window.pagActual = n;
            renderPagina();
            document.getElementById('moduloListadoTransportes')?.scrollIntoView({ behavior: 'smooth' });
        };

        window.pagJump = function() { 
            const v = parseInt(document.getElementById('pagJumpInput').value); 
            if (!isNaN(v)) pagGo(v); 
        };
        window.pagJumpTop = function() { 
            const v = parseInt(document.getElementById('pagJumpInputTop').value); 
            if (!isNaN(v)) pagGo(v); 
        };
        window.pagCambiarTamano = function() { 
            pagTam = parseInt(document.getElementById('pagTamano').value); 
            pagActual = 1; window.pagActual = 1; 
            renderPagina(); 
        };
        window.pagCambiarTamanoTop = function() { 
            pagTam = parseInt(document.getElementById('pagTamanoTop').value); 
            pagActual = 1; window.pagActual = 1; 
            renderPagina(); 
        };

        document.getElementById('btnExportar').addEventListener('click', async () => {
            const btn = document.getElementById('btnExportar');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> EXPORTANDO...';
            try {
                const resp = await fetch(`api_reportes_listados.php?action=export&entity=${ENTITY}`, { 
                    headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                });
                const res = await resp.json();
                if (res.status === 'ok') {
                    const link = document.createElement('a');
                    link.href = 'data:application/vnd.ms-excel;base64,' + res.file_content;
                    link.download = res.filename;
                    document.body.appendChild(link); link.click(); document.body.removeChild(link);
                }
            } catch (e) { Swal.fire('Error', e.message, 'error'); }
            finally { 
                btn.disabled = false; 
                btn.innerHTML = '<i class="fa-solid fa-file-excel me-2"></i> EXPORTAR';
            }
        });

        cargar();
    })();
</script>