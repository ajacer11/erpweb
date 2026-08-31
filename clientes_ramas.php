<?php
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in" id="moduloRamasClientes">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-diagram-project text-accent me-2"></i>RAMAS DE CLIENTES</h4>
            <small class="text-muted">Clasificación comercial de la cartera</small>
        </div>
        <button class="btn btn-info fw-bold" id="btnNuevaRama"><i class="fa-solid fa-plus me-2"></i>NUEVA RAMA</button>
    </div>

    <div class="card card-filtros mb-3">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text bg-transparent border-secondary text-accent"><i class="fa-solid fa-magnifying-glass"></i></span>
                <input class="form-control" id="buscarRamaCliente" placeholder="BUSCAR POR NOMBRE O ID..." autocomplete="off">
            </div>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th class="ps-3" style="width:80px">ID</th><th>RAMA</th><th class="text-end">CLIENTES</th><th class="text-center" style="width:120px">ACCIONES</th></tr></thead>
            <tbody id="ramasClientesBody"></tbody>
        </table>
    </div>
    <div class="erp-cards-wrap" id="ramasClientesCards"></div>
</div>

<div class="modal fade" id="modalRamaCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-secondary">
                <h5 class="modal-title" id="tituloModalRama">NUEVA RAMA</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formRamaCliente">
                <div class="modal-body">
                    <input type="hidden" id="ramaClienteId" value="0">
                    <label class="form-label-custom" for="ramaClienteNombre">NOMBRE</label>
                    <input class="form-control" id="ramaClienteNombre" maxlength="100" required autocomplete="off">
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="submit" class="btn btn-info fw-bold"><i class="fa-solid fa-floppy-disk me-2"></i>GUARDAR</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    const modal = new bootstrap.Modal(document.getElementById('modalRamaCliente'));
    let ramas = [];
    let timer;
    const esc = value => $('<div>').text(value ?? '').html();

    async function listar() {
        const q = document.getElementById('buscarRamaCliente').value;
        document.getElementById('ramasClientesBody').innerHTML = '<tr><td colspan="4" class="text-center py-5"><span class="spinner-border spinner-border-sm text-info me-2"></span>CARGANDO...</td></tr>';
        try {
            const response = await fetch(`api_clientes_ramas.php?q=${encodeURIComponent(q)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await response.json();
            if (!result.success) throw new Error(result.error);
            ramas = result.data || [];
            render();
        } catch (error) {
            Swal.fire('Error', error.message || 'No se pudieron cargar las ramas.', 'error');
        }
    }

    function render() {
        const body = document.getElementById('ramasClientesBody');
        const cards = document.getElementById('ramasClientesCards');
        body.innerHTML = '';
        cards.innerHTML = '';
        if (!ramas.length) {
            body.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">SIN RAMAS ENCONTRADAS</td></tr>';
            cards.innerHTML = '<div class="text-center py-5 text-muted">SIN RAMAS ENCONTRADAS</div>';
            return;
        }
        ramas.forEach(item => {
            const nombre = esc(item.rama);
            body.innerHTML += `<tr><td class="ps-3 text-muted fw-bold">${item.id}</td><td class="fw-bold">${nombre}</td><td class="text-end">${item.clientes}</td><td class="text-center"><button class="btn btn-link text-info p-1" data-edit="${item.id}" title="Editar"><i class="fa-solid fa-pen"></i></button><button class="btn btn-link text-danger p-1" data-delete="${item.id}" title="Eliminar" ${Number(item.clientes) > 0 ? 'disabled' : ''}><i class="fa-solid fa-trash"></i></button></td></tr>`;
            cards.innerHTML += `<div class="erp-card"><div class="erp-card-header"><div class="erp-card-title">${nombre}</div><span class="erp-card-code">#${item.id}</span></div><div class="erp-card-row"><span class="erp-card-label">Clientes asociados</span><span class="erp-card-value">${item.clientes}</span></div><div class="erp-card-actions"><button class="btn btn-info btn-sm" data-edit="${item.id}"><i class="fa-solid fa-pen me-1"></i>EDITAR</button><button class="btn btn-danger btn-sm" data-delete="${item.id}" ${Number(item.clientes) > 0 ? 'disabled' : ''}><i class="fa-solid fa-trash me-1"></i>ELIMINAR</button></div></div>`;
        });
    }

    function abrir(item = null) {
        document.getElementById('ramaClienteId').value = item?.id || 0;
        document.getElementById('ramaClienteNombre').value = item?.rama || '';
        document.getElementById('tituloModalRama').textContent = item ? 'EDITAR RAMA' : 'NUEVA RAMA';
        modal.show();
        setTimeout(() => document.getElementById('ramaClienteNombre').focus(), 200);
    }

    document.getElementById('btnNuevaRama').addEventListener('click', () => abrir());
    document.getElementById('buscarRamaCliente').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(listar, 250); });
    document.getElementById('moduloRamasClientes').addEventListener('click', async event => {
        const edit = event.target.closest('[data-edit]');
        if (edit) return abrir(ramas.find(item => Number(item.id) === Number(edit.dataset.edit)));
        const remove = event.target.closest('[data-delete]');
        if (!remove || remove.disabled) return;
        const confirmacion = await Swal.fire({ icon: 'warning', title: 'Eliminar rama', text: 'Esta acción no se puede deshacer.', showCancelButton: true, confirmButtonText: 'ELIMINAR', cancelButtonText: 'CANCELAR' });
        if (!confirmacion.isConfirmed) return;
        await guardarPeticion('DELETE', { id: Number(remove.dataset.delete) });
    });

    document.getElementById('formRamaCliente').addEventListener('submit', async event => {
        event.preventDefault();
        await guardarPeticion('POST', { id: Number(document.getElementById('ramaClienteId').value), rama: document.getElementById('ramaClienteNombre').value });
        modal.hide();
    });

    async function guardarPeticion(method, data) {
        try {
            const response = await fetch('api_clientes_ramas.php', { method, headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(data) });
            const result = await response.json();
            if (!result.success) throw new Error(result.error);
            await listar();
        } catch (error) {
            Swal.fire('Error', error.message || 'No se pudo guardar la rama.', 'error');
            throw error;
        }
    }

    listar();
})();
</script>
