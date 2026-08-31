<?php
/**
 * puestos_cobro.php - PANEL DE ADMINISTRACIÓN DE CAJAS
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloPuestosCobro">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-cash-register text-accent me-2"></i>ADMINISTRAR PUESTOS DE COBRO</h4>
            <small class="text-accent opacity-75">Configuración de cajas, terminales y puntos de venta físicos</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-info fw-bold rounded-pill px-3" onclick="abrirModalPuesto(0)">
                <i class="fas fa-plus me-2"></i>NUEVO PUESTO
            </button>
            <button class="btn btn-outline-success border-0 px-3" onclick="exportarPuestosExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR
            </button>
        </div>
    </div>

    <div class="card border-secondary shadow-sm mb-4" style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                <div class="col-md-12">
                    <label class="form-label-custom small opacity-75 text-uppercase">Buscar Caja</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" id="txtBuscarCaja" class="form-control" placeholder="Filtrar por número o descripción..." oninput="renderPagina()">
                    </div>
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
        <table class="table table-dark table-hover align-middle mb-0 text-nowrap" id="tblPuestos" style="font-size: 0.85rem;">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary text-uppercase">
                    <th width="60" class="text-center ps-3">ID</th>
                    <th width="100" class="text-center">Nº CAJA</th>
                    <th>DESCRIPCIÓN</th>
                    <th class="text-center">ESTADO ACTUAL</th>
                    <th>USUARIO ACTIVO</th>
                    <th width="120" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyPuestos" class="text-white-50">
                <tr><td colspan="6" class="text-center py-5 text-muted small">Cargando puestos de cobro...</td></tr>
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
    let listadoPuestos = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' };

    /**
     * Carga inicial de datos
     */
    window.cargarPuestos = async function() {
        try {
            const resp = await fetch(`api_puestos_cobro.php?action=list`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const result = await resp.json();
            
            if (result.success) {
                listadoPuestos = result.data || [];
                pagActual = 1;
                renderPagina();
            } else {
                Swal.fire('Error', result.error, 'error');
            }
        } catch (error) {
            console.error(error);
        }
    }

    /**
     * Gestión de paginación y filtrado en memoria
     */
    window.renderPagina = function() {
        const filtro = document.getElementById('txtBuscarCaja').value.toLowerCase();
        
        let filtrados = listadoPuestos.filter(c => 
            (c.descripcion || '').toLowerCase().includes(filtro) || 
            (c.numcaja || '').toString().includes(filtro)
        );

        const total = filtrados.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = filtrados.slice(inicio, inicio + pagTam);

        renderTabla(segmento);
        renderTarjetas(segmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tbodyPuestos');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted small">No se encontraron puestos de cobro.</td></tr>';
            return;
        }

        data.forEach(c => {
            const esAbierta = parseInt(c.state || c.estado) === 1;
            const badgeEstado = esAbierta ? '<span class="badge bg-success">ABIERTA</span>' : '<span class="badge bg-secondary">CERRADA</span>';
            const usuarioTxt = esAbierta && c.usuarioactivo ? `<i class="fas fa-user text-info me-1"></i>${c.usuarioactivo.toUpperCase()}` : '<span class="text-muted small">-</span>';
            
            const btnDelClass = esAbierta ? 'btn-outline-secondary opacity-50' : 'btn-outline-danger';
            const btnDelClick = esAbierta ? 'Swal.fire("Atención", "No puede eliminar una caja abierta.", "warning")' : `eliminarPuesto(${c.id}, '${c.descripcion}')`;

            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            tr.innerHTML = `
                <td class="text-white-50 small text-center ps-3">${c.id}</td>
                <td class="fw-bold text-accent text-center fs-5">${c.numcaja}</td>
                <td class="fw-bold text-white text-uppercase">${c.descripcion}</td>
                <td class="text-center">${badgeEstado}</td>
                <td class="fw-bold">${usuarioTxt}</td>
                <td class="text-center pe-3">
                    <div class="btn-group btn-group-sm shadow-sm">
                        <button class="btn btn-info" onclick="abrirModalPuesto(${c.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn ${btnDelClass}" onclick="${btnDelClick}" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(c => {
            const esAbierta = parseInt(c.state || c.estado) === 1;
            const badgeEstado = esAbierta ? '<span class="badge bg-success">ABIERTA</span>' : '<span class="badge bg-secondary">CERRADA</span>';
            
            const card = document.createElement('div');
            card.className = "erp-card mb-2";
            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">Nº CAJA: ${c.numcaja}</div>
                    <span class="erp-card-code">ID: ${c.id}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">DESCRIPCIÓN</span>
                    <span class="erp-card-value text-white fw-bold text-uppercase">${c.descripcion}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">ESTADO</span>
                    <span class="erp-card-value">${badgeEstado}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">USUARIO ACTIVO</span>
                    <span class="erp-card-value text-white">${c.usuarioactivo ? '<i class="fas fa-user text-info me-1"></i>' + c.usuarioactivo.toUpperCase() : '-'}</span>
                </div>
                <div class="mt-2 d-flex gap-2">
                    <button class="btn btn-sm btn-info flex-fill fw-bold" onclick="abrirModalPuesto(${c.id})">
                        <i class="fas fa-edit me-1"></i> EDITAR
                    </button>
                    <button class="btn btn-sm ${esAbierta ? 'btn-outline-secondary opacity-50' : 'btn-outline-danger'}" 
                            onclick="${esAbierta ? 'Swal.fire(\'Atención\', \'No puede eliminar una caja abierta.\', \'warning\')' : `eliminarPuesto(${c.id}, '${c.descripcion}')`}">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> puestos`;

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
        document.getElementById('moduloPuestosCobro').scrollIntoView({ behavior: 'smooth' });
    }

    window.pagCambiarTamanoTop = function() {
        pagTam = parseInt(document.getElementById('pagTamanoTop').value);
        pagActual = 1;
        renderPagina();
    }

    /**
     * Acciones (Modal, Eliminar, Exportar)
     */
    window.abrirModalPuesto = async function(id = 0) {
        let title = 'Nuevo Puesto de Cobro';
        let valDesc = '';
        let valNum = '';

        if (id > 0) {
            title = 'Editar Puesto';
            const res = await fetch(`api_puestos_cobro.php?action=get&id=${id}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const r = await res.json();
            if(r.success) {
                valDesc = r.data.descripcion;
                valNum = r.data.numcaja;
            }
        }

        const { value: formValues } = await Swal.fire({
            title: title,
            background: '#001020',
            color: '#fff',
            html: `
                <div class="text-start px-2">
                    <label class="form-label-custom small mt-2">NÚMERO DE CAJA</label>
                    <input id="swal-numcaja" type="number" min="1" class="form-control" value="${valNum}">
                    
                    <label class="form-label-custom small mt-3">DESCRIPCIÓN / NOMBRE</label>
                    <input id="swal-desc" type="text" class="form-control" value="${valDesc}" placeholder="Ej: Puesto 1 - Mostrador">
                </div>
            `,
            focusConfirm: false,
            showCancelButton: true,
            confirmButtonText: 'GUARDAR',
            cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#0dcaf0',
            preConfirm: () => {
                const num = document.getElementById('swal-numcaja').value;
                const desc = document.getElementById('swal-desc').value;
                if (!num || !desc) {
                    Swal.showValidationMessage('Complete todos los campos');
                    return false;
                }
                return { id: id, numcaja: num, descripcion: desc };
            }
        });

        if (formValues) {
            try {
                const res = await fetch('api_puestos_cobro.php?action=save', {
                    method: 'POST', headers: commonHeaders, body: JSON.stringify(formValues)
                }).then(r => r.json());

                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Guardado', background: '#001020', color: '#fff', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    cargarPuestos();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Falla de conexión', 'error');
            }
        }
    }

    window.eliminarPuesto = async function(id, descripcion) {
        const conf = await Swal.fire({
            title: `¿Eliminar ${descripcion}?`,
            text: "Se validará que no tenga medios de pago asociados.",
            icon: 'warning',
            background: '#001020',
            color: '#fff',
            showCancelButton: true,
            confirmButtonText: 'ELIMINAR',
            confirmButtonColor: '#dc3545'
        });

        if (conf.isConfirmed) {
            try {
                const res = await fetch('api_puestos_cobro.php?action=delete', {
                    method: 'POST', headers: commonHeaders, body: JSON.stringify({ id: id })
                }).then(r => r.json());

                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Puesto Eliminado', background: '#001020', color: '#fff', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                    cargarPuestos();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch(e) {
                Swal.fire('Error', 'Falla de conexión', 'error');
            }
        }
    }

    window.exportarPuestosExcel = function() {
        if (!listadoPuestos || listadoPuestos.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay puestos registrados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const filtro = document.getElementById('txtBuscarCaja').value.toLowerCase();
        let exportData = listadoPuestos.filter(c => (c.descripcion || '').toLowerCase().includes(filtro) || (c.numcaja || '').toString().includes(filtro));

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .text { text-align: left; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .center { text-align: center; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .abierta { color: #28a745; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .cerrada { color: #6c757d; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="5" class="title">REPORTE DE PUESTOS DE COBRO (CAJAS)</td></tr>
                    <tr><td colspan="5">FECHA DE EXPORTACIÓN: ${new Date().toLocaleDateString('es-AR')}</td></tr>
                    <tr><td colspan="5"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">ID</th>
                            <th class="header">Nº CAJA</th>
                            <th class="header">DESCRIPCIÓN</th>
                            <th class="header">ESTADO ACTUAL</th>
                            <th class="header">USUARIO ACTIVO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        exportData.forEach(c => {
            const esAbierta = parseInt(c.state || c.estado) === 1;
            const estadoTxt = esAbierta ? 'ABIERTA' : 'CERRADA';
            const estadoCls = esAbierta ? 'abierta' : 'cerrada';
            
            excelHtml += `
                <tr>
                    <td class="center">${c.id}</td>
                    <td class="center" style="font-weight:bold; color:#006666;">${c.numcaja}</td>
                    <td class="text" style="font-weight:bold;">${(c.descripcion || '').toUpperCase()}</td>
                    <td class="${estadoCls}">${estadoTxt}</td>
                    <td class="text">${c.usuarioactivo ? c.usuarioactivo.toUpperCase() : ''}</td>
                </tr>
            `;
        });

        excelHtml += `</tbody></table></body></html>`;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Puestos_Cobro_${new Date().getTime()}.xls`;
        link.click();
    }
    
    // Iniciar
    cargarPuestos();
})();
</script>