-- migrarsqldeposito_transito2.sql
-- Ejecutar despues de migrarsqldeposito_transito.sql.
-- Habilita MULTIPLES depositos de transito (antes solo existia uno generico
-- compartido por todo el sistema, lo que mezclaba el stock de envios simultaneos
-- a destinos distintos). Ahora cada transferencia guarda CUAL deposito de transito
-- uso, para que recibir/anular siempre operen sobre el correcto.

ALTER TABLE transferencias_deposito
    ADD COLUMN id_deposito_transito INT(11) DEFAULT NULL COMMENT 'deposito de transito usado en esta transferencia (NULL si fue directa, sin confirmacion)';

-- Las transferencias EN_TRANSITO creadas antes de esta migracion (si las hubiera)
-- usaron el deposito generico "En Transito" (estransito=1) creado por el sistema.
-- Las completamos para que recibir/anular sigan funcionando sobre ellas:
UPDATE transferencias_deposito t
    JOIN depositos d ON d.estransito = 1
    SET t.id_deposito_transito = d.id
    WHERE t.estado = 'EN_TRANSITO' AND t.id_deposito_transito IS NULL;
