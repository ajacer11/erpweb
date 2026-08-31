<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">CONFIGURACIÓN DE LISTAS DE PRECIOS</h5>
            <small class="text-accent opacity-75">UTILIDADES PREDETERMINADAS</small>
        </div>
        <!-- Botón Volver (Cierra el módulo, o carga dashboard) -->
        <button onclick="location.reload()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="card card-filtros p-4" style="max-width: 700px; margin: 0 auto;">
        <form id="frmListas">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 60px;">ID</th>
                            <th>DESCRIPCIÓN DE LA LISTA</th>
                            <th class="text-center" style="width: 150px;">UTILIDAD %</th>
                        </tr>
                    </thead>
                    <tbody id="bodyListas">
                        <!-- Generación dinámica de 1 a 7 (o 8) -->
                        <?php for($i=1; $i<=8; $i++): ?>
                        <tr>
                            <td class="text-center fw-bold text-muted"><?= $i ?></td>
                            <td>
                                <input type="text" class="form-control form-control-sm fw-bold text-white" 
                                       name="des_<?= $i ?>" id="des_<?= $i ?>" 
                                       placeholder="NOMBRE LISTA <?= $i ?>" autocomplete="off">
                            </td>
                            <td>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control text-center fw-bold text-accent" 
                                           name="ut_<?= $i ?>" id="ut_<?= $i ?>" 
                                           step="0.01" value="0.00">
                                    <span class="input-group-text bg-transparent border-secondary text-muted">%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>

            <div class="alert alert-info small mt-3">
                <i class="fas fa-info-circle me-2"></i> 
                Estos porcentajes se utilizarán por defecto al crear nuevos productos bajo la modalidad "Lista Predeterminada".
            </div>

            <div class="text-end pt-3 mt-2 border-top border-secondary">
                <button type="submit" class="btn btn-info px-5 fw-bold rounded-pill">
                    <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    (function() {
        
        async function cargarListas() {
            try {
                // Usamos la API existente que ya trae ID, Descripcion y Utilidad
                const resp = await fetch('api_helpers.php?action=get_listas_utilidad', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success && res.data) {
                    res.data.forEach(lista => {
                        const id = lista.id;
                        // Solo mapeamos si existe el input (del 1 al 8)
                        const txtDes = document.getElementById(`des_${id}`);
                        const txtUt = document.getElementById(`ut_${id}`);
                        
                        if (txtDes && txtUt) {
                            txtDes.value = lista.descripcion || '';
                            txtUt.value = parseFloat(lista.utilidad || 0).toFixed(2);
                        }
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Error', 'No se pudieron cargar las listas', 'error');
            }
        }

        document.getElementById('frmListas').onsubmit = async (e) => {
            e.preventDefault();
            
            // Recopilar datos manualmente para armar el JSON limpio
            const listas = [];
            for(let i=1; i<=8; i++) {
                const des = document.getElementById(`des_${i}`).value.trim();
                const ut = document.getElementById(`ut_${i}`).value;
                
                // Guardamos incluso si la descripción está vacía, para permitir "borrar" listas
                // Opcional: Validar que si hay utilidad debe haber descripción
                listas.push({
                    id: i,
                    descripcion: des,
                    utilidad: ut
                });
            }

            try {
                const resp = await fetch('api_helpers.php?action=save_listas', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ listas: listas })
                });
                const res = await resp.json();
                
                if (res.success) {
                    Swal.fire({
                        icon: 'success', 
                        title: 'Guardado', 
                        text: res.message,
                        toast: true, position: 'top-end', timer: 2000, showConfirmButton: false
                    });
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        };

        cargarListas();
    })();
</script>