#!/usr/bin/env python3
"""AM260 global keyboard entry, loaded wherever AM254 autosave script is present.
Modes check|apply|verify|rollback STAGING_ROOT. No DB modifications.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM260_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
old_asset='public/js/am254-global-form-autosave.js'
source_module=package/'files/public/js/am260-unified-keyboard-entry.js'
target=root/old_asset
marker='AM260_UNIFIED_KEYBOARD_ENTRY'
backups=root/'storage/app/am-backups'
pointer=backups/'AM260_KEYBOARD_LAST_BACKUP.txt'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file() or not target.is_file():
    raise SystemExit('STOP_AM260_NOT_AUTHORIZED_STAGING_ROOT')
if not source_module.is_file():
    raise SystemExit('STOP_AM260_MODULE_MISSING')

def sha(raw):return hashlib.sha256(raw).hexdigest()

def atomic(p,raw):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,path=tempfile.mkstemp(prefix='.am260_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(raw)
        os.chmod(path,(p.stat().st_mode&0o777) if p.is_file() else 0o644)
        os.replace(path,p)
    finally:
        if os.path.exists(path):os.unlink(path)

def cmd(args,timeout=90):
    return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=timeout)

def verify_baseline():
    for layout in ('resources/views/layouts/erp.blade.php',
                   'resources/views/layouts/new-admin.blade.php'):
        p=root/layout
        if not p.is_file() or p.read_text('utf8','replace').count('am254-global-draft-script')!=1:
            raise SystemExit('STOP_AM260_AM254_GLOBAL_INCLUSION_MISSING_'+layout)
    for page in ('resources/views/staff/inspection.blade.php',
                 'resources/views/staff/qc-review.blade.php'):
        p=root/page
        if not p.is_file() or p.read_text('utf8','replace').count('am254-global-draft-script')!=1:
            raise SystemExit('STOP_AM260_AM254_STANDALONE_STAFF_INCLUSION_MISSING_'+page)
    baseline=target.read_bytes()
    if baseline.count(b'AM254_GLOBAL_FORM_AUTOSAVE')!=1:
        raise SystemExit('STOP_AM260_AM254_BASE_AUTOSAVE_MARKER_DRIFT')
    return baseline

def verify():
    raw=verify_baseline()
    extra=source_module.read_bytes()
    if raw.count(marker.encode())!=1:
        raise SystemExit('STOP_AM260_MODULE_MARKER_COUNT_'+str(raw.count(marker.encode())))
    if not raw.endswith(extra):
        raise SystemExit('STOP_AM260_DEPLOYED_MODULE_MISMATCH')
    text=raw.decode('utf8','replace')
    for expected in ('document.addEventListener(\'keydown\',handleEnter,false)',
                     'if (isNativePicker(field)) return',
                     'if (event.key !== \'Enter\'',
                     'event.preventDefault()',
                     "var form = field.form || field.closest('form')"):
        if expected not in text:
            raise SystemExit('STOP_AM260_EXPECTED_SAFETY_CONTROL_MISSING_'+expected[:35])
    print('AM260_ADMIN_ERP_NEW_ADMIN_AND_STAFF_PORTAL_SHARED_MODULE_VERIFIED')
    print('AM260_EXISTING_AUTODRAFT_ITEM_PICKERS_AM257_AND_READY_ROWS_AM258_PRESERVED')
    print('AM260_NO_DATABASE_POST_GPS_PASSWORD_OR_PAYMENT_CHANGES')

if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM260_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM260_KEYBOARD_'):
        raise SystemExit('STOP_AM260_UNSAFE_BACKUP')
    meta=json.loads((backup/'manifest.json').read_text())
    if sha(target.read_bytes())!=meta['installed_sha']:
        raise SystemExit('STOP_AM260_SOURCE_CHANGED_AFTER_INSTALL_REFUSE_ROLLBACK')
    atomic(target,(backup/'am254-original.js').read_bytes())
    clear=cmd(['php','artisan','view:clear'],70)
    if clear.returncode:
        raise SystemExit('STOP_AM260_RESTORED_BUT_VIEW_CACHE_CLEAR_FAILED '+(clear.stdout+clear.stderr)[-500:])
    print('AM260_ROLLED_BACK_SHARED_KEYBOARD_ONLY_OLD_AUTOSAVE_RETAINED')
    sys.exit(0)

raw=verify_baseline()
module=source_module.read_bytes()
if marker.encode() in raw:raise SystemExit('STOP_AM260_ALREADY_INSTALLED_USE_VERIFY')
if module.count(marker.encode())!=1:raise SystemExit('STOP_AM260_NEW_MODULE_MARKER_NOT_UNIQUE')
if b'</script' in module.lower() or b'<script' in module.lower():
    raise SystemExit('STOP_AM260_MODULE_NOT_SAFE_TO_INCLUDE_INLINE')
if b'fetch(' in module or b'XMLHttpRequest' in module or b'requestSubmit(' in module:
    raise SystemExit('STOP_AM260_NETWORK_OR_SUBMIT_NOT_ALLOWED')
modified=raw.rstrip()+b'\n\n'+module
if not modified.endswith(module):raise SystemExit('STOP_AM260_APPEND_ERROR')
print('AM260_SHARED_AM254_LOADER_FOUND_AND_PRESERVED')
print('AM260_KEYBOARD_MODULE_APPEND_SOURCE_CHECK_PASS')
print('AM260_NO_DATABASE_Writes')
if mode=='check':
    print('AM260_READONLY_PREFLIGHT_PASS')
    sys.exit(0)

backup=backups/('AM260_KEYBOARD_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
backup_original=backup/'am254-original.js'
backup_original.write_bytes(raw);os.chmod(backup_original,0o600)
try:
    if target.read_bytes()!=raw:raise RuntimeError('AM254_CHANGED_SINCE_PREFLIGHT')
    atomic(target,modified)
    verify()
    result=cmd(['php','artisan','view:clear'],80)
    if result.returncode:raise RuntimeError('ARTISAN_VIEW_CACHE_CLEAR_FAILED '+(result.stdout+result.stderr)[-700:])
except Exception as e:
    atomic(target,raw)
    cmd(['php','artisan','view:clear'],80)
    raise SystemExit('STOP_AM260_APPLY_RESTORED_OLD_AUTOSAVE_'+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed_sha':sha(modified),'previous_sha':sha(raw)},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM260_UNIFIED_ENTER_NEXT_FIELD_INSTALLED_ALL_AM254_FORMS')
print('AM260_SOURCE_BACKUP',backup)
print('AM260_REAL_AUTHENTICATED_BROWSER_TEST_PENDING')
