#!/usr/bin/env python3
"""AM232 Staging-only STAFF LUBRICASH (Oil Sale Coupon) expense tracking.

Persistent staff_id (nullable, additive migration), linked job_card_id,
coupon company/details in Description, independent real payee, searchable
Staff and Job Card, Staff+Vehicle expense register filter, printed Voucher.
Existing GST, settlement, payment, ledger and void code untouched.
"""
from pathlib import Path
import re,sys,os,shutil,subprocess,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
paths={
 'service':root/'app/Services/ExpensePaymentService.php',
 'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'create':root/'resources/views/expenses/create.blade.php',
 'index':root/'resources/views/expenses/index.blade.php',
 'show':root/'resources/views/expenses/show.blade.php',
}
bdir=root/'storage/app/am-backups/AM232_STAFF_LUBRICASH_EXPENSE'
marker='AM232_STAFF_LUBRICASH_COUPON_TRACKING'
if not (root/'artisan').is_file() or not all(p.is_file() for p in paths.values()):
 raise SystemExit('AM232_STOP_INVALID_STAGING_SOURCE')
original={k:p.read_text('utf8',errors='surrogateescape') for k,p in paths.items()}
def atomic(p,body):
 fd,tmp=tempfile.mkstemp(prefix='.am232_',dir=p.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as file:file.write(body)
  os.chmod(tmp,p.stat().st_mode&0o777);os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
 if original['create'].count(marker)!=1 or not bdir.is_dir():
  raise SystemExit('AM232_ROLLBACK_STOP_UNEXPECTED_VIEW_OR_BACKUP')
 for key,path in paths.items():
  prior=bdir/(key+'.before_AM232')
  if not prior.is_file():raise SystemExit('AM232_ROLLBACK_MISSING_'+key)
 for key,path in paths.items():
  atomic(path,(bdir/(key+'.before_AM232')).read_text('utf8',errors='surrogateescape'))
 print('AM232_PREVIOUS_EXPENSE_SOURCES_RESTORED')
 print('AM232_ADDITIVE_NULLABLE_STAFF_ID_COLUMN_LEFT_FOR_AUDIT')
 sys.exit(0)
if marker in original['create']:
 print('AM232_ALREADY_INSTALLED');sys.exit(0)
new=dict(original)
def once(key,before,after,name):
 s=new[key];n=s.count(before)
 print('AM232_ANCHOR_'+name+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
 if n!=1:raise SystemExit('AM232_STOP_SOURCE_DRIFT_'+name)
 new[key]=s.replace(before,after,1)
# 1: category/master/report, no changes to existing categories.
once('service',
 "'REPAIR_MAINTENANCE','TAX_FEE','OTHER'];",
 "'REPAIR_MAINTENANCE','TAX_FEE','STAFF_LUBRICASH','OTHER'];",
 'SERVICE_CATEGORY')
# Persist Staff stable ID only for this new category; ensure linked JC.
guard=r'''
            // AM232: coupon used by Staff against a specific oil-sale Job Card.
            // staff_id is separate from the payee_name (actual person paid).
            $am232StaffId=(int)($data['staff_id']??0);
            if($category==='STAFF_LUBRICASH'){
                if($against!=='JOB' || $jobId<=0)
                    throw ValidationException::withMessages(['job_card_id'=>'Select a Job Card / vehicle for the Staff Lubricash coupon expense.']);
                if($am232StaffId<=0 || !DB::table('staff')->where('id',$am232StaffId)->exists())
                    throw ValidationException::withMessages(['staff_id'=>'Select a valid Staff member who used the Lubricash coupon.']);
            } else {
                $am232StaffId=0;
            }

'''
once('service',
 "if (!in_array($category,$this->expenseCategories(),true)) $category='OTHER';\n\n            $expense=WorkshopExpense",
 "if (!in_array($category,$this->expenseCategories(),true)) $category='OTHER';\n"+guard+"            $expense=WorkshopExpense",
 'SAVE_STAFF_CATEGORY_VALIDATION')
once('service',
 "'expense_category'=>$category,'expense_against'=>$against,'job_card_id'=>$jobId ?: null,'expense_reference'=>$reference ?: null,",
 "'expense_category'=>$category,'expense_against'=>$against,'job_card_id'=>$jobId ?: null,'staff_id'=>$am232StaffId ?: null,'expense_reference'=>$reference ?: null,",
 'PERSIST_STAFF_ID')
# Expense Register and printable record join actual Staff, including historic
# inactive staff. No mutation to financial amounts.
once('service',
 "->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')\n            ->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')",
 "->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')\n            ->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')\n            ->leftJoin('staff as am232s','am232s.id','=','e.staff_id')",
 'REGISTER_STAFF_JOIN')
once('service',
 "->select(['e.*','jc.job_no','jc.customer_name','jc.vehicle_reg_no','jc.vehicle_make','jc.vehicle_model','v.vendor_code','v.vendor_name']);",
 "->select(['e.*','jc.job_no','jc.customer_name','jc.vehicle_reg_no','jc.vehicle_make','jc.vehicle_model','v.vendor_code','v.vendor_name','am232s.staff_code as am232_staff_code','am232s.name as am232_staff_name']);",
 'REGISTER_STAFF_SELECT')
once('service',
 "if ($category !== '') $query->where('e.expense_category',$category);",
 "if ($category !== '') $query->where('e.expense_category',$category);\n        $staffFilter=(int)($filters['staff_id']??0);\n        if($staffFilter>0)$query->where('e.staff_id',$staffFilter);",
 'REGISTER_STAFF_FILTER')
once('service',
 "->where('e.id',$id)->select(['e.*','jc.job_no','jc.customer_name','jc.customer_phone','jc.vehicle_reg_no','jc.vehicle_make','jc.vehicle_model','v.vendor_code','v.vendor_name'])->first();",
 "->leftJoin('staff as am232s','am232s.id','=','e.staff_id')\n            ->where('e.id',$id)->select(['e.*','jc.job_no','jc.customer_name','jc.customer_phone','jc.vehicle_reg_no','jc.vehicle_make','jc.vehicle_model','v.vendor_code','v.vendor_name','am232s.staff_code as am232_staff_code','am232s.name as am232_staff_name'])->first();",
 'VOUCHER_STAFF_VIEW_JOIN')
# Controller: only read Staff names for form and filter, validate identity.
once('controller',
 "$filters=$request->only(['q','status','category','from','to']);",
 "$filters=$request->only(['q','status','category','staff_id','from','to']);",
 'REGISTER_FILTER_LIST')
once('controller',
 "'categories'=>$service->expenseCategories(),'filters'=>$filters,",
 "'categories'=>$service->expenseCategories(),'filters'=>$filters,'am232Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')->orderBy('name')->get(['id','staff_code','name']),",
 'REGISTER_STAFF_OPTIONS')
once('controller',
 "'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,",
 "'jobs'=>$service->jobs(1200),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,\n            'am232Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')->orderBy('name')->get(['id','staff_code','name']),",
 'CREATE_STAFF_OPTIONS')
once('controller',
 "'job_card_id'=>['nullable','integer','min:1'],'expense_reference'=>",
 "'job_card_id'=>['nullable','integer','min:1'],'staff_id'=>['nullable','integer','min:1'],'expense_reference'=>",
 'VALIDATE_STAFF_ID')
# Create form: one extra field, searchable Staff and Job, company in description.
once('create',
 "{{ str_replace('_',' ',$c) }}</option>@endforeach</select>",
 "{{ $c==='STAFF_LUBRICASH' ? 'STAFF LUBRICASH (Oil Sale Coupon)' : str_replace('_',' ',$c) }}</option>@endforeach</select>",
 'CATEGORY_LABEL')
once('create',
 '<label id="job-field">Job Card / Vehicle *<select',
 '<label id="job-field">Job Card / Vehicle *<input type="search" id="am232-job-search" placeholder="Type JC number or vehicle number" autocomplete="off" hidden><select',
 'JOB_CARD_SEARCH')
stafffield=r'''
{{-- AM232_STAFF_LUBRICASH_COUPON_TRACKING: selected Staff used coupon,
     not necessarily the actual Payee receiving expense payment. --}}
<label id="am232-staff-field" class="am232-staff-field" hidden>
 Staff Name / Coupon Used By *
 <input type="search" id="am232-staff-search" placeholder="Search Staff Name or Code" autocomplete="off">
 <select name="staff_id" id="am232-staff-id" disabled>
   <option value="">— Select Staff —</option>
   @foreach(($am232Staff??[]) as $person)
   <option value="{{ $person->id }}" @selected((int)old('staff_id')===(int)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>
   @endforeach
 </select>
</label>
<div id="am232-coupon-guidance" class="am232-coupon-guidance expense-wide" hidden>
 <strong>Lubricash / Oil Sale Coupon Tracking</strong>
 Select the staff who used the coupon and the Job Card / vehicle.
 Enter coupon company, coupon number, oil brand / grade and quantity in Description / Narration.
 <small>Payee Name below remains the actual person / business receiving the expense amount.</small>
</div>
'''
once('create',
 '<label id="reference-field">Expense Reference *',
 stafffield+'<label id="reference-field">Expense Reference *',
 'STAFF_SELECTOR_INSERT')
once('create',
 '<label class="expense-wide">Description *<textarea name="description"',
 '<label class="expense-wide" id="am232-description-label">Description / Narration *<textarea name="description"',
 'COUPON_NARRATION_TITLE')
# Inline CSS only for this form, searchable master fields without altering
# generic Expense Voucher geometry.
css=(Path(__file__).parent/'lubricash.css').read_text('utf8')
js=(Path(__file__).parent/'lubricash.js').read_text('utf8')
if 'AM232_LUBRICASH_EXPENSE_UI' not in js:raise SystemExit('AM232_STOP_MISSING_JS_MARKER')
once('create',
 '<form method="post" action="{{ route(\'erp.expenses.store\') }}" class="card" id="expense-form">',
 '<style id="am232-lubricash-style">'+css+'</style>\n<form method="post" action="{{ route(\'erp.expenses.store\') }}" class="card" id="expense-form">',
 'CUSTOM_CSS_INSERT')
once('create',
 '@endpush',
 '<script id="am232-lubricash-script">'+js+'</script>\n@endpush',
 'CUSTOM_JS_INSERT')
# Register staff filter and row, preserve 9 financial/action columns.
once('index',
 '<input type="date" name="from"',
 '''<select name="staff_id" id="am232-staff-filter" aria-label="Filter expenses by Staff"><option value="">All Staff</option>@foreach(($am232Staff??[]) as $person)<option value="{{ $person->id }}" @selected((int)($filters['staff_id']??0)===(int)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>@endforeach</select>
<input type="date" name="from"''',
 'REGISTER_STAFF_DROPDOWN')
once('index',
 "{{ str_replace('_',' ',$c) }}</option>@endforeach</select>",
 "{{ $c==='STAFF_LUBRICASH' ? 'STAFF LUBRICASH (Oil Sale Coupon)' : str_replace('_',' ',$c) }}</option>@endforeach</select>",
 'REGISTER_CATEGORY_LABEL')
once('index',
 "<small>{{ str_replace('_',' ',$e['expense_category']) }}</small>",
 "<small>{{ str_replace('_',' ',$e['expense_category']) }}</small>@if($e['expense_category']==='STAFF_LUBRICASH' && $e['am232_staff_name'])<br><small><strong>Coupon used by:</strong> {{ $e['am232_staff_name'] }} ({{ $e['am232_staff_code'] }})</small>@endif",
 'REGISTER_ROW_STAFF')
once('show',
 '<div><span>Created By</span>{{ $expense[\'created_by\'] ?: \'—\' }}</div>',
 '''@if($expense['expense_category']==='STAFF_LUBRICASH')
<div><span>Coupon Used By / Staff</span>{{ $expense['am232_staff_name'] ?: '—' }} @if($expense['am232_staff_code'])({{ $expense['am232_staff_code'] }})@endif</div>
@endif<div><span>Created By</span>{{ $expense['created_by'] ?: '—' }}</div>''',
 'SHOW_STAFF_SNAPSHOT')
# Safety: financial formulas, voucher issuing and amount data byte-for-byte.
for label,key,needle in [
 ('EXPENSE_PRICE_CALC','service',"$gstAmount=round($base*$gst/100,2); $total=round($base+$gstAmount,2);"),
 ('VOUCHER_NO','service',"$expense->expense_no='EXP-'"),
 ('PAYMENT_VOUCHER_CREATE','service',"'source_type'=>'EXPENSE_VOUCHER'"),
 ('ORIGINAL_JOB_CARD','create','name="job_card_id"'),
 ('ORIGINAL_PAYEE','create','name="payee_name"'),
 ('ORIGINAL_PAYMENT_NOW','create','name="pay_now"'),
 ('ORIGINAL_VOID','service',"if (WorkshopPaymentVoucher::query()"),
]:
 if original[key].count(needle)!=new[key].count(needle):
  raise SystemExit('AM232_STOP_FINANCIAL_INVARIANT_'+label)
if new['create'].count(marker)!=1:
 raise SystemExit('AM232_STOP_MARKER_COUNT')
with tempfile.TemporaryDirectory(prefix='am232_preflight_') as d:
 d=Path(d)
 boot=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
 for key,body in new.items():
  test=d/(key+'.php' if key in ('service','controller') else key+'.blade.php')
  test.write_text(body,'utf8',errors='surrogateescape')
  if key not in ('service','controller'):
   compiled=d/(key+'.compiled.php')
   c=subprocess.run(['php','-r',boot,str(test),str(compiled)],cwd=root,capture_output=True,text=True,timeout=80)
   if c.returncode:raise SystemExit('AM232_STOP_BLADE_'+key+' '+c.stderr[-320:])
   test=compiled
  c=subprocess.run(['php','-l',str(test)],cwd=root,capture_output=True,text=True,timeout=35)
  if c.returncode:raise SystemExit('AM232_STOP_PHP_'+key+' '+(c.stdout+c.stderr)[-250:])
print('AM232_CATEGORY_ADDED_AND_JOB_CARD_MANDATORY=PASS')
print('AM232_STAFF_MASTER_ID_VALIDATED_AND_STORED=PASS')
print('AM232_STAFF_AND_VEHICLE_REGISTER_FILTER=PASS')
print('AM232_COUPON_COMPANY_AND_OIL_NARRATION_AVAILABLE=PASS')
print('AM232_ORIGINAL_ACCOUNTING_GST_PAYMENT_VOID_UNCHANGED=PASS')
print('AM232_BLADE_PHP_PREFLIGHT_PASS')
if mode=='check':
 print('AM232_CHECK_READONLY_PASS');sys.exit(0)
if bdir.exists():
 raise SystemExit('AM232_STOP_BACKUP_ALREADY_EXISTS')
bdir.mkdir(parents=True,exist_ok=False)
for key,path in paths.items():shutil.copy2(path,bdir/(key+'.before_AM232'))
try:
 for key,path in paths.items():atomic(path,new[key])
except BaseException:
 for key,path in paths.items():shutil.copy2(bdir/(key+'.before_AM232'),path)
 raise
print('AM232_EXPENSE_STAFF_LUBRICASH_STAGING_SOURCE_INSTALLED')
print('AM232_EXISTING_DATA_NOT_MUTATED')
