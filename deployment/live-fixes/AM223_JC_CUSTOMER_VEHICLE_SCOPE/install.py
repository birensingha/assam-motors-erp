#!/usr/bin/env python3
"""AM223: Customer Name selection -> ONLY customer-owned vehicles in JC Create.
Uses already-loaded server-authorised customer/vehicle options. The existing
server store() ownership guard stays unchanged. UI Blade-only, no DB changes.
check|apply|rollback STAGING_ROOT.
"""
from pathlib import Path
import hashlib,os,shutil,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','rollback'):
    raise SystemExit('AM223_USAGE install.py check|apply|rollback STAGING_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM223_STOP_NOT_STAGING')
view=root/'resources/views/job-cards/form.blade.php'
fragment=pkg/'customer-vehicle.blade.php'
backup=root/'storage/app/am-backups/AM223_JC_CUSTOMER_VEHICLE_SCOPE'
old_file=backup/'form.blade.php.before_AM223'
manifest=backup/'installed-sha256.txt'
marker='AM223_JC_CUSTOMER_VEHICLE_SCOPE'
def read(p):return p.read_text('utf8',errors='surrogateescape')
def sha(s):return hashlib.sha256(s.encode('utf8',errors='surrogateescape')).hexdigest()
def atomic(p,s):
    fd,tmp=tempfile.mkstemp(prefix='.am223_',dir=p.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8',errors='surrogateescape') as fh:fh.write(s)
        os.chmod(tmp,p.stat().st_mode&0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
if not view.is_file() or not fragment.is_file():
    raise SystemExit('AM223_STOP_SOURCE_MISSING')
original=read(view)
if mode=='rollback':
    if not old_file.is_file() or not manifest.is_file():
        raise SystemExit('AM223_STOP_ROLLBACK_BACKUP_MISSING')
    if read(manifest).strip()!=sha(original):
        raise SystemExit('AM223_STOP_ROLLBACK_CURRENT_VIEW_CHANGED')
    atomic(view,read(old_file))
    print('AM223_ROLLBACK_JC_FORM_RESTORED')
    sys.exit(0)
if marker in original:
    print('AM223_ALREADY_INSTALLED');sys.exit(0)
for tk in ('id="jcCustomerMaster"','id="jcVehicleMaster"',
           'id="jcCustomerName"', 'function amFillCustomer()',
           'function amFillVehicle()',
           'data-customer="{{ $v->customer_id }}"',
           'name="customer_id"', 'name="vehicle_id"',
           "route('erp.job-cards.store')", '@csrf'):
    if tk not in original:raise SystemExit('AM223_STOP_JC_FORM_ANCHOR_'+tk[:64])
if original.count('@endsection')!=1:raise SystemExit('AM223_STOP_JC_BLADE_ENDSECTION')
# The server-side store method MUST already enforce vehicle ownership.
ctl=root/'app/Http/Controllers/Web/AdminJobCardPageController.php'
if not ctl.is_file():raise SystemExit('AM223_STOP_JOB_CARD_CONTROLLER_MISSING')
server=read(ctl)
for token in ("'vehicle_id'=>'Selected Vehicle does not belong to the selected Customer.'",
              "DB::table('am_vehicle_master')->where('id',$vehicleId)->first()",
              "(int)$vehicle->customer_id !== $customerId"):
    if token not in server:raise SystemExit('AM223_STOP_SERVER_OWNERSHIP_GUARD_MISSING')
snippet=read(fragment)
if snippet.count(marker)!=2:raise SystemExit('AM223_STOP_FRAGMENT_MARKER')
candidate=original.replace('@endsection',snippet+'\n@endsection',1)
if candidate.count(marker)!=2 or candidate.replace(snippet+'\n','',1)!=original:
    raise SystemExit('AM223_STOP_UNEXPECTED_SOURCE_CHANGE')
with tempfile.TemporaryDirectory(prefix='am223qa_') as td:
    folder=Path(td)
    source=folder/'form.blade.php';source.write_text(candidate,encoding='utf8',errors='surrogateescape')
    compiler=folder/'compile.php';out=folder/'compiled.php'
    compiler.write_text(r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[3],$app->make('blade.compiler')->compileString(file_get_contents($argv[2])));
""",encoding='utf8')
    r=subprocess.run(['php',str(compiler),str(root),str(source),str(out)],
       cwd=root,text=True,capture_output=True,timeout=40)
    if r.returncode or not out.is_file():raise SystemExit('AM223_STOP_BLADE_COMPILE '+r.stderr[-350:])
    r=subprocess.run(['php','-l',str(out)],cwd=root,text=True,capture_output=True,timeout=25)
    if r.returncode:raise SystemExit('AM223_STOP_PHP_LINT '+(r.stdout+r.stderr)[-350:])
print('AM223_CUSTOMER_NAME_SEARCH_AND_OWNED_VEHICLE_FILTER_PREFLIGHT_PASS')
print('AM223_ORIGINAL_JOB_CARD_SAVE_AND_SERVER_OWNERSHIP_GUARD_UNCHANGED')
print('AM223_BLADE_COMPILE_PHP_LINT_PASS')
if mode=='check':
    print('AM223_CHECK_PASS_NO_WRITES');sys.exit(0)
if old_file.exists() or manifest.exists():raise SystemExit('AM223_STOP_BACKUP_COLLISION')
backup.mkdir(parents=True,exist_ok=True);os.chmod(backup,0o700)
shutil.copy2(view,old_file);os.chmod(old_file,0o600)
try:
    atomic(view,candidate)
    manifest.write_text(sha(candidate)+'\n',encoding='ascii');os.chmod(manifest,0o600)
    for cmd in (['php','artisan','view:clear'],['php','artisan','view:cache']):
        r=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=90)
        if r.returncode:raise RuntimeError('VIEW_CACHE '+r.stderr[-350:])
except BaseException as ex:
    atomic(view,original)
    if manifest.exists():manifest.unlink()
    subprocess.run(['php','artisan','view:clear'],cwd=root,capture_output=True,timeout=40)
    raise SystemExit('AM223_APPLY_FAILED_RESTORED '+str(ex))
print('AM223_JC_CUSTOMER_LINKED_VEHICLES_INSTALLED')
print('AM223_BROWSER_CUSTOMER_CHANGE_AND_SAVE_TEST_PENDING')
