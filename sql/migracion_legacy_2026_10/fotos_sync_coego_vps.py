#!/usr/bin/env python3
"""Trae al ERP las fotos de productos de COEGO que el escritorio legacy sube al FTP de la web vieja.
Corre EN EL VPS como root (/root/migracion_2026_11). Solo baja las que faltan o que en el FTP son más nuevas,
las normaliza (<= 1000 px, JPEG 82, como el ERP) y las deja en la carpeta de fotos del ERP.
La clave del FTP está en /root/migracion_2026_11/.ftp_coego (solo root, chmod 600).
    python3 fotos_sync_coego_vps.py            -> sincroniza
    python3 fotos_sync_coego_vps.py --simular  -> solo informa
"""
import calendar, ftplib, os, re, shutil, subprocess, sys, tempfile, time

AQUI = os.path.dirname(os.path.abspath(__file__))
CLAVE = os.path.join(AQUI, '.ftp_coego')
DEST = '/home/westit/erp.westit.com.ar/img/productos/20211411466'
NORMALIZAR = os.path.join(AQUI, 'normalizar_foto.php')
SIMULAR = '--simular' in sys.argv

if not os.path.exists(CLAVE):
    sys.exit('Falta el archivo de la clave del FTP: ' + CLAVE)
ftp = ftplib.FTP('178.32.171.58', timeout=60)
ftp.login('pescahermanos', open(CLAVE, encoding='utf-8').read().strip())
ftp.cwd('fotos_productos')

remotos = {}
try:
    for nombre, hechos in ftp.mlsd(facts=['modify', 'type']):
        if hechos.get('type') == 'file':
            remotos[nombre] = calendar.timegm(time.strptime(hechos['modify'][:14], '%Y%m%d%H%M%S'))
except ftplib.error_perm:
    for nombre in ftp.nlst():
        try:
            r = ftp.sendcmd('MDTM ' + nombre)[4:].strip()
            remotos[nombre] = calendar.timegm(time.strptime(r[:14], '%Y%m%d%H%M%S'))
        except ftplib.error_perm:
            pass
remotos = {n: t for n, t in remotos.items() if n.lower().endswith(('.jpg', '.jpeg', '.png', '.webp', '.gif'))}

por_codigo = {}   # si hay .jpg y .png del mismo código, gana el más nuevo
for n, t in remotos.items():
    safe = re.sub(r'[^a-zA-Z0-9_\-.]', '_', os.path.splitext(n)[0])
    if safe not in por_codigo or por_codigo[safe][1] < t:
        por_codigo[safe] = (n, t)

tmp = tempfile.mkdtemp()
nuevas = cambiadas = errores = 0
for safe, (n, t) in sorted(por_codigo.items()):
    destino = os.path.join(DEST, safe + '.jpg')
    if os.path.exists(destino) and os.path.getmtime(destino) >= t:
        continue
    es_nueva = not os.path.exists(destino)
    print(('NUEVA    ' if es_nueva else 'CAMBIADA ') + n)
    if not SIMULAR:
        orig = os.path.join(tmp, n)
        with open(orig, 'wb') as f:
            ftp.retrbinary('RETR ' + n, f.write)
        r = subprocess.run(['php', NORMALIZAR, orig, destino], stdout=subprocess.PIPE, stderr=subprocess.STDOUT,
                           universal_newlines=True)
        if r.returncode != 0:
            errores += 1; print('   error: ' + r.stdout.strip()); continue
        shutil.chown(destino, 'westit', 'westit')
    nuevas += es_nueva; cambiadas += not es_nueva
ftp.quit()
shutil.rmtree(tmp, ignore_errors=True)
total = len([f for f in os.listdir(DEST) if f.endswith('.jpg')])
print('FTP: %d fotos | nuevas: %d | cambiadas: %d | errores: %d | en el ERP: %d%s'
      % (len(por_codigo), nuevas, cambiadas, errores, total, ' (simulado)' if SIMULAR else ''))
