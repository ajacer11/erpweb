-- ============================================================================
-- 2026-10-15 - Columnas de chequesterceros que el código ya usa
-- ============================================================================
--
-- api_cobros.php (cobro con cheques), anulacion_pagos_helpers.php (anular un
-- recibo) y api_pagos_proveedores.php (endoso) usan
-- chequesterceros.recibo_origen_id y chequesterceros.pago_endoso_id, pero no
-- había ninguna migración que las creara: solo existen en algunas bases. En
-- las que faltan, cobrar con cheques y ANULAR CUALQUIER RECIBO de cliente
-- fallan con "Unknown column 'recibo_origen_id'".
--
-- Se crean igual que en las bases donde ya estaban (int NULL), y se completa
-- recibo_origen_id con el recibo que originó cada cheque recibido en cobranza
-- (tabla = 'pagos', compasoc = id del recibo), que es lo que ese campo guarda.
--
-- Se puede correr más de una vez.
-- ============================================================================

ALTER TABLE chequesterceros
    ADD COLUMN IF NOT EXISTS recibo_origen_id INT(11) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS pago_endoso_id   INT(11) NULL DEFAULT NULL;

UPDATE chequesterceros
SET recibo_origen_id = compasoc
WHERE recibo_origen_id IS NULL AND tabla = 'pagos' AND compasoc > 0;
