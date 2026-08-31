<?php
/**
 * api_reporte_cajas_usuariosporpuesto.php - LÓGICA DE DATOS PARA HISTORIAL DE PUESTOS
 * ACTUALIZADO: Calcula saldo en vivo desde cccuentastesoreria para cajas abiertas.
 * ACTUALIZADO: Permite filtrar por un puesto en particular o todos.
 */
require_once 'check_api_access.php';

$desde = $_GET['fecha_desde'] ?? date('Y-m-d');
$hasta = $_GET['fecha_hasta'] ?? date('Y-m-d');
$idPuesto = $_GET['id_puesto'] ?? ''; // Nuevo parámetro para el filtro

try {
    // Consulta principal: Cruza controlcajas con cajas
    // REGLA: Si la caja está abierta (cerradael IS NULL), calculamos el saldo REAL
    // sumando (debe - haber) de la tabla cccuentastesoreria de la cuenta asociada.
    $sql = "
        SELECT 
            ctrl.id,
            c.descripcion as puesto,
            ctrl.usuario,
            ctrl.abiertael,
            ctrl.cerradael,
            ctrl.saldoinicial,
            IF(ctrl.cerradael IS NULL, 
                (SELECT COALESCE(SUM(debe) - SUM(haber), 0) 
                 FROM cccuentastesoreria ccc 
                 JOIN mediosdepago m ON ccc.idcuenta = m.id 
                 WHERE m.puestoasociado = ctrl.idcaja), 
                ctrl.saldofinal
            ) as saldofinal,
            IF(ctrl.cerradael IS NULL, 'ABIERTA', 'CERRADA') as condicion
        FROM controlcajas ctrl
        INNER JOIN cajas c ON ctrl.idcaja = c.id
        WHERE DATE(ctrl.abiertael) BETWEEN ? AND ?
    ";
    
    // Arreglo base de parámetros para el bind
    $params = [$desde, $hasta];

    // Si se envió un id de puesto específico, lo agregamos como filtro
    if ($idPuesto !== '') {
        $sql .= " AND ctrl.idcaja = ?";
        $params[] = $idPuesto;
    }

    // Ordenamiento por defecto
    $sql .= " ORDER BY ctrl.abiertael DESC, ctrl.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sesiones = $stmt->fetchAll();

    echo json_encode([
        'status' => 'success',
        'data' => $sesiones
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => 'Error de base de datos: ' . $e->getMessage()]);
}