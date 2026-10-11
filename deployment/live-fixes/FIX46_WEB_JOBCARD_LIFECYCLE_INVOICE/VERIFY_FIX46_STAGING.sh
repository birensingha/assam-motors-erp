#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-/home/u956497103/domains/assammotors.com/assam-erp-staging}"
php -l "$APP_ROOT/app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php" >/dev/null
php -l "$APP_ROOT/routes/web.php" >/dev/null
grep -q "FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE" "$APP_ROOT/routes/web.php"
grep -q "job-cards.lifecycle-panel" "$APP_ROOT/resources/views/job-cards/show.blade.php"
grep -q "FIX46_WEB_JOBCARD_LIFECYCLE_INVOICE" "$APP_ROOT/resources/views/job-cards/lifecycle-panel.blade.php"
grep -q "Workshop Tax Invoice" "$APP_ROOT/resources/views/invoices/print.blade.php"
cd "$APP_ROOT"
php artisan route:list | grep -q "erp.job-cards.lifecycle.work-complete"
php artisan route:list | grep -q "erp.job-cards.lifecycle.qc-result"
php artisan route:list | grep -q "erp.job-cards.lifecycle.ready"
php artisan route:list | grep -q "erp.job-cards.lifecycle.close"
php artisan route:list | grep -q "erp.job-cards.invoice.create"
php artisan route:list | grep -q "erp.invoices.show"
php artisan route:list | grep -q "erp.invoices.print"
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
