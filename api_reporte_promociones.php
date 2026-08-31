<?php
/**
 * api_reporte_promociones.php — BACKEND REPORTE DE PROMOCIONES APLICADAS
 * Consulta promociones_ventas cruzada con promociones, alb_clientes y vendedores.
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {

        // ── Listar registros con filtros ──────────────────────────────────
        case 'consultar':
            $input  = json_decode(file_get_contents('php://input'), true);
            $desde  = $input['desde']      ?? date('Y-m-d');
            $hasta  = $input['hasta']      ?? date('Y-m-d');
            $promo  = (int)($input['promo_id']   ?? 0);
            $vend   = (int)($input['vendedor_id'] ?? 0);
            $cli    = (int)($input['cliente_id']  ?? 0);

            $where  = ['pv.fecha BETWEEN ? AND ?'];
            $params = [$desde, $hasta];

            if ($promo > 0) { $where[] = 'pv.idpromo = ?';     $params[] = $promo; }
            if ($vend  > 0) { $where[] = 'pv.vendedor_id = ?'; $params[] = $vend;  }
            if ($cli   > 0) { $where[] = 'pv.cliente_id = ?';  $params[] = $cli;   }

            $sql = "SELECT
                        pv.id,
                        pv.fecha,
                        pv.idpromo,
                        pr.nombre          AS promo_nombre,
                        pr.tipo            AS promo_tipo,
                        pv.tipocomp,
                        pv.ptovta,
                        pv.numcomp,
                        pv.idticket,
                        pv.codigo_prod,
                        pv.cantidad,
                        pv.descuento_pct,
                        pv.descuento_total,
                        c.razon            AS cliente_nombre,
                        v.nombre           AS vendedor_nombre,
                        pv.usuario
                    FROM  promociones_ventas pv
                    INNER JOIN promociones   pr ON pr.id  = pv.idpromo
                    LEFT  JOIN alb_clientes   c ON c.id   = pv.cliente_id
                    LEFT  JOIN vendedores      v ON v.id   = pv.vendedor_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY pv.fecha DESC, pv.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Totales agregados
            $totCantidad  = array_sum(array_column($rows, 'cantidad'));
            $totDescuento = array_sum(array_column($rows, 'descuento_total'));

            // Agrupado por promoción (para los stats)
            $porPromo = [];
            foreach ($rows as $r) {
                $k = $r['idpromo'];
                if (!isset($porPromo[$k])) {
                    $porPromo[$k] = [
                        'nombre'    => $r['promo_nombre'],
                        'tipo'      => $r['promo_tipo'],
                        'usos'      => 0,
                        'cantidad'  => 0,
                        'descuento' => 0,
                    ];
                }
                $porPromo[$k]['usos']++;
                $porPromo[$k]['cantidad']  += $r['cantidad'];
                $porPromo[$k]['descuento'] += $r['descuento_total'];
            }
            usort($porPromo, fn($a, $b) => $b['descuento'] <=> $a['descuento']);

            echo json_encode([
                'success'      => true,
                'data'         => $rows,
                'tot_cantidad' => $totCantidad,
                'tot_descuento'=> $totDescuento,
                'por_promo'    => array_values($porPromo),
            ]);
            break;

        // ── Combos para filtros ───────────────────────────────────────────
        case 'get_filtros':
            $promos = $pdo->query("SELECT id, nombre FROM promociones ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
            $vends  = $pdo->query("SELECT id, nombre FROM vendedores  ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'promociones' => $promos, 'vendedores' => $vends]);
            break;

        // ── Exportar Excel ────────────────────────────────────────────────
        case 'exportar':
            $desde  = $_GET['desde']      ?? date('Y-m-d');
            $hasta  = $_GET['hasta']      ?? date('Y-m-d');
            $promo  = (int)($_GET['promo_id']   ?? 0);
            $vend   = (int)($_GET['vendedor_id'] ?? 0);
            $cli    = (int)($_GET['cliente_id']  ?? 0);

            $where  = ['pv.fecha BETWEEN ? AND ?'];
            $params = [$desde, $hasta];
            if ($promo > 0) { $where[] = 'pv.idpromo = ?';     $params[] = $promo; }
            if ($vend  > 0) { $where[] = 'pv.vendedor_id = ?'; $params[] = $vend;  }
            if ($cli   > 0) { $where[] = 'pv.cliente_id = ?';  $params[] = $cli;   }

            $sql = "SELECT pv.fecha,
                           pr.nombre AS promo_nombre,
                           pv.ptovta, pv.tipocomp, pv.numcomp,
                           c.razon AS cliente_nombre,
                           pv.codigo_prod, pv.cantidad,
                           pv.descuento_pct, pv.descuento_total,
                           v.nombre AS vendedor_nombre, pv.usuario
                    FROM promociones_ventas pv
                    INNER JOIN promociones pr ON pr.id = pv.idpromo
                    LEFT JOIN alb_clientes  c ON c.id  = pv.cliente_id
                    LEFT JOIN vendedores    v ON v.id  = pv.vendedor_id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY pv.fecha DESC, pv.id DESC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $filename = "Reporte_Promociones_" . date('Ymd_His') . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");

            $totDesc = 0;
            echo "<html xmlns:o='urn:schemas-microsoft-com:office:office'
                        xmlns:x='urn:schemas-microsoft-com:office:excel'
                        xmlns='http://www.w3.org/TR/REC-html40'>
            <head><meta charset='utf-8'>
            <style>
                .th  { background:#0d2b45; color:#00ffff; font-weight:bold; border:1px solid #999; padding:5px; text-align:center; }
                .td  { border:1px solid #ccc; padding:4px; }
                .num { text-align:right; mso-number-format:\"\\#\\,\\#\\#0\\.00\"; }
                .hdr { background:#000020; color:#00ffff; font-size:14pt; font-weight:bold; text-align:center; }
            </style></head><body><table>
            <tr><td colspan='11' class='hdr'>REPORTE DE PROMOCIONES APLICADAS — WEST IT ERP</td></tr>
            <tr><td colspan='11' style='text-align:center;'>Período: $desde al $hasta</td></tr>
            <tr><td colspan='11'></td></tr>
            <thead><tr>
                <th class='th'>FECHA</th><th class='th'>PROMOCIÓN</th>
                <th class='th'>COMPROBANTE</th><th class='th'>CLIENTE</th>
                <th class='th'>CÓDIGO</th><th class='th'>CANT.</th>
                <th class='th'>DESC %</th><th class='th'>DESC $</th>
                <th class='th'>VENDEDOR</th><th class='th'>USUARIO</th>
            </tr></thead><tbody>";

            foreach ($rows as $r) {
                $totDesc += (float)$r['descuento_total'];
                $comp = str_pad($r['ptovta'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($r['numcomp'], 8, '0', STR_PAD_LEFT);
                echo "<tr>
                    <td class='td' style='text-align:center'>" . date('d/m/Y', strtotime($r['fecha'])) . "</td>
                    <td class='td'>" . htmlspecialchars($r['promo_nombre']) . "</td>
                    <td class='td' style='text-align:center'>$comp</td>
                    <td class='td'>" . htmlspecialchars($r['cliente_nombre'] ?? '-') . "</td>
                    <td class='td' style='text-align:center'>" . htmlspecialchars($r['codigo_prod']) . "</td>
                    <td class='td num'>" . number_format($r['cantidad'], 2, ',', '.') . "</td>
                    <td class='td num'>" . number_format($r['descuento_pct'], 2, ',', '.') . "%</td>
                    <td class='td num'>" . number_format($r['descuento_total'], 2, ',', '.') . "</td>
                    <td class='td'>" . htmlspecialchars($r['vendedor_nombre'] ?? '-') . "</td>
                    <td class='td'>" . htmlspecialchars($r['usuario']) . "</td>
                </tr>";
            }

            echo "<tr>
                <td colspan='7' style='text-align:right;font-weight:bold;background:#f0f0f0;border:1px solid #ccc;'>TOTAL DESCUENTOS OTORGADOS:</td>
                <td class='num' style='font-weight:bold;background:#f0f0f0;border:1px solid #ccc;'>" . number_format($totDesc, 2, ',', '.') . "</td>
                <td colspan='2' style='background:#f0f0f0;border:1px solid #ccc;'></td>
            </tr>
            </tbody></table></body></html>";
            exit;

        default:
            throw new Exception("Acción no válida.");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>