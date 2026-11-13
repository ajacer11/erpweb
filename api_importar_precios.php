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

// --- FUNCIONES HELPER ---

/** Columnas del CSV que se interpretan como numeros. */
function camposNumericosPrecios() {
    $campos = ['NETO COSTO', 'IMP INT', 'IVA COSTO', 'TOTAL COSTO'];
    for ($i = 1; $i <= 8; $i++) $campos[] = "LISTA $i FINAL";
    return $campos;
}

/**
 * Determina si el archivo usa punto o coma como separador decimal.
 * Recorre todas las celdas numericas juntando evidencia; una celda
 * ambigua como "1.500" no vota. Sin evidencia devuelve punto.
 * Misma logica que api_importar_productos.php.
 */
function detectarSeparadorDecimal($path, $delimiter, $headers) {
    $campos = camposNumericosPrecios();
    $votosPunto = 0;
    $votosComa  = 0;

    $fh = @fopen($path, 'r');
    if (!$fh) return '.';
    fgetcsv($fh, 0, $delimiter); // descartar encabezado

    while (($row = fgetcsv($fh, 0, $delimiter)) !== FALSE) {
        if (count($row) < count($headers)) $row = array_pad($row, count($headers), "");
        $item = array_combine($headers, array_slice($row, 0, count($headers)));

        foreach ($campos as $c) {
            $v = trim((string)($item[$c] ?? ''));
            if ($v === '' || !preg_match('/^[-+]?[0-9.,]+$/', $v)) continue;

            $posPunto = strrpos($v, '.');
            $posComa  = strrpos($v, ',');

            // Conviven los dos: el que esta mas a la derecha es el decimal.
            if ($posPunto !== false && $posComa !== false) {
                if ($posPunto > $posComa) $votosPunto += 10; else $votosComa += 10;
                continue;
            }
            // Un solo tipo de separador presente.
            if ($posPunto !== false) {
                if (substr_count($v, '.') > 1) { $votosComa++; continue; }   // 1.234.567 => miles
                if (strlen(substr($v, $posPunto + 1)) !== 3) $votosPunto++;  // .5 / .50 / .5000 => decimal
            } elseif ($posComa !== false) {
                if (substr_count($v, ',') > 1) { $votosPunto++; continue; }
                if (strlen(substr($v, $posComa + 1)) !== 3) $votosComa++;
            }
        }
    }
    fclose($fh);

    return ($votosComa > $votosPunto) ? ',' : '.';
}

/**
 * Convierte texto a numero respetando el separador decimal detectado.
 * Reemplaza a la version anterior, que borraba todo punto y por lo tanto
 * multiplicaba por 10, 100 o 1000 cualquier valor con decimales en
 * formato ingles (1234.56 => 123456).
 */
function limpiarNumero($val, $decimal = '.') {
    $val = trim((string)$val);
    if ($val === '') return 0.0;

    $val = preg_replace('/[^0-9.,+\-]/', '', $val);
    if ($val === '') return 0.0;

    $miles = ($decimal === '.') ? ',' : '.';
    $val = str_replace($miles, '', $val);
    if ($decimal !== '.') $val = str_replace($decimal, '.', $val);

    // Si sobrevivio mas de un punto, los previos al ultimo eran miles.
    if (substr_count($val, '.') > 1) {
        $ult = strrpos($val, '.');
        $val = str_replace('.', '', substr($val, 0, $ult)) . substr($val, $ult);
    }
    return floatval($val);
}

/**
 * Excel destroza los codigos al abrir un CSV: un EAN de 13 digitos queda como
 * 7,79814E+12 y "0012" pierde los ceros. Al guardar, ese codigo ya no coincide
 * con alb_productos y el UPDATE no afecta ninguna fila. Envolverlo en ="..."
 * obliga a Excel a tratar la celda como texto y a devolverla intacta al grabar.
 */
function codigoParaExcel($codigo) {
    $codigo = (string)$codigo;
    if ($codigo === '') return '';
    return '="' . str_replace('"', '""', $codigo) . '"';
}

/** Saca el envoltorio ="..." por si el archivo no paso por Excel. */
function normalizarCodigo($val) {
    $val = trim((string)$val);
    if ($val === '') return '';
    if (preg_match('/^="(.*)"$/s', $val, $m)) $val = str_replace('""', '"', $m[1]);
    return trim($val);
}

/** Detecta 7,79814E+12: Excel ya perdio los digitos, el codigo es irrecuperable. */
function pareceNotacionCientifica($val) {
    return (bool)preg_match('/^[-+]?[0-9]+([.,][0-9]+)?E[-+]?[0-9]+$/i', trim((string)$val));
}

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
                codigoParaExcel($p['codigo']), $p['descripcion'],
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
            $cod = normalizarCodigo($row[$idxCod] ?? '');
            if ($cod !== '') $codigos[] = $cod;
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
                    codigoParaExcel($p['codigo']), $p['descripcion'],
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
        // Excel (.xls / .xlsx): se convierte a CSV con ';' y punto decimal, y sigue el mismo flujo que un CSV
        $ext = strtolower(pathinfo($_FILES['archivo']['name'] ?? '', PATHINFO_EXTENSION));
        if (in_array($ext, ['xls', 'xlsx'], true)) {
            require_once __DIR__ . '/vendor/autoload.php';
            try {
                $filas = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpName)->getActiveSheet()->toArray(null, true, false, false);
            } catch (\Throwable $e) {
                throw new Exception("No se pudo leer el archivo Excel. Verifique que no esté dañado o guárdelo como CSV.");
            }
            $fhCsv = fopen($tmpName, 'w');
            foreach ($filas as $fila) {
                fputcsv($fhCsv, array_map(fn($v) => $v === null ? '' : (string) $v, $fila), ';');
            }
            fclose($fhCsv);
        } elseif ($ext !== 'csv') {
            throw new Exception("Formato no soportado. Use un archivo .csv, .xls o .xlsx.");
        }
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
                // Mostrar el código ya normalizado: es el que se va a buscar en
                // el padrón, así el usuario ve en la grilla lo que realmente se
                // va a aplicar y no el envoltorio ="..." de la plantilla.
                $item['CODIGO'] = normalizarCodigo($item['CODIGO'] ?? '');
                if ($item['CODIGO'] !== '') $preview[] = $item;
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

        // El archivo puede volver de Excel con decimales argentinos (1.234,56) o
        // ingleses (1234.56). Se decide una sola vez, mirando todo el archivo.
        $decimal = detectarSeparadorDecimal($path, $delimiter, $headers);

        $handle = fopen($path, "r");
        fgetcsv($handle, 0, $delimiter); // Saltar header

        $pdo->beginTransaction();

        // 1. Histórico (Log): copia completa del producto, igual que api_productos.php.
        // Con sql_mode estricto (VPS) las columnas NOT NULL sin default (ej. cuentaventas) deben enviarse.
        $stmtHist = $pdo->prepare("INSERT INTO historicoproductos (cuentaventas, cuentacosto, cuentamercaderia, idiva, codigo, descripcion, rubro, poriva, ImpInt, siniva, iva1, precio, siniva2, iva2, precio2, siniva3, iva3, precio3, siniva4, iva4, precio4, sinivacosto, ivacosto, preciocosto, habilitado, Stock, StockMin, IIBB1, IIBB2, IIBB3, IIBB4, Proveedor, siniva5, siniva6, siniva7, siniva8, iva5, iva6, iva7, iva8, IIBB5, IIBB6, IIBB7, IIBB8, precio5, precio6, precio7, precio8, idgrupoajuste, Marca, proveedorID, familia, maxdescl8, maxdescl7, maxdescl6, maxdescl5, maxdescl4, maxdescl3, maxdescl2, maxdescl1, registrastock, usuariomodif, fechamodif)
                                   SELECT cuentaventas, cuentacosto, cuentamercaderia, idiva, codigo, descripcion, rubro, poriva, ImpInt, siniva, iva1, precio, siniva2, iva2, precio2, siniva3, iva3, precio3, siniva4, iva4, precio4, sinivacosto, ivacosto, preciocosto, habilitado, Stock, StockMin, IIBB1, IIBB2, IIBB3, IIBB4, Proveedor, siniva5, siniva6, siniva7, siniva8, iva5, iva6, iva7, iva8, IIBB5, IIBB6, IIBB7, IIBB8, precio5, precio6, precio7, precio8, idgrupoajuste, Marca, proveedorID, familia, maxdescl8, maxdescl7, maxdescl6, maxdescl5, maxdescl4, maxdescl3, maxdescl2, maxdescl1, controlastock, :user, CURRENT_DATE
                                   FROM alb_productos WHERE codigo = :cod");

        // 2. Obtener datos actuales (IVA %) para cálculos.
        // También sirve de comprobación de existencia: si el código no está en el
        // padrón el UPDATE no afecta ninguna fila, y antes esa fila se contaba
        // igual como "actualizada". De ahí que el resumen diera OK sin que la
        // base cambiara nada.
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

        $actualizados = 0; $sinCambios = 0; $errors = 0; $sinCodigo = 0;
        $noEncontrados = 0; $muestraNoEncontrados = []; $errorDetails = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            // Excel recorta las columnas vacías del final. Completar es lo mismo
            // que hace la previsualización; descartar la fila hacía que el
            // resumen contara filas que nunca se intentaron.
            if (count($row) < count($headers)) $row = array_pad($row, count($headers), "");
            $item = array_combine($headers, array_slice($row, 0, count($headers)));

            $codCrudo = $item['CODIGO'] ?? '';
            $cod = normalizarCodigo($codCrudo);
            if ($cod === '') { $sinCodigo++; continue; }

            // Valores de Costo (estos se toman literales del Excel)
            $nc = limpiarNumero($item['NETO COSTO'] ?? 0, $decimal);
            $ii = limpiarNumero($item['IMP INT'] ?? 0, $decimal); // Impuesto interno
            $ic = limpiarNumero($item['IVA COSTO'] ?? 0, $decimal);
            $tc = limpiarNumero($item['TOTAL COSTO'] ?? 0, $decimal);

            // Valores de Venta Final (del Excel)
            $preciosFinales = [];
            for($i=1; $i<=8; $i++) {
                $preciosFinales[$i] = limpiarNumero($item["LISTA $i FINAL"] ?? 0, $decimal);
            }

            try {
                // Obtener IVA del producto para ingeniería inversa
                $stmtGetTax->execute([':cod' => $cod]);
                $taxData = $stmtGetTax->fetch(PDO::FETCH_ASSOC);

                if (!$taxData) {
                    $noEncontrados++;
                    if (count($muestraNoEncontrados) < 15) {
                        $muestraNoEncontrados[] = pareceNotacionCientifica($codCrudo)
                            ? "$cod (Excel lo convirtió a notación científica y perdió los dígitos)"
                            : $cod;
                    }
                    continue;
                }

                $porIva = floatval($taxData['poriva']);

                // Divisor de IVA (ej: 1.21)
                $divIva = 1 + ($porIva / 100);
                if ($divIva <= 0) $divIva = 1; // poriva corrupto: no dividir por cero

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

                // rowCount() son filas modificadas: 0 significa que el archivo
                // traía exactamente los valores que ya estaban guardados.
                if ($stmtUpd->rowCount() > 0) $actualizados++; else $sinCambios++;
            } catch (Exception $ex) {
                $errors++;
                if (count($errorDetails) < 5) $errorDetails[] = "$cod: " . $ex->getMessage();
                error_log("api_importar_precios.php: fallo al actualizar $cod: " . $ex->getMessage());
            }
        }

        $pdo->commit();
        fclose($handle);
        unlink($path);
        unset($_SESSION['csv_path'], $_SESSION['csv_delimiter'], $_SESSION['csv_headers']);

        $partes = ["$actualizados productos actualizados (con recálculo de IVA/Neto)."];
        if ($sinCambios > 0)    $partes[] = "$sinCambios ya tenían esos mismos valores.";
        if ($noEncontrados > 0) $partes[] = "$noEncontrados códigos no existen en el padrón: esas filas NO se aplicaron.";
        if ($sinCodigo > 0)     $partes[] = "$sinCodigo filas sin código.";
        $partes[] = "$errors errores.";

        $message = "Importación Inteligente completada. " . implode(' ', $partes);
        if ($muestraNoEncontrados) {
            $message .= "\n\nCódigos no encontrados:\n" . implode("\n", $muestraNoEncontrados);
            if ($noEncontrados > count($muestraNoEncontrados)) {
                $message .= "\n… y " . ($noEncontrados - count($muestraNoEncontrados)) . " más.";
            }
            $message .= "\n\nSi los códigos se ven como 7,79814E+12, Excel los convirtió a número al abrir el CSV. Descargá de nuevo la planilla modelo: ahora viene protegida para que Excel los respete.";
        }
        if ($errorDetails) $message .= "\n\nPrimeros errores:\n" . implode("\n", $errorDetails);

        echo json_encode([
            'success' => true,
            'message' => $message,
            'actualizados' => $actualizados,
            'sin_cambios' => $sinCambios,
            'no_encontrados' => $noEncontrados,
            'sin_codigo' => $sinCodigo,
            'errores' => $errorDetails,
            'codigos_no_encontrados' => $muestraNoEncontrados
        ]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>