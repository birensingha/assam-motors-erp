<?php
/** AM226 strict read-only aggregated alert audit, NO staff IDs/names/date targets. */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){exit(2);}
$root=realpath((string)($argv[1]??''));
if(!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan')){
    fwrite(STDERR,"AM226_AGG_STOP_NOT_STAGING\n");exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
echo "AM226_AGG_READ_ONLY_BEGIN\n";
echo "AM226_STAFF_LOGIN_EVENTS_EXISTS ".(Schema::hasTable('staff_login_events')?'YES':'NO')."\n";
if(Schema::hasTable('staff_login_events')){
    echo "AM226_STAFF_LOGIN_EVENTS_COLUMNS ".implode(',',Schema::getColumnListing('staff_login_events'))."\n";
}
if(!Schema::hasTable('staff_reminder_state')){
    echo "AM226_AGG_STOP_REMINDER_TABLE_MISSING\n";exit(2);
}
$now=now('Asia/Kolkata')->toDateString();
$rows=DB::table('staff_reminder_state')
 ->selectRaw('reminder_type,COUNT(*) AS total,SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) AS open_count,SUM(CASE WHEN resolved_at IS NULL AND first_due_at < ? THEN 1 ELSE 0 END) AS due_open',[$now])
 ->groupBy('reminder_type')->orderBy('reminder_type')->get();
foreach($rows as $row){
 echo "AM226_ALERT_TYPE ".preg_replace('/[^A-Z0-9_]/','',strtoupper((string)$row->reminder_type))." TOTAL ".(int)$row->total." OPEN ".(int)$row->open_count." PRIOR_DUE ".(int)$row->due_open."\n";
}
$multiple=DB::table('staff_reminder_state')
 ->select('staff_id','reminder_type','target_key',DB::raw('COUNT(*) AS c'))
 ->groupBy('staff_id','reminder_type','target_key')->havingRaw('COUNT(*)>1')->get()->count();
echo "AM226_DUPLICATE_IDENTITY_GROUPS ".$multiple."\n";
echo "AM226_AGG_READ_ONLY_COMPLETE\n";
