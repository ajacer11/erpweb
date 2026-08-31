<?php
/**
 * monedas_abm.php - FORMULARIO SIMPLE PARA CREAR/EDITAR MONEDAS
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = ($id > 0) ? "EDITAR MONEDA" : "NUEVA MONEDA";
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0"><?php echo $titulo; ?></h4>
            <small class="text-accent opacity-75">Definición de divisa</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill btn-sm" onclick="loadModule('monedas_admin.php')">
            <i class="fas fa-arrow-left me-2"></i>VOLVER
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <form id="formMoneda" class="card card-filtros p-4 shadow-sm border-0">
                <input type="hidden" name="id" value="<?php echo $id; ?>">
                
                <div class="mb-4">
                    <label class="form-label-custom">NOMBRE DE LA MONEDA</label>
                    <input type="text" name="nombre" id="txtNombre" class="form-control form-control-lg fw-bold text-center" required placeholder="Ej: DÓLAR OFICIAL">
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="loadModule('monedas_admin.php')">CANCELAR</button>
                    <button type="submit" class="btn btn-info fw-bold px-5 rounded-pill shadow">
                        <i class="fas fa-save me-2"></i>GUARDAR
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const id = <?php echo $id; ?>;

    async function init() {
        if (id > 0) {
            const resp = await fetch(`api_monedas.php?action=get&id=${id}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const res = await resp.json();
            if(res.success) {
                document.getElementById('txtNombre').value = res.data.nombre;
            }
        }
    }

    document.getElementById('formMoneda').onsubmit = async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        const resp = await fetch('api_monedas.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(data)
        });

        const result = await resp.json();
        if(result.success) {
            Swal.fire({ title: 'Éxito', text: 'Moneda guardada correctamente.', icon: 'success', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
            setTimeout(() => loadModule('monedas_admin.php'), 500);
        } else {
            Swal.fire({ title: 'Error', text: result.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    }

    init();
})();
</script>