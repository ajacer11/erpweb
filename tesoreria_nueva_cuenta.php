<?php
/**
 * tesoreria_nueva_cuenta.php - FORMULARIO ABM DE CUENTAS DE TESORERÍA
 * Migrado de abmcuentastesoreria.vb con integración de api_helpers.php
 */

require_once 'check_access.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$clone_id = isset($_GET['clone_id']) ? intval($_GET['clone_id']) : 0;
$actual_id = ($id > 0) ? $id : 0;
$titulo = ($id > 0) ? "MODIFICAR CUENTA" : ($clone_id > 0 ? "CLONAR CUENTA DE TESORERÍA" : "NUEVA CUENTA DE TESORERÍA");
?>

<div class="container-fluid px-4 py-3 fade-in">
    <!-- Cabecera del Módulo -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0"><?php echo $titulo; ?></h4>
            <small class="text-accent opacity-75">Configuración de medios de pago, cuentas bancarias y flujo de fondos</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill btn-sm" onclick="loadModule('tesoreria_admin_cuentas.php')">
            <i class="fas fa-arrow-left me-2"></i>VOLVER AL LISTADO
        </button>
    </div>

    <form id="formTesoreriaABM" class="card card-filtros p-4 shadow-sm border-0">
        <input type="hidden" name="id" value="<?php echo $actual_id; ?>">
        
        <div class="row g-4">
            <!-- Sección Principal: Identificación y Tipos -->
            <div class="col-md-7 border-end border-secondary pe-md-4">
                <div class="mb-4">
                    <label class="form-label-custom">DESCRIPCIÓN DEL MEDIO / CUENTA</label>
                    <input type="text" name="medio" id="txtMedio" class="form-control form-control-lg fw-bold text-accent" required placeholder="Ej: CAJA PESOS, TARJETA VISA, etc.">
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label-custom">TIPO DE CUENTA</label>
                        <select name="tipodecuenta" id="cmbTipo" class="form-select select2-simple" required>
                            <option value="Efectivo">EFECTIVO</option>
                            <option value="Caja de Usuarios">CAJA DE USUARIOS</option>
                            <option value="Caja Central">CAJA CENTRAL</option>
                            <option value="Bancos">BANCOS</option>
                            <option value="Tarjetas">TARJETAS</option>
                            <option value="Cheques">CHEQUES (genérico)</option>
                            <option value="Cartera de Cheques">CARTERA DE CHEQUES (de terceros, recibidos)</option>
                            <option value="Cheques Emitidos">CHEQUES EMITIDOS (propios)</option>
                            <option value="Retenciones">RETENCIONES</option>
                            <option value="Otros">OTROS</option>
                        </select>
                        <small class="text-muted d-block mt-1">
                            El sistema busca estos tipos por nombre exacto en distintas pantallas (cobros, pagos, cajas):
                            solo puede existir <strong>una</strong> cuenta "Caja Central" habilitada, y para operar con cheques
                            de terceros o propios hace falta una cuenta de tipo "Cartera de Cheques" / "Cheques Emitidos"
                            respectivamente (el tipo genérico "Cheques" no alcanza para esas pantallas).
                        </small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">CUENTA CONTABLE (PLAN DE CTAS)</label>
                        <select name="cuentacontable" id="cmbCtaContable" class="form-select select2-simple" required></select>
                    </div>
                </div>

                <!-- Paneles Condicionales (Asociaciones Específicas) -->
                <div id="panelPuestos" class="p-3 bg-dark bg-opacity-25 rounded border border-info border-opacity-50 mb-3 d-none">
                    <label class="form-label-custom text-info"><i class="fas fa-cash-register me-2"></i>ASOCIAR A PUESTO DE COBRO</label>
                    <select name="puestoasociado" id="cmbPuesto" class="form-select select2-simple"></select>
                </div>

                <div id="panelBancos" class="p-3 bg-dark bg-opacity-25 rounded border border-info border-opacity-50 mb-3 d-none">
                    <label class="form-label-custom text-info"><i class="fas fa-university me-2"></i>VINCULAR A CUENTA BANCARIA PROPIA</label>
                    <select name="cuentabancaria" id="cmbCtaBancaria" class="form-select select2-simple"></select>
                </div>

                <!-- Configuración de Operatividad y Flujo -->
                <h6 class="text-accent mb-3 small fw-bold border-bottom border-secondary pb-1 mt-4">OPERATIVIDAD DEL MEDIO</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check custom-radio-box text-center p-3 rounded border border-secondary" id="boxOrigen">
                            <input class="form-check-input d-none" type="radio" name="flujo" id="rOrigen" value="origen">
                            <label class="form-check-label text-white w-100 cursor-pointer" for="rOrigen">
                                <i class="fas fa-sign-out-alt mb-2 d-block fa-lg text-info"></i>
                                SOLO EGRESOS
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check custom-radio-box text-center p-3 rounded border border-secondary" id="boxDestino">
                            <input class="form-check-input d-none" type="radio" name="flujo" id="rDestino" value="destino">
                            <label class="form-check-label text-white w-100 cursor-pointer" for="rDestino">
                                <i class="fas fa-sign-in-alt mb-2 d-block fa-lg text-warning"></i>
                                SOLO INGRESOS
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check custom-radio-box text-center p-3 rounded border border-secondary" id="boxAmbas">
                            <input class="form-check-input d-none" type="radio" name="flujo" id="rAmbas" value="ambas" checked>
                            <label class="form-check-label text-white w-100 cursor-pointer" for="rAmbas">
                                <i class="fas fa-exchange-alt mb-2 d-block fa-lg text-accent"></i>
                                AMBOS FLUJOS
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sección Secundaria: Visibilidad y Auditoría -->
            <div class="col-md-5">
                <h6 class="text-accent mb-3 small fw-bold border-bottom border-secondary pb-1">CONFIGURACIÓN DE VISIBILIDAD</h6>
                
                <div class="row g-2">
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visibletransferenciascuentas" id="chkT1" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT1">Visible en Transferencias entre Cuentas</label>
                    </div></div>
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visibleajustescajas" id="chkT2" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT2">Visible en Ajustes de Caja</label>
                    </div></div>
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visiblemovcajacentral" id="chkT3" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT3">Visible en Movimientos de Caja Central</label>
                    </div></div>
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visibletransferenciaotracaja" id="chkT4" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT4">Visible en Transferencia a otra Caja</label>
                    </div></div>
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visibleretiroscajacentral" id="chkT5" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT5">Visible en Retiros a Caja Central</label>
                    </div></div>
                    <div class="col-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visibleenventas" id="chkT6" value="1" checked>
                        <label class="form-check-label text-white-50 small" for="chkT6">Mostrar como opción en Pantalla de Ventas</label>
                    </div></div>
                </div>

                <h6 class="text-accent mb-3 small fw-bold border-bottom border-secondary pb-1 mt-4">ESTADOS Y AUDITORÍA</h6>
                <div class="row g-2">
                    <div class="col-md-6"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="habilitadocobro" id="chkC1" value="1" checked>
                        <label class="form-check-label text-white small" for="chkC1">Permitir Cobros</label>
                    </div></div>
                    <div class="col-md-6"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="habilitadopagos" id="chkC2" value="1" checked>
                        <label class="form-check-label text-white small" for="chkC2">Permitir Pagos</label>
                    </div></div>
                    <div class="col-md-12"><div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="habilitadomovimientosmanuales" id="chkC3" value="1" checked>
                        <label class="form-check-label text-white small" for="chkC3">Permitir Movimientos Manuales Directos</label>
                    </div></div>
                </div>

                <div class="p-3 rounded bg-info bg-opacity-10 border border-info border-opacity-25 mt-4 shadow-sm">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="habilitado" id="chkHabilitado" value="1" checked>
                        <label class="form-check-label fw-bold text-info ms-2" for="chkHabilitado">CUENTA ACTIVA (DISPONIBLE EN SISTEMA)</label>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botonera -->
        <div class="mt-4 pt-3 border-top d-flex gap-2 justify-content-end">
            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="loadModule('tesoreria_admin_cuentas.php')">CANCELAR</button>
            <button type="submit" class="btn btn-info fw-bold px-5 rounded-pill shadow">
                <i class="fas fa-save me-2"></i><?php echo ($id > 0) ? "GUARDAR CAMBIOS" : "REGISTRAR CUENTA"; ?>
            </button>
        </div>
    </form>
</div>

<style>
.custom-radio-box { transition: 0.2s; cursor: pointer; border-width: 2px !important; }
.custom-radio-box:hover { background: rgba(0, 255, 255, 0.05); }
.custom-radio-box.active { border-color: var(--accent) !important; background: rgba(0, 255, 255, 0.1) !important; }
.cursor-pointer { cursor: pointer; }
</style>

<script>
(function() {
    const id = <?php echo $actual_id; ?>;
    const clone_id = <?php echo $clone_id; ?>;

    /**
     * Gestión visual de los radio buttons de flujo
     */
    document.querySelectorAll('input[name="flujo"]').forEach(radio => {
        radio.addEventListener('change', function() {
            document.querySelectorAll('.custom-radio-box').forEach(box => {
                box.classList.remove('active', 'border-info');
                box.classList.add('border-secondary');
            });
            const parent = this.closest('.custom-radio-box');
            parent.classList.remove('border-secondary');
            parent.classList.add('active', 'border-info');
        });
    });

    /**
     * Lógica de paneles condicionales según tipo de cuenta
     */
    $('#cmbTipo').on('change', function() {
        const val = $(this).val();
        $('#panelPuestos').toggleClass('d-none', val !== 'Caja de Usuarios');
        $('#panelBancos').toggleClass('d-none', val !== 'Bancos');
    });

    async function init() {
        // Cargar Helpers (Plan de Cuentas, Puestos y Cuentas Bancarias)
        try {
            const commonHeaders = {'X-Requested-With': 'XMLHttpRequest'};
            
            // Intentamos cargar de api_helpers.php
            // Si no existen, el sistema fallará silenciosamente o mostrará el error en consola.
            const [resPlan, resPuestos, resCuentas] = await Promise.all([
                fetch('api_helpers.php?action=get_plan_cuentas', { headers: commonHeaders }),
                fetch('api_helpers.php?action=get_puestos_cobro', { headers: commonHeaders }),
                fetch('api_bancos_cuentas.php?action=list', { headers: commonHeaders })
            ]);

            const planResult = await resPlan.json();
            const puestosResult = await resPuestos.json();
            
            let cuentas = [];
            try {
                const cuentasData = await resCuentas.json();
                cuentas = Array.isArray(cuentasData) ? cuentasData : (cuentasData.data || []);
            } catch(e) { console.error("Error al cargar cuentas bancarias", e); }

            // Llenar Plan de Cuentas (usando .data si viene de api_helpers)
            const cmbPlan = $('#cmbCtaContable');
            cmbPlan.empty().append(new Option('SELECCIONE CUENTA...', '', true, true));
            if(planResult.success && planResult.data) {
                planResult.data.forEach(p => cmbPlan.append(new Option(`${p.numcuenta} - ${p.descripcion}`, p.numcuenta)));
            } else {
                console.warn("No se encontró el Plan de Cuentas en api_helpers.php");
            }

            // Llenar Puestos de Cobro (usando .data si viene de api_helpers)
            const cmbPuesto = $('#cmbPuesto');
            cmbPuesto.empty().append(new Option('SIN PUESTO ASOCIADO', '0', true, true));
            if(puestosResult.success && puestosResult.data) {
                puestosResult.data.forEach(p => cmbPuesto.append(new Option(p.nombre.toUpperCase(), p.id)));
            } else {
                console.warn("No se encontraron Puestos de Cobro en api_helpers.php");
            }

            // Llenar Cuentas Bancarias Propias (de api_bancos_cuentas.php)
            const cmbCta = $('#cmbCtaBancaria');
            cmbCta.empty().append(new Option('SIN CUENTA BANCARIA', '0', true, true));
            cuentas.forEach(c => cmbCta.append(new Option(`${c.referencia} - ${c.banco_nombre}`, c.id)));

            $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

            // Cargar datos si es edición o clonación
            const target_id = id > 0 ? id : clone_id;
            if (target_id > 0) {
                const resp = await fetch(`api_tesoreria_cuentas.php?action=get&id=${target_id}`, { headers: commonHeaders });
                const result = await resp.json();
                
                if(result.success) {
                    const d = result.data;
                    $('#txtMedio').val(clone_id > 0 ? d.medio + ' (COPIA)' : d.medio);
                    $('#cmbTipo').val(d.tipodecuenta).trigger('change');
                    $('#cmbCtaContable').val(d.cuentacontable).trigger('change');
                    $('#cmbPuesto').val(d.puestoasociado).trigger('change');
                    $('#cmbCtaBancaria').val(d.cuentabancaria).trigger('change');

                    // Flujo visual
                    if(d.mostrarenorigen == 1 && d.mostrarendestino == 0) $('#rOrigen').click();
                    else if(d.mostrarenorigen == 0 && d.mostrarendestino == 1) $('#rDestino').click();
                    else $('#rAmbas').click();

                    // Checkboxes de Visibilidad
                    document.getElementById('chkT1').checked = d.visibletransferenciascuentas == 1;
                    document.getElementById('chkT2').checked = d.visibleajustescajas == 1;
                    document.getElementById('chkT3').checked = d.visiblemovcajacentral == 1;
                    document.getElementById('chkT4').checked = d.visibletransferenciaotracaja == 1;
                    document.getElementById('chkT5').checked = d.visibleretiroscajacentral == 1;
                    document.getElementById('chkT6').checked = d.visibleenventas == 1;
                    
                    // Checkboxes de Operativa
                    document.getElementById('chkC1').checked = d.habilitadocobro == 1;
                    document.getElementById('chkC2').checked = d.habilitadopagos == 1;
                    document.getElementById('chkC3').checked = d.habilitadomovimientosmanuales == 1;
                    document.getElementById('chkHabilitado').checked = d.habilitado == 1;
                }
            }
        } catch(err) {
            console.error("Error en inicialización del formulario", err);
        }
    }

    document.getElementById('formTesoreriaABM').onsubmit = async function(e) {
        e.preventDefault();

        if (!this.checkValidity()) {
            this.reportValidity();
            return;
        }

        const formData = new FormData(this);
        const data = Object.fromEntries(formData.entries());
        
        const checkMapping = {
            'visibletransferenciascuentas': 'chkT1', 'visibleajustescajas': 'chkT2', 'visiblemovcajacentral': 'chkT3',
            'visibletransferenciaotracaja': 'chkT4', 'visibleretiroscajacentral': 'chkT5', 'visibleenventas': 'chkT6',
            'habilitadocobro': 'chkC1', 'habilitadopagos': 'chkC2', 'habilitadomovimientosmanuales': 'chkC3',
            'habilitado': 'chkHabilitado'
        };

        for(let key in checkMapping) {
            data[key] = document.getElementById(checkMapping[key]).checked ? 1 : 0;
        }

        data.mostrarenorigen = (data.flujo === 'origen' || data.flujo === 'ambas') ? 1 : 0;
        data.mostrarendestino = (data.flujo === 'destino' || data.flujo === 'ambas') ? 1 : 0;

        const resp = await fetch('api_tesoreria_cuentas.php?action=save', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(data)
        });

        const result = await resp.json();
        if(result.success) {
            Swal.fire({ title: '¡Éxito!', text: 'La cuenta de tesorería ha sido grabada.', icon: 'success', background: '#001020', color: '#fff', timer: 1500, showConfirmButton: false });
            setTimeout(() => loadModule('tesoreria_admin_cuentas.php'), 600);
        } else {
            Swal.fire({ title: 'Atención', text: result.error || 'No se pudo guardar la cuenta.', icon: 'error', background: '#001020', color: '#fff' });
        }
    }

    init();
})();
</script>