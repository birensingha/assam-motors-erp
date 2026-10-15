<?php
declare(strict_types=1);
/** P37: read-only inspection authorization audit, no personal data displayed. */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file($root.'/artisan')){fwrite(STDERR,"P37_NO_GO_ROOT\n");exit(2);}
$paths=[
 'routes/web.php',
 'app/Http/Controllers/Web/ManagerInspectionWorkController.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'app/Http/Controllers/Web/StaffAssignedInspectionController.php',
 'app/Models/ErpAuthUser.php',
 'app/Http/Middleware/EnsureAdminUser.php',
 'app/Http/Middleware/EnsureAdminOrSupervisor.php',
 'app/Http/Middleware/EnsureErpAdmin.php',
 'app/Http/Middleware/EnsureStaffUser.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
 'resources/views/job-cards/show.blade.php',
 'resources/views/inspections/manager-work.blade.php',
];
$terms=['Admin/Supervisor permission required','isAdminOrSupervisor','isAdmin','isSupervisor',
 'inspection-manager','Review Manager Inspection','Assign Inspection Manager','Admin permission required.',
 'EnsureAdmin','EnsureStaff','supervisor','isStaff','role'];
foreach($paths as $p){
 if(!is_file($root.'/'.$p))continue;
 $content=(string)file_get_contents($root.'/'.$p);
 $lines=explode("\n",$content);
 $matched=[];
 foreach($lines as $i=>$line){
  foreach($terms as $term){
   if(stripos($line,$term)!==false){$matched[$i]=true;break;}
  }
 }
 echo "P37_FILE $p\n";
 foreach(array_keys($matched) as $i){
   $v=trim($lines[$i]);
   if(strlen($v)>320)$v=substr($v,0,320);
   echo ($i+1).": ".$v."\n";
 }
}
chdir($root);
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(\Illuminate\Support\Facades\Route::getRoutes() as $route) {
 $uri=$route->uri();
 if(!str_contains($uri,'inspection') && !str_contains($uri,'diagnos'))continue;
 if(str_contains($uri,'legacy'))continue;
 echo 'P37_ROUTE '.$route->getName().' '.$uri.' METHODS='.implode(',',$route->methods()).' MW='.implode(',',$route->gatherMiddleware())."\n";
}

foreach(['app/Models/ErpAuthUser.php','app/Http/Middleware/EnsureErpAdmin.php'] as $file) {
 if(!is_file($root.'/'.$file))continue;
 echo "P37_AUTH_SOURCE $file\n";
 $lines=explode("\n",(string)file_get_contents($root.'/'.$file));
 foreach($lines as $i=>$line) echo ($i+1).": ".trim($line)."\n";
}
$p=$root.'/app/Http/Controllers/Web/AdminJobCardPageController.php';
if(is_file($p)){
 echo "P37_REPORT_CONTROLLER_CONTEXT\n";
 $lines=explode("\n",(string)file_get_contents($p));
 foreach(range(1060,1120) as $i){if(isset($lines[$i]))echo ($i+1).": ".trim($lines[$i])."\n";}
}
try {
 $actor=\Illuminate\Support\Facades\DB::table('staff')->where('staff_code','AM5077')->first();
 if($actor){
   echo 'P37_TEST_STAFF_ACTIVE='.strtolower((string)($actor->status??'unknown'))."\n";
   $accounts=\App\Models\ErpAuthUser::where('staff_id',$actor->id)->get();
   echo 'P37_TEST_STAFF_LINKED_ACCOUNTS='.$accounts->count()."\n";
   foreach($accounts as $account){
     echo 'P37_TEST_LINKED_LOGIN_ROLE='.strtolower((string)$account->role).
      ' ACTIVE='.(int)$account->active.' IS_ADMIN='.(int)$account->isAdmin().' IS_STAFF='.(int)$account->isStaff()."\n";
   }
 }
 $roles=\App\Models\ErpAuthUser::query()->select('role')->distinct()->get();
 echo "P37_DISTINCT_LOGIN_ROLES=".implode(',',array_map(
 static fn($x)=>strtolower((string)$x->role),$roles->all()))."\n";
}catch(Throwable $e){echo "P37_ACCOUNT_CHECK_UNAVAILABLE=".get_class($e)."\n";}
echo "P37_NO_BUSINESS_DATA_WRITES\n";

