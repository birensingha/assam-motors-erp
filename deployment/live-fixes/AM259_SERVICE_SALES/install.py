#!/usr/bin/env python3
"""AM259 guarded read-only Labour/ROT & OSL Sales Register integration.
Usage: python3 install.py check|apply|rollback STAGING_ROOT
No financial data rows, routes besides two new GET, or existing posting code changed.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM259 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM259_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
routes='routes/web.php'
sales_view='resources/views/sales-register/index.blade.php'
controller='app/Http/Controllers/Web/AdminServiceSalesRegisterController.php'
newview='resources/views/sales-register/service-index.blade.php'
existing=[routes,sales_view]
new=[controller,newview]
base=root/'storage/app/am-backups'
pointer=base/'AM259_SERVICE_SALES_LAST_CODE_BACKUP.txt'
marker='AM259_SERVICE_SALES_ROUTES'
def enc(t):return t.encode('utf-8','surrogateescape')
def txt(b):return b.decode('utf-8','surrogateescape')
def sha(b):return hashlib.sha256(b).hexdigest()
def run(cmd,timeout=100):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(src,old,repl,name):
    if src.count(old)!=1:raise SystemExit('STOP_AM259_'+name+'_ANCHOR_COUNT_'+str(src.count(old)))
    return src.replace(old,repl,1)
def atom(path,data):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am259_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(temp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(data,name):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as tmp:
        tmp.write(data);f=Path(tmp.name)
    try:
        r=run(['php','-l',str(f)],45)
        if r.returncode:raise SystemExit('STOP_AM259_PHP_LINT_'+name+' '+(r.stdout+r.stderr)[-550:])
    finally:f.unlink(missing_ok=True)
def blade(data,name):
    with tempfile.TemporaryDirectory(prefix='am259blade_') as td:
        x=Path(td)/'template.blade.php';y=Path(td)/'compiled.php';x.write_bytes(data)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        r=run(['php','-r',snippet,str(x),str(y)],65)
        if r.returncode or not y.is_file():raise SystemExit('STOP_AM259_BLADE_'+name+' '+(r.stdout+r.stderr)[-550:])
        lint(y.read_bytes(),'COMPILED_'+name)
def verify_schema():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
$checks=[
'invoices'=>['id','invoice_no','job_card_id','invoice_date','status','customer_name','vehicle_reg_no'],
'job_cards'=>['id','job_no'],
'job_card_labour'=>['id','job_card_id','mechanic_name','rot_code','description','standard_hours',
 'labour_rate','discount_percent','discount_amount','total','billing_included'],
'invoice_credit_notes'=>['invoice_id','credit_date','taxable_amount','status']
];
foreach($checks as $t=>$cols){
 if(!$S::hasTable($t))exit(50);
 foreach($cols as $c)if(!$S::hasColumn($t,$c))exit(51);
}
echo 'AM259_SERVICE_DATA_SOURCE_SCHEMA_PASS';"""
    r=run(['php','-r',code],65)
    if r.returncode or 'AM259_SERVICE_DATA_SOURCE_SCHEMA_PASS' not in r.stdout:
        raise SystemExit('STOP_AM259_SCHEMA_INCOMPATIBLE '+(r.stdout+r.stderr)[-500:])
    print('AM259_SERVICE_DATA_SOURCE_SCHEMA_PASS')
def verify_routes():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes();
$base=$r->getByName('erp.sales-register.index');
if(!$base)exit(52);
foreach(['erp.sales-register.labour','erp.sales-register.osl'] as $name){
 $route=$r->getByName($name);
 if(!$route || !in_array('GET',$route->methods(),true) ||
    $route->gatherMiddleware()!==$base->gatherMiddleware() ||
    !str_contains(implode('|',$route->gatherMiddleware()),'EnsureErpAdmin'))exit(53);
}
echo 'AM259_AUTH_GUARD_ROUTES_PASS';"""
    r=run(['php','-r',code],65)
    if r.returncode or 'AM259_AUTH_GUARD_ROUTES_PASS' not in r.stdout:
        raise SystemExit('STOP_AM259_AUTH_ROUTES '+(r.stdout+r.stderr)[-500:])
    print('AM259_AUTH_GUARD_ROUTES_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM259_BACKUP_POINTER_MISSING')
    path=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in path.parents or not path.name.startswith('AM259_SERVICE_SALES_'):
        raise SystemExit('STOP_AM259_BACKUP_PATH_INVALID')
    manifest=json.loads((path/'manifest.json').read_text())
    for rel,dig in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=dig:
            raise SystemExit('STOP_AM259_STAGING_SOURCE_CHANGED_AFTER_INSTALL_'+rel)
    for rel in existing:atom(root/rel,(path/rel).read_bytes())
    for rel in new:(root/rel).unlink(missing_ok=True)
    r=run(['php','artisan','optimize:clear'],110)
    if r.returncode:raise SystemExit('AM259_ROLLBACK_RESTORED_FILES_BUT_CACHE_CLEAR_FAILED')
    print('AM259_SERVICE_REGISTERS_RESTORED_PREVIOUS_SOURCE')
    sys.exit(0)

for rel in existing:
    if not (root/rel).is_file():raise SystemExit('STOP_AM259_EXISTING_FILE_MISSING_'+rel)
for rel in new:
    if not (pkg/rel).is_file():raise SystemExit('STOP_AM259_PACKAGE_FILE_MISSING_'+rel)
original={rel:(root/rel).read_bytes() for rel in existing}
rt=txt(original[routes]);sv=txt(original[sales_view])
if marker in rt and all((root/rel).is_file() for rel in new):
    print('AM259_ALREADY_INSTALLED')
    verify_routes()
    sys.exit(0)
if marker in rt or any((root/rel).exists() for rel in new):
    raise SystemExit('STOP_AM259_PARTIALLY_INSTALLED_OR_FILE_CONFLICT')
route_anchor="    Route::get('/sales-register', [\\App\\Http\\Controllers\\Web\\AdminUnifiedSalesRegisterController::class,'index'])->name('erp.sales-register.index');"
route_insert=(
    "\n    // AM259_SERVICE_SALES_ROUTES: separate GET-only Labour and OSL invoice service registers.\n"
    "    Route::get('/sales-register/labour', [\\App\\Http\\Controllers\\Web\\AdminServiceSalesRegisterController::class,'labour'])->name('erp.sales-register.labour');\n"
    "    Route::get('/sales-register/osl', [\\App\\Http\\Controllers\\Web\\AdminServiceSalesRegisterController::class,'osl'])->name('erp.sales-register.osl');"
)
view_anchor="  <div class=\"am252-stats\">"
view_insert=(
    "  {{-- AM259_SERVICE_SALES_LINKS: dedicated invoiced Labour and Outside Labour sales. --}}\n"
    "  <nav aria-label=\"Service Sales Registers\" style=\"display:flex;flex-wrap:wrap;gap:9px;margin:10px 0 14px\">\n"
    "    <a href=\"{{ route('erp.sales-register.labour') }}\" style=\"display:inline-flex;align-items:center;min-height:40px;padding:10px 14px;border:1px solid #bed6e7;border-radius:9px;color:#174f78;background:#eff7fd;font-size:12px;font-weight:850;text-decoration:none\">🔧 Labour / ROT Sales Register</a>\n"
    "    <a href=\"{{ route('erp.sales-register.osl') }}\" style=\"display:inline-flex;align-items:center;min-height:40px;padding:10px 14px;border:1px solid #b6d9cf;border-radius:9px;color:#126e5b;background:#eefaf5;font-size:12px;font-weight:850;text-decoration:none\">🏭 OSL Sales Register</a>\n"
    "  </nav>\n"
)
patched={
 routes:enc(once(rt,route_anchor,route_anchor+route_insert,'SALES_ROUTE')),
 sales_view:enc(once(sv,view_anchor,view_insert+view_anchor,'SALES_PAGE')),
 controller:(pkg/controller).read_bytes(),
 newview:(pkg/newview).read_bytes(),
}
lint(patched[controller],'SERVICE_REGISTER')
lint(patched[routes],'ROUTES')
blade(patched[sales_view],'SALES_REGISTER')
blade(patched[newview],'SERVICE_REGISTER')
verify_schema()
print('AM259_STATIC_SOURCE_PHP_BLADE_SCHEMA_PASS')
if mode=='check':sys.exit(0)

backup=base/('AM259_SERVICE_SALES_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for rel,data in original.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data)
written=[]
try:
    for rel,data in patched.items():
        if rel in original and (root/rel).read_bytes()!=original[rel]:
            raise RuntimeError('AM259_SOURCE_CHANGED_BETWEEN_CHECK_APPLY_'+rel)
        atom(root/rel,data);written.append(rel)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        r=run(cmd,120)
        if r.returncode:raise RuntimeError('AM259_LARAVEL_CACHE_FAILED '+(r.stdout+r.stderr)[-550:])
    verify_routes()
except BaseException as e:
    for rel in written:
        if rel in original:atom(root/rel,original[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],100)
    raise SystemExit('STOP_AM259_INSTALL_ROLLED_BACK_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(data) for rel,data in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM259_LABOUR_OSL_SALES_REGISTERS_STAGING_INSTALLED')
print('AM259_ONLY_NEW_READONLY_PAGES_AND_SALES_NAV_LINKS')
print('AM259_NO_INVOICE_OR_PAYMENT_OR_STOCK_MUTATIONS')
print('AM259_BACKUP='+str(backup))
