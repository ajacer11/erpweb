<?php
// SEGURIDAD
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = $id ? "MODIFICAR MARCA" : "NUEVA MARCA"; 
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" id="tituloModulo"><?= $titulo ?></h5>
            <small class="text-accent opacity-75">GESTIÓN DE MARCAS</small>
        </div>
        <button onclick="loadModule('marcas_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CANCELAR
        </button>
    </div>

    <div class="card card-filtros p-4" style="max-width: 600px; margin: 0 auto;">
        <form id="frmMarca">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-4">
                <label class="form-label-custom">Nombre de la Marca</label>
                <input type="text" class="form-control fw-bold" name="marca" id="txtMarca" required autofocus>
            </div>

            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="habilitada" id="chkHab" checked>
                    <label class="form-check-label text-white small ms-2" for="chkHab">MARCA HABILITADA</label>
                </div>
            </div>

            <!-- Alerta Informativa (Lógica VB Load Event) -->
            <div class="alert alert-warning small d-flex align-items-center" role="alert">
                <i class="fas fa-exclamation-triangle me-3 fa-2x"></i>
                <div id="lblMensaje">
                    ATENCION: La marca debe estar habilitada para poder asignarla a productos.
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
        const idMarca = <?= $id ?>;

        async function cargarDatos() {
            if (idMarca === 0) return;

            // Lógica VB: Cambiar mensaje si es edición
            document.getElementById('lblMensaje').innerText = "ATENCION: Deshabilitar una marca no la quita de los productos que la tengan asignada, solo evita que se asigne de ahora en adelante";

            try {
                const resp = await fetch(`api_marcas.php?id=${idMarca}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    const d = res.data;
                    document.getElementById('txtMarca').value = d.marca;
                    document.getElementById('chkHab').checked = (d.habilitada == 1);
                }
            } catch (e) { console.error(e); }
        }

        document.getElementById('frmMarca').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            
            // Fix Checkbox
            data.habilitada = document.getElementById('chkHab').checked ? 1 : 0;

            if(!data.marca.trim()) {
                Swal.fire('Atención', 'Ingrese un valor para el nombre de la marca', 'warning');
                return;
            }

            try {
                const resp = await fetch('api_marcas.php', {
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
                    setTimeout(() => loadModule('marcas_admin.php'), 1200);
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