#!/usr/bin/env python3
"""AM180 checked installation, STAGING ONLY.
Call: python3 install.py check|apply /full/path/to/assam-erp-staging
Does not migrate, alter inspection submissions, or change QC behaviour.
"""
import os
import sys
import shutil
import tempfile
from pathlib import Path
from datetime import datetime

if len(sys.argv)!=3 or sys.argv[1] not in ('check','apply'):
    raise SystemExit('Usage: python3 install.py check|apply STAGING_LARAVEL_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
package=Path(__file__).resolve().parent/'files'
if not (root/'artisan').is_file() or not (root/'routes/web.php').is_file():
    raise SystemExit('AM180_STOP_NOT_LARAVEL_PROJECT')
if 'staging' not in str(root).lower():
    raise SystemExit('AM180_STOP_NOT_STAGING_PATH')

manifest=[
 'app/Services/InspectionMasterService.php',
 'app/Services/InspectionDefectService.php',
 'app/Http/Controllers/Web/InspectionMasterController.php',
 'resources/views/inspection-master/index.blade.php',
 'database/migrations/2026_10_28_185000_create_am_inspection_master_defects.php',
]
for rel in manifest:
    source=package/rel
    dest=root/rel
    if not source.is_file():
        raise SystemExit('AM180_STOP_PACKAGE_FILE_MISSING '+rel)
    if dest.exists() and dest.read_bytes()!=source.read_bytes():
        raise SystemExit('AM180_STOP_EXISTING_DIFFERENT_FILE '+rel)

routes_file=root/'routes/web.php'
source_routes=routes_file.read_text(encoding='utf8')
admin_anchor="    Route::get('/job-cards/{job}/inspection-report', [AdminJobCardPageController::class,'inspectionReport'])->whereNumber('job')->name('erp.job-cards.inspection-report');"
staff_anchor="    Route::get('/inspections/{job}/report', [StaffPortalController::class,'inspectionReport'])->whereNumber('job')->name('staff.inspection-report');"

admin_extra="""    // AM180_INSPECTION_MASTER_ROUTES: inherits existing protected ERP routes.
    Route::get('/configuration/inspection-master', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'index'])->name('erp.inspection-master.index');
    Route::post('/configuration/inspection-master/publish', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'publish'])->name('erp.inspection-master.publish');
    Route::get('/job-cards/{job}/inspection-defects', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'defects'])->whereNumber('job')->name('erp.inspection-defects.index');
    Route::post('/inspection-defects/{defect}/rectify', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'rectify'])->whereNumber('defect')->name('erp.inspection-defects.rectify');
    Route::post('/inspection-defects/{defect}/verify', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'verify'])->whereNumber('defect')->name('erp.inspection-defects.verify');
"""
staff_extra="""    // AM180_INSPECTION_STAFF_ROUTES: inherits existing protected Staff routes.
    Route::get('/inspections/{job}/checklist-v2', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'jobChecklist'])->whereNumber('job')->name('staff.inspection-checklist-v2');
    Route::get('/inspections/{job}/defects', [\\App\\Http\\Controllers\\Web\\InspectionMasterController::class,'defects'])->whereNumber('job')->name('staff.inspection-defects');
"""
has_admin='AM180_INSPECTION_MASTER_ROUTES' in source_routes
has_staff='AM180_INSPECTION_STAFF_ROUTES' in source_routes
if has_admin!=has_staff:
    raise SystemExit('AM180_STOP_PARTIAL_ROUTE_INSTALL')
if has_admin:
    new_routes=source_routes
else:
    if source_routes.count(admin_anchor)!=1 or source_routes.count(staff_anchor)!=1:
        raise SystemExit('AM180_STOP_AUTH_ROUTE_ANCHORS_CHANGED')
    new_routes=source_routes.replace(admin_anchor,admin_anchor+'\n'+admin_extra,1).replace(
        staff_anchor,staff_anchor+'\n'+staff_extra,1)

print('AM180_STAGE_PREFLIGHT')
print('AM180_ROUTE_ANCHORS', 'already installed' if has_admin else 'exact match confirmed')
print('AM180_EXISTING_MODULE_FILES',sum((root/rel).is_file() for rel in manifest))
print('AM180_PACKAGE_FILES',len(manifest))
print('AM180_NO_EXISTING_JOB_CARD_OR_QC_MUTATIONS')
if mode=='check':
    print('AM180_DRY_RUN_PASS_NO_WRITES')
    sys.exit(0)
if os.environ.get('AM180_ALLOW_STAGING')!='YES':
    raise SystemExit('AM180_STOP_EXPLICIT_STAGING_APPROVAL_REQUIRED')
backup=root/'storage/app/am-backups'/('AM180_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False)
shutil.copy2(routes_file,backup/'web.php.before-am180')
for rel in manifest:
    dest=root/rel
    if dest.is_file(): continue
    dest.parent.mkdir(parents=True,exist_ok=True)
    fd,tmp=tempfile.mkstemp(dir=dest.parent,prefix='.am180_')
    try:
        with os.fdopen(fd,'wb') as handle: handle.write((package/rel).read_bytes())
        os.replace(tmp,dest)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)
if not has_admin:
    fd,tmp=tempfile.mkstemp(dir=routes_file.parent,prefix='.am180_routes_')
    try:
        with os.fdopen(fd,'w',encoding='utf8') as handle: handle.write(new_routes)
        shutil.copymode(backup/'web.php.before-am180',tmp)
        os.replace(tmp,routes_file)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)
print('AM180_FILES_AND_ROUTES_INSTALLED')
print('AM180_BACKUP',backup)
print('AM180_NEXT_REVIEWED_STEP php artisan migrate --path=database/migrations/2026_10_28_185000_create_am_inspection_master_defects.php --force')
print('AM180_NEXT_VERIFY php -l <new PHP files>; php artisan route:list -v; php artisan view:cache')
print('AM180_NOT_WIRED_TO_INSPECTION_SAVE_OR_QC_YET')
