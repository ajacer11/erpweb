<?php
/**
 * monedas_cotizacion.php - ACTUALIZACIÓN DE TIPO DE CAMBIO E IMPACTO EN PRECIOS
 * Replica la lógica de administrarmonedas.vb (cálculo de coeficiente y update masivo)
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) exit("ID Inválido");
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">ACTUALIZAR COTIZACIÓN</h4>
            <small class="text-accent opacity-75">Impactará en los precios de todos los productos asociados</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill btn-sm" onclick="loadModule('monedas_admin.php')">
            <i class="fas fa-arrow-left me-2"></i>VOLVER
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card card-filtros p-4 shadow-lg border-warning border-opacity-50">
                <input type="hidden" id="hdnIdMoneda" value="<?php echo $id; ?>">
                
                <div class="text-center mb-4">
                    <h2 class="text-white fw-bold mb-0" id="lblNombreMoneda">...</h2>
                    <span class="badge bg-warning text-dark mt-2">ACTUALIZACIÓN MASIVA DE PRECIOS</span>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-6">
                        <label class="form-label-custom text-muted">COTIZACIÓN ACTUAL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark border-secondary text-white-50">$</span>
                            <input type="text" id="txtCotizacionActual" class="form-control bg-dark text-white fw-bold fs-5 text-end" readonly>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label-custom text-info">NUEVA COTIZACIÓN</label>
                        <div class="input-group">
                            <span class="input-group-text bg-info border-info text-dark fw-bold">$</span>
                            <input type="number" id="txtNuevaCotizacion" class="form-control fw-bold fs-5 text-end border-info text-accent" step="0.01">
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning d-flex align-items-center bg-warning bg-opacity-10 border-warning border-opacity-25" role="alert">
                    <i class="fas fa-exclamation-triangle fa-2x me-3 text-warning"></i>
                    <div class="small text-white-50">
                        Al confirmar, el sistema calculará el coeficiente entre la cotización nueva y la anterior, y 
                        <strong>multiplicará el costo y todos los precios de venta</strong> de los productos vinculados a esta moneda.
                    </div>
                </div>

                <div class="d-grid gap-2 mt-2">
                    <button class="btn btn-warning fw-bold py-2 shadow" onclick="actualizarCotizacion()">
                        <i class="fas fa-sync-alt me-2"></i>CONFIRMAR Y ACTUALIZAR PRECIOS
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const id = <?php echo $id; ?>;

    async function init() {
        const resp = await fetch(`api_monedas.php?action=get_cotizacion&id=${id}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
        const res = await resp.json();
        
        if(res.success) {
            document.getElementById('lblNombreMoneda').innerText = res.data.nombre;
            document.getElementById('txtCotizacionActual').value = parseFloat(res.data.cotizacion).toLocaleString('es-AR', {minimumFractionDigits: 2});
            document.getElementById('txtNuevaCotizacion').focus();
        } else {
            Swal.fire('Error', 'No se pudo cargar la información de la moneda.', 'error');
            loadModule('monedas_admin.php');
        }
    }

    window.actualizarCotizacion = async function() {
        const nueva = parseFloat(document.getElementById('txtNuevaCotizacion').value);
        if(!nueva || nueva <= 0) {
            Swal.fire('Atención', 'Ingrese un valor de cotización válido mayor a 0.', 'warning');
            return;
        }

        const confirm = await Swal.fire({
            title: '¿Confirmar actualización?',
            text: `Se actualizarán masivamente los precios según la nueva cotización: $ ${nueva}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, Actualizar',
            background: '#001020', color: '#fff'
        });

        if (confirm.isConfirmed) {
            Swal.fire({
                title: 'Procesando...',
                html: 'Actualizando costos y precios...',
                didOpen: () => Swal.showLoading(),
                background: '#001020', color: '#fff',
                allowOutsideClick: false
            });

            try {
                const resp = await fetch('api_monedas.php?action=update_rate', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ id: id, cotizacion: nueva })
                });
                
                const res = await resp.json();

                if(res.success) {
                    await Swal.fire({
                        title: '¡Actualizado!',
                        text: res.message,
                        icon: 'success',
                        background: '#001020', color: '#fff'
                    });
                    loadModule('monedas_admin.php');
                } else {
                    Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
                }
            } catch(e) {
                Swal.fire('Error', 'Error de conexión', 'error');
            }
        }
    }

    init();
})();
</script>