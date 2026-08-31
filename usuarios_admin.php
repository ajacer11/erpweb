<?php
/**
 * usuarios_admin.php - ADMINISTRACIÓN DE USUARIOS (CORREGIDO)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloUsuariosAdmin">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-users-gear text-accent me-2"></i>ADMINISTRACIÓN DE USUARIOS</h4>
            <small class="text-accent opacity-75">CONTROL DE ACCESOS Y SEGURIDAD DE CUENTAS</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" onclick="loadModule('usuarios_new.php')">
            <i class="fas fa-plus me-1"></i> NUEVO USUARIO
        </button>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-2">
            <form onsubmit="return false;">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-dark border-secondary text-accent"><i class="fas fa-search"></i></span>
                    <input type="text" id="txtBuscarUsuario" class="form-control bg-dark text-white border-secondary shadow-none" placeholder="FILTRAR POR NOMBRE O ROL..." autocomplete="off">
                </div>
            </form>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; background: #000a1a; overflow-y: auto;">
        <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.9rem;">
            <thead class="sticky-top" style="background-color: #0d2b45; z-index: 5;">
                <tr>
                    <th class="text-white ps-3" style="width: 80px;">ID</th>
                    <th class="text-white">NOMBRE DE USUARIO</th>
                    <th class="text-center text-white" style="width: 180px;">ESTADO / ROL</th>
                    <th class="text-center text-white" style="width: 200px;">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyUsuarios">
                <tr><td colspan="4" class="text-center py-5 text-muted fst-italic">CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

    <div class="erp-pagination mb-2" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1">
                <button class="erp-pag-btn" onclick="pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
        </div>
    </div>
</div>

<div class="modal fade" id="modalChangePass" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info shadow-lg">
            <div class="modal-header py-2 border-secondary bg-navbar">
                <h6 class="modal-title text-white">RESETEAR CONTRASEÑA</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-escritorio p-3">
                <input type="hidden" id="modalUserId">
                <label class="form-label-custom mb-1">NUEVA CONTRASEÑA</label>
                <div class="input-group">
                    <input type="password" id="txtNewPass" class="form-control border-secondary bg-dark text-white shadow-none" placeholder="MÍNIMO 8 CARACTERES...">
                    <button class="btn btn-outline-secondary border-secondary" type="button" onclick="window.togglePassVisibility()">
                        <i class="fas fa-eye" id="iconPass"></i>
                    </button>
                </div>
                <div class="form-text text-info small mt-2">
                    <i class="fas fa-shield-halved me-1"></i> Debe contener: 8 caracteres, mayúsculas, números y símbolos.
                </div>
            </div>
            <div class="modal-footer border-secondary bg-navbar py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" onclick="window.guardarNuevaPass()">
                    ACTUALIZAR LLAVE
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDepositosUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info shadow-lg">
            <div class="modal-header py-2 border-secondary bg-navbar">
                <h6 class="modal-title text-white">DEPÓSITOS PERMITIDOS: <span id="lblUsuarioDepositos" class="text-accent"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-escritorio p-3">
                <input type="hidden" id="depUsuarioObjetivo">
                <label class="form-label-custom mb-2">Sin ninguno tildado, el usuario puede usar cualquier depósito habilitado.</label>
                <div id="listaCheckDepositos" class="mb-3"></div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="chkPuedeCambiarDeposito">
                    <label class="form-check-label text-white-50 small" for="chkPuedeCambiarDeposito">Puede cambiar su depósito de trabajo en cualquier momento</label>
                </div>
            </div>
            <div class="modal-footer border-secondary bg-navbar py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" onclick="window.guardarDepositosUsuario()">GUARDAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let allUsers = [];
    let filteredUsers = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;
    let todosLosDepositos = [];
    let modalDepositosUsuario;
    let multidepositoHabilitado = false;

    async function init() {
        await cargarUsuarios();
        await inicializarMultideposito();

        $('#txtBuscarUsuario').on('input', function() {
            const busqueda = $(this).val().toUpperCase();
            filteredUsers = allUsers.filter(u => {
                const nombre = (u.usuario || '').toUpperCase();
                const rol = (parseInt(u.esadmin) === 1 ? 'ADMINISTRADOR' : 'USUARIO GENERAL');
                return nombre.includes(busqueda) || rol.includes(busqueda);
            });
            pagActual = 1; window.pagActual = 1;
            renderPagina();
        });
    }

    async function inicializarMultideposito() {
        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            const estado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
            if (!estado.habilitado) return;

            const dep = await fetch('api_depositos.php?action=list_habilitados', { headers }).then(r => r.json());
            todosLosDepositos = dep.data || [];
            modalDepositosUsuario = new bootstrap.Modal(document.getElementById('modalDepositosUsuario'));
            multidepositoHabilitado = true;
            document.querySelectorAll('.btn-depositos').forEach(b => b.classList.remove('d-none'));
            renderPagina(); // re-renderizar para que el botón quede visible también en cambios de página posteriores
        } catch (e) { console.error('No se pudo inicializar multidepósito', e); }
    }

    window.abrirModalDepositos = async function(usuario) {
        const headers = { 'X-Requested-With': 'XMLHttpRequest' };
        document.getElementById('depUsuarioObjetivo').value = usuario;
        document.getElementById('lblUsuarioDepositos').textContent = usuario.toUpperCase();

        const lista = $('#listaCheckDepositos').empty();
        todosLosDepositos.forEach(d => {
            lista.append(`
                <div class="form-check">
                    <input class="form-check-input chk-dep-item" type="checkbox" value="${d.id}" id="depItem${d.id}">
                    <label class="form-check-label text-white-50 small" for="depItem${d.id}">${d.nombre}</label>
                </div>
            `);
        });

        try {
            const res = await fetch(`api_depositos.php?action=get_asignacion_usuario&usuario=${encodeURIComponent(usuario)}`, { headers }).then(r => r.json());
            if (res.success) {
                (res.depositos_asignados || []).forEach(id => $(`#depItem${id}`).prop('checked', true));
                $('#chkPuedeCambiarDeposito').prop('checked', !!res.puede_cambiar);
            }
        } catch (e) { console.error(e); }

        modalDepositosUsuario.show();
    };

    window.guardarDepositosUsuario = async function() {
        const usuario = document.getElementById('depUsuarioObjetivo').value;
        const depositos = document.querySelectorAll('.chk-dep-item:checked');
        const payload = {
            usuario,
            depositos: Array.from(depositos).map(el => parseInt(el.value)),
            puede_cambiar: document.getElementById('chkPuedeCambiarDeposito').checked ? 1 : 0
        };

        const resp = await fetch('api_depositos.php?action=set_asignacion_usuario', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const res = await resp.json();
        if (res.success) {
            modalDepositosUsuario.hide();
            Swal.fire({ icon: 'success', title: 'Guardado', timer: 1400, showConfirmButton: false, background: '#001020', color: '#fff' });
        } else {
            Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    async function cargarUsuarios() {
        try {
            const resp = await fetch('api_usuarios.php?action=list', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (res.success) {
                allUsers = res.data || [];
                filteredUsers = [...allUsers];
                renderPagina();
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la lista de usuarios', 'error');
        }
    }

    function renderPagina() {
        const total = filteredUsers.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = filteredUsers.slice((pagActual - 1) * pagTam, pagActual * pagTam);
        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyUsuarios');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center py-5 text-muted">SIN RESULTADOS</td></tr>';
            return;
        }

        data.forEach(u => {
            const rolNom = parseInt(u.esadmin) === 1 ? 'ADMINISTRADOR' : 'USUARIO';
            const rolCls = parseInt(u.esadmin) === 1 ? 'text-accent border-accent' : 'text-info border-info';
            const habilitado = parseInt(u.habilitado) === 1;
            const cajaAbierta = !!u.caja_control_id;

            const tr = document.createElement('tr');
            if(!habilitado) tr.style.opacity = '0.5';

            tr.innerHTML = `
                <td class="fw-bold ps-3 text-white-50">${u.id}</td>
                <td class="text-white fw-bold">
                    <i class="fas fa-circle me-2" style="font-size: 8px; color: ${habilitado ? '#28a745' : '#dc3545'}"></i>
                    ${u.usuario.toUpperCase()}
                    ${cajaAbierta ? `<span class="badge bg-success text-dark ms-2" style="font-size: 9px;"><i class="fas fa-cash-register me-1"></i>${(u.caja_nombre || '').toUpperCase()}</span>` : ''}
                </td>
                <td class="text-center">
                    <span class="badge bg-transparent border ${rolCls} px-3 py-1 rounded-pill" style="font-size: 10px;">${rolNom}</span>
                </td>
                <td class="text-center pe-3">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info" onclick="window.abrirModalPass(${u.id})" title="Resetear Contraseña"><i class="fas fa-key"></i></button>
                        <button class="btn btn-outline-warning btn-depositos ${multidepositoHabilitado ? '' : 'd-none'}" onclick="window.abrirModalDepositos('${u.usuario}')" title="Depósitos permitidos"><i class="fas fa-warehouse"></i></button>
                        ${cajaAbierta ? `<button class="btn btn-outline-success" onclick="window.cerrarCajaUsuario(${u.caja_control_id}, '${u.usuario}', '${(u.caja_nombre || '').replace(/'/g, "\\'")}')" title="Cerrar su caja abierta"><i class="fas fa-cash-register"></i></button>` : ''}
                        <button class="btn btn-outline-secondary" onclick="window.forzarLogout(${u.id}, '${u.usuario}')" title="Forzar cierre de sesión"><i class="fas fa-right-from-bracket"></i></button>
                        <button class="btn btn-outline-danger" onclick="window.eliminarUsuario(${u.id}, '${u.usuario}')" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(u => {
            const rolNom = parseInt(u.esadmin) === 1 ? 'ADMINISTRADOR' : 'USUARIO GENERAL';
            const habilitado = parseInt(u.habilitado) === 1;
            const cajaAbierta = !!u.caja_control_id;

            wrap.innerHTML += `
            <div class="erp-card mb-2" style="${!habilitado ? 'border-left: 4px solid #dc3545; opacity: 0.8;' : 'border-left: 4px solid #28a745;'}">
                <div class="erp-card-header">
                    <div class="erp-card-title text-white">${u.usuario.toUpperCase()}</div>
                    <span class="erp-card-code text-info">#${u.id}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Perfil / Rol</span>
                    <span class="erp-card-value ${parseInt(u.esadmin) === 1 ? 'text-accent' : 'text-info'} fw-bold">${rolNom}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Estado</span>
                    <span class="erp-card-value ${habilitado ? 'text-success' : 'text-danger'} fw-bold">${habilitado ? 'ACTIVO' : 'INHABILITADO'}</span>
                </div>
                ${cajaAbierta ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">Caja</span>
                    <span class="erp-card-value text-success fw-bold"><i class="fas fa-cash-register me-1"></i>${(u.caja_nombre || '').toUpperCase()}</span>
                </div>` : ''}
                <div class="mt-3 pt-2 border-top border-secondary border-opacity-25 d-flex justify-content-end gap-2 flex-wrap">
                    <button class="btn btn-sm btn-dark text-info border-secondary px-3" onclick="window.abrirModalPass(${u.id})"><i class="fas fa-key me-1"></i> PASSWORD</button>
                    <button class="btn btn-sm btn-dark text-warning border-secondary btn-depositos ${multidepositoHabilitado ? '' : 'd-none'}" onclick="window.abrirModalDepositos('${u.usuario}')"><i class="fas fa-warehouse"></i></button>
                    ${cajaAbierta ? `<button class="btn btn-sm btn-dark text-success border-secondary" onclick="window.cerrarCajaUsuario(${u.caja_control_id}, '${u.usuario}', '${(u.caja_nombre || '').replace(/'/g, "\\'")}')"><i class="fas fa-cash-register me-1"></i> CERRAR CAJA</button>` : ''}
                    <button class="btn btn-sm btn-dark text-secondary border-secondary" onclick="window.forzarLogout(${u.id}, '${u.usuario}')"><i class="fas fa-right-from-bracket me-1"></i> DESLOGUEAR</button>
                    <button class="btn btn-sm btn-dark text-danger border-secondary" onclick="window.eliminarUsuario(${u.id}, '${u.usuario}')"><i class="fas fa-trash-alt"></i></button>
                </div>
            </div>`;
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||0), hasta = Math.min(pagActual*pagTam, total);
        const info  = `<strong>${desde}–${hasta}</strong> de <strong>${total}</strong>`;

        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){
                cont.innerHTML='';
                if(pagTotal>1){
                    var ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4);
                    if(fin-ini<4) ini=Math.max(1,fin-4);
                    for(var i=ini; i<=fin; i++){
                        var b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = (function(p){return function(){pagGo(p);};})(i);
                        cont.appendChild(b);
                    }
                }
            }
            var ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            var sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('moduloUsuariosAdmin')?.scrollIntoView({behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    window.abrirModalPass = function(id) {
        $('#modalUserId').val(id);
        $('#txtNewPass').val('').attr('type', 'password');
        $('#iconPass').attr('class', 'fas fa-eye');
        $('#modalChangePass').modal('show');
    };

    window.togglePassVisibility = function() {
        const input = document.getElementById('txtNewPass');
        const icon = document.getElementById('iconPass');
        if (input.type === 'password') {
            input.type = 'text';
            icon.className = 'fas fa-eye-slash';
        } else {
            input.type = 'password';
            icon.className = 'fas fa-eye';
        }
    };

    window.guardarNuevaPass = async function() {
        const id = $('#modalUserId').val();
        const pass = $('#txtNewPass').val();
        const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&._#\-\(\)\[\]\{\}])[A-Za-z\d@$!%*?&._#\-\(\)\[\]\{\}]{8,}$/;
        
        if (!regex.test(pass)) {
            return Swal.fire('Seguridad', 'La contraseña debe tener al menos 8 caracteres, incluyendo mayúsculas, minúsculas, números y un símbolo.', 'warning');
        }

        try {
            Swal.fire({ title: 'Procesando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            const resp = await fetch('api_usuarios.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id, passweb: pass })
            });
            const res = await resp.json();
            if (res.success) {
                $('#modalChangePass').modal('hide');
                Swal.fire({ icon: 'success', title: 'Contraseña actualizada', timer: 2000, showConfirmButton: false });
            } else throw new Error(res.error);
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    window.forzarLogout = function(id, name) {
        Swal.fire({
            title: '¿FORZAR CIERRE DE SESIÓN?',
            text: `La sesión activa de [${name}] se cerrará automáticamente en su próxima acción dentro del sistema.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            confirmButtonText: 'SÍ, FORZAR',
            cancelButtonText: 'CANCELAR',
            background: '#001020',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const resp = await fetch('api_usuarios.php?action=force_logout', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ id })
                    });
                    const res = await resp.json();
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Sesión marcada para cierre', timer: 1800, showConfirmButton: false, background: '#001020', color: '#fff' });
                    } else throw new Error(res.error);
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        });
    };

    window.cerrarCajaUsuario = function(idControl, usuario, cajaNombre) {
        Swal.fire({
            title: '¿CERRAR CAJA?',
            text: `Se cerrará la caja "${cajaNombre}" abierta por [${usuario}], registrando el saldo actual de tesorería como saldo final.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'SÍ, CERRAR CAJA',
            cancelButtonText: 'CANCELAR',
            background: '#001020',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const formData = new FormData();
                    formData.append('id_control', idControl);
                    const resp = await fetch('api_cajas.php?action=admin_close_box', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                        body: formData
                    });
                    const res = await resp.json();
                    if (res.success) {
                        Swal.fire({ icon: 'success', title: 'Caja cerrada', text: res.message, timer: 2200, showConfirmButton: false, background: '#001020', color: '#fff' });
                        cargarUsuarios();
                    } else throw new Error(res.error);
                } catch (e) {
                    Swal.fire('Error', e.message, 'error');
                }
            }
        });
    };

    window.eliminarUsuario = function(id, name) {
        Swal.fire({
            title: '¿ELIMINAR USUARIO?',
            text: `Se borrará definitivamente la cuenta de [${name}]. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'SÍ, ELIMINAR',
            cancelButtonText: 'CANCELAR',
            background: '#001020',
            color: '#fff'
        }).then(async (result) => {
            if (result.isConfirmed) {
                try {
                    const resp = await fetch(`api_usuarios.php?action=delete&id=${id}`, { 
                        method: 'DELETE', 
                        headers: { 'X-Requested-With': 'XMLHttpRequest' } 
                    });
                    const res = await resp.json();
                    if (res.success) {
                        Swal.fire('Borrado', 'Usuario eliminado correctamente.', 'success');
                        cargarUsuarios();
                    } else throw new Error(res.error);
                } catch (e) { Swal.fire('Error', e.message, 'error'); }
            }
        });
    };

    init();
})();
</script>