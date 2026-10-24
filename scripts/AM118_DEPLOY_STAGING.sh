#!/usr/bin/env bash
# AM118: one-shot STAGING-only modal repair; backup-first, rollback on failed validation.
set -Eeuo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
test -f artisan
VIEW="resources/views/job-cards/show.blade.php"
SCRIPT="/tmp/AM118_MODAL_UNIFIED_APPLY.py"
test -f "$SCRIPT" && test -f "$VIEW"
echo "AM118_START"
python3 -m py_compile "$SCRIPT"
python3 "$SCRIPT" check "$PWD"
mkdir -p storage/app/am-backups
BACKUP="storage/app/am-backups/show_AM118_DEPLOY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BACKUP"
modified=0
rollback() {
  code=$?
  trap - ERR
  if [ "$modified" -eq 1 ]; then
    cp -p "$BACKUP" "$VIEW"
    php artisan view:clear >/dev/null 2>&1 || true
    echo "AM118_ROLLBACK_RESTORED=$BACKUP"
  fi
  exit "$code"
}
trap rollback ERR
modified=1
python3 "$SCRIPT" apply "$PWD"
php artisan view:clear
php artisan view:cache
compiled="$(grep -l 'AM118_UNIFIED_LABOUR_MODAL' storage/framework/views/*.php 2>/dev/null | head -1 || true)"
test -n "$compiled" || { echo "AM118_COMPILED_VIEW_MISSING"; false; }
php -l "$compiled"
trap - ERR
echo "AM118_SOURCE_AND_BLADE_PASS"
echo "AM118_BACKUP=$BACKUP"
echo "AM118_CONTROLLER_AND_DB_UNCHANGED"
echo "AM118_BROWSER_UNTESTED: Open WIP Job Card > Edit Labour Billing, check Delete ROT + Mechanic Assignment, then refresh."
