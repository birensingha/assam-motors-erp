#!/usr/bin/env python3
"""AM248-2: Read-only exact QC-50 staff ownership guard / auth-model mapping.
No session/credential/token values, no SQL writes, no permissions changed.
Usage: python3 identity_probe.py STAGING_LARAVEL_ROOT
"""
from pathlib import Path
import re,subprocess,sys
if len(sys.argv)!=2:raise SystemExit("AM248_USAGE identity_probe.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM248_STOP_NOT_STAGING")
sensitive=re.compile(r"(?i)password|secret|api.?key|bearer|authorization|session_id|cookie|\.env|db_pass|private_key")
paths={
 "QC_CONTROLLER":"app/Http/Controllers/Web/StaffQcController.php",
 "ERP_AUTH_MODEL":"app/Models/ErpAuthUser.php",
 "STAFF_PORTAL_CONTROLLER":"app/Http/Controllers/Web/StaffPortalController.php",
 "ROUTES":"routes/web.php",
}
print("AM248_QC50_IDENTITY_PROBE_SOURCE_BEGIN")
for label,rel in paths.items():
    path=root/rel
    if not path.is_file():
        print("AM248_IDENTITY_FILE_MISSING",label);continue
    lines=path.read_text("utf8","replace").splitlines()
    if label=="QC_CONTROLLER":
        terms=re.compile(r"(?i)not assigned to your Staff ID|assigned_staff_id|staffId|staff_id|isStaff|function show|function submit|auth|role|abort\s*\(\s*403|request->user")
        before,after=7,10
        limit=220
    elif label=="ERP_AUTH_MODEL":
        terms=re.compile(r"(?i)getTable|protected \$table|staff_id|isStaff|isAdmin|guard|role|active")
        before,after=2,5
        limit=105
    elif label=="STAFF_PORTAL_CONTROLLER":
        terms=re.compile(r"(?i)function .*login|function .*portal|function .*dashboard|isStaff|staff_id|function.*authenticate|request->user")
        before,after=2,4
        limit=75
    else:
        terms=re.compile(r"(?i)StaffQcController|Route::get\('/qc/|Route::post\('/qc/|StaffPortalController|staff\.portal")
        before,after=3,3
        limit=85
    selected=set()
    for i,line in enumerate(lines):
        if terms.search(line):
            selected.update(range(max(0,i-before),min(len(lines),i+after+1)))
    print("AM248_IDENTITY_SOURCE",label,"LINES",len(lines),"SELECTED",min(len(selected),limit))
    for i in sorted(selected)[:limit]:
        line=lines[i].strip()
        if sensitive.search(line):line="[REDACTED_SOURCE]"
        print("AM248_IDENTITY_CODE",label,i+1,line[:480])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
echo "AM248_IDENTITY_DATABASE_READONLY_BEGIN\n";
$c=DB::table('workshop_qc_checks')->where('job_card_id',50)->orderByDesc('id')->first();
if($c) echo "AM248_QC=".json_encode(['id'=>$c->id,'result'=>$c->result,
 'assigned_staff_id'=>$c->assigned_staff_id??null,'created_by_admin_user_id'=>$c->checked_by_admin_user_id??null])."\n";
$u=new \App\Models\ErpAuthUser();
$t=$u->getTable();
echo "AM248_AUTH_MODEL_TABLE=".$t."\n";
if(Schema::hasTable($t)){
 $cols=Schema::getColumnListing($t);
 echo "AM248_AUTH_MODEL_IDENTITY_COLUMNS=".json_encode(array_values(array_intersect($cols,['id','role','staff_id','active','is_active','staff_code','user_type'])))."\n";
 if(in_array('staff_id',$cols,true) && in_array('id',$cols,true)){
  $fields=array_values(array_intersect(['id','role','staff_id','active','is_active','user_type'],$cols));
  $rows=DB::table($t)->where('staff_id',4)->orWhere('id',3)->orderBy('id')->limit(12)->get($fields);
  foreach($rows as $r)echo "AM248_AUTH_IDENTITY_ROW=".json_encode($r)."\n";
 }
}
$staff=DB::table('staff')->where('id',4)->first();
if($staff)echo "AM248_ASSIGNED_STAFF=".json_encode(['id'=>$staff->id,'staff_code'=>$staff->staff_code??null,'status'=>$staff->status??null])."\n";
foreach(Route::getRoutes() as $r){
 $name=(string)($r->getName()??'');
 if(in_array($name,['staff.qc.show','staff.qc.submit','erp.job-cards.lifecycle.qc-result','staff.portal','erp.login'],true))
  echo "AM248_AUTH_ROUTE=".json_encode(['name'=>$name,'uri'=>$r->uri(),
      'methods'=>$r->methods(),'middleware'=>$r->gatherMiddleware()])."\n";
}
echo "AM248_IDENTITY_DATABASE_READONLY_COMPLETE\n";"""
p=subprocess.run(["php","-r",php],cwd=root,text=True,capture_output=True,timeout=45)
if p.returncode:
    print("AM248_IDENTITY_PHP_ERROR",(p.stderr or p.stdout)[-650:])
    raise SystemExit(3)
print(p.stdout[:6000])
print("AM248_NO_SESSION_COOKIE_PASSWORD_OR_DATA_WRITES")
print("AM248_IDENTITY_DIAG_COMPLETE")
