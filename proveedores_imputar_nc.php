<?php
/**
 * proveedores_imputar_nc.php - APLICACIÓN DE NOTAS DE CRÉDITO A FACTURAS
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">IMPUTAR NOTAS DE CRÉDITO</h4>
            <small class="text-accent opacity-75">Cruce manual de comprobantes de crédito contra facturas pendientes</small>
        </div>
        <button class="btn btn-info fw-bold px-4 shadow" id="btn-procesar-imputacion" disabled>
            <i class="fas fa-link me-2"></i>APLICAR NOTAS DE CRÉDITO
        </button>
    </div>

    <!-- SECTOR PROVEEDOR -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label-custom">PROVEEDOR</label>
                    <select id="cmb-proveedor" class="form-control"></select>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-inline-block p-2 px-3 rounded bg-dark border border-secondary shadow-sm">
                        <small class="text-muted">SALDO A FAVOR SELECCIONADO:</small>
                        <span id="lbl-total-nc-header" class="fw-bold fs-5 ms-2 text-warning">$ 0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- NOTAS DE CRÉDITO (A FAVOR) -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 h-100 shadow-sm overflow-hidden border-warning-subtle">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-warning small fw-bold"><i class="fas fa-file-invoice me-2"></i>NOTAS DE CRÉDITO</span>
                    <span class="text-muted small">Monto: <b class="text-warning" id="lbl-total-nc">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 450px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>COMPROBANTE</th>
                                <th class="text-end">SALDO DISP.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-nc">
                            <tr><td colspan="4" class="text-center p-5 text-muted">Seleccione un proveedor</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- FACTURAS DE COMPRA (DEUDA) -->
        <div class="col-md-6">
            <div class="card card-filtros p-0 h-100 shadow-sm overflow-hidden border-info-subtle">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-info small fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>FACTURAS DE COMPRA</span>
                    <span class="text-muted small">Monto: <b class="text-info" id="lbl-total-facturas">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 450px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>NÚMERO</th>
                                <th class="text-end">PENDIENTE</th>
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
    let ncList = [];
    let facturasList = [];
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        configurarSelect2();
        $('#btn-procesar-imputacion').click(procesar);
    }

    function configurarSelect2() {
        $('#cmb-proveedor').select2({
            placeholder: 'ESCRIBA NOMBRE DEL PROVEEDOR...',
            dropdownParent: $('#main-content'),
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', delay: 300, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        }).on('select2:select', e => cargarPendientes(e.params.data.id));
    }

    async function cargarPendientes(id) {
        $('#tbody-nc, #tbody-facturas').html('<tr><td colspan="4" class="text-center p-4"><div class="spinner-border spinner-border-sm text-info"></div></td></tr>');
        
        try {
            const res = await fetch(`api_imputar_nc_prov.php?action=get_pendientes&proveedor=${id}`, { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                ncList = res.notas;
                facturasList = res.facturas;
                renderNC();
                renderFacturas();
            }
        } catch (e) { console.error(e); }
        actualizarCalculos();
    }

    function renderNC() {
        const tbody = $('#tbody-nc').empty();
        if (ncList.length === 0) return tbody.append('<tr><td colspan="4" class="text-center p-4">Sin Notas de Crédito con saldo.</td></tr>');
        ncList.forEach((n, i) => {
            tbody.append(`<tr>
                <td class="text-center"><input type="checkbox" class="form-check-input chk-nc" data-idx="${i}"></td>
                <td>${n.fecha_f}</td><td class="fw-bold text-white">${n.numcomp}</td>
                <td class="text-end fw-bold text-warning">$ ${Math.abs(parseFloat(n.saldo)).toFixed(2)}</td>
            </tr>`);
        });
        $('.chk-nc').change(actualizarCalculos);
    }

    function renderFacturas() {
        const tbody = $('#tbody-facturas').empty();
        if (facturasList.length === 0) return tbody.append('<tr><td colspan="4" class="text-center p-4">Sin facturas pendientes.</td></tr>');
        facturasList.forEach((f, i) => {
            tbody.append(`<tr>
                <td class="text-center"><input type="checkbox" class="form-check-input chk-fact" data-idx="${i}"></td>
                <td>${f.fecha_f}</td><td class="fw-bold text-white">${f.num_factura}</td>
                <td class="text-end fw-bold text-info">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
            </tr>`);
        });
        $('.chk-fact').change(actualizarCalculos);
    }

    function actualizarCalculos() {
        let sumNC = 0, sumF = 0;
        // Corregido: Sumamos magnitudes absolutas para el chequeo del botón
        $('.chk-nc:checked').each(function() { sumNC += Math.abs(parseFloat(ncList[$(this).data('idx')].saldo)); });
        $('.chk-fact:checked').each(function() { sumF += parseFloat(facturasList[$(this).data('idx')].pendiente); });

        $('#lbl-total-nc, #lbl-total-nc-header').text('$ ' + sumNC.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#lbl-total-facturas').text('$ ' + sumF.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        // Habilitar el botón siempre que se seleccione al menos una Nota de Crédito (NC).
        $('#btn-procesar-imputacion').prop('disabled', !(sumNC > 0));
    }

    async function procesar() {
        const data = {
            proveedor_id: $('#cmb-proveedor').val(),
            notas: $('.chk-nc:checked').map(function() { return ncList[$(this).data('idx')]; }).get(),
            facturas: $('.chk-fact:checked').map(function() { return facturasList[$(this).data('idx')]; }).get()
        };

        const result = await Swal.fire({
            title: '¿Confirmar Cruce?',
            text: 'Se aplicarán las Notas de Crédito seleccionadas.',
            icon: 'question',
            showCancelButton: true
        });

        if (result.isConfirmed) {
            const res = await fetch('api_imputar_nc_prov.php?action=save', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', 'Imputación completada.', 'success');
                cargarPendientes($('#cmb-proveedor').val());
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    init();
})();
</script>