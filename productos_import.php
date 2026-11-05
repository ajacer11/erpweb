<?php
// SEGURIDAD
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">ALTA MASIVA DE PRODUCTOS</h5>
            <small class="text-accent opacity-75">IMPORTADOR INTELIGENTE</small>
        </div>
        <button onclick="loadModule('productos_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="row">
        <!-- Panel Izquierdo: Acciones -->
        <div class="col-md-4 mb-4">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3"><i class="fas fa-file-download me-2 text-info"></i>1. OBTENER MODELO</h6>
                    <p class="text-muted small">Descargue la plantilla CSV. Contiene ejemplos de cómo cargar Marcas, Rubros y Costos.</p>
                    <a href="api_importar_productos.php?action=download_template" class="btn btn-outline-info w-100 fw-bold rounded-pill mb-4">
                        DESCARGAR PLANTILLA
                    </a>

                    <hr class="border-secondary">

                    <h6 class="text-white mb-3"><i class="fas fa-upload me-2 text-warning"></i>2. SUBIR ARCHIVO</h6>
                    <form id="frmUploadProd">
                        <div class="mb-3">
                            <input type="file" class="form-control bg-dark border-secondary text-white" name="archivo" accept=".csv" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100 fw-bold rounded-pill text-dark">
                            PREVISUALIZAR DATOS
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panel Derecho: Previsualización -->
        <div class="col-md-8">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="text-white mb-0">3. VISTA PREVIA (<span id="lblCount">0</span>)</h6>
                        <button id="btnProcesar" class="btn btn-success btn-sm fw-bold rounded-pill px-4" onclick="procesarAlta()" disabled>
                            <i class="fas fa-check-circle me-2"></i> CONFIRMAR IMPORTACIÓN
                        </button>
                    </div>

                    <!-- Formato numerico detectado en el archivo -->
                    <div id="avisoFormato" class="alert alert-info py-2 px-3 mb-3 d-none">
                        <i class="fas fa-calculator me-2"></i>
                        Formato numérico detectado: <strong id="lblFormato">-</strong>.
                        <span class="d-block small mt-1 opacity-75">
                            Verifique los importes en la vista previa antes de confirmar.
                            Si el formato no es el correcto, corrija el archivo y vuelva a subirlo.
                        </span>
                    </div>

                    <!-- Estado Vacío -->
                    <div id="emptyState" class="text-center py-5 text-muted">
                        <i class="fas fa-boxes fa-3x mb-3 opacity-25"></i>
                        <p>Suba un archivo para validar los datos antes de importar.</p>
                        <small class="d-block mt-2 text-info">
                            <i class="fas fa-info-circle me-1"></i> Si las Marcas o Rubros no existen, el sistema las creará automáticamente.
                        </small>
                    </div>

                    <!-- Grilla -->
                    <div id="gridContainer" class="table-responsive d-none border border-secondary rounded" style="max-height: 500px;">
                        <table class="table table-sm table-dark table-hover small mb-0 font-monospace">
                            <thead class="text-accent bg-dark sticky-top" id="gridHeader"></thead>
                            <tbody id="gridBody" class="text-white-50"></tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        
        document.getElementById('frmUploadProd').onsubmit = async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cargando...';

            const formData = new FormData(e.target);

            try {
                const resp = await fetch('api_importar_productos.php?action=upload', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    mostrarPreview(res.headers, res.preview, res.total, res.formato);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch(err) {
                Swal.fire('Error', 'Fallo de conexión', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        };

        function mostrarPreview(headers, data, total, formato) {
            document.getElementById('emptyState').classList.add('d-none');
            document.getElementById('gridContainer').classList.remove('d-none');
            document.getElementById('btnProcesar').disabled = false;
            document.getElementById('lblCount').innerText = total;

            const aviso = document.getElementById('avisoFormato');
            if (formato) {
                document.getElementById('lblFormato').innerText = formato;
                aviso.classList.remove('d-none');
            } else {
                aviso.classList.add('d-none');
            }

            const thead = document.getElementById('gridHeader');
            const tbody = document.getElementById('gridBody');

            thead.innerHTML = '<tr>' + headers.map(h => `<th class="text-nowrap">${h}</th>`).join('') + '</tr>';
            tbody.innerHTML = data.map(row => {
                return '<tr>' + headers.map(h => `<td class="text-nowrap">${row[h] || ''}</td>`).join('') + '</tr>';
            }).join('');
        }

        window.procesarAlta = async function() {
            const result = await Swal.fire({
                title: '¿Confirmar Importación?',
                text: "Se darán de alta los productos. Las Marcas/Rubros nuevos se crearán automáticamente.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                confirmButtonText: 'Sí, importar',
                background: '#001020', color: '#fff'
            });

            if (result.isConfirmed) {
                Swal.fire({title: 'Importando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false});

                try {
                    const resp = await fetch('api_importar_productos.php?action=process', {
                        method: 'POST',
                        headers: {'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const res = await resp.json();

                    if (res.success) {
                        let msg = res.message;
                        if(res.errors && res.errors.length > 0) {
                            msg += "<br><br><strong>Errores encontrados:</strong><br>" + res.errors.slice(0, 5).join('<br>') + (res.errors.length > 5 ? '<br>...' : '');
                        }
                        
                        Swal.fire({
                            title: 'Proceso Finalizado',
                            html: msg,
                            icon: res.errors && res.errors.length > 0 ? 'warning' : 'success',
                            background: '#001020', color: '#fff'
                        });

                        // Reset
                        document.getElementById('gridContainer').classList.add('d-none');
                        document.getElementById('emptyState').classList.remove('d-none');
                        document.getElementById('btnProcesar').disabled = true;
                        document.getElementById('frmUploadProd').reset();
                        document.getElementById('lblCount').innerText = '0';

                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch(err) {
                    Swal.fire('Error fatal', 'No se pudo completar la importación.', 'error');
                }
            }
        }

    })();
</script>