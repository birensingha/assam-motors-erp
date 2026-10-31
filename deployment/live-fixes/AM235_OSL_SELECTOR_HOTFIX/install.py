#!/usr/bin/env python3
"""AM235 hotfix for AM232 purchase OSL selector, STAGING only.
check|apply|rollback STAGING_ROOT. Recompile inline Blade JS. No financial writes.
"""
from pathlib import Path
import sys,os,tempfile,subprocess,hashlib
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):raise SystemExit('AM235_USAGE')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():raise SystemExit('AM235_STOP_NOT_STAGING')
p=root/'public/js/am232-osl-purchase.js'
source=Path(__file__).resolve().parent/'purchase-selector.js'
backup=root/'storage/app/am-backups/AM235_OSL_SELECTOR_FIX'
oldBackup=backup/'selector.before'
after=backup/'installed.sha'
def read(f):return f.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8','surrogateescape')).hexdigest()
def atomic(f,s):
 fd,t=tempfile.mkstemp(prefix='.am235_',dir=f.parent)
 try:
  with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as h:h.write(s)
  os.chmod(t,f.stat().st_mode&0o777)
  os.replace(t,f)
 finally:
  if os.path.exists(t):os.unlink(t)
if not p.is_file() or not source.is_file():raise SystemExit('AM235_STOP_SOURCE_MISSING')
old=read(p);new=read(source)
if mode=='rollback':
 if not oldBackup.is_file() or not after.is_file() or sha(old)!=after.read_text().strip():
  raise SystemExit('AM235_STOP_ROLLBACK_SOURCE_CHANGED')
 atomic(p,read(oldBackup))
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
 subprocess.run(['php','artisan','view:cache'],cwd=root,capture_output=True,timeout=80)
 print('AM235_ROLLBACK_PASS');sys.exit(0)
if old==new:print('AM235_ALREADY_INSTALLED');sys.exit(0)
if old.count("match.type='button'")!=1 or new.count("match.type='button'")!=0:
 raise SystemExit('AM235_STOP_EXPECTED_SELECT_TYPE_SOURCE_MISMATCH')
if 'AM232_OSL_JOB_LINK_SELECTOR' not in old or 'AM232_OSL_JOB_LINK_SELECTOR' not in new:
 raise SystemExit('AM235_STOP_PATCH_MARKER')
if "if(!match.value){" not in new:raise SystemExit('AM235_STOP_LINK_RESET_MISSING')
with tempfile.NamedTemporaryFile('w',suffix='.js',delete=False,encoding='utf8') as f:
 f.write(new);tmp=f.name
try:
 node=__import__('shutil').which('node')
 if node:
  r=subprocess.run([node,'--check',tmp],cwd=root,text=True,capture_output=True,timeout=30)
  if r.returncode:raise SystemExit('AM235_STOP_JS_LINT '+r.stderr[-300:])
finally:os.unlink(tmp)
print('AM235_OSL_SELECTOR_JS_PREFLIGHT_PASS')
if mode=='check':print('AM235_CHECK_ONLY');sys.exit(0)
if backup.exists() or read(p)!=old:raise SystemExit('AM235_STOP_CONCURRENT_SOURCE_OR_BACKUP')
backup.mkdir(parents=True,mode=0o700)
oldBackup.write_text(old,encoding='utf8',errors='surrogateescape');os.chmod(oldBackup,0o600)
try:
 atomic(p,new)
 after.write_text(sha(new),encoding='ascii');os.chmod(after,0o600)
 for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
  r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=80)
  if r.returncode:raise RuntimeError('AM235_CACHE_FAIL '+r.stderr[-350:])
except BaseException as e:
 atomic(p,old)
 subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=30)
 raise SystemExit('AM235_APPLY_FAILED_ROLLED_BACK '+str(e))
print('AM235_OSL_SELECTOR_HOTFIX_STAGING_APPLIED_NO_DB_WRITES')
