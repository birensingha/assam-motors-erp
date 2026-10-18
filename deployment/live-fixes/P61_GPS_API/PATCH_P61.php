<?php
declare(strict_types=1);
/**
 * P61: accept native-app opt-in GPS uploads for linked active Admin / verified HR,
 * independent of attendance, while retaining Staff duty GPS restrictions.
 * Android 6.3.9 requires consent, precise+background OS permissions, and shows
 * a persistent tracking notification. No forced tracking or data modification.
 */
$mode=(string)($argv[1]??'check');
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file("$root/artisan"))exit(2);
$f="$root/app/Http/Controllers/Api/V1/StaffLocationController.php";
if(!is_file($f)||is_link($f)){echo "P61_NO_GO_SOURCE_MISSING\n";exit(3);}
$old=(string)file_get_contents($f);
if(str_contains($old,'P61_ROLE_GPS_REMOTE')){echo "P61_ALREADY_INSTALLED\n";exit(0);}
$new=$old;
function swap61(string &$s,string $a,string $b,string $label):void{
 $n=substr_count($s,$a);if($n!==1)throw new RuntimeException('P61_ANCHOR_'.$label.'_'.$n);
 $s=str_replace($a,$b,$s);
}
try{
 $from='abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff() && !$user->isAdmin(), 403, \'Linked Staff login required.\');';
 $to=<<<'CODE'
abort_unless($user instanceof ErpAuthUser && $user->active && $user->isStaff(), 403, 'Active linked Staff account required.');
        // P61_ROLE_GPS_REMOTE: Staff must be on duty. Explicitly opted-in native
        // HR/Admin session can report outside workshop without check-in.
        $privileged = $user->isAdmin() || \App\Support\HrAccess::permitted($user);
        $employee = \Illuminate\Support\Facades\DB::table('staff')->where('id',(int)$user->staff_id)->first();
        abort_unless($employee && strtolower(trim((string)$employee->status))==='active',
            403,'Active linked Staff Master record required.');
CODE;
 swap61($new,$from,$to,'AUTH');
 $needle=<<<'CODE'
        if (!$attendance) {
            return response()->json(['message' => 'No active duty attendance.', 'reason' => 'NO_DUTY_ATTENDANCE'], 409);
        }
CODE;
 $replace=<<<'CODE'
        if (!$attendance && !$privileged) {
            return response()->json(['message' => 'No active duty attendance.', 'reason' => 'NO_DUTY_ATTENDANCE'], 409);
        }
CODE;
 swap61($new,$needle,$replace,'ATTENDANCE');
 $from=<<<'CODE'
        if ($recordedAt->lt(CarbonImmutable::parse($attendance->check_in, 'Asia/Kolkata')->subMinutes(5))) {
            return response()->json(['message' => 'Location sample is outside duty window.', 'reason' => 'OUTSIDE_DUTY_WINDOW'], 409);
        }
CODE;
 $to=<<<'CODE'
        if ($attendance && !$privileged &&
            $recordedAt->lt(CarbonImmutable::parse($attendance->check_in, 'Asia/Kolkata')->subMinutes(5))) {
            return response()->json(['message' => 'Location sample is outside duty window.', 'reason' => 'OUTSIDE_DUTY_WINDOW'], 409);
        }
        if ($privileged) {
            // Privileged reporting must originate from the opt-in native app;
            // non-native/web requests are not a replacement for user consent.
            if (($data['source']??null) !== 'ANDROID_FOREGROUND_NATIVE_60S') {
                return response()->json(['message'=>'Enable Login-to-Logout GPS in Android Settings first.',
                    'reason'=>'PRIVILEGED_NATIVE_GPS_REQUIRED'],403);
            }
            if (abs(CarbonImmutable::now('Asia/Kolkata')->getTimestamp()-$recordedAt->getTimestamp()) > 180) {
                return response()->json(['message'=>'Fresh native GPS fix required.',
                    'reason'=>'STALE_GPS_FIX'],422);
            }
        }
CODE;
 swap61($new,$from,$to,'DUTY_WINDOW');
 swap61($new,"'attendance_id' => \$attendance->id,","'attendance_id' => \$attendance?->id,",'NULLABLE_LINK');
 swap61($new,"'source' => substr((string)(\$data['source'] ?? 'ANDROID_FOREGROUND'), 0, 40),",
      "'source' => \$privileged ? 'ADMIN_HR_OPT_IN_GPS' : substr((string)(\$data['source'] ?? 'ANDROID_FOREGROUND'), 0, 40),",'SOURCE');
 chdir($root);require "$root/vendor/autoload.php";$app=require "$root/bootstrap/app.php";
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $db=\Illuminate\Support\Facades\DB::selectOne("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='staff_location_logs' AND COLUMN_NAME='attendance_id'");
 if(!$db||$db->IS_NULLABLE!=='YES')throw new RuntimeException('P61_ATTENDANCE_FOREIGN_KEY_NON_NULL');
 foreach(['staff','erp_auth_users','staff_location_logs','staff_location_settings'] as $table)
  if(!\Illuminate\Support\Facades\Schema::hasTable($table))throw new RuntimeException('P61_REQUIRED_TABLE_'.$table);
 token_get_all($new,TOKEN_PARSE);
 echo "P61_SOURCE_AND_SCHEMA_PREFLIGHT_PASS\n";
}catch(Throwable $e){fwrite(STDERR,"P61_NO_GO ".$e->getMessage()."\n");exit(4);}
if($mode==='check'){echo "P61_DRY_RUN_NO_WRITES\n";exit(0);}
$backup="$root/storage/app/p61_role_gps_".date('Ymd_His').'_'.getmypid().'.php';
if(!copy($f,$backup)||hash_file('sha256',$backup)!==hash('sha256',$old)){echo "P61_BACKUP_FAILED\n";exit(5);}
if(hash_file('sha256',$f)!==hash('sha256',$old)){echo "P61_CONCURRENT_CHANGE\n";exit(6);}
$tmp=tempnam(dirname($f),'.p61_');
if(!$tmp||file_put_contents($tmp,$new,LOCK_EX)!==strlen($new)||!chmod($tmp,fileperms($f)&0777)||!rename($tmp,$f)){
 copy($backup,$f);echo "P61_SOURCE_RESTORED\n";exit(7);
}
echo "P61_SOURCE_INSTALLED_BACKUP=$backup\n";
echo "P61_NO_ATTENDANCE_OR_LOCATION_HISTORY_WRITES\n";