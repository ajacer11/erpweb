<?php
/**
 * api_importar_clientes.php - LÓGICA DE NEGOCIO PARA ALTA MASIVA
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'download_template':
            $filename = "plantilla_importar_clientes.csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            $output = fopen('php://output', 'w');
            
            // Instrucción especial para que Excel detecte el separador automáticamente
            // Quitamos el BOM aquí para evitar que se desplace a la segunda línea
            fwrite($output, "sep=;\n");
            
            // Usamos nombres sin tildes para evitar errores de codificación
            fputcsv($output, ["Nombre o Razon Social", "Tipo Documento", "Numero de CUIT", "Condicion Fiscal", "Domicilio", "Localidad", "Provincia", "Codigo Postal"], ";");
            fputcsv($output, ["CLIENTE EJEMPLO S.A.", "CUIT", "30112233445", "Responsable Inscripto", "CALLE 123", "MENDOZA", "MENDOZA", "5500"], ";");
            fclose($output);
            exit;

        case 'upload':
            if (!isset($_FILES['archivo'])) throw new Exception("No se recibió el archivo.");
            
            $file = $_FILES['archivo']['tmp_name'];
            $handle = fopen($file, "r");
            
            // 1. Detección de Delimitador
            $firstLine = fgets($handle);
            $delimiter = ";"; 
            
            if (strpos($firstLine, 'sep=') === 0) {
                $delimiter = trim(str_replace('sep=', '', $firstLine));
                // El siguiente fgetcsv leerá la línea de cabeceras
                $headersRaw = fgetcsv($handle, 1000, $delimiter);
            } else {
                // Si no tiene sep=, reintentamos desde el principio
                rewind($handle);
                $headersRaw = fgetcsv($handle, 1000, ";");
                if (!$headersRaw || count($headersRaw) < 2) {
                    rewind($handle);
                    $headersRaw = fgetcsv($handle, 1000, ",");
                    $delimiter = ",";
                }
            }

            if (!$headersRaw) throw new Exception("No se pudieron leer las cabeceras del archivo.");

            // 2. Limpieza profunda de cabeceras
            // Removemos BOM, caracteres de control y espacios invisibles
            $headers = array_map(function($h) {
                // Eliminar BOM si existe (UTF-8, UTF-16 BE/LE)
                $h = str_replace(["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"], '', $h);
                // Limpiar espacios y comillas accidentales
                return trim($h, " \t\n\r\0\x0B\"");
            }, $headersRaw);

            // 3. Validar estructura de columnas
            $req = ["Nombre o Razon Social", "Numero de CUIT", "Condicion Fiscal", "Tipo Documento"];
            foreach($req as $r) {
                if(!in_array($r, $headers)) {
                    throw new Exception("Falta la columna requerida: $r. Cabeceras detectadas: " . implode(', ', $headers));
                }
            }

            $preview = [];
            $has_errors = false;
            $valid_fisc = ["RESPONSABLE INSCRIPTO", "MONOTRIBUTO", "CONSUMIDOR FINAL", "EXENTO"];

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if (count($data) < count($headers)) continue;
                
                // Combinar usando las cabeceras limpias
                $row = array_combine($headers, array_slice($data, 0, count($headers)));
                $row['_errors'] = [];
                $row['_row_error'] = false;

                // Regla 1: Razón Social obligatoria
                if(empty(trim($row["Nombre o Razon Social"] ?? ''))) {
                    $row['_errors']["Nombre o Razon Social"] = true;
                    $row['_row_error'] = true;
                }

                // Regla 2: Condición Fiscal válida
                $cf = strtoupper(trim($row["Condicion Fiscal"] ?? ''));
                if(empty($cf) || !in_array($cf, $valid_fisc)) {
                    $row['_errors']["Condicion Fiscal"] = true;
                    $row['_row_error'] = true;
                }

                // Regla 3: Si no es Consumidor Final, CUIT/Domicilio/Localidad/Provincia obligatorios
                if($cf !== "CONSUMIDOR FINAL") {
                    $checkFields = ["Numero de CUIT", "Domicilio", "Localidad", "Provincia"];
                    foreach($checkFields as $c) {
                        if(empty(trim($row[$c] ?? ''))) {
                            $row['_errors'][$c] = true;
                            $row['_row_error'] = true;
                        }
                    }
                }

                if($row['_row_error']) $has_errors = true;
                $preview[] = $row;
                if(count($preview) >= 500) break; // Límite para preview
            }
            fclose($handle);

            // Guardar en sesión para el proceso real
            $_SESSION['pending_clients_import'] = $preview;

            echo json_encode([
                'success' => true, 
                'headers' => $headers, 
                'preview' => $preview, 
                'total' => count($preview),
                'has_errors' => $has_errors
            ]);
            break;

        case 'process':
            $data = $_SESSION['pending_clients_import'] ?? [];
            if(empty($data)) throw new Exception("No hay datos para procesar.");

            $pdo->beginTransaction();
            $stmtCheck = $pdo->prepare("SELECT id FROM alb_clientes WHERE numdoc = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO alb_clientes (razon, tipodoc, numdoc, condicionfiscal, domicilio, localidad, provincia, cp, habilitado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");

            $success_count = 0;
            foreach($data as $row) {
                // Verificar duplicados por CUIT/DNI
                $numdoc = trim($row['Numero de CUIT'] ?? '');
                if (empty($numdoc)) $numdoc = '99999999'; // Consumidor final genérico si aplica

                $stmtCheck->execute([$numdoc]);
                if($stmtCheck->fetch()) continue; // Saltear si ya existe (como en VB.NET)

                $stmtInsert->execute([
                    $row['Nombre o Razon Social'],
                    $row['Tipo Documento'] ?? 'DNI',
                    $numdoc,
                    $row['Condicion Fiscal'],
                    $row['Domicilio'] ?? '',
                    $row['Localidad'] ?? '',
                    $row['Provincia'] ?? '',
                    $row['Codigo Postal'] ?? ''
                ]);
                $success_count++;
            }

            $pdo->commit();
            unset($_SESSION['pending_clients_import']);
            echo json_encode(['success' => true, 'message' => "Se importaron $success_count clientes correctamente."]);
            break;
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}