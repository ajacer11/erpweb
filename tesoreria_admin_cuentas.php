<?php
/**
 * tesoreria_admin_cuentas.php - GESTIÓN DE MEDIOS DE PAGO Y CUENTAS DE TESORERÍA
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloTesoreriaCuentas">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR CUENTAS DE TESORERÍA</h4>
            <small class="text-accent opacity-75">Configuración de medios de pago, cajas y cuentas contables asociadas</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill" onclick="loadModule('tesoreria_nueva_cuenta.php')">
                <i class="fas fa-plus me-2"></i>NUEVA CUENTA
            </button>
            <button class="btn btn-outline-success border-0" onclick="exportarTesoreriaExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label-custom">ESTADO</label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroEstado" id="fHab" value="1" checked onchange="getMedios()">
                            <label class="form-check-label small text-white" for="fHab">HABILITADAS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroEstado" id="fDes" value="0" onchange="getMedios()">
                            <label class="form-check-label small text-white" for="fDes">INACTIVAS</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="filtroEstado" id="fTod" value="todas" onchange="getMedios()">
                            <label class="form-check-label small text-white" for="fTod">TODAS</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">TIPO DE CUENTA</label>
                    <select id="cmbTipoFiltro" class="form-select select2-filter" onchange="getMedios()">
                        <option value="">TODOS LOS TIPOS</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">BÚSQUEDA RÁPIDA</label>
                    <input type="text" id="txtBuscarTeso" class="form-control" placeholder="Nombre de cuenta o medio...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 fw-bold" onclick="getMedios()">
                        <i class="fas fa-search me-2"></i>BUSCAR
                    </button>
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
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 60vh;">
        <table class="table table-dark table-hover align-middle mb-0" id="tblTesoreria">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="50" class="ps-3">№</th>
                    <th>DESCRIPCIÓN</th>
                    <th>TIPO</th>
                    <th class="text-center">COBROS</th>
                    <th class="text-center">PAGOS</th>
                    <th class="text-center">ORIGEN</th>
                    <th class="text-center">DESTINO</th>
                    <th>CUENTA CONTABLE</th>
                    <th width="150" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyTesoreria">
                </tbody>
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

<script>
(function() {
    let listadoCompleto = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    async function init() {
        try {
            const resp = await fetch('api_tesoreria_cuentas.php?action=get_tipos', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const tipos = await resp.json();
            const cmb = document.getElementById('cmbTipoFiltro');
            tipos.forEach(t => {
                if(t.tipodecuenta) {
                    const opt = document.createElement('option');
                    opt.value = t.tipodecuenta;
                    opt.textContent = t.tipodecuenta.toUpperCase();
                    cmb.appendChild(opt);
                }
            });
        } catch (e) { console.error("Error cargando tipos", e); }

        $('.select2-filter').select2({ dropdownParent: $('#main-content'), width: '100%' });
        
        $('#txtBuscarTeso').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => getMedios(), 500);
        });

        getMedios();
    }

    window.getMedios = async function() {
        const estado = document.querySelector('input[name="filtroEstado"]:checked').value;
        const tipo = document.getElementById('cmbTipoFiltro').value;
        const busqueda = document.getElementById('txtBuscarTeso').value;

        const url = `api_tesoreria_cuentas.php?action=list&estado=${estado}&tipo=${encodeURIComponent(tipo)}&query=${encodeURIComponent(busqueda)}`;
        
        try {
            $('#loading-overlay').show();
            const resp = await fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            listadoCompleto = await resp.json() || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error(error);
            $('#tbodyTesoreria').html('<tr><td colspan="9" class="text-center py-4 text-danger">Error de conexión</td></tr>');
        } finally {
            $('#loading-overlay').hide();
        }
    }

    function renderPagina() {
        const total = listadoCompleto.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoCompleto.slice(inicio, inicio + pagTam);

        renderTabla(segmento);
        renderTarjetas(segmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyTesoreria');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No se encontraron cuentas.</td></tr>';
            return;
        }

        data.forEach(m => {
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            if(m.habilitado == 0) tr.style.opacity = '0.5';

            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${m.id}</td>
                <td class="fw-bold text-white">${m.medio}</td>
                <td><span class="badge bg-dark border border-secondary text-accent" style="font-size:9px">${m.tipodecuenta}</span></td>
                <td class="text-center">${m.habilitadocobro == 1 ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
                <td class="text-center">${m.habilitadopagos == 1 ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>'}</td>
                <td class="text-center">${m.mostrarenorigen == 1 ? '<i class="fas fa-arrow-up text-info"></i>' : '-'}</td>
                <td class="text-center">${m.mostrarendestino == 1 ? '<i class="fas fa-arrow-down text-warning"></i>' : '-'}</td>
                <td class="small">
                    <span class="text-white-50">${m.cuentacontable}</span><br>
                    <small class="text-muted">${m.nombre_cuenta_contable || 'Sin asignar'}</small>
                </td>
                <td class="text-center pe-3">
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-info border-0" onclick="loadModule('tesoreria_nueva_cuenta.php?id=${m.id}')" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning border-0" onclick="clonarCuenta(${m.id}, '${m.medio}')" title="Clonar">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger border-0" onclick="eliminarCuenta(${m.id}, '${m.medio}')" title="Eliminar">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(m => {
            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            if(m.habilitado == 0) card.style.opacity = '0.6';

            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${m.medio}</div>
                    <span class="badge bg-dark border border-secondary text-info" style="font-size:9px">${m.tipodecuenta}</span>
                </div>
                <div class="erp-card-main">
                    <div class="row g-2">
                        <div class="col-6">
                            <small class="text-muted d-block">ESTADO OPERATIVO</small>
                            <span class="small ${m.habilitadocobro == 1 ? 'text-success' : 'text-danger'}">Cobros: ${m.habilitadocobro == 1 ? 'SÍ' : 'NO'}</span><br>
                            <span class="small ${m.habilitadopagos == 1 ? 'text-success' : 'text-danger'}">Pagos: ${m.habilitadopagos == 1 ? 'SÍ' : 'NO'}</span>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">CONTABILIDAD</small>
                            <span class="text-white-50 small">${m.cuentacontable}</span><br>
                            <span class="text-muted" style="font-size:10px">${m.nombre_cuenta_contable || 'S/A'}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-info flex-fill fw-bold" onclick="loadModule('tesoreria_nueva_cuenta.php?id=${m.id}')">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                    <button class="btn btn-sm btn-outline-warning" onclick="clonarCuenta(${m.id}, '${m.medio}')">
                        <i class="fas fa-copy"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarCuenta(${m.id}, '${m.medio}')">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> cuentas`;

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
        document.getElementById('moduloTesoreriaCuentas').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    window.eliminarCuenta = async function(id, nombre) {
        const confirm = await Swal.fire({
            title: '¿Eliminar cuenta?',
            text: `Se borrará la cuenta "${nombre}". Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'ELIMINAR',
            cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });

        if(confirm.isConfirmed) {
            const resp = await fetch(`api_tesoreria_cuentas.php?action=delete&id=${id}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            if(res.success) {
                Swal.fire({ title: 'Eliminado', text: 'La cuenta se quitó correctamente.', icon: 'success', background: '#001020', color: '#fff' });
                getMedios();
            } else {
                Swal.fire({ title: 'Atención', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
            }
        }
    }

    window.clonarCuenta = async function(id, nombre) {
        const confirm = await Swal.fire({
            title: '¿Clonar cuenta?',
            text: `Se creará una copia idéntica de "${nombre}".`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, CLONAR',
            background: '#001020', color: '#fff'
        });
        if(confirm.isConfirmed) {
            loadModule(`tesoreria_nueva_cuenta.php?clone_id=${id}`);
        }
    }

    window.exportarTesoreriaExcel = function() {
        const estado = document.querySelector('input[name="filtroEstado"]:checked').value;
        const tipo = document.getElementById('cmbTipoFiltro').value;
        window.open(`api_tesoreria_cuentas.php?action=export&estado=${estado}&tipo=${encodeURIComponent(tipo)}`);
    }

    init();
})();
</script>