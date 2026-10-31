#!/usr/bin/env python3
"""AM238 *staging only*, source-compatible Vendor Debit accounting patch.
check: no writes to ERP, creates temporary candidate files and validates.
apply: backed-up atomic application; financial DB migrate separately, only after QA.
rollback: restore backed up source (NOT financial posting data).
"""
from pathlib import Path
import sys,os,tempfile,subprocess,shutil
if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve();assets=Path(__file__).resolve().parent
paths={
 'multi':root/'app/Services/MultiPurchasePaymentService.php',
 'service':root/'app/Services/ExpensePaymentService.php',
 'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php',
 'view':root/'resources/views/payments/create.blade.php',
 'show':root/'resources/views/payments/show.blade.php',
 'debit_service':root/'app/Services/VendorDebitSettlementService.php',
 'migration':root/'database/migrations/2026_11_01_001200_create_workshop_vendor_debit_uses.php',
}
backup=root/'storage/app/am-backups/AM238_VENDOR_DEBIT_SETTLEMENT'
marker='AM238_VENDOR_DEBIT_SETTLEMENT'
required=[root/'artisan',paths['multi'],paths['service'],paths['controller'],paths['view'],paths['show']]
required.extend([assets/'VendorDebitSettlementService.php',assets/'2026_11_01_001200_create_workshop_vendor_debit_uses.php',
                 assets/'vendor-debit-ui.js',assets/'vendor-debit-ui.css'])
if not all(p.is_file() for p in required):raise SystemExit('AM238_STOP_STAGING_SOURCE_OR_ASSET_MISSING')
existing={k:(p.read_text('utf8',errors='surrogateescape') if p.is_file() else None) for k,p in paths.items()}
if mode=='rollback':
    if not backup.is_dir() or not existing['multi'] or marker not in existing['multi']:
        raise SystemExit('AM238_STOP_ROLLBACK_INVALID')
    if __import__('subprocess').run(['php','artisan','migrate:status'],cwd=root,capture_output=True).returncode:
        raise SystemExit('AM238_STOP_CANNOT_VERIFY_MIGRATION')
    # Do NOT delete the debit-use table if any posted rows exist. Source restore
    # while active debit allocations exist would orphan posted money flows.
    guard=r"""require 'vendor/autoload.php'; $app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(\Illuminate\Support\Facades\Schema::hasTable('workshop_vendor_debit_uses') &&
\Illuminate\Support\Facades\DB::table('workshop_vendor_debit_uses')->exists())exit(2);
exit(0);"""
    safe=subprocess.run(['php','-r',guard],cwd=root,timeout=30)
    if safe.returncode:raise SystemExit('AM238_STOP_POSTED_DEBIT_USES_REQUIRE_AUDITED_REVERSAL')
    for k in paths:
        original=backup/(k+'.before_AM238')
        if not original.is_file():raise SystemExit('AM238_STOP_INCOMPLETE_BACKUP_'+k)
    for k,p in paths.items():
        source=backup/(k+'.before_AM238')
        if source.read_text('utf8')=='__AM238_FILE_NOT_PRESENT__':
            if p.is_file():p.unlink()
        else:
            p.parent.mkdir(parents=True,exist_ok=True)
            shutil.copy2(source,p)
    print('AM238_PREVIOUS_PAYMENT_SOURCE_RESTORED')
    sys.exit(0)
if existing['multi'] and marker in existing['multi']:
    print('AM238_ALREADY_APPLIED');sys.exit(0)
if existing['debit_service'] or existing['migration']:
    raise SystemExit('AM238_STOP_DESTINATION_SERVICE_OR_MIGRATION_EXISTS')
code={k:v for k,v in existing.items() if v is not None}
def replace(key,a,b,label,count=1):
    n=code[key].count(a)
    print('AM238_ANCHOR_'+label+'='+('PASS' if n==count else 'FAIL_'+str(n)))
    if n!=count:raise SystemExit('AM238_STOP_SOURCE_DRIFT_'+label)
    code[key]=code[key].replace(a,b,count)
# ----- MultiPurchasePaymentService -----
replace('multi',
    "        $total=round(array_sum($alloc),2);\n        if ($total<=0 || abs($total-(float)($data['amount']??0))>0.01) {\n            $this->reject('Payment Amount must equal sum of bill allocations.');\n        }",
    """        // AM238_VENDOR_DEBIT_SETTLEMENT: cash + prior unspent debit = bill settlement + new On Account.
        $debitService=app(\\App\\Services\\VendorDebitSettlementService::class);
        $debits=$debitService->parseAllocations($data['vendor_debits']??[]);
        $total=round(array_sum($alloc),2);
        $cash=round((float)($data['amount']??0),2);
        $creditCents=array_sum($debits);
        $cashCents=(int)round($cash*100);
        $billCents=(int)round($total*100);
        if($cashCents<0 || $cashCents>9999999999 || $billCents<=0 ||
            $creditCents>$billCents || ($cashCents+$creditCents)<$billCents) {
            $this->reject('Cash + selected prior Debit must cover bill allocations. Remaining cash becomes On Account.');
        }""",'MULTI_TOTAL_INVARIANT')
replace('multi','function()use($key,$alloc,$total,$data,$actor,$paymentMode){',
              'function()use($key,$alloc,$total,$cash,$debits,$debitService,$data,$actor,$paymentMode){',
              'MULTI_TRANSACTION_CLOSURE')
replace('multi','abs((float)$existing->amount-$total)<0.01','abs((float)$existing->amount-$cash)<0.01',
              'MULTI_IDEMPOTENT_CASH_AMOUNT')
replace('multi',"$vendor=DB::table('workshop_vendors')->where('id',$vendorId)->lockForUpdate()->first();\n            if(!$vendor)$this->reject('Vendor no longer exists.');",
"""$vendor=DB::table('workshop_vendors')->where('id',$vendorId)->lockForUpdate()->first();
            if(!$vendor)$this->reject('Vendor no longer exists.');
            $debitService->lockAndValidate($vendorId,$debits);""",'MULTI_LOCK_CREDIT_AFTER_VALIDATING_BILLS')
replace('multi',"'amount'=>$total,'payment_mode'=>$paymentMode,",
               "'amount'=>$cash,'payment_mode'=>$paymentMode,",'MULTI_HEADER_CASH_ONLY')
replace('multi',"'record_status'=>'ACTIVE','created_by'=>$actor,",
               "'record_status'=>'ACTIVE','created_by'=>$actor,'request_key'=>$key,",'MULTI_HEADER_REQUEST_KEY')
replace('multi',"$payment->source_reference='MULTI-PURCHASE-'.$payment->id;",
 """$newDebitCents=(int)round($cash*100)+array_sum($debits)-(int)round($total*100);
            $payment->source_reference=$newDebitCents>0?'OA-'.$payment->voucher_no:'MULTI-PURCHASE-'.$payment->id;""",
 'MULTI_NEW_ONACCOUNT_REFERENCE')
replace('multi','            return $payment;\n        },3);',
"""            $debitService->recordUses($vendorId,(int)$payment->id,$key,$debits);
            return $payment;
        },3);""",'MULTI_RECORD_USED_DEBIT')
# ----- Controller ----- 
replace('controller',"'amount'=>['required','numeric','gt:0'],\n                'payment_mode'",
"""'amount'=>['required','numeric','min:0'],
                'vendor_debits'=>['nullable','array','max:30'],
                'vendor_debits.*.voucher_id'=>['required','integer','min:1'],
                'vendor_debits.*.amount'=>['required','numeric','gt:0','max:99999999'],
                'payment_mode'""",'CONTROLLER_MULTI_DEBIT_VALIDATION')
replace('controller',"'am237OslVendorIds'=>\\Illuminate\\Support\\Facades\\DB::table('workshop_purchases')",
"""'am238DebitSources'=>\\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_vendor_debit_uses')
                ? app(\\App\\Services\\VendorDebitSettlementService::class)->sources() : [],
            'am237OslVendorIds'=>\\Illuminate\\Support\\Facades\\DB::table('workshop_purchases')""",
 'CONTROLLER_LOAD_VENDOR_DEBIT_SOURCES')
# Standalone ON_ACCOUNT gets an automatic stable Voucher reference too.
replace('controller',"""$request->merge(['source_reference'=>null,'source_key'=>null]);""",
"""$key=(string)$request->input('request_key','');
            if(!\\Illuminate\\Support\\Str::isUuid($key))
                return back()->withErrors(['request_key'=>'Invalid payment request.'])->withInput();
            $day=(string)$request->input('payment_date','');
            $date=\\DateTimeImmutable::createFromFormat('!Y-m-d',$day);
            $stamp=$date && $date->format('Y-m-d')===$day ? $date->format('Ymd') : now('Asia/Kolkata')->format('Ymd');
            $request->merge(['source_reference'=>'OA-'.$stamp.'-'.strtoupper(substr(str_replace('-','',$key),0,12)),'source_key'=>null]);""",
'CONTROLLER_ON_ACCOUNT_AUTO_REFERENCE')
# ----- ExpensePaymentService: block void of credits that were consumed -----
replace('service',"$p=WorkshopPaymentVoucher::query()->lockForUpdate()->find($id);",
"""$p=WorkshopPaymentVoucher::query()->lockForUpdate()->find($id);
            // AM238: consumed Debit sources may only be reversed after settlement vouchers.
            if($p && \\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_vendor_debit_uses') &&
               app(\\App\\Services\\VendorDebitSettlementService::class)->activeUsesForSource($id))
                throw ValidationException::withMessages(['payment'=>'This On Account Debit is already adjusted to later bills. Void/reverse the later settlement first.']);""",
'VOID_CONSUMED_SOURCE_PROTECTION')
replace('service',"return ['payment'=>(array)$row,'allocations'=>$allocations];",
"""$debitUses=\\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_vendor_debit_uses')
            ? DB::table('workshop_vendor_debit_uses as u')
                ->join('workshop_payment_vouchers as s','s.id','=','u.source_payment_voucher_id')
                ->where('u.settlement_payment_voucher_id',$id)
                ->select(['s.voucher_no','u.amount','u.source_payment_voucher_id'])
                ->get()->map(fn($a)=>(array)$a)->all() : [];
        $debitBalance=0.0;
        if(\\Illuminate\\Support\\Facades\\Schema::hasTable('workshop_vendor_debit_uses') && $row->vendor_id){
            foreach(app(\\App\\Services\\VendorDebitSettlementService::class)->sources((int)$row->vendor_id) as $item)
                if($item['voucher_id']===$id){$debitBalance=(float)$item['available'];break;}
        }
        return ['payment'=>(array)$row,'allocations'=>$allocations,'debitUses'=>$debitUses,'debitBalance'=>$debitBalance];""",
'SHOW_DEBIT_AUDIT_INFO')
# ----- Printed voucher -----
replace('show',"<div class=\"payment-amount-hero\"><span>AMOUNT PAID</span><strong>₹{{ number_format((float)$payment['amount'],2) }}</strong></div>",
"""<div class="payment-amount-hero"><span>NEW CASH / BANK PAYMENT</span><strong>₹{{ number_format((float)$payment['amount'],2) }}</strong></div>
@if(($debitBalance ?? 0)>0)
<div class="card" style="padding:12px;margin-bottom:10px;border:1px solid #acc7e1">
  <strong>ON ACCOUNT · DEBIT AVAILABLE</strong>
  <p>Reference: OA-{{ $payment['voucher_no'] }} · ₹{{ number_format((float)$debitBalance,2) }} remaining</p>
</div>
@endif
@if(!empty($debitUses))
<div class="card" style="padding:12px;margin-bottom:10px">
  <h3>Earlier Vendor Debit Adjusted (no new cash)</h3>
  @foreach($debitUses as $u)
    <div>OA-{{ $u['voucher_no'] }} · ₹{{ number_format((float)$u['amount'],2) }} adjusted</div>
  @endforeach
</div>
@endif""",
'PRINT_SHOW_CASH_AND_DEBIT_SEPARATE')
# ----- Blade/Voucher UI: earlier sources from controller -----
css=(assets/'vendor-debit-ui.css').read_text('utf8')
js=(assets/'vendor-debit-ui.js').read_text('utf8')
panel="""{{-- AM238_VENDOR_DEBIT_SETTLEMENT: select previous Vendor Debit (on-account) from the same bill area. --}}
<div id="am238-debit-panel" hidden>
 <h3>Vendor Debit / On Account Available</h3>
 <p>Select an earlier excess paid amount. It reduces bill outstanding but does not pay Cash again.</p>
 <div id="am238-debit-options"></div>
 <div id="am238-debit-summary"></div>
</div>
<script type="application/json" id="am238-debit-json">@json($am238DebitSources ?? [])</script>
"""
replace('view','<div id="am237-bills-slot" hidden></div>',
               '<div id="am237-bills-slot" hidden>'+panel+'</div>','DEBIT_UI_INSIDE_BILL_AREA')
# AM225 allocation algorithm: bill target = new Cash + explicitly selected old Debit.
replace('view',"const payment=paisa(amount.value),validPayment=Number.isFinite(payment)&&payment>0;",
"""const payment=paisa(amount.value),credit=(root.AM238Debit?.totalCents?.()||0),
          validPayment=Number.isFinite(payment)&&payment>=0&&(payment+credit)>0;""",
'AM225_VALID_CASH_PLUS_DEBIT')
replace('view','const remaining=Number.isFinite(payment)?payment-allocated:NaN;',
               'const remaining=Number.isFinite(payment)?payment+credit-allocated:NaN;',
'AM225_REMAINING_TOTAL')
replace('view','allocated>payment?', 'allocated>payment+credit?','AM225_OVERALLOC_CHECK')
replace('view',"money(allocated-payment)+'.'","money(allocated-payment-credit)+'.'",'AM225_ERROR_REMAINING')
replace('view',"'Unadjusted payment: '+money(remaining)+'. Select more bills or use Auto Adjust.'",
"""'On Account Debit to carry forward: '+money(remaining)+'. Confirm selected bill allocations before posting.'""",
'AM225_ALLOW_UNADJUSTED_ON_ACCOUNT_HINT')
replace('view','save.disabled=posting||!validPayment||!currentVendor||count<1||invalid||remaining!==0;',
               'save.disabled=posting||!validPayment||!currentVendor||count<1||invalid||remaining<0;',
'AM225_PERMIT_EXCESS_ON_ACCOUNT')
replace('view','const payment=paisa(amount.value);',
               'const payment=paisa(amount.value)+(root.AM238Debit?.totalCents?.()||0);',
'AM225_AUTOADJUST_USES_CASH_PLUS_DEBIT')
replace('view','const plan=allocateOldestFirst(amount.value,selected());',
               "const plan=allocateOldestFirst((payment/100).toFixed(2),selected());",
'AM225_OLDEST_ALLOC_TARGET')
replace('view','const total=paisa(amount.value),picked=getRows().filter(r=>r.querySelector(\'.am225-check\').checked);',
"""const total=paisa(amount.value),credit=(root.AM238Debit?.totalCents?.()||0),
          picked=getRows().filter(r=>r.querySelector('.am225-check').checked);""",
'AM225_SUBMIT_TOTAL_CASH_CREDIT')
replace('view','total!==sum){',
               'credit>sum||sum>total+credit){',
'AM225_SUBMIT_ALLOW_DEBIT_AND_NEW_ONACCOUNT')
replace('view',"'Enter Payment Amount and allocate exactly that amount to 1–30 bills for the same vendor. For remaining advance, create a separate On Account voucher.'",
"""'Select one or more same-vendor bills, then ensure Bill Allocations do not exceed Cash plus available Debit. Excess Cash will be retained with a new On Account reference.'""",
'AM225_SUBMIT_ERROR_COPY')
replace('view',"'Post one Payment Voucher of '+money(total)+' allocated across '+picked.length+' bill(s) for '+vendorById.get(currentVendor)+'?'",
"""'Post Cash '+money(total)+' + Prior Debit '+money(credit)+' applied across '+picked.length+' bills (Bill Total '+money(sum)+', New On Account '+money(Math.max(0,total+credit-sum))+') for '+vendorById.get(currentVendor)+'?'""",
'AM225_CONFIRM_CASH_AND_DEBIT')
replace('view','@endpush','<style id="am238-debit-css">\\n'+css+'\\n</style>\\n<script id="am238-debit-script">\\n'+js+'\\n</script>\\n@endpush',
'AM238_SCRIPT_STYLE_INSERT')
# Copy new service and migrations only when prior destinations absent.
code['debit_service']=(assets/'VendorDebitSettlementService.php').read_text('utf8')
code['migration']=(assets/'2026_11_01_001200_create_workshop_vendor_debit_uses.php').read_text('utf8')
# Preserve essential server posting protections.
for k in ('multi','service','controller','view','show'):
    if len(code[k])<=len(existing[k]):raise SystemExit('AM238_STOP_PATCH_OUTPUT_SHRUNK_'+k)
if marker not in code['multi'] or 'AM230 no Staff/Customer ledger key' not in code['controller']:
    raise SystemExit('AM238_STOP_SAFETY_MARKERS')
if 'AM237_UNIFIED_PAYMENT_TO' not in code['view']:raise SystemExit('AM238_STOP_AM237_UI_MISSING')
with tempfile.TemporaryDirectory(prefix='am238_candidate_') as tmp:
    td=Path(tmp)
    for k in ('multi','service','controller','debit_service','migration'):
        output=td/(k+'.php');output.write_text(code[k],'utf8',errors='surrogateescape')
        test=subprocess.run(['php','-l',str(output)],cwd=root,capture_output=True,text=True,timeout=30)
        if test.returncode:raise SystemExit('AM238_STOP_PHP_'+k+' '+(test.stdout+test.stderr)[-400:])
    if shutil.which('node'):
        test=subprocess.run(['node','--check',str(assets/'vendor-debit-ui.js')],capture_output=True,text=True,timeout=30)
        if test.returncode:raise SystemExit('AM238_STOP_JS_SYNTAX '+test.stderr[-400:])
    else:print('AM238_NODE_UNAVAILABLE_IN_STAGING_CI_JS_CHECK_REQUIRED')
    compiler=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for kind in ('view','show'):
        blade=td/(kind+'.blade.php');compiled=td/(kind+'.compiled.php')
        blade.write_text(code[kind],'utf8',errors='surrogateescape')
        for cmd in (['php','-r',compiler,str(blade),str(compiled)],['php','-l',str(compiled)]):
            test=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=50)
            if test.returncode:raise SystemExit('AM238_STOP_BLADE_'+kind+' '+(test.stdout+test.stderr)[-400:])
print('AM238_CASH_PLUS_PREVIOUS_DEBIT_EQUAL_BILL_PLUS_NEW_ONACCOUNT_PASS')
print('AM238_MATCH_VENDOR_AND_LOCK_PRIOR_DEBIT_PASS')
print('AM238_SOURCE_VOID_BLOCK_AFTER_DEBIT_USE_PASS')
print('AM238_PREVIOUS_DEBIT_NOT_NEW_CASH_PASS')
print('AM238_PHP_BLADE_AND_JS_LINT_PASS')
if mode=='check':
    print('AM238_READONLY_PATCH_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM238_STOP_BACKUP_EXISTS_REVIEW_BEFORE_APPLY')
backup.mkdir(parents=True,exist_ok=False)
for k,p in paths.items():
    original_file=backup/(k+'.before_AM238')
    if existing[k] is None:original_file.write_text('__AM238_FILE_NOT_PRESENT__','utf8')
    else:shutil.copy2(p,original_file)
def atomic(p,value):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am238_',dir=p.parent)
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
        old=backup/(k+'.before_AM238')
        if old.read_text('utf8')=='__AM238_FILE_NOT_PRESENT__':
            if p.exists():p.unlink()
        else:shutil.copy2(old,p)
    raise
print('AM238_STAGING_PATCH_FILES_WRITTEN_WITH_ROLLBACK_BACKUPS')
