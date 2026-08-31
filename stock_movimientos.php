<?php
/**
 * stock_movimientos.php - REPORTE HISTÓRICO DE MOVIMIENTOS DE STOCK
 * Adaptación Responsiva v5.6 (SPA + Paginación Dual + Mobile Cards)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloStockMovimientos">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
        <div>
            <h4 class="text-white mb-0">MOVIMIENTOS DE STOCK</h4>
            <small class="text-accent opacity-75">Consulta histórica de entradas, salidas y ajustes</small>
        </div>
        <div class="d-flex gap-2 ms-auto">
            <button class="btn btn-outline-success border-0 rounded-pill fw-bold" id="btn-exportar">
                <i class="fas fa-file-excel me-1"></i><span class="d-none d-sm-inline">EXPORTAR EXCEL</span><span class="d-sm-none">EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold px-4 rounded-pill" id="btn-generar">
                <i class="fas fa-search me-1"></i>GENERAR
            </button>
        </div>
    </div>

    <div class="card card-filtros shadow-sm mb-3 border-0">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">RANGO DE FECHAS</label>
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width: 60px;">DESDE</span>
                        <input type="date" id="fecha-desde" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width: 60px;">HASTA</span>
                        <input type="date" id="fecha-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="mt-3">
                        <label class="form-label-custom">FISCALIDAD</label>
                        <div class="d-flex gap-3">
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-fiscal" id="r-todos" value="todos" checked><label class="form-check-label text-white" for="r-todos">TODOS</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-fiscal" id="r-si" value="1"><label class="form-check-label text-white" for="r-si">FISCALES</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-fiscal" id="r-no" value="0"><label class="form-check-label text-white" for="r-no">NO FISC.</label></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-3 border-start-md border-secondary px-md-4">
                    <label class="form-label-custom">ORIGEN DEL MOVIMIENTO</label>
                    <div class="row g-1">
                        <div class="col-6">
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-todos" value="todos" checked><label class="form-check-label text-white-50" for="o-todos">TODOS</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-venta" value="VENTA"><label class="form-check-label text-white-50" for="o-venta">VENTAS</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-ingreso" value="INGRESO"><label class="form-check-label text-white-50" for="o-ingreso">INGRESOS</label></div>
                        </div>
                        <div class="col-6">
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-ajuste" value="AJUSTE"><label class="form-check-label text-white-50" for="o-ajuste">AJUSTES</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-envio" value="ENVIO"><label class="form-check-label text-white-50" for="o-envio">ENVÍOS</label></div>
                            <div class="form-check small"><input class="form-check-input" type="radio" name="r-origen" id="o-dev" value="DEVOLUCION"><label class="form-check-label text-white-50" for="o-dev">DEVOLUC.</label></div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label-custom">REMITO / COMPROBANTE</label>
                        <input type="number" id="txt-remito" class="form-control form-control-sm bg-dark text-white border-secondary" placeholder="Opcional">
                    </div>
                </div>

                <div class="col-12 col-md-4 border-start-md border-secondary px-md-4">
                    <label class="form-label-custom">FILTROS AVANZADOS</label>
                    <div class="input-group input-group-sm mb-2">
                        <div class="input-group-text bg-dark border-secondary"><input class="form-check-input mt-0" type="checkbox" id="chk-rubro"></div>
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width:90px;">RUBRO</span>
                        <div class="flex-grow-1"><select id="cmb-rubro" class="form-select select2-filter" disabled></select></div>
                    </div>
                    <div class="input-group input-group-sm">
                        <div class="input-group-text bg-dark border-secondary"><input class="form-check-input mt-0" type="checkbox" id="chk-producto"></div>
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width:90px;">PRODUCTO</span>
                        <div class="flex-grow-1"><select id="cmb-producto" class="form-select select2-filter" disabled></select></div>
                    </div>
                </div>

                <div class="col-12 col-md-2 d-none d-md-block border-start border-secondary">
                    <label class="form-label-custom text-center w-100">BALANCE PERÍODO</label>
                    <div class="mb-1 d-flex justify-content-between small">
                        <span class="text-muted">ENTRADAS:</span>
                        <span class="text-success fw-bold" id="lbl-entradas">0.00</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between small">
                        <span class="text-muted">SALIDAS:</span>
                        <span class="text-danger fw-bold" id="lbl-salidas">0.00</span>
                    </div>
                    <div class="p-2 bg-dark rounded border border-secondary text-center">
                        <div class="text-accent fw-bold fs-5" id="lbl-resultado">0.00</div>
                        <small class="text-white-50" style="font-size:9px;">UNIDADES TOTALES</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-md-none mb-3">
        <div class="card card-filtros border-info border-opacity-25 shadow-sm">
            <div class="card-body p-3 d-flex justify-content-around text-center">
                <div><small class="text-muted d-block">ENTRADAS</small><span class="text-success fw-bold" id="lbl-entradas-m">0.00</span></div>
                <div class="border-start border-secondary opacity-25"></div>
                <div><small class="text-muted d-block">SALIDAS</small><span class="text-danger fw-bold" id="lbl-salidas-m">0.00</span></div>
                <div class="border-start border-secondary opacity-25"></div>
                <div><small class="text-accent d-block">BALANCE</small><span class="text-white fw-bold" id="lbl-resultado-m">0.00</span></div>
            </div>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="max-height: 55vh; background-color: #000a1a;">
        <table class="table table-dark table-hover align-middle mb-0 small" id="tabla-movimientos">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th>FECHA</th>
                    <th>PRODUCTO</th>
                    <th>CÓDIGO</th>
                    <th>RUBRO</th>
                    <th class="text-center">ENTRADAS</th>
                    <th class="text-center">SALIDAS</th>
                    <th>DETALLE</th>
                    <th class="text-center">FISC.</th>
                    <th>REMITO</th>
                    <th class="pe-3">USUARIO</th>
                </tr>
            </thead>
            <tbody id="tbody-movimientos">
                <tr><td colspan="10" class="text-center p-5 text-muted">Configure los filtros y presione GENERAR</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="cards-movimientos"></div>

    <div class="erp-pagination mt-3" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
    </div>
</div>

<script>
(function () {
    let reportData = [], pagActual = 1, pagTotal = 1, pagTam = 20;
    let debounceTimeout = null, currentController = null, isLoading = false;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        await cargarFiltrosIniciales();
        configurarEventos();
        
        $('.select2-filter').select2({ dropdownParent: $('#main-content'), width: '100%' });

        $('#btn-generar').click(generarInforme);
        $('#btn-exportar').click(exportarExcel);
        
        generarInforme();
    }

    async function cargarFiltrosIniciales() {
        try {
            const resR = await fetch('api_helpers.php?action=get_rubros', { headers: commonHeaders }).then(r => r.json());
            if (resR.success) resR.data.forEach(r => $('#cmb-rubro').append(new Option(r.rubro, r.rubro)));

            $('#cmb-producto').select2({
                placeholder: 'BUSCAR PRODUCTO...', minimumInputLength: 3, allowClear: true,
                dropdownParent: $('#main-content'),
                ajax: {
                    url: 'api_productos.php?action=search', dataType: 'json', delay: 250, headers: commonHeaders,
                    data: params => ({ q: params.term }),
                    processResults: data => ({ results: data.data.map(p => ({ id: p.codigo, text: `${p.codigo} - ${p.descripcion}` })) })
                },
                width: '100%'
            });
        } catch(e) { console.error("Error filtros", e); }
    }

    function configurarEventos() {
        $('#chk-rubro').change(e => { 
            const c=$(e.target).is(':checked'); 
            $('#cmb-rubro').prop('disabled',!c); 
            if(!c)$('#cmb-rubro').val('').trigger('change'); 
            generarInforme(); 
        });
        $('#chk-producto').change(e => { 
            const c=$(e.target).is(':checked'); 
            $('#cmb-producto').prop('disabled',!c); 
            if(!c)$('#cmb-producto').val(null).trigger('change'); 
            generarInforme(); 
        });
        
        $('#cmb-rubro').on('change', generarInforme);
        $('#cmb-producto').on('change', function(){ if(!$(this).prop('disabled')) generarInforme(); });
        $('#txt-remito').on('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(generarInforme, 600);
        });
        $('input[name="r-fiscal"], input[name="r-origen"]').on('change', generarInforme);
    }

    async function generarInforme() {
        if (isLoading && currentController) currentController.abort();
        const filters = {
            desde: $('#fecha-desde').val(),
            hasta: $('#fecha-hasta').val(),
            fiscal: $('input[name="r-fiscal"]:checked').val(),
            origen: $('input[name="r-origen"]:checked').val(),
            remito: $('#txt-remito').val(),
            rubro: $('#chk-rubro').is(':checked') ? $('#cmb-rubro').val() : '',
            producto: $('#chk-producto').is(':checked') ? $('#cmb-producto').val() : ''
        };

        currentController = new AbortController();
        isLoading = true;

        $('#tbody-movimientos').html('<tr><td colspan="10" class="text-center p-5"><div class="spinner-border text-info"></div></td></tr>');
        $('#cards-movimientos').empty();

        try {
            const resp = await fetch('api_stock_movimientos.php?action=get_report', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters),
                signal: currentController.signal
            }).then(r => r.json());

            if (resp.success) {
                reportData = resp.data || [];
                renderTotales(resp.resumen);
                pagActual = 1;
                renderPagina();
            }
        } catch (err) { if (err.name !== 'AbortError') console.error(err); }
        finally { isLoading = false; }
    }

    function renderPagina() {
        const total = reportData.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        
        const inicio = (pagActual - 1) * pagTam;
        const datos = reportData.slice(inicio, inicio + pagTam);

        // Render Tabla
        const tbody = $('#tbody-movimientos').empty();
        if (!datos.length) {
            tbody.append(`<tr><td colspan="10" class="text-center p-4 text-muted">No se encontraron movimientos</td></tr>`);
        } else {
            datos.forEach(m => {
                tbody.append(`<tr class="border-secondary">
                    <td class="text-nowrap text-white-50">${m.Fecha}</td>
                    <td><div class="text-truncate text-white fw-bold" style="max-width:200px;">${m.Producto||'---'}</div></td>
                    <td class="text-info fw-bold">${m.Codigo}</td>
                    <td><small>${m.Rubro}</small></td>
                    <td class="text-center fw-bold text-success">${parseFloat(m.Entradas||0).toFixed(2)}</td>
                    <td class="text-center fw-bold text-danger">${parseFloat(m.Salidas||0).toFixed(2)}</td>
                    <td class="small text-white-50">${m['Detalle Movimiento']}</td>
                    <td class="text-center">${m.Fiscal==1?'<i class="fas fa-check text-success"></i>':'-'}</td>
                    <td class="text-center">${m['Num Remito'] || '-'}</td>
                    <td class="pe-3 small text-muted">${m.Usuario}</td>
                </tr>`);
            });
        }

        // Render Tarjetas
        const wrap = $('#cards-movimientos').empty();
        datos.forEach(m => {
            const entradas = parseFloat(m.Entradas || 0);
            const salidas  = parseFloat(m.Salidas || 0);
            wrap.append(`
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">${m.Producto || 'S/D'}</div>
                        <span class="badge bg-dark border border-secondary text-info">${m.Codigo}</span>
                    </div>
                    <div class="erp-card-main">
                        <div class="row mb-2">
                            <div class="col-6">
                                <small class="text-muted d-block">FECHA</small>
                                <span class="small text-white">${m.Fecha}</span>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block">RUBRO</small>
                                <span class="small">${m.Rubro}</span>
                            </div>
                        </div>
                        <div class="row align-items-center bg-dark bg-opacity-25 rounded p-2 g-0">
                            <div class="col-6 text-center">
                                <small class="text-muted d-block">ENTRADA</small>
                                <span class="fw-bold ${entradas > 0 ? 'text-success' : 'text-white-50'}">${entradas > 0 ? '+' : ''}${entradas.toFixed(2)}</span>
                            </div>
                            <div class="col-6 text-center border-start border-secondary">
                                <small class="text-muted d-block">SALIDA</small>
                                <span class="fw-bold ${salidas > 0 ? 'text-danger' : 'text-white-50'}">${salidas > 0 ? '-' : ''}${salidas.toFixed(2)}</span>
                            </div>
                        </div>
                        <div class="mt-2 small">
                            <span class="text-white-50">${m['Detalle Movimiento']}</span>
                        </div>
                    </div>
                    <div class="erp-card-footer d-flex justify-content-between mt-2 pt-2 border-top border-secondary opacity-75 small">
                        <span><i class="fas fa-file-invoice me-1"></i>${m['Num Remito'] || 'S/N'}</span>
                        <span><i class="fas fa-user me-1"></i>${m.Usuario}</span>
                    </div>
                </div>
            `);
        });

        sincronizarPaginacion(total);
    }

    function renderTotales(res) {
        if(!res) return;
        const entradas = parseFloat(res.entradas), salidas = parseFloat(res.salidas);
        const total = entradas - salidas;
        const totalCls = total >= 0 ? 'text-success' : 'text-danger';
        
        ['', '-m'].forEach(sfx => {
            $(`#lbl-entradas${sfx}`).text(entradas.toFixed(2));
            $(`#lbl-salidas${sfx}`).text(salidas.toFixed(2));
            $(`#lbl-resultado${sfx}`).text(total.toFixed(2)).removeClass('text-success text-danger').addClass(totalCls);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> registros`;

        ['', 'Top'].forEach(sfx => {
            $(`#pagInfo${sfx}`).html(info);
            $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
            $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

            const cont = $(`#pagBotones${sfx}`).empty();
            let ini = Math.max(1, pagActual - 2);
            let fin = Math.min(pagTotal, ini + 4);
            if (fin - ini < 4) ini = Math.max(1, fin - 4);

            for (let i = ini; i <= fin; i++) {
                const b = $('<button>').addClass('erp-pag-btn').text(i).click(() => pagGo(i));
                if (i === pagActual) b.addClass('active');
                cont.append(b);
            }
            $(`#pag${sfx}Wrap`).toggle(total > 0);
        });
    }

    window.pagGo = (n) => {
        pagActual = Math.max(1, Math.min(pagTotal, n));
        renderPagina();
        document.getElementById('moduloStockMovimientos').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    function exportarExcel() {
        if (!reportData.length) return;
        const params = new URLSearchParams({
            desde: $('#fecha-desde').val(),
            hasta: $('#fecha-hasta').val(),
            fiscal: $('input[name="r-fiscal"]:checked').val(),
            origen: $('input[name="r-origen"]:checked').val(),
            remito: $('#txt-remito').val(),
            rubro: $('#chk-rubro').is(':checked') ? $('#cmb-rubro').val() : '',
            producto: $('#chk-producto').is(':checked') ? $('#cmb-producto').val() : '',
            action: 'export_excel'
        });
        window.open(`api_stock_movimientos.php?${params.toString()}`, '_blank');
    }

    init();
})();
</script>