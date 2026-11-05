"""Ejecuta comandos en el VPS por SSH leyendo las credenciales de sistema/.env (no las imprime).
uso: python vps.py "comando"            -> ejecuta y muestra la salida
     python vps.py --put local remoto     -> sube un archivo (SFTP)
"""
import sys, paramiko

env = {}
for linea in open(r'C:\xampp\htdocs\sistema\.env', encoding='utf-8'):
    if '=' in linea:
        k, v = linea.strip().split('=', 1)
        env[k.strip()] = v.strip().strip('"').strip("'")

cli = paramiko.SSHClient()
cli.set_missing_host_key_policy(paramiko.AutoAddPolicy())
cli.connect(env['IPServidor'], username=env['UsuarioSSH'], password=env['ClaveSSH'], timeout=20, allow_agent=False, look_for_keys=False)
secreto = env['ClaveSSH']

if sys.argv[1] == '--put':
    sftp = cli.open_sftp()
    sftp.put(sys.argv[2], sys.argv[3], callback=None)
    print('subido', sys.argv[3], sftp.stat(sys.argv[3]).st_size, 'bytes')
    sftp.close()
else:
    _, out, err = cli.exec_command(sys.argv[1], timeout=int(sys.argv[2]) if len(sys.argv) > 2 else 600)
    salida = out.read().decode('utf-8', 'replace') + err.read().decode('utf-8', 'replace')
    print(salida.replace(secreto, '***'))
cli.close()
