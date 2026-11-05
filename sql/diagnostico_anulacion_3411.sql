-- Sólo lectura. El listado llama "Pago 3411" al ID interno, mientras que
-- su numcomp es 3407. No confundir idpago con numcomp en datos migrados.
SELECT id, numcomp, proveedor, fecharecibo, total, estado
FROM pagoaproveedores
WHERE id IN (3407, 3411) OR numcomp IN (3407, 3411)
ORDER BY id;

-- Esta consulta no modifica datos y devuelve la cabecera aunque no encuentre movimientos.
SELECT p.id, p.numcomp, p.estado, p.proveedor, p.total, p.fecharecibo,
  (SELECT COUNT(*) FROM moviemientosccproveedores m
    WHERE m.tabla = 'pagoaproveedores' AND m.proveedor = p.proveedor AND m.idpago = p.id) AS cc_por_id,
  (SELECT COUNT(*) FROM moviemientosccproveedores m
    WHERE m.tabla = 'pagoaproveedores' AND m.proveedor = p.proveedor
      AND m.compasoc <> 0 AND m.compasoc IN (p.id, p.numcomp)) AS cc_por_compasoc,
  (SELECT COUNT(*) FROM moviemientosccproveedores m
    WHERE m.tabla = 'pagoaproveedores' AND m.proveedor = p.proveedor
      AND m.pagos = p.total AND m.fecha = DATE(p.fecharecibo)) AS cc_por_importe_fecha,
  (SELECT COUNT(*) FROM cccuentastesoreria t
    WHERE t.tabla = 'pagoaproveedores' AND t.compasoc = p.id AND t.descripcion LIKE 'Anula%') AS reversos_tesoreria,
  (SELECT COUNT(*) FROM librodiario l
    WHERE l.tabla = 'pagoaproveedores' AND l.compasoc = p.id AND l.detalle LIKE 'Anula%') AS lineas_asiento_inverso
FROM pagoaproveedores p WHERE p.id = 3411;

SELECT m.id, m.idpago, m.compasoc, m.fecha, m.pagos, m.facturas, m.tabla
FROM moviemientosccproveedores m
WHERE m.proveedor = (SELECT proveedor FROM pagoaproveedores WHERE id = 3411)
  AND (m.idpago = 3411 OR m.compasoc = 3407 OR m.pagos = 350000)
ORDER BY m.id DESC LIMIT 30;

SELECT t.id, t.compasoc, t.tabla, t.descripcion, t.debe, t.haber
FROM cccuentastesoreria t
WHERE t.compasoc = 3411 OR t.descripcion LIKE '%#3411%'
ORDER BY t.id DESC LIMIT 20;

SELECT l.id, l.compasoc, l.tabla, l.detalle, l.debe, l.haber
FROM librodiario l
WHERE l.compasoc = 3411 OR l.detalle LIKE '%#3411%'
ORDER BY l.id DESC LIMIT 20;
