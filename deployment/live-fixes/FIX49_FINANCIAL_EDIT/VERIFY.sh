#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || exit 2
php -l "$APP_ROOT/app/Http/Controllers/Web/AdminWorkshopControlPageController.php" >/dev/null
php -l "$APP_ROOT/app/Http/Controllers/Web/AdminPaymentVoucherEditController.php" >/dev/null
php -l "$APP_ROOT/routes/web.php" >/dev/null
grep -q 'FIX48_WORKSHOP_DIRECT_CONTROLS' "$APP_ROOT/routes/web.php"
grep -q 'FIX48_WORKSHOP_DIRECT_CONTROLS' "$APP_ROOT/resources/views/job-cards/show.blade.php"
for f in job-amounts osl-index osl-edit vendor-database payment-voucher-register payment-voucher-edit; do test -s "$APP_ROOT/resources/views/workshop-controls/$f.blade.php";done
cd "$APP_ROOT"
php artisan optimize:clear >/dev/null
php artisan route:list --json | php -r '
$routes=json_decode(stream_get_contents(STDIN),true);
if(!is_array($routes))exit(3);
$needed=["erp.fix48.job-amounts","erp.fix48.job-line","erp.fix48.osl-index","erp.fix48.osl-edit","erp.fix48.osl-save","erp.fix48.vendor-database","erp.fix49.payment-register","erp.fix49.payment-edit","erp.fix49.payment-save"];
$found=array_column($routes,"name");
foreach($needed as $n){if(!in_array($n,$found,true)){fwrite(STDERR,"Missing route ".$n."\n");exit(4);}}
echo "9 FIX49 routes verified\n";
'
php -r 'require "vendor/autoload.php";$app=require "bootstrap/app.php";$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();if(!Illuminate\Support\Facades\Schema::hasTable("workshop_payment_edit_audits"))exit(7);echo "Payment voucher audit table PASS\n";'
php artisan view:cache >/dev/null
php artisan view:clear >/dev/null
echo 'FIX49 VERIFY PASS — still needs authenticated browser/save/reconciliation QA'