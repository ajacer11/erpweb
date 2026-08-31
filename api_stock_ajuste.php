<?php
/**
 * api_stock_ajuste.php - ENDPOINT PARA AJUSTE INDIVIDUAL
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once 'stock_deposito_helpers.php';

$action = $_GET['action'] ?? '';

// Mensajes amigables por tipo de operación
function respError($mensaje_amigable, $detalle_tecnico, $http_code = 200) {
    echo json_encode([
        'success'          => false,
        'error'            => $mensaje_amigable,   // lo que ve el usuario
        'error_tecnico'    => $detalle_tecnico      // visible en Network, nunca en el Swal
    ]);
}

// grabarLineaAsiento() y resolverEjercicioPeriodo() ahora viven en
// contabilidad_cuentas_helpers.php (ya incluido arriba) — antes estaban duplicadas
// idénticas en varios api_*.php.

switch ($action) {

    case 'list_all':
        try {
            $stmt = $pdo->query("SELECT codigo, descripcion FROM alb_productos WHERE habilitado = 1 ORDER BY descripcion ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (Exception $e) {
            respError('No se pudo cargar la lista de productos.', $e->getMessage());
        }
        break;

    case 'get_prod':
        $code = $_GET['code'] ?? '';

        $sql = "SELECT 
                    p.id,
                    p.codigo, 
                    p.descripcion, 
                    p.Stock AS stock, 
                    p.habilitado, 
                    p.sinivacosto, 
                    p.ivacosto, 
                    p.impint, 
                    p.preciocosto, 
                    p.rubro,
                    p.cuentamercaderia
                FROM alb_productos p 
                WHERE p.codigo = ?";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$code]);
            $prod = $stmt->fetch();

            if ($prod) {
                echo json_encode(['success' => true, 'data' => $prod]);
            } else {
                respError('El producto no existe.', "Código buscado: {$code}");
            }
        } catch (Exception $e) {
            respError('No se pudo obtener los datos del producto.', $e->getMessage());
        }
        break;

    case 'set_status':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['codigo'])) exit;

        try {
            $stmt = $pdo->prepare("UPDATE alb_productos SET habilitado = ? WHERE codigo = ?");
            $stmt->execute([$data['estado'], $data['codigo']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            respError('No se pudo cambiar el estado del producto.', $e->getMessage());
        }
        break;

    case 'save':
        $data = json_decode(file_get_contents('php://input'), true);
        $usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
        $fecha   = date('Y-m-d H:i:s');

        try {
            $ejerPer = resolverEjercicioPeriodo($pdo, date('Y-m-d'));

            $idDeposito = null;
            if (multidepositoHabilitado($pdo)) {
                $idDeposito = !empty($data['id_deposito']) ? (int) $data['id_deposito'] : depositoDeTrabajo($pdo, $usuario);
            }

            $pdo->beginTransaction();

            // 1. Datos frescos del producto
            $stmt = $pdo->prepare("SELECT id, Stock, rubro, sinivacosto, impint, cuentamercaderia FROM alb_productos WHERE codigo = ?");
            $stmt->execute([$data['codigo']]);
            $pOrig = $stmt->fetch();

            if (!$pOrig) {
                $pdo->rollBack();
                respError('No se encontró el producto al intentar guardar.', "Código: {$data['codigo']}");
                break;
            }

            // 2. Cabecera de ajuste
            $stmt = $pdo->prepare("INSERT INTO ajustesdeinventario (fechacarga, usuariocarga, estado, fechaajuste, usuarioajuste, observacion) VALUES (?, ?, 'FINALIZADO', ?, ?, ?)");
            $stmt->execute([$fecha, $usuario, $fecha, $usuario, $data['observacion']]);
            $idajuste = $pdo->lastInsertId();

            // 3. Movimiento de stock (auditoría legacy, sin depósito)
            $stmt = $pdo->prepare("INSERT INTO movimientosdestock (codigo, entradas, salidas, tipomov, esfiscal, fecha, usuario, rubro, origenmov)
                                   VALUES (?, ?, ?, 'AJUSTE DE INVENTARIO', ?, ?, ?, ?, 'AJUSTE')");
            $stmt->execute([$data['codigo'], $data['ingreso'], $data['egreso'], $data['fiscal'], $fecha, $usuario, $pOrig['rubro']]);
            $idMovLegacy = (int) $pdo->lastInsertId();

            // 4. Actualizar stock (vía helper: respeta depósito, re-habilita el producto aparte)
            if ($data['ingreso'] > 0) {
                moverStock($pdo, (int) $pOrig['id'], $data['codigo'], $idDeposito, (float) $data['ingreso'], 'ENTRADA',
                           'AJUSTE DE INVENTARIO', 'AJUSTE', 0, $idajuste, $usuario, $idMovLegacy);
            }
            if ($data['egreso'] > 0) {
                moverStock($pdo, (int) $pOrig['id'], $data['codigo'], $idDeposito, (float) $data['egreso'], 'SALIDA',
                           'AJUSTE DE INVENTARIO', 'AJUSTE', 0, $idajuste, $usuario, $idMovLegacy);
            }
            $pdo->prepare("UPDATE alb_productos SET habilitado = 1 WHERE codigo = ?")->execute([$data['codigo']]);

            // 5. Línea de ajuste (histórico detallado)
            $stockFinal = $pOrig['Stock'] + $data['ingreso'] - $data['egreso'];
            $stmt = $pdo->prepare("INSERT INTO lineajustes (idajuste, codigo, stockportabla, ingresan, salen, stockfinal, costounitario, rubro)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$idajuste, $data['codigo'], $pOrig['Stock'], $data['ingreso'], $data['egreso'], $stockFinal, $pOrig['sinivacosto'], $pOrig['rubro']]);

            // 6. Asiento contable (Ajuste de Stock / Ajuste de Costo)
            $cp = $pdo->query("SELECT ajuste, ajustemercaderia FROM cuentaspredeterminadas WHERE id = 1")->fetch();
            $valorIngreso = round((float) $data['ingreso'] * (float) $pOrig['sinivacosto'], 2);
            $valorEgreso  = round((float) $data['egreso'] * (float) $pOrig['sinivacosto'], 2);

            if ($valorIngreso > 0 || $valorEgreso > 0) {
                $stmtNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
                $stmtNum->execute([$ejerPer['ejercicio']]);
                $numAsiento = ((int) $stmtNum->fetchColumn()) + 1;
                $detalleAsiento = "Ajuste de Inventario #$idajuste - {$data['codigo']}";

                if ($valorIngreso > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], $valorIngreso, 0, $idajuste, "ajustesdeinventario", $data['fiscal'], $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], 0, $valorIngreso, $idajuste, "ajustesdeinventario", $data['fiscal'], $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
                if ($valorEgreso > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], $valorEgreso, 0, $idajuste, "ajustesdeinventario", $data['fiscal'], $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], 0, $valorEgreso, $idajuste, "ajustesdeinventario", $data['fiscal'], $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => "Producto {$data['codigo']} ajustado correctamente."]);

        } catch (Exception $e) {
            $pdo->rollBack();
            respError('No se pudo guardar el ajuste. La operación fue cancelada.', $e->getMessage());
        }
        break;
}