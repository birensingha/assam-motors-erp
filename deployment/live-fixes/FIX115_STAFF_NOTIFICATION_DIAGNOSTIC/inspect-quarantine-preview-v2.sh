#!/usr/bin/env bash
# FIX115-H3: isolate previous silent PHP failure; read-only, no notification sends.
set -euo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan && test -f vendor/autoload.php
echo "FIX115_H3_SAFE_PREVIEW_START"
php -d display_errors=1 -d display_startup_errors=1 -d log_errors=0 <<'PHP'
<?php
error_reporting(E_ALL);
$step='bootstrap';
try {
    require 'vendor/autoload.php';
    $app=require 'bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo "BOOTSTRAP_OK=yes\n";
    $step='schema-check';
    $exists=\Illuminate\Support\Facades\Schema::hasTable('notification_outbox');
    echo "OUTBOX_EXISTS=".($exists?'yes':'no')."\n";
    if (!$exists) exit(3);
    $step='status-column';
    $col=\Illuminate\Support\Facades\DB::selectOne("SHOW COLUMNS FROM `notification_outbox` LIKE 'status'");
    echo "STATUS_COLUMN_PRESENT=".($col?'yes':'no')."\n";
    if (!$col) exit(4);
    $colType=strtolower((string)($col->Type ?? 'unknown'));
    echo "STATUS_COLUMN_TYPE=".$colType."\n";
    echo "STATUS_CANCELLED_ALLOWED=".(str_contains($colType,'cancelled') || preg_match('/^(varchar|char|text)/',$colType)?'likely':'unknown')."\n";
    $step='counts';
    $q=\Illuminate\Support\Facades\DB::table('notification_outbox');
    $total=(clone $q)->where('notification_type','TEST')->where('status','PENDING')->count();
    $stale=(clone $q)->where('notification_type','TEST')->where('status','PENDING')
        ->where('created_at','<','2026-10-19 00:00:00')->where('attempts',0)->whereNull('sent_at')->count();
    echo "PENDING_TEST_TOTAL=$total\n";
    echo "STALE_TEST_CANDIDATES=$stale\n";
    echo "FIX115_H3_PREVIEW_COMPLETE_NO_WRITES\n";
} catch (\Throwable $e) {
    echo "FIX115_H3_ERROR_STEP=$step\n";
    echo "FIX115_H3_ERROR_CLASS=".get_class($e)."\n";
    echo "FIX115_H3_ERROR_CODE=".$e->getCode()."\n";
    exit(10);
}
PHP
