#!/usr/bin/env python3
"""AM206 STAGING-only: config master prepare, activate routes after migration.

check: inspect exact STAGING paths/route anchors without writes
prepare: copy new service/controller/view/migration only, never existing files
activate: require tables, backup routes, attach Admin-only config routes,
          PHP lint, route authorization check, Blade compile; rollback on fail

No Job Card assignment, Inspection submit, QC guard, Android or financial edits.
"""
from pathlib import Path
from datetime import datetime
import json,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","prepare","activate"):
    raise SystemExit("USAGE install.py check|prepare|activate STAGING_LARAVEL_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent/"files"
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM206_STOP_NOT_STAGING")
files=[
 "app/Services/Am206InspectionMaster.php",
 "app/Http/Controllers/Web/Am206InspectionMasterController.php",
 "resources/views/inspection-master-am206/index.blade.php",
 "database/migrations/2026_10_30_113000_create_am206_inspection_master_versions.php"
]
for rel in files:
    source=package/rel; dest=root/rel
    if not source.is_file():
        raise SystemExit("AM206_STOP_PACKAGE_MISSING "+rel)
    if dest.is_file() and source.read_bytes()!=dest.read_bytes():
        raise SystemExit("AM206_STOP_EXISTING_FILE_DIFFERENT "+rel)
routes=root/"routes/web.php"
original=routes.read_bytes()
text=original.decode("utf-8","surrogateescape")
anchor="Route::get('/job-cards/{job}/inspection-defects', ["
if text.count(anchor)!=1:
    raise SystemExit("AM206_STOP_AM189_ADMIN_ROUTE_ANCHOR_MISSING_OR_AMBIGUOUS")
lineend=text.find("\n",text.index(anchor))
if lineend<0:raise SystemExit("AM206_STOP_ROUTE_LINE_END_MISSING")
route_marker="AM206_INSPECTION_MASTER_ADMIN_ROUTES"
addition=(
    "\n    // "+route_marker+": inherits existing ERP admin middleware.\n"
    "    Route::get('/configuration/inspection-master', "
    "[\\App\\Http\\Controllers\\Web\\Am206InspectionMasterController::class,'index'])"
    "->name('erp.inspection-master.am206.index');\n"
    "    Route::post('/configuration/inspection-master/publish', "
    "[\\App\\Http\\Controllers\\Web\\Am206InspectionMasterController::class,'publish'])"
    "->name('erp.inspection-master.am206.publish');"
)
installed=route_marker in text
if not installed:
    if "/configuration/inspection-master'" in text or "/configuration/inspection-master/publish'" in text:
        raise SystemExit("AM206_STOP_CONFIGURATION_ROUTE_COLLISION")
    changed=text[:lineend]+addition+text[lineend:]
else:
    changed=text

print("AM206_STAGING",root.name)
print("AM206_FILES",len(files))
print("AM206_ROUTE_STATUS","PRESENT" if installed else "NEEDS_INSTALL")
print("AM206_EXISTING_FILE_COUNT",sum((root/rel).exists() for rel in files))
if mode=="check":
    print("AM206_CHECK_PASS_NO_WRITES")
    print("AM206_CONFIG_ONLY_NO_INSPECTION_SUBMIT_OR_ASSIGNMENT_CHANGES")
    sys.exit(0)
if mode=="prepare":
    for rel in files:
        source=package/rel;dest=root/rel
        dest.parent.mkdir(parents=True,exist_ok=True)
        if not dest.exists():shutil.copy2(source,dest)
    checks=[
        "app/Services/Am206InspectionMaster.php",
        "app/Http/Controllers/Web/Am206InspectionMasterController.php",
        "database/migrations/2026_10_30_113000_create_am206_inspection_master_versions.php"
    ]
    for name in checks:
        p=subprocess.run(["php","-l",str(root/name)],capture_output=True,text=True)
        if p.returncode:raise SystemExit("AM206_PREPARE_PHP_LINT_FAIL "+name)
    print("AM206_FILES_PREPARED_PHP_LINT_PASS")
    print("AM206_NOT_ACTIVATED_NO_ROUTE_OR_DB_CHANGES")
    print("AM206_NEXT_TAKE_FRESH_STAGING_DB_BACKUP_BEFORE_MIGRATION")
    print("AM206_NEXT_MIGRATE php artisan migrate --path=database/migrations/2026_10_30_113000_create_am206_inspection_master_versions.php --force")
    sys.exit(0)

if os.environ.get("AM206_STAGING_APPROVED")!="YES":
    raise SystemExit("AM206_STOP_EXPLICIT_ACTIVATION_APPROVAL_REQUIRED")
for rel in files:
    if not (root/rel).is_file():
        raise SystemExit("AM206_STOP_PREPARE_REQUIRED "+rel)
probe=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
  'versions'=>\Illuminate\Support\Facades\Schema::hasTable('am206_inspection_master_versions'),
  'snapshots'=>\Illuminate\Support\Facades\Schema::hasTable('am206_job_inspection_forms')
]);
"""
p=subprocess.run(["php","-r",probe],cwd=root,capture_output=True,text=True)
if p.returncode:raise SystemExit("AM206_STOP_DB_PROBE_FAILED")
try: tables=json.loads(p.stdout)
except ValueError:raise SystemExit("AM206_STOP_DB_PROBE_INVALID")
if not all(tables.values()):
    raise SystemExit("AM206_STOP_MASTER_AND_SNAPSHOT_MIGRATION_NOT_DONE")
if installed:
    print("AM206_ALREADY_ACTIVE_NO_WRITES")
    sys.exit(0)

# Compile prospective routes before replacing the actual web.php.
fd,tmp=tempfile.mkstemp(prefix="am206_preflight_",suffix=".php")
try:
    with os.fdopen(fd,"wb") as fh:
        fh.write(changed.encode("utf-8","surrogateescape"))
    q=subprocess.run(["php","-l",tmp],capture_output=True,text=True)
    if q.returncode:raise SystemExit("AM206_STOP_PREVIEW_ROUTE_LINT_FAILED")
finally:
    os.unlink(tmp)

backup=root/"storage/app/am-backups"/("AM206_MASTER_"+datetime.now().strftime("%Y%m%d_%H%M%S"))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(routes,backup/"web.php")
try:
    fd,temp=tempfile.mkstemp(prefix=".am206_",dir=routes.parent)
    try:
        with os.fdopen(fd,"wb") as fh:
            fh.write(changed.encode("utf-8","surrogateescape"))
        shutil.copymode(routes,temp)
        os.replace(temp,routes)
    finally:
        if os.path.exists(temp):os.unlink(temp)
    subprocess.run(["php","artisan","route:clear"],cwd=root,check=True,capture_output=True,text=True)
    result=subprocess.run(["php","artisan","route:list","--json"],cwd=root,
                          check=True,capture_output=True,text=True)
    allroutes=json.loads(result.stdout)
    for name in ("erp.inspection-master.am206.index","erp.inspection-master.am206.publish"):
        row=[r for r in allroutes if r.get("name")==name]
        if len(row)!=1 or not any("EnsureErpAdmin" in str(m) for m in row[0].get("middleware",[])):
            raise RuntimeError("AM206_MISSING_ADMIN_AUTH "+name)
    subprocess.run(["php","artisan","view:cache"],cwd=root,
                   check=True,capture_output=True,text=True)
except Exception as e:
    shutil.copy2(backup/"web.php",routes)
    subprocess.run(["php","artisan","route:clear"],cwd=root,capture_output=True,text=True)
    subprocess.run(["php","artisan","view:clear"],cwd=root,capture_output=True,text=True)
    raise SystemExit("AM206_ROLLBACK_RESTORED_ROUTE "+str(e))
print("AM206_MASTER_ADMIN_ROUTES_ACTIVATED")
print("AM206_ADMIN_ROUTE_AUTH_PASS")
print("AM206_BLADE_COMPILE_PASS")
print("AM206_BACKUP",backup)
print("AM206_NO_INSPECTION_JOB_CARD_OR_QC_WRITES")
