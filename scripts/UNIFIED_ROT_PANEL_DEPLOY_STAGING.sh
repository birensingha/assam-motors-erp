#!/usr/bin/env bash
# Staging-only guarded unified ROT UI deployment. Preserves all DB and routes.
set -Eeuo pipefail
ROOT="${1:-$HOME/domains/assammotors.com/assam-erp-staging}"
cd "$ROOT"
VIEW="resources/views/job-cards/rot-alteration.blade.php"
test -f artisan && test -f "$VIEW"
SCRIPT="/tmp/UNIFIED_ROT_PANEL_APPLY_STAGING.py"
echo "AM116_DEPLOY_START"
python3 "$SCRIPT" check "$PWD"
mkdir -p storage/app/am-backups
BEFORE="storage/app/am-backups/AM116_BEFORE_DEPLOY_$(date +%Y%m%d_%H%M%S)_$$.blade.php"
cp -p "$VIEW" "$BEFORE"
rollback(){
  status=$?
  trap - ERR
  cp -p "$BEFORE" "$VIEW"
  php artisan view:clear >/dev/null 2>&1 || true
  echo "AM116_ROLLBACK_STAGING_RESTORED=$BEFORE"
  exit "$status"
}
trap rollback ERR
python3 "$SCRIPT" apply "$PWD"
php artisan view:clear
php artisan view:cache
compiled=$(grep -l 'AM116_UNIFIED_ROT_UI' storage/framework/views/*.php 2>/dev/null | head -1 || true)
if [ -z "$compiled" ]; then
  echo "STOP: updated ROT form not found in compiled Blade"; false
fi
php -l "$compiled"
trap - ERR
echo "AM116_DEPLOY_SOURCE_BLADE_COMPILED_OK"
echo "AM116_BACKUP=$BEFORE"
echo "AM116_DB_AND_ROUTES_UNCHANGED"
echo "NEXT: log in as Admin, open a WIP Job Card, verify one ROT Assignment card has Alter ROT; verify standalone P55 is absent; verify Save + reason, refresh, and audit. Browser result still unverified."
