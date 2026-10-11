#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
PKG_DIR="$(cd "$(dirname "$0")" && pwd)"
test -f "$APP_ROOT/artisan"
test -f "$APP_ROOT/routes/web.php"
test -f "$APP_ROOT/resources/views/job-cards/show.blade.php"
test -f "$APP_ROOT/app/Models/JobCard.php"
php -l "$PKG_DIR/payload/AdminJobCardLifecyclePageController.php" >/dev/null
php -l "$PKG_DIR/PATCH_FIX46.php" >/dev/null
php -l "$PKG_DIR/MIGRATE_FIX46.php" >/dev/null
grep -q "erp.job-cards.show" "$APP_ROOT/routes/web.php"
grep -q "latestEstimateStatus" "$APP_ROOT/resources/views/job-cards/show.blade.php"
cd "$APP_ROOT"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['job_cards','job_card_parts','job_card_labour','invoices','customer_ledger','job_card_change_audits'] as $t){
  if(!Illuminate\Support\Facades\Schema::hasTable($t)){fwrite(STDERR,"Missing table $t\n");exit(20);}
}
echo "FIX46 prerequisites: PASS\n";
PHP
echo "FIX46 CHECK: PASS"
