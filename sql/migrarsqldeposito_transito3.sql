-- migrarsqldeposito_transito3.sql
-- Ejecutar despues de migrarsqldeposito_transito2.sql.
-- Agrega doble confirmacion al circuito de transito:
--   1) el deposito de transito debe ACEPTAR la mercaderia que le mando el origen
--      (antes quedaba disponible para el destino sin que nadie en transito la
--      confirmara).
--   2) el destino puede recibir TOTAL o PARCIAL. Si es parcial, el remanente
--      queda en transito.
-- Ademas, un administrador puede resolver manualmente el remanente que quedo
-- en transito: ajustarlo (perdida/rotura), devolverlo al origen, o enviarlo
-- igual al destino sin esperar mas confirmaciones.
--
-- No rompe transferencias directas (sin confirmacion) ni el historico previo.

ALTER TABLE transferencias_deposito
    ADD COLUMN fecha_aceptacion_transito DATETIME DEFAULT NULL,
    ADD COLUMN usuario_aceptacion_transito VARCHAR(100) DEFAULT NULL,
    ADD COLUMN resoluciontransito VARCHAR(30) DEFAULT NULL COMMENT 'DEVUELTA | AJUSTADA | ENVIADA_DESTINO -- si el remanente en transito se resolvio a mano',
    ADD COLUMN usuarioresoluciontransito VARCHAR(100) DEFAULT NULL,
    ADD COLUMN fecharesoluciontransito DATETIME DEFAULT NULL,
    ADD COLUMN motivoresoluciontransito VARCHAR(500) DEFAULT NULL;

-- estado ahora admite: 'PENDIENTE_TRANSITO' | 'EN_TRANSITO' | 'PARCIAL' | 'COMPLETADA' | 'ANULADA'
-- (antes solo 'EN_TRANSITO' | 'COMPLETADA' | 'ANULADA'; las filas viejas no cambian de valor).

ALTER TABLE transferencias_deposito_lineas
    ADD COLUMN cantidad_recibida DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto de esta linea ya esta en el deposito destino',
    ADD COLUMN cantidad_devuelta DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto volvio al origen por decision administrativa',
    ADD COLUMN cantidad_ajustada DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto se dio de baja (perdida) por decision administrativa';

-- Las transferencias EN_TRANSITO creadas antes de esta migracion ya estaban
-- fisicamente en el deposito de transito sin el paso de "aceptacion" (no
-- existia todavia) -> las marcamos como ya aceptadas para no pedirles un paso
-- que nunca existio cuando se enviaron.
UPDATE transferencias_deposito
    SET fecha_aceptacion_transito = fecha, usuario_aceptacion_transito = usuario
    WHERE estado = 'EN_TRANSITO' AND fecha_aceptacion_transito IS NULL;

-- Transferencias ya COMPLETADAS (directas, o recibidas 100% en destino con el
-- circuito viejo) quedan con sus lineas marcadas como totalmente recibidas,
-- para que el detalle nuevo (recibido/devuelto/ajustado) sea consistente con
-- el historico previo a esta migracion.
UPDATE transferencias_deposito_lineas l
    JOIN transferencias_deposito t ON t.id = l.id_transferencia
    SET l.cantidad_recibida = l.cantidad
    WHERE t.estado = 'COMPLETADA' AND l.cantidad_recibida = 0;
