<?php
declare(strict_types=1);
/**
 * P41: Add lightweight Android-inspired Staff desktop motion/selection.
 * ROT notification anchor -> smooth target pulse; no flashing loops/JS or data writes.
 * Existing attendance/ROT action markup untouched. Respects reduced-motion.
 */
$mode=(string)($argv[1]??'check');$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan")){fwrite(STDERR,"P41_USAGE check|apply ERP_ROOT\n");exit(2);}
$file="$root/resources/views/staff/portal.blade.php";
if(!is_file($file)||is_link($file)){fwrite(STDERR,"P41_NO_GO_STAFF_PORTAL\n");exit(3);}
$before=(string)file_get_contents($file);
if(str_contains($before,'P41_STAFF_ANDROID_MOTION_V1')){echo "P41_ALREADY_INSTALLED\n";exit(0);}
if(substr_count(strtolower($before),'</head>')!==1
 || !str_contains($before,'assam-motors-staff-android-theme-v1')
 || !str_contains($before,'class="rot-row"')
 || !str_contains($before,'class="staff-shell"')){
 fwrite(STDERR,"P41_NO_GO_STAFF_STRUCTURE_CHANGED\n");exit(4);
}
$css=<<<'BLADE'

{{-- P41_STAFF_ANDROID_MOTION_V1: desktop-friendly animated focus, same secure ROT actions. --}}
<style id="p41-staff-android-motion">
:root{
 --p41-blue:#11385d;--p41-red:#e45d64;--p41-soft:#eaf1f8
}
.staff-topbar{position:sticky;top:0;z-index:40;backdrop-filter:blur(12px)}
.staff-shell .staff-panel{
 border:1px solid #dce7f1!important;border-top:2px solid #eaf0f8!important;
 box-shadow:0 7px 25px rgba(12,52,94,.075)!important
}
.staff-shell .staff-cards>*{
 position:relative;overflow:hidden;
 border-radius:16px!important;transition:transform .2s ease,box-shadow .2s ease
}
.staff-shell .staff-cards>*::before{
 content:"";position:absolute;inset:0 auto 0 0;width:4px;
 background:linear-gradient(#d95861,#ffabb0)
}
.staff-shell .rot-row,.staff-shell .inspection-review-row{
 position:relative;border-radius:15px!important;
 transition:transform .18s ease,border-color .2s ease,box-shadow .2s ease;
 scroll-margin-top:104px
}
.staff-shell .rot-row:hover,.staff-shell .inspection-review-row:hover{
 border-color:#b5cde0!important;transform:translateY(-2px);
 box-shadow:0 11px 24px rgba(16,52,84,.12)!important
}
.staff-shell .rot-row:target{
 border-color:#d95060!important;background:linear-gradient(110deg,#fff,#fff3f4)!important;
 box-shadow:0 0 0 3px rgba(226,79,94,.18),0 14px 25px rgba(51,79,105,.12)!important;
 animation:p41RotSelectGlow 1.15s ease-in-out 2
}
.staff-shell .reminder.escalated{
 border-left:4px solid #e45d64!important;animation:p41AlertBreath 2.4s ease-in-out 2
}
.staff-shell .staff-primary-link,.staff-shell .rot-row button,.staff-shell .staff-panel button{
 transition:transform .15s ease,filter .15s ease,box-shadow .15s ease
}
.staff-shell .staff-primary-link:active,.staff-shell .rot-row button:active,.staff-shell .staff-panel button:active{
 transform:scale(.975);filter:brightness(.94)
}
.staff-shell .rot-row:focus-within{
 border-color:var(--p41-red)!important;
 box-shadow:0 0 0 3px rgba(228,93,100,.14)!important
}
.staff-shell .status.running,.staff-shell .status.Running{
 border:1px solid #9bdfb7;box-shadow:0 0 0 2px rgba(26,166,91,.1)
}
@keyframes p41RotSelectGlow{
 0%,100%{box-shadow:0 0 0 2px rgba(226,79,94,.16),0 9px 23px rgba(15,39,69,.09)}
 50%{box-shadow:0 0 0 7px rgba(226,79,94,.25),0 12px 27px rgba(15,39,69,.12)}
}
@keyframes p41AlertBreath{
 0%,100%{box-shadow:0 2px 8px rgba(15,39,69,.055)}
 50%{box-shadow:0 0 0 4px rgba(228,93,100,.1)}
}
@media(prefers-reduced-motion:reduce){
 .staff-shell .rot-row,.staff-shell .inspection-review-row,
 .staff-shell button,.staff-shell .staff-primary-link{
 animation:none!important;transition:none!important;transform:none!important
 }
}
@media(max-width:720px){
 .staff-topbar{position:relative}
 .staff-shell .rot-row:target{scroll-margin-top:15px}
}
</style>
BLADE;
$pos=stripos($before,'</head>');
$after=substr_replace($before,$css."\n",$pos,0);
if(substr_count($after,'P41_STAFF_ANDROID_MOTION_V1')!==1){fwrite(STDERR,"P41_NO_GO_MARKER\n");exit(5);}
try{
 chdir($root);require 'vendor/autoload.php';$app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($after),TOKEN_PARSE);
}catch(Throwable $e){fwrite(STDERR,"P41_NO_GO_BLADE ".get_class($e)."\n");exit(6);}
if($mode==='check'){echo "P41_ANIMATED_FOCUS_DRY_RUN_PASS\n";exit(0);}
$backup=$root.'/storage/app/p41_staff_ui_backup_'.date('Ymd_His').'_'.getmypid().'.blade.php';
if(!copy($file,$backup)||hash_file('sha256',$backup)!==hash('sha256',$before)){fwrite(STDERR,"P41_NO_GO_BACKUP\n");exit(7);}
if(hash_file('sha256',$file)!==hash('sha256',$before)){fwrite(STDERR,"P41_NO_GO_CONCURRENT_CHANGE\n");exit(8);}
$temp=tempnam(dirname($file),'.p41_');
if(!$temp||file_put_contents($temp,$after,LOCK_EX)!==strlen($after)||!chmod($temp,fileperms($file)&0777)||!rename($temp,$file)){
 if($temp)@unlink($temp);fwrite(STDERR,"P41_NO_GO_WRITE\n");exit(9);
}
echo "P41_BACKUP=$backup\nP41_STAFF_PORTAL_MOTION_STAGING_APPLIED\n";
