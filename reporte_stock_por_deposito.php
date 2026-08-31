<?php
/**
 * reporte_stock_por_deposito.php - STOCK CONSOLIDADO POR DEPÓSITO
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloReporteStockDeposito">
    <div class="mb-3">
        <h4 class="text-white mb-0"><i class="fa-solid fa-boxes-stacked text-accent me-2"></i>STOCK POR DEPÓSITO</h4>
        <small class="text-accent opacity-75">Vista consolidada del stock vigente en cada depósito</small>
    </div>

    <div id="avisoNoHabilitado" class="alert alert-warning d-none">
        El multidepósito no está activado en este sistema.
    </div>

    <div id="wrapReporte" class="d-none">
        <div class="card card-filtros mb-3 shadow-sm">
            <div class="card-body p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label-custom">DEPÓSITO</label>
                        <select id="cmbFiltroDeposito" class="form-select">
                            <option value="">TODOS</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label-custom">BUSCAR PRODUCTO</label>
                        <input type="text" id="txtBuscar" class="form-control" placeholder="Código o descripción...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-secondary w-100 fw-bold" id="btnBuscar"><i class="fas fa-search me-2"></i>BUSCAR</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 65vh;">
            <table class="table table-dark table-hover align-middle mb-0">
                <thead class="sticky-top bg-dark">
                    <tr class="text-accent border-bottom border-secondary">
                        <th>CÓDIGO</th>
                        <th>PRODUCTO</th>
                        <th>DEPÓSITO</th>
                        <th class="text-end">CANTIDAD EN DEPÓSITO</th>
                        <th class="text-end">TOTAL PRODUCTO (TODOS LOS DEPÓSITOS)</th>
                    </tr>
                </thead>
                <tbody id="tbodyReporte">
                    <tr><td colspan="5" class="text-center py-4 text-muted fst-italic">CARGANDO...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function() {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        const resp = await fetch('api_reporte_stock_por_deposito.php?action=list', { headers });
        const res = await resp.json();

        if (!res.habilitado) {
            document.getElementById('avisoNoHabilitado').classList.remove('d-none');
            return;
        }
        document.getElementById('wrapReporte').classList.remove('d-none');

        const cmb = $('#cmbFiltroDeposito');
        (res.depositos || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));

        renderTabla(res.data || []);

        $('#btnBuscar').click(cargar);
        $('#cmbFiltroDeposito').on('change', cargar);
        $('#txtBuscar').on('keydown', e => { if (e.key === 'Enter') cargar(); });
    }

    async function cargar() {
        const params = new URLSearchParams({
            action: 'list',
            id_deposito: $('#cmbFiltroDeposito').val() || '',
            query: $('#txtBuscar').val() || ''
        });
        const res = await fetch(`api_reporte_stock_por_deposito.php?${params}`, { headers }).then(r => r.json());
        renderTabla(res.data || []);
    }

    function renderTabla(data) {
        const tbody = $('#tbodyReporte').empty();
        if (!data.length) {
            tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">Sin resultados.</td></tr>');
            return;
        }
        data.forEach(r => {
            const cant = parseFloat(r.cantidad);
            tbody.append(`
                <tr>
                    <td class="text-white-50 small">${r.codigo}</td>
                    <td class="text-white">${r.descripcion}</td>
                    <td class="text-white-50 small">${r.deposito}</td>
                    <td class="text-end fw-bold ${cant < 0 ? 'text-danger' : 'text-accent'}">${cant.toFixed(2)}</td>
                    <td class="text-end text-white-50">${parseFloat(r.stock_total).toFixed(2)}</td>
                </tr>
            `);
        });
    }

    init();
})();
</script>
