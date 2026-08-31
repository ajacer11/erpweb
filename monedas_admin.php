<?php
/**
 * monedas_admin.php - ADMINISTRACIÓN DE MONEDAS Y TIPOS DE CAMBIO
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloMonedas">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">MONEDAS Y COTIZACIONES</h4>
            <small class="text-accent opacity-75">Gestión de divisas y actualización masiva de precios</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="loadModule('monedas_abm.php')">
                <i class="fas fa-plus me-2"></i>NUEVA MONEDA
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label-custom">BUSCAR MONEDA</label>
                    <input type="text" id="txtBuscarMoneda" class="form-control" placeholder="Nombre de la divisa (ej: Dólar, Euro)...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 fw-bold" onclick="getMonedas()">
                        <i class="fas fa-search me-2"></i>BUSCAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="ms-auto d-flex align-items-center gap-2">
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a;">
        <table class="table table-dark table-hover align-middle mb-0" id="tblMonedas">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="50" class="ps-3">ID</th>
                    <th>DIVISA</th>
                    <th class="text-end">COTIZACIÓN</th>
                    <th class="text-end">ÚLTIMA ACT.</th>
                    <th width="150" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyMonedas">
                </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

    <div class="erp-pagination mt-3" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
    </div>
</div>

<script>
(function() {
    let listadoCompleto = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    async function init() {
        // Buscador con debounce para no saturar
        $('#txtBuscarMoneda').on('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => getMonedas(), 500);
        });

        getMonedas();
    }

    window.getMonedas = async function() {
        const query = document.getElementById('txtBuscarMoneda').value;
        const url = `api_monedas.php?action=list&query=${encodeURIComponent(query)}`;
        
        try {
            $('#loading-overlay').show();
            const resp = await fetch(url, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            listadoCompleto = await resp.json() || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error("Error al obtener monedas:", error);
            $('#tbodyMonedas').html('<tr><td colspan="5" class="text-center py-4 text-danger">Error de conexión</td></tr>');
        } finally {
            $('#loading-overlay').hide();
        }
    }

    function renderPagina() {
        const total = listadoCompleto.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoCompleto.slice(inicio, inicio + pagTam);

        renderTabla(segmento);
        renderTarjetas(segmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyMonedas');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron monedas.</td></tr>';
            return;
        }

        data.forEach(m => {
            const cotizacion = parseFloat(m.cotizacion || 0);
            const fecha = m.fecha_act ? new Date(m.fecha_act).toLocaleDateString() : '-';
            
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${m.id}</td>
                <td class="fw-bold text-white fs-6">${m.nombre}</td>
                <td class="text-end fw-bold text-accent fs-5">
                    $ ${cotizacion.toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </td>
                <td class="text-end small text-muted">${fecha}</td>
                <td class="text-center pe-3">
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-info border-0" onclick="loadModule('monedas_abm.php?id=${m.id}')" title="Editar Nombre">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-warning border-0" onclick="loadModule('monedas_cotizacion.php?id=${m.id}')" title="Actualizar Cotización">
                            <i class="fas fa-chart-line"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(m => {
            const cotizacion = parseFloat(m.cotizacion || 0);
            const fecha = m.fecha_act ? new Date(m.fecha_act).toLocaleDateString() : '-';

            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${m.nombre}</div>
                    <span class="badge bg-dark border border-secondary text-white-50 small">ID: ${m.id}</span>
                </div>
                <div class="erp-card-main">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Cotización Actual</small>
                            <span class="fs-4 fw-bold text-white">$ ${cotizacion.toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                        <div class="text-end">
                            <small class="text-muted d-block text-uppercase" style="font-size: 10px;">Última Act.</small>
                            <span class="text-white-50">${fecha}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-info flex-fill fw-bold" onclick="loadModule('monedas_abm.php?id=${m.id}')">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                    <button class="btn btn-warning flex-fill fw-bold" onclick="loadModule('monedas_cotizacion.php?id=${m.id}')">
                        <i class="fas fa-chart-line me-1"></i> COTIZAR
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> monedas`;

        ['', 'Top'].forEach(sfx => {
            $(`#pagInfo${sfx}`).html(info);
            $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
            $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

            const cont = $(`#pagBotones${sfx}`).empty();
            let ini = Math.max(1, pagActual - 2);
            let fin = Math.min(pagTotal, ini + 4);
            if (fin - ini < 4) ini = Math.max(1, fin - 4);

            for (let i = ini; i <= fin; i++) {
                const b = $('<button>').addClass('erp-pag-btn').text(i).click(() => pagGo(i));
                if (i === pagActual) b.addClass('active');
                cont.append(b);
            }
            $(`#pag${sfx}Wrap`).toggle(total > 0);
        });
    }

    window.pagGo = (n) => {
        pagActual = Math.max(1, Math.min(pagTotal, n));
        renderPagina();
        document.getElementById('moduloMonedas').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    init();
})();
</script>