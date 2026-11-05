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
    let percepcionesVenta = { total: 0, lineas: [] }; // ver actualizarPercepciones()
    let config = null;
    let clienteSeleccionado = null;
    let associatedDoc = null;
    let globalAdj = { desc: 0, reca: 0 };
    let searchTimer = null;
    let grabando = false;
    let pedidoOrigenId = null;
    let presupuestoOrigenId = null;
    let ticketOrigenId = null;
    let usarPreciosActuales = false;   // decisión vigente: false=guardado (default), true=actual de lista
    let hayDiferenciaPrecios = false;  // true si al menos una línea difiere (calculado 1 vez al cargar)
    let mostrarTogglePrecios = false;  // visibilidad del botón del toolbar

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

    /**
     * Normaliza un importe tipeado en formato AR ("1.500,50" o "1500,50")
     * o en formato con punto ("1500.50") a un Number. Devuelve NaN si no
     * es un importe válido (evita que un "" o texto suelto llegue al backend).
     */
    function normImporte(raw) {
        if (raw === null || raw === undefined) return NaN;
        let s = String(raw).trim();
        if (s === '') return NaN;
        if (s.includes(',')) {
            s = s.replace(/\./g, '').replace(',', '.');
        }
        if (!/^-?\d+(\.\d+)?$/.test(s)) return NaN;
        return parseFloat(s);
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
     * FUNCIÓN CENTRAL DE CÁLCULO (idéntica a facturar.js - Única Fuente de Verdad)
     */
    function calculateLineData(it) {
        const tktsiniva = esTicketSinIva();
        const effectivePoriva = tktsiniva ? 0 : it.poriva;

        const netounitario = r3(it.precio_base);
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
                const brutoUnit = r(base_neto_iva_unit - total_discount_money_unit);
                netoUnitEfectivo = r(brutoUnit / (1 + effectivePoriva / 100));
                // El IVA es la diferencia: así neto + IVA = precio final exacto (sin centavos de más o de menos).
                ivaUnitEfectivo = r(brutoUnit - netoUnitEfectivo);
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

    // Stock a mostrar en el buscador ('stk' lo arma api_facturar_gets.php: depósito de trabajo si hay
    // multidepósito, si no el total). Entero si no tiene decimales; en rojo si no hay.
    function stkHtml(p) {
        if (p.stk === undefined || p.stk === null) return '';
        const n = parseFloat(p.stk) || 0;
        const txt = Number.isInteger(n) ? n : n.toFixed(2);
        return `<span class="nf2-opt-stk${n <= 0 ? ' sin-stock' : ''}">Stk: ${txt}</span>`;
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

            $('#pay_m1, #pay_m2').select2({
                dropdownParent: $('#mod_pay'),
                width: '65%',
                placeholder: 'BUSCAR MEDIO...',
                minimumResultsForSearch: 0
            });

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
                    cache: false,
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
                            ${stkHtml(p)}
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

            const res = await fetch(`api_facturar_gets.php?action=buscar_codigo_exacto&q=${encodeURIComponent(realCode)}`, { headers: h, cache: 'no-store' }).then(r => r.json());
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
                const colLista = nLista === 1 ? 'siniva' : 'siniva' + nLista;
                return {
                    codigo: it.producto,
                    descripcion: it.descripcion,
                    cantidad: parseFloat(it.cantidad),
                    precio_base: parseFloat(it.preciounit),
                    precio_guardado: parseFloat(it.preciounit),
                    precio_lista_actual: parseFloat(it[colLista] || 0),
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
                $('#in_lista').val(res.items[0].lista);
            }

            evaluarDiferenciaPrecioOrigen('pedido', false);

            if (mostrarTogglePrecios) {
                const rPrecio = await Swal.fire({
                    title: 'Precios actualizados',
                    html: 'Al menos un producto de este pedido cambió de precio desde que se generó.<br>¿Con qué precio querés facturar?',
                    icon: 'question', showCancelButton: true, reverseButtons: true,
                    confirmButtonText: 'Precios ACTUALES de lista',
                    cancelButtonText: 'Precios GUARDADOS en el pedido'
                });
                if (rPrecio.isConfirmed) toggleUsarPreciosActualesInternal();
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
                const colLista = nLista === 1 ? 'siniva' : 'siniva' + nLista;
                return {
                    codigo: it.producto,
                    descripcion: it.descripcion,
                    cantidad: parseFloat(it.cantidad),
                    precio_base: parseFloat(it.preciounit),
                    precio_guardado: parseFloat(it.preciounit),
                    precio_lista_actual: parseFloat(it[colLista] || 0),
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

            if (res.items.length > 0) $('#in_lista').val(res.items[0].lista);

            const fechaValidezVencida = res.presupuesto.fechaentrega
                ? (new Date(res.presupuesto.fechaentrega) < new Date(new Date().toDateString()))
                : false;

            evaluarDiferenciaPrecioOrigen('presupuesto', fechaValidezVencida);

            if (mostrarTogglePrecios) {
                const rPrecio = await Swal.fire({
                    title: 'Presupuesto vencido con precios desactualizados',
                    html: 'La Fecha de Validez de este presupuesto ya pasó y al menos un producto cambió de precio.<br>¿Con qué precio querés facturar?',
                    icon: 'question', showCancelButton: true, reverseButtons: true,
                    confirmButtonText: 'Precios ACTUALES de lista',
                    cancelButtonText: 'Precios GUARDADOS en el presupuesto'
                });
                if (rPrecio.isConfirmed) toggleUsarPreciosActualesInternal();
            }

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
                const res = await fetch(`api_facturar_gets.php?action=get_cliente_por_id&id=${config.clientepredeterminado}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(r => r.json());

                if (res.success && res.data) setCliente(res.data);
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
            actualizarTarjetasPago();
            actualizarBalancePago();
        });

        $('#pay_i1, #pay_i2').on('input change', actualizarBalancePago);

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

        $(document).on('click', '#btnAutocompletarCuitNC', autocompletarClienteNuevoPorCuit);
    }

    /**
     * Datos del cupón (tarjeta, cuotas, lote, cupón) debajo de cada medio de pago de tipo
     * 'Tarjetas'. Si el negocio no cargó tarjetas en Fondos > Tarjetas no se piden: el
     * servidor tampoco las exige en ese caso (tarjetas_helpers.php).
     */
    function actualizarTarjetasPago() {
        const hayCatalogo = (maestros.tarjetas || []).length > 0;
        [1, 2].forEach(n => {
            const medio = maestros.medios_pago.find(x => x.id == $(`#pay_m${n}`).val());
            const esTarjeta = hayCatalogo && medio && medio.tipodecuenta === 'Tarjetas';
            $(`#pay_t${n}`).toggleClass('d-none', !esTarjeta);
            if (!esTarjeta) {
                $(`#pay_t${n}_id`).val(''); $(`#pay_t${n}_cuotas`).val(1);
                $(`#pay_t${n}_lote, #pay_t${n}_cupon`).val('');
            }
        });
    }

    function datosTarjetaPago(n) {
        if ($(`#pay_t${n}`).hasClass('d-none')) return null;
        return {
            id: parseInt($(`#pay_t${n}_id`).val()) || 0,
            cuotas: parseInt($(`#pay_t${n}_cuotas`).val()) || 1,
            lote: $(`#pay_t${n}_lote`).val().trim(),
            cupon: $(`#pay_t${n}_cupon`).val().trim()
        };
    }

    /**
     * Calculadora de vuelto/falta entre MEDIO 1 y MEDIO 2. Ambos importes son
     * de carga libre (nada se autocompleta ni queda readonly) — esto solo
     * muestra, en vivo, cuánto falta cobrar o cuánto vuelto hay que entregar
     * según lo que el cajero ya tipeó. Se muestra con un solo medio elegido
     * (útil para calcular el vuelto de un pago en efectivo) y también con
     * los dos. La validación real de que la suma cierre exacto contra el
     * total sigue haciéndose aparte, al grabar (validateBeforePay en
     * facturar_validaciones.js), así que esto no afecta ese comportamiento.
     */
    function actualizarBalancePago() {
        const m1Sel = $('#pay_m1').val();
        const m2Sel = $('#pay_m2').val();
        $('#pay_balance_helper').toggleClass('d-none', !m1Sel);

        if (!m2Sel) {
            // Sin medio 2 elegido, se limpia el importe para que no quede un
            // valor viejo sumando de más en la validación de total cobrado.
            $('#pay_i2').val('0.00');
        }

        if (!m1Sel) {
            $('#pay_falta').text('$ 0,00');
            $('#pay_sobra').text('$ 0,00');
            return;
        }

        const total = totalDocumento();
        const imp1 = normImporte($('#pay_i1').val());
        const imp2 = m2Sel ? normImporte($('#pay_i2').val()) : 0;
        const aplicado = (Number.isFinite(imp1) ? imp1 : 0) + (Number.isFinite(imp2) ? imp2 : 0);

        const diff = r(total - aplicado);
        if (diff > 0.01) {
            $('#pay_falta').text('$ ' + m(diff));
            $('#pay_sobra').text('$ 0,00');
        } else if (diff < -0.01) {
            $('#pay_falta').text('$ 0,00');
            $('#pay_sobra').text('$ ' + m(Math.abs(diff)));
        } else {
            $('#pay_falta').text('$ 0,00');
            $('#pay_sobra').text('$ 0,00');
        }
    }

    function totalDocumento() {
        return r(totalSinPercepciones() + percepcionesVenta.total);
    }

    function totalSinPercepciones() {
        return r(cart.reduce((acc, it) => acc + calculateLineData(it).totalFinal, 0) - globalAdj.desc + globalAdj.reca);
    }

    // ---- Percepciones (agente de percepción): las calcula el servidor (api_retper.php) con
    // las mismas sumas por alícuota y el mismo prorrateo que se declara a ARCA. Se piden al
    // abrir el cobro; cualquier cambio en el carrito las vuelve a cero hasta el próximo cobro.
    function resetPercepciones() {
        percepcionesVenta = { total: 0, lineas: [] };
        $('#mod_pay_percep').addClass('d-none').empty();
    }

    async function actualizarPercepciones() {
        resetPercepciones();
        const tipo = parseInt($('#sel_tipo').val());
        if (!clienteSeleccionado || !tipo || [100, 101].includes(tipo) || !cart.length) return;
        const s = { sub21: 0, iva21: 0, sub10: 0, iva10: 0, sub0: 0 };
        cart.forEach(it => {
            const d = calculateLineData(it);
            if (d.effectiveAlicuota === 21) { s.sub21 += d.netoTotal; s.iva21 += d.ivaTotal; }
            else if (d.effectiveAlicuota === 10.5) { s.sub10 += d.netoTotal; s.iva10 += d.ivaTotal; }
            else s.sub0 += d.netoTotal;
        });
        try {
            const res = await fetch('api_retper.php?action=percepciones_venta', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ ...s, desc: globalAdj.desc, reca: globalAdj.reca, total: totalSinPercepciones(), cliente_id: clienteSeleccionado.id, tipo_comp: tipo })
            }).then(r => r.json());
            if (!res.success || !res.total) return;
            percepcionesVenta = { total: r(res.total), lineas: res.lineas };
            $('#mod_pay_percep').removeClass('d-none').html('Incluye percepciones: ' + res.lineas.map(l => `${l.nombre} ${l.alicuota}% $ ${m(l.importe)}`).join(' · '));
            $('#mod_pay_total').text('$ ' + m(totalDocumento()));
            $('#pay_i1').val(totalDocumento().toFixed(2));
        } catch (e) {
            console.warn('No se pudieron calcular las percepciones', e);
        }
    }

    function validarCUIT(cuit) {
        cuit = (cuit || '').replace(/[-_]/g, '').trim();
        if (cuit.length !== 11) return false;
        const coeficientes = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        let sum = 0;
        for (let i = 0; i < 10; i++) sum += parseInt(cuit[i]) * coeficientes[i];
        let result = 11 - (sum % 11);
        if (result === 11) result = 0;
        if (result === 10) result = 9;
        return result === parseInt(cuit[10]);
    }

    async function autocompletarClienteNuevoPorCuit() {
        const tipodoc = $('#nc_tipodoc').val();
        const cuit = $('#nc_numdoc').val().replace(/[-_]/g, '').trim();

        if (tipodoc !== 'CUIT') {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccioná "CUIT" como Tipo Doc para poder autocompletar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        }
        if (!validarCUIT(cuit)) {
            return Swal.fire({ icon: 'warning', title: 'CUIT inválido', text: 'Ingresá un CUIT válido de 11 dígitos.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        }

        const btn = document.getElementById('btnAutocompletarCuitNC');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        try {
            const res = await fetch(`api_clientes.php?action=consultar_padron&cuit=${cuit}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(r => r.json());
            if (!res.success) throw new Error(res.error || 'No se pudo consultar el padrón.');

            const d = res.data;
            if (d.razonSocial) $('#nc_razon').val(d.razonSocial);
            if (d.domicilio) $('#nc_domicilio').val(d.domicilio);
            if (d.localidad) $('#nc_localidad').val(d.localidad);
            if (d.provincia) {
                const ALIAS_PROVINCIA_AFIP = {
                    'CIUDAD AUTONOMA BUENOS AIRES': 'CABA',
                    'CAPITAL FEDERAL': 'CABA',
                    'CORDOBA': 'CÓRDOBA'
                };
                const provNorm = ALIAS_PROVINCIA_AFIP[d.provincia.toUpperCase().trim()] || d.provincia;
                $('#nc_provincia').val(provNorm);
            }
            if (d.condicionFiscal) {
                const cf = d.condicionFiscal.toUpperCase();
                const opt = $('#nc_condicionfiscal option').filter((i, o) => o.value.toUpperCase() === cf);
                if (opt.length) $('#nc_condicionfiscal').val(opt.val());
            }

            Swal.fire({ icon: 'success', title: 'Datos completados desde el padrón', toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
        } catch (e) {
            Swal.fire('No se pudo autocompletar', e.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-magic"></i>';
        }
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

    /** Calcula diferencia global (guardado vs. actual) y decide si corresponde mostrar el toggle. */
    function evaluarDiferenciaPrecioOrigen(tipoOrigen, fechaValidezVencida) {
        hayDiferenciaPrecios = cart.some(it =>
            it.codigo !== 'LM' &&
            it.precio_lista_actual !== undefined &&
            Math.abs(it.precio_lista_actual - it.precio_guardado) > 0.005
        );
        mostrarTogglePrecios = (tipoOrigen === 'pedido')
            ? hayDiferenciaPrecios
            : (hayDiferenciaPrecios && !!fechaValidezVencida);

        usarPreciosActuales = false; // arranca siempre en "guardado"
        $('#btnTogglePreciosOrigen').toggle(mostrarTogglePrecios);
        actualizarUiTogglePrecios();
    }

    /** Refleja el estado vigente (guardado/actual) en el botón toggle del toolbar. */
    function actualizarUiTogglePrecios() {
        $('#btnTogglePreciosOrigen')
            .toggleClass('info', usarPreciosActuales)
            .toggleClass('warn', !usarPreciosActuales)
            .html(usarPreciosActuales
                ? '<i class="fas fa-tag"></i>Usando: Precio ACTUAL'
                : '<i class="fas fa-tag"></i>Usando: Precio GUARDADO');
    }

    /** Config general + permiso del usuario: ambos deben estar habilitados. */
    function puedeEditarDescripcion() {
        return parseInt(config?.permitireditardescripcion) === 1 && window.NF2_PUEDE_EDITAR_DESC === true;
    }

    /** Alterna ida y vuelta, ilimitadamente, antes de Cobrar. NO toca pordesc ni lista. */
    function toggleUsarPreciosActualesInternal() {
        usarPreciosActuales = !usarPreciosActuales;
        cart.forEach(it => {
            if (it.codigo === 'LM' || it.precio_lista_actual === undefined) return; // línea manual o agregada después de cargar
            it.precio_base = usarPreciosActuales ? it.precio_lista_actual : it.precio_guardado;
        });
        actualizarUiTogglePrecios();
        renderCart();
    }

    async function buscarArticuloModal(q) {
        const res = await fetch(`api_facturar_gets.php?action=buscar_productos&q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' }).then(r => r.json());
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
                    <td>${p.descripcion} ${stkHtml(p)}</td>
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
                        ${(puedeEditarDescripcion() && it.codigo !== 'LM')
                    ? `<input type="text" class="nf2-item-desc-input" value="${it.descripcion}" title="${it.descripcion}" onblur="bill.updateItem(${i}, 'descripcion', this.value)">`
                    : `<div class="nf2-item-desc" title="${it.descripcion}">${it.descripcion}</div>`}
                        <div class="nf2-item-sub">${listaHtml} &nbsp;·&nbsp; Unit: $ ${m(d.totalUnit)}</div>
                    </div>
                    <div class="nf2-item-field"><span class="nf2-item-field-label">Cantidad</span><input type="number" class="nf2-item-qty" value="${it.cantidad}" title="Cantidad" onchange="bill.updateItem(${i}, 'cant', this.value)"></div>
                    <div class="nf2-item-field"><span class="nf2-item-field-label">% Desc.</span><input type="number" class="nf2-item-desc-pct" value="${it.pordesc}" title="% Descuento" onchange="bill.updateItem(${i}, 'pordesc', this.value)"></div>
                    <div class="nf2-item-field"><span class="nf2-item-field-label">Precio unit.</span>${finalHtml}</div>
                    <div class="nf2-item-field"><span class="nf2-item-field-label">Total</span><span class="nf2-item-total">${m(d.totalFinal)}</span></div>
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
                    <td style="text-align:left;">${(puedeEditarDescripcion() && it.codigo !== 'LM')
                    ? `<input type="text" style="width:100%;background:transparent;border:none;color:var(--text-main);" value="${it.descripcion}" onblur="bill.updateItem(${i}, 'descripcion', this.value)">`
                    : it.descripcion}</td>
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
        $('#tx_total_unidades').text(m(cart.reduce((acc, it) => acc + (parseFloat(it.cantidad) || 0), 0)));

        resetPercepciones();
        $('#mod_pay_total').text('$ ' + m(totalConAjustes));
        $('#pay_i1').val(totalConAjustes.toFixed(2));
        actualizarBalancePago();
    }

    function renderCombos() {
        fillSelect('#pay_v', maestros.vendedores, 'nombre');
        fillSelect('#pay_m1', maestros.medios_pago, 'medio');
        fillSelect('#pay_m2', maestros.medios_pago, 'medio');
        ['#pay_t1_id', '#pay_t2_id'].forEach(sel => {
            const $t = $(sel).empty().append(new Option('TARJETA...', ''));
            (maestros.tarjetas || []).forEach(t => $t.append(new Option(t.descripcion, t.id)));
        });

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

        if (field === 'descripcion') {
            if (puedeEditarDescripcion()) it.descripcion = String(value).trim() || it.descripcion;
            renderCart();
            return;
        }

        const val = parseFloat(value) || 0;

        if (field === 'cant') {
            const stockActual = parseFloat(it.precios_cache.stock || it.precios_cache.Stock || 0);
            if (parseInt(config?.stocknegativo) === 0 && !esNotaCredito() && val > stockActual) {
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
        // Guard de reentrancia: el POST a api_facturar.php puede tardar hasta 45s
        // esperando la respuesta de ARCA. Sin esto, un doble click o doble F10
        // durante esa espera dispara dos facturaciones concurrentes del mismo
        // carrito, cada una con su propio CAE (doble emisión fiscal real).
        if (grabando || cart.length === 0) return;
        grabando = true;
        $('#btnEmitir').prop('disabled', true);
        try {
            await grabarInternal();
        } finally {
            grabando = false;
            $('#btnEmitir').prop('disabled', false);
        }
    }

    async function grabarInternal() {
        const totalDoc = totalDocumento(); // incluye percepciones, si corresponden

        const m2Val = $('#pay_m2').val();
        let imp1Num = normImporte($('#pay_i1').val());
        const imp2Raw = $('#pay_i2').val();
        let imp2Num = (imp2Raw === '' || imp2Raw === null) ? 0 : normImporte(imp2Raw);

        if (isNaN(imp1Num)) {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El importe del Medio de pago 1 no es válido. Use formato 1500.50 o 1500,50.' });
        }
        if (m2Val && isNaN(imp2Num)) {
            return Swal.fire({ icon: 'warning', title: 'Atención', text: 'El importe del Medio de pago 2 no es válido. Use formato 1500.50 o 1500,50.' });
        }

        if (imp1Num < 0 || imp2Num < 0) return Swal.fire('Atención', 'Los importes no pueden ser negativos.', 'warning');
        const diferenciaCentavos = Math.round(imp1Num * 100) + Math.round(imp2Num * 100) - Math.round(totalDoc * 100);
        if (diferenciaCentavos > 0 && totalDoc >= 0) {
            const opciones = {};
            if ($('#pay_m1').val() && Math.round(imp1Num * 100) >= diferenciaCentavos) opciones['1'] = 'Medio de Pago 1: ' + $('#pay_m1 option:selected').text();
            if (m2Val && Math.round(imp2Num * 100) >= diferenciaCentavos) opciones['2'] = 'Medio de Pago 2: ' + $('#pay_m2 option:selected').text();
            if (!Object.keys(opciones).length) return Swal.fire('Atención', 'Ningún medio puede absorber el vuelto sin quedar negativo. Redistribuya los importes.', 'warning');
            const ajuste = await Swal.fire({title: '¿En qué medio ajustar el vuelto?', text: 'Vuelto: $ ' + (diferenciaCentavos / 100).toFixed(2), input: 'select', inputOptions: opciones, showCancelButton: true, confirmButtonText: 'AJUSTAR', cancelButtonText: 'VOLVER'});
            if (!ajuste.isConfirmed) return;
            if (ajuste.value === '1') { imp1Num = (Math.round(imp1Num * 100) - diferenciaCentavos) / 100; $('#pay_i1').val(imp1Num.toFixed(2)); }
            else { imp2Num = (Math.round(imp2Num * 100) - diferenciaCentavos) / 100; $('#pay_i2').val(imp2Num.toFixed(2)); }
            actualizarBalancePago();
        }

        const localCheck = billValidator.validateBeforePay({
            cart: cart,
            clienteSeleccionado: clienteSeleccionado,
            total: m(totalDoc),
            vendedor: $('#pay_v').val(),
            tipoComp: $('#sel_tipo').val(),
            m1: $('#pay_m1').val(),
            m2: m2Val,
            imp1: imp1Num,
            imp2: isNaN(imp2Num) ? 0 : imp2Num,
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

        for (const n of [1, 2]) {
            const t = datosTarjetaPago(n);
            if (t && !t.id) return Swal.fire('Atención', `Elija la tarjeta del medio de pago ${n}.`, 'warning');
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
            mp1: $('#pay_m1').val(), imp1: imp1Num,
            mp2: m2Val, imp2: isNaN(imp2Num) ? 0 : imp2Num,
            tarjeta1: datosTarjetaPago(1), tarjeta2: m2Val ? datosTarjetaPago(2) : null,
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
                let swalOpts;
                if (res.cae_pendiente) {
                    swalOpts = {
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
                    };
                } else if (res.reconciliacion_fallida) {
                    swalOpts = {
                        title: 'CAE OBTENIDO - REVISAR NUMERACIÓN',
                        html: `ARCA autorizó el comprobante N° <b>${res.numcomp}</b>, pero el sistema no pudo terminar de ` +
                              `corregir automáticamente ese número en todos sus registros internos.<br>` +
                              `Detalle: ${res.cae_error || 'Sin detalle'}<br><br>` +
                              `Avise a soporte/administración para verificar manualmente el comprobante antes de reimprimirlo.`,
                        icon: 'error',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Imprimir de todos modos',
                        cancelButtonText: 'Cerrar',
                        reverseButtons: true
                    };
                } else {
                    swalOpts = {
                        title: '¡Éxito!',
                        text: `Comprobante emitido correctamente. ¿Desea imprimirlo ahora?`,
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Imprimir',
                        cancelButtonText: 'Cerrar',
                        reverseButtons: true
                    };
                }
                Swal.fire(swalOpts).then((result) => {
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
                    // Si el usuario no tiene permiso de menú para imprimir_envios.php, NO se
                    // navega ahí: check_access.php trata una URL no autorizada como intento de
                    // acceso indebido y cierra la sesión del usuario a la fuerza (ver
                    // check_access.php, denyAccessAndLog) — un vendedor sin ese permiso quedaba
                    // expulsado del sistema justo después de facturar, sin haber hecho nada malo.
                    const tipoRecienEmitido = parseInt(dataPayload.tipo_comp);
                    const aplicaRemitoAuto = parseInt(config?.imprimeremitoenviopostfactura) === 1
                        && [1, 6, 11, 100].includes(tipoRecienEmitido)
                        && window.NF2_PUEDE_IMPRIMIR_ENVIOS === true;

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
        $('#nc_localidad').val('');
        $('#nc_provincia').val('');
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
            localidad: $('#nc_localidad').val().trim(),
            provincia: $('#nc_provincia').val().trim(),
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
        modalGlobal: modalGlobalInternal, intentarCobro: async () => { if (cart.length === 0) return; await actualizarPercepciones(); actualizarBalancePago(); $('#mod_pay').modal('show'); },
        abrirAsociar: abrirAsociarInternal,
        confirmarAsociacion: confirmarAsociacionInternal,
        abrirLineaManual: abrirLineaManualInternal,
        confirmarLineaManual: confirmarLineaManualInternal,
        toggleDetalleImpuestos: toggleDetalleImpuestosInternal,
        toggleVistaTabla: toggleVistaTablaInternal,
        toggleUsarPreciosActuales: toggleUsarPreciosActualesInternal,
        nueva: async () => {
            cart = [];
            curProd = null;
            clienteSeleccionado = null;
            associatedDoc = null;
            globalAdj = { desc: 0, reca: 0 };
            pedidoOrigenId = null;
            presupuestoOrigenId = null;
            usarPreciosActuales = false;
            hayDiferenciaPrecios = false;
            mostrarTogglePrecios = false;
            $('#btnTogglePreciosOrigen').hide();

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
