-- ============================================================================
-- 2026-10-15 - Cupones de tarjetas y liquidaciones
-- ============================================================================
--
-- Cada cobro con un medio de pago de tipo 'Tarjetas' (venta, nota de crédito,
-- recibo de cobranza o modificación de medios de cobro) genera un cupón con la
-- tarjeta y las cuotas. La liquidación de la tarjeta (Fondos > Tarjetas >
-- Liquidaciones) toma cupones pendientes, descuenta aranceles, IVA y
-- retenciones, y acredita el neto en el banco (tesorería + ccbancos + asiento).
--
-- 1. tarjetas: el catálogo ya existía (vacío y sin uso) en las bases migradas;
--    se crea si falta y se le agrega 'habilitada'.
-- 2. cupones_tarjeta y liquidaciones_tarjeta: nuevas.
-- 3. cuentaspredeterminadas.comisionestarjetas: cuenta de gasto para aranceles
--    y comisiones (se configura en Contabilidad > Configurar Asientos
--    Automáticos). IVA de aranceles va a 'ivacompras' y las retenciones
--    sufridas a 'retiibb' / 'retiva' / 'retgan', que ya existían.
--
-- IMPORTANTE: correr ANTES de subir el código, en cada base de tenant.
-- Se puede correr más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS tarjetas (
    id          INT(11)     NOT NULL AUTO_INCREMENT,
    descripcion VARCHAR(30) NOT NULL,
    tipo        INT(11)     NOT NULL DEFAULT 1,   -- 1 crédito, 2 débito
    diasalcobro INT(11)     NOT NULL DEFAULT 2,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tarjetas_descripcion (descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE tarjetas
    ADD COLUMN IF NOT EXISTS habilitada TINYINT(1) NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS cupones_tarjeta (
    id            INT(11)       NOT NULL AUTO_INCREMENT,
    fecha         DATE          NOT NULL,
    idtarjeta     INT(11)       NOT NULL DEFAULT 0,
    cuotas        INT(11)       NOT NULL DEFAULT 1,
    lote          VARCHAR(20)   NOT NULL DEFAULT '',
    numcupon      VARCHAR(30)   NOT NULL DEFAULT '',
    importe       DECIMAL(18,2) NOT NULL,           -- negativo: devolución / ajuste
    idmediopago   INT(11)       NOT NULL,
    tabla         VARCHAR(20)   NOT NULL,           -- tickets / pagos / ajuste
    compasoc      INT(11)       NOT NULL DEFAULT 0,
    cliente       INT(11)       NOT NULL DEFAULT 0,
    fiscal        TINYINT(1)    NOT NULL DEFAULT 1,
    estado        SMALLINT      NOT NULL DEFAULT 0, -- 0 pendiente, 1 liquidado, 1000 anulado
    idliquidacion INT(11)       NOT NULL DEFAULT 0,
    usuario       VARCHAR(100)  NOT NULL DEFAULT '',
    creado        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cupones_estado (estado, idtarjeta, fecha),
    KEY idx_cupones_origen (tabla, compasoc),
    KEY idx_cupones_liq (idliquidacion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS liquidaciones_tarjeta (
    id               INT(11)       NOT NULL AUTO_INCREMENT,
    idtarjeta        INT(11)       NOT NULL DEFAULT 0,
    fecha            DATE          NOT NULL,          -- fecha de acreditación
    numero           VARCHAR(30)   NOT NULL DEFAULT '', -- número de liquidación de la procesadora
    bruto_cupones    DECIMAL(18,2) NOT NULL DEFAULT 0,
    bruto_anterior   DECIMAL(18,2) NOT NULL DEFAULT 0, -- cupones previos al sistema
    aranceles        DECIMAL(18,2) NOT NULL DEFAULT 0,
    iva_aranceles    DECIMAL(18,2) NOT NULL DEFAULT 0,
    ret_iibb         DECIMAL(18,2) NOT NULL DEFAULT 0,
    ret_iva          DECIMAL(18,2) NOT NULL DEFAULT 0,
    ret_ganancias    DECIMAL(18,2) NOT NULL DEFAULT 0,
    otros_descuentos DECIMAL(18,2) NOT NULL DEFAULT 0,
    neto             DECIMAL(18,2) NOT NULL DEFAULT 0,
    idmediobanco     INT(11)       NOT NULL,
    idmediotarjeta   INT(11)       NOT NULL,
    numasiento       INT(11)       NOT NULL DEFAULT 0,
    observaciones    VARCHAR(255)  NOT NULL DEFAULT '',
    estado           SMALLINT      NOT NULL DEFAULT 1, -- 1 vigente, 1000 anulada
    usuario          VARCHAR(100)  NOT NULL DEFAULT '',
    creado           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    anulada_por      VARCHAR(100)  NOT NULL DEFAULT '',
    anulada_el       DATETIME      NULL,
    PRIMARY KEY (id),
    KEY idx_liq_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE cuentaspredeterminadas
    ADD COLUMN IF NOT EXISTS comisionestarjetas VARCHAR(20) NULL;
