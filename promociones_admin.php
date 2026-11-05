<?php
/**
 * promociones_admin.php — GESTIÓN DE PROMOCIONES COMERCIALES
 * West IT ERP v1.0
 */
require_once 'check_access.php';
?>

<style>
/* ── Layout base ────────────────────────────────────────────── */
#modPromo {
    padding: 0 16px 16px;
    height: calc(100vh - 65px);
    display: flex;
    flex-direction: column;
    gap: 10px;
    overflow: hidden;
}

/* ── Badges de tipo (colores propios, son semánticos, no de tema) ── */
.badge-tipo {
    font-size: 9px; font-weight: 900; letter-spacing: .6px;
    padding: 3px 8px; border-radius: 20px; text-transform: uppercase;
    white-space: nowrap;
}
.t-LLEVA_PAGA  { background: #1e3a5f; color: #60a5fa; border: 1px solid #2563eb; }
.t-2DA_UNIDAD  { background: #1f2d3d; color: #a78bfa; border: 1px solid #7c3aed; }
.t-CANT_DESC   { background: #1a3a2a; color: #4ade80; border: 1px solid #16a34a; }
.t-FECHA_PROD  { background: #3a2a10; color: #fbbf24; border: 1px solid #d97706; }
.t-FECHA_RUBRO { background: #2a1a3a; color: #f472b6; border: 1px solid #db2777; }
.t-FECHA_MARCA { background: #1e3a3a; color: #2dd4bf; border: 1px solid #0d9488; }

/* ── Indicador vigencia ─────────────────────────────────────── */
.dot-vigente   { width:8px; height:8px; border-radius:50%; background:#22c55e; display:inline-block; box-shadow:0 0 6px #22c55e; }
.dot-vencida   { width:8px; height:8px; border-radius:50%; background:#ef4444; display:inline-block; }
.dot-futura    { width:8px; height:8px; border-radius:50%; background:#f59e0b; display:inline-block; }
.dot-siempre   { width:8px; height:8px; border-radius:50%; background:#6366f1; display:inline-block; box-shadow:0 0 6px #6366f1; }

/* ── Barra de progreso stock ────────────────────────────────── */
.stock-bar { height: 5px; border-radius: 3px; background: var(--border-color); overflow: hidden; margin-top: 3px; }
.stock-bar-fill { height: 100%; border-radius: 3px; background: linear-gradient(90deg, #0ea5e9, #22d3ee); transition: width .4s; }
.stock-bar-fill.warn { background: linear-gradient(90deg, #f59e0b, #ef4444); }

/* ── Tabla ──────────────────────────────────────────────────── */
.promo-table-wrap {
    flex: 1; overflow-y: auto;
    border: 1px solid var(--input-border);
    border-radius: 6px;
    background: var(--bg-card);
}

/* ── Modal: caja de regla dinámica ──────────────────────────── */
.campo-promo { display: none; }
.campo-promo.visible { display: block; }

.regla-box {
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 6px;
    padding: 12px 14px;
    margin-bottom: 4px;
}
.regla-titulo {
    font-size: 10px; font-weight: 900; color: var(--accent);
    text-transform: uppercase; letter-spacing: .8px;
    margin-bottom: 10px;
    display: flex; align-items: center; gap: 6px;
}

/* ── Filtros rápidos (pills) ────────────────────────────────── */
.pill-filter {
    cursor: pointer; border-radius: 20px; padding: 3px 12px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
    border: 1px solid var(--input-border); color: var(--text-muted);
    background: transparent; transition: all .15s;
    white-space: nowrap;
}
.pill-filter:hover, .pill-filter.active {
    background: var(--accent); color: #000; border-color: var(--accent);
}

/* ── Card stats header ──────────────────────────────────────── */
.stat-card {
    background: var(--bg-card); border: 1px solid var(--input-border);
    border-radius: 6px; padding: 8px 14px; min-width: 110px; flex-shrink: 0;
}
.stat-num  { font-size: 24px; font-weight: 900; color: var(--accent); line-height: 1; }
.stat-lbl  { font-size: 9px; color: var(--text-muted); font-weight: 700;
             text-transform: uppercase; letter-spacing: .5px; margin-top: 2px; }

/* ── Badge de lista en tabla ────────────────────────────────── */
.badge-lista {
    background: var(--input-bg);
    color: var(--text-muted);
    border: 1px solid var(--border-color);
    font-size: 10px; padding: 2px 8px; border-radius: 20px; font-weight: 700;
}

/* ── Sugerencias de producto ────────────────────────────────── */
#pm_prod_sugg .list-group-item {
    background: var(--bg-modal);
    color: var(--text-main);
    border-color: var(--border-color);
    font-size: 12px;
}
#pm_prod_sugg .list-group-item:hover {
    background: var(--accent-soft);
    color: var(--accent);
}
</style>

<div id="modPromo" class="fade-in">

    <!-- ── HEADER ───────────────────────────────────────────────────── -->
    <div class="d-flex justify-content-between align-items-center pt-3">
        <div>
            <h4 class="mb-0 fw-bold" style="color: var(--text-titles);">
                <i class="fas fa-tags me-2 text-accent"></i>PROMOCIONES COMERCIALES
            </h4>
            <small class="text-muted">Gestión de descuentos, bonificaciones y ofertas por tiempo o cantidad</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4" onclick="promoApp.nuevo()">
            <i class="fas fa-plus me-2"></i>NUEVA PROMOCIÓN
        </button>
    </div>

    <!-- ── STATS ────────────────────────────────────────────────────── -->
    <div class="d-flex gap-2 flex-wrap" id="statsRow">
        <div class="stat-card">
            <div class="stat-num" id="st-total">—</div>
            <div class="stat-lbl">Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-num text-success" id="st-vigentes">—</div>
            <div class="stat-lbl">Vigentes</div>
        </div>
        <div class="stat-card">
            <div class="stat-num text-warning" id="st-vencidas">—</div>
            <div class="stat-lbl">Vencidas</div>
        </div>
        <div class="stat-card">
            <div class="stat-num text-danger" id="st-deshabilitadas">—</div>
            <div class="stat-lbl">Deshabilitadas</div>
        </div>
    </div>

    <!-- ── FILTROS ──────────────────────────────────────────────────── -->
    <div class="d-flex gap-2 flex-wrap align-items-center">
        <input type="text" id="fBuscar" class="form-control form-control-sm"
               placeholder="Buscar por nombre, código, rubro o marca..."
               style="max-width:280px" oninput="promoApp.filtrar()">

        <div class="d-flex gap-1 flex-wrap" id="pillsTipo">
            <button class="pill-filter active" data-tipo="">TODOS</button>
            <button class="pill-filter t-LLEVA_PAGA"  data-tipo="LLEVA_PAGA" >LLEVA/PAGA</button>
            <button class="pill-filter t-2DA_UNIDAD"  data-tipo="2DA_UNIDAD" >2DA UNIDAD</button>
            <button class="pill-filter t-CANT_DESC"   data-tipo="CANT_DESC"  >CANT. DESC.</button>
            <button class="pill-filter t-FECHA_PROD"  data-tipo="FECHA_PROD" >PROD. FECHA</button>
            <button class="pill-filter t-FECHA_RUBRO" data-tipo="FECHA_RUBRO">RUBRO</button>
            <button class="pill-filter t-FECHA_MARCA" data-tipo="FECHA_MARCA">MARCA</button>
        </div>

        <div class="d-flex gap-1 ms-auto">
            <button class="pill-filter" id="btnSoloVigentes" onclick="promoApp.toggleVigentes()">
                <i class="fas fa-clock me-1"></i>SOLO VIGENTES
            </button>
        </div>
    </div>

    <!-- ── TABLA ────────────────────────────────────────────────────── -->
    <div class="promo-table-wrap">
        <table class="table table-hover table-sm mb-0 small" id="tablaPromos">
            <thead class="sticky-top" style="background-color: var(--bg-table-header);">
                <tr>
                    <th width="30" class="text-center">EST.</th>
                    <th width="110">TIPO</th>
                    <th>NOMBRE / ALCANCE</th>
                    <th width="100" class="text-center">DESCUENTO</th>
                    <th width="130" class="text-center">VIGENCIA</th>
                    <th width="130" class="text-center">STOCK PROMO</th>
                    <th width="60" class="text-center">LISTA</th>
                    <th width="90" class="text-center">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyPromos">
                <tr><td colspan="8" class="text-center py-5 text-muted">Cargando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════
     MODAL ABM
═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modPromoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid var(--border-color);">
                <h6 class="modal-title fw-bold text-accent" id="modalPromoTitle">
                    <i class="fas fa-tags me-2"></i>NUEVA PROMOCIÓN
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-4">
                <input type="hidden" id="pm_id">

                <!-- NOMBRE + TIPO -->
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label-custom">NOMBRE DE LA PROMOCIÓN *</label>
                        <input type="text" id="pm_nombre" class="form-control"
                               placeholder="Ej: 3x2 ACEITE MARCA X">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label-custom">TIPO *</label>
                        <select id="pm_tipo" class="form-select" onchange="promoApp.cambiarTipo()">
                            <option value="">— Seleccione —</option>
                            <option value="LLEVA_PAGA">🛒 Lleva X, Paga Z</option>
                            <option value="2DA_UNIDAD">2️⃣  2da Unidad al X% de descuento</option>
                            <option value="CANT_DESC">📦 X% si compra Z o más unidades</option>
                            <option value="FECHA_PROD">🏷️  Producto con X% entre fechas</option>
                            <option value="FECHA_RUBRO">📂 Rubro con X% entre fechas</option>
                            <option value="FECHA_MARCA">🔖 Marca con X% entre fechas</option>
                        </select>
                    </div>
                </div>

                <!-- REGLA DINÁMICA -->
                <div class="regla-box" id="boxRegla" style="display:none;">
                    <div class="regla-titulo"><i class="fas fa-sliders-h"></i> REGLA DE LA PROMOCIÓN</div>

                    <!-- Producto -->
                    <div class="campo-promo mb-3" id="cp_producto">
                        <label class="form-label-custom">PRODUCTO *</label>
                        <div class="input-group">
                            <input type="text"   id="pm_codigo_prod_txt" class="form-control"
                                   placeholder="Escriba código o descripción..." oninput="promoApp.buscarProd(this.value)">
                            <input type="hidden" id="pm_codigo_prod">
                        </div>
                        <div id="pm_prod_sugg" class="list-group mt-1" style="max-height:180px;overflow-y:auto;display:none;"></div>
                        <small class="text-muted" id="pm_prod_desc"></small>
                    </div>

                    <!-- Rubro -->
                    <div class="campo-promo mb-3" id="cp_rubro">
                        <label class="form-label-custom">RUBRO *</label>
                        <select id="pm_rubro" class="form-select select2-simple"></select>
                    </div>

                    <!-- Marca -->
                    <div class="campo-promo mb-3" id="cp_marca">
                        <label class="form-label-custom">MARCA *</label>
                        <select id="pm_marca" class="form-select select2-simple"></select>
                    </div>

                    <!-- LLEVA / PAGA -->
                    <div class="campo-promo" id="cp_llevar_pagar">
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label-custom">LLEVA (cant.)</label>
                                <input type="number" id="pm_cant_llevar" class="form-control" min="1" step="1" placeholder="3">
                            </div>
                            <div class="col-6">
                                <label class="form-label-custom">PAGA (cant.)</label>
                                <input type="number" id="pm_cant_pagar"  class="form-control" min="1" step="1" placeholder="2">
                            </div>
                        </div>
                    </div>

                    <!-- Cant mínima -->
                    <div class="campo-promo" id="cp_cant_min">
                        <label class="form-label-custom">CANTIDAD MÍNIMA PARA ACTIVAR *</label>
                        <input type="number" id="pm_cant_minima" class="form-control" min="1" step="1" placeholder="2">
                    </div>

                    <!-- Descuento % -->
                    <div class="campo-promo mt-3" id="cp_desc_pct">
                        <label class="form-label-custom">DESCUENTO % *</label>
                        <div class="input-group">
                            <input type="number" id="pm_descuento_pct" class="form-control" min="0.01" max="100" step="0.01" placeholder="15.00">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                </div>

                <!-- VIGENCIA + LISTA -->
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label-custom">FECHA DESDE</label>
                        <input type="date" id="pm_fecha_desde" class="form-control">
                        <small class="text-muted">Dejar vacío = sin inicio fijo</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">FECHA HASTA</label>
                        <input type="date" id="pm_fecha_hasta" class="form-control">
                        <small class="text-muted">Dejar vacío = sin vencimiento</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">LISTA DE PRECIOS</label>
                        <select id="pm_lista" class="form-select">
                            <option value="0">TODAS LAS LISTAS</option>
                            <?php for($i=1;$i<=8;$i++) echo "<option value='$i'>LISTA $i</option>"; ?>
                        </select>
                    </div>
                </div>

                <!-- STOCK COMPROMETIDO -->
                <div class="row g-3 mt-1">
                    <div class="col-md-6">
                        <label class="form-label-custom">STOCK COMPROMETIDO (unidades)</label>
                        <input type="number" id="pm_stock_comp" class="form-control" min="1" step="1"
                               placeholder="Dejar vacío = sin límite">
                        <small class="text-muted">Cantidad máxima de unidades cubiertas por esta promo</small>
                    </div>
                    <div class="col-md-6" id="pm_stock_usado_wrap" style="display:none;">
                        <label class="form-label-custom">STOCK USADO (informativo)</label>
                        <input type="number" id="pm_stock_usado" class="form-control" readonly
                               style="opacity:.6;">
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="border-top: 1px solid var(--border-color);">
                <button class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">CANCELAR</button>
                <button class="btn btn-info fw-bold rounded-pill px-5" onclick="promoApp.guardar()">
                    <i class="fas fa-save me-2"></i>GUARDAR
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const H = { 'X-Requested-With': 'XMLHttpRequest' };
    const API = 'api_promociones.php';

    let todosLosPromos = [];
    let filtroTipo     = '';
    let soloVigentes   = false;
    let prodSearchTimer = null;

    const HOY = new Date().toISOString().split('T')[0];

    // ── Helpers ──────────────────────────────────────────────────────────
    function fmt(d) {
        if (!d) return '—';
        const [y, m, dia] = d.split('-');
        return `${dia}/${m}/${y}`;
    }

    function estadoVigencia(p) {
        if (!p.fecha_desde && !p.fecha_hasta) return { cls: 'dot-siempre', txt: 'SIEMPRE' };
        const desde = p.fecha_desde || '0000-00-00';
        const hasta = p.fecha_hasta || '9999-12-31';
        if (HOY < desde) return { cls: 'dot-futura',  txt: 'FUTURA'  };
        if (HOY > hasta) return { cls: 'dot-vencida', txt: 'VENCIDA' };
        return { cls: 'dot-vigente', txt: 'VIGENTE' };
    }

    function labelTipo(tipo) {
        const map = {
            LLEVA_PAGA:   'LLEVA/PAGA',
            '2DA_UNIDAD': '2DA UNIDAD',
            CANT_DESC:    'CANT. DESC.',
            FECHA_PROD:   'PROD. FECHA',
            FECHA_RUBRO:  'RUBRO',
            FECHA_MARCA:  'MARCA'
        };
        return map[tipo] || tipo;
    }

    function resumenRegla(p) {
        switch (p.tipo) {
            case 'LLEVA_PAGA':  return `<span class="text-info fw-bold">${p.codigo_prod}</span> — Lleva <b>${p.cant_llevar}</b>, paga <b>${p.cant_pagar}</b>`;
            case '2DA_UNIDAD':  return `<span class="text-info fw-bold">${p.codigo_prod}</span> — 2da unidad al <b>${p.descuento_pct}%</b> OFF (mín. ${p.cant_minima ?? 2} u.)`;
            case 'CANT_DESC':   return `<span class="text-info fw-bold">${p.codigo_prod}</span> — <b>${p.descuento_pct}%</b> con ≥ <b>${p.cant_minima}</b> u.`;
            case 'FECHA_PROD':  return `<span class="text-info fw-bold">${p.codigo_prod}</span> — <b>${p.descuento_pct}%</b> de desc.`;
            case 'FECHA_RUBRO': return `Rubro <span class="text-accent fw-bold">${p.rubro}</span> — <b>${p.descuento_pct}%</b> de desc.`;
            case 'FECHA_MARCA': return `Marca <span class="text-accent fw-bold">${p.marca}</span> — <b>${p.descuento_pct}%</b> de desc.`;
        }
        return '—';
    }

    function stockBar(p) {
        if (!p.stock_comprometido) return '<small class="text-muted">Sin límite</small>';
        const pct  = Math.min(100, Math.round((p.stock_usado / p.stock_comprometido) * 100));
        const warn = pct >= 80;
        return `
            <div style="font-size:10px;">${p.stock_usado} / ${p.stock_comprometido} u. (${pct}%)</div>
            <div class="stock-bar">
                <div class="stock-bar-fill ${warn ? 'warn' : ''}" style="width:${pct}%"></div>
            </div>`;
    }

    // ── Cargar datos ──────────────────────────────────────────────────────
    async function cargar() {
        const res = await fetch(`${API}?action=listar`, { headers: H }).then(r => r.json());
        if (!res.success) return;
        todosLosPromos = res.data;
        actualizarStats();
        renderTabla();
    }

    function actualizarStats() {
        const total = todosLosPromos.length;
        const vigentes = todosLosPromos.filter(p => {
            if (!parseInt(p.habilitada)) return false;
            const v = estadoVigencia(p);
            return v.txt === 'VIGENTE' || v.txt === 'SIEMPRE';
        }).length;
        const vencidas       = todosLosPromos.filter(p => estadoVigencia(p).txt === 'VENCIDA').length;
        const deshabilitadas = todosLosPromos.filter(p => !parseInt(p.habilitada)).length;

        $('#st-total').text(total);
        $('#st-vigentes').text(vigentes);
        $('#st-vencidas').text(vencidas);
        $('#st-deshabilitadas').text(deshabilitadas);
    }

    function renderTabla() {
        const q   = ($('#fBuscar').val() || '').toLowerCase();
        let datos = todosLosPromos;

        if (filtroTipo)   datos = datos.filter(p => p.tipo === filtroTipo);
        if (q)            datos = datos.filter(p =>
            (p.nombre      || '').toLowerCase().includes(q) ||
            (p.codigo_prod || '').toLowerCase().includes(q) ||
            (p.rubro       || '').toLowerCase().includes(q) ||
            (p.marca       || '').toLowerCase().includes(q)
        );
        if (soloVigentes) datos = datos.filter(p => {
            const v = estadoVigencia(p);
            return parseInt(p.habilitada) && (v.txt === 'VIGENTE' || v.txt === 'SIEMPRE');
        });

        const $b = $('#tbodyPromos').empty();

        if (!datos.length) {
            $b.html('<tr><td colspan="8" class="text-center py-5 text-muted">Sin promociones que coincidan con el filtro.</td></tr>');
            return;
        }

        datos.forEach(p => {
            const v   = estadoVigencia(p);
            const hab = parseInt(p.habilitada);

            $b.append(`
            <tr class="align-middle" style="${!hab ? 'opacity:.45;' : ''}">
                <td class="text-center">
                    <span class="${v.cls}" title="${v.txt}"></span>
                </td>
                <td><span class="badge-tipo t-${p.tipo}">${labelTipo(p.tipo)}</span></td>
                <td>
                    <div class="fw-bold" style="font-size:12px;">${p.nombre}</div>
                    <div class="text-muted" style="font-size:11px;">${resumenRegla(p)}</div>
                </td>
                <td class="text-center">
                    ${p.descuento_pct
                        ? `<span class="fw-bold text-warning fs-6">${parseFloat(p.descuento_pct).toFixed(2)}%</span>`
                        : p.cant_llevar
                            ? `<span class="text-info fw-bold">${p.cant_llevar}→${p.cant_pagar}</span>`
                            : '—'}
                </td>
                <td class="text-center" style="font-size:11px;">
                    ${p.fecha_desde || p.fecha_hasta
                        ? `<span class="${v.cls === 'dot-vencida' ? 'text-danger' : v.cls === 'dot-futura' ? 'text-warning' : 'text-success'}">${fmt(p.fecha_desde)} → ${fmt(p.fecha_hasta)}</span>`
                        : '<span class="text-accent opacity-75"><i class="fas fa-infinity me-1"></i>Siempre</span>'}
                </td>
                <td class="text-center">${stockBar(p)}</td>
                <td class="text-center">
                    <span class="badge-lista">
                        ${parseInt(p.lista_aplicable) === 0 ? 'TODAS' : 'Lista ' + p.lista_aplicable}
                    </span>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-link text-info p-1" title="Editar" onclick="promoApp.editar(${p.id})">
                            <i class="fas fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-link p-1 ${hab ? 'text-warning' : 'text-success'}" title="${hab ? 'Deshabilitar' : 'Habilitar'}" onclick="promoApp.toggle(${p.id})">
                            <i class="fas fa-${hab ? 'toggle-on' : 'toggle-off'}"></i>
                        </button>
                        <button class="btn btn-link text-danger p-1" title="Eliminar" onclick="promoApp.eliminar(${p.id}, '${p.nombre.replace(/'/g,"\\'")}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>`);
        });
    }

    // ── Gestión del modal ─────────────────────────────────────────────────
    function limpiarModal() {
        ['pm_id','pm_nombre','pm_codigo_prod','pm_codigo_prod_txt','pm_cant_llevar',
         'pm_cant_pagar','pm_cant_minima','pm_descuento_pct','pm_fecha_desde',
         'pm_fecha_hasta','pm_stock_comp','pm_stock_usado'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        $('#pm_tipo').val('').trigger('change');
        $('#pm_lista').val(0);
        $('#pm_prod_desc').text('');
        $('#pm_stock_usado_wrap').hide();
        $('#boxRegla').hide();
        aplicarCampos('');
    }

    function aplicarCampos(tipo) {
        document.querySelectorAll('.campo-promo').forEach(el => el.classList.remove('visible'));
        $('#boxRegla').hide();
        if (!tipo) return;

        $('#boxRegla').show();

        switch (tipo) {
            case 'LLEVA_PAGA':  mostrar(['cp_producto', 'cp_llevar_pagar']); break;
            case '2DA_UNIDAD':  mostrar(['cp_producto', 'cp_cant_min', 'cp_desc_pct']); break;
            case 'CANT_DESC':   mostrar(['cp_producto', 'cp_cant_min', 'cp_desc_pct']); break;
            case 'FECHA_PROD':  mostrar(['cp_producto', 'cp_desc_pct']); break;
            case 'FECHA_RUBRO': mostrar(['cp_rubro', 'cp_desc_pct']); break;
            case 'FECHA_MARCA': mostrar(['cp_marca', 'cp_desc_pct']); break;
        }
    }

    function mostrar(ids) {
        ids.forEach(id => document.getElementById(id)?.classList.add('visible'));
    }

    // ── Búsqueda de producto en el modal ─────────────────────────────────
    async function buscarProd(q) {
        clearTimeout(prodSearchTimer);
        if (!q || q.length < 2) { $('#pm_prod_sugg').hide(); return; }
        prodSearchTimer = setTimeout(async () => {
            const res = await fetch(`${API}?action=buscar_productos&q=${encodeURIComponent(q)}`, { headers: H }).then(r => r.json());
            const $s  = $('#pm_prod_sugg').empty();
            if (!res.success || !res.data.length) { $s.hide(); return; }
            res.data.forEach(p => {
                $('<button type="button" class="list-group-item list-group-item-action py-1 px-2">')
                    .html(`<strong class="text-accent">${p.codigo}</strong> — ${p.descripcion} <small class="text-muted">(${p.rubro})</small>`)
                    .on('click', () => {
                        $('#pm_codigo_prod').val(p.codigo);
                        $('#pm_codigo_prod_txt').val(p.codigo + ' — ' + p.descripcion);
                        $('#pm_prod_desc').text(p.rubro + ' / ' + p.marca);
                        $s.hide();
                    })
                    .appendTo($s);
            });
            $s.show();
        }, 300);
    }

    // ── Cargar rubros y marcas ────────────────────────────────────────────
    async function cargarRubros() {
        const res = await fetch(`${API}?action=get_rubros`, { headers: H }).then(r => r.json());
        const $s  = $('#pm_rubro').empty().append(new Option('— Seleccione —', ''));
        (res.data || []).forEach(r => $s.append(new Option(r, r)));
    }

    async function cargarMarcas() {
        const res = await fetch(`${API}?action=get_marcas`, { headers: H }).then(r => r.json());
        const $s  = $('#pm_marca').empty().append(new Option('— Seleccione —', ''));
        (res.data || []).forEach(m => $s.append(new Option(m, m)));
    }

    // ── CRUD ──────────────────────────────────────────────────────────────
    async function nuevo() {
        limpiarModal();
        $('#modalPromoTitle').html('<i class="fas fa-plus me-2"></i>NUEVA PROMOCIÓN');
        await cargarRubros();
        await cargarMarcas();
        $('#modPromoModal').modal('show');
    }

    async function editar(id) {
        await cargarRubros();
        await cargarMarcas();
        const res = await fetch(`${API}?action=get&id=${id}`, { headers: H }).then(r => r.json());
        if (!res.success) return Swal.fire('Error', res.error, 'error');
        const p = res.data;

        limpiarModal();
        $('#modalPromoTitle').html('<i class="fas fa-pencil-alt me-2"></i>EDITAR PROMOCIÓN');
        $('#pm_id').val(p.id);
        $('#pm_nombre').val(p.nombre);
        $('#pm_tipo').val(p.tipo);
        aplicarCampos(p.tipo);

        $('#pm_codigo_prod').val(p.codigo_prod    || '');
        $('#pm_codigo_prod_txt').val(p.codigo_prod || '');
        $('#pm_rubro').val(p.rubro               || '');
        $('#pm_marca').val(p.marca               || '');
        $('#pm_cant_llevar').val(p.cant_llevar    || '');
        $('#pm_cant_pagar').val(p.cant_pagar      || '');
        $('#pm_cant_minima').val(p.cant_minima    || '');
        $('#pm_descuento_pct').val(p.descuento_pct || '');
        $('#pm_lista').val(p.lista_aplicable      || 0);
        $('#pm_fecha_desde').val(p.fecha_desde    || '');
        $('#pm_fecha_hasta').val(p.fecha_hasta    || '');
        $('#pm_stock_comp').val(p.stock_comprometido || '');
        if (p.stock_comprometido) {
            $('#pm_stock_usado').val(p.stock_usado || 0);
            $('#pm_stock_usado_wrap').show();
        }

        $('#modPromoModal').modal('show');
    }

    async function guardar() {
        const id   = $('#pm_id').val();
        const tipo = $('#pm_tipo').val();

        const payload = {
            action:             id ? 'actualizar' : 'crear',
            id:                 id || undefined,
            nombre:             $('#pm_nombre').val().trim(),
            tipo,
            codigo_prod:        ['LLEVA_PAGA','2DA_UNIDAD','CANT_DESC','FECHA_PROD'].includes(tipo) ? $('#pm_codigo_prod').val() : null,
            rubro:              tipo === 'FECHA_RUBRO' ? $('#pm_rubro').val() : null,
            marca:              tipo === 'FECHA_MARCA' ? $('#pm_marca').val() : null,
            cant_llevar:        $('#pm_cant_llevar').val()    || null,
            cant_pagar:         $('#pm_cant_pagar').val()     || null,
            cant_minima:        $('#pm_cant_minima').val()    || null,
            descuento_pct:      $('#pm_descuento_pct').val()  || null,
            lista_aplicable:    $('#pm_lista').val(),
            fecha_desde:        $('#pm_fecha_desde').val()    || null,
            fecha_hasta:        $('#pm_fecha_hasta').val()    || null,
            stock_comprometido: $('#pm_stock_comp').val()     || null
        };

        try {
            const res = await fetch(API, {
                method: 'POST',
                headers: { ...H, 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json());

            if (!res.success) return Swal.fire('Error', res.error, 'error');

            Swal.fire({ icon: 'success', title: res.message, toast: true,
                        position: 'top-end', showConfirmButton: false, timer: 2500 });
            $('#modPromoModal').modal('hide');
            cargar();
        } catch (e) {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        }
    }

    async function toggle(id) {
        const res = await fetch(API, {
            method: 'POST',
            headers: { ...H, 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle', id })
        }).then(r => r.json());
        if (!res.success) return Swal.fire('Error', res.error, 'error');
        cargar();
    }

    async function eliminar(id, nombre) {
        const conf = await Swal.fire({
            title: '¿Eliminar promoción?',
            html: `<b>${nombre}</b><br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'SÍ, ELIMINAR',
            cancelButtonText: 'CANCELAR',
            confirmButtonColor: '#dc3545'
        });
        if (!conf.isConfirmed) return;

        const res = await fetch(API, {
            method: 'POST',
            headers: { ...H, 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'eliminar', id })
        }).then(r => r.json());

        if (!res.success) return Swal.fire('Error', res.error, 'error');
        Swal.fire({ icon: 'success', title: res.message, toast: true,
                    position: 'top-end', showConfirmButton: false, timer: 2000 });
        cargar();
    }

    // ── Filtros ───────────────────────────────────────────────────────────
    $('#pillsTipo button').on('click', function () {
        $('#pillsTipo button').removeClass('active');
        $(this).addClass('active');
        filtroTipo = $(this).data('tipo');
        renderTabla();
    });

    // ── API pública ───────────────────────────────────────────────────────
    window.promoApp = {
        nuevo, editar, guardar, toggle, eliminar,
        filtrar: renderTabla,
        cambiarTipo: () => aplicarCampos($('#pm_tipo').val()),
        buscarProd,
        toggleVigentes: () => {
            soloVigentes = !soloVigentes;
            $('#btnSoloVigentes').toggleClass('active', soloVigentes);
            renderTabla();
        }
    };

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#pm_codigo_prod_txt, #pm_prod_sugg').length) {
            $('#pm_prod_sugg').hide();
        }
    });

    cargar();
})();
</script>