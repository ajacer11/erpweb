<?php
/**
 * api_transportes_import.php - LÓGICA DE NEGOCIO PARA ALTA MASIVA DE TRANSPORTES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

// Función para capitalizar nombres (ProperCase)
function properCase($text) {
    if(empty($text)) return '';
    return mb_convert_case(mb_strtolower(trim($text)), MB_CASE_TITLE, "UTF-8");
}

try {
    switch ($action) {
        case 'download_template':
            $filename = "plantilla_importar_transportes.csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            
            $output = fopen('php://output', 'w');
            fwrite($output, "sep=;\n"); // Indicador de separador para Excel
            
            // Cabeceras
            $headers = ["Razon Social", "CUIT o DNI", "Domicilio", "Localidad", "Provincia", "Codigo Postal", "Telefono", "Email", "Contacto", "Horario"];
            fputcsv($output, $headers, ";");
            
            // Ejemplo
            fputcsv($output, ["EXPRESO LOGISTICA S.A.", "30712233445", "AV. INDEPENDENCIA 4500", "ROSARIO", "SANTA FE", "2000", "0341-4556677", "info@expreso.com", "JUAN PEREZ", "08:00 a 18:00"], ";");
            
            fclose($output);
            exit;

        case 'upload':
            if (!isset($_FILES['archivo'])) throw new Exception("No se recibió el archivo.");
            
            $file = $_FILES['archivo']['tmp_name'];
            $handle = fopen($file, "r");
            
            // Detección de delimitador (sep= o automático)
            $firstLine = fgets($handle);
            $delimiter = ";"; 
            if (strpos($firstLine, 'sep=') === 0) {
                $delimiter = trim(str_replace('sep=', '', $firstLine));
                $headersRaw = fgetcsv($handle, 1000, $delimiter);
            } else {
                rewind($handle);
                $headersRaw = fgetcsv($handle, 1000, ";");
                if (!$headersRaw || count($headersRaw) < 2) {
                    rewind($handle);
                    $headersRaw = fgetcsv($handle, 1000, ",");
                    $delimiter = ",";
                }
            }

            if (!$headersRaw) throw new Exception("El archivo está vacío o tiene un formato no válido.");

            // Limpieza de caracteres BOM y espacios
            $headers = array_map(function($h) {
                $h = str_replace(["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"], '', $h);
                return trim($h, " \t\n\r\0\x0B\"");
            }, $headersRaw);

            // Validar columnas requeridas
            $required = ["Razon Social", "CUIT o DNI"];
            foreach($required as $r) {
                if(!in_array($r, $headers)) throw new Exception("Falta la columna requerida: '$r'.");
            }

            $preview = [];
            $has_errors = false;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if (count($data) < count($headers)) continue;
                
                $row = array_combine($headers, array_slice($data, 0, count($headers)));
                $row['_errors'] = [];
                $row['_row_error'] = false;

                // Validación Razón Social
                if(empty(trim($row["Razon Social"] ?? ''))) {
                    $row['_errors']["Razon Social"] = "Requerido";
                    $row['_row_error'] = true;
                }

                // Validación Documento
                $doc = trim($row["CUIT o DNI"] ?? '');
                if(empty($doc) || !is_numeric($doc) || strlen($doc) < 7) {
                    $row['_errors']["CUIT o DNI"] = "Inválido";
                    $row['_row_error'] = true;
                }

                if($row['_row_error']) $has_errors = true;
                $preview[] = $row;
            }
            fclose($handle);

            // Guardar en sesión para el paso final
            $_SESSION['pending_transp_import'] = $preview;

            echo json_encode([
                'success' => true, 
                'headers' => $headers, 
                'preview' => $preview, 
                'total' => count($preview),
                'has_errors' => $has_errors
            ]);
            break;

        case 'process':
            $data = $_SESSION['pending_transp_import'] ?? [];
            if(empty($data)) throw new Exception("No hay datos cargados para procesar.");

            $pdo->beginTransaction();
            
            // Preparar consultas
            $stmtCheck = $pdo->prepare("SELECT id FROM transportes WHERE numdoc = ? LIMIT 1");
            
            $sqlInsert = "INSERT INTO transportes (razon, numdoc, domicilio, localidad, provincia, cp, telefono, mail, contacto, horario, tipodoc, habilitado, condicionfiscal) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'Responsable Inscripto')";
            $stmtInsert = $pdo->prepare($sqlInsert);

            $success_count = 0;
            $duplicate_count = 0;

            foreach($data as $row) {
                $numdoc = trim($row['CUIT o DNI'] ?? '');
                
                // Verificar duplicados por documento
                $stmtCheck->execute([$numdoc]);
                if($stmtCheck->fetch()) {
                    $duplicate_count++;
                    continue;
                }

                // Determinar tipo de documento (DNI o CUIT)
                $tipoDoc = (strlen($numdoc) > 8) ? "CUIT" : "DNI";

                $stmtInsert->execute([
                    properCase($row['Razon Social']),
                    $numdoc,
                    properCase($row['Domicilio'] ?? 'S/D'),
                    properCase($row['Localidad'] ?? 'S/L'),
                    properCase($row['Provincia'] ?? 'MENDOZA'),
                    $row['Codigo Postal'] ?? '',
                    $row['Telefono'] ?? '',
                    mb_strtolower($row['Email'] ?? ''),
                    properCase($row['Contacto'] ?? ''),
                    $row['Horario'] ?? '',
                    $tipoDoc
                ]);
                $success_count++;
            }

            $pdo->commit();
            unset($_SESSION['pending_transp_import']);

            $msg = "Proceso finalizado. Importados: $success_count. Omitidos (ya existentes): $duplicate_count.";
            echo json_encode(['success' => true, 'message' => $msg]);
            break;
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}