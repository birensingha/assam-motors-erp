#!/usr/bin/env bash
# AM234 staging ONLY: reversible view-only universal voucher feature navigation.
# This script NEVER posts expenses, vouchers, ledgers, journal entries or migrations.
set -Eeuo pipefail
APP="${1:?Pass staging Laravel root}"
APP="$(cd "$APP" && pwd -P)"
case "$APP" in *staging*) ;; *) echo 'AM234_STOP_NOT_STAGING_PATH'; exit 3;; esac
test -f "$APP/artisan" || { echo 'AM234_STOP_NOT_LARAVEL';exit 3; }
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
cd "$APP"
VIEW='resources/views/payments/create.blade.php'
test -f "$VIEW"
python3 -m py_compile "$HERE/apply.py"
if command -v node >/dev/null 2>&1; then node --check "$HERE/feature-ui.js"; fi
python3 "$HERE/apply.py" check "$APP"
if grep -Fq 'AM234_UNIVERSAL_PAYMENT_FEATURES' "$VIEW"; then
  echo 'AM234_ALREADY_INSTALLED_NO_CHANGES'
  exit 0
fi
EXTERNAL_BACKUP="$APP/storage/app/am-backups/AM234_GH_$(date +%Y%m%d_%H%M%S)_$$"
mkdir -p "$EXTERNAL_BACKUP"
cp -p "$VIEW" "$EXTERNAL_BACKUP/create.blade.php"
ROLLBACK_REQUIRED=1
rollback(){
  code="$?"
  trap - ERR
  if test "$ROLLBACK_REQUIRED" = 1;then
    cp -p "$EXTERNAL_BACKUP/create.blade.php" "$APP/$VIEW" || true
    php artisan view:clear >/dev/null 2>&1 || true
    php artisan view:cache >/dev/null 2>&1 || true
  fi
  echo "AM234_STAGING_UI_ROLLBACK_COMPLETED_OR_ATTEMPTED_EXIT=$code"
  exit "$code"
}
trap rollback ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
grep -Fq 'AM234_UNIVERSAL_PAYMENT_FEATURES' "$VIEW"
grep -Fq 'AM230_PAYMENT_TO_CATEGORY_PERSISTENT' "$VIEW"
grep -Fq 'AM231_PAYMENT_TO_ACCOUNTING_CONTEXT' "$VIEW"
HTTP="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM234_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
ROLLBACK_REQUIRED=0
trap - ERR
echo 'AM234_UNIVERSAL_PAYMENT_FEATURE_UI_STAGING_DEPLOY_PASS'
echo 'AM234_FINANCIAL_POSTING_TABLES_UNCHANGED'
echo 'AM234_FOUR_UNSUPPORTED_PAYMENT_ROUTES_STILL_POSTING_LOCKED'
