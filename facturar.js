/**
 * facturar.js - MOTOR DE FACTURACIÓN INTEGRAL (V42)
 * IMPLEMENTACIÓN ESTRICTA DE LÓGICA DE DESCUENTOS VB.NET.
 * BASE: V41 (Fix Vendedor + Redirección Pedidos).
 * ACTUALIZACIÓN V42: Soporte para facturación de PRESUPUESTOS (presupuestoid=x) y redirección (Regla 1).
 * REGLA 10 y 11: Mantenimiento total de la lógica previa sin recortes.
 */

window.bill = (function () {
    let cart = [];
    let curProd = null;
    let maestros = { vendedores: [], medios_pago: [], empresa: null };
    let config = null; // Almacenará la configuración desde la DB
    let clienteSeleccionado = null;
    let associatedDoc = null; // Para asociación de NC/ND (frmcompasoc)
    let globalAdj = { desc: 0, reca: 0 };
    let searchTimer = null;
    let pedidoOrigenId = null; // ID del pedido si viene de administración
    let presupuestoOrigenId = null; // REGLA: ID del presupuesto si viene de administración

    /**
     * FUNCIÓN DE REDONDEO CONTABLE (Regla 1 y 6 Manual)
     * Asegura consistencia con el redondeo de VB.NET a 2 decimales.
     *
     * Number.EPSILON (2.22e-16) solo alcanza para compensar el error de
     * representación de punto flotante en números cercanos a 1 — para importes
     * más grandes (cientos, miles) el error real ronda 1e-14 o más, así que
     * EPSILON no corrige nada y un valor exactamente en el borde de medio
     * centavo (ej. 152.145, guardado como 152.144999999999982) redondea mal,
     * para abajo, mientras PHP round() sí lo detecta y redondea a 152.15. Esto
     * generaba "Error de integridad en totales" al facturar carritos grandes:
     * el navegador y el servidor terminaban en un centavo distinto por línea,
     * multiplicado por la cantidad. Se limpia el ruido de flotante redondeando
     * primero a 6 decimales (de sobra para una cadena de cuentas de 2
     * decimales) antes de redondear al centavo.
     */
    function r(val) {
        const limpio = Math.round(val * 1e6) / 1e6;
        return Math.round((limpio + Number.EPSILON) * 100) / 100;
    }

    /**
     * Redondeo a 3 decimales para el NETO unitario de lista. El neto se guarda y se
     * recalcula con 3 decimales (api_facturar_gets.php, normalizarListasParaVenta):
     * redondearlo a 2 antes de sumarle el IVA hacía que un precio de lista de $4.000
     * se cobrara $4.000,01 (4000/1,21 = 3305,785 -> 3305,79 -> x1,21 = 4000,01).
     */
    function r3(val) {
        const limpio = Math.round(val * 1e7) / 1e7;
        return Math.round((limpio + Number.EPSILON) * 1000) / 1000;
    }

    // Notas de crédito (A/B/C y ticket de crédito): son devoluciones, la
    // mercadería vuelve al stock, así que no se valida disponibilidad.
    function esNotaCredito() {
        return [3, 8, 13, 101].includes(parseInt($('#sel_tipo').val()));
    }

    function esTicketSinIva() {
        const tipoComp = parseInt($('#sel_tipo').val());
        return parseInt(config?.ticketsiniva) === 1 && (tipoComp === 100 || tipoComp === 101);
    }

    /**
     * FUNCIÓN CENTRAL DE CÁLCULO (Regla 6 Manual - Única Fuente de Verdad)
     * Réplica exacta de la cascada VB.NET con protección de negativos y redondeo por unidad.
     */
    function calculateLineData(it) {
        const tktsiniva = esTicketSinIva();
        const effectivePoriva = tktsiniva ? 0 : it.poriva;

        // 1. Base de cálculo original (Neto de lista o manual)
        const netounitario = r3(it.precio_base);
        const impint = r(it.imp_int_unit);

        // 2. Precio Final Unitario ANTES del descuento
        const final_original_unit = r(netounitario * (1 + effectivePoriva / 100) + impint);

        // 3. Monto total a descontar POR UNIDAD en moneda (Redondeado a 2 decimales)
        const total_discount_money_unit = r((it.pordesc * final_original_unit) / 100);

        // 4. LÓGICA DE CASCADA (REGLA: No jugar con negativos)
        const base_neto_iva_unit = r(netounitario * (1 + effectivePoriva / 100));

        let netoUnitEfectivo = 0;
        let ivaUnitEfectivo = 0;
        let impIntEfectivo = impint;

        if (total_discount_money_unit <= base_neto_iva_unit) {

            if (effectivePoriva === 0) {

                // IVA EXENTO / 0%
                netoUnitEfectivo = r(base_neto_iva_unit - total_discount_money_unit);
                ivaUnitEfectivo = 0;

            } else {

                // IVA normal
                const brutoUnit = r(base_neto_iva_unit - total_discount_money_unit);
                netoUnitEfectivo = r(brutoUnit / (1 + effectivePoriva / 100));

                // El IVA es la diferencia: así neto + IVA = precio final exacto (sin centavos de más o de menos).
                ivaUnitEfectivo = r(brutoUnit - netoUnitEfectivo);
            }
        } else {
            // Caso B: El descuento agota el Neto+IVA y pasa a los Impuestos Internos
            netoUnitEfectivo = 0;
            ivaUnitEfectivo = 0;
            const excess_discount = r(total_discount_money_unit - base_neto_iva_unit);
            impIntEfectivo = r(Math.max(0, impint - excess_discount));
        }

        const totalfinal_unit = r(netoUnitEfectivo + ivaUnitEfectivo + impIntEfectivo);

        return {
            netoUnit: netoUnitEfectivo,
            ivaUnit: ivaUnitEfectivo,
            impIntUnit: impIntEfectivo,
            totalUnit: totalfinal_unit,
            netoTotal: r(netoUnitEfectivo * it.cantidad),
            ivaTotal: r(ivaUnitEfectivo * it.cantidad),
            impIntTotal: r(impIntEfectivo * it.cantidad),
            totalFinal: r(totalfinal_unit * it.cantidad),
            discountMoneyTotal: r(total_discount_money_unit * it.cantidad),
            effectiveAlicuota: effectivePoriva,
            finalOriginalTotal: r(final_original_unit * it.cantidad)
        };
    }

    /**
     * Inicialización del Módulo
     */
    async function advertirDatosEmisor(validacion) {
        if (!validacion?.requerida || validacion.completo) return false;
        const faltan = (validacion.faltantes || []).join(', ');
        const deposito = validacion.deposito ? ` para ${validacion.deposito}` : '';
        const aviso = await Swal.fire({
            icon: 'warning',
            title: 'Faltan datos para facturar',
            text: `Complete${deposito}: ${faltan}. Puede cargarlos en Datos de la Empresa o en el depósito; la fecha de inicio de actividades se carga en Datos de la Empresa.`,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonText: 'DATOS DE LA EMPRESA',
            denyButtonText: 'DEPÓSITOS',
            cancelButtonText: 'CERRAR'
        });
        if (aviso.isConfirmed) { loadModule('empresa_datos.php'); return true; }
        if (aviso.isDenied) { loadModule('depositos_admin.php'); return true; }
        return false;
    }

    async function init() {
        const h = { 'X-Requested-With': 'XMLHttpRequest' };
        try {
            const response = await fetch('api_facturar_gets.php?action=get_maestros', { headers: h });

            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }

            const text = await response.text();
            if (!text || text.trim() === "") {
                throw new Error("El servidor devolvió una respuesta vacía.");
            }

            const res = JSON.parse(text);

            if (res.success) {
                maestros = res;
                config = res.config || res.configuracion;

                if (await advertirDatosEmisor(res.emisor_validacion)) return;

                renderCombos();
                renderGrid();
                aplicarConfiguracionInicial();

                // REGLA: Detectar Pedido o PRESUPUESTO desde URL
                const urlParams = new URLSearchParams(window.location.search);
                const pedId = urlParams.get('pedidoid');
                const preId = urlParams.get('presupuestoid');

                if (pedId) await cargarPedidoDesdeAdmin(pedId);
                if (preId) await cargarPresupuestoDesdeAdmin(preId);

            } else {
                Swal.fire('Error de Carga', res.error || 'No se pudieron obtener los maestros del sistema.', 'error');
            }

            // Select2 de Clientes
            $('#sel_cliente').select2({
                dropdownParent: $('#billingAppContainer'),
                width: '100%',
                placeholder: "F3 - BUSCAR CLIENTE...",
                ajax: {
                    url: 'api_facturar_gets.php?action=buscar_clientes',
                    dataType: 'json',
                    delay: 250,
                    headers: h,
                    data: params => ({ q: params.term }),
                    processResults: res => ({ results: res.data.map(c => ({ id: c.id, text: c.razon, full: c })) })
                }
            }).on('select2:select', e => setCliente(e.params.data.full));

            // Select2 de Productos
            $('#in_desc_select2').select2({
                dropdownParent: $('#billingAppContainer'),
                width: '100%',
                placeholder: "BUSCAR POR DESCRIPCIÓN...",
                ajax: {
                    url: 'api_facturar_gets.php?action=buscar_productos',
                    dataType: 'json',
                    cache: false,
                    delay: 300,
                    headers: h,
                    data: params => ({ q: params.term }),
                    processResults: res => ({ results: res.data.map(p => ({ id: p.codigo, text: p.descripcion, full: p })) })
                }
            }).on('select2:select', e => setProducto(e.params.data.full));

            $('#pay_m1, #pay_m2').select2({
                dropdownParent: $('#mod_pay'),
                width: '65%',
                placeholder: 'BUSCAR MEDIO...',
                minimumResultsForSearch: 0
            });

            setupEvents();
            renderGrid();
        } catch (e) {
            console.error("Error init bill:", e);
            Swal.fire('Error de Comunicación', 'No se pudo procesar la respuesta del servidor. ' + e.message, 'error');
        }
    }

    /**
     * REGLA: Facturación de Pedidos (Carga y validación)
     */
    async function cargarPedidoDesdeAdmin(id) {
        Swal.fire({
            title: 'Cargando Pedido...',
            text: 'Validando integridad y stock',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            const res = await fetch(`api_facturar_gets.php?action=get_pedido_para_facturar&id=${id}`, { headers: h }).then(r => r.json());

            if (!res.success) {
                Swal.fire('Error', res.error, 'error').then(() => {
                    loadModule('pedidos_admin.php');
                });
                return;
            }

            pedidoOrigenId = id;

            // 1. Establecer Cliente
            const cli = {
                id: res.pedido.cliente,
                razon: res.pedido.razon,
                numdoc: res.pedido.numdoc,
                condicionfiscal: res.pedido.condicionfiscal,
                vendedorid: res.pedido.vendedorid,
                ListaDePRecios: res.pedido.lista_original_cli,
                domicilio: res.pedido.domicilio,
                cuentacorriente: res.pedido.cuentacorriente
            };

            setCliente(cli);

            // REGLA: Si es pedido, bloquear modificación de cliente
            $('#sel_cliente').prop('disabled', true);

            // 2. Mapear items al carrito
            cart = res.items.map(it => {
                const nLista = parseInt(it.lista) || 1;
                return {
                    codigo: it.producto,
                    descripcion: it.descripcion,
                    cantidad: parseFloat(it.cantidad),
                    precio_base: parseFloat(it.preciounit),
                    poriva: parseFloat(it.poriva),
                    imp_int_unit: parseFloat(it.impint),
                    lista: nLista,
                    costo: parseFloat(it.preciocosto),
                    pordesc: parseFloat(it.descuento),
                    precios_cache: {
                        codigo: it.producto,
                        descripcion: it.descripcion,
                        poriva: it.poriva,
                        ImpInt: it.impint,
                        preciocosto: it.preciocosto,
                        rubro: it.rubro,
                        marca: it.marca,
                        controlastock: it.controlastock,
                        siniva: it.siniva,
                        siniva2: it.siniva2,
                        siniva3: it.siniva3,
                        siniva4: it.siniva4,
                        siniva5: it.siniva5,
                        siniva6: it.siniva6,
                        siniva7: it.siniva7,
                        siniva8: it.siniva8,
                        maxdescl1: 100, maxdescl2: 100, maxdescl3: 100, maxdescl4: 100,
                        maxdescl5: 100, maxdescl6: 100, maxdescl7: 100, maxdescl8: 100
                    }
                };
            });

            if (res.items.length > 0) {
                $('#in_lista').val(res.items[0].lista).trigger('change');
            }

            renderGrid();
            Swal.close();

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo importar el pedido: ' + e.message, 'error');
        }
    }

    /**
     * REGLA: Facturación de PRESUPUESTOS (Carga y validación)
     */
    async function cargarPresupuestoDesdeAdmin(id) {
        Swal.fire({
            title: 'Cargando Presupuesto...',
            text: 'Validando integridad',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            const res = await fetch(`api_facturar_gets.php?action=get_presupuesto_para_facturar&id=${id}`, { headers: h }).then(r => r.json());

            if (!res.success) {
                Swal.fire('Error', res.error, 'error').then(() => {
                    loadModule('presupuestos_admin.php');
                });
                return;
            }

            presupuestoOrigenId = id;

            const cli = {
                id: res.presupuesto.cliente,
                razon: res.presupuesto.razon,
                numdoc: res.presupuesto.numdoc,
                condicionfiscal: res.presupuesto.condicionfiscal,
                vendedorid: res.presupuesto.vendedorid,
                ListaDePRecios: res.presupuesto.lista_original_cli,
                domicilio: res.presupuesto.domicilio,
                cuentacorriente: res.presupuesto.cuentacorriente
            };

            setCliente(cli);
            $('#sel_cliente').prop('disabled', true);

            cart = res.items.map(it => {
                const nLista = parseInt(it.lista) || 1;
                return {
                    codigo: it.producto,
                    descripcion: it.descripcion,
                    cantidad: parseFloat(it.cantidad),
                    precio_base: parseFloat(it.preciounit),
                    poriva: parseFloat(it.poriva),
                    imp_int_unit: parseFloat(it.impint),
                    lista: nLista,
                    costo: parseFloat(it.preciocosto),
                    pordesc: parseFloat(it.descuento),
                    precios_cache: {
                        codigo: it.producto, descripcion: it.descripcion, poriva: it.poriva, ImpInt: it.impint,
                        preciocosto: it.preciocosto, rubro: it.rubro, marca: it.marca, controlastock: it.controlastock,
                        siniva: it.siniva, siniva2: it.siniva2, siniva3: it.siniva3, siniva4: it.siniva4, siniva5: it.siniva5, siniva6: it.siniva6, siniva7: it.siniva7, siniva8: it.siniva8
                    }
                };
            });

            if (res.items.length > 0) $('#in_lista').val(res.items[0].lista).trigger('change');

            renderGrid();
            Swal.close();

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo importar el presupuesto: ' + e.message, 'error');
        }
    }

    /**
     * Aplica los parámetros de la tabla configuración
     */
    async function aplicarConfiguracionInicial() {
        if (!config) return;

        resetLine();

        if (parseInt(config.descuentosglobales) === 0) $('#btn_desc_global').hide();
        if (parseInt(config.recargosglobales) === 0) $('#btn_reca_global').hide();

        if (parseInt(config.predeterminarcliente) === 1 && config.clientepredeterminado && !pedidoOrigenId && !presupuestoOrigenId) {
            try {
                const res = await fetch(`api_facturar_gets.php?action=buscar_clientes&q=${config.clientepredeterminado}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                if (res.success && res.data.length > 0) {
                    const cli = res.data.find(c => c.id == config.clientepredeterminado);
                    if (cli) setCliente(cli);
                }
            } catch (e) { console.error("Error cargando cliente predeterminado", e); }
        }

        if (parseInt(config.modiflistas) === 0) {
            $('#in_lista').attr('disabled', true);
        } else {
            $('#in_lista').attr('disabled', false);
        }

        if (config.predeterminarcomprobante && parseInt(config.predeterminarcomprobante) > 0) {
            const tipoPredeterminado = String(config.predeterminarcomprobante);
            const tipoDisponible = $(`#sel_tipo option[value="${tipoPredeterminado}"]`).length > 0
                ? tipoPredeterminado : '100';
            $('#sel_tipo').val(tipoDisponible).trigger('change');
        }
    }

    function setupEvents() {
        $(document).off('keydown').on('keydown', e => {
            if (e.key === 'Escape') {
                const visibleModal = $('.modal.show');
                if (visibleModal.length > 0) {
                    e.preventDefault();
                    visibleModal.modal('hide');
                }
            }

            if (e.key === 'F8') {
                e.preventDefault();
                bill.abrirLineaManual();
            }

            if (e.key === 'F10') {
                e.preventDefault();
                if ($('#mod_pay').is(':visible')) {
                    bill.grabar();
                } else {
                    bill.intentarCobro();
                }
            }
            if (e.key === 'F4') { e.preventDefault(); bill.nueva(); }
            if (e.key === 'F3' && !pedidoOrigenId && !presupuestoOrigenId) { e.preventDefault(); $('#sel_cliente').select2('open'); }
            if (e.key === 'F2') {
                e.preventDefault();
                if (config?.texboxpredeterminado === 'CANTIDAD') $('#in_cant').focus();
                else $('#in_cod').focus();
            }
        });

        $('#in_cant').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#in_cod').focus().select();
            }
        });

        $('#in_lista').on('change', function () {
            const nuevaLista = parseInt($(this).val()) || 1;
            actualizarListaEnCarrito(nuevaLista);
        });

        $('#ml_cant').on('keypress', function (e) { if (e.which === 13) { e.preventDefault(); $('#ml_det').focus(); } });
        $('#ml_det').on('keypress', function (e) { if (e.which === 13) { e.preventDefault(); $('#ml_total').focus(); } });
        $('#ml_total').on('keypress', function (e) { if (e.which === 13) { e.preventDefault(); bill.confirmarLineaManual(); } });

        $('#sel_tipo').on('change', function () {
            const tipo = parseInt($(this).val());
            if (!clienteSeleccionado || !maestros.empresa) return;

            const permitidosAsociar = [2, 3, 7, 8, 12, 13, 101];
            if (permitidosAsociar.includes(tipo)) {
                $('#btnAsociarTrigger').show();
            } else {
                $('#btnAsociarTrigger').hide();
                associatedDoc = null;
            }

            const check = billValidator.validateInvoiceType(tipo, clienteSeleccionado, maestros.empresa);
            if (!check.isValid) {
                actualizarTipoPorDefecto();
            }

            renderGrid();
            actualizarProximoNumero();

            setTimeout(() => {
                if (config?.texboxpredeterminado === 'CANTIDAD') $('#in_cant').focus().select();
                else $('#in_cod').focus().select();
            }, 200);
        });

        $('#in_mod_cli_q').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => buscarClienteModal(this.value), 300);
        });

        $('#in_mod_art_q').on('keyup', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => buscarArticuloModal(this.value), 300);
        });

        $('#in_cod').on('keypress', async e => {
            if (e.which === 13) {
                const code = e.target.value.trim();
                if (!code) return;
                const h = { 'X-Requested-With': 'XMLHttpRequest' };

                try {
                    const resRef = await fetch(`api_referencias.php?action=resolve&code=${encodeURIComponent(code)}`, { headers: h }).then(r => r.json());
                    const realCode = resRef.data?.codigo_real || code;

                    const res = await fetch(`api_facturar_gets.php?action=buscar_codigo_producto&q=${encodeURIComponent(realCode)}`, { headers: h, cache: 'no-store' }).then(r => r.json());
                    if (res.success && res.data.length > 0) {
                        setProducto(res.data[0]);
                        addItemInternal();
                    } else {
                        Swal.fire('No Encontrado', `El código [${code}] no corresponde a ningún producto activo.`, 'error');
                        $('#in_cod').val('').focus();
                    }
                } catch (err) {
                    Swal.fire('Error de Red', 'No se pudo consultar el código del producto.', 'error');
                }
            }
        });

        $('#pay_m1, #pay_m2').on('change', function () {
            const m1 = $('#pay_m1').val();
            const m2 = $('#pay_m2').val();
            const selId = $(this).val();

            if (m1 && m2 && m1 === m2) {
                Swal.fire('Atención', 'No puede elegir el mismo medio de pago dos veces.', 'warning');
                $(this).val(null).trigger('change');
                return;
            }

            if (selId) {
                const medio = maestros.medios_pago.find(m => m.id == selId);
                if (medio && medio.tipodecuenta === 'Cuenta Corriente') {
                    if (!clienteSeleccionado || parseInt(clienteSeleccionado.cuentacorriente) === 0) {
                        Swal.fire('Crédito Bloqueado', 'El cliente no tiene habilitada la Cuenta Corriente o es un cliente eventual.', 'error');
                        $(this).val(null).trigger('change');
                    }
                }
            }
            actualizarAyudaEfectivo();
        });
        $('#pay_i1, #pay_i2, #pay_entrega_efectivo').on('input change', actualizarAyudaEfectivo);
    }

    function actualizarAyudaEfectivo() {
        const numero = valor => {
            const n = parseFloat(String(valor ?? '').replace(/\s/g, '').replace(',', '.'));
            return Number.isFinite(n) ? n : 0;
        };
        const esEfectivo = id => {
            const medio = maestros.medios_pago.find(m => m.id == id);
            return medio && (medio.tipodecuenta === 'Caja de Usuarios' || /efectivo/i.test(medio.medio || ''));
        };
        const efectivo1 = esEfectivo($('#pay_m1').val());
        const efectivo2 = esEfectivo($('#pay_m2').val());
        $('#pay_efectivo_helper').toggleClass('d-none', !efectivo1 && !efectivo2);
        if (!efectivo1 && !efectivo2) {
            $('#pay_entrega_efectivo').val('');
            $('#pay_vuelto').text('$ 0,00');
            $('#pay_falta').text('$ 0,00');
            return;
        }
        const aplicado = (efectivo1 ? numero($('#pay_i1').val()) : 0) + (efectivo2 ? numero($('#pay_i2').val()) : 0);
        const entrega = numero($('#pay_entrega_efectivo').val());
        $('#pay_vuelto').text('$ ' + m(Math.max(0, r(entrega - aplicado))));
        $('#pay_falta').text('$ ' + m(Math.max(0, r(aplicado - entrega))));
    }

    async function buscarClienteModal(q) {
        const res = await fetch(`api_facturar_gets.php?action=buscar_clientes&q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
        const $b = $('#tbody_mod_cli').empty();
        res.data.forEach(c => {
            $b.append(`
                <tr>
                    <td>${c.razon}</td>
                    <td>${c.numdoc}</td>
                    <td>${c.domicilio}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-info" onclick="bill.seleccionarDesdeModal('CLI', ${JSON.stringify(c).replace(/"/g, '&quot;')})">
                            <i class="fas fa-check"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function setCliente(obj) {
        clienteSeleccionado = obj;

        renderCombos();

        if ($('#sel_cliente').find("option[value='" + obj.id + "']").length === 0) {
            const newOption = new Option(obj.razon, obj.id, true, true);
            $('#sel_cliente').append(newOption).trigger('change');
        } else {
            $('#sel_cliente').val(obj.id).trigger('change');
        }

        if (obj.vendedorid) {
            $('#pay_v').val(obj.vendedorid).trigger('change');
        }

        if (obj.ListaDePRecios) {
            $('#in_lista').val(obj.ListaDePRecios).trigger('change');
        }

        const tipoActual = $('#sel_tipo').val();
        if (tipoActual && maestros.empresa) {
            const check = billValidator.validateInvoiceType(tipoActual, obj, maestros.empresa);
            if (!check.isValid) {
                actualizarTipoPorDefecto();
            }
        }

        renderGrid();
        $('#badge_fiscal').html(`${obj.condicionfiscal}<br>${obj.numdoc || ''}`);
        actualizarProximoNumero();
    }

    function actualizarListaEnCarrito(nuevaLista) {
        const nLista = parseInt(nuevaLista);
        cart.forEach(it => {
            if (it.codigo !== 'LM' && it.precios_cache) {
                it.lista = nLista;
                const col = nLista === 1 ? 'siniva' : 'siniva' + nLista;
                it.precio_base = parseFloat(it.precios_cache[col] || 0);
                it.pordesc = 0;
            }
        });
        renderGrid();
    }

    async function buscarArticuloModal(q) {
        const res = await fetch(`api_facturar_gets.php?action=buscar_productos&q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' }).then(r => r.json());
        const $b = $('#tbody_mod_art').empty();
        const lista = parseInt($('#in_lista').val()) || 1;
        const col = lista === 1 ? 'siniva' : 'siniva' + lista;

        res.data.forEach(p => {
            const neto = parseFloat(p[col] || 0);
            const final = neto * (1 + (parseFloat(p.poriva) / 100)) + parseFloat(p.ImpInt || 0);
            $b.append(`
                <tr>
                    <td class="fw-bold">${p.codigo}</td>
                    <td>${p.descripcion}</td>
                    <td>${p.rubro}</td>
                    <td class="text-end">$ ${neto.toFixed(2)}</td>
                    <td class="text-end text-accent fw-bold">$ ${final.toFixed(2)}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-info" onclick="bill.seleccionarDesdeModal('ART', ${JSON.stringify(p).replace(/"/g, '&quot;')})">
                            <i class="fas fa-plus"></i>
                        </button>
                    </td>
                </tr>
            `);
        });
    }

    function setProducto(p) {
        curProd = p;
        const lista = parseInt($('#in_lista').val()) || 1;
        const col = lista === 1 ? 'siniva' : 'siniva' + lista;
        $('#in_cod').val(p.codigo);
        $('#in_unit').val(parseFloat(p[col] || 0).toFixed(2));
        $('#in_desc_select2').val(p.codigo).trigger('change.select2');
    }

    function addItemInternal() {
        if (!clienteSeleccionado) {
            Swal.fire('Atención', 'Debe seleccionar un cliente antes de agregar productos a la grilla.', 'warning');
            return;
        }
        if (!curProd) return;

        let cant = parseFloat($('#in_cant').val());
        if (isNaN(cant) || cant <= 0) cant = 1;

        const stockActual = parseFloat(curProd.stock || curProd.Stock || 0);
        const existIdx = cart.findIndex(x => x.codigo.toString().trim() === curProd.codigo.toString().trim());
        const cantEnGrilla = existIdx !== -1 ? cart[existIdx].cantidad : 0;

        if (parseInt(config?.stocknegativo) === 0 && !esNotaCredito() && stockActual < (cant + cantEnGrilla)) {
            Swal.fire({
                title: 'Stock Insuficiente',
                text: `El producto no permite stock negativo. Disponible: ${stockActual}.`,
                icon: 'error',
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            });
            return;
        }

        const lista = parseInt($('#in_lista').val()) || 1;
        const col = lista === 1 ? 'siniva' : 'siniva' + lista;
        const descPorc = parseFloat($('#in_desc_it').val()) || 0;

        if (parseInt(config?.agruparproductos) === 1 && existIdx !== -1) {
            cart[existIdx].cantidad += cant;
        } else {
            cart.push({
                codigo: curProd.codigo, descripcion: curProd.descripcion,
                cantidad: cant, precio_base: parseFloat(curProd[col] || 0),
                poriva: parseFloat(curProd.poriva || 0), imp_int_unit: parseFloat(curProd.ImpInt || 0),
                lista: lista, costo: parseFloat(curProd.preciocosto || 0),
                pordesc: descPorc, precios_cache: curProd,
                promo_label: null, promo_id: null
            });
        }
        evaluarPromociones().then(() => renderGrid());
        resetLine();
    }

    /**
     * Evalúa promociones vigentes contra el carrito actual.
     * Solo pisa el pordesc si la promo supera el descuento manual existente.
     * Si el operador edita precio/descuento manualmente, promo_label/id se limpian en updateItemInternal.
     */
    async function evaluarPromociones() {
        if (cart.length === 0) return;
        try {
            const cartParam = encodeURIComponent(JSON.stringify(cart.map(it => ({
                codigo:   it.codigo,
                cantidad: it.cantidad,
                lista:    it.lista,
                rubro:    it.precios_cache?.rubro || '',
                marca:    it.precios_cache?.Marca || it.precios_cache?.marca || ''
            }))));
            const res = await fetch(
                `api_promociones.php?action=evaluar&cart=${cartParam}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            ).then(r => r.json());

            if (!res.success) return;

            res.resultados.forEach((r, idx) => {
                if (!cart[idx]) return;
                if (r.pordesc > 0) {
                    if (r.pordesc > cart[idx].pordesc) {
                        cart[idx].pordesc = r.pordesc;
                    }
                    cart[idx].promo_label = r.promo_label;
                    cart[idx].promo_id    = r.promo_id;
                } else {
                    cart[idx].promo_label = null;
                    cart[idx].promo_id    = null;
                }
            });
        } catch (e) {
            console.warn('Evaluación de promociones no disponible:', e.message);
        }
    }

    function renderGrid() {
        const isReducida = parseInt(config?.tablareducidaventas) === 1;
        const $h = $('#grid_head').empty();

        $h.append(`
            <tr style="border-bottom: 2px solid var(--accent);">
                <th width="60" class="text-center" style="color: #ffffff; font-weight: 800;">CANT.</th>
                <th width="100" style="color: #ffffff; font-weight: 800;">CÓDIGO</th>
                <th style="color: #ffffff; font-weight: 800;">DESCRIPCIÓN</th>
                <th width="40" style="color: #ffffff; font-weight: 800;">LIS.</th>
                <th width="80" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">NETO U.</th>
                <th width="80" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">I.I. U.</th>
                <th width="80" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">IVA U.</th>
                <th width="80" class="text-end" style="color: #ffffff; font-weight: 800;">FINAL U.</th>
                <th width="70" class="text-center" style="color: #ffffff; font-weight: 800;">%DESC</th>
                <th width="80" class="text-end" style="color: #ffffff; font-weight: 800;">$DESC</th>
                <th width="85" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">NETO T.</th>
                <th width="80" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">I.I. T.</th>
                <th width="80" class="text-end ${isReducida ? 'd-none' : ''}" style="color: #ffffff; font-weight: 800;">IVA T.</th>
                <th width="95" class="text-end" style="color: #ffffff; font-weight: 800;">TOTAL F.</th>
                <th width="35"></th>
            </tr>
        `);

        const $b = $('#grid_body').empty();
        let s21 = 0,
            s105 = 0,
            s0 = 0,
            tII = 0,
            tIva = 0;

        cart.forEach((it, i) => {
            const d = calculateLineData(it);

            if (d.effectiveAlicuota === 21) {
                s21 += d.netoTotal;
            }
            else if (d.effectiveAlicuota === 10.5) {
                s105 += d.netoTotal;
            }
            else if (d.effectiveAlicuota === 0) {
                s0 += d.netoTotal;
            }

            tIva += d.ivaTotal;
            tII += d.impIntTotal;

            const bgRow = i % 2 === 0 ? 'rgba(0,0,0,0.15)' : 'rgba(255,255,255,0.04)';
            const mutedStyle = 'color: var(--text-muted);';

            const canEdit = parseInt(config?.modificarprecioenventas) === 1;
            const canModifLista = parseInt(config?.modiflistas) === 1;

            const promoBadge = it.promo_label
                ? `<span title="${it.promo_label}" style="font-size:8px;font-weight:900;padding:1px 5px;border-radius:10px;background:#1a3a2a;color:#4ade80;border:1px solid #16a34a;display:inline-block;white-space:nowrap;vertical-align:middle;margin-left:3px;">🏷️ PROMO</span>`
                : '';

            $b.append(`
                <tr style="background: ${bgRow}; border-bottom: 1px solid rgba(255,255,255,0.03);">
                    <td><input type="number" class="form-control form-control-sm text-center bg-transparent border-0 text-info fw-bold p-0" value="${it.cantidad}" onchange="bill.updateItem(${i}, 'cant', this.value)"></td>
                    <td class="fw-bold" style="color: var(--text-main);">${it.codigo}</td>
                    <td style="color: var(--text-main);">${it.descripcion}${promoBadge}</td>
                    <td class="text-center">
                        ${canModifLista ?
                    `<select class="form-select form-select-sm bg-transparent border-0 text-white-50 p-0 text-center" onchange="bill.updateItem(${i}, 'lista', this.value)" style="font-size: 10px; width: 45px !important; display: inline-block;">
                                ${[1, 2, 3, 4, 5, 6, 7, 8].map(num => `<option value="${num}" ${it.lista == num ? 'selected' : ''} class="bg-dark">${num}</option>`).join('')}
                            </select>` :
                    `<span style="${mutedStyle}">${it.lista}</span>`
                }
                    </td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}">
                        ${canEdit ?
                    `<input type="number" step="0.01" class="text-end bg-transparent border-0 theme-aware-input w-100 p-0" value="${d.netoUnit.toFixed(2)}" onblur="bill.updateItem(${i}, 'precio_base', this.value)">` :
                    `<span style="${mutedStyle}">${m(d.netoUnit)}</span>`}
                    </td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}" style="${mutedStyle}">${m(d.impIntUnit)}</td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}" style="${mutedStyle}">${m(d.ivaUnit)}</td>
                    <td class="text-end">
                        ${canEdit ?
                    `<input type="number" step="0.01" class="text-end bg-transparent border-0 theme-aware-input w-100 p-0" value="${d.totalUnit.toFixed(2)}" onblur="bill.updateItem(${i}, 'precio_final', this.value)">` :
                    `<span style="color: var(--accent); font-weight:700;">${m(d.totalUnit)}</span>`}
                    </td>
                    <td><input type="number" class="form-control form-control-sm text-center bg-transparent border-0 text-warning p-0" value="${it.pordesc}" onchange="bill.updateItem(${i}, 'pordesc', this.value)"></td>
                    <td><input type="number" class="form-control form-control-sm text-end bg-transparent border-0 text-warning p-0" value="${(d.discountMoneyTotal).toFixed(2)}" onchange="bill.updateItem(${i}, 'descS', this.value)"></td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}" style="${mutedStyle}">${m(d.netoTotal)}</td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}" style="${mutedStyle}">${m(d.impIntTotal)}</td>
                    <td class="text-end ${isReducida ? 'd-none' : ''}" style="${mutedStyle}">${m(d.ivaTotal)}</td>
                    <td class="text-end text-accent fw-bold">${m(d.totalFinal)}</td>
                    <td class="text-center"><i class="fas fa-trash text-danger cursor-pointer" style="opacity: 0.5;" onclick="bill.rem(${i})"></i></td>
                </tr>
            `);
        });


        const subtotalGral = r(s21 + s105 + s0 + tIva + tII);
        const totalConAjustes = r(subtotalGral - globalAdj.desc + globalAdj.reca);

        $('#tx_n21_1').html(m(s21)).css('color', 'var(--text-main)');
        $('#tx_n105_1').html(m(s105)).css('color', 'var(--text-main)');
        $('#tx_ii_final_total').html(m(tII)).css('color', 'var(--text-main)');
        $('#tx_iva_f').html(m(tIva)).css('color', 'var(--text-main)');
        $('#tx_desc_g').html(m(globalAdj.desc));
        $('#tx_reca_g').html(m(globalAdj.reca));
        $('#tx_neto_f').html(m(s21 + s105 + s0)).css('color', 'var(--accent)');
        $('#tx_total_f').html(m(totalConAjustes)).css({ 'color': 'var(--accent)', 'text-shadow': '0 0 15px rgba(0,255,255,0.3)' });
        $('#tx_total_unidades').text(m(cart.reduce((acc, it) => acc + (parseFloat(it.cantidad) || 0), 0)));

        $('#mod_pay_total').text('$ ' + m(totalConAjustes));
        $('#pay_i1').val(totalConAjustes.toFixed(2));
        actualizarAyudaEfectivo();
    }

    function renderCombos() {
        fillSelect('#pay_v', maestros.vendedores, 'nombre');
        fillSelect('#pay_m1', maestros.medios_pago, 'medio');
        fillSelect('#pay_m2', maestros.medios_pago, 'medio');

        const $ls = $('#in_lista').empty();
        [1, 2, 3, 4, 5, 6, 7, 8].forEach(n => $ls.append(new Option('LISTA ' + n, n)));

        if (!maestros.empresa) return;

        const empresaEsRI = maestros.empresa.condicioniva.toUpperCase().includes('INSCRIPTO');
        const condCliente = (clienteSeleccionado?.condicionfiscal || '').toUpperCase();
        const clienteEsRIMono = condCliente.includes('INSCRIPTO') || condCliente.includes('MONOTRIBUTO');

        let tiposPermitidos = [];

        if (maestros.arca_fiscal_habilitado !== true) {
            tiposPermitidos = [{ id: 100, n: 'TICKET' }, { id: 101, n: 'TICKET CRÉDITO' }];
            fillSelect('#sel_tipo', tiposPermitidos, 'n');
            return;
        }

        // REGLA 1: Filtrar tipos según Condición Fiscal
        if (empresaEsRI) {
            if (clienteEsRIMono) {
                tiposPermitidos = [{ id: 1, n: 'FACTURA A' }, { id: 100, n: 'TICKET' }];
                if (parseInt(config?.admitendc) == 1 && !pedidoOrigenId && !presupuestoOrigenId) {
                    tiposPermitidos.push({ id: 2, n: 'N. DÉBITO A' }, { id: 3, n: 'N. CRÉDITO A' });
                }
            } else {
                tiposPermitidos = [{ id: 6, n: 'FACTURA B' }, { id: 100, n: 'TICKET' }];
                if (parseInt(config?.admitendc) == 1 && !pedidoOrigenId && !presupuestoOrigenId) {
                    tiposPermitidos.push({ id: 7, n: 'N. DÉBITO B' }, { id: 8, n: 'N. CRÉDITO B' });
                }
            }
        } else {
            tiposPermitidos = [{ id: 11, n: 'FACTURA C' }, { id: 100, n: 'TICKET' }];
            if (parseInt(config?.admitendc) == 1 && !pedidoOrigenId && !presupuestoOrigenId) {
                tiposPermitidos.push({ id: 12, n: 'N. DÉBITO C' }, { id: 13, n: 'N. CRÉDITO C' });
            }
        }

        if (parseInt(config?.admitendc) == 1 || pedidoOrigenId || presupuestoOrigenId) {
            tiposPermitidos.push({ id: 101, n: 'TICKET CRÉDITO' });
        }

        // REGLA 2: Si es PEDIDO o PRESUPUESTO, excluir Notas de Crédito/Débito
        if (pedidoOrigenId || presupuestoOrigenId) {
            tiposPermitidos = tiposPermitidos.filter(t => [1, 6, 11, 100, 101].includes(t.id));
        }

        // Las notas de débito (2/7/12) solo se ofrecen si están habilitadas en
        // Configuración General (por defecto no).
        if (parseInt(config?.admitenotasdebito) !== 1) {
            tiposPermitidos = tiposPermitidos.filter(t => ![2, 7, 12].includes(t.id));
        }

        fillSelect('#sel_tipo', tiposPermitidos, 'n');
    }

    function actualizarTipoPorDefecto() {
        if (!clienteSeleccionado || !maestros.empresa) return;
        const isRI = clienteSeleccionado.condicionfiscal.toUpperCase().includes('INSCRIPTO');
        const isMono = clienteSeleccionado.condicionfiscal.toUpperCase().includes('MONOTRIBUTO');
        const empresaEsRI = maestros.empresa.condicioniva.toUpperCase().includes('INSCRIPTO');

        if (empresaEsRI) {
            $('#sel_tipo').val((isRI || isMono) ? 1 : 6).trigger('change');
        } else {
            $('#sel_tipo').val(11).trigger('change');
        }
    }

    async function actualizarProximoNumero() {
        const t = $('#sel_tipo').val(); if (!t) return;
        const res = await fetch(`api_facturar_gets.php?action=get_proximo_numero&tipo=${t}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
        if (res.success) $('#bill_nro_label').text('0004-' + res.proximo);
    }

    function updateItemInternal(index, field, value) {
        const it = cart[index];
        const val = parseFloat(value) || 0;

        if (field === 'cant') {
            const stockActual = parseFloat(it.precios_cache.stock || it.precios_cache.Stock || 0);
            if (parseInt(config?.stocknegativo) === 0 && !esNotaCredito() && val > stockActual) {
                Swal.fire('Stock Insuficiente', `No puede superar las ${stockActual} unidades disponibles.`, 'error');
                renderGrid(); return;
            }
            it.cantidad = val;
            evaluarPromociones().then(() => renderGrid());
            return;
        }
        else if (field === 'precio_base') {
            if (parseInt(config?.modificarprecioenventas) === 1) {
                it.precio_base = r(val);
                it.pordesc = 0;
                it.promo_label = null; it.promo_id = null;
            }
        }
        else if (field === 'precio_final') {
            if (parseInt(config?.modificarprecioenventas) === 1) {
                const tipoComp = parseInt($('#sel_tipo').val());
                const tktsiniva = parseInt(config?.ticketsiniva) === 1 && (tipoComp === 100 || tipoComp === 101);
                const alicuota = tktsiniva ? 0 : (it.poriva / 100);

                if (val < it.imp_int_unit) {
                    Swal.fire({
                        toast: true, position: 'top-end', icon: 'info',
                        title: 'Piso de Impuestos Internos alcanzado',
                        text: `El valor no puede ser inferior a $${it.imp_int_unit.toFixed(2)}`,
                        showConfirmButton: false, timer: 2500, background: 'var(--bg-card)', color: 'var(--text-main)'
                    });
                }

                const nuevoBase = (val - it.imp_int_unit) / (1 + alicuota);
                it.precio_base = r3(Math.max(0, nuevoBase));
                it.pordesc = 0;
                it.promo_label = null; it.promo_id = null;
            }
        }
        else if (field === 'pordesc') {
            const lista = it.lista || 1;
            const maxDesc = parseFloat(it.precios_cache['maxdescl' + lista] || 100);
            if (val > maxDesc) {
                Swal.fire('Descuento Excedido', `El descuento máximo para este producto en Lista ${lista} es ${maxDesc}%`, 'warning');
                renderGrid(); return;
            }
            it.pordesc = val;
            it.promo_label = null; it.promo_id = null;
        }
        else if (field === 'descS') {
            const d = calculateLineData(it);
            const porcInverso = d.finalOriginalTotal > 0 ? (val / d.finalOriginalTotal) * 100 : 0;
            const lista = it.lista || 1;
            const maxDesc = parseFloat(it.precios_cache['maxdescl' + lista] || 100);
            if (porcInverso > maxDesc) {
                Swal.fire('Descuento Excedido', `El importe equivale a ${r(porcInverso)}%, superando el máximo de ${maxDesc}%`, 'warning');
                renderGrid(); return;
            }
            it.pordesc = porcInverso;
            it.promo_label = null; it.promo_id = null;
        }
        else if (field === 'lista') {
            it.lista = parseInt(value);
            const col = it.lista === 1 ? 'siniva' : 'siniva' + it.lista;
            it.precio_base = parseFloat(it.precios_cache[col] || 0);
            it.pordesc = 0;
            it.promo_label = null; it.promo_id = null;
        }

        renderGrid();
    }

    function abrirAsociarInternal() {
        let tAsoc = parseInt($('#sel_tipo').val());
        let tFactura = "";
        let tReal = 0;
        switch (tAsoc) {
            case 13: case 12: tFactura = "FACTURA C"; tReal = 11; break;
            case 3: case 2: tFactura = "FACTURA A"; tReal = 1; break;
            case 8: case 7: tFactura = "FACTURA B"; tReal = 6; break;
            case 101: tFactura = "TICKET"; tReal = 100; break;
        }
        $('#asoc_pv').val('0004');
        $('#asoc_tipo_txt').val(tFactura).data('tipo-real', tReal);
        $('#asoc_num, #asoc_fecha').val('');
        $('#asoc_check_pv').prop('checked', false);
        $('#mod_asociar').modal('show');
    }

    async function confirmarAsociacionInternal() {
        const num = $('#asoc_num').val();
        const pv = $('#asoc_pv').val();
        const tipo = $('#asoc_tipo_txt').data('tipo-real');
        const fechaIng = $('#asoc_fecha').val();
        if (!num || !fechaIng) return Swal.fire('Error', 'Complete número y fecha.', 'warning');
        const h = { 'X-Requested-With': 'XMLHttpRequest' };
        const res = await fetch(`api_facturar_gets.php?action=buscar_ticket_asoc&num=${num}&tipo=${tipo}&pv=${pv}`, { headers: h }).then(r => r.json());
        if (!res.success) return Swal.fire('Error', res.error, 'error');
        const ticket = res.data;
        const fechaReal = ticket.fecha.split(' ')[0];
        if (fechaReal !== fechaIng) return Swal.fire('Error', `La fecha real es ${fechaReal}`, 'error');
        if (parseInt(ticket.cliente) !== parseInt(clienteSeleccionado.id)) return Swal.fire('Error', 'Comprobante de otro cliente.', 'error');
        const diff = (new Date() - new Date(fechaReal)) / (1000 * 60 * 60 * 24);
        if (diff > 60) return Swal.fire('Error', 'Excede los 60 días de antigüedad.', 'error');
        associatedDoc = { pv: pv.padStart(4, '0'), num: num.padStart(8, '0'), fecha: fechaReal, tipo: tipo };
        Swal.fire('Éxito', `ASOCIADO: ${associatedDoc.pv}-${associatedDoc.num}`, 'success');
        $('#mod_asociar').modal('hide');
    }

    function modalGlobalInternal(type) {
        if (type === 'DESC' && parseInt(config?.descuentosglobales) === 0) return;
        if (type === 'RECA' && parseInt(config?.recargosglobales) === 0) return;
        const title = type === 'DESC' ? 'DESCUENTO GLOBAL' : 'RECARGO GLOBAL';
        const currentAmount = type === 'DESC' ? globalAdj.desc : globalAdj.reca;
        const subtotalActual = cart.reduce((acc, it) => acc + calculateLineData(it).totalFinal, 0);
        const currentPercent = subtotalActual > 0 ? (currentAmount / subtotalActual) * 100 : 0;

        Swal.fire({
            title: title, background: 'var(--bg-card)', color: 'var(--text-main)',
            html: `<div class="p-2 text-start"><div class="mb-3"><label class="form-label-custom">PORCENTAJE (%)</label><input type="number" id="adj_perc" class="form-control in-pos" value="${currentPercent.toFixed(2)}" step="0.01"></div><div class="mb-2"><label class="form-label-custom">IMPORTE FIJO ($)</label><input type="number" id="adj_fix" class="form-control in-pos" value="${currentAmount.toFixed(2)}" step="0.01"></div></div>`,
            showCancelButton: true, confirmButtonText: 'APLICAR CAMBIOS',
            didOpen: () => {
                const $perc = $('#adj_perc'), $fix = $('#adj_fix');
                $perc.on('input', function () { $fix.val(r(($(this).val() || 0) / 100 * subtotalActual).toFixed(2)); });
                $fix.on('input', function () { $perc.val(subtotalActual > 0 ? (($(this).val() || 0) / subtotalActual * 100).toFixed(2) : 0); });
            },
            preConfirm: () => { return r(parseFloat($('#adj_fix').val()) || 0); }
        }).then((result) => {
            if (result.isConfirmed) {
                if (type === 'DESC') globalAdj.desc = result.value;
                else globalAdj.reca = result.value;
                renderGrid();
            }
        });
    }

    async function grabar() {
        if (cart.length === 0) return;
        const totalDoc = r(cart.reduce((acc, it) => acc + calculateLineData(it).totalFinal, 0) - globalAdj.desc + globalAdj.reca);

        const localCheck = billValidator.validateBeforePay({
            cart: cart,
            clienteSeleccionado: clienteSeleccionado,
            total: m(totalDoc),
            vendedor: $('#pay_v').val(),
            tipoComp: $('#sel_tipo').val(),
            m1: $('#pay_m1').val(),
            m2: $('#pay_m2').val(),
            imp1: $('#pay_i1').val(),
            imp2: $('#pay_i2').val(),
            maestros: maestros,
            associatedDoc: associatedDoc
        });

        if (!localCheck.isValid) {
            return Swal.fire({
                icon: 'warning',
                title: 'Atención',
                text: localCheck.message,
                background: 'var(--bg-card)',
                color: 'var(--text-main)'
            });
        }

        const dataPayload = {
            cliente_id: clienteSeleccionado?.id,
            vendedor_id: $('#pay_v').val(),
            tipo_comp: $('#sel_tipo').val(),
            pv: 4,
            fecha: new Date().toISOString().split('T')[0],
            items: cart.map(it => ({
                codigo: it.codigo, descripcion: it.descripcion, cantidad: it.cantidad,
                precio_base: it.precio_base, poriva: it.poriva, imp_int_unit: it.imp_int_unit,
                lista: it.lista, pordesc: it.pordesc, costo: it.costo,
                promo_id: it.promo_id || null
            })),
            mp1: $('#pay_m1').val(), imp1: $('#pay_i1').val(),
            mp2: $('#pay_m2').val(), imp2: $('#pay_i2').val(),
            total_comprobante: totalDoc,
            globalAdj: globalAdj,
            associatedDoc: associatedDoc,
            lista_global: $('#in_lista').val(),
            pedido_id: pedidoOrigenId,
            presupuesto_id: presupuestoOrigenId
        };

        Swal.fire({ title: 'Validando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const check = await fetch('api_facturacion_validaciones_previas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(dataPayload)
            }).then(r => r.json());

            if (!check.success) {
                return Swal.fire({ icon: 'error', title: 'Validación Fallida', text: check.error });
            }

            const res = await fetch('api_facturar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(dataPayload)
            }).then(r => r.json());

            if (res.success) {
                Swal.fire(res.cae_pendiente ? {
                    title: 'Comprobante grabado - CAE PENDIENTE',
                    html: `El comprobante se guardó correctamente, pero AFIP/ARCA <b>no autorizó el CAE</b>.<br>` +
                          `Motivo: ${res.cae_error || 'Sin detalle'}<br><br>` +
                          `Debe reintentar la obtención del CAE antes de entregar el comprobante al cliente.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Imprimir de todos modos',
                    cancelButtonText: 'Cerrar',
                    reverseButtons: true
                } : {
                    title: '¡Éxito!',
                    text: `Comprobante emitido correctamente. ¿Desea imprimirlo ahora?`,
                    icon: 'success',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#6c757d', // Un gris neutro para el botón de cerrar
                    confirmButtonText: 'Imprimir',
                    cancelButtonText: 'Cerrar',
                    reverseButtons: true // Opcional: invierte el orden para que "Imprimir" quede a la derecha
                }).then((result) => {
                    // Si el usuario hace clic en "Imprimir"
                    if (result.isConfirmed) {
                        const fmt = config?.formatoimpresion || 'A4';
                        const urlFmt = (fmt === '80' || fmt === '58') ? 'ticket' : 'A4';
                        window.open(`imprimir_ticket.php?id=${res.id_ticket}&format=${urlFmt}`, '_blank');
                    }

                    // --- Lógica de limpieza: se ejecuta siempre (ya sea que imprima o cierre) ---
                    const modalEl = document.getElementById('mod_pay');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }

                    if (pedidoOrigenId) {
                        loadModule('pedidos_admin.php');
                    } else if (presupuestoOrigenId) {
                        loadModule('presupuestos_admin.php');
                    } else {
                        bill.nueva();
                    }
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo comunicar con el servidor.', 'error');
        }
    }

    function abrirLineaManualInternal() {
        if (!clienteSeleccionado) return Swal.fire('Atención', 'Seleccione un cliente primero.', 'warning');
        $('#ml_cant').val(1);
        $('#ml_det').val('');
        $('#ml_total').val('');
        $('#ml_iva').val(21);
        $('#mod_manual_line').modal('show');
        setTimeout(() => $('#ml_cant').focus().select(), 500);
    }

    function confirmarLineaManualInternal() {
        const prod = $('#ml_det').val().trim();
        const totalFinalU = parseFloat($('#ml_total').val());
        const poriva = parseFloat($('#ml_iva').val());
        let cant = parseFloat($('#ml_cant').val());

        if (!prod || isNaN(totalFinalU) || totalFinalU <= 0) {
            return Swal.fire('Error', 'Complete descripción e importe mayor a cero.', 'warning');
        }
        if (isNaN(cant) || cant <= 0) cant = 1;

        const alicuota = esTicketSinIva() ? 0 : poriva;
        const netoU = r3(totalFinalU / (1 + alicuota / 100));

        cart.push({
            codigo: 'LM',
            descripcion: prod,
            cantidad: cant,
            precio_base: netoU,
            poriva: poriva,
            imp_int_unit: 0,
            lista: parseInt($('#in_lista').val()) || 1,
            costo: 0,
            pordesc: 0,
            precios_cache: { codigo: 'LM', descripcion: prod, poriva: poriva, siniva: netoU, siniva1: netoU, maxdescl1: 100 }
        });

        renderGrid();
        $('#mod_manual_line').modal('hide');
        resetLine();
    }

    function m(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fillSelect(sel, data, field) { $(sel).empty().append(new Option('SELECCIONE...', '', true, true)); if (data && Array.isArray(data)) data.forEach(d => $(sel).append(new Option((d[field] || '').toUpperCase(), d.id))); }
    function resetLine() {
        $('#in_cod, #in_unit').val(''); $('#in_cant').val('1');
        $('#in_desc_select2').val(null).trigger('change');
        curProd = null;
        setTimeout(() => { if (config?.texboxpredeterminado === 'CANTIDAD') $('#in_cant').focus().select(); else $('#in_cod').focus().select(); }, 150);
    }

    init();

    return {
        addItem: addItemInternal,
        updateItem: updateItemInternal,
        grabar: grabar,
        rem: (i) => { cart.splice(i, 1); renderGrid(); },
        abrirBuscadorCliente: () => { if (pedidoOrigenId || presupuestoOrigenId) return; $('#mod_search_cli').modal('show'); buscarClienteModal(''); },
        abrirBuscadorArticulo: () => { $('#mod_search_art').modal('show'); buscarArticuloModal(''); },
        seleccionarDesdeModal: (type, obj) => { if (type === 'CLI') { setCliente(obj); $('#mod_search_cli').modal('hide'); } else { setProducto(obj); addItemInternal(); $('#mod_search_art').modal('hide'); } },
        toggleTheme: () => { $('body').toggleClass('light-mode'); const isL = $('body').hasClass('light-mode'); $('#btnThemeToggle').html(isL ? '<i class="fas fa-moon"></i>MODO OSCURO' : '<i class="fas fa-sun"></i>MODO CLARO'); },
        modalGlobal: modalGlobalInternal, intentarCobro: () => { if (cart.length === 0) return; $('#pay_entrega_efectivo').val(''); actualizarAyudaEfectivo(); $('#mod_pay').modal('show'); },
        abrirAsociar: abrirAsociarInternal,
        confirmarAsociacion: confirmarAsociacionInternal,
        abrirLineaManual: abrirLineaManualInternal,
        confirmarLineaManual: confirmarLineaManualInternal,
        nueva: async () => {
            cart = [];
            curProd = null;
            clienteSeleccionado = null;
            associatedDoc = null;
            globalAdj = { desc: 0, reca: 0 };
            pedidoOrigenId = null;
            presupuestoOrigenId = null;

            $('#sel_cliente').val(null).trigger('change').prop('disabled', false);
            $('#badge_fiscal').html('');
            $('#btnAsociarTrigger').hide();

            $('#pay_m1').val(null).trigger('change');
            $('#pay_m2').val(null).trigger('change');
            $('#pay_i1').val('0.00');
            $('#pay_i2').val('0.00');

            $('#in_lista').val(1).trigger('change');

            if (config?.predeterminarcomprobante) {
                const tipoPredeterminado = String(config.predeterminarcomprobante);
                $('#sel_tipo').val($(`#sel_tipo option[value="${tipoPredeterminado}"]`).length ? tipoPredeterminado : '100');
            } else {
                $('#sel_tipo').prop('selectedIndex', 0);
            }

            $('#in_cod').val('');
            $('#in_unit').val('');
            $('#in_cant').val('1');
            $('#in_desc_it').val('0');
            $('#in_desc_select2').val(null).trigger('change');

            $('#tx_n21_1, #tx_n105_1, #tx_ii_final_total, #tx_iva_f, #tx_desc_g, #tx_reca_g, #tx_neto_f, #tx_total_f')
                .html('0,00');

            $('#mod_pay_total').text('$ 0,00');

            renderGrid();
            await aplicarConfiguracionInicial();
            await actualizarProximoNumero();

            setTimeout(() => {
                if (config?.texboxpredeterminado === 'CANTIDAD') {
                    $('#in_cant').focus().select();
                } else {
                    $('#in_cod').focus().select();
                }
            }, 200);
        },

        verFicha: () => {
            if (!clienteSeleccionado) return Swal.fire('Error', 'Seleccione un cliente primero.', 'warning');
            let html = `<div class="text-start fs-7">
                <p><strong>RAZÓN SOCIAL:</strong> ${clienteSeleccionado.razon}</p>
                <p><strong>CUIT/DNI:</strong> ${clienteSeleccionado.numdoc}</p>
                <hr>
                <p><strong>ESTADO CTA. CTE:</strong> ${parseInt(clienteSeleccionado.cuentacorriente) === 1 ? 'HABILITADA' : 'BLOQUEADA'}</p>
                <p><strong>LISTA:</strong> ${clienteSeleccionado.ListaDePRecios}</p>
            </div>`;
            Swal.fire({ title: 'FICHA TÉCNICA', html: html });
        },
    };
})();
