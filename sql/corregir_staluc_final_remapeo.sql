-- Corrección posterior a la consolidación STALUC.
-- staluc_new es la fuente autoritativa de maestros y saldos físicos de caja.

USE staluc_final;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS staluc_final._mig_map_mediosdepago (
    id_old INT PRIMARY KEY,
    id_new INT NOT NULL,
    UNIQUE KEY uq_medio_nuevo (id_new)
);

INSERT INTO staluc_final._mig_map_mediosdepago (id_old,id_new) VALUES
    (1,101),(2,102),(3,103),(4,104),(5,105),(6,106),(7,107),
    (11,111),(12,112),(13,113),(14,114),(15,115),(16,116),(17,117),(19,108)
ON DUPLICATE KEY UPDATE id_new=VALUES(id_new);

START TRANSACTION;

UPDATE staluc_final.auxticket d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.auxticket d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.cccuentastesoreria d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.idcuenta SET d.idcuenta=m.id_new;
UPDATE staluc_final.ccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.ccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago2 SET d.mediopago2=m.id_new;
UPDATE staluc_final.ccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.ccvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediopago SET d.mediopago=m.id_new;
UPDATE staluc_final.factdecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.factdecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.lineadecompra d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.lineadeventa d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago22 SET d.mpago22=m.id_new;
UPDATE staluc_final.moviemientosccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.moviemientosccclientes d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.moviemientosccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.moviemientosccproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;
UPDATE staluc_final.pagoaproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagoaproveedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagosprov d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.medio SET d.medio=m.id_new;
UPDATE staluc_final.pagosvende d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagosvende d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.pagosvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.pagosvendedores d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.recibos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.medio SET d.medio=m.id_new;
UPDATE staluc_final.rrhhcc d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mediodepago SET d.mediodepago=m.id_new;
UPDATE staluc_final.rrhhpagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp1 SET d.mp1=m.id_new;
UPDATE staluc_final.rrhhpagos d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mp2 SET d.mp2=m.id_new;
UPDATE staluc_final.tickets d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago1 SET d.mpago1=m.id_new;
UPDATE staluc_final.tickets d JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=d.mpago2 SET d.mpago2=m.id_new;

DELETE p
FROM staluc_final.mediosdepago p
JOIN staluc_final._mig_map_mediosdepago m ON m.id_old=p.id;

-- Los controles abiertos del sistema legacy se cierran al corte. Su saldo final
-- permanece como registro histórico, pero no se suma a las cajas físicas vigentes.
UPDATE staluc_final.controlcajas c
JOIN staluc_old.controlcajas o ON o.id=c.id
SET c.estado=0,
    c.cerradael='2026-08-29 23:59:59',
    c.usuario_cierre='MIGRACION'
WHERE o.estado=1;

COMMIT;
SET FOREIGN_KEY_CHECKS = 1;
