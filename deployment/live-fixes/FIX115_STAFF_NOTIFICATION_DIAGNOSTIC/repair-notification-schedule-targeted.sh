#!/usr/bin/env bash
# FIX115-I: targeted in-process scheduler repair. Only two notification events.
# Dry-run by default. Apply requires proof of archived historical TEST rows.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
MODE="${2:-dry-run}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php && test -f routes/console.php || { echo 'FIX115_I_ABORT=LARAVEL_FILES_MISSING'; exit 2; }
case "$MODE" in dry-run|apply) ;; *) echo 'FIX115_I_ABORT=INVALID_MODE'; exit 2;; esac
export FIX115_I_MODE="$MODE"
php -d display_errors=1 <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
try {
 $pending=DB::table('notification_outbox')->where('notification_type','TEST')->where('status','PENDING')->count();
 $cancelled=DB::table('notification_outbox')->where('notification_type','TEST')->where('status','CANCELLED')->count();
 echo "FIX115_I_PENDING_TEST=$pending\n";
 echo "FIX115_I_CANCELLED_TEST=$cancelled\n";
 if (getenv('FIX115_I_MODE')==='apply') {
   if ($pending!==0 || $cancelled<9) {
      echo "FIX115_I_ABORT=OLD_TEST_ALERTS_NOT_QUARANTINED\n";exit(4);
   }
   $dir=storage_path('app/am-backups');
   $files=glob($dir.'/fix115_historical_test_outbox_*.json');
   $backupOk=false;
   foreach ($files?:[] as $p) {
      $j=json_decode((string)file_get_contents($p),true);
      if (!is_array($j) || !isset($j['rows']) || count($j['rows'])!==9) continue;
      $ids=array_map(static fn($r)=>(int)($r['id']??0),$j['rows']);
      if (count(array_unique($ids))!==9) continue;
      $actual=DB::table('notification_outbox')->whereIn('id',$ids)->where('notification_type','TEST')->where('status','CANCELLED')->count();
      if ($actual===9) { $backupOk=true; break; }
   }
   if (!$backupOk) {echo "FIX115_I_ABORT=VERIFIED_ARCHIVE_BACKUP_MISSING\n";exit(5);}
   echo "FIX115_I_ARCHIVE_BACKUP_VERIFIED=yes\n";
 }
} catch (Throwable $e) {
 echo "FIX115_I_PREFLIGHT_ERROR_CLASS=".get_class($e)."\n";exit(6);
}
PHP
python3 - <<'PY'
from pathlib import Path
from datetime import datetime,timezone
import os,subprocess,sys

source=Path('routes/console.php')
original=source.read_text()
replacement_pairs=[
("Schedule::command('assam:staff-reminders')->everyMinute()->withoutOverlapping();",
"""Schedule::call(function (): void {
    $exit = \\Illuminate\\Support\\Facades\\Artisan::call('assam:staff-reminders');
    if ($exit !== 0) throw new \\RuntimeException('Staff reminders failed: '.$exit);
})->name('assam-staff-reminders-inprocess')->everyMinute()->withoutOverlapping(10);"""),
("Schedule::command('assam:dispatch-notifications --limit=100')->everyMinute()->withoutOverlapping();",
"""Schedule::call(function (): void {
    $exit = \\Illuminate\\Support\\Facades\\Artisan::call('assam:dispatch-notifications', ['--limit'=>100]);
    if ($exit !== 0) throw new \\RuntimeException('Notification dispatch failed: '.$exit);
})->name('assam-notification-dispatch-inprocess')->everyMinute()->withoutOverlapping(10);"""),
]
if all(new in original for old,new in replacement_pairs):
 print("FIX115_I_ALREADY_APPLIED=yes")
 sys.exit(0)
if any(original.count(old)!=1 for old,new in replacement_pairs):
 print("FIX115_I_ABORT=UNEXPECTED_SCHEDULER_SOURCE")
 sys.exit(7)
modified=original
for old,new in replacement_pairs:
 modified=modified.replace(old,new,1)
print("FIX115_I_TARGET_EVENTS=2")
print("FIX115_I_SANCTUM_EVENT_UNCHANGED=yes")
if os.environ['FIX115_I_MODE']=='dry-run':
 print("FIX115_I_DRY_RUN_PASS_NO_WRITES")
 sys.exit(0)
backupDir=Path('storage/app/am-backups')
backupDir.mkdir(parents=True,exist_ok=True)
stamp=datetime.now(timezone.utc).strftime('%Y%m%dT%H%M%SZ')
backup=backupDir/('FIX115_I_routes_console_'+stamp+'.php.bak')
backup.write_text(original)
backup.chmod(0o600)
temp=source.with_name('.console_fix115i_'+stamp+'.tmp.php')
temp.write_text(modified)
temp.chmod(source.stat().st_mode & 0o777)
lint=subprocess.run(['php','-l',str(temp)],capture_output=True,text=True)
if lint.returncode!=0:
 temp.unlink(missing_ok=True)
 print("FIX115_I_ABORT=PHP_SYNTAX_FAILURE")
 print("FIX115_I_BACKUP="+str(backup))
 sys.exit(8)
os.replace(temp,source)
try:
 check=subprocess.run(['php','artisan','schedule:list'],capture_output=True,text=True)
 if check.returncode != 0 or 'assam-staff-reminders-inprocess' not in check.stdout or 'assam-notification-dispatch-inprocess' not in check.stdout:
  raise RuntimeError('schedule_list_failure')
except Exception as exc:
 source.write_text(original)
 print("FIX115_I_ROLLBACK_DONE=yes")
 print("FIX115_I_ABORT=POST_APPLY_SCHEDULE_CHECK")
 sys.exit(9)
print("FIX115_I_APPLIED=yes")
print("FIX115_I_BACKUP="+str(backup))
print("FIX115_I_SYNTAX_AND_SCHEDULE_PASS=yes")
print("FIX115_I_NO_NOTIFICATIONS_SENT=yes")
PY
