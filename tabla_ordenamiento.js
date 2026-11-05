/* Ordena el conjunto filtrado completo antes de paginar. */
(function () {
    const collator = new Intl.Collator('es', {numeric: true, sensitivity: 'base'});

    function comparar(a, b, tipo) {
        const vacioA = a === null || a === undefined || a === '';
        const vacioB = b === null || b === undefined || b === '';
        if (vacioA || vacioB) return vacioA === vacioB ? 0 : (vacioA ? 1 : -1);
        if (tipo === 'numero') return (Number(a) || 0) - (Number(b) || 0);
        if (tipo === 'fecha') {
            const fecha = v => {
                const s = String(v);
                const m = s.match(/^(\d{2})[/-](\d{2})[/-](\d{4})(.*)$/);
                return m ? `${m[3]}-${m[2]}-${m[1]}${m[4]}` : s;
            };
            return collator.compare(fecha(a), fecha(b));
        }
        return collator.compare(String(a), String(b));
    }

    window.erpOrdenarTabla = function (selector, obtenerDatos, renderizar, columnas) {
        const tabla = document.querySelector(selector)?.closest('table');
        if (!tabla) return {aplicar() {}, pintar() {}};
        tabla.dataset.erpSortManaged = '1';
        let columna = -1, direccion = 1;
        const definiciones = () => typeof columnas === 'function' ? columnas() : columnas;

        function pintar() {
            tabla.querySelectorAll('thead tr:first-child th').forEach((th, i) => {
                const def = definiciones()[i];
                if (!def) return;
                if (!th.dataset.erpSortLabel) th.dataset.erpSortLabel = th.textContent.replace(/ [▲▼]$/, '').trim();
                th.textContent = th.dataset.erpSortLabel + (columna === i ? (direccion === 1 ? ' ▲' : ' ▼') : '');
                th.style.cursor = 'pointer';
                th.title = 'Ordenar por ' + th.dataset.erpSortLabel;
                th.setAttribute('aria-sort', columna === i ? (direccion === 1 ? 'ascending' : 'descending') : 'none');
                th.tabIndex = 0;
            });
        }

        function aplicar() {
            if (columna < 0) return;
            const def = definiciones()[columna];
            if (!def) return;
            const datos = obtenerDatos();
            if (!Array.isArray(datos)) return;
            const campo = typeof def === 'string' ? def : def.campo;
            const tipo = typeof def === 'string' ? 'texto' : (def.tipo || 'texto');
            const valor = typeof campo === 'function' ? campo : fila => fila[campo];
            datos.sort((a, b) => direccion * comparar(valor(a), valor(b), tipo));
        }

        function seleccionar(th) {
            const i = Array.prototype.indexOf.call(th.parentElement.children, th);
            if (!definiciones()[i]) return;
            direccion = columna === i ? -direccion : 1;
            columna = i;
            aplicar();
            renderizar();
            pintar();
        }
        tabla.addEventListener('click', e => {
            const th = e.target.closest('thead tr:first-child th');
            if (th && tabla.contains(th)) seleccionar(th);
        });
        tabla.addEventListener('keydown', e => {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('thead tr:first-child th')) {
                e.preventDefault(); seleccionar(e.target);
            }
        });
        pintar();
        return {aplicar, pintar};
    };
})();
