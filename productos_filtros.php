<?php
// Compartido por listado y exportación; alias de productos: p.
function aplicarFiltrosProductos(&$sql, &$params, $filtros) {
    $q = $filtros['q'] ?? '';
    $rubro = $filtros['rubro'] ?? '';
    $marca = $filtros['marca'] ?? '';
    $familia = $filtros['familia'] ?? '';
    $proveedor = $filtros['proveedor'] ?? '';
    $habilitado = $filtros['habilitado'] ?? 'todos';
    $stock_bajo = ($filtros['stock_bajo'] ?? '') === 'true';
    if (!empty($q)) {
        $sql .= " AND (p.codigo LIKE ? OR p.descripcion LIKE ? OR p.rubro LIKE ? OR p.codigo IN (SELECT refierea FROM referenciasproductos WHERE codigo LIKE ?))";
        $term = "%$q%";
        array_push($params, $term, $term, $term, $term);
    }
    if (!empty($rubro)) { $sql .= " AND p.rubro = ?"; $params[] = $rubro; }
    if (!empty($marca)) { $sql .= " AND p.marca = ?"; $params[] = $marca; }
    if (!empty($familia)) { $sql .= " AND p.familia = ?"; $params[] = $familia; }
    if (!empty($proveedor)) { $sql .= " AND p.proveedor = ?"; $params[] = $proveedor; }
    if ($habilitado !== 'todos') { $sql .= " AND p.habilitado = ?"; $params[] = $habilitado; }
    if ($stock_bajo) { $sql .= " AND p.stock <= p.StockMin"; }

}
