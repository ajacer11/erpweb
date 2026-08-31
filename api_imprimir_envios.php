<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_form_data') {
        // Cargar Combos
        $data = [];
        $stmtCli = $pdo->prepare("SELECT id, razon, transporte, domicilio, localidad, provincia FROM alb_clientes WHERE habilitado = 1 ORDER BY razon ASC");
        $stmtCli->execute();
        $data['clientes'] = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

        $stmtTra = $pdo->prepare("SELECT id, razon FROM transportes WHERE habilitado = 1 ORDER BY razon ASC");
        $stmtTra->execute();
        $data['transportes'] = $stmtTra->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($data);

    } elseif ($action === 'save_and_print') {
        // 1. RECIBIR DATOS
        $input = json_decode(file_get_contents('php://input'), true);
        
        $clienteId = $input['cliente_id'] ?? 0;
        $transporteId = $input['transporte_id'] ?? 0;
        $bultos = (int)($input['bultos'] ?? 1);
        $unitario = (float)($input['unitario'] ?? 0);
        $copiasRemito = (int)($input['copias'] ?? 1); // Copias solo para el remito
        $totalEnvio = $bultos * $unitario;

        // Validaciones básicas
        if ($clienteId == 0 || $transporteId == 0) throw new Exception("Faltan datos obligatorios.");

        // Obtener datos del CLIENTE
        $stmtC = $pdo->prepare("SELECT razon, domicilio, localidad, provincia, numdoc, telefono FROM alb_clientes WHERE id = ?");
        $stmtC->execute([$clienteId]);
        $cli = $stmtC->fetch(PDO::FETCH_ASSOC);
        if (!$cli) throw new Exception("Cliente inexistente.");

        // Obtener datos del TRANSPORTE
        $stmtT = $pdo->prepare("SELECT razon, domicilio, telefono FROM transportes WHERE id = ?");
        $stmtT->execute([$transporteId]);
        $tra = $stmtT->fetch(PDO::FETCH_ASSOC);
        if (!$tra) throw new Exception("Transporte inexistente.");

        // Obtener datos de la EMPRESA (Remitente)
        $stmtEmp = $pdo->prepare("SELECT razon, domicilio, localidad, provincia, cuit, telefono FROM datosempresa LIMIT 1");
        $stmtEmp->execute();
        $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
        
        // Fallback si no hay datos de empresa cargados
        if (!$emp) {
            $emp = [
                'razon' => $_SESSION['tenant_razon'] ?? 'EMPRESA DEMO',
                'domicilio' => '', 'localidad' => '', 'provincia' => '', 'cuit' => '', 'telefono' => ''
            ];
        }

        // Datos de Sesión (PDV)
        $pdv = $_SESSION['pdv'] ?? 1;
        $pdvStr = str_pad($pdv, 5, "0", STR_PAD_LEFT);
        $userId = $_SESSION['usuario_id'];

        // 2. TRANSACCIÓN DB (Guardar Envío)
        $pdo->beginTransaction();
        
        try {
            // Calcular Número
            $stmtNum = $pdo->prepare("SELECT COALESCE(MAX(numero), 0) + 1 FROM alb_envios WHERE punto_venta = ?");
            $stmtNum->execute([$pdv]);
            $nuevoNumero = $stmtNum->fetchColumn();

            // Insertar Registro
            $sqlInsert = "INSERT INTO alb_envios 
                (fecha, punto_venta, numero, id_cliente, nombre_cliente, direccion_entrega, localidad_entrega, 
                 id_transporte, nombre_transporte, cantidad_bultos, importe_total, id_usuario) 
                VALUES (NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmtIns = $pdo->prepare($sqlInsert);
            $stmtIns->execute([
                $pdv, 
                $nuevoNumero, 
                $clienteId, 
                $cli['razon'], 
                $cli['domicilio'], 
                $cli['localidad'] . ' - ' . $cli['provincia'],
                $transporteId,
                $tra['razon'],
                $bultos,
                $totalEnvio,
                $userId
            ]);

            $pdo->commit();

            // Formatear número para impresión (00001-00000123)
            $numeroCompleto = $pdvStr . '-' . str_pad($nuevoNumero, 8, "0", STR_PAD_LEFT);

        } catch (Exception $ex) {
            $pdo->rollBack();
            throw new Exception("Error al guardar envío: " . $ex->getMessage());
        }

        // 3. GENERAR HTML DE IMPRESIÓN
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Envío <?= $numeroCompleto ?></title>
            <link rel="icon" type="image/png" href="isotipo.png">
            <style>
                @page { size: A4; margin: 0; }
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    margin: 0; 
                    padding: 0; 
                    background: #fff; 
                    color: #000; 
                    width: 210mm; /* Ancho A4 */
                }
                
                /* ETIQUETAS: 2 POR HOJA */
                .etiqueta-container {
                    width: 100%;
                    height: 148mm; /* Mitad de A4 exacto (297mm / 2) */
                    box-sizing: border-box;
                    padding: 15px;
                    border-bottom: 1px dashed #ccc; /* Línea de corte visual */
                    page-break-inside: avoid;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                }
                
                .etiqueta-border {
                    border: 4px solid #000;
                    border-radius: 10px;
                    padding: 15px;
                    height: 95%;
                    display: flex;
                    flex-direction: column;
                    justify-content: space-between;
                }

                /* REMITOS: 1 POR HOJA */
                .remito-page {
                    width: 100%;
                    height: 296mm; /* Alto A4 casi completo */
                    box-sizing: border-box;
                    padding: 30px;
                    page-break-after: always;
                    position: relative;
                }

                .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 5px; margin-bottom: 10px; }
                .header h1 { margin: 0; font-size: 28px; text-transform: uppercase; letter-spacing: 2px; }
                
                .datos-row { display: flex; margin-bottom: 8px; align-items: baseline; }
                .label { font-weight: bold; width: 110px; flex-shrink: 0; font-size: 14px; }
                .value { flex-grow: 1; font-size: 18px; font-weight: bold; text-transform: uppercase; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                .big-value { font-size: 32px; font-weight: 900; }
                
                .bulto-badge {
                    border: 4px solid #000;
                    padding: 5px 25px;
                    font-size: 42px;
                    font-weight: 900;
                    text-align: center;
                    display: inline-block;
                    border-radius: 8px;
                    background: #eee;
                }
                
                .footer { border-top: 1px solid #000; pt: 5px; font-size: 11px; text-align: center; }
                
                .remito-box { border: 1px solid #000; padding: 8px; font-size: 14px; }
                
                .page-break { page-break-after: always; }
                
                @media print {
                    .no-print { display: none; }
                    button { display: none; }
                    body { width: auto; }
                }
            </style>
        </head>
        <body onload="setTimeout(function(){ window.print(); }, 500);">
            
            <div class="no-print" style="position: fixed; top: 0; left: 0; width: 100%; background: #eee; padding: 15px; text-align: center; border-bottom: 1px solid #ccc; z-index: 9999;">
                <h3 style="margin: 0 0 10px 0;">ENVÍO GUARDADO: <?= $numeroCompleto ?></h3>
                <button onclick="window.print()" style="font-size: 18px; padding: 10px 25px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px;">IMPRIMIR (CTRL+P)</button>
                <button onclick="window.close()" style="font-size: 18px; padding: 10px 25px; cursor: pointer; background: #ccc; border: none; border-radius: 5px; margin-left: 10px;">CERRAR</button>
            </div>
            
            <!-- Espaciador para la barra fija -->
            <div class="no-print" style="height: 100px;"></div>

            <!-- ETIQUETAS DE BULTOS (2 por hoja) -->
            <?php for ($i = 1; $i <= $bultos; $i++): ?>
                <div class="etiqueta-container">
                    <div class="etiqueta-border">
                        <div class="header">
                            <h1>DESTINATARIO</h1>
                            <small>ENVÍO N° <?= $numeroCompleto ?></small>
                        </div>
                        
                        <div class="datos-row">
                            <span class="label">CLIENTE:</span>
                            <span class="value big-value"><?= htmlspecialchars($cli['razon']) ?></span>
                        </div>
                        
                        <div class="datos-row">
                            <span class="label">DIRECCIÓN:</span>
                            <span class="value"><?= htmlspecialchars($cli['domicilio']) ?></span>
                        </div>
                        
                        <div class="datos-row">
                            <span class="label">LOCALIDAD:</span>
                            <span class="value"><?= htmlspecialchars($cli['localidad']) ?> - <?= htmlspecialchars($cli['provincia']) ?></span>
                        </div>

                        <div class="datos-row">
                            <span class="label">TELÉFONO:</span>
                            <span class="value"><?= htmlspecialchars($cli['telefono']) ?></span>
                        </div>

                        <hr style="border: 2px solid #000; margin: 10px 0;">

                        <div class="datos-row">
                            <span class="label">TRANSPORTE:</span>
                            <span class="value" style="font-size: 22px;"><?= htmlspecialchars($tra['razon']) ?></span>
                        </div>

                        <div class="datos-row">
                            <span class="label">REMITENTE:</span>
                            <span class="value"><?= htmlspecialchars($emp['razon']) ?></span>
                        </div>
                        <div class="datos-row">
                            <span class="label">ORIGEN:</span>
                            <span class="value small" style="font-size: 12px;"><?= htmlspecialchars($emp['domicilio']) ?> - <?= htmlspecialchars($emp['localidad']) ?> (<?= htmlspecialchars($emp['provincia']) ?>)</span>
                        </div>

                        <div style="text-align: center; flex-grow: 1; display: flex; flex-direction: column; justify-content: center;">
                            <div>
                                <div class="bulto-badge">
                                    BULTO <?= $i ?> / <?= $bultos ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="footer">
                            FECHA: <?= date('d/m/Y H:i') ?> | CONTROL DE DESPACHO
                        </div>
                    </div>
                </div>
                
                <?php 
                // Salto de página cada 2 etiquetas
                if ($i % 2 == 0 && $i < $bultos) echo '<div class="page-break"></div>'; 
                ?>
            <?php endfor; ?>

            <!-- SALTO DE PÁGINA ANTES DE LOS REMITOS -->
            <div class="page-break"></div>

            <!-- REMITOS DE TRANSPORTE (1 por hoja x Copias) -->
            <?php 
            $labelsCopias = ['ORIGINAL', 'DUPLICADO', 'TRIPLICADO', 'CUADRUPLICADO'];
            for ($c = 1; $c <= $copiasRemito; $c++): 
                $labelCopia = $labelsCopias[$c-1] ?? 'COPIA';
            ?>
                <div class="remito-page">
                    <div style="position: absolute; top: 10px; right: 20px; font-weight: bold; font-size: 12px; border: 1px solid #000; padding: 2px 8px;">
                        <?= $labelCopia ?>
                    </div>

                    <div class="header">
                        <h1>REMITO DE CONTROL DE ENVÍO</h1>
                        <h2 style="margin: 0; font-size: 24px;">N° <?= $numeroCompleto ?></h2>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td class="remito-box" style="width: 15%; background: #eee; font-weight: bold;">FECHA:</td>
                                <td class="remito-box" style="width: 35%;"><?= date('d/m/Y') ?></td>
                                <td class="remito-box" style="width: 15%; background: #eee; font-weight: bold;">HORA:</td>
                                <td class="remito-box" style="width: 35%;"><?= date('H:i') ?></td>
                            </tr>
                            
                            <tr><td colspan="4" style="height: 10px;"></td></tr>

                            <!-- Sección Remitente -->
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">REMITENTE:</td>
                                <td colspan="3" class="remito-box" style="font-size: 16px; font-weight: bold;"><?= htmlspecialchars($emp['razon']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">DIRECCIÓN:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($emp['domicilio']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">LOCALIDAD:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($emp['localidad']) ?> (<?= htmlspecialchars($emp['provincia']) ?>)</td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">TELÉFONO:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($emp['telefono']) ?></td>
                            </tr>

                            <tr><td colspan="4" style="height: 10px;"></td></tr>

                            <!-- Sección Destinatario -->
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">DESTINATARIO:</td>
                                <td colspan="3" class="remito-box" style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($cli['razon']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">CUIT:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($cli['numdoc']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">DIRECCIÓN:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($cli['domicilio']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">LOCALIDAD:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($cli['localidad']) ?> - <?= htmlspecialchars($cli['provincia']) ?></td>
                            </tr>
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">TELÉFONO:</td>
                                <td colspan="3" class="remito-box"><?= htmlspecialchars($cli['telefono']) ?></td>
                            </tr>

                            <tr><td colspan="4" style="height: 10px;"></td></tr>

                            <!-- Sección Transporte -->
                            <tr>
                                <td class="remito-box" style="background: #eee; font-weight: bold;">TRANSPORTE:</td>
                                <td colspan="3" class="remito-box" style="font-size: 18px; font-weight: bold;"><?= htmlspecialchars($tra['razon']) ?></td>
                            </tr>
                        </table>
                    </div>

                    <div style="margin-top: 30px; border: 2px solid #000; padding: 20px; background: #f9f9f9; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; font-size: 24px; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 10px;">
                            <span>CANTIDAD DE BULTOS:</span>
                            <span style="font-size: 32px;"><?= $bultos ?></span>
                        </div>
                        <?php if($unitario > 0): ?>
                        <div style="display: flex; justify-content: space-between; font-size: 18px; margin-top: 15px;">
                            <span>VALOR DECLARADO TOTAL:</span>
                            <span>$ <?= number_format($totalEnvio, 2, ',', '.') ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div style="margin-top: 100px; display: flex; justify-content: space-between;">
                        <!-- <div style="text-align: center; border-top: 1px solid #000; width: 45%; padding-top: 5px;">
                            <p style="margin: 0; font-weight: bold;">ENTREGUÉ CONFORME</p>
                            <small>FIRMA Y SELLO REMITENTE</small>
                        </div> -->
                        <div style="text-align: center; border-top: 1px solid #000; width: 45%; padding-top: 5px;">
                            <p style="margin: 0; font-weight: bold;">RECIBIDO EN TRANSPORTE</p>
                            <small>FIRMA Y SELLO TRANSPORTISTA</small>
                        </div>
                    </div>
                </div>
            <?php endfor; ?>

        </body>
        </html>
        <?php
        $htmlContent = ob_get_clean();
        
        echo json_encode([
            'status' => 'ok',
            'numero' => $numeroCompleto,
            'html_content' => $htmlContent
        ]);
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>