#!/usr/bin/env bash
# FIX115: read-only notification delivery and device registry diagnostics.
# No DB writes, no token values, no secrets.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan
test -f vendor/autoload.php

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "FIX115 STAFF NOTIFICATION READ-ONLY AUDIT\n";
echo "UTC_NOW=" . gmdate('Y-m-d H:i:s') . "\n";
echo "APP_TIMEZONE=" . config('app.timezone', 'unknown') . "\n";
echo "FIREBASE_PROJECT_CONFIGURED=" . ((bool)config('firebase.project_id') || (bool)getenv('FIREBASE_PROJECT_ID') ? 'yes' : 'no') . "\n";
$account = (string)config('firebase.service_account_path', '');
echo "FIREBASE_SERVICE_ACCOUNT_FILE=" . ($account !== '' && is_file($account) ? 'present' : 'missing_or_unset') . "\n";

$tables = [
  'staff_devices',
  'staff_push_devices',
  'staff_push_deliveries',
  'notification_outbox',
  'staff_reminder_states',
  'staff_action_alerts',
  'staff_action_alert_events',
];
foreach ($tables as $table) {
  try {
    if (!Schema::hasTable($table)) {
      echo "TABLE $table MISSING\n";
      continue;
    }
    $columns = Schema::getColumnListing($table);
    $has = static fn(string $c): bool => in_array($c, $columns, true);
    echo "TABLE $table PRESENT ROWS=" . DB::table($table)->count() . "\n";

    foreach (['active', 'status', 'delivery_status', 'notification_permission'] as $field) {
      if (!$has($field)) continue;
      $groups = DB::table($table)->select($field)->selectRaw('COUNT(*) AS total')
        ->groupBy($field)->limit(20)->get();
      foreach ($groups as $entry) {
        $value = $entry->{$field};
        $value = is_scalar($value) ? preg_replace('/[^A-Za-z0-9_.-]/', '', (string)$value) : 'null';
        echo "  $field=" . ($value !== '' ? $value : 'empty') . " count=" . (int)$entry->total . "\n";
      }
    }

    // Token presence is counted, never printed. Do not expose actual FCM credentials.
    foreach (['fcm_token', 'token_hash'] as $tokenCol) {
      if (!$has($tokenCol)) continue;
      $n = DB::table($table)->whereNotNull($tokenCol)->where($tokenCol, '<>', '')->count();
      echo "  $tokenCol nonempty_count=$n\n";
    }

    foreach (['sent_at', 'last_seen_at', 'last_notified_at_utc', 'registered_at_utc'] as $timeCol) {
      if (!$has($timeCol)) continue;
      $latest = DB::table($table)->max($timeCol);
      echo "  $timeCol latest=" . ($latest ?: 'none') . "\n";
    }

    // Aggregate by numeric Staff ID only; never show a phone number or a token.
    if ($table === 'staff_devices' && $has('staff_id')) {
      $staff = DB::table($table)->select('staff_id')->selectRaw('COUNT(*) AS device_rows')
        ->groupBy('staff_id')->orderBy('staff_id')->limit(100)->get();
      foreach ($staff as $entry) {
        echo "  staff_id=" . (int)$entry->staff_id . " device_rows=" . (int)$entry->device_rows . "\n";
      }
    }
  } catch (Throwable $e) {
    echo "TABLE $table INSPECT_ERROR=" . get_class($e) . "\n";
  }
}
echo "FIX115_AUDIT_COMPLETE=READ_ONLY\n";
PHP
