<?php
/**
 * libro_diario.php - GESTIÓN Y CONSULTA DEL LIBRO DIARIO
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLibroDiario">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-book text-accent me-2"></i>LIBRO DIARIO</h4>
            <small class="text-accent opacity-75">VISUALIZACIÓN Y CONTROL DE ASIENTOS CONTABLES</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" id="btnActualizar">
                <i class="fas fa-sync-alt me-1"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="txtDesde" class="form-control filter-trigger" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="txtHasta" class="form-control filter-trigger" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label-custom">EJERCICIO</label>
                    <select id="cmbEjercicio" class="form-select select2-simple filter-trigger"></select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label-custom">VISTA</label>
                    <select id="cmbVista" class="form-select filter-trigger">
                        <option value="DETALLADA">DETALLADA</option>
                        <option value="AGRUPADA">AGRUPADA</option>
                    </select>
                </div>

                <div class="col-12 d-flex flex-wrap align-items-center gap-4 mt-1 border-top border-secondary border-opacity-25 pt-2">
                    <div class="d-flex gap-3 align-items-center pe-4 border-end border-secondary border-opacity-25">
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optTodos" value="TODOS" checked>
                            <label class="form-check-label text-white-50 small" for="optTodos">TODOS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optFiscales" value="FISCAL">
                            <label class="form-check-label text-white-50 small" for="optFiscales">FISCALES</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optFiscal" id="optNoFiscales" value="NO_FISCAL">
                            <label class="form-check-label text-white-50 small" for="optNoFiscales">INTERNOS</label>
                        </div>
                    </div>
                    <div class="d-flex gap-3 align-items-center">
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optManual" id="optTodosMan" value="TODOS" checked>
                            <label class="form-check-label text-white-50 small" for="optTodosMan">VER TODOS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input filter-trigger" type="radio" name="optManual" id="optSoloMan" value="MANUAL">
                            <label class="form-check-label text-white-50 small" for="optSoloMan">SOLO MANUALES</label>
                        </div>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 55vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover table-sm align-middle mb-0 text-nowrap" id="tblLibroDiario" style="font-size: 0.85rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="ps-3" style="width: 150px;">CÓDIGO</th>
                    <th>CUENTA</th>
                    <th class="text-end" style="width: 140px;">DEBE</th>
                    <th class="text-end pe-3" style="width: 140px;">HABER</th>
                </tr>
            </thead>
            <tbody id="tbodyDiario">
                <tr><td colspan="4" class="text-center py-5 text-muted">SELECCIONE EL EJERCICIO PARA CARGAR DATOS</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50">SELECCIONE EL EJERCICIO PARA CARGAR DATOS</div>
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
                <option value="50" selected>50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>
</div>

<div class="modal fade" id="modalModificarAsiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-info shadow-lg">
            <div class="modal-header py-2 border-secondary bg-navbar">
                <h6 class="modal-title text-white">MODIFICAR ASIENTO CONTABLE <span id="modalNumAsiento" class="text-accent"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-escritorio p-3">
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label-custom">FECHA</label>
                        <input type="date" id="txtModalFecha" class="form-control form-control-sm">
                        <input type="hidden" id="modalFechaOriginal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">DETALLE / CONCEPTO</label>
                        <input type="text" id="txtModalDetalle" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="chkModalFiscal">
                            <label class="form-check-label text-white small" for="chkModalFiscal">ASIENTO FISCAL</label>
                        </div>
                    </div>
                </div>

                <div class="table-responsive border border-secondary border-opacity-25 rounded" style="max-height: 350px;">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="sticky-top bg-dark">
                            <tr>
                                <th style="width: 120px;">CÓDIGO</th>
                                <th>CUENTA</th>
                                <th class="text-end" style="width: 140px;">DEBE</th>
                                <th class="text-end" style="width: 140px;">HABER</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbodyModalRenglones"></tbody>
                        <tfoot class="bg-dark sticky-bottom border-top border-secondary">
                            <tr class="fw-bold">
                                <td colspan="2" class="text-end text-white py-2">TOTALES:</td>
                                <td class="text-end text-accent" id="modalTotalDebe">0.00</td>
                                <td class="text-end text-accent pe-4" id="modalTotalHaber">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="modalAlertDiferencia" class="alert alert-danger mt-2 p-1 text-center d-none small">
                    EL ASIENTO NO BALANCEA. DIFERENCIA: <span id="modalValDiferencia" class="fw-bold"></span>
                </div>
            </div>
            <div class="modal-footer border-secondary bg-navbar py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" id="btnModalGuardar">
                    <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalBusquedaCuentas" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary shadow-lg">
            <div class="modal-header py-2 bg-dark">
                <h6 class="modal-title text-white">BUSCAR CUENTA CONTABLE</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-escritorio">
                <div class="p-2 border-bottom border-secondary border-opacity-25">
                    <input type="text" id="txtFiltroModalCuenta" class="form-control form-control-sm" placeholder="FILTRAR POR NOMBRE O CÓDIGO...">
                </div>
                <div style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover mb-0" style="font-size: 0.85rem;">
                        <tbody id="tbodySeleccionCuenta"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentData = [];
    let renglonesMod = [];
    let idxRenglonEdit = -1;
    let todasLasCuentas = [];
    let pagActual = 1, pagTotal = 1, pagTam = 50, totalRegistros = 0;
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%', theme: 'default' });
        await cargarEjercicios();
        
        $('.filter-trigger').on('change input', function() {
            if ($(this).attr('id') === 'cmbVista') {
                const isAgr = $(this).val() === 'AGRUPADA';
                ['pagTopWrap','pagBotWrap'].forEach(id => document.getElementById(id).classList.toggle('d-none', isAgr));
            }
            pagActual = 1; window.pagActual = 1;
            cargarDatos();
        });

        $('#btnActualizar').on('click', () => { pagActual = 1; window.pagActual = 1; cargarDatos(); });
        $('#btnExportar').on('click', exportarExcel);
        $('#btnModalGuardar').click(guardarModificacionAsiento);
        $('#txtFiltroModalCuenta').on('input', renderListaCuentasModal);
    }

    async function cargarEjercicios() {
        try {
            const resp = await fetch('api_helpers.php?action=get_ejercicios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success && result.data) {
                const $cmb = $('#cmbEjercicio');
                $cmb.empty().append(new Option('SELECCIONE EJERCICIO...', ''));
                result.data.forEach(ej => $cmb.append(new Option(ej.descripcion, ej.id)));
                $cmb.trigger('change');
            }
        } catch (e) { console.error(e); }
    }

    async function cargarDatos() {
        const ej = $('#cmbEjercicio').val();
        if (!ej) return;

        const payload = {
            vista: $('#cmbVista').val(), ejercicio: ej, desde: $('#txtDesde').val(), hasta: $('#txtHasta').val(),
            fiscal: $('input[name="optFiscal"]:checked').val(), manual: $('input[name="optManual"]:checked').val(),
            pagina: pagActual, tamanioPagina: pagTam
        };

        const $tbody = $('#tbodyDiario');
        const $cards = $('#tblCards');
        $tbody.html('<tr><td colspan="4" class="text-center py-5"><div class="spinner-border text-info"></div></td></tr>');
        $cards.html('<div class="text-center p-5"><div class="spinner-border text-info"></div></div>');

        try {
            const resp = await fetch('api_reporte_contable_diario.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);

            currentData = result.data || [];
            if (Array.isArray(currentData)) {
                totalRegistros = Number(result.totalRegistros ?? currentData.length);
                pagTotal = Number(result.totalPaginas ?? 1);
                pagActual = Number(result.paginaActual ?? pagActual);
                window.pagActual = pagActual; window.pagTotal = pagTotal;
            }
            if ($('#cmbVista').val() === 'DETALLADA') {
                renderPagina();
            } else {
                renderAgrupada(currentData);
            }
        } catch (e) {
            $tbody.html(`<tr><td colspan="4" class="text-danger text-center py-4">Error: ${e.message}</td></tr>`);
            $cards.html(`<div class="text-danger text-center p-4">Error: ${e.message}</div>`);
        }
    }

    function renderPagina() {
        const total = totalRegistros;
        const datos = currentData;
        renderTablaDetallada(datos);
        renderTarjetasDetallada(datos);
        sincronizarPaginacion(total);
    }

    function renderTablaDetallada(data) {
        const $tbody = $('#tbodyDiario').empty();
        let numAsientoActual = -1;

        if (data.length === 0) return $tbody.html('<tr><td colspan="4" class="text-center py-5 text-muted">SIN MOVIMIENTOS</td></tr>');

        data.forEach(row => {
            if (row.numasiento != numAsientoActual) {
                numAsientoActual = row.numasiento;
                const fiscalLabel = row.fiscal == 1 ? '<span class="badge bg-success ms-2" style="font-size:9px">FISCAL</span>' : '';
                const trHeader = $(`
                    <tr class="table-active header-asiento" style="background-color: #0d2b45 !important;">
                        <td class="fw-bold text-white ps-3">Asiento ${row.numasiento}</td>
                        <td class="fw-bold text-white" colspan="3">
                            <div class="d-flex justify-content-between align-items-center pe-3">
                                <span>${new Date(row.fecha + 'T00:00:00').toLocaleDateString('es-ES')} - ${row.detalle} ${fiscalLabel}</span>
                                <div class="d-flex gap-2">
                                    <span class="text-accent small me-3 mt-1">Usu: ${row.usuario}</span>
                                    <button class="btn btn-sm btn-info fw-bold py-0 px-2 rounded-pill shadow-sm" onclick="window.abrirModalModificar(${row.numasiento})">
                                        <i class="fas fa-edit me-1"></i>MODIFICAR
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                `);
                $tbody.append(trHeader);
            }
            $tbody.append(`
                <tr>
                    <td class="text-white-50 ps-4">${row.codigo}</td>
                    <td class="text-white">${row.cuenta}</td>
                    <td class="text-end text-success fw-bold">${parseFloat(row.debe) > 0 ? '$ ' + parseFloat(row.debe).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                    <td class="text-end text-danger fw-bold pe-3">${parseFloat(row.haber) > 0 ? '$ ' + parseFloat(row.haber).toLocaleString('es-AR', {minimumFractionDigits:2}) : '-'}</td>
                </tr>
            `);
        });
    }

    function renderTarjetasDetallada(data) {
        const $wrap = $('#tblCards').empty();
        let numAsientoActual = -1;
        let cardHtml = '';

        data.forEach(row => {
            if (row.numasiento != numAsientoActual) {
                if (cardHtml) $wrap.append(cardHtml + '</div><div class="mt-2 pt-2 border-top border-secondary border-opacity-25 text-end"><button class="btn btn-sm btn-info rounded-pill px-3 fw-bold" onclick="window.abrirModalModificar('+numAsientoActual+')"><i class="fas fa-edit me-1"></i> MODIFICAR</button></div></div>');
                numAsientoActual = row.numasiento;
                cardHtml = `
                <div class="erp-card mb-3" style="border-left: 4px solid var(--accent);">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">ASIENTO #${row.numasiento}</div>
                        <span class="erp-card-badge ${row.fiscal == 1 ? 'bg-success' : 'bg-secondary'}">${row.fiscal == 1 ? 'FISCAL' : 'INTERNO'}</span>
                    </div>
                    <div class="erp-card-row small text-white-50 mb-2">
                        <span>${new Date(row.fecha + 'T00:00:00').toLocaleDateString('es-ES')} | ${row.detalle}</span>
                    </div>
                    <div class="border-top border-secondary border-opacity-25 pt-2">`;
            }
            cardHtml += `
                <div class="d-flex justify-content-between mb-1 align-items-center">
                    <div style="max-width: 60%;">
                        <small class="d-block text-info">${row.codigo}</small>
                        <span class="small text-white">${row.cuenta}</span>
                    </div>
                    <div class="text-end">
                        ${parseFloat(row.debe) > 0 ? `<span class="text-success fw-bold small">$ ${parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2})} (D)</span>` : ''}
                        ${parseFloat(row.haber) > 0 ? `<span class="text-danger fw-bold small">$ ${parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2})} (H)</span>` : ''}
                    </div>
                </div>`;
        });
        if (cardHtml) $wrap.append(cardHtml + '</div><div class="mt-2 pt-2 border-top border-secondary border-opacity-25 text-end"><button class="btn btn-sm btn-info rounded-pill px-3 fw-bold" onclick="window.abrirModalModificar('+numAsientoActual+')"><i class="fas fa-edit me-1"></i> MODIFICAR</button></div></div>');
    }

    function renderAgrupada(data) {
        const $tbody = $('#tbodyDiario').empty();
        const $cards = $('#tblCards').empty();
        let hay = false;

        for (const grupo in data) {
            hay = true;
            $tbody.append(`<tr class="table-secondary"><td colspan="4" class="fw-bold text-white text-uppercase" style="background-color: #0d2b45 !important; border-left: 4px solid var(--accent);">${grupo}</td></tr>`);
            data[grupo].forEach(row => {
                const tr = `<tr><td class="text-white-50 ps-4">${row.codigo}</td><td class="text-white">${row.cuenta}</td><td class="text-end text-success fw-bold">${parseFloat(row.debe)>0?'$ '+parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2}):'-'}</td><td class="text-end text-danger fw-bold pe-3">${parseFloat(row.haber)>0?'$ '+parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2}):'-'}</td></tr>`;
                $tbody.append(tr);
                $cards.append(`<div class="erp-card mb-2"><div class="erp-card-header"><div class="erp-card-title">${row.cuenta}</div><small class="text-info">${row.codigo}</small></div><div class="erp-card-row"><span class="erp-card-label">Rubro</span><span>${grupo}</span></div><div class="erp-card-row">${parseFloat(row.debe)>0?`<span class="text-success fw-bold">$ ${parseFloat(row.debe).toLocaleString('es-AR',{minimumFractionDigits:2})} (D)</span>`:`<span class="text-danger fw-bold">$ ${parseFloat(row.haber).toLocaleString('es-AR',{minimumFractionDigits:2})} (H)</span>`}</div></div>`);
            });
        }
        if (!hay) { $tbody.html('<tr><td colspan="4" class="text-center py-5 text-muted">SIN DATOS</td></tr>'); $cards.html('<div class="text-center p-5">SIN DATOS</div>'); }
    }

    window.abrirModalModificar = async function(num) {
        const ej = $('#cmbEjercicio').val();
        try {
            const resp = await fetch(`api_asiento_modificar.php?action=get&asiento=${num}&ejercicio=${ej}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);

            const data = result.data;
            const origen = data[0].tabla;
            
            if (origen !== 'librodiario') {
                const confirm = await Swal.fire({
                    title: 'Asiento Automático',
                    text: `Este asiento fue generado por el módulo [${origen.toUpperCase()}]. ¿Desea modificarlo manualmente?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'SÍ, MODIFICAR',
                    cancelButtonText: 'CANCELAR',
                    background: '#001020',
                    color: '#fff'
                });
                if (!confirm.isConfirmed) return;
            }

            $('#modalNumAsiento').text('#' + num);
            $('#txtModalFecha').val(data[0].fecha);
            $('#modalFechaOriginal').val(data[0].fecha);
            $('#txtModalDetalle').val(data[0].detalle);
            $('#chkModalFiscal').prop('checked', data[0].fiscal == 1);
            
            renglonesMod = data.map(r => ({ ...r, debe: parseFloat(r.debe) || 0, haber: parseFloat(r.haber) || 0 }));
            renderRenglonesModal();
            $('#modalModificarAsiento').modal('show');
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    function renderRenglonesModal() {
        const $tbody = $('#tbodyModalRenglones'); $tbody.empty();
        let totalD = 0, totalH = 0;

        renglonesMod.forEach((r, idx) => {
            totalD += r.debe; totalH += r.haber;
            $tbody.append(`
                <tr>
                    <td class="text-white-50 small">${r.codigo}</td>
                    <td class="text-accent small">${r.cuenta_nom}</td>
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end input-mod-val" data-idx="${idx}" data-field="debe" value="${r.debe.toFixed(2)}"></td>
                    <td class="p-1"><input type="number" step="0.01" class="form-control form-control-sm text-end input-mod-val" data-idx="${idx}" data-field="haber" value="${r.haber.toFixed(2)}"></td>
                    <td class="text-center p-1"><button class="btn btn-sm btn-outline-info border-0" onclick="window.cambiarCuentaRenglon(${idx})"><i class="fas fa-search"></i></button></td>
                </tr>
            `);
        });

        $('#modalTotalDebe').text(totalD.toLocaleString('es-AR', {minimumFractionDigits:2}));
        $('#modalTotalHaber').text(totalH.toLocaleString('es-AR', {minimumFractionDigits:2}));
        const diff = Math.abs(totalD - totalH);
        if (diff > 0.009) { $('#modalAlertDiferencia').removeClass('d-none'); $('#modalValDiferencia').text(diff.toLocaleString('es-AR', {minimumFractionDigits:2})); }
        else { $('#modalAlertDiferencia').addClass('d-none'); }

        $('.input-mod-val').on('change input', function() {
            const idx = $(this).data('idx'), field = $(this).data('field'), val = parseFloat($(this).val()) || 0;
            renglonesMod[idx][field] = val;
            let tD = 0, tH = 0; renglonesMod.forEach(r => { tD += r.debe; tH += r.haber; });
            $('#modalTotalDebe').text(tD.toLocaleString('es-AR', {minimumFractionDigits:2}));
            $('#modalTotalHaber').text(tH.toLocaleString('es-AR', {minimumFractionDigits:2}));
            const d = Math.abs(tD - tH);
            if (d > 0.009) { $('#modalAlertDiferencia').removeClass('d-none'); $('#modalValDiferencia').text(d.toLocaleString('es-AR', {minimumFractionDigits:2})); }
            else { $('#modalAlertDiferencia').addClass('d-none'); }
        });
    }

    window.cambiarCuentaRenglon = async function(idx) {
        idxRenglonEdit = idx;
        if (todasLasCuentas.length === 0) {
            const resp = await fetch('api_helpers.php?action=get_plan_cuentas', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json(); todasLasCuentas = res.data;
        }
        renderListaCuentasModal();
        $('#modalBusquedaCuentas').modal('show');
    };

    function renderListaCuentasModal() {
        const filtro = $('#txtFiltroModalCuenta').val().toUpperCase();
        const filtered = todasLasCuentas.filter(c => c.numcuenta.toString().includes(filtro) || c.descripcion.toUpperCase().includes(filtro));
        const $tbody = $('#tbodySeleccionCuenta'); $tbody.empty();
        filtered.slice(0, 30).forEach(c => {
            const tr = $(`<tr class="cursor-pointer"><td>${c.numcuenta}</td><td>${c.descripcion}</td></tr>`);
            tr.click(() => { renglonesMod[idxRenglonEdit].codigo = c.numcuenta; renglonesMod[idxRenglonEdit].cuenta_nom = c.descripcion; renderRenglonesModal(); $('#modalBusquedaCuentas').modal('hide'); });
            $tbody.append(tr);
        });
    }

    async function guardarModificacionAsiento() {
        const fechaN = $('#txtModalFecha').val();
        if (!fechaN) return Swal.fire('Error', 'Debe ingresar una fecha válida', 'warning');
        const totalD = renglonesMod.reduce((a, r) => a + r.debe, 0), totalH = renglonesMod.reduce((a, r) => a + r.haber, 0);
        if (Math.abs(totalD - totalH) > 0.01) return Swal.fire('Error', 'El asiento no balancea', 'error');

        const payload = { numasiento: parseInt($('#modalNumAsiento').text().replace('#','')), idejercicio: parseInt($('#cmbEjercicio').val()), fechaNueva: fechaN, fechaOriginal: $('#modalFechaOriginal').val(), detalle: $('#txtModalDetalle').val().toUpperCase(), fiscal: $('#chkModalFiscal').is(':checked') ? 1 : 0, renglones: renglonesMod };

        try {
            $('#btnModalGuardar').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>...');
            const resp = await fetch('api_asiento_modificar.php?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) });
            const res = await resp.json();
            if (res.success) { $('#modalModificarAsiento').modal('hide'); Swal.fire({ icon: 'success', title: 'Asiento modificado', timer: 2000, showConfirmButton: false }); cargarDatos(); }
            else throw new Error(res.error);
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
        finally { $('#btnModalGuardar').prop('disabled', false).html('<i class="fas fa-save me-2"></i>GUARDAR CAMBIOS'); }
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;
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
                        const b = document.createElement('button'); b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i; b.onclick = (p => () => pagGo(p))(i); cont.appendChild(b);
                    }
                }
            }
            const ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            const sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            const wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; cargarDatos(); document.getElementById('moduloLibroDiario')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ const v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ const v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; cargarDatos(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; cargarDatos(); };

    function exportarExcel() {
        const ej = $('#cmbEjercicio').val(); if (!ej) return;
        const p = new URLSearchParams({ ejercicio: ej, desde: $('#txtDesde').val(), hasta: $('#txtHasta').val(), vista: $('#cmbVista').val(), fiscal: $('input[name="optFiscal"]:checked').val(), manual: $('input[name="optManual"]:checked').val() });
        window.open(`exportar_libro_diario.php?${p.toString()}`, '_blank');
    }

    init();
})();
</script>