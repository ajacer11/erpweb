<?php
/**
 * productos_hist.php - VISTA FRAGMENTO (SPA)
 * Migración de "Productos - Historicos Venta.vb"
 * Módulo de SOLO CONSULTA sobre tabla 'historicoproductos'.
 * Diseño unificado con productos_admin.php
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="container-hist-productos">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" style="letter-spacing: 1px;"><i class="fa-solid fa-clock-rotate-left text-accent me-2"></i>HISTÓRICO DE PRODUCTOS</h4>
            <small class="text-accent opacity-75">CONSULTA DE MODIFICACIONES (TABLA HISTÓRICA)</small>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success btn-sm px-3 rounded-pill fw-bold" id="btnExportar">
                <i class="fa-solid fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm px-3 rounded-pill"
                onclick="loadModule('dashboard.php')">
                <i class="fa-solid fa-times me-1"></i> <span class="d-none d-sm-inline">CERRAR</span>
            </button>
        </div>
    </div>

    <div class="card border-secondary shadow mb-4" style="background-color: var(--bg-card); color: var(--text-main);">
        <div class="card-body p-4">
            <form id="frmFiltros">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label text-accent small mb-1">RUBRO</label>
                        <select class="form-select form-select-sm select2-filter" id="cmbRubro" name="rubro" style="width: 100%;">
                            <option value="">-- SELECCIONAR PARA FILTRAR --</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-accent small mb-1">FAMILIA</label>
                        <select class="form-select form-select-sm select2-filter" id="cmbFamilia" name="familia" style="width: 100%;">
                            <option value="">-- SELECCIONAR PARA FILTRAR --</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-accent small mb-1">MARCA</label>
                        <select class="form-select form-select-sm select2-filter" id="cmbMarca" name="marca" style="width: 100%;">
                            <option value="">-- SELECCIONAR PARA FILTRAR --</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-accent small mb-1">PROVEEDOR</label>
                        <select class="form-select form-select-sm select2-filter" id="cmbProveedor" name="proveedor" style="width: 100%;">
                            <option value="">-- SELECCIONAR PARA FILTRAR --</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-accent small mb-1">FECHA DESDE</label>
                        <input type="date" class="form-control form-control-sm bg-dark text-white border-secondary" id="dtmDesde" name="fecha_desde">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label text-accent small mb-1">FECHA HASTA</label>
                        <input type="date" class="form-control form-control-sm bg-dark text-white border-secondary" id="dtmHasta" name="fecha_hasta">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label text-accent small mb-1">ESTADO</label>
                        <div class="d-flex align-items-center gap-3 border border-secondary rounded px-3 bg-dark" style="height: 31px;">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="estado" id="rbTodos" value="todos" checked>
                                <label class="form-check-label text-white small pt-1" for="rbTodos">TODOS</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="estado" id="rbHab" value="1">
                                <label class="form-check-label text-white small pt-1" for="rbHab">HAB</label>
                            </div>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="radio" name="estado" id="rbDes" value="0">
                                <label class="form-check-label text-white small pt-1" for="rbDes">DES</label>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label text-accent small mb-1">BÚSQUEDA RÁPIDA</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control bg-dark text-white border-secondary" id="txtBusqueda" name="busqueda" placeholder="Buscar por código o descripción...">
                            <button class="btn btn-secondary px-3" type="submit" id="btnBuscar" title="Buscar">
                                <i class="fa-solid fa-sync"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfoTop">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotonesTop" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label class="erp-pag-info mb-0 d-none d-sm-inline" for="pagTamanoTop">VER:</label>
            <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option>
            </select>
        </div>
    </div>

    <div class="erp-table-wrap border border-secondary border-opacity-25 rounded shadow-sm mb-2" style="max-height: 60vh; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm mb-0 small text-nowrap" id="tblProductos">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th class="ps-3 py-2">FECHA MODIF.</th>
                    <th class="py-2">USUARIO</th>
                    <th class="py-2">CÓDIGO</th>
                    <th class="py-2 w-25">PRODUCTO</th>
                    <th class="py-2">RUBRO</th>
                    <th class="py-2">MARCA</th>
                    <th class="py-2">PROVEEDOR</th>
                    <th class="text-end py-2">COSTO FINAL</th>
                    <th class="text-end py-2">PRECIO L1</th>
                    <th class="text-end py-2 pe-3">STOCK</th>
                </tr>
            </thead>
            <tbody id="tbodyProductos" class="text-white-50">
                <tr>
                    <td colspan="10" class="text-center p-5 text-muted">
                        <i class="fa-solid fa-sync fs-3 mb-3"></i><br>
                        Utilice los filtros para consultar el historial.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="erp-cards-wrap" id="tblCards"></div>

    <div class="erp-pagination mt-3" id="pagBotWrap" style="display:none;">
        <div class="erp-pag-info" id="pagInfo">—</div>
        <div class="erp-pag-controls">
            <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
            <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
            <span id="pagBotones" class="d-flex gap-1"></span>
            <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
            <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
        </div>
    </div>
</div>

<script>
    (function () {
        let currentData = [];
        let pagActual = 1, pagTotal = 1, pagTam = 20;

        /**
         * Cargar Combos
         */
        async function loadCombos() {
            try {
                const resp = await fetch('api_productos_hist.php?action=get_combos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                if (res.success) {
                    const populate = (id, data) => {
                        let opts = '<option value="">-- SELECCIONAR PARA FILTRAR --</option>';
                        data.forEach(item => {
                            const text = item.descripcion || item.rubro || item.nombre || item.marca || item.razon;
                            opts += `<option value="${item.id}">${text}</option>`;
                        });
                        $(`#${id}`).html(opts);
                    };
                    const populate2 = (id, data) => {
                        let opts = '<option value="">-- SELECCIONAR PARA FILTRAR --</option>';
                        data.forEach(item => {
                            const text = item.descripcion || item.rubro || item.nombre || item.marca || item.razon;
                            opts += `<option value="${text}">${text}</option>`;
                        });
                        $(`#${id}`).html(opts);
                    };
                    populate2('cmbRubro', res.data.rubros);
                    populate('cmbFamilia', res.data.familias);
                    populate('cmbMarca', res.data.marcas);
                    populate('cmbProveedor', res.data.proveedores);

                    $('.select2-filter').select2({
                        theme: 'default',
                        dropdownParent: $('#main-content'),
                        language: 'es',
                        width: '100%'
                    });
                }
            } catch (err) { console.error("Error combos:", err); }
        }

        async function performSearch(action = 'search') {
            const fDesde = $('#dtmDesde').val();
            const fHasta = $('#dtmHasta').val();

            if ((fDesde && !fHasta) || (!fDesde && fHasta)) {
                Swal.fire('Atención', 'Para filtrar por fecha debe completar tanto DESDE como HASTA.', 'warning');
                return;
            }

            try {
                $('#loading-overlay').show();
                const formData = new FormData(document.getElementById('frmFiltros'));

                if ($('#cmbRubro').val()) formData.append('usar_rubro', 'on');
                if ($('#cmbFamilia').val()) formData.append('usar_familia', 'on');
                if ($('#cmbMarca').val()) formData.append('usar_marca', 'on');
                if ($('#cmbProveedor').val()) formData.append('usar_proveedor', 'on');
                if (fDesde && fHasta) formData.append('usar_fechas', 'on');

                const resp = await fetch(`api_productos_hist.php?action=${action}`, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();

                if (res.success) {
                    if (action === 'export') {
                        const byteCharacters = atob(res.xls_base64);
                        const byteNumbers = new Array(byteCharacters.length);
                        for (let i = 0; i < byteCharacters.length; i++) { byteNumbers[i] = byteCharacters.charCodeAt(i); }
                        const byteArray = new Uint8Array(byteNumbers);
                        const blob = new Blob([byteArray], { type: 'application/vnd.ms-excel' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url; a.download = res.filename;
                        document.body.appendChild(a); a.click(); a.remove();
                    } else {
                        currentData = res.data || [];
                        pagActual = 1;
                        renderPagina();
                    }
                } else { Swal.fire('Error', res.error, 'error'); }
            } catch (err) { 
                console.error(err); 
                Swal.fire('Error', 'Error de conexión', 'error');
            } finally { $('#loading-overlay').hide(); }
        }

        function renderPagina() {
            pagTotal = Math.max(1, Math.ceil(currentData.length / pagTam));
            const inicio = (pagActual - 1) * pagTam;
            const segmento = currentData.slice(inicio, inicio + pagTam);
            
            renderTable(segmento);
            renderCards(segmento);
            sincronizarPaginacion(currentData.length);
        }

        function renderTable(data) {
            const tbody = $('#tbodyProductos');
            tbody.empty();
            if (data.length === 0) {
                tbody.html('<tr><td colspan="10" class="text-center p-5 text-warning">No se encontraron registros.</td></tr>');
                return;
            }
            data.forEach(p => {
                tbody.append(`
                    <tr class="border-secondary align-middle">
                        <td class="ps-3 text-accent font-monospace">${p['Fecha Modificacion'] || '-'}</td>
                        <td class="text-secondary">${p['Usuario Modifcicacion'] || '-'}</td>
                        <td class="fw-bold text-white">${p.Codigo}</td>
                        <td class="text-truncate" style="max-width: 250px;">${p.Producto}</td>
                        <td>${p.Rubro || '-'}</td>
                        <td>${p.Marca || '-'}</td>
                        <td class="text-truncate" style="max-width: 150px;">${p.Proveedor || '-'}</td>
                        <td class="text-end font-monospace text-info">${p['Costo Final']}</td>
                        <td class="text-end font-monospace text-success">${p['Lista 1 Final']}</td>
                        <td class="text-end font-monospace pe-3">${p.Stock}</td>
                    </tr>
                `);
            });
        }

        function renderCards(data) {
            const wrap = $('#tblCards');
            wrap.empty();
            data.forEach(p => {
                wrap.append(`
                    <div class="erp-card mb-2">
                        <div class="erp-card-header">
                            <div class="erp-card-title text-accent">${p.Producto}</div>
                            <span class="erp-card-code text-info">#${p.Codigo}</span>
                        </div>
                        <div class="erp-card-row"><span class="erp-card-label">FECHA MODIF.</span><span class="erp-card-value text-accent">${p['Fecha Modificacion']}</span></div>
                        <div class="erp-card-row"><span class="erp-card-label">USUARIO</span><span class="erp-card-value">${p['Usuario Modifcicacion']}</span></div>
                        <div class="erp-card-row"><span class="erp-card-label">RUBRO/MARCA</span><span class="erp-card-value">${p.Rubro || '-'} / ${p.Marca || '-'}</span></div>
                        <div class="erp-card-row"><span class="erp-card-label">COSTO FINAL</span><span class="erp-card-value text-info font-monospace">${p['Costo Final']}</span></div>
                        <div class="erp-card-row"><span class="erp-card-label">PRECIO L1</span><span class="erp-card-value text-success font-monospace">${p['Lista 1 Final']}</span></div>
                        <div class="erp-card-row"><span class="erp-card-label">STOCK</span><span class="erp-card-value font-monospace">${p.Stock}</span></div>
                    </div>
                `);
            });
        }

        function sincronizarPaginacion(total) {
            const desde = Math.min((pagActual - 1) * pagTam + 1, total);
            const hasta = Math.min(pagActual * pagTam, total);
            const info = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong>`;
            
            ['', 'Top'].forEach(sfx => {
                $(`#pagInfo${sfx}`).html(info);
                $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
                $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

                const cont = $(`#pagBotones${sfx}`).empty();
                for (let i = Math.max(1, pagActual - 2); i <= Math.min(pagTotal, Math.max(1, pagActual - 2) + 4); i++) {
                    const b = $('<button>').addClass('erp-pag-btn').text(i).click(() => pagGo(i));
                    if (i === pagActual) b.addClass('active');
                    cont.append(b);
                }
                $(`#pag${sfx}Wrap`).toggle(total > 0);
            });
        }

        window.pagGo = (n) => { pagActual = Math.max(1, Math.min(pagTotal, n)); renderPagina(); };
        window.pagCambiarTamanoTop = () => { pagTam = parseInt($('#pagTamanoTop').val()); pagActual = 1; renderPagina(); };

        $('#frmFiltros').on('submit', (e) => { e.preventDefault(); performSearch('search'); });
        $('#btnExportar').on('click', () => performSearch('export'));
        loadCombos();
    })();
</script>