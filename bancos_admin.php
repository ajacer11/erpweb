<?php
/**
 * bancos_admin.php - PANEL DE ADMINISTRACIÓN DE CUENTAS BANCARIAS PROPIAS
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloBancosAdmin">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR CUENTAS BANCARIAS</h4>
            <small class="text-accent opacity-75">Gestión de cuentas corrientes, cajas de ahorro y entidades financieras</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill px-3" onclick="loadModule('bancos_cuenta_add.php')">
                <i class="fas fa-plus me-2"></i>NUEVA CUENTA
            </button>
            <button class="btn btn-outline-success border-0 px-3" onclick="exportarCuentasExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR
            </button>
        </div>
    </div>

    <div class="card border-secondary shadow-sm mb-4" style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-10">
                    <label class="form-label text-accent small mb-1 text-uppercase">BUSCAR POR REFERENCIA, BANCO O CBU</label>
                    <input type="text" id="txtBuscarCuenta" class="form-control" placeholder="Escriba para filtrar resultados...">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary w-100 fw-bold" onclick="getCuentas()">
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

    <div class="erp-table-wrap border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; overflow-y: auto;">
        <table class="table table-dark table-hover align-middle mb-0 text-nowrap" id="tblCuentas" style="font-size: 0.85rem;">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="60" class="ps-3">ID</th>
                    <th>REFERENCIA</th>
                    <th>ENTIDAD BANCARIA</th>
                    <th>TIPO / MONEDA</th>
                    <th>SUCURSAL</th>
                    <th>C.B.U. / ALIAS</th>
                    <th width="100" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyCuentas" class="text-white-50">
                <tr><td colspan="7" class="text-center py-5 text-muted small">Cargando cuentas bancarias...</td></tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

    <div class="erp-pagination mt-2" id="pagBotWrap" style="display:none;">
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

    /**
     * Obtiene el listado completo y dispara el renderizado
     */
    window.getCuentas = async function() {
        const busqueda = document.getElementById('txtBuscarCuenta').value;
        try {
            const resp = await fetch(`api_bancos_cuentas.php?action=list&query=${encodeURIComponent(busqueda)}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            listadoCompleto = data; // Almacenamos en memoria (SPA)
            pagActual = 1;
            renderPagina();
        } catch (e) {
            console.error("Error al obtener cuentas:", e);
        }
    }

    /**
     * Renderiza el segmento de datos actual
     */
    function renderPagina() {
        const total = listadoCompleto.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoCompleto.slice(inicio, inicio + pagTam);

        renderTabla(segmento, inicio);
        renderTarjetas(segmento, inicio);
        sincronizarPaginacion(total);
    }

    function renderTabla(data, absoluteOffset) {
        const tbody = document.getElementById('tbodyCuentas');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted small">No se encontraron cuentas bancarias registradas.</td></tr>';
            return;
        }

        data.forEach((c, idx) => {
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            tr.innerHTML = `
                <td class="ps-3 text-white-50 small">${c.id}</td>
                <td class="fw-bold text-white">${c.referencia}</td>
                <td class="text-accent fw-bold">${c.banco_nombre}</td>
                <td>
                    <span class="badge bg-dark border border-secondary text-white-50" style="font-size:9px">${c.tipocuenta}</span>
                    <span class="ms-1 fw-bold text-info">${c.moneda}</span>
                </td>
                <td class="small">
                    ${c.sucursal}<br>
                    <small class="text-muted">№ Suc: ${c.numsucursal}</small>
                </td>
                <td class="small">
                    <span class="text-white">${c.numcbu}</span><br>
                    <i class="fas fa-tag me-1 opacity-50 small"></i><span class="text-accent">${c.alias}</span>
                </td>
                <td class="text-center pe-3">
                    <button class="btn btn-sm btn-outline-info border-0" onclick="loadModule('bancos_cuenta_add.php?id=${c.id}')" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data, absoluteOffset) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(c => {
            const card = document.createElement('div');
            card.className = "erp-card mb-2";
            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${c.referencia}</div>
                    <span class="erp-card-code">ID: ${c.id}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">BANCO</span>
                    <span class="erp-card-value text-white fw-bold">${c.banco_nombre}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">TIPO / MONEDA</span>
                    <span class="erp-card-value">
                        <span class="badge bg-dark border border-secondary text-white-50 me-1" style="font-size:9px">${c.tipocuenta}</span>
                        <span class="text-info fw-bold">${c.moneda}</span>
                    </span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">CBU / ALIAS</span>
                    <span class="erp-card-value text-white small">${c.numcbu}<br><span class="text-accent">${c.alias}</span></span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">SUCURSAL</span>
                    <span class="erp-card-value text-white-50 small">${c.sucursal} (№ ${c.numsucursal})</span>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-info flex-fill fw-bold" onclick="loadModule('bancos_cuenta_add.php?id=${c.id}')">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> cuentas`;

        ['', 'Top'].forEach(sfx => {
            const elInfo = document.getElementById('pagInfo' + sfx);
            if (elInfo) elInfo.innerHTML = info;

            document.getElementById('btnPagPrim' + sfx).disabled = (pagActual <= 1);
            document.getElementById('btnPagAnt' + sfx).disabled = (pagActual <= 1);
            document.getElementById('btnPagSig' + sfx).disabled = (pagActual >= pagTotal);
            document.getElementById('btnPagUlt' + sfx).disabled = (pagActual >= pagTotal);

            const cont = document.getElementById('pagBotones' + sfx);
            cont.innerHTML = '';
            
            let ini = Math.max(1, pagActual - 2);
            let fin = Math.min(pagTotal, ini + 4);
            if (fin - ini < 4) ini = Math.max(1, fin - 4);

            for (let i = ini; i <= fin; i++) {
                const b = document.createElement('button');
                b.className = 'erp-pag-btn' + (i === pagActual ? ' active' : '');
                b.textContent = i;
                b.onclick = () => pagGo(i);
                cont.appendChild(b);
            }

            const wrap = document.getElementById(sfx === 'Top' ? 'pagTopWrap' : 'pagBotWrap');
            if (wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n) {
        pagActual = Math.max(1, Math.min(pagTotal, n));
        renderPagina();
        document.getElementById('moduloBancosAdmin').scrollIntoView({ behavior: 'smooth' });
    }

    window.pagCambiarTamanoTop = function() {
        pagTam = parseInt(document.getElementById('pagTamanoTop').val() || 20);
        pagActual = 1;
        renderPagina();
    }

    // Inicializar
    getCuentas();

    // Exportación original (XLS HTML)
    window.exportarCuentasExcel = function() {
        if (!listadoCompleto || listadoCompleto.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay cuentas cargadas para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .text { text-align: left; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .center { text-align: center; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="7" class="title">REPORTE DE CUENTAS BANCARIAS PROPIAS</td></tr>
                    <tr><td colspan="7">FECHA DE EXPORTACIÓN: ${new Date().toLocaleDateString('es-AR')}</td></tr>
                    <tr><td colspan="7"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">ID</th>
                            <th class="header">REFERENCIA</th>
                            <th class="header">ENTIDAD BANCARIA</th>
                            <th class="header">TIPO CUENTA</th>
                            <th class="header">MONEDA</th>
                            <th class="header">SUCURSAL</th>
                            <th class="header">C.B.U. / ALIAS</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        listadoCompleto.forEach(c => {
            excelHtml += `
                <tr>
                    <td class="center">${c.id}</td>
                    <td class="text" style="font-weight:bold;">${(c.referencia || '').toUpperCase()}</td>
                    <td class="text">${(c.banco_nombre || '').toUpperCase()}</td>
                    <td class="center">${(c.tipocuenta || '').toUpperCase()}</td>
                    <td class="center" style="font-weight:bold; color:#006666;">${(c.moneda || '').toUpperCase()}</td>
                    <td class="text">${(c.sucursal || '').toUpperCase()} (Nº ${c.numsucursal || '-'})</td>
                    <td class="text">CBU: ${c.numcbu || '-'}<br>Alias: ${(c.alias || '').toUpperCase()}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Bancos_Cuentas_${new Date().getTime()}.xls`;
        link.click();
    }

    // Enter para buscar
    document.getElementById('txtBuscarCuenta').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') getCuentas();
    });
})();
</script>