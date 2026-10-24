#!/usr/bin/env python3
"""AM125 fix: hide original P55 alteration_reason native dropdown once AM124
searchable in-dialog picker is installed. STAGING ONLY; source-only/Blade patch.
Usage: python3 deploy.py check|apply /path/to/staging-erp
No data, route, model, controller, billing or ROT session writes.
"""
from pathlib import Path
import datetime
import os
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 deploy.py check|apply STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
source=root/'resources/views/job-cards/rot-alteration.blade.php'
if not (root/'artisan').is_file() or not source.is_file():
    raise SystemExit('AM125_STOP_NOT_STAGING_LARAVEL_OR_MISSING_VIEW')
original=source.read_text('utf8',errors='surrogateescape')
old_start="      // Keep native select focusable for HTML5 required-field validation.\n"
old_end="      function sync(){\n"
marker="AM125_HIDE_DUPLICATE_REASON_NATIVE"
if marker in original:
    print('AM125_ALREADY_APPLIED');sys.exit(0)
for name,present in {
    'AM116_DIALOG':'AM116_UNIFIED_ROT_UI' in original,
    'AM124_PICKER':'AM124_P55_REASON_PICKER' in original,
    'AM124_OLD_SELECT':original.count(old_start)==1,
    'AM124_END_ANCHOR':original.count(old_end)>=1,
    'AM124_CUSTOM_TRIGGER':'trigger.className=\'am124-reason-trigger\'' in original,
    'ORIGINAL_SERVER_FIELD':'name="alteration_reason"' in original,
}.items():
    print('AM125_PREFLIGHT_'+name+'='+('PASS' if present else 'FAIL'))
    if not present:raise SystemExit('AM125_SAFE_STOP_'+name+'_MISMATCH_NO_WRITES')
i=original.index(old_start)
j=original.index(old_end,i)
# Note: original required field is no longer used for *browser* validation because
# a display:none control can cause "invalid form control is not focusable".
# The original named select remains enabled and submitted, server validation
# continues to be authoritative, and guarded JS blocks empty Unlock/Save.
new=original[:i]+'''      // AM125_HIDE_DUPLICATE_REASON_NATIVE:
      // The AM124 searchable button is the ONLY visible Reason picker.
      // Keep original select name/options/value and submit it unchanged.
      select.style.setProperty('display','none','important');
      select.style.setProperty('visibility','hidden','important');
      select.tabIndex=-1;
      select.setAttribute('aria-hidden','true');
      // A CSS-hidden *required* select is not focusable by the browser.
      // Gate the required condition below AND at the Laravel backend.
      select.required=false;
      var row=select.closest('.p55-rot');
      var reasonForm=select.closest('form');
      function ensureReason(e){
        if(select.disabled || String(select.value||'').trim()!=='')return;
        if(e){e.preventDefault();e.stopImmediatePropagation();}
        openPicker(dialog,select,trigger);
      }
      if(reasonForm){
        reasonForm.addEventListener('submit',ensureReason,true);
      }
      if(row){
        row.addEventListener('click',function(event){
          var button=event.target.closest('button');
          if(!button)return;
          var label=String(button.textContent||'').toLowerCase();
          if(/unlock for alteration|save job card.*rot alteration/.test(label)){
            ensureReason(event);
          }
        },true);
      }
''' + original[j:]
if new.count(marker)!=1:
    raise SystemExit('AM125_SAFE_STOP_BAD_PATCH')
if not new.count("select.style.setProperty('display','none','important')")==1:
    raise SystemExit('AM125_SAFE_STOP_ORIGINAL_SELECT_HIDE_NOT_UNIQUE')
if mode=='check':
    print('AM125_PREFLIGHT_PASS_NO_WRITES')
    sys.exit(0)

backup=root/'storage/app/am-backups'/(
    'AM125_rot_alteration_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')
    +'_'+str(os.getpid())+'.blade.php'
)
backup.parent.mkdir(parents=True,exist_ok=True)
shutil.copy2(source,backup)
print('AM125_BACKUP='+str(backup))

def rollback(reason):
    shutil.copy2(backup,source)
    subprocess.run(['php','artisan','view:clear'],cwd=root,
                   stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    raise SystemExit('AM125_FAILED_ROLLBACK_DONE='+str(reason))

try:
    fd,tmp=tempfile.mkstemp(prefix='.am125_',dir=source.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(new)
        os.chmod(tmp,source.stat().st_mode & 0o777)
        os.replace(tmp,source)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        p=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=100)
        print((p.stdout or p.stderr)[-280:])
        if p.returncode:raise RuntimeError('Blade command failed: '+' '.join(cmd))
    found=[]
    for file in (root/'storage/framework/views').glob('*.php'):
        compiled=file.read_text('utf8',errors='replace')
        if 'AM125_HIDE_DUPLICATE_REASON_NATIVE' not in compiled:continue
        if "select.style.setProperty('display','none','important')" not in compiled:
            raise RuntimeError('Compiled AM125 picker missing hidden native select')
        p=subprocess.run(['php','-l',str(file)],cwd=root,capture_output=True,text=True,timeout=20)
        if p.returncode:raise RuntimeError('Compiled PHP error '+p.stderr[-260:])
        found.append(str(file))
    if len(found)!=1:raise RuntimeError('AM125 compiled picker count='+str(len(found)))
    print('AM125_COMPILED_HIDE_OLD_REASON_PHP_PASS='+found[0])
except BaseException as exc:
    rollback(exc)
print('AM125_STAGING_SOURCE_AND_BLADE_PASS')
print('AM125_BACKEND_AND_DB_UNCHANGED')
print('AM125_BROWSER_TEST_PENDING')
