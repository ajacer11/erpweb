<?php
/**
 * tesoreria_nuevo_movimiento.php - LÓGICA ESTRICTA VB.NET
 */

require_once 'check_access.php';
$hoy = date('Y-m-d');
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloNuevoMov">
    <!-- TITULO -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">MOVIMIENTO DE TESORERÍA</h4>
            <small class="text-accent opacity-75">Carga Manual de Asientos y Valores</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary rounded-pill" onclick="limpiarPantalla()">
                <i class="fas fa-eraser me-2"></i>LIMPIAR
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow" onclick="grabar()">
                <i class="fas fa-save me-2"></i>GRABAR MOVIMIENTO
            </button>
        </div>
    </div>

    <!-- DATOS GENERALES -->
    <div class="card card-filtros p-3 mb-3">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label-custom">FECHA</label>
                <input type="date" id="txtFecha" class="form-control" value="<?php echo $hoy; ?>" max="<?php echo $hoy; ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label-custom">OBSERVACIONES</label>
                <input type="text" id="txtObservaciones" class="form-control bg-dark text-white border-secondary" placeholder="Detalle del movimiento...">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom text-warning">PROVEEDOR (Destinatario)</label>
                <select id="cmbProveedor" class="form-select select2-dark"></select>
            </div>
            <div class="col-md-3">
                <label class="form-label-custom text-info">CLIENTE (Remitente)</label>
                <select id="cmbCliente" class="form-select select2-dark"></select>
            </div>
        </div>
    </div>

    <!-- PANELES DE CARGA (DEBE / HABER) -->
    <div class="row g-3">
        <!-- AGREGAR AL DEBE -->
        <div class="col-md-6">
            <div class="card bg-info bg-opacity-10 border-info border-opacity-25 p-3 h-100">
                <h6 class="text-info small fw-bold mb-3"><i class="fas fa-arrow-down me-2"></i>AGREGAR AL DEBE (DESTINO)</h6>
                <div class="row g-2">
                    <div class="col-8">
                        <select id="cmbDebe" class="form-select select2-dark"></select>
                    </div>
                    <div class="col-4">
                        <div class="input-group">
                            <input type="number" id="txtImporteDebe" class="form-control fw-bold" placeholder="0.00" step="0.01">
                            <button class="btn btn-info" onclick="agregarDebeManual()"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- AGREGAR AL HABER -->
        <div class="col-md-6">
            <div class="card bg-warning bg-opacity-10 border-warning border-opacity-25 p-3 h-100">
                <h6 class="text-warning small fw-bold mb-3"><i class="fas fa-arrow-up me-2"></i>AGREGAR AL HABER (ORIGEN)</h6>
                <div class="row g-2">
                    <div class="col-8">
                        <select id="cmbHaber" class="form-select select2-dark"></select>
                    </div>
                    <div class="col-4">
                        <div class="input-group">
                            <input type="number" id="txtImporteHaber" class="form-control fw-bold" placeholder="0.00" step="0.01">
                            <button class="btn btn-warning" onclick="agregarHaberManual()"><i class="fas fa-plus"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- GRILLA PRINCIPAL (ASIENTO) -->
    <div class="mt-3 table-responsive border border-secondary rounded shadow-sm" style="background: #000a1a;">
        <table class="table table-dark table-sm table-hover align-middle mb-0" id="dgvmovs">
            <thead class="bg-black border-bottom border-secondary text-uppercase small text-muted">
                <tr>
                    <th class="ps-3 py-2">CUENTA / DESCRIPCIÓN</th>
                    <th width="150" class="text-end py-2">DEBE</th>
                    <th width="150" class="text-end py-2">HABER</th>
                    <th width="50" class="text-center py-2"></th>
                </tr>
            </thead>
            <tbody id="tbodyAsiento"></tbody>
            <tfoot class="bg-dark border-top border-secondary fw-bold">
                <tr>
                    <td class="text-end pe-3">TOTALES:</td>
                    <td class="text-end text-info" id="lblTotalDebe">$ 0.00</td>
                    <td class="text-end text-warning" id="lblTotalHaber">$ 0.00</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="text-end pe-3 text-white-50">SALDO (DIFERENCIA):</td>
                    <td colspan="2" class="text-center fs-5" id="lblSaldo">$ 0.00</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- GRILLAS AUXILIARES DE VALORES (TABS) -->
    <div class="mt-4">
        <ul class="nav nav-tabs border-secondary" role="tablist">
            <li class="nav-item">
                <button class="nav-link active text-info small fw-bold bg-dark border-secondary" data-bs-toggle="tab" data-bs-target="#tabRecibidos">
                    CHEQUES RECIBIDOS (<span id="cntRec">0</span>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link text-warning small fw-bold bg-dark border-secondary" data-bs-toggle="tab" data-bs-target="#tabEndosos">
                    CHEQUES ENDOSADOS (<span id="cntEnd">0</span>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link text-danger small fw-bold bg-dark border-secondary" data-bs-toggle="tab" data-bs-target="#tabPropios">
                    CHEQUES PROPIOS (<span id="cntPro">0</span>)
                </button>
            </li>
        </ul>
        <div class="tab-content border border-top-0 border-secondary bg-dark bg-opacity-25 p-0">
            <!-- TAB 1: Cheques Recibidos -->
            <div class="tab-pane fade show active" id="tabRecibidos">
                <div class="table-responsive" style="max-height: 200px;">
                    <table class="table table-sm table-dark small mb-0">
                        <thead class="text-muted"><tr><th>Nº</th><th>BANCO</th><th>VENCIMIENTO</th><th class="text-end">IMPORTE</th><th></th></tr></thead>
                        <tbody id="gridRecibidos"></tbody>
                    </table>
                </div>
            </div>
            <!-- TAB 2: Endosos -->
            <div class="tab-pane fade" id="tabEndosos">
                <div class="table-responsive" style="max-height: 200px;">
                    <table class="table table-sm table-dark small mb-0">
                        <thead class="text-muted"><tr><th>Nº</th><th>BANCO</th><th>CLIENTE ORIGEN</th><th class="text-end">IMPORTE</th><th></th></tr></thead>
                        <tbody id="gridEndosos"></tbody>
                    </table>
                </div>
            </div>
            <!-- TAB 3: Propios -->
            <div class="tab-pane fade" id="tabPropios">
                <div class="table-responsive" style="max-height: 200px;">
                    <table class="table table-sm table-dark small mb-0">
                        <thead class="text-muted"><tr><th>Nº</th><th>CUENTA BANCARIA</th><th>VENCIMIENTO</th><th class="text-end">IMPORTE</th><th></th></tr></thead>
                        <tbody id="gridPropios"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ==================================================================================== -->
<!-- MODALES (Replica exacta de formularios VB) -->
<!-- ==================================================================================== -->

<!-- Modal Nuevo Cheque (Entrada) -->
<div class="modal fade" id="modalNuevoCheque" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-info">
            <div class="modal-header bg-info bg-opacity-25 py-2">
                <h6 class="modal-title text-white">INGRESAR CHEQUE DE TERCERO</h6>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-md-3"><label class="small">Nº Cheque</label><input type="text" id="ncNum" class="form-control"></div>
                    <div class="col-md-3"><label class="small">Importe</label><input type="number" id="ncImp" class="form-control fw-bold" step="0.01"></div>
                    <div class="col-md-6"><label class="small">Banco</label><select id="ncBan" class="form-select select2-modal"></select></div>
                    <div class="col-md-4"><label class="small">F. Emisión</label><input type="date" id="ncFemi" class="form-control" value="<?php echo $hoy; ?>"></div>
                    <div class="col-md-4"><label class="small">F. Cobro</label><input type="date" id="ncFcob" class="form-control" value="<?php echo $hoy; ?>"></div>
                    <div class="col-md-4"><label class="small">Tipo</label><select id="ncTipo" class="form-select"><option value="Comun">Común</option><option value="Diferido">Diferido</option></select></div>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info btn-sm rounded-pill fw-bold px-4" onclick="addChequeRecibido()">AGREGAR</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Buscar Cheques (Endosos) -->
<div class="modal fade" id="modalBuscarCheques" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning bg-opacity-25 py-2">
                <h6 class="modal-title text-white">SELECCIONAR CHEQUES PARA ENDOSAR</h6>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive" style="height: 300px;">
                    <table class="table table-dark table-hover table-sm small mb-0">
                        <thead class="sticky-top bg-black"><tr><th width="40"></th><th>Nº</th><th>Banco</th><th>Importe</th><th>Vencimiento</th><th>Cliente</th></tr></thead>
                        <tbody id="tbodyCarteraDisponible"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1 justify-content-between">
                <span id="lblTotalEndosoSel" class="text-warning fw-bold small">Total: $ 0.00</span>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="button" class="btn btn-warning btn-sm rounded-pill fw-bold px-4" onclick="addChequesEndosados()">ENDOSAR</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Cheque Propio (Emisión) -->
<div class="modal fade" id="modalChequePropio" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger bg-opacity-25 py-2">
                <h6 class="modal-title text-white">EMITIR CHEQUE PROPIO</h6>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <label class="small text-muted">Cuenta Bancaria Origen</label>
                    <select id="cpCtaBan" class="form-select select2-modal"></select>
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="small">Nº Cheque</label><input type="text" id="cpNum" class="form-control"></div>
                    <div class="col-6"><label class="small">Importe</label><input type="number" id="cpImp" class="form-control fw-bold" step="0.01"></div>
                    <div class="col-6"><label class="small">F. Emisión</label><input type="date" id="cpFemi" class="form-control" value="<?php echo $hoy; ?>"></div>
                    <div class="col-6"><label class="small">F. Vencimiento</label><input type="date" id="cpFvenc" class="form-control" value="<?php echo $hoy; ?>"></div>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-danger btn-sm rounded-pill fw-bold px-4" onclick="addChequePropio()">EMITIR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // ESTADO DEL ASIENTO
    let dgvmovs = []; // Array principal {idcuenta, medio, debe, haber, tipo, ...}
    let dgvcheques = []; // Recibidos
    let dgvendosos = []; // Salientes cartera
    let dgvchequespropios = []; // Salientes propios

    // Cache de Entidades
    let cuentasData = [];

    async function init() {
        const headers = {'X-Requested-With': 'XMLHttpRequest'};
        
        // 1. Cargar Combos
        const [prov, cli, ctas, bancos, ctasProp] = await Promise.all([
            fetch('api_helpers.php?action=get_proveedores', {headers}).then(r=>r.json()),
            fetch('api_helpers.php?action=get_clientes', {headers}).then(r=>r.json()),
            fetch('api_tesoreria_cuentas.php?action=list&estado=1', {headers}).then(r=>r.json()),
            fetch('api_helpers.php?action=get_bancos', {headers}).then(r=>r.json()),
            fetch('api_bancos_cuentas.php?action=list', {headers}).then(r=>r.json())
        ]);

        fillCombo('#cmbProveedor', prov.data, 'razon');
        fillCombo('#cmbCliente', cli.data, 'razon');
        fillCombo('#ncBan', bancos.data, 'denominacion');
        
        // Llenar Cuentas Propias en Modal
        const elCtaP = $('#cpCtaBan'); elCtaP.empty();
        ctasProp.forEach(c => elCtaP.append(new Option(`${c.referencia} (${c.banco_nombre})`, c.id)));

        // Llenar Debe/Haber y Guardar data en memoria para acceso rápido
        cuentasData = ctas;
        const cmbD = $('#cmbDebe').empty().append(new Option('SELECCIONE...', '', true, true));
        const cmbH = $('#cmbHaber').empty().append(new Option('SELECCIONE...', '', true, true));
        
        ctas.forEach(c => {
            if(c.mostrarendestino == 1) cmbD.append(new Option(c.medio, c.id));
            if(c.mostrarenorigen == 1) cmbH.append(new Option(c.medio, c.id));
        });

        // Eventos Select2 con lógica VB (SelectionChangeCommitted)
        $('.select2-dark').select2({dropdownParent: $('#moduloNuevoMov'), width: '100%'});
        $('.select2-modal').select2({width: '100%'});

        $('#cmbDebe').on('select2:select', function(e) { logicDebe(e.params.data.id); });
        $('#cmbHaber').on('select2:select', function(e) { logicHaber(e.params.data.id); });
    }

    function fillCombo(sel, data, txtField) {
        const el = $(sel); el.empty().append(new Option('SELECCIONE...', '', true, true));
        if(data) data.forEach(d => el.append(new Option(d[txtField], d.id)));
    }

    // =========================================================================
    // LÓGICA DEBE (ENTRADAS)
    // =========================================================================
    async function logicDebe(idCuenta) {
        const cuenta = cuentasData.find(c => c.id == idCuenta);
        if(!cuenta) return;

        // Validaciones Tipo Cuenta
        if(cuenta.tipodecuenta === 'Cheques Emitidos') {
            Swal.fire('Error', 'No puede agregar una cuenta de Cheques Emitidos al DEBE.', 'error');
            $('#cmbDebe').val('').trigger('change');
            return;
        }

        if(cuenta.tipodecuenta === 'Cartera de Cheques' || cuenta.tipodecuenta === 'Cheques') {
            // Verificar unicidad
            if(dgvmovs.some(r => (r.tipo === 'Cartera de Cheques' || r.tipo === 'Cheques') && r.col === 'debe')) {
                Swal.fire('Error', 'No puede agregar más de una cuenta de Cartera al DEBE.', 'error');
                $('#cmbDebe').val('').trigger('change');
                return;
            }
            // Abrir Modal Nuevo Cheque
            $('#modalNuevoCheque').modal('show');
        } else if (cuenta.tipodecuenta === 'Caja de Usuarios') {
            if(await validarCaja(cuenta.puestoasociado)) {
                $('#txtImporteDebe').focus();
            } else {
                $('#cmbDebe').val('').trigger('change');
            }
        } else {
            $('#txtImporteDebe').focus();
        }
    }

    window.addChequeRecibido = function() {
        const bNom = $('#ncBan option:selected').text();
        const bId = $('#ncBan').val();
        const num = $('#ncNum').val();
        const imp = parseFloat($('#ncImp').val()) || 0;
        
        if(!bId || !num || imp <= 0) return Swal.fire('Error', 'Complete los datos del cheque', 'warning');

        dgvcheques.push({
            numero: num, importe: imp, banco: bNom, banco_id: bId, 
            f_emi: $('#ncFemi').val(), f_cob: $('#ncFcob').val(), tipo: $('#ncTipo').val()
        });

        // Calcular total y agregar/actualizar fila
        recalcularCarteraDebe();
        $('#modalNuevoCheque').modal('hide');
        // Limpiar form
        $('#ncNum').val(''); $('#ncImp').val('');
    }

    function recalcularCarteraDebe() {
        const total = dgvcheques.reduce((s, c) => s + c.importe, 0);
        const idCta = $('#cmbDebe').val();
        const cta = cuentasData.find(c => c.id == idCta);
        
        // Upsert fila en dgvmovs
        upsertMovimiento(cta, total, 'debe');
        renderTablas();
    }

    window.agregarDebeManual = function() {
        const id = $('#cmbDebe').val();
        const imp = parseFloat($('#txtImporteDebe').val()) || 0;
        if(!id || imp <= 0) return;
        
        const cta = cuentasData.find(c => c.id == id);
        // Validar que no sea cartera (ya se manejó arriba)
        if(cta.tipodecuenta === 'Cartera de Cheques') return; 

        upsertMovimiento(cta, imp, 'debe');
        $('#txtImporteDebe').val('');
        $('#cmbDebe').val('').trigger('change');
    }

    // =========================================================================
    // LÓGICA HABER (SALIDAS)
    // =========================================================================
    async function logicHaber(idCuenta) {
        const cuenta = cuentasData.find(c => c.id == idCuenta);
        if(!cuenta) return;

        if(cuenta.tipodecuenta === 'Cartera de Cheques' || cuenta.tipodecuenta === 'Cheques') {
            if(dgvmovs.some(r => (r.tipo === 'Cartera de Cheques' || r.tipo === 'Cheques') && r.col === 'haber')) {
                Swal.fire('Error', 'No puede agregar más de una cuenta de Cartera al HABER.', 'error');
                $('#cmbHaber').val('').trigger('change');
                return;
            }
            cargarCarteraDisponible(); // Abre modal
        } 
        else if(cuenta.tipodecuenta === 'Cheques Emitidos') {
            if(dgvmovs.some(r => r.tipo === 'Cheques Emitidos' && r.col === 'haber')) {
                Swal.fire('Error', 'No puede agregar más de una cuenta de Cheques Emitidos.', 'error');
                $('#cmbHaber').val('').trigger('change');
                return;
            }
            $('#modalChequePropio').modal('show');
        } 
        else if (cuenta.tipodecuenta === 'Caja de Usuarios') {
            if(await validarCaja(cuenta.puestoasociado)) {
                $('#txtImporteHaber').focus();
            } else {
                $('#cmbHaber').val('').trigger('change');
            }
        } 
        else {
            $('#txtImporteHaber').focus();
        }
    }

    async function cargarCarteraDisponible() {
        const res = await fetch('api_tesoreria_movimientos.php?action=get_cartera_disponible', {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json());
        const tb = $('#tbodyCarteraDisponible'); tb.empty();
        res.forEach(c => {
            tb.append(`<tr>
                <td><input type="checkbox" class="chk-endoso" value="${c.id}" data-imp="${c.importe}" data-num="${c.numero}" data-ban="${c.banco_nombre}" data-cli="${c.cliente_nom}"></td>
                <td>${c.numero}</td><td>${c.banco_nombre}</td><td>$ ${parseFloat(c.importe).toLocaleString()}</td><td>${c.fechavencimiento}</td><td>${c.cliente_nom}</td>
            </tr>`);
        });
        $('#modalBuscarCheques').modal('show');
        
        // Listener para sumar selección
        $('.chk-endoso').on('change', function() {
            let t = 0;
            $('.chk-endoso:checked').each(function() { t += parseFloat($(this).data('imp')); });
            $('#lblTotalEndosoSel').text('Total: $ ' + t.toLocaleString('es-AR', {minimumFractionDigits:2}));
        });
    }

    window.addChequesEndosados = function() {
        const checked = $('.chk-endoso:checked');
        if(checked.length === 0) return;

        checked.each(function() {
            const el = $(this);
            dgvendosos.push({
                id: el.val(), numero: el.data('num'), importe: parseFloat(el.data('imp')), 
                banco: el.data('ban'), cliente: el.data('cli')
            });
        });

        recalcularCarteraHaber();
        $('#modalBuscarCheques').modal('hide');
    }

    function recalcularCarteraHaber() {
        const total = dgvendosos.reduce((s, c) => s + c.importe, 0);
        const idCta = $('#cmbHaber').val();
        const cta = cuentasData.find(c => c.id == idCta);
        upsertMovimiento(cta, total, 'haber');
        renderTablas();
    }

    window.addChequePropio = function() {
        const ctaId = $('#cpCtaBan').val();
        const num = $('#cpNum').val();
        const imp = parseFloat($('#cpImp').val()) || 0;
        
        if(!ctaId || !num || imp <= 0) return Swal.fire('Error', 'Datos incompletos', 'warning');

        dgvchequespropios.push({
            cta_ban_id: ctaId, cta_ban_nom: $('#cpCtaBan option:selected').text(),
            numero: num, importe: imp, f_emi: $('#cpFemi').val(), f_venc: $('#cpFvenc').val()
        });

        const total = dgvchequespropios.reduce((s, c) => s + c.importe, 0);
        const idCta = $('#cmbHaber').val();
        const cta = cuentasData.find(c => c.id == idCta);
        upsertMovimiento(cta, total, 'haber');
        
        $('#modalChequePropio').modal('hide');
        $('#cpNum').val(''); $('#cpImp').val('');
        renderTablas();
    }

    window.agregarHaberManual = function() {
        const id = $('#cmbHaber').val();
        const imp = parseFloat($('#txtImporteHaber').val()) || 0;
        if(!id || imp <= 0) return;
        const cta = cuentasData.find(c => c.id == id);
        // Validar especiales
        if(cta.tipodecuenta.includes('Cheque') || cta.tipodecuenta.includes('Cartera')) return;
        upsertMovimiento(cta, imp, 'haber');
        $('#txtImporteHaber').val('');
        $('#cmbHaber').val('').trigger('change');
    }

    // =========================================================================
    // CORE: MANEJO DEL ASIENTO
    // =========================================================================
    function upsertMovimiento(cta, importe, columna) {
        // Verificar si la cuenta ya existe en la grilla para evitar duplicados (regla general)
        // EXCEPCIÓN: Si es Cartera/Emitidos, se actualiza el monto, no se agrega fila nueva si ya existe
        const idx = dgvmovs.findIndex(r => r.idcuenta == cta.id);
        
        if(idx >= 0) {
            // Ya existe, actualizamos si es especial, sino error
            if(cta.tipodecuenta.includes('Cheque') || cta.tipodecuenta.includes('Cartera')) {
                if(columna === 'debe') dgvmovs[idx].debe = importe;
                else dgvmovs[idx].haber = importe;
                
                // Si baja a 0, eliminar
                if(importe === 0) dgvmovs.splice(idx, 1);
            } else {
                Swal.fire('Error', 'Esta cuenta ya fue agregada al asiento.', 'error');
            }
        } else {
            if(importe > 0) {
                dgvmovs.push({
                    idcuenta: cta.id, medio: cta.medio, tipo: cta.tipodecuenta,
                    debe: (columna==='debe' ? importe : 0), haber: (columna==='haber' ? importe : 0),
                    col: columna, // Para rastrear origen visual
                    ...cta // Hereda props (cuentacontable, etc)
                });
            }
        }
        renderAsiento();
    }

    function renderAsiento() {
        const tb = $('#tbodyAsiento'); tb.empty();
        let td=0, th=0;
        dgvmovs.forEach((r, i) => {
            td += r.debe; th += r.haber;
            tb.append(`<tr>
                <td class="ps-3 py-2">${r.medio} <span class="badge bg-secondary ms-2" style="font-size:0.7em">${r.tipo}</span></td>
                <td class="text-end text-info">$ ${r.debe.toLocaleString('es-AR', {minimumFractionDigits:2})}</td>
                <td class="text-end text-warning">$ ${r.haber.toLocaleString('es-AR', {minimumFractionDigits:2})}</td>
                <td class="text-center"><button class="btn btn-xs btn-outline-danger border-0" onclick="quitarFila(${i})"><i class="fas fa-times"></i></button></td>
            </tr>`);
        });
        $('#lblTotalDebe').text('$ '+td.toLocaleString('es-AR', {minimumFractionDigits:2}));
        $('#lblTotalHaber').text('$ '+th.toLocaleString('es-AR', {minimumFractionDigits:2}));
        const saldo = td - th;
        $('#lblSaldo').text('$ '+saldo.toLocaleString('es-AR', {minimumFractionDigits:2}))
                      .removeClass('text-accent text-danger')
                      .addClass(Math.abs(saldo) < 0.01 ? 'text-accent' : 'text-danger');
    }

    window.quitarFila = function(idx) {
        const row = dgvmovs[idx];
        // Si borro fila de cheques, debo borrar las listas auxiliares (Confirmación VB)
        if(row.tipo === 'Cartera de Cheques' && row.col === 'debe') {
            if(!confirm('Esto eliminará todos los cheques recibidos cargados. ¿Continuar?')) return;
            dgvcheques = []; renderTablas();
        }
        if(row.tipo === 'Cartera de Cheques' && row.col === 'haber') {
            if(!confirm('Esto quitará los endosos seleccionados. ¿Continuar?')) return;
            dgvendosos = []; renderTablas();
        }
        if(row.tipo === 'Cheques Emitidos') {
            if(!confirm('Esto eliminará los cheques propios emitidos. ¿Continuar?')) return;
            dgvchequespropios = []; renderTablas();
        }
        dgvmovs.splice(idx, 1);
        renderAsiento();
    }

    // =========================================================================
    // GRILLAS AUXILIARES Y ELIMINACIÓN INDIVIDUAL
    // =========================================================================
    function renderTablas() {
        // Recibidos
        $('#gridRecibidos').html(dgvcheques.map((c,i) => `<tr>
            <td>${c.numero}</td><td>${c.banco}</td><td>${c.f_cob}</td>
            <td class="text-end">$ ${c.importe.toLocaleString()}</td>
            <td><button class="btn btn-xs text-danger" onclick="delRecibido(${i})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join(''));
        $('#cntRec').text(dgvcheques.length);

        // Endosos
        $('#gridEndosos').html(dgvendosos.map((c,i) => `<tr>
            <td>${c.numero}</td><td>${c.banco}</td><td>${c.cliente}</td>
            <td class="text-end">$ ${c.importe.toLocaleString()}</td>
            <td><button class="btn btn-xs text-danger" onclick="delEndoso(${i})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join(''));
        $('#cntEnd').text(dgvendosos.length);

        // Propios
        $('#gridPropios').html(dgvchequespropios.map((c,i) => `<tr>
            <td>${c.numero}</td><td>${c.cta_ban_nom}</td><td>${c.f_venc}</td>
            <td class="text-end">$ ${c.importe.toLocaleString()}</td>
            <td><button class="btn btn-xs text-danger" onclick="delPropio(${i})"><i class="fas fa-trash"></i></button></td>
        </tr>`).join(''));
        $('#cntPro').text(dgvchequespropios.length);
    }

    window.delRecibido = (i) => { dgvcheques.splice(i,1); recalcularCarteraDebe(); };
    window.delEndoso = (i) => { dgvendosos.splice(i,1); recalcularCarteraHaber(); };
    window.delPropio = (i) => { dgvchequespropios.splice(i,1); const idCta = $('#cmbHaber').val(); const cta = cuentasData.find(c => c.id == idCta); const t = dgvchequespropios.reduce((s,c)=>s+c.importe,0); upsertMovimiento(cta, t, 'haber'); renderTablas(); };

    // =========================================================================
    // UTILIDADES Y GRABADO
    // =========================================================================
    async function validarCaja(puestoId) {
        // Validar si el usuario tiene abierta la caja de ese puesto
        const res = await fetch(`api_tesoreria_movimientos.php?action=check_caja&caja_id=${puestoId}`, {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r=>r.json());
        if(!res.success) {
            Swal.fire('Caja Cerrada', res.error, 'error');
            return false;
        }
        // Validar fecha hoy
        if($('#txtFecha').val() !== '<?php echo $hoy; ?>') {
            Swal.fire('Atención', 'Para movimientos de caja de usuario la fecha debe ser HOY.', 'warning');
            return false;
        }
        return true;
    }

    window.grabar = async function() {
        // 1. Validaciones básicas
        if(dgvmovs.length === 0) return Swal.fire('Error', 'No hay movimientos cargados', 'error');
        if(!$('#txtObservaciones').val()) return Swal.fire('Atención', 'Falta la observación', 'warning');
        
        // 2. Validación Saldo
        const td = dgvmovs.reduce((s,r)=>s+r.debe,0);
        const th = dgvmovs.reduce((s,r)=>s+r.haber,0);
        if(Math.abs(td - th) > 0.01) return Swal.fire('Error', 'El asiento no balancea (Debe != Haber)', 'error');

        // 3. Validaciones Entidades (Regla VB.NET)
        if(dgvcheques.length > 0 && !$('#cmbCliente').val()) return Swal.fire('Atención', 'Seleccione CLIENTE para los cheques recibidos', 'warning');
        if(dgvendosos.length > 0 && !$('#cmbProveedor').val()) return Swal.fire('Atención', 'Seleccione PROVEEDOR para los endosos', 'warning');
        if(dgvchequespropios.length > 0 && !$('#cmbProveedor').val()) return Swal.fire('Atención', 'Seleccione PROVEEDOR para los cheques emitidos', 'warning');

        // 4. Enviar
        Swal.fire({title:'Grabando...', didOpen:()=>Swal.showLoading(), background:'#001020', color:'#fff'});
        
        const payload = {
            fecha: $('#txtFecha').val(),
            obs: $('#txtObservaciones').val(),
            prov_id: $('#cmbProveedor').val(),
            cli_id: $('#cmbCliente').val(),
            rows: dgvmovs,
            cheques_rec: dgvcheques,
            cheques_end: dgvendosos,
            cheques_pro: dgvchequespropios
        };

        const res = await fetch('api_tesoreria_movimientos.php?action=save', {
            method: 'POST', 
            headers: {'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest'},
            body: JSON.stringify(payload)
        }).then(r=>r.json());

        if(res.success) {
            Swal.fire('Éxito', 'Movimiento registrado correctamente', 'success');
            limpiarPantalla();
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    }

    window.limpiarPantalla = function() {
        dgvmovs = []; dgvcheques = []; dgvendosos = []; dgvchequespropios = [];
        $('#txtObservaciones').val('');
        $('#cmbProveedor, #cmbCliente, #cmbDebe, #cmbHaber').val('').trigger('change');
        $('#txtImporteDebe, #txtImporteHaber').val('');
        renderAsiento(); renderTablas();
    }

    init();
})();
</script>