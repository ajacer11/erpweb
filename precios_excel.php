<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">IMPORTACIÓN DE PRECIOS</h5>
            <small class="text-accent opacity-75">HERRAMIENTA DE ACTUALIZACIÓN MASIVA</small>
        </div>
        <button hidden onclick="location.reload()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="row">
        <!-- PASO 1: GENERAR MODELO -->
        <div class="col-md-4 mb-4">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3 border-bottom border-secondary pb-2">1. DESCARGAR PLANILLA MODELO</h6>
                    <p class="text-muted small mb-3">Seleccione qué productos desea incluir en la planilla para editar sus precios.</p>
                    
                    <div class="mb-2">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="chkTodos" name="todos" onchange="toggleFiltros()">
                            <label class="form-check-label text-white small" for="chkTodos">INCLUIR TODOS LOS PRODUCTOS</label>
                        </div>
                    </div>

                    <div id="panelFiltros">
                        <div class="mb-2">
                            <label for="">PROVEEDOR</label>
                            <select class="form-select select2-simple" id="cmbProv" ><option value="">PROVEEDOR...</option></select>
                        </div>
                        <div class="mb-2">
                            <label for="">RUBRO</label>
                            <select class="form-select select2-simple" id="cmbRub" ><option value="">RUBRO...</option></select>
                        </div>
                        <div class="mb-2">
                            <label for="">FAMILIA</label>
                            <select class="form-select select2-simple" id="cmbFam" ><option value="">FAMILIA...</option></select>
                        </div>
                        <div class="mb-3">
                            <label for="">MARCA</label>
                            <select class="form-select select2-simple" id="cmbMar" ><option value="">MARCA...</option></select>
                        </div>
                    </div>

                    <div class="d-grid">
                        <button class="btn btn-outline-info fw-bold rounded-pill" onclick="descargarModelo()">
                            <i class="fas fa-download me-2"></i> DESCARGAR .CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- PASO 2: SUBIR Y PROCESAR -->
        <div class="col-md-8 mb-4">
            <div class="card card-filtros h-100">
                <div class="card-body p-4">
                    <h6 class="text-white mb-3 border-bottom border-secondary pb-2">2. SUBIR Y PROCESAR</h6>
                    
                    <form id="frmUpload" enctype="multipart/form-data" class="mb-4">
                        <label class="form-label-custom mb-2">SELECCIONAR ARCHIVO MODIFICADO (.CSV)</label>
                        <div class="input-group">
                            <input type="file" class="form-control bg-dark border-secondary text-white" id="fileInput" name="archivo" accept=".csv" required>
                            <button class="btn btn-info fw-bold" type="submit">
                                <i class="fas fa-eye me-2"></i> PREVISUALIZAR
                            </button>
                        </div>
                    </form>

                    <!-- GRILLA DE PREVISUALIZACIÓN -->
                    <div id="panelPreview" class="d-none">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Mostrando primeros 50 registros (<span id="lblTotalRows" class="text-white">0</span> totales)</span>
                            <button class="btn btn-success btn-sm fw-bold rounded-pill px-4" onclick="iniciarProceso()">
                                <i class="fas fa-check me-2"></i> INICIAR PROCESO
                            </button>
                        </div>
                        <div class="table-responsive border border-secondary rounded" style="max-height: 400px;">
                            <table class="table table-sm table-dark table-hover small mb-0">
                                <thead class="text-accent sticky-top bg-dark" id="tableHeader"></thead>
                                <tbody id="tableBody" class="text-white-50"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div id="emptyState" class="text-center py-5 text-muted">
                        <i class="fas fa-file-csv fa-3x mb-3 opacity-50"></i>
                        <p>Suba un archivo para ver la previsualización de datos.</p>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

        // Cargar combos
        async function loadCombos() {
            try {
                const [resProv, resRub, resFam, resMar] = await Promise.all([
                    fetch('api_helpers.php?action=get_proveedores', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_rubros', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_familias', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_marcas', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json())
                ]);
                fillSelect('cmbProv', resProv.data, 'razon', 'razon');
                fillSelect('cmbRub', resRub.data, 'rubro', 'rubro');
                fillSelect('cmbFam', resFam.data, 'id', 'nombre');
                fillSelect('cmbMar', resMar.data, 'id', 'marca');
            } catch(e) { console.error(e); }
        }

        function fillSelect(id, data, valField, textField) {
            const el = $(`#${id}`);
            el.empty().append(new Option(el.find('option:first').text(), '', true, true));
            if(data) data.forEach(d => el.append(new Option(d[textField], d[valField], false, false)));
        }

        // UI Logic Filters
        window.toggleFiltros = function() {
            const todos = document.getElementById('chkTodos').checked;
            const panel = document.getElementById('panelFiltros');
            panel.style.opacity = todos ? '0.5' : '1';
            panel.style.pointerEvents = todos ? 'none' : 'auto';
            if (todos) {
                $('#cmbProv, #cmbRub, #cmbFam, #cmbMar').val('').trigger('change').prop('disabled', true);
            } else {
                $('#cmbProv, #cmbRub, #cmbFam, #cmbMar').prop('disabled', false);
            }
        }

        // Descargar Modelo
        window.descargarModelo = function() {
            const todos = document.getElementById('chkTodos').checked;
            const params = new URLSearchParams({
                action: 'download_template',
                todos: todos,
                proveedor: $('#cmbProv').val(),
                rubro: $('#cmbRub').val(),
                familia: $('#cmbFam').val(),
                marca: $('#cmbMar').val()
            });
            window.location.href = 'api_importar_precios.php?' + params.toString();
        }

        // Subir Archivo
        document.getElementById('frmUpload').onsubmit = async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const btn = e.target.querySelector('button');
            const originalText = btn.innerHTML;
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            try {
                const resp = await fetch('api_importar_precios.php?action=upload', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                });
                const res = await resp.json();

                if (res.success) {
                    mostrarPreview(res.headers, res.preview, res.total_rows);
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Fallo al subir archivo', 'error');
            } finally {
                btn.disabled = false; btn.innerHTML = originalText;
            }
        };

        function mostrarPreview(headers, data, total) {
            document.getElementById('emptyState').classList.add('d-none');
            document.getElementById('panelPreview').classList.remove('d-none');
            
            const thead = document.getElementById('tableHeader');
            const tbody = document.getElementById('tableBody');
            
            thead.innerHTML = '<tr>' + headers.map(h => `<th class="text-nowrap">${h}</th>`).join('') + '</tr>';
            tbody.innerHTML = data.map(row => {
                return '<tr>' + headers.map(h => `<td class="text-nowrap">${row[h] || ''}</td>`).join('') + '</tr>';
            }).join('');

            document.getElementById('lblTotalRows').innerText = total;
        }

        // Proceso en 2 Pasos: Backup -> Update
        window.iniciarProceso = async function() {
            // Paso 1: Preguntar Backup
            const step1 = await Swal.fire({
                title: 'Copia de Seguridad',
                text: "Antes de aplicar cambios, ¿desea descargar una copia de los valores actuales?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#00ffff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<span style="color:#000; font-weight:bold">SÍ, Descargar Backup</span>',
                cancelButtonText: 'No, continuar sin backup',
                background: '#001020', color: '#fff'
            });

            if (step1.isConfirmed) {
                // Descargar Backup
                window.location.href = 'api_importar_precios.php?action=backup';
                
                // Esperar confirmación de usuario para seguir
                const step2 = await Swal.fire({
                    title: 'Backup Descargado',
                    text: "¿Desea proceder ahora con la actualización de precios?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Sí, aplicar cambios',
                    background: '#001020', color: '#fff'
                });

                if(step2.isConfirmed) ejecutarUpdate();

            } else if (step1.dismiss === Swal.DismissReason.cancel) {
                // Eligió "No, continuar sin backup"
                // Pedir confirmación final
                const step3 = await Swal.fire({
                    title: '¿Está seguro?',
                    text: "Se modificarán los precios SIN respaldo previo.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Sí, modificar',
                    background: '#001020', color: '#fff'
                });
                
                if(step3.isConfirmed) ejecutarUpdate();
            }
        }

        async function ejecutarUpdate() {
            Swal.fire({title: 'Procesando...', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff', allowOutsideClick: false});
            
            try {
                const resp = await fetch('api_importar_precios.php?action=process', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                
                if (res.success) {
                    Swal.fire({title: 'Éxito', text: res.message, icon: 'success', background: '#001020', color: '#fff'});
                    document.getElementById('panelPreview').classList.add('d-none');
                    document.getElementById('emptyState').classList.remove('d-none');
                    document.getElementById('frmUpload').reset();
                } else {
                    Swal.fire('Error', res.error, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Fallo al procesar', 'error');
            }
        }

        loadCombos();
    })();
</script>