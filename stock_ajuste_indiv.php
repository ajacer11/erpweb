<?php
/**
 * stock_ajuste_indiv.php
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0" style="color: var(--text-main);">AJUSTE DE INVENTARIO INDIVIDUAL</h4>
            <small style="color: var(--text-muted); opacity: 0.75;">Mantenimiento de stock físico y de gestión</small>
        </div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="chkfiscal" checked>
            <label class="form-check-label small" style="color: var(--text-main);" for="chkfiscal">AJUSTE FISCAL</label>
        </div>
    </div>

    <div class="card card-filtros shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-md-2">
                    <label class="form-label-custom">CÓDIGO</label>
                    <input type="text" id="txtcod" class="form-control" placeholder="ENTER para buscar" autocomplete="off">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">BUSCAR POR DESCRIPCIÓN</label>
                    <select id="selectProducto" class="form-select form-select-sm select2-filter" style="width:100%">
                        <option value="">Cargando productos...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">DESCRIPCIÓN DEL PRODUCTO</label>
                    <input type="text" id="txtdescripcion" class="form-control" readonly
                           style="background: var(--input-bg); color: var(--text-main); border-color: var(--input-border);">
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom text-center d-block">ESTADO</label>
                    <div id="badge-estado" class="text-center py-2 px-1 rounded fw-bold"
                         style="font-size: 11px; background: rgba(255,255,255,0.05); color: var(--text-muted); border: 1px solid var(--input-border);">
                        SIN SELECCIONAR
                    </div>
                </div>
                <div class="col-md-3 d-none" id="wrap-cmb-deposito">
                    <label class="form-label-custom">DEPÓSITO A AJUSTAR</label>
                    <select id="cmbDeposito" class="form-select"></select>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-3">
            <div class="card card-filtros h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <label class="form-label-custom">STOCK ACTUAL</label>
                    <input type="text" id="txtstock" class="form-control text-end fw-bold fs-4" readonly value="0.00"
                           style="background: var(--input-bg); color: var(--text-main); border-color: var(--input-border);">
                </div>
            </div>
        </div>

        <div class="col-md-9">
            <div class="card card-filtros h-100">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-custom" style="color: #28a745;">AGREGAR (INGRESO)</label>
                            <input type="number" id="txting" class="form-control form-control-lg text-end" step="0.01" value="0"
                                   style="background: var(--input-bg); color: var(--text-main); border-color: var(--input-border);">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom" style="color: #dc3545;">RESTAR (EGRESO)</label>
                            <input type="number" id="txtegr" class="form-control form-control-lg text-end" step="0.01" value="0"
                                   style="background: var(--input-bg); color: var(--text-main); border-color: var(--input-border);">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">STOCK FINAL</label>
                            <input type="text" id="txtfinal" class="form-control form-control-lg text-end fw-bold" readonly value="0.00"
                                   style="background: var(--input-bg) !important; border: 2px solid var(--accent) !important; color: var(--accent) !important;">
                        </div>
                        <div class="col-12">
                            <label class="form-label-custom">OBSERVACIONES / MOTIVO DEL AJUSTE</label>
                            <textarea id="txtObservacion" class="form-control" rows="2"
                                      placeholder="Ej: ajuste por rotura, error de carga, etc."
                                      style="background: var(--input-bg); color: var(--text-main); border-color: var(--input-border);"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4 p-3 rounded" style="border: 1px solid var(--warning); background: rgba(255, 193, 7, 0.08);">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-info-circle fs-4" style="color: var(--warning); flex-shrink: 0;"></i>
            <div style="font-size: 11px; color: var(--text-muted);">
                <span style="color: var(--text-main); font-weight: bold;">REGLA DE NEGOCIO:</span>
                Al buscar un producto, el sistema lo <strong style="color: var(--warning);">deshabilita</strong> automáticamente
                para evitar ventas mientras se realiza el ajuste. Una vez guardado o liberado manualmente, volverá a estar disponible.
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-outline-warning border-0" id="btnLiberar">
            <i class="fas fa-unlock me-2"></i>CANCELAR Y LIBERAR
        </button>
        <button class="btn btn-info fw-bold rounded-pill px-5 shadow" id="btnGrabar">
            <i class="fas fa-save me-2"></i>CONFIRMAR AJUSTE
        </button>
    </div>
</div>

<script>
(function () {
    let productoActivo = null;

    async function cargarSelect2() {
        try {
            const resp = await fetch('api_stock_ajuste.php?action=list_all', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            const productos = res.data || [];

            const $sel = $('#selectProducto');
            $sel.empty().append(new Option('Escriba para buscar...', '', true, false));
            productos.forEach(p => {
                $sel.append(new Option(p.descripcion, p.codigo, false, false));
            });

            $sel.select2({
                theme: 'default',
                placeholder: 'Escriba para buscar...',
                allowClear: true,
                language: { noResults: () => 'Sin resultados' }
            });
        } catch (e) {
            console.error('Error cargando productos para Select2', e);
        }
    }

    $('#selectProducto').on('select2:select', function (e) {
        $('#txtcod').val(e.params.data.id);
        cargarProducto(e.params.data.id);
    });

    $('#selectProducto').on('select2:clear', limpiarForm);

    $('#txtcod').on('keydown', function (e) {
        if (e.key === 'Enter') {
            const cod = $(this).val().trim();
            if (cod) cargarProducto(cod);
        }
    });

    $('#txting, #txtegr').on('input', calcularFinal);
    $('#btnGrabar').click(grabarAjuste);
    $('#btnLiberar').click(liberarProducto);

    // Combo de depósito: solo visible si el tenant tiene multidepósito activado.
    // Se precarga con el depósito de trabajo del usuario, pero queda editable.
    (async function cargarDeposito() {
        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            const estado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
            if (!estado.habilitado) return;

            const [permitidos, miEstado] = await Promise.all([
                fetch('api_depositos.php?action=list_permitidos', { headers }).then(r => r.json()),
                fetch('api_depositos.php?action=mi_estado', { headers }).then(r => r.json())
            ]);
            const cmb = $('#cmbDeposito').empty();
            (permitidos.data || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));
            if (miEstado.id_deposito) cmb.val(miEstado.id_deposito);
            $('#wrap-cmb-deposito').removeClass('d-none');
        } catch (e) { console.error('No se pudo cargar el depósito', e); }
    })();

    async function cargarProducto(codigoIngresado) {
        try {
            const resRef = await fetch(
                `api_referencias.php?action=resolve&code=${encodeURIComponent(codigoIngresado)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const dataRef = await resRef.json();

            if (!dataRef.success) {
                return mostrarError('No se encontró el producto. Verifique el código e intente nuevamente.');
            }

            const codigoReal = dataRef.data.codigo_real;

            const resp = await fetch(
                `api_stock_ajuste.php?action=get_prod&code=${encodeURIComponent(codigoReal)}`,
                { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const res = await resp.json();

            if (res.success && res.data) {
                productoActivo = res.data;

                $('#txtcod').val(productoActivo.codigo);
                $('#txtdescripcion').val(productoActivo.descripcion);
                $('#txtstock').val(parseFloat(productoActivo.stock).toFixed(2));
                $('#txting').val(0);
                $('#txtegr').val(0);

                actualizarUIEstado(productoActivo.habilitado);
                calcularFinal();

                if ($('#selectProducto').val() !== productoActivo.codigo) {
                    $('#selectProducto').val(productoActivo.codigo).trigger('change.select2');
                }

                if (productoActivo.habilitado == 1) {
                    await cambiarEstadoEnDB(0);
                }
            } else {
                mostrarError('No se encontró el producto. Verifique el código e intente nuevamente.', res.error);
                limpiarForm();
            }
        } catch (e) {
            console.error(e);
            mostrarError('No se pudo comunicar con el servidor. Intente nuevamente.');
        }
    }

    function calcularFinal() {
        const actual = parseFloat($('#txtstock').val()) || 0;
        const ing    = parseFloat($('#txting').val())   || 0;
        const egr    = parseFloat($('#txtegr').val())   || 0;
        $('#txtfinal').val((actual + ing - egr).toFixed(2));
    }

    async function cambiarEstadoEnDB(nuevoEstado) {
        if (!productoActivo) return;
        const resp = await fetch('api_stock_ajuste.php?action=set_status', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ codigo: productoActivo.codigo, estado: nuevoEstado })
        });
        const res = await resp.json();
        if (res.success) actualizarUIEstado(nuevoEstado);
    }

    function actualizarUIEstado(estado) {
        const el = $('#badge-estado');
        if (estado == 1) {
            el.text('HABILITADO').css({
                background: 'rgba(40, 167, 69, 0.15)',
                color: '#4ddb6f',
                border: '1px solid #28a745'
            });
        } else {
            el.text('DESHABILITADO').css({
                background: 'rgba(220, 53, 69, 0.15)',
                color: '#ff6b7a',
                border: '1px solid #dc3545'
            });
        }
    }

    async function liberarProducto() {
        if (!productoActivo) return;
        await cambiarEstadoEnDB(1);
        Swal.fire({ icon: 'info', title: 'Producto Liberado', toast: true, position: 'top-end', timer: 2000, showConfirmButton: false });
        limpiarForm();
    }

    function limpiarForm() {
        productoActivo = null;
        $('#txtcod').val('').focus();
        $('#txtdescripcion').val('');
        $('#txtstock').val('0.00');
        $('#txting').val(0);
        $('#txtegr').val(0);
        $('#txtfinal').val('0.00');
        $('#txtObservacion').val('');
        $('#selectProducto').val(null).trigger('change');
        $('#badge-estado').text('SIN SELECCIONAR').css({
            background: 'rgba(255,255,255,0.05)',
            color: 'var(--text-muted)',
            border: '1px solid var(--input-border)'
        });
    }

    async function grabarAjuste() {
        if (!productoActivo) return Swal.fire('Atención', 'Primero busque un producto', 'warning');

        const ing   = parseFloat($('#txting').val()) || 0;
        const egr   = parseFloat($('#txtegr').val()) || 0;
        const final = $('#txtfinal').val();

        if (ing === 0 && egr === 0) return Swal.fire('Atención', 'Ingrese un valor para ajustar', 'warning');
        if (ing > 0  && egr > 0)   return Swal.fire('Error', 'No puede realizar ingreso y egreso simultáneos', 'error');

        const result = await Swal.fire({
            title: '¿Confirmar ajuste?',
            html: `El stock pasará de <b>${parseFloat(productoActivo.stock).toFixed(2)}</b> a <b>${final}</b>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent)',
            confirmButtonText: 'Sí, ajustar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            const data = {
                codigo:      productoActivo.codigo,
                ingreso:     ing,
                egreso:      egr,
                observacion: $('#txtObservacion').val(),
                fiscal:      $('#chkfiscal').is(':checked') ? 1 : 0,
                id_deposito: $('#cmbDeposito').val() || null
            };

            try {
                const resp = await fetch('api_stock_ajuste.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                const res = await resp.json();

                if (res.success) {
                    await Swal.fire('Ajustado', 'El stock fue actualizado correctamente.', 'success');
                    limpiarForm();
                } else {
                    mostrarError('No se pudo guardar el ajuste. Intente nuevamente o consulte al administrador.', res.error);
                }
            } catch (e) {
                console.error(e);
                mostrarError('No se pudo comunicar con el servidor. Intente nuevamente.');
            }
        }
    }

    // Muestra un mensaje amigable al usuario; loguea el detalle técnico en consola
    // y lo incluye en la respuesta (visible en Network) sin exponerlo en el Swal
    function mostrarError(mensajeAmigable, detalleTecnico) {
        if (detalleTecnico) {
            console.error('[DETALLE TÉCNICO]', detalleTecnico);
        }
        Swal.fire({
            icon: 'error',
            title: 'Ocurrió un problema',
            text: mensajeAmigable,
            confirmButtonText: 'Entendido'
        });
    }

    cargarSelect2();
    $('#txtcod').focus();
})();
</script>