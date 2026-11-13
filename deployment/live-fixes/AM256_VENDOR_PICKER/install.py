#!/usr/bin/env python3
"""AM256 guarded Vendor picker for item-wise Purchase Return.
Usage: python3 install.py check|apply|rollback STAGING_ROOT
Only updates controller, Blade view and one Admin GET route.
Exact previous AM254 sources are compared before any write; no DB changes.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, subprocess, sys, tempfile

if len(sys.argv) != 3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM256_USAGE install.py check|apply|rollback STAGING_ROOT')
action = sys.argv[1]
root = Path(sys.argv[2]).expanduser().resolve()
if root.name != 'assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM256_NOT_STAGING')
package = Path(__file__).resolve().parent
routes='routes/web.php'
controller='app/Http/Controllers/Web/AdminPurchaseReturnItemwiseController.php'
view='resources/views/purchase-returns/itemwise.blade.php'
files = [routes,controller,view]
backups=root/'storage/app/am-backups'
pointer=backups/'AM256_VENDOR_PICKER_LAST_BACKUP.txt'
marker='AM256_VENDOR_PICKER_ROUTE'
new_route=(
"    // AM256_VENDOR_PICKER_ROUTE: searchable original supplier Vendor Master.\n"
"    Route::get('/purchase-returns/vendors', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnItemwiseController::class,'vendors'])->name('erp.purchase-returns.vendors');\n"
)
route_anchor="    Route::get('/purchase-returns/search', [\\App\\Http\\Controllers\\Web\\AdminPurchaseReturnItemwiseController::class,'search'])->name('erp.purchase-returns.search');"

def sha(blob): return hashlib.sha256(blob).hexdigest()
def run(cmd,timeout=100):
    return subprocess.run(cmd, cwd=root, capture_output=True, text=True, timeout=timeout)
def atomic(path, blob):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd, temp = tempfile.mkstemp(dir=path.parent,prefix='.am256_')
    try:
        with os.fdopen(fd,'wb') as stream:stream.write(blob)
        os.chmod(temp,path.stat().st_mode&0o777 if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp): os.unlink(temp)
def php_lint(blob,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as stream:
        stream.write(blob);p=Path(stream.name)
    try:
        result=run(['php','-l',str(p)],40)
        if result.returncode:
            raise SystemExit('STOP_AM256_PHP_LINT_'+tag+' '+(result.stdout+result.stderr)[-450:])
    finally:p.unlink(missing_ok=True)
def blade_lint(blob,tag):
    with tempfile.TemporaryDirectory(prefix='am256blade_') as directory:
        src=Path(directory)/'template.blade.php';dest=Path(directory)/'compiled.php'
        src.write_bytes(blob)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result=run(['php','-r',snippet,str(src),str(dest)],55)
        if result.returncode or not dest.is_file():
            raise SystemExit('STOP_AM256_BLADE_COMPILE_'+tag+' '+(result.stdout+result.stderr)[-450:])
        php_lint(dest.read_bytes(),'COMPILED_'+tag)
def route_check():
    snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$ref=$routes->getByName('erp.purchase-returns.search');
foreach(['erp.purchase-returns.vendors','erp.purchase-returns.search'] as $name){
$r=$routes->getByName($name);
if(!$r||!$ref||!in_array('GET',$r->methods(),true)||
   $r->gatherMiddleware()!==$ref->gatherMiddleware()||
   !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(44);
}
echo 'AM256_VENDOR_SEARCH_SAME_ADMIN_MIDDLEWARE_PASS';"""
    result=run(['php','-r',snippet],50)
    if result.returncode or 'AM256_VENDOR_SEARCH_SAME_ADMIN_MIDDLEWARE_PASS' not in result.stdout:
        raise SystemExit('STOP_AM256_ADMIN_VENDOR_ROUTE '+(result.stdout+result.stderr)[-450:])
    print('AM256_VENDOR_SEARCH_SAME_ADMIN_MIDDLEWARE_PASS')

if action=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM256_NO_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM256_VENDOR_PICKER_CODE_'):
        raise SystemExit('STOP_AM256_UNTRUSTED_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel, digest in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit('STOP_AM256_NEWER_SOURCE_CHANGE_'+rel)
    for rel in files:atomic(root/rel,(backup/rel).read_bytes())
    clear=run(['php','artisan','optimize:clear'])
    if clear.returncode:raise SystemExit('AM256_SOURCE_ROLLED_BACK_CACHE_CLEAR_FAILED')
    print('AM256_PREVIOUS_ITEMWISE_VENDORLESS_FORM_RESTORED')
    sys.exit(0)

for rel in files:
    if not (root/rel).is_file():raise SystemExit('STOP_AM256_MISSING_STAGING_SOURCE_'+rel)
for rel in [controller,view]:
    if not (package/'baseline'/rel).is_file() or not (package/'files'/rel).is_file():
        raise SystemExit('STOP_AM256_INCOMPLETE_SOURCE_PACKAGE_'+rel)
raw={rel:(root/rel).read_bytes() for rel in files}
routetxt=raw[routes].decode('utf-8','surrogateescape')
newcontroller=(package/'files'/controller).read_bytes()
newview=(package/'files'/view).read_bytes()
if marker in routetxt and raw[controller]==newcontroller and raw[view]==newview:
    print('AM256_ALREADY_INSTALLED')
    route_check()
    sys.exit(0)
if marker in routetxt or routetxt.count(route_anchor)!=1:
    raise SystemExit('STOP_AM256_UNEXPECTED_OR_PARTIAL_ROUTE_STRUCTURE')
for rel in [controller,view]:
    baseline=(package/'baseline'/rel).read_bytes()
    if raw[rel]!=baseline:
        raise SystemExit('STOP_AM256_SOURCE_CHANGED_SINCE_AM254_'+rel)
newroute=routetxt.replace(route_anchor,new_route+route_anchor,1).encode('utf-8','surrogateescape')
patched={routes:newroute,controller:newcontroller,view:newview}
for rel in [routes,controller]:php_lint(patched[rel],rel.split('/')[-1])
blade_lint(newview,'ITEMWISE_VENDOR_VIEW')
must_have=['name="vendor_id"','id="am256-vendor-name"','am256-vendor-options',
           'erp.purchase-returns.vendors','Select Vendor']
if not all(x in newview.decode('utf8') for x in must_have):
    raise SystemExit('STOP_AM256_VENDOR_FORM_CONTRACT')
print('AM256_PHP_BLADE_AND_EXACT_SOURCE_PREFLIGHT_PASS')
if action=='check':
    print('AM256_READONLY_CHECK_PASS')
    sys.exit(0)

backup=backups/('AM256_VENDOR_PICKER_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(mode=0o700,parents=True,exist_ok=False)
for rel,blob in raw.items():
    out=backup/rel;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(blob)
written=[]
try:
    for rel,blob in patched.items():
        dest=root/rel
        if dest.read_bytes()!=raw[rel]:raise RuntimeError('STAGING_SOURCE_CHANGED_DURING_PREFLIGHT_'+rel)
        atomic(dest,blob);written.append(rel)
    for cmd in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        result=run(cmd,110)
        if result.returncode:raise RuntimeError('LARAVEL_CACHE '+(result.stdout+result.stderr)[-400:])
    route_check()
except BaseException as error:
    for rel in written:atomic(root/rel,raw[rel])
    run(['php','artisan','optimize:clear'],90)
    raise SystemExit('STOP_AM256_APPLY_RESTORED_'+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(v) for rel,v in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM256_VENDOR_NAME_PICKER_INSTALLED')
print('AM256_ITEMWISE_PURCHASES_FILTERED_BY_SELECTED_ORIGINAL_VENDOR')
print('AM256_NO_PURCHASE_RETURN_OR_LEDGER_DATA_CHANGED')
print('AM256_BACKUP='+str(backup))
