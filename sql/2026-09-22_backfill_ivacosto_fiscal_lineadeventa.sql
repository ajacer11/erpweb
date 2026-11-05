-- ============================================================================
-- 2026-09-22 - Recomponer fiscal e ivacosto en Lineadeventa (histórico)
-- ============================================================================
--
-- MOTIVO
--   El INSERT de api_facturar.php no enviaba las columnas `fiscal` ni
--   `ivacosto`, y las dos tienen DEFAULT 0. Resultado: toda venta histórica
--   quedó marcada como no fiscal y con IVA de compra en cero.
--
--   El reporte de ventas calcula la utilidad como
--       (Total - IVA) - (Costo - IVA compra)
--   y `costo` se graba como preciocosto x cantidad, o sea CON el IVA adentro
--   (en alb_productos vale preciocosto = sinivacosto + ivacosto + ImpInt).
--   Con ivacosto en 0 se resta el costo completo sin devolver el crédito
--   fiscal, y la utilidad sale más baja de lo real.
--
--   El código ya está arreglado: las ventas nuevas graban bien las dos
--   columnas. Este script es sólo para los comprobantes ya grabados.
--
-- CÓMO CORRERLO
--   Sección por sección, mirando el resultado de cada una antes de seguir.
--       mysql -u USUARIO -p NOMBRE_BASE
--   Correrlo UNA VEZ POR CADA BASE DE TENANT que tenga el problema (la
--   sección 1 te dice si esa base está afectada).
--
--   Si te aparece este error en el CREATE TABLE de la sección 0:
--       #1044 - Access denied for user 'root'@'localhost'
--                to database 'information_schema'
--   NO es falta de permisos ni que te hayas olvidado de elegir la base.
--   Es un comportamiento de phpMyAdmin: cuando en el mismo lote ejecutás una
--   consulta cuyo FROM es information_schema, phpMyAdmin reapunta la base
--   activa a information_schema para las sentencias siguientes, y el CREATE
--   (que no lleva base delante) termina apuntando ahí. Ya está resuelto: la
--   sección 0 usa SHOW TABLES en lugar de information_schema.
--   Si igual lo ves, ejecutá la sección 0 sola, seleccionándola en el editor.
--
--   Por las dudas, control de base activa (tiene que devolver el tenant):
-- SELECT DATABASE() AS base_activa;
--
--   En Linux los nombres de tabla distinguen mayúsculas: acá se usa
--   `Lineadeventa`, que es como la nombra el código. Si en tu VPS quedó como
--   `lineadeventa`, reemplazá en todo el archivo.
--
-- SI USÁS phpMyAdmin
--   Las secciones 2 y 3 usan procedimientos con DELIMITER para actualizar por
--   lotes. phpMyAdmin es caprichoso con DELIMITER dentro del textarea. Si te
--   da error de sintaxis, saltealos y usá las versiones SIN procedimiento que
--   están al final de cada una de esas secciones: son un único UPDATE, hacen
--   exactamente lo mismo y son igual de idempotentes. La única diferencia es
--   que bloquean la tabla mientras corren, así que conviene hacerlo fuera del
--   horario de venta si la tabla es grande.
--
-- IDEMPOTENCIA
--   Las secciones 2 y 3 (los UPDATE) SÍ son idempotentes: sólo tocan filas que
--   todavía están mal (`fiscal <> el valor que corresponde`, `ivacosto = 0`).
--   Correrlas dos veces no cambia nada la segunda vez, y no acumulan.
--
--   La sección 0 (respaldo) NO lo es, y es la que hay que cuidar: si volvés a
--   correr el archivo entero, el CREATE TABLE falla porque el respaldo ya
--   existe, y el script se corta ahí. Eso es intencional — sin esa traba, el
--   segundo respaldo se tomaría sobre los datos ya corregidos y perderías el
--   rollback de la sección 6.
--
--   O sea: correr el archivo dos veces es SEGURO (no corrompe datos), pero la
--   segunda corrida no avanza. Si querés re-correrlo de verdad, primero
--   decidí qué hacer con el respaldo existente.
--
-- QUÉ NO TOCA
--   - Ningún importe de venta (siniva, iva, ii, total, costo quedan igual).
--   - Las filas de ingreso de stock (cliente = 'INGRESO DE STOCK'), que
--     graban `fiscal` a mano desde la pantalla de ingreso y donde el valor
--     actual ES el correcto.
--   - Productos cuya apertura de costo hoy no cierra (ver sección 3).
-- ============================================================================


-- ============================================================================
-- SECCIÓN 0 - BACKUP. No es opcional. Corré esto primero.
-- ============================================================================
-- Guarda sólo las dos columnas que se van a tocar, así que es chico y rápido.
-- La sección 6 restaura desde acá si algo sale mal.
--
-- OJO: acá NO hay DROP TABLE, a propósito. Si el respaldo ya existe, el CREATE
-- falla y el script se corta. Es la protección contra correrlo dos veces: un
-- segundo respaldo se tomaría sobre datos YA corregidos y te quedarías sin
-- vuelta atrás. Si necesitás correrlo de nuevo a propósito, cambiá el sufijo
-- de fecha del nombre en TODO el archivo; el respaldo original queda intacto.

-- Control previo: si esto devuelve una fila, ya corriste el script en esta
-- base. Pará y leé la nota de IDEMPOTENCIA del encabezado antes de seguir.
-- Si no devuelve nada, seguí.
--
-- (Se usa SHOW TABLES y no information_schema a propósito: phpMyAdmin, después
--  de ejecutar una consulta cuyo FROM es information_schema, reapunta la base
--  activa a information_schema para el resto del lote, y entonces el CREATE de
--  abajo intenta crear la tabla ahí y falla con
--      #1044 - Access denied ... to database 'information_schema'
--  aunque tengas bien seleccionada la base del tenant. SHOW TABLES no tiene
--  ese efecto.)
SHOW TABLES LIKE 'zz_backup_lineadeventa_20260922';

CREATE TABLE zz_backup_lineadeventa_20260922 AS
SELECT id, fiscal, ivacosto FROM Lineadeventa;

ALTER TABLE zz_backup_lineadeventa_20260922 ADD PRIMARY KEY (id);

-- Control: los dos números tienen que ser iguales.
SELECT (SELECT COUNT(*) FROM Lineadeventa)                      AS filas_originales,
       (SELECT COUNT(*) FROM zz_backup_lineadeventa_20260922)   AS filas_backup;


-- ============================================================================
-- SECCIÓN 1 - DIAGNÓSTICO. ¿Esta base está afectada?
-- ============================================================================
-- Si `ventas_fiscal_cero` es 0 y `ventas_sin_ivacosto` es 0, esta base ya está
-- bien y no hace falta seguir.

SELECT COUNT(*)                                              AS filas_totales,
       SUM(cliente REGEXP '^[1-9][0-9]*$')                   AS filas_de_venta,
       SUM(cliente = 'INGRESO DE STOCK')                     AS filas_ingreso_stock,
       SUM(cliente REGEXP '^[1-9][0-9]*$' AND fiscal = 0)    AS ventas_fiscal_cero,
       SUM(cliente REGEXP '^[1-9][0-9]*$'
           AND COALESCE(ivacosto,0) = 0
           AND COALESCE(costo,0) <> 0)                       AS ventas_sin_ivacosto
FROM Lineadeventa;

-- Las filas de ingreso de stock tienen fiscal elegido por el usuario en la
-- pantalla de ingreso, y ahí conviven 1, 0 y -1 (el -1 viene del sistema
-- viejo). Se listan para que veas que NO se van a tocar: el valor que tienen
-- hoy ES el correcto.
SELECT fiscal, COUNT(*) AS filas
FROM Lineadeventa
WHERE cliente = 'INGRESO DE STOCK'
GROUP BY fiscal;


-- ============================================================================
-- SECCIÓN 2 - fiscal. Esta parte es EXACTA.
-- ============================================================================
-- Depende sólo del tipo de comprobante: únicamente el ticket interno (100) y
-- el ticket de crédito (101) son no fiscales. Mismo criterio que usa
-- api_comprobantes_emitidos.php para separar fiscales de no fiscales.
--
-- Se limita a las filas de venta (cliente numérico) para no pisar el `fiscal`
-- que la pantalla de ingreso de stock graba a mano.

-- Vista previa: qué va a cambiar.
SELECT Tipocomp,
       COUNT(*)                                        AS filas,
       SUM(fiscal = 0)                                 AS hoy_no_fiscal,
       CASE WHEN Tipocomp IN (100,101) THEN 0 ELSE 1 END AS deberia_ser
FROM Lineadeventa
WHERE cliente REGEXP '^[1-9][0-9]*$'
  AND Tipocomp IN (1,2,3,6,7,8,11,12,13,100,101)
GROUP BY Tipocomp
ORDER BY Tipocomp;

-- Si arriba aparece algún Tipocomp raro (0, u otro fuera de la lista), queda
-- afuera a propósito: son filas que el informe de ventas tampoco toma.

-- Aplicar (por lotes, para no bloquear la tabla en bases grandes):
DROP PROCEDURE IF EXISTS zz_backfill_fiscal;
DELIMITER //
CREATE PROCEDURE zz_backfill_fiscal()
BEGIN
    DECLARE v_desde BIGINT DEFAULT 0;
    DECLARE v_max   BIGINT DEFAULT 0;
    DECLARE v_lote  BIGINT DEFAULT 5000;

    SELECT IFNULL(MAX(id),0) INTO v_max FROM Lineadeventa;

    WHILE v_desde <= v_max DO
        UPDATE Lineadeventa
        SET fiscal = CASE WHEN Tipocomp IN (100,101) THEN 0 ELSE 1 END
        WHERE id > v_desde AND id <= v_desde + v_lote
          AND cliente REGEXP '^[1-9][0-9]*$'
          AND Tipocomp IN (1,2,3,6,7,8,11,12,13,100,101)
          AND fiscal <> CASE WHEN Tipocomp IN (100,101) THEN 0 ELSE 1 END;
        COMMIT;
        SET v_desde = v_desde + v_lote;
    END WHILE;
END //
DELIMITER ;

CALL zz_backfill_fiscal();
DROP PROCEDURE zz_backfill_fiscal;

-- --- Alternativa para phpMyAdmin (si DELIMITER te da error) -----------------
-- Hace lo mismo en una sola pasada, sin procedimiento. Corré SÓLO una de las
-- dos variantes, no las dos.
--
-- UPDATE Lineadeventa
-- SET fiscal = CASE WHEN Tipocomp IN (100,101) THEN 0 ELSE 1 END
-- WHERE cliente REGEXP '^[1-9][0-9]*$'
--   AND Tipocomp IN (1,2,3,6,7,8,11,12,13,100,101)
--   AND fiscal <> CASE WHEN Tipocomp IN (100,101) THEN 0 ELSE 1 END;


-- ============================================================================
-- SECCIÓN 3 - ivacosto. ATENCIÓN: esto es una ESTIMACIÓN, no un dato recuperado.
-- ============================================================================
-- El costo histórico de cada línea sí está guardado (Lineadeventa.costo =
-- preciocosto x cantidad al momento de la venta; es el TOTAL de la línea, no
-- el unitario), pero la APERTURA de ese costo (neto / IVA / impuesto interno)
-- no se guardó nunca. Lo único que hay es la estructura de costos ACTUAL del
-- producto, donde tanto sinivacosto como ivacosto y preciocosto son UNITARIOS.
--
-- Se aplica la PROPORCIÓN actual ivacosto/preciocosto sobre el costo histórico:
--     ivacosto_linea = costo_linea x (ivacosto / preciocosto)
--
-- Usar la proporción y no los importes tiene dos ventajas:
--   - Es inmune a la inflación: no importa cuánto subió el costo desde la
--     venta, mientras la estructura no haya cambiado.
--   - Da directamente el total de la línea, porque `costo` ya viene
--     multiplicado por la cantidad. No hay que volver a multiplicar.
--
-- Lo único que asume es que la ALÍCUOTA del producto no cambió desde la venta.
-- Si a un producto le cambió el IVA, su historial queda aproximado.
--
-- Si preferís no estimar sobre datos contables, SALTEÁ esta sección: las
-- ventas nuevas ya graban el valor correcto y el reporte será exacto de acá en
-- adelante. El pasado queda como está.
--
-- NOTA: las filas migradas del sistema viejo ya traen ivacosto cargado y no se
-- tocan (la condición es ivacosto = 0). El bug afecta sólo a los comprobantes
-- emitidos por el sistema nuevo.

-- Productos con la apertura de costo rota: el IVA guardado es MAYOR que el
-- máximo posible para su alícuota. Se EXCLUYEN del backfill porque su
-- proporción no es confiable. Revisalos si son muchos.
--
-- (El control es por alícuota y no "sinivacosto + ivacosto + ImpInt =
--  preciocosto" a propósito: hay bases donde preciocosto incluye el impuesto
--  interno y otras donde no. Eso da igual acá, porque `costo` se calculó con
--  ESE mismo preciocosto, así que la proporción se sostiene en los dos casos.)
SELECT p.codigo, p.rubro, p.poriva, p.sinivacosto, p.ivacosto, p.ImpInt, p.preciocosto,
       ROUND(p.ivacosto / p.preciocosto, 4)                   AS proporcion_hoy,
       ROUND(p.poriva / (100 + p.poriva), 4)                  AS maximo_posible
FROM alb_productos p
WHERE p.preciocosto > 0 AND p.ivacosto > 0 AND p.poriva > 0
  AND p.ivacosto > p.preciocosto * p.poriva / (100 + p.poriva) * 1.01
ORDER BY p.ivacosto / p.preciocosto DESC;

-- Vista previa: cuántas filas se tocan y cuánto IVA de compra se imputa.
SELECT COUNT(*)                                                  AS filas_a_tocar,
       ROUND(SUM(l.costo * (p.ivacosto / p.preciocosto)), 2)      AS ivacosto_a_imputar
FROM Lineadeventa l
JOIN alb_productos p ON p.codigo = l.prodcod
WHERE COALESCE(l.ivacosto,0) = 0
  AND COALESCE(l.costo,0) <> 0
  AND p.preciocosto > 0
  AND p.ivacosto > 0
  AND p.poriva > 0
  AND p.ivacosto <= p.preciocosto * p.poriva / (100 + p.poriva) * 1.01;

-- Aplicar (por lotes; Lineadeventa.prodcod no tiene índice, así que el lote
-- evita recorrer cientos de miles de filas en una sola transacción):
DROP PROCEDURE IF EXISTS zz_backfill_ivacosto;
DELIMITER //
CREATE PROCEDURE zz_backfill_ivacosto()
BEGIN
    DECLARE v_desde BIGINT DEFAULT 0;
    DECLARE v_max   BIGINT DEFAULT 0;
    DECLARE v_lote  BIGINT DEFAULT 5000;

    SELECT IFNULL(MAX(id),0) INTO v_max FROM Lineadeventa;

    WHILE v_desde <= v_max DO
        UPDATE Lineadeventa l
        JOIN alb_productos p ON p.codigo = l.prodcod
        SET l.ivacosto = ROUND(l.costo * (p.ivacosto / p.preciocosto), 2)
        WHERE l.id > v_desde AND l.id <= v_desde + v_lote
          AND COALESCE(l.ivacosto,0) = 0
          AND COALESCE(l.costo,0) <> 0
          AND p.preciocosto > 0
          AND p.ivacosto > 0
          AND p.poriva > 0
          AND p.ivacosto <= p.preciocosto * p.poriva / (100 + p.poriva) * 1.01;
        COMMIT;
        SET v_desde = v_desde + v_lote;
    END WHILE;
END //
DELIMITER ;

CALL zz_backfill_ivacosto();
DROP PROCEDURE zz_backfill_ivacosto;

-- --- Alternativa para phpMyAdmin (si DELIMITER te da error) -----------------
-- Hace lo mismo en una sola pasada, sin procedimiento. Corré SÓLO una de las
-- dos variantes, no las dos.
--
-- UPDATE Lineadeventa l
-- JOIN alb_productos p ON p.codigo = l.prodcod
-- SET l.ivacosto = ROUND(l.costo * (p.ivacosto / p.preciocosto), 2)
-- WHERE COALESCE(l.ivacosto,0) = 0
--   AND COALESCE(l.costo,0) <> 0
--   AND p.preciocosto > 0
--   AND p.ivacosto > 0
--   AND p.poriva > 0
--   AND p.ivacosto <= p.preciocosto * p.poriva / (100 + p.poriva) * 1.01;


-- ============================================================================
-- SECCIÓN 4 - VERIFICACIÓN
-- ============================================================================

-- 4.a  Nada quedó incoherente: el IVA de compra nunca puede superar al costo,
--      y ninguna fila de ingreso de stock cambió de fiscalidad.
SELECT
  (SELECT COUNT(*) FROM Lineadeventa
    WHERE COALESCE(ivacosto,0) <> 0 AND ABS(ivacosto) > ABS(COALESCE(costo,0)))  AS ivacosto_mayor_que_costo,
  (SELECT COUNT(*) FROM Lineadeventa l
     JOIN zz_backup_lineadeventa_20260922 b ON b.id = l.id
    WHERE l.cliente = 'INGRESO DE STOCK' AND l.fiscal <> b.fiscal)               AS ingresos_stock_tocados;
-- Los dos tienen que dar 0.

-- 4.b  Cuánto se movió la utilidad. Cambiá las fechas por el período que mirás
--      en el informe de ventas.
SELECT
  ROUND(SUM(CASE WHEN Tipocomp IN (3,8,13,101) THEN -1 ELSE 1 END
            * (total - iva - (costo - b.ivacosto))), 2) AS utilidad_antes,
  ROUND(SUM(CASE WHEN Tipocomp IN (3,8,13,101) THEN -1 ELSE 1 END
            * (total - iva - (costo - l.ivacosto))), 2) AS utilidad_ahora
FROM Lineadeventa l
JOIN zz_backup_lineadeventa_20260922 b ON b.id = l.id
WHERE l.cliente REGEXP '^[1-9][0-9]*$'
  AND l.fecha BETWEEN '2026-09-01' AND '2026-09-30'
  AND l.Tipocomp IN (1,2,3,6,7,8,11,12,13,100,101);


-- ============================================================================
-- SECCIÓN 5 - LIMPIEZA (recién cuando ya validaste el reporte en pantalla)
-- ============================================================================
-- DROP TABLE zz_backup_lineadeventa_20260922;


-- ============================================================================
-- SECCIÓN 6 - VOLVER ATRÁS (si algo salió mal)
-- ============================================================================
-- Restaura exactamente los valores previos de las dos columnas.
--
-- UPDATE Lineadeventa l
-- JOIN zz_backup_lineadeventa_20260922 b ON b.id = l.id
-- SET l.fiscal = b.fiscal, l.ivacosto = b.ivacosto;
