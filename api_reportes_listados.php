<?php
// SEGURIDAD CENTRALIZADA API
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';
$entity = $_GET['entity'] ?? '';

try {
    if ($action === 'list' || $action === 'export') {
        
        $sql = "";
        $title = "";

        // Definición de Consultas por Entidad
        switch ($entity) {
            case 'clientes':
                $title = "LISTADO DE CLIENTES";
                $sql = "SELECT 
                            id AS 'ID', 
                            razon AS 'Razón Social', 
                            numdoc AS 'CUIT/DNI', 
                            domicilio AS 'Domicilio', 
                            localidad AS 'Localidad', 
                            provincia AS 'Provincia', 
                            telefono AS 'Teléfono', 
                            mail AS 'Email' 
                        FROM alb_clientes 
                        WHERE habilitado = 1 
                        ORDER BY razon ASC";
                break;

            case 'productos':
                $title = "LISTADO DE PRODUCTOS";
                // Join con Marcas para mostrar nombre en lugar de ID
                $sql = "SELECT 
                            p.codigo AS 'Código', 
                            p.Descripcion AS 'Descripción', 
                            p.rubro AS 'Rubro', 
                            m.marca AS 'Marca', 
                            p.preciocosto AS 'Costo', 
                            p.precio AS 'Precio Venta', 
                            p.stock AS 'Stock' 
                        FROM alb_productos p
                        LEFT JOIN Marcas m ON p.marca = m.id
                        WHERE p.habilitado = 1 
                        ORDER BY p.Descripcion ASC";
                break;

            case 'proveedores':
                $title = "LISTADO DE PROVEEDORES";
                // OJO: Tabla con P mayúscula por instrucción estricta
                $sql = "SELECT 
                            id AS 'ID', 
                            razon AS 'Razón Social', 
                            cuit AS 'CUIT', 
                            domicilio AS 'Domicilio', 
                            localidad AS 'Localidad', 
                            provincia AS 'Provincia', 
                            telefono AS 'Teléfono',
                            mail AS 'Email'
                        FROM Proveedores 
                        WHERE habilitado = 1 
                        ORDER BY razon ASC";
                break;

            case 'vendedores':
                $title = "LISTADO DE VENDEDORES";
                $sql = "SELECT 
                            id AS 'ID', 
                            nombre AS 'Nombre', 
                            direccion AS 'Domicilio', 
                            telefono AS 'Teléfono', 
                            mail AS 'Email',
                            comision AS '% Com.'
                        FROM vendedores 
                        WHERE habilitado = 1 
                        ORDER BY nombre ASC";
                break;

            case 'usuarios':
                $title = "LISTADO DE USUARIOS DEL SISTEMA";
                $sql = "SELECT 
                            id AS 'ID', 
                            usuario AS 'Usuario', 
                            nivel AS 'Nivel',
                            CASE WHEN (SELECT estado FROM cajas WHERE usuarioactivo = alb_usuarios.usuario) = 1 THEN 'ABIERTA' ELSE 'CERRADA' END AS 'Caja', 
                            CASE WHEN esadmin = 1 THEN 'SI' ELSE 'NO' END AS 'Es Admin'
                        FROM alb_usuarios 
                        WHERE habilitado = 1 and usuario NOT IN ('angel', 'ivan', 'sistema', 'admin', 'westit')
                        ORDER BY usuario ASC";
                break;

            case 'transportes':
                $title = "LISTADO DE TRANSPORTES";
                $sql = "SELECT 
                            id AS 'ID', 
                            razon AS 'Razón Social', 
                            domicilio AS 'Domicilio', 
                            localidad AS 'Localidad', 
                            telefono AS 'Teléfono' 
                        FROM transportes 
                        WHERE habilitado = 1 
                        ORDER BY razon ASC";
                break;

            default:
                throw new Exception("Entidad no válida o no especificada.");
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($action === 'export') {
            // Generar Excel HTML
            ob_start();
            ?>
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head><meta charset="utf-8" /></head>
            <body>
                <h3><?= $title ?></h3>
                <p><strong>Fecha Emisión:</strong> <?= date('d/m/Y H:i') ?></p>
                <table border="1">
                    <thead>
                        <tr style="background-color:#0d2b45; color:white;">
                            <?php 
                            if (!empty($data)) {
                                foreach (array_keys($data[0]) as $key) {
                                    echo "<th>" . htmlspecialchars($key) . "</th>";
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($data as $r): ?>
                        <tr>
                            <?php foreach($r as $v): 
                                $align = is_numeric($v) ? 'right' : 'left';
                            ?>
                                <td align="<?= $align ?>"><?= htmlspecialchars($v) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </body>
            </html>
            <?php
            $html = ob_get_clean();
            echo json_encode([
                'status' => 'ok', 
                'file_content' => base64_encode($html), 
                'filename' => str_replace(' ', '_', $title) . '_' . date('Ymd') . '.xls'
            ]);
        } else {
            // JSON para la grilla
            echo json_encode(['status' => 'ok', 'data' => $data]);
        }
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'error' => $e->getMessage()]);
}
?>