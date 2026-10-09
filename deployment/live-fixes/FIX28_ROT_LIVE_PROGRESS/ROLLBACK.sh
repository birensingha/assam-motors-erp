#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -f "$BACKUP/public/legacy/api/staff-rot-v3.php" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
cp -p "$BACKUP/public/legacy/api/staff-rot-v3.php" "$APP_ROOT/public/legacy/api/staff-rot-v3.php"
php -l "$APP_ROOT/public/legacy/api/staff-rot-v3.php"
echo "FIX28 ROLLBACK COMPLETE"
