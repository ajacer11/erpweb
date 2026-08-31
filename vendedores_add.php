<?php
/**
 * vendedores_add.php - ALTA Y MODIFICACIÓN DE VENDEDORES
 */
require_once 'check_access.php';

$id_edit = $_GET['id'] ?? '';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h4 class="text-white mb-0" id="vendedor-title"><?= $id_edit ? 'EDITAR VENDEDOR' : 'NUEVO VENDEDOR' ?></h4>
            <small class="text-accent opacity-75">Gestión de recursos humanos y comisiones de venta</small>
        </div>
        <button onclick="loadModule('vendedores_admin.php')" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
            <i class="fas fa-arrow-left me-2"></i>VOLVER AL LISTADO
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-10">
            <form id="form-vendedor" class="card card-filtros shadow-lg">
                <input type="hidden" name="id" id="v-id" value="<?= $id_edit ?>">
                <div class="card-body p-4 text-start">
                    <div class="row g-3">
                        <!-- Datos Principales -->
                        <div class="col-md-6">
                            <label class="form-label-custom">NOMBRE COMPLETO / RAZÓN</label>
                            <input type="text" name="nombre" id="v-nombre" class="form-control bg-dark border-secondary text-white" required placeholder="Ej: Juan Pérez">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">COMISIÓN (%)</label>
                            <input type="number" name="comision" id="v-comision" class="form-control bg-dark border-secondary text-white text-end" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="habilitado" id="v-habilitado" checked>
                                <label class="form-check-label text-white small" for="v-habilitado">Vendedor Habilitado</label>
                            </div>
                        </div>

                        <hr class="border-secondary my-4">

                        <!-- Contacto -->
                        <div class="col-md-6">
                            <label class="form-label-custom">DIRECCIÓN</label>
                            <input type="text" name="direccion" id="v-direccion" class="form-control" placeholder="Calle, Número, Piso...">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">LOCALIDAD</label>
                            <input type="text" name="localidad" id="v-localidad" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">PROVINCIA</label>
                            <input type="text" name="provincia" id="v-provincia" class="form-control">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label-custom">CÓD. POSTAL</label>
                            <input type="text" name="cp" id="v-cp" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">TELÉFONO</label>
                            <input type="text" name="telefono" id="v-telefono" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">CELULAR</label>
                            <input type="text" name="celular" id="v-celular" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label-custom">EMAIL</label>
                            <input type="email" name="mail" id="v-mail" class="form-control" placeholder="ejemplo@correo.com">
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-dark border-top border-secondary p-3 text-end">
                    <button type="button" class="btn btn-outline-secondary me-2" id="btn-limpiar-v">LIMPIAR CAMPOS</button>
                    <button type="submit" class="btn btn-info fw-bold px-5 rounded-pill shadow" id="btn-guardar-v">
                        <i class="fas fa-save me-2"></i><?= $id_edit ? 'MODIFICAR VENDEDOR' : 'GUARDAR VENDEDOR' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const idEdit = $('#v-id').val();
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        if (idEdit) await cargarDatos();
        $('#btn-limpiar-v').click(limpiarForm);
        $('#form-vendedor').submit(guardarVendedor);
    }

    async function cargarDatos() {
        try {
            const res = await fetch(`api_vendedores.php?action=get&id=${idEdit}`, { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                const v = res.data;
                $('#v-nombre').val(v.nombre);
                $('#v-direccion').val(v.direccion);
                $('#v-localidad').val(v.localidad);
                $('#v-provincia').val(v.provincia);
                $('#v-cp').val(v.cp);
                $('#v-telefono').val(v.telefono);
                $('#v-celular').val(v.celular);
                $('#v-comision').val(v.comision);
                $('#v-mail').val(v.mail);
                $('#v-habilitado').prop('checked', v.habilitado == 1);
            }
        } catch (e) { console.error(e); }
    }

    function limpiarForm() {
        $('#form-vendedor')[0].reset();
        $('#v-id').val('');
        $('#v-habilitado').prop('checked', true);
        if(idEdit) loadModule('vendedores_add.php');
    }

    async function guardarVendedor(e) {
        e.preventDefault();
        const data = {
            id: $('#v-id').val(),
            nombre: $('#v-nombre').val(),
            direccion: $('#v-direccion').val(),
            localidad: $('#v-localidad').val(),
            provincia: $('#v-provincia').val(),
            cp: $('#v-cp').val(),
            telefono: $('#v-telefono').val(),
            celular: $('#v-celular').val(),
            comision: $('#v-comision').val(),
            mail: $('#v-mail').val(),
            habilitado: $('#v-habilitado').is(':checked')
        };

        Swal.fire({ title: 'Guardando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

        try {
            const res = await fetch('api_vendedores.php?action=save', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, timer: 1500 });
                if (idEdit) loadModule('vendedores_admin.php');
                else limpiarForm();
            } else {
                Swal.fire('Atención', res.error, 'warning');
            }
        } catch (e) { Swal.fire('Error', 'Fallo crítico al guardar.', 'error'); }
    }

    init();
})();
</script>