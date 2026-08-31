<?php
/**
 * exportar_lista_precios.php - Genera Excel de Lista de Precios agrupado por Familia
 * Ajuste: Se fuerza el formato de texto para códigos y alineación derecha.
 */
require_once 'db.php';

$id_lista = $_GET['id_lista'] ?? 1;
$q = $_GET['q'] ?? '';
$rubro = $_GET['rubro'] ?? '';
$marca = $_GET['marca'] ?? '';
$familia = $_GET['familia'] ?? '';
$proveedor = $_GET['proveedor'] ?? '';
$habilitado = $_GET['habilitado'] ?? '1';

// Mapeo de columnas según la lista seleccionada
$col_neto = "siniva" . ($id_lista == 1 ? "" : $id_lista);
$col_iva = "iva" . ($id_lista == 1 ? "1" : $id_lista);
$col_total = "precio" . ($id_lista == 1 ? "" : $id_lista);

// Construcción de la consulta
$sql = "SELECT p.codigo, p.descripcion, p.rubro, p.familia as familia_id,
               f.nombre as nombre_familia, m.marca as nombre_marca,
               p.$col_neto as neto, p.$col_iva as iva, p.$col_total as total
        FROM alb_productos p
        LEFT JOIN Marcas m ON p.marca = m.id
        LEFT JOIN familias f ON p.familia = f.id
        WHERE p.habilitado = 1";

$params = [];
if (!empty($q)) {
    $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ? OR p.rubro LIKE ?)";
    $term = "%$q%";
    array_push($params, $term, $term, $term);
}
if (!empty($rubro)) { $sql .= " AND p.rubro = ?"; $params[] = $rubro; }
if (!empty($marca)) { $sql .= " AND p.marca = ?"; $params[] = $marca; }
if (!empty($familia)) { $sql .= " AND p.familia = ?"; $params[] = $familia; }
if (!empty($proveedor)) { $sql .= " AND p.proveedor = ?"; $params[] = $proveedor; }
if ($habilitado !== 'todos') { $sql .= " AND p.habilitado = ?"; $params[] = $habilitado; }

// ORDEN SOLICITADO: Agrupado por familia (implícito en el orden principal), luego rubro, marca y descripción
$sql .= " ORDER BY f.nombre ASC, p.rubro ASC, m.marca ASC, p.descripcion ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();

// Obtener el nombre de la lista para el título
$stmt_l = $pdo->prepare("SELECT descripcion FROM Listas WHERE id = ?");
$stmt_l->execute([$id_lista]);
$nom_lista = $stmt_l->fetchColumn() ?: "Lista $id_lista";

// Headers para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=Lista_Precios_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head>
        <meta http-equiv='Content-type' content='text/html;charset=utf-8' />
        <style>
            /* Estilo para forzar formato texto en celdas numéricas largas y alinear a la derecha */
            .text-force-right {
                mso-number-format:'\@';
                text-align: right;
            }
            th { background-color: #445566; color: #ffffff; font-weight: bold; }
            .header-main { background-color: #001020; color: #00ffff; font-size: 16px; text-align: center; }
            .header-info { background-color: #f2f2f2; font-size: 11px; text-align: left; }
            .familia-row { background-color: #eeeeee; font-weight: bold; height: 30px; }
            .num { text-align: right; }
        </style>
      </head>";
echo "<body>";
echo "<table border='1'>";

// Título y Cabecera
echo "<tr><th colspan='6' class='header-main'>REPORTE: $nom_lista</th></tr>";
echo "<tr><th colspan='6' class='header-info'>GENERADO EL: " . date('d/m/Y H:i') . "</th></tr>";

$familiaActual = null;

foreach ($productos as $p) {
    // Si cambia la familia, imprimimos un separador
    if ($familiaActual !== $p['nombre_familia']) {
        $familiaActual = $p['nombre_familia'];
        $labelFamilia = !empty($familiaActual) ? strtoupper($familiaActual) : "SIN FAMILIA DEFINIDA";
        echo "<tr><td colspan='6' class='familia-row'>FAMILIA: $labelFamilia</td></tr>";
        echo "<tr>
                <th>CÓDIGO</th>
                <th>DESCRIPCIÓN</th>
                <th>RUBRO / MARCA</th>
                <th align='right'>NETO</th>
                <th align='right'>IVA</th>
                <th align='right'>TOTAL</th>
              </tr>";
    }

    echo "<tr>";
    // Aplicamos la clase que fuerza el formato texto y la alineación derecha
    echo "<td class='text-force-right'>" . $p['codigo'] . "</td>";
    echo "<td>" . htmlspecialchars($p['descripcion']) . "</td>";
    echo "<td>" . $p['rubro'] . " / " . ($p['nombre_marca'] ?: '-') . "</td>";
    echo "<td align='right' class='num'>" . number_format($p['neto'], 2, ',', '.') . "</td>";
    echo "<td align='right' class='num'>" . number_format($p['iva'], 2, ',', '.') . "</td>";
    echo "<td align='right' class='num' style='font-weight:bold; color:#0055aa;'>" . number_format($p['total'], 2, ',', '.') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "</body></html>";