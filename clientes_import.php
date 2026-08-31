<?php
/**
 * clientes_import.php - INTERFAZ DE IMPORTACIÓN MASIVA DE CLIENTES
 */
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">ALTA MASIVA DE CLIENTES</h5>
            <small class="text-accent opacity-75">IMPORTADOR DE PADRÓN DESDE EXCEL/CSV</small>
        </div>
        <button onclick="loadModule('clientes_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="row">
        <!-- Panel Izquierdo: Acciones -->
        <div class="col-md-4 mb-4">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-file-download me-2 text-info"></i>1. OBTENER MODELO</h6>
                    <p class="text-muted small">Descargue la plantilla base. Asegúrese de respetar los nombres de las columnas y el formato de las condiciones fiscales.</p>
                    <a href="api_importar_clientes.php?action=download_template" class="btn btn-outline-info w-100 fw-bold rounded-pill mb-4">
                        DESCARGAR PLANTILLA
                    </a>

                    <hr class="border-secondary">

                    <h6 class="text-white mb-3"><i class="fas fa-upload me-2 text-warning"></i>2. SUBIR ARCHIVO</h6>
                    <form id="frmUploadCli">
                        <div class="mb-3">
                            <input type="file" class="form-control bg-dark border-secondary text-white" name="archivo" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill text-dark" id="btnPreview">
                            PREVISUALIZAR Y VALIDAR
                        </button>
                    </form>
                    
                    <div class="mt-4 p-3 rounded bg-dark border border-secondary small text-muted">
                        <i class="fas fa-info-circle text-info me-1"></i> <b>Reglas de Validación:</b>
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Razón Social es obligatoria.</li>
                            <li>Condición Fiscal debe ser válida (Monotributo, etc).</li>
                            <li>Si no es Consumidor Final, el CUIT es obligatorio.</li>
                            <li>No se permiten CUITs duplicados.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Previsualización -->
        <div class="col-md-8">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-white mb-0">3. VISTA PREVIA (<span id="lblCount">0</span>)</h6>
                        <button id="btnProcesar" class="btn btn-success btn-sm fw-bold rounded-pill px-4" onclick="procesarImportacion()" disabled>
                            <i class="fas fa-check-circle me-2"></i> INICIAR IMPORTACIÓN
                        </button>
                    </div>

                    <!-- Estado Vacío -->
                    <div id="emptyState" class="text-center py-5 text-muted">
                        <i class="fas fa-users-cog fa-3x mb-3 opacity-25"></i>
                        <p>Cargue un archivo para validar la integridad de los datos.</p>
                    </div>

                    <!-- Grilla de Validación -->
                    <div id="gridContainer" class="table-responsive d-none border border-secondary rounded" style="max-height: 550px;">
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

        document.getElementById('frmUploadCli').onsubmit = async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnPreview');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validando...';

            const formData = new FormData(e.target);

            try {
                const resp = await fetch('api_importar_clientes.php?action=upload', {
                    method: 'POST',
                    headers: commonHeaders,
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    mostrarPreview(res.headers, res.preview, res.total, res.has_errors);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch(err) {
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        };

        function mostrarPreview(headers, data, total, hasErrors) {
            document.getElementById('emptyState').classList.add('d-none');
            document.getElementById('gridContainer').classList.remove('d-none');
            document.getElementById('lblCount').innerText = total;
            
            // Solo habilitamos si no hay errores críticos de estructura o datos
            document.getElementById('btnProcesar').disabled = hasErrors;

            if(hasErrors) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Errores detectados',
                    text: 'Se encontraron inconsistencias en el archivo. Corrija las celdas resaltadas en rojo.',
                    background: '#001020', color: '#fff'
                });
            }

            const thead = document.getElementById('gridHeader');
            const tbody = document.getElementById('gridBody');

            thead.innerHTML = '<tr>' + headers.map(h => `<th class="text-nowrap">${h}</th>`).join('') + '</tr>';
            
            tbody.innerHTML = data.map(row => {
                let trClass = row._row_error ? 'style="background: rgba(220,53,69,0.1)"' : '';
                return `<tr ${trClass}>` + headers.map(h => {
                    let cellError = (row._errors && row._errors[h]) ? 'class="bg-danger bg-opacity-50 text-white"' : '';
                    return `<td ${cellError} class="text-nowrap">${row[h] || ''}</td>`;
                }).join('') + '</tr>';
            }).join('');
        }

        window.procesarImportacion = async function() {
            const result = await Swal.fire({
                title: '¿Confirmar Importación?',
                text: "Los clientes validados se insertarán en la base de datos central.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: 'var(--success)',
                confirmButtonText: 'Sí, importar ahora',
                background: '#001020', color: '#fff'
            });

            if (result.isConfirmed) {
                Swal.fire({title: 'Procesando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false});

                try {
                    const resp = await fetch('api_importar_clientes.php?action=process', {
                        method: 'POST',
                        headers: commonHeaders
                    });
                    const res = await resp.json();

                    if (res.success) {
                        Swal.fire({
                            title: '¡Éxito!',
                            text: res.message,
                            icon: 'success',
                            background: '#001020', color: '#fff'
                        });
                        // Limpiar vista
                        document.getElementById('gridContainer').classList.add('d-none');
                        document.getElementById('emptyState').classList.remove('d-none');
                        document.getElementById('btnProcesar').disabled = true;
                        document.getElementById('frmUploadCli').reset();
                        document.getElementById('lblCount').innerText = '0';
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch(err) {
                    Swal.fire('Error', 'No se pudo completar la operación masiva.', 'error');
                }
            }
        }
    })();
</script>