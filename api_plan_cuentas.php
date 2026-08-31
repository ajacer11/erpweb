<?php
require_once 'check_api_access.php';
$action = $_GET['action'] ?? '';

/**
 * Función Maestra de Cálculo de Numeración (REGLA DE ORO)
 * Basada en máscara 1-1-1-2-3 (Total 8 dígitos)
 * Niveles: 1, 2, 3, 4, 5
 */
function calcularProximoCodigo($pdo, $madre_cod = '') {
    if (empty($madre_cod)) {
        // NIVEL 1: Primer dígito (X0000000)
        $pattern = "_0000000";
        $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numcuenta, 1, 1) AS UNSIGNED)) FROM plandecuentas WHERE numcuenta LIKE ? FOR UPDATE");
        $stmt->execute([$pattern]);
        $max = (int)$stmt->fetchColumn();
        if ($max >= 9) throw new Exception("Límite de cuentas raíz alcanzado (9).");
        return ($max + 1) . "0000000";
    }

    // Determinamos nivel de la madre por posición de ceros (Determinístico)
    if (substr($madre_cod, 1) === "0000000") $lvl_madre = 1;      
    elseif (substr($madre_cod, 2) === "000000") $lvl_madre = 2;  
    elseif (substr($madre_cod, 3) === "00000") $lvl_madre = 3;   
    elseif (substr($madre_cod, 5) === "000") $lvl_madre = 4;     
    else throw new Exception("La cuenta seleccionada es Nivel 5 y no admite subcuentas.");

    $lvl_hija = $lvl_madre + 1;

    switch ($lvl_hija) {
        case 2: // Segundo dígito (X_000000)
            $prefix = substr($madre_cod, 0, 1);
            $pattern = $prefix . "_000000";
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numcuenta, 2, 1) AS UNSIGNED)) FROM plandecuentas WHERE numcuenta LIKE ? FOR UPDATE");
            $stmt->execute([$pattern]);
            $max = (int)$stmt->fetchColumn();
            if ($max >= 9) throw new Exception("Límite de subcuentas Nivel 2 alcanzado (9).");
            return $prefix . ($max + 1) . "000000";

        case 3: // Tercer dígito (XY_00000)
            $prefix = substr($madre_cod, 0, 2);
            $pattern = $prefix . "_00000";
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numcuenta, 3, 1) AS UNSIGNED)) FROM plandecuentas WHERE numcuenta LIKE ? FOR UPDATE");
            $stmt->execute([$pattern]);
            $max = (int)$stmt->fetchColumn();
            if ($max >= 9) throw new Exception("Límite de subcuentas Nivel 3 alcanzado (9).");
            return $prefix . ($max + 1) . "00000";

        case 4: // Cuarto y Quinto dígito (XYZ__000)
            $prefix = substr($madre_cod, 0, 3);
            $pattern = $prefix . "__000";
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numcuenta, 4, 2) AS UNSIGNED)) FROM plandecuentas WHERE numcuenta LIKE ? FOR UPDATE");
            $stmt->execute([$pattern]);
            $max = (int)$stmt->fetchColumn();
            if ($max >= 99) throw new Exception("Límite de subcuentas Nivel 4 alcanzado (99).");
            return $prefix . str_pad($max + 1, 2, "0", STR_PAD_LEFT) . "000";

        case 5: // Sexto, Séptimo y Octavo dígito (XYZAABBB)
            $prefix = substr($madre_cod, 0, 5);
            $pattern = $prefix . "___";
            $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numcuenta, 6, 3) AS UNSIGNED)) FROM plandecuentas WHERE numcuenta LIKE ? FOR UPDATE");
            $stmt->execute([$pattern]);
            $max = (int)$stmt->fetchColumn();
            if ($max >= 999) throw new Exception("Límite de cuentas Nivel 5 alcanzado (999).");
            return $prefix . str_pad($max + 1, 3, "0", STR_PAD_LEFT);
    }
}

try {
    if ($action === 'get_all') {
        $sql = "SELECT * FROM plandecuentas ORDER BY numcuenta ASC";
        echo json_encode(['success' => true, 'data' => $pdo->query($sql)->fetchAll()]);
    }

    if ($action === 'get_next') {
        $madre = $_GET['madre'] ?? '';
        $pdo->beginTransaction();
        try {
            $proximo = calcularProximoCodigo($pdo, $madre);
            
            // Determinar jerarquía real de la hija (1 a 5)
            if (substr($proximo, 1) === "0000000") $jerarquia = 1;
            elseif (substr($proximo, 2) === "000000") $jerarquia = 2;
            elseif (substr($proximo, 3) === "00000") $jerarquia = 3;
            elseif (substr($proximo, 5) === "000") $jerarquia = 4;
            else $jerarquia = 5;
            
            echo json_encode(['success' => true, 'proximo' => $proximo, 'jerarquia' => $jerarquia]);
            $pdo->rollBack();
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    if ($action === 'save') {
        $input = json_decode(file_get_contents('php://input'), true);
        $editMode = (int)$input['editMode'];
        
        $pdo->beginTransaction();
        try {
            if ($editMode === 0) {
                // ALTA: El backend es el único juez del número
                $madre = $input['cuentamadre'] ?? '';
                $numReal = calcularProximoCodigo($pdo, $madre);
                
                // Jerarquía 1-5
                if (substr($numReal, 1) === "0000000") $jerarquia = 1;
                elseif (substr($numReal, 2) === "000000") $jerarquia = 2;
                elseif (substr($numReal, 3) === "00000") $jerarquia = 3;
                elseif (substr($numReal, 5) === "000") $jerarquia = 4;
                else $jerarquia = 5;

                $imputable = ($jerarquia === 5) ? 1 : (int)$input['imputable'];

                $sql = "INSERT INTO plandecuentas (numcuenta, descripcion, habilitada, imputable, cuentamadre, patrimonial, jerarquia, observaciones) 
                        VALUES (:num, :desc, :hab, :imp, :madre, :pat, :jer, :obs)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':num' => $numReal,
                    ':desc' => $input['descripcion'],
                    ':hab' => $input['habilitada'],
                    ':imp' => $imputable,
                    ':madre' => $madre,
                    ':pat' => $input['patrimonial'],
                    ':jer' => $jerarquia,
                    ':obs' => $input['observaciones']
                ]);
            } else {
                // EDICIÓN
                $sql = "UPDATE plandecuentas SET 
                            descripcion = :desc, 
                            habilitada = :hab, 
                            patrimonial = :pat, 
                            observaciones = :obs 
                        WHERE numcuenta = :num";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':num' => $input['numcuenta'],
                    ':desc' => $input['descripcion'],
                    ':hab' => $input['habilitada'],
                    ':pat' => $input['patrimonial'],
                    ':obs' => $input['observaciones']
                ]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    if ($action === 'delete') {
        $num = $_GET['numcuenta'];
        $stmtMovs = $pdo->prepare("SELECT COUNT(*) FROM librodiario WHERE cuenta = ?");
        $stmtMovs->execute([$num]);
        if ((int)$stmtMovs->fetchColumn() > 0) throw new Exception("No se puede eliminar: la cuenta registra movimientos contables.");
        
        $stmtHijas = $pdo->prepare("SELECT COUNT(*) FROM plandecuentas WHERE cuentamadre = ?");
        $stmtHijas->execute([$num]);
        if ((int)$stmtHijas->fetchColumn() > 0) throw new Exception("No se puede eliminar: la cuenta tiene subcuentas vinculadas.");

        $stmtDel = $pdo->prepare("DELETE FROM plandecuentas WHERE numcuenta = ?");
        $stmtDel->execute([$num]);
        echo json_encode(['success' => true]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}