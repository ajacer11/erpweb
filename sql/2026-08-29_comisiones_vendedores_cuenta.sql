-- Ejecutar en cada base de datos del sistema antes de desplegar el codigo PHP.
--
-- Hasta ahora, el pasivo por comisiones a vendedores (api_vendedores_pagos.php) se
-- posteaba en la misma cuenta contable que "proveedores" (cuentaspredeterminadas.proveedores),
-- porque no existia una cuenta global propia para comisiones. Esto mezclaba en un mismo
-- submayor contable la deuda con proveedores y la deuda con vendedores.
--
-- Se agrega una columna dedicada. Se inicializa con el valor actual de "proveedores"
-- para no romper el asiento existente (mismo comportamiento de antes); el usuario debe
-- revisarla y asignar la cuenta contable correcta desde
-- Configuracion > Asientos Automaticos > pestaña "Cobros / Ret.".
--
-- IMPORTANTE: verificar que el tipo/tamaño de columna coincida con el resto de las
-- columnas de cuentaspredeterminadas (numcuenta de plandecuentas) antes de ejecutar;
-- se asume VARCHAR(20) por ser el mismo patron usado en columnas hermanas como
-- "proveedores" o "deudores".

ALTER TABLE cuentaspredeterminadas
    ADD COLUMN comisionesvend VARCHAR(20) NULL;

UPDATE cuentaspredeterminadas SET comisionesvend = proveedores WHERE id = 1;
