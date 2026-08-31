<?php
/**
 * imprimir_presupuesto.php - VISTA PROFESIONAL DE IMPRESIÓN
 * Diseñado para West IT ERP - Formato para envío a clientes.
 * Fuente de datos: Logo (configuracion) | Empresa (datosempresa)
 */
session_start();
require_once 'db.php';
require_once 'permisos_helpers.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) die("Número de presupuesto no válido.");

try {
    // 1. Obtener LOGO de tabla configuracion (Max ID)
    $stmtLogo = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1");
    $logoData = $stmtLogo->fetch();
    $logoBase64 = ($logoData && !empty($logoData['logo'])) ? base64_encode($logoData['logo']) : null;

    // 2. Obtener DATOS DE EMPRESA de tabla datosempresa (Último registro)
    $stmtEmp = $pdo->query("SELECT * FROM datosempresa ORDER BY id DESC LIMIT 1");
    $emp = $stmtEmp->fetch();

    if (!$emp) {
        $emp = [
            'razon' => 'EMPRESA NO CONFIGURADA',
            'domicilio' => '',
            'localidad' => '',
            'provincia' => '',
            'cuit' => '00-00000000-0',
            'condicioniva' => 'Responsable Inscripto',
            'telefono' => '',
            'email' => '',
            'web' => ''
        ];
    }

    // 3. Obtener Cabecera del Presupuesto
    // Se usa c.condicionfiscal para el alias cli_iva según solicitud
    $sqlCab = "SELECT p.*, c.razon as cliente_nombre, c.domicilio as cli_domicilio, c.localidad as cli_localidad, 
                      c.numdoc as cli_cuit, c.condicionfiscal as cli_iva, v.nombre as vendedor_nombre
               FROM presupuestosclientes p
               LEFT JOIN alb_clientes c ON p.cliente = c.id
               LEFT JOIN vendedores v ON p.vendedorid = v.id
               WHERE p.numpedido = ?";
    $stmtCab = $pdo->prepare($sqlCab);
    $stmtCab->execute([$id]);
    $cab = $stmtCab->fetch();

    if (!$cab) die("El presupuesto no existe.");

    if (esVendedorRestringido() && (int) $cab['vendedorid'] !== idVendedorRestringido()) {
        die("No tiene acceso a este presupuesto.");
    }

    // 4. Obtener Líneas del Presupuesto
    // FIX: Se agrega p.rubro a la selección para evitar el error "Undefined array key"
    $sqlDet = "SELECT l.*, p.poriva as iva_pct, p.rubro 
               FROM lineapresupuestosclientes l
               INNER JOIN alb_productos p ON l.producto = p.codigo
               WHERE l.numpedido = ? 
               ORDER BY l.id ASC";
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$id]);
    $items = $stmtDet->fetchAll();

} catch (Exception $e) {
    die("Error crítico: " . $e->getMessage());
}

$subtotalNeto = 0;
$ivaTotal = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Presupuesto #<?php echo str_pad($id, 8, '0', STR_PAD_LEFT); ?></title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap');
        
        :root {
            --primary: #002d5a;
            --accent: #00d4ff;
            --text-dark: #1a1a1a;
            --text-muted: #555;
            --border: #ddd;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            color: var(--text-dark); 
            line-height: 1.4; 
            margin: 0; 
            padding: 20px;
            background-color: #f5f5f5;
        }

        .sheet {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            position: relative;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 40px;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 20px;
        }

        .logo-container img {
            max-width: 250px;
            max-height: 120px;
            object-fit: contain;
        }

        .company-data {
            margin-top: 10px;
            font-size: 11px;
            color: var(--text-muted);
        }

        .company-data strong {
            color: var(--primary);
            font-size: 16px;
            display: block;
            margin-bottom: 4px;
        }

        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; color: var(--primary); font-size: 32px; font-weight: 700; }
        .doc-num { font-size: 18px; color: #d32f2f; font-weight: bold; margin-top: 5px; }
        .doc-meta { font-size: 12px; margin-top: 10px; }

        .info-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 40px;
        }

        .box h3 {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #999;
            border-bottom: 1px solid var(--border);
            padding-bottom: 5px;
            margin-bottom: 12px;
        }

        .box p { margin: 4px 0; font-size: 13px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        .items-table th {
            background-color: var(--primary);
            color: white;
            text-align: left;
            padding: 10px;
            font-size: 11px;
            text-transform: uppercase;
        }

        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            font-size: 12px;
        }

        .items-table tr:nth-child(even) { background-color: #fcfcfc; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }

        .footer-grid { display: flex; justify-content: space-between; align-items: flex-start; }
        .observaciones {
            width: 55%;
            font-size: 11px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            color: #666;
            line-height: 1.6;
        }

        .totales { width: 35%; }
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .total-row.grand-total {
            border-top: 2px solid var(--primary);
            margin-top: 10px;
            padding-top: 10px;
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
        }

        .btn-float {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px 30px;
            border-radius: 50px;
            font-weight: bold;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-float:hover { transform: translateY(-3px); box-shadow: 0 15px 30px rgba(0,0,0,0.3); }

        @media print {
            body { background: white; padding: 0; }
            .sheet { box-shadow: none; max-width: 100%; padding: 0; }
            .btn-float { display: none; }
        }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/js/all.min.js"></script>
</head>
<body>

    <button class="btn-float" onclick="window.print()">
        <i class="fas fa-file-pdf"></i> IMPRIMIR / DESCARGAR PDF
    </button>

    <div class="sheet">
        <!-- ENCABEZADO -->
        <div class="header">
            <div class="logo-side">
                <div class="logo-container">
                    <?php if($logoBase64): ?>
                        <img src="data:image/jpeg;base64,<?php echo $logoBase64; ?>" alt="Logo">
                    <?php else: ?>
                        <div style="font-weight: bold; color: var(--primary); font-size: 24px;"><?php echo htmlspecialchars($emp['razon']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="company-data">
                    <strong><?php echo htmlspecialchars($emp['razon']); ?></strong>
                    <p><?php echo htmlspecialchars($emp['domicilio']); ?></p>
                    <p><?php echo htmlspecialchars($emp['localidad'] . ', ' . $emp['provincia']); ?></p>
                    <p>CUIT: <?php echo htmlspecialchars($emp['cuit']); ?> | <?php echo htmlspecialchars($emp['condicioniva']); ?></p>
                </div>
            </div>
            <div class="doc-title">
                <h1>PRESUPUESTO</h1>
                <div class="doc-num"># <?php echo str_pad($id, 8, '0', STR_PAD_LEFT); ?></div>
                <div class="doc-meta">
                    <p>Fecha Emisión: <strong><?php echo date('d/m/Y', strtotime($cab['fechapedido'])); ?></strong></p>
                    <p>Validez hasta: <strong><?php echo date('d/m/Y', strtotime($cab['fechaentrega'])); ?></strong></p>
                </div>
            </div>
        </div>

        <!-- CLIENTE E INFO -->
        <div class="info-section">
            <div class="box">
                <h3>Cliente</h3>
                <p><strong><?php echo htmlspecialchars($cab['cliente_nombre']); ?></strong></p>
                <p><?php echo htmlspecialchars($cab['cli_domicilio']); ?></p>
                <p><?php echo htmlspecialchars($cab['cli_localidad']); ?></p>
                <p>CUIT: <?php echo htmlspecialchars($cab['cli_cuit'] ?: 'Consumidor Final'); ?> | <?php echo htmlspecialchars($cab['cli_iva']); ?></p>
            </div>
            <div class="box">
                <h3>Detalle del Comprobante</h3>
                <p>Vendedor: <strong><?php echo htmlspecialchars($cab['vendedor_nombre'] ?: 'VENTA DIRECTA'); ?></strong></p>
                <p>Condición: <strong>Venta de Contado / Saldo Cta. Cte.</strong></p>
                <p>Moneda: <strong>Pesos Argentinos ($)</strong></p>
                <p>Contacto: <strong><?php echo htmlspecialchars($emp['telefono'] . ' | ' . $emp['email']); ?></strong></p>
            </div>
        </div>

        <!-- TABLA DE ARTÍCULOS -->
        <table class="items-table">
            <thead>
                <tr>
                    <th width="12%">Código</th>
                    <th width="48%">Descripción / Rubro</th>
                    <th width="10%" class="text-center">Cant.</th>
                    <th width="15%" class="text-right">Unitario</th>
                    <th width="15%" class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $row): 
                    $pLista = floatval($row['preciounit']);
                    $cant = floatval($row['cantidad']);
                    $descPct = floatval($row['descuento']);
                    $ivaPct = floatval($row['iva_pct']);
                    
                    $precioNetoUnit = $pLista * (1 - ($descPct / 100));
                    $netoTotalLinea = $precioNetoUnit * $cant;
                    $ivaLinea = $netoTotalLinea * ($ivaPct / 100);
                    
                    $subtotalNeto += $netoTotalLinea;
                    $ivaTotal += $ivaLinea;

                    // Validación de rubro para evitar Warning
                    $rubro = $row['rubro'] ?? '';
                ?>
                <tr>
                    <td class="text-center"><strong><?php echo htmlspecialchars($row['producto']); ?></strong></td>
                    <td>
                        <div><?php echo htmlspecialchars($row['descripcion']); ?></div>
                        <?php if(!empty($rubro)): ?>
                            <small style="color:#999; font-size:10px"><?php echo htmlspecialchars($rubro); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo number_format($cant, 0, ',', '.'); ?></td>
                    <td class="text-right">$ <?php echo number_format($precioNetoUnit, 2, ',', '.'); ?></td>
                    <td class="text-right"><strong>$ <?php echo number_format($netoTotalLinea, 2, ',', '.'); ?></strong></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- TOTALES Y NOTAS -->
        <div class="footer-grid">
            <div class="observaciones">
                <p style="margin-top:0"><strong>Condiciones y Notas:</strong></p>
                <?php if($cab['observaciones']): ?>
                    <p><?php echo nl2br(htmlspecialchars($cab['observaciones'])); ?></p>
                <?php else: ?>
                    <p>• Precios sujetos a cambios sin previo aviso.<br>
                    • Los productos cotizados están sujetos a disponibilidad de stock al confirmar el pedido.<br>
                    • Plazo de entrega estimado: 48/72hs hábiles una vez confirmada la orden.</p>
                <?php endif; ?>
                
                <div style="margin-top: 20px; font-weight: bold; color: var(--primary);">
                    <?php if(!empty($emp['web'])) echo "Visitanos en " . htmlspecialchars($emp['web']); ?>
                </div>
            </div>
            
            <div class="totales">
                <div class="total-row">
                    <span>Subtotal Neto:</span>
                    <span>$ <?php echo number_format($subtotalNeto, 2, ',', '.'); ?></span>
                </div>
                <div class="total-row">
                    <span>IVA:</span>
                    <span>$ <?php echo number_format($ivaTotal, 2, ',', '.'); ?></span>
                </div>
                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span>$ <?php echo number_format($subtotalNeto + $ivaTotal, 2, ',', '.'); ?></span>
                </div>
            </div>
        </div>

        <div style="margin-top: 40px; text-align: center; font-size: 9px; color: #ccc; border-top: 1px solid #eee; padding-top: 10px;">
            Este presupuesto es un documento comercial informativo y no posee validez como factura fiscal.
        </div>
    </div>

</body>
</html>