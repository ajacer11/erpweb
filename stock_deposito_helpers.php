<?php
/**
 * stock_deposito_helpers.php - LÓGICA CENTRAL DE STOCK POR DEPÓSITO (MULTIDEPÓSITO)
 *
 * Se incluye desde cada punto de escritura de stock (facturación, ingreso,
 * ajustes, producción, transferencias). Si el tenant no tiene multidepósito
 * activado (configuracion.multidepositohabilitado = 0), moverStock() solo
 * actualiza alb_productos.Stock — comportamiento idéntico al de antes de esta
 * feature, sin tocar ninguna tabla nueva.
 */

class DepositoNoSeleccionadoException extends Exception
{
    /** @var array<int, array{id:int, nombre:string}> */
    public array $depositosPermitidos;

    public function __construct(array $depositosPermitidos)
    {
        parent::__construct('Debe seleccionar un depósito de trabajo antes de continuar.');
        $this->depositosPermitidos = $depositosPermitidos;
    }
}

function multidepositoHabilitado(PDO $pdo): bool
{
    static $cache = null;
    if ($cache === null) {
        $cache = (bool) $pdo->query('SELECT multidepositohabilitado FROM configuracion LIMIT 1')->fetchColumn();
    }
    return $cache;
}

/**
 * URL del logo del depósito (servido por api_depositos.php?action=logo), o null si no tiene
 * y el caller cae al de configuración. Lleva el hash del logo para que el navegador lo cachee
 * y lo vuelva a pedir solo si cambia.
 */
function logoDepositoUrl(PDO $pdo, ?int $idDeposito): ?string
{
    if (!$idDeposito) {
        return null;
    }
    $stmt = $pdo->prepare('SELECT MD5(logo) FROM depositos WHERE id = ? AND logo IS NOT NULL AND OCTET_LENGTH(logo) > 0');
    $stmt->execute([$idDeposito]);
    $hash = $stmt->fetchColumn();
    return $hash ? 'api_depositos.php?action=logo&id=' . $idDeposito . '&v=' . $hash : null;
}

/**
 * Resuelve los datos comerciales usados al facturar. En multidepósito cada
 * campo vacío cae individualmente a Datos de la Empresa; la identidad fiscal
 * (razón, CUIT, IVA, IIBB e inicio de actividad) siempre pertenece al tenant.
 */
function datosEmisorFacturacion(PDO $pdo, string $usuario, ?int $idDeposito = null): array
{
    $empresa = $pdo->query('SELECT * FROM datosempresa ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC) ?: [];
    $resultado = $empresa;
    $resultado['pdv'] = (int) ($empresa['puntodeventa'] ?? 0);
    $resultado['id_deposito'] = null;
    $resultado['deposito_nombre'] = '';

    if (multidepositoHabilitado($pdo)) {
        $idDeposito = $idDeposito ?: depositoDeTrabajo($pdo, $usuario);
        $stmt = $pdo->prepare('SELECT * FROM depositos WHERE id = ? AND habilitado = 1 AND estransito = 0');
        $stmt->execute([$idDeposito]);
        $deposito = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$deposito) throw new Exception('El depósito de trabajo no está disponible.');

        $mapa = [
            'domicilio' => 'domicilio', 'localidad' => 'localidad', 'provincia' => 'provincia',
            'telefono' => 'telefono', 'email' => 'email', 'cp' => 'codigopostal'
        ];
        foreach ($mapa as $destino => $origen) {
            if (trim((string) ($deposito[$origen] ?? '')) !== '') $resultado[$destino] = $deposito[$origen];
        }
        $resultado['pdv'] = (int) ($deposito['puntodeventa'] ?? 0) > 0
            ? (int) $deposito['puntodeventa'] : (int) ($empresa['puntodeventa'] ?? 0);
        $resultado['puntodeventa'] = $resultado['pdv'];
        $resultado['id_deposito'] = (int) $idDeposito;
        $resultado['deposito_nombre'] = (string) $deposito['nombre'];
    }

    $requeridos = [
        'razon' => 'Razón social', 'domicilio' => 'Domicilio', 'localidad' => 'Localidad',
        'provincia' => 'Provincia', 'cuit' => 'CUIT', 'condicioniva' => 'Condición frente al IVA',
        'numiibb' => 'Ingresos Brutos', 'fechainicioact' => 'Fecha de inicio de actividades'
    ];
    $faltantes = [];
    foreach ($requeridos as $campo => $etiqueta) {
        if (trim((string) ($resultado[$campo] ?? '')) === '') $faltantes[] = $etiqueta;
    }
    $fechaInicio = (string) ($resultado['fechainicioact'] ?? '');
    $fechaObj = DateTime::createFromFormat('!Y-m-d', $fechaInicio);
    $fechaValida = $fechaObj instanceof DateTime
        && $fechaObj->format('Y-m-d') === $fechaInicio
        && $fechaObj <= new DateTime('today');
    if ($fechaInicio !== '' && !$fechaValida) {
        $faltantes = array_values(array_filter($faltantes, fn($v) => $v !== 'Fecha de inicio de actividades'));
        $faltantes[] = 'Fecha de inicio de actividades válida';
    }
    if ((int) $resultado['pdv'] < 1) $faltantes[] = 'Punto de venta';
    $resultado['faltantes'] = $faltantes;
    $resultado['completo'] = count($faltantes) === 0;
    return $resultado;
}

function depositoPorDefecto(PDO $pdo): ?int
{
    $id = (int) $pdo->query('SELECT deposito_default_id FROM configuracion LIMIT 1')->fetchColumn();
    return $id > 0 ? $id : null;
}

/**
 * Depósitos que un usuario puede usar. Sin filas en usuarios_depositos para ese
 * usuario significa "sin restricción": puede usar cualquier depósito habilitado.
 * @return array<int, array{id:int, nombre:string}>
 */
function depositosPermitidosUsuario(PDO $pdo, string $usuario): array
{
    // "estransito = 0" excluye siempre el depósito interno "En Tránsito": nunca es
    // un depósito de trabajo válido, lo maneja únicamente el circuito de transferencias.
    $stmt = $pdo->prepare('SELECT d.id, d.nombre FROM usuarios_depositos ud
                            INNER JOIN depositos d ON d.id = ud.id_deposito
                            WHERE ud.usuario = ? AND d.habilitado = 1 AND d.estransito = 0
                            ORDER BY d.nombre ASC');
    $stmt->execute([$usuario]);
    $permitidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($permitidos) {
        return $permitidos;
    }
    return $pdo->query('SELECT id, nombre FROM depositos WHERE habilitado = 1 AND estransito = 0 ORDER BY nombre ASC')->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Resuelve el "depósito de trabajo" del usuario, sin preguntarle nada si no hace
 * falta: si ya tiene uno fijado y sigue siendo válido lo devuelve; si nunca eligió
 * y solo tiene un depósito permitido, lo fija automáticamente (caso de mostrador
 * con un solo depósito real); si tiene más de uno y ninguno fijado, lanza
 * DepositoNoSeleccionadoException para que el caller le pida elegir una vez.
 */
function depositoDeTrabajo(PDO $pdo, string $usuario): ?int
{
    if (!multidepositoHabilitado($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT iddepositoactivo FROM alb_usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $activoRaw = $stmt->fetchColumn();
    $activo = ($activoRaw !== false && $activoRaw !== null) ? (int) $activoRaw : null;

    $permitidos = depositosPermitidosUsuario($pdo, $usuario);
    $idsPermitidos = array_column($permitidos, 'id');

    if ($activo && in_array($activo, $idsPermitidos, true)) {
        $stmtChk = $pdo->prepare('SELECT habilitado FROM depositos WHERE id = ?');
        $stmtChk->execute([$activo]);
        if ((int) $stmtChk->fetchColumn() === 1) {
            return $activo;
        }
    }

    if (count($idsPermitidos) === 1) {
        $unico = $idsPermitidos[0];
        $pdo->prepare('UPDATE alb_usuarios SET iddepositoactivo = ? WHERE usuario = ?')->execute([$unico, $usuario]);
        return $unico;
    }

    if (count($idsPermitidos) === 0) {
        throw new Exception('No hay ningún depósito habilitado en el sistema. Configure al menos uno en Depósitos.');
    }

    throw new DepositoNoSeleccionadoException($permitidos);
}

/**
 * Depósitos "de tránsito" habilitados, gestionados por el admin desde Depósitos >
 * Administrar (checkbox "Es depósito de tránsito"). Nunca se ofrecen como depósito
 * de trabajo/ajuste — ver depositosPermitidosUsuario() y list_habilitados/
 * list_permitidos en api_depositos.php, que los excluyen.
 * @return array<int, array{id:int, nombre:string}>
 */
function depositosTransitoDisponibles(PDO $pdo): array
{
    return $pdo->query("SELECT id, nombre FROM depositos WHERE estransito = 1 AND habilitado = 1 ORDER BY nombre ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Resuelve QUÉ depósito de tránsito usar para una transferencia nueva:
 * - Si el caller ya eligió uno explícito, lo valida y lo devuelve.
 * - Si no, y hay depósitos de tránsito ya creados: si hay exactamente uno, lo usa;
 *   si hay más de uno, exige que se elija (no adivina para no mezclar tránsitos).
 * - Si no hay ninguno creado todavía, crea uno genérico "En Tránsito" (zero-config
 *   para el caso simple de un solo circuito de tránsito).
 */
function resolverDepositoTransito(PDO $pdo, string $usuario, ?int $idElegido): int
{
    if ($idElegido) {
        $stmt = $pdo->prepare("SELECT estransito FROM depositos WHERE id = ? AND habilitado = 1");
        $stmt->execute([$idElegido]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new Exception('El depósito de tránsito seleccionado no es válido.');
        }
        return $idElegido;
    }

    $disponibles = depositosTransitoDisponibles($pdo);
    if (count($disponibles) === 1) {
        return (int) $disponibles[0]['id'];
    }
    if (count($disponibles) > 1) {
        throw new Exception('Hay más de un depósito de tránsito configurado; debe elegir cuál usar para este envío.');
    }

    $pdo->prepare("INSERT INTO depositos (nombre, descripcion, esprincipal, habilitado, estransito, usuariocreacion)
                    VALUES ('En Tránsito', 'Depósito de tránsito genérico, creado automáticamente', 0, 1, 1, ?)")
        ->execute([$usuario]);
    return (int) $pdo->lastInsertId();
}

function validarDeposito(PDO $pdo, int $idDeposito, string $contexto): void
{
    $stmt = $pdo->prepare('SELECT habilitado FROM depositos WHERE id = ?');
    $stmt->execute([$idDeposito]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        throw new Exception("El depósito seleccionado para $contexto no existe.");
    }
    if ((int) $row['habilitado'] !== 1) {
        throw new Exception("El depósito seleccionado para $contexto está deshabilitado.");
    }
}

function permiteNegativoEnDeposito(PDO $pdo, int $idDeposito): bool
{
    if ((int) $pdo->query('SELECT stocknegativo FROM configuracion LIMIT 1')->fetchColumn() === 1) {
        return true;
    }
    $stmt = $pdo->prepare('SELECT permitenegativo FROM depositos WHERE id = ?');
    $stmt->execute([$idDeposito]);
    return (int) $stmt->fetchColumn() === 1;
}

/**
 * Fija el depósito de trabajo de un usuario. La primera elección (todavía no
 * tenía ninguno) siempre se permite; cambiar uno ya fijado requiere
 * permisosespeciales.puedecambiardeposito = 1, salvo que sea administrador
 * (el admin ya se salta el resto de las restricciones de depósitos, ej. ABM).
 */
function fijarDepositoTrabajo(PDO $pdo, string $usuario, int $idDeposito, bool $esAdmin = false): void
{
    validarDeposito($pdo, $idDeposito, 'fijar el depósito de trabajo');

    $idsPermitidos = array_column(depositosPermitidosUsuario($pdo, $usuario), 'id');
    if (!in_array($idDeposito, $idsPermitidos, true)) {
        throw new Exception('No tiene permiso para operar sobre este depósito.');
    }

    $stmt = $pdo->prepare('SELECT iddepositoactivo FROM alb_usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $activoRaw = $stmt->fetchColumn();
    $yaTeniaUno = $activoRaw !== false && $activoRaw !== null && (int) $activoRaw > 0;

    if ($yaTeniaUno && !$esAdmin) {
        $stmtPerm = $pdo->prepare('SELECT puedecambiardeposito FROM permisosespeciales WHERE usuario = ? LIMIT 1');
        $stmtPerm->execute([$usuario]);
        if ((int) $stmtPerm->fetchColumn() !== 1) {
            throw new Exception('No tiene permiso para cambiar el depósito de trabajo. Pídaselo a un administrador.');
        }
    }

    $pdo->prepare('UPDATE alb_usuarios SET iddepositoactivo = ? WHERE usuario = ?')->execute([$idDeposito, $usuario]);
}

/**
 * Función central de escritura de stock. Debe llamarse dentro de una
 * transacción ya abierta por el caller. $cantidad siempre en valor absoluto;
 * $tipoSigno define el efecto ('ENTRADA' suma, 'SALIDA' resta).
 *
 * NO reemplaza los inserts existentes a movimientosdestock/ingresosdestock/
 * controlstock/lineajustes — esos siguen intactos en cada api_*.php. Esta
 * función agrega el registro paralelo con depósito (movimientos_stock_deposito)
 * y mantiene stock_por_deposito + el total cacheado alb_productos.Stock.
 */
function moverStock(
    PDO $pdo,
    int $idProducto,
    string $codigo,
    ?int $idDeposito,
    float $cantidad,
    string $tipoSigno,
    string $tipoMov,
    string $origenMov,
    int $tipoComp,
    int $numRemito,
    string $usuario,
    ?int $idMovimientoLegacy = null
): void {
    $cantidad = round($cantidad, 2);
    if ($cantidad <= 0) {
        return;
    }
    if (!in_array($tipoSigno, ['ENTRADA', 'SALIDA'], true)) {
        throw new Exception("Tipo de movimiento de stock inválido: $tipoSigno.");
    }

    $delta = $tipoSigno === 'ENTRADA' ? $cantidad : -$cantidad;

    // 1. Total cacheado: siempre se actualiza, esté o no habilitado el multidepósito.
    $pdo->prepare('UPDATE alb_productos SET Stock = Stock + ? WHERE id = ?')
        ->execute([$delta, $idProducto]);

    if (!multidepositoHabilitado($pdo)) {
        return; // tenant sin la feature: comportamiento 100% legacy
    }

    if (!$idDeposito) {
        $idDeposito = depositoPorDefecto($pdo);
    }
    if (!$idDeposito) {
        throw new Exception("Multidepósito está activo pero no hay depósito para registrar el movimiento de $codigo.");
    }
    validarDeposito($pdo, $idDeposito, $origenMov);

    // 2. Fuente de verdad operativa (upsert atómico, seguro bajo concurrencia).
    $pdo->prepare('INSERT INTO stock_por_deposito (id_producto, id_deposito, cantidad)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE cantidad = cantidad + VALUES(cantidad)')
        ->execute([$idProducto, $idDeposito, $delta]);

    if ($tipoSigno === 'SALIDA') {
        $stmt = $pdo->prepare('SELECT cantidad FROM stock_por_deposito WHERE id_producto = ? AND id_deposito = ?');
        $stmt->execute([$idProducto, $idDeposito]);
        $cantidadFinal = (float) $stmt->fetchColumn();
        if ($cantidadFinal < 0 && !permiteNegativoEnDeposito($pdo, $idDeposito)) {
            throw new Exception("Stock insuficiente en el depósito seleccionado para $codigo.");
        }
    }

    // 3. Ledger auditable.
    $pdo->prepare('INSERT INTO movimientos_stock_deposito
            (id_producto, codigo, id_deposito, entradas, salidas, tipomov, origenmov, tipocomp, numremito, id_movimiento_legacy, fecha, usuario)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)')
        ->execute([
            $idProducto, $codigo, $idDeposito,
            $tipoSigno === 'ENTRADA' ? $cantidad : 0,
            $tipoSigno === 'SALIDA' ? $cantidad : 0,
            $tipoMov, $origenMov, $tipoComp, $numRemito, $idMovimientoLegacy, $usuario,
        ]);
}

/**
 * Variante para ajustes que fijan un stock ABSOLUTO (excel / grupo): calcula el
 * delta contra el stock anterior y llama a moverStock(). No hace nada si no hubo
 * diferencia.
 */
function ajustarStockADestino(
    PDO $pdo,
    int $idProducto,
    string $codigo,
    ?int $idDeposito,
    float $stockAntes,
    float $stockDestino,
    string $tipoMov,
    string $origenMov,
    int $tipoComp,
    int $numRemito,
    string $usuario,
    ?int $idMovimientoLegacy = null
): void {
    $diff = round($stockDestino - $stockAntes, 2);
    if ($diff == 0) {
        return;
    }
    if ($diff > 0) {
        moverStock($pdo, $idProducto, $codigo, $idDeposito, $diff, 'ENTRADA', $tipoMov, $origenMov, $tipoComp, $numRemito, $usuario, $idMovimientoLegacy);
    } else {
        moverStock($pdo, $idProducto, $codigo, $idDeposito, abs($diff), 'SALIDA', $tipoMov, $origenMov, $tipoComp, $numRemito, $usuario, $idMovimientoLegacy);
    }
}

/**
 * Cantidad de una línea de transferencia que todavía no se aplicó a ningún
 * lado (ni destino, ni devuelta a origen, ni ajustada de baja) — lo que sigue
 * físicamente "parado" en el depósito de tránsito de esa transferencia.
 */
function pendienteLinea(array $linea): float
{
    return round(
        (float) $linea['cantidad']
        - (float) $linea['cantidad_recibida']
        - (float) $linea['cantidad_devuelta']
        - (float) $linea['cantidad_ajustada'],
        2
    );
}

/** Transferencia atómica entre 2 depósitos: salida en origen + entrada en destino. */
function transferirStock(
    PDO $pdo,
    int $idProducto,
    string $codigo,
    int $idDepositoOrigen,
    int $idDepositoDestino,
    float $cantidad,
    int $idTransferencia,
    string $usuario
): void {
    if ($idDepositoOrigen === $idDepositoDestino) {
        throw new Exception('El depósito de origen y destino no pueden ser el mismo.');
    }
    moverStock($pdo, $idProducto, $codigo, $idDepositoOrigen, $cantidad, 'SALIDA', 'TRANSFERENCIA', 'TRANSFERENCIA', 0, $idTransferencia, $usuario);
    moverStock($pdo, $idProducto, $codigo, $idDepositoDestino, $cantidad, 'ENTRADA', 'TRANSFERENCIA', 'TRANSFERENCIA', 0, $idTransferencia, $usuario);
}
