<?php
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" style="letter-spacing: 1px;">AUMENTOS MASIVOS DE PRECIOS</h5>
            <small class="text-accent opacity-75">ACTUALIZACIÓN POR LOTES</small>
        </div>
        <button hidden onclick="location.reload()" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CERRAR
        </button>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card card-filtros p-4">
                
                <!-- LEYENDA DE AYUDA AL USUARIO -->
                <div class="alert alert-info border-info border-opacity-25 bg-info bg-opacity-10 text-white small mb-4 shadow-sm" style="border-left: 4px solid var(--info) !important;">
                    <h6 class="text-info fw-bold mb-2"><i class="fas fa-info-circle me-2"></i>INSTRUCCIONES DE USO</h6>
                    <ul class="mb-0 ps-3" style="list-style-type: square;">
                        <li class="mb-1"><strong>Aumentos:</strong> Ingrese el porcentaje en positivo (Ej: <code>10</code> para sumar un 10%).</li>
                        <li class="mb-1"><strong>Descuentos:</strong> Ingrese el porcentaje en negativo (Ej: <code>-5</code> para restar un 5%).</li>
                        <li><strong>Impacto:</strong> Se recalcularán Costos y Precios de Venta de los artículos seleccionados manteniendo la utilidad actual.</li>
                        <li><strong>Filtro:</strong> Aplique los filtros que considere para aumentar solo los productos que cunmplan con TODAS las condiciones seleccionadas</li>
                        <li><strong>Activar:</strong> Para activar un filtro, clickee la casilla a su lado, si esta deshabilitada, ese filtro no se aplica</li>
                    </ul>
                </div>

                <form id="frmAumento">
                    
                    <!-- Porcentaje -->
                    <div class="mb-4">
                        <label class="form-label-custom fs-6">PORCENTAJE A APLICAR %</label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control fw-bold text-center text-accent bg-dark border-secondary" 
                                   id="txtPorcentaje" name="aumento" step="0.01" placeholder="0.00" required>
                            <span class="input-group-text bg-dark border-secondary text-white">%</span>
                        </div>
                    </div>

                    <hr class="border-secondary my-4">

                    <!-- Filtros -->
                    <h6 class="text-white mb-3">FILTROS DE APLICACIÓN</h6>

                    <div class="mb-3">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="chkTodos" name="todos" onchange="toggleFiltros()">
                            <label class="form-check-label text-white fw-bold" for="chkTodos">APLICAR A TODOS LOS PRODUCTOS</label>
                        </div>
                    </div>

                    <div id="panelFiltros">
                        <!-- Proveedor -->
                        <div class="row align-items-center mb-2">
                            <div class="col-1">
                                <input class="form-check-input" type="checkbox" id="chkProv" onchange="toggleCombo('chkProv', 'cmbProv')">
                            </div>
                            <div class="col-11">
                                <label class="form-label-custom text-muted small mb-1">PROVEEDOR</label>
                                <select class="form-select select2-simple" id="cmbProv" name="proveedor" disabled>
                                    <option value="">SELECCIONE PROVEEDOR...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Rubro -->
                        <div class="row align-items-center mb-2">
                            <div class="col-1">
                                <input class="form-check-input" type="checkbox" id="chkRub" onchange="toggleCombo('chkRub', 'cmbRub')">
                            </div>
                            <div class="col-11">
                                <label class="form-label-custom text-muted small mb-1">RUBRO</label>
                                <select class="form-select select2-simple" id="cmbRub" name="rubro" disabled>
                                    <option value="">SELECCIONE RUBRO...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Familia -->
                        <div class="row align-items-center mb-2">
                            <div class="col-1">
                                <input class="form-check-input" type="checkbox" id="chkFam" onchange="toggleCombo('chkFam', 'cmbFam')">
                            </div>
                            <div class="col-11">
                                <label class="form-label-custom text-muted small mb-1">FAMILIA</label>
                                <select class="form-select select2-simple" id="cmbFam" name="familia" disabled>
                                    <option value="">SELECCIONE FAMILIA...</option>
                                </select>
                            </div>
                        </div>

                        <!-- Marca -->
                        <div class="row align-items-center mb-2">
                            <div class="col-1">
                                <input class="form-check-input" type="checkbox" id="chkMar" onchange="toggleCombo('chkMar', 'cmbMar')">
                            </div>
                            <div class="col-11">
                                <label class="form-label-custom text-muted small mb-1">MARCA</label>
                                <select class="form-select select2-simple" id="cmbMar" name="marca" disabled>
                                    <option value="">SELECCIONE MARCA...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-5">
                        <button type="submit" class="btn btn-info btn-lg fw-bold rounded-pill">
                            <i class="fas fa-check-circle me-2"></i> CONFIRMAR Y APLICAR
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        // Inicializar Select2
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

        // Función para cargar combos
        async function loadCombos() {
            try {
                const [resProv, resRub, resFam, resMar] = await Promise.all([
                    fetch('api_helpers.php?action=get_proveedores', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_rubros', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_familias', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json()),
                    fetch('api_helpers.php?action=get_marcas', { headers: {'X-Requested-With': 'XMLHttpRequest'} }).then(r=>r.json())
                ]);

                fillSelect('cmbProv', resProv.data, 'razon', 'razon'); // Value = Nombre
                fillSelect('cmbRub', resRub.data, 'rubro', 'rubro');   // Value = Nombre
                fillSelect('cmbFam', resFam.data, 'id', 'nombre');     // Value = ID
                fillSelect('cmbMar', resMar.data, 'id', 'marca');      // Value = ID

            } catch(e) { console.error(e); }
        }

        function fillSelect(id, data, valField, textField) {
            const el = $(`#${id}`);
            el.empty().append(new Option('SELECCIONE...', '', true, true));
            if(data) {
                data.forEach(d => el.append(new Option(d[textField], d[valField], false, false)));
            }
            el.val('').trigger('change');
        }

        // Lógica de UI (Checkboxes)
        window.toggleFiltros = function() {
            const todos = document.getElementById('chkTodos').checked;
            const panel = document.getElementById('panelFiltros');
            
            // Deshabilitar/Habilitar contenedor visualmente
            panel.style.opacity = todos ? '0.5' : '1';
            panel.style.pointerEvents = todos ? 'none' : 'auto';

            // Desmarcar y deshabilitar individuales si es Todos
            if (todos) {
                ['chkProv', 'chkRub', 'chkFam', 'chkMar'].forEach(id => {
                    document.getElementById(id).checked = false;
                    const cmbId = id.replace('chk', 'cmb');
                    $(`#${cmbId}`).val('').trigger('change').prop('disabled', true);
                });
            }
        }

        window.toggleCombo = function(chkId, cmbId) {
            const checked = document.getElementById(chkId).checked;
            $(`#${cmbId}`).prop('disabled', !checked);
            if(!checked) $(`#${cmbId}`).val('').trigger('change');
        }

        // Submit
        document.getElementById('frmAumento').onsubmit = async (e) => {
            e.preventDefault();
            
            const p = document.getElementById('txtPorcentaje').value;
            if(!p || p == 0) { Swal.fire('Error', 'Ingrese un porcentaje válido', 'warning'); return; }

            const todos = document.getElementById('chkTodos').checked;
            const data = {
                aumento: p,
                todos: todos,
                proveedor: todos ? null : $('#cmbProv').val(),
                rubro: todos ? null : $('#cmbRub').val(),
                familia: todos ? null : $('#cmbFam').val(),
                marca: todos ? null : $('#cmbMar').val()
            };

            // Validación filtros
            if (!todos && !data.proveedor && !data.rubro && !data.familia && !data.marca) {
                Swal.fire('Atención', 'Debe seleccionar al menos un filtro o marcar "Todos"', 'warning');
                return;
            }

            const confirm = await Swal.fire({
                title: '¿Confirmar Aumento?',
                text: `Se aplicará un ${p}% a los productos seleccionados. Esta acción modificará costos y precios de venta.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#00ffff',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<span style="color:#000; font-weight:bold">SÍ, APLICAR</span>',
                background: '#001020', color: '#fff'
            });

            if (confirm.isConfirmed) {
                try {
                    const resp = await fetch('api_aumentos.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                        body: JSON.stringify(data)
                    });
                    const res = await resp.json();
                    
                    if(res.success) {
                        Swal.fire({
                            icon: 'success', 
                            title: 'Proceso Finalizado', 
                            text: `${res.message} Registros afectados: ${res.rows_affected}`,
                            background: '#001020', color: '#fff'
                        });
                        // Reset form
                        document.getElementById('frmAumento').reset();
                        toggleFiltros();
                    } else {
                        Swal.fire('Error', res.error, 'error');
                    }
                } catch(err) { Swal.fire('Error', 'Fallo de conexión', 'error'); }
            }
        };

        loadCombos();
    })();
</script>