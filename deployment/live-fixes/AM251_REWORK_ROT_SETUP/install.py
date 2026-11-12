#!/usr/bin/env python3
"""AM251: guarded deployment of optional native no-charge Complaint Rework ROT POST.
check|apply|verify|rollback STAGING_ROOT. Deployment NEVER creates a work line.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,subprocess,sys,tempfile
if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply','verify','rollback'):
    raise SystemExit('USAGE_AM251 install.py check|apply|verify|rollback STAGING_ROOT')
mode=sys.argv[1];root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('STOP_AM251_NOT_STAGING')
route='routes/web.php'
view='resources/views/work-complaints/rework-assign.blade.php'
controller='app/Http/Controllers/Web/ComplaintReworkRotSetupController.php'
partial='resources/views/work-complaints/no-charge-rot.blade.php'
src_controller=pkg/'files'/controller
src_partial=pkg/'files'/partial
backups=root/'storage/app/am-backups'
pointer=backups/'AM251_LAST_SUCCESSFUL_BACKUP.txt'
marker='AM251_REWORK_ROT_CREATE'
include="@include('work-complaints.no-charge-rot')"
route_name='erp.rot-work-complaints.rework.create-rot'
route_anchor="Route::post('/rot-work-complaints/{complaint}/rework-rot',"
heading='<h2>Step 2 — Link a Separate ROT for Work Timer & Productivity Credit</h2>'
def sha(b):return hashlib.sha256(b).hexdigest()
def run(args,timeout=90):
    return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def atomic(p,b):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix='.am251_',dir=p.parent)
    try:
        with os.fdopen(fd,'wb') as f:f.write(b)
        os.chmod(tmp,(p.stat().st_mode&0o777) if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def lint_php(blob,label):
    with tempfile.NamedTemporaryFile('wb',suffix='.php',delete=False) as f:
        f.write(blob);p=Path(f.name)
    try:
        r=run(['php','-l',str(p)],30)
        if r.returncode:raise SystemExit('STOP_AM251_PHP_LINT_'+label+' '+(r.stdout+r.stderr)[-650:])
    finally:p.unlink(missing_ok=True)
def lint_blade(blob,label):
    with tempfile.TemporaryDirectory(prefix='am251_blade_') as d:
        source=Path(d)/'source.blade.php';out=Path(d)/'compiled.php'
        source.write_bytes(blob)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        p=run(['php','-r',php,str(source),str(out)],45)
        if p.returncode or not out.is_file():
            raise SystemExit('STOP_AM251_BLADE_COMPILE_'+label+' '+(p.stdout+p.stderr)[-600:])
        lint_php(out.read_bytes(),'COMPILED_'+label)
def route_schema_check():
    php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
foreach(['rot_sessions','job_card_labour','job_card_change_audits','labour_master',
'am_rot_work_complaints','am_complaint_rework_links','am_complaint_rework_audits'] as $t)
 if(!Schema::hasTable($t))exit(19);
foreach(['billing_included','billing_reason','rot_session_id'] as $c)
 if(!Schema::hasColumn('job_card_labour',$c))exit(20);
$r=Route::getRoutes()->getByName('erp.rot-work-complaints.rework.show');
if(!$r || !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))exit(21);
echo 'AM251_BASE_SCHEMA_AND_ADMIN_ROUTE_VERIFIED';"""
    r=run(['php','-r',php],40)
    if r.returncode or 'AM251_BASE_SCHEMA_AND_ADMIN_ROUTE_VERIFIED' not in r.stdout:
        raise SystemExit('STOP_AM251_BASE_SCHEMA_OR_ADMIN_GUARD '+(r.stdout+r.stderr)[-500:])
    print('AM251_BASE_SCHEMA_AND_ADMIN_ROUTE_VERIFIED')
def verify():
    for path in (root/route,root/view,root/controller,root/partial):
        if not path.is_file():raise SystemExit('STOP_AM251_VERIFY_MISSING_'+path.name)
    if (root/view).read_text('utf8','replace').count(include)!=1:
        raise SystemExit('STOP_AM251_BLADE_INCLUDE_MISSING_OR_DUPLICATE')
    if (root/route).read_text('utf8','replace').count(marker)!=1:
        raise SystemExit('STOP_AM251_ROUTE_MARKER_MISSING_OR_DUPLICATE')
    if marker not in (root/partial).read_text('utf8','replace'):
        raise SystemExit('STOP_AM251_NO_CHARGE_FORM_MARKER_MISSING')
    lint_php((root/controller).read_bytes(),'INSTALLED_CONTROLLER')
    test=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
$r=Route::getRoutes()->getByName('erp.rot-work-complaints.rework.create-rot');
if(!$r || !in_array('POST',$r->methods(),true)
 || !in_array('auth:web',$r->gatherMiddleware(),true)
 || !str_contains(implode('|',$r->gatherMiddleware()),'EnsureErpAdmin'))
 exit(29);
$c=DB::table('am_rot_work_complaints')->where('id',1)->first();
$l=DB::table('am_complaint_rework_links')->where('complaint_id',1)->first();
if(!$c || !$l) {echo 'AM251_ROUTE_AUTH_ONLY_PASS_NO_COMPLAINT_1';exit(0);}
$receiver=DB::table('staff')->where('id',$l->assigned_staff_id)->first();
$html=view('work-complaints.no-charge-rot',['case'=>$c,'link'=>$l,
 'receiver'=>$receiver,'candidateRots'=>collect()])->render();
if(!$l->rework_rot_session_id && !str_contains($html,'Create &amp; Link No-Charge ROT')
    && !str_contains($html,'Create & Link No-Charge ROT'))exit(32);
echo 'AM251_JC11_NO_CHARGE_ROT_FORM_RENDER_PASS';"""
    r=run(['php','-r',test],60)
    if r.returncode or 'AM251_' not in r.stdout:
        raise SystemExit('STOP_AM251_RENDER_OR_PROTECTED_ROUTE '+(r.stdout+r.stderr)[-750:])
    print(r.stdout.strip())
    print('AM251_ADMIN_POST_ROUTE_GUARD_VERIFIED')
    print('AM251_STAGING_NO_CHARGE_REWORK_FORM_VERIFIED_READONLY')

if mode=='verify':
    verify();sys.exit(0)
if mode=='rollback':
    if not pointer.is_file():raise SystemExit('STOP_AM251_NO_BACKUP_POINTER')
    backup=Path(pointer.read_text().strip()).resolve()
    if backups.resolve() not in backup.parents or not backup.name.startswith('AM251_NO_CHARGE_ROT_'):
        raise SystemExit('STOP_AM251_BAD_BACKUP_PATH')
    saved=json.loads((backup/'manifest.json').read_text())
    for rel,h in saved['installed'].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=h:
            raise SystemExit('STOP_AM251_SOURCE_CHANGED_REFUSE_ROLLBACK_'+rel)
    for rel in (route,view):atomic(root/rel,(backup/rel).read_bytes())
    for rel in (controller,partial):(root/rel).unlink()
    run(['php','artisan','optimize:clear'],90)
    print('AM251_ROLLBACK_SOURCE_ONLY_ALL_WORK_RECORDS_PRESERVED')
    sys.exit(0)

for path in [src_controller,src_partial,root/route,root/view]:
    if not path.is_file():raise SystemExit('STOP_AM251_REQUIRED_SOURCE_MISSING_'+str(path))
for rel in (controller,partial):
    if (root/rel).exists():raise SystemExit('STOP_AM251_ALREADY_OR_PARTIALLY_INSTALLED_USE_VERIFY')
raw={rel:(root/rel).read_bytes() for rel in (route,view)}
rtext=raw[route].decode('utf8','surrogateescape')
vtext=raw[view].decode('utf8','surrogateescape')
if rtext.count(route_anchor)!=1 or vtext.count(heading)!=1 or include in vtext:
    raise SystemExit('STOP_AM251_ADMIN_ROUTE_OR_STEP2_BLADE_ANCHOR_CHANGED')
newroute=(
"    // AM251_REWORK_ROT_CREATE: protected Admin only; explicitly confirmed POST.\n"
"    Route::post('/rot-work-complaints/{complaint}/rework-rot/create', "
"[\\App\\Http\\Controllers\\Web\\ComplaintReworkRotSetupController::class,'create'])"
"->whereNumber('complaint')->name('erp.rot-work-complaints.rework.create-rot');\n"
)
patched={
 route:(rtext.replace(route_anchor,newroute+route_anchor,1)).encode('utf8','surrogateescape'),
 view:(vtext.replace(heading,
  '<h2>Step 2 — Create or Link a No-Charge Rework ROT</h2>\n'
  +include+'\n'
  '<p class="muted">Option B — If you already created a separate native ROT, link it below. '
  'Do not add an unintended customer-billable work line.</p>\n',1)
  +'\n{{-- AM251_REWORK_ROT_CREATE: safe optional one-click no-charge native ROT --}}\n'
  ).encode('utf8','surrogateescape'),
 controller:src_controller.read_bytes(),
 partial:src_partial.read_bytes(),
}
lint_php(raw[route],'BASE_ROUTES')
lint_php(patched[route],'PATCHED_ROUTES')
lint_php(patched[controller],'NEW_CONTROLLER')
for rel in (view,):
    lint_blade(raw[rel],'ORIGINAL_REWORK_PAGE')
    lint_blade(patched[rel],'UPDATED_REWORK_PAGE')
lint_blade(patched[partial],'NO_CHARGE_FORM')
route_schema_check()
print('AM251_PHP_AND_BLADE_PREFLIGHT_PASS')
print('AM251_NO_DB_WRITES_NO_ROT_CREATED_UNTIL_ADMIN_FORM_POST')
print('AM251_PREFLIGHT_PASS')
if mode=='check':
    print('AM251_CHECK_READ_ONLY')
    sys.exit(0)

backup=backups/('AM251_NO_CHARGE_ROT_'+datetime.now().strftime('%Y%m%d_%H%M%S')+'_'+str(os.getpid()))
backup.mkdir(parents=True,exist_ok=False);os.chmod(backup,0o700)
for rel,b in raw.items():
    p=backup/rel;p.parent.mkdir(parents=True,exist_ok=True);p.write_bytes(b);os.chmod(p,0o600)
written=[]
try:
    for rel,b in patched.items():
        target=root/rel
        if rel in raw and target.read_bytes()!=raw[rel]:raise RuntimeError('SOURCE_CHANGED_'+rel)
        atomic(target,b);written.append(rel)
    for c in (['php','artisan','optimize:clear'],['php','artisan','view:cache']):
        o=run(c,105)
        if o.returncode:raise RuntimeError('ARTISAN_CACHE '+(o.stdout+o.stderr)[-650:])
    verify()
except Exception as exc:
    for rel in written:
        if rel in raw:atomic(root/rel,raw[rel])
        else:(root/rel).unlink(missing_ok=True)
    run(['php','artisan','optimize:clear'],90)
    raise SystemExit('STOP_AM251_APPLY_RESTORED_ORIGINALS '+str(exc))
(backup/'manifest.json').write_text(json.dumps({
    'installed':{rel:sha(b) for rel,b in patched.items()}
},indent=2))
os.chmod(backup/'manifest.json',0o600)
pointer.write_text(str(backup)+'\n');os.chmod(pointer,0o600)
print('AM251_ADMIN_NO_CHARGE_ROT_CREATE_AND_LINK_INSTALLED')
print('AM251_BACKUP',backup)
print('AM251_NO_COMPLAINT_OR_ROT_DATA_CHANGED_DURING_INSTALL')
print('AM251_BROWSER_CHECK_REQUIRED: /erp/rot-work-complaints/1/rework')
