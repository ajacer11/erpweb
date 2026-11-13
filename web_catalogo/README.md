# Web de clientes: catálogo + área de clientes (v1)

Web pública con catálogo y área de clientes (precios según su lista, pedidos, cuenta corriente,
facturas, pagos informados). Una sola instalación sirve a todas las empresas que la contraten:
la empresa se reconoce por el dominio.

## Estructura

| Carpeta | Qué es |
|---|---|
| `app/` | Front (Vue 3 + TypeScript + Tailwind). Código fuente; no se sube al VPS (tiene `.htaccess` que bloquea). |
| `public/` | **Build** del front (`npm run build`) + `public/api/index.php`. Es el docroot de cada dominio. Se versiona. |
| `api/` | API PHP (sin framework). Usa `db_master.php`, `web_helpers.php` y `precios_listas.php` del ERP. Bloqueada por `.htaccess` (se llega solo por `public/api`). |

Del lado del ERP: `web_helpers.php`, `web_config.php`, `web_accesos.php`, `web_avisos_pago.php`,
`web_atributos.php` (+ sus `api_web_*.php`), pestaña de características en `productos_add.php`,
menú "Administrador > Web de Clientes" (ítems con `"addon": "web_catalogo"` en `menu1/2.json`;
`menu.php` los oculta y `check_access.php` los bloquea si la empresa no tiene el addon).

## Datos

- Base de cada empresa: `sql/2026-11-12_web_catalogo.sql` (correr desde el backoffice en todos los tenants).
- Base maestra: `sql/2026-11-12_web_catalogo_maestra.sql` (tabla `web_sitios`: dominio → empresa).
- Imágenes de la web: `img/web/{cuit}/` (logo, banners, comprobantes de pago). Fotos de productos: `img/productos/{cuit}/` (las mismas del ERP).

## Habilitar la web para una empresa

1. Addon: `INSERT INTO tenant_addons (cuit, addon_key, habilitado) VALUES ('<cuit>', 'web_catalogo', 1) ON DUPLICATE KEY UPDATE habilitado = 1;`
2. Dominio: `INSERT INTO web_sitios (dominio, cuit, db_id, tipo) VALUES ('<dominio sin www>', '<cuit>', <id de la base>, 'catalogo');`
   (si usa `www.`, se resuelve igual: se quita el prefijo).
3. En el VPS (CWP): alta del dominio con docroot apuntando a `/home/westit/erp.westit.com.ar/web_catalogo/public`
   (o carpeta del dominio como enlace simbólico a esa ruta) + certificado SSL.
4. En el ERP de la empresa: Administrador > Web de Clientes > Configuración de la Web.

## Correo

`config_mail.php` en la raíz del ERP (gitignoreado; ver `config_mail.example.php`): casilla de WEST IT
por defecto + `clave_cifrado` para las claves SMTP propias que carguen las empresas. Requiere
`composer install` (PHPMailer).

## Desarrollo local

- `localhost` está registrado en `web_sitios` local apuntando a la base de prueba.
- `cd app && npm install && npm run dev` → http://localhost:5173 (el `/api` va al Apache de XAMPP).
- `npm run build` antes de subir (genera `public/`).
