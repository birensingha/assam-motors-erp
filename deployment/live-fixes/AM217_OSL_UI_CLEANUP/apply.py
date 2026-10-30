#!/usr/bin/env python3
"""AM217 guarded STAGING view-only Job Card OSL cleanup.
- Customer billing table shows no purchase-only supplier cost reference rows.
- Add OSL shows only supplier purchases not ALREADY billed via their valid
  linked JC labour line (not relying on stale billing_line_created flags).
- Linked supplier purchase selector defaults collapsed, optional.
- OSL Master gets keyboard-searchable names via original native select.
No DB writes; no financial/controller/purchase/route modifications.
"""
from pathlib import Path
import re,sys,subprocess,shutil,tempfile,os
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/'resources/views/job-cards/show.blade.php'
backup=root/'storage/app/am-backups/AM217_OSL_UI_CLEANUP/show.blade.php.before_AM217'
assets=Path(__file__).parent
marker='AM217_OSL_UI_CLEANUP'
if not (root/'artisan').is_file() or not view.is_file():
    raise SystemExit('AM217_STOP_WRONG_APP_ROOT')
def write_atomic(p,body):
    fd,tmp=tempfile.mkstemp(prefix='.am217_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(body)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
source=view.read_text('utf8',errors='surrogateescape')
if mode=='rollback':
    if source.count(marker)!=1 or not backup.is_file():
        raise SystemExit('AM217_STOP_BACKUP_MISSING_OR_SOURCE_CHANGED')
    write_atomic(view,backup.read_text('utf8',errors='surrogateescape'))
    print('AM217_PREVIOUS_OSL_VIEW_RESTORED')
    sys.exit(0)
if marker in source:
    print('AM217_ALREADY_APPLIED')
    sys.exit(0)
for name,needle in [
    ('PURCHASE_ONLY_BADGE','OSL · PURCHASE ONLY'),
    ('PURCHASE_ONLY_FOREACH','@foreach(($am83OslPurchases??collect()) as $am93Purchase)'),
    ('LINKED_PURCHASE_TABLE','@forelse(($am83OslPurchases ?? collect()) as $p)'),
    ('OSL_MASTER_NATIVE',"const select=document.createElement('select');select.name='osl_master_id';"),
    ('MASTER_CALCULATOR','<script id="am146-osl-calculations">'),
    ('OSL_ROW_SORT','<script id="am93-osl-row-sort">'),
    ('SUPPLIER_DETAILS_OPEN','toolsBlock.open=true;'),
    ('SUPPLIER_CHOOSER_TITLE',"toolsHeading.textContent='Select Linked OSL / Supplier Purchase Options';"),
    ('SUPPLIER_VIEW_EDIT',"route('erp.fix48.osl-edit',$p->id)"),
    ('CUSTOMER_OSL_SAVE',"route('erp.job-cards.osl.admin-save',$job->id)"),
]:
    n=source.count(needle)
    print('AM217_PREFLIGHT_'+name+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
    if n!=1:raise SystemExit('AM217_STOP_SOURCE_DRIFT_'+name)
if not all(p.is_file() for p in (assets/'master-search.js',assets/'master-search.css')):
    raise SystemExit('AM217_STOP_REQUIRED_SEARCH_ASSETS_MISSING')

s=source
# 1. Remove JUST the extra supplier purchase row rendered inside the customer
# billing table. Never remove purchase data or vendor ledger or customer lines.
pat=(r'(?ms)^[ \t]*@foreach\(\(\$am83OslPurchases\?\?collect\(\)\) as \$am93Purchase\)[ \t]*\n'
     r'(?P<body>.*?)^[ \t]*@endforeach[ \t]*\n')
found=list(re.finditer(pat,s))
if len(found)!=1:raise SystemExit('AM217_STOP_CANNOT_ISOLATE_PURCHASE_ONLY_ROW')
section=found[0].group(0)
for needle in ('am93-purchase-pending','OSL · PURCHASE ONLY',
               "route('erp.fix48.osl-edit',$am93Purchase->id)"):
    if needle not in section:raise SystemExit('AM217_STOP_PURCHASE_ONLY_ROW_CHANGED')
s=s[:found[0].start()] + (
    '\n{{-- AM217_OSL_UI_CLEANUP: supplier cost belongs in Purchase Register, '
    'not Customer Labour + OSL Billing Register. --}}\n'
) + s[found[0].end():]

# 2. True linked billing status: active row in actual JC OSL Labour collection.
# Purchase billing_line_created can be stale = 0 despite linked billing line.
prev='@forelse(($am83OslPurchases ?? collect()) as $p)'
s=s.replace(prev,'@forelse($am217UnbilledPurchases as $p)',1)
compute=r'''
      {{-- AM217 true linked purchase filter; no updates to stale purchase flags. --}}
      @php
        $am217UnbilledPurchases=collect($am83OslPurchases??collect())
          ->reject(static function($p) use ($am83OslLabour) {
            if(!empty($p->billing_line_created)) return true;
            $linked=(int)($p->labour_line_id??0);
            return $linked>0 && collect($am83OslLabour??collect())->contains(
              static fn($l)=>(int)($l->id??0)===$linked
                && (int)($l->billing_included??1)===1
            );
          });
      @endphp
'''
anchor='      <div class="am83-osl-scroll">'
if s.count(anchor)!=1:raise SystemExit('AM217_STOP_PURCHASE_TABLE_INSERT_POINT')
s=s.replace(anchor,compute+anchor,1)
s=s.replace(
 'No linked OSL supplier purchase yet. Enter an OSL Purchase with this Job Card number.',
 'No unbilled supplier OSL purchases. Select an OSL Master below for new customer work.',1
)

# 3. Keep purchase selection possible for genuinely pending purchases,
# but not occupying the Add OSL popup for unrelated new service.
s=s.replace('toolsBlock.open=true;','toolsBlock.open=false;',1)
s=s.replace(
 "toolsHeading.textContent='Select Linked OSL / Supplier Purchase Options';",
 "toolsHeading.textContent='Use Unbilled Supplier Purchase (optional)';",1
)

# 4. Add searchable Master dropdown wrapper around AM146 native select.
# Search results reflect actual select options populated from active DB masters.
javascript=(assets/'master-search.js').read_text('utf8')
css=(assets/'master-search.css').read_text('utf8')
if 'AM217_SEARCHABLE_OSL_MASTER' not in javascript or 'am217-master-search' not in css:
    raise SystemExit('AM217_STOP_SEARCH_ASSETS_INVALID')
addon=('\n{{-- AM217 searchable OSL Master selection; original OSL Master and '
       'OSL Service field behavior preserved. --}}\n'
       '<style id="am217-searchable-osl-master-style">\n'+css+'\n</style>\n'
       '<script id="am217-searchable-osl-master-script">\n'+javascript+'\n</script>\n')
anchor='<script id="am93-osl-row-sort">'
if s.count(anchor)!=1:raise SystemExit('AM217_STOP_SEARCH_INSERT_POINT')
s=s.replace(anchor,addon+'\n'+anchor,1)

# Invariants: customer financial rendering untouched, single vendor list and
# original Admin posting routes kept. No data mutations in patch.
for needle in ('OSL · PURCHASE ONLY','Pending JC customer billing — not included in customer total'):
    if needle in s:raise SystemExit('AM217_STOP_EXTRA_CUSTOMER_TABLE_REF_REMAINS')
for needle in ("route('erp.fix48.osl-index')",
               "route('erp.job-cards.osl.admin-save',$job->id)",
               "route('erp.osl-masters.quick-create')",
               "route('erp.fix48.osl-edit',$p->id)"):
    if needle not in s:raise SystemExit('AM217_STOP_ORIGINAL_BACKEND_ROUTE_REMOVED_'+needle)
if s.count(marker)!=1 or s.count('@forelse($am217UnbilledPurchases as $p)')!=1:
    raise SystemExit('AM217_STOP_UI_PATCH_MARKERS')
for key in ('data-am90-row','data-am91-osl-id','number_format($am90Taxable+$am90GstAmount,2)',
            'data-am91-description'):
    if s.count(key)!=source.count(key):
        raise SystemExit('AM217_STOP_UNRELATED_CUSTOMER_FINANCIAL_ROW_CHANGED_'+key)

# Fail before installation if Laravel cannot compile the full Blade file.
with tempfile.TemporaryDirectory(prefix='am217_preflight_') as td:
    candidate=Path(td)/'show.blade.php'
    compiled=Path(td)/'show.compiled.php'
    candidate.write_text(s,'utf8',errors='surrogateescape')
    phpscript=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    call=subprocess.run(['php','-r',phpscript,str(candidate),str(compiled)],
                        cwd=root,capture_output=True,text=True,timeout=65)
    if call.returncode:raise SystemExit('AM217_STOP_BLADE_COMPILE '+call.stderr[-300:])
    call=subprocess.run(['php','-l',str(compiled)],
                        cwd=root,capture_output=True,text=True,timeout=30)
    if call.returncode:raise SystemExit('AM217_STOP_COMPILED_PHP_LINT '+(call.stdout+call.stderr)[-350:])
print('AM217_ACTUAL_BILLED_PURCHASE_EXCLUDED_FROM_NEW_OSL_LIST=True')
print('AM217_UNBILLED_SUPPLIER_PURCHASE_STILL_SELECTABLE=True')
print('AM217_NO_PURCHASE_ONLY_ROW_IN_CUSTOMER_BILLING=True')
print('AM217_MASTER_OPTIONS_SEARCHABLE_WITH_TYPING=True')
print('AM217_ORIGINAL_BILLING_FIELDS_ROUTES_UNCHANGED=True')
print('AM217_BLADE_AND_COMPILED_PHP_SYNTAX_PASS')
if mode=='check':
    print('AM217_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists():raise SystemExit('AM217_STOP_PREVIOUS_BACKUP_EXISTS')
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
try:write_atomic(view,s)
except BaseException:
    shutil.copy2(backup,view)
    raise
print('AM217_OSL_UI_STAGING_INSTALLED')
print('AM217_RUN_ARTISAN_VIEW_CLEAR')
