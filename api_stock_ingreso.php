<?php
/**
 * api_stock_ingreso.php - PROCESAMIENTO DE COMPRAS / INGRESOS
 * Lógica transaccional optimizada.
 */
require_once 'check_api_access.php';
require_once 'stock_deposito_helpers.php';
$data = json_decode(file_get_contents('php://input'), true);
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$fecha_hoy = date('Y-m-d');
$fecha_hora = date('Y-m-d H:i:s');

try {
    // Depósito de la cabecera del ingreso (back-office: combo precargado con el
    // depósito de trabajo del usuario, pero editable). Sin multidepósito, queda null
    // y moverStock() se comporta 100% como antes.
    $idDeposito = null;
    if (multidepositoHabilitado($pdo)) {
        $idDeposito = !empty($data['id_deposito']) ? (int) $data['id_deposito'] : depositoDeTrabajo($pdo, $usuario);
    }

    $pdo->beginTransaction();

    foreach ($data['items'] as $it) {
        // A. Stock anterior
        $stmtStk = $pdo->prepare("SELECT id, Stock FROM alb_productos WHERE codigo = ?");
        $stmtStk->execute([$it['codigo']]);
        $prodAnt = $stmtStk->fetch(PDO::FETCH_ASSOC);
        $stkAnt = $prodAnt['Stock'] ?? 0;

        // B. Movimiento Auditoría (legacy, sin depósito)
        $stmtMov = $pdo->prepare("INSERT INTO movimientosdestock (codigo, entradas, salidas, tipomov, esfiscal, fecha, usuario, numremito, rubro, origenmov, tipocomp)
                                 VALUES (?, ?, 0, 'INGRESO DE STOCK', ?, ?, ?, ?, ?, 'INGRESO', ?)");
        $stmtMov->execute([$it['codigo'], $it['cantidad'], $data['fiscal'], $fecha_hora, $usuario, $data['numero'], $it['rubro'], (int)$data['tipo_comp']]);
        $idMovLegacy = (int) $pdo->lastInsertId();

        // C. Cantidad de stock (vía helper, respeta depósito) y costos por separado
        moverStock($pdo, (int) $prodAnt['id'], $it['codigo'], $idDeposito, (float) $it['cantidad'], 'ENTRADA',
                   'INGRESO DE STOCK', 'INGRESO', (int) $data['tipo_comp'], (int) $data['numero'], $usuario, $idMovLegacy);

        $stmtUpd = $pdo->prepare("UPDATE alb_productos SET sinivacosto = ?, ivacosto = ?, ImpInt = ?, preciocosto = ? WHERE codigo = ?");
        $stmtUpd->execute([$it['neto'], $it['iva'], $it['imp_int'], $it['total_un'], $it['codigo']]);

        // D. Lineadeventa (Legacy reporting)
        $stmtLin = $pdo->prepare("INSERT INTO Lineadeventa (remito, Tipocomp, numcomp, cliente, fecha, prodcod, proddes, cant, siniva, iva, ii, total, rubro, alicuota, mpago1, mpago2, IIBB, usuario, notas, fiscal) 
                                 VALUES (?, ?, ?, 'INGRESO DE STOCK', ?, ?, ?, ?, 0, 0, 0, 0, ?, 0, '--', 'MOV INT', 0, ?, ?, ?)");
        $stmtLin->execute([$data['numero'], (int)$data['tipo_comp'], (int)$data['numero'], $fecha_hoy, $it['codigo'], $it['descripcion'], $it['cantidad'], $it['rubro'], $usuario, $it['nota'], $data['fiscal']]);

        // E. Histórico Ingresos
        $stkFinal = $stkAnt + $it['cantidad'];
        $stmtIng = $pdo->prepare("INSERT INTO ingresosdestock (tipoingreso, tipocomp, numcomp, idproveedor, proveedor, codigo, descripcion, stkant, ingreso, stkfinal, nota, fecha, user, netocosto, ivacosto, impintcosto, preciocosto, stock) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtIng->execute([$data['fiscal'], $data['tipo_comp'], $data['numero'], $data['id_proveedor'], $data['nombre_proveedor'], $it['codigo'], $it['descripcion'], $stkAnt, $it['cantidad'], $stkFinal, $it['nota'], $fecha_hoy, $usuario, $it['neto'], $it['iva'], $it['imp_int'], $it['total_un'], $it['cantidad']]);
    }

    // Si el ingreso se cargó desde una Orden de Compra pendiente, se la marca
    // como finalizada (queda registrado que ya se recibió la mercadería).
    if (!empty($data['id_oc'])) {
        $pdo->prepare("UPDATE pedidos_proveedores_cab SET estado = 'FINALIZADO' WHERE id = ? AND estado = 'PENDIENTE'")
            ->execute([(int) $data['id_oc']]);
    }

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}