<?php
/**
 * api_configuracion.php - BACKEND DE CONFIGURACIÓN GENERAL
 * AHORA SIEMPRE INSERTA UN NUEVO REGISTRO (HISTÓRICO)
 * La lectura sigue tomando el último (MAX id)
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

try {
    switch ($action) {

        case 'get_config':
            // Obtener el ÚLTIMO registro (histórico activo)
            $stmt = $pdo->query("SELECT * FROM configuracion ORDER BY id DESC LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                echo json_encode(['success' => true, 'data' => null]);
                exit;
            }

            // Procesar Logo (BLOB a Base64)
            $logoBase64 = null;
            if (!empty($config['logo'])) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($config['logo']) ?: 'image/png';
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode($config['logo']);
            }
            unset($config['logo']);
            $config['logo_preview'] = $logoBase64;

            // Nombre del cliente predeterminado
            $clienteNombre = '';
            if (!empty($config['clientepredeterminado'])) {
                $stmtCli = $pdo->prepare("SELECT razon FROM alb_clientes WHERE id = ?");
                $stmtCli->execute([$config['clientepredeterminado']]);
                $clienteNombre = $stmtCli->fetchColumn();
            }
            $config['clientepredeterminado_nombre'] = $clienteNombre;

            echo json_encode(['success' => true, 'data' => $config]);
            break;

        case 'save_config':
            // ---------------------------------------------------------------
            // SIEMPRE INSERTAMOS UN NUEVO REGISTRO (HISTÓRICO)
            // ---------------------------------------------------------------

            // Campos numéricos / texto
            $montominimo        = (float)($_POST['montominimo']   ?? 0);
            $maximoajustes      = (int)($_POST['maximoajustes']   ?? 10);
            $tipoventa          = (int)($_POST['tipoventa']       ?? 1);
            $poriibb            = (float)($_POST['poriibb']       ?? 0);
            $tipodescuentoindividual = (int)($_POST['tipodescuentoindividual'] ?? 1);
            $clientepredeterminado   = (int)($_POST['clientepredeterminado']  ?? 0);
            $predeterminarcomprobante = (int)($_POST['predeterminarcomprobante'] ?? 0);

            $fechastock         = !empty($_POST['fechastock']) ? $_POST['fechastock'] : null;
            $texboxpredeterminado = strtoupper($_POST['texboxpredeterminado'] ?? 'CANTIDAD');
            $leyenda2           = $_POST['leyenda2']           ?? '';
            $formatoimpresion   = $_POST['formatoimpresion']   ?? 'A4';

            // Checkboxes (se envían como '1' o '0' desde el frontend)
            $stocknegativo              = ($_POST['stocknegativo']              ?? '0') === '1' ? 1 : 0;
            $retgan                     = ($_POST['retgan']                     ?? '0') === '1' ? 1 : 0;
            $retiibb                    = ($_POST['retiibb']                    ?? '0') === '1' ? 1 : 0;
            $retiva                     = ($_POST['retiva']                     ?? '0') === '1' ? 1 : 0;
            $habilitarubros             = ($_POST['habilitarubros']             ?? '0') === '1' ? 1 : 0;
            $modiflistas                = ($_POST['modiflistas']                ?? '0') === '1' ? 1 : 0;
            $admitendc                  = ($_POST['admitendc']                  ?? '0') === '1' ? 1 : 0;
            $descuentosglobales         = ($_POST['descuentosglobales']         ?? '0') === '1' ? 1 : 0;
            $descuentosporlinea         = ($_POST['descuentosporlinea']         ?? '0') === '1' ? 1 : 0;
            $recargosglobales           = ($_POST['recargosglobales']           ?? '0') === '1' ? 1 : 0;
            $ticketsiniva               = ($_POST['ticketsiniva']               ?? '0') === '1' ? 1 : 0;
            $agruparproductos           = ($_POST['agruparproductos']           ?? '0') === '1' ? 1 : 0;
            $modificarprecioenventas    = ($_POST['modificarprecioenventas']    ?? '0') === '1' ? 1 : 0;
            $predeterminarcliente       = ($_POST['predeterminarcliente']       ?? '0') === '1' ? 1 : 0;
            $imprimeremitoenviopostfactura = ($_POST['imprimeremitoenviopostfactura'] ?? '0') === '1' ? 1 : 0;
            $imputarcobros              = ($_POST['imputarcobros']              ?? '0') === '1' ? 1 : 0;
            $tablareducidaventas        = ($_POST['tablareducidaventas']        ?? '0') === '1' ? 1 : 0;
            $mostrarvendedor            = ($_POST['mostrarvendedor']            ?? '0') === '1' ? 1 : 0;

            // Si no se selecciona otro archivo, conservar el logo activo.
            $logoData = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1")->fetchColumn();
            $logoData = $logoData !== false ? $logoData : '';
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                if ((int)$_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('El logo no puede superar los 2 MB.');
                }
                $logoData = file_get_contents($_FILES['logo']['tmp_name']);
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->buffer($logoData);
                if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
                    throw new Exception('El logo debe ser una imagen PNG, JPG o WebP válida.');
                }
            }

            // INSERT siempre (nuevo registro)
            $sql = "INSERT INTO configuracion (
                        montominimo, maximoajustes, stocknegativo, tipoventa, poriibb,
                        retgan, retiibb, retiva, habilitarubros, modiflistas,
                        texboxpredeterminado, admitendc, fechastock, descuentosglobales,
                        descuentosporlinea, recargosglobales, tipodescuentoindividual,
                        ticketsiniva, agruparproductos, modificarprecioenventas,
                        predeterminarcliente, predeterminarcomprobante, clientepredeterminado,
                        imprimeremitoenviopostfactura, leyenda2, imputarcobros,
                        formatoimpresion, tablareducidaventas, mostrarvendedor, usuario, logo
                    ) VALUES (
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?
                    )";

            $params = [
                $montominimo, $maximoajustes, $stocknegativo, $tipoventa, $poriibb,
                $retgan, $retiibb, $retiva, $habilitarubros, $modiflistas,
                $texboxpredeterminado, $admitendc, $fechastock, $descuentosglobales,
                $descuentosporlinea, $recargosglobales, $tipodescuentoindividual,
                $ticketsiniva, $agruparproductos, $modificarprecioenventas,
                $predeterminarcliente, $predeterminarcomprobante, $clientepredeterminado,
                $imprimeremitoenviopostfactura, $leyenda2, $imputarcobros,
                $formatoimpresion, $tablareducidaventas, $mostrarvendedor, $usuario, $logoData
            ];

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            echo json_encode(['success' => true, 'message' => 'Configuración guardada correctamente (nuevo histórico).']);
            break;

        case 'buscar_clientes':
            $q = "%" . ($_GET['q'] ?? '') . "%";
            $stmt = $pdo->prepare("SELECT id, razon FROM alb_clientes WHERE razon LIKE ? OR numdoc LIKE ? LIMIT 20");
            $stmt->execute([$q, $q]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $data = [];
            foreach ($results as $row) {
                $data[] = ['id' => $row['id'], 'text' => $row['razon']];
            }
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
