<?php
/**
 * productos_mod_codigo.php - VISTA FRAGMENTO (SPA)
 * Migración fiel de "Productos - Modificar codigo.vb"
 */
require_once 'check_access.php';
?>

<div class="container-fluid fade-in" id="container-mod-codigo">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center border-bottom border-secondary pb-2">
            <div>
                <h4 class="text-white mb-0" style="letter-spacing: 2px; font-weight: 300;">MODIFICAR CÓDIGOS</h4>
                <small class="text-accent">ADMINISTRACIÓN DE PRODUCTOS - SOLO ADMINS</small>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
        </div>
    </div>

    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-5">
            <div class="card border-secondary shadow" style="background-color: var(--bg-card, #1a1a1a); color: var(--text-main, #fff);">
                <div class="card-body p-4">
                    <form id="frmModCodigo">
                        
                        <!-- CÓDIGO ACTUAL (TextBox1) -->
                        <div class="mb-3">
                            <label class="form-label text-accent small">CÓDIGO ACTUAL</label>
                            <div class="input-group">
                                <input type="text" name="codigo_actual" id="txtCodigoActual" class="form-control bg-dark text-white border-secondary fw-bold" autocomplete="off" required>
                                <button type="button" class="btn btn-info" id="btnBuscar" title="Buscar Producto">
                                    <i class="fa-solid fa-magnifying-glass"></i>
                                </button>
                            </div>
                        </div>

                        <!-- DESCRIPCIÓN (Label2) -->
                        <div class="mb-3 text-center">
                            <label class="small text-secondary">DESCRIPCIÓN DEL PRODUCTO</label>
                            <div id="lblDescripcion" class="p-2 border border-secondary rounded bg-dark text-white fw-bold" style="min-height: 40px;">
                                <!-- Se llena dinámicamente -->
                            </div>
                        </div>

                        <hr class="border-secondary opacity-50 my-4">

                        <!-- NUEVO CÓDIGO (TextBox2) -->
                        <div class="mb-4">
                            <label class="form-label text-accent small">NUEVO CÓDIGO</label>
                            <input type="text" name="nuevo_codigo" id="txtNuevoCodigo" class="form-control bg-dark text-white border-secondary fw-bold" autocomplete="off" disabled>
                        </div>

                        <!-- BOTONERA (IconButton1 y IconButton2) -->
                        <div class="d-flex justify-content-center gap-3 pt-2">
                            <button type="submit" class="btn btn-erp-action" id="btnGuardar" disabled style="background-color: #004000; color: white; border: 1px solid #000; width: 140px; padding: 10px;">
                                <i class="fa-solid fa-check d-block mb-1 fs-5"></i>
                                <span style="font-size: 10px; font-weight: bold;">GUARDAR</span>
                            </button>
                            <button type="button" class="btn btn-erp-action" onclick="loadModule('dashboard.php')" style="background-color: #800000; color: white; border: 1px solid #000; width: 140px; padding: 10px;">
                                <i class="fa-solid fa-xmark d-block mb-1 fs-5"></i>
                                <span style="font-size: 10px; font-weight: bold;">CANCELAR</span>
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
        // Función Buscar (Button1_Click / TextBox1_KeyDown)
        async function buscarProducto() {
            const codigo = $('#txtCodigoActual').val().trim();
            if (!codigo) {
                Swal.fire('Atención', 'Ingrese un código para buscar.', 'warning');
                return;
            }

            try {
                $('#loading-overlay').show();
                // Limpiar estados previos
                $('#lblDescripcion').text('');
                $('#txtNuevoCodigo').val('').prop('disabled', true);
                $('#btnGuardar').prop('disabled', true);

                const resp = await fetch(`api_productos_mod_codigo.php?action=search&codigo=${encodeURIComponent(codigo)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    $('#lblDescripcion').text(res.data.descripcion);
                    
                    if (res.data.allow_edit) {
                        // Habilitar edición si no tiene movimientos
                        $('#txtNuevoCodigo').prop('disabled', false).focus();
                        $('#btnGuardar').prop('disabled', false);
                    } else {
                        // Bloquear y avisar (Lógica original VB)
                        Swal.fire({
                            icon: 'warning',
                            title: 'Bloqueado',
                            text: 'No se puede modificar este código porque ya tiene movimientos.',
                            confirmButtonColor: '#d33'
                        });
                    }
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
            } finally {
                $('#loading-overlay').hide();
            }
        }

        // Eventos
        $('#btnBuscar').on('click', buscarProducto);
        
        $('#txtCodigoActual').on('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarProducto();
            }
        });

        // Guardar (IconButton1_Click)
        $('#frmModCodigo').on('submit', async function(e) {
            e.preventDefault();
            
            const codActual = $('#txtCodigoActual').val().trim();
            const codNuevo = $('#txtNuevoCodigo').val().trim();

            if (!codNuevo) {
                Swal.fire('Atención', 'Ingrese el nuevo código.', 'warning');
                return;
            }
            if (codActual === codNuevo) {
                Swal.fire('Atención', 'El nuevo código debe ser diferente al actual.', 'warning');
                return;
            }

            Swal.fire({
                title: '¿Confirmar cambio?',
                text: `Se modificará el código '${codActual}' por '${codNuevo}' en todas las tablas relacionadas.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004000',
                cancelButtonColor: '#800000',
                confirmButtonText: 'Sí, Modificar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        $('#loading-overlay').show();
                        const formData = new FormData();
                        formData.append('codigo_actual', codActual);
                        formData.append('nuevo_codigo', codNuevo);

                        const resp = await fetch('api_productos_mod_codigo.php?action=update', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const res = await resp.json();

                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Éxito',
                                text: 'Código de producto modificado correctamente.',
                                timer: 2000,
                                showConfirmButton: false
                            }).then(() => {
                                // Limpiar formulario (Lógica original)
                                $('#txtCodigoActual').val('').focus();
                                $('#lblDescripcion').text('');
                                $('#txtNuevoCodigo').val('').prop('disabled', true);
                                $('#btnGuardar').prop('disabled', true);
                            });
                        } else {
                            Swal.fire('Error', res.error, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Error', 'Error crítico al procesar la solicitud.', 'error');
                    } finally {
                        $('#loading-overlay').hide();
                    }
                }
            });
        });
    })();
</script>