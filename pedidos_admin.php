<?php
/**
 * pedidos_admin.php - ADMINISTRACIÓN DE PEDIDOS DE VENTA
 * Actualizado: Paginación en memoria (SPA) dual y diseño responsivo con Tarjetas Mobile.
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$hoy = date('Y-m-d');
$mes_anterior = date('Y-m-d', strtotime('-1 month'));
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloPedidosAdmin">
    <!-- Encabezado -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">ADMINISTRACIÓN DE PEDIDOS</h4>
            <small class="text-accent opacity-75">Gestión, seguimiento y facturación de pedidos</small>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-outline-success border-0 rounded-pill px-3 btn-sm fw-bold" onclick="exportarExcel()">
                <i class="fas fa-file-excel me-1"></i> <span class="d-none d-sm-inline">EXPORTAR</span>
            </button>
            <button class="btn btn-info fw-bold rounded-pill px-4 shadow btn-sm" onclick="loadModule('pedidos_nuevo.php')">
                <i class="fas fa-plus me-1"></i> <span class="d-none d-sm-inline">NUEVO PEDIDO</span><span class="d-inline d-sm-none">NUEVO</span>
            </button>
        </div>
    </div>

    <!-- Panel de Filtros -->
    <div class="card card-filtros p-3 mb-3 shadow-sm border-0 text-start">
        <div class="row g-3 align-items-center">
            <div class="col-6 col-md-3">
                <label class="form-label-custom">DESDE</label>
                <input type="date" id="dtmDesde" class="form-control form-control-sm filter-auto" value="<?php echo $mes_anterior; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label-custom">HASTA</label>
                <input type="date" id="dtmHasta" class="form-control form-control-sm filter-auto" value="<?php echo $hoy; ?>">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label-custom">FILTRAR POR FECHA DE</label>
                <div class="d-flex gap-3 mt-1 bg-dark p-1 rounded border border-secondary justify-content-center">
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="tipoFecha" id="optEntrega" value="entrega" checked>
                        <label class="form-check-label text-white small" for="optEntrega">Entrega</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="tipoFecha" id="optCarga" value="carga">
                        <label class="form-check-label text-white small" for="optCarga">Carga</label>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-2 text-md-end">
                <button class="btn btn-secondary w-100 fw-bold btn-sm h-100" onclick="buscarPedidos()">
                    <i class="fas fa-search me-1"></i> BUSCAR
                </button>
            </div>
        </div>

        <div class="row g-3 mt-1 align-items-end">
            <div class="col-12 col-lg-5">
                <label class="form-label-custom">ESTADO DEL PEDIDO</label>
                <div class="d-flex gap-3 mt-1 p-2 border border-secondary rounded bg-dark bg-opacity-25 flex-wrap justify-content-center">
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="filtroEstado" id="optPendientes" value="0" checked>
                        <label class="form-check-label text-warning small fw-bold" for="optPendientes">Pendientes</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="filtroEstado" id="optFacturados" value="1">
                        <label class="form-check-label text-success small fw-bold" for="optFacturados">Facturados</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="filtroEstado" id="optAnulados" value="2">
                        <label class="form-check-label text-danger small fw-bold" for="optAnulados">Anulados</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input filter-auto" type="radio" name="filtroEstado" id="optTodos" value="todos">
                        <label class="form-check-label text-white small" for="optTodos">Todos</label>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-6 col-lg-4">
                <label class="form-label-custom">BUSCAR (CLIENTE / N° PEDIDO)</label>
                <input type="text" id="txtBusqueda" class="form-control form-control-sm" placeholder="Escriba para filtrar...">
            </div>
            
            <div class="col-12 col-md-6 col-lg-3">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" id="chkVendedor">
                    <label class="form-check-label text-white small" for="chkVendedor">Por Vendedor</label>
                </div>
                <select id="cmbVendedorAdmin" class="form-select select2-filter" disabled></select>
            </div>
        </div>
    </div>

    <!-- ============================
         PAGINACIÓN SUPERIOR
    ============================= -->
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- ============================
         GRILLA (Desktop)
    ============================= -->
    <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded mb-2" style="max-height: 60vh; background-color: #000a1a; overflow-y: auto;">
        <table class="table table-dark table-hover table-sm mb-0 small align-middle" id="gridPedidos">
            <thead class="sticky-top bg-black border-bottom border-secondary">
                <tr>
                    <th class="ps-3 py-2 text-center" style="width: 90px;">F. CARGA</th>
                    <th class="py-2 text-center" style="width: 80px;">N° PED</th>
                    <th class="py-2">CLIENTE</th>
                    <th class="py-2 text-center" style="width: 90px;">F. ENTREGA</th>
                    <th class="py-2 text-center" style="width: 60px;">CANT.</th>
                    <th class="py-2">OBSERVACIONES</th>
                    <th class="py-2">TRANSPORTE</th>
                    <th class="py-2">VENDEDOR</th>
                    <th class="py-2 text-center">ESTADO</th>
                    <th class="py-2 text-center">IMPRESO</th>
                    <th class="py-2 text-center" style="width: 140px;">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyPedidos">
                <tr><td colspan="11" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Actualizando resultados...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- ============================
         TARJETAS (Mobile)
    ============================= -->
    <div class="erp-cards-wrap" id="tblCards">
        <div class="text-center text-muted p-5 opacity-50 italic"><i class="fas fa-spinner fa-spin me-2"></i>Actualizando resultados...</div>
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
                <option value="20" selected>20</option><option value="50">50</option><option value="100">100</option><option value="200">200</option><option value="500">500</option>
            </select>
        </div>
    </div>

    <!-- TOTALES FOOTER (RESPONSIVO) -->
    <div class="card card-filtros bg-dark border-secondary p-3 mb-4 mt-2 shadow-sm d-none" id="panel-totales">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 text-center text-md-start">
            <div>
                <small class="text-muted text-uppercase d-block" style="font-size: 10px;">Total Pedidos Listados</small>
                <span id="lblCantListados" class="fs-5 fw-bold text-white">0</span>
            </div>
            <div class="border-start border-secondary ps-md-4">
                <small class="text-accent text-uppercase fw-bold d-block" style="font-size: 10px;">Total Prod. Activos</small>
                <span id="lblCantTotal" class="fs-4 fw-bold text-accent">0</span>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let timeoutBusqueda = null;
    let listadoPedidos = [];
    let pagActual = 1, pagTotal = 1, pagTam = 20;
    
    window.pagActual = 1; window.pagTotal = 1;

    async function init() {
        const h = {'X-Requested-With': 'XMLHttpRequest'};
        const cmbV = $('#cmbVendedorAdmin');

        try {
            // 1. Obtener datos de vendedores
            const resVend = await fetch('api_helpers.php?action=get_vendedores', { headers: h }).then(r => r.json());
            
            cmbV.empty().append(new Option('SELECCIONE VENDEDOR...', '', true, true));
            if (resVend.success && resVend.data) {
                resVend.data.forEach(v => {
                    const texto = v.text || v.nombre || 'SIN NOMBRE';
                    cmbV.append(new Option(texto.toUpperCase(), v.id, false, false));
                });
            }

            // 2. Inicializar Select2
            cmbV.select2({
                dropdownParent: $('#main-content'),
                width: '100%',
                theme: 'default',
                language: 'es'
            });

            // --- Listeners para Filtrado Automático ---
            $('.filter-auto').on('change', function() { buscarPedidos(); });

            $('#txtBusqueda').on('input', function() {
                clearTimeout(timeoutBusqueda);
                timeoutBusqueda = setTimeout(() => { buscarPedidos(); }, 400);
            });

            $('#chkVendedor').on('change', function() {
                const checked = $(this).is(':checked');
                cmbV.prop('disabled', !checked);
                if(!checked) cmbV.val('').trigger('change.select2');
                buscarPedidos();
            });

            cmbV.on('change', function() {
                if($('#chkVendedor').is(':checked')) buscarPedidos();
            });

            // Carga inicial
            buscarPedidos();

        } catch (e) {
            console.error("Error inicializando módulo pedidos:", e);
        }
    }

    window.buscarPedidos = async function() {
        const payload = {
            desde: $('#dtmDesde').val(),
            hasta: $('#dtmHasta').val(),
            tipo_fecha: $('input[name="tipoFecha"]:checked').val(),
            estado: $('input[name="filtroEstado"]:checked').val(),
            busqueda: $('#txtBusqueda').val(),
            vendedor_id: $('#chkVendedor').is(':checked') ? $('#cmbVendedorAdmin').val() : 0
        };

        $('#tbodyPedidos').html('<tr><td colspan="11" class="text-center py-5 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Actualizando resultados...</td></tr>');
        $('#tblCards').html('<div class="text-center text-muted p-5 opacity-50"><i class="fas fa-spinner fa-spin me-2"></i>Actualizando...</div>');
        $('#panel-totales').addClass('d-none');

        try {
            const res = await fetch('api_pedidos_admin.php?action=list', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(payload)
            }).then(r => r.json());

            if (res.success) {
                listadoPedidos = res.data || [];
                
                // Calcular total general de productos de pedidos activos
                let sumCantProd = 0;
                listadoPedidos.forEach(r => {
                    if (r.estadofac != 2) sumCantProd += parseInt(r.cantidad_productos || 0);
                });
                $('#lblCantListados').text(listadoPedidos.length);
                $('#lblCantTotal').text(sumCantProd);
                if(listadoPedidos.length > 0) $('#panel-totales').removeClass('d-none');

                pagActual = 1; window.pagActual = 1;
                renderPagina();
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: res.error, background: '#200', color: '#fff' });
            }
        } catch (e) {
            console.error(e);
            $('#tbodyPedidos').html('<tr><td colspan="11" class="text-center py-5 text-danger">Error de comunicación con el servidor.</td></tr>');
            $('#tblCards').html('<div class="text-center text-danger p-5">Error de comunicación con el servidor.</div>');
        }
    }

    /* ============================================================
       RENDERIZADO SPA & PAGINACIÓN
    ============================================================ */
    function renderPagina() {
        const total = listadoPedidos.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;
        window.pagActual = pagActual; window.pagTotal = pagTotal;

        const datos = listadoPedidos.slice((pagActual - 1) * pagTam, pagActual * pagTam);

        renderTabla(datos);
        renderTarjetas(datos);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tb = $('#tbodyPedidos').empty();
        
        if (!data || data.length === 0) {
            tb.html('<tr><td colspan="11" class="text-center py-5 text-muted italic">No se encontraron comprobantes.</td></tr>');
            return;
        }

        data.forEach(r => {
            let badge = '';
            if (r.estadofac == 0) badge = '<span class="badge bg-warning text-dark" style="font-size:9px">PENDIENTE</span>';
            else if (r.estadofac == 1) badge = '<span class="badge bg-success" style="font-size:9px">FACTURADO</span>';
            else if (r.estadofac == 2) badge = '<span class="badge bg-danger" style="font-size:9px">ANULADO</span>';

            const fechaCarga = r.fecha_carga ? formatDate(r.fecha_carga) : '-';
            const fechaEntrega = r.fecha_entrega ? formatDate(r.fecha_entrega) : '-';
            const fontWeight = (r.impreso == 0) ? 'fw-bold text-white' : 'text-white-50';
            const opacityClass = (r.estadofac == 2) ? 'opacity-50 text-decoration-line-through' : '';

            tb.append(`
                <tr class="${opacityClass} ${fontWeight}">
                    <td class="text-center ps-3">${fechaCarga}</td>
                    <td class="text-center text-info">#${r.numpedido}</td>
                    <td><div class="text-truncate" style="max-width: 200px;">${r.cliente}</div></td>
                    <td class="text-center text-warning">${fechaEntrega}</td>
                    <td class="text-center">${r.cantidad_productos || 0}</td>
                    <td><div class="text-truncate text-muted" style="max-width: 150px;">${r.observaciones || ''}</div></td>
                    <td class="small">${r.transporte || '-'}</td>
                    <td class="small text-accent">${r.vendedor || 'S/V'}</td>
                    <td class="text-center">${badge}</td>
                    <td class="text-center small">${r.impreso == 1 ? '<i class="fas fa-check text-success"></i>' : '-'}</td>
                    <td class="text-center">
                        <div class="btn-group shadow-sm">
                            <button class="btn btn-sm btn-info p-1 px-2" onclick="editarPedido(${r.numpedido}, ${r.estadofac})" title="Ver/Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-secondary p-1 px-2" onclick="imprimirPedido(${r.numpedido})" title="Imprimir">
                                <i class="fas fa-print"></i>
                            </button>
                            ${(r.estadofac != 1 && r.estadofac != 2) ? `
                            <button class="btn btn-sm btn-success p-1 px-2" onclick="loadModule('newfactura.php?pedidoid=${r.numpedido}')" title="Facturar">
                                <i class="fas fa-file-invoice-dollar"></i>
                            </button>` : ''}
                            ${r.estadofac == 0 ? `
                            <button class="btn btn-sm btn-danger p-1 px-2" onclick="anularPedido(${r.numpedido})" title="Anular">
                                <i class="fas fa-ban"></i>
                            </button>` : ''}
                            ${r.estadofac == 2 ? `
                            <button class="btn btn-sm btn-success p-1 px-2" onclick="restaurarPedido(${r.numpedido})" title="Restaurar">
                                <i class="fas fa-trash-restore"></i>
                            </button>` : ''}
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards').empty();

        if (!data || data.length === 0) {
            wrap.append('<div class="text-center text-muted p-5 opacity-50 italic">No se encontraron comprobantes.</div>');
            return;
        }

        data.forEach(r => {
            let estadoTxt = '';
            let badgeStyle = '';
            
            if (r.estadofac == 0) {
                estadoTxt = 'PENDIENTE';
                badgeStyle = 'background:rgba(255,193,7,.2);color:#ffc107;border:1px solid #ffc107;';
            } else if (r.estadofac == 1) {
                estadoTxt = 'FACTURADO';
                badgeStyle = 'background:rgba(40,167,69,.2);color:#28a745;border:1px solid #28a745;';
            } else if (r.estadofac == 2) {
                estadoTxt = 'ANULADO';
                badgeStyle = 'background:rgba(220,53,69,.2);color:#dc3545;border:1px solid #dc3545;';
            }

            const fechaCarga = r.fecha_carga ? formatDate(r.fecha_carga) : '-';
            const fechaEntrega = r.fecha_entrega ? formatDate(r.fecha_entrega) : '-';
            const opacityClass = (r.estadofac == 2) ? 'opacity-50 grayscale' : '';

            let html = `
            <div class="erp-card ${opacityClass}">
                <div class="erp-card-header">
                    <div>
                        <div class="erp-card-title text-truncate" style="max-width: 220px;">${r.cliente}</div>
                        <span class="erp-card-badge ms-1" style="${badgeStyle}">${estadoTxt}</span>
                    </div>
                    <span class="erp-card-code">#${r.numpedido}</span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Fechas (Carga / Entr)</span>
                    <span class="erp-card-value">${fechaCarga} <i class="fas fa-arrow-right mx-1 text-muted"></i> <span class="text-warning">${fechaEntrega}</span></span>
                </div>
                <div class="erp-card-row">
                    <span class="erp-card-label">Vendedor / Transp</span>
                    <span class="erp-card-value text-truncate" style="max-width: 60%;">${r.vendedor || 'S/V'} / ${r.transporte || '-'}</span>
                </div>
                ${r.observaciones ? `
                <div class="erp-card-row">
                    <span class="erp-card-label">Notas</span>
                    <span class="erp-card-value text-muted text-truncate" style="font-size: 11px; max-width: 70%;">${r.observaciones}</span>
                </div>` : ''}
                <div class="erp-card-row pt-2 mt-1 border-top" style="border-color: rgba(255,255,255,0.05) !important;">
                    <span class="erp-card-label">Cant. Productos</span>
                    <span class="erp-card-value price fs-6">${r.cantidad_productos || 0}</span>
                </div>

                <div class="erp-card-actions flex-wrap gap-1 mt-2">
                    <button class="btn btn-info btn-sm flex-fill fw-bold" onclick="editarPedido(${r.numpedido}, ${r.estadofac})">
                        <i class="fas fa-edit me-1"></i> ${r.estadofac == 1 ? 'VER' : 'EDITAR'}
                    </button>
                    <button class="btn btn-secondary btn-sm flex-fill fw-bold" onclick="imprimirPedido(${r.numpedido})">
                        <i class="fas fa-print me-1"></i> IMPRIMIR
                    </button>`;

            if (r.estadofac != 1 && r.estadofac != 2) {
                html += `
                    <button class="btn btn-success btn-sm flex-fill fw-bold" onclick="loadModule('newfactura.php?pedidoid=${r.numpedido}')">
                        <i class="fas fa-file-invoice-dollar me-1"></i> FACTURAR
                    </button>`;
            }
            if (r.estadofac == 0) {
                html += `
                    <button class="btn btn-outline-danger btn-sm flex-fill fw-bold" onclick="anularPedido(${r.numpedido})">
                        <i class="fas fa-ban me-1"></i> ANULAR
                    </button>`;
            }
            if (r.estadofac == 2) {
                html += `
                    <button class="btn btn-outline-success btn-sm flex-fill fw-bold" onclick="restaurarPedido(${r.numpedido})">
                        <i class="fas fa-trash-restore me-1"></i> RESTAURAR
                    </button>`;
            }

            html += `</div></div>`;
            wrap.append(html);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = Math.min((pagActual-1)*pagTam+1, total||1), hasta = Math.min(pagActual*pagTam, total);
        const info  = `Mostrando <strong>${desde}–${hasta}</strong> de <strong>${total}</strong> pedidos`;
        
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
            if(wrap) wrap.style.display = total > 0 ? 'flex' : 'none';
        });
    }

    window.pagGo = function(n){ n=Math.max(1,Math.min(pagTotal,n)); if(n===pagActual)return; pagActual=n; window.pagActual=n; renderPagina(); document.getElementById('main-content')?.scrollTo({top:0,behavior:'smooth'}); };
    window.pagJump = function(){ var v=parseInt(document.getElementById('pagJumpInput').value); if(!isNaN(v))pagGo(v); };
    window.pagJumpTop = function(){ var v=parseInt(document.getElementById('pagJumpInputTop').value); if(!isNaN(v))pagGo(v); };
    window.pagCambiarTamano = function(){ pagTam=parseInt(document.getElementById('pagTamano').value); pagActual=1; window.pagActual=1; renderPagina(); };
    window.pagCambiarTamanoTop = function(){ pagTam=parseInt(document.getElementById('pagTamanoTop').value); pagActual=1; window.pagActual=1; renderPagina(); };

    function formatDate(d) { 
        if(!d) return '-';
        const parts = d.split(' ')[0].split('-'); 
        return `${parts[2]}/${parts[1]}/${parts[0]}`; 
    }

    /* ============================================================
       ACCIONES DE REGISTRO
    ============================================================ */

    window.editarPedido = function(id, estado) {
        if (estado == 1) { 
            Swal.fire({ icon: 'info', title: 'Pedido Facturado', text: 'No se puede editar un pedido procesado.', background: '#001', color: '#fff' }); 
            return; 
        }
        loadModule('pedidos_nuevo.php?id=' + id);
    }

    window.anularPedido = async function(id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Anular Pedido?',
            text: `Se marcará el pedido #${id} como anulado.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'SÍ, ANULAR',
            cancelButtonText: 'CANCELAR'
        });

        if (isConfirmed) {
            try {
                const res = await fetch('api_pedidos_admin.php?action=annul', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ numpedido: id })
                }).then(r => r.json());

                if (res.success) buscarPedidos();
                else Swal.fire('Error', res.error, 'error');
            } catch (e) { console.error(e); }
        }
    }

    window.restaurarPedido = async function(id) {
        const { isConfirmed } = await Swal.fire({
            title: '¿Restaurar?',
            text: `El pedido #${id} volverá a estado PENDIENTE.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'SÍ, RESTAURAR'
        });

        if (isConfirmed) {
            try {
                const res = await fetch('api_pedidos_admin.php?action=restore', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify({ numpedido: id })
                }).then(r => r.json());

                if (res.success) buscarPedidos();
                else Swal.fire('Error', res.error, 'error');
            } catch (e) { console.error(e); }
        }
    }

    window.imprimirPedido = function(id) {
        window.open('imprimir_pedido.php?id=' + id, '_blank');
        setTimeout(buscarPedidos, 2000);
    }

    // --- LÓGICA DE EXPORTACIÓN EXCEL NATIVA (JS) ---
    window.exportarExcel = function() {
        if (!listadoPedidos || listadoPedidos.length === 0) {
            Swal.fire({ icon: 'info', title: 'Sin datos', text: 'No hay pedidos cargados para exportar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            return;
        }

        const desde = $('#dtmDesde').val().split('-').reverse().join('/');
        const hasta = $('#dtmHasta').val().split('-').reverse().join('/');
        const estadoSelect = $('input[name="filtroEstado"]:checked').next('label').text().toUpperCase();

        let excelHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="utf-8">
                <style>
                    .header { background-color: #0d2b45; color: #00ffff; font-weight: bold; text-align: center; border: 0.5pt solid #444; }
                    .title { font-size: 14pt; font-weight: bold; text-align: left; }
                    .number { mso-number-format:"0"; text-align: center; border: 0.5pt solid #ccc; }
                    .text { text-align: left; border: 0.5pt solid #ccc; }
                    .center { text-align: center; border: 0.5pt solid #ccc; }
                    .pendiente { color: #d39e00; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .facturado { color: #28a745; font-weight: bold; text-align: center; border: 0.5pt solid #ccc;}
                    .anulado { color: #dc3545; text-decoration: line-through; text-align: center; border: 0.5pt solid #ccc;}
                </style>
            </head>
            <body>
                <table>
                    <tr><td colspan="10" class="title">REPORTE DE PEDIDOS DE VENTA</td></tr>
                    <tr><td colspan="10">RANGO: ${desde} AL ${hasta} | ESTADO FILTRADO: ${estadoSelect}</td></tr>
                    <tr><td colspan="10"></td></tr>
                    <thead>
                        <tr>
                            <th class="header">F. CARGA</th>
                            <th class="header">N° PEDIDO</th>
                            <th class="header">CLIENTE</th>
                            <th class="header">F. ENTREGA</th>
                            <th class="header">CANT. PROD.</th>
                            <th class="header">OBSERVACIONES</th>
                            <th class="header">TRANSPORTE</th>
                            <th class="header">VENDEDOR</th>
                            <th class="header">ESTADO</th>
                            <th class="header">IMPRESO</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        let sumCantProd = 0;

        listadoPedidos.forEach(r => {
            let estadoTxt = '';
            let estadoCls = '';
            
            if (r.estadofac == 0) { estadoTxt = 'PENDIENTE'; estadoCls = 'pendiente'; }
            else if (r.estadofac == 1) { estadoTxt = 'FACTURADO'; estadoCls = 'facturado'; }
            else if (r.estadofac == 2) { estadoTxt = 'ANULADO'; estadoCls = 'anulado'; }

            const fechaCarga = r.fecha_carga ? formatDate(r.fecha_carga) : '-';
            const fechaEntrega = r.fecha_entrega ? formatDate(r.fecha_entrega) : '-';
            const cantProd = parseInt(r.cantidad_productos || 0);
            
            // Solo sumamos los productos de pedidos NO anulados
            if (r.estadofac != 2) sumCantProd += cantProd;

            excelHtml += `
                <tr>
                    <td class="center">${fechaCarga}</td>
                    <td class="center" style="font-weight:bold; color:#17a2b8; mso-number-format:'\\@';">${r.numpedido}</td>
                    <td class="text">${(r.cliente || '').toUpperCase()}</td>
                    <td class="center">${fechaEntrega}</td>
                    <td class="number">${cantProd > 0 ? cantProd : ''}</td>
                    <td class="text">${(r.observaciones || '').toUpperCase()}</td>
                    <td class="text">${(r.transporte || '-').toUpperCase()}</td>
                    <td class="text">${(r.vendedor || 'S/V').toUpperCase()}</td>
                    <td class="${estadoCls}">${estadoTxt}</td>
                    <td class="center">${r.impreso == 1 ? 'SI' : 'NO'}</td>
                </tr>
            `;
        });

        excelHtml += `
                    <tr>
                        <td colspan="4" style="text-align:right; font-weight:bold; border: 0.5pt solid #ccc;">TOTALES ACTIVOS:</td>
                        <td class="number" style="font-weight:bold; color:#006666;">${sumCantProd}</td>
                        <td colspan="5"></td>
                    </tr>
                </tbody></table></body></html>
        `;

        const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel' });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `Pedidos_Venta_${new Date().getTime()}.xls`;
        link.click();
    }

    init();
})();
</script>