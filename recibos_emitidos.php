<?php
/**
 * recibos_emitidos.php - ADMINISTRADOR DE COBROS REALIZADOS
 * Actualizado: Paginación en memoria (SPA) y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">COBROS REALIZADOS</h4>
            <small class="text-accent opacity-75">Gestión administrativa y contable de cobranzas</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm px-3 rounded-pill fw-bold" id="btn-exportar">
                <i class="fas fa-file-excel me-2"></i><span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold px-4 rounded-pill" id="btn-buscar">
                <i class="fas fa-sync-alt me-2"></i><span class="d-none d-sm-inline">ACTUALIZAR</span>
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-custom">FECHAS</label>
                    <input type="date" id="f-desde" class="form-control form-control-sm mb-2" value="<?= date('Y-m-d') ?>">
                    <input type="date" id="f-hasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
                    <div class="mt-2">
                        <div class="form-check small"><input class="form-check-input filter-auto" type="checkbox" id="chk-ocultar-anulados" checked><label class="form-check-label text-white" for="chk-ocultar-anulados">Ocultar Anulados</label></div>
                        <div class="form-check small"><input class="form-check-input filter-auto" type="checkbox" id="chk-solo-sin-imputar"><label class="form-check-label text-white" for="chk-solo-sin-imputar">Sin Imputar</label></div>
                    </div>
                </div>

                <div class="col-md-5 border-start border-secondary px-4">
                    <label class="form-label-custom">CLIENTE / ENTIDAD</label>
                    <div class="form-check small mb-2">
                        <input class="form-check-input filter-auto" type="radio" name="r-entidad" id="r-todos" value="todos" checked>
                        <label class="form-check-label text-muted" for="r-todos">Ver todos los clientes</label>
                    </div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <input class="form-check-input filter-auto" type="radio" name="r-entidad" id="r-cliente" value="cliente">
                        <div class="flex-grow-1"><select id="cmb-cliente" class="form-control filter-auto"></select></div>
                    </div>
                </div>

                <div class="col-md-4 border-start border-secondary px-4">
                    <label class="form-label-custom">SEGMENTACIÓN</label>
                    <div class="input-group input-group-sm mb-2">
                        <div class="input-group-text bg-dark border-secondary"><input class="form-check-input filter-auto" type="checkbox" id="chk-usuario"></div>
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width: 100px;">USUARIO</span>
                        <select id="cmb-usuario" class="form-select filter-auto" disabled></select>
                    </div>
                    <div class="input-group input-group-sm mb-2">
                        <div class="input-group-text bg-dark border-secondary"><input class="form-check-input filter-auto" type="checkbox" id="chk-rama"></div>
                        <span class="input-group-text bg-dark border-secondary text-muted" style="width: 100px;">RAMA</span>
                        <select id="cmb-rama" class="form-select filter-auto" disabled></select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-pagination" id="paginacionRecTop">
        <div class="erp-pag-info" id="pagInfoTop">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInputTop" min="1" value="1" title="Ir a página">
                <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)" title="Primera">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual - 1)" title="Anterior">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual + 1)" title="Siguiente">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)" title="Última">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 60vh; min-height: 300px;">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="sticky-top">
                    <tr>
                        <th class="ps-3"># REC</th>
                        <th>FECHA</th>
                        <th>CLIENTE</th>
                        <th class="text-end">TOTAL</th>
                        <th>IMPUTADO A</th>
                        <th>MEDIO 1</th><th class="text-end">IMP. 1</th>
                        <th>MEDIO 2</th><th class="text-end">IMP. 2</th>
                        <th class="text-center">FISC</th>
                        <th class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbody-recibos">
                    <tr><td colspan="11" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="erp-cards-wrap" id="listaRecibosCards">
        <div class="text-center text-muted p-4">
            <div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...
        </div>
    </div>

    <div class="erp-pagination" id="paginacionRec">
        <div class="erp-pag-info" id="pagInfo">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInput" min="1" value="1" title="Ir a página">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)" title="Primera">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual - 1)" title="Anterior">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual + 1)" title="Siguiente">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)" title="Última">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
                <option value="200">200</option>
            </select>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleRecibo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">Detalle de Cobro #<span id="det-nro-recibo"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="det-banner-anulado" class="alert alert-danger border-danger bg-danger bg-opacity-10 text-center mb-3 d-none">
                    <h4 class="alert-heading fw-bold mb-0"><i class="fas fa-ban me-2"></i>COMPROBANTE ANULADO</h4>
                </div>
                <div class="row mb-4">
                    <div class="col-md-6"><small class="text-muted d-block">CLIENTE</small><span id="det-cliente" class="fw-bold text-white fs-5"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block">FECHA</small><span id="det-fecha" class="text-white"></span></div>
                    <div class="col-md-3"><small class="text-muted d-block">TOTAL COBRADO</small><span id="det-total" class="text-accent fw-bold fs-5"></span></div>
                </div>
                
                <h6 class="text-warning border-bottom border-secondary pb-1 small fw-bold">IMPUTACIONES A FACTURAS</h6>
                <table class="table table-sm table-dark mb-4 small">
                    <thead><tr><th>COMPROBANTE</th><th class="text-end">PAGADO</th></tr></thead>
                    <tbody id="det-tbody-facturas"></tbody>
                </table>
                
                <h6 class="text-info border-bottom border-secondary pb-1 small fw-bold">VALORES Y MEDIOS DE PAGO</h6>
                <table class="table table-sm table-dark mb-0 small">
                    <thead><tr><th>TIPO / CONCEPTO</th><th>NÚMERO / INFO</th><th class="text-end">IMPORTE</th><th>VENCE</th></tr></thead>
                    <tbody id="det-tbody-valores"></tbody>
                </table>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-danger" id="btn-anular-desde-detalle"><i class="fas fa-trash-alt me-2"></i>ANULAR RECIBO</button>
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">CERRAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    /* ============================================================
       ESTADO & PAGINACIÓN
    ============================================================ */
    var pagActual = 1;
    var pagTotal  = 1;
    var pagTam    = 20;
    var listado   = [];

    window.pagActual = 1;
    window.pagTotal  = 1;

    async function init() {
        await cargarCombos();
        configurarEventos();
        $('#btn-buscar').click(buscarRecibos);
        $('#btn-exportar').click(exportarExcel);
        buscarRecibos();
    }

    async function cargarCombos() {
        const [resUs, resR] = await Promise.all([
            fetch('api_helpers.php?action=get_usuarios', { headers: commonHeaders }).then(r => r.json()),
            fetch('api_helpers.php?action=get_ramas_clientes', { headers: commonHeaders }).then(r => r.json())
        ]);
        if (resUs.success) resUs.data.forEach(x => $('#cmb-usuario').append(new Option(x.usuario, x.usuario)));
        if (resR.success) resR.data.forEach(x => $('#cmb-rama').append(new Option(x.rama, x.id)));

        $('#cmb-cliente').select2({
            placeholder: 'CLIENTE...',
            dropdownParent: $('#main-content'),
            allowClear: true,
            ajax: {
                url: 'api_cobros.php?action=search_clientes',
                dataType: 'json', headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data.map(c => ({ id: c.id, text: c.razon })) })
            },
            width: '100%'
        }).on('change', function() { buscarRecibos(); });
    }

    function configurarEventos() {
        $('input[name="r-entidad"]').change(function () { 
            $('#cmb-cliente').prop('disabled', $(this).val() !== 'cliente'); 
            if($(this).val() === 'todos') buscarRecibos();
        });

        $('#chk-usuario').change(e => {
            const isChecked = $(e.target).is(':checked');
            $('#cmb-usuario').prop('disabled', !isChecked);
            if(!isChecked) buscarRecibos();
        });

        $('#chk-rama').change(e => {
            const isChecked = $(e.target).is(':checked');
            $('#cmb-rama').prop('disabled', !isChecked);
            if(!isChecked) buscarRecibos();
        });

        $('.filter-auto, #f-desde, #f-hasta').on('change', function() {
            buscarRecibos();
        });
        
        $('#tbody-recibos').on('click', 'tr', function () { 
            $('#tbody-recibos tr').removeClass('table-active'); 
            $(this).addClass('table-active'); 
        });
    }

    /* ============================================================
       FETCH & RENDERIZADO SPA
    ============================================================ */
    async function buscarRecibos() {
        const filters = getFilters();
        $('#tbody-recibos').html('<tr><td colspan="11" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</td></tr>');
        $('#listaRecibosCards').html('<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</div>');

        try {
            const res = await fetch('api_recibos.php?action=list', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters)
            }).then(r => r.json());
            if (res.success) { 
                listado = res.data; 
            } else {
                listado = [];
            }
        } catch (e) { 
            console.error(e); 
            listado = [];
        }

        pagActual = 1;
        window.pagActual = 1;
        renderPagina();
    }

    function renderPagina() {
        const total = listado.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual;
        window.pagTotal = pagTotal;

        const datos = listado.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        actualizarPaginacion(total);
    }

    function renderTabla(datos) {
        const tbody = $('#tbody-recibos').empty();
        if(datos.length === 0) {
            tbody.append('<tr><td colspan="11" class="text-center p-4 text-muted small">No se encontraron resultados.</td></tr>');
            return;
        }

        datos.forEach((r, idx) => {
            const isAnulado = r.Estado === 'ANULADO';
            const rowClass = isAnulado ? 'row-anulado opacity-50 italic' : '';
            // El índice absoluto dentro de listado total para las acciones:
            const absoluteIdx = (pagActual - 1) * pagTam + idx;

            tbody.append(`
            <tr data-idx="${absoluteIdx}" class="${rowClass} pointer">
                <td class="ps-3 fw-bold">${r['Num Recibo']}</td>
                <td>${r.Fecha}</td>
                <td><div class="text-truncate" style="max-width: 150px;">${r.Cliente}</div></td>
                <td class="text-end fw-bold text-accent">$ ${parseFloat(r.Total).toFixed(2)}</td>
                <td><small>${r['Imputado a'] || 'A CUENTA'}</small></td>
                <td><small>${r['M. de Pago 1']}</small></td>
                <td class="text-end">$ ${parseFloat(r['Importe MP1']).toFixed(2)}</td>
                <td><small>${r['M. de Pago 2']}</small></td>
                <td class="text-end">$ ${parseFloat(r['Importe MP2']).toFixed(2)}</td>
                <td class="text-center">${r.Fiscal == 1 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm shadow-sm">
                        <button class="btn btn-info" onclick="window.verDetalleRecibo(${absoluteIdx})" title="Ver Detalle"><i class="fas fa-eye"></i></button>
                        ${!isAnulado ? `
                            <button class="btn btn-warning" onclick="window.modificarFechaRecibo(${absoluteIdx})" title="Cambiar Fecha"><i class="fas fa-calendar-alt"></i></button>
                            <button class="btn btn-secondary" onclick="window.cambiarFiscalRecibo(${absoluteIdx})" title="Fiscalidad"><i class="fas fa-balance-scale"></i></button>
                            <button class="btn btn-outline-warning" onclick="window.quitarImputacionRecibo(${absoluteIdx})" title="Desimputar"><i class="fas fa-unlink"></i></button>
                            <button class="btn btn-danger" onclick="window.anularRecibo(${absoluteIdx})" title="Anular"><i class="fas fa-trash-alt"></i></button>
                        ` : ''}
                    </div>
                </td>
            </tr>`);
        });
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('listaRecibosCards');
        wrap.innerHTML = '';

        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS</div>';
            return;
        }

        datos.forEach((r, idx) => {
            const isAnulado = r.Estado === 'ANULADO';
            const opacidad = isAnulado ? 'opacity:0.6;' : '';
            const inhBadge = isAnulado ? '<span class="erp-card-badge ms-2" style="background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;">ANULADO</span>' : '';
            const absoluteIdx = (pagActual - 1) * pagTam + idx;

            let html = `
            <div class="erp-card" style="${opacidad}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title d-inline-block">RECIBO #${r['Num Recibo']}</div>
                        ${inhBadge}
                    </div>
                    <span class="erp-card-code">${r.Fecha}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Cliente</span>
                    <span class="erp-card-value fw-bold text-truncate" style="max-width: 60%;">${r.Cliente}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Total</span>
                    <span class="erp-card-value price">$ ${parseFloat(r.Total).toFixed(2)}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Imputado A</span>
                    <span class="erp-card-value">${r['Imputado a'] || 'A CUENTA'}</span>
                </div>
                ${parseFloat(r['Importe MP1']) > 0 ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">${r['M. de Pago 1']}</span>
                    <span class="erp-card-value">$ ${parseFloat(r['Importe MP1']).toFixed(2)}</span>
                </div>` : ''}
                ${parseFloat(r['Importe MP2']) > 0 ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">${r['M. de Pago 2']}</span>
                    <span class="erp-card-value">$ ${parseFloat(r['Importe MP2']).toFixed(2)}</span>
                </div>` : ''}

                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-info btn-sm fw-bold flex-fill" onclick="window.verDetalleRecibo(${absoluteIdx})">
                        <i class="fas fa-eye me-1"></i> DETALLE
                    </button>`;

            if (!isAnulado) {
                html += `
                    <button class="btn btn-warning btn-sm flex-fill" onclick="window.modificarFechaRecibo(${absoluteIdx})" title="Cambiar Fecha">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                    <button class="btn btn-secondary btn-sm flex-fill" onclick="window.cambiarFiscalRecibo(${absoluteIdx})" title="Fiscalidad">
                        <i class="fas fa-balance-scale"></i>
                    </button>
                    <button class="btn btn-outline-warning btn-sm flex-fill" onclick="window.quitarImputacionRecibo(${absoluteIdx})" title="Desimputar">
                        <i class="fas fa-unlink"></i>
                    </button>
                    <button class="btn btn-danger btn-sm flex-fill" onclick="window.anularRecibo(${absoluteIdx})" title="Anular">
                        <i class="fas fa-trash-alt"></i>
                    </button>`;
            }

            html += `</div></div>`;
            wrap.innerHTML += html;
        });
    }

    /* ============================================================
       CONTROLES DE PAGINACIÓN (SINCRONIZADOS)
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> recibos`;
        const visible = pagTotal > 1 ? 'flex' : 'none';

        ['', 'Top'].forEach(function (sfx) {
            var el = document.getElementById('pagInfo' + sfx);
            if (el) el.innerHTML = infoHtml;

            var dis = function (id, val) { var e = document.getElementById(id + sfx); if (e) e.disabled = val; };
            dis('btnPagPrim', pagActual <= 1);
            dis('btnPagAnt',  pagActual <= 1);
            dis('btnPagSig',  pagActual >= pagTotal);
            dis('btnPagUlt',  pagActual >= pagTotal);

            var cont = document.getElementById('pagBotones' + sfx);
            if (cont) {
                cont.innerHTML = '';
                if (pagTotal > 1) {
                    var ini = Math.max(1, pagActual - 2);
                    var fin = Math.min(pagTotal, ini + 4);
                    if (fin - ini < 4) ini = Math.max(1, fin - 4);
                    for (var i = ini; i <= fin; i++) {
                        var btn = document.createElement('button');
                        btn.className = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                        btn.textContent = i;
                        btn.onclick = (function (p) { return function () { pagGo(p); }; })(i);
                        cont.appendChild(btn);
                    }
                }
            }

            var jump = document.getElementById('pagJumpInput' + sfx);
            if (jump) { jump.value = pagActual; jump.max = pagTotal; }

            var sel = document.getElementById('pagTamano' + sfx);
            if (sel) sel.value = pagTam;

            var wrap = document.getElementById(sfx === 'Top' ? 'paginacionRecTop' : 'paginacionRec');
            if (wrap) wrap.style.display = visible;
        });
    }

    window.pagGo = function (n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual = n;
        window.pagActual = n;
        renderPagina();
        // Opcional: Volver al inicio de la tabla/lista al cambiar de página
        document.getElementById('main-content')?.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.pagJump = function () {
        const v = parseInt(document.getElementById('pagJumpInput').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagJumpTop = function () {
        const v = parseInt(document.getElementById('pagJumpInputTop').value);
        if (!isNaN(v)) pagGo(v);
    };

    window.pagCambiarTamano = function () {
        pagTam = parseInt(document.getElementById('pagTamano').value);
        pagActual = 1; window.pagActual = 1;
        renderPagina();
    };

    window.pagCambiarTamanoTop = function () {
        pagTam = parseInt(document.getElementById('pagTamanoTop').value);
        pagActual = 1; window.pagActual = 1;
        renderPagina();
    };

    /* ============================================================
       LÓGICA DEL NEGOCIO / ACCIONES
    ============================================================ */
    function getFilters() {
        const filters = {
            desde: $('#f-desde').val(),
            hasta: $('#f-hasta').val(),
            ocultarAnulados: $('#chk-ocultar-anulados').is(':checked') ? 1 : 0,
            soloSinImputar: $('#chk-solo-sin-imputar').is(':checked') ? 1 : 0
        };
        if ($('#r-cliente').is(':checked') && $('#cmb-cliente').val()) filters.cliente = $('#cmb-cliente').val();
        if ($('#chk-usuario').is(':checked') && $('#cmb-usuario').val()) filters.usuario = $('#cmb-usuario').val();
        if ($('#chk-rama').is(':checked') && $('#cmb-rama').val()) filters.rama = $('#cmb-rama').val();
        return filters;
    }

    function exportarExcel() {
        if (listado.length === 0) return Swal.fire('Error', 'No hay datos en la grilla para exportar', 'warning');
        const filters = getFilters();
        const params = new URLSearchParams({ ...filters, action: 'export_excel' }).toString();
        window.open(`api_recibos.php?${params}`, '_blank');
    }

    window.verDetalleRecibo = async (idx) => {
        const r = listado[idx];
        const res = await fetch(`api_recibos.php?action=get_detail&id=${r['Num Recibo']}`, { headers: commonHeaders }).then(r => r.json());
        
        if (res.success) {
            const { recibo, facturas, cheques } = res;
            $('#det-nro-recibo').text(r['Num Recibo']); 
            $('#det-cliente').text(r.Cliente);
            $('#det-fecha').text(r.Fecha); 
            $('#det-total').text('$ ' + parseFloat(r.Total).toFixed(2));
            
            if (r.Estado === 'ANULADO') { 
                $('#det-banner-anulado').removeClass('d-none'); 
                $('#btn-anular-desde-detalle').addClass('d-none'); 
            } else { 
                $('#det-banner-anulado').addClass('d-none'); 
                $('#btn-anular-desde-detalle').removeClass('d-none'); 
            }

            const fBody = $('#det-tbody-facturas').empty();
            facturas.forEach(f => fBody.append(`<tr><td>${f.comprobante}</td><td class="text-end">$ ${parseFloat(f.importepagado).toFixed(2)}</td></tr>`));
            if (facturas.length === 0) fBody.append('<tr><td colspan="2" class="text-center text-muted">A CUENTA</td></tr>');

            const vBody = $('#det-tbody-valores').empty();
            if (parseFloat(recibo.retiva) > 0) vBody.append(`<tr><td>RETENCIÓN IVA</td><td>-</td><td class="text-end">$ ${parseFloat(recibo.retiva).toFixed(2)}</td><td>-</td></tr>`);
            if (parseFloat(recibo.retgan) > 0) vBody.append(`<tr><td>RETENCIÓN GAN.</td><td>-</td><td class="text-end">$ ${parseFloat(recibo.retgan).toFixed(2)}</td><td>-</td></tr>`);
            if (parseFloat(recibo.retiibb) > 0) vBody.append(`<tr><td>RETENCIÓN IIBB</td><td>-</td><td class="text-end">$ ${parseFloat(recibo.retiibb).toFixed(2)}</td><td>-</td></tr>`);

            if (parseFloat(recibo.imp1) > 0 && recibo.m1_tipo !== 'Cartera de Cheques') {
                vBody.append(`<tr><td>${recibo.m1_nom}</td><td>-</td><td class="text-end">$ ${parseFloat(recibo.imp1).toFixed(2)}</td><td>-</td></tr>`);
            }
            if (parseFloat(recibo.imp2) > 0 && recibo.m2_tipo !== 'Cartera de Cheques') {
                vBody.append(`<tr><td>${recibo.m2_nom}</td><td>-</td><td class="text-end">$ ${parseFloat(recibo.imp2).toFixed(2)}</td><td>-</td></tr>`);
            }

            cheques.forEach(c => {
                vBody.append(`<tr><td>CHQ - ${c.banco_nom}</td><td>Nº ${c.numero}</td><td class="text-end">$ ${parseFloat(c.importe).toFixed(2)}</td><td>${c.fechavencimiento}</td></tr>`);
            });

            $('#btn-anular-desde-detalle').off('click').on('click', () => { $('#modalDetalleRecibo').modal('hide'); window.anularRecibo(idx); });
            $('#modalDetalleRecibo').modal('show');
        }
    };

    window.modificarFechaRecibo = async (idx) => {
        const r = listado[idx];
        const fechaActualArr = r.Fecha.split('-');
        const fechaIso = `${fechaActualArr[2]}-${fechaActualArr[1]}-${fechaActualArr[0]}`;

        const { value: nuevaFecha } = await Swal.fire({
            title: 'Cambiar Fecha de Recibo',
            text: `Recibo #${r['Num Recibo']}`,
            input: 'date',
            inputValue: fechaIso,
            showCancelButton: true,
            confirmButtonText: 'Actualizar',
            cancelButtonText: 'Cancelar'
        });

        if (nuevaFecha) {
            if (nuevaFecha > new Date().toISOString().split('T')[0]) {
                return Swal.fire('Error', 'No se permite fecha futura.', 'error');
            }

            try {
                const res = await fetch('api_recibos.php?action=change_date', {
                    method: 'POST',
                    headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: r['Num Recibo'], fecha: nuevaFecha })
                }).then(r => r.json());

                if (res.success) {
                    Swal.fire('Éxito', 'Fecha actualizada correctamente.', 'success');
                    buscarRecibos();
                } else {
                    Swal.fire('Error', res.error || 'No se pudo actualizar la fecha.', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error');
            }
        }
    };

    window.quitarImputacionRecibo = async (idx) => {
        const r = listado[idx];
        if (!r['Imputado a'] || r['Imputado a'] === '') {
            return Swal.fire('Info', 'Este recibo ya se encuentra sin imputación (A Cuenta).', 'info');
        }

        const confirm = await Swal.fire({
            title: '¿Quitar Imputación?',
            text: 'Se liberarán las facturas vinculadas a este recibo y el monto quedará a cuenta del cliente.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, desimputar',
            cancelButtonText: 'No, cancelar'
        });

        if (confirm.isConfirmed) {
            try {
                const res = await fetch('api_recibos.php?action=unimpute', { 
                    method: 'POST', 
                    headers: { ...commonHeaders, 'Content-Type': 'application/json' }, 
                    body: JSON.stringify({ id: r['Num Recibo'], total: r.Total }) 
                }).then(r => r.json());

                if (res.success) {
                    Swal.fire('Éxito', 'Recibo desimputado correctamente.', 'success');
                    buscarRecibos();
                } else {
                    Swal.fire('Error', res.error || 'Fallo al quitar imputación.', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error');
            }
        }
    };

    window.anularRecibo = async (idx) => {
        const r = listado[idx];
        if (r['Imputado a'] && r['Imputado a'] !== '') return Swal.fire('Atención', 'Desimpute el recibo antes de anular.', 'warning');
        
        const { value: annulDate } = await Swal.fire({ 
            title: 'Anular Recibo', 
            text: 'Fecha de reversión:', 
            input: 'date', 
            inputValue: new Date().toISOString().split('T')[0], 
            showCancelButton: true 
        });
        
        if (!annulDate) return;
        if (annulDate > new Date().toISOString().split('T')[0]) return Swal.fire('Error', 'No se permite fecha futura.', 'error');
        
        if (await Swal.fire({ title: '¿Confirmar?', text: `Se anulará el recibo #${r['Num Recibo']}`, icon: 'warning', showCancelButton: true }).then(x => x.isConfirmed)) {
            const res = await fetch('api_recibos.php?action=annul', { 
                method: 'POST', 
                headers: { ...commonHeaders, 'Content-Type': 'application/json' }, 
                body: JSON.stringify({ id: r['Num Recibo'], annul_date: annulDate }) 
            }).then(r => r.json());
            
            if (res.success) { 
                Swal.fire('Anulado', 'Recibo anulado', 'success'); 
                buscarRecibos(); 
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    };

    window.cambiarFiscalRecibo = async (idx) => {
        const r = listado[idx];
        const res = await fetch('api_recibos.php?action=toggle_fiscal', { 
            method: 'POST', 
            headers: { ...commonHeaders, 'Content-Type': 'application/json' }, 
            body: JSON.stringify({ id: r['Num Recibo'], actual: r.Fiscal }) 
        }).then(r => r.json());
        if (res.success) buscarRecibos();
    };

    // Inicializar módulo
    init();
})();
</script>

<style>
    .pointer { cursor: pointer; }
    .italic { font-style: italic; }
    .row-anulado td { text-decoration: line-through !important; color: #dc3545 !important; }
    .table-active, .table-active>td { background-color: rgba(0, 255, 255, 0.15) !important; color: #00ffff !important; font-weight: bold; }
</style>