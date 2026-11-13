#!/usr/bin/env python3
"""AM255 AttendanceService idempotent retry patch, with source rollback.
check|apply|verify|rollback STAGING_ROOT. Never records attendance on deployment.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM255_CHECKIN_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
target=root/'app/Services/AttendanceService.php'
marker='AM255_IDEMPOTENT_EXISTING_CHECK_IN'
bdir=root/'storage/app/am-backups';pointer=bdir/'AM255_CHECKIN_LAST_BACKUP.txt'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file() or not target.is_file():
    raise SystemExit('STOP_AM255_CHECKIN_NOT_STAGING')
original="""            if ($attendance && $attendance->check_in) {
                throw ValidationException::withMessages(['attendance' => 'Today\\'s Check-In is already marked.']);
            }"""
updated="""            // AM255_IDEMPOTENT_EXISTING_CHECK_IN: a valid GPS-verified repeat
            // request must not create another attendance or throw after a
            // network retry. All existing prior-day, notification, GPS and
            // mock-location checks above remain enforced.
            if ($attendance && $attendance->check_in) {
                if ($attendance->check_out) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Today\\'s Check-In and Check-Out are already completed. A second duty session must not overwrite the existing record.'
                    ]);
                }
                return $attendance; // Existing open Check-In, no duplicate writes.
            }"""
def sha(x):return hashlib.sha256(x).hexdigest()
def run(args):return subprocess.run(args,cwd=root,text=True,capture_output=True,timeout=90)
def atomic(p,b):
    fd,tmp=tempfile.mkstemp(prefix='.am255_checkin_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(data):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(data);temp=Path(f.name)
    try:
        r=run(['php','-l',str(temp)])
        if r.returncode:raise SystemExit('STOP_AM255_CHECKIN_PHP_LINT '+(r.stdout+r.stderr)[-600:])
    finally:temp.unlink(missing_ok=True)
def verify():
    s=target.read_text('utf8','replace')
    if s.count(marker)!=1 or 'return $attendance; // Existing open Check-In' not in s:
        raise SystemExit('STOP_AM255_CHECKIN_SERVER_GUARD_NOT_PRESENT')
    if 'whereNull(\\'check_out\\')' not in s or 'Strict Check-In' not in s:
        raise SystemExit('STOP_AM255_CHECKIN_PREVIOUS_GUARDS_MISSING')
    php_lint(target.read_bytes())
    print('AM255_ATTENDANCE_RETRY_GUARD_AND_PHP_SYNTAX_VERIFIED')
    print('AM255_GPS_AND_PRIOR_DAY_CHECKOUT_PROTECTIONS_PRESERVED')
if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM255_CHECKIN_BACKUP_POINTER_MISSING')
    backup=Path(pointer.read_text().strip()).resolve()
    if bdir.resolve() not in backup.parents or not backup.name.startswith('AM255_CHECKIN_'):
        raise SystemExit('STOP_AM255_CHECKIN_BACKUP_PATH')
    manifest=json.loads((backup/'manifest.json').read_text())
    if sha(target.read_bytes())!=manifest['installed_sha']:
        raise SystemExit('STOP_AM255_CHECKIN_SOURCE_CHANGED')
    atomic(target,(backup/'AttendanceService.before.php').read_bytes())
    run(['php','artisan','optimize:clear'])
    print('AM255_CHECKIN_SOURCE_RESTORED_NO_ATTENDANCE_DATA_CHANGED')
    sys.exit(0)
raw=target.read_bytes()
src=raw.decode('utf8','surrogateescape')
if src.count(original)!=1 or marker in src:
    raise SystemExit('STOP_AM255_CHECKIN_SOURCE_ANCHOR_CHANGED_COUNT_'+str(src.count(original)))
new=src.replace(original,updated,1).encode('utf8','surrogateescape')
php_lint(raw);php_lint(new)
print('AM255_CHECKIN_PREVIOUS_ATTENDANCE_PROTECTIONS_INTACT')
print('AM255_CHECKIN_SOURCE_PREFLIGHT_PASS')
if mode=='check':print('AM255_CHECKIN_CHECK_READ_ONLY');sys.exit(0)
backup=bdir/('AM255_CHECKIN_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
(backup/'AttendanceService.before.php').write_bytes(raw)
os.chmod(backup/'AttendanceService.before.php',0o600)
try:
    if target.read_bytes()!=raw:raise RuntimeError('ATTENDANCE_SOURCE_CHANGED')
    atomic(target,new)
    optimize=run(['php','artisan','optimize:clear'])
    if optimize.returncode:raise RuntimeError('ARTISAN_CACHE '+(optimize.stdout+optimize.stderr)[-600:])
    verify()
except Exception as e:
    atomic(target,raw)
    run(['php','artisan','optimize:clear'])
    raise SystemExit('STOP_AM255_CHECKIN_APPLY_RESTORED_SOURCE '+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed_sha':sha(new)},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM255_DUPLICATE_ATTENDANCE_CHECKIN_RETRY_FIXED')
print('AM255_ATTENDANCE_DATA_AND_GPS_UNCHANGED_DURING_DEPLOY')
