#!/usr/bin/env python3
"""AM220 Parts / ROT native master live server-side search (staging only).
check, apply, rollback; preserve original GET routes and all master actions.
Ajax table replacement and dropdown rebind; no DB writes / backend changes.
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM220_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM220_STOP_NOT_STAGING')
views={
    'parts':root/'resources/views/admin/native/parts.blade.php',
    'labour':root/'resources/views/admin/native/labour.blade.php'
}
patch=pkg/'live-search.blade.php'
backup=root/'storage/app/am-backups/AM220_MASTER_LIVE_SEARCH'
manifest=backup/'installed-sha256.txt'
marker='AM220_MASTER_LIVE_SEARCH'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am220_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not patch.is_file() or any(not p.is_file() for p in views.values()):
    raise SystemExit('AM220_STOP_PATCH_OR_STAGING_VIEWS_MISSING')
original={name:read(p) for name,p in views.items()}
if mode=='rollback':
    if not manifest.is_file() or not all((backup/(name+'.blade.php.before_AM220')).is_file() for name in views):
        raise SystemExit('AM220_STOP_ROLLBACK_BACKUP_MISSING')
    if read(manifest).splitlines()!=[sha(original['parts']),sha(original['labour'])]:
        raise SystemExit('AM220_STOP_ROLLBACK_SOURCE_CHANGED')
    for name,p in views.items():atomic(p,read(backup/(name+'.blade.php.before_AM220')))
    print('AM220_ROLLBACK_RESTORED_PARTS_AND_ROT_VIEWS')
    print('AM220_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)
if any(marker in s for s in original.values()):
    if all(marker in s for s in original.values()):
        print('AM220_ALREADY_INSTALLED')
        sys.exit(0)
    raise SystemExit('AM220_STOP_PARTIAL_INSTALL')
snippet=read(patch)
if snippet.count(marker)!=2: # Blade comment and JS marker
    raise SystemExit('AM220_STOP_SNIPPET_MARKERS_'+str(snippet.count(marker)))
if len(re.findall(r'<script\b',snippet))!=1:
    raise SystemExit('AM220_STOP_SEARCH_SCRIPT_COUNT')
binder='  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",function(){init();setupModal()});'
listen='  document.addEventListener("am220:rows-updated",init);'
candidate={}
for name,source in original.items():
    if source.count('class="filterbar card compact pro-filter"')!=1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_GET_FILTERBAR_CHANGED')
    form=re.findall(r'<form class="filterbar card compact pro-filter"[^>]*>',source)
    if len(form)!=1 or not re.search(r'method=["\x27]get["\x27]',form[0]):
        raise SystemExit('AM220_STOP_'+name.upper()+'_GET_SEARCH_FORM_CHANGED')
    if source.count('name="q"')<1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_SEARCH_INPUT_MISSING')
    if source.count('class="card table-card"')!=1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_RESULT_TABLE_ANCHOR_CHANGED')
    if source.count('AM214_NATIVE_MASTER_MODAL_SELECT')<1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_AM214_DROPDOWN_JS_MISSING')
    if source.count('AM217_EDIT_MASTER_MODALS')<1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_AM217_EDIT_MODALS_MISSING')
    if source.count(binder)!=1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_AM214_BINDER_CHANGED_'+str(source.count(binder)))
    route='parts' if name=='parts' else 'labour'
    for token in ("route('erp.masters."+route+".create')",
                  "route('erp.masters."+route+".update',$r->id)",
                  "route('erp.masters."+route+".delete',$r->id)",
                  "@csrf",
                  "function amToggle(id)"):
        if token not in source:raise SystemExit('AM220_STOP_'+name.upper()+'_MASTER_ACTION_MISSING_'+token[:60])
    if source.count('@endsection')!=1:
        raise SystemExit('AM220_STOP_'+name.upper()+'_ENDSECTION_CHANGED')
    new=source.replace(binder,listen+'\n'+binder,1)
    new=new.replace('@endsection',snippet+'\n@endsection',1)
    if new.count(listen)!=1 or new.count(marker)!=2:
        raise SystemExit('AM220_STOP_'+name.upper()+'_RESULT_INTEGRITY')
    # Byte-for-byte forms, all save/delete actions and AM217 originals preserved.
    if any(new.count("route('erp.masters."+route+"."+action)==0
           for action in ('create','update','delete')):
        raise SystemExit('AM220_STOP_'+name.upper()+'_FORM_ACTIONS_DROPPED')
    candidate[name]=new
# PHP Blade compile and PHP syntax check in isolated temporary file before any write.
with tempfile.TemporaryDirectory(prefix='am220_qa_') as folder:
    temp=Path(folder)
    compiler=temp/'compile.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    for name,source in candidate.items():
        p=temp/(name+'.blade.php');p.write_text(source,encoding='utf8',errors='surrogateescape')
        out=temp/(name+'.compiled.php')
        run=subprocess.run(['php',str(compiler),str(root),str(p),str(out)],
                           cwd=root,capture_output=True,text=True,timeout=45)
        if run.returncode or not out.exists():
            raise SystemExit('AM220_STOP_BLADE_COMPILE_'+name+' '+run.stderr[-350:])
        lint=subprocess.run(['php','-l',str(out)],cwd=root,capture_output=True,text=True,timeout=30)
        if lint.returncode:
            raise SystemExit('AM220_STOP_COMPILED_PHP_LINT_'+name+' '+(lint.stdout+lint.stderr)[-350:])
print('AM220_PARTS_ROT_FULL_SERVER_SEARCH_AND_BLADE_PREFLIGHT_PASS')
print('AM220_AM214_DROPDOWNS_AND_AM217_EDIT_REBIND_PASS')
print('AM220_CREATE_EDIT_DELETE_POST_AND_CSRF_UNCHANGED')
print('AM220_NO_DATABASE_OR_ANDROID_CHANGES')
if mode=='check':
    print('AM220_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if manifest.exists() or any((backup/(name+'.blade.php.before_AM220')).exists() for name in views):
    raise SystemExit('AM220_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True)
os.chmod(backup,0o700)
for name,p in views.items():
    b=backup/(name+'.blade.php.before_AM220')
    shutil.copy2(p,b);os.chmod(b,0o600)
try:
    for name,p in views.items():atomic(p,candidate[name])
    manifest.write_text(sha(candidate['parts'])+'\n'+sha(candidate['labour'])+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        run=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if run.returncode:raise RuntimeError('VIEW_CACHE_FAILED_'+run.stderr[-400:])
except BaseException as error:
    for name,p in views.items():atomic(p,original[name])
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM220_APPLY_FAILED_SOURCE_ROLLED_BACK '+str(error))
print('AM220_NATIVE_PART_ROT_LIVE_SEARCH_INSTALLED')
print('AM220_BROWSER_TYPE_CLEAR_EDIT_PAGINATION_TEST_REQUIRED')
