<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file($root.'/artisan')){echo "P52_NO_GO_ROOT\n";exit(2);}
chdir($root);
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
function out($key,$data){echo 'P52_'.$key.'='.json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n";}
$jno='JC-20261016-00036';
$cols=Schema::getColumnListing('job_cards');
$fields=array_values(array_intersect(['id','job_no','status','created_at','inspection_status','diagnosis_status','decision_status','inspection_submitted_at','technician','technician_id','assigned_mechanic_id','assigned_technician_id','deciding_technician_id','deciding_technician_assigned_at','deciding_technician_assigned_by','technician_assigned_at','technician_assigned_by'], $cols));
$jc=DB::table('job_cards')->where('job_no',$jno)->first($fields);
if(!$jc){echo "P52_JC_NOT_FOUND\n";exit(0);}
$masked=(array)$jc;
out('JC_STATE',$masked);
if(Schema::hasTable('job_card_inspection_assignments')){
  $x=DB::table('job_card_inspection_assignments')->where('job_card_id',$jc->id)->first(['assigned_staff_id','assigned_at','assigned_by_user_id','completed_at']);
  out('INSPECTION_ASSIGNMENT',$x);
}
if(Schema::hasTable('job_card_diagnosis_events')){
 $eventCols=Schema::getColumnListing('job_card_diagnosis_events');
 $take=array_intersect(['event_type','technician_id','technician_name','actor_id','created_at','reason'], $eventCols);
 $events=DB::table('job_card_diagnosis_events')->where('job_card_id',$jc->id)->orderBy('id')->get(array_values($take));
 $safe=[];foreach($events as $ev){$v=(array)$ev;unset($v['reason']);$safe[]=$v;}
 out('ASSIGNMENT_EVENT_HISTORY',$safe);
}
if(Schema::hasTable('staff')&&(int)($jc->technician_id??0)>0){
 $staff=DB::table('staff')->where('id',$jc->technician_id)->first(['id','staff_code','name','role','status']);
 out('TECHNICIAN_STAFF',$staff);
}
foreach(['app/Http/Controllers/Web/AdminJobCardPageController.php','app/Services/JobCardService.php','resources/views/job-cards/create.blade.php','resources/views/job-cards/show.blade.php'] as $p){
 $file=$root.'/'.$p;if(!is_file($file))continue;
 $s=file($file);$m=[];
 foreach($s as $i=>$v)if(preg_match('~technician_id|deciding_technician|technician_assigned|assignDecidingTechnician|function store\\(|function assignTechnician\\(~i',$v)){
   $m[]=['line'=>$i+1,'snippet'=>substr(trim(preg_replace('/\\{\\{.*?\\}\\}/','[blade value]',$v)),0,230)];
   if(count($m)>=60)break;
 }
 out('SOURCE_REFERENCES',['file'=>$p,'match'=>$m]);
}
foreach([
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',170,245],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',854,925],
 ['app/Http/Controllers/Web/AdminJobCardPageController.php',948,1010],
 ['resources/views/job-cards/show.blade.php',243,300],
 ['app/Http/Controllers/Web/ManagerInspectionWorkController.php',35,59],
 ['app/Http/Controllers/Web/AdminDiagnosisStageController.php',20,65],
 ['app/Http/Controllers/Web/StaffTechnicianDecisionController.php',22,63],
] as [$name,$from,$to]){
 $file=$root.'/'.$name;if(!is_file($file))continue;
 $lines=file($file);$part=[];
 for($i=$from-1;$i<min(count($lines),$to);$i++)
   $part[]=($i+1).':'.substr(trim($lines[$i]),0,215);
 out('CODE_CONTEXT',['path'=>$name,'range'=>"$from-$to",'lines'=>$part]);
}
echo "P52_READONLY_NO_MUTATIONS=PASS\n";
