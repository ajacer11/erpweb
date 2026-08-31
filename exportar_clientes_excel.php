<?php
/**
 * exportar_clientes_excel.php - Exportación de Padrón de Clientes
 * Genera un archivo .xls con todos los campos de la tabla alb_clientes
 */

require_once 'db.php';
require_once 'permisos_helpers.php';

// 1. Obtener filtros
$busqueda = $_GET['q'] ?? '';
$mostrar_inhabilitados = isset($_GET['inhabilitados']) && $_GET['inhabilitados'] == 'true';

// 2. Construir Query
$sql = "SELECT * FROM alb_clientes WHERE 1=1";
$params = [];

if (!empty($busqueda)) {
    $sql .= " AND (razon LIKE ? OR numdoc LIKE ? OR id LIKE ?)";
    $term = "%$busqueda%";
    $params = [$term, $term, $term];
}

if (!$mostrar_inhabilitados) {
    $sql .= " AND habilitado = 1";
}

// Seguridad: Vendedor restringido
if (esVendedorRestringido()) {
    $sql .= " AND vendedorid = ?";
    $params[] = idVendedorRestringido();
}

$sql .= " ORDER BY razon ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

// 3. Configurar Headers para descarga de Excel
$filename = "Padron_Clientes_" . date('Ymd_His') . ".xls";

header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Para que Excel reconozca correctamente los caracteres especiales (UTF-8 con BOM)
echo "\xEF\xBB\xBF"; 
?>

<style>
    .table-header {
        background-color: #001020;
        color: #00ffff;
        font-weight: bold;
        border: 1px solid #334455;
    }
    .cell-data {
        border: 1px solid #cccccc;
    }
    .title-report {
        font-size: 18px;
        font-weight: bold;
        color: #333;
    }
</style>

<table border="1">
    <thead>
        <tr>
            <th colspan="10" style="font-size: 20px; text-align: left; border: none;">WEST IT ERP - PADRÓN DE CLIENTES</th>
        </tr>
        <tr>
            <th colspan="10" style="text-align: left; border: none;">Fecha de Reporte: <?= date('d/m/Y H:i') ?></th>
        </tr>
        <tr><th colspan="10" style="border: none;"></th></tr>
        <tr class="table-header">
            <th>ID</th>
            <th>RAZÓN SOCIAL</th>
            <th>DOMICILIO</th>
            <th>LOCALIDAD</th>
            <th>PROVINCIA</th>
            <th>CP</th>
            <th>DOCUMENTO</th>
            <th>TIPO</th>
            <th>CONDICIÓN FISCAL</th>
            <th>TELÉFONO</th>
            <th>CELULAR</th>
            <th>E-MAIL</th>
            <th>CONTACTO</th>
            <th>ESTADO</th>
            <th>LISTA PRECIOS</th>
            <th>CTA. CTE.</th>
            <th>DÍAS CC</th>
            <th>VENDEDOR ID</th>
            <th>TRANSPORTE ID</th>
            <th>WEB</th>
            <th>HORARIO</th>
            <th>NOTAS</th>
            <th>SECTOR 1</th>
            <th>CONTACTO 1</th>
            <th>TEL 1</th>
            <th>MAIL 1</th>
            <th>SECTOR 2</th>
            <th>CONTACTO 2</th>
            <th>TEL 2</th>
            <th>MAIL 2</th>
            <th>SECTOR 3</th>
            <th>CONTACTO 3</th>
            <th>TEL 3</th>
            <th>MAIL 3</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($clientes as $c): ?>
            <tr>
                <td class="cell-data"><?= $c['id'] ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['razon']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['domicilio']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['localidad']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['provincia']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['cp']) ?></td>
                <td class="cell-data"><?= $c['numdoc'] ?></td>
                <td class="cell-data"><?= $c['tipodoc'] ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['condicionfiscal']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['telefono']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['celular']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['mail']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['contacto']) ?></td>
                <td class="cell-data"><?= ($c['habilitado'] == 1) ? 'HABILITADO' : 'INHABILITADO' ?></td>
                <td class="cell-data">LISTA <?= $c['ListaDePRecios'] ?></td>
                <td class="cell-data"><?= ($c['cuentacorriente'] == 1) ? 'SI' : 'NO' ?></td>
                <td class="cell-data"><?= $c['diascc'] ?></td>
                <td class="cell-data"><?= $c['vendedorid'] ?></td>
                <td class="cell-data"><?= $c['transporte'] ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['web']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['horario']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['notas']) ?></td>
                
                <!-- Contactos Adicionales -->
                <td class="cell-data"><?= htmlspecialchars($c['s1sector']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s1contacto']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s1tel']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s1mail']) ?></td>
                
                <td class="cell-data"><?= htmlspecialchars($c['s2sector']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s2contacto']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s2tel']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s2mail']) ?></td>
                
                <td class="cell-data"><?= htmlspecialchars($c['s3sector']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s3contacto']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s3tel']) ?></td>
                <td class="cell-data"><?= htmlspecialchars($c['s3mail']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>