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
require_once 'tarjetas_helpers.php';
require_once 'retper_helpers.php';
require_once 'libro_iva_helpers.php';
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
            $stmtCtrl = $pdo->prepare("SELECT id FROM controlcajas WHERE idcaja = ? AND usuario = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00') ORDER BY id DESC LIMIT 1");
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
        // Neto unitario de lista con 3 decimales (igual que newfactura.js / facturar.js, r3): redondearlo a 2
        // antes de sumar el IVA cobraba $4.000,01 un precio de lista de $4.000.
        $netoU = round((float) $it['precio_base'], 3);
        $iiU = round((float) ($art['ImpInt'] ?? $art['impint'] ?? 0), 2);
        $finalOriginalU = round($netoU * (1 + $poriva / 100) + $iiU, 2);
        $descU = round(((float) $it['pordesc'] * $finalOriginalU) / 100, 2);
        $baseNetoIvaU = round($netoU * (1 + $poriva / 100), 2);

        if ($descU <= $baseNetoIvaU) {
            $brutoU = round($baseNetoIvaU - $descU, 2);
            $netoEfU = round($brutoU / (1 + $poriva / 100), 2);
            // El IVA es la diferencia: neto + IVA = precio final exacto (con IVA 0 da 0).
            $ivaEfU = round($brutoU - $netoEfU, 2);
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

    function mapTipoComprobanteArca($t)
    {
        if (in_array($t, [1, 6, 11]))
            return "F";
        if (in_array($t, [2, 7, 12]))
            return "D";
        if (in_array($t, [3, 8, 13]))
            return "C";
        throw new Exception("Tipo inválido");
    }
    function mapCondicionArca($c)
    {
        return match ($c) { "Responsable Inscripto" => 1, "Consumidor Final" => 5, "Exento" => 4, "Monotributo" => 6, default => 5};
    }

    // Carpeta de auditoría ARCA de la empresa emisora actual (ARCA/<cuit>/), separada por
    // cliente para no mezclar el rastro de auditoría de una empresa con el de otra.
    function carpetaArcaCliente()
    {
        $cuit = preg_replace('/[^0-9]/', '', (string) ($_SESSION['cliente_cuit'] ?? ''));
        if ($cuit === '')
            $cuit = 'sin_cuit';
        $dir = __DIR__ . "/ARCA/{$cuit}";
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }

    // Log de texto de auditoría ARCA, uno por cliente (antes era un único log_arca_audit.txt
    // compartido por todas las empresas del sistema, lo que mezclaba el rastro de todas).
    function logArcaCliente($linea)
    {
        @file_put_contents(carpetaArcaCliente() . '/log_arca_audit.txt', date('Y-m-d H:i:s') . ' ' . $linea . "\n", FILE_APPEND);
    }

    // El QR que devuelve el motor ARCA (campos qr/qr_base64/qrData) es una imagen SVG en
    // base64 embebida en la respuesta — pesa mucho más que el resto del JSON y no se usa
    // para nada acá (imprimir_ticket.php genera su propio QR al momento de imprimir, a
    // partir del CAE ya grabado). Se descarta antes de guardar el archivo de auditoría.
    function limpiarQrParaAuditoria($jsonStr)
    {
        $data = json_decode($jsonStr, true);
        if (is_array($data)) {
            unset($data['qr'], $data['qr_base64'], $data['qr_mime'], $data['qrData']);
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
        return $jsonStr;
    }

    // Guarda lo enviado/recibido con el intermediario ARCA, un archivo por comprobante
    // y por empresa emisora (ARCA/<cuit del emisor>/).
    function guardarAuditoriaArca($sufijo, $contenido, $tipo_comp, $pv_emisor, $identificador)
    {
        $nombre = "{$tipo_comp}-{$pv_emisor}-{$identificador}_{$sufijo}.json";
        @file_put_contents(carpetaArcaCliente() . "/{$nombre}", $contenido);
    }

    // Cola de emergencia: cualquier comprobante fiscal que no llegue a grabarse en
    // tickets/Lineadeventa queda registrado acá (con CAE o sin él) para no perder
    // nunca el rastro de qué se intentó vender y por qué falló. Abre su propia
    // transacción, independiente de la que ya se haya hecho rollback en el llamador.
    // Devuelve el id de auxticket, o null si ni siquiera esto se pudo grabar (en ese
    // caso el log de texto de logArcaCliente queda como último recurso).
    function guardarEnAuxiliares($pdo, $estado, $tipo_comp, $pv_emisor, $numcomp, $cae, $vtocae,
                                  $input, $processedItems, $acum, $total, $usuario_nombre, $motivo)
    {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO auxticket
                (estado, tipocomp, PtoVta, numcomp, cae, fechacae, cliente, subtotal, subtotal10,
                 subtotal0, ii, iva, iva10, total, fecha, usuario, vendedor, mpago1, mpago1imp,
                 mpago2, mpago2imp, motivo_falla, payload_json)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $estado,
                $tipo_comp,
                $pv_emisor,
                $numcomp ?: null,
                $cae !== '' ? $cae : null,
                $vtocae !== '' ? $vtocae : null,
                $input['cliente_id'] ?? null,
                $acum['sub21'] ?? 0,
                $acum['sub10'] ?? 0,
                $acum['sub0'] ?? 0,
                $acum['ii'] ?? 0,
                $acum['iva21'] ?? 0,
                $acum['iva10'] ?? 0,
                $total,
                $input['fecha'] ?? date('Y-m-d'),
                $usuario_nombre,
                $input['vendedor_id'] ?? null,
                $input['mp1'] ?? '',
                $input['imp1'] ?? 0,
                $input['mp2'] ?? '',
                $input['imp2'] ?? 0,
                $motivo,
                json_encode($input, JSON_UNESCAPED_UNICODE)
            ]);
            $idAux = (int) $pdo->lastInsertId();

            $stmtL = $pdo->prepare("INSERT INTO auxlineadeventa
                (id_auxticket, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant,
                 siniva, iva, ii, total, rubro, alicuota, costo, usuario, vendedor)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            foreach ($processedItems as $p) {
                $it = $p['input'];
                $art = $p['art'];
                $res = $p['calc'];
                $stmtL->execute([
                    $idAux,
                    $pv_emisor,
                    $tipo_comp,
                    $numcomp ?: null,
                    $input['cliente_id'] ?? null,
                    $input['fecha'] ?? date('Y-m-d'),
                    $it['codigo'],
                    $it['descripcion'],
                    $it['cantidad'],
                    $res['neto'],
                    $res['iva'],
                    $res['ii'],
                    $res['total'],
                    $art['rubro'] ?? '',
                    $res['alicuota'],
                    round(($art['preciocosto'] ?? 0) * $it['cantidad'], 2),
                    $usuario_nombre,
                    $input['vendedor_id'] ?? null
                ]);
            }
            $pdo->commit();
            logArcaCliente("GUARDADO EN COLA AUXILIAR auxticket #{$idAux} (estado={$estado}) | {$pv_emisor}-" . ($numcomp ?: '?') . " (tipo {$tipo_comp}) | CAE " . ($cae ?: '(sin CAE)') . " | motivo: {$motivo}");
            return $idAux;
        } catch (Exception $ex) {
            if ($pdo->inTransaction())
                $pdo->rollBack();
            logArcaCliente("ERROR CRÍTICO: no se pudo grabar ni en tickets ni en la cola auxiliar | {$pv_emisor}-" . ($numcomp ?: '?') . " (tipo {$tipo_comp}) | CAE " . ($cae ?: '(sin CAE)') . " | vto {$vtocae} | motivo original: {$motivo} | error auxiliar: " . $ex->getMessage());
            return null;
        }
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

        // Sin multidepósito se conserva el comportamiento histórico. Con la opción
        // activa, el depósito de trabajo define el punto de venta emisor.
        $idDepositoVenta = null;
        $pv_emisor = (int) $_SESSION['pdv'];
        if (multidepositoHabilitado($pdo)) {
            $emisor = datosEmisorFacturacion($pdo, $usuario_nombre);
            if (!$emisor['completo']) {
                throw new Exception('Complete los datos de la empresa o del depósito antes de facturar: ' . implode(', ', $emisor['faltantes']) . '.');
            }
            $idDepositoVenta = (int) $emisor['id_deposito'];
            $pv_emisor = (int) $emisor['pdv'];
        }

        // El número de comprobante YA NO se reserva/adivina acá. Para tipos fiscales se pide
        // a ARCA antes de grabar nada (ver más abajo, justo antes del bucle de grabado): el
        // numcomp que se usa para Lineadeventa/tickets/etc. es directamente el que ARCA
        // autorizó, así no hace falta reconciliar/renumerar nada después del INSERT. Para
        // Ticket/Ticket de Crédito (100/101, no fiscales) sigue siendo una reserva local simple.
        $numcomp = 0;
        $cae = '';
        $vtocae = '';
        $caeError = null;

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
        // Sin este chequeo, un $input['items'] vacío (o ausente) no rompe nada: el foreach de
        // abajo simplemente no itera, y el resto del flujo sigue de largo y graba un ticket
        // cabecera sin una sola fila en Lineadeventa (el bug que motivó este chequeo). La
        // validación del navegador (cart.length === 0) no protege este endpoint si se lo
        // invoca directo.
        if (empty($input['items']) || !is_array($input['items'])) {
            throw new Exception("No se puede facturar un comprobante sin productos.");
        }

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
                    'ivacosto' => 0,
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
                // IVA del costo unitario. En alb_productos vale
                // preciocosto = sinivacosto + ivacosto + impint, así que es el
                // crédito fiscal de la compra y no puede quedar en cero: el
                // reporte de ventas lo resta del costo para calcular la utilidad.
                $art['ivacosto'] = (float) ($art['ivacosto'] ?? $art['Ivacosto'] ?? 0);
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

        // --- PERCEPCIONES (agente de percepción, retper_helpers.php) ---
        // Solo comprobantes fiscales y solo si hay regímenes de percepción habilitados. La base
        // es el neto/IVA que se declara a ARCA (mismo prorrateo del ajuste global). Se suman al
        // total y viajan a ARCA como Tributos en el mismo pedido de CAE.
        $percepciones = [];
        if (!in_array($tipo_comp, [100, 101], true)) {
            $declaradoPerc = ivaVentaComoArca(['subtotal' => $acum['sub21'], 'iva' => $acum['iva21'], 'subtotal10' => $acum['sub10'],
                                               'iva10' => $acum['iva10'], 'subtotal0' => $acum['sub0'], 'recargo' => $recaG, 'descuento' => $descG]);
            [$netoPerc, $ivaPerc] = percepcionBaseVenta($declaradoPerc, $tipo_comp);
            $percepciones = calcularPercepcionesVenta($pdo, (int) $input['cliente_id'], $tipo_comp, $netoPerc, $ivaPerc, $total_final_server, $input['fecha']);
            foreach ($percepciones as $pTmp) retperCuenta($pdo, $pTmp); // cuenta contable configurada, antes de pedir CAE
        }
        $totalPercepciones = round(array_sum(array_column($percepciones, 'importe')), 2);
        $total_final_server = round($total_final_server + $totalPercepciones, 2);

        if (abs($total_final_server - (float) $input['total_comprobante']) > 0.01) {
            throw new Exception($totalPercepciones > 0
                ? "Las percepciones del comprobante cambiaron (\$" . number_format($totalPercepciones, 2, ',', '.') . "). Cierre el cobro y vuelva a abrirlo para recalcular el total."
                : "El total de la pantalla no coincide con el del sistema. Recargue la página (F5) y vuelva a cargar la venta: no se grabó nada.");
        }

        // Esta validación debe vivir también acá (no solo en el front y en
        // api_facturacion_validaciones_previas.php): este endpoint se puede invocar
        // directo. Un imp2 negativo compensado con un imp1 inflado pasaría la
        // igualdad de suma=total de más abajo, pero en la sección de movimientos de
        // fondos el importe negativo se salteaba el movimiento de caja/banco
        // (if ($pg['imp'] > 0)), dejando el ticket "pagado" sin que el ingreso real
        // quede reflejado en ese medio.
        if ((float) $input['imp1'] < 0 || (float) ($input['imp2'] ?? 0) < 0) {
            throw new Exception("Los importes de los medios de pago no pueden ser negativos.");
        }

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
        // --- 5.5 CAE (ANTES DE GRABAR NADA) ---
        // Comprobantes fiscales SOLO se graban si ARCA autoriza. Se pide el CAE ACÁ, antes de
        // insertar una sola fila, y el numcomp que se usa para Lineadeventa/controlstock/tickets
        // es directamente el que ARCA aprobó (numeroComprobante) — no una reserva local que haya
        // que corregir después. Si ARCA no autoriza (rechazo, error de comunicación, timeout),
        // se corta acá con una excepción: no queda ningún ticket "pendiente de CAE", no se mueve
        // stock, no hay asiento contable — nada, tal como se grabaría todo mal a partir de un
        // numcomp que después terminaría sin corresponderse con lo que diga AFIP.
        if (in_array($tipo_comp, [100, 101], true)) {
            // Ticket / Ticket de Crédito: no son comprobantes fiscales, no pasan por ARCA.
            $stmtNum = $pdo->prepare("SELECT COALESCE(MAX(numcomp), 0) + 1 FROM tickets WHERE PtoVta = ? AND tipocomp = ?");
            $stmtNum->execute([$pv_emisor, $tipo_comp]);
            $numcomp = (int) $stmtNum->fetchColumn();
        } else {
            $tipoArca = mapTipoComprobanteArca($tipo_comp);
            $condArca = mapCondicionArca($cli['condicionfiscal']);
            $docTipo = ($condArca == 5) ? 99 : 80;
            $docNro = ($condArca == 5) ? 0 : (int) $cli['numdoc'];
            $condiemisor = mapCondicionArca($_SESSION['condicion_iva_empresa']);
            $ivaArray = [];

            // El descuento/recargo global ($descG/$recaG) ya está incluido en $total_final_server
            // (lo que se cobra), pero $acum solo trae los importes "crudos" de los ítems. Sin este
            // prorrateo, el ImpNeto/ImpIVA pedido a ARCA no coincide con el total real del
            // comprobante apenas hay un descuento o recargo global. Mismo criterio de prorrateo
            // que el usado para las líneas de "Descuento" en Lineadeventa (paso 7 más abajo).
            $ajusteG = $recaG - $descG;
            $subFiscal21 = $acum['sub21'];
            $ivaFiscal21 = $acum['iva21'];
            $subFiscal10 = $acum['sub10'];
            $ivaFiscal10 = $acum['iva10'];
            $subFiscal0 = $acum['sub0'];
            if ($ajusteG != 0) {
                $baseF = $acum['sub21'] + $acum['iva21'] + $acum['sub10'] + $acum['iva10'] + $acum['sub0'];
                if ($baseF > 0) {
                    $montoF21 = $ajusteG * (($acum['sub21'] + $acum['iva21']) / $baseF);
                    $montoF10 = $ajusteG * (($acum['sub10'] + $acum['iva10']) / $baseF);
                    $montoF0 = $ajusteG * ($acum['sub0'] / $baseF);

                    $netoF21 = round($montoF21 / 1.21, 2);
                    $ivaFiscal21 += round($montoF21 - $netoF21, 2);
                    $subFiscal21 += $netoF21;

                    $netoF10 = round($montoF10 / 1.105, 2);
                    $ivaFiscal10 += round($montoF10 - $netoF10, 2);
                    $subFiscal10 += $netoF10;

                    $subFiscal0 += round($montoF0, 2);
                }
            }

            if ($acum['iva21'] > 0)
                $ivaArray[] = ["id" => 5, "baseImp" => round($subFiscal21, 2), "importe" => round($ivaFiscal21, 2)];
            if ($acum['iva10'] > 0)
                $ivaArray[] = ["id" => 4, "baseImp" => round($subFiscal10, 2), "importe" => round($ivaFiscal10, 2)];

            $payload = [
                "tipo" => $tipoArca,
                "emisorCuit" => $_SESSION['cliente_cuit'],
                "emisorCondicionIva" => $condiemisor,
                "docTipo" => $docTipo,
                "docNro" => $docNro,
                "puntoVenta" => $pv_emisor,
                "condicionIVA" => $condArca,
                "importeNeto" => round($subFiscal21 + $subFiscal10 + $subFiscal0, 2),
                "importeIVA" => round($ivaFiscal21 + $ivaFiscal10, 2),
                "importeTributo" => round($acum['ii'], 2),
                "percepciones" => array_map(static fn($p) => [
                    'id' => $p['codigo_tributo_arca'], 'desc' => $p['nombre'], 'base' => $p['base'],
                    'alic' => $p['alicuota'], 'importe' => $p['importe']
                ], $percepciones),
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

            // Identificador provisorio para los archivos de auditoría: todavía no existe un
            // numcomp (viene recién en la respuesta), así que no se puede nombrar el archivo con
            // el número real hasta después de la llamada.
            $idAuditoriaPrevia = 'PRE-' . str_replace('.', '', (string) microtime(true));
            $logData = "====================================================\nFECHA: " . date('Y-m-d H:i:s') . "\nSOLICITUD PREVIA A GRABAR | PV {$pv_emisor} TIPO {$tipo_comp}\n";
            $logData .= "PAYLOAD ENVIADO:\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n";
            guardarAuditoriaArca('enviado', json_encode($payload, JSON_PRETTY_PRINT), $tipo_comp, $pv_emisor, $idAuditoriaPrevia);

            if ($percepciones) {
                // El CAE lo pide el servidor de facturación electrónica con su propia copia de
                // ArcaFunctions.php. Si todavía no sabe declarar percepciones, ARCA autorizaría un
                // total sin ellas: se pregunta antes y, si no las admite, no se emite nada.
                $chCap = curl_init("https://erp.westit.com.ar/ARCA/new_fe_cae_api.php?capacidades=1");
                curl_setopt_array($chCap, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'SistemaERP/CAE']);
                $capRaw = curl_exec($chCap);
                curl_close($chCap);
                $cap = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $capRaw)), true);
                if (empty($cap['percepciones'])) {
                    throw new Exception('El servidor de facturación electrónica todavía no admite percepciones (falta actualizar ARCA/ArcaFunctions.php y ARCA/new_fe_cae_api.php). No se emitió el comprobante.');
                }
            }

            $ch = curl_init("https://erp.westit.com.ar/ARCA/new_fe_cae_api.php");
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_TIMEOUT => 45, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_ENCODING => '', CURLOPT_USERAGENT => 'SistemaERP/CAE']);

            $resp = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            // Sin QR: el campo qr/qr_base64 que devuelve el motor ARCA es una imagen SVG en
            // base64 que pesa mucho más que el resto de la respuesta y no se usa para nada acá.
            $logData .= "RESPUESTA RAW API:\n" . ($resp ? limpiarQrParaAuditoria($resp) : "SIN RESPUESTA") . "\n";
            $logData .= "====================================================\n\n";
            file_put_contents(carpetaArcaCliente() . '/log_arca_audit.txt', $logData, FILE_APPEND);

            if ($resp === false) {
                throw new Exception('No se pudo comunicar con ARCA: ' . $curlError . '. No se grabó ningún dato.');
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
                throw new Exception("Respuesta inválida del servicio ARCA (HTTP {$httpCode}, {$contentType})" . ($preview !== '' ? ": {$preview}" : ': respuesta vacía.') . ' No se grabó ningún dato.');
            }

            if (!isset($afip['resultado']) || $afip['resultado'] !== 'A' || empty($afip['cae']) || empty($afip['numeroComprobante'])) {
                $detalle = $afip['error'] ?? implode(' - ', $afip['observaciones'] ?? []) ?: 'ARCA rechazó la solicitud sin detalle.';
                throw new Exception("ARCA no autorizó el comprobante: {$detalle}. No se grabó ningún dato.");
            }

            $numcomp = (int) $afip['numeroComprobante'];
            $cae = (string) $afip['cae'];
            $vtocaeRaw = (string) ($afip['vencimiento'] ?? '');
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

            guardarAuditoriaArca('recibido', limpiarQrParaAuditoria($cleanResp), $tipo_comp, $pv_emisor, $numcomp);
            logArcaCliente("CAE AUTORIZADO ANTES DE GRABAR | {$pv_emisor}-{$numcomp} (tipo {$tipo_comp}) | CAE {$cae} | USUARIO {$usuario_nombre}");
        }

        // --- 6. BUCLE DE GRABADO (HASTA 3 INTENTOS POR CONCURRENCIA) ---
        $idTicket = 0;
        $successGrabado = false;
        // IDs propios de las filas de Lineadeventa y promociones_ventas grabadas para este
        // comprobante: Lineadeventa para lvdesde/lvhasta en la cabecera del ticket,
        // promociones_ventas porque su idticket real recién se conoce después de insertar
        // el ticket (ver más abajo).
        $promoVentaIds = [];
        $lineaVentaIds = [];
        $maxIntentos = 3;

        for ($intento = 1; $intento <= $maxIntentos; $intento++) {
            try {
                // Un intento anterior pudo haber quedado a mitad de camino y hacer
                // rollback: los arrays de IDs no se revierten solos, hay que vaciarlos.
                $promoVentaIds = [];
                $lineaVentaIds = [];

                $pdo->beginTransaction();

                // Bloqueo de fila para el contador específico (CORRECCIÓN DB: PtoVta)
                $pdo->prepare("SELECT id FROM tickets WHERE PtoVta = ? AND tipocomp = ? ORDER BY id DESC LIMIT 1 FOR UPDATE")->execute([$pv_emisor, $tipo_comp]);

                // Solo las notas de CRÉDITO (3/8/13 y ticket de crédito 101) invierten la
                // operación: reingresan stock, acreditan la cuenta corriente, son egreso de
                // caja y revierten el asiento. Las notas de DÉBITO (2/7/12) son un cargo
                // adicional al cliente y se graban igual que una factura.
                $esNota = in_array($tipo_comp, [3, 8, 13, 101]);
                $esAdmin = isset($input['es_admin']) && $input['es_admin'] === true;

                // Solo los tickets internos (100) y el ticket de crédito (101) son
                // no fiscales; el resto son comprobantes de ARCA. Es el mismo
                // criterio que usa api_comprobantes_emitidos para separarlos.
                $esFiscal = ($tipo_comp == 100 || $tipo_comp == 101) ? 0 : 1;

                // fiscal e ivacosto faltaban en este INSERT y las dos columnas tienen
                // DEFAULT 0, así que toda venta quedaba grabada como no fiscal y sin
                // IVA de compra. Con ivacosto en cero el reporte de ventas calcula
                // (Total - IVA) - (Costo - IVA compra) restando el costo con IVA
                // incluido, y la utilidad sale más baja de lo que es.
                $stmtInsertLin = $pdo->prepare("INSERT INTO Lineadeventa (lista, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant,
                siniva, iva, ii, total, rubro, alicuota, mpago1, mpago2, mpago22, costo, ivacosto, usuario, marca, totaldescuento, sinremitir, vendedor, unitariofinallista, fiscal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                        round(((float) ($art['ivacosto'] ?? 0)) * $it['cantidad'], 2),
                        $_SESSION['usuario_nombre'],
                        $art['Marca'] ?? 1,
                        round($res['descU'] * $it['cantidad'], 2),
                        ($esAdmin ? $it['cantidad'] : 0),
                        $input['vendedor_id'],
                        $res['finalOriginalU'],
                        $esFiscal
                    ]);
                    $lineaVentaIds[] = (int) $pdo->lastInsertId();
                    $fiscalidad = $esFiscal;
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
                        $promoVentaIds[] = (int) $pdo->lastInsertId();
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
                            // La línea de descuento no tiene costo, pero sí comparte
                            // la fiscalidad del comprobante que la genera.
                            $pdo->prepare("INSERT INTO Lineadeventa (lista, pdv, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant, siniva, iva, total, rubro, alicuota, usuario, fiscal) VALUES (1, ?, ?, ?, ?, ?, 'DESC', 'Descuento', 1, ?, ?, ?, 'Descuentos', ?, ?, ?)")
                                ->execute([$pv_emisor, $tipo_comp, $numcomp, $input['cliente_id'], $input['fecha'], $netoD * -1, $ivaD * -1, $d['monto'] * -1, $d['ali'], $_SESSION['usuario_nombre'], $esFiscal]);
                            $lineaVentaIds[] = (int) $pdo->lastInsertId();
                        }
                    }
                }

                // OJO: antes esto se recalculaba con "SELECT MIN(id),MAX(id) FROM Lineadeventa
                // WHERE numcomp = ?", filtrando solo por número. Si otra emisión concurrente para
                // el mismo pdv/tipo pisaba momentáneamente el mismo numcomp (colisión de reserva
                // local resuelta más abajo, en la reconciliación con ARCA), ese rango por
                // proximidad de IDs podía terminar apuntando a filas de OTRO comprobante — o, tras
                // la reconciliación, la propia UPDATE de Lineadeventa usaba ese mismo rango
                // "prestado" y le pisaba el numcomp a líneas ajenas. Usar los IDs exactos que
                // devolvió cada INSERT (mismo patrón ya usado para controlstock/movimientosdestock)
                // elimina la ambigüedad: cada comprobante solo toca sus propias filas.
                $lvdesde = !empty($lineaVentaIds) ? min($lineaVentaIds) : 0;
                $lvhasta = !empty($lineaVentaIds) ? max($lineaVentaIds) : 0;

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
                // El CAE (y su vencimiento) ya se conocen ACÁ, de antes de este bucle: para
                // comprobantes fiscales es el que ARCA autorizó, para 100/101 quedan vacíos
                // porque no llevan CAE. Van directo en el INSERT, no hace falta un UPDATE
                // posterior.
                // Lo que queda pendiente de cobro (o de imputar, en una NC) es solo la
                // parte enviada a cuenta corriente, no el total: en un pago mixto
                // (ej. efectivo + cuenta corriente) el resto ya se cobró en el acto.
                $impCtaCte = ($mp1['tipo'] === 'Cuenta Corriente' ? (float) $input['imp1'] : 0)
                           + ($mp2['tipo'] === 'Cuenta Corriente' ? (float) $input['imp2'] : 0);

                $sqlTkt = "INSERT INTO tickets (mpago1, mpago1imp, mpago2, mpago2imp, tipocomp, PtoVta, numcomp, cliente, NumdocCli, lvdesde, lvhasta, subtotal, subtotal10, subtotal0, ii, iva,
                iva10, total, cae, fechacae, fecha, hora, usuario, recargo, descuento, pagada, vencimiento, remitidototal, afectastock, vendedor, letracomp, fecharemito, fecharemitostr, id_deposito, importectacte) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_FORMAT(NOW(), '%H:%i'), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtTkt = $pdo->prepare($sqlTkt);
                $stmtTkt->execute([
                    $mp1['nombre'],
                    (float) $input['imp1'],
                    $mp2['nombre'],
                    (float) $input['imp2'],
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
                    $cae,
                    $vtocae,
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
                    $input['fecha'],
                    $idDepositoVenta,
                    round($impCtaCte, 2)
                ]);
                $idTicket = $pdo->lastInsertId();
                if ($percepciones) {
                    registrarPercepciones($pdo, $percepciones, (int) $idTicket, (int) $input['cliente_id'], $input['fecha'],
                        in_array($tipo_comp, [3, 8, 13], true), $_SESSION['usuario_nombre']);
                }

                // El insert de promociones_ventas (paso 6, dentro del bucle de ítems)
                // ocurre antes de que exista este $idTicket real, así que grabó 0 en
                // idticket. Se corrige ahora contra los IDs propios ya capturados.
                if (!empty($promoVentaIds)) {
                    $inPv = implode(',', array_fill(0, count($promoVentaIds), '?'));
                    $pdo->prepare("UPDATE promociones_ventas SET idticket=? WHERE id IN ($inPv)")
                        ->execute(array_merge([$idTicket], $promoVentaIds));
                }

                // --- 9. CUENTA CORRIENTE ---
                if ($mp1['tipo'] === 'Cuenta Corriente' || $mp2['tipo'] === 'Cuenta Corriente') {
                    $impCC = $impCtaCte;
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
                    ['id' => $input['mp1'], 'imp' => (float) $input['imp1'], 'tarjeta' => $input['tarjeta1'] ?? null, 'n' => 1],
                    ['id' => $input['mp2'], 'imp' => (float) $input['imp2'], 'tarjeta' => $input['tarjeta2'] ?? null, 'n' => 2]
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
                                $stmtOpen = $pdo->prepare("SELECT COUNT(*) FROM controlcajas WHERE id = ? AND (cerradael IS NULL OR cerradael = '0000-00-00 00:00:00')");
                                $stmtOpen->execute([$idcontrolcaja]);
                                if ($stmtOpen->fetchColumn() == 0) {
                                    throw new Exception("Su caja se ha cerrado durante la operación. No puede realizar movimientos.");
                                }

                                $impReal = $pg['imp'];

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

                            // 2b. CUPÓN DE TARJETA (para la liquidación posterior)
                            $datosTarjeta = datosTarjetaCobro($pdo, $mpData, $pg['tarjeta'], "el medio de pago {$pg['n']}");
                            if ($datosTarjeta) {
                                registrarCupon($pdo, $datosTarjeta, $esNota ? -$pg['imp'] : $pg['imp'], (int) $pg['id'], 'tickets',
                                    (int) $idTicket, (int) $input['cliente_id'], $fiscal, $input['fecha'], $_SESSION['usuario_nombre']);
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
                // Percepciones practicadas: pasivo a depositar (la NC lo devuelve).
                foreach ($percepciones as $pAs) {
                    GrabarLineaAsiento($pdo, $numAsiento, $input['fecha'], retperCuenta($pdo, $pAs), $esNota ? $pAs['importe'] : 0, $esNota ? 0 : $pAs['importe'], $idTicket, "tickets", $fiscal, $_SESSION['usuario_nombre'], $detalleAsiento, $idEjercicio, $periodoactual);
                }

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
                    if (in_array($tipo_comp, [100, 101], true)) {
                        // Ticket/Ticket de Crédito: numcomp es una reserva local propia, sin
                        // autoridad externa — bump y reintentar es seguro acá.
                        $numcomp++;
                        continue;
                    }
                    // Comprobante fiscal: $numcomp es el número que ARCA YA autorizó (con CAE
                    // real emitido) antes de este bucle. Que choque acá significa que otro
                    // registro local ya ocupa ese pdv+tipo+número — no se puede "probar con el
                    // siguiente" sin inventar un número que ARCA nunca aprobó para ESTE
                    // comprobante. Es una alarma real (posible doble autorización del mismo
                    // número ante AFIP) que requiere revisión manual; el CAE queda igual en el
                    // log para no perderlo.
                    logArcaCliente("ERROR CRÍTICO: ARCA autorizó {$pv_emisor}-{$numcomp} (tipo {$tipo_comp}) con CAE {$cae}, pero ya existe un registro local con ese mismo número. No se grabó nada. Requiere corrección manual — verificar en AFIP si el número {$numcomp} quedó duplicado.");
                    throw new Exception("ARCA autorizó el comprobante {$pv_emisor}-{$numcomp} (CAE {$cae}), pero ya existe un registro local con ese mismo número. No se grabó nada para evitar una inconsistencia fiscal — contactá a soporte con este CAE para resolverlo a mano.");
                }
                throw $e;
            }
        }

        if (!$successGrabado)
            throw new Exception("No se pudo asignar numeración tras {$maxIntentos} intentos.");
        // El CAE (para comprobantes fiscales) ya se obtuvo y grabó ANTES de este bucle — acá no
        // hace falta llamar a ARCA de nuevo ni reconciliar nada, porque numcomp/cae/vtocae ya
        // son, por construcción, exactamente lo que ARCA autorizó.
        echo json_encode([
            'success' => true,
            'numcomp' => $numcomp,
            'cae' => $cae,
            'cae_pendiente' => false,
            'cae_error' => $caeError,
            'reconciliacion_fallida' => false,
            'id_ticket' => $idTicket
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction())
            $pdo->rollBack();

        $idAux = null;
        if (isset($tipo_comp) && !in_array($tipo_comp, [100, 101], true)) {
            // Cualquier comprobante fiscal que no se termine de grabar queda
            // registrado acá, tenga o no CAE — para no perder nunca el rastro de
            // qué se intentó vender y por qué falló.
            $idAux = guardarEnAuxiliares(
                $pdo,
                !empty($cae ?? '') ? 'cae_sin_grabar' : 'sin_cae',
                $tipo_comp,
                $pv_emisor ?? 0,
                $numcomp ?? 0,
                $cae ?? '',
                $vtocae ?? '',
                $input ?? [],
                $processedItems ?? [],
                $acum ?? [],
                $total_final_server ?? 0,
                $usuario_nombre,
                $e->getMessage()
            );
        }

        if (!empty($cae ?? '')) {
            // ARCA YA autorizó y consumió un número real: no es seguro reintentar
            // (duplicaría el comprobante ante AFIP). Se responde como éxito con
            // reconciliacion_fallida=true para que la pantalla se resetee sola
            // (mismo codepath que usa newfactura.js para el caso de éxito) y el
            // cajero no vuelva a apretar grabar sobre el mismo carrito.
            echo json_encode([
                'success' => true,
                'numcomp' => $numcomp,
                'cae' => $cae,
                'cae_pendiente' => false,
                'cae_error' => "ARCA autorizó el comprobante {$pv_emisor}-{$numcomp} (CAE {$cae}) pero no se pudo grabar en el sistema ({$e->getMessage()})." .
                    ($idAux ? " Guardado en la cola de revisión (auxticket #{$idAux})." : " ADEMÁS no se pudo guardar en la cola de revisión — avisar a soporte YA con este CAE.") .
                    " NO reintentes: contactá a soporte con este CAE para resolverlo a mano.",
                'reconciliacion_fallida' => true,
                'id_ticket' => null
            ]);
            exit;
        }

        // Sin CAE: no se gastó ningún número fiscal real. Es seguro que la pantalla
        // se quede tal cual (carrito cargado) para reintentar o pasar a Ticket no
        // fiscal — mismo comportamiento de siempre, solo que ahora además queda
        // registrado en la cola auxiliar.
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    ?>
