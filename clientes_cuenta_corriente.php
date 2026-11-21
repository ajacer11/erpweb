<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<style>
    /* Estilo para fijar el encabezado de la tabla */
    #tblCtaCte thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: #0d2b45 !important; /* Color consistente con el tema oscuro */
        box-shadow: inset 0 -2px 0 var(--accent); /* Mantiene la línea divisoria cian */
    }

    /* Asegura que el contenedor tenga el scroll adecuado */
    .table-container-scroll {
        max-height: 65vh;
        overflow-y: auto;
        border: 1px solid rgba(255,255,255,0.05);
    }

    /* PC: la pantalla ocupa el alto disponible y la tabla toma todo lo que sobra
       (antes 65vh fijo: en monitores de 768px se cortaba abajo). */
    @media (min-width: 992px) {
        #moduloCtaCte { display: flex; flex-direction: column; height: 100%; }
        #moduloCtaCte > * { flex-shrink: 0; }
        #moduloCtaCte > .table-container-scroll { flex: 1 1 auto; min-height: 120px; max-height: none; }
    }
    /* Monitores bajos (1366x768 y similares): todo más compacto */
    @media (min-width: 992px) and (max-height: 900px) {
        #moduloCtaCte { padding: 0 4px !important; }
        #moduloCtaCte > .d-flex:first-child { margin-bottom: 4px !important; }
        #moduloCtaCte h4 { font-size: 1rem; }
        #moduloCtaCte .cc-subtitulo { display: none; }
        #moduloCtaCte .btn { padding-top: 2px; padding-bottom: 2px; }
        #moduloCtaCte .card-filtros { margin-bottom: 4px !important; }
        #moduloCtaCte .card-filtros .card-body { padding: 6px 10px !important; }
        #moduloCtaCte .card-filtros .row { --bs-gutter-y: 4px; }
        #moduloCtaCte .form-label-custom { font-size: 0.68rem; margin-bottom: 1px; }
        #moduloCtaCte .form-control, #moduloCtaCte .form-select { padding-top: 2px; padding-bottom: 2px; min-height: 28px; font-size: 0.85rem; }
        #moduloCtaCte .select2-container .select2-selection--single { height: 28px; }
        #moduloCtaCte .select2-container .select2-selection--single .select2-selection__rendered { line-height: 26px; }
        #moduloCtaCte .select2-container .select2-selection--single .select2-selection__arrow { height: 26px; }
        #moduloCtaCte .form-check { margin-bottom: 0; min-height: 0; }
        #moduloCtaCte .mt-2 { margin-top: 2px !important; }
        #moduloCtaCte .cc-radios-tipo .form-check { display: inline-block; margin-right: 10px; }
        #moduloCtaCte .erp-pagination { padding: 3px 4px; margin-top: 2px; }
        #moduloCtaCte .erp-pag-btn { padding: 2px 8px; }
        #tblCtaCte { margin-bottom: 0; }
        #tblCtaCte th, #tblCtaCte td { padding-top: 2px; padding-bottom: 2px; }
        #tblCtaCte td { font-size: 0.85rem; }
    }
</style>

<div class="container-fluid px-4 py-3 fade-in" id="moduloCtaCte">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">CUENTAS CORRIENTES DE CLIENTES</h4>
            <small class="text-accent opacity-75 cc-subtitulo">Visualización de movimientos y saldos</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success border-0" id="btnExportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> EXCEL
            </button>
            <button class="btn btn-danger rounded-circle shadow" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- PANEL DE FILTROS -->
    <div class="card card-filtros mb-4 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <!-- Filtro Cliente -->
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">CLIENTE</label>
                    <select class="form-select select2-simple" id="cmbCli"></select>
                    <div class="mt-2 d-flex gap-3 flex-wrap">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroCli" id="rbPorCliente" checked>
                            <label class="form-check-label small" for="rbPorCliente">FILTRAR POR CLIENTE</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroCli" id="rbVerTodas">
                            <label class="form-check-label small" for="rbVerTodas">VER TODAS</label>
                        </div>
                    </div>
                </div>

                <!-- Filtro Tipo -->
                <div class="col-6 col-md-3">
                    <label class="form-label-custom">FILTRO POR TIPO</label>
                    <div class="cc-radios-tipo">
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

                <!-- Botón ACTUALIZAR — columna propia para que nunca se escape -->
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button class="btn btn-info fw-bold w-100" id="btnActualizar">
                        <i class="fa-solid fa-sync-alt me-2"></i>ACTUALIZAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Paginación superior -->
    <div class="erp-pagination" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1"><button class="erp-pag-btn" onclick="pagJumpTop()">IR</button></div>
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

    <!-- TABLA DE RESULTADOS (Desktop) -->
    <div class="erp-table-wrap table-container-scroll shadow-sm">
        <table class="table table-hover align-middle" id="tblCtaCte">
            <thead>
                <tr id="tblHeader">
                    <th>FECHA</th>
                    <th>CLIENTE</th>
                    <th>COMPROBANTE</th>
                    <th class="text-end">DEBE</th>
                    <th class="text-end">HABER</th>
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

    <!-- TARJETAS (Mobile) -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted py-5 opacity-50">Pulse ACTUALIZAR para cargar datos...</div>
    </div>

    <!-- Paginación inferior -->
    <div class="erp-pagination" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1"><button class="erp-pag-btn" onclick="pagJump()">IR</button></div>
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
    let isSoloSaldosView = false;
    let dataGlobal = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('#cmbCli').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'BUSCAR CLIENTE...',
            ajax: {
                url: 'api_reporte_ctacte.php?action=get_clientes',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({ results: data.map(i => ({ id: i.id, text: i.razon })) }),
                cache: true
            }
        });

        // Listeners para actualización automática
        $('#rbEntreFechas').on('change', () => {
            $('#dtmDesde, #dtmHasta').prop('disabled', false);
            buscarCuenta();
        });

        $('#rbFechaTodas, #rbSoloSaldos').on('change', () => {
            $('#dtmDesde, #dtmHasta').prop('disabled', true);
            buscarCuenta();
        });
        
        $('#rbPorCliente').on('change', function() {
            if($(this).is(':checked')) $('#cmbCli').prop('disabled', false);
            buscarCuenta();
        });

        $('#rbVerTodas, #rbFiscoCualquiera, #rbSoloFiscales, #rbSoloNoFiscales').on('change', buscarCuenta);
        $('#dtmDesde, #dtmHasta').on('change', buscarCuenta);
        $('#cmbCli').on('change', buscarCuenta);

        $('#btnActualizar').on('click', buscarCuenta);
        $('#btnExportExcel').on('click', exportarExcel);
    }

    async function buscarCuenta() {
        // Si está marcado por cliente pero no hay cliente seleccionado, no disparamos automáticamente
        if ($('#rbPorCliente').is(':checked') && !$('#cmbCli').val()) {
            limpiarTabla();
            return;
        }

        const params = getFilterParams();

        try {
            const resp = await fetch(`api_reporte_ctacte.php?${params}`, {
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
        }
    }

    function limpiarTabla() {
        dataGlobal = [];
        $('#tblBody').html('<tr><td colspan="6" class="text-center py-5 opacity-50">SELECCIONE UN CLIENTE...</td></tr>');
    }

    function getFilterParams() {
        return new URLSearchParams({
            action: 'get_data',
            por_cliente: $('#rbPorCliente').is(':checked') ? 1 : 0,
            cliente_id: $('#cmbCli').val() || 0,
            solo_saldos: $('#rbSoloSaldos').is(':checked') ? 1 : 0,
            fisco: $('#rbSoloFiscales').is(':checked') ? '1' : ($('#rbSoloNoFiscales').is(':checked') ? '0' : ''),
            desde: $('#dtmDesde').val(),
            hasta: $('#dtmHasta').val(),
            entre_fechas: $('#rbEntreFechas').is(':checked') ? 1 : 0
        });
    }

    function renderTabla(data) {
        const header = $('#tblHeader').empty();
        const body = $('#tblBody').empty();

        if (data.length === 0) {
            body.append('<tr><td colspan="10" class="text-center py-5 opacity-50">SIN MOVIMIENTOS ENCONTRADOS</td></tr>');
            return;
        }

        if (isSoloSaldosView) {
            header.append('<th>ID</th><th>CLIENTE</th><th class="text-center">ÚLT. MOV</th><th class="text-end">FACTURACIÓN</th><th class="text-end">COBRANZAS</th><th class="text-end">SALDO</th>');
            data.forEach(row => {
                body.append(`
                    <tr>
                        <td class="fw-bold text-white">${row.idcliente}</td>
                        <td>${row.Cliente}</td>
                        <td class="text-center">${row.Fecha}</td>
                        <td class="text-end text-white">${parseFloat(row.Facturas).toFixed(2)}</td>
                        <td class="text-end text-success">${parseFloat(row.Cobros).toFixed(2)}</td>
                        <td class="text-end fw-bold ${row.Saldo < 0 ? 'text-danger' : 'text-accent'}">${parseFloat(row.Saldo).toFixed(2)}</td>
                    </tr>
                `);
            });
        } else {
            header.append('<th class="text-center">FECHA</th><th>CLIENTE</th><th>COMPROBANTE</th><th class="text-end">DEBE</th><th class="text-end">HABER</th><th class="text-end">SALDO</th>');
            data.forEach(row => {
                const esSaldoAnterior = row.Num_Comprobante === 'ANTERIOR';

                body.append(`
                    <tr style="${esSaldoAnterior ? 'background-color: rgba(0,255,255,0.05)' : ''}">
                        <td class="text-center">${row.Fecha}</td>
                        <td>${row.Cliente}</td>
                        <td><small class="${esSaldoAnterior ? 'text-accent fw-bold' : 'text-muted'}">${row.Tipo_Comprobante}</small> <strong>${row.Num_Comprobante}</strong></td>
                        <td class="text-end">${parseFloat(row.Facturas) > 0 ? parseFloat(row.Facturas).toFixed(2) : ''}</td>
                        <td class="text-end text-success">${parseFloat(row.Cobros) > 0 ? parseFloat(row.Cobros).toFixed(2) : ''}</td>
                        <td class="text-end fw-bold text-accent">${parseFloat(row.Saldo || 0).toFixed(2)}</td>
                    </tr>
                `);
            });
        }
    }

    // ---- PAGINACIÓN FRONTEND ----
    function renderPagina() {
        const total = dataGlobal.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = dataGlobal.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        // Render tabla original con el slice de la página
        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
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
                wrap.innerHTML += `<div class="erp-card">
                    <div class="erp-card-header">
                        <div class="erp-card-title">${row.Cliente}</div>
                        <span class="erp-card-code">#${row.idcliente}</span>
                    </div>
                    <div class="erp-card-row"><span class="erp-card-label">Últ. Movimiento</span><span class="erp-card-value">${row.Fecha}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Facturación</span><span class="erp-card-value">$ ${parseFloat(row.Facturas).toFixed(2)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Cobranzas</span><span class="erp-card-value" style="color:var(--success);">$ ${parseFloat(row.Cobros).toFixed(2)}</span></div>
                    <div class="erp-card-row"><span class="erp-card-label">Saldo</span><span class="erp-card-value ${saldo < 0 ? 'stock-low' : 'price'}">$ ${saldo.toFixed(2)}</span></div>
                </div>`;
            });
        } else {
            datos.forEach(row => {
                const saldo = parseFloat(row.Saldo || 0);
                const esSaldoAnt = row.Num_Comprobante === 'ANTERIOR';
                const debe  = parseFloat(row.Facturas || 0);
                const haber = parseFloat(row.Cobros   || 0);
                wrap.innerHTML += `<div class="erp-card" style="${esSaldoAnt ? 'border-color:rgba(0,255,255,0.3);background:rgba(0,255,255,0.04);' : ''}">
                    <div class="erp-card-header">
                        <div>
                            <div class="erp-card-title" style="font-size:12px;">${row.Cliente || '—'}</div>
                            <small class="${esSaldoAnt ? 'text-accent fw-bold' : 'text-muted'}">${row.Tipo_Comprobante} ${row.Num_Comprobante}</small>
                        </div>
                        <span class="erp-card-code">${row.Fecha}</span>
                    </div>
                    ${debe  > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Debe</span><span class="erp-card-value">$ ${debe.toFixed(2)}</span></div>` : ''}
                    ${haber > 0 ? `<div class="erp-card-row"><span class="erp-card-label">Haber</span><span class="erp-card-value" style="color:var(--success);">$ ${haber.toFixed(2)}</span></div>` : ''}
                    <div class="erp-card-row"><span class="erp-card-label">Saldo acum.</span><span class="erp-card-value price">$ ${saldo.toFixed(2)}</span></div>
                </div>`;
            });
        }
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const label = isSoloSaldosView ? 'clientes' : 'movimientos';
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> ${label}`;
        const vis   = pagTotal > 1 ? 'flex' : 'none';
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ cont.innerHTML=''; if(pagTotal>1){ var ini=Math.max(1,pagActual-2),fin=Math.min(pagTotal,ini+4); if(fin-ini<4)ini=Math.max(1,fin-4); for(var i=ini;i<=fin;i++){var b=document.createElement('button');b.className='erp-pag-btn'+(i===pagActual?' active':'');b.textContent=i;b.onclick=(function(p){return function(){pagGo(p);};})(i);cont.appendChild(b);} } }
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
        window.location.href = `exportar_ctacte_excel.php?${params.toString()}`;
    }

    init();
})();
</script>
