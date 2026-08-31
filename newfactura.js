/**
 * newfactura.js - PROTOTIPO DE FACTURACIÓN (REDISEÑO V2)
 * Motor de cálculo, promociones, validaciones y grabado: IDÉNTICO a
 * facturar.js (misma "única fuente de verdad" para precios). Lo que
 * cambia es exclusivamente el render (tarjetas con miniatura + panel de
 * vista previa + planilla contable opcional) y la carga de ítems
 * (un solo buscador código/descripción).
 */

window.bill = (function () {
    let cart = [];
    let curProd = null;
    let maestros = { vendedores: [], medios_pago: [], empresa: null };
    let config = null;
    let clienteSeleccionado = null;
    let associatedDoc = null;
    let globalAdj = { desc: 0, reca: 0 };
    let searchTimer = null;
    let pedidoOrigenId = null;
    let presupuestoOrigenId = null;
    let ticketOrigenId = null;

    let vistaTabla = false;        // false = tarjetas (por defecto), true = planilla contable
    let vistaDetalleImpuestos = false;

    const PERMITIDOS_ASOCIAR = [2, 3, 7, 8, 12, 13, 101]; // Notas de Crédito/Débito que exigen comprobante asociado

    /**
     * Refleja en el botón "Asociar" y en el cartel de aviso si el tipo de
     * comprobante actual necesita un documento asociado y si ya se cargó uno.
     */
    function actualizarEstadoAsociacion(tipo) {
        const $btn = $('#btnAsociarTrigger');
        const $warn = $('#nf2_asoc_warning');

        if (!PERMITIDOS_ASOCIAR.includes(tipo)) {
            $btn.hide().removeClass('nf2-asociar-pend nf2-asociar-ok');
            $warn.removeClass('show');
            return;
        }

        $btn.show();
        if (associatedDoc) {
            $btn.removeClass('nf2-asociar-pend').addClass('nf2-asociar-ok')
                .attr('title', `Asociado: ${associatedDoc.pv}-${associatedDoc.num}`);
            $warn.removeClass('show');
        } else {
            $btn.removeClass('nf2-asociar-ok').addClass('nf2-asociar-pend')
                .attr('title', 'Asociar comprobante (obligatorio)');
            $warn.addClass('show');
        }
    }

    /**
     * FUNCIÓN DE REDONDEO CONTABLE (idéntica a facturar.js)
     */
    function r(val) {
        return Math.round((val + Number.EPSILON) * 100) / 100;
    }

    function esTicketSinIva() {
        const tipoComp = parseInt($('#sel_tipo').val());
        return parseInt(config?.ticketsiniva) === 1 && (tipoComp === 100 || tipoComp === 101);
    }

    /**
     * FUNCIÓN CENTRAL DE CÁLCULO (idéntica a facturar.js - Única Fuente de Verdad)
     */
    function calculateLineData(it) {
        const tktsiniva = esTicketSinIva();
        const effectivePoriva = tktsiniva ? 0 : it.poriva;

        const netounitario = r(it.precio_base);
        const impint = r(it.imp_int_unit);

        const final_original_unit = r(netounitario * (1 + effectivePoriva / 100) + impint);
        const total_discount_money_unit = r((it.pordesc * final_original_unit) / 100);
        const base_neto_iva_unit = r(netounitario * (1 + effectivePoriva / 100));

        let netoUnitEfectivo = 0;
        let ivaUnitEfectivo = 0;
        let impIntEfectivo = impint;

        if (total_discount_money_unit <= base_neto_iva_unit) {
            if (effectivePoriva === 0) {
                netoUnitEfectivo = r(base_neto_iva_unit - total_discount_money_unit);
                ivaUnitEfectivo = 0;
            } else {
                netoUnitEfectivo = r(
                    (base_neto_iva_unit - total_discount_money_unit) /
                    (1 + effectivePoriva / 100)
                );
                ivaUnitEfectivo = r(
                    netoUnitEfectivo * (effectivePoriva / 100)
                );
            }
        } else {
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

    // ============================================================
    //  IMÁGENES DE PRODUCTO
    //  No existe (todavía) una columna de imagen en alb_productos, así
    //  que se usa una convención de archivo: img/productos/{codigo}.jpg
    //  Si el archivo no existe, se cae a un ícono — no rompe nada.
    // ============================================================
    function imgUrlProducto(codigo) {
        // Servido por img_producto.php: valida sesión + que el código sea un
        // producto real del tenant conectado antes de leer el archivo del
        // disco (carpeta separada por CUIT, no expuesta por HTTP directo).
        return 'img_producto.php?codigo=' + encodeURIComponent(codigo);
    }

    function thumbHtml(codigo, extraClass) {
        return `<div class="nf2-thumb ${extraClass || ''}" data-codigo="${codigo}">
            <img src="${imgUrlProducto(codigo)}" loading="lazy"
                 onerror="this.replaceWith(Object.assign(document.createElement('i'), {className:'fas fa-box-open'}))">
        </div>`;
    }

    function abrirFotoGrandeInternal(codigo) {
        $('#foto_grande_titulo').text(codigo);
        $('#foto_grande_sin_imagen').hide();
        $('#foto_grande_img').show().attr('src', imgUrlProducto(codigo));
        $('#mod_foto_grande').modal('show');
    }

    function actualizarPreview(p) {
        if (!p) {
            $('#nf2_preview_img').removeAttr('data-codigo').html('<i class="fas fa-box-open"></i>');
            $('#nf2_preview_title').text('Pasá el mouse sobre un ítem');
            $('#nf2_preview_sub').text('para ver su foto y precio');
            $('#nf2_preview_price').text('');
            return;
        }
        const lista = parseInt($('#in_lista').val()) || p.lista || 1;
        const col = lista === 1 ? 'siniva' : 'siniva' + lista;
        const src = p.precios_cache || p; // items del carrito guardan el producto en precios_cache
        const neto = parseFloat(src[col] ?? p.precio_base ?? 0);
        const poriva = parseFloat(src.poriva ?? p.poriva ?? 0);
        const impint = parseFloat(src.ImpInt ?? p.imp_int_unit ?? 0);
        const alicuota = esTicketSinIva() ? 0 : poriva;
        const final = r(neto * (1 + alicuota / 100) + impint);

        $('#nf2_preview_img').attr('data-codigo', p.codigo)
            .html(`<img src="${imgUrlProducto(p.codigo)}" onerror="this.replaceWith(Object.assign(document.createElement('i'), {className:'fas fa-box-open'}))">`);
        $('#nf2_preview_title').text(p.descripcion || src.descripcion || p.codigo);
        $('#nf2_preview_sub').text(p.codigo + (src.rubro ? ' · ' + src.rubro : ''));
        $('#nf2_preview_price').text('$ ' + m(final));
    }

    /**
     * Inicialización del Módulo
     */
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

                renderCombos();
                renderCart();
                aplicarConfiguracionInicial();

                const urlParams = new URLSearchParams(window.location.search);
                const pedId = urlParams.get('pedidoid');
                const preId = urlParams.get('presupuestoid');
                // NF2_NC_ORIGEN_ID viene resuelto por PHP (newfactura.php lee notacredito=/asociado=
                // de $_GET). No se lee acá por window.location.search: ese valor recién queda
                // actualizado después del pushState de loadModule() en index.php, que corre
                // DESPUÉS de que este script ya arrancó — es una carrera real, no una garantía.
                const ncId = (typeof NF2_NC_ORIGEN_ID !== 'undefined' && NF2_NC_ORIGEN_ID > 0) ? NF2_NC_ORIGEN_ID : null;

                if (pedId) await cargarPedidoDesdeAdmin(pedId);
                if (preId) await cargarPresupuestoDesdeAdmin(preId);
                if (ncId) await cargarTicketParaNC(ncId);

            } else {
                Swal.fire('Error de Carga', res.error || 'No se pudieron obtener los maestros del sistema.', 'error');
            }

            // Select2 de Clientes
            $('#sel_cliente').select2({
                dropdownParent: $('#nf2Shell'),
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

            // Buscador por DESCRIPCIÓN (browsing/fuzzy). El escaneo/código exacto
            // vive en su propio campo #in_cod_exacto — ver setupEvents(). Mezclar
            // ambos en un solo input hacía que, al escanear rápido, Select2 a
            // veces ya tuviera resaltado un resultado parcial (LIKE %código%) y
            // el ENTER confirmaba ESE en vez del código exacto escaneado.
            $('#in_buscar').select2({
                dropdownParent: $('#nf2Shell'),
                width: '100%',
                placeholder: "Buscar por descripción...",
                ajax: {
                    url: 'api_facturar_gets.php?action=buscar_productos',
                    dataType: 'json',
                    delay: 250,
                    headers: h,
                    data: params => ({ q: params.term }),
                    processResults: res => ({ results: res.data.map(p => ({ id: p.codigo, text: `${p.codigo} — ${p.descripcion}`, full: p })) })
                },
                templateResult: opt => {
                    if (!opt.id) return opt.text;
                    const p = opt.full;
                    const lista = parseInt($('#in_lista').val()) || 1;
                    const col = lista === 1 ? 'siniva' : 'siniva' + lista;
                    const neto = parseFloat(p[col] || 0);
                    const poriva = esTicketSinIva() ? 0 : parseFloat(p.poriva || 0);
                    const final = neto * (1 + (poriva / 100)) + parseFloat(p.ImpInt || 0);
                    const $opt = $(`
                        <div class="nf2-opt">
                            ${thumbHtml(p.codigo, 'nf2-thumb-mini')}
                            <div class="nf2-opt-txt">
                                <span class="nf2-opt-cod">${p.codigo}</span>
                                <span class="nf2-opt-desc">${p.descripcion}</span>
                            </div>
                            <span class="nf2-opt-price">$ ${final.toFixed(2)}</span>
                        </div>
                    `);
                    $opt.on('mouseenter', () => actualizarPreview(p));
                    return $opt;
                },
                templateSelection: opt => opt.text || opt.full?.descripcion || ''
            }).on('select2:select', e => {
                // Elegir de la lista agrega directo (mismo criterio que el buscador ampliado)
                setProducto(e.params.data.full);
                addItemInternal();
            });

            setupEvents();
            renderCart();
        } catch (e) {
            console.error("Error init bill:", e);
            Swal.fire('Error de Comunicación', 'No se pudo procesar la respuesta del servidor. ' + e.message, 'error');
        }
    }

    /**
     * Escaneo/tipeo de CÓDIGO EXACTO (#in_cod_exacto): resuelve alias
     * (referenciasproductos, "REGLA DE ORO" del manual) y después busca ese
     * código resuelto con coincidencia EXACTA — nunca por parecido. O
     * encuentra ese producto puntual, o avisa que no existe: no hay
     * término medio, a diferencia del buscador por descripción que sí es
     * fuzzy a propósito.
     */
    async function intentarAgregarPorCodigoExacto(code) {
        const h = { 'X-Requested-With': 'XMLHttpRequest' };
        const $campo = $('#in_cod_exacto');
        try {
            const resRef = await fetch(`api_referencias.php?action=resolve&code=${encodeURIComponent(code)}`, { headers: h }).then(r => r.json());
            const realCode = resRef.data?.codigo_real || code;

            const res = await fetch(`api_facturar_gets.php?action=buscar_codigo_exacto&q=${encodeURIComponent(realCode)}`, { headers: h }).then(r => r.json());
            if (res.success && res.data.length > 0) {
                setProducto(res.data[0]);
                addItemInternal();
            } else {
                Swal.fire({
                    toast: true, position: 'top-end', icon: 'error',
                    title: `Código [${code}] no encontrado`,
                    showConfirmButton: false, timer: 2200, background: 'var(--bg-card)', color: 'var(--text-main)'
                });
            }
        } catch (err) {
            Swal.fire('Error de Red', 'No se pudo consultar el código del producto.', 'error');
        } finally {
            $campo.val('').focus();
        }
    }

    /**
     * REGLA: Facturación de Pedidos (idéntico a facturar.js)
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

            renderCart();
            Swal.close();

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo importar el pedido: ' + e.message, 'error');
        }
    }

    /**
     * REGLA: Facturación de PRESUPUESTOS (idéntico a facturar.js)
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

            renderCart();
            Swal.close();

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo importar el presupuesto: ' + e.message, 'error');
        }
    }

    /**
     * REGLA: Nota de Crédito a partir de un comprobante existente
     * (?notacredito=/?asociado=<id de tickets.id>). Trae cliente + ítems +
     * el tipo de NC correcto (endpoint get_ticket_para_nc, que ya calcula el
     * tipo antagónico: Factura A/Nota Débito A → NC A, etc.) y deja la
     * asociación ya confirmada — el usuario solo revisa/ajusta y cobra.
     */
    async function cargarTicketParaNC(id) {
        Swal.fire({
            title: 'Cargando Comprobante...',
            text: 'Buscando datos para la Nota de Crédito',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const h = { 'X-Requested-With': 'XMLHttpRequest' };
            const res = await fetch(`api_facturar_gets.php?action=get_ticket_para_nc&id=${id}`, { headers: h }).then(r => r.json());

            if (!res.success) {
                Swal.fire('Error', res.error, 'error').then(() => {
                    loadModule('reporte_ventas_comprobantes.php');
                });
                return;
            }

            ticketOrigenId = id;
            const tk = res.ticket;

            const cli = {
                id: tk.cliente,
                razon: tk.razon,
                numdoc: tk.numdoc,
                condicionfiscal: tk.condicionfiscal,
                vendedorid: tk.vendedorid,
                ListaDePRecios: tk.lista_original_cli,
                domicilio: tk.domicilio,
                cuentacorriente: tk.cuentacorriente
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
                    pordesc: 0,
                    precios_cache: {
                        codigo: it.producto, descripcion: it.descripcion, poriva: it.poriva, ImpInt: it.impint,
                        preciocosto: it.preciocosto, rubro: it.rubro, marca: it.marca, controlastock: it.controlastock,
                        siniva: it.siniva, siniva2: it.siniva2, siniva3: it.siniva3, siniva4: it.siniva4, siniva5: it.siniva5, siniva6: it.siniva6, siniva7: it.siniva7, siniva8: it.siniva8
                    }
                };
            });

            // La asociación ya se conoce con certeza (viene del propio ticket, no hay que
            // pedirle al usuario que la tipee a mano en el modal) — se deja confirmada
            // antes de disparar el 'change' de tipo, para que el badge nazca en verde.
            associatedDoc = {
                pv: String(tk.PtoVta).padStart(4, '0'),
                num: String(tk.numcomp).padStart(8, '0'),
                fecha: tk.fecha.split(' ')[0],
                tipo: parseInt(tk.tipocomp)
            };

            $('#sel_tipo').val(res.tipo_nc).trigger('change');

            if (res.items.length > 0) $('#in_lista').val(res.items[0].lista).trigger('change');

            renderCart();
            Swal.close();

        } catch (e) {
            Swal.fire('Error Crítico', 'No se pudo importar el comprobante: ' + e.message, 'error');
        }
    }

    /**
     * Aplica los parámetros de la tabla configuración (idéntico a facturar.js)
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
                else $('#in_cod_exacto').focus();
            }
        });

        $('#in_cant').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#in_cod_exacto').focus();
            }
        });

        // Campo de escaneo/tipeo de código EXACTO — independiente del buscador
        // por descripción, ver intentarAgregarPorCodigoExacto().
        $('#in_cod_exacto').on('keypress', async function (e) {
            if (e.which !== 13) return;
            e.preventDefault();
            const code = $(this).val().trim();
            if (!code) return;
            await intentarAgregarPorCodigoExacto(code);
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

            if (!PERMITIDOS_ASOCIAR.includes(tipo)) associatedDoc = null;
            actualizarEstadoAsociacion(tipo);

            const check = billValidator.validateInvoiceType(tipo, clienteSeleccionado, maestros.empresa);
            if (!check.isValid) {
                actualizarTipoPorDefecto();
            }

            renderCart();
            actualizarProximoNumero();

            setTimeout(() => {
                if (config?.texboxpredeterminado === 'CANTIDAD') $('#in_cant').focus().select();
                else $('#in_cod_exacto').focus();
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
        });

        // Clic en cualquier miniatura (tarjetas, buscadores, panel de vista previa) -> foto grande en modal
        $(document).on('click', '.nf2-thumb, #nf2_preview_img', function () {
            const codigo = $(this).data('codigo');
            if (codigo) abrirFotoGrandeInternal(codigo);
        });

        // Vista previa al pasar el mouse por un ítem — tanto en la vista de
        // tarjetas (#nf2_cards) como en la planilla contable (#grid_body),
        // ambas rendereadas con data-idx en cada fila/tarjeta.
        const mostrarPreviewDeIdx = function () {
            const idx = parseInt($(this).data('idx'));
            const it = cart[idx];
            if (it) actualizarPreview({ codigo: it.codigo, descripcion: it.descripcion, precios_cache: it.precios_cache, lista: it.lista });
        };
        $('#nf2_cards').on('mouseenter', '.nf2-item-card', mostrarPreviewDeIdx);
        $('#nf2_cards').on('mouseleave', () => actualizarPreview(null));
        $('#grid_body').on('mouseenter', 'tr', mostrarPreviewDeIdx);
        $('#grid_body').on('mouseleave', () => actualizarPreview(null));
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

    /**
     * Ficha del cliente, siempre visible en la columna lateral (reemplaza
     * tener que abrir el modal "Ver ficha" para ver estos datos básicos).
     */
    function actualizarFichaCliente(obj) {
        if (!obj) {
            $('#nf2_cliente_empty').show();
            $('#nf2_cliente_info').hide();
            return;
        }
        $('#nf2_cliente_empty').hide();
        $('#nf2_cliente_info').show();

        $('#nf2_cli_nombre').text(obj.razon || '');
        $('#nf2_cli_doc').text(obj.numdoc ? `CUIT/DNI: ${obj.numdoc}` : '');
        $('#nf2_cli_cond').text(obj.condicionfiscal || '-');

        const ctaOk = parseInt(obj.cuentacorriente) === 1;
        $('#nf2_cli_ctacte').text(ctaOk ? 'HABILITADA' : 'BLOQUEADA').attr('class', ctaOk ? 'ok' : 'bloqueada');

        $('#nf2_cli_lista').text(obj.ListaDePRecios ? `LISTA ${obj.ListaDePRecios}` : '-');
        $('#nf2_cli_domicilio').text(obj.domicilio || '-');
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

        renderCart();
        $('#badge_fiscal').html(`${obj.condicionfiscal}<br>${obj.numdoc || ''}`);
        actualizarProximoNumero();
        actualizarFichaCliente(obj);
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
        renderCart();
    }

    async function buscarArticuloModal(q) {
        const res = await fetch(`api_facturar_gets.php?action=buscar_productos&q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
        const $b = $('#tbody_mod_art').empty();
        const lista = parseInt($('#in_lista').val()) || 1;
        const col = lista === 1 ? 'siniva' : 'siniva' + lista;

        res.data.forEach(p => {
            const neto = parseFloat(p[col] || 0);
            const poriva = esTicketSinIva() ? 0 : parseFloat(p.poriva || 0);
            const final = neto * (1 + (poriva / 100)) + parseFloat(p.ImpInt || 0);
            const $row = $(`
                <tr>
                    <td>${thumbHtml(p.codigo, 'nf2-thumb-mini')}</td>
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
            $row.on('mouseenter', () => actualizarPreview(p));
            $b.append($row);
        });
    }

    /**
     * Fija el producto activo y actualiza el panel de vista previa.
     */
    function setProducto(p) {
        curProd = p;
        if ($('#in_buscar').val() != p.codigo) {
            const opt = new Option(`${p.codigo} — ${p.descripcion}`, p.codigo, true, true);
            $('#in_buscar').append(opt).trigger('change');
        }
        actualizarPreview(p);
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

        if (parseInt(config?.stocknegativo) === 0 && stockActual < (cant + cantEnGrilla)) {
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

        if (parseInt(config?.agruparproductos) === 1 && existIdx !== -1) {
            cart[existIdx].cantidad += cant;
        } else {
            cart.push({
                codigo: curProd.codigo, descripcion: curProd.descripcion,
                cantidad: cant, precio_base: parseFloat(curProd[col] || 0),
                poriva: parseFloat(curProd.poriva || 0), imp_int_unit: parseFloat(curProd.ImpInt || 0),
                lista: lista, costo: parseFloat(curProd.preciocosto || 0),
                pordesc: 0, precios_cache: curProd,
                promo_label: null, promo_id: null
            });
        }
        evaluarPromociones().then(() => renderCart());
        resetLine();
    }

    /**
     * Evalúa promociones vigentes (idéntico a facturar.js)
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

    /**
     * Renderiza AMBAS vistas (tarjetas y planilla) y los totales.
     * Cuál de las dos se ve la controla el CSS (.show / display).
     */
    function renderCart() {
        renderCards();
        renderTabla();
        renderTotales();
        $('#nf2_list_count').text(cart.length + (cart.length === 1 ? ' ítem' : ' ítems'));
    }

    function renderCards() {
        const $c = $('#nf2_cards').empty();

        if (cart.length === 0) {
            $c.append(`<div class="nf2-empty-list"><i class="fas fa-basket-shopping"></i><span>Todavía no agregaste ningún ítem</span></div>`);
            return;
        }

        const canEdit = parseInt(config?.modificarprecioenventas) === 1;
        const canModifLista = parseInt(config?.modiflistas) === 1;

        cart.forEach((it, i) => {
            const d = calculateLineData(it);
            const promoBadge = it.promo_label ? `<span class="nf2-promo-chip" title="${it.promo_label}">🏷️ PROMO</span>` : '';

            const listaHtml = canModifLista
                ? `<select class="nf2-item-lista-sel" onchange="bill.updateItem(${i}, 'lista', this.value)">
                       ${[1, 2, 3, 4, 5, 6, 7, 8].map(n => `<option value="${n}" ${it.lista == n ? 'selected' : ''} style="background:var(--input-bg);color:var(--text-main);">L${n}</option>`).join('')}
                   </select>`
                : `<span style="font-size:10px;color:var(--text-muted);">L${it.lista}</span>`;

            const finalHtml = canEdit
                ? `<input type="number" step="0.01" class="nf2-item-final-input" value="${d.totalUnit.toFixed(2)}" onblur="bill.updateItem(${i}, 'precio_final', this.value)">`
                : `<span class="nf2-item-total">${m(d.totalUnit)}</span>`;

            const $row = $(`
                <div class="nf2-item-card" data-idx="${i}">
                    ${thumbHtml(it.codigo)}
                    <div class="nf2-item-info">
                        <div class="nf2-item-cod">${it.codigo}${promoBadge}</div>
                        <div class="nf2-item-desc" title="${it.descripcion}">${it.descripcion}</div>
                        <div class="nf2-item-sub">${listaHtml} &nbsp;·&nbsp; Unit: $ ${m(d.totalUnit)}</div>
                    </div>
                    <input type="number" class="nf2-item-qty" value="${it.cantidad}" title="Cantidad" onchange="bill.updateItem(${i}, 'cant', this.value)">
                    <input type="number" class="nf2-item-desc-pct" value="${it.pordesc}" title="% Descuento" onchange="bill.updateItem(${i}, 'pordesc', this.value)">
                    ${finalHtml}
                    <span class="nf2-item-total">${m(d.totalFinal)}</span>
                    <button type="button" class="nf2-item-remove" onclick="bill.rem(${i})" title="Quitar"><i class="fas fa-trash"></i></button>
                </div>
            `);
            $c.append($row);
        });
    }

    function renderTabla() {
        const $h = $('#grid_head').empty();
        $h.append(`
            <tr>
                <th width="50" class="text-center">CANT.</th>
                <th width="90">CÓDIGO</th>
                <th>DESCRIPCIÓN</th>
                <th width="35">LIS.</th>
                <th width="70" class="text-end">NETO U.</th>
                <th width="70" class="text-end">I.I. U.</th>
                <th width="70" class="text-end">IVA U.</th>
                <th width="70" class="text-end">FINAL U.</th>
                <th width="55" class="text-center">%DESC</th>
                <th width="70" class="text-end">$DESC</th>
                <th width="75" class="text-end">NETO T.</th>
                <th width="70" class="text-end">I.I. T.</th>
                <th width="70" class="text-end">IVA T.</th>
                <th width="80" class="text-end">TOTAL F.</th>
                <th width="30"></th>
            </tr>
        `);

        const $b = $('#grid_body').empty();
        const canEdit = parseInt(config?.modificarprecioenventas) === 1;
        const canModifLista = parseInt(config?.modiflistas) === 1;

        cart.forEach((it, i) => {
            const d = calculateLineData(it);
            $b.append(`
                <tr data-idx="${i}">
                    <td><input type="number" value="${it.cantidad}" style="text-align:center;color:var(--info);font-weight:700;" onchange="bill.updateItem(${i}, 'cant', this.value)"></td>
                    <td style="text-align:left;font-weight:700;">${it.codigo}</td>
                    <td style="text-align:left;">${it.descripcion}</td>
                    <td class="text-center">
                        ${canModifLista ?
                    `<select style="background:transparent;border:none;color:var(--text-main);font-size:10px;" onchange="bill.updateItem(${i}, 'lista', this.value)">
                                ${[1, 2, 3, 4, 5, 6, 7, 8].map(num => `<option value="${num}" ${it.lista == num ? 'selected' : ''} style="background:var(--input-bg);color:var(--text-main);">${num}</option>`).join('')}
                            </select>` :
                    `<span style="color:var(--text-muted);">${it.lista}</span>`
                }
                    </td>
                    <td>${canEdit ? `<input type="number" step="0.01" value="${d.netoUnit.toFixed(2)}" onblur="bill.updateItem(${i}, 'precio_base', this.value)">` : `<span style="color:var(--text-muted);">${m(d.netoUnit)}</span>`}</td>
                    <td style="text-align:right;color:var(--text-muted);">${m(d.impIntUnit)}</td>
                    <td style="text-align:right;color:var(--text-muted);">${m(d.ivaUnit)}</td>
                    <td>${canEdit ? `<input type="number" step="0.01" value="${d.totalUnit.toFixed(2)}" onblur="bill.updateItem(${i}, 'precio_final', this.value)">` : `<span style="color:var(--accent);font-weight:700;">${m(d.totalUnit)}</span>`}</td>
                    <td><input type="number" value="${it.pordesc}" style="text-align:center;color:var(--warning);" onchange="bill.updateItem(${i}, 'pordesc', this.value)"></td>
                    <td><input type="number" value="${d.discountMoneyTotal.toFixed(2)}" style="color:var(--warning);" onchange="bill.updateItem(${i}, 'descS', this.value)"></td>
                    <td style="text-align:right;color:var(--text-muted);">${m(d.netoTotal)}</td>
                    <td style="text-align:right;color:var(--text-muted);">${m(d.impIntTotal)}</td>
                    <td style="text-align:right;color:var(--text-muted);">${m(d.ivaTotal)}</td>
                    <td style="text-align:right;color:var(--accent);font-weight:700;">${m(d.totalFinal)}</td>
                    <td class="text-center"><i class="fas fa-trash cursor-pointer" style="color:var(--danger);opacity:.6;" onclick="bill.rem(${i})"></i></td>
                </tr>
            `);
        });
    }

    function renderTotales() {
        let s21 = 0, s105 = 0, s0 = 0, tII = 0, tIva = 0;
        cart.forEach(it => {
            const d = calculateLineData(it);
            if (d.effectiveAlicuota === 21) s21 += d.netoTotal;
            else if (d.effectiveAlicuota === 10.5) s105 += d.netoTotal;
            else if (d.effectiveAlicuota === 0) s0 += d.netoTotal;
            tIva += d.ivaTotal;
            tII += d.impIntTotal;
        });

        const subtotalGral = r(s21 + s105 + s0 + tIva + tII);
        const totalConAjustes = r(subtotalGral - globalAdj.desc + globalAdj.reca);

        $('#tx_n21_1').text(m(s21));
        $('#tx_n105_1').text(m(s105));
        $('#tx_ii_final_total').text(m(tII));
        $('#tx_iva_f').text(m(tIva));
        $('#tx_desc_g').text(m(globalAdj.desc));
        $('#tx_reca_g').text(m(globalAdj.reca));
        $('#tx_neto_f').text(m(s21 + s105 + s0));
        $('#tx_total_f').text(m(totalConAjustes));

        $('#mod_pay_total').text('$ ' + m(totalConAjustes));
        $('#pay_i1').val(totalConAjustes.toFixed(2));
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

        // Códigos AFIP: 2/7/12 son Nota de DÉBITO (A/B/C), 3/8/13 son Nota de CRÉDITO
        // (A/B/C) — antes ambos se etiquetaban "N. CRÉDITO", lo que llevaba a emitir el
        // comprobante fiscal equivocado si alguien elegía el 2/7/12 pensando que acreditaba.
        if (empresaEsRI) {
            if (clienteEsRIMono) {
                tiposPermitidos = [{ id: 1, n: 'FACTURA A' }, { id: 100, n: 'TICKET' }];
                if ((parseInt(config?.admitendc) == 1 || ticketOrigenId) && !pedidoOrigenId && !presupuestoOrigenId) {
                    tiposPermitidos.push({ id: 2, n: 'N. DÉBITO A' }, { id: 3, n: 'N. CRÉDITO A' });
                }
            } else {
                tiposPermitidos = [{ id: 6, n: 'FACTURA B' }, { id: 100, n: 'TICKET' }];
                if ((parseInt(config?.admitendc) == 1 || ticketOrigenId) && !pedidoOrigenId && !presupuestoOrigenId) {
                    tiposPermitidos.push({ id: 7, n: 'N. DÉBITO B' }, { id: 8, n: 'N. CRÉDITO B' });
                }
            }
        } else {
            tiposPermitidos = [{ id: 11, n: 'FACTURA C' }, { id: 100, n: 'TICKET' }];
            if ((parseInt(config?.admitendc) == 1 || ticketOrigenId) && !pedidoOrigenId && !presupuestoOrigenId) {
                tiposPermitidos.push({ id: 12, n: 'N. DÉBITO C' }, { id: 13, n: 'N. CRÉDITO C' });
            }
        }

        if (parseInt(config?.admitendc) == 1 || pedidoOrigenId || presupuestoOrigenId || ticketOrigenId) {
            tiposPermitidos.push({ id: 101, n: 'TICKET CRÉDITO' });
        }

        if (pedidoOrigenId || presupuestoOrigenId) {
            tiposPermitidos = tiposPermitidos.filter(t => [1, 6, 11, 100, 101].includes(t.id));
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
        if (res.success) $('#bill_nro_label').text(pdvLabel() + '-' + res.proximo);
    }

    /**
     * Punto de venta REAL del tenant (viene de la sesión vía maestros.empresa.pdv,
     * ver api_facturar_gets.php?action=get_maestros). Antes se mostraba "0004"
     * fijo en el número de comprobante y se mandaba pv:4 al grabar — un resto de
     * cuando esto era una constante de prueba; el 4 era solo el valor por
     * defecto del fallback del backend, no el PDV real de cada empresa.
     */
    function pdvLabel() {
        return String(maestros.empresa?.pdv ?? 0).padStart(4, '0');
    }

    function updateItemInternal(index, field, value) {
        const it = cart[index];
        const val = parseFloat(value) || 0;

        if (field === 'cant') {
            const stockActual = parseFloat(it.precios_cache.stock || it.precios_cache.Stock || 0);
            if (parseInt(config?.stocknegativo) === 0 && val > stockActual) {
                Swal.fire('Stock Insuficiente', `No puede superar las ${stockActual} unidades disponibles.`, 'error');
                renderCart(); return;
            }
            it.cantidad = val;
            evaluarPromociones().then(() => renderCart());
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
                const tktsiniva = esTicketSinIva();
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
                it.precio_base = r(Math.max(0, nuevoBase));
                it.pordesc = 0;
                it.promo_label = null; it.promo_id = null;
            }
        }
        else if (field === 'pordesc') {
            const lista = it.lista || 1;
            const maxDesc = parseFloat(it.precios_cache['maxdescl' + lista] || 100);
            if (val > maxDesc) {
                Swal.fire('Descuento Excedido', `El descuento máximo para este producto en Lista ${lista} es ${maxDesc}%`, 'warning');
                renderCart(); return;
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
                renderCart(); return;
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

        renderCart();
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
        $('#asoc_pv').val(pdvLabel());
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
        actualizarEstadoAsociacion(parseInt($('#sel_tipo').val()));
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
                // El 0.00 por defecto complicaba escribir encima (había que borrarlo a mano).
                // Seleccionar todo el contenido al enfocar/clickear permite reemplazarlo directo.
                $perc.add($fix).on('focus click', function () { this.select(); });
                $perc.trigger('focus');
            },
            preConfirm: () => { return r(parseFloat($('#adj_fix').val()) || 0); }
        }).then((result) => {
            if (result.isConfirmed) {
                if (type === 'DESC') globalAdj.desc = result.value;
                else globalAdj.reca = result.value;
                renderCart();
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
            pv: maestros.empresa?.pdv,
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
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Imprimir',
                    cancelButtonText: 'Cerrar',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        const fmt = config?.formatoimpresion || 'A4';
                        const urlFmt = (fmt === '80' || fmt === '58') ? 'ticket' : 'A4';
                        window.open(`imprimir_ticket.php?id=${res.id_ticket}&format=${urlFmt}`, '_blank');
                    }

                    const modalEl = document.getElementById('mod_pay');
                    if (modalEl) {
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }

                    // "Imprimir Remito automáticamente tras facturar" (Configuración General):
                    // lleva directo a la pantalla de Imprimir Envíos con el cliente ya
                    // seleccionado, para completar bultos/valor declarado e imprimir la
                    // etiqueta/remito de transporte. Solo aplica a ventas de mercadería
                    // (Factura A/B/C o Ticket), no a Notas de Crédito/Débito.
                    const tipoRecienEmitido = parseInt(dataPayload.tipo_comp);
                    const aplicaRemitoAuto = parseInt(config?.imprimeremitoenviopostfactura) === 1
                        && [1, 6, 11, 100].includes(tipoRecienEmitido);

                    if (aplicaRemitoAuto && dataPayload.cliente_id) {
                        loadModule('imprimir_envios.php?cliente_id=' + dataPayload.cliente_id);
                    } else if (pedidoOrigenId) {
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

    function abrirNuevoClienteInternal() {
        if (!NF2_PUEDE_CREAR_CLIENTE) {
            return Swal.fire('Sin permiso', 'Tu usuario no tiene permiso de menú para cargar clientes nuevos.', 'warning');
        }
        $('#nc_razon').val('');
        $('#nc_tipodoc').val('DNI');
        $('#nc_numdoc').val('');
        $('#nc_condicionfiscal').val('CONSUMIDOR FINAL');
        $('#nc_lista').val('1');
        $('#nc_domicilio').val('');
        $('#nc_telefono').val('');
        $('#nc_ctacte').prop('checked', false);
        $('#mod_nuevo_cliente').modal('show');
        setTimeout(() => $('#nc_razon').focus(), 400);
    }

    async function confirmarNuevoClienteInternal() {
        const razon = $('#nc_razon').val().trim();
        if (!razon) return Swal.fire('Error', 'La Razón Social es obligatoria.', 'warning');

        const payload = {
            razon: razon,
            tipodoc: $('#nc_tipodoc').val(),
            numdoc: $('#nc_numdoc').val().trim(),
            condicionfiscal: $('#nc_condicionfiscal').val(),
            ListaDePRecios: $('#nc_lista').val(),
            domicilio: $('#nc_domicilio').val().trim(),
            telefono: $('#nc_telefono').val().trim(),
            cuentacorriente: $('#nc_ctacte').is(':checked') ? 1 : 0,
            habilitado: 1
        };

        try {
            const resp = await fetch('api_clientes.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();
            if (!res.success) throw new Error(res.error || 'No se pudo crear el cliente.');

            $('#mod_nuevo_cliente').modal('hide');
            setCliente(res.data);

            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'Cliente creado y seleccionado', showConfirmButton: false, timer: 2200,
                background: 'var(--bg-card)', color: 'var(--text-main)'
            });
        } catch (err) {
            Swal.fire('Error', err.message, 'error');
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
        const netoU = r(totalFinalU / (1 + alicuota / 100));

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

        renderCart();
        $('#mod_manual_line').modal('hide');
        resetLine();
    }

    function m(n) { return n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fillSelect(sel, data, field) { $(sel).empty().append(new Option('SELECCIONE...', '', true, true)); if (data && Array.isArray(data)) data.forEach(d => $(sel).append(new Option((d[field] || '').toUpperCase(), d.id))); }

    function resetLine() {
        $('#in_buscar').val(null).trigger('change');
        $('#in_cod_exacto').val('');
        $('#in_cant').val('1');
        curProd = null;
        actualizarPreview(null);
        setTimeout(() => {
            if (config?.texboxpredeterminado === 'CANTIDAD') $('#in_cant').focus().select();
            else $('#in_cod_exacto').focus();
        }, 150);
    }

    function toggleDetalleImpuestosInternal() {
        vistaDetalleImpuestos = !vistaDetalleImpuestos;
        $('#tax_matrix_detalle').toggleClass('show', vistaDetalleImpuestos);
        $('#lbl_ver_detalle').text(vistaDetalleImpuestos ? 'Ocultar detalle de impuestos' : 'Ver detalle de impuestos');
    }

    function toggleVistaTablaInternal() {
        vistaTabla = !vistaTabla;
        $('#nf2_table_wrap').toggleClass('show', vistaTabla);
        $('#nf2_cards').toggleClass('hide-cards', vistaTabla).css('display', vistaTabla ? 'none' : '');
        $('#lbl_toggle_vista').text(vistaTabla ? 'Ver tarjetas' : 'Ver planilla contable');
    }

    init();

    return {
        addItem: addItemInternal,
        updateItem: updateItemInternal,
        grabar: grabar,
        rem: (i) => { cart.splice(i, 1); renderCart(); },
        abrirBuscadorCliente: () => { if (pedidoOrigenId || presupuestoOrigenId) return; $('#mod_search_cli').modal('show'); buscarClienteModal(''); },
        abrirNuevoCliente: abrirNuevoClienteInternal,
        confirmarNuevoCliente: confirmarNuevoClienteInternal,
        abrirBuscadorArticulo: () => { $('#mod_search_art').modal('show'); buscarArticuloModal(''); },
        seleccionarDesdeModal: (type, obj) => { if (type === 'CLI') { setCliente(obj); $('#mod_search_cli').modal('hide'); } else { setProducto(obj); addItemInternal(); $('#mod_search_art').modal('hide'); } },
        modalGlobal: modalGlobalInternal, intentarCobro: () => { if (cart.length === 0) return; $('#mod_pay').modal('show'); },
        abrirAsociar: abrirAsociarInternal,
        confirmarAsociacion: confirmarAsociacionInternal,
        abrirLineaManual: abrirLineaManualInternal,
        confirmarLineaManual: confirmarLineaManualInternal,
        toggleDetalleImpuestos: toggleDetalleImpuestosInternal,
        toggleVistaTabla: toggleVistaTablaInternal,
        nueva: async () => {
            cart = [];
            curProd = null;
            clienteSeleccionado = null;
            associatedDoc = null;
            globalAdj = { desc: 0, reca: 0 };
            pedidoOrigenId = null;
            presupuestoOrigenId = null;

            $('#sel_cliente').val(null).trigger('change').prop('disabled', false);
            $('#badge_fiscal').html('CONSUMIDOR FINAL');
            actualizarEstadoAsociacion(0);
            actualizarFichaCliente(null);

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

            $('#in_cant').val('1');
            $('#in_cod_exacto').val('');
            $('#in_buscar').val(null).trigger('change');
            actualizarPreview(null);

            renderCart();
            await aplicarConfiguracionInicial();
            await actualizarProximoNumero();

            setTimeout(() => {
                if (config?.texboxpredeterminado === 'CANTIDAD') {
                    $('#in_cant').focus().select();
                } else {
                    $('#in_cod_exacto').focus();
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
