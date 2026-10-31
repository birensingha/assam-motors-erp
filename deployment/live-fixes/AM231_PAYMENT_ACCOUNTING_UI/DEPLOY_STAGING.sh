#!/usr/bin/env bash
# AM231 rollback-safe Staging Payment Voucher view/GET-only update.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT required}"
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/payments/create.blade.php"
CTRL="$APP/app/Http/Controllers/Web/AdminExpensePaymentPageController.php"
python3 -m py_compile "$HERE/apply.py"
php -l "$HERE/verify.php"
python3 "$HERE/apply.py" check "$APP"
BKP="$APP/storage/app/am-backups/AM231_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$BKP"
cp -p "$VIEW" "$BKP/create.blade.php"
cp -p "$CTRL" "$BKP/controller.php"
echo AM231_WORKFLOW_SOURCE_BACKUP_CREATED
restore(){
 status="$?"
 trap - ERR
 cp -p "$BKP/create.blade.php" "$VIEW" || true
 cp -p "$BKP/controller.php" "$CTRL" || true
 php artisan view:clear >/dev/null 2>&1 || true
 php artisan view:cache >/dev/null 2>&1 || true
 echo "AM231_STAGING_FAILURE_BOTH_SOURCE_FILES_RESTORED=$status"
 exit "$status"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php -l "$CTRL"
php artisan view:clear
php artisan view:cache
QA="$(php "$HERE/verify.php" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq "AM231_RUNTIME_QA_COMPLETE_NO_WRITES"
LOGIN="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM231_STAGING_LOGIN_HTTP=$LOGIN"
test "$LOGIN" = 200
trap - ERR
echo AM231_STAGING_ACCOUNTING_FORM_DEPLOY_PASS
echo AM231_NO_PAYMENT_POSTS_OR_LEDGER_DATABASE_WRITES
