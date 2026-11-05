<?php
$esProveedorGestion = ($gestionTipo ?? '') === 'proveedor';
$apiGestion = $esProveedorGestion ? 'api_imputar_pagos_prov.php' : 'api_imputar_cobros.php';
$selectorEntidadGestion = $esProveedorGestion ? '#cmb-proveedor' : '#cmb-cliente';
$paramEntidadGestion = $esProveedorGestion ? 'proveedor' : 'cliente';
?>
<div class="container-fluid px-4 pb-4" id="gestion-imputaciones">
    <div class="card card-filtros shadow-sm mt-3">
        <div class="card-header bg-dark border-secondary d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div>
                <strong class="text-white">GESTIÓN DE IMPUTACIONES</strong>
                <div class="text-muted small">Ver imputaciones anteriores, corregir importes, quitar o asignar <?= $esProveedorGestion ? 'pagos' : 'recibos' ?>.</div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <input id="gi-buscar" class="form-control form-control-sm" style="width:190px" placeholder="Buscar comprobante o ID">
                <label class="text-white small mb-0 text-nowrap"><input type="checkbox" id="gi-pendientes" class="form-check-input me-1">Solo pendientes</label>
            </div>
        </div>
        <div class="card-body p-2" id="gi-lista"><div class="text-muted text-center p-4">Seleccione <?= $esProveedorGestion ? 'un proveedor' : 'un cliente' ?> para ver sus comprobantes.</div></div>
        <div class="card-footer bg-dark border-secondary d-flex align-items-center justify-content-between">
            <button class="btn btn-sm btn-outline-info" id="gi-anterior" disabled>Anterior</button>
            <span class="text-muted small" id="gi-pagina">Página 1</span>
            <button class="btn btn-sm btn-outline-info" id="gi-siguiente" disabled>Siguiente</button>
        </div>
    </div>
</div>
<script>
(function () {
    const api = <?= json_encode($apiGestion) ?>;
    const selectorEntidad = <?= json_encode($selectorEntidadGestion) ?>;
    const paramEntidad = <?= json_encode($paramEntidadGestion) ?>;
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let pagina = 1, hayMas = false, expandido = 0, temporizador;
    const dinero = v => Number(v || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = v => String(v ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
    const entidad = () => Number($(selectorEntidad).val()) || 0;

    async function cargar() {
        if (!entidad()) {
            $('#gi-lista').html('<div class="text-muted text-center p-4">Seleccione una entidad.</div>');
            return;
        }
        $('#gi-lista').html('<div class="text-muted text-center p-4">Cargando...</div>');
        const params = new URLSearchParams({action:'get_gestion', [paramEntidad]:entidad(), pagina,
            buscar:$('#gi-buscar').val().trim(), solo_pendientes:$('#gi-pendientes').is(':checked') ? 1 : 0});
        try {
            const res = await fetch(api + '?' + params, {headers}).then(r => r.json());
            if (!res.success) throw new Error(res.error || 'No se pudieron cargar las imputaciones.');
            hayMas = res.hay_mas;
            $('#gi-pagina').text('Página ' + pagina);
            $('#gi-anterior').prop('disabled', pagina === 1);
            $('#gi-siguiente').prop('disabled', !hayMas);
            if (!res.facturas.length) {
                $('#gi-lista').html('<div class="text-muted text-center p-4">No hay comprobantes para este filtro.</div>');
                return;
            }
            $('#gi-lista').html(res.facturas.map(f => {
                const abierto = Number(f.id) === expandido;
                const pendiente = Number(f.pendiente);
                return `<div class="border-bottom border-secondary">
                    <button type="button" class="btn btn-link text-start text-decoration-none w-100 d-flex align-items-center gap-2 gi-abrir" data-id="${Number(f.id)}">
                        <span class="text-info">${abierto ? '▼' : '▶'}</span>
                        <strong class="text-white">${esc(f.numero)}</strong>
                        <small class="text-muted">${esc(f.fecha)} · ID ${Number(f.id)}</small>
                        <span class="ms-auto text-white">$ ${dinero(f.total)}</span>
                        <span class="badge ${pendiente > 0.009 ? 'bg-warning text-dark' : 'bg-success'}">${pendiente > 0.009 ? 'Pend. $ ' + dinero(pendiente) : 'Cancelado'}</span>
                    </button>
                    ${abierto ? detalle(f) : ''}
                </div>`;
            }).join(''));
            $('#gi-lista .gi-fondo').each(function () {
                $(this).select2({dropdownParent:$('#main-content'), width:'100%', placeholder:'Buscar pago o recibo por ID...',
                    minimumResultsForSearch:0, ajax:{url:api + '?action=buscar_fondos&' + paramEntidad + '=' + entidad(),
                        dataType:'json', delay:250, headers, data:p=>({q:p.term}),
                        processResults:r=>({results:(r.data || []).map(x=>({id:x.id, text:'#' + x.id + ' · ' + x.fecha + ' · Disponible $ ' + dinero(x.disponible)}))})}});
            });
        } catch (e) { $('#gi-lista').html('<div class="text-danger text-center p-4">' + esc(e.message) + '</div>'); }
    }

    function detalle(f) {
        const filas = f.imputaciones.map(i => {
            const editable = i.tipo === 'P' || i.tipo === 'pagos';
            return `<div class="d-flex align-items-center gap-2 flex-wrap border-bottom border-secondary py-2">
                <span class="badge ${editable ? 'bg-info text-dark' : 'bg-secondary'}">${editable ? 'Pago/Recibo' : 'NC'} #${Number(i.idpago)}</span>
                <small class="text-muted">Imputación #${Number(i.id)}</small>
                <input type="number" min="0.01" step="0.01" value="${Number(i.importepagado)}" class="form-control form-control-sm gi-importe" style="width:130px" data-id="${Number(i.id)}" ${editable ? '' : 'disabled'}>
                ${editable ? `<button class="btn btn-sm btn-outline-info gi-editar" data-id="${Number(i.id)}">Cambiar importe</button>
                    <button class="btn btn-sm btn-outline-danger gi-quitar" data-id="${Number(i.id)}">Desimputar</button>` : '<small class="text-muted">Nota de crédito</small>'}
            </div>`;
        }).join('') || '<div class="text-muted small py-2">Sin imputaciones previas.</div>';
        return `<div class="px-4 pb-3" data-factura="${Number(f.id)}">${filas}
            ${Number(f.pendiente) > 0.009 ? `<div class="row g-2 align-items-end mt-1">
                <div class="col-md-6"><label class="text-muted small">Pago o recibo disponible</label><select class="gi-fondo"><option></option></select></div>
                <div class="col-md-3"><label class="text-muted small">Importe a imputar</label><input class="form-control form-control-sm gi-nuevo-importe" type="number" min="0.01" max="${Number(f.pendiente)}" step="0.01"></div>
                <div class="col-md-3"><button class="btn btn-sm btn-info w-100 gi-agregar" data-id="${Number(f.id)}">Agregar imputación</button></div>
            </div>` : ''}
        </div>`;
    }

    async function modificar(datos, pregunta) {
        if (!(await Swal.fire({title:pregunta, icon:'question', showCancelButton:true, confirmButtonText:'CONFIRMAR'})).isConfirmed) return;
        try {
            const res = await fetch(api + '?action=modificar_gestion', {method:'POST',
                headers:{...headers,'Content-Type':'application/json'}, body:JSON.stringify({entidad_id:entidad(), ...datos})}).then(r=>r.json());
            if (!res.success) throw new Error(res.error || 'No se pudo cambiar la imputación.');
            $(selectorEntidad).trigger({type:'select2:select', params:{data:{id:entidad()}}});
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    }

    let entidadAnterior = 0;
    $(selectorEntidad).on('select2:select.gestion', function () {
        if (entidad() !== entidadAnterior) { pagina = 1; expandido = 0; entidadAnterior = entidad(); }
        cargar();
    });
    $('#gi-buscar').on('input', function () { clearTimeout(temporizador); temporizador = setTimeout(() => { pagina = 1; cargar(); }, 250); });
    $('#gi-pendientes').on('change', function () { pagina = 1; cargar(); });
    $('#gi-anterior').on('click', function () { if (pagina > 1) { pagina--; cargar(); } });
    $('#gi-siguiente').on('click', function () { if (hayMas) { pagina++; cargar(); } });
    $('#gi-lista').on('click', '.gi-abrir', function () { const id=Number($(this).data('id')); expandido = expandido === id ? 0 : id; cargar(); });
    $('#gi-lista').on('click', '.gi-agregar', function () {
        const box=$(this).closest('[data-factura]');
        modificar({accion:'agregar', factura_id:Number($(this).data('id')), pago_id:Number(box.find('.gi-fondo').val()), importe:Number(box.find('.gi-nuevo-importe').val())}, '¿Agregar imputación?');
    });
    $('#gi-lista').on('click', '.gi-quitar', function () { modificar({accion:'quitar', imputacion_id:Number($(this).data('id'))}, '¿Desimputar este pago?'); });
    $('#gi-lista').on('click', '.gi-editar', function () {
        const id=Number($(this).data('id'));
        modificar({accion:'importe', imputacion_id:id, importe:Number($('#gi-lista .gi-importe[data-id="'+id+'"]').val())}, '¿Cambiar el importe imputado?');
    });
})();
</script>
