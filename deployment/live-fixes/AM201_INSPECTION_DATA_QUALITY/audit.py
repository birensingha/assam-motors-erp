#!/usr/bin/env python3
"""AM201 read-only STAGING inspection data quality audit: counts and JC IDs only."""
import subprocess,sys,json
from pathlib import Path
if len(sys.argv)!=2: raise SystemExit("USAGE audit.py STAGING_ROOT")
root=Path(sys.argv[1]).expanduser().resolve()
if root.name!="assam-erp-staging" or not (root/"artisan").is_file():
    raise SystemExit("AM201_STOP_NOT_STAGING")
php=r"""
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$canon=\App\Support\VehicleInspectionChecklist::items();
$cards=DB::table('job_cards')->whereRaw("LOWER(inspection_status)='completed'")->pluck('id');
$group=[];
foreach(DB::table('job_card_inspection')->get(['job_card_id','item_name','status','remarks']) as $row){
  $group[(int)$row->job_card_id][]=$row;
}
$distribution=[];$samples=[];$counts=[
  'completed_inspections'=>$cards->count(),'completed_with_0_rows'=>0,
  'completed_with_less_than_30_rows'=>0,'completed_with_exactly_30_rows'=>0,
  'completed_with_more_than_30_rows'=>0,'completed_canonical_30'=>0,
  'completed_noncanonical'=>0,'completed_invalid_conditions'=>0,
  'completed_duplicate_labels'=>0,'completed_fair_poor_missing_notes'=>0,
  'completed_with_fair_or_poor'=>0,'completed_inspection_rows'=>0,
  'am189_defects'=>0,'am189_verified'=>0
];
foreach($cards as $jc){
  $rows=$group[(int)$jc]??[];$n=count($rows);
  $counts['completed_inspection_rows']+=$n;
  $distribution[(string)$n]=($distribution[(string)$n]??0)+1;
  if(!$n)$counts['completed_with_0_rows']++;
  if($n<30)$counts['completed_with_less_than_30_rows']++;
  elseif($n===30)$counts['completed_with_exactly_30_rows']++;
  else $counts['completed_with_more_than_30_rows']++;
  $names=[];$invalid=0;$missing=0;$bad=0;
  foreach($rows as $row){
    $names[]=trim((string)$row->item_name);
    $s=strtolower(trim((string)$row->status));
    if(!in_array($s,['good','fair','poor'],true))$invalid++;
    if(in_array($s,['fair','poor'],true)){
      $bad++;
      if(trim((string)$row->remarks)==='')$missing++;
    }
  }
  $duplicate=$n-count(array_unique($names));
  $canonical=$n===30 && !$duplicate && !array_diff($names,$canon) && !array_diff($canon,$names);
  if($canonical)$counts['completed_canonical_30']++;
  else $counts['completed_noncanonical']++;
  if($invalid)$counts['completed_invalid_conditions']++;
  if($duplicate)$counts['completed_duplicate_labels']++;
  if($missing)$counts['completed_fair_poor_missing_notes']++;
  if($bad)$counts['completed_with_fair_or_poor']++;
  if(!$canonical||$invalid||$missing||$duplicate){
    $samples[]=['jc_id'=>(int)$jc,'saved'=>$n,'canonical'=>$canonical,
      'duplicate'=>$duplicate,'invalid'=>$invalid,'missing_notes'=>$missing];
  }
}
if(Schema::hasTable('am189_inspection_defects')){
 $counts['am189_defects']=DB::table('am189_inspection_defects')->count();
 $counts['am189_verified']=DB::table('am189_inspection_defects')->where('status','VERIFIED')->count();
}
ksort($distribution,SORT_NUMERIC);
usort($samples,fn($a,$b)=>$a['saved']<=>$b['saved']);
echo json_encode(['counts'=>$counts,'distribution'=>$distribution,
 'sample'=>array_slice($samples,0,15),'issue_job_count'=>count($samples)],JSON_THROW_ON_ERROR);
"""
r=subprocess.run(["php","-r",php],cwd=root,text=True,capture_output=True)
if r.returncode: raise SystemExit("AM201_STOP_LARAVEL_READ_ERROR code="+str(r.returncode))
try: data=json.loads(r.stdout)
except ValueError: raise SystemExit("AM201_STOP_INVALID_AUDIT_OUTPUT")
print("=== AM201 READ ONLY INSPECTION QUALITY ===")
for k,v in data["counts"].items(): print("COUNT",k,v)
for k,v in data["distribution"].items():print("COMPLETED_POINTS",k,"JOBS",v)
for x in data["sample"]:print("ISSUE",x)
print("TOTAL_JOBS_WITH_DATA_ISSUES",data["issue_job_count"])
print("AM201_AUDIT_COMPLETE_NO_WRITES")
