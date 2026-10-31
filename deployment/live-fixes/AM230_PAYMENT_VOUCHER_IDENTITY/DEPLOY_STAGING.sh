#!/usr/bin/env bash
# AM230: guarded STAGING-only payment voucher UI & fail-closed category guard.
# Do NOT modify historic payment vouchers, staff/customer/vendor ledgers or DB.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT}"
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/payments/create.blade.php"
CTRL="$APP/app/Http/Controllers/Web/AdminExpensePaymentPageController.php"
python3 -m py_compile "$HERE/apply.py"
php -l "$HERE/verify.php"
python3 "$HERE/apply.py" check "$APP"
BACK="$APP/storage/app/am-backups/AM230_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$BACK"
cp -p "$VIEW" "$BACK/create.blade.php"
cp -p "$CTRL" "$BACK/controller.php"
echo AM230_BACKUP_VIEW_AND_CONTROLLER_PASS
restore(){
 code="$?"
 trap - ERR
 cp -p "$BACK/create.blade.php" "$VIEW" || true
 cp -p "$BACK/controller.php" "$CTRL" || true
 php artisan view:clear >/dev/null 2>&1 || true
 php artisan view:cache >/dev/null 2>&1 || true
 echo "AM230_STAGING_QA_FAILED_BOTH_FILES_RESTORED=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php -l "$CTRL"
php artisan view:clear
php artisan view:cache
OUTPUT="$(php "$HERE/verify.php" 2>&1 || true)"
printf '%s\n' "$OUTPUT"
printf '%s\n' "$OUTPUT" | grep -Fxq AM230_RUNTIME_QA_COMPLETE_NO_PAYMENT_POSTING
CODE="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM230_STAGING_LOGIN_HTTP=$CODE"
test "$CODE" = 200
trap - ERR
echo AM230_STAGING_PAYMENT_UI_AND_GUARD_VERIFIED
echo AM230_NO_PAYMENT_OR_LEDGER_DATA_WRITES
