<?php
require_once 'check_access.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = ($id > 0) ? "EDITAR TRANSPORTE" : "AGREGAR TRANSPORTE";
?>

<div class="container-fluid px-4 py-3 fade-in" id="containerAddTransp">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0"><?php echo $titulo; ?></h4>
            <small class="text-accent opacity-75">Complete los datos de la empresa de logística</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill" onclick="loadModule('transportes_admin.php')">
            <i class="fas fa-arrow-left me-2"></i>VOLVER
        </button>
    </div>

    <form id="formTransporte" class="card card-filtros p-4">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label-custom">RAZÓN SOCIAL</label>
                <input type="text" name="razon" id="txtRazon" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">CONTACTO</label>
                <input type="text" name="contacto" id="txtContacto" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label-custom">DOMICILIO</label>
                <input type="text" name="domicilio" id="txtDomicilio" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">LOCALIDAD</label>
                <input type="text" name="localidad" id="txtLocalidad" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label-custom">C.P.</label>
                <input type="text" name="cp" id="txtCP" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">PROVINCIA</label>
                <input type="text" name="provincia" id="txtProvincia" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label-custom">TIPO DOC</label>
                <select name="tipodoc" id="cmbTipoDoc" class="form-select">
                    <option value="DNI">DNI</option>
                    <option value="CUIT">CUIT</option>
                    <option value="PASAPORTE">PASAPORTE</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">NÚMERO DOC</label>
                <input type="text" name="numdoc" id="txtNumDoc" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">CONDICIÓN FISCAL</label>
                <select name="condicionfiscal" id="cmbCondFiscal" class="form-select">
                    <option value="Responsable Inscripto">Responsable Inscripto</option>
                    <option value="Monotributo">Monotributo</option>
                    <option value="Exento">Exento</option>
                    <option value="Consumidor Final">Consumidor Final</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label-custom">TELÉFONO</label>
                <input type="text" name="telefono" id="txtTel" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">CELULAR</label>
                <input type="text" name="celular" id="txtCel" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">EMAIL</label>
                <input type="email" name="mail" id="txtMail" class="form-control">
            </div>

            <div class="col-md-8">
                <label class="form-label-custom">HORARIO DE ATENCIÓN</label>
                <input type="text" name="horario" id="txtHorario" class="form-control" placeholder="Ej: Lun a Vie 08:00 a 17:00">
            </div>
            <div class="col-md-4 d-flex align-items-center">
                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="habilitado" id="chkhab" checked>
                    <label class="form-check-label text-white ms-2" for="chkhab">HABILITADO</label>
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex gap-2">
            <button type="button" class="btn btn-info fw-bold px-5 rounded-pill" onclick="guardarTransporte()">
                <i class="fas fa-save me-2"></i><?php echo ($id > 0) ? "MODIFICAR" : "GUARDAR"; ?>
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="limpiarForm()">
                LIMPIAR
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const transportId = <?php echo $id; ?>;

    async function init() {
        if (transportId > 0) {
            const resp = await fetch(`api_transportes.php?action=get&id=${transportId}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            if(res.success) {
                const d = res.data;
                document.getElementById('txtRazon').value = d.razon;
                document.getElementById('txtContacto').value = d.contacto;
                document.getElementById('txtDomicilio').value = d.domicilio;
                document.getElementById('txtLocalidad').value = d.localidad;
                document.getElementById('txtCP').value = d.cp;
                document.getElementById('txtProvincia').value = d.provincia;
                document.getElementById('cmbTipoDoc').value = d.tipodoc;
                document.getElementById('txtNumDoc').value = d.numdoc;
                document.getElementById('cmbCondFiscal').value = d.condicionfiscal;
                document.getElementById('txtTel').value = d.telefono;
                document.getElementById('txtCel').value = d.celular;
                document.getElementById('txtMail').value = d.mail;
                document.getElementById('txtHorario').value = d.horario;
                document.getElementById('chkhab').checked = (d.habilitado == 1);
            }
        }
    }
    init();
})();

async function guardarTransporte() {
    const formData = new FormData(document.getElementById('formTransporte'));
    const data = Object.fromEntries(formData.entries());
    data.habilitado = document.getElementById('chkhab').checked ? 1 : 0;

    const resp = await fetch('api_transportes.php?action=save', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    });

    const result = await resp.json();
    if(result.success) {
        Swal.fire('Éxito', 'Transporte guardado correctamente', 'success');
        loadModule('transportes_admin.php');
    } else {
        Swal.fire('Error', result.error || 'No se pudo guardar', 'error');
    }
}

function limpiarForm() {
    document.getElementById('formTransporte').reset();
}
</script>