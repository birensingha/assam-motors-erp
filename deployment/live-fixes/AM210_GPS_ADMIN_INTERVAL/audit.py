#!/usr/bin/env python3
"""AM210 read-only STAGING GPS interval and Staff auth route audit.
Prints system route metadata and source-code anchors, never user/location values.
"""
from pathlib import Path
import json,re,subprocess,sys
if len(sys.argv)!=2:raise SystemExit("USAGE python3 audit.py STAGING_LARAVEL_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM210_STOP_NOT_STAGING")
def run(cmd):
    proc=subprocess.run(cmd,cwd=root,capture_output=True,text=True,timeout=45)
    if proc.returncode:raise SystemExit("AM210_READONLY_COMMAND_FAILED "+str(proc.returncode))
    return proc.stdout
routes=json.loads(run(["php","artisan","route:list","--json"]))
print("=== GPS LOCATION / SETTINGS ROUTES (AUTH METADATA) ===")
for r in routes:
    uri=r.get("uri","")
    if re.search(r"staff/location|staff-location|reporting.settings|gps.setting",uri,re.I):
        print(json.dumps({"method":r.get("method"),"uri":uri,"action":r.get("action"),
            "name":r.get("name"),"middleware":r.get("middleware")},ensure_ascii=True))
print("=== GPS SOURCE ANCHORS (REDACTED CODE SHAPES) ===")
patterns=[
 ("routes/api.php",[r"staff/location",r"location/reporting"]),
 ("routes/web.php",[r"staff-location.settings",r"staff-location"]),
 ("app/Http/Controllers/Web/AdminNativeModuleController.php",[r"sample_minutes",r"function\s+\w*[Ll]ocation"]),
 ("resources/views/admin/native/staff-location.blade.php",[r"sample_minutes",r"[Rr]eporting",r"[Ss]ample"]),
 ("app/Http/Controllers/Api/V1/StaffLocationController.php",[r"function\s+\w+",r"location"]),
]
for rel,terms in patterns:
    path=root/rel
    print("FILE",rel,"PRESENT",path.is_file())
    if not path.is_file():continue
    lines=path.read_bytes().decode("utf-8","surrogateescape").splitlines()
    found=[(i+1,l) for i,l in enumerate(lines)
           if any(re.search(t,l,re.I) for t in terms)]
    for i,line in found[:22]:
        if any(re.search(k,line,re.I) for k in
               ["token","authorization","password","secret","api[_-]?key"]):
            line="[REDACTED]"
        print("LINE",i,line[:250].encode("ascii","backslashreplace").decode("ascii"))
print("=== STAFF LOCATION SETTINGS COLUMNS & CURRENT GLOBAL INTERVAL ===")
probe=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
if (!Schema::hasTable('staff_location_settings')) {
    echo json_encode(['table_exists'=>false]);exit;
}
$cols=Schema::getColumnListing('staff_location_settings');
$value=null;
if(in_array('sample_minutes',$cols,true)){
  $row=DB::table('staff_location_settings')->where('id',1)->first(['sample_minutes']);
  $value=$row?(int)$row->sample_minutes:null;
}
echo json_encode(['table_exists'=>true,'columns'=>$cols,'configured_sample_minutes'=>$value]);
"""
print(run(["php","-r",probe]).strip())
print("AM210_AUDIT_COMPLETE_READ_ONLY_NO_GPS_OR_ATTENDANCE_WRITES")
