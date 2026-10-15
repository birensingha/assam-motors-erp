<?php
declare(strict_types=1);
/** P39 read-only; print no PII and no exception payload (paths/lines only). */
$root=rtrim((string)($argv[1]??''),'/');if(!is_file("$root/artisan"))exit(2);
chdir($root);
require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Http\Request;
echo "P39_ROUTES\n";
foreach(Route::getRoutes() as $r){
 $u=$r->uri();$name=$r->getName()??'';
 if(preg_match('/(job.cards|staff|summary|cost|print|inspection-report)/i',"$u $name") &&
  (preg_match('/summary|print|cost|job.cards|staff\.portal/i',"$u $name"))){
  echo "ROUTE name=$name method=".implode(',',$r->methods())." uri=$u action=".$r->getActionName()." mw=".implode(',',$r->gatherMiddleware())."\n";
 }
}
$files=[
 'resources/views/staff/portal.blade.php',
 'resources/views/job-cards/show.blade.php',
 'resources/views/job-cards/summary.blade.php',
 'resources/views/job-cards/print.blade.php',
 'resources/views/job-cards/customer-cost-summary.blade.php',
 'app/Http/Controllers/Web/AdminJobCardPageController.php',
 'app/Http/Controllers/Web/StaffPortalController.php',
];
foreach($files as $p){
 if(!is_file("$root/$p")){echo "P39_MISSING $p\n";continue;}
 $text=(string)file_get_contents("$root/$p");$lines=explode("\n",$text);
 echo "P39_FILE $p len=".strlen($text)." sha=".hash('sha256',$text)."\n";
 if(str_contains($p,'staff/portal.blade.php')){
  foreach($lines as $k=>$line)if(preg_match('/<head|<\/head>|<body|<header|<nav|<main|<style|<\/style>|<script|staff-panel|rot-section|rot-card|ROT|<section|<table|class="staff-/i',$line)){
   if(count($lines)>1500 && $k>200)break;
   echo ($k+1).': '.substr(trim($line),0,320)."\n";
  }
  echo "P39_STAFF_HEAD_PREVIEW\n";
  foreach(array_slice($lines,0,120) as $i=>$l)echo ($i+1).': '.substr(trim($l),0,240)."\n";
 }else{
  foreach($lines as $i=>$line)if(preg_match('/function.*(summary|print|cost)|summary|route\(.+summary|@include.+summary/i',$line)){
   echo ($i+1).': '.substr(trim($line),0,210)."\n";
  }
 }
}
foreach(glob("$root/storage/logs/laravel*.log")?:[] as $log){
 echo "P39_LOGFILE ".basename($log)." size=".filesize($log)."\n";
 $fp=@fopen($log,'rb');if(!$fp)continue;
 fseek($fp,max(0,filesize($log)-500000));$tail=stream_get_contents($fp);fclose($fp);
 $lines=explode("\n",$tail);$hits=0;
 foreach($lines as $i=>$line){
  if(!preg_match('/summary|job.card|render|view\.php|exception|local\.ERROR|production\.ERROR/i',$line))continue;
  if(!preg_match('/\.(php|blade\.php):\d+|\.ERROR:/',$line))continue;
  $line=preg_replace('/^.*?(\d{4}-\d\d-\d\d \d\d:\d\d:\d\d).*?((?:[\/\w._-]+\.php):(\d+)).*$/','$1 FILE=$2',$line);
  $line=preg_replace('/(\w+)@([a-zA-Z0-9.-]+)/','[email]',$line);
  $line=preg_replace('/(password|token|secret|cookie|Authorization)["\x27]?\s*[:=]\s*[^\s,]+/i','$1=[redacted]',$line);
  if(strlen($line)>310)$line=substr($line,0,310);
  if(++$hits<=32)echo "P39_LOG $line\n";
 }
}
$users=\App\Models\ErpAuthUser::query()->where('active',1)->get();
$admin=$users->first(fn($x)=>$x->isAdmin());
$staff=$users->first(fn($x)=>$x->isStaff());
View::share('errors',new \Illuminate\Support\ViewErrorBag());
$jc=DB::table('job_cards')->orderByDesc('id')->first();
echo "P39_LAST_JOB_STATUS=".($jc->status??'NONE')." lastJCid=".($jc->id??0)."\n";
if($admin && $jc) {
 $r=Route::getRoutes();
 foreach($r as $route){
  $name=$route->getName()??'';
  if(!preg_match('/summary|cost.summary/i',$name))continue;
  $action=$route->getActionName();
  if(!str_contains($action,'@'))continue;
  [$class,$method]=explode('@',$action);
  if(!class_exists($class)||!method_exists($class,$method))continue;
  $ref=new ReflectionMethod($class,$method);
  echo "P39_SUMMARY_HANDLER $name class=$class method=$method params=".
    implode(',',array_map(fn($x)=>$x->getName().':'.(string)$x->getType(),$ref->getParameters()))."\n";
 }
}
echo "P39_READ_ONLY_AUDIT_DONE\n";
