<?php
/**
 * api_monedas.php - BACKEND DE GESTIÓN DE MONEDAS Y PRECIOS
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $query = $_GET['query'] ?? '';
            // Subconsulta EXACTA de VB.NET para obtener la última cotización
            $sql = "SELECT m.id, m.nombre, 
                    (SELECT cambio FROM tiposdecambio WHERE idmoneda = m.id ORDER BY id DESC LIMIT 1) as cotizacion,
                    (SELECT fecha FROM tiposdecambio WHERE idmoneda = m.id ORDER BY id DESC LIMIT 1) as fecha_act
                    FROM monedas m 
                    WHERE m.nombre LIKE ? 
                    ORDER BY m.nombre ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$query%"]);
            echo json_encode($stmt->fetchAll());
            break;

        case 'get':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM monedas WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
            break;

        case 'get_cotizacion':
            $id = $_GET['id'] ?? 0;
            // Obtener datos moneda + ultima cotización
            $sql = "SELECT m.id, m.nombre, 
                    COALESCE((SELECT cambio FROM tiposdecambio WHERE idmoneda = m.id ORDER BY id DESC LIMIT 1), 0) as cotizacion
                    FROM monedas m WHERE m.id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
            break;

        case 'save':
            $input = json_decode(file_get_contents('php://input'), true);
            $nombre = mb_strtoupper(trim($input['nombre']));
            
            if($input['id'] > 0) {
                $stmt = $pdo->prepare("UPDATE monedas SET nombre = ? WHERE id = ?");
                $res = $stmt->execute([$nombre, $input['id']]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO monedas (nombre) VALUES (?)");
                $res = $stmt->execute([$nombre]);
            }
            echo json_encode(['success' => $res]);
            break;

        case 'update_rate':
            $input = json_decode(file_get_contents('php://input'), true);
            $idMoneda = (int)$input['id'];
            $nuevaCotizacion = (float)$input['cotizacion'];

            if ($idMoneda <= 0 || $nuevaCotizacion <= 0) throw new Exception("Datos inválidos.");

            // REGLA CRÍTICA: La Moneda Base (ID 1) es inmutable en cotización.
            if ($idMoneda === 1) {
                throw new Exception("Operación no permitida: La Moneda Base (ID 1) es la referencia del sistema y su cotización siempre es 1.00.");
            }

            $pdo->beginTransaction();

            // 1. Obtener cotización anterior
            $stmtLast = $pdo->prepare("SELECT cambio FROM tiposdecambio WHERE idmoneda = ? ORDER BY id DESC LIMIT 1");
            $stmtLast->execute([$idMoneda]);
            $cotizacionAnterior = (float)$stmtLast->fetchColumn();

            // 2. Insertar nueva cotización en historial
            $stmtIns = $pdo->prepare("INSERT INTO tiposdecambio (idmoneda, cambio, fecha) VALUES (?, ?, CURDATE())");
            $stmtIns->execute([$idMoneda, $nuevaCotizacion]);

            $mensaje = "Cotización actualizada.";

            // 3. CALCULAR COEFICIENTE Y ACTUALIZAR PRECIOS (Si existe cotización previa)
            if ($cotizacionAnterior > 0) {
                $coeficiente = $nuevaCotizacion / $cotizacionAnterior;

                // ACTUALIZACIÓN MASIVA: Costos (Neto, IVA, Bruto), Imp. Internos, 8 Listas de Precios y IIBB
                $sqlUpdate = "UPDATE alb_productos SET 
                              sinivacosto = sinivacosto * :coef,
                              ivacosto = ivacosto * :coef,
                              preciocosto = preciocosto * :coef,
                              impint = impint * :coef,
                              siniva = siniva * :coef, iva1 = iva1 * :coef, precio = precio * :coef,
                              siniva2 = siniva2 * :coef, iva2 = iva2 * :coef, precio2 = precio2 * :coef,
                              siniva3 = siniva3 * :coef, iva3 = iva3 * :coef, precio3 = precio3 * :coef,
                              siniva4 = siniva4 * :coef, iva4 = iva4 * :coef, precio4 = precio4 * :coef,
                              siniva5 = siniva5 * :coef, iva5 = iva5 * :coef, precio5 = precio5 * :coef,
                              siniva6 = siniva6 * :coef, iva6 = iva6 * :coef, precio6 = precio6 * :coef,
                              siniva7 = siniva7 * :coef, iva7 = iva7 * :coef, precio7 = precio7 * :coef,
                              siniva8 = siniva8 * :coef, iva8 = iva8 * :coef, precio8 = precio8 * :coef,
                              IIBB1 = IIBB1 * :coef, IIBB2 = IIBB2 * :coef, IIBB3 = IIBB3 * :coef, IIBB4 = IIBB4 * :coef,
                              IIBB5 = IIBB5 * :coef, IIBB6 = IIBB6 * :coef, IIBB7 = IIBB7 * :coef, IIBB8 = IIBB8 * :coef
                              WHERE idmoneda = :id";
                
                $stmtUpd = $pdo->prepare($sqlUpdate);
                $stmtUpd->execute([':coef' => $coeficiente, ':id' => $idMoneda]);
                
                $filas = $stmtUpd->rowCount();
                $mensaje .= " Se actualizaron precios de $filas productos (Coef: " . round($coeficiente, 4) . ").";
            } else {
                $mensaje .= " (Primera cotización registrada: no se recalcularon precios).";
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => $mensaje]);
            break;
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}