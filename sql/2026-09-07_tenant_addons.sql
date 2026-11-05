-- Interruptor por cliente (controlado por WEST IT, no por el tenant) para
-- funcionalidades vendidas como addon aparte de la licencia 1/2. Es el mismo
-- mecanismo descrito en futura_arquitectura_modulos_adicionales.md; este es
-- su primer uso real, para poder dar de baja/alta la API de productos por
-- cliente sin depender de lo que el tenant configure en su propio panel.
--
-- Ejecutar UNA SOLA VEZ en la base MAESTRA (ClientesSistema).
--
-- addon_key usados hoy: 'api_productos'.

CREATE TABLE IF NOT EXISTS `tenant_addons` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cuit` VARCHAR(20) NOT NULL,
  `addon_key` VARCHAR(50) NOT NULL,
  `habilitado` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_alta` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_addons_cuit_key` (`cuit`, `addon_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Para habilitarle la API de productos a un cliente puntual (reemplazar el CUIT):
-- INSERT INTO tenant_addons (cuit, addon_key, habilitado) VALUES ('20XXXXXXXXX', 'api_productos', 1)
--   ON DUPLICATE KEY UPDATE habilitado = 1;
--
-- Para deshabilitarla sin borrar el registro (por ejemplo, si deja de pagarla):
-- UPDATE tenant_addons SET habilitado = 0 WHERE cuit = '20XXXXXXXXX' AND addon_key = 'api_productos';
