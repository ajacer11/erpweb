-- meteloahoraliyo.sql
-- Consolida las 3 correcciones de base de datos de esta sesion (asientos contables /
-- tesoreria). Es SEGURO correrlo aunque alguna ya este aplicada: cada ALTER se hace
-- solo si la columna todavia no existe, y el MODIFY de descripcion es idempotente
-- (se puede correr de nuevo sin romper nada).
--
-- OJO: esto es solo la parte de base de datos. Los archivos .php con las correcciones
-- de codigo hay que subirlos aparte al VPS (ver lista mas abajo / respuesta del chat).

DELIMITER $$

DROP PROCEDURE IF EXISTS _meteloahoraliyo $$
CREATE PROCEDURE _meteloahoraliyo()
BEGIN

    -- 1) Cuenta contable propia para comisiones a vendedores (antes se mezclaba con
    --    la cuenta de "Proveedores"). Se inicializa con el valor actual de
    --    "proveedores" para no romper el asiento existente; despues se puede
    --    reasignar desde Configuracion > Asientos Automaticos > "Cobros / Ret.".
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cuentaspredeterminadas'
          AND COLUMN_NAME = 'comisionesvend'
    ) THEN
        ALTER TABLE cuentaspredeterminadas ADD COLUMN comisionesvend VARCHAR(20) NULL;
        UPDATE cuentaspredeterminadas SET comisionesvend = proveedores WHERE id = 1;
    END IF;

    -- 2) Cuenta puente "Cheques Rechazados a Cobrar" (separada de Deudores por
    --    Ventas), usada por api_cheques_cartera.php al rechazar un cheque.
    IF NOT EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'cuentaspredeterminadas'
          AND COLUMN_NAME = 'chequesrechazados'
    ) THEN
        ALTER TABLE cuentaspredeterminadas ADD COLUMN chequesrechazados VARCHAR(20) NULL;
    END IF;

    -- 3) cccuentastesoreria.descripcion estaba en VARCHAR(100): se trunca con
    --    "Cobro a "/"Pago a " + razon social larga de cliente/proveedor/vendedor.
    --    Se ensancha a 255 (mismo ancho que ccbancos.detalle y librodiario.detalle).
    ALTER TABLE cccuentastesoreria MODIFY descripcion VARCHAR(255) NOT NULL;

END $$

DELIMITER ;

CALL _meteloahoraliyo();
DROP PROCEDURE _meteloahoraliyo;
