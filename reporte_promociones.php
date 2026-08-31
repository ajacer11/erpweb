<?php
/**
 * reporte_promociones.php — REPORTE DE PROMOCIONES APLICADAS EN VENTAS
 * West IT ERP v1.0
 */
require_once 'check_access.php';
$hoy = date('Y-m-d');
$primerDiaMes = date('Y-m-01');
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloReportePromos">

    <!-- ── HEADER ─────────────────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0 fw-bold" style="color: var(--text-titles);">
                <i class="fas fa-tags me-2 text-accent"></i>REPORTE DE PROMOCIONES
            </h4>
            <small class="text-muted">Historial de promociones aplicadas al facturar</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success fw-bold rounded-pill px-3 btn-sm" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i>EXCEL
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="rpPromos.consultar()">
                <i class="fas fa-magnifying-glass me-1"></i>CONSULTAR
            </button>
        </div>
    </div>

    <!-- ── FILTROS ─────────────────────────────────────────────────────── -->
    <div class="card card-filtros mb-3">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">DESDE</label>
                    <input type="date" id="fDesde" class="form-control form-control-sm"
                           value="<?php echo $primerDiaMes; ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label-custom">HASTA</label>
                    <input type="date" id="fHasta" class="form-control form-control-sm"
                           value="<?php echo $hoy; ?>">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">PROMOCIÓN</label>
                    <select id="fPromo" class="form-select form-select-sm">
                        <option value="0">TODAS LAS PROMOCIONES</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label-custom">VENDEDOR</label>
                    <select id="fVendedor" class="form-select form-select-sm">
                        <option value="0">TODOS LOS VENDEDORES</option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary btn-sm w-100 rounded-pill" onclick="rpPromos.limpiar()">
                        <i class="fas fa-eraser me-1"></i>LIMPIAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── STATS ───────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-3" id="statsRow" style="display:none !important;">
        <div class="col-6 col-md-3">
            <div class="card card-filtros p-3 text-center">
                <div class="fw-bold text-accent" style="font-size:26px;" id="stUsos">—</div>
                <small class="text-muted text-uppercase fw-bold" style="font-size:9px; letter-spacing:.5px;">Usos Totales</small>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-filtros p-3 text-center">
                <div class="fw-bold text-warning" style="font-size:26px;" id="stUnidades">—</div>
                <small class="text-muted text-uppercase fw-bold" style="font-size:9px; letter-spacing:.5px;">Unidades Promovidas</small>
            </div>
        </div>
        <div class="col-12 col-md-6">
            <div class="card card-filtros p-3 text-center">
                <div class="fw-bold text-danger" style="font-size:26px;" id="stDescuento">—</div>
                <small class="text-muted text-uppercase fw-bold" style="font-size:9px; letter-spacing:.5px;">Total Descuentos Otorgados ($)</small>
            </div>
        </div>
    </div>

    <!-- ── RANKING POR PROMO ───────────────────────────────────────────── -->
    <div id="rankingWrap" style="display:none;">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="text-muted fw-bold" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">
                <i class="fas fa-trophy me-1 text-warning"></i>Ranking por Promoción
            </span>
            <hr class="flex-grow-1 border-secondary opacity-25 m-0">
        </div>
        <div class="d-flex flex-wrap gap-2 mb-3" id="rankingCards"></div>
    </div>

    <!-- ── PAGINACIÓN SUPERIOR ─────────────────────────────────────────── -->
    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInputTop" min="1" value="1">
                <button class="erp-pag-btn" onclick="rpPromos.pagJumpTop()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPPrimTop" onclick="rpPromos.pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPAntTop"  onclick="rpPromos.pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPSigTop"  onclick="rpPromos.pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPUltTop"  onclick="rpPromos.pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0">POR PÁGINA:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="rpPromos.pagCambiarTamano('Top')">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <!-- ── TABLA ───────────────────────────────────────────────────────── -->
    <div class="erp-table-wrap table-responsive border border-secondary border-opacity-25 rounded shadow-sm mb-2"
         style="max-height: 52vh; overflow-y:auto;">
        <table class="table table-hover table-sm align-middle mb-0 small" id="tablaPromos">
            <thead class="sticky-top" style="background-color: var(--bg-table-header);">
                <tr>
                    <th width="80">FECHA</th>
                    <th>PROMOCIÓN</th>
                    <th width="130">COMPROBANTE</th>
                    <th>CLIENTE</th>
                    <th width="90">CÓDIGO</th>
                    <th width="60" class="text-end">CANT.</th>
                    <th width="65" class="text-end">DESC %</th>
                    <th width="110" class="text-end">DESC $</th>
                    <th width="110">VENDEDOR</th>
                    <th width="90">USUARIO</th>
                </tr>
            </thead>
            <tbody id="tbodyPromos">
                <tr>
                    <td colspan="10" class="text-center py-5 text-muted">
                        Seleccione un rango de fechas y presione CONSULTAR
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- ── PAGINACIÓN INFERIOR ─────────────────────────────────────────── -->
    <div class="erp-pagination mt-1 mb-2" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <div class="erp-pag-jump me-2">
                <span>IR A</span>
                <input type="number" id="pagJumpInput" min="1" value="1">
                <button class="erp-pag-btn" onclick="rpPromos.pagJump()">IR</button>
            </div>
            <button class="erp-pag-btn" id="btnPPrim" onclick="rpPromos.pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPAnt"  onclick="rpPromos.pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPSig"  onclick="rpPromos.pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPUlt"  onclick="rpPromos.pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0">POR PÁGINA:</label>
            <select id="pagTamano" class="erp-pag-select" onchange="rpPromos.pagCambiarTamano('')">
                <option value="20" selected>20</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </div>
    </div>

    <!-- ── TOTALIZADOR ─────────────────────────────────────────────────── -->
    <div class="card card-filtros mt-2" id="totalizadorWrap" style="display:none;">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap gap-4 align-items-center justify-content-between">
                <div class="d-flex gap-4 flex-wrap">
                    <div>
                        <small class="text-muted d-block" style="font-size:9px; font-weight:700; text-transform:uppercase;">Registros mostrados</small>
                        <span class="fw-bold" style="color: var(--text-main);" id="totRegistros">0</span>
                    </div>
                    <div>
                        <small class="text-muted d-block" style="font-size:9px; font-weight:700; text-transform:uppercase;">Unidades en promo</small>
                        <span class="fw-bold text-warning" id="totCantidad">0</span>
                    </div>
                </div>
                <div class="text-end">
                    <small class="text-muted d-block" style="font-size:9px; font-weight:700; text-transform:uppercase;">Total descuentos otorgados</small>
                    <span class="fw-bold text-danger" style="font-size:20px;" id="totDescuento">$ 0,00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const H   = { 'X-Requested-With': 'XMLHttpRequest' };
    const API = 'api_reporte_promociones.php';

    let todosLosRegistros = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    window.pagActual = 1; window.pagTotal = 1;

    // ── Tipos con colores (mismo criterio que el ABM) ─────────────────
    const TIPO_BADGE = {
        LLEVA_PAGA:  { label: 'LLEVA/PAGA',  cls: 'background:#1e3a5f;color:#60a5fa;border:1px solid #2563eb' },
        '2DA_UNIDAD':{ label: '2DA UNIDAD',  cls: 'background:#1f2d3d;color:#a78bfa;border:1px solid #7c3aed' },
        CANT_DESC:   { label: 'CANT. DESC.', cls: 'background:#1a3a2a;color:#4ade80;border:1px solid #16a34a' },
        FECHA_PROD:  { label: 'PROD. FECHA', cls: 'background:#3a2a10;color:#fbbf24;border:1px solid #d97706' },
        FECHA_RUBRO: { label: 'RUBRO',       cls: 'background:#2a1a3a;color:#f472b6;border:1px solid #db2777' },
        FECHA_MARCA: { label: 'MARCA',       cls: 'background:#1e3a3a;color:#2dd4bf;border:1px solid #0d9488' },
    };

    function tipoBadge(tipo) {
        const t = TIPO_BADGE[tipo];
        if (!t) return '';
        return `<span style="font-size:8px;font-weight:900;padding:1px 6px;border-radius:10px;${t.cls};white-space:nowrap;">${t.label}</span>`;
    }

    function fmt(n)    { return (parseFloat(n)||0).toLocaleString('es-AR', {minimumFractionDigits:2}); }
    function fmtN(n)   { return (parseFloat(n)||0).toLocaleString('es-AR', {minimumFractionDigits:0}); }
    function fmtFecha(d) {
        if (!d) return '-';
        const [y, m, dia] = d.split('-');
        return `${dia}/${m}/${y}`;
    }
    function compStr(r) {
        return String(r.ptovta).padStart(4,'0') + '-' + String(r.numcomp).padStart(8,'0');
    }

    // ── Cargar combos de filtros ──────────────────────────────────────
    async function init() {
        const res = await fetch(`${API}?action=get_filtros`, { headers: H }).then(r => r.json());
        if (!res.success) return;

        const $p = $('#fPromo');
        res.promociones.forEach(p => $p.append(new Option(p.nombre.toUpperCase(), p.id)));

        const $v = $('#fVendedor');
        res.vendedores.forEach(v => $v.append(new Option(v.nombre.toUpperCase(), v.id)));

        // Enter en filtros → consultar
        $('#fDesde, #fHasta').on('keypress', e => { if (e.key === 'Enter') rpPromos.consultar(); });

        // Exportar
        document.getElementById('btnExportar').addEventListener('click', exportar);
    }

    // ── Consulta principal ────────────────────────────────────────────
    async function consultar() {
        const payload = {
            desde:       $('#fDesde').val(),
            hasta:       $('#fHasta').val(),
            promo_id:    parseInt($('#fPromo').val())    || 0,
            vendedor_id: parseInt($('#fVendedor').val()) || 0,
        };

        Swal.fire({ title: 'Consultando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

        try {
            const res = await fetch(API + '?action=consultar', {
                method:  'POST',
                headers: { ...H, 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload)
            }).then(r => r.json());

            Swal.close();

            if (!res.success) return Swal.fire('Error', res.error, 'error');

            todosLosRegistros = res.data;
            pagActual = 1; window.pagActual = 1;

            // Stats globales
            $('#stUsos').text(todosLosRegistros.length.toLocaleString('es-AR'));
            $('#stUnidades').text(fmtN(res.tot_cantidad));
            $('#stDescuento').text('$ ' + fmt(res.tot_descuento));
            $('#statsRow').css('display', 'flex');
            $('#totalizadorWrap').show();

            // Ranking por promo
            renderRanking(res.por_promo);

            // Totalizador inferior
            $('#totRegistros').text(todosLosRegistros.length.toLocaleString('es-AR'));
            $('#totCantidad').text(fmtN(res.tot_cantidad));
            $('#totDescuento').text('$ ' + fmt(res.tot_descuento));

            renderPagina();

        } catch (e) {
            Swal.close();
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        }
    }

    // ── Ranking de promos (cards) ─────────────────────────────────────
    function renderRanking(porPromo) {
        const $w = $('#rankingCards').empty();
        if (!porPromo.length) { $('#rankingWrap').hide(); return; }

        $('#rankingWrap').show();
        const maxDesc = porPromo[0].descuento;

        porPromo.forEach((p, i) => {
            const pct = maxDesc > 0 ? Math.round((p.descuento / maxDesc) * 100) : 0;
            const medal = i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : `${i+1}.`;
            $w.append(`
                <div style="background:var(--bg-card);border:1px solid var(--border-color);border-radius:8px;padding:10px 14px;min-width:180px;flex:1;">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <span style="font-size:11px;font-weight:700;color:var(--text-main);">${medal} ${p.nombre}</span>
                        ${tipoBadge(p.tipo)}
                    </div>
                    <div style="height:4px;border-radius:2px;background:var(--border-color);overflow:hidden;margin-bottom:6px;">
                        <div style="height:100%;width:${pct}%;background:var(--accent);border-radius:2px;"></div>
                    </div>
                    <div class="d-flex justify-content-between" style="font-size:10px;color:var(--text-muted);">
                        <span>${p.usos} uso${p.usos!==1?'s':''} · ${fmtN(p.cantidad)} u.</span>
                        <span class="text-danger fw-bold">-$ ${fmt(p.descuento)}</span>
                    </div>
                </div>
            `);
        });
    }

    // ── Paginación ────────────────────────────────────────────────────
    function renderPagina() {
        const total  = todosLosRegistros.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const slice = todosLosRegistros.slice((pagActual - 1) * pagTam, pagActual * pagTam);
        renderTabla(slice);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const $b = $('#tbodyPromos').empty();

        if (!data.length) {
            $b.html('<tr><td colspan="10" class="text-center py-5 text-muted">Sin resultados para los filtros seleccionados.</td></tr>');
            return;
        }

        data.forEach(r => {
            $b.append(`
            <tr class="align-middle">
                <td style="font-size:11px;">${fmtFecha(r.fecha)}</td>
                <td>
                    <div style="font-size:11px;font-weight:600;color:var(--text-main);">${r.promo_nombre}</div>
                    <div>${tipoBadge(r.promo_tipo)}</div>
                </td>
                <td>
                    <a href="#" onclick="verComprobante(${r.idticket}); return false;"
                       class="text-accent fw-bold" style="font-size:11px;text-decoration:none;">
                        ${compStr(r)}
                    </a>
                </td>
                <td class="text-truncate" style="max-width:160px;font-size:11px;">${r.cliente_nombre || '-'}</td>
                <td class="fw-bold text-accent" style="font-size:11px;">${r.codigo_prod}</td>
                <td class="text-end" style="font-size:11px;">${fmtN(r.cantidad)}</td>
                <td class="text-end text-warning fw-bold" style="font-size:11px;">${parseFloat(r.descuento_pct).toFixed(2)}%</td>
                <td class="text-end text-danger fw-bold" style="font-size:11px;">-$ ${fmt(r.descuento_total)}</td>
                <td style="font-size:11px;color:var(--text-muted);">${r.vendedor_nombre || '-'}</td>
                <td style="font-size:11px;color:var(--text-muted);">${r.usuario}</td>
            </tr>`);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1);
        const hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> registros`;

        ['','Top'].forEach(sfx => {
            const elInfo = document.getElementById('pagInfo'+sfx);
            if (elInfo) elInfo.innerHTML = info;
            ['btnPPrim','btnPAnt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPSig','btnPUlt'].forEach(id => { const e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            const cont = document.getElementById('pagBotones'+sfx);
            if (cont) {
                cont.innerHTML = '';
                if (pagTotal > 1) {
                    let ini = Math.max(1, pagActual-2), fin = Math.min(pagTotal, ini+4);
                    if (fin-ini < 4) ini = Math.max(1, fin-4);
                    for (let i=ini; i<=fin; i++) {
                        const b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = () => rpPromos.pagGo(i);
                        cont.appendChild(b);
                    }
                }
            }
            const ji  = document.getElementById('pagJumpInput'+sfx);  if(ji)  { ji.value=pagActual; ji.max=pagTotal; }
            const sel = document.getElementById('pagTamano'+sfx);      if(sel)   sel.value=pagTam;
            const wrap = document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap');
            if (wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    // ── Exportar ──────────────────────────────────────────────────────
    async function exportar() {
        const desde  = $('#fDesde').val();
        const hasta  = $('#fHasta').val();
        const promo  = $('#fPromo').val()    || 0;
        const vend   = $('#fVendedor').val() || 0;

        const btn = document.getElementById('btnExportar');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>EXPORTANDO...';

        const url = `${API}?action=exportar&desde=${desde}&hasta=${hasta}&promo_id=${promo}&vendedor_id=${vend}`;
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', '');
        document.body.appendChild(link); link.click(); document.body.removeChild(link);

        setTimeout(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-file-excel me-1"></i>EXCEL';
        }, 2000);
    }

    // ── Navegar al comprobante ────────────────────────────────────────
    window.verComprobante = function(id) {
        window.open('imprimir_ticket.php?id=' + id, '_blank');
    };

    // ── API pública ───────────────────────────────────────────────────
    window.rpPromos = {
        consultar,
        limpiar: () => {
            $('#fPromo').val(0);
            $('#fVendedor').val(0);
            todosLosRegistros = [];
            $('#tbodyPromos').html('<tr><td colspan="10" class="text-center py-5 text-muted">Seleccione un rango de fechas y presione CONSULTAR</td></tr>');
            $('#statsRow, #rankingWrap, #pagTopWrap, #pagBotWrap, #totalizadorWrap').hide();
            $('#rankingCards').empty();
        },
        pagGo: (n) => {
            n = Math.max(1, Math.min(pagTotal, n));
            if (n === pagActual) return;
            pagActual = n; window.pagActual = n;
            renderPagina();
            document.getElementById('moduloReportePromos')?.scrollIntoView({ behavior: 'smooth' });
        },
        pagJump:    () => { const v = parseInt(document.getElementById('pagJumpInput').value);    if(!isNaN(v)) rpPromos.pagGo(v); },
        pagJumpTop: () => { const v = parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v)) rpPromos.pagGo(v); },
        pagCambiarTamano: (sfx) => {
            pagTam = parseInt(document.getElementById('pagTamano'+sfx).value);
            pagActual = 1; window.pagActual = 1;
            renderPagina();
        }
    };

    init();
})();
</script>