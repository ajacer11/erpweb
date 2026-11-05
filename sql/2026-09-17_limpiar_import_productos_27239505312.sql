-- =====================================================================
-- Limpieza de la importacion fallida de productos
-- Cliente CUIT 27239505312 -- archivo "MODELO STOCK PRECIOS (1).csv"
-- Generado: 2026-09-17
--
-- MOTIVO
-- El importador interpretaba el punto decimal como separador de miles.
-- Costos y precios quedaron multiplicados por 10, 100 o 1000 segun la
-- cantidad de decimales de cada celda. 41 de las 44 filas quedaron con
-- al menos un importe alterado. El defecto ya esta corregido en
-- api_importar_productos.php.
--
-- MODO DE USO
--   0. Hacer backup completo de la base ANTES de correr nada.
--   1. Ejecutar el PASO 1 entero y leer los resultados.
--   2. Ejecutar el PASO 2 solo si la consulta 1.b no devolvio filas.
--   3. Reimportar el CSV desde Productos / Alta masiva de productos.
--   4. Ejecutar el PASO 4 cuando se haya verificado todo.
--
-- No pegar el archivo completo de una sola vez. Correr paso por paso.
-- =====================================================================


-- =====================================================================
-- PASO 1 - VERIFICACION. No borra nada.
-- =====================================================================

-- 1.a Lista de codigos de la importacion fallida.
--     Tabla normal y no TEMPORARY, para que sobreviva entre pestanas
--     de phpMyAdmin y entre sesiones.
DROP TABLE IF EXISTS tmp_import_fallida_20260917;
CREATE TABLE tmp_import_fallida_20260917 (codigo VARCHAR(128) PRIMARY KEY)
  ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO tmp_import_fallida_20260917 (codigo) VALUES
    ('7790272000831'), ('7790272001036'), ('7790272001029'), ('7790272004112'),
    ('7790272001005'), ('7790272000541'), ('7790272006185'), ('7792180001665'),
    ('7792180007988'), ('7792180001641'), ('7790070265333'), ('7790070265340'),
    ('7790070265357'), ('7790060023707'), ('7790060027019'), ('7790060023684'),
    ('7790070012050'), ('17790070012050'), ('7790070265104'), ('7790070265098'),
    ('7790070265111'), ('7790070265135'), ('7790070265142'), ('7792180000583'),
    ('7792180000569'), ('7793377000027'), ('7793377000317'), ('7798316700808'),
    ('7798316700815'), ('7798122532143'), ('7798122532150'), ('7798081892876'),
    ('7798081899288'), ('7798078733069'), ('7790272000854'), ('7790272000855'),
    ('7793551001864'), ('7793551001895'), ('7793551001888'), ('7793551001857'),
    ('7793551001840'), ('ACEN'), ('ACEV'), ('CE');

-- 1.b Cuantos de los 44 codigos estan realmente en la base.
SELECT COUNT(*) AS productos_encontrados
FROM alb_productos p
JOIN tmp_import_fallida_20260917 t ON t.codigo = p.codigo;

-- 1.c Productos que YA TIENEN MOVIMIENTOS. Estos NO se deben borrar.
--     Si devuelve filas, frenar y revisar caso por caso antes de seguir.
SELECT t.codigo,
       (SELECT COUNT(*) FROM lineadeventa       WHERE prodcod = t.codigo) AS ventas,
       (SELECT COUNT(*) FROM movimientosdestock WHERE codigo  = t.codigo) AS mov_stock,
       (SELECT COUNT(*) FROM ingresosdestock    WHERE codigo  = t.codigo) AS ingresos,
       (SELECT COUNT(*) FROM lineajustes        WHERE codigo  = t.codigo) AS ajustes,
       (SELECT COUNT(*) FROM historicoproductos WHERE codigo  = t.codigo) AS historico
FROM tmp_import_fallida_20260917 t
WHERE (SELECT COUNT(*) FROM lineadeventa       WHERE prodcod = t.codigo) > 0
   OR (SELECT COUNT(*) FROM movimientosdestock WHERE codigo  = t.codigo) > 0
   OR (SELECT COUNT(*) FROM ingresosdestock    WHERE codigo  = t.codigo) > 0
   OR (SELECT COUNT(*) FROM lineajustes        WHERE codigo  = t.codigo) > 0
   OR (SELECT COUNT(*) FROM historicoproductos WHERE codigo  = t.codigo) > 0;

-- 1.d Respaldo de los productos a borrar, por si hay que volver atras.
DROP TABLE IF EXISTS respaldo_import_fallida_20260917;
CREATE TABLE respaldo_import_fallida_20260917 AS
SELECT p.* FROM alb_productos p
JOIN tmp_import_fallida_20260917 t ON t.codigo = p.codigo;

SELECT COUNT(*) AS filas_respaldadas FROM respaldo_import_fallida_20260917;


-- =====================================================================
-- PASO 2 - BORRADO. Ejecutar solo si 1.c no devolvio filas.
-- Borra unicamente productos sin ningun movimiento asociado.
-- =====================================================================

START TRANSACTION;

DELETE r FROM referenciasproductos r
JOIN tmp_import_fallida_20260917 t ON t.codigo = r.codigo
WHERE 1 = 1
  AND NOT EXISTS (SELECT 1 FROM lineadeventa       v WHERE v.prodcod = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM movimientosdestock m WHERE m.codigo  = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM ingresosdestock    i WHERE i.codigo  = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM lineajustes        a WHERE a.codigo  = t.codigo);

DELETE p FROM alb_productos p
JOIN tmp_import_fallida_20260917 t ON t.codigo = p.codigo
WHERE 1 = 1
  AND NOT EXISTS (SELECT 1 FROM lineadeventa       v WHERE v.prodcod = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM movimientosdestock m WHERE m.codigo  = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM ingresosdestock    i WHERE i.codigo  = t.codigo)
  AND NOT EXISTS (SELECT 1 FROM lineajustes        a WHERE a.codigo  = t.codigo);

-- Debe dar 0 si se borraron los 44. Si da otro numero, revisar antes de confirmar.
SELECT COUNT(*) AS quedan_en_base
FROM alb_productos p
JOIN tmp_import_fallida_20260917 t ON t.codigo = p.codigo;

COMMIT;
-- En caso de duda, en lugar de COMMIT ejecutar: ROLLBACK;


-- =====================================================================
-- PASO 3 - OPCIONAL, defensa en profundidad.
-- El importador ya manda la cuenta contable al crear proveedores, asi
-- que este ALTER no es imprescindible. Evita que cualquier otro alta
-- masiva vuelva a fallar por el mismo motivo en esta base.
-- =====================================================================

-- ALTER TABLE `Proveedores`
--   MODIFY `cuentacontable` varchar(20) NOT NULL DEFAULT '0';


-- =====================================================================
-- PASO 4 - Limpieza, una vez reimportado y verificado todo.
-- =====================================================================

-- DROP TABLE tmp_import_fallida_20260917;
-- DROP TABLE respaldo_import_fallida_20260917;
