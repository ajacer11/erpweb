<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="text-white">RETIRO A CAJA CENTRAL</h4>
            <small class="text-accent opacity-75">TRANSFERENCIA DE FONDOS INTERNOS</small>

            <!-- Panel de Carga -->
            <div id="panelRetiro" style="display:none;">
                <div class="card card-filtros mt-4 shadow-sm">
                    <div class="card-body p-4">
                        <div class="alert alert-dark border-secondary d-flex align-items-center mb-4">
                            <i class="fas fa-wallet fa-2x text-accent me-3"></i>
                            <div>
                                <small class="text-muted d-block">SALDO DISPONIBLE EN CAJA</small>
                                <h3 class="text-white mb-0 fw-bold" id="lblSaldoDisponible">$ 0.00</h3>
                            </div>
                        </div>

                        <form id="formRetiro">
                            <div class="mb-4">
                                <label class="form-label-custom">IMPORTE A RETIRAR</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                    <input type="number" class="form-control fw-bold text-white fs-4" 
                                           name="importe" id="txtImporte" step="0.01" min="0.01" required 
                                           style="background: #000a1a; border-color: #445566;">
                                </div>
                                <small class="text-muted mt-1">El importe no puede superar el saldo disponible.</small>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-info fw-bold rounded-pill py-3">
                                    <i class="fas fa-paper-plane me-2"></i>CONFIRMAR TRANSFERENCIA
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="loadModule('dashboard.php')">
                                    CANCELAR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel de Bloqueo (Si no hay caja abierta) -->
            <div id="panelBloqueo" class="text-center mt-5" style="display:none;">
                <i class="fas fa-lock fa-4x text-danger mb-3"></i>
                <h4 class="text-white">ACCESO DENEGADO</h4>
                <p class="text-muted">Debe tener una caja abierta para realizar retiros de fondos.</p>
                <button onclick="loadModule('cajas_abrir.php')" class="btn btn-outline-light rounded-pill mt-3">
                    IR A APERTURA DE CAJA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let saldoMaximo = 0;

    async function init() {
        try {
            // Verificamos estado de caja y saldo actual
            const resp = await fetch('api_cajas.php?action=get_current_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.success) {
                saldoMaximo = parseFloat(result.data.saldo);
                $('#lblSaldoDisponible').text('$ ' + saldoMaximo.toLocaleString('es-AR', {minimumFractionDigits: 2}));
                $('#txtImporte').attr('max', saldoMaximo);
                $('#panelRetiro').show();
                $('#txtImporte').focus();
            } else {
                $('#panelBloqueo').show();
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo verificar el estado de la caja.', 'error');
        }
    }

    $('#formRetiro').on('submit', async function(e) {
        e.preventDefault();
        
        const importe = parseFloat($('#txtImporte').val());

        if (isNaN(importe) || importe <= 0) {
            Swal.fire('Atención', 'Ingrese un importe válido mayor a cero.', 'warning');
            return;
        }

        if (importe > saldoMaximo) {
            Swal.fire('Saldo Insuficiente', 'El importe supera el saldo disponible en su caja.', 'error');
            return;
        }

        Swal.fire({
            title: '¿Confirmar Retiro?',
            html: `Se transferirán <b>$ ${importe.toLocaleString('es-AR', {minimumFractionDigits: 2})}</b><br>a la Caja Central.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, TRANSFERIR',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('importe', importe);

                    const resp = await fetch('api_cajas.php?action=withdraw_to_central', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Transferencia Exitosa',
                            text: res.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            loadModule('cajas_retirar_a_central.php', false, true); // Recargar para actualizar saldo
                        });
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
                }
            }
        });
    });

    init();
})();
</script>