<?php
/**
 * api_stock_ajuste_excel.php - AJUSTE MASIVO DE STOCK POR PLANILLA EXCEL
 * Lleva el stock de cada código al valor indicado en la planilla, registrando
 * exactamente los mismos movimientos que un ajuste manual (cabecera, línea de
 * ajuste, movimiento de stock e ingreso de stock cuando corresponde).
 */
require_once 'check_api_access.php';
require_once 'contabilidad_cuentas_helpers.php';
require_once __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Csv as ReaderCsv;
use PhpOffice\PhpSpreadsheet\Reader\Xls as ReaderXls;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ReaderXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as WriterXlsx;

$action  = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

const AJ_EXCEL_CODIGO_SYNS = ['CODIGO', 'COD', 'CODIGO PRODUCTO', 'SKU'];
const AJ_EXCEL_STOCK_SYNS  = ['STOCK ACTUAL', 'STOCK', 'STOCK REAL', 'STOCK FISICO', 'CANTIDAD', 'CONTEO', 'STOCK FINAL'];
const AJ_EXCEL_DESC_SYNS   = ['DESCRIPCION', 'PRODUCTO', 'DETALLE'];

// grabarLineaAsiento() y resolverEjercicioPeriodo() ahora viven en
// contabilidad_cuentas_helpers.php (ya incluido arriba) — antes estaban duplicadas
// idénticas en varios api_*.php.

function ajExcelNormHeader($h) {
    $h = trim((string) $h);
    $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);
    $h = mb_strtoupper($h, 'UTF-8');
    $h = strtr($h, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
    return trim($h);
}

function ajExcelParseNumero($val) {
    if ($val === null) return null;
    if (is_numeric($val)) return (float) $val;
    $val = trim((string) $val);
    if ($val === '') return null;
    if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $val)) {
        $val = str_replace('.', '', $val);
        $val = str_replace(',', '.', $val);
    } elseif (preg_match('/^-?\d+,\d+$/', $val)) {
        $val = str_replace(',', '.', $val);
    } else {
        $val = str_replace(',', '', $val);
    }
    return is_numeric($val) ? (float) $val : null;
}

function ajExcelLimpiarSesion() {
    if (!empty($_SESSION['ajuste_excel_path']) && file_exists($_SESSION['ajuste_excel_path'])) {
        @unlink($_SESSION['ajuste_excel_path']);
    }
    unset($_SESSION['ajuste_excel_path'], $_SESSION['ajuste_excel_nombre']);
}

try {
    switch ($action) {

        // ------------------------------------------------------------
        // 1. DESCARGAR PLANILLA (con el stock actual precargado)
        // ------------------------------------------------------------
        case 'download_template':
            $rubro     = $_GET['rubro'] ?? '';
            $marca     = $_GET['marca'] ?? '';
            $familia   = $_GET['familia'] ?? '';
            $proveedor = $_GET['proveedor'] ?? '';

            $sql = "SELECT codigo, descripcion, rubro, Stock AS stock FROM alb_productos WHERE habilitado = 1";
            $params = [];
            if ($rubro !== '')     { $sql .= " AND rubro = ?";       $params[] = $rubro; }
            if ($marca !== '')     { $sql .= " AND Marca = ?";       $params[] = $marca; }
            if ($familia !== '')   { $sql .= " AND familia = ?";     $params[] = $familia; }
            if ($proveedor !== '') { $sql .= " AND proveedorID = ?"; $params[] = $proveedor; }
            $sql .= " ORDER BY descripcion ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $productos = $stmt->fetchAll();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Ajuste de Stock');

            $headers = ['CODIGO', 'DESCRIPCION', 'RUBRO', 'STOCK SISTEMA', 'STOCK ACTUAL'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            foreach ($productos as $p) {
                $sheet->setCellValueExplicit("A$row", $p['codigo'], DataType::TYPE_STRING);
                $sheet->setCellValue("B$row", $p['descripcion']);
                $sheet->setCellValue("C$row", $p['rubro']);
                $sheet->setCellValue("D$row", (float) $p['stock']);
                $sheet->setCellValue("E$row", (float) $p['stock']);
                $row++;
            }

            $sheet->getStyle('A1:E1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:E1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('003044');
            $sheet->getStyle('D2:E' . max($row - 1, 2))->getNumberFormat()->setFormatCode('0.00');
            if ($row > 2) {
                $sheet->getStyle('E2:E' . ($row - 1))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF9C4');
            }
            foreach (range('A', 'E') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:E1');

            $filename = 'Planilla_Ajuste_Stock_' . date('Ymd_His') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new WriterXlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        // ------------------------------------------------------------
        // 2. SUBIR Y VALIDAR PLANILLA (previsualización, no impacta stock)
        // ------------------------------------------------------------
        case 'upload':
            if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No se recibió el archivo o hubo un error al subirlo.');
            }

            $nombreOriginal = $_FILES['archivo']['name'];
            $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
            $tmpName = $_FILES['archivo']['tmp_name'];

            if (!in_array($ext, ['xlsx', 'xls', 'csv'])) {
                throw new Exception('Formato no soportado. Use XLSX, XLS o CSV.');
            }

            if ($ext === 'xlsx') {
                $reader = new ReaderXlsx();
            } elseif ($ext === 'xls') {
                $reader = new ReaderXls();
            } else {
                $reader = new ReaderCsv();
                $sample = (string) file_get_contents($tmpName, false, null, 0, 4000);
                $reader->setDelimiter((substr_count($sample, ';') > substr_count($sample, ',')) ? ';' : ',');
                $reader->setInputEncoding(ReaderCsv::guessEncoding($tmpName));
            }
            $reader->setReadDataOnly(true);

            try {
                $spreadsheet = $reader->load($tmpName);
            } catch (\Throwable $ex) {
                throw new Exception('No se pudo leer el archivo. Verifique que sea un Excel/CSV válido.');
            }

            $sheet = $spreadsheet->getActiveSheet();
            $data  = $sheet->toArray(null, true, false, false);
            if (empty($data)) throw new Exception('El archivo está vacío.');

            $headerRow = array_map('ajExcelNormHeader', array_shift($data));

            $idxCodigo = $idxStock = $idxDesc = null;
            foreach ($headerRow as $i => $h) {
                if ($idxCodigo === null && in_array($h, AJ_EXCEL_CODIGO_SYNS)) $idxCodigo = $i;
                if ($idxStock === null && in_array($h, AJ_EXCEL_STOCK_SYNS))   $idxStock = $i;
                if ($idxDesc === null && in_array($h, AJ_EXCEL_DESC_SYNS))     $idxDesc = $i;
            }

            if ($idxCodigo === null) throw new Exception('No se encontró la columna CODIGO en la planilla.');
            if ($idxStock === null)  throw new Exception('No se encontró la columna de stock (STOCK ACTUAL / STOCK / CANTIDAD).');

            $stmtProd = $pdo->prepare("SELECT codigo, descripcion, Stock, rubro, sinivacosto FROM alb_productos WHERE codigo = ? LIMIT 1");
            $stmtRef  = $pdo->prepare("SELECT refierea FROM referenciasproductos WHERE codigo = ? ORDER BY id DESC LIMIT 1");

            $resultados = [];
            $resumen = ['total' => 0, 'ok' => 0, 'sin_cambio' => 0, 'no_encontrado' => 0, 'invalido' => 0];

            $fila = 1;
            foreach ($data as $row) {
                $fila++;
                $codigoInput = isset($row[$idxCodigo]) ? trim((string) $row[$idxCodigo]) : '';
                if ($codigoInput === '') continue;

                $resumen['total']++;
                $descPlanilla = $idxDesc !== null ? trim((string) ($row[$idxDesc] ?? '')) : '';
                $stockPlanilla = ajExcelParseNumero($row[$idxStock] ?? null);

                $stmtProd->execute([$codigoInput]);
                $prod = $stmtProd->fetch();
                $codigoReal = $codigoInput;

                if (!$prod) {
                    $stmtRef->execute([$codigoInput]);
                    $ref = $stmtRef->fetch();
                    if ($ref) {
                        $codigoReal = $ref['refierea'];
                        $stmtProd->execute([$codigoReal]);
                        $prod = $stmtProd->fetch();
                    }
                }

                if (!$prod) {
                    $resumen['no_encontrado']++;
                    $resultados[] = [
                        'fila' => $fila, 'codigo_planilla' => $codigoInput, 'codigo' => $codigoInput,
                        'descripcion' => $descPlanilla !== '' ? $descPlanilla : '(no encontrado)',
                        'stock_sistema' => null, 'stock_planilla' => $stockPlanilla, 'diferencia' => null,
                        'estado' => 'NO_ENCONTRADO', 'motivo' => 'El código no existe en el sistema.',
                    ];
                    continue;
                }

                if ($stockPlanilla === null || $stockPlanilla < 0) {
                    $resumen['invalido']++;
                    $resultados[] = [
                        'fila' => $fila, 'codigo_planilla' => $codigoInput, 'codigo' => $codigoReal,
                        'descripcion' => $prod['descripcion'], 'stock_sistema' => (float) $prod['Stock'],
                        'stock_planilla' => $stockPlanilla, 'diferencia' => null,
                        'estado' => 'INVALIDO', 'motivo' => 'Valor de stock vacío o inválido.',
                    ];
                    continue;
                }

                $diferencia = round($stockPlanilla - (float) $prod['Stock'], 4);
                $estado = (abs($diferencia) < 0.0001) ? 'SIN_CAMBIO' : 'OK';
                $estado === 'SIN_CAMBIO' ? $resumen['sin_cambio']++ : $resumen['ok']++;

                $resultados[] = [
                    'fila' => $fila, 'codigo_planilla' => $codigoInput, 'codigo' => $codigoReal,
                    'descripcion' => $prod['descripcion'], 'stock_sistema' => (float) $prod['Stock'],
                    'stock_planilla' => $stockPlanilla, 'diferencia' => $diferencia,
                    'estado' => $estado, 'motivo' => null,
                    'rubro' => $prod['rubro'], 'sinivacosto' => $prod['sinivacosto'],
                ];
            }

            if ($resumen['total'] === 0) throw new Exception('La planilla no contiene filas con datos.');

            ajExcelLimpiarSesion();

            $tmpPath = sys_get_temp_dir() . '/ajuste_excel_' . session_id() . '_' . uniqid() . '.json';
            file_put_contents($tmpPath, json_encode($resultados));
            $_SESSION['ajuste_excel_path']   = $tmpPath;
            $_SESSION['ajuste_excel_nombre'] = $nombreOriginal;

            echo json_encode([
                'success'  => true,
                'resumen'  => $resumen,
                'preview'  => array_slice($resultados, 0, 300),
                'truncado' => count($resultados) > 300,
                'archivo'  => $nombreOriginal,
            ]);
            break;

        // ------------------------------------------------------------
        // 3. CONFIRMAR: aplica el ajuste igual que un ajuste manual
        // ------------------------------------------------------------
        case 'process':
            if (empty($_SESSION['ajuste_excel_path']) || !file_exists($_SESSION['ajuste_excel_path'])) {
                throw new Exception('La sesión de importación expiró. Vuelva a subir el archivo.');
            }

            $body        = json_decode(file_get_contents('php://input'), true) ?: [];
            $observacion = trim($body['observacion'] ?? '');
            $nombre      = trim($body['nombre'] ?? '');
            if ($nombre === '') $nombre = 'AJUSTE EXCEL - ' . ($_SESSION['ajuste_excel_nombre'] ?? date('Y-m-d'));
            $fiscal = !empty($body['fiscal']) ? 1 : 0;

            $filas = json_decode(file_get_contents($_SESSION['ajuste_excel_path']), true) ?: [];
            $procesables = array_values(array_filter($filas, function ($r) {
                return in_array($r['estado'], ['OK', 'SIN_CAMBIO']);
            }));

            if (empty($procesables)) throw new Exception('No hay productos válidos para procesar.');

            $fecha = date('Y-m-d H:i:s');
            $ejerPer = resolverEjercicioPeriodo($pdo, date('Y-m-d'));

            $idDeposito = null;
            if (multidepositoHabilitado($pdo)) {
                $idDeposito = !empty($body['id_deposito']) ? (int) $body['id_deposito'] : depositoDeTrabajo($pdo, $usuario);
            }

            $pdo->beginTransaction();

            $stmtCab = $pdo->prepare("INSERT INTO ajustesdeinventario (fechacarga, usuariocarga, estado, fechaajuste, usuarioajuste, observacion, nombregrupo) VALUES (?, ?, 'FINALIZADO', ?, ?, ?, ?)");
            $stmtCab->execute([$fecha, $usuario, $fecha, $usuario, $observacion, mb_strtoupper($nombre, 'UTF-8')]);
            $idajuste = $pdo->lastInsertId();

            $stmtFresh  = $pdo->prepare("SELECT id, Stock, rubro, sinivacosto FROM alb_productos WHERE codigo = ? LIMIT 1");
            $stmtHabil  = $pdo->prepare("UPDATE alb_productos SET habilitado = 1 WHERE codigo = ?");
            $stmtLine   = $pdo->prepare("INSERT INTO lineajustes (idajuste, codigo, stockportabla, ingresan, salen, stockfinal, costounitario, rubro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtMov    = $pdo->prepare("INSERT INTO movimientosdestock (codigo, entradas, salidas, tipomov, esfiscal, fecha, usuario, rubro, origenmov, tipocomp, numremito) VALUES (?, ?, ?, 'AJUSTE DE INVENTARIO', ?, ?, ?, ?, 'AJUSTE', 0, 0)");
            $stmtIng    = $pdo->prepare("INSERT INTO ingresosdestock (tipoingreso, tipocomp, idproveedor, proveedor, codigo, stkant, ingreso, stkfinal, fecha, user, netocosto, fechacarga, stock) VALUES (0, 1000, 1, 'Deposito', ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $procesados = 0;
            $sinCambios = 0;
            $errores = [];
            $totalIngresoValor = 0;
            $totalEgresoValor = 0;

            foreach ($procesables as $r) {
                $stmtFresh->execute([$r['codigo']]);
                $fresh = $stmtFresh->fetch();
                if (!$fresh) {
                    $errores[] = "Código {$r['codigo']}: ya no existe en el sistema.";
                    continue;
                }

                $stockAntes   = (float) $fresh['Stock'];
                $stockDestino = (float) $r['stock_planilla'];
                $diff = round($stockDestino - $stockAntes, 4);
                $ent  = $diff > 0 ? $diff : 0;
                $sal  = $diff < 0 ? abs($diff) : 0;

                $stmtHabil->execute([$r['codigo']]);
                $stmtLine->execute([$idajuste, $r['codigo'], $stockAntes, $ent, $sal, $stockDestino, $fresh['sinivacosto'], $fresh['rubro']]);

                if ($diff != 0) {
                    $stmtMov->execute([$r['codigo'], $ent, $sal, $fiscal, $fecha, $usuario, $fresh['rubro']]);
                    $idMovLegacy = (int) $pdo->lastInsertId();
                    ajustarStockADestino($pdo, (int) $fresh['id'], $r['codigo'], $idDeposito, $stockAntes, $stockDestino,
                                         'AJUSTE DE INVENTARIO', 'AJUSTE', 0, $idajuste, $usuario, $idMovLegacy);
                    if ($ent > 0) {
                        $stmtIng->execute([$r['codigo'], $stockAntes, $ent, $stockDestino, date('Y-m-d'), $usuario, $fresh['sinivacosto'], $fecha, $ent]);
                    }
                    $totalIngresoValor += round($ent * (float) $fresh['sinivacosto'], 2);
                    $totalEgresoValor += round($sal * (float) $fresh['sinivacosto'], 2);
                    $procesados++;
                } else {
                    $sinCambios++;
                }
            }

            // Asiento contable (Ajuste de Stock / Ajuste de Costo)
            if ($totalIngresoValor > 0 || $totalEgresoValor > 0) {
                $cp = $pdo->query("SELECT ajuste, ajustemercaderia FROM cuentaspredeterminadas WHERE id = 1")->fetch();
                $stmtNum = $pdo->prepare("SELECT MAX(numasiento) FROM librodiario WHERE idejercicio = ? FOR UPDATE");
                $stmtNum->execute([$ejerPer['ejercicio']]);
                $numAsiento = ((int) $stmtNum->fetchColumn()) + 1;
                $detalleAsiento = "Ajuste de Inventario #$idajuste (Excel)";

                if ($totalIngresoValor > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], $totalIngresoValor, 0, $idajuste, "ajustesdeinventario", $fiscal, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], 0, $totalIngresoValor, $idajuste, "ajustesdeinventario", $fiscal, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
                if ($totalEgresoValor > 0) {
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajuste'], $totalEgresoValor, 0, $idajuste, "ajustesdeinventario", $fiscal, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                    grabarLineaAsiento($pdo, $numAsiento, $fecha, $cp['ajustemercaderia'], 0, $totalEgresoValor, $idajuste, "ajustesdeinventario", $fiscal, $usuario, $detalleAsiento, $ejerPer['ejercicio'], $ejerPer['periodo']);
                }
            }

            $pdo->commit();
            ajExcelLimpiarSesion();

            echo json_encode([
                'success'    => true,
                'id'         => $idajuste,
                'message'    => "Ajuste #$idajuste aplicado. Productos con movimiento: $procesados. Sin cambios: $sinCambios.",
                'procesados' => $procesados,
                'sin_cambios' => $sinCambios,
                'errores'    => $errores,
            ]);
            break;

        // ------------------------------------------------------------
        // 4. DESCARTAR IMPORTACIÓN PENDIENTE
        // ------------------------------------------------------------
        case 'discard':
            ajExcelLimpiarSesion();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
