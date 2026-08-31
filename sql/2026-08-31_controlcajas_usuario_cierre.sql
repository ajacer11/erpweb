-- Ejecutar en cada base de datos del sistema antes de desplegar el codigo PHP.
--
-- Registra quien efectivamente cerro la caja, que puede ser distinto de quien
-- la abrio (por ejemplo cuando un administrador la cierra por otro usuario
-- desde Administracion de Usuarios, ver api_cajas.php accion admin_close_box).
-- Sin esta columna no hay forma de distinguir un cierre propio de uno forzado
-- por un admin en el reporte de Aperturas y Cierres de caja.
-- Usada en api_cajas.php (close_box, admin_close_box) y
-- api_reporte_cajas_usuarios.php (action=get_aperturas_cierres).

ALTER TABLE controlcajas
    ADD COLUMN usuario_cierre VARCHAR(100) NULL AFTER usuario;
