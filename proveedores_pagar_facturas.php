<?php
/**
 * proveedores_pagar_facturas.php - MÓDULO DE PAGO A PROVEEDORES
 * Incluye carga de cheques propios y endoso de terceros.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">PAGO A PROVEEDORES</h4>
            <small class="text-accent opacity-75">Registro de egresos y gestión de valores</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="bg-dark p-2 px-3 rounded border border-warning shadow-sm">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="chk-pago-fiscal">
                    <label class="form-check-label text-warning small fw-bold" for="chk-pago-fiscal">PAGO FISCAL</label>
                </div>
            </div>
            <div class="bg-dark p-2 px-3 rounded border border-info shadow-sm text-center">
                <small class="text-muted d-block" style="font-size: 10px;">RECIBO Nº</small>
                <span class="text-info fw-bold fs-5" id="lbl-proximo-pago">...</span>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- SECTOR 1: PROVEEDOR -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <div class="row align-items-end g-3">
                        <div class="col-md-5">
                            <label class="form-label-custom">PROVEEDOR</label>
                            <select id="cmb-proveedor" class="form-control"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">SALDO C.C.</label>
                            <input type="text" id="txt-saldo-cc" class="form-control text-end fw-bold text-danger" readonly value="$ 0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">FECHA PAGO</label>
                            <input type="date" id="txt-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">NOTAS</label>
                            <input type="text" id="txt-nota" class="form-control" placeholder="Opcional...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTOR 2: FACTURAS PENDIENTES -->
        <div class="col-md-8">
            <div class="card card-filtros p-0 h-100 shadow-sm overflow-hidden border-secondary-subtle">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-white small fw-bold"><i class="fas fa-file-invoice-dollar me-2 text-accent"></i>PENDIENTES DE PAGO</span>
                    <span class="text-muted small">Seleccionado: <b class="text-accent" id="lbl-total-seleccionado">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 400px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>NÚMERO</th>
                                <th class="text-end">TOTAL</th>
                                <th class="text-end text-accent">PENDIENTE</th>
                                <th>VENCE</th>
                                <th class="text-center">FISC</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-pendientes">
                            <tr><td colspan="7" class="text-center p-5 text-muted opacity-50">Seleccione un proveedor para ver deudas</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTOR 3: FORMA DE PAGO -->
        <div class="col-md-4">
            <div class="card card-filtros shadow-sm border-info-subtle">
                <div class="card-body p-3">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-3 small fw-bold">GESTIÓN DE PAGOS</h6>
                    
                    <div class="mb-3">
                        <label class="form-label-custom small">MEDIO DE PAGO 1</label>
                        <select id="mp-1" class="form-select form-select-sm mb-1"></select>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" id="imp-1" class="form-control text-end input-calc fw-bold" step="0.01">
                            <button class="btn btn-warning d-none" id="btn-val-1" title="Cargar Valores"><i class="fas fa-money-check-alt"></i></button>
                        </div>
                    </div>

                    <div class="mb-3 border-top border-secondary pt-3">
                        <label class="form-label-custom small">MEDIO DE PAGO 2</label>
                        <select id="mp-2" class="form-select form-select-sm mb-1"><option value="">-- NINGUNO --</option></select>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" id="imp-2" class="form-control text-end input-calc fw-bold" step="0.01">
                            <button class="btn btn-warning d-none" id="btn-val-2" title="Cargar Valores"><i class="fas fa-money-check-alt"></i></button>
                        </div>
                    </div>

                    <div class="mt-4 bg-dark p-3 rounded border border-accent text-center shadow">
                        <div class="text-accent small mb-1 fw-bold">IMPORTE TOTAL A PAGAR</div>
                        <div class="fs-2 fw-bold text-white" id="lbl-total-pago">$ 0.00</div>
                    </div>
                    <!-- Retenciones como agente (retper_helpers.php): solo aparece si corresponden -->
                    <div class="mt-2 p-2 rounded border border-warning d-none" id="box-retenciones">
                        <div class="small fw-bold text-warning mb-1"><i class="fas fa-percent me-1"></i>RETENCIONES A PRACTICAR</div>
                        <div id="lst-retenciones" class="small"></div>
                        <div class="d-flex justify-content-between small border-top border-secondary pt-1 mt-1"><span>Total retenido</span><strong id="lbl-total-ret">$ 0,00</strong></div>
                        <div class="d-flex justify-content-between small"><span>Total cancelado (pago + retenciones)</span><strong id="lbl-total-cancelado">$ 0,00</strong></div>
                        <button type="button" class="btn btn-sm btn-outline-warning w-100 mt-2 d-none" id="btn-sugerir-pago"></button>
                    </div>
                    <button class="btn btn-info w-100 fw-bold py-3 mt-3 rounded-pill shadow" id="btn-grabar" disabled>
                        <i class="fas fa-save me-2"></i>CONFIRMAR Y GRABAR
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ENDOSO (CHEQUES DE TERCEROS) -->
<div class="modal fade" id="modalEndoso" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-exchange-alt me-2 text-warning"></i>ENDOSO DE CHEQUES EN CARTERA</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="max-height: 400px;">
                    <table class="table table-dark table-hover mb-0 small">
                        <thead class="sticky-top">
                            <tr><th>#</th><th>NÚMERO</th><th>BANCO</th><th class="text-end text-accent">IMPORTE</th><th>VENCE</th></tr>
                        </thead>
                        <tbody id="tbody-cartera"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <div class="me-auto text-warning fw-bold small">TOTAL SELECCIONADO: <span id="lbl-total-cartera" class="fs-5">$ 0.00</span></div>
                <button type="button" class="btn btn-info" data-bs-dismiss="modal">ACEPTAR CARGA</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CHEQUE PROPIO (EMISIÓN) -->
<div class="modal fade" id="modalPropio" tabindex="-1">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content bg-escritorio border-info shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-pen-alt me-2 text-info"></i>EMISIÓN DE CHEQUE PROPIO</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label-custom">CUENTA BANCARIA / BANCO</label>
                        <select id="cp-cuenta" class="form-select"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">NÚMERO DE CHEQUE</label>
                        <input type="text" id="cp-num" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">TIPO DE VALOR</label>
                        <select id="cp-tipo" class="form-select">
                            <option>COMUN</option><option selected>DIFERIDO</option><option>E-CHEQ</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom text-accent">IMPORTE</label>
                        <input type="number" id="cp-imp" class="form-control text-end fw-bold" step="0.01">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">VENCIMIENTO</label>
                        <input type="date" id="cp-vence" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-info w-100 fw-bold py-2 shadow" id="btn-add-propio">AGREGAR CHEQUE A LA LISTA</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let facturasPendientes = [], chequesCartera = [], endososIds = [], chequesPropios = [], mpData = [], targetMp = 1;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        await cargarConfig();
        configurarSelect2();
        $('.input-calc').on('input', calcularTotales);
        $('#btn-grabar').on('click', grabarPago);
        $('#btn-val-1').click(() => openValuesModal(1));
        $('#btn-val-2').click(() => openValuesModal(2));
        $('#btn-add-propio').click(agregarChequePropio);
    }

    /**
     * Carga inicial de datos de configuración y medios de pago
     */
    async function cargarConfig() {
        // ID Próximo Recibo
        const resR = await fetch('api_pagos_proveedores.php?action=get_next_id', { headers: commonHeaders }).then(r => r.json());
        if (resR.success) $('#lbl-proximo-pago').text(resR.next_id);

        // Medios de Pago Habilitados para PAGOS
        const resM = await fetch('api_pagos_proveedores.php?action=get_medios_pago', { headers: commonHeaders }).then(r => r.json());
        if (resM.success) {
            mpData = resM.data;
            $('#mp-1, #mp-2').empty().append(new Option('SELECCIONE...', ''));
            resM.data.forEach(m => { 
                $('#mp-1').append(new Option(m.medio, m.id)); 
                $('#mp-2').append(new Option(m.medio, m.id)); 
            });
        }

        // Listener para cambios de medios de pago
        $('#mp-1, #mp-2').on('change', function() {
            const num = $(this).attr('id').split('-')[1];
            const medio = mpData.find(m => m.id == $(this).val());
            const isChq = medio && (medio.tipodecuenta === 'Cartera de Cheques' || medio.tipodecuenta === 'Cheques Emitidos');
            
            $(`#btn-val-${num}`).toggleClass('d-none', !isChq);
            $(`#imp-${num}`).prop('readonly', isChq);
            
            if (isChq) $(`#imp-${num}`).val(0); // Forzar 0 hasta que cargue valores
            else { 
                if (num == 1) endososIds = []; 
                if (num == 1) chequesPropios = []; 
            }
            calcularTotales();
        });
    }

    /**
     * Gestión de Modales de Valores
     */
    function openValuesModal(num) {
        targetMp = num;
        const medio = mpData.find(m => m.id == $(`#mp-${num}`).val());
        if (medio.tipodecuenta === 'Cartera de Cheques') loadCartera();
        else if (medio.tipodecuenta === 'Cheques Emitidos') loadCuentasPropias();
    }

    async function loadCartera() {
        const res = await fetch('api_pagos_proveedores.php?action=get_cheques_cartera', { headers: commonHeaders }).then(r => r.json());
        const tbody = $('#tbody-cartera').empty();
        chequesCartera = res.data;
        res.data.forEach((c, i) => {
            tbody.append(`<tr>
                <td class="text-center"><input type="checkbox" class="chk-cartera form-check-input" data-idx="${i}" ${endososIds.includes(c.id) ? 'checked' : ''}></td>
                <td class="fw-bold">${c.numero}</td><td>${c.banco}</td><td class="text-end fw-bold text-accent">$ ${parseFloat(c.importe).toFixed(2)}</td><td>${c.vencimiento}</td>
            </tr>`);
        });
        $('.chk-cartera').off().on('change', updateCarteraTotal);
        $('#modalEndoso').modal('show');
    }

    function updateCarteraTotal() {
        let total = 0; endososIds = [];
        $('.chk-cartera:checked').each(function() {
            const idx = $(this).data('idx');
            total += parseFloat(chequesCartera[idx].importe);
            endososIds.push(chequesCartera[idx].id);
        });
        $('#lbl-total-cartera').text('$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $(`#imp-${targetMp}`).val(total.toFixed(2));
        calcularTotales();
    }

    async function loadCuentasPropias() {
        const res = await fetch('api_pagos_proveedores.php?action=get_cuentas_propias', { headers: commonHeaders }).then(r => r.json());
        const $select = $('#cp-cuenta').empty().append(new Option('SELECCIONE...', ''));
        
        if (res.success && res.data) {
            res.data.forEach(c => {
                const option = new Option(c.referencia + ' - ' + c.banco_nom, c.id);
                // IMPORTANTE: Se guarda el banco_id numérico recuperado de la API
                $(option).attr('data-banco', c.banco_id); 
                $select.append(option);
            });
        }
        $('#modalPropio').modal('show');
    }

    function agregarChequePropio() {
        const imp = parseFloat($('#cp-imp').val()) || 0;
        if (imp <= 0 || !$('#cp-num').val() || !$('#cp-cuenta').val()) return Swal.fire('Error', 'Complete todos los datos del cheque.', 'error');
        
        chequesPropios.push({
            cuenta_id: $('#cp-cuenta').val(),
            numero: $('#cp-num').val(),
            tipo: $('#cp-tipo').val(),
            importe: imp,
            vencimiento: $('#cp-vence').val(),
            banco_id: $('#cp-cuenta option:selected').attr('data-banco') // Recuperamos el ID numérico
        });
        
        let total = chequesPropios.reduce((a, b) => a + b.importe, 0);
        $(`#imp-${targetMp}`).val(total.toFixed(2));
        $('#modalPropio').modal('hide');
        $('#cp-num, #cp-imp').val('');
        calcularTotales();
    }

    /**
     * Búsqueda de Proveedores
     */
    function configurarSelect2() {
        $('#mp-1, #mp-2').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'BUSCAR MEDIO DE PAGO...',
            minimumResultsForSearch: 0
        });
        $('#cmb-proveedor').select2({
            placeholder: 'BUSCAR PROVEEDOR...',
            dropdownParent: $('#main-content'),
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        }).on('select2:select', e => recuperarDeuda(e.params.data.id));
    }

    async function recuperarDeuda(id) {
        const res = await fetch(`api_pagos_proveedores.php?action=get_pendientes&proveedor=${id}`, { headers: commonHeaders }).then(r => r.json());
        const tbody = $('#tbody-pendientes').empty();
        if (res.success) {
            $('#txt-saldo-cc').val('$ ' + parseFloat(res.saldo).toLocaleString('es-AR', {minimumFractionDigits: 2}));
            facturasPendientes = res.data;
            if (facturasPendientes.length === 0) {
                tbody.append('<tr><td colspan="7" class="text-center p-4 text-warning fw-bold">EL PROVEEDOR NO TIENE DEUDAS PENDIENTES (PAGO A CUENTA)</td></tr>');
            } else {
                facturasPendientes.forEach((f, i) => {
                    tbody.append(`<tr>
                        <td class="text-center"><input type="checkbox" class="chk-pend form-check-input" data-idx="${i}"></td>
                        <td>${f.fecha_f}</td><td class="fw-bold text-white">${f.num_factura}</td>
                        <td class="text-end">$ ${parseFloat(f.total).toFixed(2)}</td>
                        <td class="text-end text-accent fw-bold">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
                        <td>${f.vence_f}</td><td class="text-center">${f.fiscal ? '✅' : '-'}</td>
                    </tr>`);
                });
                $('.chk-pend').change(validarSeleccionFiscal);
            }
        }
    }

    function validarSeleccionFiscal() {
        const seleccionadas = $('.chk-pend:checked').map(function() {
            return facturasPendientes[$(this).data('idx')];
        }).get();
        const condiciones = new Set(seleccionadas.map(f => Number(f.fiscal) === 1 ? 1 : 0));

        if (condiciones.size > 1) {
            $(this).prop('checked', false);
            Swal.fire('Operación rechazada', 'No se pueden mezclar comprobantes fiscales y no fiscales en el mismo pago.', 'error');
            return calcularTotales();
        }

        $('#chk-pago-fiscal')
            .prop('disabled', seleccionadas.length > 0)
            .prop('checked', seleccionadas.length > 0 ? Number(seleccionadas[0].fiscal) === 1 : $('#chk-pago-fiscal').is(':checked'));
        calcularTotales();
    }

    // ---- Retenciones como agente: el servidor calcula; acá solo se muestran ----
    let retencionesTotal = 0, retencionesTimer = null, retencionesSugerido = 0;
    const fmtRet = n => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function actualizarRetenciones() {
        const prov = $('#cmb-proveedor').val();
        if (!prov) { retencionesTotal = 0; $('#box-retenciones').addClass('d-none'); return; }
        const efectivo = (parseFloat($('#imp-1').val()) || 0) + (parseFloat($('#imp-2').val()) || 0);
        const facturas = $('.chk-pend:checked').map(function () { return facturasPendientes[$(this).data('idx')].id; }).get();
        try {
            const res = await fetch('api_pagos_proveedores.php?action=calcular_retenciones', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({ proveedor_id: prov, facturas, efectivo: efectivo > 0 ? efectivo : null, fecha: $('#txt-fecha').val() })
            }).then(r => r.json());
            if (!res.success) throw new Error(res.error);
            const lineas = res.lineas.filter(l => l.importe > 0);
            retencionesTotal = res.total;
            retencionesSugerido = res.a_pagar;
            $('#box-retenciones').toggleClass('d-none', !lineas.length);
            $('#lst-retenciones').html(lineas.map(l => `<div class="d-flex justify-content-between"><span>${$('<div>').text(l.nombre).html()} <span class="text-white-50">(${l.alicuota}% s/ $ ${fmtRet(l.base)})</span></span><span>$ ${fmtRet(l.importe)}</span></div>`).join(''));
            $('#lbl-total-ret').text('$ ' + fmtRet(res.total));
            $('#lbl-total-cancelado').text('$ ' + fmtRet(res.cancelado));
            // Sin importe cargado: se sugiere pagar el total de las facturas menos lo retenido.
            const sugerir = lineas.length && efectivo <= 0 && res.a_pagar > 0;
            $('#btn-sugerir-pago').toggleClass('d-none', !sugerir).text(`PAGAR $ ${fmtRet(res.a_pagar)} (TOTAL - RETENCIONES)`);
        } catch (e) {
            retencionesTotal = 0;
            console.error('No se pudieron calcular las retenciones', e);
        }
    }
    function programarRetenciones() { clearTimeout(retencionesTimer); retencionesTimer = setTimeout(actualizarRetenciones, 400); }
    $(document).off('click.ret').on('click.ret', '#btn-sugerir-pago', function () {
        $('#imp-1').val(retencionesSugerido.toFixed(2)); calcularTotales();
    });

    function calcularTotales() {
        programarRetenciones();
        let selec = 0;
        $('.chk-pend:checked').each(function() { selec += parseFloat(facturasPendientes[$(this).data('idx')].pendiente); });
        $('#lbl-total-seleccionado').text('$ ' + selec.toLocaleString('es-AR', {minimumFractionDigits: 2}));

        const total = (parseFloat($('#imp-1').val()) || 0) + (parseFloat($('#imp-2').val()) || 0);
        $('#lbl-total-pago').text('$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        $('#btn-grabar').prop('disabled', total <= 0 || !$('#cmb-proveedor').val());
    }

    async function grabarPago() {
        const totalP = parseFloat($('#lbl-total-pago').text().replace('$ ', '').replace(/\./g, '').replace(',', '.'));
        const totalS = parseFloat($('#lbl-total-seleccionado').text().replace('$ ', '').replace(/\./g, '').replace(',', '.'));

        if (totalP > totalS && totalS > 0) {
            if (!await Swal.fire({ title: 'Excedente detectado', text: 'El monto a pagar supera la deuda seleccionada. El saldo quedará a favor del proveedor. ¿Continuar?', icon: 'warning', showCancelButton: true }).then(r => r.isConfirmed)) return;
        }

        const data = {
            numcomp: $('#lbl-proximo-pago').text(),
            proveedor_id: $('#cmb-proveedor').val(),
            fecha: $('#txt-fecha').val(),
            nota: $('#txt-nota').val(),
            mp1: $('#mp-1').val(),
            mp1imp: parseFloat($('#imp-1').val()) || 0,
            mp2: $('#mp-2').val(),
            mp2imp: parseFloat($('#imp-2').val()) || 0,
            fiscal: $('#chk-pago-fiscal').is(':checked'),
            facturas_seleccionadas: $('.chk-pend:checked').map(function() { return facturasPendientes[$(this).data('idx')]; }).get(),
            endosos: endososIds,
            propios: chequesPropios
        };
        // Se recalculan las retenciones con los importes finales antes de confirmar.
        clearTimeout(retencionesTimer);
        await actualizarRetenciones();
        data.retenciones_total = retencionesTotal;
        const textoRet = retencionesTotal > 0 ? ` y se practicarán retenciones por $ ${fmtRet(retencionesTotal)} (cancela $ ${fmtRet(totalP + retencionesTotal)})` : '';

        if (await Swal.fire({ title: '¿Confirmar Pago?', text: `Se grabará un recibo por $ ${totalP.toFixed(2)}${textoRet}`, icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            try {
                const resp = await fetch('api_pagos_proveedores.php?action=save_pago', {
                    method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(r => r.json());

                if (resp.success) {
                    if (resp.retenciones && resp.retenciones.length) {
                        const r = await Swal.fire({ title: 'Pago registrado', text: `Se practicaron ${resp.retenciones.length} retención(es). ¿Imprimir los certificados?`, icon: 'success', showCancelButton: true, confirmButtonText: 'IMPRIMIR', cancelButtonText: 'AHORA NO' });
                        if (r.isConfirmed) window.open('imprimir_certificado_retencion.php?pago=' + resp.id, '_blank');
                    } else {
                        await Swal.fire('Éxito', 'Pago registrado y valores afectados correctamente.', 'success');
                    }
                    limpiarTodoElFormulario();
                } else Swal.fire('Error', resp.error, 'error');
            } catch (e) {
                Swal.fire('Error', 'Fallo en la comunicación con el servidor.', 'error');
            }
        }
    }

    /**
     * Limpieza completa del formulario
     */
    function limpiarTodoElFormulario() {
        // 1. Resetear estados internos
        facturasPendientes = []; 
        endososIds = []; 
        chequesPropios = [];
        
        // 2. Limpiar visuales de grilla y etiquetas
        $('#tbody-pendientes').empty().append('<tr><td colspan="7" class="text-center p-5 text-muted opacity-50">Seleccione un proveedor para ver deudas</td></tr>');
        $('#lbl-total-seleccionado, #lbl-total-pago').text('$ 0.00');
        $('#txt-saldo-cc').val('$ 0.00');
        retencionesTotal = 0;
        $('#box-retenciones').addClass('d-none');
        
        // 3. Limpiar inputs de texto
        $('#txt-nota').val('');
        $('#chk-pago-fiscal').prop('checked', false).prop('disabled', false);
        $('#imp-1, #imp-2').val('').prop('readonly', false);
        
        // 4. Resetear Selectores
        // Para Select2 (Proveedor)
        $('#cmb-proveedor').val(null).trigger('change');
        
        // Para Medios de Pago (Selects normales)
        $('#mp-1').val('').trigger('change'); // Cambiar a valor vacío (SELECCIONE...)
        $('#mp-2').val('').trigger('change');
        
        // 5. Ocultar botones de valores
        $('#btn-val-1, #btn-val-2').addClass('d-none');
        
        // 6. Actualizar próximo ID
        fetch('api_pagos_proveedores.php?action=get_next_id', { headers: commonHeaders })
            .then(r => r.json())
            .then(res => { if (res.success) $('#lbl-proximo-pago').text(res.next_id); });
            
        // 7. Bloquear botón grabar
        $('#btn-grabar').prop('disabled', true);
    }

    init();
})();
</script>
