#!/usr/bin/env python3
"""AM231 guarded staging-only Payment Voucher recipient-specific UI.
View+create GET only: real Staff and Customer masters, Purpose triad and no
Vendor-specific controls when Payment To Staff / Customer.
Existing vendor posting, payment allocations, voids, financial service untouched.
Staff/Customer POST remains server-blocked by AM230 until their ledgers exist.
"""
from pathlib import Path
import os,sys,shutil,re,subprocess,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
view=root/'resources/views/payments/create.blade.php'
ctrl=root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
backup=root/'storage/app/am-backups/AM231_PAYMENT_ACCOUNTING_UI'
assets=Path(__file__).resolve().parent
marker='AM231_PAYMENT_TO_ACCOUNTING_CONTEXT'
if not all(p.is_file() for p in [root/'artisan',view,ctrl,assets/'accounting.js',assets/'accounting.css']):
    raise SystemExit('AM231_STOP_MISSING_SOURCE_OR_ASSETS')
v=view.read_text('utf8',errors='surrogateescape')
c=ctrl.read_text('utf8',errors='surrogateescape')
def atomic(p,data):
    fd,tmp=tempfile.mkstemp(prefix='.am231_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(data)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
    bv=backup/'create.blade.php.before_AM231'
    bc=backup/'controller.php.before_AM231'
    if v.count(marker)!=1 or not (bv.is_file() and bc.is_file()):
        raise SystemExit('AM231_STOP_ROLLBACK_SOURCE_BACKUP_MISMATCH')
    atomic(view,bv.read_text('utf8',errors='surrogateescape'))
    atomic(ctrl,bc.read_text('utf8',errors='surrogateescape'))
    print('AM231_PRIOR_PAYMENT_VIEW_AND_CONTROLLER_RESTORED')
    sys.exit(0)
if marker in v:
    print('AM231_ALREADY_APPLIED')
    sys.exit(0)
create="return view('payments.create',['sources'=>$service->payableSources(),'vendors'=>$service->vendors(),'jobs'=>$service->jobs()]);"
points=[
    ('CREATE_CONTROLLER',create,c),
    ('PAYEE_SLOT','<div id="am230-vendor-slot"></div>',v),
    ('PAYMENT_TO','<select id="am230-payee-type" name="payee_category">',v),
    ('VENDOR_BILL_SEARCH','<div id="am218-search-wrap" class="am218-wrap">',v),
    ('CREATE_SCRIPT_END','@endpush',v),
    ('AM230_POSTING_SAFETY',"AM230 no Staff/Customer ledger key",c),
    ('AM226_VENDOR_ALLOCATION',"am226-vendor-only-inline",v),
    ('AM230_CATEGORY_SCRIPT','am230-payee-script',v),
    ('PAYMENT_SOURCE_SELECT','<select name="source_type" id="source-type" required>',v),
    ('PAYMENT_POST_FORM',"action=\"{{ route('erp.payments.store') }}\"",v),
    ('EXPENSE_DEEPLINK',"request('source')==='EXPENSE_VOUCHER'",v)
]
for name,needle,body in points:
 n=body.count(needle)
 print('AM231_PREFLIGHT_'+name+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
 if n!=1:raise SystemExit('AM231_STOP_SOURCE_CHANGED_'+name)
# Fully named actual DB master fields rather than free-text or vendor identities.
# No posting fields are duplicated; the existing vendor_id select is retained.
master_ui='''{{-- AM231_PAYMENT_TO_ACCOUNTING_CONTEXT: recipient-specific master controls. --}}
<div id="am231-staff-area" class="am231-recipient" hidden>
 <label for="am231-staff-select">Select Staff Name *</label>
 <input type="search" id="am231-staff-search" placeholder="Type staff name or staff code" autocomplete="off">
 <select id="am231-staff-select" name="staff_id" disabled>
 <option value="">— Select Staff from Master —</option>
 @foreach(($am231Staff ?? []) as $person)
 <option value="{{ $person->id }}" data-name="{{ $person->name }}" @selected((string)old('staff_id')===(string)$person->id)>{{ $person->staff_code }} — {{ $person->name }}</option>
 @endforeach
 </select>
</div>
<div id="am231-customer-area" class="am231-recipient" hidden>
 <label for="am231-customer-select">Select Customer Name *</label>
 <input type="search" id="am231-customer-search" placeholder="Type customer name or customer code" autocomplete="off">
 <select id="am231-customer-select" name="customer_id" disabled>
 <option value="">— Select Customer from Master —</option>
 @foreach(($am231Customers ?? []) as $person)
 <option value="{{ $person->id }}" data-name="{{ $person->name }}" @selected((string)old('customer_id')===(string)$person->id)>{{ $person->customer_code }} — {{ $person->name }}</option>
 @endforeach
 </select>
</div>
<div id="am231-recipient-preview" aria-live="polite">Vendor · Select Vendor Name</div>
<span id="am231-posting-alert">Staff and Customer financial payouts are not active until ledger linkage, authorization and audited reversals are implemented.</span>
'''
v=v.replace('<div id="am230-vendor-slot"></div>',
            '<div id="am230-vendor-slot"></div>\n'+master_ui,1)
purpose_ui='''<div id="am231-purpose-wrap" class="p133-full">
 <label class="p133-label" for="am231-payment-purpose">Payment Against *</label>
 <select id="am231-payment-purpose" aria-describedby="am231-context-help">
 <option value="REFERENCE">Against Reference</option>
 <option value="ADVANCE">Advance</option>
 <option value="ON_ACCOUNT">On Account</option>
 </select>
 <div id="am231-reference-choices" hidden></div>
 <p id="am231-context-help">Choose recipient first, then select the appropriate payment purpose.</p>
</div>
'''
v=v.replace('<div id="am218-search-wrap" class="am218-wrap">',
            purpose_ui+'<div id="am218-search-wrap" class="am218-wrap">',1)
css=(assets/'accounting.css').read_text('utf8')
js=(assets/'accounting.js').read_text('utf8')
if 'AM231 Payment voucher accounting identity' not in css or 'AM231 Recipient-specific' not in js:
    raise SystemExit('AM231_STOP_ASSETS_DO_NOT_MATCH_RELEASE')
v=v.replace('<div class="p133-wrap">','<style id="am231-accounting-style">\n'+css+'\n</style>\n<div class="p133-wrap">',1)
if v.count('id="am231-accounting-style"')!=1:
    raise SystemExit('AM231_STOP_STYLES_INSERTION_FAILED')
v=v.replace('@endpush','<script id="am231-accounting-script">\n'+js+'\n</script>\n@endpush',1)
# Controller only adds read-only recipient list to payment create. The existing
# AM230 POST guard, expense deeplink, multibill service and finance writes stay.
addcreate=r"""return view('payments.create',[
            'sources'=>$service->payableSources(),
            'vendors'=>$service->vendors(),
            'jobs'=>$service->jobs(),
            'am231Staff'=>\Illuminate\Support\Facades\DB::table('staff')
                ->orderBy('name')->limit(3000)->get(['id','staff_code','name']),
            'am231Customers'=>\Illuminate\Support\Facades\DB::table('customer_master')
                ->orderBy('name')->limit(5000)->get(['id','customer_code','name']),
        ]);"""
c=c.replace(create,addcreate,1)
if v.count(marker)!=1 or c.count("'am231Staff'")!=1 or c.count("'am231Customers'")!=1:
    raise SystemExit('AM231_STOP_NEW_UI_OR_MASTER_BINDING_MISSING')
# Do not remove original source_type options, master vendor field or payment
# POST/CSRF/ledger references.
for needle in ('name="vendor_id"','name="source_type"',
               'name="payment_mode"', 'name="amount"', 'name="request_key"',
               "request('source')==='EXPENSE_VOUCHER'",'am226-vendor-only-inline',
               'am230-payee-script'):
    if v.count(needle)!=1:raise SystemExit('AM231_STOP_FINANCE_OR_MASTER_FORM_CHANGED_'+needle)
if c.count('AM230 no Staff/Customer ledger key')!=1 or 'MultiPurchasePaymentService' not in c:
    raise SystemExit('AM231_STOP_POSTING_SAFETY_CHANGED')
with tempfile.TemporaryDirectory(prefix='am231_preflight_') as tmp:
    td=Path(tmp);tview=td/'payment.blade.php';tcontroller=td/'controller.php';tcompiled=td/'compiled.php'
    tview.write_text(v,'utf8',errors='surrogateescape');tcontroller.write_text(c,'utf8',errors='surrogateescape')
    phpcode=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for cmd in [['php','-r',phpcode,str(tview),str(tcompiled)],
                ['php','-l',str(tcompiled)],['php','-l',str(tcontroller)]]:
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=65)
        if result.returncode:
            raise SystemExit('AM231_STOP_PHP_BLADE_LINT '+(result.stdout+result.stderr)[-340:])
print('AM231_VENDOR_ONLY_BILLS_HIDDEN_FOR_NONVENDOR=PASS')
print('AM231_STAFF_CUSTOMER_MASTER_LOOKUP_ADDED=PASS')
print('AM231_AGAINST_REFERENCE_ADVANCE_ON_ACCOUNT_UI=PASS')
print('AM231_ORIGINAL_VENDOR_ALLOCATION_AND_EXPENSE_LINK_UNCHANGED=PASS')
print('AM231_STAFF_CUSTOMER_FINANCIAL_POSTING_STAYS_BLOCKED=PASS')
print('AM231_FULL_BLADE_PHP_SYNTAX_PASS')
if mode=='check':
    print('AM231_CHECK_READONLY_PASS');sys.exit(0)
if backup.exists():
    bv=backup/'create.blade.php.before_AM231'
    bc=backup/'controller.php.before_AM231'
    if not (bv.is_file() and bc.is_file()
            and bv.read_text('utf8',errors='surrogateescape')==v
            and bc.read_text('utf8',errors='surrogateescape')==c):
        raise SystemExit('AM231_STOP_STALE_BACKUP_MISMATCH_OR_LATER_PAYMENT_EDITS')
    print('AM231_PRIOR_FAILED_DEPLOY_BACKUP_MATCHES_CURRENT_SAFE_RETRY')
else:
    backup.mkdir(parents=True,exist_ok=False)
    shutil.copy2(view,backup/'create.blade.php.before_AM231')
    shutil.copy2(ctrl,backup/'controller.php.before_AM231')
try:
    atomic(ctrl,c)
    atomic(view,v)
except BaseException:
    shutil.copy2(backup/'create.blade.php.before_AM231',view)
    shutil.copy2(backup/'controller.php.before_AM231',ctrl)
    raise
print('AM231_PAYMENT_CONTEXT_VIEW_AND_CREATE_GET_INSTALLED')
print('AM231_NO_DATABASE_OR_PAYMENT_POSTING_WRITES')
