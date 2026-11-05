-- ============================================================================
-- 2026-09-30 - Corregir la fiscalidad de los recibos de cobranza a clientes
-- ============================================================================
--
-- MOTIVO
--   Los recibos viejos se grabaron con una fiscalidad elegida a mano (casi
--   siempre "no fiscal"), de cuando el sistema todavía no la deducía del
--   comprobante imputado. Hoy api_cobros.php la deriva así:
--
--       $sqlFiscal = "SELECT id, (tipocomp < 100) AS fiscal FROM tickets ...";
--       $esFis = fiscalUnicoDeFilas(...);
--
--   O sea: fiscal = 1 si el comprobante imputado es de ARCA (tipocomp 1, 2, 3,
--   6, 7, 8, 11, 12, 13) y fiscal = 0 si es ticket interno (100) o ticket de
--   crédito (101). Y fiscalUnicoDeFilas() además rechaza mezclar fiscales con
--   no fiscales en un mismo recibo.
--
--   Este script aplica esa MISMA regla al historial.
--
-- LA FISCALIDAD DEL RECIBO VIVE EN TRES TABLAS
--   api_cobros.php escribe el mismo $esFis en:
--       ccclientes          (cuenta corriente del cliente)
--       cccuentastesoreria  (movimiento de tesorería, 1 o 2 filas)
--       librodiario         (asiento contable, varias líneas)
--   Las tres se identifican por tabla = 'pagos' y compasoc = pagos.id.
--   Corregir sólo una dejaría los informes peleados entre sí. De hecho HOY ya
--   hay recibos donde no coinciden; la sección 1 te dice cuántos.
--
-- QUÉ NO PUEDE CORREGIR (y por qué)
--   - Recibos SIN imputar (cobros a cuenta): no hay comprobante del cual
--     deducir la fiscalidad. Quedan como están.
--   - Recibos imputados a comprobantes fiscales Y no fiscales a la vez: son
--     ambiguos. El sistema actual ya no los permite, pero los viejos existen.
--     Quedan como están y se listan en la sección 2 para que los revises.
--
-- CÓMO CORRERLO
--   Sección por sección. Una vez por cada base de tenant.
--   En phpMyAdmin: hacé clic en la base del tenant ANTES de abrir la pestaña SQL.
--
-- IDEMPOTENCIA
--   Los UPDATE sólo tocan filas cuyo fiscal difiere del correcto, así que
--   correrlos de nuevo no cambia nada. La sección 0 se traba si el respaldo ya
--   existe (a propósito: un segundo respaldo se tomaría sobre datos ya
--   corregidos y perderías la vuelta atrás).
-- ============================================================================


-- ============================================================================
-- SECCIÓN 0 - RESPALDO. No es opcional.
-- ============================================================================
-- Si esto devuelve filas, ya corriste el script en esta base: pará.
SHOW TABLES LIKE 'zz_backup_fiscal_recibos_20260930';

CREATE TABLE zz_backup_fiscal_recibos_20260930 AS
SELECT 'ccclientes'         AS origen, id, compasoc, fiscal FROM ccclientes         WHERE tabla = 'pagos'
UNION ALL
SELECT 'cccuentastesoreria' AS origen, id, compasoc, fiscal FROM cccuentastesoreria WHERE tabla = 'pagos'
UNION ALL
SELECT 'librodiario'        AS origen, id, compasoc, fiscal FROM librodiario        WHERE tabla = 'pagos';

ALTER TABLE zz_backup_fiscal_recibos_20260930 ADD PRIMARY KEY (origen, id);

-- Control: los pares tienen que coincidir.
SELECT (SELECT COUNT(*) FROM ccclientes         WHERE tabla='pagos') AS cc_orig,
       (SELECT COUNT(*) FROM cccuentastesoreria WHERE tabla='pagos') AS teso_orig,
       (SELECT COUNT(*) FROM librodiario        WHERE tabla='pagos') AS diario_orig,
       (SELECT COUNT(*) FROM zz_backup_fiscal_recibos_20260930)      AS total_respaldo;


-- ============================================================================
-- SECCIÓN 1 - TABLA AUXILIAR: qué fiscalidad le corresponde a cada recibo
-- ============================================================================
-- Se juntan los dos vínculos que existen entre recibo y comprobante:
--   a) cobrosfacturasclientes (el normal, admite varios comprobantes por recibo)
--   b) pagos.idfactura        (vínculo legacy de un solo comprobante)
-- Sólo entran los recibos donde TODOS los comprobantes coinciden en fiscalidad.

DROP TABLE IF EXISTS zz_fiscal_recibos_20260930;

CREATE TABLE zz_fiscal_recibos_20260930 (
    idpago          INT NOT NULL PRIMARY KEY,
    fiscal_correcto TINYINT(1) NOT NULL,
    comprobantes    INT NOT NULL
);

INSERT INTO zz_fiscal_recibos_20260930 (idpago, fiscal_correcto, comprobantes)
SELECT v.idpago, MIN(v.es_fiscal), COUNT(DISTINCT v.idticket)
FROM (
    SELECT cf.idpago AS idpago, cf.idfactura AS idticket, (t.tipocomp < 100) AS es_fiscal
    FROM cobrosfacturasclientes cf
    JOIN tickets t ON t.id = cf.idfactura
    WHERE cf.tablapago = 'pagos'
    UNION
    SELECT p.id, p.idfactura, (t.tipocomp < 100)
    FROM pagos p
    JOIN tickets t ON t.id = p.idfactura
    WHERE p.idfactura <> 0
) v
GROUP BY v.idpago
HAVING COUNT(DISTINCT v.es_fiscal) = 1;

-- Diagnóstico: cómo queda cada recibo.
SELECT CASE
         WHEN f.idpago IS NULL AND EXISTS (
                SELECT 1 FROM cobrosfacturasclientes cf
                WHERE cf.tablapago='pagos' AND cf.idpago = c.compasoc)
              THEN 'MEZCLADO - no se toca, revisar a mano'
         WHEN f.idpago IS NULL
              THEN 'SIN IMPUTAR - no se puede deducir, no se toca'
         WHEN f.fiscal_correcto = 1 THEN 'imputado a FISCALES  -> fiscal = 1'
         ELSE                            'imputado a NO fiscales -> fiscal = 0'
       END AS situacion,
       COUNT(*)                                   AS recibos,
       SUM(c.fiscal <> COALESCE(f.fiscal_correcto, c.fiscal)) AS a_corregir
FROM ccclientes c
LEFT JOIN zz_fiscal_recibos_20260930 f ON f.idpago = c.compasoc
WHERE c.tabla = 'pagos'
GROUP BY situacion
ORDER BY recibos DESC;

-- Cuántos recibos tienen HOY las tres tablas en desacuerdo entre sí.
SELECT COUNT(*) AS recibos_con_las_3_tablas_en_desacuerdo FROM (
  SELECT c.compasoc
  FROM ccclientes c
  LEFT JOIN cccuentastesoreria te ON te.tabla='pagos' AND te.compasoc = c.compasoc
  LEFT JOIN librodiario       ld ON ld.tabla='pagos' AND ld.compasoc = c.compasoc
  WHERE c.tabla = 'pagos'
  GROUP BY c.compasoc, c.fiscal
  HAVING (MIN(te.fiscal) IS NOT NULL AND MIN(te.fiscal) <> c.fiscal)
      OR (MIN(ld.fiscal) IS NOT NULL AND MIN(ld.fiscal) <> c.fiscal)
) z;


-- ============================================================================
-- SECCIÓN 2 - LOS CASOS QUE HAY QUE MIRAR A MANO
-- ============================================================================
-- Recibos imputados a comprobantes fiscales Y no fiscales al mismo tiempo.
-- No se tocan. Si son pocos, lo más prolijo es desimputar y rehacerlos.
SELECT c.compasoc AS idpago, c.fecha, c.cliente, c.haber AS importe, c.fiscal AS fiscal_actual,
       GROUP_CONCAT(DISTINCT CONCAT(t.tipocomp, CASE WHEN t.tipocomp < 100 THEN ' (fiscal)' ELSE ' (no fiscal)' END)
                    ORDER BY t.tipocomp SEPARATOR ' + ') AS comprobantes_imputados
FROM ccclientes c
JOIN cobrosfacturasclientes cf ON cf.tablapago = 'pagos' AND cf.idpago = c.compasoc
JOIN tickets t ON t.id = cf.idfactura
LEFT JOIN zz_fiscal_recibos_20260930 f ON f.idpago = c.compasoc
WHERE c.tabla = 'pagos' AND f.idpago IS NULL
GROUP BY c.compasoc, c.fecha, c.cliente, c.haber, c.fiscal
ORDER BY c.fecha DESC;


-- ============================================================================
-- SECCIÓN 3 - APLICAR. Las tres tablas con el mismo valor.
-- ============================================================================

UPDATE ccclientes c
JOIN zz_fiscal_recibos_20260930 f ON f.idpago = c.compasoc
SET c.fiscal = f.fiscal_correcto
WHERE c.tabla = 'pagos' AND c.fiscal <> f.fiscal_correcto;

UPDATE cccuentastesoreria te
JOIN zz_fiscal_recibos_20260930 f ON f.idpago = te.compasoc
SET te.fiscal = f.fiscal_correcto
WHERE te.tabla = 'pagos' AND te.fiscal <> f.fiscal_correcto;

UPDATE librodiario ld
JOIN zz_fiscal_recibos_20260930 f ON f.idpago = ld.compasoc
SET ld.fiscal = f.fiscal_correcto
WHERE ld.tabla = 'pagos' AND ld.fiscal <> f.fiscal_correcto;


-- ============================================================================
-- SECCIÓN 4 - VERIFICACIÓN
-- ============================================================================

-- 4.a  Ningún recibo deducible puede quedar con la fiscalidad equivocada.
--      Los tres números tienen que dar 0.
SELECT
 (SELECT COUNT(*) FROM ccclientes c         JOIN zz_fiscal_recibos_20260930 f ON f.idpago=c.compasoc
   WHERE c.tabla='pagos'  AND c.fiscal  <> f.fiscal_correcto) AS mal_en_ccclientes,
 (SELECT COUNT(*) FROM cccuentastesoreria te JOIN zz_fiscal_recibos_20260930 f ON f.idpago=te.compasoc
   WHERE te.tabla='pagos' AND te.fiscal <> f.fiscal_correcto) AS mal_en_tesoreria,
 (SELECT COUNT(*) FROM librodiario ld        JOIN zz_fiscal_recibos_20260930 f ON f.idpago=ld.compasoc
   WHERE ld.tabla='pagos' AND ld.fiscal <> f.fiscal_correcto) AS mal_en_librodiario;

-- 4.b  Las tres tablas ya no deberían contradecirse en los recibos deducibles.
SELECT COUNT(*) AS recibos_deducibles_en_desacuerdo FROM (
  SELECT c.compasoc
  FROM ccclientes c
  JOIN zz_fiscal_recibos_20260930 f ON f.idpago = c.compasoc
  LEFT JOIN cccuentastesoreria te ON te.tabla='pagos' AND te.compasoc = c.compasoc
  LEFT JOIN librodiario       ld ON ld.tabla='pagos' AND ld.compasoc = c.compasoc
  WHERE c.tabla = 'pagos'
  GROUP BY c.compasoc, c.fiscal
  HAVING (MIN(te.fiscal) IS NOT NULL AND MIN(te.fiscal) <> c.fiscal)
      OR (MIN(ld.fiscal) IS NOT NULL AND MIN(ld.fiscal) <> c.fiscal)
) z;

-- 4.c  Ningún importe se tocó: sólo cambió la columna fiscal.
SELECT COUNT(*) AS filas_cambiadas
FROM zz_backup_fiscal_recibos_20260930 b
JOIN ccclientes c ON c.id = b.id AND b.origen = 'ccclientes'
WHERE c.fiscal <> b.fiscal;


-- ============================================================================
-- SECCIÓN 5 - LIMPIEZA (cuando ya validaste los informes)
-- ============================================================================
-- DROP TABLE zz_fiscal_recibos_20260930;
-- DROP TABLE zz_backup_fiscal_recibos_20260930;


-- ============================================================================
-- SECCIÓN 6 - VOLVER ATRÁS
-- ============================================================================
-- UPDATE ccclientes c JOIN zz_backup_fiscal_recibos_20260930 b
--   ON b.origen='ccclientes' AND b.id=c.id SET c.fiscal = b.fiscal;
-- UPDATE cccuentastesoreria te JOIN zz_backup_fiscal_recibos_20260930 b
--   ON b.origen='cccuentastesoreria' AND b.id=te.id SET te.fiscal = b.fiscal;
-- UPDATE librodiario ld JOIN zz_backup_fiscal_recibos_20260930 b
--   ON b.origen='librodiario' AND b.id=ld.id SET ld.fiscal = b.fiscal;
