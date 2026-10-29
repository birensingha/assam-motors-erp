#!/usr/bin/env bash
# AM198 staging-only Job Card SHOW view reposition with full rollback.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT required}"
cd "$APP";test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/show.blade.php"
python3 -m py_compile "$HERE/apply.py" "$HERE/verify.py"
python3 "$HERE/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BKP="$APP/storage/app/am-backups/AM198_WORKFLOW_PREAPPLY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BKP"
echo "AM198_WORKFLOW_BACKUP=$BKP"
restore(){
  status="$?"
  trap - ERR
  cp -p "$BKP" "$VIEW" || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "AM198_ROLLBACK_PREVIOUS_SHOW_VIEW_RESTORED"
  exit "$status"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
QA="$(python3 "$HERE/verify.py" "$APP" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq "AM198_QA_COMPLETE_NO_DATA_WRITES"
CODE="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM198_STAGING_LOGIN_HEALTH=$CODE"
test "$CODE" = 200
trap - ERR
echo "AM198_STAGING_ACTION_BAR_REORDER_VERIFIED"
echo "AM198_NO_BACKEND_OR_ATTENDANCE_OR_FINANCE_MUTATIONS"
