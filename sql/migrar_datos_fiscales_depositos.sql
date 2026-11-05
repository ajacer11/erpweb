-- Datos comerciales por depósito. El CUIT permanece exclusivamente en datosempresa/tenant.
START TRANSACTION;

ALTER TABLE depositos
    ADD COLUMN IF NOT EXISTS domicilio VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS localidad VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS provincia VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS telefono VARCHAR(100) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS codigopostal VARCHAR(20) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS puntodeventa INT(11) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS logo LONGBLOB NULL;

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS id_deposito INT(11) NULL COMMENT 'Depósito/sucursal que emitió el comprobante';

CREATE INDEX IF NOT EXISTS idx_tickets_deposito ON tickets (id_deposito);

COMMIT;
