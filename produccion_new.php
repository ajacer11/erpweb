<?php
// SEGURIDAD
// SEGURIDAD CENTRALIZADA (Check Access)
require_once 'check_access.php';
$idOrden = $_GET['id'] ?? null;
?>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-3">
        <div>
            <h5 class="text-white mb-0" id="lblTituloOrden"><?= $idOrden ? "EDITAR BORRADOR #$idOrden" : "NUEVA ORDEN DE PRODUCCIÓN" ?></h5>
            <small class="text-accent opacity-75">La orden se guarda como BORRADOR y no toca stock hasta que se confirme.</small>
        </div>
        <button onclick="loadModule('produccion_admin.php')" class="btn btn-outline-secondary btn-sm px-3 rounded-pill">
            <i class="fas fa-times me-1"></i> CANCELAR
        </button>
    </div>

    <form id="frmOrden">
        <input type="hidden" id="ordenId" value="<?= htmlspecialchars($idOrden ?? '') ?>">
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <label class="form-label-custom">Fecha Inicio</label>
                <input type="date" class="form-control" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label-custom">Fecha Fin (Estimada)</label>
                <input type="date" class="form-control" name="fecha_fin" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label-custom">Notas / Observaciones</label>
                <input type="text" class="form-control" name="notas" placeholder="Ej: Lote especial...">
            </div>
        </div>

        <div class="row">
            <!-- SALIDAS (PRODUCTOS A FABRICAR) -->
            <div class="col-md-6">
                <div class="card card-filtros p-3 h-100">
                    <h6 class="text-success mb-3"><i class="fas fa-box-open me-2"></i>PRODUCTOS A OBTENER (SALIDAS)</h6>

                    <div class="mb-2 d-none" id="wrap-cmb-deposito-pt">
                        <label class="form-label-custom">DEPÓSITO DESTINO (PRODUCTO TERMINADO)</label>
                        <select class="form-select form-select-sm" id="cmbDepositoPT"></select>
                    </div>

                    <div class="input-group mb-2">
                        <select class="form-select select2-prod" id="cmbSalida"></select>
                        <input type="number" class="form-control" id="cantSalida" placeholder="Cant" style="max-width: 80px;">
                        <button type="button" class="btn btn-success" onclick="addSalida()"><i class="fas fa-plus"></i></button>
                    </div>

                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-dark table-striped small">
                            <thead><tr><th>PRODUCTO</th><th class="text-center">CANT</th><th></th></tr></thead>
                            <tbody id="bodySalidas"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ENTRADAS (INSUMOS A CONSUMIR) -->
            <div class="col-md-6">
                <div class="card card-filtros p-3 h-100">
                    <h6 class="text-danger mb-3"><i class="fas fa-cubes me-2"></i>INSUMOS A CONSUMIR (ENTRADAS)</h6>

                    <div class="mb-2 d-none" id="wrap-cmb-deposito-mp">
                        <label class="form-label-custom">DEPÓSITO ORIGEN (INSUMOS)</label>
                        <select class="form-select form-select-sm" id="cmbDepositoMP"></select>
                    </div>

                    <div class="input-group mb-2">
                        <select class="form-select select2-prod" id="cmbEntrada"></select>
                        <input type="number" class="form-control" id="cantEntrada" placeholder="Cant" style="max-width: 80px;">
                        <button type="button" class="btn btn-danger" onclick="addEntrada()"><i class="fas fa-plus"></i></button>
                    </div>

                    <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                        <table class="table table-sm table-dark table-striped small">
                            <thead><tr><th>INSUMO</th><th class="text-center">CANT</th><th></th></tr></thead>
                            <tbody id="bodyEntradas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-4">
            <button type="submit" class="btn btn-info btn-lg px-5 fw-bold rounded-pill">
                <i class="fas fa-save me-2"></i> GUARDAR BORRADOR
            </button>
        </div>
    </form>
</div>

<script>
    (function() {
        let salidas = [];
        let entradas = [];

        const idOrden = document.getElementById('ordenId').value || null;

        // Inicializar Select2 con búsqueda AJAX
        $('.select2-prod').select2({
            dropdownParent: $('#main-content'),
            width: 'calc(100% - 130px)', // Ajuste visual por los botones
            ajax: {
                url: 'api_productos.php', // Reusamos búsqueda productos
                dataType: 'json',
                delay: 250,
                data: function(params) { return { q: params.term }; },
                processResults: function(data) {
                    return { results: data.data.map(p => ({ id: p.id, text: p.codigo + ' - ' + p.descripcion, raw: p })) };
                }
            },
            placeholder: 'Buscar...',
            minimumInputLength: 1
        }).on('select2:select', function(e) {
            $(this).data('raw', e.params.data.raw);
        });

        // Depósitos de MP/PT: solo visibles si el tenant tiene multidepósito activado.
        // Ambos se precargan con el depósito de trabajo del usuario.
        (async function cargarDepositos() {
            try {
                const headers = { 'X-Requested-With': 'XMLHttpRequest' };
                const estado = await fetch('api_depositos.php?action=estado_global', { headers }).then(r => r.json());
                if (!estado.habilitado) return;

                const [permitidos, miEstado] = await Promise.all([
                    fetch('api_depositos.php?action=list_permitidos', { headers }).then(r => r.json()),
                    fetch('api_depositos.php?action=mi_estado', { headers }).then(r => r.json())
                ]);

                [$('#cmbDepositoMP'), $('#cmbDepositoPT')].forEach(cmb => {
                    cmb.empty();
                    (permitidos.data || []).forEach(d => cmb.append(new Option(d.nombre, d.id)));
                    if (miEstado.id_deposito) cmb.val(miEstado.id_deposito);
                });
                $('#wrap-cmb-deposito-mp, #wrap-cmb-deposito-pt').removeClass('d-none');
            } catch (e) { console.error('No se pudo cargar el depósito', e); }
        })();

        window.addSalida = async () => {
            const id = $('#cmbSalida').val();
            const text = $('#cmbSalida option:selected').text();
            const cant = parseFloat($('#cantSalida').val());

            if(!id || !cant) return;

            // Bloqueo UI
            Swal.fire({title: 'Buscando Receta...', text: 'Verificando fórmulas existentes', didOpen: () => Swal.showLoading(), background: '#001020', color: '#fff'});
            
            try {
                const resp = await fetch(`api_produccion.php?action=get_receta_por_producto&id_producto=${id}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
                const res = await resp.json();
                Swal.close();
                
                let cargarInsumos = false;
                let agregarSalida = true;

                if (res.found && res.insumos.length > 0) {
                    const prompt = await Swal.fire({
                        title: 'Receta Encontrada',
                        text: `Existe una fórmula para ${text}. ¿Cómo desea proceder?`,
                        icon: 'question',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Usar Receta (Automático)',
                        denyButtonText: 'Carga Manual',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#28a745',
                        denyButtonColor: '#17a2b8',
                        cancelButtonColor: '#d33',
                        background: '#001020', color: '#fff'
                    });

                    if (prompt.isConfirmed) {
                        cargarInsumos = true;
                    } else if (prompt.isDenied) {
                        cargarInsumos = false; // Solo agrega salida, insumos a mano
                    } else {
                        agregarSalida = false; // Canceló todo
                    }
                }

                if (agregarSalida) {
                    // 1. Agregar Salida
                    salidas.push({ id_producto: id, nombre: text, cantidad: cant });

                    // 2. Agregar Insumos (si eligió receta)
                    if (cargarInsumos) {
                        res.insumos.forEach(i => {
                            const totalNecesario = parseFloat(i.cantidad) * cant;
                            
                            // Verificar si el insumo ya existe para sumar
                            const idx = entradas.findIndex(e => e.id_producto == i.id_producto);
                            if (idx >= 0) {
                                entradas[idx].cantidad += totalNecesario;
                            } else {
                                entradas.push({
                                    id_producto: i.id_producto,
                                    nombre: i.codigo + ' - ' + i.descripcion,
                                    cantidad: totalNecesario
                                });
                            }
                        });
                        
                        Swal.fire({
                            icon: 'success', 
                            title: 'Receta aplicada', 
                            text: 'Se cargaron los insumos automáticamente.',
                            toast: true, position: 'top-end', timer: 2000, showConfirmButton: false,
                            background: '#001020', color: '#fff'
                        });
                    }

                    renderTables();
                    
                    // Reset UI
                    $('#cmbSalida').val(null).trigger('change');
                    $('#cantSalida').val('');
                }

            } catch(e) { 
                Swal.close();
                console.error(e);
                // Si falla API, permitir carga manual por defecto
                salidas.push({ id_producto: id, nombre: text, cantidad: cant });
                renderTables();
            }
        }

        window.addEntrada = () => {
            const id = $('#cmbEntrada').val();
            const text = $('#cmbEntrada option:selected').text();
            const cant = parseFloat($('#cantEntrada').val());
            if(!id || !cant) return;
            const raw = $('#cmbEntrada').data('raw');
            const stockDisponible = raw ? parseFloat(raw.Stock || 0) : null;
            entradas.push({ id_producto: id, nombre: text, cantidad: cant, stock_disponible: stockDisponible });
            renderTables();
            $('#cmbEntrada').val(null).trigger('change');
            $('#cantEntrada').val('');
        }

        window.delSalida = (idx) => { salidas.splice(idx, 1); renderTables(); }
        window.delEntrada = (idx) => { entradas.splice(idx, 1); renderTables(); }

        function renderTables() {
            const tbS = document.getElementById('bodySalidas');
            const tbE = document.getElementById('bodyEntradas');

            tbS.innerHTML = salidas.map((i,x) => `<tr><td>${i.nombre}</td><td class="text-center">${i.cantidad}</td><td><button type="button" class="btn btn-link text-danger p-0" onclick="delSalida(${x})"><i class="fas fa-times"></i></button></td></tr>`).join('');

            tbE.innerHTML = entradas.map((i,x) => {
                const stockHint = (i.stock_disponible !== undefined && i.stock_disponible !== null)
                    ? `<br><small class="text-muted">Stock disp.: ${parseFloat(i.stock_disponible).toFixed(2)}</small>` : '';
                return `<tr><td>${i.nombre}${stockHint}</td><td class="text-center">${i.cantidad}</td><td><button type="button" class="btn btn-link text-danger p-0" onclick="delEntrada(${x})"><i class="fas fa-times"></i></button></td></tr>`;
            }).join('');
        }

        async function cargarBorradorExistente() {
            try {
                const resp = await fetch(`api_produccion.php?action=get_orden_detalle&id=${idOrden}`, { headers: {'X-Requested-With': 'XMLHttpRequest'} });
                const res = await resp.json();
                if (!res.success) { Swal.fire('Error', 'No se pudo cargar la orden.', 'error'); loadModule('produccion_admin.php'); return; }

                const d = res.data;
                if (parseInt(d.cabecera.estado) !== 1) {
                    Swal.fire({icon:'info', title:'Ya no es un borrador', text:'Esta orden ya fue confirmada y no se puede editar desde aquí.', background: '#001020', color: '#fff'});
                    loadModule('produccion_admin.php');
                    return;
                }

                document.querySelector('[name=fecha_inicio]').value = (d.cabecera.fecha_inicio_prevista || '').substring(0,10);
                document.querySelector('[name=fecha_fin]').value = (d.cabecera.fecha_fin_prevista || '').substring(0,10);
                document.querySelector('[name=notas]').value = d.cabecera.notas || '';

                salidas = d.salidas.map(s => ({ id_producto: s.id_producto, nombre: s.codigo + ' - ' + s.descripcion, cantidad: parseFloat(s.cantidad_a_producir) }));
                entradas = d.entradas.map(e => ({ id_producto: e.id_producto, nombre: e.codigo + ' - ' + e.descripcion, cantidad: parseFloat(e.cantidad_requerida), stock_disponible: parseFloat(e.stock_actual) }));
                renderTables();
            } catch (err) {
                Swal.fire('Error', 'No se pudo comunicar con el servidor.', 'error');
            }
        }

        document.getElementById('frmOrden').onsubmit = async (e) => {
            e.preventDefault();
            if (salidas.length === 0) { Swal.fire('Error', 'Debe haber al menos un producto de salida', 'warning'); return; }

            const data = {
                id: idOrden,
                fecha_inicio: document.querySelector('[name=fecha_inicio]').value,
                fecha_fin: document.querySelector('[name=fecha_fin]').value,
                notas: document.querySelector('[name=notas]').value,
                salidas: salidas,
                entradas: entradas,
                id_deposito_mp: $('#cmbDepositoMP').val() || null,
                id_deposito_pt: $('#cmbDepositoPT').val() || null
            };

            try {
                const resp = await fetch('api_produccion.php?action=guardar_orden', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    body: JSON.stringify(data)
                });
                const res = await resp.json();
                if(res.success) {
                    Swal.fire({icon:'success', title: 'Borrador guardado', text: res.message, background: '#001020', color: '#fff'});
                    loadModule('produccion_admin.php');
                } else Swal.fire('Error', res.error, 'error');
            } catch(err) { Swal.fire('Error', 'Fallo de conexión', 'error'); }
        }

        if (idOrden) cargarBorradorExistente();

    })();
</script>