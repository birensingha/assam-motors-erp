#!/usr/bin/env python3
"""AM242: result report, complaint register, staff-only portal panel.
STAGING check|apply|rollback. CHECK is completely read-only on Laravel.
No migrations, ROT edits or business-record writes.
"""
from pathlib import Path
from datetime import datetime
import hashlib
import json
import os
import shutil
import subprocess
import sys
import tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("AM242_USAGE install.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM242_NOT_STAGING")
ctrl="app/Http/Controllers/Web/RotWorkComplaintController.php"
routes="routes/web.php"
jc="resources/views/job-cards/show.blade.php"
portal="resources/views/staff/portal.blade.php"
old_result="resources/views/work-complaints/show.blade.php"
old_monthly="resources/views/work-complaints/monthly.blade.php"
new_register="resources/views/work-complaints/register.blade.php"
new_partial="resources/views/staff/partials/am242-work-complaints.blade.php"
existing=[ctrl,routes,jc,portal,old_result,old_monthly]
new_files=[new_register,new_partial]
backup_root=root/"storage/app/am-backups"
pointer=backup_root/"AM242_LAST_BACKUP.txt"

def txt(path):
    return path.read_text(encoding="utf-8",errors="surrogateescape")
def bytes_of(s):
    return s.encode("utf-8",errors="surrogateescape")
def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()
def shell(command,timeout=100):
    return subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=timeout)
def exact(source,old,new,label):
    if source.count(old)!=1:
        raise SystemExit("STOP_AM242_SOURCE_ANCHOR_"+label+"_"+str(source.count(old)))
    return source.replace(old,new,1)
def blade_preflight(blob,label):
    with tempfile.TemporaryDirectory(prefix="am242blade_") as d:
        temp=Path(d)
        raw=temp/"source.blade.php"
        output=temp/"compiled.php"
        raw.write_bytes(blob)
        php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        a=shell(["php","-r",php,str(raw),str(output)],50)
        if a.returncode or not output.is_file():
            raise SystemExit("STOP_AM242_BLADE_COMPILER_"+label+" "+(a.stdout+a.stderr)[-400:])
        b=shell(["php","-l",str(output)],20)
        if b.returncode:
            raise SystemExit("STOP_AM242_BLADE_PHP_LINT_"+label+" "+(b.stdout+b.stderr)[-400:])
def php_lint(blob,label):
    with tempfile.NamedTemporaryFile("wb",suffix=".php",delete=False) as f:
        f.write(blob); p=Path(f.name)
    try:
        a=shell(["php","-l",str(p)],20)
        if a.returncode:
            raise SystemExit("STOP_AM242_PHP_LINT_"+label+" "+(a.stdout+a.stderr)[-400:])
    finally:
        p.unlink(missing_ok=True)

if mode=="rollback":
    if not pointer.is_file():
        raise SystemExit("STOP_AM242_NO_BACKUP")
    b=Path(pointer.read_text().strip()).resolve()
    if backup_root.resolve() not in b.parents or not b.name.startswith("AM242_RESULT_PORTAL_"):
        raise SystemExit("STOP_AM242_BAD_BACKUP_LOCATION")
    manifest=b/"manifest.json"
    if not manifest.is_file():
        raise SystemExit("STOP_AM242_MANIFEST_MISSING")
    data=json.loads(manifest.read_text())
    # Never overwrite changes made after this patch.
    for rel,installed_sha in data["modified"].items():
        if not (root/rel).is_file() or sha(root/rel)!=installed_sha:
            raise SystemExit("STOP_AM242_ROLLBACK_SOURCE_CHANGED_"+rel)
    for rel,installed_sha in data["created"].items():
        if not (root/rel).is_file() or sha(root/rel)!=installed_sha:
            raise SystemExit("STOP_AM242_ROLLBACK_CREATED_FILE_CHANGED_"+rel)
    for rel in data["modified"]:
        shutil.copy2(b/rel,root/rel)
    for rel in data["created"]:
        (root/rel).unlink()
    cleared=shell(["php","artisan","optimize:clear"])
    if cleared.returncode:
        raise SystemExit("AM242_RESTORED_FILES_BUT_CACHE_CLEAR_FAILED")
    print("AM242_ROLLED_BACK_VIEWS_ROUTES_AND_CONTROLLER")
    print("Existing complaint records, decisions, audits and original ROT preserved")
    sys.exit(0)

for rel in existing:
    if not (root/rel).is_file():
        raise SystemExit("STOP_AM242_REQUIRED_FILE_MISSING_"+rel)
for rel in new_files:
    if (root/rel).exists():
        raise SystemExit("STOP_AM242_NEW_FILE_EXISTS_"+rel)
    if not (pkg/rel).is_file():
        raise SystemExit("STOP_AM242_PACKAGE_FILE_MISSING_"+rel)
for rel in (old_result,old_monthly):
    if not (pkg/rel).is_file():
        raise SystemExit("STOP_AM242_REPLACEMENT_MISSING_"+rel)

original={rel:(root/rel).read_bytes() for rel in existing}
src={rel:original[rel].decode("utf-8","surrogateescape") for rel in existing}
marker="AM242_RESULT_PORTAL"
if any(marker in s for s in src.values()):
    raise SystemExit("STOP_AM242_ALREADY_PRESENT")
if "AM240" not in src[jc] and "Report Work Complaint" not in src[jc]:
    raise SystemExit("STOP_AM242_AM240_JC_BUTTON_MISSING")

# Add admin-only complaint register route immediately BEFORE parameterized complaint detail.
route_anchor="Route::get('/rot-work-complaints/{complaint}',"
route_line="    // AM242_RESULT_PORTAL: reviewed complaint register. Existing admin middleware remains in force.\n    Route::get('/rot-work-complaints', [\\App\\Http\\Controllers\\Web\\RotWorkComplaintController::class,'register'])->name('erp.rot-work-complaints.register');\n"
routes_new=exact(src[routes],route_anchor,route_line+route_anchor,"ADMIN_ROUTE")

# Add audited result history to read-only result; existing review endpoint remains unchanged.
controller_new=exact(
    src[ctrl],
    "  return view('work-complaints.show',compact('case','staff'));",
    "  $audits=DB::table('am_rot_work_complaint_audits')->where('complaint_id',$complaint)->orderByDesc('id')->get();\n"
    "  return view('work-complaints.show',compact('case','staff','audits'));",
    "RESULT_AUDIT"
)
new_method=r"""
 // AM242_RESULT_PORTAL: admin-only full case register, no changes to complaint records.
 public function register(Request $request)
 {
  $this->admin($request); $this->tables();
  $data=$request->validate([
   'month'=>['nullable','date_format:Y-m'],
   'staff_id'=>['nullable','integer','min:1'],
   'job_card_id'=>['nullable','integer','min:1'],
   'outcome'=>['nullable','in:PENDING,WORKMANSHIP_CONFIRMED,NOT_WORKMANSHIP,PART_FAILURE,CUSTOMER_MISUSE,INCONCLUSIVE'],
  ]);
  $q=DB::table('am_rot_work_complaints as c')
   ->leftJoin('staff as s','s.id','=','c.staff_id')
   ->select('c.*','s.name as staff_name','s.staff_code');
  if (!empty($data['month'])) {
   $from=\Carbon\CarbonImmutable::createFromFormat('!Y-m',$data['month'],'Asia/Kolkata')->startOfMonth();
   $q->where('c.reported_at','>=',$from->format('Y-m-d H:i:s'))
     ->where('c.reported_at','<',$from->addMonth()->format('Y-m-d H:i:s'));
  }
  if (!empty($data['staff_id'])) $q->where('c.staff_id',(int)$data['staff_id']);
  if (!empty($data['job_card_id'])) $q->where('c.job_card_id',(int)$data['job_card_id']);
  if (!empty($data['outcome'])) {
   if ($data['outcome']==='PENDING') $q->whereIn('c.status',['REPORTED','UNDER_REVIEW']);
   else $q->where('c.outcome',$data['outcome']);
  }
  $cases=$q->orderByDesc('c.id')->limit(200)->get();
  $filters=$data;
  return view('work-complaints.register',compact('cases','filters'));
 }
"""
controller_new=exact(controller_new," public function monthly(Request $request)",new_method+"\n public function monthly(Request $request)","CONTROLLER_REGISTER")

# Existing AM240 ROT card already has a Report Work Complaint link. Append direct complaint register badge.
jc_button='<a class="btn" href="{{ route(\'erp.rot-work-complaints.create\', [$job->id,$rot->id]) }}">Report Work Complaint</a>'
jc_badge=r"""
        {{-- AM242_RESULT_PORTAL: show existing audited complaints on original completed ROT. --}}
        @php
          $am242ComplaintCount=\Illuminate\Support\Facades\DB::table('am_rot_work_complaints')
              ->where('job_card_id',$job->id)->where('rot_session_id',(string)$rot->id)->count();
        @endphp
        @if($am242ComplaintCount>0)
        <a class="btn" style="background:#fff0dc;border-color:#ddb378;color:#7a481e"
           href="{{ route('erp.rot-work-complaints.register',['job_card_id'=>$job->id]) }}">
           Complaint ({{ $am242ComplaintCount }}) · View Result
        </a>
        @endif
"""
jc_new=exact(src[jc],jc_button,jc_button+"\n"+jc_badge,"COMPLETED_ROT_BUTTON")

# Install into existing Staff Portal only; private staff record query lives in Blade include.
portal_anchor='<section class="staff-panel am225-rot-panel"'
if src[portal].count(portal_anchor)!=1:
    portal_anchor='<section class="staff-panel"><div class="panel-head"><h2>🔧 My ROT Work</h2>'
portal_new=exact(
    src[portal],portal_anchor,
    "{{-- AM242_RESULT_PORTAL: own-staff complaint register, read only. --}}\n"
    "@include('staff.partials.am242-work-complaints')\n"+portal_anchor,
    "STAFF_PORTAL"
)
# Preserve original non-UTF8 bytes. No changes to attendance, app APIs or old ROT controls.
replacement={
    ctrl:bytes_of(controller_new),
    routes:bytes_of(routes_new),
    jc:bytes_of(jc_new),
    portal:bytes_of(portal_new),
    old_result:(pkg/old_result).read_bytes(),
    old_monthly:(pkg/old_monthly).read_bytes(),
    new_register:(pkg/new_register).read_bytes(),
    new_partial:(pkg/new_partial).read_bytes(),
}
if b"AM242_RESULT_PORTAL" not in replacement[ctrl] or b"AM242_RESULT_PORTAL" not in replacement[jc]:
    raise SystemExit("STOP_AM242_NO_PATCH_MARKERS")
for rel in [ctrl,routes]:
    php_lint(replacement[rel],rel.replace("/","_"))
for rel in [jc,portal,old_result,old_monthly,new_register,new_partial]:
    blade_preflight(replacement[rel],rel.replace("/","_"))
print("AM242_BLADE_COMPILE_AND_PHP_LINT_PASS")
print("AM242_STAFF_ID_QUERY_IS_SCOPED_TO_AUTHENTICATED_STAFF")
print("AM242_NO_DATABASE_MIGRATION_OR_ROT_WRITES")
print("AM242_PREFLIGHT_PASS")
if mode=="check":
    print("AM242_CHECK_READ_ONLY")
    sys.exit(0)

backup=backup_root/("AM242_RESULT_PORTAL_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
for rel in existing:
    target=backup/rel
    target.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(root/rel,target)
created=[]
try:
    for rel,body in replacement.items():
        p=root/rel
        p.parent.mkdir(parents=True,exist_ok=True)
        if rel in new_files:created.append(rel)
        p.write_bytes(body)
    for cmd in (["php","artisan","optimize:clear"],["php","artisan","view:cache"]):
        verified=shell(cmd)
        if verified.returncode:
            raise RuntimeError("CACHE_COMPILE_FAILED "+(verified.stdout+verified.stderr)[-500:])
    verify=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.register')) exit(12);
echo 'AM242_REGISTER_ROUTE_PASS';"""
    ok=shell(["php","-r",verify],50)
    if ok.returncode or "AM242_REGISTER_ROUTE_PASS" not in ok.stdout:
        raise RuntimeError("REGISTER_ROUTE_MISSING "+ok.stderr[-350:])
    print("AM242_REGISTER_ROUTE_PASS")
except Exception as error:
    for rel in existing:
        shutil.copy2(backup/rel,root/rel)
    for rel in created:
        (root/rel).unlink(missing_ok=True)
    shell(["php","artisan","optimize:clear"])
    raise SystemExit("STOP_AM242_RESTORED_ORIGINAL_FILES: "+str(error))

manifest={
    "modified":{rel:sha(root/rel) for rel in existing},
    "created":{rel:sha(root/rel) for rel in new_files},
}
(backup/"manifest.json").write_text(json.dumps(manifest,indent=2))
pointer.write_text(str(backup)+"\n")
print("AM242_RESULT_REGISTER_STAFF_PORTAL_INSTALLED")
print("Backup",backup)
print("BROWSER_TEST_REQUIRED: /erp/rot-work-complaints/1 and staff AM9472 login")
print("Native Android app and AM241 rework assignment are not installed by this patch")
