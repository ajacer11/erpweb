-- Ejecutar en cada base de datos del sistema antes de desplegar el codigo PHP.
--
-- Cola de emergencia para comprobantes fiscales que ARCA rechaza (o no responde)
-- o que, habiendo obtenido CAE, no se llegan a grabar en tickets/Lineadeventa por
-- una colision de numeracion u otro error (ver api_facturar.php, funcion
-- guardarEnAuxiliares() y el catch de mas afuera del bloque principal).
--
-- Antes de esto, una falla despues de obtener el CAE hacia rollback de todo:
-- el CAE ya estaba gastado en ARCA (comprobante fiscal real) pero no quedaba
-- registrado en ningun lado del sistema, solo en el log de texto
-- ARCA/<cuit>/log_arca_audit.txt (no consultable, sin detalle de lineas). Paso
-- justo con el CAE 86351061025450 (comprobante 4-160735) el 2026-09-02.
--
-- estado = 'sin_cae'        -> ARCA rechazo o no respondio. No se gasto ningun
--                               numero fiscal real, es seguro reintentar de cero.
-- estado = 'cae_sin_grabar' -> ARCA SI autorizo un CAE real que no se pudo grabar.
--                               No es seguro reintentar (duplicaria el comprobante
--                               ante AFIP): requiere reconciliacion manual.
--
-- payload_json guarda el $input crudo tal cual llego del navegador, como fuente
-- de verdad completa para reconstruir la venta si hiciera falta.

CREATE TABLE auxticket (
  id INT AUTO_INCREMENT PRIMARY KEY,
  estado VARCHAR(20) NOT NULL,
  tipocomp INT NOT NULL,
  PtoVta INT NOT NULL,
  numcomp INT NULL,
  cae VARCHAR(50) NULL,
  fechacae VARCHAR(20) NULL,
  cliente INT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  subtotal10 DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  subtotal0 DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ii DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  iva DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  iva10 DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  fecha DATE NULL,
  usuario VARCHAR(100) NULL,
  vendedor INT NULL,
  mpago1 VARCHAR(200) NULL,
  mpago1imp DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  mpago2 VARCHAR(200) NULL,
  mpago2imp DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  motivo_falla VARCHAR(500) NOT NULL,
  payload_json LONGTEXT NOT NULL,
  resuelto TINYINT(1) NOT NULL DEFAULT 0,
  id_ticket_real INT NULL,
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_estado_resuelto (estado, resuelto)
);

CREATE TABLE auxlineadeventa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_auxticket INT NOT NULL,
  pdv INT NULL,
  Tipocomp INT NULL,
  numcomp INT NULL,
  cliente INT NULL,
  fecha DATE NULL,
  prodcod VARCHAR(200) NULL,
  proddes VARCHAR(200) NULL,
  cant DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  siniva DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  iva DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  ii DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  rubro VARCHAR(200) NULL,
  alicuota DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  costo DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  usuario VARCHAR(100) NULL,
  vendedor INT NULL,
  CONSTRAINT fk_auxlineadeventa_auxticket FOREIGN KEY (id_auxticket) REFERENCES auxticket(id)
);
