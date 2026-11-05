-- Renombra las tablas cuyo nombre correcto lleva mayúsculas (según el esquema
-- de referencia westit_sofi.sql), dejando intactas todas las demás tablas.
--
-- Necesario porque en Windows MySQL/MariaDB corre con lower_case_table_names=1,
-- que fuerza todos los nombres de tabla a minúscula al exportar. En Linux
-- (lower_case_table_names=0, valor típico en CWP) los nombres de tabla son
-- sensibles a mayúsculas/minúsculas, y el código PHP de la app espera estos
-- 7 nombres exactamente con la capitalización original.
--
-- Es idempotente: si la tabla en minúscula no existe, o si la tabla correcta
-- ya existe, no hace nada para ese par. Se puede correr contra cualquier base
-- de un tenant sin riesgo.
--
-- Uso:
--   mysql -uroot -p'CONTRASEÑA' NOMBRE_DE_LA_BASE < 2026-09-06_renombrar_tablas_case.sql

DELIMITER $$

DROP PROCEDURE IF EXISTS _renombrar_si_corresponde $$
CREATE PROCEDURE _renombrar_si_corresponde(IN nombre_actual VARCHAR(200), IN nombre_correcto VARCHAR(200))
BEGIN
    DECLARE existe_actual INT DEFAULT 0;
    DECLARE existe_correcto INT DEFAULT 0;
    DECLARE hubo_error INT DEFAULT 0;
    DECLARE msg_error TEXT DEFAULT '';

    -- Un fallo en el RENAME (permisos, colisión, etc.) no debe abortar el
    -- resto del script: se informa y se continúa con la siguiente tabla.
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1 msg_error = MESSAGE_TEXT;
        SET hubo_error = 1;
        SELECT CONCAT('ERROR al renombrar ', nombre_actual, ' -> ', nombre_correcto, ': ', msg_error) AS resultado;
    END;

    SELECT COUNT(*) INTO existe_actual
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = BINARY nombre_actual;

    SELECT COUNT(*) INTO existe_correcto
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND BINARY TABLE_NAME = BINARY nombre_correcto;

    IF existe_actual = 1 AND existe_correcto = 0 THEN
        SET @sql = CONCAT('RENAME TABLE `', nombre_actual, '` TO `', nombre_correcto, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        IF hubo_error = 0 THEN
            SELECT CONCAT('Renombrada: ', nombre_actual, ' -> ', nombre_correcto) AS resultado;
        END IF;
    ELSEIF existe_correcto = 1 THEN
        SELECT CONCAT('Sin cambios (ya existe con el nombre correcto): ', nombre_correcto) AS resultado;
    ELSE
        SELECT CONCAT('Sin cambios (no existe en esta base): ', nombre_actual) AS resultado;
    END IF;
END $$

DELIMITER ;

CALL _renombrar_si_corresponde('afipconfig',   'AFIPconfig');
CALL _renombrar_si_corresponde('caja',         'Caja');
CALL _renombrar_si_corresponde('lineadeventa', 'Lineadeventa');
CALL _renombrar_si_corresponde('listas',       'Listas');
CALL _renombrar_si_corresponde('marcas',       'Marcas');
CALL _renombrar_si_corresponde('movdecaja',    'MovdeCaja');
CALL _renombrar_si_corresponde('proveedores',  'Proveedores');

DROP PROCEDURE _renombrar_si_corresponde;
