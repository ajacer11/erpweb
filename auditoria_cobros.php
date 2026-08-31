<?php
/**
 * auditoria_cobros.php - VISTA FRAGMENTO (SPA)
 * Migración de "controldecobrosaclicentes.vb"
 * Auditoría de consistencia entre Cta. Cte. y Facturas Pendientes.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="container-auditoria-cobros">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;"><i class="fa-solid fa-microscope text-accent me-2"></i>AUDITORÍA DE COBROS</h4>
            <small class="text-accent opacity-75">CONTROL DE CONSISTENCIA: CTA. CTE. VS FACTURAS</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-warning btn-sm px-3 rounded-pill fw-bold text-dark" id="btnActualizar">
                <i class="fa-solid fa-sync me-1"></i> ACTUALIZAR
            </button>
            <button type="button" class="btn btn-outline-success btn-sm px-3 rounded-pill fw-bold" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> EXPORTAR
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill"
                onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> CERRAR
            </button>
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

    <div class="card border-secondary shadow mb-3" style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-header bg-dark-soft border-bottom border-secondary py-2">
            <div class="d-flex justify-content-between align-items-center">
                <span class="small fw-bold text-white"><i class="fa-solid fa-list-check me-2 text-accent"></i>DIFERENCIAS DETECTADAS</span>
                <span class="badge bg-danger bg-opacity-25 text-danger border border-danger small" id="lblCount">0 REGISTROS</span>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="erp-table-wrap table-responsive" style="max-height: 60vh;">
                <table class="table table-dark table-hover table-sm mb-0 small" id="tblAuditoria">
                    <thead class="sticky-top bg-dark">
                        <tr class="text-accent border-bottom border-secondary text-uppercase">
                            <th class="ps-3 py-2">ID</th>
                            <th class="py-2">Cliente</th>
                            <th class="text-end py-2">Saldo Cta. Cte.</th>
                            <th class="text-end py-2">Saldo Facturas</th>
                            <th class="text-end py-2 pe-3">Diferencia</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyAuditoria">
                        <tr>
                            <td colspan="5" class="text-center p-5 text-muted">
                                <i class="fa-solid fa-magnifying-glass-chart fs-3 mb-3"></i><br>
                                Presione <b>ACTUALIZAR</b> para ejecutar el control de auditoría.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="erp-cards-wrap" id="tblCards"></div>
        </div>
        <div class="card-footer bg-dark border-top border-secondary py-2">
            <small class="text-muted fst-italic">
                * Se muestran clientes donde la diferencia absoluta entre el saldo contable y la suma de comprobantes pendientes supera 0.10.
            </small>
        </div>
    </div>

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
    (function () {
        let currentData = [];
        let pagActual = 1;
        let pagTam = 20;
        let pagTotal = 1;

        const fmt = (val) => new Intl.NumberFormat('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val);

        async function procesarAuditoria(action = 'get_data') {
            try {
                $('#loading-overlay').show();
                
                const resp = await fetch(`api_auditoria_cobros.php?action=${action}`, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    if (action === 'export') {
                        const byteCharacters = atob(res.xls_base64);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) {
                            byteNumbers[i] = byteCharacters.charCodeAt(i);
                        }
                        const byteArray = new Uint8Array(byteNumbers);
                        const blob = new Blob([byteArray], { type: 'application/vnd.ms-excel' });
                        
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = res.filename;
                        document.body.appendChild(a);
                        a.click();
                        a.remove();
                        window.URL.revokeObjectURL(url);

                        Swal.fire({
                            icon: 'success', title: 'Exportado', text: 'Informe de auditoría generado correctamente.',
                            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000
                        });
                    } else {
                        currentData = res.data || [];
                        pagActual = 1;
                        renderPagina();
                    }
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                console.error(err);
                Swal.fire('Error', 'Error de comunicación con el servidor.', 'error');
            } finally {
                $('#loading-overlay').hide();
            }
        }

        function renderPagina() {
            const total = currentData.length;
            pagTotal = Math.max(1, Math.ceil(total / pagTam));
            if (pagActual > pagTotal) pagActual = pagTotal;

            const inicio = (pagActual - 1) * pagTam;
            const fin = inicio + pagTam;
            const segmento = currentData.slice(inicio, fin);

            renderTabla(segmento);
            renderTarjetas(segmento);
            sincronizarPaginacion(total);
            $('#lblCount').text(`${total} REGISTROS`);
        }

        function renderTabla(data) {
            const tbody = $('#tbodyAuditoria');
            tbody.empty();

            if (data.length === 0) {
                tbody.html('<tr><td colspan="5" class="text-center p-5 text-success"><i class="fa-solid fa-check-circle fs-3 mb-3"></i><br>No se encontraron diferencias. Todo consistente.</td></tr>');
                return;
            }

            data.forEach(row => {
                const dif = parseFloat(row.Diferencia);
                const colorDif = dif !== 0 ? 'text-warning fw-bold' : 'text-muted';

                tbody.append(`
                    <tr class="border-secondary align-middle">
                        <td class="ps-3 text-white font-monospace">${row.ID}</td>
                        <td class="fw-bold text-white">${row.Cliente}</td>
                        <td class="text-end font-monospace text-info">${fmt(row.SaldoCC)}</td>
                        <td class="text-end font-monospace text-success">${fmt(row.SaldoFacturas)}</td>
                        <td class="text-end font-monospace pe-3 ${colorDif}">${fmt(row.Diferencia)}</td>
                    </tr>
                `);
            });
        }

        function renderTarjetas(data) {
            const wrap = $('#tblCards');
            wrap.empty();

            data.forEach(row => {
                const dif = parseFloat(row.Diferencia);
                const colorDif = dif !== 0 ? 'text-warning fw-bold' : 'text-muted';

                wrap.append(`
                    <div class="erp-card mb-2">
                        <div class="erp-card-header">
                            <div class="erp-card-title text-accent">${row.Cliente}</div>
                            <span class="erp-card-code">ID: ${row.ID}</span>
                        </div>
                        <div class="erp-card-row">
                            <span class="erp-card-label">SALDO CTA. CTE.</span>
                            <span class="erp-card-value text-info font-monospace">${fmt(row.SaldoCC)}</span>
                        </div>
                        <div class="erp-card-row">
                            <span class="erp-card-label">SALDO FACTURAS</span>
                            <span class="erp-card-value text-success font-monospace">${fmt(row.SaldoFacturas)}</span>
                        </div>
                        <div class="erp-card-row border-top border-secondary pt-1 mt-1">
                            <span class="erp-card-label fw-bold">DIFERENCIA</span>
                            <span class="erp-card-value font-monospace ${colorDif}">${fmt(row.Diferencia)}</span>
                        </div>
                    </div>
                `);
            });
        }

        function sincronizarPaginacion(total) {
            const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
            const hasta = Math.min(pagActual * pagTam, total);
            const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong>`;

            ['', 'Top'].forEach(sfx => {
                $(`#pagInfo${sfx}`).html(info);
                $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
                $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

                const cont = $(`#pagBotones${sfx}`).empty();
                let inicioBotones = Math.max(1, pagActual - 2);
                let finBotones = Math.min(pagTotal, inicioBotones + 4);
                if (finBotones - inicioBotones < 4) inicioBotones = Math.max(1, finBotones - 4);

                for (let i = inicioBotones; i <= finBotones; i++) {
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
            document.getElementById('container-auditoria-cobros').scrollIntoView({ behavior: 'smooth' });
        };

        window.pagCambiarTamanoTop = () => {
            pagTam = parseInt($('#pagTamanoTop').val());
            pagActual = 1;
            renderPagina();
        };

        // Eventos
        $('#btnActualizar').on('click', () => procesarAuditoria('get_data'));
        
        $('#btnExportar').on('click', () => {
            if (currentData.length === 0) {
                Swal.fire('Atención', 'No hay datos para exportar. Ejecute la auditoría primero.', 'info');
                return;
            }
            Swal.fire({
                title: '¿Exportar Auditoría?',
                text: "Se generará un archivo Excel con las diferencias detectadas.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, Exportar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    procesarAuditoria('export');
                }
            });
        });
    })();
</script>