#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" && -n "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/ASSAM_MOTORS_FIX38_BACKUP_xxx"; exit 2; }
SRC="$BACKUP/resources/views/job-cards/form.blade.php"
[[ -f "$SRC" ]] || { echo "Backup missing: $SRC"; exit 3; }
cp -p "$SRC" "$APP_ROOT/resources/views/job-cards/form.blade.php"
php -l "$APP_ROOT/resources/views/job-cards/form.blade.php"
echo "FIX38 ROLLBACK COMPLETE"
