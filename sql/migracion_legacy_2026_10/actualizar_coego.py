#!/usr/bin/env python3
"""
Vuelve a traer COEGO del legacy al sistema nuevo (westit_coego en el VPS), de punta a punta.
Para usar ANTES de arrancar con el sistema nuevo, si en el legacy se siguió vendiendo/cargando.

    cd C:\\mig_legacy
    python actualizar_coego.py            (con XAMPP/MySQL local prendido)

Qué hace:
  1. Descarga la base legacy `coego` (solo lectura) -> src_coego local.
  2. Toma del VPS las actualizaciones del backoffice y la estructura de westit_sofi (referencia).
  3. Migra (paso2), valida (paso5) y exporta (paso4). Si algo no da OK, se detiene SIN tocar el VPS.
  4. En el VPS (corte_coego.sh): backup de westit_coego, conserva la configuración de la web y los logos,
     reemplaza la base y deja la marca del corte.
     PROTECCIÓN: si en el sistema nuevo ya se cargaron movimientos (tickets, cajas, pedidos, cobros...)
     después del último corte, NO reemplaza nada. Para pisarlos igual: python actualizar_coego.py --forzar
  5. Controla el login contra la base maestra y, EN EL VPS (fotos_sync_coego_vps.py), sincroniza las fotos
     nuevas/cambiadas del FTP de la web vieja.
Deja todo en log_actualizar_coego_<fecha>.txt.
"""
import datetime, os, re, subprocess, sys

AQUI = os.path.dirname(os.path.abspath(__file__))
os.chdir(AQUI)
FORZAR = '--forzar' in sys.argv
TS = datetime.datetime.now().strftime('%Y%m%d_%H%M')
LOG = os.path.join(AQUI, 'log_actualizar_coego_%s.txt' % TS)
MYSQL = r'C:\xampp\mysql\bin\mysql.exe'
PHP = r'C:\xampp\php\php.exe' if os.path.exists(r'C:\xampp\php\php.exe') else 'php'
REMOTO = '/root/migracion_2026_11'
log = open(LOG, 'w', encoding='utf-8')


def out(txt):
    print(txt, flush=True)
    log.write(txt + '\n'); log.flush()


def correr(cmd, env=None, entrada=None):
    r = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, universal_newlines=True,
                       encoding='utf-8', errors='replace', env=env, input=entrada)
    log.write(r.stdout); log.flush()
    return r.returncode, r.stdout


def vps(*args):
    return correr([sys.executable, os.path.join(AQUI, 'vps.py')] + list(args))


def frenar(motivo):
    out('\n*** SE DETUVO: ' + motivo)
    out('*** El VPS no se tocó (salvo que el mensaje diga lo contrario). Log: ' + LOG)
    sys.exit(1)


out('== Actualizar COEGO desde legacy  %s%s' % (TS, '  (FORZAR)' if FORZAR else ''))

# ------------------------------------------------ credenciales del legacy (las mismas de la web vieja; no se guardan)
env = dict(os.environ)
for linea in open(r'C:\xampp\htdocs\subida\subir.php', encoding='utf-8', errors='replace'):
    m = re.match(r"^\$(host|user|password)\s*=\s*'([^']*)'", linea.strip())
    if m:
        env[{'host': 'LEGACY_HOST', 'user': 'LEGACY_USER', 'password': 'LEGACY_PASS'}[m.group(1)]] = m.group(2)
if not all(k in env for k in ('LEGACY_HOST', 'LEGACY_USER', 'LEGACY_PASS')):
    frenar('no encontré las credenciales del legacy en C:\\xampp\\htdocs\\subida\\subir.php')

# ------------------------------------------------ 0. ¿el sistema nuevo ya se está usando? (aviso temprano)
rc, s = vps("mysql -N westit_coego -e \"select t.tabla, t.filas, (select count(*) from tickets) from _mig_corte t where t.tabla='tickets'\" 2>/dev/null")
out('VPS antes de empezar: ' + (s.strip() or 'sin marca de corte'))

# ------------------------------------------------ 1. descarga
out('1. descargando legacy coego (solo lectura)...')
rc, s = correr([PHP, 'paso0_descargar.php', 'coego'], env=env)
if rc != 0 or 'src_coego ok' not in s:
    frenar('falló la descarga del legacy:\n' + s[-800:])
rc, s = correr([MYSQL, '-uroot', '-N', 'src_coego', '-e', 'select count(*), max(fecha) from tickets'])
out('   legacy: tickets / último: ' + s.strip())

# ------------------------------------------------ 2. referencias desde el VPS
out('2. referencias del VPS (actualizaciones del backoffice y estructura de westit_sofi)...')
rc, s = vps('cat /home/westit/backoffice.westit.com.ar/sqls/actualizaciones.sql')
if rc != 0 or len(s) < 10000:
    frenar('no pude leer actualizaciones.sql del backoffice')
open(os.path.join(AQUI, 'bo_vps', 'actualizaciones_vps.sql'), 'w', encoding='utf-8', newline='\n').write(s)
rc, s = vps('mysqldump --no-data --skip-triggers --skip-routines westit_sofi')
if rc != 0 or 'Dump completed' not in s[-200:]:
    frenar('no pude leer la estructura de westit_sofi')
ref = os.path.join(AQUI, 'estructura_westit_sofi_actual.sql')
open(ref, 'w', encoding='utf-8', newline='\n').write(s)
correr([MYSQL, '-uroot', '-e', 'DROP DATABASE IF EXISTS ref_sofi; CREATE DATABASE ref_sofi'])
rc, s = correr([MYSQL, '-uroot', 'ref_sofi'], entrada=open(ref, encoding='utf-8').read())
if rc != 0:
    frenar('no pude cargar ref_sofi: ' + s[-500:])

# ------------------------------------------------ 3. migrar / validar / exportar
out('3. migrando...')
env['PADRON_OPCIONAL'] = '1'   # si ARCA no responde, el corte repone los datos de la empresa del VPS
env['ACTUALIZACIONES_SQL'] = os.path.join(AQUI, 'bo_vps', 'actualizaciones_vps.sql').replace('\\', '/')
rc, s = correr([PHP, 'paso2_legacy_vb.php', 'coego'], env=env)
open(os.path.join(AQUI, 'log_coego_%s.txt' % TS), 'w', encoding='utf-8').write(s)
if '== coego terminado' not in s:
    frenar('la migración no terminó:\n' + s[-1500:])
# Los únicos errores admitidos son los de siempre (columnas/tablas que ya existían: 1060 / 1050)
raros = [l for l in s.splitlines() if re.search(r'ERROR \d+', l) and not re.search(r'ERROR (1060|1050)', l)]
if raros:
    frenar('errores nuevos en la migración:\n' + '\n'.join(raros[:20]))
dif = [l for l in s.splitlines() if re.search(r'origen\s+(\d+)\s+destino\s+(\d+)', l)]
for l in dif:
    a, b = re.search(r'origen\s+(\d+)\s+destino\s+(\d+)', l).groups()
    if a != b and 'plandecuentas' not in l:
        frenar('cantidades distintas entre legacy y migrada: ' + l.strip())
out('   ' + ' | '.join(re.sub(r'\s+', ' ', l.split(']', 1)[1]).strip() for l in dif[:4]))
rc, s = correr([PHP, 'paso5_validar.php', 'wst_db_20211411466', 'src_coego'])
if 'todos los controles OK' not in s:
    frenar('los controles no dieron OK:\n' + '\n'.join(l for l in s.splitlines() if 'OK ' not in l)[-1500:])
out('   controles: todos OK')
rc, s = correr([PHP, 'paso4_exportar.php', 'wst_db_20211411466'])
exp = os.path.join(AQUI, 'export', 'wst_db_20211411466.sql')
if rc != 0 or not os.path.exists(exp):
    frenar('falló la exportación: ' + s[-500:])
import gzip, shutil
with open(exp, 'rb') as fi, gzip.open(exp + '.gz', 'wb', compresslevel=6) as fo:
    shutil.copyfileobj(fi, fo)
out('   exportada: %.1f MB comprimida' % (os.path.getsize(exp + '.gz') / 1048576))

# ------------------------------------------------ 4. corte en el VPS
out('4. subiendo y reemplazando en el VPS...')
rc, s = vps('--put', exp + '.gz', REMOTO + '/westit_coego.sql.gz')
if 'subido' not in s:
    frenar('no se pudo subir la base: ' + s[-500:])
vps('--put', os.path.join(AQUI, 'corte_coego.sh'), REMOTO + '/corte_coego.sh')
rc, s = vps("sed -i 's/\\r$//' %s/corte_coego.sh && bash %s/corte_coego.sh %s/westit_coego.sql.gz %s; echo EXIT=$?"
            % (REMOTO, REMOTO, REMOTO, 'FORZAR' if FORZAR else ''), '1800')
out(s.strip())
if 'EXIT=2' in s:
    frenar('el sistema nuevo YA tiene movimientos cargados: no se reemplazó la base. '
           'Si hay que pisarlos igual, correr: python actualizar_coego.py --forzar')
if 'OK corte terminado' not in s:
    frenar('el corte en el VPS no terminó bien (ver arriba). Hay backup en %s/backup_westit_coego_antes_corte_*.sql.gz' % REMOTO)

# ------------------------------------------------ 5. controles finales y fotos
rc, s = vps('php /root/migracion_2026_10/validar_vps.php 2>&1 | grep 20211411466')
out('5. login: ' + s.strip())
rc, s = vps('cd %s && python3 fotos_sync_coego_vps.py 2>&1' % REMOTO, '1800')
out('   fotos (sincronizadas en el VPS): ' + (s.strip().splitlines() or ['?'])[-1])
out('\n== LISTO. COEGO actualizado desde legacy. Log: ' + LOG)
