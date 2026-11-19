#!/usr/bin/env python3
"""AM195 guarded staging patch: category master UI + selectable Donation category."""
import os,re,sys,tempfile,subprocess,shutil
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply STAGING_LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();here=Path(__file__).resolve().parent
service=root/'app/Services/ExpensePaymentService.php'
routes=root/'routes/web.php'
controller=root/'app/Http/Controllers/Web/ExpenseCategoryMasterController.php'
view=root/'resources/views/expenses/category-master.blade.php'
migration=here/'2026_11_18_190000_am195_expense_category_master.php'
if not all(p.is_file() for p in (root/'artisan',service,routes,migration)):
    raise SystemExit('AM195_STOP_WRONG_STAGING_ROOT')
original=service.read_text(); route_original=routes.read_text()
marker='AM195_EXPENSE_CATEGORY_MASTER'
if marker in original:
    print('AM195_ALREADY_APPLIED');sys.exit(0)
pattern=r'(public\s+function\s+expenseCategories\s*\([^)]*\)\s*:\s*array\s*\{)'
m=re.search(pattern,original)
if not m:raise SystemExit('AM195_STOP_CATEGORY_METHOD_MISSING')
start=m.end(); depth=1; i=start; quote=None; escaped=False
while i<len(original) and depth:
    c=original[i]
    if quote:
        if escaped: escaped=False
        elif c=='\\':escaped=True
        elif c==quote:quote=None
    elif c in ('"',"'"):quote=c
    elif c=='{':depth+=1
    elif c=='}':depth-=1
    i+=1
if depth:raise SystemExit('AM195_STOP_CATEGORY_METHOD_MALFORMED')
body=original[start:i-1]
match=re.search(r'return\s+(\[[^;]+\]);',body,re.S)
if not match:raise SystemExit('AM195_STOP_CATEGORY_LIST_UNRECOGNISED')
old_categories=match.group(1)
newbody=""" // AM195_EXPENSE_CATEGORY_MASTER - active master categories and legacy defaults
        $legacy = """+old_categories+""";
        $custom = \\Illuminate\\Support\\Facades\\DB::table('workshop_expense_categories')
            ->where('is_active',true)->orderBy('name')->pluck('code')->all();
        return array_values(array_unique(array_merge($legacy,$custom)));
    """
patched=original[:start]+newbody+original[i-1:]
anchor="    Route::post('/expenses/{expense}/void',"
if route_original.count(anchor)!=1:raise SystemExit('AM195_STOP_ROUTE_CONTEXT_DRIFT')
newroutes=route_original.replace(anchor,"    // AM195 Expense Category Master (inside existing ERP admin route group)\n"+
"    Route::get('/expense-categories', [\\App\\Http\\Controllers\\Web\\ExpenseCategoryMasterController::class,'index'])->name('erp.expense-categories.index');\n"+
"    Route::post('/expense-categories', [\\App\\Http\\Controllers\\Web\\ExpenseCategoryMasterController::class,'store'])->name('erp.expense-categories.store');\n"+
"    Route::post('/expense-categories/{category}', [\\App\\Http\\Controllers\\Web\\ExpenseCategoryMasterController::class,'update'])->whereNumber('category')->name('erp.expense-categories.update');\n"+anchor,1)
if controller.exists() or view.exists():raise SystemExit('AM195_STOP_EXISTING_CATEGORY_FILES')
with tempfile.TemporaryDirectory() as t:
    for name,value in [('service.php',patched),('web.php',newroutes),('ExpenseCategoryMasterController.php',(here/'ExpenseCategoryMasterController.php').read_text())]:
        f=Path(t)/name;f.write_text(value)
        test=subprocess.run(['php','-l',str(f)],capture_output=True,text=True)
        if test.returncode:raise SystemExit('AM195_STOP_PHP_LINT '+test.stdout+test.stderr)
    print('AM195_SOURCE_PREFLIGHT_PASS')
if mode=='check':sys.exit(0)
backup=root/'storage/app/am-backups/AM195_EXPENSE_CATEGORIES'
if backup.exists():raise SystemExit('AM195_STOP_BACKUP_EXISTS')
backup.mkdir(parents=True)
shutil.copy2(service,backup/'ExpensePaymentService.php')
shutil.copy2(routes,backup/'web.php')
shutil.copy2(migration,backup/'migration_source.php')
def write(path,content):
    fd,temp=tempfile.mkstemp(prefix='.am195_',dir=path.parent)
    with os.fdopen(fd,'w') as f:f.write(content)
    os.replace(temp,path)
write(service,patched)
write(routes,newroutes)
shutil.copy2(here/'ExpenseCategoryMasterController.php',controller)
view.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(here/'category-master.blade.php',view)
print('AM195_FILES_APPLIED_BACKUP='+str(backup))
