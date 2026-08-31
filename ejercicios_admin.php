<?php
/**
 * ejercicios_admin.php - VISTA FRAGMENTO (SPA)
 * Migración fiel de abmejercicios.vb
 */
require_once 'check_access.php';
?>

<div class="container-fluid fade-in" id="container-ejercicios">
    <div class="row mb-3">
        <div class="col-12 d-flex justify-content-between align-items-center border-bottom border-secondary pb-2">
            <div>
                <h4 class="text-white mb-0" style="letter-spacing: 2px; font-weight: 300;">NUEVO EJERCICIO</h4>
                <small class="text-accent">MÓDULO DE APERTURA DE EJERCICIOS CONTABLES</small>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
        </div>
    </div>

    <div class="row justify-content-center mt-4">
        <div class="col-md-6 col-lg-5">
            <!-- CORRECCIÓN: Uso de variables CSS globales para asegurar fondo oscuro -->
            <div class="card border-secondary shadow" style="background-color: var(--bg-card, #1a1a1a); color: var(--text-main, #fff);">
                <div class="card-body p-4">
                    <form id="frmEjercicio">
                        <!-- Label1 / txtdescripcion con Botón Mágico (Button1 de VB) -->
                        <div class="mb-3">
                            <label class="form-label text-accent small">DESCRIPCIÓN DEL EJERCICIO</label>
                            <div class="input-group">
                                <input type="text" name="descripcion" id="txtdescripcion" class="form-control bg-dark text-white border-secondary" maxlength="100" autocomplete="off">
                                <button type="button" class="btn btn-outline-info" id="btnGenerarDesc" title="Generar Descripción Automática">
                                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                                </button>
                            </div>
                        </div>

                        <div class="row g-3">
                            <!-- Label2 / cmbmes -->
                            <div class="col-md-6">
                                <label class="form-label text-accent small">MES DE INICIO</label>
                                <select name="mesinicio" id="cmbmes" class="form-select select2-dark" required>
                                    <option value="">-- SELECCIONAR --</option>
                                </select>
                            </div>
                            
                            <!-- Label3 / txtanio -->
                            <div class="col-md-6">
                                <label class="form-label text-accent small">AÑO DE INICIO</label>
                                <input type="number" name="anioinicio" id="txtanio" class="form-control bg-dark text-white border-secondary" placeholder="AAAA" min="2000" max="2100">
                            </div>
                        </div>

                        <!-- Label4 / txtmeses -->
                        <div class="mb-4 mt-3">
                            <label class="form-label text-accent small">CANTIDAD DE MESES (1-12)</label>
                            <input type="number" name="meses" id="txtmeses" class="form-control bg-dark text-white border-secondary" value="12" min="1" max="12">
                        </div>

                        <!-- Botonera Replicada (IconButton1 y IconButton2) -->
                        <div class="d-flex justify-content-center gap-3 pt-3 border-top border-secondary">
                            <button type="submit" class="btn btn-erp-action" style="background-color: #004000; color: white; border: 1px solid #000; width: 120px; padding: 10px;">
                                <i class="fa-solid fa-check d-block mb-1 fs-5"></i>
                                <span style="font-size: 10px; font-weight: bold;">AGREGAR</span>
                            </button>
                            <button type="button" class="btn btn-erp-action" onclick="loadModule('dashboard.php')" style="background-color: #800000; color: white; border: 1px solid #000; width: 120px; padding: 10px;">
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
         * Carga de combos iniciales vía API
         */
        async function initForm() {
            try {
                const resp = await fetch('api_ejercicios.php?action=get_combos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    let options = '<option value="">-- SELECCIONAR --</option>';
                    Object.entries(res.data.meses).forEach(([nombre, valor]) => {
                        options += `<option value="${valor}">${nombre.toUpperCase()}</option>`;
                    });
                    $('#cmbmes').html(options);

                    // Inicialización de Select2 utilizando los estilos globales
                    $('.select2-dark').select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#main-content'),
                        language: 'es'
                    });
                }
            } catch (err) {
                console.error("Error cargando combos:", err);
            }
        }

        /**
         * Lógica de Generación Automática (Replicando Button1_Click + ObtenerFechas de VB.NET)
         */
        $('#btnGenerarDesc').on('click', function() {
            const anio = parseInt($('#txtanio').val());
            const mes = parseInt($('#cmbmes').val());
            const duracion = parseInt($('#txtmeses').val());

            // Validación parcial (validar(1) en VB)
            if (!anio || !mes || !duracion) {
                Swal.fire('Atención', 'Complete Año, Mes de Inicio y Cantidad de Meses para generar la descripción.', 'warning');
                return;
            }

            const mesesNombres = ["", "ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"];

            // Cálculo de Fecha Fin (Lógica DateAdd("m", meses, fechaInicio).AddDays(-1))
            // En JS: new Date(año, mesIndex, 0) obtiene el último día del mes anterior.
            // mes es 1-based, JS Date es 0-based.
            // Fin = (mes - 1) + duracion
            
            const fechaFin = new Date(anio, (mes - 1) + duracion, 0);
            const mesFinNombre = mesesNombres[fechaFin.getMonth() + 1]; // getMonth() es 0-11
            const anioFin = fechaFin.getFullYear();
            const mesInicioNombre = mesesNombres[mes];

            // Formato: "{NombreMes} {Año} - {NombreMesFin} {AñoFin}"
            const descripcion = `${mesInicioNombre} ${anio} - ${mesFinNombre} ${anioFin}`;
            $('#txtdescripcion').val(descripcion);
        });

        /**
         * Validación y envío (Replicando Private Function validar)
         */
        $('#frmEjercicio').on('submit', async function(e) {
            e.preventDefault();
            
            const desc = $('#txtdescripcion').val().trim();
            const anio = $('#txtanio').val();
            const meses = $('#txtmeses').val();
            const mesInicio = $('#cmbmes').val();

            if (!desc) { Swal.fire('Validación', 'La descripción no puede estar vacía', 'warning'); return; }
            if (!anio) { Swal.fire('Validación', 'Ingrese el año de inicio', 'warning'); return; }
            if (!meses) { Swal.fire('Validación', 'Ingrese la cantidad de meses', 'warning'); return; }
            if (meses < 1 || meses > 12) { Swal.fire('Validación', 'La cantidad de meses ingresada no es válida, ingrese un valor entre 1 y 12', 'warning'); return; }
            if (!mesInicio) { Swal.fire('Validación', 'Ingrese el mes de inicio', 'warning'); return; }

            Swal.fire({
                title: '¿Confirmar apertura?',
                text: "¿Desea abrir el nuevo ejercicio contable con " + meses + " meses?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#004000',
                cancelButtonColor: '#800000',
                confirmButtonText: 'Sí, Abrir Ejercicio',
                cancelButtonText: 'No'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        $('#loading-overlay').show();
                        const formData = new FormData(this);
                        const resp = await fetch('api_ejercicios.php?action=save', {
                            method: 'POST',
                            body: formData,
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const res = await resp.json();

                        if (res.success) {
                            Swal.fire({ icon: 'success', title: 'Éxito', text: 'Ejercicio creado correctamente', timer: 2000, showConfirmButton: false });
                            loadModule('dashboard.php');
                        } else {
                            Swal.fire('Error', res.error || 'No se pudo crear el ejercicio', 'error');
                        }
                    } catch (err) {
                        Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
                    } finally {
                        $('#loading-overlay').hide();
                    }
                }
            });
        });

        initForm();
    })();
</script>