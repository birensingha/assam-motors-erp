#!/usr/bin/env python3
"""AM212 - Optional internal ROT work procedure textarea in native ERP master.

Modes:
  check STAGING_ROOT   (read-only preflight of current controller and Blade)
  prepare STAGING_ROOT (copy additive migration only; no DB write)
  apply STAGING_ROOT   (requires migration complete; guarded backup+source patch)
  rollback STAGING_ROOT (restore exact source backups iff current files unmodified)

Never edits Job Card, finance, invoice, ROT timers, historical records,
or OLD ERP. Only Admin native labour master create/edit and nullable field.
"""
from pathlib import Path
import hashlib,os,re,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','apply','rollback'):
    raise SystemExit('AM212_USAGE install.py check|prepare|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM212_STOP_NOT_STAGING')
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
view=root/'resources/views/admin/native/labour.blade.php'
migration_name='2026_10_30_190000_add_work_procedure_to_labour_master.php'
migration_pkg=pkg/'files/database/migrations'/migration_name
migration_dest=root/'database/migrations'/migration_name
backup_dir=root/'storage/app/am-backups/AM212_ROT_WORK_PROCEDURE'
controller_bak=backup_dir/'AdminNativeModuleController.php.before_AM212'
view_bak=backup_dir/'labour.blade.php.before_AM212'
manifest=backup_dir/'installed-sha256.txt'
marker='AM212_ROT_WORK_PROCEDURE'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def digest(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,temp=tempfile.mkstemp(prefix='.am212_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(temp,p.stat().st_mode & 0o777)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
if not controller.is_file() or not view.is_file() or not migration_pkg.is_file():
    raise SystemExit('AM212_STOP_SOURCE_OR_MIGRATION_MISSING')
current_controller=read(controller);current_view=read(view)
if mode=='rollback':
    if not all(p.is_file() for p in (controller_bak,view_bak,manifest)):
        raise SystemExit('AM212_STOP_ROLLBACK_BACKUP_MISSING')
    expected=read(manifest).splitlines()
    if expected != [digest(current_controller),digest(current_view)]:
        raise SystemExit('AM212_STOP_ROLLBACK_SOURCE_CHANGED_REFUSING_OVERWRITE')
    atomic(controller,read(controller_bak));atomic(view,read(view_bak))
    print('AM212_ROLLBACK_SOURCE_RESTORED')
    print('AM212_COLUMN_PRESERVED_NO_DATA_LOSS')
    print('AM212_RUN_PHP_ARTISAN_VIEW_CLEAR')
    sys.exit(0)
if marker in current_controller or marker in current_view:
    if marker in current_controller and marker in current_view:
        print('AM212_ALREADY_INSTALLED_NO_CHANGES')
        sys.exit(0)
    raise SystemExit('AM212_STOP_PARTIAL_INSTALL_REVIEW')
if not ('public function createLabour(' in current_controller
        and 'public function updateLabour(' in current_controller):
    raise SystemExit('AM212_STOP_NATIVE_LABOUR_CREATE_UPDATE_MISSING')
if "route('erp.masters.labour.create')" not in current_view or "route('erp.masters.labour.update',$r->id)" not in current_view:
    raise SystemExit('AM212_STOP_NATIVE_LABOUR_MASTER_FORMS_CHANGED')
# Extract only the existing Admin controller Create/Edit methods and leave
# every other method unchanged.
def method_segment(source,method):
    x=list(re.finditer(r'\bpublic\s+function\s+'+method+r'\s*\(',source))
    if len(x)!=1:raise SystemExit('AM212_STOP_METHOD_COUNT_'+method+'_'+str(len(x)))
    start=x[0].start()
    nxt=re.search(r'\n[ \t]*(?:public|private|protected)\s+function\s+\w+\s*\(',source[x[0].end():])
    end=x[0].end()+nxt.start() if nxt else len(source)
    return start,end,source[start:end]
def patch_method(source,name):
    start,end,part=method_segment(source,name)
    # Exact existing validation anchor: service GST validation. We do not guess
    # other fields or rework the business validation array.
    validation=re.compile(r"(?P<leading>\s*)'gst_percent'\s*=>\s*\[\s*'required'")
    vh=list(validation.finditer(part))
    if len(vh)!=1:raise SystemExit('AM212_STOP_'+name+'_VALIDATION_ANCHOR_'+str(len(vh)))
    insert_val="\n            'work_procedure'=>['nullable','string','max:12000'],"
    part=part[:vh[0].start()]+insert_val+part[vh[0].start():]
    # Must persist along with the existing per-master description, in the same
    # insert/update transaction. Do not accidentally change JC/invoice records.
    write=re.compile(r"'rot_description'\s*=>\s*\$data\s*\[\s*'rot_description'\s*\]")
    hits=list(write.finditer(part))
    if len(hits)!=1:raise SystemExit('AM212_STOP_'+name+'_WRITE_ANCHOR_'+str(len(hits)))
    w=hits[0]
    part=part[:w.end()]+",'work_procedure'=>($data['work_procedure']??null)"+part[w.end():]
    # Three literal occurrences are expected: validation key, DB write key,
    # and input access ($data['work_procedure']). The earlier guard expected
    # only two, incorrectly rejecting a correctly generated Create/Update.
    validation_keys=len(re.findall(r"'work_procedure'\s*=>\s*\[\s*'nullable'",part))
    write_keys=len(re.findall(r"'work_procedure'\s*=>\s*\(\s*\$data\s*\[\s*'work_procedure'\s*\]",part))
    if validation_keys!=1 or write_keys!=1:
        raise SystemExit('AM212_STOP_'+name+'_FIELD_COUNT_VALIDATION_'+str(validation_keys)+'_WRITE_'+str(write_keys))
    return source[:start]+part+source[end:]
new_controller=patch_method(current_controller,'createLabour')
new_controller=patch_method(new_controller,'updateLabour')
# Marker is injected only at the Controller class's existing create method,
# before all methods are modified; this makes re-running idempotent.
needle='    public function createLabour('
if new_controller.count(needle)!=1:
    raise SystemExit('AM212_STOP_METHOD_MARKER_ANCHOR')
new_controller=new_controller.replace(needle,
    '    // AM212_ROT_WORK_PROCEDURE: internal technical steps, not invoice description.\n'+needle,1)
# Add only to the existing two description labels: new ROT + foreach edit.
labels=list(re.finditer(r'<label\b[^>]*>[\s\S]*?</label>',current_view,re.I))
target=[m for m in labels if re.search(r'\bname\s*=\s*["\x27]rot_description["\x27]',m.group(),re.I)]
if len(target)!=2:
    raise SystemExit('AM212_STOP_ROT_DESCRIPTION_FORM_LABELS_'+str(len(target)))
create=[];edit=[]
for m in target:
    chunk=m.group()
    if '$r->rot_description' in chunk:edit.append(m)
    else:create.append(m)
if len(create)!=1 or len(edit)!=1:
    raise SystemExit('AM212_STOP_CREATE_EDIT_FORM_IDENTIFICATION')
new_view=current_view
for m,is_edit in sorted([(create[0],False),(edit[0],True)],key=lambda x:x[0].start(),reverse=True):
    code = "old('work_procedure',$r->work_procedure??'')" if is_edit else "old('work_procedure','')"
    addon=(
        '\n    <label class="am212-work-procedure" style="display:block;grid-column:1/-1">'
        '\n      <span style="font-weight:750">Work Procedure / Technical Instructions (Internal, optional)</span>'
        '\n      <textarea name="work_procedure" rows="5" maxlength="12000"'
        ' style="display:block;width:100%;min-height:106px;padding:9px 11px;'
        'box-sizing:border-box;resize:vertical;border:1px solid #becfe0;'
        'border-radius:7px;font:inherit;line-height:1.55;white-space:pre-wrap"'
        ' placeholder="Removal, disconnection, fitting, torque, verification steps...">'
        '{{ '+code+' }}</textarea>'
        '\n      <small style="display:block;color:#647c90;line-height:1.4;margin-top:4px">'
        'Admin reference only. Separate from ROT description; not printed on invoices.'
        '</small>\n    </label>'
    )
    new_view=new_view[:m.end()]+addon+new_view[m.end():]
if new_view.count('name="work_procedure"')!=2:
    raise SystemExit('AM212_STOP_WORK_PROCEDURE_TWO_FIELDS_REQUIRED')
# Blade comment outside the form provides reliable installation marker.
new_view='{{-- AM212_ROT_WORK_PROCEDURE: optional private ROT work steps. --}}\n'+new_view
if current_view.count("name=\"work_procedure\"") or current_controller.count("'work_procedure'"):
    raise SystemExit('AM212_STOP_FIELD_ALREADY_PRESENT_WITHOUT_MARKER')
# If migration has been manually created and differs, never overwrite it.
if migration_dest.is_file() and migration_pkg.read_bytes()!=migration_dest.read_bytes():
    raise SystemExit('AM212_STOP_EXISTING_MIGRATION_COLLISION')
# PHP lint candidate and independently compile the modified Blade source.
with tempfile.TemporaryDirectory(prefix='am212_preflight_') as d:
    temp=Path(d)
    candidate=temp/'AdminNativeModuleController.php'
    candidate.write_text(new_controller,encoding='utf8',errors='surrogateescape')
    result=subprocess.run(['php','-l',str(candidate)],cwd=root,capture_output=True,text=True,timeout=25)
    if result.returncode:raise SystemExit('AM212_STOP_PHP_CONTROLLER_LINT '+(result.stdout+result.stderr)[-550:])
    result=subprocess.run(['php','-l',str(migration_pkg)],cwd=root,capture_output=True,text=True,timeout=25)
    if result.returncode:raise SystemExit('AM212_STOP_PHP_MIGRATION_LINT '+(result.stdout+result.stderr)[-550:])
    test_view=temp/'view.blade.php';test_view.write_text(new_view,encoding='utf8',errors='surrogateescape')
    compiler=temp/'compile.php';compiled=temp/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    result=subprocess.run(['php',str(compiler),str(root),str(test_view),str(compiled)],
        cwd=root,capture_output=True,text=True,timeout=30)
    if result.returncode or not compiled.is_file():
        raise SystemExit('AM212_STOP_BLADE_COMPILE '+result.stderr[-450:])
    result=subprocess.run(['php','-l',str(compiled)],
        cwd=root,capture_output=True,text=True,timeout=25)
    if result.returncode:raise SystemExit('AM212_STOP_BLADE_PHP_LINT '+(result.stdout+result.stderr)[-450:])
print('AM212_CONTROLLER_CREATE_UPDATE_WORK_PROCEDURE_VALIDATION_AND_PERSISTENCE_PASS')
print('AM212_FORM_CREATE_EDIT_2_SEPARATE_INTERNAL_TEXTAREAS_PASS')
print('AM212_NO_JC_INVOICE_PRICE_GST_OLD_ERP_CHANGES')
print('AM212_SOURCE_AND_BLADE_PREFLIGHT_PASS')

if mode=='check':
    print('AM212_CHECK_PASS_NO_WRITES')
    sys.exit(0)
if mode=='prepare':
    if migration_dest.exists():
        print('AM212_MIGRATION_ALREADY_PREPARED_NO_WRITES')
    else:
        migration_dest.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(migration_pkg,migration_dest)
        print('AM212_ADDITIVE_MIGRATION_FILE_PREPARED')
    print('AM212_NEXT_VERIFIED_STAGING_DB_BACKUP_THEN_RUN_EXACT_MIGRATION')
    sys.exit(0)

# APPLY does not run migrations or touch data. The caller must take a verified
# STAGING DB backup and migrate the nullable column first.
php=r"""<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo \Illuminate\Support\Facades\Schema::hasColumn('labour_master','work_procedure')?'AM212_COLUMN_READY':'AM212_COLUMN_MISSING';
"""
r=subprocess.run(['php','-r',php[5:]],cwd=root,text=True,capture_output=True,timeout=25)
if r.returncode or r.stdout.strip()!='AM212_COLUMN_READY':
    raise SystemExit('AM212_STOP_COLUMN_MISSING_RUN_ADDITIVE_MIGRATION_AFTER_STAGING_DB_BACKUP')
if any(p.exists() for p in (controller_bak,view_bak,manifest)):
    raise SystemExit('AM212_STOP_BACKUP_ALREADY_EXISTS_NO_OVERWRITE')
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(controller,controller_bak);shutil.copy2(view,view_bak)
os.chmod(controller_bak,0o600);os.chmod(view_bak,0o600)
try:
    atomic(controller,new_controller);atomic(view,new_view)
    manifest.write_text(digest(new_controller)+'\n'+digest(new_view)+'\n',encoding='ascii')
    os.chmod(manifest,0o600)
    # Only compile views. Existing migrations are never run by this installer.
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        result=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if result.returncode:raise RuntimeError('VIEW_COMPILE_FAILED '+result.stderr[-400:])
except BaseException as error:
    atomic(controller,current_controller);atomic(view,current_view)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=45)
    raise SystemExit('AM212_SAFE_SOURCE_ROLLBACK '+str(error))
print('AM212_INTERNAL_ROT_WORK_PROCEDURE_INSTALLED')
print('AM212_STAGING_BROWSER_CREATE_EDIT_PERSISTENCE_TEST_REQUIRED')
