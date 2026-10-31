#!/usr/bin/env python3
"""AM222 STAGING Vehicle Make->Model->Variant accurate SAP popup.
No DB/controller changes. Original New/Edit POST forms and values preserved.
check|apply|rollback ROOT. Blade compiled and PHP lint before apply.
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM222_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM222_STOP_NOT_STAGING')
view=root/'resources/views/admin/native/vehicles.blade.php'
fragment=pkg/'cascade.blade.php'
backup=root/'storage/app/am-backups/AM222_VEHICLE_CASCADE_SAP'
prior=backup/'vehicles.blade.php.before_AM222'
manifest=backup/'installed-sha256.txt'
marker='AM222_VEHICLE_CASCADE_SAP'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am222_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as f:f.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not view.exists() or not fragment.exists():raise SystemExit('AM222_STOP_FILES_MISSING')
old=read(view)
if mode=='rollback':
    if not manifest.is_file() or not prior.is_file():raise SystemExit('AM222_STOP_BACKUP_MISSING')
    if read(manifest).strip()!=sha(old):raise SystemExit('AM222_STOP_SOURCE_CHANGED')
    atomic(view,read(prior));print('AM222_ROLLBACK_VIEW_RESTORED');sys.exit(0)
if marker in old:
    print('AM222_ALREADY_INSTALLED');sys.exit(0)
for tk in ('id="vmMake"','id="vmModel"','id="vmVariant"',
           'function amVehicleNew()','function amVehicleEdit(r)',
           "route('erp.masters.vehicles.save')",'@csrf',
           'id="vehicleEditPanel"','id="vmId"'):
    if tk not in old:raise SystemExit('AM222_STOP_MASTER_ANCHOR_'+tk[:50])
if old.count('@endsection')!=1:raise SystemExit('AM222_STOP_BLADE_SECTION_ANCHOR')
snippet=read(fragment)
if snippet.count(marker)!=2:
    raise SystemExit('AM222_STOP_SNIPPET_MARKER_'+str(snippet.count(marker)))
if snippet.count('<script>')!=1 or snippet.count('</script>')!=1:
    raise SystemExit('AM222_STOP_SCRIPT_COUNT')
new=old.replace('@endsection',snippet+'\n@endsection',1)
if new.count(marker)!=2:raise SystemExit('AM222_STOP_NEW_MARKER_COUNT')
if new.replace(snippet+'\n','',1)!=old:
    raise SystemExit('AM222_STOP_UNEXPECTED_MASTER_VIEW_CHANGE')
with tempfile.TemporaryDirectory(prefix='am222preflight_') as tempdir:
    work=Path(tempdir)
    candidate=work/'vehicles.blade.php'
    candidate.write_text(new,encoding='utf8',errors='surrogateescape')
    compiler=work/'compile.php';compiled=work/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    run=subprocess.run(['php',str(compiler),str(root),str(candidate),str(compiled)],
        cwd=root,capture_output=True,text=True,timeout=45)
    if run.returncode or not compiled.exists():
        raise SystemExit('AM222_STOP_BLADE_COMPILE '+run.stderr[-450:])
    run=subprocess.run(['php','-l',str(compiled)],cwd=root,capture_output=True,text=True,timeout=25)
    if run.returncode:raise SystemExit('AM222_STOP_PHP_LINT '+(run.stdout+run.stderr)[-450:])
print('AM222_MAKE_MODEL_VARIANT_CASCADE_BLADE_PREFLIGHT_PASS')
print('AM222_VEHICLE_CREATE_EDIT_FIELDS_AND_ROUTES_UNCHANGED')
print('AM222_NORMALIZED_RELATIONSHIP_CHOICES_NO_CROSS_MODEL_SUGGESTION')
if mode=='check':
    print('AM222_CHECK_PASS_NO_WRITES');sys.exit(0)
if prior.exists() or manifest.exists():raise SystemExit('AM222_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True);os.chmod(backup,0o700)
shutil.copy2(view,prior);os.chmod(prior,0o600)
try:
    atomic(view,new)
    manifest.write_text(sha(new)+'\n',encoding='ascii');os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if r.returncode:raise RuntimeError('VIEW_CACHE '+r.stderr[-450:])
except BaseException as ex:
    atomic(view,old)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM222_APPLY_FAILED_RESTORED '+str(ex))
print('AM222_VEHICLE_SAP_CASCADE_INSTALLED')
print('AM222_BROWSER_NEW_EDIT_SAVE_REFRESH_TEST_PENDING')
