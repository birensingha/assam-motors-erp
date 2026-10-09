#!/usr/bin/env bash
set -euo pipefail
APP_ROOT="${1:-}"; [[ -n "$APP_ROOT" && -f "$APP_ROOT/artisan" ]] || { echo "Usage: bash APPLY.sh /path/to/app"; exit 2; }
DIR="$(cd "$(dirname "$0")" && pwd)"
bash "$DIR/CHECK.sh" "$APP_ROOT"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP="$(dirname "$APP_ROOT")/ASSAM_MOTORS_FIX30_BACKUP_$STAMP"
FILES=(app/Services/EstimateService.php app/Http/Controllers/Web/AdminEstimatePageController.php resources/views/job-cards/show.blade.php)
for f in "${FILES[@]}"; do mkdir -p "$BACKUP/$(dirname "$f")"; cp -p "$APP_ROOT/$f" "$BACKUP/$f"; done
echo "BACKUP: $BACKUP"
php "$DIR/PATCH_FIX30.php" "$APP_ROOT"
php -l "$APP_ROOT/app/Services/EstimateService.php"
php -l "$APP_ROOT/app/Http/Controllers/Web/AdminEstimatePageController.php"
php -l "$APP_ROOT/resources/views/job-cards/show.blade.php"
cd "$APP_ROOT"
php artisan view:clear >/dev/null 2>&1 || true
bash "$DIR/VERIFY.sh" "$APP_ROOT"
echo "FIX30 APPLY COMPLETE"
echo "Backup: $BACKUP"
