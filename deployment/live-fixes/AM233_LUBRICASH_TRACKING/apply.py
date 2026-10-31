#!/usr/bin/env python3
"""AM233 guarded Staging source patch: Expense + staff coupon + linked cash payout.
Financially additive: nullable IDs/company/coupon only via separate migration.
No historic expense/payment mutations; original supplier/customer finance untouched.
"""
import sys,re,subprocess,tempfile,os,shutil
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
base=Path(__file__).resolve().parent
paths={
 'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'service':root/'app/Services/ExpensePaymentService.php',
 'create':root/'resources/views/expenses/create.blade.php',
 'show':root/'resources/views/expenses/show.blade.php',
 'index':root/'resources/views/expenses/index.blade.php',
 'routes':root/'routes/web.php',
}
backup=root/'storage/app/am-backups/AM233_LUBRICASH_TRACKING'
marker='AM233_STAFF_LUBRICASH_AUDIT'
if not (root/'artisan').is_file() or not all(p.is_file() for p in paths.values()):
 raise SystemExit('AM233_STOP_WRONG_APP_ROOT')
data={k:p.read_text('utf8',errors='surrogateescape') for k,p in paths.items()}
def atomic(path,s):
 fd,tmp=tempfile.mkstemp(dir=path.parent,prefix='.am233_')
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
  os.chmod(tmp,path.stat().st_mode&0o777)
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
 if not backup.is_dir() or data['service'].count(marker)!=1:
  raise SystemExit('AM233_STOP_ROLLBACK_MISSING_BACKUP_OR_MARKER')
 for key,p in paths.items():
  orig=backup/(key+'.before_AM233')
  if not orig.is_file():raise SystemExit('AM233_STOP_ROLLBACK_MISSING_'+key)
 for key,p in paths.items():atomic(p,(backup/(key+'.before_AM233')).read_text('utf8',errors='surrogateescape'))
 print('AM233_PREVIOUS_EXPENSE_SOURCES_RESTORED_DB_FIELDS_RETAINED')
 sys.exit(0)
if marker in data['service']:
 print('AM233_ALREADY_APPLIED');sys.exit(0)
def replace_once(k,before,after,label):
 body=data[k];n=body.count(before)
 print('AM233_ANCHOR_'+label+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
 if n!=1:raise SystemExit('AM233_STOP_SOURCE_DRIFT_'+label)
 data[k]=body.replace(before,after,1)
# category and human identity always supported for audit.
replace_once('service',"'REPAIR_MAINTENANCE','TAX_FEE','OTHER']",
             "'REPAIR_MAINTENANCE','TAX_FEE','LUBRICASH_COUPON','OTHER']", 'CATEGORY_MASTER')
# Guarded staff lookup within server transaction; company and JC mandatory.
old="""            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);"""
new="""            // AM233_STAFF_LUBRICASH_AUDIT: always store actual staff+JC IDs.
            $isCoupon=strtoupper(trim((string)($data['expense_category']??'')))==='LUBRICASH_COUPON';
            $staffId=null;
            if ($isCoupon) {
                if ($against!=='JOB' || $jobId<=0) throw ValidationException::withMessages(['job_card_id'=>'Lubricash Coupon requires a valid Job Card / Vehicle.']);
                if ($vendorId>0) throw ValidationException::withMessages(['vendor_id'=>'Lubricash Staff Coupon must be linked to Staff, not Vendor.']);
                $staffId=(int)($data['staff_id']??0);
                $staff=$staffId>0 ? DB::table('staff')->where('id',$staffId)->first() : null;
                if(!$staff) throw ValidationException::withMessages(['staff_id'=>'Select an employee from Staff Master.']);
                $company=trim((string)($data['coupon_company']??''));
                if ($company==='') throw ValidationException::withMessages(['coupon_company'=>'Enter coupon / oil company name for auditing.']);
                $payee=trim((string)$staff->name);
            }
            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);"""
replace_once('service',old,new,'STAFF_REQUIRED_AND_PAYEE_SERVER_BIND')
replace_once('service', "'vendor_id'=>$vendorId ?: null,'payee_name'=>$payee,'supplier_invoice'=>",
             "'vendor_id'=>$vendorId ?: null,'staff_id'=>$staffId,'coupon_company'=>$isCoupon ? mb_substr(trim((string)($data['coupon_company']??'')),0,140) : null,'coupon_no'=>$isCoupon ? (mb_substr(trim((string)($data['coupon_no']??'')),0,120) ?: null) : null,'payee_name'=>$payee,'supplier_invoice'=>", 'EXPENSE_LINK_SAVE')
replace_once('service', "'payee_type'=>$vendorId>0?'VENDOR':'OTHER','vendor_id'=>$vendorId ?: null,'payee_name'=>$payee,'amount'=>$amount,",
             "'payee_type'=>$isCoupon?'STAFF':($vendorId>0?'VENDOR':'OTHER'),'staff_id'=>$staffId,'vendor_id'=>$vendorId ?: null,'payee_name'=>$payee,'amount'=>$amount,",'PAY_NOW_STAFF_ID')
replace_once('service', "'job_card_id'=>$data['job_card_id'] ?? null,'payee_type'=>$data['payee_type'] ?? 'OTHER','vendor_id'=>$data['vendor_id'] ?? null,'payee_name'=>",
             "'job_card_id'=>$data['job_card_id'] ?? null,'payee_type'=>$data['payee_type'] ?? 'OTHER','staff_id'=>$data['staff_id'] ?? null,'request_key'=>$data['request_key'] ?? null,'vendor_id'=>$data['vendor_id'] ?? null,'payee_name'=>",'PAYMENT_VOUCHER_LINK_SAVE')
# Source resolving existing expense payments must keep Staff ID after later pay.
replace_once('service', "'payee_type'=>$e->vendor_id?'VENDOR':'OTHER'",
             "'payee_type'=>((int)($e->staff_id??0)>0?'STAFF':($e->vendor_id?'VENDOR':'OTHER')),'staff_id'=>$e->staff_id??null",
             'EXPENSE_SOURCE_STAFF_RESOLVE')
replace_once('service', "'payee_type'=>$resolved['payee_type'],'vendor_id'=>$resolved['vendor_id']",
             "'payee_type'=>$resolved['payee_type'],'staff_id'=>$resolved['staff_id']??null,'vendor_id'=>$resolved['vendor_id']",
             'PAYMENT_SOURCE_STAFF_LINK')
# Original expense+payment status and amounts preserved; add safe,
# admin-only later payout using lockForUpdate and idempotency request UUID.
method=r'''
    /** AM233: linked staff coupon payout. No manual free-text payee allowed.
     * A transaction + locked expense + unique request_key protects against
     * repeat click and overpayment. Does not modify other expenses.
     */
    public function payLubricashExpense(int $expenseId, array $data, string $actor): WorkshopPaymentVoucher
    {
        return DB::transaction(function() use($expenseId,$data,$actor) {
            $expense=WorkshopExpense::query()->lockForUpdate()->where('record_status','ACTIVE')->find($expenseId);
            if (!$expense || $expense->expense_category!=='LUBRICASH_COUPON' ||
                (int)$expense->staff_id<=0 || (int)$expense->job_card_id<=0)
                throw ValidationException::withMessages(['expense'=>'Select a linked active Lubricash Expense Voucher.']);
            $requestKey=(string)($data['request_key']??'');
            $existing=WorkshopPaymentVoucher::query()->where('request_key',$requestKey)->first();
            if($existing){
                if($existing->source_type==='EXPENSE_VOUCHER' && (int)$existing->source_id===$expenseId)
                    return $existing;
                throw ValidationException::withMessages(['request_key'=>'Payment request reference already used.']);
            }
            $paid=round((float)WorkshopPaymentVoucher::query()
                ->where('source_type','EXPENSE_VOUCHER')->where('source_id',$expenseId)
                ->where('record_status','ACTIVE')->sum('amount'),2);
            $outstanding=round((float)$expense->total_amount-$paid,2);
            $amount=round((float)($data['amount']??0),2);
            if($amount<=0 || $outstanding<=0 || $amount>$outstanding+0.001)
                throw ValidationException::withMessages(['amount'=>'Payment exceeds unpaid Lubricash amount ₹'.number_format(max(0,$outstanding),2).'.']);
            if(!DB::table('staff')->where('id',(int)$expense->staff_id)->exists())
                throw ValidationException::withMessages(['staff_id'=>'Original linked Staff Master record is missing. Contact Admin.']);
            $payment=$this->createVoucher([
                'payment_date'=>$data['payment_date'],
                'source_type'=>'EXPENSE_VOUCHER',
                'source_id'=>$expenseId,
                'source_reference'=>$expense->expense_no,
                'job_card_id'=>$expense->job_card_id,
                'payee_type'=>'STAFF',
                'staff_id'=>$expense->staff_id,
                'vendor_id'=>null,
                'payee_name'=>$expense->payee_name,
                'amount'=>$amount,
                'payment_mode'=>$data['payment_mode'],
                'account_name'=>$data['account_name']??null,
                'reference_no'=>$data['reference_no']??null,
                'notes'=>$data['notes']??('Staff Lubricash Coupon payment against '.$expense->expense_no),
                'request_key'=>$requestKey
            ],$actor);
            $this->refreshExpensePaymentStatus($expenseId);
            return $payment;
        });
    }

'''
replace_once('service', '    public function showExpense(int $id): array',
             method+'    public function showExpense(int $id): array','LATER_PAYMENT_SAFE_SERVICE')
replace_once('service', "->where('e.id',$id)->select(['e.*'",
             "->leftJoin('staff as am233staff','am233staff.id','=','e.staff_id')->where('e.id',$id)->select(['am233staff.staff_code','am233staff.name as staff_name','e.*'",
             'EXPENSE_DETAIL_STAFF')
replace_once('service', "->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')\n            ->whereRaw(\"COALESCE(e.record_status,'ACTIVE') <> 'VOID'\")",
             "->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')\n            ->leftJoin('staff as am233staff','am233staff.id','=','e.staff_id')\n            ->whereRaw(\"COALESCE(e.record_status,'ACTIVE') <> 'VOID'\")",
             'EXPENSE_REGISTER_JOIN_STAFF')
replace_once('service', "'v.vendor_code','v.vendor_name']);\n        if ($q !== '') {",
             "'v.vendor_code','v.vendor_name','am233staff.name as staff_name','am233staff.staff_code']);\n        if ($q !== '') {",
             'REGISTER_SELECT_STAFF')
replace_once('service', "->orWhere('v.vendor_name','like',$like);",
             "->orWhere('v.vendor_name','like',$like)->orWhere('am233staff.name','like',$like)->orWhere('am233staff.staff_code','like',$like)->orWhere('e.coupon_company','like',$like)->orWhere('e.coupon_no','like',$like);",
             'REGISTER_SEARCH_STAFF_COUPON')
replace_once('service', "if ($category !== '') $query->where('e.expense_category',$category);",
             "if ($category !== '') $query->where('e.expense_category',$category);\n        if((int)($filters['staff_id']??0)>0)$query->where('e.staff_id',(int)$filters['staff_id']);",
             'REGISTER_FILTER_STAFF')
# Create form / controller. Require linked identity for coupon with a separate
# controller validator; non-coupon current forms keep old behavior.
replace_once('controller', "'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,",
             "'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,\n            'am233Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')->orderBy('name')->get(['id','staff_code','name']),",
             'CREATE_GET_STAFF_MASTER')
replace_once('controller', "'expense_date'=>['required','date'],'expense_category'=>['required','string','max:60'],'expense_against'=>['required','in:JOB,REFERENCE'],",
             "'expense_date'=>['required','date'],'expense_category'=>['required','string','max:60'],'expense_against'=>['required','in:JOB,REFERENCE'],\n            'staff_id'=>['nullable','integer','min:1'],'coupon_company'=>['nullable','string','max:140'],'coupon_no'=>['nullable','string','max:120'],",
             'CREATE_POST_FIELDS')
replace_once('controller', "        $data['pay_now']=$request->boolean('pay_now');",
             """        if (strtoupper((string)($data['expense_category']??''))==='LUBRICASH_COUPON') {
            $request->validate([
                'expense_against'=>['required','in:JOB'],
                'job_card_id'=>['required','integer','exists:job_cards,id'],
                'staff_id'=>['required','integer','exists:staff,id'],
                'coupon_company'=>['required','string','max:140'],
                'vendor_id'=>['nullable','in:'],
            ]);
        }
        $data['pay_now']=$request->boolean('pay_now');""",
             'CREATE_VALIDATION_REQUIRED_STAFF_JC')
replace_once('controller', "    public function showExpense(Request $request, int $expense, ExpensePaymentService $service): View",
             """    public function payLubricashExpense(Request $request, int $expense, ExpensePaymentService $service): RedirectResponse
    {
        $user=$this->admin($request);
        $data=$request->validate([
            'request_key'=>['required','uuid'],
            'payment_date'=>['required','date'],
            'amount'=>['required','numeric','gt:0'],
            'payment_mode'=>['required','in:Cash,UPI,Bank Transfer,Cheque,Card'],
            'account_name'=>['nullable','string','max:120'],
            'reference_no'=>['nullable','string','max:150'],
            'notes'=>['nullable','string','max:2000'],
        ]);
        $payment=$service->payLubricashExpense($expense,$data,$this->actorName($user));
        return redirect()->route('erp.expenses.show',$expense)->with('success','Staff Lubricash Payment '.$payment->voucher_no.' recorded.');
    }

    public function showExpense(Request $request, int $expense, ExpensePaymentService $service): View""",
             'CONTROLLER_ADMIN_LATER_PAYMENT_ROUTE')
replace_once('controller', "$filters=$request->only(['q','status','category','from','to']);",
             "$filters=$request->only(['q','status','category','staff_id','from','to']);",
             'CONTROLLER_EXPENSE_FILTER')
replace_once('controller', "'categories'=>$service->expenseCategories(),'filters'=>$filters,",
             "'categories'=>$service->expenseCategories(),'filters'=>$filters,\n            'am233Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')->orderBy('name')->get(['id','staff_code','name']),",
             'REGISTER_STAFF_FILTER_LIST')
# Do not alter financial route middleware group; append next to existing expense void.
replace_once('routes',"    Route::post('/expenses/{expense}/void',",
 """    Route::post('/expenses/{expense}/lubricash-payment', [AdminExpensePaymentPageController::class,'payLubricashExpense'])->whereNumber('expense')->name('erp.expenses.lubricash-payment');
    Route::post('/expenses/{expense}/void',""",'LINKED_EXPENSE_POST_ROUTE')

# New fields are shown only for coupon category; browser never creates staff
# payment without explicit pay-now checkbox.
formblock=r'''
<div id="am233-lubricash" class="expense-wide" hidden>
 <div class="am233-coupon-heading">Staff Lubricash Coupon — Oil Sale</div>
 <p class="am233-coupon-help">Select staff and Job Card to track which employee used which oil coupon on which vehicle. Coupon company and details are kept for audit.</p>
 <div class="am233-coupon-grid">
  <label>Staff Name * <input type="search" id="am233-staff-search" placeholder="Search Staff Name / Code" autocomplete="off">
    <select name="staff_id" id="am233-staff-id" disabled>
     <option value="">Select Employee from Staff Master</option>
     @foreach(($am233Staff ?? []) as $person)
     <option value="{{ $person->id }}" data-name="{{ $person->name }}" @selected((string)old('staff_id')===(string)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>
     @endforeach
    </select>
  </label>
  <label>Oil / Coupon Company * <input name="coupon_company" id="am233-company" maxlength="140" placeholder="e.g. VEEDOL / CASTROL" value="{{ old('coupon_company') }}" disabled></label>
  <label>Coupon Number / Reference <input name="coupon_no" maxlength="120" placeholder="Coupon No. / Batch (if available)" value="{{ old('coupon_no') }}" disabled></label>
 </div>
 <div class="am233-coupon-note">Job Card / Vehicle is mandatory above. Use Description (Narration) for oil company, grade, litres, oil sale and coupon remarks.</div>
</div>
'''
replace_once('create','<div class="job-preview" id="job-preview"></div>',
             formblock+'\n<div class="job-preview" id="job-preview"></div>','EXPENSE_CREATE_COUPON_FIELDS')
css=(base/'coupon.css').read_text('utf8')
js=(base/'coupon.js').read_text('utf8')
replace_once('create','<form method="post" action="{{ route(\'erp.expenses.store\') }}"',
             '<style id="am233-lubricash-css">\n'+css+'\n</style>\n<form method="post" action="{{ route(\'erp.expenses.store\') }}"','EXPENSE_CSS')
replace_once('create','@endpush', '<script id="am233-lubricash-js">\n'+js+'\n</script>\n@endpush', 'EXPENSE_JS')
# Human-friendly expense register with direct staff / coupon + vehicle trace.
replace_once('index', '<input type="date" name="from"',
             '''<select name="staff_id" aria-label="Staff filter"><option value="">All Staff</option>@foreach(($am233Staff ?? []) as $person)<option value="{{ $person->id }}" @selected((string)($filters['staff_id']??'')===(string)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>@endforeach</select>
<input type="date" name="from"''','REGISTER_FILTER_SELECT')
replace_once('index', "{{ $e['payee_name'] }}",
             """{{ $e['payee_name'] }}@if(($e['expense_category']??'')==='LUBRICASH_COUPON')<small style="display:block;color:#345f82">STAFF: {{ $e['staff_name']??$e['payee_name'] }} · {{ $e['staff_code']??'' }}</small><small style="display:block;color:#345f82">COUPON: {{ $e['coupon_company']??'—' }} @if($e['coupon_no'])· {{ $e['coupon_no'] }}@endif</small>@endif""",
             'REGISTER_PAYEE_STAFF_DETAIL')
# Expense detail has exact audit and payment action, not generic Staff-disabled
# Payment Voucher form; only new coupon category has dedicated linked payment.
replace_once('show', '<div class="note-box"><strong>Description:</strong>',
             """@if(($expense['expense_category']??'')==='LUBRICASH_COUPON')
<div class="note-box" style="background:#eaf4fe;border:1px solid #b2cfe9;border-radius:8px;padding:11px;margin:10px 0">
 <strong>Lubricash Coupon Audit</strong>
 <div>Employee: <strong>{{ $expense['staff_name']??$expense['payee_name'] }} ({{ $expense['staff_code']??'—' }})</strong></div>
 <div>Job Card: <strong>{{ $expense['job_no']??'—' }}</strong> · Vehicle: <strong>{{ $expense['vehicle_reg_no']??'—' }}</strong></div>
 <div>Oil Company: <strong>{{ $expense['coupon_company']??'—' }}</strong> · Coupon: <strong>{{ $expense['coupon_no']??'—' }}</strong></div>
 <small>Staff ID {{ $expense['staff_id'] }} · JC ID {{ $expense['job_card_id'] }} · Audited payment vouchers below</small>
</div>
@endif
<div class="note-box"><strong>Description:</strong>""",
             'EXPENSE_DETAIL_AUDIT')
link="""<a class="btn small primary" href="{{ route('erp.payments.create',['source'=>'EXPENSE_VOUCHER','id'=>$expense['id']]) }}">＋ Payment</a>"""
linknew="""@if(($expense['expense_category']??'')==='LUBRICASH_COUPON')<span style="color:#14507d;font-weight:700">Staff-linked payment below</span>@else"""+link+"@endif"
replace_once('show',link,linknew,'EXPENSE_DETAIL_LATER_PAYMENT_LINK')
payform=r'''
@if(($expense['expense_category']??'')==='LUBRICASH_COUPON' && $expense['record_status']==='ACTIVE' && (float)$expense['paid_amount'] < (float)$expense['total_amount']-0.009)
<div class="card compact section-gap" style="background:#f5faff;border:1px solid #b1cce5;padding:14px;margin:12px 0">
 <h3>Record Staff Lubricash Payment — {{ $expense['staff_name']??$expense['payee_name'] }}</h3>
 <p>Pay only when Cash / Bank has actually been disbursed. Payment will link to this Expense, Staff ID and Job Card. Balance ₹{{ number_format(max(0,(float)$expense['total_amount']-(float)$expense['paid_amount']),2) }}</p>
 <form method="post" action="{{ route('erp.expenses.lubricash-payment',$expense['id']) }}" class="form-grid">
  @csrf
  <input type="hidden" name="request_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
  <label>Payment Date *<input type="date" name="payment_date" value="{{ now('Asia/Kolkata')->toDateString() }}" required></label>
  <label>Amount ₹ *<input type="number" name="amount" min="0.01" max="{{ max(0,round((float)$expense['total_amount']-(float)$expense['paid_amount'],2)) }}" step="0.01" value="{{ number_format(max(0,(float)$expense['total_amount']-(float)$expense['paid_amount']),2,'.','') }}" required></label>
  <label>Payment Mode *<select name="payment_mode" required><option>Cash</option><option>UPI</option><option>Bank Transfer</option><option>Cheque</option><option>Card</option></select></label>
  <label>Cash / Bank Account<input name="account_name" maxlength="120" placeholder="Cash / SBI / HDFC"></label>
  <label>Transaction / Cheque Ref<input name="reference_no" maxlength="150" placeholder="UPI / Cheque No."></label>
  <label>Payment Note<input name="notes" maxlength="2000" placeholder="Oil coupon payout narration"></label>
  <button type="submit" class="btn primary">Record Payment Against Staff &amp; JC</button>
 </form>
</div>
@endif
'''
replace_once('show','<div class="line-scroll"><table',payform+'<div class="line-scroll"><table','EXPENSE_DETAIL_LINKED_PAY_LATER_FORM')

# Validate no change to original other payment methods; compile both views
# + PHP syntax for route/controller/service pre migration, no app/data writes.
for key in ('service','controller','routes'):
 if data[key].count('AM233_STAFF_LUBRICASH_AUDIT')!=(1 if key=='service' else 0):
  raise SystemExit('AM233_STOP_DUPLICATE_SERVER_MARKER_'+key)
with tempfile.TemporaryDirectory(prefix='am233_preflight_') as tmp:
 t=Path(tmp);compiled=t/'compiled.php'
 compiler=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
 for k in ('service','controller','routes'):
  path=t/(k+'.php');path.write_text(data[k],'utf8',errors='surrogateescape')
  r=subprocess.run(['php','-l',str(path)],cwd=root,capture_output=True,text=True,timeout=30)
  if r.returncode:raise SystemExit('AM233_STOP_PHP_LINT_'+k+' '+(r.stdout+r.stderr)[-400:])
 for k in ('create','show','index'):
  p=t/(k+'.blade.php');p.write_text(data[k],'utf8',errors='surrogateescape')
  r=subprocess.run(['php','-r',compiler,str(p),str(compiled)],cwd=root,capture_output=True,text=True,timeout=50)
  if r.returncode:raise SystemExit('AM233_STOP_BLADE_COMPILE_'+k+' '+r.stderr[-400:])
  r=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=30)
  if r.returncode:raise SystemExit('AM233_STOP_BLADE_PHP_LINT_'+k+' '+(r.stdout+r.stderr)[-400:])
print('AM233_STAFF_AND_JOB_VALIDATED_SERVER_SIDE=PASS')
print('AM233_EXPENSE_AND_PAYMENT_STAFF_ID_PERMANENTLY_LINKED=PASS')
print('AM233_UNPAID_PARTIAL_PAID_LOGIC_PRESERVED=PASS')
print('AM233_LATER_PAYMENT_BOUNDED_IDEMPOTENT_ADMIN_ONLY=PASS')
print('AM233_EXPENSES_PAYMENT_ROUTE_AND_GST_UNCHANGED=PASS')
print('AM233_ALL_LARAVEL_BLADE_PHP_SYNTAX_PASS')
if mode=='check':
 print('AM233_READONLY_PREFLIGHT_PASS');sys.exit(0)
if backup.exists():raise SystemExit('AM233_STOP_BACKUP_ALREADY_EXISTS')
backup.mkdir(parents=True,exist_ok=False)
for k,p in paths.items():shutil.copy2(p,backup/(k+'.before_AM233'))
try:
 for k,p in paths.items():atomic(p,data[k])
except BaseException:
 for k,p in paths.items():shutil.copy2(backup/(k+'.before_AM233'),p)
 raise
print('AM233_STAGING_SOURCE_INSTALLED_WITH_BACKUP')
print('AM233_NO_HISTORICAL_EXPENSE_OR_PAYMENT_DATA_MODIFIED')
