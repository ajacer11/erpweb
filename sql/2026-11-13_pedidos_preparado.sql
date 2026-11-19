-- Pedidos: guarda el tilde "preparado en caja" de cada línea del pedido.
-- Idempotente: si la columna ya existe no hace nada (apto para Actualizar Bases en todos los tenants).
SET @existe := (SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lineapedidosclientes' AND COLUMN_NAME = 'preparado');
SET @tabla := (SELECT COUNT(*) FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'lineapedidosclientes');
SET @sql := IF(@existe = 0 AND @tabla = 1,
               'ALTER TABLE lineapedidosclientes ADD COLUMN preparado TINYINT(1) NOT NULL DEFAULT 0',
               'SELECT 1');
PREPARE st FROM @sql;
EXECUTE st;
DEALLOCATE PREPARE st;
