<?php
/**
 * bancos_entidades.php - ABM DE ENTIDADES BANCARIAS
 * Adaptado a arquitectura SPA con paginación en memoria y tarjetas responsivas.
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in" id="moduloBancosEntidades">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0"><i class="fas fa-university text-accent me-2"></i>ENTIDADES BANCARIAS</h4>
            <small class="text-accent opacity-75">Gestión de bancos habilitados para operaciones</small>
        </div>
        <button class="btn btn-info fw-bold rounded-pill px-4" onclick="abrirModalBanco()">
            <i class="fas fa-plus me-2"></i>NUEVO BANCO
        </button>
    </div>

    <div class="card card-filtros mb-4 shadow-sm border-0">
        <div class="card-body p-3">
            <div class="row g-2">
                <div class="col-md-10">
                    <label class="form-label-custom small opacity-75 text-uppercase">Búsqueda rápida</label>
                    <input type="text" id="txtBuscarBanco" class="form-control" placeholder="Buscar por código o denominación...">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-secondary w-100 fw-bold" onclick="listarBancos()">
                        <i class="fas fa-search me-2"></i>BUSCAR
                    </button>
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
        <table class="table table-dark table-hover align-middle mb-0">
            <thead class="sticky-top bg-dark" style="z-index: 5;">
                <tr class="text-accent border-bottom border-secondary">
                    <th width="100" class="text-center py-2 ps-3">CÓDIGO</th>
                    <th class="py-2">DENOMINACIÓN</th>
                    <th width="120" class="text-center py-2 pe-3">ACCIONES</th>
                </tr>
            </thead>
            <tbody id="tbodyBancos">
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

<div class="modal fade" id="modalBanco" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-info shadow-lg">
            <div class="modal-header bg-info bg-opacity-10 border-bottom border-info border-opacity-25">
                <h6 class="modal-title text-white fw-bold" id="lblTituloModal">NUEVA ENTIDAD BANCARIA</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form id="frmBanco">
                    <input type="hidden" id="hdnId" name="id" value="0">
                    <div class="mb-3">
                        <label class="form-label-custom">CÓDIGO (BCRA / INTERNO)</label>
                        <input type="number" id="txtCodigo" name="codigo" class="form-control fw-bold text-info" required placeholder="Ej: 072">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">DENOMINACIÓN</label>
                        <input type="text" id="txtDenominacion" name="denominacion" class="form-control text-uppercase" required placeholder="Ej: BANCO SANTANDER">
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 bg-dark py-2">
                <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">CANCELAR</button>
                <button type="button" class="btn btn-info rounded-pill fw-bold px-4" onclick="guardarBanco()">GUARDAR</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    let listadoCompleto = [];
    let pagActual = 1;
    let pagTam = 20;
    let pagTotal = 1;
    let debounceTimer;

    /**
     * Obtención de datos y gestión SPA
     */
    window.listarBancos = async function() {
        const query = $('#txtBuscarBanco').val();
        
        try {
            $('#loading-overlay').show();
            const resp = await fetch(`api_bancos_entidades.php?action=list&q=${encodeURIComponent(query)}`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await resp.json();
            
            listadoCompleto = data || [];
            pagActual = 1;
            renderPagina();
        } catch (error) {
            console.error(error);
            $('#tbodyBancos').html('<tr><td colspan="3" class="text-center py-4 text-danger">Error de conexión</td></tr>');
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
        const tbody = $('#tbodyBancos');
        tbody.empty();

        if (data.length === 0) {
            tbody.html('<tr><td colspan="3" class="text-center py-4 text-muted">No se encontraron bancos.</td></tr>');
            return;
        }

        data.forEach(b => {
            tbody.append(`
                <tr class="border-secondary align-middle">
                    <td class="text-center text-info fw-bold">${b.codigo}</td>
                    <td class="fw-bold text-white ps-2">${b.denominacion}</td>
                    <td class="text-center pe-3">
                        <button class="btn btn-sm btn-link text-info" onclick="editarBanco(${b.id})" title="Editar"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-link text-danger" onclick="eliminarBanco(${b.id}, '${b.denominacion}')" title="Eliminar"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `);
        });
    }

    function renderTarjetas(data) {
        const wrap = $('#tblCards');
        wrap.empty();

        data.forEach(b => {
            wrap.append(`
                <div class="erp-card mb-2">
                    <div class="erp-card-header">
                        <div class="erp-card-title text-accent">${b.denominacion}</div>
                        <span class="erp-card-code">CÓD: ${b.codigo}</span>
                    </div>
                    <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-sm btn-info flex-fill fw-bold" onclick="editarBanco(${b.id})">
                            <i class="fas fa-edit me-1"></i> EDITAR
                        </button>
                        <button class="btn btn-sm btn-outline-danger px-3" onclick="eliminarBanco(${b.id}, '${b.denominacion}')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `);
        });
    }

    function sincronizarPaginacion(total) {
        const desde = total > 0 ? (pagActual - 1) * pagTam + 1 : 0;
        const hasta = Math.min(pagActual * pagTam, total);
        const info = `Mostrando <strong>${desde}-${hasta}</strong> de <strong>${total}</strong> entidades`;

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
        document.getElementById('moduloBancosEntidades').scrollIntoView({ behavior: 'smooth' });
    };

    window.pagCambiarTamanoTop = () => {
        pagTam = parseInt($('#pagTamanoTop').val());
        pagActual = 1;
        renderPagina();
    };

    /**
     * Gestión de ABM (Se mantiene lógica original)
     */
    window.abrirModalBanco = function() {
        $('#hdnId').val('0');
        $('#txtCodigo').val('');
        $('#txtDenominacion').val('');
        $('#lblTituloModal').text('NUEVA ENTIDAD BANCARIA');
        $('#modalBanco').modal('show');
        setTimeout(() => $('#txtCodigo').focus(), 500);
    };

    window.editarBanco = async function(id) {
        try {
            const resp = await fetch(`api_bancos_entidades.php?action=get&id=${id}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
            const res = await resp.json();
            
            if (res.success) {
                $('#hdnId').val(res.data.id);
                $('#txtCodigo').val(res.data.codigo);
                $('#txtDenominacion').val(res.data.denominacion);
                $('#lblTituloModal').text('EDITAR ENTIDAD BANCARIA');
                $('#modalBanco').modal('show');
            } else {
                Swal.fire('Error', 'No se pudieron cargar los datos', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Error de conexión', 'error');
        }
    };

    window.guardarBanco = async function() {
        if (!$('#txtCodigo').val() || !$('#txtDenominacion').val()) {
            Swal.fire('Atención', 'Complete código y denominación', 'warning');
            return;
        }

        const formData = new FormData(document.getElementById('frmBanco'));
        const data = Object.fromEntries(formData.entries());

        try {
            const resp = await fetch('api_bancos_entidades.php?action=save', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify(data)
            });
            const res = await resp.json();

            if (res.success) {
                $('#modalBanco').modal('hide');
                Swal.fire({
                    title: 'Guardado', 
                    text: 'Operación exitosa', 
                    icon: 'success', 
                    timer: 1500, 
                    showConfirmButton: false,
                    background: '#001020', color: '#fff'
                });
                listarBancos();
            } else {
                Swal.fire('Error', res.error || 'Error al guardar', 'error');
            }
        } catch (e) {
            Swal.fire('Error', 'Error de conexión al guardar', 'error');
        }
    };

    window.eliminarBanco = async function(id, nombre) {
        const confirm = await Swal.fire({
            title: '¿Eliminar Banco?',
            text: `Se intentará eliminar: ${nombre}`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            background: '#001020', color: '#fff'
        });

        if (confirm.isConfirmed) {
            try {
                const resp = await fetch(`api_bancos_entidades.php?action=delete&id=${id}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}});
                const res = await resp.json();

                if (res.success) {
                    Swal.fire('Eliminado', 'Operación exitosa', 'success');
                    listarBancos();
                } else {
                    Swal.fire({
                        title: 'No se puede eliminar', 
                        text: res.error, 
                        icon: 'error', 
                        background: '#001020', color: '#fff'
                    });
                }
            } catch (e) {
                Swal.fire('Error', 'Error de conexión al eliminar', 'error');
            }
        }
    };

    // Filtro con debounce para no saturar al backend
    $('#txtBuscarBanco').off('input').on('input', function() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => listarBancos(), 800);
    });

    // Llamada inicial
    listarBancos();

})();
</script>