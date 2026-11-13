<?php
/**
 * bootstrap.php - Arranque de la API de la web de clientes (catálogo + área de clientes).
 *
 * Una sola instalación sirve a todas las empresas: la empresa se resuelve por el dominio
 * con el que entró el visitante (tabla web_sitios de la base maestra). Si el dominio no
 * está registrado, o la empresa no tiene contratado el addon 'web_catalogo', responde 404.
 *
 * Sesión propia ('WEBCLI'), separada de la del ERP, ligada al sitio: un login en una web
 * no sirve en otra.
 */
declare(strict_types=1);

const ERP_DIR = __DIR__ . '/../..';
date_default_timezone_set('America/Argentina/Buenos_Aires');
ini_set('display_errors', '0');

// ---------------------------------------------------------------- Respuestas
final class ApiError extends Exception
{
    public function __construct(string $mensaje, public int $status = 400, public ?string $codigo = null)
    {
        parent::__construct($mensaje);
    }
}

function responder(mixed $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cuerpoJson(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true);
    return is_array($in) ? $in : [];
}

function ipCliente(): string
{
    return (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
}

// ---------------------------------------------------------------- Sitio / empresa
$host = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? ''));
$host = explode(':', explode(',', $host)[0])[0];
$host = preg_replace('/^www\./', '', trim($host));

// Sesión ANTES de db_master.php (que si no hay sesión arranca la del ERP).
$https = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_name('WEBCLI');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once ERP_DIR . '/db_master.php';   // $pdo_master, getTenantDatabaseConfig(), parseConnectionString()
require_once ERP_DIR . '/web_helpers.php';
require_once ERP_DIR . '/precios_listas.php';

$st = $pdo_master->prepare("SELECT id, dominio, cuit, db_id, tipo FROM web_sitios WHERE dominio = ? AND habilitado = 1 LIMIT 1");
$st->execute([$host]);
$SITIO = $st->fetch(PDO::FETCH_ASSOC);
if (!$SITIO || !webAddonHabilitado($pdo_master, (string) $SITIO['cuit'])) {
    responder(['error' => 'Sitio no encontrado'], 404);
}
$SITIO['db_id'] = (int) $SITIO['db_id'];

// Sesión de otro sitio (mismo navegador, otro dominio no comparte cookie, pero por las dudas).
if (($_SESSION['sitio_id'] ?? null) !== (int) $SITIO['id']) {
    $_SESSION = ['sitio_id' => (int) $SITIO['id']];
}

try {
    $dbCfg = getTenantDatabaseConfig($pdo_master, (string) $SITIO['cuit'], $SITIO['db_id'] ?: null);
    if (!$dbCfg) throw new Exception('Config de empresa no encontrada');
    $c = parseConnectionString($dbCfg['cadenaconec']);
    $pdo = new PDO("mysql:host={$c['host']};port={$c['port']};dbname={$c['db']};charset=utf8mb4", $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("SET time_zone = '-03:00'");
} catch (Throwable $e) {
    error_log('web_catalogo: conexión a la empresa ' . $SITIO['cuit'] . ': ' . $e->getMessage());
    responder(['error' => 'El sitio no está disponible en este momento. Probá de nuevo en unos minutos.'], 503);
}

$CFG = webConfigLeer($pdo);

// ---------------------------------------------------------------- Sesión del cliente
/** Cliente logueado o null. Revalida contra la base (acceso activo) cada 5 minutos. */
function clienteActual(): ?array
{
    global $pdo;
    if (empty($_SESSION['web_usuario_id'])) return null;
    if (($_SESSION['revalidado'] ?? 0) < time() - 300) {
        $st = $pdo->prepare("SELECT w.activo, c.id, c.razon, c.ListaDePRecios, c.habilitado FROM web_usuarios w
                             JOIN alb_clientes c ON c.id = w.cliente_id WHERE w.id = ?");
        $st->execute([$_SESSION['web_usuario_id']]);
        $r = $st->fetch();
        if (!$r || !(int) $r['activo'] || !(int) $r['habilitado']) {
            $sid = $_SESSION['sitio_id'];
            $_SESSION = ['sitio_id' => $sid];
            return null;
        }
        $_SESSION['cliente_id'] = (int) $r['id'];
        $_SESSION['razon'] = $r['razon'];
        $_SESSION['lista'] = listaValida($r['ListaDePRecios']);
        $_SESSION['revalidado'] = time();
    }
    return [
        'web_usuario_id' => (int) $_SESSION['web_usuario_id'],
        'cliente_id' => (int) $_SESSION['cliente_id'],
        'razon' => (string) $_SESSION['razon'],
        'lista' => (int) $_SESSION['lista'],
        'usuario' => (string) ($_SESSION['usuario'] ?? ''),
    ];
}

function requiereCliente(): array
{
    $c = clienteActual();
    if (!$c) throw new ApiError('Tu sesión expiró. Ingresá de nuevo.', 401, 'sin_sesion');
    return $c;
}

function listaValida($l): int
{
    $l = (int) $l;
    return $l >= 1 && $l <= 8 ? $l : 1;
}

/** Lista de precios a usar en esta petición, o null si no se muestran precios. */
function listaParaPrecios(): ?int
{
    global $CFG;
    $c = clienteActual();
    if ($c) return $c['lista'];
    return $CFG['precios_publicos'] === '1' ? listaValida($CFG['lista_publica']) : null;
}

function catalogoVisible(): bool
{
    global $CFG;
    return $CFG['catalogo_publico'] === '1' || clienteActual() !== null;
}

function urlBaseSitio(): string
{
    global $SITIO;
    return webUrlBase($SITIO['dominio']) ?? '';
}
