<?php

/**
 * api_facturar_gets.php - ENDPOINT CENTRALIZADO DE DATOS PARA FACTURACIÓN
 * Retorna maestros de Clientes, Vendedores, Medios de Pago y CONFIGURACIÓN.
 * ACTUALIZACIÓN: Autodetección de Caja y Filtrado Estricto de Medios de Pago.
 */
require_once 'check_api_access.php';
require_once 'arca_certificados.php';

$action = $_GET['action'] ?? '';
$usuario = $_SESSION['usuario_nombre'] ?? 'SISTEMA';

// ---------------------------------------------------------------------------
// AUTODETECCIÓN DE CAJA ABIERTA (Directo de BD, sin sesión)
// ---------------------------------------------------------------------------
$idcajaabierta = 0;
// Buscar en tabla 'cajas' si el usuario figura como activo en alguna
$stmtBox = $pdo->prepare("SELECT id FROM cajas WHERE usuarioactivo = ? AND estado = 1 LIMIT 1");
$stmtBox->execute([$usuario]);
$cajaIdDB = $stmtBox->fetchColumn();

if ($cajaIdDB) {
    $idcajaabierta = $cajaIdDB;
}
// ---------------------------------------------------------------------------

try {
    switch ($action) {
        case 'get_maestros':
            // 1. Vendedores
            $stmtVend = $pdo->query("SELECT id, nombre FROM vendedores ORDER BY nombre ASC");
            $vendedores = $stmtVend->fetchAll(PDO::FETCH_ASSOC);

            // 2. Medios de Pago (FILTRADO POR CAJA)
            // Se trae solo la caja del usuario activo si está abierta
            $sqlMP = "SELECT id, medio, recargo, descuento, tipodecuenta FROM mediosdepago 
                      WHERE habilitado = 1 
                      AND visibleenventas = 1 
                      AND (
                          (tipodecuenta = 'Caja de Usuarios' AND (puestoasociado = (SELECT numcaja FROM cajas WHERE id = ?) OR puestoasociado = ?)) 
                          OR tipodecuenta <> 'Caja de Usuarios'
                      )
                      ORDER BY medio ASC";
            $stmtMP = $pdo->prepare($sqlMP);
            $stmtMP->execute([$idcajaabierta, $idcajaabierta]);
            $medios = $stmtMP->fetchAll(PDO::FETCH_ASSOC);

            // 3. Datos de la Empresa (ID 1)
            $stmtEmp = $pdo->query("SELECT * FROM datosempresa WHERE id = 1 LIMIT 1");
            $empresa = $stmtEmp->fetch(PDO::FETCH_ASSOC);
            $_SESSION['condicion_iva_empresa'] = $empresa['condicioniva']; // Para uso en frontend

            // 4. CONFIGURACIÓN
            $stmtCfg = $pdo->query("SELECT `id`, `imptickets`, `imppdf`, `rutafacturas`, `rutacert`, `modoafip`, `tokenafip`, `pswcert`, `rutalic`, `fechastock`, `version`, `pideclave`, `montominimo`, `maximoajustes`, `stocknegativo`, `tipoventa`, `poriibb`, `retgan`, `retiibb`, `retiva`, `habilitarubros`, `modiflistas`, `texboxpredeterminado`, `admitendc`, `modifdatos`, `fechacreacion`, `usuario`, `loteyvenc`, `descuentosglobales`, `descuentosporlinea`, `recargosglobales`, `tipodescuentoindividual`, `ticketsiniva`, `agruparproductos`, `modificarprecioenventas`, `predeterminarcliente`, `predeterminarcomprobante`, `clientepredeterminado`, `imprimeremitoenviopostfactura`, `leyenda2`, `tieneweb`, `imputarcobros`, `formatoimpresion`, `tablareducidaventas` FROM `configuracion` WHERE id = (SELECT MAX(id) FROM configuracion) LIMIT 1");
            $configurame = $stmtCfg->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'vendedores' => $vendedores,
                'medios_pago' => $medios,
                'empresa' => array_merge($empresa, ['pdv' => (int) ($_SESSION['pdv'] ?? 4)]),
                'configuracion' => $configurame,
                'arca_fiscal_habilitado' => arcaCredencialesFiscalesDisponibles($_SESSION['cliente_cuit'] ?? '')
            ]);
            break;

        case 'buscar_clientes':
            $q = $_GET['q'] ?? '';
            $sql = "SELECT * FROM alb_clientes WHERE habilitado = 1";
            // PERMISO ESPECIAL: filtrar solo clientes del vendedor asignado
            if ((int) ($_SESSION['pe_unvendedor'] ?? 0) === 1) {
                $sql .= " AND vendedorid = " . (int) $_SESSION['pe_idvendedor'];
            }
            if (!empty($q)) {
                $sql .= " AND (razon LIKE ? OR numdoc LIKE ? OR domicilio LIKE ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(["%$q%", "%$q%", "%$q%"]);
            } else {
                $stmt = $pdo->query($sql . " ORDER BY razon ASC LIMIT 50");
            }
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'buscar_productos':
            $q = $_GET['q'] ?? '';
            $sql = "SELECT * FROM alb_productos WHERE habilitado = 1 AND (codigo LIKE ? OR descripcion LIKE ? OR rubro LIKE ?) ORDER BY descripcion ASC LIMIT 50";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(["%$q%", "%$q%", "%$q%"]);
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;
        case 'buscar_codigo_producto':

            $q = $_GET['q'] ?? '';

            $sql = "
                    SELECT p.*
                    FROM referenciasproductos r
                    INNER JOIN alb_productos p 
                        ON p.codigo = r.refierea
                    WHERE r.codigo = ?
                    AND p.habilitado = 1
                    ORDER BY p.descripcion ASC
                    LIMIT 50
                ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$q]);

            echo json_encode([
                'success' => true,
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);

            break;

        case 'buscar_codigo_exacto':
            // Coincidencia EXACTA por código (para el campo de escaneo/lectora).
            // A diferencia de 'buscar_codigo_producto', no depende de que exista
            // una fila en referenciasproductos para el código real (la mayoría de
            // los productos nunca tuvo un alias propio cargado, así que exigir esa
            // fila hacía que escanear el código real de muchos productos "no
            // encontrara" nada). El llamador debe resolver alias antes con
            // api_referencias.php?action=resolve; acá solo se busca el código ya
            // resuelto, uno a uno, sin LIKE, para que nunca traiga otro producto.
            $q = trim($_GET['q'] ?? '');
            if ($q === '') {
                echo json_encode(['success' => true, 'data' => []]);
                break;
            }
            $stmt = $pdo->prepare("SELECT * FROM alb_productos WHERE codigo = ? AND habilitado = 1 LIMIT 1");
            $stmt->execute([$q]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $prod ? [$prod] : []]);
            break;

        case 'get_proximo_numero':
            $tipo = $_GET['tipo'] ?? 6;
            $pv = $_GET['pv'] ?? $_SESSION['pdv'] ?? 4;
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(numcomp), 0) + 1 FROM tickets WHERE tipocomp = ? AND PtoVta = ?");
            $stmt->execute([(int) $tipo, (int) $pv]);
            $prox = $stmt->fetchColumn();
            echo json_encode(['success' => true, 'proximo' => str_pad($prox, 8, '0', STR_PAD_LEFT)]);
            break;

        case 'get_pedido_para_facturar':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT p.*, c.razon, c.numdoc, c.condicionfiscal, c.vendedorid, c.ListaDePRecios as lista_original_cli, c.domicilio, c.cuentacorriente
                                   FROM pedidosclientes p
                                   INNER JOIN alb_clientes c ON p.cliente = c.id
                                   WHERE p.numpedido = ?");
            $stmt->execute([$id]);
            $ped = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$ped)
                throw new Exception("Pedido no encontrado.");
            if ($ped['estadofac'] == 1)
                throw new Exception("El pedido ya se encuentra FACTURADO.");
            if ($ped['estadofac'] == 2)
                throw new Exception("El pedido se encuentra ANULADO.");

            $sqlLin = "SELECT l.*, p.poriva, p.ImpInt, p.preciocosto, p.rubro, p.marca, p.controlastock,
                              p.siniva, p.siniva2, p.siniva3, p.siniva4, p.siniva5, p.siniva6, p.siniva7, p.siniva8
                       FROM lineapedidosclientes l
                       INNER JOIN alb_productos p ON l.producto = p.codigo
                       WHERE l.numpedido = ?";
            $stmtLin = $pdo->prepare($sqlLin);
            $stmtLin->execute([$id]);
            echo json_encode(['success' => true, 'pedido' => $ped, 'items' => $stmtLin->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'get_presupuesto_para_facturar':
            $id = $_GET['id'] ?? 0;
            // REGLA: Los presupuestos usan las mismas tablas estructurales que pedidos
            $stmt = $pdo->prepare("SELECT p.*, c.razon, c.numdoc, c.condicionfiscal, c.vendedorid, c.ListaDePRecios as lista_original_cli, c.domicilio, c.cuentacorriente
                                   FROM presupuestosclientes p
                                   INNER JOIN alb_clientes c ON p.cliente = c.id
                                   WHERE p.numpedido = ?");
            $stmt->execute([$id]);
            $pre = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pre)
                throw new Exception("Presupuesto no encontrado.");
            if ($pre['estadofac'] == 1)
                throw new Exception("El presupuesto ya se encuentra FACTURADO.");
            if ($pre['estadofac'] == 2)
                throw new Exception("El presupuesto se encuentra ANULADO.");

            $sqlLin = "SELECT l.*, p.poriva, p.ImpInt, p.preciocosto, p.rubro, p.marca, p.controlastock,
                              p.siniva, p.siniva2, p.siniva3, p.siniva4, p.siniva5, p.siniva6, p.siniva7, p.siniva8
                       FROM lineapresupuestosclientes l
                       INNER JOIN alb_productos p ON l.producto = p.codigo
                       WHERE l.numpedido = ?";
            $stmtLin = $pdo->prepare($sqlLin);
            $stmtLin->execute([$id]);
            echo json_encode(['success' => true, 'presupuesto' => $pre, 'items' => $stmtLin->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'buscar_ticket_asoc':
            $num = $_GET['num'] ?? 0;
            $tipo = $_GET['tipo'] ?? 0;
            $pv = $_GET['pv'] ?? 0;
            $stmt = $pdo->prepare("SELECT id, cliente, fecha, total FROM tickets WHERE numcomp = ? AND tipocomp = ? AND ptovta = ? LIMIT 1");
            $stmt->execute([(int) $num, (int) $tipo, (int) $pv]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$res)
                throw new Exception("Comprobante no encontrado.");
            echo json_encode(['success' => true, 'data' => $res]);
            break;
        case 'get_pedido_facturar':
            $id = $_GET['id'] ?? 0;
            if (!$id)
                throw new Exception("ID de pedido no válido.");

            // 1. Validar Cabecera y Estado
            $stmtCab = $pdo->prepare("SELECT * FROM pedidosclientes WHERE numpedido = ?");
            $stmtCab->execute([$id]);
            $pedido = $stmtCab->fetch(PDO::FETCH_ASSOC);
            if (!$pedido)
                throw new Exception("Pedido no encontrado.");
            if ($pedido['estadofac'] == 1)
                throw new Exception("Este pedido ya fue FACTURADO.");
            if ($pedido['estadofac'] == 2)
                throw new Exception("Este pedido se encuentra ANULADO.");

            // 2. Traer Datos Completos del Cliente
            $stmtCli = $pdo->prepare("SELECT id, razon, numdoc, condicionfiscal, cuentacorriente, ListaDePRecios, vendedorid, email FROM alb_clientes WHERE id = ?");
            $stmtCli->execute([$pedido['cliente']]);
            $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

            // 3. Traer Líneas y cruzar con la información fiscal de alb_productos
            $sqlLin = "SELECT l.*, p.poriva, p.ImpInt, p.preciocosto, p.rubro, p.marca, p.controlastock 
                       FROM lineapedidosclientes l
                       INNER JOIN alb_productos p ON l.producto = p.codigo
                       WHERE l.numpedido = ? ORDER BY l.id ASC";
            $stmtLin = $pdo->prepare($sqlLin);
            $stmtLin->execute([$id]);

            echo json_encode(['success' => true, 'pedido' => $pedido, 'cliente' => $cliente, 'items' => $stmtLin->fetchAll(PDO::FETCH_ASSOC)]);
            break;


        case 'get_ticket_para_nc':
            /**
             * Devuelve cabecera + líneas de un ticket existente para pre-cargar
             * una Nota de Crédito en facturar.php?notacredito={id}
             *
             * Mapa de tipos antagónicos:
             *   1,2  → 3   (NC A)
             *   6,7  → 8   (NC B)
             *   11,12→ 13  (NC C)
             *   100  → 101 (Ticket Crédito)
             */
            $id = (int)($_GET['id'] ?? 0);
            if (!$id) throw new Exception("ID de comprobante no válido.");

            // 1. Cabecera del ticket + datos del cliente
            $stmtTk = $pdo->prepare(
                "SELECT t.*, c.razon, c.numdoc, c.condicionfiscal, c.vendedorid,
                        c.ListaDePRecios AS lista_original_cli, c.domicilio,
                        c.cuentacorriente
                 FROM tickets t
                 INNER JOIN alb_clientes c ON t.cliente = c.id
                 WHERE t.id = ?
                 LIMIT 1"
            );
            $stmtTk->execute([$id]);
            $ticket = $stmtTk->fetch(PDO::FETCH_ASSOC);
            if (!$ticket) throw new Exception("Comprobante no encontrado (id=$id).");

            // 2. Calcular tipo antagónico
            $tipoOrig = (int)$ticket['tipocomp'];
            $mapaNC = [1=>3, 2=>3, 6=>8, 7=>8, 11=>13, 12=>13, 100=>101];
            if (!isset($mapaNC[$tipoOrig])) {
                throw new Exception("El comprobante tipo $tipoOrig no puede generar una Nota de Crédito.");
            }
            $tipoNC = $mapaNC[$tipoOrig];

            // 3. Líneas del ticket cruzadas con datos fiscales del producto
            $stmtLin = $pdo->prepare(
                "SELECT l.prodcod      AS producto,
                        l.proddes      AS descripcion,
                        l.cant         AS cantidad,
                        (l.siniva / NULLIF(l.cant, 0)) AS preciounit,
                        l.alicuota     AS poriva,
                        (l.ii / NULLIF(l.cant, 0)) AS impint,
                        l.total,
                        l.lista,
                        l.totaldescuento,
                        l.costo        AS preciocosto,
                        l.rubro,
                        p.ImpInt,
                        p.controlastock,
                        p.siniva,  p.siniva2, p.siniva3, p.siniva4,
                        p.siniva5, p.siniva6, p.siniva7, p.siniva8
                 FROM Lineadeventa l
                 LEFT JOIN alb_productos p ON p.codigo = l.prodcod
                 WHERE ((? > 0 AND ? >= ? AND l.id BETWEEN ? AND ?)
                    OR (? <= 0 AND l.Tipocomp = ? AND l.numcomp = ? AND l.pdv = ?))
                   AND l.prodcod NOT IN ('DESC','RECA')
                 ORDER BY l.id ASC"
            );
            $stmtLin->execute([
                (int)$ticket['lvdesde'],
                (int)$ticket['lvhasta'],
                (int)$ticket['lvdesde'],
                (int)$ticket['lvdesde'],
                (int)$ticket['lvhasta'],
                (int)$ticket['lvdesde'],
                (int)$ticket['tipocomp'],
                (int)$ticket['numcomp'],
                (int)$ticket['PtoVta']
            ]);
            $items = $stmtLin->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'ticket'   => $ticket,
                'items'    => $items,
                'tipo_nc'  => $tipoNC
            ]);
            break;

        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
