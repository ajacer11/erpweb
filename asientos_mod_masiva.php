<?php
/**
 * asientos_mod_masiva.php - VISTA FRAGMENTO (SPA)
 * Migración fiel de cambiarasientosmasivo.vb
 */
require_once 'check_access.php';
?>

<div class="container-fluid fade-in" id="container-mod-masiva">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center border-bottom border-secondary pb-2">
            <div>
                <h4 class="text-white mb-0" style="letter-spacing: 2px; font-weight: 300;">MODIFICACIÓN MASIVA</h4>
                <small class="text-accent">REEMPLAZO DE CUENTAS EN LIBRO DIARIO</small>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
        </div>
    </div>

    <div class="row justify-content-center mt-4">
        <div class="col-lg-8">
            <div class="card border-secondary shadow" style="background-color: var(--bg-card, #1a1a1a); color: var(--text-main, #fff);">
                <div class="card-body p-4">
                    <form id="frmModMasiva">
                        
                        <!-- SELECCIÓN DE CUENTAS -->
                        <div class="row mb-4">
                            <div class="col-12 mb-3">
                                <label class="form-label text-accent small">CUENTA ORIGINAL (A REEMPLAZAR)</label>
                                <select name="cuenta_origen" id="cmbCuentaOrigen" class="form-select select2-dark" required>
                                    <option value="">-- SELECCIONAR --</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-accent small">CUENTA DESTINO (NUEVA ASIGNACIÓN)</label>
                                <select name="cuenta_destino" id="cmbCuentaDestino" class="form-select select2-dark" required>
                                    <option value="">-- SELECCIONAR --</option>
                                </select>
                            </div>
                        </div>

                        <hr class="border-secondary opacity-50">

                        <!-- FILTROS -->
                        <h6 class="text-white mb-3"><i class="fa-solid fa-filter me-2"></i>FILTROS DE ACTUALIZACIÓN</h6>
                        
                        <div class="row g-3">
                            <!-- Filtro Ejercicio (obligatorio: un reemplazo masivo nunca se aplica a todo el histórico) -->
                            <div class="col-md-6">
                                <div class="card bg-dark border-secondary h-100">
                                    <div class="card-body p-3">
                                        <label class="form-label small text-white mb-2" for="cmbEjercicio">EJERCICIO (OBLIGATORIO)</label>
                                        <select name="ejercicio" id="cmbEjercicio" class="form-select form-select-sm bg-dark text-white border-secondary" required>
                                            <option value="">-- SELECCIONAR --</option>
                                        </select>
                                        <small class="text-muted d-block mt-1">El reemplazo siempre se limita a un único ejercicio, para no afectar todo el historial contable de una vez.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtro Fechas -->
                            <div class="col-md-6">
                                <div class="card bg-dark border-secondary h-100">
                                    <div class="card-body p-3">
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" id="chkFechas" name="usar_fechas">
                                            <label class="form-check-label small text-white" for="chkFechas">FILTRAR POR FECHAS</label>
                                        </div>
                                        <div class="input-group input-group-sm">
                                            <input type="date" name="fecha_desde" id="dtmDesde" class="form-control bg-dark text-white border-secondary" disabled>
                                            <span class="input-group-text bg-dark border-secondary text-secondary">-</span>
                                            <input type="date" name="fecha_hasta" id="dtmHasta" class="form-control bg-dark text-white border-secondary" disabled>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Filtro Fiscal (RadioButtons) -->
                            <div class="col-12">
                                <div class="card bg-dark border-secondary">
                                    <div class="card-body p-3 d-flex justify-content-around">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filtro_fiscal" id="rbTodos" value="todos" checked>
                                            <label class="form-check-label text-white small" for="rbTodos">TODOS</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filtro_fiscal" id="rbFiscales" value="1">
                                            <label class="form-check-label text-white small" for="rbFiscales">SOLO FISCALES</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="filtro_fiscal" id="rbNoFiscales" value="0">
                                            <label class="form-check-label text-white small" for="rbNoFiscales">SOLO NO FISCALES</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botonera -->
                        <div class="d-flex justify-content-center gap-3 mt-4 pt-3 border-top border-secondary">
                            <button type="submit" class="btn btn-erp-action" style="background-color: #004000; color: white; border: 1px solid #000; width: 140px; padding: 10px;">
                                <i class="fa-solid fa-gears d-block mb-1 fs-5"></i>
                                <span style="font-size: 10px; font-weight: bold;">PROCESAR</span>
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
        /**
         * Inicialización y Carga de Combos
         */
        async function init() {
            try {
                const resp = await fetch('api_asientos_mod_masiva.php?action=get_combos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    // Cargar Cuentas (Origen y Destino)
                    let optCuentas = '<option value="">-- SELECCIONAR CUENTA --</option>';
                    res.data.cuentas.forEach(c => {
                        optCuentas += `<option value="${c.numcuenta}">${c.numcuenta} - ${c.descripcion}</option>`;
                    });
                    $('#cmbCuentaOrigen').html(optCuentas);
                    $('#cmbCuentaDestino').html(optCuentas);

                    // Cargar Ejercicios
                    let optEjer = '<option value="">-- SELECCIONAR --</option>';
                    res.data.ejercicios.forEach(e => {
                        optEjer += `<option value="${e.id}">${e.descripcion}</option>`;
                    });
                    $('#cmbEjercicio').html(optEjer);

                    // Inicializar Select2
                    $('.select2-dark').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#main-content'),
                        language: 'es'
                    });
                }
            } catch (err) {
                console.error("Error al cargar combos", err);
            }
        }

        /**
         * Control de Estado de Filtros (Checkbox logic)
         */
        $('#chkFechas').on('change', function() {
            $('#dtmDesde, #dtmHasta').prop('disabled', !this.checked);
            if (!this.checked) {
                $('#dtmDesde, #dtmHasta').val('');
            }
        });

        /**
         * Procesar (Submit)
         */
        $('#frmModMasiva').on('submit', async function(e) {
            e.preventDefault();
            
            // Validaciones básicas de cliente (fidelidad a VB)
            const origen = $('#cmbCuentaOrigen').val();
            const destino = $('#cmbCuentaDestino').val();
            
            if (!origen || !destino) {
                Swal.fire('Atención', 'Debe seleccionar tanto la cuenta de origen como la de destino.', 'warning');
                return;
            }

            if (origen === destino) {
                Swal.fire('Atención', 'La cuenta de origen y destino no pueden ser la misma.', 'warning');
                return;
            }

            // Validar filtros activos
            if (!$('#cmbEjercicio').val()) {
                Swal.fire('Atención', 'Seleccione un Ejercicio: el reemplazo siempre debe acotarse a uno.', 'warning');
                return;
            }
            if ($('#chkFechas').is(':checked') && (!$('#dtmDesde').val() || !$('#dtmHasta').val())) {
                Swal.fire('Atención', 'Complete el rango de fechas o desmarque la casilla de filtro.', 'warning');
                return;
            }

            // No hay deshacer para este reemplazo, así que en vez de eso pedimos
            // dos confirmaciones: la primera general, la segunda mostrando la
            // cantidad real de renglones que se van a modificar.
            const formData = new FormData(this);

            const primeraConfirmacion = await Swal.fire({
                title: '¿Confirmar reemplazo masivo?',
                text: "Esta acción modificará permanentemente los asientos contables que coincidan con los filtros. Esta operación NO se puede deshacer.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#004000',
                cancelButtonColor: '#800000',
                confirmButtonText: 'Continuar',
                cancelButtonText: 'Cancelar'
            });
            if (!primeraConfirmacion.isConfirmed) return;

            let cantidadAfectada = null;
            try {
                $('#loading-overlay').show();
                const respCount = await fetch('api_asientos_mod_masiva.php?action=preview_count', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const resCount = await respCount.json();
                if (!resCount.success) throw new Error(resCount.error || 'No se pudo calcular cuántos registros se verían afectados.');
                cantidadAfectada = resCount.count;
            } catch (err) {
                $('#loading-overlay').hide();
                Swal.fire('Error', err.message || 'Error de comunicación con el servidor.', 'error');
                return;
            }
            $('#loading-overlay').hide();

            if (cantidadAfectada === 0) {
                Swal.fire('Sin resultados', 'Ningún asiento coincide con esos filtros, no hay nada para reemplazar.', 'info');
                return;
            }

            const segundaConfirmacion = await Swal.fire({
                title: 'Confirmación final',
                html: `Se van a modificar <b>${cantidadAfectada}</b> renglones de asientos contables. Esta acción es permanente e irreversible.<br><br>¿Confirmás que querés continuar?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#800000',
                cancelButtonColor: '#004000',
                confirmButtonText: `Sí, reemplazar ${cantidadAfectada} registros`,
                cancelButtonText: 'Cancelar'
            });
            if (!segundaConfirmacion.isConfirmed) return;

            try {
                $('#loading-overlay').show();

                const resp = await fetch('api_asientos_mod_masiva.php?action=process', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Proceso Finalizado',
                        text: res.message
                    }).then(() => {
                        // Opción: recargar o cerrar según flujo VB
                        loadModule('dashboard.php');
                    });
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
            } finally {
                $('#loading-overlay').hide();
            }
        });

        init();
    })();
</script>