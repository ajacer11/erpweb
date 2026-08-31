<?php
/**
 * recetas_admin.php - GESTIÓN DE RECETAS / FÓRMULAS DE PRODUCCIÓN
 * Adaptación Responsiva v5.7 (Paginación Dual + Mobile Cards)
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloRecetas">
    <div id="panelListado">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h4 class="text-white mb-0">GESTIÓN DE RECETAS</h4>
                <small class="text-accent opacity-75">FÓRMULAS DE PRODUCCIÓN</small>
            </div>
            <button onclick="mostrarFormulario()" class="btn btn-info fw-bold rounded-pill px-4">
                <i class="fas fa-plus me-2"></i>NUEVA RECETA
            </button>
        </div>

        <div class="card card-filtros mb-4 shadow-sm border-0">
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label-custom small opacity-75 text-uppercase">Búsqueda rápida</label>
                        <input type="text" id="busqReceta" class="form-control" placeholder="BUSCAR POR PRODUCTO O DESCRIPCIÓN..." onkeyup="agendarBusqueda()">
                    </div>
                </div>
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
                <select id="pagTamanoTop" class="erp-pag-select" onchange="pagCambiarTamanoTop()">
                    <option value="20" selected>20</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="erp-table-wrap table-responsive shadow-sm border border-secondary rounded" style="background-color: #000a1a; max-height: 60vh;">
            <table class="table table-dark table-hover align-middle mb-0 small">
                <thead class="sticky-top bg-dark" style="z-index: 5;">
                    <tr class="text-accent border-bottom border-secondary">
                        <th width="80" class="ps-3">ID</th>
                        <th>PRODUCTO FINAL</th>
                        <th class="text-center">VERSIÓN</th>
                        <th class="text-center">ESTADO</th>
                        <th width="100" class="text-center pe-3">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tablaRecetasBody"></tbody>
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

    <div id="panelFormulario" class="d-none">
        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
            <h5 class="text-white mb-0" id="tituloForm">NUEVA RECETA</h5>
            <button onclick="cerrarFormulario()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
                <i class="fas fa-arrow-left me-2"></i>VOLVER
            </button>
        </div>

        <form id="frmReceta">
            <input type="hidden" name="id" id="recetaId">
            
            <div class="row g-3 mb-4">
                <div class="col-md-8">
                    <label class="form-label-custom">Producto Terminado</label>
                    <select class="form-select select2-simple" id="cmbProductoFinal" name="id_producto" required></select>
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">Versión / Identificador</label>
                    <input type="text" class="form-control" name="version" id="txtVersion" value="1.0" placeholder="Ej: v1.0 o Estándar">
                </div>
            </div>

            <div class="card card-filtros p-3 mb-3 border-0">
                <h6 class="text-accent mb-3 border-bottom border-secondary pb-2">COMPONENTES / INSUMOS</h6>
                
                <div class="row g-2 align-items-end mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom small">Insumo</label>
                        <select class="form-select select2-simple" id="cmbInsumo"></select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label-custom small">Cantidad</label>
                        <input type="number" class="form-control text-center fw-bold" id="txtCantInsumo" step="0.001" placeholder="0.000">
                    </div>
                    <div class="col-md-3">
                        <button type="button" class="btn btn-warning w-100 fw-bold" onclick="agregarInsumo()">
                            <i class="fas fa-plus me-2"></i>AGREGAR
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-dark table-bordered mb-0">
                        <thead>
                            <tr class="text-accent bg-dark bg-opacity-50">
                                <th class="ps-3">INSUMO</th>
                                <th class="text-center" width="150">CANTIDAD</th>
                                <th class="text-center" width="50"></th>
                            </tr>
                        </thead>
                        <tbody id="bodyInsumos"></tbody>
                    </table>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mt-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="chkActiva" name="activa" checked value="1">
                    <label class="form-check-label text-white small fw-bold" for="chkActiva">RECETA ACTIVA</label>
                </div>
                <button type="submit" class="btn btn-info px-5 fw-bold rounded-pill shadow-sm">
                    <i class="fas fa-save me-2"></i>GUARDAR RECETA
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    let listadoCompleto = [];
    let insumosList = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    /**
     * Inicialización SPA
     */
    async function init() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

        function initSelect2Product(selector) {
            $(selector).select2({
                dropdownParent: $('#main-content'),
                width: '100%',
                ajax: {
                    url: 'api_productos.php', 
                    dataType: 'json',
                    delay: 250,
                    data: function(params) { return { q: params.term }; },
                    processResults: function(data) {
                        return { results: data.data.map(p => ({ id: p.id, text: p.codigo + ' - ' + p.descripcion })) };
                    }
                },
                placeholder: 'Buscar...',
                minimumInputLength: 1
            });
        }
        initSelect2Product('#cmbProductoFinal');
        initSelect2Product('#cmbInsumo');

        listarRecetas();
    }

    window.agendarBusqueda = function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => listarRecetas(), 600);
    };

    window.listarRecetas = async function() {
        const q = document.getElementById('busqReceta').value;
        try {
            $('#loading-overlay').show();
            const resp = await fetch(`api_produccion.php?action=get_recetas&q=${encodeURIComponent(q)}`, { 
                headers: {'X-Requested-With': 'XMLHttpRequest'} 
            });
            const res = await resp.json();
            listadoCompleto = res.data || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error(error);
        } finally {
            $('#loading-overlay').hide();
        }
    };

    function renderPagina() {
        const total = listadoCompleto.length;
        pagTotal = Math.max(1, Math.ceil(total / pagTam));
        if (pagActual > pagTotal) pagActual = pagTotal;

        const inicio = (pagActual - 1) * pagTam;
        const segmento = listadoCompleto.slice(inicio, inicio + pagTam);

        renderTabla(segmento);
        renderTarjetas(segmento);
        sincronizarPaginacion(total);
    }

    function renderTabla(data) {
        const tbody = document.getElementById('tablaRecetasBody');
        tbody.innerHTML = '';

        if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No se encontraron recetas</td></tr>';
            return;
        }

        data.forEach(r => {
            const badge = r.activa == 1 ? '<span class="badge bg-success">ACTIVA</span>' : '<span class="badge bg-secondary">INACTIVA</span>';
            const tr = document.createElement('tr');
            tr.className = "border-secondary align-middle";
            tr.innerHTML = `
                <td class="text-white-50 small ps-3">${r.id}</td>
                <td class="text-white fw-bold">${r.producto_final}</td>
                <td class="text-center">${r.version || '-'}</td>
                <td class="text-center">${badge}</td>
                <td class="text-center pe-3">
                    <button class="btn btn-sm btn-link text-info" onclick="editarReceta(${r.id})" title="Editar"><i class="fas fa-edit"></i></button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function renderTarjetas(data) {
        const wrap = document.getElementById('tblCards');
        wrap.innerHTML = '';

        data.forEach(r => {
            const badge = r.activa == 1 ? '<span class="badge bg-success">ACTIVA</span>' : '<span class="badge bg-secondary">INACTIVA</span>';
            const card = document.createElement('div');
            card.className = 'erp-card mb-2';
            card.innerHTML = `
                <div class="erp-card-header">
                    <div class="erp-card-title text-accent">${r.producto_final}</div>
                    <span class="erp-card-code">ID: ${r.id}</span>
                </div>
                <div class="erp-card-main">
                    <div class="row align-items-center">
                        <div class="col-6">
                            <small class="text-muted d-block">VERSIÓN</small>
                            <span class="text-white small">${r.version || '-'}</span>
                        </div>
                        <div class="col-6 text-end">
                            ${badge}
                        </div>
                    </div>
                </div>
                <div class="mt-2 pt-2 border-top border-secondary">
                    <button class="btn btn-info w-100 fw-bold btn-sm py-2" onclick="editarReceta(${r.id})">
                        <i class="fas fa-edit me-2"></i>EDITAR FÓRMULA
                    </button>
                </div>
            `;
            wrap.appendChild(card);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> fórmulas`;

        ['', 'Top'].forEach(sfx => {
            $(`#pagInfo${sfx}`).html(info);
            $(`#btnPagPrim${sfx}, #btnPagAnt${sfx}`).prop('disabled', pagActual <= 1);
            $(`#btnPagSig${sfx}, #btnPagUlt${sfx}`).prop('disabled', pagActual >= pagTotal);

            const cont = $(`#pagBotones${sfx}`).empty();
            let ini = Math.max(1, pagActual - 2);
            let fin = Math.min(pagTotal, ini + 4);
            if (fin - ini < 4) ini = Math.max(1, fin - 4);

            for (let i = ini; i <= fin; i++) {
                const b = $('<button>').addClass('erp-pag-btn').text(i).click(() => pagGo(i));
                if (i === pagActual) b.addClass('active');
                cont.append(b);
            }
            $(`#pag${sfx}Wrap`).toggle(total > 0);
        });
    }

    window.pagGo = (n) => {
        pagActual = Math.max(1, Math.min(pagTotal, n));
        renderPagina();
        document.getElementById('moduloRecetas').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    window.mostrarFormulario = () => {
        document.getElementById('panelListado').classList.add('d-none');
        document.getElementById('panelFormulario').classList.remove('d-none');
        document.getElementById('frmReceta').reset();
        document.getElementById('recetaId').value = '';
        $('#cmbProductoFinal').val(null).trigger('change');
        insumosList = [];
        renderInsumos();
        document.getElementById('tituloForm').innerText = "NUEVA RECETA";
    }

    window.cerrarFormulario = () => {
        document.getElementById('panelListado').classList.remove('d-none');
        document.getElementById('panelFormulario').classList.add('d-none');
        listarRecetas();
    }

    window.agregarInsumo = () => {
        const id = $('#cmbInsumo').val();
        const text = $('#cmbInsumo option:selected').text();
        const cant = parseFloat(document.getElementById('txtCantInsumo').value);

        if (!id || !cant || cant <= 0) { 
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccione un insumo y cantidad válida', background: '#001020', color: '#fff' }); 
            return; 
        }

        insumosList.push({ id_producto: id, nombre: text, cantidad: cant });
        renderInsumos();
        $('#cmbInsumo').val(null).trigger('change');
        document.getElementById('txtCantInsumo').value = '';
    }

    window.borrarInsumo = (idx) => {
        insumosList.splice(idx, 1);
        renderInsumos();
    }

    function renderInsumos() {
        const tbody = document.getElementById('bodyInsumos');
        tbody.innerHTML = '';
        insumosList.forEach((item, idx) => {
            tbody.innerHTML += `
                <tr class="border-secondary align-middle">
                    <td class="ps-3 text-white-50 small">${item.nombre}</td>
                    <td class="text-center fw-bold text-accent">${parseFloat(item.cantidad).toFixed(3)}</td>
                    <td class="text-center"><button type="button" class="btn btn-link text-danger p-0" onclick="borrarInsumo(${idx})"><i class="fas fa-trash"></i></button></td>
                </tr>`;
        });
    }

    window.editarReceta = async (id) => {
        mostrarFormulario();
        document.getElementById('tituloForm').innerText = "EDITAR RECETA #" + id;
        
        try {
            const resp = await fetch(`api_produccion.php?action=get_receta_detalle&id=${id}`, { 
                headers: {'X-Requested-With': 'XMLHttpRequest'} 
            });
            const res = await resp.json();
            const d = res.data;

            document.getElementById('recetaId').value = d.enc.id;
            document.getElementById('txtVersion').value = d.enc.version;
            document.getElementById('chkActiva').checked = (d.enc.activa == 1);

            if (d.enc.producto_terminado_id) {
                const nombreCompleto = d.enc.prod_codigo + ' - ' + d.enc.prod_descripcion;
                const newOption = new Option(nombreCompleto, d.enc.producto_terminado_id, true, true);
                $('#cmbProductoFinal').empty().append(newOption).trigger('change');
            }

            insumosList = d.det.map(x => ({
                id_producto: x.producto_componente_id,
                nombre: x.codigo + ' - ' + x.descripcion,
                cantidad: x.cantidad
            }));
            renderInsumos();
        } catch (error) {
            Swal.fire('Error', 'No se pudo cargar el detalle', 'error');
        }
    }

    document.getElementById('frmReceta').onsubmit = async (e) => {
        e.preventDefault();
        if (insumosList.length === 0) { 
            Swal.fire({ icon: 'warning', title: 'Atención', text: 'La receta debe tener al menos un insumo', background: '#001020', color: '#fff' }); 
            return; 
        }
        let descripcionreceta = 'Receta Para: ' + $('#cmbProductoFinal option:selected').text();
        const data = {
            id: document.getElementById('recetaId').value,
            id_producto: $('#cmbProductoFinal').val(),
            version: document.getElementById('txtVersion').value,
            activa: document.getElementById('chkActiva').checked ? 1 : 0,
            detalles: insumosList,
            descripcion: descripcionreceta
        };

        try {
            const resp = await fetch('api_produccion.php?action=save_receta', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(data)
            });
            const res = await resp.json();
            if(res.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: 'Receta guardada', timer: 1500, showConfirmButton: false, background: '#001020', color: '#fff' });
                cerrarFormulario();
            } else Swal.fire('Error', res.error, 'error');
        } catch(err) { Swal.fire('Error', 'Fallo de conexión', 'error'); }
    }

    init();
})();
</script>