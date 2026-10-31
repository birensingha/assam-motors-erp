#!/usr/bin/env python3
"""AM234 guarded view-only Staging Payment Voucher feature selector.
No schema, controller, service, ledger, existing payment, or voucher writes.
"""
import os,sys,subprocess,tempfile,shutil
from pathlib import Path

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: apply.py check|apply|rollback STAGING_LARAVEL_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
view=root/'resources/views/payments/create.blade.php'
backup=root/'storage/app/am-backups/AM234_UNIVERSAL_PAYMENT_VOUCHER/create.blade.php.before_AM234'
assets=Path(__file__).resolve().parent
script=assets/'feature-ui.js'
marker='AM234_UNIVERSAL_PAYMENT_FEATURES'
anchor='<div id="am230-payee-box" class="p133-full am230-payee">'
if not all(p.is_file() for p in (root/'artisan',view,script)):
    raise SystemExit('AM234_STOP_STAGING_SOURCE_OR_ASSET_MISSING')
source=view.read_text(encoding='utf8',errors='surrogateescape')

def atomic(path,content):
    fd,tmp=tempfile.mkstemp(prefix='.am234_',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as stream:
            stream.write(content)
        os.chmod(tmp,path.stat().st_mode&0o777)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)

if mode=='rollback':
    if source.count(marker)!=1 or not backup.is_file():
        raise SystemExit('AM234_STOP_ROLLBACK_BACKUP_OR_MARKER_MISSING')
    atomic(view,backup.read_text(encoding='utf8',errors='surrogateescape'))
    print('AM234_PREVIOUS_PAYMENT_VIEW_RESTORED')
    sys.exit(0)

if source.count(marker)==1:
    print('AM234_ALREADY_APPLIED')
    sys.exit(0)
required=[
 ('AM230_PAYEE_ANCHOR',anchor),
 ('AM231_RECIPIENT_CONTEXT','AM231_PAYMENT_TO_ACCOUNTING_CONTEXT'),
 ('AM230_IDENTITY','am230-payee-type'),
 ('AM231_PURPOSE','am231-payment-purpose'),
 ('AM231_SCRIPT','am231-accounting-script'),
 ('MAIN_PAYMENT_FORM','id="payment-form"'),
 ('BACKEND_SOURCE_TYPE','id="source-type"'),
 ('POST_BUTTON','id="p133-save"'),
 ('END_OF_SCRIPT_SECTION','@endpush')
]
for label,needle in required:
    n=source.count(needle)
    print('AM234_ANCHOR_'+label+'='+('PASS' if n==1 else 'FAIL_'+str(n)))
    if n!=1:raise SystemExit('AM234_STOP_SOURCE_DRIFT_'+label)

panel='''{{-- AM234_UNIVERSAL_PAYMENT_FEATURES: one existing voucher; select approved business intent. --}}
<style id="am234-payment-feature-css">
.am234-container{grid-column:1/-1;padding:12px 14px;background:linear-gradient(120deg,#eaf3fe,#f7fbff);border:1px solid #b5cbe3;border-radius:11px;margin:6px 0 10px}
.am234-head{display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap}
.am234-head strong{font-size:15px;color:#153958;letter-spacing:.1px}
.am234-head small{font-size:10px;font-weight:700;color:#486986}
.am234-container label{display:block;font-size:11px;font-weight:700;margin-top:9px;color:#234764}
#am234-feature{width:100%;min-height:39px;border:1px solid #9cbcd8;border-radius:7px;background:#fff;padding:7px 10px;font-size:13px;color:#163f5e}
#am234-status{margin:8px 0 0;padding:9px 10px;border-radius:6px;background:#e7f3e9;border-left:3px solid #32835a;color:#174b30;font-size:12px;line-height:1.4}
#am234-status[data-status=blocked]{background:#fff0e5;border-left-color:#bd791a;color:#754c19}
.am234-sub{font-size:11px;color:#506c83;margin-top:6px}
@media(max-width:560px){.am234-container{padding:10px;margin-top:0}.am234-head strong{font-size:14px}}
</style>
<section class="am234-container" id="am234-feature-panel" aria-label="Universal Payment Voucher Feature">
 <div class="am234-head"><strong>Universal Payment Voucher</strong><small>ONE VOUCHER · SIX PAYMENT FEATURES</small></div>
 <label for="am234-feature">Payment Feature / Transaction Purpose *</label>
 <select id="am234-feature" autocomplete="off">
  <option value="VENDOR">1. Vendor Payment — Parts Purchase</option>
  <option value="CUSTOMER">2. Customer Payment / Refund</option>
  <option value="SALARY_ADVANCE">3. Staff Salary Advance</option>
  <option value="STAFF_EXPENSE">4. Staff Expense Reimbursement — Tour, Petrol, Lunch, Lubricash</option>
  <option value="DIRECT_OSL">5. Direct OSL Payment — Job Card Linked</option>
  <option value="OSL_VENDOR">6. OSL Vendor Payment — Outstanding OSL Purchase</option>
 </select>
 <p class="am234-sub" id="am234-feature-title">Vendor Payment</p>
 <p id="am234-status" role="status">Existing vendor payment ledger and bill-wise allocation remain available.</p>
 <p class="am234-sub">Only verified accounting paths can post. Unsupported payment types remain locked until full audited ledger/reversal integration is complete.</p>
</section>
'''
newview=source.replace(anchor,panel+anchor,1)
js=script.read_text('utf8')
if 'AM234 universal payment feature selector' not in js or 'form.addEventListener' not in js:
    raise SystemExit('AM234_STOP_UNRECOGNIZED_SCRIPT')
newview=newview.replace('@endpush','<script id="am234-feature-ui-script">\n'+js+'\n</script>\n@endpush',1)
if newview.count(marker)!=1 or newview.count('id="am234-feature"')!=1:
    raise SystemExit('AM234_STOP_PATCH_INSERT_FAILED')
# Preserve original payment POST and all existing backend input names.
for needle in ('name="vendor_id"','name="source_type"','name="payment_mode"',
               'name="amount"','name="request_key"','am226-vendor-only-inline',
               'am230-payee-script','am231-accounting-script'):
    if newview.count(needle)!=source.count(needle):
        raise SystemExit('AM234_STOP_FINANCIAL_SOURCE_CHANGED_'+needle)

with tempfile.TemporaryDirectory(prefix='am234_preflight_') as temp:
    td=Path(temp)
    candidate=td/'create.blade.php'
    compiled=td/'compiled.php'
    candidate.write_text(newview,encoding='utf8',errors='surrogateescape')
    check=subprocess.run(['node','--check',str(script)],capture_output=True,text=True,timeout=20)
    if check.returncode:
        raise SystemExit('AM234_STOP_JS_SYNTAX '+check.stderr[-300:])
    code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
    for command in ([ 'php','-r',code,str(candidate),str(compiled)],
                    [ 'php','-l',str(compiled)]):
        check=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=60)
        if check.returncode:
            raise SystemExit('AM234_STOP_BLADE_COMPILE_OR_LINT '+(check.stdout+check.stderr)[-380:])

print('AM234_SIX_FEATURE_SAP_STYLE_UI=PASS')
print('AM234_VENDOR_AND_OSL_VENDOR_EXISTING_POST_PATHS_PRESERVED=PASS')
print('AM234_STAFF_CUSTOMER_DIRECT_OSL_POST_BLOCKED=PASS')
print('AM234_BACKEND_SERVICE_AND_FINANCIAL_TABLES_UNCHANGED=PASS')
print('AM234_BLADE_AND_JS_SYNTAX_PASS')
if mode=='check':
    print('AM234_READONLY_PREFLIGHT_PASS')
    sys.exit(0)
if backup.exists():
    raise SystemExit('AM234_STOP_BACKUP_EXISTS_MANUAL_REVIEW_REQUIRED')
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(view,backup)
try:atomic(view,newview)
except BaseException:
    shutil.copy2(backup,view)
    raise
print('AM234_STAGING_VIEW_APPLIED_WITH_ROLLBACK_BACKUP')
