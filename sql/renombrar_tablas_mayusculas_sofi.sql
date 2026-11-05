-- Nombres de tabla tomados de:
-- C:\Users\angel\Downloads\westit_sofi.sql
--
-- IMPORTANTE:
-- 1. Seleccionar primero la base que se desea corregir:
--      USE `nombre_de_la_base`;
-- 2. El servidor debe tener lower_case_table_names = 0. En Windows/XAMPP
--    normalmente vale 1 y MySQL no puede conservar mayusculas en los nombres.
-- 3. El script es idempotente: si una tabla ya tiene el nombre exacto, no la toca.
-- 4. Las tablas cuyo nombre correcto es completamente minusculo quedan intactas.

DELIMITER $$

DROP PROCEDURE IF EXISTS `_westit_renombrar_tabla_respetando_mayusculas`$$

CREATE PROCEDURE `_westit_renombrar_tabla_respetando_mayusculas`(
    IN p_nombre_actual VARCHAR(64),
    IN p_nombre_correcto VARCHAR(64)
)
BEGIN
    DECLARE v_actual_existe INT DEFAULT 0;
    DECLARE v_correcta_existe INT DEFAULT 0;
    DECLARE v_temporal_existe INT DEFAULT 0;
    DECLARE v_nombre_temporal VARCHAR(64);
    DECLARE v_sql TEXT;
    DECLARE v_mensaje TEXT;

    IF DATABASE() IS NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Seleccione primero la base con USE nombre_de_la_base';
    END IF;

    IF @@lower_case_table_names <> 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Este servidor no conserva mayusculas: lower_case_table_names debe ser 0';
    END IF;

    SELECT COUNT(*)
      INTO v_correcta_existe
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND BINARY TABLE_NAME = BINARY p_nombre_correcto;

    IF v_correcta_existe = 0 THEN
        SELECT COUNT(*)
          INTO v_actual_existe
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND BINARY TABLE_NAME = BINARY p_nombre_actual;

        IF v_actual_existe = 0 THEN
            SET v_mensaje = CONCAT(
                'No existe la tabla origen `', p_nombre_actual,
                '` en `', DATABASE(), '`'
            );
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
        END IF;

        SET v_nombre_temporal = CONCAT('__westit_case_tmp_', p_nombre_actual);

        SELECT COUNT(*)
          INTO v_temporal_existe
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND BINARY TABLE_NAME = BINARY v_nombre_temporal;

        IF v_temporal_existe <> 0 THEN
            SET v_mensaje = CONCAT(
                'Ya existe la tabla temporal `', v_nombre_temporal, '`'
            );
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_mensaje;
        END IF;

        -- Se usa un nombre intermedio porque el cambio es solamente de mayusculas.
        SET v_sql = CONCAT(
            'RENAME TABLE `', REPLACE(DATABASE(), '`', '``'), '`.`',
            REPLACE(p_nombre_actual, '`', '``'), '` TO `',
            REPLACE(DATABASE(), '`', '``'), '`.`',
            REPLACE(v_nombre_temporal, '`', '``'), '`'
        );
        SET @westit_sql = v_sql;
        PREPARE westit_stmt FROM @westit_sql;
        EXECUTE westit_stmt;
        DEALLOCATE PREPARE westit_stmt;

        SET v_sql = CONCAT(
            'RENAME TABLE `', REPLACE(DATABASE(), '`', '``'), '`.`',
            REPLACE(v_nombre_temporal, '`', '``'), '` TO `',
            REPLACE(DATABASE(), '`', '``'), '`.`',
            REPLACE(p_nombre_correcto, '`', '``'), '`'
        );
        SET @westit_sql = v_sql;
        PREPARE westit_stmt FROM @westit_sql;
        EXECUTE westit_stmt;
        DEALLOCATE PREPARE westit_stmt;
    END IF;
END$$

CALL `_westit_renombrar_tabla_respetando_mayusculas`('afipconfig',   'AFIPconfig')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('caja',         'Caja')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('lineadeventa', 'Lineadeventa')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('listas',       'Listas')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('marcas',       'Marcas')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('movdecaja',    'MovdeCaja')$$
CALL `_westit_renombrar_tabla_respetando_mayusculas`('proveedores',  'Proveedores')$$

DROP PROCEDURE `_westit_renombrar_tabla_respetando_mayusculas`$$

DELIMITER ;

-- Verificacion: debe devolver exactamente estos siete nombres.
SELECT TABLE_NAME
  FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
   AND BINARY TABLE_NAME IN (
       BINARY 'AFIPconfig',
       BINARY 'Caja',
       BINARY 'Lineadeventa',
       BINARY 'Listas',
       BINARY 'Marcas',
       BINARY 'MovdeCaja',
       BINARY 'Proveedores'
   )
 ORDER BY TABLE_NAME;
