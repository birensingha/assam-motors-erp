#!/usr/bin/env bash
# FIX115-F: read-only Eloquent eligibility and Laravel schedule mutex check.
# Does NOT execute scheduled commands, send push, or clear scheduler locks.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Console\Scheduling\Schedule;
use Carbon\CarbonImmutable;
use App\Models\NotificationOutbox;

echo "FIX115_F_MUTEX_AND_SELECTION_READ_ONLY\n";
$now=CarbonImmutable::now('Asia/Kolkata');
echo "NOW_IST=".$now->format('Y-m-d H:i:s')."\n";
echo "CACHE_DRIVER=".config('cache.default','unknown')."\n";
echo "QUEUE_DRIVER=".config('queue.default','unknown')."\n";

try {
  $raw=DB::table('notification_outbox')
     ->where('status','PENDING')->where('available_at','<=',$now)->count();
  $eloquent=NotificationOutbox::query()
     ->where('status','PENDING')->where('available_at','<=',$now)->count();
  $selected=NotificationOutbox::query()
     ->where('status','PENDING')->where('available_at','<=',$now)
     ->orderBy('id')->limit(100)->get()->count();
  echo "RAW_ELIGIBLE=$raw\n";
  echo "ELOQUENT_ELIGIBLE=$eloquent\n";
  echo "COMMAND_SELECTION_COUNT=$selected\n";
} catch (Throwable $e) {
  echo "QUERY_ERROR_CLASS=".get_class($e)."\n";
}

try {
  $events=app(Schedule::class)->events();
  echo "SCHEDULE_EVENTS=".count($events)."\n";
  foreach ($events as $event) {
    $command=(string)($event->command??'');
    if (!str_contains($command,'assam:dispatch-notifications') &&
        !str_contains($command,'assam:staff-reminders')) continue;
    $kind=str_contains($command,'assam:dispatch-notifications') ? 'DISPATCH' : 'REMINDERS';
    $overlap=(bool)($event->withoutOverlapping??false);
    echo "EVENT=$kind WITHOUT_OVERLAPPING=".($overlap?'yes':'no')."\n";
    if ($overlap) {
      $lockName=$event->mutexName();
      echo "EVENT=$kind MUTEX_PRESENT=".(Cache::has($lockName)?'yes':'no')."\n";
      echo "EVENT=$kind MUTEX_TTL_MINUTES=".(int)($event->expiresAt??0)."\n";
    }
  }
} catch (Throwable $e) {
  echo "SCHEDULE_INSPECT_ERROR_CLASS=".get_class($e)."\n";
}
echo "FIX115_F_AUDIT_COMPLETE_NO_CHANGES\n";
PHP
