<?php
/**
 * depositos_recepciones.php - CONFIRMAR RECEPCIÓN DE MERCADERÍA EN TRÁNSITO
 * Pantalla acotada: pensada para asignarse por permisos a un usuario que solo
 * necesita aprobar recepciones (ej. encargado de depósito de tránsito, encargado
 * de depósito destino), sin darle acceso al resto de la administración de
 * depósitos ni a las acciones de administrador (ajustar/devolver/anular).
 *
 * Dos pasos secuenciales por transferencia:
 *  1) El depósito de tránsito acepta la mercadería que le mandó el origen.
 *  2) El depósito destino confirma la recepción, total o parcial. Si es
 *     parcial, lo que falta queda pendiente en tránsito (vuelve a aparecer acá
 *     hasta que se reciba del todo o un administrador resuelva el remanente).
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloRecepciones">
    <div class="mb-3">
        <h4 class="text-white mb-0"><i class="fa-solid fa-truck-ramp-box text-accent me-2"></i>RECEPCIONES PENDIENTES</h4>
        <small class="text-accent opacity-75">Mercadería en tránsito esperando aceptación o confirmación de recepción</small>
    </div>

    <h6 class="text-white-50 mb-2"><i class="fa-solid fa-warehouse me-2"></i>POR ACEPTAR EN TRÁNSITO</h6>
    <div id="listaTransito" class="row g-3 mb-4">
        <div class="col-12 text-center py-4 text-muted fst-italic">CARGANDO...</div>
    </div>

    <h6 class="text-white-50 mb-2"><i class="fa-solid fa-house-flag me-2"></i>POR RECIBIR EN DESTINO</h6>
    <div id="listaDestino" class="row g-3">
        <div class="col-12 text-center py-4 text-muted fst-italic">CARGANDO...</div>
    </div>
</div>

<script>
(function() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };

    function fmt(n) { return parseFloat(n).toFixed(2); }

    async function cargar() {
        cargarTransito();
        cargarDestino();
    }

    // ---------------------------------------------------------------
    // Paso 1: aceptación en el depósito de tránsito
    // ---------------------------------------------------------------
    async function cargarTransito() {
        const resp = await fetch('api_depositos.php?action=list_transferencias&estado=PENDIENTE_TRANSITO', { headers });
        const res = await resp.json();
        const cont = $('#listaTransito').empty();

        if (!res.success || !res.data.length) {
            cont.html('<div class="col-12 text-center py-3 text-muted small">Nada pendiente de aceptar en tránsito.</div>');
            return;
        }

        for (const t of res.data) {
            const detalle = await fetch(`api_depositos.php?action=get_transferencia&id=${t.id}`, { headers }).then(r => r.json());
            const lineas = (detalle.data || []).map(l => `<li>${l.codigo} — <b>${fmt(l.cantidad)}</b></li>`).join('');

            cont.append(`
                <div class="col-md-6 col-lg-4">
                    <div class="card card-filtros h-100 shadow-sm border-warning">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-warning text-dark">PENDIENTE DE ACEPTAR</span>
                                <small class="text-muted">Nº ${t.id}</small>
                            </div>
                            <div class="mb-2">
                                <div class="small text-muted">DESDE</div>
                                <div class="text-white fw-bold">${t.deposito_origen || '-'}</div>
                            </div>
                            <div class="mb-2">
                                <div class="small text-muted">DEPÓSITO DE TRÁNSITO (VOS)</div>
                                <div class="text-accent fw-bold">${t.deposito_transito || '-'}</div>
                            </div>
                            <div class="mb-2">
                                <div class="small text-muted">DESTINO FINAL</div>
                                <div class="text-white-50 small">${t.deposito_destino || '-'}</div>
                            </div>
                            ${t.transporte ? `<div class="mb-2"><div class="small text-muted">TRANSPORTE</div><div class="text-white-50 small">${t.transporte}</div></div>` : ''}
                            <div class="mb-3 flex-grow-1">
                                <div class="small text-muted mb-1">PRODUCTOS (${t.cant_items})</div>
                                <ul class="small text-white-50 mb-0 ps-3">${lineas}</ul>
                            </div>
                            <button class="btn btn-warning text-dark fw-bold rounded-pill" onclick="window.aceptarTransito(${t.id})">
                                <i class="fas fa-box-open me-2"></i>ACEPTAR MERCADERÍA
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    window.aceptarTransito = async function(id) {
        const confirm = await Swal.fire({
            title: '¿Aceptar mercadería en tránsito?',
            text: 'Confirmás que la mercadería llegó físicamente a este depósito de tránsito. Todavía no se aplica al destino final.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'SÍ, LA RECIBÍ', cancelButtonText: 'CANCELAR',
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

    // ---------------------------------------------------------------
    // Paso 2: recepción en destino (total o parcial)
    // ---------------------------------------------------------------
    async function cargarDestino() {
        const resp = await fetch('api_depositos.php?action=list_transferencias&estado=EN_TRANSITO,PARCIAL', { headers });
        const res = await resp.json();
        const cont = $('#listaDestino').empty();

        if (!res.success || !res.data.length) {
            cont.html('<div class="col-12 text-center py-3 text-muted small">Nada pendiente de recibir en destino.</div>');
            return;
        }

        for (const t of res.data) {
            const detalle = await fetch(`api_depositos.php?action=get_transferencia&id=${t.id}`, { headers }).then(r => r.json());
            const lineas = (detalle.data || []).filter(l => (parseFloat(l.cantidad) - parseFloat(l.cantidad_recibida) - parseFloat(l.cantidad_devuelta) - parseFloat(l.cantidad_ajustada)) > 0.004);

            const filasLineas = lineas.map(l => {
                const pendiente = (parseFloat(l.cantidad) - parseFloat(l.cantidad_recibida) - parseFloat(l.cantidad_devuelta) - parseFloat(l.cantidad_ajustada));
                return `
                    <tr>
                        <td class="small text-white-50">${l.codigo}</td>
                        <td class="text-end small text-white-50">${fmt(pendiente)}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm text-end input-recibir"
                                data-id-producto="${l.id_producto}" data-pendiente="${pendiente}"
                                min="0" max="${pendiente}" step="0.01" value="${fmt(pendiente)}">
                        </td>
                    </tr>
                `;
            }).join('');

            const badgeEstado = t.estado === 'PARCIAL' ? '<span class="badge bg-info text-dark">PARCIAL</span>' : '<span class="badge bg-warning text-dark">EN TRÁNSITO</span>';

            cont.append(`
                <div class="col-md-8 col-lg-6">
                    <div class="card card-filtros h-100 shadow-sm border-info">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                ${badgeEstado}
                                <small class="text-muted">Nº ${t.id}</small>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <div class="small text-muted">ORIGEN</div>
                                    <div class="text-white-50 small">${t.deposito_origen || '-'}</div>
                                </div>
                                <div class="col-6">
                                    <div class="small text-muted">DESTINO (VOS)</div>
                                    <div class="text-accent fw-bold">${t.deposito_destino || '-'}</div>
                                </div>
                            </div>
                            <div class="table-responsive mb-3">
                                <table class="table table-dark table-sm mb-0">
                                    <thead>
                                        <tr class="text-accent small">
                                            <th>CÓDIGO</th>
                                            <th class="text-end">PENDIENTE</th>
                                            <th style="width:110px">A RECIBIR</th>
                                        </tr>
                                    </thead>
                                    <tbody data-transferencia="${t.id}">${filasLineas}</tbody>
                                </table>
                            </div>
                            <button class="btn btn-success fw-bold rounded-pill" onclick="window.recibir(${t.id})">
                                <i class="fas fa-check-circle me-2"></i>CONFIRMAR RECEPCIÓN
                            </button>
                        </div>
                    </div>
                </div>
            `);
        }
    }

    window.recibir = async function(id) {
        const inputs = document.querySelectorAll(`tbody[data-transferencia="${id}"] .input-recibir`);
        const lineasPayload = [];
        let esParcial = false;
        inputs.forEach(inp => {
            const pendiente = parseFloat(inp.dataset.pendiente) || 0;
            let cantidad = parseFloat(inp.value) || 0;
            if (cantidad < 0) cantidad = 0;
            if (cantidad > pendiente) cantidad = pendiente;
            if (cantidad < pendiente) esParcial = true;
            lineasPayload.push({ id_producto: parseInt(inp.dataset.idProducto), cantidad });
        });

        if (!lineasPayload.some(l => l.cantidad > 0)) {
            return Swal.fire({ title: 'Atención', text: 'Ingresá al menos una cantidad mayor a cero para recibir.', icon: 'warning', background: '#001020', color: '#fff' });
        }

        const confirm = await Swal.fire({
            title: esParcial ? '¿Confirmar recepción parcial?' : '¿Confirmar recepción total?',
            text: esParcial
                ? 'Lo que no recibas ahora queda pendiente en tránsito.'
                : 'El stock va a pasar a estar disponible en tu depósito.',
            icon: 'question', showCancelButton: true,
            confirmButtonText: 'SÍ, CONFIRMAR', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!confirm.isConfirmed) return;

        const resp = await fetch('api_depositos.php?action=recibir', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' },
            body: JSON.stringify({ id, lineas: lineasPayload })
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire({ icon: 'success', title: res.estado === 'PARCIAL' ? 'Recepción parcial registrada' : 'Recepción confirmada', timer: 1600, showConfirmButton: false, background: '#001020', color: '#fff' });
            cargar();
        } else {
            Swal.fire({ title: 'No se pudo confirmar', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    };

    cargar();
})();
</script>
