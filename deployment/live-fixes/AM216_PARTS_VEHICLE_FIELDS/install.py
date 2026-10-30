#!/usr/bin/env python3
"""AM216: native STAGING Parts Master Vehicle Make/Model restore.
Add optional fields to existing Admin Create/Edit, existing parts_master columns.
No JS, no schema changes, no past purchases, invoices or stock mutation.
Usage install.py check|apply|rollback STAGING_ROOT
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM216_USAGE check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM216_STOP_NOT_STAGING')
ctl=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
view=root/'resources/views/admin/native/parts.blade.php'
backup=root/'storage/app/am-backups/AM216_PARTS_VEHICLE_FIELDS'
ctlbak=backup/'AdminNativeModuleController.php.before_AM216'
viewbak=backup/'parts.blade.php.before_AM216'
manifest=backup/'installed-sha256.txt'
marker='AM216_PARTS_VEHICLE_FIELDS'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(x):return hashlib.sha256(x.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am216_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as o:o.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not ctl.is_file() or not view.is_file():
    raise SystemExit('AM216_STOP_NATIVE_PARTS_FILES_MISSING')
a=read(ctl);b=read(view)
if mode=='rollback':
    if not (manifest.is_file() and ctlbak.is_file() and viewbak.is_file()):
        raise SystemExit('AM216_STOP_ROLLBACK_BACKUP_MISSING')
    if read(manifest).splitlines()!=[digest(a),digest(b)]:
        raise SystemExit('AM216_STOP_ROLLBACK_SOURCE_CHANGED')
    atomic(ctl,read(ctlbak));atomic(view,read(viewbak))
    print('AM216_ROLLBACK_PARTS_SOURCE_RESTORED')
    sys.exit(0)
if marker in a or marker in b:
    if marker in a and marker in b:
        print('AM216_ALREADY_INSTALLED');sys.exit(0)
    raise SystemExit('AM216_STOP_PARTIAL_SOURCE_INSTALL')
if re.search(r"\bname\s*=\s*['\"]vehicle_make['\"]",b):
    raise SystemExit('AM216_STOP_EXISTING_VEHICLE_FIELDS_REQUIRE_REVIEW')
# Ensure real schema columns before changing the controller.
verifyphp=r"""<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s=\Illuminate\Support\Facades\Schema::class;
echo ($s::hasTable('parts_master')&&$s::hasColumn('parts_master','vehicle_make')&&$s::hasColumn('parts_master','vehicle_model'))
  ? 'AM216_SCHEMA_READY':'AM216_SCHEMA_FIELDS_MISSING';
"""
p=subprocess.run(['php','-r',verifyphp[5:]],cwd=root,text=True,capture_output=True,timeout=30)
if p.returncode or p.stdout.strip()!='AM216_SCHEMA_READY':
    raise SystemExit('AM216_STOP_PARTS_SCHEMA_VEHICLE_COLUMNS_UNAVAILABLE')
def locate(src,name):
    m=list(re.finditer(r'\bpublic\s+function\s+'+name+r'\s*\(',src))
    if len(m)!=1:raise SystemExit('AM216_STOP_METHOD_'+name+'_COUNT_'+str(len(m)))
    nxt=re.search(r'\n[ \t]*(?:public|private|protected)\s+function\s+\w+\s*\(',src[m[0].end():])
    end=m[0].end()+nxt.start() if nxt else len(src)
    return m[0].start(),end,src[m[0].start():end]
def patch_method(src,name):
    start,end,body=locate(src,name)
    if "'vehicle_make'" in body or "'vehicle_model'" in body:
        raise SystemExit('AM216_STOP_'+name+'_FIELDS_ALREADY_EXIST')
    anchor=re.compile(r"'sub_category'\s*=>\s*\[\s*'nullable'\s*,\s*'string'\s*,\s*'max:100'\s*\]")
    validations=list(anchor.finditer(body))
    if len(validations)!=1:raise SystemExit('AM216_STOP_'+name+'_VALIDATION_'+str(len(validations)))
    m=validations[0]
    body=body[:m.end()]+",'vehicle_make'=>['nullable','string','max:100'],'vehicle_model'=>['nullable','string','max:120']"+body[m.end():]
    if name=='createPart':
        # Only the master insert array is changed; never Job Card/Purchase.
        rex=re.compile(r"'sub_category'\s*=>\s*\$data\s*\[\s*'sub_category'\s*\]\s*\?\?\s*''")
        matches=list(rex.finditer(body))
        if len(matches)!=1:raise SystemExit('AM216_STOP_CREATE_INSERT_ANCHOR_'+str(len(matches)))
        m=matches[0]
        body=body[:m.end()]+",'vehicle_make'=>$data['vehicle_make']??'','vehicle_model'=>$data['vehicle_model']??''"+body[m.end():]
    else:
        if len(re.findall(r"DB::table\(\s*'parts_master'\s*\)->where\(\s*'id'\s*,\s*\$part\s*\)->update\(\s*\$data\s*\)",body))!=1:
            raise SystemExit('AM216_STOP_UPDATE_DATA_ANCHOR')
        val=re.search(r"\$data\s*=\s*\$request->validate\([\s\S]*?\]\);",body)
        if not val:raise SystemExit('AM216_STOP_UPDATE_VALIDATION_END')
        lines="\n        $data['vehicle_make']=trim((string)($data['vehicle_make']??''));\n        $data['vehicle_model']=trim((string)($data['vehicle_model']??''));"
        body=body[:val.end()]+lines+body[val.end():]
    if body.count("'vehicle_make'")<2 or body.count("'vehicle_model'")<2:
        raise SystemExit('AM216_STOP_FIELD_KEYS_NOT_IN_SAVE')
    return src[:start]+body+src[end:]
newa=patch_method(a,'createPart')
newa=patch_method(newa,'updatePart')
needle='    public function createPart('
if newa.count(needle)!=1:raise SystemExit('AM216_STOP_MARKER_ANCHOR')
newa=newa.replace(needle,'    // AM216_PARTS_VEHICLE_FIELDS: native master Make/Model create/edit only.\n'+needle,1)

# Add exactly once after Sub Category in CREATE and EDIT, preserving their
# existing action, CSRF, labels, price, stock and GST values.
matches=list(re.finditer(r'\bname\s*=\s*["\x27]sub_category["\x27]',b))
if len(matches)!=2:raise SystemExit('AM216_STOP_PARTS_SUB_CATEGORY_LABEL_COUNT_'+str(len(matches)))
edits=[]
for m in matches:
    end=b.find('</label>',m.end())
    if end<0 or end-m.end()>350:raise SystemExit('AM216_STOP_SUB_CATEGORY_LABEL_END')
    end+=len('</label>')
    begin=b.rfind('<form',0,m.start())
    fragment=b[begin:m.start()] if begin>=0 else ''
    isedit="route('erp.masters.parts.update',$r->id)" in fragment
    iscreate="route('erp.masters.parts.create')" in fragment
    if not (iscreate or isedit):raise SystemExit('AM216_STOP_FORM_ACTION_CONTEXT')
    valmake="old('vehicle_make',$r->vehicle_make??'')" if isedit else "old('vehicle_make','')"
    valmodel="old('vehicle_model',$r->vehicle_model??'')" if isedit else "old('vehicle_model','')"
    extra=(
      '\n<label>Vehicle Make<input name="vehicle_make" maxlength="100"'
      ' value="{{ '+valmake+' }}" placeholder="Select or enter Make"></label>'
      '\n<label>Vehicle Model<input name="vehicle_model" maxlength="120"'
      ' value="{{ '+valmodel+' }}" placeholder="Select or enter Model"></label>'
    )
    edits.append((end,extra))
newb=b
for end,extra in sorted(edits,reverse=True):newb=newb[:end]+extra+newb[end:]
newb='{{-- AM216_PARTS_VEHICLE_FIELDS: NEW ERP Parts Master Make/Model fields. --}}\n'+newb
if any(newb.count('name="'+x+'"')!=2 for x in ('vehicle_make','vehicle_model')):
    raise SystemExit('AM216_STOP_NEW_FORMS_MISSING_FIELDS')
if newa.count(marker)!=1 or newb.count(marker)!=1:
    raise SystemExit('AM216_STOP_MARKER_COUNT')
for tk in ("route('erp.masters.parts.create')","route('erp.masters.parts.update',$r->id)","@csrf"):
    if tk not in newb:raise SystemExit('AM216_STOP_EXISTING_FORM_ACTION_CHANGED')
with tempfile.TemporaryDirectory(prefix='am216qa_') as d:
    folder=Path(d)
    c=folder/'AdminNativeModuleController.php'
    c.write_text(newa,encoding='utf8',errors='surrogateescape')
    x=subprocess.run(['php','-l',str(c)],cwd=root,text=True,capture_output=True,timeout=25)
    if x.returncode:raise SystemExit('AM216_STOP_PHP_CONTROLLER_LINT '+(x.stdout+x.stderr)[-350:])
    v=folder/'parts.blade.php';v.write_text(newb,encoding='utf8',errors='surrogateescape')
    compiler=folder/'compile.php';compiled=folder/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    x=subprocess.run(['php',str(compiler),str(root),str(v),str(compiled)],cwd=root,text=True,capture_output=True,timeout=35)
    if x.returncode or not compiled.is_file():raise SystemExit('AM216_STOP_BLADE_COMPILE '+x.stderr[-350:])
    x=subprocess.run(['php','-l',str(compiled)],cwd=root,text=True,capture_output=True,timeout=25)
    if x.returncode:raise SystemExit('AM216_STOP_BLADE_PHP_LINT '+x.stderr[-350:])
print('AM216_NATIVE_PART_VEHICLE_MAKE_MODEL_CREATE_EDIT_BACKEND_PREFLIGHT_PASS')
print('AM216_PARTS_CONTROLLER_AND_BLADE_SYNTAX_PASS')
print('AM216_NO_MASTER_ROWS_OR_FINANCIAL_RECORDS_CHANGED')
if mode=='check':
    print('AM216_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if any(x.exists() for x in (ctlbak,viewbak,manifest)):
    raise SystemExit('AM216_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True);os.chmod(backup,0o700)
shutil.copy2(ctl,ctlbak);shutil.copy2(view,viewbak)
os.chmod(ctlbak,0o600);os.chmod(viewbak,0o600)
try:
    atomic(ctl,newa);atomic(view,newb)
    manifest.write_text(digest(newa)+'\n'+digest(newb)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,text=True,capture_output=True,timeout=90)
        if r.returncode:raise RuntimeError('VIEW_CACHE_FAILED '+r.stderr[-250:])
except BaseException as error:
    atomic(ctl,a);atomic(view,b)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM216_APPLY_FAILED_SOURCE_RESTORED '+str(error))
print('AM216_PARTS_VEHICLE_MAKE_MODEL_CREATE_EDIT_INSTALLED')
print('AM216_BROWSER_SAVE_REFRESH_CHECK_REQUIRED')
