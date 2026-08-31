<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="text-white">CIERRE DE CAJA</h4>
            <small class="text-accent opacity-75">FINALIZACIÓN DE JORNADA Y RENDICIÓN DE SALDOS</small>

            <div id="cajaInfoContainer" style="display:none;">
                <div class="card card-filtros mt-4 shadow-sm border-danger">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-cash-register fa-3x text-accent mb-3"></i>
                        <h3 class="text-white" id="lblCajaNombre">---</h3>
                        <p class="text-muted">PUESTO ACTUALMENTE ABIERTO</p>

                        <div class="bg-dark p-4 rounded my-4">
                            <label class="form-label-custom">SALDO ACTUAL EN TESORERÍA</label>
                            <h2 class="text-accent fw-bold" id="lblSaldoActual">$ 0.00</h2>
                        </div>

                        <div class="d-grid">
                            <button id="btnCerrarCaja" class="btn btn-danger fw-bold rounded-pill py-3">
                                <i class="fas fa-power-off me-2"></i>CERRAR CAJA Y FINALIZAR
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div id="noCajaContainer" class="text-center mt-5" style="display:none;">
                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                <h4 class="text-white">NO TIENES CAJAS ABIERTAS</h4>
                <p class="text-muted">No se detectaron jornadas activas para tu usuario.</p>
                <button onclick="loadModule('cajas_abrir.php')" class="btn btn-outline-info rounded-pill mt-3">
                    IR A APERTURA DE CAJA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    async function init() {
        try {
            const resp = await fetch('api_cajas.php?action=get_current_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.success) {
                $('#lblCajaNombre').text(result.data.nombre);
                $('#lblSaldoActual').text('$ ' + parseFloat(result.data.saldo).toLocaleString('es-AR', {minimumFractionDigits: 2}));
                $('#cajaInfoContainer').show();
            } else {
                $('#noCajaContainer').show();
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo validar el estado de la caja', 'error');
        }
    }

    $('#btnCerrarCaja').on('click', async function() {
        Swal.fire({
            title: '¿Cerrar jornada?',
            text: "Se registrará el saldo final y la caja quedará libre para otros usuarios.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'SÍ, CERRAR CAJA',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const resp = await fetch('api_cajas.php?action=close_box', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Caja Cerrada',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            loadModule('dashboard.php');
                        });
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Error de red', 'error');
                }
            }
        });
    });

    init();
})();
</script>