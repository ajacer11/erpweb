<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white">CONFIGURAR APIS</h4>
            <small class="text-accent opacity-75">Integraciones externas del sistema</small>
        </div>
        <div>
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="guardarConfigApiProductos()">
                <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-3" id="apisTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active text-white" id="productos-tab" data-bs-toggle="tab" data-bs-target="#productosApi" type="button" role="tab">
                <i class="fas fa-box me-2 text-info"></i>Productos
            </button>
        </li>
    </ul>

    <div class="tab-content" id="apisTabsContent">
        <div class="tab-pane fade show active" id="productosApi" role="tabpanel">
            <div class="card card-filtros p-4 shadow-sm">
                <p class="text-white-50 mb-4">
                    Expone un catálogo en JSON (precios y stock) para que tiendas externas lo consuman,
                    autenticando con un token propio. Consultá al proveedor de tu tienda cómo cargarlo.
                </p>

                <div class="alert alert-warning py-2 px-3 mb-4" id="apiProdNoContratado" style="display:none;">
                    <i class="fas fa-triangle-exclamation me-2"></i>
                    Este servicio todavía no está habilitado para tu cuenta. Podés dejar la configuración lista,
                    pero la API va a rechazar las llamadas hasta que WEST IT lo habilite. Contactanos para contratarlo.
                </div>

                <form id="formApiProductos">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="apiProdHabilitado" name="habilitado" value="1">
                                <label class="form-check-label text-white fw-bold" for="apiProdHabilitado">API habilitada</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">LISTAS DE PRECIOS A COMPARTIR</label>
                            <div id="listasCheckboxes" class="border border-secondary rounded p-3" style="max-height: 220px; overflow-y: auto;">
                                <span class="text-muted">Cargando...</span>
                            </div>
                            <small class="text-muted d-block mt-1">Podés compartir más de una lista; la tienda recibe el precio neto y final de cada una.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label-custom">STOCK A COMPARTIR</label>
                            <select class="form-select" id="apiProdDeposito" name="id_deposito">
                                <option value="">Consolidado (todos los depósitos)</option>
                            </select>
                            <small class="text-muted d-block mt-1" id="apiProdDepositoAyuda">Multidepósito no está habilitado: se comparte siempre el stock consolidado.</small>
                        </div>

                        <div class="col-12">
                            <hr class="border-secondary opacity-25">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label-custom">TOKEN DE ACCESO</label>
                            <div class="input-group">
                                <input type="text" class="form-control font-monospace" id="apiProdToken" readonly placeholder="Se genera al guardar por primera vez">
                                <button class="btn btn-outline-info" type="button" onclick="copiarValor('apiProdToken')" title="Copiar">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="btn btn-outline-danger" type="button" onclick="regenerarTokenApiProductos()" title="Generar un token nuevo">
                                    <i class="fas fa-rotate"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">Enviar como header <code>Authorization: Bearer &lt;token&gt;</code> o parámetro <code>token</code>. Acepta GET y POST. Generar uno nuevo invalida el anterior de inmediato.</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-custom">URL DE LA API</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="apiProdUrl" readonly>
                                <button class="btn btn-outline-info" type="button" onclick="copiarValor('apiProdUrl')" title="Copiar">
                                    <i class="fas fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-12">
                            <a href="documentacion_api_productos.php" target="_blank" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-file-arrow-down me-2"></i>Descargar documentación técnica para el desarrollador de la tienda
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    async function init() {
        await cargarConfigApiProductos();
    }

    async function cargarConfigApiProductos() {
        try {
            const resp = await fetch('api_config_apis_externas.php?action=get_config', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const res = await resp.json();
            if (!res.success) throw new Error(res.error || 'Error desconocido');

            const d = res.data;

            $('#apiProdHabilitado').prop('checked', !!d.habilitado);
            $('#apiProdToken').val(d.token || '');
            $('#apiProdUrl').val(res.url_api || '');
            $('#apiProdNoContratado').toggle(!res.contratado);

            // Checkboxes de listas
            const $cont = $('#listasCheckboxes').empty();
            if (res.listas && res.listas.length) {
                res.listas.forEach(function(l) {
                    const checked = d.listas_compartidas.includes(parseInt(l.id)) ? 'checked' : '';
                    $cont.append(`
                        <div class="form-check">
                            <input class="form-check-input listaCheck" type="checkbox" value="${l.id}" id="lista_${l.id}" ${checked}>
                            <label class="form-check-label text-white" for="lista_${l.id}">${l.descripcion || ('Lista ' + l.id)}</label>
                        </div>
                    `);
                });
            } else {
                $cont.html('<span class="text-muted">No hay listas de precios configuradas.</span>');
            }

            // Depósitos (solo si multidepósito está habilitado)
            const $sel = $('#apiProdDeposito');
            $sel.find('option:not(:first)').remove();
            if (res.multidepositohabilitado && res.depositos && res.depositos.length) {
                res.depositos.forEach(function(dep) {
                    $sel.append(new Option(dep.nombre, dep.id));
                });
                $sel.prop('disabled', false);
                $('#apiProdDepositoAyuda').text('Elegí un depósito puntual o dejá "Consolidado" para compartir la suma de todos.');
                if (d.id_deposito) $sel.val(d.id_deposito);
            } else {
                $sel.prop('disabled', true);
                $('#apiProdDepositoAyuda').text('Multidepósito no está habilitado: se comparte siempre el stock consolidado.');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la configuración: ' + e.message, 'error');
        }
    }

    window.guardarConfigApiProductos = async function() {
        const listasIds = $('.listaCheck:checked').map(function() { return this.value; }).get();
        if (listasIds.length === 0) {
            Swal.fire('Atención', 'Seleccioná al menos una lista de precios para compartir.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.set('habilitado', $('#apiProdHabilitado').is(':checked') ? '1' : '0');
        formData.set('listas_compartidas', listasIds.join(','));
        formData.set('id_deposito', $('#apiProdDeposito').val() || '');

        try {
            const resp = await fetch('api_config_apis_externas.php?action=save_config', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                body: formData
            });
            const res = await resp.json();
            if (res.success) {
                Swal.fire({icon: 'success', title: 'Guardado', text: res.message, timer: 1500, showConfirmButton: false});
                cargarConfigApiProductos();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de conexión al guardar', 'error');
        }
    };

    window.regenerarTokenApiProductos = async function() {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: '¿Generar un token nuevo?',
            text: 'El token actual dejará de funcionar de inmediato. Vas a tener que actualizarlo en la tienda externa.',
            showCancelButton: true,
            confirmButtonText: 'Sí, generar nuevo',
            cancelButtonText: 'Cancelar'
        });
        if (!confirm.isConfirmed) return;

        try {
            const resp = await fetch('api_config_apis_externas.php?action=regenerar_token', {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const res = await resp.json();
            if (res.success) {
                $('#apiProdToken').val(res.token);
                Swal.fire('Listo', res.message, 'success');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Error de conexión', 'error');
        }
    };

    window.copiarValor = function(id) {
        const el = document.getElementById(id);
        if (!el.value) return;
        navigator.clipboard.writeText(el.value).then(function() {
            Swal.fire({icon: 'success', title: 'Copiado', timer: 800, showConfirmButton: false});
        });
    };

    init();
})();
</script>
