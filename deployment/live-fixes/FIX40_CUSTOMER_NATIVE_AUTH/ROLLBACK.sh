#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"
BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" && -n "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/ASSAM_MOTORS_FIX40_BACKUP_xxx"; exit 2; }
SRC="$BACKUP/public/legacy/workshop/customers.php"
[[ -f "$SRC" ]] || { echo "Backup file missing: $SRC"; exit 3; }
cp -p "$SRC" "$APP_ROOT/public/legacy/workshop/customers.php"
php -l "$APP_ROOT/public/legacy/workshop/customers.php"
echo "FIX40 ROLLBACK COMPLETE"
