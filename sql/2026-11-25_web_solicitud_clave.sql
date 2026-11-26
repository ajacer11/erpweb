-- Web de clientes: el cliente elige su contraseña en el formulario "Quiero ser cliente".
-- Se guarda cifrada (password_hash de PHP) en la solicitud y, al aprobarla, el acceso
-- se crea con esa contraseña y queda activo sin pasar por el mail de invitación.
-- Correr en todos los tenants ANTES de subir el código. Idempotente.

ALTER TABLE web_solicitudes_registro
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email;
