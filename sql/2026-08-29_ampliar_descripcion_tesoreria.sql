-- Ejecutar en cada base de datos del sistema.
--
-- cccuentastesoreria.descripcion quedó en VARCHAR(100), mientras que las columnas
-- equivalentes ccbancos.detalle y librodiario.detalle ya están en VARCHAR(255).
-- El valor que se graba ahí ("Cobro a " / "Pago a " + razón social del cliente,
-- proveedor o vendedor) puede superar los 100 caracteres (alb_clientes.razon permite
-- hasta 200, y Proveedores.Razon es TEXT sin límite), lo que produce
-- SQLSTATE[22001]: Data too long for column 'descripcion' en api_cobros.php,
-- api_pagos_proveedores.php y api_vendedores_pagos.php.
--
-- Ensanchar un VARCHAR es una operación segura: no trunca ni afecta los datos
-- existentes, solo permite que entren strings más largos de ahora en más.

ALTER TABLE cccuentastesoreria
    MODIFY descripcion VARCHAR(255) NOT NULL;
