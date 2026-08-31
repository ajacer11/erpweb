<?php
/**
 * uns_admin.php - VISTA FRAGMENTO (SPA)
 * Administración de Unidades de Negocio (ABM Simple).
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="container-uns">
    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">UNIDADES DE NEGOCIO</h4>
            <small class="text-accent opacity-75">GESTIÓN DE UNIDADES ORGANIZATIVAS</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-info btn-sm px-4 fw-bold rounded-pill" onclick="abrirModalUN()">
                <i class="fa-solid fa-plus me-2"></i> NUEVA UNIDAD
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="card border-secondary shadow" style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-dark table-hover table-sm mb-0 small" id="tblUNs">
                    <thead class="bg-dark">
                        <tr class="text-accent border-bottom border-secondary text-uppercase">
                            <th class="ps-4 py-3" style="width: 80px;">ID</th>
                            <th class="py-3">Nombre de la Unidad</th>
                            <th class="py-3 text-end pe-4" style="width: 100px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyUNs">
                        <tr><td colspan="3" class="text-center p-5 text-muted">Cargando unidades...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL ABM -->
<div class="modal fade" id="modalUN" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-secondary shadow-lg" style="background-color: #1a1a1a; color: #fff;">
            <div class="modal-header border-secondary bg-dark">
                <h5 class="modal-title text-white small fw-bold" id="modalTitle">NUEVA UNIDAD</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="frmUN">
                    <input type="hidden" name="id" id="hdnId">
                    <div class="mb-3">
                        <label class="form-label text-accent small">NOMBRE DE LA UNIDAD</label>
                        <input type="text" class="form-control bg-dark text-white border-secondary fw-bold" id="txtNombre" name="nombre" required autocomplete="off">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary bg-dark-soft">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-success btn-sm rounded-pill px-4 fw-bold" id="btnGuardar">GUARDAR</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        let modalInstance = null;

        // Cargar Listado
        async function loadUNs() {
            try {
                const resp = await fetch('api_uns.php?action=list', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                const tbody = $('#tbodyUNs');
                tbody.empty();

                if (res.success && res.data.length > 0) {
                    res.data.forEach(u => {
                        tbody.append(`
                            <tr class="border-secondary align-middle">
                                <td class="ps-4 font-monospace text-secondary">${u.id}</td>
                                <td class="fw-bold text-white">${u.nombre}</td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-warning border-0" 
                                        onclick="editarUN(${u.id}, '${u.nombre.replace(/'/g, "\\'")}')" 
                                        title="Editar">
                                        <i class="fa-solid fa-pencil"></i>
                                    </button>
                                </td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.html('<tr><td colspan="3" class="text-center p-4 text-muted">No hay unidades de negocio registradas.</td></tr>');
                }
            } catch (err) {
                console.error(err);
                $('#tbodyUNs').html('<tr><td colspan="3" class="text-center p-4 text-danger">Error al cargar datos.</td></tr>');
            }
        }

        // Abrir Modal (Alta)
        window.abrirModalUN = function() {
            $('#modalTitle').text('NUEVA UNIDAD');
            $('#hdnId').val('');
            $('#txtNombre').val('');
            if (!modalInstance) modalInstance = new bootstrap.Modal(document.getElementById('modalUN'));
            modalInstance.show();
            setTimeout(() => $('#txtNombre').focus(), 300);
        };

        // Abrir Modal (Edición)
        window.editarUN = function(id, nombre) {
            $('#modalTitle').text('EDITAR UNIDAD');
            $('#hdnId').val(id);
            $('#txtNombre').val(nombre);
            if (!modalInstance) modalInstance = new bootstrap.Modal(document.getElementById('modalUN'));
            modalInstance.show();
            setTimeout(() => $('#txtNombre').focus(), 300);
        };

        // Guardar
        $('#btnGuardar').on('click', async function() {
            const nombre = $('#txtNombre').val().trim();
            if (!nombre) {
                Swal.fire('Atención', 'El nombre es obligatorio.', 'warning');
                return;
            }

            try {
                const formData = new FormData(document.getElementById('frmUN'));
                const action = formData.get('id') ? 'update' : 'create';

                const resp = await fetch(`api_uns.php?action=${action}`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    modalInstance.hide();
                    Swal.fire({
                        icon: 'success', title: 'Guardado', text: 'Unidad de negocio guardada correctamente.',
                        toast: true, position: 'top-end', showConfirmButton: false, timer: 2000
                    });
                    loadUNs();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de conexión.', 'error');
            }
        });

        // Enter para guardar
        $('#txtNombre').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $('#btnGuardar').click();
            }
        });

        loadUNs();
    })();
</script>