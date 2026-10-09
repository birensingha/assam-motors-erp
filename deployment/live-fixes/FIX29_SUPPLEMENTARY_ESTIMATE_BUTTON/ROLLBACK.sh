#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -f "$BACKUP/resources/views/job-cards/show.blade.php" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
cp -p "$BACKUP/resources/views/job-cards/show.blade.php" "$APP_ROOT/resources/views/job-cards/show.blade.php"
cd "$APP_ROOT"; php artisan view:clear >/dev/null 2>&1 || true
echo "FIX29 ROLLBACK COMPLETE"
