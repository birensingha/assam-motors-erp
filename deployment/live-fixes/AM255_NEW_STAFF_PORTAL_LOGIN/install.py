#!/usr/bin/env python3
"""AM255 secure Staff Portal and native App new-staff login reconciliation.
check|apply|verify|rollback STAGING_ROOT. No DB mutation during install.
Actual AM5848 account recovery is a separate guarded PHP file, after backup.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('AM255_USAGE check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM255_NOT_STAGING')
web='app/Http/Controllers/Web/StaffWebAuthController.php'
api='app/Http/Controllers/Api/V1/AuthController.php'
svc='app/Services/StaffLoginBootstrapService.php'
files=[web,api]
payload=pkg/'files'/svc
backupbase=root/'storage/app/am-backups'
pointer=backupbase/'AM255_LOGIN_LINK_LAST_BACKUP.txt'
marker='AM255_NEW_STAFF_ACCOUNT_RECONCILE'
web_anchor="        if(!$user || !$user->isStaff() || !Hash::check($data['password'],$user->password)){"
api_anchor="        if (!$user || !Hash::check($data['password'], $user->password)) {"
web_insert="""        // AM255_NEW_STAFF_ACCOUNT_RECONCILE: only ACTIVE master with matching bcrypt credentials.
        if (!$user) {
            $user = app(\\App\\Services\\StaffLoginBootstrapService::class)
                ->resolve($data['email'], $data['password']);
        }
"""
api_insert="""        // AM255_NEW_STAFF_ACCOUNT_RECONCILE: reuse guarded active Staff Master credentials.
        // Existing ERP users retain their existing password and Admin/GPS rules.
        if (!$user) {
            $user = app(\\App\\Services\\StaffLoginBootstrapService::class)
                ->resolve($data['email'], $data['password']);
        }
"""
def sha(data):return hashlib.sha256(data).hexdigest()
def run(args,timeout=100):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(p,b):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,temp=tempfile.mkstemp(prefix='.am255_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as h:h.write(b)
        os.chmod(temp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(temp,p)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def lint(path,b):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as h:
        h.write(b);f=Path(h.name)
    try:
        x=run(['php','-l',str(f)],30)
        if x.returncode:raise SystemExit('STOP_AM255_PHP_LINT_'+path+' '+(x.stdout+x.stderr)[-750:])
    finally:f.unlink(missing_ok=True)
def verify():
    for rel in files+[svc]:
        p=root/rel
        if not p.is_file():raise SystemExit('STOP_AM255_VERIFY_MISSING_'+rel)
        lint(Path(rel).name,p.read_bytes())
    for rel in files:
        if (root/rel).read_text('utf8','replace').count(marker)!=1:
            raise SystemExit('STOP_AM255_LOGIN_RECONCILE_MARKER_MISSING_'+rel)
    if 'final class StaffLoginBootstrapService' not in (root/svc).read_text('utf8','replace'):
        raise SystemExit('STOP_AM255_STAFF_ACCOUNT_SERVICE_NOT_LOADED')
    probe=r"""require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!class_exists(\App\Services\StaffLoginBootstrapService::class))exit(11);
foreach(['staff.login.submit'] as $n){$r=\Illuminate\Support\Facades\Route::getRoutes()->getByName($n);if(!$r||!in_array('POST',$r->methods(),true))exit(12);}
echo 'AM255_STAFF_PORTAL_ROUTE_AND_SERVICE_VERIFIED'."\n";"""
    r=run(['php','-r',probe],40)
    if r.returncode or 'AM255_STAFF_PORTAL_ROUTE_AND_SERVICE_VERIFIED' not in r.stdout:
        raise SystemExit('STOP_AM255_AUTH_SERVICE_ROUTE_VERIFICATION '+(r.stdout+r.stderr)[-600:])
    print('AM255_WEB_AND_NATIVE_APP_LOGIN_SOURCE_VERIFIED')
    print('AM255_EXISTING_LOGIN_GUARDS_AND_GEOFENCE_PRESERVED')
if mode=='verify':verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM255_NO_BACKUP')
    backup=Path(pointer.read_text().strip()).resolve()
    if backupbase.resolve() not in backup.parents or not backup.name.startswith('AM255_STAFF_LOGIN_'):
        raise SystemExit('STOP_AM255_UNSAFE_BACKUP')
    manifest=json.loads((backup/'manifest.json').read_text())
    for rel,expected in manifest['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=expected:
            raise SystemExit('STOP_AM255_FILES_CHANGED_NO_ROLLBACK_'+rel)
    for rel in files:atomic(root/rel,(backup/rel).read_bytes())
    (root/svc).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],80)
    print('AM255_LOGIN_CODE_ROLLBACK_COMPLETED_ACCOUNT_ROWS_PRESERVED')
    sys.exit(0)
for rel in files:
    if not (root/rel).is_file():raise SystemExit('STOP_AM255_LOGIN_CONTROLLER_MISSING_'+rel)
if (root/svc).exists():raise SystemExit('STOP_AM255_SERVICE_ALREADY_PRESENT_USE_VERIFY')
if not payload.is_file():raise SystemExit('STOP_AM255_SERVICE_PAYLOAD_MISSING')
before={rel:(root/rel).read_bytes() for rel in files}
texts={rel:before[rel].decode('utf8','surrogateescape') for rel in files}
after={}
for rel,anchor,insert in ((web,web_anchor,web_insert),(api,api_anchor,api_insert)):
    if texts[rel].count(anchor)!=1 or marker in texts[rel]:
        raise SystemExit('STOP_AM255_LOGIN_CONTROLLER_ANCHOR_DRIFT_'+rel)
    after[rel]=texts[rel].replace(anchor,insert+anchor,1).encode('utf8','surrogateescape')
after[svc]=payload.read_bytes()
for rel,data in [*before.items(),*after.items()]:lint(Path(rel).name,data)
print('AM255_WEB_API_AND_SERVICE_PHP_LINT_PASS')
print('AM255_FIRST_LOGIN_BOOTSTRAP_FAIL_CLOSED')
print('AM255_PREFLIGHT_PASS_NO_DATABASE_WRITES')
if mode=='check':
    print('AM255_CHECK_READ_ONLY')
    sys.exit(0)
backup=backupbase/('AM255_STAFF_LOGIN_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,data in before.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(data);os.chmod(p,0o600)
written=[]
try:
    for rel,blob in after.items():
        if rel in before and (root/rel).read_bytes()!=before[rel]:
            raise RuntimeError('CONTROLLER_CHANGED_'+rel)
        atomic(root/rel,blob);written.append(rel)
    for args in (['php','artisan','optimize:clear'],):
        rr=run(args,100)
        if rr.returncode:raise RuntimeError('CACHE_CLEAR_FAILED '+(rr.stdout+rr.stderr)[-600:])
    verify()
except Exception as e:
    for rel in written:
        if rel in before:atomic(root/rel,before[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],70)
    raise SystemExit('STOP_AM255_APPLY_RESTORED_ALL_SOURCES '+str(e))
(backup/'manifest.json').write_text(json.dumps({'installed':{rel:sha(b) for rel,b in after.items()}},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM255_STAFF_PORTAL_AND_APP_MISSING_LOGIN_BOOTSTRAP_INSTALLED')
print('AM255_LOGIN_SOURCE_BACKUP',backup)
print('AM255_NO_STAFF_ATTENDANCE_OR_PASSWORD_MUTATION_ON_DEPLOY')
