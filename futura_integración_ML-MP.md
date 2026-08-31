# Integración Mercado Libre + Mercado Pago (diseño, sin implementar)

Estado: **diseño cerrado, sin código escrito**. Este documento es la referencia para cuando se decida arrancar el desarrollo.

Se planea ofrecer como módulo adicional (no incluido por defecto en ninguna licencia) — ver el mecanismo de habilitación en `futura_arquitectura_modulos_adicionales.md`.

## Alcance pedido

- **Mercado Libre**: actualizar stock, actualizar precios, generar y enviar facturas de venta.
- **Mercado Pago**: cobros con QR.

## Estado actual del sistema (relevado antes de diseñar)

- `menu2.json` tiene una entrada `mercadolibre_import.php` que **no existe como archivo** — es un ítem de menú muerto, sin ninguna integración real detrás.
- No hay ningún rastro de integración con Mercado Pago en el código.
- Es una integración **desde cero**, no hay nada parcialmente construido para reutilizar.

## Decisión de alcance: multi-tenant real

El sistema es multi-tenant (una base por empresa, seleccionada por CUIT en el login). La integración se diseña para que **cada empresa cliente conecte su propia cuenta** de ML y de MP — no es una integración centralizada de WEST IT. Esto implica:

- Las tablas de tokens OAuth viven en la **base de cada tenant**, no en la base maestra (`ClientesSistema`).
- La pantalla de conexión vive en Configuración de cada empresa, no en el backoffice.

## 1. Conexión OAuth (prerrequisito de todo lo demás)

- Tablas nuevas por tenant: `integraciones_ml` (access_token, refresh_token, user_id_ml, vencimiento) e `integraciones_mp` (access_token, user_id_mp, public_key, vencimiento).
- Tokens **cifrados en la base** — mismo nivel de cuidado que el certificado digital de ARCA (no se guardan en texto plano).
- Pantalla nueva: **Configuración > Integraciones**, con botones "Conectar Mercado Libre" / "Conectar Mercado Pago" que disparan el flujo OAuth de cada plataforma y vuelven a un callback propio que guarda los tokens.
- Refresh automático del access_token antes de que venza (usando el refresh_token).
- Ya existen credenciales de developer (client_id/client_secret) creadas en ambas plataformas.
- **Además de guardar los tokens en la base del tenant**, el callback OAuth guarda un registro en la base **maestra** (`ClientesSistema`), en una tabla nueva `integraciones_externas_index` (plataforma, external_user_id, cuit) — el `external_user_id` es el ID de cuenta que devuelve ML o MP al conectar (no el CUIT). Este índice es lo que permite resolver los webhooks (ver punto 1.5).

## 1.5. Enrutamiento de webhooks a la base del tenant correcto

Los webhooks de ML y MP le pegan a la URL pública **sin sesión** (son POST servidor-a-servidor, no hay login de por medio), así que no se puede usar el mecanismo normal de `$_SESSION['tenant_cuit']` para saber a qué base conectarse. Tampoco conviene un archivo de webhook por cliente (ej. `webhooks/20272869554.php`): cualquier cambio de lógica futura obligaría a actualizar N archivos a la vez, cada cliente nuevo requeriría desplegar y registrar una URL pública nueva, y probablemente ni ML ni MP permitan registrar más de una URL de callback por aplicación.

En cambio, hay **un solo archivo por plataforma** (`webhook_ml.php`, `webhook_mp.php`) que atiende a todos los tenants:

1. Valida la firma de la notificación (ver Seguridad) antes de tocar cualquier base — así una request espuria no gasta ni una consulta.
2. Lee el `user_id`/`collector_id` que manda ML o MP en el payload (es su ID de cuenta, no el CUIT del tenant).
3. Busca ese ID en `integraciones_externas_index` (base maestra) → obtiene el CUIT del tenant dueño de esa cuenta.
4. Con el CUIT resuelto, llama a las mismas funciones que ya existen en `db_master.php` (`getTenantDatabaseConfig`, `parseConnectionString`) para armar la conexión a la base de ese tenant específico — las mismas que arma el login normal, solo que acá el CUIT sale del índice en vez de `$_SESSION`.
5. Ya conectado a la base correcta, procesa el evento: marca stock pendiente de sync, guarda el pedido entrante, confirma el pago QR, etc.

Con este esquema, cualquier cambio a la lógica de los webhooks se toca en un solo archivo por plataforma, para siempre — nunca hay N archivos que mantener sincronizados.

## 2. Sincronización de stock y precio hacia ML

### Vínculo producto ↔ publicación
- Tabla `ml_publicaciones`: vínculo **manual** entre `alb_productos.codigo` y el `item_id` (y `variation_id` si aplica) de la publicación en ML. No se intenta automatizar el matching — emparejar mal es peor que no sincronizar.

### Qué precio se sincroniza
- **Lista 8** (de las 8 listas de precio existentes) es la que se empuja a Mercado Libre, sin excepción.

### Mecanismo de sincronización (dos capas)

**Capa 1 — intento inmediato, no bloqueante:**
Cuando cambia el stock o el precio de Lista 8 de un producto vinculado (venta, ajuste, transferencia entre depósitos, producción, ingreso de stock — son ~8 puntos distintos del código que tocan stock), se marca el producto como "pendiente de sync" (una bandera simple, escritura rápida que nunca falla ni bloquea la operación). Inmediatamente después, usando `fastcgi_finish_request()` (disponible porque el sitio corre sobre PHP-FPM), la respuesta ya se le devuelve al usuario y **recién después**, en segundo plano dentro del mismo proceso, se intenta el POST/PUT a la API de ML. Si ese intento falla (ML caído, timeout, token vencido), no rompe nada — la bandera de pendiente sigue activa.

**Capa 2 — cron cada 1 minuto (red de seguridad):**
Un cron job (`* * * * *` en CWP, ejecutado vía PHP CLI, no por URL pública) recorre **todos los tenants** que tengan `integraciones_ml` activa y token vigente, y por cada uno barre los productos que sigan marcados como "pendiente de sync" (los que la capa 1 no pudo confirmar) y reintenta el push. Un solo cron cubre a todas las empresas, no uno por cliente.

No hay colisión entre una venta en curso y el cron corriendo al mismo tiempo: la venta solo escribe una bandera y sigue; el cron solo lee/procesa esa tabla de banderas. En el peor caso, un cambio ocurrido en el mismo instante que corre el cron se procesa en el ciclo siguiente (1 minuto después), no genera error ni bloqueo.

### Panel de estado
Pantalla de "Estado de sincronización ML" para ver qué falló (token vencido, publicación pausada, error de API) sin tener que ir al log crudo del cron.

## 3. Pedidos de ML → cola de revisión (no facturación 100% automática)

Decisión: **automática con cola de revisión**, no automática sin intervención y no manual puro.

- ML notifica pedidos nuevos vía webhook (`topic=orders_v2`) al `webhook_ml.php` único (ver 1.5 para cómo resuelve a qué tenant pertenece). Acá sí es webhook real: es ML avisándonos a nosotros, en tiempo real, sin cron de por medio.
- Al recibir la notificación se guarda el pedido en una tabla nueva `ml_pedidos_pendientes` (comprador, ítems, importe, envío) — **sin facturar todavía**.
- Pantalla nueva **"Pedidos de Mercado Libre"**: lista la cola con estado (Pendiente / Facturado / Rechazado) y un botón **"Revisar y Facturar"** que abre `newfactura.php` **precargado** con los datos del pedido, para que el operador confirme/ajuste antes de emitir.
- Si ML no provee CUIT del comprador, se factura como Consumidor Final — ya queda cubierto por la validación de tope de Consumidor Final sin identificar que se implementó el 2026-08-31 (columna `montominimo` en `configuracion`, validada en `api_facturar.php`).
- El descuento de stock al facturar sale del flujo normal ya existente (`api_facturar.php`), no se duplica lógica ahí.

## 4. Cobro con QR (Mercado Pago)

- Nuevo tipo de cuenta de tesorería **"Mercado Pago"** — no existe hoy, hay que crearlo (mismo criterio que se usó para agregar "Cartera de Cheques" / "Cheques Emitidos" en `tesoreria_nueva_cuenta.php`), para que el dinero cobrado caiga en su propia cuenta contable.
- Nuevo medio de pago en Facturar / Cobrar Facturas: al elegirlo, se genera un QR dinámico por el monto exacto vía API de MP y se muestra en pantalla.
- Webhook de MP (`topic=payment`) al `webhook_mp.php` único (mismo mecanismo de resolución de tenant que 1.5) confirma la acreditación y marca el comprobante/cobro como pagado automáticamente, sin intervención manual.
- Falta definir en la implementación: comportamiento ante timeout (cliente no paga) — el operador tiene que poder cancelar el QR y elegir otro medio de pago.

## 5. Seguridad

- Tokens OAuth cifrados en la base (no texto plano).
- Los webhooks públicos (`webhook_ml.php`, `webhook_mp.php`) deben **validar la firma de origen** que mandan ML y MP respectivamente, para que nadie pueda simular una notificación falsa (ej. "pago confirmado" apócrifo) pegándole directo a la URL.

## Orden de implementación sugerido

1. **Conexión OAuth ML + MP** — prerrequisito de todo lo demás.
2. **Stock/precio a ML** — bajo riesgo, no toca fiscal ni pagos.
3. **Cobro QR MP** — independiente, se puede hacer en paralelo al punto 2.
4. **Webhook de pedidos + cola de revisión** — depende del punto 1.
5. **Facturación desde la cola** — depende del punto 4, reutiliza `newfactura.php` / `api_facturar.php` ya existentes.

## Pantallas nuevas que hacen falta

- Configuración > Integraciones (conectar ML / conectar MP)
- Vincular Publicaciones de ML (emparejar producto ↔ publicación)
- Estado de Sincronización ML (panel de errores)
- Pedidos de Mercado Libre (cola de revisión y facturación)
- Cobro QR: no es pantalla nueva, es un medio de pago más dentro de Facturar/Cobrar Facturas

## Pendiente de decidir cuando se arranque a construir

- Estrategia exacta de reintentos/backoff para pushes fallidos a ML.
- Validación puntual de firma de webhook de cada plataforma (documentación específica de ML y MP al momento de implementar).
- Qué pasa si ML sí provee CUIT del comprador (¿se usa para facturar A/B según corresponda, o siempre Consumidor Final)?
- Comportamiento de expiración/cancelación del QR de MP si el cliente no paga.
