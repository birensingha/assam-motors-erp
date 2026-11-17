#!/usr/bin/env python3
"""AM261 guarded STAGING-only GRN receipt verification / Item Ledger linking.
No migrations, stock updates, inventory movement inserts, or data repair.
check | apply | rollback STAGING_ROOT. Rollback restores code, never posted data.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM261 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM261_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
route='routes/web.php'
controller='app/Http/Controllers/Web/AdminProcurementGrnController.php'
view='resources/views/procurement/index.blade.php'
detail='resources/views/procurement/grn-show.blade.php'
existing=[route,controller,view]
backupBase=root/'storage/app/am-backups'
pointer=backupBase/'AM261_GRN_LEDGER_LAST_CODE_BACKUP.txt'
oldBlob={
    controller:'71bfb37d70e986a8fe8a64eb381cd2b9d07188ea',
    view:'52150381909fb040e939f480fd251152c06b58dd',
}
def blob(b):return hashlib.sha1(b'blob '+str(len(b)).encode()+b'\0'+b).hexdigest()
def digest(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=105):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(s,old,new,label):
    if s.count(old)!=1:raise SystemExit('STOP_AM261_ANCHOR_'+label+'_COUNT_'+str(s.count(old)))
    return s.replace(old,new,1)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am261_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(temp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(data,name):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as t:
        t.write(data);p=Path(t.name)
    try:
        o=run(['php','-l',str(p)],48)
        if o.returncode:raise SystemExit('STOP_AM261_PHP_LINT_'+name+' '+(o.stdout+o.stderr)[-600:])
    finally:p.unlink(missing_ok=True)
def blade(data,name):
    with tempfile.TemporaryDirectory(prefix='am261blade_') as td:
        src=Path(td)/'source.blade.php';dst=Path(td)/'compiled.php';src.write_bytes(data)
        code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        o=run(['php','-r',code,str(src),str(dst)],55)
        if o.returncode or not dst.is_file():
            raise SystemExit('STOP_AM261_BLADE_COMPILE_'+name+' '+(o.stdout+o.stderr)[-500:])
        lint(dst.read_bytes(),'BLADE_'+name)
def authRoutes():
    code=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes();
$a=$r->getByName('erp.procurement.index');
$b=$r->getByName('erp.procurement.grn.show');
if(!$a || !$b || !in_array('GET',$b->methods(),true)||
 $a->gatherMiddleware()!==$b->gatherMiddleware() ||
 !str_contains(implode('|',$b->gatherMiddleware()),'EnsureErpAdmin'))exit(49);
echo 'AM261_GRN_DETAIL_ADMIN_AUTH_ROUTE_PASS';"""
    o=run(['php','-r',code],55)
    if o.returncode or 'AM261_GRN_DETAIL_ADMIN_AUTH_ROUTE_PASS' not in o.stdout:
        raise RuntimeError('STOP_AM261_GRN_AUTH_ROUTE '+(o.stdout+o.stderr)[-750:])
    print('AM261_GRN_DETAIL_ADMIN_AUTH_ROUTE_PASS')
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM261_BACKUP_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupBase.resolve() not in backup.parents or not backup.name.startswith('AM261_GRN_LEDGER_'):
        raise SystemExit('STOP_AM261_BACKUP_PATH_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,sha in manifest['installed'].items():
        if not(root/path).is_file() or digest((root/path).read_bytes())!=sha:
            raise SystemExit('STOP_AM261_SOURCE_CHANGED_AFTER_DEPLOY_'+path)
    for path in existing:atom(root/path,(backup/path).read_bytes())
    (root/detail).unlink(missing_ok=True)
    r=run(['php','artisan','optimize:clear'],110)
    if r.returncode:raise SystemExit('AM261_SOURCE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM261_GRN_DETAIL_CODE_ROLLBACK_SUCCESS_BUSINESS_DATA_UNCHANGED')
    sys.exit(0)
for path in existing:
    if not(root/path).is_file():raise SystemExit('STOP_AM261_EXISTING_SOURCE_MISSING_'+path)
for path in [controller,view,detail]:
    if not(pkg/path).is_file():raise SystemExit('STOP_AM261_PACKAGE_SOURCE_MISSING_'+path)
original={p:(root/p).read_bytes() for p in existing}
rt=original[route].decode('utf-8','surrogateescape')
marker='AM261_GRN_LEDGER_VERIFY_ROUTE'
if marker in rt and (root/detail).is_file():
    authRoutes();print('AM261_ALREADY_INSTALLED');sys.exit(0)
if marker in rt or (root/detail).exists():
    raise SystemExit('STOP_AM261_PARTIALLY_INSTALLED_OR_CONFLICT')
for p,sha in oldBlob.items():
    if blob(original[p])!=sha:
        raise SystemExit('STOP_AM261_EXISTING_SOURCE_CHANGED_SINCE_AM260_'+p)
anchor="    Route::get('/procurement', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'index'])->name('erp.procurement.index');"
insertion="    // AM261_GRN_LEDGER_VERIFY_ROUTE: confirmed physical receipt & item movement source.\n"
insertion+="    Route::get('/procurement/grn/{grn}', [\\App\\Http\\Controllers\\Web\\AdminProcurementGrnController::class,'showReceipt'])->name('erp.procurement.grn.show');\n"
patched={
 route:once(rt,anchor,anchor+"\n"+insertion,'PROCUREMENT_ROUTE').encode('utf-8','surrogateescape'),
 controller:(pkg/controller).read_bytes(),
 view:(pkg/view).read_bytes(),
 detail:(pkg/detail).read_bytes(),
}
lint(patched[route],'ROUTES')
lint(patched[controller],'PROCUREMENT')
blade(patched[view],'PROCUREMENT_INDEX')
blade(patched[detail],'GRN_DETAIL')
print('AM261_SOURCE_PHP_BLADE_PREFLIGHT_PASS')
if mode=='check':
    print('AM261_READONLY_NO_STOCK_CHANGES_CHECK_PASS');sys.exit(0)
backup=backupBase/('AM261_GRN_LEDGER_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for path,data in original.items():
    target=backup/path;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
written=[]
try:
    for path,data in patched.items():
        if path in original and (root/path).read_bytes()!=original[path]:
            raise RuntimeError('AM261_CONCURRENT_STAGING_EDIT_'+path)
        atom(root/path,data);written.append(path)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        o=run(cmd,140)
        if o.returncode:raise RuntimeError('AM261_CACHE_BUILD_FAILED '+(o.stdout+o.stderr)[-600:])
    authRoutes()
except BaseException as error:
    for path in written:
        if path in original:atom(root/path,original[path])
        else:(root/path).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],100)
    raise SystemExit('STOP_AM261_DEPLOY_ROLLED_BACK_'+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:digest(v) for p,v in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM261_GRN_RECEIPT_VERIFICATION_STAGING_INSTALLED')
print('AM261_LEDGER_LINKS_AND_GRN_MOVEMENT_CHECK_READY')
print('AM261_NO_STOCK_JC_PURCHASE_OR_INVENTORY_MOVEMENT_MODIFIED_BY_INSTALL')
print('AM261_BACKUP='+str(backup))
