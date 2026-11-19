#!/usr/bin/env bash
# AM277 staging-only guarded context metadata. No invoice/jobcard/stock writes.
set -Eeuo pipefail
ROOT="$1"
HERE="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"
test -f artisan
python3 -m py_compile "$HERE/apply.py"
python3 "$HERE/apply.py" check "$ROOT"
ROLLBACK=1
rollback(){
 code="$?"
 if [ "$code" != 0 ] && [ "$ROLLBACK" = 1 ];then
  B="$ROOT/storage/app/am-backups/AM277_PARTS_CONTEXT_ONLY"
  if [ -d "$B" ];then
   for row in \
     "purchase_controller:app/Http/Controllers/Web/AdminPurchasePageController.php" \
     "purchase_create_js:public/js/purchase-entry.js" \
     "purchase_edit_js:public/js/purchase-edit.js" \
     "estimate_controller:app/Http/Controllers/Web/AdminEstimatePageController.php" \
     "estimate_view:resources/views/estimates/create.blade.php" \
     "estimate_js:public/js/estimate-entry.js" \
     "counter_controller:app/Http/Controllers/Web/CounterPartsInvoiceController.php" \
     "counter_view:resources/views/counter-sales/create.blade.php"
   do
    key="$(printf '%s' "$row" | cut -d: -f1)"
    rel="$(printf '%s' "$row" | cut -d: -f2-)"
    test -f "$B/$key.before" && cp -p "$B/$key.before" "$ROOT/$rel" || true
   done
  fi
  php artisan view:clear >/dev/null 2>&1 || true
  echo AM277_ROLLBACK_AFTER_FAILED_VERIFICATION
 fi
 exit "$code"
}
trap rollback EXIT
python3 "$HERE/apply.py" apply "$ROOT"
php artisan view:clear
php artisan view:cache
grep -q AM277_PARTS_CONTEXT_ONLY app/Http/Controllers/Web/AdminPurchasePageController.php
grep -q am277-part-vehicle public/js/purchase-entry.js
grep -q am277-part-vehicle public/js/purchase-edit.js
grep -q am277-part-vehicle public/js/estimate-entry.js
grep -q am277-counter-parts-context resources/views/counter-sales/create.blade.php
php -r '
require "vendor/autoload.php";$app=require "bootstrap/app.php";
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$rows=\Illuminate\Support\Facades\DB::table("parts_master")
  ->where(function($q){$q->whereNotNull("vehicle_make")->where("vehicle_make","<>","")
  ->orWhere(function($w){$w->whereNotNull("vehicle_model")->where("vehicle_model","<>","");});})->count();
echo "AM277_PART_MASTER_VEHICLE_LINKS=".$rows."\n";
'
CODE="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
test "$CODE" = 200
ROLLBACK=0;trap - EXIT
echo AM277_STAGING_CONTEXT_ONLY_PART_CHOICES_DEPLOY_PASS
echo AM277_NO_PART_DESCRIPTION_OR_FINANCIAL_DATA_WRITES
