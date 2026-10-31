<?php
declare(strict_types=1);
/** AM226 aggregate-only readback after deploy; read-only, no staff or date values. */
if(PHP_SAPI!=='cli'){exit(2);}
$root=realpath((string)($argv[1]??''));
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan')){
    fwrite(STDERR,"AM226_VERIFY_STOP_NOT_STAGING\n");exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
$src=[
 $root.'/app/Services/StaffReminderEngine.php',
 $root.'/app/Services/StaffDashboardService.php',
 $root.'/resources/views/staff/portal.blade.php'
];
foreach($src as $file){
 if(!is_file($file)||!str_contains((string)file_get_contents($file),'AM226_MANDATORY_ALERTS_DEDUP')){
    echo "AM226_VERIFY_SOURCE_MARKER_MISSING\n";exit(3);
 }
}
echo "AM226_VERIFY_THREE_SOURCE_MARKERS_PASS\n";
if(!Schema::hasTable('staff_reminder_state') || !Schema::hasTable('attendance')){
    echo "AM226_VERIFY_SCHEMA_MISSING\n";exit(3);
}
$groups=DB::table('staff_reminder_state')
 ->selectRaw('reminder_type, COUNT(*) AS total, SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) AS active')
 ->groupBy('reminder_type')->orderBy('reminder_type')->get();
foreach($groups as $row){
 $type=preg_replace('/[^A-Z_]/','',strtoupper((string)$row->reminder_type));
 echo "AM226_VERIFY_TYPE ".$type." TOTAL ".(int)$row->total." ACTIVE ".(int)$row->active."\n";
}
$stable=DB::table('staff_reminder_state')
 ->where('reminder_type','NOTIFICATION_DISABLED')
 ->where('target_key','permission')->count();
echo "AM226_VERIFY_STABLE_PERMISSION_STATES ".$stable."\n";
$matchedIn=DB::table('staff_reminder_state as s')
 ->join('attendance as a',function($join){
   $join->on('s.staff_id','=','a.staff_id')->on('s.target_key','=','a.date');
 })
 ->where('s.reminder_type','CHECKIN_MISSED')->whereNull('s.resolved_at')
 ->whereNotNull('a.check_in')->count();
$matchedOut=DB::table('staff_reminder_state as s')
 ->join('attendance as a',function($join){
   $join->on('s.staff_id','=','a.staff_id')->on('s.target_key','=','a.date');
 })
 ->where('s.reminder_type','CHECKOUT_PENDING')->whereNull('s.resolved_at')
 ->whereNotNull('a.check_in')->whereNotNull('a.check_out')->count();
echo "AM226_VERIFY_VERIFIED_CHECKIN_STILL_OPEN ".$matchedIn."\n";
echo "AM226_VERIFY_VERIFIED_CHECKOUT_STILL_OPEN ".$matchedOut."\n";
$today=now('Asia/Kolkata')->toDateString();
foreach(['CHECKIN_MISSED','CHECKOUT_PENDING'] as $type){
 $q=DB::table('staff_reminder_state as r')
    ->join('attendance as a',function($join){
      $join->on('r.staff_id','=','a.staff_id')->on('r.target_key','=','a.date');
    })
    ->leftJoin('staff as s','s.id','=','r.staff_id')
    ->where('r.reminder_type',$type)->whereNull('r.resolved_at')
    ->whereNotNull('a.check_in');
 if($type==='CHECKOUT_PENDING')$q->whereNotNull('a.check_out');
 $rows=$q->selectRaw("CASE WHEN LOWER(COALESCE(s.status,''))='active' AND COALESCE(s.attendance_enabled,0)=1 THEN 'eligible' ELSE 'inactive' END AS bucket, CASE WHEN r.target_key < ? THEN 'historical' WHEN r.target_key = ? THEN 'today' ELSE 'other' END AS age_bucket, COUNT(*) AS n",[$today,$today])
    ->groupBy('bucket','age_bucket')->get();
 foreach($rows as $row){
   echo "AM226_VERIFY_HISTORICAL_MATCH ".$type." BUCKET ".$row->bucket." AGE ".$row->age_bucket." COUNT ".(int)$row->n."\n";
 }
}

$identityDupes=(int)DB::table('staff_reminder_state')
 ->selectRaw('staff_id,reminder_type,target_key,COUNT(*) AS n')
 ->groupBy('staff_id','reminder_type','target_key')->havingRaw('COUNT(*) > 1')->get()->count();
echo "AM226_VERIFY_DUPLICATE_IDENTITIES ".$identityDupes."\n";
echo "AM226_VERIFY_AGGREGATE_READ_ONLY_COMPLETE\n";
