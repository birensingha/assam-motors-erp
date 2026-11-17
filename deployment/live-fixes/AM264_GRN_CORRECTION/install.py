#!/usr/bin/env python3
"""AM264 STAGING-only guarded install for item-wise GRN correction.

check | apply | rollback <STAGING_ROOT>
Changes only AdminProcurementGrnController, procurement index/show and two
GET/POST routes, and adds a new GRN Edit form. Adds immutable audit table.
NO existing stock, GRN, movement, invoice, JC, or vendor records changed
during deployment. Only an authenticated Admin correction POST can change
stock. Test writes run inside outer DB transaction and roll back.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM264 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM264_NOT_STAGING')
pkg=Path(__file__).resolve().parent
route='routes/web.php'
ctl='app/Http/Controllers/Web/AdminProcurementGrnController.php'
index='resources/views/procurement/index.blade.php'
detail='resources/views/procurement/grn-show.blade.php'
edit='resources/views/procurement/grn-edit.blade.php'
existing=[route,ctl,index,detail]
paths=existing+[edit]
expected_git={
 ctl:'1f7bf52ada11f36b043d72e14c95506d572ef770',
 index:'f81e4868689edd08834ed6622eadf89af235398c',
 detail:'64f5b05d5bdd60471d7e691d0f8fb64084fd602e',
}
backupBase=root/'storage/app/am-backups'
pointer=backupBase/'AM264_GRN_CORRECTION_LAST_CODE_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def run(cmd,timeout=115):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(s,old,new,tag):
    count=s.count(old)
    if count!=1:raise SystemExit('STOP_AM264_PATCH_'+tag+'_ANCHOR_'+str(count))
    return s.replace(old,new,1)
def atomic(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am264_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(content,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(content);tmp=Path(f.name)
    try:
        r=run(['php','-l',str(tmp)],55)
        if r.returncode:raise SystemExit('STOP_AM264_PHP_LINT_'+tag+': '+(r.stdout+r.stderr)[-900:])
    finally:tmp.unlink(missing_ok=True)
def blade(data,tag):
    with tempfile.TemporaryDirectory(prefix='am264blade_') as td:
        src=Path(td)/'source.blade.php';out=Path(td)/'compiled.php'
        src.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(['php','-r',php,str(src),str(out)],75)
        if r.returncode or not out.exists():
            raise SystemExit('STOP_AM264_BLADE_'+tag+': '+(r.stdout+r.stderr)[-700:])
        lint(out.read_bytes(),'BLADE_'+tag)
def routesafe():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$base=$routes->getByName('erp.procurement.index');
if(!$base)exit(72);
foreach(['erp.procurement.grn.edit'=>'GET','erp.procurement.grn.update'=>'POST'] as $n=>$verb){
  $r=$routes->getByName($n);
  if(!$r || !in_array($verb,$r->methods(),true) ||
      $r->gatherMiddleware()!==$base->gatherMiddleware() ||
      !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(73);
}
echo 'AM264_OWNER_ADMIN_GRN_EDIT_ROUTES_SAFE';"""
    r=run(['php','-r',php],65)
    if r.returncode or 'AM264_OWNER_ADMIN_GRN_EDIT_ROUTES_SAFE' not in r.stdout:
        raise RuntimeError('STOP_AM264_AUTH_ROUTES '+(r.stdout+r.stderr)[-700:])
    print('AM264_OWNER_ADMIN_GRN_EDIT_ROUTES_SAFE')
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM264_BACKUP_POINTER_MISSING')
    back=Path(pointer.read_text().strip()).resolve()
    if backupBase.resolve() not in back.parents or not back.name.startswith('AM264_GRN_CODE_'):
        raise SystemExit('STOP_AM264_BACKUP_LOCATION_INVALID')
    manifest=json.loads((back/'manifest.json').read_text())
    for path,after in manifest['installed'].items():
        if not (root/path).is_file() or sha((root/path).read_bytes())!=after:
            raise SystemExit('STOP_AM264_SOURCE_CHANGED_SINCE_DEPLOY_'+path)
    for path in existing:atomic(root/path,(back/path).read_bytes())
    (root/edit).unlink(missing_ok=True)
    r=run(['php','artisan','optimize:clear'],130)
    if r.returncode:raise SystemExit('AM264_SOURCE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM264_CODE_ROLLBACK_SUCCESS_AUDIT_TABLE_AND_BUSINESS_RECORDS_PRESERVED')
    sys.exit(0)

for path in existing:
    if not(root/path).is_file():raise SystemExit('STOP_AM264_MISSING_CURRENT_SOURCE_'+path)
for path in [ctl,index,detail,edit]:
    if not(pkg/'files'/path).is_file():raise SystemExit('STOP_AM264_MISSING_PACKAGE_FILE_'+path)
old={path:(root/path).read_bytes() for path in existing}
current=old[route].decode('utf-8','surrogateescape')
marker='AM264_GRN_ITEM_CORRECTION_ROUTES'
if marker in current and (root/edit).exists():
    routesafe()
    print('AM264_ALREADY_INSTALLED')
    sys.exit(0)
if marker in current or (root/edit).exists():
    raise SystemExit('STOP_AM264_PARTIAL_INSTALL_CONFLICT')
for path,expected in expected_git.items():
    if blob(old[path])!=expected:
        raise SystemExit('STOP_AM264_CURRENT_STAGING_SOURCE_NOT_EXPECTED_'+path)
anchor="    Route::get('/procurement/grn/{grn}', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'showReceipt'])->name('erp.procurement.grn.show');"
snippet=(
"\n    // AM264_GRN_ITEM_CORRECTION_ROUTES: authenticated item editing and audited stock deltas.\n"
"    Route::get('/procurement/grn/{grn}/edit', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'editReceipt'])->name('erp.procurement.grn.edit');\n"
"    Route::post('/procurement/grn/{grn}/edit', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'updateReceipt'])->name('erp.procurement.grn.update');"
)
updated={
 route:once(current,anchor,anchor+snippet,'ROUTES').encode('utf-8','surrogateescape'),
 ctl:(pkg/'files'/ctl).read_bytes(),
 index:(pkg/'files'/index).read_bytes(),
 detail:(pkg/'files'/detail).read_bytes(),
 edit:(pkg/'files'/edit).read_bytes(),
}
for path in [route,ctl]:
    lint(updated[path],Path(path).stem)
lint((pkg/'migrate.php').read_bytes(),'MIGRATE')
for path in [index,detail,edit]:
    blade(updated[path],Path(path).stem)
print('AM264_PHP_BLADE_SOURCE_AND_NO_DATA_CHANGE_PREFLIGHT_PASS')
if mode=='check':
    print('AM264_PREFLIGHT_SUCCESS_NO_EXISTING_RECORDS_CHANGED')
    sys.exit(0)

migration=run(['php',str(pkg/'migrate.php')],120)
if migration.returncode or 'AM264_ADDITIVE_AUDIT_SCHEMA_READY_NO_EXISTING_STOCK_CHANGED' not in migration.stdout:
    raise SystemExit('STOP_AM264_ADDITIVE_AUDIT_MIGRATION_FAILED '+(migration.stdout+migration.stderr)[-650:])
print(migration.stdout[-550:])
backup=backupBase/('AM264_GRN_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in old.items():
    b=backup/path;b.parent.mkdir(parents=True,exist_ok=True);b.write_bytes(data)
written=[]
try:
    for path,data in updated.items():
        if path in old and sha((root/path).read_bytes())!=sha(old[path]):
            raise RuntimeError('AM264_CONCURRENT_STAGING_EDIT_'+path)
        atomic(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        r=run(cmd,180)
        if r.returncode:raise RuntimeError('AM264_CACHE_FAILED '+(r.stdout+r.stderr)[-850:])
    routesafe()
except BaseException as error:
    for path in written:
        if path in old:atomic(root/path,old[path])
        else:(root/path).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],120)
    raise SystemExit('STOP_AM264_DEPLOY_CODE_ROLLED_BACK_'+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(data) for p,data in updated.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM264_GRN_ITEM_CORRECTION_STAGING_INSTALLED')
print('AM264_NO_JC_ISSUE_NO_STOCK_RECEIPT_NO_PURCHASE_POSTED_DURING_INSTALL')
print('AM264_BACKUP='+str(backup))
