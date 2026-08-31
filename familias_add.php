<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = $id ? "MODIFICAR FAMILIA" : "NUEVA FAMILIA"; 
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" id="tituloModulo"><?= $titulo ?></h5>
            <small class="text-accent opacity-75">GESTIÓN DE FAMILIAS</small>
        </div>
        <button onclick="loadModule('familias_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CANCELAR
        </button>
    </div>

    <div class="card card-filtros p-4" style="max-width: 600px; margin: 0 auto;">
        <form id="frmFamilia">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-4">
                <label class="form-label-custom">Nombre de la Familia</label>
                <input type="text" class="form-control fw-bold" name="nombre" id="txtFamilia" required autofocus>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="habilitada" id="chkHab" checked>
                    <label class="form-check-label text-white small ms-2" for="chkHab">FAMILIA HABILITADA</label>
                </div>
            </div>

            <!-- Alerta Informativa -->
            <div class="alert alert-warning small d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-3 fa-2x"></i>
                <div id="lblMensaje">
                    ATENCION: La familia debe estar habilitada para poder asignarla a productos.
                </div>
            </div>

            <div class="text-end border-top border-secondary pt-3 mt-4">
                <button type="submit" class="btn btn-info px-5 fw-bold rounded-pill">
                    <i class="fas fa-save me-2"></i>GUARDAR
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const idFamilia = <?= $id ?>;

        async function cargarDatos() {
            if (idFamilia === 0) return;

            // Lógica VB: Cambiar mensaje si es edición
            document.getElementById('lblMensaje').innerText = "ATENCION: Deshabilitar una familia no la quita de los productos que la tengan asignada, solo evita que se asigne de ahora en adelante";

            try {
                const resp = await fetch(`api_familias.php?id=${idFamilia}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    const d = res.data;
                    document.getElementById('txtFamilia').value = d.nombre;
                    document.getElementById('chkHab').checked = (d.habilitada == 1);
                }
            } catch (e) { console.error(e); }
        }

        document.getElementById('frmFamilia').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            
            // Fix Checkbox
            data.habilitada = document.getElementById('chkHab').checked ? 1 : 0;

            if(!data.nombre.trim()) {
                Swal.fire('Atención', 'Ingrese un valor para el nombre de la familia', 'warning');
                return;
            }

            try {
                const resp = await fetch('api_familias.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                const res = await resp.json();
                
                if (res.success) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'Éxito', 
                        text: res.message,
                        toast: true, position: 'top-end', timer: 2000, showConfirmButton: false
                    });
                    setTimeout(() => loadModule('familias_admin.php'), 1200);
                } else {
                    Swal.fire('Atención', res.error, 'warning');
                }
            } catch (err) {
                Swal.fire('Atención', 'Error de conexión', 'warning');
            }
        };

        cargarDatos();
    })();
</script>