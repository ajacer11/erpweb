#!/bin/sh
# Antes de reemplazar una base en el corte: guarda lo que se cargó a mano en el VPS durante las pruebas
# y que la re-migración pisaría (logos de depósitos y de configuración). Genera conservar_<base>.sql para
# aplicar DESPUÉS de importar la base nueva:  mysql <base> < conservar_<base>.sql
# Uso (en el VPS): sh conservar_vps.sh westit_nicolas
set -e
DB="$1"
[ -n "$DB" ] || { echo "uso: sh conservar_vps.sh westit_xxx"; exit 1; }
OUT="conservar_${DB}.sql"
{
  mysql -N "$DB" -e "SELECT CONCAT('UPDATE depositos SET logo = 0x', HEX(logo), ' WHERE id = ', id, ' AND nombre = ', QUOTE(nombre), ';') FROM depositos WHERE logo IS NOT NULL AND OCTET_LENGTH(logo) > 0"
  mysql -N "$DB" -e "SELECT CONCAT('UPDATE configuracion SET logo = 0x', HEX(logo), ' ORDER BY id DESC LIMIT 1;') FROM configuracion WHERE logo IS NOT NULL AND OCTET_LENGTH(logo) > 0 ORDER BY id DESC LIMIT 1"
} > "$OUT"
echo "$OUT: $(grep -c '^UPDATE' "$OUT") sentencias ($(du -h "$OUT" | cut -f1))"
