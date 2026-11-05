<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

// Cargar menu.json para renderizar el árbol
$json_path = 'menu'. $_SESSION['version'] . '.json';
$menu_items = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];

/**
 * Función recursiva para dibujar el árbol de permisos
 * Sigue la estructura visual del manual (Listas anidadas)
 */
function renderPermissionTree($items, $level = 0) {
    echo '<ul class="list-unstyled ' . ($level > 0 ? 'ms-4 border-start border-secondary ps-3' : '') . '">';
    
    foreach ($items as $item) {
        $hasChildren = !empty($item['children']);
        $url = !empty($item['url']) ? $item['url'] : '';
        $id = $item['id'];
        $text = htmlspecialchars($item['text']);
        $icon = !empty($item['icon']) ? '<i class="' . $item['icon'] . ' text-accent me-2"></i>' : '';

        // ID único para el checkbox
        $chkId = "chk_" . $id;
        
        echo '<li class="mb-2 list-item-permiso">';
        echo '<div class="form-check form-switch">';
        // Agregamos data-parent para lógica JS futura si fuera necesaria, 
        // pero la cascada se maneja mejor por estructura DOM
        echo '<input class="form-check-input perm-check" type="checkbox" id="' . $chkId . '" data-menu-id="' . $id . '" data-url="' . $url . '">';
        echo '<label class="form-check-label text-white" for="' . $chkId . '">';
        echo $icon . $text;
        echo '</label>';
        echo '</div>';

        if ($hasChildren) {
            // Contenedor de hijos para fácil selección JS
            echo '<div class="children-container">';
            renderPermissionTree($item['children'], $level + 1);
            echo '</div>';
        }
        echo '</li>';
    }
    
    echo '</ul>';
}
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white">NIVELES DE ACCESO</h4>
            <small class="text-accent opacity-75">GESTIÓN DE ROLES Y PERMISOS DE USUARIO</small>
        </div>
        <div>
            <button id="btnClonar" class="btn btn-outline-warning fw-bold rounded-pill me-2" disabled>
                <i class="fa-solid fa-copy me-2"></i> CLONAR DE OTRO
            </button>
            <button id="btnGuardar" class="btn btn-info fw-bold rounded-pill" disabled>
                <i class="fa-solid fa-save me-2"></i> GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <!-- Panel de Selección de Usuario -->
    <div class="card card-filtros mb-4 shadow-sm">
        <div class="card-body p-3">
            <div class="row">
                <div class="col-md-6">
                    <label class="form-label-custom">SELECCIONAR USUARIO</label>
                    <select class="form-select select2-simple" id="cmbUsuario">
                        <option value="">CARGANDO USUARIOS...</option>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <div id="admin-badge" class="alert alert-warning py-1 px-3 mb-0 w-100 text-center fw-bold" style="display: none;">
                        <i class="fa-solid fa-crown me-2"></i> USUARIO ADMINISTRADOR (ACCESO TOTAL)
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Árbol de Permisos -->
    <div class="card card-filtros shadow-sm">
        <div class="card-header bg-transparent border-bottom border-secondary">
            <ul class="nav nav-tabs border-0" id="permisosTabNav" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active text-white" id="tab-arbol-btn" data-bs-toggle="tab" data-bs-target="#tab-arbol" type="button" role="tab">
                        <i class="fas fa-sitemap me-2 text-info"></i>ÁRBOL DE MENÚ
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link text-white" id="tab-especiales-btn" data-bs-toggle="tab" data-bs-target="#tab-especiales" type="button" role="tab">
                        <i class="fas fa-lock me-2 text-warning"></i>PERMISOS ESPECIALES
                    </button>
                </li>
            </ul>
        </div>

        <div class="tab-content">
            <!-- TAB: ÁRBOL -->
            <div class="tab-pane fade show active" id="tab-arbol" role="tabpanel">
                <div class="card-body p-3 pb-0">
                    <div class="d-flex justify-content-end mb-2">
                        <button class="btn btn-sm btn-outline-secondary me-2" id="btnCheckAll" title="Marcar Todo"><i class="fa-solid fa-check-double"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" id="btnUncheckAll" title="Desmarcar Todo"><i class="fa-solid fa-square"></i></button>
                    </div>
                </div>
                <div class="card-body p-4 pt-0" style="max-height: 60vh; overflow-y: auto;">
                    <form id="formPermisos">
                        <?php renderPermissionTree($menu_items); ?>
                    </form>
                </div>
            </div>

            <!-- TAB: PERMISOS ESPECIALES -->
            <div class="tab-pane fade" id="tab-especiales" role="tabpanel">
                <div class="card-body p-4">
                    <div id="especiales-placeholder" class="text-center text-muted py-5">
                        <i class="fas fa-arrow-up me-2"></i>Seleccione un usuario para ver sus permisos especiales.
                    </div>
                    <div id="especiales-form" style="display:none;">
                        <div class="mb-4">
                            <small class="text-accent opacity-75">
                                Restricciones adicionales independientes del árbol de menú. Se replican exactamente desde el sistema de escritorio.
                            </small>
                        </div>

                        <!-- Permiso 1 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_precioscosto" name="precioscosto" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_precioscosto">
                                No permitir ver Precios de Costo de Mercadería
                            </label>
                            <div class="text-muted small mt-1">
                                El usuario no podrá visualizar la columna de precio de costo en productos y reportes.
                            </div>
                        </div>

                        <!-- Permiso 2 -->
                        <div class="mb-4 border-bottom border-secondary pb-4">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="pe_unvendedor" name="unvendedor" value="1">
                                <label class="form-check-label text-white fw-bold" for="pe_unvendedor">
                                    Solo Mostrar Operaciones de Un Vendedor
                                </label>
                                <div class="text-muted small mt-1">
                                    Filtra listados y reportes para mostrar únicamente las operaciones del vendedor seleccionado.
                                </div>
                            </div>
                            <div class="ms-4 mt-2" id="pe_vendedor_wrapper" style="display:none;">
                                <label class="form-label-custom">VENDEDOR ASIGNADO</label>
                                <select class="form-select" id="pe_idvendedor" name="idvendedor" style="max-width: 350px;">
                                    <option value="0">— Seleccione un vendedor —</option>
                                </select>
                            </div>
                        </div>

                        <!-- Permiso 3 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_modificarprecios" name="modificarprecios" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_modificarprecios">
                                No permitir modificar Pedidos
                            </label>
                            <div class="text-muted small mt-1">
                                El usuario podrá ver los pedidos pero no editarlos ni eliminarlos.
                            </div>
                        </div>

                        <!-- Permiso 4 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_facturarpedidos" name="facturarpedidos" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_facturarpedidos">
                                No permitir Facturar Pedidos o Presupuestos
                            </label>
                            <div class="text-muted small mt-1">
                                Bloquea el botón de facturación desde la lista de pedidos y presupuestos.
                            </div>
                        </div>

                        <!-- Permiso 5 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_modificarfacturas" name="modificarfacturas" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_modificarfacturas">
                                No Permitir modificar facturas de compra
                            </label>
                            <div class="text-muted small mt-1">
                                Las facturas de compra quedan en modo solo lectura para este usuario.
                            </div>
                        </div>

                        <!-- Permiso 6 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_editardescripcionfacturar" name="editardescripcionfacturar" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_editardescripcionfacturar">
                                Permitir editar descripción de producto al Facturar
                            </label>
                            <div class="text-muted small mt-1">
                                Solo aplica si además está habilitado en Configuración General.
                            </div>
                        </div>

                        <!-- Permiso 7 -->
                        <div class="form-check form-switch mb-4 border-bottom border-secondary pb-4">
                            <input class="form-check-input" type="checkbox" id="pe_editardescripcionpedidos" name="editardescripcionpedidos" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_editardescripcionpedidos">
                                Permitir editar descripción de producto en Pedidos
                            </label>
                            <div class="text-muted small mt-1">
                                Solo aplica si además está habilitado en Configuración General.
                            </div>
                        </div>

                        <!-- Permiso 8 -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="pe_editardescripcionpresupuestos" name="editardescripcionpresupuestos" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_editardescripcionpresupuestos">
                                Permitir editar descripción de producto en Presupuestos
                            </label>
                            <div class="text-muted small mt-1">
                                Solo aplica si además está habilitado en Configuración General.
                            </div>
                        </div>

                        <!-- Permiso 9 -->
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="pe_modificarmedioscobro" name="modificarmedioscobro" value="1">
                            <label class="form-check-label text-white fw-bold" for="pe_modificarmedioscobro">
                                Permitir modificar medios de cobro de comprobantes emitidos
                            </label>
                            <div class="text-muted small mt-1">
                                Desde Comprobantes Emitidos. Genera ajustes en caja, tesorería, bancos, cuenta corriente y contabilidad. Los administradores siempre pueden.
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-info fw-bold rounded-pill px-4" id="btnGuardarEspeciales" disabled>
                                <i class="fas fa-save me-2"></i>GUARDAR PERMISOS ESPECIALES
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Clonar -->
<div class="modal fade" id="modalClonar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-white"><i class="fa-solid fa-copy text-warning me-2"></i>CLONAR PERMISOS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Seleccione un usuario origen para copiar sus permisos actuales al usuario que está editando. <br><strong class="text-warning">Nota: Solo se listan usuarios NO administradores.</strong></p>
                <div class="mb-3">
                    <label class="form-label-custom">USUARIO ORIGEN (FUENTE)</label>
                    <select class="form-select select2-simple" id="cmbUsuarioFuente" style="width: 100%;">
                        <option value="">SELECCIONE...</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-warning fw-bold rounded-pill" id="btnConfirmarClonacion">
                    <i class="fa-solid fa-check me-2"></i> APLICAR COPIA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let currentUserId = null;
    let currentUserName = "";
    let modalClonar = null;

    // -----------------------------------------------------------------------
    // PERMISOS ESPECIALES
    // -----------------------------------------------------------------------
    async function cargarEspeciales(usuarioNombre, isAdmin) {
        const form    = document.getElementById('especiales-form');
        const placeholder = document.getElementById('especiales-placeholder');
        const btnGrd  = document.getElementById('btnGuardarEspeciales');

        form.style.display = 'block';
        placeholder.style.display = 'none';

        // Deshabilitar si es admin (no aplican restricciones especiales)
        const inputs = form.querySelectorAll('input, select, button');
        inputs.forEach(el => el.disabled = isAdmin);

        if (isAdmin) {
            // Limpiar todo visualmente para admin
            document.getElementById('pe_precioscosto').checked   = false;
            document.getElementById('pe_unvendedor').checked     = false;
            document.getElementById('pe_modificarprecios').checked  = false;
            document.getElementById('pe_facturarpedidos').checked   = false;
            document.getElementById('pe_modificarfacturas').checked = false;
            document.getElementById('pe_editardescripcionfacturar').checked     = false;
            document.getElementById('pe_editardescripcionpedidos').checked      = false;
            document.getElementById('pe_editardescripcionpresupuestos').checked = false;
            document.getElementById('pe_modificarmedioscobro').checked          = false;
            document.getElementById('pe_vendedor_wrapper').style.display = 'none';
            return;
        }

        try {
            const resp = await fetch(`api_niveles_acceso.php?action=get_permisos_especiales&usuario_nombre=${encodeURIComponent(usuarioNombre)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            if (!res.success) throw new Error(res.error);

            const d = res.data;
            document.getElementById('pe_precioscosto').checked      = parseInt(d.precioscosto)      === 1;
            document.getElementById('pe_unvendedor').checked        = parseInt(d.unvendedor)        === 1;
            document.getElementById('pe_modificarprecios').checked  = parseInt(d.modificarprecios)  === 1;
            document.getElementById('pe_facturarpedidos').checked   = parseInt(d.facturarpedidos)   === 1;
            document.getElementById('pe_modificarfacturas').checked = parseInt(d.modificarfacturas) === 1;
            document.getElementById('pe_editardescripcionfacturar').checked     = parseInt(d.editardescripcionfacturar)     === 1;
            document.getElementById('pe_editardescripcionpedidos').checked      = parseInt(d.editardescripcionpedidos)      === 1;
            document.getElementById('pe_editardescripcionpresupuestos').checked = parseInt(d.editardescripcionpresupuestos) === 1;
            document.getElementById('pe_modificarmedioscobro').checked          = parseInt(d.modificarmedioscobro)          === 1;

            // Vendedor
            const wrapper = document.getElementById('pe_vendedor_wrapper');
            if (parseInt(d.unvendedor) === 1) {
                wrapper.style.display = 'block';
                document.getElementById('pe_idvendedor').value = d.idvendedor || 0;
            } else {
                wrapper.style.display = 'none';
            }

            btnGrd.disabled = false;

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los permisos especiales.' });
        }
    }

    async function guardarEspeciales() {
        if (!currentUserName) return;

        const unvendedor = document.getElementById('pe_unvendedor').checked;

        const payload = {
            usuario:           currentUserName,
            precioscosto:      document.getElementById('pe_precioscosto').checked   ? 1 : 0,
            unvendedor:        unvendedor ? 1 : 0,
            idvendedor:        unvendedor ? parseInt(document.getElementById('pe_idvendedor').value || 0) : 0,
            modificarprecios:  document.getElementById('pe_modificarprecios').checked  ? 1 : 0,
            facturarpedidos:   document.getElementById('pe_facturarpedidos').checked   ? 1 : 0,
            modificarfacturas: document.getElementById('pe_modificarfacturas').checked ? 1 : 0,
            editardescripcionfacturar:     document.getElementById('pe_editardescripcionfacturar').checked     ? 1 : 0,
            editardescripcionpedidos:      document.getElementById('pe_editardescripcionpedidos').checked      ? 1 : 0,
            editardescripcionpresupuestos: document.getElementById('pe_editardescripcionpresupuestos').checked ? 1 : 0,
            modificarmedioscobro:          document.getElementById('pe_modificarmedioscobro').checked          ? 1 : 0,
        };

        // Validar que si marcó "un vendedor" tenga uno seleccionado
        if (payload.unvendedor === 1 && payload.idvendedor === 0) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un vendedor para aplicar el filtro.' });
            return;
        }

        try {
            const resp = await fetch('api_niveles_acceso.php?action=save_permisos_especiales', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            });
            const res = await resp.json();
            if (!res.success) throw new Error(res.error || 'Error desconocido');

            Swal.fire({ icon: 'success', title: 'Guardado', text: res.message, timer: 2000, showConfirmButton: false });

        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Error', text: e.message });
        }
    }

    async function init() {
        // Configurar Select2 Principal (Regla Manual: dropdownParent)
        $('#cmbUsuario').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'SELECCIONE UN USUARIO...'
        });

        // Configurar Select2 Modal Clonar (dropdownParent debe ser el modal)
        $('#cmbUsuarioFuente').select2({
            dropdownParent: $('#modalClonar'),
            width: '100%',
            placeholder: 'SELECCIONE...'
        });

        modalClonar = new bootstrap.Modal(document.getElementById('modalClonar'));

        // Cargar Usuarios Principales
        try {
            const resp = await fetch('api_niveles_acceso.php?action=get_users', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            const select = $('#cmbUsuario');
            select.empty().append(new Option('SELECCIONE...', '', true, true));
            
            data.data.forEach(u => {
                select.append(new Option(u.usuario, u.id, false, false));
            });
            select.trigger('change');

        } catch (error) {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los usuarios.' });
        }

        // Cargar vendedores para el select de permisos especiales
        try {
            const resp = await fetch('api_vendedores.php?action=list', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            if (data.success) {
                const sel = document.getElementById('pe_idvendedor');
                data.data.forEach(v => {
                    if (parseInt(v.habilitado) === 1) {
                        const opt = document.createElement('option');
                        opt.value = v.id;
                        opt.textContent = v.nombre;
                        sel.appendChild(opt);
                    }
                });
            }
        } catch (e) {
            console.error('Error cargando vendedores:', e);
        }

        // Toggle vendedor al marcar "Solo un vendedor"
        document.getElementById('pe_unvendedor').addEventListener('change', function () {
            document.getElementById('pe_vendedor_wrapper').style.display = this.checked ? 'block' : 'none';
        });

        // Botón guardar permisos especiales
        document.getElementById('btnGuardarEspeciales').addEventListener('click', guardarEspeciales);
    }

    // Lógica de Cascada (Padre -> Hijos)
    // Se ejecuta al hacer click en cualquier checkbox de permiso
    $('#formPermisos').on('change', '.perm-check', function() {
        const isChecked = $(this).is(':checked');
        
        // Buscar el contenedor de hijos inmediato
        // Estructura: li -> div.form-check -> input(this)
        // Hijos están en: li -> div.children-container
        const liPadre = $(this).closest('li.list-item-permiso');
        const contenedorHijos = liPadre.children('.children-container');

        if (contenedorHijos.length > 0) {
            // Si desmarcamos el padre, desmarcamos TODOS los hijos descendientes
            if (!isChecked) {
                contenedorHijos.find('.perm-check').prop('checked', false);
            }
            // OPCIONAL: Si marcamos el padre, ¿Marcamos los hijos?
            // El requerimiento solo especificó: "si desmarco un item... sus childrens queden deshabilitados"
            // Se asume que habilitar el padre no necesariamente habilita todo lo de adentro por seguridad.
        }
        
        // Lógica Inversa (Hijo -> Padre)
        // Si marco un hijo, DEBO marcar los padres recursivamente para que sea accesible
        if (isChecked) {
            $(this).parents('.children-container').each(function() {
                // Buscar el checkbox hermano del container (el checkbox del padre)
                $(this).siblings('.form-check').find('.perm-check').prop('checked', true);
            });
        }
    });

    // Evento Cambio de Usuario
    $('#cmbUsuario').on('change', async function() {
        const id = $(this).val();
        currentUserId = id;
        currentUserName = $(this).find('option:selected').text();
        const btnGuardar = document.getElementById('btnGuardar');
        const btnClonar = document.getElementById('btnClonar');
        const badge = document.getElementById('admin-badge');
        const checkboxes = document.querySelectorAll('.perm-check');

        // Resetear UI
        checkboxes.forEach(chk => {
            chk.checked = false;
            chk.disabled = true;
        });
        badge.style.display = 'none';
        btnGuardar.disabled = true;
        btnClonar.disabled = true;

        if (!id) return;

        // Cargar Permisos
        try {
            const resp = await fetch(`api_niveles_acceso.php?action=get_permissions&id=${id}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();

            if (data.is_admin) {
                badge.style.display = 'block';
                // Dejamos checkboxes deshabilitados pero visualmente podríamos marcarlos todos si se desea
                // Por lógica del prompt: "Si es administrador, tiene acceso a todo sin restricciones"
                // Deshabilitamos edición.
            } else {
                // Habilitar edición
                checkboxes.forEach(chk => chk.disabled = false);
                btnGuardar.disabled = false;
                btnClonar.disabled = false; // Habilitar clonación

                // Marcar permisos existentes
                if (data.permisos && data.permisos.allowed_ids) {
                    data.permisos.allowed_ids.forEach(menuId => {
                        const chk = document.querySelector(`.perm-check[data-menu-id="${menuId}"]`);
                        if (chk) chk.checked = true;
                    });
                }
            }

            // Siempre cargar permisos especiales (incluso para admins, en modo lectura)
            cargarEspeciales(currentUserName, data.is_admin);

        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al recuperar permisos.' });
        }
    });

    // Abrir Modal Clonar
    document.getElementById('btnClonar').addEventListener('click', async () => {
        // Cargar usuarios "fuente" (Solo NO admins)
        const selectFuente = $('#cmbUsuarioFuente');
        selectFuente.empty().append(new Option('CARGANDO...', '', true, true));
        
        try {
            const resp = await fetch('api_niveles_acceso.php?action=get_users_source', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            selectFuente.empty().append(new Option('SELECCIONE USUARIO ORIGEN...', '', true, true));
            
            data.data.forEach(u => {
                // No listarse a sí mismo
                if (u.id != currentUserId) {
                    selectFuente.append(new Option(u.usuario, u.id, false, false));
                }
            });
            
            modalClonar.show();

        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar la lista de origen.' });
        }
    });

    // Confirmar Clonación
    document.getElementById('btnConfirmarClonacion').addEventListener('click', async () => {
        const sourceId = $('#cmbUsuarioFuente').val();
        const sourceName = $('#cmbUsuarioFuente option:selected').text();

        if (!sourceId) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Debe seleccionar un usuario de origen.' });
            return;
        }

        Swal.fire({
            title: '¿Confirmar copia?',
            text: `Se reemplazarán los permisos marcados actualmente con los de "${sourceName}".`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, COPIAR',
            cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d'
        }).then(async (result) => {
            if (result.isConfirmed) {
                // Obtener permisos del origen
                try {
                    const resp = await fetch(`api_niveles_acceso.php?action=get_permissions&id=${sourceId}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await resp.json();

                    // Limpiar checkboxes actuales
                    const checkboxes = document.querySelectorAll('.perm-check');
                    checkboxes.forEach(chk => chk.checked = false);

                    // Aplicar nuevos checks
                    if (data.permisos && data.permisos.allowed_ids) {
                        data.permisos.allowed_ids.forEach(menuId => {
                            const chk = document.querySelector(`.perm-check[data-menu-id="${menuId}"]`);
                            if (chk) chk.checked = true;
                        });
                    }

                    modalClonar.hide();
                    Swal.fire({
                        icon: 'success', 
                        title: 'Permisos Copiados', 
                        text: 'La configuración ha sido copiada visualmente. Recuerde presionar GUARDAR para aplicar los cambios en la base de datos.',
                        timer: 4000
                    });

                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al obtener permisos del origen.' });
                }
            }
        });
    });

    // Guardar Cambios
    document.getElementById('btnGuardar').addEventListener('click', async () => {
        if (!currentUserId) return;

        const allowedIds = [];
        const allowedUrls = [];

        document.querySelectorAll('.perm-check:checked').forEach(chk => {
            const mid = chk.getAttribute('data-menu-id');
            const url = chk.getAttribute('data-url');
            
            if (mid) allowedIds.push(mid);
            if (url && url.trim() !== '' && url !== '#') allowedUrls.push(url);
        });

        const payload = {
            usuario_id: currentUserId,
            usuario_nombre: currentUserName,
            permisos: {
                allowed_ids: allowedIds,
                allowed_urls: allowedUrls
            }
        };

        try {
            const resp = await fetch('api_niveles_acceso.php?action=save_permissions', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const res = await resp.json();

            if (resp.ok && res.status === 'ok') {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: 'Los permisos se han actualizado correctamente.',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else {
                throw new Error(res.error || 'Error desconocido');
            }

        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        }
    });

    // Botones auxiliares
    document.getElementById('btnUncheckAll').addEventListener('click', (e) => {
        e.preventDefault();
        if(document.getElementById('btnGuardar').disabled) return;
        document.querySelectorAll('.perm-check').forEach(c => c.checked = false);
    });

    document.getElementById('btnCheckAll').addEventListener('click', (e) => {
        e.preventDefault();
        if(document.getElementById('btnGuardar').disabled) return;
        document.querySelectorAll('.perm-check').forEach(c => c.checked = true);
    });

    init();
})();
</script>