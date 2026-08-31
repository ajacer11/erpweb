<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$codigo = $_GET['codigo'] ?? '';
// Título inicial
$titulo = $codigo ? "CARGANDO " . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . "..." : "NUEVO PRODUCTO";
?>

<style>
    .prod-foto-box {
        width: 74px; height: 74px; border-radius: 12px; flex-shrink: 0;
        background: var(--input-bg); border: 1px solid var(--input-border);
        display: flex; align-items: center; justify-content: center;
        cursor: pointer; position: relative; overflow: hidden;
    }
    .prod-foto-box.disabled { cursor: not-allowed; opacity: .5; }
    .prod-foto-box img { width: 100%; height: 100%; object-fit: cover; }
    .prod-foto-box i.fa-camera { font-size: 22px; color: var(--text-muted); }
    .prod-foto-overlay {
        position: absolute; inset: 0; background: rgba(0,0,0,.55); color: #fff;
        font-size: 8px; font-weight: 800; display: flex; align-items: center; justify-content: center;
        opacity: 0; transition: .15s;
    }
    .prod-foto-box:hover .prod-foto-overlay { opacity: 1; }
</style>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex align-items-center gap-3">
            <div class="prod-foto-box" id="prodFotoBox" title="Subir/cambiar foto">
                <img id="prodFotoImg" src="" style="display:none;" alt="Foto del producto">
                <i class="fas fa-camera" id="prodFotoIcon"></i>
                <div class="prod-foto-overlay"><i class="fas fa-upload me-1"></i>CAMBIAR</div>
            </div>
            <input type="file" id="inpFoto" accept="image/*" style="display:none;">
            <div>
                <!-- AGREGADO id="tituloModulo" -->
                <h4 class="text-white mb-0" id="tituloModulo"><?= $titulo ?></h4>
                <small class="text-accent fw-bold opacity-75">SISTEMA DE GESTIÓN DE ARTÍCULOS</small>
                <div class="small text-muted" id="fotoHint" style="display:none;">Guardá el producto para poder subirle una foto.</div>
            </div>
        </div>
        <button onclick="loadModule('productos_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> VOLVER AL LISTADO
        </button>
    </div>

    <form id="frmProducto">
        <input type="hidden" name="is_edit" id="is_edit_flag" value="<?= $codigo ? '1' : '0' ?>">

        <ul class="nav nav-tabs nav-tabs-custom" role="tablist">
            <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#tab-gen">DATOS GENERALES</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-pre" onclick="calcular()">PRECIOS Y COSTOS</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-con">CONTABILIDAD</button></li>
            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-cat">CATÁLOGO ONLINE</button></li>
            <!-- NUEVA SOLAPA REFERENCIAS -->
            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-ref">REFERENCIAS</button></li>
        </ul>

        <div class="tab-content card card-filtros p-4 border-top-0 mb-4">
            
            <!-- DATOS GENERALES -->
            <div class="tab-pane fade show active" id="tab-gen">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label-custom">Código</label>
                        <input type="text" class="form-control fw-bold" name="codigo" id="prod_codigo" required <?= $codigo ? 'readonly' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Descripción</label>
                        <input type="text" class="form-control" name="descripcion" id="prod_desc" required placeholder="EJ: ALFAJOR CHOCOLATE X 50GR">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label-custom">Tipo</label>
                        <select class="form-select" name="tipo_producto">
                            <option value="1">PRODUCTO TERMINADO</option>
                            <option value="2">MATERIA PRIMA</option>
                            <option value="3">SERVICIO</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                         <label class="form-label-custom">Estado</label>
                         <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" name="habilitado" value="1" id="chkHab" checked>
                            <label class="text-white small ms-2" for="chkHab">HABILITADO</label>
                        </div>
                    </div>

                    <div class="col-md-4"><label class="form-label-custom">Rubro</label><select class="form-select select2-simple" name="rubro" id="cmbRubros"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Marca</label><select class="form-select select2-simple" name="marca" id="cmbMarcas"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Familia</label><select class="form-select select2-simple" name="familia" id="cmbFamilias"></select></div>
                    <div class="col-md-6">
                        <label class="form-label-custom">Proveedor Principal</label>
                        <select class="form-select select2-simple" name="proveedor" id="cmbProveedores"></select>
                        <input type="hidden" name="proveedorID" id="h_proveedorID">
                    </div>
                    <div class="col-md-3"><label class="form-label-custom">Código Prov.</label><input type="text" class="form-control" name="codigoproveedor"></div>
                    <div class="col-md-3">
                        <label class="form-label-custom">Alicuota IVA</label>
                        <select class="form-select" name="idiva" id="prod_idiva" onchange="calcular()">
                            <option value="5" data-iva="21">21.00%</option>
                            <option value="4" data-iva="10.5">10.50%</option>
                            <option value="6" data-iva="27">27.00%</option>
                            <option value="3" data-iva="0">0.00%</option>
                            <option value="8" data-iva="5">5.00%</option>
                            <option value="9" data-iva="2.5">2.50%</option>
                        </select>
                        <input type="hidden" name="poriva" id="prod_poriva_val" value="21">
                    </div>
                    <div class="col-md-2"><label class="form-label-custom">Stk Mínimo</label><input type="number" class="form-control text-center" name="StockMin" value="0" step="0.01"></div>
                    <div class="col-md-2"><label class="form-label-custom">Stk Ideal</label><input type="number" class="form-control text-center" name="stockmaximo" value="0" step="0.01"></div>
                    <div class="col-md-2"><label class="form-label-custom">UxBulto</label><input type="number" class="form-control text-center" name="uniporbulto" value="1"></div>
                    
                    <div class="col-md-6 d-flex align-items-end gap-3 pb-1 justify-content-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="controlastock" value="1" id="chkCtrlStock" checked>
                            <label class="text-white small" for="chkCtrlStock">CONTROL STOCK</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PRECIOS -->
            <div class="tab-pane fade" id="tab-pre">
                <div class="row g-4">
                    <div class="col-md-3 border-end border-secondary">
                        
                        <!-- SELECTOR DE MONEDA -->
                        <div class="mb-3 p-2 rounded" style="background: rgba(255,255,255,0.05);">
                            <label class="form-label-custom text-accent">MONEDA DE REFERENCIA</label>
                            <div class="d-flex gap-2">
                                <select class="form-select select2-simple" name="idmoneda" id="cmbMoneda" onchange="actualizarCotizacion()"></select>
                                <input type="number" id="txtCotizacion" class="form-control text-end" value="1" style="width: 80px;" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-custom">Neto Costo Compra</label>
                            <input type="number" class="form-control fw-bold border-info text-end" name="sinivacosto" id="costo_neto" step="0.001" oninput="calcular()">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Impuestos Int.</label>
                            <input type="number" class="form-control text-end" name="impint" id="costo_ii" step="0.001" oninput="calcular()">
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label-custom">IVA Costo</label><input type="text" class="form-control bg-dark bg-opacity-50 text-end" name="ivacosto" id="costo_iva" readonly></div>
                            <div class="col-6"><label class="form-label-custom">Costo Final</label><input type="text" class="form-control fw-bold text-accent text-end" name="preciocosto" id="costo_final" readonly></div>
                        </div>
                        
                        <!-- SELECTOR DE MODO DE CÁLCULO -->
                        <div class="mt-4 pt-3 border-top border-secondary">
                            <label class="form-label-custom text-accent mb-2">MÉTODO DE ACTUALIZACIÓN</label>
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="calc_mode" value="lista" id="r_lista" checked onchange="toggleCalcMode()">
                                    <label class="form-check-label text-white small" for="r_lista">Por Listas Predeterminadas</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="calc_mode" value="final" id="r_final" onchange="toggleCalcMode()">
                                    <label class="form-check-label text-white small" for="r_final">Por Precio Venta Final</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="calc_mode" value="neto" id="r_neto" onchange="toggleCalcMode()">
                                    <label class="form-check-label text-white small" for="r_neto">Por Precio Venta Neto</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="calc_mode" value="manual" id="r_manual" onchange="toggleCalcMode()">
                                    <label class="form-check-label text-white small" for="r_manual">Porcentaje Manual</label>
                                </div>
                            </div>
                            <div class="mt-2 text-warning small fst-italic" id="lblNotaCalc"></div>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="table-responsive">
                            <table class="table table-sm small align-middle">
                                <thead>
                                    <tr>
                                        <th>LISTA</th>
                                        <th class="text-center" width="12%">% UTIL</th>
                                        <th class="text-center" width="12%">PRED. %</th>
                                        <th class="text-center" width="18%">NETO VTA</th>
                                        <th class="text-center" width="15%">IVA</th>
                                        <th class="text-center" width="15%">IIBB</th>
                                        <th class="text-end" width="18%">P. FINAL</th>
                                    </tr>
                                </thead>
                                <tbody id="grillaPrecios">
                                    <?php for($i=1; $i<=8; $i++): ?>
                                    <tr>
                                        <td class="fw-bold text-muted pt-2">LISTA <?=$i?></td>
                                        
                                        <!-- Utilidad Calculada -->
                                        <td><input type="text" class="form-control form-control-sm text-center border-0 bg-transparent text-accent fw-bold" id="por_l<?=$i?>" readonly></td>
                                        
                                        <!-- Utilidad Predeterminada -->
                                        <td><input type="number" class="form-control form-control-sm text-center" id="util_l<?=$i?>" step="0.01" oninput="calcular(<?=$i?>)"></td>
                                        
                                        <!-- Neto Venta -->
                                        <td><input type="number" class="form-control form-control-sm text-center" name="siniva<?=$i==1?'':$i?>" id="neto_l<?=$i?>" step="0.001" oninput="calcular(<?=$i?>)"></td>
                                        
                                        <!-- IVA Venta -->
                                        <td><input type="text" class="form-control form-control-sm text-center border-0 bg-transparent text-muted" name="iva<?=$i==1?'1':$i?>" id="iva_l<?=$i?>" readonly></td>
                                        
                                        <!-- IIBB -->
                                        <td><input type="text" class="form-control form-control-sm text-center border-0 bg-transparent text-muted" name="IIBB<?=$i?>" id="iibb_l<?=$i?>" readonly></td>
                                        
                                        <!-- Precio Final -->
                                        <td><input type="number" class="form-control form-control-sm text-end fw-bold text-accent" name="precio<?=$i==1?'':$i?>" id="final_l<?=$i?>" step="0.001" oninput="calcular(<?=$i?>)"></td>
                                    </tr>
                                    <?php endfor; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CONTABILIDAD -->
            <div class="tab-pane fade" id="tab-con">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label-custom">Cuenta Ventas</label><select class="form-select select2-simple" name="cuentaventas" id="cmbCuentaVenta"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Cuenta Costo</label><select class="form-select select2-simple" name="cuentacosto" id="cmbCuentaCosto"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Cuenta Mercaderías</label><select class="form-select select2-simple" name="cuentamercaderia" id="cmbCuentaMercaderia"></select></div>
                </div>
            </div>

            <!-- CATÁLOGO -->
            <div class="tab-pane fade" id="tab-cat">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="mostrarencatalogo" value="1" id="pubWeb"><label class="text-white small" for="pubWeb">Publicar en West IT Online</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="mostrarsinstock" value="1" id="shwStock"><label class="text-white small" for="shwStock">Permitir pedidos sin stock</label></div>
                    </div>
                </div>
            </div>

            <!-- REFERENCIAS (NUEVO) -->
            <div class="tab-pane fade" id="tab-ref">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-accent mb-0">CÓDIGOS ADICIONALES / REFERENCIAS</h6>
                    <button type="button" class="btn btn-info btn-sm fw-bold" onclick="agregarReferencia()"><i class="fas fa-plus me-2"></i>AGREGAR REFERENCIA</button>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover table-sm">
                        <thead>
                            <tr>
                                <th>CÓDIGO DE REFERENCIA</th>
                                <th class="text-end" width="100">ACCIONES</th>
                            </tr>
                        </thead>
                        <tbody id="listaReferenciasBody">
                            <!-- Se llena con JS -->
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-info mt-3 small py-2">
                    <i class="fas fa-info-circle me-2"></i> Estos códigos funcionan como alias para encontrar este producto en búsquedas y ventas.
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <button type="submit" class="btn btn-info px-5 py-2 fw-bold text-uppercase" style="letter-spacing: 2px;">
                <i class="fas fa-save me-2"></i><?= $codigo ? "GUARDAR CAMBIOS" : "REGISTRAR PRODUCTO" ?>
            </button>
        </div>
    </form>
</div>

<script>
    (function() {
        $('.select2-simple').select2({ dropdownParent: $('#main-content'), width: '100%' });

        const prodCodigo = <?= json_encode($codigo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
        let isEditing = false;

        // --- FOTO DEL PRODUCTO ---
        // Solo se puede subir si el producto ya existe (necesita un código real
        // grabado en alb_productos, que es lo que valida api_productos_foto.php).
        function setupFotoBox() {
            const $box = $('#prodFotoBox');
            const $img = $('#prodFotoImg');
            const $icon = $('#prodFotoIcon');

            if (!prodCodigo) {
                $box.addClass('disabled');
                $('#fotoHint').show();
                return;
            }

            $img.attr('src', `img_producto.php?codigo=${encodeURIComponent(prodCodigo)}&v=${Date.now()}`)
                .off('error').on('error', function () { $(this).hide(); $icon.show(); })
                .off('load').on('load', function () { $(this).show(); $icon.hide(); })
                .show();

            $box.off('click').on('click', () => document.getElementById('inpFoto').click());
        }

        document.getElementById('inpFoto').addEventListener('change', async function () {
            const file = this.files[0];
            if (!file || !prodCodigo) return;

            const fd = new FormData();
            fd.append('codigo', prodCodigo);
            fd.append('foto', file);

            Swal.fire({ title: 'Subiendo foto...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const resp = await fetch('api_productos_foto.php?action=upload', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                });
                const res = await resp.json();
                if (res.success) {
                    $('#prodFotoImg').attr('src', res.url).show();
                    $('#prodFotoIcon').hide();
                    Swal.fire({ icon: 'success', title: 'Foto actualizada', toast: true, position: 'top-end', timer: 1800, showConfirmButton: false });
                } else {
                    Swal.fire('Error', res.error || 'No se pudo subir la foto.', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            } finally {
                this.value = '';
            }
        });
        // -----------------------------

        window.toggleCalcMode = function() {
            const mode = document.querySelector('input[name="calc_mode"]:checked').value;
            const lbl = document.getElementById('lblNotaCalc');
            const allUtil = document.querySelectorAll('[id^="util_l"]');
            const allNeto = document.querySelectorAll('[id^="neto_l"]');
            const allFinal = document.querySelectorAll('[id^="final_l"]');
            const setEnabled = (nodes, enabled) => nodes.forEach(n => { n.readOnly = !enabled; n.style.opacity = enabled ? 1 : 0.6; });

            if (mode === 'lista') {
                lbl.textContent = "Calcula automático según utilidad predefinida de listas.";
                setEnabled(allUtil, false); setEnabled(allNeto, false); setEnabled(allFinal, false);
                if (!isEditing) calcular();
            } else if (mode === 'final') {
                lbl.textContent = "Tipee Precio Final (IVA inc). Se recalcula Neto y Utilidad.";
                setEnabled(allUtil, false); setEnabled(allNeto, false); setEnabled(allFinal, true);
            } else if (mode === 'neto') {
                lbl.textContent = "Tipee Neto Venta. Se recalcula Final y Utilidad.";
                setEnabled(allUtil, false); setEnabled(allNeto, true); setEnabled(allFinal, false);
            } else if (mode === 'manual') {
                lbl.textContent = "Tipee % Utilidad manual. Se recalcula todo.";
                setEnabled(allUtil, true); setEnabled(allNeto, false); setEnabled(allFinal, false);
            }
        }

        // --- LÓGICA DE REFERENCIAS ---
        window.cargarReferencias = async function() {
            const codigo = document.getElementById('prod_codigo').value;
            if(!codigo) return;
            const tbody = document.getElementById('listaReferenciasBody');
            
            try {
                const resp = await fetch(`api_referencias.php?action=list&cod=${encodeURIComponent(codigo)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await resp.json();
                tbody.innerHTML = '';
                if(res.success && res.data) {
                    res.data.forEach(ref => {
                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold text-white">${ref.codigo}</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-link text-danger p-0" onclick="eliminarReferencia(${ref.id})">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>`;
                    });
                }
            } catch(e) { console.error(e); }
        }

        window.agregarReferencia = async function() {
            const codigoPadre = document.getElementById('prod_codigo').value;
            if(!codigoPadre) { Swal.fire('Error', 'Primero debe definir un código de producto principal', 'error'); return; }

            const { value: codigoRef } = await Swal.fire({
                title: 'Nueva Referencia',
                input: 'text',
                inputLabel: 'Ingrese el código alternativo',
                inputPlaceholder: 'Ej: COD-ALT-001',
                showCancelButton: true,
                background: '#001020', color: '#fff'
            });

            if (codigoRef) {
                try {
                    const resp = await fetch('api_referencias.php?action=add', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ codigo: codigoRef, refierea: codigoPadre })
                    });
                    const res = await resp.json();
                    if(res.success) cargarReferencias();
                    else Swal.fire('Error', res.error, 'error');
                } catch(e) { Swal.fire('Error', 'No se pudo guardar la referencia', 'error'); }
            }
        }

        window.eliminarReferencia = async function(id) {
            const result = await Swal.fire({
                title: '¿Eliminar referencia?', text: "Esta acción no se puede deshacer",
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar',
                background: '#001020', color: '#fff'
            });

            if (result.isConfirmed) {
                try {
                    const resp = await fetch('api_referencias.php?action=delete', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        body: JSON.stringify({ id: id })
                    });
                    const res = await resp.json();
                    if(res.success) cargarReferencias();
                    else Swal.fire('Error', res.error, 'error');
                } catch(e) { Swal.fire('Error', 'Error al eliminar', 'error'); }
            }
        }
        // -----------------------------

        async function init() {
            setupFotoBox();

            await Promise.allSettled([
                fetch('api_helpers.php?action=get_rubros', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => fillCombo('cmbRubros', res.data, 'rubro', 'rubro')),
                fetch('api_helpers.php?action=get_marcas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => fillCombo('cmbMarcas', res.data, 'id', 'marca')),
                fetch('api_helpers.php?action=get_familias', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => fillCombo('cmbFamilias', res.data, 'id', 'nombre')),
                fetch('api_helpers.php?action=get_proveedores', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => fillCombo('cmbProveedores', res.data, 'razon', 'razon')),
                fetch('api_helpers.php?action=get_cuentas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => {
                    const data = res.data;
                    fillCombo('cmbCuentaVenta', data, 'numcuenta', 'descripcion');
                    fillCombo('cmbCuentaCosto', data, 'numcuenta', 'descripcion');
                    fillCombo('cmbCuentaMercaderia', data, 'numcuenta', 'descripcion');
                }),
                fetch('api_helpers.php?action=get_monedas', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => {
                    fillCombo('cmbMoneda', res.data, 'id', 'nombre');
                }),
                fetch('api_helpers.php?action=get_listas_utilidad', { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(r => r.json()).then(res => {
                    if(res.success) res.data.forEach(l => {
                        const el = document.getElementById(`util_l${l.id}`);
                        if(el) {
                            el.dataset.defaultUtil = l.utilidad;
                            el.value = l.utilidad; 
                        }
                    });
                })
            ]);

            toggleCalcMode();

            if (prodCodigo) {
                isEditing = true;
                // Establecer modo por Precio Final temporalmente
                document.getElementById('r_final').checked = true;
                toggleCalcMode();
                await cargarDatos();
                // Cargar referencias DESPUÉS de cargar datos
                cargarReferencias();
                setTimeout(() => { isEditing = false; }, 500); 
            }
        }

        window.actualizarCotizacion = async function() {
            const idMoneda = document.getElementById('cmbMoneda').value;
            if(!idMoneda) return;
            try {
                const resp = await fetch(`api_helpers.php?action=get_cotizacion&id=${idMoneda}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await resp.json();
                if(res.success) {
                    document.getElementById('txtCotizacion').value = res.data.cambio || 1;
                    if (!isEditing) calcular(); 
                }
            } catch(e) { console.error("Error fetching cotizacion", e); }
        }

        function fillCombo(id, data, valField, textField) {
            const el = document.getElementById(id);
            if(!el || !data) return;
            $(el).empty().append(new Option('SELECCIONE...', '', true, true));
            data.forEach(d => {
                const opt = new Option(d[textField], d[valField], false, false);
                if(id === 'cmbProveedores') opt.dataset.id = d.id;
                $(el).append(opt);
            });
            $(el).trigger('change');
        }

        async function cargarDatos() {
            try {
                const resp = await fetch(`api_productos.php?codigo=${encodeURIComponent(prodCodigo)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await resp.json();
                
                if(res.success) {
                    const p = res.data;
                    const frm = document.getElementById('frmProducto');

                    if (p.descripcion) document.getElementById('tituloModulo').innerHTML = `<span class="text-info">EDITAR:</span> ${p.codigo} - ${p.descripcion}`;
                    
                    if(p.idmoneda) {
                        $('#cmbMoneda').val(p.idmoneda).trigger('change');
                        await actualizarCotizacion();
                    }
                    const tipoCambio = parseFloat(document.getElementById('txtCotizacion').value) || 1;

                    const pNorm = {};
                    Object.keys(p).forEach(k => pNorm[k.toLowerCase()] = p[k]);

                    Array.from(frm.elements).forEach(el => {
                        if (!el.name) return;
                        const key = el.name.toLowerCase();
                        if (pNorm.hasOwnProperty(key)) {
                            let val = pNorm[key];
                            
                            if (['sinivacosto', 'ivacosto', 'preciocosto', 'impint'].includes(key) || 
                                key.startsWith('precio') || key.startsWith('siniva') || key.startsWith('iibb')) {
                                val = (parseFloat(val) / tipoCambio).toFixed(3);
                            }

                            if (el.tagName === 'SELECT' && $(el).hasClass('select2-hidden-accessible')) {
                                $(el).val(val).trigger('change');
                            } else if(el.type === 'checkbox') {
                                el.checked = (val == 1);
                            } else {
                                el.value = (val !== null) ? val : "";
                            }
                        }
                    });

                    // Carga explicita de listas
                    document.getElementById('neto_l1').value = (parseFloat(pNorm['siniva']) / tipoCambio).toFixed(3);
                    document.getElementById('iva_l1').value = (parseFloat(pNorm['iva1']) / tipoCambio).toFixed(3);
                    document.getElementById('final_l1').value = (parseFloat(pNorm['precio']) / tipoCambio).toFixed(3);
                    document.getElementById('iibb_l1').value = (parseFloat(pNorm['iibb1']) / tipoCambio).toFixed(3);

                    for(let i=2; i<=8; i++) {
                        if(pNorm[`siniva${i}`]) document.getElementById(`neto_l${i}`).value = (parseFloat(pNorm[`siniva${i}`]) / tipoCambio).toFixed(3);
                        if(pNorm[`iva${i}`]) document.getElementById(`iva_l${i}`).value = (parseFloat(pNorm[`iva${i}`]) / tipoCambio).toFixed(3);
                        if(pNorm[`precio${i}`]) document.getElementById(`final_l${i}`).value = (parseFloat(pNorm[`precio${i}`]) / tipoCambio).toFixed(3);
                        if(pNorm[`iibb${i}`]) document.getElementById(`iibb_l${i}`).value = (parseFloat(pNorm[`iibb${i}`]) / tipoCambio).toFixed(3);
                    }

                    const costoNeto = parseFloat(document.getElementById('costo_neto').value) || 0;
                    if(costoNeto > 0) {
                        for(let i=1; i<=8; i++) {
                            const netoVenta = parseFloat(document.getElementById(`neto_l${i}`).value) || 0;
                            const utilReal = ((netoVenta - costoNeto) / costoNeto) * 100;
                            document.getElementById(`por_l${i}`).value = utilReal.toFixed(2);
                            document.getElementById(`util_l${i}`).value = utilReal.toFixed(2);
                        }
                    }

                    if(p.idiva) document.getElementById('prod_idiva').value = p.idiva;
                    if(p.proveedorID) document.getElementById('h_proveedorID').value = p.proveedorID;
                    
                    setTimeout(calcular, 150);
                }
            } catch (e) { console.error("Error crítico en fetch de producto:", e); }
        }

        window.calcular = function(rowIdx = null) {
            const mode = document.querySelector('input[name="calc_mode"]:checked').value;
            const costoNeto = parseFloat(document.getElementById('costo_neto').value) || 0;
            const impInt = parseFloat(document.getElementById('costo_ii').value) || 0;
            const selIva = document.getElementById('prod_idiva');
            
            const alicIva = (parseFloat(selIva.options[selIva.selectedIndex]?.dataset.iva) || 0) / 100;
            document.getElementById('prod_poriva_val').value = (alicIva * 100).toFixed(2);

            const costoIva = costoNeto * alicIva;
            document.getElementById('costo_iva').value = costoIva.toFixed(3);
            document.getElementById('costo_final').value = (costoNeto + impInt + costoIva).toFixed(3);

            const rubroText = $("#cmbRubros option:selected").text().toUpperCase();
            let alicIIBB = 0.035; 
            if (rubroText.includes("CIGARRILLOS") || rubroText.includes("TABACO") || rubroText.includes("HABANO")) {
                alicIIBB = 0.08; 
            }

            for (let i = 1; i <= 8; i++) {
                const utilInput = document.getElementById(`util_l${i}`);
                const porcCalculado = document.getElementById(`por_l${i}`);
                const netoInput = document.getElementById(`neto_l${i}`);
                const finalInput = document.getElementById(`final_l${i}`);
                const ivaLabel = document.getElementById(`iva_l${i}`);
                const iibbLabel = document.getElementById(`iibb_l${i}`);

                let netoVenta = 0, finalVenta = 0, util = 0;

                if (mode === 'lista') {
                    util = parseFloat(utilInput.dataset.defaultUtil) || parseFloat(utilInput.value) || 0;
                    utilInput.value = util;
                    netoVenta = costoNeto * (1 + util / 100);
                    finalVenta = netoVenta * (1 + alicIva) + impInt;
                    netoInput.value = netoVenta.toFixed(3);
                    finalInput.value = finalVenta.toFixed(3);

                } else if (mode === 'manual') {
                    util = parseFloat(utilInput.value) || 0;
                    netoVenta = costoNeto * (1 + util / 100);
                    finalVenta = netoVenta * (1 + alicIva) + impInt;
                    netoInput.value = netoVenta.toFixed(3);
                    finalInput.value = finalVenta.toFixed(3);

                } else if (mode === 'neto') {
                    netoVenta = parseFloat(netoInput.value) || 0;
                    finalVenta = netoVenta * (1 + alicIva) + impInt;
                    util = costoNeto > 0 ? ((netoVenta - costoNeto) / costoNeto * 100) : 0;
                    finalInput.value = finalVenta.toFixed(3);
                    utilInput.value = util.toFixed(2);

                } else if (mode === 'final') {
                    finalVenta = parseFloat(finalInput.value) || 0;
                    netoVenta = (finalVenta - impInt) / (1 + alicIva);
                    util = costoNeto > 0 ? ((netoVenta - costoNeto) / costoNeto * 100) : 0;
                    netoInput.value = netoVenta.toFixed(3);
                    utilInput.value = util.toFixed(2);
                }

                ivaLabel.value = (netoVenta * alicIva).toFixed(3);
                iibbLabel.value = (netoVenta * alicIIBB).toFixed(3);
                porcCalculado.value = util.toFixed(2);
            }
        }
        
        document.getElementById('frmProducto').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));
            const tipoCambio = parseFloat(document.getElementById('txtCotizacion').value) || 1;
            
            const moneyFields = ['sinivacosto', 'ivacosto', 'preciocosto', 'impint'];
            for(let i=1; i<=8; i++) {
                if(i===1) {
                    if(data['siniva']) data['siniva'] = (parseFloat(data['siniva']) * tipoCambio).toFixed(3);
                    if(data['iva1']) data['iva1'] = (parseFloat(data['iva1']) * tipoCambio).toFixed(3);
                    if(data['precio']) data['precio'] = (parseFloat(data['precio']) * tipoCambio).toFixed(3);
                } else {
                    if(data[`siniva${i}`]) data[`siniva${i}`] = (parseFloat(data[`siniva${i}`]) * tipoCambio).toFixed(3);
                    if(data[`iva${i}`]) data[`iva${i}`] = (parseFloat(data[`iva${i}`]) * tipoCambio).toFixed(3);
                    if(data[`precio${i}`]) data[`precio${i}`] = (parseFloat(data[`precio${i}`]) * tipoCambio).toFixed(3);
                }
                if(data[`IIBB${i}`]) data[`IIBB${i}`] = (parseFloat(data[`IIBB${i}`]) * tipoCambio).toFixed(3);
            }
            moneyFields.forEach(f => { if(data[f]) data[f] = (parseFloat(data[f]) * tipoCambio).toFixed(3); });

            const selPrv = document.getElementById('cmbProveedores');
            data.proveedorID = selPrv.options[selPrv.selectedIndex]?.dataset.id || document.getElementById('h_proveedorID').value || 1;
            ['controlastock', 'habilitado', 'mostrarencatalogo', 'mostrarsinstock'].forEach(f => {
                data[f] = e.target.querySelector(`[name="${f}"]`)?.checked ? 1 : 0;
            });

            try {
                const resp = await fetch('api_productos.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(data)
                });
                const res = await resp.json();
                if(res.success) {
                    Swal.fire({icon:'success', title:'Guardado', toast:true, position:'top-end', timer:2000, showConfirmButton:false});
                    setTimeout(() => loadModule('productos_admin.php'), 1200);
                } else Swal.fire('Error', res.error, 'error');
            } catch(err) { Swal.fire('Error', 'Error de conexión', 'error'); }
        };

        init();
    })();
</script>