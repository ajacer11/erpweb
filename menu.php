<?php
// menu.php - RENDERIZADO DINÁMICO SEGÚN PERMISOS
// Hereda estilos de stylesglobal.css cargado en index.php

require_once 'db.php'; // Necesario para consultar permisos

$empresaRazon = $_SESSION['tenant_razon'] ?? 'EMPRESA';
$empresaSucursal = $_SESSION['tenant_razon'] ?? '';
$empresaLogoUri = null;
try {
    $empresaDb = $pdo->query("SELECT razon FROM datosempresa ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (!empty($empresaDb['razon'])) {
        $empresaRazon = $empresaDb['razon'];
    }

    $logoBlob = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1")->fetchColumn();
    if (!empty($logoBlob)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $logoMime = $finfo->buffer($logoBlob) ?: 'image/png';
        $empresaLogoUri = 'data:' . $logoMime . ';base64,' . base64_encode($logoBlob);
    }
} catch (Throwable $e) {
    error_log('menu.php: no se pudo cargar la identidad visual de la empresa: ' . $e->getMessage());
}
// Multidepósito: se muestra el logo del depósito de trabajo; si no tiene, queda el de configuración.
$empresaLogoDefaultUri = $empresaLogoUri;
try {
    require_once 'stock_deposito_helpers.php';
    if (multidepositoHabilitado($pdo)) {
        $empresaLogoUri = logoDepositoUrl($pdo, depositoDeTrabajo($pdo, (string) ($_SESSION['usuario_nombre'] ?? ''))) ?? $empresaLogoUri;
    }
} catch (Throwable $e) {
    // Sin depósito elegido todavía: queda el logo de configuración.
}

$version = (int)($_SESSION['version'] ?? 1);
$version = $version === 2 ? 2 : 1;
$_SESSION['version'] = $version;
$json_path = 'menu' . $version . '.json';
$full_menu_items = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];
$menu_items = [];

// Módulos que se contratan aparte (tenant_addons en la maestra): los ítems con "addon"
// solo aparecen si la empresa lo tiene habilitado. check_access.php bloquea la URL igual.
require_once 'web_helpers.php';
$addonsEmpresa = webAddonsDeEmpresa($pdo_master, (string) ($_SESSION['tenant_cuit'] ?? ''));
if (!function_exists('quitarItemsSinAddon')) {
    function quitarItemsSinAddon(array $items, array $addons): array
    {
        $res = [];
        foreach ($items as $item) {
            if (!empty($item['addon']) && !in_array($item['addon'], $addons, true)) continue;
            if (!empty($item['children'])) $item['children'] = quitarItemsSinAddon($item['children'], $addons);
            $res[] = $item;
        }
        return $res;
    }
}
$full_menu_items = quitarItemsSinAddon($full_menu_items, $addonsEmpresa);

// LÓGICA DE PERMISOS
if (isset($_SESSION['es_admin']) && (int) $_SESSION['es_admin'] === 1) {
    // 1. Administrador: Ve todo
    $menu_items = $full_menu_items;

    // Administrador no tiene restricciones especiales — limpiar/resetear a todo permitido
    $_SESSION['pe_precioscosto'] = 0;
    $_SESSION['pe_unvendedor'] = 0;
    $_SESSION['pe_idvendedor'] = 0;
    $_SESSION['pe_modificarprecios'] = 0;
    $_SESSION['pe_facturarpedidos'] = 0;
    $_SESSION['pe_modificarfacturas'] = 0;
    // Permisos positivos (permitir X): el admin siempre puede, a diferencia de las
    // restricciones de arriba (que van a 0 = sin restricción).
    $_SESSION['pe_editardescripcionfacturar'] = 1;
    $_SESSION['pe_editardescripcionpedidos'] = 1;
    $_SESSION['pe_editardescripcionpresupuestos'] = 1;

} else {
    // 2. Usuario Normal: Filtramos según DB
    try {
        $stmtPerm = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
        $stmtPerm->execute([$_SESSION['usuario_id']]);
        $row = $stmtPerm->fetch(PDO::FETCH_ASSOC);

        $allowed_ids = [];
        if ($row) {
            $jsonPermisos = json_decode($row['permisos'], true);
            $allowed_ids = $jsonPermisos['allowed_ids'] ?? [];
        }

        // Función recursiva para filtrar el árbol
        function filterMenuItems($items, $allowed_ids)
        {
            $result = [];
            foreach ($items as $item) {
                if (in_array($item['id'], $allowed_ids)) {
                    if (!empty($item['children'])) {
                        $filteredChildren = filterMenuItems($item['children'], $allowed_ids);
                        if (empty($filteredChildren) && empty($item['url'])) {
                            continue;
                        }
                        $item['children'] = $filteredChildren;
                    }
                    $result[] = $item;
                }
            }
            return $result;
        }

        $menu_items = filterMenuItems($full_menu_items, $allowed_ids);

        // Compatibilidad: los registros de permisos guardados antes de que
        // existiera el árbol de "Dashboards" en el menú no van a tener
        // NINGUNO de estos IDs, y sin este fallback esos usuarios se
        // quedarían sin dashboards. Pero si el registro SÍ menciona algún
        // ID de dashboard (fue guardado con la niveles_acceso.php actual),
        // hay que respetar el filtro tal cual quedó armado — incluye que
        // un panel puntual (p.ej. Panel Gerencial) haya sido deshabilitado
        // a propósito.
        $dashboardRelatedIds = ['dashboards', 'dash_gerencial', 'dash_rapido'];
        $tieneDashboardsEnPermisos = count(array_intersect($dashboardRelatedIds, $allowed_ids)) > 0;
        if ($version === 2 && !$tieneDashboardsEnPermisos) {
            foreach ($full_menu_items as $rootItem) {
                if (($rootItem['id'] ?? '') === 'dashboards') {
                    array_unshift($menu_items, $rootItem);
                    break;
                }
            }
        }

        // ----------------------------------------------------------------
        // PERMISOS ESPECIALES — leer de DB y almacenar en sesión
        // Se usa el nombre de usuario (igual que el sistema de escritorio)
        // ----------------------------------------------------------------
        $stmtPE = $pdo->prepare("SELECT * FROM permisosespeciales WHERE usuario = ? LIMIT 1");
        $stmtPE->execute([$_SESSION['usuario_nombre']]);
        $pe = $stmtPE->fetch(PDO::FETCH_ASSOC);

        if ($pe) {
            $_SESSION['pe_precioscosto'] = (int) $pe['precioscosto'];
            $_SESSION['pe_unvendedor'] = (int) $pe['unvendedor'];
            $_SESSION['pe_idvendedor'] = (int) $pe['idvendedor'];
            $_SESSION['pe_modificarprecios'] = (int) $pe['modificarprecios'];
            $_SESSION['pe_facturarpedidos'] = (int) $pe['facturarpedidos'];
            $_SESSION['pe_modificarfacturas'] = (int) $pe['modificarfacturas'];
            $_SESSION['pe_editardescripcionfacturar'] = (int) ($pe['editardescripcionfacturar'] ?? 0);
            $_SESSION['pe_editardescripcionpedidos'] = (int) ($pe['editardescripcionpedidos'] ?? 0);
            $_SESSION['pe_editardescripcionpresupuestos'] = (int) ($pe['editardescripcionpresupuestos'] ?? 0);
        } else {
            // Sin registro → sin restricciones especiales
            $_SESSION['pe_precioscosto'] = 0;
            $_SESSION['pe_unvendedor'] = 0;
            $_SESSION['pe_idvendedor'] = 0;
            $_SESSION['pe_modificarprecios'] = 0;
            $_SESSION['pe_facturarpedidos'] = 0;
            $_SESSION['pe_modificarfacturas'] = 0;
            $_SESSION['pe_editardescripcionfacturar'] = 0;
            $_SESSION['pe_editardescripcionpedidos'] = 0;
            $_SESSION['pe_editardescripcionpresupuestos'] = 0;
        }

    } catch (Exception $e) {
        error_log("Error cargando menú: " . $e->getMessage());
        $menu_items = [];
    }
}

function renderMenuItem($item)
{
    $hasChildren = !empty($item['children']);
    $url = !empty($item['url']) ? htmlspecialchars($item['url']) : '#';

    $icon = "";
    if (!empty($item['icon'])) {
        $icon = '<i class="' . htmlspecialchars($item['icon']) . '"></i> ';
    }

    if (!$hasChildren) {
        // Enlace simple con clase nav-link-ajax para el router JS
        echo '<li><a class="dropdown-item nav-link-ajax" href="' . $url . '">' . $icon . htmlspecialchars($item['text']) . '</a></li>';
    } else {
        // Submenú
        echo '<li class="dropdown-submenu">';
        echo '<a class="dropdown-item dropdown-toggle d-flex justify-content-between align-items-center" href="' . $url . '">';
        echo '<span>' . $icon . htmlspecialchars($item['text']) . '</span>';
        echo '<i class="fa-solid fa-caret-right ms-2 opacity-50"></i>';
        echo '</a>';
        echo '<ul class="dropdown-menu shadow">';
        foreach ($item['children'] as $child) {
            renderMenuItem($child);
        }
        echo '</ul>';
        echo '</li>';
    }
}
?>

<!-- Barra Superior de Estado -->
<div class="status-bar">
    <div class="status-context">
        CAJA: <span id="status-caja" class="ms-3 badge bg-secondary"
            style="font-size: 9px; padding: 4px 8px; cursor: pointer;" onclick="checkCajaStatus()">
            <i class="fas fa-spinner fa-spin me-1"></i> VERIFICANDO CAJA...
        </span>
        <span id="status-deposito-wrap" class="d-none">
            DEPÓSITO: <span id="status-deposito" class="ms-1 badge bg-secondary"
                style="font-size: 9px; padding: 4px 8px;" onclick="onClickStatusDeposito()">
                <i class="fas fa-spinner fa-spin me-1"></i> VERIFICANDO...
            </span>
        </span>
        <span class="status-date"><i class="fa-solid fa-calendar-day me-1"></i> <?= date('d/m/Y') ?></span>
    </div>
    <div class="status-company" title="Empresa activa: <?= htmlspecialchars($empresaRazon) ?>">
        <img id="status-company-logo" src="<?= htmlspecialchars((string) $empresaLogoUri) ?>" alt="Logo de <?= htmlspecialchars($empresaRazon) ?>"
            data-default="<?= htmlspecialchars((string) $empresaLogoDefaultUri) ?>"<?= $empresaLogoUri ? '' : ' class="d-none"' ?>>
        <span id="status-company-placeholder" class="status-company-placeholder<?= $empresaLogoUri ? ' d-none' : '' ?>"><i class="fa-solid fa-building"></i></span>
        <span class="status-company-copy">
            <strong><?= htmlspecialchars($empresaRazon) ?></strong>
            <?php if ($empresaSucursal && strcasecmp($empresaSucursal, $empresaRazon) !== 0): ?>
                <small><?= htmlspecialchars($empresaSucursal) ?></small>
            <?php endif; ?>
        </span>
    </div>
    <div class="status-actions d-flex align-items-center">
        <button type="button" id="btn-ia-soporte" onclick="abrirChatIA()"
            class="btn btn-sm btn-outline-light ms-2" title="Asistente de soporte">
            <i class="fa-solid fa-robot"></i>
        </button>
        <button type="button" id="btn-ayuda-pantalla" onclick="abrirAyudaPantalla()"
            class="btn btn-sm btn-outline-light ms-2" title="Ayuda de esta pantalla">
            <i class="fa-solid fa-circle-question"></i>
        </button>
        <button onclick="toggleTheme()" class="btn btn-sm btn-outline-light ms-2">
            <i id="theme-icon" class="fas fa-moon"></i>
        </button>
        <span id="clock-small">00:00:00</span>
        <a href="logout.php" class="btn-exit-fast">SALIR</a>
    </div>
</div>

<!-- Modal de Ayuda Contextual -->
<div class="modal fade" id="modalAyudaPantalla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ayudaPantallaTitulo"><i class="fa-solid fa-circle-question me-2"></i>Ayuda</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="ayudaPantallaBody">
                <div class="text-center text-muted py-4">
                    <i class="fas fa-spinner fa-spin me-1"></i> Cargando ayuda...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Chat con el Asistente de Soporte (IA) -->
<div class="modal fade" id="modalChatIA" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content" style="height: 80vh;">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-robot me-2"></i>Asistente de Soporte</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body d-flex flex-column p-0">
                <div class="px-3 pt-2">
                    <small class="text-muted">Te ayudo a usar el sistema: qué hace cada pantalla y cómo se usa. No tengo acceso a tus datos reales (clientes, ventas, stock, etc.).</small>
                </div>
                <div id="iaChatMensajes" class="flex-grow-1 overflow-auto px-3 py-2" style="min-height: 0;"></div>
                <div id="iaChatSugerencias" class="px-3 pb-2 d-flex flex-wrap gap-2"></div>
                <div class="border-top px-3 pt-1">
                    <small class="text-muted" style="font-size: 10px;"><i class="fas fa-triangle-exclamation me-1"></i>Las respuestas las genera una IA y pueden tener errores: verificá los pasos importantes antes de aplicarlos.</small>
                </div>
                <div class="p-2 d-flex gap-2">
                    <input type="text" id="iaChatInput" class="form-control" placeholder="Escribí tu consulta sobre el sistema..." maxlength="600">
                    <button type="button" class="btn btn-info" id="iaChatEnviar" onclick="enviarMensajeIA()">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navbar Principal -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-erp">
    <div class="container-fluid p-0">
        <!-- AGREGADO: Clase nav-link-ajax para navegación SPA -->
        <?php $dashboard = "dashboard" . $version . ".php"; ?>
        <a class="navbar-brand nav-link-ajax" href="<?php echo $dashboard; ?>">WEST IT</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMain"
            aria-controls="navMain" aria-expanded="false" aria-label="Abrir menú principal">
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav me-auto">
                <?php foreach ($menu_items as $root): ?>
                    <li class="nav-item <?= !empty($root['children']) ? 'dropdown' : '' ?>">
                        <?php $rootIcon = !empty($root['icon']) ? '<i class="' . htmlspecialchars($root['icon']) . ' me-1"></i> ' : ''; ?>

                        <a class="nav-link <?= !empty($root['children']) ? 'dropdown-toggle' : 'nav-link-ajax' ?>"
                            href="<?= !empty($root['url']) ? $root['url'] : '#' ?>" <?= !empty($root['children']) ? 'data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside"' : '' ?>>
                            <?= $rootIcon . htmlspecialchars($root['text']) ?>
                        </a>

                        <?php if (!empty($root['children'])): ?>
                            <ul class="dropdown-menu shadow">
                                <?php foreach ($root['children'] as $child)
                                    renderMenuItem($child); ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <ul class="navbar-nav ms-auto">
                <li class="nav-item dropdown user-nav-item">
                    <a class="nav-link dropdown-toggle" href="#" id="userDrop" role="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user-circle me-1"></i>
                        <?= isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'USUARIO' ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="cambiar_password.php"><i class="fa-solid fa-key"></i> Cambiar
                                Password</a></li>
                        <li><a class="dropdown-item nav-link-ajax" href="ayuda.php"><i class="fa-solid fa-question-circle"></i> Ayuda
                                del Sistema</a></li>
                        <li>
                            <hr class="dropdown-divider bg-secondary">
                        </li>
                        <li><a class="dropdown-item text-danger fw-bold" href="logout.php"><i
                                    class="fa-solid fa-power-off"></i> CERRAR SESIÓN</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Scripts Específicos del Menú (Reloj y Comportamiento Submenú) -->
<!-- NO INCLUIR BOOTSTRAP AQUÍ, YA ESTÁ EN INDEX.PHP -->
<script>
    function tick() {
        const el = document.getElementById('clock-small');
        if (el) el.textContent = new Date().toLocaleTimeString();
    }
    setInterval(tick, 1000); tick();

    // Soporte para submenús
    document.querySelectorAll('.dropdown-submenu > a').forEach(function (element) {
        element.addEventListener('click', function (e) {
            // Solo prevenimos comportamiento default si es necesario
            e.preventDefault();
            e.stopPropagation();

            // Cerrar otros abiertos
            let parent = this.closest('.dropdown-menu');
            if (parent) {
                parent.querySelectorAll('.dropdown-submenu .dropdown-menu').forEach(menu => {
                    if (menu !== this.nextElementSibling) menu.style.display = 'none';
                });
            }

            let nextEl = this.nextElementSibling;
            if (nextEl && nextEl.classList.contains('dropdown-menu')) {
                nextEl.style.display = (nextEl.style.display === 'block') ? 'none' : 'block';
            }
        });
    });

    // Al navegar a un módulo final, cerrar submenús, dropdowns y hamburguesa.
    document.querySelectorAll('#navMain .nav-link-ajax').forEach(function (link) {
        link.addEventListener('click', function () {
            document.querySelectorAll('#navMain .dropdown-submenu > .dropdown-menu').forEach(function (submenu) {
                submenu.style.display = 'none';
            });

            document.querySelectorAll('#navMain [data-bs-toggle="dropdown"]').forEach(function (toggle) {
                const dropdown = bootstrap.Dropdown.getInstance(toggle);
                if (dropdown) dropdown.hide();
            });

            const navMain = document.getElementById('navMain');
            if (navMain && navMain.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(navMain, { toggle: false }).hide();
            }
        });
    });
    async function checkCajaStatus() {
        try {
            const resp = await fetch('api_cajas.php?action=get_current_status', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            const el = document.getElementById('status-caja');

            // Soportar estructura según si la API devuelve en 'data' o 'caja'
            const cajaObj = res.data || res.caja;

            if (res.success && cajaObj && cajaObj.id) {
                el.className = 'ms-3 badge bg-success text-dark fw-bold border border-success';
                el.innerHTML = `<i class="fas fa-lock-open me-1"></i> CAJA ABIERTA: ${cajaObj.nombre.toUpperCase()}`;
            } else {
                el.className = 'ms-3 badge bg-danger text-white fw-bold border border-danger shadow-sm';
                el.innerHTML = `<i class="fas fa-lock me-1"></i> CAJA CERRADA`;
            }
        } catch (e) {
            console.error("Error consultando estado de caja", e);
            const el = document.getElementById('status-caja');
            if (el) {
                el.className = 'ms-3 badge bg-warning text-dark';
                el.innerHTML = `<i class="fas fa-exclamation-triangle me-1"></i> ESTADO DESCONOCIDO`;
            }
        }
    }
    // Logo del depósito de trabajo; sin logo propio vuelve al de configuración.
    function actualizarLogoEncabezado(logoDeposito) {
        const img = document.getElementById('status-company-logo');
        const ph = document.getElementById('status-company-placeholder');
        if (!img) return;
        const src = logoDeposito || img.dataset.default || '';
        if (src) img.src = src;
        img.classList.toggle('d-none', !src);
        if (ph) ph.classList.toggle('d-none', !!src);
    }

    async function checkDepositoStatus() {
        try {
            const resp = await fetch('api_depositos.php?action=mi_estado', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const res = await resp.json();
            const wrap = document.getElementById('status-deposito-wrap');
            const el = document.getElementById('status-deposito');
            if (!res.success || !res.habilitado) {
                wrap.classList.add('d-none');
                return;
            }
            wrap.classList.remove('d-none');
            window.__depositoEstado = res;
            actualizarLogoEncabezado(res.logo);

            if (res.requiere_seleccion) {
                el.className = 'ms-1 badge bg-danger text-white fw-bold border border-danger shadow-sm';
                el.style.cursor = 'pointer';
                el.innerHTML = '<i class="fas fa-triangle-exclamation me-1"></i> ELEGIR DEPÓSITO';
                // No esperamos a que lo toque: lo mandamos derecho al selector.
                onClickStatusDeposito();
            } else {
                el.className = 'ms-1 badge bg-success text-dark fw-bold border border-success';
                el.style.cursor = res.puede_cambiar ? 'pointer' : 'default';
                el.innerHTML = `<i class="fas fa-warehouse me-1"></i> ${(res.nombre_deposito || '').toUpperCase()}`;
                avisarDepositoActivoSiCorresponde(res);
            }
        } catch (e) {
            console.error('Error consultando depósito de trabajo', e);
        }
    }

    // Si el usuario tiene más de un depósito disponible pero ya tiene uno fijado,
    // no le pedimos que elija — solo le avisamos en qué depósito está trabajando,
    // una vez por sesión de navegador (no en cada recarga/navegación interna).
    function avisarDepositoActivoSiCorresponde(res) {
        const permitidos = res.depositos_permitidos || [];
        if (permitidos.length <= 1) return;

        let yaAvisado = false;
        try { yaAvisado = sessionStorage.getItem('avisoDepositoActivo') === String(res.id_deposito); } catch (e) {}
        if (yaAvisado) return;

        Swal.fire({
            icon: 'info',
            title: 'Depósito de trabajo',
            html: `Estás trabajando en el depósito <b>${(res.nombre_deposito || '').toUpperCase()}</b>.`,
            confirmButtonText: res.puede_cambiar ? 'CAMBIAR DEPÓSITO' : 'ENTENDIDO',
            showCancelButton: res.puede_cambiar,
            cancelButtonText: 'CONTINUAR',
            background: '#001020', color: '#fff'
        }).then(result => {
            try { sessionStorage.setItem('avisoDepositoActivo', String(res.id_deposito)); } catch (e) {}
            if (res.puede_cambiar && result.isConfirmed) {
                onClickStatusDeposito();
            }
        });
    }

    async function onClickStatusDeposito() {
        const estado = window.__depositoEstado;
        if (!estado || (!estado.requiere_seleccion && !estado.puede_cambiar)) return;

        const opciones = {};
        (estado.depositos_permitidos || []).forEach(d => { opciones[d.id] = d.nombre; });

        const { value: idDeposito } = await Swal.fire({
            title: 'Depósito de trabajo',
            input: 'select',
            inputOptions: opciones,
            inputPlaceholder: 'Seleccione un depósito',
            showCancelButton: !estado.requiere_seleccion,
            confirmButtonText: 'CONFIRMAR',
            cancelButtonText: 'CANCELAR',
            background: '#001020', color: '#fff'
        });
        if (!idDeposito) return;

        try {
            const resp = await fetch('api_depositos.php?action=fijar_trabajo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ id_deposito: parseInt(idDeposito) })
            });
            const res = await resp.json();
            if (res.success) {
                // El usuario acaba de elegirlo a propósito: no hace falta el
                // aviso informativo de "estás trabajando en X" a continuación.
                try { sessionStorage.setItem('avisoDepositoActivo', String(idDeposito)); } catch (e) {}
                checkDepositoStatus();
            } else {
                Swal.fire({ title: 'No se pudo cambiar', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
            }
        } catch (e) {
            console.error(e);
        }
    }

    function toggleTheme() {
        const html = document.documentElement;
        const icon = document.getElementById('theme-icon');

        if (html.getAttribute('data-theme') === 'light') {
            html.removeAttribute('data-theme');
            icon.className = 'fas fa-moon';
            localStorage.setItem('theme', 'dark');
        } else {
            html.setAttribute('data-theme', 'light');
            icon.className = 'fas fa-sun';
            localStorage.setItem('theme', 'light');
        }
    }

    // Al cargar la página, recordar la preferencia del usuario
    document.addEventListener('DOMContentLoaded', () => {
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
            if (document.getElementById('theme-icon'))
                document.getElementById('theme-icon').className = 'fas fa-sun';
        }
    });

    // Ejecutar inmediatamente al cargar el menú y programar una revisión cada 60 segundos
    checkCajaStatus();
    checkDepositoStatus();
    setInterval(checkCajaStatus, 60000);

    // ---- Ayuda contextual (botón "?") ----
    let __ayudaData = null;

    async function cargarAyudaData() {
        if (__ayudaData) return __ayudaData;
        try {
            // Cache-buster atado a la fecha de modificación real del archivo: evita que
            // el navegador siga sirviendo una versión vieja de la ayuda desde su caché
            // HTTP después de actualizar ayuda_sistema.json.
            const resp = await fetch('ayuda_sistema.json?v=<?= (int)(@filemtime(__DIR__ . '/ayuda_sistema.json') ?: 0) ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            __ayudaData = await resp.json();
        } catch (e) {
            console.error('No se pudo cargar la ayuda del sistema', e);
            __ayudaData = { vistas: {}, general: {} };
        }
        return __ayudaData;
    }

    function moduloActual() {
        const params = new URLSearchParams(window.location.search);
        return params.get('p') || null;
    }

    function renderAyudaPantalla(entry, tituloFallback) {
        const titulo = document.getElementById('ayudaPantallaTitulo');
        const body = document.getElementById('ayudaPantallaBody');

        if (!entry) {
            titulo.innerHTML = '<i class="fa-solid fa-circle-question me-2"></i>Ayuda';
            body.innerHTML = `
                <p>Todavía no hay una ficha de ayuda específica para esta pantalla.</p>
                <p class="text-muted small mb-0">Elegí una opción del menú para ver información sobre esa sección, o consultá "Ayuda del Sistema" en el menú de usuario.</p>`;
            return;
        }

        titulo.innerHTML = `<i class="fa-solid fa-circle-question me-2"></i>${entry.titulo || tituloFallback || 'Ayuda'}`;

        let html = '';
        if (entry.categoria) {
            html += `<div class="mb-2"><span class="badge bg-secondary">${entry.categoria}</span></div>`;
        }
        if (entry.resumen) {
            html += `<p>${entry.resumen}</p>`;
        }
        if (entry.cuando_usarla) {
            html += `<p class="text-muted"><strong>¿Cuándo se usa?</strong> ${entry.cuando_usarla}</p>`;
        }
        if (Array.isArray(entry.como_se_usa) && entry.como_se_usa.length) {
            html += '<p class="mb-1"><strong>¿Cómo se usa?</strong></p><ol>';
            entry.como_se_usa.forEach((paso, i) => {
                // Capturas opcionales del paso (campo "capturas" de la ficha). Clic: tamaño completo.
                const caps = (entry.capturas && entry.capturas[String(i)]) || [];
                const imgs = caps.length ? '<div class="d-flex flex-wrap gap-2 mt-2 mb-3">' + caps.map(c =>
                    `<a href="${c.src}" target="_blank" rel="noopener" title="Ver en tamaño completo"><img src="${c.src}" alt="${c.alt || ''}" loading="lazy" style="max-width:100%;border:1px solid rgba(0,0,0,.15);border-radius:6px;background:#fff"></a>`
                ).join('') + '</div>' : '';
                html += `<li>${paso}${imgs}</li>`;
            });
            html += '</ol>';
        }
        if (Array.isArray(entry.tips) && entry.tips.length) {
            html += '<p class="mb-1"><strong>Tips</strong></p><ul>';
            entry.tips.forEach(tip => { html += `<li>${tip}</li>`; });
            html += '</ul>';
        }
        if (entry.funcionamiento_interno) {
            html += `
                <details class="mt-3">
                    <summary style="cursor:pointer;" class="text-muted">¿Cómo funciona por dentro?</summary>
                    <p class="text-muted small mt-2 mb-0">${entry.funcionamiento_interno}</p>
                </details>`;
        }
        body.innerHTML = html || '<p class="text-muted mb-0">Sin contenido de ayuda para esta pantalla.</p>';
    }

    async function abrirAyudaPantalla() {
        const modalEl = document.getElementById('modalAyudaPantalla');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.getElementById('ayudaPantallaBody').innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-spinner fa-spin me-1"></i> Cargando ayuda...</div>';
        modal.show();

        const data = await cargarAyudaData();
        const modulo = moduloActual();
        const entry = (modulo && data.vistas && data.vistas[modulo]) ? data.vistas[modulo] : (data.general && data.general.default);
        renderAyudaPantalla(entry);
    }

    // ---- Asistente de Soporte (IA) ----
    let __iaHistorial = [];
    let __iaCargando = false;

    function agregarBurbujaIA(rol, texto) {
        const cont = document.getElementById('iaChatMensajes');
        const esUsuario = rol === 'usuario';
        const burbuja = document.createElement('div');
        burbuja.className = 'd-flex ' + (esUsuario ? 'justify-content-end' : 'justify-content-start');
        burbuja.style.marginBottom = '6px';
        const globo = document.createElement('div');
        globo.className = 'rounded-3';
        globo.style.cssText = `max-width: 80%; margin: 0; font-size: 13px; line-height: 1.3; white-space: pre-wrap;
            padding: 5px 10px;
            background: ${esUsuario ? 'var(--info)' : 'var(--bg-card)'};
            color: ${esUsuario ? '#fff' : 'var(--text-main)'};
            border: 1px solid ${esUsuario ? 'transparent' : 'var(--border-color)'};`;
        globo.textContent = texto.trim();
        burbuja.appendChild(globo);
        cont.appendChild(burbuja);
        cont.scrollTop = cont.scrollHeight;
        return burbuja;
    }

    async function armarSugerenciasIA() {
        const wrap = document.getElementById('iaChatSugerencias');
        wrap.innerHTML = '';
        const data = await cargarAyudaData();
        const modulo = moduloActual();
        const entry = modulo && data.vistas ? data.vistas[modulo] : null;
        if (!entry) return;

        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'btn btn-sm btn-outline-secondary';
        chip.style.fontSize = '11px';
        chip.textContent = `¿Cómo se usa "${entry.titulo}"?`;
        chip.onclick = () => {
            document.getElementById('iaChatInput').value = chip.textContent;
            enviarMensajeIA();
        };
        wrap.appendChild(chip);
    }

    async function abrirChatIA() {
        const modalEl = document.getElementById('modalChatIA');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();

        const cont = document.getElementById('iaChatMensajes');
        if (!cont.dataset.iniciado) {
            cont.dataset.iniciado = '1';
            agregarBurbujaIA('asistente', 'Hola, soy el asistente de soporte del sistema. Preguntame cómo usar cualquier pantalla y te oriento con los pasos.');
            armarSugerenciasIA();
        }
        setTimeout(() => document.getElementById('iaChatInput').focus(), 300);
    }

    async function enviarMensajeIA() {
        if (__iaCargando) return;
        const input = document.getElementById('iaChatInput');
        const pregunta = input.value.trim();
        if (!pregunta) return;

        document.getElementById('iaChatSugerencias').innerHTML = '';
        agregarBurbujaIA('usuario', pregunta);
        input.value = '';
        __iaCargando = true;
        document.getElementById('iaChatEnviar').disabled = true;
        const burbujaEspera = agregarBurbujaIA('asistente', 'Pensando...');

        try {
            const resp = await fetch('api_soporte_ia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    pregunta: pregunta,
                    vista_actual: moduloActual(),
                    historial: __iaHistorial
                })
            });
            const data = await resp.json();
            burbujaEspera.remove();

            if (data.success) {
                agregarBurbujaIA('asistente', data.respuesta);
                __iaHistorial.push({ rol: 'usuario', texto: pregunta });
                __iaHistorial.push({ rol: 'asistente', texto: data.respuesta });
                if (__iaHistorial.length > 12) __iaHistorial = __iaHistorial.slice(-12);
            } else {
                agregarBurbujaIA('asistente', data.error || 'No pude responder en este momento.');
            }
        } catch (e) {
            burbujaEspera.remove();
            agregarBurbujaIA('asistente', 'No pude conectarme con el asistente. Probá de nuevo en un rato.');
        } finally {
            __iaCargando = false;
            document.getElementById('iaChatEnviar').disabled = false;
            input.focus();
        }
    }

    document.getElementById('iaChatInput')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            enviarMensajeIA();
        }
    });
</script>
