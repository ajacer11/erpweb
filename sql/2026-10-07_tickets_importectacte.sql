-- ============================================================================
-- 2026-10-07 - Saldo pendiente de las ventas = solo lo enviado a cuenta corriente
-- ============================================================================
--
-- MOTIVO
--   En una venta con pago mixto (ej. $600 efectivo + $400 cuenta corriente)
--   ccclientes ya registraba bien la deuda ($400), pero el saldo pendiente de la
--   factura se calculaba como
--
--       tickets.total - SUM(cobrosfacturasclientes.importepagado)
--
--   o sea $1000: al cobrar o imputar, la factura pedía el total, y el informe de
--   facturas pendientes / composición de saldos no coincidía con la cuenta
--   corriente (la auditoría de cobros lo marcaba como diferencia).
--
--   A partir de ahora api_facturar.php guarda en tickets.importectacte lo que
--   se mandó a cuenta corriente, y todas las consultas de pendiente usan
--
--       COALESCE(importectacte, total) - SUM(importepagado)
--
--   NULL significa "comprobante anterior a esta columna": se sigue tomando el
--   total, igual que antes. Este script además completa la columna en los
--   comprobantes existentes que se pagaron (total o parcialmente) con un medio
--   de pago de tipo cuenta corriente.
--
-- IMPORTANTE
--   Correr ANTES de subir el código nuevo: api_facturar.php ya inserta la
--   columna, y sin ella no se puede facturar.
--
-- CÓMO CORRERLO
--   Una vez por cada base de tenant, sección por sección.
--   En phpMyAdmin: hacé clic en la base del tenant ANTES de abrir la pestaña SQL.
--
-- IDEMPOTENCIA
--   Se puede correr más de una vez: la columna se crea con IF NOT EXISTS y el
--   completado sólo toca filas con importectacte en NULL.
-- ============================================================================


-- ----------------------------------------------------------------------------
-- 1. Crear la columna
-- ----------------------------------------------------------------------------
ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS importectacte DECIMAL(18,2) NULL DEFAULT NULL;


-- ----------------------------------------------------------------------------
-- 2. Diagnóstico (sólo lectura): ventas con pago mixto cuyo pendiente hoy está
--    mal calculado. "Pendiente hoy" es lo que muestra el sistema actual, y
--    "Pendiente correcto" lo que va a mostrar después de este script.
--
--    La parte de cuenta corriente se toma de los medios de pago guardados en el
--    propio ticket (mpago1/mpago2 + importes), cruzando por nombre con
--    mediosdepago.tipodecuenta = 'Cuenta Corriente'. NO se toma de ccclientes:
--    en los datos migrados del sistema anterior ccclientes.compasoc a veces
--    apunta a otro ticket (desfasado), y daría importes que no corresponden.
-- ----------------------------------------------------------------------------
SELECT t.id, t.fecha, t.letracomp, t.PtoVta, t.numcomp, t.cliente,
       t.total,
       t.mpago1, t.mpago1imp, t.mpago2, t.mpago2imp,
       (CASE WHEN m1.id IS NOT NULL THEN t.mpago1imp ELSE 0 END)
     + (CASE WHEN m2.id IS NOT NULL THEN t.mpago2imp ELSE 0 END)     AS EnviadoACtaCte,
       COALESCE(p.pagado, 0)                                          AS Imputado,
       ROUND(t.total - COALESCE(p.pagado, 0), 2)                      AS PendienteHoy,
       ROUND((CASE WHEN m1.id IS NOT NULL THEN t.mpago1imp ELSE 0 END)
           + (CASE WHEN m2.id IS NOT NULL THEN t.mpago2imp ELSE 0 END)
           - COALESCE(p.pagado, 0), 2)                                AS PendienteCorrecto
FROM tickets t
LEFT JOIN mediosdepago m1 ON m1.medio = t.mpago1 AND m1.tipodecuenta = 'Cuenta Corriente'
LEFT JOIN mediosdepago m2 ON m2.medio = t.mpago2 AND m2.tipodecuenta = 'Cuenta Corriente'
LEFT JOIN (SELECT idfactura, SUM(importepagado) AS pagado
           FROM cobrosfacturasclientes GROUP BY idfactura) p ON p.idfactura = t.id
WHERE t.importectacte IS NULL
  AND (m1.id IS NOT NULL OR m2.id IS NOT NULL)
  AND t.total - ((CASE WHEN m1.id IS NOT NULL THEN t.mpago1imp ELSE 0 END)
               + (CASE WHEN m2.id IS NOT NULL THEN t.mpago2imp ELSE 0 END)) > 0.01
ORDER BY t.fecha;


-- ----------------------------------------------------------------------------
-- 3. Completar la columna en los comprobantes existentes que tienen algún
--    medio de pago de tipo cuenta corriente. El resto queda en NULL (se sigue
--    tomando el total, como hasta ahora).
-- ----------------------------------------------------------------------------
UPDATE tickets t
LEFT JOIN mediosdepago m1 ON m1.medio = t.mpago1 AND m1.tipodecuenta = 'Cuenta Corriente'
LEFT JOIN mediosdepago m2 ON m2.medio = t.mpago2 AND m2.tipodecuenta = 'Cuenta Corriente'
SET t.importectacte = ROUND((CASE WHEN m1.id IS NOT NULL THEN t.mpago1imp ELSE 0 END)
                          + (CASE WHEN m2.id IS NOT NULL THEN t.mpago2imp ELSE 0 END), 2)
WHERE t.importectacte IS NULL
  AND (m1.id IS NOT NULL OR m2.id IS NOT NULL);


-- ----------------------------------------------------------------------------
-- 4. Recalcular "pagada" de los comprobantes con pago mixto: si los cobros ya
--    cubren la parte de cuenta corriente, la factura queda saldada.
-- ----------------------------------------------------------------------------
UPDATE tickets t
LEFT JOIN (SELECT idfactura, SUM(importepagado) AS pagado
           FROM cobrosfacturasclientes GROUP BY idfactura) p ON p.idfactura = t.id
SET t.pagada = CASE WHEN t.importectacte - COALESCE(p.pagado, 0) > 0.01 THEN 0 ELSE 1 END
WHERE t.importectacte IS NOT NULL
  AND t.total - t.importectacte > 0.01
  AND t.tipocomp NOT IN (3, 8, 13, 101);


-- ----------------------------------------------------------------------------
-- 5. Para revisar a mano (sólo lectura): facturas a las que se les imputó MÁS
--    de lo que fue a cuenta corriente. Pasa cuando, con el cálculo viejo, se
--    cobró el total de una venta que ya tenía una parte pagada al contado. El
--    excedente debería volver al recibo como saldo a cuenta (desde Gestión de
--    imputaciones se puede bajar el importe imputado).
-- ----------------------------------------------------------------------------
SELECT t.id, t.fecha, t.letracomp, t.PtoVta, t.numcomp, t.cliente,
       t.total, t.importectacte,
       p.pagado                                     AS Imputado,
       ROUND(p.pagado - t.importectacte, 2)         AS Excedente
FROM tickets t
JOIN (SELECT idfactura, SUM(importepagado) AS pagado
      FROM cobrosfacturasclientes GROUP BY idfactura) p ON p.idfactura = t.id
WHERE t.importectacte IS NOT NULL
  AND p.pagado - t.importectacte > 0.01
ORDER BY t.fecha;
