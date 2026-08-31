<?php
/**
 * stock_ajuste_excel.php - AJUSTE MASIVO DE STOCK POR PLANILLA EXCEL
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h4 class="text-white mb-0">AJUSTE MASIVO DE STOCK POR EXCEL</h4>
            <small class="text-accent opacity-75">Actualización automática de inventario mediante planilla</small>
        </div>
        <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="chk-fiscal" checked>
            <label class="form-check-label small text-white" for="chk-fiscal">AJUSTE FISCAL</label>
        </div>
    </div>

    <div class="mt-0 mb-4 p-3 rounded" style="border: 1px solid var(--warning); background: rgba(255, 193, 7, 0.08);">
        <div class="d-flex align-items-center gap-3">
            <i class="fas fa-info-circle fs-4" style="color: var(--warning); flex-shrink: 0;"></i>
            <div style="font-size: 11px; color: var(--text-muted);">
                <span style="color: var(--text-main); font-weight: bold;">REGLA DE NEGOCIO:</span>
                El sistema lleva el stock de cada código al valor de la columna <strong style="color: var(--warning);">STOCK ACTUAL</strong> de la planilla,
                generando automáticamente los ingresos o egresos necesarios y registrando el ajuste en las mismas tablas que un ajuste manual
                (cabecera, línea de ajuste, movimiento de stock e ingreso de stock).
            </div>
        </div>
    </div>

    <div class="row g-3">
        <!-- Columna Izquierda: pasos 1 y 2 -->
        <div class="col-lg-4">
            <div class="card card-filtros mb-3">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-file-download me-2 text-info"></i>1. DESCARGAR PLANILLA</h6>
                    <p class="text-muted small">Genera un Excel con el stock actual. Puede filtrar qué productos incluir, o dejar todos los campos vacíos para incluirlos todos.</p>

                    <div class="mb-2">
                        <label class="form-label-custom">RUBRO</label>
                        <select class="form-select select2-simple" id="f-rubro"><option value="">TODOS</option></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label-custom">MARCA</label>
                        <select class="form-select select2-simple" id="f-marca"><option value="">TODAS</option></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label-custom">FAMILIA</label>
                        <select class="form-select select2-simple" id="f-familia"><option value="">TODAS</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">PROVEEDOR</label>
                        <select class="form-select select2-simple" id="f-proveedor"><option value="">TODOS</option></select>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-outline-info fw-bold rounded-pill" id="btn-descargar">
                            <i class="fas fa-download me-2"></i>DESCARGAR PLANILLA (.XLSX)
                        </button>
                    </div>
                </div>
            </div>

            <div class="card card-filtros">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-upload me-2 text-warning"></i>2. SUBIR PLANILLA COMPLETADA</h6>
                    <form id="frm-upload-ajuste">
                        <div class="mb-3">
                            <input type="file" class="form-control" name="archivo" accept=".xlsx,.xls,.csv" required>
                            <small class="text-muted d-block mt-1">Formatos admitidos: XLSX, XLS, CSV.</small>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill text-dark">
                                <i class="fas fa-check-double me-2"></i>VALIDAR ARCHIVO
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: previsualización y confirmación -->
        <div class="col-lg-8">
            <div class="card card-filtros h-100" style="position: relative;">
                <div id="loading-ajuste" style="display: none; position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 100; justify-content: center; align-items: center;">
                    <div class="text-center">
                        <div class="spinner-border text-info" role="status"></div>
                        <div class="text-info mt-2 small">PROCESANDO...</div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-list-check me-2 text-info"></i>3. VALIDACIÓN Y CONFIRMACIÓN</h6>

                    <!-- Estado vacío -->
                    <div id="empty-state-ajuste" class="text-center py-5 text-muted">
                        <i class="fas fa-file-excel fa-3x mb-3 opacity-25"></i>
                        <p>Suba una planilla para validar los datos antes de aplicar el ajuste.</p>
                    </div>

                    <!-- Resultado -->
                    <div id="panel-resultado" class="d-none">
                        <div class="d-flex flex-wrap gap-2 mb-3" id="badges-resumen"></div>

                        <div class="erp-table-wrap table-responsive mb-3" style="max-height: 350px;">
                            <table class="table table-hover align-middle table-sm mb-0">
                                <thead class="sticky-top">
                                    <tr>
                                        <th>CÓDIGO</th>
                                        <th>DESCRIPCIÓN</th>
                                        <th class="text-end">STOCK SISTEMA</th>
                                        <th class="text-end">STOCK PLANILLA</th>
                                        <th class="text-end">DIF.</th>
                                        <th>ESTADO</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-preview-ajuste"></tbody>
                            </table>
                        </div>
                        <small class="text-muted d-block mb-3" id="lbl-truncado" style="display:none;"></small>

                        <div class="row g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label-custom">NOMBRE DEL AJUSTE</label>
                                <input type="text" id="txt-nombre-ajuste" class="form-control">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label-custom">OBSERVACIONES</label>
                                <input type="text" id="txt-observacion-ajuste" class="form-control" placeholder="Motivo del ajuste masivo...">
                            </div>
                            <div class="col-md-2 d-none" id="wrap-cmb-deposito-excel">
                                <label class="form-label-custom">DEPÓSITO</label>
                                <select id="cmb-deposito-excel" class="form-select"></select>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <button class="btn btn-outline-secondary" id="btn-cancelar-ajuste">
                                <i class="fas fa-times me-1"></i>CANCELAR
                            </button>
                            <button class="btn btn-success fw-bold px-4 rounded-pill" id="btn-confirmar-ajuste">
                                <i class="fas fa-check-circle me-2"></i>CONFIRMAR Y APLICAR AJUSTE
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    let resumenActual = null;
    let hayProcesables = false;

    async function cargarCombos() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

        const endpoints = [
            { id: '#f-rubro', action: 'get_rubros', key: 'rubro', label: 'rubro' },
            { id: '#f-marca', action: 'get_marcas', key: 'id', label: 'marca' },
            { id: '#f-familia', action: 'get_familias', key: 'id', label: 'nombre' },
            { id: '#f-proveedor', action: 'get_proveedores', key: 'id', label: 'razon' }
        ];

        for (const item of endpoints) {
            try {
                const resp = await fetch(`api_helpers.php?action=${item.action}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await resp.json();
                if (res.success) res.data.forEach(d => $(item.id).append(new Option(d[item.label], d[item.key])));
            } catch (e) { /* silencioso */ }
        }

        // Depósito del lote: solo visible si el tenant tiene multidepósito activado.
        try {
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            const estado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
            if (estado.habilitado) {
                const [permitidos, miEstado] = await Promise.all([
                    fetch('api_depositos.php?action=list_permitidos', { headers }).then(r => r.json()),
                    fetch('api_depositos.php?action=mi_estado', { headers }).then(r => r.json())
                ]);
                const cmb = $('#cmb-deposito-excel').empty();
                (permitidos.data || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));
                if (miEstado.id_deposito) cmb.val(miEstado.id_deposito);
                $('#wrap-cmb-deposito-excel').removeClass('d-none');
            }
        } catch (e) { console.error('No se pudo cargar el depósito', e); }
    }

    window.descargarPlanillaAjuste = async function () {
        const params = new URLSearchParams({
            action: 'download_template',
            rubro: $('#f-rubro').val() || '',
            marca: $('#f-marca').val() || '',
            familia: $('#f-familia').val() || '',
            proveedor: $('#f-proveedor').val() || ''
        });

        const btn = document.getElementById('btn-descargar');
        const original = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>GENERANDO...';

        try {
            // Descarga vía Blob (requerido: el endpoint exige X-Requested-With, una
            // navegación directa del navegador no lo envía y sería rechazada).
            const resp = await fetch('api_stock_ajuste_excel.php?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });

            const contentType = resp.headers.get('Content-Type') || '';
            if (!resp.ok || contentType.includes('application/json')) {
                const err = await resp.json().catch(() => null);
                throw new Error((err && err.error) || 'No se pudo generar la planilla.');
            }

            const blob = await resp.blob();
            const disposition = resp.headers.get('Content-Disposition') || '';
            const match = disposition.match(/filename="?([^"]+)"?/);
            const filename = match ? match[1] : `Planilla_Ajuste_Stock_${Date.now()}.xlsx`;

            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        } catch (e) {
            Swal.fire('Error', e.message || 'No se pudo descargar la planilla.', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = original;
        }
    };
    $('#btn-descargar').click(descargarPlanillaAjuste);

    function badgeEstado(e) {
        if (e === 'OK') return 'bg-success';
        if (e === 'SIN_CAMBIO') return 'bg-secondary';
        if (e === 'NO_ENCONTRADO') return 'bg-danger';
        return 'bg-warning text-dark';
    }

    function renderResumen(resumen) {
        const items = [
            { label: 'TOTAL', val: resumen.total, cls: 'bg-info text-dark' },
            { label: 'A AJUSTAR', val: resumen.ok, cls: 'bg-success' },
            { label: 'SIN CAMBIOS', val: resumen.sin_cambio, cls: 'bg-secondary' },
            { label: 'NO ENCONTRADOS', val: resumen.no_encontrado, cls: 'bg-danger' },
            { label: 'INVÁLIDOS', val: resumen.invalido, cls: 'bg-warning text-dark' }
        ];
        $('#badges-resumen').html(items.map(i =>
            `<span class="badge ${i.cls} p-2 px-3">${i.label}: <strong>${i.val}</strong></span>`
        ).join(''));
    }

    function renderPreview(preview, truncado) {
        const tbody = $('#tbody-preview-ajuste').empty();
        if (!preview.length) {
            tbody.html('<tr><td colspan="6" class="text-center p-3 text-muted">SIN FILAS VÁLIDAS</td></tr>');
        } else {
            preview.forEach(r => {
                const dif = (r.diferencia === null || r.diferencia === undefined) ? '-' : Number(r.diferencia).toFixed(2);
                const difCls = r.diferencia > 0 ? 'text-success' : (r.diferencia < 0 ? 'text-danger' : 'text-muted');
                tbody.append(`<tr>
                    <td class="fw-bold text-white">${r.codigo}</td>
                    <td><small>${r.descripcion || '-'}</small>${r.motivo ? `<br><small class="text-danger">${r.motivo}</small>` : ''}</td>
                    <td class="text-end">${r.stock_sistema === null ? '-' : Number(r.stock_sistema).toFixed(2)}</td>
                    <td class="text-end">${r.stock_planilla === null ? '-' : Number(r.stock_planilla).toFixed(2)}</td>
                    <td class="text-end fw-bold ${difCls}">${dif}</td>
                    <td><span class="badge ${badgeEstado(r.estado)}">${r.estado.replace('_', ' ')}</span></td>
                </tr>`);
            });
        }
        if (truncado) {
            $('#lbl-truncado').show().text('Se muestran las primeras 300 filas. El total ingresado se procesará igualmente al confirmar.');
        } else {
            $('#lbl-truncado').hide();
        }
    }

    $('#frm-upload-ajuste').on('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);
        $('#loading-ajuste').css('display', 'flex');

        try {
            const resp = await fetch('api_stock_ajuste_excel.php?action=upload', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            });
            const res = await resp.json();

            if (!res.success) {
                Swal.fire('Error', res.error, 'error');
                return;
            }

            resumenActual = res.resumen;
            hayProcesables = (res.resumen.ok + res.resumen.sin_cambio) > 0;

            $('#empty-state-ajuste').addClass('d-none');
            $('#panel-resultado').removeClass('d-none');
            renderResumen(res.resumen);
            renderPreview(res.preview, res.truncado);

            $('#txt-nombre-ajuste').val('AJUSTE EXCEL - ' + res.archivo);
            $('#btn-confirmar-ajuste').prop('disabled', !hayProcesables);

            if (!hayProcesables) {
                Swal.fire({ icon: 'warning', title: 'Sin cambios para aplicar', text: 'Ningún código de la planilla coincide con productos válidos del sistema.', toast: true, position: 'top-end', timer: 3500, showConfirmButton: false });
            }
        } catch (err) {
            Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
        } finally {
            $('#loading-ajuste').hide();
        }
    });

    $('#btn-confirmar-ajuste').click(async function () {
        if (!hayProcesables) return;

        const result = await Swal.fire({
            title: '¿Confirmar ajuste de stock?',
            html: `Se aplicará el ajuste a <b>${resumenActual.ok}</b> producto(s) con movimiento y <b>${resumenActual.sin_cambio}</b> sin cambios. Esta acción no se puede deshacer.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, aplicar ajuste',
            cancelButtonText: 'Cancelar'
        });
        if (!result.isConfirmed) return;

        Swal.fire({ title: 'Aplicando ajuste...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

        try {
            const resp = await fetch('api_stock_ajuste_excel.php?action=process', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({
                    nombre: $('#txt-nombre-ajuste').val(),
                    observacion: $('#txt-observacion-ajuste').val(),
                    fiscal: $('#chk-fiscal').is(':checked') ? 1 : 0,
                    id_deposito: $('#cmb-deposito-excel').val() || null
                })
            });
            const res = await resp.json();

            if (res.success) {
                let html = res.message;
                if (res.errores && res.errores.length) {
                    html += '<br><br><strong>Avisos:</strong><br>' + res.errores.slice(0, 5).join('<br>') + (res.errores.length > 5 ? '<br>...' : '');
                }
                await Swal.fire({ title: 'Ajuste aplicado', html, icon: res.errores && res.errores.length ? 'warning' : 'success' });
                resetFormulario();
            } else {
                Swal.fire('Error', res.error, 'error');
            }
        } catch (err) {
            Swal.fire('Error', 'No se pudo aplicar el ajuste.', 'error');
        }
    });

    $('#btn-cancelar-ajuste').click(async function () {
        try {
            await fetch('api_stock_ajuste_excel.php?action=discard', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        } catch (e) { /* silencioso */ }
        resetFormulario();
    });

    function resetFormulario() {
        resumenActual = null;
        hayProcesables = false;
        $('#frm-upload-ajuste')[0].reset();
        $('#panel-resultado').addClass('d-none');
        $('#empty-state-ajuste').removeClass('d-none');
        $('#tbody-preview-ajuste').empty();
        $('#txt-observacion-ajuste').val('');
    }

    cargarCombos();
})();
</script>
