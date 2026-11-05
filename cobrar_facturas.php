<?php
/**
 * cobrar_facturas.php - MÓDULO DE COBRO A CLIENTES
 * Actualizado: Agregados campos técnicos de cheques y lógica de persistencia.
 * Corrección: Grabación de banco y función de limpieza robusta.
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">COBRO A CLIENTES</h4>
            <small class="text-accent opacity-75">Gestión de cobranzas, retenciones e imputación de facturas</small>
        </div>
        <div class="d-flex gap-3">
            <div class="bg-dark p-2 px-3 rounded border border-info shadow-sm text-center">
                <small class="text-muted d-block" style="font-size: 10px;">RECIBO Nº</small>
                <span class="text-info fw-bold fs-5" id="lbl-num-recibo">...</span>
            </div>
            <div id="status-fiscal"
                class="bg-dark p-2 px-3 rounded border border-warning shadow-sm d-none align-items-center">
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="chk-es-fiscal">
                    <label class="form-check-label text-warning small fw-bold" for="chk-es-fiscal"
                        style="cursor:pointer">PAGO FISCAL</label>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- SELECCIÓN DE CLIENTE Y SALDO -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <div class="row align-items-end g-3">
                        <div class="col-md-5">
                            <label class="form-label-custom">CLIENTE</label>
                            <select id="cmb-cliente" class="form-control"></select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">SALDO C.C.</label>
                            <input type="text" id="txt-saldo-cc" class="form-control text-end fw-bold text-danger"
                                readonly value="$ 0.00">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">FECHA COBRO</label>
                            <input type="date" id="txt-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 text-end">
                            <button class="btn btn-outline-info" id="btn-buscar-cliente"><i
                                    class="fas fa-search me-2"></i>BUSCAR AVANZADO</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FACTURAS PENDIENTES -->
        <div class="col-md-8">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm h-100 border-secondary-subtle">
                <div
                    class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-white small fw-bold"><i
                            class="fas fa-file-invoice-dollar me-2 text-accent"></i>COMPROBANTES PENDIENTES</span>
                    <span class="text-muted small">Seleccionado para imputar: <b class="text-accent"
                            id="lbl-total-seleccionado">$ 0.00</b></span>
                </div>
                <div class="table-responsive" style="max-height: 400px; min-height: 300px;">
                    <table class="table table-hover align-middle mb-0 small" id="tabla-facturas">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>FECHA</th>
                                <th>NÚMERO</th>
                                <th class="text-end">TOTAL</th>
                                <th class="text-end">PENDIENTE</th>
                                <th>VENCE</th>
                                <th class="text-center">FISCAL</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-facturas">
                            <tr>
                                <td colspan="7" class="text-center p-5 text-muted">Seleccione un cliente para ver deudas
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- MEDIOS DE PAGO Y RETENCIONES -->
        <div class="col-md-4">
            <!-- Retenciones -->
            <div class="card card-filtros shadow-sm mb-3">
                <div class="card-body p-3">
                    <h6 class="text-warning border-bottom border-secondary pb-2 mb-3 small fw-bold">RETENCIONES</h6>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label-custom small">GANANCIAS</label>
                            <input type="number" id="ret-gan" class="form-control form-control-sm text-end input-calc"
                                step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom small">IIBB</label>
                            <input type="number" id="ret-iibb" class="form-control form-control-sm text-end input-calc"
                                step="0.01" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom small">IVA</label>
                            <input type="number" id="ret-iva" class="form-control form-control-sm text-end input-calc"
                                step="0.01" value="0">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Medios de Pago -->
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-3 small fw-bold">MEDIOS DE COBRO</h6>

                    <div class="mb-3">
                        <label class="form-label-custom small">PRINCIPAL</label>
                        <select id="mp-1" class="form-select form-select-sm mb-1"></select>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" id="imp-1" class="form-control text-end input-calc" step="0.01">
                            <button class="btn btn-warning d-none" id="btn-chq-1" title="Cargar Cheques"><i
                                    class="fas fa-money-check"></i></button>
                        </div>
                        <div class="d-none mt-1" id="tj-box-1">
                            <div class="row g-1">
                                <div class="col-6"><select id="tj-id-1" class="form-select form-select-sm" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="tj-cuotas-1" class="form-control form-control-sm text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-4"><input type="text" id="tj-cupon-1" class="form-control form-control-sm" maxlength="30" placeholder="Cupón" title="Número de cupón (opcional)"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 border-top border-secondary pt-3">
                        <label class="form-label-custom small">SECUNDARIO</label>
                        <select id="mp-2" class="form-select form-select-sm mb-1">
                            <option value="">-- NINGUNO --</option>
                        </select>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-dark border-secondary text-white">$</span>
                            <input type="number" id="imp-2" class="form-control text-end input-calc" step="0.01">
                            <button class="btn btn-warning d-none" id="btn-chq-2" title="Cargar Cheques"><i
                                    class="fas fa-money-check"></i></button>
                        </div>
                        <div class="d-none mt-1" id="tj-box-2">
                            <div class="row g-1">
                                <div class="col-6"><select id="tj-id-2" class="form-select form-select-sm" title="Tarjeta"></select></div>
                                <div class="col-2"><input type="number" id="tj-cuotas-2" class="form-control form-control-sm text-center" min="1" max="99" value="1" title="Cuotas"></div>
                                <div class="col-4"><input type="text" id="tj-cupon-2" class="form-control form-control-sm" maxlength="30" placeholder="Cupón" title="Número de cupón (opcional)"></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 bg-dark p-3 rounded border border-accent text-center shadow-lg">
                        <div class="text-accent small mb-1 fw-bold">TOTAL A COBRAR</div>
                        <div class="fs-2 fw-bold text-white" id="lbl-total-cobro">$ 0.00</div>
                    </div>

                    <button class="btn btn-info w-100 fw-bold py-3 mt-3 rounded-pill shadow" id="btn-confirmar"
                        disabled>
                        <i class="fas fa-check-circle me-2"></i>CONFIRMAR COBRO
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL CHEQUES DE TERCEROS -->
<div class="modal fade" id="modalCheques" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-university me-2 text-warning"></i>CARGA DE VALORES
                    (CHEQUES)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-4 border-bottom border-secondary pb-4">
                    <div class="col-md-3">
                        <label class="form-label-custom">Nº CHEQUE</label>
                        <input type="text" id="chq-num" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">BANCO</label>
                        <select id="chq-banco" class="form-select"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">IMPORTE</label>
                        <input type="number" id="chq-imp" class="form-control text-end fw-bold" step="0.01">
                    </div>

                    <div class="col-md-3"><label class="form-label-custom">EMISIÓN</label><input type="date"
                            id="chq-f-emi" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-3"><label class="form-label-custom text-warning">VENCIMIENTO</label><input
                            type="date" id="chq-f-vto" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-3">
                        <label class="form-label-custom">TIPO</label>
                        <select id="chq-tipo" class="form-select">
                            <option>Común</option>
                            <option selected>Diferido</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom">ORIGEN</label>
                        <select id="chq-origen" class="form-select">
                            <option selected>Cliente</option>
                            <option>De Terceros</option>
                        </select>
                    </div>

                    <div class="col-md-6"><label class="form-label-custom">EMISOR / FIRMANTE</label><input type="text"
                            id="chq-emisor" class="form-control"></div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="chq-nala">
                            <label class="form-check-label text-muted small" for="chq-nala">N.A.L.A.</label>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-warning w-100 fw-bold" id="btn-add-chq"><i
                                class="fas fa-plus me-2"></i>AGREGAR</button>
                    </div>
                </div>

                <div class="table-responsive" style="max-height: 200px;">
                    <table class="table table-dark table-sm small">
                        <thead>
                            <tr>
                                <th>Nº</th>
                                <th>BANCO</th>
                                <th class="text-end">IMPORTE</th>
                                <th>VTO</th>
                                <th>ACC</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cheques-modal"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <div class="me-auto text-warning fw-bold">TOTAL CHEQUES: <span id="lbl-total-chq-modal">$ 0.00</span>
                </div>
                <button type="button" class="btn btn-info" data-bs-dismiss="modal">ACEPTAR CARGA</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let facturasPendientes = [];
        let chequesCargados = [];
        let mpData = [];
        let tarjetasData = [];
        let targetMp = 1;
        const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

        async function init() {
            await cargarConfigInicial();
            configurarSelect2();
            configurarEventos();
        }

        async function cargarConfigInicial() {
            const resR = await fetch('api_cobros.php?action=get_next_id', { headers: commonHeaders }).then(r => r.json());
            if (resR.success) $('#lbl-num-recibo').text(resR.next_id);

            const resM = await fetch('api_cobros.php?action=get_medios', { headers: commonHeaders }).then(r => r.json());
            if (resM.success) {
                mpData = resM.data;
                tarjetasData = resM.tarjetas || [];
                $('#tj-id-1, #tj-id-2').empty().append(new Option('TARJETA...', ''));
                tarjetasData.forEach(t => { $('#tj-id-1').append(new Option(t.descripcion, t.id)); $('#tj-id-2').append(new Option(t.descripcion, t.id)); });
                $('#mp-1, #mp-2').empty().append(new Option('SELECCIONE...', ''));
                resM.data.forEach(m => {
                    $('#mp-1').append(new Option(m.medio, m.id));
                    $('#mp-2').append(new Option(m.medio, m.id));
                });
            }

            const resBancos = await fetch('api_helpers.php?action=get_bancos', { headers: commonHeaders }).then(r => r.json());
            if (resBancos.success) {
                $('#chq-banco').empty().append(new Option('SELECCIONE BANCO...', ''));
                resBancos.data.forEach(b => $('#chq-banco').append(new Option(b.denominacion, b.id)));
            }
        }

        function configurarSelect2() {
            $('#mp-1, #mp-2').select2({
                dropdownParent: $('#main-content'),
                width: '100%',
                placeholder: 'BUSCAR MEDIO DE COBRO...',
                minimumResultsForSearch: 0
            });
            $('#cmb-cliente').select2({
                placeholder: 'BUSCAR CLIENTE POR NOMBRE O ID...',
                minimumInputLength: 2,
                dropdownParent: $('#main-content'),
                ajax: {
                    url: 'api_cobros.php?action=search_clientes',
                    dataType: 'json',
                    delay: 250,
                    headers: commonHeaders,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: data.data.map(c => ({ id: c.id, text: `${c.razon} - [ID: ${c.id}]`, razon: c.razon }))
                    })
                },
                width: '100%'
            }).on('select2:select', function (e) {
                recuperarPendientes(e.params.data.id);
            });
        }

        function configurarEventos() {
            $('.input-calc').on('input', calcularTotales);

            $('#mp-1, #mp-2').on('change', function () {
                const num = $(this).attr('id').split('-')[1];
                const medio = mpData.find(m => m.id == $(this).val());
                const isCheque = (medio && medio.tipodecuenta === 'Cartera de Cheques');
                // Datos del cupón si el medio es una tarjeta (solo si hay tarjetas cargadas).
                const isTarjeta = !!(medio && medio.tipodecuenta === 'Tarjetas' && tarjetasData.length);
                $(`#tj-box-${num}`).toggleClass('d-none', !isTarjeta);
                if (!isTarjeta) { $(`#tj-id-${num}`).val(''); $(`#tj-cuotas-${num}`).val(1); $(`#tj-cupon-${num}`).val(''); }

                $(`#btn-chq-${num}`).toggleClass('d-none', !isCheque);
                $(`#imp-${num}`).prop('readonly', isCheque).val(isCheque ? 0 : $(`#imp-${num}`).val());

                if (!isCheque) {
                    chequesCargados = chequesCargados.filter(c => c.target != num);
                    actualizarTablaCheques();
                }
                calcularTotales();
            });

            $('#btn-chq-1').click(() => { targetMp = 1; $('#modalCheques').modal('show'); });
            $('#btn-chq-2').click(() => { targetMp = 2; $('#modalCheques').modal('show'); });
            $('#btn-add-chq').click(agregarCheque);
            $('#btn-confirmar').click(grabarCobro);

            $('#btn-buscar-cliente').click(() => {
                // Lógica de búsqueda avanzada existente...
            });
        }

        async function recuperarPendientes(id) {
            const res = await fetch(`api_cobros.php?action=get_pendientes&cliente=${id}`, { headers: commonHeaders }).then(r => r.json());
            const tbody = $('#tbody-facturas').empty();

            if (res.success) {
                $('#txt-saldo-cc').val('$ ' + parseFloat(res.saldo).toLocaleString('es-AR', { minimumFractionDigits: 2 }));
                facturasPendientes = res.data;

                if (facturasPendientes.length === 0) {
                    const result = await Swal.fire({
                        title: 'Sin deudas',
                        text: 'El cliente no tiene comprobantes pendientes. ¿Desea cargar un pago a cuenta?',
                        icon: 'info',
                        showCancelButton: true
                    });
                    if (result.isConfirmed) {
                        $('#status-fiscal').removeClass('d-none').addClass('d-flex');
                        tbody.append('<tr><td colspan="7" class="text-center p-4 text-warning">MODO: PAGO A CUENTA (SIN COMPROBANTES SELECCIONADOS)</td></tr>');
                    } else {
                        $('#cmb-cliente').val(null).trigger('change');
                        $('#txt-saldo-cc').val('$ 0.00');
                    }
                } else {
                    $('#status-fiscal').addClass('d-none');
                    facturasPendientes.forEach((f, idx) => {
                        tbody.append(`
                        <tr>
                            <td class="text-center"><input type="checkbox" class="form-check-input chk-factura" data-idx="${idx}" ${res.autoimputar ? 'checked' : ''}></td>
                            <td>${f.fecha_f}</td>
                            <td class="fw-bold">${f.tipo_label} ${f.num_factura}</td>
                            <td class="text-end">$ ${parseFloat(f.total).toFixed(2)}</td>
                            <td class="text-end text-accent fw-bold">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
                            <td>${f.vence_f}</td>
                            <td class="text-center">${f.es_fiscal ? '✅' : '<i class="fas fa-times text-muted opacity-50"></i>'}</td>
                        </tr>
                    `);
                    });
                    $('.chk-factura').change(calcularTotales);
                }
            }
            calcularTotales();
        }

        function calcularTotales() {
            let seleccionado = 0;
            let fiscalCount = 0;
            let noFiscalCount = 0;

            $('.chk-factura:checked').each(function () {
                const idx = $(this).data('idx');
                const f = facturasPendientes[idx];
                if (f) {
                    seleccionado += parseFloat(f.pendiente) || 0;
                    if (f.es_fiscal) fiscalCount++; else noFiscalCount++;
                }
            });

            if (fiscalCount > 0 && noFiscalCount > 0) {
                $(this).prop('checked', false);
                calcularTotales();
                Swal.fire('Operación rechazada', 'No se pueden mezclar comprobantes fiscales y no fiscales en el mismo cobro.', 'error');
                return;
            }

            const tieneComprobantes = fiscalCount + noFiscalCount > 0;
            $('#status-fiscal')
                .toggleClass('d-none', tieneComprobantes)
                .toggleClass('d-flex', !tieneComprobantes);
            if (tieneComprobantes) {
                $('#chk-es-fiscal').prop('checked', fiscalCount > 0);
            }

            $('#lbl-total-seleccionado').text('$ ' + seleccionado.toLocaleString('es-AR', { minimumFractionDigits: 2 }));

            const rGan = parseFloat($('#ret-gan').val()) || 0;
            const rIibb = parseFloat($('#ret-iibb').val()) || 0;
            const rIva = parseFloat($('#ret-iva').val()) || 0;
            const imp1 = parseFloat($('#imp-1').val()) || 0;
            const imp2 = parseFloat($('#imp-2').val()) || 0;

            const totalCobro = imp1 + imp2 + rGan + rIibb + rIva;
            $('#lbl-total-cobro').text('$ ' + totalCobro.toLocaleString('es-AR', { minimumFractionDigits: 2 }));

            const clienteOk = $('#cmb-cliente').val() !== null;
            const importeOk = totalCobro > 0;
            $('#btn-confirmar').prop('disabled', !(clienteOk && importeOk));
        }

        function agregarCheque() {
            const num = $('#chq-num').val();
            const bancoId = $('#chq-banco').val();
            const bancoNom = $('#chq-banco option:selected').text();
            const imp = parseFloat($('#chq-imp').val()) || 0;
            const emi = $('#chq-f-emi').val();
            const vto = $('#chq-f-vto').val();
            const tipo = $('#chq-tipo').val();
            const origen = $('#chq-origen').val();
            const emisor = $('#chq-emisor').val();
            const nala = $('#chq-nala').is(':checked');

            // CORRECCIÓN: Validación estricta del ID del banco
            if (!num || !bancoId || bancoId === "" || bancoId === "SELECCIONE BANCO..." || imp <= 0) {
                return Swal.fire('Error', 'Debe completar el número, seleccionar un banco válido e ingresar el importe.', 'error');
            }

            chequesCargados.push({
                numero: num,
                banco_id: parseInt(bancoId), // Aseguramos que sea entero
                banco_nom: bancoNom,
                importe: imp,
                fecha_emision: emi,
                vencimiento: vto,
                tipo: tipo,
                origen: origen,
                emisor: emisor,
                nala: nala,
                target: targetMp
            });

            actualizarTablaCheques();
            limpiarCamposCheque();
        }

        function actualizarTablaCheques() {
            const tbody = $('#tbody-cheques-modal').empty();
            let totalModal = 0;
            let totalTarget = 0;

            chequesCargados.forEach((c, idx) => {
                totalModal += c.importe;
                if (c.target === targetMp) totalTarget += c.importe;

                tbody.append(`
                <tr>
                    <td>${c.numero}</td>
                    <td>${c.banco_nom}</td>
                    <td class="text-end">$ ${c.importe.toFixed(2)}</td>
                    <td>${c.vencimiento}</td>
                    <td class="text-center">
                        <button class="btn btn-link text-danger p-0" onclick="window.delChq(${idx})"><i class="fas fa-times"></i></button>
                    </td>
                </tr>
            `);
            });

            $('#lbl-total-cheques-modal').text('$ ' + totalModal.toFixed(2));
            $(`#imp-${targetMp}`).val(totalTarget.toFixed(2));
            calcularTotales();
        }

        window.delChq = (idx) => {
            chequesCargados.splice(idx, 1);
            actualizarTablaCheques();
        };

        function limpiarCamposCheque() {
            $('#chq-num, #chq-imp, #chq-emisor').val('');
            $('#chq-f-emi, #chq-f-vto').val('<?= date('Y-m-d') ?>');
            $('#chq-nala').prop('checked', false);
            $('#chq-banco').val('').trigger('change');
        }

        /**
         * Limpieza profunda del formulario para arquitectura SPA
         */
        function limpiarTodoElFormulario() {
            // Resetear estados
            facturasPendientes = [];
            chequesCargados = [];

            // Limpiar DOM visualmente
            $('#tbody-facturas').empty().append('<tr><td colspan="7" class="text-center p-5 text-muted">Seleccione un cliente para ver deudas</td></tr>');
            $('#tbody-cheques-modal').empty();
            $('#lbl-total-seleccionado').text('$ 0.00');
            $('#lbl-total-cobro').text('$ 0.00');
            $('#lbl-total-chq-modal').text('$ 0.00');
            $('#txt-saldo-cc').val('$ 0.00');

            // Inputs
            $('#ret-gan, #ret-iibb, #ret-iva, #imp-1, #imp-2').val(0);
            $('#txt-fecha').val('<?= date('Y-m-d') ?>');
            $('#imp-1, #imp-2').prop('readonly', false);
            $('#btn-chq-1, #btn-chq-2').addClass('d-none');

            // Selectores
            $('#cmb-cliente').val(null).trigger('change');
            $('#mp-1, #mp-2').val('').trigger('change');
            $('#status-fiscal').addClass('d-none');
            $('#chk-es-fiscal').prop('checked', false);

            // Actualizar número de recibo
            fetch('api_cobros.php?action=get_next_id', { headers: commonHeaders })
                .then(r => r.json())
                .then(res => { if (res.success) $('#lbl-num-recibo').text(res.next_id); });

            $('#btn-confirmar').prop('disabled', true);
        }

        function datosTarjeta(n) {
            if ($(`#tj-box-${n}`).hasClass('d-none')) return null;
            return { id: parseInt($(`#tj-id-${n}`).val()) || 0, cuotas: parseInt($(`#tj-cuotas-${n}`).val()) || 1, cupon: $(`#tj-cupon-${n}`).val().trim() };
        }

        async function grabarCobro() {
            for (const n of [1, 2]) {
                const t = datosTarjeta(n);
                if (t && !t.id) return Swal.fire('Atención', `Elija la tarjeta del medio de cobro ${n === 1 ? 'principal' : 'secundario'}.`, 'warning');
            }
            const data = {
                cliente_id: $('#cmb-cliente').val(),
                nombre_cliente: $('#cmb-cliente').select2('data')[0].razon,
                fecha: $('#txt-fecha').val(),
                es_fiscal: $('.chk-factura:checked').length > 0 ? facturasPendientes[$('.chk-factura:checked').first().data('idx')].es_fiscal : ($('#chk-es-fiscal').is(':checked') ? 1 : 0),
                medio1: { id: $('#mp-1').val(), importe: parseFloat($('#imp-1').val()) || 0, tarjeta: datosTarjeta(1) },
                medio2: { id: $('#mp-2').val(), importe: parseFloat($('#imp-2').val()) || 0, tarjeta: datosTarjeta(2) },
                retenciones: {
                    ganancias: parseFloat($('#ret-gan').val()) || 0,
                    iibb: parseFloat($('#ret-iibb').val()) || 0,
                    iva: parseFloat($('#ret-iva').val()) || 0
                },
                cheques: chequesCargados,
                facturas_imputar: $('.chk-factura:checked').map(function () {
                    return facturasPendientes[$(this).data('idx')];
                }).get()
            };

            if (await Swal.fire({ title: '¿Confirmar?', text: 'Se registrará el cobro.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
                try {
                    const resp = await fetch('api_cobros.php?action=save_cobro', {
                        method: 'POST',
                        headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                        body: JSON.stringify(data)
                    }).then(r => r.json());

                    if (resp.success) {
                        await Swal.fire('Éxito', 'Cobro registrado correctamente. Nº Recibo: ' + resp.id_pago, 'success');
                        limpiarTodoElFormulario(); // USAR LIMPIEZA LOCAL EN LUGAR DE RECARGAR MÓDULO
                    } else {
                        Swal.fire('Error', resp.error, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Error de conexión.', 'error');
                }
            }
        }

        init();
    })();
</script>
