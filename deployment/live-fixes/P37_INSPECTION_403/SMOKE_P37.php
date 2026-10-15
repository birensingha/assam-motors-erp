<?php
declare(strict_types=1);
/** P37 signed-in context simulation, read-only. Do not mutate business records. */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file($root.'/artisan'))exit(2);
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
$person=DB::table('staff')->where('staff_code','AM5077')->first();
if(!$person || strtolower((string)$person->status)!=='active') {echo "P37_NO_GO_ACTIVE_STAFF\n";exit(3);}
$user=\App\Models\ErpAuthUser::query()->where('staff_id',$person->id)->where('active',true)->first();
if(!$user || !$user->isStaff()){echo "P37_NO_GO_STAFF_LINK\n";exit(4);}
$job=DB::table('job_cards')->where('job_no','JC-20261009-00030')->first();
if(!$job || (int)$job->technician_id!==(int)$person->id){echo "P37_NO_GO_DECIDING_TECHNICIAN\n";exit(5);}
$assignment=DB::table('job_card_inspection_assignments')->where('job_card_id',$job->id)->first();
if(!$assignment || (int)$assignment->assigned_staff_id!==(int)$person->id){echo "P37_NO_GO_MANAGER_ASSIGNMENT\n";exit(6);}
Auth::guard('web')->setUser($user);
View::share('errors',new \Illuminate\Support\ViewErrorBag());
$r=Request::create('/staff/inspections/'.$job->id.'/report','GET');
$r->setUserResolver(static fn()=>$user);
if(in_array(strtolower((string)$job->inspection_status),['completed','skipped'],true)){
 try{
   $result=app(\App\Http\Controllers\Web\StaffPortalController::class)->inspectionReport($r,(int)$job->id);
   $html=$result->render();
   if(!str_contains($html,'Inspection')) throw new \RuntimeException('Expected report heading absent');
   echo "P37_DECIDING_TECHNICIAN_REPORT_RENDER_PASS\n";
 }catch(Throwable $e){echo "P37_REPORT_FAIL=".get_class($e)." ".$e->getMessage()."\n";exit(7);}
}else{
 echo "P37_REPORT_INTENTIONALLY_LOCKED_UNTIL_INSPECTION_COMPLETED\n";
}
$r=Request::create('/staff/inspections/'.$job->id.'/work','GET');
$r->setUserResolver(static fn()=>$user);
try{
  $result=app(\App\Http\Controllers\Web\StaffAssignedInspectionController::class)->show(
    $r,(int)$job->id,app(\App\Http\Controllers\Api\V1\StaffExtraController::class)
  );
  $html=$result->render();
  if(!str_contains($html,'Inspection'))throw new \RuntimeException('Expected work heading absent');
  echo "P37_ASSIGNED_MANAGER_INSPECTION_RENDER_PASS\n";
}catch(Throwable $e){echo "P37_WORK_FAIL=".get_class($e)." ".$e->getMessage()."\n";exit(8);}
echo "P37_NO_DATA_WRITES\n";
