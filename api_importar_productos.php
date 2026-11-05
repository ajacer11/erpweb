<?php
/**
 * api_importar_productos.php - Backend para Alta Masiva de Productos
 * Funcionalidad clave: Verifica relaciones y ahora soporta múltiples listas de precios y cuentas contables.
 */

// SEGURIDAD
require_once 'check_api_access.php';

if (!isset($_GET['action']) || $_GET['action'] !== 'download_template') {
    header('Content-Type: application/json; charset=utf-8');
}


$action = $_GET['action'] ?? '';

// --- FUNCIONES HELPER ---

function getOrCreate($pdo, $table, $colName, $value) {
    $value = trim($value);
    if (empty($value)) return 0;

    $stmt = $pdo->prepare("SELECT id FROM $table WHERE $colName = ? LIMIT 1");
    $stmt->execute([$value]);
    $id = $stmt->fetchColumn();

    if ($id) return $id;

    $sqlInsert = "INSERT INTO $table ($colName";
    $sqlValues = " VALUES (?";

    if ($table === 'Marcas' || $table === 'familias') {
        $sqlInsert .= ", habilitada)"; $sqlValues .= ", 1)";
    } elseif ($table === 'rubros') {
        $sqlInsert .= ", iva, idiva)"; $sqlValues .= ", 21, 5)"; 
    } elseif (strcasecmp($table, 'Proveedores') === 0) {
        // cuentacontable es NOT NULL sin default en varias bases.
        // '0' es el centinela que el sistema ya interpreta como "sin cuenta asignada".
        $sqlInsert .= ", cuentacontable)"; $sqlValues .= ", '0')";
    } else {
        $sqlInsert .= ")"; $sqlValues .= ")";
    }

    $stmtInsert = $pdo->prepare($sqlInsert . $sqlValues);
    $stmtInsert->execute([$value]);
    return $pdo->lastInsertId();
}

/**
 * Devuelve el valor de una columna del CSV, o el default si la celda
 * no existe O viene vacia. El operador ?? solo cubre el primer caso.
 */
function campoCsv($item, $key, $default = '') {
    $v = trim((string)($item[$key] ?? ''));
    return ($v === '') ? $default : $v;
}

/** Columnas del CSV que se interpretan como numeros. */
function camposNumericosCsv() {
    $campos = ['NETO COSTO', 'ALICUOTA IVA', 'IMPUESTOS INTERNOS', 'STOCK MINIMO', 'STOCK IDEAL'];
    for ($i = 1; $i <= 7; $i++) $campos[] = "NETO VENTA L$i";
    return $campos;
}

/**
 * Determina si el archivo usa punto o coma como separador decimal.
 * Recorre todas las celdas numericas juntando evidencia; una celda
 * ambigua como "1.500" no vota. Sin evidencia devuelve punto, que es
 * el formato de la plantilla modelo que entrega el propio sistema.
 */
function detectarSeparadorDecimal($path, $delimiter, $headers) {
    $campos = camposNumericosCsv();
    $votosPunto = 0;
    $votosComa  = 0;

    $fh = @fopen($path, 'r');
    if (!$fh) return '.';
    fgetcsv($fh, 0, $delimiter); // descartar encabezado

    while (($row = fgetcsv($fh, 0, $delimiter)) !== FALSE) {
        if (count($row) != count($headers)) continue;
        $item = array_combine($headers, $row);

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
 * formato ingles.
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

try {
    // --- 1. DESCARGAR PLANTILLA MODELO ---
    if ($action === 'download_template') {
        $filename = "Modelo_Alta_Productos_Completo.csv";
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8

        // Encabezados requeridos (Ampliados)
        $headers = [
            'CODIGO', 'DESCRIPCION', 'RUBRO', 'MARCA', 'FAMILIA', 'PROVEEDOR', 
            'CODIGO PROVEEDOR', 'NETO COSTO', 'ALICUOTA IVA', 'IMPUESTOS INTERNOS', 
            'STOCK MINIMO', 'STOCK IDEAL',
            // Listas de Precios (Netos)
            'NETO VENTA L1', 'NETO VENTA L2', 'NETO VENTA L3', 'NETO VENTA L4', 
            'NETO VENTA L5', 'NETO VENTA L6', 'NETO VENTA L7',
            // Cuentas Contables (Opcionales)
            'CUENTA VENTAS', 'CUENTA COSTO', 'CUENTA MERCADERIA'
        ];
        fputcsv($output, $headers, ';');

        // Productos de Ejemplo
        $ejemplos = [
            ['PROD-001', 'Coca Cola 2.25L', 'BEBIDAS', 'COCA COLA', 'GASEOSAS', 'DISTRIBUIDORA NORTE', 'CC-225', '1500.00', '21', '0', '10', '50', '2500.00', '2600.00', '2700.00', '0', '0', '0', '0', '41001', '51001', '11005'],
            ['PROD-002', 'Arroz Gallo 1kg', 'ALMACEN', 'GALLO', 'ARROZ', 'MOLINOS RIO', 'ARG-1K', '1200.50', '10.5', '0', '20', '100', '1900.00', '0', '0', '0', '0', '0', '0', '', '', '']
        ];

        foreach ($ejemplos as $row) fputcsv($output, $row, ';');
        fclose($output);
        exit;
    }

    // --- 2. SUBIR Y PREVISUALIZAR ---
    if ($action === 'upload') {
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Error al subir archivo.");
        }

        $tmpName = $_FILES['archivo']['tmp_name'];
        $handle = fopen($tmpName, "r");
        
        $firstLine = fgets($handle);
        $delimiter = (strpos($firstLine, ';') !== false) ? ';' : ',';
        rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        $headers = array_map(function($h) { return trim(strtoupper(preg_replace('/^\xEF\xBB\xBF/', '', $h))); }, $headers);

        if (!in_array('CODIGO', $headers)) throw new Exception("Falta columna obligatoria CODIGO.");

        $preview = [];
        $count = 0;
        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if ($count < 50 && count($row) == count($headers)) {
                $preview[] = array_combine($headers, $row);
            }
            if (!empty($row[0])) $count++;
        }
        fclose($handle);

        $tempPath = sys_get_temp_dir() . '/import_new_prod_' . uniqid() . '.csv';
        move_uploaded_file($tmpName, $tempPath);

        // Formato numerico del archivo, decidido una sola vez para todo el lote.
        $sepDecimal = detectarSeparadorDecimal($tempPath, $delimiter, $headers);

        $_SESSION['csv_new_prod_path'] = $tempPath;
        $_SESSION['csv_new_prod_delim'] = $delimiter;
        $_SESSION['csv_new_prod_headers'] = $headers;
        $_SESSION['csv_new_prod_decimal'] = $sepDecimal;

        echo json_encode([
            'success' => true,
            'preview' => $preview,
            'headers' => $headers,
            'total'   => $count,
            'decimal' => $sepDecimal,
            'formato' => ($sepDecimal === ',')
                ? 'Coma decimal (1.234,56)'
                : 'Punto decimal (1234.56)'
        ]);
        exit;
    }

    // --- 3. PROCESAR IMPORTACIÓN ---
    if ($action === 'process') {
        if (!isset($_SESSION['csv_new_prod_path'])) throw new Exception("Sesión expirada.");

        $path = $_SESSION['csv_new_prod_path'];
        $delimiter = $_SESSION['csv_new_prod_delim'];
        $headers = $_SESSION['csv_new_prod_headers'];
        
        $sepDecimal = $_SESSION['csv_new_prod_decimal']
            ?? detectarSeparadorDecimal($path, $delimiter, $headers);

        $handle = fopen($path, "r");
        fgetcsv($handle, 0, $delimiter);

        $pdo->beginTransaction();
        $imported = 0;
        $errors = [];

        // Insert SQL ampliado para todas las listas y cuentas
        $sqlInsert = "INSERT INTO alb_productos 
            (codigo, descripcion, rubro, marca, familia, proveedorID, Proveedor, codigoproveedor,
             sinivacosto, ivacosto, impint, preciocosto, 
             poriva, idiva, habilitado, controlastock, StockMin, stockmaximo,
             siniva, iva1, precio, 
             siniva2, iva2, precio2, siniva3, iva3, precio3, siniva4, iva4, precio4,
             siniva5, iva5, precio5, siniva6, iva6, precio6, siniva7, iva7, precio7,
             cuentaventas, cuentacosto, cuentamercaderia)
            VALUES 
            (:cod, :desc, :rub, :mar, :fam, :provId, :provName, :codProv,
             :nc, :ic, :ii, :tc,
             :piva, :idiva, 1, 1, :stkMin, :stkIdeal,
             :n1, :i1, :p1, :n2, :i2, :p2, :n3, :i3, :p3, :n4, :i4, :p4,
             :n5, :i5, :p5, :n6, :i6, :p6, :n7, :i7, :p7,
             :ctaVenta, :ctaCosto, :ctaMerc
            )";
        
        $stmtInsert = $pdo->prepare($sqlInsert);
        $stmtRef = $pdo->prepare("INSERT INTO referenciasproductos (codigo, refierea) VALUES (?, ?)");

        $clean = function($val) use ($sepDecimal) {
            return limpiarNumero($val, $sepDecimal);
        };

        while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
            if (count($row) != count($headers)) continue;
            $item = array_combine($headers, $row);
            
            $cod = trim($item['CODIGO'] ?? '');
            if (!$cod) continue;

            try {
                // Verificar existencia
                $chk = $pdo->prepare("SELECT id FROM alb_productos WHERE codigo = ?");
                $chk->execute([$cod]);
                if ($chk->fetch()) throw new Exception("El código ya existe.");

                // IDs Relacionales
                // campoCsv aplica el default tambien cuando la celda viene vacia,
                // no solo cuando falta la columna.
                $rubroName = campoCsv($item, 'RUBRO', 'VARIOS');
                $marcaName = campoCsv($item, 'MARCA', 'GENERICA');
                $famName   = campoCsv($item, 'FAMILIA', 'GENERAL');
                $provName  = campoCsv($item, 'PROVEEDOR', 'VARIOS');

                getOrCreate($pdo, 'rubros', 'rubro', $rubroName); // Asegurar existencia
                $marcaId = getOrCreate($pdo, 'Marcas', 'marca', $marcaName);
                $famId = getOrCreate($pdo, 'familias', 'nombre', $famName);
                $provId = getOrCreate($pdo, 'Proveedores', 'Razon', $provName);

                // Costos e Impuestos
                $netoCosto = $clean($item['NETO COSTO'] ?? 0);
                $porIva = $clean($item['ALICUOTA IVA'] ?? 21);
                // Una columna corrida del Excel puede traer un precio en la alícuota (ej. 31500): se rechaza la fila
                if (!in_array((float) $porIva, [0.0, 2.5, 5.0, 10.5, 21.0, 27.0], true)) {
                    throw new Exception("Alícuota de IVA inválida ({$porIva}). Valores permitidos: 0, 2.5, 5, 10.5, 21, 27.");
                }
                $impInt = $clean($item['IMPUESTOS INTERNOS'] ?? 0);
                $ivaCosto = $netoCosto * ($porIva / 100);
                $totalCosto = $netoCosto + $ivaCosto + $impInt;

                // ID IVA legacy
                $idIva = 5; 
                if ($porIva == 10.5) $idIva = 4;
                elseif ($porIva == 27) $idIva = 6;
                elseif ($porIva == 0) $idIva = 3;
                elseif ($porIva == 5) $idIva = 8;
                elseif ($porIva == 2.5) $idIva = 9;

                // --- PROCESAMIENTO DE 7 LISTAS DE PRECIOS ---
                $precios = [];
                for ($i = 1; $i <= 7; $i++) {
                    $neto = $clean($item["NETO VENTA L$i"] ?? 0);
                    // Si el neto es 0 y es la lista 1, quizás deberíamos forzar algo, pero asumimos 0.
                    // Si listas 2-7 son 0, quedan en 0.
                    $iva = $neto * ($porIva / 100);
                    $final = $neto + $iva + $impInt;
                    
                    $precios["n$i"] = $neto;
                    $precios["i$i"] = $iva;
                    $precios["p$i"] = $final;
                }

                $stmtInsert->execute([
                    ':cod' => $cod,
                    ':desc' => trim($item['DESCRIPCION'] ?? ''),
                    ':rub' => $rubroName,
                    ':mar' => $marcaId,
                    ':fam' => $famId,
                    ':provId' => $provId,
                    ':provName' => $provName,
                    ':codProv' => trim($item['CODIGO PROVEEDOR'] ?? ''),
                    ':nc' => $netoCosto,
                    ':ic' => $ivaCosto,
                    ':ii' => $impInt,
                    ':tc' => $totalCosto,
                    ':piva' => $porIva,
                    ':idiva' => $idIva,
                    ':stkMin' => $clean($item['STOCK MINIMO'] ?? 0),
                    ':stkIdeal' => $clean($item['STOCK IDEAL'] ?? 0),
                    
                    // Listas 1 a 7
                    ':n1' => $precios['n1'], ':i1' => $precios['i1'], ':p1' => $precios['p1'],
                    ':n2' => $precios['n2'], ':i2' => $precios['i2'], ':p2' => $precios['p2'],
                    ':n3' => $precios['n3'], ':i3' => $precios['i3'], ':p3' => $precios['p3'],
                    ':n4' => $precios['n4'], ':i4' => $precios['i4'], ':p4' => $precios['p4'],
                    ':n5' => $precios['n5'], ':i5' => $precios['i5'], ':p5' => $precios['p5'],
                    ':n6' => $precios['n6'], ':i6' => $precios['i6'], ':p6' => $precios['p6'],
                    ':n7' => $precios['n7'], ':i7' => $precios['i7'], ':p7' => $precios['p7'],

                    // Cuentas Contables (Strings vacíos si no vienen)
                    ':ctaVenta' => trim($item['CUENTA VENTAS'] ?? ''),
                    ':ctaCosto' => trim($item['CUENTA COSTO'] ?? ''),
                    ':ctaMerc' => trim($item['CUENTA MERCADERIA'] ?? '')
                ]);

                $stmtRef->execute([$cod, $cod]);
                $imported++;

            } catch (Exception $ex) {
                $errors[] = "Código $cod: " . $ex->getMessage();
            }
        }

        $pdo->commit();
        fclose($handle);
        unlink($path);

        echo json_encode(['success' => true, 'message' => "Importados: $imported.", 'errors' => $errors]);
        exit;
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>