#!/usr/bin/env bash
# FIX115-H2: audit-preserving archive of historical unsent TEST messages.
# Defaults to preview; explicit apply needed for changes.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
MODE="${2:-preview}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php
case "$MODE" in preview|apply) ;; *) echo 'MODE must be preview or apply'; exit 2;; esac
export FIX115_ARCHIVE_MODE="$MODE"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;
$t='notification_outbox';
$cutoff='2026-10-19 00:00:00';
if (!Schema::hasTable($t)) {echo "FIX115_ARCHIVE_ABORT=NO_OUTBOX\n";exit(3);}
$col=DB::selectOne("SHOW COLUMNS FROM `notification_outbox` LIKE 'status'");
if (!$col || !preg_match('/^(var)?char\(/i,(string)$col->Type)) {
 echo "FIX115_ARCHIVE_ABORT=STATUS_NOT_VARCHAR\n";exit(4);
}
$query=static fn()=>DB::table($t)
 ->where('notification_type','TEST')->where('status','PENDING')
 ->where('created_at','<',$cutoff)->where('attempts',0)->whereNull('sent_at');
$rows=$query()->orderBy('id')->get(['id','staff_id','staff_device_id','notification_type','status','attempts','created_at','available_at']);
$count=$rows->count();
$remaining=DB::table($t)->where('notification_type','TEST')->where('status','PENDING')->count();
echo "FIX115_ARCHIVE_CANDIDATE_COUNT=$count\n";
echo "FIX115_ARCHIVE_ALL_PENDING_TEST_COUNT=$remaining\n";
echo "FIX115_ARCHIVE_MODE=".getenv('FIX115_ARCHIVE_MODE')."\n";
if (getenv('FIX115_ARCHIVE_MODE')==='preview') {
 echo "FIX115_ARCHIVE_PREVIEW_ONLY_NO_WRITES\n";exit(0);
}
if ($count!==9 || $remaining!==9) {
 echo "FIX115_ARCHIVE_ABORT=EXPECTED_EXACTLY_9_STALE_TEST_ROWS\n";exit(5);
}
$folder=storage_path('app/am-backups');
if (!is_dir($folder) && !mkdir($folder,0700,true) && !is_dir($folder)) {
 echo "FIX115_ARCHIVE_ABORT=BACKUP_DIRECTORY\n";exit(6);
}
$stamp=gmdate('Ymd_His');
$backup=$folder.'/fix115_historical_test_outbox_'.$stamp.'.json';
$json=json_encode(['created_at_utc'=>gmdate('c'),'reason'=>'FIX115 legacy TEST PENDING suppression before restoring scheduler','rows'=>$rows->toArray()],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
if ($json===false || file_put_contents($backup,$json,LOCK_EX)===false) {
 echo "FIX115_ARCHIVE_ABORT=BACKUP_SAVE_FAILED\n";exit(7);
}
chmod($backup,0600);
try {
 DB::transaction(function() use ($query,$rows) {
  $ids=$rows->pluck('id')->all();
  $updated=$query()->whereIn('id',$ids)->update([
   'status'=>'CANCELLED',
   'last_error'=>'FIX115 archived stale historical TEST before scheduler repair',
   'updated_at'=>CarbonImmutable::now('Asia/Kolkata'),
  ]);
  if ($updated!==9) throw new RuntimeException('Expected 9 archived rows, got '.$updated);
 });
} catch (Throwable $e) {
 echo 'FIX115_ARCHIVE_TRANSACTION_ROLLBACK='.get_class($e)."\n";exit(8);
}
echo "FIX115_ARCHIVED=9\n";
echo "FIX115_BACKUP_PATH=$backup\n";
echo "FIX115_ARCHIVE_COMPLETE_AUDIT_RETAINED\n";
PHP
