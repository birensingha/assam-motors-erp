#!/usr/bin/env python3
"""AM217 - Existing Part/ROT Edit opens the identical responsive popup as New.
No backend/DB changes. Preserve original CSRF, Update route and form contents.

Usage: install.py check|apply|rollback /.../assam-erp-staging
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM217_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM217_STOP_NOT_STAGING')
views={
 'parts':root/'resources/views/admin/native/parts.blade.php',
 'labour':root/'resources/views/admin/native/labour.blade.php'
}
fragment=pkg/'edit_popup.blade.php'
backup=root/'storage/app/am-backups/AM217_EDIT_MASTER_MODALS'
manifest=backup/'installed-sha256.txt'
marker='AM217_EDIT_MASTER_MODALS'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am217_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not fragment.is_file() or any(not p.is_file() for p in views.values()):
    raise SystemExit('AM217_STOP_SOURCE_MISSING')
old={name:read(p) for name,p in views.items()}
if mode=='rollback':
    if not manifest.exists() or not all((backup/(name+'.blade.php.before_AM217')).is_file() for name in views):
        raise SystemExit('AM217_ROLLBACK_BACKUP_MISSING')
    if read(manifest).splitlines()!=[sha(old['parts']),sha(old['labour'])]:
        raise SystemExit('AM217_STOP_ROLLBACK_VIEWS_CHANGED')
    for name,p in views.items():atomic(p,read(backup/(name+'.blade.php.before_AM217')))
    print('AM217_ROLLBACK_BOTH_EDIT_VIEWS_RESTORED')
    print('AM217_RUN_VIEW_CLEAR')
    sys.exit(0)
if any(marker in s for s in old.values()):
    if all(marker in s for s in old.values()):
        print('AM217_ALREADY_INSTALLED_NO_CHANGES')
        sys.exit(0)
    raise SystemExit('AM217_STOP_PARTIAL_INSTALL')
template=read(fragment)
if template.count(marker)!=1 or template.count('__AM217_PAGE__')!=1:
    raise SystemExit('AM217_STOP_PATCH_TEMPLATE')
candidate={}
for name,source in old.items():
    if 'AM214_NATIVE_MASTER_MODAL_SELECT' not in source:
        raise SystemExit('AM217_STOP_AM214_DROPDOWN_JS_NOT_INSTALLED_'+name)
    prefix='partEdit' if name=='parts' else 'rotEdit'
    route='parts' if name=='parts' else 'labour'
    for tk in (
        'amToggle(\''+prefix+'{{ $r->id }}\')',
        'id="'+prefix+'{{ $r->id }}"',
        'class="inline-edit-row"',
        'form class="form-grid four-col inline-edit-card"',
        "route('erp.masters."+route+".update',$r->id)",
        "route('erp.masters."+route+".create')",
        '@csrf'
    ):
        if tk not in source:raise SystemExit('AM217_STOP_EXISTING_EDIT_ANCHOR_CHANGED_'+name+'_'+tk[:55])
    if source.count('@endsection')!=1:
        raise SystemExit('AM217_STOP_ENDSECTION_ANCHOR_'+name)
    block=template.replace('__AM217_PAGE__','"'+name+'"')
    candidate[name]=source.replace('@endsection',block+'\n@endsection',1)
    if candidate[name].count(marker)!=1 or candidate[name].count('id="am217-edit-popup"')!=0:
        # dialog constructed by JS, not static HTML
        raise SystemExit('AM217_STOP_CANDIDATE_INTEGRITY_'+name)
    if candidate[name].count("route('erp.masters."+route+".update',$r->id)")!=source.count("route('erp.masters."+route+".update',$r->id)"):
        raise SystemExit('AM217_STOP_ORIGINAL_UPDATE_ACTION_CHANGED_'+name)
# Verify candidate Blade from Laravel compiler; never apply invalid output.
with tempfile.TemporaryDirectory(prefix='am217_qa_') as td:
    temp=Path(td)
    compiler=temp/'compile.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    for name,data in candidate.items():
        src=temp/(name+'.blade.php');src.write_text(data,encoding='utf8',errors='surrogateescape')
        out=temp/(name+'.compiled.php')
        run=subprocess.run(['php',str(compiler),str(root),str(src),str(out)],
            cwd=root,text=True,capture_output=True,timeout=40)
        if run.returncode or not out.exists():
            raise SystemExit('AM217_STOP_BLADE_COMPILE_'+name+' '+run.stderr[-380:])
        run=subprocess.run(['php','-l',str(out)],cwd=root,text=True,capture_output=True,timeout=30)
        if run.returncode:
            raise SystemExit('AM217_STOP_COMPILED_VIEW_LINT_'+name+' '+(run.stdout+run.stderr)[-380:])
print('AM217_EDIT_PART_AND_ROT_SAME_POPUP_BLADE_PREFLIGHT_PASS')
print('AM217_ORIGINAL_EDIT_POST_CSRF_VALUES_AND_DROPDOWN_HANDLERS_UNTOUCHED')
if mode=='check':
    print('AM217_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if manifest.exists() or any((backup/(name+'.blade.php.before_AM217')).exists() for name in views):
    raise SystemExit('AM217_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
for name,p in views.items():
    target=backup/(name+'.blade.php.before_AM217')
    shutil.copy2(p,target);os.chmod(target,0o600)
try:
    for name,p in views.items():atomic(p,candidate[name])
    manifest.write_text(sha(candidate['parts'])+'\n'+sha(candidate['labour'])+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        run=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if run.returncode:raise RuntimeError('VIEW_CACHE_FAILED '+run.stderr[-380:])
except BaseException as error:
    for name,p in views.items():atomic(p,old[name])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    raise SystemExit('AM217_APPLY_FAILED_RESTORED '+str(error))
print('AM217_EDIT_PART_AND_ROT_MODAL_INSTALLED')
print('AM217_BROWSER_EDIT_SAVE_AND_REFRESH_TEST_REQUIRED')
