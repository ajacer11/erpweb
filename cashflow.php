<?php
/**
 * cashflow.php - CASHFLOW DEFINITIVO
 * Análisis financiero, proyección diaria, arrastre de saldos y reporte PDF
 */
require_once 'check_access.php';
?>

<!-- Carga de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-3 fade-in" style="overflow-x: hidden;">
    
    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white mb-0 fw-light">
                <i class="fas fa-chart-line text-warning me-2"></i>ESTADO DE CASHFLOW
            </h3>
            <small class="text-muted">Proyección de liquidez basada en Tesorería, CxC, CxP y Cheques</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-danger rounded-pill fw-bold px-3" onclick="imprimirCashflowPDF()">
                <i class="fas fa-file-pdf me-2"></i>IMPRIMIR PDF
            </button>
            <button class="btn btn-outline-success rounded-pill fw-bold px-3" onclick="exportarCashflowExcel()">
                <i class="fas fa-file-excel me-2"></i>EXPORTAR EXCEL
            </button>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label-custom">PROYECTAR DESDE</label>
                    <input type="date" id="cf-desde" class="form-control" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label-custom">PROYECTAR HASTA</label>
                    <input type="date" id="cf-hasta" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                </div>
                <div class="col-md-4 d-flex flex-column justify-content-end gap-2 pb-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="chk-vencidos" checked>
                        <label class="form-check-label text-white small ms-1" for="chk-vencidos">Incluir Arrastre (Saldos Vencidos Previos)</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-info fw-bold w-100" onclick="cargarCashflow()">
                        <i class="fas fa-sync-alt me-2"></i>CALCULAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- PESTAÑAS (TABS) -->
    <ul class="nav nav-tabs mb-4 border-secondary" id="cfTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold text-warning" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen-pane" type="button" role="tab">
                <i class="fas fa-chart-pie me-2"></i>RESUMEN GLOBAL
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-primary" id="diaria-tab" data-bs-toggle="tab" data-bs-target="#diaria-pane" type="button" role="tab">
                <i class="fas fa-calendar-day me-2"></i>PROYECCIÓN DIARIA
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-info" id="tesoreria-tab" data-bs-toggle="tab" data-bs-target="#tesoreria-pane" type="button" role="tab">
                <i class="fas fa-vault me-2"></i>TESORERÍA <span class="badge bg-info ms-1 text-dark" id="badge-teso">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-success" id="cobros-tab" data-bs-toggle="tab" data-bs-target="#cobros-pane" type="button" role="tab">
                <i class="fas fa-arrow-down-long me-2"></i>A COBRAR (PERÍODO) <span class="badge bg-success ms-1" id="badge-cobros">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-danger" id="pagos-tab" data-bs-toggle="tab" data-bs-target="#pagos-pane" type="button" role="tab">
                <i class="fas fa-arrow-up-long me-2"></i>A PAGAR (PERÍODO) <span class="badge bg-danger ms-1" id="badge-pagos">0</span>
            </button>
        </li>
    </ul>

    <!-- LOADER -->
    <div id="cf-loader" class="text-center py-5" style="display: none;">
        <div class="spinner-border text-warning" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="text-muted mt-3 fw-bold tracking-widest">PROCESANDO FLUJO DE CAJA...</p>
    </div>

    <!-- CONTENEDOR DE PESTAÑAS -->
    <div class="tab-content" id="cfTabsContent">
        
        <!-- 1. RESUMEN GLOBAL -->
        <div class="tab-pane fade show active" id="resumen-pane" role="tabpanel">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 border-start border-info border-4 shadow-sm h-100">
                        <div class="card-body p-3">
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.70rem;">SALDO DE PARTIDA</p>
                            <h5 class="text-info mb-0 fw-bold" id="lbl-partida">$0.00</h5>
                            <small class="text-muted" style="font-size: 0.65rem;" id="lbl-partida-desc">Liquidez Hoy + Arrastre Vencido</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 border-start border-success border-4 shadow-sm h-100">
                        <div class="card-body p-3">
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.70rem;">COBROS DEL PERÍODO</p>
                            <h5 class="text-success mb-0 fw-bold" id="lbl-cobrar">$0.00</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">A ingresar entre fechas</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 border-start border-danger border-4 shadow-sm h-100">
                        <div class="card-body p-3">
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.70rem;">PAGOS DEL PERÍODO</p>
                            <h5 class="text-danger mb-0 fw-bold" id="lbl-pagar">$0.00</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">A egresar entre fechas</small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 shadow-sm h-100" id="card-posicion" style="background: linear-gradient(145deg, rgba(255,193,7,0.1) 0%, rgba(0,0,0,0) 100%);">
                        <div class="card-body p-3">
                            <p class="text-warning fw-bold mb-1" style="font-size: 0.70rem;">POSICIÓN FINAL PROYECTADA</p>
                            <h5 class="text-white mb-0 fw-bold" id="lbl-posicion">$0.00</h5>
                            <small class="text-muted" style="font-size: 0.65rem;">Partida + Cobros - Pagos</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-filtros h-100 shadow-sm">
                <div class="card-header border-bottom border-secondary bg-transparent py-3">
                    <h6 class="text-white fw-bold mb-0"><i class="fas fa-chart-line text-warning me-2"></i> EVOLUCIÓN GRÁFICA DEL CASHFLOW</h6>
                </div>
                <div class="card-body" style="position: relative; height: 350px;">
                    <canvas id="chartCashflow"></canvas>
                </div>
            </div>
        </div>

        <!-- 2. PROYECCIÓN DIARIA -->
        <div class="tab-pane fade" id="diaria-pane" role="tabpanel">
            <div class="card card-filtros shadow-sm">
                <div class="table-responsive" style="max-height: 550px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-navbar">
                            <tr>
                                <th>FECHA</th>
                                <th class="text-end">SALDO INICIAL</th>
                                <th class="text-end text-success">+ INGRESOS (COBROS)</th>
                                <th class="text-end text-danger">- EGRESOS (PAGOS)</th>
                                <th class="text-end text-info">SALDO FINAL PROYECTADO</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-diaria"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 3. TESORERÍA (DISPONIBILIDAD) -->
        <div class="tab-pane fade" id="tesoreria-pane" role="tabpanel">
            <div class="card card-filtros shadow-sm">
                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-navbar">
                            <tr>
                                <th>TIPO DE CUENTA</th>
                                <th>NOMBRE DE MEDIO / CUENTA</th>
                                <th class="text-end text-info">SALDO ACTUAL</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-tesoreria"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 4. A COBRAR -->
        <div class="tab-pane fade" id="cobros-pane" role="tabpanel">
            <div class="card card-filtros shadow-sm">
                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-navbar">
                            <tr>
                                <th>ORIGEN</th>
                                <th>DESCRIPCIÓN / CLIENTE</th>
                                <th class="text-center">VENCIMIENTO</th>
                                <th class="text-end text-success">MONTO A COBRAR</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cobros"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 5. A PAGAR -->
        <div class="tab-pane fade" id="pagos-pane" role="tabpanel">
            <div class="card card-filtros shadow-sm">
                <div class="table-responsive" style="max-height: 500px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="sticky-top bg-navbar">
                            <tr>
                                <th>ORIGEN</th>
                                <th>DESCRIPCIÓN / PROVEEDOR</th>
                                <th class="text-center">VENCIMIENTO</th>
                                <th class="text-end text-danger">MONTO A PAGAR</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-pagos"></tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
    .bg-card { background-color: var(--bg-card); border-radius: 8px; }
    .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.05) !important; }
    .row-total { background-color: #000714; }
    .bg-danger-soft { background-color: rgba(220, 53, 69, 0.15) !important; }
    .bg-success-soft { background-color: rgba(40, 167, 69, 0.1) !important; }
    .bg-dark-header { background-color: #001020; }
</style>

<script>
(function() {
    let currentData = null;
    let chartCashflowInstance = null;

    const formatMoney = (val) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val);
    const formatDateStr = (dateStr) => {
        if(!dateStr) return '-';
        const [y, m, d] = dateStr.split('-');
        return `${d}/${m}/${y}`;
    };

    window.cargarCashflow = async function() {
        $('#cfTabsContent').hide();
        $('#cf-loader').show();

        const desde = $('#cf-desde').val();
        const hasta = $('#cf-hasta').val();
        const incVencidos = $('#chk-vencidos').is(':checked') ? 1 : 0;

        if (incVencidos) {
            $('#lbl-partida-desc').text('Liquidez Real + Arrastre Vencido');
        } else {
            $('#lbl-partida-desc').text('Liquidez Real (Sin Arrastre)');
        }

        try {
            const resp = await fetch(`api_cashflow.php?action=get_data&desde=${desde}&hasta=${hasta}&inc_vencidos=${incVencidos}`, { 
                headers: {'X-Requested-With': 'XMLHttpRequest'} 
            });
            const res = await resp.json();

            if (res.success) {
                currentData = res;
                
                $('#badge-teso').text(res.tesoreria.length);
                $('#badge-cobros').text(res.cobros.length);
                $('#badge-pagos').text(res.pagos.length);

                renderizarResumen(res.resumen);
                renderizarTesoreria(res.tesoreria);
                renderizarCobros(res.cobros);
                renderizarPagos(res.pagos);
                renderizarProyeccionDiaria(res, desde, hasta);
                
                if (typeof Chart === 'undefined') {
                    const script = document.querySelector('script[src*="chart.js"]');
                    if (script) script.addEventListener('load', () => renderizarGrafico(res, desde, hasta));
                } else {
                    renderizarGrafico(res, desde, hasta);
                }

                $('#cf-loader').hide();
                $('#cfTabsContent').fadeIn();
            } else {
                Swal.fire('Error', res.error, 'error');
                $('#cf-loader').hide();
            }
        } catch (e) {
            Swal.fire('Error', 'Fallo de conexión al calcular Cashflow.', 'error');
            $('#cf-loader').hide();
        }
    };

    function renderizarResumen(r) {
        const partida = r.liquidez_actual + r.arrastre_cobros - r.arrastre_pagos;
        $('#lbl-partida').text(formatMoney(partida));
        $('#lbl-cobrar').text(formatMoney(r.total_cobrar_periodo));
        $('#lbl-pagar').text(formatMoney(r.total_pagar_periodo));
        
        const posicionFinal = partida + r.total_cobrar_periodo - r.total_pagar_periodo;
        $('#lbl-posicion').text(formatMoney(posicionFinal));
        
        const cardPos = $('#card-posicion');
        if (posicionFinal < 0) {
            cardPos.css('background', 'linear-gradient(145deg, rgba(220,53,69,0.2) 0%, rgba(0,0,0,0) 100%)');
            $('#lbl-posicion').removeClass('text-white text-success').addClass('text-danger');
        } else {
            cardPos.css('background', 'linear-gradient(145deg, rgba(40,167,69,0.2) 0%, rgba(0,0,0,0) 100%)');
            $('#lbl-posicion').removeClass('text-white text-danger').addClass('text-success');
        }
    }

    function renderizarProyeccionDiaria(data, strDesde, strHasta) {
        const tbody = $('#tbody-diaria').empty();
        
        const dateStart = new Date(strDesde + 'T00:00:00');
        const dateEnd = new Date(strHasta + 'T00:00:00');
        
        let mapCobros = {};
        data.cobros.forEach(c => { mapCobros[c.fecha] = (mapCobros[c.fecha] || 0) + parseFloat(c.monto); });
        
        let mapPagos = {};
        data.pagos.forEach(p => { mapPagos[p.fecha] = (mapPagos[p.fecha] || 0) + parseFloat(p.monto); });

        // Saldo Inicial de Partida (Liquidez + Arrastre)
        let saldoAcumulado = data.resumen.liquidez_actual + data.resumen.arrastre_cobros - data.resumen.arrastre_pagos;

        // Fila 0 (Mochila / Partida)
        tbody.append(`
            <tr class="bg-dark border-bottom border-warning">
                <td class="fw-bold text-warning"><i class="fas fa-suitcase me-2"></i>SALDO DE PARTIDA</td>
                <td class="text-end text-muted">LIQUIDEZ + ARRASTRE</td>
                <td class="text-end text-success">+ ${formatMoney(data.resumen.arrastre_cobros)}</td>
                <td class="text-end text-danger">- ${formatMoney(data.resumen.arrastre_pagos)}</td>
                <td class="text-end fw-bold text-warning fs-6">${formatMoney(saldoAcumulado)}</td>
            </tr>
        `);

        // Recorrido Día a Día
        let d = new Date(dateStart);
        while (d <= dateEnd) {
            const fechaStr = d.toISOString().split('T')[0];
            const cobroDia = mapCobros[fechaStr] || 0;
            const pagoDia = mapPagos[fechaStr] || 0;
            
            let saldoInicialDia = saldoAcumulado;
            saldoAcumulado = saldoAcumulado + cobroDia - pagoDia;

            // Clases de color para facilitar la lectura
            let classRow = saldoAcumulado < 0 ? 'bg-danger-soft' : '';
            let classFinal = saldoAcumulado < 0 ? 'text-danger' : 'text-info';

            tbody.append(`
                <tr class="${classRow}">
                    <td class="fw-bold text-white">${formatDateStr(fechaStr)}</td>
                    <td class="text-end text-muted">${formatMoney(saldoInicialDia)}</td>
                    <td class="text-end text-success">${cobroDia > 0 ? '+ ' + formatMoney(cobroDia) : '-'}</td>
                    <td class="text-end text-danger">${pagoDia > 0 ? '- ' + formatMoney(pagoDia) : '-'}</td>
                    <td class="text-end fw-bold ${classFinal}">${formatMoney(saldoAcumulado)}</td>
                </tr>
            `);

            d.setDate(d.getDate() + 1);
        }
    }

    function renderizarTesoreria(tesoreria) {
        const tbody = $('#tbody-tesoreria').empty();
        if (tesoreria.length === 0) {
            tbody.html('<tr><td colspan="3" class="text-center p-4 text-muted">No se detectaron saldos de tesorería con los filtros actuales.</td></tr>');
            return;
        }

        // SEPARACIÓN INTELIGENTE DE ACTIVOS Y PASIVOS BASADA EN EL SIGNO DEL SALDO
        const activos = tesoreria.filter(t => t.saldo >= 0);
        const pasivos = tesoreria.filter(t => t.saldo < 0);

        let totalActivos = 0;
        let totalPasivos = 0;

        // SECCIÓN ACTIVOS
        if (activos.length > 0) {
            tbody.append(`
                <tr class="bg-dark-header border-bottom border-success">
                    <td colspan="3" class="text-success fw-bold p-3">
                        <i class="fas fa-arrow-circle-down me-2"></i>FONDOS DISPONIBLES (ACTIVOS)
                    </td>
                </tr>
            `);
            activos.forEach(t => {
                totalActivos += parseFloat(t.saldo);
                tbody.append(`
                    <tr>
                        <td class="text-muted"><span class="badge bg-secondary">${t.tipo}</span></td>
                        <td class="fw-bold text-white">${t.cuenta}</td>
                        <td class="text-end text-success fw-bold">${formatMoney(t.saldo)}</td>
                    </tr>
                `);
            });
        }

        // SECCIÓN PASIVOS (DESCUBIERTOS / TARJETAS)
        if (pasivos.length > 0) {
            tbody.append(`
                <tr class="bg-dark-header border-bottom border-danger">
                    <td colspan="3" class="text-danger fw-bold p-3 mt-2">
                        <i class="fas fa-arrow-circle-up me-2"></i>OBLIGACIONES DE TESORERÍA (PASIVOS / DESCUBIERTOS)
                    </td>
                </tr>
            `);
            pasivos.forEach(t => {
                totalPasivos += parseFloat(t.saldo);
                tbody.append(`
                    <tr class="bg-danger-soft">
                        <td class="text-muted"><span class="badge bg-dark border border-danger text-danger">${t.tipo}</span></td>
                        <td class="fw-bold text-white">${t.cuenta}</td>
                        <td class="text-end text-danger fw-bold">${formatMoney(t.saldo)}</td>
                    </tr>
                `);
            });
        }

        // TOTALIZADOR NETO
        tbody.append(`
            <tr class="row-total border-top border-info border-2">
                <td colspan="2" class="text-end fw-bold text-muted p-3">LIQUIDEZ NETA (ACTIVOS - PASIVOS):</td>
                <td class="text-end text-info fw-bold fs-5 p-3">${formatMoney(totalActivos + totalPasivos)}</td>
            </tr>
        `);
    }

    function renderizarCobros(cobros) {
        const tbody = $('#tbody-cobros').empty();
        if (cobros.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center p-4">No hay ingresos en el período seleccionado.</td></tr>');
            return;
        }

        let totalGral = 0;
        cobros.forEach(c => {
            totalGral += parseFloat(c.monto);
            const badgeOrigen = c.origen.includes('CHEQUE') ? 'bg-primary' : 'bg-success';
            tbody.append(`
                <tr>
                    <td><span class="badge ${badgeOrigen}">${c.origen}</span></td>
                    <td class="text-white"><small>${c.descripcion}</small></td>
                    <td class="text-center text-muted fw-bold">${formatDateStr(c.fecha)}</td>
                    <td class="text-end text-success fw-bold">${formatMoney(c.monto)}</td>
                </tr>
            `);
        });

        tbody.append(`
            <tr class="row-total border-top border-success border-2">
                <td colspan="3" class="text-end text-white fw-bold">TOTAL A COBRAR EN PERÍODO:</td>
                <td class="text-end text-success fw-bold fs-5">${formatMoney(totalGral)}</td>
            </tr>
        `);
    }

    function renderizarPagos(pagos) {
        const tbody = $('#tbody-pagos').empty();
        if (pagos.length === 0) {
            tbody.html('<tr><td colspan="4" class="text-center p-4">No hay egresos en el período seleccionado.</td></tr>');
            return;
        }

        let totalGral = 0;
        pagos.forEach(p => {
            totalGral += parseFloat(p.monto);
            const badgeOrigen = p.origen.includes('CHEQUE') ? 'bg-warning text-dark' : 'bg-danger';
            tbody.append(`
                <tr>
                    <td><span class="badge ${badgeOrigen}">${p.origen}</span></td>
                    <td class="text-white"><small>${p.descripcion}</small></td>
                    <td class="text-center text-muted fw-bold">${formatDateStr(p.fecha)}</td>
                    <td class="text-end text-danger fw-bold">${formatMoney(p.monto)}</td>
                </tr>
            `);
        });

        tbody.append(`
            <tr class="row-total border-top border-danger border-2">
                <td colspan="3" class="text-end text-white fw-bold">TOTAL A PAGAR EN PERÍODO:</td>
                <td class="text-end text-danger fw-bold fs-5">${formatMoney(totalGral)}</td>
            </tr>
        `);
    }

    function renderizarGrafico(data, strDesde, strHasta) {
        Chart.defaults.color = '#cfd8dc';
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

        if (chartCashflowInstance) chartCashflowInstance.destroy();
        const ctx = document.getElementById('chartCashflow');
        if (!ctx) return;

        const dateStart = new Date(strDesde + 'T00:00:00');
        const dateEnd = new Date(strHasta + 'T00:00:00');
        
        const labels = [];
        const arrFechasStr = [];
        
        let d = new Date(dateStart);
        while (d <= dateEnd) {
            const fechaStr = d.toISOString().split('T')[0];
            arrFechasStr.push(fechaStr);
            labels.push(d.toLocaleDateString('es-AR', {day: '2-digit', month: '2-digit'}));
            d.setDate(d.getDate() + 1);
        }

        const mapCobros = {}; 
        const mapPagos = {}; 
        data.cobros.forEach(c => { mapCobros[c.fecha] = (mapCobros[c.fecha] || 0) + parseFloat(c.monto); });
        data.pagos.forEach(p => { mapPagos[p.fecha] = (mapPagos[p.fecha] || 0) + parseFloat(p.monto); });

        const dataCobros = [];
        const dataPagos = [];
        const dataAcumulado = [];

        // Saldo Inicial de Partida
        let saldoAcumulado = data.resumen.liquidez_actual + data.resumen.arrastre_cobros - data.resumen.arrastre_pagos;

        arrFechasStr.forEach(fecha => {
            const cobroDia = mapCobros[fecha] || 0;
            const pagoDia = mapPagos[fecha] || 0;
            
            saldoAcumulado = saldoAcumulado + cobroDia - pagoDia;

            dataCobros.push(cobroDia);
            dataPagos.push(pagoDia);
            dataAcumulado.push(saldoAcumulado);
        });

        chartCashflowInstance = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        type: 'line',
                        label: 'Saldo Proyectado Real',
                        data: dataAcumulado,
                        borderColor: '#ffc107',
                        backgroundColor: '#ffc107',
                        borderWidth: 3,
                        tension: 0.3,
                        yAxisID: 'y',
                        order: 1
                    },
                    {
                        type: 'bar',
                        label: 'Ingresos (Cobros)',
                        data: dataCobros,
                        backgroundColor: '#28a745',
                        borderRadius: 4,
                        yAxisID: 'y',
                        order: 2
                    },
                    {
                        type: 'bar',
                        label: 'Egresos (Pagos)',
                        data: dataPagos,
                        backgroundColor: '#dc3545',
                        borderRadius: 4,
                        yAxisID: 'y',
                        order: 3
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'top', labels: { color: '#cfd8dc', usePointStyle: true } },
                    tooltip: { callbacks: { label: c => c.dataset.label + ': ' + formatMoney(c.raw) } }
                }
            }
        });
    }

    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (chartCashflowInstance && e.target.id === 'resumen-tab') {
            chartCashflowInstance.resize();
        }
    });

    // ==============================================================
    // GENERADOR PDF NATIVO (Basado en la Proyección Diaria y Split Activos/Pasivos)
    // ==============================================================
    window.imprimirCashflowPDF = function() {
        if (!currentData) return Swal.fire('Atención', 'Calcule primero el Cashflow.', 'warning');

        const activos = currentData.tesoreria.filter(t => t.saldo >= 0);
        const pasivos = currentData.tesoreria.filter(t => t.saldo < 0);

        let html = `
        <html>
        <head>
            <title>Reporte de Cashflow - WEST IT</title>
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; color: #333; margin: 0; padding: 20px; }
                h2 { color: #0d2b45; border-bottom: 2px solid #0d2b45; padding-bottom: 5px; }
                h3 { background-color: #f4f4f4; padding: 5px; margin-top: 20px; border: 1px solid #ccc; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
                th, td { border: 1px solid #ccc; padding: 5px 8px; text-align: left; }
                th { background-color: #eaeaea; }
                .num { text-align: right; }
                .bold { font-weight: bold; }
                .text-success { color: #155724; font-weight: bold;}
                .bg-success-light { background-color: #d4edda; }
                .text-danger { color: #721c24; font-weight: bold;}
                .bg-danger-light { background-color: #f8d7da; }
                .bg-dark { background-color: #333; color: #fff; }
                @media print {
                    @page { margin: 1.5cm; }
                    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                }
            </style>
        </head>
        <body>
            <h2>REPORTE GERENCIAL DE CASHFLOW DIARIO</h2>
            <p><strong>Período Analizado:</strong> ${formatDateStr($('#cf-desde').val())} al ${formatDateStr($('#cf-hasta').val())}</p>
            
            <h3>1. TESORERÍA ACTUAL (DISPONIBILIDAD LÍQUIDA)</h3>
            <table>
                <tr><th>TIPO</th><th>CUENTA</th><th class="num">SALDO</th></tr>`;
        
        if(activos.length > 0) {
            html += `<tr><td colspan="3" class="bold bg-success-light text-success">FONDOS DISPONIBLES (ACTIVOS)</td></tr>`;
            activos.forEach(t => { html += `<tr><td>${t.tipo}</td><td>${t.cuenta}</td><td class="num text-success">${formatMoney(t.saldo)}</td></tr>`; });
        }
        
        if(pasivos.length > 0) {
            html += `<tr><td colspan="3" class="bold bg-danger-light text-danger">OBLIGACIONES (PASIVOS / DESCUBIERTOS)</td></tr>`;
            pasivos.forEach(t => { html += `<tr><td>${t.tipo}</td><td>${t.cuenta}</td><td class="num text-danger">${formatMoney(t.saldo)}</td></tr>`; });
        }

        let partida = currentData.resumen.liquidez_actual + currentData.resumen.arrastre_cobros - currentData.resumen.arrastre_pagos;

        html += `
                <tr><th colspan="2" class="num">LIQUIDEZ NETA (ACTIVOS - PASIVOS):</th><th class="num bold">${formatMoney(currentData.resumen.liquidez_actual)}</th></tr>
            </table>

            <h3>2. SALDO DE PARTIDA (LIQUIDEZ + ARRASTRE VENCIDOS)</h3>
            <table>
                <tr><td>LIQUIDEZ NETA ACTUAL:</td><td class="num">${formatMoney(currentData.resumen.liquidez_actual)}</td></tr>
                <tr><td>ARRASTRE VENCIDO A COBRAR:</td><td class="num text-success">+ ${formatMoney(currentData.resumen.arrastre_cobros)}</td></tr>
                <tr><td>ARRASTRE VENCIDO A PAGAR:</td><td class="num text-danger">- ${formatMoney(currentData.resumen.arrastre_pagos)}</td></tr>
                <tr><td class="bold bg-dark">SALDO INICIAL PROYECTADO:</td><td class="num bold bg-dark">${formatMoney(partida)}</td></tr>
            </table>

            <h3>3. PROYECCIÓN DE FLUJO DIARIO</h3>
        `;

        let saldoAcumulado = partida;
        let timeline = {}; 

        // Agrupar todo por fecha
        currentData.cobros.forEach(c => {
            if(!timeline[c.fecha]) timeline[c.fecha] = { cobros: [], pagos: [] };
            timeline[c.fecha].cobros.push(c);
        });
        currentData.pagos.forEach(p => {
            if(!timeline[p.fecha]) timeline[p.fecha] = { cobros: [], pagos: [] };
            timeline[p.fecha].pagos.push(p);
        });

        const dateStart = new Date($('#cf-desde').val() + 'T00:00:00');
        const dateEnd = new Date($('#cf-hasta').val() + 'T00:00:00');
        let d = new Date(dateStart);

        while (d <= dateEnd) {
            const date = d.toISOString().split('T')[0];
            let obj = timeline[date] || { cobros: [], pagos: [] };
            
            let cobrosDia = obj.cobros;
            let pagosDia = obj.pagos;

            let totalCobros = cobrosDia.reduce((sum, c) => sum + parseFloat(c.monto), 0);
            let totalPagos = pagosDia.reduce((sum, p) => sum + parseFloat(p.monto), 0);

            let saldoInicialDia = saldoAcumulado;
            saldoAcumulado = saldoAcumulado + totalCobros - totalPagos;

            // Si el saldo de caja del día es positivo, está cubierto (Verde), si es negativo, no alcanza (Rojo).
            let bgClass = saldoAcumulado >= 0 ? 'bg-success-light' : 'bg-danger-light';
            let colorClass = saldoAcumulado >= 0 ? 'text-success' : 'text-danger';

            if (totalCobros > 0 || totalPagos > 0) {
                html += `
                <table>
                    <tr>
                        <th colspan="3" class="bg-dark">MOVIMIENTOS DEL: ${formatDateStr(date)}</th>
                        <th class="num bg-dark">Saldo Inicial: ${formatMoney(saldoInicialDia)}</th>
                    </tr>`;

                if(cobrosDia.length > 0) {
                    html += `<tr><td colspan="4" class="bold" style="background:#f0f0f0;">+ INGRESOS (A COBRAR)</td></tr>`;
                    cobrosDia.forEach(c => {
                        html += `<tr><td width="15%">${c.origen}</td><td width="55%">${c.descripcion}</td><td width="15%"></td><td class="num text-success">+ ${formatMoney(c.monto)}</td></tr>`;
                    });
                }

                if(pagosDia.length > 0) {
                    html += `<tr><td colspan="4" class="bold" style="background:#f0f0f0;">- EGRESOS (A PAGAR)</td></tr>`;
                    pagosDia.forEach(p => {
                        html += `<tr><td width="15%">${p.origen}</td><td width="55%">${p.descripcion}</td><td width="15%"></td><td class="num text-danger">- ${formatMoney(p.monto)}</td></tr>`;
                    });
                }

                html += `
                    <tr>
                        <th colspan="3" class="num ${bgClass}">SALDO FINAL DEL DÍA (COBERTURA):</th>
                        <th class="num ${colorClass} ${bgClass}">${formatMoney(saldoAcumulado)}</th>
                    </tr>
                </table>`;
            }
            d.setDate(d.getDate() + 1);
        }

        html += `</body></html>`;

        // Generar ventana y disparar impresión
        let printWin = window.open('', '_blank');
        printWin.document.write(html);
        printWin.document.close();
        printWin.focus();
        setTimeout(() => { printWin.print(); }, 250);
    };

    window.exportarCashflowExcel = function() {
        if (!currentData) return Swal.fire('Atención', 'Calcule primero el Cashflow.', 'warning');
        
        const activos = currentData.tesoreria.filter(t => t.saldo >= 0);
        const pasivos = currentData.tesoreria.filter(t => t.saldo < 0);

        let html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="utf-8"><style>
            table { border-collapse: collapse; font-family: Arial, sans-serif; margin-bottom: 20px; }
            th { background-color: #0d2b45; color: #00ffff; border: 1px solid #445566; padding: 6px; }
            td { border: 1px solid #445566; padding: 6px; }
            .num { text-align: right; } .bold { font-weight: bold; }
            .text-success { color: #155724; font-weight: bold;}
            .bg-success-light { background-color: #d4edda; }
            .text-danger { color: #721c24; font-weight: bold;}
            .bg-danger-light { background-color: #f8d7da; }
        </style></head>
        <body>
            <h2>INFORME DE CASHFLOW PROYECTADO - WEST IT</h2>
            <p><strong>Período:</strong> ${formatDateStr($('#cf-desde').val())} al ${formatDateStr($('#cf-hasta').val())}</p>
            
            <h3>1. RESUMEN DE SALDOS</h3>
            <table>
                <tr><td>LIQUIDEZ NETA ACTUAL (ACTIVOS - PASIVOS):</td><td class="num bold">${currentData.resumen.liquidez_actual.toFixed(2)}</td></tr>
                <tr><td>ARRASTRE A COBRAR (VENCIDOS):</td><td class="num bold text-success">+ ${currentData.resumen.arrastre_cobros.toFixed(2)}</td></tr>
                <tr><td>ARRASTRE A PAGAR (VENCIDOS):</td><td class="num bold text-danger">- ${currentData.resumen.arrastre_pagos.toFixed(2)}</td></tr>
                <tr><td>COBROS DEL PERÍODO:</td><td class="num bold text-success">+ ${currentData.resumen.total_cobrar_periodo.toFixed(2)}</td></tr>
                <tr><td>PAGOS DEL PERÍODO:</td><td class="num bold text-danger">- ${currentData.resumen.total_pagar_periodo.toFixed(2)}</td></tr>
            </table>

            <h3>2. TESORERÍA (SALDOS ACTUALES)</h3>
            <table>
                <tr><th>TIPO</th><th>CUENTA</th><th>SALDO</th></tr>`;
        
        if (activos.length > 0) {
            html += `<tr><td colspan="3" class="bold bg-success-light text-success">ACTIVOS (FONDOS DISPONIBLES)</td></tr>`;
            activos.forEach(t => { html += `<tr><td>${t.tipo}</td><td>${t.cuenta}</td><td class="num">${parseFloat(t.saldo).toFixed(2)}</td></tr>`; });
        }
        
        if (pasivos.length > 0) {
            html += `<tr><td colspan="3" class="bold bg-danger-light text-danger">PASIVOS (OBLIGACIONES)</td></tr>`;
            pasivos.forEach(t => { html += `<tr><td>${t.tipo}</td><td>${t.cuenta}</td><td class="num text-danger">${parseFloat(t.saldo).toFixed(2)}</td></tr>`; });
        }
        
        html += `</table>

            <h3>3. A COBRAR EN EL PERÍODO</h3>
            <table>
                <tr><th>ORIGEN</th><th>DESCRIPCIÓN</th><th>VENCIMIENTO</th><th>MONTO</th></tr>`;
        currentData.cobros.forEach(c => { 
            html += `<tr><td>${c.origen}</td><td>${c.descripcion}</td><td>${formatDateStr(c.fecha)}</td><td class="num">${parseFloat(c.monto).toFixed(2)}</td></tr>`; 
        });
        html += `</table>

            <h3>4. A PAGAR EN EL PERÍODO</h3>
            <table>
                <tr><th>ORIGEN</th><th>DESCRIPCIÓN</th><th>VENCIMIENTO</th><th>MONTO</th></tr>`;
        currentData.pagos.forEach(p => { 
            html += `<tr><td>${p.origen}</td><td>${p.descripcion}</td><td>${formatDateStr(p.fecha)}</td><td class="num">${parseFloat(p.monto).toFixed(2)}</td></tr>`; 
        });
        html += `</table>
        </body></html>`;

        const blob = new Blob(['\ufeff', html], { type: 'application/vnd.ms-excel' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `CashFlow_Detalle_${new Date().getTime()}.xls`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // Auto-Carga inicial
    cargarCashflow();

})();
</script>