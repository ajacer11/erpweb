<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$codigo = $_GET['codigo'] ?? '';
$clonar = $_GET['clonar'] ?? '';
// Características de la web: solo si la empresa tiene contratada la web de clientes.
require_once 'web_helpers.php';
$webCaracteristicas = webAddonHabilitado($pdo_master, (string) $_SESSION['tenant_cuit']);
// Título inicial
if ($codigo) {
    $titulo = "CARGANDO " . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . "...";
} elseif ($clonar) {
    $titulo = "CLONANDO " . htmlspecialchars($clonar, ENT_QUOTES, 'UTF-8') . "...";
} else {
    $titulo = "NUEVO PRODUCTO";
}
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
    /* Solapa RENTABILIDAD */
    .rent-box { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 12px 14px; }
    .rent-box-tit { font-size: .72rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); margin-bottom: 8px; }
    .rent-kpi { background: var(--bg-card); border: 1px solid var(--border-color); border-radius: 12px; padding: 10px 12px; height: 100%; }
    .rent-kpi .k-lbl { font-size: .68rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: var(--text-muted); }
    .rent-kpi .k-val { font-size: 1.35rem; font-weight: 800; color: var(--text-titles); line-height: 1.2; }
    .rent-kpi .k-sub { font-size: .72rem; color: var(--text-muted); }
    .rent-kpi.k-dest { border-color: var(--accent); background: var(--accent-soft); }
    .rent-tabla td, .rent-tabla th { white-space: nowrap; }
    .rent-tabla .rent-grupo { border-left: 1px solid var(--border-color); }
    .rent-tabla .rent-grupo-ini { border-left: 1px solid var(--border-color); }
    .rent-tabla tr.rent-sel td { background: var(--accent-soft); }
    .rent-tabla tbody tr { cursor: pointer; }
    .rent-neg { color: var(--danger) !important; }
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
            <li class="nav-item"><button class="nav-link" type="button" data-bs-toggle="tab" data-bs-target="#tab-rent" id="btnTabRent">RENTABILIDAD</button></li>
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
                                        <th class="text-center" width="9%">% UTIL</th>
                                        <th class="text-center" width="9%">PRED. %</th>
                                        <th class="text-center" width="9%">DESC. MÁX %</th>
                                        <th class="text-center" width="18%">NETO VTA</th>
                                        <th class="text-center" width="14%">IVA</th>
                                        <th class="text-center" width="15%">IMP. INT.</th>
                                        <th class="text-end" width="17%">P. FINAL</th>
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

                                        <!-- Descuento máximo permitido en facturación para esta lista -->
                                        <td><input type="number" class="form-control form-control-sm text-center" name="maxdescl<?=$i?>" id="maxdesc_l<?=$i?>" step="0.5" min="0" max="100"></td>

                                        <!-- Neto Venta -->
                                        <td><input type="number" class="form-control form-control-sm text-center" name="siniva<?=$i==1?'':$i?>" id="neto_l<?=$i?>" step="0.001" oninput="calcular(<?=$i?>)"></td>

                                        <!-- IVA Venta -->
                                        <td><input type="text" class="form-control form-control-sm text-center border-0 bg-transparent text-muted" name="iva<?=$i==1?'1':$i?>" id="iva_l<?=$i?>" readonly></td>

                                        <!-- Impuesto interno: importe fijo unitario, no integra la base de IVA -->
                                        <td><input type="text" class="form-control form-control-sm text-center border-0 bg-transparent text-muted" id="impint_l<?=$i?>" readonly></td>

                                        <!-- IIBB: se sigue calculando y guardando (lo usan retenciones/percepciones),
                                             pero se saca de la vista porque confundía a los usuarios en esta grilla -->
                                        <td style="display:none"><input type="text" name="IIBB<?=$i?>" id="iibb_l<?=$i?>" readonly></td>

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

            <!-- RENTABILIDAD: solo lectura, se calcula con los valores de PRECIOS Y COSTOS -->
            <div class="tab-pane fade" id="tab-rent">
                <div class="d-flex flex-wrap align-items-end gap-3 mb-3">
                    <div>
                        <label class="form-label-custom">Lista a analizar</label>
                        <select class="form-select form-select-sm" id="rentLista" style="min-width:140px"></select>
                    </div>
                    <div>
                        <label class="form-label-custom">Alícuota IIBB %</label>
                        <input type="number" class="form-control form-control-sm text-end" id="rentIibb" step="0.01" min="0" style="width:110px">
                    </div>
                    <div class="form-check mb-1">
                        <input class="form-check-input" type="checkbox" id="rentIibbConII">
                        <label class="form-check-label small" for="rentIibbConII">IIBB también sobre imp. internos</label>
                    </div>
                    <div class="small text-muted fst-italic mb-1 ms-auto" style="max-width:420px">
                        Simulación: la alícuota y la base de IIBB de esta solapa no se graban. Por defecto se usa la del rubro (8 % tabaco, 3,5 % resto).
                    </div>
                </div>

                <div id="rentVacio" class="text-center text-muted py-5" style="display:none">
                    <i class="fa-solid fa-chart-pie fa-2x mb-2"></i><br>
                    Cargá el <b>neto costo</b> y al menos un <b>precio de venta</b> en PRECIOS Y COSTOS para ver la rentabilidad.
                </div>

                <div id="rentContenido">
                    <!-- Indicadores de la lista elegida -->
                    <div class="row g-2 mb-3" id="rentKpis"></div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-5">
                            <div class="rent-box h-100">
                                <div class="rent-box-tit">¿A dónde va el precio final? <span class="text-muted" id="rentTitDona"></span></div>
                                <div style="position:relative;height:260px"><canvas id="rentChartDona"></canvas></div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="rent-box h-100">
                                <div class="rent-box-tit">Utilidad por lista</div>
                                <div style="position:relative;height:260px"><canvas id="rentChartListas"></canvas></div>
                            </div>
                        </div>
                    </div>

                    <div class="rent-box mb-3">
                        <div class="rent-box-tit">Todas las utilidades por lista</div>
                        <div class="table-responsive">
                            <table class="table table-sm small align-middle mb-0 rent-tabla">
                                <thead>
                                    <tr class="text-muted">
                                        <th rowspan="2">LISTA</th>
                                        <th class="text-end" rowspan="2">P. FINAL</th>
                                        <th class="text-center rent-grupo" colspan="5">ANTES DE IIBB</th>
                                        <th class="text-center rent-grupo" colspan="4">DESPUÉS DE IIBB</th>
                                        <th class="text-center rent-grupo" colspan="3">CON DESCUENTO MÁXIMO</th>
                                    </tr>
                                    <tr class="text-muted">
                                        <th class="text-end rent-grupo-ini" title="Neto venta − neto costo">GANANCIA $</th>
                                        <th class="text-end" title="Ganancia / neto costo. Es la utilidad de la solapa PRECIOS Y COSTOS">s/ NETO COSTO</th>
                                        <th class="text-end" title="Ganancia / (neto costo + imp. internos): lo que rinde la plata que ponés por unidad">s/ COSTO + II</th>
                                        <th class="text-end" title="Ganancia / neto venta">s/ NETO VTA</th>
                                        <th class="text-end" title="Ganancia / precio final: de lo que paga el cliente, cuánto te queda">s/ P. FINAL</th>
                                        <th class="text-end rent-grupo-ini">IIBB $</th>
                                        <th class="text-end" title="Ganancia − IIBB">GANANCIA $</th>
                                        <th class="text-end" title="(Ganancia − IIBB) / (neto costo + imp. internos)">s/ COSTO + II</th>
                                        <th class="text-end" title="(Ganancia − IIBB) / precio final">s/ P. FINAL</th>
                                        <th class="text-end rent-grupo-ini" title="Descuento máximo permitido para la lista">DESC.</th>
                                        <th class="text-end" title="Ganancia después de IIBB vendiendo con el descuento máximo">GANANCIA $</th>
                                        <th class="text-end" title="Descuento sobre el precio final a partir del cual se pierde plata (después de IIBB)">SIN PERDER</th>
                                    </tr>
                                </thead>
                                <tbody id="rentTbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="small text-muted">
                        <b>Cómo leerlo.</b> El IVA no es ganancia ni costo: el de la compra se recupera y el de la venta se paga a ARCA.
                        Los impuestos internos se cobran en el precio y se pagan en el costo: no cambian la ganancia en pesos, pero sí cuánta plata ponés por unidad, por eso la utilidad "s/ costo + II" es más baja.
                        "Sin perder" es el descuento sobre el precio final hasta el cual todavía cubrís costo e IIBB.
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
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="mostrarencatalogo" value="1" id="pubWeb"><label class="text-white small" for="pubWeb">Publicar en tienda/catálogo</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="mostrarsinstock" value="1" id="shwStock"><label class="text-white small" for="shwStock">Permitir pedidos sin stock</label></div>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label-custom">Descripción para tienda/catálogo</label>
                        <textarea class="form-control" name="descripcion_tienda" id="prod_desc_tienda" rows="4" maxlength="2000" placeholder="Si se deja vacío, se usa la descripción interna."></textarea>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-outline-info btn-sm" id="btnIaGenerarDesc" onclick="generarDescripcionTienda('generar')">
                                <i class="fas fa-wand-magic-sparkles me-1"></i>Generar con IA
                            </button>
                            <button type="button" class="btn btn-outline-info btn-sm" id="btnIaMejorarDesc" onclick="generarDescripcionTienda('mejorar')">
                                <i class="fas fa-arrows-rotate me-1"></i>Mejorar redacción
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1" id="descTiendaHint" style="display:none;">Guardá el producto para poder usar la IA acá.</small>
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-12">
                        <label class="form-label-custom">Imágenes adicionales</label>
                        <div class="d-flex gap-2 flex-wrap" id="galeriaAdicional"></div>
                        <small class="text-muted d-block mt-1">Se suman a la foto de portada (junto al título) y se muestran en la API de productos.</small>
                    </div>

                    <?php if ($webCaracteristicas): ?>
                    <div class="col-12"><hr class="border-secondary opacity-25"></div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label-custom mb-0">Características (ficha técnica en la web)</label>
                            <select class="form-select form-select-sm" id="prodAtrAgregar" style="max-width: 260px;"><option value="">+ Agregar otra característica...</option></select>
                        </div>
                        <div class="row g-2" id="prodAtributos"><div class="col-12 text-muted small">Cargando...</div></div>
                        <small class="text-muted d-block mt-1">Las que se piden dependen del rubro y la familia. Se administran en Administrador &gt; Web de Clientes &gt; Características de Productos.</small>
                    </div>
                    <?php endif; ?>
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
        const clonarCodigo = <?= json_encode($clonar, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
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

        // --- GALERÍA DE IMÁGENES ADICIONALES (slots 2 a 5) ---
        function setupGaleriaAdicional() {
            const cont = document.getElementById('galeriaAdicional');
            cont.innerHTML = '';

            for (let slot = 2; slot <= 5; slot++) {
                const box = document.createElement('div');
                box.className = 'prod-foto-box' + (!prodCodigo ? ' disabled' : '');
                box.title = prodCodigo ? `Subir/cambiar imagen ${slot - 1}` : 'Guardá el producto para poder subir imágenes';
                box.innerHTML = `
                    <img class="gal-img" src="" style="display:none;" alt="Imagen adicional">
                    <i class="fas fa-plus gal-icon"></i>
                    <div class="prod-foto-overlay"><i class="fas fa-upload me-1"></i>CAMBIAR</div>
                `;

                const input = document.createElement('input');
                input.type = 'file';
                input.accept = 'image/*';
                input.style.display = 'none';

                const img = box.querySelector('.gal-img');
                const icon = box.querySelector('.gal-icon');

                if (prodCodigo) {
                    img.src = `img_producto.php?codigo=${encodeURIComponent(prodCodigo)}&slot=${slot}&v=${Date.now()}`;
                    img.addEventListener('error', () => { img.style.display = 'none'; icon.style.display = ''; });
                    img.addEventListener('load', () => { img.style.display = ''; icon.style.display = 'none'; });
                    img.style.display = '';
                    box.addEventListener('click', () => input.click());
                }

                input.addEventListener('change', async function () {
                    const file = this.files[0];
                    if (!file || !prodCodigo) return;

                    const fd = new FormData();
                    fd.append('codigo', prodCodigo);
                    fd.append('foto', file);
                    fd.append('slot', slot);

                    Swal.fire({ title: 'Subiendo imagen...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    try {
                        const resp = await fetch('api_productos_foto.php?action=upload', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd
                        });
                        const res = await resp.json();
                        if (res.success) {
                            img.src = res.url;
                            img.style.display = '';
                            icon.style.display = 'none';
                            Swal.fire({ icon: 'success', title: 'Imagen actualizada', toast: true, position: 'top-end', timer: 1500, showConfirmButton: false });
                        } else {
                            Swal.fire('Error', res.error || 'No se pudo subir la imagen.', 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
                    } finally {
                        this.value = '';
                    }
                });

                box.appendChild(input);
                cont.appendChild(box);
            }
        }
        // -----------------------------

        // --- DESCRIPCIÓN DE TIENDA POR IA ---
        window.generarDescripcionTienda = async function (modo) {
            if (!prodCodigo) {
                Swal.fire('Atención', 'Primero guardá el producto para poder usar la IA.', 'warning');
                return;
            }
            const textoActual = document.getElementById('prod_desc_tienda').value.trim();
            if (modo === 'mejorar' && !textoActual) {
                Swal.fire('Atención', 'Escribí primero un texto para poder mejorarlo.', 'warning');
                return;
            }

            Swal.fire({ title: 'Generando con IA...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            try {
                const resp = await fetch('api_ia_descripcion_producto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ codigo: prodCodigo, modo, texto_actual: textoActual })
                });
                const res = await resp.json();
                Swal.close();
                if (res.success) {
                    document.getElementById('prod_desc_tienda').value = res.descripcion;
                } else {
                    Swal.fire('Error', res.error || 'No se pudo generar la descripción.', 'error');
                }
            } catch (e) {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        };
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
                lbl.textContent = "Tipee Precio Final (IVA + Imp. Int. incluidos). Se recalculan Neto, IVA y Utilidad.";
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
                const resp = await fetch(`api_referencias.php?action=list&code=${encodeURIComponent(codigo)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store' });
                const res = await resp.json();
                tbody.innerHTML = '';
                if(res.success && res.data) {
                    res.data.forEach(ref => {
                        tbody.innerHTML += `
                            <tr>
                                <td class="fw-bold" style="color: var(--text-main) !important;">${ref.codigo}</td>
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
            setupGaleriaAdicional();
            if (!prodCodigo) {
                document.getElementById('descTiendaHint').style.display = '';
            }

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
            } else if (clonarCodigo) {
                isEditing = true;
                document.getElementById('r_final').checked = true;
                toggleCalcMode();
                await cargarDatos(clonarCodigo, true);
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

        async function cargarDatos(codigoOrigen = prodCodigo, esClon = false) {
            try {
                const resp = await fetch(`api_productos.php?codigo=${encodeURIComponent(codigoOrigen)}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const res = await resp.json();

                if(res.success) {
                    const p = res.data;
                    const frm = document.getElementById('frmProducto');

                    if (p.descripcion) {
                        document.getElementById('tituloModulo').innerHTML = esClon
                            ? `<span class="text-warning">CLONANDO:</span> ${p.descripcion} <small class="text-muted">(asigná el nuevo código)</small>`
                            : `<span class="text-info">EDITAR:</span> ${p.codigo} - ${p.descripcion}`;
                    }

                    if(p.idmoneda) {
                        $('#cmbMoneda').val(p.idmoneda).trigger('change');
                        await actualizarCotizacion();
                    }
                    const tipoCambio = parseFloat(document.getElementById('txtCotizacion').value) || 1;

                    const pNorm = {};
                    Object.keys(p).forEach(k => pNorm[k.toLowerCase()] = p[k]);

                    Array.from(frm.elements).forEach(el => {
                        if (!el.name) return;
                        if (esClon && el.name === 'codigo') return; // El código nunca se clona
                        if (el.id === 'cmbProveedores') return; // Se resuelve aparte por ID (evita colisiones de razón social duplicada)
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

                    // Seleccionar el proveedor por ID (no por nombre): el combo puede tener
                    // razones sociales duplicadas o excluir proveedores deshabilitados, y
                    // matchear por texto termina seleccionando el proveedor equivocado.
                    if (p.proveedorID) {
                        const $prov = $('#cmbProveedores');
                        let encontrado = false;
                        $prov.find('option').each(function () {
                            if (String(this.dataset.id) === String(p.proveedorID)) {
                                $prov.val(this.value).trigger('change');
                                encontrado = true;
                                return false;
                            }
                        });
                        if (!encontrado) {
                            // Proveedor deshabilitado o renombrado: se agrega una opción temporal
                            // para no perder el dato original al guardar.
                            const nombreProv = pNorm['proveedor'] || `Proveedor #${p.proveedorID}`;
                            const opt = new Option(nombreProv, nombreProv, true, true);
                            opt.dataset.id = p.proveedorID;
                            $prov.append(opt).val(nombreProv).trigger('change');
                        }
                        document.getElementById('h_proveedorID').value = p.proveedorID;
                    }

                    setTimeout(calcular, 150);
                }
            } catch (e) { console.error("Error crítico en fetch de producto:", e); }
        }

        // Alícuota de IIBB según el rubro (la usa calcular() y la solapa RENTABILIDAD)
        window.alicIIBBRubro = function() {
            const rubroText = $("#cmbRubros option:selected").text().toUpperCase();
            if (rubroText.includes("CIGARRILLOS") || rubroText.includes("TABACO") || rubroText.includes("HABANO")) return 0.08;
            return 0.035;
        };

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

            const alicIIBB = window.alicIIBBRubro();

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
                document.getElementById(`impint_l${i}`).value = impInt.toFixed(3);
                iibbLabel.value = (netoVenta * alicIIBB).toFixed(3);
                porcCalculado.value = util.toFixed(2);
            }
            if (document.getElementById('tab-rent')?.classList.contains('active') && window.renderRentabilidad) window.renderRentabilidad();
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
                if(res.success && window.guardarCaracteristicasProducto) {
                    const errAtr = await window.guardarCaracteristicasProducto(data.codigo);
                    if (errAtr) { Swal.fire('Producto guardado, pero no sus características', errAtr, 'warning'); return; }
                }
                if(res.success) {
                    Swal.fire({icon:'success', title:'Guardado', toast:true, position:'top-end', timer:2000, showConfirmButton:false});
                    setTimeout(() => loadModule('productos_admin.php'), 1200);
                } else Swal.fire('Error', res.error, 'error');
            } catch(err) { Swal.fire('Error', 'Error de conexión', 'error'); }
        };

        init();
    })();

<?php if ($webCaracteristicas): ?>
    // ===== Características de la web (prod_atributos / api_web_atributos.php) =====
    (function() {
        const API_ATR = 'api_web_atributos.php';
        const H = {'X-Requested-With': 'XMLHttpRequest'};
        const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        let mostrados = [];   // atributos en pantalla
        let todos = [];       // todos los activos (para "agregar otra")
        let cargado = false;  // si no se pudieron cargar, al guardar no se tocan (no borrar valores existentes)

        function campo(a) {
            const v = esc(a.valor ?? '');
            let input;
            if (a.tipo === 'opciones') {
                input = `<select class="form-select form-select-sm prod-atr" data-id="${a.id}"><option value=""></option>${a.opciones.map(o => `<option ${o === a.valor ? 'selected' : ''}>${esc(o)}</option>`).join('')}</select>`;
            } else if (a.tipo === 'sino') {
                input = `<select class="form-select form-select-sm prod-atr" data-id="${a.id}"><option value=""></option><option ${a.valor === 'Sí' ? 'selected' : ''}>Sí</option><option ${a.valor === 'No' ? 'selected' : ''}>No</option></select>`;
            } else if (a.tipo === 'numero') {
                input = `<div class="input-group input-group-sm"><input type="text" inputmode="decimal" class="form-control prod-atr" data-id="${a.id}" value="${v}">${a.unidad ? `<span class="input-group-text">${esc(a.unidad)}</span>` : ''}</div>`;
            } else {
                input = `<input type="text" class="form-control form-control-sm prod-atr" data-id="${a.id}" value="${v}" maxlength="255">`;
            }
            return `<div class="col-md-4 col-lg-3"><label class="small text-white-50 mb-1">${esc(a.nombre)}</label>${input}</div>`;
        }

        function valoresActuales() {
            const out = {};
            document.querySelectorAll('#prodAtributos .prod-atr').forEach(el => out[el.dataset.id] = el.value);
            return out;
        }

        function render() {
            $('#prodAtributos').html(mostrados.length ? mostrados.map(campo).join('')
                : '<div class="col-12 text-muted small">No hay características definidas para este rubro/familia.</div>');
            const ids = new Set(mostrados.map(a => +a.id));
            $('#prodAtrAgregar').html('<option value="">+ Agregar otra característica...</option>' +
                todos.filter(a => !ids.has(+a.id)).map(a => `<option value="${a.id}">${esc(a.nombre)}</option>`).join(''));
        }

        async function cargar() {
            const actuales = valoresActuales();
            const p = new URLSearchParams({action: 'producto', codigo: $('#prod_codigo').val() || '', rubro: $('#cmbRubros').val() || '', familia: $('#cmbFamilias').val() || ''});
            try {
                const r = await (await fetch(`${API_ATR}?${p}`, {headers: H})).json();
                if (!r.success) throw new Error(r.error);
                // Conserva lo que el usuario ya escribió en pantalla (al cambiar de rubro no se pierde).
                mostrados = r.data.map(a => ({...a, valor: actuales[a.id] ?? a.valor}));
                todos = r.todos;
                cargado = true;
                render();
            } catch (e) {
                $('#prodAtributos').html(`<div class="col-12 text-danger small">No se pudieron cargar las características: ${esc(e.message)}</div>`);
            }
        }

        $('#prodAtrAgregar').on('change', async function() {
            const id = +this.value;
            if (!id) return;
            const actuales = valoresActuales();
            mostrados = mostrados.map(a => ({...a, valor: actuales[a.id] ?? a.valor}));
            const r = await (await fetch(`${API_ATR}?action=listar`, {headers: H})).json();
            const a = (r.data || []).find(x => +x.id === id);
            if (a) mostrados.push({...a, valor: ''});
            render();
        });

        // El rubro/familia del producto se cargan después de abrir el formulario: esperamos a que estén.
        $(document).on('change', '#cmbRubros, #cmbFamilias', () => cargar());
        setTimeout(cargar, 1200);

        /** Se llama después de guardar el producto. Devuelve null si salió bien o el mensaje de error. */
        window.guardarCaracteristicasProducto = async function(codigo) {
            if (!codigo || !cargado) return null;
            try {
                const r = await (await fetch(`${API_ATR}?action=guardar_producto`, {
                    method: 'POST', headers: {...H, 'Content-Type': 'application/json'},
                    body: JSON.stringify({codigo, valores: valoresActuales()})
                })).json();
                return r.success ? null : r.error;
            } catch (e) { return e.message; }
        };
    })();
<?php endif; ?>
</script>

<script>
    // ===================== SOLAPA RENTABILIDAD =====================
    // Solo lectura: toma neto costo, imp. internos, alícuota de IVA y los precios
    // de cada lista de PRECIOS Y COSTOS. El descuento replica el de api_facturar
    // (el % se aplica sobre el precio final y descuenta primero de neto + IVA).
    (function() {
        const COL = { costo: '#6b7f99', ii: '#e0a030', iva: '#8e7cc3', iibb: '#d9534f', gan: '#2fb67c' };
        const $id = (x) => document.getElementById(x);
        const num = (x) => parseFloat($id(x)?.value) || 0;
        const fmt$ = (v) => '$ ' + (Number.isFinite(v) ? v : 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const fmtP = (v) => (v === null || !Number.isFinite(v)) ? '—' : v.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' %';
        const pct = (a, b) => b > 0 ? a / b * 100 : null;
        const cls = (v) => (v !== null && v < 0) ? 'rent-neg' : '';
        let iibbManual = false;
        let charts = [];

        function cargarChart() {
            if (window.Chart) return Promise.resolve();
            return new Promise((ok, mal) => {
                const s = document.createElement('script');
                s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
                s.onload = ok; s.onerror = mal;
                document.head.appendChild(s);
            });
        }

        function calcularListas() {
            const CN = num('costo_neto');
            const II = num('costo_ii');
            const selIva = $id('prod_idiva');
            const a = (parseFloat(selIva?.options[selIva.selectedIndex]?.dataset.iva) || 0) / 100;
            const r = (parseFloat($id('rentIibb').value) || 0) / 100;
            const iibbConII = $id('rentIibbConII').checked;
            const costoII = CN + II;
            const iibbDe = (neto, ii) => (neto + (iibbConII ? ii : 0)) * r;

            const filas = [];
            for (let i = 1; i <= 8; i++) {
                const N = num(`neto_l${i}`);
                if (N <= 0 && num(`final_l${i}`) <= 0) continue;
                const F = num(`final_l${i}`) || (N * (1 + a) + II);
                const ivaV = N * a;
                const G = N - CN;
                const iibb = iibbDe(N, II);
                const G2 = G - iibb;

                // Vendiendo con el descuento máximo de la lista
                const d = num(`maxdesc_l${i}`) / 100;
                const baseNetoIva = N * (1 + a);
                const descU = d * F;
                let Nd, IId = II;
                if (descU <= baseNetoIva) { Nd = (baseNetoIva - descU) / (1 + a); }
                else { Nd = 0; IId = Math.max(0, II - (descU - baseNetoIva)); }
                const Gd = Nd - CN - iibbDe(Nd, IId);

                // Descuento sobre el precio final hasta el cual no se pierde (después de IIBB)
                let sinPerder = null;
                if (F > 0 && r < 1) {
                    const NEq = (CN + (iibbConII ? II * r : 0)) / (1 - r);
                    sinPerder = (baseNetoIva - NEq * (1 + a)) / F * 100;
                }

                filas.push({
                    i, N, F, ivaV, G, iibb, G2, d: d * 100, Gd, sinPerder,
                    mkNeto: pct(G, CN), mkII: pct(G, costoII), mgNeto: pct(G, N), mgFinal: pct(G, F),
                    mkII2: pct(G2, costoII), mgFinal2: pct(G2, F), mkD: pct(Gd, costoII)
                });
            }
            return { filas, CN, II, costoII };
        }

        function kpi(lbl, val, sub, dest) {
            return `<div class="col-6 col-md-4 col-xl-2"><div class="rent-kpi ${dest ? 'k-dest' : ''}">
                <div class="k-lbl">${lbl}</div><div class="k-val">${val}</div><div class="k-sub">${sub}</div></div></div>`;
        }
        const txtSinPerder = (v) => v === null ? '—' : (v <= 0 ? 'Pierde' : fmtP(v));

        window.renderRentabilidad = async function() {
            if (!iibbManual) $id('rentIibb').value = (window.alicIIBBRubro() * 100).toFixed(2);
            const { filas, CN, II, costoII } = calcularListas();

            const hay = CN > 0 && filas.length > 0;
            $id('rentVacio').style.display = hay ? '' : 'none';
            $id('rentContenido').style.display = hay ? '' : 'none';
            if (!hay) return;

            // Selector de lista (conserva la elegida si sigue existiendo)
            const sel = $id('rentLista');
            const previa = parseInt(sel.value) || filas[0].i;
            sel.innerHTML = filas.map(f => `<option value="${f.i}">Lista ${f.i}</option>`).join('');
            sel.value = filas.some(f => f.i === previa) ? previa : filas[0].i;
            const f = filas.find(x => x.i === parseInt(sel.value));

            // Indicadores de la lista elegida
            $id('rentKpis').innerHTML =
                kpi('Ganancia por unidad', fmt$(f.G2), `${fmt$(f.G)} antes de IIBB`, true) +
                kpi('Utilidad s/ neto costo', fmtP(f.mkNeto), 'la de PRECIOS Y COSTOS') +
                kpi('Utilidad s/ costo + II', fmtP(f.mkII), `ponés ${fmt$(costoII)} por unidad`) +
                kpi('Después de IIBB', fmtP(f.mkII2), `s/ costo + II · IIBB ${fmt$(f.iibb)}`) +
                kpi('Te queda del precio final', fmtP(f.mgFinal2), `de ${fmt$(f.F)} que paga el cliente`) +
                kpi('Descuento sin perder', txtSinPerder(f.sinPerder), f.d > 0 ? `máx. permitido ${fmtP(f.d)}` : 'sin descuento máximo cargado');

            // Tabla con todas las listas
            $id('rentTbody').innerHTML = filas.map(x => `
                <tr data-lista="${x.i}" class="${x.i === f.i ? 'rent-sel' : ''}">
                    <td class="fw-bold">LISTA ${x.i}</td>
                    <td class="text-end">${fmt$(x.F)}</td>
                    <td class="text-end rent-grupo-ini ${cls(x.G)}">${fmt$(x.G)}</td>
                    <td class="text-end fw-bold ${cls(x.mkNeto)}">${fmtP(x.mkNeto)}</td>
                    <td class="text-end ${cls(x.mkII)}">${fmtP(x.mkII)}</td>
                    <td class="text-end ${cls(x.mgNeto)}">${fmtP(x.mgNeto)}</td>
                    <td class="text-end ${cls(x.mgFinal)}">${fmtP(x.mgFinal)}</td>
                    <td class="text-end rent-grupo-ini">${fmt$(x.iibb)}</td>
                    <td class="text-end fw-bold ${cls(x.G2)}">${fmt$(x.G2)}</td>
                    <td class="text-end ${cls(x.mkII2)}">${fmtP(x.mkII2)}</td>
                    <td class="text-end ${cls(x.mgFinal2)}">${fmtP(x.mgFinal2)}</td>
                    <td class="text-end rent-grupo-ini">${x.d > 0 ? fmtP(x.d) : '—'}</td>
                    <td class="text-end ${x.d > 0 ? cls(x.Gd) : ''}">${x.d > 0 ? fmt$(x.Gd) + ` <span class="text-muted">(${fmtP(x.mkD)})</span>` : '—'}</td>
                    <td class="text-end ${x.sinPerder !== null && x.sinPerder <= 0 ? 'rent-neg' : ''}">${txtSinPerder(x.sinPerder)}</td>
                </tr>`).join('');

            // Gráficos
            try { await cargarChart(); } catch (e) { return; }
            charts.forEach(c => c.destroy()); charts = [];
            const css = getComputedStyle(document.documentElement);
            const txt = css.getPropertyValue('--text-muted').trim() || '#999';
            const grid = css.getPropertyValue('--border-color').trim() || 'rgba(128,128,128,.2)';
            const bg = css.getPropertyValue('--bg-modal').trim() || '#fff';

            $id('rentTitDona').textContent = `· Lista ${f.i} · ${fmt$(f.F)}`;
            const partes = [
                ['Costo neto', CN, COL.costo], ['Imp. internos', II, COL.ii], ['IVA (a ARCA)', f.ivaV, COL.iva],
                ['IIBB', f.iibb, COL.iibb], ['Ganancia', Math.max(0, f.G2), COL.gan]
            ].filter(p => p[1] > 0.004);
            charts.push(new Chart($id('rentChartDona'), {
                type: 'doughnut',
                data: { labels: partes.map(p => p[0]), datasets: [{ data: partes.map(p => +p[1].toFixed(2)), backgroundColor: partes.map(p => p[2]), borderColor: bg, borderWidth: 2 }] },
                options: {
                    maintainAspectRatio: false, cutout: '58%',
                    plugins: {
                        legend: { position: 'right', labels: { color: txt, boxWidth: 12, padding: 10 } },
                        tooltip: { callbacks: { label: (c) => `${c.label}: ${fmt$(c.parsed)} (${fmtP(pct(c.parsed, f.F))})` } }
                    }
                }
            }));

            charts.push(new Chart($id('rentChartListas'), {
                type: 'bar',
                data: {
                    labels: filas.map(x => `Lista ${x.i}`),
                    datasets: [
                        { label: 's/ neto costo', data: filas.map(x => x.mkNeto), backgroundColor: '#3b8fd9' },
                        { label: 's/ costo + II', data: filas.map(x => x.mkII), backgroundColor: COL.ii },
                        { label: 'después de IIBB', data: filas.map(x => x.mkII2), backgroundColor: COL.gan },
                        { label: 'del precio final', data: filas.map(x => x.mgFinal2), backgroundColor: COL.iva }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        x: { ticks: { color: txt }, grid: { display: false } },
                        y: { ticks: { color: txt, callback: (v) => v + ' %' }, grid: { color: grid } }
                    },
                    plugins: {
                        legend: { labels: { color: txt, boxWidth: 12 } },
                        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${fmtP(c.parsed.y)}` } }
                    }
                }
            }));
        };

        $('#btnTabRent').on('shown.bs.tab', () => window.renderRentabilidad());
        $('#rentLista').on('change', () => window.renderRentabilidad());
        $('#rentIibb').on('input', () => { iibbManual = true; window.renderRentabilidad(); });
        $('#rentIibbConII').on('change', () => window.renderRentabilidad());
        $('#rentTbody').on('click', 'tr[data-lista]', function() { $('#rentLista').val(this.dataset.lista); window.renderRentabilidad(); });
    })();
</script>
