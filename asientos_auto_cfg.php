<?php
/**
 * asientos_auto_cfg.php - CONFIGURACIÓN DE ASIENTOS AUTOMÁTICOS
 * Rediseño responsivo para evitar desbordamientos y optimizar usabilidad en móviles.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloAsientosCfg">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-gears text-accent me-2"></i>ASIENTOS AUTOMÁTICOS</h4>
            <small class="text-accent opacity-75">CONFIGURACIÓN DE CUENTAS PREDETERMINADAS</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary fw-bold rounded-pill px-3 btn-sm" id="btn-cancelar-asientos">
                <i class="fa-solid fa-arrow-left me-1"></i> VOLVER
            </button>
            <button type="submit" form="form-asientos-auto" class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm">
                <i class="fa-solid fa-save me-1"></i> GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <form id="form-asientos-auto">
        <ul class="nav nav-pills erp-tabs mb-3 gap-1" id="tabAsientos" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active btn-sm text-uppercase" data-bs-toggle="tab" data-bs-target="#ventas" type="button">Ventas / CMV</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm text-uppercase" data-bs-toggle="tab" data-bs-target="#compras" type="button">Compras / Prov.</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm text-uppercase" data-bs-toggle="tab" data-bs-target="#cobros" type="button">Cobros / Ret.</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link btn-sm text-uppercase" data-bs-toggle="tab" data-bs-target="#ajustes" type="button">Ajustes Stock</button>
            </li>
        </ul>

        <div class="tab-content" id="tabAsientosContent">
            
            <div class="tab-pane fade show active" id="ventas">
                <div class="row g-3">
                    <div class="col-12 col-xl-6">
                        <div class="card card-filtros h-100 shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft">
                                <span class="text-accent fw-bold small"><i class="fa-solid fa-cart-shopping me-2"></i>ASIENTO DE VENTAS</span>
                            </div>
                            <div class="card-body p-3" id="container-ventas-fields">
                                <div class="text-center py-4"><div class="spinner-border text-info spinner-border-sm"></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-6">
                        <div class="card card-filtros h-100 shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft">
                                <span class="text-accent fw-bold small"><i class="fa-solid fa-tags me-2"></i>COSTO MERCADERÍA VENDIDA (CMV)</span>
                            </div>
                            <div class="card-body p-3" id="container-cmv-fields">
                                <div class="text-center py-4"><div class="spinner-border text-info spinner-border-sm"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="compras">
                <div class="row justify-content-center">
                    <div class="col-12 col-xl-8">
                        <div class="card card-filtros shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft">
                                <span class="text-accent fw-bold small"><i class="fa-solid fa-truck-ramp-box me-2"></i>CUENTAS DE COMPRAS Y PROVEEDORES</span>
                            </div>
                            <div class="card-body p-3" id="container-compras-fields">
                                </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="cobros">
                <div class="row justify-content-center">
                    <div class="col-12 col-xl-8">
                        <div class="card card-filtros shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft">
                                <span class="text-accent fw-bold small"><i class="fa-solid fa-hand-holding-dollar me-2"></i>COBROS Y RETENCIONES</span>
                            </div>
                            <div class="card-body p-3" id="container-cobros-fields">
                                </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="ajustes">
                <div class="row g-3">
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft small text-accent fw-bold">DIF. IMPUESTOS INTERNOS</div>
                            <div class="card-body p-3" id="container-ajuste-imp-fields"></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft small text-accent fw-bold">AJUSTE DE STOCK</div>
                            <div class="card-body p-3" id="container-ajuste-stock-fields"></div>
                        </div>
                    </div>
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow-sm">
                            <div class="card-header border-secondary border-opacity-25 bg-dark-soft small text-accent fw-bold">AJUSTE DE COSTO</div>
                            <div class="card-body p-3" id="container-ajuste-costo-fields"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
    (function() {
        /**
         * Renderiza un campo de selección con soporte responsivo y Select2
         */
        function renderField(label, name, selectedValue, accounts, helpText = '') {
            let options = '<option value="">-- SELECCIONAR CUENTA --</option>';
            accounts.forEach(acc => {
                const isSelected = acc.numcuenta == selectedValue ? 'selected' : '';
                options += `<option value="${acc.numcuenta}" ${isSelected}>${acc.numcuenta} - ${acc.descripcion}</option>`;
            });

            const helpHtml = helpText ? `<div class="form-text text-info x-small mt-1 fst-italic"><i class="fa-solid fa-circle-info me-1"></i>${helpText}</div>` : '';

            return `
                <div class="mb-3">
                    <label class="form-label-custom mb-1 d-block">${label.toUpperCase()}</label>
                    <div class="input-group input-group-sm">
                        <select class="form-select select2-account" name="${name}" required>
                            ${options}
                        </select>
                        <button type="button" class="btn btn-outline-info" onclick="window.abrirPlanCuentas('${name}')"><i class="fa-solid fa-magnifying-glass"></i></button>
                    </div>
                    ${helpHtml}
                </div>
            `;
        }

        async function loadConfig() {
            try {
                const resp = await fetch('api_asientos_auto_cfg.php?action=get_data', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                if (!res.success) throw new Error(res.error);

                const { data, accounts } = res;

                // Sección Ventas
                $('#container-ventas-fields').html(
                    renderField('Cuenta Ventas (Haber)', 'ventas', data.ventas, accounts, 'Cuenta por defecto cuando el producto no tiene asociación.') +
                    renderField('Cuenta I.V.A. Ventas', 'ivaventas', data.ivaventas, accounts) +
                    renderField('Cuenta Imp. Internos', 'impintventas', data.impintventas, accounts) +
                    renderField('Cuenta Descuentos', 'descuentos', data.descuentos, accounts) +
                    renderField('Cuenta Recargos', 'recargos', data.recargos, accounts)
                );

                // Sección CMV
                $('#container-cmv-fields').html(
                    renderField('Cuenta Debe (CMV)', 'costomercaderia', data.costomercaderia, accounts, 'Cuenta para el registro del Costo de Mercadería Vendida.')
                );

                // Sección Compras
                $('#container-compras-fields').html(
                    '<div class="row"><div class="col-md-6">' +
                    renderField('Cta. Mercadería (Debe)', 'mercaderia', data.mercaderia, accounts, 'Predeterminada para compras de stock.') +
                    renderField('Cta. Proveedores (Haber)', 'proveedores', data.proveedores, accounts) +
                    renderField('Cta. I.V.A. Compras', 'ivacompras', data.ivacompras, accounts) +
                    '</div><div class="col-md-6">' +
                    renderField('Cta. Imp. Int. Compras', 'impintcompras', data.impintcompras, accounts) +
                    renderField('Cta. Percepción IIBB', 'perciibb', data.perciibb, accounts) +
                    renderField('Cta. Percepción IVA', 'perciva', data.perciva, accounts) +
                    '</div></div>'
                );

                // Sección Cobros
                $('#container-cobros-fields').html(
                    renderField('Deudores por Ventas', 'deudores', data.deudores, accounts) +
                    '<hr class="border-secondary border-opacity-25 my-3">' +
                    '<div class="row"><div class="col-md-4">' +
                    renderField('Retención I.V.A.', 'retiva', data.retiva, accounts) +
                    '</div><div class="col-md-4">' +
                    renderField('Retención IIBB', 'retiibb', data.retiibb, accounts) +
                    '</div><div class="col-md-4">' +
                    renderField('Retención Ganancias', 'retgan', data.retgan, accounts) +
                    '</div></div>' +
                    '<hr class="border-secondary border-opacity-25 my-3">' +
                    renderField('Comisiones a Vendedores', 'comisionesvend', data.comisionesvend, accounts, 'Cuenta para el pasivo por comisiones a vendedores (antes se usaba la misma cuenta que Proveedores).') +
                    renderField('Cheques Rechazados a Cobrar', 'chequesrechazados', data.chequesrechazados, accounts, 'Cuenta puente para cheques de clientes rechazados (rebotados), separada de Deudores por Ventas.')
                );

                // Sección Ajustes
                $('#container-ajuste-imp-fields').html(renderField('Diferencia Impuestos', 'ajusteimp', data.ajusteimp, accounts));
                $('#container-ajuste-stock-fields').html(renderField('Ajuste de Stock', 'ajuste', data.ajuste, accounts));
                $('#container-ajuste-costo-fields').html(renderField('Ajuste de Costo', 'ajustemercaderia', data.ajustemercaderia, accounts));

                // Init Select2 con scroll al main-content
                $('.select2-account').select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $('#main-content'),
                    width: '100%'
                });

            } catch (err) {
                Swal.fire('Error', 'No se pudo cargar la configuración: ' + err.message, 'error');
            }
        }

        // Evento Guardar
        $('#form-asientos-auto').on('submit', function(e) {
            e.preventDefault();
            Swal.fire({
                title: '¿Guardar Configuración?',
                text: "Se actualizarán las cuentas contables de los procesos automáticos.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--accent)',
                confirmButtonText: 'SÍ, GUARDAR',
                cancelButtonText: 'CANCELAR',
                background: '#001020',
                color: '#fff'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = $(this).serialize();
                    $.ajax({
                        url: 'api_asientos_auto_cfg.php?action=save',
                        method: 'POST',
                        data: formData,
                        dataType: 'json',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        success: function(res) {
                            if (res.success) {
                                Swal.fire({ icon: 'success', title: 'Actualizado', text: res.message, timer: 1500, showConfirmButton: false });
                            } else {
                                Swal.fire('Error', res.error || 'No se pudo guardar.', 'error');
                            }
                        }
                    });
                }
            });
        });

        $('#btn-cancelar-asientos').on('click', () => {
            loadModule('dashboard.php');
        });

        loadConfig();
    })();
</script>

<style>
    /* Tabs personalizadas estilo ERP */
    .erp-tabs .nav-link {
        background: #1a1a1a;
        color: #888;
        border: 1px solid #333;
        font-size: 11px;
        letter-spacing: 0.5px;
        padding: 8px 15px;
        border-radius: 5px;
        transition: all 0.3s;
    }
    .erp-tabs .nav-link.active {
        background: var(--accent) !important;
        color: #000 !important;
        font-weight: bold;
        border-color: var(--accent);
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.2);
    }
    .erp-tabs .nav-link:hover:not(.active) {
        background: #252525;
        color: #fff;
        border-color: #555;
    }

    .x-small { font-size: 10px; }
    
    /* Adaptación Select2 para el formulario denso */
    .select2-container--bootstrap-5 .select2-selection--single {
        height: 31px !important;
        font-size: 12px !important;
        background-color: #0a0a0a !important;
        border-color: #444 !important;
        color: #fff !important;
    }
    .select2-container--bootstrap-5 .select2-selection__rendered {
        color: var(--accent) !important;
        line-height: 30px !important;
    }

    @media (max-width: 768px) {
        .erp-tabs {
            flex-direction: column;
            width: 100%;
        }
        .erp-tabs .nav-item {
            width: 100%;
        }
        .erp-tabs .nav-link {
            text-align: center;
            width: 100%;
            margin-bottom: 2px;
        }
    }
</style>