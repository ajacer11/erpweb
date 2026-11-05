<?php
/**
 * bancos_conciliacion.php - CONCILIACIÓN BANCARIA Y MAYOR DE CUENTAS BANCARIAS
 * Backend: api_bancos_conciliacion.php
 */
require_once 'check_access.php';
?>
<style>
    #moduloConciliacion .conc-sel { background: rgba(13, 202, 240, .15) !important; }
    #moduloConciliacion tr.conc-ok td { color: #6c757d; }
    #moduloConciliacion .conc-panel { max-height: 58vh; overflow-y: auto; background: #000a1a; }
    #moduloConciliacion .conc-grupo { font-size: 10px; }
</style>

<div class="container-fluid px-4 py-3 fade-in" id="moduloConciliacion">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-building-columns text-accent me-2"></i>CONCILIACIÓN BANCARIA</h4>
            <small class="text-accent opacity-75">Extracto del banco contra los movimientos del sistema</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="form-label-custom mb-0">CUENTA</label>
            <select id="concCuenta" class="form-select form-select-sm" style="min-width:280px"></select>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-3">
        <li class="nav-item"><button class="nav-link active fw-bold" data-conc-tab="ext" type="button"><i class="fas fa-file-import me-1"></i>EXTRACTOS</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-conc-tab="con" type="button" id="concTabConciliar" disabled><i class="fas fa-link me-1"></i>CONCILIAR <span id="concTabNombre"></span></button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-conc-tab="may" type="button"><i class="fas fa-book me-1"></i>MAYOR BANCARIO</button></li>
    </ul>

    <!-- ================= EXTRACTOS ================= -->
    <div data-conc-panel="ext">
        <div class="text-end mb-2">
            <button class="btn btn-success btn-sm fw-bold rounded-pill px-3" onclick="concImportar()"><i class="fas fa-upload me-1"></i>IMPORTAR EXTRACTO</button>
        </div>
        <div class="table-responsive border border-secondary border-opacity-25 rounded" style="background:#000a1a">
            <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap">
                <thead class="bg-black"><tr><th>#</th><th>PERÍODO</th><th>ARCHIVO</th><th class="text-end">SALDO INICIAL</th><th class="text-end">SALDO FINAL</th><th class="text-center">LÍNEAS</th><th class="text-center">PENDIENTES</th><th>ESTADO</th><th></th></tr></thead>
                <tbody id="concExtBody"><tr><td colspan="9" class="text-center text-muted py-4">Elija una cuenta.</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ================= CONCILIAR ================= -->
    <div data-conc-panel="con" class="d-none">
        <div class="row g-2 mb-2" id="concResumen"></div>
        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
            <div class="input-group input-group-sm" style="width:auto">
                <span class="input-group-text bg-dark text-white-50 border-secondary">± días</span>
                <input type="number" id="concDias" class="form-control" value="3" min="0" max="15" style="width:60px">
                <button class="btn btn-info fw-bold" onclick="concAuto()"><i class="fas fa-wand-magic-sparkles me-1"></i>CONCILIAR AUTOMÁTICO</button>
            </div>
            <button class="btn btn-sm btn-outline-info fw-bold" onclick="concConciliarSel()"><i class="fas fa-link me-1"></i>CONCILIAR SELECCIÓN</button>
            <span class="small" id="concSelInfo"></span>
            <div class="form-check form-switch ms-auto mb-0">
                <input class="form-check-input" type="checkbox" id="concSoloPend" checked onchange="concRender()">
                <label class="form-check-label small text-white-50" for="concSoloPend">Solo pendientes</label>
            </div>
            <button class="btn btn-sm btn-outline-warning fw-bold" id="concBtnCerrar" onclick="concCerrar()"></button>
        </div>
        <div class="row g-2">
            <div class="col-12 col-xl-6">
                <div class="small fw-bold text-accent mb-1">EXTRACTO DEL BANCO</div>
                <div class="conc-panel border border-secondary border-opacity-25 rounded">
                    <table class="table table-dark table-hover table-sm align-middle mb-0 small">
                        <thead class="sticky-top bg-black"><tr><th></th><th>FECHA</th><th>DESCRIPCIÓN</th><th class="text-end">IMPORTE</th><th></th></tr></thead>
                        <tbody id="concLineas"></tbody>
                    </table>
                </div>
            </div>
            <div class="col-12 col-xl-6">
                <div class="small fw-bold text-accent mb-1">MOVIMIENTOS DEL SISTEMA</div>
                <div class="conc-panel border border-secondary border-opacity-25 rounded">
                    <table class="table table-dark table-hover table-sm align-middle mb-0 small">
                        <thead class="sticky-top bg-black"><tr><th></th><th>FECHA</th><th>ORIGEN</th><th>DETALLE</th><th class="text-end">IMPORTE</th><th></th></tr></thead>
                        <tbody id="concMovs"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MAYOR ================= -->
    <div data-conc-panel="may" class="d-none">
        <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
            <div><label class="form-label-custom">DESDE</label><input type="date" id="concMayDesde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>"></div>
            <div><label class="form-label-custom">HASTA</label><input type="date" id="concMayHasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
            <button class="btn btn-info btn-sm fw-bold rounded-pill px-3" onclick="concMayor()"><i class="fas fa-search me-1"></i>CONSULTAR</button>
            <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" onclick="concMayorExcel()"><i class="fas fa-file-excel me-1"></i>EXCEL</button>
            <span class="small text-white-50 ms-2">El saldo se calcula sumando los movimientos (no depende del saldo grabado en cada fila).</span>
        </div>
        <div class="table-responsive border border-secondary border-opacity-25 rounded" style="max-height:60vh;background:#000a1a;overflow-y:auto">
            <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap small">
                <thead class="sticky-top bg-black"><tr><th>FECHA</th><th>ORIGEN</th><th>DETALLE</th><th class="text-end">INGRESO</th><th class="text-end">EGRESO</th><th class="text-end">SALDO</th><th>CONCILIADO</th></tr></thead>
                <tbody id="concMayBody"><tr><td colspan="7" class="text-center text-muted py-4">Presione CONSULTAR.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL IMPORTAR -->
<div class="modal fade" id="modConcImportar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-accent fw-bold"><i class="fas fa-upload me-2"></i>IMPORTAR EXTRACTO BANCARIO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-12 col-md-6"><label class="form-label-custom">ARCHIVO (Excel .xlsx / .xls o CSV)</label><input type="file" id="concArchivo" class="form-control form-control-sm" accept=".xlsx,.xls,.csv,.txt"></div>
                    <div class="col-12 col-md-2"><button class="btn btn-outline-info btn-sm w-100" onclick="concLeerArchivo()"><i class="fas fa-eye me-1"></i>LEER</button></div>
                </div>
                <div id="concMapeoBox" class="d-none">
                    <div class="small text-white-50 mb-2">Confirme qué columna es cada dato. Si el banco separa débitos y créditos en dos columnas, deje IMPORTE vacío y complete DÉBITO y CRÉDITO.</div>
                    <div class="row g-2 mb-2">
                        <div class="col-6 col-md-2"><label class="form-label-custom">DATOS DESDE FILA</label><input type="number" min="1" id="concFilaIni" class="form-control form-control-sm"></div>
                        <div class="col-6 col-md-2"><label class="form-label-custom">FECHA</label><select id="concMapFecha" class="form-select form-select-sm conc-map"></select></div>
                        <div class="col-6 col-md-2"><label class="form-label-custom">DESCRIPCIÓN</label><select id="concMapDesc" class="form-select form-select-sm conc-map"></select></div>
                        <div class="col-6 col-md-2"><label class="form-label-custom">REFERENCIA</label><select id="concMapRef" class="form-select form-select-sm conc-map"></select></div>
                        <div class="col-4 col-md-2"><label class="form-label-custom">IMPORTE</label><select id="concMapImp" class="form-select form-select-sm conc-map"></select></div>
                        <div class="col-4 col-md-1"><label class="form-label-custom">DÉBITO</label><select id="concMapDeb" class="form-select form-select-sm conc-map"></select></div>
                        <div class="col-4 col-md-1"><label class="form-label-custom">CRÉDITO</label><select id="concMapCred" class="form-select form-select-sm conc-map"></select></div>
                    </div>
                    <div class="row g-2 mb-2 align-items-end">
                        <div class="col-6 col-md-2"><label class="form-label-custom">SALDO INICIAL</label><input type="text" id="concSaldoIni" class="form-control form-control-sm text-end" value="0"></div>
                        <div class="col-6 col-md-2"><label class="form-label-custom">SALDO FINAL (opcional)</label><input type="text" id="concSaldoFin" class="form-control form-control-sm text-end" placeholder="se calcula"></div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" id="concInvertir"><label class="form-check-label small text-white" for="concInvertir">Invertir signo (el banco muestra los débitos en positivo)</label></div>
                        </div>
                    </div>
                    <div class="border border-secondary rounded" style="max-height:35vh;overflow:auto">
                        <table class="table table-dark table-sm mb-0 small text-nowrap"><tbody id="concPreview"></tbody></table>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-info rounded-pill px-4 fw-bold" id="concBtnCrear" onclick="concCrearExtracto()" disabled><i class="fas fa-check me-2"></i>IMPORTAR</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REGISTRAR -->
<div class="modal fade" id="modConcRegistrar" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-accent fw-bold"><i class="fas fa-pen-to-square me-2"></i>REGISTRAR MOVIMIENTO DEL BANCO</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="small mb-2" id="concRegInfo"></div>
                <label class="form-label-custom">CUENTA CONTABLE DE CONTRAPARTIDA</label>
                <input type="text" id="concRegCuenta" class="form-control form-control-sm mb-2" list="concPlanCuentas" placeholder="Escriba número o nombre (ej. 42009001 GASTOS BANCARIOS)">
                <datalist id="concPlanCuentas"></datalist>
                <label class="form-label-custom">DESCRIPCIÓN</label>
                <input type="text" id="concRegDesc" class="form-control form-control-sm" maxlength="200">
                <div class="small text-white-50 mt-2">Se registra el movimiento en el banco, en tesorería y un asiento contable con la fecha de la línea del extracto, y queda conciliado.</div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-info rounded-pill px-4 fw-bold" onclick="concRegistrarOk()"><i class="fas fa-check me-2"></i>REGISTRAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const API = 'api_bancos_conciliacion.php';
    const H = { 'X-Requested-With': 'XMLHttpRequest' };
    const money = n => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fdate = d => d ? String(d).slice(0, 10).split('-').reverse().join('/') : '';
    const esc = t => $('<div>').text(t ?? '').html();
    const ORIGEN = { tickets: 'Venta', pagos: 'Cobro', pagosprov: 'Pago prov.', pagosvende: 'Pago vend.', chequespropios: 'Cheque propio', depositos: 'Depósito', rechazo_cheque: 'Rechazo cheque',
                     cccuentastesoreria: 'Mov. tesorería', liqtarjeta: 'Liq. tarjeta', conciliacion: 'Conciliación', recibos: 'Recibo', ajuste: 'Ajuste' };
    let cuentas = [], extActual = null, datos = null, archivo = null, plan = null, lineaReg = null, mayorData = null;

    async function get(action, qs = '') {
        const res = await fetch(`${API}?action=${action}${qs}`, { headers: H }).then(r => r.json());
        if (!res.success) throw new Error(res.error || 'Error');
        return res;
    }
    async function post(action, body) {
        const res = await fetch(`${API}?action=${action}`, { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' }, body: JSON.stringify(body) }).then(r => r.json());
        if (!res.success) throw new Error(res.error || 'Error');
        return res;
    }
    const fallo = e => Swal.fire('Error', e.message, 'error');
    const cuentaSel = () => cuentas.find(c => String(c.cuenta) === $('#concCuenta').val());

    function tab(t) {
        $('[data-conc-tab]').removeClass('active'); $(`[data-conc-tab="${t}"]`).addClass('active');
        $('[data-conc-panel]').addClass('d-none'); $(`[data-conc-panel="${t}"]`).removeClass('d-none');
    }
    $('[data-conc-tab]').on('click', function () { tab($(this).data('conc-tab')); });

    async function init() {
        try {
            cuentas = (await get('cuentas')).data;
            $('#concCuenta').html(cuentas.map(c => `<option value="${c.cuenta}">${esc(c.medio)}${c.numcuenta ? ' - ' + esc(c.numcuenta) : ''} (saldo $ ${money(c.saldo)})</option>`).join(''));
            if (!cuentas.length) $('#concCuenta').html('<option value="">No hay medios de tipo Bancos con cuenta bancaria</option>');
            listarExtractos();
        } catch (e) { fallo(e); }
    }
    $('#concCuenta').on('change', () => { extActual = null; $('#concTabConciliar').prop('disabled', true); $('#concTabNombre').text(''); tab('ext'); listarExtractos(); });

    async function listarExtractos() {
        if (!$('#concCuenta').val()) return;
        try {
            const res = await get('extractos', '&cuenta=' + $('#concCuenta').val());
            const tb = $('#concExtBody').empty();
            if (!res.data.length) return tb.html('<tr><td colspan="9" class="text-center text-muted py-4">No hay extractos importados para esta cuenta.</td></tr>');
            res.data.forEach(e => tb.append(`<tr>
                <td>${e.id}</td><td>${fdate(e.desde)} al ${fdate(e.hasta)}</td><td class="small text-white-50">${esc(e.archivo)}</td>
                <td class="text-end">$ ${money(e.saldo_inicial)}</td><td class="text-end">$ ${money(e.saldo_final)}</td>
                <td class="text-center">${e.lineas}</td><td class="text-center ${+e.pendientes ? 'text-warning fw-bold' : 'text-success'}">${e.pendientes}</td>
                <td>${+e.estado === 1 ? '<span class="badge bg-success">CERRADO</span>' : '<span class="badge bg-warning text-dark">ABIERTO</span>'}</td>
                <td><button class="btn btn-sm btn-info py-0" onclick="concAbrir(${e.id})"><i class="fas fa-link"></i> ABRIR</button>
                    ${+e.estado === 1 ? '' : `<button class="btn btn-sm btn-link text-danger p-0 ms-2" title="Eliminar" onclick="concEliminar(${e.id})"><i class="fas fa-trash"></i></button>`}</td></tr>`));
        } catch (e) { fallo(e); }
    }

    // ---------------- importar
    window.concImportar = function () {
        if (!cuentaSel()) return Swal.fire('Atención', 'Elija una cuenta.', 'warning');
        archivo = null; $('#concArchivo').val(''); $('#concMapeoBox').addClass('d-none'); $('#concBtnCrear').prop('disabled', true);
        $('#concSaldoIni').val('0'); $('#concSaldoFin').val(''); $('#concInvertir').prop('checked', false);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modConcImportar')).show();
    };
    window.concLeerArchivo = async function () {
        const f = document.getElementById('concArchivo').files[0];
        if (!f) return Swal.fire('Atención', 'Elija el archivo.', 'warning');
        const fd = new FormData(); fd.append('archivo', f);
        try {
            const res = await fetch(`${API}?action=leer_archivo`, { method: 'POST', headers: H, body: fd }).then(r => r.json());
            if (!res.success) throw new Error(res.error);
            archivo = res;
            const nCols = Math.max(...res.filas.slice(0, 50).map(f => f.length));
            const enc = res.mapeo.fila_encabezado >= 0 ? res.filas[res.mapeo.fila_encabezado] : [];
            const opts = '<option value="">(ninguna)</option>' + Array.from({ length: nCols }, (_, i) => `<option value="${i}">${String.fromCharCode(65 + (i % 26))}${enc[i] ? ' - ' + esc(String(enc[i]).slice(0, 25)) : ''}</option>`).join('');
            $('.conc-map').html(opts);
            const m = res.mapeo;
            $('#concMapFecha').val(m.fecha ?? ''); $('#concMapDesc').val(m.descripcion ?? ''); $('#concMapRef').val(m.referencia ?? '');
            $('#concMapImp').val(m.importe ?? ''); $('#concMapDeb').val(m.debito ?? ''); $('#concMapCred').val(m.credito ?? '');
            $('#concFilaIni').val((m.fila_encabezado ?? -1) + 2);
            $('#concPreview').html(res.filas.slice(0, 15).map((f, i) => `<tr><td class="text-white-50">${i + 1}</td>${f.map(c => `<td>${esc(String(c).slice(0, 40))}</td>`).join('')}</tr>`).join(''));
            $('#concMapeoBox').removeClass('d-none'); $('#concBtnCrear').prop('disabled', false);
        } catch (e) { fallo(e); }
    };
    window.concCrearExtracto = async function () {
        const c = cuentaSel();
        const payload = {
            cuenta: c.cuenta, idmediobanco: c.idmedio, archivo: archivo.archivo, filas: archivo.filas,
            fila_inicio: Math.max(0, (parseInt($('#concFilaIni').val()) || 1) - 1), invertir_signo: $('#concInvertir').is(':checked'),
            saldo_inicial: $('#concSaldoIni').val(), saldo_final: $('#concSaldoFin').val(),
            mapeo: { fecha: $('#concMapFecha').val(), descripcion: $('#concMapDesc').val(), referencia: $('#concMapRef').val(), importe: $('#concMapImp').val(), debito: $('#concMapDeb').val(), credito: $('#concMapCred').val() }
        };
        try {
            const res = await post('extracto_crear', payload);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modConcImportar')).hide();
            let txt = `${res.lineas} línea(s) importadas.`;
            if (res.descartadas) txt += ` ${res.descartadas} fila(s) sin fecha o importe válido se descartaron (encabezados, totales, etc.).`;
            if (Math.abs(res.diferencia_saldos) > 0.01) txt += ` Atención: saldo inicial + movimientos no da el saldo final indicado (diferencia $ ${money(res.diferencia_saldos)}); revise el archivo o los saldos.`;
            await Swal.fire('Extracto importado', txt, Math.abs(res.diferencia_saldos) > 0.01 ? 'warning' : 'success');
            listarExtractos(); concAbrir(res.id);
        } catch (e) { fallo(e); }
    };
    window.concEliminar = async function (id) {
        if (!(await Swal.fire({ title: `¿Eliminar el extracto #${id}?`, text: 'Se borran sus líneas y las conciliaciones hechas con él. Los movimientos del sistema no se tocan.', icon: 'warning', showCancelButton: true, confirmButtonText: 'ELIMINAR', cancelButtonText: 'CANCELAR' })).isConfirmed) return;
        try { await post('extracto_eliminar', { id }); listarExtractos(); } catch (e) { fallo(e); }
    };

    // ---------------- conciliar
    window.concAbrir = async function (id) {
        try {
            datos = await get('extracto_get', '&id=' + id);
            extActual = id;
            $('#concTabConciliar').prop('disabled', false); $('#concTabNombre').text(`#${id}`);
            tab('con'); concRender();
        } catch (e) { fallo(e); }
    };
    const recargar = () => concAbrir(extActual);

    window.concRender = function () {
        const e = datos.extracto, r = datos.resumen, solo = $('#concSoloPend').is(':checked'), cerrado = +e.estado === 1;
        const card = (t, v, cls = '') => `<div class="col-6 col-lg"><div class="p-2 rounded border border-secondary"><div class="small text-white-50">${t}</div><div class="fw-bold ${cls}">$ ${money(v)}</div></div></div>`;
        $('#concResumen').html(
            card('SALDO SEGÚN EXTRACTO al ' + fdate(e.hasta), r.saldo_extracto) +
            card('SALDO SEGÚN SISTEMA al ' + fdate(e.hasta), r.saldo_sistema) +
            card('DIFERENCIA', r.diferencia, Math.abs(r.diferencia) < 0.01 ? 'text-success' : 'text-warning') +
            card('MOV. DEL SISTEMA SIN CONCILIAR (período)', r.mov_sistema_sin_conciliar) +
            card('LÍNEAS DEL EXTRACTO PENDIENTES', r.lineas_extracto_pendientes) +
            (r.lineas_extracto_ignoradas ? card('LÍNEAS IGNORADAS', r.lineas_extracto_ignoradas, 'text-white-50') : '') +
            (r.previos_sin_conciliar_cant ? `<div class="col-12 small text-warning">Hay ${r.previos_sin_conciliar_cant} movimiento(s) del sistema anteriores a este extracto sin conciliar ($ ${money(r.previos_sin_conciliar_importe)}): parte de la diferencia puede venir de ahí.</div>` : ''));
        $('#concBtnCerrar').html(cerrado ? '<i class="fas fa-lock-open me-1"></i>REABRIR' : '<i class="fas fa-lock me-1"></i>CERRAR EXTRACTO');

        const tl = $('#concLineas').empty();
        datos.lineas.filter(l => !solo || +l.estado === 0).forEach(l => {
            const est = +l.estado;
            let acc = '';
            if (!cerrado) {
                if (est === 0) acc = `<button class="btn btn-link btn-sm p-0 text-info" title="Registrar en el sistema (comisión, impuesto, etc.)" onclick="concRegistrar(${l.id})"><i class="fas fa-pen-to-square"></i></button>
                                     <button class="btn btn-link btn-sm p-0 text-secondary ms-1" title="Ignorar" onclick="concIgnorar(${l.id})"><i class="fas fa-eye-slash"></i></button>`;
                else if (est === 1) acc = `<button class="btn btn-link btn-sm p-0 text-warning" title="Desconciliar grupo ${l.grupo}" onclick="concDesconciliar(${l.grupo})"><i class="fas fa-link-slash"></i></button>`;
                else if (est === 3) acc = `<button class="btn btn-link btn-sm p-0 text-info" title="Volver a pendiente" onclick="concIgnorar(${l.id}, true)"><i class="fas fa-rotate-left"></i></button>`;
            }
            tl.append(`<tr class="${est ? 'conc-ok' : ''}" data-l="${l.id}">
                <td>${est === 0 && !cerrado ? `<input type="checkbox" class="form-check-input conc-chk-l" value="${l.id}" data-imp="${l.importe}">` : (est === 1 ? `<span class="badge bg-success conc-grupo">G${l.grupo}</span>` : '<span class="badge bg-secondary conc-grupo" title="' + esc(l.nota) + '">IGN</span>')}</td>
                <td>${fdate(l.fecha)}</td><td class="text-truncate" style="max-width:260px" title="${esc(l.descripcion)} ${esc(l.referencia)}">${esc(l.descripcion)}${l.referencia ? ` <span class="text-white-50">${esc(l.referencia)}</span>` : ''}</td>
                <td class="text-end ${+l.importe < 0 ? 'text-danger' : 'text-success'}">$ ${money(l.importe)}</td><td class="text-nowrap">${acc}</td></tr>`);
        });
        if (!tl.children().length) tl.html('<tr><td colspan="5" class="text-center text-muted py-3">Sin líneas pendientes.</td></tr>');

        const tm = $('#concMovs').empty();
        datos.movimientos.filter(m => !solo || !m.conc_extracto).forEach(m => {
            const conc = !!m.conc_extracto, fuera = m.fecha < e.desde || m.fecha > e.hasta;
            tm.append(`<tr class="${conc ? 'conc-ok' : ''}">
                <td>${!conc && !cerrado ? `<input type="checkbox" class="form-check-input conc-chk-m" value="${m.id}" data-imp="${m.importe}">` : (conc ? `<span class="badge bg-success conc-grupo">G${m.grupo}</span>` : '')}</td>
                <td class="${fuera ? 'text-white-50' : ''}" title="${fuera ? 'Fuera del período del extracto' : ''}">${fdate(m.fecha)}</td>
                <td>${esc(ORIGEN[m.tabla] || m.tabla)} <span class="text-white-50">#${m.compasoc}</span></td>
                <td class="text-truncate" style="max-width:220px" title="${esc(m.detalle)} ${esc(m.observacion)}">${esc(m.detalle)}</td>
                <td class="text-end ${+m.importe < 0 ? 'text-danger' : 'text-success'}">$ ${money(m.importe)}</td>
                <td>${conc && !cerrado ? `<button class="btn btn-link btn-sm p-0 text-warning" title="Desconciliar grupo ${m.grupo}" onclick="concDesconciliar(${m.grupo})"><i class="fas fa-link-slash"></i></button>` : ''}</td></tr>`);
        });
        if (!tm.children().length) tm.html('<tr><td colspan="6" class="text-center text-muted py-3">Sin movimientos pendientes.</td></tr>');
        actualizarSel();
    };

    function actualizarSel() {
        const s = sel => $(sel + ':checked').map((_, el) => +$(el).data('imp')).get().reduce((a, b) => a + b, 0);
        const sl = Math.round(s('.conc-chk-l') * 100) / 100, sm = Math.round(s('.conc-chk-m') * 100) / 100;
        const n = $('.conc-chk-l:checked').length + $('.conc-chk-m:checked').length;
        $('#concSelInfo').html(n ? `Extracto <strong>$ ${money(sl)}</strong> · Sistema <strong>$ ${money(sm)}</strong> · ${Math.abs(sl - sm) < 0.01 ? '<span class="text-success">coinciden</span>' : `<span class="text-danger">diferencia $ ${money(sl - sm)}</span>`}` : '');
        $('.conc-chk-l, .conc-chk-m').each(function () { $(this).closest('tr').toggleClass('conc-sel', this.checked); });
    }
    $(document).off('.conc').on('change.conc', '.conc-chk-l, .conc-chk-m', actualizarSel);

    window.concAuto = async function () {
        try {
            const res = await post('auto', { id: extActual, dias: parseInt($('#concDias').val()) || 0 });
            Swal.fire('Conciliación automática', `${res.uno_a_uno} coincidencia(s) uno a uno y ${res.agrupados} agrupada(s).`, 'success');
            recargar();
        } catch (e) { fallo(e); }
    };
    window.concConciliarSel = async function () {
        const lineas = $('.conc-chk-l:checked').map((_, el) => +el.value).get(), movimientos = $('.conc-chk-m:checked').map((_, el) => +el.value).get();
        if (!lineas.length || !movimientos.length) return Swal.fire('Atención', 'Tilde al menos una línea del extracto y un movimiento del sistema que sumen lo mismo.', 'info');
        try { await post('conciliar', { id: extActual, lineas, movimientos }); recargar(); } catch (e) { fallo(e); }
    };
    window.concDesconciliar = async function (grupo) {
        try { await post('desconciliar', { id: extActual, grupo }); recargar(); } catch (e) { fallo(e); }
    };
    window.concIgnorar = async function (linea, restaurar = false) {
        let nota = '';
        if (!restaurar) {
            const r = await Swal.fire({ title: 'Ignorar línea', input: 'text', inputPlaceholder: 'Motivo (ej. movimiento informativo)', showCancelButton: true, confirmButtonText: 'IGNORAR', cancelButtonText: 'CANCELAR' });
            if (!r.isConfirmed) return;
            nota = r.value || '';
        }
        try { await post('ignorar', { id: extActual, linea, nota, restaurar }); recargar(); } catch (e) { fallo(e); }
    };
    window.concRegistrar = async function (id) {
        lineaReg = datos.lineas.find(l => +l.id === id);
        if (!plan) {
            try { plan = (await get('plan_cuentas')).data; } catch (e) { return fallo(e); }
            $('#concPlanCuentas').html(plan.map(p => `<option value="${esc(p.numcuenta)} ${esc(p.descripcion)}">`).join(''));
        }
        $('#concRegInfo').html(`${fdate(lineaReg.fecha)} · ${esc(lineaReg.descripcion)} · <strong class="${+lineaReg.importe < 0 ? 'text-danger' : 'text-success'}">$ ${money(lineaReg.importe)}</strong>`);
        $('#concRegCuenta').val(''); $('#concRegDesc').val(lineaReg.descripcion);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modConcRegistrar')).show();
    };
    window.concRegistrarOk = async function () {
        const cuenta = ($('#concRegCuenta').val().trim().split(' ')[0] || '');
        if (!cuenta) return Swal.fire('Atención', 'Elija la cuenta contable.', 'warning');
        try {
            await post('registrar', { id: extActual, linea: lineaReg.id, cuenta_contable: cuenta, descripcion: $('#concRegDesc').val().trim() });
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modConcRegistrar')).hide();
            recargar();
        } catch (e) { fallo(e); }
    };
    window.concCerrar = async function () {
        const reabrir = +datos.extracto.estado === 1;
        try { await post('cerrar', { id: extActual, reabrir }); recargar(); listarExtractos(); } catch (e) { fallo(e); }
    };

    // ---------------- mayor
    window.concMayor = async function () {
        if (!cuentaSel()) return;
        try {
            mayorData = await post('mayor', { cuenta: cuentaSel().cuenta, desde: $('#concMayDesde').val(), hasta: $('#concMayHasta').val() });
            const tb = $('#concMayBody').empty();
            const filas = [`<tr class="fw-bold"><td colspan="5">SALDO ANTERIOR</td><td class="text-end">$ ${money(mayorData.saldo_anterior)}</td><td></td></tr>`];
            mayorData.data.slice(-5000).forEach(m => filas.push(`<tr><td>${fdate(m.fecha)}</td><td>${esc(ORIGEN[m.tabla] || m.tabla)} <span class="text-white-50">#${m.compasoc}</span></td>
                <td class="text-truncate" style="max-width:320px">${esc(m.detalle)} ${esc(m.observacion)}</td>
                <td class="text-end text-success">${+m.debe ? '$ ' + money(m.debe) : ''}</td><td class="text-end text-danger">${+m.haber ? '$ ' + money(m.haber) : ''}</td>
                <td class="text-end">$ ${money(m.saldo)}</td><td>${m.idextracto ? `<span class="badge bg-success">EXT #${m.idextracto}</span>` : ''}</td></tr>`));
            if (mayorData.data.length > 5000) filas.splice(1, 0, `<tr><td colspan="7" class="text-warning small">Se muestran los últimos 5000 de ${mayorData.data.length} movimientos; el Excel incluye todos.</td></tr>`);
            filas.push(`<tr class="fw-bold"><td colspan="5">SALDO FINAL</td><td class="text-end">$ ${money(mayorData.saldo_final)}</td><td></td></tr>`);
            tb.html(filas.join(''));
        } catch (e) { fallo(e); }
    };
    window.concMayorExcel = function () {
        if (!mayorData) return Swal.fire('Atención', 'Consulte el mayor primero.', 'info');
        let html = '<html><head><meta charset="UTF-8"></head><body><table border="1"><tr><th>FECHA</th><th>ORIGEN</th><th>COMPROBANTE</th><th>DETALLE</th><th>INGRESO</th><th>EGRESO</th><th>SALDO</th><th>CONCILIADO</th></tr>';
        html += `<tr><td colspan="6">SALDO ANTERIOR</td><td>${(+mayorData.saldo_anterior).toFixed(2)}</td><td></td></tr>`;
        mayorData.data.forEach(m => { html += `<tr><td>${fdate(m.fecha)}</td><td>${esc(ORIGEN[m.tabla] || m.tabla)}</td><td>${m.compasoc}</td><td>${esc(m.detalle)} ${esc(m.observacion)}</td><td>${(+m.debe).toFixed(2)}</td><td>${(+m.haber).toFixed(2)}</td><td>${(+m.saldo).toFixed(2)}</td><td>${m.idextracto ? 'Extracto #' + m.idextracto : ''}</td></tr>`; });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + html + '</table></body></html>'], { type: 'application/vnd.ms-excel' }));
        a.download = `Mayor_bancario_${$('#concMayDesde').val()}_${$('#concMayHasta').val()}.xls`;
        document.body.appendChild(a); a.click(); a.remove();
    };

    init();
})();
</script>
