<?php
/**
 * api_libro_iva.php - Libro IVA Ventas / Compras, posición de IVA y TXT del Libro IVA Digital.
 * El cálculo vive en libro_iva_helpers.php.
 */
require_once 'check_api_access.php';
require_once 'libro_iva_helpers.php';

$action = $_GET['action'] ?? '';

function livaFechas(array $d): array
{
    $desde = (string) ($d['desde'] ?? '');
    $hasta = (string) ($d['hasta'] ?? '');
    $ok = static fn($f) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $f);
    if (!$ok($desde) || !$ok($hasta)) throw new Exception('Indique el período (desde / hasta).');
    if ($desde > $hasta) throw new Exception('La fecha desde es posterior a la fecha hasta.');
    return [$desde, $hasta];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido.');
    $d = json_decode(file_get_contents('php://input'), true) ?: [];
    [$desde, $hasta] = livaFechas($d);
    $campoFecha = ($d['campo_fecha'] ?? '') === 'fecha' ? 'fecha' : 'fechaimputacion';
    $libro = ($d['libro'] ?? '') === 'compras' ? 'compras' : 'ventas';

    switch ($action) {
        case 'consultar':
            $filas = $libro === 'ventas' ? libroIvaVentas($pdo, $desde, $hasta) : libroIvaCompras($pdo, $desde, $hasta, $campoFecha);
            echo json_encode(['success' => true, 'data' => $filas, 'totales' => libroIvaTotales($filas)]);
            break;

        case 'resumen':
            $v = libroIvaTotales(libroIvaVentas($pdo, $desde, $hasta));
            $c = libroIvaTotales(libroIvaCompras($pdo, $desde, $hasta, $campoFecha));
            $debito = livaR2(($v['iva21'] ?? 0) + ($v['iva10'] ?? 0));
            $credito = livaR2($c['credito_fiscal'] ?? 0);
            $percepciones = livaR2($c['perc_iva'] ?? 0);
            echo json_encode(['success' => true, 'data' => [
                'debito' => ['iva21' => $v['iva21'] ?? 0, 'iva10' => $v['iva10'] ?? 0, 'total' => $debito,
                             'neto_gravado' => livaR2(($v['neto21'] ?? 0) + ($v['neto10'] ?? 0)), 'neto0' => $v['neto0'] ?? 0, 'ventas_total' => $v['total'] ?? 0],
                'credito' => ['iva21' => $c['iva21'] ?? 0, 'iva10' => $c['iva10'] ?? 0, 'iva27' => $c['iva27'] ?? 0, 'total' => $credito,
                              'compras_total' => $c['total'] ?? 0],
                'percepciones_iva_sufridas' => $percepciones,
                'saldo_tecnico' => livaR2($debito - $credito),
                'saldo_con_percepciones' => livaR2($debito - $credito - $percepciones),
            ]]);
            break;

        case 'txt':
            $tipo = ($d['tipo'] ?? '') === 'alicuotas' ? 'alicuotas' : 'cbte';
            if ($libro === 'ventas') {
                $filas = libroIvaVentas($pdo, $desde, $hasta);
                $txt = $tipo === 'cbte' ? txtVentasComprobantes($filas) : txtVentasAlicuotas($filas);
            } else {
                $filas = libroIvaCompras($pdo, $desde, $hasta, $campoFecha);
                $txt = $tipo === 'cbte' ? txtComprasComprobantes($filas) : txtComprasAlicuotas($filas);
            }
            $periodo = substr(str_replace('-', '', $desde), 0, 6);
            $nombre = 'LIBRO_IVA_DIGITAL_' . strtoupper($libro) . '_' . ($tipo === 'cbte' ? 'CBTE' : 'ALICUOTAS') . "_$periodo.txt";
            echo json_encode(['success' => true, 'filename' => $nombre, 'file_content' => base64_encode($txt),
                              'registros' => substr_count($txt, "\r\n")]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
