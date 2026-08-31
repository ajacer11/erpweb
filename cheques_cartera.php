<?php
/**
 * cheques_cartera.php - VISOR DE CHEQUES DE TERCEROS
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloChequesCartera">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-money-check-alt text-accent me-2"></i>CARTERA DE CHEQUES DE
                TERCEROS</h4>
            <small class="text-accent opacity-75">Administración de valores recibidos y estados de negociación</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 px-3 btn-sm" id="btn-excel-chq">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 btn-sm" id="btn-actualizar-chq">
                <i class="fas fa-sync-alt me-1"></i> <span class="d-none d-sm-inline">ACTUALIZAR</span>
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm border-secondary border-opacity-25"
        style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label text-accent small mb-1 text-uppercase">Vto. Desde</label>
                    <input type="date" id="f-desde-chq" class="form-control bg-dark text-white border-secondary"
                        value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label text-accent small mb-1 text-uppercase">Vto. Hasta</label>
                    <input type="date" id="f-hasta-chq" class="form-control bg-dark text-white border-secondary"
                        value="<?= date('Y-m-d', strtotime('+90 days')) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label text-accent small mb-1 text-uppercase">Cliente Emisor</label>
                    <select id="cmb-cliente-chq" class="form-control select2-filter"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label text-accent small mb-1 text-uppercase">Estado del Valor</label>
                    <select id="cmb-estado-chq" class="form-select bg-dark text-white border-secondary">
                        <option value="todos">-- TODOS LOS ESTADOS --</option>
                        <option value="0" selected>EN CARTERA (DISPONIBLES)</option>
                        <option value="1">DEPOSITADOS / COBRADOS</option>
                        <option value="2">ENDOSADOS A PROVEEDOR</option>
                        <option value="3">RECHAZADOS</option>
                        <option value="1000">ANULADOS</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 fw-bold" id="btn-limpiar-filtros">
                        <i class="fas fa-eraser me-1"></i> LIMPIAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop" onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop" onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop" onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="card card-filtros p-0 overflow-hidden shadow-sm border-secondary border-opacity-25"
        style="background-color: var(--bg-card);">
        <div class="erp-table-wrap table-responsive" style="max-height: 60vh; min-height: 300px;">
            <table class="table table-hover align-middle mb-0 small text-nowrap" id="tabla-cartera">
                <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                    <tr class="text-accent border-bottom border-secondary text-uppercase">
                        <th class="ps-3 py-2">Número</th>
                        <th class="py-2">Vencimiento</th>
                        <th class="py-2">Cliente</th>
                        <th class="text-end py-2">Importe</th>
                        <th class="py-2">Banco</th>
                        <th class="py-2">Estado</th>
                        <th class="py-2">Endosado A</th>
                        <th class="text-center py-2 pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-cartera" class="text-white-50"></tbody>
            </table>
        </div>

        <div class="erp-cards-wrap" id="tblCards"></div>

        <div
            class="card-footer bg-dark border-top border-secondary p-2 px-3 d-flex justify-content-between flex-wrap gap-2">
            <span class="text-muted small">CANTIDAD: <b class="text-white" id="lbl-cant-chq">0</b></span>
            <span class="text-muted small">TOTAL EN CARTERA: <b class="text-accent fs-6" id="lbl-total-chq">$
                    0.00</b></span>
        </div>
    </div>

    <div class="erp-pagination mt-3" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt" onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig" onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt" onclick="pagGo(pagTotal)">»</button>
        </div>
    </div>
</div>

<script>
    (function () {
        let listado = [];
        let pagActual = 1, pagTam = 20, pagTotal = 1;
        let debounceTimer;
        const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

        async function init() {
            configurarSelect2();
            $('#btn-actualizar-chq').click(buscar);
            $('#btn-excel-chq').click(exportar);
            $('#btn-limpiar-filtros').click(() => {
                $('#cmb-cliente-chq').val(null).trigger('change');
                $('#cmb-estado-chq').val('0');
                buscar();
            });

            // Eventos de filtros con debounce
            $('#f-desde-chq, #f-hasta-chq, #cmb-estado-chq').on('change', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(buscar, 800);
            });

            buscar();
        }

        function configurarSelect2() {
            $('#cmb-cliente-chq').select2({
                placeholder: 'FILTRAR POR CLIENTE...',
                theme: 'default',
                dropdownParent: $('#main-content'),
                allowClear: true,
                ajax: {
                    url: 'api_cobros.php?action=search_clientes',
                    dataType: 'json', headers: commonHeaders,
                    data: p => ({ q: p.term }),
                    processResults: d => ({ results: d.data.map(c => ({ id: c.id, text: c.razon })) })
                }
            }).on('change', buscar);
        }

        async function buscar() {
            const filters = {
                desde: $('#f-desde-chq').val(),
                hasta: $('#f-hasta-chq').val(),
                estado: $('#cmb-estado-chq').val(),
                cliente: $('#cmb-cliente-chq').val() || 'todos'
            };

            try {
                $('#loading-overlay').show();
                const res = await fetch('api_cheques_cartera.php?action=list', {
                    method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify(filters)
                }).then(r => r.json());

                if (res.success) {
                    listado = res.data || [];
                    pagActual = 1;
                    renderPagina();
                }
            } catch (e) {
                console.error(e);
            } finally {
                $('#loading-overlay').hide();
            }
        }

        function renderPagina() {
            const total = listado.length;
            pagTotal = Math.max(1, Math.ceil(total / pagTam));
            if (pagActual > pagTotal) pagActual = pagTotal;

            const inicio = (pagActual - 1) * pagTam;
            const segmento = listado.slice(inicio, inicio + pagTam);

            renderTabla(segmento);
            renderTarjetas(segmento, inicio);
            sincronizarPaginacion(total);
            actualizarTotales();
        }

        function renderTabla(data) {
            const tbody = $('#tbody-cartera').empty();
            if (data.length === 0) {
                tbody.html('<tr><td colspan="8" class="text-center p-5 text-muted">No se encontraron cheques con los filtros aplicados.</td></tr>');
                return;
            }

            data.forEach((c, idx) => {
                const absoluteIdx = listado.indexOf(c);
                const colorStatus = { 0: 'text-success', 1: 'text-info', 2: 'text-warning', 3: 'text-danger', 1000: 'text-muted' }[c.status_code];
                const isAnulado = c.status_code == 1000;

                tbody.append(`
                <tr class="border-secondary align-middle ${isAnulado ? 'opacity-50 strike' : ''}">
                    <td class="ps-3 fw-bold text-white font-monospace">${c['Num Cheque']}</td>
                    <td>${c.Vencimiento}</td>
                    <td><div class="text-truncate" style="max-width:150px" title="${c.Cliente}">${c.Cliente}</div></td>
                    <td class="text-end fw-bold text-accent font-monospace">$ ${parseFloat(c.Importe).toFixed(2)}</td>
                    <td><div class="text-truncate" style="max-width:120px" title="${c.Banco}">${c.Banco}</div></td>
                    <td class="${colorStatus} fw-bold small">${c['Estado Actual']}</td>
                    <td><small class="text-muted text-truncate d-inline-block" style="max-width:100px">${c['Endosado A'] || '-'}</small></td>
                    <td class="text-center pe-3">
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-info" onclick="window.modificarObsChq(${absoluteIdx})" title="Observaciones"><i class="fas fa-comment"></i></button>
                            ${c.status_code == 0 ? `<button class="btn btn-outline-success" onclick="window.depositarChq(${absoluteIdx})" title="Depositar"><i class="fas fa-university"></i></button>` : ''}
                            ${c.status_code == 2 ? `<button class="btn btn-outline-warning" onclick="window.quitarEndosoChq(${absoluteIdx})" title="Quitar Endoso"><i class="fas fa-unlink"></i></button>` : ''}
                            ${(c.status_code == 1 || c.status_code == 2) ? `<button class="btn btn-outline-danger" onclick="window.rechazarChq(${absoluteIdx})" title="Rechazar"><i class="fas fa-times-circle"></i></button>` : ''}
                        </div>
                    </td>
                </tr>
            `);
            });
        }

        function renderTarjetas(data, absoluteOffset) {
            const wrap = $('#tblCards').empty();
            if (data.length === 0) return;

            data.forEach((c) => {
                const absoluteIdx = listado.indexOf(c);
                const colorStatus = { 0: 'text-success', 1: 'text-info', 2: 'text-warning', 3: 'text-danger', 1000: 'text-muted' }[c.status_code];
                const isAnulado = c.status_code == 1000;

                wrap.append(`
                <div class="erp-card mb-2 ${isAnulado ? 'opacity-50 strike' : ''}">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">Nº: ${c['Num Cheque']}</div>
                        <span class="erp-card-code font-monospace">$ ${parseFloat(c.Importe).toFixed(2)}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">VTO / BANCO</span>
                        <span class="erp-card-value text-white">${c.Vencimiento} / ${c.Banco}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">CLIENTE</span>
                        <span class="erp-card-value text-white-50">${c.Cliente}</span>
                    </div>
                    <div class="erp-card-row">
                        <span class="erp-card-label">ESTADO</span>
                        <span class="erp-card-value ${colorStatus} fw-bold">${c['Estado Actual']}</span>
                    </div>
                    ${c['Endosado A'] ? `
                        <div class="erp-card-row">
                            <span class="erp-card-label">ENDOSADO A</span>
                            <span class="erp-card-value text-warning">${c['Endosado A']}</span>
                        </div>
                    ` : ''}
                    <div class="mt-2 d-flex flex-wrap gap-2">
                        <button class="btn btn-sm btn-info flex-fill" onclick="window.modificarObsChq(${absoluteIdx})"><i class="fas fa-comment me-1"></i> OBS</button>
                        ${c.status_code == 0 ? `<button class="btn btn-sm btn-success flex-fill" onclick="window.depositarChq(${absoluteIdx})"><i class="fas fa-university me-1"></i> DEP</button>` : ''}
                        ${c.status_code == 2 ? `<button class="btn btn-sm btn-warning flex-fill" onclick="window.quitarEndosoChq(${absoluteIdx})"><i class="fas fa-unlink me-1"></i> UNLINK</button>` : ''}
                        ${(c.status_code == 1 || c.status_code == 2) ? `<button class="btn btn-sm btn-danger flex-fill" onclick="window.rechazarChq(${absoluteIdx})"><i class="fas fa-times-circle me-1"></i> RECH</button>` : ''}
                    </div>
                </div>
            `);
            });
        }

        function sincronizarPaginacion(total) {
            const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
            const hasta = Math.min(pagActual * pagTam, total);
            const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> valores`;

            ['', 'Top'].forEach(sfx => {
                $(`#pagInfo${sfx}`).html(info);
                $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
                $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

                const cont = $(`#pagBotones${sfx}`).empty();
                let ini = Math.max(1, pagActual - 2);
                let fin = Math.min(pagTotal, ini + 4);
                if (fin - ini < 4) ini = Math.max(1, fin - 4);

                for (let i = ini; i <= fin; i++) {
                    const b = $('<button>').addClass('erp-pag-btn' + (i === pagActual ? ' active' : '')).text(i).click(() => pagGo(i));
                    cont.append(b);
                }
                $(`#pag${sfx}Wrap`).toggle(total > 0);
            });
        }

        function actualizarTotales() {
            let total = 0;
            listado.forEach(c => {
                if (c.status_code != 1000) total += parseFloat(c.Importe);
            });
            $('#lbl-cant-chq').text(listado.length);
            $('#lbl-total-chq').text('$ ' + total.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
        }

        window.pagGo = (n) => {
            pagActual = Math.max(1, Math.min(pagTotal, n));
            renderPagina();
            document.getElementById('moduloChequesCartera').scrollIntoView({ behavior: 'smooth' });
        };

        window.pagCambiarTamanoTop = () => {
            pagTam = parseInt($('#pagTamanoTop').val());
            pagActual = 1;
            renderPagina();
        };

        function exportar() {
            const params = new URLSearchParams({
                desde: $('#f-desde-chq').val(),
                hasta: $('#f-hasta-chq').val(),
                estado: $('#cmb-estado-chq').val(),
                cliente: $('#cmb-cliente-chq').val() || 'todos',
                action: 'export_excel'
            }).toString();
            window.open(`api_cheques_cartera.php?${params}`, '_blank');
        }

        // ACCIONES DE GESTIÓN (IDEM ORIGINAL)
        window.modificarObsChq = async (idx) => {
            const c = listado[idx];
            const { value: text } = await Swal.fire({ title: 'Modificar Observación', background: '#001020', color: '#fff', input: 'textarea', inputValue: c.Observaciones, showCancelButton: true });
            if (text !== undefined) {
                await fetch('api_cheques_cartera.php?action=update_obs', {
                    method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: c.id, obs: text })
                });
                buscar();
            }
        };

        window.rechazarChq = async (idx) => {
            const c = listado[idx];
            if (await Swal.fire({ title: '¿Marcar como RECHAZADO?', background: '#001020', color: '#fff', text: 'El cheque cambiará su estado a Rechazado.', icon: 'warning', showCancelButton: true }).then(r => r.isConfirmed)) {
                await fetch('api_cheques_cartera.php?action=reject', {
                    method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: c.id })
                });
                buscar();
            }
        };

        window.quitarEndosoChq = async (idx) => {
            const c = listado[idx];
            if (await Swal.fire({ title: '¿Quitar Endoso?', background: '#001020', color: '#fff', text: 'El cheque volverá a estar disponible en cartera.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
                await fetch('api_cheques_cartera.php?action=remove_endorsement', {
                    method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: c.id })
                });
                buscar();
            }
        };

        window.depositarChq = async (idx) => {
            const c = listado[idx];

            try {

                const resC = await fetch(
                    'api_pagos_proveedores.php?action=get_cuentas_propias',
                    { headers: commonHeaders }
                ).then(r => r.json());

                if (!resC.success || !resC.data || resC.data.length === 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Sin cuentas',
                        text: 'No existen cuentas de depósito configuradas.',
                        background: '#001020',
                        color: '#fff'
                    });
                    return;
                }

                const opciones = resC.data.map(cuenta =>
                    `<option value="${cuenta.id}">${cuenta.referencia}</option>`
                ).join('');

                const { value: cuentaId } = await Swal.fire({
                    title: 'Seleccionar Cuenta de Depósito',
                    background: '#001020',
                    color: '#fff',
                    width: 500,
                    html: `
                <div class="text-start">
                    <label class="form-label text-white mb-2">
                        Cuenta de depósito
                    </label>
                    <select id="swal-cuenta-deposito"
                            class="form-select bg-dark text-white border-secondary">
                        <option value="">-- SELECCIONE CUENTA --</option>
                        ${opciones}
                    </select>
                </div>
            `,
                    showCancelButton: true,
                    confirmButtonText: 'Depositar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    focusConfirm: false,
                    preConfirm: () => {

                        const cuenta = document.getElementById('swal-cuenta-deposito').value;

                        if (!cuenta) {
                            Swal.showValidationMessage('Debe seleccionar una cuenta');
                            return false;
                        }

                        return cuenta;
                    }
                });

                if (!cuentaId) return;

                const resp = await fetch(
                    'api_cheques_cartera.php?action=deposit',
                    {
                        method: 'POST',
                        headers: {
                            ...commonHeaders,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            id: c.id,
                            cuenta_id: cuentaId,
                            importe: c.Importe,
                            numero: c['Num Cheque']
                        })
                    }
                ).then(r => r.json());

                if (resp.success) {

                    await Swal.fire({
                        icon: 'success',
                        title: 'Cheque depositado',
                        text: 'El cheque fue depositado correctamente.',
                        background: '#001020',
                        color: '#fff'
                    });

                    buscar();

                } else {

                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: resp.message || 'No fue posible realizar el depósito.',
                        background: '#001020',
                        color: '#fff'
                    });

                }

            } catch (e) {

                console.error(e);

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Ocurrió un error al procesar el depósito.',
                    background: '#001020',
                    color: '#fff'
                });

            }
        };

        init();
    })();
</script>

<style>
    .strike {
        text-decoration: line-through;
    }
</style>