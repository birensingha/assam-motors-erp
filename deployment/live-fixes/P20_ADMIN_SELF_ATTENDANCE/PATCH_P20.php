<?php
declare(strict_types=1);
/** P20: Add Admin My Attendance self Check-In/Out controls without changing Staff API or normal geofence. */
$mode=$argv[1]??'check';
$root=rtrim((string)($argv[2]??''),'/');
if(!in_array($mode,['check','apply'],true)||!is_file($root.'/artisan')){
 fwrite(STDERR,"Usage: php PATCH_P20.php check|apply /staging-laravel-root\n");exit(2);
}
$paths=[
 'routes/web.php',
 'app/Http/Controllers/Web/AdminNativeModuleController.php',
 'resources/views/admin/native/attendance.blade.php'
];
$newPath='app/Http/Controllers/Web/AdminSelfAttendanceController.php';
$payload=__DIR__.'/payload/AdminSelfAttendanceController.php';
if(!is_file($payload)||is_link($payload)){fwrite(STDERR,"P20_NO_GO_PAYLOAD\n");exit(3);}
if(file_exists($root.'/'.$newPath)){fwrite(STDERR,"P20_NO_GO_CONTROLLER_ALREADY_EXISTS\n");exit(3);}
$src=[];
foreach($paths as $p){
 if(!is_file($root.'/'.$p)||is_link($root.'/'.$p)){fwrite(STDERR,"P20_NO_GO_SOURCE ".$p."\n");exit(3);}
 $src[$p]=(string)file_get_contents($root.'/'.$p);
 if(str_contains($src[$p],'P20_ADMIN_SELF_ATTENDANCE')){
    fwrite(STDERR,"P20_NO_GO_PARTIAL_INSTALLATION\n");exit(3);
 }
}
function p20Once(string $s,string $old,string $new,string $label):string {
 $n=substr_count($s,$old);
 if($n!==1)throw new RuntimeException("P20_NO_GO_ANCHOR ".$label." ".$n);
 return str_replace($old,$new,$s);
}
try{
 $routes=$src['routes/web.php'];
 if(!preg_match('/^([ \t]*Route::get\(\s*["\x27]\/attendance["\x27][^\n]*name\(["\x27]erp\.attendance["\x27]\);)[ \t]*$/m',$routes,$hit)){
   throw new RuntimeException('Attendance GET route not found exactly once');
 }
 if(substr_count($routes,$hit[1])!==1)throw new RuntimeException('Attendance route count mismatch');
 $extra=<<<'ROUTES'

    // P20_ADMIN_SELF_ATTENDANCE: Owner/Admin only; linked Staff record (not a posted staff_id).
    Route::post('/attendance/my-check-in', [\App\Http\Controllers\Web\AdminSelfAttendanceController::class,'checkIn'])
        ->name('erp.attendance.self.checkin');
    Route::post('/attendance/my-check-out', [\App\Http\Controllers\Web\AdminSelfAttendanceController::class,'checkOut'])
        ->name('erp.attendance.self.checkout');
ROUTES;
 $routes=p20Once($routes,$hit[1],$hit[1].$extra,'attendance_routes');

 $ctrl=$src['app/Http/Controllers/Web/AdminNativeModuleController.php'];
 $old="        return view('admin.native.attendance',compact(\n";
 $new=<<<'PHP'
        // P20_ADMIN_SELF_ATTENDANCE: today's linked staff record is independent of report filters.
        $myUser=$request->user();
        $myIsOwnerAdmin=$myUser instanceof \App\Models\ErpAuthUser
            && (bool)$myUser->active && strtolower(trim((string)$myUser->role))==='admin';
        $myStaffId=$myIsOwnerAdmin ? (int)($myUser->staff_id ?? 0) : 0;
        $myStaffRecord=$myStaffId>0 ? DB::table('staff')->where('id',$myStaffId)->first() : null;
        $mySelfAttendanceEnabled=$myStaffRecord
            && strtolower(trim((string)($myStaffRecord->status??'')))==='active'
            && (bool)($myStaffRecord->attendance_enabled??false);
        $myDate=now('Asia/Kolkata')->toDateString();
        $myAttendanceRows=$myStaffId>0
            ? DB::table('attendance')->where('staff_id',$myStaffId)
                ->where('date',$myDate)->orderBy('id')->limit(3)->get()
            : collect();
        $myAttendanceCount=$myAttendanceRows->count();
        $myAttendance=$myAttendanceRows->first();

        return view('admin.native.attendance',compact(
PHP;
 $ctrl=p20Once($ctrl,$old,$new,'controller_attendance_return');
 $ctrl=p20Once($ctrl,
    "            'daysInMonth'\n        ));",
    "            'daysInMonth',\n            'myIsOwnerAdmin','mySelfAttendanceEnabled','myDate',\n            'myAttendance','myAttendanceCount','myStaffRecord'\n        ));",
    'attendance_compact');

 $view=$src['resources/views/admin/native/attendance.blade.php'];
 $anchor='<div class="card attendance-filter-card">';
 $card=<<<'BLADE'
{{-- P20_ADMIN_SELF_ATTENDANCE: dedicated Owner/Admin self-attendance panel (no GPS fabrication) --}}
@if($myIsOwnerAdmin)
<style>
.am20-own-attendance{border:1px solid #cdddec;border-top:4px solid #236aae;border-radius:16px;background:linear-gradient(130deg,#f4f9ff,#fff);box-shadow:0 5px 17px #173b6620;padding:17px 19px;margin:14px 0;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.am20-own-attendance h2{font-size:21px;color:#12375f;margin:3px 0}
.am20-own-attendance p{font-size:12px;color:#557089;margin:6px 0}
.am20-own-status{font-size:12px;display:inline-block;padding:5px 10px;border-radius:16px;color:#185a46;background:#dcf4e9;font-weight:850}
.am20-own-pending{background:#fff0d7;color:#9b5611}
.am20-own-metrics{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}
.am20-own-metrics span{padding:8px 11px;background:#eff5fd;border:1px solid #d9e7f5;border-radius:9px;font-size:12px;color:#244d76}
.am20-own-submit{font-weight:800;padding:12px 20px;border:0;border-radius:11px;min-width:145px;min-height:46px;cursor:pointer;background:#137651;color:#fff;box-shadow:0 4px 12px #0e644b2a}
.am20-own-submit.out{background:#214f97}
.am20-own-submit:focus-visible{outline:3px solid #e8b839;outline-offset:3px}
.am20-own-warn{color:#9a351c;font-size:13px;font-weight:700}
@media(max-width:600px){.am20-own-attendance{padding:14px}.am20-own-submit{width:100%}}
</style>
<section class="am20-own-attendance" id="my-attendance" aria-label="My Attendance">
 <div style="flex:1 1 300px">
  <span style="color:#467296;font-weight:850;font-size:11px;letter-spacing:.08em">OWNER / ADMIN · STAFF ATTENDANCE</span>
  <h2>My Attendance <span style="color:#6c829b;font-size:13px;font-weight:600">{{ $myDate }} (IST)</span></h2>
  <p>Apna real-time Check-In / Check-Out yahan mark karein. Admin remote attendance permitted; normal workshop Staff geofence unchanged.</p>
  @if(!$mySelfAttendanceEnabled)
    <div class="am20-own-warn">Admin login ke linked Staff Master mein Attendance Enabled / Active status check karna zaroori hai. Action blocked.</div>
  @elseif($myAttendanceCount>1)
    <div class="am20-own-warn">Duplicate attendance rows detected. HR se record verify karayein; self actions disabled.</div>
  @elseif(!$myAttendance)
    <span class="am20-own-status am20-own-pending">NOT CHECKED IN</span>
  @elseif($myAttendance->check_in && !$myAttendance->check_out)
    <span class="am20-own-status">CHECKED IN · CHECK-OUT PENDING</span>
  @elseif($myAttendance->check_in && $myAttendance->check_out)
    <span class="am20-own-status">TODAY COMPLETE</span>
  @else
    <span class="am20-own-status am20-own-pending">HR REVIEW REQUIRED</span>
  @endif
  @if($myAttendanceCount===1 && $myAttendance)
   <div class="am20-own-metrics">
    <span>Check-In: <strong>{{ $myAttendance->check_in ? \Carbon\Carbon::parse($myAttendance->check_in)->format('h:i A') : '—' }}</strong></span>
    <span>Check-Out: <strong>{{ $myAttendance->check_out ? \Carbon\Carbon::parse($myAttendance->check_out)->format('h:i A') : '—' }}</strong></span>
    <span>Worked: <strong>{{ number_format((float)($myAttendance->worked_hours??0),2) }} h</strong></span>
   </div>
  @endif
 </div>
 <div style="display:flex;flex-direction:column;gap:7px;align-items:stretch">
  @if($mySelfAttendanceEnabled && $myAttendanceCount===0)
   <form method="post" action="{{ route('erp.attendance.self.checkin') }}" onsubmit="return confirm('Mark YOUR Check-In using current server time (IST)?')">
    @csrf <button class="am20-own-submit" type="submit">✓ Check-In Now</button>
   </form>
  @elseif($mySelfAttendanceEnabled && $myAttendanceCount===1 && $myAttendance && $myAttendance->check_in && !$myAttendance->check_out)
   <form method="post" action="{{ route('erp.attendance.self.checkout') }}" onsubmit="return confirm('Mark YOUR Check-Out using current server time (IST)?')">
    @csrf <button class="am20-own-submit out" type="submit">↪ Check-Out Now</button>
   </form>
  @endif
  <a href="{{ route('erp.attendance',['month'=>now('Asia/Kolkata')->format('Y-m'),'staff_id'=>(int)($myStaffRecord->id??0)]) }}" style="font-size:12px;color:#17527c;text-align:center;font-weight:750">View My Attendance History</a>
 </div>
</section>
@if($errors->has('attendance') || $errors->has('staff'))
 <div class="card" role="alert" style="background:#fff2f2;color:#91231e;border-left:4px solid #c62828;padding:12px;margin:8px 0">
  {{ $errors->first('attendance') ?: $errors->first('staff') }}
 </div>
@endif
@endif

BLADE;
 $view=p20Once($view,$anchor,$card.$anchor,'self_attendance_card');
 $out=[
 'routes/web.php'=>$routes,
 'app/Http/Controllers/Web/AdminNativeModuleController.php'=>$ctrl,
 'resources/views/admin/native/attendance.blade.php'=>$view,
 ];
 $payloadSource=(string)file_get_contents($payload);
 token_get_all($payloadSource,TOKEN_PARSE);
 token_get_all($routes,TOKEN_PARSE);
 token_get_all($ctrl,TOKEN_PARSE);
 chdir($root);
 require 'vendor/autoload.php';
 $app=require 'bootstrap/app.php';
 $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 token_get_all(app('blade.compiler')->compileString($view),TOKEN_PARSE);
 echo 'P20_PRECHECK_BLADE_AND_PHP_PASS'."\n";
 foreach($paths as $p)echo 'P20_HASH '.$p.' '.hash('sha256',$src[$p]).' '.hash('sha256',$out[$p])."\n";
} catch(Throwable $e){
 fwrite(STDERR,'P20_NO_GO_PRECHECK '.$e->getMessage()."\n");exit(4);
}
if($mode==='check'){echo "P20_DRY_RUN_NO_SOURCE_OR_ATTENDANCE_WRITES\n";exit(0);}
$backup=$root.'/storage/app/p20_admin_self_attendance_backup_'.date('Ymd_His').'_'.getmypid();
if(!mkdir($backup,0700,true)){fwrite(STDERR,"P20_NO_GO_BACKUP\n");exit(5);}
foreach($paths as $p){
 $dest=$backup.'/'.str_replace('/','__',$p);
 if(!copy($root.'/'.$p,$dest)||hash_file('sha256',$dest)!==hash('sha256',$src[$p])){
     fwrite(STDERR,"P20_NO_GO_BACKUP_HASH\n");exit(5);
 }
}
$written=[];
try{
 foreach($paths as $p){
   $dest=$root.'/'.$p;
   if(hash_file('sha256',$dest)!==hash('sha256',$src[$p]))throw new RuntimeException('Concurrent source edit '.$p);
   $tmp=tempnam(dirname($dest),'.p20_');
   if(!$tmp||file_put_contents($tmp,$out[$p],LOCK_EX)!==strlen($out[$p]))throw new RuntimeException('Temp write failed');
   chmod($tmp,fileperms($dest)&0777);
   if(!rename($tmp,$dest))throw new RuntimeException('Atomic write failed');
   $written[]=$p;
   if(hash_file('sha256',$dest)!==hash('sha256',$out[$p]))throw new RuntimeException('Hash mismatch');
 }
 if(file_exists($root.'/'.$newPath))throw new RuntimeException('Controller concurrently created');
 if(!copy($payload,$root.'/'.$newPath))throw new RuntimeException('Controller file copy failed');
 $written[]=$newPath;
 if(hash_file('sha256',$root.'/'.$newPath)!==hash_file('sha256',$payload))
     throw new RuntimeException('New controller checksum');
 echo "P20_APPLIED_BACKUP=".$backup."\n";
 echo "P20_SOURCE_ONLY_NO_ATTENDANCE_OR_GEOFENCE_DATA_WRITES\n";
}catch(Throwable $e){
 foreach(array_reverse($written) as $p){
   if($p===$newPath)@unlink($root.'/'.$p);
   else copy($backup.'/'.str_replace('/','__',$p),$root.'/'.$p);
 }
 fwrite(STDERR,"P20_SOURCE_ROLLBACK ".$e->getMessage()."\n");exit(6);
}
