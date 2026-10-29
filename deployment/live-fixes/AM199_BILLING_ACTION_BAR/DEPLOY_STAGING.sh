#!/usr/bin/env bash
# AM199: staged Job Card view-only relocation. No billing route/data writes.
set -Eeuo pipefail
APP="${1:?Staging Laravel root required}"
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/show.blade.php"
python3 -m py_compile "$HERE/apply.py" "$HERE/verify.py"
python3 "$HERE/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BACKUP="$APP/storage/app/am-backups/AM199_WORKFLOW_PREAPPLY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BACKUP"
echo "AM199_WORKFLOW_BACKUP=$BACKUP"
rollback(){
 status="$?"
 trap - ERR
 cp -p "$BACKUP" "$VIEW" || true
 php artisan view:clear >/dev/null 2>&1 || true
 php artisan view:cache >/dev/null 2>&1 || true
 echo "AM199_STAGING_FAILURE_PREVIOUS_JC_VIEW_RESTORED"
 exit "$status"
}
trap rollback ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
QA="$(python3 "$HERE/verify.py" "$APP" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq AM199_QA_COMPLETE_NO_DATABASE_WRITES
CODE="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM199_STAGING_LOGIN_HTTP=$CODE"
test "$CODE" = 200
trap - ERR
echo "AM199_STAGING_AMOUNT_GST_IN_TOP_BAR_DEPLOY_PASS"
echo "AM199_BACKEND_FINANCIAL_AND_DATABASE_UNCHANGED"
