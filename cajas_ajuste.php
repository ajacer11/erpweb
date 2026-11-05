<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="text-white">ARQUEO Y AJUSTE DE CAJA</h4>
            <small class="text-accent opacity-75">CORRECCIÓN DE SALDOS POR DIFERENCIAS FÍSICAS</small>

            <!-- Panel de Carga -->
            <div id="panelAjuste" style="display:none;">
                <div class="card card-filtros mt-4 shadow-sm">
                    <div class="card-body p-4">
                        
                        <!-- Saldo Teórico -->
                        <div class="row mb-4">
                            <div class="col-6">
                                <small class="text-muted d-block text-uppercase">SALDO TEÓRICO (SISTEMA)</small>
                                <h3 class="text-info fw-bold mb-0" id="lblSaldoTeorico">$ 0.00</h3>
                            </div>
                            <div class="col-6 text-end">
                                <small class="text-muted d-block text-uppercase">DIFERENCIA</small>
                                <h3 class="fw-bold mb-0" id="lblDiferencia">$ 0.00</h3>
                            </div>
                        </div>

                        <hr class="border-secondary mb-4">

                        <form id="formAjuste">
                            <!-- Input Saldo Real -->
                            <div class="mb-4">
                                <label class="form-label-custom">SALDO REAL (CONTEO FÍSICO)</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                    <input type="number" class="form-control fw-bold text-white fs-2 text-center" 
                                           name="saldo_real" id="txtSaldoReal" step="0.01" min="0" required 
                                           style="background: #000a1a; border-color: #445566;"
                                           placeholder="0.00">
                                </div>
                                <small class="text-muted mt-2 d-block text-center">Ingrese el total de efectivo contado en la caja.</small>
                            </div>

                            <!-- Botones -->
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-warning fw-bold rounded-pill py-3 text-dark">
                                    <i class="fas fa-balance-scale me-2"></i>CONFIRMAR AJUSTE
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="loadModule('dashboard.php')">
                                    CANCELAR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Panel Bloqueo -->
            <div id="panelBloqueoAjuste" class="text-center mt-5" style="display:none;">
                <i class="fas fa-lock fa-4x text-danger mb-3"></i>
                <h4 class="text-white">ACCESO DENEGADO</h4>
                <p class="text-muted">Debe tener una caja abierta para realizar arqueos.</p>
                <button onclick="loadModule('cajas_abrir.php')" class="btn btn-outline-light rounded-pill mt-3">
                    IR A APERTURA DE CAJA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let saldoTeorico = 0;

    async function init() {
        try {
            // Obtener estado actual
            const resp = await fetch('api_cajas.php?action=get_current_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.success) {
                saldoTeorico = parseFloat(result.data.saldo);
                $('#lblSaldoTeorico').text('$ ' + saldoTeorico.toLocaleString('es-AR', {minimumFractionDigits: 2}));
                $('#panelAjuste').show();
                $('#txtSaldoReal').focus();
            } else {
                $('#panelBloqueoAjuste').show();
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudo obtener información de la caja.', 'error');
        }
    }

    // Cálculo dinámico de diferencia al escribir
    $('#txtSaldoReal').on('input', function() {
        const val = parseFloat($(this).val());
        if (!isNaN(val)) {
            const dif = val - saldoTeorico;
            const elem = $('#lblDiferencia');
            elem.text('$ ' + dif.toLocaleString('es-AR', {minimumFractionDigits: 2}));

            if (dif < 0) {
                elem.removeClass('text-success text-white').addClass('text-danger'); // Faltante
            } else if (dif > 0) {
                elem.removeClass('text-danger text-white').addClass('text-success'); // Sobrante
            } else {
                elem.removeClass('text-danger text-success').addClass('text-white'); // Igual
            }
        }
    });

    $('#formAjuste').on('submit', async function(e) {
        e.preventDefault();
        
        const saldoReal = parseFloat($('#txtSaldoReal').val());

        if (isNaN(saldoReal) || saldoReal < 0) {
            Swal.fire('Atención', 'Ingrese un saldo real válido.', 'warning');
            return;
        }

        const diferencia = saldoReal - saldoTeorico;
        
        if (Math.abs(diferencia) < 0.01) {
            Swal.fire('Perfecto', 'El saldo real coincide con el sistema. No es necesario realizar ajustes.', 'success');
            return;
        }

        let tipoDiferencia = diferencia < 0 ? "FALTANTE (PÉRDIDA)" : "SOBRANTE (GANANCIA)";
        let colorConfirm = diferencia < 0 ? "#d33" : "#28a745";

        Swal.fire({
            title: '¿Confirmar Ajuste?',
            html: `Saldo Sistema: <b>$ ${saldoTeorico.toFixed(2)}</b><br>
                   Saldo Real: <b>$ ${saldoReal.toFixed(2)}</b><br><br>
                   Se registrará un <b>${tipoDiferencia}</b> de:<br>
                   <h3 style="color:${colorConfirm}">$ ${Math.abs(diferencia).toFixed(2)}</h3>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: colorConfirm,
            confirmButtonText: 'SÍ, AJUSTAR SALDO',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('saldo_real', saldoReal);

                    const resp = await fetch('api_cajas.php?action=adjust_balance', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            icon: 'success', title: 'Movimiento registrado',
                            text: res.message + (res.comprobante_id ? ' ¿Desea imprimir el comprobante?' : ''),
                            showCancelButton: !!res.comprobante_id,
                            confirmButtonText: res.comprobante_id ? 'IMPRIMIR' : 'ACEPTAR', cancelButtonText: 'NO IMPRIMIR'
                        }).then((respuesta) => {
                            if (respuesta.isConfirmed && res.comprobante_id) window.open('imprimir_movimiento_caja.php?id=' + encodeURIComponent(res.comprobante_id), '_blank');
                            loadModule('cajas_ajuste.php', false, true); // Recargar para ver saldo actualizado
                        });
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'Error de conexión.', 'error');
                }
            }
        });
    });

    init();
})();
</script>