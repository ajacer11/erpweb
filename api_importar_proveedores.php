<?php
/**
 * api_importar_proveedores.php - LÓGICA DE NEGOCIO PARA ALTA MASIVA DE PROVEEDORES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'download_template':
            $filename = "plantilla_importar_proveedores.csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename);
            $output = fopen('php://output', 'w');
            fwrite($output, "sep=;\n");
            
            // Cabeceras según VB.NET: Nombre O Razón Social, Número de CUIT, Domicilio, Localidad, Provincia, Codigo Postal
            fputcsv($output, ["Nombre O Razon Social", "Numero de CUIT", "Domicilio", "Localidad", "Provincia", "Codigo Postal"], ";");
            fputcsv($output, ["PROVEEDOR EJEMPLO S.A.", "30112233445", "CALLE FALSA 123", "MENDOZA", "MENDOZA", "5500"], ";");
            fclose($output);
            exit;

        case 'upload':
            if (!isset($_FILES['archivo'])) throw new Exception("No se recibió el archivo.");
            
            $file = $_FILES['archivo']['tmp_name'];
            $handle = fopen($file, "r");
            
            // Detección de Delimitador
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

            if (!$headersRaw) throw new Exception("No se pudieron leer las cabeceras.");

            // Limpieza de cabeceras
            $headers = array_map(function($h) {
                $h = str_replace(["\xEF\xBB\xBF", "\xFE\xFF", "\xFF\xFE"], '', $h);
                return trim($h, " \t\n\r\0\x0B\"");
            }, $headersRaw);

            // Validar estructura básica
            $req = ["Nombre O Razon Social", "Numero de CUIT"];
            foreach($req as $r) {
                if(!in_array($r, $headers)) {
                    throw new Exception("Falta la columna requerida: $r.");
                }
            }

            $preview = [];
            $has_errors = false;

            while (($data = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                if (count($data) < count($headers)) continue;
                
                $row = array_combine($headers, array_slice($data, 0, count($headers)));
                $row['_errors'] = [];
                $row['_row_error'] = false;

                // Validación Razón Social
                if(empty(trim($row["Nombre O Razon Social"] ?? ''))) {
                    $row['_errors']["Nombre O Razon Social"] = true;
                    $row['_row_error'] = true;
                }

                // Validación CUIT (Numérico y largo)
                $cuit = trim($row["Numero de CUIT"] ?? '');
                if(empty($cuit) || !is_numeric($cuit) || strlen($cuit) > 11) {
                    $row['_errors']["Numero de CUIT"] = true;
                    $row['_row_error'] = true;
                }

                if($row['_row_error']) $has_errors = true;
                $preview[] = $row;
            }
            fclose($handle);

            $_SESSION['pending_prov_import'] = $preview;

            echo json_encode([
                'success' => true, 
                'headers' => $headers, 
                'preview' => $preview, 
                'total' => count($preview),
                'has_errors' => $has_errors
            ]);
            break;

        case 'process':
            $data = $_SESSION['pending_prov_import'] ?? [];
            if(empty($data)) throw new Exception("No hay datos para procesar.");

            $pdo->beginTransaction();
            $stmtCheck = $pdo->prepare("SELECT Id FROM Proveedores WHERE cuit = ?");
            $stmtInsert = $pdo->prepare("INSERT INTO Proveedores (Razon, domicilio, localidad, provincia, cp, tipodoc, cuit, habilitado) VALUES (?, ?, ?, ?, ?, 'CUIT', ?, 1)");

            $success_count = 0;
            foreach($data as $row) {
                $cuit = trim($row['Numero de CUIT'] ?? '');
                
                $stmtCheck->execute([$cuit]);
                if($stmtCheck->fetch()) continue; // Saltear duplicados como en VB.NET

                $stmtInsert->execute([
                    $row['Nombre O Razon Social'],
                    $row['Domicilio'] ?? 'Sin Informar',
                    $row['Localidad'] ?? 'Sin Informar',
                    $row['Provincia'] ?? 'Mendoza',
                    $row['Codigo Postal'] ?? 'Sin Informar',
                    $cuit
                ]);
                $success_count++;
            }

            $pdo->commit();
            unset($_SESSION['pending_prov_import']);
            echo json_encode(['success' => true, 'message' => "Se importaron $success_count proveedores correctamente."]);
            break;
    }
} catch (Exception $e) {
    if(isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}