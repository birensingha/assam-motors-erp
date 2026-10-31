<?php
/**
 * AM226 final one-time reconciliation for past/inactive staff.
 * Only resolves reminders where SAME staff AND date have recorded
 * check_in / check_out evidence. Never changes attendance, login events,
 * shift records, app permissions, payroll, job cards or finance.
 * Usage: php reconcile_verified.php check|apply STAGING_ROOT
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array((string)($argv[1]??''),['check','apply'],true)){
    fwrite(STDERR,"AM226_RECONCILE_USAGE check|apply STAGING_ROOT\n");exit(2);
}
$mode=(string)$argv[1];$root=realpath((string)($argv[2]??''));
if(!$root||basename($root)!=='assam-erp-staging'||!is_file($root.'/artisan')){
    fwrite(STDERR,"AM226_RECONCILE_STOP_NOT_STAGING\n");exit(2);
}
chdir($root);require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
if(!str_contains((string)config('app.url',''),'staging.assammotors.com')){
    fwrite(STDERR,"AM226_RECONCILE_STOP_NOT_STAGING_APP_URL\n");exit(2);
}
if(!Schema::hasTable('attendance')||!Schema::hasTable('staff_reminder_state')){
    fwrite(STDERR,"AM226_RECONCILE_STOP_TABLE_MISSING\n");exit(2);
}
function matches(){
    return DB::table('staff_reminder_state as s')
      ->join('attendance as a',function($join){
          $join->on('s.staff_id','=','a.staff_id')
               ->on('s.target_key','=','a.date');
      })
      ->whereNull('s.resolved_at')
      ->where(function($q){
          $q->where(function($a){
                $a->where('s.reminder_type','CHECKIN_MISSED')->whereNotNull('a.check_in');
             })
            ->orWhere(function($a){
                $a->where('s.reminder_type','CHECKOUT_PENDING')
                  ->whereNotNull('a.check_in')->whereNotNull('a.check_out');
            });
      });
}
$eligible=matches()->count();
echo "AM226_RECONCILE_VERIFIED_MATCHES ".$eligible."\n";
if($mode==='check'){
    echo "AM226_RECONCILE_CHECK_PASS_NO_WRITES\n";exit(0);
}
$resolved=DB::transaction(function(){
    $service=app(\App\Services\MandatoryNotificationService::class);
    $rows=matches()->select('s.staff_id','s.reminder_type','s.target_key')
        ->orderBy('s.id')->limit(1001)->get();
    if($rows->count()>1000)throw new RuntimeException('AM226_RECONCILE_STOP_TOO_MANY');
    $changed=0;
    foreach($rows as $row){
        // Reconfirm against attendance inside same transaction, using the
        // original staff_id + date association (never name/registration).
        $proof=DB::table('attendance')
            ->where('staff_id',(int)$row->staff_id)->where('date',(string)$row->target_key)
            ->whereNotNull('check_in');
        if($row->reminder_type==='CHECKOUT_PENDING')$proof->whereNotNull('check_out');
        if(!$proof->exists())continue;
        $changed += $service->resolveByTarget(
            (int)$row->staff_id,(string)$row->reminder_type,(string)$row->target_key
        );
    }
    return $changed;
});
echo "AM226_RECONCILE_VERIFIED_BUSINESS_ACTIONS_RESOLVED ".$resolved."\n";
echo "AM226_RECONCILE_REMAINING_VERIFIED_MATCHES ".matches()->count()."\n";
echo "AM226_RECONCILE_ATTENDANCE_AND_HISTORY_UNCHANGED\n";
