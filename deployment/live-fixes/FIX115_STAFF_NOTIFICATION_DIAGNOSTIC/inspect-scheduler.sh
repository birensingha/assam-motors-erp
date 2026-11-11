#!/usr/bin/env bash
# FIX115-C — Read-only notification scheduler/dispatch diagnosis.
# No cron edits, artisan dispatch, FCM sends, schema changes or DB mutations.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php

echo 'FIX115_C_NOTIFICATION_SCHEDULER_READ_ONLY'
echo '=== Laravel schedule (configuration only; not executed) ==='
php artisan schedule:list 2>&1 | head -65 || true

echo '=== Installed related artisan command names (not executed) ==='
php artisan list --raw 2>/dev/null | grep -Ei '(notification|reminder|fcm|firebase|schedule|outbox)' | head -65 || true

echo '=== Relevant schedule registrations (filename:line only) ==='
for file in routes/console.php app/Console/Kernel.php bootstrap/app.php; do
  if [ -f "$file" ]; then
    grep -nE '(Schedule::|->command\(|SyncStaffReminders|DispatchNotificationOutbox|notification|reminder|outbox|->every|->hourly|->daily)' "$file" |
       grep -vEi '(password|secret|private.?key|access.?token)' | head -60 |
       sed "s@^@$file:@"
  fi
done || true

echo '=== Command signatures (source identifiers only; no credentials) ==='
for file in app/Console/Commands/DispatchNotificationOutbox.php app/Console/Commands/SyncStaffReminders.php; do
  if [ -f "$file" ]; then
    grep -nE '(protected[[:space:]]+\$signature|protected[[:space:]]+\$description|function handle|function __construct)' "$file" |
      head -16 | sed "s@^@$file:@"
  fi
done || true

echo '=== Hostinger SSH account crontab (schedule presence only) ==='
if crontab -l 2>/dev/null | grep -Eq '(artisan[[:space:]]+schedule:run|artisan[[:space:]]+notifications:|artisan[[:space:]]+.*outbox|artisan[[:space:]]+.*reminder)'; then
  echo 'RELATED_CRONTAB_ENTRY=yes'
else
  echo 'RELATED_CRONTAB_ENTRY=no_or_external_scheduler'
fi

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== Outbox status / due date / assigned device diagnostics ===\n";
if (!Schema::hasTable('notification_outbox')) {
    echo "OUTBOX_TABLE=MISSING\n";
    exit(0);
}
$t = 'notification_outbox';
$cols=Schema::getColumnListing($t);
$has=static fn(string $name): bool => in_array($name,$cols,true);
echo "DB_UTC_NOW=" . gmdate('Y-m-d H:i:s') . "\n";
echo "DB_IST_NOW=" . \Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d H:i:s') . "\n";
echo "OUTBOX_TOTAL=" . DB::table($t)->count() . "\n";
if ($has('available_at')) {
  echo "AVAILABLE_AT_MIN=" . (DB::table($t)->min('available_at') ?: 'none') . "\n";
  echo "AVAILABLE_AT_MAX=" . (DB::table($t)->max('available_at') ?: 'none') . "\n";
  foreach (['UTC'=>gmdate('Y-m-d H:i:s'), 'IST'=>\Carbon\CarbonImmutable::now('Asia/Kolkata')->format('Y-m-d H:i:s')] as $zone=>$now) {
    echo "AVAILABLE_BY_$zone=" . DB::table($t)->where('available_at','<=',$now)->count() . "\n";
  }
}
if ($has('staff_device_id')) {
  echo "OUTBOX_DEVICE_LINK_NULL=" . DB::table($t)->whereNull('staff_device_id')->count() . "\n";
  echo "OUTBOX_DEVICE_LINK_NONNULL=" . DB::table($t)->whereNotNull('staff_device_id')->count() . "\n";
}
if (Schema::hasTable('staff_devices') && $has('staff_device_id')) {
  $devices=Schema::getColumnListing('staff_devices');
  $q=DB::table($t.' as o')->leftJoin('staff_devices as d','o.staff_device_id','=','d.id');
  echo "OUTBOX_DEVICE_LINK_UNMATCHED=" . (clone $q)->whereNotNull('o.staff_device_id')->whereNull('d.id')->count() . "\n";
  if (in_array('fcm_token',$devices,true)) {
    echo "OUTBOX_LINKED_WITH_FCM_TOKEN=" . (clone $q)
      ->whereNotNull('o.staff_device_id')->whereNotNull('d.fcm_token')->where('d.fcm_token','<>','')->count() . "\n";
  }
}
try {
  $m = new \App\Models\StaffReminderState();
  $table=$m->getTable();
  echo "REMINDER_MODEL_TABLE=" . preg_replace('/[^A-Za-z0-9_]/','',$table) . "\n";
  echo "REMINDER_MODEL_TABLE_EXISTS=" . (Schema::hasTable($table) ? 'yes' : 'no') . "\n";
} catch (Throwable $e) {
  echo "REMINDER_MODEL_LOOKUP_ERROR=" . get_class($e) . "\n";
}
echo "FIX115_C_DB_INSPECTION=READ_ONLY\n";
PHP
echo 'FIX115_C_AUDIT_COMPLETE_NO_CHANGES'
