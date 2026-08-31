<?php
/**
 * proveedores_conceptos_compra.php - ADMINISTRACIÓN DE CONCEPTOS DE COMPRA
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="text-white mb-0">CONCEPTOS DE COMPRA</h4>
            <small class="text-accent opacity-75">Configuración de rubros para carga simplificada de comprobantes</small>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" id="btn-exportar-conc"><i class="fas fa-file-excel me-2"></i>EXCEL</button>
            <button class="btn btn-info fw-bold px-4" id="btn-nuevo-concepto"><i class="fas fa-plus me-2"></i>NUEVO CONCEPTO</button>
        </div>
    </div>

    <!-- SECCIÓN DE FILTROS -->
    <div class="card card-filtros mb-3 shadow-sm">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label-custom">CÓDIGO</label>
                    <input type="text" id="filtro-codigo" class="form-control form-control-sm" placeholder="Buscar código...">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">CONCEPTO / TEXTO</label>
                    <input type="text" id="filtro-texto" class="form-control form-control-sm" placeholder="Buscar por descripción del concepto...">
                </div>
                <div class="col-md-4">
                    <label class="form-label-custom">CUENTA CONTABLE</label>
                    <select id="filtro-cuenta" class="form-control"></select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-secondary btn-sm w-100" id="btn-limpiar-filtros">
                        <i class="fas fa-eraser me-2"></i>LIMPIAR
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- GRILLA -->
    <div class="card card-filtros p-0 overflow-hidden shadow-sm">
        <div class="card-header bg-dark border-secondary p-2 px-3 d-flex justify-content-between align-items-center">
            <span class="text-white small fw-bold"><i class="fas fa-tags me-2 text-accent"></i>LISTADO DE CONCEPTOS CONFIGURADOS</span>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted small">Registros: <b class="text-accent" id="lbl-total-regs">0</b></span>
                <button class="btn btn-sm btn-link text-accent p-0" id="btn-refresh-conc"><i class="fas fa-sync-alt"></i></button>
            </div>
        </div>
        <div class="table-responsive" style="max-height: 70vh; min-height: 400px;">
            <table class="table table-hover align-middle mb-0 small" id="tabla-conceptos">
                <thead class="sticky-top">
                    <tr>
                        <th style="width: 80px;">CÓDIGO</th>
                        <th>CONCEPTO / DESCRIPCIÓN</th>
                        <th>Nº CUENTA CONTABLE</th>
                        <th>NOMBRE CUENTA CONTABLE</th>
                        <th style="width: 120px;" class="text-center">ACCIONES</th>
                    </tr>
                </thead>
                <tbody id="tbody-conceptos">
                    <tr><td colspan="5" class="text-center p-5 text-muted"><div class="spinner-border text-info"></div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL ALTA/EDICIÓN -->
<div class="modal fade" id="modalConcepto" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-escritorio border-accent shadow-lg">
            <div class="modal-header border-secondary">
                <h5 class="modal-title text-white" id="modalTitle">Nuevo Concepto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="form-concepto">
                <input type="hidden" id="conc-id" name="id">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label-custom">CÓDIGO</label>
                            <input type="text" id="conc-codigo" name="codigo" class="form-control" required placeholder="Ej: MER">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label-custom">NOMBRE DEL CONCEPTO</label>
                            <input type="text" id="conc-nombre" name="concepto" class="form-control" required placeholder="Ej: MERCADERÍA">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label-custom">CUENTA CONTABLE ASOCIADA</label>
                            <select id="conc-cuenta" name="cuentacontable" class="form-control" required></select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">CANCELAR</button>
                    <button type="submit" class="btn btn-info fw-bold px-4">GUARDAR CAMBIOS</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    let listado = [];
    const commonHeaders = { 'X-Requested-With': 'XMLHttpRequest' };

    async function init() {
        await cargarCuentas();
        configurarEventos();
        buscar();
    }

    async function cargarCuentas() {
        try {
            const res = await fetch('api_conceptos_compra.php?action=get_cuentas', { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                // Select2 para el Modal
                $('#conc-cuenta').select2({
                    dropdownParent: $('#modalConcepto'),
                    data: res.data,
                    width: '100%',
                    placeholder: 'BUSCAR CUENTA...'
                });

                // Select2 para el Filtro
                $('#filtro-cuenta').select2({
                    dropdownParent: $('#main-content'),
                    data: [{id: '', text: '-- TODAS LAS CUENTAS --'}, ...res.data],
                    width: '100%',
                    placeholder: 'FILTRAR POR CUENTA...'
                }).on('change', aplicarFiltros);
            }
        } catch (e) { console.error("Error al cargar cuentas contables", e); }
    }

    function configurarEventos() {
        $('#btn-refresh-conc').click(buscar);
        $('#btn-nuevo-concepto').click(() => openModal());
        $('#btn-exportar-conc').click(exportar);

        // Listeners de filtrado en tiempo real
        $('#filtro-codigo, #filtro-texto').on('input', aplicarFiltros);
        $('#btn-limpiar-filtros').click(function() {
            $('#filtro-codigo, #filtro-texto').val('');
            $('#filtro-cuenta').val('').trigger('change');
            render(listado);
        });

        $('#form-concepto').on('submit', async function(e) {
            e.preventDefault();
            const data = {
                id: $('#conc-id').val(),
                codigo: $('#conc-codigo').val(),
                concepto: $('#conc-nombre').val(),
                cuentacontable: $('#conc-cuenta').val()
            };

            const res = await fetch('api_conceptos_compra.php?action=save', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            }).then(r => r.json());

            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Éxito', text: res.message, toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                $('#modalConcepto').modal('hide');
                buscar();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        });
    }

    async function buscar() {
        const tbody = $('#tbody-conceptos').html('<tr><td colspan="5" class="text-center p-5"><div class="spinner-border text-info"></div></td></tr>');
        try {
            const res = await fetch('api_conceptos_compra.php?action=list', { headers: commonHeaders }).then(r => r.json());
            if (res.success) {
                listado = res.data;
                aplicarFiltros();
            }
        } catch (e) { console.error(e); }
    }

    function aplicarFiltros() {
        const cod = $('#filtro-codigo').val().toUpperCase();
        const txt = $('#filtro-texto').val().toUpperCase();
        const cta = $('#filtro-cuenta').val();

        const filtered = listado.filter(item => {
            const matchCod = item.Codigo.toUpperCase().includes(cod);
            const matchTxt = item.Concepto.toUpperCase().includes(txt);
            const matchCta = cta === '' || item['Num Cuenta Cont.'] == cta;
            return matchCod && matchTxt && matchCta;
        });

        render(filtered);
    }

    function render(dataToRender) {
        const tbody = $('#tbody-conceptos').empty();
        $('#lbl-total-regs').text(dataToRender.length);

        if (dataToRender.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center p-5 text-muted small">No se encontraron conceptos con los filtros aplicados</td></tr>');
            return;
        }

        dataToRender.forEach((c) => {
            // Buscamos el índice original en listado para las funciones de window
            const originalIdx = listado.findIndex(orig => orig.ID === c.ID);
            
            tbody.append(`
                <tr>
                    <td class="fw-bold text-accent">${c.Codigo}</td>
                    <td class="text-white">${c.Concepto}</td>
                    <td class="text-muted">${c['Num Cuenta Cont.']}</td>
                    <td><small>${c['Cuenta Contable']}</small></td>
                    <td class="text-center">
                        <div class="btn-group btn-group-sm shadow-sm">
                            <button class="btn btn-outline-info" onclick="window.editConc(${originalIdx})" title="Editar"><i class="fas fa-edit"></i></button>
                            <button class="btn btn-outline-danger" onclick="window.delConc(${originalIdx})" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function openModal(data = null) {
        $('#form-concepto')[0].reset();
        $('#conc-id').val('');
        $('#conc-cuenta').val(null).trigger('change');
        
        if (data) {
            $('#modalTitle').text('Modificar Concepto');
            $('#conc-id').val(data.ID);
            $('#conc-codigo').val(data.Codigo);
            $('#conc-nombre').val(data.Concepto);
            $('#conc-cuenta').val(data['Num Cuenta Cont.']).trigger('change');
        } else {
            $('#modalTitle').text('Nuevo Concepto');
        }
        $('#modalConcepto').modal('show');
    }

    window.editConc = (idx) => openModal(listado[idx]);

    window.delConc = async (idx) => {
        const c = listado[idx];
        const confirm = await Swal.fire({
            title: `¿Eliminar ${c.Concepto}?`,
            text: "Esta acción no se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'SÍ, ELIMINAR',
            cancelButtonText: 'CANCELAR'
        });

        if (confirm.isConfirmed) {
            const res = await fetch('api_conceptos_compra.php?action=delete', {
                method: 'POST',
                headers: { ...commonHeaders, 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: c.ID })
            }).then(r => r.json());

            if (res.success) {
                Swal.fire({ icon: 'success', title: 'Eliminado', toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
                buscar();
            } else {
                Swal.fire('Atención', res.error, 'warning');
            }
        }
    };

    function exportar() {
        if (listado.length === 0) return Swal.fire('Error', 'No hay datos para exportar', 'warning');
        window.open('api_conceptos_compra.php?action=export_excel', '_blank');
    }

    init();
})();
</script>