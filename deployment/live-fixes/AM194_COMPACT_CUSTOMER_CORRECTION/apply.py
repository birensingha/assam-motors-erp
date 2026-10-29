#!/usr/bin/env python3
"""AM194: Replace ugly inline AM172 Customer Name Correction bar on Job Card.

One compact Admin-only 'Correct Customer Name' button in JC quick-action bar
opens a professional modal with the SAME existing audited Sync/Manual options.
Changes ONLY resources/views/job-cards/show.blade.php. No route/controller,
migration, customer, invoice, accounting or permission changes.

  python3 apply.py check STAGING_ROOT
  python3 apply.py apply STAGING_ROOT
  python3 apply.py rollback STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('Usage: python3 apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
target=root/'resources/views/job-cards/show.blade.php'
payload=pkg/'compact-customer-name.blade.php'
backend=root/'app/Http/Controllers/Web/AdminJobCardCustomerNameSyncController.php'
backupdir=root/'storage/app/am-backups/AM194_COMPACT_CUSTOMER_CORRECTION'
backup=backupdir/'show.blade.php.before_AM194'
manifest=backupdir/'installed.sha256'
marker='AM194_COMPACT_CUSTOMER_CORRECTION'

def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,temp=tempfile.mkstemp(prefix='.am194_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(temp,p.stat().st_mode & 0o777)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)

if not (root/'artisan').is_file() or not target.is_file() or not backend.is_file():
    raise SystemExit('AM194_STOP_STAGING_OR_EXISTING_AM172_BACKEND_MISSING')
original=read(target)

if mode=='rollback':
    if not backup.is_file() or not manifest.is_file():
        raise SystemExit('AM194_STOP_BACKUP_MISSING')
    if sha(original)!=read(manifest).strip():
        raise SystemExit('AM194_STOP_LIVE_VIEW_CHANGED_AFTER_APPLY_PRESERVE_NEW_EDITS')
    atomic(target,read(backup))
    print('AM194_ROLLBACK_RESTORED_ORIGINAL_VIEW')
    print('AM194_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)

if not payload.is_file():raise SystemExit('AM194_STOP_MODAL_TEMPLATE_MISSING')
if marker in original:raise SystemExit('AM194_ALREADY_INSTALLED')
if original.count('id="AM172_JC_CUSTOMER_NAME_SYNC"')!=1:
    raise SystemExit('AM194_STOP_ORIGINAL_CUSTOMER_NAME_BAR_NOT_FOUND')
if original.count('class="am172-name-bar"')!=1:
    raise SystemExit('AM194_STOP_ORIGINAL_FORM_MISMATCH')
if original.count('<nav class="am75-quick-actions"')!=1:
    raise SystemExit('AM194_STOP_JOB_CARD_ACTION_BAR_MISSING')
if "route('erp.job-cards.customer-name-sync',$job->id)" not in original:
    raise SystemExit('AM194_STOP_CUSTOMER_SYNC_ROUTE_NOT_FOUND')
for signature in ("'mode' => ['required', 'in:sync,manual']",
                  "'reason' => ['required', 'string', 'min:10'",
                  "AM172_CUSTOMER_NAME_SYNC", "AM172_CUSTOMER_NAME_MANUAL"):
    if signature not in read(backend):
        raise SystemExit('AM194_STOP_EXISTING_BACKEND_CONTRACT_CHANGED')
# Extract EXACT AM172 inline bar including nested Blade @if, but leave all
# unrelated JC panels, action handlers, styles and components untouched.
start=original.index('{{-- AM172_JC_CUSTOMER_NAME_SYNC:')
endToken='</form>\n</div>\n@endif'
end=original.find(endToken,start)
if end<0:raise SystemExit('AM194_STOP_AM172_END_MARKER_MISSING')
end+=len(endToken)
old=original[start:end]
for token in [
    'class="am172-name-bar"',
    '<style id="AM172_JC_CUSTOMER_NAME_SYNC">',
    'Sync from Master','Correct Name Manually',
    'name="new_name"','name="reason"','name="mode"',
    '@csrf',
]:
    if token not in old:raise SystemExit('AM194_STOP_AM172_BLOCK_CHANGED_'+token)
if old.count('<form')!=1 or old.count('</form>')!=1:
    raise SystemExit('AM194_STOP_ORIGINAL_BLOCK_AMBIGUOUS')

modal=read(payload).rstrip()
if modal.count(marker)!=1:
    raise SystemExit('AM194_STOP_NEW_MODAL_MARKER_MISMATCH')
required=[
    'route(\'erp.job-cards.customer-name-sync\', $job->id)',
    '@csrf', 'value="sync"', 'value="manual"', 'name="reason"',
    'name="new_name"', "request()->user()", "isAdmin()",
    "am194-open", 'am194-customer-modal',
]
for token in required:
    if token not in modal:
        raise SystemExit('AM194_STOP_MODAL_CONTRACT_MISSING_'+token)
changed=original[:start]+modal+'\n'+original[end:]

# Add exactly ONE compact button in the existing JC quick-action navbar.
# Insert next to Job Card Summary / Credit Note / Estimate, not a new panel.
nav='<nav class="am75-quick-actions"'
nav_start=changed.find(nav)
nav_end=changed.find('</nav>',nav_start)
if nav_start<0 or nav_end<0 or nav_end-nav_start>6000:
    raise SystemExit('AM194_STOP_QUICK_ACTIONS_NAV_CHANGED')
button="""  {{-- AM194: only one compact customer correction button; opens modal. --}}
  @if($am194CanCorrect)
    <button type="button" id="am194-open" class="am194-top-button"
            aria-haspopup="dialog" aria-controls="am194-customer-modal">Correct Customer Name</button>
  @endif
"""
changed=changed[:nav_end]+button+changed[nav_end:]
if changed.count('id="am194-open"')!=1:
    raise SystemExit('AM194_STOP_BUTTON_INJECTION_INVALID')
if changed.count('id="am194-customer-modal"')!=1:
    raise SystemExit('AM194_STOP_MODAL_INJECTION_INVALID')
if 'class="am172-name-bar"' in changed:
    raise SystemExit('AM194_STOP_LEGACY_BAR_STILL_VISIBLE')
if changed.count("route('erp.job-cards.customer-name-sync'")!=1:
    raise SystemExit('AM194_STOP_BACKEND_FORM_COUNT_CHANGED')
for before in ['FIX75_JC_THREE_ACTION_BAR','AM193_UNUSED_JOB_CARD_DELETE_ACTION',
               "route('erp.job-cards.summary-print'",
               "route('erp.job-cards.credit-correction'"]:
    if before in original and before not in changed:
        raise SystemExit('AM194_STOP_UNRELATED_JOB_CARD_FEATURE_REMOVED')

# Compile entire candidate Blade in the actual Laravel installation and lint
# compiled PHP before saving; no request is dispatched, no database writes.
with tempfile.TemporaryDirectory(prefix='am194check_') as d:
    tmp=Path(d)
    view=tmp/'check.blade.php';view.write_text(changed,encoding='utf8',errors='surrogateescape')
    compiled=tmp/'compiled.php'
    compiler=tmp/'compile.php'
    compiler.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    cmd=subprocess.run(['php',str(compiler),str(root),str(view),str(compiled)],
                       text=True,capture_output=True,timeout=35)
    if cmd.returncode or not compiled.exists():
        raise SystemExit('AM194_STOP_BLADE_COMPILE '+cmd.stderr[-400:])
    check=subprocess.run(['php','-l',str(compiled)],capture_output=True,text=True,timeout=30)
    if check.returncode:
        raise SystemExit('AM194_STOP_BLADE_PHP_LINT '+(check.stdout+check.stderr)[-500:])
print('AM194_EXISTING_AM172_BACKEND_UNCHANGED True')
print('AM194_OLD_LARGE_CUSTOMER_NAME_BAR_REMOVED True')
print('AM194_SINGLE_COMPACT_BUTTON_IN_QUICK_ACTION_BAR True')
print('AM194_SYNC_AND_MANUAL_IN_POPUP True')
print('AM194_ADMIN_ONLY_VISIBILITY_AND_EXISTING_SERVER_AUTH True')
print('AM194_INVOICE_AUDIT_CUSTOMER_MASTER_PROTECTION_UNCHANGED True')
print('AM194_BLADE_COMPILE_AND_PHP_SYNTAX_PASS')
print('AM194_NO_DATABASE_ROUTES_OR_CONTROLLER_WRITES')
if mode=='check':
    print('AM194_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup.exists() or manifest.exists():
    raise SystemExit('AM194_STOP_BACKUP_CONFLICT')
backupdir.mkdir(parents=True,exist_ok=True)
os.chmod(backupdir,0o700)
shutil.copy2(target,backup);os.chmod(backup,0o600)
atomic(target,changed)
manifest.write_text(sha(changed)+'\n',encoding='ascii')
os.chmod(manifest,0o600)
print('AM194_CUSTOMER_NAME_CORRECTION_COMPACT_POPUP_INSTALLED')
print('AM194_RUN_PHP_ARTISAN_VIEW_CLEAR')
