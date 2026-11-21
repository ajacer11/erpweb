#!/bin/bash
# Corte COEGO (legacy -> sistema nuevo). Corre EN EL VPS como root.
#   bash corte_coego.sh /root/migracion_2026_11/westit_coego.sql.gz [FORZAR]
#
# 1. Si la base ya está en producción (hay movimientos cargados en el sistema nuevo después del último
#    corte), NO hace nada, salvo que se pase FORZAR (se perderían esos movimientos).
# 2. Backup completo de westit_coego.
# 3. Conserva lo que no viene del legacy: configuración de la web (web_*, prod_atributo*) y logos.
# 4. Reemplaza la base con la migrada, reaplica lo conservado y deja la marca _mig_corte.
set -e
set -o pipefail
DB=${DB:-westit_coego}   # DB=otra solo para pruebas
NUEVA="$1"
FORZAR="$2"
DIR=/root/migracion_2026_11
TS=$(date +%Y%m%d_%H%M)
cd "$DIR"
[ -f "$NUEVA" ] || { echo "ERROR: no existe $NUEVA"; exit 1; }
gzip -t "$NUEVA" || { echo "ERROR: $NUEVA está dañado"; exit 1; }

TABLAS_MOV="tickets controlcajas pedidosclientes pagos librodiario ccclientes facturasdecompra presupuestosclientes cccuentastesoreria"

# ---------------------------------------------------------------- 1. ¿ya se usa el sistema nuevo?
if [ "$(mysql -N -e "select count(*) from information_schema.tables where table_schema='$DB' and table_name='_mig_corte'")" = "1" ]; then
  USADO=""
  for t in $TABLAS_MOV; do
    antes=$(mysql -N $DB -e "select coalesce(max(filas),0) from _mig_corte where tabla='$t'")
    ahora=$(mysql -N $DB -e "select count(*) from \`$t\`" 2>/dev/null || echo 0)
    [ "$ahora" -gt "$antes" ] && USADO="$USADO $t($antes->$ahora)"
  done
  if [ -n "$USADO" ]; then
    echo "ATENCION: el sistema nuevo ya tiene movimientos cargados después del corte:$USADO"
    if [ "$FORZAR" != "FORZAR" ]; then
      echo "No se reemplazó nada. Si de verdad hay que pisarlos, correr con FORZAR (se pierden esos movimientos)."
      exit 2
    fi
    echo "FORZAR: se reemplaza igual (queda backup)."
  fi
fi

# ---------------------------------------------------------------- 2. backup
mysqldump --single-transaction --routines --triggers $DB | gzip > "backup_${DB}_antes_corte_${TS}.sql.gz"
echo "backup: $DIR/backup_${DB}_antes_corte_${TS}.sql.gz ($(du -h backup_${DB}_antes_corte_${TS}.sql.gz | cut -f1))"
mysql -N -e "select table_name, column_name, column_type from information_schema.columns where table_schema='$DB' and table_name not like '\_mig%' order by 1,2" > "estructura_antes_${TS}.txt"

# ---------------------------------------------------------------- 3. conservar
WEB=$(mysql -N -e "select group_concat(table_name separator ' ') from information_schema.tables where table_schema='$DB' and (table_name like 'web\_%' or table_name like 'prod\_atributo%')")
if [ -n "$WEB" ] && [ "$WEB" != "NULL" ]; then
  mysqldump --single-transaction $DB $WEB > "conservar_web_${TS}.sql"
  echo "web conservada: $WEB"
fi
{
  mysql -N $DB -e "SELECT CONCAT('UPDATE depositos SET logo = 0x', HEX(logo), ' WHERE id = ', id, ' AND nombre = ', QUOTE(nombre), ';') FROM depositos WHERE logo IS NOT NULL AND OCTET_LENGTH(logo) > 0"
  mysql -N $DB -e "SELECT CONCAT('UPDATE configuracion SET logo = 0x', HEX(logo), ' ORDER BY id DESC LIMIT 1;') FROM configuracion WHERE logo IS NOT NULL AND OCTET_LENGTH(logo) > 0 ORDER BY id DESC LIMIT 1"
} > "conservar_logos_${TS}.sql"
# Datos de la empresa: los del VPS (se editan desde el ERP) mandan sobre los que arma la migración
mysqldump --single-transaction --no-create-info --replace $DB datosempresa > "conservar_empresa_${TS}.sql"

# ---------------------------------------------------------------- 4. reemplazar
mysql -e "DROP DATABASE IF EXISTS $DB; CREATE DATABASE $DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
zcat "$NUEVA" | mysql $DB
mysql $DB < "conservar_logos_${TS}.sql"
mysql $DB -e "DELETE FROM datosempresa" && mysql $DB < "conservar_empresa_${TS}.sql"
[ -f "conservar_web_${TS}.sql" ] && mysql $DB < "conservar_web_${TS}.sql"

mysql $DB -e "CREATE TABLE _mig_corte (tabla VARCHAR(64) NOT NULL PRIMARY KEY, filas INT NOT NULL, fecha DATETIME NOT NULL) ENGINE=InnoDB"
for t in $TABLAS_MOV; do
  mysql $DB -e "INSERT INTO _mig_corte SELECT '$t', COUNT(*), NOW() FROM \`$t\`" 2>/dev/null || true
done

# ---------------------------------------------------------------- 5. controles
mysql -N -e "select table_name, column_name, column_type from information_schema.columns where table_schema='$DB' and table_name not like '\_mig%' order by 1,2" > "estructura_despues_${TS}.txt"
if diff "estructura_antes_${TS}.txt" "estructura_despues_${TS}.txt" > "estructura_diff_${TS}.txt"; then
  echo "estructura: idéntica a la anterior"
else
  echo "estructura: diferencias (< antes, > después):"; head -40 "estructura_diff_${TS}.txt"
fi
mysql $DB -e "select (select count(*) from alb_productos) productos, (select count(*) from alb_clientes) clientes, (select count(*) from tickets) tickets, (select max(fecha) from tickets) ultimo_ticket, (select max(numpedido) from pedidosclientes) ultimo_pedido, (select count(*) from web_config) web_config, (select count(*) from web_usuarios) web_usuarios"
echo "OK corte terminado $TS"
