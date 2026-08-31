<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
require_once 'permisos_helpers.php';

// Verificamos si el usuario es un vendedor restringido
$esVendedorRestringido = esVendedorRestringido();
$idVendedorAsignado = idVendedorRestringido();
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">CUENTAS POR VENDEDOR</h4>
            <small class="text-accent opacity-75">Saldos de clientes agrupados por cartera de ventas</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success border-0" id="btnExportExcel">
                <i class="fa-solid fa-file-excel me-1"></i> EXCEL
            </button>
            <button class="btn btn-outline-info border-0" id="btnImprimir">
                <i class="fa-solid fa-print me-1"></i> IMPRIMIR
            </button>
            <button class="btn btn-danger rounded-circle shadow" onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </div>

    <!-- PANEL DE FILTROS -->
    <div class="card card-filtros mb-4 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <!-- Filtro Vendedor -->
                <div class="col-md-4 border-end border-secondary border-opacity-25">
                    <label class="form-label-custom">VENDEDOR</label>
                    <select class="form-select select2-simple" id="cmbVendedor"></select>
                </div>

                <!-- Opciones de Visualización -->
                <div class="col-md-4 border-end border-secondary border-opacity-25">
                    <label class="form-label-custom">MODO DE REPORTE</label>
                    <div class="d-flex gap-4 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modoReporte" id="rbDetallado" checked>
                            <label class="form-check-label small" for="rbDetallado">Detalle por Cliente</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="modoReporte" id="rbResumen">
                            <label class="form-check-label small" for="rbResumen">Total por Vendedor</label>
                        </div>
                    </div>
                </div>

                <!-- Totales y Acción -->
                <div class="col-md-4 d-flex justify-content-between align-items-center">
                    <div class="px-3">
                        <small class="text-muted d-block uppercase" style="font-size: 10px;">TOTAL DEUDA:</small>
                        <span id="lblTotalGeneral" class="text-accent fw-bold fs-4">$ 0.00</span>
                    </div>
                    <button class="btn btn-info fw-bold px-4" id="btnActualizar">
                        <i class="fa-solid fa-sync-alt me-2"></i>ACTUALIZAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="table-responsive shadow-sm" style="max-height: 60vh;">
        <table class="table table-hover align-middle" id="tblSaldos">
            <thead>
                <tr id="tblHeader">
                    <!-- Dinámico -->
                </tr>
            </thead>
            <tbody id="tblBody">
                <tr>
                    <td colspan="5" class="text-center py-5 opacity-50">Seleccione un vendedor y presione ACTUALIZAR</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function() {
    let dataGlobal = [];
    const esRestringido = <?= $esVendedorRestringido ? 'true' : 'false' ?>;
    const idRestringido = <?= (int)$idVendedorAsignado ?>;

    async function init() {
        // Inicializar Vendedores
        $('#cmbVendedor').select2({
            dropdownParent: $('#main-content'),
            placeholder: 'SELECCIONE VENDEDOR...',
            ajax: {
                url: 'api_ctas_por_vendedor.php?action=get_vendedores',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                dataType: 'json',
                processResults: data => {
                    const results = data.map(v => ({ id: v.id, text: v.nombre }));
                    if (!esRestringido) results.unshift({ id: '0', text: '--- TODOS LOS VENDEDORES ---' });
                    return { results };
                }
            }
        });

        if (esRestringido) {
            // Cargar automáticamente el vendedor asignado
            const option = new Option("CARGANDO...", idRestringido, true, true);
            $('#cmbVendedor').append(option).trigger('change').prop('disabled', true);
            buscarDatos();
        }

        // Eventos
        $('#btnActualizar').on('click', buscarDatos);
        $('#cmbVendedor, input[name="modoReporte"]').on('change', buscarDatos);
        $('#btnExportExcel').on('click', exportarExcel);
        $('#btnImprimir').on('click', imprimirReporte);
    }

    async function buscarDatos() {
        const vendedorId = $('#cmbVendedor').val();
        if (vendedorId === null && !esRestringido) return;

        const params = new URLSearchParams({
            action: 'get_data',
            vendedor_id: vendedorId || 0,
            resumen: $('#rbResumen').is(':checked') ? 1 : 0
        });

        try {
            const resp = await fetch(`api_ctas_por_vendedor.php?${params}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            
            if(!res.success) throw new Error(res.error);
            
            dataGlobal = res.data;
            renderTabla();
            actualizarTotales(res.total_general);
            
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    }

    function renderTabla() {
        const header = $('#tblHeader').empty();
        const body = $('#tblBody').empty();
        const esResumen = $('#rbResumen').is(':checked');

        if (dataGlobal.length === 0) {
            body.append(`<tr><td colspan="10" class="text-center py-5 opacity-50">SIN SALDOS PENDIENTES</td></tr>`);
            return;
        }

        if (esResumen) {
            header.append('<th style="width:100px;">ID VEN.</th><th>VENDEDOR</th><th class="text-end">SALDO TOTAL CARTERA</th>');
            dataGlobal.forEach(row => {
                body.append(`
                    <tr>
                        <td class="text-white-50">${row.vendedor_id}</td>
                        <td class="fw-bold">${row.Vendedor}</td>
                        <td class="text-end fw-bold text-accent">${parseFloat(row.Saldo).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `);
            });
        } else {
            header.append('<th style="width:80px;">NRO.</th><th>CLIENTE</th><th>VENDEDOR</th><th class="text-end">SALDO</th>');
            dataGlobal.forEach(row => {
                body.append(`
                    <tr>
                        <td class="text-white-50">${row.id_cliente}</td>
                        <td class="fw-bold text-white">${row.Cliente}</td>
                        <td><small class="text-muted">${row.Vendedor}</small></td>
                        <td class="text-end fw-bold ${row.Saldo < 0 ? 'text-danger' : 'text-accent'}">${parseFloat(row.Saldo).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                    </tr>
                `);
            });
        }
    }

    function actualizarTotales(total) {
        $('#lblTotalGeneral').text('$ ' + parseFloat(total).toLocaleString('es-AR', {minimumFractionDigits: 2}));
    }

    function exportarExcel() {
        if (dataGlobal.length === 0) return Swal.fire('Atención', 'No hay datos para exportar.', 'warning');
        const params = new URLSearchParams({
            vendedor_id: $('#cmbVendedor').val() || 0,
            resumen: $('#rbResumen').is(':checked') ? 1 : 0
        });
        window.location.href = `exportar_ctas_por_vendedor_excel.php?${params.toString()}`;
    }

    function imprimirReporte() {
        if (dataGlobal.length === 0) return;
        const printWin = window.open('', '_blank');
        const esResumen = $('#rbResumen').is(':checked');
        const titulo = esResumen ? 'RESUMEN DE SALDOS POR VENDEDOR' : 'DETALLE DE SALDOS POR CARTERA';
        
        let html = `<html><head><title>REPORTE</title><style>
            body { font-family: sans-serif; font-size: 11px; padding: 20px; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background: #eee; padding: 8px; border: 1px solid #ddd; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #eee; }
            .text-end { text-align: right; }
            .header { text-align: center; margin-bottom: 30px; }
        </style></head><body>
        <div class="header">
            <h2>WEST IT ERP - ${titulo}</h2>
            <p>Generado: ${new Date().toLocaleString()}</p>
        </div>
        <table><thead><tr>`;

        if (esResumen) {
            html += "<th>VENDEDOR</th><th class='text-end'>SALDO TOTAL</th></tr></thead><tbody>";
            dataGlobal.forEach(r => html += `<tr><td>${r.Vendedor}</td><td class='text-end'><b>${parseFloat(r.Saldo).toFixed(2)}</b></td></tr>`);
        } else {
            html += "<th>CLIENTE</th><th>VENDEDOR</th><th class='text-end'>SALDO</th></tr></thead><tbody>";
            dataGlobal.forEach(r => html += `<tr><td>${r.Cliente}</td><td>${r.Vendedor}</td><td class='text-end'><b>${parseFloat(r.Saldo).toFixed(2)}</b></td></tr>`);
        }

        html += "</tbody></table></body></html>";
        printWin.document.write(html);
        printWin.document.close();
        printWin.print();
    }

    init();
})();
</script>