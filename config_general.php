<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white">CONFIGURACIÓN GENERAL</h4>
            <small class="text-accent opacity-75">Parámetros globales del sistema</small>
        </div>
        <div>
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="guardarConfiguracion()">
                <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <!-- Formulario -->
    <form id="formConfig" enctype="multipart/form-data">
        <input type="hidden" name="id" id="configId">

        <!-- Tabs de Navegación -->
        <ul class="nav nav-tabs border-secondary mb-3" id="configTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active text-white" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    <i class="fas fa-cogs me-2 text-info"></i>General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-white" id="ventas-tab" data-bs-toggle="tab" data-bs-target="#ventas" type="button" role="tab">
                    <i class="fas fa-shopping-cart me-2 text-warning"></i>Ventas y Stock
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-white" id="fiscal-tab" data-bs-toggle="tab" data-bs-target="#fiscal" type="button" role="tab">
                    <i class="fas fa-file-invoice-dollar me-2 text-success"></i>Fiscal e Impuestos
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link text-white" id="visual-tab" data-bs-toggle="tab" data-bs-target="#visual" type="button" role="tab">
                    <i class="fas fa-print me-2 text-white-50"></i>Visual e Impresión
                </button>
            </li>
        </ul>

        <div class="tab-content" id="configTabsContent">

            <!-- TAB 1: GENERAL -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card card-filtros p-4 shadow-sm">
                    <div class="row g-4">
                        <!-- Logo de la Empresa -->
                        <div class="col-md-4 text-center border-end border-secondary border-opacity-25">
                            <label class="form-label-custom mb-3">LOGO DE COMPROBANTES</label>
                            <div class="mb-3 d-flex justify-content-center align-items-center bg-dark border border-secondary rounded" style="height: 150px; width: 100%; overflow: hidden;">
                                <img id="imgLogoPreview" src="" alt="Sin Logo" class="img-fluid" style="max-height: 100%; display: none;">
                                <i id="iconLogoPlaceholder" class="fas fa-image fa-3x text-secondary"></i>
                            </div>
                            <input type="file" class="form-control form-control-sm bg-dark text-white border-secondary" name="logo" id="fileLogo" accept="image/*">
                            <small class="text-muted mt-1 d-block">Se usa en facturas, notas y tickets. PNG/JPG/WebP, máximo 2 MB.</small>
                        </div>

                        <!-- Parámetros Básicos -->
                        <div class="col-md-8">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label-custom">FECHA CONTROL STOCK</label>
                                    <input type="date" class="form-control" name="fechastock" id="fechastock">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label-custom">TOPE CONSUMIDOR FINAL SIN IDENTIFICAR</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                        <input type="number" class="form-control" name="montominimo" id="montominimo" step="0.01">
                                    </div>
                                    <small class="text-muted d-block mt-1">Si un comprobante a Consumidor Final (sin DNI cargado) supera este monto, el sistema exige identificar al cliente antes de facturar. Dejar en 0 para no aplicar el control.</small>
                                </div>
                                <div class="col-md-6">
                                    <!-- FIX: valores en minúscula para coincidir con la DB y facturar.js (comparación toLower) -->
                                    <label class="form-label-custom">FOCO INICIAL EN VENTAS</label>
                                    <select class="form-select" name="texboxpredeterminado" id="texboxpredeterminado">
                                        <option value="CANTIDAD">Cantidad</option>
                                        <option value="CODIGO">Código de Producto</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: VENTAS Y STOCK -->
            <div class="tab-pane fade" id="ventas" role="tabpanel">
                <div class="card card-filtros p-4 shadow-sm">
                    <div class="row g-4">
                        <!-- Comportamiento de Venta -->
                        <div class="col-md-6">
                            <h6 class="text-accent border-bottom border-secondary pb-2 mb-3">COMPORTAMIENTO DE VENTA</h6>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="stocknegativo" id="stocknegativo" value="1">
                                <label class="form-check-label text-white" for="stocknegativo">Permitir Stock Negativo</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="modificarprecioenventas" id="modificarprecioenventas" value="1">
                                <label class="form-check-label text-white" for="modificarprecioenventas">Permitir modificar Precio en Venta</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="permitireditardescripcion" id="permitireditardescripcion" value="1">
                                <label class="form-check-label text-white" for="permitireditardescripcion">Permitir editar Descripción de Producto</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="modiflistas" id="modiflistas" value="1">
                                <label class="form-check-label text-white" for="modiflistas">Permitir cambiar Lista de Precios</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="agruparproductos" id="agruparproductos" value="1">
                                <label class="form-check-label text-white" for="agruparproductos">Agrupar productos iguales en ticket</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="imputarcobros" id="imputarcobros" value="1">
                                <label class="form-check-label text-white" for="imputarcobros">Imputar Cobros Automáticamente</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="admitendc" id="admitendc" value="1">
                                <label class="form-check-label text-white" for="admitendc">Admitir Notas de Crédito</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="admitenotasdebito" id="admitenotasdebito" value="1">
                                <label class="form-check-label text-white" for="admitenotasdebito">Admitir Notas de Débito</label>
                            </div>
                        </div>

                        <!-- Predeterminaciones -->
                        <div class="col-md-6">
                            <h6 class="text-accent border-bottom border-secondary pb-2 mb-3">PREDETERMINACIONES Y RUBROS</h6>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="habilitarubros" id="habilitarubros" value="1">
                                <label class="form-check-label text-white" for="habilitarubros">Habilitar Rubros</label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label-custom">TIPO DE VENTA PREDETERMINADO</label>
                                <select class="form-select" name="tipoventa" id="tipoventa">
                                    <option value="1">Contado</option>
                                    <option value="2">Cuenta Corriente</option>
                                    <option value="3">Cheque</option>
                                </select>
                            </div>

                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="predeterminarcliente" id="predeterminarcliente" value="1">
                                <label class="form-check-label text-white" for="predeterminarcliente">Cliente Predeterminado al abrir Venta</label>
                            </div>

                            <div class="mb-3 ps-4">
                                <select class="form-select select2-cliente" name="clientepredeterminado" id="clientepredeterminado">
                                    <!-- Se llena via JS -->
                                </select>
                            </div>

                            <!-- FIX: predeterminarcomprobante ahora es un SELECT con los tipos reales,
                                 no un checkbox. facturar.js usa el valor numérico para sel_tipo. -->
                            <div class="mb-3">
                                <label class="form-label-custom">COMPROBANTE PREDETERMINADO AL ABRIR VENTA</label>
                                <select class="form-select" name="predeterminarcomprobante" id="predeterminarcomprobante">
                                    <option value="0">— Ninguno —</option>
                                    <option value="1">Factura A</option>
                                    <option value="6">Factura B</option>
                                    <option value="11">Factura C</option>
                                    <option value="100">Ticket</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: FISCAL E IMPUESTOS -->
            <div class="tab-pane fade" id="fiscal" role="tabpanel">
                <div class="card card-filtros p-4 shadow-sm">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <h6 class="text-accent border-bottom border-secondary pb-2 mb-3">RETENCIONES Y TASAS</h6>

                            <div class="mb-3">
                                <label class="form-label-custom">ALÍCUOTA INGRESOS BRUTOS (%)</label>
                                <input type="number" class="form-control" name="poriibb" id="poriibb" step="0.01">
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="retiva" id="retiva" value="1">
                                <label class="form-check-label text-white" for="retiva">Agente de Retención IVA</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="retgan" id="retgan" value="1">
                                <label class="form-check-label text-white" for="retgan">Agente de Retención Ganancias</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="retiibb" id="retiibb" value="1">
                                <label class="form-check-label text-white" for="retiibb">Agente de Retención IIBB</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-accent border-bottom border-secondary pb-2 mb-3">DESCUENTOS Y RECARGOS</h6>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="descuentosglobales" id="descuentosglobales" value="1">
                                <label class="form-check-label text-white" for="descuentosglobales">Habilitar Descuentos Globales</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="recargosglobales" id="recargosglobales" value="1">
                                <label class="form-check-label text-white" for="recargosglobales">Habilitar Recargos Globales</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="descuentosporlinea" id="descuentosporlinea" value="1">
                                <label class="form-check-label text-white" for="descuentosporlinea">Habilitar Descuento por Línea</label>
                            </div>

                            <div class="mb-3 ps-4">
                                <label class="form-label-custom small">TIPO DESCUENTO PREDETERMINADO</label>
                                <select class="form-select form-select-sm" name="tipodescuentoindividual" id="tipodescuentoindividual">
                                    <option value="1">Porcentaje (%)</option>
                                    <option value="2">Monto Fijo ($)</option>
                                </select>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="ticketsiniva" id="ticketsiniva" value="1">
                                <label class="form-check-label text-white" for="ticketsiniva">Emitir Tickets sin discriminar IVA (Comandera)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: VISUAL E IMPRESIÓN -->
            <div class="tab-pane fade" id="visual" role="tabpanel">
                <div class="card card-filtros p-4 shadow-sm">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label-custom">FORMATO DE IMPRESIÓN PREDETERMINADO</label>
                                <select class="form-select" name="formatoimpresion" id="formatoimpresion">
                                    <option value="A4">Hoja A4</option>
                                    <option value="80">Ticket 80mm</option>
                                    <option value="58">Ticket 58mm</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label-custom">LEYENDA AL PIE (TICKETS)</label>
                                <input type="text" class="form-control" name="leyenda2" id="leyenda2" placeholder="Ej: Gracias por su compra">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="tablareducidaventas" id="tablareducidaventas" value="1">
                                <label class="form-check-label text-white" for="tablareducidaventas">Usar Tabla Reducida en Ventas (Compacto)</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="imprimeremitoenviopostfactura" id="imprimeremitoenviopostfactura" value="1">
                                <label class="form-check-label text-white" for="imprimeremitoenviopostfactura">Imprimir Remito automáticamente tras facturar</label>
                            </div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="mostrarvendedor" id="mostrarvendedor" value="1">
                                <label class="form-check-label text-white" for="mostrarvendedor">Mostrar vendedor en comprobantes impresos</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<script>
(function() {

    async function init() {
        // Inicializar Select2 Cliente
        $('.select2-cliente').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'Buscar Cliente...',
            allowClear: true,
            ajax: {
                url: 'api_configuracion.php?action=buscar_clientes',
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    return { results: data.data };
                }
            }
        });

        // Previsualización de Logo
        $('#fileLogo').on('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    $('#imgLogoPreview').attr('src', e.target.result).show();
                    $('#iconLogoPlaceholder').hide();
                };
                reader.readAsDataURL(file);
            }
        });

        cargarConfiguracion();
    }

    async function cargarConfiguracion() {
        try {
            const resp = await fetch('api_configuracion.php?action=get_config', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const res = await resp.json();

            if (res.success && res.data) {
                const d = res.data;

                // ID oculto
                $('#configId').val(d.id);

                // Inputs texto / número / fecha / select
                $('#fechastock').val(d.fechastock);
                $('#montominimo').val(d.montominimo);
                // FIX: guardar y cargar en MAYÚSCULAS para coincidir con facturar.js
                $('#texboxpredeterminado').val((d.texboxpredeterminado || 'CANTIDAD').toUpperCase());
                $('#poriibb').val(d.poriibb);
                $('#tipoventa').val(d.tipoventa);
                $('#tipodescuentoindividual').val(d.tipodescuentoindividual);
                $('#formatoimpresion').val(d.formatoimpresion);
                $('#leyenda2').val(d.leyenda2);
                // FIX: predeterminarcomprobante es ahora un SELECT numérico
                $('#predeterminarcomprobante').val(d.predeterminarcomprobante || 0);

                // Checkboxes (Switches)
                setCheck('stocknegativo',               d.stocknegativo);
                setCheck('modificarprecioenventas',      d.modificarprecioenventas);
                setCheck('permitireditardescripcion',    d.permitireditardescripcion);
                setCheck('modiflistas',                  d.modiflistas);
                setCheck('agruparproductos',             d.agruparproductos);
                setCheck('imputarcobros',                d.imputarcobros);
                setCheck('admitendc',                    d.admitendc);
                setCheck('admitenotasdebito',            d.admitenotasdebito);
                setCheck('habilitarubros',               d.habilitarubros);
                setCheck('predeterminarcliente',         d.predeterminarcliente);
                setCheck('retiva',                       d.retiva);
                setCheck('retgan',                       d.retgan);
                setCheck('retiibb',                      d.retiibb);
                setCheck('descuentosglobales',           d.descuentosglobales);
                setCheck('recargosglobales',             d.recargosglobales);
                setCheck('descuentosporlinea',           d.descuentosporlinea);
                setCheck('ticketsiniva',                 d.ticketsiniva);
                setCheck('tablareducidaventas',          d.tablareducidaventas);
                setCheck('imprimeremitoenviopostfactura', d.imprimeremitoenviopostfactura);
                setCheck('mostrarvendedor',               d.mostrarvendedor ?? 1);

                // Select2 Cliente Predeterminado
                if (d.clientepredeterminado > 0 && d.clientepredeterminado_nombre) {
                    const option = new Option(d.clientepredeterminado_nombre, d.clientepredeterminado, true, true);
                    $('#clientepredeterminado').append(option).trigger('change');
                }

                // Logo Preview
                if (d.logo_preview) {
                    $('#imgLogoPreview').attr('src', d.logo_preview).show();
                    $('#iconLogoPlaceholder').hide();
                }
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la configuración', 'error');
        }
    }

    function setCheck(id, val) {
        $('#' + id).prop('checked', parseInt(val) === 1);
    }

    window.guardarConfiguracion = async function() {
        const formData = new FormData(document.getElementById('formConfig'));

        // FIX: usar siempre formData.set() para garantizar un único valor por campo.
        // Los checkboxes marcados envían su value="1"; los desmarcados no se incluyen
        // en FormData, así que los agregamos explícitamente con "0".
        $('#formConfig input[type="checkbox"]').each(function() {
            formData.set($(this).attr('name'), $(this).is(':checked') ? '1' : '0');
        });

        try {
            const resp = await fetch('api_configuracion.php?action=save_config', {
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
                    cargarConfiguracion();
                });
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de conexión al guardar', 'error');
        }
    };

    init();
})();
</script>
