<?php
/**
 * documentacion_api_productos.php - PERMALINK DE DOCUMENTACIÓN TÉCNICA
 *
 * Documentación pública y genérica (no lleva el token de ningún tenant) de
 * api_productos_externa.php, pensada para descargarse y pasársela a quien
 * programe la integración del lado de la tienda. Sin sesión: es un permalink
 * estable, igual que la API que documenta.
 */

$md = <<<MD
# API de Productos WEST IT — Precios y Stock

Documentación técnica para integrar el catálogo de productos (precios y
stock) de un sistema WEST IT en una tienda externa.

## Autenticación

Todas las llamadas requieren un token de acceso, generado desde
**Administrador > Configurar APIs > Productos** en el sistema WEST IT del
comercio. Se envía de una de estas dos formas:

- Header (recomendado):
  `Authorization: Bearer <token>`
- Parámetro `token` (útil para pruebas rápidas desde el navegador):
  `?token=<token>`

Un token inválido devuelve `401`. Un token válido pero con la API
deshabilitada devuelve `403`.

## Endpoint

```
GET/POST https://<dominio-del-comercio>/api_productos_externa.php
```

Acepta **GET** (parámetros por querystring) y **POST** (parámetros por
formulario `application/x-www-form-urlencoded`/`multipart/form-data`, o por
JSON con `Content-Type: application/json`). Los nombres de parámetro son los
mismos en ambos casos. Si un parámetro llega tanto por querystring como por
body, gana el valor del body.

### Parámetros opcionales

| Parámetro          | Tipo   | Descripción                                                                 |
|---------------------|--------|------------------------------------------------------------------------------|
| `codigo`            | string | Filtra por código exacto de producto.                                       |
| `q`                  | string | Busca coincidencias en código o descripción.                                |
| `rubro`              | string | Filtra por rubro (texto exacto).                                            |
| `marca`              | int    | Filtra por id de marca.                                                     |
| `familia`            | int    | Filtra por id de familia.                                                   |
| `actualizado_desde`  | string | Fecha/hora `YYYY-MM-DD HH:MM:SS`. Solo productos modificados desde ese momento (útil para sincronización incremental). |
| `pagina`             | int    | Página de resultados. Default `1`.                                          |
| `por_pagina`         | int    | Resultados por página. Default `100`, máximo `500`.                         |

## Respuesta

`Content-Type: application/json`. Forma general:

```json
{
  "success": true,
  "data": [ { /* producto */ }, ... ],
  "meta": { "pagina": 1, "por_pagina": 100, "total": 2657 }
}
```

Cada producto en `data`:

```json
{
  "codigo": "0001-ABC",
  "descripcion": "Descripción interna del producto",
  "descripcion_tienda": "Descripción pensada para catálogo público (si no está cargada, repite la descripción interna)",
  "marca": "Nombre de la marca",
  "rubro": "Nombre del rubro",
  "familia": "Nombre de la familia",
  "listas_precios": [
    { "id_lista": 1, "nombre": "Lista Minorista", "precio_neto": 1908.84, "iva": 400.86, "precio_final": 2309.72 }
  ],
  "alicuota_iva": 21,
  "impuesto_interno": 0,
  "stock": 15,
  "stock_disponible": 12,
  "controla_stock": true,
  "mostrar_sin_stock": true,
  "imagenes": [ "https://<dominio>/img_producto_publico.php?token=...&codigo=..." ],
  "actualizado": "2026-08-14 09:28:14"
}
```

Notas sobre los campos:

- **`listas_precios`**: trae una entrada por cada lista de precios que el
  comercio haya elegido compartir (puede ser más de una). `precio_neto` es
  sin IVA, `iva` es el importe de IVA de esa lista, `precio_final` es
  `precio_neto + iva` (el impuesto interno, si tiene, no está incluido acá:
  ver `impuesto_interno`).
- **`stock`**: según lo configurado por el comercio, es el stock
  consolidado de todos los depósitos o el de un depósito puntual.
- **`stock_disponible`**: `stock` menos lo comprometido (por ejemplo,
  reservado en pedidos). Cuando el stock compartido es el de un depósito
  puntual, `stock_disponible` es igual a `stock` (lo comprometido se
  registra a nivel global, no por depósito).
- **`imagenes`**: URLs públicas (requieren el mismo token) o array vacío si
  el producto no tiene fotos cargadas.
- Solo se listan productos habilitados y marcados para mostrarse en
  catálogo del lado del comercio.

## Imágenes de producto

Cada URL de `imagenes[]` apunta a:

```
GET https://<dominio-del-comercio>/img_producto_publico.php?token=<token>&codigo=<codigo>
```

Devuelve el archivo de imagen directamente (`Content-Type: image/jpeg`,
`image/png` o `image/webp`), o `404` si no hay foto cargada para ese código.

## Códigos de respuesta

| HTTP | Significado                                                             |
|------|--------------------------------------------------------------------------|
| 200  | OK.                                                                       |
| 401  | Falta el token, o el token no es válido.                                 |
| 403  | El token es válido pero la API está deshabilitada para ese comercio.     |
| 500  | Error interno.                                                           |
| 503  | La base de datos del comercio no está disponible en este momento.        |

## Ejemplos

```bash
# Listado paginado
curl -H "Authorization: Bearer <token>" \\
  "https://<dominio>/api_productos_externa.php?por_pagina=50"

# Un producto puntual
curl -H "Authorization: Bearer <token>" \\
  "https://<dominio>/api_productos_externa.php?codigo=0001-ABC"

# Sincronización incremental (solo cambios desde una fecha)
curl -H "Authorization: Bearer <token>" \\
  "https://<dominio>/api_productos_externa.php?actualizado_desde=2026-09-01+00:00:00"

# POST con JSON
curl -X POST -H "Content-Type: application/json" \\
  -H "Authorization: Bearer <token>" \\
  -d '{"q": "niwa", "por_pagina": 10}' \\
  "https://<dominio>/api_productos_externa.php"
```
MD;

header('Content-Type: text/markdown; charset=utf-8');
header('Content-Disposition: attachment; filename="documentacion_api_productos_westit.md"');
header('Cache-Control: no-cache');
echo $md;
