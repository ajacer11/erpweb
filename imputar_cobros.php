<?php
/**
 * imputar_cobros.php - APLICACIÓN DE PAGOS A CUENTA A FACTURAS PENDIENTES
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">GESTIÓN DE IMPUTACIONES DE COBROS</h4>
            <small class="text-accent opacity-75">Imputación rápida y consulta de vínculos anteriores</small>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-outline-warning fw-bold" id="btn-auto-fifo" disabled>Auto-imputar FIFO</button>
        <button class="btn btn-info fw-bold px-4 shadow" id="btn-confirmar-imputacion" disabled>
            <i class="fas fa-link me-2"></i>PROCESAR IMPUTACIÓN
        </button></div>
    </div>

    <!-- SELECCIÓN DE CLIENTE -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label-custom">SELECCIONE CLIENTE</label>
                    <select id="cmb-cliente" class="form-control"></select>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block p-2 px-3 rounded bg-dark border border-secondary">
                        <small class="text-muted">DIFERENCIA DE SELECCIÓN:</small>
                        <span id="lbl-diferencia" class="fw-bold fs-5 ms-2 text-white">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- TABLA DE PAGOS A CUENTA -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm h-100">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between">
                    <span class="text-warning small fw-bold">PAGOS / CRÉDITOS A CUENTA</span>
                    <span class="text-muted small">Total: <b class="text-warning" id="lbl-total-pagos">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 450px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>Nº PAGO</th>
                                <th class="text-end">PENDIENTE</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-pagos">
                            <tr><td colspan="4" class="text-center p-5 text-muted italic">Seleccione un cliente</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TABLA DE FACTURAS PENDIENTES -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm h-100">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between">
                    <span class="text-info small fw-bold">FACTURAS / DEUDAS PENDIENTES</span>
                    <span class="text-muted small">Total: <b class="text-info" id="lbl-total-facturas">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 450px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>COMPROBANTE</th>
                                <th class="text-end">SALDO</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-facturas">
                            <tr><td colspan="4" class="text-center p-5 text-muted italic">Seleccione un cliente</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let pagosList = [];
    let facturasList = [];
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        configurarSelect2();
        $('#btn-confirmar-imputacion').click(procesarImputacion);
        $('#btn-auto-fifo').click(function () {
            $('.chk-pago, .chk-factura').prop('checked', true);
            actualizarTotales();
            procesarImputacion();
        });
    }

    function configurarSelect2() {
        $('#cmb-cliente').select2({
            placeholder: 'BUSCAR CLIENTE...',
            minimumInputLength: 2,
            ajax: {
                url: 'api_cobros.php?action=search_clientes', // Reutilizamos endpoint existente
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data.map(c => ({ id: c.id, text: `${c.razon} [ID: ${c.id}]` })) })
            },
            width: '100%'
        }).on('select2:select', e => cargarPendientes(e.params.data.id));
    }

    async function cargarPendientes(clienteId) {
        $('#tbody-pagos, #tbody-facturas').html('<tr><td colspan="4" class="text-center p-4"><div class="spinner-border spinner-border-sm text-info"></div></td></tr>');
        
        try {
            const res = await fetch(`api_imputar_cobros.php?action=get_pendientes&cliente=${clienteId}`, { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                pagosList = res.pagos;
                facturasList = res.facturas;
                renderPagos();
                renderFacturas();
                $('#btn-auto-fifo').prop('disabled', !(pagosList.length && facturasList.length));
            }
        } catch (e) { console.error(e); }
        actualizarTotales();
    }

    function renderPagos() {
        const tbody = $('#tbody-pagos').empty();
        if (pagosList.length === 0) return tbody.append('<tr><td colspan="4" class="text-center p-4">No hay saldos a favor.</td></tr>');
        pagosList.forEach((p, idx) => {
            tbody.append(`
                <tr>
                    <td class="text-center"><input type="checkbox" class="form-check-input chk-pago" data-idx="${idx}"></td>
                    <td>${p.fecha_f}</td>
                    <td class="fw-bold">${p.id}</td>
                    <td class="text-end fw-bold text-warning">$ ${parseFloat(p.saldoaimputar).toFixed(2)}</td>
                </tr>
            `);
        });
        $('.chk-pago').change(actualizarTotales);
    }

    function renderFacturas() {
        const tbody = $('#tbody-facturas').empty();
        if (facturasList.length === 0) return tbody.append('<tr><td colspan="4" class="text-center p-4">No hay facturas pendientes.</td></tr>');
        facturasList.forEach((f, idx) => {
            tbody.append(`
                <tr>
                    <td class="text-center"><input type="checkbox" class="form-check-input chk-factura" data-idx="${idx}"></td>
                    <td>${f.fecha_f}</td>
                    <td class="fw-bold">${f.num_factura}</td>
                    <td class="text-end fw-bold text-info">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
                </tr>
            `);
        });
        $('.chk-factura').change(actualizarTotales);
    }

    function actualizarTotales() {
        let totalP = 0, totalF = 0;
        $('.chk-pago:checked').each(function() { totalP += parseFloat(pagosList[$(this).data('idx')].saldoaimputar); });
        $('.chk-factura:checked').each(function() { totalF += parseFloat(facturasList[$(this).data('idx')].pendiente); });

        $('#lbl-total-pagos').text('$ ' + totalP.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl-total-facturas').text('$ ' + totalF.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        const dif = totalF - totalP;
        $('#lbl-diferencia').text('$ ' + dif.toLocaleString('es-AR', {minimumFractionDigits: 2}))
            .removeClass('text-success text-danger text-white')
            .addClass(dif === 0 ? 'text-success' : (dif > 0 ? 'text-warning' : 'text-danger'));

        $('#btn-confirmar-imputacion').prop('disabled', !(totalP > 0 && totalF > 0));
    }

    async function procesarImputacion() {
        const data = {
            cliente_id: $('#cmb-cliente').val(),
            pagos: $('.chk-pago:checked').map(function() { return pagosList[$(this).data('idx')]; }).get(),
            facturas: $('.chk-factura:checked').map(function() { return facturasList[$(this).data('idx')]; }).get()
        };

        const confirm = await Swal.fire({
            title: '¿Confirmar Imputación?',
            text: 'Se aplicarán los saldos seleccionados a las facturas marcadas.',
            icon: 'question',
            showCancelButton: true
        });

        if (confirm.isConfirmed) {
            const res = await fetch('api_imputar_cobros.php?action=save', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', 'Imputación realizada correctamente', 'success');
                cargarPendientes($('#cmb-cliente').val());
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    init();
})();
</script>
<?php $gestionTipo = 'cliente'; require 'imputaciones_gestion_panel.php'; ?>
