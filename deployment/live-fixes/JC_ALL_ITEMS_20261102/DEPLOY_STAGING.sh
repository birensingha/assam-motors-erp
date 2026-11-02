#!/usr/bin/env bash
# Customer print blocker: fix JC Summary display of ALL saved lines, STAGING ONLY.
set -Eeuo pipefail
APP="${1:?STAGING_LARAVEL_ROOT is required}"
cd "$APP"
test -f artisan
HERE="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
VIEW="$APP/resources/views/job-cards/summary-print.blade.php"
test -f "$VIEW"
python3 -m py_compile "$HERE/patch.py"
php -l "$HERE/verify.php"
python3 "$HERE/patch.py" check "$APP"

mkdir -p "$APP/storage/app/am-backups"
BACKUP="$APP/storage/app/am-backups/JC_FULL_ITEMS_PRE_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BACKUP"
echo "AM_ALL_LINES_BACKUP=$BACKUP"
rollback(){
  result="$?"
  trap - ERR
  cp -p "$BACKUP" "$VIEW" || true
  php artisan view:clear >/dev/null 2>&1 || true
  php artisan view:cache >/dev/null 2>&1 || true
  echo "AM_ALL_LINES_ROLLED_BACK_SOURCE_TO_BACKUP"
  exit "$result"
}
trap rollback ERR
python3 "$HERE/patch.py" apply "$APP"
php artisan view:clear
php artisan view:cache
php "$HERE/verify.php" | tee /tmp/am_all_lines_qa_$$.log
grep -Fxq "AM_ALL_LINES_RUNTIME_RENDER_PASS_NO_DATA_WRITES" /tmp/am_all_lines_qa_$$.log
rm -f /tmp/am_all_lines_qa_$$.log
code="$(curl -sSL --max-time 25 -o /dev/null -w '%{http_code}' 'https://staging.assammotors.com/erp/login')"
echo "AM_ALL_LINES_STAGING_LOGIN_HTTP=$code"
test "$code" = "200"
trap - ERR
echo "AM_ALL_LINES_STAGING_DEPLOY_AND_JC35_RENDER_VERIFIED"
