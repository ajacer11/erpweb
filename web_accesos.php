<?php
// SEGURIDAD CENTRALIZADA (Check Access) - módulo contratado aparte (addon web_catalogo)
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">ACCESOS DE CLIENTES</h4>
            <small class="text-accent opacity-75">Quién puede entrar al área de clientes de la web</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4" onclick="accNuevo()"><i class="fas fa-user-plus me-2"></i>DAR ACCESO A UN CLIENTE</button>
    </div>

    <div id="accSolicitudesBox" class="card card-filtros p-3 mb-3 shadow-sm d-none" style="border-left: 4px solid #f59e0b;">
        <h6 class="text-warning mb-2"><i class="fas fa-bell me-2"></i>Solicitudes de registro pendientes</h6>
        <div class="table-responsive">
            <table class="table table-dark table-sm align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Empresa / Nombre</th><th>CUIT</th><th>Contacto</th><th>Localidad</th><th>Mensaje</th><th></th></tr></thead>
                <tbody id="accSolicitudes"></tbody>
            </table>
        </div>
    </div>

    <div class="card card-filtros p-3 shadow-sm">
        <div class="d-flex flex-wrap gap-2 mb-2">
            <input type="search" class="form-control" id="accFiltro" placeholder="Buscar cliente, usuario o mail..." style="max-width: 360px;">
        </div>
        <div class="table-responsive">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead><tr><th>Cliente</th><th>Usuario</th><th>Mail</th><th class="text-center">Lista</th><th>Estado</th><th>Último ingreso</th><th class="text-center">Pedidos web</th><th></th></tr></thead>
                <tbody id="accTabla"><tr><td colspan="8" class="text-center text-muted">Cargando...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: nuevo acceso -->
<div class="modal fade" id="accModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white">
            <div class="modal-header border-secondary"><h5 class="modal-title">Dar acceso a un cliente</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="hidden" id="accSolicitudId">
                <div id="accSolicitudInfo" class="alert alert-secondary small d-none"></div>
                <label class="form-label-custom">CLIENTE</label>
                <select id="accCliente" style="width:100%"></select>
                <div class="small text-muted mt-1 mb-3">¿No está cargado? Primero dalo de alta en <a href="#" onclick="bootstrap.Modal.getInstance(document.getElementById('accModal')).hide(); loadModule('clientes_alta.php'); return false;">Clientes &gt; Nuevo cliente</a>.</div>
                <label class="form-label-custom">USUARIO</label>
                <input class="form-control mb-1" id="accUsuario" maxlength="50" placeholder="Ej: el CUIT del cliente">
                <div class="small text-muted mb-3">También va a poder entrar con su mail.</div>
                <label class="form-label-custom">MAIL</label>
                <input type="email" class="form-control mb-3" id="accEmail">
                <label class="form-label-custom">CÓMO CREA SU CONTRASEÑA</label>
                <div class="form-check d-none" id="accModoSolBox"><input class="form-check-input" type="radio" name="accModo" id="accModoSol" value="solicitud"><label class="form-check-label" for="accModoSol">Ya la eligió al registrarse: queda activo al aprobar <span class="badge bg-success">recomendado</span></label></div>
                <div class="form-check"><input class="form-check-input" type="radio" name="accModo" id="accModoInv" value="invitacion" checked><label class="form-check-label" for="accModoInv">Le mandamos una invitación por mail y la crea él <span class="badge bg-info">recomendado</span></label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="radio" name="accModo" id="accModoMan" value="manual"><label class="form-check-label" for="accModoMan">Le asigno una contraseña ahora y se la paso yo</label></div>
                <div class="input-group d-none" id="accClaveBox">
                    <input type="text" class="form-control font-monospace" id="accClave" placeholder="Mínimo 8 caracteres">
                    <button class="btn btn-outline-info" type="button" onclick="$('#accClave').val(accGenerarClave())" title="Generar"><i class="fas fa-dice"></i></button>
                </div>
            </div>
            <div class="modal-footer border-secondary">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info fw-bold" onclick="accCrear()"><i class="fas fa-check me-1"></i>Dar acceso</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const API = 'api_web_accesos.php';
    const H = {'X-Requested-With': 'XMLHttpRequest'};
    const HJ = {...H, 'Content-Type': 'application/json'};
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fecha = (s) => s ? new Date(s.replace(' ', 'T')).toLocaleString('es-AR', {dateStyle: 'short', timeStyle: 'short'}) : '-';
    let accesos = [], solicitudes = [];
    const modal = new bootstrap.Modal(document.getElementById('accModal'));

    async function post(action, body) {
        const r = await (await fetch(`${API}?action=${action}`, {method: 'POST', headers: HJ, body: JSON.stringify(body)})).json();
        if (!r.success) throw new Error(r.error);
        return r;
    }

    async function cargar() {
        const r = await (await fetch(`${API}?action=listar`, {headers: H})).json();
        if (!r.success) return Swal.fire('Error', r.error, 'error');
        accesos = r.data;
        solicitudes = r.solicitudes;
        renderAccesos();
        $('#accSolicitudesBox').toggleClass('d-none', !solicitudes.length);
        $('#accSolicitudes').html(solicitudes.map(s => `<tr>
            <td class="small">${fecha(s.fecha)}</td>
            <td class="fw-bold">${esc(s.razon)}${s.contacto ? `<div class="small text-muted">${esc(s.contacto)}</div>` : ''}</td>
            <td>${esc(s.cuit)}</td>
            <td class="small">${esc(s.email)}<br>${esc(s.telefono)}${s.con_clave == 1 ? '<div><span class="badge bg-success">Ya eligió su contraseña</span></div>' : ''}</td>
            <td class="small">${esc(s.localidad)}</td>
            <td class="small" style="max-width:240px">${esc(s.mensaje)}</td>
            <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-success" onclick="accAprobar(${s.id})"><i class="fas fa-check me-1"></i>Aprobar</button>
                <button class="btn btn-sm btn-outline-danger" onclick="accRechazar(${s.id})"><i class="fas fa-xmark"></i></button>
            </td></tr>`).join(''));
    }

    function estado(a) {
        if (a.activo != 1) return '<span class="badge bg-secondary">Desactivado</span>';
        if (a.bloqueado == 1) return '<span class="badge bg-danger">Bloqueado (intentos fallidos)</span>';
        if (a.sin_clave == 1) return a.invitacion_vigente == 1 ? '<span class="badge bg-warning text-dark">Invitación enviada</span>' : '<span class="badge bg-warning text-dark">Sin contraseña (invitación vencida)</span>';
        return '<span class="badge bg-success">Activo</span>';
    }

    function renderAccesos() {
        const f = $('#accFiltro').val().toLowerCase();
        const lista = accesos.filter(a => !f || [a.razon, a.nombre_usuario, a.email, a.numdoc].some(x => String(x ?? '').toLowerCase().includes(f)));
        $('#accTabla').html(lista.length ? lista.map(a => `<tr>
            <td class="fw-bold">${esc(a.razon || '(cliente inexistente)')}<div class="small text-muted">${esc(a.numdoc)}</div></td>
            <td class="font-monospace">${esc(a.nombre_usuario)}</td>
            <td class="small">${esc(a.email)}</td>
            <td class="text-center">${esc(a.lista)}</td>
            <td>${estado(a)}</td>
            <td class="small">${fecha(a.ultimo_login)}</td>
            <td class="text-center">${a.pedidos}</td>
            <td class="text-end text-nowrap">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">Acciones</button>
                    <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                        <li><a class="dropdown-item" href="#" onclick="accInvitar(${a.id}); return false;"><i class="fas fa-envelope me-2"></i>${a.sin_clave == 1 ? 'Reenviar invitación' : 'Mandar link para cambiar la contraseña'}</a></li>
                        <li><a class="dropdown-item" href="#" onclick="accClaveManual(${a.id}); return false;"><i class="fas fa-key me-2"></i>Asignar contraseña</a></li>
                        <li><a class="dropdown-item" href="#" onclick="accEditarMail(${a.id}); return false;"><i class="fas fa-at me-2"></i>Cambiar mail</a></li>
                        ${a.bloqueado == 1 ? `<li><a class="dropdown-item" href="#" onclick="accEstado(${a.id}, true, true); return false;"><i class="fas fa-unlock me-2"></i>Desbloquear</a></li>` : ''}
                        <li><a class="dropdown-item" href="#" onclick="accEstado(${a.id}, ${a.activo != 1}); return false;"><i class="fas fa-power-off me-2"></i>${a.activo == 1 ? 'Desactivar' : 'Activar'}</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="#" onclick="accEliminar(${a.id}); return false;"><i class="fas fa-trash me-2"></i>Eliminar acceso</a></li>
                    </ul>
                </div>
            </td></tr>`).join('') : '<tr><td colspan="8" class="text-center text-muted py-4">Ningún cliente tiene acceso todavía.</td></tr>');
    }
    $('#accFiltro').on('input', renderAccesos);

    $('#accCliente').select2({
        dropdownParent: $('#accModal'), width: '100%', placeholder: 'Buscar cliente por nombre o CUIT...', minimumInputLength: 1,
        ajax: { url: `${API}?action=buscar_clientes`, headers: H, delay: 250, data: p => ({q: p.term || ''}), processResults: d => d }
    }).on('select2:select', function(e) {
        const d = e.params.data;
        if (!$('#accUsuario').val()) $('#accUsuario').val((d.numdoc || '').replace(/\D/g, '') || '');
        if (!$('#accEmail').val() && d.mail) $('#accEmail').val(String(d.mail).split(/[;,\s]/)[0]);
    });
    $('input[name=accModo]').on('change', () => $('#accClaveBox').toggleClass('d-none', $('#accModoMan:not(:checked)').length > 0));

    window.accGenerarClave = () => {
        const c = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        return Array.from(crypto.getRandomValues(new Uint32Array(10)), n => c[n % c.length]).join('');
    };

    function abrirModal(sol) {
        $('#accSolicitudId').val(sol ? sol.id : '');
        $('#accSolicitudInfo').toggleClass('d-none', !sol).html(sol ? `Solicitud de <b>${esc(sol.razon)}</b> ${sol.cuit ? '(' + esc(sol.cuit) + ')' : ''}. Elegí a qué cliente del sistema corresponde.` : '');
        $('#accCliente').val(null).trigger('change');
        $('#accUsuario').val(sol ? (sol.cuit || '').replace(/\D/g, '') : '');
        $('#accEmail').val(sol ? sol.email : '');
        const conClave = !!sol && sol.con_clave == 1;
        $('#accModoSolBox').toggleClass('d-none', !conClave);
        $(conClave ? '#accModoSol' : '#accModoInv').prop('checked', true).trigger('change');
        $('#accModoInv + label .badge').toggleClass('d-none', conClave);
        $('#accClave').val('');
        modal.show();
    }
    window.accNuevo = () => abrirModal(null);
    window.accAprobar = (id) => abrirModal(solicitudes.find(s => s.id == id));

    window.accCrear = async function() {
        try {
            const r = await post('crear', {
                cliente_id: $('#accCliente').val(), usuario: $('#accUsuario').val(), email: $('#accEmail').val(),
                modo: $('input[name=accModo]:checked').val(), clave: $('#accClave').val(), solicitud_id: $('#accSolicitudId').val() || null,
            });
            modal.hide();
            const manual = $('#accModoMan').is(':checked');
            Swal.fire({icon: 'success', title: 'Listo', html: esc(r.message) + (manual ? `<br><br>Usuario: <b>${esc($('#accUsuario').val())}</b><br>Contraseña: <b class="font-monospace">${esc($('#accClave').val())}</b>` : '')});
            cargar();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    window.accInvitar = async (id) => {
        try { const r = await post('invitar', {id}); Swal.fire('Listo', r.message, 'success'); cargar(); }
        catch (e) { Swal.fire('Error', e.message, 'error'); }
    };
    window.accClaveManual = async (id) => {
        const {value} = await Swal.fire({title: 'Nueva contraseña', input: 'text', inputValue: accGenerarClave(), showCancelButton: true, confirmButtonText: 'Guardar',
            inputValidator: v => v.length < 8 ? 'Mínimo 8 caracteres' : null});
        if (!value) return;
        try { const r = await post('cambiar_clave', {id, clave: value}); Swal.fire('Listo', `${r.message}<br>Nueva contraseña: <b class="font-monospace">${esc(value)}</b>`, 'success'); cargar(); }
        catch (e) { Swal.fire('Error', e.message, 'error'); }
    };
    window.accEditarMail = async (id) => {
        const a = accesos.find(x => x.id == id);
        const {value, isConfirmed} = await Swal.fire({title: 'Mail del acceso', input: 'email', inputValue: a.email || '', showCancelButton: true, confirmButtonText: 'Guardar'});
        if (!isConfirmed) return;
        try { await post('editar', {id, email: value, activo: a.activo == 1}); cargar(); }
        catch (e) { Swal.fire('Error', e.message, 'error'); }
    };
    window.accEstado = async (id, activo, desbloquear = false) => {
        const a = accesos.find(x => x.id == id);
        try { await post('editar', {id, email: a.email || '', activo, desbloquear}); cargar(); }
        catch (e) { Swal.fire('Error', e.message, 'error'); }
    };
    window.accEliminar = async (id) => {
        const a = accesos.find(x => x.id == id);
        const c = await Swal.fire({icon: 'warning', title: `¿Eliminar el acceso de ${a.razon || a.nombre_usuario}?`, text: 'No borra al cliente ni sus pedidos: solo deja de poder entrar a la web.', showCancelButton: true, confirmButtonText: 'Eliminar', confirmButtonColor: '#dc3545'});
        if (!c.isConfirmed) return;
        try { await post('eliminar', {id}); cargar(); } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };
    window.accRechazar = async (id) => {
        const c = await Swal.fire({icon: 'question', title: '¿Rechazar la solicitud?', showCancelButton: true, confirmButtonText: 'Rechazar'});
        if (!c.isConfirmed) return;
        try { await post('rechazar_solicitud', {id}); cargar(); } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    cargar();
})();
</script>
