<?php
/**
 * Traduce los permisos del legacy VB.NET (tabla nivelesdeacceso: un registro por usuario y botón/menú de
 * cada formulario, con habilitado 0/1) a los del sistema nuevo (accesosusuarios.permisos =
 * {"allowed_ids": [ids de menu2.json], "allowed_urls": [urls]}).
 *
 * Reglas:
 *  - Una opción del menú nuevo queda permitida si el usuario tenía habilitado ALGUNO de los controles
 *    legacy que la equivalen Y el botón de esa sección en el menú principal (menuprincipal.btnX): en el
 *    legacy, sin el botón principal no se llegaba a ningún submenú.
 *  - Se agregan las secciones padre de cada opción permitida (el menú las necesita para mostrarla).
 *  - Los administradores (esadmin = 1) ven todo en el sistema nuevo: no llevan registro.
 *  - Lo que el legacy no restringía y el sistema nuevo agregó (dashboard rápido) se da a todos.
 *  - Los permisos especiales (tabla permisosespeciales: precios de costo, un solo vendedor, modificar
 *    precios, facturar pedidos, modificar facturas) son la misma tabla en los dos sistemas y pasan con la
 *    copia; las columnas nuevas quedan en su default (restringido).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

// formulario => botón del menú principal que lo habilita
const VB_SECCION = [
    'MenuProductos' => 'btnproductos', 'frminventario' => 'btnproductos', 'MenuClientes' => 'btnclientes',
    'Menuproveedores' => 'btnproveedores', 'MenuFacturacion' => 'btnfacturacion', 'MenuFondos' => 'btnfondos',
    'Menucontabilidad' => 'btncontabilidad', 'Menureportes' => 'btnreportes', 'Menuvendedores' => 'btnvendedores',
    'Menutransportes' => 'btntransportes', 'FormAdministrador' => 'btnadmin', 'FormSetting' => 'btnconfig',
    'Menurrhhh' => 'btnrrhh',
];

// "formulario.control" => ids de menu2.json
const VB_A_MENU = [
    // Productos
    'MenuProductos.btnadmproductos' => ['prod_adm'], 'MenuProductos.btnagregarprod' => ['prod_add'],
    'MenuProductos.btnadmrubros' => ['rub_adm'], 'MenuProductos.btnagregarrubros' => ['rub_add'],
    'MenuProductos.btnadmmarcas' => ['mar_adm'], 'MenuProductos.btnagregarmarca' => ['mar_add'],
    'MenuProductos.btnadministrarfamilia' => ['fam_adm'], 'MenuProductos.btnagregarfamilia' => ['fam_add'],
    'MenuProductos.btneditarlistas' => ['prc_edit', 'promo_admin', 'promo_reporte'],
    'MenuProductos.btnaumentomasivo' => ['prc_aum'], 'MenuProductos.btnimportarexcel' => ['prc_exc'],
    'MenuProductos.IconButton1' => ['prod_imp'], 'MenuProductos.IconButton2' => ['pdn_adm', 'pdn_rep'],
    'MenuProductos.IconButton3' => ['pdn_new'], 'MenuProductos.IconButton4' => ['pdn_rec'],
    'frminventario.btnmovstock' => ['inv_mov'], 'frminventario.btningresostock' => ['inv_ing'],
    'frminventario.btnajustegrupo' => ['inv_ajg', 'adm_ajuste_excel'], 'frminventario.btnajusteindividual' => ['inv_aji'],
    // Clientes
    'MenuClientes.btnadmclientes' => ['cli_adm', 'cli_ramas'], 'MenuClientes.btnagregarcli' => ['cli_add'],
    'MenuClientes.btncobrarfactu' => ['cob_fac'], 'MenuClientes.clientesbtnimputar' => ['cob_imp'],
    'MenuClientes.IconButton3' => ['cob_nc'], 'MenuClientes.btnverfcpendientesclientes' => ['inf_pend'],
    'MenuClientes.btnestadodecuentacli' => ['inf_res'], 'MenuClientes.IconButton4' => ['inf_res'],
    'MenuClientes.IconButton1' => ['inf_comp'], 'MenuClientes.btnctaporvendedor' => ['inf_vend'],
    'MenuClientes.IconButton2' => ['inf_rec'], 'MenuClientes.IconButton5' => ['cli_import'],
    // Proveedores
    'Menuproveedores.btnadmproveedores' => ['prv_adm'], 'Menuproveedores.btnagregarproveedor' => ['prv_add'],
    'Menuproveedores.btnpagarfacturas' => ['pag_fac'], 'Menuproveedores.btnimputarpagosprov' => ['pag_imp'],
    'Menuproveedores.IconButton2' => ['pag_nc'], 'Menuproveedores.btnverfcpendientes' => ['prv_inf_pend'],
    'Menuproveedores.btnestadoctaprov' => ['prv_inf_res'], 'Menuproveedores.IconButton1' => ['prv_inf_pag'],
    'Menuproveedores.btnadministrarconceptos' => ['prv_conceptos'], 'Menuproveedores.btnadministrarOC' => ['oc_ver'],
    'Menuproveedores.btnnuevaOC' => ['oc_new'], 'Menuproveedores.btncargarfccompras' => ['fccompra_new'],
    'Menuproveedores.btnimportarfcdecompra' => ['fccompra_new'], 'Menuproveedores.btnverfaccompras' => ['fccompra_admin'],
    'Menuproveedores.IconButton3' => ['prv_import'],
    // Vendedores / transportes
    'Menuvendedores.btnadmvendedor' => ['ven_adm'], 'Menuvendedores.btnagregarvendedor' => ['ven_add'],
    'Menuvendedores.btnpagarcometa' => ['ven_comi'], 'Menuvendedores.btnresumenctavende' => ['ven_res'],
    'Menutransportes.btnadmtransportes' => ['tra_adm'], 'Menutransportes.btnagregartransporte' => ['tra_add'],
    'Menutransportes.transporteimprimirenvios' => ['tra_prn'],
    // Facturación y caja propia
    'MenuFacturacion.btnfacturarmostrador' => ['fac_emit'], 'MenuFacturacion.btnfacturaadmin' => ['fac_emit'],
    'MenuFacturacion.btncomprobantesemitidos' => ['fac_comp'], 'MenuFacturacion.btnadmpedidos' => ['ped_adm'],
    'MenuFacturacion.btnnuevopedido' => ['ped_nue'], 'MenuFacturacion.btnadmpresupuestos' => ['presup_adm'],
    'MenuFacturacion.btnnuevopresupuesto' => ['presup_nue'], 'MenuFacturacion.IconButton2' => ['fac_ml'],
    'MenuFacturacion.botonabrircajafactu' => ['caja_abr', 'caja_cerr'], 'MenuFacturacion.btnvermovimientos' => ['rcj_usr'],
    // Fondos
    'MenuFondos.fondobancos' => ['bnc_adm', 'bnc_entidades_adm', 'bnc_conc'], 'MenuFondos.fondoCuentasBancariasToolStripMenuItem' => ['bnc_adm'],
    'MenuFondos.AdministrarPuestosToolStripMenuItem' => ['fnd_puestos_cobro'],
    'MenuFondos.fondocheques' => ['chq_adm', 'chq_reg'], 'MenuFondos.fondosVerCarteraToolStripMenuItem' => ['chq_adm'],
    'MenuFondos.fondoscargarchequesterceros' => ['chq_adm'], 'MenuFondos.fondosVerChequesEmitidosToolStripMenuItem' => ['chq_reg'],
    'MenuFondos.fondotesoreria' => ['fondos_admcuentas'], 'MenuFondos.fondoAdministrarCuentasToolStripMenuItem' => ['fondos_admcuentas'],
    'MenuFondos.fondoAministrarCuentasToolStripMenuItem' => ['fondos_admcuentas'], 'MenuFondos.fondoNuevaCuentaToolStripMenuItem' => ['fondos_nuevacta'],
    'MenuFondos.fondomovimientos' => ['fondos_nuevomov'], 'MenuFondos.fondoNuevoMovimientoToolStripMenuItem' => ['fondos_nuevomov'],
    'MenuFondos.IngresosToolStripMenuItem1' => ['fondos_nuevomov'],
    'MenuFondos.AdministrarMonedasToolStripMenuItem' => ['fnd_mon'], 'MenuFondos.MONEDASYTIPOSDECAMBIOToolStripMenuItem' => ['fnd_mon'],
    'MenuFondos.TiposDeCambioToolStripMenuItem' => ['fnd_mon'],
    'MenuFondos.TarjetasToolStripMenuItem' => ['fnd_tarjetas'], 'MenuFondos.AdministrarTarjetasToolStripMenuItem' => ['fnd_tarjetas'],
    'MenuFondos.RegistrarCuponesCobradosToolStripMenuItem' => ['fnd_tarjetas'],
    'MenuFondos.fondoretirocajacentral' => ['caja_reti'], 'MenuFondos.fondotransferenciaotracaja' => ['caja_trans'],
    'MenuFondos.fondoMovimientoDeAjusteToolStripMenuItem' => ['caja_arqueo'],
    'MenuFondos.fondoreportes' => ['inf_tes'], 'MenuFondos.fondoreportebancos' => ['inf_tes'],
    'MenuFondos.fondosreportesCuentasTesoreríaToolStripMenuItem' => ['inf_tes'], 'MenuFondos.ResumenDeCuentaToolStripMenuItem' => ['inf_tes'],
    'MenuFondos.VerMovimientosToolStripMenuItem1' => ['inf_tes'],
    'MenuFondos.fondoreportecajas' => ['rcj_usr', 'rcj_mov'], 'MenuFondos.fondoreporteCajasDeUsuariosToolStripMenuItem' => ['rcj_usr', 'rcj_mov'],
    'MenuFondos.fondosreportesInformeDeCajasToolStripMenuItem' => ['rcj_usr'], 'MenuFondos.fondosreportesSaldoEnCajasToolStripMenuItem' => ['rcj_usr'],
    'MenuFondos.fondosreportesAuditoríaDeCajasToolStripMenuItem' => ['rcj_ctrl'],
    'MenuFondos.fondoCajaCentralToolStripMenuItem' => ['rcj_cnt'], 'MenuFondos.fondoreportecajacentral' => ['rcj_cnt'],
    // Contabilidad
    'Menucontabilidad.btnplancuentas' => ['cnt_plan'], 'Menucontabilidad.contabilidadagregarcuenta' => ['cnt_plan'],
    'Menucontabilidad.btnlibrodiarios' => ['cnt_diario', 'imp_libro_iva', 'imp_retper_reg', 'imp_retper_pract'],
    'Menucontabilidad.btndiarioagrupado' => ['cnt_diario'], 'Menucontabilidad.btnlibromayor' => ['cnt_mayor'],
    'Menucontabilidad.btnbalance' => ['cnt_balance'], 'Menucontabilidad.btnasientomanual' => ['cnt_manual'],
    'Menucontabilidad.contabilidadconfiguracion' => ['cnt_auto'], 'Menucontabilidad.contabilidadabrirejercicio' => ['cnt_ejer'],
    'Menucontabilidad.IconButton1' => ['cnt_per'], 'Menucontabilidad.IconButton2' => ['cnt_masivo'],
    // Reportes
    'Menureportes.VerReporteDeVentasToolStripMenuItem' => ['rv_inf', 'dash_gerencial'],
    'Menureportes.VerComprobantesEmitidosToolStripMenuItem' => ['rv_com'], 'Menureportes.VerDescuentosRealizadosToolStripMenuItem' => ['rv_des'],
    'Menureportes.VentasPorMedioDePagoToolStripMenuItem' => ['rm_ventas'], 'Menureportes.VerCobrosRecibidosToolStripMenuItem' => ['rm_cobros'],
    'Menureportes.VerPagosRealizadosToolStripMenuItem' => ['rm_pagos'], 'Menureportes.VerPagosRealizadosToolStripMenuItem1' => ['rm_pagos'],
    'Menureportes.VerComprobantesRecibidosToolStripMenuItem' => ['rc_f_ver'], 'Menureportes.VerFacturasDeCompraToolStripMenuItem' => ['rc_f_ver'],
    'Menureportes.FacturasPendientesDePagoToolStripMenuItem' => ['rc_f_pend'], 'Menureportes.historicoscompra' => ['rc_hist'],
    'Menureportes.VerMovimientosPorConceptosToolStripMenuItem' => ['rc_conc'],
    'Menureportes.CajasToolStripMenuItem' => ['rep_cashflow'], 'Menureportes.ControlDeCajasToolStripMenuItem' => ['rcj_ctrl'],
    'Menureportes.MovimientosDeCajasToolStripMenuItem' => ['rcj_mov'], 'Menureportes.MovimientosDeCajaCentralToolStripMenuItem' => ['rcj_cnt'],
    'Menureportes.VerUsuariosPorCajasToolStripMenuItem' => ['rcj_usr'],
    'Menureportes.ListadosToolStripMenuItem' => ['rl_prod'], 'Menureportes.ClientesToolStripMenuItem' => ['rl_cli'],
    'Menureportes.ProveedoresToolStripMenuItem' => ['rl_prv'], 'Menureportes.VendedoresToolStripMenuItem' => ['rl_ven'],
    'Menureportes.UsuariosToolStripMenuItem' => ['rl_usr'], 'Menureportes.TransportesToolStripMenuItem' => ['rl_tra'],
    'Menureportes.ClientesToolStripMenuItem1' => ['inf_res'], 'Menureportes.VerCuentaCorrienteToolStripMenuItem' => ['inf_res'],
    'Menureportes.VerCuentasCorrientesToolStripMenuItem' => ['inf_res'], 'Menureportes.ProveedoresToolStripMenuItem1' => ['prv_inf_res'],
    'Menureportes.VerCuentasCorrientesToolStripMenuItem1' => ['prv_inf_res'], 'Menureportes.VendedoresToolStripMenuItem1' => ['ven_res'],
    'Menureportes.AjustesDeInventarioToolStripMenuItem' => ['rs_aj_inv'], 'Menureportes.AjsutesValorizadosToolStripMenuItem' => ['rs_aj_val'],
    'Menureportes.MovimientosDeStockToolStripMenuItem' => ['rs_mov'], 'Menureportes.VerIngresosDeMercaderíaToolStripMenuItem' => ['rs_mov'],
    'Menureportes.LibroDiarioToolStripMenuItem' => ['rct_diario'], 'Menureportes.LibroMayorToolStripMenuItem' => ['rct_mayor'],
    'Menureportes.BalanceDeSumasYSaldosToolStripMenuItem' => ['rct_bal'], 'Menureportes.LoginDeUsuariosToolStripMenuItem' => ['ra_log'],
    'Menureportes.HistorialDeCambiosDePrecioToolStripMenuItem' => ['adm_hist'],
    // Administrador / configuración
    'FormAdministrador.btnadmusuarios' => ['adm_usr'], 'FormAdministrador.btnagregarusr' => ['adm_new'],
    'FormAdministrador.btnnivelesacceso' => ['adm_niv', 'adm_noper'], 'FormAdministrador.btnloginusuarios' => ['adm_log', 'ra_log'],
    'FormAdministrador.btnmodifcodigo' => ['adm_cod'], 'FormAdministrador.btnhistorico' => ['adm_hist'],
    'FormAdministrador.btnadmauditoriadecobros' => ['adm_aud_cob'], 'FormAdministrador.btnagregarpuesto' => ['fnd_puestos_cobro'],
    'FormAdministrador.btncontrolcajas' => ['rcj_ctrl', 'rcj_usr'],
    'FormSetting.btndatosempresa' => ['cfg_emp', 'cfg_solicitar_cert', 'cfg_subir_cert'],
    'FormSetting.btnconfiggral' => ['cfg_gral', 'cfg_apis'], 'FormSetting.btnadmuns' => ['cfg_uns'],
];

// Lo que el sistema nuevo agrega sin equivalente restringido en el legacy
const MENU_PARA_TODOS = ['dash_rapido'];

/** Devuelve [usuario => ['allowed_ids' => [...], 'allowed_urls' => [...]]] para los no administradores con registros. */
function traducirNivelesDeAcceso(PDO $pdo, string $menuJson = 'C:/xampp/htdocs/sistema/menu2.json'): array
{
    $menu = json_decode(file_get_contents($menuJson), true);
    $padres = []; $urls = [];
    $recorrer = function ($items, array $ancestros) use (&$recorrer, &$padres, &$urls) {
        foreach ($items as $it) {
            $padres[$it['id']] = $ancestros;
            if (!empty($it['url']) && trim($it['url']) !== '') $urls[$it['id']] = trim($it['url']);
            if (!empty($it['children'])) $recorrer($it['children'], array_merge($ancestros, [$it['id']]));
        }
    };
    $recorrer($menu, []);
    foreach (VB_A_MENU as $k => $ids) foreach ($ids as $id) if (!isset($padres[$id])) throw new Exception("permisos_vb: '$id' ($k) no existe en menu2.json");

    $res = [];
    $filas = $pdo->query("SELECT n.usuario, n.formulario, n.control, MAX(n.habilitado) h FROM nivelesdeacceso n
        JOIN alb_usuarios u ON u.usuario = n.usuario AND u.esadmin = 0 GROUP BY n.usuario, n.formulario, n.control")->fetchAll(PDO::FETCH_ASSOC);
    $hab = [];
    foreach ($filas as $f) $hab[$f['usuario']][$f['formulario'] . '.' . $f['control']] = (int) $f['h'] === 1;
    foreach ($hab as $usuario => $controles) {
        $ids = MENU_PARA_TODOS;
        foreach (VB_A_MENU as $clave => $destinos) {
            [$form] = explode('.', $clave, 2);
            $seccion = VB_SECCION[$form] ?? null;
            if (empty($controles[$clave])) continue;
            if ($seccion && empty($controles['menuprincipal.' . $seccion])) continue;
            foreach ($destinos as $d) $ids[] = $d;
        }
        $todos = [];
        foreach (array_unique($ids) as $id) { $todos[] = $id; foreach ($padres[$id] as $p) $todos[] = $p; }
        $todos = array_values(array_unique($todos));
        $u = []; foreach ($todos as $id) if (isset($urls[$id])) $u[] = $urls[$id];
        $res[$usuario] = ['allowed_ids' => $todos, 'allowed_urls' => array_values(array_unique($u))];
    }
    return $res;
}

/** Graba los permisos traducidos en accesosusuarios (un registro vigente por usuario). Devuelve usuarios procesados. */
function migrarNivelesDeAcceso(PDO $pdo): array
{
    $out = [];
    foreach (traducirNivelesDeAcceso($pdo) as $usuario => $perm) {
        $id = (int) $pdo->query("SELECT id FROM alb_usuarios WHERE usuario = " . $pdo->quote($usuario))->fetchColumn();
        if (!$id) continue;
        $pdo->prepare("UPDATE accesosusuarios SET vigente = 0 WHERE usuarioid = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO accesosusuarios (usuarioid, usuarionombre, permisos, timestamp, usuariomodificacion, vigente) VALUES (?, ?, ?, NOW(), 'migracion', 1)")
            ->execute([$id, $usuario, json_encode($perm, JSON_UNESCAPED_SLASHES)]);
        $out[$usuario] = count($perm['allowed_urls']);
    }
    return $out;
}
