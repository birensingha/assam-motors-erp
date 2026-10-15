#!/usr/bin/env bash
set -euo pipefail
BASE="$(cd "$(dirname "$0")" && pwd)"
for f in "$BASE"/*.sh; do bash -n "$f"; done
for f in "$BASE"/*.php "$BASE"/scripts/*.php "$BASE"/payload/app/Http/Controllers/Web/*.php "$BASE"/payload/database/migrations/*.php; do php -l "$f" > /dev/null; done
php "$BASE/scripts/SELFTEST.php"
grep -Fq 'Historical Job Card GST basis is ambiguous' "$BASE/payload/app/Http/Controllers/Web/AdminWorkshopControlPageController.php"
grep -Fq 'Part Qty is stock-linked' "$BASE/payload/app/Http/Controllers/Web/AdminWorkshopControlPageController.php"
grep -Fq 'taxBasisUnverified' "$BASE/payload/resources/views/workshop-controls/job-amounts.blade.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/routes" "$TMP/resources/views/job-cards" "$TMP/resources/views/payments"
touch "$TMP/artisan"
cat > "$TMP/routes/web.php" <<'PHP'
<?php
use App\Http\Controllers\Web\AdminJobCardLifecyclePageController;
Route::middleware('auth')->group(function(){
    Route::get('/invoices/{invoice}/print', [AdminJobCardLifecyclePageController::class,'printInvoice'])->whereNumber('invoice')->name('erp.invoices.print');
});
PHP
printf "%s\n" "@include('job-cards.lifecycle-panel')" > "$TMP/resources/views/job-cards/show.blade.php"
printf "%s\n" "@extends('layouts.erp')" "@section('content')" "INDEX PAGE" "@endsection" > "$TMP/resources/views/payments/index.blade.php"
php "$BASE/PATCH_FIX48.php" "$TMP"
php -l "$TMP/routes/web.php" >/dev/null
for name in erp.fix48.job-amounts erp.fix48.osl-edit erp.fix48.vendor-database erp.fix49.payment-register erp.fix49.payment-edit erp.fix49.payment-save; do grep -q "$name" "$TMP/routes/web.php"; done
grep -Fq "Edit Past Payment Vouchers" "$TMP/resources/views/payments/index.blade.php"
grep -Fq "Edit Job Card Amount" "$TMP/resources/views/job-cards/show.blade.php"
echo 'FIX49 LOCAL SOURCE + MOCK ROUTE + EDIT BUTTON SMOKE PASS'