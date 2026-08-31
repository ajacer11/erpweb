<?php
/**
 * cheques_emitidos.php - GESTIÓN DE CHEQUES PROPIOS EMITIDOS
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

// Fecha actual para los filtros por defecto
$hoy = date('Y-m-d');
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloChequesEmitidos">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRAR CHEQUES EMITIDOS</h4>
            <small class="text-accent opacity-75">Control y seguimiento de cartera de cheques propios</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success border-0" onclick="exportarChequesExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR EXCEL
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label-custom">ESTADO</label>
                    <select id="cmbEstado" class="form-select select2-filter">
                        <option value="">TODOS LOS ESTADOS</option>
                        <option value="A vencer">A VENCER</option>
                        <option value="Vencidos">VENCIDOS</option>
                        <option value="Pagados">PAGADOS</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">VENCIMIENTO DESDE</label>
                    <input type="date" id="dtDesde" class="form-control" value="<?php echo date('Y-m-01'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">VENCIMIENTO HASTA</label>
                    <input type="date" id="dtHasta" class="form-control" value="<?php echo date('Y-m-t'); ?>">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-info w-100 fw-bold" onclick="getCheques()">
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
        <div class="d-flex align-items-center gap-2">
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 60vh;">
        <table class="table table-dark table-hover align-middle mb-0" id="tblCheques">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="50" class="ps-3">ID</th>
                    <th>CHEQUE №</th>
                    <th>PROVEEDOR</th>
                    <th>BANCO</th>
                    <th>F. EMISIÓN</th>
                    <th>F. VENC.</th>
                    <th class="text-end">IMPORTE</th>
                    <th>CUENTA</th>
                    <th class="text-center">ESTADO</th>
                    <th width="100" class="text-center pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyCheques">
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

    // Inicializar Select2 para filtros
    $('.select2-filter').select2({
        dropdownParent: $('#main-content'),
        width: '100%'
    });

    /**
     * Obtiene los datos del servidor
     */
    window.getCheques = async function() {
        const estado = document.getElementById('cmbEstado').value;
        const desde = document.getElementById('dtDesde').value;
        const hasta = document.getElementById('dtHasta').value;

        const query = new URLSearchParams({
            action: 'list',
            estado: estado,
            desde: desde,
            hasta: hasta
        });

        try {
            $('#loading-overlay').show();
            const resp = await fetch(`api_cheques_emitidos.php?${query}`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await resp.json();
            
            listadoCompleto = data || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error(error);
            $('#tbodyCheques').html('<tr><td colspan="10" class="text-center py-4 text-danger">Error de conexión</td></tr>');
        } finally {
            $('#loading-overlay').hide();
        }
    }

    /**
     * Renderiza el segmento actual de datos
     */
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
        const tbody = document.getElementById('tbodyCheques');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-4 text-muted">No se encontraron cheques.</td></tr>';
            return;
        }

        data.forEach(ch => {
            const { badgeClass, labelEstado } = getEstadoInfo(ch);
            
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${ch.id}</td>
                <td class="fw-bold text-white">${ch.numero}</td>
                <td class="text-accent fw-bold">${(ch.proveedor || 'S/P').toUpperCase()}</td>
                <td>${ch.banco}</td>
                <td>${formatDate(ch.fechaemision)}</td>
                <td class="fw-bold">${formatDate(ch.fechavencimiento)}</td>
                <td class="text-end fw-bold text-accent">$ ${parseFloat(ch.importe).toLocaleString('es-AR', {minimumFractionDigits: 2})}</td>
                <td class="small text-muted">${ch.cuenta}</td>
                <td class="text-center">
                    <span class="badge ${badgeClass}" style="font-size: 9px;">${labelEstado}</span>
                </td>
                <td class="text-center pe-3">
                    ${ch.estado != 2 ? `
                        <button class="btn btn-sm btn-outline-success border-0" onclick="confirmarPago(${ch.id}, '${ch.numero}')" title="Marcar como Pagado">
                            <i class="fas fa-check-double"></i>
                        </button>
                    ` : '<i class="fas fa-check text-success"></i>'}
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(ch => {
            const { badgeClass, labelEstado } = getEstadoInfo(ch);
            
            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${ch.proveedor || 'S/P'}</div>
                    <span class="badge ${badgeClass}" style="font-size: 9px;">${labelEstado}</span>
                </div>
                <div class="erp-card-main">
                    <div class="row">
                        <div class="col-6">
                            <small class="text-muted d-block">CHEQUE №</small>
                            <span class="fw-bold text-white">${ch.numero}</span>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">IMPORTE</small>
                            <span class="fw-bold text-accent">$ ${parseFloat(ch.importe).toLocaleString('es-AR', {minimumFractionDigits: 2})}</span>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-6">
                            <small class="text-muted d-block">VENCIMIENTO</small>
                            <span class="fw-bold">${formatDate(ch.fechavencimiento)}</span>
                        </div>
                        <div class="col-6 text-end">
                            <small class="text-muted d-block">BANCO</small>
                            <span class="small">${ch.banco}</span>
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-top border-secondary d-flex justify-content-between align-items-center">
                    <small class="text-white-50">ID: ${ch.id} | ${ch.cuenta}</small>
                    ${ch.estado != 2 ? `
                        <button class="btn btn-sm btn-success px-3 fw-bold" onclick="confirmarPago(${ch.id}, '${ch.numero}')">
                            <i class="fas fa-check-double me-1"></i> PAGAR
                        </button>
                    ` : '<span class="text-success small fw-bold"><i class="fas fa-check me-1"></i> PAGADO</span>'}
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    /**
     * Lógica unificada de estados y colores
     */
    function getEstadoInfo(ch) {
        let badgeClass = 'bg-secondary';
        let labelEstado = 'EMITIDO';

        if (ch.estado == 2) {
            badgeClass = 'bg-success';
            labelEstado = 'PAGADO';
        } else {
            const fVenc = new Date(ch.fechavencimiento);
            const hoy = new Date();
            hoy.setHours(0,0,0,0);
            
            if (fVenc < hoy) {
                badgeClass = 'bg-danger';
                labelEstado = 'VENCIDO';
            } else {
                badgeClass = 'bg-warning text-dark';
                labelEstado = 'A VENCER';
            }
        }
        return { badgeClass, labelEstado };
    }

    /**
     * Sincroniza los controles de paginación
     */
    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> cheques`;

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
        document.getElementById('moduloChequesEmitidos').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    function formatDate(dateStr) {
        if(!dateStr) return '-';
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    }

    /**
     * Marcar cheque como pagado
     */
    window.confirmarPago = async function(id, numero) {
        const result = await Swal.fire({
            title: '¿Confirmar Pago?',
            text: `¿Desea marcar el cheque Nº ${numero} como PAGADO?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, PAGADO',
            cancelButtonText: 'CANCELAR',
            background: '#001020',
            color: '#fff'
        });

        if (result.isConfirmed) {
            const resp = await fetch('api_cheques_emitidos.php?action=mark_paid', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ id: id })
            });

            const res = await resp.json();
            if (res.success) {
                Swal.fire({ title: 'Éxito', text: 'Cheque marcado como pagado', icon: 'success', timer: 1500, showConfirmButton: false });
                getCheques();
            } else {
                Swal.fire('Error', 'No se pudo procesar el cambio de estado', 'error');
            }
        }
    }

    /**
     * Exportación Excel Nativa
     */
    window.exportarChequesExcel = function() {
        if (!listadoCompleto || listadoCompleto.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay cheques cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const estadoSelect = document.getElementById('cmbEstado');
        const estadoFiltro = estadoSelect.options[estadoSelect.selectedIndex].text;
        const desde = document.getElementById('dtDesde').value.split('-').reverse().join('/');
        const hasta = document.getElementById('dtHasta').value.split('-').reverse().join('/');

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"\\#\\,\\#\\#0\\.00"; text-align: right; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .center { text-align: center; border: 0.5pt solid #ccc; mso-number-format:"\\@"; }
                    .pagado { color: #28a745; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .vencido { color: #cc0000; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .avencer { color: #d39e00; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="9" class="title">REPORTE DE CHEQUES PROPIOS EMITIDOS</td></tr>
                    <tr><td colspan="9">PERIODO DE VENCIMIENTO: ${desde} AL ${hasta} | FILTRO: ${estadoFiltro}</td></tr>
                    <tr><td colspan="9"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">ID</th>
                            <th class="header">CHEQUE №</th>
                            <th class="header">PROVEEDOR</th>
                            <th class="header">BANCO</th>
                            <th class="header">F. EMISIÓN</th>
                            <th class="header">F. VENCIMIENTO</th>
                            <th class="header">IMPORTE</th>
                            <th class="header">CUENTA</th>
                            <th class="header">ESTADO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        let totalGeneral = 0;

        listadoCompleto.forEach(ch => {
            const { labelEstado } = getEstadoInfo(ch);
            let estadoCls = (ch.estado == 2) ? 'pagado' : (new Date(ch.fechavencimiento) < new Date().setHours(0,0,0,0) ? 'vencido' : 'avencer');

            const importe = parseFloat(ch.importe || 0);
            totalGeneral += importe;

            excelHtml += `
                <tr>
                    <td class="center">${ch.id}</td>
                    <td class="text" style="font-weight:bold;">${ch.numero}</td>
                    <td class="text">${(ch.proveedor || 'S/P').toUpperCase()}</td>
                    <td class="text">${(ch.banco || '').toUpperCase()}</td>
                    <td class="center">${formatDate(ch.fechaemision)}</td>
                    <td class="center" style="font-weight:bold;">${formatDate(ch.fechavencimiento)}</td>
                    <td class="number" style="font-weight:bold;">${importe}</td>
                    <td class="text">${(ch.cuenta || '').toUpperCase()}</td>
                    <td class="${estadoCls}">${labelEstado}</td>
                </tr>
            `;
        });

        excelHtml += `
                    <tr>
                        <td colspan="6" style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTAL GENERAL:</td>
                        <td class="number" style="font-weight:bold; color:#006666;">${totalGeneral}</td>
                        <td colspan="2"></td>
                    </tr>
                </tbody></table></body></html>
        `;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Cheques_Emitidos_${new Date().getTime()}.xls`;
        link.click();
    }

    // Carga inicial
    getCheques();

})();
</script>