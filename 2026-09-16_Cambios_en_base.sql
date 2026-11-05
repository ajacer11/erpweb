-- 2026-09-16: conservar el origen del cheque al endosarlo a un proveedor.
-- Aplicar antes de desplegar el código. Compatible con MariaDB (XAMPP).
-- Ejecutado en localhost / coegotest. No elimina comprobantes ni imputaciones.
ALTER TABLE chequesterceros ADD COLUMN IF NOT EXISTS recibo_origen_id INT NULL DEFAULT NULL;
ALTER TABLE chequesterceros ADD COLUMN IF NOT EXISTS pago_endoso_id INT NULL DEFAULT NULL;
-- Conceptos de compra deshabilitables sin borrar su historial.
-- Los existentes permanecen habilitados al agregar la columna.
ALTER TABLE conceptosgastos
  ADD COLUMN IF NOT EXISTS habilitada TINYINT(1) NOT NULL DEFAULT 1;
-- Sólo reconstruir relaciones inequívocas. Los endosos antiguos perdieron el recibo original.
UPDATE chequesterceros SET recibo_origen_id = compasoc
WHERE recibo_origen_id IS NULL AND tabla = 'pagos' AND estado IN (0,1) AND endosadoa = 0;
UPDATE chequesterceros SET pago_endoso_id = compasoc
WHERE pago_endoso_id IS NULL AND tabla = 'pagos' AND estado = 2 AND endosadoa <> 0;
-- Las pruebas funcionales se realizan dentro de transacciones revertidas (ROLLBACK).

-- 2026-09-16: reparar cuentas corrientes de pagos ya anulados cuyo egreso de
-- tesorería y asiento contable fueron revertidos, pero quedó el haber de la CC.
-- Vista previa de los movimientos afectados (ejecutar antes de aplicar UPDATE):
SELECT p.id AS pago, p.numcomp, m.id AS movimiento_cc, m.pagos AS importe_pendiente
FROM pagoaproveedores p
JOIN moviemientosccproveedores m
  ON m.tabla = 'pagoaproveedores' AND m.proveedor = p.proveedor
 AND ((m.idpago = p.id AND (m.compasoc = 0 OR (p.numcomp <> 0 AND m.compasoc = p.numcomp)))
      OR (p.numcomp <> 0 AND m.compasoc = p.numcomp
          AND m.fecha = DATE(p.fecharecibo) AND m.pagos = p.total))
WHERE p.estado = 1000 AND m.pagos <> 0
  AND EXISTS (SELECT 1 FROM cccuentastesoreria t WHERE t.tabla = 'pagoaproveedores'
              AND t.compasoc = p.id AND t.descripcion LIKE 'Anula%')
  AND EXISTS (SELECT 1 FROM librodiario l WHERE l.tabla = 'pagoaproveedores'
              AND l.compasoc = p.id AND l.detalle LIKE 'Anula%');

-- Caso comprobado en westit_tabac: el pago mostrado como #3411 tiene
-- id=3411, numcomp=3407, proveedor=24, fecha=2026-08-31 y total=350000.
-- Su movimiento CC id=7197 guarda idpago=3407 (número antiguo), por lo que
-- no debe confundirse con el pago id=3407 de otro proveedor.
-- Ejecutar este UPDATE específico una sola vez en la base afectada; las
-- condiciones evitan tocar una fila distinta si los datos ya cambiaron.
SELECT p.id AS pago, p.numcomp, p.estado, m.id AS movimiento_cc,
       m.idpago, m.compasoc, m.pagos AS importe_pendiente
FROM pagoaproveedores p
JOIN moviemientosccproveedores m ON m.id = 7197 AND m.proveedor = p.proveedor
WHERE p.id = 3411;

UPDATE moviemientosccproveedores m
JOIN pagoaproveedores p ON p.id = 3411 AND p.proveedor = 24
 AND p.numcomp = 3407 AND DATE(p.fecharecibo) = '2026-08-31'
 AND p.total = 350000 AND p.estado = 1000
SET m.pagos = 0, m.mpago1imp = 0, m.mpago2imp = 0
WHERE m.id = 7197 AND m.proveedor = 24 AND m.tabla = 'pagoaproveedores'
  AND m.idpago = 3407 AND m.compasoc = 3407
  AND m.fecha = '2026-08-31' AND m.pagos = 350000
  AND EXISTS (SELECT 1 FROM cccuentastesoreria t
              WHERE t.tabla = 'pagoaproveedores' AND t.compasoc = 3411
                AND t.descripcion LIKE 'Anula%' AND t.debe = 350000)
  AND EXISTS (SELECT 1 FROM librodiario l
              WHERE l.tabla = 'pagoaproveedores' AND l.compasoc = 3411
                AND l.detalle LIKE 'Anula%');

SELECT id AS movimiento_cc, pagos AS importe_pendiente
FROM moviemientosccproveedores WHERE id = 7197;

-- El UPDATE es idempotente y no toca anulaciones viejas sin ambos contramovimientos.
UPDATE moviemientosccproveedores m
JOIN pagoaproveedores p ON m.tabla = 'pagoaproveedores'
 AND m.proveedor = p.proveedor
 AND ((m.idpago = p.id AND (m.compasoc = 0 OR (p.numcomp <> 0 AND m.compasoc = p.numcomp)))
      OR (p.numcomp <> 0 AND m.compasoc = p.numcomp
          AND m.fecha = DATE(p.fecharecibo) AND m.pagos = p.total))
SET m.pagos = 0, m.mpago1imp = 0, m.mpago2imp = 0
WHERE p.estado = 1000 AND m.pagos <> 0
  AND EXISTS (SELECT 1 FROM cccuentastesoreria t WHERE t.tabla = 'pagoaproveedores'
              AND t.compasoc = p.id AND t.descripcion LIKE 'Anula%')
  AND EXISTS (SELECT 1 FROM librodiario l WHERE l.tabla = 'pagoaproveedores'
              AND l.compasoc = p.id AND l.detalle LIKE 'Anula%');

-- Si no se puede identificar exactamente una fila original, preservar el historial
-- y dejar un movimiento compensatorio visible en la cuenta corriente.
-- Se ejecuta sólo cuando tesorería y contabilidad ya tienen contramovimientos.
-- No repetir la compensación para el mismo pago.
INSERT INTO moviemientosccproveedores
  (proveedor, facturas, pagos, fecha, compasoc, tabla, fiscal, usuario, idpago)
SELECT p.proveedor,
       IF(COUNT(m.id) = 0, p.total, COALESCE(SUM(m.pagos), 0)) AS importe_revertir,
       0, CURDATE(), p.numcomp, 'anulacion_pagoaproveedores', p.fiscal,
       'MIGRACION', p.id
FROM pagoaproveedores p
LEFT JOIN moviemientosccproveedores m
  ON m.tabla = 'pagoaproveedores' AND m.proveedor = p.proveedor
 AND ((m.idpago = p.id AND (m.compasoc = 0 OR (p.numcomp <> 0 AND m.compasoc = p.numcomp)))
      OR (p.numcomp <> 0 AND m.compasoc = p.numcomp
          AND m.fecha = DATE(p.fecharecibo) AND m.pagos = p.total))
WHERE p.estado = 1000
  AND EXISTS (SELECT 1 FROM cccuentastesoreria t WHERE t.tabla = 'pagoaproveedores'
              AND t.compasoc = p.id AND t.descripcion LIKE 'Anula%')
  AND EXISTS (SELECT 1 FROM librodiario l WHERE l.tabla = 'pagoaproveedores'
              AND l.compasoc = p.id AND l.detalle LIKE 'Anula%')
  AND NOT EXISTS (SELECT 1 FROM moviemientosccproveedores c
                  WHERE c.tabla = 'anulacion_pagoaproveedores' AND c.idpago = p.id)
GROUP BY p.id
HAVING COUNT(m.id) <> 1 AND importe_revertir <> 0;
