#!/usr/bin/env python3
"""AM254 guarded STAGING item-wise Purchase Return upgrade.
Commands: check | apply | rollback <STAGING_ROOT>.
No migrations, no purchase/payment data changes at installation.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM254 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM254_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
routes='routes/web.php'
ctrl='app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php'
view='resources/views/purchase-returns/itemwise.blade.php'
existing=[routes]
new=[ctrl,view]
backup_base=root/'storage/app/am-backups'
pointer=backup_base/'AM254_ITEMWISE_LAST_BACKUP.txt'
marker='AM254_ITEMWISE_PURCHASE_RETURN'

def sha(v):return hashlib.sha256(v).hexdigest()
def enc(s):return s.encode('utf-8','surrogateescape')
def txt(b):return b.decode('utf-8','surrogateescape')
def run(args,timeout=105):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(path,blob):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(dir=path.parent,prefix='.am254_')
    try:
        with os.fdopen(fd,'wb') as f:f.write(blob)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.exists() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint(blob,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:f.write(blob);p=Path(f.name)
    try:
        x=run(['php','-l',str(p)],40)
        if x.returncode:raise SystemExit('STOP_AM254_PHP_LINT_'+tag+' '+(x.stdout+x.stderr)[-500:])
    finally:p.unlink(missing_ok=True)
def blade(blob):
    with tempfile.TemporaryDirectory(prefix='am254blade_') as d:
        a=Path(d)/'view.blade.php'; b=Path(d)/'compiled.php'
        a.write_bytes(blob)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        x=run(['php','-r',php,str(a),str(b)],55)
        if x.returncode or not b.is_file():raise SystemExit('STOP_AM254_BLADE_COMPILE '+(x.stdout+x.stderr)[-500:])
        lint(b.read_bytes(),'COMPILED_ITEMWISE')
def schema_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s=\Illuminate\Support\Facades\Schema::class;
$required=[
'workshop_purchases'=>['id','purchase_type','purchase_no','purchase_batch_no','purchase_date','vendor_id','supplier_invoice','job_card_id','description','qty','unit_cost','total_amount'],
'parts_master'=>['id','part_code','part_name','stock_qty'],
'workshop_vendors'=>['id','vendor_name'],
'job_cards'=>['id','job_no','vehicle_reg_no','customer_name'],
'invoices'=>['id','job_card_id','invoice_no'],
'am_purchase_returns'=>['id','request_key','source_purchase_id','source_batch_no','return_no','record_status'],
'am_purchase_return_lines'=>['return_id','purchase_id','qty']];
foreach($required as $t=>$cols){if(!$s::hasTable($t))exit(51);
foreach($cols as $c)if(!$s::hasColumn($t,$c))exit(52);
}
echo 'AM254_SOURCE_AND_RETURN_SCHEMA_PASS';"""
    x=run(['php','-r',php],65)
    if x.returncode or 'AM254_SOURCE_AND_RETURN_SCHEMA_PASS' not in x.stdout:
        raise SystemExit('STOP_AM254_SCHEMA '+(x.stdout+x.stderr)[-500:])
    print('AM254_SOURCE_AND_RETURN_SCHEMA_PASS')
def route_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$old=$routes->getByName('erp.purchases.store');
foreach(['erp.purchase-returns.create'=>'GET','erp.purchase-returns.select-bill'=>'GET',
'erp.purchase-returns.search'=>'GET','erp.purchase-returns.itemwise.store'=>'POST'] as $name=>$method){
  $r=$routes->getByName($name);
  if(!$r||!in_array($method,$r->methods(),true)||
     !$old||$r->gatherMiddleware()!==$old->gatherMiddleware())exit(40);
  if(!str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(41);
}
echo 'AM254_FOUR_ADMIN_PROTECTED_ITEMWISE_ROUTES_PASS';"""
    x=run(['php','-r',php],50)
    if x.returncode or 'AM254_FOUR_ADMIN_PROTECTED_ITEMWISE_ROUTES_PASS' not in x.stdout:
        raise SystemExit('STOP_AM254_AUTH_ROUTES '+(x.stdout+x.stderr)[-500:])
    print('AM254_FOUR_ADMIN_PROTECTED_ITEMWISE_ROUTES_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM254_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith('AM254_ITEMWISE_CODE_'):
        raise SystemExit('STOP_AM254_INVALID_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,digest in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit('STOP_AM254_NEWER_CHANGE_CONFLICT_'+rel)
    for rel in existing:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new:(root/rel).unlink(missing_ok=True)
    res=run(['php','artisan','optimize:clear'])
    if res.returncode:raise SystemExit('AM254_ROLLBACK_FILES_RESTORED_CACHE_CLEAR_FAILED')
    print('AM254_ITEMWISE_CODE_ROLLED_BACK_NO_RETURN_DOCUMENT_CHANGED')
    sys.exit(0)

for rel in existing:
    if not (root/rel).is_file():raise SystemExit('STOP_AM254_BASE_MISSING_'+rel)
for rel in new:
    if not (pkg/rel).is_file():raise SystemExit('STOP_AM254_PACKAGE_MISSING_'+rel)
raw={rel:(root/rel).read_bytes() for rel in existing}
s=txt(raw[routes])
if marker in s and all((root/rel).is_file() for rel in new):
    print('AM254_ALREADY_INSTALLED');sys.exit(0)
if marker in s or any((root/rel).exists() for rel in new):
    raise SystemExit('STOP_AM254_PARTIAL_OR_UNEXPECTED_SOURCE')
anchor="    Route::get('/purchase-returns/create', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'create'])->name('erp.purchase-returns.create');"
if s.count(anchor)!=1:raise SystemExit('STOP_AM254_OLD_ADMIN_RETURN_ROUTE_ANCHOR_'+str(s.count(anchor)))
insert=(
"    // AM254_ITEMWISE_PURCHASE_RETURN: select any purchased item, see vendor/invoice/JC before return.\n"
"    Route::get('/purchase-returns/create', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnItemwiseController::class,'create'])->name('erp.purchase-returns.create');\n"
"    Route::get('/purchase-returns/select-bill', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnController::class,'create'])->name('erp.purchase-returns.select-bill');\n"
"    Route::get('/purchase-returns/search', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnItemwiseController::class,'search'])->name('erp.purchase-returns.search');\n"
"    Route::post('/purchase-returns/itemwise', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnItemwiseController::class,'store'])->name('erp.purchase-returns.itemwise.store');"
)
patched={routes:enc(s.replace(anchor,insert))}
for rel in new:patched[rel]=(pkg/rel).read_bytes()
lint(patched[routes],'ROUTES')
lint(patched[ctrl],'ITEMWISE_CONTROLLER')
blade(patched[view])
schema_check()
print('AM254_ITEMWISE_PHP_BLADE_AND_SCHEMA_PREFLIGHT_PASS')
if mode=='check':
    print('AM254_READONLY_CHECK_PASS')
    sys.exit(0)

backup=backup_base/('AM254_ITEMWISE_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for rel,blob in raw.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(blob)
written=[]
try:
    for rel,blob in patched.items():
        path=root/rel
        if rel in raw and path.read_bytes()!=raw[rel]:
            raise RuntimeError('BASE_CHANGED_DURING_PREFLIGHT_'+rel)
        atomic(path,blob);written.append(rel)
    for cmd in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        result=run(cmd,125)
        if result.returncode:raise RuntimeError('LARAVEL_CACHE '+(result.stdout+result.stderr)[-650:])
    route_check()
except BaseException as exc:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],85)
    raise SystemExit('STOP_AM254_APPLY_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(blob) for rel,blob in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM254_ITEMWISE_PURCHASE_RETURN_INSTALLED')
print('AM254_AM253_POSTING_ENGINE_REUSED_ORIGINAL_BILL_AUDIT_RETAINED')
print('AM254_NO_TRANSACTION_DATA_CHANGED_BY_INSTALL')
print('AM254_ROLLBACK_BACKUP='+str(backup))
