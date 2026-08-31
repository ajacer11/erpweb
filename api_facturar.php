<?php

    /**
     * api_facturar.php - MOTOR TRANSACCIONAL DE VENTAS (V63)
     * FIX V63: pv_emisor desde sesión en lugar de hardcode.
     * BASE: V61 (Fidelidad Absoluta)
     * ACTUALIZACIÓN V62: Gestión Estricta de Caja de Usuarios (Autodetección, Validación y Registro de Movimientos de Fondos).
     * REGLA 10: Integridad total. Prohibido omitir código.
     * REGLA 11: No eliminar lógica previa.
     * CORRECCIÓN: Restauración de los nombres exactos de columnas de la DB Legacy.
     */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'stock_deposito_helpers.php';
    require_once 'arca_certificados.php';

    // ---------------------------------------------------------------------------
    // 0. AUTODETECCIÓN DE CAJA ABIERTA (BLINDAJE DE SESIÓN)
    // ---------------------------------------------------------------------------
    $idcajaabierta = 0;
    $idcontrolcaja = 0;
    $usuario_nombre = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

    if ($idcajaabierta == 0) {
        // Buscar en tabla 'cajas' si el usuario figura como activo en alguna
        $stmtBox = $pdo->prepare("SELECT id FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
        $stmtBox->execute([$usuario_nombre]);
        $cajaIdDB = $stmtBox->fetchColumn();

        if ($cajaIdDB) {
            $idcajaabierta = $cajaIdDB;
            // Recuperar el ID de control (auditoría) asociado
            $stmtCtrl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? ORDER BY id DESC LIMIT 1");
            $stmtCtrl->execute([$idcajaabierta, $usuario_nombre]);
            $idcontrolcaja = $stmtCtrl->fetchColumn() ?: 0;
        }
    }
    // ---------------------------------------------------------------------------

    /**
     * Función interna de recálculo (Réplica exacta de facturacion.vb -> calculagrilla/calculatotales)
     */
    function recalcularLineaServidor($it, $art, $config, $tipo_comp)
    {
        $tktsiniva = ((int) ($config['ticketsiniva'] ?? 0) === 1 && ($tipo_comp === 100 || $tipo_comp === 101));
        $poriva = $tktsiniva ? 0 : (float) ($art['poriva'] ?? $art['Poriva'] ?? 0);
        $netoU = round((float) $it['precio_base'], 2);
        $iiU = round((float) ($art['ImpInt'] ?? $art['impint'] ?? 0), 2);
        $finalOriginalU = round($netoU * (1 + $poriva / 100) + $iiU, 2);
        $descU = round(((float) $it['pordesc'] * $finalOriginalU) / 100, 2);
        $baseNetoIvaU = round($netoU * (1 + $poriva / 100), 2);

        if ($descU <= $baseNetoIvaU) {
            $netoEfU = round(($baseNetoIvaU - $descU) / (1 + $poriva / 100), 2);
            $ivaEfU = round($netoEfU * ($poriva / 100), 2);
            $iiEfU = $iiU;
        } else {
            $netoEfU = 0;
            $ivaEfU = 0;
            $iiEfU = round(max(0, $iiU - ($descU - $baseNetoIvaU)), 2);
        }

        $totalLin = round(($netoEfU + $ivaEfU + $iiEfU) * (float) $it['cantidad'], 2);
        return [
            'total' => $totalLin,
            'neto' => round($netoEfU * $it['cantidad'], 2),
            'iva' => round($ivaEfU * $it['cantidad'], 2),
            'ii' => round($iiEfU * $it['cantidad'], 2),
            'alicuota' => $poriva,
            'finalOriginalU' => $finalOriginalU,
            'descU' => $descU
        ];
    }

    // GrabarLineaAsiento() ahora vive en contabilidad_cuentas_helpers.php como
    // grabarLineaAsiento() (ya incluido arriba). PHP no distingue mayúsculas de
    // minúsculas en nombres de función, así que esta copia local duplicaba el
    // nombre y provocaba "Cannot redeclare grabarLineaAsiento()" al facturar.

    /**
     * Consulta el próximo número a ARCA (Sincronización Externa)
     */
    function obtenerProximoComprobanteARCA($cuit, $pv, $tipo, $modo)
    {
        $ch = curl_init("https://erp.westit.com.ar/ARCA/fe_prox_comprobante.php");
        $payload = [
            'emisorCuit' => $cuit,
            'puntoVenta' => $pv,
            'cbteTipo' => $tipo,
            'produccion' => $modo
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_TIMEOUT => 12,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $data = json_decode($resp, true);
        if ($data && isset($data['resultado']) && $data['resultado'] === 'A') {
            return (int) $data['proximoComprobante'];
        }
        return null;
    }

    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input)
            throw new Exception("No se recibieron datos transaccionales.");

        $tipo_comp = (int) ($input['tipo_comp'] ?? 0);
        if ($tipo_comp < 1) {
            throw new Exception("Debe seleccionar un tipo de comprobante válido antes de emitir.");
        }
        if (!in_array($tipo_comp, [100, 101], true)
            && !arcaCredencialesFiscalesDisponibles($_SESSION['cliente_cuit'] ?? '')) {
            throw new Exception("La empresa no tiene configurados los certificados de ARCA. Solo puede emitir Ticket o Ticket de Crédito.");
        }

        // Esta validación debe vivir también en el endpoint que graba: la auditoría
        // previa del navegador puede omitirse invocando esta API directamente.
        $tiposConAsociacion = [2 => 1, 3 => 1, 7 => 6, 8 => 6, 12 => 11, 13 => 11, 101 => 100];
        $associated = $input['associatedDoc'] ?? null;
        if (isset($tiposConAsociacion[$tipo_comp])) {
            if (!is_array($associated)) {
                throw new Exception("Debe asociar el comprobante original antes de emitir la Nota de Crédito/Débito.");
            }

            $tipoAsoc = (int) ($associated['tipo'] ?? 0);
            $pvAsoc = (int) ($associated['pv'] ?? 0);
            $numAsoc = (int) ($associated['num'] ?? 0);
            if ($tipoAsoc !== $tiposConAsociacion[$tipo_comp] || $pvAsoc < 1 || $numAsoc < 1) {
                throw new Exception("El comprobante asociado no es válido para el tipo de Nota seleccionado.");
            }

            $stmtAsoc = $pdo->prepare("SELECT cliente, fecha FROM tickets WHERE PtoVta = ? AND numcomp = ? AND tipocomp = ? LIMIT 1");
            $stmtAsoc->execute([$pvAsoc, $numAsoc, $tipoAsoc]);
            $ticketAsoc = $stmtAsoc->fetch(PDO::FETCH_ASSOC);
            if (!$ticketAsoc) {
                throw new Exception("El comprobante asociado no existe.");
            }
            if ((int) $ticketAsoc['cliente'] !== (int) ($input['cliente_id'] ?? 0)) {
                throw new Exception("El comprobante asociado pertenece a otro cliente.");
            }

            // La fecha se toma de la base, no del dato manipulable enviado por el cliente.
            $associated['fecha'] = substr((string) $ticketAsoc['fecha'], 0, 10);
        }

        $pv_emisor = (int) $_SESSION['pdv'];

        // --- 1. SINCRONIZACIÓN EXTERNA (PRE-TRANSACCIÓN) ---
        $numcomp = 0;
        if (!in_array($tipo_comp, [100, 101])) {
            $numcomp = obtenerProximoComprobanteARCA($_SESSION['cliente_cuit'], $pv_emisor, $tipo_comp, $_SESSION['modo']);
        }

        // Si ARCA falla o es interno, usamos el contador local
        if (!$numcomp) {
            // CORRECCIÓN DB: PtoVta
            $stmtNum = $pdo->prepare("SELECT COALESCE(MAX(numcomp), 0) + 1 FROM tickets WHERE PtoVta = ? AND tipocomp = ?");
            $stmtNum->execute([$pv_emisor, $tipo_comp]);
            $numcomp = (int) $stmtNum->fetchColumn();
        }

        // --- 2. CUENTAS PREDETERMINADAS ---
        $stmtPred = $pdo->query("SELECT * FROM cuentaspredeterminadas WHERE id = 1 LIMIT 1");
        $cp = $stmtPred->fetch(PDO::FETCH_ASSOC);
        if (!$cp)
            throw new Exception("No se configuraron las cuentas contables predeterminadas.");


        // --- 3. VALIDACIÓN DE EJERCICIO Y PERÍODO ---
        $fechaFactura = $input['fecha'];
        $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
        $stmtEjer->execute([$fechaFactura, $fechaFactura]);
        $idEjercicio = (int) $stmtEjer->fetchColumn();
        if (!$idEjercicio)
            throw new Exception("No hay un ejercicio contable abierto para esta fecha.");
        $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
        $stmtPer->execute([$idEjercicio]);
        $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);
        if (!$rowPer)
            throw new Exception("No se encontró la estructura de períodos.");

        $fechaDT = new DateTime($fechaFactura);
        $mesesDesdeInicio = ((int) $fechaDT->format('Y') - (int) $rowPer['anioinicio']) * 12 + ((int) $fechaDT->format('m') - (int) $rowPer['mesinicio']);
        $periodoactual = $mesesDesdeInicio + 1;

        if ($periodoactual < 1 || $periodoactual > (int) $rowPer['cantperiodos'])
            throw new Exception("La fecha no pertenece a ningún período del ejercicio.");
        $campoP = "p" . $periodoactual;
        if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0)
            throw new Exception("El período contable {$periodoactual} está cerrado.");

        // --- 4. MAESTROS Y MEDIOS ---
        $resolverMP = function ($id) use ($pdo) {
            if (!$id)
                return ['nombre' => '', 'tipo' => '', 'cuenta' => ''];
            $st = $pdo->prepare("SELECT medio, tipodecuenta, CuentaContable FROM mediosdepago WHERE id = ?");
            $st->execute([$id]);
            $r = $st->fetch(PDO::FETCH_ASSOC);
            return $r ? ['nombre' => $r['medio'], 'tipo' => $r['tipodecuenta'], 'cuenta' => $r['CuentaContable']] : ['nombre' => '', 'tipo' => '', 'cuenta' => ''];
        };
        $mp1 = $resolverMP($input['mp1']);
        $mp2 = $resolverMP($input['mp2']);

        $stmtCfg = $pdo->query("SELECT * FROM configuracion WHERE id = (SELECT MAX(id) FROM configuracion) LIMIT 1");
        $config = $stmtCfg->fetch(PDO::FETCH_ASSOC);
        $stmtCli = $pdo->prepare("SELECT * FROM alb_clientes WHERE id = ?");
        $stmtCli->execute([$input['cliente_id']]);
        $cli = $stmtCli->fetch(PDO::FETCH_ASSOC);
        if (!$cli)
            throw new Exception("Cliente no encontrado.");

        // --- VALIDACIÓN FISCAL: tipo de comprobante vs condición fiscal empresa/cliente ---
        // Esta regla ya se aplica en el navegador (arma el combo de "Tipo comprobante" en
        // newfactura.js), pero antes no se repetía acá: invocando esta API directamente se
        // podía forzar una Factura A/B/C que no correspondía a la combinación real. Las Notas
        // de Crédito/Débito no se validan con esta regla porque ya quedan cubiertas más arriba
        // (deben asociarse a un comprobante existente de la misma letra).
        if (in_array($tipo_comp, [1, 6, 11], true)) {
            $condIvaEmpresa = strtoupper((string) $pdo->query("SELECT condicioniva FROM datosempresa WHERE id = 1 LIMIT 1")->fetchColumn());
            $empresaEsRI = strpos($condIvaEmpresa, 'INSCRIPTO') !== false;

            $condCliente = strtoupper((string) ($cli['condicionfiscal'] ?? ''));
            $clienteEsRIMono = strpos($condCliente, 'INSCRIPTO') !== false || strpos($condCliente, 'MONOTRIBUTO') !== false;

            $tipoEsperado = $empresaEsRI ? ($clienteEsRIMono ? 1 : 6) : 11;
            if ($tipo_comp !== $tipoEsperado) {
                $nombresTipo = [1 => 'Factura A', 6 => 'Factura B', 11 => 'Factura C'];
                throw new Exception("El tipo de comprobante no corresponde a la condición fiscal de la empresa y el cliente. Debería emitirse: {$nombresTipo[$tipoEsperado]}.");
            }
        }

        // --- 5. RECÁLCULO Y LOCK DE PRODUCTOS ---
        $processedItems = [];
        $acum = ['sub21' => 0, 'sub10' => 0, 'sub0' => 0, 'iva21' => 0, 'iva10' => 0, 'ii' => 0, 'total_items' => 0];

        foreach ($input['items'] as $it) {
            if ($it['codigo'] === 'LM') {
                $art = [
                    'codigo' => 'LM',
                    'descripcion' => $it['descripcion'],
                    'rubro' => 'Otros',
                    'marca' => '1',
                    'preciocosto' => 0,
                    'stock' => 0,
                    'controlastock' => 0,
                    'poriva' => (float) $it['poriva'],
                    'ImpInt' => 0,
                    'cuentaventa' => $cp['ventas'],
                    'cuentamerca' => $cp['mercaderia'],
                    'cuentacostomerca' => $cp['costomercaderia']
                ];
            } else {
                $stmtArt = $pdo->prepare("SELECT * FROM alb_productos WHERE codigo = ? FOR UPDATE");
                $stmtArt->execute([$it['codigo']]);
                $art = $stmtArt->fetch(PDO::FETCH_ASSOC);
                if (!$art)
                    throw new Exception("Artículo no encontrado: " . $it['codigo']);

                $art['poriva'] = (float) ($art['poriva'] ?? $art['Poriva'] ?? 0);
                $art['ImpInt'] = (float) ($art['ImpInt'] ?? $art['impint'] ?? 0);
                $art['preciocosto'] = (float) ($art['preciocosto'] ?? $art['Preciocosto'] ?? 0);
                $art['stock'] = (float) ($art['stock'] ?? $art['Stock'] ?? 0);

                $art['cuentaventa'] = resolverCuentaContable($pdo, $art['cuentaventas'] ?? $art['cuentaventa'] ?? null, $cp['ventas'], "ventas del producto {$art['codigo']}");
                $art['cuentamerca'] = resolverCuentaContable($pdo, $art['cuentamercaderia'] ?? $art['cuentamerca'] ?? null, $cp['mercaderia'], "mercadería del producto {$art['codigo']}");
                $art['cuentacostomerca'] = resolverCuentaContable($pdo, $art['cuentacosto'] ?? $art['cuentacostomerca'] ?? null, $cp['costomercaderia'], "costo del producto {$art['codigo']}");
            }

            $res = recalcularLineaServidor($it, $art, $config, $tipo_comp);

            if ($res['alicuota'] == 21) {
                $acum['sub21'] += $res['neto'];
                $acum['iva21'] += $res['iva'];
            } elseif ($res['alicuota'] == 10.5) {
                $acum['sub10'] += $res['neto'];
                $acum['iva10'] += $res['iva'];
            } else {
                $acum['sub0'] += $res['neto'];
            }

            $acum['ii'] += $res['ii'];
            $acum['total_items'] += $res['total'];

            $processedItems[] = ['input' => $it, 'art' => $art, 'calc' => $res];
        }

        $descG = (float) $input['globalAdj']['desc'];
        $recaG = (float) $input['globalAdj']['reca'];
        $total_final_server = round($acum['total_items'] - $descG + $recaG, 2);

        if (abs($total_final_server - (float) $input['total_comprobante']) > 0.01)
            throw new Exception("Error de integridad en totales.");

        // Los medios de pago deben cubrir exactamente el total del comprobante, o el asiento
        // (pata de pago vs. pata de ventas/IVA) queda desbalanceado sin que nada lo detecte.
        $sumaPagos = round((float) $input['imp1'] + (float) $input['imp2'], 2);
        if (abs($sumaPagos - $total_final_server) > 0.01)
            throw new Exception("Los medios de pago (\$" . number_format($sumaPagos, 2) . ") no coinciden con el total del comprobante (\$" . number_format($total_final_server, 2) . ").");

        // --- VALIDACIÓN ARCA: Consumidor Final sin identificar por encima del tope ---
        // "montominimo" en configuracion es el tope a partir del cual ARCA exige identificar
        // al comprador con su documento real; por debajo se puede seguir usando el DNI
        // genérico (99999999 / vacío). Antes no había ningún control y siempre se mandaba
        // documento 0 a ARCA para Consumidor Final sin importar el monto (ver más abajo,
        // "$docNro = ($cond == 5) ? 0 : ..."). Se valida acá, ya con el total confirmado
        // contra el servidor, para no confiar en lo que mande el navegador.
        $condClienteCF = strtoupper((string) ($cli['condicionfiscal'] ?? ''));
        $numDocCliente = trim((string) ($cli['numdoc'] ?? ''));
        $clienteSinIdentificar = $numDocCliente === '' || $numDocCliente === '99999999';
        $topeConsumidorFinal = (float) ($config['montominimo'] ?? 0);

        if ($condClienteCF === 'CONSUMIDOR FINAL' && $clienteSinIdentificar && $topeConsumidorFinal > 0 && $total_final_server > $topeConsumidorFinal) {
            throw new Exception("El total (\$" . number_format($total_final_server, 2) . ") supera el tope de \$" . number_format($topeConsumidorFinal, 2) . " que exige identificar al comprador. Cargá el DNI real del cliente en su ficha antes de facturar.");
        }

        // Depósito de trabajo del usuario (mostrador: nunca se pregunta acá, ver
        // stock_deposito_helpers.php). Se resuelve UNA sola vez, antes del bucle de
        // reintentos: si el usuario tiene más de un depósito permitido y ninguno
        // fijado todavía, esto lanza DepositoNoSeleccionadoException (Exception simple,
        // no PDOException) que se escapa del catch de reintentos de abajo sin
        // consumir ningún intento, y cae directo al catch general del archivo.
        $idDepositoVenta = null;
        if (multidepositoHabilitado($pdo)) {
            $idDepositoVenta = depositoDeTrabajo($pdo, $usuario_nombre);
        }

        // --- 6. BUCLE DE GRABADO (HASTA 3 INTENTOS POR CONCURRENCIA) ---
        $idTicket = 0;
        $successGrabado = false;
        $maxIntentos = 3;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                $pdo->beginTransaction();

                // Bloqueo de fila para el contador específico (CORRECCIÓN DB: PtoVta)
                $pdo->prepare("SELECT id FROM tickets WHERE PtoVta = ? AND tipocomp = ? ORDER BY id DESC LIMIT 1 FOR UPDATE")->execute([$pv_emisor, $tipo_comp]);

                $esNota = in_array($tipo_comp, [2, 3, 7, 8, 12, 13, 101]);
                $esAdmin = isset($input['es_admin']) && $input['es_admin'] === true;

                $stmtInsertLin = $pdo->prepare("INSERT INTO Lineadeventa (lista, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant, 
                siniva, iva, ii, total, rubro, alicuota, mpago1, mpago2, mpago22, costo, usuario, marca, totaldescuento, sinremitir, vendedor, unitariofinallista) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($processedItems as $p) {
                    $it = $p['input'];
                    $art = $p['art'];
                    $res = $p['calc'];
                    $stmtInsertLin->execute([
                        $it['lista'] ?? 1,
                        $pv_emisor,
                        $tipo_comp,
                        $numcomp,
                        $input['cliente_id'],
                        $input['fecha'],
                        $it['codigo'],
                        $it['descripcion'],
                        $it['cantidad'],
                        $res['neto'],
                        $res['iva'],
                        $res['ii'],
                        $res['total'],
                        $art['rubro'] ?? '',
                        $res['alicuota'],
                        $input['mp1'],
                        $input['mp2'],
                        $tipo_comp,
                        round($art['preciocosto'] * $it['cantidad'], 2),
                        $_SESSION['usuario_nombre'],
                        $art['Marca'] ?? 1,
                        round($res['descU'] * $it['cantidad'], 2),
                        ($esAdmin ? $it['cantidad'] : 0),
                        $input['vendedor_id'],
                        $res['finalOriginalU']
                    ]);
                    $fiscalidad = 1;
                    if ($tipo_comp == 100 || $tipo_comp == 101) {
                        $fiscalidad = 0;
                    }
                    if ($it['codigo'] !== 'LM' && (int) ($art['controlastock'] ?? $art['Controlastock'] ?? 0) === 1) {
                        $stkI = (float) $art['stock'];
                        $stkF = $esNota ? ($stkI + $it['cantidad']) : ($stkI - $it['cantidad']);
                        $pdo->prepare("INSERT INTO controlstock (numcomp, codprod, inicial, venta, final, usuario, tipocomp, diferencia) VALUES (?, ?, ?, ?, ?, ?, ?, 0)")
                            ->execute([$numcomp, $it['codigo'], $stkI, ($esNota ? $it['cantidad'] * -1 : $it['cantidad']), $stkF, $_SESSION['usuario_nombre'], $tipo_comp]);
                        $pdo->prepare("INSERT INTO movimientosdestock (codigo, rubro, entradas, salidas, tipomov, origenmov, esfiscal, tipocomp, numremito, usuario, fecha) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                            ->execute([$it['codigo'], $art['rubro'] ?? '', ($esNota ? $it['cantidad'] : 0), ($esNota ? 0 : $it['cantidad']), 'FACTURA', 'VENTA', $fiscalidad, 0, 0, $_SESSION['usuario_nombre'], date('Y-m-d H:i:s')]);
                        $idMovLegacy = (int) $pdo->lastInsertId();

                        moverStock($pdo, (int) $art['id'], $it['codigo'], $idDepositoVenta, (float) $it['cantidad'],
                                   $esNota ? 'ENTRADA' : 'SALIDA', 'FACTURA', 'VENTA', $tipo_comp, $numcomp, $_SESSION['usuario_nombre'], $idMovLegacy);
                    }

                    // Registrar uso de promoción (solo en ventas, no en NC)
                    if (!empty($it['promo_id']) && !$esNota) {
                        $promoId  = (int)$it['promo_id'];
                        $cantUsada = (int)$it['cantidad'];

                        // 1. Descontar cupo comprometido (solo si tiene límite)
                        $pdo->prepare(
                            "UPDATE promociones SET stock_usado = stock_usado + ? WHERE id = ? AND stock_comprometido IS NOT NULL"
                        )->execute([$cantUsada, $promoId]);

                        // 2. Registrar en el historial de promociones vendidas
                        $pdo->prepare(
                            "INSERT INTO promociones_ventas
                             (idpromo, idticket, tipocomp, numcomp, ptovta, codigo_prod, cantidad,
                              descuento_pct, descuento_total, cliente_id, vendedor_id, fecha, usuario)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                        )->execute([
                            $promoId,
                            $idTicket,
                            $tipo_comp,
                            $numcomp,
                            $pv_emisor,
                            $it['codigo'],
                            $it['cantidad'],
                            round((float)($it['pordesc'] ?? 0), 2),          // % aplicado
                            round($res['descU'] * $it['cantidad'], 2),        // $ total descontado
                            $input['cliente_id'],
                            $input['vendedor_id'] ?: null,
                            $input['fecha'],
                            $_SESSION['usuario_nombre']
                        ]);
                    }
                }

                // --- 7. PRORRATEO AJUSTES GLOBALES ---
                if ($descG > 0) {
                    $baseP = $acum['sub21'] + $acum['iva21'] + $acum['sub10'] + $acum['iva10'] + $acum['sub0'];
                    if ($baseP > 0) {
                        $descs = [['ali' => 21, 'monto' => $descG * (($acum['sub21'] + $acum['iva21']) / $baseP)], ['ali' => 10.5, 'monto' => $descG * (($acum['sub10'] + $acum['iva10']) / $baseP)], ['ali' => 0, 'monto' => $descG * ($acum['sub0'] / $baseP)]];
                        foreach ($descs as $d) {
                            if ($d['monto'] <= 0)
                                continue;
                            $netoD = round($d['monto'] / (1 + $d['ali'] / 100), 2);
                            $ivaD = round($d['monto'] - $netoD, 2);
                            $pdo->prepare("INSERT INTO Lineadeventa (lista, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant, siniva, iva, total, rubro, alicuota, usuario) VALUES (1, ?, ?, ?, ?, ?, 'DESC', 'Descuento', 1, ?, ?, ?, 'Descuentos', ?, ?)")
                                ->execute([$pv_emisor, $tipo_comp, $numcomp, $input['cliente_id'], $input['fecha'], $netoD * -1, $ivaD * -1, $d['monto'] * -1, $d['ali'], $_SESSION['usuario_nombre']]);
                        }
                    }
                }

                $stmtRange = $pdo->prepare("SELECT MIN(id) as desde, MAX(id) as hasta FROM Lineadeventa WHERE pdv = ? AND Tipocomp = ? AND numcomp = ?");
                $stmtRange->execute([$pv_emisor, $tipo_comp, $numcomp]);
                $lineRange = $stmtRange->fetch(PDO::FETCH_ASSOC);
                $lvdesde = $lineRange['desde'] ?: 0;
                $lvhasta = $lineRange['hasta'] ?: 0;

                // --- 8. TICKET CABECERA ---
                $mapLetras = [
                    1 => 'FCA',
                    2 => 'NDA',
                    3 => 'NCA',
                    6 => 'FCB',
                    7 => 'NDB',
                    8 => 'NCB',
                    11 => 'FCC',
                    12 => 'NDC',
                    13 => 'NCC',
                    100 => 'TKT',
                    101 => 'TDC'
                ];
                $letracomp = $mapLetras[$tipo_comp] ?? 'OTRO';

                // CORRECCIÓN DB: Se respeta PtoVta, NumdocCli y fecha original.
                $sqlTkt = "INSERT INTO tickets (mpago1, mpago1imp, mpago2, mpago2imp, tipocomp, PtoVta, numcomp, cliente, NumdocCli, lvdesde, lvhasta, subtotal, subtotal10, subtotal0, ii, iva, 
                iva10, total, cae, fechacae, fecha, hora, usuario, recargo, descuento, pagada, vencimiento, remitidototal, afectastock, vendedor, letracomp, fecharemito, fecharemitostr) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '', '', ?, DATE_FORMAT(NOW(), '%H:%i'), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtTkt = $pdo->prepare($sqlTkt);
                $stmtTkt->execute([
                    $mp1['nombre'],
                    $input['imp1'],
                    $mp2['nombre'],
                    $input['imp2'],
                    $tipo_comp,
                    $pv_emisor,
                    $numcomp,
                    $input['cliente_id'],
                    $cli['numdoc'],
                    $lvdesde,
                    $lvhasta,
                    $acum['sub21'],
                    $acum['sub10'],
                    $acum['sub0'],
                    $acum['ii'],
                    $acum['iva21'],
                    $acum['iva10'],
                    $total_final_server,
                    $input['fecha'],
                    $_SESSION['usuario_nombre'],
                    $recaG,
                    $descG,
                    (($mp1['tipo'] == 'Cuenta Corriente' || $mp2['tipo'] == 'Cuenta Corriente') ? 0 : 1),
                    $input['fecha'],
                    ($esAdmin ? 0 : 1),
                    ($esAdmin ? 0 : 1),
                    $input['vendedor_id'],
                    $letracomp,
                    $input['fecha'],
                    $input['fecha']
                ]);
                $idTicket = $pdo->lastInsertId();

                // --- 9. CUENTA CORRIENTE ---
                if ($mp1['tipo'] === 'Cuenta Corriente' || $mp2['tipo'] === 'Cuenta Corriente') {
                    $impCC = ($mp1['tipo'] === 'Cuenta Corriente' ? (float) $input['imp1'] : 0) + ($mp2['tipo'] === 'Cuenta Corriente' ? (float) $input['imp2'] : 0);
                    $stmtS = $pdo->prepare("SELECT saldo FROM ccclientes WHERE cliente = ? ORDER BY id DESC LIMIT 1");
                    $stmtS->execute([$input['cliente_id']]);
                    $saldoP = $stmtS->fetchColumn() ?: 0;
                    $debe = 0;
                    $haber = 0;
                    if (!$esNota) {
                        $debe = $impCC;
                        $nuevoS = $saldoP + $impCC;
                    } else {
                        $haber = $impCC;
                        $nuevoS = $saldoP - $impCC;
                    }
                    $pdo->prepare("INSERT INTO ccclientes (cliente, debe, haber, saldo, compasoc, cliorigen, tabla, fecha, fiscal) VALUES (?, ?, ?, ?, ?, ?, 'tickets', ?, ?)")
                        ->execute([$input['cliente_id'], $debe, $haber, $nuevoS, $idTicket, $input['cliente_id'], $input['fecha'], (in_array($tipo_comp, [100, 101]) ? 0 : 1)]);
                }

                // --- 10. MOVIMIENTOS DE FONDOS (CAJA / BANCOS / TESORERÍA) - NUEVA SECCIÓN BLINDADA ---
                // Recuperar numerador para tesorería
                $stmtTesoNum = $pdo->query("SELECT MAX(numerodeasiento) FROM cccuentastesoreria");
                $num_asiento_teso = ((int) $stmtTesoNum->fetchColumn()) + 1;
                $detalleTeso = "Venta $letracomp " . $tipo_comp . " " . $pv_emisor . "-" . $numcomp . " (" . $cli['razon'] . ")";
                $fiscal = (in_array($tipo_comp, [100, 101])) ? 0 : 1;

                $pagos_procesar = [
                    ['id' => $input['mp1'], 'imp' => (float) $input['imp1']],
                    ['id' => $input['mp2'], 'imp' => (float) $input['imp2']]
                ];

                foreach ($pagos_procesar as $pg) {
                    if ($pg['id'] && $pg['imp'] > 0) {
                        // LEER SIEMPRE DE TABLA
                        $stmtMP = $pdo->prepare("SELECT * FROM mediosdepago WHERE id = ?");
                        $stmtMP->execute([$pg['id']]);
                        $mpData = $stmtMP->fetch(PDO::FETCH_ASSOC);

                        if ($mpData) {
                            // 1. REGISTRO EN TESORERÍA (cccuentastesoreria) - ENTRADA DE DINERO (DEBE)
                            $montoTeso = $pg['imp'];

                            // Si es nota de crédito, el movimiento es inverso (HABER)
                            // CORRECCIÓN DB: Se conservaron EXACTAMENTE las columnas que proporcionaste en tu código
                            if ($esNota) {
                                $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, 0, ?, 'tickets', ?, ?, ?, ?, ?, ?)")
                                    ->execute([$pg['id'], $montoTeso, $_SESSION['usuario_nombre'], $input['fecha'], $detalleTeso, $fiscal, $idTicket, $num_asiento_teso]);
                            } else {
                                $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta, debe, haber, tabla, usuario, fecha, descripcion, fiscal, compasoc, numerodeasiento) VALUES (?, ?, 0, 'tickets', ?, ?, ?, ?, ?, ?)")
                                    ->execute([$pg['id'], $montoTeso, $_SESSION['usuario_nombre'], $input['fecha'], $detalleTeso, $fiscal, $idTicket, $num_asiento_teso]);
                            }

                            // 2. LÓGICA CAJA DE USUARIOS
                            if ($mpData['tipodecuenta'] === 'Caja de Usuarios') {
                                // Validar vinculación con caja abierta actual
                                // Se permite enlace por 'numcaja' O por 'id' directo (Compatibilidad Legacy/New)
                                $stmtValidBox = $pdo->prepare("SELECT COUNT(*) FROM cajas WHERE id = ? AND (numcaja = ? OR id = ?)");
                                $stmtValidBox->execute([$idcajaabierta, $mpData['puestoasociado'], $mpData['puestoasociado']]);
                                if ($stmtValidBox->fetchColumn() == 0) {
                                    throw new Exception("El medio de pago [{$mpData['medio']}] no pertenece a su caja actual.");
                                }

                                // Validar que siga abierta (Regla User)
                                $stmtOpen = $pdo->prepare("SELECT COUNT(*) FROM controlcajas WHERE id = ? AND cerradael IS NULL");
                                $stmtOpen->execute([$idcontrolcaja]);
                                if ($stmtOpen->fetchColumn() == 0) {
                                    throw new Exception("Su caja se ha cerrado durante la operación. No puede realizar movimientos.");
                                }

                                // Calcular importe real (Neto de vuelto)
                                $impReal = $pg['imp'];
                                if (isset($input['vuelto']) && (float) $input['vuelto'] > 0) {
                                    $impReal -= (float) $input['vuelto'];
                                }

                                // Movimiento Físico (Auditoría)
                                $debeCaja = $esNota ? 0 : $impReal;
                                $haberCaja = $esNota ? $impReal : 0;

                                // CORRECCIÓN DB: Respetar CamelCase de campos en tabla cajaxusuario (FechaMov, HoraMov, TipoMov, Debe, Haber)
                                $pdo->prepare("INSERT INTO cajaxusuario (FechaMov, HoraMov, TipoMov, Debe, Haber, usuario, idcaja, idcontrolcaja) VALUES (?, DATE_FORMAT(NOW(), '%H:%i'), ?, ?, ?, ?, ?, ?)")
                                    ->execute([$input['fecha'], $detalleTeso, $debeCaja, $haberCaja, $_SESSION['usuario_nombre'], $idcajaabierta, $idcontrolcaja]);

                                // Actualizar Saldos
                                if ($esNota) {
                                    $pdo->prepare("UPDATE cajas SET saldo = saldo - ? WHERE id = ?")->execute([$impReal, $idcajaabierta]);
                                    $pdo->prepare("UPDATE controlcajas SET saldofinal = saldofinal - ? WHERE id = ?")->execute([$impReal, $idcontrolcaja]);
                                } else {
                                    $pdo->prepare("UPDATE cajas SET saldo = saldo + ? WHERE id = ?")->execute([$impReal, $idcajaabierta]);
                                    $pdo->prepare("UPDATE controlcajas SET cantidadmovimientos = cantidadmovimientos + 1, saldofinal = saldofinal + ? WHERE id = ?")->execute([$impReal, $idcontrolcaja]);
                                }
                            }

                            // 3. LÓGICA BANCOS
                            if ($mpData['tipodecuenta'] === 'Bancos' && $mpData['cuentabancaria']) {
                                $stSaldo = $pdo->prepare("SELECT saldo FROM ccbancos WHERE cuenta = ? ORDER BY id DESC LIMIT 1");
                                $stSaldo->execute([$mpData['cuentabancaria']]);
                                $saldoB = $stSaldo->fetchColumn() ?: 0;

                                if ($esNota) {
                                    $nuevoS = $saldoB - $pg['imp'];
                                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, 0, ?, ?, ?, ?, 'tickets', ?)")
                                        ->execute([$mpData['cuentabancaria'], $pg['imp'], $nuevoS, $input['fecha'], $idTicket, $detalleTeso]);
                                } else {
                                    $nuevoS = $saldoB + $pg['imp'];
                                    $pdo->prepare("INSERT INTO ccbancos (cuenta, debe, haber, saldo, fecha, compasoc, tabla, detalle) VALUES (?, ?, 0, ?, ?, ?, 'tickets', ?)")
                                        ->execute([$mpData['cuentabancaria'], $pg['imp'], $nuevoS, $input['fecha'], $idTicket, $detalleTeso]);
                                }
                            }
                        }
                    }
                }

                // --- 11. CONTABILIDAD (Fidelidad V60) ---
                $stMaxHoy = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? AND fecha = ?");
                $stMaxHoy->execute([$idEjercicio, $input['fecha']]);
                $numAsiento = $stMaxHoy->fetchColumn();
                if (!$numAsiento) {
                    $stMaxPrev = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? AND fecha < ?");
                    $stMaxPrev->execute([$idEjercicio, $input['fecha']]);
                    $numAsiento = ($stMaxPrev->fetchColumn() ?: 0) + 1;
                } else {
                    $numAsiento++;
                }

                $fiscal = (in_array($tipo_comp, [100, 101])) ? 0 : 1;
                $detalleAsiento = str_pad($pv_emisor, 4, '0', STR_PAD_LEFT) . "-" . str_pad($numcomp, 8, '0', STR_PAD_LEFT) . " - " . $cli['razon'];

                $imp1_c = (float) $input['imp1'];
                $imp2_c = (float) $input['imp2'];
                if (isset($input['vuelto']) && (float) $input['vuelto'] > 0) {
                    if ($mp1['tipo'] == 'Caja de Usuarios')
                        $imp1_c -= (float) $input['vuelto'];
                    if ($mp2['tipo'] == 'Caja de Usuarios')
                        $imp2_c -= (float) $input['vuelto'];
                }

                $debe1 = 0;
                $haber1 = 0;
                $debe2 = 0;
                $haber2 = 0;
                $debeIva = 0;
                $haberIva = 0;
                $debeImp = 0;
                $haberImp = 0;
                $debeDesc = 0;
                $haberDesc = 0;
                $debeReca = 0;
                $haberReca = 0;

                // El descuento/recargo global se postea por su importe COMPLETO (incluye IVA) contra
                // descuentos/recargos, no dividido por 1.21 — dividirlo dejaba la porción de IVA del
                // ajuste global sin ninguna cuenta contrapartida y desbalanceaba el asiento. Así, la
                // cuenta de IVA y la de ventas quedan intactas (suma pura de líneas de artículo).
                if ($esNota) {
                    $haber1 = $imp1_c;
                    $haber2 = $imp2_c;
                    $haberDesc = round($descG, 2);
                    $debeReca = round($recaG, 2);
                    $debeIva = round($acum['iva21'] + $acum['iva10'], 2);
                    $debeImp = round($acum['ii'], 2);
                } else {
                    $debe1 = $imp1_c;
                    $debe2 = $imp2_c;
                    $debeDesc = round($descG, 2);
                    $haberReca = round($recaG, 2);
                    $haberIva = round($acum['iva21'] + $acum['iva10'], 2);
                    $haberImp = round($acum['ii'], 2);
                }

                GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $mp1['cuenta'], $debe1, $haber1, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                if (!empty($input['mp2']))
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $mp2['cuenta'], $debe2, $haber2, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                if ($descG > 0)
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['descuentos'], $debeDesc, $haberDesc, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                if ($recaG > 0)
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['recargos'], $debeReca, $haberReca, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                if (($acum['iva21'] + $acum['iva10']) > 0)
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['ivaventas'], $debeIva, $haberIva, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                if ($acum['ii'] > 0)
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['impintventas'], $debeImp, $haberImp, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);

                $totalesVenta = [];
                $sumaimpventa = 0;
                foreach ($processedItems as $p) {
                    $sumaimpventa += $p['calc']['ii'];
                    // calc['neto'] ya viene neto del descuento de línea (recalcularLineaServidor lo aplica
                    // sobre netoEfU) — restar descU acá de nuevo duplicaba el descuento y desbalanceaba el asiento.
                    $asumar = round($p['calc']['neto'], 2);
                    $cta_v = $p['art']['cuentaventa'];
                    $totalesVenta[$cta_v] = ($totalesVenta[$cta_v] ?? 0) + $asumar;
                }
                foreach ($totalesVenta as $cta => $monto) {
                    if ($esNota)
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, round($monto, 2), 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                    else
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, 0, round($monto, 2), $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                }

                $numAsiento++;
                $totMerca = [];
                $totCosto = [];
                $sumaimpcompra = 0;
                foreach ($processedItems as $p) {
                    if ($p['art']['codigo'] === 'LM')
                        continue;
                    $sumaimpcompra += ((float) ($p['art']['ImpInt'] ?? 0) * abs((float) $p['input']['cantidad']));
                    $costoB = $p['art']['preciocosto'];
                    if ((float) $p['art']['ImpInt'] > 0 && $costoB > (float) $p['art']['ImpInt'])
                        $costoB -= (float) $p['art']['ImpInt'];
                    $costoL = round(($costoB / (1 + ($p['art']['poriva'] / 100))) * (float) $p['input']['cantidad'], 2);
                    $totMerca[$p['art']['cuentamerca']] = ($totMerca[$p['art']['cuentamerca']] ?? 0) + $costoL;
                    $totCosto[$p['art']['cuentacostomerca']] = ($totCosto[$p['art']['cuentacostomerca']] ?? 0) + $costoL;
                }
                foreach ($totCosto as $cta => $monto) {
                    if ($esNota)
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, 0, round($monto, 2), $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                    else
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, round($monto, 2), 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                }
                foreach ($totMerca as $cta => $monto) {
                    if ($esNota)
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, round($monto, 2), 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                    else
                        GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cta, 0, round($monto, 2), $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                }

                $diffII = round($sumaimpventa - $sumaimpcompra, 2);
                if (abs($diffII) > 0.1) {
                    $numAsiento++;
                    if ($diffII > 0) {
                        if (!$esNota) {
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['impintventas'], $diffII, 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['ajusteimp'], 0, $diffII, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                        } else {
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['ajusteimp'], $diffII, 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['impintventas'], 0, $diffII, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                        }
                    } else {
                        $absD = abs($diffII);
                        if (!$esNota) {
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['ajusteimp'], $absD, 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['impintventas'], 0, $absD, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                        } else {
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['impintventas'], $absD, 0, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                            GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], $cp['ajusteimp'], 0, $absD, $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                        }
                    }
                }

                // --- 12. ESTADOS ORIGEN ---
                $pedido_id = isset($input['pedido_id']) ? intval($input['pedido_id']) : 0;
                if ($pedido_id > 0)
                    $pdo->prepare("UPDATE pedidosclientes SET estadofac = 1 WHERE numpedido = ?")->execute([$pedido_id]);
                if (!empty($input['presupuesto_id']))
                    $pdo->prepare("UPDATE presupuestosclientes SET estadofac = 1 WHERE numpedido = ?")->execute([$input['presupuesto_id']]);

                $pdo->commit();
                $successGrabado = true;
                break; // Grabación exitosa, salimos del bucle de reintentos

            } catch (PDOException $e) {
                if ($pdo->inTransaction())
                    $pdo->rollBack();
                if ($e->getCode() == '23000') { // Duplicate Entry
                    $numcomp++;
                    continue; // Reintentamos con el siguiente número
                }
                throw $e;
            }
        }

        if (!$successGrabado)
            throw new Exception("No se pudo asignar numeración tras {$maxIntentos} intentos.");

        // --- 13. AFIP ---
        $cae = '';
        $vtocae = '';
        $caeError = null;
        if (!in_array($tipo_comp, [100, 101])) {
            try {
                function mapTipo($t)
                {
                    if (in_array($t, [1, 6, 11]))
                        return "F";
                    if (in_array($t, [2, 7, 12]))
                        return "D";
                    if (in_array($t, [3, 8, 13]))
                        return "C";
                    throw new Exception("Tipo inválido");
                }
                function mapCond($c)
                {
                    return match ($c) { "Responsable Inscripto" => 1, "Consumidor Final" => 5, "Exento" => 4, "Monotributo" => 6, default => 5};
                }

                $tipo = mapTipo($tipo_comp);
                $cond = mapCond($cli['condicionfiscal']);
                $docTipo = ($cond == 5) ? 99 : 80;
                $docNro = ($cond == 5) ? 0 : (int) $cli['numdoc'];
                $ivaArray = [];
                $condiemisor = mapCond($_SESSION['condicion_iva_empresa']);

                if ($acum['iva21'] > 0)
                    $ivaArray[] = ["id" => 5, "baseImp" => round($acum['sub21'], 2), "importe" => round($acum['iva21'], 2)];
                if ($acum['iva10'] > 0)
                    $ivaArray[] = ["id" => 4, "baseImp" => round($acum['sub10'], 2), "importe" => round($acum['iva10'], 2)];

                $payload = [
                    "tipo" => $tipo,
                    "emisorCuit" => $_SESSION['cliente_cuit'],
                    "emisorCondicionIva" => $condiemisor,
                    "docTipo" => $docTipo,
                    "docNro" => $docNro,
                    "puntoVenta" => $pv_emisor,
                    "numeroComp" => $numcomp,
                    "condicionIVA" => $cond,
                    "importeNeto" => round($acum['sub21'] + $acum['sub10'] + $acum['sub0'], 2),
                    "importeIVA" => round($acum['iva21'] + $acum['iva10'], 2),
                    "importeTributo" => round($acum['ii'], 2),
                    "iva" => $ivaArray,
                    "produccion" => $_SESSION['modo']
                ];

                if (in_array($tipo_comp, [2, 3, 7, 8, 12, 13], true)) {
                    $payload['comprobanteAsociado'] = [
                        'tipo' => (int) $associated['tipo'],
                        'puntoVenta' => (int) $associated['pv'],
                        'numero' => (int) $associated['num'],
                        'cuit' => (string) $_SESSION['cliente_cuit'],
                        'fecha' => str_replace('-', '', (string) $associated['fecha'])
                    ];
                }

                // --- AUDITORÍA DE LOG ---
                $logFile = 'log_arca_audit.txt';
                $logData = "====================================================\nFECHA: " . date('Y-m-d H:i:s') . "\nTICKET ID: " . $idTicket . " | COMP: " . $numcomp . "\n";
                $logData .= "PAYLOAD ENVIADO:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";

                $ch = curl_init("https://erp.westit.com.ar/ARCA/new_fe_cae_api.php");
                curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'SistemaERP/CAE']);

                $resp = curl_exec($ch);
                $curlError = curl_error($ch);
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                $logData .= "RESPUESTA RAW API:\n" . ($resp ?: "SIN RESPUESTA") . "\n";
                if ($resp === false) {
                    throw new Exception('No se pudo comunicar con ARCA: ' . $curlError);
                }
                $cleanResp = preg_replace('/^\xEF\xBB\xBF/', '', trim($resp));
                $afip = json_decode($cleanResp, true);
                if (!is_array($afip)) {
                    $firstJson = strpos($cleanResp, '{');
                    $lastJson = strrpos($cleanResp, '}');
                    if ($firstJson !== false && $lastJson !== false && $lastJson >= $firstJson) {
                        $afip = json_decode(substr($cleanResp, $firstJson, $lastJson - $firstJson + 1), true);
                    }
                }
                if (!is_array($afip)) {
                    $preview = preg_replace('/\s+/', ' ', trim(strip_tags($cleanResp)));
                    if (strlen($preview) > 180) $preview = substr($preview, 0, 180) . '...';
                    throw new Exception("Respuesta inválida del servicio ARCA (HTTP {$httpCode}, {$contentType})" . ($preview !== '' ? ": {$preview}" : ': respuesta vacía.'));
                }

                // --- RECUPERACIÓN CONCURRENTE SI EL NÚMERO YA FUE EMITIDO ---
                if ($afip && isset($afip['error']) && (strpos($afip['error'], 'registrado') !== false || strpos($afip['error'], 'proximo') !== false)) {
                    $ch_get = curl_init("https://erp.westit.com.ar/ARCA/fe_get_cae.php?cuit={$_SESSION['cliente_cuit']}&pv={$pv_emisor}&tipo={$tipo_comp}&num={$numcomp}&modo={$_SESSION['modo']}");
                    curl_setopt($ch_get, CURLOPT_RETURNTRANSFER, true);
                    $resp_get = curl_exec($ch_get);
                    $afip_get = json_decode($resp_get, true);
                    if ($afip_get && isset($afip_get['resultado']) && $afip_get['resultado'] === 'A') {
                        $afip = $afip_get;
                    }
                }

                if ($afip && isset($afip['resultado']) && $afip['resultado'] === 'A') {
                    $cae = (string) $afip['cae'];
                    $vtocaeRaw = (string) ($afip['vencimiento'] ?? '');
                    $vtocae = '';
                    if (!empty($vtocaeRaw)) {
                        $cleanVto = preg_replace('/[^0-9]/', '', $vtocaeRaw);
                        if (strlen($cleanVto) === 8) {
                            $vtocae = $cleanVto;
                        } else {
                            $dt = DateTime::createFromFormat('d-m-Y', $vtocaeRaw);
                            if ($dt instanceof DateTime)
                                $vtocae = $dt->format('Ymd');
                        }
                    }
                    $pdo->prepare("UPDATE tickets SET cae=?, fechacae=? WHERE id=?")->execute([$cae, $vtocae, $idTicket]);
                    $logData .= "CAE ASIGNADO EXITOSAMENTE: " . $cae . "\n";
                } else {
                    $caeError = $afip['error'] ?? 'AFIP no autorizó el comprobante.';
                    $logData .= "ESTADO: AFIP NO AUTORIZÓ O ERROR EN API\n";
                }

                $logData .= "====================================================\n\n";
                file_put_contents($logFile, $logData, FILE_APPEND);

            } catch (Exception $e) {
                $caeError = $e->getMessage();
                file_put_contents('log_arca_audit.txt', "ERROR EXCEPCIÓN AFIP: " . $e->getMessage() . "\n", FILE_APPEND);
            }
        }

        // El comprobante y sus movimientos ya están grabados (transacción confirmada más arriba).
        // Si no se obtuvo CAE, se informa igual como éxito de grabación pero marcando
        // explícitamente que la autorización fiscal quedó pendiente, para que la UI avise
        // al usuario en vez de mostrar "emitido correctamente" sin CAE real.
        echo json_encode([
            'success' => true,
            'numcomp' => $numcomp,
            'cae' => $cae,
            'cae_pendiente' => empty($cae) && !in_array($tipo_comp, [100, 101]),
            'cae_error' => $caeError,
            'id_ticket' => $idTicket
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    ?>
