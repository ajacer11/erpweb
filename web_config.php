<?php
// SEGURIDAD CENTRALIZADA (Check Access) - módulo contratado aparte (addon web_catalogo)
require_once 'check_access.php';
?>
<style>
    .webcfg-thumb { width: 100%; max-height: 120px; object-fit: contain; background: repeating-conic-gradient(#ffffff10 0% 25%, transparent 0% 50%) 50% / 16px 16px; border-radius: 8px; }
    .webcfg-banner { border: 1px solid rgba(255,255,255,.12); border-radius: 10px; padding: 12px; margin-bottom: 12px; }
    .webcfg-banner img { width: 100%; height: 110px; object-fit: cover; border-radius: 8px; }
    .webcfg-color { width: 56px; height: 38px; padding: 2px; }
    .webcfg-preview { border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,.15); }
    .webcfg-preview .pv-head { padding: 12px 16px; color: #fff; font-weight: 700; display: flex; justify-content: space-between; align-items: center; }
    .webcfg-preview .pv-body { background: #f8fafc; padding: 16px; color: #0f172a; }
    .webcfg-preview .pv-btn { display: inline-block; padding: 8px 16px; border-radius: 10px; color: #fff; font-weight: 700; font-size: 13px; }
    .webcfg-destacados li { cursor: grab; }
    .webcfg-help { font-size: 12px; color: #94a3b8; }
</style>

<div class="container-fluid px-4 py-3 fade-in">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h4 class="text-white mb-0">CONFIGURACIÓN DE LA WEB</h4>
            <small class="text-accent opacity-75">Catálogo online y área de clientes</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a id="webcfgVerWeb" class="btn btn-outline-light rounded-pill px-3 d-none" target="_blank" rel="noopener">
                <i class="fas fa-up-right-from-square me-2"></i>VER MI WEB
            </a>
            <span id="webcfgSinDominio" class="badge bg-warning text-dark d-none">Sin dominio asignado todavía</span>
            <button class="btn btn-info fw-bold rounded-pill px-4" onclick="webcfgGuardar()">
                <i class="fas fa-save me-2"></i>GUARDAR CAMBIOS
            </button>
        </div>
    </div>

    <ul class="nav nav-tabs border-secondary mb-3" role="tablist">
        <li class="nav-item"><button class="nav-link active text-white" data-bs-toggle="tab" data-bs-target="#wc-sitio" type="button"><i class="fas fa-globe me-2 text-info"></i>Sitio</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-contacto" type="button"><i class="fas fa-address-card me-2 text-info"></i>Contacto</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-apariencia" type="button"><i class="fas fa-palette me-2 text-info"></i>Apariencia</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-portada" type="button"><i class="fas fa-wand-magic-sparkles me-2 text-info"></i>Portada y Experiencia</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-catalogo" type="button"><i class="fas fa-book-open me-2 text-info"></i>Catálogo</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-pedidos" type="button"><i class="fas fa-cart-shopping me-2 text-info"></i>Pedidos y Clientes</button></li>
        <li class="nav-item"><button class="nav-link text-white" data-bs-toggle="tab" data-bs-target="#wc-correo" type="button"><i class="fas fa-envelope me-2 text-info"></i>Correo</button></li>
    </ul>

    <div class="tab-content">
        <!-- ===================== SITIO ===================== -->
        <div class="tab-pane fade show active" id="wc-sitio">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label-custom">NOMBRE DEL SITIO</label>
                        <input type="text" class="form-control" data-k="sitio_nombre" maxlength="80" placeholder="Si queda vacío se usa la razón social">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">FRASE / SLOGAN</label>
                        <input type="text" class="form-control" data-k="sitio_slogan" maxlength="120" placeholder="Ej: Equipamiento mayorista de pesca y camping">
                    </div>
                    <div class="col-12">
                        <label class="form-label-custom">DESCRIPCIÓN PARA BUSCADORES</label>
                        <input type="text" class="form-control" data-k="sitio_descripcion" maxlength="200" placeholder="Lo que muestra Google y WhatsApp al compartir el link">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label-custom">LOGO</label>
                        <img class="webcfg-thumb mb-2 d-none" id="pv-logo" alt="">
                        <input type="file" class="form-control form-control-sm" accept="image/*" onchange="webcfgSubir(this, 'logo', 'logo')">
                        <div class="webcfg-help mt-1">PNG con fondo transparente, idealmente horizontal.</div>
                        <button type="button" class="btn btn-sm btn-link text-danger px-0" onclick="webcfgQuitar('logo')">Quitar logo</button>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">ÍCONO (FAVICON)</label>
                        <img class="webcfg-thumb mb-2 d-none" id="pv-favicon" alt="">
                        <input type="file" class="form-control form-control-sm" accept="image/*" onchange="webcfgSubir(this, 'favicon', 'favicon')">
                        <div class="webcfg-help mt-1">Cuadrado. Es el ícono de la pestaña y de la app en el celular.</div>
                        <button type="button" class="btn btn-sm btn-link text-danger px-0" onclick="webcfgQuitar('favicon')">Quitar ícono</button>
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label-custom mb-0">BANNERS DE LA PORTADA</label>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill" onclick="webcfgAgregarBanner()"><i class="fas fa-plus me-1"></i>Agregar banner</button>
                        </div>
                        <div class="webcfg-help mb-2">Imágenes anchas (ideal 1920 × 700). Si hay más de uno, rotan solos.</div>
                        <div id="webcfgBanners"></div>
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-md-8">
                        <label class="form-label-custom">QUIÉNES SOMOS: TÍTULO</label>
                        <input type="text" class="form-control mb-3" data-k="quienes_somos_titulo" maxlength="80">
                        <label class="form-label-custom">QUIÉNES SOMOS: TEXTO</label>
                        <textarea class="form-control" data-k="quienes_somos_texto" rows="9" placeholder="Contá la historia de la empresa, qué venden, a quién, por qué elegirlos... Dejá una línea en blanco para separar párrafos."></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">QUIÉNES SOMOS: IMAGEN</label>
                        <img class="webcfg-thumb mb-2 d-none" id="pv-quienes_somos_imagen" alt="">
                        <input type="file" class="form-control form-control-sm" accept="image/*" onchange="webcfgSubir(this, 'quienes', 'quienes_somos_imagen')">
                        <button type="button" class="btn btn-sm btn-link text-danger px-0" onclick="webcfgQuitar('quienes_somos_imagen')">Quitar imagen</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CONTACTO ===================== -->
        <div class="tab-pane fade" id="wc-contacto">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label-custom">WHATSAPP</label>
                        <input type="text" class="form-control" data-k="contacto_whatsapp" placeholder="5491112345678">
                        <div class="webcfg-help mt-1">Con código de país y área, sin 0 ni 15. Ej: 54 9 11 1234-5678 → 5491112345678</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">TELÉFONO</label>
                        <input type="text" class="form-control" data-k="contacto_telefono">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">MAIL DE CONTACTO</label>
                        <input type="email" class="form-control" data-k="contacto_email">
                        <div class="webcfg-help mt-1">Las respuestas a los mails de la web llegan acá.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">DIRECCIÓN</label>
                        <input type="text" class="form-control" data-k="contacto_direccion">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">LOCALIDAD / PROVINCIA</label>
                        <input type="text" class="form-control" data-k="contacto_localidad">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">HORARIOS DE ATENCIÓN</label>
                        <textarea class="form-control" data-k="contacto_horarios" rows="3" placeholder="Lunes a viernes de 8 a 17 hs&#10;Sábados de 9 a 13 hs"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">LINK DE GOOGLE MAPS</label>
                        <input type="url" class="form-control" data-k="contacto_mapa" placeholder="https://maps.app.goo.gl/...">
                    </div>
                    <div class="col-md-3"><label class="form-label-custom">INSTAGRAM</label><input type="url" class="form-control" data-k="red_instagram" placeholder="https://instagram.com/..."></div>
                    <div class="col-md-3"><label class="form-label-custom">FACEBOOK</label><input type="url" class="form-control" data-k="red_facebook" placeholder="https://facebook.com/..."></div>
                    <div class="col-md-3"><label class="form-label-custom">TIKTOK</label><input type="url" class="form-control" data-k="red_tiktok" placeholder="https://tiktok.com/@..."></div>
                    <div class="col-md-3"><label class="form-label-custom">YOUTUBE</label><input type="url" class="form-control" data-k="red_youtube" placeholder="https://youtube.com/..."></div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" data-k="whatsapp_flotante" id="wcWppFlot">
                            <label class="form-check-label text-white" for="wcWppFlot">Mostrar el botón flotante de WhatsApp en toda la web</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== APARIENCIA ===================== -->
        <div class="tab-pane fade" id="wc-apariencia">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-5">
                        <label class="form-label-custom">COLOR PRINCIPAL</label>
                        <div class="d-flex gap-2 mb-1"><input type="color" class="form-control form-control-color webcfg-color" data-k="color_primario"><input type="text" class="form-control" data-mirror="color_primario" maxlength="7"></div>
                        <div class="webcfg-help mb-3">Botones, precios y links.</div>
                        <label class="form-label-custom">COLOR DE ACENTO</label>
                        <div class="d-flex gap-2 mb-1"><input type="color" class="form-control form-control-color webcfg-color" data-k="color_secundario"><input type="text" class="form-control" data-mirror="color_secundario" maxlength="7"></div>
                        <div class="webcfg-help mb-3">Ofertas, novedades y botones destacados.</div>
                        <label class="form-label-custom">COLOR DEL ENCABEZADO</label>
                        <div class="d-flex gap-2 mb-1"><input type="color" class="form-control form-control-color webcfg-color" data-k="color_encabezado"><input type="text" class="form-control" data-mirror="color_encabezado" maxlength="7"></div>
                        <div class="webcfg-help">Barra superior y pie de página.</div>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label-custom">VISTA PREVIA</label>
                        <div class="webcfg-preview">
                            <div class="pv-head" id="pvHead"><span id="pvNombre">Mi empresa</span><span><i class="fas fa-cart-shopping"></i></span></div>
                            <div class="pv-body">
                                <div class="fw-bold mb-1">Producto de ejemplo</div>
                                <div class="fw-bold fs-5 mb-2" id="pvPrecio">$ 12.345,00</div>
                                <span class="pv-btn" id="pvBtn">Agregar al pedido</span>
                                <span class="pv-btn ms-2" id="pvBtn2">NOVEDAD</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== PORTADA Y EXPERIENCIA ===================== -->
        <div class="tab-pane fade" id="wc-portada">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-4">
                        <label class="form-label-custom">ANIMACIONES</label>
                        <select class="form-select" data-k="animaciones">
                            <option value="completas">Completas (íconos animados, olas, efectos)</option>
                            <option value="suaves">Suaves (solo al pasar el mouse / tocar)</option>
                            <option value="ninguna">Sin animaciones</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">ESTILO DEL ENCABEZADO</label>
                        <select class="form-select" data-k="encabezado_estilo">
                            <option value="claro">Claro (fondo blanco, logo a color)</option>
                            <option value="oscuro">Oscuro (color de encabezado)</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label-custom">BARRA DE ANUNCIO (ARRIBA DE TODO)</label>
                        <input type="text" class="form-control" data-k="anuncio_texto" maxlength="120" placeholder="Ej: Envíos a todo el país · Solo mayoristas">
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-md-6">
                        <label class="form-label-custom">BANNER PRINCIPAL</label>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-k="hero_buscador" id="wcHeroBusc"><label class="form-check-label text-white" for="wcHeroBusc">Buscador grande sobre el banner</label></div>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-k="hero_olas" id="wcHeroOlas"><label class="form-check-label text-white" for="wcHeroOlas">Olas animadas al pie del banner</label></div>
                        <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" data-k="mostrar_contadores" id="wcContad"><label class="form-check-label text-white" for="wcContad">Contadores animados (productos, rubros, novedades)</label></div>
                        <label class="form-label-custom">PALABRAS QUE ROTAN EN EL TÍTULO</label>
                        <input type="text" class="form-control" data-k="hero_palabras" maxlength="300" placeholder="cañas, reels, señuelos, camping">
                        <div class="webcfg-help mt-1">Separadas por coma. Si queda vacío se usan los rubros con más productos.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">EXPERIENCIA DE COMPRA</label>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-k="carrito_sin_login" id="wcCarrSL"><label class="form-check-label text-white" for="wcCarrSL">Armar el pedido sin ingresar</label></div>
                        <div class="webcfg-help mb-3">Cualquiera agrega productos al pedido; para enviarlo se pide ingresar (y se recalculan los precios de su lista).</div>
                        <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" data-k="vista_rapida" id="wcVistaR"><label class="form-check-label text-white" for="wcVistaR">Vista rápida desde el catálogo</label></div>
                        <div class="webcfg-help">Ver fotos, características y agregar al pedido sin salir del listado.</div>
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-md-5">
                        <label class="form-label-custom">SECCIONES DE LA PORTADA (Y SU ORDEN)</label>
                        <ul class="list-group list-group-flush" id="wcSecciones"></ul>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label-custom">BENEFICIOS (HASTA 4)</label>
                        <div id="wcBeneficios"></div>
                        <div class="webcfg-help">Tarjetas debajo del banner. Ej: "Envíos a todo el país", "Financiación", "Solo mayoristas".</div>
                    </div>

                    <div class="col-12"><hr class="border-secondary opacity-25"></div>

                    <div class="col-12">
                        <label class="form-label-custom">ÍCONO DE CADA FAMILIA Y RUBRO</label>
                        <div class="webcfg-help mb-2">Las familias son las tarjetas de la portada y del catálogo; los rubros se ven en la franja en movimiento. Por defecto el ícono se elige solo según el nombre. Cambialo si alguno no queda bien.</div>
                        <div class="row g-2" id="wcRubrosIconos"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CATÁLOGO ===================== -->
        <div class="tab-pane fade" id="wc-catalogo">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="catalogo_publico" id="wcCatPub">
                            <label class="form-check-label text-white fw-bold" for="wcCatPub">Catálogo visible sin iniciar sesión</label>
                        </div>
                        <div class="webcfg-help mb-3">Si lo apagás, solo los clientes con acceso ven los productos.</div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="precios_publicos" id="wcPrecPub">
                            <label class="form-check-label text-white fw-bold" for="wcPrecPub">Mostrar precios al público</label>
                        </div>
                        <div class="webcfg-help mb-3">Los clientes logueados siempre ven el precio de su propia lista.</div>
                        <label class="form-label-custom">LISTA DE PRECIOS PARA EL PÚBLICO</label>
                        <select class="form-select" data-k="lista_publica" id="wcListaPub"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">PRECIOS</label>
                        <select class="form-select mb-3" data-k="precio_mostrar_iva">
                            <option value="1">Precio final (con IVA)</option>
                            <option value="0">Precio neto + IVA</option>
                        </select>
                        <label class="form-label-custom">STOCK</label>
                        <select class="form-select mb-3" data-k="mostrar_stock">
                            <option value="ninguno">No mostrar stock</option>
                            <option value="disponibilidad">Mostrar "Disponible / Sin stock"</option>
                            <option value="cantidad">Mostrar la cantidad disponible</option>
                        </select>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label-custom">ORDEN DEL CATÁLOGO</label>
                                <select class="form-select" data-k="catalogo_orden">
                                    <option value="familia">Por familia y descripción</option>
                                    <option value="descripcion">Por descripción</option>
                                    <option value="codigo">Por código</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label-custom">NOVEDADES</label>
                                <div class="input-group"><span class="input-group-text">Últimos</span><input type="number" min="0" max="200" class="form-control" data-k="novedades_cantidad"><span class="input-group-text">productos</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="alert alert-info py-2 small mb-0">
                            <i class="fas fa-circle-info me-2"></i>Qué productos aparecen se define en cada producto (pestaña <b>Web</b>): "Mostrar en catálogo" y "Mostrar sin stock".
                            Ahí también se cargan las fotos, la descripción larga y las características.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label-custom">RUBROS OCULTOS EN LA WEB</label>
                        <div id="wcRubros" class="border border-secondary rounded p-3" style="max-height: 260px; overflow-y: auto;"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom">PRODUCTOS DESTACADOS EN LA PORTADA</label>
                        <select id="wcDestBuscar" class="form-select" style="width:100%"></select>
                        <ul id="wcDestacados" class="list-group list-group-flush webcfg-destacados mt-2"></ul>
                        <div class="webcfg-help mt-1">Arrastrá para ordenar. Si no elegís ninguno, la portada muestra las novedades.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== PEDIDOS Y CLIENTES ===================== -->
        <div class="tab-pane fade" id="wc-pedidos">
            <div class="card card-filtros p-4 shadow-sm">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" data-k="pedidos_habilitados" id="wcPedHab">
                            <label class="form-check-label text-white fw-bold" for="wcPedHab">Los clientes pueden enviar pedidos desde la web</label>
                        </div>
                        <label class="form-label-custom">MONTO MÍNIMO DEL PEDIDO</label>
                        <div class="input-group mb-1"><span class="input-group-text">$</span><input type="number" min="0" step="0.01" class="form-control" data-k="pedido_monto_minimo"></div>
                        <div class="webcfg-help mb-3">0 = sin mínimo. Se compara contra el total con IVA.</div>
                        <label class="form-label-custom">AVISAR LOS PEDIDOS NUEVOS A</label>
                        <input type="text" class="form-control mb-1" data-k="pedido_mail_aviso" placeholder="ventas@empresa.com, deposito@empresa.com">
                        <div class="webcfg-help mb-3">Uno o más mails separados por coma.</div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="pedido_avisar_vendedor" id="wcAvVend">
                            <label class="form-check-label text-white" for="wcAvVend">Avisar también al vendedor del cliente (si tiene mail)</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="pedido_confirmar_cliente" id="wcConfCli">
                            <label class="form-check-label text-white" for="wcConfCli">Mandarle al cliente un mail con el detalle de su pedido</label>
                        </div>
                        <label class="form-label-custom mt-2">MENSAJE AL CONFIRMAR EL PEDIDO</label>
                        <textarea class="form-control" data-k="pedido_texto_confirmacion" rows="3"></textarea>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="registro_habilitado" id="wcReg">
                            <label class="form-check-label text-white fw-bold" for="wcReg">Formulario "Quiero ser cliente"</label>
                        </div>
                        <div class="webcfg-help mb-3">Las solicitudes llegan a <b>Accesos de Clientes</b> para aprobarlas o rechazarlas.</div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" data-k="avisos_pago_habilitados" id="wcAvPago">
                            <label class="form-check-label text-white fw-bold" for="wcAvPago">Los clientes pueden informar pagos</label>
                        </div>
                        <div class="webcfg-help mb-3">Suben el comprobante de la transferencia y queda en <b>Pagos Informados</b> para imputarlo.</div>
                        <label class="form-label-custom">DATOS PARA TRANSFERENCIAS</label>
                        <textarea class="form-control" data-k="datos_pago" rows="5" placeholder="Banco ...&#10;CBU: ...&#10;Alias: ...&#10;Titular: ..."></textarea>
                        <div class="webcfg-help mt-1">Se muestran en el área de clientes, junto al saldo.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===================== CORREO ===================== -->
        <div class="tab-pane fade" id="wc-correo">
            <div class="card card-filtros p-4 shadow-sm">
                <p class="text-white-50">
                    Por defecto, los mails de la web (invitaciones, recuperar contraseña, avisos de pedidos) salen de una casilla de WEST IT
                    con el nombre de tu empresa, y las respuestas llegan a tu mail de contacto. Si preferís que salgan de tu propia casilla, cargala acá.
                </p>
                <div class="alert alert-warning py-2 small d-none" id="wcMailSistemaNo"><i class="fas fa-triangle-exclamation me-2"></i>El correo del sistema todavía no está configurado en el servidor: hasta que lo esté, solo funciona tu propia casilla.</div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" data-k="smtp_activo" id="wcSmtpAct">
                    <label class="form-check-label text-white fw-bold" for="wcSmtpAct">Usar mi propia casilla de correo</label>
                </div>
                <div class="row g-3" id="wcSmtpCampos">
                    <div class="col-md-6"><label class="form-label-custom">SERVIDOR SMTP</label><input type="text" class="form-control" data-k="smtp_host" placeholder="mail.miempresa.com.ar"></div>
                    <div class="col-md-3"><label class="form-label-custom">PUERTO</label><input type="number" class="form-control" data-k="smtp_puerto"></div>
                    <div class="col-md-3"><label class="form-label-custom">SEGURIDAD</label>
                        <select class="form-select" data-k="smtp_seguridad"><option value="tls">STARTTLS (587)</option><option value="ssl">SSL/TLS (465)</option><option value="none">Ninguna</option></select>
                    </div>
                    <div class="col-md-6"><label class="form-label-custom">USUARIO</label><input type="text" class="form-control" data-k="smtp_usuario" autocomplete="off"></div>
                    <div class="col-md-6"><label class="form-label-custom">CONTRASEÑA</label><input type="password" class="form-control" data-k="smtp_clave" autocomplete="new-password" id="wcSmtpClave"></div>
                    <div class="col-md-6"><label class="form-label-custom">MAIL REMITENTE</label><input type="email" class="form-control" data-k="smtp_remitente" placeholder="Si queda vacío, el usuario"></div>
                    <div class="col-md-6"><label class="form-label-custom">NOMBRE DEL REMITENTE</label><input type="text" class="form-control" data-k="smtp_remitente_nombre" placeholder="Si queda vacío, el nombre del sitio"></div>
                </div>
                <hr class="border-secondary opacity-25">
                <label class="form-label-custom">PROBAR EL CORREO</label>
                <div class="input-group" style="max-width: 480px;">
                    <input type="email" class="form-control" id="wcMailPrueba" placeholder="tu@mail.com">
                    <button class="btn btn-outline-info" type="button" onclick="webcfgProbarMail()"><i class="fas fa-paper-plane me-1"></i>Enviar prueba</button>
                </div>
                <div class="webcfg-help mt-1">Guardá los cambios antes de probar.</div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const API = 'api_web_config.php';
    const H = {'X-Requested-With': 'XMLHttpRequest'};
    let cfg = {};
    let banners = [];
    let destacados = [];
    let secciones = [];
    let beneficios = [];
    let rubrosIconos = {};
    let rubrosLista = [];
    let familiasLista = [];
    const SECCIONES = {beneficios: 'Beneficios', rubros: 'Familias con íconos', destacados: 'Productos destacados', novedades: 'Novedades',
                       franja: 'Franja en movimiento', nosotros: 'Quiénes somos', contacto: 'Contacto rápido'};
    const ICONOS_RUBRO = {auto: 'Automático', cana: 'Caña', reel: 'Reel', anzuelo: 'Anzuelo', senuelo: 'Señuelo', linea: 'Línea / hilo', boya: 'Boya',
        carpa: 'Carpa / camping', linterna: 'Linterna', cuchillo: 'Cuchillo', mira: 'Mira / armas', ropa: 'Ropa', dormir: 'Descanso',
        frio: 'Frío / conservadora', silla: 'Silla', mochila: 'Mochila / bolso', balanza: 'Balanza', fuego: 'Fuego / gas', red: 'Red',
        gota: 'Líquidos / carnada', bateria: 'Batería', brujula: 'Brújula / aire libre', herramienta: 'Herramientas', pez: 'Pez', caja: 'Caja (genérico)'};
    const ICONOS_BENEF = {truck: 'Camión', wallet: 'Billetera', shield: 'Escudo', star: 'Estrella', percent: 'Porcentaje', clock: 'Reloj',
        phone: 'Teléfono', box: 'Caja', check: 'Tilde', sparkles: 'Destello', user: 'Persona'};

    function renderSecciones() {
        const todas = [...secciones, ...Object.keys(SECCIONES).filter(k => !secciones.includes(k))];
        $('#wcSecciones').html(todas.map(k => {
            const on = secciones.includes(k), i = secciones.indexOf(k);
            return `<li class="list-group-item bg-transparent text-white border-secondary d-flex align-items-center gap-2 py-1">
                <input class="form-check-input m-0" type="checkbox" ${on ? 'checked' : ''} onchange="webcfgSeccion('${k}', this.checked)">
                <span class="flex-fill ${on ? '' : 'text-muted'}">${SECCIONES[k]}</span>
                ${on ? `<button type="button" class="btn btn-sm btn-link text-info p-0" ${i === 0 ? 'disabled' : ''} onclick="webcfgSeccionMover('${k}', -1)"><i class="fas fa-arrow-up"></i></button>
                <button type="button" class="btn btn-sm btn-link text-info p-0" ${i === secciones.length - 1 ? 'disabled' : ''} onclick="webcfgSeccionMover('${k}', 1)"><i class="fas fa-arrow-down"></i></button>` : ''}
            </li>`;
        }).join(''));
    }
    window.webcfgSeccion = (k, on) => { secciones = on ? [...secciones, k] : secciones.filter(x => x !== k); renderSecciones(); };
    window.webcfgSeccionMover = (k, d) => { const i = secciones.indexOf(k), j = i + d; [secciones[i], secciones[j]] = [secciones[j], secciones[i]]; renderSecciones(); };

    function renderBeneficios() {
        while (beneficios.length < 4) beneficios.push({icono: 'check', titulo: '', texto: ''});
        $('#wcBeneficios').html(beneficios.map((b, i) => `
            <div class="row g-2 mb-2">
                <div class="col-3"><select class="form-select form-select-sm" onchange="webcfgBenef(${i}, 'icono', this.value)">${Object.entries(ICONOS_BENEF).map(([k, t]) => `<option value="${k}" ${b.icono === k ? 'selected' : ''}>${t}</option>`).join('')}</select></div>
                <div class="col-4"><input class="form-control form-control-sm" maxlength="60" placeholder="Título" value="${esc(b.titulo)}" onchange="webcfgBenef(${i}, 'titulo', this.value)"></div>
                <div class="col-5"><input class="form-control form-control-sm" maxlength="140" placeholder="Texto corto (opcional)" value="${esc(b.texto)}" onchange="webcfgBenef(${i}, 'texto', this.value)"></div>
            </div>`).join(''));
    }
    window.webcfgBenef = (i, k, v) => { beneficios[i][k] = v; };

    function renderRubrosIconos() {
        const fila = (r, i) => `
            <div class="col-md-4 col-lg-3"><label class="small text-white-50 mb-1 text-truncate d-block" title="${esc(r)}">${esc(r)}</label>
                <select class="form-select form-select-sm wc-rubro-icono" data-i="${i}">${Object.entries(ICONOS_RUBRO).map(([k, t]) => `<option value="${k}" ${(rubrosIconos[r] || 'auto') === k ? 'selected' : ''}>${t}</option>`).join('')}</select></div>`;
        const titulo = (t) => `<div class="col-12 small fw-bold text-info mt-2">${t}</div>`;
        let html = '';
        if (familiasLista.length) html += titulo('Familias') + familiasLista.map((f, i) => fila(f, i)).join('');
        if (rubrosLista.length) html += titulo('Rubros') + rubrosLista.map((r, i) => fila(r, familiasLista.length + i)).join('');
        $('#wcRubrosIconos').html(html || '<div class="col-12 text-muted small">Sin familias ni rubros.</div>');
    }

    const imgUrl = (f) => f ? `${API}?action=imagen&f=${encodeURIComponent(f)}` : '';
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    async function init() {
        try {
            const r = await (await fetch(`${API}?action=get_config`, {headers: H})).json();
            if (!r.success) throw new Error(r.error);
            cfg = r.data;

            const $lista = $('#wcListaPub').empty();
            r.listas.forEach(l => $lista.append(`<option value="${l.id}">${l.id} - ${esc(l.descripcion)}</option>`));

            const ocultos = JSON.parse(cfg.rubros_ocultos || '[]');
            $('#wcRubros').html(r.rubros.length ? r.rubros.map((rb, i) => `
                <div class="form-check"><input class="form-check-input wc-rubro" type="checkbox" id="wcr${i}" value="${esc(rb)}" ${ocultos.includes(rb) ? 'checked' : ''}>
                <label class="form-check-label text-white" for="wcr${i}">${esc(rb)}</label></div>`).join('') : '<span class="text-muted">Sin rubros cargados</span>');

            $('[data-k]').each(function() {
                const k = this.dataset.k;
                if (this.type === 'checkbox') this.checked = cfg[k] === '1';
                else if (this.type !== 'file') $(this).val(cfg[k] ?? '');
            });
            $('[data-mirror]').each(function() { $(this).val(cfg[this.dataset.mirror]); });
            $('#wcSmtpClave').attr('placeholder', cfg.smtp_clave_cargada === '1' ? '•••••••• (cargada; escribí para cambiarla)' : '');

            ['logo', 'favicon', 'quienes_somos_imagen'].forEach(k => mostrarPreview(k));
            banners = JSON.parse(cfg.banners || '[]');
            renderBanners();
            try { secciones = JSON.parse(cfg.portada_secciones || '[]'); } catch (e) { secciones = []; }
            try { beneficios = JSON.parse(cfg.beneficios || '[]'); } catch (e) { beneficios = []; }
            try { rubrosIconos = JSON.parse(cfg.rubros_iconos || '{}') || {}; } catch (e) { rubrosIconos = {}; }
            rubrosLista = r.rubros;
            familiasLista = r.familias || [];
            renderSecciones(); renderBeneficios(); renderRubrosIconos();
            destacados = r.destacados.map(d => ({codigo: d.codigo, descripcion: d.descripcion || '(producto inexistente)'}));
            renderDestacados();

            if (r.url_web) $('#webcfgVerWeb').attr('href', r.url_web).removeClass('d-none');
            else $('#webcfgSinDominio').removeClass('d-none');
            $('#wcMailSistemaNo').toggleClass('d-none', !!r.mail_sistema_ok);
            toggleSmtp();
            actualizarPreview();
        } catch (e) {
            console.error(e);
            Swal.fire('Error', 'No se pudo cargar la configuración: ' + e.message, 'error');
        }
    }

    function initBuscadorDestacados() {
        $('#wcDestBuscar').select2({
            placeholder: 'Buscar producto para destacar...',
            ajax: { url: `${API}?action=buscar_productos`, headers: H, delay: 250, data: p => ({q: p.term || ''}), processResults: d => d },
            minimumInputLength: 1, width: '100%'
        }).on('select2:select', function(e) {
            const d = e.params.data;
            if (!destacados.some(x => x.codigo === d.id)) destacados.push({codigo: d.id, descripcion: d.text});
            $(this).val(null).trigger('change');
            renderDestacados();
        });
    }

    function mostrarPreview(k) {
        const $img = $('#pv-' + k);
        if (cfg[k]) $img.attr('src', imgUrl(cfg[k])).removeClass('d-none');
        else $img.addClass('d-none').removeAttr('src');
    }

    async function subirArchivo(file, tipo) {
        const fd = new FormData();
        fd.append('tipo', tipo);
        fd.append('imagen', file);
        const r = await (await fetch(`${API}?action=upload_imagen`, {method: 'POST', headers: H, body: fd})).json();
        if (!r.success) throw new Error(r.error);
        return r.archivo;
    }

    window.webcfgSubir = async function(input, tipo, clave) {
        if (!input.files[0]) return;
        try {
            cfg[clave] = await subirArchivo(input.files[0], tipo);
            mostrarPreview(clave);
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
        input.value = '';
    };
    window.webcfgQuitar = function(clave) { cfg[clave] = ''; mostrarPreview(clave); };

    function renderBanners() {
        $('#webcfgBanners').html(banners.length ? banners.map((b, i) => `
            <div class="webcfg-banner row g-2 align-items-center">
                <div class="col-md-3">${b.imagen ? `<img src="${imgUrl(b.imagen)}" alt="">` : '<div class="text-muted small">Sin imagen</div>'}
                    <input type="file" class="form-control form-control-sm mt-1" accept="image/*" onchange="webcfgBannerImg(this, ${i})"></div>
                <div class="col-md-8 row g-2">
                    <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Título (opcional)" value="${esc(b.titulo)}" onchange="webcfgBannerSet(${i}, 'titulo', this.value)"></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Subtítulo (opcional)" value="${esc(b.subtitulo)}" onchange="webcfgBannerSet(${i}, 'subtitulo', this.value)"></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Texto del botón (opcional)" value="${esc(b.boton_texto)}" onchange="webcfgBannerSet(${i}, 'boton_texto', this.value)"></div>
                    <div class="col-md-6"><input class="form-control form-control-sm" placeholder="Link del botón: /catalogo, /rubro/..., https://..." value="${esc(b.boton_link)}" onchange="webcfgBannerSet(${i}, 'boton_link', this.value)"></div>
                </div>
                <div class="col-md-1 text-end d-flex flex-md-column gap-1 justify-content-end">
                    <button class="btn btn-sm btn-outline-light" ${i === 0 ? 'disabled' : ''} onclick="webcfgBannerMover(${i}, -1)" title="Subir"><i class="fas fa-arrow-up"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="webcfgBannerQuitar(${i})" title="Quitar"><i class="fas fa-trash"></i></button>
                </div>
            </div>`).join('') : '<div class="text-muted small">Sin banners: la portada muestra el nombre y el slogan sobre el color principal.</div>');
    }
    window.webcfgAgregarBanner = () => { banners.push({imagen: '', titulo: '', subtitulo: '', boton_texto: '', boton_link: ''}); renderBanners(); };
    window.webcfgBannerSet = (i, k, v) => { banners[i][k] = v; };
    window.webcfgBannerQuitar = (i) => { banners.splice(i, 1); renderBanners(); };
    window.webcfgBannerMover = (i, d) => { const [b] = banners.splice(i, 1); banners.splice(i + d, 0, b); renderBanners(); };
    window.webcfgBannerImg = async function(input, i) {
        if (!input.files[0]) return;
        try { banners[i].imagen = await subirArchivo(input.files[0], 'banner'); renderBanners(); }
        catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    function renderDestacados() {
        $('#wcDestacados').html(destacados.map((d, i) => `
            <li class="list-group-item bg-transparent text-white border-secondary d-flex justify-content-between align-items-center" draggable="true" data-i="${i}">
                <span><i class="fas fa-grip-vertical me-2 text-muted"></i>${esc(d.descripcion)}</span>
                <button class="btn btn-sm btn-link text-danger" onclick="webcfgDestQuitar(${i})"><i class="fas fa-xmark"></i></button>
            </li>`).join(''));
        let desde = null;
        $('#wcDestacados li').on('dragstart', function() { desde = +this.dataset.i; })
            .on('dragover', e => e.preventDefault())
            .on('drop', function(e) {
                e.preventDefault();
                const hasta = +this.dataset.i;
                if (desde === null || desde === hasta) return;
                const [d] = destacados.splice(desde, 1);
                destacados.splice(hasta, 0, d);
                renderDestacados();
            });
    }
    window.webcfgDestQuitar = (i) => { destacados.splice(i, 1); renderDestacados(); };

    function toggleSmtp() { $('#wcSmtpCampos').toggle($('#wcSmtpAct').is(':checked')); }
    $('#wcSmtpAct').on('change', toggleSmtp);

    function actualizarPreview() {
        const p = $('[data-k=color_primario]').val(), s = $('[data-k=color_secundario]').val(), h = $('[data-k=color_encabezado]').val();
        $('#pvHead').css('background', h);
        $('#pvBtn').css('background', p);
        $('#pvPrecio').css('color', p);
        $('#pvBtn2').css('background', s);
        $('#pvNombre').text($('[data-k=sitio_nombre]').val() || 'Mi empresa');
    }
    $('input[type=color][data-k]').on('input', function() { $(`[data-mirror=${this.dataset.k}]`).val(this.value); actualizarPreview(); });
    $('[data-mirror]').on('change', function() {
        if (/^#[0-9a-fA-F]{6}$/.test(this.value)) { $(`[data-k=${this.dataset.mirror}]`).val(this.value); actualizarPreview(); }
    });
    $('[data-k=sitio_nombre]').on('input', actualizarPreview);

    window.webcfgGuardar = async function() {
        const datos = {};
        $('[data-k]').each(function() {
            if (this.type === 'file') return;
            datos[this.dataset.k] = this.type === 'checkbox' ? (this.checked ? '1' : '0') : $(this).val();
        });
        ['logo', 'favicon', 'quienes_somos_imagen'].forEach(k => datos[k] = cfg[k] || '');
        datos.banners = banners.filter(b => b.imagen);
        datos.portada_secciones = secciones;
        datos.beneficios = beneficios.filter(b => b.titulo.trim());
        datos.rubros_iconos = {};
        const nombresIconos = [...familiasLista, ...rubrosLista];
        $('.wc-rubro-icono').each(function() { if (this.value !== 'auto') datos.rubros_iconos[nombresIconos[+this.dataset.i]] = this.value; });
        datos.rubros_ocultos = $('.wc-rubro:checked').map(function() { return this.value; }).get();
        if (banners.some(b => !b.imagen)) {
            const c = await Swal.fire({icon: 'warning', title: 'Hay banners sin imagen', text: 'Se van a descartar. ¿Seguimos?', showCancelButton: true, confirmButtonText: 'Sí, guardar'});
            if (!c.isConfirmed) return;
        }
        try {
            const r = await (await fetch(`${API}?action=save_config`, {method: 'POST', headers: {...H, 'Content-Type': 'application/json'}, body: JSON.stringify(datos)})).json();
            if (!r.success) throw new Error(r.error);
            const r2 = await (await fetch(`${API}?action=save_destacados`, {method: 'POST', headers: {...H, 'Content-Type': 'application/json'}, body: JSON.stringify({codigos: destacados.map(d => d.codigo)})})).json();
            if (!r2.success) throw new Error(r2.error);
            Swal.fire({icon: 'success', title: 'Guardado', text: r.message, timer: 1400, showConfirmButton: false});
            $('#wcSmtpClave').val('');
            init();
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    window.webcfgProbarMail = async function() {
        const fd = new FormData();
        fd.append('para', $('#wcMailPrueba').val());
        try {
            Swal.fire({title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading()});
            const r = await (await fetch(`${API}?action=probar_mail`, {method: 'POST', headers: H, body: fd})).json();
            if (!r.success) throw new Error(r.error);
            Swal.fire('Listo', r.message, 'success');
        } catch (e) { Swal.fire('Error', e.message, 'error'); }
    };

    initBuscadorDestacados();
    init();
})();
</script>
