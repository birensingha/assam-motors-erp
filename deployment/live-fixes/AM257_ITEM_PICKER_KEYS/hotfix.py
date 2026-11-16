#!/usr/bin/env python3
"""AM257 safety refinement: no arbitrary Estimate Master on blank Tab/Enter;
no accidental Purchase form submission when Enter is pressed on blank search.
check|apply|verify|rollback STAGING_ROOT. No business data writes.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
 raise SystemExit('AM257_HOTFIX_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
files=['public/js/estimate-entry.js','public/js/purchase-entry.js','public/js/purchase-edit.js']
marker='AM257_SAFE_EMPTY_ENTER'
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM257_SAFE_ENTER_BACKUP.txt'
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('STOP_AM257_SAFE_ENTER_NOT_STAGING')
def sha(x):return hashlib.sha256(x).hexdigest()
def atomic(p,b):
 fd,tmp=tempfile.mkstemp(prefix='.am257_safe_',dir=p.parent)
 try:
  with os.fdopen(fd,'wb') as out:out.write(b)
  os.chmod(tmp,p.stat().st_mode&0o777)
  os.replace(tmp,p)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)
def verify():
 for path in files:
  p=root/path
  if not p.is_file() or p.read_text('utf8','replace').count(marker)!=1:
   raise SystemExit('STOP_AM257_SAFE_ENTER_VERIFY_'+path)
 print('AM257_NO_ARBITRARY_MASTER_SELECT_AND_NO_EMPTY_ENTER_SUBMIT_VERIFIED')
 if any('commitAndNext(' in (root/p).read_text('utf8','replace') for p in files[:1]):
  raise SystemExit('STOP_AM257_ESTIMATE_NEXT_ROW_OLD_CODE_PRESENT')
 print('AM257_SAFE_ENTER_SOURCE_VERIFIED_NO_BUSINESS_WRITES')
if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
 if not pointer.is_file():raise SystemExit('STOP_AM257_NO_SAFETY_BACKUP')
 back=Path(pointer.read_text().strip()).resolve()
 if backupbase.resolve() not in back.parents or not back.name.startswith('AM257_SAFE_ENTER_'):
  raise SystemExit('STOP_AM257_BACKUP_PATH_INVALID')
 manifest=json.loads((back/'manifest.json').read_text())
 for p,h in manifest['installed'].items():
  if not (root/p).is_file() or sha((root/p).read_bytes())!=h:
   raise SystemExit('STOP_AM257_SOURCE_CHANGED_NO_ROLLBACK_'+p)
 for p in files:atomic(root/p,(back/p).read_bytes())
 print('AM257_SAFE_ENTER_SOURCE_ROLLBACK_NO_BUSINESS_WRITES')
 sys.exit(0)
before={}
patched={}
for p in files:
 path=root/p
 if not path.is_file():raise SystemExit('STOP_AM257_REQUIRED_JS_MISSING_'+p)
 original=path.read_bytes();body=original.decode('utf8','surrogateescape')
 if 'AM257_PICKER_ENTER_TAB' not in body or marker in body:
  raise SystemExit('STOP_AM257_SAFETY_PRECONDITION_'+p)
 if p.endswith('estimate-entry.js'):
  old='const item = isOpen ? selectedOptionItem(tr) : (!alreadySelected ? firstMatchForInput(tr) : null);'
  new='''// AM257_SAFE_EMPTY_ENTER: blank Reference must never silently pick first Master.
const item = isOpen ? selectedOptionItem(tr) :
  (!alreadySelected && input.value.trim().length >= 2 ? firstMatchForInput(tr) : null);'''
 elif p.endswith('purchase-entry.js'):
  old='if (!search.value.trim()) return; // Native Tab on a truly empty row.'
  new='''// AM257_SAFE_EMPTY_ENTER: blank Enter cannot submit a Purchase.
  if (!search.value.trim()) {
    if (!isTab) { e.preventDefault(); e.stopPropagation(); }
    return; // Native Tab only on an empty line.
  }'''
 else:
  old='if (!search.value.trim()) return;'
  new='''// AM257_SAFE_EMPTY_ENTER: blank Enter cannot submit a Purchase.
  if (!search.value.trim()) {
    if (!isTab) { e.preventDefault(); e.stopPropagation(); }
    return;
  }'''
 if body.count(old)!=1:raise SystemExit('STOP_AM257_SAFETY_ANCHOR_DRIFT_'+p)
 before[p]=original
 patched[p]=body.replace(old,new,1).encode('utf8','surrogateescape')
print('AM257_SAFE_EMPTY_ENTER_PREFLIGHT_PASS')
if mode=='check':print('AM257_SAFE_ENTER_CHECK_READ_ONLY');sys.exit(0)
back=backupbase/('AM257_SAFE_ENTER_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
back.mkdir(parents=True,exist_ok=False);os.chmod(back,0o700)
for p,b in before.items():
 dest=back/p;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(b);os.chmod(dest,0o600)
written=[]
try:
 for p,b in patched.items():
  if (root/p).read_bytes()!=before[p]:raise RuntimeError('JS_CHANGED_'+p)
  atomic(root/p,b);written.append(p)
 verify()
except Exception as err:
 for p in written:atomic(root/p,before[p])
 raise SystemExit('STOP_AM257_SAFE_ENTER_RESTORED_JS '+str(err))
(back/'manifest.json').write_text(json.dumps({'installed':{p:sha(x) for p,x in patched.items()}},indent=2))
os.chmod(back/'manifest.json',0o600)
pointer.write_text(str(back)+'\n');os.chmod(pointer,0o600)
print('AM257_SAFE_EMPTY_ENTER_AND_NO_UNINTENDED_PART_SELECTION_INSTALLED')
