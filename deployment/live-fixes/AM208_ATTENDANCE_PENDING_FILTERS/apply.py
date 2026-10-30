#!/usr/bin/env python3
"""AM208 - Staff Name/Code + date + missing checkout/checkin attendance filters.
STAFF/ERP ONLY. Existing AM130 Admin correction/HR audit/AM129 guard untouched.
Usage:
  python3 apply.py check /.../assam-erp-staging
  python3 apply.py apply /.../assam-erp-staging
  python3 apply.py rollback /.../assam-erp-staging
Check is read-only. Apply backs up controller and AM130 partial, lints/compiles
the candidate, then changes exactly the attendance query and register view.
"""
from pathlib import Path
import os,re,sys,hashlib,shutil,subprocess,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM208_USAGE python3 apply.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
partial=root/'resources/views/admin/native/partials/am130-missed-checkout.blade.php'
query=package/'controller-query.php.txt'
view=package/'filter-register.blade.php'
backup_dir=root/'storage/app/am-backups/AM208_ATTENDANCE_PENDING_FILTERS'
backup_controller=backup_dir/'AdminNativeModuleController.php.before_AM208'
backup_view=backup_dir/'am130-missed-checkout.blade.php.before_AM208'
manifest=backup_dir/'installed-sha256.txt'
marker='AM208_ATTENDANCE_PENDING_FILTERS'

def read(p):return p.read_text(encoding='utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,temp=tempfile.mkstemp(prefix='.am208_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
        os.chmod(temp,p.stat().st_mode & 0o777)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)

if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM208_STOP_NOT_STAGING')
if not all(p.is_file() for p in (controller,partial)):
    raise SystemExit('AM208_STOP_ATTENDANCE_SOURCE_MISSING')
old_controller=read(controller)
old_view=read(partial)
if mode=='rollback':
    if not all(p.is_file() for p in (backup_controller,backup_view,manifest)):
        raise SystemExit('AM208_STOP_ROLLBACK_BACKUP_MISSING')
    pairs=read(manifest).strip().splitlines()
    if len(pairs)!=2 or pairs != [sha(old_controller),sha(old_view)]:
        raise SystemExit('AM208_STOP_ROLLBACK_CURRENT_FILES_CHANGED_NO_OVERWRITE')
    atomic(controller,read(backup_controller))
    atomic(partial,read(backup_view))
    print('AM208_ROLLBACK_RESTORED_CONTROLLER_AND_ADMIN_REGISTER')
    print('AM208_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)

if '{{-- AM208_ATTENDANCE_PENDING_FILTERS:' in old_view:
    if marker in old_controller:
        print('AM208_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM208_STOP_PARTIAL_INSTALL_MANUAL_REVIEW')
if marker in old_controller:
    raise SystemExit('AM208_STOP_CONTROLLER_ONLY_PARTIAL_INSTALL')
if not all(p.is_file() for p in (query,view)):
    raise SystemExit('AM208_STOP_PATCH_FILES_MISSING')
if 'AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION' not in old_controller:
    raise SystemExit('AM208_STOP_AM130_AUDITED_CORRECTION_GUARD_MISSING')
if 'name="am130_checkout_time"' not in old_view or 'name="am130_reason"' not in old_view:
    raise SystemExit('AM208_STOP_AM130_ADMIN_CORRECTION_FORM_MISSING')
if 'AM130_MISSED_CHECKOUT_ADMIN_REGISTER' not in old_view:
    raise SystemExit('AM208_STOP_OLD_ADMIN_REGISTER_CHANGED')

# Replace ONLY AM130's previous-day unresolved list block.
# Every server-side financial/staff attendance correction method remains byte-for-byte unchanged.
old_pattern=re.compile(
    r'(?m)^[ \t]*// AM130_MISSED_CHECKOUT_ADMIN_REGISTER: global unresolved register;'
    r'[\s\S]{0,1450}?->limit\(100\)->get\(\);'
)
matches=list(old_pattern.finditer(old_controller))
if len(matches)!=1:
    raise SystemExit('AM208_STOP_AM130_LIST_QUERY_ANCHOR_COUNT_'+str(len(matches)))
found=matches[0]
old_block=found.group(0)
for x in ("$am130Base=DB::table('attendance as a')",
          "$am130PendingCount=(clone $am130Base)->count()",
          '$am130PendingRows=(clone $am130Base)',
          "->whereNotNull('a.check_in')->whereNull('a.check_out')",
          "'s.name as employee_name'",
          "'s.staff_code','s.department'"):
    if x not in old_block:raise SystemExit('AM208_STOP_OLD_QUERY_GUARD '+x)
new_block=read(query).rstrip()
if new_block.count(marker)!=1 or 'DB::table(' not in new_block:
    raise SystemExit('AM208_STOP_NEW_QUERY_INVALID')
new_controller=old_controller[:found.start()]+new_block+old_controller[found.end():]

# Extend the existing attendance view variables, without touching other
# controller methods or the currently-live audit correction branch.
old_compact="'am130PendingRows','am130PendingCount'"
if new_controller.count(old_compact)!=1:
    raise SystemExit('AM208_STOP_ATTENDANCE_COMPACT_VARIABLE_ANCHOR_CHANGED')
am208_vars="'am208Staff','am208Kind','am208From','am208To','am208CheckoutMatched','am208NoCheckinRows','am208NoCheckinCount'"
new_controller=new_controller.replace(old_compact,old_compact+','+am208_vars,1)
new_view=read(view)
for field in ("am208_staff","am208_kind","am208_from","am208_to",
              'id="am130-missed-checkout-register"',
              "route('erp.attendance.update'",
              'name="am130_checkout_time"',
              'name="am130_reason"',"@csrf",
              'name="am130_resolution"'):
    if field not in new_view:raise SystemExit('AM208_STOP_NEW_VIEW_MISSING_'+field)
if new_view.count('<form')!=2 or new_view.count('</form>')!=2:
    raise SystemExit('AM208_STOP_SEARCH_AND_POST_FORMS_INVALID')
if new_controller.count(marker)!=1:
    raise SystemExit('AM208_STOP_CONTROLLER_MARKER_COUNT')
if new_controller.count('AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION')!=old_controller.count('AM130_MISSED_CHECKOUT_ADMIN_AUDITED_CORRECTION'):
    raise SystemExit('AM208_STOP_AUDIT_CORRECTION_CHANGED')
if new_controller.count("ATTENDANCE_MISSED_CHECKOUT_RESOLVED")!=old_controller.count("ATTENDANCE_MISSED_CHECKOUT_RESOLVED"):
    raise SystemExit('AM208_STOP_AUDIT_ACTION_CHANGED')
if new_controller.count('am130_resolution')!=old_controller.count('am130_resolution'):
    raise SystemExit('AM208_STOP_CORRECTION_GUARDS_CHANGED')

# Strict client-visible form invariants: original admin POST unchanged.
def original_form(v):
    m=re.search(r'<form class="am130-form"[\s\S]*?</form>',v)
    return m.group(0) if m else None
if original_form(old_view) is None or original_form(old_view)!=original_form(new_view):
    raise SystemExit('AM208_STOP_ADMIN_CORRECTION_POST_FORM_CHANGED')

# PHP lint and Blade compile before any deployment write.
with tempfile.TemporaryDirectory(prefix='am208_preflight_') as t:
    temp=Path(t)
    candidate=temp/'AdminNativeModuleController.php'
    candidate.write_text(new_controller,encoding='utf8',errors='surrogateescape')
    res=subprocess.run(['php','-l',str(candidate)],cwd=root,
                       capture_output=True,text=True,timeout=25)
    if res.returncode:
        raise SystemExit('AM208_STOP_PHP_CONTROLLER_LINT '+(res.stdout+res.stderr)[-450:])
    test_view=temp/'view.blade.php'
    test_view.write_text(new_view,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php'
    compiled=temp/'compiled.php'
    compiler.write_text("""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    res=subprocess.run(['php',str(compiler),str(root),str(test_view),str(compiled)],
                       cwd=root,capture_output=True,text=True,timeout=35)
    if res.returncode or not compiled.is_file():
        raise SystemExit('AM208_STOP_BLADE_COMPILE '+res.stderr[-500:])
    res=subprocess.run(['php','-l',str(compiled)],cwd=root,
                       capture_output=True,text=True,timeout=25)
    if res.returncode:raise SystemExit('AM208_STOP_COMPILED_BLADE_LINT '+(res.stdout+res.stderr)[-500:])

print('AM208_EMPLOYEE_NAME_STAFF_CODE_FILTER_READY')
print('AM208_MISSING_CHECKOUT_BLOCKED_CHECKIN_NO_CHECKIN_TODAY_FILTER_READY')
print('AM208_DATE_FROM_TO_SERVER_FILTER_READY')
print('AM208_ALL_BACKLOG_COUNT_RETAINED_AND_FIRST_100_FILTERED')
print('AM208_ORIGINAL_SAVE_AUDIT_POST_FORM_UNCHANGED')
print('AM208_CHECKIN_GUARD_AND_EMPLOYEE_ATTENDANCE_RECORDS_UNCHANGED')
print('AM208_CONTROLLER_AND_BLADE_SYNTAX_PASS')
if mode=='check':
    print('AM208_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if backup_controller.exists() or backup_view.exists() or manifest.exists():
    raise SystemExit('AM208_STOP_EXISTING_BACKUP_CONFLICT')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(controller,backup_controller)
shutil.copy2(partial,backup_view)
os.chmod(backup_controller,0o600)
os.chmod(backup_view,0o600)
try:
    atomic(controller,new_controller)
    atomic(partial,new_view)
    manifest.write_text(sha(new_controller)+'\n'+sha(new_view)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
except BaseException:
    atomic(controller,old_controller)
    atomic(partial,old_view)
    if manifest.exists():manifest.unlink()
    raise SystemExit('AM208_WRITE_FAILURE_ROLLED_BACK')
print('AM208_ATTENDANCE_PENDING_FILTERS_INSTALLED')
print('AM208_RUN_PHP_ARTISAN_VIEW_CLEAR')
