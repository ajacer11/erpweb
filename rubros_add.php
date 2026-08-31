<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = $id ? "MODIFICAR RUBRO" : "NUEVO RUBRO"; // Texto exacto de VB
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" id="tituloModulo"><?= $titulo ?></h5>
            <small class="text-accent opacity-75">GESTIÓN DE CATEGORÍAS</small>
        </div>
        <button onclick="loadModule('rubros_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CANCELAR
        </button>
    </div>

    <div class="card card-filtros p-4" style="max-width: 600px; margin: 0 auto;">
        <form id="frmRubro">
            <input type="hidden" name="id" value="<?= $id ?>">

            <div class="mb-3">
                <label class="form-label-custom">Nombre del Rubro</label>
                <input type="text" class="form-control fw-bold" name="rubro" id="txtRubro" required autofocus>
            </div>

            <div class="row align-items-center mb-4">
                <div class="col-md-6">
                    <div class="form-check form-switch pt-3">
                        <input class="form-check-input" type="checkbox" name="iibbespecial" id="chkIIBB" onchange="toggleIIBB()">
                        <label class="form-check-label text-white small ms-2" for="chkIIBB">IIBB ESPECIAL</label>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Alícuota IIBB (%)</label>
                    <input type="number" class="form-control text-end" name="alicuotaiibb" id="txtAlicuota" step="0.01" value="0.00" disabled>
                </div>
            </div>

            <div class="text-end border-top border-secondary pt-3">
                <button type="submit" class="btn btn-info px-5 fw-bold rounded-pill">
                    <i class="fas fa-save me-2"></i>GUARDAR
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        const idRubro = <?= $id ?>;

        window.toggleIIBB = function() {
            const chk = document.getElementById('chkIIBB');
            const txt = document.getElementById('txtAlicuota');
            txt.disabled = !chk.checked;
            if (!chk.checked) txt.value = "0.00";
        }

        async function cargarDatos() {
            if (idRubro === 0) return;

            try {
                const resp = await fetch(`api_rubros.php?id=${idRubro}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    const d = res.data;
                    document.getElementById('txtRubro').value = d.rubro;
                    
                    const chk = document.getElementById('chkIIBB');
                    chk.checked = (d.iibbespecial == 1);
                    
                    const txt = document.getElementById('txtAlicuota');
                    txt.value = d.alicuotaiibb;
                    
                    toggleIIBB(); // Actualizar estado del input
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudo cargar el rubro', 'error');
            }
        }

        document.getElementById('frmRubro').onsubmit = async (e) => {
            e.preventDefault();
            
            // Validaciones frontend (replica de VB)
            const rubroVal = document.getElementById('txtRubro').value.trim();
            if(!rubroVal) {
                Swal.fire('Advertencia', 'Ingrese un valor válido en el rubro', 'warning');
                return;
            }

            const data = Object.fromEntries(new FormData(e.target));
            // Checkbox value fix (si no está checked no se envía en FormData)
            data.iibbespecial = document.getElementById('chkIIBB').checked ? 1 : 0;

            try {
                const resp = await fetch('api_rubros.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                const res = await resp.json();
                
                if (res.success) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'Guardado', 
                        text: res.message,
                        toast: true, position: 'top-end', timer: 2000, showConfirmButton: false
                    });
                    setTimeout(() => loadModule('rubros_admin.php'), 1200);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        };

        cargarDatos();
    })();
</script>