<?php
/**
 * api_importar_precios.php - Backend Inteligente
 * Mejora: Calcula automáticamente Netos e IVAs a partir del Precio Final del Excel.
 * Optimización: Lectura de archivos línea por línea para bajo consumo de memoria.
 */

// SEGURIDAD y HEADERS
require_once 'check_api_access.php';

if (!isset($_GET['action']) || !in_array($_GET['action'], ['download_template', 'backup'])) {
    header('Content-Type: application/json; charset=utf-8');
}

require_once 'db.php';
$action = $_GET['action'] ?? '';

try {
    // --- 1. GENERAR PLANTILLA (MODELO) ---
    if ($action === 'download_template') {
        // ... (Lógica de filtros idéntica a la anterior) ...
        $todos = $_GET['todos'] ?? 'false';
        $proveedor = $_GET['proveedor'] ?? '';
        $rubro = $_GET['rubro'] ?? '';
        $familia = $_GET['familia'] ?? '';
        $marca = $_GET['marca'] ?? '';

        $sql = "SELECT p.codigo, p.descripcion, p.sinivacosto, p.impint, p.ivacosto, p.preciocosto, 
                       p.precio AS precio1, p.precio2, p.precio3, p.precio4, p.precio5, p.precio6, p.precio7, p.precio8
                FROM alb_productos p WHERE 1=1";
        
        $params = [];
        if ($todos !== 'true') {
            if ($proveedor) { $sql .= " AND p.Proveedor = ?"; $params[] = $proveedor; }
            if ($rubro) { $sql .= " AND p.rubro = ?"; $params[] = $rubro; }
            if ($familia) { $sql .= " AND p.familia = ?"; $params[] = $familia; }
            if ($marca) { $sql .= " AND p.Marca = ?"; $params[] = $marca; }
        }
        $sql .= " ORDER BY p.descripcion ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $filename = "Modelo_Precios_" . date('Ymd_Hi') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        $headers = [
            'CODIGO', 'DESCRIPCION', 'NETO COSTO', 'IMP INT', 'IVA COSTO', 'TOTAL COSTO',
            'LISTA 1 FINAL', 'LISTA 2 FINAL', 'LISTA 3 FINAL', 'LISTA 4 FINAL', 
            'LISTA 5 FINAL', 'LISTA 6 FINAL', 'LISTA 7 FINAL', 'LISTA 8 FINAL'
        ];
        fputcsv($output, $headers, ';');

        while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row = [
                $p['codigo'], $p['descripcion'],
                number_format($p['sinivacosto'], 2, ',', ''), number_format($p['impint'], 2, ',', ''),
                number_format($p['ivacosto'], 2, ',', ''), number_format($p['preciocosto'], 2, ',', ''),
                number_format($p['precio1'], 2, ',', ''), number_format($p['precio2'], 2, ',', ''),
                number_format($p['precio3'], 2, ',', ''), number_format($p['precio4'], 2, ',', ''),
                number_format($p['precio5'], 2, ',', ''), number_format($p['precio6'], 2, ',', ''),
                number_format($p['precio7'], 2, ',', ''), number_format($p['precio8'], 2, ',', '')
            ];
            fputcsv($output, $row, ';');
        }
        fclose($output);
        exit;
    }

    // --- 2. GENERAR BACKUP ---
    if ($action === 'backup') {
        if (!isset($_SESSION['csv_path']) || !file_exists($_SESSION['csv_path'])) {
            die("Error: No hay archivo cargado.");
        }
        $path = $_SESSION['csv_path'];
        $delimiter = $_SESSION['csv_delimiter'] ?? ';';
        $handle = fopen($path, "r");
        
        $fileHeaders = fgetcsv($handle, 0, $delimiter);
        $fileHeaders = array_map(function($h) { return trim(strtoupper(preg_replace('/^\xEF\xBB\xBF/', '', $h))); }, $fileHeaders);
        $idxCod = array_search('CODIGO', $fileHeaders);

        if ($idxCod === false) die("Error: Columna CODIGO no encontrada.");

        $codigos = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (isset($row[$idxCod]) && $row[$idxCod]) $codigos[] = $row[$idxCod];
        }
        fclose($handle);

        if (empty($codigos)) die("Error: Archivo vacío.");

        // Optimización: Si son muchos, hacemos chunks
        $chunks = array_chunk($codigos, 1000);
        
        $filename = "BACKUP_Pre_Import_" . date('Ymd_His') . ".csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Mismos headers del modelo
        $headersBackup = [
            'CODIGO', 'DESCRIPCION', 'NETO COSTO', 'IMP INT', 'IVA COSTO', 'TOTAL COSTO',
            'LISTA 1 FINAL', 'LISTA 2 FINAL', 'LISTA 3 FINAL', 'LISTA 4 FINAL', 
            'LISTA 5 FINAL', 'LISTA 6 FINAL', 'LISTA 7 FINAL', 'LISTA 8 FINAL'
        ];
        fputcsv($output, $headersBackup, ';');

        foreach ($chunks as $chunk) {
            $placeholders = implode(',', array_fill(0, count($chunk), '?'));
            $sql = "SELECT p.codigo, p.descripcion, p.sinivacosto, p.impint, p.ivacosto, p.preciocosto, 
                       p.precio AS precio1, p.precio2, p.precio3, p.precio4, p.precio5, p.precio6, p.precio7, p.precio8
                    FROM alb_productos p WHERE p.codigo IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($chunk);
            while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
                 $row = [
                    $p['codigo'], $p['descripcion'],
                    number_format($p['sinivacosto'], 2, ',', ''), number_format($p['impint'], 2, ',', ''),
                    number_format($p['ivacosto'], 2, ',', ''), number_format($p['preciocosto'], 2, ',', ''),
                    number_format($p['precio1'], 2, ',', ''), number_format($p['precio2'], 2, ',', ''),
                    number_format($p['precio3'], 2, ',', ''), number_format($p['precio4'], 2, ',', ''),
                    number_format($p['precio5'], 2, ',', ''), number_format($p['precio6'], 2, ',', ''),
                    number_format($p['precio7'], 2, ',', ''), number_format($p['precio8'], 2, ',', '')
                ];
                fputcsv($output, $row, ';');
            }
        }
        fclose($output);
        exit;
    }

    // --- 3. SUBIR Y PREVISUALIZAR (OPTIMIZADO CON FOPEN) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'upload') {
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir el archivo.");
        }

        $tmpName = $_FILES['archivo']['tmp_name'];
        $handle = fopen($tmpName, "r");
        $firstLine = fgets($handle);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        if (!$headers) throw new Exception("Formato inválido.");
        
        $headers = array_map(function($h) { 
            return trim(strtoupper(preg_replace('/^\xEF\xBB\xBF/', '', $h))); 
        }, $headers);

        if (!in_array('CODIGO', $headers)) throw new Exception("Falta columna CODIGO.");

        $preview = [];
        $count = 0;
        // Solo leemos las primeras 50 para preview, el resto solo contamos
        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if ($count < 50) {
                if (count($row) < count($headers)) $row = array_pad($row, count($headers), "");
                $item = array_combine($headers, array_slice($row, 0, count($headers)));
                if (!empty($item['CODIGO'])) $preview[] = $item;
            }
            if (!empty($row[0])) $count++;
        }
        fclose($handle);

        $tempPath = sys_get_temp_dir() . '/import_erp_' . uniqid() . '.csv';
        move_uploaded_file($tmpName, $tempPath);
        $_SESSION['csv_path'] = $tempPath;
        $_SESSION['csv_delimiter'] = $delimiter;
        $_SESSION['csv_headers'] = $headers;

        echo json_encode([
            'success' => true, 
            'headers' => $headers, 
            'preview' => $preview, 
            'total_rows' => $count
        ]);
        exit;
    }

    // --- 4. PROCESAR (SMART UPDATE) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'process') {
        if (!isset($_SESSION['csv_path']) || !file_exists($_SESSION['csv_path'])) throw new Exception("Sesión expirada.");

        $path = $_SESSION['csv_path'];
        $delimiter = $_SESSION['csv_delimiter'];
        $headers = $_SESSION['csv_headers'];

        $handle = fopen($path, "r");
        fgetcsv($handle, 0, $delimiter); // Saltar header

        $pdo->beginTransaction();
        
        // 1. Histórico (Log)
        $stmtHist = $pdo->prepare("INSERT INTO historicoproductos (codigo, descripcion, sinivacosto, preciocosto, precio, usuariomodif, fechamodif) 
                                   SELECT codigo, descripcion, sinivacosto, preciocosto, precio, :user, NOW() FROM alb_productos WHERE codigo = :cod");
        
        // 2. Obtener datos actuales (IVA %) para cálculos
        $stmtGetTax = $pdo->prepare("SELECT poriva FROM alb_productos WHERE codigo = :cod");

        // 3. Update Completo (calculado)
        $sqlUpd = "UPDATE alb_productos SET 
                   -- Costos
                   sinivacosto = :nc, impint = :ii, ivacosto = :ic, preciocosto = :tc,
                   -- Listas de Precios (Calculamos Netos e IVAs)
                   precio = :p1, siniva = :n1, iva1 = :i1,
                   precio2 = :p2, siniva2 = :n2, iva2 = :i2,
                   precio3 = :p3, siniva3 = :n3, iva3 = :i3,
                   precio4 = :p4, siniva4 = :n4, iva4 = :i4,
                   precio5 = :p5, siniva5 = :n5, iva5 = :i5,
                   precio6 = :p6, siniva6 = :n6, iva6 = :i6,
                   precio7 = :p7, siniva7 = :n7, iva7 = :i7,
                   precio8 = :p8, siniva8 = :n8, iva8 = :i8
                   WHERE codigo = :cod";
        
        $stmtUpd = $pdo->prepare($sqlUpd);
        
        $updated = 0; $errors = 0;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (count($row) < count($headers)) continue;
            $item = array_combine($headers, array_slice($row, 0, count($headers)));
            
            $cod = $item['CODIGO'] ?? '';
            if (!$cod) continue;

            // Función limpieza (1.200,50 -> 1200.50)
            $cleanNum = function($val) {
                $val = str_replace('.', '', $val); 
                $val = str_replace(',', '.', $val); 
                return floatval($val);
            };

            // Valores de Costo (estos se toman literales del Excel)
            $nc = $cleanNum($item['NETO COSTO'] ?? 0);
            $ii = $cleanNum($item['IMP INT'] ?? 0); // Impuesto interno
            $ic = $cleanNum($item['IVA COSTO'] ?? 0);
            $tc = $cleanNum($item['TOTAL COSTO'] ?? 0);
            
            // Valores de Venta Final (del Excel)
            $preciosFinales = [];
            for($i=1; $i<=8; $i++) {
                $colName = "LISTA $i FINAL";
                $preciosFinales[$i] = $cleanNum($item[$colName] ?? 0);
            }

            try {
                // Obtener IVA del producto para ingeniería inversa
                $stmtGetTax->execute([':cod' => $cod]);
                $taxData = $stmtGetTax->fetch(PDO::FETCH_ASSOC);
                $porIva = $taxData ? floatval($taxData['poriva']) : 21; // Default 21% si falla
                
                // Divisor de IVA (ej: 1.21)
                $divIva = 1 + ($porIva / 100);

                // --- LOGICA INTELIGENTE: Calcular Netos e IVAs de venta ---
                // Neto = (Final - ImpInt) / 1.IVA
                // IVA = Final - ImpInt - Neto
                
                $updateParams = [
                    ':nc' => $nc, ':ii' => $ii, ':ic' => $ic, ':tc' => $tc, ':cod' => $cod
                ];

                for($i=1; $i<=8; $i++) {
                    $pFinal = $preciosFinales[$i];
                    
                    if ($pFinal > 0) {
                        $pNeto = round(($pFinal - $ii) / $divIva, 3);
                        $pIva = round($pFinal - $ii - $pNeto, 3);
                    } else {
                        $pNeto = 0; $pIva = 0;
                    }
                    
                    $updateParams[":p$i"] = $pFinal;
                    $updateParams[":n$i"] = $pNeto;
                    $updateParams[":i$i"] = $pIva;
                }

                // Guardar Histórico
                $stmtHist->execute([':user' => $_SESSION['usuario_nombre'] ?? 'SYS', ':cod' => $cod]);
                
                // Ejecutar Update con cálculos
                $stmtUpd->execute($updateParams);
                
                $updated++;
            } catch (Exception $ex) {
                $errors++;
            }
        }

        $pdo->commit();
        fclose($handle);
        unlink($path);

        echo json_encode(['success' => true, 'message' => "Importación Inteligente completada. $updated actualizados (con recálculo de IVA/Neto). $errors errores."]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>