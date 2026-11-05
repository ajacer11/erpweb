<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="text-white">TRANSFERENCIA ENTRE CAJAS</h4>
            <small class="text-accent opacity-75">ENVÍO DE FONDOS A OTRO USUARIO</small>

            <div id="panelTransferencia" style="display:none;">
                <div class="card card-filtros mt-4 shadow-sm">
                    <div class="card-body p-4">
                        <div class="alert alert-dark border-secondary d-flex align-items-center mb-4">
                            <i class="fas fa-wallet fa-2x text-accent me-3"></i>
                            <div>
                                <small class="text-muted d-block">SALDO DISPONIBLE</small>
                                <h3 class="text-white mb-0 fw-bold" id="lblSaldo">$ 0.00</h3>
                            </div>
                        </div>

                        <form id="formTransferencia">
                            <div class="mb-4">
                                <label class="form-label-custom">CAJA DE DESTINO (USUARIO ACTIVO)</label>
                                <select class="form-select select2-simple" name="id_caja_destino" id="cmbDestino" required>
                                    <option value="">CARGANDO CAJAS DISPONIBLES...</option>
                                </select>
                                <small class="text-muted mt-1">Solo se muestran cajas abiertas de tipo "Caja de Usuarios".</small>
                            </div>

                            <div class="mb-4">
                                <label class="form-label-custom">IMPORTE A TRANSFERIR</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                    <input type="number" class="form-control fw-bold text-white fs-4" 
                                           name="importe" id="txtMonto" step="0.01" min="0.01" required 
                                           style="background: #000a1a; border-color: #445566;">
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-info fw-bold rounded-pill py-3">
                                    <i class="fas fa-exchange-alt me-2"></i>CONFIRMAR TRANSFERENCIA
                                </button>
                                <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="loadModule('dashboard.php')">
                                    CANCELAR
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div id="panelBloqueoTr" class="text-center mt-5" style="display:none;">
                <i class="fas fa-lock fa-4x text-danger mb-3"></i>
                <h4 class="text-white">ACCESO DENEGADO</h4>
                <p class="text-muted">Debe tener una caja abierta para realizar transferencias.</p>
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
            // 1. Obtener estado actual (saldo)
            const respStatus = await fetch('api_cajas.php?action=get_current_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const dataStatus = await respStatus.json();

            if (dataStatus.success) {
                saldoMaximo = parseFloat(dataStatus.data.saldo);
                $('#lblSaldo').text('$ ' + saldoMaximo.toLocaleString('es-AR', {minimumFractionDigits: 2}));
                $('#txtMonto').attr('max', saldoMaximo);
                $('#panelTransferencia').show();

                // 2. Cargar cajas destino
                cargarCajasDestino();
            } else {
                $('#panelBloqueoTr').show();
            }

            // Inicializar Select2
            $('.select2-simple').select2({
                dropdownParent: $('#main-content'),
                width: '100%',
                theme: 'default',
                language: 'es'
            });

        } catch (e) {
            Swal.fire('Error', 'No se pudo inicializar el módulo.', 'error');
        }
    }

    async function cargarCajasDestino() {
        try {
            const resp = await fetch('api_cajas.php?action=list_open_target_boxes', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            
            const cmb = $('#cmbDestino');
            cmb.empty().append(new Option('SELECCIONE CAJA DE DESTINO...', '', true, true));

            if (res.success && res.data.length > 0) {
                res.data.forEach(c => {
                    const texto = `${c.nombre} (Usuario: ${c.usuarioactivo})`;
                    cmb.append(new Option(texto, c.id, false, false));
                });
            } else {
                cmb.append(new Option('NO HAY OTRAS CAJAS ABIERTAS', '', false, false));
                cmb.prop('disabled', true);
            }
        } catch (e) {
            console.error(e);
        }
    }

    $('#formTransferencia').on('submit', async function(e) {
        e.preventDefault();
        
        const importe = parseFloat($('#txtMonto').val());
        const idDestino = $('#cmbDestino').val();
        const textoDestino = $('#cmbDestino option:selected').text();

        if (isNaN(importe) || importe <= 0) {
            Swal.fire('Atención', 'Ingrese un importe válido.', 'warning');
            return;
        }
        if (!idDestino) {
            Swal.fire('Atención', 'Seleccione una caja de destino.', 'warning');
            return;
        }
        if (importe > saldoMaximo) {
            Swal.fire('Saldo Insuficiente', 'El importe supera su saldo disponible.', 'error');
            return;
        }

        Swal.fire({
            title: '¿Confirmar Transferencia?',
            html: `Se enviarán <b>$ ${importe.toLocaleString('es-AR', {minimumFractionDigits: 2})}</b><br>a: <b>${textoDestino}</b>`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, TRANSFERIR',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('importe', importe);
                    formData.append('id_caja_destino', idDestino);

                    const resp = await fetch('api_cajas.php?action=transfer_to_box', {
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
                            loadModule('cajas_transferir_a_otra_caja.php', false, true);
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