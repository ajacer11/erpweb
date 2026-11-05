-- Remito de mercadería por comprobante emitido (comprobantes_emitidos.php),
-- con talonario propio (numeración 6200 en adelante), independiente de la
-- numeración fiscal de "tickets". Un remito por comprobante como máximo
-- (UNIQUE KEY sobre id_ticket): si ya existe, se reutiliza; si no, se genera
-- la primera vez que se pide imprimir (ver imprimir_remito.php).
--
-- Nombre "remitos_mercaderia" (no "remitos" a secas) a propósito: algunos
-- tenants migrados desde sistemas viejos (tabac/staluc) ya tienen una tabla
-- huérfana llamada "remitos" con un esquema totalmente distinto (columnas
-- numremito/ticket/cliente/estado, vacía, sin código actual que la use). No
-- se toca esa tabla legacy; esta es una tabla nueva e independiente.
--
-- No reemplaza tickets.numremito (columna legacy sin uso real) ni alb_envios
-- (remito de despacho/transporte, feature distinta).
--
-- Ejecutar en cada base de datos de tenant.

CREATE TABLE IF NOT EXISTS `remitos_mercaderia` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_ticket` INT(11) NOT NULL,
  `numero` INT(11) NOT NULL,
  `fecha` DATE NOT NULL,
  `usuario` VARCHAR(100) DEFAULT NULL,
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remitos_mercaderia_ticket` (`id_ticket`),
  UNIQUE KEY `uq_remitos_mercaderia_numero` (`numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
