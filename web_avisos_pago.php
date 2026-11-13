<?php
// SEGURIDAD CENTRALIZADA (Check Access) - módulo contratado aparte (addon web_catalogo)
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">PAGOS INFORMADOS</h4>
            <small class="text-accent opacity-75">Transferencias y depósitos que los clientes avisaron desde la web</small>
        </div>
        <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="apEstado" id="apPend" value="pendiente" checked><label class="btn btn-outline-warning" for="apPend">Pendientes <span class="badge bg-warning text-dark" id="apCant"></span></label>
            <input type="radio" class="btn-check" name="apEstado" id="apProc" value="procesado"><label class="btn btn-outline-success" for="apProc">Procesados</label>
            <input type="radio" class="btn-check" name="apEstado" id="apRech" value="rechazado"><label class="btn btn-outline-danger" for="apRech">Rechazados</label>
            <input type="radio" class="btn-check" name="apEstado" id="apTodos" value="todos"><label class="btn btn-outline-light" for="apTodos">Todos</label>
        </div>
    </div>

    <div class="alert alert-info py-2 small">
        <i class="fas fa-circle-info me-2"></i>Un aviso no mueve la cuenta corriente. Verificá que el dinero haya entrado, cargá el cobro en
        <a href="#" onclick="loadModule('cobrar_facturas.php'); return false;">Cobrar Facturas</a> y después marcá el aviso como procesado.
    </div>

    <div class="card card-filtros p-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Informado</th><th>Cliente</th><th>Fecha pago</th><th class="text-end">Importe</th><th>Medio / Referencia</th><th>Observaciones</th><th class="text-end">Saldo cta. cte.</th><th>Estado</th><th></th></tr></thead>
                <tbody id="apTabla"><tr><td colspan="9" class="text-center text-muted">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const API = 'api_web_avisos_pago.php';
    const H = {'X-Requested-With': 'XMLHttpRequest'};
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money = (n) => '$ ' + Number(n || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const fecha = (s, conHora) => s ? new Date(s.length > 10 ? s.replace(' ', 'T') : s + 'T00:00').toLocaleString('es-AR', conHora ? {dateStyle: 'short', timeStyle: 'short'} : {dateStyle: 'short'}) : '-';
    const MEDIOS = {transferencia: 'Transferencia', deposito: 'Depósito', efectivo: 'Efectivo', cheque: 'Cheque', otro: 'Otro'};
    const BADGE = {pendiente: 'bg-warning text-dark', procesado: 'bg-success', rechazado: 'bg-danger'};

    async function cargar() {
        const estado = $('input[name=apEstado]:checked').val();
        const r = await (await fetch(`${API}?action=listar&estado=${estado}`, {headers: H})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        $('#apCant').text(r.pendientes || '');
        $('#apTabla').html(r.data.length ? r.data.map(a => `<tr>
            <td class="small">${fecha(a.fecha, true)}</td>
            <td class="fw-bold">${esc(a.razon)}<div class="small text-muted">${esc(a.numdoc)}</div></td>
            <td>${fecha(a.fecha_pago)}</td>
            <td class="text-end fw-bold">${money(a.importe)}</td>
            <td class="small">${esc(MEDIOS[a.medio] || a.medio)}${a.referencia ? `<div class="text-muted">${esc(a.referencia)}</div>` : ''}</td>
            <td class="small" style="max-width:220px">${esc(a.observaciones)}${a.nota_interna ? `<div class="text-info">Nota: ${esc(a.nota_interna)}</div>` : ''}</td>
            <td class="text-end small">${money(a.saldo)}</td>
            <td><span class="badge ${BADGE[a.estado]}">${esc(a.estado)}</span>${a.usuario_resolucion ? `<div class="small text-muted">${esc(a.usuario_resolucion)}</div>` : ''}</td>
            <td class="text-end text-nowrap">
                ${a.archivo ? `<a class="btn btn-sm btn-outline-info" href="${API}?action=comprobante&id=${a.id}" target="_blank" title="Ver comprobante"><i class="fas fa-paperclip"></i></a>` : ''}
                ${a.estado === 'pendiente' ? `
                    <button class="btn btn-sm btn-success" onclick="apResolver(${a.id}, 'procesado')" title="Marcar procesado"><i class="fas fa-check"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="apResolver(${a.id}, 'rechazado')" title="Rechazar"><i class="fas fa-xmark"></i></button>`
                : `<button class="btn btn-sm btn-outline-light" onclick="apResolver(${a.id}, 'pendiente')" title="Volver a pendiente"><i class="fas fa-rotate-left"></i></button>`}
            </td></tr>`).join('') : '<tr><td colspan="9" class="text-center text-muted py-4">No hay pagos informados en este estado.</td></tr>');
    }
    $('input[name=apEstado]').on('change', cargar);

    window.apResolver = async function(id, estado) {
        let nota = '';
        if (estado !== 'pendiente') {
            const res = await Swal.fire({title: estado === 'procesado' ? 'Marcar como procesado' : 'Rechazar el aviso', input: 'text',
                inputPlaceholder: estado === 'procesado' ? 'Nota (opcional): ej. recibo 0001-00001234' : 'Motivo (opcional)', showCancelButton: true, confirmButtonText: 'Confirmar'});
            if (!res.isConfirmed) return;
            nota = res.value || '';
        }
        const r = await (await fetch(`${API}?action=resolver`, {method: 'POST', headers: {...H, 'Content-Type': 'application/json'}, body: JSON.stringify({id, estado, nota})})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        cargar();
    };

    cargar();
})();
</script>
