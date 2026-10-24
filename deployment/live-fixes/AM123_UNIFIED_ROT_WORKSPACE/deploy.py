#!/usr/bin/env python3
"""AM123 – Mount existing P55 Admin Full ROT Correction and FIX82 billing form in
the Main Job Card ROT panel. STAGING ONLY, view-only.
No routes/controllers/DB writes, no new billing endpoints.
Usage: python3 deploy.py check|apply STAGING_ROOT
"""
from pathlib import Path
import datetime
import os
import re
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 deploy.py check|apply STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).resolve()
package=Path(__file__).resolve().parent
payload=package/'unified-correction.blade.php'
show=root/'resources/views/job-cards/show.blade.php'
p55=root/'resources/views/job-cards/rot-alteration.blade.php'
partial=root/'resources/views/job-cards/partials/am123-unified-correction.blade.php'
controller=root/'app/Http/Controllers/Web/JobCardRotAlterationController.php'
routes=root/'routes/web.php'
for p in (root/'artisan',payload,show,p55,controller,routes):
    if not p.is_file():raise SystemExit('AM123_STOP_MISSING_'+str(p))

old=show.read_text('utf8',errors='surrogateescape')
p55text=p55.read_text('utf8',errors='surrogateescape')
r=routes.read_text('utf8',errors='surrogateescape')
frame=payload.read_text('utf8',errors='surrogateescape')
marker='AM123_UNIFIED_ROT_WORKSPACE'
if marker in old or partial.exists():
    if partial.exists() and "@include('job-cards.partials.am123-unified-correction')" in old:
        print('AM123_ALREADY_APPLIED');sys.exit(0)
    raise SystemExit('AM123_STOP_PARTIAL_PATCH')
checks={
  'P55_EXISTING_FORM': 'data-p55-form' in p55text,
  'P55_IS_ORIGINAL_AUDITED_EDITOR': 'p55-rot-edit' in p55text,
  'P55_HAS_AM116_DIALOG_SUPPORT': 'AM116_UNIFIED_ROT_UI' in p55text,
  'P55_ROWS_COLLECTION': 'p55Rows' in p55text,
  'P55_ROUTE': "name('erp.job-cards.labour.alter')" in r,
  'MAIN_JC_HAS_ROWS': '$am83RotLabour' in old,
  'MAIN_JC_HAS_ROT_CARDS': 'data-am90-labour-id' in old and 'rot-list' in old,
  'MAIN_JC_HAS_BILLING_FORM': 'am82-finance-form' in old,
  'MAIN_JC_HAS_FINANCE_ROUTE': "route('erp.fix48.job-line'" in old,
  'MAIN_JC_HAS_WORK_CONTROLS': 'rot-time-editor' in old,
  'MAIN_JC_HAS_VOID': 'AM121_COMPLETED_ROT_VOID' in old,
  'MAIN_JC_HAS_DELETE': 'AM117:' in old,
  'FRAGMENT_SINGLE_MARKER': frame.count(marker)==1,
  'FRAGMENT_CSF_REQUIRED': 'data-p55-form' not in frame and "make('job-cards.rot-alteration'" in frame and 'Full ROT Correction unavailable' in frame,
}
for key,val in checks.items():
    print('AM123_PREFLIGHT_'+key+'='+('PASS' if val else 'FAIL'))
if not all(checks.values()):
    raise SystemExit('AM123_STOP_GUARD_FAILED_NO_CHANGES')

# Insert AFTER the ONE actual ROT list. Never put this inside the per-ROT
# @forelse loop, which would duplicate forms and collide with IDs.
pattern=re.compile(r'(@empty<div class="empty">No Labour/ROT lines on this Job Card\.</div>\s*@endforelse\s*</div>)',re.M)
matches=list(pattern.finditer(old))
print('AM123_ASSIGNMENT_LIST_ANCHORS='+str(len(matches)))
if len(matches)!=1:
    raise SystemExit('AM123_STOP_ROT_LIST_CLOSING_ANCHOR_CHANGED')
anchor=matches[0]
addon="\n  {{-- AM123_UNIFIED_ROT_WORKSPACE: reuse audited Admin P55 form on Main JC --}}\n  @include('job-cards.partials.am123-unified-correction')"
new=old[:anchor.end()]+addon+old[anchor.end():]
if new.count('AM123_UNIFIED_ROT_WORKSPACE')!=1:
    raise SystemExit('AM123_STOP_BAD_SHOW_INSERTION')
if mode=='check':
    print('AM123_MAIN_JC_READY_FOR_VIEW_ONLY_PATCH_NO_WRITES')
    sys.exit(0)

backup_dir=root/'storage/app/am-backups'
backup_dir.mkdir(parents=True,exist_ok=True)
backup=backup_dir/('AM123_show_'+datetime.datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid())+'.blade.php')
shutil.copy2(show,backup)
print('AM123_BACKUP='+str(backup))
modified=False
def rollback(why):
    shutil.copy2(backup,show)
    partial.unlink(missing_ok=True)
    subprocess.run(['php','artisan','view:clear'],cwd=root,
                   stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    print('AM123_ROLLBACK_RESTORED='+str(backup))
    raise SystemExit('AM123_DEPLOY_FAILED_ROLLED_BACK='+str(why))
def atomic(p,s):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am123_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(temp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
try:
    modified=True
    atomic(partial,frame)
    atomic(show,new)
    for args in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        p=subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=95)
        print((p.stdout or p.stderr)[-250:])
        if p.returncode:raise RuntimeError('Laravel view compile returned '+str(p.returncode))
    hits=[]
    for file in (root/'storage/framework/views').glob('*.php'):
        txt=file.read_text('utf8',errors='replace')
        if 'id="AM123_UNIFIED_ROT_WORKSPACE"' in txt and 'am123-fields' in txt:
            lint=subprocess.run(['php','-l',str(file)],cwd=root,capture_output=True,text=True,timeout=25)
            if lint.returncode:raise RuntimeError('AM123 compiled Blade syntax error '+lint.stderr[-250:])
            hits.append(str(file))
    if len(hits)!=1:raise RuntimeError('AM123 compiled Main JC fragment missing or duplicated: '+str(len(hits)))
    if "@include('job-cards.partials.am123-unified-correction')" not in show.read_text('utf8',errors='surrogateescape'):
        raise RuntimeError('AM123 Main JC partial include not persisted')
    print('AM123_COMPILED_FRAGMENT_PHP_LINT_PASS='+hits[0])
except BaseException as e:
    if modified:rollback(e)
    raise

print('AM123_UNIFIED_ROT_WORKSPACE_SOURCE_AND_BLADE_PASS')
print('AM123_DATABASE_ROUTES_CONTROLLERS_UNCHANGED')
print('AM123_REAL_BROWSER_P55_MAPPING_AND_SAVE_NOT_YET_TESTED')
print('AM123_QTY_NOT_SUPPORTED_BY_BACKEND_DO_NOT_MARK_COMPLETE')
