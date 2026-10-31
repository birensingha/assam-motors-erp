#!/usr/bin/env python3
"""AM221 read-only inventory of Vehicle Make/Model/Variant, Job Card customer/vehicle
relations and inspection stage. No DB row values, credentials or user data.
"""
from pathlib import Path
import re,sys,os,subprocess,tempfile
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!='assam-erp-staging' or not (root/'artisan').is_file():
    raise SystemExit('AM221_STOP_NOT_STAGING')
print('AM221_BEGIN_READONLY')
def redact(line):
    if re.search(r'(?i)(password|passwd|secret|private_key|api_key|authorization|bearer |->getenv|\\.env)',line):
        return '[SOURCE_REDACTED]'
    return line.strip()[:750]
def excerpt(path,label,limit=120):
    if not path.exists():print('AM221_MISSING',label,str(path.name));return
    content=path.read_text('utf8','replace').splitlines()
    print('AM221_FILE_BEGIN',label,'LINES',len(content))
    for i,line in enumerate(content[:limit],1):
        print('AM221_LINE',label,i,redact(line))
    print('AM221_FILE_END',label)
for name in ('vehicles','parts'):
    excerpt(root/('resources/views/admin/native/'+name+'.blade.php'),name.upper(),105 if name=='vehicles' else 23)
for name in ('create','form'):
    excerpt(root/('resources/views/job-cards/'+name+'.blade.php'),'JOB_'+name.upper(),120)
for parent in (root/'resources/views/admin/native',root/'resources/views/job-cards'):
    if not parent.is_dir():continue
    for p in sorted(parent.glob('*.blade.php')):
        if re.search(r'(?i)(vehicle|customer|job)',p.stem):
            print('AM221_VIEW_CANDIDATE',str(p.relative_to(root)),p.stat().st_size)
controller=root/'app/Http/Controllers/Web/AdminNativeModuleController.php'
if controller.exists():
    lines=controller.read_text('utf8','replace').splitlines()
    for name in ('vehicles','saveVehicle','createJobCard','storeJobCard'):
        hits=[i for i,line in enumerate(lines) if re.search(r'\bfunction\s+'+name+r'\s*\(',line)]
        print('AM221_NATIVE_METHOD',name,len(hits))
        for i in hits[:1]:
            for j in range(i,min(i+75,len(lines))):
                print('AM221_NATIVE',name,j+1,redact(lines[j]))
controllers=root/'app/Http/Controllers'
patterns=re.compile(r'(customer_id|vehicle_id|customer.*vehicle|vehicle.*customer|vehicle_makes|vehicle_models|vehicle_variants|am_vehicle_catalog_master)')
if controllers.is_dir():
    matches=[]
    for p in controllers.rglob('*.php'):
        if p.stat().st_size>200000:continue
        s=p.read_text('utf8','replace')
        if ('job' in p.name.lower() or 'vehicle' in p.name.lower()) and patterns.search(s):
            matches.append(p)
    print('AM221_CONTROLLER_CANDIDATES',len(matches))
    for p in matches[:19]:
        lines=p.read_text('utf8','replace').splitlines()
        ix=[i for i,l in enumerate(lines) if re.search(r'\bfunction\s+(create|store|save|index|edit|update)\b',l)
            or ('customer_id' in l and 'vehicle_id' in l)]
        print('AM221_CONTROLLER_PATH',str(p.relative_to(root)), 'METHOD_LINES',','.join(str(i+1) for i in ix[:20]))
        if re.search(r'(?i)JobCardController|VehicleController',p.name):
            for i in ix[:8]:
                for j in range(i,min(i+23,len(lines))):
                    print('AM221_CTRLINE',p.name,j+1,redact(lines[j]))
php=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
foreach(['customers','customer_vehicles','vehicles','vehicle_makes','vehicle_models','vehicle_variants','am_vehicle_catalog_master','job_cards','am206_inspection_master_versions','am206_inspection_job_snapshots','am189_inspection_defects'] as $table){
 if(!Schema::hasTable($table)){echo "AM221_TABLE_MISSING ".$table."\n";continue;}
 echo "AM221_TABLE ".$table." COLUMNS ".implode(',',Schema::getColumnListing($table))."\n";
}
foreach(app('router')->getRoutes() as $route){
 $name=(string)$route->getName();$uri=(string)$route->uri();
 if(preg_match('~erp/(job-cards/create|vehicles|configuration/inspection-master|inspection-defects)|^api/v1/staff/inspections~',$uri)){
  echo "AM221_ROUTE ".implode(',',$route->methods())." ".$uri." NAME ".$name." HANDLER ".$route->getActionName()."\n";
 }
}
"""
with tempfile.TemporaryDirectory(prefix='am221audit_') as temp:
    f=Path(temp)/'audit.php';f.write_text(php,encoding='utf8')
    r=subprocess.run(['php',str(f),str(root)],cwd=root,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True,timeout=45)
    if r.returncode:print('AM221_META_UNAVAILABLE',r.returncode)
    else:print(r.stdout[:18000])

# AM221 Focused 2nd pass: Job Card relation and form controls only, never data rows.
form=root/'resources/views/job-cards/form.blade.php'
if form.is_file():
    ll=form.read_text('utf8','replace').splitlines()
    for j in range(119,min(230,len(ll))):
        print('AM221_FORM_DETAIL',j+1,redact(ll[j]))
jc=root/'app/Http/Controllers/Web/AdminJobCardPageController.php'
if jc.is_file():
    ll=jc.read_text('utf8','replace').splitlines()
    for low,high in ((15,165),(347,432)):
        for j in range(low-1,min(high,len(ll))):
            print('AM221_JC_CONTROLLER',j+1,redact(ll[j]))
php2=r"""<?php
require $argv[1].'/vendor/autoload.php';
$app=require $argv[1].'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
foreach(['am_customer_master','am_vehicle_master','customer_vehicles','customer_master','am_vehicle_catalog_master'] as $table){
 if(!Schema::hasTable($table))continue;
 echo "AM221_REAL_MASTER_TABLE ".$table." COLUMNS ".implode(',',Schema::getColumnListing($table))."\n";
}
"""
with tempfile.TemporaryDirectory(prefix='am221rel_') as td:
    p=Path(td)/'read.php';p.write_text(php2,encoding='utf8')
    out=subprocess.run(['php',str(p),str(root)],cwd=root,text=True,capture_output=True,timeout=30)
    if out.returncode:print('AM221_RELATION_SCHEMA_UNAVAILABLE')
    else:print(out.stdout)

# Targeted ownership verification in actual Save/Update controller source
if jc.is_file():
    lines=jc.read_text('utf8','replace').splitlines()
    for low,high in ((156,248),(395,462)):
        for j in range(low-1,min(high,len(lines))):
            print('AM221_SAVE_DETAIL',j+1,redact(lines[j]))
print('AM221_READONLY_COMPLETE_NO_DB_WRITES')
