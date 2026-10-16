#!/usr/bin/env bash
# Safe metadata/source-signature inspection: no PII, passwords or full rows.
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
cd "$APP_ROOT"
php artisan route:list 2>/dev/null | grep -Ei 'erp/(job-cards|purchases|vendors)|erp.fix48' | head -90 || true
for f in app/Http/Controllers/Web/AdminJobCardPageController.php app/Http/Controllers/Web/AdminPurchasePageController.php app/Services/PurchaseService.php app/Services/VendorService.php;do
 echo "-- $f --"
 if [[ -f "$f" ]];then grep -nE 'function (edit|update|saveOsl|saveOslBatch|save|ledger|recalculate)' "$f" | head -40 || true;sha256sum "$f";fi
done
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['job_cards','job_card_parts','job_card_labour','workshop_purchases','workshop_purchase_batches','purchase_audit_logs','job_card_change_audits','workshop_payment_vouchers','workshop_payment_edit_audits','workshop_vendors','workshop_vendor_payments','customer_ledger','invoices'] as $table){
 if(!Illuminate\Support\Facades\Schema::hasTable($table)){echo "$table=MISSING\n";continue;}
 echo "$table: ".implode(', ',Illuminate\Support\Facades\Schema::getColumnListing($table))."\n";
}
PHP