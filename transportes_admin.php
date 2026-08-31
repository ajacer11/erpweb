<?php
// SEGURIDAD: Solo permitir acceso via AJAX
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloTransportes">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR TRANSPORTES</h4>
            <small class="text-accent opacity-75">Gestión de logística y empresas de transporte</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill" onclick="loadModule('transportes_add.php')">
                <i class="fas fa-plus me-2"></i>NUEVA EMPRESA
            </button>
            <button class="btn btn-outline-success border-0" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label-custom">BUSCAR POR NOMBRE O LOCALIDAD</label>
                    <input type="text" id="txtBuscar" class="form-control" placeholder="Escriba para filtrar...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 fw-bold" onclick="getTransportes()">
                        <i class="fas fa-search me-2"></i>FILTRAR
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
        <div class="d-flex align-items-center gap-2">
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 60vh;">
        <table class="table table-dark table-hover align-middle mb-0" id="tblTransportes">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="50" class="ps-3">ID</th>
                    <th>RAZÓN SOCIAL</th>
                    <th>DOMICILIO</th>
                    <th>LOCALIDAD</th>
                    <th>TELÉFONO</th>
                    <th>EMAIL</th>
                    <th width="100" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyTransportes">
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
    let listadoTransportes = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    /**
     * Obtiene y renderiza los datos
     */
    window.getTransportes = async function() {
        const busqueda = document.getElementById('txtBuscar').value;
        
        try {
            $('#loading-overlay').show();
            const resp = await fetch(`api_transportes.php?action=list&filter=${encodeURIComponent(busqueda)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            listadoTransportes = data || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error(error);
            $('#tbodyTransportes').html('<tr><td colspan="7" class="text-center py-4 text-danger">Error de conexión</td></tr>');
        } finally {
            $('#loading-overlay').hide();
        }
    };

    function renderPagina() {
        const total = listadoTransportes.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoTransportes.slice(inicio, inicio + pagTam);

        renderTabla(segmento);
        renderTarjetas(segmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyTransportes');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No se encontraron transportes.</td></tr>';
            return;
        }

        data.forEach(t => {
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            if (t.habilitado == 0) tr.style.opacity = '0.5';
            
            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${t.id}</td>
                <td class="fw-bold text-accent">${t.razon}</td>
                <td>${t.domicilio}</td>
                <td>${t.localidad}</td>
                <td>${t.telefono}</td>
                <td class="small">${t.mail}</td>
                <td class="text-center pe-3">
                    <button class="btn btn-sm btn-outline-info border-0" onclick="loadModule('transportes_add.php?id=${t.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(t => {
            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            if (t.habilitado == 0) card.style.opacity = '0.6';

            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${t.razon}</div>
                    <span class="badge bg-dark border border-secondary text-white-50 small">ID: ${t.id}</span>
                </div>
                <div class="erp-card-main">
                    <div class="row mb-2">
                        <div class="col-12">
                            <small class="text-muted d-block">DOMICILIO / LOCALIDAD</small>
                            <span class="text-white small">${t.domicilio}</span> | <span class="text-info small">${t.localidad}</span>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block">TELÉFONO</small>
                            <span class="small">${t.telefono || '-'}</span>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">ESTADO</small>
                            <span class="badge ${t.habilitado == 0 ? 'bg-danger' : 'bg-success'}" style="font-size: 9px;">${t.habilitado == 0 ? 'INACTIVO' : 'ACTIVO'}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-top border-secondary">
                    <button class="btn btn-info w-100 fw-bold" onclick="loadModule('transportes_add.php?id=${t.id}')">
                        <i class="fas fa-edit me-1"></i> EDITAR TRANSPORTE
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> transportes`;

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
        document.getElementById('moduloTransportes').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    // Búsqueda en tiempo real con debounce
    document.getElementById('txtBuscar').addEventListener('input', (e) => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => window.getTransportes(), 500);
    });

    // LÓGICA NATIVA DE EXPORTACIÓN (XLS)
    window.exportarExcel = function() {
        if (!listadoTransportes || listadoTransportes.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay transportes cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .center { text-align: center; border: 0.5pt solid #ccc; }
                    .inactivo { color: #cc0000; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .activo { color: #28a745; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="7" class="title">REPORTE DE TRANSPORTES</td></tr>
                    <tr><td colspan="7">FECHA DE EXPORTACIÓN: ${new Date().toLocaleDateString('es-AR')}</td></tr>
                    <tr><td colspan="7"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">ID</th>
                            <th class="header">RAZÓN SOCIAL</th>
                            <th class="header">DOMICILIO</th>
                            <th class="header">LOCALIDAD</th>
                            <th class="header">TELÉFONO</th>
                            <th class="header">EMAIL</th>
                            <th class="header">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        listadoTransportes.forEach(t => {
            const estadoTxt = t.habilitado == 0 ? 'INACTIVO' : 'ACTIVO';
            const estadoCls = t.habilitado == 0 ? 'inactivo' : 'activo';
            
            excelHtml += `
                <tr>
                    <td class="center">${t.id}</td>
                    <td class="text" style="font-weight:bold;">${(t.razon || '').toUpperCase()}</td>
                    <td class="text">${(t.domicilio || '').toUpperCase()}</td>
                    <td class="text">${(t.localidad || '').toUpperCase()}</td>
                    <td class="text">${t.telefono || ''}</td>
                    <td class="text">${(t.mail || '').toLowerCase()}</td>
                    <td class="${estadoCls}">${estadoTxt}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Transportes_${new Date().getTime()}.xls`;
        link.click();
    };

    // Inicialización
    window.getTransportes();
})();
</script>