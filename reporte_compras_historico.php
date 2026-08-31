<?php
// SEGURIDAD CENTRALIZADA
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloComprasHistorico">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-history text-accent me-2"></i>PRECIOS HISTÓRICOS</h4>
            <small class="text-accent opacity-75">EVOLUCIÓN DE COSTOS DE COMPRA POR PRODUCTO</small>
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

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">HASTA FECHA</label>
                    <input type="date" id="txtFecha" class="form-control form-control-sm trigger-filter" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label-custom">PRODUCTO</label>
                    <select id="cmbProducto" class="form-select select2-filter trigger-filter"></select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-check form-switch pb-1">
                        <input class="form-check-input trigger-filter" type="checkbox" id="chkDetalles">
                        <label class="form-check-label text-white fw-bold ms-2" for="chkDetalles">VER HISTORIAL COMPLETO</label>
                    </div>
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

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" style="font-size: 0.9rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3">FECHA</th>
                    <th class="text-white">CÓDIGO</th>
                    <th class="text-white">DESCRIPCIÓN</th>
                    <th class="text-end text-white">NETO COSTO</th>
                    <th class="text-end text-white-50">IVA COSTO</th>
                    <th class="text-end text-white-50">IMP. INT.</th>
                    <th class="text-end text-accent fw-bold pe-3">COSTO TOTAL</th>
                </tr>
            </thead>
            <tbody id="tbodyHistorico">
                <tr><td colspan="7" class="text-center py-5 text-muted">SELECCIONE UN PRODUCTO PARA CONSULTAR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">SELECCIONE UN PRODUCTO PARA CONSULTAR</div>
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
    let debounceTimer = null;
    let currentData = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        // Init Select2
        $('#cmbProducto').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'BUSCAR PRODUCTO...',
            allowClear: true,
            minimumInputLength: 0
        });

        // Cargar Productos
        try {
            const resp = await fetch('api_reporte_compras_historico.php?action=get_filters', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            if (data.productos) {
                const el = $('#cmbProducto');
                el.empty().append(new Option('SELECCIONE PRODUCTO...', '', true, true));
                data.productos.forEach(p => {
                    el.append(new Option(p.descripcion, p.codigo, false, false));
                });
            }
        } catch (error) {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los productos.' });
        }

        // Listeners con Debounce
        $('.trigger-filter').on('input change', function () {
            clearTimeout(debounceTimer);
            const fecha = document.getElementById('txtFecha').value;
            // Validar que el año sea correcto si se está tipeando
            if (fecha && parseInt(fecha.split('-')[0]) < 2000) return;
            
            debounceTimer = setTimeout(() => {
                if ($('#cmbProducto').val()) {
                    cargarInforme();
                }
            }, 800);
        });
    }

    function cargarInformeForce() {
        clearTimeout(debounceTimer);
        cargarInforme();
    }

    async function cargarInforme() {
        const prod = $('#cmbProducto').val();
        if (!prod) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un producto.' });
            return;
        }

        const btn = document.getElementById('btnConsultar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> BUSCANDO...';

        const tbody = document.getElementById('tbodyHistorico');
        const cards = document.getElementById('tblCards');
        
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>';
        cards.innerHTML = '<div class="text-center text-muted p-5 opacity-50"><div class="spinner-border text-info"></div></div>';

        const payload = {
            fecha: document.getElementById('txtFecha').value,
            producto_cod: prod,
            ver_detalles: document.getElementById('chkDetalles').checked
        };

        try {
            const resp = await fetch('api_reporte_compras_historico.php?action=generate_report', {
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
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">ERROR AL CONSULTAR DATOS</td></tr>';
            cards.innerHTML = '<div class="text-center text-danger p-5">ERROR AL CONSULTAR DATOS</div>';
            currentData = [];
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> CONSULTAR';
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
        const tbody = document.getElementById('tbodyHistorico');
        tbody.innerHTML = '';

        if (!data || data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">NO SE ENCONTRARON COMPRAS PARA ESTE PRODUCTO EN LA FECHA INDICADA</td></tr>';
            return;
        }

        data.forEach(r => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="ps-3 text-white">${formatDate(r.Fecha)}</td>
                <td class="text-white-50">${r.Codigo}</td>
                <td class="text-truncate" style="max-width: 300px;" title="${r.Descripcion}">${r.Descripcion}</td>
                <td class="text-end text-white">$ ${formatMoney(r.Neto_Costo)}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.Iva_Costo)}</td>
                <td class="text-end text-white-50">$ ${formatMoney(r.Imp_Int)}</td>
                <td class="text-end text-accent fw-bold pe-3">$ ${formatMoney(r.Costo_Total)}</td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!data || data.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50">NO SE ENCONTRARON COMPRAS PARA ESTE PRODUCTO EN LA FECHA INDICADA</div>';
            return;
        }

        data.forEach(r => {
            wrap.innerHTML += `
            <div class="erp-card mb-2">
                <div class="erp-card-header">
                    <div class="erp-card-title text-truncate" style="max-width: 220px;">${r.Descripcion}</div>
                    <span class="erp-card-code text-info">${r.Codigo}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Fecha</span><span class="erp-card-value">${formatDate(r.Fecha)}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Neto Costo</span><span class="erp-card-value text-white">$ ${formatMoney(r.Neto_Costo)}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">IVA Costo</span><span class="erp-card-value" style="color:var(--text-muted);">$ ${formatMoney(r.Iva_Costo)}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Imp. Int.</span><span class="erp-card-value" style="color:var(--text-muted);">$ ${formatMoney(r.Imp_Int)}</span></div>
                <div class="erp-card-row"><span class="erp-card-label">Costo Total</span><span class="erp-card-value price" style="color:var(--accent);">$ ${formatMoney(r.Costo_Total)}</span></div>
            </div>`;
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

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloComprasHistorico')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    document.getElementById('btnConsultar').addEventListener('click', cargarInformeForce);

    document.getElementById('btnExportar').addEventListener('click', async () => {
        const prod = $('#cmbProducto').val();
        if (!prod) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un producto para exportar.' });
            return;
        }

        const btn = document.getElementById('btnExportar');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner-border spinner-border-sm me-2"></div> ...';

        const payload = {
            fecha: document.getElementById('txtFecha').value,
            producto_cod: prod,
            ver_detalles: document.getElementById('chkDetalles').checked
        };

        try {
            const resp = await fetch('api_reporte_compras_historico.php?action=export', {
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
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-file-excel me-2"></i> EXPORTAR';
        }
    });

    function formatMoney(n) { return parseFloat(n).toLocaleString('es-AR', {minimumFractionDigits: 2}); }
    function formatDate(d) { if(!d) return ''; const [y,m,day] = d.split(' ')[0].split('-'); return `${day}/${m}/${y}`; }

    init();
})();
</script>