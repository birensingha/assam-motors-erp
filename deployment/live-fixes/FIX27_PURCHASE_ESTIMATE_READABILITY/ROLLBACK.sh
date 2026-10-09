#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -d "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
cp -p "$BACKUP/resources/views/purchases/create.blade.php" "$APP_ROOT/resources/views/purchases/create.blade.php"
cp -p "$BACKUP/resources/views/estimates/create.blade.php" "$APP_ROOT/resources/views/estimates/create.blade.php"
cd "$APP_ROOT"; php artisan view:clear >/dev/null 2>&1 || true
echo "FIX27 ROLLBACK COMPLETE"
