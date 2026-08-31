<?php
/**
 * api_caja_central.php - LÓGICA DE DATOS PARA CAJA CENTRAL
 * Sincronizado con la estructura real de cccuentastesoreria y mediosdepago.
 */
require_once 'check_api_access.php';

$desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');

try {
    // 1. OBTENER LA CUENTA CONFIGURADA COMO CAJA CENTRAL
    // Buscamos por tipodecuenta = 'Caja Central' según la estructura de mediosdepago
    $stmtCta = $pdo->prepare("SELECT id, medio FROM mediosdepago WHERE tipodecuenta = 'Caja Central' AND habilitado = 1 LIMIT 1");
    $stmtCta->execute();
    $cta = $stmtCta->fetch();

    if (!$cta) {
        echo json_encode(['status' => 'error', 'error' => 'No se encontró la cuenta de Caja Central activa.']);
        exit;
    }

    $idCuenta = $cta['id'];

    // 2. CALCULAR SALDO ANTERIOR
    $stmtAnt = $pdo->prepare("
        SELECT (SUM(debe) - SUM(haber)) as saldo 
        FROM cccuentastesoreria 
        WHERE idcuenta = ? AND fecha < ?
    ");
    $stmtAnt->execute([$idCuenta, $desde]);
    $saldoAnterior = (float)($stmtAnt->fetchColumn() ?: 0);

    // 3. OBTENER MOVIMIENTOS DEL PERÍODO
    // Mapeamos 'descripcion' a 'observaciones' para el JSON
    $stmtMov = $pdo->prepare("
        SELECT id, fecha, debe, haber, descripcion as observaciones, compasoc, usuario 
        FROM cccuentastesoreria 
        WHERE idcuenta = ? AND fecha BETWEEN ? AND ? 
        ORDER BY fecha ASC, id ASC
    ");
    $stmtMov->execute([$idCuenta, $desde, $hasta]);
    $movimientos = $stmtMov->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => [
            'nombre_cuenta' => $cta['medio'],
            'saldo_anterior' => $saldoAnterior,
            'movimientos' => $movimientos
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}