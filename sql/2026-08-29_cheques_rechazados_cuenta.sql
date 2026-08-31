-- Ejecutar en cada base de datos del sistema antes de desplegar el codigo PHP.
--
-- Cuenta puente para cheques de clientes que rebotan (rechazados), separada de
-- "Deudores por Ventas" para poder hacer seguimiento aparte de los cheques
-- rechazados hasta que se cobren o se den de baja definitivamente.
-- Usada en api_cheques_cartera.php (action=reject).
--
-- Queda en NULL hasta que se configure desde
-- Configuracion > Asientos Automaticos > pestaña "Cobros / Ret."; hasta entonces,
-- rechazar un cheque falla con un mensaje explicito en vez de contabilizar en una
-- cuenta inventada o quedar sin asiento.

ALTER TABLE cuentaspredeterminadas
    ADD COLUMN chequesrechazados VARCHAR(20) NULL;
