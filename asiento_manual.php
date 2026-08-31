<?php
// SEGURIDAD CENTRALIZADA
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white"><i class="fa-solid fa-pen-to-square text-accent me-2"></i>ASIENTO MANUAL</h4>
            <small class="text-accent opacity-75">REGISTRO DE MOVIMIENTOS CONTABLES</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-warning" id="btn-plantillas">
                <i class="fa-solid fa-folder-open me-2"></i>PLANTILLAS
            </button>
            <button class="btn btn-success fw-bold px-4" id="btn-guardar">
                <i class="fa-solid fa-save me-2"></i>GUARDAR (F10)
            </button>
        </div>
    </div>

    <!-- CABECERA -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label-custom">FECHA</label>
                    <input type="date" id="txt-fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label-custom">OBSERVACIONES</label>
                    <input type="text" id="txt-obs" class="form-control" placeholder="Descripción del asiento..." autocomplete="off">
                </div>
                <div class="col-md-2">
                    <div class="form-check form-switch pt-3">
                        <input class="form-check-input" type="checkbox" id="chk-fiscal">
                        <label class="form-check-label text-white fw-bold" for="chk-fiscal">ASIENTO FISCAL</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECCIÓN DE CARGA (DEBE / HABER) -->
    <div class="row g-3 mb-3">
        <!-- Lado DEBE -->
        <div class="col-md-6">
            <div class="card card-filtros shadow-sm border-start border-success border-3">
                <div class="card-body p-3">
                    <h6 class="text-success fw-bold mb-3"><i class="fa-solid fa-arrow-right me-2"></i>IMPUTAR AL DEBE</h6>
                    <div class="mb-2">
                        <label class="form-label-custom">CUENTA</label>
                        <select id="cmb-debe" class="form-select select2-cuenta"></select>
                    </div>
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label-custom">IMPORTE</label>
                            <input type="number" id="inp-debe" class="form-control text-end fw-bold text-success" placeholder="0.00">
                        </div>
                        <button class="btn btn-success fw-bold" id="btn-add-debe">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lado HABER -->
        <div class="col-md-6">
            <div class="card card-filtros shadow-sm border-start border-warning border-3">
                <div class="card-body p-3">
                    <h6 class="text-warning fw-bold mb-3"><i class="fa-solid fa-arrow-left me-2"></i>IMPUTAR AL HABER</h6>
                    <div class="mb-2">
                        <label class="form-label-custom">CUENTA</label>
                        <select id="cmb-haber" class="form-select select2-cuenta"></select>
                    </div>
                    <div class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label-custom">IMPORTE</label>
                            <input type="number" id="inp-haber" class="form-control text-end fw-bold text-warning" placeholder="0.00">
                        </div>
                        <button class="btn btn-warning fw-bold" id="btn-add-haber">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRILLA DE MOVIMIENTOS -->
    <div class="card card-filtros shadow-sm flex-grow-1 overflow-hidden mb-3">
        <div class="table-responsive" style="max-height: 400px;">
            <table class="table table-hover align-middle mb-0" id="tabla-asientos">
                <thead class="sticky-top bg-dark">
                    <tr>
                        <th class="text-white ps-3">CUENTA CONTABLE</th>
                        <th class="text-white text-end" style="width: 150px;">DEBE</th>
                        <th class="text-white text-end" style="width: 150px;">HABER</th>
                        <th class="text-center text-white" style="width: 50px;"><i class="fa-solid fa-trash"></i></th>
                    </tr>
                </thead>
                <tbody id="tbody-asientos">
                    <tr><td colspan="4" class="text-center py-4 text-muted">AGREGUE MOVIMIENTOS</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TOTALIZADOR -->
    <div class="card card-totales-dark shadow-sm border-secondary" style="background-color: #000a1a;">
        <div class="card-body p-3">
            <div class="row text-center">
                <div class="col-md-4 border-end border-secondary">
                    <small class="text-success d-block mb-1">TOTAL DEBE</small>
                    <span class="fs-4 fw-bold text-success" id="lbl-total-debe">$ 0.00</span>
                </div>
                <div class="col-md-4 border-end border-secondary">
                    <small class="text-warning d-block mb-1">TOTAL HABER</small>
                    <span class="fs-4 fw-bold text-warning" id="lbl-total-haber">$ 0.00</span>
                </div>
                <div class="col-md-4">
                    <small class="text-white-50 d-block mb-1">DIFERENCIA (SALDO)</small>
                    <span class="fs-4 fw-bold text-white" id="lbl-saldo">$ 0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Plantillas -->
<div class="modal fade" id="modalPlantillas" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom border-secondary">
                <h5 class="modal-title text-white">GESTIÓN DE PLANTILLAS</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label-custom">SELECCIONAR EXISTENTE (PARA CARGAR)</label>
                    <select id="cmb-plantillas" class="form-select select2-simple w-100"></select>
                </div>
                <div class="d-grid mb-4">
                    <button class="btn btn-info rounded-pill fw-bold" id="btn-cargar-plantilla">
                        <i class="fa-solid fa-upload me-2"></i>CARGAR PLANTILLA
                    </button>
                </div>
                
                <hr class="border-secondary my-3">
                
                <div class="mb-3">
                    <label class="form-label-custom">NOMBRE NUEVA PLANTILLA (PARA GUARDAR)</label>
                    <input type="text" id="txt-nombre-plantilla" class="form-control" placeholder="Ej: Sueldos Mensuales">
                </div>
                <div class="d-grid">
                    <button class="btn btn-outline-warning rounded-pill" id="btn-guardar-plantilla">
                        <i class="fa-solid fa-floppy-disk me-2"></i>GUARDAR ASIENTO ACTUAL
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let cuentas = [];
    let items = [];
    let plantillas = [];

    async function init() {
        // Inicializar Select2
        $('.select2-cuenta').select2({
            dropdownParent: $('#main-content'),
            width: '100%',
            placeholder: 'SELECCIONE CUENTA...',
            allowClear: true
        });
        
        $('#cmb-plantillas').select2({
            dropdownParent: $('#modalPlantillas'),
            width: '100%',
            placeholder: 'SELECCIONE...'
        });

        // Cargar Datos
        try {
            const resp = await fetch('api_asiento_manual.php?action=get_init_data', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const data = await resp.json();
            
            if (data.status === 'ok') {
                cuentas = data.data.cuentas;
                plantillas = data.data.plantillas;
                
                const fill = (sel) => {
                    const el = $(sel); 
                    el.empty().append(new Option('SELECCIONE...', '', true, true));
                    cuentas.forEach(c => el.append(new Option(c.id + ' - ' + c.text, c.id, false, false)));
                };
                
                fill('#cmb-debe');
                fill('#cmb-haber');
                
                actualizarComboPlantillas();
            }
        } catch (e) { console.error(e); }

        $('#txt-obs').focus();
    }

    function actualizarComboPlantillas() {
        const el = $('#cmb-plantillas');
        el.empty().append(new Option('SELECCIONE...', '', true, true));
        plantillas.forEach(p => el.append(new Option(p.descripcion, p.id)));
    }

    // Agregar Item
    function addItem(lado) {
        const selId = lado === 'debe' ? '#cmb-debe' : '#cmb-haber';
        const inpId = lado === 'debe' ? '#inp-debe' : '#inp-haber';
        
        const cuentaId = $(selId).val();
        const cuentaText = $(selId).find('option:selected').text();
        // Si el importe está vacío al agregar manual, se asume 0 o lo que ponga el usuario
        // Pero en manual usualmente se pone importe. En plantilla vendrá en 0 para editar.
        let importe = parseFloat($(inpId).val());
        if (isNaN(importe)) importe = 0;

        if (!cuentaId) return Swal.fire('Error', 'Seleccione una cuenta.', 'warning');
        
        // Validación laxa para permitir cargar 0 y editar luego (como en plantillas)
        // if (!importe || importe <= 0) ... (Desactivado para permitir carga flexible)

        items.push({
            cuenta_id: cuentaId,
            cuenta_text: cuentaText,
            debe: lado === 'debe' ? importe : 0,
            haber: lado === 'haber' ? importe : 0
        });

        // Reset inputs
        $(selId).val(null).trigger('change');
        $(inpId).val('');
        $(selId).select2('open');

        renderGrid();
    }

    function renderGrid() {
        const tbody = $('#tbody-asientos');
        tbody.empty();

        let tDebe = 0, tHaber = 0;

        if (items.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center py-4 text-muted">AGREGUE MOVIMIENTOS</td></tr>');
        } else {
            items.forEach((it, idx) => {
                tDebe += parseFloat(it.debe);
                tHaber += parseFloat(it.haber);
                
                // Inputs editables para Debe y Haber
                const inputDebe = `<input type="number" class="form-control form-control-sm text-end inp-edit-grid" 
                    data-idx="${idx}" data-field="debe" value="${it.debe.toFixed(2)}" 
                    onfocus="this.select()" onchange="billManual.updateItem(${idx}, 'debe', this.value)">`;
                
                const inputHaber = `<input type="number" class="form-control form-control-sm text-end inp-edit-grid" 
                    data-idx="${idx}" data-field="haber" value="${it.haber.toFixed(2)}" 
                    onfocus="this.select()" onchange="billManual.updateItem(${idx}, 'haber', this.value)">`;

                const tr = `
                    <tr>
                        <td class="ps-3 fw-bold text-white align-middle">${it.cuenta_text}</td>
                        <td class="text-end" style="width: 150px;">${inputDebe}</td>
                        <td class="text-end" style="width: 150px;">${inputHaber}</td>
                        <td class="text-center align-middle"><button class="btn btn-sm btn-outline-danger border-0" onclick="billManual.removeItem(${idx})"><i class="fa-solid fa-times"></i></button></td>
                    </tr>
                `;
                tbody.append(tr);
            });
        }

        $('#lbl-total-debe').text('$ ' + formatMoney(tDebe));
        $('#lbl-total-haber').text('$ ' + formatMoney(tHaber));
        
        const saldo = tDebe - tHaber;
        const color = Math.abs(saldo) < 0.01 ? 'text-white' : 'text-danger';
        $('#lbl-saldo').text('$ ' + formatMoney(saldo)).removeClass('text-white text-danger').addClass(color);
    }

    // Funciones globales para interactuar desde el HTML generado
    window.billManual = {
        removeItem: (idx) => {
            items.splice(idx, 1);
            renderGrid();
        },
        updateItem: (idx, field, value) => {
            const val = parseFloat(value) || 0;
            if (items[idx]) {
                items[idx][field] = val;
                // Si edito Debe, pongo Haber en 0 y viceversa?
                // En asientos manuales complejos a veces una cuenta tiene ambos, pero es raro.
                // Mantendremos la lógica simple: Si pongo en debe, haber se queda como estaba (usualmente 0).
                // Pero recalculamos totales globales.
                renderGrid(); // Re-render para actualizar totales
                // Foco inteligente? Al re-renderizar se pierde el foco.
                // Mejor actualizar solo los labels de totales si no queremos perder foco,
                // pero como el evento es 'onchange' (al salir), el re-render está bien.
            }
        }
    };

    // Eventos
    $('#btn-add-debe').click(() => addItem('debe'));
    $('#inp-debe').on('keydown', e => { if(e.key === 'Enter') addItem('debe'); });
    
    $('#btn-add-haber').click(() => addItem('haber'));
    $('#inp-haber').on('keydown', e => { if(e.key === 'Enter') addItem('haber'); });

    $('#btn-plantillas').click(() => new bootstrap.Modal(document.getElementById('modalPlantillas')).show());

    $('#btn-guardar').click(async () => {
        if (items.length === 0) return Swal.fire('Error', 'No hay movimientos.', 'warning');
        if (!$('#txt-obs').val()) return Swal.fire('Error', 'Ingrese una observación.', 'warning');
        
        // Check saldo visual
        const tDebe = items.reduce((a,b) => a + parseFloat(b.debe), 0);
        const tHaber = items.reduce((a,b) => a + parseFloat(b.haber), 0);
        if (Math.abs(tDebe - tHaber) > 0.009) return Swal.fire('Error', 'El asiento no balancea.', 'error');

        guardarAsiento(false);
    });

    $(document).off('keydown.asientoManual').on('keydown.asientoManual', e => {
        if (e.key === 'F10') {
            e.preventDefault();
            $('#btn-guardar').click();
        }
    });

    async function guardarAsiento(forceTreasury) {
        try {
            const payload = {
                fecha: $('#txt-fecha').val(),
                observaciones: $('#txt-obs').val(),
                fiscal: $('#chk-fiscal').is(':checked'),
                items: items,
                force_treasury: forceTreasury
            };

            const resp = await fetch('api_asiento_manual.php?action=save_entry', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(payload)
            });
            const res = await resp.json();

            if (res.status === 'ok') {
                Swal.fire('Guardado', res.message, 'success');
                // Limpiar
                items = [];
                $('#txt-obs').val('');
                renderGrid();
            } else if (res.status === 'warning_treasury') {
                Swal.fire({
                    title: 'Atención',
                    text: res.message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, continuar'
                }).then((r) => {
                    if (r.isConfirmed) guardarAsiento(true);
                });
            } else {
                throw new Error(res.error || 'Error desconocido');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }

    // Plantillas
    $('#btn-cargar-plantilla').click(() => {
        const pid = $('#cmb-plantillas').val();
        if (!pid) return;
        
        const tmpl = plantillas.find(p => p.id == pid);
        if (!tmpl) return;

        Swal.fire({
            title: '¿Cargar Plantilla?',
            text: 'Se reemplazarán los registros actuales.',
            icon: 'question',
            showCancelButton: true
        }).then(r => {
            if(r.isConfirmed) {
                items = [];
                const ctas = tmpl.cuentas.split(',');
                ctas.forEach(cId => {
                    cId = cId.trim();
                    const cData = cuentas.find(x => x.id == cId);
                    if (cData) {
                        // Cargar con importe 0 editable
                        items.push({ 
                            cuenta_id: cId, 
                            cuenta_text: cId + ' - ' + cData.text, 
                            debe: 0, 
                            haber: 0 
                        });
                    }
                });
                renderGrid();
                bootstrap.Modal.getInstance(document.getElementById('modalPlantillas')).hide();
            }
        });
    });

    $('#btn-guardar-plantilla').click(async () => {
        if (items.length === 0) return Swal.fire('Error', 'Cargue cuentas primero.', 'warning');
        
        // Ahora tomamos el nombre del input del modal
        const nombre = $('#txt-nombre-plantilla').val().trim();
        
        if (!nombre) {
            return Swal.fire('Error', 'Ingrese un nombre para la plantilla.', 'warning');
        }

        const ids = items.map(i => i.cuenta_id).join(',');
        try {
            const resp = await fetch('api_asiento_manual.php?action=save_template', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({ nombre: nombre, cuentas: ids })
            });
            const res = await resp.json();
            if (res.status === 'ok') {
                Swal.fire('Éxito', res.message, 'success');
                // Recargar data para actualizar combo
                const r2 = await fetch('api_asiento_manual.php?action=get_init_data', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json());
                if(r2.status === 'ok') {
                    plantillas = r2.data.plantillas;
                    actualizarComboPlantillas();
                    $('#txt-nombre-plantilla').val(''); // Limpiar input
                }
            }
        } catch (e) { console.error(e); }
    });

    function formatMoney(n) { return parseFloat(n).toLocaleString('es-AR', {minimumFractionDigits: 2}); }

    init();
})();
</script>