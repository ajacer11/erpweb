<?php
/**
 * stock_movimientos.php - REPORTE HISTÓRICO DE MOVIMIENTOS DE STOCK
 * Corregido: Alineación de Select2 en filtros.
 * Actualizado: Filtros automáticos y validación de exportación basada en grilla.
 * FIX: Debounce unificado y timeout consistente.
 */
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    header("Location: index.php");
    exit();
}
require_once 'db.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">MOVIMIENTOS DE STOCK</h4>
            <small class="text-accent opacity-75">Consulta histórica de entradas, salidas y ajustes</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" id="btn-exportar">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR EXCEL
            </button>
            <button class="btn btn-info fw-bold px-4" id="btn-generar">
                <i class="fas fa-search me-2"></i>GENERAR INFORME
            </button>
        </div>
    </div>

    <div class="row g-3">
        <!-- PANEL DE FILTROS -->
        <div class="col-md-12">
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <div class="row g-3">

                        <!-- Fechas y Fiscalidad -->
                        <div class="col-md-3">
                            <label class="form-label-custom">RANGO DE FECHAS</label>
                            <div class="input-group input-group-sm mb-2">
                                <span class="input-group-text bg-dark border-secondary text-muted">DESDE</span>
                                <input type="date" id="fecha-desde" class="form-control trigger-debounce"
                                    value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-dark border-secondary text-muted">HASTA</span>
                                <input type="date" id="fecha-hasta" class="form-control trigger-debounce"
                                    value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mt-3">
                                <label class="form-label-custom">FISCALIDAD</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check small">
                                        <input class="form-check-input filter-auto" type="radio" name="r-fiscal"
                                            id="r-todos" value="todos" checked>
                                        <label class="form-check-label text-white trigger-debounce"
                                            for="r-todos">TODOS</label>
                                    </div>
                                    <div class="form-check small">
                                        <input class="form-check-input filter-auto" type="radio" name="r-fiscal"
                                            id="r-si" value="1">
                                        <label class="form-check-label text-white trigger-debounce"
                                            for="r-si">FISCALES</label>
                                    </div>
                                    <div class="form-check small">
                                        <input class="form-check-input filter-auto" type="radio" name="r-fiscal"
                                            id="r-no" value="0">
                                        <label class="form-check-label text-white trigger-debounce" for="r-no">NO
                                            FISC.</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Origen -->
                        <div class="col-md-3 border-start border-secondary px-4">
                            <label class="form-label-custom">ORIGEN DEL MOVIMIENTO</label>
                            <div class="row g-1">
                                <div class="col-6">
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="todos" checked><label
                                            class="form-check-label text-muted">TODOS</label></div>
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="VENTA"><label
                                            class="form-check-label text-muted">VENTAS</label></div>
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="INGRESO"><label
                                            class="form-check-label text-muted">INGRESOS</label></div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="AJUSTE"><label
                                            class="form-check-label text-muted">AJUSTES</label></div>
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="ENVIO"><label
                                            class="form-check-label text-muted">ENVÍOS</label></div>
                                    <div class="form-check small"><input class="form-check-input filter-auto"
                                            type="radio" name="r-origen" value="DEVOLUCION"><label
                                            class="form-check-label text-muted">DEVOLUC.</label></div>
                                </div>
                            </div>
                            <div class="mt-2">
                                <label class="form-label-custom">REMITO / COMP.</label>
                                <input type="number" id="txt-remito" class="form-control form-control-sm"
                                    placeholder="Opcional">
                            </div>
                        </div>

                        <!-- Filtros avanzados -->
                        <div class="col-md-6 border-start border-secondary px-4">
                            <label class="form-label-custom">FILTROS AVANZADOS</label>
                            <div class="row g-2">
                                <div class="col-md-12">
                                    <div class="input-group input-group-sm mb-2 d-flex">
                                        <div class="input-group-text bg-dark border-secondary">
                                            <input class="form-check-input mt-0" type="checkbox" id="chk-rubro">
                                        </div>
                                        <span class="input-group-text bg-dark border-secondary text-muted"
                                            style="width: 100px;">RUBRO</span>
                                        <div class="flex-grow-1">
                                            <select id="cmb-rubro" class="form-select select2-simple" disabled
                                                style="width: 100%"></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <div class="input-group input-group-sm d-flex">
                                        <div class="input-group-text bg-dark border-secondary">
                                            <input class="form-check-input mt-0" type="checkbox" id="chk-producto">
                                        </div>
                                        <span class="input-group-text bg-dark border-secondary text-muted"
                                            style="width: 100px;">PRODUCTO</span>
                                        <div class="flex-grow-1">
                                            <select id="cmb-producto" class="form-select" disabled
                                                style="width: 100%"></select>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-12 d-none" id="wrap-cmb-deposito-mov">
                                    <div class="input-group input-group-sm d-flex">
                                        <span class="input-group-text bg-dark border-secondary text-muted"
                                            style="width: 100px;">DEPÓSITO</span>
                                        <div class="flex-grow-1">
                                            <select id="cmb-deposito-mov" class="form-select" style="width: 100%">
                                                <option value="">TODOS</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- RESULTADOS -->
        <div class="col-md-9">
            <div class="card card-filtros p-0 overflow-hidden shadow-sm" style="min-height: 500px;">
                <div class="table-responsive" style="max-height: 550px;">
                    <table class="table table-hover align-middle mb-0 small" id="tabla-movimientos">
                        <thead class="sticky-top">
                            <tr>
                                <th>FECHA</th>
                                <th>PRODUCTO</th>
                                <th>CÓDIGO</th>
                                <th>RUBRO</th>
                                <th class="text-center">ENTRADAS</th>
                                <th class="text-center">SALIDAS</th>
                                <th>DETALLE / MOTIVO</th>
                                <th class="text-center">FISC.</th>
                                <th>REMITO</th>
                                <th>USUARIO</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-movimientos">
                            <tr>
                                <td colspan="10" class="text-center p-5 text-muted">Configure los filtros y presione
                                    GENERAR INFORME</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RESUMEN -->
        <div class="col-md-3">
            <div class="card card-filtros shadow-sm border-info mb-3">
                <div class="card-body">
                    <h6 class="text-accent mb-3 border-bottom border-secondary pb-2">RESUMEN DE STOCK</h6>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">TOTAL ENTRADAS:</span>
                        <span class="text-success fw-bold" id="lbl-entradas">0.00</span>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <span class="text-muted small">TOTAL SALIDAS:</span>
                        <span class="text-danger fw-bold" id="lbl-salidas">0.00</span>
                    </div>
                    <div class="mt-3 p-2 bg-dark rounded border border-accent text-center">
                        <div class="text-accent x-small mb-1">BALANCE DEL PERÍODO</div>
                        <div class="fs-4 fw-bold text-white" id="lbl-resultado">0.00</div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
    (function () {

        let reportData = [];
        let debounceTimeout = null;
        let currentController = null;
        let isLoading = false;

        const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

        function debounceGenerarInforme(delay = 500) {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(() => {
                generarInforme();
            }, delay);
        }

        async function init() {
            await cargarFiltrosIniciales();
            configurarEventos();
            generarInforme();

            $('#btn-generar').click(generarInforme);
            $('#btn-exportar').click(exportarExcel);
        }

        async function cargarFiltrosIniciales() {
            const resR = await fetch('api_helpers.php?action=get_rubros', { headers: commonHeaders }).then(r => r.json());
            if (resR.success) resR.data.forEach(r => $('#cmb-rubro').append(new Option(r.rubro, r.rubro)));

            try {
                const estado = await fetch('api_depositos.php?action=estado_global', { headers: commonHeaders }).then(r => r.json());
                if (estado.habilitado) {
                    const dep = await fetch('api_depositos.php?action=list_habilitados', { headers: commonHeaders }).then(r => r.json());
                    (dep.data || []).forEach(d => $('#cmb-deposito-mov').append(new Option(d.nombre, d.id)));
                    $('#wrap-cmb-deposito-mov').removeClass('d-none');
                }
            } catch (e) { console.error('No se pudo cargar depósitos', e); }

            $('#cmb-producto').select2({
                placeholder: 'BUSCAR PRODUCTO...',
                minimumInputLength: 3,
                allowClear: true,
                ajax: {
                    url: 'api_productos.php?action=search',
                    dataType: 'json',
                    delay: 250,
                    headers: commonHeaders,
                    data: params => ({ q: params.term }),
                    processResults: data => ({
                        results: data.data.map(p => ({ id: p.codigo, text: `${p.codigo} - ${p.descripcion}` }))
                    })
                },
                width: '100%'
            });
        }

        function configurarEventos() {

            /* $('.trigger-debounce').on('change input', debounceGenerarInforme); */

    
            $('#cmb-deposito-mov').on('change', generarInforme);

            $('#chk-rubro').change(e => {
                const isChecked = $(e.target).is(':checked');
                $('#cmb-rubro').prop('disabled', !isChecked);
                if (!isChecked) $('#cmb-rubro').val('').trigger('change');
                generarInforme();
            });

            $('#chk-producto').change(e => {
                const isChecked = $(e.target).is(':checked');
                $('#cmb-producto').prop('disabled', !isChecked);
                if (!isChecked) $('#cmb-producto').val(null).trigger('change');
                generarInforme();
            });

            $('#cmb-rubro').on('change', generarInforme);

            $('#cmb-producto').on('change', function () {
                if (!$(this).prop('disabled')) generarInforme();
            });

            $('#txt-remito').on('input', debounceGenerarInforme);
        }

        async function generarInforme() {

            if (isLoading && currentController) {
                currentController.abort();
            }

            const filters = getFilters();

            currentController = new AbortController();
            const signal = currentController.signal;

            isLoading = true;

            $('#tbody-movimientos').html(
                '<tr><td colspan="10" class="text-center p-5">' +
                '<div class="spinner-border text-info"></div><br>Actualizando...</td></tr>'
            );

            try {

                const response = await fetch(
                    'api_stock_movimientos.php?action=get_report',
                    {
                        method: 'POST',
                        headers: {
                            ...commonHeaders,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(filters),
                        signal
                    }
                );

                const resp = await response.json();

                if (!signal.aborted) {

                    if (resp.success) {
                        reportData = resp.data;
                        renderTabla(resp.data);
                        renderTotales(resp.resumen);
                    } else {
                        Swal.fire('Error', resp.error, 'error');
                    }

                }

            } catch (err) {

                if (err.name !== 'AbortError') {
                    console.error(err);
                }

            } finally {
                isLoading = false;
            }
        }

        function getFilters() {
            return {
                desde: $('#fecha-desde').val(),
                hasta: $('#fecha-hasta').val(),
                fiscal: $('input[name="r-fiscal"]:checked').val(),
                origen: $('input[name="r-origen"]:checked').val(),
                remito: $('#txt-remito').val(),
                rubro: $('#chk-rubro').is(':checked') ? $('#cmb-rubro').val() : '',
                producto: $('#chk-producto').is(':checked') ? $('#cmb-producto').val() : '',
                id_deposito: $('#cmb-deposito-mov').val() || ''
            };
        }

        function renderTabla(data) {
            const tbody = $('#tbody-movimientos').empty();
            if (data.length === 0) {
                tbody.append('<tr><td colspan="10" class="text-center p-4">No se encontraron resultados para los filtros aplicados.</td></tr>');
                return;
            }

            data.forEach(m => {
                tbody.append(`
                <tr>
                    <td class="text-nowrap">${m.Fecha}</td>
                    <td><div class="text-truncate" style="max-width: 200px;">${m.Producto || '---'}</div></td>
                    <td class="fw-bold">${m.Codigo}</td>
                    <td><small>${m.Rubro}</small></td>
                    <td class="text-center fw-bold text-success">${parseFloat(m.Entradas).toFixed(2)}</td>
                    <td class="text-center fw-bold text-danger">${parseFloat(m.Salidas).toFixed(2)}</td>
                    <td><small>${m['Detalle Movimiento']}</small></td>
                    <td class="text-center">${m.Fiscal == 1 ? 'SI' : 'NO'}</td>
                    <td class="text-center">${m['Num Remito']}</td>
                    <td><small>${m.Usuario}</small></td>
                </tr>
            `);
            });
        }

        function renderTotales(res) {
            $('#lbl-entradas').text(parseFloat(res.entradas).toFixed(2));
            $('#lbl-salidas').text(parseFloat(res.salidas).toFixed(2));
            const total = res.entradas - res.salidas;
            $('#lbl-resultado').text(total.toFixed(2)).removeClass('text-success text-danger').addClass(total >= 0 ? 'text-success' : 'text-danger');
        }

        function exportarExcel() {
            if (!reportData || reportData.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Atención',
                    text: 'No hay datos en la grilla para exportar. Ajuste sus filtros y genere el informe primero.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3500
                });
                return;
            }

            const filters = getFilters();
            const params = new URLSearchParams({ ...filters, action: 'export_excel' }).toString();
            window.open(`api_stock_movimientos.php?${params}`, '_blank');
        }

        init();

    })();
</script>