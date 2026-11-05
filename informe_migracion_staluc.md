# Informe de migración STALUC

Fecha de ejecución: 05/09/2026.

## Bases involucradas

- Origen histórico: `staluc_old`.
- Origen vigente: `staluc_new`.
- Resultado consolidado: `staluc_final`.

Las bases de origen no fueron modificadas. La primera construcción parcial de
`staluc_final` se descartó y se reconstruyó por completo luego de detectar una
restricción de Caja Central incompatible con los datos legacy.

## Criterio aplicado

1. Se creó `staluc_final` usando el esquema actual de `staluc_new`.
2. Se importó la historia completa de `staluc_old`, conservando sus IDs.
3. Se incorporaron las 106 ventas del sistema nuevo con IDs autoincrementales.
4. Se crearon tablas `_mig_map_*` para relacionar cada ID original del sistema
   nuevo con su ID definitivo.
5. Los clientes nuevos se insertaron con IDs nuevos. Solamente Consumidor Final
   se unificó con el cliente histórico ID 1. Esto evita unir arbitrariamente
   CUIT duplicados presentes en el legacy.
6. `Lineadeventa.id`, `tickets.id`, `tickets.lvdesde`, `tickets.lvhasta`,
   `librodiario.compasoc` y `cccuentastesoreria.compasoc` fueron remapeados.
7. Los números de asiento del sistema nuevo se desplazaron `+6324`.
8. Los números de tesorería del sistema nuevo se desplazaron `+10873`.
9. Se mantuvieron las fechas originales: el legacy termina el 29/08/2026 y el
   sistema nuevo comienza el 01/09/2026. Este piloto no necesitó cambiar fechas.
10. Se mantuvo la configuración vigente de `staluc_new` y la historia operativa
    de `staluc_old`.
11. Los medios de pago que ya habían sido migrados como maestros se unificaron
    mediante el mapa `_mig_map_mediosdepago`. Todas sus referencias históricas
    se cambiaron a los IDs vigentes y se eliminaron solamente las copias legacy
    duplicadas. Los medios legacy sin equivalente confirmado se conservaron.
12. Las referencias históricas a cajas se remapearon por `numcaja` hacia los IDs
    del maestro vigente. Los saldos, estados y usuarios activos de `cajas` son
    una copia exacta de `staluc_new`, definida por el cliente como fuente real
    del efectivo físico. No se sumaron saldos legacy.
13. Los tres controles que permanecían abiertos en legacy se cerraron
    administrativamente al corte `29/08/2026 23:59:59`. Su `saldofinal` se
    conserva como dato histórico, sin afectar el saldo vigente de las cajas.
14. Se generó el control de caja ID 1122 para vincular los 104 movimientos del
    sistema nuevo, cuya base no contenía la correspondiente fila en `controlcajas`.
15. Se copiaron desde `staluc_new` las seis rutinas almacenadas y el trigger
    `before_insert_tickets`.

## Resultados

| Entidad | Legacy | Nuevo | Final |
|---|---:|---:|---:|
| Tickets | 9.354 | 106 | 9.460 |
| Líneas de venta | 21.758 | 177 | 21.935 |
| Libro diario | 33.990 | 318 | 34.308 |
| Tesorería | 10.878 | 106 | 10.984 |
| Movimientos de caja | 9.047 | 104 | 9.151 |
| Clientes | 6.756 | 20 | 6.775 |
| Tickets auxiliares | 0 | 3 | 3 |

Rangos definitivos de los datos nuevos:

- Tickets: IDs 9558 a 9663.
- Líneas de venta: IDs 22184 a 22360.
- Clientes nuevos: IDs 6757 a 6775.
- Asientos: 6325 a 6430.
- Numeración de tesorería: 10874 a 10979.

## Validaciones

- Los importes y cantidades por fecha desde el 01/09 hasta el 05/09 coinciden
  exactamente con `staluc_new`.
- Los 318 renglones contables incorporados suman Debe `$4.546.553,00` y Haber
  `$4.546.553,00`; diferencia `$0,00`.
- No hay asientos nuevos con `compasoc` huérfano.
- No hay movimientos nuevos de tesorería con `compasoc` huérfano.
- Los 106 tickets nuevos tienen mapa de ID único.
- Las 177 líneas nuevas tienen mapa de ID único.
- Los 20 clientes nuevos tienen mapa de ID; Consumidor Final apunta al ID 1.
- No quedan referencias a los 15 IDs legacy de medios de pago unificados.
- Las 10.872 filas históricas de `cccuentastesoreria` fueron remapeadas por
  `idcuenta`; ninguna queda huérfana respecto de `mediosdepago`.
- No quedan medios habilitados con el mismo nombre.
- El catálogo vigente de medios de pago de `staluc_new` está completo en el final.
- `staluc_final.cajas` coincide exactamente con `staluc_new.cajas` en ID, número,
  saldo, estado y usuario activo.
- No existen referencias huérfanas desde `controlcajas` o `cajaxusuario` a cajas.
- Solo permanece abierto el control vigente ID 1122, correspondiente a Puesto 1,
  usuario `admin`, con saldo final `$4.546.550,00`.
- Los `AUTO_INCREMENT` quedaron por encima de los IDs existentes.

## Hallazgos que requieren conocimiento del cliente

### Colisión de numeración fiscal (RESUELTA el 05/09/2026)

Se había detectado que el ticket final ID 9558 (Factura B, PV 4, número 1,
CAE `86350844331321`) colisionaba con el legacy ID 7509 (mismo PV/tipo/número,
fecha 18/05/2026, CAE `86206014511441`). En un principio no se modificó
ninguno de los dos porque ambos son números fiscales autorizados, y se
reemplazó el índice único `pdv_tipo_y_numero_de_comprobante` por el de
consulta no único `idx_pdv_tipo_numcomp` para poder convivir con la colisión.

Investigación posterior determinó que **no era una colisión real de ARCA**,
sino que la numeración local del ticket 9558 (y otros 28 comprobantes del
01/09) estaba mal grabada — ver sección "Corrección de numeración fiscal del
01/09/2026" más abajo. Con esa corrección, el ticket 9558 pasó a ser el
número 8676 y la colisión desapareció: hoy no existe ningún par
(PtoVta, tipocomp, numcomp) duplicado en toda la tabla `tickets`. El índice
único `pdv_tipo_y_numero_de_comprobante` ya fue restablecido — ver sección
"Restablecimiento del índice único" más abajo.

### Rangos de líneas históricos

Hay 80 tickets legacy cuyo `lvdesde` o `lvhasta` ya apuntaba a una línea
inexistente en `staluc_old`. Los 106 tickets incorporados desde `staluc_new` no
tienen este problema. La migración preservó la anomalía histórica sin inventar
relaciones.

### Desbalance contable heredado del legacy

El libro completo de `staluc_old` ya estaba desbalanceado antes de la migración:

- Debe: `$1.286.197.901,13`.
- Haber: `$1.280.522.394,67`.
- Diferencia: `$5.675.506,46`.
- 534 números de asiento legacy presentan una diferencia superior a un centavo.

El esquema vigente guarda `debe` y `haber` como `DECIMAL(18,2)`, mientras que
legacy utilizaba `DOUBLE` y tenía valores con más de dos decimales. Al copiarlos
al esquema actual se normalizaron 137 importes de Debe y 7.856 de Haber a dos
decimales. El efecto acumulado fue `-$0,04` en Debe y `+$1,20` en Haber; por eso
la diferencia histórica en `staluc_final` es `$5.675.505,22`.

No se generaron asientos compensatorios ni se alteró la contabilidad para forzar
un balance. El lote procedente de `staluc_new` sí está balanceado exactamente en
`$4.546.553,00` por cada lado.

### Decisión confirmada sobre cajas físicas

El cliente confirmó que los saldos de `staluc_new` representan el efectivo
físico real. Por ese motivo no se arrastraron al estado vigente los saldos de
los controles legacy que figuraban abiertos:

| Control legacy | Caja vigente | Usuario histórico | Saldo histórico | Tratamiento |
|---:|---:|---|---:|---|
| 13 | 6 (ID 8) | angel | $16.903,00 | Cerrado al corte |
| 39 | 4 (ID 4) | diego | $9.530,00 | Cerrado al corte |
| 896 | 5 (ID 5) | claudio | $2.226.142,00 | Cerrado al corte |

Los importes anteriores permanecen en `controlcajas.saldofinal` exclusivamente
como historia. No se incorporaron a `cajas.saldo` ni a movimientos contables.

### Unificación de medios de pago

Se aplicaron estas equivalencias de maestro:

| Legacy | Vigente | Medio |
|---:|---:|---|
| 1 | 101 | Efectivo Caja 1 |
| 2 | 102 | Efectivo Caja 2 |
| 3 | 103 | Saldo inicial cajas de usuario |
| 4 | 104 | Efectivo Caja 3 |
| 5 | 105 | Efectivo Caja 4 |
| 6 | 106 | Efectivo Caja 5 |
| 7 | 107 | Efectivo Caja 6 |
| 11 | 111 | Cuenta Corriente |
| 12 | 112 | Caja Central |
| 13 | 113 | Valores en Cartera |
| 14 | 114 | Cheques Propios |
| 15 | 115 | Ajuste de Cajas |
| 16 | 116 | Cupones de Tarjetas |
| 17 | 117 | Redondeo |
| 19 | 108 | MercadoPago |

MercadoPago cambió su cuenta contable de maestro (`11104003` en legacy a
`11104001` en el vigente). La referencia al medio se remapeó a ID 108, pero los
asientos históricos de tesorería no se reescribieron: conservan la imputación
contable con la que fueron originalmente registrados.

## Archivos

El procedimiento reproducible está en `sql/migrar_staluc_final.php`. El script
se niega a ejecutarse si `staluc_final` ya existe, para evitar sobrescrituras.

## Queries utilizadas

### Inventario y estructura

```sql
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA IN ('staluc_old','staluc_new')
ORDER BY TABLE_SCHEMA,TABLE_NAME;

SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA IN ('staluc_old','staluc_new')
ORDER BY TABLE_SCHEMA,TABLE_NAME,ORDINAL_POSITION;
```

### Creación del destino y copia histórica

Estas operaciones fueron generadas para cada tabla por el script reproducible:

```sql
CREATE DATABASE `staluc_final`
CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

CREATE TABLE `staluc_final`.`<tabla>`
LIKE `staluc_new`.`<tabla>`;

INSERT INTO `staluc_final`.`<tabla>` (`<columnas comunes>`)
SELECT `<columnas comunes>`
FROM `staluc_old`.`<tabla>`;
```

### Mapas de IDs

```sql
CREATE TABLE staluc_final._mig_map_clientes (
    id_old INT PRIMARY KEY,
    id_new INT NOT NULL
);

CREATE TABLE staluc_final._mig_map_lineadeventa (
    id_old BIGINT PRIMARY KEY,
    id_new BIGINT NOT NULL
);

CREATE TABLE staluc_final._mig_map_tickets (
    id_old INT PRIMARY KEY,
    id_new INT NOT NULL
);

INSERT INTO staluc_final._mig_map_clientes VALUES (1,1);
```

Los registros nuevos se insertaron omitiendo `id`; después de cada inserción se
guardó `LAST_INSERT_ID()` junto al ID del origen en la tabla de mapa correspondiente.

### Remapeo de relaciones

```sql
-- Forma aplicada al importar tickets:
SELECT id_new
FROM staluc_final._mig_map_clientes
WHERE id_old = ?;

SELECT id_new
FROM staluc_final._mig_map_lineadeventa
WHERE id_old = ?;

-- Forma aplicada a libro y tesorería:
CASE
    WHEN LOWER(tabla) = 'tickets'
    THEN COALESCE(mapa_ticket.id_new, compasoc)
    ELSE compasoc
END;
```

### Desplazamiento de numeraciones

```sql
SELECT COALESCE(MAX(numasiento),0)
FROM staluc_old.librodiario;
-- Resultado: 6324

SELECT COALESCE(MAX(numerodeasiento),0)
FROM staluc_old.cccuentastesoreria;
-- Resultado: 10873

-- Al importar:
SELECT numasiento + 6324 FROM staluc_new.librodiario;
SELECT numerodeasiento + 10873 FROM staluc_new.cccuentastesoreria;
```

### Cajas y medios de pago

```sql
UPDATE staluc_final.cajaxusuario x
JOIN staluc_old.cajas oc ON oc.id = x.idcaja
JOIN staluc_new.cajas nc ON nc.numcaja = oc.numcaja
SET x.idcaja = nc.id;

UPDATE staluc_final.controlcajas x
JOIN staluc_old.cajas oc ON oc.id = x.idcaja
JOIN staluc_new.cajas nc ON nc.numcaja = oc.numcaja
SET x.idcaja = nc.id;

UPDATE staluc_final.mediosdepago
SET habilitado = 0
WHERE tipodecuenta = 'Caja Central';
```

### Colisión fiscal

```sql
ALTER TABLE staluc_final.tickets
DROP INDEX pdv_tipo_y_numero_de_comprobante;

ALTER TABLE staluc_final.tickets
ADD INDEX idx_pdv_tipo_numcomp (tipocomp,numcomp,PtoVta);
```

### Validaciones finales

```sql
SELECT fecha,COUNT(*),ROUND(SUM(total),2)
FROM staluc_final.tickets
WHERE fecha >= '2026-09-01'
GROUP BY fecha
ORDER BY fecha;

SELECT ROUND(SUM(debe),2),ROUND(SUM(haber),2),
       ROUND(SUM(debe)-SUM(haber),2)
FROM staluc_final.librodiario
WHERE id > (SELECT MAX(id) FROM staluc_old.librodiario);

SELECT COUNT(*) AS asientos_huerfanos
FROM staluc_final.librodiario l
LEFT JOIN staluc_final.tickets t ON t.id=l.compasoc
WHERE l.id > (SELECT MAX(id) FROM staluc_old.librodiario)
  AND LOWER(l.tabla)='tickets'
  AND t.id IS NULL;

SELECT COUNT(*) AS tesoreria_huerfana
FROM staluc_final.cccuentastesoreria c
LEFT JOIN staluc_final.tickets t ON t.id=c.compasoc
WHERE c.id > (SELECT MAX(id) FROM staluc_old.cccuentastesoreria)
  AND LOWER(c.tabla)='tickets'
  AND t.id IS NULL;

SELECT t.id,t.PtoVta,t.tipocomp,t.numcomp,t.lvdesde,t.lvhasta
FROM staluc_final.tickets t
LEFT JOIN staluc_final.lineadeventa a ON a.id=t.lvdesde
LEFT JOIN staluc_final.lineadeventa b ON b.id=t.lvhasta
WHERE t.lvdesde<>0
  AND (a.id IS NULL OR b.id IS NULL);
```

### Rutinas y triggers

La definición vigente se exportó desde `staluc_new` y se aplicó sobre
`staluc_final` con:

```powershell
& 'C:\xampp\mysql\bin\mysqldump.exe' -uroot `
  --no-data --no-create-info --routines --triggers `
  --skip-add-drop-table staluc_new |
& 'C:\xampp\mysql\bin\mysql.exe' -uroot staluc_final
```

Se validó su instalación mediante:

```sql
SELECT ROUTINE_SCHEMA,COUNT(*)
FROM information_schema.ROUTINES
WHERE ROUTINE_SCHEMA='staluc_final'
GROUP BY ROUTINE_SCHEMA;

SELECT TRIGGER_SCHEMA,COUNT(*)
FROM information_schema.TRIGGERS
WHERE TRIGGER_SCHEMA='staluc_final'
GROUP BY TRIGGER_SCHEMA;
```

### Comparación exacta del lote nuevo

```sql
SELECT COUNT(*),ROUND(SUM(ABS(n.total-f.total)),2),
       SUM(n.cae<>f.cae),SUM(n.numcomp<>f.numcomp),SUM(n.fecha<>f.fecha)
FROM staluc_new.tickets n
JOIN staluc_final._mig_map_tickets m ON m.id_old=n.id
JOIN staluc_final.tickets f ON f.id=m.id_new;

SELECT COUNT(*),ROUND(SUM(ABS(n.total-f.total)),2),
       SUM(n.prodcod<>f.prodcod),SUM(n.fecha<>f.fecha)
FROM staluc_new.lineadeventa n
JOIN staluc_final._mig_map_lineadeventa m ON m.id_old=n.id
JOIN staluc_final.lineadeventa f ON f.id=m.id_new;

CHECK TABLE staluc_final.tickets,
            staluc_final.lineadeventa,
            staluc_final.librodiario,
            staluc_final.cccuentastesoreria,
            staluc_final.cajaxusuario;
```

## Corrección de maestros y cajas aplicada el 05/09/2026

Antes de aplicar esta corrección se generó el respaldo recuperable
`sql/backups/staluc_final_antes_remapeo_20260905.sql`.

El SQL íntegro ejecutado está en `sql/corregir_staluc_final_remapeo.sql`. Las
operaciones de remapeo fueron:

```sql
USE staluc_final;

CREATE TABLE IF NOT EXISTS staluc_final._mig_map_mediosdepago (
    id_old INT PRIMARY KEY,
    id_new INT NOT NULL,
    UNIQUE KEY uq_medio_nuevo (id_new)
);

INSERT INTO staluc_final._mig_map_mediosdepago (id_old,id_new) VALUES
    (1,101),(2,102),(3,103),(4,104),(5,105),(6,106),(7,107),
    (11,111),(12,112),(13,113),(14,114),(15,115),(16,116),(17,117),(19,108)
ON DUPLICATE KEY UPDATE id_new=VALUES(id_new);

UPDATE staluc_final.auxticket d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.auxticket d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.cccuentastesoreria d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.idcuenta SET d.idcuenta=m.id_new;
UPDATE staluc_final.ccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.ccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago2 SET d.mediopago2=m.id_new;
UPDATE staluc_final.ccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.ccvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.factdecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.factdecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.lineadecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago22 SET d.mpago22=m.id_new;
UPDATE staluc_final.moviemientosccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.moviemientosccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.moviemientosccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.moviemientosccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.pagoaproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagoaproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagosprov d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.medio SET d.medio=m.id_new;
UPDATE staluc_final.pagosvende d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagosvende d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagosvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagosvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.recibos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.medio SET d.medio=m.id_new;
UPDATE staluc_final.rrhhcc d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediodepago SET d.mediodepago=m.id_new;
UPDATE staluc_final.rrhhpagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.rrhhpagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.tickets d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.tickets d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;

DELETE p
FROM staluc_final.mediosdepago p
JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=p.id;

UPDATE staluc_final.controlcajas c
JOIN staluc_old.controlcajas o ON o.id=c.id
SET c.estado=0,
    c.cerradael='2026-08-29 23:59:59',
    c.usuario_cierre='MIGRACION'
WHERE o.estado=1;
```

Validaciones ejecutadas después de la corrección:

```sql
SELECT COUNT(*)
FROM staluc_final.mediosdepago
WHERE id IN (1,2,3,4,5,6,7,11,12,13,14,15,16,17,19);
-- 0

SELECT medio,COUNT(*)
FROM staluc_final.mediosdepago
WHERE habilitado=1
GROUP BY UPPER(TRIM(medio))
HAVING COUNT(*)>1;
-- 0 filas

SELECT COUNT(*)
FROM staluc_new.cajas n
LEFT JOIN staluc_final.cajas f ON f.id=n.id
WHERE f.id IS NULL
   OR NOT(f.numcaja<=>n.numcaja)
   OR NOT(f.estado<=>n.estado)
   OR NOT(f.usuarioactivo<=>n.usuarioactivo)
   OR ABS(f.saldo-n.saldo)>0.001;
-- 0

SELECT COUNT(*)
FROM staluc_final.controlcajas c
LEFT JOIN staluc_final.cajas ca ON ca.id=c.idcaja
WHERE ca.id IS NULL;
-- 0

SELECT COUNT(*) AS tesoreria_sin_medio,
       ROUND(SUM(t.debe),2) AS debe,
       ROUND(SUM(t.haber),2) AS haber
FROM staluc_final.cccuentastesoreria t
LEFT JOIN staluc_final.mediosdepago m ON m.id=t.idcuenta
WHERE m.id IS NULL;
-- 0 | NULL | NULL

SELECT COUNT(*)
FROM staluc_final.cajaxusuario x
LEFT JOIN staluc_final.cajas ca ON ca.id=x.idcaja
LEFT JOIN staluc_final.controlcajas c ON c.id=x.idcontrolcaja
WHERE ca.id IS NULL OR c.id IS NULL;
-- 0

SELECT ROUND(SUM(debe),2),ROUND(SUM(haber),2),
       ROUND(SUM(debe)-SUM(haber),2)
FROM staluc_final.librodiario
WHERE id > (SELECT MAX(id) FROM staluc_old.librodiario);
-- 4546553.00 | 4546553.00 | 0.00

SELECT 'OLD',COUNT(*),ROUND(SUM(debe),2),ROUND(SUM(haber),2),
       ROUND(SUM(debe)-SUM(haber),2)
FROM staluc_old.librodiario
UNION ALL
SELECT 'NEW',COUNT(*),ROUND(SUM(debe),2),ROUND(SUM(haber),2),
       ROUND(SUM(debe)-SUM(haber),2)
FROM staluc_new.librodiario
UNION ALL
SELECT 'FINAL',COUNT(*),ROUND(SUM(debe),2),ROUND(SUM(haber),2),
       ROUND(SUM(debe)-SUM(haber),2)
FROM staluc_final.librodiario;

SELECT COUNT(*)
FROM (
    SELECT numasiento
    FROM staluc_old.librodiario
    GROUP BY numasiento
    HAVING ABS(SUM(debe)-SUM(haber))>0.009
) asientos_desbalanceados;
-- 534

SELECT SUM(ABS(f.debe-o.debe)>0.000001),
       SUM(ABS(f.haber-o.haber)>0.000001),
       ROUND(SUM(f.debe-o.debe),6),
       ROUND(SUM(f.haber-o.haber),6)
FROM staluc_old.librodiario o
JOIN staluc_final.librodiario f ON f.id=o.id;
-- 137 | 7856 | -0.039347 | 1.197983
```

## Corrección de numeración fiscal del 01/09/2026 aplicada el 05/09/2026

### Diagnóstico

El cliente reportó que la numeración de los primeros comprobantes de
septiembre no respetaba la correlatividad de agosto. Se comparó el último
número usado en legacy contra la numeración del sistema nuevo, por
PV/tipo de comprobante:

| Tipo | Último legacy (29/08) | Primeros del 01/09 (mal grabados) | Resto (02/09 en adelante) |
|---|---:|---|---|
| Factura A, PV4 | 4150 | 1, 2, 3, 4 | 4155...4168 (continua sin huecos) |
| Factura B, PV4 | 8675 | 1, 2, 3, 5...25 | 8701...8763 (continua sin huecos) |

En ambas series el resto de los comprobantes retoma exactamente en
`último_legacy + cantidad_del_01/09 + 1` (4150+4+1=4155 y 8675+25+1=8701),
lo que indica que ARCA sí asignó la numeración correlativa real durante el
01/09, pero el sistema grabó localmente un contador reiniciado desde 1 para
esos primeros comprobantes — bug que dejó de ocurrir el mismo día (no vuelve
a pasar desde el 02/09). El cliente confirmó contra ARCA que la numeración
real es la correlativa; no era una colisión fiscal genuina.

Comprobantes afectados: 4 Facturas A (numcomp local 1-4) y 25 Facturas B
(numcomp local 1-25), todos PV4, fecha 2026-09-01, IDs finales 9558-9586.

### Relevamiento de tablas con `numcomp`

Se relevaron las 15 tablas del esquema con columna `numcomp`
(`auxlineadeventa`, `auxticket`, `controlstock`, `ingresosdestock`,
`lineadecompra`, `lineadepedido`, `lineadescuentos`, `lineadeventa`,
`pagoaproveedores`, `pagosprov`, `pagosvende`, `pedidos`,
`promociones_ventas`, `tickets`, `xmlarca`). Solo `tickets` y `lineadeventa`
tenían filas para estos 29 comprobantes: las 106 ventas nuevas son 100%
línea manual (código `LM`), sin movimiento de stock ni promociones, así que
`controlstock`/`promociones_ventas`/etc. no tenían nada que corregir.
`auxticket`/`auxlineadeventa` (cola de comprobantes fallidos) no se tocaron
por no tener relación con estos 29 comprobantes exitosos. `xmlarca` tiene
filas con `numcomp` bajo pero son de 2025, ajenas a esta corrección.

Antes de corregir se generó el respaldo
`sql/backups/staluc_final_antes_renumeracion_01092026_20260905.sql`
(tablas `tickets` y `lineadeventa`).

El SQL ejecutado está en `sql/corregir_staluc_final_numeracion_01092026.sql`:

```sql
UPDATE staluc_final.tickets
SET numcomp = numcomp + 4150
WHERE PtoVta = 4 AND tipocomp = 1 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 4;

UPDATE staluc_final.lineadeventa
SET numcomp = numcomp + 4150
WHERE pdv = 4 AND Tipocomp = 1 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 4;

UPDATE staluc_final.tickets
SET numcomp = numcomp + 8675
WHERE PtoVta = 4 AND tipocomp = 6 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 25;

UPDATE staluc_final.lineadeventa
SET numcomp = numcomp + 8675
WHERE pdv = 4 AND Tipocomp = 6 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 25;
```

### Validaciones ejecutadas después de la corrección

```sql
SELECT tipocomp, MIN(numcomp), MAX(numcomp), COUNT(*), COUNT(DISTINCT numcomp)
FROM staluc_final.tickets WHERE PtoVta=4 AND tipocomp IN (1,6) GROUP BY tipocomp;
-- 1 | 2074 | 4168 | 2095 | 2095   (sin huecos: 4168-2074+1 = 2095)
-- 6 | 1    | 8763 | 7072 | 7072   (sin huecos en el tramo migrado)

SELECT PtoVta, tipocomp, numcomp, COUNT(*)
FROM staluc_final.tickets GROUP BY PtoVta, tipocomp, numcomp HAVING COUNT(*)>1;
-- 0 filas: no queda ningún (PtoVta,tipocomp,numcomp) duplicado en toda la tabla

SELECT id, PtoVta, tipocomp, numcomp, fecha, cae
FROM staluc_final.tickets WHERE PtoVta=4 AND tipocomp=6 AND numcomp=1;
-- Solo el legacy ID 7509 (18/05/2026). El ex-9558 ahora es 8676: la colisión
-- original desapareció sin haber tocado ningún dato de agosto.
```

### Restablecimiento del índice único (05/09/2026)

Con la colisión resuelta, se restableció el índice único
`pdv_tipo_y_numero_de_comprobante` (`tipocomp`,`numcomp`,`PtoVta`) en
`tickets`, reemplazando al `idx_pdv_tipo_numcomp` no único que se había
dejado como parche temporal:

```sql
ALTER TABLE staluc_final.tickets DROP INDEX idx_pdv_tipo_numcomp;
ALTER TABLE staluc_final.tickets
    ADD UNIQUE KEY pdv_tipo_y_numero_de_comprobante (tipocomp,numcomp,PtoVta) USING BTREE;
```

Validado en tres niveles:

- El diff completo de índices entre `staluc_new` y `staluc_final` (todas las
  tablas) volvió a dar cero diferencias — la estructura de `staluc_final`
  vuelve a ser 100% idéntica a la de `staluc_new`, sin ninguna excepción.
- Un `INSERT` de prueba con `(PtoVta=4, tipocomp=6, numcomp=8676)` —el número
  que hoy ocupa el ex-ticket 9558— fue rechazado por la base:
  `ERROR 1062: Duplicate entry '6-8676-4' for key 'pdv_tipo_y_numero_de_comprobante'`.
  No quedó ningún dato de la prueba.
- La red de seguridad a nivel de base de datos contra comprobantes fiscales
  duplicados vuelve a estar activa para cualquier emisión futura.
