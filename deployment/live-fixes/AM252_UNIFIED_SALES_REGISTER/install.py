#!/usr/bin/env python3
"""AM252 guarded STAGING read-only unified Sales Register installer.
Usage: python3 install.py check|apply|rollback STAGING_ROOT
Does not edit transactions or schema; modifies code/view/routes/sidebar only.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM252_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/'files'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM252_NOT_STAGING')
route='routes/web.php'
sidebar='resources/views/layouts/erp.blade.php'
controller='app/Http/Controllers/Web/AdminUnifiedSalesRegisterController.php'
view='resources/views/sales-register/index.blade.php'
existing=[route,sidebar]
new=[controller,view]
backup_base=root/'storage/app/am-backups'
pointer=backup_base/'AM252_LAST_BACKUP.txt'
marker='AM252_UNIFIED_SALES_REGISTER'

def sha(blob):return hashlib.sha256(blob).hexdigest()
def enc(value):return value.encode('utf-8','surrogateescape')
def txt(blob):return blob.decode('utf-8','surrogateescape')
def run(args,timeout=90):
    return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(path,blob):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am252_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(blob)
        os.chmod(tmp,path.stat().st_mode&0o777 if path.is_file() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def once(s,old,new,tag):
    count=s.count(old)
    if count!=1:raise SystemExit('STOP_AM252_ANCHOR_'+tag+'_'+str(count))
    return s.replace(old,new,1)
def lint(blob,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(blob);p=Path(f.name)
    try:
        z=run(['php','-l',str(p)],30)
        if z.returncode:raise SystemExit('STOP_AM252_PHP_LINT_'+tag+' '+(z.stdout+z.stderr)[-400:])
    finally:p.unlink(missing_ok=True)
def blade_lint(blob,tag):
    with tempfile.TemporaryDirectory(prefix='am252blade_') as d:
        source=Path(d)/'view.blade.php'
        compiled=Path(d)/'compiled.php'
        source.write_bytes(blob)
        snippet=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        x=run(['php','-r',snippet,str(source),str(compiled)],50)
        if x.returncode or not compiled.is_file():raise SystemExit('STOP_AM252_BLADE_COMPILE_'+tag+' '+(x.stdout+x.stderr)[-400:])
        lint(compiled.read_bytes(),'COMPILED_'+tag)
def schema_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$needs=['invoices'=>['id','invoice_no','invoice_date','customer_name','vehicle_reg_no','grand_total','gst_total','status','created_at'],
'invoice_credit_notes'=>['id','credit_note_no','invoice_id','credit_date','credit_amount','gst_amount','status','created_at']];
foreach($needs as $table=>$cols){
if(!\Illuminate\Support\Facades\Schema::hasTable($table))exit(33);
foreach($cols as $c)if(!\Illuminate\Support\Facades\Schema::hasColumn($table,$c))exit(34);
}
$q1=\Illuminate\Support\Facades\DB::table('invoices as i')
->selectRaw("'SALE' as document_type, i.id as doc_id, i.invoice_no as document_no, i.invoice_date as document_date, i.customer_name as customer_name, i.vehicle_reg_no as vehicle_no, i.id as invoice_id, i.invoice_no as invoice_no, i.grand_total as signed_amount, i.gst_total as signed_gst, i.status as document_status, i.created_at as created_at");
$q2=\Illuminate\Support\Facades\DB::table('invoice_credit_notes as c')
->leftJoin('invoices as i','i.id','=','c.invoice_id')->where('c.status','posted')
->selectRaw("'CREDIT_NOTE' as document_type, c.id as doc_id, c.credit_note_no as document_no, c.credit_date as document_date, i.customer_name as customer_name, i.vehicle_reg_no as vehicle_no, c.invoice_id as invoice_id, i.invoice_no as invoice_no, -c.credit_amount as signed_amount, -c.gst_amount as signed_gst, c.status as document_status, c.created_at as created_at");
$query=\Illuminate\Support\Facades\DB::query()->fromSub($q1->unionAll($q2),'docs');
$query->limit(1)->get();
$query->selectRaw("COUNT(*) as document_count, COALESCE(SUM(signed_amount),0) as net_total")->first();
echo 'AM252_SALES_UNION_SQL_READONLY_PASS';"""
    x=run(['php','-r',php],65)
    if x.returncode or 'AM252_SALES_UNION_SQL_READONLY_PASS' not in x.stdout:
        raise SystemExit('STOP_AM252_SALES_SQL_PREFLIGHT '+(x.stdout+x.stderr)[-400:])
    print('AM252_SALES_UNION_SQL_READONLY_PASS')
def routes_check():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes();
$a=$r->getByName('erp.sales-register.index');
$b=$r->getByName('erp.purchases.index');
if(!$a||!$b||!in_array('GET',$a->methods())||$a->gatherMiddleware()!==$b->gatherMiddleware())exit(41);
if(!str_contains(implode('|',$a->gatherMiddleware()),'EnsureErpAdmin'))exit(42);
echo 'AM252_SALES_REGISTER_ADMIN_AUTH_ROUTE_PASS';"""
    x=run(['php','-r',code],40)
    if x.returncode or 'AM252_SALES_REGISTER_ADMIN_AUTH_ROUTE_PASS' not in x.stdout:
        raise SystemExit('STOP_AM252_NEW_SALES_ROUTE '+(x.stdout+x.stderr)[-400:])
    print('AM252_SALES_REGISTER_ADMIN_AUTH_ROUTE_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM252_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith('AM252_SALES_REGISTER_'):
        raise SystemExit('STOP_AM252_UNTRUSTED_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,digest in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit('STOP_AM252_SOURCE_CHANGED_AFTER_INSTALL_'+rel)
    for rel in existing:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new:(root/rel).unlink(missing_ok=True)
    clear=run(['php','artisan','optimize:clear'],85)
    if clear.returncode:raise SystemExit('AM252_CODE_RESTORED_CACHE_CLEAR_FAIL')
    print('AM252_PREVIOUS_REGISTER_CODE_RESTORED')
    sys.exit(0)

for rel in existing:
    if not (root/rel).is_file():raise SystemExit('STOP_AM252_SOURCE_MISSING_'+rel)
for rel in new:
    if not (pkg/rel).is_file():raise SystemExit('STOP_AM252_PACKAGE_MISSING_'+rel)
raw={rel:(root/rel).read_bytes() for rel in existing}
src={rel:txt(raw[rel]) for rel in existing}
if marker in src[route] and marker in src[sidebar] and all((root/rel).exists() for rel in new):
    print('AM252_ALREADY_INSTALLED')
    sys.exit(0)
if marker in src[route] or marker in src[sidebar] or any((root/rel).exists() for rel in new):
    raise SystemExit('STOP_AM252_PARTIAL_INSTALL_OR_FILE_CONFLICT')
route_anchor="    Route::get('/counter-sales',"
add_route=("    // AM252_UNIFIED_SALES_REGISTER: secure read-only Admin sales + posted credit note history.\n"
 "    Route::get('/sales-register', "
 "[\\App\\Http\\Controllers\\Web\\AdminUnifiedSalesRegisterController::class,'index'])"
 "->name('erp.sales-register.index');\n")
patched={}
patched[route]=enc(once(src[route],route_anchor,add_route+route_anchor,'ADMIN_ROUTE'))
side_anchor='        <a href="{{ route(\'erp.money-receipts.index\') }}"'
side_link=('        {{-- AM252_UNIFIED_SALES_REGISTER: original invoices and posted Credit Notes in one register. --}}\n'
 '        <a href="{{ route(\'erp.sales-register.index\') }}" class="{{ request()->routeIs(\'erp.sales-register.*\')?\'active\':\'\' }}"><span>🧾</span> Sales Register</a>\n')
patched[sidebar]=enc(once(src[sidebar],side_anchor,side_link+side_anchor,'SIDEBAR_NAV'))
for rel in new:patched[rel]=(pkg/rel).read_bytes()
lint(patched[route],'ROUTES')
lint(patched[controller],'NEW_CONTROLLER')
blade_lint(patched[view],'SALES_INDEX')
blade_lint(patched[sidebar],'SIDEBAR')
schema_check()
print('AM252_READONLY_SALES_REGISTER_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)

backup=backup_base/('AM252_SALES_REGISTER_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,mode=0o700,exist_ok=False)
for rel,blob in raw.items():
    out=backup/rel;out.parent.mkdir(parents=True,exist_ok=True);out.write_bytes(blob)
written=[]
try:
    for rel,blob in patched.items():
        p=root/rel
        if rel in raw and p.read_bytes()!=raw[rel]:raise RuntimeError('SOURCE_CHANGED_'+rel)
        atomic(p,blob);written.append(rel)
    for cmd in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        z=run(cmd,110)
        if z.returncode:raise RuntimeError('ARTISAN_CACHE '+(z.stdout+z.stderr)[-400:])
    routes_check()
except BaseException as e:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],85)
    raise SystemExit('STOP_AM252_DEPLOY_ROLLED_BACK_'+str(e))
(backup/'manifest.json').write_text(json.dumps({
    'installed':{rel:sha(blob) for rel,blob in patched.items()}
},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM252_UNIFIED_SALES_REGISTER_INSTALLED')
print('AM252_SALE_POSITIVE_CREDIT_NOTE_NEGATIVE')
print('AM252_PURCHASES_AND_LEDGER_NO_DATA_CHANGES')
print('AM252_BACKUP='+str(backup))
