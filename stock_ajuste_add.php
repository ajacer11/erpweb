<?php
/**
 * stock_ajuste_add.php - EDITOR DE AJUSTE (NUEVO O EXISTENTE)
 * Incluye búsqueda Select2 y eliminación de ítems.
 */

 
require_once 'check_access.php';
$id = $_GET['id'] ?? null;
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <button class="btn btn-outline-secondary btn-sm mb-2" onclick="loadModule('stock_ajuste_grupo.php')">
                <i class="fas fa-arrow-left me-1"></i> VOLVER AL LISTADO
            </button>
            <h4 class="text-white mb-0"><?= $id ? "PROCESAR AJUSTE #$id" : "NUEVO AJUSTE GRUPAL" ?></h4>
            <small class="text-accent opacity-75" id="lbl-estado">ESTADO: ALTA</small>
        </div>
        <div id="panel-botones" class="d-flex gap-2"></div>
    </div>

    <!-- PANEL DE FILTROS Y BÚSQUEDA (Solo visible si no está finalizado) -->
    <div class="card card-filtros mb-3" id="card-filtros-productos">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label-custom">NOMBRE DEL GRUPO</label>
                    <input type="text" id="txt-nombre-grupo" class="form-control" placeholder="EJ: INVENTARIO DEPÓSITO">
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">RUBRO</label>
                    <select class="form-select select2-simple" id="f-rubro"><option value="">TODOS</option></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">MARCA</label>
                    <select class="form-select select2-simple" id="f-marca"><option value="">TODAS</option></select>
                </div>
                <div class="col-md-2">
                    <label class="form-label-custom">FAMILIA</label>
                    <select class="form-select select2-simple" id="f-familia"><option value="">TODAS</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">PROVEEDOR</label>
                    <select class="form-select select2-simple" id="f-proveedor"><option value="">TODOS</option></select>
                </div>
                
                <div class="col-md-12 mt-3 border-top border-secondary pt-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label-custom">BÚSQUEDA RÁPIDA (DESCRIPCIÓN / LUPA)</label>
                            <select id="select-prod-search" class="form-control"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label-custom">CÓDIGO MANUAL</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-white"><i class="fas fa-barcode"></i></span>
                                <input type="text" id="txt-add-indiv" class="form-control" placeholder="ESCANEAR CÓDIGO">
                            </div>
                        </div>
                        <div class="col-md-5 d-flex justify-content-end align-items-end">
                            <button class="btn btn-info fw-bold" id="btn-aplicar-filtros">
                                <i class="fas fa-filter me-2"></i>AGREGAR POR FILTROS ACUMULATIVOS
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE PRODUCTOS -->
    <div class="card card-filtros p-0 overflow-hidden shadow-sm" style="position: relative;">
        <!-- LOADING OVERLAY -->
        <div id="loading-table" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 100; justify-content: center; align-items: center;">
            <div class="text-center">
                <div class="spinner-border text-info" role="status"></div>
                <div class="text-info mt-2 small">PROCESANDO...</div>
            </div>
        </div>

        <div class="table-responsive" style="max-height: 50vh; min-height: 200px;">
            <table class="table table-hover align-middle mb-0">
                <thead class="sticky-top">
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th>CÓDIGO</th>
                        <th>DESCRIPCIÓN</th>
                        <th class="text-end">STOCK TABLA</th>
                        <th class="text-end">STOCK GESTIÓN</th>
                        <th class="text-center" style="width: 130px;">CONTEO</th>
                        <th class="text-end">DIF.</th>
                        <th class="text-center" style="width: 50px;"></th>
                    </tr>
                </thead>
                <tbody id="tbody-items"></tbody>
            </table>
        </div>
        <div class="bg-dark p-2 d-flex justify-content-between px-3 border-top border-secondary">
            <small class="text-muted">PRODUCTOS: <span id="span-cant" class="text-white">0</span></small>
            <small class="text-muted">TOTAL DIFERENCIA: <span id="span-dif" class="text-accent fw-bold">0.00</span></small>
        </div>
    </div>

    <div class="row mt-3 g-3">
        <div class="col-md-9">
            <label class="form-label-custom">OBSERVACIONES DEL AJUSTE</label>
            <textarea id="txt-observacion" class="form-control" rows="2" placeholder="Escriba aquí el motivo del ajuste masivo..."></textarea>
        </div>
        <div class="col-md-3 d-none" id="wrap-cmb-deposito-grupo">
            <label class="form-label-custom">DEPÓSITO</label>
            <select id="cmb-deposito-grupo" class="form-select"></select>
        </div>
    </div>
</div>

<script>
(function() {
    const idAjuste = "<?= $id ?>";
    let items = [];
    let estado = "ALTA";

    async function init() {
        await cargarCombos();
        await cargarDeposito();
        configSelect2Busqueda();

        if (idAjuste) {
            await cargarAjusteExistente();
        } else {
            $('#panel-botones').html('<button class="btn btn-success fw-bold px-4 rounded-pill" id="btn-iniciar"><i class="fas fa-play me-2"></i>INICIAR CONGELAMIENTO</button>');
            $('#btn-iniciar').click(iniciarGrupo);
        }

        $('#btn-aplicar-filtros').click(cargarPorFiltros);
        $('#txt-add-indiv').on('keydown', e => { if (e.key === 'Enter') agregarIndividual($(e.target).val()); });
    }

    // Depósito del lote: solo visible si el tenant tiene multidepósito activado.
    async function cargarDeposito() {
        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            const estadoGlobal = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
            if (!estadoGlobal.habilitado) return;

            const [permitidos, miEstado] = await Promise.all([
                fetch('api_depositos.php?action=list_permitidos', { headers }).then(r => r.json()),
                fetch('api_depositos.php?action=mi_estado', { headers }).then(r => r.json())
            ]);
            const cmb = $('#cmb-deposito-grupo').empty();
            (permitidos.data || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));
            if (miEstado.id_deposito) cmb.val(miEstado.id_deposito);
            $('#wrap-cmb-deposito-grupo').removeClass('d-none');
        } catch (e) { console.error('No se pudo cargar el depósito', e); }
    }

    /**
     * Configuración del buscador avanzado Select2
     */
    function configSelect2Busqueda() {
        $('#select-prod-search').select2({
            placeholder: 'BUSCAR PRODUCTO POR NOMBRE O LUPA...',
            minimumInputLength: 3,
            ajax: {
                url: 'api_productos.php?action=search', 
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) {
                    if (!data.success) return { results: [] };
                    return { results: data.data.map(p => ({ id: p.codigo, text: `${p.codigo} - ${p.descripcion}`, raw: p })) };
                }
            }
        }).on('select2:select', function(e) {
            const data = e.params.data.raw;
            procesarProductoEncontrado(data);
            $(this).val(null).trigger('change');
        });
    }

    async function cargarCombos() {
        const endpoints = [
            { id: '#f-rubro', action: 'get_rubros', key: 'rubro', label: 'rubro' },
            { id: '#f-marca', action: 'get_marcas', key: 'id', label: 'marca' },
            { id: '#f-familia', action: 'get_familias', key: 'id', label: 'nombre' },
            { id: '#f-proveedor', action: 'get_proveedores', key: 'id', label: 'razon' }
        ];

        for (const item of endpoints) {
            const resp = await fetch(`api_helpers.php?action=${item.action}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            if (res.success) res.data.forEach(d => $(item.id).append(new Option(d[item.label], d[item.key])));
        }
    }

    async function cargarPorFiltros() {
        if (estado !== 'ALTA' && idAjuste) return;
        $('#loading-table').css('display', 'flex');
        const q = new URLSearchParams({
            action: 'get_filtered_prods',
            rubro: $('#f-rubro').val(),
            marca: $('#f-marca').val(),
            familia: $('#f-familia').val(),
            proveedor: $('#f-proveedor').val()
        });

        try {
            const resp = await fetch(`api_stock_grupo.php?${q}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            if (res.success) {
                res.data.forEach(p => {
                    if (!items.find(i => i.codigo === p.codigo)) {
                        // Normalizamos claves para evitar problemas en el backend
                        items.push({
                            codigo: p.codigo,
                            descripcion: p.descripcion,
                            stock: parseFloat(p.stock !== undefined ? p.stock : p.Stock || 0),
                            stock_gestion: parseFloat(p.stock_gestion !== undefined ? p.stock_gestion : 0),
                            rubro: p.rubro,
                            sinivacosto: p.sinivacosto || 0
                        });
                    }
                });
                renderTabla();
            }
        } finally {
            $('#loading-table').hide();
        }
    }

    async function agregarIndividual(code) {
        if (!code) return;
        $('#loading-table').css('display', 'flex');
        try {
            const resRef = await fetch(`api_referencias.php?action=resolve&code=${encodeURIComponent(code)}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const dataRef = await resRef.json();
            const real = dataRef.data.codigo_real;

            const resp = await fetch(`api_stock_ajuste.php?action=get_prod&code=${encodeURIComponent(real)}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            if (res.success) {
                procesarProductoEncontrado(res.data);
                $('#txt-add-indiv').val('').focus();
            } else {
                Swal.fire('Error', 'Producto no encontrado', 'error');
            }
        } finally {
            $('#loading-table').hide();
        }
    }

    function procesarProductoEncontrado(data) {
        if (items.find(i => i.codigo === data.codigo)) {
            return Swal.fire({icon:'info', title:'Ya está en la lista', toast:true, position:'top-end', timer:2000, showConfirmButton:false});
        }
        // Aseguramos que 'stock' exista siempre antes de pushear
        items.push({
            codigo: data.codigo,
            descripcion: data.descripcion,
            stock: parseFloat(data.stock !== undefined ? data.stock : (data.Stock !== undefined ? data.Stock : 0)),
            stock_gestion: parseFloat(data.stock_gestion !== undefined ? data.stock_gestion : 0),
            rubro: data.rubro,
            sinivacosto: data.sinivacosto || 0
        });
        renderTabla();
    }

    function renderTabla() {
        const tbody = $('#tbody-items').empty();
        const canDelete = (estado === 'ALTA' || estado === 'INICIADO');
        
        // REGLA: Solo permitir edición si el estado es INICIADO. 
        // Si es ALTA (antes de congelar) o FINALIZADO, es readonly.
        const readOnly = (estado !== 'INICIADO') ? 'readonly' : '';
        
        items.forEach((it, idx) => {
            const stockActual = parseFloat(it.stock || it.stockportabla || 0);
            const conteo = parseFloat(it.ingresan !== undefined ? it.ingresan : stockActual);
            const dif = conteo - stockActual;

            tbody.append(`
                <tr data-index="${idx}">
                    <td class="text-muted small">${idx+1}</td>
                    <td class="text-white fw-bold">${it.codigo}</td>
                    <td><div class="text-truncate" style="max-width: 300px;"><small>${it.descripcion}</small></div></td>
                    <td class="text-end text-muted">${stockActual.toFixed(2)}</td>
                    <td class="text-end text-accent">${parseFloat(it.stock_gestion || it.stockporingresos || 0).toFixed(2)}</td>
                    <td><input type="number" class="form-control form-control-sm text-center inp-conteo" value="${conteo}" ${readOnly} data-index="${idx}" step="0.01"></td>
                    <td class="text-end fw-bold dif-cell ${dif < 0 ? 'text-danger' : (dif > 0 ? 'text-success' : 'text-muted')}">${dif.toFixed(2)}</td>
                    <td class="text-center">
                        ${canDelete ? `<button class="btn btn-link text-danger p-0 btn-del-row" data-index="${idx}"><i class="fas fa-trash-alt"></i></button>` : ''}
                    </td>
                </tr>
            `);
        });

        $('.inp-conteo').on('input', function() {
            const i = $(this).data('index');
            const val = parseFloat($(this).val()) || 0;
            items[i].ingresan = val;
            const stockActual = parseFloat(items[i].stock || items[i].stockportabla || 0);
            const dif = val - stockActual;
            $(this).closest('tr').find('.dif-cell').text(dif.toFixed(2))
                .removeClass('text-danger text-success text-muted')
                .addClass(dif < 0 ? 'text-danger' : (dif > 0 ? 'text-success' : 'text-muted'));
            calcTotales();
        });

        $('.btn-del-row').click(function() {
            const idx = $(this).data('index');
            items.splice(idx, 1);
            renderTabla();
        });

        calcTotales();
    }

    function calcTotales() {
        $('#span-cant').text(items.length);
        let totalDif = 0;
        items.forEach(it => {
            const stockActual = parseFloat(it.stock || it.stockportabla || 0);
            const conteo = parseFloat(it.ingresan !== undefined ? it.ingresan : stockActual);
            totalDif += (conteo - stockActual);
        });
        $('#span-dif').text(totalDif.toFixed(2));
    }

    async function iniciarGrupo() {
        const nombre = $('#txt-nombre-grupo').val().trim();
        if (!nombre || items.length === 0) return Swal.fire('Atención', 'Se requiere un nombre de grupo y al menos un producto.', 'warning');

        // Normalizamos los items antes de enviar para asegurar que 'stock' llegue al PHP
        const itemsParaEnviar = items.map(it => ({
            codigo: it.codigo,
            stock: it.stock,
            stock_gestion: it.stock_gestion,
            rubro: it.rubro,
            sinivacosto: it.sinivacosto
        }));

        const resp = await fetch('api_stock_grupo.php?action=init_group', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify({ nombre, items: itemsParaEnviar })
        });
        const res = await resp.json();
        if (res.success) loadModule(`stock_ajuste_add.php?id=${res.id}`);
        else Swal.fire('Error', res.error, 'error');
    }

    async function cargarAjusteExistente() {
        $('#loading-table').css('display', 'flex');
        try {
            const resp = await fetch(`api_stock_grupo.php?action=get&id=${idAjuste}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();
            if (res.success) {
                estado = res.cabecera.estado;
                items = res.detalle;
                $('#lbl-estado').text(`ESTADO: ${estado}`).addClass(estado === 'FINALIZADO' ? 'text-success' : 'text-info');
                $('#txt-nombre-grupo').val(res.cabecera.nombregrupo).prop('disabled', true);
                
                if (estado !== 'INICIADO') {
                    $('#card-filtros-productos').hide();
                    $('#txt-observacion').prop('disabled', true);
                }

                $('#txt-observacion').val(res.cabecera.observacion);
                renderTabla();
                renderBotonesEstado();
            }
        } finally {
            $('#loading-table').hide();
        }
    }

    function renderBotonesEstado() {
        const container = $('#panel-botones').empty();
        if (estado === 'INICIADO') {
            container.append(`
                <button class="btn btn-outline-danger" id="btn-cancelar"><i class="fas fa-times me-1"></i> CANCELAR</button>
                <button class="btn btn-warning" id="btn-guardar"><i class="fas fa-save me-1"></i> GUARDAR CONTEO</button>
                <button class="btn btn-success fw-bold px-4" id="btn-finalizar"><i class="fas fa-check-double me-1"></i> FINALIZAR AJUSTE</button>
            `);
            $('#btn-cancelar').click(() => procesarAccion('cancel'));
            $('#btn-guardar').click(() => procesarAccion('save_conteo'));
            $('#btn-finalizar').click(() => procesarAccion('finalize'));
        }
    }

    async function procesarAccion(tipo) {
        let confirmTxt = "¿Desea continuar?";
        if (tipo === 'finalize') confirmTxt = "Se impactará el stock de forma definitiva. ¿Continuar?";
        
        const result = await Swal.fire({
            title: 'Confirmar',
            text: confirmTxt,
            icon: 'question',
            showCancelButton: true
        });

        if (!result.isConfirmed) return;

        const data = {
            id: idAjuste,
            observacion: $('#txt-observacion').val(),
            id_deposito: $('#cmb-deposito-grupo').val() || null,
            items: items.map(i => ({ codigo: i.codigo, conteo: i.ingresan !== undefined ? i.ingresan : (i.stockportabla || i.stock) }))
        };

        const resp = await fetch(`api_stock_grupo.php?action=${tipo}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
            body: JSON.stringify(data)
        });
        const res = await resp.json();
        if (res.success) {
            Swal.fire('Éxito', res.message, 'success');
            if (tipo === 'save_conteo') cargarAjusteExistente(); else loadModule('stock_ajuste_grupo.php');
        } else {
            Swal.fire('Error', res.error, 'error');
        }
    }

    init();
})();
</script>