#!/usr/bin/env python3
"""AM240 — expose existing audited OSL Purchase editor from Purchase Register.
Edits ONLY one Blade view and creates a backup. No financial/data posting changes.
Usage: python3 apply.py check|apply APP_ROOT
       python3 apply.py rollback APP_ROOT BACKUP_DIR
"""
from pathlib import Path
import sys,os,shutil,tempfile,datetime,re
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: apply.py check|apply ROOT [BACKUP]')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM240_STOP_NOT_STAGING')
path=root/'resources/views/purchases/index.blade.php'
if not path.is_file():raise SystemExit('AM240_STOP_REGISTER_NOT_FOUND')
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM240_STOP_MISSING_BACKUP')
 b=Path(sys.argv[3]).resolve()
 if b.parent!=root/'storage/app/am-backups' or not b.name.startswith('AM240_REGISTER_OSL_EDIT_') or not (b/'index.blade.php').is_file():
  raise SystemExit('AM240_STOP_BAD_BACKUP')
 shutil.copy2(b/'index.blade.php',path)
 print('AM240_ROLLBACK_COMPLETE')
 sys.exit(0)
view=path.read_text('utf8','surrogateescape')
marker='AM240_OSL_EDIT_IN_PURCHASE_REGISTER'
if marker in view:
 print('AM240_ALREADY_INSTALLED')
 sys.exit(0)
old='''        @if(strtoupper((string)$r['purchase_type']) === 'PART')
            <a class="btn small primary" href="{{ route('erp.purchases.edit',$r['id']) }}">✏ Edit</a>
        @endif'''
new='''        @if(strtoupper((string)$r['purchase_type']) === 'PART')
            <a class="btn small primary" href="{{ route('erp.purchases.edit',$r['id']) }}">✏ Edit</a>
        @elseif(strtoupper((string)$r['purchase_type']) === 'OSL')
            {{-- AM240_OSL_EDIT_IN_PURCHASE_REGISTER: use existing audited OSL purchase correction route. --}}
            <a class="btn small primary" href="{{ route('erp.fix48.osl-edit',$r['id']) }}" aria-label="Edit OSL Purchase {{ $r['purchase_batch_no'] ?: $r['purchase_no'] }}">✏ Edit</a>
        @endif'''
if view.count(old)!=1:
 raise SystemExit('AM240_STOP_REGISTER_EDIT_BRANCH_CHANGED')
out=view.replace(old,new,1)
if out.count(marker)!=1 or out.count("route('erp.fix48.osl-edit'")!=1:
 raise SystemExit('AM240_STOP_OUTPUT_VALIDATION')
print('AM240_PREFLIGHT_PASS')
if mode=='check':
 print('AM240_NO_FILES_CHANGED')
 sys.exit(0)
b=root/'storage/app/am-backups'/('AM240_REGISTER_OSL_EDIT_'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d_%H%M%S'))
b.mkdir(parents=True,exist_ok=False,mode=0o750)
shutil.copy2(path,b/'index.blade.php')
tmp=None
try:
 with tempfile.NamedTemporaryFile(mode='w',encoding='utf8',errors='surrogateescape',dir=path.parent,prefix='.am240_register_',delete=False) as f:
  f.write(out)
  tmp=Path(f.name)
 os.chmod(tmp,path.stat().st_mode&0o777)
 os.replace(tmp,path)
except Exception:
 if tmp is not None:tmp.unlink(missing_ok=True)
 shutil.copy2(b/'index.blade.php',path)
 raise
print('AM240_APPLIED')
print('AM240_BACKUP='+str(b))
print('AM240_PURCHASE_REGISTER_VIEW_ONLY_NO_ACCOUNTING_WRITES')
