#!/usr/bin/env python3
"""AM187: Single NEW ERP Vendor Master + instant highlight + safe unused delete.

Preflight checks existing AM186 vendor-edit and live vendor create routes.
Apply changes ONLY web.php and two new controllers/service/view and public JS.
Original vendor routes (POST creation, vendor ledger, payment etc) unchanged.
Existing vendor records unaffected during installation; no schema migrations.
Check: python3 apply.py check LARAVEL_STAGING_ROOT
Apply: python3 apply.py apply LARAVEL_STAGING_ROOT
Rollback: python3 apply.py rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import json,os,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
if not (root/"artisan").is_file(): raise SystemExit("AM187_STOP_NOT_STAGING_ROOT")
routes=root/"routes/web.php"
view=root/"resources/views/vendors/master-edit.blade.php"
controller=root/"app/Http/Controllers/Web/AdminVendorMasterConsolidatedController.php"
api_controller=root/"app/Http/Controllers/Web/AdminVendorSearchDeleteController.php"
guard=root/"app/Services/VendorReferenceGuard.php"
js=root/"public/js/vendor-master-search-am187.js"
backup_dir=root/"storage/app/am-backups/AM187_VENDOR_SINGLE_MASTER"
route_backup=backup_dir/"web.php.before_AM187"
view_backup=backup_dir/"master-edit.blade.php.before_AM187"
marker="AM187_SINGLE_VENDOR_MASTER"
targets=[
    (package/"AdminVendorMasterConsolidatedController.php",controller),
    (package/"AdminVendorSearchDeleteController.php",api_controller),
    (package/"VendorReferenceGuard.php",guard),
    (package/"vendor-lookup.js",js),
]
read=lambda p:p.read_text(encoding="utf8",errors="surrogateescape")
def atomic(p,content):
    fd,t=tempfile.mkstemp(prefix=".am187_",dir=p.parent)
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:f.write(content)
        os.chmod(t,p.stat().st_mode & 0o777 if p.exists() else 0o644)
        os.replace(t,p)
    finally:
        if os.path.exists(t):os.unlink(t)
def php_lint(src):
    with tempfile.TemporaryDirectory(prefix="am187lint_") as d:
        file=Path(d)/"check.php"
        file.write_text(src,encoding="utf8",errors="surrogateescape")
        p=subprocess.run(["php","-l",str(file)],capture_output=True,text=True,timeout=30)
        if p.returncode:raise SystemExit("AM187_STOP_PHP_LINT "+(p.stdout+p.stderr)[-220:])
def route_check():
    php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$a=[];
foreach($app->make('router')->getRoutes() as $r){
 $a[]=['uri'=>(string)$r->uri(),'name'=>(string)$r->getName(),
 'method'=>$r->methods(),'action'=>(string)$r->getActionName(),
 'middleware'=>$r->gatherMiddleware()];
}
echo json_encode($a);
"""
    with tempfile.TemporaryDirectory(prefix="am187routes_") as d:
        f=Path(d)/"routes.php";f.write_text(php,encoding="utf8")
        p=subprocess.run(["php",str(f),str(root)],capture_output=True,text=True,timeout=30)
        if p.returncode:raise SystemExit("AM187_STOP_RUNTIME_ROUTE_PROBE_FAILED")
        try:return json.loads(p.stdout)
        except ValueError:raise SystemExit("AM187_STOP_RUNTIME_ROUTE_BAD_JSON")

if mode=="rollback":
    if not route_backup.is_file() or not view_backup.is_file():
        raise SystemExit("AM187_STOP_BACKUP_MISSING")
    if marker not in read(routes):
        raise SystemExit("AM187_STOP_NOT_INSTALLED")
    php_lint(read(route_backup))
    atomic(routes,read(route_backup))
    atomic(view,read(view_backup))
    for original,dest in targets:
        if dest.is_file():dest.unlink()
    print("AM187_ROLLBACK_VENDOR_ROUTES_AND_VIEW")
    print("AM187_FINANCIAL_AND_VENDOR_DATA_UNTOUCHED")
    print("AM187_RUN_ARTISAN_ROUTE_CLEAR_VIEW_CLEAR")
    sys.exit(0)

needed=[
    routes,view,
    root/"app/Http/Controllers/Web/AdminVendorPageController.php",
    root/"app/Http/Controllers/Web/AdminVendorMasterEditController.php",
    root/"app/Services/VendorService.php",
    root/"database/migrations/2026_10_29_120000_create_workshop_vendor_master_audits_table.php",
    package/"master.blade.php",
]
if not all(p.is_file() for p in needed):
    raise SystemExit("AM187_STOP_AM186_OR_VENDOR_SOURCE_MISSING")
if marker in read(routes):raise SystemExit("AM187_ALREADY_INSTALLED")
if not read(view).lstrip().startswith("@extends('layouts.erp')"):
    raise SystemExit("AM187_STOP_AM186_VIEW_UNEXPECTED")
if not read(routes).lstrip().startswith("<?php") or read(routes).rstrip().endswith("?>"):
    raise SystemExit("AM187_STOP_ROUTES_UNEXPECTED")
for src,dest in targets:
    if not src.is_file():raise SystemExit("AM187_STOP_PATCH_FILE_MISSING "+src.name)
    if dest.exists():raise SystemExit("AM187_STOP_TARGET_ALREADY_EXISTS "+dest.name)
    if src.suffix==".php":php_lint(read(src))
routes_runtime=route_check()
def matches(name,method):
    return [x for x in routes_runtime if x["name"]==name and method in x["method"]]
for name,method,uri in (
    ("erp.vendors.index","GET","erp/vendors"),
    ("erp.vendors.master.manage","GET","erp/vendor-master/manage"),
    ("erp.vendors.master.update","POST","erp/vendor-master/{vendor}/update"),
):
    hits=matches(name,method)
    if len(hits)!=1 or hits[0]["uri"]!=uri:
        raise SystemExit("AM187_STOP_ROUTE_CONTRACT_CHANGED_"+name)
    if not any("EnsureErpAdmin" in v for v in hits[0]["middleware"]):
        raise SystemExit("AM187_STOP_ADMIN_AUTH_MISSING_"+name)
if not any(x for x in routes_runtime if x["uri"]=="erp/vendors" and
           "POST" in x["method"] and
           "AdminVendorPageController" in x["action"]):
    raise SystemExit("AM187_STOP_EXISTING_VENDOR_CREATE_POST_MISSING")
if not any(x for x in routes_runtime if x["uri"]=="erp/vendors/{vendor}/ledger" and "GET" in x["method"]):
    raise SystemExit("AM187_STOP_EXISTING_VENDOR_LEDGER_MISSING")
existing_names={x["name"] for x in routes_runtime if x["name"]}
for new in ("erp.vendors.master.search","erp.vendors.master.delete"):
    if new in existing_names:raise SystemExit("AM187_STOP_DUPLICATE_ROUTE_"+new)

route_add=r"""

/* AM187_SINGLE_VENDOR_MASTER
 * Replace GET Vendor Master page with the unified native dashboard.
 * Existing POST /erp/vendors and purchase/payment/ledger actions unaffected.
 * GET aliases deliberately resolve to ONE unified Vendor Master interface.
 */
\Illuminate\Support\Facades\Route::middleware([
    'web','auth:web',\App\Http\Middleware\EnsureErpAdmin::class
])->group(function () {
    \Illuminate\Support\Facades\Route::get('/erp/vendors',
        [\App\Http\Controllers\Web\AdminVendorMasterConsolidatedController::class,'index']
    )->name('erp.vendors.index');
    \Illuminate\Support\Facades\Route::get('/erp/vendor-master/manage',
        [\App\Http\Controllers\Web\AdminVendorMasterConsolidatedController::class,'index']
    )->name('erp.vendors.master.manage');
    \Illuminate\Support\Facades\Route::get('/erp/vendor-master/search',
        [\App\Http\Controllers\Web\AdminVendorSearchDeleteController::class,'search']
    )->name('erp.vendors.master.search');
    \Illuminate\Support\Facades\Route::delete('/erp/vendor-master/{vendor}',
        [\App\Http\Controllers\Web\AdminVendorSearchDeleteController::class,'destroy']
    )->whereNumber('vendor')->name('erp.vendors.master.delete');
});
"""
new_routes=read(routes).rstrip()+"\n"+route_add+"\n"
php_lint(new_routes)
new_view=read(package/"master.blade.php")
if not all(x in new_view for x in [
    "route('erp.vendors.master.delete'","route('erp.vendors.master.search')",
    "route('erp.vendors.master.update'","View Ledger","@csrf"
]):
    raise SystemExit("AM187_STOP_VIEW_REQUIRED_ACTIONS_MISSING")
print("AM187_PREFLIGHT_OK")
print("AM187_SINGLE_VENDOR_MASTER_URL /erp/vendors")
print("AM187_SEARCH_INPUT_DROPDOWN_HIGHLIGHT_ARROW_ENTER")
print("AM187_RIGHT_PANEL_VIEW_LEDGER_EDIT_SAFE_DELETE_AND_ADD")
print("AM187_VENDOR_CREATE_POST_AND_LEDGER_ROUTES_PRESERVED")
print("AM187_DATABASE_CHANGES_NONE_DURING_INSTALL")
if mode=="check":
    print("AM187_CHECK_PASS_NO_WRITES")
    sys.exit(0)
if route_backup.exists() or view_backup.exists():
    raise SystemExit("AM187_STOP_BACKUP_ALREADY_EXISTS")
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
shutil.copy2(routes,route_backup);os.chmod(route_backup,0o600)
shutil.copy2(view,view_backup);os.chmod(view_backup,0o600)
# Preserve all originals for rollback before any live modification.
for src,dest in targets:
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(src,dest)
atomic(view,new_view)
atomic(routes,new_routes)
print("AM187_SINGLE_VENDOR_MASTER_FILES_INSTALLED")
print("AM187_VENDOR_RECORDS_FINANCIAL_RECORDS_UNCHANGED")
print("AM187_NEXT_ROUTE_CLEAR_VIEW_CLEAR_BROWSER_QA")
