<?php
/**
 * libro_iva.php - LIBRO IVA VENTAS / COMPRAS Y LIBRO IVA DIGITAL (ARCA)
 * Cálculo en libro_iva_helpers.php, datos por api_libro_iva.php.
 */
require_once 'check_access.php';
$mesAnterior = date('Y-m', strtotime('first day of last month'));
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloLibroIva">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-book text-accent me-2"></i>LIBRO IVA</h4>
            <small class="text-accent opacity-75">Ventas, compras, posición de IVA y Libro IVA Digital de ARCA</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-warning btn-sm fw-bold rounded-pill px-3" onclick="livaTxt('cbte')" title="Archivo de comprobantes del Libro IVA Digital">
                <i class="fas fa-file-lines me-1"></i> TXT COMPROBANTES
            </button>
            <button class="btn btn-outline-warning btn-sm fw-bold rounded-pill px-3" onclick="livaTxt('alicuotas')" title="Archivo de alícuotas del Libro IVA Digital">
                <i class="fas fa-file-lines me-1"></i> TXT ALÍCUOTAS
            </button>
            <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" onclick="livaExcel()">
                <i class="fas fa-file-excel me-1"></i> EXCEL
            </button>
            <button class="btn btn-info btn-sm fw-bold rounded-pill px-4 shadow" onclick="livaConsultar()">
                <i class="fas fa-search me-1"></i> CONSULTAR
            </button>
        </div>
    </div>

    <div class="card card-filtros mb-3 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">PERÍODO</label>
                    <input type="month" id="livaMes" class="form-control form-control-sm" value="<?= $mesAnterior ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="livaDesde" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="livaHasta" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label-custom">COMPRAS POR</label>
                    <select id="livaCampoFecha" class="form-select form-select-sm">
                        <option value="fechaimputacion">FECHA DE IMPUTACIÓN</option>
                        <option value="fecha">FECHA DEL COMPROBANTE</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="livaSoloObs" onchange="livaRender()">
                        <label class="form-check-label text-white small fw-bold" for="livaSoloObs">Solo con observaciones</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active fw-bold" data-liva-libro="ventas" type="button"><i class="fas fa-arrow-up me-1"></i>VENTAS <span class="badge bg-secondary ms-1" id="livaCantVentas">0</span></button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-liva-libro="compras" type="button"><i class="fas fa-arrow-down me-1"></i>COMPRAS <span class="badge bg-secondary ms-1" id="livaCantCompras">0</span></button></li>
        <li class="nav-item"><button class="nav-link fw-bold" data-liva-libro="resumen" type="button"><i class="fas fa-scale-balanced me-1"></i>POSICIÓN IVA</button></li>
    </ul>

    <div id="livaAviso" class="alert alert-warning small py-2 d-none"></div>

    <div id="livaPanelTabla">
        <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; background: #000a1a; overflow-y: auto;">
            <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap" id="livaTabla">
                <thead class="sticky-top bg-black"></thead>
                <tbody><tr><td class="text-center text-muted py-5">Elija el período y presione CONSULTAR.</td></tr></tbody>
                <tfoot class="sticky-bottom bg-black fw-bold"></tfoot>
            </table>
        </div>
    </div>

    <div id="livaPanelResumen" class="d-none">
        <div class="row g-3" id="livaResumen"></div>
    </div>
</div>

<script>
(function () {
    const H = { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' };
    let libro = 'ventas';
    const datos = { ventas: null, compras: null, resumen: null };
    const LIMITE_FILAS = 3000;

    const COLS = {
        ventas: [
            ['fecha', 'FECHA', 'f'], ['sigla', 'TIPO'], ['nro', 'NÚMERO'], ['cliente', 'CLIENTE'], ['doc', 'DOCUMENTO'],
            ['neto21', 'NETO 21%', 'n'], ['iva21', 'IVA 21%', 'n'], ['neto10', 'NETO 10,5%', 'n'], ['iva10', 'IVA 10,5%', 'n'],
            ['neto0', 'NETO 0% / EXENTO', 'n'], ['imp_internos', 'IMP. INTERNOS', 'n'], ['perc_nacionales', 'PERC. NAC.', 'n'],
            ['perc_iibb', 'PERC. IIBB', 'n'], ['total', 'TOTAL', 'n']
        ],
        compras: [
            ['fecha', 'FECHA', 'f'], ['fechaimputacion', 'IMPUTACIÓN', 'f'], ['sigla', 'TIPO'], ['nro', 'NÚMERO'], ['proveedor', 'PROVEEDOR'], ['doc', 'CUIT'],
            ['neto21', 'NETO 21%', 'n'], ['iva21', 'IVA 21%', 'n'], ['neto10', 'NETO 10,5%', 'n'], ['iva10', 'IVA 10,5%', 'n'],
            ['neto27', 'NETO 27%', 'n'], ['iva27', 'IVA 27%', 'n'], ['neto0', 'NETO 0% / EXENTO', 'n'], ['no_gravado', 'NO GRAVADO', 'n'],
            ['imp_internos', 'IMP. INTERNOS', 'n'], ['perc_iva', 'PERC. IVA', 'n'], ['perc_iibb', 'PERC. IIBB', 'n'],
            ['credito_fiscal', 'CRÉDITO FISCAL', 'n'], ['total', 'TOTAL', 'n']
        ]
    };

    const money = n => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fdate = d => d ? d.split('-').reverse().join('/') : '';
    const esc = t => $('<div>').text(t ?? '').html();

    function periodo() {
        let desde = $('#livaDesde').val(), hasta = $('#livaHasta').val();
        if (!desde || !hasta) {
            const m = $('#livaMes').val();
            if (!m) return null;
            const [y, mo] = m.split('-').map(Number);
            desde = `${m}-01`;
            hasta = `${m}-${String(new Date(y, mo, 0).getDate()).padStart(2, '0')}`;
        }
        return { desde, hasta, campo_fecha: $('#livaCampoFecha').val() };
    }

    async function post(action, body) {
        const r = await fetch(`api_libro_iva.php?action=${action}`, { method: 'POST', headers: H, body: JSON.stringify(body) });
        const res = await r.json();
        if (!res.success) throw new Error(res.error || 'Error');
        return res;
    }

    // Al tocar el mes se limpian las fechas manuales, y viceversa.
    $('#livaMes').on('change', () => { $('#livaDesde, #livaHasta').val(''); });
    $('#livaDesde, #livaHasta').on('change', () => { if ($('#livaDesde').val() && $('#livaHasta').val()) $('#livaMes').val(''); });
    $('#livaCampoFecha').on('change', () => { datos.compras = null; datos.resumen = null; });

    $('[data-liva-libro]').on('click', function () {
        $('[data-liva-libro]').removeClass('active'); $(this).addClass('active');
        libro = $(this).data('liva-libro');
        if (!datos[libro] && periodo()) livaConsultar(); else livaRender();
    });

    window.livaConsultar = async function () {
        const p = periodo();
        if (!p) return Swal.fire('Atención', 'Elija un período.', 'warning');
        Swal.fire({ title: 'Calculando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            if (libro === 'resumen') {
                datos.resumen = (await post('resumen', p)).data;
            } else {
                const res = await post('consultar', { ...p, libro });
                datos[libro] = res;
                $(libro === 'ventas' ? '#livaCantVentas' : '#livaCantCompras').text(res.data.length);
            }
            Swal.close();
            livaRender();
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    window.livaRender = function () {
        const esResumen = libro === 'resumen';
        $('#livaPanelTabla').toggleClass('d-none', esResumen);
        $('#livaPanelResumen').toggleClass('d-none', !esResumen);
        if (esResumen) return renderResumen();

        const thead = $('#livaTabla thead').empty(), tbody = $('#livaTabla tbody').empty(), tfoot = $('#livaTabla tfoot').empty();
        const res = datos[libro];
        const cols = COLS[libro];
        thead.append('<tr>' + cols.map(c => `<th class="${c[2] === 'n' ? 'text-end' : ''} small">${c[1]}</th>`).join('') + '<th class="small">OBS.</th></tr>');
        if (!res) { tbody.html(`<tr><td colspan="${cols.length + 1}" class="text-center text-muted py-5">Presione CONSULTAR.</td></tr>`); return; }

        let filas = res.data;
        const conObs = filas.filter(f => f.obs.length).length;
        $('#livaAviso').toggleClass('d-none', !conObs)
            .html(conObs ? `<i class="fas fa-triangle-exclamation me-1"></i>${conObs} comprobante(s) con observaciones. Revíselos antes de presentar (ícono <i class="fas fa-circle-exclamation text-warning"></i>; pase el mouse para ver el detalle).` : '');
        if ($('#livaSoloObs').is(':checked')) filas = filas.filter(f => f.obs.length);
        if (!filas.length) { tbody.html(`<tr><td colspan="${cols.length + 1}" class="text-center text-muted py-5">Sin comprobantes.</td></tr>`); return; }

        const html = [];
        filas.slice(0, LIMITE_FILAS).forEach(f => {
            const fila = { ...f, nro: `${String(f.pv).padStart(5, '0')}-${String(f.numero).padStart(8, '0')}`, doc: f.doc_cod === 99 ? 'CF' : f.doc_nro };
            html.push('<tr>' + cols.map(c => {
                const v = fila[c[0]];
                if (c[2] === 'n') return `<td class="text-end ${v < 0 ? 'text-danger' : ''}">${v ? money(v) : '<span class="text-muted">-</span>'}</td>`;
                if (c[2] === 'f') return `<td>${fdate(v)}</td>`;
                return `<td class="${c[0] === 'cliente' || c[0] === 'proveedor' ? 'text-truncate' : ''}" style="${c[0] === 'cliente' || c[0] === 'proveedor' ? 'max-width:220px' : ''}">${esc(v)}</td>`;
            }).join('') + `<td class="text-center">${f.obs.length ? `<i class="fas fa-circle-exclamation text-warning" title="${esc(f.obs.join('\n'))}"></i>` : ''}</td></tr>`);
        });
        if (filas.length > LIMITE_FILAS) html.push(`<tr><td colspan="${cols.length + 1}" class="text-center text-warning small">Se muestran ${LIMITE_FILAS} de ${filas.length} comprobantes. El Excel y los TXT incluyen todos.</td></tr>`);
        tbody.html(html.join(''));
        tfoot.html('<tr>' + cols.map((c, i) => c[2] === 'n' ? `<td class="text-end text-accent">${money(res.totales[c[0]])}</td>` : (i === 0 ? '<td class="text-accent">TOTALES</td>' : '<td></td>')).join('') + '<td></td></tr>');
    };

    function renderResumen() {
        const r = datos.resumen, cont = $('#livaResumen').empty();
        if (!r) { cont.html('<div class="col-12 text-center text-muted py-5">Presione CONSULTAR.</div>'); return; }
        const tarjeta = (titulo, filas, total, color) => `
            <div class="col-12 col-lg-4"><div class="card bg-dark border-secondary h-100"><div class="card-body">
                <h6 class="text-${color} fw-bold mb-3">${titulo}</h6>
                ${filas.map(([k, v]) => `<div class="d-flex justify-content-between small mb-1"><span class="text-white-50">${k}</span><span>$ ${money(v)}</span></div>`).join('')}
                <div class="d-flex justify-content-between border-top border-secondary pt-2 mt-2 fw-bold"><span>TOTAL</span><span class="text-${color}">$ ${money(total)}</span></div>
            </div></div></div>`;
        cont.append(tarjeta('DÉBITO FISCAL (VENTAS)', [['IVA 21%', r.debito.iva21], ['IVA 10,5%', r.debito.iva10], ['Neto gravado', r.debito.neto_gravado], ['Neto 0% / exento', r.debito.neto0]], r.debito.total, 'danger'));
        cont.append(tarjeta('CRÉDITO FISCAL (COMPRAS)', [['IVA 21%', r.credito.iva21], ['IVA 10,5%', r.credito.iva10], ['IVA 27%', r.credito.iva27]], r.credito.total, 'success'));
        const saldo = r.saldo_con_percepciones;
        cont.append(`
            <div class="col-12 col-lg-4"><div class="card bg-dark border-secondary h-100"><div class="card-body">
                <h6 class="text-accent fw-bold mb-3">POSICIÓN DEL PERÍODO</h6>
                <div class="d-flex justify-content-between small mb-1"><span class="text-white-50">Débito - crédito (saldo técnico)</span><span>$ ${money(r.saldo_tecnico)}</span></div>
                <div class="d-flex justify-content-between small mb-1"><span class="text-white-50">Percepciones de IVA sufridas</span><span>$ ${money(r.percepciones_iva_sufridas)}</span></div>
                <div class="d-flex justify-content-between border-top border-secondary pt-2 mt-2 fw-bold">
                    <span>${saldo >= 0 ? 'A PAGAR' : 'SALDO A FAVOR'}</span><span class="${saldo >= 0 ? 'text-danger' : 'text-success'}">$ ${money(Math.abs(saldo))}</span>
                </div>
                <div class="small text-white-50 mt-3">Orientativo: no incluye saldos a favor de períodos anteriores, retenciones sufridas ni otros conceptos de la DDJJ.</div>
            </div></div></div>`);
    }

    function descargar(nombre, contenido, tipo) {
        const blob = contenido instanceof Blob ? contenido : new Blob([contenido], { type: tipo });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = nombre;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    }

    window.livaTxt = async function (tipo) {
        if (libro === 'resumen') return Swal.fire('Atención', 'Elija la pestaña VENTAS o COMPRAS para generar su TXT.', 'info');
        const p = periodo();
        if (!p) return Swal.fire('Atención', 'Elija un período.', 'warning');
        try {
            const res = await post('txt', { ...p, libro, tipo });
            const bytes = Uint8Array.from(atob(res.file_content), c => c.charCodeAt(0));
            descargar(res.filename, new Blob([bytes], { type: 'text/plain' }));
            if (datos[libro] && datos[libro].data.some(f => f.obs.length)) {
                Swal.fire('TXT generado', `${res.registros} registro(s). Hay comprobantes con observaciones: revíselos antes de importar el archivo en ARCA.`, 'warning');
            }
        } catch (e) {
            Swal.fire('Error', e.message, 'error');
        }
    };

    window.livaExcel = function () {
        if (libro === 'resumen' || !datos[libro]) return Swal.fire('Atención', 'Consulte el libro de ventas o de compras primero.', 'info');
        const cols = COLS[libro], res = datos[libro];
        const celda = (v, n) => n ? `<td style="mso-number-format:'#,##0.00'">${(parseFloat(v) || 0).toFixed(2)}</td>` : `<td>${esc(v)}</td>`;
        let html = '<html><head><meta charset="UTF-8"></head><body><table border="1"><tr>' + cols.map(c => `<th>${c[1]}</th>`).join('') + '<th>OBSERVACIONES</th></tr>';
        res.data.forEach(f => {
            const fila = { ...f, nro: `${String(f.pv).padStart(5, '0')}-${String(f.numero).padStart(8, '0')}`, doc: f.doc_cod === 99 ? 'CF' : f.doc_nro };
            html += '<tr>' + cols.map(c => celda(c[2] === 'f' ? fdate(fila[c[0]]) : fila[c[0]], c[2] === 'n')).join('') + `<td>${esc(f.obs.join(' | '))}</td></tr>`;
        });
        html += '<tr>' + cols.map((c, i) => c[2] === 'n' ? celda(res.totales[c[0]], true) : `<td>${i === 0 ? 'TOTALES' : ''}</td>`).join('') + '<td></td></tr></table></body></html>';
        const p = periodo() || { desde: '' };
        descargar(`Libro_IVA_${libro}_${p.desde.slice(0, 7)}.xls`, '﻿' + html, 'application/vnd.ms-excel');
    };
})();
</script>
