<?php
/**
 * proveedores_facturas_add.php - CARGA DE COMPROBANTES DE COMPRA
 * Actualizado: Reforzado trigger de imputaciones al cambiar proveedor.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">CARGA DE FACTURA DE COMPRA</h4>
            <small class="text-accent opacity-75">Registro técnico de egresos, impuestos e imputaciones</small>
        </div>
        <div class="bg-dark p-2 px-3 rounded border border-info shadow-sm text-center">
            <small class="text-muted d-block" style="font-size: 10px;">ESTADO CONTABLE</small>
            <span class="text-info fw-bold" id="lbl-status">LISTO PARA CARGA</span>
        </div>
    </div>

    <div class="row g-3">
        <!-- SECTOR 1: CABECERA -->
        <div class="col-md-9">
            <div class="card card-filtros mb-3 shadow-sm">
                <div class="card-body p-3">
                    <div class="row g-3 text-start">
                        <div class="col-md-5">
                            <label class="form-label-custom">PROVEEDOR</label>
                            <select id="cmb-proveedor" class="form-control"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">TIPO COMPROBANTE</label>
                            <select id="cmb-tipo" class="form-select">
                                <option value="1">Factura A</option>
                                <option value="6">Factura B</option>
                                <option value="11">Factura C</option>
                                <option value="2">Nota de Débito A</option>
                                <option value="7">Nota de Débito B</option>
                                <option value="12">Nota de Débito C</option>
                                <option value="3">Nota de Crédito A</option>
                                <option value="8">Nota de Crédito B</option>
                                <option value="13">Nota de Crédito C</option>
                                <option value="1000">Remito</option>
                                <option value="100">Ticket NF</option>
                                <option value="101">Ticket de Crédito NF</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">P. VENTA</label>
                            <input type="text" id="txt-pv" class="form-control text-center" maxlength="4" placeholder="0000">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label-custom">NÚMERO</label>
                            <input type="text" id="txt-num" class="form-control text-center" maxlength="8" placeholder="00000000">
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label-custom">FECHA COMP.</label>
                            <input type="date" id="f-comp" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom text-warning">FECHA IMPUTACIÓN</label>
                            <input type="date" id="f-imput" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">FECHA VTO.</label>
                            <input type="date" id="f-venci" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="chk-fiscal" checked>
                                <label class="form-check-label text-white small" for="chk-fiscal" style="cursor:pointer">FISCAL (IVA)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECTOR 2: CONCEPTOS -->
            <div class="card card-filtros p-0 mb-3 shadow-sm border-secondary-subtle overflow-hidden">
                <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="text-white small fw-bold"><i class="fas fa-list me-2 text-accent"></i>CONCEPTOS CARGADOS</span>
                    <div class="d-flex gap-2 align-items-center">
                        <select id="cmb-add-concepto" class="form-select form-select-sm" style="width: 250px;"></select>
                        <button class="btn btn-sm btn-info" id="btn-add-linea"><i class="fas fa-plus"></i></button>
                    </div>
                </div>
                <div class="table-responsive" style="min-height: 200px;">
                    <table class="table table-dark table-hover mb-0 small" id="tabla-lineas">
                        <thead>
                            <tr>
                                <th>CONCEPTO</th>
                                <th style="width: 150px;" class="text-end">IMPORTE</th>
                                <th style="width: 120px;" class="text-center">ALÍCUOTA</th>
                                <th style="width: 80px;" class="text-center">ACC</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-lineas"></tbody>
                    </table>
                </div>
            </div>

            <!-- SECTOR 3: IMPUTACIONES (SOLO NC) -->
            <div class="card card-filtros p-0 shadow-sm d-none" id="card-imputaciones">
                <div class="card-header bg-dark border-secondary p-2 px-3 text-warning small fw-bold">
                    <i class="fas fa-link me-2"></i>IMPUTAR NOTA DE CRÉDITO A FACTURAS PENDIENTES
                </div>
                <div class="table-responsive" style="max-height: 200px;">
                    <table class="table table-dark table-sm mb-0">
                        <thead><tr><th class="text-center">#</th><th>FECHA</th><th>NÚMERO</th><th class="text-end">PENDIENTE</th></tr></thead>
                        <tbody id="tbody-pendientes"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SECTOR 4: TOTALES -->
        <div class="col-md-3">
            <div class="card card-filtros shadow-sm h-100">
                <div class="card-body p-3">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-3 small fw-bold">TOTALIZADOR</h6>
                    
                    <div class="mb-2">
                        <label class="form-label-custom small">NETO GRAVADO</label>
                        <input type="text" id="t-neto" class="form-control form-control-sm text-end fw-bold" readonly value="0.00">
                    </div>

                    <div class="row g-1 mb-2">
                        <div class="col-4"><label class="form-label-custom small" style="font-size:9px">IVA 10.5</label><input type="text" id="t-iva10" class="form-control form-control-sm text-end" readonly value="0.00"></div>
                        <div class="col-4"><label class="form-label-custom small" style="font-size:9px">IVA 21</label><input type="text" id="t-iva21" class="form-control form-control-sm text-end" readonly value="0.00"></div>
                        <div class="col-4"><label class="form-label-custom small" style="font-size:9px">IVA 27</label><input type="text" id="t-iva27" class="form-control form-control-sm text-end" readonly value="0.00"></div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label-custom small">IMPUESTOS INTERNOS</label>
                        <input type="number" id="t-impint" class="form-control form-control-sm text-end calc-trigger" step="0.01" value="0.00">
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label-custom small">PERC. IIBB</label>
                            <input type="number" id="t-perciibb" class="form-control form-control-sm text-end calc-trigger" step="0.01" value="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label-custom small">PERC. IVA</label>
                            <input type="number" id="t-perciva" class="form-control form-control-sm text-end calc-trigger" step="0.01" value="0.00">
                        </div>
                    </div>

                    <div class="mt-4 bg-dark p-3 rounded border border-accent text-center shadow">
                        <div class="text-accent small mb-1 fw-bold">TOTAL COMPROBANTE</div>
                        <div class="fs-3 fw-bold text-white" id="lbl-total">$ 0.00</div>
                    </div>

                    <button class="btn btn-info w-100 fw-bold py-3 mt-4 rounded-pill shadow" id="btn-grabar" disabled>
                        <i class="fas fa-save me-2"></i>GRABAR COMPROBANTE
                    </button>
                    
                    <div class="mt-3 text-start">
                        <label class="form-label-custom small">NOTAS</label>
                        <textarea id="txt-notas" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let lineas = [];
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        configurarSelect2();
        cargarConceptos();
        aplicarFiscalPorDefecto();
        $('.calc-trigger').on('input', calcularTotales);
        $('#btn-add-linea').click(agregarLinea);
        $('#btn-grabar').click(grabar);
        $('#f-comp').on('change input', function() {
            if (this.value) $('#f-imput').val(this.value);
        });
        
        // Listeners para refrescar facturas cuando sea NC o cambie fiscalidad
        $('#cmb-tipo').on('change', function() {
            aplicarFiscalPorDefecto();
            toggleImputaciones();
        });
        $('#chk-fiscal').on('change', toggleImputaciones);
    }

    // Facturas y Notas de Débito/Crédito (A/B/C) son fiscales por defecto.
    // Tickets NF y Tickets de Crédito NF no son fiscales. Remitos son fiscales
    // por defecto. En todos los casos el usuario puede cambiarlo manualmente.
    function aplicarFiscalPorDefecto() {
        const tipo = parseInt($('#cmb-tipo').val());
        const TICKETS_NF = [100, 101];
        $('#chk-fiscal').prop('checked', !TICKETS_NF.includes(tipo));
    }

    function limpiarFormFactCompra() {
        lineas = [];
        $('#txt-pv, #txt-num, #txt-notas').val('');
        $('.calc-trigger').val('0.00');
        $('#t-neto, #t-iva10, #t-iva21, #t-iva27').val('0.00');
        $('#cmb-proveedor').val(null).trigger('change');
        $('#cmb-tipo').val('1');
        $('#cmb-add-concepto').val(null).trigger('change');
        const hoy = new Date().toISOString().split('T')[0];
        $('#f-comp, #f-imput, #f-venci').val(hoy);
        aplicarFiscalPorDefecto();
        $('#tbody-lineas, #tbody-pendientes').empty();
        $('#card-imputaciones').addClass('d-none');
        $('#lbl-total').text('$ 0.00');
        $('#btn-grabar').prop('disabled', true);
    }

    function configurarSelect2() {
        $('#cmb-add-concepto').select2({
            placeholder: 'BUSCAR CONCEPTO...',
            dropdownParent: $('#main-content'),
            width: '250px',
            allowClear: true
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
        }).on('select2:select', toggleImputaciones);
    }

    async function cargarConceptos() {
        const res = await fetch('api_conceptos_compra.php?action=list&solo_habilitados=1', { headers: commonHeaders }).then(r => r.json());
        if (res.success) {
            $('#cmb-add-concepto').empty().append(new Option('', ''));
            res.data.forEach(c => $('#cmb-add-concepto').append(new Option(c.Concepto, c.ID)));
            $('#cmb-add-concepto').trigger('change');
        }
    }

    function agregarLinea() {
        const id = $('#cmb-add-concepto').val();
        const nom = $('#cmb-add-concepto option:selected').text();
        if (!id) return;
        if (lineas.find(l => l.idconcepto == id)) return Swal.fire('Atención', 'Este concepto ya fue incluido.', 'warning');
        lineas.push({ idconcepto: id, nombre: nom, importe: 0, alicuota: 21 });
        renderLineas();
    }

    function renderLineas() {
        const tbody = $('#tbody-lineas').empty();
        lineas.forEach((l, i) => {
            tbody.append(`
                <tr>
                    <td class="text-white">${l.nombre}</td>
                    <td><input type="number" class="form-control form-control-sm text-end row-imp" data-idx="${i}" value="${l.importe}" step="0.01"></td>
                    <td>
                        <select class="form-select form-select-sm row-ali" data-idx="${i}">
                            <option value="0" ${l.alicuota == 0 ? 'selected' : ''}>0%</option>
                            <option value="10.5" ${l.alicuota == 10.5 ? 'selected' : ''}>10.5%</option>
                            <option value="21" ${l.alicuota == 21 ? 'selected' : ''}>21%</option>
                            <option value="27" ${l.alicuota == 27 ? 'selected' : ''}>27%</option>
                        </select>
                    </td>
                    <td class="text-center"><button class="btn btn-link text-danger p-0" onclick="window.delLinea(${i})"><i class="fas fa-trash-alt"></i></button></td>
                </tr>
            `);
        });
        $('.row-imp').on('input', function() { lineas[$(this).data('idx')].importe = parseFloat($(this).val()) || 0; calcularTotales(); });
        $('.row-ali').on('change', function() { lineas[$(this).data('idx')].alicuota = parseFloat($(this).val()); calcularTotales(); });
        calcularTotales();
    }

    window.delLinea = (i) => { lineas.splice(i, 1); renderLineas(); };

    function calcularTotales() {
        let neto = 0, iva105 = 0, iva21 = 0, iva27 = 0;
        lineas.forEach(l => {
            neto += l.importe;
            if (l.alicuota == 10.5) iva105 += l.importe * 0.105;
            if (l.alicuota == 21) iva21 += l.importe * 0.21;
            if (l.alicuota == 27) iva27 += l.importe * 0.27;
        });
        const impint = parseFloat($('#t-impint').val()) || 0;
        const perciibb = parseFloat($('#t-perciibb').val()) || 0;
        const perciva = parseFloat($('#t-perciva').val()) || 0;
        $('#t-neto').val(neto.toFixed(2));
        $('#t-iva10').val(iva105.toFixed(2));
        $('#t-iva21').val(iva21.toFixed(2));
        $('#t-iva27').val(iva27.toFixed(2));
        const total = neto + iva105 + iva21 + iva27 + impint + perciibb + perciva;
        $('#lbl-total').text('$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#btn-grabar').prop('disabled', total <= 0 || !$('#cmb-proveedor').val() || !$('#txt-num').val());
    }

    async function toggleImputaciones() {
        const tipo = parseInt($('#cmb-tipo').val());
        const esNC = [3, 8, 13, 101].includes(tipo);
        const provId = $('#cmb-proveedor').val();

        if (esNC && provId) {
            $('#card-imputaciones').removeClass('d-none');
            try {
                // Reforzamos la llamada a la API enviando el ID de proveedor siempre
                const res = await fetch(`api_proveedores_facturas.php?action=get_pendientes_imputar&proveedor=${provId}&fiscal=${$('#chk-fiscal').is(':checked')}`, { headers: commonHeaders }).then(r => r.json());
                const tbody = $('#tbody-pendientes').empty();
                if (res.success && res.data.length > 0) {
                    res.data.forEach((f, i) => {
                        tbody.append(`<tr>
                            <td class="text-center"><input type="checkbox" class="form-check-input chk-imp" data-id="${f.id}" data-pend="${f.pendiente}"></td>
                            <td>${f.fecha_f}</td><td>${f.num_factura}</td><td class="text-end fw-bold text-info">$ ${parseFloat(f.pendiente).toFixed(2)}</td>
                        </tr>`);
                    } );
                } else {
                    tbody.append('<tr><td colspan="4" class="text-center p-4 text-muted small">Sin facturas pendientes para este proveedor y fiscalidad.</td></tr>');
                }
            } catch (e) { console.error("Error al cargar pendientes de imputación:", e); }
        } else {
            $('#card-imputaciones').addClass('d-none');
        }
        calcularTotales();
    }

    async function grabar() {
        const totalRaw = $('#lbl-total').text().replace('$ ', '').replace(/\./g, '').replace(',', '.');
        const total = parseFloat(totalRaw);
        
        const data = {
            proveedorid: $('#cmb-proveedor').val(),
            nombre_proveedor: $('#cmb-proveedor').select2('data')[0].text,
            tipocomp: $('#cmb-tipo').val(),
            tipo_label: $('#cmb-tipo option:selected').text(),
            pv: $('#txt-pv').val(),
            numfact: $('#txt-num').val(),
            fecha: $('#f-comp').val(),
            fechaimputacion: $('#f-imput').val(),
            fechavencimiento: $('#f-venci').val(),
            fiscal: $('#chk-fiscal').is(':checked'),
            notas: $('#txt-notas').val(),
            neto: parseFloat($('#t-neto').val()),
            iva105: parseFloat($('#t-iva10').val()),
            iva21: parseFloat($('#t-iva21').val()),
            iva27: parseFloat($('#t-iva27').val()),
            impint: parseFloat($('#t-impint').val()),
            retiibb: parseFloat($('#t-perciibb').val()),
            retiva: parseFloat($('#t-perciva').val()),
            subtotal: parseFloat($('#t-neto').val()) + parseFloat($('#t-iva10').val()) + parseFloat($('#t-iva21').val()) + parseFloat($('#t-iva27').val()) + parseFloat($('#t-impint').val()),
            total: total,
            conceptos: lineas,
            imputaciones: $('.chk-imp:checked').map(function() { return { id: $(this).data('id'), pendiente: $(this).data('pend') }; }).get()
        };

        if (await Swal.fire({ title: '¿Grabar Comprobante?', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_proveedores_facturas.php?action=save', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', 'Comprobante y movimientos registrados.', 'success');
                limpiarFormFactCompra();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    init();
})();
</script>
