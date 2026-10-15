<?php
declare(strict_types=1);
/** P38: ZERO WRITE staging auth audit, no emails/passwords/tokens/session contents. */
$root=rtrim((string)($argv[1]??''),'/');
if(!is_file("$root/artisan")){fwrite(STDERR,"P38_NO_GO_ROOT\n");exit(2);}
$paths=[
 'app/Http/Controllers/Web/WebAuthController.php',
 'app/Http/Middleware/EnsureErpAdmin.php',
 'app/Http/Middleware/EnsureStaffUser.php',
 'app/Models/ErpAuthUser.php',
 'config/auth.php',
 'config/session.php',
 'routes/web.php',
];
foreach($paths as $p){
 if(!is_file("$root/$p")){echo "P38_NOT_FOUND $p\n";continue;}
 $lines=explode("\n",(string)file_get_contents("$root/$p"));
 $chunks=[];
 if(str_contains($p,'WebAuthController.php')){
  foreach($lines as $i=>$l)if(preg_match('/function\s+(login|logout|loginForm|staffLogin|staffLogout|staffLoginForm)\s*\(/i',$l)){
   for($k=max(0,$i-4);$k<min(count($lines),$i+65);$k++)$chunks[$k]=true;
  }
 }elseif(str_contains($p,'routes/web.php')){
  for($k=23;$k<min(count($lines),50);$k++)$chunks[$k]=true;
  foreach($lines as $i=>$l)if(preg_match('/staff.login|staff.logout|erp.login|erp.logout|Route::middleware|EnsureErpAdmin|EnsureStaffUser/i',$l))$chunks[$i]=true;
 }elseif(str_contains($p,'session.php')){
  foreach($lines as $i=>$l)if(preg_match('/(driver|lifetime|expire_on_close|encrypt|cookie|path|domain|secure|same_site)/i',$l)){
   $chunks[$i]=true;
  }
 }else{
   foreach($lines as $i=>$l)$chunks[$i]=true;
 }
 echo "P38_FILE $p\n";
 foreach(array_keys($chunks) as $i){
  $line=trim((string)$lines[$i]);
  if(preg_match('/(password|secret|token|key|SESSION_COOKIE|SESSION_DOMAIN)/i',$line) &&
     !preg_match('/(function|validate|Hash::check|Auth::|env\(|config\(|\$request->|->attempt\(|->password)/i',$line))continue;
  if(strlen($line)>270)$line=substr($line,0,270);
  echo ($i+1).": ".$line."\n";
 }
}
chdir($root);require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'P38_EFFECTIVE_SESSION_DRIVER='.config('session.driver')."\n";
echo 'P38_EFFECTIVE_SESSION_PATH='.config('session.path')."\n";
echo 'P38_EFFECTIVE_SESSION_DOMAIN='.((string)config('session.domain')?:'host-only')."\n";
echo 'P38_EFFECTIVE_SESSION_COOKIE_NAME='.config('session.cookie')."\n";
echo 'P38_EFFECTIVE_SESSION_SECURE='.(config('session.secure')===true?'true':'false/auto')."\n";
echo 'P38_EFFECTIVE_SESSION_SAME_SITE='.config('session.same_site')."\n";
try{
 $rows=\App\Models\ErpAuthUser::query()->get(['role','active','staff_id']);
 $result=[];
 foreach($rows as $row){
  $role=strtolower(trim((string)$row->role));
  $key=$role.'_'.($row->active?'active':'inactive');
  $result[$key]=($result[$key]??0)+1;
 }
 foreach($result as $type=>$count)echo "P38_AUTH_ROLE_COUNT $type $count\n";
}catch(Throwable $e){echo "P38_USERS_CHECK_FAILED=".get_class($e)."\n";}
foreach(['erp.login','erp.login.submit','erp.dashboard','erp.job-cards.show','erp.logout','staff.login','staff.login.submit','staff.logout'] as $n){
 $r=\Illuminate\Support\Facades\Route::getRoutes()->getByName($n);
 echo 'P38_ROUTE '.$n.' URI='.($r?$r->uri():'absent').' METHODS='.($r?implode(',',$r->methods()):'').
    ' MW='.($r?implode(',',$r->gatherMiddleware()):'')."\n";
}
echo "P38_AUDIT_READ_ONLY_PASS\n";