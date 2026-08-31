<?php
/**
 * dashboard1.php - DASHBOARD OPERATIVO SIMPLIFICADO (POS / RETAIL)
 * Botones grandes, alertas directas al inicio y validación estricta de permisos.
 */
require_once 'check_access.php';

// ======================================================================
// LÓGICA DE PERMISOS (Exactamente igual que el menú principal)
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

// Configuración de botones dinámicos (Ajustados a los enlaces exactos de menu.json)
$botonesSolapa1 = [
    ['url' => 'newfactura.php', 'icono' => 'fas fa-cash-register', 'texto' => 'FACTURAR', 'color' => '#28a745', 'text_color' => '#fff'],
    ['url' => 'stock_ingreso.php', 'icono' => 'fas fa-truck-loading', 'texto' => 'INGRESO MERCADERÍA', 'color' => '#17a2b8', 'text_color' => '#fff'],
    ['url' => 'clientes_admin.php', 'icono' => 'fas fa-users', 'texto' => 'CLIENTES', 'color' => '#ffc107', 'text_color' => '#000'],
    ['url' => 'proveedores_admin.php', 'icono' => 'fas fa-truck-field', 'texto' => 'PROVEEDORES', 'color' => '#dc3545', 'text_color' => '#fff'],
    ['url' => 'cajas_abrir.php', 'icono' => 'fas fa-lock-open', 'texto' => 'ABRIR CAJA', 'color' => '#0d6efd', 'text_color' => '#fff'],
    ['url' => 'cajas_cerrar.php', 'icono' => 'fas fa-lock', 'texto' => 'CERRAR CAJA', 'color' => '#6c757d', 'text_color' => '#fff'],
    ['url' => 'reporte_cajas_usuarios.php', 'icono' => 'fas fa-file-invoice-dollar', 'texto' => 'INFORME DE CAJA', 'color' => '#6610f2', 'text_color' => '#fff'],
    ['url' => 'comprobantes_emitidos.php', 'icono' => 'fas fa-receipt', 'texto' => 'COMPROBANTES', 'color' => '#00ffff', 'text_color' => '#000']
];

$botonesSolapa3 = [
    ['url' => 'clientes_cuenta_corriente.php', 'icono' => 'fas fa-hand-holding-dollar', 'texto' => 'CTA. CTE. CLIENTES', 'color' => '#28a745', 'text_color' => '#fff'],
    ['url' => 'proveedores_cuentas_corrientes.php', 'icono' => 'fas fa-file-invoice', 'texto' => 'CTA. CTE. PROVEEDORES', 'color' => '#dc3545', 'text_color' => '#fff'],
    ['url' => 'stock_movimientos.php', 'icono' => 'fas fa-boxes-stacked', 'texto' => 'MOVIMIENTOS STOCK', 'color' => '#fd7e14', 'text_color' => '#fff'],
    ['url' => 'reporte_ventas_informe.php', 'icono' => 'fas fa-tags', 'texto' => 'ARTÍCULOS VENDIDOS', 'color' => '#6f42c1', 'text_color' => '#fff']
];
?>

<div class="container-fluid px-4 py-3 fade-in">
    
    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="text-white mb-0 fw-bold">
                <i class="fas fa-store text-accent me-2"></i>PANEL OPERATIVO
            </h3>
            <small class="text-muted">Accesos rápidos y alertas para la gestión diaria</small>
        </div>
        <div class="text-end">
            <button class="btn btn-outline-info rounded-pill fw-bold" onclick="cargarAlertasAPI()">
                <i class="fas fa-sync-alt me-1"></i> ACTUALIZAR ALERTAS
            </button>
        </div>
    </div>

    <!-- PESTAÑAS (NAVPILLS GRANDES) -->
    <ul class="nav nav-pills nav-fill mb-4 gap-2" id="dash1Tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold fs-5 py-3 shadow-sm" id="operaciones-tab" data-bs-toggle="pill" data-bs-target="#operaciones-pane" type="button" role="tab">
                <i class="fas fa-bolt me-2"></i>ACCESOS RÁPIDOS
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold fs-5 py-3 shadow-sm" id="alertas-tab" data-bs-toggle="pill" data-bs-target="#alertas-pane" type="button" role="tab">
                <i class="fas fa-bell me-2"></i>ALERTAS <span class="badge bg-danger ms-1 rounded-pill" id="badge-total-alertas">0</span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold fs-5 py-3 shadow-sm" id="reportes-tab" data-bs-toggle="pill" data-bs-target="#reportes-pane" type="button" role="tab">
                <i class="fas fa-chart-bar me-2"></i>GESTIÓN Y REPORTES
            </button>
        </li>
    </ul>

    <div class="tab-content" id="dash1TabsContent">
        
        <!-- ========================================== -->
        <!-- SOLAPA 1: ACCESOS VISTOSOS Y GRANDES       -->
        <!-- ========================================== -->
        <div class="tab-pane fade show active" id="operaciones-pane" role="tabpanel">
            <div class="row g-4">
                <?php foreach ($botonesSolapa1 as $btn): ?>
                    <?php if (canAccessUrl($btn['url'])): ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <button onclick="loadModule('<?= $btn['url'] ?>')" 
                                class="btn w-100 h-100 btn-huge d-flex flex-column justify-content-center align-items-center shadow" 
                                style="background-color: <?= $btn['color'] ?>; color: <?= $btn['text_color'] ?>;">
                            <i class="<?= $btn['icono'] ?> mb-3" style="font-size: 3.5rem;"></i>
                            <span class="fw-bold fs-5 text-uppercase text-center" style="letter-spacing: 1px;"><?= $btn['texto'] ?></span>
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <?php if (empty(array_filter($botonesSolapa1, function($b) { return canAccessUrl($b['url']); }))): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fas fa-lock fa-3x mb-3"></i>
                        <h5>No posees permisos para acceder a estos módulos.</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SOLAPA 2: ALERTAS VENCIDAS Y STOCK         -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="alertas-pane" role="tabpanel">
            <div id="loader-alertas" class="text-center py-5">
                <div class="spinner-border text-danger" style="width: 3rem; height: 3rem;" role="status"></div>
                <h5 class="text-white mt-3">Buscando alertas...</h5>
            </div>

            <div id="contenido-alertas" style="display: none;">
                <div class="row g-4">
                    
                    <!-- ALERTA: STOCK CRÍTICO -->
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow border-0 border-top border-warning border-4">
                            <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                                <h5 class="text-warning fw-bold mb-0"><i class="fas fa-exclamation-triangle me-2"></i>STOCK CRÍTICO</h5>
                                <span class="badge bg-warning text-dark fs-6" id="badge-stock">0</span>
                            </div>
                            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;" id="list-stock">
                                <!-- JS Injects here -->
                            </div>
                        </div>
                    </div>

                    <!-- ALERTA: COBROS VENCIDOS -->
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow border-0 border-top border-success border-4">
                            <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                                <h5 class="text-success fw-bold mb-0"><i class="fas fa-hand-holding-dollar me-2"></i>COBROS VENCIDOS</h5>
                                <span class="badge bg-success fs-6" id="badge-cobros">0</span>
                            </div>
                            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;" id="list-cobros">
                                <!-- JS Injects here -->
                            </div>
                        </div>
                    </div>

                    <!-- ALERTA: PAGOS VENCIDOS -->
                    <div class="col-12 col-xl-4">
                        <div class="card card-filtros h-100 shadow border-0 border-top border-danger border-4">
                            <div class="card-header bg-transparent border-secondary py-3 d-flex justify-content-between align-items-center">
                                <h5 class="text-danger fw-bold mb-0"><i class="fas fa-file-invoice me-2"></i>PAGOS VENCIDOS</h5>
                                <span class="badge bg-danger fs-6" id="badge-pagos">0</span>
                            </div>
                            <div class="card-body p-0" style="max-height: 450px; overflow-y: auto;" id="list-pagos">
                                <!-- JS Injects here -->
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- SOLAPA 3: CTAS CTES Y REPORTES             -->
        <!-- ========================================== -->
        <div class="tab-pane fade" id="reportes-pane" role="tabpanel">
            <div class="row g-4 justify-content-center">
                <?php foreach ($botonesSolapa3 as $btn): ?>
                    <?php if (canAccessUrl($btn['url'])): ?>
                    <div class="col-6 col-md-4 col-xl-3">
                        <button onclick="loadModule('<?= $btn['url'] ?>')" 
                                class="btn w-100 h-100 btn-huge outline-huge d-flex flex-column justify-content-center align-items-center shadow" 
                                style="border: 3px solid <?= $btn['color'] ?>;">
                            <i class="<?= $btn['icono'] ?> mb-3" style="font-size: 3.5rem; color: <?= $btn['color'] ?>;"></i>
                            <span class="fw-bold fs-5 text-uppercase text-center" style="letter-spacing: 1px;"><?= $btn['texto'] ?></span>
                        </button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty(array_filter($botonesSolapa3, function($b) { return canAccessUrl($b['url']); }))): ?>
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fas fa-lock fa-3x mb-3"></i>
                        <h5>No posees permisos para ver los reportes.</h5>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<style>
    /* Estilos para el Dashboard Simplificado */
    .nav-pills .nav-link {
        background-color: var(--bg-card);
        color: var(--text-muted);
        border: 1px solid var(--input-border);
        transition: all 0.3s ease;
        border-radius: 12px;
    }
    .nav-pills .nav-link:hover {
        background-color: var(--accent-soft);
        color: var(--text-titles);
    }
    .nav-pills .nav-link.active {
        background-color: #000020 !important; /* Azul oscuro corporativo */
        color: #00ffff !important;          /* Cian Accent */
        border-color: #00ffff !important;
        box-shadow: 0 0 15px rgba(0, 255, 255, 0.3);
    }

    /* Botones Gigantes (Solapa 1) */
    .btn-huge {
        min-height: 160px;
        border-radius: 15px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none;
    }
    .btn-huge:hover {
        transform: translateY(-5px) scale(1.02);
        box-shadow: 0 15px 25px rgba(0,0,0,0.5) !important;
        opacity: 0.9;
    }

    /* Botones Gigantes Outline (Solapa 3) */
    .outline-huge {
        background-color: var(--bg-card);
        color: var(--text-titles) !important;
    }
    .outline-huge:hover {
        background-color: var(--accent-soft);
        color: var(--text-titles) !important;
    }

    /* Listas de Alertas */
    .alerta-item {
        transition: background-color 0.2s;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .alerta-item:hover {
        background-color: rgba(255,255,255,0.08);
    }
</style>

<script>
(function() {
    const formatMoney = (val) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(val);
    const formatNum = (val) => new Intl.NumberFormat('es-AR').format(val);

    window.cargarAlertasAPI = async function() {
        $('#contenido-alertas').hide();
        $('#loader-alertas').fadeIn();

        try {
            const resp = await fetch('api_dashboard1.php', { headers: {'X-Requested-With': 'XMLHttpRequest'} });
            const res = await resp.json();

            if (res.success) {
                renderizarStock(res.alertas.stock);
                renderizarCobros(res.alertas.cobros);
                renderizarPagos(res.alertas.pagos);

                // Actualizar contadores
                const totStock = res.alertas.stock.length;
                const totCobros = res.alertas.cobros.length;
                const totPagos = res.alertas.pagos.length;
                
                $('#badge-stock').text(totStock);
                $('#badge-cobros').text(totCobros);
                $('#badge-pagos').text(totPagos);
                $('#badge-total-alertas').text(totStock + totCobros + totPagos);

                $('#loader-alertas').hide();
                $('#contenido-alertas').fadeIn();
            } else {
                Swal.fire('Atención', res.error, 'warning');
                $('#loader-alertas').hide();
            }
        } catch (e) {
            Swal.fire('Error', 'Fallo de conexión al cargar alertas.', 'error');
            $('#loader-alertas').hide();
        }
    };

    function renderizarStock(data) {
        const container = $('#list-stock').empty();
        if (data.length === 0) {
            container.html('<div class="p-5 text-center text-muted"><i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i><br><h5>Stock Óptimo</h5></div>');
            return;
        }
        data.forEach(item => {
            container.append(`
                <div class="p-3 d-flex justify-content-between align-items-center alerta-item">
                    <div class="text-truncate me-2" style="max-width: 75%;">
                        <span class="text-white fw-bold d-block text-truncate">${item.codigo}</span>
                        <small class="text-muted d-block text-truncate">${item.descripcion}</small>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-warning text-dark fs-6 shadow">${formatNum(item.Stock)}</span>
                    </div>
                </div>
            `);
        });
    }

    function renderizarCobros(data) {
        const container = $('#list-cobros').empty();
        if (data.length === 0) {
            container.html('<div class="p-5 text-center text-muted"><i class="fas fa-smile-beam fa-3x mb-3 text-success opacity-50"></i><br><h5>Sin cobros atrasados</h5></div>');
            return;
        }
        data.forEach(item => {
            container.append(`
                <div class="p-3 d-flex justify-content-between align-items-center alerta-item">
                    <div class="text-truncate me-2" style="max-width: 70%;">
                        <span class="text-white fw-bold d-block text-truncate" title="${item.cliente}">${item.cliente}</span>
                        <small class="text-danger fw-bold d-block"><i class="fas fa-calendar-times me-1"></i> Venció el ${item.fecha}</small>
                    </div>
                    <div class="text-end">
                        <span class="text-success fw-bold fs-5">${formatMoney(item.monto)}</span>
                    </div>
                </div>
            `);
        });
    }

    function renderizarPagos(data) {
        const container = $('#list-pagos').empty();
        if (data.length === 0) {
            container.html('<div class="p-5 text-center text-muted"><i class="fas fa-thumbs-up fa-3x mb-3 text-success opacity-50"></i><br><h5>Sin pagos atrasados</h5></div>');
            return;
        }
        data.forEach(item => {
            container.append(`
                <div class="p-3 d-flex justify-content-between align-items-center alerta-item">
                    <div class="text-truncate me-2" style="max-width: 70%;">
                        <span class="text-white fw-bold d-block text-truncate" title="${item.proveedor}">${item.proveedor}</span>
                        <small class="text-danger fw-bold d-block"><i class="fas fa-calendar-times me-1"></i> Venció el ${item.fecha}</small>
                    </div>
                    <div class="text-end">
                        <span class="text-danger fw-bold fs-5">${formatMoney(item.monto)}</span>
                    </div>
                </div>
            `);
        });
    }

    // CARGA INMEDIATA: Al iniciar la pantalla se disparan las alertas
    // para llenar el badge del contador principal sin necesidad de clickear la solapa.
    $(document).ready(function() {
        cargarAlertasAPI();
    });

})();
</script>
