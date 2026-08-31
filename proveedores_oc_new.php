<?php
/**
 * proveedores_oc_new.php - MÓDULO DE PEDIDOS/ÓRDENES DE COMPRA
 * Actualizado: Paginación en memoria (SPA) dual y visible permanentemente.
 */
require_once 'check_access.php';

// Capturamos parámetros de URL de forma segura
$id_edit = $_GET['id'] ?? '';
$is_view = isset($_GET['view']) && ($_GET['view'] === 'true' || $_GET['edit'] === 'false');
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0" id="title-oc">
                <?= $is_view ? 'ORDEN DE COMPRA (SOLO LECTURA)' : ($id_edit ? 'EDITAR ORDEN DE COMPRA' : 'NUEVA ORDEN DE COMPRA') ?>
            </h4>
            <small class="text-accent opacity-75">Gestión de abastecimiento y pedidos automáticos</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success btn-sm rounded-pill" id="btn-export-excel">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <?php if (!$is_view): ?>
                <button class="btn btn-info btn-sm fw-bold px-4 rounded-pill shadow" id="btn-grabar-oc" disabled>
                    <i class="fas fa-save me-1"></i> <span class="d-none d-sm-inline">GRABAR PEDIDO</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-3 text-start">
        <!-- PANEL DE CONTROL -->
        <div class="col-md-12" id="panel-control-oc">
            <div class="card card-filtros shadow-sm">
                <div class="card-body p-3">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label-custom">PROVEEDOR</label>
                            <select id="cmb-proveedor-oc" class="form-control" <?= $is_view ? 'disabled' : '' ?>></select>
                        </div>
                        
                        <?php if (!$is_view): ?>
                        <div class="col-12 col-md-4 col-lg-3">
                            <label class="form-label-custom">MODO DE GENERACIÓN</label>
                            <div class="d-flex gap-3 bg-dark p-2 rounded border border-secondary flex-wrap">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gen-mode" id="rb-max" value="maximo" checked>
                                    <label class="form-check-label text-white small" for="rb-max">Bajo Ideal</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="gen-mode" id="rb-min" value="minimo">
                                    <label class="form-check-label text-white small" for="rb-min">Bajo Mínimo</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-12 col-md-4 col-lg-2">
                            <button class="btn btn-warning w-100 fw-bold shadow-sm" id="btn-generar-auto">
                                <i class="fas fa-magic me-2"></i>GENERAR
                            </button>
                        </div>
                        <div class="col-12 col-lg-3 border-lg-start border-secondary ps-lg-3 mt-3 mt-lg-0">
                            <label class="form-label-custom">BUSCAR PRODUCTO (Lupa)</label>
                            <select id="cmb-buscar-producto" class="form-control"></select>
                        </div>
                        <?php else: ?>
                        <div class="col-12 col-md-8 text-end">
                            <div class="alert alert-info py-2 mb-0 border-info bg-info bg-opacity-10 small">
                                <i class="fas fa-info-circle me-2"></i>Esta Orden de Compra está cerrada. No se admiten modificaciones.
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================
             PAGINACIÓN SUPERIOR
        ============================= -->
        <div class="col-12">
            <div class="erp-pagination mb-2" id="pagTopWrap" style="display:none;">
                <div class="erp-pag-info" id="pagInfoTop">—</div>
                <div class="erp-pag-controls">
                    <div class="erp-pag-jump me-2">
                        <span>IR A</span><input type="number" id="pagJumpInputTop" min="1" value="1">
                        <button class="erp-pag-btn" onclick="pagJumpTop()">IR</button>
                    </div>
                    <button class="erp-pag-btn" id="btnPagPrimTop" onclick="pagGo(1)">«</button>
                    <button class="erp-pag-btn" id="btnPagAntTop"  onclick="pagGo(pagActual-1)">‹</button>
                    <span id="pagBotonesTop" class="d-flex gap-1"></span>
                    <button class="erp-pag-btn" id="btnPagSigTop"  onclick="pagGo(pagActual+1)">›</button>
                    <button class="erp-pag-btn" id="btnPagUltTop"  onclick="pagGo(pagTotal)">»</button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="erp-pag-info mb-0" for="pagTamanoTop">POR PÁGINA:</label>
                    <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                        <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option>
                    </select>
                </div>
            </div>

            <!-- ============================
                 GRILLA (Desktop)
            ============================= -->
            <div class="erp-table-wrap card card-filtros p-0 overflow-hidden shadow-sm border-0 mb-2">
                <div class="table-responsive" style="max-height: 55vh; min-height: 300px;">
                    <table class="table table-dark table-hover align-middle mb-0 small" id="tabla-oc">
                        <thead class="sticky-top">
                            <tr>
                                <th class="ps-3">CÓDIGO</th>
                                <th>COD. PROV.</th>
                                <th>DESCRIPCIÓN</th>
                                <th class="text-center">ST. ACT</th>
                                <th class="text-center">ST. IDEAL</th>
                                <th class="text-center" style="width: 110px;">CANTIDAD</th>
                                <th class="text-end">COSTO</th>
                                <th class="text-end">SUBTOTAL</th>
                                <th class="text-center">X</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-oc">
                            <tr><td colspan="9" class="text-center p-5 text-muted italic">Seleccione un proveedor y genere el pedido</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ============================
                 TARJETAS (Mobile)
            ============================= -->
            <div class="erp-cards-wrap" id="tblCards">
                <div class="text-center text-muted p-5 opacity-50 italic">Seleccione un proveedor y genere el pedido</div>
            </div>

            <!-- ============================
                 PAGINACIÓN INFERIOR
            ============================= -->
            <div class="erp-pagination mb-2" id="pagBotWrap" style="display:none;">
                <div class="erp-pag-info" id="pagInfo">—</div>
                <div class="erp-pag-controls">
                    <div class="erp-pag-jump me-2">
                        <span>IR A</span><input type="number" id="pagJumpInput" min="1" value="1">
                        <button class="erp-pag-btn" onclick="pagJump()">IR</button>
                    </div>
                    <button class="erp-pag-btn" id="btnPagPrim" onclick="pagGo(1)">«</button>
                    <button class="erp-pag-btn" id="btnPagAnt"  onclick="pagGo(pagActual-1)">‹</button>
                    <span id="pagBotones" class="d-flex gap-1"></span>
                    <button class="erp-pag-btn" id="btnPagSig"  onclick="pagGo(pagActual+1)">›</button>
                    <button class="erp-pag-btn" id="btnPagUlt"  onclick="pagGo(pagTotal)">»</button>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="erp-pag-info mb-0" for="pagTamano">POR PÁGINA:</label>
                    <select id="pagTamano" class="erp-pag-select" onchange="pagCambiarTamano()">
                        <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="500">500</option>
                    </select>
                </div>
            </div>

            <!-- FOOTER TOTALES -->
            <div class="card card-filtros bg-dark border-secondary p-3 mt-2 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <div class="text-muted small">Items en pedido: <b class="text-white" id="lbl-cant-items">0</b></div>
                <div class="d-flex align-items-center gap-2 flex-wrap justify-content-center">
                    <span class="text-accent fw-bold small">VALOR TOTAL ESTIMADO:</span>
                    <span class="fs-4 fw-bold text-white" id="lbl-total-estimado">$ 0.00</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let items = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    
    const idEdit = "<?= $id_edit ?>";
    const isView = <?= $is_view ? 'true' : 'false' ?>;
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        configurarSelect2();
        if (!isView) {
            configurarBuscadorProductos();
            $('#btn-generar-auto').click(generarAutomatico);
            $('#btn-grabar-oc').click(grabarPedido);
            
            // Delegación de eventos para los inputs de cantidad (Funciona en tabla y tarjetas)
            $(document).off('change', '.row-cant').on('change', '.row-cant', function() {
                const idx = $(this).data('idx');
                const val = parseFloat($(this).val()) || 0;
                items[idx].cantidad = val;
                items[idx].subtotal = val * items[idx].costo;
                renderPagina(); // Re-renderiza para actualizar subtotales
            });
        }
        $('#btn-export-excel').click(exportarPersonalizado);

        if (idEdit) cargarPedidoExistente();
    }

    function configurarSelect2() {
        $('#cmb-proveedor-oc').select2({
            placeholder: 'BUSCAR PROVEEDOR...',
            dropdownParent: $('#main-content'),
            ajax: {
                url: 'api_pagos_proveedores.php?action=search_proveedores',
                dataType: 'json', delay: 250, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        });
    }

    function configurarBuscadorProductos() {
        $('#cmb-buscar-producto').select2({
            placeholder: 'BUSCAR POR NOMBRE O CÓDIGO...',
            dropdownParent: $('#main-content'),
            minimumInputLength: 2,
            ajax: {
                url: 'api_proveedores_oc.php?action=search_products',
                dataType: 'json', delay: 300, headers: commonHeaders,
                data: p => ({ q: p.term }),
                processResults: d => ({ results: d.data })
            },
            width: '100%'
        }).on('select2:select', function(e) {
            const prodData = e.params.data;
            procesarSeleccionManual(prodData.id, prodData.proveedorID);
            $(this).val(null).trigger('change');
        });
    }

    async function procesarSeleccionManual(codigo, prodProvID) {
        const provActual = $('#cmb-proveedor-oc').val();
        if (!provActual) return Swal.fire('Atención', 'Debe seleccionar un proveedor primero.', 'warning');

        if (prodProvID != provActual) {
            const confirm = await Swal.fire({
                title: 'Proveedor Diferente',
                text: 'El producto no pertenece al proveedor de la orden. ¿Agregar de todas formas?',
                icon: 'question', showCancelButton: true,
                confirmButtonText: 'SÍ', cancelButtonText: 'NO'
            });
            if (!confirm.isConfirmed) return;
        }
        agregarProductoPorCodigo(codigo);
    }

    async function cargarPedidoExistente() {
        try {
            const res = await fetch(`api_proveedores_oc.php?action=get_order&id=${idEdit}`, { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                $('#cmb-proveedor-oc').append(new Option(res.cab.proveedor_nombre, res.cab.id_proveedor, true, true)).trigger('change');
                items = res.items;
                pagActual = 1; window.pagActual = 1;
                renderPagina();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (e) { console.error(e); }
    }

    async function generarAutomatico() {
        const idProv = $('#cmb-proveedor-oc').val();
        if (!idProv) return Swal.fire('Atención', 'Seleccione un proveedor.', 'warning');
        if (items.length > 0 && !await Swal.fire({ title: '¿Sobrescribir?', text: 'Se reemplazará el detalle actual.', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) return;

        const mode = $('input[name="gen-mode"]:checked').val();
        
        $('#tbody-oc').html('<tr><td colspan="9" class="text-center p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> GENERANDO...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5"><div class="spinner-border text-info spinner-border-sm me-2"></div> GENERANDO...</div>');

        const resp = await fetch('api_proveedores_oc.php?action=generate_auto', {
            method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
            body: JSON.stringify({ proveedor_id: idProv, filtro: mode })
        }).then(r => r.json());

        if (resp.success) {
            items = resp.data;
            if(items.length === 0) Swal.fire('Sin resultados', 'No hay faltantes de stock.', 'info');
            pagActual = 1; window.pagActual = 1;
            renderPagina();
        }
    }

    async function agregarProductoPorCodigo(codigo) {
        if (items.find(i => i.codigo === codigo)) return Swal.fire('Info', 'Producto ya en lista.', 'info');
        const res = await fetch(`api_proveedores_oc.php?action=get_product&codigo=${codigo}`, { headers: commonHeaders }).then(r => r.json());
        if (res.success) { 
            items.push(res.data); 
            // Si hay muchos, vamos a la última página para ver lo que agregamos
            pagTotal = Math.max(1, Math.ceil(items.length / pagTam));
            pagActual = pagTotal; window.pagActual = pagActual;
            renderPagina(); 
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = items.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = items.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
        
        // El total general aplica a TODO el array `items`, no solo a la página
        let totalGeneral = 0;
        items.forEach(i => totalGeneral += i.subtotal);
        actualizarUI(total, totalGeneral);
    }

    function renderTabla(datos) {
        const tbody = $('#tbody-oc').empty();

        if (datos.length === 0) {
            tbody.append('<tr><td colspan="9" class="text-center p-5 text-muted opacity-50 italic">Sin productos cargados</td></tr>');
            return;
        }

        datos.forEach((item, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            tbody.append(`
                <tr>
                    <td class="ps-3 fw-bold text-accent">${item.codigo}</td>
                    <td>${item.cod_prov || '-'}</td>
                    <td><div class="text-truncate" style="max-width:300px">${item.descripcion}</div></td>
                    <td class="text-center text-muted small">${item.stock_actual}</td>
                    <td class="text-center text-muted small">${item.stock_ideal}</td>
                    <td class="text-center">
                        <input type="number" class="form-control form-control-sm text-center fw-bold border-warning bg-warning bg-opacity-10 text-white row-cant" 
                               data-idx="${absoluteIdx}" value="${item.cantidad}" step="0.01" ${isView ? 'readonly' : ''}>
                    </td>
                    <td class="text-end">$ ${parseFloat(item.costo).toFixed(2)}</td>
                    <td class="text-end fw-bold text-white">$ ${parseFloat(item.subtotal).toFixed(2)}</td>
                    <td class="text-center">
                        ${!isView ? `<button class="btn btn-link text-danger p-0" onclick="window.delItemOC(${absoluteIdx})"><i class="fas fa-trash-alt"></i></button>` : '-'}
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(datos) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        if (!datos.length) {
            wrap.innerHTML = '<div class="text-center text-muted p-5 opacity-50 italic">Sin productos cargados</div>';
            return;
        }

        datos.forEach((item, idx) => {
            const absoluteIdx = (pagActual - 1) * pagTam + idx;
            let html = `
            <div class="erp-card">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 200px;">${item.descripcion}</div>
                        <small class="text-muted">Cod Prov: ${item.cod_prov || '-'}</small>
                    </div>
                    <span class="erp-card-code">${item.codigo}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Stock Actual / Ideal</span>
                    <span class="erp-card-value">${item.stock_actual} / ${item.stock_ideal}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label text-warning">Cantidad</span>
                    <input type="number" class="form-control form-control-sm text-center fw-bold border-warning bg-warning bg-opacity-10 text-white row-cant" 
                           data-idx="${absoluteIdx}" value="${item.cantidad}" step="0.01" style="width: 80px;" ${isView ? 'readonly' : ''}>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Costo</span>
                    <span class="erp-card-value">$ ${parseFloat(item.costo).toFixed(2)}</span>
                </div>
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Subtotal</span>
                    <span class="erp-card-value price fs-6">$ ${parseFloat(item.subtotal).toFixed(2)}</span>
                </div>`;
            
            if (!isView) {
                html += `
                <div class="erp-card-actions mt-2">
                    <button class="btn btn-outline-danger btn-sm w-100 fw-bold" onclick="window.delItemOC(${absoluteIdx})">
                        <i class="fas fa-trash-alt me-1"></i> QUITAR
                    </button>
                </div>`;
            }

            html += `</div>`;
            wrap.innerHTML += html;
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> items`;
        
        ['','Top'].forEach(sfx => {
            var el = document.getElementById('pagInfo'+sfx); if(el) el.innerHTML = info;
            ['btnPagPrim','btnPagAnt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual<=1; });
            ['btnPagSig','btnPagUlt'].forEach(id => { var e=document.getElementById(id+sfx); if(e) e.disabled=pagActual>=pagTotal; });
            var cont = document.getElementById('pagBotones'+sfx);
            if(cont){ 
                cont.innerHTML=''; 
                if(pagTotal>1){ 
                    var ini=Math.max(1,pagActual-2), fin=Math.min(pagTotal,ini+4); 
                    if(fin-ini<4) ini=Math.max(1,fin-4); 
                    for(var i=ini; i<=fin; i++){
                        var b = document.createElement('button');
                        b.className = 'erp-pag-btn' + (i===pagActual?' active':'');
                        b.textContent = i;
                        b.onclick = (function(p){return function(){pagGo(p);};})(i);
                        cont.appendChild(b);
                    } 
                } 
            }
            var ji=document.getElementById('pagJumpInput'+sfx); if(ji){ji.value=pagActual;ji.max=pagTotal;}
            var sel=document.getElementById('pagTamano'+sfx); if(sel) sel.value=pagTam;
            var wrap=document.getElementById(sfx==='Top'?'pagTopWrap':'pagBotWrap'); 
            // MEJORA APLICADA: Siempre se muestra en formato flex si hay más de 0 ítems
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function actualizarUI(cant, total) {
        $('#lbl-cant-items').text(cant);
        $('#lbl-total-estimado').text('$ ' + total.toLocaleString('es-AR', {minimumFractionDigits: 2}));
        if(!isView) $('#btn-grabar-oc').prop('disabled', cant === 0);
    }

    window.delItemOC = (absoluteIdx) => { 
        items.splice(absoluteIdx, 1); 
        
        // Si borramos el último ítem de una página y la página queda vacía, retrocedemos
        if (items.length > 0 && (pagActual - 1) * pagTam >= items.length) {
            pagActual--;
        }
        
        renderPagina(); 
    };

    async function grabarPedido() {
        if (isView) return;
        const total = parseFloat($('#lbl-total-estimado').text().replace('$ ', '').replace(/\./g, '').replace(',', '.'));
        const data = { id_pedido: idEdit, edit: !!idEdit, proveedor_id: $('#cmb-proveedor-oc').val(), total: total, items: items };

        if (await Swal.fire({ title: '¿Grabar?', icon: 'question', showCancelButton: true }).then(r => r.isConfirmed)) {
            const res = await fetch('api_proveedores_oc.php?action=save', {
                method: 'POST', headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                await Swal.fire('Éxito', `Orden Nº ${res.id} guardada.`, 'success');
                loadModule('proveedores_oc_admin.php');
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        }
    }

    function exportarPersonalizado() {
        if (items.length === 0) return;
        let csv = "\xEF\xBB\xBF";
        csv += "Código;Cód. Proveedor;Cantidad;Descripción;Precio Unit.;Importe Total\n";
        items.forEach(i => { csv += `${i.codigo};${i.cod_prov};${i.cantidad};${i.descripcion};${i.costo.toFixed(2)};${i.subtotal.toFixed(2)}\n`; });
        csv += `\n;;;;TOTAL ESTIMADO:;${$('#lbl-total-estimado').text()}`;
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement("a");
        link.setAttribute("href", URL.createObjectURL(blob));
        link.setAttribute("download", `Orden_Compra_${$('#cmb-proveedor-oc option:selected').text().replace(/ /g, '_')}.csv`);
        link.click();
    }

    init();
})();
</script>