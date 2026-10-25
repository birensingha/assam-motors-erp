#!/usr/bin/env bash
# AM126 - safe GitHub Actions STAGING-only view deployment + server render QA.
set -Eeuo pipefail
APP="${1:?STAGING_APP_ROOT required}"
cd "$APP"
test -f artisan
SOURCE="resources/views/dashboard/partials/rot-live-clock.blade.php"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
test -f "$SCRIPT_DIR/apply.py" && test -f "$SCRIPT_DIR/verify.php"
python3 "$SCRIPT_DIR/apply.py" check "$APP"
php -l "$SCRIPT_DIR/verify.php"
BACKUP_DIR="$APP/storage/app/am-backups"
mkdir -p "$BACKUP_DIR"
BACKUP="$BACKUP_DIR/AM126_GH_STAGING_PREAPPLY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$SOURCE" "$BACKUP"
echo "AM126_WORKFLOW_BACKUP=$BACKUP"
rollback(){
  status="$?"
  trap - ERR
  echo "AM126_STAGING_QA_FAILED_ROLLBACK_TRIGGERED=$status"
  cp -p "$BACKUP" "$SOURCE" || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "AM126_ORIGINAL_LIVE_CLOCK_RESTORED"
  exit "$status"
}
trap rollback ERR
python3 "$SCRIPT_DIR/apply.py" apply "$APP"
php "$SCRIPT_DIR/verify.php"
STATUS="$(curl -sS -L --max-time 25 -o /dev/null -w '%{http_code}' https://staging.assammotors.com/erp/login)"
echo "AM126_STAGING_LOGIN_HEALTH=$STATUS"
test "$STATUS" = 200
trap - ERR
echo "AM126_GITHUB_STAGING_APPLY_AND_READONLY_QA_PASS"
echo "AM126_NO_PRODUCTIVE_TIME_OR_LEDGER_DB_WRITES"
