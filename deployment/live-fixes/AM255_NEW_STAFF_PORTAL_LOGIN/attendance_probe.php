<?php
declare(strict_types=1);
// AM255 read-only attendance/login diagnostic. No GPS coordinates, tokens,
// passwords, email addresses, attendance correction, or staff mutation.
if (PHP_SAPI!=='cli' || ($argc??0)!==2) { echo "STOP_AM255_ATTENDANCE_PROBE_USAGE\n"; exit(2); }
$root=realpath((string)$argv[1]);
if (!$root || basename($root)!=='assam-erp-staging' || !is_file($root.'/artisan')) {
    echo "STOP_AM255_ATTENDANCE_PROBE_NOT_STAGING\n"; exit(2);
}
chdir($root);
require $root.'/vendor/autoload.php';
$app=require $root.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=\Illuminate\Support\Facades\DB::class;
$schema=\Illuminate\Support\Facades\Schema::class;
if (!$schema::hasTable('attendance') || !$schema::hasTable('staff')) {
    echo "STOP_AM255_ATTENDANCE_TABLE_MISSING\n";exit(2);
}
echo "AM255_ATTENDANCE_REPORT_TODAY=".now('Asia/Kolkata')->toDateString()."\n";
$staff=$db::table('staff')->whereRaw("LOWER(COALESCE(status,'active'))='active'")
    ->orderBy('id')->get(['id','staff_code','status']);
foreach ($staff as $s) {
    $now=now('Asia/Kolkata')->toDateString();
    $earlier=$db::table('attendance')->where('staff_id',$s->id)
        ->where('date','<',$now)->whereNotNull('check_in')->whereNull('check_out')
        ->orderBy('date')->first(['date']);
    $today=$db::table('attendance')->where('staff_id',$s->id)
        ->where('date',$now)->orderByDesc('id')->first(['check_in','check_out','status']);
    echo 'AM255_STAFF_ATTENDANCE_STATE='.json_encode([
        'staff_code'=>$s->staff_code,
        'prior_unclosed_date'=>$earlier->date??null,
        'today_checked_in'=>!empty($today->check_in),
        'today_checked_out'=>!empty($today->check_out),
        'today_status'=>$today->status??null,
    ],JSON_UNESCAPED_SLASHES)."\n";
}
foreach($db::select("SELECT INDEX_NAME AS i, COLUMN_NAME AS c, SEQ_IN_INDEX AS seq FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='attendance' ORDER BY INDEX_NAME,SEQ_IN_INDEX") as $idx)
    echo 'AM255_ATTENDANCE_INDEX='.$idx->i.':'.$idx->seq.':'.$idx->c."\n";
foreach (['staff_reminder_states','notification_outbox','staff_devices'] as $t) {
    if (!$schema::hasTable($t))continue;
    $q=$db::table($t);
    echo 'AM255_NOTIFICATION_TABLE_COUNT='.$t.':'.$q->count()."\n";
}
$path=$root.'/storage/logs/laravel.log';
if (is_file($path) && filesize($path)>0) {
    $fh=fopen($path,'rb');
    if($fh) {
        fseek($fh,-min(400000,(int)filesize($path)),SEEK_END);
        $tail=stream_get_contents($fh);
        fclose($fh);
        foreach ([
            'LOCK_WAIT_TIMEOUT'=>'Lock wait timeout exceeded',
            'DEADLOCK'=>'Deadlock found',
            'SQLSTATE'=>'SQLSTATE',
            'MAX_EXECUTION'=>'Maximum execution time',
            'STAFF_LOGIN_INVALID'=>'Staff login details are invalid',
        ] as $kind=>$term) {
            echo 'AM255_LOG_TAIL_SIGNAL='.$kind.':'.substr_count((string)$tail,$term)."\n";
        }
    }
}
echo "AM255_ATTENDANCE_PROBE_READ_ONLY_COMPLETE\n";
