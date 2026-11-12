#!/usr/bin/env python3
"""AM248 read-only JC50 QC routing/permission diagnostic on STAGING.
No mutation of Job Card, QC, staff, security or database.
Usage: python3 audit.py ~/domains/assammotors.com/assam-erp-staging
"""
import re,sys,subprocess
from pathlib import Path
if len(sys.argv)!=2:raise SystemExit("USAGE_AM248 audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("STOP_AM248_BAD_STAGING_ROOT")
sources=[
 "resources/views/job-cards/show.blade.php",
 "resources/views/job-cards/lifecycle-panel.blade.php",
 "app/Http/Controllers/Web/StaffQcController.php",
 "app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php",
 "app/Http/Controllers/Web/StaffPortalController.php",
 "routes/web.php",
]
patterns={
 sources[0]:re.compile(r"(?i)QC Assigned|Staff QC Review|Quality Check|staff[._-]qc|assigned_staff_id|qc.review|qc.show"),
 sources[1]:re.compile(r"(?i)QC Assigned|Staff QC Review|staff[._-]qc|qc.result|assigned_staff_id"),
 sources[2]:re.compile(r"(?i)not assigned to your Staff ID|assigned_staff_id|qc|staff_id|function show|function submit|abort\("),
 sources[3]:re.compile(r"(?i)qcResult|qcPending|assigned_staff_id|function admin|QC PASS"),
 sources[4]:re.compile(r"(?i)function|QC|staff_id|isStaff|auth\("),
 sources[5]:re.compile(r"(?i)staff[._-]qc|StaffQcController|qc.review|qc-result|qc.pending")
}
secrets=re.compile(r"(?i)(password|secret|bearer|authorization|api.?token|private.?key|DB_(?:PASS|USER|HOST)|APP_KEY|session_secret|cookie|\\.env)")
print("AM248_JC50_QC403_SOURCE_AUDIT_BEGIN")
for rel in sources:
    p=root/rel
    if not p.is_file():
        print("AM248_MISSING",rel);continue
    lines=p.read_text("utf8","replace").splitlines()
    hits=[i for i,s in enumerate(lines) if patterns[rel].search(s)]
    print("AM248_FILE",rel,"LINES",len(lines),"MATCHES",len(hits))
    displayed=set()
    for i in hits[:22]:
        for j in range(max(0,i-2),min(len(lines),i+3)):
            if j in displayed:continue
            displayed.add(j)
            s=lines[j].strip()
            if secrets.search(s):s="[REDACTED_SENSITIVE_SOURCE]"
            print("AM248_CODE",rel,j+1,s[:620])
php=r"""require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
echo "AM248_JC50_READ_ONLY_BEGIN\n";
if(Schema::hasTable('job_cards')){
 $j=DB::table('job_cards')->where('id',50)->first();
 if($j)echo 'JC50='.json_encode(['id'=>$j->id,'status'=>$j->status??null,'public_status'=>$j->customer_public_status??null])."\n";
 else echo "JC50_NOT_FOUND\n";
}
if(Schema::hasTable('workshop_qc_checks')){
 $q=DB::table('workshop_qc_checks')->where('job_card_id',50)->orderByDesc('id')->first();
 if($q){
  echo 'QC50='.json_encode(['id'=>$q->id,'result'=>$q->result??null,'assigned_staff_id'=>$q->assigned_staff_id??null,'checked_by_admin_user_id'=>$q->checked_by_admin_user_id??null])."\n";
  if(!empty($q->assigned_staff_id)&&Schema::hasTable('staff')){
   $s=DB::table('staff')->where('id',$q->assigned_staff_id)->first();
   if($s)echo 'QC_ASSIGNEE='.json_encode(['id'=>$s->id,'name'=>$s->name??null,'staff_code'=>$s->staff_code??null,'status'=>$s->status??null])."\n";
  }
 } else echo "QC50_NOT_FOUND\n";
}
foreach(Route::getRoutes() as $route){
 $uri=(string)$route->uri();$name=(string)($route->getName()??'');
 $action=(string)($route->getActionName()??'');
 if(str_contains(strtolower($uri),'qc') || str_contains(strtolower($name),'qc') ||
    str_contains($action,'StaffQcController')){
  if(preg_match('/(staff|job-cards)/i',$uri) || str_contains($action,'StaffQcController'))
   echo 'QC_ROUTE='.json_encode(['methods'=>$route->methods(),'uri'=>$uri,'name'=>$name,'action'=>$action])."\n";
 }
}
echo "AM248_JC50_READ_ONLY_END\n";"""
p=subprocess.run(["php","-r",php],cwd=root,capture_output=True,text=True,timeout=40)
if p.returncode:
    print("AM248_PHP_PROBE_FAILED",p.stderr[-750:])
    raise SystemExit(3)
print(p.stdout[:6500])
print("AM248_NO_DATABASE_OR_SOURCE_CHANGES")
print("AM248_DIAGNOSTIC_COMPLETE_READ_ONLY")
