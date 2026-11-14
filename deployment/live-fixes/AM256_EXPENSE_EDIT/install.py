#!/usr/bin/env python3
"""AM256: guarded Expense amount edit UI + server route, DB migration separately.
Usage: install.py check|prepare|apply|verify|rollback STAGING_ROOT.
No edit to user business data in installer. Only Admin authenticated POST edits.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,re,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','apply','verify','rollback'):
    raise SystemExit("AM256_USAGE check|prepare|apply|verify|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit("STOP_AM256_NOT_STAGING")
controller='app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
view='resources/views/expenses/show.blade.php'
routes='routes/web.php'
service='app/Services/ExpenseAmountCorrectionService.php'
migration='database/migrations/2026_11_14_193000_am256_expense_amount_corrections.php'
targets=[controller,view,routes]
backups=root/'storage/app/am-backups'
pointer=backups/'AM256_LAST_BACKUP.txt'
marker='AM256_AUDITED_EXPENSE_AMOUNT_EDIT'
if not all((root/r).is_file() for r in targets):raise SystemExit('STOP_AM256_REQUIRED_SOURCE_MISSING')
for r in [service,migration]:
    if not (pkg/'files'/r).is_file():raise SystemExit('STOP_AM256_PACKAGE_PAYLOAD_MISSING_'+r)

def sha(b):return hashlib.sha256(b).hexdigest()
def cmd(args,timeout=95):return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)
def atomic(path,b):
    path.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am256_',dir=path.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,(path.stat().st_mode&0o777) if path.exists() else 0o644)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def phplint(b,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(b);name=Path(f.name)
    try:
        p=cmd(['php','-l',str(name)],45)
        if p.returncode:raise SystemExit('STOP_AM256_PHP_LINT_'+label+' '+(p.stdout+p.stderr)[-900:])
    finally:name.unlink(missing_ok=True)
def blade_lint(b,label):
    with tempfile.TemporaryDirectory(prefix='am256_blade_') as td:
        p=Path(td)/'show.blade.php';out=Path(td)/'compiled.php'
        p.write_bytes(b)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2], $app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        result=cmd(['php','-r',php,str(p),str(out)],60)
        if result.returncode or not out.is_file():
            raise SystemExit('STOP_AM256_BLADE_COMPILE_'+label+' '+(result.stdout+result.stderr)[-900:])
        phplint(out.read_bytes(),'COMPILED_'+label)
def once(text,old,new,label):
    n=text.count(old)
    if n!=1:raise SystemExit('STOP_AM256_SOURCE_DRIFT_'+label+'_COUNT_'+str(n))
    return text.replace(old,new,1)
def dbcheck():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;
if(!Schema::hasTable('am_expense_amount_corrections'))exit(21);
foreach(['expense_id','expense_no','old_total_amount','new_total_amount','reason','changed_by','created_at'] as $c)
 if(!Schema::hasColumn('am_expense_amount_corrections',$c))exit(22);
$e=DB::table('workshop_expenses')->where('expense_no','EXP-20261114-000026')->first();
if(!$e)exit(23);
$payments=DB::table('workshop_payment_vouchers')->where('source_type','EXPENSE_VOUCHER')->where('source_id',$e->id)->count();
$alloc=DB::table('workshop_payment_allocations')->where('purchase_ref','EXPENSE_VOUCHER::'.$e->id)->count();
echo 'AM256_AUDIT_TABLE_AND_TARGET_EXPENSE_PRESENT'."\n";
echo 'AM256_TARGET_STATE='.json_encode(['id'=>$e->id,'no'=>$e->expense_no,'total'=>$e->total_amount,
'paid'=>$e->paid_amount,'status'=>$e->payment_status,'payment_refs'=>$payments,'allocations'=>$alloc])."\n";"""
    result=cmd(['php','-r',php],50)
    if result.returncode:raise SystemExit('STOP_AM256_AUDIT_TABLE_OR_RECORD_MISSING '+(result.stdout+result.stderr)[-1000:])
    print(result.stdout.strip())
def patch():
    before={r:(root/r).read_bytes() for r in targets}
    src={r:before[r].decode('utf8','surrogateescape') for r in targets}
    if any(marker in src[r] for r in targets):raise SystemExit('STOP_AM256_ALREADY_PATCHED_VERIFY')
    method=r'''
    /** AM256_AUDITED_EXPENSE_AMOUNT_EDIT: only Owner/Admin and unpaid expense. */
    public function correctExpenseAmount(
        Request $request,
        int $expense,
        \App\Services\ExpenseAmountCorrectionService $correction
    ): RedirectResponse {
        $user=$this->admin($request);
        abort_unless(strtolower(trim((string)$user->role))==='admin',403,
            'Owner/Admin permission required to correct an Expense Voucher.');
        $data=$request->validate([
            'taxable_amount'=>['required','numeric','gt:0','max:99999999'],
            'expected_current_amount'=>['required','numeric','min:0'],
            'reason'=>['required','string','min:8','max:300'],
        ]);
        $result=$correction->correct(
            $expense,
            (float)$data['taxable_amount'],
            (float)$data['expected_current_amount'],
            $data['reason'],
            $this->actorName($user)
        );
        return redirect()->route('erp.expenses.show',$expense)
            ->with('success','Expense amount corrected ₹'.number_format($result['old_total'],2).
                ' → ₹'.number_format($result['new_total'],2).' with audited reason.');
    }

'''
    src[controller]=once(src[controller],
        '    public function voidExpense(Request $request, int $expense, ExpensePaymentService $service): RedirectResponse',
        method+'    public function voidExpense(Request $request, int $expense, ExpensePaymentService $service): RedirectResponse',
        'ADMIN_EDIT_CONTROLLER')
    src[controller]=once(src[controller],
        "        $this->admin($request); return view('expenses.show',$service->showExpense($expense));",
        """        $this->admin($request);
        $expenseData=$service->showExpense($expense);
        $am256Corrections=\\Illuminate\\Support\\Facades\\DB::table('am_expense_amount_corrections')
            ->where('expense_id',$expense)->orderByDesc('id')->limit(50)->get();
        return view('expenses.show',$expenseData+['am256Corrections'=>$am256Corrections]);""",
        'DETAIL_HISTORY')
    src[routes]=once(src[routes],
        "Route::post('/expenses/{expense}/void',",
        """// AM256_AUDITED_EXPENSE_AMOUNT_EDIT: POST under existing authenticated Admin group.
    Route::post('/expenses/{expense}/correct-amount',
        [AdminExpensePaymentPageController::class,'correctExpenseAmount'])
        ->whereNumber('expense')->name('erp.expenses.correct-amount');
    Route::post('/expenses/{expense}/void',""",
        'ROUTE_POST_CORRECTION')

    editpanel=r'''
@if(($expense['record_status']??'')==='ACTIVE' &&
    ($expense['payment_status']??'')==='UNPAID' &&
    abs((float)($expense['paid_amount']??0))<0.009 &&
    strtolower((string)(auth()->user()?->role??''))==='admin')
<details id="am256-expense-edit" class="card compact section-gap" style="padding:14px;border:1px solid #b9cfe3;border-radius:8px;margin-bottom:14px">
 <summary style="cursor:pointer;font-weight:700">✎ Edit Expense Amount — Unpaid Voucher Only</summary>
 <p style="margin:10px 0">Change the saved expense amount without creating a payment. Voucher number, linked Job Card, employee and existing GST % stay unchanged.</p>
 <form method="POST" action="{{ route('erp.expenses.correct-amount',$expense['id']) }}" class="form-grid" style="gap:12px">
  @csrf
  <input type="hidden" name="expected_current_amount" value="{{ number_format((float)$expense['taxable_amount'],2,'.','') }}">
  <label>Current Amount ₹
   <input type="text" readonly value="{{ number_format((float)$expense['taxable_amount'],2,'.','') }}">
  </label>
  <label>Corrected Base Amount ₹ *
   <input type="number" name="taxable_amount" min="0.01" max="99999999" step="0.01" value="{{ old('taxable_amount',number_format((float)$expense['taxable_amount'],2,'.','')) }}" required>
  </label>
  <label>Correction Reason * (audit required)
   <input type="text" name="reason" minlength="8" maxlength="300" value="{{ old('reason','') }}" placeholder="Example: Oil coupon value corrected from ₹175 to ₹150" required>
  </label>
  <div><small>GST {{ number_format((float)$expense['gst_percent'],2) }}% will be recalculated automatically. Unpaid/paid fields are not changed.</small></div>
  <div><button class="btn primary" type="submit" onclick="return confirm('Confirm audited correction of this unpaid Expense amount?')">Save Amount Correction</button></div>
 </form>
</details>
@endif
'''
    src[view]=once(src[view],
        '<div class="summary-grid">',
        editpanel+'<div class="summary-grid">',
        'DETAIL_CORRECTION_FORM')
    button=r'''@if(($expense['record_status']??'')==='ACTIVE' && ($expense['payment_status']??'')==='UNPAID' &&
        abs((float)($expense['paid_amount']??0))<0.009 && strtolower((string)(auth()->user()?->role??''))==='admin')
 <a class="btn" href="#am256-expense-edit" onclick="document.getElementById('am256-expense-edit').open=true">✎ Edit Amount</a>
 @endif
'''
    src[view]=once(src[view],
        '<button type="button" class="btn" onclick="window.print()">🖨 Print</button>',
        button+'<button type="button" class="btn" onclick="window.print()">🖨 Print</button>',
        'TOP_EDIT_BUTTON')
    history=r'''
@if(($am256Corrections??collect())->count()>0)
<details class="card compact section-gap" style="padding:12px;margin:10px 0;border:1px solid #cbd7e3">
 <summary style="cursor:pointer;font-weight:700">Expense Amount Correction History ({{ $am256Corrections->count() }})</summary>
 <div class="line-scroll">
  <table class="data-table">
   <thead><tr><th>Date</th><th>Changed By</th><th>Original Total</th><th>Corrected Total</th><th>Audit Reason</th></tr></thead>
   <tbody>
    @foreach($am256Corrections as $entry)
    <tr><td>{{ $entry->created_at }}</td><td>{{ $entry->changed_by }}</td>
        <td>₹{{ number_format((float)$entry->old_total_amount,2) }}</td>
        <td>₹{{ number_format((float)$entry->new_total_amount,2) }}</td>
        <td>{{ $entry->reason }}</td></tr>
    @endforeach
   </tbody>
  </table>
 </div>
</details>
@endif
'''
    src[view]=once(src[view],
        '<div class="card section-gap"><div class="section-head"><h2 class="section-title">Payment History</h2>',
        history+'<div class="card section-gap"><div class="section-head"><h2 class="section-title">Payment History</h2>',
        'CORRECTION_AUDIT_HISTORY')
    modified={r:src[r].encode('utf8','surrogateescape') for r in targets}
    for r in [controller,routes]:
        phplint(before[r],r.replace('/','_')+'_BASE')
        phplint(modified[r],r.replace('/','_')+'_UPDATED')
    blade_lint(before[view],'BASE_SHOW')
    blade_lint(modified[view],'UPDATED_SHOW')
    phplint((pkg/'files'/service).read_bytes(),'NEW_CORRECTION_SERVICE')
    phplint((pkg/'files'/migration).read_bytes(),'NEW_AUDIT_MIGRATION')
    print('AM256_PHP_AND_BLADE_PREFLIGHT_PASS')
    print('AM256_NO_PAYMENT_OR_JC_DATA_MUTATIONS_BY_INSTALLER')
    return before,modified

def verify():
    dbcheck()
    for r in targets+[service]:
        if not (root/r).is_file():raise SystemExit('STOP_AM256_VERIFY_SOURCE_MISSING_'+r)
    code=(root/controller).read_text('utf8','replace')
    blade=(root/view).read_text('utf8','replace')
    route=(root/routes).read_text('utf8','replace')
    impl=(root/service).read_text('utf8','replace')
    if any(v not in code for v in ['correctExpenseAmount(', 'am256Corrections','Owner/Admin permission required']):
        raise SystemExit('STOP_AM256_CONTROLLER_EDIT_MISSING')
    for v in ['am256-expense-edit','Correction History','route(\'erp.expenses.correct-amount\'']:
        if v not in blade:raise SystemExit('STOP_AM256_VIEW_EDIT_MISSING_'+v)
    if 'erp.expenses.correct-amount' not in route:raise SystemExit('STOP_AM256_EDIT_POST_ROUTE_MISSING')
    if 'am_expense_amount_corrections' not in impl or 'lockForUpdate()' not in impl:
        raise SystemExit('STOP_AM256_SERVER_FINANCIAL_AUDIT_GUARD_MISSING')
    routeProbe=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$r=\Illuminate\Support\Facades\Route::getRoutes()->getByName('erp.expenses.correct-amount');
if(!$r || !in_array('POST',$r->methods(),true) || !in_array('auth:web',$r->gatherMiddleware(),true) ||
 !in_array('App\\Http\\Middleware\\EnsureErpAdmin',$r->gatherMiddleware(),true)) exit(31);
echo 'AM256_EDIT_POST_AUTH_ADMIN_ROUTE_VERIFIED'."\n";"""
    rr=cmd(['php','-r',routeProbe],45)
    if rr.returncode:raise SystemExit('STOP_AM256_ADMIN_POST_ROUTE_BAD '+(rr.stdout+rr.stderr)[-900:])
    print(rr.stdout.strip())
    print('AM256_EXPENSE_AMOUNT_EDITOR_AND_AUDIT_INSTALLED_VERIFIED')

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM256_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM256_EXPENSE_EDIT_'):
        raise SystemExit('STOP_AM256_BACKUP_PATH_UNSAFE')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,h in manifest['installed'].items():
        p=root/rel
        if not p.is_file() or sha(p.read_bytes())!=h:
            raise SystemExit('STOP_AM256_SOURCE_CHANGED_REFUSE_ROLLBACK_'+rel)
    for rel in targets:atomic(root/rel,(backup/rel).read_bytes())
    (root/service).unlink(missing_ok=True)
    cmd(['php','artisan','optimize:clear'],90)
    print('AM256_SOURCE_RESTORED_AUDIT_HISTORY_TABLE_PRESERVED')
    sys.exit(0)

if mode=='prepare':
    dest=root/migration
    data=(pkg/'files'/migration).read_bytes()
    phplint(data,'MIGRATION')
    if dest.exists():
        if sha(dest.read_bytes())!=sha(data):raise SystemExit('STOP_AM256_MIGRATION_CONFLICT')
        print('AM256_MIGRATION_ALREADY_PREPARED')
    else:
        atomic(dest,data)
        print('AM256_MIGRATION_PREPARED_FILE_ONLY')
    print('AM256_DB_BACKUP_REQUIRED_BEFORE_ARTISAN_MIGRATE')
    sys.exit(0)

before,modified=patch()
if mode=='check':
    print('AM256_CHECK_READ_ONLY_PREFLIGHT_PASS')
    sys.exit(0)
dbcheck()
if (root/service).exists():raise SystemExit('STOP_AM256_SERVICE_FILE_CONFLICT')
backup=backups/('AM256_EXPENSE_EDIT_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for r,b in before.items():
    p=backup/r;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b);os.chmod(p,0o600)
modified[service]=(pkg/'files'/service).read_bytes()
written=[]
try:
    for r,data in modified.items():
        if r in before and (root/r).read_bytes()!=before[r]:
            raise RuntimeError('SOURCE_CHANGED_'+r)
        atomic(root/r,data);written.append(r)
    for cmdline in (['php','artisan','view:clear'],['php','artisan','optimize:clear']):
        o=cmd(cmdline,100)
        if o.returncode:raise RuntimeError('ARTISAN_CLEAR '+(o.stdout+o.stderr)[-900:])
    verify()
except Exception as e:
    for r in written:
        if r in before:atomic(root/r,before[r])
        else:(root/r).unlink(missing_ok=True)
    cmd(['php','artisan','optimize:clear'],90)
    raise SystemExit('STOP_AM256_APPLY_RESTORED_SOURCES '+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{r:sha(b) for r,b in modified.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM256_AUDITED_UNPAID_EXPENSE_AMOUNT_EDITOR_INSTALLED')
print('AM256_PRIVATE_SOURCE_BACKUP',backup)
print('AM256_BROWSER_POST_CORRECTION_NOT_RUN')
