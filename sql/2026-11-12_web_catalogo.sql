-- =====================================================================
-- WEB CATÁLOGO (v1) - Tablas por empresa (correr en cada tenant desde el backoffice)
-- Idempotente: se puede correr más de una vez.
-- La habilitación (addon 'web_catalogo') y el dominio viven en la base MAESTRA
-- (ver 2026-11-12_web_catalogo_maestra.sql).
-- =====================================================================

-- Configuración de la web: pares clave/valor (textos, colores, contacto, reglas).
CREATE TABLE IF NOT EXISTS web_config (
    clave VARCHAR(60) NOT NULL PRIMARY KEY,
    valor MEDIUMTEXT NULL,
    fecha_modificacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    usuario_modificacion VARCHAR(50) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Accesos de clientes al área de clientes. Ya existía en algunas bases (web vieja de COEGO):
-- se crea si falta y se le agregan las columnas nuevas.
CREATE TABLE IF NOT EXISTS web_usuarios (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    cliente_id INT NULL,
    nombre_usuario VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(100) NULL,
    rol ENUM('cliente','admin') NULL DEFAULT 'cliente',
    ultimo_login DATETIME NULL,
    activo TINYINT(1) NULL DEFAULT 1,
    UNIQUE KEY uq_web_usuarios_nombre (nombre_usuario),
    KEY idx_web_usuarios_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE web_usuarios
    ADD COLUMN IF NOT EXISTS token_hash CHAR(64) NULL,
    ADD COLUMN IF NOT EXISTS token_tipo VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS token_vence DATETIME NULL,
    ADD COLUMN IF NOT EXISTS intentos_fallidos INT NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bloqueado_hasta DATETIME NULL,
    ADD COLUMN IF NOT EXISTS fecha_alta DATETIME NULL,
    ADD COLUMN IF NOT EXISTS usuario_alta VARCHAR(50) NULL;

-- Solicitudes de registro hechas desde la web (quedan pendientes hasta que la empresa
-- las vincule a un cliente o las rechace).
CREATE TABLE IF NOT EXISTS web_solicitudes_registro (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    razon VARCHAR(150) NOT NULL,
    cuit VARCHAR(20) NULL,
    contacto VARCHAR(100) NULL,
    email VARCHAR(100) NOT NULL,
    telefono VARCHAR(50) NULL,
    localidad VARCHAR(100) NULL,
    mensaje VARCHAR(1000) NULL,
    estado ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'pendiente',
    cliente_id INT NULL,
    fecha_resolucion DATETIME NULL,
    usuario_resolucion VARCHAR(50) NULL,
    ip VARCHAR(45) NULL,
    KEY idx_web_solreg_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pedidos que entraron por la web (el pedido en sí va a pedidosclientes/lineapedidosclientes,
-- igual que uno cargado en el ERP; acá solo queda el origen).
CREATE TABLE IF NOT EXISTS web_pedidos (
    numpedido INT NOT NULL PRIMARY KEY,
    web_usuario_id INT NOT NULL,
    cliente_id INT NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(18,2) NOT NULL DEFAULT 0,
    ip VARCHAR(45) NULL,
    KEY idx_web_pedidos_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Pagos informados por el cliente (transferencias/depósitos) para que la empresa los impute.
CREATE TABLE IF NOT EXISTS web_avisos_pago (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cliente_id INT NOT NULL,
    web_usuario_id INT NOT NULL,
    fecha_pago DATE NOT NULL,
    importe DECIMAL(18,2) NOT NULL,
    medio VARCHAR(30) NOT NULL,
    referencia VARCHAR(100) NULL,
    observaciones VARCHAR(500) NULL,
    archivo VARCHAR(255) NULL,
    estado ENUM('pendiente','procesado','rechazado') NOT NULL DEFAULT 'pendiente',
    nota_interna VARCHAR(500) NULL,
    fecha_resolucion DATETIME NULL,
    usuario_resolucion VARCHAR(50) NULL,
    KEY idx_web_avisos_estado (estado),
    KEY idx_web_avisos_cliente (cliente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Productos destacados en la portada (orden manual).
CREATE TABLE IF NOT EXISTS web_destacados (
    codigo VARCHAR(128) NOT NULL PRIMARY KEY,
    orden INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Características de productos (modelo MercadoLibre), genéricas para cualquier rubro:
--   prod_atributos            catálogo de atributos de la empresa (Largo, Material, Talle...)
--   prod_atributo_opciones    valores posibles de los atributos tipo 'opciones'
--   prod_atributo_plantillas  qué atributos se piden para un rubro o una familia
--   prod_atributo_valores     el valor de cada atributo en cada producto
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS prod_atributos (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    tipo ENUM('texto','numero','opciones','sino') NOT NULL DEFAULT 'texto',
    unidad VARCHAR(20) NULL,
    filtrable TINYINT(1) NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    UNIQUE KEY uq_prod_atributos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS prod_atributo_opciones (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    atributo_id INT NOT NULL,
    valor VARCHAR(100) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_prod_atr_opc (atributo_id, valor),
    CONSTRAINT fk_prod_atr_opc_atr FOREIGN KEY (atributo_id) REFERENCES prod_atributos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS prod_atributo_plantillas (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    atributo_id INT NOT NULL,
    ambito ENUM('rubro','familia') NOT NULL,
    ambito_valor VARCHAR(200) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_prod_atr_plant (atributo_id, ambito, ambito_valor),
    KEY idx_prod_atr_plant_ambito (ambito, ambito_valor),
    CONSTRAINT fk_prod_atr_plant_atr FOREIGN KEY (atributo_id) REFERENCES prod_atributos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS prod_atributo_valores (
    codigo VARCHAR(128) NOT NULL,
    atributo_id INT NOT NULL,
    valor VARCHAR(255) NOT NULL,
    PRIMARY KEY (codigo, atributo_id),
    KEY idx_prod_atr_val_atr (atributo_id, valor),
    CONSTRAINT fk_prod_atr_val_atr FOREIGN KEY (atributo_id) REFERENCES prod_atributos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
