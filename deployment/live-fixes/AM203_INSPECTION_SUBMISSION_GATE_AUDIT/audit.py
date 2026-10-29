#!/usr/bin/env python3
"""AM203 Staging-only read-only 80-row reconciliation / submit-gate audit.
Prints counts and JC internal IDs only, no customer/staff private details.
"""
from pathlib import Path
import subprocess,sys,json
if len(sys.argv)!=2:raise SystemExit("USAGE audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM203_STOP_NOT_STAGING")
php=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$cols=['id','status','inspection_status'];
if(Schema::hasColumn('job_cards','diagnosis_status'))$cols[]='diagnosis_status';
$jobs=DB::table('job_cards')->get($cols)->keyBy('id');
$assigned=DB::table('job_card_inspection_assignments')
    ->get(['job_card_id','assigned_staff_id','completed_at'])
    ->groupBy('job_card_id');
$saved=DB::table('job_card_inspection')
    ->selectRaw('job_card_id, count(*) as points')
    ->groupBy('job_card_id')->get()->keyBy('job_card_id');
$counts=[
 'total_saved_rows'=>0,'completed_job_rows'=>0,
 'not_completed_job_rows'=>0,'orphan_rows'=>0,
 'pending_jobs'=>0,'pending_jobs_with_saved_rows'=>0,
 'pending_jobs_without_assignment'=>0,
 'pending_jobs_assigned_completed'=>0,
 'pending_jobs_not_open'=>0,
 'pending_jobs_diagnosis_already_completed'=>0,
];
$byInspectionStatus=[];$samples=[];
foreach($saved as $id=>$record){
 $points=(int)$record->points;
 $counts['total_saved_rows']+=$points;
 $job=$jobs->get($id);
 if(!$job){$counts['orphan_rows']+=$points;$samples[]=['jc_id'=>(int)$id,'points'=>$points,'inspection'=>'ORPHAN'];continue;}
 $s=strtolower(trim((string)($job->inspection_status??'')));
 $byInspectionStatus[$s?:'empty']=($byInspectionStatus[$s?:'empty']??0)+$points;
 if($s==='completed')$counts['completed_job_rows']+=$points;
 else{
   $counts['not_completed_job_rows']+=$points;
   $assignee=$assigned->get($id)?->first();
   $samples[]=[
     'jc_id'=>(int)$id,'points'=>$points,
     'inspection_status'=>$s?:'empty',
     'job_status'=>strtolower(trim((string)($job->status??''))),
     'diagnosis_status'=>strtolower(trim((string)($job->diagnosis_status??''))),
     'assignment_exists'=>boolval($assignee),
     'assignment_completed'=>boolval($assignee?->completed_at),
   ];
 }
}
foreach($jobs as $id=>$job){
 if(strtolower(trim((string)($job->inspection_status??'')))!=='pending')continue;
 $counts['pending_jobs']++;
 $assignee=$assigned->get($id)?->first();
 if(($saved->get($id)?->points??0)>0)$counts['pending_jobs_with_saved_rows']++;
 if(!$assignee)$counts['pending_jobs_without_assignment']++;
 if($assignee?->completed_at)$counts['pending_jobs_assigned_completed']++;
 if(strtolower(trim((string)($job->status??'')))!=='open')$counts['pending_jobs_not_open']++;
 if(strtolower(trim((string)($job->diagnosis_status??'')))==='completed')
   $counts['pending_jobs_diagnosis_already_completed']++;
}
ksort($byInspectionStatus);
echo json_encode(['counts'=>$counts,'rows_by_inspection_status'=>$byInspectionStatus,
    'sample_noncomplete'=>array_slice($samples,0,25),
    'noncomplete_job_count'=>count($samples)],JSON_THROW_ON_ERROR);
"""
r=subprocess.run(["php","-r",php],cwd=root,capture_output=True,text=True)
if r.returncode:
    raise SystemExit("AM203_STOP_READ_FAILED_EXIT_"+str(r.returncode))
try:data=json.loads(r.stdout)
except ValueError:raise SystemExit("AM203_STOP_UNEXPECTED_READ_RESULT")
print("=== AM203 INSPECTION SUBMIT GATE / HISTORICAL ROWS READ ONLY ===")
for k,v in data["counts"].items():print("COUNT",k,v)
for k,v in data["rows_by_inspection_status"].items():print("SAVED_INSPECTION_STATUS",k,"ROWS",v)
for row in data["sample_noncomplete"]:print("NONCOMPLETED_SAVED_JOB",row)
print("TOTAL_NONCOMPLETE_OR_ORPHAN_JOB_GROUPS",data["noncomplete_job_count"])
print("AM203_AUDIT_COMPLETE_NO_WRITES")
