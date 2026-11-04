#!/usr/bin/env python3
"""AM238 — repair Enter after a selected Job Card in Purchase Entry.

Patches only the existing AM234 JC picker key handler in the Laravel source
and verified browser-served JS file. Changes cache-busting script URL in Blade.
Backups and rollback included. NEVER posts purchases or mutates DB.
"""
from pathlib import Path
import os,re,sys,shutil,tempfile,datetime
if len(sys.argv) not in (3,4) or sys.argv[1] not in ('check','apply','rollback'):
 raise SystemExit('Usage: python3 apply.py check|apply STAGING_ROOT [BACKUP_FOR_ROLLBACK]')
mode=sys.argv[1];root=Path(sys.argv[2]).resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
 raise SystemExit('AM238_INVALID_STAGING')
source=root/'public/js/purchase-entry.js'
served=root.parent/'public_html/staging/js/purchase-entry.js'
# Hostinger's actual webroot is domains/assammotors.com/public_html/staging.
served=root.parent/'public_html/staging/js/purchase-entry.js'
view=root/'resources/views/purchases/create.blade.php'
paths={'source.js':source,'web.js':served,'create.blade.php':view}
for k,p in paths.items():
 if not p.is_file():raise SystemExit('AM238_REQUIRED_FILE_MISSING '+k+':'+str(p))
backup_root=root/'storage/app/am-backups'
if mode=='rollback':
 if len(sys.argv)!=4:raise SystemExit('AM238_BACKUP_REQUIRED')
 backup=Path(sys.argv[3]).resolve()
 if backup.parent!=backup_root or not backup.name.startswith('AM238_JC_ENTER_'):
  raise SystemExit('AM238_INVALID_BACKUP')
 for name,p in paths.items():
  if not (backup/name).is_file():raise SystemExit('AM238_BACKUP_INCOMPLETE '+name)
 for name,p in paths.items():shutil.copy2(backup/name,p)
 print('AM238_ROLLBACK_PASS');sys.exit(0)

original={k:p.read_text('utf8','surrogateescape') for k,p in paths.items()}
marker='AM238_JC_ENTER_SAFE_NEXT_LINE'
if any(marker in original[k] for k in ('source.js','web.js')):
 print('AM238_ALREADY_APPLIED');sys.exit(0)
old="""      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (menu.hidden) show();
        if (visible[active]) choose(visible[active]);
        else { input.classList.add('invalid'); input.setCustomValidity('Select an existing Job Card'); }
      }
"""
new="""      } else if (e.key === 'Enter') {
        e.preventDefault();
        // AM238_JC_ENTER_SAFE_NEXT_LINE:
        // Enter on an already selected JC must never reselect the first suggestion.
        // Complete the current purchase row and focus the next Part Search.
        if (select.value && selectedLabel && input.value === selectedLabel) {
          close();
          finishRow(row);
          return;
        }
        if (menu.hidden) show();
        if (visible[active]) choose(visible[active]);
        else { input.classList.add('invalid'); input.setCustomValidity('Select an existing Job Card'); }
      }
"""
for name in ('source.js','web.js'):
 js=original[name]
 if 'AM234_PURCHASE_JC_PICKER_V2' not in js or js.count(old)!=1:
  raise SystemExit('AM238_PICKER_SOURCE_CONTRACT_FAILED '+name)
# Preserve other scripts/CSS in each file: patch only this exact handler.
modified={name:original[name].replace(old,new,1) for name in ('source.js','web.js')}
pattern=r"(asset\('js/purchase-entry\.js'\) \}\}\?v=)\d+"
timestamp=datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%d%H%M%S')
updated,n=re.subn(pattern,lambda m:m.group(1)+'238'+timestamp,original['create.blade.php'])
if n!=1:raise SystemExit('AM238_VIEW_CACHE_VERSION_NOT_FOUND')
modified['create.blade.php']=updated
for name in ('source.js','web.js'):
 if modified[name].count(marker)!=1:raise SystemExit('AM238_BAD_OUTPUT '+name)
print('AM238_PREFLIGHT_PASS')
print('AM238_JS_COPIES_PATCHED_2')
if mode=='check':
 print('AM238_CHECK_NO_WRITES');sys.exit(0)
backup=backup_root/('AM238_JC_ENTER_'+timestamp)
backup.mkdir(mode=0o750,parents=True,exist_ok=False)
for k,p in paths.items():shutil.copy2(p,backup/k)
try:
 for k,p in paths.items():
  fd,tmp=tempfile.mkstemp(dir=str(p.parent),prefix='.am238_',suffix='.tmp')
  try:
   with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(modified[k])
   os.chmod(tmp,p.stat().st_mode&0o777)
   os.replace(tmp,p)
  finally:
   if os.path.exists(tmp):os.unlink(tmp)
except Exception:
 for k,p in paths.items():shutil.copy2(backup/k,p)
 raise
print('AM238_APPLIED')
print('AM238_BACKUP='+str(backup))
print('AM238_JS_HANDLER_ONLY_NO_DB_WRITES')
