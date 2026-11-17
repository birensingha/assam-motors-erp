#!/usr/bin/env python3
"""AM258 STAGING only: guarded Profit Control date-range filtering.
Modes: check, apply, rollback. Only two existing dashboard files can change.
No financial rows, business plan settings, routes, sidebar, or migrations touched.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM258: install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM258_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
paths=[
 'app/Http/Controllers/Web/AdminWorkshopProfitControlController.php',
 'resources/views/profit-control/index.blade.php'
]
# Exact AM257 Git blob hashes: guard against overwriting any other work.
baseline_git_sha={
 paths[0]:'527b14350cbdb07046c22ceaf7bb5363cf8d0292',
 paths[1]:'f77859b87a877890ca57689263c05a2d0a5b2a23'
}
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM258_PROFIT_PERIOD_LAST_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def blobsha(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def run(args,timeout=90):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(p,b):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am258_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,(p.stat().st_mode&0o777) if p.is_file() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def phplint(b,marker):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(b);tmp=Path(f.name)
    try:
        r=run(['php','-l',str(tmp)],50)
        if r.returncode:raise SystemExit('STOP_AM258_PHP_LINT_'+marker+': '+(r.stdout+r.stderr)[-700:])
    finally:tmp.unlink(missing_ok=True)
def bladecompile(b):
    with tempfile.TemporaryDirectory(prefix='am258blade_') as td:
        a=Path(td)/'index.blade.php';o=Path(td)/'compiled.php';a.write_bytes(b)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(['php','-r',code,str(a),str(o)],65)
        if r.returncode or not o.is_file():raise SystemExit('STOP_AM258_BLADE_COMPILE '+(r.stdout+r.stderr)[-800:])
        phplint(o.read_bytes(),'BLADE_COMPILED')
new={path:(pkg/path).read_bytes() for path in paths}
if b'AM258' not in new[paths[0]] or b'AM258_PROFIT_PERIOD' not in new[paths[1]]:
    raise SystemExit('STOP_AM258_REQUIRED_PACKAGE_MARKER_MISSING')
phplint(new[paths[0]],'CONTROLLER')
bladecompile(new[paths[1]])
orig={path:(root/path).read_bytes() for path in paths}
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM258_BACKUP_POINTER_MISSING')
    p=Path(pointer.read_text().strip()).resolve()
    if backupbase.resolve() not in p.parents or not p.name.startswith('AM258_PROFIT_PERIOD_'):
        raise SystemExit('STOP_AM258_BACKUP_LOCATION_INVALID')
    manifest=json.loads((p/'manifest.json').read_text())
    for path in paths:
        if sha(orig[path])!=manifest['installed'][path]:
            raise SystemExit('STOP_AM258_SOURCE_CHANGED_AFTER_DEPLOY_'+path)
    for path in paths:atomic(root/path,(p/path).read_bytes())
    r=run(['php','artisan','optimize:clear'],100)
    if r.returncode:raise SystemExit('AM258_ROLLBACK_RESTORED_FILES_BUT_CACHE_FAILED')
    print('AM258_ROLLBACK_RESTORED_AM257_SETTINGS_INTACT')
    sys.exit(0)
if all(sha(orig[path])==sha(new[path]) for path in paths):
    print('AM258_ALREADY_INSTALLED')
    sys.exit(0)
for path in paths:
    if blobsha(orig[path])!=baseline_git_sha[path]:
        raise SystemExit('STOP_AM258_STAGING_VERSION_CONFLICT_'+path)
snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$R=\Illuminate\Support\Facades\Route::getRoutes();
foreach(['erp.profit-control.index'=>'GET','erp.profit-control.save-plan'=>'POST'] as $name=>$method){
 $r=$R->getByName($name);
 if(!$r || !in_array($method,$r->methods(),true) ||
    !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(32);
}
echo 'AM258_EXISTING_AUTH_ROUTES_SAFE';"""
r=run(['php','-r',snippet],65)
if r.returncode or 'AM258_EXISTING_AUTH_ROUTES_SAFE' not in r.stdout:
    raise SystemExit('STOP_AM258_ADMIN_AUTH_ROUTES_NOT_VERIFIED '+(r.stdout+r.stderr)[-400:])
print('AM258_PHP_BLADE_AND_ADMIN_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)
backup=backupbase/('AM258_PROFIT_PERIOD_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,b in orig.items():
    out=backup/path;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(b)
wrote=[]
try:
    for path in paths:
        if sha((root/path).read_bytes())!=sha(orig[path]):
            raise RuntimeError('SOURCE_CHANGED_DURING_PRECHECK_'+path)
        atomic(root/path,new[path]);wrote.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        r=run(cmd,120)
        if r.returncode:raise RuntimeError('CACHE_COMMAND_FAILED '+(r.stdout+r.stderr)[-600:])
except BaseException as exc:
    for path in wrote:atomic(root/path,orig[path])
    run(['php','artisan','optimize:clear'],100)
    raise SystemExit('STOP_AM258_APPLY_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{path:sha(new[path]) for path in paths}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM258_PERIOD_FILTER_STAGING_INSTALLED')
print('AM258_EXISTING_FINANCIAL_RECORDS_PLAN_ROUTES_NAV_UNCHANGED')
print('AM258_BACKUP='+str(backup))
