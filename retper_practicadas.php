<?php
/**
 * retper_practicadas.php - RETENCIONES Y PERCEPCIONES PRACTICADAS (agente)
 * Backend: api_retper.php?action=practicadas
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloRetperPract">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fa-solid fa-file-invoice-dollar text-accent me-2"></i>RETENCIONES Y PERCEPCIONES PRACTICADAS</h4>
            <small class="text-accent opacity-75">Lo retenido a proveedores y lo percibido a clientes, para depositar e informar</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" onclick="rppExcel()"><i class="fas fa-file-excel me-1"></i>EXCEL</button>
            <button class="btn btn-info btn-sm fw-bold rounded-pill px-4" onclick="rppConsultar()"><i class="fas fa-search me-1"></i>CONSULTAR</button>
        </div>
    </div>
    <div class="card card-filtros mb-3 border-0"><div class="card-body p-3">
        <div class="row g-2 align-items-end">
            <div class="col-6 col-md-2"><label class="form-label-custom">TIPO</label><select id="rppTipo" class="form-select form-select-sm"><option value="RETENCION">RETENCIONES</option><option value="PERCEPCION">PERCEPCIONES</option></select></div>
            <div class="col-6 col-md-2"><label class="form-label-custom">DESDE</label><input type="date" id="rppDesde" class="form-control form-control-sm" value="<?= date('Y-m-01', strtotime('first day of last month')) ?>"></div>
            <div class="col-6 col-md-2"><label class="form-label-custom">HASTA</label><input type="date" id="rppHasta" class="form-control form-control-sm" value="<?= date('Y-m-t', strtotime('last day of last month')) ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label-custom">RÉGIMEN</label><select id="rppRegimen" class="form-select form-select-sm"><option value="">TODOS</option></select></div>
            <div class="col-12 col-md-3"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="rppCero"><label class="form-check-label small text-white" for="rppCero">Incluir pagos sin retención (base acumulada)</label></div></div>
        </div>
    </div></div>
    <div class="small mb-2" id="rppResumen"></div>
    <div class="table-responsive border border-secondary border-opacity-25 rounded" style="max-height:60vh;background:#000a1a;overflow-y:auto">
        <table class="table table-dark table-hover table-sm align-middle mb-0 text-nowrap">
            <thead class="sticky-top bg-black" id="rppHead"></thead>
            <tbody id="rppBody"><tr><td class="text-center text-muted py-4">Presione CONSULTAR.</td></tr></tbody>
        </table>
    </div>
    <div class="small text-white-50 mt-2">La presentación ante ARCA (SIRE / SICORE) o la provincia (ATM, ARBA, etc.) se hace con su aplicativo: el Excel trae todos los datos necesarios (CUIT, base, alícuota, importe, códigos de impuesto y régimen, número de certificado).</div>
</div>

<script>
(function () {
    const H = { 'X-Requested-With': 'XMLHttpRequest' };
    const money = n => (parseFloat(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const fdate = d => d ? String(d).slice(0, 10).split('-').reverse().join('/') : '';
    const esc = t => $('<div>').text(t ?? '').html();
    let filas = [];

    fetch('api_retper.php?action=regimenes_list', { headers: H }).then(r => r.json()).then(res => {
        if (res.success) res.data.forEach(r => $('#rppRegimen').append(new Option(`${r.tipo === 'RETENCION' ? 'Ret.' : 'Perc.'} ${r.nombre}`, r.id)));
    });

    window.rppConsultar = async function () {
        const tipo = $('#rppTipo').val();
        try {
            const res = await fetch('api_retper.php?action=practicadas', { method: 'POST', headers: { ...H, 'Content-Type': 'application/json' },
                body: JSON.stringify({ tipo, desde: $('#rppDesde').val(), hasta: $('#rppHasta').val(), idregimen: $('#rppRegimen').val(), incluir_cero: $('#rppCero').is(':checked') }) }).then(r => r.json());
            if (!res.success) throw new Error(res.error);
            filas = res.data;
            const ret = tipo === 'RETENCION';
            $('#rppHead').html(`<tr><th>FECHA</th><th>RÉGIMEN</th><th>${ret ? 'PROVEEDOR' : 'CLIENTE'}</th><th>CUIT</th><th>COMPROBANTE</th><th class="text-end">BASE</th><th class="text-end">ALÍCUOTA</th><th class="text-end">IMPORTE</th>${ret ? '<th>CERTIFICADO</th>' : ''}</tr>`);
            const tot = {};
            $('#rppBody').html(filas.map(f => {
                tot[f.regimen] = (tot[f.regimen] || 0) + (+f.importe);
                return `<tr><td>${fdate(f.fecha)}</td><td>${esc(f.regimen)}</td><td class="text-truncate" style="max-width:220px">${esc(f.sujeto)}</td><td>${esc(f.cuit)}</td><td>${esc(f.comprobante)}</td>
                    <td class="text-end">$ ${money(f.base)}</td><td class="text-end">${(+f.alicuota).toLocaleString('es-AR', { maximumFractionDigits: 4 })}%</td>
                    <td class="text-end fw-bold ${+f.importe < 0 ? 'text-danger' : ''}">$ ${money(f.importe)}</td>
                    ${ret ? `<td>${+f.importe > 0 ? `<a href="imprimir_certificado_retencion.php?id=${f.id}" target="_blank">N° ${String(f.numero_certificado).padStart(8, '0')}</a>` : ''}</td>` : ''}</tr>`;
            }).join('') || '<tr><td colspan="9" class="text-center text-muted py-4">Sin movimientos en el período.</td></tr>');
            $('#rppResumen').html(Object.entries(tot).map(([k, v]) => `${esc(k)}: <strong>$ ${money(v)}</strong>`).join(' · '));
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    window.rppExcel = function () {
        if (!filas.length) return Swal.fire('Atención', 'Consulte primero.', 'info');
        const ret = $('#rppTipo').val() === 'RETENCION';
        let html = `<html><head><meta charset="UTF-8"></head><body><table border="1"><tr><th>FECHA</th><th>REGIMEN</th><th>IMPUESTO</th><th>JURISDICCION</th>${ret ? '<th>COD. IMPUESTO</th><th>COD. REGIMEN</th>' : ''}<th>${ret ? 'PROVEEDOR' : 'CLIENTE'}</th><th>CUIT</th><th>COMPROBANTE</th><th>IMPORTE COMPROBANTE</th><th>BASE</th><th>ALICUOTA</th><th>IMPORTE</th>${ret ? '<th>CERTIFICADO</th>' : ''}<th>ORIGEN ALICUOTA</th></tr>`;
        const n = v => `<td style="mso-number-format:'#,##0.00'">${(+v).toFixed(2)}</td>`;
        filas.forEach(f => {
            html += `<tr><td>${fdate(f.fecha)}</td><td>${esc(f.regimen)}</td><td>${esc(f.impuesto)}</td><td>${esc(f.jurisdiccion)}</td>${ret ? `<td>${esc(f.codigo_impuesto)}</td><td>${esc(f.codigo_regimen)}</td>` : ''}
                <td>${esc(f.sujeto)}</td><td style="mso-number-format:'\\@'">${esc(f.cuit)}</td><td>${esc(f.comprobante)}</td>${n(f.total_comprobante)}${n(f.base)}<td>${f.alicuota}</td>${n(f.importe)}${ret ? `<td>${f.numero_certificado}</td>` : ''}<td>${esc(f.origen_alicuota)}</td></tr>`;
        });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob(['\ufeff' + html + '</table></body></html>'], { type: 'application/vnd.ms-excel' }));
        a.download = `${ret ? 'Retenciones' : 'Percepciones'}_${$('#rppDesde').val()}_${$('#rppHasta').val()}.xls`;
        document.body.appendChild(a); a.click(); a.remove();
    };
})();
</script>
