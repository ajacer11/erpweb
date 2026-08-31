<?php
/**
 * depositos_transferencias_admin.php - HISTORIAL DE TRANSFERENCIAS ENTRE DEPÓSITOS
 */
require_once 'check_access.php';
$isAdmin = isset($_SESSION['es_admin']) && (int) $_SESSION['es_admin'] === 1;
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloTransferenciasAdmin" data-es-admin="<?= $isAdmin ? 1 : 0 ?>">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-clock-rotate-left text-accent me-2"></i>HISTORIAL DE TRANSFERENCIAS</h4>
            <small class="text-accent opacity-75">Últimos movimientos entre depósitos</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" onclick="loadModule('depositos_transferencia.php')">
            <i class="fas fa-plus me-1"></i> NUEVA TRANSFERENCIA
        </button>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 65vh;">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="sticky-top bg-dark">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="60">ID</th>
                    <th>FECHA</th>
                    <th>ORIGEN</th>
                    <th>DESTINO</th>
                    <th>TRANSPORTE</th>
                    <th class="text-center">ÍTEMS</th>
                    <th>USUARIO</th>
                    <th class="text-center">ESTADO</th>
                    <th width="140" class="text-center">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyTransferencias">
                <tr><td colspan="8" class="text-center py-4 text-muted fst-italic">CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    const esAdmin = document.getElementById('moduloTransferenciasAdmin').dataset.esAdmin === '1';

    const BADGES = {
        PENDIENTE_TRANSITO: { clase: 'bg-warning text-dark', texto: 'POR ACEPTAR TRÁNSITO' },
        EN_TRANSITO: { clase: 'bg-warning text-dark', texto: 'EN TRÁNSITO' },
        PARCIAL: { clase: 'bg-info text-dark', texto: 'PARCIAL' },
        COMPLETADA: { clase: 'bg-success', texto: 'COMPLETADA' },
        ANULADA: { clase: 'bg-danger', texto: 'ANULADA' },
    };

    async function cargar() {
        const resp = await fetch('api_depositos.php?action=list_transferencias', { headers });
        const res = await resp.json();
        const tbody = $('#tbodyTransferencias').empty();

        if (!res.success || !res.data.length) {
            tbody.html('<tr><td colspan="9" class="text-center py-4 text-muted">Sin transferencias registradas.</td></tr>');
            return;
        }

        res.data.forEach(t => {
            const anulada = t.estado === 'ANULADA';
            const pendienteTransito = t.estado === 'PENDIENTE_TRANSITO';
            const pendienteEnTransito = t.estado === 'EN_TRANSITO' || t.estado === 'PARCIAL';
            const badge = BADGES[t.estado] || { clase: 'bg-secondary', texto: t.estado };
            const tr = $('<tr>');
            if (anulada) tr.css('opacity', '0.55');

            let acciones = '';
            if (pendienteTransito) {
                acciones += `<button class="btn btn-sm btn-warning text-dark fw-bold me-1" onclick="window.aceptarTransito(${t.id})" title="Aceptar en tránsito"><i class="fas fa-box-open me-1"></i>ACEPTAR</button>`;
            }
            if (pendienteEnTransito) {
                acciones += `<button class="btn btn-sm btn-success fw-bold me-1" onclick="window.recibirTransferencia(${t.id})" title="Recibir todo lo pendiente en destino"><i class="fas fa-truck-ramp-box me-1"></i>RECIBIR</button>`;
                if (esAdmin) {
                    acciones += `
                        <div class="btn-group btn-group-sm me-1">
                            <button class="btn btn-outline-light border-0 dropdown-toggle" data-bs-toggle="dropdown" title="Acciones de administrador"><i class="fas fa-gear"></i></button>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" onclick="window.resolverTransito(${t.id}, 'admin_enviar_destino'); return false;"><i class="fas fa-forward me-2"></i>Enviar todo a destino</a></li>
                                <li><a class="dropdown-item" href="#" onclick="window.resolverTransito(${t.id}, 'admin_devolver_transito'); return false;"><i class="fas fa-rotate-left me-2"></i>Devolver al origen</a></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="window.resolverTransito(${t.id}, 'admin_ajustar_transito'); return false;"><i class="fas fa-triangle-exclamation me-2"></i>Ajustar (baja por pérdida)</a></li>
                            </ul>
                        </div>`;
                }
            }
            if (!anulada && t.estado !== 'PARCIAL') {
                acciones += `<button class="btn btn-sm btn-outline-danger border-0" onclick="window.anularTransferencia(${t.id})" title="Anular"><i class="fas fa-rotate-left"></i></button>`;
            }
            acciones += `<button class="btn btn-sm btn-outline-light border-0" onclick="window.imprimirRemitoTransferencia(${t.id})" title="Imprimir remito"><i class="fas fa-print"></i></button>`;

            tr.html(`
                <td class="text-white-50 small">${t.id}</td>
                <td class="small text-white-50">${new Date(t.fecha).toLocaleString('es-AR')}</td>
                <td class="text-white">${t.deposito_origen || '-'}</td>
                <td class="text-white">${t.deposito_destino || '-'}</td>
                <td class="small text-white-50">${t.transporte || '-'}</td>
                <td class="text-center fw-bold text-accent">${t.cant_items}</td>
                <td class="small text-white-50">${t.usuario}</td>
                <td class="text-center"><span class="badge ${badge.clase}" style="font-size:9px">${badge.texto}</span></td>
                <td class="text-center text-nowrap">${acciones}</td>
            `);
            tbody.append(tr);
        });
    }

    window.aceptarTransito = async function(id) {
        const confirm = await Swal.fire({
            title: '¿Aceptar mercadería en tránsito?',
            text: 'Confirma que la mercadería llegó al depósito de tránsito.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'SÍ, ACEPTAR', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!confirm.isConfirmed) return;

        const resp = await fetch('api_depositos.php?action=aceptar_transito', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Aceptada en tránsito', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargar();
        } else {
            Swal.fire({ title: 'No se pudo aceptar', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    window.recibirTransferencia = async function(id) {
        const confirm = await Swal.fire({
            title: '¿Confirmar recepción total?',
            text: 'Se recibirá en destino todo lo que esté pendiente en tránsito. Para recepción parcial, use la pantalla "Recepciones Pendientes".',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'SÍ, RECIBIR TODO', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!confirm.isConfirmed) return;

        const resp = await fetch('api_depositos.php?action=recibir', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Recepción confirmada', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargar();
        } else {
            Swal.fire({ title: 'No se pudo recibir', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    window.resolverTransito = async function(id, accion) {
        const textos = {
            admin_enviar_destino: { titulo: '¿Enviar todo el remanente a destino?', texto: 'Se aplicará al depósito destino sin esperar más confirmaciones.', boton: 'SÍ, ENVIAR' },
            admin_devolver_transito: { titulo: '¿Devolver el remanente al origen?', texto: 'El stock que quedó en tránsito vuelve al depósito de origen.', boton: 'SÍ, DEVOLVER' },
            admin_ajustar_transito: { titulo: '¿Ajustar el remanente (dar de baja)?', texto: 'El stock que quedó en tránsito se pierde (ajuste por rotura/faltante). Esta acción no se puede deshacer.', boton: 'SÍ, AJUSTAR' },
        }[accion];

        const { value: motivo, isConfirmed } = await Swal.fire({
            title: textos.titulo,
            html: textos.texto,
            input: 'text', inputLabel: 'Motivo (opcional)',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: textos.boton, cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!isConfirmed) return;

        const resp = await fetch(`api_depositos.php?action=${accion}`, {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, motivo: motivo || '' })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Resuelto', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargar();
        } else {
            Swal.fire({ title: 'No se pudo resolver', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    window.imprimirRemitoTransferencia = async function(id) {
        const resp = await fetch(`api_depositos.php?action=imprimir_remito&id=${id}`, { headers });
        const res = await resp.json();
        if (!res.success) {
            return Swal.fire({ title: 'No se pudo generar el remito', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
        const printWindow = window.open('', '_blank');
        if (printWindow) {
            printWindow.document.write(res.html_content);
            printWindow.document.close();
        } else {
            Swal.fire({ title: 'Bloqueo de Pop-up', text: 'Permita las ventanas emergentes para imprimir el remito.', icon: 'warning', background: '#001020', color: '#fff' });
        }
    };

    window.anularTransferencia = async function(id) {
        const { value: motivo, isConfirmed } = await Swal.fire({
            title: '¿Anular transferencia?',
            input: 'text',
            inputLabel: 'Motivo (opcional)',
            text: 'Se revertirá el stock al depósito de origen (esté en tránsito o ya recibido en destino).',
            icon: 'warning', showCancelButton: true,
            confirmButtonText: 'SÍ, ANULAR', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!isConfirmed) return;

        const resp = await fetch('api_depositos.php?action=anular', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, motivo: motivo || '' })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: 'Transferencia anulada', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargar();
        } else {
            Swal.fire({ title: 'No se pudo anular', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    cargar();
})();
</script>
