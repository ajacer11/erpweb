<?php
/**
 * stock_ingreso.php - INGRESO DE MERCADERÍA / COMPRAS
 * Actualizado: Inicialización Select2 Proveedor, UI mejorada y selección obligatoria.
 */

require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">INGRESO DE STOCK / COMPRAS</h4>
            <small class="text-accent opacity-75">Gestión de ingresos y actualización de costos</small>
        </div>

        <!-- UI MEJORADA: MOVIMIENTO INTERNO (Desactivado por defecto) -->
        <div class="d-flex align-items-stretch" style="min-width: 320px;">
            <div class="form-check form-switch  p-2 px-4  d-flex align-items-center w-100 shadow-sm"
                style="cursor: pointer;">
                <input class="form-check-input me-3" type="checkbox" id="chk-interna"
                    style="width: 2.8em; height: 1.4em; cursor: pointer;">
                <label class="form-check-label text-white fw-bold small mb-0" for="chk-interna"
                    style="cursor: pointer; user-select: none;">MOVIMIENTO INTERNO</label>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- CABECERA DEL COMPROBANTE -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm" id="panel-cabecera">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3 section-externa">
                            <label class="form-label-custom">PROVEEDOR</label>
                            <select class="form-select" id="cmb-proveedor">
                                <option value="">SELECCIONE UN PROVEEDOR...</option>
                            </select>
                            <small class="text-info d-none mt-1 d-block" id="lbl-oc-activa">
                                <i class="fas fa-clipboard-check me-1"></i>Cargado desde OC #<b id="lbl-oc-activa-num"></b>
                            </small>
                        </div>
                        <div class="col-md-1 section-externa">
                            <label class="form-label-custom d-block">&nbsp;</label>
                            <button class="btn btn-outline-info w-100" type="button" id="btn-cargar-oc" disabled title="Cargar desde Orden de Compra pendiente">
                                <i class="fas fa-clipboard-list"></i>
                            </button>
                        </div>
                        <div class="col-md-2 section-externa">
                            <label class="form-label-custom">TIPO COMPROBANTE</label>
                            <select class="form-select" id="cmb-tipo-comp">
                                <option value="" selected disabled>-- SELECCIONE --</option>
                            </select>
                        </div>
                        <div class="col-md-1 section-externa">
                            <label class="form-label-custom">PDV</label>
                            <input type="number" id="txt-pdv" class="form-control" value="1">
                        </div>
                        <div class="col-md-2 section-externa">
                            <label class="form-label-custom">NÚMERO</label>
                            <input type="number" id="txt-num-comp" class="form-control" placeholder="00000000">
                        </div>
                        <div class="col-md-2 section-externa">
                            <label class="form-label-custom">FECHA</label>
                            <input type="date" id="txt-fecha-comp" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom">FISCAL</label>
                            <select class="form-select" id="cmb-fiscal">
                                <option value="1">SÍ</option>
                                <option value="0">NO</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-none" id="wrap-cmb-deposito">
                            <label class="form-label-custom">DEPÓSITO</label>
                            <select class="form-select" id="cmb-deposito"></select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- BUSCADOR DE PRODUCTOS -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm border-accent-soft">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2">
                            <div class="d-flex justify-content-between mb-1">
                                <label class="form-label-custom mb-0">CÓDIGO</label>
                                <div class="form-check form-switch small">
                                    <input class="form-check-input" type="checkbox" id="chk-search-mode">
                                    <label class="form-check-label text-accent" for="chk-search-mode"
                                        style="font-size: 9px;">ES COD. PROV.</label>
                                </div>
                            </div>
                            <input type="text" id="txt-cod" class="form-control" placeholder="ESCANEAR O TIPEAR">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">BÚSQUEDA POR DESCRIPCIÓN</label>
                            <select id="select-prod-search" class="form-control"></select>
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom">CANTIDAD</label>
                            <input type="number" id="txt-ing" class="form-control text-center fw-bold" step="0.01">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom">NETO UN.</label>
                            <input type="number" id="txt-costo-neto" class="form-control text-end" step="0.01">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom">IVA UN.</label>
                            <input type="number" id="txt-iva" class="form-control text-end" step="0.01">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom">IMP.INT.</label>
                            <input type="number" id="txt-imp-int" class="form-control text-end" step="0.01">
                        </div>
                        <div class="col-md-1">
                            <label class="form-label-custom text-accent">TOTAL UN.</label>
                            <input type="number" id="txt-costo-total" class="form-control text-end fw-bold" readonly
                                style="color: var(--accent) !important;">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-info w-100 fw-bold" id="btn-agregar" disabled>
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="row g-2 mt-2 align-items-center">
                        <div class="col-md-2">
                            <div class="form-check small text-muted">
                                <input class="form-check-input" type="checkbox" id="chk-bultos">
                                <label class="form-check-label" for="chk-bultos">INGRESAR POR BULTOS</label>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block" style="font-size: 10px;">PRODUCTO: <span
                                    id="lbl-prod-actual" class="text-white">---</span></small>
                            <small class="text-muted d-block" style="font-size: 10px;">STK ACTUAL: <span
                                    id="lbl-stk-actual" class="text-white">0</span></small>
                        </div>
                        <div class="col-md-8">
                            <input type="text" id="txt-nota" class="form-control form-control-sm"
                                placeholder="OBSERVACIONES DE LA LÍNEA...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA -->
        <div class="col-md-9">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm" style="min-height: 400px;">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top">
                            <tr>
                                <th>CÓDIGO</th>
                                <th>DESCRIPCIÓN</th>
                                <th class="text-center">CANT.</th>
                                <th class="text-end">NETO</th>
                                <th class="text-end">IVA</th>
                                <th class="text-end">IMP.INT</th>
                                <th class="text-end">TOTAL</th>
                                <th class="text-center">ACC.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-ingreso"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- TOTALES -->
        <div class="col-md-3">
            <div class="card card-filtros h-100 shadow-sm border-info">
                <div class="card-body d-flex flex-column h-100">
                    <h6 class="text-accent mb-3 border-bottom border-secondary pb-2">RESUMEN</h6>

                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">NETO:</span>
                        <span class="text-white fw-bold" id="total-neto">0.00</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">IVA:</span>
                        <span class="text-white fw-bold" id="total-iva">0.00</span>
                    </div>
                    <div class="mb-3 d-flex justify-content-between border-bottom border-secondary pb-2">
                        <span class="text-muted small">IMP. INT.:</span>
                        <span class="text-white fw-bold" id="total-impint">0.00</span>
                    </div>

                    <div class="mb-2">
                        <label class="form-label-custom small">PERC. IVA</label>
                        <input type="number" id="txt-ret-iva" class="form-control form-control-sm text-end"
                            value="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom small">PERC. IIBB</label>
                        <input type="number" id="txt-ret-iibb" class="form-control form-control-sm text-end"
                            value="0.00">
                    </div>

                    <div class="mt-auto bg-dark p-3 rounded text-center border border-accent">
                        <div class="fs-2 fw-bold text-white" id="total-final">$ 0.00</div>
                        <div class="text-muted x-small mt-1">TOTAL ITEMS: <span id="lbl-cant-items">0</span></div>
                    </div>

                    <button class="btn btn-info w-100 fw-bold py-3 mt-3 rounded-pill shadow" id="btn-guardar" disabled>
                        <i class="fas fa-save me-2"></i>CONFIRMAR INGRESO
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: OCs PENDIENTES DEL PROVEEDOR SELECCIONADO -->
<div class="modal fade" id="modalOcPendientes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg text-start">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white"><i class="fas fa-clipboard-list me-2 text-info"></i>Órdenes de Compra Pendientes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>NRO</th>
                                <th>FECHA</th>
                                <th>USUARIO</th>
                                <th class="text-center">LÍNEAS</th>
                                <th class="text-center">ARTÍCULOS</th>
                                <th class="text-end">MONTO ESTIMADO</th>
                                <th class="text-center">ACC.</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-oc-pendientes">
                            <tr><td colspan="7" class="text-center p-4 text-muted">Cargando...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        let items = [];
        let productoActivo = null;
        let ocIdCargada = null;
        let ocsPendientesCache = [];
        const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

        async function init() {
            await cargarCombos();
            await cargarDeposito();
            configurarSelect2();
            configurarEventos();
            limpiarFormularioCompleto(); // Inicializar estado limpio
            toggleInterna();
            $('#txt-cod').focus();
        }

        // Combo de depósito: solo se muestra si el tenant tiene multidepósito
        // activado. Se precarga con el depósito de trabajo del usuario, pero queda
        // editable por si esta carga puntual va a otro depósito.
        async function cargarDeposito() {
            try {
                const estado = await fetch('api_depositos.php?action=estado_global', { headers: commonHeaders }).then(r => r.json());
                if (!estado.habilitado) return;

                const [permitidos, miEstado] = await Promise.all([
                    fetch('api_depositos.php?action=list_permitidos', { headers: commonHeaders }).then(r => r.json()),
                    fetch('api_depositos.php?action=mi_estado', { headers: commonHeaders }).then(r => r.json())
                ]);

                const cmb = $('#cmb-deposito').empty();
                (permitidos.data || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));
                if (miEstado.id_deposito) cmb.val(miEstado.id_deposito);

                $('#wrap-cmb-deposito').removeClass('d-none');
            } catch (e) { console.error('No se pudo cargar el depósito', e); }
        }

        function configurarSelect2() {
            // SELECT2 PROVEEDOR
            $('#cmb-proveedor').select2({
                placeholder: 'SELECCIONE UN PROVEEDOR...',
                allowClear: true,
                width: '100%'
            });

            // SELECT2 PRODUCTOS (AJAX)
            $('#select-prod-search').select2({
                placeholder: 'BUSCAR POR DESCRIPCIÓN...',
                minimumInputLength: 3,
                ajax: {
                    url: 'api_productos.php?action=search',
                    dataType: 'json',
                    delay: 250,
                    headers: commonHeaders,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: data.data.map(p => ({ id: p.codigo, text: `${p.codigo} - ${p.descripcion}`, raw: p }))
                    })
                }
            }).on('select2:select', function (e) {
                const data = e.params.data.raw;
                $('#txt-cod').val(data.codigo);
                procesarDatosProducto(data);
                $(this).val(null).trigger('change');
            });
        }

        async function cargarCombos() {
            const [resP, resC] = await Promise.all([
                fetch('api_helpers.php?action=get_proveedores', { headers: commonHeaders }).then(r => r.json()),
                fetch('api_helpers.php?action=get_tipos_comprobante&tipo=compra', { headers: commonHeaders }).then(r => r.json())
            ]);

            if (resP.success) {
                resP.data.forEach(p => $('#cmb-proveedor').append(new Option(p.razon, p.id)));
                $('#cmb-proveedor').val('').trigger('change');
            }
            if (resC.success) {
                resC.data.forEach(c => {
                    $('#cmb-tipo-comp').append(`<option value="${c.numero}" data-fiscal="${c.fiscal}">${c.numero} - ${c.leyenda}</option>`);
                });
                $('#cmb-tipo-comp').val(''); // Forzar vacío
            }
        }

        function configurarEventos() {
            $('#chk-interna').change(toggleInterna);

            $('#cmb-proveedor').on('change', function () {
                $('#btn-cargar-oc').prop('disabled', !$(this).val());
            });
            $('#btn-cargar-oc').click(abrirModalOcPendientes);

            $('#cmb-tipo-comp').on('change', function () {
                const val = $(this).val();
                // Si es tipo 100, no es fiscal
                $('#cmb-fiscal').val(val == "100" ? 0 : 1);
                validarEstadoGuardar();
            });

            $('#txt-cod').on('keydown', e => {
                if (e.key === 'Enter') buscarProducto($(e.target).val());
            });

            $('#txt-costo-neto, #txt-iva, #txt-imp-int, #txt-ing').on('input', calcularCostoLinea);
            $('#btn-agregar').click(agregarLinea);
            $('#txt-ret-iva, #txt-ret-iibb').on('input', calcularTotales);
            $('#btn-guardar').click(guardarIngreso);

            $('#txt-ing, #txt-costo-neto, #txt-iva, #txt-imp-int').on('keydown', e => {
                if (e.key === 'Enter') {
                    if (e.target.id === 'txt-imp-int') { if (!$('#btn-agregar').prop('disabled')) agregarLinea(); }
                    else { $(e.target).closest('.col-md-1').next().find('input').focus(); }
                }
            });
        }

        function toggleInterna() {
            const isInterna = $('#chk-interna').is(':checked');
            $('.section-externa').toggleClass('opacity-50', isInterna).find('input, select').prop('disabled', isInterna);

            if (isInterna) {
                $('#cmb-fiscal').val(0).prop('disabled', true);
                $('#cmb-tipo-comp').val('').trigger('change');
                $('#cmb-proveedor').val('').trigger('change');
            } else {
                $('#cmb-fiscal').prop('disabled', false);
            }
            validarEstadoGuardar();
        }

        /**
         * CARGA DESDE ORDEN DE COMPRA PENDIENTE
         */
        async function abrirModalOcPendientes() {
            const idProv = $('#cmb-proveedor').val();
            if (!idProv) return;

            const tbody = $('#tbody-oc-pendientes').html('<tr><td colspan="7" class="text-center p-4 text-muted"><div class="spinner-border spinner-border-sm text-info me-2"></div>Cargando...</td></tr>');
            $('#modalOcPendientes').modal('show');

            try {
                const res = await fetch(`api_proveedores_oc.php?action=pendientes_proveedor&proveedor=${idProv}`, { headers: commonHeaders }).then(r => r.json());
                ocsPendientesCache = res.success ? res.data : [];

                if (!ocsPendientesCache.length) {
                    tbody.html('<tr><td colspan="7" class="text-center p-4 text-muted">Este proveedor no tiene órdenes de compra pendientes.</td></tr>');
                    return;
                }

                tbody.empty();
                ocsPendientesCache.forEach(oc => {
                    tbody.append(`
                        <tr>
                            <td class="fw-bold text-accent">${oc.id}</td>
                            <td>${oc.fecha}</td>
                            <td>${oc.usuario || '-'}</td>
                            <td class="text-center">${oc.cant_lineas}</td>
                            <td class="text-center">${parseFloat(oc.cant_articulos).toFixed(0)}</td>
                            <td class="text-end fw-bold text-white">$ ${parseFloat(oc.total).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                            <td class="text-center">
                                <button class="btn btn-info btn-sm fw-bold" onclick="window.usarOcPendiente(${oc.id})">
                                    <i class="fas fa-check me-1"></i>USAR
                                </button>
                            </td>
                        </tr>
                    `);
                });
            } catch (e) {
                console.error(e);
                tbody.html('<tr><td colspan="7" class="text-center p-4 text-danger">No se pudieron cargar las OCs pendientes.</td></tr>');
            }
        }

        window.usarOcPendiente = async function (idOc) {
            if (items.length > 0) {
                const confirmar = await Swal.fire({
                    title: '¿Reemplazar renglones actuales?',
                    text: 'Ya hay productos cargados en la grilla. Se van a reemplazar por los de la Orden de Compra elegida.',
                    icon: 'warning', showCancelButton: true,
                    confirmButtonText: 'SÍ, REEMPLAZAR', cancelButtonText: 'CANCELAR'
                }).then(r => r.isConfirmed);
                if (!confirmar) return;
            }

            $('#modalOcPendientes').modal('hide');
            Swal.fire({ title: 'Cargando pedido...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

            try {
                const res = await fetch(`api_proveedores_oc.php?action=get_order&id=${idOc}`, { headers: commonHeaders }).then(r => r.json());
                if (!res.success) { Swal.fire('Error', res.error, 'error'); return; }

                // Se trae el costo ACTUAL del producto (neto/iva/imp.int desglosados,
                // tal como los maneja este formulario), no el costo estimado que había
                // en la OC — así el usuario ajusta sobre datos reales y consistentes con
                // el resto de la pantalla. La cantidad sí viene de la OC.
                const nuevosItems = await Promise.all(res.items.map(async (it) => {
                    try {
                        const rProd = await fetch(`api_stock_ajuste.php?action=get_prod&code=${encodeURIComponent(it.codigo)}`, { headers: commonHeaders }).then(r => r.json());
                        if (rProd.success) {
                            const neto = parseFloat(rProd.data.sinivacosto || 0);
                            const iva = parseFloat(rProd.data.ivacosto || 0);
                            const impInt = parseFloat(rProd.data.impint || 0);
                            return {
                                codigo: rProd.data.codigo,
                                descripcion: rProd.data.descripcion,
                                cantidad: parseFloat(it.cantidad) || 0,
                                neto, iva, imp_int: impInt,
                                total_un: neto + iva + impInt,
                                rubro: rProd.data.rubro,
                                nota: `OC #${idOc}`
                            };
                        }
                    } catch (e) { console.error(e); }
                    // Fallback: el producto no se pudo resolver (fue dado de baja, etc.)
                    // Se conserva el costo que tenía la OC como neto, sin desglose.
                    return {
                        codigo: it.codigo, descripcion: it.descripcion, cantidad: parseFloat(it.cantidad) || 0,
                        neto: parseFloat(it.costo) || 0, iva: 0, imp_int: 0,
                        total_un: parseFloat(it.costo) || 0, rubro: '', nota: `OC #${idOc} (producto no encontrado, revisar costo)`
                    };
                }));

                items = nuevosItems;
                ocIdCargada = idOc;
                $('#lbl-oc-activa-num').text(idOc);
                $('#lbl-oc-activa').removeClass('d-none');

                renderTabla();
                Swal.close();
                Swal.fire({ icon: 'success', title: `OC #${idOc} cargada`, toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudo cargar el pedido.', 'error');
            }
        };

        async function buscarProducto(codigo) {
            if (!codigo) return;
            try {
                const isProvMode = $('#chk-search-mode').is(':checked') ? 1 : 0;
                const resRef = await fetch(`api_referencias.php?action=resolve&code=${encodeURIComponent(codigo)}&search_prov=${isProvMode}`, { headers: commonHeaders }).then(r => r.json());

                if (!resRef.success) return Swal.fire('Error', resRef.error, 'error');
                const real = resRef.data.codigo_real;

                const resProd = await fetch(`api_stock_ajuste.php?action=get_prod&code=${encodeURIComponent(real)}`, { headers: commonHeaders }).then(r => r.json());
                if (resProd.success) {
                    procesarDatosProducto(resProd.data);
                } else {
                    Swal.fire('Error', 'Producto no encontrado', 'error');
                    limpiarFichaProd();
                }
            } catch (e) { console.error(e); }
        }

        function procesarDatosProducto(data) {
            productoActivo = data;
            $('#lbl-prod-actual').text(data.descripcion);
            $('#lbl-stk-actual').text(parseFloat(data.stock).toFixed(2));
            $('#txt-costo-neto').val(parseFloat(data.sinivacosto || 0).toFixed(2));
            $('#txt-iva').val(parseFloat(data.ivacosto || 0).toFixed(2));
            // api_stock_ajuste.php?action=get_prod devuelve "impint" (alias explicito en
            // minuscula), pero api_productos.php?action=search hace SELECT p.* y la columna
            // real es "ImpInt" (mayusculas) — sin el fallback, ese segundo camino (buscar por
            // descripcion) precargaba siempre 0.00 y pisaba el imp. interno real del producto
            // al guardar el ingreso.
            $('#txt-imp-int').val(parseFloat(data.ImpInt ?? data.impint ?? 0).toFixed(2));
            $('#txt-ing').val(1).focus().select();
            calcularCostoLinea();
            $('#btn-agregar').prop('disabled', false);
        }

        function calcularCostoLinea() {
            const neto = parseFloat($('#txt-costo-neto').val()) || 0;
            const iva = parseFloat($('#txt-iva').val()) || 0;
            const imp = parseFloat($('#txt-imp-int').val()) || 0;
            $('#txt-costo-total').val((neto + iva + imp).toFixed(2));

            const stkAct = parseFloat($('#lbl-stk-actual').text()) || 0;
            const ing = parseFloat($('#txt-ing').val()) || 0;
            const bultos = $('#chk-bultos').is(':checked');
            const uxb = parseFloat(productoActivo?.uniporbulto || 1) || 1;
            const cantFinal = bultos ? (ing * uxb) : ing;
            $('#lbl-stk-final').text((stkAct + cantFinal).toFixed(2));
        }
        /**
         * LIMPIEZA PROFUNDA DEL FORMULARIO (Sustituye loadModule para evitar bloqueo SPA)
         */
        function limpiarFormularioCompleto() {
            items = [];
            productoActivo = null;
            ocIdCargada = null;
            $('#lbl-oc-activa').addClass('d-none');
            $('#btn-cargar-oc').prop('disabled', true);

            // Cabecera
            $('#chk-interna').prop('checked', false); // Por defecto NO es interno
            $('#cmb-proveedor, #cmb-tipo-comp').val('').trigger('change');
            $('#txt-pdv').val('1');
            $('#txt-num-comp').val('');
            $('#txt-fecha-comp').val(new Date().toISOString().split('T')[0]);
            $('#cmb-fiscal').val('1');

            // Buscador
            limpiarFichaProd();
            $('#chk-search-mode').prop('checked', false);
            $('#select-prod-search').val(null).trigger('change');

            // Tabla y Totales
            renderTabla();
            $('#txt-ret-iva, #txt-ret-iibb').val('0.00');
            calcularTotales();

            // UI
            toggleInterna();
            $('#txt-cod').focus();
        }

        function agregarLinea() {
            if (!productoActivo) return;
            const ingRaw = parseFloat($('#txt-ing').val()) || 0;
            if (ingRaw <= 0) return;

            const uxb = parseFloat(productoActivo.uniporbulto || 1) || 1;
            const cantidad = $('#chk-bultos').is(':checked') ? (ingRaw * uxb) : ingRaw;

            items.push({
                codigo: productoActivo.codigo,
                descripcion: productoActivo.descripcion,
                cantidad: cantidad,
                neto: parseFloat($('#txt-costo-neto').val()) || 0,
                iva: parseFloat($('#txt-iva').val()) || 0,
                imp_int: parseFloat($('#txt-imp-int').val()) || 0,
                total_un: parseFloat($('#txt-costo-total').val()) || 0,
                rubro: productoActivo.rubro,
                nota: $('#txt-nota').val()
            });

            renderTabla();
            limpiarFichaProd();
            $('#txt-cod').focus();
        }

        function renderTabla() {
            const tbody = $('#tbody-ingreso').empty();
            items.forEach((it, idx) => {
                tbody.append(`
                <tr data-row="${idx}">
                    <td class="fw-bold text-white small">${it.codigo}</td>
                    <td><div class="text-truncate" style="max-width: 220px;"><small>${it.descripcion}</small></div></td>
                    <td><input type="number" class="form-control form-control-sm text-center fw-bold text-accent row-edit" style="min-width:75px" data-idx="${idx}" data-field="cantidad" value="${it.cantidad}" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm text-end row-edit" style="min-width:80px" data-idx="${idx}" data-field="neto" value="${it.neto.toFixed(2)}" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm text-end row-edit" style="min-width:75px" data-idx="${idx}" data-field="iva" value="${it.iva.toFixed(2)}" step="0.01"></td>
                    <td><input type="number" class="form-control form-control-sm text-end row-edit" style="min-width:75px" data-idx="${idx}" data-field="imp_int" value="${it.imp_int.toFixed(2)}" step="0.01"></td>
                    <td class="text-end fw-bold text-white cell-total">${(it.total_un * it.cantidad).toFixed(2)}</td>
                    <td class="text-center"><button class="btn btn-link text-danger p-0" onclick="window.delIng(${idx})"><i class="fas fa-trash-alt"></i></button></td>
                </tr>
            `);
            });
            calcularTotales();
            validarEstadoGuardar();
        }

        // Delegado (no reconstruye la tabla en cada tecla, así no se pierde el foco
        // del input mientras se edita cantidad/precio de una línea).
        $(document).on('input', '.row-edit', function () {
            const idx = $(this).data('idx');
            const field = $(this).data('field');
            const val = parseFloat($(this).val()) || 0;
            items[idx][field] = val;
            items[idx].total_un = items[idx].neto + items[idx].iva + items[idx].imp_int;

            const totalLinea = items[idx].total_un * items[idx].cantidad;
            $(`#tbody-ingreso tr[data-row="${idx}"] .cell-total`).text(totalLinea.toFixed(2));

            calcularTotales();
            validarEstadoGuardar();
        });

        window.delIng = (idx) => { items.splice(idx, 1); renderTabla(); };

        function calcularTotales() {
            let n = 0, i = 0, imp = 0, q = 0;
            items.forEach(it => { n += it.neto * it.cantidad; i += it.iva * it.cantidad; imp += it.imp_int * it.cantidad; q += it.cantidad; });
            // El Imp. Interno no se sumaba acá (solo neto+iva), asi que el total final
            // quedaba por debajo del total real cuando habia productos con imp. interno.
            const sub = n + i + imp;
            const final = sub + (parseFloat($('#txt-ret-iva').val()) || 0) + (parseFloat($('#txt-ret-iibb').val()) || 0);

            $('#total-neto').text(n.toFixed(2));
            $('#total-iva').text(i.toFixed(2));
            $('#total-impint').text(imp.toFixed(2));
            $('#total-final').text('$ ' + final.toLocaleString('es-AR', { minimumFractionDigits: 2 }));
            $('#lbl-cant-items').text(q.toFixed(0));
        }

        function validarEstadoGuardar() {
            const isInterna = $('#chk-interna').is(':checked');
            const cabOk = isInterna || ($('#cmb-proveedor').val() && $('#txt-num-comp').val() && $('#cmb-tipo-comp').val());
            $('#btn-guardar').prop('disabled', !(items.length > 0 && cabOk));
        }

        function limpiarFichaProd() {
            productoActivo = null;
            $('#txt-cod, #txt-des, #txt-ing, #txt-costo-neto, #txt-iva, #txt-imp-int, #txt-costo-total, #txt-nota').val('');
            $('#lbl-stk-actual').text('0');
            $('#lbl-prod-actual').text('---');
            $('#chk-bultos').prop('checked', false);
            $('#btn-agregar').prop('disabled', true);
        }

        async function guardarIngreso() {
            const isInt = $('#chk-interna').is(':checked');
            const data = {
                interna: isInt,
                fiscal: $('#cmb-fiscal').val(),
                tipo_comp: isInt ? 1000 : $('#cmb-tipo-comp').val(),
                pdv: isInt ? 1 : $('#txt-pdv').val(),
                numero: isInt ? 1 : $('#txt-num-comp').val(),
                id_proveedor: isInt ? 1 : $('#cmb-proveedor').val(),
                nombre_proveedor: isInt ? 'INTERNO' : $('#cmb-proveedor option:selected').text(),
                fecha: isInt ? new Date().toISOString().split('T')[0] : $('#txt-fecha-comp').val(),
                id_deposito: $('#cmb-deposito').val() || null,
                id_oc: isInt ? null : ocIdCargada,
                items: items
            };

            const textoConfirm = data.id_oc
                ? `Se registrará el ingreso y la Orden de Compra #${data.id_oc} quedará marcada como FINALIZADA.`
                : undefined;
            const result = await Swal.fire({ title: '¿Confirmar Ingreso?', text: textoConfirm, icon: 'question', showCancelButton: true });
            if (result.isConfirmed) {
                const res = await fetch('api_stock_ingreso.php', {
                    method: 'POST',
                    headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                }).then(r => r.json());

                if (res.success) {
                    await Swal.fire('Éxito', 'Stock Ingresado Correctamente', 'success');
                    limpiarFormularioCompleto(); // LIMPIEZA MANUAL PROFUNDA
                } else Swal.fire('Error', res.error, 'error');
            }
        }

        init();
    })();
</script>