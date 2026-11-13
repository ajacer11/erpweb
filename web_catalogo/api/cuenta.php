<?php
/**
 * cuenta.php - Área de clientes: resumen, cuenta corriente, comprobantes, pedidos,
 * envío de pedidos y pagos informados. Todo filtrado por el cliente de la sesión.
 */
declare(strict_types=1);

const TIPOS_COMPROBANTE = [
    0 => 'Comprobante', 1 => 'Factura A', 2 => 'Nota de Débito A', 3 => 'Nota de Crédito A', 6 => 'Factura B', 7 => 'Nota de Débito B',
    8 => 'Nota de Crédito B', 11 => 'Factura C', 12 => 'Nota de Débito C', 13 => 'Nota de Crédito C', 100 => 'Ticket', 101 => 'Devolución', 1000 => 'Remito',
];
const ESTADOS_PEDIDO = [0 => 'pendiente', 1 => 'facturado', 2 => 'anulado'];

function nombreComprobante(int $tipo): string
{
    return TIPOS_COMPROBANTE[$tipo] ?? 'Comprobante';
}

function numeroComprobante($ptoVta, $num): string
{
    return str_pad((string) (int) $ptoVta, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string) (int) $num, 8, '0', STR_PAD_LEFT);
}

function saldoCliente(int $clienteId): float
{
    global $pdo;
    $st = $pdo->prepare("SELECT ROUND(IFNULL(SUM(debe), 0) - IFNULL(SUM(haber), 0), 2) FROM ccclientes WHERE cliente = ?");
    $st->execute([$clienteId]);
    return (float) $st->fetchColumn();
}

/** SQL del saldo pendiente de un comprobante (alias t). Igual que api_facturas_pendientes_clientes.php. */
const SQL_SALDO_COMPROBANTE = "ROUND(COALESCE(t.importectacte, t.total) - COALESCE((SELECT SUM(importepagado) FROM cobrosfacturasclientes WHERE idfactura = t.id), 0), 2)";

function comprobantesPendientes(int $clienteId): array
{
    global $pdo;
    $st = $pdo->prepare("SELECT t.id, t.tipocomp, t.PtoVta, t.numcomp, t.fecha, t.vencimiento, t.total, " . SQL_SALDO_COMPROBANTE . " AS saldo
                         FROM tickets t
                         WHERE t.cliente = ? AND t.pagada = 0 AND t.tipocomp NOT IN (3, 8, 13, 101)
                         ORDER BY t.vencimiento, t.fecha");
    $st->execute([$clienteId]);
    $hoy = date('Y-m-d');
    $out = [];
    foreach ($st->fetchAll() as $t) {
        if ((float) $t['saldo'] <= 0.009) continue;
        $out[] = [
            'id' => (int) $t['id'],
            'tipo' => nombreComprobante((int) $t['tipocomp']),
            'numero' => numeroComprobante($t['PtoVta'], $t['numcomp']),
            'fecha' => $t['fecha'],
            'vencimiento' => $t['vencimiento'],
            'vencida' => $t['vencimiento'] && $t['vencimiento'] < $hoy,
            'total' => (float) $t['total'],
            'saldo' => (float) $t['saldo'],
        ];
    }
    return $out;
}

/** Total con IVA de las líneas de un pedido (preciounit es neto). */
const SQL_TOTAL_PEDIDO = "(SELECT ROUND(SUM(l.cantidad * (l.preciounit * (1 + COALESCE(l.poriva, pr.poriva, 0) / 100) + IFNULL(l.impint, 0))), 2)
                           FROM lineapedidosclientes l LEFT JOIN alb_productos pr ON pr.codigo = l.producto WHERE l.numpedido = pc.numpedido)";

// =================================================================== Handlers

function hResumen(): array
{
    global $pdo, $CFG;
    $c = requiereCliente();
    $st = $pdo->prepare("SELECT c.razon, c.numdoc, c.domicilio, c.localidad, c.provincia, c.telefono, c.celular, c.mail, c.condicionfiscal,
                                v.nombre AS vendedor, v.celular AS vendedor_celular, v.mail AS vendedor_mail
                         FROM alb_clientes c LEFT JOIN vendedores v ON v.id = c.vendedorid WHERE c.id = ?");
    $st->execute([$c['cliente_id']]);
    $datos = $st->fetch() ?: [];
    $st = $pdo->prepare("SELECT email FROM web_usuarios WHERE id = ?");
    $st->execute([$c['web_usuario_id']]);
    $datos['email_acceso'] = $st->fetchColumn() ?: null;
    $datos['usuario'] = $c['usuario'];

    $pend = comprobantesPendientes($c['cliente_id']);
    $st = $pdo->prepare("SELECT pc.numpedido, pc.fechapedido, pc.estadofac, " . SQL_TOTAL_PEDIDO . " AS total
                         FROM pedidosclientes pc WHERE pc.cliente = ? ORDER BY pc.numpedido DESC LIMIT 5");
    $st->execute([$c['cliente_id']]);
    $pedidos = array_map(fn($p) => ['numero' => (int) $p['numpedido'], 'fecha' => $p['fechapedido'], 'estado' => ESTADOS_PEDIDO[(int) $p['estadofac']] ?? 'pendiente', 'total' => (float) $p['total']], $st->fetchAll());

    return [
        'datos' => $datos,
        'saldo' => saldoCliente($c['cliente_id']),
        'pendientes' => [
            'cantidad' => count($pend),
            'total' => round(array_sum(array_column($pend, 'saldo')), 2),
            'vencido' => round(array_sum(array_map(fn($p) => $p['vencida'] ? $p['saldo'] : 0, $pend)), 2),
        ],
        'ultimosPedidos' => $pedidos,
        'datosPago' => $CFG['datos_pago'],
    ];
}

function hMovimientos(): array
{
    global $pdo;
    $c = requiereCliente();
    $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['desde'] ?? '')) ? $_GET['desde'] : date('Y-m-d', strtotime('-6 months'));
    $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['hasta'] ?? '')) ? $_GET['hasta'] : date('Y-m-d');

    $st = $pdo->prepare("SELECT ROUND(IFNULL(SUM(debe), 0) - IFNULL(SUM(haber), 0), 2) FROM ccclientes WHERE cliente = ? AND fecha < ?");
    $st->execute([$c['cliente_id'], $desde]);
    $saldo = (float) $st->fetchColumn();
    $saldoAnterior = $saldo;

    $st = $pdo->prepare("SELECT cc.id, cc.fecha, cc.debe, cc.haber, cc.tabla, cc.compasoc,
                                t.tipocomp, t.PtoVta, t.numcomp
                         FROM ccclientes cc
                         LEFT JOIN tickets t ON cc.tabla = 'tickets' AND t.id = cc.compasoc
                         WHERE cc.cliente = ? AND cc.fecha BETWEEN ? AND ?
                         ORDER BY cc.fecha, cc.id");
    $st->execute([$c['cliente_id'], $desde, $hasta]);
    $movs = [];
    foreach ($st->fetchAll() as $m) {
        $saldo = round($saldo + (float) $m['debe'] - (float) $m['haber'], 2);
        $esTicket = $m['tabla'] === 'tickets' && $m['tipocomp'] !== null;
        $movs[] = [
            'fecha' => $m['fecha'],
            'concepto' => $esTicket ? nombreComprobante((int) $m['tipocomp']) : ($m['tabla'] === 'pagos' ? 'Recibo de pago' : 'Movimiento'),
            'numero' => $esTicket ? numeroComprobante($m['PtoVta'], $m['numcomp']) : ($m['tabla'] === 'pagos' ? str_pad((string) $m['compasoc'], 8, '0', STR_PAD_LEFT) : ''),
            'debe' => round((float) $m['debe'], 2),
            'haber' => round((float) $m['haber'], 2),
            'saldo' => $saldo,
            'comprobanteId' => $esTicket ? (int) $m['compasoc'] : null,
        ];
    }
    return ['desde' => $desde, 'hasta' => $hasta, 'saldoAnterior' => $saldoAnterior, 'movimientos' => $movs, 'saldoActual' => saldoCliente($c['cliente_id'])];
}

function hComprobantes(): array
{
    global $pdo;
    $c = requiereCliente();
    if (!empty($_GET['pendientes'])) return ['items' => comprobantesPendientes($c['cliente_id'])];
    $st = $pdo->prepare("SELECT t.id, t.tipocomp, t.PtoVta, t.numcomp, t.fecha, t.vencimiento, t.total, t.pagada, " . SQL_SALDO_COMPROBANTE . " AS saldo
                         FROM tickets t WHERE t.cliente = ? AND t.fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                         ORDER BY t.fecha DESC, t.id DESC LIMIT 300");
    $st->execute([$c['cliente_id']]);
    return ['items' => array_map(fn($t) => [
        'id' => (int) $t['id'],
        'tipo' => nombreComprobante((int) $t['tipocomp']),
        'numero' => numeroComprobante($t['PtoVta'], $t['numcomp']),
        'fecha' => $t['fecha'],
        'vencimiento' => $t['vencimiento'],
        'total' => (float) $t['total'],
        'saldo' => in_array((int) $t['tipocomp'], [3, 8, 13, 101], true) || (int) $t['pagada'] ? 0.0 : max(0, (float) $t['saldo']),
    ], $st->fetchAll())];
}

/**
 * Comprobante para ver/imprimir/guardar como PDF: es la misma vista que imprime el ERP
 * (imprimir_ticket.php), solo si el comprobante es del cliente logueado.
 */
function hVerComprobante(): never
{
    global $pdo, $SITIO, $pdo_master; // $pdo_master: db.php (incluido por imprimir_ticket.php) lo usa en este scope
    $c = clienteActual();
    $id = (int) ($_GET['id'] ?? 0);
    $st = $pdo->prepare("SELECT cliente FROM tickets WHERE id = ?");
    $st->execute([$id]);
    if (!$c || (int) $st->fetchColumn() !== $c['cliente_id']) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Comprobante no encontrado.';
        exit;
    }
    // imprimir_ticket.php se conecta con db.php, que toma la empresa de la sesión. Se le pasan
    // los datos de este sitio solo durante el include (la sesión de la web no habilita el ERP:
    // el ERP exige además usuario_id y usa otra cookie de sesión).
    $_SESSION['tenant_cuit'] = (string) $SITIO['cuit'];
    $_SESSION['tenant_db_id'] = $SITIO['db_id'] ?: null;
    register_shutdown_function(function () {
        unset($_SESSION['tenant_cuit'], $_SESSION['tenant_db_id']);
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    });
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex');
    $_GET = ['id' => (string) $id];
    chdir(ERP_DIR);
    require ERP_DIR . '/imprimir_ticket.php';
    exit;
}

function hPedidos(): array
{
    global $pdo;
    $c = requiereCliente();
    $st = $pdo->prepare("SELECT pc.numpedido, pc.fechapedido, pc.estadofac, pc.observaciones, (wp.numpedido IS NOT NULL) AS web, " . SQL_TOTAL_PEDIDO . " AS total,
                                (SELECT COUNT(*) FROM lineapedidosclientes l WHERE l.numpedido = pc.numpedido) AS items
                         FROM pedidosclientes pc LEFT JOIN web_pedidos wp ON wp.numpedido = pc.numpedido
                         WHERE pc.cliente = ? ORDER BY pc.numpedido DESC LIMIT 100");
    $st->execute([$c['cliente_id']]);
    return ['items' => array_map(fn($p) => [
        'numero' => (int) $p['numpedido'],
        'fecha' => $p['fechapedido'],
        'estado' => ESTADOS_PEDIDO[(int) $p['estadofac']] ?? 'pendiente',
        'web' => (bool) $p['web'],
        'total' => (float) $p['total'],
        'items' => (int) $p['items'],
        'observaciones' => preg_replace('/^\[WEB\]\s*/', '', (string) $p['observaciones']),
    ], $st->fetchAll())];
}

function hPedido(): array
{
    global $pdo;
    $c = requiereCliente();
    $num = (int) ($_GET['numero'] ?? 0);
    $st = $pdo->prepare("SELECT numpedido, fechapedido, estadofac, observaciones FROM pedidosclientes WHERE numpedido = ? AND cliente = ?");
    $st->execute([$num, $c['cliente_id']]);
    $p = $st->fetch();
    if (!$p) throw new ApiError('Pedido no encontrado.', 404);
    $st = $pdo->prepare("SELECT l.producto, l.descripcion, l.cantidad,
                                ROUND(l.preciounit * (1 + COALESCE(l.poriva, pr.poriva, 0) / 100) + IFNULL(l.impint, 0), 2) AS precio
                         FROM lineapedidosclientes l LEFT JOIN alb_productos pr ON pr.codigo = l.producto WHERE l.numpedido = ? ORDER BY l.id");
    $st->execute([$num]);
    $lineas = array_map(fn($l) => ['codigo' => $l['producto'], 'descripcion' => $l['descripcion'], 'cantidad' => (float) $l['cantidad'],
        'precio' => (float) $l['precio'], 'subtotal' => round((float) $l['precio'] * (float) $l['cantidad'], 2)], $st->fetchAll());
    return [
        'numero' => (int) $p['numpedido'],
        'fecha' => $p['fechapedido'],
        'estado' => ESTADOS_PEDIDO[(int) $p['estadofac']] ?? 'pendiente',
        'observaciones' => preg_replace('/^\[WEB\]\s*/', '', (string) $p['observaciones']),
        'lineas' => $lineas,
        'total' => round(array_sum(array_column($lineas, 'subtotal')), 2),
    ];
}

/** Envía el carrito como pedido (pedidosclientes + lineapedidosclientes, igual que el ERP). */
function hEnviarPedido(): array
{
    global $pdo, $CFG;
    $c = requiereCliente();
    if ($CFG['pedidos_habilitados'] !== '1') throw new ApiError('Por ahora no se reciben pedidos desde la web.', 403);
    $in = cuerpoJson();

    $cant = [];
    foreach (array_slice((array) ($in['items'] ?? []), 0, 300) as $it) {
        $cod = (string) ($it['codigo'] ?? '');
        $q = (int) ($it['cantidad'] ?? 0);
        if ($cod === '' || $q <= 0) continue;
        if ($q > 100000) throw new ApiError('Cantidad inválida.');
        $cant[$cod] = ($cant[$cod] ?? 0) + $q;
    }
    if (!$cant) throw new ApiError('El pedido está vacío.');

    $prods = array_column(traerProductosCompletos(array_keys($cant)), null, 'codigo');
    $st = $pdo->prepare("SELECT codigo, IFNULL(ImpInt, 0) AS impint FROM alb_productos WHERE codigo IN (" . implode(',', array_fill(0, count($cant), '?')) . ")");
    $st->execute(array_keys($cant));
    $impInt = $st->fetchAll(PDO::FETCH_KEY_PAIR);

    $lineas = [];
    $total = 0.0;
    $problemas = [];
    foreach ($cant as $cod => $q) {
        $p = $prods[$cod] ?? null;
        if (!$p) { $problemas[] = "El producto $cod ya no está disponible."; continue; }
        if ($p['precio'] === null) { $problemas[] = "\"{$p['nombre']}\" no tiene precio: consultanos."; continue; }
        $lineas[] = ['p' => $p, 'cantidad' => $q, 'impint' => (float) ($impInt[$cod] ?? 0)];
        $total += $p['precio'] * $q;
    }
    if ($problemas) throw new ApiError(implode("\n", $problemas), 409, 'carrito_cambio');
    $total = round($total, 2);
    $minimo = (float) $CFG['pedido_monto_minimo'];
    if ($minimo > 0 && $total < $minimo) {
        throw new ApiError('El pedido mínimo es de $ ' . number_format($minimo, 2, ',', '.') . '.', 409, 'minimo');
    }

    $obs = trim(preg_replace('/\s+/', ' ', (string) ($in['observaciones'] ?? '')));
    $obsCab = mb_substr('[WEB] ' . $obs, 0, 100);

    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare("SELECT vendedorid FROM alb_clientes WHERE id = ?");
        $st->execute([$c['cliente_id']]);
        $vendedor = (int) $st->fetchColumn();

        $num = (int) $pdo->query("SELECT COALESCE(MAX(numpedido), 0) + 1 FROM pedidosclientes FOR UPDATE")->fetchColumn();
        $pdo->prepare("INSERT INTO pedidosclientes (cliente, estadofac, numpedido, fechaentrega, estadoprod, observaciones, vendedorid) VALUES (?, 0, ?, CURDATE(), 0, ?, ?)")
            ->execute([$c['cliente_id'], $num, $obsCab, $vendedor]);
        $stL = $pdo->prepare("INSERT INTO lineapedidosclientes (numpedido, producto, cantidad, tela, color, columna, descripcion, observacion, lista, descuento, preciounit, impint, poriva)
                              VALUES (?, ?, ?, '', '', '', ?, '', ?, 0, ?, ?, NULL)");
        foreach ($lineas as $l) {
            $stL->execute([$num, $l['p']['codigo'], $l['cantidad'], mb_substr($l['p']['nombre'], 0, 200), $c['lista'], $l['p']['precioNeto'], $l['impint']]);
        }
        $pdo->prepare("INSERT INTO web_pedidos (numpedido, web_usuario_id, cliente_id, total, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([$num, $c['web_usuario_id'], $c['cliente_id'], $total, ipCliente()]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    avisarPedido($num, $c, $lineas, $total, $obs, $vendedor);
    return ['numero' => $num, 'total' => $total, 'mensaje' => $CFG['pedido_texto_confirmacion']];
}

function avisarPedido(int $num, array $c, array $lineas, float $total, string $obs, int $vendedorId): void
{
    global $pdo, $CFG;
    $fmt = fn($n) => '$ ' . number_format((float) $n, 2, ',', '.');
    $filas = '';
    foreach ($lineas as $l) {
        $filas .= '<tr><td style="padding:6px 8px;border-bottom:1px solid #e2e8f0">' . htmlspecialchars($l['p']['nombre'])
            . '<div style="color:#64748b;font-size:12px">Cód. ' . htmlspecialchars($l['p']['codigo']) . '</div></td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:center">' . $l['cantidad'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e2e8f0;text-align:right">' . $fmt($l['p']['precio'] * $l['cantidad']) . '</td></tr>';
    }
    $tabla = '<table style="width:100%;border-collapse:collapse;font-size:14px"><tr style="background:#f1f5f9"><th style="padding:6px 8px;text-align:left">Producto</th><th style="padding:6px 8px">Cant.</th><th style="padding:6px 8px;text-align:right">Subtotal</th></tr>'
        . $filas . '<tr><td colspan="2" style="padding:8px;text-align:right"><b>Total</b></td><td style="padding:8px;text-align:right"><b>' . $fmt($total) . '</b></td></tr></table>'
        . ($obs !== '' ? '<p><b>Observaciones:</b> ' . htmlspecialchars($obs) . '</p>' : '');
    $razon = htmlspecialchars($c['razon']);

    // A la empresa (y al vendedor del cliente)
    $destinos = array_filter(array_map('trim', preg_split('/[,;]+/', $CFG['pedido_mail_aviso'])));
    if ($CFG['pedido_avisar_vendedor'] === '1' && $vendedorId) {
        $st = $pdo->prepare("SELECT mail FROM vendedores WHERE id = ?");
        $st->execute([$vendedorId]);
        $mv = trim((string) $st->fetchColumn());
        if (filter_var($mv, FILTER_VALIDATE_EMAIL)) $destinos[] = $mv;
    }
    if ($destinos) {
        webEnviarMail($pdo, array_unique($destinos), "Nuevo pedido web #$num - {$c['razon']}",
            "<p>Entró el pedido <b>#$num</b> de <b>$razon</b> desde la web.</p>$tabla<p>Ya está cargado en el sistema (Pedidos de clientes), listo para preparar y facturar.</p>", $CFG);
    }

    // Al cliente
    if ($CFG['pedido_confirmar_cliente'] === '1') {
        $st = $pdo->prepare("SELECT email FROM web_usuarios WHERE id = ?");
        $st->execute([$c['web_usuario_id']]);
        $mc = (string) $st->fetchColumn();
        if (filter_var($mc, FILTER_VALIDATE_EMAIL)) {
            webEnviarMail($pdo, $mc, "Recibimos tu pedido #$num",
                "<p>Hola <b>$razon</b>,</p><p>" . nl2br(htmlspecialchars($CFG['pedido_texto_confirmacion'])) . "</p>$tabla"
                . webMailBoton(urlBaseSitio() . "/cuenta/pedidos/$num", 'Ver mi pedido', $CFG['color_primario']), $CFG);
        }
    }
}

// =================================================================== Pagos informados

const MEDIOS_PAGO = ['transferencia', 'deposito', 'efectivo', 'cheque', 'otro'];

function hAvisosPago(): array
{
    global $pdo;
    $c = requiereCliente();
    $st = $pdo->prepare("SELECT id, fecha, fecha_pago, importe, medio, referencia, observaciones, estado, (archivo IS NOT NULL) AS adjunto
                         FROM web_avisos_pago WHERE cliente_id = ? ORDER BY fecha DESC LIMIT 50");
    $st->execute([$c['cliente_id']]);
    return ['items' => array_map(fn($a) => array_merge($a, ['id' => (int) $a['id'], 'importe' => (float) $a['importe'], 'adjunto' => (bool) $a['adjunto']]), $st->fetchAll())];
}

function hInformarPago(): array
{
    global $pdo, $CFG, $SITIO;
    $c = requiereCliente();
    if ($CFG['avisos_pago_habilitados'] !== '1') throw new ApiError('No está habilitado informar pagos.', 403);

    $fecha = (string) ($_POST['fecha_pago'] ?? '');
    $importe = (float) str_replace(',', '.', (string) ($_POST['importe'] ?? '0'));
    $medio = (string) ($_POST['medio'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d') || $fecha < date('Y-m-d', strtotime('-1 year'))) throw new ApiError('Revisá la fecha del pago.');
    if ($importe <= 0 || $importe > 999999999) throw new ApiError('Revisá el importe.');
    if (!in_array($medio, MEDIOS_PAGO, true)) throw new ApiError('Elegí cómo pagaste.');

    $st = $pdo->prepare("SELECT COUNT(*) FROM web_avisos_pago WHERE cliente_id = ? AND fecha > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $st->execute([$c['cliente_id']]);
    if ((int) $st->fetchColumn() >= 20) throw new ApiError('Ya informaste muchos pagos hoy. Comunicate con nosotros.', 429);

    $archivo = null;
    if (!empty($_FILES['comprobante']) && $_FILES['comprobante']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['comprobante'];
        if ($f['error'] !== UPLOAD_ERR_OK) throw new ApiError('No se pudo subir el comprobante.');
        if ($f['size'] > 8 * 1024 * 1024) throw new ApiError('El comprobante supera los 8 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
        if (!$ext) throw new ApiError('El comprobante tiene que ser una foto (JPG/PNG) o un PDF.');
        $dir = webDirAvisosPago((string) $SITIO['cuit']);
        webAsegurarDir($dir);
        $archivo = 'aviso_' . $c['cliente_id'] . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . ".$ext";
        if (!move_uploaded_file($f['tmp_name'], "$dir/$archivo")) throw new ApiError('No se pudo guardar el comprobante.', 500);
    }

    $pdo->prepare("INSERT INTO web_avisos_pago (cliente_id, web_usuario_id, fecha_pago, importe, medio, referencia, observaciones, archivo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$c['cliente_id'], $c['web_usuario_id'], $fecha, round($importe, 2), $medio,
            mb_substr(trim((string) ($_POST['referencia'] ?? '')), 0, 100) ?: null, mb_substr(trim((string) ($_POST['observaciones'] ?? '')), 0, 500) ?: null, $archivo]);

    $destinos = $CFG['pedido_mail_aviso'] ?: $CFG['contacto_email'];
    if ($destinos) {
        webEnviarMail($pdo, $destinos, 'Pago informado por ' . $c['razon'],
            '<p><b>' . htmlspecialchars($c['razon']) . '</b> informó un pago de <b>$ ' . number_format($importe, 2, ',', '.') . '</b> (' . htmlspecialchars($medio) . ') del ' . date('d/m/Y', strtotime($fecha)) . '.</p>'
            . '<p>Verificalo y procesalo en el sistema: <b>Administrador &gt; Web de Clientes &gt; Pagos Informados</b>.</p>', $CFG);
    }
    return ['ok' => true];
}
