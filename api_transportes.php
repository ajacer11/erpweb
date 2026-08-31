<?php
require_once 'check_api_access.php';

$action = $_GET['action'] ?? '';

// Función auxiliar similar a Capitalizar de VB.NET
function properCase($text) {
    return mb_convert_case(mb_strtolower($text), MB_CASE_TITLE, "UTF-8");
}

switch($action) {
    case 'list':
        $filter = $_GET['filter'] ?? '';
        $sql = "SELECT id, razon, domicilio, localidad, telefono, mail, habilitado FROM transportes 
                WHERE razon LIKE ? OR localidad LIKE ? ORDER BY razon ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(["%$filter%", "%$filter%"]);
        echo json_encode($stmt->fetchAll());
        break;

    case 'get':
        $id = $_GET['id'] ?? 0;
        $stmt = $pdo->prepare("SELECT * FROM transportes WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true, 'data' => $stmt->fetch()]);
        break;

    case 'save':
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Simulación de validacuit del VB.NET
        if ($input['tipodoc'] == 'CUIT' && strlen($input['numdoc']) < 11) {
            echo json_encode(['success' => false, 'error' => 'CUIT Inválido']);
            exit;
        }

        $params = [
            properCase($input['razon']),
            properCase($input['domicilio']),
            properCase($input['localidad']),
            properCase($input['provincia']),
            $input['cp'],
            $input['numdoc'],
            $input['condicionfiscal'],
            $input['telefono'],
            $input['celular'],
            $input['mail'],
            properCase($input['contacto']),
            $input['tipodoc'],
            $input['habilitado'],
            $input['horario']
        ];

        if ($input['id'] > 0) {
            $sql = "UPDATE transportes SET razon=?, domicilio=?, localidad=?, provincia=?, cp=?, numdoc=?, 
                    condicionfiscal=?, telefono=?, celular=?, mail=?, contacto=?, tipodoc=?, habilitado=?, horario=? 
                    WHERE id = ?";
            $params[] = $input['id'];
        } else {
            $sql = "INSERT INTO transportes (razon, domicilio, localidad, provincia, cp, numdoc, 
                    condicionfiscal, telefono, celular, mail, contacto, tipodoc, habilitado, horario) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
        }

        $stmt = $pdo->prepare($sql);
        $res = $stmt->execute($params);
        echo json_encode(['success' => $res]);
        break;
}