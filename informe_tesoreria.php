<?php
/**
 * informe_tesoreria.php - INFORME DE MOVIMIENTOS Y SALDOS
 * Adaptación Responsiva v5.5 (Paginación Dual + Mobile Cards)
 */

// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$hoy = date('Y-m-d');
$inicio_mes = date('Y-m-01');
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloInformeTesoreria">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">INFORME DE TESORERÍA</h4>
            <small class="text-accent opacity-75">Historial de movimientos y análisis de saldos</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary border-0 rounded-pill px-3" onclick="buscarMovimientos()" title="Refrescar datos">
                <i class="fas fa-sync-alt"></i>
            </button>
            <button class="btn btn-outline-success border-0 rounded-pill px-3" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-4">
            <div class="row g-4">
                
                <div class="col-md-3 border-end border-secondary border-opacity-25">
                    <label class="form-label-custom mb-3 text-accent">
                        <i class="far fa-calendar-alt me-2"></i>RANGO DE FECHAS
                    </label>
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-dark border-secondary text-secondary" style="width: 60px;">DEL</span>
                        <input type="date" id="dtmDesde" class="form-control bg-dark text-white border-secondary fw-bold" value="<?php echo $inicio_mes; ?>">
                    </div>
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark border-secondary text-secondary" style="width: 60px;">AL</span>
                        <input type="date" id="dtmHasta" class="form-control bg-dark text-white border-secondary fw-bold" value="<?php echo $hoy; ?>">
                    </div>
                </div>

                <div class="col-md-6 border-end border-secondary border-opacity-25 px-md-4">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-custom mb-0 text-accent"><i class="fas fa-wallet me-2"></i>CUENTAS</label>
                            <button class="btn btn-xs btn-link text-info text-decoration-none p-0 d-none" id="btnVerSeleccionCuentas" onclick="abrirModalSeleccionCuentas()">
                                <small>Ver selección (<span id="lblCountSelCuentas">0</span>)</small>
                            </button>
                        </div>
                        <select id="cmbCuenta" class="form-select select2-filter">
                            <option value="0">TODAS LAS CUENTAS</option>
                            <option value="multiple" class="fw-bold text-accent">-- SELECCIÓN MÚLTIPLE / AVANZADA --</option>
                        </select>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label-custom mb-0 text-accent"><i class="fas fa-users me-2"></i>USUARIOS</label>
                            <button class="btn btn-xs btn-link text-info text-decoration-none p-0 d-none" id="btnVerSeleccionUsuarios" onclick="abrirModalSeleccionUsuarios()">
                                <small>Ver selección (<span id="lblCountSelUsuarios">0</span>)</small>
                            </button>
                        </div>
                        <select id="cmbUsuario" class="form-select select2-filter">
                            <option value="">TODOS LOS USUARIOS</option>
                            <option value="multiple" class="fw-bold text-accent">-- SELECCIÓN MÚLTIPLE / AVANZADA --</option>
                        </select>
                    </div>
                </div>

                <div class="col-md-3 ps-md-4">
                    <label class="form-label-custom mb-3 text-accent">
                        <i class="fas fa-sliders-h me-2"></i>OPCIONES
                    </label>
                    <div class="mb-4">
                        <small class="text-muted d-block mb-1" style="font-size: 0.7rem;">TIPO DE MOVIMIENTO</small>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="filtroFiscal" id="optCualquiera" value="cualquiera" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="optCualquiera">Todos</label>
                            <input type="radio" class="btn-check" name="filtroFiscal" id="optFiscales" value="fiscales">
                            <label class="btn btn-outline-secondary btn-sm" for="optFiscales">Fiscal</label>
                            <input type="radio" class="btn-check" name="filtroFiscal" id="optNoFiscales" value="nofiscales">
                            <label class="btn btn-outline-secondary btn-sm" for="optNoFiscales">No Fiscal</label>
                        </div>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="chkDesglosar">
                        <label class="form-check-label text-white-50 small" for="chkDesglosar">Agrupar por Cuentas</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chkSoloSaldos">
                        <label class="form-check-label text-info fw-bold small" for="chkSoloSaldos">Solo Saldos Finales</label>
                    </div>
                </div>
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
                <option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="max-height: 55vh; background-color: #000a1a;">
        <table class="table table-dark table-hover table-sm mb-0 small" id="gridTesoreria">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 10;" id="theadTesoreria">
                </thead>
            <tbody id="tbodyTesoreria">
                </tbody>
            <tfoot class="bg-dark sticky-bottom border-top border-secondary">
                <tr id="rowTotales" class="fw-bold fs-6">
                    </tr>
            </tfoot>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

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

<div class="modal fade" id="modalSeleccionCuentas" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info border-opacity-50 shadow-lg">
            <div class="modal-header bg-info bg-opacity-10 py-2 border-bottom border-info border-opacity-25">
                <h6 class="modal-title text-white fw-bold"><i class="fas fa-wallet me-2"></i>SELECCIÓN DE CUENTAS</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3">
                    <select id="cmbAddCuenta" class="form-select select2-modal"></select>
                    <button class="btn btn-info fw-bold" onclick="agregarCuentaSeleccion()"><i class="fas fa-plus"></i></button>
                </div>
                <div class="card bg-dark border-secondary" style="height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-dark table-hover mb-0 small">
                        <tbody id="tbodySelCuentas"></tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <button class="btn btn-xs btn-link text-danger text-decoration-none" onclick="limpiarSeleccionCuentas()">
                        <i class="fas fa-trash me-1"></i> Limpiar lista
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 bg-dark py-2">
                <button type="button" class="btn btn-info w-100 fw-bold rounded-pill" onclick="confirmarSeleccionCuentas()">
                    <i class="fas fa-check me-2"></i>APLICAR FILTRO
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalSeleccionUsuarios" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info border-opacity-50 shadow-lg">
            <div class="modal-header bg-info bg-opacity-10 py-2 border-bottom border-info border-opacity-25">
                <h6 class="modal-title text-white fw-bold"><i class="fas fa-users me-2"></i>SELECCIÓN DE USUARIOS</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="input-group mb-3">
                    <select id="cmbAddUsuario" class="form-select select2-modal"></select>
                    <button class="btn btn-info fw-bold" onclick="agregarUsuarioSeleccion()"><i class="fas fa-plus"></i></button>
                </div>
                <div class="card bg-dark border-secondary" style="height: 250px; overflow-y: auto;">
                    <table class="table table-sm table-dark table-hover mb-0 small">
                        <tbody id="tbodySelUsuarios"></tbody>
                    </table>
                </div>
                <div class="text-end mt-2">
                    <button class="btn btn-xs btn-link text-danger text-decoration-none" onclick="limpiarSeleccionUsuarios()">
                        <i class="fas fa-trash me-1"></i> Limpiar lista
                    </button>
                </div>
            </div>
            <div class="modal-footer border-0 bg-dark py-2">
                <button type="button" class="btn btn-info w-100 fw-bold rounded-pill" onclick="confirmarSeleccionUsuarios()">
                    <i class="fas fa-check me-2"></i>APLICAR FILTRO
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let listadoCompleto = [];
    let currentMode = 'listado';
    let filtroUsuarioActivo = false;
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    let cuentasSeleccionadas = [];
    let usuariosSeleccionados = [];

    async function init() {
        const [respCuentas, respUsu] = await Promise.all([
            fetch('api_tesoreria_informe.php?action=listar_cuentas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }),
            fetch('api_tesoreria_informe.php?action=listar_usuarios', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        ]);
        
        const dataCuentas = await respCuentas.json();
        const dataUsu = await respUsu.json();

        const cmbCuenta = $('#cmbCuenta');
        const cmbAddCuenta = $('#cmbAddCuenta');
        const cmbUsuario = $('#cmbUsuario');
        const cmbAddUsuario = $('#cmbAddUsuario');

        if(dataCuentas.success && dataCuentas.data) {
            dataCuentas.data.forEach(c => {
                cmbCuenta.append(new Option(c.medio, c.id));
                cmbAddCuenta.append(new Option(c.medio, c.id));
            });
        }

        if(dataUsu.success && dataUsu.data) {
            dataUsu.data.forEach(u => {
                cmbUsuario.append(new Option(u, u)); 
                cmbAddUsuario.append(new Option(u, u));
            });
        }

        $('.select2-filter').select2({
            dropdownParent: $('#main-content'),
            width: '100%'
        });

        $('.select2-modal').select2({ dropdownParent: null, width: '100%', placeholder: 'Buscar...' });
        $('#cmbAddCuenta').select2({ dropdownParent: $('#modalSeleccionCuentas'), width: '100%' });
        $('#cmbAddUsuario').select2({ dropdownParent: $('#modalSeleccionUsuarios'), width: '100%' });

        $('#dtmDesde, #dtmHasta').on('change', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(buscarMovimientos, 800);
        });

        $('input[name="filtroFiscal"]').on('change', buscarMovimientos);
        $('#chkSoloSaldos, #chkDesglosar').on('change', function() {
            actualizarEstadoToggles();
            buscarMovimientos();
        });
        
        $('#cmbCuenta').on('select2:select', function(e) {
            if($(this).val() === 'multiple') abrirModalSeleccionCuentas();
            else {
                $('#btnVerSeleccionCuentas').addClass('d-none');
                cuentasSeleccionadas = [];
                actualizarEstadoToggles();
                buscarMovimientos();
            }
        });

        $('#cmbUsuario').on('select2:select', function(e) {
            if($(this).val() === 'multiple') abrirModalSeleccionUsuarios();
            else {
                $('#btnVerSeleccionUsuarios').addClass('d-none');
                usuariosSeleccionados = [];
                actualizarEstadoToggles();
                buscarMovimientos();
            }
        });

        actualizarEstadoToggles();
        buscarMovimientos();
    }

    function actualizarEstadoToggles() {
        const soloSaldos = $('#chkSoloSaldos').is(':checked');
        const multipleCuentas = ($('#cmbCuenta').val() === 'multiple');
        $('#dtmDesde, #dtmHasta, #cmbUsuario').prop('disabled', soloSaldos).toggleClass('opacity-50', soloSaldos);
        if (soloSaldos) {
            $('#chkDesglosar').prop('checked', false).prop('disabled', true);
        } else {
            if (multipleCuentas) {
                $('#chkDesglosar').prop('checked', true).prop('disabled', true);
            } else {
                $('#chkDesglosar').prop('disabled', false);
            }
        }
    }

    window.buscarMovimientos = async function() {
        let idsCuentas = ($('#cmbCuenta').val() === 'multiple') ? cuentasSeleccionadas.map(c => c.id) : [$('#cmbCuenta').val()];
        if (idsCuentas.length === 0 && $('#cmbCuenta').val() === 'multiple') idsCuentas = [0];

        let valUsu = $('#cmbUsuario').val();
        let idsUsuarios = (valUsu === 'multiple') ? usuariosSeleccionados.map(u => u.id) : (valUsu ? [valUsu] : []);
        filtroUsuarioActivo = idsUsuarios.length > 0;

        const soloSaldos = $('#chkSoloSaldos').is(':checked');
        const payload = {
            ids_cuentas: JSON.stringify(idsCuentas),
            usuarios: JSON.stringify(idsUsuarios),
            desde: $('#dtmDesde').val(),
            hasta: $('#dtmHasta').val(),
            tipo_fiscal: $('input[name="filtroFiscal"]:checked').val(),
            solo_saldos: soloSaldos,
            desglosar: $('#chkDesglosar').is(':checked') || ($('#cmbCuenta').val() === 'multiple' && !soloSaldos)
        };

        renderGridLoading(soloSaldos);

        try {
            const formData = new FormData();
            for (const key in payload) formData.append(key, payload[key]);

            const resp = await fetch('api_tesoreria_informe.php?action=consultar', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData
            });
            const res = await resp.json();

            if (res.success) {
                listadoCompleto = res.data || [];
                currentMode = res.mode;
                pagActual = 1;
                renderPagina();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (err) {
            $('#tbodyTesoreria').html(`<tr><td colspan="10" class="text-center py-3 text-danger">Error de conexión</td></tr>`);
        }
    }

    function renderPagina() {
        const total = listadoCompleto.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoCompleto.slice(inicio, inicio + pagTam);

        renderTabla(segmento, currentMode);
        renderTarjetas(segmento, currentMode);
        sincronizarPaginacion(total);
    }

    function renderTabla(data, mode) {
        const tbody = document.getElementById('tbodyTesoreria');
        const tfoot = document.getElementById('rowTotales');
        tbody.innerHTML = '';
        tfoot.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="text-center py-5 text-muted">No se encontraron registros.</td></tr>`;
            return;
        }

        let html = '';
        if (mode === 'resumen') {
            let totalGeneral = 0;
            listadoCompleto.forEach(row => totalGeneral += parseFloat(row.Saldo));
            data.forEach(row => {
                const saldo = parseFloat(row.Saldo);
                html += `<tr><td class="ps-3 text-white-50">${row.ID}</td><td class="fw-bold text-white">${row.Cuenta}</td><td class="text-end pe-3 fw-bold ${saldo < 0 ? 'text-danger' : 'text-success'}">$ ${formatMoney(row.Saldo)}</td></tr>`;
            });
            tfoot.innerHTML = `<td colspan="2" class="text-end pe-3 text-uppercase text-white-50">Total Consolidado:</td><td class="text-end pe-3 fw-bold text-info fs-5">$ ${formatMoney(totalGeneral)}</td>`;
        } else {
            data.forEach(row => {
                if (row.Tipo === 'SEPARATOR') {
                    html += `<tr class="bg-secondary bg-opacity-25"><td colspan="10" class="fw-bold text-warning ps-3 py-2 text-uppercase"><i class="fas fa-wallet me-2"></i>${row.Cuenta}</td></tr>`;
                } else {
                    const saldoCls = parseFloat(row.Saldo) < 0 ? 'text-danger' : 'text-white';
                    const debe = parseFloat(row.Debe) > 0 ? `<span class="text-info">$ ${formatMoney(row.Debe)}</span>` : '-';
                    const haber = parseFloat(row.Haber) > 0 ? `<span class="text-warning">$ ${formatMoney(row.Haber)}</span>` : '-';
                    let bgRow = '';
                    if (row.Tipo === 'SALDO_INICIAL') bgRow = 'bg-info bg-opacity-10';
                    else if (row.Detalle === 'SALDO ANTERIOR') bgRow = 'bg-secondary bg-opacity-10';

                    html += `<tr class="${bgRow}">
                        <td class="ps-3 text-nowrap">${formatDate(row.Fecha)}</td>
                        <td class="text-center"><span class="badge bg-dark border border-secondary text-muted small">${row.NumAsiento || '-'}</span></td>
                        <td class="text-accent small text-truncate" style="max-width: 200px;">${row.Cuenta}</td>
                        <td class="small text-white-50 text-truncate" style="max-width: 300px;" title="${row.Detalle}">${row.Detalle}</td>
                        <td class="text-end">${debe}</td>
                        <td class="text-end">${haber}</td>
                        <td class="text-center">${row.Fiscal_Badge || '-'}</td>
                        <td class="text-center text-white-50 small">${row.Usuario || '-'}</td>
                        <td class="text-end pe-3 fw-bold ${saldoCls}">$ ${formatMoney(row.Saldo)}</td>
                        <td class="text-center">
                            ${row.NumAsiento > 0 ? `<button class="btn btn-xs btn-link text-muted" onclick="editarAsiento(${row.NumAsiento})"><i class="fas fa-search"></i></button>` : ''}
                        </td>
                    </tr>`;
                }
            });
            if(mode !== 'desglosado' && listadoCompleto.length > 0) {
                // El backend ya filtra por usuario en el SQL: si hay filtro activo, el Saldo de
                // la última fila es el saldo acumulado solo de ese/esos usuario/s (no mezcla
                // datos de otros usuarios).
                const ultimo = listadoCompleto[listadoCompleto.length-1];
                const etiqueta = filtroUsuarioActivo ? 'Total Usuario(s) Filtrado(s):' : 'Saldo Final Global:';
                tfoot.innerHTML = `<td colspan="8" class="text-end pe-3 text-uppercase text-white-50">${etiqueta}</td><td class="text-end pe-3 fw-bold text-accent fs-5">$ ${formatMoney(ultimo.Saldo)}</td><td></td>`;
            }
        }
        tbody.innerHTML = html;
    }

    function renderTarjetas(data, mode) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(row => {
            if (row.Tipo === 'SEPARATOR') {
                const sep = document.createElement('div');
                sep.className = 'text-warning fw-bold py-2 px-1 text-uppercase small border-bottom border-secondary mb-2 mt-3';
                sep.innerHTML = `<i class="fas fa-wallet me-2"></i>${row.Cuenta}`;
                wrap.appendChild(sep);
                return;
            }

            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            
            if (mode === 'resumen') {
                const saldo = parseFloat(row.Saldo);
                card.innerHTML = `
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">${row.Cuenta}</div>
                        <span class="badge bg-dark border border-secondary text-white-50 small">ID: ${row.ID}</span>
                    </div>
                    <div class="erp-card-main text-end py-2">
                        <span class="fs-4 fw-bold ${saldo < 0 ? 'text-danger' : 'text-success'}">$ ${formatMoney(row.Saldo)}</span>
                    </div>
                `;
            } else {
                const saldoCls = parseFloat(row.Saldo) < 0 ? 'text-danger' : 'text-white';
                card.innerHTML = `
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent small">${row.Cuenta}</div>
                        <span class="badge bg-dark border border-secondary text-white-50" style="font-size:10px">${formatDate(row.Fecha)}</span>
                    </div>
                    <div class="erp-card-main">
                        <div class="mb-2">
                            <span class="fw-bold text-white small">${row.Detalle}</span>
                        </div>
                        <div class="row g-1">
                            <div class="col-6">
                                <small class="text-muted d-block" style="font-size:9px">MOVIMIENTOS</small>
                                <span class="text-info small">D: ${row.Debe > 0 ? formatMoney(row.Debe) : '-'}</span><br>
                                <span class="text-warning small">H: ${row.Haber > 0 ? formatMoney(row.Haber) : '-'}</span>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block" style="font-size:9px">SALDO PARCIAL</small>
                                <span class="fw-bold ${saldoCls}">$ ${formatMoney(row.Saldo)}</span>
                            </div>
                        </div>
                    </div>
                    <div class="erp-card-footer d-flex justify-content-between align-items-center mt-2 pt-2 border-top border-secondary">
                        <small class="text-white-50">Seat: ${row.NumAsiento || '-'}</small>
                        <small class="text-white-50"><i class="fas fa-user me-1"></i>${row.Usuario || '-'}</small>
                    </div>
                `;
            }
            wrap.appendChild(card);
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
        document.getElementById('moduloInformeTesoreria').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    function renderGridLoading(soloSaldos) {
        const thead = $('#theadTesoreria');
        const tbody = $('#tbodyTesoreria');
        thead.empty();
        $('#rowTotales').empty();

        if (soloSaldos) {
            thead.html(`<tr><th class="py-2 ps-3" width="80">ID</th><th class="py-2">CUENTA DE TESORERÍA</th><th class="text-end py-2 pe-3">SALDO FINAL</th></tr>`);
        } else {
            thead.html(`<tr>
                <th class="py-2 ps-3">FECHA</th>
                <th class="py-2">ASIENTO</th>
                <th class="py-2">CUENTA</th>
                <th class="py-2">DETALLE / OBSERVACIÓN</th>
                <th class="text-end py-2">DEBE</th>
                <th class="text-end py-2">HABER</th>
                <th class="text-center py-2">FISCAL</th>
                <th class="text-center py-2">USUARIO</th>
                <th class="text-end py-2 pe-3">SALDO</th>
                <th width="50"></th>
            </tr>`);
        }
        tbody.html(`<tr><td colspan="10" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Consultando...</td></tr>`);
    }

    // MODAL FUNCTIONS MANTENIDAS
    window.abrirModalSeleccionCuentas = function() { renderTablaCuentas(); $('#modalSeleccionCuentas').modal('show'); }
    window.agregarCuentaSeleccion = function() {
        const id = $('#cmbAddCuenta').val();
        const texto = $('#cmbAddCuenta option:selected').text();
        if (!id || cuentasSeleccionadas.some(c => c.id == id)) return;
        cuentasSeleccionadas.push({id: id, texto: texto});
        renderTablaCuentas();
    }
    window.quitarCuentaSeleccion = function(idx) { cuentasSeleccionadas.splice(idx, 1); renderTablaCuentas(); }
    window.limpiarSeleccionCuentas = function() { cuentasSeleccionadas = []; renderTablaCuentas(); }
    function renderTablaCuentas() {
        const tbody = $('#tbodySelCuentas');
        tbody.empty();
        if (cuentasSeleccionadas.length === 0) tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">Ninguna cuenta seleccionada</td></tr>');
        else {
            cuentasSeleccionadas.forEach((c, i) => {
                tbody.append(`<tr><td class="ps-3 align-middle text-white">${c.texto}</td><td class="text-center"><button class="btn btn-xs text-danger" onclick="quitarCuentaSeleccion(${i})"><i class="fas fa-trash"></i></button></td></tr>`);
            });
        }
        $('#lblCountSelCuentas').text(cuentasSeleccionadas.length);
    }
    window.confirmarSeleccionCuentas = function() {
        $('#modalSeleccionCuentas').modal('hide');
        if (cuentasSeleccionadas.length > 0) $('#btnVerSeleccionCuentas').removeClass('d-none');
        else { $('#btnVerSeleccionCuentas').addClass('d-none'); $('#cmbCuenta').val('0').trigger('change.select2'); }
        actualizarEstadoToggles();
        buscarMovimientos();
    }

    window.abrirModalSeleccionUsuarios = function() { renderTablaUsuarios(); $('#modalSeleccionUsuarios').modal('show'); }
    window.agregarUsuarioSeleccion = function() {
        const id = $('#cmbAddUsuario').val();
        const texto = $('#cmbAddUsuario option:selected').text();
        if (!id || usuariosSeleccionados.some(u => u.id == id)) return;
        usuariosSeleccionados.push({id: id, texto: texto});
        renderTablaUsuarios();
    }
    window.quitarUsuarioSeleccion = function(idx) { usuariosSeleccionados.splice(idx, 1); renderTablaUsuarios(); }
    window.limpiarSeleccionUsuarios = function() { usuariosSeleccionados = []; renderTablaUsuarios(); }
    function renderTablaUsuarios() {
        const tbody = $('#tbodySelUsuarios');
        tbody.empty();
        if (usuariosSeleccionados.length === 0) tbody.html('<tr><td colspan="2" class="text-center text-muted py-3">Ningún usuario seleccionado</td></tr>');
        else {
            usuariosSeleccionados.forEach((u, i) => {
                tbody.append(`<tr><td class="ps-3 align-middle text-white">${u.texto}</td><td class="text-center"><button class="btn btn-xs text-danger" onclick="quitarUsuarioSeleccion(${i})"><i class="fas fa-trash"></i></button></td></tr>`);
            });
        }
        $('#lblCountSelUsuarios').text(usuariosSeleccionados.length);
    }
    window.confirmarSeleccionUsuarios = function() {
        $('#modalSeleccionUsuarios').modal('hide');
        if (usuariosSeleccionados.length > 0) $('#btnVerSeleccionUsuarios').removeClass('d-none');
        else { $('#btnVerSeleccionUsuarios').addClass('d-none'); $('#cmbUsuario').val('').trigger('change.select2'); }
        buscarMovimientos();
    }

    window.exportarExcel = function() {
        let idsCuentas = ($('#cmbCuenta').val() === 'multiple') ? cuentasSeleccionadas.map(c => c.id) : [$('#cmbCuenta').val()];
        let valUsu = $('#cmbUsuario').val();
        let idsUsuarios = (valUsu === 'multiple') ? usuariosSeleccionados.map(u => u.id) : (valUsu ? [valUsu] : []);
        const params = new URLSearchParams({
            action: 'exportar_excel',
            ids_cuentas: JSON.stringify(idsCuentas),
            usuarios: JSON.stringify(idsUsuarios),
            desde: $('#dtmDesde').val(),
            hasta: $('#dtmHasta').val(),
            tipo_fiscal: $('input[name="filtroFiscal"]:checked').val(),
            solo_saldos: $('#chkSoloSaldos').is(':checked'),
            desglosar: $('#chkDesglosar').is(':checked') || ($('#cmbCuenta').val() === 'multiple' && !$('#chkSoloSaldos').is(':checked'))
        });
        window.open(`api_tesoreria_informe.php?${params.toString()}`, '_blank');
    }

    function formatMoney(amount) { return parseFloat(amount).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }
    function formatDate(dateStr) { if(!dateStr || dateStr === '-') return '-'; const [y, m, d] = dateStr.split('-'); return `${d}/${m}/${y}`; }
    window.editarAsiento = function(nro) { Swal.fire('Info', `Funcionalidad de ver asiento #${nro} en desarrollo`, 'info'); }

    init();
})();
</script>