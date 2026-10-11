#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
php -l "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php" >/dev/null
php -l "$APP_ROOT/routes/web.php" >/dev/null
grep -q "FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE" "$APP_ROOT/routes/web.php"
grep -q "job-cards.lifecycle-panel" "$APP_ROOT/resources/views/job-cards/show.blade.php"
grep -q "FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE" "$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"
grep -q "Workshop Tax Invoice" "$APP_ROOT/resources/views/invoices/print.blade.php"
! grep -R -q "CarbonCarbon" "$APP_ROOT/resources/views/invoices"
grep -q "date('d-m-Y',strtotime" "$APP_ROOT/resources/views/invoices/show.blade.php"
grep -q "date('d-m-Y',strtotime" "$APP_ROOT/resources/views/invoices/print.blade.php"
cd "$APP_ROOT"
php artisan route:list --json > /tmp/fix46_routes.json
grep -q '"name":"erp.job-cards.lifecycle.work-complete"' /tmp/fix46_routes.json
grep -q '"name":"erp.job-cards.lifecycle.qc-result"' /tmp/fix46_routes.json
grep -q '"name":"erp.job-cards.lifecycle.ready"' /tmp/fix46_routes.json
grep -q '"name":"erp.job-cards.lifecycle.close"' /tmp/fix46_routes.json
grep -q '"name":"erp.job-cards.invoice.create"' /tmp/fix46_routes.json
grep -q '"name":"erp.invoices.show"' /tmp/fix46_routes.json
grep -q '"name":"erp.invoices.print"' /tmp/fix46_routes.json
rm -f /tmp/fix46_routes.json
php artisan view:clear >/dev/null
php artisan view:cache >/dev/null
php artisan view:clear >/dev/null
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(!Illuminate\Support\Facades\Schema::hasTable('workshop_qc_checks')){fwrite(STDERR,"Missing workshop_qc_checks\n");exit(21);}
echo "FIX46 schema verify: PASS\n";
PHP
echo "FIX46 VERIFY: PASS"
