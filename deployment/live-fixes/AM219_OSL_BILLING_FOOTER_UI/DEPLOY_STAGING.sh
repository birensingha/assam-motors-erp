#!/usr/bin/env bash
# AM219 guarded STAGING-only one-CSS-rule UI change.
# No financial, supplier, job card data modifications.
set -Eeuo pipefail
APP="${1:?STAGING_APP_ROOT}"
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/show.blade.php"
python3 -m py_compile "$HERE/apply.py" "$HERE/verify.py"
python3 "$HERE/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BKP="$APP/storage/app/am-backups/AM219_EMERGENCY_ROLLBACK_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BKP"
echo AM219_STAGING_BACKUP_CREATED
rollback(){
  status="$?"
  trap - ERR
  cp -p "$BKP" "$VIEW" || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "AM219_STAGE_FAILED_RESTORED_PREVIOUS_JC_VIEW=$status"
  exit "$status"
}
trap rollback ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
QA="$(python3 "$HERE/verify.py" "$APP" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq AM219_READONLY_VIEW_QA_PASS
HTTP="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM219_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
trap - ERR
echo AM219_STAGING_ONLY_PURCHASE_REFERENCE_UI_HIDE_PASS
echo AM219_VENDOR_CUSTOMER_INVOICE_DATABASE_UNCHANGED
