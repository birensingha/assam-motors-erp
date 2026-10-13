<?php
declare(strict_types=1);
/** ASSAM MOTORS Staging: explicit HR sign-in, no admin/HR permission bypass. */
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: php APPLY_HR_DIRECT_LOGIN.php check|apply\n");exit(2);}
$root=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$payload=__DIR__.'/payload';
if(realpath($root)!==$root||!is_file($root.'/artisan')){fwrite(STDERR,"NO-GO: staging app path\n");exit(2);}
$controller='app/Http/Controllers/Web/HrDirectLoginController.php';
$view='resources/views/hr/direct-login.blade.php';
$new=[$controller=>$payload.'/'.$controller,$view=>$payload.'/'.$view];
foreach($new as $rel=>$path){if(!is_file($path)||is_file($root.'/'.$rel)){fwrite(STDERR,"NO-GO: source missing or destination exists: $rel\n");exit(2);}}
$guards=['app/Http/Middleware/EnsureHrManager.php','app/Http/Middleware/EnsureStaffUser.php','routes/web.php'];
$src=[];foreach($guards as $p){if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"NO-GO: unsafe guard path $p\n");exit(2);} $src[$p]=(string)file_get_contents($root.'/'.$p);}
function one(string $s,string $needle,string $insert,string $scope):string {
 if(substr_count($s,$needle)!==1){fwrite(STDERR,"NO-GO: unexpected $scope source\n");exit(2);}
 return str_replace($needle,$insert,$s);
}
$anchor="        abort_unless(HrAccess::permitted(\$request->user()), 403, 'HR Manager permission required.');";
$replacement=<<<'PHPBLOCK'
        // HR_DIRECT_LOGIN_P8: HR role restrictions stay server-side; make browser session switching explicit.
        if (!HrAccess::permitted($request->user())) {
            if ($request->isMethod('GET') && !$request->expectsJson() &&
                ($request->is('erp/hr') || $request->is('erp/hr/*'))) {
                return redirect()->route('erp.hr.direct-login');
            }
            abort(403, 'HR Manager permission required.');
        }
PHPBLOCK;
$next=[];
$next[$guards[0]]=one($src[$guards[0]],$anchor,$replacement,'HR middleware');
$staffAnchor="        abort_unless(\$user instanceof ErpAuthUser && \$user->active && \$user->isStaff(),403,'Staff permission required.');";
$staffReplace=<<<'PHPBLOCK'
        // HR_DIRECT_LOGIN_P8: an existing Admin browser session can choose a separate HR login.
        if (!($user instanceof ErpAuthUser && $user->active && $user->isStaff()) &&
            $request->isMethod('GET') && !$request->expectsJson() &&
            ($request->is('erp/hr') || $request->is('erp/hr/*'))) {
            return redirect()->route('erp.hr.direct-login');
        }
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff(),403,'Staff permission required.');
PHPBLOCK;
$next[$guards[1]]=one($src[$guards[1]],$staffAnchor,$staffReplace,'Staff middleware');
$routeAnchor="Route::get('/erp/login', [WebAuthController::class,'loginForm'])->name('erp.login');";
$routeInsert=<<<'PHPBLOCK'
// HR_DIRECT_LOGIN_P8 — direct credential-checked HR session switch, independent of Admin Login.
Route::get('/erp/hr/sign-in', [\App\Http\Controllers\Web\HrDirectLoginController::class, 'show'])
    ->name('erp.hr.direct-login');
Route::post('/erp/hr/sign-in', [\App\Http\Controllers\Web\HrDirectLoginController::class, 'login'])
    ->middleware('throttle:5,1')->name('erp.hr.direct-login.submit');
PHPBLOCK;
$next[$guards[2]]=one($src[$guards[2]],$routeAnchor,$routeInsert."\n".$routeAnchor,'web routes');
foreach($next as $rel=>$content){try{token_get_all($content,TOKEN_PARSE);}catch(Throwable $e){fwrite(STDERR,"NO-GO: invalid PHP $rel\n");exit(2);}}
try{token_get_all((string)file_get_contents($new[$controller]),TOKEN_PARSE);}catch(Throwable $e){fwrite(STDERR,"NO-GO: controller PHP\n");exit(2);}
echo "HR_DIRECT_LOGIN_PREFLIGHT_PASS: 3 guarded sources, 1 new controller, 1 new view; no security roles or data changes.\n";
if($mode==='check'){echo "DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/hr_direct_login_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO backup dir\n");exit(2);}
foreach($src as $rel=>$content){$b=$backup.'/'.str_replace('/','_',$rel);if(!copy($root.'/'.$rel,$b)||hash_file('sha256',$b)!==hash('sha256',$content)){fwrite(STDERR,"NO-GO backup failed\n");exit(2);}}
$installed=[];$added=[];
try{
 foreach($new as $rel=>$payloadPath){$target=$root.'/'.$rel;if(!is_dir(dirname($target))||!($fd=@fopen($target,'x')))throw new RuntimeException('Cannot create '.$rel);$text=(string)file_get_contents($payloadPath);$n=fwrite($fd,$text);fclose($fd);$added[]=$target;if($n!==strlen($text))throw new RuntimeException('Partial create '.$rel);}
 foreach($next as $rel=>$content){$target=$root.'/'.$rel;if(hash_file('sha256',$target)!==hash('sha256',$src[$rel]))throw new RuntimeException('Source changed '.$rel);$tmp=tempnam(dirname($target),'.hrl_');if(!$tmp||file_put_contents($tmp,$content,LOCK_EX)!==strlen($content))throw new RuntimeException('Failed stage '.$rel);chmod($tmp,fileperms($target)&0777);if(!rename($tmp,$target))throw new RuntimeException('Failed rename '.$rel);$installed[]=$rel;}
} catch(Throwable $e){foreach($installed as $rel)copy($backup.'/'.str_replace('/','_',$rel),$root.'/'.$rel);foreach($added as $p)@unlink($p);fwrite(STDERR,"NO-GO: rolled back all source changes: ".$e->getMessage()."\n");exit(2);}
echo 'HR_DIRECT_LOGIN_APPLIED: PASS BACKUP='.$backup."\n";