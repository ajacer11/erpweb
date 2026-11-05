<?php
/**
 * depositos_admin.php - ABM DE DEPÓSITOS (MULTIDEPÓSITO)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloDepositosAdmin">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-warehouse text-accent me-2"></i>DEPÓSITOS</h4>
            <small class="text-accent opacity-75">Administración de depósitos y stock por ubicación</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm d-none" id="btnNuevoDeposito" onclick="window.abrirModalDeposito()">
                <i class="fas fa-plus me-1"></i> NUEVO DEPÓSITO
            </button>
        </div>
    </div>

    <div class="alert alert-warning d-none" id="bannerActivar">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <strong><i class="fa-solid fa-triangle-exclamation me-2"></i>Multidepósito no está activado</strong>
                <div class="small mt-1">Al activarlo se crea un "Depósito Principal" y se vuelca ahí todo el stock actual. No se pierde ningún dato. Se recomienda hacerlo fuera de horario de atención si el catálogo es grande.</div>
            </div>
            <button class="btn btn-warning fw-bold rounded-pill px-4" onclick="window.activarMultideposito()">
                <i class="fa-solid fa-power-off me-1"></i> ACTIVAR MULTIDEPÓSITO
            </button>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded d-none" id="wrapTablaDepositos" style="background-color: #000a1a; max-height: 65vh;">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="60" class="ps-3">ID</th>
                    <th>NOMBRE</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="text-center">STOCK TOTAL</th>
                    <th class="text-center">PERMITE NEGATIVO</th>
                    <th class="text-center">ESTADO</th>
                    <th width="140" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyDepositos">
                <tr><td colspan="7" class="text-center py-4 text-muted fst-italic">CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalDeposito" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-info shadow-lg">
            <div class="modal-header py-2 border-secondary bg-navbar">
                <h6 class="modal-title text-white" id="tituloModalDeposito">NUEVO DEPÓSITO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-escritorio p-3">
                <input type="hidden" id="depId">
                <div class="mb-3">
                    <label class="form-label-custom mb-1">NOMBRE</label>
                    <input type="text" id="depNombre" class="form-control" maxlength="100" placeholder="Ej: Depósito Sucursal Norte">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-8"><label class="form-label-custom mb-1">DOMICILIO</label><input type="text" id="depDomicilio" class="form-control" maxlength="255"></div>
                    <div class="col-md-4"><label class="form-label-custom mb-1">CÓDIGO POSTAL</label><input type="text" id="depCodigoPostal" class="form-control" maxlength="20"></div>
                    <div class="col-md-6"><label class="form-label-custom mb-1">LOCALIDAD</label><input type="text" id="depLocalidad" class="form-control" maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label-custom mb-1">PROVINCIA</label><input type="text" id="depProvincia" class="form-control" maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label-custom mb-1">TELÉFONO</label><input type="text" id="depTelefono" class="form-control" maxlength="100"></div>
                    <div class="col-md-6"><label class="form-label-custom mb-1">E-MAIL</label><input type="email" id="depEmail" class="form-control" maxlength="150"></div>
                    <div class="col-md-4"><label class="form-label-custom mb-1">PUNTO DE VENTA</label><input type="number" id="depPuntoVenta" class="form-control" min="1" max="99999"></div>
                    <div class="col-md-8"><label class="form-label-custom mb-1">LOGO DE COMPROBANTES</label><input type="file" id="depLogo" class="form-control" accept="image/png,image/jpeg,image/webp"><small id="depLogoEstado" class="text-white-50"></small></div>
                </div>
                <div class="mb-3">
                    <label class="form-label-custom mb-1">DESCRIPCIÓN</label>
                    <textarea id="depDescripcion" class="form-control" rows="2" maxlength="255"></textarea>
                </div>
                <div class="form-check form-switch mb-2">
                    <input class="form-check-input" type="checkbox" id="depPermiteNegativo">
                    <label class="form-check-label text-white-50 small" for="depPermiteNegativo">Permitir stock negativo en este depósito</label>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="depEsTransito">
                    <label class="form-check-label text-white-50 small" for="depEsTransito">
                        Es depósito de tránsito (uso interno: solo se usa como paso intermedio al enviar mercadería con confirmación de recepción; no se puede usar como depósito de trabajo)
                    </label>
                </div>
            </div>
            <div class="modal-footer border-secondary bg-navbar py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" onclick="window.guardarDeposito()">GUARDAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let modalDeposito;

    async function init() {
        modalDeposito = new bootstrap.Modal(document.getElementById('modalDeposito'));

        const resEstado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
        if (!resEstado.habilitado) {
            document.getElementById('bannerActivar').classList.remove('d-none');
            return;
        }

        document.getElementById('btnNuevoDeposito').classList.remove('d-none');
        document.getElementById('wrapTablaDepositos').classList.remove('d-none');
        cargarDepositos();
    }

    async function cargarDepositos() {
        const resp = await fetch('api_depositos.php?action=list', { headers });
        const res = await resp.json();
        const tbody = document.getElementById('tbodyDepositos');
        tbody.innerHTML = '';

        if (!res.success || !res.data.length) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">Sin depósitos.</td></tr>';
            return;
        }

        res.data.forEach(d => {
            const habilitado = parseInt(d.habilitado) === 1;
            const esTransito = parseInt(d.estransito) === 1;
            const tr = document.createElement('tr');
            if (!habilitado) tr.style.opacity = '0.55';
            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${d.id}</td>
                <td class="fw-bold text-white">
                    ${d.nombre}
                    ${parseInt(d.esprincipal) === 1 ? '<span class="badge bg-accent text-dark ms-2" style="font-size:9px">PRINCIPAL</span>' : ''}
                    ${esTransito ? '<span class="badge bg-warning text-dark ms-2" style="font-size:9px">USO INTERNO</span>' : ''}
                </td>
                <td class="small text-white-50">${d.descripcion || '-'}</td>
                <td class="text-center fw-bold">${parseFloat(d.stock_total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                <td class="text-center">${parseInt(d.permitenegativo) === 1 ? '<i class="fas fa-check text-warning"></i>' : '<i class="fas fa-times text-muted"></i>'}</td>
                <td class="text-center">
                    <span class="badge ${habilitado ? 'bg-success' : 'bg-danger'}" style="font-size:9px">${habilitado ? 'HABILITADO' : 'DESHABILITADO'}</span>
                </td>
                <td class="text-center pe-3">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-info border-0" onclick='window.abrirModalDeposito(${JSON.stringify(d)})' title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-outline-${habilitado ? 'danger' : 'success'} border-0" onclick="window.toggleDeposito(${d.id}, ${habilitado ? 0 : 1})" title="${habilitado ? 'Deshabilitar' : 'Habilitar'}">
                            <i class="fas fa-power-off"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    window.abrirModalDeposito = function(d) {
        document.getElementById('tituloModalDeposito').textContent = d ? 'EDITAR DEPÓSITO' : 'NUEVO DEPÓSITO';
        document.getElementById('depId').value = d ? d.id : '';
        document.getElementById('depNombre').value = d ? d.nombre : '';
        document.getElementById('depDescripcion').value = d ? d.descripcion : '';
        document.getElementById('depPermiteNegativo').checked = d ? parseInt(d.permitenegativo) === 1 : false;
        document.getElementById('depEsTransito').checked = d ? parseInt(d.estransito) === 1 : false;
        document.getElementById('depEsTransito').disabled = d ? parseInt(d.esprincipal) === 1 : false;
        ['Domicilio','Localidad','Provincia','Telefono','Email','CodigoPostal','PuntoVenta'].forEach(c => {
            const key = c === 'PuntoVenta' ? 'puntodeventa' : c.toLowerCase();
            document.getElementById('dep' + c).value = d ? (d[key] || '') : '';
        });
        document.getElementById('depLogo').value = '';
        document.getElementById('depLogoEstado').textContent = d && parseInt(d.tiene_logo) === 1 ? 'Logo asignado (elegir otro para reemplazarlo)' : 'Sin logo asignado';
        modalDeposito.show();
    };

    window.guardarDeposito = async function() {
        const id = document.getElementById('depId').value;
        const payload = {
            nombre: document.getElementById('depNombre').value.trim(),
            descripcion: document.getElementById('depDescripcion').value.trim(),
            permitenegativo: document.getElementById('depPermiteNegativo').checked ? 1 : 0,
            estransito: document.getElementById('depEsTransito').checked ? 1 : 0,
            domicilio: document.getElementById('depDomicilio').value.trim(),
            localidad: document.getElementById('depLocalidad').value.trim(),
            provincia: document.getElementById('depProvincia').value.trim(),
            telefono: document.getElementById('depTelefono').value.trim(),
            email: document.getElementById('depEmail').value.trim(),
            codigopostal: document.getElementById('depCodigoPostal').value.trim(),
            puntodeventa: parseInt(document.getElementById('depPuntoVenta').value) || 0,
        };
        if (!payload.nombre) {
            return Swal.fire({ title: 'Atención', text: 'El nombre es obligatorio.', icon: 'warning', background: '#001020', color: '#fff' });
        }
        if (!payload.estransito && payload.puntodeventa < 1) {
            return Swal.fire({ title: 'Atención', text: 'El punto de venta es obligatorio.', icon: 'warning', background: '#001020', color: '#fff' });
        }
        const logoFile = document.getElementById('depLogo').files[0];
        if (logoFile) {
            if (logoFile.size > 2 * 1024 * 1024) return Swal.fire({ title: 'Atención', text: 'El logo no puede superar los 2 MB.', icon: 'warning', background: '#001020', color: '#fff' });
            payload.logo = await new Promise((resolve, reject) => { const fr = new FileReader(); fr.onload = () => resolve(fr.result); fr.onerror = reject; fr.readAsDataURL(logoFile); });
        }
        if (id) payload.id = parseInt(id);

        const resp = await fetch(`api_depositos.php?action=${id ? 'update' : 'create'}`, {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const res = await resp.json();
        if (res.success) {
            modalDeposito.hide();
            Swal.fire({ icon: 'success', title: 'Guardado', timer: 1400, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargarDepositos();
        } else {
            Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    window.toggleDeposito = async function(id, nuevoEstado) {
        const resp = await fetch('api_depositos.php?action=set_status', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, habilitado: nuevoEstado })
        });
        const res = await resp.json();
        if (res.success) {
            cargarDepositos();
        } else {
            Swal.fire({ title: 'No se pudo', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    window.activarMultideposito = async function() {
        const confirm = await Swal.fire({
            title: '¿Activar multidepósito?',
            text: 'Se creará un "Depósito Principal" y se volcará ahí todo el stock actual. Esta acción no se puede desactivar después.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'SÍ, ACTIVAR', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!confirm.isConfirmed) return;

        Swal.fire({ title: 'Activando...', text: 'Puede tardar unos segundos según el tamaño del catálogo.', allowOutsideClick: false, background: '#001020', color: '#fff', didOpen: () => Swal.showLoading() });
        const resp = await fetch('api_depositos.php?action=activar', { method: 'POST', headers });
        const res = await resp.json();
        if (res.success) {
            await Swal.fire({ icon: 'success', title: 'Multidepósito activado', background: '#001020', color: '#fff' });
            location.reload();
        } else {
            Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    init();
})();
</script>
