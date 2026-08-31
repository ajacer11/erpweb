<?php
/**
 * api_comprobantes_emitidos.php - MOTOR DE REPORTES Y EXPORTACIÓN
 * Migración ESTRICTA de Informes - Comprobantes.vb (Tabla: tickets)
 */

// 1. SEGURIDAD CENTRALIZADA
// Incluye: Session start, Validación AJAX estricta, Conexión DB ($pdo)
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
        case 'export':
            // Lógica de parámetros (Mantenemos compatibilidad con tu código anterior)
            if ($action === 'list') {
                // Listado suele venir por POST JSON
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) $input = $_GET; // Fallback
            } else {
                // Export suele venir por GET
                $input = $_GET;
            }
            
            // Validar parámetros mínimos para evitar Warnings
            $desde = $input['desde'] ?? date('Y-m-01');
            $hasta = $input['hasta'] ?? date('Y-m-d');
            $tipoFecha = $input['tipo_fecha'] ?? 'fecha'; // 'fecha' o 'fecharemito'
            
            $where = " WHERE t.$tipoFecha BETWEEN ? AND ? ";
            $params = [$desde, $hasta];

            // PERMISO ESPECIAL: un usuario restringido a "un solo vendedor" solo puede
            // ver sus propios comprobantes, sin importar qué filtro haya elegido en pantalla.
            if (esVendedorRestringido()) {
                $where .= " AND t.vendedor = ? ";
                $params[] = idVendedorRestringido();
            } elseif (!empty($input['vendedor_id'])) {
                $where .= " AND t.vendedor = ? ";
                $params[] = $input['vendedor_id'];
            }
            if (!empty($input['cliente_id'])) {
                $where .= " AND t.cliente = ? ";
                $params[] = $input['cliente_id'];
            }
            if (!empty($input['tipo_comp'])) {
                switch($input['tipo_comp']) {
                    case 'FACTURA A': $where .= " AND t.tipocomp = '1' "; break;
                    case 'NOTA DE CREDITO A': $where .= " AND t.tipocomp = '3' "; break;
                    case 'FACTURA B': $where .= " AND t.tipocomp = '6' "; break;
                    case 'NOTA DE CREDITO B': $where .= " AND t.tipocomp = '8' "; break;
                    case 'TICKET': $where .= " AND t.tipocomp = '100' "; break;
                    case 'TICKET DE CREDITO': $where .= " AND t.tipocomp = '101' "; break;
                    case 'FISCALES': $where .= " AND (t.tipocomp <> '100' AND t.tipocomp <> '101') "; break;
                    case 'NO FISCALES': $where .= " AND (t.tipocomp = '100' OR t.tipocomp = '101') "; break;
                }
            }
            if (!empty($input['mpago_txt'])) {
                $where .= " AND (t.mpago1 = ? OR t.mpago2 = ?) ";
                $params[] = $input['mpago_txt'];
                $params[] = $input['mpago_txt'];
            }
            if (!empty($input['unidad_id'])) {
                // OJO: 'd.unidad' implica un JOIN con 'alb_clientes d'. 
                // Asegurar que la tabla clientes tenga alias 'd' en la query principal.
                $where .= " AND d.unidad = ? ";
                $params[] = $input['unidad_id'];
            }
            if (!empty($input['provincia'])) {
                $where .= " AND d.provincia = ? ";
                $params[] = $input['provincia'];
            }
            if (!empty($input['rama_id'])) {
                $where .= " AND d.rama = ? ";
                $params[] = $input['rama_id'];
            }
            if (isset($input['sin_remitir']) && ($input['sin_remitir'] === true || $input['sin_remitir'] === 'true')) {
                $where .= " AND t.remitidototal = 0 ";
            }

            $sql = "SELECT 
                        t.fecha, t.hora, t.numcomp, t.total, t.descuento, t.recargo,
                        (t.subtotal + t.subtotal10 + t.subtotal0) as subtotal_calc,
                        t.ii as imp_int,
                        (t.iva + t.iva10) as iva_calc,
                        t.ptovta, t.tipocomp, t.id as ticket_id,
                        t.cae, t.fechacae,
                        t.remitidototal, t.usuario,
                        t.mpago1, t.mpago1imp, 
                        t.mpago2, t.mpago2imp,
                        t.numremito, t.fecharemitostr,
                        d.razon as cliente_nombre,
                        rc.rama as rama_nombre,
                        d.provincia as jurisdiccion,
                        v.nombre as vendedor_nombre,
                        CASE t.tipocomp 
                            WHEN 1 THEN 'FCA' WHEN 3 THEN 'NCA' WHEN 7 THEN 'NDB'
                            WHEN 11 THEN 'FCC' WHEN 12 THEN 'NDC' WHEN 13 THEN 'NCC'
                            WHEN 6 THEN 'FCB' WHEN 8 THEN 'NCB' WHEN 2 THEN 'NDA'
                            WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' 
                            ELSE 'Otro' END AS tipo_str
                    FROM tickets t
                    LEFT OUTER JOIN alb_clientes d ON t.cliente = d.id
                    LEFT OUTER JOIN ramasclientes rc ON d.rama = rc.id
                    LEFT OUTER JOIN vendedores v ON t.vendedor = v.id
                    $where
                    ORDER BY t.fecha DESC, t.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($action === 'export') {
                // EXPORTACIÓN SEGURA VIA AJAX
                // Capturamos la salida HTML en un buffer
                ob_start();
                generarExcelLindo($data, $desde, $hasta);
                $htmlContent = ob_get_clean();

                // Devolvemos JSON con el contenido en Base64
                echo json_encode([
                    'success' => true,
                    'status' => 'ok',
                    'filename' => "Reporte_Comprobantes_" . date('Ymd_His') . ".xls",
                    'file_content' => base64_encode($htmlContent)
                ]);
            } else {
                echo json_encode(['success' => true, 'data' => $data]);
            }
            break;

        case 'retry_cae':
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $ticketId = (int)($input['ticket_id'] ?? 0);
            if ($ticketId <= 0) throw new Exception('Comprobante inválido.');

            $lockName = 'retry_cae_ticket_' . $ticketId;
            $stLock = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $stLock->execute([$lockName]);
            if ((int)$stLock->fetchColumn() !== 1) {
                throw new Exception('El CAE de este comprobante ya está siendo solicitado.');
            }

            try {
                $stmt = $pdo->prepare("SELECT t.*, c.condicionfiscal, c.numdoc AS cliente_numdoc
                    FROM tickets t
                    LEFT JOIN alb_clientes c ON c.id = t.cliente
                    WHERE t.id = ? LIMIT 1");
                $stmt->execute([$ticketId]);
                $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$ticket) throw new Exception('No se encontró el comprobante.');
                if (in_array((int)$ticket['tipocomp'], [100, 101], true)) {
                    throw new Exception('Los comprobantes no fiscales no requieren CAE.');
                }
                if (trim((string)$ticket['cae']) !== '') {
                    echo json_encode(['success' => true, 'cae' => (string)$ticket['cae'], 'already_authorized' => true]);
                    break;
                }

                $cuit = (string)($_SESSION['cliente_cuit'] ?? $_SESSION['tenant_cuit'] ?? '');
                $modo = (int)($_SESSION['modo'] ?? 0);
                $pv = (int)$ticket['PtoVta'];
                $tipoComp = (int)$ticket['tipocomp'];
                $numComp = (int)$ticket['numcomp'];
                $comprobanteAsociado = null;

                $tiposAsociados = [2 => 1, 3 => 1, 7 => 6, 8 => 6, 12 => 11, 13 => 11];
                if (isset($tiposAsociados[$tipoComp])) {
                    $associated = $input['associatedDoc'] ?? null;
                    $pvAsoc = (int)($associated['pv'] ?? 0);
                    $numAsoc = (int)($associated['num'] ?? 0);
                    $tipoAsoc = $tiposAsociados[$tipoComp];
                    if ($pvAsoc < 1 || $numAsoc < 1) {
                        throw new Exception('Para re-solicitar el CAE de esta nota debe asociar el comprobante original.');
                    }

                    $stAsoc = $pdo->prepare("SELECT cliente, fecha FROM tickets WHERE PtoVta = ? AND numcomp = ? AND tipocomp = ? LIMIT 1");
                    $stAsoc->execute([$pvAsoc, $numAsoc, $tipoAsoc]);
                    $origen = $stAsoc->fetch(PDO::FETCH_ASSOC);
                    if (!$origen) {
                        throw new Exception('No existe una factura compatible con el punto de venta y número ingresados.');
                    }
                    if ((int)$origen['cliente'] !== (int)$ticket['cliente']) {
                        throw new Exception('El comprobante asociado pertenece a otro cliente.');
                    }

                    $comprobanteAsociado = [
                        'tipo' => $tipoAsoc,
                        'puntoVenta' => $pvAsoc,
                        'numero' => $numAsoc,
                        'cuit' => $cuit,
                        'fecha' => str_replace('-', '', substr((string)$origen['fecha'], 0, 10))
                    ];
                }

                $requestJson = function(string $url, ?array $payload = null, bool $allowUnavailable = false, string $serviceName = 'servicio ARCA') {
                    $ch = curl_init($url);
                    $opts = [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_TIMEOUT => 45,
                        CURLOPT_SSL_VERIFYPEER => false,
                        CURLOPT_ENCODING => '',
                        CURLOPT_USERAGENT => 'SistemaERP/CAE'
                    ];
                    if ($payload !== null) {
                        $opts[CURLOPT_POST] = true;
                        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/json'];
                        $opts[CURLOPT_POSTFIELDS] = json_encode($payload);
                    }
                    curl_setopt_array($ch, $opts);
                    $raw = curl_exec($ch);
                    $curlError = curl_error($ch);
                    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                    curl_close($ch);
                    if ($raw === false) throw new Exception('No se pudo comunicar con ARCA: ' . $curlError);
                    if ($allowUnavailable && ($httpCode === 404 || $httpCode >= 500)) {
                        return ['resultado' => 'R', 'error' => 'Consulta previa no disponible'];
                    }
                    $clean = preg_replace('/^\xEF\xBB\xBF/', '', trim($raw));
                    $data = json_decode($clean, true);
                    // Algunos servidores agregan warnings/HTML antes o después del JSON.
                    if (!is_array($data)) {
                        $first = strpos($clean, '{');
                        $last = strrpos($clean, '}');
                        if ($first !== false && $last !== false && $last >= $first) {
                            $data = json_decode(substr($clean, $first, $last - $first + 1), true);
                        }
                    }
                    if (!is_array($data)) {
                        $preview = trim(strip_tags($clean));
                        $preview = preg_replace('/\s+/', ' ', $preview);
                        if (strlen($preview) > 180) $preview = substr($preview, 0, 180) . '...';
                        throw new Exception("Respuesta inválida de {$serviceName} (HTTP {$httpCode}, {$contentType})" . ($preview !== '' ? ": {$preview}" : ': respuesta vacía.'));
                    }
                    return $data;
                };

                // Primero recuperamos una autorización que pudo haberse emitido aunque la
                // respuesta original se haya perdido por timeout.
                $query = http_build_query(['cuit' => $cuit, 'pv' => $pv, 'tipo' => $tipoComp, 'num' => $numComp, 'modo' => $modo]);
                $afip = $requestJson('https://erp.westit.com.ar/ARCA/fe_get_cae.php?' . $query, null, true, 'consulta de CAE');

                if (($afip['resultado'] ?? '') !== 'A') {
                    $mapCond = function($cond) {
                        return match ((string)$cond) {
                            'Responsable Inscripto' => 1,
                            'Exento' => 4,
                            'Monotributo' => 6,
                            default => 5
                        };
                    };
                    $tipoOperacion = in_array($tipoComp, [1, 6, 11], true) ? 'F'
                        : (in_array($tipoComp, [2, 7, 12], true) ? 'D' : 'C');
                    $condReceptor = $mapCond($ticket['condicionfiscal'] ?? '');
                    $iva = [];
                    if ((float)$ticket['iva'] > 0) $iva[] = ['id' => 5, 'baseImp' => round((float)$ticket['subtotal'], 2), 'importe' => round((float)$ticket['iva'], 2)];
                    if ((float)$ticket['iva10'] > 0) $iva[] = ['id' => 4, 'baseImp' => round((float)$ticket['subtotal10'], 2), 'importe' => round((float)$ticket['iva10'], 2)];

                    $payload = [
                        'tipo' => $tipoOperacion,
                        'emisorCuit' => $cuit,
                        'emisorCondicionIva' => $mapCond($_SESSION['condicion_iva_empresa'] ?? ''),
                        'docTipo' => $condReceptor === 5 ? 99 : 80,
                        'docNro' => $condReceptor === 5 ? 0 : (int)($ticket['cliente_numdoc'] ?? $ticket['NumdocCli'] ?? 0),
                        'puntoVenta' => $pv,
                        'numeroComp' => $numComp,
                        'condicionIVA' => $condReceptor,
                        'importeNeto' => round((float)$ticket['subtotal'] + (float)$ticket['subtotal10'] + (float)$ticket['subtotal0'], 2),
                        'importeIVA' => round((float)$ticket['iva'] + (float)$ticket['iva10'], 2),
                        'importeTributo' => round((float)$ticket['ii'], 2),
                        'iva' => $iva,
                        'produccion' => $modo
                    ];
                    if ($comprobanteAsociado !== null) {
                        $payload['comprobanteAsociado'] = $comprobanteAsociado;
                    }
                    $afip = $requestJson('https://erp.westit.com.ar/ARCA/new_fe_cae_api.php', $payload, false, 'solicitud de CAE');

                    // La API sincroniza numeración. Nunca vinculamos al ticket un CAE de otro número.
                    if (isset($afip['numeroComprobante']) && (int)$afip['numeroComprobante'] !== $numComp) {
                        throw new Exception('ARCA espera el comprobante N° ' . $afip['numeroComprobante'] . ', pero el registro local es el N° ' . $numComp . '. No se vinculó el CAE para evitar una inconsistencia fiscal.');
                    }
                }

                if (($afip['resultado'] ?? '') !== 'A' || empty($afip['cae'])) {
                    $detalle = $afip['error'] ?? implode(' - ', $afip['observaciones'] ?? []) ?: 'ARCA rechazó la solicitud sin detalle.';
                    throw new Exception($detalle);
                }

                $vtoRaw = preg_replace('/[^0-9]/', '', (string)($afip['vencimiento'] ?? ''));
                $vto = strlen($vtoRaw) === 8 ? $vtoRaw : '';
                $upd = $pdo->prepare("UPDATE tickets SET cae = ?, fechacae = ? WHERE id = ? AND (cae IS NULL OR cae = '')");
                $upd->execute([(string)$afip['cae'], $vto, $ticketId]);
                file_put_contents('log_arca_audit.txt', date('Y-m-d H:i:s') . " CAE RE-SOLICITADO | TICKET {$ticketId} | {$pv}-{$numComp} | CAE {$afip['cae']} | USUARIO " . ($_SESSION['usuario_nombre'] ?? '') . "\n", FILE_APPEND);

                echo json_encode(['success' => true, 'cae' => (string)$afip['cae'], 'vencimiento' => $vto]);
            } finally {
                $stUnlock = $pdo->prepare('SELECT RELEASE_LOCK(?)');
                $stUnlock->execute([$lockName]);
            }
            break;

        default: throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    // Si falla algo, devolvemos JSON de error (incluso en exportación)
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

/**
 * Genera el contenido HTML para el Excel.
 * MODIFICADO: Ya no envía headers ni hace exit(). Solo imprime el HTML.
 */
function generarExcelLindo($data, $desde, $hasta) {
    // Agregamos namespaces XML para mejor compatibilidad con Excel
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head><meta charset="utf-8" /></head><body>';
    
    echo "<table border='1'>";
    // Cabecera institucional
    echo "<tr><th colspan='13' style='background-color:#001020; color:#00ffff; font-size:18px; padding:10px;'>INFORME DE COMPROBANTES EMITIDOS</th></tr>";
    echo "<tr><th colspan='13' style='background-color:#001020; color:#ffffff; font-size:12px;'>Período: $desde al $hasta</th></tr>";
    
    // Encabezados de columnas
    echo "<tr style='background-color:#334455; color:white; font-weight:bold;'>
            <th>FECHA</th><th>HORA</th><th>TIPO</th><th>NÚMERO</th><th>CLIENTE</th>
            <th>SUBTOTAL</th><th>IVA</th><th>TOTAL</th>
            <th>PAGO 1</th><th>IMP 1</th><th>PAGO 2</th><th>IMP 2</th>
            <th>VENDEDOR</th><th>USUARIO</th>
          </tr>";

    $sumSub = 0; $sumIva = 0; $sumTotal = 0;

    foreach ($data as $r) {
        $factor = in_array($r['tipocomp'], [3, 8, 13, 101]) ? -1 : 1;
        $sumSub += ($r['subtotal_calc'] * $factor);
        $sumIva += ($r['iva_calc'] * $factor);
        $sumTotal += ($r['total'] * $factor);

        echo "<tr>
                <td>{$r['fecha']}</td>
                <td>{$r['hora']}</td>
                <td>{$r['tipo_str']}</td>
                <td>'{$r['ptovta']}-{$r['numcomp']}</td>
                <td>" . htmlspecialchars($r['cliente_nombre'] ?? '') . "</td>
                <td align='right'>" . number_format($r['subtotal_calc'], 2, '.', '') . "</td>
                <td align='right'>" . number_format($r['iva_calc'], 2, '.', '') . "</td>
                <td align='right' style='font-weight:bold;'>" . number_format($r['total'], 2, '.', '') . "</td>
                <td>" . htmlspecialchars($r['mpago1'] ?? '') . "</td>
                <td align='right'>" . number_format($r['mpago1imp'], 2, '.', '') . "</td>
                <td>" . htmlspecialchars($r['mpago2'] ?? '') . "</td>
                <td align='right'>" . number_format($r['mpago2imp'], 2, '.', '') . "</td>
                <td>" . htmlspecialchars($r['vendedor_nombre'] ?? '') . "</td>
                <td>" . htmlspecialchars($r['usuario'] ?? '') . "</td>
              </tr>";
    }
    
    // Fila de Totales
    echo "<tr style='background-color:#001020; color:#00ffff; font-weight:bold;'>
            <td colspan='5' align='right'>TOTALES GENERALES:</td>
            <td align='right'>" . number_format($sumSub, 2, '.', '') . "</td>
            <td align='right'>" . number_format($sumIva, 2, '.', '') . "</td>
            <td align='right'>" . number_format($sumTotal, 2, '.', '') . "</td>
            <td colspan='6'></td>
          </tr>";
    echo "</table>";
    echo "</body></html>";
}
?>
