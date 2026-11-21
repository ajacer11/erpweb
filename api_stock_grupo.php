<?php
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

// grabarLineaAsiento() y resolverEjercicioPeriodo() ahora viven en
// contabilidad_cuentas_helpers.php (ya incluido arriba) — antes estaban duplicadas
// idénticas en varios api_*.php.

try {
    switch ($action) {
        case 'list':
            $desde = $_GET['desde'] . " 00:00:00";
            $hasta = $_GET['hasta'] . " 23:59:59";
            $sql = "SELECT id, fechacarga, estado AS Estado, usuariocarga, nombregrupo, fechaajuste 
                    FROM ajustesdeinventario 
                    WHERE fechacarga BETWEEN ? AND ? ORDER BY id DESC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$desde, $hasta]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'get':
            $id = $_GET['id'];
            $cab = $pdo->prepare("SELECT * FROM ajustesdeinventario WHERE id = ?");
            $cab->execute([$id]);
            $cabecera = $cab->fetch();
            if (!$cabecera) throw new Exception("Ajuste no encontrado");
            
            $det = $pdo->prepare("SELECT l.*, p.descripcion 
                                 FROM lineajustes l 
                                 LEFT JOIN alb_productos p ON l.codigo = p.codigo 
                                 WHERE l.idajuste = ?");
            $det->execute([$id]);
            echo json_encode(['success' => true, 'cabecera' => $cabecera, 'detalle' => $det->fetchAll()]);
            break;

        case 'get_filtered_prods':
            $rubro = $_GET['rubro'] ?? '';
            $marca = $_GET['marca'] ?? '';
            $familia = $_GET['familia'] ?? '';
            $proveedor = $_GET['proveedor'] ?? '';

            $sql = "SELECT codigo, descripcion, Stock AS stock, rubro, sinivacosto, impint 
                    FROM alb_productos WHERE habilitado = 1";
            $params = [];

            if ($rubro) { $sql .= " AND rubro = ?"; $params[] = $rubro; }
            if ($marca) { $sql .= " AND Marca = ?"; $params[] = $marca; }
            if ($familia) { $sql .= " AND familia = ?"; $params[] = $familia; }
            if ($proveedor) { $sql .= " AND proveedorID = ?"; $params[] = $proveedor; }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $prods = $stmt->fetchAll();

            foreach ($prods as &$p) {
                $st = $pdo->prepare("SELECT COALESCE(SUM(entradas),0) - COALESCE(SUM(salidas),0) + COALESCE((SELECT SUM(cantidad) FROM saldosiniciales WHERE codigo = ?), 0) FROM movimientosdestock WHERE codigo = ?");
                $st->execute([$p['codigo'], $p['codigo']]);
                $p['stock_gestion'] = $st->fetchColumn() ?: 0;
            }
            echo json_encode(['success' => true, 'data' => $prods]);
            break;

        case 'init_group':
            $data = json_decode(file_get_contents('php://input'), true);
            $nombre = strtoupper($data['nombre'] ?? 'AJUSTE GRUPAL');
            $items = $data['items'] ?? [];
            if (empty($items)) throw new Exception("No hay productos seleccionados.");

            $pdo->beginTransaction();
            $fecha = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("INSERT INTO ajustesdeinventario (fechacarga, usuariocarga, estado, nombregrupo) VALUES (?, ?, 'INICIADO', ?)");
            $stmt->execute([$fecha, $usuario, $nombre]);
            $id = $pdo->lastInsertId();

            $stmtLine = $pdo->prepare("INSERT INTO lineajustes (idajuste, codigo, stockportabla, stockporingresos, ingresan, costounitario, rubro) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmtBlock = $pdo->prepare("UPDATE alb_productos SET habilitado = 0 WHERE codigo = ?");

            foreach ($items as $p) {
                $stmtLine->execute([$id, $p['codigo'], $p['stock'], $p['stock_gestion'], $p['stock'], $p['sinivacosto'], $p['rubro']]);
                $stmtBlock->execute([$p['codigo']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $id]);
            break;

        case 'save_conteo':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE lineajustes SET ingresan = ? WHERE idajuste = ? AND codigo = ?");
            foreach ($data['items'] as $it) {
                $stmt->execute([$it['conteo'], $data['id'], $it['codigo']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Conteo guardado']);
            break;

        case 'finalize':
            $data = json_decode(file_get_contents('php://input'), true);
            $fecha = date('Y-m-d H:i:s');
            $ejerPer = resolverEjercicioPeriodo($pdo, date('Y-m-d'));

            $idDeposito = null;
            if (multidepositoHabilitado($pdo)) {
                $idDeposito = !empty($data['id_deposito']) ? (int) $data['id_deposito'] : depositoDeTrabajo($pdo, $usuario);
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ajustesdeinventario SET estado = 'FINALIZADO', fechaajuste = ?, usuarioajuste = ?, observacion = ? WHERE id = ?")
                ->execute([$fecha, $usuario, $data['observacion'], $data['id']]);

            $stmtDet = $pdo->prepare("SELECT * FROM lineajustes WHERE idajuste = ?");
            $stmtDet->execute([$data['id']]);
            $items = $stmtDet->fetchAll();

            $stmtIdProd = $pdo->prepare("SELECT id FROM alb_productos WHERE codigo = ?");

            $totalIngresoValor = 0;
            $totalEgresoValor = 0;

            foreach ($items as $it) {
                $dif = $it['ingresan'] - $it['stockportabla'];
                $pdo->prepare("UPDATE alb_productos SET habilitado = 1 WHERE codigo = ?")->execute([$it['codigo']]);
                if ($dif != 0) {
                    $ent = $dif > 0 ? $dif : 0;
                    $sal = $dif < 0 ? abs($dif) : 0;
                    $pdo->prepare("INSERT INTO movimientosdestock (codigo, entradas, salidas, tipomov, esfiscal, fecha, usuario, rubro, origenmov, tipocomp, numremito) VALUES (?, ?, ?, 'AJUSTE DE INVENTARIO', 1, ?, ?, ?, 'AJUSTE', 0, 0)")->execute([$it['codigo'], $ent, $sal, $fecha, $usuario, $it['rubro']]);
                    $idMovLegacy = (int) $pdo->lastInsertId();

                    $stmtIdProd->execute([$it['codigo']]);
                    $idProd = (int) $stmtIdProd->fetchColumn();
                    ajustarStockADestino($pdo, $idProd, $it['codigo'], $idDeposito, (float) $it['stockportabla'], (float) $it['ingresan'],
                                         'AJUSTE DE INVENTARIO', 'AJUSTE', 0, (int) $data['id'], $usuario, $idMovLegacy);

                    if ($ent > 0) {
                        $pdo->prepare("INSERT INTO ingresosdestock (tipoingreso, tipocomp, idproveedor, proveedor, codigo, stkant, ingreso, stkfinal, fecha, user, netocosto, fechacarga, stock) VALUES (0, 1000, 1, 'Deposito', ?, ?, ?, ?, ?, ?, ?, ?, ?)")->execute([$it['codigo'], $it['stockportabla'], $ent, $it['ingresan'], date('Y-m-d'), $usuario, $it['costounitario'], $fecha, $ent]);
                    }
                    $totalIngresoValor += round($ent * (float) $it['costounitario'], 2);
                    $totalEgresoValor += round($sal * (float) $it['costounitario'], 2);
                }
            }

            // Asiento contable (Ajuste de Stock / Ajuste de Costo)
            if ($totalIngresoValor > 0 || $totalEgresoValor > 0) {
                $cp = $pdo->query("SELECT ajuste, ajustemercaderia FROM cuentaspredeterminadas WHERE id = 1")->fetch();
                $stmtNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
                $stmtNum->execute([$ejerPer['ejercicio']]);
                $numAsiento = ((int) $stmtNum->fetchColumn()) + 1;
                $detalleAsiento = "Ajuste de Inventario #{$data['id']}";

                if ($totalIngresoValor > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], $totalIngresoValor, 0, $data['id'], "ajustesdeinventario", 1, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], 0, $totalIngresoValor, $data['id'], "ajustesdeinventario", 1, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
                if ($totalEgresoValor > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], $totalEgresoValor, 0, $data['id'], "ajustesdeinventario", 1, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], 0, $totalEgresoValor, $data['id'], "ajustesdeinventario", 1, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Ajuste finalizado']);
            break;

        case 'cancel':
            $data = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ajustesdeinventario SET estado = 'CANCELADO', fechacancelado = ?, usuariocancelado = ? WHERE id = ?")->execute([date('Y-m-d H:i:s'), $usuario, $data['id']]);
            $pdo->prepare("UPDATE alb_productos SET habilitado = 1 WHERE codigo IN (SELECT codigo FROM lineajustes WHERE idajuste = ?)")->execute([$data['id']]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Ajuste cancelado']);
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}