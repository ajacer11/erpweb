<?php
/**
 * api_facturacion_validaciones_previas.php - MOTOR DE AUDITORÍA PRE-GRABACIÓN (V19.5)
 * REGLA 10 y 11: INTEGRIDAD TOTAL. No se elimina lógica de Pedidos ni Ajustes previos.
 * ACTUALIZACIÓN V19.5: Validación de propiedad y saltos de precio para PRESUPUESTOS.
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';
require_once 'stock_deposito_helpers.php';
require_once 'tarjetas_helpers.php';

/**
 * Margen admitido, por unidad, entre el neto de lista guardado en el producto y
 * el neto que manda la pantalla de facturacion. Existe porque las dos puntas
 * calculan ese numero distinto: el producto guarda el neto, y el buscador lo
 * deriva del precio final. Cubre la diferencia de redondeo, no una edicion
 * manual de precio, que siempre mueve mucho mas que esto.
 */
define('TOLERANCIA_PRECIO_LISTA', 0.05);

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input)
        throw new Exception("No se recibieron datos para validar.");

    if (multidepositoHabilitado($pdo)) {
        $emisor = datosEmisorFacturacion($pdo, $_SESSION['usuario_nombre'] ?? '');
        if (!$emisor['completo']) {
            throw new Exception('Complete los datos de la empresa o del depósito antes de facturar: ' . implode(', ', $emisor['faltantes']) . '.');
        }
    }

    $cliente_id = $input['cliente_id'];
    $vendedor_id = $input['vendedor_id'];
    $tipo_comp = (int) $input['tipo_comp'];
    $items = $input['items'] ?? [];

    // REGLA: los importes deben ser numéricos. Un "" (p.ej. típeo con coma
    // decimal en vez de punto en el front) no puede colarse como pago válido.
    $imp1raw = $input['imp1'] ?? '';
    $imp2raw = $input['imp2'] ?? '';
    if ($imp1raw === '' || !is_numeric($imp1raw)) {
        throw new Exception("El importe del Medio de pago 1 no es un número válido.");
    }
    if (!empty($input['mp2']) && (!is_numeric($imp2raw))) {
        throw new Exception("El importe del Medio de pago 2 no es un número válido.");
    }
    if ((float) $imp1raw < 0 || (is_numeric($imp2raw) && (float) $imp2raw < 0)) {
        throw new Exception("Los importes de los medios de pago no pueden ser negativos.");
    }
    $pagos = [
        ['id' => $input['mp1'], 'imp' => (float) $imp1raw],
        ['id' => $input['mp2'], 'imp' => is_numeric($imp2raw) ? (float) $imp2raw : 0]
    ];
    $total_doc = (float) $input['total_comprobante'];
    $associated = $input['associatedDoc'] ?? null;
    $adj = $input['globalAdj'] ?? ['desc' => 0, 'reca' => 0];

    // 1. OBTENER CONFIGURACIÓN ACTUALIZADA
    $stmtCfg = $pdo->query("SELECT * FROM configuracion WHERE id = (SELECT MAX(id) FROM configuracion) LIMIT 1");
    $config = $stmtCfg->fetch(PDO::FETCH_ASSOC);

    // 2. VALIDAR CLIENTE
    $stmtCli = $pdo->prepare("SELECT * FROM alb_clientes WHERE id = ?");
    $stmtCli->execute([$cliente_id]);
    $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);

    if (!$cli)
        throw new Exception("El cliente seleccionado no existe.");
    if ((int) $cli['habilitado'] === 0)
        throw new Exception("El cliente [{$cli['razon']}] está inhabilitado.");

    // REGLA 15: Validar propiedad del pedido si existe
    if (!empty($input['pedido_id'])) {
        $stmtPed = $pdo->prepare("SELECT cliente FROM pedidosclientes WHERE numpedido = ?");
        $stmtPed->execute([$input['pedido_id']]);
        $realCliente = $stmtPed->fetchColumn();
        if ($realCliente != $cliente_id) {
            throw new Exception("Error de seguridad: El cliente enviado no coincide con el dueño del pedido origen.");
        }
    }

    // REGLA 15: Validar propiedad del presupuesto si existe (NUEVO V19.5)
    if (!empty($input['presupuesto_id'])) {
        $stmtPre = $pdo->prepare("SELECT cliente FROM presupuestosclientes WHERE numpedido = ?");
        $stmtPre->execute([$input['presupuesto_id']]);
        $realClientePre = $stmtPre->fetchColumn();
        if ($realClientePre != $cliente_id) {
            throw new Exception("Error de seguridad: El cliente enviado no coincide con el dueño del presupuesto origen.");
        }
    }

    // Validar Lista si modiflistas = 0 (Se saltea si es Pedido o Presupuesto)
    if ((int) $config['modiflistas'] === 0 && empty($input['pedido_id']) && empty($input['presupuesto_id'])) {
        if ((int) $input['lista_global'] !== (int) $cli['ListaDePRecios']) {
            throw new Exception("No tiene permitido cambiar la lista de precios. Debe usar la Lista " . $cli['ListaDePRecios']);
        }
    }

    $tipo_comp = (int) ($input['tipo_comp'] ?? 0);
    if ($tipo_comp < 1) {
        throw new Exception("Seleccione un tipo de comprobante antes de emitir.");
    }
    if (in_array($tipo_comp, [2, 7, 12], true) && (int) ($config['admitenotasdebito'] ?? 0) !== 1) {
        throw new Exception("Las notas de débito no están habilitadas en la Configuración General.");
    }

    // 3. VALIDAR VENDEDOR
    $stmtVen = $pdo->prepare("SELECT * FROM vendedores WHERE id = ?");
    $stmtVen->execute([$vendedor_id]);
    $ven = $stmtVen->fetch(PDO::FETCH_ASSOC);
    if (!$ven)
        throw new Exception("Debe seleccionar un vendedor válido.");

    // 4. VALIDAR PRODUCTOS (LOOP)
    if (empty($items))
        throw new Exception("El comprobante no tiene artículos.");

    foreach ($items as $it) {
        if ($it['codigo'] === 'LM') {
            continue;
        }

        $stmtArt = $pdo->prepare("SELECT * FROM alb_productos WHERE codigo = ?");
        $stmtArt->execute([$it['codigo']]);
        $art = $stmtArt->fetch(PDO::FETCH_ASSOC);

        if (!$art)
            throw new Exception("El artículo [{$it['codigo']}] no existe.");
        if ((int) $art['habilitado'] === 0)
            throw new Exception("El artículo [{$it['descripcion']}] está inhabilitado.");

        if (isset($it['descripcion']) && trim($it['descripcion']) !== trim($art['descripcion'])) {
            if ((int) $config['permitireditardescripcion'] !== 1 || !puedeEditarDescripcionFacturar()) {
                throw new Exception("No tiene permiso para modificar la descripción del artículo [{$art['descripcion']}].");
            }
        }

        // Las notas de crédito (3, 8, 13 y ticket de crédito 101) son
        // devoluciones: reingresan mercadería, no consumen stock.
        if ((int) $config['stocknegativo'] === 0 && !in_array($tipo_comp, [3, 8, 13, 101], true)) {
            $stockActual = (float) ($art['Stock'] ?? $art['stock'] ?? 0);
            if ($stockActual < (float) $it['cantidad']) {
                throw new Exception("Stock insuficiente para [{$it['descripcion']}]. Disponible: " . $stockActual);
            }
        }

        // VALIDACIÓN DE PRECIO (Se saltea si es Pedido o Presupuesto, ya que vienen con precio pactado)
        if ((int) $config['modificarprecioenventas'] === 0 && empty($input['pedido_id']) && empty($input['presupuesto_id'])) {
            $col_lista = ($it['lista'] === 1) ? 'siniva' : 'siniva' . $it['lista'];
            // El buscador de facturacion (api_facturar_gets.php, normalizarListasParaVenta)
            // no manda el neto guardado: lo recalcula desde el precio final como
            // ($final - $impInterno) / (1 + $alicuota), redondeado a 3 decimales.
            // Cuando el neto y el precio final guardados del producto no cierran
            // entre si, ese recalculo cae en otro centavo y esta comparacion
            // rechazaba ventas que el vendedor nunca habia modificado. El caso
            // tipico son los cigarrillos, donde el impuesto interno es varias veces
            // el neto y amplifica cualquier medio centavo perdido al dividir.
            // Por eso se compara con tolerancia en lugar de exigir igualdad exacta.
            $netoGuardado = (float) ($art[$col_lista] ?? 0);
            $netoEnviado  = (float) $it['precio_base'];

            if (abs($netoGuardado - $netoEnviado) > TOLERANCIA_PRECIO_LISTA) {
                throw new Exception("El precio del artículo [{$it['codigo']}] no coincide con la Lista {$it['lista']}. Su perfil no permite modificaciones manuales de precio.");
            }
        }

        $col_max_desc = 'maxdescl' . $it['lista'];
        $max_permitido = (float) ($art[$col_max_desc] ?? 100);
        if ((float) $it['pordesc'] > $max_permitido) {
            throw new Exception("Descuento excedido en [{$it['codigo']}]. Máximo para Lista {$it['lista']}: {$max_permitido}%");
        }
    }

    // 5. VALIDAR AJUSTES GLOBALES
    if ((float) $adj['desc'] > 0 && (int) $config['descuentosglobales'] === 0)
        throw new Exception("Los descuentos globales están desactivados en la configuración.");
    if ((float) $adj['reca'] > 0 && (int) $config['recargosglobales'] === 0)
        throw new Exception("Los recargos globales están desactivados en la configuración.");

    // 6. VALIDAR PAGOS Y CUENTA CORRIENTE
    $total_pagado = 0;
    $cantidad_pagos_validos = 0; 
    foreach ($pagos as $p) {
        if (!empty($p['id'])) {
            $cantidad_pagos_validos++;
            $stmtMP = $pdo->prepare("SELECT * FROM mediosdepago WHERE id = ?");
            $stmtMP->execute([$p['id']]);
            $mp = $stmtMP->fetch(PDO::FETCH_ASSOC);

            if (!$mp)
                throw new Exception("Medio de pago no válido.");
            if ((int) $mp['habilitado'] === 0)
                throw new Exception("El medio de pago [{$mp['medio']}] está deshabilitado.");

            if ($mp['tipodecuenta'] === 'Cuenta Corriente') {
                if ((int) $cli['cuentacorriente'] === 0)
                    throw new Exception("El cliente no tiene habilitada la Cuenta Corriente.");
            }
            if ($p['imp'] > 0) {
                $nMedio = ($p['id'] == ($input['mp1'] ?? null)) ? 1 : 2;
                datosTarjetaCobro($pdo, $mp, $input["tarjeta$nMedio"] ?? null, "el medio de pago $nMedio");
            }
            $total_pagado += $p['imp'];
        }
    }

    if ($cantidad_pagos_validos === 0) {
        throw new Exception("Debe seleccionar al menos un medio de pago para procesar el cobro de la factura.");
    }

    if (abs($total_pagado - $total_doc) > 0.01) {
        throw new Exception("El total pagado ($" . number_format($total_pagado, 2) . ") no coincide con el total del comprobante ($" . number_format($total_doc, 2) . ")");
    }

    // 7. VALIDAR COMPROBANTE ASOCIADO (NC/ND)
    $asociables = [2, 3, 7, 8, 12, 13, 101];
    if (in_array($tipo_comp, $asociables)) {
        if (!$associated)
            throw new Exception("Debe asociar un comprobante para realizar esta operación.");

        $valid_type = false;
        switch ($tipo_comp) {
            case 2:
            case 3:
                if ((int) $associated['tipo'] === 1)
                    $valid_type = true;
                break; 
            case 7:
            case 8:
                if ((int) $associated['tipo'] === 6)
                    $valid_type = true;
                break; 
            case 12:
            case 13:
                if ((int) $associated['tipo'] === 11)
                    $valid_type = true;
                break; 
            case 101:
                if ((int) $associated['tipo'] === 100)
                    $valid_type = true;
                break; 
        }
        if (!$valid_type)
            throw new Exception("El tipo de comprobante asociado no es válido para este documento.");

        $stmtTkt = $pdo->prepare("SELECT cliente FROM tickets WHERE ptovta = ? AND numcomp = ? AND tipocomp = ?");
        $stmtTkt->execute([(int) $associated['pv'], (int) $associated['num'], (int) $associated['tipo']]);
        $tkt_asoc = $stmtTkt->fetch(PDO::FETCH_ASSOC);

        if (!$tkt_asoc)
            throw new Exception("El comprobante asociado no existe en la base de datos.");
        if ((int) $tkt_asoc['cliente'] !== (int) $cliente_id)
            throw new Exception("El comprobante asociado pertenece a otro cliente.");
    }

    echo json_encode(['success' => true, 'message' => 'Validaciones correctas']);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
