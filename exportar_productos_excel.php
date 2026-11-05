<?php
/**
 * exportar_productos_excel.php - Exportación de Padrón de Artículos
 * Genera un archivo .xls compatible con Excel filtrando por los mismos criterios del admin.
 */

// SEGURIDAD: Aunque es descarga directa, verificamos sesión básica
session_start();
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debe iniciar sesión.");
}

require_once 'db.php';

// 1. OBTENER FILTROS (Igual que en api_productos.php)
$q = $_GET['q'] ?? '';
$rubro = $_GET['rubro'] ?? '';
$marca = $_GET['marca'] ?? '';
$familia = $_GET['familia'] ?? '';
$proveedor = $_GET['proveedor'] ?? '';
$habilitado = $_GET['habilitado'] ?? 'todos'; // 1, 0, todos
$stock_bajo = isset($_GET['stock_bajo']) && $_GET['stock_bajo'] == 'true';

// 2. CONSTRUIR QUERY
// Seleccionamos todo, más los nombres de las tablas relacionadas para mostrar texto en vez de IDs
$sql = "SELECT p.*, 
               m.marca AS nombre_marca, 
               f.nombre AS nombre_familia,
               mon.nombre AS nombre_moneda
        FROM alb_productos p
        LEFT JOIN Marcas m ON p.Marca = m.id
        LEFT JOIN familias f ON p.familia = f.id
        LEFT JOIN monedas mon ON p.idmoneda = mon.id
        WHERE 1=1";

$params = [];

require_once 'productos_filtros.php';
aplicarFiltrosProductos($sql, $params, $_GET);

$sql .= " ORDER BY p.descripcion ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error al consultar productos: " . $e->getMessage());
}

// 3. CONFIGURAR HEADERS PARA DESCARGA EXCEL
$filename = "Listado_Productos_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// 4. GENERAR HTML DE LA TABLA
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        .header { background-color: #001020; color: #ffffff; font-weight: bold; text-align: center; border: 1px solid #000; }
        .text { text-align: left; } /* Fuerza texto */
        .number { text-align: right; } /* Decimales */
        .int { text-align: center; } /* Enteros */
        .title { font-size: 18px; font-weight: bold; text-align: center; background-color: #00ffff; color: #000; }
        .info { background-color: #f0f0f0; font-style: italic; }
        /* mso-number-format styles applied inline in table cells for Excel compatibility */
    </style>
</head>
<body>

<table>
    <tr>
        <td colspan="15" class="title">LISTADO DE PRODUCTOS - WEST IT ERP</td>
    </tr>
    <tr>
        <td colspan="15" class="info">Generado el: <?= date('d/m/Y H:i:s') ?> | Filtros: <?= htmlspecialchars($q) ?></td>
    </tr>
    <tr></tr> <!-- Espacio -->
    <thead>
        <tr>
            <!-- IDENTIFICACIÓN -->
            <th class="header">CÓDIGO</th>
            <th class="header">DESCRIPCIÓN</th>
            <th class="header">RUBRO</th>
            <th class="header">MARCA</th>
            <th class="header">FAMILIA</th>
            <th class="header">PROVEEDOR</th>
            <th class="header">COD. PROV.</th>
            <th class="header">TIPO</th>
            <th class="header">ESTADO</th>
            
            <!-- STOCK -->
            <th class="header" style="background-color: #2c3e50;">STOCK</th>
            <th class="header" style="background-color: #2c3e50;">STK MIN</th>
            <th class="header" style="background-color: #2c3e50;">STK IDEAL</th>
            
            <!-- COSTOS -->
            <th class="header" style="background-color: #8e44ad;">MONEDA</th>
            <th class="header" style="background-color: #8e44ad;">COSTO NETO</th>
            <th class="header" style="background-color: #8e44ad;">IMP. INT.</th>
            <th class="header" style="background-color: #8e44ad;">IVA COSTO</th>
            <th class="header" style="background-color: #8e44ad;">COSTO FINAL</th>
            
            <!-- PRECIOS DE VENTA (L1 a L8) -->
            <?php for($i=1; $i<=8; $i++): ?>
                <th class="header" style="background-color: #16a085;">L<?= $i ?> NETO</th>
                <th class="header" style="background-color: #27ae60;">L<?= $i ?> FINAL</th>
            <?php endfor; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($productos as $p): ?>
            <?php 
                $estado = $p['habilitado'] == 1 ? 'ACTIVO' : 'INACTIVO';
                $tipo = '';
                switch($p['tipo_producto']) {
                    case 1: $tipo = 'TERMINADO'; break;
                    case 2: $tipo = 'MATERIA PRIMA'; break;
                    case 3: $tipo = 'SERVICIO'; break;
                }
            ?>
            <tr>
                <!-- DATOS GENERALES -->
                <td class="text" style="mso-number-format:'\@'"><?= htmlspecialchars($p['codigo']) ?></td>
                <td><?= htmlspecialchars($p['descripcion']) ?></td>
                <td><?= htmlspecialchars($p['rubro']) ?></td>
                <td><?= htmlspecialchars($p['nombre_marca']) ?></td>
                <td><?= htmlspecialchars($p['nombre_familia']) ?></td>
                <td><?= htmlspecialchars($p['Proveedor']) ?></td>
                <td class="text"><?= htmlspecialchars($p['codigoproveedor']) ?></td>
                <td><?= $tipo ?></td>
                <td style="<?= $p['habilitado'] == 0 ? 'color: red;' : 'color: green;' ?>"><?= $estado ?></td>
                
                <!-- STOCK -->
                <td class="int"><?= $p['Stock'] + 0 ?></td> <!-- +0 quita decimales innecesarios -->
                <td class="int"><?= $p['StockMin'] + 0 ?></td>
                <td class="int"><?= $p['stockmaximo'] + 0 ?></td>
                
                <!-- COSTOS -->
                <td class="text" style="text-align: center;"><?= htmlspecialchars($p['nombre_moneda'] ?? 'PESOS') ?></td>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($p['sinivacosto'], 2, ',', '') ?></td>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($p['ImpInt'], 2, ',', '') ?></td>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($p['ivacosto'], 2, ',', '') ?></td>
                <td class="number" style="font-weight: bold; mso-number-format:'0.00'"><?= number_format($p['preciocosto'], 2, ',', '') ?></td>
                
                <!-- PRECIOS L1 a L8 -->
                <?php 
                    // Mapeo manual porque la L1 tiene nombres distintos (siniva, precio) vs L2..8 (siniva2, precio2)
                    $l1_neto = $p['siniva'];
                    $l1_final = $p['precio'];
                ?>
                <td class="number" style="mso-number-format:'0.00'"><?= number_format($l1_neto, 2, ',', '') ?></td>
                <td class="number" style="font-weight: bold; mso-number-format:'0.00'"><?= number_format($l1_final, 2, ',', '') ?></td>

                <?php for($i=2; $i<=8; $i++): ?>
                    <td class="number" style="mso-number-format:'0.00'"><?= number_format($p['siniva'.$i], 2, ',', '') ?></td>
                    <td class="number" style="font-weight: bold; mso-number-format:'0.00'"><?= number_format($p['precio'.$i], 2, ',', '') ?></td>
                <?php endfor; ?>
                
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

</body>
</html>