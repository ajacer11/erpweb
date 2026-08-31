<?php
/**
 * api_ctas_por_vendedor.php - MOTOR DE SALDOS POR CARTERA
 */
require_once 'check_api_access.php';
require_once 'permisos_helpers.php';

$action = $_GET['action'] ?? '';

try {
    if ($action === 'get_vendedores') {
        $q = $_GET['q'] ?? '';
        $params = ["%$q%"];
        $sql = "SELECT id, nombre FROM vendedores WHERE nombre LIKE ?";
        // Seguridad: un vendedor restringido solo ve su propia cartera en el selector.
        if (esVendedorRestringido()) {
            $sql .= " AND id = ?";
            $params[] = idVendedorRestringido();
        }
        $sql .= " ORDER BY nombre ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo json_encode($stmt->fetchAll());
        exit;
    }

    if ($action === 'get_data') {
        $vendedor_id = (int)($_GET['vendedor_id'] ?? 0);
        $resumen = ($_GET['resumen'] ?? 0) == 1;
        $params = [];

        // Seguridad: Vendedor restringido (se fuerza, ignorando lo que pida el front)
        if (esVendedorRestringido()) {
            $vendedor_id = idVendedorRestringido();
        }

        $where = " WHERE 1=1 ";
        if ($vendedor_id > 0) {
            $where .= " AND a.vendedorid = ? ";
            $params[] = $vendedor_id;
        }

        if ($resumen) {
            // MODO RESUMEN: Total adeudado por cada vendedor
            $sql = "SELECT 
                        v.id as vendedor_id,
                        v.nombre as Vendedor,
                        ROUND(SUM(c.debe - c.haber), 2) as Saldo
                    FROM ccclientes c
                    INNER JOIN alb_clientes a ON a.id = c.cliente
                    INNER JOIN vendedores v ON v.id = a.vendedorid
                    $where
                    GROUP BY v.id
                    HAVING Saldo <> 0
                    ORDER BY v.id ASC";
        } else {
            // MODO DETALLADO: Cliente por cliente
            $sql = "SELECT 
                        c.cliente as id_cliente,
                        a.razon as Cliente,
                        v.nombre as Vendedor,
                        ROUND(SUM(c.debe - c.haber), 2) as Saldo
                    FROM ccclientes c
                    INNER JOIN alb_clientes a ON a.id = c.cliente
                    INNER JOIN vendedores v ON v.id = a.vendedorid
                    $where
                    GROUP BY c.cliente
                    HAVING Saldo <> 0
                    ORDER BY a.vendedorid ASC, c.cliente ASC";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        $totalGral = array_sum(array_column($data, 'Saldo'));

        echo json_encode([
            'success' => true, 
            'data' => $data, 
            'total_general' => $totalGral
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
