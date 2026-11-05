<?php
// SEGURIDAD: Solo permitir acceso via AJAX
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';

$id_cliente = isset($_GET['id']) ? intval($_GET['id']) : 0;
// Título inicial (placeholder) que luego JS actualizará con el nombre real
$titulo = $id_cliente ? "CARGANDO CLIENTE $id_cliente..." : "NUEVO CLIENTE";
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-end mb-4 border-bottom border-secondary pb-3">
        <div>
            <!-- AGREGADO id="tituloModulo" para actualizarlo desde JS -->
            <h5 class="text-white mb-0" id="tituloModulo" style="letter-spacing: 2px;"><?php echo $titulo; ?></h5>
            <small class="text-accent" style="font-size: 11px;">MÓDULO DE PADRÓN DE CLIENTES</small>
        </div>
        <button onclick="loadModule('clientes_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CANCELAR
        </button>
    </div>

    <form id="frmCliente">
        <input type="hidden" name="id" value="<?php echo $id_cliente; ?>">

        <!-- Identificación -->
        <div class="row g-2 mb-3">
            <div class="col-md-5">
                <label class="form-label-custom">Razón Social</label>
                <input type="text" class="form-control fw-bold" name="razon" required placeholder="NOMBRE DEL CLIENTE">
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">Condición Fiscal</label>
                <select class="form-select select2-simple" name="condicionfiscal">
                    <option value="CONSUMIDOR FINAL">CONSUMIDOR FINAL</option>
                    <option value="RESPONSABLE INSCRIPTO">RESPONSABLE INSCRIPTO</option>
                    <option value="MONOTRIBUTO">MONOTRIBUTO</option>
                    <option value="EXENTO">EXENTO</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label-custom">Tipo Doc</label>
                <select class="form-select" name="tipodoc" id="tipodoc">
                    <option value="DNI">DNI</option>
                    <option value="CUIT">CUIT</option>
                    <option value="CUIL">CUIL</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label-custom">Nro Documento</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="numdoc" id="numdoc">
                    <button type="button" class="btn btn-outline-info" id="btnAutocompletarCuit" title="Autocompletar por CUIT (consulta el padrón de AFIP)">
                        <i class="fas fa-magic"></i>
                    </button>
                </div>
            </div>
        </div>

        <ul class="nav nav-tabs" id="clientTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-dir" type="button">UBICACIÓN</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-com" type="button">COMERCIAL</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-log" type="button">LOGÍSTICA</button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-sec" type="button">CONTACTOS</button>
            </li>
        </ul>

        <div class="tab-content card card-filtros p-4 border-top-0 mb-4">
            <!-- UBICACIÓN -->
            <div class="tab-pane fade show active" id="tab-dir">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label-custom">Domicilio</label><input type="text" class="form-control" name="domicilio"></div>
                    <div class="col-md-3"><label class="form-label-custom">Localidad</label><input type="text" class="form-control" name="localidad"></div>
                    <div class="col-md-1"><label class="form-label-custom">CP</label><input type="text" class="form-control" name="cp"></div>
                    <div class="col-md-2">
                        <label class="form-label-custom">Provincia</label>
                        <select class="form-select select2-simple" name="provincia">
                            <option value="">SELECCIONE...</option>
                            <option value="BUENOS AIRES">BUENOS AIRES</option>
                            <option value="CABA">CABA</option>
                            <option value="CATAMARCA">CATAMARCA</option>
                            <option value="CHACO">CHACO</option>
                            <option value="CHUBUT">CHUBUT</option>
                            <option value="CÓRDOBA">CÓRDOBA</option>
                            <option value="CORRIENTES">CORRIENTES</option>
                            <option value="ENTRE RÍOS">ENTRE RÍOS</option>
                            <option value="FORMOSA">FORMOSA</option>
                            <option value="JUJUY">JUJUY</option>
                            <option value="LA PAMPA">LA PAMPA</option>
                            <option value="LA RIOJA">LA RIOJA</option>
                            <option value="MENDOZA">MENDOZA</option>
                            <option value="MISIONES">MISIONES</option>
                            <option value="NEUQUÉN">NEUQUÉN</option>
                            <option value="RÍO NEGRO">RÍO NEGRO</option>
                            <option value="SALTA">SALTA</option>
                            <option value="SAN JUAN">SAN JUAN</option>
                            <option value="SAN LUIS">SAN LUIS</option>
                            <option value="SANTA CRUZ">SANTA CRUZ</option>
                            <option value="SANTA FE">SANTA FE</option>
                            <option value="SANTIAGO DEL ESTERO">SANTIAGO DEL ESTERO</option>
                            <option value="TIERRA DEL FUEGO, ANTÁRTIDA E ISLAS DEL ATLÁNTICO SUR">TIERRA DEL FUEGO, ANTÁRTIDA E ISLAS DEL ATLÁNTICO SUR</option>
                            <option value="TUCUMÁN">TUCUMÁN</option>
                        </select>
                    </div>
                    <div class="col-md-4"><label class="form-label-custom">Teléfono</label><input type="text" class="form-control" name="telefono"></div>
                    <div class="col-md-4"><label class="form-label-custom">Celular</label><input type="text" class="form-control" name="celular"></div>
                    <div class="col-md-4"><label class="form-label-custom">E-Mail</label><input type="email" class="form-control" name="mail"></div>
                </div>
            </div>

            <!-- COMERCIAL -->
            <div class="tab-pane fade" id="tab-com">
                <div class="row">
                    <div class="col-md-8">
                        <label class="form-label-custom text-accent mb-3">LISTA DE PRECIOS PREDETERMINADA</label>
                        <div class="d-flex flex-wrap gap-3 p-3 bg-dark bg-opacity-25 rounded border border-secondary">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ListaDePRecios" value="<?= $i ?>" id="lp<?= $i ?>" <?= ($i == 1 ? 'checked' : '') ?>>
                                    <label class="form-check-label text-muted small" for="lp<?= $i ?>">LISTA <?= $i ?></label>
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom text-accent mb-3">CONDICIÓN DE CRÉDITO</label>
                        <div class="p-3 bg-dark bg-opacity-25 border border-secondary rounded">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" name="cuentacorriente" id="chkCtaCte" onchange="document.getElementById('txtDias').disabled = !this.checked">
                                <label class="text-white small ms-2">Habilita Cta. Cte.</label>
                            </div>
                            <label class="form-label-custom">Días de Gracia</label>
                            <input type="number" class="form-control w-50" name="diascc" id="txtDias" value="10" disabled>
                        </div>
                        <div class="mt-4 pt-3 border-top border-secondary">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="habilitado" id="chkHab" checked value="1">
                                <label class="text-white small fw-bold ms-2">CLIENTE HABILITADO</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LOGÍSTICA -->
            <div class="tab-pane fade" id="tab-log">
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label-custom">Vendedor</label><select class="form-select select2-simple" name="vendedorid" id="cmbVendedores"></select></div>
                    <div class="col-md-6"><label class="form-label-custom">Transporte</label><select class="form-select select2-simple" name="transporte" id="cmbTransportes"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Unidad</label><select class="form-select select2-simple" name="unidad" id="cmbUnidades"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Rama</label><select class="form-select" name="rama" id="cmbRamas"></select></div>
                    <div class="col-md-4"><label class="form-label-custom">Web</label><input type="text" class="form-control" name="web"></div>
                    <div class="col-md-12"><label class="form-label-custom">Notas Logísticas</label><input type="text" class="form-control" name="horario"></div>
                </div>
            </div>

            <!-- CONTACTOS EXTRA -->
            <div class="tab-pane fade" id="tab-sec">
                <div class="row g-1 mb-1 d-none d-md-flex text-accent small fw-bold">
                    <div class="col-md-2">SECTOR</div>
                    <div class="col-md-3">NOMBRE</div>
                    <div class="col-md-2">TEL. FIJO</div>
                    <div class="col-md-2">CELULAR</div>
                    <div class="col-md-3">EMAIL</div>
                </div>
                <?php for($i=1; $i<=3; $i++): ?>
                <div class="row g-1 mb-2">
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="s<?=$i?>sector" placeholder="Sector"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="s<?=$i?>contacto" placeholder="Nombre"></div>
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="s<?=$i?>tel" placeholder="Fijo"></div>
                    <div class="col-md-2"><input type="text" class="form-control form-control-sm" name="s<?=$i?>celu" placeholder="Móvil"></div>
                    <div class="col-md-3"><input type="text" class="form-control form-control-sm" name="s<?=$i?>mail" placeholder="Email"></div>
                </div>
                <?php endfor; ?>
                <div class="mt-3">
                    <label class="form-label-custom">Observaciones Generales</label>
                    <textarea class="form-control" name="notas" rows="3"></textarea>
                </div>
            </div>
        </div>

        <div class="text-center">
            <button type="submit" class="btn btn-info px-5 fw-bold"><i class="fas fa-save me-2"></i>REGISTRAR CLIENTE</button>
        </div>
    </form>
</div>

<script>
    (function () {
        $('.select2-simple').select2({
            dropdownParent: $('#main-content'),
            width: '100%'
        });

        const idCli = <?php echo $id_cliente; ?>;

        function validarCUIT(cuit) {
            cuit = cuit.replace(/[-_]/g, "").trim();
            if (cuit.length !== 11) return false;
            const coeficientes = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
            let sum = 0;
            for (let i = 0; i < 10; i++) sum += parseInt(cuit[i]) * coeficientes[i];
            let result = 11 - (sum % 11);
            if (result === 11) result = 0;
            if (result === 10) result = 9;
            return result === parseInt(cuit[10]);
        }

        const ALIAS_PROVINCIA_AFIP = {
            'CIUDAD AUTONOMA BUENOS AIRES': 'CABA',
            'CIUDAD AUTONOMA DE BUENOS AIRES': 'CABA',
            'CIUDAD AUTÓNOMA DE BUENOS AIRES': 'CABA',
            'CAPITAL FEDERAL': 'CABA',
            'CORDOBA': 'CÓRDOBA',
            'ENTRE RIOS': 'ENTRE RÍOS',
            'NEUQUEN': 'NEUQUÉN',
            'RIO NEGRO': 'RÍO NEGRO',
            'TUCUMAN': 'TUCUMÁN',
            'TIERRA DEL FUEGO, ANTARTIDA E ISLAS DEL ATLANTICO SUR': 'TIERRA DEL FUEGO, ANTÁRTIDA E ISLAS DEL ATLÁNTICO SUR'
        };

        function setSelectConAutoAgregado(el, valor) {
            if (!valor) return;
            const opt = Array.from(el.options).find(o => o.value.toUpperCase() === valor.toUpperCase());
            if (opt) {
                $(el).val(opt.value).trigger('change');
            } else {
                const nuevaOpt = new Option(valor, valor, true, true);
                $(el).append(nuevaOpt).trigger('change');
            }
        }

        async function autocompletarPorCuit() {
            const tipodoc = document.getElementById('tipodoc').value;
            const numdocEl = document.getElementById('numdoc');
            const cuit = numdocEl.value.replace(/[-_]/g, '').trim();

            if (tipodoc !== 'CUIT') {
                return Swal.fire({ icon: 'warning', title: 'Atención', text: 'Seleccioná "CUIT" como Tipo Doc para poder autocompletar.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            }
            if (!validarCUIT(cuit)) {
                return Swal.fire({ icon: 'warning', title: 'CUIT inválido', text: 'Ingresá un CUIT válido de 11 dígitos.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
            }

            const btn = document.getElementById('btnAutocompletarCuit');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            try {
                const resp = await fetch(`api_clientes.php?action=consultar_padron&cuit=${cuit}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                if (!res.success) throw new Error(res.error || 'No se pudo consultar el padrón.');

                const d = res.data;
                const frm = document.getElementById('frmCliente');
                if (d.razonSocial) frm.querySelector('[name="razon"]').value = d.razonSocial;
                if (d.domicilio) frm.querySelector('[name="domicilio"]').value = d.domicilio;
                if (d.localidad) frm.querySelector('[name="localidad"]').value = d.localidad;
                if (d.codPostal) frm.querySelector('[name="cp"]').value = d.codPostal;
                if (d.provincia) {
                    const provNorm = ALIAS_PROVINCIA_AFIP[d.provincia.toUpperCase().trim()] || d.provincia;
                    setSelectConAutoAgregado(frm.querySelector('[name="provincia"]'), provNorm);
                }
                if (d.condicionFiscal) {
                    setSelectConAutoAgregado(frm.querySelector('[name="condicionfiscal"]'), d.condicionFiscal.toUpperCase());
                }

                Swal.fire({ icon: 'success', title: 'Datos completados desde el padrón', toast: true, position: 'top-end', showConfirmButton: false, timer: 2200 });
            } catch (e) {
                Swal.fire('No se pudo autocompletar', e.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-magic"></i>';
            }
        }
        document.getElementById('btnAutocompletarCuit').addEventListener('click', autocompletarPorCuit);

        async function initCombos() {
            try {
                const resp = await fetch('api_clientes.php?action=get_combos', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                if (res.success) {
                    const fill = (elId, data, valField, textField) => {
                        const el = document.getElementById(elId);
                        if (!el) return;
                        $(el).empty();
                        $(el).append(new Option('SELECCIONE...', '0', true, true));
                        data.forEach(d => {
                            const opt = new Option(d[textField], d[valField], false, false);
                            $(el).append(opt);
                        });
                        $(el).trigger('change');
                    };
                    fill('cmbVendedores', res.data.vendedores, 'id', 'nombre');
                    fill('cmbRamas', res.data.ramas, 'id', 'rama');
                    fill('cmbUnidades', res.data.unidades, 'id', 'nombre');
                    fill('cmbTransportes', res.data.transportes, 'id', 'razon');
                    $('#cmbRamas').select2({
                        dropdownParent: $('#main-content'),
                        width: '100%',
                        placeholder: 'SELECCIONE UNA RAMA...',
                        allowClear: true
                    }).prop('disabled', false);
                }
                if (idCli > 0) setTimeout(cargarCliente, 50);
            } catch (e) { console.error(e); }
        }

        async function cargarCliente() {
            try {
                const resp = await fetch(`api_clientes.php?id=${idCli}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const res = await resp.json();
                if (res.success) {
                    const c = res.data;
                    const frm = document.getElementById('frmCliente');

                    // --- ACTUALIZACIÓN DINÁMICA DEL TÍTULO ---
                    if (c.razon) {
                        const tituloEl = document.getElementById('tituloModulo');
                        // Formato: EDITAR: 123 - RAZON SOCIAL
                        tituloEl.innerHTML = `<span class="text-info">EDITAR:</span> ${c.id} - ${c.razon}`;
                    }
                    // ----------------------------------------

                    Object.keys(c).forEach(key => {
                        const el = frm.querySelector(`[name="${key}"]`);
                        if (el) {
                            let val = c[key] === null ? "" : c[key];

                            if (el.tagName === 'SELECT') {
                                // Coincidencia case-insensitive: la API devuelve el valor tal
                                // cual está guardado ("Consumidor Final", "Dni"), pero las
                                // <option value="..."> de estos combos están en MAYÚSCULAS.
                                // Con .val() exacto no matcheaba nada y el combo quedaba vacío.
                                let matchVal = val;
                                if (typeof val === 'string' && val !== '') {
                                    const opt = Array.from(el.options).find(o => o.value.toLowerCase() === val.toLowerCase());
                                    if (opt) matchVal = opt.value;
                                }
                                if ($(el).hasClass('select2-hidden-accessible')) {
                                    $(el).val(matchVal).trigger('change');
                                } else {
                                    el.value = matchVal;
                                }
                            } else if (el.type === 'checkbox') {
                                el.checked = (val == 1);
                            } else if (el.type === 'radio') {
                                const rad = frm.querySelector(`[name="${key}"][value="${val}"]`);
                                if (rad) rad.checked = true;
                            } else {
                                el.value = val;
                            }
                        }
                    });
                    if (c.cuentacorriente == 1) document.getElementById('txtDias').disabled = false;
                }
            } catch (e) { console.error("Error cargando cliente:", e); }
        }

        document.getElementById('frmCliente').onsubmit = async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(e.target));

            if (data.tipodoc === "CUIT" && !validarCUIT(data.numdoc)) {
                Swal.fire({icon: 'warning', title:'CUIT Inválido', text: 'El CUIT ingresado no es válido.', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000});
                return;
            }

            data.cuentacorriente = document.getElementById('chkCtaCte').checked ? 1 : 0;
            data.habilitado = document.getElementById('chkHab').checked ? 1 : 0;

            try {
                const resp = await fetch('api_clientes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify(data)
                });
                const res = await resp.json();
                if (res.success) {
                    Swal.fire({icon:'success', title:'Guardado', text:res.message, toast:true, position:'top-end', timer:2000, showConfirmButton:false});
                    setTimeout(() => loadModule('clientes_admin.php'), 1200);
                } else {
                    throw new Error(res.error);
                }
            } catch (err) {
                Swal.fire('Error', err.message, 'error');
            }
        };

        initCombos();
    })();
</script>
