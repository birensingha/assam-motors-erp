#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-}"
if [[ -z "$APP_ROOT" || ! -f "$APP_ROOT/artisan" ]]; then
  echo "Usage: bash INSPECT.sh /path/to/laravel/app"
  exit 2
fi

cd "$APP_ROOT"

echo "ASSAM MOTORS FIX26 — PURCHASE SEARCH / ALLOCATION INSPECT (READ ONLY)"
echo "APP_ROOT: $APP_ROOT"
echo

echo "== ROUTES =="
php artisan route:list --path=erp/purchases 2>&1 | grep -E 'parts/search|jobs/search|purchases/create|purchases/osl' || true
echo

echo "== PART SEARCH MARKERS =="
grep -nE -A34 -B4 'function partSearch|part_name.*like|part_code.*like'   app/Http/Controllers/Web/AdminPurchasePageController.php 2>/dev/null | head -n 150 || true
echo

echo "== JOB SEARCH SERVICE =="
grep -nE -A44 -B4 'function searchJobs' app/Services/PurchaseService.php 2>/dev/null | head -n 100 || true
echo

echo "== PURCHASE AGAINST UI =="
grep -nE -A8 -B5 'job-select|job-search|allocation_type|JC / Vehicle|Part Search'   resources/views/purchases/create.blade.php 2>/dev/null | head -n 180 || true
echo

echo "== PURCHASE ENTRY JS JOB/PART MARKERS =="
grep -nE -A7 -B3 'fetchParts|fetchJobs|job-select|job-search|No Job Card|Part search'   public/js/purchase-entry.js 2>/dev/null | head -n 220 || true
echo

echo "== LIVE SEARCH PROBE =="
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$s=app(App\Services\PurchaseService::class);
$all=$s->searchJobs("",200);
echo "job_count_sample=".count($all).PHP_EOL;
foreach (array_slice($all,0,5) as $j) {
  echo ($j["job_no"]??"")." | ".($j["vehicle_reg_no"]??"")." | ".($j["customer_name"]??"").PHP_EOL;
}
' 2>&1 || true
echo

echo "== FILE HASHES =="
sha256sum  app/Http/Controllers/Web/AdminPurchasePageController.php  app/Services/PurchaseService.php  resources/views/purchases/create.blade.php  public/js/purchase-entry.js  routes/web.php

echo
echo "INSPECT COMPLETE — READ ONLY"
