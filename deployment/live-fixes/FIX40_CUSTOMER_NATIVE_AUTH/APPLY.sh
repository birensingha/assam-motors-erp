#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"

set +e
bash "$DIR/CHECK.sh" "$APP_ROOT"
RC=$?
set -e

if [[ $RC -eq 5 ]]; then
  echo "FIX40 already appears installed"
  exit 0
fi
[[ $RC -eq 0 ]] || exit $RC

STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX40_BACKUP_$STAMP"
mkdir -p "$BACKUP/public/legacy/workshop"
cp -p "$APP_ROOT/public/legacy/workshop/customers.php" "$BACKUP/public/legacy/workshop/customers.php"
echo "BACKUP: $BACKUP"

php "$DIR/PATCH_FIX40.php" "$APP_ROOT"
php -l "$APP_ROOT/public/legacy/workshop/customers.php"
bash "$DIR/VERIFY.sh" "$APP_ROOT"

echo "FIX40 APPLY COMPLETE"
echo "Backup: $BACKUP"
echo "No DB migration was run."
