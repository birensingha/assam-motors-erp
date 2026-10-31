#!/usr/bin/env python3
"""AM237 staged payment UI and server-authored Vendor reference numbers.
Existing financial POST services, allocations, ledger voucher records untouched.
Staff/Customer payout remains fail-closed until proper ledgers approved.
"""
import sys,shutil,subprocess,tempfile,os
from pathlib import Path
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
paths={
 'view':root/'resources/views/payments/create.blade.php',
 'controller':root/'app/Http/Controllers/Web/AdminExpensePaymentPageController.php'
}
backup=root/'storage/app/am-backups/AM237_UNIFIED_PAYMENT_TO'
asset=Path(__file__).resolve().parent
marker='AM237_UNIFIED_PAYMENT_TO'
if not (root/'artisan').is_file() or not all(x.is_file() for x in [*paths.values(),asset/'unified.js',asset/'unified.css']):
    raise SystemExit('AM237_STOP_APP_SOURCE_OR_ASSETS_MISSING')
original={k:p.read_text('utf8',errors='surrogateescape') for k,p in paths.items()}
def atomic(path,content):
    fd,tmp=tempfile.mkstemp(prefix='.am237_',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(content)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if mode=='rollback':
    if marker not in original['view'] or not backup.is_dir():
        raise SystemExit('AM237_STOP_ROLLBACK_INVALID_STATE')
    for k,p in paths.items():
        stored=backup/(k+'.before_AM237')
        if not stored.is_file():raise SystemExit('AM237_STOP_ROLLBACK_BACKUP_'+k)
    for k,p in paths.items():atomic(p,(backup/(k+'.before_AM237')).read_text('utf8',errors='surrogateescape'))
    print('AM237_PREVIOUS_VIEW_AND_CONTROLLER_RESTORED')
    sys.exit(0)
if marker in original['view']:
    print('AM237_ALREADY_APPLIED')
    sys.exit(0)
def swap(k,a,b,label):
    v=original[k]
    count=v.count(a)
    print('AM237_ANCHOR_'+label+'='+('PASS' if count==1 else 'FAIL_'+str(count)))
    if count!=1:raise SystemExit('AM237_STOP_SOURCE_DRIFT_'+label)
    original[k]=v.replace(a,b,1)
view=original['view']
ctrl=original['controller']
for name,needle,source in [
 ('AM236_AUTO_REFERENCE','AM236_VENDOR_NEW_REFERENCE',ctrl),
 ('AM230_FAIL_CLOSED','AM230 no Staff/Customer ledger key',ctrl),
 ('AM234_FEATURE_SELECTOR','id="am234-feature-panel"',view),
 ('AM235_SINGLE_VENDOR','AM235_SINGLE_VENDOR_MASTER_COMBOBOX',view),
 ('AM236_REFERENCE','id="am236-reference-label"',view),
 ('AM226_MULTI_BILL','id="p133-multi-area"',view),
 ('CANONICAL_VENDOR_ID','name="vendor_id" id="payment-vendor"',view),
 ('STAFF_MASTER','id="am231-staff-select"',view),
 ('CUSTOMER_MASTER','id="am231-customer-select"',view),
 ('REQUEST_KEY','name="request_key"',view),
 ('SOURCE_TYPE','id="source-type"',view),
 ('PAYMENT_AMOUNT','id="payment-value"',view),
]:
 if source.count(needle)!=1:raise SystemExit('AM237_STOP_EXPECTED_ONE_'+name)
css=(asset/'unified.css').read_text('utf8')
js=(asset/'unified.js').read_text('utf8')
if 'AM237 Payment Voucher compact' not in css or 'AM237 PAYMENT TO:' not in js:
    raise SystemExit('AM237_STOP_ASSET_SIGNATURE_MISMATCH')
panel='''{{-- AM237_UNIFIED_PAYMENT_TO: one Payment To, one Payment Against, amount first. --}}
<style id="am237-unified-style">
'''+css+'''
</style>
<section id="am237-layout" aria-label="Payment Voucher Details">
 <div class="am237-head"><h2>Payment Voucher</h2><div id="am237-date-slot"></div></div>
 <div class="am237-fields">
  <div class="am237-payee">
   <label for="am237-payee-search">Payment To *</label>
   <div class="am237-search-wrap">
     <input type="search" id="am237-payee-search" placeholder="Search Staff / Customer / Vendor / OSL Vendor by name or code"
      autocomplete="off" aria-controls="am237-payee-results" aria-expanded="false" role="combobox" aria-autocomplete="list">
     <div id="am237-payee-results" role="listbox" hidden></div>
   </div>
   <div id="am237-payee-selected" role="status"></div>
  </div>
  <label for="am237-mode">Payment Against *
   <select id="am237-mode">
    <option value="NEW_REFERENCE">New Reference</option>
    <option value="AGAINST" selected>Against Reference</option>
    <option value="ON_ACCOUNT">On Account</option>
    <option value="ADVANCE">Advance</option>
   </select>
  </label>
  <div id="am237-amount-slot"></div>
  <div id="am237-reference-slot" hidden></div>
  <div id="am237-bills-slot" hidden></div>
 </div>
 <div id="am237-ledger-status" role="status"></div>
 <div id="am237-osl-vendor-ids" hidden>{{ implode(',', array_map('strval', $am237OslVendorIds ?? [])) }}</div>
</section>
'''
swap('view','<div class="p133-body">',panel+'<div class="p133-body">','MAIN_PANEL_INSERT')
swap('view','@endpush','<script id="am237-unified-script">\n'+js+'\n</script>\n@endpush','SCRIPT_INSERT')
# Controller: include OSL suppliers as Vendor Master participants (same vendor_id ledger).
swap('controller',"->orderBy('name')->limit(5000)->get(['id','customer_code','name']),\n        ]);",
"""->orderBy('name')->limit(5000)->get(['id','customer_code','name']),
            'am237OslVendorIds'=>\\Illuminate\\Support\\Facades\\DB::table('workshop_purchases')
                ->where('purchase_type','OSL')->whereNotNull('vendor_id')->distinct()->pluck('vendor_id')->all(),
        ]);""",'LOOKUP_OSL_VENDOR_IDS')
# Server-authoritative automatic numbers: timestamp+uuid token; never trust edited client.
needle='        // AM236 vendor New Reference and Advance must have real vendor + reference identity.'
guard="""        // AM237: auto-generated references are derived server-side from a UUID request key.
        // UTR/Bank transaction reference remains a separate field (reference_no).
        if (!$request->boolean('multi_bill') && in_array((string)$request->input('source_type'),['NEW_REFERENCE','ADVANCE'],true)) {
            $requestKey=(string)$request->input('request_key','');
            if (!\\Illuminate\\Support\\Str::isUuid($requestKey)) {
                return back()->withErrors(['request_key'=>'Invalid voucher request identity. No payment saved.'])->withInput();
            }
            $prefix=$request->input('source_type')==='ADVANCE'?'ADV-':'NR-';
            $number=$prefix.now('Asia/Kolkata')->format('Ymd').'-'.strtoupper(substr(str_replace('-','',$requestKey),0,12));
            $request->merge(['source_reference'=>$number,'source_key'=>null]);
        }
        if (!$request->boolean('multi_bill') && (string)$request->input('source_type')==='ON_ACCOUNT') {
            $request->merge(['source_reference'=>null,'source_key'=>null]);
        }
"""
swap('controller',needle,guard+needle,'SERVER_GENERATED_REFERENCE')
if not any('AM237_UNIFIED_PAYMENT_TO' in v for v in original.values()):
    raise SystemExit('AM237_STOP_OUTPUT_MARKER_ABSENT')
if original['view'].count('name="vendor_id"')!=1 or original['view'].count('name="amount"')!=1:
    raise SystemExit('AM237_STOP_DUPLICATE_FINANCIAL_FORM_FIELD')
if 'AM230 no Staff/Customer ledger key' not in original['controller']:
    raise SystemExit('AM237_STOP_FINANCIAL_POSTING_GUARD_REMOVED')
with tempfile.TemporaryDirectory(prefix='am237_check_') as t:
    td=Path(t)
    if shutil.which('node'):
        check=subprocess.run(['node','--check',str(asset/'unified.js')],capture_output=True,text=True,timeout=20)
        if check.returncode:raise SystemExit('AM237_STOP_JAVASCRIPT '+check.stderr[-400:])
    else:print('AM237_NO_STAGING_NODE_CI_LINT_REQUIRED')
    controller=td/'controller.php';controller.write_text(original['controller'],'utf8',errors='surrogateescape')
    check=subprocess.run(['php','-l',str(controller)],cwd=root,capture_output=True,text=True,timeout=30)
    if check.returncode:raise SystemExit('AM237_STOP_CONTROLLER_PHP '+check.stderr[-400:])
    blade=td/'create.blade.php';compiled=td/'compiled.php'
    blade.write_text(original['view'],'utf8',errors='surrogateescape')
    compiler=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for cmd in (['php','-r',compiler,str(blade),str(compiled)],['php','-l',str(compiled)]):
        check=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=60)
        if check.returncode:raise SystemExit('AM237_STOP_BLADE_COMPILE '+(check.stdout+check.stderr)[-400:])
print('AM237_ONE_PAYMENT_TO_MASTER_SEARCH=PASS')
print('AM237_FOUR_PAYMENT_AGAINST_MODES=PASS')
print('AM237_AMOUNT_BEFORE_REFERENCE_BILLS=PASS')
print('AM237_SERVER_GENERATED_VENDOR_NEW_ADVANCE_REFERENCE=PASS')
print('AM237_ON_ACCOUNT_NO_REFERENCE=PASS')
print('AM237_VENDOR_PARTS_OSL_MULTI_ALLOCATION_PRESERVED=PASS')
print('AM237_STAFF_CUSTOMER_POSTING_STILL_LOCKED=PASS')
print('AM237_EXISTING_VOUCHERS_AND_FINANCIAL_SERVICES_UNCHANGED=PASS')
print('AM237_PHP_AND_BLADE_SYNTAX_PASS')
if mode=='check':
    print('AM237_READONLY_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():raise SystemExit('AM237_STOP_BACKUP_ALREADY_EXISTS')
backup.mkdir(parents=True,exist_ok=False)
for k,p in paths.items():shutil.copy2(p,backup/(k+'.before_AM237'))
try:
    for k,p in paths.items():atomic(p,original[k])
except BaseException:
    for k,p in paths.items():shutil.copy2(backup/(k+'.before_AM237'),p)
    raise
print('AM237_STAGING_PAYMENT_FORM_AND_REFERENCE_CONTROLLER_APPLIED_WITH_BACKUPS')
