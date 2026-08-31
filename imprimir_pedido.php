<?php
/**
 * imprimir_pedido.php - VISTA DE IMPRESIÓN DE PEDIDO
 * Replica la lógica de reporte "DataSet2" de VB.NET
 */
session_start();
// No chequeamos AJAX aquí porque esto se abre en una pestaña nueva para imprimir
require_once 'db.php';
require_once 'permisos_helpers.php';

$numpedido = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($numpedido <= 0) die("Número de pedido inválido.");

try {
    // 1. OBTENER CABECERA (Datos del Cliente, Transporte, Vendedor)
    $sqlCab = "SELECT p.numpedido, p.fechapedido, p.fechaentrega, p.observaciones, p.vendedorid,
                      c.razon as cliente_razon, c.domicilio, c.localidad, c.provincia, c.telefono, c.numdoc as cuit, c.horario, c.id as cliente_id,
                      t.razon as transporte_nombre,
                      v.nombre as vendedor_nombre
               FROM pedidosclientes p
               LEFT JOIN alb_clientes c ON p.cliente = c.id
               LEFT JOIN transportes t ON c.transporte = t.id
               LEFT JOIN vendedores v ON p.vendedorid = v.id
               WHERE p.numpedido = ?";

    $stmtCab = $pdo->prepare($sqlCab);
    $stmtCab->execute([$numpedido]);
    $cab = $stmtCab->fetch(PDO::FETCH_ASSOC);

    if (!$cab) die("Pedido no encontrado.");

    if (esVendedorRestringido() && (int) $cab['vendedorid'] !== idVendedorRestringido()) {
        die("No tiene acceso a este pedido.");
    }

    // 2. MARCAR COMO IMPRESO (Lógica VB: EjecutaQuery("UPDATE ... impreso = 1"))
    $pdo->query("UPDATE pedidosclientes SET impreso = 1 WHERE numpedido = $numpedido");

    // 3. OBTENER DETALLE (Líneas)
    // Nota: La query de VB hace una subconsulta compleja para la marca. Aquí hacemos un JOIN simplificado asumiendo integridad.
    $sqlDet = "SELECT l.producto as codigo, l.descripcion, l.cantidad, l.preciounit, l.descuento, l.observacion as color, l.tela,
                      (SELECT m.marca FROM Marcas m 
                       INNER JOIN alb_productos prod ON m.id = prod.marca 
                       WHERE prod.codigo = l.producto LIMIT 1) as marca
               FROM lineapedidosclientes l
               WHERE l.numpedido = ?
               ORDER BY l.id ASC";
               
    $stmtDet = $pdo->prepare($sqlDet);
    $stmtDet->execute([$numpedido]);
    $items = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error de base de datos: " . $e->getMessage());
}

// Cálculos de Totales (VB: totalfinal += cant * (unit - (unit * desc / 100)))
$totalFinal = 0;
$totalBultos = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido N° <?php echo $numpedido; ?></title>
    <link rel="icon" type="image/png" href="isotipo.png">
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 12px; color: #000; margin: 0; padding: 20px; }
        .header-box { border: 1px solid #000; padding: 10px; margin-bottom: 10px; }
        .title { font-size: 20px; font-weight: bold; text-align: center; text-transform: uppercase; margin-bottom: 5px; }
        .subtitle { font-size: 14px; text-align: center; margin-bottom: 15px; }
        
        .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .info-grid td { padding: 4px; vertical-align: top; }
        .label { font-weight: bold; width: 120px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .items-table th { border: 1px solid #000; background-color: #eee; padding: 5px; font-weight: bold; text-align: center; }
        .items-table td { border: 1px solid #000; padding: 5px; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        
        .footer { margin-top: 20px; border-top: 2px solid #000; padding-top: 10px; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
            button { display: none; }
        }
    </style>
</head>
<body> <!-- onload="window.print()" -->

    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #000; color: #fff; border: none; cursor: pointer; font-weight: bold;">IMPRIMIR</button>
    </div>

    <!-- ENCABEZADO -->
    <div class="header-box">
        <div class="title">NOTA DE PEDIDO</div>
        <div class="subtitle">NÚMERO: <strong><?php echo str_pad($numpedido, 8, '0', STR_PAD_LEFT); ?></strong></div>
        
        <table class="info-grid">
            <tr>
                <td class="label">CLIENTE:</td>
                <td><?php echo htmlspecialchars($cab['cliente_razon']); ?> (ID: <?php echo $cab['cliente_id']; ?>)</td>
                <td class="label">FECHA PEDIDO:</td>
                <td><?php echo date('d/m/Y', strtotime($cab['fechapedido'])); ?></td>
            </tr>
            <tr>
                <td class="label">DOMICILIO:</td>
                <td><?php echo htmlspecialchars($cab['domicilio'] . ' - ' . $cab['localidad'] . ' (' . $cab['provincia'] . ')'); ?></td>
                <td class="label">FECHA ENTREGA:</td>
                <td><strong><?php echo date('d/m/Y', strtotime($cab['fechaentrega'])); ?></strong></td>
            </tr>
            <tr>
                <td class="label">TELÉFONO:</td>
                <td><?php echo htmlspecialchars($cab['telefono']); ?></td>
                <td class="label">VENDEDOR:</td>
                <td><?php echo htmlspecialchars($cab['vendedor_nombre']); ?></td>
            </tr>
            <tr>
                <td class="label">CUIT:</td>
                <td><?php echo htmlspecialchars($cab['cuit']); ?></td>
                <td class="label">HORARIO:</td>
                <td><?php echo htmlspecialchars($cab['horario']); ?></td>
            </tr>
            <tr>
                <td class="label">TRANSPORTE:</td>
                <td colspan="3"><?php echo htmlspecialchars($cab['transporte_nombre']); ?></td>
            </tr>
            <?php if(!empty($cab['observaciones'])): ?>
            <tr>
                <td class="label">OBSERVACIONES:</td>
                <td colspan="3" style="background-color: #f9f9f9; font-style: italic;"><?php echo nl2br(htmlspecialchars($cab['observaciones'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- DETALLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th width="10%">CÓDIGO</th>
                <th width="35%">DESCRIPCIÓN</th>
                <th width="10%">MARCA</th>
                <th width="10%">COLOR / OBS</th>
                <th width="5%">CANT.</th>
                <th width="10%">PRECIO U.</th>
                <th width="5%">DESC.%</th>
                <th width="10%">PRECIO DESC.</th>
                <th width="10%">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalCant = 0;
            foreach ($items as $item): 
                $cant = floatval($item['cantidad']);
                $precio = floatval($item['preciounit']);
                $descPct = floatval($item['descuento']);
                
                // Cálculo VB: (unit - (unit * desc / 100))
                $precioDesc = $precio - ($precio * ($descPct / 100));
                $subtotal = $cant * $precioDesc;
                
                $totalFinal += $subtotal;
                $totalCant += $cant;
            ?>
            <tr>
                <td><?php echo htmlspecialchars($item['codigo']); ?></td>
                <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($item['marca']); ?></td>
                <td class="text-center"><?php echo htmlspecialchars($item['color']); ?></td>
                <td class="text-center font-bold"><?php echo number_format($cant, 0); ?></td>
                <td class="text-right">$ <?php echo number_format($precio, 2, ',', '.'); ?></td>
                <td class="text-center"><?php echo $descPct > 0 ? number_format($descPct, 1).'%' : '-'; ?></td>
                <td class="text-right">$ <?php echo number_format($precioDesc, 2, ',', '.'); ?></td>
                <td class="text-right font-bold">$ <?php echo number_format($subtotal, 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- PIE -->
    <div class="footer">
        <table style="width: 100%">
            <tr>
                <td style="width: 70%; vertical-align: top;">
                    <small>Documento de uso interno. No válido como factura.</small>
                </td>
                <td style="width: 30%">
                    <table style="width: 100%; border: 1px solid #000;">
                        <tr>
                            <td class="label" style="padding: 5px;">CANT. ARTÍCULOS:</td>
                            <td class="text-right font-bold" style="padding: 5px;"><?php echo $totalCant; ?></td>
                        </tr>
                        <tr>
                            <td class="label" style="padding: 5px; background: #eee;">TOTAL NETO:</td>
                            <td class="text-right font-bold" style="padding: 5px; background: #eee; font-size: 16px;">
                                $ <?php echo number_format($totalFinal, 2, ',', '.'); ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>