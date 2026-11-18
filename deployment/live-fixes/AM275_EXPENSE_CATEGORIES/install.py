#!/usr/bin/env python3
"""AM275 STAGING-only Expense Category Settings + Donation.
check|apply|rollback STAGING_ROOT

Safeguards: exact current source hashes, pre-compiled Blade and PHP, Admin
middleware inherited, backup/automatic code rollback, additive-only master
migration. NO actual expense, payment, vendor, JC or accounting rows changed
during installation. Historical category keys remain unchanged.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('USAGE_AM275 install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not(root/'artisan').is_file():
    raise SystemExit('STOP_AM275_NOT_STAGING')

pkg=Path(__file__).resolve().parent
routes='routes/web.php'
service='app/Services/ExpensePaymentService.php'
controller='app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
config='resources/views/dashboard/configuration.blade.php'
create='resources/views/expenses/create.blade.php'
index='resources/views/expenses/index.blade.php'
show='resources/views/expenses/show.blade.php'
newService='app/Services/ExpenseCategoryMasterService.php'
newController='app/Http/Controllers/Web/AdminExpenseCategorySettingsController.php'
newView='resources/views/expenses/categories.blade.php'
oldFiles=[routes,service,controller,config,create,index,show]
newFiles=[newService,newController,newView]
sources=oldFiles+newFiles
fingerprints={
 routes:'d6c107e2fc853ba5118723edfd748213509cfb2b2acd93d4dcef824d2bffdbaa',
 service:'86de7b2065a8f524502c62427f44a3fbbf6d1be1bfdccb4173c3003a0e88ffe3',
 controller:'a4970f630162036d66281ec3bcd79d400ce498019ecc9141992159b32d1b6496',
 create:'ef3f9946c2d3171d52689e04ea3da3671a24bbece5cefe050e0615428b76a7ad',
 config:'325d3a2ffbe9e429afe74dec78045e2e4a2cc9afc0b7c197c217567ca8bb3cd7',
 index:'8148863b596d3a1bd9395b7dc538bad423fbf4768c1bc3782b364bc8f95b05ea',
 show:'d3acff0796db63108435aacad83303ec3ba5980f129bfc98fe25d2e7a159a26b',
}
base=root/'storage/app/am-backups'
pointer=base/'AM275_EXPENSE_CATEGORY_MASTER_LAST_BACKUP.txt'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(cmd,timeout=115):return subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=timeout)
def once(s,a,b,label):
    n=s.count(a)
    if n!=1:raise SystemExit('STOP_AM275_ANCHOR_'+label+'_MATCHES_'+str(n))
    return s.replace(a,b,1)
def atomic(p,data):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am275_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(data)
        os.chmod(temp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(data,tag):
    with tempfile.NamedTemporaryFile(suffix='.php',delete=False) as f:
        f.write(data);path=Path(f.name)
    try:
        x=run(['php','-l',str(path)],65)
        if x.returncode:raise SystemExit('STOP_AM275_PHP_LINT_'+tag+': '+(x.stdout+x.stderr)[-750:])
    finally:path.unlink(missing_ok=True)
def blade(data,tag):
    with tempfile.TemporaryDirectory(prefix='am275blade_') as t:
        source=Path(t)/'template.blade.php';out=Path(t)/'compiled.php';source.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        x=run(['php','-r',php,str(source),str(out)],80)
        if x.returncode or not out.exists():
            raise SystemExit('STOP_AM275_BLADE_'+tag+': '+(x.stdout+x.stderr)[-650:])
        lint(out.read_bytes(),'COMPILED_'+tag)
def routecheck():
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes=\Illuminate\Support\Facades\Route::getRoutes();
$base=$routes->getByName('erp.expenses.create');
if(!$base)exit(20);
foreach(['erp.expense-categories.index'=>'GET','erp.expense-categories.create'=>'POST',
'erp.expense-categories.update'=>'POST'] as $name=>$verb){
 $route=$routes->getByName($name);
 if(!$route || !in_array($verb,$route->methods(),true) ||
    $route->gatherMiddleware()!==$base->gatherMiddleware() ||
    !str_contains(implode('|',$route->gatherMiddleware()),'EnsureErpAdmin'))
    exit(21);
}
echo 'AM275_EXPENSE_CATEGORY_SETTINGS_SAME_ADMIN_AUTH_GUARD_PASS';"""
    x=run(['php','-r',code],65)
    if x.returncode or 'AM275_EXPENSE_CATEGORY_SETTINGS_SAME_ADMIN_AUTH_GUARD_PASS' not in x.stdout:
        raise RuntimeError('STOP_AM275_ADMIN_ROUTES_'+(x.stdout+x.stderr)[-700:])
    print('AM275_EXPENSE_CATEGORY_SETTINGS_SAME_ADMIN_AUTH_GUARD_PASS')

if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM275_CODE_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if base.resolve() not in backup.parents or not backup.name.startswith('AM275_CATEGORY_CODE_'):
        raise SystemExit('STOP_AM275_BACKUP_LOCATION_INVALID')
    manifest=json.loads((backup/'manifest.json').read_text())
    for path,after in manifest['installed'].items():
        if not(root/path).is_file() or sha((root/path).read_bytes())!=after:
            raise SystemExit('STOP_AM275_CODE_CHANGED_SINCE_INSTALL_'+path)
    for path in oldFiles:atomic(root/path,(backup/path).read_bytes())
    for path in newFiles:(root/path).unlink(missing_ok=True)
    x=run(['php','artisan','optimize:clear'],130)
    if x.returncode:raise SystemExit('AM275_SOURCE_RESTORED_CACHE_CLEAR_FAILED')
    print('AM275_CODE_ROLLBACK_COMPLETE_CATEGORY_AUDIT_TABLE_RETAINED_NO_EXPENSES_CHANGED')
    sys.exit(0)

for p in oldFiles:
    if not(root/p).is_file():raise SystemExit('STOP_AM275_SOURCE_NOT_FOUND_'+p)
for p in newFiles:
    if not(pkg/'files'/p).is_file():raise SystemExit('STOP_AM275_NEW_PACKAGE_FILE_MISSING_'+p)
before={p:(root/p).read_bytes() for p in oldFiles}
if b'AM275_EXPENSE_CATEGORY_MASTER' in before[service] and (root/newService).is_file():
    routecheck()
    print('AM275_ALREADY_INSTALLED')
    sys.exit(0)
for p in newFiles:
    if(root/p).exists():raise SystemExit('STOP_AM275_NEW_CODE_ALREADY_EXISTS_'+p)
for p,digest in fingerprints.items():
    if sha(before[p])!=digest:raise SystemExit('STOP_AM275_CONCURRENT_STAGING_SOURCE_CHANGE_'+p)

new={}
php=before[service].decode('utf-8')
php=once(php,
"""    public function expenseCategories(): array
    {
        return ['OUTSIDE_LABOUR','FUEL','FREIGHT','CONSUMABLES','TRAVEL','OFFICE','UTILITIES','STAFF_WELFARE','SALARY_WAGES','REPAIR_MAINTENANCE','TAX_FEE','LUBRICASH_COUPON','OTHER'];
    }""",
"""    /** AM275_EXPENSE_CATEGORY_MASTER: configurable Expense Categories, no hardcoded list. */
    public function expenseCategories(): array
    {
        return ExpenseCategoryMasterService::activeCodes();
    }""",'DYNAMIC_LIST')
php=once(php,
"""            $category=strtoupper(trim((string)($data['expense_category'] ?? 'OTHER')));
            if (!in_array($category,$this->expenseCategories(),true)) $category='OTHER';""",
"""            $category=strtoupper(trim((string)($data['expense_category'] ?? 'OTHER')));
            // AM275: reject unknown/disabled categories. Never silently
            // reclassify a Donation or any other expense as OTHER.
            ExpenseCategoryMasterService::requireActive($category);""",
'STRICT_CATEGORY_VALIDATION')
new[service]=php.encode('utf-8')

ctl=before[controller].decode('utf-8')
ctl=once(ctl,
"""'expenses'=>$service->searchExpenses($filters),'summary'=>$service->expenseSummary(),'categories'=>$service->expenseCategories(),'filters'=>$filters,""",
"""'expenses'=>$service->searchExpenses($filters),'summary'=>$service->expenseSummary(),
            'categories'=>\App\Services\ExpenseCategoryMasterService::allCodes(),
            'categoryLabels'=>\App\Services\ExpenseCategoryMasterService::labels(),'filters'=>$filters,""",
'EXPENSE_REGISTER_LABELS')
ctl=once(ctl,
"""'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,""",
"""'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),
            'categories'=>$service->expenseCategories(),
            'categoryLabels'=>\App\Services\ExpenseCategoryMasterService::labels(),
            'presetJobId'=>$presetJobId,""",
'EXPENSE_NEW_LABELS')
ctl=once(ctl,
"""return view('expenses.show',$expenseData+['am256Corrections'=>$am256Corrections]);""",
"""return view('expenses.show',$expenseData+[
            'am256Corrections'=>$am256Corrections,
            'categoryLabels'=>\App\Services\ExpenseCategoryMasterService::labels()
        ]);""",
'EXPENSE_SHOW_LABELS')
new[controller]=ctl.encode('utf-8')

rt=before[routes].decode('utf-8')
rt=once(rt,
"""    Route::get('/configuration', [AdminDashboardPageController::class,'configuration'])->name('erp.configuration');""",
"""    Route::get('/configuration', [AdminDashboardPageController::class,'configuration'])->name('erp.configuration');
    // AM275_EXPENSE_CATEGORY_MASTER: Admin Settings, validated category masters and audit.
    Route::get('/configuration/expense-categories',
        [\App\Http\Controllers\Web\AdminExpenseCategorySettingsController::class,'index'])
        ->name('erp.expense-categories.index');
    Route::post('/configuration/expense-categories',
        [\App\Http\Controllers\Web\AdminExpenseCategorySettingsController::class,'create'])
        ->name('erp.expense-categories.create');
    Route::post('/configuration/expense-categories/{category}',
        [\App\Http\Controllers\Web\AdminExpenseCategorySettingsController::class,'update'])
        ->whereNumber('category')->name('erp.expense-categories.update');""",
'CONFIGURATION_ROUTES')
new[routes]=rt.encode('utf-8')

conf=before[config].decode('utf-8')
conf=once(conf,
""" <a class="module-tile" href="{{ route('erp.vendors.index') }}"><div class="tile-icon">🏪</div><h3>Vendor Master</h3><p>Vendor activation, ledger and payments.</p></a>""",
""" <a class="module-tile" href="{{ route('erp.vendors.index') }}"><div class="tile-icon">🏪</div><h3>Vendor Master</h3><p>Vendor activation, ledger and payments.</p></a>
 {{-- AM275_EXPENSE_CATEGORY_MASTER: user-editable, audit-protected settings. --}}
 <a class="module-tile" href="{{ route('erp.expense-categories.index') }}"><div class="tile-icon">🧾</div><h3>Expense Categories</h3><p>Add Donation, rename existing categories or make them inactive. Historical vouchers are preserved.</p></a>""",
'CONFIG_CATEGORY_TILE')
new[config]=conf.encode('utf-8')

v=before[create].decode('utf-8')
v=once(v,
"""<label>Category *<select name="expense_category" required>@foreach($categories as $c)<option value="{{ $c }}" @selected(old('expense_category')===$c)>{{ $c==='TRAVEL' ? 'TRAVEL / TOUR / LOCAL CONVEYANCE' : str_replace('_',' ',$c) }}</option>@endforeach</select></label>""",
"""<label>Category *<select name="expense_category" required>@foreach($categories as $c)<option value="{{ $c }}" @selected(old('expense_category')===$c)>{{ $categoryLabels[$c] ?? str_replace('_',' ',$c) }}</option>@endforeach</select></label>""",
'NEW_EXPENSE_LABELS')
v=once(v,
"""<form method="post" action="{{ route('erp.expenses.store') }}"  class="card" id="expense-form">@csrf""",
"""<div style="margin:-2px 0 12px;display:flex;gap:12px;flex-wrap:wrap;align-items:center">
 <a href="{{ route('erp.expense-categories.index') }}" style="font-weight:800;font-size:12px">⚙ Settings → Add / Edit Expense Categories</a>
 <small style="color:#587995">For Donation: select DONATION / CHARITY, GENERAL / REFERENCE, recipient/payee, amount; tick Record Payment Now if already paid.</small>
</div>
<form method="post" action="{{ route('erp.expenses.store') }}"  class="card" id="expense-form">@csrf""",
'MANAGE_LINK')
new[create]=v.encode('utf-8')

v=before[index].decode('utf-8')
v=once(v,
"""<a class="btn" href="{{ route('erp.payments.index') }}">💳 Payment Vouchers</a><a class="btn primary" href="{{ route('erp.expenses.create') }}">＋ New Expense</a>""",
"""<a class="btn" href="{{ route('erp.payments.index') }}">💳 Payment Vouchers</a><a class="btn" href="{{ route('erp.expense-categories.index') }}">⚙ Expense Categories</a><a class="btn primary" href="{{ route('erp.expenses.create') }}">＋ New Expense</a>""",
'EXPENSE_REGISTER_SETTINGS_LINK')
v=once(v,
"""{{ str_replace('_',' ',$c) }}</option>@endforeach</select>""",
"""{{ $categoryLabels[$c] ?? str_replace('_',' ',$c) }}</option>@endforeach</select>""",
'REGISTER_CATEGORY_FILTER_LABEL')
v=once(v,
"""<small>{{ str_replace('_',' ',$e['expense_category']) }}</small>""",
"""<small>{{ $categoryLabels[$e['expense_category']] ?? str_replace('_',' ',$e['expense_category']) }}</small>""",
'REGISTER_HISTORY_CATEGORY_LABEL')
new[index]=v.encode('utf-8')

v=before[show].decode('utf-8')
old="{{ str_replace('_',' ',$expense['expense_category']) }}"
if v.count(old)!=2:raise SystemExit('STOP_AM275_SHOW_CATEGORY_LABEL_ANCHORS_'+str(v.count(old)))
v=v.replace(old,"{{ $categoryLabels[$expense['expense_category']] ?? str_replace('_',' ',$expense['expense_category']) }}")
new[show]=v.encode('utf-8')
for p in newFiles:new[p]=(pkg/'files'/p).read_bytes()
for p in [service,controller,routes,newService,newController]:lint(new[p],Path(p).stem)
lint((pkg/'migrate.php').read_bytes(),'MIGRATION')
for p in [config,create,index,show,newView]:blade(new[p],Path(p).stem)
print('AM275_PHP_BLADE_AND_CONCURRENT_SOURCE_PREFLIGHT_PASS')
if mode=='check':
    print('AM275_PREFLIGHT_SUCCESS_NO_EXPENSE_OR_PAYMENT_DATA_CHANGED')
    sys.exit(0)

migration=run(['php',str(pkg/'migrate.php')],115)
if migration.returncode or 'AM275_DONATION_AND_DEFAULT_CATEGORIES_SEEDED_NO_EXISTING_EXPENSE_OR_PAYMENT_CHANGED' not in migration.stdout:
    raise SystemExit('STOP_AM275_ADDITIVE_CATEGORY_MIGRATION_FAILED '+(migration.stdout+migration.stderr)[-600:])
print(migration.stdout[-650:])
backup=base/('AM275_CATEGORY_CODE_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for p,data in before.items():
    dest=backup/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(data)
written=[]
try:
    for p,data in new.items():
        if p in before and (root/p).read_bytes()!=before[p]:
            raise RuntimeError('AM275_CONCURRENT_CODE_EDIT_'+p)
        atomic(root/p,data);written.append(p)
    for cmd in [['php','artisan','optimize:clear'],['php','artisan','view:cache']]:
        result=run(cmd,180)
        if result.returncode:raise RuntimeError('AM275_LARAVEL_CACHE_FAILURE '+(result.stdout+result.stderr)[-850:])
    routecheck()
except BaseException as exc:
    for p in written:
        if p in before:atomic(root/p,before[p])
        else:(root/p).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],115)
    raise SystemExit('STOP_AM275_INSTALL_CODE_ROLLED_BACK_'+str(exc))
(backup/'manifest.json').write_text(json.dumps({'installed':{p:sha(b) for p,b in new.items()}},indent=2))
pointer.write_text(str(backup)+'\n')
print('AM275_EXPENSE_CATEGORY_MASTER_DONATION_SETTINGS_INSTALLED')
print('AM275_POSTED_EXPENSES_PURCHASES_PAYMENTS_AND_STOCK_UNCHANGED')
print('AM275_BACKUP='+str(backup))
