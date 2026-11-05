-- ============================================================================
-- 2026-10-08 - Habilitar / deshabilitar notas de débito de venta
-- ============================================================================
--
-- Nuevo switch "Admitir Notas de Débito" en Configuración General. Con 0 (por
-- defecto) la pantalla de facturación no ofrece N. Débito A/B/C (2/7/12) y
-- api_facturacion_validaciones_previas.php rechaza emitirlas.
--
-- IMPORTANTE: correr ANTES de subir el código, en cada base de tenant.
-- api_facturar_gets.php lee la columna por nombre; sin ella no carga la
-- configuración de la pantalla de facturación.
--
-- Se puede correr más de una vez.
-- ============================================================================

ALTER TABLE configuracion
    ADD COLUMN IF NOT EXISTS admitenotasdebito TINYINT(1) NOT NULL DEFAULT 0;
