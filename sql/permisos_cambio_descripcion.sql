-- permisos_cambio_descripcion.sql
-- Permiso para editar la descripcion de un producto al Facturar / cargar Pedidos / cargar Presupuestos,
-- mas la columna de IVA propio que necesitan las lineas manuales (LM) de Pedidos y Presupuestos.
-- Ejecutar una sola vez.

-- Interruptor maestro en Configuracion General
ALTER TABLE configuracion
  ADD COLUMN permitireditardescripcion TINYINT(1) NOT NULL DEFAULT 0;

-- 3 permisos por usuario y por pantalla (Facturar / Pedidos / Presupuestos)
ALTER TABLE permisosespeciales
  ADD COLUMN editardescripcionfacturar     TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN editardescripcionpedidos      TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN editardescripcionpresupuestos TINYINT(1) NOT NULL DEFAULT 0;

-- IVA propio de linea, usado SOLO por lineas manuales (codigo = 'LM'); las lineas
-- de producto real lo dejan en NULL y siguen sacando el IVA por JOIN con alb_productos.
ALTER TABLE lineapedidosclientes
  ADD COLUMN poriva DECIMAL(5,2) NULL DEFAULT NULL;
ALTER TABLE lineapresupuestosclientes
  ADD COLUMN poriva DECIMAL(5,2) NULL DEFAULT NULL;
