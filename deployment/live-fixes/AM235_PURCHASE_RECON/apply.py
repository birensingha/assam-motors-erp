#!/usr/bin/env python3
"""AM235 read-only Parts Purchase Reconciliation register installer (guarded STAGING only).
check: no writes; apply: backup + atomic source changes; rollback via rollback.py.
Does not modify PurchaseService / inventory / accounting / invoice posting.
"""
from pathlib import Path
import re,sys,hashlib,os,tempfile,shutil,datetime,subprocess
if len(sys.argv)<3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: apply.py check|apply STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM235_STOP_NOT_STAGING')
folder=Path(__file__).resolve().parent
targets={
 'routes/web.php':None,
 'resources/views/purchases/create.blade.php':None,
 'resources/views/purchases/index.blade.php':None,
 'app/Http/Controllers/Web/AdminPurchaseReconciliationController.php':'AdminPurchaseReconciliationController.php',
 'resources/views/purchases/reconciliation.blade.php':'reconciliation.blade.php'
}
old={}
for rel in targets:
 p=root/rel
 if p.is_file():
    old[rel]=p.read_text('utf8','surrogateescape')
 elif targets[rel] is None:
    raise SystemExit('AM235_REQUIRED_SOURCE_MISSING '+rel)
 else:old[rel]=None
marker='AM235_PURCHASE_RECONCILIATION'
if any(marker in (old[k] or '') for k in ('routes/web.php','resources/views/purchases/create.blade.php','resources/views/purchases/index.blade.php')):
 print('AM235_ALREADY_INSTALLED_NO_CHANGE');sys.exit(0)
if old['app/Http/Controllers/Web/AdminPurchaseReconciliationController.php'] is not None or old['resources/views/purchases/reconciliation.blade.php'] is not None:
 raise SystemExit('AM235_CONFLICT_NEW_TARGET_ALREADY_EXISTS')
if 'AM234_PURCHASE_JC_PICKER_V2' not in old['resources/views/purchases/create.blade.php']:
 raise SystemExit('AM235_REQUIRES_AM234_VALIDATED_PICKER')
def once(source,needle,replacement,label):
 count=source.count(needle)
 if count!=1:raise SystemExit('AM235_STOP_SOURCE_MISMATCH '+label+' matched '+str(count))
 return source.replace(needle,replacement,1)
route=old['routes/web.php']
route=once(route,'use App\\Http\\Controllers\\Web\\AdminPurchasePageController;',
           'use App\\Http\\Controllers\\Web\\AdminPurchasePageController;\nuse App\\Http\\Controllers\\Web\\AdminPurchaseReconciliationController;', 'route_import')
anchor="    Route::get('/purchases/create', [AdminPurchasePageController::class,'create'])->name('erp.purchases.create');"
route=once(route,anchor,anchor+"\n    // AM235_PURCHASE_RECONCILIATION: read-only linked JC and STOCK review.\n    Route::get('/purchases/reconciliation', [AdminPurchaseReconciliationController::class,'index'])->name('erp.purchases.reconciliation');",'route_registration')
create=old['resources/views/purchases/create.blade.php']
anchor='href="{{ route(\'erp.purchases.index\') }}">← Purchase Register</a>'
create=once(create,anchor,anchor+'\n        {{-- AM235_PURCHASE_RECONCILIATION --}}<a class="btn ghost" href="{{ route(\'erp.purchases.reconciliation\') }}">Parts Reconciliation</a>','form_report_link')
# The blue Keyboard Entry paragraph is already gone. Remove the remaining verbose instruction.
verbose='<span class="muted">PART search is connected to Parts Master. STOCK is default; choose JOB CARD / VEHICLE only when required.</span>'
if verbose in create:
 create=once(create,verbose,'','trim_purchase_toolbar')
register=old['resources/views/purchases/index.blade.php']
anchor='New Parts Purchase</a>'
report_link = """\n   <a class="btn ghost" style="grid-column:1/-1;display:block;text-align:center;border:1px solid #b8cde5;border-radius:10px;padding:9px;background:#fff;color:#184675;font-weight:800;text-decoration:none" href="{{ route('erp.purchases.reconciliation') }}">AM235_PURCHASE_RECONCILIATION · Parts Purchase Reconciliation</a>"""
register=once(register,anchor,anchor+report_link,'register_report_link')

updates={
 'routes/web.php':route,
 'resources/views/purchases/create.blade.php':create,
 'resources/views/purchases/index.blade.php':register,
 'app/Http/Controllers/Web/AdminPurchaseReconciliationController.php':(folder/'AdminPurchaseReconciliationController.php').read_text('utf8'),
 'resources/views/purchases/reconciliation.blade.php':(folder/'reconciliation.blade.php').read_text('utf8')
}
if 'AM235' not in updates['routes/web.php'] or 'AM234_PURCHASE_JC_PICKER_V2' not in updates['resources/views/purchases/create.blade.php']:
 raise SystemExit('AM235_OUTPUT_VALIDATION_STOP')
print('AM235_PREFLIGHT_PASS')
print('AM235_TARGET_COUNT',len(updates))
if mode=='check':
 print('AM235_READONLY_CHECK_NO_CHANGES');sys.exit(0)
# Source lint of the new controller (never executes it).
with tempfile.TemporaryDirectory(prefix='am235_lint_') as td:
 p=Path(td)/'AdminPurchaseReconciliationController.php'
 p.write_text(updates['app/Http/Controllers/Web/AdminPurchaseReconciliationController.php'],encoding='utf8')
 result=subprocess.run(['php','-l',str(p)],capture_output=True,text=True)
 if result.returncode:raise SystemExit('AM235_PHP_LINT_FAILED '+result.stdout+result.stderr)
backup=root/'storage/app/am-backups'/('AM235_PURCHASE_RECON_'+datetime.datetime.utcnow().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False,mode=0o750)
for rel in ('routes/web.php','resources/views/purchases/create.blade.php','resources/views/purchases/index.blade.php'):
 b=backup/rel;b.parent.mkdir(parents=True,exist_ok=True)
 shutil.copy2(root/rel,b)
(backup/'created_files.txt').write_text('app/Http/Controllers/Web/AdminPurchaseReconciliationController.php\nresources/views/purchases/reconciliation.blade.php\n')
installed=[]
try:
 for rel,value in updates.items():
    target=root/rel
    target.parent.mkdir(parents=True,exist_ok=True)
    with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=target.parent,prefix='.am235_',delete=False) as f:
        f.write(value); temp=Path(f.name)
    if target.exists():os.chmod(temp,target.stat().st_mode & 0o777)
    else:os.chmod(temp,0o644)
    os.replace(temp,target)
    installed.append(rel)
except Exception:
 for rel in installed:
    target=root/rel
    if old[rel] is None: target.unlink(missing_ok=True)
    else: shutil.copy2(backup/rel,target)
 raise
print('AM235_APPLIED')
print('AM235_BACKUP='+str(backup))
print('AM235_NO_PURCHASE_OR_LEDGER_RECORDS_CHANGED')
