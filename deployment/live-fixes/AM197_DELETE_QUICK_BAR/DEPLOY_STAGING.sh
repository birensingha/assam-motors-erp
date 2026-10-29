#!/usr/bin/env bash
# AM197 staging-only UI deployment with full restoration on any failure.
set -Eeuo pipefail
APP="${1:?Staging Laravel root required}"
cd "$APP"; test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/show.blade.php"
php -v | head -1
python3 -m py_compile "$HERE/apply.py" "$HERE/verify.py"
python3 "$HERE/apply.py" check "$APP"
mkdir -p "$APP/storage/app/am-backups"
BKP="$APP/storage/app/am-backups/AM197_WORKFLOW_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BKP"
echo "AM197_WORKFLOW_BACKUP=$BKP"
restore(){
  status="$?"
  trap - ERR
  cp -p "$BKP" "$VIEW" || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "AM197_ROLLBACK_PREVIOUS_JOB_CARD_VIEW_RESTORED"
  exit "$status"
}
trap restore ERR
python3 "$HERE/apply.py" apply "$APP"
php artisan view:clear
php artisan view:cache
# Insist on the exact success marker (not only process exit status).
QA="$(python3 "$HERE/verify.py" "$APP" 2>&1 || true)"
printf '%s\n' "$QA"
printf '%s\n' "$QA" | grep -Fxq AM197_ALL_CHECKS_PASS_READ_ONLY
# Smoke test login page only (no authenticated Job Card edit performed).
CODE="$(curl -sSL -o /dev/null -w '%{http_code}' --max-time 25 https://staging.assammotors.com/erp/login)"
echo "AM197_STAGING_LOGIN_HEALTH=$CODE"
test "$CODE" = 200
trap - ERR
echo AM197_STAGING_SINGLE_AUDITED_DELETE_BUTTON_VERIFIED
echo AM197_NO_DATABASE_ROUTES_CONTROLLER_FINANCE_WRITES
