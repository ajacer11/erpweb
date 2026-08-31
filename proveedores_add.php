<?php
require_once 'check_access.php';

$id_edit = $_GET['id'] ?? '';

$provincias = [
    "Buenos Aires", "CABA", "Catamarca", "Chaco", "Chubut", "Córdoba", "Corrientes",
    "Entre Ríos", "Formosa", "Jujuy", "La Pampa", "La Rioja", "Mendoza", "Misiones",
    "Neuquén", "Río Negro", "Salta", "San Juan", "San Luis", "Santa Cruz", "Santa Fe",
    "Santiago del Estero", "Tierra del Fuego, Antártida e Isla del Atlántico Sur", "Tucumán"
];
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h4 class="text-white mb-0" id="mod-title"><?= $id_edit ? 'EDITAR PROVEEDOR' : 'ALTA DE PROVEEDOR' ?></h4>
            <small class="text-accent opacity-75">Gestión de información fiscal, comercial y de contacto</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="loadModule('proveedores_admin.php')">
                <i class="fas fa-times me-2"></i>CANCELAR
            </button>
            <button type="button" class="btn btn-info fw-bold rounded-pill px-4 shadow" id="btnGuardar">
                <i class="fas fa-save me-2"></i><?= $id_edit ? 'GUARDAR CAMBIOS' : 'REGISTRAR PROVEEDOR' ?>
            </button>
        </div>
    </div>

    <form id="frmProveedor">
        <input type="hidden" name="id" value="<?= $id_edit ?>">
        
        <div class="row g-3">
            <!-- IDENTIFICACIÓN FISCAL -->
            <div class="col-lg-7">
                <div class="card card-filtros h-100">
                    <div class="card-body p-4">
                        <h6 class="text-accent mb-4 border-bottom border-secondary pb-2"><i class="fas fa-file-invoice me-2"></i>DATOS FISCALES</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-custom">RAZÓN SOCIAL / NOMBRE</label>
                                <input type="text" name="Razon" class="form-control" required maxlength="200">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">TIPO DOC.</label>
                                <select name="tipodoc" class="form-select">
                                    <option value="CUIT">CUIT</option>
                                    <option value="CUIL">CUIL</option>
                                    <option value="DNI">DNI</option>
                                    <option value="OTRO">OTRO</option>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label-custom">NÚMERO</label>
                                <input type="text" name="cuit" id="numdoc" class="form-control" placeholder="SOLO NÚMEROS" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">CONDICIÓN FISCAL</label>
                                <select name="condicion" class="form-select">
                                    <option value="Responsable Inscripto">Responsable Inscripto</option>
                                    <option value="Monotributo">Monotributo</option>
                                    <option value="Exento">Exento</option>
                                    <option value="Consumidor Final">Consumidor Final</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">TIPO PROVEEDOR</label>
                                <input type="text" name="tipo" class="form-control" placeholder="EJ: INSUMOS, SERVICIOS">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONFIGURACIÓN Y ESTADO -->
            <div class="col-lg-5">
                <div class="card card-filtros h-100">
                    <div class="card-body p-4">
                        <h6 class="text-accent mb-4 border-bottom border-secondary pb-2"><i class="fas fa-cog me-2"></i>CONFIGURACIÓN</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label-custom">WEB / SITIO OFICIAL</label>
                                <input type="text" name="web" class="form-control" placeholder="www.proveedor.com">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label-custom">CUENTA CONTABLE PREDET.</label>
                                <select name="cuentacontable" id="cmbCuenta" class="form-select select2-simple"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">APLICA STOCK</label>
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" name="provmerca" value="1" id="chkProvMerca" checked>
                                    <span class="text-muted small ms-2">PROV. MERCADERÍA</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">ESTADO</label>
                                <div class="form-check form-switch mt-1">
                                    <input class="form-check-input" type="checkbox" name="habilitado" value="1" id="chkHabilitado" checked>
                                    <span class="text-muted small ms-2">HABILITADO</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOCALIZACIÓN Y CONTACTO -->
            <div class="col-lg-8">
                <div class="card card-filtros">
                    <div class="card-body p-4">
                        <h6 class="text-accent mb-4 border-bottom border-secondary pb-2"><i class="fas fa-map-marker-alt me-2"></i>DOMICILIO Y CONTACTO PRINCIPAL</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label-custom">DOMICILIO</label>
                                <input type="text" name="domicilio" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">LOCALIDAD</label>
                                <input type="text" name="localidad" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">PROVINCIA</label>
                                <select name="provincia" class="form-select">
                                    <?php foreach ($provincias as $prov): ?>
                                        <option value="<?= $prov ?>" <?= $prov == 'Mendoza' ? 'selected' : '' ?>><?= $prov ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label-custom">CP</label>
                                <input type="text" name="cp" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label-custom">CONTACTO PRINCIPAL</label>
                                <input type="text" name="contacto" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">TELÉFONO</label>
                                <input type="text" name="telefono" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">CELULAR</label>
                                <input type="text" name="celular" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label-custom">EMAIL</label>
                                <input type="email" name="mail" class="form-control">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- NOTAS -->
            <div class="col-lg-4">
                <div class="card card-filtros h-100">
                    <div class="card-body p-4">
                        <h6 class="text-accent mb-4 border-bottom border-secondary pb-2"><i class="fas fa-sticky-note me-2"></i>NOTAS INTERNAS</h6>
                        <textarea name="notas" class="form-control" style="height: 160px;"></textarea>
                    </div>
                </div>
            </div>

            <!-- TABS DE SECTORES ADICIONALES -->
            <div class="col-12 mt-4">
                <ul class="nav nav-tabs" id="contactTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sec1" type="button">SECTOR 1</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sec2" type="button">SECTOR 2</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#sec3" type="button">SECTOR 3</button>
                    </li>
                </ul>
                <div class="tab-content border border-top-0 border-secondary p-4 bg-dark bg-opacity-25 rounded-bottom">
                    <!-- SECTOR 1 -->
                    <div class="tab-pane fade show active" id="sec1">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label-custom">SECTOR</label><input type="text" name="s1sector" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label-custom">CONTACTO</label><input type="text" name="s1contacto" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">TEL.</label><input type="text" name="s1tel" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">CEL.</label><input type="text" name="s1celu" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">EMAIL</label><input type="text" name="s1mail" class="form-control"></div>
                        </div>
                    </div>
                    <!-- SECTOR 2 -->
                    <div class="tab-pane fade" id="sec2">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label-custom">SECTOR</label><input type="text" name="s2sector" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label-custom">CONTACTO</label><input type="text" name="s2contacto" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">TEL.</label><input type="text" name="s2tel" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">CEL.</label><input type="text" name="s2celu" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">EMAIL</label><input type="text" name="s2mail" class="form-control"></div>
                        </div>
                    </div>
                    <!-- SECTOR 3 -->
                    <div class="tab-pane fade" id="sec3">
                        <div class="row g-3">
                            <div class="col-md-3"><label class="form-label-custom">SECTOR</label><input type="text" name="s3sector" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label-custom">CONTACTO</label><input type="text" name="s3contacto" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">TEL.</label><input type="text" name="s3tel" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">CEL.</label><input type="text" name="s3celu" class="form-control"></div>
                            <div class="col-md-2"><label class="form-label-custom">EMAIL</label><input type="text" name="s3mail" class="form-control"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
(function() {
    const idEdit = "<?= $id_edit ?>";
    const commonHeaders = {'X-Requested-With': 'XMLHttpRequest'};

    async function init() {
        // Inicializar Cuenta Contable
        $('#cmbCuenta').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'BUSCAR CUENTA...',
            ajax: { url: 'api_proveedores_add.php?action=get_cuentas', dataType: 'json', delay: 250, headers: commonHeaders }
        });

        if (idEdit) cargarDatosProveedor();
        $('#btnGuardar').on('click', guardar);
    }

    async function cargarDatosProveedor() {
        try {
            Swal.fire({ title: 'Cargando datos...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
            const resp = await fetch(`api_proveedores_add.php?action=get_data&id=${idEdit}`, { headers: commonHeaders });
            const res = await resp.json();
            
            if (res.success) {
                const p = res.data;
                const frm = document.getElementById('frmProveedor');
                
                Object.keys(p).forEach(key => {
                    const el = frm.elements[key];
                    if (el && el.type !== 'checkbox') el.value = p[key];
                });

                $('#chkProvMerca').prop('checked', p.provmerca == 1);
                $('#chkHabilitado').prop('checked', p.habilitado == 1);

                if (p.cuentacontable && p.cuentacontable != '0') {
                    $('#cmbCuenta').append(new Option(p.nombre_cuenta, p.cuentacontable, true, true)).trigger('change');
                }

                Swal.close();
            } else {
                throw new Error(res.error);
            }
        } catch (e) {
            Swal.fire('Error', 'No se pudieron cargar los datos: ' + e.message, 'error');
        }
    }

    function validacuit(cuit) {
        if (!cuit) return false;
        cuit = cuit.replace(/[-_]/g, "");
        if (cuit.length != 11) return false;
        const mult = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        let total = 0;
        for (let i = 0; i < 10; i++) total += parseInt(cuit[i]) * mult[i];
        let mod = total % 11;
        let digito = mod === 0 ? 0 : mod === 1 ? 9 : 11 - mod;
        return digito === parseInt(cuit[10]);
    }

    async function guardar() {
        const frm = document.getElementById('frmProveedor');
        if (!frm.checkValidity()) {
            frm.reportValidity();
            return;
        }

        const tipoDoc = frm.tipodoc.value;
        const numDoc = frm.cuit.value;
        if (tipoDoc === 'CUIT' && !validacuit(numDoc)) {
            Swal.fire({ icon: 'error', title: 'Documento Inválido', text: 'El número de CUIT ingresado es INVÁLIDO' });
            return;
        }

        const data = Object.fromEntries(new FormData(frm).entries());
        data.provmerca = $('#chkProvMerca').is(':checked') ? 1 : 0;
        data.habilitado = $('#chkHabilitado').is(':checked') ? 1 : 0;

        try {
            Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
            const resp = await fetch('api_proveedores_add.php?action=save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(data)
            });
            const res = await resp.json();
            if (res.success) {
                await Swal.fire({ icon: 'success', title: '¡Completado!', text: idEdit ? 'Proveedor actualizado' : 'Proveedor registrado', timer: 2000 });
                loadModule('proveedores_admin.php');
            } else {
                throw new Error(res.error);
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }

    init();
})();
</script>
