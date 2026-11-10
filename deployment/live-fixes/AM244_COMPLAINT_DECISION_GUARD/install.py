#!/usr/bin/env python3
"""AM244: guarded investigation-decision corrections and readable complaint audit timeline.
STAGING only: python3 install.py check|apply|rollback ROOT.
Never rewrites existing complaint rows or audit history during installation.
"""
from pathlib import Path
from datetime import datetime
import hashlib, json, os, shutil, subprocess, sys, tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("AM244_USAGE install.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM244_NOT_STAGING")
ctrl=root/"app/Http/Controllers/Web/RotWorkComplaintController.php"
view=root/"resources/views/work-complaints/show.blade.php"
backup_parent=root/"storage/app/am-backups"
pointer=backup_parent/"AM244_LAST_SUCCESSFUL_BACKUP.txt"

def shell(command,timeout=80):
    return subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=timeout)
def hash_bytes(data):
    return hashlib.sha256(data).hexdigest()
def store_atomic(target,data):
    fd,tmp=tempfile.mkstemp(prefix=".am244_",dir=target.parent)
    try:
        with os.fdopen(fd,"wb") as file:file.write(data)
        os.chmod(tmp,target.stat().st_mode & 0o777)
        os.replace(tmp,target)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(data,kind):
    with tempfile.NamedTemporaryFile("wb",suffix=".php",delete=False) as file:
        file.write(data); name=Path(file.name)
    try:
        result=shell(["php","-d","display_errors=1","-d","log_errors=0","-l",str(name)],30)
        if result.returncode:
            raise SystemExit("STOP_AM244_"+kind+"_PHP_LINT "+(result.stdout+result.stderr)[-1400:])
    finally:
        name.unlink(missing_ok=True)
def compile_blade(data,kind):
    with tempfile.TemporaryDirectory(prefix="am244view_") as d:
        source=Path(d)/"source.blade.php"
        compiled=Path(d)/"compiled.php"
        source.write_bytes(data)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        c=shell(["php","-r",php,str(source),str(compiled)],50)
        if c.returncode or not compiled.is_file():
            raise SystemExit("STOP_AM244_BLADE_COMPILE_"+kind+" "+(c.stdout+c.stderr)[-900:])
        php_lint(compiled.read_bytes(),"COMPILED_BLADE_"+kind)

if mode=="rollback":
    if not pointer.is_file():
        raise SystemExit("STOP_AM244_NO_SUCCESSFUL_BACKUP")
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_parent.resolve() not in backup.parents or not backup.name.startswith("AM244_WORK_COMPLAINT_"):
        raise SystemExit("STOP_AM244_UNSAFE_BACKUP_PATH")
    manifest=backup/"manifest.json"
    if not manifest.is_file():
        raise SystemExit("STOP_AM244_NO_BACKUP_MANIFEST")
    data=json.loads(manifest.read_text())
    for target,originalname in ((ctrl,"controller.php"),(view,"show.blade.php")):
        if not target.is_file() or not (backup/originalname).is_file():
            raise SystemExit("STOP_AM244_ROLLBACK_MISSING_SOURCE")
        if hash_bytes(target.read_bytes())!=data["installed"][originalname]:
            raise SystemExit("STOP_AM244_ROLLBACK_FILE_CHANGED_"+originalname)
    store_atomic(ctrl,(backup/"controller.php").read_bytes())
    store_atomic(view,(backup/"show.blade.php").read_bytes())
    cleared=shell(["php","artisan","view:clear"],60)
    if cleared.returncode:
        raise SystemExit("AM244_ORIGINAL_FILES_RESTORED_BUT_VIEW_CACHE_CLEAR_FAILED")
    print("AM244_CONTROLLER_AND_REPORT_ROLLED_BACK")
    print("Complaint #1, original ROT, audits and financial rows retained")
    sys.exit(0)

if not ctrl.is_file() or not view.is_file():
    raise SystemExit("STOP_AM244_AM242_NOT_INSTALLED")
oldmethod=pkg/"review_old.txt"
newmethod=pkg/"review_new.txt"
newview=pkg/"show.blade.php"
if any(not x.is_file() for x in (oldmethod,newmethod,newview)):
    raise SystemExit("STOP_AM244_PACKAGE_INCOMPLETE")
c=ctrl.read_bytes()
v=view.read_bytes()
a=oldmethod.read_bytes()
b=newmethod.read_bytes()
if c.count(a)!=1:
    if c.count(b)==1: raise SystemExit("STOP_AM244_ALREADY_PATCHED")
    raise SystemExit("STOP_AM244_REVIEW_METHOD_CHANGED_REQUIRES_MANUAL_REVIEW")
if b"AM242" not in v or b"Audited Complaint History" not in v:
    raise SystemExit("STOP_AM244_COMPLAINT_REPORT_NOT_EXPECTED_AM242_VERSION")
if b"Investigation Decision Timeline" in v:
    raise SystemExit("STOP_AM244_VIEW_ALREADY_PATCHED")
newc=c.replace(a,b,1)
newv=newview.read_bytes()
if not (b"expected_audit_id" in newc and b"DECISION_CORRECTED" in newc
        and b"Investigation Decision Timeline" in newv and b"correction_reason" in newv):
    raise SystemExit("STOP_AM244_PATCH_MARKERS_MISSING")

php_lint(newc,"CONTROLLER")
compile_blade(v,"ORIGINAL")
compile_blade(newv,"UPDATED")
print("AM244_BASELINE_AND_UPDATED_BLADE_PASS")
print("AM244_CONTROLLER_PHP_LINT_PASS")
print("AM244_NO_EXISTING_COMPLAINT_AUDIT_OR_ROT_DATA_CHANGES")
print("AM244_PREFLIGHT_PASS")
if mode=="check":
    print("AM244_CHECK_READ_ONLY")
    sys.exit(0)

backup=backup_parent/("AM244_WORK_COMPLAINT_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(ctrl,backup/"controller.php")
shutil.copy2(view,backup/"show.blade.php")
try:
    store_atomic(ctrl,newc)
    store_atomic(view,newv)
    clear=shell(["php","artisan","view:clear"],60)
    if clear.returncode:
        raise RuntimeError("VIEW_CACHE_CLEAR_FAILED "+(clear.stdout+clear.stderr)[-350:])
    compiled=shell(["php","artisan","view:cache"],110)
    if compiled.returncode:
        raise RuntimeError("VIEW_CACHE_COMPILE_FAILED "+(compiled.stdout+compiled.stderr)[-700:])
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
app('request')->setLaravelSession(app('session')->driver());
if (!\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.show')
 || !\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.register')) {
 fwrite(STDERR,'AM244_MISSING_ROUTES');exit(20);
}
$case=(object)[
 'id'=>0,'outcome'=>null,'status'=>'UNDER_REVIEW','job_card_id'=>0,
 'rot_session_id'=>'TEST-SOURCE-ONLY','staff_id'=>0,
 'reported_at'=>'2026-11-10 00:00:00','reviewed_at'=>null,
 'description'=>'Synthetic read-only test','investigation_notes'=>'Synthetic test finding',
 'employee_response'=>null
];
$staff=(object)['name'=>'Synthetic Test','staff_code'=>'TEST'];
$audits=collect([(object)[
 'id'=>1,'previous_json'=>null,
 'new_json'=>json_encode(['status'=>'REPORTED','description'=>'Synthetic read-only test']),
 'action'=>'REPORTED','created_at'=>'2026-11-10 00:00:00','actor_admin_id'=>0
]]);
$errors=new \Illuminate\Support\ViewErrorBag();
try {
 $html=view('work-complaints.show',compact('case','staff','audits','errors'))->render();
 if (strpos($html,'Investigation Decision Timeline')===false
  || strpos($html,'expected_audit_id')===false) {
   fwrite(STDERR,'AM244_REPORT_MISSING_ELEMENTS');exit(21);
 }
 echo 'AM244_REPORT_SYNTHETIC_RENDER_PASS';
} catch (\Throwable $e) {
 fwrite(STDERR,get_class($e).': '.$e->getMessage());exit(22);
}"""
    result=shell(["php","-d","display_errors=1","-r",probe],60)
    if result.returncode or "AM244_REPORT_SYNTHETIC_RENDER_PASS" not in result.stdout:
        raise RuntimeError("AM244_RENDER_TEST_FAILED "+(result.stdout+result.stderr)[-1100:])
    print("AM244_REPORT_SYNTHETIC_RENDER_PASS")
except Exception as e:
    store_atomic(ctrl,(backup/"controller.php").read_bytes())
    store_atomic(view,(backup/"show.blade.php").read_bytes())
    shell(["php","artisan","view:clear"],60)
    raise SystemExit("STOP_AM244_APPLY_FAILED_RESTORED "+str(e))
(backup/"manifest.json").write_text(json.dumps({
    "installed":{
        "controller.php":hash_bytes(newc),
        "show.blade.php":hash_bytes(newv)
    }},indent=2))
pointer.write_text(str(backup)+"\n")
print("AM244_DECISION_GUARD_AND_READABLE_AUDIT_INSTALLED")
print("BACKUP",backup)
print("BROWSER_CHECK_PENDING: /erp/rot-work-complaints/1")
print("Historical findings are preserved; this patch does NOT automatically change Complaint #1 status")
