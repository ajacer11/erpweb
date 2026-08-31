<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h4 class="text-white">APERTURA DE CAJA</h4>
            <small class="text-accent opacity-75">SELECCIÓN DE PUESTO PARA INICIO DE OPERACIONES</small>

            <div class="card card-filtros mt-4 shadow-sm">
                <div class="card-body p-4">
                    <form id="formAbrirCaja">
                        <div class="mb-4">
                            <label class="form-label-custom">PUESTOS DISPONIBLES</label>
                            <select class="form-select select2-simple" name="id_puesto" id="cmbPuestos" required>
                                <option value="">CARGANDO...</option>
                            </select>
                        </div>

                        <div class="alert alert-info bg-dark border-info text-info small">
                            <i class="fas fa-info-circle me-2"></i>
                            Al confirmar, se registrará el saldo actual de la cuenta de tesorería asociada al puesto.
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-info fw-bold rounded-pill py-2">
                                <i class="fas fa-key me-2"></i>CONFIRMAR APERTURA
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    async function init() {
        $('#cmbPuestos').select2({
            dropdownParent: $('#main-content'),
            width: '100%'
        });

        try {
            const resp = await fetch('api_cajas.php?action=list_available', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();

            if (result.success) {
                const combo = $('#cmbPuestos');
                combo.empty().append(new Option('SELECCIONE UN PUESTO...', '', true, true));
                result.data.forEach(p => {
                    combo.append(new Option(p.nombre, p.id));
                });
                combo.trigger('change');
            } else {
                Swal.fire('Error', result.error, 'error');
            }
        } catch (e) {
            console.error(e);
        }
    }

    $('#formAbrirCaja').on('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);

        Swal.fire({
            title: '¿Abrir caja?',
            text: "Se registrará el inicio de jornada con el puesto seleccionado.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, ABRIR',
            cancelButtonText: 'CANCELAR'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const resp = await fetch('api_cajas.php?action=open_box', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
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
                    Swal.fire('Error', 'Falla de conexión', 'error');
                }
            }
        });
    });

    init();
})();
</script>