<?php
// Ejecutar: php tests/cambios_20260916.php --coegotest
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--coegotest') exit('Sólo CLI con --coegotest.');
chdir(dirname(__DIR__));
require 'anulacion_pagos_helpers.php';
require 'productos_filtros.php';
require 'imputaciones_gestion_helpers.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$_SESSION = [];
$pdo = new PDO('mysql:host=localhost;dbname=coegotest;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
function verificar($condicion, $mensaje) { if (!$condicion) throw new Exception($mensaje); echo "OK: $mensaje\n"; }
function filaPrueba($tabla, $datos) {
    global $pdo;
    $valores = [];
    foreach ($pdo->query("SHOW COLUMNS FROM `$tabla`")->fetchAll() as $col) {
        $nombre = $col['Field'];
        if (str_contains($col['Extra'], 'auto_increment')) continue;
        if (array_key_exists($nombre, $datos)) $valores[$nombre] = $datos[$nombre];
        elseif ($col['Default'] === null && $col['Null'] === 'NO') {
            $valores[$nombre] = preg_match('/int|decimal|double|float/', $col['Type']) ? 0 : (preg_match('/date|time/', $col['Type']) ? date('Y-m-d') : 'TEST');
        }
    }
    $cols = implode('`,`', array_keys($valores));
    $pdo->prepare("INSERT INTO `$tabla` (`$cols`) VALUES (".implode(',', array_fill(0,count($valores),'?')).")")->execute(array_values($valores));
    return (int)$pdo->lastInsertId();
}
function valor($sql, $params = []) { global $pdo; $st = $pdo->prepare($sql); $st->execute($params); return $st->fetchColumn(); }
$pdo->beginTransaction();
try {
    $fecha = date('Y-m-d');
    $cuenta = valor('SELECT numcuenta FROM plandecuentas LIMIT 1');
    $medio = filaPrueba('mediosdepago', ['medio'=>'TEST anulación','tipodecuenta'=>'Movimiento','cuentacontable'=>$cuenta]);
    foreach (['cliente', 'proveedor'] as $tipo) {
        $prov = $tipo === 'proveedor';
        $tabla = $prov ? 'pagoaproveedores' : 'pagos';
        $rel = $prov ? 'pagosfacturasproveedores' : 'cobrosfacturasclientes';
        $factTabla = $prov ? 'facturasdecompra' : 'tickets';
        $id = filaPrueba($tabla, $prov ? ['proveedor'=>999999,'total'=>60,'mp1'=>$medio,'mp1imp'=>60,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1,'saldoacuenta'=>0] : ['cliente'=>999999,'mp1'=>$medio,'mp2'=>0,'imp1'=>60,'imp2'=>0,'fecha'=>$fecha,'estado'=>1]);
        $factura = filaPrueba($factTabla, ['total'=>100,'estado'=>2,'pagada'=>1,'saldoaimputar'=>0]);
        filaPrueba($rel, ['idpago'=>$id,'idfactura'=>$factura,'importepagado'=>60,'tipo'=>'P']);
        // La NC comparte el ID del pago: no debe eliminarse ni ignorarse.
        filaPrueba($rel, ['idpago'=>$prov ? $id : 999999,'idfactura'=>$factura,'importepagado'=>40,'tipo'=>'N']);
        if ($prov) filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>60,'idpago'=>$id,'tabla'=>$tabla]);
        else filaPrueba('ccclientes',['cliente'=>999999,'haber'=>60,'saldo'=>-60,'compasoc'=>$id,'tabla'=>$tabla]);
        filaPrueba('cccuentastesoreria',['idcuenta'=>$medio,'debe'=>$prov?0:60,'haber'=>$prov?60:0,'tabla'=>$tabla,'compasoc'=>$id]);
        filaPrueba('librodiario',['cuenta'=>$cuenta,'debe'=>$prov?0:60,'haber'=>$prov?60:0,'tabla'=>$tabla,'compasoc'=>$id]);
        filaPrueba('ccbancos',['cuenta'=>999999,'debe'=>$prov?0:60,'haber'=>$prov?60:0,'saldo'=>$prov?-60:60,'tabla'=>$prov?'pagosprov':'pagos','compasoc'=>$id]);
        anularPago($pdo,$tipo,$id,$fecha,'TEST');
        verificar((int)valor("SELECT estado FROM $tabla WHERE id=?",[$id])===1000,"$tipo soft delete");
        verificar((int)valor("SELECT COUNT(*) FROM $rel WHERE idfactura=?",[$factura])===1,"$tipo conserva otras imputaciones");
        verificar(abs((float)valor('SELECT SUM(debe-haber) FROM cccuentastesoreria WHERE tabla=? AND compasoc=?',[$tabla,$id]))<0.001,"$tipo tesorería neutralizada");
        verificar(abs((float)valor('SELECT SUM(debe-haber) FROM librodiario WHERE tabla=? AND compasoc=?',[$tabla,$id]))<0.001,"$tipo contabilidad neutralizada");
        if ($prov) verificar((float)valor('SELECT saldoaimputar FROM facturasdecompra WHERE id=?',[$factura])===60.0,'Saldo proveedor respeta NC con mismo ID');
        else verificar((int)valor('SELECT pagada FROM tickets WHERE id=?',[$factura])===0,'Factura cliente vuelve a pendiente');
        $repetida = false;
        try { anularPago($pdo,$tipo,$id,$fecha,'TEST'); } catch (Exception $e) { $repetida = str_contains($e->getMessage(),'ya está anulado'); }
        verificar($repetida,"$tipo impide doble anulación");
    }
    $idLegacy = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987654,'total'=>75,'mp1'=>$medio,'mp1imp'=>75,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    $ccLegacy = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>75,'idpago'=>0,'compasoc'=>987654,'tabla'=>'pagoaproveedores']);
    anularPago($pdo,'proveedor',$idLegacy,$fecha,'TEST');
    verificar((float)valor('SELECT pagos FROM moviemientosccproveedores WHERE id=?',[$ccLegacy])===0.0,'Pago migrado sin idpago revierte cuenta corriente por numcomp');
    $idColision = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987660,'total'=>75,'mp1'=>$medio,'mp1imp'=>75,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    $idMigrado = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>$idColision,'total'=>90,'mp1'=>$medio,'mp1imp'=>90,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    $ccColision = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>90,'idpago'=>$idColision,'compasoc'=>$idColision,'fecha'=>$fecha,'tabla'=>'pagoaproveedores']);
    anularPago($pdo,'proveedor',$idColision,$fecha,'TEST');
    verificar((float)valor('SELECT pagos FROM moviemientosccproveedores WHERE id=?',[$ccColision])===90.0,'ID interno coincidente con numcomp ajeno no anula otra cuenta corriente');
    anularPago($pdo,'proveedor',$idMigrado,$fecha,'TEST');
    verificar((float)valor('SELECT pagos FROM moviemientosccproveedores WHERE id=?',[$ccColision])===0.0,'Número migrado con idpago antiguo revierte la fila correcta');
    $idSinCC = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987655,'total'=>75,'mp1'=>$medio,'mp1imp'=>75,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    anularPago($pdo,'proveedor',$idSinCC,$fecha,'TEST');
    verificar((int)valor('SELECT estado FROM pagoaproveedores WHERE id=?',[$idSinCC])===1000,'Pago sin fila de cuenta corriente también se anula');
    verificar((float)valor("SELECT facturas FROM moviemientosccproveedores WHERE tabla='anulacion_pagoaproveedores' AND idpago=?",[$idSinCC])===75.0,'Pago sin fila de cuenta corriente genera contramovimiento');
    $idDuplicado = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987658,'total'=>75,'mp1'=>$medio,'mp1imp'=>75,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    $cc1 = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>25,'idpago'=>$idDuplicado,'tabla'=>'pagoaproveedores']);
    $cc2 = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>50,'idpago'=>$idDuplicado,'tabla'=>'pagoaproveedores']);
    anularPago($pdo,'proveedor',$idDuplicado,$fecha,'TEST');
    verificar((float)valor('SELECT SUM(pagos) FROM moviemientosccproveedores WHERE id IN (?,?)',[$cc1,$cc2])===75.0,'Movimientos ambiguos conservan historial');
    verificar((float)valor("SELECT facturas FROM moviemientosccproveedores WHERE tabla='anulacion_pagoaproveedores' AND idpago=?",[$idDuplicado])===75.0,'Movimientos ambiguos se compensan por su efecto real');
    $reciboSinCC = filaPrueba('pagos',['cliente'=>999999,'mp1'=>$medio,'mp2'=>0,'imp1'=>90,'imp2'=>0,'fecha'=>$fecha,'estado'=>1]);
    anularPago($pdo,'cliente',$reciboSinCC,$fecha,'TEST');
    verificar((float)valor("SELECT debe FROM ccclientes WHERE tabla='anulacion_pagos' AND compasoc=?",[$reciboSinCC])===90.0,'Recibo sin fila de cuenta corriente genera contramovimiento');
    $idReparar = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987656,'total'=>350000,'mp1'=>$medio,'mp1imp'=>350000,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1000]);
    $ccReparar = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>350000,'idpago'=>$idReparar,'tabla'=>'pagoaproveedores']);
    filaPrueba('cccuentastesoreria',['idcuenta'=>$medio,'debe'=>350000,'haber'=>0,'tabla'=>'pagoaproveedores','compasoc'=>$idReparar,'descripcion'=>'Anulación pago #'.$idReparar]);
    filaPrueba('librodiario',['cuenta'=>$cuenta,'debe'=>350000,'haber'=>0,'tabla'=>'pagoaproveedores','compasoc'=>$idReparar,'detalle'=>'Anulación pago #'.$idReparar]);
    $idSinReversos = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987657,'total'=>350000,'mp1'=>$medio,'mp1imp'=>350000,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1000]);
    $ccSinReversos = filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>350000,'idpago'=>$idSinReversos,'tabla'=>'pagoaproveedores']);
    $idRepararSinCC = filaPrueba('pagoaproveedores',['proveedor'=>999999,'numcomp'=>987659,'total'=>350000,'mp1'=>$medio,'mp1imp'=>350000,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1000]);
    filaPrueba('cccuentastesoreria',['idcuenta'=>$medio,'debe'=>350000,'haber'=>0,'tabla'=>'pagoaproveedores','compasoc'=>$idRepararSinCC,'descripcion'=>'Anulación pago #'.$idRepararSinCC]);
    filaPrueba('librodiario',['cuenta'=>$cuenta,'debe'=>350000,'haber'=>0,'tabla'=>'pagoaproveedores','compasoc'=>$idRepararSinCC,'detalle'=>'Anulación pago #'.$idRepararSinCC]);
    $sqlReparacion = file_get_contents('2026-09-16_Cambios_en_base.sql');
    $sqlReparacion = substr($sqlReparacion, strrpos($sqlReparacion, 'UPDATE moviemientosccproveedores m'));
    $pdo->exec($sqlReparacion);
    verificar((float)valor('SELECT pagos FROM moviemientosccproveedores WHERE id=?',[$ccReparar])===0.0,'Reparación de pago anulado con asiento y tesorería revertidos');
    $sinReversoDespues = (float)valor('SELECT pagos FROM moviemientosccproveedores WHERE id=?',[$ccSinReversos]);
    verificar($sinReversoDespues===350000.0,'Reparación no toca anulaciones antiguas sin contramovimientos (saldo: '.$sinReversoDespues.')');
    verificar((float)valor("SELECT facturas FROM moviemientosccproveedores WHERE tabla='anulacion_pagoaproveedores' AND idpago=?",[$idRepararSinCC])===350000.0,'Migración compensa pago anulado sin fila vinculada');
    $pdo->exec($sqlReparacion);
    verificar((int)valor("SELECT COUNT(*) FROM moviemientosccproveedores WHERE tabla='anulacion_pagoaproveedores' AND idpago=?",[$idRepararSinCC])===1,'Migración de compensación es idempotente');
    $giPago = filaPrueba('pagoaproveedores', ['proveedor'=>999998,'numcomp'=>987700,'total'=>80,'mp1'=>$medio,'mp1imp'=>80,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1,'fiscal'=>1]);
    $giFactura = filaPrueba('facturasdecompra', ['proveedorid'=>999998,'total'=>100,'fecha'=>$fecha,'fechaimputacion'=>$fecha,'fechavencimiento'=>$fecha,'estado'=>1,'saldoaimputar'=>100,'fiscal'=>1]);
    modificarGestionImputacion($pdo,'proveedor',999998,'agregar',$giFactura,$giPago,0,60);
    $giRelacion = (int)valor("SELECT id FROM pagosfacturasproveedores WHERE idpago=? AND idfactura=? AND tipo='P'",[$giPago,$giFactura]);
    verificar($giRelacion > 0 && (float)valor('SELECT saldoacuenta FROM pagoaproveedores WHERE id=?',[$giPago])===20.0,'Gestión proveedor agrega imputación y actualiza saldo');
    verificar(count(listarGestionImputaciones($pdo,'proveedor',999998,1,'',false)['facturas'][0]['imputaciones'])===1,'Gestión proveedor muestra imputaciones históricas');
    modificarGestionImputacion($pdo,'proveedor',999998,'importe',0,0,$giRelacion,40);
    verificar((float)valor('SELECT saldoaimputar FROM facturasdecompra WHERE id=?',[$giFactura])===60.0,'Gestión proveedor corrige importe anterior');
    $rechazoExceso = false;
    try { modificarGestionImputacion($pdo,'proveedor',999998,'importe',0,0,$giRelacion,200); } catch (Exception $e) { $rechazoExceso = str_contains($e->getMessage(),'supera'); }
    verificar($rechazoExceso && (float)valor('SELECT importepagado FROM pagosfacturasproveedores WHERE id=?',[$giRelacion])===40.0,'Gestión proveedor rechaza sobreimputación sin cambios');
    modificarGestionImputacion($pdo,'proveedor',999998,'quitar',0,0,$giRelacion,0);
    verificar((float)valor('SELECT saldoacuenta FROM pagoaproveedores WHERE id=?',[$giPago])===80.0 && (float)valor('SELECT saldoaimputar FROM facturasdecompra WHERE id=?',[$giFactura])===100.0,'Gestión proveedor desimputa y restaura ambos saldos');
    autoImputarSeleccionGestion($pdo,'proveedor',999998,[$giPago],[$giFactura]);
    verificar((float)valor('SELECT saldoacuenta FROM pagoaproveedores WHERE id=?',[$giPago])===0.0 && (float)valor('SELECT saldoaimputar FROM facturasdecompra WHERE id=?',[$giFactura])===20.0,'Imputación rápida proveedor usa saldos del servidor');
    $giRecibo = filaPrueba('pagos',['cliente'=>999998,'mp1'=>$medio,'imp1'=>80,'imp2'=>0,'retiva'=>0,'retgan'=>0,'retiibb'=>0,'fecha'=>$fecha,'estado'=>1]);
    filaPrueba('ccclientes',['cliente'=>999998,'compasoc'=>$giRecibo,'tabla'=>'pagos','fiscal'=>1]);
    $giTicket = filaPrueba('tickets',['cliente'=>999998,'tipocomp'=>1,'PtoVta'=>1,'numcomp'=>987701,'total'=>100,'fecha'=>$fecha,'pagada'=>0]);
    modificarGestionImputacion($pdo,'cliente',999998,'agregar',$giTicket,$giRecibo,0,60);
    $giRelacionCli = (int)valor("SELECT id FROM cobrosfacturasclientes WHERE idpago=? AND idfactura=? AND tablapago='pagos'",[$giRecibo,$giTicket]);
    verificar($giRelacionCli > 0 && (float)valor('SELECT saldoaimputar FROM pagos WHERE id=?',[$giRecibo])===20.0,'Gestión cliente agrega recibo y actualiza saldo');
    verificar(count(listarGestionImputaciones($pdo,'cliente',999998,1,(string)$giTicket,false)['facturas'][0]['imputaciones'])===1,'Gestión cliente encuentra una imputación histórica por ID');
    verificar((float)buscarFondosGestion($pdo,'cliente',999998,(string)$giRecibo)[0]['disponible']===20.0,'Buscador de recibos muestra saldo real disponible');
    modificarGestionImputacion($pdo,'cliente',999998,'quitar',0,0,$giRelacionCli,0);
    verificar((float)valor('SELECT saldoaimputar FROM pagos WHERE id=?',[$giRecibo])===80.0 && (int)valor('SELECT pagada FROM tickets WHERE id=?',[$giTicket])===0,'Gestión cliente desimputa y restaura factura');
    autoImputarSeleccionGestion($pdo,'cliente',999998,[$giRecibo],[$giTicket]);
    verificar((float)valor('SELECT saldoaimputar FROM pagos WHERE id=?',[$giRecibo])===0.0 && (float)valor('SELECT importepagado FROM cobrosfacturasclientes WHERE idpago=? AND idfactura=? ORDER BY id DESC LIMIT 1',[$giRecibo,$giTicket])===80.0,'Imputación rápida cliente usa saldos del servidor');
    $idRecibo = filaPrueba('pagos', ['cliente'=>999999, 'mp1'=>$medio, 'mp2'=>0, 'imp1'=>60, 'imp2'=>0, 'fecha'=>$fecha, 'estado'=>1]);
    $cheque = filaPrueba('chequesterceros', ['cliente'=>999999,'importe'=>60,'recibo_origen_id'=>$idRecibo,'compasoc'=>$idRecibo,'tabla'=>'pagos','estado'=>0]);
    anularPago($pdo,'cliente',$idRecibo,$fecha,'TEST');
    verificar((int)valor('SELECT estado FROM chequesterceros WHERE id=?',[$cheque])===1000,'Cheque en cartera se anula con su recibo');
    $idPago = filaPrueba('pagoaproveedores',['proveedor'=>999999,'total'=>60,'mp1'=>$medio,'mp1imp'=>60,'fecha'=>$fecha,'fecharecibo'=>$fecha,'estado'=>1]);
    filaPrueba('moviemientosccproveedores',['proveedor'=>999999,'pagos'=>60,'idpago'=>$idPago,'tabla'=>'pagoaproveedores']);
    $idRecibo = filaPrueba('pagos',['cliente'=>999999,'mp1'=>$medio,'mp2'=>0,'imp1'=>60,'imp2'=>0,'fecha'=>$fecha,'estado'=>1]);
    $cheque = filaPrueba('chequesterceros',['cliente'=>999999,'importe'=>60,'recibo_origen_id'=>$idRecibo,'compasoc'=>$idRecibo,'tabla'=>'pagos','estado'=>2,'endosadoa'=>999999,'pago_endoso_id'=>$idPago]);
    $pdo->exec('SAVEPOINT cheque_depositado');
    $bloqueado = false;
    try { anularPago($pdo,'cliente',$idRecibo,$fecha,'TEST'); } catch (Exception $e) { $bloqueado = str_contains($e->getMessage(),'depositado o endosado'); }
    $pdo->exec('ROLLBACK TO SAVEPOINT cheque_depositado');
    verificar($bloqueado && (int)valor('SELECT estado FROM pagos WHERE id=?',[$idRecibo])===1,'Cheque endosado bloquea anulación del recibo sin cambios parciales');
    anularPago($pdo,'proveedor',$idPago,$fecha,'TEST');
    verificar((int)valor('SELECT estado FROM chequesterceros WHERE id=?',[$cheque])===0,'Anular pago devuelve cheque endosado a cartera');
    verificar((int)valor('SELECT recibo_origen_id FROM chequesterceros WHERE id=?',[$cheque])===$idRecibo,'Se conserva recibo original del cheque');
    anularPago($pdo,'cliente',$idRecibo,$fecha,'TEST');
    verificar((int)valor('SELECT estado FROM chequesterceros WHERE id=?',[$cheque])===1000,'Luego se puede anular el recibo original');

    $proveedor = valor("SELECT Proveedor FROM alb_productos WHERE Proveedor <> '' LIMIT 1");
    $sql='SELECT COUNT(*) FROM alb_productos p WHERE 1=1';$params=[];
    aplicarFiltrosProductos($sql,$params,['proveedor'=>$proveedor,'habilitado'=>'todos']);
    verificar((int)valor($sql,$params)===(int)valor('SELECT COUNT(*) FROM alb_productos WHERE Proveedor=?',[$proveedor]),'Filtro proveedor coincide en consulta compartida');
    echo "Todas las pruebas pasaron. ROLLBACK de datos de prueba.\n";
} finally { $pdo->rollBack(); }
