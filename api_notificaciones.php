<?php
/**
 * api_notificaciones.php - Avisos de lo que entra desde la web de clientes (campanita del ERP).
 *
 * Devuelve lo último que llegó: pedidos online (web_pedidos), solicitudes de alta de clientes
 * (web_solicitudes_registro) y pagos informados (web_avisos_pago). Cada usuario ve solo lo de
 * las pantallas a las que tiene acceso (pedidos_admin.php, web_accesos.php, web_avisos_pago.php).
 * Qué es "nuevo" para cada usuario lo decide el navegador (último id visto por tipo), así que
 * esto no guarda nada ni necesita tablas propias.
 */
require_once 'check_api_access.php';
require_once 'web_helpers.php';

$vacio = ['success' => true, 'activo' => false, 'items' => [], 'pendientes' => ['solicitudes' => 0, 'avisos' => 0]];

try {
    if (!webAddonHabilitado($pdo_master, (string) $_SESSION['tenant_cuit'])) {
        echo json_encode($vacio);
        exit;
    }

    // Mismo criterio de permisos que el menú: admin ve todo; el resto, según sus URLs habilitadas.
    $esAdmin = (int) ($_SESSION['es_admin'] ?? 0) === 1;
    $urls = [];
    if (!$esAdmin) {
        $st = $pdo->prepare("SELECT permisos FROM accesosusuarios WHERE usuarioid = ? AND vigente = 1 LIMIT 1");
        $st->execute([$_SESSION['usuario_id']]);
        $perm = json_decode((string) $st->fetchColumn(), true);
        $urls = array_map('basename', $perm['allowed_urls'] ?? []);
    }
    $puede = fn(string $url) => $esAdmin || in_array($url, $urls, true);

    $items = [];
    $pendientes = ['solicitudes' => 0, 'avisos' => 0];

    if ($puede('pedidos_admin.php')) {
        $st = $pdo->query("SELECT w.numpedido, w.fecha, w.total, c.razon
                           FROM web_pedidos w LEFT JOIN alb_clientes c ON c.id = w.cliente_id
                           WHERE w.fecha >= NOW() - INTERVAL 15 DAY
                           ORDER BY w.numpedido DESC LIMIT 15");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'tipo' => 'pedido', 'id' => (int) $r['numpedido'], 'fecha' => $r['fecha'],
                'titulo' => 'Pedido web N° ' . $r['numpedido'],
                'detalle' => trim(($r['razon'] ?: 'Cliente') . ' · $ ' . number_format((float) $r['total'], 2, ',', '.')),
                'url' => 'pedidos_admin.php',
            ];
        }
    }

    if ($puede('web_accesos.php')) {
        $st = $pdo->query("SELECT id, fecha, razon, localidad FROM web_solicitudes_registro
                           WHERE estado = 'pendiente' ORDER BY id DESC LIMIT 15");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'tipo' => 'solicitud', 'id' => (int) $r['id'], 'fecha' => $r['fecha'],
                'titulo' => 'Solicitud de alta de cliente',
                'detalle' => trim($r['razon'] . ($r['localidad'] ? ' · ' . $r['localidad'] : '')),
                'url' => 'web_accesos.php',
            ];
        }
        $pendientes['solicitudes'] = (int) $pdo->query("SELECT COUNT(*) FROM web_solicitudes_registro WHERE estado = 'pendiente'")->fetchColumn();
    }

    if ($puede('web_avisos_pago.php')) {
        $st = $pdo->query("SELECT a.id, a.fecha, a.importe, c.razon FROM web_avisos_pago a
                           LEFT JOIN alb_clientes c ON c.id = a.cliente_id
                           WHERE a.estado = 'pendiente' ORDER BY a.id DESC LIMIT 15");
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $items[] = [
                'tipo' => 'aviso', 'id' => (int) $r['id'], 'fecha' => $r['fecha'],
                'titulo' => 'Pago informado',
                'detalle' => trim(($r['razon'] ?: 'Cliente') . ' · $ ' . number_format((float) $r['importe'], 2, ',', '.')),
                'url' => 'web_avisos_pago.php',
            ];
        }
        $pendientes['avisos'] = (int) $pdo->query("SELECT COUNT(*) FROM web_avisos_pago WHERE estado = 'pendiente'")->fetchColumn();
    }

    usort($items, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

    echo json_encode([
        'success' => true,
        'activo' => $esAdmin || $puede('pedidos_admin.php') || $puede('web_accesos.php') || $puede('web_avisos_pago.php'),
        'items' => array_slice($items, 0, 25),
        'pendientes' => $pendientes,
    ]);
} catch (Throwable $e) {
    error_log('api_notificaciones: ' . $e->getMessage());
    echo json_encode($vacio);
}
