#!/usr/bin/env python3
"""AM240. STAGING only. Usage: python staging_install.py check|apply ROOT.
CHECK is read-only; APPLY makes backups and stops on any unknown anchors.
"""
from pathlib import Path
from datetime import datetime
import subprocess, shutil, tempfile, sys, json

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply"):
    raise SystemExit("Usage: staging_install.py check|apply /path/to/assam-erp-staging")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_NOT_STAGING_LARAVEL")
routepath=root/"routes/web.php"
viewpath=root/"resources/views/job-cards/show.blade.php"
if not routepath.is_file() or not viewpath.is_file():
    raise SystemExit("STOP_NO_ROUTES_OR_JC_VIEW")
files=[
 "database/migrations/2026_11_07_150000_create_work_complaints.php",
 "app/Http/Controllers/Web/RotWorkComplaintController.php",
 "resources/views/work-complaints/create.blade.php",
 "resources/views/work-complaints/show.blade.php",
 "resources/views/work-complaints/monthly.blade.php",
]
for rel in files:
    if not (pkg/rel).is_file(): raise SystemExit("STOP_PACKAGE_MISSING "+rel)
    if (root/rel).exists(): raise SystemExit("STOP_SOURCE_COLLISION "+rel)

# Admin route insertion anchors inside existing protected admin group.
routes=routepath.read_text()
route_anchor="Route::post('/inspection-defects/{defect}/verify'"
if routes.count(route_anchor)!=1 or "erp.rot-work-complaints.create" in routes:
    raise SystemExit("STOP_ADMIN_ROUTE_ANCHOR_CHANGED")
newroute = r"""
    // AM240: work complaints against completed ROT. Controller enforces Admin.
    Route::get('/job-cards/{job}/rots/{rot}/work-complaints/create', [\App\Http\Controllers\Web\RotWorkComplaintController::class,'create'])->name('erp.rot-work-complaints.create');
    Route::post('/job-cards/{job}/rots/{rot}/work-complaints', [\App\Http\Controllers\Web\RotWorkComplaintController::class,'store'])->name('erp.rot-work-complaints.store');
    Route::get('/rot-work-complaints/monthly', [\App\Http\Controllers\Web\RotWorkComplaintController::class,'monthly'])->name('erp.rot-work-complaints.monthly');
    Route::get('/rot-work-complaints/{complaint}', [\App\Http\Controllers\Web\RotWorkComplaintController::class,'show'])->name('erp.rot-work-complaints.show');
    Route::post('/rot-work-complaints/{complaint}/review', [\App\Http\Controllers\Web\RotWorkComplaintController::class,'review'])->name('erp.rot-work-complaints.review');
"""
route_lines=routes.splitlines(keepends=True)
matched=[i for i,l in enumerate(route_lines) if route_anchor in l]
if len(matched)!=1: raise SystemExit("STOP_ROUTE_LINE_NOT_UNIQUE")
route_lines.insert(matched[0]+1,newroute)
routes_new="".join(route_lines)

# Must be the existing Completed ROT card's action row, with $rot in scope.
view=viewpath.read_text()
view_anchor='<div class="rot-actions {{ $rot && $rot->status===\'Completed\' ? \'am95-completed-actions\' : \'am95-active-actions\' }}">'
if view.count(view_anchor)!=1:
    raise SystemExit("STOP_COMPLETED_ROT_CARD_ANCHOR_CHANGED")
link=r"""@if($rot && strcasecmp((string)$rot->status,'Completed')===0)
        <a class="btn" href="{{ route('erp.rot-work-complaints.create', [$job->id,$rot->id]) }}">Report Work Complaint</a>
        @endif"""
view_new=view.replace(view_anchor,view_anchor+"\n"+link,1)

def lint(content,label):
    with tempfile.NamedTemporaryFile("w",suffix=".php",delete=False) as f:
        f.write(content); t=Path(f.name)
    try:
        result=subprocess.run(["php","-l",str(t)],capture_output=True,text=True)
        if result.returncode: raise SystemExit("STOP_PHP_SYNTAX "+label+": "+result.stdout[-600:]+result.stderr[-600:])
    finally: t.unlink(missing_ok=True)

for rel in files:
    if rel.endswith(".php"): lint((pkg/rel).read_text(),rel)
lint(routes_new,"routes/web.php")

# Compile all candidate Blade templates with the REAL Laravel Blade compiler.
# php -l on raw Blade is NOT a meaningful syntax validation.
def blade_lint(content,label):
    with tempfile.TemporaryDirectory(prefix="am240-blade-") as d:
        source=Path(d)/"source.blade.php"
        compiled=Path(d)/"compiled.php"
        source.write_text(content)
        php_script=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
file_put_contents($argv[2],$app->make('blade.compiler')->compileString(file_get_contents($argv[1])));"""
        done=subprocess.run(["php","-r",php_script,str(source),str(compiled)],cwd=root,capture_output=True,text=True,timeout=45)
        if done.returncode or not compiled.exists():
            raise SystemExit("STOP_BLADE_COMPILER "+label+": "+(done.stdout+done.stderr)[-500:])
        done=subprocess.run(["php","-l",str(compiled)],cwd=root,capture_output=True,text=True,timeout=25)
        if done.returncode:
            raise SystemExit("STOP_COMPILED_BLADE_SYNTAX "+label+": "+(done.stdout+done.stderr)[-500:])

blade_lint(view_new,"job-cards/show.blade.php")
for rel in files:
    if rel.endswith(".blade.php"):blade_lint((pkg/rel).read_text(),rel)

# Read-only schema shape check, without reading rows or leaking credentials.
probe=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema as S;
echo json_encode([
 'rot_sessions'=>S::hasTable('rot_sessions'),
 'rot_job'=>S::hasColumn('rot_sessions','job_card_id'),
 'rot_staff'=>S::hasColumn('rot_sessions','staff_id'),
 'rot_status'=>S::hasColumn('rot_sessions','status'),
 'job_cards'=>S::hasTable('job_cards'),
 'staff'=>S::hasTable('staff'),
 'staff_code'=>S::hasColumn('staff','staff_code'),
 'staff_name'=>S::hasColumn('staff','name'),
 'staff_id'=>S::hasColumn('staff','id')
]);"""
check=subprocess.run(["php","-r",probe],cwd=root,capture_output=True,text=True)
if check.returncode: raise SystemExit("STOP_SCHEMA_CHECK_FAILED (inspect server logs; no changes)")
try: schema=json.loads(check.stdout)
except ValueError: raise SystemExit("STOP_SCHEMA_RESPONSE_INVALID")
print("AM240_SCHEMA",schema)
if not all(schema.values()):raise SystemExit("STOP_SCHEMA_MISMATCH_NO_WRITES")
print("AM240_PREFLIGHT_PASS")
print("AM240_CHECK: no customer, ROT, billing, QC or attendance rows changed")
if mode=="check":raise SystemExit(0)

backup=root/"storage/app/am-backups"/("AM240_WORK_COMPLAINT_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
for p in [routepath,viewpath]:
    dest=backup/p.relative_to(root)
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(p,dest)
print("BACKUP:",backup)
created=[]
try:
    for rel in files:
        target=root/rel
        target.parent.mkdir(parents=True,exist_ok=True)
        shutil.copy2(pkg/rel,target)
        created.append(target)
    routepath.write_text(routes_new)
    viewpath.write_text(view_new)
    migr=subprocess.run(["php","artisan","migrate","--path=database/migrations/2026_11_07_150000_create_work_complaints.php","--force"],cwd=root)
    if migr.returncode:raise RuntimeError("Migration failed; tables may partially exist. Inspect schema before retry.")
    for command in (["php","artisan","route:clear"],["php","artisan","view:cache"],["php","artisan","optimize:clear"]):
        result=subprocess.run(command,cwd=root,capture_output=True,text=True,timeout=100)
        if result.returncode:raise RuntimeError("Validation failed: "+" ".join(command)+" "+result.stderr[-300:])
    # Verify Laravel registered the route names the new Blade uses.
    verify=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach (['erp.rot-work-complaints.create','erp.rot-work-complaints.store',
  'erp.rot-work-complaints.show','erp.rot-work-complaints.review',
  'erp.rot-work-complaints.monthly'] as $name) {
  if (!\Illuminate\Support\Facades\Route::has($name)) {
    fwrite(STDERR,'Missing route name: '.$name);exit(11);
  }
}
echo 'AM240_ROUTE_PASS';"""
    verified=subprocess.run(["php","-r",verify],cwd=root,capture_output=True,text=True,timeout=50)
    if verified.returncode or "AM240_ROUTE_PASS" not in verified.stdout:
        raise RuntimeError("New complaint routes not registered: "+verified.stderr[-300:])
    print("AM240_ROUTE_PASS")
except Exception as exc:
    shutil.copy2(backup/routepath.relative_to(root),routepath)
    shutil.copy2(backup/viewpath.relative_to(root),viewpath)
    for target in created:target.unlink(missing_ok=True)
    subprocess.run(["php","artisan","optimize:clear"],cwd=root,capture_output=True)
    raise SystemExit("STOP_APPLICATION_ROLLED_BACK: "+str(exc)+"; database migration must be reviewed separately")
print("AM240_STAGING_CODE_INSTALLED")
print("Original ROT work history, timing, invoice and all QC records unchanged")
print("Next: test link from actual JC-11 Completed ROT, report and monthly counts in browser")
