<?php
/**
 * bancos_cuenta_add.php - FORMULARIO DE ALTA Y EDICIÓN DE CUENTA BANCARIA
 */
require_once 'check_access.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$titulo = ($id > 0) ? "MODIFICAR CUENTA BANCARIA" : "NUEVA CUENTA BANCARIA";
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-2">
        <div>
            <h4 class="text-white mb-0"><?php echo $titulo; ?></h4>
            <small class="text-accent opacity-75">Identificación de cuenta para tesorería y conciliación</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill btn-sm" onclick="loadModule('bancos_admin.php')">
            <i class="fas fa-arrow-left me-2"></i>VOLVER AL LISTADO
        </button>
    </div>

    <form id="formCuentaBancaria" class="card card-filtros p-4">
        <input type="hidden" name="id" value="<?php echo $id; ?>">
        
        <div class="row g-4">
            <!-- Columna Izquierda: Identificación -->
            <div class="col-md-6 border-end border-secondary">
                <h6 class="text-accent mb-3 fw-bold"><i class="fas fa-university me-2"></i>DATOS DE LA ENTIDAD</h6>
                
                <div class="mb-3">
                    <label class="form-label-custom">BANCO / ENTIDAD</label>
                    <select name="banco" id="cmbBanco" class="form-select select2-simple" required></select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-8">
                        <label class="form-label-custom">NOMBRE DE SUCURSAL</label>
                        <input type="text" name="sucursal" id="txtSucursal" class="form-control" placeholder="Ej: Centro">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">№ SUCURSAL</label>
                        <input type="text" name="numsucursal" id="txtNumSucursal" class="form-control" placeholder="000">
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom">TIPO DE CUENTA</label>
                        <select name="tipo" id="cmbTipo" class="form-select" required>
                            <option value="Cuenta Corriente">CUENTA CORRIENTE</option>
                            <option value="Caja de Ahorro">CAJA DE AHORRO</option>
                            <option value="Cuenta Especial">CUENTA ESPECIAL</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">MONEDA</label>
                        <select name="moneda" id="cmbMoneda" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="PESOS">PESOS ($)</option>
                            <option value="DOLARES">DÓLARES (U$D)</option>
                            <option value="EUROS">EUROS (€)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Columna Derecha: Detalles Técnicos -->
            <div class="col-md-6">
                <h6 class="text-accent mb-3 fw-bold"><i class="fas fa-id-card me-2"></i>DATOS DE CUENTA</h6>

                <div class="mb-3">
                    <label class="form-label-custom text-info">REFERENCIA INTERNA (ALIAS SISTEMA)</label>
                    <input type="text" name="referencia" id="txtReferencia" class="form-control fw-bold border-info" required placeholder="Ej: BANCO NACION 001">
                    <small class="text-muted mt-1 d-block">Este nombre se usará para identificar la cuenta en todo el sistema.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom">NÚMERO DE CUENTA</label>
                    <input type="text" name="numcuenta" id="txtNumCuenta" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom">C.B.U. / C.V.U.</label>
                    <input type="text" name="cbu" id="txtCBU" class="form-control" maxlength="22">
                </div>

                <div class="mb-3">
                    <label class="form-label-custom">ALIAS BANCARIO</label>
                    <input type="text" name="aliasbanco" id="txtAliasBanco" class="form-control">
                </div>
            </div>
        </div>

        <div class="mt-4 pt-3 border-top d-flex gap-2">
            <button type="submit" class="btn btn-info fw-bold px-5 rounded-pill shadow">
                <i class="fas fa-save me-2"></i><?php echo ($id > 0) ? "MODIFICAR DATOS" : "REGISTRAR CUENTA"; ?>
            </button>
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="loadModule('bancos_admin.php')">
                CANCELAR
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const cuentaId = <?php echo $id; ?>;

    /**
     * Inicialización del formulario
     */
    async function init() {
        // Cargar Bancos
        const resBancos = await fetch('api_bancos_cuentas.php?action=get_bancos', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        const bancos = await resBancos.json();
        
        const cmb = $('#cmbBanco');
        cmb.empty().append(new Option('SELECCIONE BANCO...', '', true, true));
        bancos.forEach(b => {
            cmb.append(new Option(b.denominacion, b.id, false, false));
        });

        // Inicializar Select2
        $('.select2-simple').select2({
            dropdownParent: $('#main-content'),
            width: '100%'
        });

        // Si es edición, cargar datos
        if (cuentaId > 0) {
            const res = await fetch(`api_bancos_cuentas.php?action=get&id=${cuentaId}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await res.json();
            
            if (result.success) {
                const d = result.data;
                $('#cmbBanco').val(d.banco).trigger('change');
                $('#txtSucursal').val(d.sucursal);
                $('#txtNumSucursal').val(d.numsucursal);
                $('#cmbTipo').val(d.tipocuenta);
                $('#cmbMoneda').val(d.moneda);
                $('#txtNumCuenta').val(d.numcuenta);
                $('#txtCBU').val(d.cbu);
                $('#txtAliasBanco').val(d.alias);
                $('#txtReferencia').val(d.referencia);
            }
        }
    }

    init();

    /**
     * Envío del formulario
     */
    document.getElementById('formCuentaBancaria').onsubmit = async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());

        const resp = await fetch('api_bancos_cuentas.php?action=save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data)
        });

        const result = await resp.json();
        if (result.success) {
            Swal.fire({
                title: 'Operación Exitosa',
                text: 'La cuenta bancaria ha sido procesada correctamente.',
                icon: 'success',
                timer: 2000,
                showConfirmButton: false,
                background: '#001020', color: '#fff'
            });
            setTimeout(() => loadModule('bancos_admin.php'), 500);
        } else {
            Swal.fire('Error', result.error || 'No se pudo completar la operación.', 'error');
        }
    };
})();
</script>