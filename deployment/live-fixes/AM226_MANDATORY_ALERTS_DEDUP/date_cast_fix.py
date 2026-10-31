#!/usr/bin/env python3
"""AM226 follow-up: Eloquent Attendance date cast must compare YYYY-MM-DD keys.
STAGING source-only, read-only check / guarded apply / safe rollback.
Never changes attendance or reminder rows directly.
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM226_DATE_USAGE date_cast_fix.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM226_DATE_STOP_NOT_STAGING')
source=root/'app/Services/StaffReminderEngine.php'
bakdir=root/'storage/app/am-backups/AM226_DATE_CAST_FIX'
bak=bakdir/'StaffReminderEngine.php.before_AM226_DATE'
manifest=bakdir/'installed-sha256.txt'
marker='AM226_DATE_CAST_FIX'
before="->whereIn('date',$dates)->get()->keyBy(fn($a)=>(string)$a->date);"
after="->whereIn('date',$dates)->get()->keyBy(fn($a)=>substr((string)$a->getRawOriginal('date'),0,10));"
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am226date_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not source.is_file():raise SystemExit('AM226_DATE_STOP_SOURCE_MISSING')
old=read(source)
if mode=='rollback':
    if not bak.is_file() or not manifest.is_file():raise SystemExit('AM226_DATE_STOP_NO_BACKUP')
    if read(manifest).strip()!=sha(old):raise SystemExit('AM226_DATE_STOP_SOURCE_CHANGED')
    atomic(source,read(bak))
    print('AM226_DATE_ROLLBACK_SOURCE_RESTORED');sys.exit(0)
if marker in old:
    if after in old:
        print('AM226_DATE_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM226_DATE_STOP_MARKER_INCONSISTENT')
if 'AM226_MANDATORY_ALERTS_DEDUP' not in old or old.count(before)!=1:
    raise SystemExit('AM226_DATE_STOP_RECONCILIATION_SOURCE_CHANGED_'+str(old.count(before)))
if 'private function reconcileHistoricalVerifiedActions' not in old:
    raise SystemExit('AM226_DATE_STOP_RECONCILIATION_METHOD_MISSING')
updated=old.replace(before,
    '// '+marker+': use raw ISO date, not cast Carbon string.\n            '+after,1)
if updated.replace('// '+marker+': use raw ISO date, not cast Carbon string.\n            '+after,before,1)!=old:
    raise SystemExit('AM226_DATE_STOP_UNEXPECTED_SOURCE_DIFF')
with tempfile.TemporaryDirectory(prefix='am226date_qa_') as directory:
    candidate=Path(directory)/'StaffReminderEngine.php'
    candidate.write_text(updated,encoding='utf8',errors='surrogateescape')
    r=subprocess.run(['php','-l',str(candidate)],cwd=root,text=True,capture_output=True,timeout=25)
    if r.returncode:raise SystemExit('AM226_DATE_STOP_PHP_LINT '+(r.stdout+r.stderr)[-450:])
print('AM226_DATE_RAW_YYYY_MM_DD_KEY_MAPPING_PASS')
print('AM226_DATE_PHP_SOURCE_LINT_PASS')
if mode=='check':
    print('AM226_DATE_CHECK_PASS_NO_WRITES');sys.exit(0)
if bak.exists() or manifest.exists():raise SystemExit('AM226_DATE_STOP_BACKUP_COLLISION')
bakdir.mkdir(parents=True,exist_ok=True);os.chmod(bakdir,0o700)
shutil.copy2(source,bak);os.chmod(bak,0o600)
try:
    atomic(source,updated)
    manifest.write_text(sha(updated)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
except BaseException:
    atomic(source,old)
    if manifest.exists():manifest.unlink()
    raise SystemExit('AM226_DATE_APPLY_ROLLED_BACK')
print('AM226_DATE_CAST_FIX_STAGING_INSTALLED')
print('AM226_DATE_VERIFIED_ATTENDANCE_ALERTS_RECHECK_NEXT_SCHEDULER_TICK')
