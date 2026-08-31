<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<style>
    .tree-container {
        background-color: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 8px;
        padding: 15px;
        max-height: calc(100vh - 250px);
        overflow-y: auto;
    }
    .tree-node-wrapper {
        margin-bottom: 2px;
    }
    .tree-node {
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s;
        color: var(--text-main);
        user-select: none;
    }
    .tree-node:hover {
        background-color: var(--accent-soft);
    }
    .tree-node.selected {
        background-color: var(--accent);
        color: #000 !important;
        font-weight: bold;
    }
    .tree-node.selected i {
        color: #000 !important;
    }
    .tree-node i {
        width: 20px;
        text-align: center;
        font-size: 1.1rem;
    }
    .node-madre { color: var(--warning); }
    .node-imputable { color: var(--accent); }
    
    .tree-children {
        margin-left: 24px;
        border-left: 1px dashed rgba(255,255,255,0.1);
        display: block;
    }
    .tree-children.collapsed {
        display: none;
    }
    
    .toggle-icon {
        cursor: pointer;
        width: 20px;
        text-align: center;
        display: inline-block;
        transition: transform 0.2s;
    }
    .collapsed .toggle-icon {
        transform: rotate(-90deg);
    }
    .level-padding { width: 20px; display: inline-block; }
</style>

<div class="container-fluid px-4 py-3 fade-in d-flex flex-column h-100">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-shrink-0">
        <div>
            <h4 class="text-white mb-0">PLAN DE CUENTAS</h4>
            <small class="text-accent opacity-75">Jerarquía Estricta 1-1-1-2-3 (Nivel 5 Límite)</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-info rounded-pill" id="btnNuevo">
                <i class="fas fa-plus me-2"></i>NUEVA CUENTA
            </button>
            <button class="btn btn-outline-warning rounded-pill" id="btnEditar">
                <i class="fas fa-edit me-2"></i>EDITAR
            </button>
            <button class="btn btn-outline-danger rounded-pill" id="btnEliminar">
                <i class="fas fa-trash me-2"></i>ELIMINAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm flex-shrink-0">
        <div class="card-body p-2">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-0 text-muted"><i class="fas fa-search"></i></span>
                <input type="text" id="txtBusquedaPlan" class="form-control bg-transparent border-0 text-white" placeholder="BUSCAR POR NOMBRE O CÓDIGO (8 DÍGITOS)...">
            </div>
        </div>
    </div>

    <div class="flex-grow-1 overflow-hidden d-flex flex-column">
        <div class="tree-container flex-grow-1" id="arbolPlanCuentas">
            <div class="text-center p-5"><div class="spinner-border text-info"></div></div>
        </div>
    </div>
</div>

<!-- MODAL ABM CUENTAS -->
<div class="modal fade" id="modalABMCuenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-accent">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="modalTitle">NUEVA CUENTA CONTABLE</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body bg-escritorio">
                <form id="formCuenta">
                    <input type="hidden" id="editMode" value="0">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="chkNoTieneMadre">
                                <label class="form-check-label text-white" for="chkNoTieneMadre">ES CUENTA DE NIVEL 1 (RAÍZ)</label>
                            </div>
                            <label class="form-label-custom">CUENTA MADRE</label>
                            <select id="cmbCuentaMadre" class="form-select select2-modal"></select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label-custom">NÚMERO DE CUENTA</label>
                            <input type="text" id="txtNumCuenta" class="form-control fw-bold text-accent" placeholder="00000000" maxlength="8" readonly>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label-custom">DESCRIPCIÓN / NOMBRE</label>
                            <input type="text" id="txtDescripcion" class="form-control" required>
                        </div>

                        <div class="col-md-12 d-flex gap-4">
                            <div class="form-check" id="containerImputable">
                                <input class="form-check-input" type="checkbox" id="chkImputable">
                                <label class="form-check-label text-white" for="chkImputable">ES IMPUTABLE</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkPatrimonial" checked>
                                <label class="form-check-label text-white" for="chkPatrimonial">ES PATRIMONIAL</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="chkHabilitada" checked>
                                <label class="form-check-label text-white" for="chkHabilitada">HABILITADA</label>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label-custom">OBSERVACIONES</label>
                            <textarea id="txtObservaciones" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-secondary">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info fw-bold rounded-pill px-4" id="btnGuardarCuenta">
                    <i class="fas fa-save me-2"></i>GUARDAR CUENTA
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let selectedAccount = null;
    let flatData = [];

    async function init() {
        $('.select2-modal').select2({ dropdownParent: $('#modalABMCuenta'), width: '100%', theme: 'default' });
        await cargarArbol();

        $('#txtBusquedaPlan').on('input', function() { filtrarArbol($(this).val()); });
        $('#btnNuevo').click(abrirNuevo);
        $('#btnEditar').click(abrirEditar);
        $('#btnEliminar').click(eliminarCuenta);
        $('#btnGuardarCuenta').click(guardarCuenta);
        
        $('#chkNoTieneMadre').change(function() {
            const noMadre = $(this).is(':checked');
            $('#cmbCuentaMadre').prop('disabled', noMadre);
            if (noMadre) {
                $('#cmbCuentaMadre').val(null).trigger('change');
                generarProximoNumero(''); 
            }
        });

        $('#cmbCuentaMadre').on('change', function() {
            if ($('#editMode').val() == '0' && $(this).val()) {
                generarProximoNumero($(this).val());
            }
        });
    }

    async function cargarArbol() {
        try {
            const resp = await fetch('api_plan_cuentas.php?action=get_all', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success) {
                flatData = result.data;
                renderArbol(flatData);
                poblarComboMadres(flatData);
            }
        } catch (e) { console.error(e); }
    }

    function renderArbol(data) {
        const container = $('#arbolPlanCuentas');
        container.empty();
        const map = {}; const roots = [];
        data.forEach(item => { map[item.numcuenta] = { ...item, children: [] }; });
        data.forEach(item => {
            if (item.cuentamadre && map[item.cuentamadre]) map[item.cuentamadre].children.push(map[item.numcuenta]);
            else roots.push(map[item.numcuenta]);
        });

        function buildNodeElement(node) {
            const hasChildren = node.children.length > 0;
            const isImputable = node.imputable == 1;
            const wrapper = $('<div class="tree-node-wrapper"></div>');
            const nodeEl = $(`
                <div class="tree-node" data-num="${node.numcuenta}">
                    ${!isImputable ? `<span class="toggle-icon"><i class="fas fa-caret-down text-muted"></i></span>` : '<span class="level-padding"></span>'}
                    <i class="${isImputable ? 'fas fa-file-invoice node-imputable' : 'fas fa-folder node-madre'}"></i>
                    <span>${node.numcuenta} - ${node.descripcion}</span>
                </div>
            `);
            nodeEl.on('click', function(e) {
                if ($(e.target).closest('.toggle-icon').length > 0) return;
                $('.tree-node').removeClass('selected'); $(this).addClass('selected');
                selectedAccount = $(this).data('num');
            });
            if (!isImputable) {
                nodeEl.find('.toggle-icon').on('click', function() {
                    const childrenCont = $(this).closest('.tree-node-wrapper').find('> .tree-children');
                    childrenCont.toggleClass('collapsed'); $(this).find('i').toggleClass('fa-caret-down fa-caret-right');
                });
            }
            wrapper.append(nodeEl);
            if (hasChildren) {
                const childrenCont = $('<div class="tree-children"></div>');
                node.children.sort((a,b) => a.numcuenta.localeCompare(b.numcuenta)).forEach(child => { childrenCont.append(buildNodeElement(child)); });
                wrapper.append(childrenCont);
            }
            return wrapper;
        }
        roots.sort((a,b) => a.numcuenta.localeCompare(b.numcuenta)).forEach(root => container.append(buildNodeElement(root)));
    }

    function filtrarArbol(val) {
        const busqueda = val.toUpperCase();
        if (!busqueda) { renderArbol(flatData); return; }
        const filtered = flatData.filter(item => item.numcuenta.includes(busqueda) || item.descripcion.toUpperCase().includes(busqueda));
        const container = $('#arbolPlanCuentas'); container.empty();
        filtered.forEach(node => {
            const el = $(`<div class="tree-node" data-num="${node.numcuenta}"><i class="${node.imputable == 1 ? 'fas fa-file-invoice node-imputable' : 'fas fa-folder node-madre'}"></i><span>${node.numcuenta} - ${node.descripcion}</span></div>`);
            el.click(function() { $('.tree-node').removeClass('selected'); $(this).addClass('selected'); selectedAccount = $(this).data('num'); });
            container.append(el);
        });
    }

    function poblarComboMadres(data) {
        const $cmb = $('#cmbCuentaMadre'); $cmb.empty().append(new Option('SELECCIONE MADRE...', ''));
        // REGLA CORREGIDA: Jerarquía 1-5. Madre puede ser nivel 1, 2, 3 o 4 (jerarquia < 5).
        data.filter(i => i.imputable == 0 && i.habilitada == 1 && i.jerarquia < 5).forEach(i => {
            $cmb.append(new Option(`${i.numcuenta} - ${i.descripcion}`, i.numcuenta));
        });
    }

    async function abrirNuevo() {
        $('#formCuenta')[0].reset(); $('#editMode').val('0'); $('#modalTitle').text('NUEVA CUENTA CONTABLE');
        $('#txtNumCuenta').val('').prop('readonly', true); $('#chkImputable').prop('disabled', false).prop('checked', false);
        $('#cmbCuentaMadre').prop('disabled', false); $('#chkNoTieneMadre').prop('disabled', false).prop('checked', false);

        let preSelectMadre = null;
        if (selectedAccount) {
            const acc = flatData.find(i => i.numcuenta == selectedAccount);
            // REGLA CORREGIDA: Nivel 5 es el límite. 
            if (acc && acc.imputable == 0 && acc.jerarquia < 5) {
                const result = await Swal.fire({
                    title: 'Crear Cuenta',
                    text: `¿Desea crear una cuenta hija para [${acc.numcuenta}] o crear una cuenta raíz nueva?`,
                    icon: 'question', showDenyButton: true, showCancelButton: true,
                    confirmButtonText: 'CREAR HIJA', denyButtonText: 'CUENTA RAÍZ', cancelButtonText: 'CANCELAR',
                    confirmButtonColor: 'var(--accent)', denyButtonColor: 'var(--info)', background: '#001020', color: '#fff'
                });
                if (result.isConfirmed) { preSelectMadre = acc.numcuenta; $('#chkNoTieneMadre').prop('checked', false); }
                else if (result.isDenied) { preSelectMadre = null; $('#chkNoTieneMadre').prop('checked', true); }
                else return;
            } else { $('#chkNoTieneMadre').prop('checked', true); }
        } else { $('#chkNoTieneMadre').prop('checked', true); }

        if (preSelectMadre) { $('#cmbCuentaMadre').val(preSelectMadre).trigger('change').prop('disabled', false); }
        else { const isNoMadre = $('#chkNoTieneMadre').is(':checked'); $('#cmbCuentaMadre').val(null).trigger('change').prop('disabled', isNoMadre); if (isNoMadre) generarProximoNumero(''); }
        $('#modalABMCuenta').modal('show');
    }

    async function abrirEditar() {
        if (!selectedAccount) return Swal.fire('Atención', 'Seleccione una cuenta para editar', 'info');
        const acc = flatData.find(i => i.numcuenta == selectedAccount);
        $('#editMode').val('1'); $('#modalTitle').text('EDITAR CUENTA: ' + acc.numcuenta);
        $('#txtNumCuenta').val(acc.numcuenta).prop('readonly', true);
        $('#txtDescripcion').val(acc.descripcion);
        $('#chkImputable').prop('checked', acc.imputable == 1).prop('disabled', true);
        $('#chkPatrimonial').prop('checked', acc.patrimonial == 1);
        $('#chkHabilitada').prop('checked', acc.habilitada == 1);
        $('#txtObservaciones').val(acc.observaciones);
        $('#chkNoTieneMadre').prop('checked', !acc.cuentamadre).prop('disabled', true);
        $('#cmbCuentaMadre').val(acc.cuentamadre).trigger('change').prop('disabled', true);
        $('#modalABMCuenta').modal('show');
    }

    async function generarProximoNumero(madre) {
        try {
            const resp = await fetch(`api_plan_cuentas.php?action=get_next&madre=${madre}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            if (result.success) {
                $('#txtNumCuenta').val(result.proximo);
                // Si es Nivel 5, forzar imputabilidad
                if (result.jerarquia == 5) { $('#chkImputable').prop('checked', true).prop('disabled', true); }
                else { $('#chkImputable').prop('disabled', false); }
            } else { Swal.fire('Atención', result.error, 'warning'); $('#txtNumCuenta').val(''); }
        } catch (e) { console.error(e); }
    }

    async function guardarCuenta() {
        const payload = {
            editMode: $('#editMode').val(), numcuenta: $('#txtNumCuenta').val(),
            descripcion: $('#txtDescripcion').val().toUpperCase(),
            imputable: $('#chkImputable').is(':checked') ? 1 : 0,
            habilitada: $('#chkHabilitada').is(':checked') ? 1 : 0,
            patrimonial: $('#chkPatrimonial').is(':checked') ? 1 : 0,
            cuentamadre: $('#cmbCuentaMadre').val() || '', observaciones: $('#txtObservaciones').val()
        };
        if (!payload.numcuenta) return Swal.fire('Error', 'Error en generación de código', 'error');
        if (!payload.descripcion) return Swal.fire('Error', 'Descripción obligatoria', 'error');

        try {
            $('#btnGuardarCuenta').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>GUARDANDO...');
            const resp = await fetch('api_plan_cuentas.php?action=save', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(payload) });
            const result = await resp.json();
            if (result.success) { $('#modalABMCuenta').modal('hide'); Swal.fire({ icon: 'success', title: 'Guardado', toast: true, position: 'top-end', timer: 3000, showConfirmButton: false }); cargarArbol(); }
            else throw new Error(result.error);
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
        finally { $('#btnGuardarCuenta').prop('disabled', false).html('<i class="fas fa-save me-2"></i>GUARDAR CUENTA'); }
    }

    async function eliminarCuenta() {
        if (!selectedAccount) return Swal.fire('Atención', 'Seleccione cuenta', 'info');
        const confirm = await Swal.fire({ title: '¿Eliminar?', text: `Se eliminará ${selectedAccount}.`, icon: 'warning', showCancelButton: true, confirmButtonColor: 'var(--danger)', confirmButtonText: 'SÍ, ELIMINAR', cancelButtonText: 'CANCELAR', background: '#001020', color: '#fff' });
        if (confirm.isConfirmed) {
            try {
                const resp = await fetch(`api_plan_cuentas.php?action=delete&numcuenta=${selectedAccount}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const result = await resp.json();
                if (result.success) { Swal.fire('Éxito', 'Eliminada', 'success'); selectedAccount = null; cargarArbol(); }
                else throw new Error(result.error);
            } catch (e) { Swal.fire('Error', e.message, 'error'); }
        }
    }
    init();
})();
</script>