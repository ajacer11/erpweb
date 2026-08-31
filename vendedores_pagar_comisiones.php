<?php
/**
 * vendedores_pagar_comisiones.php - CARGA DE PAGOS A VENDEDORES
 * Actualizado: Lógica de carga robusta y consistencia con VB.NET.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">PAGO DE COMISIONES</h4>
            <small class="text-accent opacity-75">Registro de pagos a personal de ventas y staff</small>
        </div>
        <div class="bg-dark p-2 px-3 rounded border border-info shadow-sm text-center">
            <small class="text-muted d-block" style="font-size: 10px;">RECIBO Nº</small>
            <span class="text-info fw-bold fs-5" id="lbl-next-pago">...</span>
        </div>
    </div>

    <div class="row g-3 text-start">
        <!-- PANEL SUPERIOR -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <div class="row align-items-end g-3">
                        <div class="col-md-5">
                            <label class="form-label-custom">VENDEDOR / RECEPTOR</label>
                            <select id="cmb-vendedor-pago" class="form-control"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">SALDO ADEUDADO</label>
                            <input type="text" id="txt-saldo-cc" class="form-control text-end fw-bold text-danger" readonly value="$ 0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">FECHA PAGO</label>
                            <input type="date" id="txt-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom small">FISCALIDAD</label>
                            <div class="form-check form-switch mt-1">
                                <input class="form-check-input" type="checkbox" id="chk-fiscal">
                                <label class="form-check-label text-white small" for="chk-fiscal">Operación con comprobante fiscal</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORMULARIO DE PAGO -->
        <div class="col-md-8">
            <div class="card card-filtros h-100 shadow-sm border-secondary-subtle">
                <div class="card-body p-4">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-4 fw-bold">DISTRIBUCIÓN DE MEDIOS DE PAGO</h6>
                    
                    <div class="row g-4">
                        <!-- Medio 1 -->
                        <div class="col-md-6 border-end border-secondary">
                            <label class="form-label-custom">MEDIO DE PAGO 1</label>
                            <select id="mp-1" class="form-select mb-3"></select>
                            
                            <label class="form-label-custom">IMPORTE MP1</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                <input type="number" id="imp-1" class="form-control text-end fs-5 fw-bold input-calc" step="0.01">
                                <button class="btn btn-warning d-none" id="btn-val-1"><i class="fas fa-money-check-alt"></i></button>
                            </div>
                        </div>

                        <!-- Medio 2 -->
                        <div class="col-md-6">
                            <label class="form-label-custom">MEDIO DE PAGO 2</label>
                            <select id="mp-2" class="form-select mb-3"><option value="">-- NINGUNO --</option></select>
                            
                            <label class="form-label-custom">IMPORTE MP2</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                <input type="number" id="imp-2" class="form-control text-end fs-5 fw-bold input-calc" step="0.01">
                                <button class="btn btn-warning d-none" id="btn-val-2"><i class="fas fa-money-check-alt"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label class="form-label-custom">NOTAS / OBSERVACIONES DEL PAGO</label>
                        <textarea id="txt-notas" class="form-control" rows="2" placeholder="Escriba aquí cualquier aclaración sobre el pago..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOTALIZADOR -->
        <div class="col-md-4">
            <div class="card card-filtros h-100 shadow-sm border-info-subtle bg-escritorio">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <h6 class="text-info border-bottom border-secondary pb-2 mb-4 fw-bold">RESUMEN DEL MOVIMIENTO</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total MP1:</span>
                            <span id="res-imp1" class="text-white fw-bold">$ 0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Total MP2:</span>
                            <span id="res-imp2" class="text-white fw-bold">$ 0.00</span>
                        </div>
                    </div>

                    <div class="mt-auto">
                        <div class="bg-dark p-3 rounded border border-accent text-center shadow-lg mb-4">
                            <div class="text-accent small mb-1 fw-bold">TOTAL A PAGAR</div>
                            <div class="fs-1 fw-bold text-white" id="lbl-total-pago">$ 0.00</div>
                        </div>

                        <button class="btn btn-info w-100 fw-bold py-3 rounded-pill shadow-lg" id="btn-grabar-pago" disabled>
                            <i class="fas fa-save me-2"></i>CONFIRMAR PAGO
                        </button>
                        <button class="btn btn-outline-secondary w-100 mt-2 btn-sm" id="btn-cancelar">CANCELAR</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Reutilizamos modales de cheques de pagos_proveedores -->
<div id="modal-container-chq"></div>

<script>
(function() {
    let mpData = [];
    let endososIds = [];
    let chequesPropiosIds = [];
    let targetMp = 1;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        await cargarConfig();
        configurarSelect2();
        configurarListeners();
        $('#modal-container-chq').load('proveedores_pagar_facturas.php #modalEndoso, #modalPropio');
    }

    async function cargarConfig() {
        const resR = await fetch('api_vendedores_pagos.php?action=get_next_id', { headers: commonHeaders }).then(r => r.json());
        if (resR.success) $('#lbl-next-pago').text(resR.next_id);

        const resM = await fetch('api_pagos_proveedores.php?action=get_medios_pago', { headers: commonHeaders }).then(r => r.json());
        if (resM.success) {
            mpData = resM.data;
            $('#mp-1, #mp-2').empty().append(new Option('SELECCIONE...', ''));
            resM.data.forEach(m => { 
                $('#mp-1').append(new Option(m.medio, m.id)); 
                $('#mp-2').append(new Option(m.medio, m.id)); 
            });
        }
    }

    function configurarSelect2() {
        $('#cmb-vendedor-pago').select2({
            placeholder: 'BUSCAR VENDEDOR...',
            dropdownParent: $('#main-content'),
            ajax: {
                url: 'api_vendedores.php?action=list',
                dataType: 'json', delay: 250, headers: commonHeaders,
                processResults: d => ({ results: d.data.map(v => ({ id: v.id, text: v.nombre })) })
            },
            width: '100%'
        }).on('select2:select', e => cargarSaldo(e.params.data.id));
    }

    async function cargarSaldo(id) {
        const res = await fetch(`api_vendedores_pagos.php?action=get_saldo&vendedor_id=${id}`, { headers: commonHeaders }).then(r => r.json());
        if (res.success) {
            const s = parseFloat(res.saldo);
            $('#txt-saldo-cc').val('$ ' + s.toLocaleString('es-AR', {minimumFractionDigits: 2}))
                .removeClass('text-danger text-success')
                .addClass(s > 0 ? 'text-danger' : (s < 0 ? 'text-success' : ''));
        }
    }

    function configurarListeners() {
        $('.input-calc').on('input', calcularTotales);
        
        $('#mp-1, #mp-2').on('change', function() {
            const num = $(this).attr('id').split('-')[1];
            const medio = mpData.find(m => m.id == $(this).val());
            const isVal = medio && (medio.tipodecuenta === 'Cartera de Cheques' || medio.tipodecuenta === 'Cheques Emitidos');
            
            $(`#btn-val-${num}`).toggleClass('d-none', !isVal);
            $(`#imp-${num}`).prop('readonly', isVal);
            if (isVal) $(`#imp-${num}`).val(0);
            calcularTotales();
        });

        $('#btn-val-1').click(() => abrirValores(1));
        $('#btn-val-2').click(() => abrirValores(2));
        $('#btn-grabar-pago').click(grabar);
        $('#btn-cancelar').click(() => loadModule('vendedores_cta_cte.php'));
    }

    function calcularTotales() {
        const i1 = parseFloat($('#imp-1').val()) || 0;
        const i2 = parseFloat($('#imp-2').val()) || 0;
        const total = i1 + i2;

        $('#res-imp1').text('$ ' + i1.toFixed(2));
        $('#res-imp2').text('$ ' + i2.toFixed(2));
        $('#lbl-total-pago').text('$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        $('#btn-grabar-pago').prop('disabled', total <= 0 || !$('#cmb-vendedor-pago').val());
    }

    function abrirValores(num) {
        targetMp = num;
        const medio = mpData.find(m => m.id == $(`#mp-${num}`).val());
        if (medio.tipodecuenta === 'Cartera de Cheques') loadCarteraTerceros();
        else if (medio.tipodecuenta === 'Cheques Emitidos') loadModalChequePropio();
    }

    async function loadCarteraTerceros() {
        const res = await fetch('api_pagos_proveedores.php?action=get_cheques_cartera', { headers: commonHeaders }).then(r => r.json());
        const tbody = $('#tbody-cartera').empty();
        res.data.forEach((c) => {
            tbody.append(`<tr>
                <td class="text-center"><input type="checkbox" class="chk-cartera form-check-input" data-id="${c.id}" data-imp="${c.importe}" ${endososIds.includes(c.id) ? 'checked' : ''}></td>
                <td>${c.numero}</td><td>${c.banco}</td><td class="text-end">$ ${parseFloat(c.importe).toFixed(2)}</td><td>${c.vencimiento}</td>
            </tr>`);
        });
        $('.chk-cartera').off('change').on('change', function() {
            let sum = 0; endososIds = [];
            $('.chk-cartera:checked').each(function() { sum += parseFloat($(this).data('imp')); endososIds.push(parseInt($(this).data('id'))); });
            $('#lbl-total-cartera').text('$ ' + sum.toFixed(2));
            $(`#imp-${targetMp}`).val(sum.toFixed(2));
            calcularTotales();
        });
        $('#modalEndoso').modal('show');
    }

    async function loadModalChequePropio() {
        const res = await fetch('api_pagos_proveedores.php?action=get_cuentas_propias', { headers: commonHeaders }).then(r => r.json());
        const $sel = $('#cp-cuenta').empty().append(new Option('SELECCIONE...', ''));
        res.data.forEach(c => $sel.append(new Option(c.referencia + ' - ' + c.banco_nom, c.id)));
        
        $('#btn-add-propio').off('click').on('click', async () => {
            const imp = parseFloat($('#cp-imp').val()) || 0;
            if (imp <= 0 || !$('#cp-num').val() || !$('#cp-cuenta').val()) return;
            
            // Simulación: grabamos el cheque propio para obtener el ID, similar al flujo de proveedores
            // Pero como VB lo graba al final, nosotros recolectamos los datos para que la API lo haga en la transacción.
            // Para mantener consistencia con la API de proveedores que ya tienes, asumiremos que se insertan al final.
            // Nota: Aquí podrías llamar a una api de cheques propios si fuera necesario.
            
            Swal.fire({ title: 'Agregando cheque...', didOpen: () => Swal.showLoading() });
            const chqData = {
                proveedor: $('#cmb-vendedor-pago').val(),
                numero: $('#cp-num').val(),
                fechaemision: $('#txt-fecha').val(),
                fechavencimiento: $('#cp-vence').val(),
                importe: imp,
                cuenta: $('#cp-cuenta').val(),
                tipo: $('#cp-tipo').val()
            };

            // Usamos la API de proveedores para insertar el cheque propio si es necesario o lo mandamos en el array
            chequesPropiosIds.push(chqData); // Mandamos el objeto completo para procesar en el backend
            
            $(`#imp-${targetMp}`).val((parseFloat($(`#imp-${targetMp}`).val()) || 0) + imp);
            $('#modalPropio').modal('hide');
            Swal.close();
            calcularTotales();
        });
        $('#modalPropio').modal('show');
    }

    async function grabar() {
        const data = {
            vendedor_id: $('#cmb-vendedor-pago').val(),
            vendedor_nombre: $('#cmb-vendedor-pago').select2('data')[0].text,
            numcomp: $('#lbl-next-pago').text(),
            fecha: $('#txt-fecha').val(),
            nota: $('#txt-notas').val(),
            fiscal: $('#chk-fiscal').is(':checked'),
            mp1: $('#mp-1').val(),
            mp1imp: parseFloat($('#imp-1').val()) || 0,
            mp2: $('#mp-2').val(),
            mp2imp: parseFloat($('#imp-2').val()) || 0,
            endosos: endososIds,
            propios_data: chequesPropiosIds // Enviamos los objetos de cheques propios
        };

        if (await Swal.fire({ title: '¿Confirmar Pago?', text: 'Se registrará el pago y se afectarán cuentas y tesorería.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_vendedores_pagos.php?action=save_pago', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', 'Pago registrado correctamente.', 'success');
                loadModule('vendedores_cta_cte.php');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    init();
})();
</script>