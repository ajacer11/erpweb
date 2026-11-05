-- Corrección de numeración fiscal del 01/09/2026 en STALUC.
-- Los primeros comprobantes emitidos el 01/09 (antes de que se corrigiera el bug
-- ese mismo día) se grabaron localmente con numeración reiniciada desde 1, aunque
-- ARCA sí les asignó el número correlativo real que seguía a agosto. Confirmado
-- contra ARCA por el cliente: la numeración real es correlativa.
--
-- Afecta 4 Facturas A (numcomp local 1-4 -> real 4151-4154) y 25 Facturas B
-- (numcomp local 1-25 -> real 8676-8700), todas PV4, fecha 2026-09-01.
-- Relevamiento de TODAS las tablas con columna numcomp (auxlineadeventa,
-- auxticket, controlstock, ingresosdestock, lineadecompra, lineadepedido,
-- lineadescuentos, pagoaproveedores, pagosprov, pagosvende, pedidos,
-- promociones_ventas, xmlarca) confirmó 0 filas afectadas salvo tickets y
-- lineadeventa: las 106 ventas nuevas son 100% línea manual (código 'LM'), sin
-- movimiento de stock ni promociones. auxticket/auxlineadeventa no se tocan
-- (son la cola de comprobantes fallidos, sin relación con estos 29).

USE staluc_final;
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;

-- Factura A (tipocomp 1), PV4: +4150
UPDATE staluc_final.tickets
SET numcomp = numcomp + 4150
WHERE PtoVta = 4 AND tipocomp = 1 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 4;

UPDATE staluc_final.lineadeventa
SET numcomp = numcomp + 4150
WHERE pdv = 4 AND Tipocomp = 1 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 4;

-- Factura B (tipocomp 6), PV4: +8675
UPDATE staluc_final.tickets
SET numcomp = numcomp + 8675
WHERE PtoVta = 4 AND tipocomp = 6 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 25;

UPDATE staluc_final.lineadeventa
SET numcomp = numcomp + 8675
WHERE pdv = 4 AND Tipocomp = 6 AND fecha = '2026-09-01' AND numcomp BETWEEN 1 AND 25;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
