<?php
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--coegotest') exit('Sólo CLI con --coegotest.');
chdir(dirname(__DIR__));
$pdo = new PDO('mysql:host=localhost;dbname=coegotest;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function paginaDiario($pagina,$tam) {
    global $pdo;
    $inputPrueba = ['vista'=>'DETALLADA','ejercicio'=>3,'desde'=>'2026-01-01','hasta'=>'2026-12-31','fiscal'=>'TODOS','manual'=>'TODOS','pagina'=>$pagina,'tamanioPagina'=>$tam];
    $source = file_get_contents('api_reporte_contable_diario.php');
    $source = str_replace("require_once 'check_api_access.php';",'',$source);
    $source = str_replace('$input = json_decode(file_get_contents(\'php://input\'), true);', '$input = $inputPrueba;', $source);
    ob_start(); eval('?>'.$source); return json_decode(ob_get_clean(),true,512,JSON_THROW_ON_ERROR);
}
function checkD($ok,$label){if(!$ok)throw new Exception($label);echo "OK: $label\n";}
$a=paginaDiario(1,50);$b=paginaDiario(2,50);$c=paginaDiario(3,100);
checkD($a['success'] && $a['totalRegistros']>100 && $a['totalPaginas']>2,'API informa total real y páginas');
checkD(count($a['data'])===50 && count($b['data'])===50 && count($c['data'])===100,'API respeta tamaño y página');
checkD(count(array_intersect(array_column($a['data'],'id'),array_column($b['data'],'id')))===0,'Páginas sin filas repetidas');
checkD($b['paginaActual']===2 && $c['paginaActual']===3,'API responde página solicitada');
$d=paginaDiario(999999,50);
checkD($d['paginaActual']===$d['totalPaginas'] && count($d['data'])>0,'Página fuera de rango se ajusta a la última');
