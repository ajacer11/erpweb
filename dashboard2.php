<?php
/**
 * dashboard.php - DASHBOARD PRINCIPAL DEL SISTEMA (SPA)
 * Renderiza la estructura visual y solicita datos a la API.
 * Filtra accesos rápidos según permisos e incluye 4 pestañas gerenciales.
 */
require_once 'check_access.php';

// ======================================================================
// LÓGICA DE PERMISOS PARA BOTONES DE ACCESO RÁPIDO (Basado en menu.php)
// ======================================================================
$isAdmin = isset($_SESSION['es_admin']) && (int)$_SESSION['es_admin'] === 1;
$allowedUrls = [];

if (!$isAdmin) {
    try {
        $stmtPerm = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
        $stmtPerm->execute([$_SESSION['usuario_id']]);
        $row = $stmtPerm->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            $jsonPermisos = json_decode($row['permisos'], true);
            $allowedUrls = $jsonPermisos['allowed_urls'] ?? [];
        }
    } catch (Exception $e) {
        $allowedUrls = [];
    }
}

// Función helper para verificar si un archivo está en los permisos del usuario
function canAccessUrl($url) {
    global $isAdmin, $allowedUrls;
    if ($isAdmin) return true;
    
    $baseTarget = basename($url);
    foreach ($allowedUrls as $allowed) {
        if (basename($allowed) === $baseTarget) {
            return true;
        }
    }
    return false;
}
// ======================================================================
?>

<!-- Carga de Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="container-fluid px-4 py-3 fade-in" style="overflow-x: hidden;">
    
    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white mb-0 fw-light">
                Panel Gerencial, <span class="fw-bold text-accent"><?= htmlspecialchars($_SESSION['usuario_nombre']) ?></span>
            </h3>
            <small class="text-muted"><i class="fas fa-satellite-dish me-1"></i> Información en tiempo real del estado de la empresa</small>
        </div>
        <div class="text-end d-none d-md-block">
            <button class="btn btn-outline-info rounded-pill btn-sm" onclick="cargarDashboardAPI()">
                <i class="fas fa-sync-alt me-1 fa-spin-hover"></i> ACTUALIZAR
            </button>
        </div>
    </div>

    <!-- PESTAÑAS (TABS) - 4 CATEGORÍAS -->
    <ul class="nav nav-tabs mb-4 border-secondary" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold" id="resumen-tab" data-bs-toggle="tab" data-bs-target="#resumen-pane" type="button" role="tab" aria-controls="resumen-pane" aria-selected="true">
                <i class="fas fa-chart-line me-2"></i>OPERACIONES
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-warning" id="cashflow-tab" data-bs-toggle="tab" data-bs-target="#cashflow-pane" type="button" role="tab" aria-controls="cashflow-pane" aria-selected="false">
                <i class="fas fa-money-bill-trend-up me-2"></i>CASHFLOW
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="vendedores-tab" data-bs-toggle="tab" data-bs-target="#vendedores-pane" type="button" role="tab" aria-controls="vendedores-pane" aria-selected="false">
                <i class="fas fa-user-tie me-2"></i>VENDEDORES
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold" id="inventario-tab" data-bs-toggle="tab" data-bs-target="#inventario-pane" type="button" role="tab" aria-controls="inventario-pane" aria-selected="false">
                <i class="fas fa-boxes-stacked me-2"></i>INVENTARIO
            </button>
        </li>
    </ul>

    <!-- LOADER -->
    <div id="dashboard-loader" class="text-center py-5">
        <div class="spinner-border text-accent" style="width: 3rem; height: 3rem;" role="status"></div>
        <p class="text-muted mt-3 fw-bold tracking-widest">OBTENIENDO MÉTRICAS...</p>
    </div>

    <!-- CONTENEDOR DE PESTAÑAS -->
    <div class="tab-content" id="dashboardTabsContent" style="display: none;">
        
        <!-- ========================================== -->
        <!-- PESTAÑA 1: RESUMEN OPERATIVO               -->
        <!-- ========================================== -->
        <div class="tab-pane fade show active" id="resumen-pane" role="tabpanel" aria-labelledby="resumen-tab">
            
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card card-filtros border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-receipt text-muted fs-4 mb-2"></i>
                            <p class="text-muted fw-bold mb-0" style="font-size: 0.70rem;">TICKET PROMEDIO (3M)</p>
                            <h4 class="text-white fw-bold" id="kpi-ticket-promedio">$0.00</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-filtros border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-shopping-cart text-info fs-4 mb-2"></i>
                            <p class="text-muted fw-bold mb-0" style="font-size: 0.70rem;">VENTAS HOY</p>
                            <h4 class="text-info fw-bold" id="kpi-ventas-hoy">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-filtros border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-users text-warning fs-4 mb-2"></i>
                            <p class="text-muted fw-bold mb-0" style="font-size: 0.70rem;">CLIENTES ACTIVOS</p>
                            <h4 class="text-white fw-bold" id="kpi-total-clientes">0</h4>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card card-filtros border-0 shadow-sm h-100">
                        <div class="card-body text-center p-3">
                            <i class="fas fa-cubes text-accent fs-4 mb-2"></i>
                            <p class="text-muted fw-bold mb-0" style="font-size: 0.70rem;">PRODUCTOS ACTIVOS</p>
                            <h4 class="text-white fw-bold" id="kpi-total-productos">0</h4>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-12">
                    <div class="card card-filtros h-100 shadow-sm">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-chart-line text-accent me-2"></i> TENDENCIA DE VENTAS (ÚLTIMOS 7 DÍAS)</h6>
                        </div>
                        <div class="card-body" style="position: relative; height: 300px;">
                            <canvas id="chartTendencia"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN: ACCESOS DIRECTOS SEGURIZADOS -->
            <h6 class="text-muted fw-bold mb-3 ms-1" style="letter-spacing: 1px;"><i class="fas fa-bolt text-accent me-2"></i> ACCESOS RÁPIDOS</h6>
            <div class="row g-3">
                <?php if (canAccessUrl('newfactura.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('newfactura.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-cash-register fa-2x mb-2 text-success"></i><br>
                        <span class="small fw-bold text-white">Facturar</span>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (canAccessUrl('productos_admin.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('productos_admin.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-box fa-2x mb-2 text-info"></i><br>
                        <span class="small fw-bold text-white">Productos</span>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (canAccessUrl('clientes_admin.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('clientes_admin.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-users fa-2x mb-2 text-warning"></i><br>
                        <span class="small fw-bold text-white">Clientes</span>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (canAccessUrl('proveedores_admin.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('proveedores_admin.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-truck-field fa-2x mb-2 text-danger"></i><br>
                        <span class="small fw-bold text-white">Proveedores</span>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (canAccessUrl('reporte_ventas_informe.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('reporte_ventas_informe.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-chart-pie fa-2x mb-2 text-accent"></i><br>
                        <span class="small fw-bold text-white">Inf. Ventas</span>
                    </button>
                </div>
                <?php endif; ?>
                <?php if (canAccessUrl('caja_admin.php')): ?>
                <div class="col-6 col-md-3 col-lg-2">
                    <button onclick="loadModule('caja_admin.php')" class="btn btn-outline-secondary w-100 py-3 border-0 shadow-sm btn-quick-action">
                        <i class="fas fa-cash-register fa-2x mb-2 text-white"></i><br>
                        <span class="small fw-bold text-white">Cajas</span>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- PESTAÑA 2: FINANZAS Y CASHFLOW             -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="cashflow-pane" role="tabpanel" aria-labelledby="cashflow-tab">
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 border-start border-success border-4 shadow-sm h-100 kpi-card">
                        <div class="card-body p-3">
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.70rem;">A COBRAR (CLIENTES)</p>
                            <h5 class="text-success mb-0 fw-bold" id="kpi-saldos-cobrar">$0.00</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 border-start border-danger border-4 shadow-sm h-100 kpi-card">
                        <div class="card-body p-3">
                            <p class="text-muted fw-bold mb-1" style="font-size: 0.70rem;">A PAGAR (PROVEEDORES)</p>
                            <h5 class="text-danger mb-0 fw-bold" id="kpi-saldos-pagar">$0.00</h5>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 shadow-sm h-100 kpi-card" style="background: linear-gradient(145deg, rgba(40,167,69,0.1) 0%, rgba(0,0,0,0) 100%);">
                        <div class="card-body p-3">
                            <p class="text-success fw-bold mb-1" style="font-size: 0.70rem;"><i class="fas fa-clock me-1"></i>VENCIDO (COBROS)</p>
                            <h5 class="text-white mb-0 fw-bold" id="kpi-vencidos-cobrar">$0.00</h5>
                            <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">Vencimientos de Hoy: <span id="kpi-hoy-cobros" class="text-success">$0.00</span></small>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card bg-card border-0 shadow-sm h-100 kpi-card" style="background: linear-gradient(145deg, rgba(220,53,69,0.1) 0%, rgba(0,0,0,0) 100%);">
                        <div class="card-body p-3">
                            <p class="text-danger fw-bold mb-1" style="font-size: 0.70rem;"><i class="fas fa-exclamation-circle me-1"></i>VENCIDO (PAGOS)</p>
                            <h5 class="text-white mb-0 fw-bold" id="kpi-vencidos-pagar">$0.00</h5>
                            <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">Vencimientos de Hoy: <span id="kpi-hoy-pagos" class="text-danger">$0.00</span></small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráfico de Proyección de Cashflow -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="card card-filtros h-100 shadow-sm">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3 d-flex justify-content-between align-items-center">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-chart-column text-warning me-2"></i> PROYECCIÓN DE CASHFLOW (CORTO PLAZO)</h6>
                            <span class="badge bg-dark border border-secondary text-muted">Próximos 15 Días</span>
                        </div>
                        <div class="card-body" style="position: relative; height: 350px;">
                            <canvas id="chartCashflow"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- PESTAÑA 3: RENDIMIENTO VENDEDORES          -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="vendedores-pane" role="tabpanel" aria-labelledby="vendedores-tab">
            <div class="row g-3">
                <div class="col-12 mb-2">
                    <div class="card card-filtros shadow-sm">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-chart-column text-info me-2"></i> VENTAS VS COBROS (ÚLTIMOS 30 DÍAS)</h6>
                        </div>
                        <div class="card-body" style="position: relative; height: 300px;">
                            <canvas id="chartVendedores"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card card-filtros shadow-sm">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-table text-success me-2"></i> DETALLE DE CARTERA Y MOVIMIENTOS</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>VENDEDOR</th>
                                        <th class="text-center">CLIENTES ACTIVOS</th>
                                        <th class="text-end text-info">VENTAS (30D)</th>
                                        <th class="text-end text-success">COBROS (30D)</th>
                                        <th class="text-end text-warning">SALDO A COBRAR (CTA CTE)</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-vendedores">
                                    <tr><td colspan="5" class="text-center p-4">Cargando métricas...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- PESTAÑA 4: INVENTARIO Y STOCK              -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="inventario-pane" role="tabpanel" aria-labelledby="inventario-tab">
            
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-6">
                    <div class="card card-filtros border-0 border-start border-info border-4 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center p-4">
                            <div>
                                <p class="text-muted fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">VALORIZACIÓN DE STOCK (A COSTO)</p>
                                <h3 class="text-accent mb-0 fw-bold" id="kpi-valorizacion">$0.00</h3>
                            </div>
                            <i class="fas fa-vault text-info opacity-50 fa-3x"></i>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <div class="card card-filtros border-0 border-start border-danger border-4 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center p-4">
                            <div>
                                <p class="text-muted fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">PRODUCTOS CON STOCK CRÍTICO</p>
                                <h3 class="text-danger mb-0 fw-bold" id="kpi-stock-bajo">0</h3>
                            </div>
                            <i class="fas fa-exclamation-triangle text-danger opacity-50 fa-3x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <!-- Gráfico de Dona: Rubros -->
                <div class="col-12 col-xl-6">
                    <div class="card card-filtros h-100 shadow-sm">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-chart-pie text-info me-2"></i> DISTRIBUCIÓN DE CAPITAL POR RUBRO</h6>
                        </div>
                        <div class="card-body d-flex justify-content-center align-items-center" style="position: relative; height: 350px;">
                            <canvas id="chartRubros"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Lista de Faltantes -->
                <div class="col-12 col-xl-6">
                    <div class="card card-filtros h-100 shadow-sm d-flex flex-column">
                        <div class="card-header border-bottom border-secondary bg-transparent py-3 d-flex justify-content-between align-items-center">
                            <h6 class="text-white fw-bold mb-0"><i class="fas fa-bell text-danger me-2"></i> ACCIÓN REQUERIDA: FALTANTES</h6>
                            <span class="badge bg-danger rounded-pill" id="badge-stock-bajo">0</span>
                        </div>
                        <div class="card-body p-0" style="overflow-y: auto; max-height: 350px;" id="list-stock-critico"></div>
                        <?php if (canAccessUrl('compras_add.php')): ?>
                        <div class="card-footer border-top border-secondary bg-transparent p-2 text-center">
                            <button class="btn btn-sm btn-outline-danger w-50 fw-bold rounded-pill" onclick="loadModule('compras_add.php')">
                                <i class="fas fa-cart-plus me-1"></i> NUEVA ORDEN COMPRA
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
    .bg-card { background-color: var(--bg-card); border-radius: 8px; }
    .kpi-card { transition: transform 0.2s; }
    .kpi-card:hover { transform: translateY(-3px); }
    .btn-quick-action {
        background-color: var(--bg-card);
        transition: all 0.3s ease;
        border-radius: 10px;
    }
    .btn-quick-action:hover {
        background-color: rgba(0, 255, 255, 0.05);
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.5) !important;
    }
    .hover-effect-list:hover { background-color: rgba(255, 255, 255, 0.05) !important; }
    .fa-spin-hover:hover { animation: fa-spin 2s linear infinite; }
</style>

<script>
(function() {
    let chartRubrosInstance = null;
    let chartTendenciaInstance = null;
    let chartVendedoresInstance = null;
    let chartCashflowInstance = null;

    const formatMoney = (val) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val);
    const formatNum = (val) => new Intl.NumberFormat('es-AR').format(val);

    window.cargarDashboardAPI = async function() {
        $('#dashboardTabsContent').hide();
        $('#dashboard-loader').show();

        try {
            const resp = await fetch('api_dashboard.php', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();

            if (res.success) {
                renderizarKPIs(res.kpis);
                renderizarListaAlertas(res.lists.stock_critico);
                renderizarTablaVendedores(res.lists.vendedores_stats);
                
                if (typeof Chart === 'undefined') {
                    const script = document.querySelector('script[src*="chart.js"]');
                    if (script) script.addEventListener('load', () => renderizarGraficos(res.charts, res.lists.vendedores_stats, res.kpis));
                } else {
                    renderizarGraficos(res.charts, res.lists.vendedores_stats, res.kpis);
                }

                $('#dashboard-loader').hide();
                $('#dashboardTabsContent').fadeIn();
            } else {
                Swal.fire('Atención', 'No se pudieron cargar algunas métricas: ' + res.error, 'warning');
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'Fallo de conexión al cargar el dashboard.', 'error');
        }
    };

    function renderizarKPIs(kpis) {
        // Finanzas
        $('#kpi-saldos-cobrar').text(formatMoney(kpis.saldos_cobrar));
        $('#kpi-saldos-pagar').text(formatMoney(kpis.saldos_pagar));
        $('#kpi-vencidos-cobrar').text(formatMoney(kpis.vencidos_cobrar));
        $('#kpi-vencidos-pagar').text(formatMoney(kpis.vencidos_pagar));
        $('#kpi-hoy-cobros').text(formatMoney(kpis.hoy_cobros));
        $('#kpi-hoy-pagos').text(formatMoney(kpis.hoy_pagos));

        // Operaciones
        $('#kpi-ticket-promedio').text(formatMoney(kpis.ticket_promedio));
        $('#kpi-ventas-hoy').text(formatNum(kpis.ventas_hoy));
        $('#kpi-total-clientes').text(formatNum(kpis.total_clientes));
        $('#kpi-total-productos').text(formatNum(kpis.total_productos));
        
        // Inventario
        $('#kpi-valorizacion').text(formatMoney(kpis.valorizacion));
        $('#kpi-stock-bajo').text(formatNum(kpis.stock_bajo));
        $('#badge-stock-bajo').text(formatNum(kpis.stock_bajo));
    }

    function renderizarListaAlertas(productos) {
        const container = $('#list-stock-critico').empty();
        if (!productos || productos.length === 0) {
            container.html(`
                <div class="p-4 text-center text-muted">
                    <i class="fas fa-check-circle text-success fa-3x mb-3 opacity-50"></i><br>
                    <p class="mb-0">Todo en orden. No hay alertas de stock.</p>
                </div>
            `);
            return;
        }

        productos.forEach(p => {
            container.append(`
                <div class="bg-transparent border-bottom border-secondary px-4 py-3 d-flex justify-content-between align-items-center hover-effect-list">
                    <div class="text-truncate me-2" style="max-width: 75%;">
                        <span class="text-white fw-bold d-block text-truncate">${p.codigo}</span>
                        <small class="text-muted d-block text-truncate">${p.descripcion}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-danger fs-6">${formatNum(p.Stock)}</span>
                    </div>
                </div>
            `);
        });
    }

    function renderizarTablaVendedores(vendedores) {
        const tbody = $('#tbody-vendedores').empty();
        
        if (!vendedores || vendedores.length === 0) {
            tbody.html('<tr><td colspan="5" class="text-center text-muted p-4"><i class="fas fa-info-circle me-1"></i>No hay estadísticas de vendedores disponibles.</td></tr>');
            return;
        }

        vendedores.forEach(v => {
            tbody.append(`
                <tr>
                    <td class="fw-bold text-white"><i class="fas fa-user-circle text-secondary me-2"></i>${v.vendedor_nombre}</td>
                    <td class="text-center text-muted">${formatNum(v.cant_clientes)}</td>
                    <td class="text-end text-info fw-bold">${formatMoney(v.ventas_30d)}</td>
                    <td class="text-end text-success fw-bold">${formatMoney(v.cobros_30d)}</td>
                    <td class="text-end text-warning fw-bold">${formatMoney(v.saldo_clientes)}</td>
                </tr>
            `);
        });
    }

    function renderizarGraficos(charts, vendedores, kpis) {
        Chart.defaults.color = '#cfd8dc';
        Chart.defaults.font.family = "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif";

        // GRÁFICO 1: Dona de Rubros (Inventario)
        if (chartRubrosInstance) chartRubrosInstance.destroy();
        const ctxRubros = document.getElementById('chartRubros');
        if (ctxRubros && charts.rubros && charts.rubros.length > 0) {
            chartRubrosInstance = new Chart(ctxRubros, {
                type: 'doughnut',
                data: {
                    labels: charts.rubros.map(r => r.label),
                    datasets: [{
                        data: charts.rubros.map(r => r.value),
                        backgroundColor: ['#00ffffcc', '#17a2b8cc', '#28a745cc', '#ffc107cc', '#dc3545cc'],
                        borderColor: ['#00ffff', '#17a2b8', '#28a745', '#ffc107', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '75%',
                    plugins: {
                        legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: { callbacks: { label: c => ' ' + formatMoney(c.parsed) } }
                    }
                }
            });
        }

        // GRÁFICO 2: Línea Tendencia Ventas (Resumen)
        if (chartTendenciaInstance) chartTendenciaInstance.destroy();
        const ctxTend = document.getElementById('chartTendencia');
        if (ctxTend && charts.ventas_tendencia && charts.ventas_tendencia.length > 0) {
            chartTendenciaInstance = new Chart(ctxTend, {
                type: 'line',
                data: {
                    labels: charts.ventas_tendencia.map(t => t.label),
                    datasets: [{
                        label: 'Ventas Diarias',
                        data: charts.ventas_tendencia.map(t => t.value),
                        borderColor: '#00ffff',
                        backgroundColor: 'rgba(0, 255, 255, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#000714',
                        pointBorderColor: '#00ffff',
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: c => ' ' + formatMoney(c.parsed.y) } }
                    }
                }
            });
        }

        // GRÁFICO 3: Barras de Vendedores (Vendedores)
        if (chartVendedoresInstance) chartVendedoresInstance.destroy();
        const ctxVend = document.getElementById('chartVendedores');
        if (ctxVend && vendedores && vendedores.length > 0) {
            const topVendedores = vendedores.slice(0, 10);
            chartVendedoresInstance = new Chart(ctxVend, {
                type: 'bar',
                data: {
                    labels: topVendedores.map(v => v.vendedor_nombre),
                    datasets: [
                        {
                            label: 'Ventas (30D)',
                            data: topVendedores.map(v => v.ventas_30d),
                            backgroundColor: '#17a2b8',
                            borderRadius: 4
                        },
                        {
                            label: 'Cobros (30D)',
                            data: topVendedores.map(v => v.cobros_30d),
                            backgroundColor: '#28a745',
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#cfd8dc' } },
                        tooltip: { callbacks: { label: c => c.dataset.label + ': ' + formatMoney(c.raw) } }
                    }
                }
            });
        }

        // GRÁFICO 4: Cashflow Proyección 15 Días (Cashflow)
        if (chartCashflowInstance) chartCashflowInstance.destroy();
        const ctxCashflow = document.getElementById('chartCashflow');
        if (ctxCashflow) {
            const labelsCashflow = [];
            const dataCobros = [];
            const dataPagos = [];
            
            // Recorremos los próximos 15 días y cruzamos con la DB
            for (let i = 0; i < 15; i++) {
                let d = new Date(); 
                d.setDate(d.getDate() + i);
                // Ajustar al timezone local evitando problemas de UTC
                let offset = d.getTimezoneOffset() * 60000;
                let localDate = new Date(d.getTime() - offset);
                let fechaYMD = localDate.toISOString().split('T')[0]; // Formato YYYY-MM-DD
                
                labelsCashflow.push(i === 0 ? 'HOY' : d.toLocaleDateString('es-AR', {day: '2-digit', month: '2-digit'}));
                
                // Buscar si existe monto para esta fecha en los arrays de la API
                let cobroDia = charts.cashflow_cobros?.find(c => c.fecha === fechaYMD);
                let pagoDia = charts.cashflow_pagos?.find(p => p.fecha === fechaYMD);
                
                dataCobros.push(cobroDia ? parseFloat(cobroDia.monto) : 0);
                dataPagos.push(pagoDia ? parseFloat(pagoDia.monto) : 0);
            }

            chartCashflowInstance = new Chart(ctxCashflow, {
                type: 'bar',
                data: {
                    labels: labelsCashflow,
                    datasets: [
                        { label: 'Proyección Cobros (Ingresos)', data: dataCobros, backgroundColor: '#28a745', borderRadius: 4 },
                        { label: 'Proyección Pagos (Egresos)', data: dataPagos, backgroundColor: '#dc3545', borderRadius: 4 }
                    ]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' }, ticks: { callback: v => '$' + v } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { color: '#cfd8dc' } },
                        tooltip: { callbacks: { label: c => c.dataset.label + ': ' + formatMoney(c.raw) } }
                    }
                }
            });
        }
    }

    // Arrancar la carga inicial
    cargarDashboardAPI();

    // Redibujar gráficos si se cambia de pestaña (Workaround para el resize de ChartJS en Tabs ocultas)
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        if (chartTendenciaInstance) chartTendenciaInstance.resize();
        if (chartRubrosInstance) chartRubrosInstance.resize();
        if (chartVendedoresInstance) chartVendedoresInstance.resize();
        if (chartCashflowInstance) chartCashflowInstance.resize();
    });

})();
</script>