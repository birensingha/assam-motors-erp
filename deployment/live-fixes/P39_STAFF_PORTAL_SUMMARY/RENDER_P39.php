<?php
declare(strict_types=1);
/** Read-only render test. Never print account/customer personal data or exceptions' messages. */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan"))exit(2);
chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Contracts\Console\Kernel;
View::share('errors',new \Illuminate\Support\ViewErrorBag());
$admin=\App\Models\ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isAdmin());
$staff=\App\Models\ErpAuthUser::query()->where('active',1)->get()->first(fn($u)=>$u->isStaff());
$jc=DB::table('job_cards')->orderByDesc('id')->first();
if(!$jc||!$admin||!$staff){echo "P39_RENDER_NO_TEST_DATA\n";exit(3);}
function safeTest($name,callable $fn):void {
 try{
  $result=$fn();
  if($result instanceof \Illuminate\Contracts\View\View) {
   $html=$result->render();
   echo "P39_RENDER_".$name."_PASS bytes=".strlen($html)."\n";
  }elseif($result instanceof \Symfony\Component\HttpFoundation\Response){
   echo "P39_RENDER_".$name."_RESPONSE=".$result->getStatusCode()."\n";
  }else echo "P39_RENDER_".$name."_RESULT=".get_debug_type($result)."\n";
 }catch(Throwable $e){
  $file=str_replace(getcwd(),'',$e->getFile());
  echo "P39_RENDER_".$name."_FAIL class=".get_class($e)." file=".$file." line=".$e->getLine()."\n";
  foreach(array_slice($e->getTrace(),0,7) as $t){
   $f=str_replace(getcwd(),'',$t['file']??'');
   echo "TRACE ".$f.':'.($t['line']??0)." fn=".($t['function']??'')."\n";
  }
 }
}
Auth::guard('web')->setUser($admin);
$r=Request::create('/erp/job-cards/'.$jc->id,'GET');
$r->setUserResolver(static fn()=>$admin);
safeTest('ADMIN_JC_SHOW',static function()use($r,$jc){
 $c=app(\App\Http\Controllers\Web\AdminJobCardPageController::class);
 return app()->call([$c,'show'],['request'=>$r,'job'=>(int)$jc->id]);
});
Auth::guard('web')->setUser($staff);
$r=Request::create('/staff','GET');
$r->setUserResolver(static fn()=>$staff);
try{
 $m=new ReflectionMethod(\App\Http\Controllers\Web\StaffPortalController::class,'index');
 echo "P39_STAFF_METHOD_PARAMS=".implode(',',array_map(fn($p)=>$p->getName().':'.(string)$p->getType(),$m->getParameters()))."\n";
 safeTest('STAFF_PORTAL',static function()use($r){
   return app()->call([app(\App\Http\Controllers\Web\StaffPortalController::class),'index'],['request'=>$r]);
 });
}catch(Throwable $e){
 echo "P39_STAFF_METHOD_EXCEPTION=".get_class($e)."\n";
}
echo "P39_RENDER_TEST_READ_ONLY_FINISHED\n";
