#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:?Give full staging Laravel root}"
cd "$APP_ROOT"
test -f artisan
test -f .env
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import os
f=Path('.env')
data=f.read_text()
if data.count('DB_HOST=localhost')==1:
    print('STAGING_DB_HOST=ALREADY_UNIX_SOCKET')
elif data.count('DB_HOST=127.0.0.1')==1:
    stamp=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    b=f.with_name('.env.backup-staging-mysql-socket-'+stamp)
    b.write_bytes(f.read_bytes())
    b.chmod(0o600)
    t=f.with_name('.env.staging-socket-tmp')
    t.write_text(data.replace('DB_HOST=127.0.0.1','DB_HOST=localhost',1))
    t.chmod(f.stat().st_mode & 0o777)
    os.replace(t,f)
    print('STAGING_MYSQL_ENV_CHANGED=YES')
    print('ENV_BACKUP_FILENAME='+b.name)
else:
    raise RuntimeError('UNEXPECTED_DB_HOST_CONFIG; refusing to edit')
PY
rollback() {
  python3 - <<'PY'
from pathlib import Path
from shutil import copy2
f=Path('.env')
b=sorted(Path('.').glob('.env.backup-staging-mysql-socket-*'))
if b:
    copy2(b[-1],f)
    print('STAGING_MYSQL_ENV_ROLLBACK=RESTORED')
else:
    print('STAGING_MYSQL_ENV_ROLLBACK=NO_BACKUP_AVAILABLE')
PY
  php artisan optimize:clear >/dev/null 2>&1 || true
}
trap rollback ERR
php artisan optimize:clear >/dev/null
php -r '
try {
  require "vendor/autoload.php";
  $app=require "bootstrap/app.php";
  $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
  $c=config("database.connections.mysql");
  if(($c["host"]??"")!=="localhost")exit(6);
  $pdo=new PDO("mysql:host=localhost;port=".($c["port"]??3306).";dbname=".$c["database"].";charset=utf8mb4",
    $c["username"],$c["password"],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
  for($n=1;$n<=7;$n++){
    if($pdo->query("SELECT 1")->fetchColumn()!=1)exit(7);
  }
  $exists=$pdo->query("SELECT id FROM personal_access_tokens LIMIT 1");
  if($exists===false)exit(8);
  echo "MYSQL_UNIX_SOCKET_PDO_AND_TOKEN_QUERY=VERIFIED\n";
}catch(Throwable $e){
  echo "DB_HEALTH_PROBE_FAILED_CLASS=".get_class($e)."\n";
  exit(9);
}'
trap - ERR
echo "STAGING_MYSQL_SOCKET_PATCH=DEPLOYED_AND_TESTED"
