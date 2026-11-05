-- Punto de venta general usado como fallback cuando el depósito no tiene uno.
ALTER TABLE datosempresa
    ADD COLUMN IF NOT EXISTS puntodeventa INT(11) NOT NULL DEFAULT 0;
