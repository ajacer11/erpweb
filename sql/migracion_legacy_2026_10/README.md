# Migración legacy → sistema nuevo (COEGO, NADIA RUGE, NICOLAS PERAGGINI, MENA GROUP)

Ensayo realizado el 16/10/2026. Se ejecuta **solo por consola** en la PC de desarrollo (XAMPP local,
root sin contraseña), copiando esta carpeta a `C:\mig_legacy\` (algunas rutas de trabajo están fijas ahí).

| Cliente | CUIT | Base legacy | Base local de trabajo | Base en el VPS | PV | Estado |
|---|---|---|---|---|---|---|
| COEGO, CLAUDIO | 20211411466 | `coego` | `wst_db_20211411466` | `westit_coego` | 3 | **corte hecho el 20/11/2026**; re-actualizar desde legacy: `python actualizar_coego.py` |
| RUGE SUBIRANA, NADIA | 27326319363 | `kinetica_nadia1` | `wst_db_27326319363` | `westit_nadia` | 5 | **en producción desde el 16/10/2026: NO volver a migrar** |
| PERAGGINI, PABLO NICOLAS | 20281563859 | `kinetica_nicolas` (el más viejo) | `wst_db_20281563859` | `westit_nicolas` | 3 y 4 | en prueba: **falta el corte** |
| MENA GROUP SA | 30714902888 | `kinetica_mena` | `wst_db_30714902888` | `westit_mena` | 1 | en prueba desde el 16/10/2026: **falta el corte** |

## Corte real (COEGO, NICOLAS y MENA)

**No se vuelven a crear como clientes.** COEGO y NICOLAS ya están dados de alta en el backoffice
(`westit_ClientesSistema`: cliente, tabla del CUIT y fila de conexión). El corte solo reemplaza las bases
`westit_coego` / `westit_nicolas`; ningún paso toca la base maestra. `master_alta.sql` queda como registro
y está anulado (todo comentado).

Lo que se cargue en `westit_coego` / `westit_nicolas` durante las pruebas se pierde al reemplazarlas
(ventas de prueba, cajas abiertas, cambios de configuración), salvo lo que se conserva en el paso 6.

0. **Cerrar los sistemas viejos** (que nadie facture durante el corte) y descargar las bases:
   ```
   set LEGACY_HOST=179.43.126.39
   set LEGACY_USER=...
   set LEGACY_PASS=...
   php paso0_descargar.php coego nicolas mena
   ```
1. Referencias (solo si cambió el esquema del sistema):
   - `php paso1_referencia.php` — arma `ref_completa` (plantilla de la que parte NICOLAS).
   - Estructura de **westit_sofi** del VPS como base local **`ref_sofi`**: todas las bases migradas se dejan
     idénticas a ella (tablas, campos, tipo, NULL, default, collation e índices; ver `alinear.php`).
     ```
     python vps.py "mysqldump --no-data --skip-triggers --skip-routines westit_sofi > /root/migracion_2026_10/estructura_westit_sofi.sql"
     (bajarlo y)  mysql -uroot -e "DROP DATABASE IF EXISTS ref_sofi; CREATE DATABASE ref_sofi"
                  mysql -uroot ref_sofi < estructura_westit_sofi.sql
     ```
2. `php paso2_legacy_vb.php coego` / `php paso2_legacy_vb.php mena` — legacy VB.NET: copia, actualizaciones del backoffice, migraciones,
   catálogos contables, medios de pago, datos de ARCA, logo, contraseñas y estructura idéntica a sofi.
3. NICOLAS — las contraseñas de los usuarios del negocio van por variable de entorno (no se escriben en archivos):
   ```
   set PW_NICOLAS=...
   set PW_QUESERIA=...
   set PW_RICSAN=...
   php paso3_nicolas.php
   ```
   Mapea el legacy viejo al esquema nuevo, arma los depósitos (PV 3 La Quesería - Sargento Cabral 396 /
   PV 4 Ric San - Paso de los Andes 588) con su stock, crea Nicolas (admin, ambos depósitos), Queseria
   (La Quesería) y Ricsan (Ric San), y carga el **saldo de efectivo** del libro de caja legacy al momento
   del corte: PV 3 → Puesto 2, PV 4 → Puesto 3 (`$PV_A_PUESTO`).
4. `php paso5_validar.php wst_db_xxx src_xxx` — controles (contraseñas, contabilidad, medios, APIs reales).
5. `php paso4_exportar.php wst_db_xxx` — dump con mayúsculas correctas para Linux y DEFINER = root@localhost.
6. En el VPS, **antes de reemplazar**, guardar lo cargado a mano durante las pruebas (logos de depósitos y de
   configuración): `sh conservar_vps.sh westit_xxx` → `conservar_westit_xxx.sql`.
7. Subir y reemplazar la base (credenciales SSH en `sistema/.env`), y aplicar lo conservado:
   ```
   export MSYS_NO_PATHCONV=1      (Git Bash)
   python vps.py --put export/wst_db_xxx.sql.gz /root/migracion_2026_10/westit_xxx.sql.gz
   python vps.py "mysqldump --single-transaction westit_xxx | gzip > /root/migracion_2026_10/backup_westit_xxx_antes_corte.sql.gz"
   python vps.py "mysql -e 'DROP DATABASE IF EXISTS westit_xxx; CREATE DATABASE westit_xxx CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; zcat /root/migracion_2026_10/westit_xxx.sql.gz | mysql westit_xxx; mysql westit_xxx < conservar_westit_xxx.sql"
   ```
8. `php validar_vps.php` en el VPS — resuelve los CUIT con el mismo código del login y conecta
   (solo lee la base maestra; no da de alta nada).

## Bases ya publicadas

`php alinear_vps.php westit_xxx` (en el VPS, con backup previo) deja una base publicada idéntica a
westit_sofi sin re-migrarla. Es lo que se usa para NADIA, que ya está en producción.

## Decisiones tomadas

- Contraseñas: `passweb = password_hash(password legacy)`; cada usuario entra con la de siempre.
  El usuario `angel` queda con la contraseña maestra (la fija el backoffice).
- Estructura idéntica a westit_sofi, con estas excepciones que se conservan a propósito:
  `lineapresupuestosclientes.impint` (la usa api_presupuestos.php; **a sofi le falta**), `Caja.ptovta`
  (historial de NICOLAS) y las tablas de auditoría `_mig_renumerados` / `cobros_backup`.
  Los `double` del legacy pasan a `decimal(18,2)` como en sofi (se redondean a 2 decimales).
- Netos: en el legacy el precio final se actualizaba y el neto quedaba viejo (NICOLAS: 4.201 de 6.176
  productos), y la facturación rechazaba la venta ("El precio no coincide con la Lista"). paso2/paso3
  recalculan neto e IVA de las 8 listas desde el precio final con `recalcular_netos.sql`
  (`neto = (precio − impuesto interno) / (1 + alícuota)`; el impuesto interno no lleva IVA).
- Saldo de efectivo: el sistema nuevo lo calcula desde tesorería; se carga como movimiento de apertura
  (tesorería + asiento Caja contra 33200000 RESULTADOS NO ASIGNADOS). COEGO no tenía saldos de caja.
- La plantilla del backoffice traía `cuentaspredeterminadas.mercaderia = 11301002` (no existe): 11301001;
  `chequesrechazados` → 11201001, `comisionestarjetas` → 42009001.
- NADIA: el efectivo legacy se separa en "Efectivo Caja 1..6" (uno por puesto); MercadoPago → Bancos.
  Saldo inicial: $41.490 (cierre de la Caja 3 del 15/10) cargado en Efectivo Caja 1.
- NICOLAS: 36 tickets internos con número repetido se renumeran (tabla `_mig_renumerados`).
  CONSUMO INTERNO → cuenta 11501001. Stock legacy stock3/stock4 → depósito La Quesería / Ric San.
- COEGO: en el maestro se agrega la fila "COEGO - SISTEMA NUEVO"; las filas legacy y de prueba siguen igual.
- Triggers/rutinas del legacy (DEFINER kinetica / Liyo) se recrean con root@localhost: con un DEFINER
  inexistente el INSERT en tickets falla (error 1449).
- MENA: el legacy está en uso; la descarga es solo lectura (`mysqldump --single-transaction --skip-lock-tables`).
  Permisos: `nivelesdeacceso` (un registro por usuario y botón VB) se traduce al menú nuevo con
  `permisos_vb.php` (una opción queda permitida si el usuario tenía el botón Y el botón de esa sección en el
  menú principal); `permisosespeciales` es la misma tabla en los dos sistemas y pasa tal cual.
  El efectivo estaba asociado por número de puesto (1..7) y los puestos tienen ids 15..27: se reasocia al id;
  los puestos 8, 9 y 10 no tenían medio de efectivo y se les crea. El libro diario usaba 11301002 y 42002002,
  que no estaban en su plan: se crean (`crearCuentasHuerfanas`, también aplica a COEGO).
  En el backoffice ya existía el cliente: `mena_alta.sql` agregó la fila "MENA GROUP - SISTEMA NUEVO"
  y se deshabilitó la fila del legacy (la web solo ofrece el sistema nuevo; el escritorio usa la base de clientes del otro VPS). No se repite en el corte.

## COEGO: corte del 20/11/2026 y re-actualización desde legacy

`python actualizar_coego.py` (en `C:\mig_legacy`, con XAMPP/MySQL prendido) hace todo de punta a punta:
descarga el legacy (solo lectura), toma del VPS las actualizaciones del backoffice y la estructura de westit_sofi,
migra / valida / exporta y, si todo da OK, corre `corte_coego.sh` en el VPS (`/root/migracion_2026_11`):
backup de `westit_coego`, conserva la configuración de la web (`web_*`, `prod_atributo*`), los logos y los datos
de la empresa, reemplaza la base y deja la marca `_mig_corte`. Después sincroniza las fotos nuevas o cambiadas
del FTP de la web vieja, en el VPS (`fotos_sync_coego_vps.py` + `normalizar_foto.php`, en `/root/migracion_2026_11`).
La clave del FTP está en `/root/migracion_2026_11/.ftp_coego` (root, chmod 600); también se puede correr suelto:
`python3 fotos_sync_coego_vps.py [--simular]`.

**Protección:** si el sistema nuevo ya tiene movimientos cargados después del último corte (tickets, cajas,
pedidos, cobros, asientos...), `corte_coego.sh` no reemplaza nada. `--forzar` lo pisa igual (queda backup).

- Si el padrón de ARCA no responde, la migración sigue (`PADRON_OPCIONAL=1`) y el corte repone los datos de la
  empresa que ya tenía el VPS. Sin esa variable, paso2 se detiene en vez de cargar datos por defecto.
- `ACTUALIZACIONES_SQL` permite usar el `actualizaciones.sql` del backoffice del VPS (más nuevo que el local).
- `master_coego_produccion.sql`: deshabilita las filas legacy de COEGO en la maestra, agrega el dominio
  `pescahermanos.com.ar` en `web_sitios` y el módulo `api_productos`. Ya aplicado; no hace falta repetirlo.
