#!/usr/bin/env bash
# AM440 — STAGING-only native Finance API discovery. No SQL data reads,
# migrations, deployment, source patches, or financial writes.
set -Eeuo pipefail
ROOT="${1:?Pass existing STAGING Laravel root}"
cd "$ROOT"
test -f artisan
test -f vendor/autoload.php
test -f bootstrap/app.php
echo 'AM440_STAGING_FINANCE_AUDIT_START'
php <<'PHP'
<?php
declare(strict_types=1);
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;

echo "AM440_BACKEND_FRAMEWORK=Laravel\n";
foreach ([
 'staff', 'vendors', 'workshop_vendors', 'workshop_expenses',
 'workshop_payment_vouchers', 'workshop_purchase_bills',
 'workshop_purchases', 'purchase_bills', 'purchases',
 'parts', 'workshop_parts', 'labour_master', 'rot_master',
 'osl_master', 'outside_labour_master', 'job_cards',
 'workshop_job_cards', 'workshop_estimates'
] as $table) {
 try {
   if (!Schema::hasTable($table)) {
     echo "AM440_TABLE_".strtoupper($table)."=ABSENT\n";
     continue;
   }
   // Schema metadata only. Never query row values, PII, prices or financial totals.
   $columns=Schema::getColumnListing($table);
   $safe=array_filter($columns, static fn($name) =>
     preg_match('/^(id|name|code|staff_id|vendor_id|job_card_id|payee_type|amount|total|grand_total|outstanding|status|date|created_at|updated_at|reference|request_key|is_deleted|deleted_at|company_id|expense_category|transaction_type|.*_id|.*_amount|.*_total|.*_no)$/i', $name) === 1);
   echo "AM440_TABLE_".strtoupper($table)."=PRESENT;FINANCE_FIELDS=".
     implode(',', array_values($safe))."\n";
 } catch (Throwable $e) {
   echo "AM440_TABLE_".strtoupper($table)."=SCHEMA_INTROSPECTION_UNAVAILABLE\n";
 }
}
try {
 $paths=[];
 foreach (Route::getRoutes() as $route) {
   $uri=$route->uri();
   if (!str_contains($uri, 'admin/mobile') &&
       !str_contains($uri, 'api/v1') &&
       !str_contains($uri, 'expenses') &&
       !str_contains($uri, 'payment-vouchers')) continue;
   // Print route NAME/PATH only, NEVER request query strings or credentials.
   if (!preg_match('/(finance|purchase|expense|payment|vendor|estimate|master|ledger|osl|mobile)/i', $uri)) continue;
   $paths[]=implode('|', array_filter($route->methods(),
      static fn($method) => $method !== 'HEAD')).' '.
      $uri.' NAME:'.($route->getName() ?: 'UNNAMED');
 }
 sort($paths, SORT_STRING);
 echo 'AM440_MATCHED_ROUTE_COUNT='.count($paths)."\n";
 foreach (array_slice($paths,0,110) as $path) {
   echo 'AM440_ROUTE='.str_replace(["\n","\r"],' ', $path)."\n";
 }
 echo "AM440_ROUTE_TRUNCATED=".((count($paths)>110)?'YES':'NO')."\n";
} catch (Throwable $e) {
 echo "AM440_ROUTE_INSPECTION_UNAVAILABLE\n";
}
echo "AM440_READONLY_SCHEMA_AND_ROUTE_DISCOVERY_FINISHED\n";
PHP
echo 'AM440_AUDIT_NO_WRITES_COMPLETE'
