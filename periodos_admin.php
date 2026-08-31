<?php
/**
 * periodos_admin.php - VISTA FRAGMENTO (SPA)
 * Migración fiel de abrirocerrarperiodos.vb
 */
require_once 'check_access.php';
?>

<div class="container-fluid fade-in" id="container-periodos">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center border-bottom border-secondary pb-2">
            <div>
                <h4 class="text-white mb-0" style="letter-spacing: 2px; font-weight: 300;">ADMINISTRAR PERÍODOS</h4>
                <small class="text-accent">APERTURA Y CIERRE DE PERÍODOS CONTABLES</small>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
        </div>
    </div>

    <div class="card border-secondary shadow mb-4" style="background-color: var(--bg-card, #1a1a1a); color: var(--text-main, #fff);">
        <div class="card-body">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <label class="form-label text-accent small">SELECCIONAR EJERCICIO</label>
                    <select id="cmbEjercicio" class="form-select select2-dark" style="width: 100%;">
                        <option value="">-- SELECCIONAR --</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel Dinámico (FlowLayoutPanel en VB) -->
    <form id="frmPeriodos">
        <input type="hidden" name="idejercicio" id="hiddenIdEjercicio">
        
        <div id="panelPeriodos" class="row g-3">
            <!-- Los períodos se generarán aquí dinámicamente -->
            <div class="col-12 text-center text-muted py-5">
                <i class="fa-solid fa-arrow-up mb-2"></i><br>
                Seleccione un ejercicio para visualizar sus períodos.
            </div>
        </div>

        <!-- Botonera -->
        <div id="botonera" class="row mt-4 d-none">
            <div class="col-12 d-flex justify-content-center gap-3 pt-3 border-top border-secondary">
                <button type="submit" class="btn btn-erp-action" style="background-color: #004000; color: white; border: 1px solid #000; width: 120px; padding: 10px;">
                    <i class="fa-solid fa-save d-block mb-1 fs-5"></i>
                    <span style="font-size: 10px; font-weight: bold;">GUARDAR</span>
                </button>
                <button type="button" class="btn btn-erp-action" onclick="loadModule('dashboard.php')" style="background-color: #800000; color: white; border: 1px solid #000; width: 120px; padding: 10px;">
                    <i class="fa-solid fa-xmark d-block mb-1 fs-5"></i>
                    <span style="font-size: 10px; font-weight: bold;">CANCELAR</span>
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    (function() {
        /**
         * Inicialización
         */
        async function init() {
            // Cargar Ejercicios
            try {
                const resp = await fetch('api_periodos.php?action=get_exercises', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    let options = '<option value="">-- SELECCIONAR --</option>';
                    res.data.forEach(e => {
                        options += `<option value="${e.id}">${e.descripcion}</option>`;
                    });
                    $('#cmbEjercicio').html(options);

                    // Estilo Select2 Global
                    $('.select2-dark').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#main-content'),
                        language: 'es'
                    });
                }
            } catch (err) {
                console.error("Error al cargar ejercicios", err);
            }
        }

        /**
         * Cambio de Selección: Cargar Períodos
         * Replica ComboBoxEjercicios_SelectedIndexChanged
         */
        $('#cmbEjercicio').on('change', async function() {
            const id = $(this).val();
            $('#hiddenIdEjercicio').val(id);
            const container = $('#panelPeriodos');
            const botonera = $('#botonera');

            if (!id) {
                container.html('<div class="col-12 text-center text-muted py-5">Seleccione un ejercicio.</div>');
                botonera.addClass('d-none');
                return;
            }

            try {
                $('#loading-overlay').show();
                const resp = await fetch(`api_periodos.php?action=get_periods&id=${id}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    let html = '';
                    
                    if (res.data.length === 0) {
                        html = '<div class="col-12 text-center text-warning">No se encontraron períodos definidos para este ejercicio en la tabla ejperidodos.</div>';
                        botonera.addClass('d-none');
                    } else {
                        // Generación Dinámica (Replicando GenerarControlesPeriodos)
                        res.data.forEach(p => {
                            const isAbierto = p.estado == 1;
                            const isCerrado = p.estado == 0;
                            
                            html += `
                            <div class="col-md-4 col-lg-3">
                                <div class="card bg-dark border-secondary h-100">
                                    <div class="card-header bg-dark-soft border-bottom border-secondary py-2">
                                        <span class="text-white fw-bold small"><i class="fa-regular fa-calendar me-2 text-accent"></i>${p.label}</span>
                                    </div>
                                    <div class="card-body d-flex align-items-center justify-content-center gap-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${p.col}" id="${p.col}_abierto" value="1" ${isAbierto ? 'checked' : ''}>
                                            <label class="form-check-label text-success small fw-bold" for="${p.col}_abierto">ABIERTO</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="${p.col}" id="${p.col}_cerrado" value="0" ${isCerrado ? 'checked' : ''}>
                                            <label class="form-check-label text-danger small fw-bold" for="${p.col}_cerrado">CERRADO</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            `;
                        });
                        botonera.removeClass('d-none');
                    }
                    container.html(html);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Error de comunicación', 'error');
            } finally {
                $('#loading-overlay').hide();
            }
        });

        /**
         * Guardar Cambios (BtnGuardar_Click)
         */
        $('#frmPeriodos').on('submit', async function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: '¿Guardar cambios?',
                text: "Se actualizará el estado de los períodos seleccionados.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#004000',
                cancelButtonColor: '#800000',
                confirmButtonText: 'Sí, Guardar',
                cancelButtonText: 'Cancelar'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        $('#loading-overlay').show();
                        const formData = new FormData(this);
                        
                        const resp = await fetch('api_periodos.php?action=save', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const res = await resp.json();

                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 2000, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', res.error, 'error');
                        }
                    } catch (err) {
                        Swal.fire('Error', 'Error al guardar', 'error');
                    } finally {
                        $('#loading-overlay').hide();
                    }
                }
            });
        });

        init();
    })();
</script>