<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$clientePreseleccionado = isset($_GET['cliente_id']) ? intval($_GET['cliente_id']) : 0;
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white"><i class="fa-solid fa-print text-accent me-2"></i>IMPRIMIR ENVÍOS</h4>
            <small class="text-accent opacity-75">GENERACIÓN DE ETIQUETAS Y REMITOS DE TRANSPORTE</small>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card card-filtros shadow-lg border-0">
                <div class="card-header bg-transparent border-bottom border-secondary py-3">
                    <h5 class="text-white mb-0 text-center">DATOS DEL ENVÍO</h5>
                </div>
                <div class="card-body p-4">
                    <form id="frmImprimirEnvio" onsubmit="return false;">
                        
                        <!-- Cliente -->
                        <div class="mb-3">
                            <label class="form-label-custom">CLIENTE (DESTINATARIO)</label>
                            <select id="cmbCliente" class="form-select select2-simple" required>
                                <option value="">CARGANDO...</option>
                            </select>
                        </div>

                        <!-- Transporte -->
                        <div class="mb-3">
                            <label class="form-label-custom">TRANSPORTE</label>
                            <select id="cmbTransporte" class="form-select select2-simple" required>
                                <option value="">CARGANDO...</option>
                            </select>
                        </div>

                        <div class="row g-3 mb-3">
                            <!-- Cantidad de Bultos -->
                            <div class="col-md-6">
                                <label class="form-label-custom">CANTIDAD DE BULTOS</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white"><i class="fa-solid fa-box"></i></span>
                                    <input type="number" id="txtCantBultos" class="form-control fw-bold text-center" value="1" min="1" required>
                                </div>
                            </div>

                            <!-- Valor Declarado / Unitario -->
                            <div class="col-md-6">
                                <label class="form-label-custom">VALOR DECLARADO (UNITARIO)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-dark border-secondary text-white">$</span>
                                    <input type="number" id="txtUnitario" class="form-control fw-bold text-end" value="0.00" step="0.01" min="0">
                                </div>
                            </div>
                        </div>

                        <!-- Copias -->
                        <div class="mb-4">
                            <label class="form-label-custom">COPIAS A IMPRIMIR</label>
                            <input type="number" id="txtCopias" class="form-control" value="1" min="1" required>
                            <small class="text-muted">Se generará una etiqueta por cada bulto multiplicado por las copias.</small>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="d-grid gap-2">
                            <button type="button" id="btnImprimir" class="btn btn-info fw-bold rounded-pill py-2 shadow-sm">
                                <i class="fa-solid fa-print me-2"></i> GUARDAR E IMPRIMIR
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-pill" onclick="loadModule('dashboard.php')">
                                CANCELAR / VOLVER
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
    const CLIENTE_PRESELECCIONADO = <?php echo (int)$clientePreseleccionado; ?>;
    let clientesCache = [];

    async function init() {
        // Inicializar Select2
        $('#cmbCliente').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'SELECCIONE CLIENTE...'
        });

        $('#cmbTransporte').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'SELECCIONE TRANSPORTE...'
        });

        // Cargar Combos
        try {
            const resp = await fetch('api_imprimir_envios.php?action=get_form_data', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();

            if (data.status === 'error') throw new Error(data.error);

            // Llenar Clientes
            const selCli = $('#cmbCliente');
            selCli.empty().append(new Option('SELECCIONE CLIENTE...', '', true, true));
            
            clientesCache = data.clientes;
            data.clientes.forEach(c => {
                const option = new Option(c.razon, c.id, false, false);
                $(option).attr('data-transporte-id', c.transporte || '');
                selCli.append(option);
            });

            // Llenar Transportes
            const selTra = $('#cmbTransporte');
            selTra.empty().append(new Option('SELECCIONE TRANSPORTE...', '', true, true));
            data.transportes.forEach(t => {
                selTra.append(new Option(t.razon, t.id, false, false));
            });

            // Si se llegó acá desde Facturación con "Imprimir Remito automático" activado,
            // el cliente ya viene elegido: lo preseleccionamos junto con su transporte
            // habitual (misma lógica que el autoseleccionado al elegir cliente a mano).
            if (CLIENTE_PRESELECCIONADO) {
                const clientePre = clientesCache.find(c => parseInt(c.id) === CLIENTE_PRESELECCIONADO);
                if (clientePre) {
                    selCli.val(String(CLIENTE_PRESELECCIONADO)).trigger('change');
                    if (clientePre.transporte) {
                        selTra.val(String(clientePre.transporte)).trigger('change');
                    }
                }
            }

            selCli.trigger('change');
            selTra.trigger('change');

            document.getElementById('txtCantBultos').focus();

        } catch (error) {
            console.error(error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los datos iniciales.' });
        }
    }

    // Autoseleccionar Transporte
    $('#cmbCliente').on('select2:select', function(e) {
        const transporteId = $(this).find(':selected').data('transporte-id');
        if (transporteId) {
            $('#cmbTransporte').val(transporteId).trigger('change');
        }
    });

    // Acción: Guardar e Imprimir
    document.getElementById('btnImprimir').addEventListener('click', async () => {
        const clienteId = $('#cmbCliente').val();
        const transporteId = $('#cmbTransporte').val();
        const bultos = document.getElementById('txtCantBultos').value;
        const unitario = document.getElementById('txtUnitario').value;
        const copias = document.getElementById('txtCopias').value;

        if (!clienteId || !transporteId) {
            Swal.fire({ icon: 'warning', title: 'Faltan datos', text: 'Debe seleccionar Cliente y Transporte.' });
            return;
        }
        if (bultos < 1) {
            Swal.fire({ icon: 'warning', title: 'Error', text: 'La cantidad de bultos debe ser mayor a 0.' });
            return;
        }

        const btn = document.getElementById('btnImprimir');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i> PROCESANDO...';

        try {
            // Enviamos a guardar y generar HTML
            const resp = await fetch('api_imprimir_envios.php?action=save_and_print', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    cliente_id: clienteId,
                    transporte_id: transporteId,
                    bultos: bultos,
                    unitario: unitario,
                    copias: copias
                })
            });

            const res = await resp.json();

            if (res.status === 'error') throw new Error(res.error);

            // Mensaje de éxito
            Swal.fire({ 
                icon: 'success', 
                title: 'Envío Guardado', 
                text: 'Número generado: ' + res.numero,
                timer: 2000 
            });

            // Imprimir
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write(res.html_content);
                printWindow.document.close();
            } else {
                Swal.fire({ icon: 'error', title: 'Bloqueo de Pop-up', text: 'Permita las ventanas emergentes para imprimir.' });
            }

            // Resetear form opcionalmente
            // document.getElementById('frmImprimirEnvio').reset(); 

        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'Error al procesar envío.' });
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalText;
        }
    });

    init();
})();
</script>