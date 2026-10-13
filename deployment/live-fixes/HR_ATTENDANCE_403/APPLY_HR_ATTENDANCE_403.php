<?php
declare(strict_types=1);
/** ASSAM MOTORS HR Attendance 403: exact GET-only HR redirect, staging only. */
$mode=$argv[1]??'check';
if(!in_array($mode,['check','apply'],true)){fwrite(STDERR,"Usage: php APPLY_HR_ATTENDANCE_403.php check|apply\n");exit(2);}
$base=rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
$target=$base.'/app/Http/Middleware/EnsureErpAdmin.php';
$routes=$base.'/routes/web.php';
if(realpath($base)!==$base||!is_file($base.'/artisan')||!is_file($target)||is_link($target)||!is_file($routes)){fwrite(STDERR,"NO-GO: canonical staging auth middleware missing\n");exit(2);}
$original=(string)file_get_contents($target);
if(str_contains($original,'HR_ATTENDANCE_LINK_403_P7')){
 echo "ALREADY_PRESENT: scoped HR navigation fix installed. No source changes.\n";exit(0);
}
if(!str_contains((string)file_get_contents($routes),"name('operations')")||
   !str_contains((string)file_get_contents($routes),"name('erp.attendance')")){
 fwrite(STDERR,"NO-GO: required ERP attendance and protected HR routes missing\n");exit(2);
}
$before=<<<'OLD'
        $user=$request->user();
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin/Supervisor permission required.');
        return $next($request);
OLD;
$after=<<<'NEW'
        $user=$request->user();
        // HR_ATTENDANCE_LINK_403_P7: GET-only safe navigation. NEVER grant HR the Admin controller.
        // HR has a separate protected attendance, leave and ROT workflow with audit trail.
        if ($user instanceof ErpAuthUser && $user->active && !$user->isAdmin() &&
            $request->isMethod('GET') &&
            ($request->is('erp/attendance') || $request->is('erp'))) {
            if (\App\Support\HrAccess::permitted($user)) {
                $url=$request->is('erp/attendance')
                    ? route('erp.hr.operations').'#attendance'
                    : route('erp.hr.dashboard');
                return redirect()->to($url,302);
            }
        }
        abort_unless($user instanceof ErpAuthUser && $user->active && $user->isAdmin(),403,'Admin/Supervisor permission required.');
        return $next($request);
NEW;
if(substr_count($original,$before)!==1){fwrite(STDERR,"NO-GO: source permission guard changed, no modifications\n");exit(2);}
$patched=str_replace($before,$after,$original);
try{token_get_all($patched,TOKEN_PARSE);}catch(Throwable $e){fwrite(STDERR,"NO-GO: invalid middleware syntax ".$e->getMessage()."\n");exit(2);}
echo "HR ATTENDANCE 403 PRECHECK PASS; original_sha256=".hash('sha256',$original)." corrected_sha256=".hash('sha256',$patched)."\n";
echo "SCOPE: only HR GET /erp/attendance or /erp redirects. Admin & Staff POST/other routes unchanged.\n";
if($mode==='check'){echo "READ ONLY PASS: no files/data modified.\n";exit(0);}
$backup=$base.'/storage/app/hr_attendance_403_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO: private backup directory creation failed\n");exit(2);}
if(!copy($target,$backup.'/EnsureErpAdmin.php')||hash_file('sha256',$backup.'/EnsureErpAdmin.php')!==hash('sha256',$original)){
 fwrite(STDERR,"NO-GO: backup integrity failed\n");exit(2);
}
if(hash_file('sha256',$target)!==hash('sha256',$original)){fwrite(STDERR,"NO-GO: middleware changed concurrently\n");exit(2);}
$temp=tempnam(dirname($target),'.hr_att_');
if(!$temp||file_put_contents($temp,$patched,LOCK_EX)!==strlen($patched)){if($temp)@unlink($temp);fwrite(STDERR,"NO-GO: stage middleware write failed\n");exit(2);}
chmod($temp,fileperms($target)&0777);
if(!rename($temp,$target)||hash_file('sha256',$target)!==hash('sha256',$patched)){
 if($temp)@unlink($temp);
 copy($backup.'/EnsureErpAdmin.php',$target);
 fwrite(STDERR,"NO-GO: failed install; source restored from backup\n");exit(2);
}
echo "HR ATTENDANCE 403 APPLIED; BACKUP=".$backup."\n";
echo "No attendance data, staff roles, salary, API permissions or geo-fence settings changed.\n";
