<?php
/**
 * facturas_pend_cli.php - LISTADO DE SALDOS PENDIENTES DE CLIENTES
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">FACTURAS PENDIENTES DE COBRO</h4>
            <small class="text-accent opacity-75">Control de saldos deudores y antigüedad de deuda</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm rounded-pill fw-bold" id="btn-exportar-excel">
                <i class="fas fa-file-excel me-1"></i><span class="d-none d-sm-inline">EXCEL</span>
            </button>
            <button class="btn btn-warning btn-sm rounded-pill fw-bold" id="btn-imprimir-reporte">
                <i class="fas fa-print me-1"></i><span class="d-none d-sm-inline">IMPRIMIR</span>
            </button>
            <button class="btn btn-info btn-sm fw-bold px-4 rounded-pill" id="btn-generar">
                <i class="fas fa-search me-1"></i>GENERAR
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">HASTA VENCIMIENTO</label>
                    <input type="date" id="f-hasta" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label-custom">FILTRAR CLIENTE</label>
                    <div class="input-group input-group-sm">
                        <div class="input-group-text bg-dark border-secondary">
                            <input class="form-check-input mt-0" type="checkbox" id="chk-cliente">
                        </div>
                        <div class="flex-grow-1">
                            <select id="cmb-cliente" class="form-control" disabled></select>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <div class="d-flex align-items-center justify-content-md-end">
                        <div class="p-2 px-3 rounded bg-dark border border-info w-100 text-center text-md-end">
                            <small class="text-muted d-block d-md-inline">DEUDA TOTAL FILTRADA:</small>
                            <span id="lbl-total-deuda" class="fw-bold fs-5 ms-md-2 text-info">$ 0.00</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionPendTop" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
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
    <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small" id="tabla-pendientes">
                <thead>
                    <tr>
                        <th>CLIENTE</th>
                        <th class="text-center">VENCIMIENTO</th>
                        <th class="text-center">FECHA FAC.</th>
                        <th>TIPO</th>
                        <th>Nº COMPROBANTE</th>
                        <th class="text-end">TOTAL</th>
                        <th class="text-end text-success">COBRADO</th>
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
    <div class="erp-cards-wrap" id="cards-pendientes">
        <div class="text-center text-muted p-5">Ajuste los filtros y presione GENERAR</div>
    </div>

    <!-- ============================
         PAGINACIÓN INFERIOR
    ============================= -->
    <div class="erp-pagination" id="paginacionPend" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
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
(function () {
    /* ============================================================
       ESTADO
    ============================================================ */
    var listado   = [];
    var pagActual = 1;
    var pagTotal  = 1;
    var pagTam    = 20;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    window.pagActual = 1;
    window.pagTotal  = 1;

    /* ============================================================
       INIT
    ============================================================ */
    function init() {
        configurarSelect2();
        $('#chk-cliente').change(e => $('#cmb-cliente').prop('disabled', !$(e.target).is(':checked')));
        $('#btn-generar').click(generarReporte);
        $('#btn-imprimir-reporte').click(imprimirHTML);
        $('#btn-exportar-excel').click(exportarExcel);
    }

    function configurarSelect2() {
        $('#cmb-cliente').select2({
            placeholder: 'BUSCAR CLIENTE...',
            ajax: {
                url: 'api_cobros.php?action=search_clientes',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data.map(c => ({ id: c.id, text: c.razon })) })
            },
            width: '100%'
        });
    }

    /* ============================================================
       FETCH
    ============================================================ */
    async function generarReporte() {
        const filters = {
            hasta: $('#f-hasta').val(),
            cliente: $('#chk-cliente').is(':checked') ? $('#cmb-cliente').val() : null
        };

        $('#tbody-pendientes').html('<tr><td colspan="9" class="text-center p-4"><div class="spinner-border text-info"></div></td></tr>');
        $('#cards-pendientes').html('<div class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div> CARGANDO...</div>');

        try {
            const res = await fetch('api_facturas_pendientes_clientes.php?action=list', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(filters)
            }).then(r => r.json());

            if (res.success) {
                listado  = res.data;
                $('#lbl-total-deuda').text('$ ' + parseFloat(res.total_general).toLocaleString('es-AR', { minimumFractionDigits: 2 }));
            } else {
                listado = [];
            }
        } catch (e) {
            console.error(e);
            listado = [];
        }

        pagActual        = 1;
        window.pagActual = 1;
        renderPagina();
    }

    /* ============================================================
       RENDER PÁGINA
    ============================================================ */
    function renderPagina() {
        const total = listado.length;
        pagTotal    = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual;
        window.pagTotal  = pagTotal;

        const datos = listado.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos, total);
        renderTarjetas(datos, total);
        actualizarPaginacion(total);
    }

    /* ---- TABLA ---- */
    function renderTabla(datos, total) {
        const tbody = $('#tbody-pendientes').empty();

        if (total === 0) {
            tbody.append('<tr><td colspan="9" class="text-center p-4 text-muted">No hay comprobantes pendientes.</td></tr>');
            return;
        }
        if (datos.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center p-4 text-muted">SIN RESULTADOS EN ESTA PÁGINA</td></tr>');
            return;
        }

        datos.forEach(r => {
            const saldoFmt   = parseFloat(r.Saldo).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            const totalFmt   = parseFloat(r.Total).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            const cobradoFmt = parseFloat(r.Cobrado).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            tbody.append(`
                <tr>
                    <td class="fw-bold text-white">${r.Cliente}</td>
                    <td class="text-center">${r.Vencimiento}</td>
                    <td class="text-center">${r['Fecha Fac']}</td>
                    <td>${r.Tipo}</td>
                    <td>${r['Num Factura']}</td>
                    <td class="text-end">$ ${totalFmt}</td>
                    <td class="text-end text-success">$ ${cobradoFmt}</td>
                    <td class="text-end text-warning fw-bold">$ ${saldoFmt}</td>
                    <td class="text-center">${r.Fiscal ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
                </tr>
            `);
        });
    }

    /* ---- TARJETAS ---- */
    function renderTarjetas(datos, total) {
        const wrap = document.getElementById('cards-pendientes');
        wrap.innerHTML = '';

        if (total === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">No hay comprobantes pendientes.</div>';
            return;
        }
        if (datos.length === 0) {
            wrap.innerHTML = '<div class="text-center text-muted p-4">SIN RESULTADOS EN ESTA PÁGINA</div>';
            return;
        }

        datos.forEach(r => {
            const saldoFmt   = parseFloat(r.Saldo).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            const totalFmt   = parseFloat(r.Total).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            const cobradoFmt = parseFloat(r.Cobrado).toLocaleString('es-AR', { minimumFractionDigits: 2 });
            const fiscalBadge = r.Fiscal
                ? '<span class="erp-card-badge" style="background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;">FISCAL</span>'
                : '';

            wrap.innerHTML += `
            <div class="erp-card">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title">${r.Cliente}</div>
                        ${fiscalBadge}
                    </div>
                    <span class="erp-card-code">${r.Tipo}</span>
                </div>

                <div class="erp-card-row">
                    <span class="erp-card-label">Nº Comprobante</span>
                    <span class="erp-card-value">${r['Num Factura']}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Fecha Emisión</span>
                    <span class="erp-card-value">${r['Fecha Fac']}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Vencimiento</span>
                    <span class="erp-card-value">${r.Vencimiento}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Total</span>
                    <span class="erp-card-value">$ ${totalFmt}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Cobrado</span>
                    <span class="erp-card-value" style="color:var(--success);">$ ${cobradoFmt}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Saldo Pendiente</span>
                    <span class="erp-card-value price" style="color:var(--warning);">$ ${saldoFmt}</span>
                </div>
            </div>`;
        });
    }

    /* ============================================================
       PAGINACIÓN (sincroniza top e inferior)
    ============================================================ */
    function actualizarPaginacion(total) {
        const desde    = Math.min((pagActual - 1) * pagTam + 1, total || 1);
        const hasta    = Math.min(pagActual * pagTam, total);
        const infoHtml = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> comprobantes`;
        const visible  = pagTotal > 1 ? 'flex' : 'none';

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
                        btn.className   = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                        btn.textContent = i;
                        btn.onclick     = (function (p) { return function () { pagGo(p); }; })(i);
                        cont.appendChild(btn);
                    }
                }
            }

            var jump = document.getElementById('pagJumpInput' + sfx);
            if (jump) { jump.value = pagActual; jump.max = pagTotal; }

            var sel = document.getElementById('pagTamano' + sfx);
            if (sel) sel.value = pagTam;

            var wrapId = sfx === 'Top' ? 'paginacionPendTop' : 'paginacionPend';
            var wrap   = document.getElementById(wrapId);
            if (wrap) wrap.style.display = total > 0 ? visible : 'none';
        });
    }

    window.pagGo = function (n) {
        n = Math.max(1, Math.min(pagTotal, n));
        if (n === pagActual) return;
        pagActual        = n;
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
       IMPRIMIR (sin cambios de lógica)
    ============================================================ */
    function imprimirHTML() {
        if (listado.length === 0) return;

        const grupos = listado.reduce((acc, item) => {
            if (!acc[item.Cliente]) acc[item.Cliente] = { items: [], saldo: 0 };
            acc[item.Cliente].items.push(item);
            acc[item.Cliente].saldo += parseFloat(item.Saldo);
            return acc;
        }, {});

        let html = `<html><head><title>Reporte de Pendientes</title>
        <style>
            body { font-family: sans-serif; font-size: 11px; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            th { background: #eee; border: 1px solid #ccc; padding: 5px; text-align: left; }
            td { border: 1px solid #eee; padding: 5px; }
            .header { text-align: center; margin-bottom: 30px; }
            .cliente-head { background: #f9f9f9; font-weight: bold; font-size: 13px; padding: 10px; border: 1px solid #ccc; margin-top: 10px; }
            .text-right { text-align: right; }
            .subtotal { font-weight: bold; background: #f0f0f0; }
        </style></head><body>
        <div class="header">
            <h2>LISTADO DE SALDOS PENDIENTES</h2>
            <p>FECHA DE CORTE: ${$('#f-hasta').val()} | GENERADO: ${new Date().toLocaleString()}</p>
        </div>`;

        let totalGral = 0;
        for (const [cliente, data] of Object.entries(grupos)) {
            html += `<div class="cliente-head">${cliente}</div>`;
            html += `<table><thead><tr><th>VENCE</th><th>EMISION</th><th>COMPROBANTE</th><th class="text-right">TOTAL</th><th class="text-right">SALDO</th></tr></thead><tbody>`;
            data.items.forEach(i => {
                html += `<tr>
                    <td>${i.Vencimiento}</td><td>${i['Fecha Fac']}</td>
                    <td>${i.Tipo} ${i['Num Factura']}</td>
                    <td class="text-right">$ ${parseFloat(i.Total).toFixed(2)}</td>
                    <td class="text-right">$ ${parseFloat(i.Saldo).toFixed(2)}</td>
                </tr>`;
            });
            html += `<tr class="subtotal"><td colspan="4" class="text-right">SALDO CLIENTE:</td><td class="text-right">$ ${data.saldo.toFixed(2)}</td></tr>`;
            html += `</tbody></table>`;
            totalGral += data.saldo;
        }

        html += `<div style="text-align:right;font-size:16px;font-weight:bold;margin-top:20px;">TOTAL GENERAL: $ ${totalGral.toFixed(2)}</div>`;
        html += `</body></html>`;

        const win = window.open('', '_blank');
        win.document.write(html);
        win.document.close();
        win.print();
    }

    /* ============================================================
       EXPORTAR EXCEL (sin cambios de lógica)
    ============================================================ */
    function exportarExcel() {
        if (listado.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay datos cargados para exportar. Haga clic en GENERAR primero.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const fecHasta = $('#f-hasta').val();
        const grupos   = listado.reduce((acc, item) => {
            if (!acc[item.Cliente]) acc[item.Cliente] = { items: [], saldo: 0 };
            acc[item.Cliente].items.push(item);
            acc[item.Cliente].saldo += parseFloat(item.Saldo);
            return acc;
        }, {});

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8">
            <style>
                .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                .title { font-size: 14pt; font-weight: bold; text-align: left; }
                .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                .text { text-align: left; border: 0.5pt solid #ccc; }
                .date { text-align: center; border: 0.5pt solid #ccc; }
                .subtotal { background-color: #f8f9fa; font-weight: bold; border: 0.5pt solid #ccc; }
                .client-row { background-color: #1a365d; color: #ffffff; font-weight: bold; border: 0.5pt solid #ccc; }
                .danger-text { color: #cc0000; font-weight: bold; }
            </style></head><body><table>
                <tr><td colspan="6" class="title">REPORTE DE FACTURAS PENDIENTES DE COBRO</td></tr>
                <tr><td colspan="6">FECHA DE CORTE: ${fecHasta.split('-').reverse().join('/')}</td></tr>
                <tr><td colspan="6"></td></tr>
                <thead><tr>
                    <th class="header">CLIENTE</th><th class="header">VENCIMIENTO</th>
                    <th class="header">EMISIÓN</th><th class="header">COMPROBANTE</th>
                    <th class="header">TOTAL ORIG.</th><th class="header">SALDO PENDIENTE</th>
                </tr></thead><tbody>`;

        let totalGeneral = 0;
        for (const [cliente, data] of Object.entries(grupos)) {
            excelHtml += `<tr><td colspan="6" class="client-row">${cliente}</td></tr>`;
            data.items.forEach(i => {
                excelHtml += `<tr>
                    <td class="text"></td>
                    <td class="date">${i.Vencimiento}</td>
                    <td class="date">${i['Fecha Fac']}</td>
                    <td class="text">${i.Tipo} ${i['Num Factura']}</td>
                    <td class="number">${parseFloat(i.Total)}</td>
                    <td class="number danger-text">${parseFloat(i.Saldo)}</td>
                </tr>`;
            });
            excelHtml += `<tr>
                <td colspan="5" class="subtotal" style="text-align:right;">SALDO TOTAL CLIENTE:</td>
                <td class="number subtotal danger-text">${parseFloat(data.saldo)}</td>
            </tr><tr><td colspan="6"></td></tr>`;
            totalGeneral += data.saldo;
        }

        excelHtml += `<tr>
            <td colspan="5" style="text-align:right;font-weight:bold;font-size:12pt;border:0.5pt solid #ccc;">TOTAL GENERAL ADEUDADO:</td>
            <td class="number" style="font-weight:bold;font-size:12pt;color:#cc0000;background-color:#ffe6e6;border:0.5pt solid #ccc;">${totalGeneral}</td>
        </tr></tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement('a');
        link.href     = URL.createObjectURL(blob);
        link.download = `Facturas_Pendientes_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>