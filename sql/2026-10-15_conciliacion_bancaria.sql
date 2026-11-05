-- ============================================================================
-- 2026-10-15 - Conciliación bancaria
-- ============================================================================
--
-- Fondos > Bancos y Cuentas Bancarias > Conciliación Bancaria
-- (bancos_conciliacion.php / api_bancos_conciliacion.php).
--
-- Se importa el extracto del banco (Excel o CSV), sus líneas se emparejan con
-- los movimientos del sistema en ccbancos (automático por importe y fecha, o a
-- mano, incluso varios contra varios), y lo que el banco cobró y el sistema no
-- tiene (comisiones, impuestos, débitos automáticos) se registra desde ahí.
--
--   extractos_bancarios   un extracto importado (cuenta, período, saldos)
--   extracto_lineas       cada renglón del extracto (importe con signo:
--                         + crédito / ingreso, - débito / egreso)
--   conciliacion_items    qué líneas y qué movimientos de ccbancos quedaron
--                         conciliados juntos (mismo 'grupo'); un movimiento de
--                         ccbancos se concilia una sola vez (UNIQUE).
--
-- IMPORTANTE: correr ANTES de subir el código, en cada base de tenant.
-- Se puede correr más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS extractos_bancarios (
    id             INT(11)       NOT NULL AUTO_INCREMENT,
    cuenta         INT(11)       NOT NULL,          -- cuentaspropias.id (= ccbancos.cuenta)
    idmediobanco   INT(11)       NOT NULL,          -- mediosdepago del banco (tesorería / asiento)
    desde          DATE          NOT NULL,
    hasta          DATE          NOT NULL,
    saldo_inicial  DECIMAL(18,2) NOT NULL DEFAULT 0,
    saldo_final    DECIMAL(18,2) NOT NULL DEFAULT 0,
    archivo        VARCHAR(255)  NOT NULL DEFAULT '',
    estado         SMALLINT      NOT NULL DEFAULT 0, -- 0 abierto, 1 cerrado
    usuario        VARCHAR(100)  NOT NULL DEFAULT '',
    creado         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cerrado_por    VARCHAR(100)  NOT NULL DEFAULT '',
    cerrado_el     DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_extracto_cuenta (cuenta, desde)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS extracto_lineas (
    id           INT(11)       NOT NULL AUTO_INCREMENT,
    idextracto   INT(11)       NOT NULL,
    orden        INT(11)       NOT NULL DEFAULT 0,
    fecha        DATE          NOT NULL,
    descripcion  VARCHAR(255)  NOT NULL DEFAULT '',
    referencia   VARCHAR(100)  NOT NULL DEFAULT '',
    importe      DECIMAL(18,2) NOT NULL,
    estado       SMALLINT      NOT NULL DEFAULT 0,  -- 0 pendiente, 1 conciliada, 3 ignorada
    nota         VARCHAR(255)  NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_lineas_extracto (idextracto, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS conciliacion_items (
    id          INT(11)      NOT NULL AUTO_INCREMENT,
    idextracto  INT(11)      NOT NULL,
    grupo       INT(11)      NOT NULL,
    idlinea     INT(11)      NULL,
    idccbancos  INT(11)      NULL,
    usuario     VARCHAR(100) NOT NULL DEFAULT '',
    creado      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conc_ccbancos (idccbancos),
    UNIQUE KEY uq_conc_linea (idlinea),
    KEY idx_conc_grupo (idextracto, grupo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE ccbancos ADD INDEX IF NOT EXISTS idx_ccbancos_cuenta_fecha (cuenta, fecha);
