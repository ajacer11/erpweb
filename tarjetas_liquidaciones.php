<?php
/**
 * tarjetas_liquidaciones.php - TARJETAS: LIQUIDACIONES, CUPONES Y CATÁLOGO
 * Backend: api_tarjetas.php. Los cupones los generan la venta, el cobro de facturas y la
 * modificación de medios de cobro (tarjetas_helpers.php).
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloTarjetas">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-credit-card text-accent me-2"></i>TARJETAS Y CUPONES</h4>
            <small class="text-accent opacity-75">Cupones cobrados con tarjeta, liquidaciones de la procesadora y acreditación en el banco</small>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-3">
        <li class="nav-item"><button class="nav-link active fw-bold" data-tj-tab="liq" type="button"><i class="fas fa-file-invoice-dollar me-1"></i>LIQUIDACIONES</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-tj-tab="cup" type="button"><i class="fas fa-receipt me-1"></i>CUPONES</button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-tj-tab="cat" type="button"><i class="fas fa-list me-1"></i>TARJETAS</button></li>
    </ul>

    <!-- ===================== LIQUIDACIONES ===================== -->
    <div data-tj-panel="liq">
        <div class="card card-filtros mb-3 border-0"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2"><label class="form-label-custom">DESDE</label><input type="date" id="tjLiqDesde" class="form-control form-control-sm" value="<?= date('Y-m-01', strtotime('-2 months')) ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">HASTA</label><input type="date" id="tjLiqHasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-12 col-md-8 text-md-end">
                    <button class="btn btn-info btn-sm fw-bold rounded-pill px-3" onclick="tjListarLiq()"><i class="fas fa-search me-1"></i>CONSULTAR</button>
                    <button class="btn btn-success btn-sm fw-bold rounded-pill px-3" onclick="tjNuevaLiq()"><i class="fas fa-plus me-1"></i>NUEVA LIQUIDACIÓN</button>
                </div>
            </div>
        </div></div>
        <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded" style="max-height:60vh;background:#000a1a;overflow-y:auto">
            <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap">
                <thead class="sticky-top bg-black"><tr>
                    <th>#</th><th>ACREDITACIÓN</th><th>Nº LIQ.</th><th>MEDIO / TARJETA</th><th>BANCO</th><th class="text-end">CUPONES</th>
                    <th class="text-end">BRUTO</th><th class="text-end">DEDUCCIONES</th><th class="text-end">NETO</th><th>ASIENTO</th><th>ESTADO</th><th></th>
                </tr></thead>
                <tbody id="tjLiqBody"><tr><td colspan="12" class="text-center text-muted py-4">Presione CONSULTAR.</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ===================== CUPONES ===================== -->
    <div data-tj-panel="cup" class="d-none">
        <div class="card card-filtros mb-3 border-0"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2"><label class="form-label-custom">DESDE</label><input type="date" id="tjCupDesde" class="form-control form-control-sm" value="<?= date('Y-m-01') ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">HASTA</label><input type="date" id="tjCupHasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">TARJETA</label><select id="tjCupTarjeta" class="form-select form-select-sm"></select></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">ESTADO</label>
                    <select id="tjCupEstado" class="form-select form-select-sm">
                        <option value="">TODOS</option><option value="0" selected>PENDIENTES</option><option value="1">LIQUIDADOS</option><option value="1000">ANULADOS</option>
                    </select></div>
                <div class="col-12 col-md-4 text-md-end">
                    <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" onclick="tjExcelCupones()"><i class="fas fa-file-excel me-1"></i>EXCEL</button>
                    <button class="btn btn-info btn-sm fw-bold rounded-pill px-3" onclick="tjListarCupones()"><i class="fas fa-search me-1"></i>CONSULTAR</button>
                </div>
            </div>
        </div></div>
        <div class="small text-white-50 mb-2" id="tjCupResumen"></div>
        <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded" style="max-height:60vh;background:#000a1a;overflow-y:auto">
            <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap">
                <thead class="sticky-top bg-black"><tr>
                    <th>FECHA</th><th>TARJETA</th><th class="text-center">CUOTAS</th><th>LOTE</th><th>CUPÓN</th><th>ORIGEN</th><th>CLIENTE</th>
                    <th class="text-end">IMPORTE</th><th>ESTADO</th><th>LIQ.</th>
                </tr></thead>
                <tbody id="tjCupBody"><tr><td colspan="10" class="text-center text-muted py-4">Presione CONSULTAR.</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ===================== CATÁLOGO ===================== -->
    <div data-tj-panel="cat" class="d-none">
        <div class="alert alert-info small py-2">
            Mientras no haya ninguna tarjeta habilitada, al cobrar con un medio de tipo <strong>Tarjetas</strong> no se pide cuál: el cupón queda "sin tarjeta".
            Con al menos una cargada, elegirla pasa a ser obligatorio en el mostrador y en Cobrar Facturas.
        </div>
        <div class="text-end mb-2"><button class="btn btn-success btn-sm fw-bold rounded-pill px-3" onclick="tjEditarTarjeta()"><i class="fas fa-plus me-1"></i>NUEVA TARJETA</button></div>
        <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded" style="background:#000a1a">
            <table class="table table-dark table-hover table-sm align-middle mb-0">
                <thead class="bg-black"><tr><th>TARJETA</th><th>TIPO</th><th class="text-center">DÍAS AL COBRO</th><th class="text-center">CUPONES PENDIENTES</th><th>ESTADO</th><th></th></tr></thead>
                <tbody id="tjCatBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL NUEVA LIQUIDACIÓN -->
<div class="modal fade" id="modTjLiq" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-accent fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>NUEVA LIQUIDACIÓN DE TARJETA</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3">
                <div class="row g-2 mb-3">
                    <div class="col-6 col-md-3"><label class="form-label-custom">MEDIO DE TARJETA</label><select id="tjLMedio" class="form-select form-select-sm"></select></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">TARJETA</label><select id="tjLTarjeta" class="form-select form-select-sm"></select></div>
                    <div class="col-6 col-md-2"><label class="form-label-custom">CUPONES HASTA</label><input type="date" id="tjLHasta" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-6 col-md-2 d-flex align-items-end"><button class="btn btn-outline-info btn-sm w-100" onclick="tjCargarPendientes()"><i class="fas fa-rotate me-1"></i>VER CUPONES</button></div>
                </div>
                <div class="row g-3">
                    <div class="col-12 col-lg-7">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-white-50">CUPONES PENDIENTES (tilde los que incluye la liquidación)</span>
                            <span><a href="#" onclick="tjMarcarTodos(true);return false;">todos</a> · <a href="#" onclick="tjMarcarTodos(false);return false;">ninguno</a></span>
                        </div>
                        <div class="border border-secondary rounded" style="max-height:45vh;overflow-y:auto">
                            <table class="table table-dark table-sm mb-0 small">
                                <thead class="sticky-top bg-black"><tr><th></th><th>FECHA</th><th>TARJETA</th><th>CUOTAS</th><th>CUPÓN</th><th>CLIENTE</th><th class="text-end">IMPORTE</th></tr></thead>
                                <tbody id="tjLCupones"><tr><td colspan="7" class="text-center text-muted py-3">Elija el medio y presione VER CUPONES.</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label-custom">FECHA ACREDITACIÓN</label><input type="date" id="tjLFecha" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                            <div class="col-6"><label class="form-label-custom">Nº LIQUIDACIÓN</label><input type="text" id="tjLNumero" class="form-control form-control-sm" maxlength="30"></div>
                            <div class="col-12"><label class="form-label-custom">SE ACREDITA EN</label><select id="tjLBanco" class="form-select form-select-sm"></select></div>
                            <div class="col-12"><label class="form-label-custom" title="Cupones cobrados antes de que existiera este módulo">BRUTO DE CUPONES ANTERIORES AL SISTEMA</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLBrutoAnt" value="0"></div>
                            <div class="col-6"><label class="form-label-custom">ARANCELES / COMISIONES</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLAranceles" value="0"></div>
                            <div class="col-6"><label class="form-label-custom">IVA S/ ARANCELES</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLIva" value="0"></div>
                            <div class="col-4"><label class="form-label-custom">RET. IIBB</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLRetIibb" value="0"></div>
                            <div class="col-4"><label class="form-label-custom">RET. IVA</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLRetIva" value="0"></div>
                            <div class="col-4"><label class="form-label-custom">RET. GANANCIAS</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLRetGan" value="0"></div>
                            <div class="col-12"><label class="form-label-custom">OTROS DESCUENTOS (costo financiero de cuotas, etc.)</label><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end tj-calc" id="tjLOtros" value="0"></div>
                            <div class="col-12"><label class="form-label-custom">OBSERVACIONES</label><input type="text" id="tjLObs" class="form-control form-control-sm" maxlength="255"></div>
                        </div>
                        <div class="mt-3 p-2 rounded border border-secondary small">
                            <div class="d-flex justify-content-between"><span class="text-white-50">Cupones seleccionados</span><span id="tjLSelCupones">$ 0,00</span></div>
                            <div class="d-flex justify-content-between"><span class="text-white-50">Bruto total</span><span id="tjLBruto">$ 0,00</span></div>
                            <div class="d-flex justify-content-between"><span class="text-white-50">Deducciones</span><span id="tjLDed" class="text-danger">$ 0,00</span></div>
                            <div class="d-flex justify-content-between fw-bold border-top border-secondary pt-1 mt-1"><span>NETO AL BANCO</span><span id="tjLNeto" class="text-success">$ 0,00</span></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-info rounded-pill px-4 fw-bold" id="tjLGuardar" onclick="tjGuardarLiq()"><i class="fas fa-check me-2"></i>REGISTRAR LIQUIDACIÓN</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const H = { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' };
    const money = n => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fdate = d => d ? String(d).slice(0, 10).split('-').reverse().join('/') : '';
    const esc = t => $('<div>').text(t ?? '').html();
    const num = sel => Math.round((parseFloat($(sel).val()) || 0) * 100) / 100;
    let medios = [], tarjetas = [], cupones = [], pendientes = [];

    async function api(action, body) {
        const opt = body === undefined ? { headers: H } : { method: 'POST', headers: H, body: JSON.stringify(body) };
        const res = await fetch(`api_tarjetas.php?action=${action}`, opt).then(r => r.json());
        if (!res.success) throw new Error(res.error || 'Error');
        return res;
    }
    const fallo = e => Swal.fire('Error', e.message, 'error');

    $('[data-tj-tab]').on('click', function () {
        $('[data-tj-tab]').removeClass('active'); $(this).addClass('active');
        $('[data-tj-panel]').addClass('d-none');
        $(`[data-tj-panel="${$(this).data('tj-tab')}"]`).removeClass('d-none');
        if ($(this).data('tj-tab') === 'cat') tjListarTarjetas();
    });

    async function cargarMaestros() {
        try {
            const res = await api('medios');
            medios = res.data; tarjetas = res.tarjetas;
            const optT = '<option value="">TODAS</option>' + tarjetas.map(t => `<option value="${t.id}">${esc(t.descripcion)}</option>`).join('') + '<option value="0">SIN TARJETA</option>';
            $('#tjCupTarjeta, #tjLTarjeta').html(optT);
            $('#tjLMedio').html(medios.filter(m => m.tipodecuenta === 'Tarjetas').map(m => `<option value="${m.id}">${esc(m.medio)}</option>`).join(''));
            $('#tjLBanco').html('<option value="">SELECCIONE...</option>' + medios.filter(m => m.tipodecuenta === 'Bancos').map(m => `<option value="${m.id}">${esc(m.medio)}</option>`).join(''));
        } catch (e) { fallo(e); }
    }

    // ---------------- liquidaciones
    window.tjListarLiq = async function () {
        try {
            const res = await api('liquidaciones_list', { desde: $('#tjLiqDesde').val(), hasta: $('#tjLiqHasta').val() });
            const tb = $('#tjLiqBody').empty();
            if (!res.data.length) return tb.html('<tr><td colspan="12" class="text-center text-muted py-4">Sin liquidaciones en el período.</td></tr>');
            res.data.forEach(l => {
                const bruto = (+l.bruto_cupones) + (+l.bruto_anterior);
                const anulada = +l.estado === 1000;
                tb.append(`<tr class="${anulada ? 'opacity-50' : ''}">
                    <td>${l.id}</td><td>${fdate(l.fecha)}</td><td>${esc(l.numero)}</td>
                    <td>${esc(l.medio_tarjeta)}<br><small class="text-white-50">${esc(l.tarjeta)}</small></td><td>${esc(l.banco)}</td>
                    <td class="text-end">${l.cantidad_cupones}${+l.bruto_anterior ? `<br><small class="text-warning" title="Bruto de cupones anteriores al sistema">+ $ ${money(l.bruto_anterior)}</small>` : ''}</td>
                    <td class="text-end">$ ${money(bruto)}</td><td class="text-end text-danger">$ ${money(bruto - l.neto)}</td>
                    <td class="text-end text-success fw-bold">$ ${money(l.neto)}</td><td>${l.numasiento || '-'}</td>
                    <td>${anulada ? `<span class="badge bg-danger" title="Por ${esc(l.anulada_por)} el ${esc(l.anulada_el)}">ANULADA</span>` : '<span class="badge bg-success">VIGENTE</span>'}</td>
                    <td>${anulada ? '' : `<button class="btn btn-sm btn-link text-danger p-0" title="Anular" onclick="tjAnularLiq(${l.id})"><i class="fas fa-ban"></i></button>`}</td>
                </tr>`);
            });
        } catch (e) { fallo(e); }
    };

    window.tjAnularLiq = async function (id) {
        const ok = await Swal.fire({ title: `¿Anular la liquidación #${id}?`, html: 'Se registran los movimientos inversos con fecha de hoy (banco, tesorería y asiento) y los cupones vuelven a quedar pendientes.', icon: 'warning', showCancelButton: true, confirmButtonText: 'SÍ, ANULAR', cancelButtonText: 'CANCELAR' }).then(r => r.isConfirmed);
        if (!ok) return;
        try { const res = await api('liquidacion_anular', { id }); Swal.fire('Listo', res.message, 'success'); tjListarLiq(); } catch (e) { fallo(e); }
    };

    window.tjNuevaLiq = function () {
        if (!$('#tjLMedio option').length) return Swal.fire('Atención', 'No hay medios de pago de tipo Tarjetas habilitados.', 'warning');
        pendientes = [];
        $('#tjLCupones').html('<tr><td colspan="7" class="text-center text-muted py-3">Elija el medio y presione VER CUPONES.</td></tr>');
        $('.tj-calc').val(0); $('#tjLNumero, #tjLObs').val(''); $('#tjLBanco').val('');
        tjRecalcular();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modTjLiq')).show();
        tjCargarPendientes();
    };

    window.tjCargarPendientes = async function () {
        try {
            const res = await api('cupones_pendientes', { idmediotarjeta: $('#tjLMedio').val(), idtarjeta: $('#tjLTarjeta').val(), hasta: $('#tjLHasta').val() });
            pendientes = res.data;
            const tb = $('#tjLCupones').empty();
            if (!pendientes.length) tb.html('<tr><td colspan="7" class="text-center text-muted py-3">No hay cupones pendientes con ese filtro.</td></tr>');
            pendientes.forEach(c => tb.append(`<tr>
                <td><input type="checkbox" class="form-check-input tj-chk" value="${c.id}" checked></td>
                <td>${fdate(c.fecha)}</td><td>${esc(c.tarjeta)}</td><td class="text-center">${c.cuotas}</td><td>${esc(c.numcupon)}</td>
                <td class="text-truncate" style="max-width:160px">${esc(c.cliente_nombre)}</td>
                <td class="text-end ${+c.importe < 0 ? 'text-danger' : ''}">$ ${money(c.importe)}</td></tr>`));
            tjRecalcular();
        } catch (e) { fallo(e); }
    };
    window.tjMarcarTodos = v => { $('.tj-chk').prop('checked', v); tjRecalcular(); };
    $(document).off('.tj').on('change.tj', '.tj-chk', tjRecalcular).on('input.tj', '.tj-calc', tjRecalcular);

    function tjRecalcular() {
        const sel = new Set($('.tj-chk:checked').map((_, el) => +el.value).get());
        const cup = Math.round(pendientes.filter(c => sel.has(+c.id)).reduce((a, c) => a + (+c.importe), 0) * 100) / 100;
        const bruto = cup + num('#tjLBrutoAnt');
        const ded = num('#tjLAranceles') + num('#tjLIva') + num('#tjLRetIibb') + num('#tjLRetIva') + num('#tjLRetGan') + num('#tjLOtros');
        $('#tjLSelCupones').text(`$ ${money(cup)} (${sel.size})`);
        $('#tjLBruto').text('$ ' + money(bruto));
        $('#tjLDed').text('$ ' + money(ded));
        $('#tjLNeto').text('$ ' + money(bruto - ded)).toggleClass('text-danger', bruto - ded < 0).toggleClass('text-success', bruto - ded >= 0);
    }
    window.tjRecalcular = tjRecalcular;

    window.tjGuardarLiq = async function () {
        const payload = {
            idmediotarjeta: +$('#tjLMedio').val(), idtarjeta: $('#tjLTarjeta').val() === '' ? 0 : +$('#tjLTarjeta').val(),
            idmediobanco: +$('#tjLBanco').val() || 0, fecha: $('#tjLFecha').val(), numero: $('#tjLNumero').val().trim(),
            cupones: $('.tj-chk:checked').map((_, el) => +el.value).get(),
            bruto_anterior: num('#tjLBrutoAnt'), aranceles: num('#tjLAranceles'), iva_aranceles: num('#tjLIva'),
            ret_iibb: num('#tjLRetIibb'), ret_iva: num('#tjLRetIva'), ret_ganancias: num('#tjLRetGan'), otros_descuentos: num('#tjLOtros'),
            observaciones: $('#tjLObs').val().trim()
        };
        if (!payload.idmediobanco) return Swal.fire('Atención', 'Elija la cuenta bancaria donde se acredita.', 'warning');
        const ok = await Swal.fire({ title: '¿Registrar la liquidación?', html: `Neto a acreditar: <strong>${$('#tjLNeto').text()}</strong>`, icon: 'question', showCancelButton: true, confirmButtonText: 'REGISTRAR', cancelButtonText: 'VOLVER' }).then(r => r.isConfirmed);
        if (!ok) return;
        $('#tjLGuardar').prop('disabled', true);
        try {
            const res = await api('liquidacion_save', payload);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modTjLiq')).hide();
            await Swal.fire('Listo', res.message, 'success');
            tjListarLiq();
        } catch (e) { fallo(e); } finally { $('#tjLGuardar').prop('disabled', false); }
    };

    // ---------------- cupones
    const ESTADOS = { 0: ['PENDIENTE', 'warning'], 1: ['LIQUIDADO', 'success'], 1000: ['ANULADO', 'secondary'] };
    window.tjListarCupones = async function () {
        try {
            const res = await api('cupones_list', { desde: $('#tjCupDesde').val(), hasta: $('#tjCupHasta').val(), idtarjeta: $('#tjCupTarjeta').val(), estado: $('#tjCupEstado').val() });
            cupones = res.data;
            const tb = $('#tjCupBody').empty();
            const tot = {};
            cupones.forEach(c => {
                if (+c.estado !== 1000) tot[c.tarjeta] = (tot[c.tarjeta] || 0) + (+c.importe);
                const [txt, color] = ESTADOS[c.estado] || ['?', 'secondary'];
                tb.append(`<tr><td>${fdate(c.fecha)}</td><td>${esc(c.tarjeta)}</td><td class="text-center">${c.cuotas}</td><td>${esc(c.lote)}</td><td>${esc(c.numcupon)}</td>
                    <td>${esc(c.origen)}</td><td class="text-truncate" style="max-width:180px">${esc(c.cliente_nombre)}</td>
                    <td class="text-end ${+c.importe < 0 ? 'text-danger' : ''}">$ ${money(c.importe)}</td>
                    <td><span class="badge bg-${color}">${txt}</span></td><td>${+c.idliquidacion ? '#' + c.idliquidacion : ''}</td></tr>`);
            });
            if (!cupones.length) tb.html('<tr><td colspan="10" class="text-center text-muted py-4">Sin cupones.</td></tr>');
            $('#tjCupResumen').html(Object.entries(tot).map(([t, v]) => `${esc(t)}: <strong>$ ${money(v)}</strong>`).join(' · '));
        } catch (e) { fallo(e); }
    };

    window.tjExcelCupones = function () {
        if (!cupones.length) return Swal.fire('Atención', 'Consulte los cupones primero.', 'info');
        let html = '<html><head><meta charset="UTF-8"></head><body><table border="1"><tr><th>FECHA</th><th>TARJETA</th><th>CUOTAS</th><th>LOTE</th><th>CUPON</th><th>ORIGEN</th><th>CLIENTE</th><th>IMPORTE</th><th>ESTADO</th><th>LIQUIDACION</th></tr>';
        cupones.forEach(c => { html += `<tr><td>${fdate(c.fecha)}</td><td>${esc(c.tarjeta)}</td><td>${c.cuotas}</td><td>${esc(c.lote)}</td><td>${esc(c.numcupon)}</td><td>${esc(c.origen)}</td><td>${esc(c.cliente_nombre)}</td><td style="mso-number-format:'#,##0.00'">${(+c.importe).toFixed(2)}</td><td>${(ESTADOS[c.estado] || ['?'])[0]}</td><td>${+c.idliquidacion || ''}</td></tr>`; });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['﻿' + html + '</table></body></html>'], { type: 'application/vnd.ms-excel' }));
        a.download = `Cupones_tarjeta_${$('#tjCupDesde').val()}_${$('#tjCupHasta').val()}.xls`;
        document.body.appendChild(a); a.click(); a.remove();
    };

    // ---------------- catálogo
    window.tjListarTarjetas = async function () {
        try {
            const res = await api('tarjetas_list');
            const tb = $('#tjCatBody').empty();
            if (!res.data.length) tb.html('<tr><td colspan="6" class="text-center text-muted py-4">No hay tarjetas cargadas.</td></tr>');
            res.data.forEach(t => tb.append(`<tr class="${+t.habilitada ? '' : 'opacity-50'}"><td class="fw-bold">${esc(t.descripcion)}</td><td>${+t.tipo === 2 ? 'DÉBITO' : 'CRÉDITO'}</td>
                <td class="text-center">${t.diasalcobro}</td><td class="text-center">${t.pendientes}</td>
                <td>${+t.habilitada ? '<span class="badge bg-success">HABILITADA</span>' : '<span class="badge bg-secondary">DESHABILITADA</span>'}</td>
                <td><button class="btn btn-sm btn-link text-info p-0" onclick='tjEditarTarjeta(${JSON.stringify(t).replace(/'/g, "&#39;")})'><i class="fas fa-pen"></i></button></td></tr>`));
        } catch (e) { fallo(e); }
    };

    window.tjEditarTarjeta = async function (t = null) {
        const r = await Swal.fire({
            title: t ? 'Editar tarjeta' : 'Nueva tarjeta',
            html: `<input id="swTjDesc" class="swal2-input" maxlength="30" placeholder="Nombre (ej. VISA CRÉDITO)" value="${esc(t?.descripcion || '')}">
                   <select id="swTjTipo" class="swal2-select"><option value="1">CRÉDITO</option><option value="2">DÉBITO</option></select>
                   <input id="swTjDias" type="number" class="swal2-input" min="0" max="120" placeholder="Días al cobro" value="${t ? t.diasalcobro : 2}">
                   <label class="d-block mt-2"><input id="swTjHab" type="checkbox" ${!t || +t.habilitada ? 'checked' : ''}> Habilitada</label>`,
            didOpen: () => { if (t) document.getElementById('swTjTipo').value = String(t.tipo); },
            showCancelButton: true, confirmButtonText: 'GUARDAR', cancelButtonText: 'CANCELAR',
            preConfirm: () => ({ id: t?.id || 0, descripcion: document.getElementById('swTjDesc').value.trim(), tipo: +document.getElementById('swTjTipo').value,
                                 diasalcobro: +document.getElementById('swTjDias').value || 0, habilitada: document.getElementById('swTjHab').checked })
        });
        if (!r.isConfirmed) return;
        try { await api('tarjeta_save', r.value); tjListarTarjetas(); cargarMaestros(); } catch (e) { fallo(e); }
    };

    cargarMaestros().then(tjListarLiq);
})();
</script>
