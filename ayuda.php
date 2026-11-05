<?php
/**
 * ayuda.php - MANUAL DE AYUDA DEL SISTEMA
 *
 * Vista tipo "manual/documentación": un índice lateral navegable a la
 * izquierda y el contenido de la sección elegida a la derecha. El índice se
 * arma recorriendo el MISMO árbol de menú que ya usa menu.php (menu1.json o
 * menu2.json según $_SESSION['version']), así que automáticamente solo
 * muestra las secciones que existen en la licencia activa del sistema — no
 * hace falta mantener una lista aparte de "qué pantalla es de qué versión".
 *
 * El contenido de cada tema sale de ayuda_sistema.json (la misma base que
 * usa el botón de ayuda "?" y el asistente de soporte por IA).
 */
require_once 'check_access.php';

$version = (int)($_SESSION['version'] ?? 1);
$version = $version === 2 ? 2 : 1;
$menuPath = __DIR__ . '/menu' . $version . '.json';
$menuItems = file_exists($menuPath) ? json_decode(file_get_contents($menuPath), true) : [];

$ayudaPath = __DIR__ . '/ayuda_sistema.json';
$ayudaData = file_exists($ayudaPath) ? json_decode(file_get_contents($ayudaPath), true) : ['vistas' => []];
$vistasDisponibles = $ayudaData['vistas'] ?? [];

/**
 * Recorre el árbol del menú (idéntico al que renderiza la navbar real) y
 * arma el HTML del índice lateral. Solo entran las pantallas que existen en
 * este árbol (=esta licencia) Y que además tienen ficha de ayuda cargada.
 */
function renderTocNivel($items, $vistasDisponibles, $nivel = 0)
{
    $html = '<ul class="ayuda-toc-lista' . ($nivel > 0 ? ' ayuda-toc-sub' : '') . '">';
    foreach ($items as $item) {
        $texto = htmlspecialchars($item['text'] ?? '');
        $hasChildren = !empty($item['children']);
        $url = trim($item['url'] ?? '');

        if ($hasChildren) {
            $html .= '<li class="ayuda-toc-grupo">';
            $html .= '<details' . ($nivel === 0 ? ' open' : '') . '><summary>' . $texto . '</summary>';
            $html .= renderTocNivel($item['children'], $vistasDisponibles, $nivel + 1);
            $html .= '</details></li>';
        } elseif ($url !== '' && isset($vistasDisponibles[$url])) {
            $html .= '<li><button type="button" class="ayuda-toc-link" data-vista="' . htmlspecialchars($url) . '">' . $texto . '</button></li>';
        }
        // Si el ítem no tiene ficha de ayuda cargada todavía, se omite del
        // índice en lugar de mostrar un link roto.
    }
    $html .= '</ul>';
    return $html;
}
?>

<style>
    .ayuda-manual { display: grid; grid-template-columns: 300px 1fr; gap: 14px; height: calc(100vh - 100px); }
    .ayuda-sidebar {
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px;
        padding: 12px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px;
    }
    .ayuda-buscador {
        background: var(--input-bg); border: 1px solid var(--input-border); color: var(--text-main);
        border-radius: 8px; padding: 8px 12px; font-size: 12.5px; width: 100%;
    }
    .ayuda-toc-lista { list-style: none; margin: 0; padding-left: 0; }
    .ayuda-toc-sub { padding-left: 14px; margin-top: 2px; }
    .ayuda-toc-grupo > details > summary {
        cursor: pointer; font-size: 11.5px; font-weight: 800; text-transform: uppercase;
        letter-spacing: .3px; color: var(--accent); padding: 6px 4px; list-style: none;
    }
    .ayuda-toc-grupo > details > summary::-webkit-details-marker { display: none; }
    .ayuda-toc-grupo > details > summary::before { content: '▸ '; }
    .ayuda-toc-grupo > details[open] > summary::before { content: '▾ '; }
    .ayuda-toc-link {
        display: block; width: 100%; text-align: left; background: transparent; border: none;
        color: var(--text-main); font-size: 12.5px; padding: 6px 8px; border-radius: 6px; cursor: pointer;
    }
    .ayuda-toc-link:hover { background: var(--accent-soft); color: var(--accent); }
    .ayuda-toc-link.activo { background: var(--accent); color: #fff; font-weight: 700; }
    .ayuda-toc-link.oculto, .ayuda-toc-grupo.oculto { display: none !important; }

    .ayuda-contenido {
        background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 10px;
        padding: 26px 32px; overflow-y: auto;
    }
    .ayuda-contenido h2 { color: var(--text-titles); font-weight: 800; margin-bottom: 4px; }
    .ayuda-contenido .ayuda-categoria { color: var(--accent); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
    .ayuda-contenido h6 { color: var(--accent); font-weight: 800; text-transform: uppercase; font-size: 12px; letter-spacing: .4px; margin-top: 22px; }
    .ayuda-contenido p, .ayuda-contenido li { color: var(--text-main); font-size: 13.5px; line-height: 1.6; }
    .ayuda-chip {
        display: inline-block; font-size: 11px; padding: 3px 10px; border-radius: 20px; margin: 2px 4px 2px 0;
        background: var(--accent-soft); color: var(--accent); border: 1px solid var(--accent); cursor: pointer;
    }
    .ayuda-vacio { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); gap: 10px; text-align: center; }
    .ayuda-vacio i { font-size: 40px; opacity: .5; }

    @media (max-width: 900px) {
        .ayuda-manual { grid-template-columns: 1fr; height: auto; }
        .ayuda-sidebar { max-height: 40vh; }
        .ayuda-contenido { min-height: 50vh; }
    }
</style>

<div class="container-fluid px-2 py-2 fade-in">
    <div class="mb-2">
        <h4 class="text-white mb-0"><i class="fa-solid fa-book me-2 text-accent"></i>Ayuda del Sistema</h4>
        <small class="text-accent opacity-75">Manual de uso de WEST IT — organizado igual que el menú de tu sistema</small>
    </div>

    <div class="ayuda-manual">
        <div class="ayuda-sidebar">
            <input type="text" id="ayudaManualBuscar" class="ayuda-buscador" placeholder="Buscar en el manual...">

            <ul class="ayuda-toc-lista">
                <li class="ayuda-toc-grupo">
                    <details open><summary>Introducción</summary>
                        <ul class="ayuda-toc-lista ayuda-toc-sub">
                            <li><button type="button" class="ayuda-toc-link" data-vista="_intro">Qué es WEST IT y para qué sirve</button></li>
                            <li><button type="button" class="ayuda-toc-link" data-vista="_como_navegar">Recorrido por el menú y las ayudas</button></li>
                            <li><button type="button" class="ayuda-toc-link" data-vista="login.php">Cómo entrar al sistema</button></li>
                        </ul>
                    </details>
                </li>
            </ul>

            <?= renderTocNivel($menuItems, $vistasDisponibles) ?>
        </div>

        <div class="ayuda-contenido" id="ayudaManualContenido">
            <div class="ayuda-vacio">
                <i class="fa-solid fa-hand-point-left"></i>
                <div>Elegí un tema del índice para ver su explicación acá.</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const CONTENIDO_ESTATICO = {
        _intro: {
            titulo: 'Qué es WEST IT y para qué sirve',
            categoria: 'Introducción',
            resumen: 'WEST IT es el sistema de gestión integral del negocio: reúne en un solo lugar la facturación electrónica, la administración de clientes y proveedores, el control de stock y depósitos, el manejo de cajas y cuentas de tesorería, y la contabilidad. La idea es que cualquier movimiento del negocio (una venta, una compra, un cobro, un pago, un ajuste de stock) quede registrado una sola vez y se refleje automáticamente en todos los sectores relacionados.',
            como_se_usa: [
                'Iniciá sesión con tu usuario y contraseña (o el CUIT de la empresa si es la primera vez, ver "Cómo entrar al sistema").',
                'Usá el menú superior para moverte entre las secciones: Facturación, Productos, Clientes, Proveedores, Fondos, Reportes, Contabilidad, etc. (las que tengas habilitadas según tu perfil y la licencia contratada).',
                'Este manual está organizado con las mismas secciones que ese menú: elegí un tema de la izquierda para ver cómo se usa cada pantalla.'
            ],
            funcionamiento_interno: 'Por dentro, el sistema comparte la misma información entre todas las pantallas: por ejemplo, cuando facturás una venta, automáticamente se descuenta el stock del producto, se actualiza la cuenta corriente del cliente, se registra el cobro en la caja correspondiente y se genera el asiento contable si está configurado. No son módulos separados: es un único sistema donde cada pantalla es una "puerta de entrada" distinta a la misma información.'
        },
        _como_navegar: {
            titulo: 'Recorrido por el menú y las ayudas',
            categoria: 'Introducción',
            resumen: 'Además de este manual completo, el sistema tiene ayuda rápida disponible en cada pantalla, y un asistente que responde dudas puntuales sobre cómo usar el sistema.',
            como_se_usa: [
                'Arriba, junto al nombre de la empresa, hay dos botones: uno con el ícono de robot (asistente de soporte por IA) y uno con el ícono de interrogación (ayuda rápida de la pantalla en la que estás parado).',
                'El botón de interrogación (?) abre un resumen corto de la pantalla actual: para qué sirve, cómo se usa y qué hace el sistema por detrás.',
                'El botón de robot abre un chat donde podés preguntar en tus palabras cómo hacer algo puntual (por ejemplo "¿cómo cobro por transferencia?"). El asistente solo responde en base a la información de este mismo manual, no tiene acceso a tus datos reales (clientes, ventas, stock, etc.).',
                'Este manual (Ayuda del Sistema, en el menú de usuario arriba a la derecha) es el lugar para leer con calma toda la documentación, sección por sección, en vez de consultar pantalla por pantalla.'
            ],
            funcionamiento_interno: 'Las tres formas de ayuda (este manual, el botón "?" y el asistente por IA) leen la misma base de información: no hay contenido distinto entre una y otra, solo cambia la forma de consultarla.'
        }
    };

    let ayudaData = null;
    async function obtenerAyudaData() {
        if (ayudaData) return ayudaData;
        if (typeof cargarAyudaData === 'function') {
            ayudaData = await cargarAyudaData();
        } else {
            const resp = await fetch('ayuda_sistema.json?v=<?= (int)(@filemtime($ayudaPath) ?: 0) ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            ayudaData = await resp.json();
        }
        return ayudaData;
    }

    function normalizar(s) {
        return (s || '').toString().normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
    }

    function tituloDe(clave) {
        if (CONTENIDO_ESTATICO[clave]) return CONTENIDO_ESTATICO[clave].titulo;
        const rel = ayudaData?.vistas?.[clave] || ayudaData?.general?.[clave];
        return rel?.titulo || clave;
    }

    // Capturas de pantalla de un paso (campo opcional "capturas" de la ficha:
    // { "<índice del paso>": [ { "src": "...", "alt": "..." } ] }). Clic: se abre en grande.
    function capturasDePaso(entry, i) {
        const lista = entry.capturas && entry.capturas[String(i)];
        if (!Array.isArray(lista) || !lista.length) return '';
        return '<div class="d-flex flex-wrap gap-2 mt-2 mb-3">' + lista.map(c =>
            `<a href="${c.src}" target="_blank" rel="noopener" title="Ver en tamaño completo"><img src="${c.src}" alt="${c.alt || ''}" loading="lazy" style="max-width:100%;width:520px;border:1px solid rgba(255,255,255,.2);border-radius:6px;background:#fff"></a>`
        ).join('') + '</div>';
    }

    function renderContenido(entry) {
        const cont = document.getElementById('ayudaManualContenido');
        if (!entry) {
            cont.innerHTML = '<div class="ayuda-vacio"><i class="fa-solid fa-circle-exclamation"></i><div>No encontramos contenido para este tema todavía.</div></div>';
            return;
        }

        let html = '';
        if (entry.categoria) html += `<div class="ayuda-categoria">${entry.categoria}</div>`;
        html += `<h2>${entry.titulo || ''}</h2>`;
        if (entry.resumen) html += `<p>${entry.resumen}</p>`;
        if (entry.cuando_usarla) html += `<h6>¿Cuándo se usa?</h6><p>${entry.cuando_usarla}</p>`;

        if (Array.isArray(entry.como_se_usa) && entry.como_se_usa.length) {
            html += '<h6>¿Cómo se usa?</h6><ol>';
            entry.como_se_usa.forEach((p, i) => html += `<li>${p}${capturasDePaso(entry, i)}</li>`);
            html += '</ol>';
        }
        if (Array.isArray(entry.tips) && entry.tips.length) {
            html += '<h6>Tips</h6><ul>';
            entry.tips.forEach(t => html += `<li>${t}</li>`);
            html += '</ul>';
        }
        if (entry.funcionamiento_interno) {
            html += `<h6>¿Cómo funciona por dentro?</h6><p class="text-muted">${entry.funcionamiento_interno}</p>`;
        }
        if (Array.isArray(entry.relacionado) && entry.relacionado.length) {
            html += '<h6>Temas relacionados</h6><div>';
            entry.relacionado.forEach(rel => {
                html += `<span class="ayuda-chip" data-vista="${rel}">${tituloDe(rel)}</span>`;
            });
            html += '</div>';
        }

        cont.innerHTML = html;
        cont.scrollTop = 0;

        cont.querySelectorAll('.ayuda-chip').forEach(chip => {
            chip.addEventListener('click', () => seleccionarTema(chip.dataset.vista, true));
        });
    }

    async function seleccionarTema(clave, buscarEnRelacionados) {
        document.querySelectorAll('.ayuda-toc-link').forEach(b => b.classList.remove('activo'));
        const btn = document.querySelector(`.ayuda-toc-link[data-vista="${CSS.escape(clave)}"]`);
        if (btn) {
            btn.classList.add('activo');
            let padre = btn.closest('details');
            while (padre) { padre.open = true; padre = padre.parentElement?.closest('details'); }
        }

        if (CONTENIDO_ESTATICO[clave]) {
            renderContenido(CONTENIDO_ESTATICO[clave]);
            return;
        }

        const data = await obtenerAyudaData();
        const entry = data.vistas?.[clave] || data.general?.[clave];
        if (!entry && buscarEnRelacionados) {
            // Puede ser un tema relacionado que no tiene link visible en el índice
            // (por ejemplo una pantalla de otra licencia): igual mostramos su ficha si existe.
        }
        renderContenido(entry || null);
    }

    document.querySelectorAll('.ayuda-toc-link').forEach(btn => {
        btn.addEventListener('click', () => seleccionarTema(btn.dataset.vista, false));
    });

    document.getElementById('ayudaManualBuscar').addEventListener('input', function () {
        const q = normalizar(this.value.trim());
        document.querySelectorAll('.ayuda-toc-lista li:not(.ayuda-toc-grupo)').forEach(li => {
            const btn = li.querySelector('.ayuda-toc-link');
            if (!btn) return;
            const coincide = !q || normalizar(btn.textContent).includes(q);
            li.classList.toggle('oculto', !coincide);
        });
        // Abrir automáticamente los grupos que tengan algún resultado visible, y
        // ocultar (sin desarmar) los que se quedaron sin coincidencias.
        document.querySelectorAll('.ayuda-toc-grupo').forEach(grupo => {
            const details = grupo.querySelector(':scope > details');
            if (!details) return;
            const hayVisible = !!details.querySelector('li:not(.oculto):not(.ayuda-toc-grupo)') ||
                                !!details.querySelector('.ayuda-toc-grupo:not(.oculto)');
            grupo.classList.toggle('oculto', q && !hayVisible);
            if (q && hayVisible) details.open = true;
        });
    });

    // Tema inicial
    seleccionarTema('_intro', false);
})();
</script>
