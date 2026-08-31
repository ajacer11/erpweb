<?php
require_once 'check_access.php';
?>
<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;">ÓRDENES DE PRODUCCIÓN</h4>
            <small class="text-accent opacity-75">CONTROL DE PROCESOS</small>
        </div>
        <button onclick="loadModule('produccion_new.php')" class="btn btn-info btn-sm px-4 fw-bold rounded-pill">
            <i class="fas fa-plus me-1"></i><span class="d-none d-sm-inline">NUEVA ORDEN</span><span class="d-sm-none">NUEVA</span>
        </button>
    </div>

    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="btn-group w-100 flex-wrap" role="group">
                <input type="radio" class="btn-check" name="filtroEst" id="est1" value="TODOS" checked onchange="listarOrdenes()">
                <label class="btn btn-outline-secondary btn-sm" for="est1">TODAS</label>
                <input type="radio" class="btn-check" name="filtroEst" id="est2" value="PENDIENTE" onchange="listarOrdenes()">
                <label class="btn btn-outline-secondary btn-sm" for="est2">BORRADORES</label>
                <input type="radio" class="btn-check" name="filtroEst" id="est3" value="CONFIRMADA" onchange="listarOrdenes()">
                <label class="btn btn-outline-secondary btn-sm" for="est3">CONFIRMADAS</label>
                <input type="radio" class="btn-check" name="filtroEst" id="est4" value="FINALIZADA" onchange="listarOrdenes()">
                <label class="btn btn-outline-secondary btn-sm" for="est4">FINALIZADAS</label>
                <input type="radio" class="btn-check" name="filtroEst" id="est5" value="CANCELADA" onchange="listarOrdenes()">
                <label class="btn btn-outline-secondary btn-sm" for="est5">CANCELADAS</label>
            </div>
        </div>
    </div>

    <!-- Paginación superior -->
    <div class="erp-pagination" id="pagTopWrap">
        <div class="erp-pag-info" id="pagInfoTop">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1"><button class="erp-pag-btn" onclick="pagJumpTop()">IR</button></div>
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>

    <!-- Tabla desktop -->
    <div class="erp-table-wrap table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr>
                <th class="ps-3">NRO ORDEN</th>
                <th>FECHA PREVISTA</th>
                <th>PRODUCTOS A PRODUCIR</th>
                <th class="text-center">ESTADO</th>
                <th class="text-center">ACCIONES</th>
            </tr></thead>
            <tbody id="listaOrdenesBody">
                <tr><td colspan="5" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Tarjetas mobile -->
    <div class="erp-cards-wrap" id="listaOrdenesCards">
        <div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</div>
    </div>

    <!-- Paginación inferior -->
    <div class="erp-pagination" id="pagBotWrap">
        <div class="erp-pag-info" id="pagInfo">Cargando...</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2"><span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1"><button class="erp-pag-btn" onclick="pagJump()">IR</button></div>
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option>
            </select>
        </div>
    </div>
</div>

<script>
(function () {
    var todos = [], pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    function estadoInfo(st) {
        st = parseInt(st);
        switch(st) {
            case 1: return { texto: 'BORRADOR',    badge: 'bg-warning text-dark' };
            case 2: return { texto: 'CONFIRMADA',  badge: 'bg-primary' };
            case 3: return { texto: 'CONFIRMADA',  badge: 'bg-primary' }; // legado (EN PROCESO, stock ya comprometido)
            case 4: return { texto: 'FINALIZADA',  badge: 'bg-success' };
            case 5: return { texto: 'CANCELADA',   badge: 'bg-danger' };
            default: return { texto: 'DESCONOCIDO', badge: 'bg-dark' };
        }
    }

    // PENDIENTE(1) y CONFIRMADA(2)/EN_PROCESO(3, legado) admiten cancelar.
    // Solo PENDIENTE admite editar/confirmar. Solo CONFIRMADA/EN_PROCESO admite finalizar.
    function accionesPorEstado(st) {
        st = parseInt(st);
        return {
            puedeEditar: st === 1,
            puedeConfirmar: st === 1,
            puedeFinalizar: st === 2 || st === 3,
            puedeCancelar: st === 1 || st === 2 || st === 3,
        };
    }

    window.listarOrdenes = async function () {
        const estado = document.querySelector('input[name="filtroEst"]:checked').value;
        document.getElementById('listaOrdenesBody').innerHTML = '<tr><td colspan="5" class="text-center p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</td></tr>';
        document.getElementById('listaOrdenesCards').innerHTML = '<div class="text-center text-muted p-4"><div class="spinner-border text-info spinner-border-sm me-2"></div>CARGANDO...</div>';
        try {
            const res = await fetch(`api_produccion.php?action=get_ordenes&estado=${estado}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json());
            todos = res.success ? res.data : [];
        } catch (e) { todos = []; }
        pagActual = 1; window.pagActual = 1; renderPagina();
    };

    function renderPagina() {
        const total = todos.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;
        const datos = todos.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        // Tabla
        const tbody = document.getElementById('listaOrdenesBody');
        tbody.innerHTML = '';
        if (!datos.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4 text-muted">No se encontraron órdenes.</td></tr>'; }
        else datos.forEach(o => {
            const st = estadoInfo(o.estado);
            const acc = accionesPorEstado(o.estado);
            const fecha = o.fecha_inicio_prevista ? new Date(o.fecha_inicio_prevista).toLocaleDateString() : '-';

            let botones = `<button class="btn btn-link btn-sm text-info p-0 mx-1" onclick="verDetalle(${o.id})" title="Ver detalle"><i class="fas fa-eye fa-lg"></i></button>`;
            if (acc.puedeEditar) botones += `<button class="btn btn-link btn-sm text-warning p-0 mx-1" onclick="loadModule('produccion_new.php?id=${o.id}')" title="Editar borrador"><i class="fas fa-edit fa-lg"></i></button>`;
            if (acc.puedeConfirmar) botones += `<button class="btn btn-link btn-sm text-primary p-0 mx-1" onclick="confirmarOrden(${o.id})" title="Confirmar"><i class="fas fa-check fa-lg"></i></button>`;
            if (acc.puedeFinalizar) botones += `<button class="btn btn-link btn-sm text-success p-0 mx-1" onclick="finalizarOrden(${o.id})" title="Finalizar"><i class="fas fa-check-circle fa-lg"></i></button>`;
            if (acc.puedeCancelar) botones += `<button class="btn btn-link btn-sm text-danger p-0 mx-1" onclick="cancelarOrden(${o.id})" title="Cancelar"><i class="fas fa-times-circle fa-lg"></i></button>`;

            tbody.innerHTML += `<tr>
                <td class="ps-3 fw-bold text-white">${o.numero_orden || '#'+o.id}</td>
                <td class="text-accent">${fecha}</td>
                <td class="text-white small">${o.productos_a_producir || 'Sin detalle'}</td>
                <td class="text-center"><span class="badge ${st.badge}">${st.texto}</span></td>
                <td class="text-center text-nowrap">${botones}</td>
            </tr>`;
        });

        // Tarjetas
        const wrap = document.getElementById('listaOrdenesCards');
        wrap.innerHTML = '';
        if (!datos.length) { wrap.innerHTML = '<div class="text-center text-muted p-4">No se encontraron órdenes.</div>'; }
        else datos.forEach(o => {
            const st = estadoInfo(o.estado);
            const acc = accionesPorEstado(o.estado);
            const fecha = o.fecha_inicio_prevista ? new Date(o.fecha_inicio_prevista).toLocaleDateString() : '-';

            let botones = `<button class="btn btn-outline-info btn-sm fw-bold" onclick="verDetalle(${o.id})"><i class="fas fa-eye me-1"></i>VER</button>`;
            if (acc.puedeEditar) botones += `<button class="btn btn-outline-warning btn-sm fw-bold" onclick="loadModule('produccion_new.php?id=${o.id}')"><i class="fas fa-edit me-1"></i>EDITAR</button>`;
            if (acc.puedeConfirmar) botones += `<button class="btn btn-primary btn-sm fw-bold" onclick="confirmarOrden(${o.id})"><i class="fas fa-check me-1"></i>CONFIRMAR</button>`;
            if (acc.puedeFinalizar) botones += `<button class="btn btn-success btn-sm fw-bold" onclick="finalizarOrden(${o.id})"><i class="fas fa-check-circle me-1"></i>FINALIZAR</button>`;
            if (acc.puedeCancelar) botones += `<button class="btn btn-outline-danger btn-sm fw-bold" onclick="cancelarOrden(${o.id})"><i class="fas fa-times-circle me-1"></i>CANCELAR</button>`;

            wrap.innerHTML += `<div class="erp-card">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title">${o.numero_orden || '#'+o.id}</div>
                        <span class="badge ${st.badge} mt-1">${st.texto}</span>
                    </div>
                    <span class="erp-card-code">${fecha}</span>
                </div>
                <div class="erp-card-row"><span class="erp-card-label">Productos</span><span class="erp-card-value" style="font-size:11px;max-width:60%;text-align:right;">${o.productos_a_producir || 'Sin detalle'}</span></div>
                <div class="erp-card-actions d-flex flex-wrap gap-1">${botones}</div>
            </div>`;
        });

        sincronizarPaginacion(total);
    }

    window.verDetalle = async function (id) {
        Swal.fire({ title: 'Cargando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff' });
        try {
            const resp = await fetch(`api_produccion.php?action=get_orden_detalle&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (!res.success) { Swal.fire('Error', res.error || 'No se pudo cargar el detalle.', 'error'); return; }

            const d = res.data;
            const st = estadoInfo(d.cabecera.estado);

            const filasEnt = d.entradas.map(e => `<tr>
                <td>${e.codigo} - ${e.descripcion}</td>
                <td class="text-end">${parseFloat(e.cantidad_requerida).toFixed(2)}</td>
                <td class="text-end">${e.cantidad_consumida !== null ? parseFloat(e.cantidad_consumida).toFixed(2) : '-'}</td>
            </tr>`).join('') || '<tr><td colspan="3" class="text-center text-muted">Sin insumos</td></tr>';

            const filasSal = d.salidas.map(s => `<tr>
                <td>${s.codigo} - ${s.descripcion}</td>
                <td class="text-end">${parseFloat(s.cantidad_a_producir).toFixed(2)}</td>
                <td class="text-end">${s.costo_unitario_asignado !== null ? parseFloat(s.costo_unitario_asignado).toFixed(2) : '-'}</td>
            </tr>`).join('') || '<tr><td colspan="3" class="text-center text-muted">Sin salidas</td></tr>';

            Swal.fire({
                title: `Orden ${d.cabecera.numero_orden || '#' + d.cabecera.id}`,
                width: '700px', background: '#001020', color: '#fff',
                showCloseButton: true, showConfirmButton: false,
                html: `<div class="text-start small">
                    <div class="mb-2"><span class="badge ${st.badge}">${st.texto}</span>${d.cabecera.modo_costeo ? ` <span class="badge bg-secondary">COSTEO ${d.cabecera.modo_costeo}</span>` : ''}
                    ${d.cabecera.notas ? `<div class="text-muted mt-1">${d.cabecera.notas.replace(/\n/g, '<br>')}</div>` : ''}</div>
                    <h6 class="text-danger mt-3">Insumos</h6>
                    <table class="table table-sm table-dark"><thead><tr><th>Producto</th><th class="text-end">Requerido</th><th class="text-end">Consumido</th></tr></thead><tbody>${filasEnt}</tbody></table>
                    <h6 class="text-success mt-3">Salidas</h6>
                    <table class="table table-sm table-dark"><thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Costo unit.</th></tr></thead><tbody>${filasSal}</tbody></table>
                </div>`
            });
        } catch (e) { Swal.fire('Error', 'Fallo de conexión.', 'error'); }
    };

    window.confirmarOrden = async function (id) {
        const result = await Swal.fire({
            title: '¿Confirmar orden?',
            text: 'Se validará el stock de los insumos y, si alcanza, se reservará.',
            icon: 'question', showCancelButton: true,
            confirmButtonColor: '#0d6efd', confirmButtonText: 'Sí, confirmar',
            background: '#001020', color: '#fff'
        });
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Confirmando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false });
        try {
            const res = await fetch('api_produccion.php?action=confirmar_orden', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id })
            }).then(r => r.json());
            if (res.success) { Swal.fire({ title: 'Orden confirmada', text: res.message, icon: 'success', background: '#001020', color: '#fff' }); listarOrdenes(); }
            else {
                const detalle = res.faltantes && res.faltantes.length ? '<br><br>' + res.faltantes.join('<br>') : '';
                Swal.fire({ title: 'No se pudo confirmar', html: (res.error || '') + detalle, icon: 'error', background: '#001020', color: '#fff' });
            }
        } catch (e) { Swal.fire('Error', 'Fallo de conexión.', 'error'); }
    };

    window.cancelarOrden = async function (id) {
        const result = await Swal.fire({
            title: '¿Cancelar orden?',
            text: 'Si la orden ya estaba confirmada, el stock reservado se devolverá automáticamente.',
            input: 'text', inputPlaceholder: 'Motivo (opcional)',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#dc3545', confirmButtonText: 'Sí, cancelar', cancelButtonText: 'Volver',
            background: '#001020', color: '#fff'
        });
        if (!result.isConfirmed) return;
        Swal.fire({ title: 'Cancelando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false });
        try {
            const res = await fetch('api_produccion.php?action=cancelar_orden', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id, motivo: result.value || '' })
            }).then(r => r.json());
            if (res.success) { Swal.fire({ title: 'Orden cancelada', text: res.message, icon: 'success', background: '#001020', color: '#fff' }); listarOrdenes(); }
            else Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        } catch (e) { Swal.fire('Error', 'Fallo de conexión.', 'error'); }
    };

    window.finalizarOrden = async function (id) {
        Swal.fire({ title: 'Cargando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff' });
        let d;
        try {
            const resp = await fetch(`api_produccion.php?action=get_orden_detalle&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const res = await resp.json();
            if (!res.success) { Swal.fire('Error', res.error || 'No se pudo cargar la orden.', 'error'); return; }
            d = res.data;
        } catch (e) { Swal.fire('Error', 'Fallo de conexión.', 'error'); return; }

        if (!d.salidas.length) { Swal.fire('Error', 'La orden no tiene productos de salida.', 'error'); return; }
        const multiSalida = d.salidas.length > 1;

        const filasEntradas = d.entradas.map(e => `
            <tr data-requerida="${e.cantidad_requerida}" data-costo="${e.sinivacosto || 0}">
                <td class="small">${e.codigo} - ${e.descripcion}</td>
                <td class="text-end small">${parseFloat(e.cantidad_requerida).toFixed(2)}</td>
                <td><input type="number" class="form-control form-control-sm inp-consumido text-end" data-idprod="${e.id_producto}" value="${parseFloat(e.cantidad_requerida).toFixed(2)}" step="0.001"></td>
                <td class="text-end small merma-cell">0.00</td>
            </tr>`).join('');

        const filasSalidas = d.salidas.map(s => `
            <tr data-precio="${s.precio || 0}" data-cant="${s.cantidad_a_producir}">
                <td class="small">${s.codigo} - ${s.descripcion}</td>
                <td class="text-end small">${parseFloat(s.cantidad_a_producir).toFixed(2)}</td>
                <td class="text-end small costo-auto-cell">-</td>
                <td><input type="number" class="form-control form-control-sm inp-costo-manual text-end d-none" data-idprod="${s.id_producto}" value="0" step="0.01"></td>
            </tr>`).join('');

        const html = `
            <div class="text-start small">
                <h6 class="text-danger">Insumos consumidos</h6>
                <table class="table table-sm table-dark mb-2">
                    <thead><tr><th>Insumo</th><th class="text-end">Requerido</th><th style="width:110px">Consumido real</th><th class="text-end">Merma</th></tr></thead>
                    <tbody id="tbodyFinEnt">${filasEntradas}</tbody>
                </table>
                <div class="text-end text-accent mb-3">Costo total insumos: $<span id="lblCostoTotal">0.00</span></div>
                ${multiSalida ? `
                <h6 class="text-success">Costeo de productos de salida</h6>
                <div class="mb-2">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modoCosteo" id="modoAuto" value="auto" checked>
                        <label class="form-check-label" for="modoAuto">Automático (por valor de venta)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="modoCosteo" id="modoManual" value="manual">
                        <label class="form-check-label" for="modoManual">Manual</label>
                    </div>
                </div>
                <table class="table table-sm table-dark mb-2">
                    <thead><tr><th>Producto</th><th class="text-end">Cantidad</th><th class="text-end">Costo unit. (auto)</th><th style="width:110px">Costo unit. (manual)</th></tr></thead>
                    <tbody id="tbodyFinSal">${filasSalidas}</tbody>
                </table>` : `
                <h6 class="text-success">Producto de salida</h6>
                <div class="mb-2">${d.salidas[0].codigo} - ${d.salidas[0].descripcion} (${parseFloat(d.salidas[0].cantidad_a_producir).toFixed(2)}) — recibe el 100% del costo de insumos.</div>`}
            </div>`;

        const result = await Swal.fire({
            title: `Finalizar Orden ${d.cabecera.numero_orden || '#' + id}`,
            html, width: '900px', background: '#001020', color: '#fff',
            showCancelButton: true, confirmButtonColor: '#28a745', confirmButtonText: 'Finalizar orden', cancelButtonText: 'Volver',
            didOpen: () => {
                function recalcular() {
                    let total = 0;
                    document.querySelectorAll('#tbodyFinEnt tr').forEach(tr => {
                        const req = parseFloat(tr.dataset.requerida);
                        const costo = parseFloat(tr.dataset.costo);
                        const consumido = parseFloat(tr.querySelector('.inp-consumido').value) || 0;
                        const merma = consumido - req;
                        const celda = tr.querySelector('.merma-cell');
                        celda.textContent = merma.toFixed(2);
                        celda.className = 'text-end small merma-cell ' + (merma > 0 ? 'text-danger' : (merma < 0 ? 'text-success' : 'text-muted'));
                        total += consumido * costo;
                    });
                    document.getElementById('lblCostoTotal').textContent = total.toFixed(2);

                    if (multiSalida && document.getElementById('modoAuto').checked) {
                        const filas = document.querySelectorAll('#tbodyFinSal tr');
                        let pesoTotal = 0;
                        filas.forEach(tr => { pesoTotal += parseFloat(tr.dataset.precio) * parseFloat(tr.dataset.cant); });
                        filas.forEach(tr => {
                            const precio = parseFloat(tr.dataset.precio);
                            const cant = parseFloat(tr.dataset.cant);
                            const proporcion = pesoTotal > 0 ? ((precio * cant) / pesoTotal) : (1 / filas.length);
                            const costoUnit = cant > 0 ? (total * proporcion / cant) : 0;
                            tr.querySelector('.costo-auto-cell').textContent = costoUnit.toFixed(2);
                        });
                    }
                }

                document.querySelectorAll('.inp-consumido').forEach(inp => inp.addEventListener('input', recalcular));

                if (multiSalida) {
                    document.querySelectorAll('input[name="modoCosteo"]').forEach(radio => radio.addEventListener('change', () => {
                        const manual = document.getElementById('modoManual').checked;
                        document.querySelectorAll('.inp-costo-manual').forEach(el => el.classList.toggle('d-none', !manual));
                        recalcular();
                    }));
                }

                recalcular();
            },
            preConfirm: () => {
                const consumos = Array.from(document.querySelectorAll('.inp-consumido')).map(inp => ({
                    id_producto: inp.dataset.idprod,
                    cantidad_consumida: parseFloat(inp.value) || 0
                }));

                let modo_costeo = 'auto';
                let costos_manuales = [];
                if (multiSalida) {
                    modo_costeo = document.getElementById('modoManual').checked ? 'manual' : 'auto';
                    if (modo_costeo === 'manual') {
                        costos_manuales = Array.from(document.querySelectorAll('.inp-costo-manual')).map(inp => ({
                            id_producto: inp.dataset.idprod,
                            costo_unitario: parseFloat(inp.value) || 0
                        }));
                    }
                }
                return { consumos, modo_costeo, costos_manuales };
            }
        });

        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Finalizando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false });
        try {
            const res = await fetch('api_produccion.php?action=finalizar_orden', {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id, ...result.value })
            }).then(r => r.json());
            if (res.success) { Swal.fire({ title: '¡Orden Finalizada!', text: res.message, icon: 'success', background: '#001020', color: '#fff' }); listarOrdenes(); }
            else Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
        } catch (e) { Swal.fire('Error', 'Fallo de conexión.', 'error'); }
    };

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> órdenes`;
        const vis = pagTotal > 1 ? 'flex' : 'none';
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ cont.innerHTML=''; if(pagTotal>1){ var ini=Math.max(1,pagActual-2),fin=Math.min(pagTotal,ini+4); if(fin-ini<4)ini=Math.max(1,fin-4); for(var i=ini;i<=fin;i++){var b=document.createElement('button');b.className='erp-pag-btn'+(i===pagActual?' active':'');b.textContent=i;b.onclick=(function(p){return function(){pagGo(p);};})(i);cont.appendChild(b);} } }
            var ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            var sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); if(wrap) wrap.style.display=vis;
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    listarOrdenes();
})();
</script>
