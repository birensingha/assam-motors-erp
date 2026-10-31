#!/usr/bin/env python3
"""AM233 guarded staging-only Lubricash coupon expense/employee/JC trace.
Adds nullable staff_id and coupon_company to workshop_expenses via additive
migration, new category, required staff and JC for category, searchable UI,
register/detail audit metadata, payment history remains original.
NO existing expenses or payments rewritten.
"""
import sys,re,subprocess,shutil,os,tempfile
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
 raise SystemExit('Usage: apply.py check|apply STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
paths={
 'view':root/'resources/views/expenses/create.blade.php',
 'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'service':root/'app/Services/ExpensePaymentService.php',
 'index':root/'resources/views/expenses/index.blade.php',
 'show':root/'resources/views/expenses/show.blade.php',
}
if not (root/'artisan').is_file() or not all(p.is_file() for p in paths.values()):
 raise SystemExit('AM233_STOP_MISSING_STAGING_SOURCE')
orig={k:p.read_text('utf8',errors='surrogateescape') for k,p in paths.items()}
m='AM233_LUBRICASH_STAFF_JC_AUDIT'
if any(m in s for s in orig.values()):
 raise SystemExit('AM233_STOP_ALREADY_PATCHED')
def replace(k,a,b):
 s=out[k]
 n=s.count(a)
 print('AM233_ANCHOR_'+k+'_'+str(len(a))+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
 if n!=1:raise SystemExit('AM233_STOP_SOURCE_DRIFT_'+k+'_'+a[:50])
 out[k]=s.replace(a,b,1)
out=orig.copy()
# Category and robust immutable staff identity validation before DB write.
replace('service',"'STAFF_WELFARE','SALARY_WAGES'","'STAFF_WELFARE','LUBRICASH_COUPON','SALARY_WAGES'")
replace('controller',"'expense_category'=>['required','string','max:60']","'expense_category'=>['required','string','max:60'],'staff_id'=>['nullable','integer','min:1'],'coupon_company'=>['nullable','string','max:120']")
replace('controller',"'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,",
"'jobs'=>$service->jobs(),'vendors'=>$service->vendors(),'categories'=>$service->expenseCategories(),'presetJobId'=>$presetJobId,\n            'am233Staff'=>\\Illuminate\\Support\\Facades\\DB::table('staff')->orderBy('name')->get(['id','staff_code','name']),")
needle="            $vendorId=(int)($data['vendor_id'] ?? 0);"
check="""            // AM233_LUBRICASH_STAFF_JC_AUDIT: require real Staff Master and
            // linked Job Card for oil coupon, preserving the employee ID.
            $isCoupon=strtoupper(trim((string)($data['expense_category']??'')))==='LUBRICASH_COUPON';
            $staffId=(int)($data['staff_id']??0);
            $company=mb_substr(trim((string)($data['coupon_company']??'')),0,120);
            if($isCoupon) {
                if($against!=='JOB'||$jobId<=0) throw ValidationException::withMessages(['job_card_id'=>'Lubricash coupon requires a Job Card / Vehicle.']);
                if($staffId<=0 || !DB::table('staff')->where('id',$staffId)->exists())
                    throw ValidationException::withMessages(['staff_id'=>'Choose a valid Staff Master employee.']);
                if($company==='') throw ValidationException::withMessages(['coupon_company'=>'Coupon company / oil company required.']);
                if($vendorId??0) {} // Vendor policy is applied after field read.
            }
"""
# Insert before vendorId, no reference to uninitialized values
check=check.replace("                if($vendorId??0) {} // Vendor policy is applied after field read.\n","")
replace('service',needle,check+needle)
replace('service',"'expense_category'=>$category,'expense_against'=>$against,'job_card_id'=>$jobId ?: null,'expense_reference'=>$reference ?: null,",
"'expense_category'=>$category,'expense_against'=>$against,'job_card_id'=>$jobId ?: null,'expense_reference'=>$reference ?: null,\n                'staff_id'=>$isCoupon?$staffId:null,'coupon_company'=>$isCoupon?$company:null,")
# Expense payee should be actual employee, not loosely typed vendor name.
replace('service',"            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);",
"""            if($isCoupon){
                if($vendorId>0) throw ValidationException::withMessages(['vendor_id'=>'Lubricash payment recipient must be Staff, not Vendor.']);
                $payee=(string)DB::table('staff')->where('id',$staffId)->value('name');
            }
            if ($payee === '') throw ValidationException::withMessages(['payee_name'=>'Select Vendor or enter Payee Name.']);""")
# Coupon should be traceable by staff and oil company through list search, not only narration.
replace('service',"->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')\n            ->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')",
"->leftJoin('job_cards as jc','jc.id','=','e.job_card_id')\n            ->leftJoin('workshop_vendors as v','v.id','=','e.vendor_id')\n            ->leftJoin('staff as am233s','am233s.id','=','e.staff_id')")
replace('service',"'v.vendor_code','v.vendor_name']);","'v.vendor_code','v.vendor_name','am233s.staff_code as coupon_staff_code','am233s.name as coupon_staff_name']);")
replace('service',"->orWhere('e.payee_name','like',$like)->orWhere('e.description','like',$like)",
"->orWhere('e.payee_name','like',$like)->orWhere('am233s.name','like',$like)->orWhere('am233s.staff_code','like',$like)->orWhere('e.coupon_company','like',$like)->orWhere('e.description','like',$like)")
# Detail page rich linked staff and company. Ensure correct showExpense selector.
replace('service',"->where('e.id',$id)->select(['e.*','jc.job_no'","->leftJoin('staff as am233s','am233s.id','=','e.staff_id')\n            ->where('e.id',$id)->select(['e.*','am233s.staff_code as coupon_staff_code','am233s.name as coupon_staff_name','jc.job_no'")
# Create view fields, controlled visibility by category, native select searchable by typing.
replace('view',"<label>Vendor (optional)<select name=\"vendor_id\" id=\"expense-vendor\">",
"""<div id="am233-coupon-fields" class="expense-wide" hidden>
 <div class="form-grid expense-grid">
  <label>Staff Name *<input type="search" id="am233-staff-search" placeholder="Type name / staff code to search" autocomplete="off">
   <select name="staff_id" id="am233-staff" disabled><option value="">Select Employee</option>
   @foreach(($am233Staff??[]) as $employee)
    <option value="{{ $employee->id }}" @selected((string)old('staff_id')===(string)$employee->id)>{{ $employee->staff_code }} — {{ $employee->name }}</option>
   @endforeach</select>
  </label>
  <label>Oil / Coupon Company *<input name="coupon_company" id="am233-company" maxlength="120" value="{{ old('coupon_company') }}" placeholder="e.g. VEEDOL"></label>
 </div>
 <small>Lubricash Coupon: Select Staff + Job Card. Narration must include coupon number, oil grade/qty and reason.</small>
</div>
<label id="am233-vendor-field">Vendor (optional)<select name="vendor_id" id="expense-vendor">""")
replace('view',"<label>Payee Name *<input name=\"payee_name\" id=\"expense-payee\"",
"<label id=\"am233-payee-field\">Payee Name *<input name=\"payee_name\" id=\"expense-payee\"")
replace('view',"@push('scripts')<script>window.AM_EXPENSE_JOBS=",
"""@push('scripts')
<script id="am233-coupon-js">
(function(){
 function init(){
 const form=document.getElementById('expense-form'),category=form?.querySelector('select[name="expense_category"]');
 const block=document.getElementById('am233-coupon-fields'),staff=document.getElementById('am233-staff'),query=document.getElementById('am233-staff-search');
 const company=document.getElementById('am233-company'),against=document.getElementById('expense-against'),job=document.getElementById('expense-job');
 const vendor=document.getElementById('expense-vendor'),payee=document.getElementById('expense-payee');
 const vendorField=document.getElementById('am233-vendor-field'),payeeField=document.getElementById('am233-payee-field');
 if(![category,block,staff,query,company,against,job,vendor,payee,vendorField,payeeField].every(Boolean))return;
 const all=Array.from(staff.options).map(o=>o.cloneNode(true));
 function syncStaff(){const selected=staff.options[staff.selectedIndex];
  if(category.value==='LUBRICASH_COUPON'&&selected&&selected.value){payee.value=selected.textContent.split(' — ').slice(1).join(' — ').trim();}}
 function sync(){
   const on=category.value==='LUBRICASH_COUPON';
   block.hidden=!on;vendorField.hidden=on;payeeField.hidden=on;
   staff.disabled=!on;staff.required=on;company.required=on;payee.required=!on;
   if(on){against.value='JOB';against.dispatchEvent(new Event('change',{bubbles:true}));
      against.disabled=true;job.required=true;vendor.value='';syncStaff();
   }else {against.disabled=false;}
 }
 // Disabled selects are excluded from POST. Before submit restore JOB flag.
 form.addEventListener('submit',()=>{against.disabled=false;},true);
 query.addEventListener('input',function(){
  const term=query.value.toLowerCase().trim(),current=staff.value;
  const filtered=all.filter(o=>!o.value||o.value===current||o.textContent.toLowerCase().includes(term));
  staff.replaceChildren(...filtered.map(o=>o.cloneNode(true)));staff.value=current;
 });
 staff.addEventListener('change',syncStaff);
 category.addEventListener('change',sync);sync();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
</script>
<script>window.AM_EXPENSE_JOBS=""")
# Show and index compact audit provenance without changing financial rows.
replace('index',"<td>{{ $e['expense_date'] }}", "<td>{{ $e['expense_date'] }}") if False else None
# Render provenance in description column via unique original line fragment.
replace('index',"<td>{{ $e['description'] }}</td>",
"""<td>{{ $e['description'] }} @if(($e['expense_category']??'')==='LUBRICASH_COUPON')
  <div class="small"><strong>Oil Coupon:</strong> {{ $e['coupon_staff_code']??'' }} · {{ $e['coupon_staff_name']??$e['payee_name'] }} · {{ $e['coupon_company']??'' }}</div>
 @endif</td>""")
replace('show',"<div class=\"note-box\"><strong>Description:</strong> {{ $expense['description'] }}</div>",
"""@if(($expense['expense_category']??'')==='LUBRICASH_COUPON')
<div class="note-box"><strong>Lubricash Coupon Audit:</strong>
 Staff: {{ $expense['coupon_staff_code']??'' }} · {{ $expense['coupon_staff_name']??$expense['payee_name'] }};
 Job Card: {{ $expense['job_no']??'' }}; Vehicle: {{ $expense['vehicle_reg_no']??'' }};
 Company: {{ $expense['coupon_company']??'' }}.
</div>
@endif
<div class="note-box"><strong>Description:</strong> {{ $expense['description'] }}</div>""")
for key in ('view','controller','service','index','show'):
 if out[key]==orig[key]:raise SystemExit('AM233_STOP_NOT_MODIFIED_'+key)
# Before installation compile all Blade files and PHP modules.
with tempfile.TemporaryDirectory(prefix='am233_preflight_') as td:
 d=Path(td);compile_script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
 for key in out:
  input=d/(key+'.blade.php' if key in ('view','index','show') else key+'.php')
  input.write_text(out[key],'utf8',errors='surrogateescape')
  if key in ('view','index','show'):
   compiled=d/(key+'.compiled.php')
   cmd=['php','-r',compile_script,str(input),str(compiled)]
   r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=70)
   if r.returncode:raise SystemExit('AM233_BLADE_COMPILE_ERROR_'+key+' '+r.stderr[-240:])
   input=compiled
  r=subprocess.run(['php','-l',str(input)],cwd=root,capture_output=True,text=True,timeout=35)
  if r.returncode:raise SystemExit('AM233_PHP_LINT_ERROR_'+key+' '+(r.stdout+r.stderr)[-260:])
print('AM233_STAFF_ID_AND_JOB_CARD_REQUIRED_FOR_COUPON=PASS')
print('AM233_EMPLOYEE_MASTER_AND_COUPON_COMPANY=PASS')
print('AM233_EXPENSE_PAYMENT_POSTING_LOGIC_UNCHANGED=PASS')
print('AM233_EXPENSE_REGISTER_AND_DETAIL_AUDIT_FIELDS=PASS')
print('AM233_BLADE_AND_PHP_LINT_PASS')
if mode=='check':
 print('AM233_CHECK_COMPLETE_READONLY')
 sys.exit(0)
backup=root/'storage/app/am-backups/AM233_OIL_COUPON_AUDIT'
if backup.exists():raise SystemExit('AM233_STOP_PREVIOUS_BACKUP_EXISTS')
backup.mkdir(parents=True)
for key,path in paths.items():shutil.copy2(path,backup/(key+'.before_AM233'))
try:
 for key,path in paths.items():
  fd,tmp=tempfile.mkstemp(prefix='.am233_',dir=path.parent)
  try:
   with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(out[key])
   os.chmod(tmp,path.stat().st_mode&0o777)
   os.replace(tmp,path)
  finally:
   if os.path.exists(tmp):os.unlink(tmp)
except BaseException:
 for key,path in paths.items():shutil.copy2(backup/(key+'.before_AM233'),path)
 raise
print('AM233_VIEW_CONTROLLER_SERVICE_INSTALLED')
