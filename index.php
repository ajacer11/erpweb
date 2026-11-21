<?php
/**
 * index.php - ESTRUCTURA CENTRAL CON SEGURIDAD SPA
 */
session_start();

// SEGURIDAD: Si no hay usuario en sesión, a la calle (Login)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WEST IT ERP - Gestión Integral</title>
    <link rel="icon" type="image/png" href="isotipo.png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="stylesglobal.css?v=<?php echo time(); ?>">

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>
</head>

<body>

    <?php include 'menu.php'; ?>

    <div id="loading-overlay"
        style="position: fixed; top: 54px; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); display: none; justify-content: center; align-items: center; z-index: 2000;">
        <div class="text-center">
            <div class="spinner-border text-info mb-2" style="width: 3rem; height: 3rem;"></div>
            <div class="small text-accent">ACCEDIENDO AL MÓDULO...</div>
        </div>
    </div>

    <div id="custom-context-menu" role="menu" aria-label="Opciones del módulo">
        <button type="button" class="context-menu-item" id="ctx-reload" role="menuitem"><i
                class="fa-solid fa-sync"></i><span>Recargar módulo</span></button>
        <button type="button" class="context-menu-item" id="ctx-open-tab" role="menuitem"><i
                class="fa-solid fa-arrow-up-right-from-square"></i><span>Abrir en pestaña nueva</span></button>
        <button type="button" class="context-menu-item" id="ctx-copy-link" role="menuitem"><i
                class="fa-solid fa-link"></i><span>Copiar enlace</span></button>
    </div>

    <main id="main-content" style="height: calc(100vh - 54px); overflow-y: auto; padding: 20px;">
        <div class="container-fluid text-center mt-5 pt-5 fade-in" id="default-view">
            <img src="logo.png" alt="WEST IT" style="max-width: 400px;" onerror="this.style.display='none'">
            <h2 class="mt-4 text-white" style="letter-spacing: 5px; font-weight: 300;">SISTEMA DE GESTIÓN</h2>
            <p class="text-accent opacity-75">SELECCIONE UNA OPCIÓN DEL MENÚ SUPERIOR</p>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="tabla_ordenamiento.js?v=20260916"></script>

    <script>
        // El área de trabajo ocupa exactamente lo que queda debajo de las barras de arriba.
        // Antes era 100vh - 54px fijo, pero las barras miden ~92px (más si el logo/estado ocupan
        // dos líneas): con body overflow:hidden, el final de cada pantalla quedaba fuera de vista.
        (function () {
            const main = document.getElementById('main-content');
            if (!main) return;
            const ajustar = () => {
                const top = Math.max(0, main.getBoundingClientRect().top);
                main.style.height = (window.innerHeight - top) + 'px';
            };
            ajustar();
            window.addEventListener('resize', ajustar);
            if (window.ResizeObserver) {
                const ro = new ResizeObserver(ajustar);
                document.querySelectorAll('.status-bar, .navbar-erp').forEach(el => ro.observe(el));
            }
        })();

        let currentUrl = "";
        $.fn.select2.defaults.set("language", "es");
        $.fn.select2.defaults.set("width", "100%");
        $.fn.select2.defaults.set("matcher", function (params, data) {
            const term = $.trim(params.term || '');
            if (!term) return data;

            const normalize = value => String(value ?? '')
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toUpperCase();

            // Select2 normalmente busca sólo data.text. Incluimos data.id para
            // que los combos de productos también encuentren su código interno.
            const needle = normalize(term);
            const haystack = normalize(`${data.text || ''} ${data.id || ''}`);
            if (haystack.includes(needle)) return data;

            if (Array.isArray(data.children)) {
                const matches = data.children
                    .map(child => $.fn.select2.defaults.defaults.matcher(params, child))
                    .filter(Boolean);
                if (matches.length) return { ...data, children: matches };
            }

            return null;
        });

        async function loadModule(url, isPopState = false, forceReload = false) {
            if (!url || url === '#' || url === 'javascript:void(0)') return;
            if (url === currentUrl && !isPopState && !forceReload) {
                Swal.fire({
                    icon: 'info',
                    title: 'Módulo activo',
                    text: 'Ya estás en esta sección.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return;
            }

            const content = document.getElementById('main-content');
            const loader = document.getElementById('loading-overlay');

            try {
                loader.style.display = 'flex';
                content.style.opacity = '0.5';

                if (window.jQuery) $('select.select2-hidden-accessible').select2('destroy');
                document.querySelectorAll('script[data-dynamic-script="true"]').forEach(s => s.remove());
                if (typeof window.cleanupPage === 'function') window.cleanupPage();

                // AQUÍ ESTÁ LA CLAVE: Enviamos cabecera para que el PHP sepa que es AJAX
                const response = await fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!response.ok) throw new Error('Error de conexión.');

                const html = await response.text();

                // Si el servidor nos redirigió al login o index (HTML completo), recargamos
                if (html.includes('<!DOCTYPE html>')) {
                    window.location.reload();
                    return;
                }

                content.innerHTML = html;
                currentUrl = url;

                const scripts = content.querySelectorAll('script');
                for (const oldScript of scripts) {
                    const newScript = document.createElement('script');
                    Array.from(oldScript.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                    newScript.setAttribute('data-dynamic-script', 'true');
                    if (oldScript.src) {
                        await new Promise((res) => { newScript.onload = res; document.body.appendChild(newScript); });
                    } else {
                        newScript.appendChild(document.createTextNode(oldScript.innerHTML));
                        document.body.appendChild(newScript);
                    }
                    oldScript.remove();
                }

                if (!isPopState) {
                    // El módulo puede traer su propio query string (ej. "newfactura.php?asociado=168171&tipo_nc=true").
                    // Pegarlo tal cual detrás de "?p=" rompe el parseo (queda un "?" de más): hay que
                    // separar el nombre del módulo de sus parámetros y unirlos con "&" a nivel index.php.
                    const qIdx = url.indexOf('?');
                    const modName = qIdx === -1 ? url : url.substring(0, qIdx);
                    const modQuery = qIdx === -1 ? '' : url.substring(qIdx + 1);
                    const newSearch = modQuery ? `?p=${modName}&${modQuery}` : `?p=${modName}`;
                    window.history.pushState({ path: url }, '', newSearch);
                }

            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar el módulo.', background: '#200', color: '#fff' });
            } finally {
                loader.style.display = 'none';
                content.style.opacity = '1';
            }
        }

        const ctxMenu = document.getElementById('custom-context-menu');
        let rightClickedUrl = "";

        function getModulePageUrl(moduleUrl) {
            const source = new URL(moduleUrl, window.location.href);
            const target = new URL('index.php', window.location.href);
            target.searchParams.set('p', source.pathname.split('/').pop());
            source.searchParams.forEach((value, key) => target.searchParams.append(key, value));
            return target.href;
        }

        document.addEventListener('contextmenu', e => {
            const link = e.target.closest('.nav-link-ajax');
            const href = link?.getAttribute('href') || '';
            if (link && href && href !== '#' && !href.startsWith('javascript:')) {
                e.preventDefault();
                rightClickedUrl = href;
                ctxMenu.style.display = 'block';
                const menuWidth = ctxMenu.offsetWidth;
                const menuHeight = ctxMenu.offsetHeight;
                const left = Math.min(e.clientX, window.innerWidth - menuWidth - 8);
                const top = Math.min(e.clientY, window.innerHeight - menuHeight - 8);
                ctxMenu.style.left = Math.max(8, left) + "px";
                ctxMenu.style.top = Math.max(8, top) + "px";
            } else ctxMenu.style.display = 'none';
        });
        document.addEventListener('click', () => ctxMenu.style.display = 'none');
        document.getElementById('ctx-reload').addEventListener('click', () => { currentUrl = ""; loadModule(rightClickedUrl); });
        document.getElementById('ctx-open-tab').addEventListener('click', () => {
            window.open(getModulePageUrl(rightClickedUrl), '_blank', 'noopener,noreferrer');
        });
        document.getElementById('ctx-copy-link').addEventListener('click', async () => {
            await navigator.clipboard.writeText(getModulePageUrl(rightClickedUrl));
            Swal.fire({
                icon: 'success',
                title: 'Enlace copiado',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1800
            });
        });

        document.addEventListener('click', e => {
            const link = e.target.closest('.nav-link-ajax');
            if (link) { e.preventDefault(); loadModule(link.getAttribute('href')); }
        });

        window.onpopstate = e => { if (e.state?.path) loadModule(e.state.path, true); else location.reload(); };

        document.addEventListener('DOMContentLoaded', () => {
            // Si la URL trae más parámetros que "p" (ej. ?p=newfactura.php&notacredito=168171),
            // hay que reconstruirlos junto al módulo — si no, se pierden al recargar/abrir el link.
            const params = new URLSearchParams(window.location.search);
            const p = params.get('p');
            if (p) {
                params.delete('p');
                const rest = params.toString();
                loadModule(rest ? `${p}?${rest}` : p, true);
            }
        });

        document.body.addEventListener('input', e => {
            if (window.FORCE_UPPERCASE_DISABLED) return;
            if (e.target.matches('input[type="text"], textarea, .select2-search__field')) {
                const s = e.target.selectionStart, n = e.target.selectionEnd;
                e.target.value = e.target.value.toUpperCase();
                e.target.setSelectionRange(s, n);
            }
        });
    </script>
</body>

</html>
