<?php
/**
 * composicion_saldos_cli.php - DESGLOSE DE SALDOS PENDIENTES
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$id_inicial = $_GET['id'] ?? ''; // Por si se llama desde el resumen de cuenta
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">COMPOSICIÓN DE SALDOS DE CLIENTES</h4>
            <small class="text-accent opacity-75">Análisis de comprobantes pendientes de imputación y cobro</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success border-0" id="btn-excel"><i class="fas fa-file-excel me-2"></i>EXCEL</button>
            <button class="btn btn-danger rounded-circle shadow" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- SELECCIÓN DE CLIENTE -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-md-6">
                    <label class="form-label-custom">CLIENTE</label>
                    <select id="cmb-cliente" class="form-select select2-simple"></select>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block p-2 px-3 rounded bg-dark border border-info">
                        <small class="text-muted">SALDO CONSOLIDADO:</small>
                        <span id="lbl-saldo-total" class="fw-bold fs-4 ms-2 text-accent">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- COLUMNA IZQUIERDA: DESGLOSE -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3">
                <!-- PAGOS SIN IMPUTAR -->
                <div class="card card-filtros p-0 overflow-hidden shadow-sm">
                    <div class="card-header bg-dark border-secondary py-1 px-3 d-flex justify-content-between align-items-center">
                        <span class="text-warning small fw-bold">PAGOS A CUENTA</span>
                        <span id="total-pagos" class="badge bg-warning text-dark">0.00</span>
                    </div>
                    <div class="table-responsive" style="max-height: 200px;">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th>FECHA</th><th>NÚMERO</th><th class="text-end">PENDIENTE</th></tr></thead>
                            <tbody id="tbody-pagos"></tbody>
                        </table>
                    </div>
                </div>

                <!-- NC SIN IMPUTAR -->
                <div class="card card-filtros p-0 overflow-hidden shadow-sm">
                    <div class="card-header bg-dark border-secondary py-1 px-3 d-flex justify-content-between align-items-center">
                        <span class="text-info small fw-bold">NOTAS DE CRÉDITO</span>
                        <span id="total-nc" class="badge bg-info text-dark">0.00</span>
                    </div>
                    <div class="table-responsive" style="max-height: 200px;">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th>FECHA</th><th>NÚMERO</th><th class="text-end">PENDIENTE</th></tr></thead>
                            <tbody id="tbody-nc"></tbody>
                        </table>
                    </div>
                </div>

                <!-- FACTURAS PENDIENTES -->
                <div class="card card-filtros p-0 overflow-hidden shadow-sm">
                    <div class="card-header bg-dark border-secondary py-1 px-3 d-flex justify-content-between align-items-center">
                        <span class="text-white small fw-bold">FACTURAS PENDIENTES</span>
                        <span id="total-facturas" class="badge bg-secondary">0.00</span>
                    </div>
                    <div class="table-responsive" style="max-height: 200px;">
                        <table class="table table-hover table-sm mb-0">
                            <thead><tr><th>FECHA</th><th>NÚMERO</th><th class="text-end">SALDO</th></tr></thead>
                            <tbody id="tbody-facturas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: CONSOLIDADO -->
        <div class="col-lg-7">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm h-100">
                <div class="card-header bg-dark border-secondary py-2 px-3">
                    <span class="text-accent fw-bold small uppercase">MOVIMIENTOS PENDIENTES CONSOLIDADOS (LIBRO MAYOR)</span>
                </div>
                <div class="table-responsive" style="max-height: 660px;">
                    <table class="table table-hover align-middle mb-0" id="tbl-consolidado">
                        <thead class="sticky-top" style="background: #0d2b45; z-index: 5;">
                            <tr>
                                <th class="text-center">FECHA</th>
                                <th>COMPROBANTE</th>
                                <th class="text-end">DEBE (+)</th>
                                <th class="text-end">HABER (-)</th>
                                <th class="text-end text-accent">SALDO</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-consolidado">
                            <tr><td colspan="5" class="text-center py-5 opacity-50 italic">Seleccione un cliente para analizar</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };
    const idInicial = "<?= $id_inicial ?>";

    async function init() {
        configurarSelect2();
        if (idInicial) {
            // Carga automática si viene ID
            const option = new Option("CARGANDO...", idInicial, true, true);
            $('#cmb-cliente').append(option).trigger('change');
            cargarDatos(idInicial);
        }

        $('#btn-excel').click(exportarExcel);
    }

    function configurarSelect2() {
        $('#cmb-cliente').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'BUSCAR CLIENTE...',
            ajax: {
                url: 'api_reporte_ctacte.php?action=get_clientes',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.map(c => ({ id: c.id, text: c.razon })) })
            }
        }).on('change', function() {
            if (this.value) cargarDatos(this.value);
        });
    }

    async function cargarDatos(clienteId) {
        // Spinners
        $('#tbody-pagos, #tbody-nc, #tbody-facturas, #tbody-consolidado').html('<tr><td colspan="10" class="text-center p-3"><div class="spinner-border spinner-border-sm text-info"></div></td></tr>');

        try {
            const res = await fetch(`api_composicion_saldos_cli.php?cliente_id=${clienteId}`, { headers: commonHeaders }).then(r => r.json());
            if (!res.success) throw new Error(res.error);

            renderDesglose(res.pagos, '#tbody-pagos', '#total-pagos');
            renderDesglose(res.nc, '#tbody-nc', '#total-nc');
            renderDesglose(res.facturas, '#tbody-facturas', '#total-facturas');
            renderConsolidado(res.consolidado);

            $('#lbl-saldo-total').text('$ ' + parseFloat(res.saldo_final).toLocaleString('es-AR', {minimumFractionDigits: 2}));

        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    }

    function renderDesglose(data, tbodyId, badgeId) {
        const tbody = $(tbodyId).empty();
        let total = 0;
        if (data.length === 0) return tbody.append('<tr><td colspan="3" class="text-center py-2 opacity-50 small">Sin pendientes</td>');
        
        data.forEach(r => {
            total += parseFloat(r.pendiente);
            tbody.append(`<tr><td>${r.fecha_f}</td><td><small>${r.numero}</small></td><td class="text-end fw-bold">${parseFloat(r.pendiente).toFixed(2)}</td></tr>`);
        });
        $(badgeId).text(total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
    }

    function renderConsolidado(data) {
        const tbody = $('#tbody-consolidado').empty();
        if (data.length === 0) return tbody.append('<tr><td colspan="5" class="text-center py-5">Sin movimientos encontrados.</td></tr>');

        data.forEach(r => {
            tbody.append(`
                <tr>
                    <td class="text-center small">${r.fecha_f}</td>
                    <td><small class="text-muted d-block" style="font-size: 9px;">${r.tipo}</small> <strong>${r.numero}</strong></td>
                    <td class="text-end">${parseFloat(r.debe) > 0 ? parseFloat(r.debe).toFixed(2) : ''}</td>
                    <td class="text-end text-success">${parseFloat(r.haber) > 0 ? parseFloat(r.haber).toFixed(2) : ''}</td>
                    <td class="text-end fw-bold text-accent">${parseFloat(r.saldo_acum).toFixed(2)}</td>
                </tr>
            `);
        });
    }

    function exportarExcel() {
        const cliId = $('#cmb-cliente').val();
        if (!cliId) return Swal.fire('Atención', 'Seleccione un cliente primero.', 'warning');
        window.location.href = `exportar_composicion_saldos_cli_excel.php?cliente_id=${cliId}`;
    }

    init();
})();
</script>