<?php
declare(strict_types=1);
/* Assam Motors HR remote login, staging only, fail closed. */
$mode = $argv[1] ?? 'check';
if (!in_array($mode, ['check', 'apply'], true)) {fwrite(STDERR,"Usage: php APPLY_HR_REMOTE_LOGIN.php check|apply\n");exit(2);}
$home=rtrim((string)getenv('HOME'),'/');
$app=$home.'/domains/assammotors.com/assam-erp-staging';
$file=$app.'/app/Http/Controllers/Api/V1/AuthController.php';
if (!$home || realpath($app)!==$app || !is_file($app.'/artisan') || !is_file($file) || is_link($file)) {fwrite(STDERR,"NO-GO: staging application/auth file mismatch\n");exit(2);}
$raw=(string)file_get_contents($file);
$marker='ASSAM_HR_REMOTE_LOGIN_P5';
if (str_contains($raw,$marker)) {echo "ALREADY_APPLIED: HR remote login marker found. No changes.\n";exit(0);}
$patch=[
    [
        '$loginGeo = ['."'required' => false, 'verified' => false, 'admin_exempt' => ".'$user->isAdmin()' .'];',
        '// ASSAM_HR_REMOTE_LOGIN_P5: verify HR server-side before waiving LOGIN geofence.'."\n".
        '        $hrRemote = $user->isStaff() && \App\Support\HrAccess::permitted($user);'."\n".
        '        $remoteExempt = $user->isAdmin() || $hrRemote;'."\n".
        '        $loginGeo = ['."'required' => false, 'verified' => false, 'admin_exempt' => ".'$user->isAdmin()'.", 'hr_exempt' => ".'$hrRemote'.'];',
    ],
    [
        'if (!$user->isAdmin()) {',
        'if (!$remoteExempt) {',
    ],
    [
        "'source' => 'APP_NATIVE_GEOFENCE',",
        "'source' => \$hrRemote ? 'APP_NATIVE_HR_REMOTE' : 'APP_NATIVE_GEOFENCE',",
    ],
    [
        "'admin_exempt' => \$user->isAdmin(),\n            ],",
        "'admin_exempt' => \$user->isAdmin(),\n".
        "                'hr_exempt' => \$hrRemote,\n".
        "                'remote_login' => \$remoteExempt,\n            ],",
    ],
];
$out=$raw;
foreach ($patch as $i=>[$before,$after]) {
    if (substr_count($out,$before)!==1) {fwrite(STDERR,"NO-GO: unique source anchor absent/changed: $i\n");exit(2);}
    $out=str_replace($before,$after,$out);
}
if (str_contains($out,'attendance/check-in') || str_contains($out,'attendance/check-out')) {fwrite(STDERR,"NO-GO: AuthController unexpectedly contains attendance routes\n");exit(2);}
try {token_get_all($out,TOKEN_PARSE);} catch (Throwable $e) {fwrite(STDERR,"NO-GO: patched PHP invalid: ".$e->getMessage()."\n");exit(2);}
echo "HR_REMOTE_LOGIN_P5 CHECK PASS; source_sha256=".hash('sha256',$raw)." result_sha256=".hash('sha256',$out)."\n";
if ($mode==='check') {echo "READ_ONLY_PASS: no production/staging files or data changed.\n";exit(0);}
$backup=$app.'/storage/app/hr_remote_login_p5_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)) {fwrite(STDERR,"NO-GO: private backup directory failed\n");exit(2);}
if(!copy($file,$backup.'/AuthController.php') || hash_file('sha256',$backup.'/AuthController.php')!==hash('sha256',$raw)) {fwrite(STDERR,"NO-GO: backup verification failed\n");exit(2);}
if(hash_file('sha256',$file)!==hash('sha256',$raw)) {fwrite(STDERR,"NO-GO: source modified concurrently\n");exit(2);}
$tmp=tempnam(dirname($file),'.hr_remote_');
if(!$tmp || file_put_contents($tmp,$out,LOCK_EX)!==strlen($out)) {if($tmp)@unlink($tmp);fwrite(STDERR,"NO-GO: stage write failed\n");exit(2);}
chmod($tmp,fileperms($file)&0777);
if(!rename($tmp,$file) || hash_file('sha256',$file)!==hash('sha256',$out)) {
   if($tmp)@unlink($tmp);
   copy($backup.'/AuthController.php',$file);
   fwrite(STDERR,"NO-GO: install/check failed, reverted source\n");exit(2);
}
echo "HR_REMOTE_LOGIN_P5 APPLIED; BACKUP=$backup\n";
echo "LOGIN_ONLY: normal staff GPS and attendance geofence unchanged. No DB writes.\n";
