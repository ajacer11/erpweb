-- ============================================================================
-- 2026-10-15 - Agente de retención y percepción (IVA, Ganancias, IIBB u otros)
-- ============================================================================
--
-- Contabilidad > Impuestos > Regímenes de Retención y Percepción
-- (retper_regimenes.php / api_retper.php / retper_helpers.php).
--
-- Mientras no haya ningún régimen habilitado, el sistema se comporta igual que
-- antes: no calcula retenciones al pagar a proveedores ni percepciones al
-- facturar. Cada régimen habilitado es la "bandera" de agente para ese impuesto.
--
--   regimenes_retper          configuración de cada régimen (impuesto,
--                             jurisdicción, retención o percepción, base,
--                             alícuotas, mínimos, acumulado mensual, cuenta
--                             contable, código de tributo de ARCA)
--   padron_retper             alícuota por CUIT (padrones de ARBA, ATM, etc.)
--   sujetos_retper            excepciones por cliente/proveedor (exento,
--                             certificado de no retención, alícuota propia)
--   retenciones_practicadas   una fila por régimen y pago a proveedor
--   percepciones_practicadas  una fila por régimen y comprobante de venta
--
-- IMPORTANTE: correr ANTES de subir el código, en cada base de tenant.
-- Se puede correr más de una vez.
-- ============================================================================

CREATE TABLE IF NOT EXISTS regimenes_retper (
    id                      INT(11)       NOT NULL AUTO_INCREMENT,
    nombre                  VARCHAR(100)  NOT NULL,
    impuesto                VARCHAR(20)   NOT NULL,           -- IVA / GANANCIAS / IIBB / OTRO
    jurisdiccion            VARCHAR(60)   NOT NULL DEFAULT 'Nacional',
    tipo                    VARCHAR(10)   NOT NULL,           -- RETENCION / PERCEPCION
    base                    VARCHAR(10)   NOT NULL DEFAULT 'NETO', -- NETO / TOTAL / IVA
    alicuota                DECIMAL(9,4)  NOT NULL DEFAULT 0, -- % general
    alicuota_no_inscripto   DECIMAL(9,4)  NULL,               -- % si el sujeto no es RI / Monotributo / Exento
    minimo_no_sujeto        DECIMAL(18,2) NOT NULL DEFAULT 0, -- base mínima para que corresponda
    resta_minimo            TINYINT(1)    NOT NULL DEFAULT 0, -- 1: se aplica sobre el excedente del mínimo (Ganancias)
    minimo_importe          DECIMAL(18,2) NOT NULL DEFAULT 0, -- importe mínimo a retener / percibir
    acumula_mensual         TINYINT(1)    NOT NULL DEFAULT 0, -- base y mínimo por mes y sujeto
    condiciones             VARCHAR(255)  NOT NULL DEFAULT '', -- condiciones fiscales alcanzadas, separadas por coma (vacío: todas)
    usa_padron              TINYINT(1)    NOT NULL DEFAULT 0,
    sin_padron              VARCHAR(10)   NOT NULL DEFAULT 'GENERAL', -- si el CUIT no está en el padrón: GENERAL / NO_APLICA
    codigo_tributo_arca     INT(11)       NOT NULL DEFAULT 99, -- percepciones: 6 IVA, 7 IIBB, 9 otras, 99 otro
    codigo_impuesto         VARCHAR(10)   NOT NULL DEFAULT '', -- SICORE/SIRE (ej. 217 Ganancias, 767 IVA)
    codigo_regimen          VARCHAR(10)   NOT NULL DEFAULT '', -- SICORE/SIRE (ej. 078)
    cuenta_contable         VARCHAR(20)   NOT NULL DEFAULT '', -- pasivo: retenciones / percepciones a depositar
    resolucion              VARCHAR(120)  NOT NULL DEFAULT '',
    vigente_desde           DATE          NULL,
    vigente_hasta           DATE          NULL,
    habilitado              TINYINT(1)    NOT NULL DEFAULT 0,
    observaciones           VARCHAR(255)  NOT NULL DEFAULT '',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS padron_retper (
    id             INT(11)      NOT NULL AUTO_INCREMENT,
    idregimen      INT(11)      NOT NULL,
    cuit           VARCHAR(11)  NOT NULL,
    alicuota       DECIMAL(9,4) NOT NULL DEFAULT 0,
    excluido       TINYINT(1)   NOT NULL DEFAULT 0,
    vigente_desde  DATE         NULL,
    vigente_hasta  DATE         NULL,
    PRIMARY KEY (id),
    KEY idx_padron (idregimen, cuit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS sujetos_retper (
    id             INT(11)      NOT NULL AUTO_INCREMENT,
    idregimen      INT(11)      NOT NULL,
    tipo_sujeto    VARCHAR(10)  NOT NULL,          -- cliente / proveedor
    idsujeto       INT(11)      NOT NULL,
    modo           VARCHAR(10)  NOT NULL,          -- EXENTO / ALICUOTA
    alicuota       DECIMAL(9,4) NOT NULL DEFAULT 0,
    vigente_hasta  DATE         NULL,
    certificado    VARCHAR(100) NOT NULL DEFAULT '',
    observaciones  VARCHAR(255) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uq_sujeto (idregimen, tipo_sujeto, idsujeto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS retenciones_practicadas (
    id                  INT(11)       NOT NULL AUTO_INCREMENT,
    idregimen           INT(11)       NOT NULL,
    fecha               DATE          NOT NULL,
    idproveedor         INT(11)       NOT NULL,
    idpago              INT(11)       NOT NULL,         -- pagoaproveedores.id
    base                DECIMAL(18,2) NOT NULL DEFAULT 0,
    alicuota            DECIMAL(9,4)  NOT NULL DEFAULT 0,
    importe             DECIMAL(18,2) NOT NULL DEFAULT 0, -- 0: el pago no superó el mínimo (se guarda para el acumulado)
    numero_certificado  INT(11)       NOT NULL DEFAULT 0,
    origen_alicuota     VARCHAR(20)   NOT NULL DEFAULT '', -- GENERAL / NO_INSCRIPTO / PADRON / SUJETO
    estado              SMALLINT      NOT NULL DEFAULT 1,  -- 1 vigente, 1000 anulada
    usuario             VARCHAR(100)  NOT NULL DEFAULT '',
    creado              DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ret_pago (idpago),
    KEY idx_ret_mes (idregimen, idproveedor, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS percepciones_practicadas (
    id              INT(11)       NOT NULL AUTO_INCREMENT,
    idregimen       INT(11)       NOT NULL,
    fecha           DATE          NOT NULL,
    idcliente       INT(11)       NOT NULL,
    idticket        INT(11)       NOT NULL,
    base            DECIMAL(18,2) NOT NULL DEFAULT 0,      -- con signo (NC en negativo)
    alicuota        DECIMAL(9,4)  NOT NULL DEFAULT 0,
    importe         DECIMAL(18,2) NOT NULL DEFAULT 0,      -- con signo (NC en negativo)
    origen_alicuota VARCHAR(20)   NOT NULL DEFAULT '',
    estado          SMALLINT      NOT NULL DEFAULT 1,
    usuario         VARCHAR(100)  NOT NULL DEFAULT '',
    creado          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_per_ticket (idticket),
    KEY idx_per_mes (idregimen, idcliente, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
