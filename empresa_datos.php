<?php
// SEGURIDAD
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white">DATOS DE LA EMPRESA</h4>
            <small class="text-accent opacity-75">Información fiscal y de contacto (Membrete)</small>
        </div>
        <div>
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="guardarEmpresa()">
                <i class="fas fa-save me-2"></i>GUARDAR DATOS
            </button>
        </div>
    </div>

    <form id="formEmpresa">
        <div class="row">
            <!-- Columna Izquierda: Datos Fiscales -->
            <div class="col-md-6">
                <div class="card card-filtros p-4 shadow-sm h-100">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-3"><i class="fas fa-file-invoice me-2"></i>DATOS FISCALES</h6>
                    
                    <div class="mb-3">
                        <label class="form-label-custom">RAZÓN SOCIAL</label>
                        <input type="text" class="form-control" name="razon" id="razon" required>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">CUIT</label>
                            <input type="text" class="form-control" name="cuit" id="cuit" placeholder="Sin guiones" maxlength="11" inputmode="numeric" pattern="\d{11}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">CONDICIÓN IVA</label>
                            <select class="form-select" name="condicioniva" id="condicioniva">
                                <option value="Responsable Inscripto">Responsable Inscripto</option>
                                <option value="Monotributo">Monotributo</option>
                                <option value="Consumidor Final">Consumidor Final</option>
                                <option value="Exento">Exento</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">N° INGRESOS BRUTOS</label>
                            <input type="text" class="form-control" name="numiibb" id="numiibb">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">INICIO ACTIVIDADES</label>
                            <input type="date" class="form-control" name="fechainicioact" id="fechainicioact">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">PUNTO DE VENTA</label>
                            <input type="number" class="form-control" name="puntoventa" id="puntoventa">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Ubicación y Contacto -->
            <div class="col-md-6">
                <div class="card card-filtros p-4 shadow-sm h-100">
                    <h6 class="text-accent border-bottom border-secondary pb-2 mb-3"><i class="fas fa-map-marker-alt me-2"></i>UBICACIÓN Y CONTACTO</h6>
                    
                    <div class="mb-3">
                        <label class="form-label-custom">DOMICILIO COMERCIAL</label>
                        <input type="text" class="form-control" name="domicilio" id="domicilio">
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">LOCALIDAD</label>
                            <input type="text" class="form-control" name="localidad" id="localidad">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">PROVINCIA</label>
                            <select class="form-select select2-provincia" name="provincia" id="provincia">
                                <option value="Buenos Aires">Buenos Aires</option>
                                <option value="CABA">CABA</option>
                                <option value="Catamarca">Catamarca</option>
                                <option value="Chaco">Chaco</option>
                                <option value="Chubut">Chubut</option>
                                <option value="Córdoba">Córdoba</option>
                                <option value="Corrientes">Corrientes</option>
                                <option value="Entre Ríos">Entre Ríos</option>
                                <option value="Formosa">Formosa</option>
                                <option value="Jujuy">Jujuy</option>
                                <option value="La Pampa">La Pampa</option>
                                <option value="La Rioja">La Rioja</option>
                                <option value="Mendoza">Mendoza</option>
                                <option value="Misiones">Misiones</option>
                                <option value="Neuquén">Neuquén</option>
                                <option value="Río Negro">Río Negro</option>
                                <option value="Salta">Salta</option>
                                <option value="San Juan">San Juan</option>
                                <option value="San Luis">San Luis</option>
                                <option value="Santa Cruz">Santa Cruz</option>
                                <option value="Santa Fe">Santa Fe</option>
                                <option value="Santiago del Estero">Santiago del Estero</option>
                                <option value="Tierra del Fuego, Antártida e Isla del Atlántico Sur">Tierra del Fuego</option>
                                <option value="Tucumán">Tucumán</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">TELÉFONO</label>
                        <input type="text" class="form-control" name="telefono" id="telefono">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">EMAIL</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label-custom">SITIO WEB</label>
                        <input type="text" class="form-control" name="web" id="web" placeholder="www.miempresa.com">
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    
    async function init() {
        // Select2 para Provincia (Mejora visual)
        $('.select2-provincia').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            theme: 'default'
        });

        cargarDatos();
    }

    async function cargarDatos() {
        try {
            const resp = await fetch('api_empresa.php?action=get_datos', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const res = await resp.json();

            if (res.success && res.data) {
                const d = res.data;
                
                $('#razon').val(d.razon);
                $('#domicilio').val(d.domicilio);
                $('#localidad').val(d.localidad);
                $('#cuit').val(d.cuit);
                $('#telefono').val(d.telefono);
                $('#email').val(d.email);
                $('#web').val(d.web);
                $('#numiibb').val(d.numiibb);
                $('#fechainicioact').val(d.fechainicioact);
                $('#puntoventa').val(d.puntodeventa)
                
                // Selects
                $('#condicioniva').val(d.condicioniva);
                
                // Provincia con Select2
                $('#provincia').val(d.provincia).trigger('change');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudieron cargar los datos de la empresa', 'error');
        }
    }

    // Réplica en JS de la validación de dígito verificador de api_empresa.php,
    // para avisar antes de mandar el formulario en vez de recién enterarse
    // cuando ARCA rechaza la autorización.
    function cuitEsValido(cuit) {
        if (!/^\d{11}$/.test(cuit)) return false;
        const mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        let suma = 0;
        for (let i = 0; i < 10; i++) suma += parseInt(cuit[i], 10) * mult[i];
        let verificador = 11 - (suma % 11);
        if (verificador === 11) verificador = 0;
        if (verificador === 10) return false;
        return verificador === parseInt(cuit[10], 10);
    }

    window.guardarEmpresa = async function() {
        if (!$('#formEmpresa')[0].checkValidity()) {
            $('#formEmpresa')[0].reportValidity();
            return;
        }

        const cuitVal = $('#cuit').val().trim();
        if (!cuitEsValido(cuitVal)) {
            return Swal.fire('CUIT inválido', 'El CUIT debe tener 11 números y un dígito verificador válido. Revisalo antes de guardar.', 'warning');
        }

        const formData = new FormData(document.getElementById('formEmpresa'));
        
        try {
            const resp = await fetch('api_empresa.php?action=save_datos', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: formData
            });
            const res = await resp.json();

            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Guardado',
                    text: res.message,
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    // Opcional: Actualizar algún elemento global si muestra el nombre de la empresa
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de conexión al guardar', 'error');
        }
    }

    init();
})();
</script>
