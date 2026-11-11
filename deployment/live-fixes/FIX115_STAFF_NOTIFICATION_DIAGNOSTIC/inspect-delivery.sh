#!/usr/bin/env bash
# FIX115-B - staging notification pipeline deep diagnosis (READ ONLY).
# No writes, no secret/token values, no push sends, no migrations.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php

php <<'PHP'
<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function inspectTable(string $table): array {
    if (!Schema::hasTable($table)) {
        echo "TABLE $table MISSING\n";
        return [];
    }
    $cols = Schema::getColumnListing($table);
    echo "TABLE $table COLUMNS=" . implode(',', $cols) . "\n";
    return $cols;
}
function hasAll(array $cols, array $wanted): bool {
    return count(array_diff($wanted, $cols)) === 0;
}
function statusGroups(string $table, array $cols, array $fields): void {
    if (!hasAll($cols, $fields)) return;
    $q = DB::table($table)->select($fields)->selectRaw('COUNT(*) AS total')
        ->groupBy($fields)->orderByDesc('total')->limit(40);
    foreach ($q->get() as $row) {
        $parts = [];
        foreach ($fields as $f) {
            $v = $row->{$f};
            $parts[] = $f . '=' . preg_replace('/[^A-Za-z0-9_.-]/', '', (string)($v ?? 'null'));
        }
        echo "  GROUP " . implode(' ', $parts) . " count=" . (int)$row->total . "\n";
    }
}
echo "FIX115_B_NOTIFICATION_PIPELINE_READ_ONLY\n";
echo "APP_TIMEZONE=" . config('app.timezone', 'unknown') . "\n";
echo "QUEUE_DEFAULT=" . config('queue.default', 'unknown') . "\n";

try {
    $dev = inspectTable('staff_devices');
    if (hasAll($dev, ['staff_id', 'fcm_token', 'notification_permission'])) {
        echo "DEVICE_BY_STAFF:\n";
        $q = DB::table('staff_devices')->select('staff_id')
            ->selectRaw('COUNT(*) AS device_count')
            ->selectRaw("SUM(CASE WHEN fcm_token IS NOT NULL AND fcm_token <> '' THEN 1 ELSE 0 END) AS with_token")
            ->selectRaw('SUM(CASE WHEN notification_permission = 1 THEN 1 ELSE 0 END) AS with_permission')
            ->groupBy('staff_id')->orderBy('staff_id')->limit(100);
        foreach ($q->get() as $row) {
            echo '  staff_id=' . (int)$row->staff_id .
                ' device_rows=' . (int)$row->device_count .
                ' token_rows=' . (int)$row->with_token .
                ' permission_rows=' . (int)$row->with_permission . "\n";
        }
    }
    if (in_array('app_version', $dev, true)) {
        statusGroups('staff_devices', $dev, ['app_version']);
    }

    $out = inspectTable('notification_outbox');
    statusGroups('notification_outbox', $out, ['status']);
    statusGroups('notification_outbox', $out, ['notification_type','status']);
    if (hasAll($out, ['staff_id','status'])) {
        statusGroups('notification_outbox', $out, ['staff_id','status']);
    }

    foreach (['sent_at','created_at','updated_at','last_attempt_at','next_attempt_at'] as $field) {
        if (in_array($field, $out, true)) {
            $v = DB::table('notification_outbox')->max($field);
            echo "OUTBOX_MAX_$field=" . ($v ?: 'none') . "\n";
        }
    }

    foreach (['attempts','attempt_count','retry_count','failed_attempts'] as $field) {
        if (in_array($field, $out, true)) {
            $v = DB::table('notification_outbox')->max($field);
            echo "OUTBOX_MAX_$field=" . ($v === null ? 'none' : $v) . "\n";
        }
    }

    foreach (['last_error','error_message','provider_error','failure_reason'] as $field) {
        if (in_array($field, $out, true)) {
            $n = DB::table('notification_outbox')->whereNotNull($field)
                ->where($field, '<>', '')->count();
            echo "OUTBOX_$field" . "_NONEMPTY_COUNT=$n\n";
        }
    }
    foreach (['jobs', 'failed_jobs', 'job_batches'] as $table) {
        if (!Schema::hasTable($table)) {
            echo "QUEUE_TABLE_$table=MISSING\n";
            continue;
        }
        echo "QUEUE_TABLE_$table" . "_COUNT=" . DB::table($table)->count() . "\n";
    }
} catch (Throwable $e) {
    echo "AUDIT_ERROR_CLASS=" . get_class($e) . "\n";
}
echo "FIX115_B_SQL_AUDIT_COMPLETE=READ_ONLY\n";
PHP

echo 'SOURCE_PATHS_RELEVANT_TO_PUSH:'
find app -type f -name '*.php' 2>/dev/null | grep -Ei '(push|fcm|firebase|notification|reminder|device|schedule)' | head -40 || true
echo 'SOURCE_FILES_MATCHING_FCM_OUTBOX_REFERENCES:'
grep -RIl --include='*.php' -E '(NotificationOutbox|notification_outbox|fcm_token)' app 2>/dev/null | head -40 || true
echo 'SCHEDULE_RUN_CRONTAB_ENTRY_EXISTS='
if crontab -l 2>/dev/null | grep -Eq 'artisan[[:space:]]+schedule:run'; then
  echo 'yes'
else
  echo 'no_or_external_scheduler'
fi
echo 'FIX115_B_AUDIT_COMPLETE_NO_CHANGES'
