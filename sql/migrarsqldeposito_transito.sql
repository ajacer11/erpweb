-- migrarsqldeposito_transito.sql
-- Ejecutar en cada base de datos del sistema (requiere haber corrido antes
-- sql/migrarsqldepositos.sql). Agrega el circuito de "en transito" para
-- transferencias entre depositos: origen descarga stock de inmediato, el destino
-- recien lo recibe cuando confirma la recepcion.
--
-- No requiere activar nada nuevo: si el tenant no usa transferencias con
-- confirmacion, esto no se nota (el deposito "En Transito" se crea solo,
-- de forma perezosa, la primera vez que se envia una transferencia que lo pida).

ALTER TABLE depositos
    ADD COLUMN estransito TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'deposito especial de uso interno (no seleccionable como deposito de trabajo/ajuste)';

ALTER TABLE transferencias_deposito
    ADD COLUMN id_transporte INT(11) DEFAULT NULL COMMENT 'transportes.id, opcional, quien traslada la mercaderia',
    ADD COLUMN fecha_recepcion DATETIME DEFAULT NULL,
    ADD COLUMN usuario_recepcion VARCHAR(100) DEFAULT NULL;

-- estado ahora admite 3 valores: 'EN_TRANSITO' | 'COMPLETADA' | 'ANULADA'
-- (antes solo existian 'COMPLETADA' y 'ANULADA'; las filas viejas no cambian).
