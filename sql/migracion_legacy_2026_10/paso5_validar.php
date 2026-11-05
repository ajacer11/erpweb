<?php
/**
 * Validación funcional de una base migrada. Uso: php paso5_validar.php wst_db_xxx src_xxx
 * Corre las APIs reales del sistema (con un stub de sesión apuntando a la base) y controles
 * de integridad contable. No modifica datos.
 */
require __DIR__ . '/lib.php';
[$_, $DB, $SRC] = $argv + [null, null, null];
$pdo = conectar($DB);
$fallas = 0;
$check = function (bool $ok, string $msg) use (&$fallas) { if (!$ok) $fallas++; logm(($ok ? 'OK   ' : 'FALLA') . " $msg"); };

// 1. Contraseñas: cada usuario entra con su contraseña legacy (sin mostrarla)
$tabUsr = $SRC === 'src_nicolas' ? 'usuarios' : 'alb_usuarios';
$legacy = $pdo->query("SELECT usuario, password FROM $SRC.$tabUsr")->fetchAll(PDO::FETCH_KEY_PAIR);
$okPw = 0; $malPw = [];
foreach ($pdo->query("SELECT usuario, passweb, habilitado FROM alb_usuarios")->fetchAll() as $u) {
    $pw = $legacy[$u['usuario']] ?? null;
    if ($u['usuario'] === 'angel') continue; // usuario de soporte: actualizaciones.sql del backoffice le fija la contraseña maestra
    if ($pw !== null && $pw !== '' && password_verify($pw, (string) $u['passweb'])) $okPw++; else $malPw[] = $u['usuario'];
}
$check(!$malPw, "contraseñas: $okPw usuarios entran con su contraseña de siempre" . ($malPw ? '; sin acceso: ' . implode(', ', $malPw) : ''));

// 2. Configuración contable
$hoy = date('Y-m-d');
$ej = $pdo->query("SELECT COUNT(*) FROM ejercicios WHERE abierto = 1 AND apertura <= '$hoy' AND cierre >= '$hoy'")->fetchColumn();
$check($ej > 0, "ejercicio contable abierto para hoy");
$cp = $pdo->query("SELECT * FROM cuentaspredeterminadas WHERE id = 1")->fetch();
$malas = [];
foreach ($cp as $k => $v) if ($k !== 'id' && $k !== 'chequesrechazados' && $k !== 'comisionestarjetas' && !(int) $pdo->query("SELECT COUNT(*) FROM plandecuentas WHERE imputable = 1 AND numcuenta = " . $pdo->quote((string) $v))->fetchColumn()) $malas[] = "$k=$v";
$check(!$malas, 'cuentas predeterminadas imputables' . ($malas ? ': ' . implode(', ', $malas) : ''));
$mv = $pdo->query("SELECT GROUP_CONCAT(medio) FROM mediosdepago WHERE habilitado = 1 AND visibleenventas = 1")->fetchColumn();
$check((bool) $mv, "medios visibles en ventas: $mv");
$sinCta = $pdo->query("SELECT GROUP_CONCAT(medio) FROM mediosdepago m WHERE habilitado = 1 AND NOT EXISTS (SELECT 1 FROM plandecuentas p WHERE p.numcuenta = m.cuentacontable AND p.imputable = 1)")->fetchColumn();
$check(!$sinCta, 'medios habilitados con cuenta contable' . ($sinCta ? " (sin cuenta: $sinCta)" : ''));
$cajaMed = $pdo->query("SELECT COUNT(*) FROM cajas c WHERE NOT EXISTS (SELECT 1 FROM mediosdepago m WHERE m.tipodecuenta = 'Caja de Usuarios' AND m.habilitado = 1 AND (m.puestoasociado = c.numcaja OR m.puestoasociado = c.id))")->fetchColumn();
$check($cajaMed == 0, 'cada puesto de caja tiene su medio de efectivo');
foreach (['Cartera de Cheques', 'Cheques Emitidos', 'Caja Central'] as $tipo) {
    $n = $pdo->query("SELECT COUNT(*) FROM mediosdepago WHERE habilitado = 1 AND tipodecuenta = " . $pdo->quote($tipo))->fetchColumn();
    $check($n == 1, "un único medio habilitado de tipo '$tipo' ($n)");
}
$de = $pdo->query("SELECT razon, cuit, condicioniva, provincia, puntodeventa FROM datosempresa WHERE id = 1")->fetch();
$check((bool) $de, 'datos de la empresa: ' . ($de ? implode(' / ', $de) : 'faltan'));
$cfg = $pdo->query("SELECT multidepositohabilitado, deposito_default_id, LENGTH(logo) logo FROM configuracion ORDER BY id DESC LIMIT 1")->fetch();
logm("     configuración: multidepósito={$cfg['multidepositohabilitado']} depósito={$cfg['deposito_default_id']} logo=" . ($cfg['logo'] ? round($cfg['logo'] / 1024) . ' KB' : 'no'));

// 3. APIs reales del sistema contra esta base
$stubDir = 'C:/mig_legacy/stub';
@mkdir($stubDir, 0775, true);
file_put_contents("$stubDir/check_api_access.php", '<?php $_SESSION = $_SESSION ?? []; $_SESSION += ["usuario_nombre"=>"angel","es_admin"=>1,"usuario_id"=>1,"cliente_cuit"=>getenv("T_CUIT"),"tenant_cuit"=>getenv("T_CUIT"),"pdv"=>1,"modo"=>1,"condicion_iva_empresa"=>""];
date_default_timezone_set("America/Argentina/Buenos_Aires");
$pdo = new PDO("mysql:host=127.0.0.1;dbname=" . getenv("T_DB") . ";charset=utf8mb4","root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);');
$api = function (string $archivo, string $action, ?array $post = null, array $get = []) use ($stubDir, $DB, $de): array {
    $code = file_get_contents("C:/xampp/htdocs/sistema/$archivo");
    $code = str_replace(["file_get_contents('php://input')", '__DIR__'], ["getenv('T_INPUT_JSON')", "'C:/xampp/htdocs/sistema'"], $code);
    file_put_contents("$stubDir/_$archivo", $code);
    $runner = "$stubDir/_run.php";
    file_put_contents($runner, '<?php set_include_path("' . $stubDir . '" . PATH_SEPARATOR . "C:/xampp/htdocs/sistema"); chdir("C:/xampp/htdocs/sistema");
        $_GET = json_decode(getenv("T_GET"), true); $_SERVER["REQUEST_METHOD"] = getenv("T_METHOD"); require "' . "$stubDir/_$archivo" . '";');
    $env = 'set "T_DB=' . $DB . '" && set "T_CUIT=' . ($de['cuit'] ?? '') . '" && set "T_METHOD=' . ($post === null ? 'GET' : 'POST') . '" && set "T_GET=' . str_replace('"', '\"', json_encode(['action' => $action] + $get)) . '"';
    putenv('T_INPUT_JSON=' . json_encode($post ?? []));
    putenv("T_DB=$DB"); putenv('T_CUIT=' . ($de['cuit'] ?? '')); putenv('T_METHOD=' . ($post === null ? 'GET' : 'POST')); putenv('T_GET=' . json_encode(['action' => $action] + $get));
    $out = (string) shell_exec('php ' . escapeshellarg($runner) . ' 2>&1');
    $j = json_decode(substr($out, (int) strpos($out, '{')), true);
    return is_array($j) ? $j : ['success' => false, 'error' => mb_substr(trim($out), 0, 300)];
};
$r = $api('api_facturar_gets.php', 'get_maestros');
$check(!empty($r['success']), 'facturar: get_maestros ' . (!empty($r['success']) ? count($r['medios_pago'] ?? []) . ' medios / ' . count($r['vendedores'] ?? []) . ' vendedores' : ($r['error'] ?? '')));
$hasta = date('Y-m-d'); $desde = date('Y-m-01', strtotime('-1 month'));
$r = $api('api_comprobantes_emitidos.php', 'list', ['desde' => $desde, 'hasta' => $hasta, 'tipo_fecha' => 'fecha']);
$check(!empty($r['success']), 'comprobantes emitidos: ' . (!empty($r['success']) ? count($r['data'] ?? []) . " comprobantes desde $desde" : ($r['error'] ?? '')));
$r = $api('api_libro_iva.php', 'resumen', ['desde' => date('Y-m-01', strtotime('first day of last month')), 'hasta' => date('Y-m-t', strtotime('last day of last month'))]);
$check(!empty($r['success']), 'libro IVA mes anterior: ' . (!empty($r['success']) ? 'débito $' . number_format($r['data']['debito']['total'], 2, ',', '.') : ($r['error'] ?? '')));
$r = $api('api_tarjetas.php', 'tarjetas_list');
$check(!empty($r['success']), 'tarjetas y cupones: ' . (!empty($r['success']) ? 'ok' : ($r['error'] ?? '')));
if ((int) $cfg['multidepositohabilitado'] === 1) {
    $dep = $pdo->query("SELECT GROUP_CONCAT(CONCAT(nombre,' PV ',puntodeventa) SEPARATOR ', ') FROM depositos WHERE estransito = 0")->fetchColumn();
    $stk = $pdo->query("SELECT COUNT(*), ROUND(SUM(cantidad)) FROM stock_por_deposito")->fetch(PDO::FETCH_NUM);
    $check((bool) $dep, "depósitos: $dep; stock_por_deposito {$stk[0]} filas, {$stk[1]} unidades");
}
logm($fallas ? "== $DB: $fallas control(es) con falla" : "== $DB: todos los controles OK");
