<?php
/**
 * api_vendedores.php - LÓGICA DE NEGOCIO PARA VENDEDORES
 */
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

// Función para replicar el "Capitalizar" de VB.NET (ProperCase)
function capitalizar($texto) {
    return mb_convert_case(trim($texto), MB_CASE_TITLE, "UTF-8");
}

try {
    switch ($action) {
        case 'list':
        case 'export_excel':
            $sql = "SELECT id, nombre, direccion, localidad, provincia, cp, telefono, celular, comision, mail, habilitado 
                    FROM vendedores ORDER BY nombre ASC";
            $res = $pdo->query($sql)->fetchAll();

            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'get':
            $id = (int)$_GET['id'];
            $stmt = $pdo->prepare("SELECT * FROM vendedores WHERE id = ?");
            $stmt->execute([$id]);
            $vendedor = $stmt->fetch();
            echo json_encode(['success' => true, 'data' => $vendedor]);
            break;

        case 'save':
            $data = json_decode(file_get_contents('php://input'), true);
            $id = (int)($data['id'] ?? 0);
            $nombre = capitalizar($data['nombre'] ?? '');

            if (empty($nombre)) throw new Exception("No puede dejar en blanco el nombre");

            $campos = [
                'nombre' => $nombre,
                'direccion' => capitalizar($data['direccion'] ?? ''),
                'localidad' => capitalizar($data['localidad'] ?? ''),
                'provincia' => capitalizar($data['provincia'] ?? ''),
                'cp' => trim($data['cp'] ?? ''),
                'telefono' => trim($data['telefono'] ?? ''),
                'celular' => trim($data['celular'] ?? ''),
                'comision' => floatval($data['comision'] ?? 0),
                'habilitado' => !empty($data['habilitado']) ? 1 : 0,
                'mail' => trim($data['mail'] ?? '')
            ];

            if ($id > 0) {
                // UPDATE
                $sql = "UPDATE vendedores SET nombre=:nombre, direccion=:direccion, localidad=:localidad, 
                        provincia=:provincia, cp=:cp, telefono=:telefono, celular=:celular, 
                        comision=:comision, habilitado=:habilitado, mail=:mail WHERE id = :id";
                $campos['id'] = $id;
            } else {
                // INSERT
                $sql = "INSERT INTO vendedores (nombre, direccion, localidad, provincia, cp, telefono, celular, comision, habilitado, mail) 
                        VALUES (:nombre, :direccion, :localidad, :provincia, :cp, :telefono, :celular, :comision, :habilitado, :mail)";
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($campos);
            echo json_encode(['success' => true, 'message' => "Realizado correctamente"]);
            break;

        case 'delete':
            $id = (int)json_decode(file_get_contents('php://input'), true)['id'];
            // Validar que no tenga clientes asociados antes de borrar
            $check = $pdo->prepare("SELECT COUNT(*) FROM alb_clientes WHERE vendedorid = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) throw new Exception("No se puede eliminar el vendedor porque tiene clientes asociados.");

            $pdo->prepare("DELETE FROM vendedores WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}