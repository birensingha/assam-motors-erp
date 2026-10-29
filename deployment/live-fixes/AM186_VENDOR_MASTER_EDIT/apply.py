#!/usr/bin/env python3
"""AM186 Vendor Master Edit in NEW ERP.

check  - read-only check of current Vendor module/controller/schema/files
apply  - backup and install admin routes, edit controller, view, audit migration,
         and a visible "Edit Vendor Master" link in current Vendor page
rollback - restore original routes/index view without deleting audit records

No changes to vendor records during installation.
Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT
"""
from pathlib import Path
import json,os,re,shutil,subprocess,sys,tempfile

if len(sys.argv)!=3 or sys.argv[1] not in ("check","apply","rollback"):
    raise SystemExit("Usage: python3 apply.py check|apply|rollback LARAVEL_STAGING_ROOT")
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent
if not (root/"artisan").is_file():raise SystemExit("AM186_STOP_NOT_STAGING_LARAVEL")
routes=root/"routes/web.php"
vendor_index=root/"resources/views/vendors/index.blade.php"
controller=root/"app/Http/Controllers/Web/AdminVendorMasterEditController.php"
edit_view=root/"resources/views/vendors/master-edit.blade.php"
migration_name="2026_10_29_120000_create_workshop_vendor_master_audits_table.php"
migration=root/"database/migrations"/migration_name
backup_dir=root/"storage/app/am-backups/AM186_VENDOR_MASTER_EDIT"
routes_backup=backup_dir/"web.php.before_AM186"
vendor_backup=backup_dir/"vendors-index.blade.php.before_AM186"
marker="AM186_VENDOR_MASTER_EDIT"
read=lambda p:p.read_text(encoding="utf8",errors="surrogateescape")
def write_atomic(path,contents):
    fd,temp=tempfile.mkstemp(dir=path.parent,prefix=".am186_")
    try:
        with os.fdopen(fd,"w",encoding="utf8",errors="surrogateescape") as f:
            f.write(contents)
        os.chmod(temp, path.stat().st_mode&0o777 if path.exists() else 0o644)
        os.replace(temp,path)
    finally:
        if os.path.exists(temp):os.unlink(temp)
def php_lint(contents):
    with tempfile.TemporaryDirectory(prefix="am186lint_") as d:
        p=Path(d)/"target.php";p.write_text(contents,encoding="utf8",errors="surrogateescape")
        result=subprocess.run(["php","-l",str(p)],capture_output=True,text=True,timeout=20)
        return result.returncode==0,(result.stdout+result.stderr)[-250:]

if mode=="rollback":
    if not routes_backup.is_file() or not vendor_backup.is_file():
        raise SystemExit("AM186_STOP_ROLLBACK_BACKUPS_MISSING")
    if marker not in read(routes) or marker not in read(vendor_index):
        raise SystemExit("AM186_STOP_MARKERS_NOT_INSTALLED")
    # The created controller/view/migration belong only to this patch.
    # Keep the migration and audit TABLE on rollback to preserve audit history.
    write_atomic(routes,read(routes_backup))
    write_atomic(vendor_index,read(vendor_backup))
    for p in [controller,edit_view]:
        if p.is_file():
            p.unlink()
    print("AM186_ROLLED_BACK_ROUTES_AND_VENDOR_UI")
    print("AM186_AUDIT_HISTORY_AND_MIGRATION_PRESERVED")
    print("AM186_RUN_ARTISAN_ROUTE_CLEAR_VIEW_CLEAR")
    sys.exit(0)

if not (routes.is_file() and vendor_index.is_file() and
        (root/"app/Http/Controllers/Web/AdminVendorPageController.php").is_file() and
        (root/"app/Services/VendorService.php").is_file()):
    raise SystemExit("AM186_STOP_EXISTING_VENDOR_MODULE_MISSING")
for p in [package/"AdminVendorMasterEditController.php",package/"master-edit.blade.php",package/migration_name]:
    if not p.is_file():raise SystemExit("AM186_STOP_PATCH_COMPONENT_MISSING "+p.name)
for p in [controller,edit_view,migration]:
    if p.exists():raise SystemExit("AM186_STOP_TARGET_ALREADY_EXISTS "+p.name)
if marker in read(routes) or marker in read(vendor_index):
    raise SystemExit("AM186_STOP_ALREADY_INSTALLED")
if not routes.read_text(encoding="utf8").lstrip().startswith("<?php"):
    raise SystemExit("AM186_STOP_WEB_ROUTES_UNEXPECTED_FORMAT")
if routes.read_text(encoding="utf8").rstrip().endswith("?>"):
    raise SystemExit("AM186_STOP_WEB_ROUTES_HAS_CLOSE_TAG")

view_src=read(vendor_index)
if view_src.count("@section('content')")!=1:
    raise SystemExit("AM186_STOP_VENDOR_VIEW_CONTENT_SECTION_NOT_UNIQUE")
if "erp.vendors.index" not in read(routes):
    raise SystemExit("AM186_STOP_VENDOR_INDEX_ROUTE_NAME_MISSING")
if any(x in read(routes) for x in
       ("erp.vendors.master.manage","erp.vendors.master.update","vendor-master/manage")):
    raise SystemExit("AM186_STOP_VENDOR_EDIT_ROUTE_CONFLICT")

# Read-only schema compatibility; never print vendor rows, financial balances,
# addresses, GSTINs or private DB config.
probe=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try{
 $schema=Illuminate\Support\Facades\Schema::getColumnListing('workshop_vendors');
 $out=['schema'=>$schema,'count'=>Illuminate\Support\Facades\DB::table('workshop_vendors')->count()];
 echo json_encode($out);
}catch(Throwable $e){fwrite(STDERR,get_class($e));exit(1);}
"""
with tempfile.TemporaryDirectory(prefix="am186db_") as d:
    p=Path(d)/"schema.php";p.write_text(probe,encoding="utf8")
    r=subprocess.run(["php",str(p),str(root)],capture_output=True,text=True,timeout=35)
    if r.returncode:raise SystemExit("AM186_STOP_SCHEMA_PROBE_FAILED "+r.stderr[:100])
    try:db=json.loads(r.stdout)
    except ValueError:raise SystemExit("AM186_STOP_SCHEMA_PROBE_BAD_RESULT")
required={"id","vendor_name","vendor_code"}
if not required.issubset(db["schema"]):
    raise SystemExit("AM186_STOP_VENDOR_SCHEMA_MISSING_REQUIRED_FIELDS")
print("AM186_EXISTING_VENDOR_ROWS",db["count"])
print("AM186_VENDOR_MASTER_FIELDS",",".join(k for k in
    ("vendor_name","mobile","email","gstin","address","city","state",
     "pincode","contact_person","pan","bank_name","bank_account","ifsc","notes")
    if k in db["schema"]))

route_add=r"""

/* AM186_VENDOR_MASTER_EDIT — authenticated Vendor Master corrections.
 * Vendor ID/code, purchases, payments, ledger are never updated here.
 */
\Illuminate\Support\Facades\Route::middleware([
    'web','auth:web',\App\Http\Middleware\EnsureErpAdmin::class
])->group(function() {
    \Illuminate\Support\Facades\Route::get('/erp/vendor-master/manage',
        [\App\Http\Controllers\Web\AdminVendorMasterEditController::class,'index']
    )->name('erp.vendors.master.manage');
    \Illuminate\Support\Facades\Route::post('/erp/vendor-master/{vendor}/update',
        [\App\Http\Controllers\Web\AdminVendorMasterEditController::class,'update']
    )->whereNumber('vendor')->name('erp.vendors.master.update');
});
"""
routes_new=read(routes).rstrip()+"\n"+route_add
if not php_lint(routes_new)[0]:raise SystemExit("AM186_STOP_ROUTES_PHP_LINT_FAILED")
for source in [package/"AdminVendorMasterEditController.php",package/migration_name]:
    ok,output=php_lint(read(source))
    if not ok:raise SystemExit("AM186_STOP_PHP_LINT_FAILED "+source.name+" "+output)
edit_src=read(package/"master-edit.blade.php")
if "@extends('layouts.erp')" not in edit_src or "route('erp.vendors.master.update'" not in edit_src:
    raise SystemExit("AM186_STOP_EDIT_VIEW_MARKUP_UNEXPECTED")
button=r"""
{{-- AM186_VENDOR_MASTER_EDIT: safe dedicated Vendor correction panel --}}
<div style="display:flex;justify-content:flex-end;align-items:center;gap:8px;margin:0 0 12px">
  <a href="{{ route('erp.vendors.master.manage') }}" style="display:inline-flex;align-items:center;
  min-height:36px;padding:7px 14px;background:#175aa9;color:#fff!important;
  border-radius:7px;text-decoration:none;font-size:12px;font-weight:700">
    Edit Vendor Master
  </a>
</div>
"""
new_view=view_src.replace("@section('content')","@section('content')\n"+button,1)
print("AM186_ROUTES_AND_CONTROLLER_PHP_LINT_PASS")
print("AM186_VENDOR_LIST_EDIT_BUTTON_READY")
print("AM186_PROPOSED_ROUTES GET_erp_vendor-master_manage POST_erp_vendor-master_vendor_update")
print("AM186_AUDIT_TABLE_CREATION_REQUIRED_AFTER_INSTALL")
if mode=="check":
    print("AM186_CHECK_PASS_NO_WRITES")
    sys.exit(0)
backup_dir.mkdir(parents=True,exist_ok=True)
os.chmod(backup_dir,0o700)
if routes_backup.exists() or vendor_backup.exists():
    raise SystemExit("AM186_STOP_BACKUP_ALREADY_EXISTS")
shutil.copy2(routes,routes_backup);os.chmod(routes_backup,0o600)
shutil.copy2(vendor_index,vendor_backup);os.chmod(vendor_backup,0o600)
# Atomic replacement of existing files; copied files are brand-new.
write_atomic(routes,routes_new)
write_atomic(vendor_index,new_view)
shutil.copy2(package/"AdminVendorMasterEditController.php",controller)
shutil.copy2(package/"master-edit.blade.php",edit_view)
shutil.copy2(package/migration_name,migration)
print("AM186_VENDOR_EDIT_FILES_INSTALLED")
print("AM186_NEXT_RUN_MIGRATION_AND_CLEAR_ROUTE_VIEW_CACHE")
print("AM186_EXISTING_VENDOR_LEDGER_PAYMENT_PURCHASE_ROWS_UNCHANGED")
