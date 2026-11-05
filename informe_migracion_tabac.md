# Informe de migración TABAC

Fecha de ejecución: 06/09/2026.

## Bases

- Histórico: `tabac_old`.
- Sistema vigente: `tabac_new`.
- Consolidado: `tabac_final`.

`tabac_old` y `tabac_new` no fueron modificadas. `tabac_final` se construyó con
el esquema vigente de `tabac_new` y contiene 133 tablas de aplicación, seis
procedimientos y un trigger. Las demás tablas del esquema final cuyo nombre
comienza con `_mig_` son mapas y controles de auditoría de esta migración.

## Criterios aplicados

1. Se conservó el historial transaccional de `tabac_old`.
2. Configuración y maestros se tomaron de `tabac_new`: usuarios, cuentas,
   proveedores, medios de pago, cajas, listas, rubros, marcas, familias y demás
   catálogos vigentes.
3. Se agregaron los clientes legacy 1811, 1812 y 1813, ausentes en el snapshot
   inicial de `tabac_new`, porque ya tenían historia válida en legacy.
4. Los productos se relacionaron por código normalizado y no por ID. Ambos
   sistemas reutilizaron algunos IDs superiores a 15200 para artículos distintos.
5. Se incorporaron nueve productos que existían solamente en legacy, asignándoles
   IDs nuevos entre 15222 y 15230.
6. Se trasladaron al 01/09/2026 las fechas operativas legacy del 03/09 y 04/09.
   Los timestamps de carga, números fiscales, CAE y vencimientos de CAE quedaron
   sin cambios. Cada campo modificado está registrado en `_mig_fechas_legacy`.
7. Los IDs de movimientos de `tabac_new` se desplazaron y quedaron registrados
   en tablas `_mig_map_*`. También se remapearon todas las relaciones hijas.
8. `librodiario.numasiento` del sistema nuevo se desplazó `+202584`.
9. Los comprobantes internos de pagos a proveedores se desplazaron `+3407`.
10. Los saldos físicos de cajas de usuarios y Caja Central se tomaron de
    `tabac_new`. Se neutralizó únicamente el saldo histórico de esas cuentas al
    inicio del 02/09, antes de incorporar sus movimientos nuevos.
11. El stock final se calculó como stock actual legacy más la variación de
    `tabac_new`, que comenzó en cero. También se ajustaron los valores inicial/final
    del historial de stock nuevo para conservar continuidad.
12. Todos los controles legacy activos se cerraron administrativamente al corte;
    los controles vigentes de `tabac_new` se incorporaron con IDs remapeados.

## Resultado cuantitativo

| Tabla | Legacy | Nuevo | Ajustes técnicos | Final |
|---|---:|---:|---:|---:|
| `tickets` | 168.566 | 713 | 0 | 169.279 |
| `lineadeventa` | 423.357 | 1.678 | 0 | 425.035 |
| `librodiario` | 601.786 | 4.263 | 0 | 606.049 |
| `cccuentastesoreria` | 116.549 | 804 | 5 | 117.358 |
| `cajaxusuario` | 115.744 | 323 | 0 | 116.067 |
| `controlcajas` | 3.552 | 12 | 0 | 3.564 |
| `facturasdecompra` | 3.635 | 8 | 0 | 3.643 |
| `ccbancos` | 61.090 | 382 | 0 | 61.472 |
| `ccclientes` | 1.830 | 11 | 0 | 1.841 |
| `moviemientosccproveedores` | 7.040 | 16 | 0 | 7.056 |
| `movimientosdestock` | 409.873 | 1.665 | 0 | 411.538 |
| `controlstock` | 384.304 | 1.561 | 0 | 385.865 |
| `ingresosdestock` | 30.478 | 104 | 0 | 30.582 |

Rangos principales asignados al lote nuevo:

| Entidad | Desde | Hasta | Filas |
|---|---:|---:|---:|
| Tickets | 169933 | 170646 | 713 |
| Líneas de venta | 428965 | 430643 | 1.678 |
| Facturas de compra | 3845 | 3852 | 8 |
| Pagos a proveedores | 3412 | 3419 | 8 |
| Cobros | 570 | 572 | 3 |
| Controles de caja | 3559 | 3570 | 12 |
| Movimientos de caja | 116055 | 116377 | 323 |
| Libro diario | 602319 | 606581 | 4.263 |
| Tesorería | 117147 | 117950 | 804 |

## Traslado de fechas legacy

Se modificaron 4.481 campos, todos auditados. No quedan valores auditados cuya
fecha nueva sea distinta del 01/09/2026.

| Tabla/campo | Fecha original | Cantidad |
|---|---|---:|
| `caja.FechaMov` | 03/09 | 5 |
| `caja.FechaMov` | 04/09 | 1 |
| `cajaxusuario.FechaMov` | 03/09 | 106 |
| `ccbancos.fecha` | 03/09 | 106 |
| `cccuentastesoreria.fecha` | 03/09 | 223 |
| `ccvendedores.fecha` | 03/09 | 178 |
| `controlcajas.abiertael` | 03/09 | 3 |
| `controlcajas.abiertael` | 04/09 | 1 |
| `controlcajas.cerradael` | 03/09 | 4 |
| `controlstock.fecha` | 03/09 | 745 |
| `ingresosdestock.fecha` | 03/09 | 50 |
| `librodiario.fecha` | 03/09 | 1.109 |
| `lineaconceptoscompra.fecha` | 03/09 | 8 |
| `lineadeventa.fecha` | 03/09 | 794 |
| `movimientosdestock.fecha` | 03/09 | 786 |
| `pagoaproveedores.fecha` | 03/09 | 4 |
| `pagoaproveedores.fecha` | 04/09 | 2 |
| `tickets.fecha` | 03/09 | 178 |
| `tickets.fecharemito` | 03/09 | 178 |

No se cambiaron fechas de vencimiento de facturas, fechas de CAE, timestamps de
auditoría ni registros de login.

## Stock

- Stock legacy al corte: 27.334 unidades.
- Variación completa registrada en `tabac_new`: -4.159 unidades.
- Stock consolidado: 23.175 unidades.
- Productos con diferencia respecto de la fórmula esperada: 0.
- Diferencias en los valores inicial/final remapeados de `controlstock`,
  `ingresosdestock` e `historicoproductos`: 0.
- Multidepósito está deshabilitado y no existen movimientos por depósito.

Los 2.793 productos legacy tienen un único destino en `_mig_map_productos`. El
consolidado contiene los 2.800 productos vigentes más nueve exclusivos de legacy:

| ID legacy | ID final | Código | Stock final |
|---:|---:|---|---:|
| 15201 | 15222 | 2100115 | 0 |
| 15202 | 15223 | HXZP011 | 0 |
| 15203 | 15224 | HXZP048 | 1 |
| 15205 | 15225 | HXZP076 | 1 |
| 15207 | 15226 | 4014663702474 | 2 |
| 15211 | 15227 | 6932570176387 | 2 |
| 15212 | 15228 | 840472605590 | 1 |
| 15213 | 15229 | 840472612536 | 0 |
| 15214 | 15230 | 840472612482 | 1 |

## Cajas y tesorería

`tabac_final.cajas` coincide exactamente con `tabac_new.cajas` en saldo, estado
y usuario activo. Los saldos calculados desde tesorería también coinciden:

| Medio | Saldo final/new |
|---|---:|
| Efectivo Caja 1 | $11.050,40 |
| Efectivo Caja 2 | $23.750,00 |
| Efectivo Caja 3 | $34.800,00 |
| Efectivo Caja 4 | $0,00 |
| Efectivo Caja 5 | $0,00 |
| Efectivo Caja 6 | $0,00 |
| Caja Central | $5.229.000,00 |
| Efectivo Caja 7 | $0,00 |

Los cinco ajustes técnicos de corte ocupan los números de tesorería 102792 a
102796. El lote nuevo comienza en 102797. Los ajustes no son fiscales ni crean
asientos contables; su única función es impedir que saldos de efectivo históricos
se sumen al efectivo físico confirmado en `tabac_new`.

## Comprobantes fiscales recuperados

Los cuatro comprobantes emitidos accidentalmente mientras se cargaba legacy se
conservaron con CAE, punto de venta, número, vencimiento y timestamp originales:

| Tipo | PV-Número | CAE | Total | Fecha operativa final |
|---|---|---|---:|---|
| Factura B | 4-160842 | 86361273248147 | $52.600,00 | 01/09/2026 |
| Factura B | 4-160844 | 86361274358826 | $44.910,00 | 01/09/2026 |
| Nota de Crédito B | 4-1186 | 86361275075502 | $52.600,00 | 01/09/2026 |
| Nota de Crédito B | 4-1187 | 86361275496565 | $44.910,00 | 01/09/2026 |

El efecto financiero y contable conjunto es cero. No existe colisión de punto de
venta, tipo y número con los 713 comprobantes de `tabac_new`.

### Pendiente de definición: diferencia de tres unidades

La Factura B 4-160844 registra tres unidades de `77993397` (Marlboro Crafted
Forward KS 20), pero su Nota de Crédito B 4-1187 registra tres unidades de
`77987303` (Marlboro Crafted Red KS 20). Por lo tanto, los importes se cancelan,
pero el stock no: legacy dejó `-3` del primer código y `+3` del segundo.

La consolidación conserva por ahora lo efectivamente registrado y el stock final
incluye esa diferencia. No se modificó documentación fiscal ni se inventó un
ajuste. Si el cliente confirma que la NC debía revertir el mismo artículo de la
factura, corresponde agregar un ajuste de migración `+3` a `77993397` y `-3` a
`77987303`, sin alterar los cuatro comprobantes fiscales.

## Anomalías heredadas, no creadas por la migración

- 1.111 tickets legacy ya tenían `lvdesde` o `lvhasta` inexistente. El lote nuevo
  no agregó ninguno.
- Seis movimientos legacy de `cajaxusuario` ya apuntaban a caja/control 0 o -1.
  Los 323 movimientos nuevos no tienen huérfanos.
- Hay 67 tickets sin cliente maestro: 66 provienen de legacy y uno de `tabac_new`;
  todos usan cliente 0 sin documento que permita un remapeo seguro.
- `tabac_new.referenciasproductos` ya contiene 1.096 referencias cuyo `refierea`
  no coincide con un código vigente. El consolidado conserva el maestro nuevo.
- Los huérfanos de códigos de stock coinciden con los orígenes: legacy aportó 45
  en `movimientosdestock`, 3.622 en `controlstock` y seis en `ingresosdestock`;
  `tabac_new` no agregó ninguno.
- Las líneas de venta sin producto maestro también coinciden con los orígenes:
  2.649 legacy y cinco nuevas, sin contar el código manual `LM`.
- El libro legacy ya tenía una diferencia Debe-Haber de `-$19,15` distribuida en
  500 números de asiento. `tabac_new` aporta una diferencia preexistente de
  `-$0,01` en el asiento 130.
- El esquema legacy guardaba Debe/Haber como `DOUBLE`; el esquema vigente usa
  `DECIMAL(18,2)`. La normalización de 46.140 valores Debe y 151.783 valores Haber
  agrega un efecto acumulado de `-$51,65`. La diferencia global final es
  `-$70,81`. No se generaron asientos artificiales para ocultarla.

## Validaciones

- Los 713 tickets y 1.678 líneas nuevas coinciden en importes, códigos, fechas,
  CAE y numeración con `tabac_new`.
- Todas las relaciones nuevas hacia tickets, líneas, compras, pagos, cobros,
  controles de caja y maestros existen.
- No hay movimientos de tesorería con medio de pago inexistente.
- Los offsets de libro, tesorería y pagos son consecutivos y no se superponen.
- Los `AUTO_INCREMENT` quedaron por encima del máximo ID de cada tabla.
- Las 156 tablas físicas del esquema final, incluidas las tablas de auditoría,
  devolvieron estado `OK` en `mysqlcheck`.

## Archivos

El procedimiento reproducible se encuentra en `sql/migrar_tabac_final.php`. El
script se niega a ejecutarse si `tabac_final` ya existe.

## Queries principales utilizadas

### Traslado auditable de fechas

```sql
CREATE TABLE tabac_final._mig_fechas_legacy (
    tabla VARCHAR(100) NOT NULL,
    id_registro BIGINT NOT NULL,
    columna VARCHAR(100) NOT NULL,
    fecha_original DATETIME NOT NULL,
    fecha_nueva DATETIME NOT NULL,
    PRIMARY KEY(tabla,id_registro,columna)
);

-- Se repitió sobre cada tabla/campo enumerado en este informe:
INSERT INTO tabac_final._mig_fechas_legacy
SELECT 'tickets',id,'fecha',fecha,'2026-09-01'
FROM tabac_final.tickets
WHERE fecha IN ('2026-09-03','2026-09-04');

UPDATE tabac_final.tickets
SET fecha='2026-09-01'
WHERE fecha IN ('2026-09-03','2026-09-04');
```

### Mapa de productos y stock

```sql
CREATE TABLE tabac_final._mig_map_productos (
    id_old INT PRIMARY KEY,
    id_new INT NOT NULL UNIQUE,
    codigo_old VARCHAR(200) NOT NULL,
    codigo_new VARCHAR(200) NOT NULL
);

UPDATE tabac_final.alb_productos p
JOIN tabac_final._mig_stock_base b ON b.id_producto=p.id
LEFT JOIN tabac_new.alb_productos n ON n.id=p.id
SET p.Stock=ROUND(b.stock_base+COALESCE(n.Stock,0),2);
```

### Neutralización de efectivo

```sql
-- Forma resumida. El script asigna números 102792..102796 con ROW_NUMBER().
INSERT INTO tabac_final.cccuentastesoreria
    (idcuenta,debe,haber,tabla,usuario,usuariocaja,fecha,
     descripcion,fiscal,compasoc,numerodeasiento)
SELECT id,
       CASE WHEN saldo<0 THEN -saldo ELSE 0 END,
       CASE WHEN saldo>0 THEN saldo ELSE 0 END,
       'migracion_saldo_corte','MIGRACION','MIGRACION','2026-09-02',
       CONCAT('Neutralización saldo legacy: ',medio),0,0,
       102791+ROW_NUMBER() OVER (ORDER BY id)
FROM saldos_legacy_de_efectivo;
```

### Validaciones de stock y caja

```sql
SELECT ROUND(SUM(Stock),2)
FROM tabac_final.alb_productos;
-- 23175.00

SELECT COUNT(*)
FROM tabac_new.cajas n
LEFT JOIN tabac_final.cajas f ON f.id=n.id
WHERE f.id IS NULL
   OR NOT(f.estado<=>n.estado)
   OR NOT(f.usuarioactivo<=>n.usuarioactivo)
   OR ABS(f.saldo-n.saldo)>0.001;
-- 0

SELECT COUNT(*)
FROM tabac_final.cccuentastesoreria t
LEFT JOIN tabac_final.mediosdepago m ON m.id=t.idcuenta
WHERE m.id IS NULL;
-- 0
```

### Validación fiscal

```sql
SELECT id,fecha,tipocomp,PtoVta,numcomp,cae,fechacae,total,timestamp
FROM tabac_final.tickets
WHERE id IN (169755,169757,169758,169759)
ORDER BY id;

SELECT ROUND(SUM(
    CASE WHEN tipocomp=6 THEN total
         WHEN tipocomp=8 THEN -total ELSE 0 END
),2)
FROM tabac_final.tickets
WHERE id IN (169755,169757,169758,169759);
-- 0.00
```

### Integridad física

```powershell
& 'C:\xampp\mysql\bin\mysqlcheck.exe' -uroot --check tabac_final
```
