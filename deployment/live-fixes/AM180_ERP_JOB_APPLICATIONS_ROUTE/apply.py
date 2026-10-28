#!/usr/bin/env python3
"""AM180: expose existing NEW ERP Job Applications controller, safely.

No applicant writes. Adds 3 admin-authenticated web routes and, where a
compatible sidebar Attendance link exists, a Job Applications menu link.
CLI:
  python3 apply.py check LARAVEL_STAGING_ROOT
  python3 apply.py apply LARAVEL_STAGING_ROOT
  python3 apply.py rollback LARAVEL_STAGING_ROOT

Backups live only in staging storage/app/am-backups (not public_html).
"""
from pathlib import Path
import datetime,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
routes=root/"routes/web.php"
ctl=root/"app/Http/Controllers/Web/AdminNativeModuleController.php"
view=root/"resources/views/admin/native/job-applications.blade.php"
if not all(p.is_file() for p in (root/"artisan",routes,ctl,view)):
    raise SystemExit("AM180_STOP_ERP_FILES_MISSING")
marker="AM180_JOB_APPLICATIONS_ROUTES"
backup_dir=root/"storage/app/am-backups/AM180_JOB_APPLICATIONS"
snippet="""

/* AM180_JOB_APPLICATIONS_ROUTES - public Hiring records stored in ERP DB.
 * Keep behind same authentication and Admin gate as native ERP modules.
 */
\Illuminate\Support\Facades\Route::middleware([
    'web', 'auth:web', \App\Http\Middleware\EnsureErpAdmin::class
])->group(function () {
    \Illuminate\Support\Facades\Route::get('/erp/job-applications',
        [\App\Http\Controllers\Web\AdminNativeModuleController::class, 'jobApplications']
    )->name('erp.job-applications.index');
    \Illuminate\Support\Facades\Route::post('/erp/job-applications/{application}/status',
        [\App\Http\Controllers\Web\AdminNativeModuleController::class, 'updateJobApplication']
    )->name('erp.job-applications.status');
    \Illuminate\Support\Facades\Route::delete('/erp/job-applications/{application}',
        [\App\Http\Controllers\Web\AdminNativeModuleController::class, 'deleteJobApplication']
    )->name('erp.job-applications.delete');
});
"""
nav_candidates=[
    root/"resources/views/layouts/erp.blade.php",
    root/"resources/views/layouts/partials/sidebar.blade.php",
    root/"resources/views/layouts/sidebar.blade.php",
    root/"resources/views/partials/sidebar.blade.php",
    root/"resources/views/admin/partials/sidebar.blade.php",
]
expected_methods=("jobApplications","updateJobApplication","deleteJobApplication")
def get_text(p):
    return p.read_text(encoding="utf8",errors="surrogateescape")
def php_lint(content):
    with tempfile.TemporaryDirectory(prefix="am180lint_") as d:
        p=Path(d)/"route.php"
        p.write_text(content,encoding="utf8",errors="surrogateescape")
        result=subprocess.run(["php","-l",str(p)],text=True,
                              capture_output=True,timeout=20)
        return result.returncode==0,(result.stdout+result.stderr)[-300:]
def atomic_write(p,content):
    fd,tmp=tempfile.mkstemp(prefix=".am180_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:
            f.write(content)
        os.chmod(tmp,p.stat().st_mode & 0o777)
        os.replace(tmp,p)
    finally:
        if os.path.exists(tmp):os.unlink(tmp)
def nav_patch(data):
    if "erp.job-applications.index" in data or "/erp/job-applications" in data:
        return data,"ALREADY_PRESENT"
    # Duplicate existing attendance <a> style/parent for nav consistency.
    # Only a simple standalone link is eligible; do not modify Blade loops.
    hits=list(re.finditer(
        r'<a\b[^>]*\bhref\s*=\s*["\x27][^"\x27]*'
        r"route\(['\x22]erp\.attendance['\x22]\)"
        r'[^"\x27]*["\x27][^>]*>[\s\S]{0,350}?</a>',
        data,re.I
    ))
    if len(hits)!=1:return data,"NO_UNAMBIGUOUS_ATTENDANCE_LINK"
    h=hits[0]
    orig=h.group()
    if "@foreach" in orig or "@if" in orig:return data,"NAV_TEMPLATE_COMPLEX"
    clone=orig.replace("erp.attendance","erp.job-applications.index",1)
    clone=re.sub(r'\bAttendance\b','Job Applications',clone,flags=re.I)
    if "Job Applications" not in clone:return data,"NAV_LABEL_UNAVAILABLE"
    # Avoid cloned conditional active-state syntax inadvertently matching
    # Attendance only; route changes above provide the correct active route.
    return data[:h.end()]+"\n"+clone+data[h.end():],"LINK_CLONED_FROM_ATTENDANCE"

if mode=="rollback":
    saved=backup_dir/"routes_web_before_AM180.php"
    if not saved.is_file():raise SystemExit("AM180_STOP_ROLLBACK_BACKUP_MISSING")
    saved_route=get_text(saved)
    if marker in saved_route:raise SystemExit("AM180_STOP_ROLLBACK_BACKUP_NOT_PRISTINE")
    if marker not in get_text(routes):raise SystemExit("AM180_STOP_NOT_INSTALLED")
    atomic_write(routes,saved_route)
    for p in nav_candidates:
        b=backup_dir/(p.name+".nav.before_AM180")
        if b.is_file() and p.is_file():
            atomic_write(p,get_text(b))
    print("AM180_ROLLBACK_FILES_RESTORED")
    print("RUN_PHP_ARTISAN_ROUTE_CLEAR")
    sys.exit(0)

current=get_text(routes)
controller=get_text(ctl)
for name in expected_methods:
    if not re.search(r"\bfunction\s+"+name+r"\s*\(",controller):
        raise SystemExit("AM180_STOP_CONTROLLER_METHOD_MISSING_"+name)
for route_name in ("erp.job-applications.status","erp.job-applications.delete"):
    if route_name not in get_text(view):
        raise SystemExit("AM180_STOP_VIEW_MISSING_EXPECTED_ROUTE_"+route_name)
if marker in current:
    print("AM180_ROUTES_ALREADY_INSTALLED")
    sys.exit(0)
if re.search(r"\bjob-applications\b",current):
    raise SystemExit("AM180_STOP_ROUTE_FILE_ALREADY_CONTAINS_JOB_APPLICATIONS_REVIEW")
if not current.lstrip().startswith("<?php"):
    raise SystemExit("AM180_STOP_ROUTES_FILE_NOT_PHP")
if "<?" in current[5:]:
    # Embedded templates / opening PHP tags could mean concatenation unsafe.
    raise SystemExit("AM180_STOP_UNEXPECTED_PHP_TAGS_IN_ROUTES")
if current.rstrip().endswith("?>"):
    raise SystemExit("AM180_STOP_ROUTES_WITH_CLOSING_PHP_TAG")
candidate=current.rstrip()+"\n"+snippet+"\n"
valid,diag=php_lint(candidate)
if not valid:raise SystemExit("AM180_STOP_ROUTE_PHP_SYNTAX_FAIL "+diag)
print("AM180_ROUTE_PHP_SYNTAX_PASS")
print("AM180_EXISTING_APPLICATION_DATA_UNTOUCHED")
print("AM180_ROUTES_TO_ADD GET_INDEX POST_STATUS DELETE_APPLICATION")
print("AM180_AUTH web auth:web EnsureErpAdmin")
nav_plan=[]
for p in nav_candidates:
    if p.is_file():
        updated,status=nav_patch(get_text(p))
        if status=="LINK_CLONED_FROM_ATTENDANCE":
            nav_plan.append((p,updated))
            print("AM180_NAV_LINK_TARGET",p.relative_to(root))
            break
if not nav_plan:
    print("AM180_NAV_LINK_SKIP_DIRECT_URL_STILL_AVAILABLE")
if mode=="check":
    print("AM180_CHECK_PASS_NO_WRITES")
    sys.exit(0)
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
route_backup=backup_dir/"routes_web_before_AM180.php"
if route_backup.exists():
    raise SystemExit("AM180_STOP_BACKUP_ALREADY_EXISTS")
shutil.copy2(routes,route_backup)
os.chmod(route_backup,0o600)
for p,new_text in nav_plan:
    saved=backup_dir/(p.name+".nav.before_AM180")
    if saved.exists():raise SystemExit("AM180_STOP_NAV_BACKUP_EXISTS")
    shutil.copy2(p,saved)
    os.chmod(saved,0o600)
atomic_write(routes,candidate)
for p,new_text in nav_plan:atomic_write(p,new_text)
print("AM180_ERP_JOB_APPLICATIONS_ROUTES_INSTALLED")
if nav_plan:print("AM180_SIDEBAR_LINK_INSTALLED")
print("AM180_BACKUP_IN_STAGING_STORAGE")
print("AM180_NEED_ROUTE_CACHE_CLEAR_AND_BROWSER_QA")
