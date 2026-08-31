<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$numasiento = (int)($_GET['asiento'] ?? 0);
$idejercicio = (int)($_GET['ejercicio'] ?? 0);
?>

<div class="container-fluid px-4 py-3 fade-in d-flex flex-column" style="height: calc(100vh - 80px);">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <div>
            <h4 class="text-white mb-0">MODIFICAR ASIENTO CONTABLE <span class="text-accent">#<?php echo $numasiento; ?></span></h4>
            <small class="text-accent opacity-75">Edición detallada de movimientos por renglón</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary rounded-pill" onclick="loadModule('reporte_contable_diario.php')">
                <i class="fas fa-arrow-left me-2"></i>VOLVER
            </button>
            <button class="btn btn-info fw-bold rounded-pill" id="btnGuardarAsiento">
                <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <!-- CABECERA DEL ASIENTO -->
    <div class="card card-filtros mb-3 shadow-sm flex-shrink-0">
        <div class="card-body p-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label-custom">FECHA DEL ASIENTO</label>
                    <input type="date" id="txtFechaAsiento" class="form-control">
                    <input type="hidden" id="fechaOriginal">
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">DETALLE / CONCEPTO</label>
                    <input type="text" id="txtDetalleAsiento" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">EJERCICIO</label>
                    <select id="cmbEjercicioMod" class="form-select select2-simple" disabled>
                        <!-- Cargado vía AJAX -->
                    </select>
                </div>
                <div class="col-md-12 d-flex align-items-center gap-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chkFiscalMod">
                        <label class="form-check-label text-white" for="chkFiscalMod">ES ASIENTO FISCAL</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRILLA DE RENGLONES -->
    <div class="card card-filtros shadow-sm d-flex flex-column flex-grow-1 overflow-hidden">
        <div class="table-responsive flex-grow-1" style="overflow-y: auto;">
            <table class="table table-hover align-middle shadow-sm mb-0" id="tblRenglonesAsiento">
                <thead class="sticky-top">
                    <tr>
                        <th style="width: 120px;">ID LÍNEA</th>
                        <th style="width: 150px;">CÓDIGO CTA</th>
                        <th>CUENTA CONTABLE</th>
                        <th class="text-end" style="width: 180px;">DEBE</th>
                        <th class="text-end" style="width: 180px;">HABER</th>
                        <th class="text-center" style="width: 100px;">ACCIÓN</th>
                    </tr>
                </thead>
                <tbody id="tbodyRenglones">
                    <!-- Dinámico -->
                </tbody>
                <tfoot class="sticky-bottom bg-navbar">
                    <tr class="fw-bold">
                        <td colspan="3" class="text-end text-white">TOTALES:</td>
                        <td class="text-end text-accent" id="totalDebe">0.00</td>
                        <td class="text-end text-accent" id="totalHaber">0.00</td>
                        <td></td>
                    </tr>
                    <tr id="rowDiferencia" class="d-none">
                        <td colspan="3" class="text-end text-danger small">DIFERENCIA:</td>
                        <td colspan="2" class="text-center text-danger small" id="valDiferencia">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal Búsqueda de Cuentas (Reemplaza buscarcuentacontable.vb) -->
<div class="modal fade" id="modalCuentas" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white">SELECCIONAR CUENTA CONTABLE</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 bg-card border-bottom border-secondary">
                    <input type="text" id="txtBuscarCuenta" class="form-control" placeholder="BUSCAR POR NOMBRE O CÓDIGO...">
                </div>
                <div style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>CÓDIGO</th>
                                <th>DESCRIPCIÓN</th>
                                <th class="text-center">TIPO</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyModalCuentas">
                            <!-- Dinámico -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const numasiento = <?php echo $numasiento; ?>;
    const idejercicio = <?php echo $idejercicio; ?>;
    let renglones = [];
    let filaEditando = null;

    async function init() {
        $('.select2-simple').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            theme: 'default'
        });

        await cargarEjercicios();
        await cargarDatosAsiento();

        // Listeners
        $('#btnGuardarAsiento').click(guardarCambios);
        
        $('#txtBuscarCuenta').on('input', function() {
            filtrarCuentasModal($(this).val());
        });
    }

    async function cargarEjercicios() {
        const resp = await fetch('api_helpers.php?action=get_ejercicios', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
        const res = await resp.json();
        const $cmb = $('#cmbEjercicioMod');
        res.data.forEach(ej => $cmb.append(new Option(ej.descripcion, ej.id)));
        $cmb.val(idejercicio).trigger('change');
    }

    async function cargarDatosAsiento() {
        const resp = await fetch(`api_asiento_modificar.php?action=get&asiento=${numasiento}&ejercicio=${idejercicio}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        });
        const res = await resp.json();
        if (!res.success) {
            Swal.fire('Error', res.error, 'error');
            return;
        }

        const data = res.data;
        if (data.length > 0) {
            $('#txtDetalleAsiento').val(data[0].detalle);
            $('#txtFechaAsiento').val(data[0].fecha);
            $('#fechaOriginal').val(data[0].fecha);
            $('#chkFiscalMod').prop('checked', data[0].fiscal == 1);
            
            renglones = data;
            renderRenglones();
        }
    }

    function renderRenglones() {
        const $tbody = $('#tbodyRenglones');
        $tbody.empty();
        let totalDebe = 0;
        let totalHaber = 0;

        renglones.forEach((r, idx) => {
            totalDebe += parseFloat(r.debe);
            totalHaber += parseFloat(r.haber);

            const tr = $(`
                <tr>
                    <td class="text-white-50">${r.id}</td>
                    <td class="fw-bold">${r.codigo}</td>
                    <td class="text-accent">${r.cuenta_nom}</td>
                    <td class="text-end">
                        <input type="number" step="0.01" class="form-control form-control-sm text-end input-monto" data-idx="${idx}" data-field="debe" value="${r.debe}">
                    </td>
                    <td class="text-end">
                        <input type="number" step="0.01" class="form-control form-control-sm text-end input-monto" data-idx="${idx}" data-field="haber" value="${r.haber}">
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-outline-info border-0 btn-buscar-cta" data-idx="${idx}">
                            <i class="fas fa-search"></i>
                        </button>
                    </td>
                </tr>
            `);
            $tbody.append(tr);
        });

        actualizarTotales(totalDebe, totalHaber);

        // Events de inputs
        $('.input-monto').on('change', function() {
            const idx = $(this).data('idx');
            const field = $(this).data('field');
            renglones[idx][field] = parseFloat($(this).val()) || 0;
            renderRenglones();
        });

        $('.btn-buscar-cta').click(function() {
            filaEditando = $(this).data('idx');
            abrirModalCuentas();
        });
    }

    function actualizarTotales(debe, haber) {
        $('#totalDebe').text(debe.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        $('#totalHaber').text(haber.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        
        const diff = Math.abs(debe - haber);
        if (diff > 0.01) {
            $('#rowDiferencia').removeClass('d-none');
            $('#valDiferencia').text(diff.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        } else {
            $('#rowDiferencia').addClass('d-none');
        }
    }

    let todasLasCuentas = [];
    async function abrirModalCuentas() {
        if (todasLasCuentas.length === 0) {
            const resp = await fetch('api_helpers.php?action=get_plan_cuentas', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            todasLasCuentas = res.data;
        }
        filtrarCuentasModal('');
        $('#modalCuentas').modal('show');
    }

    function filtrarCuentasModal(filtro) {
        const f = filtro.toUpperCase();
        const filtered = todasLasCuentas.filter(c => c.numcuenta.toString().includes(f) || c.descripcion.includes(f));
        const $tbody = $('#tbodyModalCuentas');
        $tbody.empty();
        
        filtered.slice(0, 50).forEach(c => {
            const tr = $(`
                <tr class="cursor-pointer">
                    <td class="fw-bold">${c.numcuenta}</td>
                    <td>${c.descripcion}</td>
                    <td class="text-center small opacity-50">IMPUTABLE</td>
                </tr>
            `);
            tr.click(() => seleccionarCuenta(c));
            $tbody.append(tr);
        });
    }

    function seleccionarCuenta(c) {
        renglones[filaEditando].codigo = c.numcuenta;
        renglones[filaEditando].cuenta_nom = c.descripcion;
        $('#modalCuentas').modal('hide');
        renderRenglones();
    }

    async function guardarCambios() {
        // Validaciones Regla VB
        const fechaNueva = $('#txtFechaAsiento').val();
        if (new Date(fechaNueva) > new Date()) {
            Swal.fire('Atención', 'NO PUEDE CARGAR MOVIMIENTOS CON FECHA FUTURA', 'warning');
            return;
        }

        const debe = renglones.reduce((acc, r) => acc + parseFloat(r.debe), 0);
        const haber = renglones.reduce((acc, r) => acc + parseFloat(r.haber), 0);
        if (Math.abs(debe - haber) > 0.01) {
            Swal.fire('Atención', 'EL ASIENTO NO BALANCEA (DEBE != HABER)', 'error');
            return;
        }

        const dataEnvio = {
            numasiento: numasiento,
            idejercicio: idejercicio,
            fechaNueva: fechaNueva,
            fechaOriginal: $('#fechaOriginal').val(),
            detalle: $('#txtDetalleAsiento').val(),
            fiscal: $('#chkFiscalMod').is(':checked') ? 1 : 0,
            renglones: renglones
        };

        Swal.fire({
            title: '¿Guardar Cambios?',
            text: "Se actualizará el asiento y se recalculará la numeración si la fecha cambió.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: 'var(--accent)',
            confirmButtonText: 'SÍ, GUARDAR',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const resp = await fetch('api_asiento_modificar.php?action=save', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(dataEnvio)
                    });
                    const res = await resp.json();
                    if (res.success) {
                        Swal.fire('Éxito', 'Asiento modificado correctamente', 'success').then(() => {
                            loadModule('reporte_contable_diario.php');
                        });
                    } else {
                        throw new Error(res.error);
                    }
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        });
    }

    init();
})();
</script>