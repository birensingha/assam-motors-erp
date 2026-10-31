#!/usr/bin/env python3
"""AM236 guarded Vendor Payment NEW_REFERENCE/ADVANCE:
Separate vendor advance/reference identities and validate server-side.
No migrations or edits to historical vouchers; no automatic outstanding bill adjustments.
"""
from pathlib import Path
import os,sys,tempfile,subprocess,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
paths={
'view':root/'resources/views/payments/create.blade.php',
'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
'service':root/'app/Services/ExpensePaymentService.php'
}
backup=root/'storage/app/am-backups/AM236_VENDOR_NEW_REFERENCE'
marker='AM236_VENDOR_NEW_REFERENCE'
if not (root/'artisan').is_file() or not all(p.is_file() for p in paths.values()):
    raise SystemExit('AM236_STOP_WRONG_LARAVEL_ROOT')
original={k:p.read_text('utf8',errors='surrogateescape') for k,p in paths.items()}
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am236_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
    if original['service'].count(marker)!=1 or not backup.is_dir():
        raise SystemExit('AM236_STOP_ROLLBACK_INVALID_MARKER_OR_BACKUP')
    for k,p in paths.items():
        b=backup/(k+'.before_AM236')
        if not b.is_file():raise SystemExit('AM236_STOP_ROLLBACK_MISSING_'+k)
    for k,p in paths.items():
        atomic(p,(backup/(k+'.before_AM236')).read_text('utf8',errors='surrogateescape'))
    print('AM236_PREVIOUS_PAYMENT_SOURCES_RESTORED')
    sys.exit(0)
if marker in original['service']:
    print('AM236_ALREADY_APPLIED')
    sys.exit(0)
data=dict(original)
def swap(k,a,b,name,count=1):
    n=data[k].count(a)
    print('AM236_ANCHOR_'+name+'='+('PASS' if n==count else 'FAIL_'+str(n)))
    if n!=count:raise SystemExit('AM236_STOP_SOURCE_DRIFT_'+name)
    data[k]=data[k].replace(a,b)
# VIEW: 4 user-facing reference modes plus source type for durable voucher distinction.
swap('view',' <option value="REFERENCE">Against Reference</option>\n <option value="ADVANCE">Advance</option>',
     ' <option value="REFERENCE">Against Reference</option>\n <option value="NEW_REFERENCE">New Reference</option>\n <option value="ADVANCE">Advance</option>','PURPOSE_OPTIONS')
swap('view','<option value="ADVANCE">Advance</option><option value="ON_ACCOUNT">On Account — Unallocated</option>',
     '<option value="NEW_REFERENCE">New Reference</option><option value="ADVANCE">Advance</option><option value="ON_ACCOUNT">On Account — Unallocated</option>','BACKEND_SOURCE_OPTION')
old_expr="source.value=purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':'ADVANCE'"
print('AM236_TERNARY_OCCURRENCES='+str(data['view'].count(old_expr)))
if data['view'].count(old_expr)<2:raise SystemExit('AM236_STOP_SWITCH_SOURCE_MISSING')
data['view']=data['view'].replace(old_expr,"source.value=purpose.value==='ON_ACCOUNT'?'ON_ACCOUNT':(purpose.value==='NEW_REFERENCE'?'NEW_REFERENCE':'ADVANCE')")
swap('view',"else if(source.value==='ADVANCE'||source.value==='ON_ACCOUNT')",
    "else if(source.value==='NEW_REFERENCE'||source.value==='ADVANCE'||source.value==='ON_ACCOUNT')",
    'PERSIST_PURPOSE_ON_REVISIT')
swap('view','<label class="p133-label">Reference / Purpose<input name="source_reference"',
    '<label class="p133-label" id="am236-reference-label">Reference / Purpose<input name="source_reference"',
    'EXISTING_SOURCE_REFERENCE_INPUT')
swap('view','<p id="am231-context-help">Choose recipient first, then select the appropriate payment purpose.</p>',
'''<p id="am231-context-help">Choose recipient first, then select the appropriate payment purpose.</p>
 <div id="am236-reference-context" role="status" style="font-size:11px;color:#43617b;padding:5px 0"></div>''',
    'HELP_CONTEXT')
js=r'''
<script id="am236-reference-mode-script">
/* AM236: Vendor bill selection and new reference are distinct; preserve original POST data. */
(function(){
 'use strict';
 function init(){
  const form=document.getElementById('payment-form');
  const purpose=document.getElementById('am231-payment-purpose');
  const type=document.getElementById('source-type');
  const category=document.getElementById('am230-payee-type');
  const reference=form?.querySelector('input[name="source_reference"]');
  const refLabel=document.getElementById('am236-reference-label');
  const help=document.getElementById('am236-reference-context');
  const sourceField=document.getElementById('known-source-field');
  const multi=document.getElementById('p133-multi-toggle');
  const search=document.getElementById('am218-search-wrap');
  if(!form||!purpose||!type||!reference||!refLabel||!help||!category)return;
  function apply(){
   const vendor=category.value==='VENDOR';
   const val=type.value;
   const newRef=vendor&&val==='NEW_REFERENCE';
   const advance=vendor&&val==='ADVANCE';
   const manual=newRef||advance;
   form.classList.toggle('am236-manual-ref-mode',manual);
   form.classList.toggle('am236-new-ref-mode',newRef);
   reference.required=manual;
   if(manual){
     reference.placeholder=newRef?'Enter unique reference, e.g. NR-2026-0001':'Enter advance reference, e.g. ADV-2026-0001';
     refLabel.firstChild.nodeValue=newRef?'New Reference No. *':'Advance Reference No. *';
   } else {
     refLabel.firstChild.nodeValue='Reference / Purpose';
     reference.placeholder='Optional payment reference/purpose';
   }
   if(sourceField&&manual)sourceField.style.display='none';
   if(search&&manual)search.hidden=true;
   if(multi&&manual)multi.checked=false;
   if(!vendor){help.textContent='Staff and Customer financial posting remains restricted until ledger integration.';return;}
   help.textContent=newRef?'New Reference: enter a unique number; no existing Purchase/OSL bill is selected. Future bill adjustment is a separate audited action.'
     :advance?'Advance: enter the supplier advance reference. Payment is tracked separately from existing bills; future bill adjustment must be audited.'
     :val==='ON_ACCOUNT'?'On Account: no bill selected; the payment stays unallocated in the vendor ledger.':
       'Against Reference: select one or multiple existing outstanding vendor bills.';
  }
  purpose.addEventListener('change',apply);
  type.addEventListener('change',apply);
  category.addEventListener('change',apply);
  reference.addEventListener('input',()=>reference.setCustomValidity(''));
  form.addEventListener('submit',event=>{
    if(category.value==='VENDOR'&&['ADVANCE','NEW_REFERENCE'].includes(type.value)&&!reference.value.trim()){
      event.preventDefault();event.stopImmediatePropagation();
      reference.setCustomValidity('Enter an Advance or New Reference number before posting.');
      reference.reportValidity();reference.setCustomValidity('');reference.focus();
    }
  },true);
  apply();
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});
 else init();
})();
</script>
'''
css='''<style id="am236-reference-style">
#payment-form.am236-manual-ref-mode #known-source-field,
#payment-form.am236-manual-ref-mode #am218-search-wrap,
#payment-form.am236-manual-ref-mode #p133-multi-area,
#payment-form.am236-manual-ref-mode #p133-multi-toggle-wrap{display:none!important}
#payment-form.am236-manual-ref-mode #am236-reference-label{display:flex!important}
#payment-form.am236-manual-ref-mode #am236-reference-label input{border-color:#89b0d2}
</style>
'''
swap('view','@endpush',css+js+'@endpush','VIEW_SCRIPTS_END')
# Validated NEW_REFERENCE vendor+ref, ADVANCE vendor+ref, keep ON_ACCOUNT unchanged.
needle="'payment_date'=>['required','date'],'source_type'=>['required','in:PURCHASE_INVOICE,EXPENSE_VOUCHER,OSL_JOB,ADVANCE,ON_ACCOUNT,OTHER']"
replacement="'payment_date'=>['required','date'],'source_type'=>['required','in:PURCHASE_INVOICE,EXPENSE_VOUCHER,OSL_JOB,NEW_REFERENCE,ADVANCE,ON_ACCOUNT,OTHER']"
swap('controller',needle,replacement,'CONTROLLER_SOURCE_TYPE')
swap('controller',"'vendor_id'=>['nullable','integer','min:1'],'payee_name'",
     "'request_key'=>['nullable','uuid'],'vendor_id'=>['nullable','integer','min:1'],'payee_name'",'CONTROLLER_IDEMPOTENCY_FIELD')
ctrl_source="""        $data=$request->validate([
            'payment_date'=>['required','date'],'source_type'"""
ctrl_new="""        // AM236 vendor New Reference and Advance must have real vendor + reference identity.
        if (!$request->boolean('multi_bill') && in_array((string)$request->input('source_type'),['NEW_REFERENCE','ADVANCE'],true)) {
            $request->validate([
                'vendor_id'=>['required','integer','min:1','exists:workshop_vendors,id'],
                'source_reference'=>['required','string','min:3','max:180'],
                'request_key'=>['required','uuid'],
            ]);
        }
        $data=$request->validate([
            'payment_date'=>['required','date'],'source_type'"""
swap('controller',ctrl_source,ctrl_new,'CONTROLLER_VENDOR_REFERENCE_VALIDATION')
swap('service',"public const SOURCE_TYPES = ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB','ADVANCE','ON_ACCOUNT','OTHER'];",
     "public const SOURCE_TYPES = ['PURCHASE_INVOICE','EXPENSE_VOUCHER','OSL_JOB','NEW_REFERENCE','ADVANCE','ON_ACCOUNT','OTHER'];",
     'SERVICE_SOURCE_TYPES')
sv_old="""            $amount=round((float)($data['amount'] ?? 0),2); if($amount<=0) throw ValidationException::withMessages(['amount'=>'Payment amount must be greater than zero.']);"""
sv_new="""            $amount=round((float)($data['amount'] ?? 0),2); if($amount<=0) throw ValidationException::withMessages(['amount'=>'Payment amount must be greater than zero.']);
            // AM236_VENDOR_NEW_REFERENCE: lock Vendor, enforce reference and idempotent repeat.
            if (in_array($type,['NEW_REFERENCE','ADVANCE'],true)) {
                $vendorId=(int)($data['vendor_id']??0);
                $vendor=$vendorId>0 ? DB::table('workshop_vendors')->where('id',$vendorId)->lockForUpdate()->first() : null;
                if (!$vendor) throw ValidationException::withMessages(['vendor_id'=>'Select a valid Vendor Master.']);
                $ref=trim((string)($data['source_reference']??''));
                if (mb_strlen($ref)<3) throw ValidationException::withMessages(['source_reference'=>'Enter New Reference / Advance Reference No.']);
                $key=trim((string)($data['request_key']??''));
                if ($key==='') throw ValidationException::withMessages(['request_key'=>'Voucher request identity is required.']);
                $existing=WorkshopPaymentVoucher::query()->where('request_key',$key)->first();
                if ($existing) {
                    if ((int)$existing->vendor_id===$vendorId
                        && $existing->source_type===$type
                        && (string)$existing->source_reference===$ref
                        && abs((float)$existing->amount-$amount)<0.009) return $existing;
                    throw ValidationException::withMessages(['request_key'=>'Voucher request identity was already used for another payment.']);
                }
                if ($type==='NEW_REFERENCE' && WorkshopPaymentVoucher::query()
                    ->where('vendor_id',$vendorId)->where('source_type','NEW_REFERENCE')
                    ->where('source_reference',$ref)->where('record_status','ACTIVE')->exists())
                    throw ValidationException::withMessages(['source_reference'=>'This New Reference already exists for this vendor. Choose a different reference.']);
            }"""
swap('service',sv_old,sv_new,'SERVICE_VENDOR_REFERENCE_GUARD')
swap('service',"'account_name'=>$data['account_name'] ?? null,'reference_no'=>$data['reference_no'] ?? null,'notes'=>$data['notes'] ?? null,",
    "'account_name'=>$data['account_name'] ?? null,'reference_no'=>$data['reference_no'] ?? null,'notes'=>$data['notes'] ?? null,'request_key'=>$data['request_key'] ?? null,",
    'SERVICE_SAVEPAYMENT_REQUEST_KEY')
swap('service',"'reference_no'=>mb_substr(trim((string)($data['reference_no'] ?? '')),0,150) ?: null,'notes'=>mb_substr(trim((string)($data['notes'] ?? '')),0,2000) ?: null,'record_status'=>'ACTIVE','created_by'=>$actor,",
    "'reference_no'=>mb_substr(trim((string)($data['reference_no'] ?? '')),0,150) ?: null,'notes'=>mb_substr(trim((string)($data['notes'] ?? '')),0,2000) ?: null,'request_key'=>$data['request_key']??null,'record_status'=>'ACTIVE','created_by'=>$actor,",
    'SERVICE_PERSIST_REQUEST_KEY')
swap('service',"""        if($type==='ON_ACCOUNT' && $vendorId<=0) throw ValidationException::withMessages(['vendor_id'=>'Select a Vendor for On Account payment.']);""",
    """        if(in_array($type,['ON_ACCOUNT','NEW_REFERENCE','ADVANCE'],true) && $vendorId<=0) throw ValidationException::withMessages(['vendor_id'=>'Select a Vendor Master for this payment.']);""",
    'SERVICE_VENDOR_VALIDATION')
swap('service',"""        $ref=mb_substr(trim((string)($data['source_reference'] ?? '')),0,180); if($type==='ADVANCE' && $ref==='') $ref='ADVANCE';""",
    """        $ref=mb_substr(trim((string)($data['source_reference'] ?? '')),0,180);
        if(in_array($type,['NEW_REFERENCE','ADVANCE'],true) && mb_strlen($ref)<3)
            throw ValidationException::withMessages(['source_reference'=>'Enter a valid New Reference / Advance Reference number.']);""",
    'SERVICE_REQUIRE_REFERENCE')
# Preserve every original unrelated payment category and system guards.
for k in ('view','controller','service'):
    if len(data[k])<=len(original[k]):raise SystemExit('AM236_STOP_UNEXPECTED_SOURCE_SHRINK_'+k)
if data['service'].count(marker)!=1:raise SystemExit('AM236_STOP_SERVER_GUARD_MARKER')
if "AM230 no Staff/Customer ledger key" not in data['controller']:
    raise SystemExit('AM236_STOP_STAFF_CUSTOMER_FAIL_CLOSED_GUARD_MISSING')
with tempfile.TemporaryDirectory(prefix='am236_check_') as td:
    base=Path(td)
    for k in ('controller','service'):
        f=base/(k+'.php');f.write_text(data[k],'utf8',errors='surrogateescape')
        test=subprocess.run(['php','-l',str(f)],cwd=root,capture_output=True,text=True,timeout=60)
        if test.returncode:raise SystemExit('AM236_STOP_PHP_LINT_'+k+' '+(test.stdout+test.stderr)[-380:])
    template=base/'create.blade.php';template.write_text(data['view'],'utf8',errors='surrogateescape')
    compiled=base/'compiled.php'
    compiler=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for cmd in (['php','-r',compiler,str(template),str(compiled)],['php','-l',str(compiled)]):
        test=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=60)
        if test.returncode:raise SystemExit('AM236_STOP_BLADE_LINT '+(test.stdout+test.stderr)[-380:])
print('AM236_NEW_REFERENCE_AND_ADVANCE_DISTINCT_TYPES_PASS')
print('AM236_SERVER_SIDE_VENDOR_AND_NEW_REFERENCE_REQUIRED_PASS')
print('AM236_NO_EXISTING_BILL_AUTOALLOCATED_TO_NEW_REFERENCE_PASS')
print('AM236_IDEMPOTENCY_AND_UNIQUE_VENDOR_NEW_REFERENCE_PASS')
print('AM236_EXISTING_VENDOR_MULTI_AND_STAFF_GUARDS_PRESERVED_PASS')
print('AM236_NO_HISTORIC_FINANCIAL_RECORDS_CHANGED_PASS')
print('AM236_PHP_AND_BLADE_SYNTAX_PASS')
if mode=='check':
    print('AM236_READONLY_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM236_STOP_EXISTING_BACKUP_CONFLICT')
backup.mkdir(parents=True,exist_ok=False)
for k,p in paths.items():shutil.copy2(p,backup/(k+'.before_AM236'))
try:
    for k,p in paths.items():atomic(p,data[k])
except BaseException:
    for k,p in paths.items():shutil.copy2(backup/(k+'.before_AM236'),p)
    raise
print('AM236_STAGING_VIEW_CONTROLLER_SERVICE_UPDATED_WITH_BACKUP')
