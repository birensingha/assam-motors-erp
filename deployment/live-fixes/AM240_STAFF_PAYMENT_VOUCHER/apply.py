#!/usr/bin/env python3
"""AM240 guarded staging patch: Staff salary/advance/expense paid from SINGLE Payment Voucher.
check => no application source or DB writes; PHP/Blade and JS validated candidate.
apply => atomic sources with backups; no DB schema change, no financial POST.
rollback => restore originals (do not erase posted transactions).
"""
import sys,os,tempfile,shutil,subprocess
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assets=Path(__file__).resolve().parent
paths={
 'payment_controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'staff_controller':root/'app/Http/Controllers/Web/AdminStaffFinanceLedgerController.php',
 'payment_edit':root/'app/Http/Controllers/Web/AdminPaymentVoucherEditController.php',
 'payment_service':root/'app/Services/ExpensePaymentService.php',
 'payment_view':root/'resources/views/payments/create.blade.php',
 'staff_view':root/'resources/views/staff-finance/index.blade.php',
 'new_service':root/'app/Services/StaffPaymentVoucherService.php'
}
backup=root/'storage/app/am-backups/AM240_STAFF_PAYMENT_VOUCHER'
marker='AM240_STAFF_PAYMENT_VOUCHER'
required=[root/'artisan',assets/'StaffPaymentVoucherService.php',assets/'staff-voucher.js',assets/'staff-voucher.css']
required.extend(p for k,p in paths.items() if k!='new_service')
if not all(p.is_file() for p in required):raise SystemExit('AM240_STOP_STAGING_SOURCE_OR_ASSETS_MISSING')
existing={k:(p.read_text('utf8',errors='surrogateescape') if p.is_file() else None) for k,p in paths.items()}
if mode=='rollback':
    if not backup.is_dir() or marker not in (existing['payment_controller'] or ''):
        raise SystemExit('AM240_STOP_MISSING_BACKUP_OR_MARKER')
    if not all((backup/(k+'.before_AM240')).is_file() for k in paths):
        raise SystemExit('AM240_STOP_MISSING_SOURCE_BACKUP')
    if (root/'app/Services/StaffPaymentVoucherService.php').is_file():
        # Reverting source while staff payouts are posted would orphan payment-to-journal reconciliation.
        checker=r"""require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
exit(\Illuminate\Support\Facades\DB::table('workshop_payment_vouchers')
->whereIn('source_type',['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'])->exists()?6:0);"""
        result=subprocess.run(['php','-r',checker],cwd=root,timeout=30)
        if result.returncode:raise SystemExit('AM240_STOP_POSTED_STAFF_VOUCHER_REQUIRES_AUDITED_ROLLBACK')
    for k,p in paths.items():
        b=backup/(k+'.before_AM240')
        if b.read_text('utf8')=='__AM240_ABSENT__':
            if p.exists():p.unlink()
        else:shutil.copy2(b,p)
    print('AM240_ALL_PREVIOUS_SOURCES_RESTORED')
    sys.exit(0)
if marker in (existing['payment_controller'] or ''):
    print('AM240_ALREADY_APPLIED');sys.exit(0)
if existing['new_service'] is not None:
    raise SystemExit('AM240_STOP_NEW_SERVICE_ALREADY_EXISTS')
code={k:v for k,v in existing.items() if v is not None}
def swap(k,a,b,label,count=1):
    n=code[k].count(a)
    print('AM240_ANCHOR_'+label+'='+('PASS' if n==count else 'FAIL_'+str(n)))
    if n!=count:raise SystemExit('AM240_STOP_SOURCE_DRIFT_'+label)
    code[k]=code[k].replace(a,b,count)
# -- Show Staff payable and expense references from actual posted ledger (read-only GET).
swap('payment_controller',"'am231Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')",
 """'am240StaffPayables'=>\\Illuminate\\Support\\Facades\\DB::table('workshop_staff_finance_lines as l')
                ->join('workshop_staff_finance_journals as j','j.id','=','l.journal_id')
                ->where('l.account_code','SALARY_PAYABLE')
                ->select('l.staff_id','j.salary_month')
                ->selectRaw('ROUND(SUM(l.credit-l.debit),2) as payable')
                ->groupBy('l.staff_id','j.salary_month')
                ->havingRaw('SUM(l.credit-l.debit)>0.009')->get(),
            'am240StaffExpenses'=>\\Illuminate\\Support\\Facades\\DB::table('workshop_expenses as e')
                ->leftJoinSub(
                    \\Illuminate\\Support\\Facades\\DB::table('workshop_payment_vouchers')
                      ->where('record_status','ACTIVE')->where('source_type','EXPENSE_VOUCHER')
                      ->select('source_id')->selectRaw('SUM(amount) as paid')->groupBy('source_id'),
                    'paid','paid.source_id','=','e.id'
                )
                ->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')
                ->where('e.record_status','ACTIVE')->whereNotNull('e.staff_id')
                ->select(['e.id','e.staff_id','e.expense_no','jc.job_no'])
                ->selectRaw('ROUND(e.total_amount-COALESCE(paid.paid,0),2) as outstanding')
                ->havingRaw('e.total_amount-COALESCE(paid.paid,0)>0.009')
                ->orderByDesc('e.id')->limit(2500)->get(),
            'am231Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')""",
'CONTROLLER_STAFF_PAYABLE_AND_EXPENSE_SOURCES')
# -- First-class STAFF payment route before obsolete AM230 fail-closed guard.
swap('payment_controller',
"""        // AM230 no Staff/Customer ledger key in current Payment Voucher schema.""",
"""        // AM240_STAFF_PAYMENT_VOUCHER: one Payment Voucher entry, existing balanced staff finance journal.
        if(strtoupper((string)$request->input('payee_category','VENDOR'))==='STAFF') {
            return app(\\App\\Services\\StaffPaymentVoucherService::class)->post($request);
        }
        // AM230 no Staff/Customer ledger key in current Payment Voucher schema.""",
'CONTROLLER_STAFF_POST_NOT_VENDOR')
swap('payment_controller',
"""        $user=$this->admin($request); $data=$request->validate(['reason'=>['required','string','max:300']]); $service->voidPayment($payment,$data['reason'],$this->actorName($user));""",
"""        $user=$this->admin($request);
        $voucher=\\App\\Models\\WorkshopPaymentVoucher::query()->find($payment);
        if($voucher && $voucher->payee_type==='STAFF' &&
           in_array($voucher->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true)) {
            return app(\\App\\Services\\StaffPaymentVoucherService::class)->void($request,$payment);
        }
        $data=$request->validate(['reason'=>['required','string','max:300']]); $service->voidPayment($payment,$data['reason'],$this->actorName($user));""",
'CONTROLLER_STAFF_VOID_LINKED_TO_JOURNAL')
# -- Direct salary disbursements no longer allowed from Staff Ledger POST screen.
swap('staff_controller',
"""        $actor=$this->actor($request);
        $data=$request->validate([
            'staff_id'""",
"""        $actor=$this->actor($request);
        if(in_array((string)$request->input('entry_type'),['SALARY_ADVANCE','SALARY_PAYMENT'],true)
            && $request->attributes->get('am240_from_payment_voucher')!==true) {
            throw ValidationException::withMessages(['entry_type'=>
                'Staff salary payment / advance must be posted from Universal Payment Voucher.']);
        }
        $data=$request->validate([
            'staff_id'""",
'STAFF_LEDGER_DISALLOW_DIRECT_CASH_POSTING')
swap('staff_controller',
"""    public function reverse(Request $request,int $journal): RedirectResponse
    {
        $actor=$this->actor($request);""",
"""    public function reverse(Request $request,int $journal): RedirectResponse
    {
        $actor=$this->actor($request);
        if($request->attributes->get('am240_from_payment_voucher')!==true &&
            DB::table('workshop_payment_vouchers')->where('source_id',$journal)
                ->whereIn('source_type',['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'])
                ->where('record_status','ACTIVE')->exists()) {
            throw ValidationException::withMessages(['reason'=>
                'This Staff salary journal is linked to an active Payment Voucher. Void the Payment Voucher to reverse both records together.']);
        }""",
'STAFF_LEDGER_REVERSAL_LINK_PROTECTION')
swap('staff_controller',
"$payments=DB::table('workshop_payment_vouchers')",
"$payments=DB::table('workshop_payment_vouchers')->whereNotIn('source_type',['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'])",
'STAFF_LEDGER_NO_MIRROR_DUPLICATE')
# -- Old FIX49 edit must not mutate Staff salary journal mirrors independently.
swap('payment_edit',
"""        abort_unless($row,404);
        return $row;""",
"""        abort_unless($row,404);
        abort_if(in_array((string)$row->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true),
            409,'Staff salary payments are immutable. Use audited Payment Voucher Void to reverse the linked Staff Journal.');
        return $row;""",
'STAFF_VOUCHER_AMOUNT_EDIT_BLOCK_GET')
swap('payment_edit',
"""            abort_unless($voucher,404);
            if(strtoupper((string)$voucher->record_status)!=='ACTIVE'""",
"""            abort_unless($voucher,404);
            if(in_array((string)$voucher->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true))
                throw ValidationException::withMessages(['amount'=>
                    'Linked staff salary payment is immutable. Void through Payment Voucher to reverse the Staff Ledger.']);
            if(strtoupper((string)$voucher->record_status)!=='ACTIVE'""",
'STAFF_VOUCHER_AMOUNT_EDIT_BLOCK_POST')
# -- Old generic Expense service void may NOT unlink a salary journal by itself.
swap('payment_service',
"""if(!$p || $p->record_status==='VOID') throw ValidationException::withMessages(['payment'=>'Payment Voucher not found or already voided.']);""",
"""if(!$p || $p->record_status==='VOID') throw ValidationException::withMessages(['payment'=>'Payment Voucher not found or already voided.']);
            if(in_array($p->source_type,['STAFF_SALARY_ADVANCE','STAFF_SALARY_PAYMENT'],true))
                throw ValidationException::withMessages(['payment'=>'Linked Staff Salary Voucher must use audited Staff ledger reversal; generic void denied.']);""",
'BLOCK_UNLINKED_GENERIC_VOID')
# -- Salary Entry screen remains for NON-CASH salary provision/advance adjustment only.
swap('staff_view',
"""  <a class="am262-btn" href="{{ route('erp.payments.index') }}">Payment Register</a>""",
"""  <a class="am262-btn am262-primary" href="{{ route('erp.payments.create') }}">+ New Payment Voucher</a>
  <a class="am262-btn" href="{{ route('erp.payments.index') }}">Payment Register</a>""",
'STAFF_LEDGER_LINK_TO_PAYMENT_ENTRY')
swap('staff_view',
"""<h2 class="am262-heading">Post Staff Salary / Advance Entry</h2>""",
"""<h2 class="am262-heading">Salary Provision & Non-Cash Advance Adjustment</h2>""",
'STAFF_LEDGER_NO_DIRECT_PAYMENT_LABEL')
swap('staff_view',
"""        <option value="SALARY_ADVANCE" @selected(old('entry_type')==='SALARY_ADVANCE')>Advance Paid — Salary Advance Dr / Cash Bank Cr</option>
        <option value="SALARY_PAYMENT" @selected(old('entry_type')==='SALARY_PAYMENT')>Salary Paid — Salary Payable Dr / Cash Bank Cr</option>
""",'','STAFF_LEDGER_REMOVE_DIRECT_CASH_OPTIONS')
swap('staff_view',
"""<p class="am262-note">Every posting creates a balanced journal. Existing Expenses and Payment Vouchers are not posted again. Salary analysis settings do not automatically create provisions.</p>""",
"""<p class="am262-note">Payment/Salary Advance aur Staff Expense reimbursement **Universal Payment Voucher** se post karein. Yahan sirf Salary Provision aur non-cash Advance Adjustment rahega; balanced journal history yahin dikhegi.</p>""",
'STAFF_LEDGER_POSTING_GUIDANCE')
# -- Staff salary / expense form within unified Payment Voucher, no duplicate posted form fields.
panel="""{{-- AM240 Staff Payment Voucher, backed by existing Staff Ledger/Expense payable. --}}
<style id="am240-staff-style">
"""+(assets/'staff-voucher.css').read_text('utf8')+"""
</style>
<div id="am240-staff-panel" hidden>
 <h3>Staff Payment / Advance – Linked Financial Ledger</h3>
 <div class="am240-grid">
  <label id="am240-kind-box">Payment For *
   <select id="am240-kind">
    <option value="SALARY_PAYMENT">Salary Payable – Against Reference</option>
    <option value="EXPENSE_REIMBURSEMENT">Staff Expense Reimbursement – Against Reference</option>
   </select>
  </label>
  <label id="am240-month-box">Salary Month *
   <input type="month" name="salary_month" id="am240-month" value="{{ old('salary_month',now('Asia/Kolkata')->format('Y-m')) }}">
  </label>
  <label id="am240-expense-box" hidden>Approved Staff Expense Reference *
   <select name="staff_expense_id" id="am240-expense"><option value="">Select approved Staff Expense</option></select>
  </label>
 </div>
 <input type="hidden" id="am240-kind-value" name="staff_payment_kind">
 <input type="hidden" id="am240-against-value" name="staff_payment_against">
 <div id="am240-balance"></div>
 <div id="am240-staff-notice" role="status"></div>
</div>
<script type="application/json" id="am240-references-data">@json(['payables'=>$am240StaffPayables ?? [],'expenses'=>$am240StaffExpenses ?? []])</script>
"""
swap('payment_view','<div id="am237-reference-slot" hidden></div>',
'<div id="am237-reference-slot" hidden></div>\n'+panel,
'UNIFIED_STAFF_PAYMENT_PANEL')
swap('payment_view','@endpush',
'<script id="am240-staff-script">\n'+(assets/'staff-voucher.js').read_text('utf8')+'\n</script>\n@endpush',
'STAFF_PAYMENT_UI_SCRIPT')
# Ensure the user-visible locked warning disappears for supported staff form.
# Note that Customer remains server-side blocked and legacy warn still applies.
code['new_service']=(assets/'StaffPaymentVoucherService.php').read_text('utf8')
if code['payment_controller'].count(marker)!=1:raise SystemExit('AM240_STOP_REQUIRED_MARKER_DUPLICATED')
if 'AM230 no Staff/Customer ledger key' not in code['payment_controller']:
    raise SystemExit('AM240_STOP_CUSTOMER_GUARD_REMOVED')
if 'AM237_UNIFIED_PAYMENT_TO' not in code['payment_view']:raise SystemExit('AM240_STOP_UNIFIED_FORM_MISSING')
if code['payment_view'].count('name="amount"')!=1 or code['payment_view'].count('name="staff_id"')!=1:
    raise SystemExit('AM240_STOP_DUPLICATE_AMOUNT_OR_STAFF_FIELDS')
# Check actual payment voucher and staff journal schema without any writes.
schema=r"""require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach([
'workshop_staff_finance_journals'=>['id','staff_id','request_key','entry_type','amount','voucher_no','salary_month'],
'workshop_staff_finance_lines'=>['journal_id','staff_id','debit','credit','account_code'],
'workshop_payment_vouchers'=>['id','source_type','source_id','source_reference','payee_type','staff_id','request_key','amount','record_status'],
'workshop_expenses'=>['id','staff_id','expense_no','record_status','total_amount']
] as $table=>$columns){
 if(!\Illuminate\Support\Facades\Schema::hasTable($table))exit(5);
 foreach($columns as $column)if(!\Illuminate\Support\Facades\Schema::hasColumn($table,$column))exit(6);
}
echo 'AM240_LEDGER_SCHEMA_COMPATIBLE_PASS'.PHP_EOL;"""
test=subprocess.run(['php','-r',schema],cwd=root,capture_output=True,text=True,timeout=50)
if test.returncode:raise SystemExit('AM240_STOP_SCHEMA_CHECK '+(test.stdout+test.stderr)[-300:])
print(test.stdout.strip())
with tempfile.TemporaryDirectory(prefix='am240_preflight_') as t:
    td=Path(t)
    for k in ('payment_controller','staff_controller','payment_edit','payment_service','new_service'):
        p=td/(k+'.php');p.write_text(code[k],'utf8',errors='surrogateescape')
        check=subprocess.run(['php','-l',str(p)],cwd=root,capture_output=True,text=True,timeout=30)
        if check.returncode:raise SystemExit('AM240_STOP_PHP_'+k+' '+(check.stdout+check.stderr)[-350:])
    if shutil.which('node'):
        check=subprocess.run(['node','--check',str(assets/'staff-voucher.js')],capture_output=True,text=True,timeout=30)
        if check.returncode:raise SystemExit('AM240_STOP_JS '+check.stderr[-350:])
    else:print('AM240_NO_NODE_ON_STAGING_VERIFY_IN_GITHUB_CI')
    compiler=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for k in ('payment_view','staff_view'):
        blade=td/(k+'.blade.php');compiled=td/(k+'.compiled.php')
        blade.write_text(code[k],'utf8',errors='surrogateescape')
        for cmd in (['php','-r',compiler,str(blade),str(compiled)],['php','-l',str(compiled)]):
            check=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=45)
            if check.returncode:raise SystemExit('AM240_STOP_BLADE_'+k+' '+(check.stdout+check.stderr)[-350:])
print('AM240_STAFF_ADVANCE_PAYMENT_IN_UNIVERSAL_VOUCHER_PASS')
print('AM240_STAFF_SALARY_PAYABLE_IN_UNIVERSAL_VOUCHER_PASS')
print('AM240_STAFF_EXPENSE_SETTLES_ORIGINAL_EXPENSE_ONLY_PASS')
print('AM240_LINKED_STAFF_JOURNAL_AND_VOUCHER_VOID_PROTECTION_PASS')
print('AM240_SALARY_STAFF_FINANCE_DIRECT_PAYOUT_BLOCK_PASS')
print('AM240_CUSTOMER_REFUND_REMAINS_LOCKED_PASS')
print('AM240_PHP_JS_BLADE_PREFLIGHT_PASS')
if mode=='check':
    print('AM240_READONLY_SOURCE_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM240_STOP_EXISTING_BACKUP')
backup.mkdir(parents=True,exist_ok=False)
for k,p in paths.items():
    b=backup/(k+'.before_AM240')
    if existing[k] is None:b.write_text('__AM240_ABSENT__','utf8')
    else:shutil.copy2(p,b)
def atomic(p,value):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am240_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(value)
        if p.exists():os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
try:
    for k,p in paths.items():atomic(p,code[k])
except BaseException:
    for k,p in paths.items():
        b=backup/(k+'.before_AM240')
        if b.read_text('utf8')=='__AM240_ABSENT__':
            if p.exists():p.unlink()
        else:shutil.copy2(b,p)
    raise
print('AM240_STAGING_SOURCES_APPLIED_WITH_BACKUPS')
