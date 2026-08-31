-- Ejecutar en cada base de datos del sistema antes de desplegar el codigo PHP.
-- El valor 1 conserva el comportamiento existente: mostrar el vendedor.

ALTER TABLE configuracion
    ADD COLUMN mostrarvendedor TINYINT(1) NOT NULL DEFAULT 1;
