#!/usr/bin/env python3
"""AM218 emergency restore for Staging JC 500 after AM217.
Compare current view byte-for-byte to output generated from AM217's stored
prepatch backup before restoring. Refuse overwrite if any later JC changes.
No database writes, no restoration of any other module.
"""
from pathlib import Path
import hashlib,subprocess,sys,os,shutil,tempfile,re,datetime
if len(sys.argv)!=3 or sys.argv[1] not in ('check','restore'):
    raise SystemExit('Usage: restore.py check|restore STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
view=root/'resources/views/job-cards/show.blade.php'
backup=root/'storage/app/am-backups/AM217_OSL_UI_CLEANUP/show.blade.php.before_AM217'
installer=Path.home()/'AM217_DEPLOY/apply.py'
here=Path(__file__).resolve().parent
for p in (root/'artisan',view,backup,installer):
    if not p.is_file():
        raise SystemExit('AM218_STOP_REQUIRED_FILE_MISSING_'+p.name)
current=view.read_bytes();previous=backup.read_bytes()
if current.count(b'AM217_OSL_UI_CLEANUP') != 1:
    raise SystemExit('AM218_STOP_CURRENT_VIEW_NOT_AM217_PATCH')
print('AM218_AM217_MARKER_CONFIRMED=YES')
print('AM218_PREPATCH_BACKUP_EXISTS=YES')
# Rebuild AM217 expected source from untouched backup using the actual
# AM217 installer and same assets on Staging; no served file modification.
src=installer.read_text('utf8')
needle="source=view.read_text('utf8',errors='surrogateescape')"
replacement="source=backup.read_text('utf8',errors='surrogateescape')"
if src.count(needle)!=1:
    raise SystemExit('AM218_STOP_CANNOT_REPLAY_AM217_PATCH_SOURCE')
src=src.replace(needle,replacement,1)
needle2="if mode=='check':\n    print('AM217_CHECK_PASS_NO_WRITES')"
if src.count(needle2)!=1:
    raise SystemExit('AM218_STOP_CANNOT_INTERCEPT_AM217_EXPECTED')
src=src.replace(needle2,
    "if mode=='check':\n    import hashlib\n    print('AM218_EXPECTED_SHA256='+hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest())\n    print('AM217_CHECK_PASS_NO_WRITES')",1)
with tempfile.NamedTemporaryFile(mode='w',suffix='.py',prefix='am218_replay_',dir=installer.parent,encoding='utf8',delete=False) as fh:
    fh.write(src);tmp=Path(fh.name)
try:
    check=subprocess.run(['python3',str(tmp),'check',str(root)],
         cwd=root,text=True,capture_output=True,timeout=120)
finally:
    tmp.unlink(missing_ok=True)
if check.returncode:
    print('AM218_REPLAY_FAILED='+check.stderr[-200:].replace('\n',' '))
    print('AM218_REPLAY_DETAILS='+check.stdout[-220:].replace('\n',' '))
    raise SystemExit('AM218_STOP_CANNOT_SAFELY_VERIFY_NO_LATER_CHANGES')
m=re.search(r'^AM218_EXPECTED_SHA256=([0-9a-f]{64})$',check.stdout,re.M)
if not m:
    raise SystemExit('AM218_STOP_AM217_EXPECTED_HASH_MISSING')
actual=hashlib.sha256(current).hexdigest()
expected=m.group(1)
print('AM218_POST_DEPLOY_VIEW_EXACT_MATCH='+('YES' if actual==expected else 'NO'))
if actual!=expected:
    raise SystemExit('AM218_STOP_NEWER_JOB_CARD_EDITS_FOUND_REQUIRES_TARGETED_REVERSE')
if mode=='check':
    print('AM218_RESTORE_PREFLIGHT_PASS_NO_WRITES')
    sys.exit(0)
# Saved current state as another independent rollback point; restoring
# exclusively the one view file then recompiling Laravel Blade.
saved=root/'storage/app/am-backups'/('AM218_BEFORE_EMERGENCY_RESTORE_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'.blade.php')
shutil.copy2(view,saved)
fd,staged=tempfile.mkstemp(prefix='.am218_',dir=view.parent)
try:
    with os.fdopen(fd,'wb') as w:w.write(previous)
    os.chmod(staged,view.stat().st_mode & 0o777)
    os.replace(staged,view)
finally:
    if os.path.exists(staged):os.unlink(staged)
for command in (['php','artisan','view:clear'],['php','artisan','view:cache']):
    run=subprocess.run(command,cwd=root,timeout=110,text=True,capture_output=True)
    if run.returncode:
        shutil.copy2(saved,view)
        subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True)
        raise SystemExit('AM218_STOP_BLADE_RECOMPILE_FAILED_PREVIOUS_VIEW_RESTORED '+run.stderr[-200:])
print('AM218_AM217_ONLY_VIEW_ROLLED_BACK=YES')
print('AM218_BLADE_CACHE_REBUILT=YES')
print('AM218_NO_DATABASE_WRITES=YES')
