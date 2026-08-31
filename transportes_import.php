<?php
/**
 * transportes_import.php - INTERFAZ DE IMPORTACIÓN MASIVA DE TRANSPORTES
 */
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">IMPORTACIÓN DE TRANSPORTES</h5>
            <small class="text-accent opacity-75">ALTA MASIVA DESDE ARCHIVOS CSV / EXCEL</small>
        </div>
        <button onclick="loadModule('transportes_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="row">
        <!-- Panel de Carga -->
        <div class="col-md-4 mb-4">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-file-download me-2 text-info"></i>1. DESCARGAR PLANTILLA</h6>
                    <p class="text-muted small">Descargue el modelo para organizar los datos de sus transportistas. No elimine ni cambie el orden de las columnas.</p>
                    <a href="api_transportes_import.php?action=download_template" class="btn btn-outline-info w-100 fw-bold rounded-pill mb-4 shadow-sm">
                        DESCARGAR MODELO CSV
                    </a>

                    <hr class="border-secondary my-4">

                    <h6 class="text-white mb-3"><i class="fas fa-upload me-2 text-warning"></i>2. SUBIR LISTADO</h6>
                    <form id="frmUploadTransp">
                        <div class="mb-3">
                            <input type="file" class="form-control bg-dark border-secondary text-white" name="archivo" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill text-dark shadow" id="btnPreview">
                            VALIDAR ARCHIVO
                        </button>
                    </form>
                    
                    <div class="mt-4 p-3 rounded bg-dark bg-opacity-50 border border-secondary small text-muted">
                        <i class="fas fa-exclamation-triangle text-warning me-1"></i> <b>Reglas de Negocio:</b>
                        <ul class="mb-0 mt-1 ps-3">
                            <li><b>Razón Social</b> y <b>Documento (CUIT/DNI)</b> son obligatorios.</li>
                            <li>Si el documento ya existe en el sistema, el registro será ignorado.</li>
                            <li>Los nombres de calles y localidades se capitalizan automáticamente.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Resultados -->
        <div class="col-md-8">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-white mb-0">3. VISTA PREVIA DE DATOS (<span id="lblCount">0</span>)</h6>
                        <button id="btnProcesar" class="btn btn-success btn-sm fw-bold rounded-pill px-4 shadow" onclick="procesarImportacion()" disabled>
                            <i class="fas fa-cloud-upload-alt me-2"></i> IMPORTAR TRANSPORTES
                        </button>
                    </div>

                    <div id="emptyState" class="text-center py-5 text-muted opacity-50">
                        <i class="fas fa-truck fa-3x mb-3"></i>
                        <p>Seleccione un archivo CSV para previsualizar los registros antes de procesarlos.</p>
                    </div>

                    <div id="gridContainer" class="table-responsive d-none border border-secondary rounded shadow-sm" style="max-height: 550px;">
                        <table class="table table-sm table-dark table-hover small mb-0">
                            <thead class="text-accent bg-dark sticky-top" id="gridHeader"></thead>
                            <tbody id="gridBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const commonHeaders = {'X-Requested-With': 'XMLHttpRequest'};

        document.getElementById('frmUploadTransp').onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnPreview');
            const originalText = btn.innerHTML;
            btn.disabled = true; 
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>PROCESANDO...';

            const formData = new FormData(e.target);

            try {
                const resp = await fetch('api_transportes_import.php?action=upload', {
                    method: 'POST',
                    headers: commonHeaders,
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    renderPreview(res.headers, res.preview, res.total, res.has_errors);
                } else {
                    Swal.fire({ title: 'Atención', text: res.error, icon: 'warning', background: '#001020', color: '#fff' });
                }
            } catch(err) {
                Swal.fire({ title: 'Error', text: 'Error de conexión con el servidor.', icon: 'error', background: '#001020', color: '#fff' });
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        };

        function renderPreview(headers, data, total, hasErrors) {
            document.getElementById('emptyState').classList.add('d-none');
            document.getElementById('gridContainer').classList.remove('d-none');
            document.getElementById('lblCount').innerText = total;
            document.getElementById('btnProcesar').disabled = hasErrors || total === 0;

            if(hasErrors) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Datos Inválidos',
                    text: 'Se encontraron filas con errores (marcadas en rojo). Corrija el archivo y vuelva a subirlo.',
                    background: '#001020', color: '#fff'
                });
            }

            const thead = document.getElementById('gridHeader');
            const tbody = document.getElementById('gridBody');

            thead.innerHTML = '<tr>' + headers.map(h => `<th class="text-nowrap p-2">${h}</th>`).join('') + '</tr>';
            
            tbody.innerHTML = data.map(row => {
                let trStyle = row._row_error ? 'background: rgba(220,53,69,0.15)' : '';
                return `<tr style="${trStyle}">` + headers.map(h => {
                    let cellClass = (row._errors && row._errors[h]) ? 'bg-danger bg-opacity-25 text-warning fw-bold' : '';
                    return `<td class="text-nowrap p-2 ${cellClass}">${row[h] || ''}</td>`;
                }).join('') + '</tr>';
            }).join('');
        }

        window.procesarImportacion = async function() {
            const confirm = await Swal.fire({
                title: '¿Confirmar Importación?',
                text: "Se darán de alta los transportes validados en el sistema.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, importar ahora',
                cancelButtonText: 'Cancelar',
                background: '#001020', color: '#fff'
            });

            if (confirm.isConfirmed) {
                Swal.fire({title: 'Importando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false});

                try {
                    const resp = await fetch('api_transportes_import.php?action=process', {
                        method: 'POST',
                        headers: commonHeaders
                    });
                    const res = await resp.json();

                    if (res.success) {
                        await Swal.fire({ title: '¡Completado!', text: res.message, icon: 'success', background: '#001020', color: '#fff' });
                        loadModule('transportes_admin.php');
                    } else {
                        Swal.fire({ title: 'Error', text: res.error, icon: 'error', background: '#001020', color: '#fff' });
                    }
                } catch(err) {
                    Swal.fire({ title: 'Error', text: 'Error crítico durante el procesamiento.', icon: 'error', background: '#001020', color: '#fff' });
                }
            }
        }
    })();
</script>