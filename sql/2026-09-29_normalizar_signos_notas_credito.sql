-- ============================================================================
-- 2026-09-29 - Normalizar los signos de las notas de crédito en Lineadeventa
-- ============================================================================
--
-- MOTIVO
--   Una nota de crédito (3 NCA, 8 NCB, 13 NCC) o un ticket de crédito (101) se
--   graba hoy con signos MEZCLADOS: `cant`, `ii`, `costo` e `ivacosto` quedan
--   negativos, pero `siniva`, `iva`, `total` e `IIBB` quedan positivos.
--
--   Eso obliga a cada consumidor a saber cuáles invertir y cuáles no, y ya rompe
--   la impresión del comprobante. imprimir_ticket.php calcula:
--       $netoTotalL = $totalL - $impIntL - $ivaL;
--       $netoU      = $netoTotalL / $cant;
--   Con los signos mezclados, una NC real de 1 unidad a $600 imprime:
--       neto calculado 889,06 (debería ser 218,98)
--       neto unitario  -889,06      final unitario -600,00   <- precios negativos
--   Con todo en negativo, esa misma línea imprime neto unitario 218,97 y final
--   unitario 600,00, que es lo correcto.
--
--   Este script deja TODAS las columnas de importe y cantidad de las NC en
--   negativo. Regla: si es > 0, se multiplica por -1. Lo que ya está negativo
--   no se toca.
--
-- IMPORTANTE - HAY QUE DESPLEGAR api_reporte_ventas.php JUNTO CON ESTO
--   El informe de ventas invertía `total`, `iva`, `siniva` e `IIBB` para
--   compensar los signos mezclados. Si normalizás la base y dejás el informe
--   viejo, esas cuatro columnas van a salir POSITIVAS en las NC.
--   La versión nueva del informe usa -ABS() y funciona con la base normalizada
--   Y con la vieja, así que podés desplegarla antes o después, sin apuro.
--
-- CÓMO CORRERLO
--   Sección por sección, mirando el resultado antes de seguir.
--   Una vez por cada base de tenant.
--   En phpMyAdmin: hacé clic en la base del tenant ANTES de abrir la pestaña SQL.
--
-- QUÉ NO TOCA (a propósito)
--   - `unitariofinallista`: es un PRECIO UNITARIO. En una NC de 30 unidades a
--     $1.350, el unitario sigue siendo 1.350, no -1.350. Va positivo.
--   - `totaldescuento`: 13 filas positivas por base. Ver la sección 4.
--   - Las líneas DESC y RECA: llevan el signo OPUESTO al del comprobante. Ver
--     la sección 3.
--   - Facturas, notas de débito, tickets normales: no se tocan en absoluto.
--
-- IDEMPOTENCIA
--   Las secciones 2 y 3 sólo tocan filas con el signo equivocado, así que
--   correrlas de nuevo no cambia nada. La sección 0 se traba si el respaldo ya
--   existe (es a propósito: un segundo respaldo se tomaría sobre datos ya
--   normalizados y perderías la vuelta atrás).
-- ============================================================================


-- ============================================================================
-- SECCIÓN 0 - RESPALDO. No es opcional.
-- ============================================================================
-- Si esto devuelve una fila, ya corriste el script en esta base: pará.
SHOW TABLES LIKE 'zz_backup_signos_nc_20260929';

CREATE TABLE zz_backup_signos_nc_20260929 AS
SELECT id, cant, siniva, iva, ii, total, IIBB, costo, ivacosto
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101);

ALTER TABLE zz_backup_signos_nc_20260929 ADD PRIMARY KEY (id);

-- Control: los dos números tienen que ser iguales.
SELECT (SELECT COUNT(*) FROM Lineadeventa WHERE Tipocomp IN (3,8,13,101)) AS filas_nc,
       (SELECT COUNT(*) FROM zz_backup_signos_nc_20260929)                AS filas_respaldo;


-- ============================================================================
-- SECCIÓN 1 - DIAGNÓSTICO. Cuántas filas están mal en esta base.
-- ============================================================================
SELECT COUNT(*)              AS lineas_nc,
       SUM(cant     > 0)     AS cant_a_invertir,
       SUM(siniva   > 0)     AS siniva_a_invertir,
       SUM(iva      > 0)     AS iva_a_invertir,
       SUM(ii       > 0)     AS ii_a_invertir,
       SUM(total    > 0)     AS total_a_invertir,
       SUM(IIBB     > 0)     AS iibb_a_invertir,
       SUM(costo    > 0)     AS costo_a_invertir,
       SUM(ivacosto > 0)     AS ivacosto_a_invertir
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101)
  AND prodcod NOT IN ('DESC', 'RECA');


-- ============================================================================
-- SECCIÓN 2 - NORMALIZAR. Todo lo positivo pasa a negativo.
-- ============================================================================
-- Cada columna se trata por separado: las que ya están negativas no se tocan.
-- Se excluyen DESC y RECA, que van al revés (sección 3).

UPDATE Lineadeventa
SET cant     = CASE WHEN cant     > 0 THEN cant     * -1 ELSE cant     END,
    siniva   = CASE WHEN siniva   > 0 THEN siniva   * -1 ELSE siniva   END,
    iva      = CASE WHEN iva      > 0 THEN iva      * -1 ELSE iva      END,
    ii       = CASE WHEN ii       > 0 THEN ii       * -1 ELSE ii       END,
    total    = CASE WHEN total    > 0 THEN total    * -1 ELSE total    END,
    IIBB     = CASE WHEN IIBB     > 0 THEN IIBB     * -1 ELSE IIBB     END,
    costo    = CASE WHEN costo    > 0 THEN costo    * -1 ELSE costo    END,
    ivacosto = CASE WHEN ivacosto > 0 THEN ivacosto * -1 ELSE ivacosto END
WHERE Tipocomp IN (3, 8, 13, 101)
  AND prodcod NOT IN ('DESC', 'RECA')
  AND (cant > 0 OR siniva > 0 OR iva > 0 OR ii > 0
       OR total > 0 OR IIBB > 0 OR costo > 0 OR ivacosto > 0);


-- ============================================================================
-- SECCIÓN 3 - LÍNEAS DESC / RECA. OPCIONAL: leé esto antes de aplicar.
-- ============================================================================
-- Un descuento lleva el signo CONTRARIO al del comprobante: en una factura las
-- líneas son positivas y el DESC negativo (resta). En una NC ya normalizada las
-- líneas son negativas, así que el DESC tiene que ser POSITIVO para restarle
-- magnitud al crédito. Hoy está negativo, o sea que suma en vez de restar.
--
-- Son pocas filas. Mirá cuáles son antes de decidir:
SELECT id, fecha, Tipocomp, numcomp, prodcod, siniva, iva, total
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101) AND prodcod IN ('DESC', 'RECA')
ORDER BY fecha DESC;

-- Si estás de acuerdo, descomentá y aplicá:
-- UPDATE Lineadeventa
-- SET siniva = CASE WHEN siniva < 0 THEN siniva * -1 ELSE siniva END,
--     iva    = CASE WHEN iva    < 0 THEN iva    * -1 ELSE iva    END,
--     total  = CASE WHEN total  < 0 THEN total  * -1 ELSE total  END
-- WHERE Tipocomp IN (3, 8, 13, 101) AND prodcod IN ('DESC', 'RECA')
--   AND (siniva < 0 OR iva < 0 OR total < 0);


-- ============================================================================
-- SECCIÓN 4 - VERIFICACIÓN
-- ============================================================================

-- 4.a  No tiene que quedar nada positivo en las líneas de producto de NC.
SELECT SUM(cant > 0)     AS cant_pos,
       SUM(siniva > 0)   AS siniva_pos,
       SUM(iva > 0)      AS iva_pos,
       SUM(ii > 0)       AS ii_pos,
       SUM(total > 0)    AS total_pos,
       SUM(IIBB > 0)     AS iibb_pos,
       SUM(costo > 0)    AS costo_pos,
       SUM(ivacosto > 0) AS ivacosto_pos
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101) AND prodcod NOT IN ('DESC', 'RECA');
-- Los ocho tienen que dar 0 (o NULL si la base no tiene NC).

-- 4.b  Coherencia interna: total = siniva + iva + ii en cada línea.
--      Esto ya fallaba antes en algunas filas; lo que importa es que el número
--      NO haya empeorado respecto de lo que había.
SELECT COUNT(*) AS lineas_nc_descuadradas
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101) AND prodcod NOT IN ('DESC', 'RECA')
  AND ABS(total - (siniva + iva + ii)) >= 0.02;

-- 4.c  Cómo queda la impresión del comprobante (imprimir_ticket.php).
--      El neto y el final UNITARIOS tienen que dar POSITIVOS.
SELECT id, cant, siniva, iva, ii, total,
       ROUND((total - ii - iva) / NULLIF(cant,0), 2) AS neto_unitario_impreso,
       ROUND(total / NULLIF(cant,0), 2)             AS final_unitario_impreso
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101) AND prodcod NOT IN ('DESC', 'RECA')
  AND cant <> 0
ORDER BY id DESC LIMIT 10;

-- 4.d  `totaldescuento`: quedó como estaba. Si estas filas te molestan,
--      decidilo aparte; no entra en la normalización.
SELECT COUNT(*) AS filas_con_totaldescuento_positivo
FROM Lineadeventa
WHERE Tipocomp IN (3, 8, 13, 101) AND totaldescuento > 0;


-- ============================================================================
-- SECCIÓN 5 - LIMPIEZA (recién cuando validaste informe e impresión)
-- ============================================================================
-- DROP TABLE zz_backup_signos_nc_20260929;


-- ============================================================================
-- SECCIÓN 6 - VOLVER ATRÁS
-- ============================================================================
-- UPDATE Lineadeventa l
-- JOIN zz_backup_signos_nc_20260929 b ON b.id = l.id
-- SET l.cant = b.cant, l.siniva = b.siniva, l.iva = b.iva, l.ii = b.ii,
--     l.total = b.total, l.IIBB = b.IIBB, l.costo = b.costo,
--     l.ivacosto = b.ivacosto;
