<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<style>
    /* Estilo para fijar el encabezado de la tabla */
    #tblCtaCtePrv thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #0d2b45 !important; /* Color consistente con el tema oscuro */
        box-shadow: inset 0 -2px 0 var(--accent); /* Mantiene la línea divisoria cian */
    }

    /* Asegura que el contenedor tenga el scroll adecuado */
    .table-container-scroll {
        max-height: 60vh;
        overflow-y: auto;
        border: 1px solid rgba(255,255,255,0.05);
    }
</style>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">CUENTAS CORRIENTES DE PROVEEDORES</h4>
            <small class="text-accent opacity-75">Gestión de deudas y pagos realizados</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 btn-sm" id="btnExportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-danger btn-sm rounded-circle shadow ms-2" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- PANEL DE FILTROS -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <!-- Filtro Proveedor -->
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">PROVEEDOR</label>
                    <select class="form-select select2-simple" id="cmbProv"></select>
                    <div class="mt-2 d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroProv" id="rbPorProv" checked>
                            <label class="form-check-label small" for="rbPorProv">FILTRAR POR PROVEEDOR</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroProv" id="rbVerTodos">
                            <label class="form-check-label small" for="rbVerTodos">VER TODOS</label>
                        </div>
                    </div>
                </div>

                <!-- Filtro Tipo -->
                <div class="col-6 col-md-3">
                    <label class="form-label-custom">FILTRO POR TIPO</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="filtroTipo" id="rbFiscoCualquiera" checked>
                        <label class="form-check-label small" for="rbFiscoCualquiera">VER TODAS</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="filtroTipo" id="rbSoloFiscales">
                        <label class="form-check-label small" for="rbSoloFiscales">SOLO FISCALES</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="filtroTipo" id="rbSoloNoFiscales">
                        <label class="form-check-label small" for="rbSoloNoFiscales">SOLO NO FISCALES</label>
                    </div>
                </div>

                <!-- Filtro Fechas -->
                <div class="col-6 col-md-3">
                    <div class="row g-2">
                        <div class="col-12 col-sm-6">
                            <label class="form-label-custom">DESDE</label>
                            <input type="date" id="dtmDesde" class="form-control" value="<?= date('Y-m-d') ?>" disabled>
                        </div>
                        <div class="col-12 col-sm-6">
                            <label class="form-label-custom">HASTA</label>
                            <input type="date" id="dtmHasta" class="form-control" value="<?= date('Y-m-d') ?>" disabled>
                        </div>
                    </div>
                    <div class="mt-2 d-flex gap-2 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroFecha" id="rbFechaTodas" checked>
                            <label class="form-check-label small" for="rbFechaTodas">TODAS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroFecha" id="rbEntreFechas">
                            <label class="form-check-label small" for="rbEntreFechas">FECHAS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroFecha" id="rbSoloSaldos">
                            <label class="form-check-label small" for="rbSoloSaldos">SALDOS</label>
                        </div>
                    </div>
                </div>

                <!-- Botón ACTUALIZAR -->
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button class="btn btn-info fw-bold w-100" id="btnActualizar">
                        <i class="fa-solid fa-sync-alt me-2"></i>ACTUALIZAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="pagTopWrap" style="display:none;">
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

    <!-- ============================
         TABLA DE RESULTADOS (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-container-scroll shadow-sm card card-filtros p-0 border-0 mb-2">
        <table class="table table-hover align-middle mb-0 small" id="tblCtaCtePrv">
            <thead>
                <tr id="tblHeader">
                    <th class="ps-3">FECHA</th>
                    <th>PROVEEDOR</th>
                    <th>COMPROBANTE</th>
                    <th class="text-end">DEBE (FACT.)</th>
                    <th class="text-end">HABER (PAGOS)</th>
                    <th class="text-end">SALDO</th>
                </tr>
            </thead>
            <tbody id="tblBody">
                <tr>
                    <td colspan="6" class="text-center py-5 opacity-50">Pulse ACTUALIZAR para cargar datos...</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted py-5 opacity-50">Pulse ACTUALIZAR para cargar datos...</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="pagBotWrap" style="display:none;">
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
    /* ============================================================
       ESTADO & PAGINACIÓN
    ============================================================ */
    let isSoloSaldosView = false;
    let dataGlobal = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('#cmbProv').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'BUSCAR PROVEEDOR...',
            allowClear: true,
            ajax: {
                url: 'api_reporte_ctacte_proveedores.php?action=get_proveedores',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.map(i => ({ id: i.id, text: i.razon })) }),
                cache: true
            },
            width: '100%'
        });

        // Listeners Reactivos
        $('#rbEntreFechas').on('change', () => {
            $('#dtmDesde, #dtmHasta').prop('disabled', false);
            buscarCuenta();
        });

        $('#rbFechaTodas, #rbSoloSaldos').on('change', () => {
            $('#dtmDesde, #dtmHasta').prop('disabled', true);
            buscarCuenta();
        });
        
        $('#rbPorProv').on('change', function() {
            if($(this).is(':checked')) $('#cmbProv').prop('disabled', false);
            buscarCuenta();
        });

        $('#rbVerTodos, #rbFiscoCualquiera, #rbSoloFiscales, #rbSoloNoFiscales').on('change', buscarCuenta);
        $('#dtmDesde, #dtmHasta').on('change', buscarCuenta);
        $('#cmbProv').on('change', buscarCuenta);

        $('#btnActualizar').on('click', buscarCuenta);
        $('#btnExportExcel').on('click', exportarExcel);
    }

    async function buscarCuenta() {
        if ($('#rbPorProv').is(':checked') && !$('#cmbProv').val()) {
            limpiarTabla();
            return;
        }

        const params = getFilterParams();

        $('#tblBody').html('<tr><td colspan="6" class="text-center py-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted py-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</div>');

        try {
            const resp = await fetch(`api_reporte_ctacte_proveedores.php?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            if(!res.success) throw new Error(res.error);
            
            isSoloSaldosView = res.solo_saldos;
            dataGlobal = res.data || [];

            pagActual = 1; window.pagActual = 1;
            renderPagina();
            
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
            limpiarTabla();
        }
    }

    function limpiarTabla() {
        dataGlobal = [];
        $('#tblBody').html('<tr><td colspan="6" class="text-center py-5 opacity-50">SELECCIONE UN PROVEEDOR...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted py-5 opacity-50">SELECCIONE UN PROVEEDOR...</div>');
        ['pagTopWrap', 'pagBotWrap'].forEach(id => document.getElementById(id).style.display = 'none');
    }

    function getFilterParams() {
        return new URLSearchParams({
            action: 'get_data',
            por_proveedor: $('#rbPorProv').is(':checked') ? 1 : 0,
            proveedor_id: $('#cmbProv').val() || 0,
            solo_saldos: $('#rbSoloSaldos').is(':checked') ? 1 : 0,
            fisco: $('#rbSoloFiscales').is(':checked') ? '1' : ($('#rbSoloNoFiscales').is(':checked') ? '0' : ''),
            desde: $('#dtmDesde').val(),
            hasta: $('#dtmHasta').val(),
            entre_fechas: $('#rbEntreFechas').is(':checked') ? 1 : 0
        });
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = dataGlobal.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = dataGlobal.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const header = $('#tblHeader').empty();
        const body = $('#tblBody').empty();

        if (data.length === 0) {
            body.append('<tr><td colspan="6" class="text-center py-5 opacity-50 text-muted">SIN MOVIMIENTOS ENCONTRADOS</td></tr>');
            return;
        }

        if (isSoloSaldosView) {
            header.append('<th class="ps-3">ID</th><th>PROVEEDOR</th><th class="text-center">ÚLT. MOV</th><th class="text-end">FACTURACIÓN (+)</th><th class="text-end">PAGOS (-)</th><th class="text-end">SALDO DEUDA</th>');
            data.forEach(row => {
                body.append(`
                    <tr>
                        <td class="ps-3 fw-bold text-white">${row.idproveedor}</td>
                        <td class="fw-bold">${row.Proveedor}</td>
                        <td class="text-center">${row.Fecha}</td>
                        <td class="text-end text-danger">${parseFloat(row.Facturas).toFixed(2)}</td>
                        <td class="text-end text-success">${parseFloat(row.Pagos).toFixed(2)}</td>
                        <td class="text-end fw-bold ${parseFloat(row.Saldo) > 0 ? 'text-warning' : 'text-accent'}">$ ${parseFloat(row.Saldo).toFixed(2)}</td>
                    </tr>
                `);
            });
        } else {
            header.append('<th class="ps-3 text-center">FECHA</th><th>PROVEEDOR</th><th>COMPROBANTE</th><th class="text-end">DEBE (FACT.)</th><th class="text-end">HABER (PAGOS)</th><th class="text-end">SALDO ACUM.</th>');
            data.forEach(row => {
                const esSaldoAnterior = row.Num_Comprobante === 'ANTERIOR';
                body.append(`
                    <tr style="${esSaldoAnterior ? 'background-color: rgba(0,255,255,0.05)' : ''}">
                        <td class="ps-3 text-center">${row.Fecha}</td>
                        <td class="fw-bold">${row.Proveedor}</td>
                        <td><small class="${esSaldoAnterior ? 'text-accent fw-bold' : 'text-muted'}">${row.Tipo_Comprobante}</small> <strong>${row.Num_Comprobante}</strong></td>
                        <td class="text-end text-danger">${parseFloat(row.Facturas) > 0 ? parseFloat(row.Facturas).toFixed(2) : ''}</td>
                        <td class="text-end text-success">${parseFloat(row.Pagos) > 0 ? parseFloat(row.Pagos).toFixed(2) : ''}</td>
                        <td class="text-end fw-bold text-accent">$ ${parseFloat(row.Saldo || 0).toFixed(2)}</td>
                    </tr>
                `);
            });
        }
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!datos.length) {
            wrap.innerHTML = '<div class="text-center text-muted py-5 opacity-50">SIN MOVIMIENTOS ENCONTRADOS</div>';
            return;
        }

        if (isSoloSaldosView) {
            datos.forEach(row => {
                const saldo = parseFloat(row.Saldo);
                wrap.innerHTML += `
                <div class="erp-card">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-truncate" style="max-width: 200px;">${row.Proveedor}</div>
                        <span class="erp-card-code">#${row.idproveedor}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Últ. Movimiento</span>
                        <span class="erp-card-value">${row.Fecha}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Facturación (+)</span>
                        <span class="erp-card-value text-danger">$ ${parseFloat(row.Facturas).toFixed(2)}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">Pagos (-)</span>
                        <span class="erp-card-value text-success">$ ${parseFloat(row.Pagos).toFixed(2)}</span>
                    </div>
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="erp-card-label">Saldo Deuda</span>
                        <span class="erp-card-value ${saldo > 0 ? 'text-warning' : 'text-accent'} fw-bold fs-6">$ ${saldo.toFixed(2)}</span>
                    </div>
                </div>`;
            });
        } else {
            datos.forEach(row => {
                const esSaldoAnt = row.Num_Comprobante === 'ANTERIOR';
                const debe  = parseFloat(row.Facturas || 0);
                const haber = parseFloat(row.Pagos || 0);
                
                wrap.innerHTML += `
                <div class="erp-card" style="${esSaldoAnt ? 'border-color:rgba(0,255,255,0.3);background:rgba(0,255,255,0.04);' : ''}">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title text-truncate" style="font-size:12px; max-width: 200px;">${row.Proveedor || '—'}</div>
                            <small class="${esSaldoAnt ? 'text-accent fw-bold' : 'text-muted'}">${row.Tipo_Comprobante} ${row.Num_Comprobante}</small>
                        </div>
                        <span class="erp-card-code">${row.Fecha}</span>
                    </div>
                    ${debe  > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Debe</span><span class="erp-card-value text-danger">$ ${debe.toFixed(2)}</span></div>` : ''}
                    ${haber > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Haber</span><span class="erp-card-value text-success">$ ${haber.toFixed(2)}</span></div>` : ''}
                    <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                        <span class="erp-card-label">Saldo Acum.</span>
                        <span class="erp-card-value price fs-6">$ ${parseFloat(row.Saldo || 0).toFixed(2)}</span>
                    </div>
                </div>`;
            });
        }
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const label = isSoloSaldosView ? 'proveedores' : 'movimientos';
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ${label}`;
        const vis   = pagTotal > 1 ? 'flex' : 'none';
        
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
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); if(wrap) wrap.style.display=total>0?vis:'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    /* ============================================================
       EXPORTACIÓN
    ============================================================ */
    function exportarExcel() {
        if (dataGlobal.length === 0) {
            return Swal.fire({
                icon: 'warning',
                title: 'No es posible exportar',
                text: 'La tabla está vacía. Primero realice una búsqueda con resultados.',
                confirmButtonColor: 'var(--accent)'
            });
        }
        const params = getFilterParams();
        window.location.href = `exportar_ctacte_proveedores_excel.php?${params.toString()}`;
    }

    init();
})();
</script>
