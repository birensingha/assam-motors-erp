#!/usr/bin/env python3
"""AM189 - conservative STAGING ONLY QC defect protection.
check: no writes
prepare: copy new service/controller/view/migration only; no routes or QC edits
activate: require migrated tables, backup two actual QC controllers + routes,
          PHP-lint all replacements, then patch QC PASS branches and Admin routes.
Usage: python3 install.py check|prepare|activate /path/to/assam-erp-staging
"""
from pathlib import Path
from datetime import datetime
import subprocess
import tempfile
import shutil
import sys
import os
import json
import re

if len(sys.argv)!=3 or sys.argv[1] not in ('check','prepare','activate'):
    raise SystemExit('Usage: python3 install.py check|prepare|activate STAGING_LARAVEL_ROOT')
mode=sys.argv[1]
root=Path(sys.argv[2]).expanduser().resolve()
pkg=Path(__file__).resolve().parent/'files'
if 'staging' not in str(root).lower() or not (root/'artisan').is_file() or not (root/'routes/web.php').is_file():
    raise SystemExit('AM189_STOP_NOT_STAGING_LARAVEL')
files=[
 'app/Services/InspectionQcGuard.php',
 'app/Http/Controllers/Web/InspectionQcDefectController.php',
 'resources/views/inspection-defects/index.blade.php',
 'database/migrations/2026_10_29_161500_create_am189_inspection_defects.php',
]
targets=[
 'app/Http/Controllers/Web/StaffQcController.php',
 'app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php',
 'routes/web.php'
]
for rel in files:
    source=pkg/rel
    dest=root/rel
    if not source.is_file(): raise SystemExit('AM189_PACKAGE_MISSING '+rel)
    if dest.is_file() and dest.read_bytes()!=source.read_bytes():
        raise SystemExit('AM189_COLLISION_EXISTING_DIFFERENT_FILE '+rel)
for rel in targets:
    if not (root/rel).is_file(): raise SystemExit('AM189_TARGET_MISSING '+rel)

marker='AM189_INSPECTION_QC_PASS_GUARD'
route_marker='AM189_INSPECTION_DEFECT_ROUTES'

def method(source,name):
    found=list(re.finditer(r'\bpublic\s+function\s+'+re.escape(name)+r'\s*\(',source))
    if len(found)!=1: raise SystemExit('AM189_METHOD_NOT_UNIQUE '+name)
    start=found[0].start()
    end_match=re.search(r'\n\s*(?:public|private|protected)\s+function\s+\w+\s*\(',source[found[0].end():])
    end=found[0].end()+end_match.start() if end_match else len(source)
    return start,end,source[start:end]

def replace_method(src,name,anchor,addition):
    first,last,block=method(src,name)
    if marker in block:
        return src,True
    if block.count(anchor)!=1:
        raise SystemExit('AM189_QC_ANCHOR_CHANGED '+name+' count='+str(block.count(anchor)))
    changed=block.replace(anchor,anchor+'\n'+addition,1)
    return src[:first]+changed+src[last:],False

staff_rel=targets[0]
admin_rel=targets[1]
web_rel=targets[2]
staff=(root/staff_rel).read_text(encoding='utf8')
admin=(root/admin_rel).read_text(encoding='utf8')
web=(root/web_rel).read_text(encoding='utf8')
staff_new,staff_installed=replace_method(staff,'submit',"$result=$data['result'];",
    "            // "+marker+" - inside existing locked Job Card transaction.\n"
    "            if ($result==='PASS') app(\\App\\Services\\InspectionQcGuard::class)->assertQcReady($job);")
admin_new,admin_installed=replace_method(admin,'qcResult',"$before=$card->toArray();",
    "            // "+marker+" - Admin and Staff QC PASS use one authoritative rule.\n"
    "            if ($data['result']==='PASS') app(\\App\\Services\\InspectionQcGuard::class)->assertQcReady($job);")
anchor="Route::get('/job-cards/{job}/inspection-report', [AdminJobCardPageController::class,'inspectionReport'])->whereNumber('job')->name('erp.job-cards.inspection-report');"
new_routes="""
    // AM189_INSPECTION_DEFECT_ROUTES - inherits ERP Owner/Admin middleware.
    Route::get('/job-cards/{job}/inspection-defects', [\\App\\Http\\Controllers\\Web\\InspectionQcDefectController::class,'index'])->whereNumber('job')->name('erp.job-cards.inspection-defects');
    Route::post('/job-cards/{job}/inspection-defects/sync', [\\App\\Http\\Controllers\\Web\\InspectionQcDefectController::class,'sync'])->whereNumber('job')->name('erp.job-cards.inspection-defects.sync');
    Route::post('/inspection-defects/{defect}/repair', [\\App\\Http\\Controllers\\Web\\InspectionQcDefectController::class,'rectify'])->whereNumber('defect')->name('erp.job-cards.inspection-defects.repair');
    Route::post('/inspection-defects/{defect}/verify', [\\App\\Http\\Controllers\\Web\\InspectionQcDefectController::class,'verify'])->whereNumber('defect')->name('erp.job-cards.inspection-defects.verify');
"""
if route_marker not in web:
    if web.count(anchor)!=1: raise SystemExit('AM189_ADMIN_ROUTE_ANCHOR_CHANGED')
    web_new=web.replace(anchor,anchor+'\n'+new_routes,1)
    route_installed=False
else:
    web_new=web
    route_installed=True
if len({staff_installed,admin_installed,route_installed})!=1:
    raise SystemExit('AM189_PARTIAL_PREVIOUS_ACTIVATION_REQUIRES_REVIEW')

def lint(text,label):
    with tempfile.NamedTemporaryFile(mode='w',suffix='.php',encoding='utf8',delete=False) as fh:
        fh.write(text)
        path=Path(fh.name)
    try:
        p=subprocess.run(['php','-l',str(path)],capture_output=True,text=True)
        if p.returncode: raise SystemExit('AM189_PHP_LINT_FAIL '+label+' '+p.stdout+' '+p.stderr)
    finally:
        path.unlink(missing_ok=True)

lint(staff_new,'StaffQcController')
lint(admin_new,'AdminJobCardLifecyclePageController')
lint(web_new,'routes/web')
for rel in files:
    if rel.endswith('.php'): lint((pkg/rel).read_text(encoding='utf8'),rel)

print('AM189_PREFLIGHT_PASS')
print('AM189_QC_HANDLERS_PASS AdminJobCardLifecyclePageController@qcResult / StaffQcController@submit')
print('AM189_ROUTES_PROTECTED_BY_EXISTING_ERP_GROUP verify_after_activation')
print('AM189_ALREADY_ACTIVE',staff_installed and admin_installed and route_installed)
print('AM189_NEW_FILES',len(files))
print('AM189_CHECK_HAS_NO_CUSTOMER_DATA_WRITES')
if mode=='check':
    print('AM189_CHECK_COMPLETE_NO_WRITES')
    sys.exit(0)

def atomic_write(path,contents):
    fd,tmp=tempfile.mkstemp(prefix='.am189_',dir=path.parent)
    try:
        with os.fdopen(fd,'w',encoding='utf8') as handle: handle.write(contents)
        shutil.copymode(path,tmp)
        os.replace(tmp,path)
    finally:
        if os.path.exists(tmp): os.unlink(tmp)

if mode=='prepare':
    for rel in files:
        dest=root/rel
        dest.parent.mkdir(parents=True,exist_ok=True)
        if not dest.is_file():
            shutil.copy2(pkg/rel,dest)
    print('AM189_PREPARED_NEW_FILES_ONLY_NO_QC_ROUTE_CHANGES')
    print('NEXT: cd '+str(root))
    print('NEXT: php artisan migrate --path=database/migrations/2026_10_29_161500_create_am189_inspection_defects.php --force')
    print('NEXT: python3 '+str(Path(__file__).resolve())+' activate '+str(root))
    sys.exit(0)

if os.environ.get('AM189_STAGING_APPROVED')!='YES':
    raise SystemExit('AM189_STOP_SET_AM189_STAGING_APPROVED=YES_BEFORE_ACTIVATE')
for rel in files:
    if not (root/rel).is_file(): raise SystemExit('AM189_PREPARE_REQUIRED '+rel)
# Confirm both tables in the EXACT staging Laravel DB before protecting QC.
probe=("""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
echo json_encode([
 'defects'=>\\Illuminate\\Support\\Facades\\Schema::hasTable('am189_inspection_defects'),
 'audits'=>\\Illuminate\\Support\\Facades\\Schema::hasTable('am189_inspection_defect_audits')
]);
""")
p=subprocess.run(['php','-r',probe],cwd=root,capture_output=True,text=True)
if p.returncode:
    raise SystemExit('AM189_DB_PROBE_FAILED '+p.stderr[-650:])
try: migrated=json.loads(p.stdout)
except ValueError: raise SystemExit('AM189_DB_PROBE_NOT_JSON '+p.stdout[-500:])
if not migrated.get('defects') or not migrated.get('audits'):
    raise SystemExit('AM189_STOP_RUN_REVIEWED_MIGRATION_FIRST')
if staff_installed and admin_installed and route_installed:
    print('AM189_ALREADY_ACTIVE_NO_CHANGES');sys.exit(0)

backup=root/'storage/app/am-backups'/('AM189_'+datetime.now().strftime('%Y%m%d_%H%M%S'))
backup.mkdir(parents=True,exist_ok=False)
originals={staff_rel:staff,admin_rel:admin,web_rel:web}
for rel in targets:
    dest=backup/rel
    dest.parent.mkdir(parents=True,exist_ok=True)
    shutil.copy2(root/rel,dest)
new_map={staff_rel:staff_new,admin_rel:admin_new,web_rel:web_new}
try:
    for rel in targets: atomic_write(root/rel,new_map[rel])
    subprocess.run(['php','artisan','route:clear'],cwd=root,check=True,capture_output=True,text=True)
    listed=subprocess.run(['php','artisan','route:list','--json'],cwd=root,check=True,capture_output=True,text=True)
    routes=json.loads(listed.stdout)
    new_uris=['erp/job-cards/{job}/inspection-defects',
              'erp/job-cards/{job}/inspection-defects/sync',
              'erp/inspection-defects/{defect}/repair',
              'erp/inspection-defects/{defect}/verify']
    for uri in new_uris:
        found=[x for x in routes if x.get('uri')==uri]
        if len(found)!=1 or not any('EnsureErpAdmin' in str(m) for m in found[0].get('middleware',[])):
            raise RuntimeError('Missing/unauthorized route '+uri)
except Exception as exc:
    for rel in targets: shutil.copy2(backup/rel,root/rel)
    subprocess.run(['php','artisan','route:clear'],cwd=root,check=False,capture_output=True,text=True)
    raise SystemExit('AM189_ACTIVATE_ABORT_RESTORED_BACKUPS '+str(exc))
print('AM189_ACTIVATED_TWO_QC_PASS_GUARDS_AND_ADMIN_REGISTER')
print('AM189_BACKUP',backup)
print('AM189_ROUTE_AUTH_PASS')
print('AM189_NO_EXISTING_JOB_CARD_INSPECTION_MUTATIONS')
