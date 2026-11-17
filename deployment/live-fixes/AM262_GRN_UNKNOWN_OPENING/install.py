#!/usr/bin/env python3
"""AM262 STAGING-only hotfix: receive Vendor GRN despite unrecorded opening.

Modes: check | apply | rollback STAGING_ROOT.
Adds independent audit table, changes controller and two procurement views.
Existing opening balances, stock movements, vendor bills, PO and JC data are
never modified BY INSTALLATION. Stock changes require authenticated GRN POST.
Test transactions are rolled back. Never touch production.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM262 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM262_NOT_STAGING')
pkg=Path(__file__).resolve().parent
paths=[
 'app/Http/Controllers/Web/AdminProcurementGrnController.php',
 'resources/views/procurement/receive.blade.php',
 'resources/views/procurement/grn-show.blade.php'
]
expected={
 paths[0]:'f12fbf4b3f1bdbcc20fcd237bf4d089c1cad2699e0edf1a191c4085ed14bdb9a',
 paths[1]:'3dda8b4af736a1a21dc8df97a50b9f9e63bc6022381cb1c5f42c3a91ac33658a',
 paths[2]:'14ce2085fc9d6b8126c71bba74a0ca3f306077ef66f734559ff66773edc08a4c'
}
base=root/'storage/app/am-backups'
pointer=base/'AM262_OPENING_UNKNOWN_LAST_CODE_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=120):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(dir=path.parent,prefix='.am262_')
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode & 0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(content,name):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as tmp:
        tmp.write(content);p=Path(tmp.name)
    try:
        result=run(['php','-l',str(p)],50)
        if result.returncode:
            raise SystemExit('STOP_AM262_PHP_LINT_'+name+' '+(result.stdout+result.stderr)[-750:])
    finally:p.unlink(missing_ok=True)
def blade(content,name):
    with tempfile.TemporaryDirectory(prefix='am262blade_') as td:
        p=Path(td)/'page.blade.php';out=Path(td)/'compiled.php';p.write_bytes(content)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result=run(['php','-r',code,str(p),str(out)],65)
        if result.returncode or not out.exists():
            raise SystemExit('STOP_AM262_BLADE_'+name+' '+(result.stdout+result.stderr)[-750:])
        lint(out.read_bytes(),'BLADE_'+name)
def schema_ready():
    code=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
if(!$S::hasTable('am262_grn_opening_exceptions'))exit(76);
foreach(['grn_id','part_master_id','received_qty','provisional_after_qty',
 'prior_movement_id','status','acknowledged_by'] as $c){
 if(!$S::hasColumn('am262_grn_opening_exceptions',$c))exit(77);
}
echo 'AM262_AUDIT_TABLE_SCHEMA_VERIFIED';"""
    out=run(['php','-r',code],65)
    if out.returncode or 'AM262_AUDIT_TABLE_SCHEMA_VERIFIED' not in out.stdout:
        raise RuntimeError('STOP_AM262_NEW_SCHEMA_NOT_VERIFIED '+(out.stdout+out.stderr)[-450:])
    print('AM262_AUDIT_TABLE_SCHEMA_VERIFIED')
def auth_routes():
    code=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$R=\Illuminate\Support\Facades\Route::getRoutes();
foreach(['erp.procurement.receive.store','erp.procurement.grn.show'] as $name){
 $route=$R->getByName($name);
 if(!$route || !str_contains(implode('|',$route->gatherMiddleware()),'EnsureErpAdmin'))exit(78);
}
echo 'AM262_OWNER_ADMIN_ROUTES_UNCHANGED';"""
    out=run(['php','-r',code],65)
    if out.returncode or 'AM262_OWNER_ADMIN_ROUTES_UNCHANGED' not in out.stdout:
        raise RuntimeError('STOP_AM262_ADMIN_ROUTES '+(out.stdout+out.stderr)[-500:])
    print('AM262_OWNER_ADMIN_ROUTES_UNCHANGED')
if mode=='rollback':
    if not pointer.exists():raise SystemExit('STOP_AM262_NO_CODE_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in backup.parents or not backup.name.startswith('AM262_OPENING_CODE_'):
        raise SystemExit('STOP_AM262_BAD_BACKUP_POINTER')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,digest in manifest['installed'].items():
        if sha((root/path).read_bytes())!=digest:
            raise SystemExit('STOP_AM262_SOURCE_WAS_CHANGED_AFTER_INSTALL_'+path)
    for path in paths:atom(root/path,(backup/path).read_bytes())
    result=run(['php','artisan','optimize:clear'],110)
    if result.returncode:raise SystemExit('AM262_FILES_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM262_CODE_ROLLBACK_SUCCESS_AUDIT_TABLE_AND_PAST_BUSINESS_RECORDS_PRESERVED')
    sys.exit(0)
for path in paths:
    if not (root/path).is_file() or not(pkg/'files'/path).is_file():
        raise SystemExit('STOP_AM262_REQUIRED_SOURCE_MISSING_'+path)
original={path:(root/path).read_bytes() for path in paths}
new={path:(pkg/'files'/path).read_bytes() for path in paths}
for path in paths:
    if sha(original[path])==sha(new[path]):
        continue
    if sha(original[path])!=expected[path]:
        raise SystemExit('STOP_AM262_STAGING_FILE_CHANGED_CONFLICT_'+path)
if all(sha(original[p])==sha(new[p]) for p in paths):
    print('AM262_ALREADY_INSTALLED')
    auth_routes()
    sys.exit(0)
lint(new[paths[0]],'PROCUREMENT_CONTROLLER')
blade(new[paths[1]],'GRN_RECEIVE')
blade(new[paths[2]],'GRN_SHOW')
lint((pkg/'migrate.php').read_bytes(),'ADDITIVE_SCHEMA')
auth_routes()
print('AM262_SOURCE_BLADE_AND_AUTH_PREFLIGHT_PASS')
if mode=='check':
    print('AM262_SOURCE_CHECK_ONLY_NO_STOCK_CHANGES')
    sys.exit(0)
# Additive audit table created before installed code can write exceptions.
migration=run(['php',str(pkg/'migrate.php')],130)
if migration.returncode or 'AM262_SCHEMA_READY_EXISTING_STOCK_UNCHANGED' not in migration.stdout:
    raise SystemExit('STOP_AM262_SCHEMA_INSTALL_FAILED '+(migration.stdout+migration.stderr)[-900:])
print(migration.stdout[-400:])
schema_ready()
backup=base/('AM262_OPENING_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in original.items():
    target=backup/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
written=[]
try:
    for path,data in new.items():
        if sha((root/path).read_bytes())!=sha(original[path]):
            raise RuntimeError('AM262_CONCURRENT_CHANGE_'+path)
        atom(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        out=run(cmd,150)
        if out.returncode:raise RuntimeError('AM262_CACHE_FAILED '+(out.stdout+out.stderr)[-700:])
    auth_routes()
except BaseException as e:
    for path in written:atom(root/path,original[path])
    run(['php','artisan','optimize:clear'],110)
    raise SystemExit('STOP_AM262_SOURCE_RESTORED_ON_FAILURE_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{path:sha(new[path]) for path in paths}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM262_STAGING_UNKNOWN_OPENING_RECEIPTS_INSTALLED')
print('AM262_NO_EXISTING_STOCK_PO_JC_VENDOR_OR_PURCHASE_DATA_MODIFIED_BY_INSTALL')
print('AM262_NEW_GRN_REQUIRES_OPERATOR_CONFIRMATION_WHEN_OPENING_UNKNOWN')
print('AM262_BACKUP='+str(backup))
