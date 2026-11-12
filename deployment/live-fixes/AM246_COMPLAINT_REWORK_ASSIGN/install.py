#!/usr/bin/env python3
"""AM246 — guarded complaint rework assignment deployment (not ROT creation).
Usage: python3 install.py check|prepare|apply|rollback STAGING_ROOT
No existing ROT, finance, complaint or historical audit row is ever modified by this installer.
"""
from pathlib import Path
from datetime import datetime
import hashlib,json,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","prepare","apply","rollback"):
    raise SystemExit("USAGE_AM246 install.py check|prepare|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM246_NOT_STAGING")
routes="routes/web.php"
report="resources/views/work-complaints/show.blade.php"
register="resources/views/work-complaints/register.blade.php"
staff="resources/views/staff/partials/am242-work-complaints.blade.php"
controller="app/Http/Controllers/Web/ComplaintReworkAssignmentController.php"
view="resources/views/work-complaints/rework-assign.blade.php"
migration="database/migrations/2026_11_10_173000_create_complaint_rework_assignments.php"
existing=[routes,report,register,staff]
new=[controller,view]
backup_base=root/"storage/app/am-backups"
pointer=backup_base/"AM246_LAST_SUCCESSFUL_BACKUP.txt"

def sha(blob):return hashlib.sha256(blob).hexdigest()
def text(p):return p.read_text("utf-8",errors="surrogateescape")
def enc(s):return s.encode("utf-8",errors="surrogateescape")
def run(args,timeout=100):return subprocess.run(args,cwd=root,capture_output=True,text=True,timeout=timeout)
def exactly(s,old,add,tag):
    if s.count(old)!=1:raise SystemExit("STOP_AM246_ANCHOR_"+tag+"_"+str(s.count(old)))
    return s.replace(old,add,1)
def atomic(p,blob):
    p.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(prefix=".am246_",dir=p.parent)
    try:
        with os.fdopen(fd,"wb") as f:f.write(blob)
        os.chmod(tmp,(p.stat().st_mode & 0o777) if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(blob,tag):
    with tempfile.NamedTemporaryFile("wb",suffix=".php",delete=False) as f:
        f.write(blob);p=Path(f.name)
    try:
        r=run(["php","-d","display_errors=1","-d","log_errors=0","-l",str(p)],25)
        if r.returncode:raise SystemExit("STOP_AM246_PHP_LINT_"+tag+" "+(r.stdout+r.stderr)[-800:])
    finally:p.unlink(missing_ok=True)
def blade_lint(blob,tag):
    with tempfile.TemporaryDirectory(prefix="am246blade_") as d:
        a=Path(d)/"source.blade.php";b=Path(d)/"compiled.php"
        a.write_bytes(blob)
        comp=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        c=run(["php","-r",comp,str(a),str(b)],45)
        if c.returncode or not b.is_file():
            raise SystemExit("STOP_AM246_BLADE_COMPILE_"+tag+" "+(c.stdout+c.stderr)[-700:])
        php_lint(b.read_bytes(),"COMPILED_"+tag)
def schema_check(require_installed=False):
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
$db=\Illuminate\Support\Facades\DB::class;
$tables=['am_complaint_rework_links','am_complaint_rework_audits','am_complaint_staff_notifications'];
$present=0;
foreach($tables as $t){if($schema::hasTable($t))$present++;}
if($present==0){echo 'AM246_NEW_TABLES_REQUIRED';exit(0);}
if($present!==3){fwrite(STDERR,'AM246_PARTIAL_TABLES');exit(31);}
$required=['id','complaint_id','job_card_id','original_rot_session_id','original_staff_id',
 'rework_rot_session_id','assigned_staff_id','assigned_by_admin_id','status','instructions','assigned_at'];
foreach($required as $c){if(!$schema::hasColumn('am_complaint_rework_links',$c)){
 fwrite(STDERR,'AM246_MISSING_COLUMN_'.$c);exit(32);
}}
foreach(['original_rot_session_id','rework_rot_session_id'] as $column){
 // Hostinger MariaDB rejects parameter binding in SHOW COLUMNS LIKE ?. Use information_schema instead.
 $r=$db::selectOne('SELECT DATA_TYPE AS data_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
   ['am_complaint_rework_links',$column]);
 if(!$r || stripos((string)$r->data_type,'varchar')===false) {
  fwrite(STDERR,'AM246_ROT_ID_MUST_BE_VARCHAR_'.$column);exit(33);
 }
}
echo 'AM246_STRING_ROT_LINK_SCHEMA_READY';"""
    r=run(["php","-r",probe],50)
    if r.returncode:raise SystemExit("STOP_AM246_SCHEMA "+(r.stdout+r.stderr)[-550:])
    value=r.stdout.strip()
    if require_installed and value!="AM246_STRING_ROT_LINK_SCHEMA_READY":
        raise SystemExit("STOP_AM246_RUN_PREPARED_MIGRATION_AFTER_DATABASE_BACKUP")
    print(value)
def route_check():
    probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.register')
 || !\Illuminate\Support\Facades\Route::has('erp.rot-work-complaints.show'))
 exit(25);
echo 'AM246_BASE_ROUTES_PRESENT';"""
    r=run(["php","-r",probe],40)
    if r.returncode:raise SystemExit("STOP_AM246_MISSING_EXISTING_ROUTES "+r.stderr[-350:])
    print("AM246_BASE_ROUTES_PRESENT")

if mode=="rollback":
    if not pointer.is_file():raise SystemExit("STOP_AM246_NO_BACKUP")
    backup=Path(pointer.read_text().strip()).resolve()
    if backup_base.resolve() not in backup.parents or not backup.name.startswith("AM246_REWORK_ASSIGN_"):
        raise SystemExit("STOP_AM246_BACKUP_PATH_INVALID")
    manifest=backup/"manifest.json"
    if not manifest.is_file():raise SystemExit("STOP_AM246_BACKUP_MANIFEST_MISSING")
    saved=json.loads(manifest.read_text())
    for rel,digest in saved["installed"].items():
        if not (root/rel).is_file() or sha((root/rel).read_bytes())!=digest:
            raise SystemExit("STOP_AM246_CURRENT_FILE_CHANGED_"+rel)
    for rel in existing:atomic(root/rel,(backup/rel).read_bytes())
    for rel in new:(root/rel).unlink()
    cleared=run(["php","artisan","optimize:clear"],80)
    if cleared.returncode:raise SystemExit("AM246_SOURCES_RESTORED_CACHE_CLEAR_FAILED")
    print("AM246_ROLLBACK_RESTORED_CODE")
    print("AM246_LINK_TABLES_AND_ALL_STAFF_COMPLAINT_AUDITS_PRESERVED")
    sys.exit(0)

if not (pkg/migration).is_file() or not all((pkg/rel).is_file() for rel in new):
    raise SystemExit("STOP_AM246_PACKAGE_INCOMPLETE")
for rel in existing:
    if not (root/rel).is_file():raise SystemExit("STOP_AM246_REQUIRED_SOURCE_MISSING_"+rel)
for rel in new:
    if (root/rel).exists():raise SystemExit("STOP_AM246_NEW_CONTROLLER_OR_VIEW_EXISTS_"+rel)
raw={rel:(root/rel).read_bytes() for rel in existing}
src={rel:raw[rel].decode("utf-8","surrogateescape") for rel in existing}
if any("AM246_COMPLAINT_REWORK" in s for s in src.values()):
    raise SystemExit("STOP_AM246_ALREADY_APPLIED")
route_anchor="Route::get('/rot-work-complaints/{complaint}',"
addition=(
    "    // AM246_COMPLAINT_REWORK: existing Admin-only route group.\n"
    "    Route::get('/rot-work-complaints/{complaint}/rework',"
    " [\\App\\Http\\Controllers\\Web\\ComplaintReworkAssignmentController::class,'show'])"
    "->name('erp.rot-work-complaints.rework.show');\n"
    "    Route::post('/rot-work-complaints/{complaint}/rework',"
    " [\\App\\Http\\Controllers\\Web\\ComplaintReworkAssignmentController::class,'assign'])"
    "->name('erp.rot-work-complaints.rework.assign');\n"
    "    Route::post('/rot-work-complaints/{complaint}/rework-rot',"
    " [\\App\\Http\\Controllers\\Web\\ComplaintReworkAssignmentController::class,'linkRot'])"
    "->name('erp.rot-work-complaints.rework.link');\n"
)
patched={}
patched[routes]=enc(exactly(src[routes],route_anchor,addition+route_anchor,"ADMIN_ROUTES"))
nav_anchor=' <a href="{{ route('+"'erp.job-cards.show'"+',$case->job_card_id) }}">Back to Job Card</a>'
if src[report].count(nav_anchor)!=1:
    raise SystemExit("STOP_AM246_AM244_REPORT_NAV_CHANGED")
patched[report]=enc(src[report].replace(nav_anchor,nav_anchor+
    '\n <a href="{{ route('+"'erp.rot-work-complaints.rework.show'"+',$case->id) }}">Assign / View Rework Mechanic</a>'
    '\n {{-- AM246_COMPLAINT_REWORK: links to independent staff assignment and native ROT --}}',1))
# Show receiving staff in main admin register. Never imply mere assignment means repaired.
title='<th>Finding</th><th>Result</th>'
newtitle='<th>Finding</th><th>Rework Assignment</th><th>Result</th>'
row='<td><a href="{{ route('+"'erp.rot-work-complaints.show'"+',$c->id) }}">View Report</a></td>'
newrow=(
 '<td><a href="{{ route('+"'erp.rot-work-complaints.rework.show'"+',$c->id) }}">'
 "{{ $c->rework_staff_name ? ($c->rework_staff_name . ' · ' . $c->rework_status . ($c->rework_rot_session_id ? ' · ROT ' . $c->rework_rot_session_id : ' · New ROT pending')) : 'Assign Mechanic' }}"
 '</a></td>'+row
)
# AM246: use a single escaped Blade expression; inline nested @if/@else directly
# adjacent to @forelse can compile to an unterminated conditional in PHP.

x=exactly(src[register],title,newtitle,"REGISTER_HEAD")
x=exactly(x,row,newrow,"REGISTER_ROW")
x=exactly(x,'colspan="8"','colspan="9"',"EMPTY_COLSPAN")
patched[register]=enc(x+'\n{{-- AM246_COMPLAINT_REWORK register receiving staff column --}}\n')
# No data access from request-supplied staff IDs. Existing AM242 staff portal uses auth()->user()->staff_id.
x=src[staff]
x=exactly(x,
    'New ROT {{ $am242Transfer->rework_rot_session_id }}',
    "New ROT {{ $am242Transfer->rework_rot_session_id ?: 'pending' }}",
    "ORIGINAL_STAFF_INFO")
x=exactly(x,
    'Rework ROT {{ $am242R->rework_rot_session_id }} · JC #{{ $am242R->job_card_id }}',
    "Rework ROT {{ $am242R->rework_rot_session_id ?: 'not yet linked' }} · JC #{{ $am242R->job_card_id }}",
    "RECEIVING_STAFF_INFO")
x=exactly(x,
    'Use your existing assigned ROT card and normal Check-In, Start, Pause and Complete controls to record work. Assignment does not make you responsible for the original fault.',
    "If the new ROT is pending, Admin must create and link it before you can use the existing Check-In, Start, Pause and Complete controls. Assignment does not make you responsible for the original fault.",
    "RECEIVING_INSTRUCTIONS")
patched[staff]=enc(x+'\n{{-- AM246_COMPLAINT_REWORK staff self-view receives initial assignment before ROT linking --}}\n')
for rel in new:patched[rel]=(pkg/rel).read_bytes()

# Modify only the existing Register query's select with two verified additional LEFT JOINs.
# The other controller actions and AM244 decision guard are not touched.
register_ctrl="app/Http/Controllers/Web/RotWorkComplaintController.php"
ctrlpath=root/register_ctrl
if not ctrlpath.is_file():raise SystemExit("STOP_AM246_REGISTER_CONTROLLER_MISSING")
original_ctrl=ctrlpath.read_bytes()
s=original_ctrl.decode("utf-8","surrogateescape")
query_old=(
"  $q=DB::table('am_rot_work_complaints as c')\n"
"   ->leftJoin('staff as s','s.id','=','c.staff_id')\n"
"   ->select('c.*','s.name as staff_name','s.staff_code');")
query_new=(
"  // AM246_COMPLAINT_REWORK: receiving mechanic is not necessarily the person who completed the repair.\n"
"  $q=DB::table('am_rot_work_complaints as c')\n"
"   ->leftJoin('staff as s','s.id','=','c.staff_id')\n"
"   ->leftJoin('am_complaint_rework_links as l','l.complaint_id','=','c.id')\n"
"   ->leftJoin('staff as r','r.id','=','l.assigned_staff_id')\n"
"   ->select('c.*','s.name as staff_name','s.staff_code',"
"'r.name as rework_staff_name','l.rework_rot_session_id','l.status as rework_status');")
patched[register_ctrl]=enc(exactly(s,query_old,query_new,"REGISTER_QUERY"))
existing.append(register_ctrl)
raw[register_ctrl]=original_ctrl

php_lint(patched[controller],"ASSIGNMENT_CONTROLLER")
php_lint(patched[register_ctrl],"REGISTER_CONTROLLER")
php_lint(patched[routes],"ROUTES")
php_lint((pkg/migration).read_bytes(),"MIGRATION")
for rel in [report,register,staff,view]:
    blade_lint(raw[rel],"BASELINE_"+rel.replace("/","_")) if rel in raw else None
    blade_lint(patched[rel],"UPDATED_"+rel.replace("/","_"))
route_check()
schema_check(require_installed=False)
print("AM246_STRING_ROT_ID_AND_ADMIN_ASSIGNMENT_CODE_PASS")
print("AM246_BLADE_PHP_LINT_PASS")
print("AM246_NO_NATIVE_ROT_OR_BILLING_DATA_WRITES")
print("AM246_PREFLIGHT_PASS")
if mode=="check":
    print("AM246_CHECK_READ_ONLY")
    sys.exit(0)
if mode=="prepare":
    dest=root/migration
    if dest.exists():
        if dest.read_bytes()!=(pkg/migration).read_bytes():
            raise SystemExit("STOP_AM246_MIGRATION_FILENAME_CONFLICT")
        print("AM246_MIGRATION_ALREADY_PREPARED")
    else:
        atomic(dest,(pkg/migration).read_bytes())
        print("AM246_MIGRATION_PREPARED_FILE_ONLY")
    print("BACKUP_STAGING_DB_BEFORE_EXPLICIT_PHP_ARTISAN_MIGRATE")
    sys.exit(0)
schema_check(require_installed=True)
backup=backup_base/("AM246_REWORK_ASSIGN_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
for rel,blob in raw.items():
    dest=backup/rel;dest.parent.mkdir(parents=True,exist_ok=True);dest.write_bytes(blob)
written=[]
try:
    for rel,blob in patched.items():
        target=root/rel
        if rel in raw and target.read_bytes()!=raw[rel]:
            raise RuntimeError("SOURCE_CHANGED_"+rel)
        atomic(target,blob)
        written.append(rel)
    for command in (["php","artisan","optimize:clear"],["php","artisan","view:cache"]):
        result=run(command,115)
        if result.returncode:
            raise RuntimeError("ARTISAN_CHECK_FAILED "+(result.stdout+result.stderr)[-800:])
    route_code=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['erp.rot-work-complaints.rework.show','erp.rot-work-complaints.rework.assign',
'erp.rot-work-complaints.rework.link'] as $name)
 if(!\Illuminate\Support\Facades\Route::has($name))exit(24);
echo 'AM246_THREE_ADMIN_ROUTES_PASS';"""
    result=run(["php","-r",route_code],40)
    if result.returncode or "AM246_THREE_ADMIN_ROUTES_PASS" not in result.stdout:
        raise RuntimeError("ROUTE_CHECK_FAILED "+(result.stdout+result.stderr)[-300:])
    print("AM246_THREE_ADMIN_ROUTES_PASS")
except Exception as error:
    for rel in written:
        target=root/rel
        if rel in raw:atomic(target,raw[rel])
        else:target.unlink(missing_ok=True)
    run(["php","artisan","optimize:clear"],80)
    raise SystemExit("STOP_AM246_APPLY_RESTORED_"+str(error))
(backup/"manifest.json").write_text(json.dumps({
    "installed":{rel:sha(blob) for rel,blob in patched.items()}},indent=2))
pointer.write_text(str(backup)+"\n")
print("AM246_ADMIN_REWORK_ASSIGNMENT_INSTALLED")
print("AM246_STAFF_PORTAL_AND_ADMIN_REGISTER_RECEIVING_MECHANIC_CONNECTED")
print("BACKUP",backup)
print("BROWSER_TEST_REQUIRED: /erp/rot-work-complaints/1/rework")
print("No rework mechanic has yet been chosen; user must make an explicit assignment in Admin ERP.")
