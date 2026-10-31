#!/usr/bin/env python3
"""AM262 guarded STAGING Staff Finance Ledger install.
check|apply|rollback STAGING_ROOT; additive empty schema, no postings / historical changes.
Rollback restores source; migration is retained if applied to prevent accidental accounting drop.
"""
from pathlib import Path
import sys,os,tempfile,subprocess,hashlib,json
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM262_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM262_STOP_NOT_STAGING')
base=Path(__file__).resolve().parent/'files'
mapping={
 'controller':'app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php',
 'migration':'database/migrations/2026_10_31_225000_create_staff_finance_subledger.php',
 'view':'resources/views/staff-finance/index.blade.php',
 'routes':'routes/web.php',
 'staff':'resources/views/admin/native/staff.blade.php',
 'expenses':'resources/views/expenses/index.blade.php',
}
paths={n:root/p for n,p in mapping.items()}
backup=root/'storage/app/am-backups/AM262_STAFF_FINANCE_LEDGER'
manifest=backup/'checksums.json'
marker='AM262_STAFF_FINANCE_LEDGER'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(p,s):
 p.parent.mkdir(parents=True,exist_ok=True)
 fd,tmp=tempfile.mkstemp(prefix='.am262_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,p.stat().st_mode&0o777 if p.exists() else 0o644)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
for n in ('controller','migration','view'):
 if not (base/mapping[n]).is_file():raise SystemExit('AM262_STOP_PAYLOAD_MISSING_'+n)
for n in ('routes','staff','expenses'):
 if not paths[n].is_file() or paths[n].is_symlink():raise SystemExit('AM262_STOP_SOURCE_MISSING_'+n)
old={n:read(p) if p.exists() else '' for n,p in paths.items()}
if mode=='rollback':
 if not manifest.is_file():raise SystemExit('AM262_STOP_BACKUP_MISSING')
 hashes=json.loads(read(manifest))
 if any(sha(old[n])!=hashes['after'][n] for n in paths):
  raise SystemExit('AM262_STOP_ROLLBACK_SOURCE_CHANGED')
 for n,p in paths.items():
  if hashes['existed'][n]:atomic(p,read(backup/(n+'.before')))
  elif p.exists() and n!='migration':p.unlink()
 # Migration retained: preserving payroll history takes priority over automatic rollback.
 for cmd in (['php','artisan','route:clear'],['php','artisan','view:clear']):
  subprocess.run(cmd,cwd=root,capture_output=True,timeout=45)
 print('AM262_SOURCE_ROLLBACK_COMPLETE_STAFF_FINANCE_TABLES_RETAINED');sys.exit(0)
if all(marker in old[n] for n in ('routes','controller','view','staff','expenses')):
 print('AM262_ALREADY_INSTALLED');sys.exit(0)
if any(marker in old[n] for n in paths) or any(paths[n].exists() for n in ('controller','migration','view')):
 raise SystemExit('AM262_STOP_EXISTING_OR_PARTIAL_INSTALL')
new=dict(old)
for n in ('controller','migration','view'):
 new[n]=read(base/mapping[n])
 if marker not in new[n]:raise SystemExit('AM262_STOP_PAYLOAD_MARKER_'+n)
def replace(n,needle,repl,label):
 if new[n].count(needle)!=1:raise SystemExit('AM262_STOP_ANCHOR_'+label+'_'+str(new[n].count(needle)))
 new[n]=new[n].replace(needle,repl,1)
route="""    // AM262_STAFF_FINANCE_LEDGER: admin-only balanced salary journals and existing staff expense ledger.
    \Illuminate\Support\Facades\Route::get('/staff-finance',
        [\App\Http\Controllers\Web\AdminStaffFinanceLedgerController::class,'index'])
        ->name('erp.staff-finance.index');
    \Illuminate\Support\Facades\Route::post('/staff-finance',
        [\App\Http\Controllers\Web\AdminStaffFinanceLedgerController::class,'store'])
        ->name('erp.staff-finance.store');
    \Illuminate\Support\Facades\Route::post('/staff-finance/journals/{journal}/reverse',
        [\App\Http\Controllers\Web\AdminStaffFinanceLedgerController::class,'reverse'])
        ->whereNumber('journal')->name('erp.staff-finance.reverse');

"""
replace('routes','    // FIX-18 — Expenses + Universal Payment Voucher.',route+'    // FIX-18 — Expenses + Universal Payment Voucher.','ROUTE_LOCATION')
link="""{{-- AM262_STAFF_FINANCE_LEDGER: staff-wise salary advance, liability and expenses. --}}
<div style="margin:8px 0 14px;display:flex;gap:8px;flex-wrap:wrap">
 <a class="btn primary" href="{{ route('erp.staff-finance.index') }}">Staff Financial Ledger / Salary Advance</a>
</div>
"""
replace('staff',"@section('content')","@section('content')\n"+link,'STAFF_MASTER_LINK')
replace('expenses',"@section('content')","@section('content')\n"+link,'EXPENSE_REGISTER_LINK')
for n in paths:
 if marker not in new[n]:raise SystemExit('AM262_STOP_MARKER_'+n)
with tempfile.TemporaryDirectory(prefix='am262_qa_') as temp:
 d=Path(temp)
 for n in ('controller','migration','routes'):
  file=d/(n+'.php');file.write_text(new[n],encoding='utf8',errors='surrogateescape')
  r=subprocess.run(['php','-l',str(file)],cwd=root,text=True,capture_output=True,timeout=40)
  if r.returncode:raise SystemExit('AM262_STOP_PHP_LINT_'+n+' '+(r.stdout+r.stderr)[-550:])
 helper=d/'compile.php'
 helper.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
 for n in ('view','staff','expenses'):
  f=d/(n+'.blade.php');f.write_text(new[n],encoding='utf8',errors='surrogateescape')
  compiled=d/(n+'.compiled.php')
  r=subprocess.run(['php',str(helper),str(root),str(f),str(compiled)],cwd=root,text=True,capture_output=True,timeout=65)
  if r.returncode:raise SystemExit('AM262_STOP_BLADE_COMPILE_'+n+' '+r.stderr[-550:])
  r=subprocess.run(['php','-l',str(compiled)],cwd=root,text=True,capture_output=True,timeout=40)
  if r.returncode:raise SystemExit('AM262_STOP_BLADE_PHP_LINT_'+n+' '+(r.stdout+r.stderr)[-550:])
print('AM262_CONTROLLER_ROUTE_MIGRATION_BLADE_PREFLIGHT_PASS')
if mode=='check':
 print('AM262_CHECK_ONLY_NO_SOURCE_OR_DB_WRITES');sys.exit(0)
if backup.exists() or any((read(paths[n]) if paths[n].exists() else '')!=old[n] for n in paths):
 raise SystemExit('AM262_STOP_BACKUP_COLLISION_OR_RACE')
backup.mkdir(parents=True,exist_ok=False,mode=0o700)
for n,p in paths.items():
 if p.exists():
  q=backup/(n+'.before');q.write_text(old[n],encoding='utf8',errors='surrogateescape');os.chmod(q,0o600)
try:
 for n,p in paths.items():atomic(p,new[n])
 manifest.write_text(json.dumps({
  'after':{n:sha(new[n]) for n in paths},
  'existed':{n:n in ('routes','staff','expenses') for n in paths}
 }),encoding='ascii')
 os.chmod(manifest,0o600)
 r=subprocess.run(['php','artisan','migrate','--path='+mapping['migration'],'--force'],
                  cwd=root,text=True,capture_output=True,timeout=95)
 if r.returncode:raise RuntimeError('AM262_MIGRATION_FAILED '+r.stderr[-650:]+' '+r.stdout[-400:])
 print('AM262_ADDITIVE_EMPTY_JOURNAL_SCHEMA_APPLIED')
 for cmd in (['php','artisan','route:clear'],['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=100)
  if r.returncode:raise RuntimeError('AM262_CACHE_FAILURE '+r.stderr[-650:])
 r=subprocess.run(['php','artisan','route:list'],cwd=root,text=True,capture_output=True,timeout=90)
 if r.returncode or 'erp.staff-finance.index' not in r.stdout or 'erp.staff-finance.store' not in r.stdout:
  raise RuntimeError('AM262_ROUTES_NOT_REGISTERED')
except BaseException as ex:
 for n,p in paths.items():
  if n in ('controller','view'):
   if p.exists():p.unlink()
  elif n=='migration':
   # Additive schema migration remains installed to avoid unreviewed journal history loss.
   pass
  else:atomic(p,old[n])
 for cmd in (['php','artisan','route:clear'],['php','artisan','view:clear']):
  subprocess.run(cmd,cwd=root,capture_output=True,timeout=40)
 raise SystemExit('AM262_INSTALL_FAILED_SOURCE_ROLLED_BACK '+str(ex))
print('AM262_STAFF_LEDGER_STAGING_INSTALLED_NO_HISTORICAL_POSTINGS')
print('AM262_AUTHENTICATED_BROWSER_AND_POSTING_QA_PENDING')
