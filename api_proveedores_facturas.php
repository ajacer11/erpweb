<?php
/**
 * api_proveedores_facturas.php - BACKEND DE CARGA DE FACTURAS DE COMPRA
 * v2.0 (Validación Contable Estricta Integrada)
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

// grabarLineaAsiento() ahora vive en contabilidad_cuentas_helpers.php (ya incluido arriba).

try {
    switch ($action) {
        case 'get_pendientes_imputar':
            $idprov = $_GET['proveedor'];
            $fiscal = $_GET['fiscal'] == 'true' ? 1 : 0;
            // Busca facturas pendientes para imputar una Nota de Crédito (Estado 1)
            $sql = "SELECT id, 
                    CONCAT(LPAD(PV, 4, '0'), '-', LPAD(numfact, 8, '0')) AS num_factura,
                    total,
                    total - (SELECT IFNULL(SUM(importepagado), 0) FROM pagosfacturasproveedores WHERE idfactura = facturasdecompra.id) AS pendiente,
                    DATE_FORMAT(fecha, '%d-%m-%Y') as fecha_f
                    FROM facturasdecompra
                    WHERE proveedorid = ? AND tipocomp NOT IN (3, 8, 13, 101) AND fiscal = ? AND estado = 1
                    ORDER BY fecha ASC";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$idprov, $fiscal]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);

            $fechaImput = $data['fechaimputacion']; // Fecha contable

            // VALIDACIÓN DE FECHA FUTURA
            if ($fechaImput > date('Y-m-d')) {
                throw new Exception("No se permiten movimientos con fechas futuras.");
            }

            $pdo->beginTransaction();

            // ---------------------------------------------------------
            // REGLA CONTABLE CRÍTICA (Validación Ejercicio y Período)
            // ---------------------------------------------------------

            // A. Buscar Ejercicio Abierto
            $stmtEjer = $pdo->prepare("SELECT id FROM ejercicios WHERE abierto = 1 AND (apertura <= ? AND cierre >= ?) LIMIT 1");
            $stmtEjer->execute([$fechaImput, $fechaImput]);
            $ejercicio_id = (int) $stmtEjer->fetchColumn();

            if (!$ejercicio_id) {
                throw new Exception("No hay un ejercicio contable abierto para la fecha de imputación ($fechaImput).");
            }

            // B. Buscar Estructura de Períodos
            $stmtPer = $pdo->prepare("SELECT * FROM ejperidodos WHERE idejercicio = ? LIMIT 1");
            $stmtPer->execute([$ejercicio_id]);
            $rowPer = $stmtPer->fetch(PDO::FETCH_ASSOC);

            if (!$rowPer) {
                throw new Exception("No se encontró la estructura de períodos para el ejercicio actual.");
            }

            // C. Calcular Período Actual dinámicamente
            $fechaDT = new DateTime($fechaImput);
            $anio_fecha = (int) $fechaDT->format('Y');
            $mes_fecha = (int) $fechaDT->format('m');

            $mesesDesdeInicio = (($anio_fecha - (int) $rowPer['anioinicio']) * 12) + ($mes_fecha - (int) $rowPer['mesinicio']);
            $periodoActual = $mesesDesdeInicio + 1;

            if ($periodoActual < 1 || $periodoActual > (int) $rowPer['cantperiodos']) {
                throw new Exception("La fecha de imputación no pertenece a ningún período válido del ejercicio.");
            }

            // D. Validar si el período específico está abierto
            $campoP = "p" . $periodoActual;
            if (!isset($rowPer[$campoP]) || (int) $rowPer[$campoP] === 0) {
                throw new Exception("El período contable $periodoActual está cerrado. No se pueden registrar facturas.");
            }
            // ---------------------------------------------------------

            $totalFactura = floatval($data['total']);
            $tipocomp = (int) $data['tipocomp'];
            $esFiscal = $data['fiscal'] ? 1 : 0;
            $stmtConceptoActivo = $pdo->prepare('SELECT 1 FROM conceptosgastos WHERE id = ? AND habilitada = 1');
            foreach ($data['conceptos'] ?? [] as $concepto) {
                $stmtConceptoActivo->execute([(int)($concepto['idconcepto'] ?? 0)]);
                if (!$stmtConceptoActivo->fetchColumn()) {
                    throw new Exception('Un concepto seleccionado fue deshabilitado. Actualice la lista antes de grabar.');
                }
            }

            // Determinar multiplicador (NC restan en CC y contabilidad)
            $esNC = in_array($tipocomp, [3, 8, 13, 101]);
            $mult = $esNC ? -1 : 1;

            // 1. Insertar Factura
            $sqlF = "INSERT INTO facturasdecompra (proveedorid, neto, impint, iva, iva10, iva27, subtotal, retiva, retiibb, retganancia, total, fecha, fechaimputacion, fechavencimiento, pv, numfact, tipocomp, tipofactura, fiscal, notas, usuario, estado, saldoaimputar) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, '', ?, ?, ?, 1, ?)";
            $stmtF = $pdo->prepare($sqlF);
            $stmtF->execute([
                $data['proveedorid'],
                $data['neto'] * $mult,
                $data['impint'] * $mult,
                $data['iva21'] * $mult,
                $data['iva105'] * $mult,
                $data['iva27'] * $mult,
                $data['subtotal'] * $mult,
                $data['retiva'] * $mult,
                $data['retiibb'] * $mult,
                $totalFactura * $mult,
                $data['fecha'],
                $fechaImput,
                $data['fechavencimiento'],
                $data['pv'],
                $data['numfact'],
                $tipocomp,
                $esFiscal,
                $data['notas'],
                $usuario,
                $totalFactura
            ]);
            $idFactura = $pdo->lastInsertId();

            // 2. Movimiento CC Proveedores
            $facturasCC = $esNC ? 0 : $totalFactura;
            $pagosCC = $esNC ? $totalFactura : 0;
            $pdo->prepare("INSERT INTO moviemientosccproveedores (proveedor, facturas, pagos, fecha, compasoc, tabla, fiscal, usuario, idpago) VALUES (?, ?, ?, ?, ?, 'facturasdecompra', ?, ?, 0)")
                ->execute([$data['proveedorid'], $facturasCC, $pagosCC, $data['fecha'], $idFactura, $esFiscal, $usuario]);

            // 3. Lineas de Conceptos (Para estadísticas de compra)
            foreach ($data['conceptos'] as $c) {
                $pdo->prepare("INSERT INTO lineaconceptoscompra (idfactura, fecha, idproveedor, idconcepto, importe, alicuota) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([$idFactura, $data['fecha'], $data['proveedorid'], $c['idconcepto'], $c['importe'], $c['alicuota']]);
            }

            // 4. Imputación si es Nota de Crédito
            $saldoARepartir = $totalFactura;
            if ($esNC && !empty($data['imputaciones'])) {
                foreach ($data['imputaciones'] as $imp) {
                    if ($saldoARepartir <= 0)
                        break;
                    $montoImputar = min($imp['pendiente'], $saldoARepartir);

                    $pdo->prepare("INSERT INTO pagosfacturasproveedores (idpago, idfactura, importepagado, tipo) VALUES (?, ?, ?, 'N')")
                        ->execute([$idFactura, $imp['id'], $montoImputar]);

                    if ($montoImputar >= $imp['pendiente']) {
                        $pdo->prepare("UPDATE facturasdecompra SET estado = 2 WHERE id = ?")->execute([$imp['id']]);
                    }
                    $saldoARepartir -= $montoImputar;
                }
            }

            // Actualizar saldo de la propia factura/NC
            $estadoFinal = ($saldoARepartir <= 0) ? 2 : 1;
            $pdo->prepare("UPDATE facturasdecompra SET saldoaimputar = ?, estado = ? WHERE id = ?")
                ->execute([$saldoARepartir, $estadoFinal, $idFactura]);

            // 5. Libro Diario (Asiento Contable) - USANDO DATOS VALIDADOS
            $stmtMax = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
            $stmtMax->execute([$ejercicio_id]);
            $numAsiento = ((int) $stmtMax->fetchColumn()) + 1;

            $detalleAsiento = $data['tipo_label'] . " Prov. " . $data['pv'] . "-" . $data['numfact'] . " - " . $data['nombre_proveedor'];

            // Cuentas predeterminadas
            $cp = $pdo->query("SELECT proveedores, ivacompras, impintcompras, retiibb, retiva, mercaderia FROM cuentaspredeterminadas WHERE id = 1")->fetch();
            $stmtCtaProv = $pdo->prepare("SELECT cuentacontable FROM Proveedores WHERE id = ?");
            $stmtCtaProv->execute([(int) $data['proveedorid']]);
            $ctaprov = resolverCuentaContable($pdo, $stmtCtaProv->fetchColumn(), $cp['proveedores'], 'el proveedor');

            // NOTA: Se pasa $ejercicio_id y $periodoActual a grabarLineaAsiento
            if ($esNC) {
                grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $ctaprov, $totalFactura, 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            } else {
                grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $ctaprov, 0, $totalFactura, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }

            // Lineas de conceptos al diario
            $stmtCtaConc = $pdo->prepare("SELECT cuentacontable FROM conceptosgastos WHERE id = ?");
            foreach ($data['conceptos'] as $c) {
                $stmtCtaConc->execute([(int) $c['idconcepto']]);
                $ctaConc = resolverCuentaContable($pdo, $stmtCtaConc->fetchColumn(), $cp['mercaderia'], "el concepto de compra #{$c['idconcepto']}");
                if ($esNC) {
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $ctaConc, 0, $c['importe'], $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                } else {
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $ctaConc, $c['importe'], 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                }
            }

            // Impuestos al diario (IVA y Percepciones)
            $ivatotal = $data['iva21'] + $data['iva105'] + $data['iva27'];
            if ($ivatotal > 0) {
                if ($esNC)
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['ivacompras'], 0, $ivatotal, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                else
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['ivacompras'], $ivatotal, 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }
            if ($data['retiibb'] > 0) {
                if ($esNC)
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['retiibb'], 0, $data['retiibb'], $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                else
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['retiibb'], $data['retiibb'], 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }
            if ($data['retiva'] > 0) {
                if ($esNC)
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['retiva'], 0, $data['retiva'], $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                else
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['retiva'], $data['retiva'], 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }
            if ($data['impint'] > 0) {
                if ($esNC)
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['impintcompras'], 0, $data['impint'], $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
                else
                    grabarLineaAsiento($pdo, $numAsiento, $fechaImput, $cp['impintcompras'], $data['impint'], 0, $idFactura, "facturasdecompra", $esFiscal, $usuario, $detalleAsiento, $ejercicio_id, $periodoActual);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction())
        $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
