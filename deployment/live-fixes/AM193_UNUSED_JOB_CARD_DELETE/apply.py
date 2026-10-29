#!/usr/bin/env python3
"""AM193 — Admin-only direct Delete Unstarted Job Card (Inspection may be completed).

Only PATCH installation: routes/web.php + job-cards/show.blade.php + two new
PHP classes + ONE non-destructive migration creating a separate audit table.
Deletion is performed only on explicit POST by Admin, after all checks.

Commands:
  python3 apply.py check STAGING_ROOT
  python3 apply.py apply STAGING_ROOT
  cd STAGING_ROOT && php artisan migrate --path=database/migrations/2026_10_29_180000_create_am193_deleted_job_card_audits.php --force
"""
from pathlib import Path
import json,os,re,shutil,subprocess,sys,tempfile,hashlib

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
if not (root/"artisan").is_file():
    raise SystemExit("AM193_STOP_INVALID_STAGING_ROOT")
routes=root/"routes/web.php"
view=root/"resources/views/job-cards/show.blade.php"
backups=root/"storage/app/am-backups/AM193_UNUSED_JOB_CARD_DELETE"
before_routes=backups/"web.php.before_AM193"
before_view=backups/"show.blade.php.before_AM193"
manifest=backups/"installed.sha256"
sources=[
    ("JobCardDeleteGuard.php","app/Services/JobCardDeleteGuard.php"),
    ("AdminUnusedJobCardDeleteController.php","app/Http/Controllers/Web/AdminUnusedJobCardDeleteController.php"),
    ("2026_10_29_180000_create_am193_deleted_job_card_audits.php",
     "database/migrations/2026_10_29_180000_create_am193_deleted_job_card_audits.php"),
]
route_marker="AM193_ADMIN_DELETE_UNUSED_JOB_CARD"
ui_marker="AM193_UNUSED_JOB_CARD_DELETE_ACTION"

def read(p):return p.read_text(encoding="utf8",errors="surrogateescape")
def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()
def atomic(p,source):
    fd,tmp=tempfile.mkstemp(prefix=".am193_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(source)
        os.chmod(tmp,p.stat().st_mode & 0o777 if p.exists() else 0o644)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def php_lint(src):
    with tempfile.TemporaryDirectory(prefix="am193php_") as d:
        p=Path(d)/"a.php";p.write_text(src,encoding="utf8",errors="surrogateescape")
        r=subprocess.run(["php","-l",str(p)],capture_output=True,text=True,timeout=30)
        if r.returncode:raise SystemExit("AM193_STOP_PHP_LINT "+(r.stderr+r.stdout)[-300:])

if mode=="rollback":
    if not all(p.is_file() for p in (before_routes,before_view,manifest)):
        raise SystemExit("AM193_STOP_BACKUP_MISSING")
    want=read(manifest).splitlines()
    if want != [sha(routes),sha(view)]:
        raise SystemExit("AM193_STOP_FILES_CHANGED_SINCE_APPLY_DO_NOT_OVERWRITE")
    atomic(routes,read(before_routes))
    atomic(view,read(before_view))
    # Do NOT drop audit table/migration after a deletion; preserve evidence.
    print("AM193_ROLLBACK_ROUTES_AND_SHOW_VIEW_ONLY")
    print("AM193_CONTROLLER_AND_AUDIT_TABLE_PRESERVED_FOR_FORENSIC_HISTORY")
    print("AM193_RUN_ROUTE_CLEAR_VIEW_CLEAR")
    sys.exit(0)

if not routes.is_file() or not view.is_file():
    raise SystemExit("AM193_STOP_JOB_CARD_ROUTE_OR_VIEW_MISSING")
if not (root/"app/Http/Middleware/EnsureErpAdmin.php").is_file():
    raise SystemExit("AM193_STOP_ADMIN_MIDDLEWARE_NOT_FOUND")
r=read(routes)
v=read(view)
if route_marker in r or ui_marker in v:
    raise SystemExit("AM193_STOP_ALREADY_INSTALLED")
if not r.lstrip().startswith("<?php") or r.rstrip().endswith("?>"):
    raise SystemExit("AM193_STOP_ROUTE_FILE_FORMAT")
if not re.search(r"\bname\(['\"]erp\.job-cards\.show['\"]\)",r):
    raise SystemExit("AM193_STOP_JOB_CARD_SHOW_ROUTE_MISSING")
if not re.search(r"\bname\(['\"]erp\.job-cards\.index['\"]\)",r):
    raise SystemExit("AM193_STOP_JOB_CARD_INDEX_ROUTE_MISSING")
if v.count("@section('content')")!=1:
    raise SystemExit("AM193_STOP_EXPECTED_CONTENT_SECTION_NOT_FOUND")
if "$job" not in v:
    raise SystemExit("AM193_STOP_EXPECTED_JOB_CARD_MODEL_NOT_FOUND")
for filename,dest in sources:
    p=package/filename
    if not p.is_file():raise SystemExit("AM193_STOP_SOURCE_MISSING_"+filename)
    if (root/dest).exists():raise SystemExit("AM193_STOP_LIVE_FILE_CONFLICT_"+dest)
    php_lint(read(p))

# Read-only runtime preflight. The route is already admin-only, and this
# action is added under the same auth/admin middleware contract as AM187.
probe=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$out=['routes'=>[],'tables'=>[]];
foreach($app->make('router')->getRoutes() as $r){
 if(in_array($r->getName(),['erp.job-cards.show','erp.job-cards.index'],true)){
  $out['routes'][]=['uri'=>$r->uri(),'name'=>$r->getName(),'methods'=>$r->methods(),
                    'middleware'=>$r->gatherMiddleware()];}
}
foreach(['job_cards','job_card_inspection','job_card_parts','job_card_labour',
         'invoices','workshop_purchases'] as $table){
 if(Illuminate\Support\Facades\Schema::hasTable($table))
    $out['tables'][$table]=Illuminate\Support\Facades\Schema::getColumnListing($table);
}
echo json_encode($out);
"""
with tempfile.TemporaryDirectory(prefix="am193probe_") as d:
    p=Path(d)/"probe.php";p.write_text(probe,encoding="utf8")
    result=subprocess.run(["php",str(p),str(root)],capture_output=True,text=True,timeout=30)
    if result.returncode:
        raise SystemExit("AM193_STOP_RUNTIME_SCHEMA_PROBE_FAILED "+result.stderr[-220:])
    try:info=json.loads(result.stdout)
    except ValueError:raise SystemExit("AM193_STOP_RUNTIME_SCHEMA_PROBE_INVALID")
if 'job_cards' not in info['tables'] or 'id' not in info['tables']['job_cards']:
    raise SystemExit("AM193_STOP_JOBCARD_ID_SCHEMA_MISSING")
for name in ('erp.job-cards.show','erp.job-cards.index'):
    hits=[x for x in info['routes'] if x['name']==name and 'GET' in x['methods']]
    if len(hits)!=1:
        raise SystemExit("AM193_STOP_JOB_CARD_ROUTE_CONTRACT_CHANGED_"+name)
    if not any('EnsureErpAdmin' in x for x in hits[0]['middleware']):
        raise SystemExit("AM193_STOP_EXISTING_JOB_CARD_ADMIN_GATE_MISSING_"+name)
    print("AM193_EXISTING_ROUTE",hits[0]['uri'])
if 'status' not in info['tables']['job_cards']:
    raise SystemExit("AM193_STOP_JOBCARD_STATUS_MISSING")
if not ('job_card_inspection' in info['tables']):
    print("AM193_INSPECTION_TABLE_NOT_PRESENT_WORKFLOW_MAY_DIFFER")

route_add=r"""

// AM193_ADMIN_DELETE_UNUSED_JOB_CARD — audited admin-only pre-work deletion.
\Illuminate\Support\Facades\Route::middleware([
    'web','auth:web',\App\Http\Middleware\EnsureErpAdmin::class
])->post('/erp/job-cards/{job}/delete-unused',
    [\App\Http\Controllers\Web\AdminUnusedJobCardDeleteController::class,'destroy']
)->whereNumber('job')->name('erp.job-cards.delete-unused');
"""
ui=r"""
{{-- AM193_UNUSED_JOB_CARD_DELETE_ACTION --}}
@if(!in_array(strtolower(trim((string)($job->status ?? ''))), [
    'wip','work_in_progress','work in progress','in_progress','in progress',
    'started','running','work_completed','work completed','completed',
    'qc_pending','qc pending','qc_passed','qc passed',
    'ready_for_delivery','ready for delivery','closed','invoiced',
    'cancelled','canceled','deleted','financial_reopened'
]))
<section class="card section-gap" style="margin:12px 0;border:1px solid #f4c9bf;border-radius:12px;padding:12px;background:#fffdfb">
  <details>
    <summary style="cursor:pointer;font-weight:750;color:#a42b20">
      <span aria-hidden="true">🗑</span> Delete Unstarted Job Card — Admin Only
    </summary>
    <p style="margin:10px 0;color:#67483a;font-size:12px">
      Inspection completed ho to bhi deletion allowed hai, jab actual workshop
      work start nahi hua ho. Started ROT, parts stock movement, OSL/Purchase,
      invoice, receipt, QC ya linked financial record hone par delete BLOCK hoga.
      Deleted Job Card ka audit snapshot separately saved rahega.
    </p>
    @if($errors->has('delete'))
      <p role="alert" style="color:#b11e1e;font-weight:650">{{ $errors->first('delete') }}</p>
    @endif
    <form method="POST" action="{{ route('erp.job-cards.delete-unused', $job->id) }}"
          onsubmit="return confirm('This will permanently remove this unused Job Card and eligible inspection records. Continue?')">
      @csrf
      <label style="display:block;margin-top:10px;font-size:12px;font-weight:650">
        Deletion Reason (minimum 10 characters)
        <textarea name="reason" required minlength="10" maxlength="600" rows="2"
                  style="display:block;width:100%;max-width:700px;margin-top:5px;padding:9px;border:1px solid #cdd7e2;border-radius:8px"
                  placeholder="Reason for deleting this unused job card">{{ old('reason') }}</textarea>
      </label>
      <label style="display:block;margin-top:10px;font-size:12px;font-weight:650">
        Type DELETE to confirm
        <input type="text" name="confirmation" required pattern="DELETE"
               autocomplete="off" placeholder="DELETE"
               style="display:block;width:180px;margin-top:5px;padding:9px;border:1px solid #cdd7e2;border-radius:8px">
      </label>
      <button type="submit" class="btn"
              style="margin-top:10px;background:#b22e23;color:#fff;border-radius:8px;padding:9px 16px">
        Permanently Delete Unstarted Job Card
      </button>
    </form>
  </details>
</section>
@endif
"""
new_routes=r.rstrip()+"\n"+route_add+"\n"
php_lint(new_routes)
new_view=v.replace("@section('content')","@section('content')\n"+ui,1)
if new_view.count(ui_marker)!=1:
    raise SystemExit("AM193_STOP_BLADE_INSERT_FAILED")

print("AM193_ADMIN_ONLY_DELETE_ROUTE /erp/job-cards/{job}/delete-unused")
print("AM193_INSPECTION_COMPLETED_IS_NOT_WORK_STARTED True")
print("AM193_LINKED_FINANCIAL_OR_STARTED_ROT_BLOCKED True")
print("AM193_PERMANENT_AUDIT_SNAPSHOT True")
print("AM193_NO_EXISTING_DATA_CHANGES_DURING_INSTALL")
print("AM193_PHP_LINT_PASS")
if mode=="check":
    print("AM193_CHECK_PASS_NO_WRITES")
    sys.exit(0)

if any(x.exists() for x in (before_routes,before_view,manifest)):
    raise SystemExit("AM193_STOP_BACKUP_CONFLICT")
backups.mkdir(parents=True,exist_ok=True);os.chmod(backups,0o700)
shutil.copy2(routes,before_routes);shutil.copy2(view,before_view)
for p in (before_routes,before_view):os.chmod(p,0o600)
for name,dest in sources:shutil.copy2(package/name,root/dest)
atomic(routes,new_routes)
atomic(view,new_view)
manifest.write_text(sha(routes)+"\n"+sha(view)+"\n",encoding="ascii")
os.chmod(manifest,0o600)
print("AM193_UNUSED_JOB_CARD_DELETE_INSTALLED")
print("AM193_MIGRATION_REQUIRED")
print("AM193_RUN_ARTISAN_ROUTE_CLEAR_AND_VIEW_CLEAR")
