<?php
/**
 * api_proveedores_facturas_admin.php - ENDPOINT ADMINISTRATIVO DE COMPRAS
 * Actualizado: Implementación completa de exportación ARCA (Libro IVA Digital)
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

// Funciones Auxiliares para ARCA (Réplica de VB.NET)
function mapearTipoCompArca($tipoStr) {
    $t = strtoupper($tipoStr);
    if ($t == "FCA") return "001";
    if ($t == "NDA") return "002";
    if ($t == "NCA") return "003";
    if ($t == "FCB") return "006";
    if ($t == "NDB") return "007";
    if ($t == "NCB") return "008";
    if ($t == "FCC") return "011";
    if ($t == "NDC") return "012";
    if ($t == "NCC") return "013";
    return "099";
}

function mapearAlicuotaArca($valor) {
    $v = round(floatval($valor), 3);
    if ($v >= 0.26) return "6"; // 27%
    if ($v >= 0.20) return "5"; // 21%
    if ($v >= 0.10) return "4"; // 10.5%
    if ($v >= 0.04) return "8"; // 5%
    if ($v >= 0.02) return "9"; // 2.5%
    return "3"; // 0% / Exento
}

function formatearImporteArca($valor, $longitud) {
    $centavos = strval(round(abs($valor) * 100));
    $resultado = str_pad($centavos, $longitud, '0', STR_PAD_LEFT);
    if ($valor < 0) {
        $resultado = "-" . str_pad($centavos, $longitud - 1, '0', STR_PAD_LEFT);
    }
    return $resultado;
}

try {
    switch ($action) {
        case 'list':
        case 'export_excel':
        case 'export_arca_comp':
        case 'export_arca_ali':
            $data = ($action === 'list') ? json_decode(file_get_contents('php://input'), true) : $_GET;

            $desde = $data['desde'];
            $hasta = $data['hasta'];
            $tipoFecha = $data['tipo_fecha'] ?? 'fecha'; 

            $where = " WHERE a.$tipoFecha BETWEEN ? AND ? ";
            $params = [$desde, $hasta];

            if (!empty($data['proveedor']) && $data['proveedor'] !== 'todos') {
                $where .= " AND a.proveedorid = ? ";
                $params[] = $data['proveedor'];
            }

            if (!empty($data['tipo_comp']) && $data['tipo_comp'] !== 'todos') {
                if ($data['tipo_comp'] === 'FISCALES') $where .= " AND a.fiscal = 1 ";
                elseif ($data['tipo_comp'] === 'NO_FISCALES') $where .= " AND a.fiscal = 0 ";
                else {
                    $where .= " AND a.tipocomp = ? ";
                    $params[] = $data['tipo_comp'];
                }
            }

            if (!empty($data['solo_pendientes']) && $data['solo_pendientes'] == 'true') {
                $where .= " AND a.estado = 1 ";
            }

            if (!empty($data['filtro_importe']) && $data['filtro_importe'] !== 'todos') {
                $valor = floatval($data['valor_importe']);
                $op = ['MAYOR' => '>', 'MENOR' => '<', 'IGUAL' => '='][$data['filtro_importe']] ?? '=';
                $where .= " AND ABS(a.total) $op ? ";
                $params[] = $valor;
            }

            $sql = "SELECT 
                        a.id AS 'ID', 
                        DATE_FORMAT(a.fecha, '%Y-%m-%d') AS 'Fecha', 
                        DATE_FORMAT(a.fechaimputacion, '%Y-%m-%d') AS 'Fecha Imputacion', 
                        DATE_FORMAT(a.fechavencimiento, '%Y-%m-%d') AS 'Fecha Vencimiento', 
                        DATE_FORMAT(a.fecharegistro, '%d-%m-%Y %H:%i:%s') AS 'Fecha Registro',
                        CASE a.tipocomp 
                            WHEN 1 THEN 'FCA' WHEN 2 THEN 'NDA' WHEN 3 THEN 'NCA' WHEN 1000 THEN 'REM' WHEN 6 THEN 'FCB' WHEN 7 THEN 'NDB'
                            WHEN 8 THEN 'NCB' WHEN 100 THEN 'TKT' WHEN 101 THEN 'TDC' WHEN 11 THEN 'FCC' WHEN 12 THEN 'NDC'
                            WHEN 13 THEN 'NCC' ELSE 'Otro' 
                        END AS 'Tipo',
                        a.PV, a.numfact,
                        CONCAT(LPAD(a.PV, 4, '0'),'-',LPAD(a.numfact, 8, '0')) AS 'Num Factura',
                        d.razon AS 'Proveedor', d.cuit AS 'CUIT', a.proveedorid AS 'Proveedor ID',
                        a.neto AS 'Neto', 
                        a.iva AS 'IVA 21', a.iva10 AS 'IVA 10.5', a.iva27 AS 'IVA 27',
                        (a.iva + a.iva10 + a.iva27) AS 'IVA Total',
                        a.impint AS 'ImpInt',
                        a.retiibb AS 'Ret IIBB', a.retiva AS 'Ret IVA', a.retganancia AS 'Ret Ganancias',
                        a.total AS 'Total',
                        CASE a.estado WHEN 1 THEN 'Pendiente' WHEN 2 THEN 'Pagada' ELSE 'Otro' END AS 'Estado',
                        a.fiscal AS 'Fiscal', a.usuario AS 'Usuario Carga', a.notas AS 'Notas', a.tipocomp AS 'tc_cod'
                    FROM facturasdecompra a 
                    LEFT JOIN Proveedores d ON a.proveedorid = d.id 
                    $where 
                    ORDER BY a.$tipoFecha DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $res = $stmt->fetchAll();

            // LÓGICA DE EXPORTACIÓN ARCA (LIBRO IVA DIGITAL)
            if ($action === 'export_arca_comp' || $action === 'export_arca_ali') {
                $txt = "";
                foreach ($res as $row) {
                    $fechaArca = str_replace("-", "", $row['Fecha']);
                    $tipoArca = mapearTipoCompArca($row['Tipo']);
                    $cuitArca = str_replace("-", "", $row['CUIT']);
                    $esNC = in_array(intval($row['tc_cod']), [3, 8, 13, 101]);
                    $signo = $esNC ? -1 : 1;

                    if ($action === 'export_arca_comp') {
                        // Diseño de registro de Comprobantes (F.8011)
                        $txt .= $fechaArca; // Fecha (8)
                        $txt .= str_pad($tipoArca, 3, '0', STR_PAD_LEFT); // Tipo (3)
                        $txt .= str_pad($row['PV'], 5, '0', STR_PAD_LEFT); // P.Venta (5)
                        $txt .= str_pad($row['numfact'], 20, '0', STR_PAD_LEFT); // Numero (20)
                        $txt .= str_repeat(' ', 16); // Despacho (16)
                        $txt .= "80"; // Cod Doc (2)
                        $txt .= str_pad($cuitArca, 20, '0', STR_PAD_LEFT); // CUIT (20)
                        $txt .= str_pad(substr($row['Proveedor'], 0, 30), 30, ' ', STR_PAD_RIGHT); // Nombre (30)
                        $txt .= formatearImporteArca($row['Total'], 15); // Total (15)
                        
                        // No gravado (VB calculaba: total - neto - iva - impint - retiva - retgan - retiibb)
                        $noGravado = $row['Total'] - $row['Neto'] - $row['IVA Total'] - $row['ImpInt'] - $row['Ret IVA'] - $row['Ret Ganancias'] - $row['Ret IIBB'];
                        $txt .= formatearImporteArca($noGravado, 15);
                        $txt .= formatearImporteArca(0, 15); // Exento
                        $txt .= formatearImporteArca($row['Ret Ganancias'], 15); // Perc. Gan
                        $txt .= formatearImporteArca($row['Ret IIBB'], 15); // Perc. IIBB
                        $txt .= formatearImporteArca(0, 15); // Perc. Munic
                        $txt .= formatearImporteArca($row['ImpInt'], 15); // Imp. Internos
                        $txt .= "PES"; // Moneda (3)
                        $txt .= "0001000000"; // T.Cambio (10)
                        $txt .= ($row['IVA Total'] > 0) ? "1" : "0"; // Cant. Alicuotas (1)
                        $txt .= " "; // Cod Operacion (1)
                        $txt .= formatearImporteArca($row['IVA Total'], 15); // Cred. Fiscal
                        $txt .= formatearImporteArca($row['Ret IVA'], 15); // Otros Trib
                        $txt .= str_pad('', 11, '0'); // CUIT Corredor
                        $txt .= str_repeat(' ', 30); // Nombre Corredor
                        $txt .= formatearImporteArca(0, 15); // IVA Comision
                        $txt .= "\r\n";
                    } else {
                        // Diseño de registro de Alícuotas (F.8012)
                        if ($row['IVA Total'] > 0 || $row['Neto'] > 0) {
                            $txt .= str_pad($tipoArca, 3, '0', STR_PAD_LEFT);
                            $txt .= str_pad($row['PV'], 5, '0', STR_PAD_LEFT);
                            $txt .= str_pad($row['numfact'], 20, '0', STR_PAD_LEFT);
                            $txt .= "80";
                            $txt .= str_pad($cuitArca, 20, '0', STR_PAD_LEFT);
                            $txt .= formatearImporteArca($row['Neto'] * $signo, 15);
                            
                            // Determinación de código de alícuota dominante o principal
                            $aliVal = 0.21;
                            if ($row['IVA 10.5'] > 0) $aliVal = 0.105;
                            if ($row['IVA 27'] > 0) $aliVal = 0.27;
                            
                            $txt .= str_pad(mapearAlicuotaArca($aliVal), 4, '0', STR_PAD_LEFT);
                            $txt .= formatearImporteArca($row['IVA Total'] * $signo, 15);
                            $txt .= "\r\n";
                        }
                    }
                }
                $filename = ($action === 'export_arca_comp' ? "COMPROBANTES_" : "ALICUOTAS_") . date('Ymd') . ".txt";
                header('Content-Type: text/plain');
                header('Content-Disposition: attachment; filename="'.$filename.'"');
                echo $txt;
                exit;
            }

            // Resumen de Totales para la grilla
            $resumen = ['subt' => 0, 'iva' => 0, 'ii' => 0, 'total' => 0, 'rgan' => 0, 'riibb' => 0, 'riva' => 0];
            foreach ($res as $r) {
                $resumen['subt'] += $r['Neto'];
                $resumen['iva'] += $r['IVA Total'];
                $resumen['ii'] += $r['ImpInt'];
                $resumen['total'] += $r['Total'];
                $resumen['rgan'] += $r['Ret Ganancias'];
                $resumen['riibb'] += $r['Ret IIBB'];
                $resumen['riva'] += $r['Ret IVA'];
            }

            if ($action === 'list') {
                echo json_encode(['success' => true, 'data' => $res, 'resumen' => $resumen]);
            } elseif ($action === 'export_excel') {
                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename=facturas_compra_' . date('Ymd_His') . '.xls');
                echo "\xEF\xBB\xBF<table border='1'><thead><tr style='background-color: #001020; color: #00ffff;'>";
                if (!empty($res)) {
                    foreach (array_keys($res[0]) as $col) {
                        if (in_array($col, ['PV', 'numfact', 'Proveedor ID', 'tc_cod', 'IVA 21', 'IVA 10.5', 'IVA 27'])) continue;
                        echo "<th>" . strtoupper($col) . "</th>";
                    }
                    echo "</tr></thead><tbody>";
                    foreach ($res as $row) {
                        echo "<tr>";
                        foreach ($row as $key => $val) {
                            if (in_array($key, ['PV', 'numfact', 'Proveedor ID', 'tc_cod', 'IVA 21', 'IVA 10.5', 'IVA 27'])) continue;
                            $style = (is_numeric($val) && !in_array($key, ['ID', 'Num Factura', 'CUIT'])) ? "text-align: right;" : "";
                            echo "<td style='$style'>" . htmlspecialchars($val) . "</td>";
                        }
                        echo "</tr>";
                    }
                }
                echo "</tbody></table>";
                exit;
            }
            break;

        case 'get_lines':
            $id = $_GET['id'];
            $sql = "SELECT l.importe, l.alicuota, c.concepto 
                    FROM lineaconceptoscompra l 
                    JOIN conceptosgastos c ON l.idconcepto = c.id 
                    WHERE l.idfactura = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
            break;

        case 'delete':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'];
            $pdo->beginTransaction();
            $stmtCheck = $pdo->prepare("SELECT 1 FROM pagosfacturasproveedores WHERE idfactura = ? AND tipo = 'P' LIMIT 1");
            $stmtCheck->execute([$id]);
            if ($stmtCheck->fetch()) throw new Exception("NO SE PUEDE ELIMINAR LA FACTURA PORQUE TIENE PAGOS PROCESADOS");

            $pdo->prepare("INSERT INTO facturaseliminadas SELECT * FROM facturasdecompra WHERE id = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM moviemientosccproveedores WHERE compasoc = ? AND tabla = 'facturasdecompra'")->execute([$id]);
            $pdo->prepare("DELETE FROM lineaconceptoscompra WHERE idfactura = ?")->execute([$id]);
            $pdo->prepare("DELETE FROM librodiario WHERE compasoc = ? AND tabla = 'facturasdecompra'")->execute([$id]);
            $pdo->prepare("DELETE FROM facturasdecompra WHERE id = ?")->execute([$id]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'unimpute_nc':
            $data = json_decode(file_get_contents('php://input'), true);
            $idNC = $data['id'];
            $pdo->beginTransaction();
            $stmtImp = $pdo->prepare("SELECT idfactura, importepagado FROM pagosfacturasproveedores WHERE idpago = ? AND tipo = 'N'");
            $stmtImp->execute([$idNC]);
            $imputaciones = $stmtImp->fetchAll();
            if (empty($imputaciones)) throw new Exception("El comprobante no tiene imputaciones.");

            foreach ($imputaciones as $row) {
                $pdo->prepare("UPDATE facturasdecompra SET estado = 1, saldoaimputar = total - (SELECT IFNULL(SUM(importepagado),0) FROM pagosfacturasproveedores WHERE idfactura = ? AND idpago <> ?) WHERE id = ?")
                    ->execute([$row['idfactura'], $idNC, $row['idfactura']]);
            }
            $pdo->prepare("UPDATE facturasdecompra SET estado = 1, saldoaimputar = total WHERE id = ?")->execute([$idNC]);
            $pdo->prepare("DELETE FROM pagosfacturasproveedores WHERE idpago = ? AND tipo = 'N'")->execute([$idNC]);
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'update_field':
            $data = json_decode(file_get_contents('php://input'), true);
            $camposPermitidos = ['fecha', 'fechavencimiento', 'notas', 'fiscal'];
            if (!in_array($data['field'], $camposPermitidos)) throw new Exception("Campo no editable.");

            if ($data['field'] === 'fiscal') {
                $stmtChk = $pdo->prepare("SELECT estado FROM facturasdecompra WHERE id = ?");
                $stmtChk->execute([$data['id']]);
                if ($stmtChk->fetchColumn() == 2) throw new Exception("No se puede modificar la fiscalidad de una factura ya pagada.");
            }

            $pdo->beginTransaction();
            $pdo->prepare("UPDATE facturasdecompra SET {$data['field']} = ? WHERE id = ?")->execute([$data['value'], $data['id']]);
            if ($data['field'] === 'fecha') {
                $pdo->prepare("UPDATE moviemientosccproveedores SET fecha = ? WHERE compasoc = ? AND tabla = 'facturasdecompra'")->execute([$data['value'], $data['id']]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}