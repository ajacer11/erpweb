<?php
/**
 * api_proveedores_admin.php - MOTOR DE DATOS PARA ADMINISTRACIÓN DE PROVEEDORES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $q = $_GET['q'] ?? '';
            $inhabilitados = ($_GET['inhabilitados'] ?? 'false') === 'true';
            
            $params = [];
            $where = " WHERE 1=1 ";
            
            if (!$inhabilitados) {
                $where .= " AND habilitado = 1 ";
            }

            if (!empty($q)) {
                $where .= " AND (Id LIKE ? OR Razon LIKE ? OR cuit LIKE ? OR domicilio LIKE ?) ";
                $search = "%$q%";
                $params = array_fill(0, 4, $search);
            }

            $sql = "SELECT Id, Razon, cuit, condicion, localidad, telefono, mail, habilitado 
                    FROM Proveedores $where 
                    ORDER BY Razon ASC LIMIT 500";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll());
            break;

        case 'export':
            // Lógica simplificada de exportación CSV (Excel compatible)
            $q = $_GET['q'] ?? '';
            $inhabilitados = ($_GET['inhabilitados'] ?? 'false') === 'true';
            
            $params = [];
            $where = " WHERE 1=1 ";
            if (!$inhabilitados) $where .= " AND habilitado = 1 ";
            if (!empty($q)) {
                $where .= " AND (Id LIKE ? OR Razon LIKE ? OR cuit LIKE ? OR domicilio LIKE ?) ";
                $search = "%$q%";
                $params = array_fill(0, 4, $search);
            }

            $sql = "SELECT Id, Razon, domicilio, localidad, provincia, cp, cuit, condicion, telefono, celular, mail, contacto, web, notas 
                    FROM Proveedores $where ORDER BY Razon ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();

            $filename = "Padron_Proveedores_" . date('Ymd_His') . ".xls";
            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"$filename\"");
            
            echo "<table border='1'>
                    <tr>
                        <th style='background:#001020;color:#fff'>ID</th>
                        <th style='background:#001020;color:#fff'>NOMBRE</th>
                        <th style='background:#001020;color:#fff'>CUIT</th>
                        <th style='background:#001020;color:#fff'>CONDICION</th>
                        <th style='background:#001020;color:#fff'>DOMICILIO</th>
                        <th style='background:#001020;color:#fff'>LOCALIDAD</th>
                        <th style='background:#001020;color:#fff'>PROVINCIA</th>
                        <th style='background:#001020;color:#fff'>CONTACTO</th>
                        <th style='background:#001020;color:#fff'>MAIL</th>
                    </tr>";
            foreach($data as $r) {
                echo "<tr>
                        <td>{$r['Id']}</td>
                        <td>" . htmlspecialchars($r['Razon']) . "</td>
                        <td>{$r['cuit']}</td>
                        <td>{$r['condicion']}</td>
                        <td>" . htmlspecialchars($r['domicilio']) . "</td>
                        <td>{$r['localidad']}</td>
                        <td>{$r['provincia']}</td>
                        <td>" . htmlspecialchars($r['contacto']) . "</td>
                        <td>{$r['mail']}</td>
                      </tr>";
            }
            echo "</table>";
            exit;

        default:
            throw new Exception("Acción no definida");
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}