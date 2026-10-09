#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
echo "ASSAM MOTORS FIX26 — READ ONLY VERIFY"
grep -q "job-filter" resources/views/purchases/create.blade.php
grep -q "wireJobFilter" public/js/purchase-entry.js
grep -q "normalizeLookup" public/js/purchase-entry.js
grep -q "preg_replace('/\[^A-Z0-9\]/'" app/Services/PurchaseService.php
php -r 'require "vendor/autoload.php"; $app=require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $s=app(App\Services\PurchaseService::class); $r=$s->searchJobs("",5); echo "PASS searchJobs count=".count($r).PHP_EOL;'
echo "VERIFY RESULT: PASS"
