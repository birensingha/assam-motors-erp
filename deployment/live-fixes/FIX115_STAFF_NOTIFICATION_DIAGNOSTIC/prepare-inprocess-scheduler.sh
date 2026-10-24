#!/usr/bin/env bash
# FIX115-H: prepare/update in-process Laravel scheduler, no notification execution.
# DRY-RUN DEFAULT. APPLY only after historical pending TEST rows are safely archived.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
MODE="${2:-dry-run}"
cd "$ROOT"
test -f artisan && test -f routes/console.php && test -f vendor/autoload.php
test "$MODE" = "dry-run" || test "$MODE" = "apply" || { echo 'MODE must be dry-run or apply'; exit 2; }
export FIX115_SCHEDULER_MODE="$MODE"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
if (!Schema::hasTable('notification_outbox')) {
  fwrite(STDERR,"FIX115_H_FAIL=OUTBOX_MISSING\n"); exit(3);
}
$old=DB::table('notification_outbox')->where('notification_type','TEST')->where('status','PENDING')->count();
echo "FIX115_H_PENDING_TEST=$old\n";
if (getenv('FIX115_SCHEDULER_MODE')==='apply' && $old>0) {
  fwrite(STDERR,"FIX115_H_BLOCKED=ARCHIVE_PENDING_TEST_FIRST\n"); exit(4);
}
PHP
python3 - <<'PY'
from pathlib import Path
import os,sys,subprocess,datetime
source=Path('routes/console.php')
original=source.read_text()
anchors=[
"Schedule::command('assam:staff-reminders')->everyMinute()->withoutOverlapping();",
"Schedule::command('assam:dispatch-notifications --limit=100')->everyMinute()->withoutOverlapping();",
"Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('02:20')->withoutOverlapping();",
]
replacements=[
"""Schedule::call(function (): void {
    $code = \\Illuminate\\Support\\Facades\\Artisan::call('assam:staff-reminders');
    if ($code !== 0) throw new \\RuntimeException('Staff reminder command failed with code '.$code);
})->name('assam-staff-reminders-inprocess')->everyMinute()->withoutOverlapping(10);""",
"""Schedule::call(function (): void {
    $code = \\Illuminate\\Support\\Facades\\Artisan::call('assam:dispatch-notifications', ['--limit' => 100]);
    if ($code !== 0) throw new \\RuntimeException('Notification dispatch command failed with code '.$code);
})->name('assam-notification-dispatch-inprocess')->everyMinute()->withoutOverlapping(10);""",
"""Schedule::call(function (): void {
    $code = \\Illuminate\\Support\\Facades\\Artisan::call('sanctum:prune-expired', ['--hours' => 24]);
    if ($code !== 0) throw new \\RuntimeException('Sanctum prune failed with code '.$code);
})->name('sanctum-prune-inprocess')->dailyAt('02:20')->withoutOverlapping(30);""",
]
if all(x not in original for x in anchors) and all(x in original for x in ("assam-staff-reminders-inprocess","assam-notification-dispatch-inprocess","sanctum-prune-inprocess")):
    print("FIX115_H_ALREADY_APPLIED")
    sys.exit(0)
for anchor in anchors:
    if original.count(anchor)!=1:
        print("FIX115_H_ABORT_UNEXPECTED_ANCHOR_COUNT",anchor)
        sys.exit(5)
modified=original
for a,b in zip(anchors,replacements):
    modified=modified.replace(a,b,1)
mode=os.environ["FIX115_SCHEDULER_MODE"]
if mode=="dry-run":
    print("FIX115_H_DRY_RUN_PASS_NO_WRITES")
    print("FIX115_H_REPLACEMENTS=3")
    print("FIX115_H_CLEARED_PROC_OPEN_DEPENDENCY_FOR_SCHEDULED_COMMANDS=PROPOSED")
    sys.exit(0)
stamp=datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
backup=source.with_name("console.php.fix115h."+stamp+".bak")
backup.write_text(original)
backup.chmod(0o600)
temp=source.with_name(".console.php.fix115h."+stamp+".tmp.php")
temp.write_text(modified)
temp.chmod(source.stat().st_mode&0o777)
check=subprocess.run(["php","-l",str(temp)],capture_output=True,text=True)
if check.returncode!=0:
    temp.unlink(missing_ok=True)
    print("FIX115_H_LINT_FAILURE_BACKUP="+str(backup))
    print(check.stdout+check.stderr)
    sys.exit(6)
os.replace(temp,source)
verify=subprocess.run(["php","artisan","schedule:list"],capture_output=True,text=True)
if verify.returncode!=0 or "inprocess" not in verify.stdout:
    source.write_text(original)
    print("FIX115_H_ROLLBACK=PERFORMED")
    print("FIX115_H_SCHEDULE_LIST_FAILED")
    sys.exit(7)
print("FIX115_H_APPLIED_BACKUP="+str(backup))
print("FIX115_H_SOURCE_AND_SCHEDULE_LIST_PASS")
print("FIX115_H_NO_NOTIFICATION_EXECUTED")
PY
