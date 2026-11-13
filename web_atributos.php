<?php
// SEGURIDAD CENTRALIZADA (Check Access) - módulo contratado aparte (addon web_catalogo)
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">CARACTERÍSTICAS DE PRODUCTOS</h4>
            <small class="text-accent opacity-75">La ficha técnica que se muestra en la web (como en MercadoLibre)</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4" onclick="atrEditar(0)"><i class="fas fa-plus me-2"></i>NUEVA CARACTERÍSTICA</button>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="fas fa-circle-info me-2"></i>
        Creá las características que usan tus productos (ej. <b>Largo</b> en cm, <b>Material</b> con opciones, <b>Sumergible</b> sí/no) y
        asignalas a los <b>rubros o familias</b> donde aplican: al editar un producto de ese rubro, aparecen para completarlas en la pestaña <b>Web</b>.
        Las marcadas como <b>filtro</b> se pueden usar para filtrar el catálogo.
    </div>

    <div class="card card-filtros p-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Característica</th><th>Tipo</th><th>Se pide en</th><th class="text-center">Filtro</th><th class="text-center">Productos</th><th class="text-center">Activa</th><th></th></tr></thead>
                <tbody id="atrTabla"><tr><td colspan="7" class="text-center text-muted">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="atrModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary"><h5 class="modal-title" id="atrModalTitulo">Característica</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="atrId">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label-custom">NOMBRE</label><input class="form-control" id="atrNombre" maxlength="80" placeholder="Ej: Largo, Material, Talle"></div>
                    <div class="col-md-3"><label class="form-label-custom">TIPO</label>
                        <select class="form-select" id="atrTipo">
                            <option value="texto">Texto libre</option>
                            <option value="numero">Número</option>
                            <option value="opciones">Lista de opciones</option>
                            <option value="sino">Sí / No</option>
                        </select>
                    </div>
                    <div class="col-md-3" id="atrUnidadBox"><label class="form-label-custom">UNIDAD</label><input class="form-control" id="atrUnidad" maxlength="20" placeholder="cm, kg, m, mm..."></div>
                    <div class="col-12" id="atrOpcionesBox">
                        <label class="form-label-custom">OPCIONES (UNA POR LÍNEA)</label>
                        <textarea class="form-control" id="atrOpciones" rows="5" placeholder="Carbono&#10;Fibra de vidrio&#10;Grafito"></textarea>
                    </div>
                    <div class="col-md-6"><label class="form-label-custom">SE PIDE EN LOS RUBROS</label><select class="form-select" id="atrRubros" multiple style="width:100%"></select></div>
                    <div class="col-md-6"><label class="form-label-custom">SE PIDE EN LAS FAMILIAS</label><select class="form-select" id="atrFamilias" multiple style="width:100%"></select></div>
                    <div class="col-md-3"><label class="form-label-custom">ORDEN</label><input type="number" class="form-control" id="atrOrden" value="0"></div>
                    <div class="col-md-9 d-flex align-items-end gap-4">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="atrFiltrable"><label class="form-check-label" for="atrFiltrable">Usar como filtro en el catálogo</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="atrActivo" checked><label class="form-check-label" for="atrActivo">Activa</label></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-outline-danger me-auto" id="atrBtnEliminar" onclick="atrEliminar()"><i class="fas fa-trash me-1"></i>Eliminar</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info fw-bold" onclick="atrGuardar()"><i class="fas fa-save me-1"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const API = 'api_web_atributos.php';
    const H = {'X-Requested-With': 'XMLHttpRequest'};
    const TIPOS = {texto: 'Texto', numero: 'Número', opciones: 'Opciones', sino: 'Sí / No'};
    let atributos = [], familias = [];
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const modal = new bootstrap.Modal(document.getElementById('atrModal'));

    async function cargar() {
        const r = await (await fetch(`${API}?action=listar`, {headers: H})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        atributos = r.data;
        familias = r.familias;
        $('#atrRubros').html(r.rubros.map(x => `<option value="${esc(x)}">${esc(x)}</option>`).join(''));
        $('#atrFamilias').html(familias.map(f => `<option value="${f.id}">${esc(f.nombre)}</option>`).join(''));
        const famNom = Object.fromEntries(familias.map(f => [String(f.id), f.nombre]));
        $('#atrTabla').html(atributos.length ? atributos.map(a => {
            const donde = a.plantillas.map(p => p.ambito === 'rubro' ? esc(p.ambito_valor) : '<i>' + esc(famNom[p.ambito_valor] || ('Familia ' + p.ambito_valor)) + '</i>');
            const tipo = TIPOS[a.tipo] + (a.tipo === 'numero' && a.unidad ? ` (${esc(a.unidad)})` : '') + (a.tipo === 'opciones' ? ` <small class="text-muted">${a.opciones.length}</small>` : '');
            return `<tr class="${a.activo == 1 ? '' : 'opacity-50'}" style="cursor:pointer" onclick="atrEditar(${a.id})">
                <td class="fw-bold">${esc(a.nombre)}</td><td>${tipo}</td>
                <td class="small">${donde.join(', ') || '<span class="text-muted">Ninguno (solo se agrega a mano)</span>'}</td>
                <td class="text-center">${a.filtrable == 1 ? '<i class="fas fa-filter text-info"></i>' : ''}</td>
                <td class="text-center">${a.usos}</td>
                <td class="text-center">${a.activo == 1 ? '<i class="fas fa-check text-success"></i>' : ''}</td>
                <td class="text-end"><i class="fas fa-pen text-muted"></i></td></tr>`;
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay características. Creá la primera.</td></tr>');
    }

    function toggleTipo() {
        const t = $('#atrTipo').val();
        $('#atrUnidadBox').toggle(t === 'numero');
        $('#atrOpcionesBox').toggle(t === 'opciones');
    }
    $('#atrTipo').on('change', toggleTipo);

    window.atrEditar = function(id) {
        const a = atributos.find(x => x.id == id) || {id: 0, nombre: '', tipo: 'texto', unidad: '', opciones: [], plantillas: [], filtrable: 0, orden: 0, activo: 1};
        $('#atrModalTitulo').text(id ? 'Editar característica' : 'Nueva característica');
        $('#atrId').val(a.id);
        $('#atrNombre').val(a.nombre);
        $('#atrTipo').val(a.tipo);
        $('#atrUnidad').val(a.unidad || '');
        $('#atrOpciones').val(a.opciones.join('\n'));
        $('#atrOrden').val(a.orden);
        $('#atrFiltrable').prop('checked', a.filtrable == 1);
        $('#atrActivo').prop('checked', a.activo == 1);
        $('#atrRubros').val(a.plantillas.filter(p => p.ambito === 'rubro').map(p => p.ambito_valor));
        $('#atrFamilias').val(a.plantillas.filter(p => p.ambito === 'familia').map(p => String(p.ambito_valor)));
        $('#atrBtnEliminar').toggle(!!id);
        toggleTipo();
        modal.show();
        $('#atrRubros, #atrFamilias').each(function() {
            if ($(this).data('select2')) $(this).select2('destroy');
            $(this).select2({dropdownParent: $('#atrModal'), width: '100%', placeholder: 'Ninguno'});
        });
    };

    window.atrGuardar = async function() {
        const datos = {
            id: +$('#atrId').val(),
            nombre: $('#atrNombre').val(),
            tipo: $('#atrTipo').val(),
            unidad: $('#atrUnidad').val(),
            opciones: $('#atrOpciones').val().split('\n'),
            orden: +$('#atrOrden').val(),
            filtrable: $('#atrFiltrable').is(':checked'),
            activo: $('#atrActivo').is(':checked'),
            plantillas: [
                ...($('#atrRubros').val() || []).map(v => ({ambito: 'rubro', ambito_valor: v})),
                ...($('#atrFamilias').val() || []).map(v => ({ambito: 'familia', ambito_valor: v})),
            ],
        };
        const r = await (await fetch(`${API}?action=guardar`, {method: 'POST', headers: {...H, 'Content-Type': 'application/json'}, body: JSON.stringify(datos)})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        modal.hide();
        Swal.fire({icon: 'success', title: r.message, timer: 1200, showConfirmButton: false});
        cargar();
    };

    window.atrEliminar = async function() {
        const a = atributos.find(x => x.id == $('#atrId').val());
        const c = await Swal.fire({icon: 'warning', title: `¿Eliminar "${a.nombre}"?`,
            text: a.usos > 0 ? `Se borra también el valor cargado en ${a.usos} producto(s). Si solo querés dejar de usarla, desactivala.` : '',
            showCancelButton: true, confirmButtonText: 'Sí, eliminar', confirmButtonColor: '#dc3545'});
        if (!c.isConfirmed) return;
        const r = await (await fetch(`${API}?action=eliminar`, {method: 'POST', headers: {...H, 'Content-Type': 'application/json'}, body: JSON.stringify({id: a.id})})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        modal.hide();
        cargar();
    };

    cargar();
})();
</script>
