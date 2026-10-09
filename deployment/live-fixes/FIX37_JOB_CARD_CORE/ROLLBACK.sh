#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -d "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
for f in app/Http/Controllers/Web/AdminJobCardPageController.php resources/views/job-cards/show.blade.php routes/web.php; do cp -p "$BACKUP/$f" "$APP_ROOT/$f"; done
cd "$APP_ROOT"; php artisan route:clear >/dev/null 2>&1 || true; php artisan view:clear >/dev/null 2>&1 || true
echo "FIX37 ROLLBACK COMPLETE"
