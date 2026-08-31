# Módulos Adicionales por Tenant (diseño, sin implementar)

Estado: **diseño cerrado, sin código escrito**. Describe el mecanismo con el que se van a empaquetar y habilitar como adicionales (no incluidos por defecto en ninguna licencia) los módulos documentados en:

- `futura_integración_ML-MP.md`
- `futura_integracion_retenciones-percepciones.md`
- `futura_integracion_sueldos.md`

## Motivación

Son funcionalidades muy específicas que no todos los clientes van a usar. La idea es venderlas como adicionales — el cliente que las necesita las contrata y se le habilitan, el resto ni las ve ni paga por ellas. Esto es una dimensión **distinta** de la licencia 1/2 que ya existe (`tipoversion`): un mismo tenant en licencia 2 puede o no tener contratado cada addon, independientemente uno del otro.

## Por qué se puede reutilizar el mecanismo de licencias que ya existe

`check_access.php` ya arma `$contractUrls` leyendo el `menu1.json` o `menu2.json` según la licencia del tenant (`$_SESSION['version']`), y bloquea con "La licencia contratada no incluye este servicio" cualquier URL que no esté en esa lista. Si las pantallas de un addon **simplemente no se agregan** a `menu1.json`/`menu2.json`, ya quedan bloqueadas por defecto para todos los tenants, sin tocar nada del código existente. Solo hace falta agregar la excepción para el tenant que sí lo contrató.

## Mecanismo propuesto

### 1. Tabla `tenant_addons` (en la base maestra `ClientesSistema`)

Columnas: `cuit`, `addon_key`, `habilitado`, `fecha_alta`. Una fila por cada addon contratado por cada empresa. No tiene relación con `tipoversion` — son dimensiones independientes (un tenant de licencia 2 puede tener cero, uno, o varios addons activos).

### 2. Registro de addons → archivos

Un array/JSON chico (ej. `addons_registro.php`) que mapea cada `addon_key` a la lista de archivos `.php` que le pertenecen. Mismo espíritu que la lista `$core_files` que ya existe en `check_access.php`. Es la **fuente única**: la usan tanto el chequeo de acceso como el armado del menú, para no mantener la misma lista en dos lugares.

Addons previstos hoy (keys sugeridas):
- `ml_mp` → pantallas de conexión OAuth, vínculo de publicaciones, cola de pedidos ML, cobro QR MP.
- `retenciones_percepciones` → pantallas de Padrón, regímenes/alícuotas, retenciones practicadas, percepciones practicadas, certificados.
- `sueldos_rrhh` → legajos, convenios, liquidaciones, vacaciones, liquidación final.

### 3. Extensión de `check_access.php`

Antes de armar `$contractUrls` desde el JSON de licencia, se le suman las URLs de los addons que ese tenant tenga activos en `tenant_addons` (join contra el registro del punto 2). El resto de la lógica —whitelist, mensaje de "no incluido", log de acceso denegado— queda exactamente igual, sin reescribir nada de lo que ya funciona. Un tenant sin el addon activo simplemente nunca ve esa URL agregada a su `$contractUrls`, y cae en el mismo bloqueo que ya existe hoy para cualquier módulo fuera de licencia.

### 4. Extensión de `menu.php`

Al renderizar el menú (una sola vez por carga completa de página, como ya funciona hoy — no se recarga en la navegación SPA), se agrega un ítem nuevo "Módulos Adicionales" que solo se pinta si el tenant tiene al menos un addon activo. Adentro, solo aparecen los sub-ítems de los addons que efectivamente contrató. Si no tiene ninguno, el ítem ni se renderiza.

### 5. Gestión desde el backoffice

Hace falta una pantalla chica (o un agregado a `administrartablas.php`) para tildar/destildar addons por cliente al armar o modificar el contrato. El alta la controla el administrador (vos), no queda en manos del cliente activarse un addon solo.

## Por qué es de bajo riesgo

No modifica la lógica central de `check_access.php` (whitelist, roles, admin bypass, core files) — solo extiende la **fuente de datos** de la que se arma `$contractUrls`, reutilizando el mismo mecanismo de bloqueo por defecto server-side que ya usa toda la app (nunca depender de que la UI oculte un botón; el servidor es el que decide).

## Pendiente de decidir cuando se arranque a construir

- Si un addon puede tener sub-módulos con licenciamiento independiente entre sí (ej. contratar ML pero no MP dentro del mismo addon `ml_mp`) o si cada addon es todo-o-nada.
- Si el vencimiento/baja de un addon debe dejar los datos ya cargados visibles en modo solo lectura, o bloquear el acceso por completo incluida la consulta histórica.
- Si conviene facturar los addons como línea aparte en algún proceso de facturación de la propia plataforma (relacionado con el punto de "self-service de alta de tenant" que se mencionó en una charla anterior sobre escalabilidad).
