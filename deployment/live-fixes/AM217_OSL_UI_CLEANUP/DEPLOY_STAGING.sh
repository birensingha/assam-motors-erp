#!/usr/bin/env bash
# AM217 staging-only view patch; fail closed and restore prior view on QA failure.
set -Eeuo pipefail
APP="${1:?STAGING_ROOT required}"
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/show.blade.php"
python3 -m py_compile "$HERE/apply.py" "$HERE/verify.py"
php -l "$HERE/verify.php"
python3 "$HERE/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BACKUP="$APP/storage/app/am-backups/AM217_WORKFLOW_PREAPPLY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BACKUP"
echo "AM217_WORKFLOW_BACKUP_CREATED"
restore(){
 code="$?"
 trap - ERR
 cp -p "$BACKUP" "$VIEW" || true
 php artisan view:clear >/dev/null 2>&1 || true
 php artisan view:cache >/dev/null 2>&1 || true
 echo "AM217_FAILED_STAGING_VIEW_SAFE_ROLLBACK=$code"
 exit "$code"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
QA="$(python3 "$HERE/verify.py" "$APP" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq AM217_QA_COMPLETE_NO_DATABASE_WRITES
DBQA="$(php "$HERE/verify.php" 2>&1 || true)"
printf '%s\n' "$DBQA"
printf '%s\n' "$DBQA" | grep -Fxq AM217_DB_CHECK_COMPLETE_NO_WRITES
HTTP="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM217_STAGING_LOGIN_HTTP=$HTTP"
test "$HTTP" = 200
trap - ERR
echo "AM217_STAGING_OSL_UI_DEPLOY_AND_QA_PASS"
echo "AM217_NO_SUPPLIER_CUSTOMER_INVOICE_DB_WRITES"
