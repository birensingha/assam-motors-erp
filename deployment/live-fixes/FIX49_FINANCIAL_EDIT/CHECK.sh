#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
DIR="$(cd "$(dirname "$0")" && pwd)"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash CHECK.sh /path/to/laravel/staging"; exit 2; }
for f in routes/web.php resources/views/job-cards/show.blade.php app/Models/ErpAuthUser.php \
 app/Http/Controllers/Web/AdminJobCardLifecyclePageController.php resources/views/payments/index.blade.php app/Services/ExpensePaymentService.php app/Services/VendorService.php; do
 test -f "$APP_ROOT/$f" || { echo "NO-GO: Missing $f"; exit 3; }
done
[[ ! -d "$APP_ROOT/resources/views/workshop-controls" ]] || { echo "NO-GO: existing workshop-controls views; do not overwrite"; exit 4; }
[[ ! -f "$APP_ROOT/app/Http/Controllers/Web/AdminWorkshopControlPageController.php" ]] || { echo "NO-GO: existing controller; do not overwrite"; exit 4; }
php -l "$DIR/PATCH_FIX48.php" >/dev/null
php -l "$DIR/PREFLIGHT_PAYMENTS.php" >/dev/null
php -l "$DIR/payload/app/Http/Controllers/Web/AdminPaymentVoucherEditController.php" >/dev/null
php -l "$DIR/payload/database/migrations/2026_10_15_220000_create_workshop_payment_edit_audits.php" >/dev/null
php -l "$DIR/payload/app/Http/Controllers/Web/AdminWorkshopControlPageController.php" >/dev/null
if grep -q 'FIX48_WORKSHOP_DIRECT_CONTROLS' "$APP_ROOT/routes/web.php";then echo "ALREADY INSTALLED"; exit 5;fi
grep -Fq 'erp.invoices.print' "$APP_ROOT/routes/web.php" || { echo 'NO-GO: FIX46 not installed'; exit 4; }
grep -Fq "@include('job-cards.lifecycle-panel')" "$APP_ROOT/resources/views/job-cards/show.blade.php" || { echo 'NO-GO: Missing FIX46 view anchor'; exit 4; }
cd "$APP_ROOT"
php "$DIR/PREFLIGHT_PAYMENTS.php"
php <<'PHP'
<?php
require 'vendor/autoload.php';
$app=require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
foreach(['job_cards','job_card_parts','job_card_labour','invoices','customer_ledger','job_card_change_audits','workshop_purchases','workshop_vendors','workshop_payment_vouchers'] as $t){
 if(!Illuminate\Support\Facades\Schema::hasTable($t)){fwrite(STDERR,"NO-GO: Missing $t\n");exit(4); }
}
echo "FIX48 READ-ONLY TABLE PREFLIGHT PASS\n";
PHP
echo "FIX49 CHECK GO"