START TRANSACTION;
ALTER TABLE configuracion
    ADD COLUMN IF NOT EXISTS mostrarvendedor TINYINT(1) NOT NULL DEFAULT 1;
ALTER TABLE cuentaspredeterminadas
    ADD COLUMN IF NOT EXISTS comisionesvend VARCHAR(20) NULL;
UPDATE cuentaspredeterminadas
    SET comisionesvend = proveedores
    WHERE id = 1 AND comisionesvend IS NULL;
ALTER TABLE cuentaspredeterminadas
    ADD COLUMN IF NOT EXISTS chequesrechazados VARCHAR(20) NULL;
ALTER TABLE cccuentastesoreria
    MODIFY descripcion VARCHAR(255) NOT NULL;
ALTER TABLE configuracion
    ADD COLUMN IF NOT EXISTS multidepositohabilitado TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS deposito_default_id INT(11) NOT NULL DEFAULT 0;
CREATE TABLE IF NOT EXISTS depositos (
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
CREATE TABLE IF NOT EXISTS stock_por_deposito (
    id INT(11) NOT NULL AUTO_INCREMENT,
    id_producto INT(11) NOT NULL COMMENT 'alb_productos.id',
    id_deposito INT(11) NOT NULL COMMENT 'depositos.id',
    cantidad DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_stockdeposito_prod_dep (id_producto, id_deposito),
    KEY idx_stockdeposito_deposito (id_deposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
CREATE TABLE IF NOT EXISTS movimientos_stock_deposito (
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
CREATE TABLE IF NOT EXISTS transferencias_deposito (
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
CREATE TABLE IF NOT EXISTS transferencias_deposito_lineas (
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
CREATE TABLE IF NOT EXISTS usuarios_depositos (
    id INT(11) NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(100) NOT NULL,
    id_deposito INT(11) NOT NULL,
    es_predeterminado TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuariodeposito (usuario, id_deposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
ALTER TABLE alb_usuarios
    ADD COLUMN IF NOT EXISTS iddepositoactivo INT(11) DEFAULT NULL;
ALTER TABLE permisosespeciales
    ADD COLUMN IF NOT EXISTS puedecambiardeposito TINYINT(4) NOT NULL DEFAULT 0;
ALTER TABLE produccion_ordenes_entradas
    ADD COLUMN IF NOT EXISTS id_deposito INT(11) DEFAULT NULL COMMENT 'deposito del que se descontaron los insumos';
ALTER TABLE produccion_ordenes_salidas
    ADD COLUMN IF NOT EXISTS id_deposito INT(11) DEFAULT NULL COMMENT 'deposito donde ingresa el producto terminado';
ALTER TABLE depositos
    ADD COLUMN IF NOT EXISTS estransito TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'deposito especial de uso interno (no seleccionable como deposito de trabajo/ajuste)';
ALTER TABLE transferencias_deposito
    ADD COLUMN IF NOT EXISTS id_transporte INT(11) DEFAULT NULL COMMENT 'transportes.id, opcional, quien traslada la mercaderia',
    ADD COLUMN IF NOT EXISTS fecha_recepcion DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS usuario_recepcion VARCHAR(100) DEFAULT NULL;
ALTER TABLE transferencias_deposito
    ADD COLUMN IF NOT EXISTS id_deposito_transito INT(11) DEFAULT NULL COMMENT 'deposito de transito usado en esta transferencia (NULL si fue directa, sin confirmacion)';
UPDATE transferencias_deposito t
    JOIN depositos d ON d.estransito = 1
    SET t.id_deposito_transito = d.id
    WHERE t.estado = 'EN_TRANSITO' AND t.id_deposito_transito IS NULL;
ALTER TABLE transferencias_deposito
    ADD COLUMN IF NOT EXISTS fecha_aceptacion_transito DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS usuario_aceptacion_transito VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS resoluciontransito VARCHAR(30) DEFAULT NULL COMMENT 'DEVUELTA | AJUSTADA | ENVIADA_DESTINO -- si el remanente en transito se resolvio a mano',
    ADD COLUMN IF NOT EXISTS usuarioresoluciontransito VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS fecharesoluciontransito DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS motivoresoluciontransito VARCHAR(500) DEFAULT NULL;
ALTER TABLE transferencias_deposito_lineas
    ADD COLUMN IF NOT EXISTS cantidad_recibida DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto de esta linea ya esta en el deposito destino',
    ADD COLUMN IF NOT EXISTS cantidad_devuelta DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto volvio al origen por decision administrativa',
    ADD COLUMN IF NOT EXISTS cantidad_ajustada DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'cuanto se dio de baja (perdida) por decision administrativa';
UPDATE transferencias_deposito
    SET fecha_aceptacion_transito = fecha, usuario_aceptacion_transito = usuario
    WHERE estado = 'EN_TRANSITO' AND fecha_aceptacion_transito IS NULL;
UPDATE transferencias_deposito_lineas l
    JOIN transferencias_deposito t ON t.id = l.id_transferencia
    SET l.cantidad_recibida = l.cantidad
    WHERE t.estado = 'COMPLETADA' AND l.cantidad_recibida = 0;
ALTER TABLE controlcajas
    ADD COLUMN IF NOT EXISTS usuario_cierre VARCHAR(100) NULL;
COMMIT;