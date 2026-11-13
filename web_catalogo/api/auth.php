<?php
/**
 * auth.php - Ingreso de clientes, activación (invitación), recuperación de contraseña y
 * solicitud de registro ("Quiero ser cliente").
 *
 * Protección contra fuerza bruta: 5 intentos fallidos bloquean el acceso 15 minutos
 * (web_usuarios.bloqueado_hasta) y cada IP tiene un límite de intentos por hora
 * (log_accesos de la maestra).
 */
declare(strict_types=1);

const MAX_INTENTOS = 5;
const MINUTOS_BLOQUEO = 15;
const MAX_INTENTOS_IP_HORA = 30;

function registrarIntentoIp(string $evento, string $estado, string $usuario = ''): void
{
    global $pdo_master, $SITIO;
    try {
        $pdo_master->prepare("INSERT INTO log_accesos (cuit, usuario, evento, estado, ip) VALUES (?, ?, ?, ?, ?)")
            ->execute([(string) $SITIO['cuit'], mb_substr('web:' . $usuario, 0, 50), $evento, $estado, ipCliente()]);
    } catch (Throwable $e) {
        error_log('web_catalogo log_accesos: ' . $e->getMessage());
    }
}

function ipBloqueada(): bool
{
    global $pdo_master;
    try {
        $st = $pdo_master->prepare("SELECT COUNT(*) FROM log_accesos WHERE ip = ? AND evento LIKE 'WEB_%' AND estado = 'FALLIDO' AND fecha > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $st->execute([ipCliente()]);
        return (int) $st->fetchColumn() >= MAX_INTENTOS_IP_HORA;
    } catch (Throwable $e) {
        return false;
    }
}

function iniciarSesionCliente(array $u): void
{
    global $pdo;
    session_regenerate_id(true);
    $sid = $_SESSION['sitio_id'];
    $_SESSION = [
        'sitio_id' => $sid,
        'web_usuario_id' => (int) $u['id'],
        'usuario' => $u['nombre_usuario'],
        'revalidado' => 0, // fuerza a leer cliente/lista ya mismo
    ];
    $pdo->prepare("UPDATE web_usuarios SET ultimo_login = NOW(), intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")->execute([$u['id']]);
}

function validarClaveNueva(string $clave): void
{
    if (mb_strlen($clave) < 8) throw new ApiError('La contraseña tiene que tener al menos 8 caracteres.');
    if (mb_strlen($clave) > 128) throw new ApiError('La contraseña es demasiado larga.');
}

// =================================================================== Handlers

function hSesion(): array
{
    $c = clienteActual();
    return ['cliente' => $c ? ['razon' => $c['razon'], 'usuario' => $c['usuario'], 'lista' => $c['lista']] : null];
}

function hLogin(): array
{
    global $pdo;
    $in = cuerpoJson();
    $usuario = trim((string) ($in['usuario'] ?? ''));
    $clave = (string) ($in['clave'] ?? '');
    if ($usuario === '' || $clave === '') throw new ApiError('Completá usuario y contraseña.');
    if (ipBloqueada()) throw new ApiError('Demasiados intentos. Esperá un rato y volvé a probar.', 429);

    $st = $pdo->prepare("SELECT w.*, c.habilitado AS cliente_habilitado FROM web_usuarios w
                         LEFT JOIN alb_clientes c ON c.id = w.cliente_id
                         WHERE w.nombre_usuario = ? OR LOWER(w.email) = LOWER(?) ORDER BY w.nombre_usuario = ? DESC LIMIT 1");
    $st->execute([$usuario, $usuario, $usuario]);
    $u = $st->fetch();

    $error = 'Usuario o contraseña incorrectos.';
    if (!$u) {
        password_verify($clave, '$2y$10$Xzm1i4yWEuXhJsy67kl.CusfgkDaBP7fR3GaQS/h7xTfSztZ.Lvx6'); // tiempo parejo
        registrarIntentoIp('WEB_LOGIN', 'FALLIDO', $usuario);
        throw new ApiError($error, 401);
    }
    if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
        throw new ApiError('Por seguridad, el acceso quedó bloqueado unos minutos por intentos fallidos. Probá más tarde o pedí una nueva contraseña.', 429);
    }
    if (!password_verify($clave, (string) $u['password_hash'])) {
        $intentos = (int) $u['intentos_fallidos'] + 1;
        $pdo->prepare("UPDATE web_usuarios SET intentos_fallidos = ?, bloqueado_hasta = IF(? >= ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL) WHERE id = ?")
            ->execute([$intentos >= MAX_INTENTOS ? 0 : $intentos, $intentos, MAX_INTENTOS, MINUTOS_BLOQUEO, $u['id']]);
        registrarIntentoIp('WEB_LOGIN', 'FALLIDO', $usuario);
        throw new ApiError($error, 401);
    }
    if (!(int) $u['activo'] || !(int) $u['cliente_habilitado']) {
        throw new ApiError('Tu acceso está desactivado. Comunicate con nosotros.', 403);
    }
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare("UPDATE web_usuarios SET password_hash = ? WHERE id = ?")->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
    }
    iniciarSesionCliente($u);
    registrarIntentoIp('WEB_LOGIN', 'EXITOSO', $usuario);
    return hSesion();
}

function hLogout(): array
{
    $sid = $_SESSION['sitio_id'] ?? null;
    $_SESSION = ['sitio_id' => $sid];
    session_regenerate_id(true);
    return ['ok' => true];
}

/** Busca el acceso de un token vigente. */
function accesoPorToken(string $token): ?array
{
    global $pdo;
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $st = $pdo->prepare("SELECT w.*, c.razon FROM web_usuarios w LEFT JOIN alb_clientes c ON c.id = w.cliente_id
                         WHERE w.token_hash = ? AND w.token_vence > NOW() AND w.activo = 1 LIMIT 1");
    $st->execute([hash('sha256', $token)]);
    return $st->fetch() ?: null;
}

function hValidarToken(): array
{
    $u = accesoPorToken((string) ($_GET['token'] ?? ''));
    if (!$u) throw new ApiError('El link venció o ya fue usado. Pedí uno nuevo.', 400, 'token_invalido');
    return ['razon' => $u['razon'], 'usuario' => $u['nombre_usuario'], 'tipo' => $u['token_tipo']];
}

/** Crear contraseña (invitación) o restablecerla (recuperación). Deja al cliente logueado. */
function hActivar(): array
{
    global $pdo;
    $in = cuerpoJson();
    $u = accesoPorToken((string) ($in['token'] ?? ''));
    if (!$u) throw new ApiError('El link venció o ya fue usado. Pedí uno nuevo.', 400, 'token_invalido');
    $clave = (string) ($in['clave'] ?? '');
    validarClaveNueva($clave);
    $pdo->prepare("UPDATE web_usuarios SET password_hash = ?, token_hash = NULL, token_tipo = NULL, token_vence = NULL,
                   intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id = ?")
        ->execute([password_hash($clave, PASSWORD_DEFAULT), $u['id']]);
    iniciarSesionCliente($u);
    return hSesion();
}

/** Manda un link para restablecer la contraseña. Siempre responde igual (no revela si el mail existe). */
function hRecuperar(): array
{
    global $pdo, $CFG;
    $dato = trim((string) (cuerpoJson()['usuario'] ?? ''));
    if ($dato === '') throw new ApiError('Ingresá tu usuario o mail.');
    if (ipBloqueada()) throw new ApiError('Demasiados intentos. Esperá un rato y volvé a probar.', 429);
    registrarIntentoIp('WEB_RECUPERAR', 'FALLIDO', $dato); // cuenta para el límite por IP

    $st = $pdo->prepare("SELECT w.id, w.email, w.nombre_usuario, c.razon FROM web_usuarios w LEFT JOIN alb_clientes c ON c.id = w.cliente_id
                         WHERE (w.nombre_usuario = ? OR LOWER(w.email) = LOWER(?)) AND w.activo = 1 LIMIT 1");
    $st->execute([$dato, $dato]);
    $u = $st->fetch();
    if ($u && filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
        $token = webGenerarToken($pdo, (int) $u['id'], 'recuperacion', 2);
        $link = urlBaseSitio() . '/cuenta/activar?token=' . $token;
        $empresa = webNombreEmpresa($pdo, $CFG);
        $html = '<p>Hola <b>' . htmlspecialchars((string) $u['razon']) . '</b>,</p>'
            . '<p>Pediste cambiar tu contraseña del área de clientes. Tu usuario es <b>' . htmlspecialchars($u['nombre_usuario']) . '</b>.</p>'
            . webMailBoton($link, 'Elegir una contraseña nueva', $CFG['color_primario'])
            . '<p style="color:#64748b;font-size:13px">El link vence en 2 horas. Si no lo pediste, ignorá este mail: tu contraseña sigue siendo la misma.</p>';
        webEnviarMail($pdo, $u['email'], "Cambiar tu contraseña - $empresa", $html, $CFG);
    }
    return ['ok' => true, 'mensaje' => 'Si los datos corresponden a un acceso, te mandamos un mail con un link para elegir una contraseña nueva.'];
}

function hCambiarClave(): array
{
    global $pdo;
    $c = requiereCliente();
    $in = cuerpoJson();
    $st = $pdo->prepare("SELECT password_hash FROM web_usuarios WHERE id = ?");
    $st->execute([$c['web_usuario_id']]);
    if (!password_verify((string) ($in['actual'] ?? ''), (string) $st->fetchColumn())) {
        throw new ApiError('La contraseña actual no es correcta.');
    }
    $nueva = (string) ($in['nueva'] ?? '');
    validarClaveNueva($nueva);
    $pdo->prepare("UPDATE web_usuarios SET password_hash = ? WHERE id = ?")->execute([password_hash($nueva, PASSWORD_DEFAULT), $c['web_usuario_id']]);
    return ['ok' => true];
}

function hRegistro(): array
{
    global $pdo, $CFG;
    if ($CFG['registro_habilitado'] !== '1') throw new ApiError('El registro no está habilitado.', 403);
    $in = cuerpoJson();
    if (!empty($in['web'])) return ['ok' => true]; // honeypot: los bots completan el campo oculto
    $razon = trim((string) ($in['razon'] ?? ''));
    $email = trim(mb_strtolower((string) ($in['email'] ?? '')));
    if (mb_strlen($razon) < 2) throw new ApiError('Ingresá el nombre o la razón social.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiError('Ingresá un mail válido.');
    if (ipBloqueada()) throw new ApiError('Demasiados intentos. Esperá un rato y volvé a probar.', 429);

    $st = $pdo->prepare("SELECT COUNT(*) FROM web_solicitudes_registro WHERE ip = ? AND fecha > DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $st->execute([ipCliente()]);
    if ((int) $st->fetchColumn() >= 5) throw new ApiError('Ya recibimos tus solicitudes. Te vamos a contactar.', 429);

    $corto = fn($k, $n) => mb_substr(trim((string) ($in[$k] ?? '')), 0, $n);
    $pdo->prepare("INSERT INTO web_solicitudes_registro (razon, cuit, contacto, email, telefono, localidad, mensaje, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([mb_substr($razon, 0, 150), $corto('cuit', 20), $corto('contacto', 100), $email, $corto('telefono', 50), $corto('localidad', 100), $corto('mensaje', 1000), ipCliente()]);

    // Aviso a la empresa (a los mismos destinatarios de los pedidos, o al mail de contacto).
    $destinos = $CFG['pedido_mail_aviso'] ?: $CFG['contacto_email'];
    if ($destinos) {
        $filas = '';
        foreach (['Empresa / Nombre' => $razon, 'CUIT' => $in['cuit'] ?? '', 'Contacto' => $in['contacto'] ?? '', 'Mail' => $email,
                     'Teléfono' => $in['telefono'] ?? '', 'Localidad' => $in['localidad'] ?? '', 'Mensaje' => $in['mensaje'] ?? ''] as $k => $v) {
            if (trim((string) $v) !== '') $filas .= '<tr><td style="padding:4px 12px 4px 0;color:#64748b">' . $k . '</td><td><b>' . nl2br(htmlspecialchars((string) $v)) . '</b></td></tr>';
        }
        webEnviarMail($pdo, $destinos, 'Nueva solicitud para ser cliente: ' . $razon,
            "<p>Llegó una solicitud desde la web:</p><table>$filas</table><p>Aprobala o rechazala en el sistema: <b>Administrador &gt; Web de Clientes &gt; Accesos de Clientes</b>.</p>", $CFG);
    }
    return ['ok' => true];
}
