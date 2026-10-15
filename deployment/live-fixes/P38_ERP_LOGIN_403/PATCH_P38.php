<?php
declare(strict_types=1);
/**
 * P38_ERP_LOGIN_ROLE_SWITCH: Fix ERP 403 dead end after Staff/HR browser login.
 * Still requires real Admin/Supervisor credentials; never escalates user roles.
 * Changes only ERP GET login form + EnsureErpAdmin browser GET redirect.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan")){fwrite(STDERR,"P38_USAGE check|apply ROOT\n");exit(2);}
$files=[
 'app/Http/Controllers/Web/WebAuthController.php',
 'app/Http/Middleware/EnsureErpAdmin.php',
];
$before=[];
foreach($files as $p){
 if(!is_file("$root/$p")||is_link("$root/$p")){fwrite(STDERR,"P38_NO_GO_FILE $p\n");exit(3);}
 $before[$p]=(string)file_get_contents("$root/$p");
}
if(str_contains($before[$files[0]],'P38_ERP_LOGIN_ROLE_SWITCH')){
 if(!str_contains($before[$files[1]],'P38_ERP_STAFF_BROWSER_RECOVERY')){
  fwrite(STDERR,"P38_NO_GO_PARTIAL_PATCH\n");exit(4);
 }
 echo "P38_ALREADY_INSTALLED\n";exit(0);
}
function p38Replace(string $str,string $old,string $new,string $name):string{
 $count=substr_count($str,$old);
 if($count!==1)throw new RuntimeException("P38_NO_GO_ANCHOR_$name:$count");
 return str_replace($old,$new,$str);
}
try{
 $oldForm=<<<'PHP'
        if ($request->user() instanceof ErpAuthUser) {
            return redirect()->route('erp.job-cards.index');
        }
        return view('auth.login');
PHP;
 $newForm=<<<'PHP'
        // P38_ERP_LOGIN_ROLE_SWITCH: a linked Staff/HR browser session must not
        // redirect to an Admin-only Job Card page. Offer the real ERP login form.
        $current=$request->user();
        if ($current instanceof ErpAuthUser && $current->active && $current->isAdmin()) {
            return redirect()->route('erp.job-cards.index');
        }
        return view('auth.login');
PHP;
 $web=p38Replace($before[$files[0]],$oldForm,$newForm,'login_form');
 $oldGuard=<<<'PHP'
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin/Supervisor permission required.');
        return $next($request);
PHP;
 $newGuard=<<<'PHP'
        // P38_ERP_STAFF_BROWSER_RECOVERY: Admin-only permission stays enforced.
        // Browser GETs from a Staff/HR session go to explicit Admin login instead
        // of an unrecoverable 403. API/JSON and state-changing methods still 403.
        if ($request->isMethod('GET') && !$request->expectsJson() &&
            !($user instanceof ErpAuthUser && $user->active && $user->isAdmin())) {
            return redirect()->route('erp.login')->withErrors([
                'email'=>'This browser is signed in with a non-Admin account. Sign in with your ERP Admin/Supervisor ID.',
            ]);
        }
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin/Supervisor permission required.');
        return $next($request);
PHP;
 $guard=p38Replace($before[$files[1]],$oldGuard,$newGuard,'admin_guard');
 $updated=[$files[0]=>$web,$files[1]=>$guard];
 foreach($updated as $p=>$value)token_get_all($value,TOKEN_PARSE);
}catch(Throwable $e){fwrite(STDERR,"P38_NO_GO_BUILD ".$e->getMessage()."\n");exit(5);}
if($mode==='check'){echo "P38_SOURCE_PREFLIGHT_PASS\n";exit(0);}
$backDir="$root/storage/app/p38_auth_backup_".date('Ymd_His').'_'.getmypid();
if(!@mkdir($backDir,0700,true)){fwrite(STDERR,"P38_NO_GO_BACKUP_DIR\n");exit(6);}
foreach($files as $p){
 $dest="$backDir/".str_replace('/','__',$p);
 if(!copy("$root/$p",$dest)||hash_file('sha256',$dest)!==hash('sha256',$before[$p])){
  fwrite(STDERR,"P38_NO_GO_BACKUP_HASH\n");exit(6);
 }
}
foreach($files as $p){
 if(hash_file('sha256',"$root/$p")!==hash('sha256',$before[$p])){
  fwrite(STDERR,"P38_NO_GO_SOURCE_CHANGED $p\n");exit(7);
 }
}
$written=[];
foreach($files as $p){
 $dest="$root/$p";$tmp=tempnam(dirname($dest),'.p38_');
 if(!$tmp||file_put_contents($tmp,$updated[$p],LOCK_EX)!==strlen($updated[$p])||
   !chmod($tmp,fileperms($dest)&0777)||!rename($tmp,$dest)){
  if($tmp)@unlink($tmp);
  foreach($written as $done)copy("$backDir/".str_replace('/','__',$done),"$root/$done");
  fwrite(STDERR,"P38_NO_GO_WRITE_AND_ROLLBACK\n");exit(8);
 }
 $written[]=$p;
}
foreach($files as $p){
 if(hash_file('sha256',"$root/$p")!==hash('sha256',$updated[$p])){
  foreach($files as $done)copy("$backDir/".str_replace('/','__',$done),"$root/$done");
  fwrite(STDERR,"P38_NO_GO_VERIFY_ROLLBACK\n");exit(9);
 }
}
echo "P38_BACKUP_DIR=$backDir\n";
echo "P38_ERP_LOGIN_ROLE_SWITCH_APPLIED\n";
