<?php
/**
 * proveedores_imputar_pagos.php - IMPUTACIÓN MANUAL DE PAGOS A CUENTA
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">GESTIÓN DE IMPUTACIONES DE PAGOS</h4>
            <small class="text-accent opacity-75">Imputación rápida y consulta de vínculos anteriores</small>
        </div>
        <div class="d-flex gap-2"><button class="btn btn-outline-warning fw-bold" id="btn-auto-fifo" disabled>Auto-imputar FIFO</button>
        <button class="btn btn-info fw-bold px-4 shadow" id="btn-procesar-imputacion" disabled>
            <i class="fas fa-link me-2"></i>PROCESAR IMPUTACIÓN
        </button></div>
    </div>

    <!-- SECTOR 1: PROVEEDOR -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label-custom">SELECCIONE PROVEEDOR</label>
                    <select id="cmb-proveedor" class="form-control"></select>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block p-2 px-3 rounded bg-dark border border-secondary shadow-sm">
                        <small class="text-muted">DIFERENCIA SELECCIONADA:</small>
                        <span id="lbl-diferencia" class="fw-bold fs-5 ms-2 text-white">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- COLUMNA IZQUIERDA: PAGOS -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 h-100 shadow-sm overflow-hidden border-warning-subtle">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-warning small fw-bold"><i class="fas fa-money-bill-wave me-2"></i>PAGOS A CUENTA</span>
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
                            <tr><td colspan="4" class="text-center p-5 text-muted">Seleccione un proveedor</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- COLUMNA DERECHA: FACTURAS -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 h-100 shadow-sm overflow-hidden border-info-subtle">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-info small fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>FACTURAS PENDIENTES</span>
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
                            <tr><td colspan="4" class="text-center p-5 text-muted">Seleccione un proveedor</td></tr>
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
        $('#btn-procesar-imputacion').click(procesar);
        $('#btn-auto-fifo').click(function () {
            $('.chk-pago, .chk-fact').prop('checked', true);
            actualizarCalculos();
            procesar();
        });
    }

    function configurarSelect2() {
        $('#cmb-proveedor').select2({
            placeholder: 'BUSCAR PROVEEDOR...',
            dropdownParent: $('#main-content'),
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        }).on('select2:select', e => cargarDatos(e.params.data.id));
    }

    async function cargarDatos(id) {
        const res = await fetch(`api_imputar_pagos_prov.php?action=get_pendientes&proveedor=${id}`, { headers: commonHeaders }).then(r => r.json());
        if (res.success) {
            pagosList = res.pagos;
            facturasList = res.facturas;
            renderTablas();
            $('#btn-auto-fifo').prop('disabled', !(pagosList.length && facturasList.length));
        }
        actualizarCalculos();
    }

    function renderTablas() {
        // Pagos
        const tP = $('#tbody-pagos').empty();
        if (pagosList.length === 0) tP.append('<tr><td colspan="4" class="text-center p-4">Sin saldos a cuenta.</td></tr>');
        pagosList.forEach((p, i) => {
            tP.append(`<tr>
                <td class="text-center"><input type="checkbox" class="form-check-input chk-pago" data-idx="${i}"></td>
                <td>${p.fecha_f}</td><td class="fw-bold text-white">${p.numcomp}</td>
                <td class="text-end fw-bold text-warning">$ ${parseFloat(p.saldoacuenta).toFixed(2)}</td>
            </tr>`);
        });

        // Facturas
        const tF = $('#tbody-facturas').empty();
        if (facturasList.length === 0) tF.append('<tr><td colspan="4" class="text-center p-4">Sin facturas pendientes.</td></tr>');
        facturasList.forEach((f, i) => {
            tF.append(`<tr>
                <td class="text-center"><input type="checkbox" class="form-check-input chk-fact" data-idx="${i}"></td>
                <td>${f.fecha_f}</td><td class="fw-bold text-white">${f.num_factura}</td>
                <td class="text-end fw-bold text-info">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
            </tr>`);
        });

        $('.chk-pago, .chk-fact').change(actualizarCalculos);
    }

    function actualizarCalculos() {
        let sumP = 0, sumF = 0;
        $('.chk-pago:checked').each(function() { sumP += parseFloat(pagosList[$(this).data('idx')].saldoacuenta); });
        $('.chk-fact:checked').each(function() { sumF += parseFloat(facturasList[$(this).data('idx')].pendiente); });

        $('#lbl-total-pagos').text('$ ' + sumP.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl-total-facturas').text('$ ' + sumF.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        const dif = sumF - sumP;
        $('#lbl-diferencia').text('$ ' + Math.abs(dif).toLocaleString('es-AR', {minimumFractionDigits: 2}))
            .removeClass('text-success text-danger text-warning')
            .addClass(dif === 0 ? 'text-success' : (dif > 0 ? 'text-warning' : 'text-danger'));

        $('#btn-procesar-imputacion').prop('disabled', !(sumP > 0 && sumF > 0));
    }

    async function procesar() {
        const data = {
            proveedor_id: $('#cmb-proveedor').val(),
            pagos: $('.chk-pago:checked').map(function() { return pagosList[$(this).data('idx')]; }).get(),
            facturas: $('.chk-fact:checked').map(function() { return facturasList[$(this).data('idx')]; }).get()
        };

        if (await Swal.fire({ title: '¿Confirmar Imputación?', text: 'Se aplicarán los saldos seleccionados.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_imputar_pagos_prov.php?action=save', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', 'Imputación realizada correctamente.', 'success');
                cargarDatos($('#cmb-proveedor').val());
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    init();
})();
</script>
<?php $gestionTipo = 'proveedor'; require 'imputaciones_gestion_panel.php'; ?>
