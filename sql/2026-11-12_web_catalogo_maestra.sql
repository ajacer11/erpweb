-- =====================================================================
-- WEB CATÁLOGO (v1) - Base MAESTRA (clientesSistema). Correr una sola vez.
-- =====================================================================

-- Dominio -> empresa. La web resuelve a qué empresa pertenece mirando el dominio
-- con el que entró el visitante (sin "www."). Un mismo código sirve a todas las webs.
CREATE TABLE IF NOT EXISTS web_sitios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dominio VARCHAR(190) NOT NULL,
    cuit VARCHAR(20) NOT NULL,
    db_id INT NOT NULL DEFAULT 0,
    tipo ENUM('catalogo','tienda') NOT NULL DEFAULT 'catalogo',
    habilitado TINYINT(1) NOT NULL DEFAULT 1,
    fecha_alta TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_web_sitios_dominio (dominio),
    KEY idx_web_sitios_cuit (cuit, db_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- La contratación se habilita con el addon 'web_catalogo' en tenant_addons, ej.:
-- INSERT INTO tenant_addons (cuit, addon_key, habilitado) VALUES ('<cuit>', 'web_catalogo', 1)
--   ON DUPLICATE KEY UPDATE habilitado = 1;
-- INSERT INTO web_sitios (dominio, cuit, db_id, tipo) VALUES ('pescahermanos.westit.com.ar', '<cuit>', <db_id>, 'catalogo');
