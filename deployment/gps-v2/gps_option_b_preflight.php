<?php
/**
 * ASSAM MOTORS — GPS V2 Option B preflight (READ ONLY).
 * Usage: php gps_option_b_preflight.php /path/to/assam-erp-staging
 * No GPS coordinates, tokens, email addresses or device IDs leave the server.
 */
declare(strict_types=1);
$root = realpath($argv[1] ?? getcwd());
if (!$root || !is_file($root.'/artisan') || !is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR,"ERROR: pass the existing Laravel staging root\n");
    exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$schema=\Illuminate\Support\Facades\Schema::class;
$conn=$db::connection();
$name=$conn->getDatabaseName();
echo "OPTION_B_PHASE_0=READ_ONLY_NO_DELETION\n";
echo "SERVER_NOW_IST=".\Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d_H:i:s')."\n";
foreach(['staff','staff_location_logs','staff_location_settings','attendance','rot_sessions'] as $table) {
    echo 'TABLE_'.strtoupper($table).'='.($schema::hasTable($table)?'PRESENT':'MISSING')."\n";
}
if(!$schema::hasTable('staff_location_logs')||!$schema::hasTable('staff')){
    echo "PURGE_PREFLIGHT=BLOCKED_MISSING_TABLE\n"; exit(3);
}
echo 'GPS_HISTORY_ROWS='.$db::table('staff_location_logs')->count()."\n";
if($schema::hasColumn('staff_location_logs','received_at')){
    $last=$db::table('staff_location_logs')->max('received_at');
    $since=\Carbon\CarbonImmutable::now('Asia/Kolkata')->subMinutes(10)->format('Y-m-d H:i:s');
    $recent=$db::table('staff_location_logs')->where('received_at','>=',$since)->count();
    echo 'GPS_RECENT_10_MIN_ROWS='.$recent."\n";
    echo 'GPS_LAST_RECEIVED_IST='.($last?:'NONE')."\n";
    if($recent>0)echo "PURGE_CUTOVER_GATE=ACTIVE_OLD_WRITERS_MUST_BE_RETIRED_FIRST\n";
} else echo "PURGE_CUTOVER_GATE=BLOCKED_MISSING_RECEIVED_AT\n";
if($schema::hasColumn('staff','staff_code')){
    $dupes=$db::table('staff')->select('staff_code')->selectRaw('COUNT(*) as n')
        ->groupBy('staff_code')->havingRaw('COUNT(*)>1')->get();
    echo 'DUPLICATE_STAFF_CODE_GROUPS='.$dupes->count()."\n";
    foreach($dupes as $d){
        $code=preg_replace('/[^A-Za-z0-9-]/','_',strval($d->staff_code));
        echo 'REVIEW_DUPLICATE_STAFF_CODE='.$code.' COUNT='.$d->n."\n";
    }
}
try{
    $keys=$db::select(<<<'SQL'
SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = ? AND
 (REFERENCED_TABLE_NAME = 'staff_location_logs' OR TABLE_NAME = 'staff_location_logs')
ORDER BY TABLE_NAME,COLUMN_NAME
SQL,[$name]);
    echo 'GPS_FOREIGN_KEY_RELATIONS='.count($keys)."\n";
    foreach($keys as $k){
        foreach(['TABLE_NAME','COLUMN_NAME','CONSTRAINT_NAME','REFERENCED_TABLE_NAME'] as $p){
            echo $p.'='.preg_replace('/[^A-Za-z0-9_-]/','_',strval($k->$p??'NONE')).' ';
        }
        echo "\n";
    }
    $tables=$db::select(<<<'SQL'
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = ? AND
 (TABLE_NAME LIKE '%gps%' OR TABLE_NAME LIKE '%location%' OR TABLE_NAME LIKE '%geofence%')
ORDER BY TABLE_NAME
SQL,[$name]);
    echo 'RELATED_TABLES='.count($tables)."\n";
    foreach($tables as $t)echo 'TABLE_REVIEW='.preg_replace('/[^A-Za-z0-9_-]/','_',$t->TABLE_NAME)."\n";
}catch(\Throwable $e){echo "PURGE_PREFLIGHT=BLOCKED_METADATA_ACCESS_FAILURE\n";exit(3);}
echo "PURGE_PREFLIGHT=INSPECT_COMPLETE_NOT_AUTHORIZED_FOR_EXECUTION\n";
echo "DATABASE_WRITES=ZERO\n";
