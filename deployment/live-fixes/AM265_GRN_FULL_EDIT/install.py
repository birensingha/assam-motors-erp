#!/usr/bin/env python3
"""AM265 STAGING-only full GRN header + item edit.

check|apply|rollback STAGING_ROOT.
Changes only two existing PHP/Blade business files and one GRN detail view.
New append-only GRN header audit table. No existing stocks, bills, invoices,
GRNs, movements, vendor ledgers or JC rows changed during install.
Authentic edits require Admin POST with reason and physical verification.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM265 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM265_NOT_STAGING')
pkg=Path(__file__).resolve().parent
ctl='app/Http/Controllers/Web/AdminProcurementGrnController.php'
edit='resources/views/procurement/grn-edit.blade.php'
detail='resources/views/procurement/grn-show.blade.php'
paths=[ctl,edit,detail]
expected_blobs={
 ctl:'a9d93dd1a9f729a0fe022cd9d93bf3e08e603093',
 edit:'0648f984b2ad32556d461ce38f6953c1a341aa41',
 detail:'2bf4b408ddb3222b2c33910b4b647022c98d29b5',
}
base=root/'storage/app/am-backups'
pointer=base/'AM265_FULL_GRN_LAST_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def run(cmd,timeout=130):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am265_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(data,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(data);p=Path(f.name)
    try:
        out=run(['php','-l',str(p)],55)
        if out.returncode:raise SystemExit('STOP_AM265_PHP_LINT_'+tag+' '+(out.stdout+out.stderr)[-800:])
    finally:p.unlink(missing_ok=True)
def blade(data,tag):
    with tempfile.TemporaryDirectory(prefix='am265blade_') as td:
        src=Path(td)/'source.blade.php';compiled=Path(td)/'compiled.php'
        src.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        out=run(['php','-r',php,str(src),str(compiled)],85)
        if out.returncode or not compiled.is_file():
            raise SystemExit('STOP_AM265_BLADE_COMPILE_'+tag+' '+(out.stdout+out.stderr)[-650:])
        lint(compiled.read_bytes(),'BLADE_'+tag)
def routes():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$R=\Illuminate\Support\Facades\Route::getRoutes();
$base=$R->getByName('erp.procurement.index');
if(!$base)exit(80);
foreach(['erp.procurement.grn.edit'=>'GET','erp.procurement.grn.update'=>'POST'] as $name=>$verb){
 $route=$R->getByName($name);
 if(!$route || !in_array($verb,$route->methods(),true) ||
    $route->gatherMiddleware()!==$base->gatherMiddleware() ||
    !str_contains(implode('|',$route->gatherMiddleware()),'EnsureErpAdmin'))exit(81);
}
echo 'AM265_OWNER_ADMIN_PROTECTED_GRN_ROUTES_PASS';"""
    out=run(['php','-r',code],65)
    if out.returncode or 'AM265_OWNER_ADMIN_PROTECTED_GRN_ROUTES_PASS' not in out.stdout:
        raise RuntimeError('STOP_AM265_ROUTE_AUTH '+(out.stdout+out.stderr)[-650:])
    print('AM265_OWNER_ADMIN_PROTECTED_GRN_ROUTES_PASS')
def schema():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
if(!$S::hasTable('am265_grn_header_audits'))exit(92);
foreach(['id','grn_id','grn_no','edit_no','before_json','after_json','changed_fields','actor','reason'] as $c){
 if(!$S::hasColumn('am265_grn_header_audits',$c))exit(93);
}
echo 'AM265_AUDIT_SCHEMA_VERIFIED';"""
    out=run(['php','-r',code],65)
    if out.returncode or 'AM265_AUDIT_SCHEMA_VERIFIED' not in out.stdout:
        raise RuntimeError('STOP_AM265_AUDIT_SCHEMA '+(out.stdout+out.stderr)[-650:])
    print('AM265_AUDIT_SCHEMA_VERIFIED')
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM265_BACKUP_POINTER_NOT_FOUND')
    backup=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in backup.parents or not backup.name.startswith('AM265_GRN_FULL_CODE_'):
        raise SystemExit('STOP_AM265_BACKUP_PATH_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,after in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=after:
            raise SystemExit('STOP_AM265_STAGING_SOURCE_CHANGED_SINCE_INSTALL_'+path)
    for path in paths:atom(root/path,(backup/path).read_bytes())
    r=run(['php','artisan','optimize:clear'],130)
    if r.returncode:raise SystemExit('AM265_CODE_ROLLBACK_CACHE_CLEAR_FAILED')
    print('AM265_SOURCE_CODE_RESTORED_BUSINESS_DATA_AND_AUDIT_RETAINED')
    sys.exit(0)
for path in paths:
    if not(root/path).is_file() or not(pkg/'files'/path).is_file():
        raise SystemExit('STOP_AM265_REQUIRED_FILE_MISSING_'+path)
old={path:(root/path).read_bytes() for path in paths}
new={path:(pkg/'files'/path).read_bytes() for path in paths}
if all(sha(old[p])==sha(new[p]) for p in paths):
    print('AM265_FULL_GRN_ALREADY_INSTALLED')
    routes()
    sys.exit(0)
for path in paths:
    if blob(old[path])!=expected_blobs[path]:
        raise SystemExit('STOP_AM265_STAGING_SOURCE_CONFLICT_'+path)
lint(new[ctl],'GRN_CONTROLLER')
blade(new[edit],'GRN_EDIT_COMPLETE_FORM')
blade(new[detail],'GRN_HEADER_AUDIT_DETAIL')
lint((pkg/'migrate.php').read_bytes(),'AUDIT_MIGRATION')
routes()
print('AM265_PHP_BLADE_AUTH_SOURCE_PREFLIGHT_PASS')
if mode=='check':
    print('AM265_NO_SOURCE_OR_STOCK_WRITES_DURING_PREFLIGHT')
    sys.exit(0)
migrate=run(['php',str(pkg/'migrate.php')],125)
if migrate.returncode or 'AM265_SCHEMA_READY_NO_EXISTING_DOCUMENT_OR_STOCK_MUTATED' not in migrate.stdout:
    raise SystemExit('STOP_AM265_MIGRATION_FAILED '+(migrate.stdout+migrate.stderr)[-700:])
print(migrate.stdout[-500:])
schema()
backup=base/('AM265_GRN_FULL_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in old.items():
    target=backup/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
written=[]
try:
    for path,data in new.items():
        if (root/path).read_bytes()!=old[path]:
            raise RuntimeError('AM265_CONCURRENT_STAGING_EDIT_'+path)
        atom(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        out=run(cmd,165)
        if out.returncode:raise RuntimeError('AM265_CACHE_BUILD_FAILED '+(out.stdout+out.stderr)[-650:])
    routes()
except BaseException as exc:
    for path in written:atom(root/path,old[path])
    run(['php','artisan','optimize:clear'],115)
    raise SystemExit('STOP_AM265_INSTALL_CODE_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(data) for p,data in new.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM265_COMPLETE_GRN_HEADER_ITEM_EDIT_STAGING_INSTALLED')
print('AM265_NO_GRN_STOCK_LEDGER_OR_INVOICE_DATA_CHANGED_ON_INSTALL')
print('AM265_BACKUP='+str(backup))
