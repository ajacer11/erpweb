<?php
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--coegotest') exit('Sólo CLI con --coegotest.');
chdir(dirname(__DIR__));
$pdo = new PDO('mysql:host=localhost;dbname=coegotest;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$_SESSION=['usuario_id'=>1,'usuario_nombre'=>'TEST','es_admin'=>1];
function renderPrueba($archivo, $params) {
    global $pdo;
    $_GET=$params;
    $source=file_get_contents($archivo);
    $source=str_replace(["session_start();", "require_once 'db.php';"],'',$source);
    // Evitar redeclaración de función de escape al renderizar ambos tamaños.
    if ($archivo==='imprimir_movimiento_caja.php' && function_exists('hCaja')) $source=preg_replace('/function hCaja\(\$v\) \{.*?\}/','',$source);
    ob_start(); eval('?>'.$source); return ob_get_clean();
}
function checkPrint($ok,$label) { if(!$ok)throw new Exception($label); echo "OK: $label\n"; }
$pdo->beginTransaction();
try {
    $id=(int)$pdo->query('SELECT id FROM pedidos_proveedores_cab ORDER BY id DESC LIMIT 1')->fetchColumn();
    if (!$id) throw new Exception('Se requiere una OC de testing.');
    $orders=['grabado'=>'d.id ASC','alfabetico'=>'d.descripcion ASC, d.id ASC','cantidad'=>'d.cantidad DESC, d.id ASC','precio'=>'d.costo_unitario DESC, d.id ASC','codigo'=>'d.codigo_producto ASC, d.id ASC','proveedor'=>'p.codigoproveedor ASC, d.id ASC'];
    foreach($orders as $key=>$sort) {
        $html=renderPrueba('imprimir_oc.php',['id'=>$id,'precios'=>'1','orden'=>$key]);
        $st=$pdo->prepare("SELECT d.codigo_producto FROM pedidos_proveedores_det d LEFT JOIN alb_productos p ON p.codigo=d.codigo_producto WHERE d.id_pedido=? ORDER BY $sort");$st->execute([$id]);$codes=$st->fetchAll(PDO::FETCH_COLUMN);
        preg_match_all('/<tr>\s*<td>(.*?)<\/td>/s',$html,$matches);
        checkPrint(array_map('html_entity_decode',$matches[1])===$codes,"OC orden $key");
        checkPrint(str_contains($html,'TOTAL UNIDADES'),'OC muestra unidades');
    }
    $html=renderPrueba('imprimir_oc.php',['id'=>$id,'precios'=>'0']);
    checkPrint(str_contains($html,'Total unidades:') && !str_contains($html,'PRECIO UNIT.'),'OC sin precios conserva total unidades');
    $asiento=(int)$pdo->query('SELECT COALESCE(MAX(numerodeasiento),0)+1 FROM cccuentastesoreria')->fetchColumn();
    $pdo->prepare("INSERT INTO cccuentastesoreria (idcuenta,fecha,debe,haber,numerodeasiento,descripcion,usuario,tabla) VALUES (101,CURDATE(),0,25,?,'TEST RETIRO','TEST','retiro_central'),(112,CURDATE(),25,0,?,'TEST RETIRO','TEST','retiro_central')")->execute([$asiento,$asiento]);
    foreach(['A4','80'] as $formato) {
        $pdo->prepare('UPDATE configuracion SET formatoimpresion=? ORDER BY id DESC LIMIT 1')->execute([$formato]);
        $html=renderPrueba('imprimir_movimiento_caja.php',['id'=>$asiento]);
        checkPrint(str_contains($html,$formato==='80'?'size: 80mm auto':'size: A4'),"Caja formato $formato");
        checkPrint(str_contains($html,'Salida / Origen') && str_contains($html,'Entrada / Destino') && str_contains($html,'25,00'),"Caja origen, destino e importe $formato");
    }
} finally { $pdo->rollBack(); }
echo "ROLLBACK: movimientos y configuración de prueba.\n";
