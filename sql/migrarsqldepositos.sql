-- migrarsqldepositos.sql
-- Ejecutar en cada base de datos del sistema ANTES de desplegar el codigo PHP
-- de multideposito. El valor 0/NULL en todos los campos nuevos conserva el
-- comportamiento existente (multideposito deshabilitado): ningun tenant nota
-- ningun cambio hasta que activa el flag desde Configuracion.
--
-- Ver plan completo en: C:\Users\angel\.claude\plans\esta-versi-n-del-sistema-lazy-glade.md

-- ---------------------------------------------------------------------------
-- 1. Flag opt-in + deposito por defecto (bootstrap)
-- ---------------------------------------------------------------------------
ALTER TABLE configuracion
    ADD COLUMN multidepositohabilitado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN deposito_default_id INT(11) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------------
-- 2. Catalogo de depositos
-- ---------------------------------------------------------------------------
CREATE TABLE depositos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL DEFAULT '',
    esprincipal TINYINT(1) NOT NULL DEFAULT 0,
    habilitado TINYINT(1) NOT NULL DEFAULT 1,
    permitenegativo TINYINT(1) NOT NULL DEFAULT 0,
    fechacreacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuariocreacion VARCHAR(100) NOT NULL DEFAULT 'SISTEMA',
    PRIMARY KEY (id),
    UNIQUE KEY uq_depositos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------------
-- 3. Stock por deposito (fuente de verdad operativa, mantenida incrementalmente)
-- ---------------------------------------------------------------------------
CREATE TABLE stock_por_deposito (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_producto INT(11) NOT NULL COMMENT 'alb_productos.id',
    id_deposito INT(11) NOT NULL COMMENT 'depositos.id',
    cantidad DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stockdeposito_prod_dep (id_producto, id_deposito),
    KEY idx_stockdeposito_deposito (id_deposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------------
-- 4. Ledger auditable de movimientos por deposito (espejo de movimientosdestock)
-- ---------------------------------------------------------------------------
CREATE TABLE movimientos_stock_deposito (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_producto INT(11) NOT NULL,
    codigo VARCHAR(200) NOT NULL COMMENT 'redundante con alb_productos.codigo, para reportes sin JOIN',
    id_deposito INT(11) NOT NULL,
    entradas DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    salidas DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    tipomov VARCHAR(200) NOT NULL COMMENT 'mismo vocabulario que movimientosdestock.tipomov',
    origenmov VARCHAR(100) NOT NULL COMMENT 'VENTA, INGRESO, AJUSTE, PRODUCCION, TRANSFERENCIA, BOOTSTRAP',
    tipocomp INT(11) NOT NULL DEFAULT 0,
    numremito INT(11) NOT NULL DEFAULT 0 COMMENT 'id del comprobante/ajuste/orden/transferencia asociado',
    id_movimiento_legacy INT(11) DEFAULT NULL COMMENT 'movimientosdestock.id correspondiente, para cruzar con el kardex global',
    fecha DATETIME NOT NULL,
    usuario VARCHAR(110) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_movdep_prod_dep_fecha (id_producto, id_deposito, fecha),
    KEY idx_movdep_deposito_fecha (id_deposito, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------------
-- 5. Transferencias entre depositos (instantaneas, con anulacion)
-- ---------------------------------------------------------------------------
CREATE TABLE transferencias_deposito (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_deposito_origen INT(11) NOT NULL,
    id_deposito_destino INT(11) NOT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'COMPLETADA' COMMENT 'COMPLETADA | ANULADA',
    observacion VARCHAR(500) NOT NULL DEFAULT '',
    usuario VARCHAR(100) NOT NULL,
    fecha DATETIME NOT NULL,
    usuarioanulacion VARCHAR(100) DEFAULT NULL,
    fechaanulacion DATETIME DEFAULT NULL,
    motivoanulacion VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE transferencias_deposito_lineas (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_transferencia INT(11) NOT NULL,
    id_producto INT(11) NOT NULL,
    codigo VARCHAR(200) NOT NULL,
    cantidad DECIMAL(18,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_translin_transferencia (id_transferencia),
    CONSTRAINT fk_translin_transferencia FOREIGN KEY (id_transferencia)
        REFERENCES transferencias_deposito (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------------
-- 6. Depositos permitidos por usuario (sin filas = sin restriccion)
-- ---------------------------------------------------------------------------
CREATE TABLE usuarios_depositos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(100) NOT NULL,
    id_deposito INT(11) NOT NULL,
    es_predeterminado TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuariodeposito (usuario, id_deposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- ---------------------------------------------------------------------------
-- 7. Deposito de trabajo activo por usuario (mismo rol que alb_usuarios.idcaja)
-- ---------------------------------------------------------------------------
ALTER TABLE alb_usuarios
    ADD COLUMN iddepositoactivo INT(11) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 8. Permiso para cambiar el deposito de trabajo ya elegido
-- ---------------------------------------------------------------------------
ALTER TABLE permisosespeciales
    ADD COLUMN puedecambiardeposito TINYINT(4) NOT NULL DEFAULT 0;

-- ---------------------------------------------------------------------------
-- 9. Deposito de origen (MP) y destino (PT) por orden de produccion
-- ---------------------------------------------------------------------------
ALTER TABLE produccion_ordenes_entradas
    ADD COLUMN id_deposito INT(11) DEFAULT NULL COMMENT 'deposito del que se descontaron los insumos';

ALTER TABLE produccion_ordenes_salidas
    ADD COLUMN id_deposito INT(11) DEFAULT NULL COMMENT 'deposito donde ingresa el producto terminado';
