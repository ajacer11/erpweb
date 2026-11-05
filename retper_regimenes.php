<?php
/**
 * retper_regimenes.php - REGÍMENES DE RETENCIÓN Y PERCEPCIÓN (agente)
 * Backend: api_retper.php. Cálculo: retper_helpers.php.
 */
require_once 'check_access.php';
$esAdmin = (int) ($_SESSION['es_admin'] ?? 0) === 1;
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloRetper">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-percent text-accent me-2"></i>REGÍMENES DE RETENCIÓN Y PERCEPCIÓN</h4>
            <small class="text-accent opacity-75">Configuración como agente de IVA, Ganancias, Ingresos Brutos u otros</small>
        </div>
        <?php if ($esAdmin): ?>
        <button class="btn btn-success btn-sm fw-bold rounded-pill px-3" onclick="rpEditar()"><i class="fas fa-plus me-1"></i>NUEVO RÉGIMEN</button>
        <?php endif; ?>
    </div>

    <div class="alert alert-info small py-2">
        Solo se retiene o percibe con los regímenes <strong>habilitados</strong>: sin ninguno habilitado, pagar a proveedores y facturar funcionan igual que siempre.
        Las <strong>retenciones</strong> se calculan al pagar a proveedores (Proveedores > Pagos); las <strong>percepciones</strong>, al facturar comprobantes fiscales, y se informan a ARCA dentro del mismo pedido de CAE.
        <?php if (!$esAdmin): ?><br>Solo un administrador puede modificar esta configuración.<?php endif; ?>
    </div>

    <div class="table-responsive border border-secondary border-opacity-25 rounded mb-3" style="background:#000a1a">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap">
            <thead class="bg-black"><tr><th>RÉGIMEN</th><th>TIPO</th><th>IMPUESTO</th><th>BASE</th><th class="text-end">ALÍCUOTA</th><th class="text-end">MÍNIMO</th><th>PADRÓN</th><th>EXCEPCIONES</th><th>CUENTA</th><th>ESTADO</th><th></th></tr></thead>
            <tbody id="rpBody"><tr><td colspan="11" class="text-center text-muted py-4">Cargando...</td></tr></tbody>
        </table>
    </div>

    <!-- Detalle de padrón / excepciones del régimen elegido -->
    <div id="rpDetalle" class="d-none">
        <h6 class="text-accent fw-bold" id="rpDetTitulo"></h6>
        <ul class="nav nav-tabs border-secondary mb-2">
            <li class="nav-item"><button class="nav-link active fw-bold" data-rp-tab="padron" type="button">PADRÓN POR CUIT</button></li>
            <li class="nav-item"><button class="nav-link fw-bold" data-rp-tab="sujetos" type="button">EXCEPCIONES POR CLIENTE / PROVEEDOR</button></li>
        </ul>
        <div data-rp-panel="padron">
            <div class="d-flex flex-wrap gap-2 align-items-end mb-2">
                <div><label class="form-label-custom">BUSCAR CUIT</label><input type="text" id="rpPadQ" class="form-control form-control-sm" style="width:180px"></div>
                <button class="btn btn-sm btn-outline-info" onclick="rpPadron()"><i class="fas fa-search"></i></button>
                <?php if ($esAdmin): ?>
                <div class="ms-md-4"><label class="form-label-custom">IMPORTAR (CSV o Excel: CUIT ; alícuota ; excluido S/N ; desde ; hasta)</label>
                    <div class="input-group input-group-sm"><input type="file" id="rpPadArchivo" class="form-control" accept=".csv,.txt,.xlsx,.xls">
                    <div class="input-group-text bg-dark border-secondary"><input type="checkbox" class="form-check-input mt-0 me-1" id="rpPadReemplazar" checked><label for="rpPadReemplazar" class="small text-white mb-0">reemplazar</label></div>
                    <button class="btn btn-info fw-bold" onclick="rpImportarPadron()">IMPORTAR</button></div></div>
                <button class="btn btn-sm btn-outline-danger" onclick="rpVaciarPadron()">VACIAR PADRÓN</button>
                <?php endif; ?>
            </div>
            <div class="small text-white-50 mb-1" id="rpPadInfo"></div>
            <div class="border border-secondary rounded" style="max-height:40vh;overflow:auto;background:#000a1a">
                <table class="table table-dark table-sm mb-0 small"><thead class="sticky-top bg-black"><tr><th>CUIT</th><th class="text-end">ALÍCUOTA</th><th>EXCLUIDO</th><th>VIGENCIA</th></tr></thead><tbody id="rpPadBody"></tbody></table>
            </div>
        </div>
        <div data-rp-panel="sujetos" class="d-none">
            <?php if ($esAdmin): ?>
            <div class="row g-2 align-items-end mb-2">
                <div class="col-6 col-md-2"><label class="form-label-custom">TIPO</label><select id="rpSujTipo" class="form-select form-select-sm"><option value="proveedor">PROVEEDOR</option><option value="cliente">CLIENTE</option></select></div>
                <div class="col-6 col-md-3"><label class="form-label-custom">BUSCAR</label><input type="text" id="rpSujBuscar" class="form-control form-control-sm" list="rpSujLista" placeholder="Nombre o CUIT"><datalist id="rpSujLista"></datalist></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">MODO</label><select id="rpSujModo" class="form-select form-select-sm"><option value="EXENTO">EXENTO / NO RETENER</option><option value="ALICUOTA">ALÍCUOTA PROPIA</option></select></div>
                <div class="col-6 col-md-1"><label class="form-label-custom">%</label><input type="text" id="rpSujAlic" class="form-control form-control-sm" value="0"></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">VIGENTE HASTA</label><input type="date" id="rpSujHasta" class="form-control form-control-sm"></div>
                <div class="col-6 col-md-2"><label class="form-label-custom">CERTIFICADO</label><input type="text" id="rpSujCert" class="form-control form-control-sm" maxlength="100"></div>
                <div class="col-12 text-end"><button class="btn btn-sm btn-info fw-bold" onclick="rpGuardarSujeto()"><i class="fas fa-plus me-1"></i>AGREGAR / ACTUALIZAR</button></div>
            </div>
            <?php endif; ?>
            <table class="table table-dark table-sm small"><thead><tr><th>TIPO</th><th>NOMBRE</th><th>MODO</th><th class="text-end">%</th><th>VIGENTE HASTA</th><th>CERTIFICADO</th><th></th></tr></thead><tbody id="rpSujBody"></tbody></table>
        </div>
    </div>
</div>

<!-- MODAL RÉGIMEN -->
<div class="modal fade" id="modRp" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header"><h6 class="modal-title text-accent fw-bold">RÉGIMEN</h6><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-3">
                <input type="hidden" id="rpId">
                <div class="row g-2">
                    <div class="col-12 col-md-6"><label class="form-label-custom">NOMBRE</label><input type="text" id="rpNombre" class="form-control form-control-sm" maxlength="100" placeholder="Ej.: Retención IIBB Mendoza"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">TIPO</label><select id="rpTipo" class="form-select form-select-sm"><option value="RETENCION">RETENCIÓN (pagos)</option><option value="PERCEPCION">PERCEPCIÓN (ventas)</option></select></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">IMPUESTO</label><select id="rpImpuesto" class="form-select form-select-sm"><option>IIBB</option><option>IVA</option><option>GANANCIAS</option><option>OTRO</option></select></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">JURISDICCIÓN</label><input type="text" id="rpJuris" class="form-control form-control-sm" value="Nacional"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">BASE DE CÁLCULO</label><select id="rpBase" class="form-select form-select-sm"><option value="NETO">NETO (sin IVA)</option><option value="TOTAL">TOTAL</option><option value="IVA">IVA</option></select></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">ALÍCUOTA %</label><input type="text" id="rpAlic" class="form-control form-control-sm text-end"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom" title="Se usa si el sujeto no es RI, Monotributo ni Exento">% NO INSCRIPTOS</label><input type="text" id="rpAlicNI" class="form-control form-control-sm text-end" placeholder="igual"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">MÍNIMO NO SUJETO $</label><input type="text" id="rpMinimo" class="form-control form-control-sm text-end" value="0"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">IMPORTE MÍNIMO $</label><input type="text" id="rpMinImp" class="form-control form-control-sm text-end" value="0"></div>
                    <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="rpRestaMin"><label class="form-check-label small text-white" for="rpRestaMin">Aplicar sobre el excedente del mínimo (Ganancias)</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="rpAcumula"><label class="form-check-label small text-white" for="rpAcumula">Acumulado mensual por sujeto</label></div>
                    </div>
                    <div class="col-12"><label class="form-label-custom">CONDICIONES ALCANZADAS (ninguna tildada = todas)</label>
                        <div class="d-flex flex-wrap gap-3 small" id="rpConds">
                            <?php foreach (['Responsable Inscripto', 'Monotributo', 'Exento', 'Consumidor Final'] as $c): ?>
                            <label class="text-white"><input type="checkbox" class="form-check-input rp-cond" value="<?= $c ?>"> <?= $c ?></label>
                            <?php endforeach; ?>
                        </div></div>
                    <div class="col-12 col-md-6 d-flex flex-column justify-content-end">
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="rpPadron"><label class="form-check-label small text-white" for="rpPadron">Usa padrón por CUIT</label></div>
                    </div>
                    <div class="col-12 col-md-6"><label class="form-label-custom">SI EL CUIT NO ESTÁ EN EL PADRÓN</label><select id="rpSinPadron" class="form-select form-select-sm"><option value="GENERAL">APLICAR LA ALÍCUOTA GENERAL</option><option value="NO_APLICA">NO RETENER / PERCIBIR</option></select></div>
                    <div class="col-12 col-md-6"><label class="form-label-custom">CUENTA CONTABLE (pasivo a depositar)</label><input type="text" id="rpCuenta" class="form-control form-control-sm" list="rpPlan" placeholder="Número de cuenta"><datalist id="rpPlan"></datalist></div>
                    <div class="col-4 col-md-2"><label class="form-label-custom" title="Solo percepciones: código de tributo en la factura electrónica">TRIBUTO ARCA</label><input type="number" id="rpTributo" class="form-control form-control-sm" placeholder="auto"></div>
                    <div class="col-4 col-md-2"><label class="form-label-custom">CÓD. IMPUESTO</label><input type="text" id="rpCodImp" class="form-control form-control-sm" maxlength="10"></div>
                    <div class="col-4 col-md-2"><label class="form-label-custom">CÓD. RÉGIMEN</label><input type="text" id="rpCodReg" class="form-control form-control-sm" maxlength="10"></div>
                    <div class="col-12 col-md-6"><label class="form-label-custom">RESOLUCIÓN / NORMATIVA</label><input type="text" id="rpResol" class="form-control form-control-sm" maxlength="120"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">VIGENTE DESDE</label><input type="date" id="rpDesde" class="form-control form-control-sm"></div>
                    <div class="col-6 col-md-3"><label class="form-label-custom">VIGENTE HASTA</label><input type="date" id="rpHasta" class="form-control form-control-sm"></div>
                    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="rpHab"><label class="form-check-label fw-bold text-white" for="rpHab">HABILITADO</label></div></div>
                </div>
                <div class="alert alert-warning small py-2 mt-2 mb-0" id="rpAvisoPerc">
                    Percepciones: la factura electrónica la pide el servidor de ARCA del sistema; tiene que tener la versión de ARCA/ArcaFunctions.php del 15/10/2026 o posterior.
                    Si no la tiene, al facturar se avisa y no se emite el comprobante.
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <button class="btn btn-outline-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">CANCELAR</button>
                <?php if ($esAdmin): ?><button class="btn btn-info rounded-pill px-4 fw-bold" onclick="rpGuardar()"><i class="fas fa-check me-2"></i>GUARDAR</button><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const H = { 'X-Requested-With': 'XMLHttpRequest' };
    const esc = t => $('<div>').text(t ?? '').html();
    const num = v => (parseFloat(v) || 0).toLocaleString('es-AR', { maximumFractionDigits: 4 });
    let regimenes = [], actual = null, plan = null;

    async function get(a, qs = '') { const r = await fetch(`api_retper.php?action=${a}${qs}`, { headers: H }).then(r => r.json()); if (!r.success) throw new Error(r.error); return r; }
    async function post(a, b) { const r = await fetch(`api_retper.php?action=${a}`, { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' }, body: JSON.stringify(b) }).then(r => r.json()); if (!r.success) throw new Error(r.error); return r; }
    const fallo = e => Swal.fire('Error', e.message, 'error');

    async function listar() {
        try {
            regimenes = (await get('regimenes_list')).data;
            const tb = $('#rpBody').empty();
            if (!regimenes.length) tb.html('<tr><td colspan="11" class="text-center text-muted py-4">No hay regímenes cargados.</td></tr>');
            regimenes.forEach(r => tb.append(`<tr class="${+r.habilitado ? '' : 'opacity-50'}">
                <td class="fw-bold">${esc(r.nombre)}<br><small class="text-white-50">${esc(r.jurisdiccion)}</small></td>
                <td>${r.tipo === 'RETENCION' ? 'RETENCIÓN' : 'PERCEPCIÓN'}</td><td>${esc(r.impuesto)}</td><td>${esc(r.base)}</td>
                <td class="text-end">${num(r.alicuota)}%${r.alicuota_no_inscripto !== null ? `<br><small class="text-white-50">NI ${num(r.alicuota_no_inscripto)}%</small>` : ''}</td>
                <td class="text-end">$ ${num(r.minimo_no_sujeto)}${+r.acumula_mensual ? '<br><small class="text-white-50">mensual</small>' : ''}</td>
                <td>${+r.usa_padron ? `<span class="badge bg-info">${r.padron_cant} CUIT</span>` : '-'}</td><td>${r.sujetos_cant}</td><td>${esc(r.cuenta_contable)}</td>
                <td>${+r.habilitado ? '<span class="badge bg-success">HABILITADO</span>' : '<span class="badge bg-secondary">DESHABILITADO</span>'}</td>
                <td class="text-nowrap"><button class="btn btn-sm btn-link text-info p-0" title="Editar" onclick="rpEditar(${r.id})"><i class="fas fa-pen"></i></button>
                    <button class="btn btn-sm btn-link text-accent p-0 ms-2" title="Padrón y excepciones" onclick="rpDetalle(${r.id})"><i class="fas fa-list"></i></button></td></tr>`));
        } catch (e) { fallo(e); }
    }

    window.rpEditar = async function (id) {
        const r = regimenes.find(x => +x.id === id) || { tipo: 'RETENCION', impuesto: 'IIBB', jurisdiccion: 'Nacional', base: 'NETO', alicuota: '', minimo_no_sujeto: 0, minimo_importe: 0, sin_padron: 'GENERAL', condiciones: '' };
        if (!plan) { try { plan = (await get('plan_cuentas')).data; $('#rpPlan').html(plan.map(p => `<option value="${esc(p.numcuenta)}">${esc(p.descripcion)}</option>`).join('')); } catch (e) { } }
        $('#rpId').val(r.id || ''); $('#rpNombre').val(r.nombre || ''); $('#rpTipo').val(r.tipo); $('#rpImpuesto').val(r.impuesto); $('#rpJuris').val(r.jurisdiccion);
        $('#rpBase').val(r.base); $('#rpAlic').val(r.alicuota); $('#rpAlicNI').val(r.alicuota_no_inscripto ?? ''); $('#rpMinimo').val(r.minimo_no_sujeto); $('#rpMinImp').val(r.minimo_importe);
        $('#rpRestaMin').prop('checked', +r.resta_minimo === 1); $('#rpAcumula').prop('checked', +r.acumula_mensual === 1);
        const conds = (r.condiciones || '').split(',').map(s => s.trim());
        $('.rp-cond').each(function () { this.checked = conds.includes(this.value); });
        $('#rpPadron').prop('checked', +r.usa_padron === 1); $('#rpSinPadron').val(r.sin_padron); $('#rpCuenta').val(r.cuenta_contable || '');
        $('#rpTributo').val(r.id ? r.codigo_tributo_arca : ''); $('#rpCodImp').val(r.codigo_impuesto || ''); $('#rpCodReg').val(r.codigo_regimen || '');
        $('#rpResol').val(r.resolucion || ''); $('#rpDesde').val(r.vigente_desde || ''); $('#rpHasta').val(r.vigente_hasta || ''); $('#rpHab').prop('checked', +r.habilitado === 1);
        $('#rpAvisoPerc').toggleClass('d-none', r.tipo !== 'PERCEPCION');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modRp')).show();
    };
    $('#rpTipo').on('change', function () { $('#rpAvisoPerc').toggleClass('d-none', this.value !== 'PERCEPCION'); });

    window.rpGuardar = async function () {
        const b = {
            id: +$('#rpId').val() || 0, nombre: $('#rpNombre').val(), tipo: $('#rpTipo').val(), impuesto: $('#rpImpuesto').val(), jurisdiccion: $('#rpJuris').val(),
            base: $('#rpBase').val(), alicuota: $('#rpAlic').val(), alicuota_no_inscripto: $('#rpAlicNI').val(), minimo_no_sujeto: $('#rpMinimo').val(), minimo_importe: $('#rpMinImp').val(),
            resta_minimo: $('#rpRestaMin').is(':checked'), acumula_mensual: $('#rpAcumula').is(':checked'), condiciones: $('.rp-cond:checked').map((_, el) => el.value).get(),
            usa_padron: $('#rpPadron').is(':checked'), sin_padron: $('#rpSinPadron').val(), cuenta_contable: $('#rpCuenta').val().trim().split(' ')[0],
            codigo_tributo_arca: $('#rpTributo').val(), codigo_impuesto: $('#rpCodImp').val(), codigo_regimen: $('#rpCodReg').val(), resolucion: $('#rpResol').val(),
            vigente_desde: $('#rpDesde').val(), vigente_hasta: $('#rpHasta').val(), habilitado: $('#rpHab').is(':checked')
        };
        try { await post('regimen_save', b); bootstrap.Modal.getOrCreateInstance(document.getElementById('modRp')).hide(); listar(); } catch (e) { fallo(e); }
    };

    // ---- padrón y excepciones
    $('[data-rp-tab]').on('click', function () {
        $('[data-rp-tab]').removeClass('active'); $(this).addClass('active');
        $('[data-rp-panel]').addClass('d-none'); $(`[data-rp-panel="${$(this).data('rp-tab')}"]`).removeClass('d-none');
    });
    window.rpDetalle = function (id) {
        actual = regimenes.find(x => +x.id === id);
        $('#rpDetTitulo').text(actual.nombre); $('#rpDetalle').removeClass('d-none');
        rpPadron(); rpSujetos();
    };
    window.rpPadron = async function () {
        try {
            const res = await get('padron_list', `&idregimen=${actual.id}&q=${encodeURIComponent($('#rpPadQ').val())}`);
            $('#rpPadInfo').text(`${res.total} CUIT en el padrón${+actual.usa_padron ? '' : ' (el régimen no está marcado como "usa padrón": no se consulta)'}. Se muestran hasta 200.`);
            $('#rpPadBody').html(res.data.map(p => `<tr><td>${esc(p.cuit)}</td><td class="text-end">${num(p.alicuota)}%</td><td>${+p.excluido ? 'SÍ' : ''}</td><td>${esc(p.vigente_desde || '')} ${p.vigente_hasta ? 'al ' + esc(p.vigente_hasta) : ''}</td></tr>`).join(''));
        } catch (e) { fallo(e); }
    };
    window.rpImportarPadron = async function () {
        const f = document.getElementById('rpPadArchivo').files[0];
        if (!f) return Swal.fire('Atención', 'Elija el archivo del padrón.', 'warning');
        const fd = new FormData(); fd.append('archivo', f); fd.append('idregimen', actual.id); fd.append('reemplazar', $('#rpPadReemplazar').is(':checked') ? '1' : '0');
        Swal.fire({ title: 'Importando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const res = await fetch('api_retper.php?action=padron_importar', { method: 'POST', headers: H, body: fd }).then(r => r.json());
            if (!res.success) throw new Error(res.error);
            Swal.fire('Padrón importado', `${res.importados} CUIT importados; ${res.descartados} renglones descartados (encabezados o sin CUIT válido).`, 'success');
            rpPadron(); listar();
        } catch (e) { fallo(e); }
    };
    window.rpVaciarPadron = async function () {
        if (!(await Swal.fire({ title: '¿Vaciar el padrón de este régimen?', icon: 'warning', showCancelButton: true, confirmButtonText: 'VACIAR', cancelButtonText: 'CANCELAR' })).isConfirmed) return;
        try { await post('padron_vaciar', { idregimen: actual.id }); rpPadron(); listar(); } catch (e) { fallo(e); }
    };
    async function rpSujetos() {
        try {
            const res = await get('sujetos_list', `&idregimen=${actual.id}`);
            $('#rpSujBody').html(res.data.map(s => `<tr><td>${s.tipo_sujeto.toUpperCase()}</td><td>${esc(s.nombre)}</td><td>${s.modo === 'EXENTO' ? 'EXENTO' : 'ALÍCUOTA PROPIA'}</td>
                <td class="text-end">${s.modo === 'EXENTO' ? '' : num(s.alicuota) + '%'}</td><td>${esc(s.vigente_hasta || 'sin vencimiento')}</td><td>${esc(s.certificado)}</td>
                <td>${<?= $esAdmin ? 'true' : 'false' ?> ? `<button class="btn btn-sm btn-link text-danger p-0" onclick="rpBorrarSujeto(${s.id})"><i class="fas fa-trash"></i></button>` : ''}</td></tr>`).join('') || '<tr><td colspan="7" class="text-muted text-center">Sin excepciones.</td></tr>');
        } catch (e) { fallo(e); }
    }
    let sujetosBusqueda = [];
    $('#rpSujBuscar').on('input', async function () {
        if (this.value.length < 2) return;
        try {
            sujetosBusqueda = (await get('buscar_sujetos', `&tipo=${$('#rpSujTipo').val()}&q=${encodeURIComponent(this.value)}`)).data;
            $('#rpSujLista').html(sujetosBusqueda.map(s => `<option value="${esc(s.nombre)} [${s.id}]">${esc(s.cuit || '')}</option>`).join(''));
        } catch (e) { }
    });
    window.rpGuardarSujeto = async function () {
        const m = $('#rpSujBuscar').val().match(/\[(\d+)\]$/);
        if (!m) return Swal.fire('Atención', 'Elija el cliente o proveedor de la lista.', 'warning');
        try {
            await post('sujeto_save', { idregimen: actual.id, tipo_sujeto: $('#rpSujTipo').val(), idsujeto: +m[1], modo: $('#rpSujModo').val(), alicuota: $('#rpSujAlic').val(), vigente_hasta: $('#rpSujHasta').val(), certificado: $('#rpSujCert').val() });
            $('#rpSujBuscar, #rpSujCert, #rpSujHasta').val(''); rpSujetos(); listar();
        } catch (e) { fallo(e); }
    };
    window.rpBorrarSujeto = async function (id) {
        try { await post('sujeto_borrar', { id }); rpSujetos(); listar(); } catch (e) { fallo(e); }
    };

    listar();
})();
</script>
