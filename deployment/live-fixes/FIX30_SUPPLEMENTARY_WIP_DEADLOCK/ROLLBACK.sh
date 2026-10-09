#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; BACKUP="${2:-}"
[[ -n "$APP_ROOT" && -d "$BACKUP" ]] || { echo "Usage: bash ROLLBACK.sh /path/to/app /path/to/backup"; exit 2; }
FILES=(app/Services/EstimateService.php app/Http/Controllers/Web/AdminEstimatePageController.php resources/views/job-cards/show.blade.php)
for f in "${FILES[@]}"; do cp -p "$BACKUP/$f" "$APP_ROOT/$f"; done
cd "$APP_ROOT"; php artisan view:clear >/dev/null 2>&1 || true
echo "FIX30 ROLLBACK COMPLETE"
