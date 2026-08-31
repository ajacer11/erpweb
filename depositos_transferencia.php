<?php
/**
 * depositos_transferencia.php - TRANSFERENCIA DE STOCK ENTRE DEPÓSITOS
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloDepositosTransferencia">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-truck-arrow-right text-accent me-2"></i>TRANSFERENCIA ENTRE DEPÓSITOS</h4>
            <small class="text-accent opacity-75">Mueve stock de un depósito a otro (aplica al instante)</small>
        </div>
        <button class="btn btn-outline-secondary rounded-pill px-3 btn-sm" onclick="loadModule('depositos_transferencias_admin.php')">
            <i class="fas fa-clock-rotate-left me-1"></i> VER HISTORIAL
        </button>
    </div>

    <div class="card card-filtros shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label-custom">DEPÓSITO ORIGEN</label>
                    <select id="cmbOrigen" class="form-select"></select>
                </div>
                <div class="col-md-1 text-center">
                    <i class="fa-solid fa-arrow-right-long text-accent fs-4"></i>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">DEPÓSITO DESTINO</label>
                    <select id="cmbDestino" class="form-select"></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">OBSERVACIÓN</label>
                    <input type="text" id="txtObservacion" class="form-control" placeholder="Motivo de la transferencia...">
                </div>
            </div>
            <div class="row g-3 align-items-end mt-1">
                <div class="col-md-5">
                    <label class="form-label-custom">TRANSPORTE (OPCIONAL)</label>
                    <select id="cmbTransporte" class="form-select">
                        <option value="">SIN ESPECIFICAR</option>
                    </select>
                </div>
                <div class="col-md-7">
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="chkRequiereConfirmacion" checked>
                        <label class="form-check-label text-white-50 small" for="chkRequiereConfirmacion">
                            Requiere confirmación de recepción en destino (queda "En Tránsito" hasta que lo reciban)
                        </label>
                    </div>
                </div>
                <div class="col-md-5" id="wrapDepositoTransito">
                    <label class="form-label-custom">DEPÓSITO DE TRÁNSITO A USAR</label>
                    <select id="cmbDepositoTransito" class="form-select">
                        <option value="">AUTOMÁTICO</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card card-filtros shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label-custom">PRODUCTO</label>
                    <select id="selectProducto" class="form-select" style="width:100%"></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">STOCK EN ORIGEN</label>
                    <input type="text" id="txtStockOrigen" class="form-control text-end" readonly value="-">
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">CANTIDAD</label>
                    <input type="number" id="txtCantidad" class="form-control text-end" step="0.01">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-info w-100 fw-bold" id="btnAgregar" disabled><i class="fas fa-plus me-1"></i> AGREGAR</button>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded mb-3" style="background-color: #000a1a; max-height: 40vh;">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="sticky-top bg-dark">
                <tr class="text-accent border-bottom border-secondary">
                    <th>CÓDIGO</th>
                    <th>PRODUCTO</th>
                    <th class="text-end">CANTIDAD</th>
                    <th width="60" class="text-center">QUITAR</th>
                </tr>
            </thead>
            <tbody id="tbodyItems">
                <tr><td colspan="4" class="text-center py-4 text-muted fst-italic">SIN PRODUCTOS AGREGADOS</td></tr>
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-end">
        <button class="btn btn-success fw-bold rounded-pill px-5 shadow" id="btnConfirmar" disabled>
            <i class="fas fa-check-circle me-2"></i>CONFIRMAR TRANSFERENCIA
        </button>
    </div>
</div>

<script>
(function() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };
    let items = [];
    let productoActivo = null;

    async function init() {
        const estado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
        if (!estado.habilitado) {
            document.getElementById('moduloDepositosTransferencia').innerHTML =
                '<div class="alert alert-warning">El multidepósito no está activado. Activalo desde Depósitos &gt; Administrar Depósitos.</div>';
            return;
        }

        const [origenes, destinos, transportes, transitos] = await Promise.all([
            fetch('api_depositos.php?action=list_permitidos', { headers }).then(r => r.json()),
            fetch('api_depositos.php?action=list_habilitados', { headers }).then(r => r.json()),
            fetch('api_transportes.php?action=list', { headers }).then(r => r.json()).catch(() => []),
            fetch('api_depositos.php?action=list_transito', { headers }).then(r => r.json())
        ]);
        (origenes.data || []).forEach(d => $('#cmbOrigen').append(new Option(d.nombre, d.id)));
        (destinos.data || []).forEach(d => $('#cmbDestino').append(new Option(d.nombre, d.id)));
        (Array.isArray(transportes) ? transportes : []).forEach(t => $('#cmbTransporte').append(new Option(t.razon, t.id)));
        (transitos.data || []).forEach(d => $('#cmbDepositoTransito').append(new Option(d.nombre, d.id)));

        $('#chkRequiereConfirmacion').on('change', function() {
            $('#wrapDepositoTransito').toggleClass('d-none', !this.checked);
        }).trigger('change');

        $('#selectProducto').select2({
            placeholder: 'Buscar producto por código o descripción...',
            minimumInputLength: 2,
            ajax: {
                url: 'api_productos.php?action=search',
                dataType: 'json',
                delay: 250,
                headers,
                data: params => ({ q: params.term }),
                processResults: data => ({
                    results: (data.data || []).map(p => ({ id: p.id, text: `${p.codigo} - ${p.descripcion}`, raw: p }))
                })
            }
        }).on('select2:select', function(e) {
            productoActivo = e.params.data.raw;
            cargarStockOrigen();
        });

        $('#cmbOrigen').on('change', cargarStockOrigen);
        $('#txtCantidad').on('input', validarAgregar);
        $('#btnAgregar').click(agregarItem);
        $('#btnConfirmar').click(confirmarTransferencia);
    }

    async function cargarStockOrigen() {
        $('#txtStockOrigen').val('-');
        if (!productoActivo || !$('#cmbOrigen').val()) return;
        try {
            const resp = await fetch(`api_reporte_stock_por_deposito.php?action=stock_producto&id_producto=${productoActivo.id}&id_deposito=${$('#cmbOrigen').val()}`, { headers }).then(r => r.json());
            $('#txtStockOrigen').val(resp.success ? parseFloat(resp.cantidad).toFixed(2) : '0.00');
        } catch (e) { $('#txtStockOrigen').val('?'); }
        validarAgregar();
    }

    function validarAgregar() {
        const cant = parseFloat($('#txtCantidad').val()) || 0;
        $('#btnAgregar').prop('disabled', !(productoActivo && $('#cmbOrigen').val() && $('#cmbDestino').val() && cant > 0));
    }

    $('#cmbOrigen, #cmbDestino').on('change', function() {
        if ($('#cmbOrigen').val() && $('#cmbOrigen').val() === $('#cmbDestino').val()) {
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'El origen y el destino no pueden ser el mismo depósito.', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false });
        }
        validarAgregar();
    });

    function agregarItem() {
        const cantidad = parseFloat($('#txtCantidad').val()) || 0;
        if (!productoActivo || cantidad <= 0) return;
        if ($('#cmbOrigen').val() === $('#cmbDestino').val()) return;

        items.push({ id_producto: productoActivo.id, codigo: productoActivo.codigo, descripcion: productoActivo.descripcion, cantidad });
        productoActivo = null;
        $('#selectProducto').val(null).trigger('change');
        $('#txtCantidad').val('');
        $('#txtStockOrigen').val('-');
        renderTabla();
    }

    function renderTabla() {
        const tbody = $('#tbodyItems').empty();
        if (!items.length) {
            tbody.html('<tr><td colspan="4" class="text-center py-4 text-muted fst-italic">SIN PRODUCTOS AGREGADOS</td></tr>');
        } else {
            items.forEach((it, idx) => {
                tbody.append(`
                    <tr>
                        <td class="text-white-50 small">${it.codigo}</td>
                        <td class="text-white">${it.descripcion}</td>
                        <td class="text-end fw-bold text-accent">${it.cantidad.toFixed(2)}</td>
                        <td class="text-center"><button class="btn btn-link text-danger p-0" onclick="window.quitarItem(${idx})"><i class="fas fa-trash-alt"></i></button></td>
                    </tr>
                `);
            });
        }
        $('#btnConfirmar').prop('disabled', items.length === 0);
    }

    window.quitarItem = (idx) => { items.splice(idx, 1); renderTabla(); };

    async function confirmarTransferencia() {
        const requiereConfirmacion = $('#chkRequiereConfirmacion').is(':checked');
        const textoAccion = requiereConfirmacion
            ? `Se descontarán <b>${items.length}</b> producto(s) de <b>${$('#cmbOrigen option:selected').text()}</b> y quedarán "En Tránsito" hasta que <b>${$('#cmbDestino option:selected').text()}</b> confirme la recepción.`
            : `Se moverán <b>${items.length}</b> producto(s) de <b>${$('#cmbOrigen option:selected').text()}</b> a <b>${$('#cmbDestino option:selected').text()}</b> de forma inmediata.`;

        const confirm = await Swal.fire({
            title: requiereConfirmacion ? '¿Enviar transferencia?' : '¿Confirmar transferencia directa?',
            html: textoAccion,
            icon: 'question', showCancelButton: true,
            confirmButtonText: requiereConfirmacion ? 'SÍ, ENVIAR' : 'SÍ, TRANSFERIR', cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!confirm.isConfirmed) return;

        const payload = {
            id_deposito_origen: $('#cmbOrigen').val(),
            id_deposito_destino: $('#cmbDestino').val(),
            observacion: $('#txtObservacion').val(),
            id_transporte: $('#cmbTransporte').val() || null,
            requiere_confirmacion: requiereConfirmacion,
            id_deposito_transito: requiereConfirmacion ? ($('#cmbDepositoTransito').val() || null) : null,
            items
        };
        const resp = await fetch('api_depositos.php?action=transferir', {
            method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(payload)
        });
        const res = await resp.json();
        if (res.success) {
            const msg = res.estado === 'PENDIENTE_TRANSITO'
                ? 'Quedó pendiente de que el depósito de tránsito acepte la mercadería. Luego el destino deberá confirmar la recepción (total o parcial).'
                : 'El stock ya se movió al destino.';
            const confirmImprimir = await Swal.fire({
                icon: 'success', title: `Transferencia Nº ${res.id} registrada`, text: msg,
                showCancelButton: true, confirmButtonText: 'IMPRIMIR REMITO', cancelButtonText: 'CERRAR',
                background: '#001020', color: '#fff'
            });
            if (confirmImprimir.isConfirmed) {
                window.imprimirRemitoTransferencia(res.id);
            }
            items = [];
            $('#txtObservacion').val('');
            renderTabla();
        } else {
            Swal.fire({ title: 'No se pudo transferir', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        }
    }

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

    init();
})();
</script>
