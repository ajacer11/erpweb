-- ============================================================================
-- 2026-10-14 - Modificar los medios de cobro de un comprobante ya emitido
-- ============================================================================
--
-- Comprobantes Emitidos > botón "Modificar medios de cobro"
-- (api_modificar_medios_cobro.php). No reescribe lo grabado por la venta:
-- registra contramovimientos con fecha del día de la modificación (tesorería,
-- caja abierta del usuario, bancos, cuenta corriente y un asiento contable),
-- y actualiza los medios que muestra el comprobante.
--
-- 1. Permiso especial nuevo, por usuario (Niveles de acceso > Permisos
--    especiales). Los administradores pueden siempre.
-- 2. Tabla de auditoría: qué se cambió, quién, cuándo y por qué.
--
-- IMPORTANTE: correr ANTES de subir el código, en cada base de tenant.
-- Se puede correr más de una vez.
-- ============================================================================

ALTER TABLE permisosespeciales
    ADD COLUMN IF NOT EXISTS modificarmedioscobro TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS modificacionesmedioscobro (
    id              INT(11)       NOT NULL AUTO_INCREMENT,
    idticket        INT(11)       NOT NULL,
    fecha           DATETIME      NOT NULL,
    usuario         VARCHAR(100)  NOT NULL,
    motivo          VARCHAR(255)  NOT NULL,
    mpago1_ant      VARCHAR(100)  NULL,
    mpago1imp_ant   DECIMAL(18,2) NOT NULL DEFAULT 0,
    mpago2_ant      VARCHAR(100)  NULL,
    mpago2imp_ant   DECIMAL(18,2) NOT NULL DEFAULT 0,
    mpago1_nuevo    VARCHAR(100)  NULL,
    mpago1imp_nuevo DECIMAL(18,2) NOT NULL DEFAULT 0,
    mpago2_nuevo    VARCHAR(100)  NULL,
    mpago2imp_nuevo DECIMAL(18,2) NOT NULL DEFAULT 0,
    numasiento      INT(11)       NOT NULL DEFAULT 0,
    idcaja          INT(11)       NOT NULL DEFAULT 0,
    idcontrolcaja   INT(11)       NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_modmc_ticket (idticket)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
