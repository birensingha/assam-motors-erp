#!/usr/bin/env python3
"""AM257 guarded Stage-only Workshop Profit Control dashboard.
Usage: install.py check|apply|rollback STAGING_ROOT
No migrations. No financial transaction edits. Only app Controller, Blade view,
routes and one navigation link. Planning settings may later be saved via Admin UI.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM257 install.py check|apply|rollback STAGING_ROOT')
action=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM257_NOT_STAGING')
pkg=Path(__file__).resolve().parent/'files'
routes='routes/web.php'
layout='resources/views/layouts/erp.blade.php'
controller='app/Http/Controllers/Web/AdminWorkshopProfitControlController.php'
view='resources/views/profit-control/index.blade.php'
existing=[routes,layout]
new=[controller,view]
backups=root/'storage/app/am-backups'
pointer=backups/'AM257_PROFIT_CONTROL_LAST_BACKUP.txt'
marker='AM257_PROFIT_CONTROL_ROUTE'
def enc(s):return s.encode('utf-8','surrogateescape')
def txt(b):return b.decode('utf-8','surrogateescape')
def sha(b):return hashlib.sha256(b).hexdigest()
def run(cmd,timeout=95):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(s,old,replacement,tag):
    if s.count(old)!=1:raise SystemExit('STOP_AM257_ANCHOR_'+tag+'_'+str(s.count(old)))
    return s.replace(old,replacement,1)
def atomic(path,content):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am257_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(content)
        os.chmod(temp,(path.stat().st_mode&0o777) if path.is_file() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(content,label):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(content);temporary=Path(f.name)
    try:
        result=run(['php','-l',str(temporary)],45)
        if result.returncode:
            raise SystemExit('STOP_AM257_PHP_LINT_'+label+' '+(result.stdout+result.stderr)[-600:])
    finally:temporary.unlink(missing_ok=True)
def compile_blade(content,label):
    with tempfile.TemporaryDirectory(prefix='am257blade_') as td:
        a=Path(td)/'index.blade.php';b=Path(td)/'compiled.php';a.write_bytes(content)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result=run(['php','-r',snippet,str(a),str(b)],55)
        if result.returncode or not b.is_file():
            raise SystemExit('STOP_AM257_BLADE_COMPILE_'+label+' '+(result.stdout+result.stderr)[-500:])
        lint(b.read_bytes(),'COMPILED_'+label)
def schema_check():
    snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$S=\Illuminate\Support\Facades\Schema::class;
$required=[
 'admin_settings'=>['id','setting_key','setting_value','updated_at'],
 'invoices'=>['id','invoice_date','status','invoice_no','job_card_id','subtotal','gst_total','tax_total','round_off','grand_total'],
 'invoice_credit_notes'=>['invoice_id','credit_date','status','taxable_amount'],
 'job_card_parts'=>['job_card_id','discount_percent','discount_amount','part_name','part_code'],
 'job_card_labour'=>['job_card_id','discount_percent','discount_amount','billing_included','rot_code','description'],
 'direct_invoice_lines'=>['invoice_id','discount_percent','discount_amount','part_code','part_name'],
 'workshop_expenses'=>['expense_date','record_status','expense_against','taxable_amount']];
foreach($required as $table=>$cols){
 if(!$S::hasTable($table))exit(51);
 foreach($cols as $column)if(!$S::hasColumn($table,$column))exit(52);
}
echo 'AM257_FINANCIAL_READONLY_SCHEMA_PASS';"""
    r=run(['php','-r',snippet],65)
    if r.returncode or 'AM257_FINANCIAL_READONLY_SCHEMA_PASS' not in r.stdout:
        raise SystemExit('STOP_AM257_FINANCIAL_SCHEMA '+(r.stdout+r.stderr)[-600:])
    print('AM257_FINANCIAL_READONLY_SCHEMA_PASS')
def routes_check():
    snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$anchor=$routes->getByName('erp.sales-register.index');
foreach(['erp.profit-control.index'=>'GET','erp.profit-control.save-plan'=>'POST'] as $name=>$method){
 $r=$routes->getByName($name);
 if(!$r||!$anchor||!in_array($method,$r->methods(),true)||
    $r->gatherMiddleware()!==$anchor->gatherMiddleware()||
    !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(48);
}
echo 'AM257_OWNER_ADMIN_ROUTE_PROTECTION_PASS';"""
    r=run(['php','-r',snippet],50)
    if r.returncode or 'AM257_OWNER_ADMIN_ROUTE_PROTECTION_PASS' not in r.stdout:
        raise SystemExit('STOP_AM257_ADMIN_ROUTES '+(r.stdout+r.stderr)[-600:])
    print('AM257_OWNER_ADMIN_ROUTE_PROTECTION_PASS')

if action=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM257_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM257_PROFIT_CODE_'):
        raise SystemExit('STOP_AM257_INVALID_BACKUP_POINTER')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,digest in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit('STOP_AM257_NEWER_SOURCE_CONFLICT_'+rel)
    for rel in existing:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new:(root/rel).unlink(missing_ok=True)
    r=run(['php','artisan','optimize:clear'],110)
    if r.returncode:raise SystemExit('AM257_SOURCE_RESTORED_BUT_CACHE_CLEAR_FAILED')
    print('AM257_PROFIT_DASHBOARD_CODE_RESTORED_PLANNING_SETTINGS_PRESERVED')
    sys.exit(0)

for rel in existing:
    if not (root/rel).is_file():raise SystemExit('STOP_AM257_REQUIRED_SOURCE_MISSING_'+rel)
for rel in new:
    if not (pkg/rel).is_file():raise SystemExit('STOP_AM257_PACKAGE_FILE_MISSING_'+rel)
raw={rel:(root/rel).read_bytes() for rel in existing}
routetxt=txt(raw[routes]);layouttxt=txt(raw[layout])
if marker in routetxt and all((root/rel).is_file() for rel in new):
    print('AM257_ALREADY_INSTALLED')
    routes_check()
    sys.exit(0)
if marker in routetxt or any((root/rel).exists() for rel in new):
    raise SystemExit('STOP_AM257_PARTIAL_OR_EXISTING_DASHBOARD')
route_anchor="    Route::get('/sales-register', [\\App\\Http\\Controllers\\Web\\AdminUnifiedSalesRegisterController::class,'index'])->name('erp.sales-register.index');"
route_add=(
"    // AM257_PROFIT_CONTROL_ROUTE: Owner/Admin real invoice facts and separate cost-recovery forecast.\n"
"    Route::get('/profit-control', [\\App\\Http\\Controllers\\Web\\AdminWorkshopProfitControlController::class,'index'])->name('erp.profit-control.index');\n"
"    Route::post('/profit-control/settings', [\\App\\Http\\Controllers\\Web\\AdminWorkshopProfitControlController::class,'savePlan'])->name('erp.profit-control.save-plan');\n"
)
nav_anchor="        <a href=\"{{ route('erp.rot-performance') }}\" class=\"{{ request()->routeIs('erp.rot-performance')?'active':'' }}\"><span>📊</span> ROT Performance</a>"
nav_add="\n        {{-- AM257_PROFIT_CONTROL_SIDEBAR: authorised workshop Profit Control dashboard. --}}\n        <a href=\"{{ route('erp.profit-control.index') }}\" class=\"{{ request()->routeIs('erp.profit-control.*')?'active':'' }}\"><span>📈</span> Profit Control</a>"
patched={
    routes:enc(once(routetxt,route_anchor,route_anchor+"\n"+route_add,'SALES_REGISTER_ROUTE')),
    layout:enc(once(layouttxt,nav_anchor,nav_anchor+nav_add,'ROT_PERFORMANCE_NAV')),
    controller:(pkg/controller).read_bytes(),
    view:(pkg/view).read_bytes(),
}
for rel in [routes,controller]:lint(patched[rel],Path(rel).stem)
compile_blade(patched[view],'PROFIT_CONTROL')
compile_blade(patched[layout],'ERP_LAYOUT')
schema_check()
print('AM257_SOURCE_PHP_BLADE_PREFLIGHT_PASS')
if action=='check':
    print('AM257_FINANCIAL_DASHBOARD_READONLY_CHECK_PASS')
    sys.exit(0)

backup=backups/('AM257_PROFIT_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for rel,b in raw.items():
    file=backup/rel;file.parent.mkdir(parents=True,exist_ok=True);file.write_bytes(b)
written=[]
try:
    for rel,blob in patched.items():
        if rel in raw and (root/rel).read_bytes()!=raw[rel]:
            raise RuntimeError('SOURCE_CHANGED_DURING_CHECK_'+rel)
        atomic(root/rel,blob);written.append(rel)
    for cmd in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        result=run(cmd,120)
        if result.returncode:raise RuntimeError('LARAVEL_CACHE_FAILED '+(result.stdout+result.stderr)[-700:])
    routes_check()
except BaseException as error:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],110)
    raise SystemExit('STOP_AM257_INSTALL_ROLLED_BACK_'+str(error))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(b) for rel,b in patched.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM257_PROFIT_CONTROL_DASHBOARD_STAGING_INSTALLED')
print('AM257_NAV_AND_ROUTES_READY')
print('AM257_INVOICE_PURCHASE_PAYMENT_STOCK_NOT_MUTATED')
print('AM257_PROFIT_PLAN_NOT_AUTOMATICALLY_CONFIGURED')
print('AM257_BACKUP='+str(backup))
