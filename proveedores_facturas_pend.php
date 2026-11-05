<?php
/**
 * facturas_pend_prv.php - LISTADO DE SALDOS PENDIENTES DE PROVEEDORES
 * Actualizado: Paginación en memoria (SPA) y diseño responsivo con Tarjetas Mobile.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">FACTURAS PENDIENTES DE PAGO</h4>
            <small class="text-accent opacity-75">Control de deudas con proveedores y vencimientos</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 btn-sm" id="btn-exportar-excel">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-outline-info border-0 btn-sm" id="btn-imprimir-reporte">
                <i class="fas fa-print me-1"></i> <span class="d-none d-sm-inline">IMPRIMIR</span>
            </button>
            <button class="btn btn-danger btn-sm rounded-circle shadow ms-2" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3 col-lg-2">
                    <label class="form-label-custom">HASTA VENCIMIENTO</label>
                    <input type="date" id="f-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-9 col-lg-5 border-start border-secondary border-opacity-25 px-3">
                    <label class="form-label-custom">FILTRAR PROVEEDOR</label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-text bg-dark border-secondary">
                            <input class="form-check-input mt-0" type="checkbox" id="chk-proveedor">
                        </div>
                        <div class="flex-grow-1">
                            <select id="cmb-proveedor" class="form-control select2-simple" disabled></select>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5 text-end d-flex justify-content-end align-items-center flex-wrap gap-2">
                    <div class="p-2 px-3 rounded bg-dark border border-info text-start flex-grow-1 flex-lg-grow-0">
                        <small class="text-muted d-block" style="font-size: 10px;">DEUDA TOTAL FILTRADA:</small>
                        <span id="lbl-total-deuda" class="fw-bold fs-5 text-info">$ 0.00</span>
                    </div>
                    <button class="btn btn-info fw-bold px-4 rounded-pill" id="btn-generar">
                        <i class="fas fa-search me-1"></i> GENERAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionPendTop" style="display: none;">
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

    <!-- ============================
         TABLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
        <div class="table-responsive" style="max-height: 60vh; min-height: 300px;">
            <table class="table table-hover align-middle mb-0 small" id="tabla-pendientes">
                <thead class="sticky-top" style="background-color: #0d2b45; z-index: 10;">
                    <tr>
                        <th class="ps-3">PROVEEDOR</th>
                        <th class="text-center">VENCIMIENTO</th>
                        <th class="text-center">FECHA FAC.</th>
                        <th>TIPO</th>
                        <th>Nº COMPROBANTE</th>
                        <th class="text-end">TOTAL</th>
                        <th class="text-end text-success">PAGADO</th>
                        <th class="text-end text-warning">SALDO</th>
                        <th class="text-center">FISCAL</th>
                    </tr>
                </thead>
                <tbody id="tbody-pendientes">
                    <tr><td colspan="9" class="text-center p-5 text-muted">Ajuste los filtros y presione GENERAR</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="listaPendientesCards">
        <div class="text-center text-muted p-4">Ajuste los filtros y presione GENERAR</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionPend" style="display: none;">
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

<script>
(function() {
    /* ============================================================
       ESTADO & PAGINACIÓN
    ============================================================ */
    var pagActual = 1;
    var pagTotal  = 1;
    var pagTam    = 20;
    var listado   = [];
    const ordenTabla = erpOrdenarTabla('#tabla-pendientes', () => listado, renderPagina, [
        'Proveedor', {campo:'Vencimiento', tipo:'fecha'}, {campo:'Fecha Fac', tipo:'fecha'},
        'Tipo', 'Num Factura', {campo:'Total', tipo:'numero'}, {campo:'Pagado', tipo:'numero'},
        {campo:'Saldo', tipo:'numero'}, {campo:'Fiscal', tipo:'numero'}
    ]);
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    window.pagActual = 1;
    window.pagTotal  = 1;

    async function init() {
        configurarSelect2();
        
        $('#chk-proveedor').change(e => {
            const isChecked = $(e.target).is(':checked');
            $('#cmb-proveedor').prop('disabled', !isChecked);
            if (!isChecked) {
                $('#cmb-proveedor').val(null).trigger('change');
            }
        });

        // Eventos reactivos para actualizar al cambiar filtros
        $('#f-hasta, #cmb-proveedor').on('change', generarReporte);
        $('#btn-generar').click(generarReporte);
        $('#btn-exportar-excel').click(exportarExcel);
        $('#btn-imprimir-reporte').click(imprimirHTML);
    }

    function configurarSelect2() {
        $('#cmb-proveedor').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'BUSCAR PROVEEDOR...',
            allowClear: true,
            ajax: {
                url: 'api_reporte_ctacte_proveedores.php?action=get_proveedores',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.map(c => ({ id: c.id, text: c.razon })) })
            },
            width: '100%'
        });
    }

    /* ============================================================
       FETCH & RENDERIZADO SPA
    ============================================================ */
    async function generarReporte() {
        if ($('#chk-proveedor').is(':checked') && !$('#cmb-proveedor').val()) return;

        const filters = {
            hasta: $('#f-hasta').val(),
            proveedor_id: $('#chk-proveedor').is(':checked') ? $('#cmb-proveedor').val() : null
        };

        $('#tbody-pendientes').html('<tr><td colspan="9" class="text-center p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO COMPROBANTES...</td></tr>');
        $('#listaPendientesCards').html('<div class="text-center text-muted p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> BUSCANDO...</div>');

        try {
            const res = await fetch('api_facturas_pendientes_prv.php?action=list', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters)
            }).then(r => r.json());

            if (res.success) {
                listado = res.data;
                ordenTabla.aplicar();
                $('#lbl-total-deuda').text('$ ' + parseFloat(res.total_general).toLocaleString('es-AR', {minimumFractionDigits: 2}));
            } else {
                listado = [];
                $('#lbl-total-deuda').text('$ 0.00');
            }
        } catch (e) { 
            console.error(e); 
            listado = []; 
            $('#lbl-total-deuda').text('$ 0.00');
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
        const tbody = $('#tbody-pendientes').empty();
        if (datos.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center p-4 opacity-50 text-muted">No hay comprobantes pendientes de pago.</td></tr>');
            return;
        }

        datos.forEach(r => {
            tbody.append(`
                <tr>
                    <td class="ps-3 fw-bold text-white">${r.Proveedor}</td>
                    <td class="text-center fw-bold text-info">${r.Vencimiento}</td>
                    <td class="text-center">${r['Fecha Fac']}</td>
                    <td>${r.Tipo}</td>
                    <td class="fw-bold">${r['Num Factura']}</td>
                    <td class="text-end">$ ${parseFloat(r.Total).toFixed(2)}</td>
                    <td class="text-end text-success">$ ${parseFloat(r.Pagado).toFixed(2)}</td>
                    <td class="text-end text-warning fw-bold">$ ${parseFloat(r.Saldo).toFixed(2)}</td>
                    <td class="text-center">${r.Fiscal ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
                </tr>
            `);
        });
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('listaPendientesCards');
        wrap.innerHTML = '';

        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">NO HAY COMPROBANTES PENDIENTES</div>';
            return;
        }

        datos.forEach(r => {
            const fiscalBadge = r.Fiscal ? '<span class="erp-card-badge ms-2" style="background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;">FISCAL</span>' : '';
            
            wrap.innerHTML += `
            <div class="erp-card">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title d-inline-block text-truncate" style="max-width: 200px;">${r.Proveedor}</div>
                        ${fiscalBadge}
                    </div>
                    <span class="erp-card-code">${r.Vencimiento}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Comprobante</span>
                    <span class="erp-card-value fw-bold">${r.Tipo} ${r['Num Factura']}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Emisión</span>
                    <span class="erp-card-value">${r['Fecha Fac']}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Total</span>
                    <span class="erp-card-value">$ ${parseFloat(r.Total).toFixed(2)}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Pagado</span>
                    <span class="erp-card-value text-success">$ ${parseFloat(r.Pagado).toFixed(2)}</span>
                </div>
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Saldo Pendiente</span>
                    <span class="erp-card-value text-warning fw-bold fs-6">$ ${parseFloat(r.Saldo).toFixed(2)}</span>
                </div>
            </div>`;
        });
    }

    /* ============================================================
       CONTROLES DE PAGINACIÓN (SINCRONIZADOS)
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> comprobantes`;
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

            var wrap = document.getElementById(sfx === 'Top' ? 'paginacionPendTop' : 'paginacionPend');
            if (wrap) wrap.style.display = visible;
        });
    }

    window.pagGo = function (n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual = n;
        window.pagActual = n;
        renderPagina();
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
       EXPORTACIÓN E IMPRESIÓN
    ============================================================ */
    function exportarExcel() {
        if (listado.length === 0) {
            return Swal.fire('Atención', 'No hay datos en la grilla para exportar.', 'warning');
        }
        const hasta = $('#f-hasta').val();
        const prov = $('#chk-proveedor').is(':checked') ? $('#cmb-proveedor').val() : '';
        window.location.href = `exportar_facturas_pend_prv_excel.php?hasta=${hasta}&proveedor_id=${prov}`;
    }

    function imprimirHTML() {
        if (listado.length === 0) return;
        
        const grupos = listado.reduce((acc, item) => {
            if (!acc[item.Proveedor]) acc[item.Proveedor] = { items: [], saldo: 0 };
            acc[item.Proveedor].items.push(item);
            acc[item.Proveedor].saldo += parseFloat(item.Saldo);
            return acc;
        }, {});

        let html = `<html><head><title>Pendientes de Pago</title>
        <style>
            body { font-family: sans-serif; font-size: 11px; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background: #eee; border: 1px solid #ccc; padding: 5px; text-align: left; }
            td { border: 1px solid #eee; padding: 5px; }
            .header { text-align: center; margin-bottom: 30px; }
            .prov-head { background: #f9f9f9; font-weight: bold; font-size: 13px; padding: 10px; border: 1px solid #ccc; margin-top: 10px; }
            .text-right { text-align: right; }
        </style></head><body>
        <div class="header">
            <h2>FACTURAS PENDIENTES DE PAGO</h2>
            <p>CORTE: ${$('#f-hasta').val()} | GENERADO: ${new Date().toLocaleString()}</p>
        </div>`;

        let totalGral = 0;
        for (const [prov, data] of Object.entries(grupos)) {
            html += `<div class="prov-head">${prov}</div>`;
            html += `<table><thead><tr><th>VENCE</th><th>EMISION</th><th>COMPROBANTE</th><th class="text-right">TOTAL</th><th class="text-right">SALDO</th></tr></thead><tbody>`;
            data.items.forEach(i => {
                html += `<tr>
                    <td>${i.Vencimiento}</td>
                    <td>${i['Fecha Fac']}</td>
                    <td>${i.Tipo} ${i['Num Factura']}</td>
                    <td class="text-right">$ ${parseFloat(i.Total).toFixed(2)}</td>
                    <td class="text-right">$ ${parseFloat(i.Saldo).toFixed(2)}</td>
                </tr>`;
            });
            html += `<tr><td colspan="4" class="text-right"><b>SALDO PROVEEDOR:</b></td><td class="text-right"><b>$ ${data.saldo.toFixed(2)}</b></td></tr></tbody></table>`;
            totalGral += data.saldo;
        }
        html += `<div style="text-align:right; font-size: 16px; font-weight:bold;">TOTAL GENERAL: $ ${totalGral.toFixed(2)}</div></body></html>`;

        const win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
        win.print();
    }

    init();
})();
</script>
