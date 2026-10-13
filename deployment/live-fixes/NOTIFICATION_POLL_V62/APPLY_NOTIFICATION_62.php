<?php
declare(strict_types=1);
// ASSAM MOTORS: staging-only v6.2 native notification polling eligibility.
// Preserve all existing permission, staff identity, GPS and alert-delivery guards.
$mode = $argv[1] ?? 'check';
if (!in_array($mode, ['check','apply'], true)) { fwrite(STDERR,"Usage: php APPLY_NOTIFICATION_62.php check|apply\n"); exit(2); }
$root = rtrim((string)getenv('HOME'),'/').'/domains/assammotors.com/assam-erp-staging';
if (realpath($root)!==$root || !is_file($root.'/artisan')) { fwrite(STDERR,"NO-GO: canonical staging root unavailable\n");exit(2); }
$relPaths=[
 'app/Http/Controllers/Api/V1/AttendanceController.php',
 'app/Http/Controllers/Api/V1/RotWorkflowController.php',
];
$needle="->where('app_version', 'like', '6.1%')";
$replacement="->where(function (\$query) {\n                \$query->where('app_version', 'like', '6.1%')\n                      ->orWhere('app_version', 'like', '6.2%');\n            }) // NOTIFICATION_POLL_V62_20261013";
$files=[];
foreach($relPaths as $rel) {
 $target=$root.'/'.$rel;
 if(!is_file($target)||is_link($target)){fwrite(STDERR,"NO-GO missing/linked ".$rel."\n");exit(2);}
 $source=(string)file_get_contents($target);
 if(str_contains($source,'NOTIFICATION_POLL_V62_20261013')) {
   if(!str_contains($source,"->orWhere('app_version', 'like', '6.2%')")){fwrite(STDERR,"NO-GO partial patch ".$rel."\n");exit(2);}
   echo "ALREADY_PRESENT ".$rel."\n";continue;
 }
 if(substr_count($source,$needle)!==1 || !str_contains($source,"->where('notification_permission', 1)")) {
   fwrite(STDERR,"NO-GO unexpected source guard: ".$rel."\n");exit(2);
 }
 $patched=str_replace($needle,$replacement,$source);
 try {token_get_all($patched,TOKEN_PARSE);} catch(Throwable $e){fwrite(STDERR,"NO-GO syntax: ".$rel."\n");exit(2);}
 $files[$rel]=[$source,$patched];
 echo "PRECHECK_PASS ".$rel." original_sha256=".hash('sha256',$source)." next_sha256=".hash('sha256',$patched)."\n";
}
if(!$files){echo "ALREADY_INSTALLED: no writes\n";exit(0);}
if(count($files)!==2){fwrite(STDERR,"NO-GO both attendance and ROT must be patched atomically\n");exit(2);}
echo "SCOPE: existing version 6.1 polling fallback extended only to 6.2, still requires active staff device and notification_permission=true.\n";
if($mode==='check'){echo "DRY_RUN_PASS: no files changed\n";exit(0);}
$backup=$root.'/storage/app/notification_v62_backup_'.date('Ymd_His').'_'.getmypid();
if(!@mkdir($backup,0700,true)){fwrite(STDERR,"NO-GO cannot create private backup\n");exit(2);}
foreach($files as $rel=>$pair){
 $target=$root.'/'.$rel;$b=$backup.'/'.basename($rel);
 if(!copy($target,$b)||hash_file('sha256',$b)!==hash('sha256',$pair[0])){fwrite(STDERR,"NO-GO backup integrity failed\n");exit(2);}
}
$updated=[];
try{
 foreach($files as $rel=>$pair){
   [$before,$after]=$pair;$target=$root.'/'.$rel;
   if(hash_file('sha256',$target)!==hash('sha256',$before))throw new RuntimeException("Concurrent source change: ".$rel);
   $tmp=tempnam(dirname($target),'.poll_');
   if(!$tmp||file_put_contents($tmp,$after,LOCK_EX)!==strlen($after))throw new RuntimeException("Failed stage: ".$rel);
   chmod($tmp,fileperms($target)&0777);
   if(!rename($tmp,$target))throw new RuntimeException("Failed replacement: ".$rel);
   $updated[]=$rel;
   if(hash_file('sha256',$target)!==hash('sha256',$after))throw new RuntimeException("Digest mismatch: ".$rel);
 }
} catch(Throwable $e) {
 foreach($updated as $rel)copy($backup.'/'.basename($rel),$root.'/'.$rel);
 fwrite(STDERR,"NO-GO: rolled back updated controllers: ".$e->getMessage()."\n");exit(2);
}
echo "APPLIED_ATTENDANCE_AND_ROT_V62_POLLING: PASS BACKUP=".$backup."\n";
echo "NOT MODIFIED: Attendance records, ROT sessions, GPS, security roles, FCM tokens, device rows or Android APK.\n";
