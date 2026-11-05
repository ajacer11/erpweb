-- API publica de productos (precios + stock) para tiendas externas.
-- No modifica ni borra datos existentes: crea tablas nuevas y agrega una
-- columna nullable a alb_productos (no rompe históricos ni requiere backfill).
--
-- ============================================================================
-- PARTE 1: ejecutar UNA SOLA VEZ en la base MAESTRA (ClientesSistema).
-- ============================================================================
-- El token llega sin sesión ni CUIT conocido de antemano: el sistema tiene que
-- poder resolver "a que tenant pertenece este token" ANTES de poder conectarse
-- a la base de ese tenant. Por eso esta tabla vive en la maestra (igual que
-- log_accesos), no en cada base de tenant.
--
-- listas_compartidas guarda los IDs de "Listas"."ID" (de la base del tenant)
-- separados por coma (ej: "1,3"). id_deposito NULL = se comparte el stock
-- consolidado (alb_productos.Stock); si no es NULL, referencia depositos.id
-- de la base de ESE tenant.

CREATE TABLE IF NOT EXISTS `api_config_productos_externa` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `cuit` VARCHAR(20) NOT NULL,
  `db_id` INT(11) NOT NULL DEFAULT 0 COMMENT 'tenant_db_id (fila de la tabla-por-cuit en la maestra). 0 = usa la config activa por defecto del cuit (NULL no sirve aca: MySQL no lo trata como unico en la UNIQUE KEY de abajo)',
  `habilitado` TINYINT(1) NOT NULL DEFAULT 0,
  `token` VARCHAR(70) NOT NULL,
  `listas_compartidas` VARCHAR(50) NOT NULL DEFAULT '1' COMMENT 'IDs de Listas.ID (base del tenant) separados por coma, ej: 1,3',
  `id_deposito` INT(11) DEFAULT NULL COMMENT 'NULL = stock consolidado. Si no es NULL, referencia depositos.id de la base del tenant',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_modificacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `usuario_modificacion` VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_config_productos_token` (`token`),
  UNIQUE KEY `uq_api_config_productos_cuit_db` (`cuit`, `db_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `log_accesos_api_productos_externa` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `fecha` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` VARCHAR(45) DEFAULT NULL,
  `token_usado` VARCHAR(70) DEFAULT NULL,
  `cuit` VARCHAR(20) DEFAULT NULL,
  `resultado` VARCHAR(20) NOT NULL COMMENT 'ok, token_invalido, deshabilitado, error',
  `detalle` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_log_api_productos_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ============================================================================
-- PARTE 2: ejecutar en CADA base de tenant (una por CUIT).
-- ============================================================================
-- Texto opcional pensado para catalogo publico, distinto de la descripcion
-- interna. Si queda NULL/vacio, la API hace fallback a alb_productos.descripcion.
-- VARCHAR(2000) y no TEXT: en MySQL/MariaDB mas viejo, TEXT no admite DEFAULT.

ALTER TABLE `alb_productos`
  ADD COLUMN IF NOT EXISTS `descripcion_tienda` VARCHAR(2000) DEFAULT NULL AFTER `descripcion`;
