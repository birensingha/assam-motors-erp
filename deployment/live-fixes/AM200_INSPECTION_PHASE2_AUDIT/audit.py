#!/usr/bin/env python3
"""AM200 STAGING Inspection Phase 2 compatibility audit (read-only).
No source, configuration, DB, or cache writes. Does not expose customer records.
"""
import json, os, re, subprocess, sys
from pathlib import Path

if len(sys.argv)!=2:
    raise SystemExit("Usage: python3 audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM200_STOP_STAGING_LARAVEL_ROOT_REQUIRED")

def call(command):
    result=subprocess.run(command,cwd=root,capture_output=True,text=True)
    if result.returncode:
        raise SystemExit("AM200_SUBCOMMAND_FAILED "+str(command[:2])+" "+result.stderr[-250:])
    return result.stdout

print("=== AM200 STAGING INSPECTION / HEALTH AUDIT ===")
print("PROJECT",root.name)
print("DOMPDF", "INSTALLED" if (root/"vendor/dompdf/dompdf").is_dir() else "MISSING")
print("AM189_SERVICE", (root/"app/Services/InspectionQcGuard.php").is_file())
print("AM189_DEFECT_REGISTER", (root/"app/Http/Controllers/Web/InspectionQcDefectController.php").is_file())
print("AM180_MASTER_SERVICE", (root/"app/Services/InspectionMasterService.php").is_file())
print("AM179_HEALTH_SERVICE", (root/"app/Services/VehicleHealthReportService.php").is_file())

print("\n=== RELEVANT ROUTES ===")
routes=json.loads(call(["php","artisan","route:list","--json"]))
for item in routes:
    uri=str(item.get("uri",""))
    if any(word in uri.lower() for word in ("inspection","qc-result","staff/qc","vehicle-health","configuration/inspection")):
        print("ROUTE",item.get("method"),uri,"ACTION",item.get("action","").split("\\")[-1])

# Bootstrap effective Laravel's own DB connection: ONLY schema and aggregate
# counts; no direct rows/identifiers/names/customer info or credentials.
probe=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$schema=\Illuminate\Support\Facades\Schema::class;
$db=\Illuminate\Support\Facades\DB::class;
$names=[
  'job_cards','job_card_inspection','job_card_inspection_assignments',
  'am189_inspection_defects','am189_inspection_defect_audits',
  'am_inspection_master_versions','am_inspection_job_snapshots',
  'am_inspection_defects','am_vehicle_health_valuations',
  'workshop_qc_checks'
];
$out=[];
foreach($names as $name){
  $out[$name]=[
    'exists'=>$schema::hasTable($name),
    'columns'=>$schema::hasTable($name)?$schema::getColumnListing($name):[]
  ];
}
$counts=[];
if($schema::hasTable('job_cards')&&$schema::hasColumn('job_cards','inspection_status')){
  foreach(['pending','completed'] as $status)
    $counts['job_inspection_'.$status]=$db::table('job_cards')->where('inspection_status',$status)->count();
}
if($schema::hasTable('job_card_inspection')){
  $counts['saved_inspection_rows']=$db::table('job_card_inspection')->count();
  if($schema::hasColumn('job_card_inspection','status')){
    foreach(['good','fair','poor'] as $condition)
      $counts['saved_'.$condition]=$db::table('job_card_inspection')->where('status',$condition)->count();
  }
}
echo json_encode(['schema'=>$out,'counts'=>$counts],JSON_THROW_ON_ERROR);
"""
result=call(["php","-r",probe])
try: data=json.loads(result)
except json.JSONDecodeError:
    raise SystemExit("AM200_STOP_UNEXPECTED_SCHEMA_OUTPUT")
print("\n=== TABLES / FIELD NAMES ONLY ===")
for name,info in data["schema"].items():
    cols=info["columns"]
    print("TABLE",name,"EXISTS",int(info["exists"]),"COLS",",".join(cols[:40]),"PLUS",max(0,len(cols)-40))
print("=== AGGREGATE COUNTS ONLY ===")
for name,count in data["counts"].items(): print("COUNT",name,count)

print("\n=== INSPECTION CONTROLLER DECISION POINTS ===")
checks=[
  ("app/Http/Controllers/Api/V1/StaffExtraController.php","saveInspection"),
  ("app/Http/Controllers/Api/V1/OwnerSelfAttendanceInspectionController.php","completeInspection"),
  ("app/Http/Controllers/Web/ManagerInspectionWorkController.php","complete"),
  ("app/Http/Controllers/Web/StaffAssignedInspectionController.php","complete"),
]
pattern=re.compile(r"validate|transaction|lockForUpdate|inspection_status|assignments|->insert|->delete|->update|\\breturn\\b|->save|VehicleInspectionChecklist|abort\\(",re.I)
for path,method in checks:
    file=root/path
    print("CONTROLLER",path,"METHOD",method)
    if not file.is_file():
        print(" FILE_MISSING")
        continue
    raw=file.read_bytes().decode("utf-8","surrogateescape").splitlines()
    found=[i for i,line in enumerate(raw) if re.search(r"\bfunction\s+"+re.escape(method)+r"\s*\(",line)]
    if len(found)!=1:
        print(" METHOD_MATCH_COUNT",len(found))
        continue
    start=found[0]
    nxt=next((i for i in range(start+1,len(raw)) if re.search(r"^\s*(?:(?:public|private|protected)\s+)?function\s+\w+\s*\(",raw[i])),len(raw))
    selected=set(range(start,min(start+8,nxt)))
    for i in range(start,nxt):
        if pattern.search(raw[i]):selected.update(range(max(start,i-1),min(nxt,i+2)))
    shown=0
    for i in sorted(selected):
        if shown>=46:
            print(" LINES_TRUNCATED")
            break
        line=raw[i]
        if any(word in line.lower() for word in (".env","password","secret","authorization:","bearer ")):
            line="[REDACTED_CONFIG_LINE]"
        print(f" {i+1}: {line[:220]}")
        shown+=1
    print(" METHOD_TOTAL_LINES",nxt-start)

print("\n=== INSPECTION VIEW / CHECKLIST PRESENCE ===")
for path in [
    "app/Support/VehicleInspectionChecklist.php",
    "resources/views/staff/assigned-inspection.blade.php",
    "resources/views/job-cards/show.blade.php",
    "resources/views/inspection-manager/work.blade.php",
    "resources/views/staff/portal.blade.php",
]:
    file=root/path
    if not file.is_file():
        print("VIEW",path,"MISSING")
        continue
    raw=file.read_bytes().decode("utf-8","surrogateescape")
    print("VIEW",path,"PRESENT","BYTES",file.stat().st_size,
          "HAS_DEFECT_BUTTON",int("AM190B_ADMIN_JOB_CARD_DEFECT_MANAGEMENT" in raw),
          "INSPECTION_TEXT",len(re.findall(r"inspection",raw,re.I)))
print("AM200_AUDIT_COMPLETE_NO_WRITES")
