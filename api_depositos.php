<?php
/**
 * api_depositos.php - ABM DE DEPÓSITOS, DEPÓSITO DE TRABAJO Y TRANSFERENCIAS
 */
require_once 'check_api_access.php';
require_once 'stock_deposito_helpers.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';
$esAdmin = (int) ($_SESSION['es_admin'] ?? 0) === 1;
$input = json_decode(file_get_contents('php://input'), true) ?? [];

function requireAdminDeposito(bool $esAdmin): void
{
    if (!$esAdmin) {
        throw new Exception('Esta acción requiere permisos de administrador.');
    }
}

/** Convierte un data URL validado a BLOB. null significa conservar el logo actual. */
function decodificarLogoDeposito($dataUrl): ?string
{
    if ($dataUrl === null || $dataUrl === '') return null;
    if (!is_string($dataUrl) || !preg_match('#^data:(image/(?:png|jpeg|webp));base64,(.+)$#s', $dataUrl, $m)) {
        throw new Exception('El logo debe ser una imagen PNG, JPG o WebP válida.');
    }
    $bin = base64_decode($m[2], true);
    if ($bin === false || strlen($bin) > 2 * 1024 * 1024) {
        throw new Exception('El logo no es válido o supera los 2 MB.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bin);
    if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
        throw new Exception('El logo debe ser una imagen PNG, JPG o WebP válida.');
    }
    return $bin;
}

/**
 * Trae una transferencia + sus líneas, validando que esté en uno de los
 * estados permitidos para la acción que la llama y que tenga un depósito de
 * tránsito registrado (siempre lo tiene si pasó por PENDIENTE_TRANSITO/
 * EN_TRANSITO/PARCIAL).
 */
function cargarTransferenciaConLineas(PDO $pdo, int $id, array $estadosPermitidos): array
{
    if (!$id) {
        throw new Exception('Transferencia inválida.');
    }
    $stmt = $pdo->prepare("SELECT * FROM transferencias_deposito WHERE id = ?");
    $stmt->execute([$id]);
    $transf = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$transf) {
        throw new Exception('Transferencia no encontrada.');
    }
    if (!in_array($transf['estado'], $estadosPermitidos, true)) {
        throw new Exception('Esta transferencia no está en un estado válido para esta acción.');
    }
    if (!(int) ($transf['id_deposito_transito'] ?? 0)) {
        throw new Exception('Esta transferencia no tiene registrado un depósito de tránsito.');
    }
    $stmtLineas = $pdo->prepare("SELECT * FROM transferencias_deposito_lineas WHERE id_transferencia = ?");
    $stmtLineas->execute([$id]);
    return [$transf, $stmtLineas->fetchAll(PDO::FETCH_ASSOC)];
}

try {
    switch ($action) {

        case 'logo':
            $stmt = $pdo->prepare('SELECT logo FROM depositos WHERE id = ?');
            $stmt->execute([(int) ($_GET['id'] ?? 0)]);
            $blob = $stmt->fetchColumn();
            if (empty($blob)) {
                http_response_code(404);
                exit;
            }
            header('Content-Type: ' . ((new finfo(FILEINFO_MIME_TYPE))->buffer($blob) ?: 'image/png'));
            header('Cache-Control: private, max-age=31536000, immutable'); // la URL lleva el hash del logo
            header_remove('Pragma');
            header_remove('Expires');
            echo $blob;
            exit;

        case 'estado_global':
            echo json_encode(['success' => true, 'habilitado' => multidepositoHabilitado($pdo)]);
            break;

        // -------------------------------------------------------------
        // BOOTSTRAP: activa multidepósito por primera vez para el tenant
        // -------------------------------------------------------------
        case 'activar':
            requireAdminDeposito($esAdmin);
            if (multidepositoHabilitado($pdo)) {
                throw new Exception('El multidepósito ya está activado.');
            }

            $pdo->beginTransaction();

            $idPrincipal = $pdo->query("SELECT id FROM depositos WHERE esprincipal = 1 LIMIT 1")->fetchColumn();
            if (!$idPrincipal) {
                // El principal hereda la identidad operativa vigente. De ese modo activar
                // multidepósito no cambia ni el encabezado ni el PV de la empresa actual.
                $empresa = $pdo->query("SELECT domicilio, localidad, provincia, telefono, email, puntodeventa FROM datosempresa ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
                $logo = $pdo->query("SELECT logo FROM configuracion ORDER BY id DESC LIMIT 1")->fetchColumn();
                $pdo->prepare("INSERT INTO depositos
                        (nombre, descripcion, esprincipal, habilitado, usuariocreacion, domicilio, localidad, provincia, telefono, email, codigopostal, puntodeventa, logo)
                        VALUES ('Depósito Principal', 'Creado automáticamente al activar multidepósito', 1, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$usuario, $empresa['domicilio'] ?? '', $empresa['localidad'] ?? '', $empresa['provincia'] ?? '',
                        $empresa['telefono'] ?? '', $empresa['email'] ?? '', '', (int) ($empresa['puntodeventa'] ?? 0), $logo ?: null]);
                $idPrincipal = (int) $pdo->lastInsertId();

                // Volcado aditivo: NO se toca alb_productos.Stock (ya tiene el valor correcto).
                $stmtProd = $pdo->query("SELECT id, codigo, Stock FROM alb_productos WHERE Stock <> 0");
                $insStock = $pdo->prepare("INSERT INTO stock_por_deposito (id_producto, id_deposito, cantidad)
                                            VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE cantidad = VALUES(cantidad)");
                $insMov = $pdo->prepare("INSERT INTO movimientos_stock_deposito
                        (id_producto, codigo, id_deposito, entradas, salidas, tipomov, origenmov, tipocomp, numremito, fecha, usuario)
                        VALUES (?, ?, ?, ?, 0, 'SALDO INICIAL MULTIDEPOSITO', 'BOOTSTRAP', 0, 0, NOW(), ?)");
                while ($p = $stmtProd->fetch(PDO::FETCH_ASSOC)) {
                    $insStock->execute([$p['id'], $idPrincipal, $p['Stock']]);
                    $insMov->execute([$p['id'], $p['codigo'], $idPrincipal, $p['Stock'], $usuario]);
                }
            }

            $pdo->prepare("UPDATE configuracion SET multidepositohabilitado = 1, deposito_default_id = ?")
                ->execute([$idPrincipal]);

            $pdo->commit();
            echo json_encode(['success' => true, 'id_deposito_principal' => (int) $idPrincipal]);
            break;

        // -------------------------------------------------------------
        // ABM de depósitos
        // -------------------------------------------------------------
        case 'list':
            $stmt = $pdo->query("SELECT d.id, d.nombre, d.descripcion, d.esprincipal, d.habilitado, d.permitenegativo,
                                         d.estransito, d.domicilio, d.localidad, d.provincia, d.telefono, d.email,
                                         d.codigopostal, d.puntodeventa, (d.logo IS NOT NULL AND OCTET_LENGTH(d.logo) > 0) AS tiene_logo,
                                         COALESCE((SELECT SUM(s.cantidad) FROM stock_por_deposito s WHERE s.id_deposito = d.id), 0) AS stock_total
                                  FROM depositos d ORDER BY d.esprincipal DESC, d.nombre ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_habilitados':
            // Excluye el depósito interno "En Tránsito": nunca es un destino/depósito
            // de trabajo válido para el usuario, solo lo usa el circuito de transferencias.
            $stmt = $pdo->query("SELECT id, nombre FROM depositos WHERE habilitado = 1 AND estransito = 0 ORDER BY nombre ASC");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_permitidos':
            echo json_encode(['success' => true, 'data' => depositosPermitidosUsuario($pdo, $usuario)]);
            break;

        // Depósitos "de tránsito" disponibles, para elegir cuál usar al enviar
        // una transferencia con confirmación de recepción.
        case 'list_transito':
            echo json_encode(['success' => true, 'data' => depositosTransitoDisponibles($pdo)]);
            break;

        case 'create':
            requireAdminDeposito($esAdmin);
            $nombre = trim($input['nombre'] ?? '');
            if ($nombre === '') {
                throw new Exception('El nombre del depósito es obligatorio.');
            }
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM depositos WHERE nombre = ?");
            $stmtChk->execute([$nombre]);
            if ($stmtChk->fetchColumn() > 0) {
                throw new Exception('Ya existe un depósito con ese nombre.');
            }
            $estransito = !empty($input['estransito']) ? 1 : 0;

            $pv = (int) ($input['puntodeventa'] ?? 0);
            if (!$estransito && $pv < 1) throw new Exception('El punto de venta es obligatorio y debe ser mayor a cero.');
            $logo = decodificarLogoDeposito($input['logo'] ?? null);
            $pdo->prepare("INSERT INTO depositos (nombre, descripcion, permitenegativo, estransito, usuariocreacion,
                    domicilio, localidad, provincia, telefono, email, codigopostal, puntodeventa, logo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([$nombre, trim($input['descripcion'] ?? ''), (int) ($input['permitenegativo'] ?? 0), $estransito, $usuario,
                    trim($input['domicilio'] ?? ''), trim($input['localidad'] ?? ''), trim($input['provincia'] ?? ''),
                    trim($input['telefono'] ?? ''), trim($input['email'] ?? ''), trim($input['codigopostal'] ?? ''), $pv, $logo]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update':
            requireAdminDeposito($esAdmin);
            $id = (int) ($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('Depósito inválido.');
            }
            $stmtEs = $pdo->prepare("SELECT esprincipal, estransito FROM depositos WHERE id = ?");
            $stmtEs->execute([$id]);
            $flagsActuales = $stmtEs->fetch(PDO::FETCH_ASSOC);
            if ($flagsActuales === false) {
                throw new Exception('Depósito no encontrado.');
            }

            $nombre = trim($input['nombre'] ?? '');
            if ($nombre === '') {
                throw new Exception('El nombre del depósito es obligatorio.');
            }
            $stmtChk = $pdo->prepare("SELECT COUNT(*) FROM depositos WHERE nombre = ? AND id <> ?");
            $stmtChk->execute([$nombre, $id]);
            if ($stmtChk->fetchColumn() > 0) {
                throw new Exception('Ya existe otro depósito con ese nombre.');
            }
            $estransito = !empty($input['estransito']) ? 1 : 0;

            if ($estransito === 1 && (int) $flagsActuales['esprincipal'] === 1) {
                throw new Exception('El depósito Principal no puede marcarse como depósito de tránsito.');
            }

            // Cambiar la naturaleza (normal <-> tránsito) de un depósito que
            // todavía tiene stock generaría confusión operativa — se exige que
            // esté vacío primero (mismo criterio que deshabilitar).
            if ($estransito !== (int) $flagsActuales['estransito']) {
                $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM stock_por_deposito WHERE id_deposito = ?");
                $stmtStock->execute([$id]);
                if (round((float) $stmtStock->fetchColumn(), 2) != 0) {
                    throw new Exception('No se puede cambiar el tipo de un depósito con stock. Transfiera el stock primero.');
                }
            }

            $pv = (int) ($input['puntodeventa'] ?? 0);
            if (!$estransito && $pv < 1) throw new Exception('El punto de venta es obligatorio y debe ser mayor a cero.');
            $logo = decodificarLogoDeposito($input['logo'] ?? null);
            $sqlLogo = $logo !== null ? ', logo = ?' : '';
            $params = [$nombre, trim($input['descripcion'] ?? ''), (int) ($input['permitenegativo'] ?? 0), $estransito,
                trim($input['domicilio'] ?? ''), trim($input['localidad'] ?? ''), trim($input['provincia'] ?? ''),
                trim($input['telefono'] ?? ''), trim($input['email'] ?? ''), trim($input['codigopostal'] ?? ''), $pv];
            if ($logo !== null) $params[] = $logo;
            $params[] = $id;
            $pdo->prepare("UPDATE depositos SET nombre = ?, descripcion = ?, permitenegativo = ?, estransito = ?,
                    domicilio = ?, localidad = ?, provincia = ?, telefono = ?, email = ?, codigopostal = ?, puntodeventa = ?{$sqlLogo} WHERE id = ?")
                ->execute($params);
            echo json_encode(['success' => true]);
            break;

        case 'set_status':
            requireAdminDeposito($esAdmin);
            $id = (int) ($input['id'] ?? 0);
            $habilitado = (int) ($input['habilitado'] ?? 0);
            if (!$id) {
                throw new Exception('Depósito inválido.');
            }

            $stmtFlags = $pdo->prepare("SELECT esprincipal FROM depositos WHERE id = ?");
            $stmtFlags->execute([$id]);
            $flags = $stmtFlags->fetch(PDO::FETCH_ASSOC);
            if ($flags === false) {
                throw new Exception('Depósito no encontrado.');
            }

            if ($habilitado === 0) {
                $stmtStock = $pdo->prepare("SELECT COALESCE(SUM(cantidad), 0) FROM stock_por_deposito WHERE id_deposito = ?");
                $stmtStock->execute([$id]);
                if (round((float) $stmtStock->fetchColumn(), 2) != 0) {
                    throw new Exception('No se puede deshabilitar un depósito con stock. Transfiera el stock a otro depósito primero.');
                }
                if ((int) $flags['esprincipal'] === 1) {
                    throw new Exception('No se puede deshabilitar el depósito principal.');
                }
            }
            $pdo->prepare("UPDATE depositos SET habilitado = ? WHERE id = ?")->execute([$habilitado, $id]);
            echo json_encode(['success' => true]);
            break;

        // -------------------------------------------------------------
        // Depósito de trabajo (sesión de usuario)
        // -------------------------------------------------------------
        case 'mi_estado':
            if (!multidepositoHabilitado($pdo)) {
                echo json_encode(['success' => true, 'habilitado' => false]);
                break;
            }
            try {
                $idActivo = depositoDeTrabajo($pdo, $usuario);
                $nombreActivo = null;
                if ($idActivo) {
                    $stmt = $pdo->prepare("SELECT nombre FROM depositos WHERE id = ?");
                    $stmt->execute([$idActivo]);
                    $nombreActivo = $stmt->fetchColumn();
                }
                $permitidos = depositosPermitidosUsuario($pdo, $usuario);
                $stmtPerm = $pdo->prepare("SELECT puedecambiardeposito FROM permisosespeciales WHERE usuario = ? LIMIT 1");
                $stmtPerm->execute([$usuario]);
                $puedeCambiar = $esAdmin || (int) $stmtPerm->fetchColumn() === 1;

                echo json_encode([
                    'success' => true,
                    'habilitado' => true,
                    'requiere_seleccion' => false,
                    'id_deposito' => $idActivo,
                    'nombre_deposito' => $nombreActivo,
                    'logo' => logoDepositoUrl($pdo, $idActivo),
                    'puede_cambiar' => $puedeCambiar && count($permitidos) > 1,
                    'depositos_permitidos' => $permitidos,
                ]);
            } catch (DepositoNoSeleccionadoException $e) {
                echo json_encode([
                    'success' => true,
                    'habilitado' => true,
                    'requiere_seleccion' => true,
                    'id_deposito' => null,
                    'nombre_deposito' => null,
                    'depositos_permitidos' => $e->depositosPermitidos,
                ]);
            }
            break;

        case 'fijar_trabajo':
            $idDeposito = (int) ($input['id_deposito'] ?? 0);
            if (!$idDeposito) {
                throw new Exception('Debe indicar un depósito.');
            }
            fijarDepositoTrabajo($pdo, $usuario, $idDeposito, $esAdmin);
            echo json_encode(['success' => true]);
            break;

        // -------------------------------------------------------------
        // Asignación de depósitos por usuario (desde usuarios_admin.php)
        // -------------------------------------------------------------
        case 'get_asignacion_usuario':
            requireAdminDeposito($esAdmin);
            $usuarioObjetivo = trim($_GET['usuario'] ?? '');
            if ($usuarioObjetivo === '') {
                throw new Exception('Usuario inválido.');
            }
            $stmt = $pdo->prepare("SELECT id_deposito FROM usuarios_depositos WHERE usuario = ?");
            $stmt->execute([$usuarioObjetivo]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

            $stmtPerm = $pdo->prepare("SELECT puedecambiardeposito FROM permisosespeciales WHERE usuario = ? LIMIT 1");
            $stmtPerm->execute([$usuarioObjetivo]);
            $puedeCambiarRaw = $stmtPerm->fetchColumn();

            echo json_encode([
                'success' => true,
                'depositos_asignados' => $ids,
                'puede_cambiar' => $puedeCambiarRaw !== false ? (int) $puedeCambiarRaw === 1 : false,
            ]);
            break;

        case 'set_asignacion_usuario':
            requireAdminDeposito($esAdmin);
            $usuarioObjetivo = trim($input['usuario'] ?? '');
            if ($usuarioObjetivo === '') {
                throw new Exception('Usuario inválido.');
            }
            $idsDeposito = array_map('intval', $input['depositos'] ?? []);
            $puedeCambiar = (int) ($input['puede_cambiar'] ?? 0);

            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM usuarios_depositos WHERE usuario = ?")->execute([$usuarioObjetivo]);
            $ins = $pdo->prepare("INSERT INTO usuarios_depositos (usuario, id_deposito) VALUES (?, ?)");
            foreach (array_unique($idsDeposito) as $idDep) {
                if ($idDep > 0) {
                    $ins->execute([$usuarioObjetivo, $idDep]);
                }
            }

            $stmtExistePerm = $pdo->prepare("SELECT id FROM permisosespeciales WHERE usuario = ?");
            $stmtExistePerm->execute([$usuarioObjetivo]);
            if ($stmtExistePerm->fetchColumn()) {
                $pdo->prepare("UPDATE permisosespeciales SET puedecambiardeposito = ? WHERE usuario = ?")
                    ->execute([$puedeCambiar, $usuarioObjetivo]);
            } else {
                $pdo->prepare("INSERT INTO permisosespeciales
                        (usuario, precioscosto, unvendedor, modificarprecios, facturarpedidos, modificarfacturas, puedecambiardeposito)
                        VALUES (?, 0, 0, 0, 0, 0, ?)")
                    ->execute([$usuarioObjetivo, $puedeCambiar]);
            }
            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        // -------------------------------------------------------------
        // Transferencias entre depósitos.
        // Dos modos:
        // - Directa (requiere_confirmacion=false): igual que antes, origen -> destino
        //   al instante, queda COMPLETADA (con la línea ya marcada 100% recibida).
        // - Con confirmación (requiere_confirmacion=true, default): origen -> depósito
        //   de tránsito al instante (el origen ya se descuenta), queda PENDIENTE_TRANSITO.
        //   Recién avanza cuando alguien en el depósito de tránsito la acepta
        //   (action=aceptar_transito -> EN_TRANSITO) y después el destino confirma
        //   la recepción total o parcial (action=recibir -> PARCIAL o COMPLETADA).
        // -------------------------------------------------------------
        case 'transferir':
            $idOrigen = (int) ($input['id_deposito_origen'] ?? 0);
            $idDestino = (int) ($input['id_deposito_destino'] ?? 0);
            $items = $input['items'] ?? [];
            $observacion = trim($input['observacion'] ?? '');
            $idTransporte = !empty($input['id_transporte']) ? (int) $input['id_transporte'] : null;
            $requiereConfirmacion = !isset($input['requiere_confirmacion']) || !empty($input['requiere_confirmacion']);
            $idDepositoTransitoElegido = !empty($input['id_deposito_transito']) ? (int) $input['id_deposito_transito'] : null;

            if (!$idOrigen || !$idDestino) {
                throw new Exception('Debe indicar depósito de origen y destino.');
            }
            if ($idOrigen === $idDestino) {
                throw new Exception('El depósito de origen y destino no pueden ser el mismo.');
            }
            if (empty($items)) {
                throw new Exception('Debe indicar al menos un producto a transferir.');
            }

            validarDeposito($pdo, $idOrigen, 'la transferencia (origen)');
            validarDeposito($pdo, $idDestino, 'la transferencia (destino)');

            $stmtChkTransito = $pdo->prepare("SELECT COUNT(*) FROM depositos WHERE id IN (?, ?) AND estransito = 1");
            $stmtChkTransito->execute([$idOrigen, $idDestino]);
            if ($stmtChkTransito->fetchColumn() > 0) {
                throw new Exception('Un depósito de tránsito es de uso interno y no puede elegirse como origen ni destino.');
            }

            $pdo->beginTransaction();

            $idDepositoTransitoUsado = null;
            if ($requiereConfirmacion) {
                $idDepositoTransitoUsado = resolverDepositoTransito($pdo, $usuario, $idDepositoTransitoElegido);
            }
            $idDepositoSalida = $idOrigen;
            $idDepositoEntrada = $requiereConfirmacion ? $idDepositoTransitoUsado : $idDestino;
            $estadoInicial = $requiereConfirmacion ? 'PENDIENTE_TRANSITO' : 'COMPLETADA';

            $pdo->prepare("INSERT INTO transferencias_deposito (id_deposito_origen, id_deposito_destino, observacion, usuario, fecha, estado, id_transporte, id_deposito_transito)
                            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?)")
                ->execute([$idOrigen, $idDestino, $observacion, $usuario, $estadoInicial, $idTransporte, $idDepositoTransitoUsado]);
            $idTransf = (int) $pdo->lastInsertId();

            // Transferencia directa: la línea ya nace 100% recibida en destino
            // (mantiene el invariante cantidad_recibida = "lo que hay en destino").
            $insLinea = $pdo->prepare("INSERT INTO transferencias_deposito_lineas (id_transferencia, id_producto, codigo, cantidad, cantidad_recibida) VALUES (?, ?, ?, ?, ?)");
            $huboItems = false;
            foreach ($items as $it) {
                $idProd = (int) ($it['id_producto'] ?? 0);
                $codigo = (string) ($it['codigo'] ?? '');
                $cantidad = round((float) ($it['cantidad'] ?? 0), 2);
                if (!$idProd || $cantidad <= 0) {
                    continue;
                }
                $insLinea->execute([$idTransf, $idProd, $codigo, $cantidad, $requiereConfirmacion ? 0 : $cantidad]);
                transferirStock($pdo, $idProd, $codigo, $idDepositoSalida, $idDepositoEntrada, $cantidad, $idTransf, $usuario);
                $huboItems = true;
            }
            if (!$huboItems) {
                throw new Exception('Ningún ítem tenía una cantidad válida para transferir.');
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'id' => $idTransf, 'estado' => $estadoInicial]);
            break;

        // El depósito de tránsito confirma que recibió físicamente la mercadería
        // que le mandó el origen. No mueve stock (ya está ahí desde 'transferir'),
        // solo destraba el paso siguiente (que el destino pueda recibirla).
        case 'aceptar_transito':
            $id = (int) ($input['id'] ?? 0);
            if (!$id) {
                throw new Exception('Transferencia inválida.');
            }
            $stmt = $pdo->prepare("SELECT estado FROM transferencias_deposito WHERE id = ?");
            $stmt->execute([$id]);
            $estadoActual = $stmt->fetchColumn();
            if ($estadoActual === false) {
                throw new Exception('Transferencia no encontrada.');
            }
            if ($estadoActual !== 'PENDIENTE_TRANSITO') {
                throw new Exception('Esta transferencia no está pendiente de aceptación en tránsito.');
            }

            $pdo->prepare("UPDATE transferencias_deposito SET estado = 'EN_TRANSITO', fecha_aceptacion_transito = NOW(), usuario_aceptacion_transito = ? WHERE id = ?")
                ->execute([$usuario, $id]);
            echo json_encode(['success' => true]);
            break;

        // Confirma la recepción en destino de una transferencia ya aceptada en
        // tránsito. Por defecto recibe TODO lo pendiente de cada línea; si viene
        // "lineas" en el body, recibe solo esas cantidades (recepción parcial) y
        // lo que falte queda pendiente en el depósito de tránsito.
        case 'recibir':
            $id = (int) ($input['id'] ?? 0);
            [$transf, $lineas] = cargarTransferenciaConLineas($pdo, $id, ['EN_TRANSITO', 'PARCIAL']);

            $cantidadesPedidas = [];
            foreach (($input['lineas'] ?? []) as $l) {
                $idp = (int) ($l['id_producto'] ?? 0);
                if ($idp) {
                    $cantidadesPedidas[$idp] = round((float) ($l['cantidad'] ?? 0), 2);
                }
            }

            $pdo->beginTransaction();

            $huboMovimiento = false;
            $quedaPendiente = false;
            foreach ($lineas as $linea) {
                $pendiente = pendienteLinea($linea);
                if ($pendiente <= 0) {
                    continue;
                }

                $aRecibir = $pendiente;
                if (isset($cantidadesPedidas[(int) $linea['id_producto']])) {
                    $aRecibir = min($pendiente, max(0, $cantidadesPedidas[(int) $linea['id_producto']]));
                }
                if ($aRecibir <= 0) {
                    $quedaPendiente = true;
                    continue;
                }

                transferirStock($pdo, (int) $linea['id_producto'], $linea['codigo'], (int) $transf['id_deposito_transito'], (int) $transf['id_deposito_destino'], $aRecibir, $id, $usuario);
                $pdo->prepare("UPDATE transferencias_deposito_lineas SET cantidad_recibida = cantidad_recibida + ? WHERE id = ?")
                    ->execute([$aRecibir, $linea['id']]);
                $huboMovimiento = true;
                if (round($pendiente - $aRecibir, 2) > 0) {
                    $quedaPendiente = true;
                }
            }
            if (!$huboMovimiento) {
                throw new Exception('No hay cantidad pendiente para recibir.');
            }

            $nuevoEstado = $quedaPendiente ? 'PARCIAL' : 'COMPLETADA';
            $pdo->prepare("UPDATE transferencias_deposito SET estado = ?, fecha_recepcion = NOW(), usuario_recepcion = ? WHERE id = ?")
                ->execute([$nuevoEstado, $usuario, $id]);

            $pdo->commit();
            echo json_encode(['success' => true, 'estado' => $nuevoEstado]);
            break;

        // -------------------------------------------------------------
        // Resolución administrativa del remanente parado en tránsito (aplica a
        // TODO lo pendiente de la transferencia, no lo que ya se recibió/devolvió/
        // ajustó antes). Disponible mientras haya algo pendiente en tránsito,
        // sea que el destino todavía no recibió nada (EN_TRANSITO) o ya recibió
        // una parte (PARCIAL).
        // -------------------------------------------------------------
        case 'admin_devolver_transito':
        case 'admin_ajustar_transito':
        case 'admin_enviar_destino':
            requireAdminDeposito($esAdmin);
            $id = (int) ($input['id'] ?? 0);
            $motivo = trim($input['motivo'] ?? '');
            [$transf, $lineas] = cargarTransferenciaConLineas($pdo, $id, ['EN_TRANSITO', 'PARCIAL']);

            $pdo->beginTransaction();

            $huboMovimiento = false;
            foreach ($lineas as $linea) {
                $pendiente = pendienteLinea($linea);
                if ($pendiente <= 0) {
                    continue;
                }
                $huboMovimiento = true;

                if ($action === 'admin_ajustar_transito') {
                    moverStock($pdo, (int) $linea['id_producto'], $linea['codigo'], (int) $transf['id_deposito_transito'], $pendiente, 'SALIDA', 'AJUSTE_TRANSITO', 'AJUSTE_TRANSITO', 0, $id, $usuario, $id);
                    $pdo->prepare("UPDATE transferencias_deposito_lineas SET cantidad_ajustada = cantidad_ajustada + ? WHERE id = ?")->execute([$pendiente, $linea['id']]);
                } elseif ($action === 'admin_devolver_transito') {
                    transferirStock($pdo, (int) $linea['id_producto'], $linea['codigo'], (int) $transf['id_deposito_transito'], (int) $transf['id_deposito_origen'], $pendiente, $id, $usuario);
                    $pdo->prepare("UPDATE transferencias_deposito_lineas SET cantidad_devuelta = cantidad_devuelta + ? WHERE id = ?")->execute([$pendiente, $linea['id']]);
                } else { // admin_enviar_destino
                    transferirStock($pdo, (int) $linea['id_producto'], $linea['codigo'], (int) $transf['id_deposito_transito'], (int) $transf['id_deposito_destino'], $pendiente, $id, $usuario);
                    $pdo->prepare("UPDATE transferencias_deposito_lineas SET cantidad_recibida = cantidad_recibida + ? WHERE id = ?")->execute([$pendiente, $linea['id']]);
                }
            }
            if (!$huboMovimiento) {
                throw new Exception('No hay cantidad pendiente en tránsito para resolver.');
            }

            $resolucion = ['admin_ajustar_transito' => 'AJUSTADA', 'admin_devolver_transito' => 'DEVUELTA', 'admin_enviar_destino' => 'ENVIADA_DESTINO'][$action];
            if ($action === 'admin_enviar_destino') {
                $pdo->prepare("UPDATE transferencias_deposito
                                SET estado = 'COMPLETADA', resoluciontransito = ?, usuarioresoluciontransito = ?, fecharesoluciontransito = NOW(), motivoresoluciontransito = ?,
                                    fecha_recepcion = NOW(), usuario_recepcion = ?
                                WHERE id = ?")
                    ->execute([$resolucion, $usuario, $motivo, $usuario, $id]);
            } else {
                $pdo->prepare("UPDATE transferencias_deposito
                                SET estado = 'COMPLETADA', resoluciontransito = ?, usuarioresoluciontransito = ?, fecharesoluciontransito = NOW(), motivoresoluciontransito = ?
                                WHERE id = ?")
                    ->execute([$resolucion, $usuario, $motivo, $id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        case 'list_transferencias':
            // Admite un estado ("EN_TRANSITO") o varios separados por coma
            // ("EN_TRANSITO,PARCIAL") para pantallas que agrupan más de un estado.
            $estadosValidos = ['PENDIENTE_TRANSITO', 'EN_TRANSITO', 'PARCIAL', 'COMPLETADA', 'ANULADA'];
            $estadosPedidos = array_values(array_intersect(array_map('trim', explode(',', (string) ($_GET['estado'] ?? ''))), $estadosValidos));
            $whereEstado = '';
            $paramsEstado = [];
            if ($estadosPedidos) {
                $placeholders = implode(',', array_fill(0, count($estadosPedidos), '?'));
                $whereEstado = "WHERE t.estado IN ($placeholders)";
                $paramsEstado = $estadosPedidos;
            }
            $stmt = $pdo->prepare("SELECT t.*, do.nombre AS deposito_origen, dd.nombre AS deposito_destino, dt.nombre AS deposito_transito, tr.razon AS transporte,
                                    (SELECT COUNT(*) FROM transferencias_deposito_lineas l WHERE l.id_transferencia = t.id) AS cant_items
                                  FROM transferencias_deposito t
                                  LEFT JOIN depositos do ON do.id = t.id_deposito_origen
                                  LEFT JOIN depositos dd ON dd.id = t.id_deposito_destino
                                  LEFT JOIN depositos dt ON dt.id = t.id_deposito_transito
                                  LEFT JOIN transportes tr ON tr.id = t.id_transporte
                                  $whereEstado
                                  ORDER BY t.fecha DESC, t.id DESC LIMIT 200");
            $stmt->execute($paramsEstado);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_transferencia':
            $id = (int) ($_GET['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM transferencias_deposito_lineas WHERE id_transferencia = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // Remito interno imprimible (sin precios) para acompañar el traslado
        // físico entre depósitos. No es un comprobante fiscal: es documentación
        // de logística interna, indica transporte y depósito de tránsito en uso.
        case 'imprimir_remito':
            $id = (int) ($_GET['id'] ?? 0);
            if (!$id) {
                throw new Exception('Transferencia inválida.');
            }

            $stmt = $pdo->prepare("SELECT t.*, do.nombre AS deposito_origen, dd.nombre AS deposito_destino, dt.nombre AS deposito_transito, tr.razon AS transporte, tr.domicilio AS transporte_domicilio, tr.telefono AS transporte_telefono
                                    FROM transferencias_deposito t
                                    LEFT JOIN depositos do ON do.id = t.id_deposito_origen
                                    LEFT JOIN depositos dd ON dd.id = t.id_deposito_destino
                                    LEFT JOIN depositos dt ON dt.id = t.id_deposito_transito
                                    LEFT JOIN transportes tr ON tr.id = t.id_transporte
                                    WHERE t.id = ?");
            $stmt->execute([$id]);
            $transf = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$transf) {
                throw new Exception('Transferencia no encontrada.');
            }

            $stmtLineas = $pdo->prepare("SELECT l.*, p.descripcion
                                          FROM transferencias_deposito_lineas l
                                          LEFT JOIN alb_productos p ON p.id = l.id_producto
                                          WHERE l.id_transferencia = ?
                                          ORDER BY l.id ASC");
            $stmtLineas->execute([$id]);
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

            $emp = $pdo->query("SELECT razon, domicilio, localidad, provincia, cuit, telefono FROM datosempresa LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if (!$emp) {
                $emp = ['razon' => '', 'domicilio' => '', 'localidad' => '', 'provincia' => '', 'cuit' => '', 'telefono' => ''];
            }

            $numeroCompleto = 'TD-' . str_pad((string) $id, 8, '0', STR_PAD_LEFT);
            $BADGES_ESTADO = [
                'PENDIENTE_TRANSITO' => 'PENDIENTE DE ACEPTAR EN TRÁNSITO',
                'EN_TRANSITO' => 'EN TRÁNSITO',
                'PARCIAL' => 'RECIBIDA PARCIALMENTE',
                'COMPLETADA' => 'COMPLETADA',
                'ANULADA' => 'ANULADA',
            ];
            $estadoTexto = $BADGES_ESTADO[$transf['estado']] ?? $transf['estado'];

            ob_start();
            ?>
            <!DOCTYPE html>
            <html lang="es">
            <head>
                <meta charset="UTF-8">
                <title>Remito de Traslado <?= htmlspecialchars($numeroCompleto) ?></title>
                <style>
                    @page { size: A4; margin: 0; }
                    body { font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; background: #fff; color: #000; width: 210mm; }
                    .remito-page { width: 100%; box-sizing: border-box; padding: 25px; }
                    .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #000; padding-bottom: 10px; margin-bottom: 15px; }
                    .header h1 { margin: 0; font-size: 24px; text-transform: uppercase; letter-spacing: 1px; }
                    .header .empresa { font-size: 13px; line-height: 1.4; }
                    .header .numero { text-align: right; }
                    .header .numero .big { font-size: 20px; font-weight: 900; }
                    .aviso { background: #f2f2f2; border: 1px solid #999; padding: 6px 10px; font-size: 11px; text-align: center; margin-bottom: 15px; }
                    .datos-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 20px; margin-bottom: 15px; }
                    .datos-box { border: 1px solid #000; padding: 8px 10px; }
                    .datos-box .label { font-size: 10px; font-weight: bold; color: #444; text-transform: uppercase; }
                    .datos-box .value { font-size: 15px; font-weight: bold; text-transform: uppercase; }
                    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                    th, td { border: 1px solid #000; padding: 6px 8px; font-size: 13px; }
                    th { background: #eee; text-transform: uppercase; text-align: left; }
                    td.cant { text-align: center; font-weight: bold; width: 100px; }
                    .firmas { display: flex; justify-content: space-between; margin-top: 60px; }
                    .firma-box { width: 30%; text-align: center; font-size: 11px; }
                    .firma-linea { border-top: 1px solid #000; margin-bottom: 4px; padding-top: 4px; }
                    .footer { margin-top: 20px; font-size: 10px; text-align: center; color: #555; }
                    @media print { .no-print { display: none; } }
                </style>
            </head>
            <body onload="setTimeout(function(){ window.print(); }, 400);">
                <div class="no-print" style="position: fixed; top: 0; left: 0; width: 100%; background: #eee; padding: 12px; text-align: center; border-bottom: 1px solid #ccc; z-index: 9999;">
                    <button onclick="window.print()" style="font-size: 16px; padding: 8px 20px; cursor: pointer; background: #000; color: #fff; border: none; border-radius: 5px;">IMPRIMIR (CTRL+P)</button>
                    <button onclick="window.close()" style="font-size: 16px; padding: 8px 20px; cursor: pointer; background: #ccc; border: none; border-radius: 5px; margin-left: 10px;">CERRAR</button>
                </div>
                <div class="no-print" style="height: 60px;"></div>

                <div class="remito-page">
                    <div class="header">
                        <div class="empresa">
                            <strong style="font-size:16px;"><?= htmlspecialchars($emp['razon']) ?></strong><br>
                            <?= htmlspecialchars($emp['domicilio']) ?><?= $emp['localidad'] ? ' - ' . htmlspecialchars($emp['localidad']) : '' ?><?= $emp['provincia'] ? ' (' . htmlspecialchars($emp['provincia']) . ')' : '' ?><br>
                            <?php if ($emp['cuit']): ?>CUIT: <?= htmlspecialchars($emp['cuit']) ?><br><?php endif; ?>
                        </div>
                        <div class="numero">
                            <h1>Remito de Traslado</h1>
                            <div class="big">N° <?= htmlspecialchars($numeroCompleto) ?></div>
                            <div>Fecha: <?= date('d/m/Y H:i', strtotime($transf['fecha'])) ?></div>
                        </div>
                    </div>

                    <div class="aviso">
                        DOCUMENTO DE LOGÍSTICA INTERNA — NO VÁLIDO COMO FACTURA NI COMPROBANTE FISCAL — SIN VALOR COMERCIAL
                    </div>

                    <div class="datos-grid">
                        <div class="datos-box">
                            <div class="label">Depósito Origen</div>
                            <div class="value"><?= htmlspecialchars($transf['deposito_origen'] ?? '-') ?></div>
                        </div>
                        <div class="datos-box">
                            <div class="label">Depósito Destino</div>
                            <div class="value"><?= htmlspecialchars($transf['deposito_destino'] ?? '-') ?></div>
                        </div>
                        <?php if ($transf['deposito_transito']): ?>
                        <div class="datos-box">
                            <div class="label">Depósito de Tránsito en uso</div>
                            <div class="value"><?= htmlspecialchars($transf['deposito_transito']) ?></div>
                        </div>
                        <?php endif; ?>
                        <div class="datos-box">
                            <div class="label">Transporte</div>
                            <div class="value"><?= htmlspecialchars($transf['transporte'] ?? 'SIN ESPECIFICAR') ?></div>
                            <?php if ($transf['transporte_domicilio'] || $transf['transporte_telefono']): ?>
                                <div style="font-size:11px; font-weight:normal; text-transform:none;">
                                    <?= htmlspecialchars($transf['transporte_domicilio'] ?? '') ?>
                                    <?= $transf['transporte_telefono'] ? ' - Tel: ' . htmlspecialchars($transf['transporte_telefono']) : '' ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="datos-box">
                            <div class="label">Estado</div>
                            <div class="value"><?= htmlspecialchars($estadoTexto) ?></div>
                        </div>
                        <div class="datos-box">
                            <div class="label">Usuario que generó el envío</div>
                            <div class="value"><?= htmlspecialchars($transf['usuario']) ?></div>
                        </div>
                    </div>

                    <?php if ($transf['observacion']): ?>
                        <div class="datos-box" style="margin-bottom:15px;">
                            <div class="label">Observación</div>
                            <div class="value" style="text-transform:none; font-size:13px;"><?= htmlspecialchars($transf['observacion']) ?></div>
                        </div>
                    <?php endif; ?>

                    <table>
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="cant">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lineas as $l): ?>
                                <tr>
                                    <td><?= htmlspecialchars($l['codigo']) ?></td>
                                    <td><?= htmlspecialchars($l['descripcion'] ?? '') ?></td>
                                    <td class="cant"><?= number_format((float) $l['cantidad'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="firmas">
                        <div class="firma-box"><div class="firma-linea">DESPACHA (ORIGEN)</div></div>
                        <div class="firma-box"><div class="firma-linea">TRANSPORTE</div></div>
                        <div class="firma-box"><div class="firma-linea">RECIBE (DESTINO)</div></div>
                    </div>

                    <div class="footer">Impreso el <?= date('d/m/Y H:i') ?> — Transferencia interna N° <?= $id ?></div>
                </div>
            </body>
            </html>
            <?php
            $htmlContent = ob_get_clean();
            echo json_encode(['success' => true, 'html_content' => $htmlContent]);
            break;

        // Anula una transferencia que todavía no tuvo ninguna recepción en destino:
        // - PENDIENTE_TRANSITO o EN_TRANSITO -> revierte depósito de tránsito -> origen.
        // - COMPLETADA (directa, o ya recibida) -> revierte SOLO lo que efectivamente
        //   está en destino (cantidad_recibida) hacia el origen.
        // Una transferencia PARCIAL no se anula: el remanente en tránsito se resuelve
        // con las acciones administrativas (ajustar / devolver / enviar a destino).
        case 'anular':
            $id = (int) ($input['id'] ?? 0);
            $motivo = trim($input['motivo'] ?? '');
            if (!$id) {
                throw new Exception('Transferencia inválida.');
            }

            $stmt = $pdo->prepare("SELECT * FROM transferencias_deposito WHERE id = ?");
            $stmt->execute([$id]);
            $transf = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$transf) {
                throw new Exception('Transferencia no encontrada.');
            }
            if ($transf['estado'] === 'ANULADA') {
                throw new Exception('Esta transferencia ya fue anulada.');
            }
            if ($transf['estado'] === 'PARCIAL') {
                throw new Exception('Esta transferencia ya tiene una recepción parcial. Use las acciones de administrador para resolver el remanente en tránsito.');
            }

            $pendienteEnTransito = in_array($transf['estado'], ['PENDIENTE_TRANSITO', 'EN_TRANSITO'], true);
            if ($pendienteEnTransito && !$transf['id_deposito_transito']) {
                throw new Exception('Esta transferencia no tiene registrado un depósito de tránsito de origen.');
            }

            $pdo->beginTransaction();

            $stmtLineas = $pdo->prepare("SELECT * FROM transferencias_deposito_lineas WHERE id_transferencia = ?");
            $stmtLineas->execute([$id]);
            $lineas = $stmtLineas->fetchAll(PDO::FETCH_ASSOC);

            $huboMovimiento = false;
            foreach ($lineas as $linea) {
                if ($pendienteEnTransito) {
                    $cantidad = pendienteLinea($linea);
                    $idOrigenReversa = (int) $transf['id_deposito_transito'];
                } else {
                    $cantidad = (float) $linea['cantidad_recibida'];
                    $idOrigenReversa = (int) $transf['id_deposito_destino'];
                }
                if ($cantidad <= 0) {
                    continue;
                }
                transferirStock($pdo, (int) $linea['id_producto'], $linea['codigo'], $idOrigenReversa, (int) $transf['id_deposito_origen'], $cantidad, $id, $usuario);
                $huboMovimiento = true;
            }
            if (!$huboMovimiento) {
                throw new Exception('Esta transferencia no tiene stock para revertir (ya fue resuelto por completo en tránsito).');
            }

            $pdo->prepare("UPDATE transferencias_deposito SET estado = 'ANULADA', usuarioanulacion = ?, fechaanulacion = NOW(), motivoanulacion = ? WHERE id = ?")
                ->execute([$usuario, $motivo, $id]);

            $pdo->commit();
            echo json_encode(['success' => true]);
            break;

        default:
            throw new Exception('Acción no definida.');
    }
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
