<?php
declare(strict_types=1);
// AM129 staging QA. SELECT-only preflight and an explicitly rollback-wrapped
// negative test using an existing active staff member with a prior missed
// checkout. No attendance row or geolocation record can be persisted.
if(!is_file(getcwd().'/artisan')){echo "AM129_STOP_NOT_STAGING\n";exit(2);}
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Services\AttendanceService;
use App\Models\Attendance;
function am129Fail(string $reason): never {
  echo "AM129_VERIFY_FAIL_".$reason."\n";exit(3);
}
$src=file_get_contents('app/Services/AttendanceService.php');
if(!$src||!str_contains($src,'AM129_PREVIOUS_DAY_MISSED_CHECKOUT_BLOCK'))am129Fail('SOURCE_MARKER_MISSING');
$inPos=strpos($src,'public function checkIn(');
$outPos=strpos($src,'public function checkOut(');
$guardPos=strpos($src,'AM129_PREVIOUS_DAY_MISSED_CHECKOUT_BLOCK');
if($inPos===false||$outPos===false||$guardPos===false||!($inPos<$guardPos && $guardPos<$outPos))
 am129Fail('GUARD_NOT_IN_CHECKIN');
$checkOutSrc=substr($src,$outPos);
if(!str_contains($checkOutSrc,"->whereDate('date', \$now->toDateString())"))
 am129Fail('STAFF_BACKDATE_GUARD_MISSING');
$today=now('Asia/Kolkata')->toDateString();
$open=DB::table('attendance')->where('date','<',$today)
 ->whereNotNull('check_in')->whereNull('check_out');
$count=$open->count();
$staffCount=(clone $open)->distinct()->count('staff_id');
echo "AM129_HISTORICAL_OPEN_ROWS=".$count."\n";
echo "AM129_AFFECTED_STAFF_COUNT=".$staffCount."\n";
echo "AM129_CHECKOUT_STILL_CURRENT_DATE_ONLY=YES\n";
$eligible=DB::table('attendance as a')
 ->join('staff as s','s.id','=','a.staff_id')
 ->where('a.date','<',$today)
 ->whereNotNull('a.check_in')->whereNull('a.check_out')
 ->whereRaw("LOWER(s.status)='active'")->where('s.attendance_enabled',1)
 ->select('a.staff_id','a.date')->orderBy('a.date')->first();
if($eligible){
    $response='';
    $before=(clone $open)->count();
    DB::beginTransaction();
    try{
        // Deliberately invalid coordinates; if the prior-day guard is absent
        // this MUST NOT be permitted to create attendance even in test.
        app(AttendanceService::class)->checkIn((int)$eligible->staff_id,[
            'latitude'=>0.0,'longitude'=>0.0,'accuracy_m'=>10000,
            'is_mocked'=>true,
        ]);
        am129Fail('MISSING_CHECKOUT_DID_NOT_BLOCK');
    }catch(ValidationException $e){
        $m=implode(' ',array_map(fn($a)=>implode(' ',$a),$e->errors()));
        if(!str_contains($m,'CHECK-IN BLOCKED') || !str_contains($m,'Contact Admin')
            || !str_contains($m,'cannot back-date'))am129Fail('WRONG_ERROR_OR_GPS_ERROR');
        $response='AM129_STAFF_PREVIOUS_CHECKOUT_BLOCK_ENFORCED=YES';
    }finally{
        DB::rollBack();
    }
    if((clone $open)->count()!==$before)am129Fail('HISTORICAL_ROW_COUNT_CHANGED');
    echo $response."\n";
}else{
    echo "AM129_NO_ACTIVE_OPEN_SAMPLE_SOURCE_GUARD_ONLY\n";
}
echo "AM129_VERIFICATION_COMPLETE_NO_DATA_WRITES\n";
