<?php
declare(strict_types=1);
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;use Illuminate\Support\Facades\Schema;use Illuminate\Support\Facades\Route;
function v(string $k,mixed $d):void{echo 'P60_'.$k.'='.json_encode($d,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n";}
v('MODE','READ_ONLY_INSPECTION_CONTRACT_SOURCE_NO_PAYLOAD_OR_CLIENT_DATA');
foreach(['job_card_inspection','job_card_inspection_assignments','job_cards','staff','erp_auth_users'] as $t){
 if(Schema::hasTable($t))v('SCHEMA',[$t,Schema::getColumnListing($t),DB::table($t)->count()]);
}
foreach(Route::getRoutes() as $r){
 $st=$r->uri().' '.$r->getActionName();
 if(str_contains($st,'inspection'))v('ROUTE',[$r->uri(),$r->getName(),$r->methods(),$r->getActionName()]);
}
$files=[
 'app/Http/Controllers/Api/V1/StaffExtraController.php',
 'app/Http/Controllers/Web/StaffAssignedInspectionController.php',
 'resources/views/staff/assigned-inspection.blade.php',
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php'
];
foreach($files as $f){
 $path="$root/$f";if(!is_file($path)){v('MISSING',$f);continue;}
 $ls=file($path);
 if(str_contains($f,'StaffExtraController')){
  foreach($ls as $i=>$line)if(preg_match('/function inspection\(|function saveInspection\(/',$line)){
    $lo=max(0,$i-4);$hi=min(count($ls),$i+145);v('METHOD',[$f,$i+1,array_map(fn($j)=>($j+1).':'.rtrim($ls[$j]),range($lo,$hi-1))]);
  }
 }else{
  v('SOURCE',[$f,count($ls),array_map(fn($j)=>($j+1).':'.rtrim($ls[$j]),range(0,min(count($ls)-1,220)))]);
 }
}
$targets=DB::table('staff')->whereRaw("LOWER(name) LIKE '%mizanur%'")->get(['id','staff_code','role','status']);
v('MIZANUR_MATCH_COUNT_NO_CONTACT',$targets->count());
foreach($targets as $s){
 $tasks=DB::table('job_card_inspection_assignments as a')
  ->join('job_cards as j','j.id','=','a.job_card_id')
  ->where('a.assigned_staff_id',$s->id)
  ->select('a.job_card_id','a.completed_at','j.inspection_status','j.status')
  ->orderByDesc('a.job_card_id')->limit(5)->get();
 foreach($tasks as $job){
  $job->saved_inspection_rows=DB::table('job_card_inspection')->where('job_card_id',$job->job_card_id)->count();
 }
 v('MIZANUR_INSPECTION_STATE_NO_VEHICLE',$tasks);
}
echo "P60_INSPECTION_READONLY_AUDIT_DONE\n";